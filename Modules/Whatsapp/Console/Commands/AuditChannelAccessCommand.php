<?php

declare(strict_types=1);

namespace Modules\Whatsapp\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Jana\Scopes\ScopeByBusiness;

/**
 * Auditor de conflitos em `channel_user_access` — ACL atendente↔canal (US-WA-068, ADR 0135).
 *
 * Motivação (Wagner 2026-06-13 "uma hora é removido, uma hora é reativado, por?"):
 * o acesso de um atendente a um canal fica oscilando entre revogado e ativo.
 * A causa é DUAS fontes de verdade que se contradizem:
 *
 *   1. `DUP_ATIVO` — o `UNIQUE(channel_id, user_id, revoked_at)` original NÃO
 *      enforça "1 grant ativo por (canal,user)" porque NULLs são DISTINTOS em
 *      índice UNIQUE (MySQL/MariaDB/SQLite). Logo 2 grants com `revoked_at=NULL`
 *      (dois ativos do mesmo atendente no mesmo canal) coexistem silenciosamente.
 *      O enforcement de schema vive na migration 2026_06_13_120000 (coluna gerada
 *      `revoked_marker`); ESTE auditor é o equivalente runtime/on-demand que acha
 *      e limpa duplicados mesmo onde a migration ainda não rodou.
 *
 *   2. `FLIP_BACKFILL` — `whatsapp:backfill-channel-access` concede grant a TODO
 *      user com `whatsapp.send/access`, olhando só `whereNull('revoked_at')`. Se um
 *      admin REVOGOU de propósito (revoked_by_user_id = humano > 0) e o backfill
 *      roda depois, ele cria um grant novo (`granted_by_user_id = 0` = system) →
 *      DESFAZ o revoke deliberado. O atendente volta a ver um canal do qual foi
 *      removido. (A causa-raiz foi corrigida no BackfillChannelAccessCommand, que
 *      passou a respeitar o tombstone de revoke humano; este auditor limpa o
 *      passivo já existente em prod.)
 *
 * Padrão (espelha `financeiro-bridge-auditor` + `jana:health-check`):
 *   - READ-ONLY por default. `--fix` (opt-in) é a ÚNICA porta pra DML.
 *   - Idempotente: re-rodar após `--fix` reporta 0 conflitos.
 *   - `--strict` faz exit 1 quando há conflito não-corrigido (uso em CI/cron gate).
 *
 * Tier 0 IRREVOGÁVEL (ADR 0093):
 *   - `business_id` explícito em toda query; `--business=X` escopa, `all` = cross-tenant.
 *   - `withoutGlobalScope` deixa o intent CLI-superadmin explícito (sem auth/sessão).
 *   - Logs/saída SEM PII: só ids numéricos + contadores. Nunca phone/nome.
 *
 * Uso:
 *   php artisan whatsapp:audit-channel-access --business=1            (relatório biz=1)
 *   php artisan whatsapp:audit-channel-access                         (relatório todos)
 *   php artisan whatsapp:audit-channel-access --json                  (saída máquina)
 *   php artisan whatsapp:audit-channel-access --business=1 --fix      (corrige biz=1)
 *   php artisan whatsapp:audit-channel-access --strict                (gate CI/cron)
 *
 * @see Modules\Whatsapp\Console\Commands\BackfillChannelAccessCommand
 * @see Modules\Whatsapp\Database\Migrations\2026_06_13_120000_enforce_single_active_channel_user_access
 * @see memory/decisions/0135-omnichannel-inbox-arquitetura.md
 * @see memory/requisitos/Whatsapp/SPEC.md US-WA-068
 */
class AuditChannelAccessCommand extends Command
{
    protected $signature = 'whatsapp:audit-channel-access
                            {--business=all : business_id alvo (default: all)}
                            {--fix : Resolve os conflitos (revoga duplicados/flips). Sem isto = só relatório.}
                            {--json : Saída JSON (máquina) em vez de tabela.}
                            {--strict : Exit 1 se houver conflito não-corrigido (gate CI/cron).}';

    protected $description = 'Audita channel_user_access: acha grants em conflito (duplicado-ativo + revogado-por-humano/reativado-pelo-backfill). --fix opcional.';

    /**
     * Sequência monotônica de revokes do --fix. Garante `revoked_at` GLOBALMENTE
     * distinto entre TODAS as rows revogadas nesta run (dup + flip), pra não
     * colidir no UNIQUE antigo `(channel_id,user_id,revoked_at)` onde a migration
     * corretiva (revoked_marker) ainda não rodou — um par pode aparecer em dup E
     * flip ao mesmo tempo.
     */
    private int $revokeSeq = 0;

