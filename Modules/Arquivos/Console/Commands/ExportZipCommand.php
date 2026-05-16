<?php

namespace Modules\Arquivos\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Modules\Arquivos\Services\VaultEncryptionService;
use Modules\Jana\Services\Privacy\PiiRedactor;

/**
 * arquivos:export-zip — Sprint 2 ADR 0123 (LGPD Art. 18 portabilidade de dados).
 *
 * LGPD Art. 18 garante ao titular o direito à portabilidade — empresa precisa
 * exportar todos os arquivos de um business em formato estruturado sob demanda.
 *
 * Fluxo:
 *   1. Valida --business obrigatório (≥1)
 *   2. Resolve path output (default storage/app/exports/biz-{N}-{YYYY-MM-DD-HHmmss}.zip)
 *   3. Cria diretório output se necessário
 *   4. Query arquivos WHERE business_id = ? (+ filtros --include-vault / --include-deleted)
 *   5. Cap interno 5000 rows — warn + LIMIT se ultrapassar
 *   6. Pra cada row: lê file físico, decrypt se encrypted=true via VaultEncryptionService
 *   7. Adiciona ao ZIP em path relativo: {bucket}/{sub_destination}/{arquivable_type-short}/{original_name}
 *   8. Cria _MANIFEST.json no root do ZIP com metadados LGPD-auditáveis
 *   9. Insere audit log "exported" por arquivo (LGPD compliance trail obrigatório)
 *  10. Dry-run: lista files + total bytes estimado, sem criar ZIP nem audit log
 *
 * Multi-tenant Tier 0 (ADR 0093):
 *   Command CLI sem session — --business é filtro EXPLÍCITO obrigatório.
 *   Audit log SEMPRE inclui business_id original do row para rastreio LGPD.
 *   withoutGlobalScopes + DB::table direto para acessar deleted_at IS NOT NULL.
 *
 * Uso:
 *   php artisan arquivos:export-zip --business=1
 *   php artisan arquivos:export-zip --business=1 --output=/tmp/export-biz1.zip
 *   php artisan arquivos:export-zip --business=1 --include-vault --include-deleted
 *   php artisan arquivos:export-zip --business=1 --dry-run
 *
 * @see Modules/Arquivos/Services/VaultEncryptionService.php (decrypt vault)
 * @see memory/decisions/0123-modules-arquivos-backbone.md Sprint 2
 * @see LGPD Art. 18 (portabilidade de dados pessoais)
 */
class ExportZipCommand extends Command
{
    protected $signature = 'arquivos:export-zip
        {--business= : business_id obrigatório (CLI sem session)}
        {--output= : path absoluto do ZIP de output (default: storage/app/exports/biz-{N}-{date}.zip)}
        {--include-vault : Incluir arquivos com bucket=sensitive (default false — vault sensitive precisa razão explícita)}
        {--include-deleted : Incluir soft-deleted rows (default false — só active)}
        {--dry-run : Não cria ZIP, só lista + log}';

    protected $description = 'Exporta arquivos de um business em ZIP estruturado — LGPD Art. 18 portabilidade (ADR 0123).';

    /** Cap interno de rows para proteger memória e file system. */
    private const ROW_CAP = 5000;

    /** Compressão DEFLATE nível 6 — balance velocidade/tamanho. */
    private const COMPRESS_LEVEL = 6;

