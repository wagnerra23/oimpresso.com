<?php

declare(strict_types=1);

namespace Modules\NfeBrasil\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Modules\NfeBrasil\Models\NfeEmissao;

/**
 * E-mail enviado ao destinatário quando NF-e é autorizada.
 *
 * Anexos:
 *   - DANFE PDF (`{chave_44}.pdf`)
 *   - XML autorizado (`{chave_44}.xml`)
 *
 * Disparado pelo listener `EnviarDanfePorEmail` (consumindo `NFeAutorizada`).
 *
 * Body inline (sem template Blade) — fluxo recorrente automático,
 * sem branding sofisticado por agora. Quando US-NFE-044 fase 2 chegar
 * com template visual, trocar `Content::view()`.
 */
class DanfeNotaFiscalMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly NfeEmissao $emissao,
        public readonly string $danfePdfBytes,
        public readonly string $xmlString,
        public readonly ?string $razaoEmissora = null,
    ) {}

    public function envelope(): Envelope
    {
        $razao = $this->razaoEmissora ?? 'oimpresso';
        return new Envelope(
            subject: "NF-e {$this->emissao->numero} autorizada — {$razao}",
        );
    }

    public function content(): Content
    {
        $linhas = [
            "Olá,",
            '',
            "Segue em anexo a Nota Fiscal Eletrônica autorizada referente ao seu pagamento.",
            '',
            "Número:        {$this->emissao->numero}",
            "Série:         {$this->emissao->serie}",
            "Chave acesso:  {$this->emissao->chave_44}",
            "Valor total:   R\$ " . number_format((float) $this->emissao->valor_total, 2, ',', '.'),
            "Data emissão:  " . optional($this->emissao->emitido_em)->format('d/m/Y H:i'),
            '',
            'Anexos:',
            '  - DANFE em PDF (impressão/visualização)',
            '  - XML autorizado (uso fiscal/contábil)',
            '',
            'Esta é uma notificação automática.',
        ];

        return new Content(
            view: 'nfebrasil::mail.danfe-html',
            text: 'nfebrasil::mail.danfe-text',
            with: [
                'linhas'         => $linhas,
                'emissao'        => $this->emissao,
                'razaoEmissora'  => $this->razaoEmissora,
            ],
        );
    }

    public function attachments(): array
    {
        $chave = $this->emissao->chave_44 ?: 'nota';

        return [
            Attachment::fromData(fn () => $this->danfePdfBytes, "{$chave}.pdf")
                ->withMime('application/pdf'),
            Attachment::fromData(fn () => $this->xmlString, "{$chave}.xml")
                ->withMime('application/xml'),
        ];
    }
}
