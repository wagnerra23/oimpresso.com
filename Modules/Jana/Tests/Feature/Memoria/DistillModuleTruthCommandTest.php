<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Laravel\Ai\Ai;
use Laravel\Ai\AnonymousAgent;

uses(Tests\TestCase::class);

/**
 * PR-C2 do keystone distiller-módulo-verdade (ADR 0291 D-C · peça 2).
 *
 * Testa o comando jana:distill-module-truth SEM git e SEM prod: dirs via config
 * (jana.requisitos_dir/sessions_dir/handoffs_dir), Process::fake (PRs vazios),
 * Ai::fakeAgent (LLM determinístico), tempo congelado (janela de 30d determinística).
 */

beforeEach(function () {
    Carbon::setTestNow('2026-06-19 12:00:00');

    $base = sys_get_temp_dir() . '/distill_cmd_' . uniqid();
    test()->base = $base;
    File::makeDirectory($base . '/requisitos/Financeiro', 0o755, recursive: true);
    File::makeDirectory($base . '/sessions', 0o755, recursive: true);
    File::makeDirectory($base . '/handoffs', 0o755, recursive: true);
    config([
        'jana.requisitos_dir' => $base . '/requisitos',
        'jana.sessions_dir' => $base . '/sessions',
        'jana.handoffs_dir' => $base . '/handoffs',
    ]);

    Process::fake(); // git log → fake vazio (PRs não entram; FS é a fonte testada)
});

afterEach(function () {
    Carbon::setTestNow();
    if (isset(test()->base) && File::isDirectory(test()->base)) {
        File::deleteDirectory(test()->base);
    }
});

function seedSession(string $name, string $body): void
{
    File::put(test()->base . '/sessions/' . $name, $body);
}

function briefingPath(): string
{
    return test()->base . '/requisitos/Financeiro/BRIEFING.md';
}

test('--module escreve a porta a partir de session que cita o módulo', function () {
    seedSession('2026-06-15-bridge.md', "# Sessão\nMexi em Modules/Financeiro hoje.");
    Ai::fakeAgent(AnonymousAgent::class, ["## Estado atual\nBridge Sells→fin_titulos fechando."]);

    $this->artisan('jana:distill-module-truth', ['--module' => 'Financeiro'])->assertExitCode(0);

    expect(File::exists(briefingPath()))->toBeTrue();
    expect(File::get(briefingPath()))
        ->toContain('distilled_at:')
        ->toContain('Bridge Sells→fin_titulos fechando')
        ->toContain('2026-06-15-bridge.md');
});

test('--dry-run calcula mas não escreve', function () {
    seedSession('2026-06-15-bridge.md', 'Modules/Financeiro');
    Ai::fakeAgent(AnonymousAgent::class, ["## Estado atual\nok"]);

    $this->artisan('jana:distill-module-truth', ['--module' => 'Financeiro', '--dry-run' => true])
        ->assertExitCode(0);

    expect(File::exists(briefingPath()))->toBeFalse();
});

test('módulo sem eventos recentes → não escreve (não chama LLM)', function () {
    seedSession('2026-06-15-outro.md', 'Modules/Crm apenas');
    Ai::fakeAgent(AnonymousAgent::class, ['NUNCA DEVERIA SER CHAMADO']);

    $this->artisan('jana:distill-module-truth', ['--module' => 'Financeiro'])->assertExitCode(0);

    expect(File::exists(briefingPath()))->toBeFalse();
});

test('sem --module nem --all → falha com instrução', function () {
    $this->artisan('jana:distill-module-truth')->assertExitCode(1);
});

test('--all resolve módulos que já têm porta e a refresca (sobrescreve)', function () {
    File::put(briefingPath(), "---\ndistilled_at: \"2026-05-01\"\n---\nCONTEUDO VELHO");
    seedSession('2026-06-15-bridge.md', 'update em Modules/Financeiro');
    Ai::fakeAgent(AnonymousAgent::class, ["## Estado atual\nconteudo refrescado"]);

    $this->artisan('jana:distill-module-truth', ['--all' => true])->assertExitCode(0);

    expect(File::get(briefingPath()))
        ->toContain('conteudo refrescado')
        ->not->toContain('CONTEUDO VELHO');
});
