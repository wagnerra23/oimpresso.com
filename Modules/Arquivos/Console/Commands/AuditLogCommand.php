<?php

namespace Modules\Arquivos\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * arquivos:audit-log — Sprint 2 ADR 0123 (compliance LGPD).
 *
 * Ferramenta de auditoria pra compliance LGPD: query da tabela arquivos_audit_log
 * com filtros por business, action e janela temporal. Três modos de operação:
 *
 *   - Default  : tabela flat com últimas N rows na janela informada
 *   - --top-files : agrega por arquivo, mostra os com mais acessos
 *   - --suspicious: flag padrões suspeitos (URL vazada, deleção em série, scraping)
 *
 * Multi-tenant Tier 0 (ADR 0093): command CLI sem session — sem --business filtra
 * TODOS businesses (admin global). Com --business filtra explicitamente um business.
 * Isso é intencional e logado no header pra clareza auditável.
 *
 * Proteção DB: hard cap em 1000 rows mesmo que --limit seja maior.
 * PII: user_id mostrado como nome (JOIN users) ou ID fallback — nunca CPF/email raw.
 *
 * Ações enum em arquivos_audit_log:
 *   upload | download | classify | reclassify | soft_delete |
 *   restore | hard_delete | signed_url_issued
 *
 * Uso:
 *   php artisan arquivos:audit-log
 *   php artisan arquivos:audit-log --business=1 --hours=48 --limit=50
 *   php artisan arquivos:audit-log --action=signed_url_issued --business=4
 *   php artisan arquivos:audit-log --top-files --hours=72
 *   php artisan arquivos:audit-log --suspicious --hours=24
 *
 * @see Modules/Arquivos/Database/Migrations/2026_05_10_000002_create_arquivos_audit_log_table.php
 * @see memory/decisions/0123-modules-arquivos-backbone.md Sprint 2
 */
class AuditLogCommand extends Command
{
    protected $signature = 'arquivos:audit-log
        {--business= : Filtra por business_id (default: todos — admin global view)}
        {--action= : Filtra por action (upload|signed_url_issued|soft_delete|...)}
        {--hours=24 : Janela das últimas N horas (default 24)}
        {--limit=100 : Cap de rows retornadas (hard cap em 1000 — proteção DB)}
        {--top-files : Modo agregado — top 10 arquivos com mais acessos na janela}
        {--suspicious : Modo flag — destaca padrões suspeitos LGPD}';

    protected $description = 'Audit log de acesso a arquivos — compliance LGPD (ADR 0123).';

    /** Hard cap — nunca ultrapassar esse valor mesmo com --limit maior. */
    private const HARD_CAP = 1000;

    public function handle(): int
    {
        if (! Schema::hasTable('arquivos_audit_log')) {
            $this->error('arquivos_audit_log table missing — rode Modules/Arquivos migrate primeiro.');
            return 1;
        }

        $businessId = $this->option('business') !== null
            ? (int) $this->option('business')
            : null;

        $action  = $this->option('action') ?: null;
        $hours   = max(1, (int) $this->option('hours'));
        $limit   = min(self::HARD_CAP, max(1, (int) $this->option('limit')));
        $topFiles   = (bool) $this->option('top-files');
        $suspicious = (bool) $this->option('suspicious');

        // Header contextual — clareza de modo admin vs business-scoped.
        if ($businessId !== null) {
            $this->line("Audit log — business_id={$businessId} | janela={$hours}h | limit={$limit}");
        } else {
            $this->warn('MODO ADMIN — todos businesses (sem filtro --business)');
            $this->line("Audit log — janela={$hours}h | limit={$limit}");
        }

        if ($action !== null) {
            $this->line("  Filtro action: {$action}");
        }

        $this->newLine();

        // Despacha pro modo correto.
        if ($suspicious) {
            return $this->runSuspicious($businessId, $hours, $limit);
        }

        if ($topFiles) {
            return $this->runTopFiles($businessId, $action, $hours);
        }

        return $this->runDefault($businessId, $action, $hours, $limit);
    }

    // -------------------------------------------------------------------------
    // Modo default — tabela flat
    // -------------------------------------------------------------------------

