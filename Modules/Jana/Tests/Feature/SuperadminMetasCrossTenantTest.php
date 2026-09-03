<?php

declare(strict_types=1);

use App\Business;
use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Jana\Entities\Meta;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * P0 Tier 0 — `/ia/superadmin/metas` entregava as metas de TODOS os tenants pro
 * dono de QUALQUER negócio, com o link visível no menu dele.
 *
 * ── ÂNCORA DE CONTRATO (externa ao código — teste tautológico é lápide §5 2026-06-05)
 *   · ADR 0093 — multi-tenant Tier 0 IRREVOGÁVEL. `App\Scopes\ScopeByBusiness`:
 *     "Toda query que deliberadamente queira sair desse escopo precisa chamar
 *      withoutGlobalScope. Caso contrário, NUNCA há vazamento entre businesses."
 *   · ADR 0358 — tenant canônico 98, adversário 99. NUNCA biz=4 (ROTA LIVRE, cliente
 *     real) e NUNCA biz=1 (WR2, em operação; no CT 100 a base é clone de prod).
 *   · app/Providers/AuthServiceProvider.php:34-47 — o `Gate::before` que abriu o buraco.
 *
 * ── A CADEIA, MEDIDA EM 2026-08-28 (estado ANTES do fix)
 *   O `Gate::before` devolve `true` em QUALQUER ability fora de
 *   ['backup','superadmin','manage_modules'] pra quem tem `Admin#{business_id}`.
 *   `jana.superadmin` NÃO está nessa allowlist → todo dono passava no
 *   `abort_unless($user->can('jana.superadmin'), 403)` → e o `withoutGlobalScope`
 *   do controller devolvia `whereNotNull('business_id')`, isto é, todos os tenants.
 *   O item de menu (Modules/Jana/Resources/menus/topnav.php:44) usa a mesma ability,
 *   então o link ainda aparecia pra ele.
 *
 * ── O CONTROLE QUE TORNA ESTE TESTE HONESTO
 *   O 1º caso asserta que `can('jana.superadmin')` É `true` pro dono. Sem esse
 *   controle, o 403 poderia vir de qualquer outra trava e o verde seria FALSO —
 *   mediria a ausência do bypass em vez da presença da defesa.
 *
 * @see Modules/Jana/Http/Controllers/SuperadminController.php
 * @see Modules/Jana/Http/Controllers/DataController.php (podeVerPlataforma — o predicado das 2 portas, dono do menu)
 * @see Modules/Jana/Tests/Feature/MetasControllerBaselineTest.php (harness base)
 * @see Modules/Jana/Tests/Feature/JanaAccessGateTest.php (sentinela do Gate::before)
 */

const SUPMETAS_BIZ_CANONICO = 98;
const SUPMETAS_BIZ_ADVERSARIO = 99;

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: schema UltimatePOS requer MySQL (ADR 0062).');
    }
    if (! Schema::hasTable('jana_metas')) {
        $this->markTestSkipped('Tabela jana_metas ausente — rode migrate Modules/Jana.');
    }

    $business = Business::find(SUPMETAS_BIZ_CANONICO);
    if (! $business) {
        $this->markTestSkipped('business_id=98 (tenant canônico ADR 0358) ausente — rode o seed do pest-mysql-setup.');
    }
    $user = User::where('business_id', SUPMETAS_BIZ_CANONICO)->first();
    if (! $user) {
        $this->markTestSkipped('Sem user em business_id=98.');
    }
    $this->user = $user;

    if (! Business::find(SUPMETAS_BIZ_ADVERSARIO)) {
        Business::forceCreate([
            'id' => SUPMETAS_BIZ_ADVERSARIO,
            'name' => 'Test Biz Adversario#99 (Superadmin metas)',
            'currency_id' => 1,
            'start_date' => now()->toDateString(),
            'default_profit_percent' => 0,
            'owner_id' => $user->id,
            'stop_selling_before' => 0,
            'weighing_scale_setting' => '',
            'certificado' => '',
            'officeimpresso_numerodemaquinas' => 0,
        ]);
    }

    // O DADO QUE VAZAVA: uma meta do tenant adversário.
    $this->metaAdversaria = Meta::withoutGlobalScopes()->create([
        'business_id' => SUPMETAS_BIZ_ADVERSARIO,
        'slug' => 'vazamento_'.uniqid(),
        'nome' => 'CANARIO-VAZAMENTO-99',
        'unidade' => 'R$',
        'tipo_agregacao' => 'soma',
        'ativo' => true,
        'origem' => 'manual',
    ]);

    Permission::findOrCreate('jana.access', 'web');
    Permission::findOrCreate('jana.superadmin', 'web');

    // O dono do negócio: papel `Admin#98`, que é o que dispara o `Gate::before`.
    // `roles.business_id` é NOT NULL no UltimatePOS quando a coluna existe.
    $attrs = ['name' => 'Admin#'.SUPMETAS_BIZ_CANONICO, 'guard_name' => 'web'];
    if (Schema::hasColumn('roles', 'business_id')) {
        $attrs['business_id'] = SUPMETAS_BIZ_CANONICO;
    }
    $roleAdmin = Role::firstOrCreate($attrs);
    $this->user->assignRole($roleAdmin);

    $this->user->givePermissionTo('jana.access');
    $this->user->revokePermissionTo('jana.superadmin');
    $this->user->user_type = 'user';   // dono de negócio comum, NÃO superadmin
    $this->user->save();
    $this->user->forgetCachedPermissions();

    $this->actingAs($this->user);
    session([
        'user.business_id' => SUPMETAS_BIZ_CANONICO,
        'business' => ['id' => SUPMETAS_BIZ_CANONICO, 'name' => $business->name],
    ]);
});

