<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * Migration backfill — US-ARQ-020 (Sprint 3 dia 2 do ADR 0123).
 *
 * Cria rows em `arquivos` table pra cada xml_path/danfe_path existente em:
 * - nfe_emissoes.xml_path        → bucket=active sub_destination=nfe-xml
 * - nfe_emissoes.danfe_path      → bucket=active sub_destination=nfe-danfe
 * - nfe_dfe_recebidos.xml_path   → bucket=active sub_destination=nfe-xml
 *
 * Polimorfismo Eloquent:
 *   arquivable_type = 'Modules\\NfeBrasil\\Models\\NfeEmissao' (ou NfeDfeRecebido)
 *   arquivable_id   = nfe_emissoes.id
 *
 * Idempotente: verifica se Arquivo já existe pra (arquivable, sub_destination)
 * antes de inserir — re-rodar migration não duplica.
 *
 * Strategy:
 *   - SELECT em batch (chunk 500 rows)
 *   - INSERT row arquivos com path legacy preservado em storage_path
 *   - md5: tenta calcular do file físico se exists; senão usa hash do path
 *     (placeholder — Sprint 4 US-ARQ-024 recalcula md5 real on-demand)
 *   - size_bytes: tenta ler do file; senão 0 (placeholder)
 *
 * Rollback (down): DELETE FROM arquivos WHERE classified_by='backfill-us-arq-020'.
 *
 * Multi-tenant Tier 0 ([ADR 0093](memory/decisions/0093-multi-tenant-isolation-tier-0.md)):
 * arquivos.business_id herda de nfe_emissoes.business_id (preserva isolamento).
 *
 * @see memory/decisions/0123-modules-arquivos-backbone.md Sprint 3 plano
 */
return new class extends Migration {
    private const SOURCE_TAG = 'backfill-us-arq-020';

    public function up(): void
    {
        if (! Schema::hasTable('arquivos')) {
            $this->log('arquivos table missing — skip (rode Modules/Arquivos migrate primeiro)');
            return;
        }

        $this->backfillTable(
            tableName: 'nfe_emissoes',
            arquivableType: 'Modules\\NfeBrasil\\Models\\NfeEmissao',
            pathColumns: [
                'xml_path'   => 'nfe-xml',
                'danfe_path' => 'nfe-danfe',
            ],
        );

        $this->backfillTable(
            tableName: 'nfe_dfe_recebidos',
            arquivableType: 'Modules\\NfeBrasil\\Models\\NfeDfeRecebido',
            pathColumns: [
                'xml_path' => 'nfe-xml',
            ],
        );
    }

    public function down(): void
    {
        if (! Schema::hasTable('arquivos')) return;

        DB::table('arquivos')
            ->where('classified_by', self::SOURCE_TAG)
            ->delete();
    }

    /**
     * Itera tabela em chunks + cria Arquivo row pra cada path não-vazio,
     * skip se já existir (idempotente).
     */
    private function backfillTable(string $tableName, string $arquivableType, array $pathColumns): void
    {
        if (! Schema::hasTable($tableName)) {
            $this->log("{$tableName} missing — skip");
            return;
        }

        // Verifica se colunas anexo existem (pode já ter sido removida em US-ARQ-021)
        $existingColumns = Schema::getColumnListing($tableName);
        $availableColumns = array_intersect(array_keys($pathColumns), $existingColumns);
        if (empty($availableColumns)) {
            $this->log("{$tableName} sem colunas {xml_path,danfe_path} — skip (talvez US-ARQ-021 já rodou)");
            return;
        }

        $totalCreated = 0;
        $totalSkipped = 0;

        DB::table($tableName)
            ->select(['id', 'business_id', ...$availableColumns])
            ->orderBy('id')
            ->chunk(500, function ($rows) use (
                $tableName,
                $arquivableType,
                $pathColumns,
                $availableColumns,
                &$totalCreated,
                &$totalSkipped,
            ) {
                foreach ($rows as $row) {
                    foreach ($availableColumns as $column) {
                        $subDestination = $pathColumns[$column];
                        $path = $row->{$column} ?? null;
                        if (! $path) continue;

                        $exists = DB::table('arquivos')
                            ->where('arquivable_type', $arquivableType)
                            ->where('arquivable_id', $row->id)
                            ->where('sub_destination', $subDestination)
                            ->exists();
                        if ($exists) {
                            $totalSkipped++;
                            continue;
                        }

                        DB::table('arquivos')->insert($this->buildArquivoRow(
                            $arquivableType,
                            $row,
                            $path,
                            $subDestination,
                        ));
                        $totalCreated++;
                    }
                }
            });

        $this->log("{$tableName}: criados={$totalCreated} skipped={$totalSkipped}");
    }

    private function buildArquivoRow(string $arquivableType, $row, string $path, string $subDestination): array
    {
        $disk = Storage::disk('local');
        $exists = false;
        $size = 0;
        $md5 = null;

        try {
            $exists = $disk->exists($path);
            if ($exists) {
                $size = $disk->size($path);
                $contents = $disk->get($path);
                if (is_string($contents)) {
                    $md5 = md5($contents);
                }
            }
        } catch (\Throwable $e) {
            // file pode estar em outro disk ou movido — não bloqueia backfill
        }

        // Placeholder md5 se file ausente/inacessível (Sprint 4 US-ARQ-024 recalcula)
        if (! $md5) {
            $md5 = md5($arquivableType . ':' . $row->id . ':' . $path);
        }

        return [
            'business_id'         => $row->business_id ?? 0,
            'arquivable_type'     => $arquivableType,
            'arquivable_id'       => $row->id,
            'disk'                => 'local', // legacy — Sprint 4 migra pra disk 'arquivos'
            'storage_path'        => $path,
            'original_name'       => basename($path),
            'mime_type'           => str_ends_with($path, '.pdf') ? 'application/pdf' : 'application/xml',
            'size_bytes'          => $size,
            'md5'                 => $md5,
            'bucket'              => 'active',
            'sub_destination'     => $subDestination,
            'sensitive_flags'     => null,
            'classified_by'       => self::SOURCE_TAG,
            'classified_at'       => now(),
            'uploaded_by_user_id' => null,
            'visibility'          => 'private',
            'encrypted'           => false,
            'retention_days'      => null,
            'created_at'          => now(),
            'updated_at'          => now(),
        ];
    }

    private function log(string $msg): void
    {
        echo "  [arquivos-backfill] {$msg}\n";
    }
};
