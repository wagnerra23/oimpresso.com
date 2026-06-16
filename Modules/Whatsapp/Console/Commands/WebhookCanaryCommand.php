<?php

declare(strict_types=1);

namespace Modules\Whatsapp\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Canário do webhook Whatsmeow — Fase 1 (camadas 1 + 5) do padrão de ingestão
 * perda-zero (proposta memory/decisions/proposals/whatsapp-ingestao-perda-zero.md).
 *
 * POSTa um evento sintético BENIGNO (`Presence`, envelope real do WuzAPI
 * `{instanceName, jsonData}`) na PRÓPRIA URL pública do webhook com o segredo
 * `?wh=<secret>` e confere HTTP **200**. Não-200 (401/500/timeout) → ALERT
 * imediato (Log::error + mcp_alertas_eventos se existir) + exit não-zero.
 *
 * **Por que pega o #2726:** o incidente (hardening de auth deixou o recebimento
 * morto 3 dias sem ninguém ver) seria detectado em <5min — o canário prova
 * continuamente o caminho público inteiro (TLS → edge → rota → middleware de
 * auth → controller). Combinado com a janela de retry do daemon (~10-15 min),
 * detectar+corrigir dentro dela = mensagens re-entregues = perda zero, SEM
 * reescrever durabilidade.
 *
 * **Por que é benigno:** um evento `Presence` cujo `instanceName` não casa
 * nenhum channel cai no ramo `no_channel` do WhatsmeowWebhookController e é
 * ACKado em 200 SEM criar mensagem, sem dispatch de job, sem escrita no DB
 * (provado por WhatsmeowWebhookAuthTest "contrato REAL do daemon"). O canário
 * só OBSERVA a via — não altera o recebimento.
 *
 * **Camada 5 (auto-teste do alarme):** grava o resultado de cada tick em cache
 * (`self::CACHE_KEY`); o `jana:health-check` lê (check `whatsapp_inbound_canary`)
 * pra detectar se o próprio canário apodreceu (cron morto = sem provas frescas).
 *
 * **Multi-tenant Tier 0 (ADR 0093):** o canário não toca dados de tenant — só
 * posta um evento sintético e lê config. O business_uuid do alvo é só o
 * endereço público da rota (já é parte da URL que o daemon usa).
 *
 * Uso:
 *   php artisan whatsapp:webhook-canary
 *   php artisan whatsapp:webhook-canary --json
 *   php artisan whatsapp:webhook-canary --url=https://staging.../api/whatsapp/webhook/whatsmeow/<uuid>
 *
 * @see Modules/Whatsapp/Http/Middleware/VerifyWhatsmeowSignature.php (auth ?wh=)
 * @see Modules/Whatsapp/Http/Controllers/Api/WhatsmeowWebhookController.php (ACK 200)
 * @see Modules/Jana/Console/Commands/HealthCheckCommand.php (check whatsapp_inbound_canary)
 */
class WebhookCanaryCommand extends Command
{
    protected $signature = 'whatsapp:webhook-canary
                            {--json : Output JSON em vez de texto}
                            {--url= : Override do alvo (default = rota pública whatsmeow do business configurado)}';

    protected $description = 'Canário do webhook WhatsApp — prova que o recebimento responde 200 (<5min); alerta + exit≠0 se quebrou (incidente #2726)';

    /**
     * Chave de cache do último resultado (lida pelo jana:health-check). Literal
     * compartilhado de propósito (acoplamento mínimo entre módulos): o check de
     * frescor funciona mesmo sem o módulo Whatsapp carregado.
     */
    public const CACHE_KEY = 'whatsapp:webhook-canary:last';

    /** Nome identificável nos logs/access-log do receiver — não casa channel real. */
    private const SYNTHETIC_INSTANCE = 'oimpresso-webhook-canary';