it('CONTROLE: o dono do negócio PASSA no can(jana.superadmin) — o bypass do Gate::before está ativo', function () {
    // Se este assert virar false, o `Gate::before` mudou e TODA permissão do ERP
    // mudou de significado junto. Sem ele, o 403 do caso seguinte seria falso-verde.
    expect($this->user->can('jana.superadmin'))->toBeTrue();
    expect($this->user->hasPermissionTo('jana.superadmin'))->toBeFalse();
})->group('tier0');

it('ADR 0093 · dono de negócio recebe 403 e NÃO vê a meta de outro tenant', function () {
    $resp = $this->get('/ia/superadmin/metas');

    $resp->assertStatus(403);
    expect($resp->getContent())->not->toContain('CANARIO-VAZAMENTO-99');
})->group('tier0');

it('não regride: quem tem a permissão REAL (Spatie, sem bypass) segue entrando', function () {
    $this->user->givePermissionTo('jana.superadmin');
    $this->user->forgetCachedPermissions();

    expect($this->user->fresh()->hasPermissionTo('jana.superadmin'))->toBeTrue();

    // `not 403` e nao `200`: o que este caso prova e que A GUARDA DEIXOU PASSAR.
    // Amarrar em 200 mediria junto a renderizacao da view Blade AdminLTE, que
    // depende de sessao/layout e falharia por motivo alheio ao contrato. Mesmo
    // padrao do precedente do modulo (JanaAccessGateTest, 'LIMITE HONESTO').
    expect($this->get('/ia/superadmin/metas')->status())->not->toBe(403);
})->group('tier0');

/**
 * ERRATA 2026-09-03 — este caso afirmava o OPOSTO, e era falso por construção.
 *
 * Texto original: *"não regride: superadmin por user_type segue entrando mesmo sem a
 * permissão atribuída"*, assertando `not->toBe(403)`. Ele nasceu com o #6421 e **nunca
 * rodou**: não estava nesta lane nem na de sqlite (onde skipa por driver). No primeiro
 * run real (PR da tela Plataforma) reprovou com `Expecting 403 not to be 403`.
 *
 * A CAUSA, medida na cadeia e não deduzida: o grupo `/ia` carrega o middleware
 * `CheckUserLogin` (`Modules/Jana/Http/routes.php:50`), e ele faz
 *
 *     if ($request->user()->user_type != 'user' || ... ) abort(403);
 *
 * ⇒ QUALQUER `user_type` diferente de `'user'` leva 403 ANTES de o controller rodar.
 * A porta (b) do gate — `user_type ∈ {superadmin, user_oimpresso}` — é **inalcançável
 * em toda rota do grupo `/ia`**. Ela existe no código e não pode ser exercida ali.
 *
 * Isso NÃO é regressão da tela nem do #6421: é uma verdade do middleware que ninguém
 * tinha medido, porque o teste que a teria pego nunca executou. Bate com a medição de
 * produção de 2026-08-31 (RUNBOOK-plataforma.md §1.1): ZERO usuários com esse
 * `user_type`, e os 5 que alcançam a tela entram todos pela porta (a).
 *
 * ⛔ REMOVER a porta (b) do controller é decisão [W], não conserto de teste — ela é
 * fail-safe declarado e sair dela muda o gate. O que este caso faz agora é IMPEDIR que
 * alguém volte a acreditar que ela funciona.
 */
it('a porta `user_type` é INALCANÇÁVEL no grupo /ia — o CheckUserLogin barra antes do controller', function () {
    $this->user->user_type = 'superadmin';
    $this->user->save();
    $this->user->forgetCachedPermissions();

    // CONTROLE POSITIVO: o predicado do controller diz SIM para este usuário...
    expect(\Modules\Jana\Http\Controllers\DataController::podeVerPlataforma($this->user->fresh()))
        ->toBeTrue('o gate do controller deixaria passar — se a request chegasse nele');

    // ...e mesmo assim a request leva 403, porque o middleware do grupo aborta antes.
    // Sem o controle acima, este 403 seria indistinguível de "o gate funcionou".
    expect($this->get('/ia/superadmin/metas')->status())->toBe(403);
})->group('tier0');
