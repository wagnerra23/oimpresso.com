<?php

declare(strict_types=1);
// @covers-us US-FISIO-050
// Cobre UC-FOB-01..06 (Fisio/Onboarding.casos.md).

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;
use Modules\Fisio\Services\ConfiguracaoClinica;
use Modules\Fisio\Services\Whatsapp\CanalDaClinica;
use Modules\Fisio\Tests\Support\FisioFixtures as F;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * Primeira configuração da clínica: cada passo grava só o dele em `fisio_settings`, o perfil é do
 * profissional (não da clínica), o canal segue a regra de Configurações › WhatsApp e nada vaza entre
 * empresas. Tenant 98 contra 99. Nunca biz=4. ⚠️ SKIP em SQLite (LC-13).
 */
beforeEach(function () {
    if ($motivo = F::motivoDeSkip(['fisio_settings'])) {
        $this->markTestSkipped($motivo);
    }
    F::configurar();
    $this->b98 = (int) $this->seededTenant()->id;
    $this->b99 = (int) $this->seededSupportClientTenant()->id;
    $this->u98 = $this->usuarioComPermissoes([F::PERM_ACESSO, F::PERM_EDITAR], $this->seededTenant());
    $this->u99 = $this->usuarioComPermissoes([F::PERM_ACESSO, F::PERM_EDITAR], $this->seededSupportClientTenant());
    F::pacote([$this->b98, $this->b99]);
    $this->cfg = app(ConfiguracaoClinica::class);
});

function fobProtocolo(?int $biz, string $nome): int
{
    return (int) DB::table('fisio_protocols')->insertGetId([
        'business_id' => $biz, 'name' => $nome, 'items' => '[]', 'created_at' => now(), 'updated_at' => now(),
    ]);
}

it('UC-FOB-01 · a tela abre no passo 1 com os protocolos visíveis para a clínica', function () {
    $nosso = fobProtocolo($this->b98, 'Protocolo da 98');
    $deles = fobProtocolo($this->b99, 'Protocolo da 99');
    F::como($this, $this->u98);

    $this->get('/fisio/onboarding')->assertOk()->assertInertia(fn (AssertableInertia $p) => $p
        ->component('Fisio/Onboarding', false)
        ->where('concluido', false)
        ->where('protocolos', fn ($lista) => collect($lista)->pluck('id')->contains($nosso) && ! collect($lista)->pluck('id')->contains($deles)));
});

it('UC-FOB-02 · o perfil é do profissional; a forma de atendimento é da clínica', function () {
    F::como($this, $this->u98);
    $this->put('/fisio/onboarding/perfil', ['nome_exibicao' => 'Profissional Teste', 'crefito' => F::CREFITO])->assertSessionHasNoErrors();
    $this->put('/fisio/onboarding/atendimento', ['atendimento' => 'pj'])->assertSessionHasNoErrors();

    expect($this->cfg->ler($this->b98, 'perfil.crefito', null, (int) $this->u98->id))->toBe(F::CREFITO)
        ->and($this->cfg->ler($this->b98, 'perfil.crefito'))->toBeNull()
        ->and($this->cfg->ler($this->b98, 'atendimento.tipo'))->toBe('pj');
    $this->put('/fisio/onboarding/atendimento', ['atendimento' => 'mei'])->assertSessionHasErrors('atendimento');
});

it('UC-FOB-03 · canal QR sem o aceite não grava; com o aceite grava canal e aceite', function () {
    F::como($this, $this->u98);
    $this->put('/fisio/onboarding/canal', ['canal' => 'qr'])->assertSessionHasErrors('aceite_qr');
    expect($this->cfg->ler($this->b98, 'whatsapp.canal'))->toBeNull();

    $this->put('/fisio/onboarding/canal', ['canal' => 'qr', 'aceite_qr' => true])->assertSessionHasNoErrors();
    expect($this->cfg->ler($this->b98, 'whatsapp.canal'))->toBe('qr')
        ->and(app(CanalDaClinica::class)->aceiteQr($this->b98)['user_id'])->toBe((int) $this->u98->id);
});

it('UC-FOB-04 · protocolo de outra empresa marcado é descartado', function () {
    $global = fobProtocolo(null, 'Modelo do produto');
    $nosso = fobProtocolo($this->b98, 'Protocolo da 98');
    $deles = fobProtocolo($this->b99, 'Protocolo da 99');
    F::como($this, $this->u98);

    $this->put('/fisio/onboarding/protocolos', ['favoritos' => [$global, $nosso, $deles]])->assertSessionHasNoErrors();
    $gravado = $this->cfg->ler($this->b98, 'protocolos.favoritos');
    sort($gravado);
    $esperado = [$global, $nosso];
    sort($esperado);
    expect($gravado)->toBe($esperado);
});

it('UC-FOB-05 · concluir marca a data; sem fisio.editar nada grava; passo desconhecido é 404', function () {
    $leitor = $this->usuarioComPermissoes([F::PERM_ACESSO], $this->seededTenant());
    F::como($this, $leitor);
    $this->put('/fisio/onboarding/concluir')->assertForbidden();
    expect($this->cfg->ler($this->b98, 'onboarding.concluido_em'))->toBeNull();

    F::como($this, $this->u98);
    $this->put('/fisio/onboarding/fiscal')->assertNotFound();
    $this->put('/fisio/onboarding/concluir')->assertSessionHasNoErrors();
    expect($this->cfg->ler($this->b98, 'onboarding.concluido_em'))->toBeString();
});

it('UC-FOB-06 · o que a 98 grava não aparece para a 99', function () {
    F::como($this, $this->u98);
    $this->put('/fisio/onboarding/atendimento', ['atendimento' => 'pj']);
    $this->put('/fisio/onboarding/concluir');

    F::como($this, $this->u99);
    $this->get('/fisio/onboarding')->assertOk()->assertInertia(fn (AssertableInertia $p) => $p
        ->where('atendimento', null)
        ->where('concluido', false));
});
