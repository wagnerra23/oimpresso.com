<?php

declare(strict_types=1);
// Etapa 07 P-04 · tela /fisio/pesquisa (UC-FES-07..08, Fisio/Pesquisa/Index.casos.md).

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Modules\Fisio\Entities\Patient;
use Modules\Fisio\Services\Pesquisa\EstudoService;
use Modules\Fisio\Tests\Support\FisioFixtures as F;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * Área do estudo: quem abre, o que vê e de qual clínica. As props deferidas são pedidas como o
 * navegador pede (partial reload com X-Inertia + X-Requested-With). Tenant 98 contra 99.
 * ⚠️ SKIP em SQLite (LC-13).
 */
beforeEach(function () {
    if ($motivo = F::motivoDeSkip(['fisio_studies', 'fisio_study_moments', 'fisio_study_participants'])) {
        $this->markTestSkipped($motivo);
    }
    F::configurar();
    Carbon::setTestNow('2026-11-11 09:00:00');
    $this->b98 = (int) $this->seededTenant()->id;
    $this->b99 = (int) $this->seededSupportClientTenant()->id;
    F::pacote([$this->b98, $this->b99]);
    $this->clin = $this->usuarioComPermissoes([F::PERM_ACESSO, F::PERM_EDITAR], $this->seededTenant());
    $this->pesqClinica = $this->usuarioComPermissoes([F::PERM_ACESSO, F::PERM_EDITAR, 'fisio.pesquisa'], $this->seededTenant());
    $this->pesqExterno = $this->usuarioComPermissoes([F::PERM_ACESSO, 'fisio.pesquisa'], $this->seededTenant());
    $svc = app(EstudoService::class);
    $momentos = [['label' => 'Entrada', 'day_offset' => 0, 'window_days' => 3, 'instruments' => ['eva']]];
    $dados = ['title' => 'Estudo 98', 'cep_opinion_number' => '1', 'protocol_version' => 'p1', 'tcle_version' => 't1', 'starts_on' => '2026-11-01'];
    $this->e98 = $svc->criar($this->b98, $dados, $momentos, null);
    $this->e99 = $svc->criar($this->b99, ['title' => 'Estudo 99'] + $dados, $momentos, null);
    $this->p = F::paciente($this->b98, $this->clin->id);
    $this->livre = F::paciente($this->b98, $this->clin->id);
    $svc->incluir($this->e98, Patient::withoutGlobalScopes()->findOrFail($this->p->id), null, null); // SUPERADMIN: fixture.
});

afterEach(fn () => Carbon::setTestNow());

it('UC-FES-07 · só fisio.pesquisa abre; o nome do paciente só vai para quem também atende', function () {
    F::como($this, $this->clin);
    $this->get('/fisio/pesquisa')->assertForbidden();

    $externo = F::props($this, $this->pesqExterno, '/fisio/pesquisa', 'Fisio/Pesquisa/Index', 'participantes');
    expect($externo)->toHaveCount(1)
        ->and($externo[0]['code'])->toMatch('/^P-/')
        ->and($externo[0]['nome'])->toBeNull()
        ->and(F::props($this, $this->pesqExterno, '/fisio/pesquisa', 'Fisio/Pesquisa/Index', 'pacientes'))->toBe([]);

    $clinica = F::props($this, $this->pesqClinica, '/fisio/pesquisa', 'Fisio/Pesquisa/Index', 'participantes');
    $pacientes = F::props($this, $this->pesqClinica, '/fisio/pesquisa', 'Fisio/Pesquisa/Index', 'pacientes');
    expect($clinica[0]['nome'])->not->toBeNull()
        // Quem já está no estudo não aparece para incluir de novo.
        ->and(collect($pacientes)->pluck('id')->all())->toContain($this->livre->id)->not->toContain($this->p->id);
});

it('UC-FES-08 · a 98 não lista nem abre estudo da 99', function () {
    F::como($this, $this->pesqClinica);
    $versao = app(\App\Http\Middleware\HandleInertiaRequests::class)->version(request());
    $r = $this->get('/fisio/pesquisa', ['X-Inertia' => 'true', 'X-Requested-With' => 'XMLHttpRequest', 'X-Inertia-Version' => (string) $versao])->assertOk();

    expect($r->json('component'))->toBe('Fisio/Pesquisa/Index')
        ->and(collect($r->json('props.estudos'))->pluck('id')->all())->toBe([$this->e98->id])
        ->and($r->json('props.estudo.id'))->toBe($this->e98->id);
    $this->get('/fisio/pesquisa?estudo='.$this->e99->id)->assertNotFound();
});
