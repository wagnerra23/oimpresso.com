<?php

declare(strict_types=1);

use App\Support\Privacy\PiiRedactor;
use Illuminate\Http\UploadedFile;
use Modules\Arquivos\Services\ArquivosService;
use Modules\Arquivos\Services\Curador\CuradorStatsReader;
use Modules\Arquivos\Services\VaultEncryptionService;

/**
 * Auditoria Arquivos 2026-11-05, lote L2 (D3) — três consertos pequenos. Sem banco.
 *  1. CuradorStatsReader sem empresa não cai para a empresa 1.
 *  2. Exceção do upload cifrado não leva o nome do arquivo (pode trazer CPF/CNPJ) ao log.
 *  3. Redator de PII do audit falha FECHADO: sem redator, o payload cru não é gravado.
 */
uses(Tests\TestCase::class);

const L2_NOME_SENSIVEL = 'contrato-fulano-de-tal.pdf';

it('CuradorStatsReader sem empresa devolve stub em vez de ler a empresa 1', function () {
    $stats = app(CuradorStatsReader::class)->fetch();

    expect($stats['available'])->toBeFalse()
        ->and($stats['reason'])->toBe('sem_empresa_no_contexto');
});

it('CONTROLE: CuradorStatsReader com empresa na sessão passa da checagem de empresa', function () {
    session(['user.business_id' => 98]);

    $stats = app(CuradorStatsReader::class)->fetch();

    expect($stats['reason'] ?? null)->not->toBe('sem_empresa_no_contexto');
});

it('upload cifrado acima do cap: a exceção não cita o nome do arquivo', function () {
    config()->set('arquivos.vault_max_file_size_mb', 1);
    $arquivo = UploadedFile::fake()->create(L2_NOME_SENSIVEL, 2048); // 2MB > cap 1MB

    try {
        app(VaultEncryptionService::class)->putFileEncrypted('vault-test', 'x/y.bin', $arquivo);
        $mensagem = null;
    } catch (RuntimeException $e) {
        $mensagem = $e->getMessage();
    }

    expect($mensagem)->not->toBeNull()
        ->and($mensagem)->toContain('cap')
        ->and($mensagem)->not->toContain(L2_NOME_SENSIVEL);
});

it('upload cifrado ilegível: a exceção não cita o nome do arquivo', function () {
    $arquivo = new class(__FILE__, L2_NOME_SENSIVEL, null, null, true) extends UploadedFile
    {
        public function getSize(): int|false
        {
            return 10;
        }

        public function getRealPath(): string|false
        {
            return sys_get_temp_dir() . '/l2-nao-existe-' . bin2hex(random_bytes(6));
        }
    };

    set_error_handler(fn () => true); // o file_get_contents avisa; o que importa é a exceção
    try {
        app(VaultEncryptionService::class)->putFileEncrypted('vault-test', 'x/y.bin', $arquivo);
        $mensagem = null;
    } catch (RuntimeException $e) {
        $mensagem = $e->getMessage();
    } finally {
        restore_error_handler();
    }

    expect($mensagem)->toBe('vault: falha ao ler o arquivo enviado.')
        ->and($mensagem)->not->toContain(L2_NOME_SENSIVEL);
});

function l2Redigir(array $payload): array
{
    $servico = app(ArquivosService::class);

    return (fn (array $p) => $this->redactPayload($p))->call($servico, $payload);
}

it('redator do audit indisponível: payload cru NÃO passa (falha fechado)', function () {
    $quebrado = Mockery::mock(PiiRedactor::class);
    $quebrado->shouldReceive('redactArray')->andThrow(new RuntimeException('redator fora'));
    app()->instance(PiiRedactor::class, $quebrado);

    $saida = l2Redigir(['original_name' => L2_NOME_SENSIVEL, 'size' => 10]);

    expect($saida)->toBe(['redacao_falhou' => true])
        ->and(json_encode($saida))->not->toContain(L2_NOME_SENSIVEL);
});

it('CONTROLE: redator funcionando devolve o payload redigido, não o marcador de falha', function () {
    $ok = Mockery::mock(PiiRedactor::class);
    $ok->shouldReceive('redactArray')->andReturn(['size' => 10]);
    app()->instance(PiiRedactor::class, $ok);

    expect(l2Redigir(['original_name' => L2_NOME_SENSIVEL, 'size' => 10]))->toBe(['size' => 10]);
});

it('mensagem de erro de log sem redator é omitida, não vai crua', function () {
    $quebrado = Mockery::mock(PiiRedactor::class);
    $quebrado->shouldReceive('redact')->andThrow(new RuntimeException('redator fora'));
    app()->instance(PiiRedactor::class, $quebrado);

    $servico = app(ArquivosService::class);
    $saida = (fn (string $m) => $this->redactPiiSafe($m))->call($servico, 'falhou em ' . L2_NOME_SENSIVEL);

    expect($saida)->not->toContain(L2_NOME_SENSIVEL)
        ->and($saida)->not->toBe('');
});
