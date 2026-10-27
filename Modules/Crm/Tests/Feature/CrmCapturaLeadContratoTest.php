<?php
// @covers-us US-CRM-096

declare(strict_types=1);

use App\Business;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Crm\Entities\FormularioCaptura;

uses(Tests\TestCase::class);

/**
 * Captura pública de lead com origem e UTM (`POST /api/crm/captura/{chave}`) — US-CRM-096.
 *
 * Âncora: SPEC Crm US-CRM-096 (critérios 1-7). Prova a porta pública: flag, chave, empresa
 * resolvida só pela chave, anti-robô, limite de requisições e o negócio nascendo no funil
 * com a origem preenchida.
 *
 * Tenant 98 (fictício, ADR 0358) e 99 como a outra empresa. NUNCA biz=4.
 * ⚠️ SKIP em SQLite: leia assertions, não "0 failed" (LC-13).
 */
const CAP_TAG = '[cap93]';
const CAP_BIZ = 98;
const CAP_OUTRO = 99;

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: crm_deals/contacts requerem schema MySQL.');
    }
    foreach (['business', 'users', 'contacts', 'crm_deals', 'crm_formularios_captura'] as $t) {
        if (! Schema::hasTable($t)) {
            $this->markTestSkipped("Schema incompleto — tabela {$t} ausente.");
        }
    }
    capLimpa();
    config(['crm.captura_lead_enabled' => true]);
});

afterEach(fn () => capLimpa());

function capLimpa(): void
{
    DB::table('crm_deals')->where('titulo', 'like', '%'.CAP_TAG.'%')->delete();
    DB::table('contacts')->where('name', 'like', '%'.CAP_TAG.'%')->delete();
    DB::table('crm_formularios_captura')->where('nome', 'like', '%'.CAP_TAG.'%')->delete();
}

function capEmpresa(int $biz): int
{
    $dono = (int) DB::table('users')->min('id');
    if (! Business::whereKey($biz)->exists()) {
        (new Business)->forceFill(['id' => $biz, 'name' => 'Tenant fictício crm '.$biz, 'currency_id' => 1, 'owner_id' => $dono])->save();
    }

    return $dono;
}

function capForm(int $biz = CAP_BIZ, ?string $obrigado = null): FormularioCaptura
{
    $dono = capEmpresa($biz);

    return FormularioCaptura::query()->create([
        'business_id' => $biz, 'chave' => FormularioCaptura::novaChave(), 'nome' => 'Site '.CAP_TAG,
        'owner_user_id' => $dono, 'obrigado_url' => $obrigado, 'ativo' => true,
    ]);
}

function capEnviar(FormularioCaptura $f, array $extra = []): \Illuminate\Testing\TestResponse
{
    return test()->postJson('/api/crm/captura/'.$f->chave, array_merge([
        'nome' => 'Ana Lead '.CAP_TAG, 'email' => 'ana.cap93@example.test', 'telefone' => '(48) 99999-0001',
        'mensagem' => 'Quero orçamento', 'utm_source' => 'google', 'utm_medium' => 'cpc', 'utm_campaign' => 'outubro',
    ], $extra));
}

it('UC-CAP-01 · flag desligada e chave inexistente respondem 404 sem gravar nada', function () {
    $f = capForm();
    config(['crm.captura_lead_enabled' => false]);
    capEnviar($f)->assertNotFound();

    config(['crm.captura_lead_enabled' => true]);
    test()->postJson('/api/crm/captura/'.str_repeat('a', 40), ['nome' => 'X '.CAP_TAG, 'email' => 'x@example.test'])->assertNotFound();

    expect(DB::table('crm_deals')->where('titulo', 'like', '%'.CAP_TAG.'%')->count())->toBe(0);
});

