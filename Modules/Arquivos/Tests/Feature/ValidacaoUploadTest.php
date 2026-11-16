<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Modules\Arquivos\Services\ArquivosService;
use Modules\Arquivos\Services\ValidacaoUpload;

/**
 * Auditoria Arquivos 2026-11-05, lote L2 (D4) — o upload compartilhado valida tipo e tamanho.
 * Sem banco. Os tipos aceitos são os que os consumidores mandam hoje (Fisio, Associações,
 * OficinaAuto, app das lojas, NFe); recusa executável, tipo desconhecido e acima do teto.
 */
uses(Tests\TestCase::class);

function vupRecusa(UploadedFile $arquivo): ?string
{
    try {
        app(ValidacaoUpload::class)->validar($arquivo);

        return null;
    } catch (ValidationException $e) {
        return $e->errors()['arquivo'][0] ?? 'sem mensagem';
    }
}

it('aceita o que os consumidores mandam hoje', function (UploadedFile $arquivo) {
    expect(vupRecusa($arquivo))->toBeNull();
})->with([
    'foto JPG (Fisio, OficinaAuto, app)' => fn () => UploadedFile::fake()->create('foto.jpg', 100, 'image/jpeg'),
    'PNG da assinatura (OficinaAuto)'      => fn () => UploadedFile::fake()->create('assinatura-os.png', 20, 'image/png'),
    'HEIC do iPhone (OficinaAuto)'         => fn () => UploadedFile::fake()->create('laudo.heic', 300, 'image/heic'),
    'PDF (Fisio, Associações, app)'        => fn () => UploadedFile::fake()->create('ficha.pdf', 120, 'application/pdf'),
    'vídeo MP4 (Fisio)'                    => fn () => UploadedFile::fake()->create('exercicio.mp4', 2048, 'video/mp4'),
    'XML (NFe)'                            => fn () => UploadedFile::fake()->create('nfe.xml', 10, 'application/xml'),
    'no teto exato de 50 MB'               => fn () => UploadedFile::fake()->create('grande.pdf', 50 * 1024, 'application/pdf'),
]);

it('recusa executável, tipo desconhecido e acima do teto', function (UploadedFile $arquivo) {
    expect(vupRecusa($arquivo))->not->toBeNull();
})->with([
    'executável .exe'                       => fn () => UploadedFile::fake()->create('programa.exe', 10, 'application/x-msdownload'),
    'PHP disfarçado de imagem'              => fn () => UploadedFile::fake()->create('foto.php', 10, 'image/jpeg'),
    'SVG (script embutido)'                 => fn () => UploadedFile::fake()->create('logo.svg', 10, 'image/svg+xml'),
    'binário genérico'                      => fn () => UploadedFile::fake()->create('dados.bin', 10, 'application/octet-stream'),
    '1 KB acima do teto de 50 MB'           => fn () => UploadedFile::fake()->create('grande.pdf', 50 * 1024 + 1, 'application/pdf'),
]);

it('o teto segue a config arquivos.upload_max_mb', function () {
    config()->set('arquivos.upload_max_mb', 1);

    expect(vupRecusa(UploadedFile::fake()->create('a.pdf', 1024, 'application/pdf')))->toBeNull()
        ->and(vupRecusa(UploadedFile::fake()->create('b.pdf', 1025, 'application/pdf')))->toContain('1 MB');
});

it('ArquivosService recusa antes de gravar no storage (attachNoNegocio, sem sessão)', function () {
    Storage::fake('arquivos');
    Storage::fake('vault');
    $dono = new class extends Model
    {
        protected $table = 'vup_dono';
    };
    $dono->id = 1;

    expect(fn () => app(ArquivosService::class)->attachNoNegocio(
        $dono,
        UploadedFile::fake()->create('programa.exe', 10, 'application/x-msdownload'),
        98,
    ))->toThrow(ValidationException::class);

    expect(Storage::disk('arquivos')->allFiles())->toBe([])
        ->and(Storage::disk('vault')->allFiles())->toBe([]);
});
