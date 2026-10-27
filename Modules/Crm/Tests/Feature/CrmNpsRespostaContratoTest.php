<?php
// @covers-us US-CRM-097

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * Resposta pública do NPS (`/nps/{token}`) — US-CRM-097 · Responder.casos.md (UC-NPSR-01..03).
 *
 * Tenant 98 (fictício, ADR 0358). Nunca biz=4. A linha da pesquisa é inserida direto (o envio
 * já é provado em CrmNpsContratoTest); aqui se prova a porta pública.
 * Não roda local (proibicoes.md). Lane: verticais-pest.yml (MySQL).
 */
function npsrPesquisa(int $biz): string
{
    $token = Str::lower(Str::random(40));
    DB::table('crm_nps_pesquisas')->insert([
        'business_id' => $biz, 'contact_id' => 1, 'origem_tipo' => 'venda', 'origem_id' => random_int(900000000, 999999999),
        'token' => $token, 'status' => 'enviado', 'enviada_em' => now(), 'created_at' => now(), 'updated_at' => now(),
    ]);

    return $token;
}

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite' || ! Schema::hasTable('crm_nps_pesquisas')) {
        $this->markTestSkipped('crm_nps_pesquisas ausente — roda na lane MySQL / CT 100.');
    }
    $this->bizId = (int) $this->seededTenant()->id;
    config(['crm.nps_enabled' => true]);
});

it('UC-NPSR-01 · flag desligada ou token desconhecido dão 404 e não gravam', function () {
    $token = npsrPesquisa($this->bizId);

    config(['crm.nps_enabled' => false]);
    $this->get("/nps/{$token}")->assertNotFound();
    $this->post("/nps/{$token}", ['nota' => 9])->assertNotFound();

    config(['crm.nps_enabled' => true]);
    $this->get('/nps/'.str_repeat('z', 40))->assertNotFound();
    $this->post('/nps/'.str_repeat('z', 40), ['nota' => 9])->assertNotFound();

    expect(DB::table('crm_nps_pesquisas')->where('token', $token)->value('nota'))->toBeNull();
});

it('UC-NPSR-02 · abrir o link entrega só o nome da empresa e a nota ainda vazia', function () {
    $token = npsrPesquisa($this->bizId);
    $empresa = (string) DB::table('business')->where('id', $this->bizId)->value('name');

    $this->get("/nps/{$token}")->assertOk()->assertInertia(fn (AssertableInertia $p) => $p
        ->component('Crm/Nps/Responder', false)
        ->where('token', $token)
        ->where('empresa', $empresa)
        ->where('nota', null)
        ->missing('contact_id')
        ->missing('cliente')
        ->missing('origem_id'));
});

it('UC-NPSR-03 · enviar grava a primeira nota, recusa fora de 0-10 e não troca depois', function () {
    $token = npsrPesquisa($this->bizId);

    $this->post("/nps/{$token}", ['nota' => 11])->assertSessionHasErrors('nota');
    expect(DB::table('crm_nps_pesquisas')->where('token', $token)->value('nota'))->toBeNull();

    $this->post("/nps/{$token}", ['nota' => 9, 'comentario' => 'Arte ótima'])->assertRedirect("/nps/{$token}");
    $this->post("/nps/{$token}", ['nota' => 0])->assertRedirect("/nps/{$token}");

    $linha = DB::table('crm_nps_pesquisas')->where('token', $token)->first();
    expect((int) $linha->nota)->toBe(9)->and($linha->comentario)->toBe('Arte ótima')->and($linha->status)->toBe('respondido');

    $this->get("/nps/{$token}")->assertOk()->assertInertia(fn (AssertableInertia $p) => $p->component('Crm/Nps/Responder', false)->where('nota', 9));
});
