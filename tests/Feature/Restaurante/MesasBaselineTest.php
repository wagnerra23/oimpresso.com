<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\EstoqueFixture;

/**
 * Thread restaurante/playbook/01, F2 — o que a Blade de Mesas (/modules/tables) lista e mostra HOJE, antes da tela
 * React. Trava a DataTable do TableController::index: só mesas do negócio, 403 sem access_tables e botões de editar e
 * excluir só para o papel Admin#<negócio> (o `@role` da coluna action). Tenant 98 × 99 (ADR 0358).
 *
 * @see memory/requisitos/Restaurante/RUNBOOK-mesas.md
 */

uses(DatabaseTransactions::class);

beforeEach(function () {
    if (! Schema::hasTable('res_tables') || ! Schema::hasColumn('transactions', 'essentials_duration')) {
        $this->markTestSkipped('Schema UltimatePOS ausente (sqlite memory) — rode com DB_CONNECTION=mysql.');
    }

    $this->business = $this->seededTenant();
    $this->local = EstoqueFixture::locationId($this->business->id, '-MESA-'.uniqid());
});

function mesasBaselineEntrar($teste, \App\User $user): void
{
    $teste->actingAs($user);
    session(['user.business_id' => $teste->business->id, 'user.id' => $user->id, 'business.id' => $teste->business->id]);
}

function mesasBaselineMesa(int $businessId, int $local, int $criador, string $nome): int
{
    return DB::table('res_tables')->insertGetId([
        'business_id' => $businessId, 'location_id' => $local, 'name' => $nome, 'description' => 'Desc '.$nome,
        'created_by' => $criador, 'created_at' => now(), 'updated_at' => now(),
    ]);
}

/** O JSON que a DataTable da Blade pede (sem X-Inertia). */
function mesasBaselineDataTable($teste): array
{
    $r = $teste->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
        ->get('/modules/tables?'.http_build_query(['draw' => 1, 'start' => 0, 'length' => -1]));
    $r->assertOk();

    return $r->json('data');
}

test('a DataTable da Blade lista só as mesas do negócio da sessão', function () {
    $user = $this->usuarioComPermissoes(['access_tables'], $this->business);
    mesasBaselineEntrar($this, $user);
    $outro = $this->seededSupportClientTenant();
    $localOutro = EstoqueFixture::locationId($outro->id, '-MESA-'.uniqid());
    $minha = 'Minha '.uniqid();
    $alheia = 'Alheia '.uniqid();
    mesasBaselineMesa($this->business->id, $this->local, $user->id, $minha);
    mesasBaselineMesa($outro->id, $localOutro, $user->id, $alheia);

    $nomes = collect(mesasBaselineDataTable($this))->pluck('name');

    expect($nomes)->toContain($minha);
    expect($nomes)->not->toContain($alheia);
});

test('sem access_tables a rota devolve 403', function () {
    mesasBaselineEntrar($this, $this->usuarioComPermissoes([], $this->business));

    $this->get('/modules/tables')->assertForbidden();
});

test('editar e excluir só aparecem na DataTable para o papel Admin#<negócio>', function () {
    $comum = $this->usuarioComPermissoes(['access_tables'], $this->business);
    $nome = 'Botoes '.uniqid();
    mesasBaselineMesa($this->business->id, $this->local, $comum->id, $nome);

    mesasBaselineEntrar($this, $comum);
    $linha = collect(mesasBaselineDataTable($this))->firstWhere('name', $nome);
    expect($linha)->not->toBeNull();
    expect((string) $linha['action'])->not->toContain('edit_table_button');

    $admin = $this->usuarioComPermissoes(['access_tables'], $this->business);
    $papel = \Spatie\Permission\Models\Role::firstOrCreate(
        ['name' => 'Admin#'.$this->business->id, 'guard_name' => 'web'],
        ['business_id' => $this->business->id],
    );
    $admin->assignRole($papel);
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    mesasBaselineEntrar($this, \App\User::findOrFail($admin->id));
    $linha = collect(mesasBaselineDataTable($this))->firstWhere('name', $nome);
    expect((string) $linha['action'])->toContain('edit_table_button');
    expect((string) $linha['action'])->toContain('delete_table_button');
});
