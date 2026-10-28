<?php

namespace App\Mail;

use App\Models\SosAlert;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SosAlertNotification extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public SosAlert $alert) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Alerta SOS #{$this->alert->id} de RESGUARDA",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.sos-alert',
            text: 'mail.sos-alert-text',
            with: [
                'alertId' => $this->alert->id,
                'createdAt' => $this->alert->created_at?->format('d/m/Y H:i:s T'),
                'latitude' => $this->alert->latitude,
                'longitude' => $this->alert->longitude,
            ],
        );
    }
}
