<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Modules\Copiloto\Services\MemoriaAutonoma\SinteseSemanalService;

uses(Tests\TestCase::class)->in(__DIR__);

/**
 * Fase 1 MemoriaAutonoma — golden tests do comando copiloto:sintese-semanal.
 *
 * Testa coleta de contexto + idempotência + dry-run + range ISO.
 * NÃO chama LLM real (--dry-run sempre).
 *
 * Ver ADR MemoriaAutonoma/adr/arq/0001-fase-1-sintese-semanal.md.
 */

it('resolve range ISO YYYY-Www corretamente', function () {
    $svc = app(SinteseSemanalService::class);
    [$ini, $fim] = $svc->resolverRangeIso('2026-W18');

    expect($ini->dayOfWeek)->toBe(1) // segunda
        ->and($fim->dayOfWeek)->toBe(0) // domingo
        ->and($ini->isoWeek)->toBe(18)
        ->and($ini->isoWeekYear)->toBe(2026);
});

it('rejeita formato de semana invalido', function () {
    $svc = app(SinteseSemanalService::class);
    expect(fn () => $svc->resolverRangeIso('invalido'))
        ->toThrow(\RuntimeException::class, 'Semana inválida');
});

it('coleta contexto da semana sem chamar LLM em dry-run', function () {
    $svc = app(SinteseSemanalService::class);
    $resultado = $svc->gerar('2026-W18', dryRun: true);

    expect($resultado['path'])->toBeNull()
        ->and($resultado['sintese'])->toBeNull()
        ->and($resultado['contexto'])->toBeString()
        ->and($resultado['contexto'])->toContain('== COMMITS')
        ->and($resultado['contexto'])->toContain('== ARQUIVOS NOVOS EM memory/')
        ->and($resultado['custo_estimado'])->toHaveKeys(['input_tokens', 'output_tokens', 'usd', 'brl_aprox']);
});

it('estima custo Haiku em USD e BRL', function () {
    $svc = app(SinteseSemanalService::class);
    $custo = $svc->estimarCusto('a' . str_repeat('x', 4000)); // ~1k tokens

    expect($custo['input_tokens'])->toBeGreaterThan(900)
        ->and($custo['input_tokens'])->toBeLessThan(1100)
        ->and($custo['usd'])->toBeFloat()
        ->and($custo['usd'])->toBeLessThan(0.01) // 1k tokens é barato
        ->and($custo['brl_aprox'])->toBeFloat();
});

it('comando dry-run nao cria arquivo', function () {
    $semana = '2099-W01'; // futuro distante, sem commits
    $arquivo = base_path("memory/sessions/SEMANA-{$semana}-resumo.md");

    if (file_exists($arquivo)) unlink($arquivo);

    $exitCode = \Artisan::call('copiloto:sintese-semanal', [
        '--week' => $semana,
        '--dry-run' => true,
    ]);

    expect($exitCode)->toBe(0)
        ->and(file_exists($arquivo))->toBeFalse();
});

it('aborta se arquivo ja existe sem --force', function () {
    $semana = '2099-W02';
    $arquivo = base_path("memory/sessions/SEMANA-{$semana}-resumo.md");
    File::ensureDirectoryExists(dirname($arquivo));
    file_put_contents($arquivo, "# placeholder\n");

    $svc = app(SinteseSemanalService::class);

    expect(fn () => $svc->gerar($semana, dryRun: false, force: false))
        ->toThrow(\RuntimeException::class, 'já existe');

    unlink($arquivo);
});
