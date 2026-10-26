<?php

declare(strict_types=1);

namespace Modules\NfeBrasil\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * US-NFE-073 · XML do mês para o contador. Corpo inline, sem template (como o RevisaoContadorMail).
 * Avisa quantas notas ficaram sem XML — o contador precisa saber que o pacote está incompleto.
 */
class PacoteXmlContadorMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $empresa,
        public readonly string $competencia,
        public readonly int $qtd,
        public readonly int $semXml,
        public readonly ?string $zipPath,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "XML das notas fiscais {$this->mesAno()} — {$this->empresa}");
    }

    public function content(): Content
    {
        $linhas = [
            "Segue o XML das notas fiscais de {$this->mesAno()} de {$this->empresa}: {$this->qtd} arquivo(s) no ZIP anexo.",
        ];
        if ($this->semXml > 0) {
            $linhas[] = "Atenção: {$this->semXml} nota(s) autorizada(s) ou cancelada(s) do mês não tiveram o XML encontrado e não estão no pacote.";
        }

        return new Content(htmlString: '<p>' . implode('<br>', array_map(fn (string $l) => e($l), $linhas)) . '</p>');
    }

    /** @return list<Attachment> */
    public function attachments(): array
    {
        if ($this->zipPath === null) {
            return [];
        }

        return [Attachment::fromPath($this->zipPath)->as("xml-{$this->competencia}.zip")->withMime('application/zip')];
    }

    private function mesAno(): string
    {
        [$a, $m] = explode('-', $this->competencia);

        return "{$m}/{$a}";
    }
}
