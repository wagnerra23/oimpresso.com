<?php

namespace Modules\Arquivos\Services;

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
     * Escreve $contents encriptado em disk/path. Retorna true se sucesso.
     *
     * Usa Crypt::encryptString — base64 + HMAC envelope, APP_KEY-backed.
     */
    public function putEncrypted(string $disk, string $path, string $contents): bool
    {
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
    }

    /**
     * Sobe UploadedFile encriptado pra $disk/$path. Retorna true se sucesso.
     *
     * Streaming não é seguro com Crypt (precisa carregar conteúdo inteiro pra
     * encrypt em uma chamada). Aceitável: cap upload 50MB ADR 0123.
     */
    public function putFileEncrypted(string $disk, string $path, UploadedFile $file): bool
    {
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