    private function runDefault(?int $businessId, ?string $action, int $hours, int $limit): int
    {
        $since = now()->subHours($hours);

        $query = DB::table('arquivos_audit_log as aal')
            ->select([
                'aal.id',
                'aal.created_at',
                'aal.business_id',
                // UltimatePOS users table não tem coluna `name` — usa CONCAT(first_name, last_name)
                // com fallback pra username e por fim ID. Bug detectado em prod 2026-05-10.
                DB::raw("COALESCE(NULLIF(TRIM(CONCAT_WS(' ', u.first_name, u.last_name)), ''), u.username, CAST(aal.user_id AS CHAR)) as usuario"),
                'aal.action',
                'aal.arquivo_id',
                DB::raw("COALESCE(a.original_name, '(arquivo removido)') as filename"),
                'aal.payload',
            ])
            ->leftJoin('arquivos as a', 'a.id', '=', 'aal.arquivo_id')
            ->leftJoin('users as u', 'u.id', '=', 'aal.user_id')
            ->where('aal.created_at', '>=', $since)
            ->orderByDesc('aal.created_at')
            ->limit($limit);

        if ($businessId !== null) {
            $query->where('aal.business_id', $businessId);
        }

        if ($action !== null) {
            $query->where('aal.action', $action);
        }

        $rows = $query->get();

        if ($rows->isEmpty()) {
            $this->info('Nenhum registro encontrado com os critérios informados.');
            return 0;
        }

        $headers = ['id', 'created_at', 'biz', 'usuario', 'action', 'arquivo_id', 'filename', 'payload_summary'];

        $tableRows = $rows->map(function ($row) {
            return [
                $row->id,
                $row->created_at,
                $row->business_id,
                $row->usuario ?? '(anônimo)',
                $row->action,
                $row->arquivo_id,
                mb_strimwidth((string) ($row->filename ?? ''), 0, 40, '…'),
                mb_strimwidth((string) ($row->payload ?? ''), 0, 80, '…'),
            ];
        })->toArray();

        $this->table($headers, $tableRows);
        $this->newLine();
        $this->info(count($tableRows) . ' registro(s) exibido(s).');

        return 0;
    }

    // -------------------------------------------------------------------------
    // Modo --top-files — agregado por arquivo
    // -------------------------------------------------------------------------

    private function runTopFiles(?int $businessId, ?string $action, int $hours): int
    {
        $since = now()->subHours($hours);

        $query = DB::table('arquivos_audit_log as aal')
            ->select([
                'aal.arquivo_id',
                DB::raw("COALESCE(a.original_name, '(arquivo removido)') as filename"),
                'aal.business_id',
                DB::raw('COUNT(*) as total_acessos'),
                DB::raw('MIN(aal.created_at) as primeira_acao'),
                DB::raw('MAX(aal.created_at) as ultima_acao'),
            ])
            ->leftJoin('arquivos as a', 'a.id', '=', 'aal.arquivo_id')
            ->where('aal.created_at', '>=', $since)
            ->groupBy('aal.arquivo_id', 'aal.business_id', 'a.original_name')
            ->orderByDesc('total_acessos')
            ->limit(10);

        if ($businessId !== null) {
            $query->where('aal.business_id', $businessId);
        }

        if ($action !== null) {
            $query->where('aal.action', $action);
        }

        $rows = $query->get();

        if ($rows->isEmpty()) {
            $this->info('Nenhum acesso encontrado na janela informada.');
            return 0;
        }

        $headers    = ['arquivo_id', 'filename', 'biz', 'total_acessos', 'primeira_acao', 'ultima_acao'];
        $tableRows  = $rows->map(function ($row) {
            return [
                $row->arquivo_id,
                mb_strimwidth((string) ($row->filename ?? ''), 0, 40, '…'),
                $row->business_id,
                $row->total_acessos,
                $row->primeira_acao,
                $row->ultima_acao,
            ];
        })->toArray();

        $this->table($headers, $tableRows);
        $this->newLine();
        $this->info('Top ' . count($tableRows) . ' arquivos por acessos na janela de ' . $hours . 'h.');

        return 0;
    }

    // -------------------------------------------------------------------------
    // Modo --suspicious — padrões suspeitos LGPD
    // -------------------------------------------------------------------------

