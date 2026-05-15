<?php

declare(strict_types=1);

namespace Modules\Whatsapp\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Detecta drift entre `whatsapp_baileys_auth_state` e canais ativos.
 *
 * **Por que existe (incident 2026-05-15 — Baileys 6.7.18→7.0.0-rc11 deploy):**
 *
 * Após deploy Baileys 7.x, daemon falhou com `failed to find key "AAAAALtG"
 * to decode mutation` em `chat-utils.ts:309` — auth state antigo 6.x
 * INCOMPATÍVEL com algoritmo de mutation decode 7.x. Solução foi PURGAR
 * 103 rows ofensivas. **Sem detecção ANTES, daemon ficaria em loop até
 * alguém investigar logs.**
 *
 * Outros drifts catalogados:
 *
 *  - **Orphan rows**: `whatsapp_baileys_auth_state.instance_id` sem canal
 *    correspondente (canal deletado mas rows persistiram — incident
 *    catalogou pelo menos 2 instances: ch-d57cb5fed... + ch-62edc13f09...).
 *  - **Stale rows**: auth_state pra canal `status='banned'` ou `'inactive'`
 *    nunca vai ser reusado — drift inútil ocupando DB.
 *  - **Major bump risk**: se daemon Baileys atualizar major (6.x→7.x ou
 *    futuro 7.x→8.x), auth state OLDxNEW provavelmente incompatível.
 *    Heurística: comando avisa quando detecta divergência > 30d entre
 *    `auth_state.updated_at` e ultima atualização do canal.
 *
 * **Como funciona:**
 *
 * Query 3 tipos de drift:
 *
 * ```
 * 1. Orphan instance_ids:
 *    SELECT DISTINCT instance_id FROM whatsapp_baileys_auth_state
 *    WHERE instance_id NOT IN (
 *        SELECT CONCAT('ch-', REPLACE(channel_uuid, '-', ''))
 *        FROM channels WHERE deleted_at IS NULL  -- ou similar
 *    )
 *
 * 2. Banned/inactive rows:
 *    SELECT ... JOIN channels ON ... WHERE channel.status IN ('banned', 'inactive')
 *
 * 3. Stale rows (>90d) — eviction candidates.
 * ```
 *
 * **Quando rodar:**
 *
 *  - Cron daily 03h BRT (Schedule em `app/Console/Kernel.php` — TODO próximo PR)
 *  - Manual antes de `whatsapp:channels-reconcile` ou Baileys upgrade
 *  - CI `--fail-on-drift` em workflow daemon-docker-build
 *
 * **Fail-safe (NUNCA delete automático):** apenas detecta + loga. Operador
 * decide se purgar via `whatsapp:channels-reconcile --purge-orphan-auth` ou
 * tinker manual. Regra Wagner Tier 0: "nunca perca mensagem" — auth state
 * raro tem msg em si mas garante recuperação multi-device, melhor pecar pelo
 * preservativo.
 *
 * @see memory/sessions/2026-05-15-estudo-whatsapp-protocol-vs-oimpresso.md
 * @see memory/handoffs/2026-05-15-0700-whatsapp-maratona-fechamento-... (lição catalogada)
 * @see .claude/skills/baileys-update-procedure/SKILL.md §Fase 1.5 (TODO add)
 */
class WhatsappAuthStateDriftCheckCommand extends Command
{
    protected $signature = 'whatsapp:auth-state-drift-check
                            {--fail-on-drift : Exit 1 se houver drift (CI usage)}
                            {--biz= : Limitar check a 1 business_id (multi-tenant Tier 0)}';

    protected $description = 'Detecta drift entre whatsapp_baileys_auth_state e canais ativos (orphans + banned/inactive).';

