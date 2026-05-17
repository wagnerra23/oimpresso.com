<?php

declare(strict_types=1);

namespace Modules\Arquivos\Services;

use App\Util\OtelHelper;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Arquivos\Entities\Arquivo;

/**
 * ArquivosRetentionService — Wave 18 D9 + D5 SATURATION (2026-05-16).
 *
 * Service dedicado pra políticas de retenção LGPD do DMS backbone
 * ({@see ADR 0123} §retention). Extrai lógica antes embutida em comando
 * `arquivos:retention-cleanup` direto, ganhando observabilidade OTel
 * + cobertura Pest reflection.
 *
 * **Tier 0 multi-tenant IRREVOGÁVEL** ({@see ADR 0093}): toda query DB
 * respeita `business_id` (Arquivo usa HasBusinessScope global scope).
 *
 * **LGPD Art. 16** (eliminação após cumprimento da finalidade): caller
 * passa `retention_days` configurado por business (default 1825 = 5 anos
 * fiscal NFe). Service NÃO decide política — apenas executa.
 *
 * **OTel spans** ({@see ADR 0155}) — 4 spans cobrem fluxo end-to-end:
 *   - `arquivos.retention.scan` — lista arquivos elegíveis (read-only)
 *   - `arquivos.retention.expire_one` — soft-delete individual (audit)
 *   - `arquivos.retention.purge_one` — hard-delete + remove storage (irreversível)
 *   - `arquivos.retention.run` — orquestração batch (entrypoint command)
 *
 * Defesa em profundidade: caller deve passar `dry_run=true` em produção
 * pra simular antes (idempotent — não muta nada). Apenas Wagner aprovação
 * Tier 0 permite `dry_run=false` cross-tenant em batch.
 *
 * @see Modules\Arquivos\Console\RetentionCleanupCommand
 * @see memory/decisions/0123-modules-arquivos-backbone.md §retention
 */
class ArquivosRetentionService
{
    /**
     * Lista arquivos elegíveis pra expiração (created_at > cutoff).
     *
     * Span: `arquivos.retention.scan` — atributos sem PII (apenas count/cutoff).
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, Arquivo>
     */
    public function scanExpired(int $businessId, int $retentionDays): \Illuminate\Database\Eloquent\Collection
    {
        return OtelHelper::spanBiz('arquivos.retention.scan', function () use ($businessId, $retentionDays) {
            $cutoff = Carbon::now()->subDays($retentionDays);

            return Arquivo::query()
                ->where('business_id', $businessId)
                ->whereNull('deleted_at')
                ->where('created_at', '<', $cutoff)
                ->limit(1000) // batch cap defensivo
                ->get(['id', 'business_id', 'storage_disk', 'storage_path', 'created_at']);
        }, [
            'module'         => 'Arquivos',
            'business_id'    => $businessId,
            'retention_days' => $retentionDays,
        ]);
    }

    /**
     * Soft-delete individual de um arquivo. Idempotente — re-chamar é no-op.
     *
     * Span: `arquivos.retention.expire_one` — audit por arquivo (debug latência).
     */
    public function expireOne(Arquivo $arquivo): bool
    {
        return OtelHelper::spanBiz('arquivos.retention.expire_one', function () use ($arquivo) {
            if ($arquivo->trashed()) {
                return false; // já soft-deleted — idempotente
            }

            $arquivo->delete(); // soft-delete via SoftDeletes trait

            Log::info('arquivos.retention.expired', [
                'arquivo_id'  => $arquivo->id,
                'business_id' => $arquivo->business_id,
                'created_at'  => $arquivo->created_at?->toIso8601String(),
            ]);

            return true;
        }, [
            'module'      => 'Arquivos',
            'arquivo_id'  => $arquivo->id,
            'business_id' => $arquivo->business_id,
        ]);
    }

    /**
     * Hard-delete + remove storage (irreversível) — LGPD Art. 16 cumprimento.
     *
     * Span: `arquivos.retention.purge_one` — alta gravidade, audit obrigatório.
     *
     * Idempotente: se row já não existe, retorna false. Sempre tenta limpar
     * storage (pode haver órfão de soft-delete anterior).
     */
    public function purgeOne(Arquivo $arquivo): bool
    {
        return OtelHelper::spanBiz('arquivos.retention.purge_one', function () use ($arquivo) {
            $diskName = $arquivo->storage_disk;
            $path = $arquivo->storage_path;

            // Tenta remover storage (não falha se ausente — fail-open por design)
            try {
                if ($diskName && $path && \Illuminate\Support\Facades\Storage::disk($diskName)->exists($path)) {
                    \Illuminate\Support\Facades\Storage::disk($diskName)->delete($path);
                }
            } catch (\Throwable $e) {
                Log::warning('arquivos.retention.purge_storage_failed', [
                    'arquivo_id' => $arquivo->id,
                    'disk'       => $diskName,
                    'error'      => $e->getMessage(),
                ]);
            }

            // Force-delete DB (bypassa SoftDeletes)
            $arquivo->forceDelete();

            Log::warning('arquivos.retention.purged', [
                'arquivo_id'  => $arquivo->id,
                'business_id' => $arquivo->business_id,
                'storage'     => "{$diskName}:{$path}",
            ]);

            return true;
        }, [
            'module'      => 'Arquivos',
            'arquivo_id'  => $arquivo->id,
            'business_id' => $arquivo->business_id,
        ]);
    }

    /**
     * Orquestra scan + expire/purge em batch por business.
     *
     * Span: `arquivos.retention.run` — entrypoint command.
     *
     * @param  bool  $dryRun  true = scan + log, sem mutação (default true defesa em profundidade)
     * @return array{scanned:int, expired:int, purged:int, dry_run:bool}
     */
    public function run(int $businessId, int $retentionDays, bool $dryRun = true, bool $purge = false): array
    {
        return OtelHelper::spanBiz('arquivos.retention.run', function () use ($businessId, $retentionDays, $dryRun, $purge) {
            $eligible = $this->scanExpired($businessId, $retentionDays);
            $expired = 0;
            $purged = 0;

            foreach ($eligible as $arquivo) {
                if ($dryRun) {
                    continue; // só conta, não muta
                }

                if ($purge) {
                    if ($this->purgeOne($arquivo)) {
                        $purged++;
                    }
                } else {
                    if ($this->expireOne($arquivo)) {
                        $expired++;
                    }
                }
            }

            return [
                'scanned' => $eligible->count(),
                'expired' => $expired,
                'purged'  => $purged,
                'dry_run' => $dryRun,
            ];
        }, [
            'module'         => 'Arquivos',
            'business_id'    => $businessId,
            'retention_days' => $retentionDays,
            'dry_run'        => $dryRun,
            'purge'          => $purge,
        ]);
    }
}
