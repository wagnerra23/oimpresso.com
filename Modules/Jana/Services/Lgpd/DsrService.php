<?php

declare(strict_types=1);

namespace Modules\Jana\Services\Lgpd;

use App\Util\OtelHelper;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Modules\Jana\Services\JanaAuditService;
use Modules\Jana\Services\Privacy\PiiRedactor;
use Throwable;

/**
 * DsrService — G1 P0 (AUDIT-SENIOR-2026-05-25 §6) — D7.e LGPD direito esquecimento.
 *
 * Implementa LGPD Art. 18 §VI (direito de eliminação) pra titulares identificados
 * via CPF/CNPJ. Faz busca cross-entities, anonimiza colunas livres PII e (opcionalmente)
 * hard-delete linhas dedicadas.
 *
 * Prazo legal LGPD: <30 dias após pedido. Implementação atual é SÍNCRONA — completa
 * em <5s no caso comum (≤10k refs total por titular). Se >10k refs, command/tool
 * deve disparar job assíncrono (TODO próxima iteração).
 *
 * Multi-tenant Tier 0 ([ADR 0093](../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md))
 * IRREVOGÁVEL — `business_id` é parâmetro EXPLÍCITO, NUNCA confia em session
 * (chamadas vêm de tool MCP superadmin ou job assíncrono).
 *
 * Append-only audit trail ([ADR 0094](../../../../memory/decisions/0094-constituicao-v2-7-camadas-8-principios.md) §4):
 * Cada esquecimento gera 1 batch ActivityLog com UUID estável + entry CRITICAL
 * em `jana.audit.lgpd.eliminacao` (NUNCA purgado pelo retention job — append-only
 * contract retention.php).
 *
 * Anonimização > hard delete (default):
 * Preserva referential integrity (count conversas, tokens médios, custo IA tracking
 * ADR 0094 §4) sem reter o dado pessoal. Hard delete (`mode='hard'`) reservado pra
 * pedidos do titular que exigem remoção total (raro — geralmente disputa judicial).
 *
 * @see Modules\Jana\Services\Lgpd\DsrEsquecimentoResult
 * @see Modules\Jana\Services\Privacy\PiiRedactor
 * @see Modules\Jana\Services\JanaAuditService
 * @see https://gdpr.eu/gdpr-vs-lgpd/
 */
class DsrService
{
    /**
     * Map entidade → coluna(s) onde CPF/CNPJ pode aparecer textualmente.
     * Busca usa LIKE '%doc%' (regex via PCRE é ainda mais caro — LIKE basta pro caso BR).
     *
     * `pii_columns` indica colunas que serão redactadas via PiiRedactor quando match.
     * `is_child` indica se entidade tem business_id via parent (precisa JOIN).
     *
     * @return array<string,array{table:string,search_columns:array<int,string>,pii_columns:array<int,string>,is_child?:bool,parent_table?:string,parent_fk?:string}>
     */
    public function searchableEntityMap(): array
    {
        return [
            'mensagem' => [
                'table' => 'jana_mensagens',
                'search_columns' => ['content'],
                'pii_columns' => ['content'],
                'is_child' => true,
                'parent_table' => 'jana_conversas',
                'parent_fk' => 'conversa_id',
            ],
            'memoria_fato' => [
                'table' => 'jana_memoria_facts',
                'search_columns' => ['fato'],
                'pii_columns' => ['fato'],
            ],
            'cache_semantico' => [
                'table' => 'jana_cache_semantico',
                'search_columns' => ['query_original', 'resposta'],
                'pii_columns' => ['query_original', 'resposta'],
            ],
            'conversa' => [
                'table' => 'jana_conversas',
                'search_columns' => ['titulo'],
                'pii_columns' => ['titulo'],
            ],
        ];
    }

    public function __construct(
        protected PiiRedactor $piiRedactor,
        protected JanaAuditService $audit,
    ) {}

    /**
     * Executa esquecimento Art. 18 §VI pra um titular.
     *
     * @param  string  $cpfOuCnpj  CPF ou CNPJ — aceita formatado ou só dígitos
     * @param  int  $businessId  Tenant scope EXPLÍCITO (Tier 0 IRREVOGÁVEL)
     * @param  string  $mode  'anonymize' (default — LGPD-preferred) | 'hard' (delete row)
     */
    public function esquecerTitular(
        string $cpfOuCnpj,
        int $businessId,
        string $mode = 'anonymize',
    ): DsrEsquecimentoResult {
        return OtelHelper::spanBiz(
            'jana.lgpd.dsr.esquecer_titular',
            fn () => $this->doEsquecer($cpfOuCnpj, $businessId, $mode),
            [
                'business_id' => $businessId,
                'mode' => $mode,
                'doc_hash' => substr(hash('sha256', $cpfOuCnpj), 0, 12),
            ],
        );
    }

