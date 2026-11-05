<?php

declare(strict_types=1);
// @covers-us US-FISIO-009
// Cobre UC-FCW-01..06 (Fisio/Configuracoes/Whatsapp.casos.md).

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;
use Modules\Fisio\Services\ConfiguracaoClinica;
use Modules\Fisio\Services\Whatsapp\CanalDaClinica;
use Modules\Fisio\Services\Whatsapp\EnvioWhatsappFisio;
use Modules\Fisio\Tests\Support\FisioFixtures as F;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * Configurações › WhatsApp: a clínica escolhe o canal e a escolha MUDA o envio real — mede-se
 * `EnvioWhatsappFisio::canalAtivo`, que o job e o botão "Enviar pelo WhatsApp" consultam, não só
 * o que ficou gravado. Número e driver são do módulo Whatsapp (fixture `telefoneWhatsapp`).
 *
 * Tenant 98 contra 99. Nunca biz=4. ⚠️ SKIP em SQLite (LC-13).
 */
beforeEach(function () {
    if ($motivo = F::motivoDeSkip(['fisio_settings', 'whatsapp_business_phones'])) {
        $this->markTestSkipped($motivo);
    }
    F::configurar();
    $this->b98 = (int) $this->seededTenant()->id;
    $this->b99 = (int) $this->seededSupportClientTenant()->id;
    $this->u98 = $this->usuarioComPermissoes([F::PERM_ACESSO, F::PERM_EDITAR], $this->seededTenant());
    $this->u99 = $this->usuarioComPermissoes([F::PERM_ACESSO, F::PERM_EDITAR], $this->seededSupportClientTenant());
    F::pacote([$this->b98, $this->b99]);
    $this->fone98 = F::telefoneWhatsapp($this->b98); // meta_cloud, saída padrão
    $this->corpo = ['canal' => 'proprio', 'teto_por_hora' => 20, 'silencio' => ['inicio' => '22:00', 'fim' => '07:30']];
});

function fcwAtivo(int $biz): bool
{
    return app(EnvioWhatsappFisio::class)->canalAtivo($biz) !== null;
}

it('UC-FCW-01 · a tela abre com o estado do número do módulo e sem escolha gravada', function () {
    F::como($this, $this->u98);
    $this->get('/fisio/configuracoes/whatsapp')->assertOk()->assertInertia(fn (AssertableInertia $p) => $p
        ->component('Fisio/Configuracoes/Whatsapp', false)
        ->where('canal', null)
        ->where('numero.oficial', true)
        ->where('ativo_agora', true));
});

it('UC-FCW-02 · "Pelo meu WhatsApp" desliga o envio automático mesmo com número conectado', function () {
    expect(fcwAtivo($this->b98))->toBeTrue();
    F::como($this, $this->u98);
    $this->put('/fisio/configuracoes/whatsapp', $this->corpo)->assertSessionHasNoErrors();

    expect(fcwAtivo($this->b98))->toBeFalse();
    expect(app(CanalDaClinica::class)->ritmo($this->b98))
        ->toBe(['teto' => 20, 'silencio_inicio' => '22:00', 'silencio_fim' => '07:30']);
});

it('UC-FCW-03 · QR sem o aceite do risco não grava nada; com o aceite grava quem, quando e a versão', function () {
    DB::table('whatsapp_business_phones')->where('id', $this->fone98)->update(['driver' => 'whatsmeow']);
    F::como($this, $this->u98);

    $this->put('/fisio/configuracoes/whatsapp', ['canal' => 'qr'] + $this->corpo)->assertSessionHasErrors('aceite_qr');
    expect(app(ConfiguracaoClinica::class)->ler($this->b98, 'whatsapp.canal'))->toBeNull();

    $this->put('/fisio/configuracoes/whatsapp', ['canal' => 'qr', 'aceite_qr' => true] + $this->corpo)->assertSessionHasNoErrors();
    $aceite = app(CanalDaClinica::class)->aceiteQr($this->b98);
    expect($aceite['user_id'])->toBe((int) $this->u98->id)
        ->and($aceite['versao'])->toBe(CanalDaClinica::TERMO_QR_VERSAO)
        ->and(fcwAtivo($this->b98))->toBeTrue();
});

it('UC-FCW-04 · "oficial" com o número do módulo conectado por QR não envia automático', function () {
    DB::table('whatsapp_business_phones')->where('id', $this->fone98)->update(['driver' => 'whatsmeow']);
    F::como($this, $this->u98);
    $this->put('/fisio/configuracoes/whatsapp', ['canal' => 'oficial'] + $this->corpo)->assertSessionHasNoErrors();
    expect(fcwAtivo($this->b98))->toBeFalse();

    DB::table('whatsapp_business_phones')->where('id', $this->fone98)->update(['driver' => 'meta_cloud']);
    expect(fcwAtivo($this->b98))->toBeTrue();
});

it('UC-FCW-05 · sem fisio.editar não salva; valores fora do limite são recusados', function () {
    $leitor = $this->usuarioComPermissoes([F::PERM_ACESSO], $this->seededTenant());
    F::como($this, $leitor);
    $this->put('/fisio/configuracoes/whatsapp', $this->corpo)->assertForbidden();

    F::como($this, $this->u98);
    $this->put('/fisio/configuracoes/whatsapp', ['teto_por_hora' => 500] + $this->corpo)->assertSessionHasErrors('teto_por_hora');
    $this->put('/fisio/configuracoes/whatsapp', ['canal' => 'zapi'] + $this->corpo)->assertSessionHasErrors('canal');
    expect(app(ConfiguracaoClinica::class)->ler($this->b98, 'whatsapp.canal'))->toBeNull();
});

it('UC-FCW-06 · a escolha da 98 não muda o envio da 99', function () {
    $fone99 = F::telefoneWhatsapp($this->b99);
    F::como($this, $this->u98);
    $this->put('/fisio/configuracoes/whatsapp', $this->corpo)->assertSessionHasNoErrors();

    expect(fcwAtivo($this->b98))->toBeFalse()
        ->and(fcwAtivo($this->b99))->toBeTrue()
        ->and(app(ConfiguracaoClinica::class)->ler($this->b99, 'whatsapp.canal'))->toBeNull()
        ->and($fone99)->toBeGreaterThan(0);
});