    public function handle(): int
    {
        if (! (bool) config('whatsapp.canary.enabled', true)) {
            return $this->report(['skipped' => true, 'reason' => 'canário desabilitado (whatsapp.canary.enabled=false)'], self::SUCCESS);
        }

        $secret = (string) config('whatsapp.whatsmeow.webhook_url_secret', '');
        if ($secret === '') {
            // Sem segredo o caminho ?wh= é inerte (dev/CI) — nada a provar. Não
            // grava cache: o check de frescor trata "ausente" como cold-start.
            return $this->report(['skipped' => true, 'reason' => 'WHATSMEOW_WEBHOOK_URL_SECRET não configurado (dev/CI)'], self::SUCCESS);
        }

        $baseUrl = $this->resolveBaseUrl();
        if ($baseUrl === null) {
            return $this->report(['skipped' => true, 'reason' => 'sem business alvo (config whatsapp.canary.business_uuid e nenhum channel whatsmeow ativo)'], self::SUCCESS);
        }

        $host = (string) (parse_url($baseUrl, PHP_URL_HOST) ?: $baseUrl);
        $timeout = (int) config('whatsapp.canary.timeout_seconds', 10);
        $status = null;
        $error = null;

        try {
            $response = Http::timeout($timeout)
                ->withHeaders(['Content-Type' => 'application/json'])
                ->withBody(self::syntheticPresenceBody(), 'application/json')
                ->post($baseUrl . '?wh=' . rawurlencode($secret));
            $status = $response->status();
        } catch (\Throwable $e) {
            // Timeout / conexão recusada / DNS — tudo conta como quebra do caminho.
            $error = mb_substr($e->getMessage(), 0, 120);
        }

        $r = self::evaluateCanary($status, $error);

        // Camada 5: registra o tick (TTL longo — o frescor é computado pelo
        // timestamp, não pela expiração da chave, pra distinguir stale de cold).
        Cache::put(self::CACHE_KEY, [
            'ok' => $r['ok'],
            'status' => $r['status'],
            'reason' => $r['reason'],
            'at' => now()->toIso8601String(),
        ], now()->addDay());

        if ($r['ok']) {
            return $this->report([
                'ok' => true,
                'status' => $r['status'],
                'reason' => $r['reason'],
                'target_host' => $host,
            ], self::SUCCESS);
        }

        // ── Quebra: ALERT imediato (sem segredo no log) + exit≠0 ───────────────
        $msg = "ALERTA recebimento WhatsApp: canário do webhook falhou — {$r['reason']} "
            . '(classe do incidente #2726: webhook recusando/indisponível; mensagens '
            . 'em risco até a janela de retry do daemon ~10-15min expirar). '
            . "Checar auth/rota/worker/edge/daemon. Host: {$host}";
        Log::channel('single')->error($msg);
        $this->persistAlert($r);

        return $this->report([
            'ok' => false,
            'status' => $r['status'],
            'reason' => $r['reason'],
            'target_host' => $host,
        ], self::FAILURE);
    }

    /**
     * Lógica pura do veredito — pública e estática pra ser testável sem rede
     * (mesmo padrão de HealthCheckCommand::evaluateInboundFlow/parseLessonLedger).
     *
     * @return array{ok: bool, status: ?int, reason: string}
     */
    public static function evaluateCanary(?int $status, ?string $error, int $expectedStatus = 200): array
    {
        if ($error !== null) {
            return ['ok' => false, 'status' => null, 'reason' => "erro de conexão: {$error}"];
        }
        if ($status === $expectedStatus) {
            return ['ok' => true, 'status' => $status, 'reason' => "HTTP {$status}"];
        }

        return ['ok' => false, 'status' => $status, 'reason' => "HTTP {$status} (esperado {$expectedStatus})"];
    }

    /**
     * Envelope idêntico ao que o daemon WuzAPI emite: `{instanceName, jsonData}`
     * com jsonData = JSON-string aninhada {event, type}. Evento `Presence` com
     * instanceName sintético → controller ACKa 200 `no_channel` sem efeito.
     */
    public static function syntheticPresenceBody(): string
    {
        return (string) json_encode([
            'instanceName' => self::SYNTHETIC_INSTANCE,
            'jsonData' => json_encode([
                'event' => ['Info' => ['Chat' => '']],
                'type' => 'Presence',
            ]),
        ]);
    }

