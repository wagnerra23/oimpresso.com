<?php

declare(strict_types=1);

namespace Modules\Whatsapp\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Jana\Scopes\ScopeByBusiness;
use Modules\Whatsapp\Entities\Channel;

/**
 * whatsapp:channel-health-snapshot — observabilidade de saúde de canal (ADR 0288).
 *
 * Grava um snapshot append-only de `channel_health` por canal (série temporal,
 * `channel_health_snapshots`) e **ALERTA** quando um canal `active` fica caído
 * (`disconnected`/`banned`/`degraded`) por mais de N min (config
 * `whatsapp.whatsmeow.health_alert_after_minutes`, default 10). O alerta dispara
 * **uma vez por streak** (no cruzamento do limiar) via Log `ALERT` — canal
 * monitorado (jana:health-check). A série habilita uptime% e time-to-detect (SLIs).
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
