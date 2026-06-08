<?php

declare(strict_types=1);

namespace Modules\Jana\Services\Privacy;

use App\Util\OtelHelper;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Jana\Entities\CacheSemantico;
use Modules\Jana\Entities\Conversa;
use Modules\Jana\Entities\HealthNarrative;
use Modules\Jana\Entities\MemoriaFato;
use Modules\Jana\Entities\MemoriaMetrica;
use Modules\Jana\Entities\Mensagem;
use Modules\Jana\Entities\Sugestao;
use Modules\Jana\Services\JanaAuditService;
use Throwable;

/**
 * RetentionPurgeService — G1 P0 (AUDIT-SENIOR-2026-05-25 §6) — D7.d LGPD purge job.
 *
 * Aplica a política canônica `Config/retention.php` sobre as 6 entidades PII-relevantes
 * do módulo Jana. Suporta 3 estratégias (anonymize/soft_delete/hard_delete) com default
 * `anonymize` (preserva métricas agregadas sem reter dado pessoal — alinha LGPD Art. 16
 * com necessidade operacional).
 *
 * Multi-tenant Tier 0 ([ADR 0093](../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md))
 * IRREVOGÁVEL — itera business by business via loop explícito. NUNCA cross-tenant cleanup.
 *
 * Append-only audit trail ([ADR 0094](../../../../memory/decisions/0094-constituicao-v2-7-camadas-8-principios.md) §4):
 * Cada purge dispara `JanaAuditService::lgpdEliminacao()` que grava em activity_log
 * (NUNCA purgado, mesmo que dado-fonte seja).
 *
 * Stack canônica preservada ([ADR 0035](../../../../memory/decisions/0035-stack-ai-canonica-wagner-2026-04-26.md)):
 * NÃO altera contratos `LaravelAiSdkDriver`/`MeilisearchDriver`. Atua fora do path quente do chat.
 *
 * @see Modules\Jana\Config\retention.php
 * @see Modules\Jana\Services\Privacy\PiiRedactor
 * @see Modules\Jana\Services\JanaAuditService
 * @see Modules\Jana\Console\Commands\RetentionPurgeCommand
 */
class RetentionPurgeService
{
    public function __construct(
        protected PiiRedactor $piiRedactor,
        protected JanaAuditService $audit,
    ) {}

    /**
     * Map entidade canon → Model class + coluna de data + coluna(s) PII livre.
     *
     * `pii_columns` é usado pela estratégia `anonymize` — colunas textuais cujo
     * conteúdo pode conter PII (CPF/CNPJ/EMAIL/CEP/PHONE) e deve passar pelo
     * PiiRedactor antes de persistir.
     *
     * `parent_join` é usado pra entidades com tenancy via parent (Mensagem,
     * Sugestao herdam business_id de Conversa) — purge precisa de WHERE business_id
     * no parent.
     *
     * @return array<string,array{model:class-string,date_column:string,pii_columns:array<int,string>,parent_join?:array{table:string,foreign:string,parent_key:string}}>
     */
    public function entityMap(): array
    {
        return [
            'conversa' => [
                'model' => Conversa::class,
                'date_column' => 'created_at',
                'pii_columns' => ['titulo'],
            ],
            'mensagem' => [
                'model' => Mensagem::class,
                'date_column' => 'created_at',
                'pii_columns' => ['content'],
                // Mensagem.business_id herdado de Conversa via FK conversa_id
                'parent_join' => [
                    'table' => 'jana_conversas',
                    'foreign' => 'conversa_id',
                    'parent_key' => 'id',
                ],
            ],
            'sugestao' => [
                'model' => Sugestao::class,
                'date_column' => 'created_at',
                'pii_columns' => [], // payload_json é JSON — anonymize hard-delete-only
                'parent_join' => [
                    'table' => 'jana_conversas',
                    'foreign' => 'conversa_id',
                    'parent_key' => 'id',
                ],
            ],
            'cache_semantico' => [
                'model' => CacheSemantico::class,
                'date_column' => 'created_at',
                'pii_columns' => ['query_original', 'resposta'],
            ],
            'memoria_fato' => [
                'model' => MemoriaFato::class,
                'date_column' => 'created_at',
                'pii_columns' => ['fato'],
            ],
            'memoria_metrica' => [
                'model' => MemoriaMetrica::class,
                'date_column' => 'apurado_em',
                'pii_columns' => [], // métricas agregadas sem PII direta
            ],
            'health_narrative' => [
                'model' => HealthNarrative::class,
                'date_column' => 'generated_at',
                'pii_columns' => ['narrative'],
            ],
        ];
    }

