<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\NfeBrasil\Models\NfeEmissao;

uses(Tests\TestCase::class);

/**
 * PR #3 Wave SPED Fiscal — placeholder.
 */

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: NfeEmissao requer schema MySQL (ADR 0101)');
    }
    if (! Schema::hasTable('nfe_emissoes')) {
        $this->markTestSkipped('nfe_emissoes table missing');
    }
});

it('agregação de períodos NfeEmissao respeita scope per business', function () {
    session(['business.id' => 1, 'user.business_id' => 1]);

    $crossTenantCount = NfeEmissao::query()
        ->where('business_id', '!=', 1)
        ->count();

    expect($crossTenantCount)->toBe(0, 'Agregação SPED scoped — nunca vaza outros businesses');
});

it('Controller é placeholder — sem gerador SPED real ainda', function () {
    // Defensa: garante que a classe NÃO tem método "exportSped" implementado
    // (seria gerador real — não pode existir até PR dedicado).
    $controller = new \Modules\Fiscal\Http\Controllers\SpedController();

    expect(method_exists($controller, 'exportSped'))->toBeFalse(
        'Gerador SPED real só em PR dedicado — anti-hook charter'
    );
    expect(method_exists($controller, 'gerarEFD'))->toBeFalse();
});
