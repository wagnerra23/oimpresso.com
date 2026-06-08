<?php

namespace Modules\Arquivos\Services;

use App\Util\OtelHelper;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * VaultEncryptionService — wrapper Crypt::encryptString sobre Storage pra disk=vault.
 *
 * Sprint 1 dia 4 (US-ARQ-009 — encryption-at-rest) ADR 0123 §3.
 *
 * Decisão Wagner 2026-05-10: Crypt::encrypt (Laravel native, APP_KEY-backed AES-256-CBC)
 * em vez de league/flysystem-encrypted middleware. Razão:
 * - Zero deps novas (Crypt já está em Laravel core)
 * - Transparente (encrypt no put, decrypt no get — middleware mandaria envelope encryption)
 * - Reversível (mudar APP_KEY = re-encrypt batch via command, não redeploy)
 *
 * Trade-off: arquivos vault NÃO podem ser servidos via Storage::url direto (driver
 * local não-decrypta). Sempre passar pelo DownloadController + signed URL — ADR 0123 §6.
 *
 * @see Modules/Arquivos/Services/ArquivosService.php (attach + classify)
 * @see Modules/Arquivos/Http/Controllers/DownloadController.php
 * @see memory/decisions/0123-modules-arquivos-backbone.md §3 (encryption-at-rest)
 */
class VaultEncryptionService
{
    /**
     * Cap default de 50MB — alinhado com upload_max_mb ADR 0123.
     * Chunked encryption real planejada Sprint 2 (ADR 0126).
     */
    private const MAX_PLAINTEXT_BYTES = 50 * 1024 * 1024;

    /**
     * Retorna o cap em bytes vigente, lendo config arquivos.vault_max_file_size_mb.
     * Default 50MB. Não pode ser desabilitado (<=0 → RuntimeException).
     */
    private function capBytes(): int
    {
        $mb = (int) config('arquivos.vault_max_file_size_mb', 50);
        if ($mb <= 0) {
            throw new \RuntimeException(
                'Vault: config arquivos.vault_max_file_size_mb deve ser > 0. ' .
                'Para aumentar o cap, ajuste ARQUIVOS_VAULT_MAX_FILE_SIZE_MB no .env — ' .
                'desabilitar totalmente não é permitido (ADR 0126).'
            );
        }
        return $mb * 1024 * 1024;
    }

    /**
     * Escreve $contents encriptado em disk/path. Retorna true se sucesso.
     *
     * Usa Crypt::encryptString — base64 + HMAC envelope, APP_KEY-backed.
     *
     * Throw RuntimeException se conteúdo exceder cap configurado (default 50MB).
     * Chunked encryption planejada Sprint 2 — ver ADR 0126.
     */
    public function putEncrypted(string $disk, string $path, string $contents): bool
    {
        // Wave 18 D9.a — span pra encrypt+write (hot-path vault). Atributos sem PII
        // (path/disk + size — jamais conteúdo plaintext).
        return OtelHelper::spanBiz('arquivos.vault.put_encrypted', function () use ($disk, $path, $contents) {
            $cap = $this->capBytes();
            if (strlen($contents) > $cap) {
                throw new \RuntimeException(sprintf(
                    'Vault: conteúdo excede cap %dMB (recebeu %dMB). ' .
                    'Sprint 2 implementará chunked encryption — ver ADR 0126.',
                    intdiv($cap, 1024 * 1024),
                    intdiv(strlen($contents), 1024 * 1024)
                ));
            }

            try {
                $cipher = Crypt::encryptString($contents);
                return Storage::disk($disk)->put($path, $cipher);
            } catch (\Throwable $e) {
                Log::error('arquivos.vault.encrypt_failed', [
                    'disk'  => $disk,
                    'path'  => $path,
                    'error' => $e->getMessage(),
                ]);
                throw $e;
            }
        }, [
            'module'      => 'Arquivos',
            'disk'        => $disk,
            'size_bytes'  => strlen($contents),
        ]);
    }

    /**
     * Sobe UploadedFile encriptado pra $disk/$path. Retorna true se sucesso.
     *
     * Valida tamanho ANTES do file_get_contents para evitar OOM em arquivos
     * maiores que o cap configurado (default 50MB — ADR 0126).
     *
     * Streaming não é seguro com Crypt (precisa carregar conteúdo inteiro pra
     * encrypt em uma chamada). Chunked encryption planejada Sprint 2 (ADR 0126).
     */
    public function putFileEncrypted(string $disk, string $path, UploadedFile $file): bool
    {
        $cap = $this->capBytes();
        $fileSize = $file->getSize();
        if ($fileSize !== false && $fileSize > $cap) {
            throw new \RuntimeException(sprintf(
                'Vault: arquivo "%s" excede cap %dMB (%dMB recebido). ' .
                'Sprint 2 implementará chunked encryption — ver ADR 0126.',
                $file->getClientOriginalName(),
                intdiv($cap, 1024 * 1024),
                intdiv((int) $fileSize, 1024 * 1024)
            ));
        }

        $contents = file_get_contents($file->getRealPath());
        if ($contents === false) {
            throw new \RuntimeException("vault: falha ao ler UploadedFile {$file->getClientOriginalName()}");
        }
        return $this->putEncrypted($disk, $path, $contents);
    }

    /**
     * Lê arquivo encriptado de disk/path e retorna conteúdo decriptado.
     *
     * Throws DecryptException se: APP_KEY mudou + arquivo não foi re-encrypted,
     * arquivo foi tampered, ou path não é Crypt-payload válido.
     */
    public function getDecrypted(string $disk, string $path): ?string
    {
        // Wave 18 D9.a — span pra read+decrypt (hot-path download). Latência
        // observável separa storage I/O vs Crypt decrypt (debug performance).
        return OtelHelper::spanBiz('arquivos.vault.get_decrypted', function () use ($disk, $path) {
            $disk_obj = Storage::disk($disk);
            if (! $disk_obj->exists($path)) {
                return null;
            }

            $cipher = $disk_obj->get($path);
            if (! is_string($cipher)) {
                return null;
            }

            try {
                return Crypt::decryptString($cipher);
            } catch (DecryptException $e) {
                Log::error('arquivos.vault.decrypt_failed', [
                    'disk'  => $disk,
                    'path'  => $path,
                    'error' => $e->getMessage(),
                ]);
                throw $e;
            }
        }, [
            'module' => 'Arquivos',
            'disk'   => $disk,
        ]);
    }

    /**
     * Helper: testa se conteúdo é Crypt-payload válido (sem decrypt).
     * Útil pra debug + verificar arquivo encrypted antes de tentar decrypt.
     */
    public function isEncrypted(string $contents): bool
    {
        $decoded = base64_decode($contents, true);
        if ($decoded === false) {
            return false;
        }
        $payload = json_decode($decoded, true);
        return is_array($payload)
            && isset($payload['iv'], $payload['value'], $payload['mac']);
    }
}
