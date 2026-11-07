<?php

namespace App\Domain\Reporting\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Rapport périodique (quotidien, hebdomadaire, mensuel) envoyé à l'administration et à la rédaction en chef (§13).
 */
class PeriodicReport extends Mailable
{
    use Queueable;

    public function __construct(
        public readonly string $label,
        public readonly string $periodText,
        public readonly string $pdf,
        public readonly string $xlsxPath,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'JE RELAIS · Rapport '.$this->label.' · '.$this->periodText);
    }

    public function content(): Content
    {
        return new Content(text: 'mail.periodic-report');
    }

    /**
     * @return list<Attachment>
     */
    public function attachments(): array
    {
        return [
            Attachment::fromData(fn (): string => $this->pdf, 'rapport-je-relais.pdf')->withMime('application/pdf'),
            Attachment::fromPath($this->xlsxPath)->as('rapport-je-relais.xlsx')->withMime('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'),
        ];
    }
}
