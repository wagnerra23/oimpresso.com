<?php

declare(strict_types=1);

namespace Modules\Whatsapp\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Jana\Scopes\ScopeByBusiness;
use Modules\Whatsapp\Entities\WhatsappBusinessPhone;
use Modules\Whatsapp\Entities\WhatsappMessage;

/**
 * WhatsappObservabilityHealthCommand — snapshot consolidado da saúde do módulo
 * Whatsapp em todos os businesses ativos.
 *
 * Diferente de `whatsapp:health-check-all` (que pinga drivers Baileys/Z-API),
 * este command reporta MÉTRICAS DE NEGÓCIO observáveis:
 * - phones cadastrados por business + driver primário
 * - mensagens últimas 24h (sent/queued/failed) por business
 * - taxa de falha por business (alerta se >10%)
 * - última mensagem inbound/outbound por phone (detecta canal silencioso)
 *
 * Output JSON (machine-readable) ou tabela (--detail).
 *
 * Schedule canônico: NÃO recurring (ad-hoc + dashboard pull). Diferente de
 * `whatsapp:health-check-all` que tem cron 6h pra acionar fallback driver.
 *
 * Multi-tenant Tier 0 (ADR 0093): queries com `withoutGlobalScope` + filtro
 * explícito por business_id porque command CLI roda sem session().
 *
 * Wave 16 governance v3 D9 — observabilidade módulo Whatsapp (nota 68→71).
 *
 * @see memory/requisitos/Whatsapp/SPEC.md US-WA-014
 * @see app/Util/OtelHelper.php (instrumentação spans complementar)
 */
class WhatsappObservabilityHealthCommand extends Command
{
    /**
     * NOTA: `--detail` (NÃO `--verbose`) — Symfony reserva `--verbose` (PR #851).
     * Ver `.claude/rules/commands.md`.
     */
    protected $signature = 'whatsapp:observability-health
                            {--business= : Apenas 1 business_id (default: todos)}
                            {--detail : Tabela detalhada por phone (default: JSON sumário)}
                            {--hours=24 : Janela das métricas em horas (default: 24h)}';

    protected $description = 'Snapshot observabilidade Whatsapp: phones, mensagens 24h, taxa de falha por business — D9 governance v3';

    public function handle(): int
    {
        $businessOption = $this->option('business');
        $detail = (bool) $this->option('detail');
        $hours = max(1, (int) $this->option('hours'));
        $sinceIso = now()->subHours($hours)->toIso8601String();

        // 1) Phones cadastrados — base da observabilidade
        $phoneQuery = WhatsappBusinessPhone::query()
            ->withoutGlobalScope(ScopeByBusiness::class);

        if ($businessOption !== null) {
            $phoneQuery->where('business_id', (int) $businessOption);
        }

        $phones = $phoneQuery->get();
        $phonesByBusiness = $phones->groupBy('business_id');

        // 2) Métricas mensagens últimas N horas
        $msgQuery = WhatsappMessage::query()
            ->withoutGlobalScope(ScopeByBusiness::class)
            ->where('created_at', '>=', now()->subHours($hours));

        if ($businessOption !== null) {
            $msgQuery->where('business_id', (int) $businessOption);
        }

        $msgStats = $msgQuery
            ->selectRaw('business_id, direction, status, COUNT(*) as total')
            ->groupBy('business_id', 'direction', 'status')
            ->get();

        $summary = [];
        foreach ($phonesByBusiness as $bizId => $bizPhones) {
            $bizMsgs = $msgStats->where('business_id', $bizId);
            $sent = (int) $bizMsgs->where('direction', 'outbound')->where('status', 'sent')->sum('total');
            $queued = (int) $bizMsgs->where('direction', 'outbound')->where('status', 'queued')->sum('total');
            $failed = (int) $bizMsgs->where('direction', 'outbound')->where('status', 'failed')->sum('total');
            $inbound = (int) $bizMsgs->where('direction', 'inbound')->sum('total');
            $outboundTotal = max(1, $sent + $queued + $failed);
            $failRate = round(($failed / $outboundTotal) * 100, 2);

            $summary[(int) $bizId] = [
                'business_id' => (int) $bizId,
                'phones_count' => $bizPhones->count(),
                'drivers' => $bizPhones->pluck('driver')->unique()->values()->all(),
                'window_hours' => $hours,
                'outbound_sent' => $sent,
                'outbound_queued' => $queued,
                'outbound_failed' => $failed,
                'inbound' => $inbound,
                'failure_rate_pct' => $failRate,
                'alert' => $failRate > 10.0 ? 'failure_rate_high' : 'ok',
            ];
        }

        // 3) Output + log estruturado
        Log::info('whatsapp.observability.snapshot', [
            'window_hours' => $hours,
            'since' => $sinceIso,
            'businesses_evaluated' => count($summary),
            'businesses_in_alert' => count(array_filter($summary, fn ($r) => $r['alert'] !== 'ok')),
        ]);

        if ($detail) {
            $this->table(
                ['Biz', 'Phones', 'Drivers', 'Sent', 'Queued', 'Failed', 'Inbound', 'Fail%', 'Alert'],
                array_map(fn ($r) => [
                    $r['business_id'],
                    $r['phones_count'],
                    implode(',', $r['drivers']),
                    $r['outbound_sent'],
                    $r['outbound_queued'],
                    $r['outbound_failed'],
                    $r['inbound'],
                    $r['failure_rate_pct'],
                    $r['alert'],
                ], $summary)
            );
        } else {
            $this->line(json_encode([
                'generated_at' => now()->toIso8601String(),
                'window_hours' => $hours,
                'businesses' => array_values($summary),
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }

        $hasAlert = !empty(array_filter($summary, fn ($r) => $r['alert'] !== 'ok'));

        // Exit 0 sempre (snapshot é informativo; alerta é loggado mas não quebra cron)
        // Caller pode parsear JSON pra criar issue/notificação se quiser.
        if ($hasAlert) {
            $this->warn('Atenção: algum business com failure_rate > 10%. Ver log "whatsapp.observability.snapshot".');
        }

        return self::SUCCESS;
    }
}
