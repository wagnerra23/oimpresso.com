<?php

declare(strict_types=1);
// Etapa 07 P-05 · cobrança da avaliação do estudo pelo portal e pelo WhatsApp (UC-FPP-59..61).

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Modules\Fisio\Jobs\CobrarAvaliacaoDoEstudoJob;
use Modules\Fisio\Services\Pesquisa\EstudoService;
use Modules\Fisio\Tests\Support\FisioFixtures as F;
use Modules\Whatsapp\Jobs\SendWhatsappMessageJob;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * O que vence é avisado; quem responde é a clínica, na sessão, enquanto o texto validado do
 * questionário não estiver no catálogo (ADR 0437 §8). O aviso pelo WhatsApp respeita o
 * consentimento de WhatsApp e sai uma vez por momento. Tenant 98 contra 99. ⚠️ SKIP em SQLite.
 */
beforeEach(function () {
    if ($motivo = F::motivoDeSkip(['fisio_studies', 'fisio_study_participants', 'whatsapp_business_phones'])) {
        $this->markTestSkipped($motivo);
    }
    F::configurar();
    config([
        'fisio.whatsapp.teto_por_hora' => 30,
        'fisio.whatsapp.intervalo_min_segundos' => 20,
        'fisio.whatsapp.intervalo_max_segundos' => 90,
        'fisio.whatsapp.silencio' => ['inicio' => '21:00', 'fim' => '07:00'],
    ]);
    Carbon::setTestNow('2026-11-01 09:00:00');
    $this->b98 = (int) $this->seededTenant()->id;
    $this->b99 = (int) $this->seededSupportClientTenant()->id;
    F::pacote([$this->b98, $this->b99]);
    $this->u98 = $this->usuarioComPermissoes([F::PERM_ACESSO, F::PERM_EDITAR], $this->seededTenant());
    F::telefoneWhatsapp($this->b98);
    $svc = app(EstudoService::class);
    $dados = ['title' => 'Fibro', 'cep_opinion_number' => '1', 'protocol_version' => 'p1', 'tcle_version' => 't1', 'starts_on' => '2026-11-01'];
    $this->estudo = $svc->criar($this->b98, $dados, [
        ['label' => 'Entrada', 'day_offset' => 0, 'window_days' => 3, 'instruments' => ['eva']],
        ['label' => '4 semanas', 'day_offset' => 28, 'window_days' => 7, 'instruments' => ['eva']],
    ], null);
    $this->p = F::paciente($this->b98, $this->u98->id);
    F::programa($this->p, [F::exercicio(null, 'Ponte')], $this->u98->id);
    $svc->incluir($this->estudo, $this->p, null, null);
    $this->fora = F::paciente($this->b98, $this->u98->id);
    F::programa($this->fora, [F::exercicio(null, 'Ponte')], $this->u98->id);
});

afterEach(fn () => Carbon::setTestNow());

it('UC-FPP-59 · o portal avisa só a avaliação aberta do participante e some quando a clínica aplica', function () {
    $token = F::portalPronto($this, $this->p);
    $props = F::portalProps($this, $token);
    expect($props['avaliacoes_estudo'])->toHaveCount(1)
        ->and($props['avaliacoes_estudo'][0]['momento'])->toBe('Entrada')
        ->and($props['avaliacoes_estudo'][0]['ate'])->toBe('2026-11-04')
        ->and($props['avaliacoes_estudo'][0]['questionarios'])->toHaveCount(1);

    // A clínica aplica a EVA: o aviso some.
    DB::table('fisio_questionnaires')->insert(['business_id' => $this->b98, 'patient_id' => $this->p->id, 'instrument' => 'eva',
        'answers' => '{}', 'score' => 5, 'answered_at' => '2026-11-01 10:00:00', 'created_at' => now(), 'updated_at' => now()]);
    expect(F::portalProps($this, $token)['avaliacoes_estudo'])->toBe([]);

    // Quem não está no estudo não vê aviso nenhum.
    expect(F::portalProps($this, F::portalPronto($this, $this->fora))['avaliacoes_estudo'])->toBe([]);
});

it('UC-FPP-60 · o WhatsApp avisa uma vez por momento e só com o consentimento de WhatsApp', function () {
    Bus::fake([SendWhatsappMessageJob::class]);

    // Sem consentimento de WhatsApp: nada sai.
    (new CobrarAvaliacaoDoEstudoJob($this->b98))->handle();
    Bus::assertNotDispatched(SendWhatsappMessageJob::class);

    F::consentir($this->p, 'whatsapp');
    (new CobrarAvaliacaoDoEstudoJob($this->b98))->handle();
    Bus::assertDispatchedTimes(SendWhatsappMessageJob::class, 1);
    Bus::assertDispatched(SendWhatsappMessageJob::class, fn ($job) => str_contains($job->payload['body'], 'Entrada') && str_contains($job->payload['body'], 'próxima sessão'));

    // No dia seguinte, o mesmo momento não é avisado de novo.
    Carbon::setTestNow('2026-11-02 09:00:00');
    (new CobrarAvaliacaoDoEstudoJob($this->b98))->handle();
    Bus::assertDispatchedTimes(SendWhatsappMessageJob::class, 1);

    // Na janela das 4 semanas, sai o aviso do novo momento.
    Carbon::setTestNow('2026-11-24 09:00:00');
    (new CobrarAvaliacaoDoEstudoJob($this->b98))->handle();
    Bus::assertDispatchedTimes(SendWhatsappMessageJob::class, 2);
});

it('UC-FPP-61 · o job de uma empresa não avisa participante de outra [T0]', function () {
    Bus::fake([SendWhatsappMessageJob::class]);
    F::consentir($this->p, 'whatsapp');

    (new CobrarAvaliacaoDoEstudoJob($this->b99))->handle();
    Bus::assertNotDispatched(SendWhatsappMessageJob::class);
});