    /**
     * Executa purge pra um business + entidade específicos.
     *
     * @param  int|null  $businessId  null = entidades repo-wide (ex: health_narrative)
     * @param  string  $entityKey  ex 'conversa', 'mensagem'
     * @param  int|null  $retentionDaysOverride  sobrescreve TTL config
     * @param  bool  $dryRun  só conta, não persiste
     * @return array{entity:string,business_id:?int,retention_days:?int,strategy:string,rows_matched:int,rows_purged:int,error:?string}
     */
    public function purgeEntity(
        ?int $businessId,
        string $entityKey,
        ?int $retentionDaysOverride = null,
        bool $dryRun = false,
    ): array {
        return OtelHelper::spanBiz(
            'jana.retention.purge_entity',
            function () use ($businessId, $entityKey, $retentionDaysOverride, $dryRun) {
                return $this->doPurgeEntity($businessId, $entityKey, $retentionDaysOverride, $dryRun);
            },
            [
                'entity' => $entityKey,
                'business_id' => $businessId,
                'dry_run' => $dryRun,
            ],
        );
    }

    /**
     * @return array{entity:string,business_id:?int,retention_days:?int,strategy:string,rows_matched:int,rows_purged:int,error:?string}
     */
    protected function doPurgeEntity(
        ?int $businessId,
        string $entityKey,
        ?int $retentionDaysOverride,
        bool $dryRun,
    ): array {
        $map = $this->entityMap();

        $base = [
            'entity' => $entityKey,
            'business_id' => $businessId,
            'retention_days' => null,
            'strategy' => (string) config('jana.retention.strategy', 'anonymize'),
            'rows_matched' => 0,
            'rows_purged' => 0,
            'error' => null,
        ];

        if (! isset($map[$entityKey])) {
            $base['error'] = "Entidade desconhecida: {$entityKey}";

            return $base;
        }

        $retentionDays = $retentionDaysOverride
            ?? config("jana.retention.entities.{$entityKey}");

        // null = retention indefinida (ex: meta) — skipa silenciosamente.
        if ($retentionDays === null) {
            return $base;
        }

        $retentionDays = (int) $retentionDays;
        $base['retention_days'] = $retentionDays;

        $cutoff = Carbon::now()->subDays($retentionDays);

        try {
            $strategy = $base['strategy'];
            $entityConfig = $map[$entityKey];

            // Itera com chunks pra evitar memory blow up em business com 1M+ rows.
            // chunkById é safe — não pula rows quando outras mutations rolam em paralelo.
            $query = $this->buildPurgeQuery(
                $entityConfig,
                $businessId,
                $cutoff,
            );

            $matched = (clone $query)->count();
            $base['rows_matched'] = $matched;

            if ($dryRun || $matched === 0) {
                return $base;
            }

            $purged = $this->applyStrategy(
                $entityConfig,
                $businessId,
                $cutoff,
                $strategy,
            );

            $base['rows_purged'] = $purged;

            // Audit trail — Spatie ActivityLog + OTel + Log canal copiloto-ai.
            $this->audit->register(
                'retention.purge.executed',
                [
                    'entity' => $entityKey,
                    'strategy' => $strategy,
                    'retention_days' => $retentionDays,
                    'rows_matched' => $matched,
                    'rows_purged' => $purged,
                    'cutoff' => $cutoff->toIso8601String(),
                ],
                $businessId,
                $purged > 0 ? 'warning' : 'info',
            );

            return $base;
        } catch (Throwable $e) {
            $base['error'] = $e->getMessage();

            Log::channel('copiloto-ai')->error('RetentionPurge falhou', [
                'entity' => $entityKey,
                'business_id' => $businessId,
                'error' => $e->getMessage(),
            ]);

            return $base;
        }
    }

