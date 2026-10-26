<?php

namespace App\Mail;

use App\Filament\Resources\Bookings\BookingResource;
use App\Models\Booking;
use App\Services\BookingDocuments;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\URL;

/**
 * Email relative a una pratica.
 *
 * Destinatari:
 * - "customer": cliente diretto o agenzia titolare della pratica;
 * - "operator": il tour operator (notifiche interne).
 *
 * Eventi: quote, option, option_reminder, option_expired, confirmed,
 * proforma_viewed, deposit_received, paid, cancelled.
 */
class BookingMail extends Mailable
{
    use Queueable, SerializesModels;

    public const CUSTOMER = 'customer';

    public const OPERATOR = 'operator';

    public function __construct(
        public Booking $booking,
        public string $event,
        public string $audience = self::CUSTOMER,
        public ?int $hoursLeft = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->subjectLine());
    }

    public function content(): Content
    {
        $booking = $this->booking->loadMissing(['items', 'passengers', 'agency', 'user']);
        $forAgency = $booking->isAgencyBooking();

        return new Content(markdown: 'mail.booking', with: [
            'booking' => $booking,
            'isOperator' => $this->audience === self::OPERATOR,
            'forAgency' => $forAgency,
            'headline' => $this->subjectLine(),
            'intro' => $this->intro(),
            'button' => $this->button(),
        ]);
    }

    /**
     * Il preventivo viene allegato in PDF.
     *
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        if ($this->event !== 'quote' || $this->audience !== self::CUSTOMER) {
            return [];
        }

        return [
            Attachment::fromData(fn () => app(BookingDocuments::class)->quoteOutput($this->booking), "Preventivo-{$this->booking->code}.pdf")
                ->withMime('application/pdf'),
        ];
    }

    private function subjectLine(): string
    {
        $code = $this->booking->code;

        if ($this->audience === self::OPERATOR) {
            $who = $this->booking->agency?->company_name ?? $this->booking->contactFullName();

            return match ($this->event) {
                'option' => "Nuova opzione {$code} - {$who}",
                'confirmed' => "Nuova conferma {$code} - {$who}",
                'proforma_viewed' => "Proforma aperta {$code} - {$who}",
                default => "Pratica {$code} - {$who}",
            };
        }

        return match ($this->event) {
            'quote' => "Il tuo preventivo {$code}",
            'option' => "Opzione attiva sulla pratica {$code}",
            'option_reminder' => "La tua opzione {$code} scade tra {$this->hoursLeft} ".($this->hoursLeft === 1 ? 'ora' : 'ore'),
            'option_expired' => "Opzione scaduta - pratica {$code}",
            'confirmed' => "Prenotazione confermata - pratica {$code}",
            'deposit_received' => "Acconto ricevuto - pratica {$code}",
            'paid' => "Pagamento completato - pratica {$code}",
            'cancelled' => "Pratica {$code} annullata",
            default => "Aggiornamento pratica {$code}",
        };
    }

    private function intro(): string
    {
        $booking = $this->booking;
        $agency = $booking->isAgencyBooking();
        $expires = $booking->option_expires_at?->timezone('Europe/Rome')->format('d/m/Y \a\l\l\e H:i');

        if ($this->audience === self::OPERATOR) {
            return match ($this->event) {
                'option' => "È stata messa in opzione una nuova pratica (scadenza {$expires}). La disponibilità è bloccata fino alla scadenza.",
                'confirmed' => $agency
                    ? "L'agenzia {$booking->agency->company_name} ha confermato la pratica. La proforma è stata generata e il link è stato inviato all'agenzia."
                    : 'Un cliente ha confermato la prenotazione. Contattalo per le istruzioni di pagamento.',
                'proforma_viewed' => "L'agenzia {$booking->agency?->company_name} ha aperto la proforma: la prenotazione è ora definitiva e in attesa dell'acconto.",
                default => 'Aggiornamento della pratica.',
            };
        }

        return match ($this->event) {
            'quote' => 'Ecco il preventivo richiesto, in allegato in PDF. Il preventivo non blocca la disponibilità'
                .($booking->price_lock ? ', ma il prezzo è garantito grazie all\'opzione "Blocca prezzo".' : ': prezzi e disponibilità saranno verificati alla conferma.')
                .($booking->quote_valid_until ? " È valido fino al {$booking->quote_valid_until->format('d/m/Y')}." : ''),
            'option' => "La disponibilità è bloccata per te fino al {$expires}. Entro questa data puoi confermare la prenotazione; dopo l'opzione scade automaticamente.",
            'option_reminder' => "La tua opzione scade il {$expires}. Se vuoi mantenere la disponibilità, conferma la prenotazione prima della scadenza.",
            'option_expired' => "L'opzione è scaduta e la disponibilità è stata liberata. Se sei ancora interessato puoi creare una nuova richiesta dal sito.",
            'confirmed' => $agency
                ? 'La prenotazione è confermata. Apri la proforma per renderla definitiva: troverai l\'importo dovuto, l\'acconto e le coordinate per il bonifico.'
                : 'La tua prenotazione è confermata. A breve verrai contattato da Queen Tour con le istruzioni per il pagamento.',
            'deposit_received' => 'Abbiamo ricevuto l\'acconto: la tua prenotazione è garantita. Grazie!',
            'paid' => 'Il pagamento è stato completato. Grazie per aver scelto Queen Tour, buon viaggio!',
            'cancelled' => 'La pratica è stata annullata. Per qualsiasi informazione rispondi a questa email.',
            default => 'La tua pratica è stata aggiornata.',
        };
    }

    /**
     * @return array{label: string, url: string}
     */
    private function button(): array
    {
        if ($this->audience === self::OPERATOR) {
            return ['label' => 'Apri nel pannello', 'url' => BookingResource::getUrl('view', ['record' => $this->booking], panel: 'admin')];
        }

        if ($this->event === 'confirmed' && $this->booking->isAgencyBooking() && $this->booking->proforma_sent_at) {
            return ['label' => 'Apri la proforma', 'url' => URL::signedRoute('api.bookings.proforma', ['booking' => $this->booking->code])];
        }

        return ['label' => 'Vedi la pratica', 'url' => $this->booking->publicUrl()];
    }
}
