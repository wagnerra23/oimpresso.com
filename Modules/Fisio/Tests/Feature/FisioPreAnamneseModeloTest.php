<?php

declare(strict_types=1);
// @covers-us US-FISIO-051
// Cobre UC-FPP-53 (Fisio/Public/Portal.casos.md): a pré-anamnese é uma só e, enviada, não muda.

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Modules\Fisio\Entities\PreAnamnese;
use Modules\Fisio\Exceptions\DadoClinicoImutavelException;
use Modules\Fisio\Services\PreAnamneseDoPortal;
use Modules\Fisio\Tests\Support\FisioFixtures as F;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * Auditoria do Fisio d10 (2026-11-05): `fisio_pre_anamneses` não tinha model nem regra de
 * imutabilidade. Agora o model `PreAnamnese` tem escopo de empresa e recusa UPDATE e DELETE.
 *
 * Tenant 98 contra 99. ⚠️ SKIP em SQLite (LC-13).
 */
beforeEach(function () {
    if ($motivo = F::motivoDeSkip(['fisio_pre_anamneses'])) {
        $this->markTestSkipped($motivo);
    }
    F::configurar();
    config(['fisio.red_flags' => ['febre', 'queda', 'perda_forca_pernas', 'perda_controle_esfincter', 'dormencia_regiao_intima']]);
    $this->b98 = (int) $this->seededTenant()->id;
    $this->b99 = (int) $this->seededSupportClientTenant()->id;
    $this->u98 = $this->usuarioComPermissoes([F::PERM_ACESSO, F::PERM_EDITAR], $this->seededTenant());
    $this->u99 = $this->usuarioComPermissoes([F::PERM_ACESSO, F::PERM_EDITAR], $this->seededSupportClientTenant());
    $this->p98 = F::paciente($this->b98, $this->u98->id);
    $this->p99 = F::paciente($this->b99, $this->u99->id);
    $resposta = ['regioes' => ['lombar'], 'desde' => '1_a_3_meses', 'dor' => 6, 'piora_melhora' => 'Piora sentado.', 'sinais' => []];
    $servico = app(PreAnamneseDoPortal::class);
    expect($servico->registrar($this->p98, $resposta))->toBeTrue()
        ->and($servico->registrar($this->p99, ['dor' => 2] + $resposta))->toBeTrue();
});

it('UC-FPP-53 · o model da pré-anamnese só mostra a da própria empresa (98 × 99)', function () {
    F::como($this, $this->u98);
    expect(PreAnamnese::query()->pluck('patient_id')->map('intval')->all())->toBe([$this->p98->id]);

    F::como($this, $this->u99);
    expect(PreAnamnese::query()->pluck('patient_id')->map('intval')->all())->toBe([$this->p99->id]);

    // Controle: as duas existem no banco.
    expect(PreAnamnese::withoutGlobalScopes() // SUPERADMIN: controle do teste, conta as duas empresas.
        ->whereIn('patient_id', [$this->p98->id, $this->p99->id])->count())->toBe(2);
});

it('UC-FPP-53 · pré-anamnese enviada não muda nem some pelo model', function () {
    F::como($this, $this->u98);
    $pre = PreAnamnese::query()->sole();
    expect($pre->body['dor'])->toBe(6);

    expect(fn () => $pre->forceFill(['body' => ['dor' => 0]])->save())->toThrow(DadoClinicoImutavelException::class);
    expect(fn () => PreAnamnese::query()->sole()->delete())->toThrow(DadoClinicoImutavelException::class);

    $linha = DB::table('fisio_pre_anamneses')->where('patient_id', $this->p98->id)->sole();
    expect(json_decode((string) $linha->body, true)['dor'])->toBe(6);
});
