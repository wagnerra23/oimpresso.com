<?php

declare(strict_types=1);

namespace Modules\Whatsapp\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Modules\Jana\Scopes\ScopeByBusiness;
use Modules\Whatsapp\Entities\Channel;
use Modules\Whatsapp\Services\Centrifugo\CentrifugoPublisher;

/**
 * whatsapp:channel-health-snapshot — observabilidade de saúde de canal (ADR 0288).
 *
 * Grava um snapshot append-only de `channel_health` por canal (série temporal,
 * `channel_health_snapshots`) e **ALERTA** quando um canal `active` fica caído
 * (`disconnected`/`banned`/`degraded`) por mais de N min (config
 * `whatsapp.whatsmeow.health_alert_after_minutes`, default 10). O alerta dispara
 * **uma vez por streak** (no cruzamento do limiar) — canal monitorado
 * (jana:health-check). A série habilita uptime% e time-to-detect (SLIs).
 *
 * FASE 2 (ADR 0288): o alerta sai por TRÊS sinks no mesmo ponto (1×/streak via
 * `shouldAlert`, sem duplicar dedup): (1) Log `ALERT` (laravel.log), (2) Centrifugo
 * realtime no canal do business → surfacea na Caixa, (3) `mcp_alertas_eventos` →
 * a notificação que CHEGA no humano. Tira o alerta de "vive só no log do Hostinger"
 * pra "avisa de verdade + verificável sem SSH".
 *
 * Fecha o pilar de observabilidade (dossiê 2026-06-18: 20% → o maior salto): a
 * queda passa a avisar sem ninguém olhar a tela. Decisão de alerta **PURA** em
 * `shouldAlert()` (catraca de teste determinístico).
 *
 * Multi-tenant Tier 0 (ADR 0093): cron sem session → `withoutGlobalScope`; cada
 * row carrega `business_id`; queries escopadas por `channel_id` (PK única por business).
 *
 * @see memory/decisions/0288-slo-sli-saude-canal-whatsapp.md
 */
class ChannelHealthSnapshotCommand extends Command
{
    protected $signature = 'whatsapp:channel-health-snapshot
                            {--dry-run : Não grava nem alerta (preview)}
                            {--detail : Loga cada canal}';

    protected $description = 'Snapshot append-only de channel_health + alerta canal-down > N min + uptime% (ADR 0288).';

    /** Estados que contam como "caído" (alerta + uptime). */
    public const DOWN_HEALTHS = ['disconnected', 'banned', 'degraded'];

    public const DEFAULT_ALERT_AFTER_MINUTES = 10;

    public function __construct(private readonly CentrifugoPublisher $centrifugo)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $threshold = (int) config('whatsapp.whatsmeow.health_alert_after_minutes', self::DEFAULT_ALERT_AFTER_MINUTES);
        $dryRun = (bool) $this->option('dry-run');
        $now = now();

        // SUPERADMIN: cron sem session — Tier 0 garantido pelo business_id de cada row.
        $channels = Channel::query()
            ->withoutGlobalScope(ScopeByBusiness::class)
            ->whereIn('type', [
                Channel::TYPE_WHATSAPP_WHATSMEOW,
                Channel::TYPE_WHATSAPP_BAILEYS,
                Channel::TYPE_WHATSAPP_META,
                Channel::TYPE_WHATSAPP_ZAPI,
            ])
            ->where('status', 'active')
            ->orderBy('business_id')->orderBy('id')
            ->get();

        if ($channels->isEmpty()) {
            $this->info('Nenhum canal WhatsApp ativo pra snapshot.');

            return self::SUCCESS;
        }

        $recorded = 0;
        $alerted = 0;
        $rows = [];

