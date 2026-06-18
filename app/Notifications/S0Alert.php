<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Support\Errors\Classification;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * S0Alert — o ÚNICO alerta que interrompe 1 humano (Fase 1 · E-1).
 *
 * Disparado pelo {@see \App\Support\Errors\ErrorReporter} só pra severidade S0,
 * rate-limited por dedupKey. Payload enxuto, **sem PII e sem trace** (LGPD) —
 * só o quê/onde (dedupKey)/quando.
 *
 * Mesmo transporte do {@see SellsSmokeFailedNotification}: HTTP POST direto
 * (sem channel), pra funcionar com qualquer webhook Slack-compatible / push /
 * WhatsApp de 1 pessoa (`config('errors.s0_channel')`).
 *
 * @see prototipo-ui/handoffs/erros-fase1-classificacao.md
 */
class S0Alert extends Notification
{
    use Queueable;

    public function __construct(public Classification $classification, public ?int $count = null) {}

    /** Sem channel — usamos HTTP direto (ver ErrorReporter::dispatchS0Alert). */
    public function via(object $notifiable): array
    {
        return [];
    }

    /**
     * Payload Slack-compatible (Block Kit). Sem PII, sem trace.
     *
     * @return array<string, mixed>
     */
    public function toWebhookPayload(): array
    {
        $c = $this->classification;
        $title = "🚨 S0 — {$c->owner} (interrompe humano)";

        return [
            'text' => $title,
            'blocks' => [
                [
                    'type' => 'header',
                    'text' => ['type' => 'plain_text', 'text' => $title, 'emoji' => true],
                ],
                [
                    'type' => 'section',
                    'text' => [
                        'type' => 'mrkdwn',
                        'text' => "*Severidade:* {$c->severity->value}\n"
                            ."*Dono:* {$c->owner}\n"
                            .($this->count !== null ? "*Ocorrências:* {$this->count} no grupo\n" : '')
                            ."*Grupo (dedup):* `{$c->dedupKey}`",
                    ],
                ],
                [
                    'type' => 'context',
                    'elements' => [[
                        'type' => 'mrkdwn',
                        'text' => '*Ambiente:* '.app()->environment()
                            .' | *Quando:* '.now()->format('Y-m-d H:i:s').' BRT',
                    ]],
                ],
            ],
        ];
    }
}
