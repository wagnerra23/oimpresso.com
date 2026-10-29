<?php

/**
 * Contrato da página Documentação › Casos de uso por tela (/documentacao/casos-por-tela).
 *
 * Os casos defendem o que a página promete à Maiara, ao Felipe e ao [W]:
 *   - UC-CASOSP-00 · a capa só mostra o botão pra quem abre a página;
 *   - UC-CASOSP-01 · a página traz os dados lidos dos casos.md NA HORA, iguais ao leitor;
 *   - UC-CASOSP-02 · o dono de uma empresa-cliente (Admin#98) NÃO abre — é doc da plataforma;
 *   - UC-CASOSP-03 · "Conferido" grava e desgrava a marca com o usuário logado;
 *   - UC-CASOSP-04 · só quem decide responde a decisão; quem só confere toma 403.
 * A paridade com o leitor Node (UC-CASOSP-05) mora em scripts/casos-backlog/paridade-php.test.mjs.
 *
 * TENANT: 98 (ADR 0358) — só como empresa do usuário; a página não lê dado de empresa.
 * Limpeza em try/finally (a base do CT 100 persiste entre runs).
 *
 * @see resources/js/Pages/Documentacao/CasosPorTela.casos.md
 * @see memory/requisitos/Documentacao/RUNBOOK-casos-por-tela.md
 */

use App\Http\Controllers\CasosPorTelaController;
use App\Services\Documentacao\CasosPorTela;
use App\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    if (! Schema::hasTable('doc_casos_marcas') || ! Schema::hasTable('permissions') || ! Schema::hasTable('business')) {
        $this->markTestSkipped('Schema ausente (doc_casos_marcas/permissions/business) — lane sem MySQL migrado.');
    }
    // Ninguém do teste está em administrator_usernames: mede a permissão, não o atalho do [W].
    config(['constants.administrator_usernames' => 'ninguem-casosp-'.uniqid()]);
    foreach ([CasosPorTelaController::PERMISSAO_VER, CasosPorTelaController::PERMISSAO_CONFERIR, CasosPorTelaController::PERMISSAO_DECIDIR] as $p) {
        Permission::findOrCreate($p, 'web');
    }
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    Cache::forget('doc:casos-por-tela:v1');
});

function casospUsuario(int $biz, array $permissoes = []): User
{
    $u = User::factory()->create(['business_id' => $biz, 'username' => 'casosp_'.uniqid(), 'user_type' => 'user', 'allow_login' => 1]);
    foreach ($permissoes as $p) {
        $u->givePermissionTo($p);
    }
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    return $u->fresh();
}

function casospLimpar(): void
{
    DB::table('doc_casos_marcas')->whereIn('user_id', User::withTrashed()->where('username', 'like', 'casosp_%')->pluck('id'))->delete();
    foreach (User::withTrashed()->where('username', 'like', 'casosp_%')->get() as $u) {
        DB::table('model_has_roles')->where('model_id', $u->id)->delete();
        DB::table('model_has_permissions')->where('model_id', $u->id)->delete();
        $u->forceDelete();
    }
}

function casospGet(User $user, int $biz, string $url = '/documentacao/casos-por-tela')
{
    return test()->actingAs($user)
        ->withSession(['user.business_id' => $biz, 'user' => ['business_id' => $biz, 'id' => $user->id]])
        ->get($url);
}

/** Resolve as props deferidas como o cliente Inertia (com os headers que ele manda de fato). */
function casospDeferidas(User $user, int $biz): array
{
    test()->flushHeaders();
    $inicial = casospGet($user, $biz);
    expect($inicial->status())->toBe(200);
    $versao = data_get($inicial->viewData('page'), 'version');

    $parcial = test()->actingAs($user)
        ->withSession(['user.business_id' => $biz, 'user' => ['business_id' => $biz, 'id' => $user->id]])
        ->withHeaders([
            'X-Requested-With' => 'XMLHttpRequest',
            'X-Inertia' => 'true',
            'X-Inertia-Version' => (string) $versao,
            'X-Inertia-Partial-Component' => 'Documentacao/CasosPorTela',
            'X-Inertia-Partial-Data' => 'dados,marcas',
        ])
        ->get('/documentacao/casos-por-tela');
    test()->flushHeaders();
    expect($parcial->status())->toBe(200);

    return $parcial->json('props');
}

it('UC-CASOSP-00: a capa mostra o botão "Casos de uso por tela" só pra quem pode abrir', function () {
    $biz = (int) $this->seededTenant()->id;
    try {
        $com = casospUsuario($biz, [CasosPorTelaController::PERMISSAO_VER]);
        $sem = casospUsuario($biz);
        expect(data_get(casospGet($com, $biz, '/documentacao')->viewData('page'), 'props.podeVerCasosPorTela'))->toBeTrue();
        test()->flushHeaders();
        expect(data_get(casospGet($sem, $biz, '/documentacao')->viewData('page'), 'props.podeVerCasosPorTela'))->toBeFalse();
    } finally {
        casospLimpar();
    }
});

