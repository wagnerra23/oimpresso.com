<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Notificação Slack quando `sells:smoke-daily --notify` detecta drift.
 *
 * Gap catalogado em "NÃO INCLUI" da Onda 6 (PR #1044 mergeado):
 * smoke loga ALERT em `Log::channel('single')->error(...)`, mas Wagner
 * só descobre via `tail` manual. Plug Slack Incoming Webhook escala
 * pra time MCP (Felipe/Maiara/Eliana/Luiz) sem mexer em monitoring.
 *
 * **Por que NÃO via('slack') channel:** o pacote oficial
 * `laravel/slack-notification-channel` NÃO está em composer.json hoje
 * e Wagner pediu pra evitar `composer require` neste gap (escopo
 * cirúrgico). Implementação minimalista via HTTP POST + payload
 * Block Kit simples mantém zero deps novos e funciona com qualquer
 * Incoming Webhook URL Slack-compatible (Mattermost, Discord webhook
 * Slack-format, etc).
 *
 * O comando `SmokeDailyCommand::handle()` chama `toSlackPayload()`
 * e dispara `Http::post($url, $payload)` dentro de try/catch graceful
 * (Slack down NÃO derruba smoke — só loga warning).
 *
 * **Refs:**
 *  - memory/requisitos/Sells/RUNBOOK-smoke-cowork.md (gap "Próximos passos")
 *  - app/Console/Commands/Sells/SmokeDailyCommand.php (caller)
 *  - tests/Feature/Sells/SellsSmokeSlackNotifyTest.php (cobertura estrutural)
 *
 * @example
 *   $notification = new SellsSmokeFailedNotification(['schema: ...', 'tenancy: ...']);
 *   Http::post($webhookUrl, $notification->toSlackPayload());
 */
class SellsSmokeFailedNotification extends Notification
{
    use Queueable;

    /**
     * @param  array<int, string>  $failures  Lista de falhas detectadas (≥1)
     */
    public function __construct(public array $failures)
    {
    }

    /**
     * Canais de entrega — vazio porque usamos HTTP direto (sem channel).
     *
     * Se algum dia adicionarmos `laravel/slack-notification-channel`,
     * trocar pra `['slack']` + implementar `toSlack(SlackMessage)`.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return [];
    }

    /**
     * Payload Slack Block Kit (Incoming Webhook format).
     *
     * Render estimado no Slack:
     * ```
     * 🚨 sells:smoke-daily FALHOU — 2 check(s)
     *
     * • tenancy: biz=4 (ROTA LIVRE piloto) ZERO vendas 30d — CRÍTICO
     * • manifest: chunks Cowork ausentes — SaleSheet,SaleAiPanel
     *
     * Ambiente: live | Quando: 2026-05-18 06:31:02
     * Action: verificar storage/logs/laravel.log e deploy SSH Hostinger
     * ```
     *
     * @return array<string, mixed>
     */
    public function toSlackPayload(): array
    {
        $count = count($this->failures);
        $bullets = implode("\n", array_map(fn (string $f) => "• {$f}", $this->failures));

        return [
            'text' => "🚨 sells:smoke-daily FALHOU — {$count} check(s)",
            'blocks' => [
                [
                    'type' => 'header',
                    'text' => [
                        'type' => 'plain_text',
                        'text' => "🚨 sells:smoke-daily FALHOU — {$count} check(s)",
                        'emoji' => true,
                    ],
                ],
                [
                    'type' => 'section',
                    'text' => [
                        'type' => 'mrkdwn',
                        'text' => "*Sells/Index Cowork drift detectado*\n\n{$bullets}",
                    ],
                ],
                [
                    'type' => 'context',
                    'elements' => [
                        [
                            'type' => 'mrkdwn',
                            'text' => '*Ambiente:* '.app()->environment()
                                .' | *Quando:* '.now()->format('Y-m-d H:i:s')
                                .' BRT',
                        ],
                    ],
                ],
                [
                    'type' => 'context',
                    'elements' => [
                        [
                            'type' => 'mrkdwn',
                            'text' => '*Action:* verificar `storage/logs/laravel.log`'
                                .' e deploy SSH Hostinger (último commit em `claude/sells-*`)',
                        ],
                    ],
                ],
            ],
        ];
    }
}