    public function handle(): int
    {
        $businessOpt = (string) $this->option('business');
        $fix = (bool) $this->option('fix');
        $asJson = (bool) $this->option('json');
        $strict = (bool) $this->option('strict');

        $businessId = null;
        if ($businessOpt !== 'all') {
            $businessId = (int) $businessOpt;
            if ($businessId <= 0) {
                $this->error("--business={$businessOpt} inválido (esperado inteiro > 0 ou 'all').");

                return self::FAILURE;
            }
        }

        $dupGroups = $this->findDuplicateActiveGroups($businessId);
        $flips = $this->findBackfillFlips($businessId);

        $dupRowsRevoked = 0;
        $flipRevoked = 0;

        if ($fix) {
            $dupRowsRevoked = $this->fixDuplicateActiveGroups($dupGroups);
            $flipRevoked = $this->fixBackfillFlips($flips);
        }

        $report = [
            'business_filter' => $businessOpt,
            'fix' => $fix,
            'dup_active' => $dupGroups,
            'flip_backfill' => $flips,
            'summary' => [
                'dup_groups' => count($dupGroups),
                'dup_rows_revoked' => $dupRowsRevoked,
                'flip_grants' => count($flips),
                'flip_revoked' => $flipRevoked,
            ],
        ];

        Log::info('[whatsapp.audit_channel_access.completed]', array_merge(
            ['business_filter' => $businessOpt, 'fix' => $fix],
            $report['summary']
        ));

        if ($asJson) {
            $this->line((string) json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        } else {
            $this->renderHuman($report, $fix);
        }

        $hasFindings = $report['summary']['dup_groups'] > 0 || $report['summary']['flip_grants'] > 0;

        if ($strict && $hasFindings && ! $fix) {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * DUP_ATIVO — pares (business,channel,user) com >1 grant ativo (revoked_at NULL).
     *
     * Portável (sem UPDATE..JOIN nem window functions). Retorna, por grupo, o
     * `keep_id` (maior id = mais recente, preservado) e os `revoke_ids` (revogados
     * pelo --fix). Tudo só ids — sem PII.
     *
     * @return list<array{business_id:int,channel_id:int,user_id:int,active_count:int,keep_id:int,revoke_ids:list<int>}>
     */
    private function findDuplicateActiveGroups(?int $businessId): array
    {
        $groups = DB::table('channel_user_access')
            ->select('business_id', 'channel_id', 'user_id', DB::raw('count(*) as active_count'))
            ->whereNull('revoked_at')
            ->when($businessId !== null, fn ($q) => $q->where('business_id', $businessId))
            ->groupBy('business_id', 'channel_id', 'user_id')
            ->havingRaw('count(*) > 1')
            ->get();

        $out = [];

        foreach ($groups as $g) {
            $activeIds = DB::table('channel_user_access')
                ->where('business_id', $g->business_id)
                ->where('channel_id', $g->channel_id)
                ->where('user_id', $g->user_id)
                ->whereNull('revoked_at')
                ->orderBy('id')
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();

            $keepId = array_pop($activeIds); // mantém o maior id (mais recente)

            $out[] = [
                'business_id' => (int) $g->business_id,
                'channel_id' => (int) $g->channel_id,
                'user_id' => (int) $g->user_id,
                'active_count' => (int) $g->active_count,
                'keep_id' => (int) $keepId,
                'revoke_ids' => array_values($activeIds),
            ];
        }

        return $out;
    }

    /**
     * FLIP_BACKFILL — grant ATIVO criado pelo system backfill
     * (`granted_by_user_id = 0`) num par que JÁ teve revoke HUMANO
     * (`revoked_by_user_id > 0`). Sintoma: o atendente foi removido de propósito
     * e o backfill o reativou.
     *
     * Regra correta porque: se o grant ativo é `granted_by=0` E existe revoke
     * humano pro mesmo par, o sistema desfez a decisão humana. Se o ativo fosse
     * `granted_by>0`, seria re-grant humano deliberado (não flip). Backfill
     * legítimo (sem revoke humano anterior) não é flagado.
     *
     * @return list<array{business_id:int,channel_id:int,user_id:int,grant_id:int,human_revokes:int}>
     */
    private function findBackfillFlips(?int $businessId): array
    {
        $activeSystemGrants = DB::table('channel_user_access')
            ->whereNull('revoked_at')
            ->where('granted_by_user_id', 0)
            ->when($businessId !== null, fn ($q) => $q->where('business_id', $businessId))
            ->orderBy('id')
            ->get(['id', 'business_id', 'channel_id', 'user_id']);

        $out = [];

        foreach ($activeSystemGrants as $g) {
            $humanRevokes = (int) DB::table('channel_user_access')
                ->where('business_id', $g->business_id)
                ->where('channel_id', $g->channel_id)
                ->where('user_id', $g->user_id)
                ->whereNotNull('revoked_at')
                ->where('revoked_by_user_id', '>', 0)
                ->count();

            if ($humanRevokes > 0) {
                $out[] = [
                    'business_id' => (int) $g->business_id,
                    'channel_id' => (int) $g->channel_id,
                    'user_id' => (int) $g->user_id,
                    'grant_id' => (int) $g->id,
                    'human_revokes' => $humanRevokes,
                ];
            }
        }

        return $out;
    }

    /**
     * Soft-revoga os duplicados ativos (mantém `keep_id`). `revoked_by_user_id = 0`
     * (system). Timestamps ESCALONADOS pra não colidir no UNIQUE antigo
     * (revoked_at não-NULL iguais colidem onde a migration corretiva não rodou).
     */
    private function fixDuplicateActiveGroups(array $dupGroups): int
    {
        $revoked = 0;
        $now = now();

        foreach ($dupGroups as $group) {
            foreach ($group['revoke_ids'] as $id) {
                $affected = DB::table('channel_user_access')
                    ->where('id', $id)
                    ->whereNull('revoked_at') // defensivo: só se ainda ativo
                    ->update([
                        'revoked_at' => $now->copy()->addSeconds($this->revokeSeq++),
                        'revoked_by_user_id' => 0,
                        'updated_at' => $now,
                    ]);
                $revoked += $affected;
            }
        }

        return $revoked;
    }

    /**
     * Revoga os grants do system backfill que reativaram um revoke humano —
     * restaura a intenção do admin. `revoked_by_user_id = 0` (system).
     */
    private function fixBackfillFlips(array $flips): int
    {
        $revoked = 0;
        $now = now();

        foreach ($flips as $flip) {
            $affected = DB::table('channel_user_access')
                ->where('id', $flip['grant_id'])
                ->whereNull('revoked_at') // defensivo (dup-fix pode ter pego antes)
                ->update([
                    'revoked_at' => $now->copy()->addSeconds($this->revokeSeq++),
                    'revoked_by_user_id' => 0,
                    'updated_at' => $now,
                ]);
            $revoked += $affected;
        }

        return $revoked;
    }

    private function renderHuman(array $report, bool $fix): void
    {
        $s = $report['summary'];

        $this->info($fix ? '== Auditoria channel_user_access (modo --fix) ==' : '== Auditoria channel_user_access (relatório) ==');
        $this->line("business: {$report['business_filter']}");
        $this->newLine();

        $this->line(sprintf(
            'DUP_ATIVO   : %d grupo(s) com >1 grant ativo%s',
            $s['dup_groups'],
            $fix ? " · {$s['dup_rows_revoked']} row(s) revogada(s)" : ''
        ));
        $this->line(sprintf(
            'FLIP_BACKFILL: %d grant(s) system reativando revoke humano%s',
            $s['flip_grants'],
            $fix ? " · {$s['flip_revoked']} revogado(s)" : ''
        ));
        $this->newLine();

        if ($s['dup_groups'] > 0) {
            $this->warn('Duplicados ativos (channel_id/user_id → keep, revoke):');
            $this->table(
                ['business_id', 'channel_id', 'user_id', 'ativos', 'mantém id', 'revoga ids'],
                array_map(fn ($d) => [
                    $d['business_id'], $d['channel_id'], $d['user_id'],
                    $d['active_count'], $d['keep_id'], implode(',', $d['revoke_ids']),
                ], $report['dup_active'])
            );
        }

        if ($s['flip_grants'] > 0) {
            $this->warn('Flips backfill (grant system reativou revoke humano):');
            $this->table(
                ['business_id', 'channel_id', 'user_id', 'grant_id', 'revokes humanos'],
                array_map(fn ($f) => [
                    $f['business_id'], $f['channel_id'], $f['user_id'],
                    $f['grant_id'], $f['human_revokes'],
                ], $report['flip_backfill'])
            );
        }

        if ($s['dup_groups'] === 0 && $s['flip_grants'] === 0) {
            $this->info('✓ Nenhum conflito encontrado.');
        } elseif (! $fix) {
            $this->newLine();
            $this->comment('Rode com --fix pra resolver (revoga duplicados + flips, preserva history).');
        }
    }
}
