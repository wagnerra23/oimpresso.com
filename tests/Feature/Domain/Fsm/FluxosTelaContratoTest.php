<?php

declare(strict_types=1);

// Tests\TestCase já é aplicado globalmente em tests/Pest.php. NÃO redeclarar.

/**
 * Tela Sistema › Fluxos e regras (ADR 0435) — acesso, menu e a aba Modelos.
 *
 * Cobre os UCs de resources/js/Pages/Fluxos/Index.casos.md (G-2: cada UC citado aqui).
 * Os números do encaixe vêm do protótipo (fluxos-data.jsx `encaixe`: porte igual 2, vizinho 1,
 * atividade igual 2), não do controller.
 *
 * Headers Inertia de verdade, incluindo X-Requested-With (§5 2026-09-08). Tenant fictício 98
 * (ADR 0358), transação revertida por caso. NUNCA biz=4.
 *
 * @see app/Http/Controllers/FluxosController.php
 * @see resources/js/Pages/Fluxos/Index.casos.md
 */

use App\User;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

const FLXT_BIZ = 98;

function flxtUsuario(array $permissoes): User
{
    $user = User::factory()->create(['business_id' => FLXT_BIZ, 'username' => 'flxt_' . uniqid()]);
    if ($permissoes !== []) {
        $papel = Role::create(['name' => 'FlxtTeste' . uniqid() . '#' . FLXT_BIZ, 'business_id' => FLXT_BIZ, 'guard_name' => 'web']);
        foreach ($permissoes as $p) {
            Permission::findOrCreate($p, 'web');
        }
        $papel->syncPermissions($permissoes);
        $user->assignRole($papel);
    }
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    return User::findOrFail($user->id);
}

function flxtGet($teste, string $url, ?string $parcial = null)
{
    $manifest = public_path('build-inertia/manifest.json');
    $h = [
        'X-Inertia' => 'true',
        'X-Inertia-Version' => file_exists($manifest) ? md5_file($manifest) : '1',
        'X-Requested-With' => 'XMLHttpRequest',
        'Accept' => 'text/html, application/xhtml+xml',
    ];
    if ($parcial !== null) {
        $h['X-Inertia-Partial-Component'] = 'Fluxos/Index';
        $h['X-Inertia-Partial-Data'] = $parcial;
    }

    return $teste->withHeaders($h)->get($url);
}

/** Procura no shell.menu um item com href /fluxos; devolve o item ou null. */
function flxtItemMenu(array $itens): ?array
{
    foreach ($itens as $item) {
        if (parse_url((string) ($item['href'] ?? ''), PHP_URL_PATH) === '/fluxos') {
            return $item;
        }
        if ($achado = flxtItemMenu($item['children'] ?? [])) {
            return $achado;
        }
    }

    return null;
}

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('Schema UltimatePOS + Spatie exigem MySQL (ADR 0358).');
    }
    if (! DB::table('business')->where('id', FLXT_BIZ)->exists()) {
        $this->markTestSkipped('Tenant fictício 98 ausente nesta lane.');
    }
    DB::beginTransaction();
});

afterEach(function () {
    if (DB::transactionLevel() > 0) {
        DB::rollBack();
    }
});

function flxtEntrar($teste, User $u): void
{
    $teste->actingAs($u);
    session(['user.business_id' => FLXT_BIZ, 'business.id' => FLXT_BIZ]);
}

it('UC-FLXT-01 · com fluxos.ver a tela abre e o menu leva a /fluxos', function () {
    flxtEntrar($this, flxtUsuario(['fluxos.ver']));

    $r = flxtGet($this, '/fluxos');
    $r->assertOk();
    expect($r->json('component'))->toBe('Fluxos/Index');
    expect($r->json('props.aba'))->toBe('modelos');

    $menu = flxtGet($this, '/fluxos', 'shell');
    $menu->assertOk();
    $item = flxtItemMenu($menu->json('props.shell.menu') ?? []);
    expect($item)->not->toBeNull();
    expect((string) ($item['label'] ?? ''))->toBe('Fluxos e regras');

    // O item declara o grupo SISTEMA (Sidebar.tsx findGroupKey dá precedência ao declarado).
    expect((string) ($item['group'] ?? ''))->toBe('sistema');
    expect((bool) ($item['inertia'] ?? false))->toBeTrue();
    $src = file_get_contents(base_path('app/Http/Middleware/AdminSidebarMenu.php'));
    expect($src)->toContain("auth()->user()->can('fluxos.ver')");
    expect($src)->toContain("route('fluxos.index')");
});

