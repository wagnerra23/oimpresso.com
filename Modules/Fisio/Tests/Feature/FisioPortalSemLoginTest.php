<?php

declare(strict_types=1);
// @covers-us US-FISIO-007
// Cobre UC-FPP-01 e UC-FPP-07 (Fisio/Public/Portal.casos.md) pelo caminho REAL do pacote.

use App\Utils\ModuleUtil;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Modules\Fisio\Tests\Support\FisioFixtures as F;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * O portal do paciente é rota pública: não há usuário logado. Os outros testes do portal
 * trocam `hasThePermissionInSubscription` por mock (F::pacote) e por isso nunca passaram
 * pelo corpo real dele, que perguntava `auth()->user()->can('superadmin')` — em produção,
 * 2026-10-19, todo link VÁLIDO dava 500 ("Call to a member function can() on null").
 *
 * Aqui só `isSuperadminInstalled` é fixado em true, para o corpo real rodar em qualquer
 * ambiente. Tenant 98 sem assinatura ativa: o pacote conta como desligado e o portal mostra
 * o estado vazio (UC-FPP-07), com 200 e sem dado clínico. ⚠️ SKIP em SQLite (LC-13).
 */
beforeEach(function () {
    if ($motivo = F::motivoDeSkip()) {
        $this->markTestSkipped($motivo);
    }
    F::configurar();
    Carbon::setTestNow('2026-10-14 09:00:00');
    $this->b98 = (int) $this->seededTenant()->id;
    $u98 = $this->usuarioComPermissoes([F::PERM_ACESSO, F::PERM_EDITAR], $this->seededTenant());
    $this->p = F::paciente($this->b98, $u98->id);
    F::programa($this->p, [F::exercicio(null, 'Ponte')], $u98->id);

    $util = \Mockery::mock(ModuleUtil::class)->makePartial();
    $util->shouldReceive('isSuperadminInstalled')->andReturn(true);
    app()->instance(ModuleUtil::class, $util);
});

afterEach(fn () => Carbon::setTestNow());

it('UC-FPP-07 · link válido sem login responde 200 com o estado vazio, nunca 500', function () {
    $g = F::emitirPortal($this->p);

    expect(auth()->user())->toBeNull();

    $props = F::portalProps($this, $g['token']);
    expect($props['erro'])->toBe('link_invalido')
        ->and($props)->not->toHaveKey('hoje')
        ->and($props)->not->toHaveKey('paciente');
});

it('o pacote de uma empresa sem assinatura é falso para visitante sem login, sem exceção', function () {
    expect(auth()->user())->toBeNull()
        ->and(app(ModuleUtil::class)->hasThePermissionInSubscription($this->b98, F::PACOTE, 'superadmin_package'))
        ->toBeFalsy();
});
