<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(Tests\TestCase::class);

// @covers-us US-SUPER-009

/**
 * Contrato da vitrine pública `/pricing`. UCs de Modules/Superadmin/Resources/js/Pages/Site/Pricing.casos.md
 * (US-CMS-001 + US-SUPER-009 + charter + RUNBOOK-pacotes). Pacotes fictícios, preço neutro sintético
 * (Tier 0). Escrita em transação revertida (§5 2026-09-18). SKIP em SQLite: leia assertions (LC-13).
 */
beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite' || ! Schema::hasTable('packages')) {
        $this->markTestSkipped('Requer o schema MySQL de packages.');
    }
    DB::beginTransaction();
});

afterEach(fn () => DB::rollBack());

function sprHeaders(): array
{
    $versao = (string) app(\App\Http\Middleware\HandleInertiaRequests::class)->version(request());

    return ['X-Inertia' => 'true', 'X-Inertia-Version' => $versao, 'X-Requested-With' => 'XMLHttpRequest'];
}

function sprPacote(string $nome, int $ativo, int $privado): int
{
    return (int) DB::table('packages')->insertGetId([
        'name' => $nome, 'description' => 'Só para o contrato da vitrine de planos.',
        'location_count' => 1, 'user_count' => 1, 'product_count' => 0, 'invoice_count' => 0,
        'interval' => 'months', 'interval_count' => 1, 'trial_days' => 0, 'price' => 1,
        'custom_permissions' => json_encode([]), 'created_by' => 1, 'sort_order' => 950,
        'is_active' => $ativo, 'is_private' => $privado, 'is_one_time' => 0,
        'created_at' => now(), 'updated_at' => now(),
    ]);
}

it('UC-SPRIC-01 · /pricing abre sem login, com planos e permissões', function () {
    $this->assertGuest();
    $r = $this->withHeaders(sprHeaders())->get('/pricing');

    $r->assertOk();
    expect($r->json('component'))->toBe('Site/Pricing');
    expect($r->json('props.packages'))->toBeArray();
    expect(array_key_exists('permissions', (array) $r->json('props')))->toBeTrue();
});

it('UC-SPRIC-02 · pacote privado ou desativado não aparece na vitrine', function () {
    $publico = sprPacote('Pacote fictício público SPRIC', 1, 0);
    $privado = sprPacote('Pacote fictício privado SPRIC', 1, 1);
    $inativo = sprPacote('Pacote fictício inativo SPRIC', 0, 0);

    $ids = collect($this->withHeaders(sprHeaders())->get('/pricing')->json('props.packages'))
        ->pluck('id')->map(fn ($id) => (int) $id)->all();

    expect($ids)->toContain($publico);
    expect(in_array($privado, $ids, true))->toBeFalse();
    expect(in_array($inativo, $ids, true))->toBeFalse();
});