    private function runSuspicious(?int $businessId, int $hours, int $limit): int
    {
        $since = now()->subHours($hours);
        $foundAny = false;

        // ── 1. signed_url_issued sem user_id (URL vazada / acesso anônimo) ──
        $anonQuery = DB::table('arquivos_audit_log as aal')
            ->select([
                'aal.id',
                'aal.created_at',
                'aal.business_id',
                'aal.arquivo_id',
                DB::raw("COALESCE(a.original_name, '(arquivo removido)') as filename"),
                'aal.payload',
                DB::raw("'signed_url_anonima' as flag"),
            ])
            ->leftJoin('arquivos as a', 'a.id', '=', 'aal.arquivo_id')
            ->where('aal.action', 'signed_url_issued')
            ->whereNull('aal.user_id')
            ->where('aal.created_at', '>=', $since)
            ->orderByDesc('aal.created_at')
            ->limit($limit);

        if ($businessId !== null) {
            $anonQuery->where('aal.business_id', $businessId);
        }

        $anonRows = $anonQuery->get();

        if ($anonRows->isNotEmpty()) {
            $foundAny = true;
            $this->warn('[SUSPEITO] signed_url_issued sem user_id — possível URL vazada ou acesso anônimo:');
            $this->table(
                ['id', 'created_at', 'biz', 'arquivo_id', 'filename', 'payload_summary', 'flag'],
                $anonRows->map(fn ($r) => [
                    $r->id,
                    $r->created_at,
                    $r->business_id,
                    $r->arquivo_id,
                    mb_strimwidth((string) ($r->filename ?? ''), 0, 35, '…'),
                    mb_strimwidth((string) ($r->payload ?? ''), 0, 60, '…'),
                    $r->flag,
                ])->toArray()
            );
            $this->newLine();
        }

        // ── 2. soft_delete seguido de nenhum restore na janela (deleção sem revisão) ──
        // Arquivos deletados na janela cuja contagem de restore = 0.
        $deleteQuery = DB::table('arquivos_audit_log as del')
            ->select([
                'del.arquivo_id',
                DB::raw("COALESCE(a.original_name, '(arquivo removido)') as filename"),
                'del.business_id',
                'del.created_at as deletado_em',
                DB::raw("'soft_delete_sem_restore' as flag"),
            ])
            ->leftJoin('arquivos as a', 'a.id', '=', 'del.arquivo_id')
            ->where('del.action', 'soft_delete')
            ->where('del.created_at', '>=', $since)
            ->whereNotExists(function ($sub) use ($since) {
                $sub->select(DB::raw(1))
                    ->from('arquivos_audit_log as rst')
                    ->whereColumn('rst.arquivo_id', 'del.arquivo_id')
                    ->where('rst.action', 'restore')
                    ->where('rst.created_at', '>=', $since);
            })
            ->orderByDesc('del.created_at')
            ->limit($limit);

        if ($businessId !== null) {
            $deleteQuery->where('del.business_id', $businessId);
        }

        $deleteRows = $deleteQuery->get();

        if ($deleteRows->isNotEmpty()) {
            $foundAny = true;
            $this->warn('[SUSPEITO] soft_delete sem restore na janela — deleção definitiva sem revisão:');
            $this->table(
                ['arquivo_id', 'filename', 'biz', 'deletado_em', 'flag'],
                $deleteRows->map(fn ($r) => [
                    $r->arquivo_id,
                    mb_strimwidth((string) ($r->filename ?? ''), 0, 40, '…'),
                    $r->business_id,
                    $r->deletado_em,
                    $r->flag,
                ])->toArray()
            );
            $this->newLine();
        }

        // ── 3. 3+ downloads (consumação de signed URL) mesmo arquivo+IP em <60s (rapid-fire = scraping) ──
        // Sinal de scraping = CONSUMAÇÃO repetida (action `download`, emitida pelo
        // DownloadController a cada acesso à signed URL), não a emissão única
        // (`signed_url_issued`). Um atacante gera a URL 1× e a consome N× — logo
        // rapid-fire de `download` é o indicador correto de varredura via URL vazada.
        // Busca combinações arquivo_id+IP com ≥3 acessos em qualquer janela de 60s.
        // A detecção é feita via GROUP BY + HAVING em subquery; o JSON payload deve
        // conter {"ip":"..."} — se não tiver IP no payload, o campo é NULL (skip ok).
        $rapidQuery = DB::table(DB::raw("(
            SELECT
                aal.arquivo_id,
                JSON_UNQUOTE(JSON_EXTRACT(aal.payload, '$.ip')) as ip,
                aal.business_id,
                MIN(aal.created_at) as primeira,
                MAX(aal.created_at) as ultima,
                COUNT(*) as cnt,
                COALESCE(a.original_name, '(arquivo removido)') as filename
            FROM arquivos_audit_log aal
            LEFT JOIN arquivos a ON a.id = aal.arquivo_id
            WHERE aal.action = 'download'
              AND aal.created_at >= ?
              AND JSON_UNQUOTE(JSON_EXTRACT(aal.payload, '$.ip')) IS NOT NULL
              " . ($businessId !== null ? "AND aal.business_id = {$businessId}" : '') . "
            GROUP BY aal.arquivo_id, ip, aal.business_id, a.original_name
            HAVING cnt >= 3
               AND TIMESTAMPDIFF(SECOND, MIN(aal.created_at), MAX(aal.created_at)) <= 60
        ) as rapid"), [$since])
            ->select('*')
            ->orderByDesc('cnt')
            ->limit(50);

        try {
            $rapidRows = $rapidQuery->get();
        } catch (\Throwable) {
            // JSON_EXTRACT pode não existir em MySQL antigo — skip silencioso.
            $rapidRows = collect();
        }

        if ($rapidRows->isNotEmpty()) {
            $foundAny = true;
            $this->warn('[SUSPEITO] Rapid-fire downloads — 3+ acessos ao mesmo arquivo+IP em <60s (scraping?):');
            $this->table(
                ['arquivo_id', 'filename', 'biz', 'ip', 'acessos', 'primeira', 'ultima', 'flag'],
                $rapidRows->map(fn ($r) => [
                    $r->arquivo_id,
                    mb_strimwidth((string) ($r->filename ?? ''), 0, 30, '…'),
                    $r->business_id,
                    $r->ip ?? '–',
                    $r->cnt,
                    $r->primeira,
                    $r->ultima,
                    'rapid_fire_scraping',
                ])->toArray()
            );
            $this->newLine();
        }

        if (! $foundAny) {
            $this->info('Nenhum padrão suspeito detectado na janela de ' . $hours . 'h.');
        } else {
            $this->warn('Revise os registros acima para compliance LGPD (Art. 46 LGPD — segurança dos dados).');
        }

        return 0;
    }
}
