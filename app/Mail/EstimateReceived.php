<?php

namespace App\Mail;

use App\Models\EstimateRequest;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class EstimateReceived extends Mailable
{
    public function __construct(public EstimateRequest $estimate) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'طلب مقايسة جديد - '.$this->estimate->name,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.estimate-received',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
