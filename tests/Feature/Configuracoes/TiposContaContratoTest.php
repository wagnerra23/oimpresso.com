<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tipos de conta (`account_types`) pelo painel da tela Financeiro/ContasBancarias — decisão [W] 2026-10-27.
 * Casos: resources/js/Pages/Financeiro/ContasBancarias/Index.casos.md (UC-CTB-14..17). Tenant 98 × 99 (ADR 0358).
 */

uses(DatabaseTransactions::class);

beforeEach(function () {
    if (! Schema::hasTable('account_types')) {
        $this->markTestSkipped('Schema UltimatePOS ausente (sqlite memory) — rode com DB_CONNECTION=mysql.');
    }

    $this->business = $this->seededTenant();
    $this->outro = $this->seededSupportClientTenant();
    $this->user = $this->usuarioComPermissoes(['account.access'], $this->business);
    $this->actingAs($this->user);
    session(['user.business_id' => $this->business->id, 'user.id' => $this->user->id, 'business.id' => $this->business->id]);
});

function tiposContaNovo(int $businessId, string $nome, ?int $pai = null): int
{
    return DB::table('account_types')->insertGetId([
        'business_id' => $businessId, 'name' => $nome, 'parent_account_type_id' => $pai,
        'created_at' => now(), 'updated_at' => now(),
    ]);
}

test('UC-CTB-14 cria tipo e subtipo na empresa da sessão; pai de outra empresa é recusado', function () {
    $pai = tiposContaNovo($this->business->id, 'Ativo '.uniqid());
    $paiAlheio = tiposContaNovo($this->outro->id, 'Alheio '.uniqid());
    $sub = 'Conta corrente '.uniqid();
    $invasor = 'Invasor '.uniqid();

    $this->post('/account-types', ['name' => $sub, 'parent_account_type_id' => $pai, 'business_id' => $this->outro->id])
        ->assertSessionHasNoErrors()->assertRedirect();
    $this->post('/account-types', ['name' => $invasor, 'parent_account_type_id' => $paiAlheio])
        ->assertSessionHasErrors('parent_account_type_id');

    $criado = DB::table('account_types')->where('name', $sub)->first();
    expect((int) $criado->business_id)->toBe($this->business->id);
    expect((int) $criado->parent_account_type_id)->toBe($pai);
    expect(DB::table('account_types')->where('name', $invasor)->exists())->toBeFalse();
});

test('UC-CTB-15 editar e remover tipo de outra empresa não tem efeito', function () {
    $alheio = tiposContaNovo($this->outro->id, 'Intocado');

    $this->put("/account-types/{$alheio}", ['name' => 'Invadido'])->assertNotFound();
    $this->delete("/account-types/{$alheio}");

    expect(DB::table('account_types')->where('id', $alheio)->value('name'))->toBe('Intocado');
});

test('UC-CTB-16 remover um tipo solta os subtipos dele; tipo não pode ser pai de si mesmo', function () {
    $pai = tiposContaNovo($this->business->id, 'Pai '.uniqid());
    $filho = tiposContaNovo($this->business->id, 'Filho '.uniqid(), $pai);

    $this->put("/account-types/{$pai}", ['name' => 'Pai', 'parent_account_type_id' => $pai])
        ->assertSessionHasErrors('parent_account_type_id');

    $this->delete("/account-types/{$pai}")->assertRedirect();

    expect(DB::table('account_types')->where('id', $pai)->exists())->toBeFalse();
    expect(DB::table('account_types')->where('id', $filho)->value('parent_account_type_id'))->toBeNull();
});

test('UC-CTB-17 os modais Blade de criar e editar tipo saíram', function () {
    // /create agora casa com PUT/DELETE de {account_type}: GET responde 405, não a tela.
    expect($this->get('/account-types/create')->status())->toBeIn([404, 405]);
    $this->get('/account-types/1/edit')->assertNotFound();
});