    public function handle(VaultEncryptionService $vault): int
    {
        if (! Schema::hasTable('arquivos')) {
            $this->error('arquivos table missing — rode Modules/Arquivos migrate primeiro.');
            return 1;
        }

        // ── Validação obrigatória de --business ───────────────────────────────
        $businessOption = $this->option('business');
        if ($businessOption === null) {
            $this->error('--business é obrigatório. Informe o business_id a exportar.');
            $this->line('  Exemplo: php artisan arquivos:export-zip --business=1');
            return 1;
        }

        $businessId = (int) $businessOption;
        if ($businessId < 1) {
            $this->error('--business deve ser um inteiro ≥ 1. Recebido: ' . $businessOption);
            return 1;
        }

        // ── Opções ───────────────────────────────────────────────────────────
        $includeVault   = (bool) $this->option('include-vault');
        $includeDeleted = (bool) $this->option('include-deleted');
        $dryRun         = (bool) $this->option('dry-run');

        // ── Resolve path de output ───────────────────────────────────────────
        $outputPath = $this->resolveOutputPath($businessId);

        // ── Header informativo ────────────────────────────────────────────────
        $this->line("Export ZIP — business_id={$businessId} | include_vault=" . ($includeVault ? 'yes' : 'no') . ' | include_deleted=' . ($includeDeleted ? 'yes' : 'no'));
        $this->line('  Output: ' . $outputPath);

        if ($dryRun) {
            $this->warn('[DRY-RUN] Nenhum arquivo será criado e nenhum audit log será inserido.');
        }

        $this->newLine();

        // ── Query arquivos ────────────────────────────────────────────────────
        // CLI sem session → DB::table direto (bypass GlobalScopes multi-tenant).
        // business_id explícito é o filtro multi-tenant (ADR 0093 Tier 0).
        $query = DB::table('arquivos')
            ->where('business_id', $businessId);

        if (! $includeVault) {
            $query->where('bucket', '!=', 'sensitive');
        }

        if (! $includeDeleted) {
            $query->whereNull('deleted_at');
        }

        $total = (clone $query)->count();

        if ($total === 0) {
            $this->info('Nenhum arquivo encontrado com os critérios informados.');
            return 0;
        }

        // ── Cap 5000 rows ────────────────────────────────────────────────────
        $capped = false;
        if ($total > self::ROW_CAP) {
            $this->warn("Total {$total} rows ultrapassa o cap de " . self::ROW_CAP . " — exportando apenas as primeiras " . self::ROW_CAP . " rows.");
            $this->warn('  Sugestão: use --business + filtros mais específicos, ou dividir em múltiplas execuções.');
            $capped = true;
        }

        $rows = $query->orderBy('id')->limit(self::ROW_CAP)->get();
        $rowCount = $rows->count();

        // ── Dry-run: lista arquivos e bytes estimados ─────────────────────────
        if ($dryRun) {
            return $this->runDryRun($rows, $total, $capped);
        }

        // ── Cria diretório de output ──────────────────────────────────────────
        $outputDir = dirname($outputPath);
        if (! is_dir($outputDir)) {
            if (! mkdir($outputDir, 0755, true) && ! is_dir($outputDir)) {
                $this->error("Falha ao criar diretório de output: {$outputDir}");
                return 1;
            }
        }

        // ── Cria ZIP ─────────────────────────────────────────────────────────
        $zip = new \ZipArchive();
        $zipResult = $zip->open($outputPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);

        if ($zipResult !== true) {
            $this->error("Falha ao criar arquivo ZIP em: {$outputPath} (código ZipArchive: {$zipResult})");
            return 1;
        }

        // Stats
        $stats = [
            'exported'     => 0,
            'missing_file' => 0,
            'errored'      => 0,
            'total_bytes'  => 0,
        ];

        $manifestFiles = [];

        $this->line("Processando {$rowCount} arquivo(s)...");
        $this->newLine();

        // ── Itera rows e adiciona ao ZIP ──────────────────────────────────────
        foreach ($rows as $row) {
            $result = $this->processRow($row, $zip, $vault, $stats, $manifestFiles);
            if ($result === 'ok') {
                // Audit log LGPD Art. 18 — rastreabilidade obrigatória por arquivo exportado
                $this->insertAuditLog($row, $outputPath);
            }
        }

        // ── Manifest JSON ─────────────────────────────────────────────────────
        $manifest = [
            'business_id'     => $businessId,
            'generated_at'    => now()->toIso8601String(),
            'command_version' => '1.0',
            'include_vault'   => $includeVault,
            'include_deleted' => $includeDeleted,
            'capped_at'       => $capped ? self::ROW_CAP : null,
            'total_rows_found'=> $total,
            'total_files'     => $stats['exported'],
            'total_bytes'     => $stats['total_bytes'],
            'files'           => $manifestFiles,
        ];

        $zip->addFromString('_MANIFEST.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $zip->close();

        // ── Output final ──────────────────────────────────────────────────────
        $this->newLine();

        if ($stats['exported'] === 0) {
            $this->warn('Nenhum arquivo foi adicionado ao ZIP (todos missing ou com erro). ZIP vazio gerado com manifest.');
        } else {
            $this->info("ZIP exportado: {$outputPath} ({$stats['total_bytes']} bytes, {$stats['exported']} files)");
        }

        if ($stats['missing_file'] > 0) {
            $this->warn("File ausente (disk/orphan): {$stats['missing_file']}");
        }

        if ($stats['errored'] > 0) {
            $this->error("Errored: {$stats['errored']}");
        }

        Log::info('arquivos.export_zip.summary', [
            'business_id'    => $businessId,
            'output_path'    => $outputPath,
            'exported'       => $stats['exported'],
            'missing_file'   => $stats['missing_file'],
            'errored'        => $stats['errored'],
            'total_bytes'    => $stats['total_bytes'],
            'include_vault'  => $includeVault,
            'include_deleted'=> $includeDeleted,
        ]);

        // Exit 2 se errored > total exportado/2
        $processed = $stats['exported'] + $stats['errored'];
        if ($processed > 0 && $stats['errored'] > $processed / 2) {
            return 2;
        }

        return 0;
    }

    // -------------------------------------------------------------------------
    // Dry-run: lista arquivos sem criar ZIP nem audit log
    // -------------------------------------------------------------------------

    /**
     * Modo dry-run: imprime lista de arquivos e total bytes estimado.
     * Não cria ZIP, não insere audit log (LGPD — só logar quando realmente exportar).
     *
     * @param  \Illuminate\Support\Collection  $rows
     * @param  int                             $total   Total de rows encontradas (pode > cap)
     * @param  bool                            $capped  Se total foi capado
     */
    private function runDryRun($rows, int $total, bool $capped): int
    {
        $estimatedBytes = 0;
        $count = 0;

        foreach ($rows as $row) {
            $pathInZip = $this->resolvePathInZip($row);
            $this->line("  [{$row->id}] biz:{$row->business_id} disk:{$row->disk} → {$pathInZip} ({$row->size_bytes} bytes)");
            $estimatedBytes += (int) ($row->size_bytes ?? 0);
            $count++;
        }

        $this->newLine();

        if ($capped) {
            $this->warn("[DRY-RUN] {$count} files seriam exportados (cap " . self::ROW_CAP . " de {$total} total), total estimado {$estimatedBytes} bytes");
        } else {
            $this->info("[DRY-RUN] {$count} files seriam exportados, total estimado {$estimatedBytes} bytes");
        }

        return 0;
    }

    // -------------------------------------------------------------------------
    // Processa uma row: lê file, decrypt se necessário, adiciona ao ZIP
    // -------------------------------------------------------------------------

    /**
     * Processa uma row e adiciona o file ao ZIP.
     *
     * @param  \stdClass              $row          Row da tabela arquivos
     * @param  \ZipArchive            $zip          Instância ZipArchive já aberta
     * @param  VaultEncryptionService $vault        Serviço de decrypt vault
     * @param  array<string,int>      $stats        Stats mutáveis por referência
     * @param  array                  $manifestFiles Acumula entradas pro manifest por referência
     * @return string  'ok' | 'missing' | 'error'
     */
    private function processRow(
        \stdClass $row,
        \ZipArchive $zip,
        VaultEncryptionService $vault,
        array &$stats,
        array &$manifestFiles
    ): string {
        try {
            $pathInZip = $this->resolvePathInZip($row);
            $diskName  = $row->disk ?: 'local';

            // Verifica existência do file no disk antes de tentar ler
            if (! Storage::disk($diskName)->exists($row->storage_path)) {
                $stats['missing_file']++;
                // D7 LGPD (Wave 10): path/filename pode conter CPF/CNPJ embutido
                // ("biz-1/2026/05/ab12.../contrato-123.456.789-00.pdf") — redact.
                Log::warning('arquivos.export_zip.file_missing', [
                    'arquivo_id'  => $row->id,
                    'business_id' => $row->business_id,
                    'disk'        => $diskName,
                    'path'        => $this->redactPii((string) $row->storage_path),
                ]);
                return 'missing';
            }

            // Lê conteúdo — decrypt se arquivo está encriptado (vault)
            if (! empty($row->encrypted)) {
                try {
                    $contents = $vault->getDecrypted($diskName, $row->storage_path);
                } catch (DecryptException $e) {
                    $stats['errored']++;
                    Log::error('arquivos.export_zip.decrypt_error', [
                        'arquivo_id'  => $row->id,
                        'business_id' => $row->business_id,
                        'error'       => substr($e->getMessage(), 0, 200),
                    ]);
                    return 'error';
                }
            } else {
                $contents = Storage::disk($diskName)->get($row->storage_path);
            }

            if (! is_string($contents)) {
                $stats['missing_file']++;
                return 'missing';
            }

            // Adiciona ao ZIP com compressão DEFLATE nível 6
            $zip->addFromString($pathInZip, $contents);
            $zip->setCompressionName($pathInZip, \ZipArchive::CM_DEFLATE, self::COMPRESS_LEVEL);

            $fileBytes   = strlen($contents);
            $stats['exported']++;
            $stats['total_bytes'] += $fileBytes;

            // Acumula entrada no manifest LGPD
            $manifestFiles[] = [
                'path_in_zip'       => $pathInZip,
                'arquivo_id'        => $row->id,
                'md5'               => $row->md5 ?? md5($contents),
                'encrypted_at_rest' => ! empty($row->encrypted),
                'size_bytes'        => $fileBytes,
                'bucket'            => $row->bucket ?? null,
                'original_name'     => $row->original_name ?? $row->filename ?? null,
                'deleted_at'        => $row->deleted_at ?? null,
            ];

            return 'ok';
        } catch (\Throwable $e) {
            $stats['errored']++;
            Log::error('arquivos.export_zip.error', [
                'arquivo_id'  => $row->id ?? null,
                'business_id' => $row->business_id ?? null,
                'error'       => substr($e->getMessage(), 0, 200),
            ]);
            return 'error';
        }
    }

    // -------------------------------------------------------------------------
    // Audit log LGPD Art. 18 — trail obrigatório por arquivo exportado
    // -------------------------------------------------------------------------

    /**
     * Insere registro de auditoria "exported" na tabela arquivos_audit_log.
     *
     * LGPD Art. 18 exige rastreabilidade: quem exportou, quando, pra onde.
     * CLI sem session → user_id = null, razão no payload.
     */
    private function insertAuditLog(\stdClass $row, string $outputPath): void
    {
        try {
            DB::table('arquivos_audit_log')->insert([
                'arquivo_id'  => $row->id,
                'business_id' => $row->business_id,
                'user_id'     => null, // CLI sem sessão — user_action registrado no payload
                'action'      => 'exported',
                'payload'     => json_encode([
                    'exported_to' => $outputPath,
                    'business_id' => $row->business_id,
                    'command'     => 'arquivos:export-zip',
                    'lgpd_art18'  => true,
                ]),
                'created_at'  => now()->toDateTimeString(),
            ]);
        } catch (\Throwable $e) {
            // Falha no audit log não deve interromper o export (log + continue)
            Log::error('arquivos.export_zip.audit_log_error', [
                'arquivo_id'  => $row->id ?? null,
                'business_id' => $row->business_id ?? null,
                'error'       => substr($e->getMessage(), 0, 200),
            ]);
        }
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Resolve o path relativo do arquivo dentro do ZIP.
     *
     * Formato: {bucket}/{sub_destination}/{arquivable_type-short}/{original_name}
     * Preserva organização lógica do DMS (ADR 0123).
     *
     * Exemplos:
     *   active/nfe-xml/Modules-NfeBrasil-Models-NfeEmissao/nfe-001.xml
     *   sensitive/documentos/Modules-Financeiro-Models-Contrato/contrato.pdf
     */
    private function resolvePathInZip(\stdClass $row): string
    {
        // Bucket (active, sensitive, archive, etc.)
        $bucket = $row->bucket ?? 'active';

        // Sub-destination (subpasta lógica dentro do bucket)
        $sub = isset($row->sub_destination) && $row->sub_destination !== ''
            ? $row->sub_destination
            : 'geral';

        // Tipo curto do arquivable (namespace → slug com hífen)
        $typeShort = $this->arquivableTypeToSlug($row->arquivable_type ?? 'Unknown');

        // Nome original do arquivo (fallback para basename do storage_path)
        $filename = $row->original_name
            ?? $row->filename
            ?? basename($row->storage_path ?? 'arquivo');

        // Sanitiza componentes para evitar path traversal no ZIP
        $bucket    = $this->sanitizeZipComponent($bucket);
        $sub       = $this->sanitizeZipComponent($sub);
        $typeShort = $this->sanitizeZipComponent($typeShort);
        $filename  = $this->sanitizeZipComponent($filename);

        return "{$bucket}/{$sub}/{$typeShort}/{$filename}";
    }

    /**
     * Converte FQCN arquivable_type em slug com hífen para uso em path ZIP.
     *
     * Exemplo: "Modules\NfeBrasil\Models\NfeEmissao" → "Modules-NfeBrasil-Models-NfeEmissao"
     */
    private function arquivableTypeToSlug(string $type): string
    {
        // Substitui backslashes e barras por hífen, remove chars inválidos
        return preg_replace('/[\\\\\/]+/', '-', $type);
    }

    /**
     * D7 LGPD (Wave 10) — redactor seguro pra paths/filenames em logs.
     *
     * Filename original pode trazer PII embutida (ex: "rg-123.456.789-00.pdf",
     * "contrato-cnpj-12.345.678-0001-90.pdf", "boleto-email-foo@bar.com.pdf").
     * Fail-open: se PiiRedactor não resolver (boot/teardown), retorna input.
     */
    private function redactPii(string $input): string
    {
        if ($input === '') {
            return '';
        }

        try {
            return App::make(PiiRedactor::class)->redact($input);
        } catch (\Throwable) {
            return $input;
        }
    }

    /**
     * Sanitiza um componente de path para uso dentro de ZIP.
     * Remove path traversal (../) e caracteres problemáticos.
     */
    private function sanitizeZipComponent(string $component): string
    {
        // Remove path separators e sequências de ".."
        $component = str_replace(['/', '\\', '..'], ['-', '-', ''], $component);
        // Remove caracteres de controle e NULL bytes
        $component = preg_replace('/[\x00-\x1f\x7f]/', '', $component);
        // Trunca a 200 chars para compatibilidade de path no ZIP
        return mb_substr(trim($component), 0, 200) ?: 'arquivo';
    }

    /**
     * Resolve o path de output para o ZIP.
     *
     * Se --output foi informado, usa o valor literalmente.
     * Caso contrário, gera default em storage/app/exports/biz-{N}-{YYYY-MM-DD-HHmmss}.zip.
     */
    private function resolveOutputPath(int $businessId): string
    {
        $outputOption = $this->option('output');

        if ($outputOption !== null && $outputOption !== '') {
            return $outputOption;
        }

        $date = now()->format('Y-m-d-His');
        $exportsDir = storage_path("app/exports");

        return "{$exportsDir}/biz-{$businessId}-{$date}.zip";
    }
}