    protected function doEsquecer(
        string $cpfOuCnpj,
        int $businessId,
        string $mode,
    ): DsrEsquecimentoResult {
        $startedAt = Carbon::now();
        $startMs = (int) (microtime(true) * 1000);
        $auditTrailId = (string) Str::uuid();

        // Normaliza: aceita 123.456.789-00, 12345678900, 12.345.678/0001-90, etc.
        $docDigits = preg_replace('/\D+/', '', $cpfOuCnpj) ?? '';

        if (! in_array(strlen($docDigits), [11, 14], true)) {
            return new DsrEsquecimentoResult(
                cpfOuCnpj: $cpfOuCnpj,
                businessId: $businessId,
                refsByEntity: [],
                auditTrailId: $auditTrailId,
                startedAt: $startedAt->toIso8601String(),
                finishedAt: Carbon::now()->toIso8601String(),
                durationMs: 0,
                status: 'failed',
                errorMessage: 'Documento inválido: deve ser CPF (11 dígitos) ou CNPJ (14 dígitos)',
            );
        }

        if (! in_array($mode, ['anonymize', 'hard'], true)) {
            $mode = 'anonymize';
        }

        $refsByEntity = [];
        $status = 'ok';
        $errorMessage = null;

        try {
            foreach ($this->searchableEntityMap() as $entityKey => $config) {
                $refsByEntity[$entityKey] = $this->processEntity(
                    $entityKey,
                    $config,
                    $docDigits,
                    $cpfOuCnpj,
                    $businessId,
                    $mode,
                );
            }

            // Audit trail CRITICAL (helper específico LGPD do JanaAuditService).
            foreach ($refsByEntity as $entityKey => $refs) {
                if ($refs['rows_matched'] > 0) {
                    $this->audit->lgpdEliminacao(
                        entidade: $entityKey,
                        entityId: 0, // 0 = batch (ids array vai em payload)
                        businessId: $businessId,
                        motivo: 'titular_request_art_18_vi',
                    );
                }
            }

            // Sink consolidado — registra batch inteiro com audit_trail_id pra correlação.
            $this->audit->register(
                'lgpd.dsr.esquecimento.batch',
                [
                    'audit_trail_id' => $auditTrailId,
                    'doc_hash' => substr(hash('sha256', $docDigits), 0, 12),
                    'mode' => $mode,
                    'total_refs' => array_sum(array_column($refsByEntity, 'rows_matched')),
                    'total_anonymized' => array_sum(array_column($refsByEntity, 'rows_anonymized')),
                    'total_deleted' => array_sum(array_column($refsByEntity, 'rows_deleted')),
                ],
                $businessId,
                'critical',
            );
        } catch (Throwable $e) {
            $status = 'failed';
            $errorMessage = $e->getMessage();
            Log::channel('copiloto-ai')->error('DsrService::esquecerTitular falhou', [
                'business_id' => $businessId,
                'doc_hash' => substr(hash('sha256', $docDigits), 0, 12),
                'error' => $e->getMessage(),
            ]);
        }

        $finishedAt = Carbon::now();
        $durationMs = (int) (microtime(true) * 1000) - $startMs;

        return new DsrEsquecimentoResult(
            cpfOuCnpj: $cpfOuCnpj,
            businessId: $businessId,
            refsByEntity: $refsByEntity,
            auditTrailId: $auditTrailId,
            startedAt: $startedAt->toIso8601String(),
            finishedAt: $finishedAt->toIso8601String(),
            durationMs: $durationMs,
            status: $status,
            errorMessage: $errorMessage,
        );
    }

