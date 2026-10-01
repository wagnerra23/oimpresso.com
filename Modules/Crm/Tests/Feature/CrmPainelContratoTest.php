<?php

declare(strict_types=1);

use App\Business;
use App\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(Tests\TestCase::class);

/**
 * Contrato do Painel do CRM `/crm/dashboard` — thread Crm/04, Blade → Inertia (PT-04).
 *
 * Os UCs vêm do contrato, não do código:
 *   Modules/Crm/Resources/js/Pages/Crm/Painel/Index.casos.md
 *
 * Tenant 98 (fictício, ADR 0358) e 99 como o "outro negócio". NUNCA biz=4.
 * ⚠️ SKIP em SQLite: leia assertions, não "0 failed" (LC-13).
 *
 * @see Modules/Crm/Http/Controllers/CrmDashboardController::index()
 */
const PAI_TAG = '[pai04]';
const PAI_BIZ = 98;
const PAI_OUTRO = 99;
const PAI_ROTA = '/crm/dashboard';

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: painel do CRM requer schema MySQL UltimatePOS.');
    }
    foreach (['business', 'users', 'contacts', 'categories', 'roles', 'crm_schedules', 'crm_schedule_users', 'crm_call_logs'] as $t) {
        if (! Schema::hasTable($t)) {
            $this->markTestSkipped("Schema incompleto — tabela {$t} ausente.");
        }
    }
    paiLimpa();
});

afterEach(fn () => paiLimpa());

function paiLimpa(): void
{
    DB::table('contacts')->where('name', 'like', '%'.PAI_TAG.'%')->delete();
    DB::table('categories')->where('name', 'like', '%'.PAI_TAG.'%')->delete();
}

/**
 * Garante o business `$biz`. `id` é guarded no model: `firstOrCreate(['id' => 99])` não cria
 * o 99 — insere outro id SEM `owner_id` e cai no FK (medido na lane verticais-pest, run
 * 36828026606: passava só quando outro arquivo já tinha criado o 99 antes — ordem aleatória).
 * Mesmo conserto do CrmLeadsContratoTest::leadNegocio.
 */
function paiNegocio(int $biz): void
{
    if (Business::whereKey($biz)->exists()) {
        return;
    }
    $dono = (int) DB::table('users')->min('id');
    (new Business)->forceFill(['id' => $biz, 'name' => 'Tenant fictício crm '.$biz, 'currency_id' => 1, 'owner_id' => $dono])->save();
}

function paiUsuario(string $username, array $permissoes, bool $admin = false, int $biz = PAI_BIZ): User
{
    paiNegocio($biz);
    // `user_type`/`allow_login` explícitos: o CheckUserLogin barra o model recém-criado sem eles
    // (medido no CI da thread 03).
    $user = User::firstOrCreate(['username' => $username], [
        'email' => $username.'@test.local', 'password' => bcrypt('secret'),
        'business_id' => $biz, 'first_name' => 'Pai', 'last_name' => 'Teste',
        'user_type' => 'user', 'allow_login' => 1,
    ]);
    $user->syncPermissions(array_map(fn ($p) => Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']), $permissoes));
    if ($admin) {
        $role = Role::firstOrCreate(['name' => 'Admin#'.$biz, 'guard_name' => 'web'], ['business_id' => $biz]);
        $user->syncRoles([$role]);
    } else {
        $user->syncRoles([]);
    }
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    return $user->fresh();
}

function paiContato(int $biz, string $nome, string $tipo, User $criador, array $extra = []): int
{
    paiNegocio($biz);

    return DB::table('contacts')->insertGetId(array_merge([
        'business_id' => $biz, 'type' => $tipo, 'name' => $nome.' '.PAI_TAG, 'mobile' => '0',
        'contact_status' => 'active', 'created_by' => $criador->id, 'created_at' => now(), 'updated_at' => now(),
    ], $extra));
}

function paiFonte(int $biz, string $nome, User $criador): int
{
    return DB::table('categories')->insertGetId([
        'business_id' => $biz, 'name' => $nome.' '.PAI_TAG, 'category_type' => 'source', 'parent_id' => 0,
        'created_by' => $criador->id, 'created_at' => now(), 'updated_at' => now(),
    ]);
}

/** As props deferidas, pedidas como o browser pede (X-Inertia + X-Requested-With). */
function paiProps(User $user): array
{
    $versao = app(\App\Http\Middleware\HandleInertiaRequests::class)->version(request());
    $resposta = test()->actingAs($user)->get(PAI_ROTA, [
        'X-Inertia' => 'true',
        'X-Requested-With' => 'XMLHttpRequest',
        'X-Inertia-Version' => (string) $versao,
        'X-Inertia-Partial-Data' => 'permissoes,pessoal,negocio',
        'X-Inertia-Partial-Component' => 'Crm/Painel/Index',
    ]);
    $resposta->assertOk();

    return (array) $resposta->json('props');
}

it('UC-CRMPAI-01 · o painel responde Inertia com o componente Crm/Painel/Index', function () {
    $this->actingAs(paiUsuario('pai_comum_test', ['crm.access_own_schedule']))
        ->get(PAI_ROTA)
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $p) => $p->component('Crm/Painel/Index', false)->has('permissoes'));
});

