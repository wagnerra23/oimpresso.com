<?php

declare(strict_types=1);
// @covers-us US-FISIO-060
// Cobre UC-FBP-03..UC-FBP-06 (memory/requisitos/Fisio/_telas/laudo-biopsicossocial.casos.md).

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Fisio\Entities\LaudoBiopsicossocial;
use Modules\Fisio\Entities\LaudoBiopsicossocialSecao as Secao;
use Modules\Fisio\Tests\Support\FisioFixtures as F;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * Escrita do laudo biopsicossocial pelas rotas: criar, rascunho por seção, registrar.
 * O carimbo (quem, quando, CREFITO) sai do servidor; o corpo não escolhe autor na assinatura
 * pelo sistema. Tenant 98 contra 99 (ADR 0358). ⚠️ SKIP em SQLite (LC-13).
 */
beforeEach(function () {
    if ($motivo = F::motivoDeSkip(['fisio_laudos_bps', 'fisio_laudos_bps_secoes', 'fisio_settings'])) {
        $this->markTestSkipped($motivo);
    }
    F::configurar();
    $this->b98 = (int) $this->seededTenant()->id;
    $this->b99 = (int) $this->seededSupportClientTenant()->id;
    $this->u98 = $this->usuarioComPermissoes([F::PERM_ACESSO, F::PERM_EDITAR], $this->seededTenant());
    $this->u99 = $this->usuarioComPermissoes([F::PERM_ACESSO, F::PERM_EDITAR], $this->seededSupportClientTenant());
    F::pacote([$this->b98, $this->b99]);
    $this->p = F::paciente($this->b98, $this->u98->id);
    F::como($this, $this->u98);
    $this->laudoId = (int) $this->postJson("/fisio/pacientes/{$this->p->id}/laudos-bps")->assertCreated()->json('id');
});

afterEach(fn () => Carbon::setTestNow());

it('UC-FBP-03 · assinatura pelo sistema: autor, CREFITO e hora vêm do servidor, não do corpo', function () {
    Carbon::setTestNow('2026-11-11 15:30:00');
    $this->putJson("/fisio/laudos-bps/{$this->laudoId}/secoes/atividades", [
        'texto' => 'Limitação para permanecer em pé por mais de 20 minutos.', 'assinatura' => 'sistema',
        'autor_nome' => 'Outra Pessoa', 'conselho' => 'CRM', 'registro_conselho' => 'CRM-SC 0000',
    ])->assertOk();
    $this->postJson("/fisio/laudos-bps/{$this->laudoId}/secoes/atividades/registrar")->assertOk();

    $s = Secao::query()->where('laudo_id', $this->laudoId)->where('item', 'atividades')->sole();
    $nome = DB::table('users')->where('id', $this->u98->id)->first(['first_name', 'last_name']);
    expect($s->autor_nome)->toBe(trim($nome->first_name.' '.$nome->last_name))
        ->and($s->conselho)->toBe('CREFITO')
        ->and($s->registro_conselho)->toBe(F::CREFITO)
        ->and((int) $s->registrado_por)->toBe($this->u98->id)
        ->and($s->registrado_em->format('Y-m-d H:i'))->toBe('2026-11-11 15:30');

    // Registrada, a seção não volta a rascunho: salvar de novo cria uma correção apontando a original.
    $this->putJson("/fisio/laudos-bps/{$this->laudoId}/secoes/atividades", ['texto' => 'Correção: 15 minutos.', 'assinatura' => 'sistema'])->assertOk();
    $correcao = Secao::query()->where('laudo_id', $this->laudoId)->whereNull('registrado_em')->sole();
    expect((int) $correcao->amends_id)->toBe($s->id)
        ->and(Secao::query()->findOrFail($s->id)->texto)->toBe('Limitação para permanecer em pé por mais de 20 minutos.');
});

it('UC-FBP-03 · sem CREFITO no perfil não assina pelo sistema', function () {
    $semCrefito = $this->usuarioComPermissoes([F::PERM_ACESSO, F::PERM_EDITAR], $this->seededTenant());
    $this->actingAs($semCrefito);
    session(['user.business_id' => $this->b98]);

    $this->putJson("/fisio/laudos-bps/{$this->laudoId}/secoes/atividades", ['texto' => 'Texto', 'assinatura' => 'sistema'])->assertOk();
    $this->postJson("/fisio/laudos-bps/{$this->laudoId}/secoes/atividades/registrar")->assertUnprocessable()->assertJsonValidationErrors('crefito');
    expect(Secao::query()->where('laudo_id', $this->laudoId)->whereNotNull('registrado_em')->exists())->toBeFalse();
});

