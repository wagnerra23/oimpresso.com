<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Modules\NfeBrasil\Models\NfeEmissao;

uses(Tests\TestCase::class);

/**
 * Contrato da tela `Fiscal/Sped` (US-FISCAL-010 panorama + US-FISCAL-016/017 gerador).
 *
 * ⚠️ GUARD REVOGADO 2026-07-27 — o caso `Controller é placeholder — sem gerador SPED
 * real ainda` foi REMOVIDO. Ele assertava `! method_exists('exportSped')` e
 * `! method_exists('gerarEFD')`, e ficava VERDE apenas porque o gerador real nasceu
 * com OUTRO nome: `SpedController::gerar` (rota `fiscal.sped.icms-ipi`, entregue em
 * US-FISCAL-016 PR #8 / US-FISCAL-017 PR #9). Ou seja: o nome do teste afirmava
 * "não existe gerador" enquanto o gerador estava em produção atrás da feature-flag
 * `fiscal.sped_simples_only_lock` — verde acoplado a NOME, não a comportamento.
 * O Non-Goal de charter que ele defendia ("❌ Gerador SPED real") foi revogado no
 * mesmo PR (precedência: teste verde > casos > charter > SPEC · proibicoes.md).
 *
 * No lugar dele entra o contrato VIVO: o Controller expõe `gerar` e a rota de
 * download está registrada. Roda sempre (reflection + Route, zero hit DB).
 *
 * O comportamento de runtime do download (403 sem permissão · 503 com flag ligada ·
 * bypass superadmin · flag off libera) é defendido por `SimplesOnlyGateTest`, e o
 * default da flag por `SimplesOnlyGateConfigTest`.
 *
 * @see memory/requisitos/Fiscal/SPEC.md US-FISCAL-010 / 016 / 017
 * @see resources/js/Pages/Fiscal/Sped.casos.md
 */

it('UC-FSPED-03 · SpedController expõe gerar() e a rota de download está registrada', function () {
    $controller = new \Modules\Fiscal\Http\Controllers\SpedController();

    expect(method_exists($controller, 'index'))->toBeTrue('panorama dos 5 meses (US-FISCAL-010)')
        ->and(method_exists($controller, 'gerar'))->toBeTrue(
            'gerador EFD-ICMS/IPI entregue em US-FISCAL-016 — NÃO é mais placeholder'
        );

    expect(Route::has('fiscal.sped.icms-ipi'))->toBeTrue(
        'rota de download do TXT EFD registrada (GET /fiscal/sped/icms-ipi/{ano}/{mes})'
    );
});

it('UC-FSPED-01 · agregação de períodos NfeEmissao respeita scope per business', function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: NfeEmissao requer schema MySQL (ADR 0101)');
    }
    if (! Schema::hasTable('nfe_emissoes')) {
        $this->markTestSkipped('nfe_emissoes table missing');
    }

    session(['business.id' => 1, 'user.business_id' => 1]);

    $crossTenantCount = NfeEmissao::query()
        ->where('business_id', '!=', 1)
        ->count();

    expect($crossTenantCount)->toBe(0, 'Agregação SPED scoped — nunca vaza outros businesses');
});
