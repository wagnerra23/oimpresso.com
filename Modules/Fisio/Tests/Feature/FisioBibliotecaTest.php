<?php

declare(strict_types=1);
// Cobre UC-FBI-01..UC-FBI-06 (Fisio/Biblioteca/Index.casos.md).
// @covers-us US-FISIO-004

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Modules\Fisio\Database\Seeders\FisioBibliotecaSeeder;
use Modules\Fisio\Entities\Exercise;
use Modules\Fisio\Tests\Support\FisioFixtures as F;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * Biblioteca de exercícios: os globais (`business_id` nulo) aparecem para todo business com o
 * módulo; o exercício próprio só para o dono. Global não é editável por business nenhum.
 *
 * ⚠️ O ScopeByBusiness padrão FILTRA `business_id = sessão` e esconderia os globais para
 * usuário comum. O Exercise precisa de escopo próprio (`business_id = sessão OR NULL`) —
 * este teste falha se o model usar só o HasBusinessScope cru.
 *
 * Tenant 98 contra 99, ambos com pacote. ⚠️ SKIP em SQLite (LC-13).
 */
beforeEach(function () {
    if ($motivo = F::motivoDeSkip()) {
        $this->markTestSkipped($motivo);
    }
    F::configurar();
    $this->b98 = (int) $this->seededTenant()->id;
    $this->b99 = (int) $this->seededSupportClientTenant()->id;
    $this->u98 = $this->usuarioComPermissoes([F::PERM_ACESSO, F::PERM_EDITAR], $this->seededTenant());
    $this->u99 = $this->usuarioComPermissoes([F::PERM_ACESSO, F::PERM_EDITAR], $this->seededSupportClientTenant());
    F::pacote([$this->b98, $this->b99]);
    $this->global = F::exercicio(null, 'Global ponte');
    $this->de98 = F::exercicio($this->b98, 'Próprio da 98');
    $this->de99 = F::exercicio($this->b99, 'Próprio da 99');
});

function fbiIds($t, $user, string $qs = ''): array
{
    return collect(F::props($t, $user, '/fisio/biblioteca'.$qs, 'Fisio/Biblioteca/Index', 'exercicios'))->pluck('id')->map('intval')->all();
}

it('UC-FBI-01 · global aparece para os dois; próprio só para o dono', function () {
    $v98 = fbiIds($this, $this->u98);
    $v99 = fbiIds($this, $this->u99);

    expect($v98)->toContain($this->global->id)->toContain($this->de98->id)->not->toContain($this->de99->id)
        ->and($v99)->toContain($this->global->id)->toContain($this->de99->id)->not->toContain($this->de98->id);
});

it('UC-FBI-01 · no Eloquent também: sessão da 98 vê global + 98, nunca 99', function () {
    F::como($this, $this->u98);
    $ids = Exercise::query()->whereIn('id', [$this->global->id, $this->de98->id, $this->de99->id])->pluck('id')->map('intval')->sort()->values()->all();

    expect($ids)->toBe(collect([$this->global->id, $this->de98->id])->sort()->values()->all());
});

it('UC-FBI-02 · criar exercício próprio grava business da sessão e is_custom', function () {
    F::como($this, $this->u98);
    $this->postJson('/fisio/biblioteca/exercicios', [
        'name' => 'Ponte unilateral da clínica', 'region' => 'lombar', 'type' => 'fortalecimento', 'pose' => 'deitado',
        'steps' => ['Deite', 'Estenda uma perna', 'Eleve o quadril'], 'default_sets' => 3, 'default_reps' => 10, 'default_hold_s' => 0,
        'times_per_day' => 1, 'business_id' => null,
    ])->assertCreated();

    $ex = Exercise::withoutGlobalScopes()->where('name', 'Ponte unilateral da clínica')->firstOrFail(); // SUPERADMIN: verificação
    expect((int) $ex->business_id)->toBe($this->b98)->and((bool) $ex->is_custom)->toBeTrue();
    expect(fbiIds($this, $this->u99))->not->toContain($ex->id);
});

it('UC-FBI-02 · exercício sem passos ou sem nome é recusado', function () {
    F::como($this, $this->u98);
    $this->postJson('/fisio/biblioteca/exercicios', ['name' => '', 'region' => 'lombar', 'steps' => []])
        ->assertStatus(422)->assertJsonValidationErrors(['name', 'steps']);
});

it('UC-FBI-03 · global não é editável por business; próprio de outro responde 404', function () {
    F::como($this, $this->u98);
    $this->putJson("/fisio/biblioteca/exercicios/{$this->global->id}", ['name' => 'Sequestrado'])->assertForbidden();
    $this->putJson("/fisio/biblioteca/exercicios/{$this->de99->id}", ['name' => 'Invadido'])->assertNotFound();
    $this->putJson("/fisio/biblioteca/exercicios/{$this->de98->id}", ['name' => 'Renomeado'])->assertOk();

    // SUPERADMIN: verificação pós-tentativa.
    expect(Exercise::withoutGlobalScopes()->find($this->global->id)->name)->toBe($this->global->name)
        ->and(Exercise::withoutGlobalScopes()->find($this->de99->id)->name)->toBe($this->de99->name)
        ->and(Exercise::withoutGlobalScopes()->find($this->de98->id)->name)->toBe('Renomeado');
});

