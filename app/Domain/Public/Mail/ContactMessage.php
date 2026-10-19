<?php

namespace App\Domain\Public\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Message reçu par le formulaire de contact du site.
 */
class ContactMessage extends Mailable implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $senderName,
        public readonly string $senderEmail,
        public readonly string $topic,
        public readonly string $body,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            replyTo: [new Address($this->senderEmail, $this->senderName)],
            subject: 'JE RELAIS · Contact ('.$this->topic.') : '.$this->senderName,
        );
    }

    public function content(): Content
    {
        return new Content(text: 'mail.contact');
    }
}
