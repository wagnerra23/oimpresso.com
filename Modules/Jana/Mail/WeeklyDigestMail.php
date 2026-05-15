<?php

declare(strict_types=1);

namespace Modules\Jana\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * WeeklyDigestMail — envia o Weekly Digest Reflect-style ao destinatário.
 *
 * AUDITORIA-MEMORIA-2026-05-15 §D8 #6 — fecha o gap "Weekly digest populado":
 *  - Comando `jana:weekly-digest` já gera markdown + persiste em DB
 *    (`WeeklyDigestService`) com 5 seções Reflect-style.
 *  - Faltava: ENTREGAR pro Wagner por email toda segunda 09h BRT.
 *
 * Pattern Laravel Markdown Mailable (Mail::markdown / Content::markdown).
 * Auto-gera HTML responsivo + texto plain a partir do mesmo Blade.
 *
 * Schedule já registrado em `app/Console/Kernel.php` (mondays 09:00 BRT,
 * timezone America/Sao_Paulo, withoutOverlapping, env=live).
 *
 * Multi-tenant Tier 0 (ADR 0093): destinatário derivado de
 * `Business::find($businessId)->owner->email`. Sem hardcode PII.
 */
class WeeklyDigestMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param string $semana ISO 8601 week (YYYY-Www)
     * @param string $rangeInicio data início (YYYY-MM-DD)
     * @param string $rangeFim data fim (YYYY-MM-DD)
     * @param string $digestMarkdown corpo digest gerado pelo LLM (5 seções)
     * @param array<string, int|string> $metrics métricas coletadas
     * @param string $businessName nome do business (rótulo)
     * @param string|null $dashboardUrl URL absoluta pro cockpit governance (opcional)
     */
    public function __construct(
        public readonly string $semana,
        public readonly string $rangeInicio,
        public readonly string $rangeFim,
        public readonly string $digestMarkdown,
        public readonly array $metrics,
        public readonly string $businessName,
        public readonly ?string $dashboardUrl = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Jana — Weekly Digest {$this->semana} ({$this->rangeInicio} → {$this->rangeFim})",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'copiloto::emails.weekly-digest',
            with: [
                'semana' => $this->semana,
                'rangeInicio' => $this->rangeInicio,
                'rangeFim' => $this->rangeFim,
                'businessName' => $this->businessName,
                'digestBody' => $this->digestMarkdown,
                'metrics' => $this->metrics,
                'dashboardUrl' => $this->dashboardUrl ?? rtrim((string) config('app.url'), '/') . '/governance',
            ],
        );
    }
}
