<?php

declare(strict_types=1);

use App\Business;
use App\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Connector\Http\Controllers\Api\Crm\FollowUpController;
use Modules\Crm\Utils\CrmUtil;

uses(Tests\TestCase::class);

/**
 * `CrmUtil::getLeadsListQuery` contra o schema MySQL REAL — a raiz do 500 achado no #8370.
 *
 * A query selecionava `contacts.prefix/first_name/middle_name/last_name`, colunas que não
 * existem mais em `contacts` (`database/schema/mysql-schema.sql` só tem `name` e
 * `supplier_business_name`) ⇒ `Unknown column` em TODO chamador. Os chamadores são 2 de 2
 * (`git grep getLeadsListQuery`): `LeadController::index` (Crm) e
 * `FollowUpController::getLeads` (`GET connector/api/crm/leads`, cliente externo).
 *
 * As chaves `prefix/first_name/middle_name/last_name` SEGUEM no resultado (contrato da API
 * documentado no próprio FollowUpController), derivadas de `name`.
 *
 * Tenant 98 (fictício, ADR 0358) × 99. NUNCA biz=4. ⚠️ SKIP em SQLite — leia assertions (LC-13).
 */
const LQ_TAG = '[leadsq]';
const LQ_BIZ = 98;
const LQ_OUTRO = 99;

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: contacts/crm_lead_users requerem schema MySQL UltimatePOS.');
    }
    foreach (['business', 'users', 'contacts', 'crm_lead_users'] as $t) {
        if (! Schema::hasTable($t)) {
            $this->markTestSkipped("Schema incompleto — tabela {$t} ausente.");
        }
    }
    lqLimpa();
});

afterEach(fn () => lqLimpa());

function lqLimpa(): void
{
    if (Schema::hasTable('contacts')) {
        DB::table('contacts')->where('name', 'like', '%'.LQ_TAG.'%')->delete();
    }
}

function lqNegocio(int $biz): void
{
    if (Business::whereKey($biz)->exists()) {
        return;
    }
    $dono = (int) DB::table('users')->min('id');
    (new Business)->forceFill(['id' => $biz, 'name' => 'Tenant fictício crm '.$biz, 'currency_id' => 1, 'owner_id' => $dono])->save();
}

function lqUsuario(string $username, int $biz): User
{
    lqNegocio($biz);

    return User::firstOrCreate(['username' => $username], [
        'email' => $username.'@test.local', 'password' => bcrypt('secret'),
        'business_id' => $biz, 'first_name' => 'Lead', 'last_name' => 'Query',
        'user_type' => 'user', 'allow_login' => 1,
    ]);
}

function lqLead(int $biz, string $nome, User $dono): int
{
    lqNegocio($biz);
    $id = DB::table('contacts')->insertGetId([
        'business_id' => $biz, 'type' => 'lead', 'name' => $nome.' '.LQ_TAG, 'mobile' => '0',
        'contact_id' => 'Q'.random_int(100000, 999999),
        'created_by' => $dono->id, 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('crm_lead_users')->insert(['contact_id' => $id, 'user_id' => $dono->id]);

    return $id;
}

it('a query executa no MySQL e devolve só os leads do negócio [T0]', function () {
    // Pré-condição anti-vácuo: o schema de fato NÃO tem as colunas — sem isto o teste
    // passaria também num banco onde o defeito nunca existiu.
    expect(Schema::hasColumn('contacts', 'first_name'))->toBeFalse();

    $nosso = lqUsuario('lq_nosso_test', LQ_BIZ);
    $vizinho = lqUsuario('lq_vizinho_test', LQ_OUTRO);
    lqLead(LQ_BIZ, 'Grafica Nossa', $nosso);
    lqLead(LQ_OUTRO, 'Grafica Vizinha', $vizinho);

    $linhas = app(CrmUtil::class)->getLeadsListQuery(LQ_BIZ)->get()
        ->filter(fn ($l) => str_contains((string) $l->name, LQ_TAG));

    expect($linhas->pluck('name')->all())->toBe(['Grafica Nossa '.LQ_TAG]);
    $this->assertSame(LQ_BIZ, (int) $linhas->first()->business_id, 'lead de outro negócio vazou');

    // As chaves de nome seguem no resultado, derivadas de `name`.
    $l = $linhas->first();
    expect($l->first_name)->toBe('Grafica Nossa '.LQ_TAG)
        ->and($l->prefix)->toBeNull()
        ->and($l->middle_name)->toBeNull()
        ->and($l->last_name)->toBeNull();
});

it('FollowUpController::getLeads (GET connector/api/crm/leads) não dá 500 e não vaza lead de outro negócio [T0]', function () {
    $nosso = lqUsuario('lq_nosso_test', LQ_BIZ);
    $vizinho = lqUsuario('lq_vizinho_test', LQ_OUTRO);
    lqLead(LQ_BIZ, 'Api Nossa', $nosso);
    lqLead(LQ_OUTRO, 'Api Vizinha', $vizinho);

    // Chama o controller direto (padrão do LicencaComputadorApiEscopoTest): o guard `api`
    // (Passport) não sobe no CI sem as chaves OAuth (`Invalid key supplied` — medido na 1ª
    // rodada desta lane). O 500 era SQL DENTRO do getLeads(), e é isso que se exercita aqui.
    $this->actingAs($nosso);
    request()->merge(['per_page' => -1]);
    $resposta = app(FollowUpController::class)->getLeads()->response();
    expect($resposta->getStatusCode())->toBe(200);

    $nossos = collect((array) ($resposta->getData(true)['data'] ?? []))
        ->filter(fn ($r) => str_contains((string) ($r['name'] ?? ''), LQ_TAG));

    expect($nossos->pluck('name')->all())->toBe(['Api Nossa '.LQ_TAG]);
    // Formato da API preservado: as 4 chaves de nome continuam no payload.
    expect($nossos->first())->toHaveKeys(['prefix', 'first_name', 'middle_name', 'last_name'])
        ->and($nossos->first()['first_name'])->toBe('Api Nossa '.LQ_TAG);
});