it('UC-CASOSP-01: a página traz os pedidos e contratos lidos dos casos.md, iguais ao leitor', function () {
    $biz = (int) $this->seededTenant()->id;
    try {
        $u = casospUsuario($biz, [CasosPorTelaController::PERMISSAO_VER]);
        $esperado = (new CasosPorTela(base_path()))->dados();
        // Controle positivo: o repositório tem pedidos e contratos — comparar vazios não provaria nada.
        expect($esperado['resumo']['pedidos'])->toBeGreaterThan(0);
        expect($esperado['resumo']['casosDeUso'])->toBeGreaterThan(0);

        $props = casospDeferidas($u, $biz);
        expect($props['dados']['resumo'])->toBe($esperado['resumo']);
        expect(array_column($props['dados']['casos'], 'id'))->toBe(array_column($esperado['casos'], 'id'));
        expect($props['marcas'])->toBe(['conferencia' => [], 'decisoes' => []]);
    } finally {
        casospLimpar();
    }
});

it('UC-CASOSP-02: o dono de uma empresa-cliente (Admin#98) não abre — é documentação da plataforma', function () {
    $biz = (int) $this->seededTenant()->id;
    try {
        $dono = casospUsuario($biz);
        $attrs = ['name' => 'Admin#'.$biz, 'guard_name' => 'web'];
        if (Schema::hasColumn('roles', 'business_id')) {
            $attrs['business_id'] = $biz;
        }
        $dono->assignRole(Role::firstOrCreate($attrs));
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        // Controle: o Gate::before de fato libera o dono pra uma permissão comum do ERP —
        // sem isto o 403 abaixo poderia vir de o papel não ter sido reconhecido.
        expect($dono->fresh()->can('product.view'))->toBeTrue();

        casospGet($dono->fresh(), $biz)->assertStatus(403);
    } finally {
        casospLimpar();
    }
});

it('UC-CASOSP-03: "Conferido" grava a marca com o usuário logado, e desmarcar apaga', function () {
    $biz = (int) $this->seededTenant()->id;
    try {
        $pedido = (new CasosPorTela(base_path()))->dados()['casos'][0]['id'];
        $u = casospUsuario($biz, [CasosPorTelaController::PERMISSAO_VER, CasosPorTelaController::PERMISSAO_CONFERIR]);
        DB::table('doc_casos_marcas')->where('tipo', 'conferencia')->where('chave', $pedido)->delete();

        test()->actingAs($u)->withSession(['user.business_id' => $biz])
            ->post("/documentacao/casos-por-tela/conferencia/{$pedido}", ['conferido' => true])->assertRedirect();
        expect(DB::table('doc_casos_marcas')->where('tipo', 'conferencia')->where('chave', $pedido)->value('user_id'))->toBe($u->id);
        expect(casospDeferidas($u, $biz)['marcas']['conferencia'])->toHaveKey($pedido);

        test()->actingAs($u)->withSession(['user.business_id' => $biz])
            ->post("/documentacao/casos-por-tela/conferencia/{$pedido}", ['conferido' => false])->assertRedirect();
        expect(DB::table('doc_casos_marcas')->where('tipo', 'conferencia')->where('chave', $pedido)->exists())->toBeFalse();
    } finally {
        casospLimpar();
    }
});

it('UC-CASOSP-04: só quem decide responde; quem só confere toma 403 e nada é gravado', function () {
    $biz = (int) $this->seededTenant()->id;
    try {
        $confere = casospUsuario($biz, [CasosPorTelaController::PERMISSAO_VER, CasosPorTelaController::PERMISSAO_CONFERIR]);
        $decide = casospUsuario($biz, [CasosPorTelaController::PERMISSAO_VER, CasosPorTelaController::PERMISSAO_DECIDIR]);
        DB::table('doc_casos_marcas')->where('tipo', 'decisao')->where('chave', 'D-99')->delete();

        test()->actingAs($confere)->withSession(['user.business_id' => $biz])
            ->post('/documentacao/casos-por-tela/decisao/D-99', ['opcao' => 'A', 'nota' => 'x'])->assertStatus(403);
        expect(DB::table('doc_casos_marcas')->where('tipo', 'decisao')->where('chave', 'D-99')->exists())->toBeFalse();

        test()->actingAs($decide)->withSession(['user.business_id' => $biz])
            ->post('/documentacao/casos-por-tela/decisao/D-99', ['opcao' => 'A', 'nota' => 'faz já'])->assertRedirect();
        $linha = DB::table('doc_casos_marcas')->where('tipo', 'decisao')->where('chave', 'D-99')->first();
        expect($linha->opcao)->toBe('A');
        expect($linha->nota)->toBe('faz já');
        expect((int) $linha->user_id)->toBe($decide->id);
    } finally {
        DB::table('doc_casos_marcas')->where('tipo', 'decisao')->where('chave', 'D-99')->delete();
        casospLimpar();
    }
});