    /**
     * URL base (sem o `?wh=`) da rota pública do webhook. Override via --url;
     * senão resolve o business_uuid alvo (config OU primeiro channel whatsmeow
     * ativo) e gera a rota canônica.
     */
    private function resolveBaseUrl(): ?string
    {
        $override = (string) ($this->option('url') ?? '');
        if ($override !== '') {
            // Remove qualquer ?wh= que o operador tenha colado — o segredo é
            // sempre re-anexado a partir da config (evita vazar no histórico).
            return (string) strtok($override, '?');
        }

        $uuid = $this->resolveBusinessUuid();
        if ($uuid === null) {
            return null;
        }

        return route('whatsapp.webhook.whatsmeow.handle', ['business_uuid' => $uuid]);
    }

    private function resolveBusinessUuid(): ?string
    {
        $configured = (string) config('whatsapp.canary.business_uuid', '');
        if ($configured !== '') {
            return $configured;
        }

        // Sem config: deriva do business do primeiro channel whatsmeow ativo
        // (cross-tenant read intencional — comando ops, sem session). Tier 0:
        // só lê o uuid público da rota, não dados de negócio.
        try {
            if (! Schema::hasTable('channels') || ! Schema::hasTable('business')) {
                return null;
            }
            $businessId = DB::table('channels')
                ->where('type', \Modules\Whatsapp\Entities\Channel::TYPE_WHATSAPP_WHATSMEOW)
                ->where('status', 'active')
                ->min('business_id');
            if ($businessId === null) {
                return null;
            }
            $uuid = DB::table('business')->where('id', (int) $businessId)->value('uuid');

            return is_string($uuid) && $uuid !== '' ? $uuid : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Persiste alerta idempotente (1×/dia) em mcp_alertas_eventos se a tabela
     * existir. Espelha o padrão PersistsDriftAlert (Governance). Falha de
     * persistência NUNCA derruba o canário (o Log::error já é o sinal realtime).
     *
     * @param  array{ok: bool, status: ?int, reason: string}  $r
     */
    private function persistAlert(array $r): void
    {
        try {
            if (! Schema::hasTable('mcp_alertas_eventos')) {
                return;
            }
            $chave = mb_substr('whatsapp_webhook_canary:' . now()->format('Y-m-d'), 0, 200);
            if (DB::table('mcp_alertas_eventos')->where('chave_idempotencia', $chave)->exists()) {
                return;
            }
            DB::table('mcp_alertas_eventos')->insert([
                'user_id' => null,
                'business_id' => null, // repo-wide: alerta de infra do caminho de webhook (ADR 0093 §Exceção mcp_*)
                'tipo' => 'whatsapp_webhook_canary',
                'severidade' => 'high',
                'titulo' => 'Canário do webhook WhatsApp falhou',
                'descricao' => "Recebimento WhatsApp pode estar parado — {$r['reason']} (classe #2726).",
                'chave_idempotencia' => $chave,
                'metadata' => json_encode([
                    'reason' => $r['reason'],
                    'status' => $r['status'],
                    'detected_at' => now()->toIso8601String(),
                ], JSON_UNESCAPED_UNICODE),
                'status' => 'aberto',
                'criado_em' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::channel('single')->warning('whatsapp:webhook-canary — falha ao persistir mcp_alertas_eventos: ' . $e->getMessage());
        }
    }

    /**
     * Output (tabela/JSON) + exit code. Centraliza pra os ramos skip/ok/fail.
     *
     * @param  array<string, mixed>  $payload
     */
    private function report(array $payload, int $exit): int
    {
        if ($this->option('json')) {
            $this->line((string) json_encode($payload + ['checked_at' => now()->toIso8601String()], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

            return $exit;
        }

        if ($payload['skipped'] ?? false) {
            $this->warn('⏭  canário pulado: ' . $payload['reason']);
        } elseif ($payload['ok'] ?? false) {
            $this->info("✓ webhook WhatsApp respondeu {$payload['reason']} — caminho de recebimento provado.");
        } else {
            $this->error("✗ webhook WhatsApp NÃO respondeu 200 — {$payload['reason']}. ALERT disparado.");
        }

        return $exit;
    }
}