        foreach ($channels as $ch) {
            $health = (string) $ch->channel_health;

            if (! $dryRun) {
                DB::table('channel_health_snapshots')->insert([
                    'business_id' => $ch->business_id,
                    'channel_id' => $ch->id,
                    'channel_health' => $health,
                    'recorded_at' => $now,
                ]);
                $recorded++;
            }

            [$downMinNow, $downMinPrev] = $this->downStreak($ch->id, $now);
            $uptime = $this->uptimePercent24h($ch->id);

            if (! $dryRun && self::shouldAlert($downMinNow, $downMinPrev, $threshold)) {
                $alerted++;
                Log::channel('single')->error('ALERT whatsapp.channel_health.down', [
                    'event' => 'whatsapp.channel_health.alert_down',
                    'business_id' => $ch->business_id,
                    'channel_id' => $ch->id,
                    'channel_health' => $health,
                    'down_minutes' => $downMinNow,
                    'threshold_minutes' => $threshold,
                ]);

                // FASE 2 (ADR 0288): além do Log, dois sinks no MESMO ponto — a
                // dedup segue sendo o shouldAlert acima (1×/streak). $downMinNow
                // não é null aqui (shouldAlert==true). Ambos best-effort.
                $this->publishAlert($ch, $health, (float) $downMinNow, $threshold);
                $this->persistAlert($ch, $health, (float) $downMinNow, $threshold, $now);
            }

            $rows[] = [
                $ch->id, $ch->business_id, mb_strimwidth((string) $ch->label, 0, 20, '…'),
                $health, $downMinNow === null ? '-' : (int) $downMinNow, $uptime === null ? '-' : $uptime.'%',
            ];

            if ($this->option('detail')) {
                $this->line(sprintf(
                    '  ch#%d biz=%d "%s" health=%s down=%s uptime24h=%s',
                    $ch->id, $ch->business_id, $ch->label, $health,
                    $downMinNow === null ? '-' : ((int) $downMinNow).'min',
                    $uptime === null ? '-' : $uptime.'%',
                ));
            }
        }

        $this->table(['ch', 'biz', 'label', 'health', 'down(min)', 'uptime24h'], $rows);
        $this->info("Snapshots: {$recorded} · alertas: {$alerted}".($dryRun ? ' (dry-run)' : ''));

        Log::info('whatsapp.channel_health.snapshot_done', [
            'event' => 'whatsapp.channel_health.snapshot_done',
            'recorded' => $recorded,
            'alerted' => $alerted,
            'threshold_minutes' => $threshold,
            'dry_run' => $dryRun,
        ]);

