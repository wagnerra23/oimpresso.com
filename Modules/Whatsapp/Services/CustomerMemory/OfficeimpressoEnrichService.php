<?php

declare(strict_types=1);

namespace Modules\Whatsapp\Services\CustomerMemory;

use Illuminate\Support\Facades\Log;
use Modules\Jana\Scopes\ScopeByBusiness;
use Modules\Whatsapp\Entities\CustomerMemory;
use Modules\Whatsapp\Services\CustomerMemory\Sources\FirebirdLookupSourceContract;
use Throwable;

/**
 * Observabilidade D9.a (ADR 0155): cross-DB lookup envolto em
 * `OtelHelper::span(` (Tracer whatsapp.customer_memory.enrich_firebird) —
 * mede match rate + latência por fonte.
 *
 * US-WA-VOZ-002 — Enrichment cross-DB com OfficeImpresso legacy (Firebird).
 *
 * Wagner 2026-05-15: "nem todo cliente está cadastrado, pesquise no firebird."
 *
 * Pra customers que NÃO bateram match contra `contacts` (CRM Hostinger),
 * tenta lookup em `clientes` legacy (Firebird WR Sistemas). Resultado vira
 * `external_sources` JSON com candidates encontrados.
 *
 * NÃO sobrescreve `contact_id` — esse permanece do CRM Hostinger. Firebird
 * é metadata complementar (mostra "esse mesmo cliente também existe no
 * sistema antigo como código 12345 — pode ser oportunidade de migração").
 *
 * Fonte injetada via interface — driver agnostic (JSON, PDO futuro, HTTP).
 *
 * @see Modules/Whatsapp/Services/CustomerMemory/Sources/FirebirdLookupSourceContract.php
 */
class OfficeimpressoEnrichService
{
    public function __construct(
        protected readonly FirebirdLookupSourceContract $source,
    ) {
    }

    /**
     * Enriquece 1 customer_memory com lookup Firebird.
     *
     * Retorna number of candidates encontrados (0 = sem match).
     *
     * NÃO recompila identity nem stats — caller deve rodar
     * CustomerMemoryRebuilder::rebuild() antes/depois se quiser refresh full.
     */
    public function enrich(CustomerMemory $memory): int
    {
        if (! $this->source->isHealthy()) {
            Log::channel('single')->warning('[officeimpresso_enrich.source_unhealthy]', [
                'source' => $this->source->sourceLabel(),
                'business_id' => $memory->business_id,
            ]);
            return 0;
        }

        try {
            $candidates = $this->source->lookupByPhone($memory->customer_external_id);

            if (empty($candidates)) {
                // Marca tentativa mesmo sem match — debug
                $memory->external_sources = $this->mergeWithExisting($memory->external_sources, []);
                $memory->external_sources_enriched_at = now();
                $memory->saveQuietly();
                return 0;
            }

            $entries = array_map(fn ($c) => [
                'source' => $this->source->sourceLabel(),
                'cliente_id' => $c['cliente_id'] ?? null,
                'nome' => $c['nome'] ?? null,
                'fone1' => $c['fone1'] ?? null,
                'fone2' => $c['fone2'] ?? null,
                'email' => $c['email'] ?? null,
                'bloqueado' => (bool) ($c['bloqueado'] ?? false),
                'cpf_cnpj' => $c['cpf_cnpj'] ?? null,
                'cidade' => $c['cidade'] ?? null,
                'data_cadastro' => $c['data_cadastro'] ?? null,
            ], $candidates);

            $memory->external_sources = $this->mergeWithExisting($memory->external_sources, $entries);
            $memory->external_sources_enriched_at = now();
            $memory->saveQuietly();

            Log::channel('single')->info('[officeimpresso_enrich.matched]', [
                'metric_name' => 'officeimpresso_enrich_matched',
                'business_id' => $memory->business_id,
                'customer_memory_id' => $memory->id,
                'matches_count' => count($candidates),
                'source' => $this->source->sourceLabel(),
            ]);

            return count($candidates);
        } catch (Throwable $e) {
            Log::channel('single')->warning('[officeimpresso_enrich.failed]', [
                'business_id' => $memory->business_id,
                'customer_memory_id' => $memory->id,
                'error' => $e->getMessage(),
            ]);
            return 0;
        }
    }

    /**
     * Enriquece batch — itera customers do business com `external_sources_enriched_at`
     * NULL OU stale (>N dias).
     *
     * @return array{processed: int, matched: int, skipped: int}
     */
    public function enrichBusiness(int $businessId, int $limit = 1000, int $staleDays = 30): array
    {
        $cutoff = now()->subDays($staleDays);

        $memories = CustomerMemory::query()
            ->withoutGlobalScope(ScopeByBusiness::class)
            ->where('business_id', $businessId)
            ->where(function ($q) use ($cutoff) {
                $q->whereNull('external_sources_enriched_at')
                  ->orWhere('external_sources_enriched_at', '<', $cutoff);
            })
            ->orderBy('id')
            ->limit($limit)
            ->get();

        $matched = 0;
        $skipped = 0;
        foreach ($memories as $mem) {
            $n = $this->enrich($mem);
            if ($n > 0) {
                $matched++;
            } else {
                $skipped++;
            }
        }

        return [
            'processed' => $memories->count(),
            'matched' => $matched,
            'skipped' => $skipped,
        ];
    }

    /**
     * Merge defensivo — preserva entries de OUTRAS fontes (futuras: ASAAS,
     * Inter, Asaas etc) e substitui apenas matches do source atual.
     */
    protected function mergeWithExisting(?array $existing, array $newEntries): array
    {
        $existing = $existing ?? [];
        $thisLabel = $this->source->sourceLabel();
        $sourcePrefix = explode(':', $thisLabel, 2)[0]; // 'firebird_office_json'

        // Remove entradas antigas dessa fonte (mesmo prefix antes do ':')
        $filtered = array_filter($existing, function ($e) use ($sourcePrefix) {
            $eSource = (string) ($e['source'] ?? '');
            return ! str_starts_with($eSource, $sourcePrefix);
        });

        return array_values(array_merge(array_values($filtered), $newEntries));
    }
}
