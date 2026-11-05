<?php

declare(strict_types=1);
// @covers-us US-FISIO-051
// Cobre UC-FPP-52..55 (Fisio/Public/Portal.casos.md).

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Modules\Fisio\Notifications\SinalDeAlertaNotification;
use Modules\Fisio\Tests\Support\FisioFixtures as F;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * Pré-anamnese do portal (Lote 5): uma resposta por paciente, só valores das listas fechadas,
 * sinal marcado avisa o profissional pelo sino (como no check-in), mesmos portões do portal.
 * Tenant 98 contra 99. Nunca biz=4. ⚠️ SKIP em SQLite (LC-13).
 */
beforeEach(function () {
    if ($motivo = F::motivoDeSkip(['fisio_pre_anamneses'])) {
        $this->markTestSkipped($motivo);
    }
    F::configurar();
    config(['fisio.red_flags' => ['febre', 'queda', 'perda_forca_pernas', 'perda_controle_esfincter', 'dormencia_regiao_intima', 'dor_noturna_intensa']]);
    Carbon::setTestNow('2026-10-14 09:00:00');
    $this->b98 = (int) $this->seededTenant()->id;
    $this->u98 = $this->usuarioComPermissoes([F::PERM_ACESSO, F::PERM_EDITAR], $this->seededTenant());
    F::pacote([$this->b98]);
    $this->p = F::paciente($this->b98, $this->u98->id);
    $this->resposta = ['regioes' => ['lombar'], 'desde' => '1_a_3_meses', 'dor' => 6, 'piora_melhora' => 'Piora sentado.', 'sinais' => []];
});

afterEach(fn () => Carbon::setTestNow());

function fpaLinhas(int $patientId): int
{
    return DB::table('fisio_pre_anamneses')->where('patient_id', $patientId)->count();
}

it('UC-FPP-52 · o Hoje oferece a pré-anamnese até o paciente responder; depois some', function () {
    $token = F::portalPronto($this, $this->p);
    $opcoes = F::portalProps($this, $token)['pre_anamnese'];
    expect($opcoes['regioes'])->toContain('lombar')
        ->and($opcoes['sinais'])->toBe(['febre', 'dormencia_regiao_intima', 'perda_controle_esfincter', 'perda_forca_pernas', 'queda']);

    $this->postJson('/fisio/p/'.$token.'/pre-anamnese', $this->resposta)->assertCreated();
    expect(F::portalProps($this, $token)['pre_anamnese'])->toBeNull()
        ->and(fpaLinhas($this->p->id))->toBe(1);
});

it('UC-FPP-53 · fora das listas é recusado; a segunda resposta não grava', function () {
    $token = F::portalPronto($this, $this->p);
    $this->postJson('/fisio/p/'.$token.'/pre-anamnese', ['regioes' => ['cotovelo']] + $this->resposta)->assertStatus(422);
    // Sinal da lista do check-in que a pré-anamnese não pergunta.
    $this->postJson('/fisio/p/'.$token.'/pre-anamnese', ['sinais' => ['dor_noturna_intensa']] + $this->resposta)->assertStatus(422);
    $this->postJson('/fisio/p/'.$token.'/pre-anamnese', ['dor' => 11] + $this->resposta)->assertStatus(422);
    expect(fpaLinhas($this->p->id))->toBe(0);

    $this->postJson('/fisio/p/'.$token.'/pre-anamnese', $this->resposta)->assertCreated();
    $this->postJson('/fisio/p/'.$token.'/pre-anamnese', ['dor' => 2] + $this->resposta)->assertStatus(409);
    expect(fpaLinhas($this->p->id))->toBe(1)
        ->and(json_decode((string) DB::table('fisio_pre_anamneses')->where('patient_id', $this->p->id)->value('body'), true)['dor'])->toBe(6);
});

it('UC-FPP-54 · sinal marcado avisa o profissional pelo sino; sem sinal, ninguém é avisado', function () {
    Notification::fake();
    F::programa($this->p, [F::exercicio(null, 'Ponte')], $this->u98->id);
    $token = F::portalPronto($this, $this->p);

    $this->postJson('/fisio/p/'.$token.'/pre-anamnese', ['sinais' => ['queda']] + $this->resposta)->assertCreated();
    Notification::assertSentTo($this->u98, SinalDeAlertaNotification::class,
        fn (SinalDeAlertaNotification $n) => $n->origem === SinalDeAlertaNotification::ORIGEM_PRE_ANAMNESE
            && $n->patientId === (int) $this->p->id && $n->redFlags === ['queda']);

    $outro = F::paciente($this->b98, $this->u98->id, ['contact_id' => F::contato($this->b98, $this->u98->id, 'Outro Paciente')]);
    Notification::fake();
    $this->postJson('/fisio/p/'.F::portalPronto($this, $outro).'/pre-anamnese', $this->resposta)->assertCreated();
    Notification::assertNothingSent();
});

it('UC-FPP-55 · sem PIN ou sem a autorização do tratamento não grava; o link grava só na clínica dele', function () {
    $g = F::emitirPortal($this->p);
    $this->postJson('/fisio/p/'.$g['token'].'/pre-anamnese', $this->resposta)->assertForbidden();
    $this->postJson('/fisio/p/'.$g['token'].'/pin', ['pin' => $g['pin']])->assertOk();
    $this->postJson('/fisio/p/'.$g['token'].'/pre-anamnese', $this->resposta)->assertForbidden();
    expect(fpaLinhas($this->p->id))->toBe(0);

    $this->postJson('/fisio/p/'.$g['token'].'/consentimentos', ['purpose' => 'teleatendimento', 'aceito' => true])->assertCreated();
    $this->postJson('/fisio/p/'.$g['token'].'/pre-anamnese', $this->resposta)->assertCreated();

    $b99 = (int) $this->seededSupportClientTenant()->id;
    expect(DB::table('fisio_pre_anamneses')->where('patient_id', $this->p->id)->value('business_id'))->toEqual($this->b98)
        ->and(DB::table('fisio_pre_anamneses')->where('business_id', $b99)->where('patient_id', $this->p->id)->exists())->toBeFalse();
});