        return self::SUCCESS;
    }

    /**
     * Decisão PURA do alerta — dispara UMA vez por streak (no cruzamento do limiar).
     *
     * @param  float|null  $downMinutesNow   minutos em queda agora (null = não caído)
     * @param  float|null  $downMinutesPrev  minutos em queda no snapshot anterior da streak (null = streak começou agora)
     */
    public static function shouldAlert(?float $downMinutesNow, ?float $downMinutesPrev, int $thresholdMin): bool
    {
        if ($downMinutesNow === null) {
            return false; // não caído
        }
        if ($downMinutesNow < $thresholdMin) {
            return false; // dentro do limiar
        }
        if ($downMinutesPrev !== null && $downMinutesPrev >= $thresholdMin) {
            return false; // já alertou nesta streak
        }

        return true; // cruzou o limiar agora
    }

    /**
     * FASE 2 — publica o alerta no Centrifugo, no canal do business
     * (`whatsapp:business:{id}`), espelhando o envelope de
     * WhatsmeowWebhookController::publish (`event` = `whatsmeow.<x>` + payload).
     * A Caixa reage em realtime. Falha silenciosa é OK (o publisher já trata e
     * realtime é eventually-consistent — ADR 0058).
     */
    private function publishAlert(Channel $ch, string $health, float $downMinutes, int $threshold): void
    {
        $this->centrifugo->publish("whatsapp:business:{$ch->business_id}", [
            'event' => 'whatsmeow.channel_alert',
            'channel_id' => $ch->id,
            'channel_health' => $health,
            'down_minutes' => $downMinutes,
            'threshold_minutes' => $threshold,
        ]);
    }

    /**
     * FASE 2 — grava o alerta em `mcp_alertas_eventos` (a notificação disparada
     * que CHEGA no humano — distinta de `mcp_alertas`, que é só a regra/config).
     * Reusa o store + o padrão de insert idempotente de DetectDriftCommand /
     * WebhookCanaryCommand (mesmo módulo): guard de schema + SELECT-antes-INSERT +
     * try/catch que NUNCA derruba o cron. Não cria store novo (ADR 0270).
     *
     * Tier 0 (ADR 0093): business_id real do canal. Sem PII (só ids + health). A
     * dedup primária é o shouldAlert; a chave_idempotencia (ancorada na origem da
     * streak) é a rede de segurança contra insert duplo no mesmo tick.
     */
    private function persistAlert(Channel $ch, string $health, float $downMinutes, int $threshold, Carbon $now): void
    {
        try {
            if (! Schema::hasTable('mcp_alertas_eventos')) {
                return;
            }

            // Ancora na origem da streak (now - downMinutes): estável enquanto a
            // queda persiste; uma nova queda gera nova chave. ≤200 (schema UNIQUE).
            $downSince = $now->copy()->subMinutes((int) round($downMinutes));
            $chave = mb_substr("whatsapp_channel_down:{$ch->id}:".$downSince->format('YmdHi'), 0, 200);

            if (DB::table('mcp_alertas_eventos')->where('chave_idempotencia', $chave)->exists()) {
                return;
            }

            DB::table('mcp_alertas_eventos')->insert([
                'user_id' => null,
                'business_id' => $ch->business_id, // Tier 0: tenant real do canal
                'tipo' => 'whatsapp_channel_down',
                'severidade' => 'high',
                'titulo' => mb_strimwidth("Canal WhatsApp #{$ch->id} caído ({$health})", 0, 200, '…'),
                'descricao' => "Canal #{$ch->id} (biz {$ch->business_id}) em '{$health}' há ".((int) round($downMinutes))." min (limiar {$threshold} min) — sem reconectar.",
                'chave_idempotencia' => $chave,
                'metadata' => json_encode([
                    'channel_id' => $ch->id,
                    'channel_health' => $health,
                    'down_minutes' => $downMinutes,
                    'threshold_minutes' => $threshold,
                    'detected_at' => $now->toIso8601String(),
                ], JSON_UNESCAPED_UNICODE),
                'status' => 'aberto',
                'criado_em' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        } catch (\Throwable $e) {
            Log::channel('single')->warning('whatsapp:channel-health-snapshot — falha ao persistir mcp_alertas_eventos: '.$e->getMessage());
        }
    }

    /**
     * Duração (min) da streak de queda atual, agora e no snapshot anterior da streak.
     * Tier 0: escopado por channel_id. Retorna [null, null] se o canal não está caído.
     *
     * @return array{0: float|null, 1: float|null}
     */
    private function downStreak(int $channelId, Carbon $now): array
    {
        $snaps = DB::table('channel_health_snapshots')
            ->where('channel_id', $channelId)
            ->orderByDesc('recorded_at')->orderByDesc('id')
            ->limit(500)
            ->get(['channel_health', 'recorded_at']);

        $run = []; // recorded_at dos snapshots caídos consecutivos, mais-recente primeiro
        foreach ($snaps as $s) {
            if (in_array($s->channel_health, self::DOWN_HEALTHS, true)) {
                $run[] = Carbon::parse($s->recorded_at);
            } else {
                break; // streak quebrou
            }
        }

        if (empty($run)) {
            return [null, null];
        }

        $downSince = end($run); // o mais antigo consecutivo
        $downMinNow = (float) $downSince->diffInMinutes($now);
        $downMinPrev = count($run) >= 2 ? (float) $downSince->diffInMinutes($run[1]) : null;

        return [$downMinNow, $downMinPrev];
    }

    /** uptime% (snapshots healthy / total) nas últimas 24h. null se sem amostras. */
    private function uptimePercent24h(int $channelId): ?float
    {
        $since = now()->subDay();

        $total = DB::table('channel_health_snapshots')
            ->where('channel_id', $channelId)->where('recorded_at', '>=', $since)->count();
        if ($total === 0) {
            return null;
        }

        $healthy = DB::table('channel_health_snapshots')
            ->where('channel_id', $channelId)->where('recorded_at', '>=', $since)
            ->where('channel_health', 'healthy')->count();

        return round($healthy / $total * 100, 1);
    }
}
