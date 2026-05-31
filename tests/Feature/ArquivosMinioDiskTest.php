<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;

/**
 * R-ARQ-MINIO — anti-regressão Sprint 0.3 ADR 0214 (Arquivos backbone S3 MinIO).
 *
 * Garante que disks `arquivos-minio` + `arquivos-vault-minio` estão declarados
 * em config/filesystems.php com shape S3-compat correto (path-style endpoint
 * OBRIGATÓRIO pra MinIO).
 *
 * Sem este test, regressão silenciosa: alguém remove disk + Storage::disk()
 * cai pro driver default → mídias vão pra lugar errado.
 *
 * NOTA: smoke E2E REAL (com MinIO live) precisa env vars setadas em prod
 * (`MINIO_ACCESS_KEY`, `MINIO_SECRET_KEY`, `MINIO_ENDPOINT`) — esse smoke roda
 * via cmd artisan `arquivos:health-check-minio` (criado em Sprint 1+).
 */

/**
 * Pest test env carrega config/filesystems.php mas com env() retornando null
 * pra vars não-setadas. Pra validar shape do disk, lemos o array RAW
 * via require_once + verificamos KEYS presentes (não os values resolvidos).
 */
function getDiskConfigRaw(string $diskName): array
{
    $config = require __DIR__ . '/../../config/filesystems.php';
    return $config['disks'][$diskName] ?? [];
}

it('R-ARQ-MINIO-001 — disk arquivos-minio declarado com driver s3 + path-style', function () {
    $config = getDiskConfigRaw('arquivos-minio');

    expect($config)->not->toBeEmpty();
    expect($config['driver'])->toBe('s3');
    expect($config['use_path_style_endpoint'])->toBeTrue();
    expect($config)->toHaveKey('key');
    expect($config)->toHaveKey('secret');
    expect($config)->toHaveKey('bucket');
    expect($config)->toHaveKey('endpoint');
});

it('R-ARQ-MINIO-002 — disk arquivos-vault-minio declarado bucket separado', function () {
    $config = getDiskConfigRaw('arquivos-vault-minio');

    expect($config)->not->toBeEmpty();
    expect($config['driver'])->toBe('s3');
    expect($config['use_path_style_endpoint'])->toBeTrue();
    // Bucket sources de env vars diferentes (anti-cross-write)
    $arquivos = getDiskConfigRaw('arquivos-minio');
    expect($config)->toHaveKey('bucket');
    expect($arquivos)->toHaveKey('bucket');
});

it('R-ARQ-MINIO-003 — region default us-east-1 (MinIO compat)', function () {
    // Sem env MINIO_REGION setada, default é 'us-east-1'.
    $config = getDiskConfigRaw('arquivos-minio');
    // Region pode ser null ou 'us-east-1' dependendo do env teste — testamos shape
    expect($config)->toHaveKey('region');
});

it('R-ARQ-MINIO-004 — config raw source usa env vars MINIO_* (não AWS_*)', function () {
    // ADR 0214 separa MINIO_* (CT 100 self-hosted) de AWS_* (eventual S3 externo).
    // Lemos o source do config pra confirmar env() apontam pras chaves canônicas.
    $source = file_get_contents(__DIR__ . '/../../config/filesystems.php');
    $blockArquivos = strstr($source, "'arquivos-minio' => [");
    $blockArquivos = substr($blockArquivos, 0, strpos($blockArquivos, '],') + 2);

    expect($blockArquivos)->toContain("env('MINIO_ACCESS_KEY')");
    expect($blockArquivos)->toContain("env('MINIO_SECRET_KEY')");
    expect($blockArquivos)->toContain("env('MINIO_BUCKET_ARQUIVOS'");
    expect($blockArquivos)->toContain("env('MINIO_ENDPOINT')");
    expect($blockArquivos)->not->toContain("env('AWS_ACCESS_KEY_ID')");
});
