<?php

namespace Modules\Arquivos\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * arquivos:reencrypt-vault — Sprint 2 ADR 0123 (APP_KEY rotation).
 *
 * Quando Wagner rotacionar APP_KEY, todos arquivos encrypted com a chave VELHA
 * ficam ilegíveis. Este command decrypta cada arquivo com a chave VELHA
 * (passada via --old-key) e re-encrypta com a chave NOVA (APP_KEY atual).
 *
 * Design decisions:
 * - Idempotent: se decrypt com old-key falha (já re-encrypted), skip sem erro
 * - Batch 200 rows por chunk (mesmo padrão de RecalcularMetadataCommand)
 * - Stats: reencrypted/skipped/errored por run
 * - Log por row com business_id (multi-tenant Tier 0 — ADR 0093)
 * - --dry-run: simula sem escrever em disk
 *
 * Uso:
 *   php artisan arquivos:reencrypt-vault \
 *     --old-key="base64:chave-antiga-em-base64..." \
 *     --limit=1000 \
 *     --dry-run
 *
 * ATENCAO: operacao administrativa (CLI). Roda sem session, itera todos businesses.
 * Nao expor como rota web. Executar imediatamente apos rotacionar APP_KEY.
 *
 * @see Modules/Arquivos/Services/VaultEncryptionService.php
 * @see memory/decisions/0123-modules-arquivos-backbone.md S3 (encryption-at-rest)
 */
class ReencryptVaultCommand extends Command
{
    protected $signature = 'arquivos:reencrypt-vault
        {--old-key= : APP_KEY antiga no formato base64:... (obrigatorio)}
        {--limit=1000 : Cap rows a processar (default 1000)}
        {--dry-run : Nao escreve em disk, so simula}';

    protected $description = 'Re-encrypta arquivos vault apos rotacao de APP_KEY (decrypt com chave velha, encrypt com chave nova).';

    public function handle(): int
    {
        if (! Schema::hasTable('arquivos')) {
            $this->error('arquivos table missing — rode Modules/Arquivos migrate primeiro.');
            return 1;
        }

        $oldKey = $this->option('old-key');
        $limit  = (int) $this->option('limit');
        $dryRun = (bool) $this->option('dry-run');

        // 1. Validar --old-key presente e formato correto
        if (empty($oldKey)) {
            $this->error('--old-key e obrigatorio. Formato: base64:<chave-em-base64>');
            $this->line('  Exemplo: --old-key="base64:AbCdEfGhIjKlMnOpQrStUvWxYz..."');
            return 1;
        }

        if (! str_starts_with($oldKey, 'base64:')) {
            $this->error('--old-key invalido: deve comecar com "base64:". Formato: base64:<chave-em-base64>');
            return 1;
        }

        $rawOldKey = base64_decode(substr($oldKey, 7), true);
        if ($rawOldKey === false) {
            $this->error('--old-key invalido: a parte apos "base64:" nao e base64 valido.');
            return 1;
        }

        // 2. Construir Encrypter com chave velha (mesmo cipher do app)
        try {
            $oldEncrypter = new Encrypter($rawOldKey, config('app.cipher', 'AES-256-CBC'));
        } catch (\RuntimeException $e) {
            $this->error('Falha ao construir Encrypter com old-key: ' . $e->getMessage());
            $this->line('  Verifique o tamanho da chave (AES-256-CBC exige 32 bytes / 256 bits).');
            return 1;
        }

        // 3. Query rows encrypted=true
        $query = DB::table('arquivos')
            ->where('encrypted', true)
            ->whereNull('deleted_at');

        $total = (clone $query)->count();

        $this->info("Encontradas {$total} rows com encrypted=true" . ($dryRun ? ' [DRY-RUN]' : ''));
        $this->line("  Limit: {$limit} | Cipher: " . config('app.cipher', 'AES-256-CBC'));

        if ($total === 0) {
            $this->info('Nada pra re-encryptar.');
            return 0;
        }

        $stats = [
            'reencrypted' => 0,
            'skipped'     => 0,
            'errored'     => 0,
        ];

        // 4. Processar em chunks de 200 (mesmo padrao RecalcularMetadataCommand)
        $query->orderBy('id')
            ->limit($limit)
            ->chunk(200, function ($rows) use ($oldEncrypter, $dryRun, &$stats) {
                $batchIds = $rows->pluck('id')->all();

                Log::info('arquivos.reencrypt_vault.batch_start', [
                    'batch_size'  => count($batchIds),
                    'first_id'    => $batchIds[0] ?? null,
                    'last_id'     => end($batchIds) ?: null,
                    'dry_run'     => $dryRun,
                ]);

                foreach ($rows as $row) {
                    $this->processRow($row, $oldEncrypter, $dryRun, $stats);
                }
            });

        // 5. Sumario final
        $this->newLine();
        $this->info("Re-encryptados: {$stats['reencrypted']}");
        $this->warn("Skipped (ja re-encrypted ou file ausente): {$stats['skipped']}");

        if ($stats['errored'] > 0) {
            $this->error("Errored:        {$stats['errored']}");
        } else {
            $this->line("Errored:        {$stats['errored']}");
        }

        Log::info('arquivos.reencrypt_vault.summary', [
            'reencrypted' => $stats['reencrypted'],
            'skipped'     => $stats['skipped'],
            'errored'     => $stats['errored'],
            'dry_run'     => $dryRun,
        ]);

        return $stats['errored'] > $total / 2 ? 2 : 0;
    }

