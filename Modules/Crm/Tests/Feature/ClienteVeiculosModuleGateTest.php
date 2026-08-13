<?php

declare(strict_types=1);

use App\Business;
use App\Contact;
use App\User;
use App\Utils\ModuleUtil;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(Tests\TestCase::class);

/**
 * Gate de módulo do `ClienteVeiculosController` — endpoint do sub-tab "Placas".
 *
 * O QUE DEFENDE: de 2026-05-27 a 2026-08-13 o `Routes/web.php` AFIRMAVA em tempo presente
 * que o controller *"retorna [] se Vehicle model inexistente em ambiente sem modulo"*, e o
 * docblock declarava o mesmo como já-feito. Não havia gate: `index()` ia direto de
 * permissão → `business_id` → `Contact` → `Vehicle::where(...)`. Só não quebrava por
 * ACIDENTE DE INFRA (PSR-4 na raiz torna `Vehicle` autoloadable com o módulo desligado, e
 * `vehicles` viaja no `mysql-schema.sql`). Classe LC-10.
 *
 * TENANT: cria o próprio via `Business::factory()`, em vez de fixar um id. A 1ª versão
 * deste arquivo hardcodava `business_id = 98` (o tenant canônico da ADR 0358) e **falhou
 * no CT 100 com FK violation** — o staging tem business `1, 99, 2`, não 98. Fixar id
 * assume seed; criar o próprio não assume nada. O espírito da 0358 (nunca tocar tenant de
 * cliente real, jamais biz=4) é respeitado com folga.
 *
 * ⚠️ ONDE RODA: `Modules/Crm/Tests/Feature` está no `phpunit.xml`, mas **nenhuma lane de
 * PR o executa** — `test-lane-coverage --modulo Crm` → 13/13 fora do PR, e o
 * `modules-pest.yml` tem matrix onde o Crm não está.
 *
 * @see Modules/Crm/Http/Controllers/ClienteVeiculosController::index()
 */

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: exige schema MySQL UltimatePOS (FKs business/contacts/users).');
    }
    if (! Schema::hasTable('business') || ! Schema::hasTable('contacts')) {
        $this->markTestSkipped('Schema UltimatePOS ausente — esta suíte roda em MySQL real semeado.');
    }

    // Tenant 98 (ADR 0358). `App\Business` NÃO tem `HasFactory` — não existe
    // `BusinessFactory` no repo, então `Business::factory()` estoura
    // BadMethodCallException. O idioma que funciona é `find`-ou-`forceCreate` com as
    // colunas NOT NULL sem default do schema real, espelhando o seed do
    // `.github/actions/pest-mysql-setup` (mesmo padrão do ComprasContratoFiltrosTest,
    // que roda em lane própria).
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

    // `roles.business_id` é NOT NULL + FK pra business (proibicoes.md §FSM), e o sufixo
    // `#{biz}` é a convenção da casa pra role por tenant.
    $perm = Permission::firstOrCreate(['name' => 'customer.view', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(
        ['name' => 'cv-gate-test#'.$this->biz->id, 'guard_name' => 'web'],
        ['business_id' => $this->biz->id]
    );
    $role->givePermissionTo($perm);

    // user_type='user' + allow_login=1: sem eles o middleware CheckUserLogin aborta 403.
    $this->user = User::factory()->create([
        'business_id' => $this->biz->id,
        'username' => 'cv_gate_'.uniqid(),
        'user_type' => 'user',
        'allow_login' => 1,
    ]);
    $this->user->assignRole($role);

    $this->contact = Contact::create([
        'business_id' => $this->biz->id,
        'type' => 'customer',
        'name' => 'Cliente Fixture Gate',
        'contact_status' => 'active',
        'is_customer' => 1,
        // FK NOT NULL `contacts.created_by` -> `users.id`. Medido no CT 100: sem isto,
        // 1452 Integrity constraint violation.
        'created_by' => $this->user->id,
    ]);
});

/** Troca o ModuleUtil do container — o controller resolve por `app(ModuleUtil::class)`. */
function cvModulo(bool $instalado): void
{
    $fake = Mockery::mock(ModuleUtil::class)->makePartial();
    $fake->shouldReceive('isModuleInstalled')->with('OficinaAuto')->andReturn($instalado);
    app()->instance(ModuleUtil::class, $fake);
}

function cvChamar()
{
    return test()
        ->actingAs(test()->user)
        ->withSession(['user.business_id' => test()->biz->id, 'user' => ['business_id' => test()->biz->id, 'id' => test()->user->id]])
        ->getJson('/cliente/'.test()->contact->id.'/veiculos');
}

it('MORDE: sem o módulo OficinaAuto, responde 200 com paginador VAZIO', function () {
    cvModulo(false);

    cvChamar()
        ->assertOk()
        ->assertExactJson([
            'data' => [],
            'total' => 0,
            'current_page' => 1,
            'last_page' => 1,
            'from' => null,
            'to' => null,
        ]);
});

it('CN: com o módulo instalado, o gate NÃO curto-circuita — responde a shape real', function () {
    cvModulo(true);

    // Controle negativo do próprio gate: se ele passasse a barrar SEMPRE, o caminho feliz
    // morreria em silêncio e o sub-tab ficaria vazio pra quem TEM o módulo — regressão pior
    // que o defeito original, e invisível sem esta asserção.
    cvChamar()
        ->assertOk()
        ->assertJsonStructure(['data', 'total', 'current_page', 'last_page', 'from', 'to']);
});

it('permissão continua obrigatória — o gate de módulo não abriu buraco de autorização', function () {
    cvModulo(false);
    $this->user->removeRole('cv-gate-test#'.$this->biz->id);

    // O 403 tem de vir ANTES do paginador vazio: sem esta asserção, um refactor que
    // subisse o gate de módulo acima do gate de permissão passaria despercebido e
    // qualquer autenticado saberia que o cliente existe.
    cvChamar()->assertStatus(403);
});