    /**
     * Busca + processa entidade. Retorna stats granulares.
     *
     * @param  array{table:string,search_columns:array<int,string>,pii_columns:array<int,string>,is_child?:bool,parent_table?:string,parent_fk?:string}  $config
     * @return array{rows_matched:int,rows_anonymized:int,rows_deleted:int,ids:array<int,int>}
     */
    protected function processEntity(
        string $entityKey,
        array $config,
        string $docDigits,
        string $docOriginal,
        int $businessId,
        string $mode,
    ): array {
        $table = $config['table'];
        $searchColumns = $config['search_columns'];
        $piiColumns = $config['pii_columns'];

        // Pattern de busca: CPF/CNPJ formatado + dígitos puros (cobre ambos casos
        // que o user pode ter digitado no chat).
        $patterns = $this->buildSearchPatterns($docDigits, $docOriginal);

        // Builda query base com filtro multi-tenant explícito (Tier 0 IRREVOGÁVEL).
        $isChild = $config['is_child'] ?? false;

        if ($isChild) {
            $parentTable = $config['parent_table'];
            $parentFk = $config['parent_fk'];

            $query = DB::table($table)
                ->join($parentTable, "{$table}.{$parentFk}", '=', "{$parentTable}.id")
                ->where("{$parentTable}.business_id", $businessId);
        } else {
            $query = DB::table($table)
                ->where('business_id', $businessId);
        }

        // OR (LIKE col1 LIKE patternN) por todas combinações col × pattern.
        $query->where(function ($q) use ($searchColumns, $patterns, $table, $isChild) {
            $colPrefix = $isChild ? "{$table}." : '';
            foreach ($searchColumns as $col) {
                foreach ($patterns as $p) {
                    $q->orWhere("{$colPrefix}{$col}", 'like', "%{$p}%");
                }
            }
        });

        // SELECT só PK pra dedup.
        $selectKey = $isChild ? "{$table}.id as match_id" : 'id as match_id';
        $matchedRows = (clone $query)->select($selectKey)->get();
        $ids = $matchedRows->pluck('match_id')->map(fn ($v) => (int) $v)->unique()->values()->toArray();
        $rowsMatched = count($ids);

        $stats = [
            'rows_matched' => $rowsMatched,
            'rows_anonymized' => 0,
            'rows_deleted' => 0,
            'ids' => $ids,
        ];

        if ($rowsMatched === 0) {
            return $stats;
        }

        // Aplica ação por chunks pra não travar table.
        foreach (array_chunk($ids, 500) as $chunkIds) {
            if ($mode === 'hard') {
                $deleted = DB::table($table)->whereIn('id', $chunkIds)->delete();
                $stats['rows_deleted'] += $deleted;
            } else {
                // anonymize: lê cada row, redacta cada PII column, persiste.
                $stats['rows_anonymized'] += $this->anonymizeRows($table, $chunkIds, $piiColumns);
            }
        }

        return $stats;
    }

    /**
     * Gera patterns de busca cobrindo formato dígitos puros + formatos canônicos BR.
     *
     * @return array<int,string>
     */
    protected function buildSearchPatterns(string $docDigits, string $docOriginal): array
    {
        $patterns = [$docDigits];

        if (strlen($docDigits) === 11) {
            // CPF formatado: 123.456.789-00
            $patterns[] = sprintf(
                '%s.%s.%s-%s',
                substr($docDigits, 0, 3),
                substr($docDigits, 3, 3),
                substr($docDigits, 6, 3),
                substr($docDigits, 9, 2),
            );
        } elseif (strlen($docDigits) === 14) {
            // CNPJ formatado: 12.345.678/0001-90
            $patterns[] = sprintf(
                '%s.%s.%s/%s-%s',
                substr($docDigits, 0, 2),
                substr($docDigits, 2, 3),
                substr($docDigits, 5, 3),
                substr($docDigits, 8, 4),
                substr($docDigits, 12, 2),
            );
        }

        // Inclui também o que o user passou exato (cobre formatos exóticos)
        if (! in_array($docOriginal, $patterns, true)) {
            $patterns[] = $docOriginal;
        }

        return $patterns;
    }

    /**
     * Anonimiza colunas PII em rows específicas via PiiRedactor.
     *
     * @param  array<int,int>  $ids
     * @param  array<int,string>  $piiColumns
     */
    protected function anonymizeRows(string $table, array $ids, array $piiColumns): int
    {
        $rows = DB::table($table)
            ->whereIn('id', $ids)
            ->get(['id', ...$piiColumns]);

        $count = 0;
        foreach ($rows as $row) {
            $updates = [];
            foreach ($piiColumns as $col) {
                $original = $row->{$col} ?? null;
                if ($original === null || $original === '') {
                    continue;
                }
                $updates[$col] = $this->piiRedactor->redact((string) $original);
            }

            if (! empty($updates)) {
                DB::table($table)->where('id', $row->id)->update($updates);
                $count++;
            }
        }

        return $count;
    }
}
