<?php

namespace App\Services;

use App\Enums\BookingAction;
use App\Enums\BookingStatus;
use App\Mail\AgencyRegisteredMail;
use App\Mail\BookingMail;
use App\Models\Agency;
use App\Models\Booking;
use Closure;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Invio delle email legate a pratiche e agenzie.
 *
 * - Cliente diretto: all'email del referente.
 * - Agenzia: all'email aziendale, con l'operatore in copia.
 * - Tour operator: all'indirizzo di notifica (config tourflow.notification_email),
 *   solo per opzioni, conferme, proforma aperte e nuove agenzie.
 *
 * Le email partono dopo la risposta HTTP (il cliente non aspetta l'SMTP) e un
 * errore di invio non blocca mai l'operazione: viene solo registrato nei log.
 */
class BookingNotifier
{
    public function bookingSaved(Booking $booking, BookingAction $action): void
    {
        $event = match ($action) {
            BookingAction::Quote => 'quote',
            BookingAction::Option => 'option',
            BookingAction::Confirm => 'confirmed',
        };

        $this->toCustomer($booking, new BookingMail($booking, $event));

        if ($action !== BookingAction::Quote) {
            $this->toOperator(new BookingMail($booking, $event, BookingMail::OPERATOR));
        }
    }

    public function optionReminder(Booking $booking, int $hoursLeft): void
    {
        $this->toCustomer($booking, new BookingMail($booking, 'option_reminder', hoursLeft: $hoursLeft));
    }

    public function optionExpired(Booking $booking): void
    {
        $this->toCustomer($booking, new BookingMail($booking, 'option_expired'));
    }

    public function proformaViewed(Booking $booking): void
    {
        $this->toOperator(new BookingMail($booking, 'proforma_viewed', BookingMail::OPERATOR));
    }

    /**
     * Cambio di stato deciso dal tour operator nel pannello.
     */
    public function statusChanged(Booking $booking, BookingStatus $status): void
    {
        $event = match ($status) {
            BookingStatus::DepositReceived => 'deposit_received',
            BookingStatus::Paid => 'paid',
            BookingStatus::Cancelled => 'cancelled',
            default => null,
        };

        if ($event) {
            $this->toCustomer($booking, new BookingMail($booking, $event));
        }
    }

    public function agencyRegistered(Agency $agency): void
    {
        $this->deliver(fn () => Mail::to($agency->email ?: $agency->user->email)
            ->cc($this->ccOperator($agency->email, $agency->user->email))
            ->send(new AgencyRegisteredMail($agency)));

        $this->toOperator(new AgencyRegisteredMail($agency, BookingMail::OPERATOR));
    }

    private function toCustomer(Booking $booking, Mailable $mail): void
    {
        $booking->loadMissing(['agency', 'user']);

        if ($booking->agency) {
            $to = $booking->agency->email ?: $booking->user?->email;
            $cc = $this->ccOperator($to, $booking->user?->email);
        } else {
            $to = $booking->contact_email;
            $cc = [];
        }

        if (blank($to)) {
            return;
        }

        $this->deliver(fn () => Mail::to($to)->cc($cc)->send($mail));
    }

    private function toOperator(Mailable $mail): void
    {
        $to = config('tourflow.notification_email');

        if (filled($to)) {
            $this->deliver(fn () => Mail::to($to)->send($mail));
        }
    }

    /**
     * Operatore in copia, se diverso dal destinatario principale.
     *
     * @return array<int, string>
     */
    private function ccOperator(?string $to, ?string $operator): array
    {
        return $operator && strcasecmp((string) $to, $operator) !== 0 ? [$operator] : [];
    }

    private function deliver(Closure $send): void
    {
        $run = function () use ($send) {
            try {
                $send();
            } catch (Throwable $e) {
                report($e);
            }
        };

        // Da riga di comando (scheduler, test) si invia subito; via web dopo la risposta.
        app()->runningInConsole() ? $run() : app()->terminating($run);
    }
}
