<?php

declare(strict_types=1);

// Tests\TestCase já é aplicado globalmente em tests/Pest.php (uses(TestCase::class)->in('Feature')). NÃO redeclarar aqui — Pest 4 lança TestCaseAlreadyInUse.

/**
 * O catálogo fechado de permissões, medido pelo COMPORTAMENTO — não pela forma da resposta.
 *
 * D1 (segurança): __createPermissionIfNotExists() criava QUALQUER nome vindo do POST, e a tabela
 * `permissions` do Spatie é GLOBAL (não tem business_id). Um POST forjado poluía o catálogo de
 * todos os tenants, sem sinal em lugar nenhum da UI. O assert é a CONTAGEM da tabela — o que
 * importa é que a linha não nasce, não o status HTTP (estas rotas respondem com redirect, não JSON).
 *
 * D2 (perda silenciosa): medido nas views em 2026-08-19 — grupo de preço é `spg_permissions[]`
 * (checkbox gerado em loop, valor 'selling_price_group.<id>'), e o store() NUNCA lia essa chave;
 * lia `radio_option` duas vezes. Ao CRIAR um papel, o grupo de preço marcado era perdido e os
 * radios entravam duplicados. O update() sempre leu as duas chaves certas.
 *
 * Nomes de helper diferentes dos de RoleTenantIsolationTest de propósito: os dois arquivos rodam
 * na MESMA lane, e função global repetida quebra com "cannot redeclare".
 */

use App\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

function usuarioCat(array $permissoes, int $businessId = 1): User
{
    $user = User::factory()->create(['business_id' => $businessId]);

    $papel = Role::create([
        'name' => 'CatTeste'.uniqid().'#'.$businessId,
        'business_id' => $businessId,
        'guard_name' => 'web',
    ]);

    foreach ($permissoes as $p) {
        Permission::findOrCreate($p, 'web');
    }
    $papel->syncPermissions($permissoes);
    $user->assignRole($papel);

    // Mesma correcao do RoleTenantIsolationTest (ja em main): sem limpar o cache de
    // permissoes do Spatie e sem reler o usuario, o can() responde com o retrato anterior
    // e o controller aborta 403 — e ai os casos NEGATIVOS passam pelo MOTIVO ERRADO,
    // porque 'nada foi criado' tambem e verdade quando a requisicao nem chega.
    app(\\Spatie\\Permission\\PermissionRegistrar::class)->forgetCachedPermissions();

    return User::findOrFail($user->id);
}

it('D1: permissão fora do catálogo não vira linha na tabela global', function () {
    $user = usuarioCat(['roles.create']);

    $this->actingAs($user);
    session(['user.business_id' => 1]);

    $antes = Permission::count();   // contado DEPOIS do setup, senão mediria o próprio setup

    $this->post('/roles', [
        'name' => 'Forjado'.uniqid(),
        'permissions' => ['isto.nao.existe.no.catalogo'],
    ]);

    expect(Permission::count())->toBe($antes);
    expect(Permission::where('name', 'isto.nao.existe.no.catalogo')->exists())->toBeFalse();
});

it('D1-positivo: permissão legítima do catálogo continua sendo gravada', function () {
    // Sem este caso, um filtro largo demais passaria no D1 e o teste diria "nada é salvo",
    // chamando isso de segurança.
    $user = usuarioCat(['roles.create']);

    $this->actingAs($user);
    session(['user.business_id' => 1]);

    $nome = 'Legitimo'.uniqid();
    $this->post('/roles', ['name' => $nome, 'permissions' => ['sell.view']]);

    $papel = Role::where('name', $nome.'#1')->first();
    expect($papel)->not->toBeNull();
    expect($papel->permissions->pluck('name')->all())->toContain('sell.view');
});

it('D2: grupo de preço marcado no CREATE não se perde (vinha em spg_permissions, que o store ignorava)', function () {
    $user = usuarioCat(['roles.create']);

    $this->actingAs($user);
    session(['user.business_id' => 1]);

    $nome = 'Atacado'.uniqid();
    $this->post('/roles', [
        'name' => $nome,
        'permissions' => [],
        'spg_permissions' => ['selling_price_group.1'],
    ]);

    $papel = Role::where('name', $nome.'#1')->firstOrFail();
    $doGrupo = $papel->permissions->pluck('name')
        ->filter(fn ($n) => str_starts_with($n, 'selling_price_group.'))
        ->values();

    expect($doGrupo->all())->toBe(['selling_price_group.1']);
});

it('D2: opção exclusiva de radio entra UMA vez, não duplicada', function () {
    $user = usuarioCat(['roles.create']);

    $this->actingAs($user);
    session(['user.business_id' => 1]);

    $nome = 'Escopo'.uniqid();
    $this->post('/roles', [
        'name' => $nome,
        'permissions' => [],
        'radio_option' => ['customer_view' => 'customer.view'],
    ]);

    $papel = Role::where('name', $nome.'#1')->firstOrFail();
    $doRadio = $papel->permissions->pluck('name')->filter(fn ($n) => $n === 'customer.view')->values();

    expect($doRadio->count())->toBe(1);
});
