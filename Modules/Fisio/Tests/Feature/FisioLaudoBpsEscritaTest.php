<?php

declare(strict_types=1);
// @covers-us US-FISIO-060
// Cobre UC-FBP-04..UC-FBP-09 (memory/requisitos/Fisio/_telas/laudo-biopsicossocial.casos.md).

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Modules\Fisio\Entities\LaudoBiopsicossocial;
use Modules\Fisio\Entities\LaudoBiopsicossocialEquipe as Equipe;
use Modules\Fisio\Services\Laudo\MatrizIfbrm;
use Modules\Fisio\Tests\Support\FisioFixtures as F;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * Escrita do laudo biopsicossocial (modelo SES-SC) pelas rotas: preencher, equipe, finalizar e
 * nova versão. Tenant 98 contra 99 (ADR 0358). ⚠️ SKIP em SQLite (LC-13).
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
    $this->id = (int) $this->postJson("/fisio/pacientes/{$this->p->id}/laudos-bps")->assertCreated()->json('id');
    $this->url = "/fisio/laudos-bps/{$this->id}";
});

/** Deixa o laudo pronto para finalizar: matriz, conclusão, local/data e equipe com médico e fisio. */
function fbpCompleto(object $t): void
{
    $t->putJson($t->url, ['matriz' => array_fill_keys(MatrizIfbrm::itens(), 75),
        'conclusao' => ['impedimento_longo_prazo' => '1', 'barreiras' => true, 'equiparacao' => '0'],
        'local' => 'Florianópolis', 'data' => '2026-11-11'])->assertOk();
    $t->postJson("{$t->url}/equipe", ['area' => 'medicina', 'assinatura' => 'externa', 'nome' => 'Médica Fixture', 'registro' => 'CRM-SC 00000'])->assertCreated();
    $t->postJson("{$t->url}/equipe", ['area' => 'fisioterapia_to', 'assinatura' => 'sistema'])->assertCreated();
}

it('UC-FBP-04 · preencher grava só o que veio; matriz validada e pontuada; Sim/Não vira booleano', function () {
    $this->putJson($this->url, ['identificacao' => ['unidade_aps' => 'UBS Centro', 'natureza' => ['fisica']]])->assertOk();
    $r = $this->putJson($this->url, ['identificacao' => ['cid' => 'M79.7'], 'matriz' => ['1.1' => 100, '1.2' => '50'],
        'conclusao' => ['barreiras' => '1']])->assertOk();

    $d = LaudoBiopsicossocial::query()->findOrFail($this->id)->dados;
    expect($d['identificacao'])->toBe(['unidade_aps' => 'UBS Centro', 'natureza' => ['fisica'], 'cid' => 'M79.7'])
        ->and($d['matriz'])->toBe(['1.1' => 100, '1.2' => 50])
        ->and($d['conclusao']['barreiras'])->toBeTrue()
        ->and($r->json('pontuacao.completo'))->toBeFalse()
        ->and($r->json('pontuacao.faltando'))->toHaveCount(28);

    // Limpar um item tira ele da matriz.
    $this->putJson($this->url, ['matriz' => ['1.2' => null]])->assertOk();
    expect(LaudoBiopsicossocial::query()->findOrFail($this->id)->dados['matriz'])->toBe(['1.1' => 100]);

    $this->putJson($this->url, ['matriz' => ['1.1' => 60]])->assertUnprocessable()->assertJsonValidationErrors('matriz');
    $this->putJson($this->url, ['matriz' => ['9.9' => 100]])->assertUnprocessable()->assertJsonValidationErrors('matriz');
    $this->putJson($this->url, ['identificacao' => ['natureza' => ['outra']]])->assertUnprocessable();
});

it('UC-FBP-05 · equipe: o fisioterapeuta assina com nome e CREFITO do servidor; o de fora, com o que foi digitado', function () {
    $this->postJson("{$this->url}/equipe", ['area' => 'fisioterapia_to', 'assinatura' => 'sistema', 'nome' => 'Outro Nome', 'registro' => 'X'])->assertCreated();
    $m = Equipe::query()->where('laudo_id', $this->id)->sole();
    $u = DB::table('users')->where('id', $this->u98->id)->first(['first_name', 'last_name']);
    expect($m->nome)->toBe(trim($u->first_name.' '.$u->last_name))->and($m->registro)->toBe(F::CREFITO);

    $this->postJson("{$this->url}/equipe", ['area' => 'medicina', 'assinatura' => 'sistema'])->assertUnprocessable()->assertJsonValidationErrors('area');
    $this->postJson("{$this->url}/equipe", ['area' => 'psicologia', 'assinatura' => 'externa'])->assertUnprocessable()->assertJsonValidationErrors(['nome', 'registro']);
    $id = (int) $this->postJson("{$this->url}/equipe", ['area' => 'psicologia', 'assinatura' => 'externa', 'nome' => 'Psicóloga Fixture', 'registro' => 'CRP 12/00000'])->assertCreated()->json('id');

    $this->deleteJson("{$this->url}/equipe/{$id}")->assertOk();
    expect(Equipe::query()->where('laudo_id', $this->id)->pluck('area')->all())->toBe(['fisioterapia_to']);
});

