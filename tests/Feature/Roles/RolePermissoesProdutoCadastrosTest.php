<?php

declare(strict_types=1);

// Tests\TestCase ja e aplicado globalmente em tests/Pest.php (uses(TestCase::class)->in('Feature')).

use App\Business;
use App\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * As 9 permissoes de Variacoes, Garantias e Etiquetas (PR #8349) precisam ser CONCEDIVEIS pelo
 * editor de papeis — senao papel criado depois do deploy nasce sem elas e o admin nao tem como
 * dar.
 *
 * O vetor nao era so a tela: `RoleController::__somenteDoCatalogo()` DESCARTA no save tudo o
 * que `PermissionCatalog::CORE` nao lista. Antes deste PR, marcar `variation.view` num POST
 * forjado ia pro log como intrusa e nao chegava ao papel. Por isso os casos de persistencia
 * sao HTTP real + estado no banco, nao leitura de lista.
 *
 * Render: o formulario e Blade com layout completo (sessao de business, enabled_modules, etc.);
 * o oraculo de "o formulario oferece" e o mesmo que o `PermissionCatalogSyncTest` usa — os
 * checkbox `permissions[]` extraidos das duas views —, mais a marcacao no edit (in_array).
 *
 * Tenant 98 (ADR 0358) — NUNCA biz=4. Pest e CI/CT 100 only.
 */
const PERMS_PRODUTO_CADASTROS = [
    'variation.view', 'variation.create', 'variation.update', 'variation.delete',
    'warranty.view', 'warranty.create', 'warranty.update', 'warranty.delete',
    'print_labels.access',
];

function ppcCheckboxesDaView(string $view): array
{
    $src = File::get(resource_path('views/role/'.$view.'.blade.php'));
    preg_match_all("/Form::checkbox\('permissions\[\]',\s*'([^']+)'/", $src, $m);

    return array_values(array_unique($m[1] ?? []));
}

it('create e edit oferecem as 9 permissoes, no grupo Produtos', function () {
    foreach (['create', 'edit'] as $view) {
        $oferecidas = ppcCheckboxesDaView($view);

        // Controle positivo: extracao quebrada devolveria [] e o caso abaixo cairia pelo motivo certo,
        // mas o vizinho do grupo prova que estamos lendo a view inteira.
        expect($oferecidas)->toContain('product.view');

        foreach (PERMS_PRODUTO_CADASTROS as $p) {
            expect(in_array($p, $oferecidas, true))->toBeTrue("view {$view} nao oferece {$p}");
        }

        // Mesmo grupo: as 9 aparecem DEPOIS de product.view e ANTES do grupo Marca (brand.view).
        $src = File::get(resource_path('views/role/'.$view.'.blade.php'));
        $posProduto = strpos($src, "'product.view'");
        $posMarca = strpos($src, "'brand.view'");
        foreach (PERMS_PRODUTO_CADASTROS as $p) {
            $pos = strpos($src, "'permissions[]', '{$p}'");
            expect($pos > $posProduto && $pos < $posMarca)->toBeTrue("{$p} fora do grupo Produtos em {$view}");
        }
    }
});

it('o edit marca a permissao que o papel ja tem (in_array no role_permissions)', function () {
    $src = File::get(resource_path('views/role/edit.blade.php'));

    foreach (PERMS_PRODUTO_CADASTROS as $p) {
        expect(str_contains($src, "'{$p}', in_array('{$p}', \$role_permissions)"))
            ->toBeTrue("edit nao reflete o estado de {$p}");
    }
});

it('as 9 tem rotulo PT-BR (role.<perm> traduzido, nao a chave crua)', function () {
    app()->setLocale('pt');

    foreach (PERMS_PRODUTO_CADASTROS as $p) {
        expect(__('role.'.$p))->not->toBe('role.'.$p);
    }
});

describe('persistencia via RoleController (MySQL, tenant 98)', function () {
    beforeEach(function () {
        if (DB::connection()->getDriverName() === 'sqlite') {
            $this->markTestSkipped('SQLite-incompativel: exige schema MySQL UltimatePOS (FKs business/users/roles).');
        }
        if (! Schema::hasTable('business') || ! Schema::hasTable('roles')) {
            $this->markTestSkipped('Schema UltimatePOS ausente — esta suite roda em MySQL real semeado.');
        }

        $this->biz = Business::find(98) ?: Business::forceCreate([
            'id' => 98,
            'name' => 'Tenant ficticio 98 (ADR 0358)',
            'currency_id' => 1,
            'start_date' => now()->toDateString(),
            'default_profit_percent' => 0,
            'owner_id' => 1,
            'stop_selling_before' => 0,
            'weighing_scale_setting' => '',
            'certificado' => '',
            'officeimpresso_numerodemaquinas' => 0,
        ]);

        foreach (array_merge(['roles.create', 'roles.update', 'sell.view'], PERMS_PRODUTO_CADASTROS) as $p) {
            Permission::findOrCreate($p, 'web');
        }

        $papelDoAtor = Role::create([
            'name' => 'PpcAtor'.uniqid().'#'.$this->biz->id,
            'business_id' => $this->biz->id,
            'guard_name' => 'web',
        ]);
        $papelDoAtor->syncPermissions(['roles.create', 'roles.update']);

        $ator = User::factory()->create([
            'business_id' => $this->biz->id,
            'username' => 'ppc_ator_'.uniqid(),
            'user_type' => 'user',
            'allow_login' => 1,
        ]);
        $ator->assignRole($papelDoAtor);

        // Sem limpar o cache do Spatie o can() responde com o retrato anterior e o controller
        // aborta 403 — o caso de "nao gravou" passaria pelo motivo errado.
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        $this->ator = User::findOrFail($ator->id);
        $this->sessao = [
            'user.business_id' => $this->biz->id,
            'user' => ['business_id' => $this->biz->id, 'id' => $this->ator->id],
        ];
    });

    it('CRIAR papel com as 9 marcadas persiste as 9', function () {
        $nome = 'PpcNovo'.uniqid();

        $this->actingAs($this->ator)->withSession($this->sessao)->post('/roles', [
            'name' => $nome,
            'permissions' => array_merge(['sell.view'], PERMS_PRODUTO_CADASTROS),
        ]);

        $papel = Role::where('name', $nome.'#'.$this->biz->id)->where('business_id', $this->biz->id)->first();
        // Controle positivo: o papel nasceu (a requisicao chegou ao store) e levou a vizinha.
        expect($papel)->not->toBeNull();
        $gravadas = $papel->permissions->pluck('name')->all();
        expect($gravadas)->toContain('sell.view');

        expect(array_values(array_diff(PERMS_PRODUTO_CADASTROS, $gravadas)))->toBe([]);
    });

    it('EDITAR papel concede as 9 e, desmarcadas, revoga (o form agora as oferece)', function () {
        $nome = 'PpcEdit'.uniqid();
        $alvo = Role::create([
            'name' => $nome.'#'.$this->biz->id,
            'business_id' => $this->biz->id,
            'guard_name' => 'web',
        ]);

        $this->actingAs($this->ator)->withSession($this->sessao)->put('/roles/'.$alvo->id, [
            'name' => $nome,
            'permissions' => array_merge(['sell.view'], PERMS_PRODUTO_CADASTROS),
        ]);

        $depois = Role::findOrFail($alvo->id)->permissions->pluck('name')->all();
        expect($depois)->toContain('sell.view');
        expect(array_values(array_diff(PERMS_PRODUTO_CADASTROS, $depois)))->toBe([]);

        // Ofertada => revogavel. Se a perm ainda estivesse fora do catalogo, o
        // __preservaNaoOfertadas a manteria e o admin nao conseguiria tirar.
        $this->actingAs($this->ator)->withSession($this->sessao)->put('/roles/'.$alvo->id, [
            'name' => $nome,
            'permissions' => ['sell.view'],
        ]);

        $final = Role::findOrFail($alvo->id)->permissions->pluck('name')->all();
        expect($final)->toContain('sell.view');
        expect(array_values(array_intersect(PERMS_PRODUTO_CADASTROS, $final)))->toBe([]);
    });
});