    /**
     * Processa uma row: decrypt com chave velha e encrypt com chave nova.
     *
     * @param \stdClass          $row         Row da tabela arquivos
     * @param Encrypter          $oldEncrypter Encrypter construido com APP_KEY velha
     * @param bool               $dryRun       Se true, nao escreve em disk
     * @param array<string,int>  $stats        Stats mutaveis por referencia
     */
    private function processRow(\stdClass $row, Encrypter $oldEncrypter, bool $dryRun, array &$stats): void
    {
        try {
            $diskName = $row->disk ?: 'local';
            $disk     = Storage::disk($diskName);

            if (! $disk->exists($row->storage_path)) {
                $stats['skipped']++;
                Log::warning('arquivos.reencrypt_vault.file_missing', [
                    'arquivo_id'   => $row->id,
                    'business_id'  => $row->business_id,
                    'disk'         => $diskName,
                    'storage_path' => $row->storage_path,
                ]);
                return;
            }

            $cipher = $disk->get($row->storage_path);
            if (! is_string($cipher)) {
                $stats['skipped']++;
                return;
            }

            try {
                $plain = $oldEncrypter->decryptString($cipher);
            } catch (DecryptException) {
                $stats['skipped']++;
                Log::info('arquivos.reencrypt_vault.already_reencrypted', [
                    'arquivo_id'  => $row->id,
                    'business_id' => $row->business_id,
                ]);
                return;
            }

            if ($dryRun) {
                $this->line("  [dry] arquivo:{$row->id} biz:{$row->business_id} disk:{$diskName} path:{$row->storage_path}");
                $stats['reencrypted']++;
                return;
            }

            $newCipher = Crypt::encryptString($plain);
            $disk->put($row->storage_path, $newCipher);

            $stats['reencrypted']++;

            Log::info('arquivos.reencrypt_vault.reencrypted', [
                'arquivo_id'  => $row->id,
                'business_id' => $row->business_id,
                'disk'        => $diskName,
            ]);
        } catch (\Throwable $e) {
            $stats['errored']++;
            Log::error('arquivos.reencrypt_vault.error', [
                'arquivo_id'  => $row->id ?? null,
                'business_id' => $row->business_id ?? null,
                'error'       => substr($e->getMessage(), 0, 200),
            ]);
        }
    }
}