it('UC-FBP-06 · finalizar exige 30 itens, conclusão respondida, local e data, e dois profissionais de áreas distintas com médico', function () {
    $this->postJson("{$this->url}/finalizar")->assertUnprocessable()->assertJsonValidationErrors(['matriz', 'conclusao', 'local', 'equipe']);

    // Só fisioterapia e enfermagem: falta o médico.
    $this->putJson($this->url, ['matriz' => array_fill_keys(MatrizIfbrm::itens(), 100),
        'conclusao' => ['impedimento_longo_prazo' => false, 'barreiras' => false, 'equiparacao' => false], 'local' => 'Joinville', 'data' => '2026-11-11'])->assertOk();
    $this->postJson("{$this->url}/equipe", ['area' => 'fisioterapia_to', 'assinatura' => 'sistema'])->assertCreated();
    $this->postJson("{$this->url}/equipe", ['area' => 'enfermagem', 'assinatura' => 'externa', 'nome' => 'Enf. Fixture', 'registro' => 'COREN 0'])->assertCreated();
    $this->postJson("{$this->url}/finalizar")->assertUnprocessable()->assertJsonValidationErrors('equipe');

    // Um médico sozinho também não basta: precisa de duas áreas.
    DB::table('fisio_laudos_bps_equipe')->where('laudo_id', $this->id)->delete();
    $this->postJson("{$this->url}/equipe", ['area' => 'medicina', 'assinatura' => 'externa', 'nome' => 'Médico A', 'registro' => 'CRM 1'])->assertCreated();
    $this->postJson("{$this->url}/equipe", ['area' => 'medicina', 'assinatura' => 'externa', 'nome' => 'Médico B', 'registro' => 'CRM 2'])->assertCreated();
    $this->postJson("{$this->url}/finalizar")->assertUnprocessable()->assertJsonValidationErrors('equipe');

    $this->postJson("{$this->url}/equipe", ['area' => 'psicologia', 'assinatura' => 'externa', 'nome' => 'Psi', 'registro' => 'CRP 1'])->assertCreated();
    $r = $this->postJson("{$this->url}/finalizar")->assertOk();
    expect($r->json('total'))->toBe(800);
    $l = LaudoBiopsicossocial::query()->findOrFail($this->id);
    expect((int) $l->finalizado_por)->toBe($this->u98->id)->and($l->finalizado_em)->not->toBeNull();

    // Finalizado: nada muda pelas rotas.
    $this->putJson($this->url, ['local' => 'Outra'])->assertUnprocessable()->assertJsonValidationErrors('laudo');
    $this->postJson("{$this->url}/equipe", ['area' => 'enfermagem', 'assinatura' => 'externa', 'nome' => 'X', 'registro' => 'Y'])->assertUnprocessable();
    $this->postJson("{$this->url}/finalizar")->assertUnprocessable();
});

it('UC-FBP-07 · nova versão copia formulário e equipe para um rascunho que aponta o original', function () {
    fbpCompleto($this);
    $this->postJson("{$this->url}/nova-versao")->assertUnprocessable()->assertJsonValidationErrors('laudo');
    $this->postJson("{$this->url}/finalizar")->assertOk();

    $novoId = (int) $this->postJson("{$this->url}/nova-versao")->assertCreated()->json('id');
    $novo = LaudoBiopsicossocial::query()->findOrFail($novoId);
    expect((int) $novo->amends_id)->toBe($this->id)
        ->and($novo->estaFinalizado())->toBeFalse()
        ->and($novo->dados['local'])->toBe('Florianópolis')
        ->and($novo->equipe()->pluck('area')->sort()->values()->all())->toBe(['fisioterapia_to', 'medicina']);
});

it('UC-FBP-08 · sem fisio.editar 403; outra empresa 404; paciente bloqueado não recebe laudo novo', function () {
    F::como($this, $this->usuarioComPermissoes([F::PERM_ACESSO], $this->seededTenant()));
    $this->postJson("/fisio/pacientes/{$this->p->id}/laudos-bps")->assertForbidden();
    $this->putJson($this->url, ['local' => 'X'])->assertForbidden();

    F::como($this, $this->u99);
    $this->postJson("/fisio/pacientes/{$this->p->id}/laudos-bps")->assertNotFound();
    $this->putJson($this->url, ['local' => 'X'])->assertNotFound();
    $this->postJson("{$this->url}/equipe", ['area' => 'medicina', 'assinatura' => 'externa', 'nome' => 'X', 'registro' => 'Y'])->assertNotFound();
    $this->postJson("{$this->url}/finalizar")->assertNotFound();
    // SUPERADMIN: verificação de que nada da 99 entrou no laudo da 98.
    expect(Equipe::query()->withoutGlobalScopes()->where('laudo_id', $this->id)->count())->toBe(0);

    F::como($this, $this->u98);
    DB::table('fisio_patients')->where('id', $this->p->id)->update(['blocked_at' => now()]);
    $this->postJson("/fisio/pacientes/{$this->p->id}/laudos-bps")->assertUnprocessable()->assertJsonValidationErrors('paciente');
});

it('UC-FBP-09 · com pontuação abaixo de 500 o sistema não marca a conclusão', function () {
    // Todos 25: total 200, bem abaixo dos 500 do formulário.
    $r = $this->putJson($this->url, ['matriz' => array_fill_keys(MatrizIfbrm::itens(), 25)])->assertOk();
    expect($r->json('pontuacao.total'))->toBe(200);
    expect(LaudoBiopsicossocial::query()->findOrFail($this->id)->dados)->not->toHaveKey('conclusao');
});
