<?php

namespace App\Mail;

use App\Filament\Resources\Users\UserResource;
use App\Models\Agency;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Nuova agenzia registrata: benvenuto all'agenzia ("customer") oppure avviso
 * al tour operator ("operator").
 */
class AgencyRegisteredMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Agency $agency, public string $audience = BookingMail::CUSTOMER) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->audience === BookingMail::OPERATOR
            ? "Nuova agenzia registrata: {$this->agency->company_name}"
            : 'Benvenuto nell\'area agenzie');
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.agency-registered', with: [
            'agency' => $this->agency->loadMissing('user'),
            'isOperator' => $this->audience === BookingMail::OPERATOR,
            'panelUrl' => UserResource::getUrl('edit', ['record' => $this->agency->user_id], panel: 'admin'),
        ]);
    }
}
