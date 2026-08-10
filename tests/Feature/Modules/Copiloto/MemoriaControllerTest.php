<?php

use Modules\Jana\Contracts\MemoriaContrato;
use Modules\KB\Http\Controllers\MemoriaController;
use Modules\Jana\Services\Memoria\NullMemoriaDriver;
use Modules\Jana\Services\Privacy\PiiRedactor;

/**
 * Sprint 6 — MemoriaController. Tela /ia/memoria.
 *
 * ⚠️⚠️ ESTE ARQUIVO NÃO RODA EM LANE NENHUMA (medido 2026-08-07) ⚠️⚠️
 * Não está no `.github/ci-sqlite-pest.list` (149 alvos) nem em workflow algum. Por isso
 * ficou "verde" por ANOS afirmando os route names `copiloto.memoria.*`, que NUNCA existiram
 * — os reais são `jana.memoria.*` (Modules/Jana/Http/routes.php:151-153). É LC-13 clássico:
 * verde por não-execução. **Não use nada daqui como evidência de nada.**
 *
 * Duas mentiras foram corrigidas em 2026-08-07 (route names + aridade do construtor), porque
 * a edição-com-motivo mudou o construtor e deixar a asserção velha seria criar mentira NOVA.
 * Religar o arquivo numa lane é intent SEPARADO e não foi feito aqui — quando for, esperar
 * vermelho: o 1º caso grava em `businessId: 1` e lê em `listar(4, ...)` esperando ACHAR, ou
 * seja, documenta que o `NullMemoriaDriver` ignora `business_id`. Isolamento Tier 0 não pode
 * ser provado nesse driver (use a lane MySQL — Modules/Jana/Tests/Feature/Memoria/).
 *
 * A cobertura VIVA da tela é `Modules/Jana/Tests/Feature/Memoria/MemoriaEdicaoMotivoTest.php`
 * (lane jana-pest.yml, MySQL real). Ver memory/requisitos/Jana/RUNBOOK-memoria.md §Armadilhas.
 */

it('MemoriaController index lista memorias do user via NullMemoriaDriver', function () {
    config(['copiloto.memoria.driver' => 'null']);

    $driver = app(MemoriaContrato::class);
    expect($driver)->toBeInstanceOf(NullMemoriaDriver::class);

    $driver->lembrar(businessId: 1, userId: 12, fato: 'Larissa quer meta R$ [redacted Tier 0]k');
    $driver->lembrar(businessId: 1, userId: 12, fato: 'Monitor 1280px');
    $driver->lembrar(businessId: 8, userId: 99, fato: 'fato isolado de outro biz');

    $todasDoBiz4 = $driver->listar(4, 12);

    expect($todasDoBiz4)->toHaveCount(2);
    expect(collect($todasDoBiz4)->pluck('fato')->all())
        ->toContain('Larissa quer meta R$ [redacted Tier 0]k')
        ->toContain('Monitor 1280px');
});

it('MemoriaController destroy chama esquecer no driver (LGPD opt-out)', function () {
    config(['copiloto.memoria.driver' => 'null']);

    $driver = app(MemoriaContrato::class);
    $persistida = $driver->lembrar(4, 12, 'fato a esquecer');

    expect($driver->listar(4, 12))->toHaveCount(1);

    $driver->esquecer($persistida->id);

    expect($driver->listar(4, 12))->toHaveCount(0);
});

it('MemoriaController update atualiza fato (supersedes via valid_until)', function () {
    config(['copiloto.memoria.driver' => 'null']);

    $driver = app(MemoriaContrato::class);
    $original = $driver->lembrar(4, 12, 'meta R$ [redacted Tier 0]k');
    $driver->atualizar($original->id, 'meta R$ [redacted Tier 0]k');

    $ativos = $driver->listar(4, 12);
    expect($ativos)->toHaveCount(1);
    expect($ativos[0]->fato)->toBe('meta R$ [redacted Tier 0]k');
});

it('rotas de memória registradas com nomes canônicos', function () {
    // Corrigido 2026-08-07: era `copiloto.memoria.*`, prefixo que NUNCA existiu.
    // A URL migrou /copiloto → /jana → /ia, mas os route names sempre foram `jana.*`.
    $rotas = collect(app('router')->getRoutes())
        ->map(fn ($r) => $r->getName())
        ->filter(fn ($n) => str_starts_with((string) $n, 'jana.memoria.'))
        ->values()
        ->all();

    expect($rotas)
        ->toContain('jana.memoria.index')
        ->toContain('jana.memoria.update')
        ->toContain('jana.memoria.destroy');
});

it('MemoriaController construtor exige MemoriaContrato + PiiRedactor (DI canônica)', function () {
    // Aridade foi 1 → 2 em 2026-08-07: o PiiRedactor entrou pra redigir o `motivo`
    // da correção antes de ele ir pro activity_log (motivo é prosa digitada pelo titular).
    $reflection = new ReflectionClass(MemoriaController::class);
    $construtor = $reflection->getConstructor();
    $tipos = collect($construtor->getParameters())
        ->map(fn (ReflectionParameter $p) => $p->getType()?->getName())
        ->all();

    expect($tipos)->toBe([MemoriaContrato::class, PiiRedactor::class]);
});