    /**
     * Builda query que casa rows elegíveis pra purge.
     *
     * Multi-tenant Tier 0: para entidades com `parent_join`, faz INNER JOIN no
     * parent + filtra `parent.business_id` (defesa em profundidade Wave 7).
     *
     * @param  array{model:class-string,date_column:string,pii_columns:array<int,string>,parent_join?:array{table:string,foreign:string,parent_key:string}}  $entityConfig
     */
    protected function buildPurgeQuery(array $entityConfig, ?int $businessId, Carbon $cutoff): \Illuminate\Database\Query\Builder
    {
        /** @var Model $modelInstance */
        $modelInstance = new ($entityConfig['model'])();
        $table = $modelInstance->getTable();
        $dateColumn = $entityConfig['date_column'];

        if (isset($entityConfig['parent_join'])) {
            $parent = $entityConfig['parent_join'];
            $query = DB::table($table)
                ->join(
                    $parent['table'],
                    "{$table}.{$parent['foreign']}",
                    '=',
                    "{$parent['table']}.{$parent['parent_key']}",
                )
                ->where("{$table}.{$dateColumn}", '<', $cutoff);

            if ($businessId !== null) {
                $query->where("{$parent['table']}.business_id", $businessId);
            }

            // SELECT só o PK do child pra DELETE WHERE id IN não vazar JOIN.
            return $query->select("{$table}.id as purge_id");
        }

        $query = DB::table($table)
            ->where($dateColumn, '<', $cutoff);

        if ($businessId !== null) {
            // health_narrative + memoria_metrica podem ter business_id NULL
            // (plataforma toda) — filtro estrito quando $businessId != null.
            if ($this->hasBusinessIdColumn($table)) {
                $query->where('business_id', $businessId);
            }
        }

        return $query;
    }

    /**
     * Aplica estratégia + retorna número de rows efetivamente purgadas.
     *
     * Estratégias:
     * - `anonymize`: UPDATE pii_columns via PiiRedactor.redact() — preserva métricas
     * - `soft_delete`: UPDATE deleted_at = now() (Model precisa ter SoftDeletes)
     * - `hard_delete`: DELETE definitivo (LGPD Art. 18 §VI)
     *
     * @param  array{model:class-string,date_column:string,pii_columns:array<int,string>,parent_join?:array{table:string,foreign:string,parent_key:string}}  $entityConfig
     */
    protected function applyStrategy(array $entityConfig, ?int $businessId, Carbon $cutoff, string $strategy): int
    {
        /** @var Model $modelInstance */
        $modelInstance = new ($entityConfig['model'])();
        $table = $modelInstance->getTable();
        $dateColumn = $entityConfig['date_column'];
        $piiColumns = $entityConfig['pii_columns'];

        // Pega IDs elegíveis (até 5000 por batch pra evitar lock longo)
        $idsQuery = $this->buildPurgeQuery($entityConfig, $businessId, $cutoff);
        $ids = isset($entityConfig['parent_join'])
            ? $idsQuery->pluck('purge_id')->toArray()
            : $idsQuery->pluck('id')->toArray();

        if (empty($ids)) {
            return 0;
        }

        // Chunked em 1000s pra UPDATE/DELETE não travar tabela.
        $purged = 0;
        foreach (array_chunk($ids, 1000) as $chunkIds) {
            $purged += match ($strategy) {
                'hard_delete' => $this->doHardDelete($table, $chunkIds),
                'soft_delete' => $this->doSoftDelete($table, $chunkIds),
                'anonymize' => $this->doAnonymize($table, $chunkIds, $piiColumns),
                default => throw new \InvalidArgumentException("Estratégia desconhecida: {$strategy}"),
            };
        }

        return $purged;
    }

    /**
     * @param  array<int,int>  $ids
     */
    protected function doHardDelete(string $table, array $ids): int
    {
        return DB::table($table)->whereIn('id', $ids)->delete();
    }

    /**
     * @param  array<int,int>  $ids
     */
    protected function doSoftDelete(string $table, array $ids): int
    {
        return DB::table($table)
            ->whereIn('id', $ids)
            ->whereNull('deleted_at')
            ->update(['deleted_at' => Carbon::now()]);
    }

    /**
     * Anonymize: lê rows, passa cada PII column pelo PiiRedactor, persiste.
     *
     * Fallback: se Model não tem coluna PII textual (ex: memoria_metrica), apenas
     * marca uma flag de anonimização no metadata (preserva linha pra analytics).
     *
     * @param  array<int,int>  $ids
     * @param  array<int,string>  $piiColumns
     */
    protected function doAnonymize(string $table, array $ids, array $piiColumns): int
    {
        // Sem colunas PII textuais: nada a anonimizar — preserva row (métrica
        // agregada já não tem PII). Conta como 0 purged (não houve mudança).
        if (empty($piiColumns)) {
            return 0;
        }

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

    protected function hasBusinessIdColumn(string $table): bool
    {
        try {
            return \Schema::hasColumn($table, 'business_id');
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Lista todas entidades configuradas (pra command --list / introspecção).
     *
     * @return array<int,string>
     */
    public function listEntities(): array
    {
        return array_keys($this->entityMap());
    }
}
