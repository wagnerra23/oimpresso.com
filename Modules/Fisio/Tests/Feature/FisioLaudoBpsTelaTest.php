<?php

declare(strict_types=1);
// @covers-us US-FISIO-060
// Cobre UC-FBP-15 (memory/requisitos/Fisio/_telas/laudo-biopsicossocial.casos.md).

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Modules\Fisio\Tests\Support\FisioFixtures as F;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * Cartão do laudo biopsicossocial na ficha do paciente: o catálogo da matriz vai junto (estático),
 * os laudos chegam por prop deferida em grupo próprio, com formulário, pontuação e equipe.
 * Tenant 98 contra 99. ⚠️ SKIP em SQLite (LC-13).
 */
beforeEach(function () {
    if ($motivo = F::motivoDeSkip(['fisio_laudos_bps', 'fisio_laudos_bps_equipe', 'fisio_settings'])) {
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

it('UC-FBP-15 · a ficha recebe o catálogo da matriz e os laudos deferidos com pontuação e equipe; a 99 não vê', function () {
    $versao = app(\App\Http\Middleware\HandleInertiaRequests::class)->version(request());
    $r = $this->get("/fisio/pacientes/{$this->p->id}", ['X-Inertia' => 'true', 'X-Requested-With' => 'XMLHttpRequest', 'X-Inertia-Version' => (string) $versao])->assertOk();
    expect($r->json('props'))->not->toHaveKey('laudos_bps')
        ->and($r->json('deferredProps.laudos_bps'))->toContain('laudos_bps')
        ->and($r->json('props.laudo_bps_dominios'))->toHaveCount(8)
        ->and($r->json('props.laudo_bps_dominios.3.itens'))->toHaveKey('3.4');

    $id = (int) $this->postJson("/fisio/pacientes/{$this->p->id}/laudos-bps")->assertCreated()->json('id');
    $this->putJson("/fisio/laudos-bps/{$id}", ['matriz' => ['1.1' => 100], 'local' => 'Florianópolis'])->assertOk();
    $this->postJson("/fisio/laudos-bps/{$id}/equipe", ['area' => 'fisioterapia_to', 'assinatura' => 'sistema'])->assertCreated();

    $laudos = F::props($this, $this->u98, "/fisio/pacientes/{$this->p->id}", 'Fisio/Pacientes/Show', 'laudos_bps');
    expect($laudos)->toHaveCount(1)
        ->and($laudos[0]['id'])->toBe($id)
        ->and($laudos[0]['finalizado_em'])->toBeNull()
        ->and($laudos[0]['dados']['local'])->toBe('Florianópolis')
        ->and($laudos[0]['pontuacao']['faltando'])->toHaveCount(29)
        ->and($laudos[0]['equipe'][0]['registro'])->toBe(F::CREFITO);

    F::como($this, $this->u99);
    $this->get("/fisio/pacientes/{$this->p->id}")->assertNotFound();
});