it('UC-CAP-02 · lead entra no funil da empresa da chave, em Lead, com origem e UTM', function () {
    $f = capForm();
    $r = capEnviar($f)->assertOk();

    // A resposta não devolve nada da empresa, do contato nem do negócio.
    expect($r->json())->toBe(['ok' => true]);

    $deal = DB::table('crm_deals')->where('titulo', 'like', '%'.CAP_TAG.'%')->first();
    expect($deal)->not->toBeNull();
    expect((int) $deal->business_id)->toBe(CAP_BIZ);
    expect($deal->stage)->toBe('lead');
    expect($deal->origem)->toBe('formulario');
    expect([$deal->utm_source, $deal->utm_medium, $deal->utm_campaign, $deal->utm_term])->toBe(['google', 'cpc', 'outubro', null]);
    expect((int) $deal->owner_user_id)->toBe((int) $f->owner_user_id);

    $contato = DB::table('contacts')->where('id', $deal->contact_id)->first();
    expect((int) $contato->business_id)->toBe(CAP_BIZ);
    expect($contato->type)->toBe('customer');
    expect($contato->mobile)->toBe('48999990001');
});

it('UC-CAP-03 · reaproveita o cliente da MESMA empresa e nunca o de outra', function () {
    $dono = capEmpresa(CAP_OUTRO);
    $alheio = DB::table('contacts')->insertGetId([
        'business_id' => CAP_OUTRO, 'type' => 'customer', 'name' => 'Alheio '.CAP_TAG, 'email' => 'ana.cap93@example.test',
        'mobile' => '0', 'contact_id' => 'C'.random_int(100000, 999999), 'created_by' => $dono, 'created_at' => now(), 'updated_at' => now(),
    ]);

    $f = capForm();
    capEnviar($f)->assertOk();
    $primeiro = (int) DB::table('crm_deals')->where('titulo', 'like', '%'.CAP_TAG.'%')->value('contact_id');
    expect($primeiro)->not->toBe((int) $alheio);

    capEnviar($f, ['telefone' => null])->assertOk();
    $contatos = DB::table('crm_deals')->where('titulo', 'like', '%'.CAP_TAG.'%')->pluck('contact_id')->map(fn ($v) => (int) $v)->unique()->values()->all();
    expect($contatos)->toBe([$primeiro]);
});

it('UC-CAP-04 · campo-armadilha preenchido responde sucesso e não grava', function () {
    $f = capForm();
    capEnviar($f, ['website' => 'http://spam.test'])->assertOk()->assertExactJson(['ok' => true]);

    expect(DB::table('crm_deals')->where('titulo', 'like', '%'.CAP_TAG.'%')->count())->toBe(0);
    expect(DB::table('contacts')->where('name', 'like', '%'.CAP_TAG.'%')->count())->toBe(0);
});

it('UC-CAP-05 · sem e-mail e sem telefone é recusado (422) e não grava', function () {
    $f = capForm();
    capEnviar($f, ['email' => null, 'telefone' => null])->assertStatus(422)->assertJsonValidationErrors(['email', 'telefone'], 'erros');

    expect(DB::table('crm_deals')->where('titulo', 'like', '%'.CAP_TAG.'%')->count())->toBe(0);
});

it('UC-CAP-06 · formulário com página de agradecimento redireciona o navegador para ela', function () {
    $f = capForm(CAP_BIZ, 'https://cliente.example.test/obrigado');
    test()->post('/api/crm/captura/'.$f->chave, ['nome' => 'Bia '.CAP_TAG, 'email' => 'bia.cap93@example.test'])
        ->assertStatus(303)->assertRedirect('https://cliente.example.test/obrigado');

    expect(DB::table('crm_deals')->where('titulo', 'like', '%'.CAP_TAG.'%')->count())->toBe(1);
});

it('UC-CAP-07 · a partir do 11º envio no minuto o IP recebe 429', function () {
    $f = capForm();
    foreach (range(1, 10) as $i) {
        capEnviar($f, ['email' => "lote{$i}.cap93@example.test", 'telefone' => null])->assertOk();
    }
    capEnviar($f, ['email' => 'lote11.cap93@example.test', 'telefone' => null])->assertStatus(429);

    expect(DB::table('crm_deals')->where('titulo', 'like', '%'.CAP_TAG.'%')->count())->toBe(10);
});