// Busca por texto saiu da F1: a decisão nº 3 da ADR 0437 §14 não foi aceita por [W], e o
// HANDOFF-F1 (ajuste de conciliação nº 3) manda ficar só com o filtro por região e equipamento.
// O caso troca a busca `?q=` pelo filtro de equipamento — mesma forma de prova (o filtrado entra,
// o resto sai), com controle de que o filtro não esvazia nem vaza o próprio de outra clínica.
it('UC-FBI-04 · filtro por região e por equipamento', function () {
    $ombro = F::exercicio(null, 'Rotação externa', ['region' => 'ombro', 'equipment' => ['elastico']]);
    $semNada = F::exercicio(null, 'Sem equipamento', ['equipment' => []]);
    $elastico99 = F::exercicio($this->b99, 'Elástico da 99', ['equipment' => ['elastico']]);

    $porRegiao = fbiIds($this, $this->u98, '?region=ombro');
    $porEquipamento = fbiIds($this, $this->u98, '?equipamento=elastico');
    $semEquipamento = fbiIds($this, $this->u98, '?equipamento=nenhum');

    expect($porRegiao)->toContain($ombro->id)->not->toContain($this->global->id)
        ->and($porEquipamento)->toContain($ombro->id)->not->toContain($this->de98->id)->not->toContain($semNada->id)->not->toContain($elastico99->id)
        ->and($semEquipamento)->toContain($semNada->id)->not->toContain($ombro->id);
});

it('UC-FBI-05 · seed L0: 38 globais pendentes de revisão clínica, fora da grade até publicar', function () {
    $r = (new FisioBibliotecaSeeder())->semear();
    // SUPERADMIN: verificação do seed global (business_id nulo), sem sessão.
    $semeados = Exercise::withoutGlobalScopes()->whereNull('business_id')->whereNotNull('code')->get();

    expect($r['criados'] + $r['atualizados'] + $r['preservados'])->toBe(38)
        ->and($semeados)->toHaveCount(38)
        ->and($semeados->pluck('review_status')->unique()->values()->all())->toBe(['em_revisao'])
        ->and($semeados->every(fn ($e) => $e->is_custom === false && count($e->steps) > 0 && $e->times_per_day >= 1))->toBeTrue();

    // Contraindicações usam só os códigos do paciente (1:1 com condition_flags).
    $flags = $semeados->flatMap(fn ($e) => (array) $e->contraindication_flags)->unique()->values()->all();
    expect(array_values(array_diff($flags, (array) config('fisio.condition_flags'))))->toBe([])
        ->and($flags)->not->toBeEmpty();

    // Pendente da Dra. Dejeane: a clínica NÃO vê o conteúdo em revisão...
    $exemplo = (int) $semeados->first()->id;
    expect(fbiIds($this, $this->u98))->not->toContain($exemplo)->toContain($this->global->id);

    // ...e vê depois de publicado (controle: a trava é o estado, não o seed).
    // SUPERADMIN: a revisão clínica publica o exercício.
    Exercise::withoutGlobalScopes()->whereKey($exemplo)->update(['review_status' => 'publicado', 'reviewed_by_name' => 'Revisora', 'reviewed_crefito' => F::CREFITO, 'reviewed_at' => now()]);
    expect(fbiIds($this, $this->u98))->toContain($exemplo);

    // Re-seed é idempotente e não sobrescreve o que já foi revisado.
    $r2 = (new FisioBibliotecaSeeder())->semear();
    expect($r2['criados'])->toBe(0)
        ->and($r2['preservados'])->toBe(1)
        ->and(Exercise::withoutGlobalScopes()->whereNull('business_id')->whereNotNull('code')->count())->toBe(38) // SUPERADMIN: verificação
        ->and(Exercise::withoutGlobalScopes()->find($exemplo)->review_status)->toBe('publicado'); // SUPERADMIN: verificação
});

it('UC-FBI-06 · remover: só o próprio; global 403, de outra clínica 404, em programa 409', function () {
    $emUso = F::exercicio($this->b98, 'Em programa');
    F::programa(F::paciente($this->b98, $this->u98->id), [$emUso], $this->u98->id);

    F::como($this, $this->u98);
    $this->deleteJson("/fisio/biblioteca/exercicios/{$this->global->id}")->assertForbidden();
    $this->deleteJson("/fisio/biblioteca/exercicios/{$this->de99->id}")->assertNotFound();
    $this->deleteJson("/fisio/biblioteca/exercicios/{$emUso->id}")->assertStatus(409)->assertJson(['erro' => 'em_uso']);
    $this->deleteJson("/fisio/biblioteca/exercicios/{$this->de98->id}")->assertNoContent();

    // SUPERADMIN: verificação pós-tentativa — só o próprio livre saiu.
    expect(Exercise::withoutGlobalScopes()->whereKey([$this->global->id, $this->de99->id, $emUso->id])->count())->toBe(3)
        ->and(Exercise::withoutGlobalScopes()->find($this->de98->id))->toBeNull();
});