it('UC-CRMPAI-02 · "Meus leads convertidos" conta só conversões deste negócio [T0]', function () {
    $user = paiUsuario('pai_comum_test', []);
    $antes = (int) paiProps($user)['pessoal']['convertidos'];

    paiContato(PAI_BIZ, 'Convertido aqui', 'customer', $user, ['converted_by' => $user->id]);
    paiContato(PAI_OUTRO, 'Convertido no vizinho', 'customer', $user, ['converted_by' => $user->id]);

    $depois = (int) paiProps($user)['pessoal']['convertidos'];
    $this->assertSame($antes + 1, $depois, 'a conversão do outro business entrou na conta (ou a deste não entrou)');
});

it('UC-CRMPAI-03 · Admin vê o quadro do negócio, só com dados do próprio negócio [T0]', function () {
    $admin = paiUsuario('pai_admin_test', [], true);
    $vizinho = paiUsuario('pai_vizinho_test', [], true, PAI_OUTRO);
    $minha = paiFonte(PAI_BIZ, 'Feira', $admin);
    paiFonte(PAI_OUTRO, 'Indicacao vizinho', $vizinho);
    paiContato(PAI_BIZ, 'Lead da feira', 'lead', $admin, ['crm_source' => (string) $minha]);

    $negocio = paiProps($admin)['negocio'];
    $fontes = collect($negocio['por_fonte'])->keyBy('fonte');

    $this->assertTrue($fontes->has('Feira '.PAI_TAG), 'a fonte do próprio negócio não apareceu');
    $this->assertSame(1, (int) $fontes['Feira '.PAI_TAG]['total'], 'o lead da fonte não foi contado');
    $this->assertFalse($fontes->has('Indicacao vizinho '.PAI_TAG), 'vazou fonte de outro business');
});

it('UC-CRMPAI-04 · quem não é Admin não recebe o quadro do negócio', function () {
    $props = paiProps(paiUsuario('pai_comum_test', ['crm.access_all_schedule']));

    // A chave existe (foi pedida) e vem vazia — `?? null` aqui deixaria o assert verde por ausência.
    $this->assertArrayHasKey('negocio', $props, 'a prop negocio nem foi devolvida — o assert seria vácuo');
    $this->assertNull($props['negocio'], 'não-Admin recebeu o bloco do negócio');
    expect($props['permissoes']['admin'])->toBeFalse();
});

it('UC-CRMPAI-05 · seções pessoais seguem a permissão, como na Blade', function () {
    $sem = paiProps(paiUsuario('pai_sem_test', []))['pessoal'];
    $this->assertNull($sem['hoje'], 'sem permissão de acompanhamento recebeu "Acompanhamentos de hoje"');
    $this->assertNull($sem['por_status'], 'sem permissão de acompanhamento recebeu "Meus acompanhamentos"');
    $this->assertNull($sem['meus_leads'], 'sem permissão de leads recebeu "Meus leads"');

    $com = paiProps(paiUsuario('pai_com_test', ['crm.access_own_schedule', 'crm.access_own_leads']))['pessoal'];
    $this->assertIsInt($com['hoje']);
    $this->assertIsArray($com['por_status']);
    $this->assertIsInt($com['meus_leads']);
});

it('UC-CRMPAI-06 · ?classico=1 devolve a tela Blade', function () {
    $resposta = $this->actingAs(paiUsuario('pai_comum_test', ['crm.access_own_schedule']))
        ->withSession(['currency' => ['code' => 'BRL', 'symbol' => 'R$', 'thousand_separator' => '.', 'decimal_separator' => ',']])
        ->get(PAI_ROTA.'?classico=1');

    $resposta->assertOk();
    $resposta->assertViewIs('crm::crm_dashboard.index');
});
