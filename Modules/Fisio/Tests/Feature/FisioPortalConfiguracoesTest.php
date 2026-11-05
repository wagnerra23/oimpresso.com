<?php

declare(strict_types=1);
// @covers-us US-FISIO-007
// Cobre UC-FPP-50, UC-FPP-51 (Fisio/Public/Portal.casos.md).

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Modules\Fisio\Services\Consentimentos;
use Modules\Fisio\Services\Whatsapp\EnvioWhatsappFisio;
use Modules\Fisio\Tests\Support\FisioFixtures as F;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * Configurações do paciente no portal (protótipo PacienteConfig.dc.html, Lote 5): a tela mostra o
 * estado REAL de cada autorização (vem do servidor na etapa Hoje) e cada chave muda só a própria
 * finalidade — e a mudança vale para o envio de verdade (o WhatsApp para de sair).
 * Tenant 98 contra 99. Nunca biz=4. ⚠️ SKIP em SQLite (LC-13).
 */
beforeEach(function () {
    if ($motivo = F::motivoDeSkip()) {
        $this->markTestSkipped($motivo);
    }
    F::configurar();
    Carbon::setTestNow('2026-10-14 09:00:00');
    $this->b98 = (int) $this->seededTenant()->id;
    $this->u98 = $this->usuarioComPermissoes([F::PERM_ACESSO, F::PERM_EDITAR], $this->seededTenant());
    F::pacote([$this->b98]);
    $this->p = F::paciente($this->b98, $this->u98->id);
    F::programa($this->p, [F::exercicio(null, 'Ponte')], $this->u98->id);
});

afterEach(fn () => Carbon::setTestNow());

it('UC-FPP-50 · a etapa Hoje traz o estado de cada autorização, que a tela de Configurações mostra', function () {
    $token = F::portalPronto($this, $this->p);
    $props = F::portalProps($this, $token);

    expect($props['etapa'])->toBe('hoje')
        ->and($props['consentimentos']['teleatendimento'])->toBeTrue()
        ->and($props['consentimentos']['whatsapp'])->toBeFalse()
        ->and($props['consentimentos']['camera'])->toBeFalse();

    $this->postJson('/fisio/p/'.$token.'/consentimentos', ['purpose' => 'camera', 'aceito' => true])->assertCreated();
    expect(F::portalProps($this, $token)['consentimentos']['camera'])->toBeTrue();
});

it('UC-FPP-51 · desligar "Lembretes no WhatsApp" pela tela corta o envio; religar volta', function () {
    $token = F::portalPronto($this, $this->p);
    $envio = app(EnvioWhatsappFisio::class);

    $this->postJson('/fisio/p/'.$token.'/consentimentos', ['purpose' => 'whatsapp', 'aceito' => true])->assertCreated();
    expect($envio->consentimentoVigente($this->p))->toBeTrue();

    $this->deleteJson('/fisio/p/'.$token.'/consentimentos/whatsapp')->assertOk();
    expect($envio->consentimentoVigente($this->p))->toBeFalse()
        ->and(app(Consentimentos::class)->vigente($this->p, Consentimentos::TELEATENDIMENTO))->toBeTrue();

    $this->postJson('/fisio/p/'.$token.'/consentimentos', ['purpose' => 'whatsapp', 'aceito' => true])->assertCreated();
    expect($envio->consentimentoVigente($this->p))->toBeTrue();
});

it('UC-FPP-51 · o link de um paciente não muda a autorização de paciente de outra clínica', function () {
    $b99 = (int) $this->seededSupportClientTenant()->id;
    $u99 = $this->usuarioComPermissoes([F::PERM_ACESSO], $this->seededSupportClientTenant());
    F::pacote([$this->b98, $b99]);
    $p99 = F::paciente($b99, $u99->id);
    F::consentir($p99, 'whatsapp');

    $token = F::portalPronto($this, $this->p);
    $this->deleteJson('/fisio/p/'.$token.'/consentimentos/whatsapp')->assertOk();

    expect(app(EnvioWhatsappFisio::class)->consentimentoVigente($p99))->toBeTrue();
});
