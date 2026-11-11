<?php

declare(strict_types=1);
// @covers-us US-FISIO-060
// Cobre UC-FBP-12 (memory/requisitos/Fisio/_telas/laudo-biopsicossocial.casos.md).

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Modules\Fisio\Tests\Support\FisioFixtures as F;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * Cartão do laudo biopsicossocial na ficha do paciente: a prop `laudos_bps` é deferida (grupo
 * próprio) e traz, por inciso, a seção registrada e o rascunho aberto. Tenant 98 contra 99.
 * ⚠️ SKIP em SQLite (LC-13).
 */
beforeEach(function () {
    if ($motivo = F::motivoDeSkip(['fisio_laudos_bps', 'fisio_laudos_bps_secoes', 'fisio_settings'])) {
        $this->markTestSkipped($motivo);
    }
    F::configurar();
    $this->b98 = (int) $this->seededTenant()->id;
    $this->u98 = $this->usuarioComPermissoes([F::PERM_ACESSO, F::PERM_EDITAR], $this->seededTenant());
    $this->u99 = $this->usuarioComPermissoes([F::PERM_ACESSO, F::PERM_EDITAR], $this->seededSupportClientTenant());
    F::pacote([$this->b98, (int) $this->seededSupportClientTenant()->id]);
    $this->p = F::paciente($this->b98, $this->u98->id);
    F::como($this, $this->u98);
});

it('UC-FBP-12 · a ficha recebe os laudos deferidos, por inciso, com registrada e rascunho; a 99 não vê', function () {
    $versao = app(\App\Http\Middleware\HandleInertiaRequests::class)->version(request());
    $r = $this->get("/fisio/pacientes/{$this->p->id}", ['X-Inertia' => 'true', 'X-Requested-With' => 'XMLHttpRequest', 'X-Inertia-Version' => (string) $versao])->assertOk();
    expect($r->json('props'))->not->toHaveKey('laudos_bps')
        ->and($r->json('deferredProps.laudos_bps'))->toContain('laudos_bps');

    $laudoId = (int) $this->postJson("/fisio/pacientes/{$this->p->id}/laudos-bps")->assertCreated()->json('id');
    $this->putJson("/fisio/laudos-bps/{$laudoId}/secoes/atividades", ['texto' => 'Texto registrado', 'assinatura' => 'sistema'])->assertOk();
    $this->postJson("/fisio/laudos-bps/{$laudoId}/secoes/atividades/registrar")->assertOk();
    $this->putJson("/fisio/laudos-bps/{$laudoId}/secoes/fatores", ['texto' => 'Rascunho aberto', 'assinatura' => 'externa'])->assertOk();

    $laudos = F::props($this, $this->u98, "/fisio/pacientes/{$this->p->id}", 'Fisio/Pacientes/Show', 'laudos_bps');
    $secoes = collect($laudos[0]['secoes'])->keyBy('item');
    expect($laudos)->toHaveCount(1)
        ->and(array_column($laudos[0]['secoes'], 'inciso'))->toBe(['I', 'II', 'III', 'IV'])
        ->and($secoes['atividades']['registrada']['registro'])->toBe(F::CREFITO)
        ->and($secoes['atividades']['rascunho'])->toBeNull()
        ->and($secoes['fatores']['registrada'])->toBeNull()
        ->and($secoes['fatores']['rascunho']['texto'])->toBe('Rascunho aberto')
        ->and($secoes['impedimentos']['registrada'])->toBeNull();

    F::como($this, $this->u99);
    $this->get("/fisio/pacientes/{$this->p->id}")->assertNotFound();
});