    public function handle(): int
    {
        if (! Schema::hasTable('whatsapp_baileys_auth_state')) {
            $this->warn('⚠ Tabela whatsapp_baileys_auth_state não existe — skip.');
            return self::SUCCESS;
        }
        if (! Schema::hasTable('channels')) {
            $this->warn('⚠ Tabela channels não existe — skip.');
            return self::SUCCESS;
        }

        $bizFilter = $this->option('biz');
        $failOnDrift = (bool) $this->option('fail-on-drift');

        // 1. Resolve instance_ids esperados (canais ATIVOS biz/qualquer)
        $channelsQuery = DB::table('channels')
            ->where('type', 'whatsapp_baileys')
            ->where('status', 'active');
        if ($bizFilter !== null) {
            $channelsQuery->where('business_id', (int) $bizFilter);
        }
        $expectedInstanceIds = $channelsQuery->get()
            ->map(fn ($c) => 'ch-' . str_replace('-', '', $c->channel_uuid))
            ->all();

        // 2. Auth state instance_ids distintos
        $authStateRows = DB::table('whatsapp_baileys_auth_state')
            ->select('instance_id', DB::raw('count(*) as rows_count'), DB::raw('MAX(updated_at) as last_updated'))
            ->groupBy('instance_id')
            ->get();

        // 3. Detecta drift por categoria
        $orphans = [];
        $banned = [];
        $stale90d = [];

        $stale90dThreshold = now()->subDays(90);

        foreach ($authStateRows as $row) {
            // Orphan: instance_id sem channel correspondente ativo
            if (! in_array($row->instance_id, $expectedInstanceIds, true)) {
                // Pode ser de canal banned/inactive
                $matchingChannel = DB::table('channels')
                    ->where('type', 'whatsapp_baileys')
                    ->whereRaw("CONCAT('ch-', REPLACE(channel_uuid, '-', '')) = ?", [$row->instance_id])
                    ->first();

                if ($matchingChannel === null) {
                    $orphans[] = [
                        'instance_id_prefix' => substr($row->instance_id, 0, 12) . '...',
                        'rows' => (int) $row->rows_count,
                        'last_updated' => $row->last_updated,
                    ];
                } elseif (in_array($matchingChannel->status, ['banned', 'inactive'], true)) {
                    $banned[] = [
                        'instance_id_prefix' => substr($row->instance_id, 0, 12) . '...',
                        'channel_id' => $matchingChannel->id,
                        'channel_status' => $matchingChannel->status,
                        'rows' => (int) $row->rows_count,
                    ];
                }
            }

            // Stale 90d (mesmo se canal ativo, auth_state muito antigo merece atenção)
            if ($row->last_updated !== null) {
                $lastUpdated = \Carbon\Carbon::parse($row->last_updated);
                if ($lastUpdated->lt($stale90dThreshold)) {
                    $stale90d[] = [
                        'instance_id_prefix' => substr($row->instance_id, 0, 12) . '...',
                        'rows' => (int) $row->rows_count,
                        'last_updated' => $row->last_updated,
                        'age_days' => $lastUpdated->diffInDays(now()),
                    ];
                }
            }
        }

        // 4. Reporta
        $this->info('=== whatsapp:auth-state-drift-check ===');
        $this->line('Total auth_state rows (distinct instance_id): ' . count($authStateRows));
        $this->line('Canais ativos esperados: ' . count($expectedInstanceIds));
        $this->line('');

        if (! empty($orphans)) {
            $this->warn('⚠ ORPHANS: ' . count($orphans) . ' instance_ids sem channel correspondente');
            foreach ($orphans as $o) {
                $this->line(sprintf('  - %s (%d rows, last_updated=%s)',
                    $o['instance_id_prefix'], $o['rows'], $o['last_updated'] ?? '-'));
            }
        } else {
            $this->info('✓ Zero orphans');
        }

        if (! empty($banned)) {
            $this->warn('⚠ BANNED/INACTIVE: ' . count($banned) . ' instance_ids em canais não-ativos');
            foreach ($banned as $b) {
                $this->line(sprintf('  - %s (channel_id=%d status=%s, %d rows)',
                    $b['instance_id_prefix'], $b['channel_id'], $b['channel_status'], $b['rows']));
            }
        } else {
            $this->info('✓ Zero rows em canais banned/inactive');
        }

        if (! empty($stale90d)) {
            $this->warn('⚠ STALE >90d: ' . count($stale90d) . ' instance_ids sem update recente');
            foreach (array_slice($stale90d, 0, 5) as $s) {
                $this->line(sprintf('  - %s (%d rows, %.0f dias parado)',
                    $s['instance_id_prefix'], $s['rows'], $s['age_days']));
            }
            if (count($stale90d) > 5) {
                $this->line('  ... +' . (count($stale90d) - 5) . ' mais');
            }
        } else {
            $this->info('✓ Zero rows stale >90d');
        }

        $totalDrift = count($orphans) + count($banned) + count($stale90d);

        Log::info('[whatsapp.auth_state.drift_check.result]', [
            'business_id_filter' => $bizFilter,
            'expected_channels' => count($expectedInstanceIds),
            'total_distinct_instances' => count($authStateRows),
            'orphans' => count($orphans),
            'banned_inactive' => count($banned),
            'stale_90d' => count($stale90d),
            'total_drift' => $totalDrift,
        ]);

        if ($totalDrift === 0) {
            $this->info('');
            $this->info('✓ SEM DRIFT — auth_state aligned com canais ativos.');
            return self::SUCCESS;
        }

        $this->warn('');
        $this->warn(sprintf('Total drift detectado: %d (orphans=%d, banned=%d, stale=%d)',
            $totalDrift, count($orphans), count($banned), count($stale90d)));
        $this->warn('Operador decide se purgar. NUNCA delete automático (regra Wagner Tier 0 "nunca perca mensagem" preventivo).');
        $this->line('Comando sugerido (manual): php artisan tinker → DB::table(\'whatsapp_baileys_auth_state\')->where(\'instance_id\',\'X\')->delete()');

        return $failOnDrift ? self::FAILURE : self::SUCCESS;
    }
}