it('UC-FBP-04 · seção externa: nome, conselho e número vêm do formulário e são obrigatórios para registrar', function () {
    $this->putJson("/fisio/laudos-bps/{$this->laudoId}/secoes/fatores", ['texto' => 'Ansiedade relacionada à dor.', 'assinatura' => 'externa'])->assertOk();
    $this->postJson("/fisio/laudos-bps/{$this->laudoId}/secoes/fatores/registrar")->assertUnprocessable()->assertJsonValidationErrors('autor_nome');

    $this->putJson("/fisio/laudos-bps/{$this->laudoId}/secoes/fatores", [
        'texto' => 'Ansiedade relacionada à dor.', 'assinatura' => 'externa',
        'autor_nome' => 'Psicóloga Fixture', 'conselho' => 'CRP', 'registro_conselho' => 'CRP-12/00000',
    ])->assertOk();
    $this->postJson("/fisio/laudos-bps/{$this->laudoId}/secoes/fatores/registrar")->assertOk();

    $s = Secao::query()->where('laudo_id', $this->laudoId)->where('item', 'fatores')->sole();
    expect($s->autor_nome)->toBe('Psicóloga Fixture')->and($s->conselho)->toBe('CRP')
        ->and($s->assinatura)->toBe('externa')->and((int) $s->registrado_por)->toBe($this->u98->id);

    // Seção sem texto não se registra (o espaço em branco do PDF é para quem ainda não escreveu).
    $this->putJson("/fisio/laudos-bps/{$this->laudoId}/secoes/participacao", ['assinatura' => 'externa',
        'autor_nome' => 'Assistente Fixture', 'conselho' => 'CRESS', 'registro_conselho' => 'CRESS-12/0000'])->assertOk();
    $this->postJson("/fisio/laudos-bps/{$this->laudoId}/secoes/participacao/registrar")->assertUnprocessable()->assertJsonValidationErrors('texto');
});

it('UC-FBP-05 · sem fisio.editar 403; laudo e paciente da outra empresa 404; item fora da lei 404', function () {
    $leitor = $this->usuarioComPermissoes([F::PERM_ACESSO], $this->seededTenant());
    F::como($this, $leitor);
    $this->postJson("/fisio/pacientes/{$this->p->id}/laudos-bps")->assertForbidden();
    $this->putJson("/fisio/laudos-bps/{$this->laudoId}/secoes/atividades", ['texto' => 'x', 'assinatura' => 'sistema'])->assertForbidden();

    F::como($this, $this->u99);
    $this->postJson("/fisio/pacientes/{$this->p->id}/laudos-bps")->assertNotFound();
    $this->putJson("/fisio/laudos-bps/{$this->laudoId}/secoes/atividades", ['texto' => 'x', 'assinatura' => 'sistema'])->assertNotFound();
    $this->postJson("/fisio/laudos-bps/{$this->laudoId}/secoes/atividades/registrar")->assertNotFound();

    F::como($this, $this->u98);
    $this->putJson("/fisio/laudos-bps/{$this->laudoId}/secoes/conclusao", ['texto' => 'x', 'assinatura' => 'sistema'])->assertNotFound();

    // Nada da 99 entrou no laudo da 98.
    expect(Secao::query()->where('laudo_id', $this->laudoId)->count())->toBe(0)
        ->and(LaudoBiopsicossocial::query()->where('patient_id', $this->p->id)->count())->toBe(1);
});

it('UC-FBP-06 · paciente bloqueado não recebe laudo novo', function () {
    DB::table('fisio_patients')->where('id', $this->p->id)->update(['blocked_at' => now()]);
    $this->postJson("/fisio/pacientes/{$this->p->id}/laudos-bps")->assertUnprocessable()->assertJsonValidationErrors('paciente');
    expect(LaudoBiopsicossocial::query()->where('patient_id', $this->p->id)->count())->toBe(1);
});