it('UC-FLXT-02 · sem fluxos.ver a tela devolve 403', function () {
    flxtEntrar($this, flxtUsuario(['customer.view']));

    expect(flxtGet($this, '/fluxos')->getStatusCode())->toBe(403);
});

it('UC-FLXT-03 · aba Modelos traz os 7 modelos com o encaixe do catálogo', function () {
    flxtEntrar($this, flxtUsuario(['fluxos.ver']));

    $r = flxtGet($this, '/fluxos?aba=modelos');
    $r->assertOk();
    $modelos = $r->json('props.modelos');
    expect($modelos)->toHaveCount(7);
    expect(array_column($modelos, 'id'))->toContain('cv-pequena', 'oficina', 'balcao');

    $cv = collect($modelos)->firstWhere('id', 'cv-pequena');
    expect(array_column($cv['processos'], 'dominio'))->toBe(['venda', 'producao', 'compra']);

    // Pequena · Comunicação visual: cv-pequena tem porte e atividade iguais (2 + 2).
    expect($r->json('props.encaixe.cv-pequena.pequena.cv'))->toBe(['n' => 4, 'rotulo' => 'Recomendado', 'motivo' => 'Porte e atividade batem']);
    // Oficina: porte igual (pequena está na lista), outra atividade → 2 = Parcial.
    expect($r->json('props.encaixe.oficina.pequena.cv'))->toBe(['n' => 2, 'rotulo' => 'Parcial', 'motivo' => 'Porte bate · outra atividade']);
    // Balcão (só micro): porte vizinho 1 + atividade cv 2 = 3 = Serve.
    expect($r->json('props.encaixe.balcao.pequena.cv'))->toBe(['n' => 3, 'rotulo' => 'Serve', 'motivo' => 'Atividade bate · feito para porte vizinho']);

    // Aba desconhecida cai em Modelos.
    expect(flxtGet($this, '/fluxos?aba=xyz')->json('props.aba'))->toBe('modelos');
});

it('UC-FLXT-04 · a migration dá fluxos.ver e fluxos.configurar a quem tem business_settings.access', function () {
    Permission::findOrCreate('business_settings.access', 'web');
    $gerente = Role::create(['name' => 'FlxtGerente' . uniqid() . '#' . FLXT_BIZ, 'business_id' => FLXT_BIZ, 'guard_name' => 'web']);
    $gerente->syncPermissions(['business_settings.access']);
    $vendedor = Role::create(['name' => 'FlxtVendedor' . uniqid() . '#' . FLXT_BIZ, 'business_id' => FLXT_BIZ, 'guard_name' => 'web']);
    $vendedor->syncPermissions([Permission::findOrCreate('customer.view', 'web')]);

    $migration = require base_path('database/migrations/2026_10_16_150000_add_fluxos_permissions.php');
    $migration->up();
    $migration->up(); // idempotente
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $gerente = Role::findById($gerente->id, 'web');
    expect($gerente->hasPermissionTo('fluxos.ver'))->toBeTrue();
    expect($gerente->hasPermissionTo('fluxos.configurar'))->toBeTrue();
    expect(Role::findById($vendedor->id, 'web')->hasPermissionTo('fluxos.ver'))->toBeFalse();

    $u = User::factory()->create(['business_id' => FLXT_BIZ, 'username' => 'flxt_g_' . uniqid()]);
    $u->assignRole($gerente);
    flxtEntrar($this, User::findOrFail($u->id));
    expect(flxtGet($this, '/fluxos')->json('props.pode_configurar'))->toBeTrue();
});

function flxtPostAplicar($teste, array $dados)
{
    return $teste->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'X-Inertia' => 'true'])
        ->post('/fluxos/modelo', $dados);
}

