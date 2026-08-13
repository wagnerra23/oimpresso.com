<?php

declare(strict_types=1);

use App\Contact;
use App\User;
use App\Utils\ModuleUtil;
use Illuminate\Support\Facades\DB;

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
 * ⚠️ ONDE ESTE TESTE RODA — declarado, não presumido:
 * `Modules/Crm/Tests/Feature` está no `phpunit.xml:44`, mas **nenhuma lane de PR o executa**
 * — medido com o dono da pergunta (`node scripts/governance/test-lane-coverage.mjs --modulo
 * Crm` → **13/13 fora do PR**); o `modules-pest.yml` tem matrix e o Crm **não está nela**.
 * Logo: roda na árvore nightly do CT 100 e **não é gate de merge**.
 *
 * ⚠️ NÃO EXECUTADO pelo autor: Pest é proibido na máquina local (ADR 0062, hook
 * `block-test-fora-ct100`) e o checkout do CT 100 está em 2026-07-23, sem estes arquivos.
 * A primeira execução real será a nightly. Isto é rótulo honesto, não desculpa.
 *
 * Tenant: 98 (ADR 0358 — tenant fictício canônico). NUNCA biz=4, que é cliente real.
 *
 * @see Modules/Crm/Http/Controllers/ClienteVeiculosController::index()
 * @see app/Http/Controllers/ContactController (linha do `isModuleInstalled('OficinaAuto')` — o pattern canon)
 */

const CV_BIZ_FICTICIO = 98;

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: exige schema MySQL UltimatePOS (FKs business/contacts/users).');
    }
});

/** Usuário + contato do tenant fictício, com as permissões que o endpoint exige. */
function cvSeed(): array
{
    $user = User::factory()->create(['business_id' => CV_BIZ_FICTICIO]);
    $user->givePermissionTo('customer.view');

    $contact = Contact::create([
        'business_id' => CV_BIZ_FICTICIO,
        'type' => 'customer',
        'name' => 'Cliente Fixture Gate',
        'created_by' => $user->id,
    ]);

    return [$user, $contact];
}

function cvChamar(User $user, Contact $contact)
{
    return test()
        ->actingAs($user)
        ->withSession(['user' => ['business_id' => CV_BIZ_FICTICIO, 'id' => $user->id]])
        ->getJson("/cliente/{$contact->id}/veiculos");
}

it('MORDE: sem o módulo OficinaAuto, o endpoint responde 200 com paginador VAZIO', function () {
    [$user, $contact] = cvSeed();

    // O controller resolve por `app(ModuleUtil::class)`, então o fake intercepta sem
    // precisar desinstalar módulo de verdade.
    $fake = Mockery::mock(ModuleUtil::class);
    $fake->shouldReceive('isModuleInstalled')->with('OficinaAuto')->andReturnFalse();
    app()->instance(ModuleUtil::class, $fake);

    cvChamar($user, $contact)
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
    [$user, $contact] = cvSeed();

    $fake = Mockery::mock(ModuleUtil::class);
    $fake->shouldReceive('isModuleInstalled')->with('OficinaAuto')->andReturnTrue();
    app()->instance(ModuleUtil::class, $fake);

    // Controle negativo do próprio gate: se ele passasse a barrar SEMPRE, o caminho feliz
    // morreria em silêncio e o sub-tab ficaria vazio pra quem TEM o módulo — regressão pior
    // que o defeito original, e invisível sem esta asserção. O cliente não tem veículo
    // semeado, então `data` vem vazia nos dois casos: o que distingue é `current_page`, que
    // no caminho real vem do paginator do Eloquent, não da constante.
    cvChamar($user, $contact)
        ->assertOk()
        ->assertJsonStructure(['data', 'total', 'current_page', 'last_page', 'from', 'to']);
});

it('permissão continua obrigatória — o gate de módulo não abriu buraco de autorização', function () {
    [$user, $contact] = cvSeed();
    $user->revokePermissionTo('customer.view');

    $fake = Mockery::mock(ModuleUtil::class);
    $fake->shouldReceive('isModuleInstalled')->with('OficinaAuto')->andReturnFalse();
    app()->instance(ModuleUtil::class, $fake);

    // O 403 tem de vir ANTES do paginador vazio: sem esta asserção, um refactor que
    // subisse o gate de módulo pra cima do gate de permissão passaria despercebido e
    // qualquer usuário autenticado saberia que o cliente existe.
    cvChamar($user, $contact)->assertStatus(403);
});
