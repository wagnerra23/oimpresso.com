<?php

declare(strict_types=1);

namespace Modules\Arquivos\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * E-mail do aviso ao titular (ADR 0421 + 0422).
 *
 * Texto genérico de propósito: NÃO leva nome de arquivo, caminho nem conteúdo. Se o e-mail
 * cair na caixa errada, ele revela só que existe um documento guardado e quando vence.
 */
class AvisoTitularMail extends Mailable
{
    use Queueable;

    public function __construct(
        public readonly string $empresa,
        public readonly string $nomeTitular,
        public readonly string $venceEm,
    ) {
    }

    public static function texto(string $empresa, string $nomeTitular, string $venceEm): string
    {
        $saudacao = trim($nomeTitular) !== '' ? "Olá, {$nomeTitular}." : 'Olá.';

        return $saudacao . "\n\n"
            . "{$empresa} guarda um documento com dados pessoais seus, e o prazo de guarda dele vence em {$venceEm}.\n"
            . "Depois dessa data o documento pode ser eliminado, conforme a política de retenção da empresa.\n\n"
            . "Se você quiser uma cópia, ou tiver qualquer pedido sobre os seus dados (LGPD, Art. 18), "
            . "responda esta mensagem ou fale com {$empresa} antes do vencimento.";
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: "{$this->empresa}: documento com seus dados vence em {$this->venceEm}");
    }

    public function content(): Content
    {
        return new Content(htmlString: nl2br(e(self::texto($this->empresa, $this->nomeTitular, $this->venceEm))));
    }
}