it('UC-FLXT-05 · quem configura usa um modelo e só a empresa da sessão passa a tê-lo', function () {
    if (! \Illuminate\Support\Facades\Schema::hasTable('fsm_modelos_empresa')) {
        $this->markTestSkipped('Tabela fsm_modelos_empresa ausente (migration do #9492).');
    }
    $outra = (int) DB::table('business')->where('id', '!=', FLXT_BIZ)->value('id');
    $antesOutra = DB::table('sale_processes')->where('business_id', $outra)->where('key', 'like', 'flx_cv_pequena_%')->count();

    flxtEntrar($this, flxtUsuario(['fluxos.ver', 'fluxos.configurar']));
    $r = flxtPostAplicar($this, ['modelo' => 'cv-pequena', 'porte' => 'pequena', 'atividade' => 'cv', 'business_id' => $outra]);

    $r->assertRedirect('/fluxos?aba=empresa');
    expect(session('status.msg'))->toContain('Documentos em andamento continuam na etapa em que estavam');
    $linha = DB::table('fsm_modelos_empresa')->where('business_id', FLXT_BIZ)->first();
    expect($linha->modelo)->toBe('cv-pequena');
    expect([$linha->porte, $linha->atividade])->toBe(['pequena', 'cv']);
    expect(DB::table('sale_processes')->where('business_id', FLXT_BIZ)->where('key', 'like', 'flx_cv_pequena_%')->pluck('dominio')->sort()->values()->all())
        ->toBe(['compra', 'producao', 'venda']);
    // O business_id do formulário é ignorado: a outra empresa não ganhou nada.
    expect(DB::table('sale_processes')->where('business_id', $outra)->where('key', 'like', 'flx_cv_pequena_%')->count())->toBe($antesOutra);
});

it('UC-FLXT-06 · sem fluxos.configurar o POST devolve 403 e nada é gravado', function () {
    if (! \Illuminate\Support\Facades\Schema::hasTable('fsm_modelos_empresa')) {
        $this->markTestSkipped('Tabela fsm_modelos_empresa ausente (migration do #9492).');
    }
    $antes = DB::table('fsm_modelos_empresa')->where('business_id', FLXT_BIZ)->value('modelo');

    flxtEntrar($this, flxtUsuario(['fluxos.ver']));
    expect(flxtGet($this, '/fluxos')->json('props.pode_configurar'))->toBeFalse();
    expect(flxtPostAplicar($this, ['modelo' => 'oficina'])->getStatusCode())->toBe(403);

    expect(DB::table('fsm_modelos_empresa')->where('business_id', FLXT_BIZ)->value('modelo'))->toBe($antes);
    expect(DB::table('sale_processes')->where('business_id', FLXT_BIZ)->where('key', 'like', 'flx_oficina_%')->exists())->toBeFalse();
});

it('UC-FLXT-07 · a aba Fluxos da empresa traz o modelo da própria empresa', function () {
    if (! \Illuminate\Support\Facades\Schema::hasTable('fsm_modelos_empresa')) {
        $this->markTestSkipped('Tabela fsm_modelos_empresa ausente (migration do #9492).');
    }
    $outra = (int) DB::table('business')->where('id', '!=', FLXT_BIZ)->value('id');
    DB::table('fsm_modelos_empresa')->updateOrInsert(['business_id' => FLXT_BIZ], ['modelo' => 'grafica', 'porte' => 'media', 'atividade' => 'offset', 'aplicado_em' => now()]);
    DB::table('fsm_modelos_empresa')->updateOrInsert(['business_id' => $outra], ['modelo' => 'oficina', 'porte' => 'micro', 'atividade' => 'oficina', 'aplicado_em' => now()]);

    flxtEntrar($this, flxtUsuario(['fluxos.ver']));
    $empresa = flxtGet($this, '/fluxos?aba=empresa')->json('props.empresa');

    expect($empresa['modelo'])->toBe('grafica');
    expect([$empresa['porte'], $empresa['atividade']])->toBe(['media', 'offset']);
});
