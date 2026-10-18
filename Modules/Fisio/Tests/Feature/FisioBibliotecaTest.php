<?php

declare(strict_types=1);
// Cobre UC-FBI-01..UC-FBI-04 (Fisio/Biblioteca/Index.casos.md).

use Illuminate\Foundation\Testing\DatabaseTransactions;
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

it('UC-FBI-04 · filtro por região e busca por nome', function () {
    $ombro = F::exercicio(null, 'Rotação externa', ['region' => 'ombro']);

    $porRegiao = fbiIds($this, $this->u98, '?region=ombro');
    $porBusca = fbiIds($this, $this->u98, '?q=Rota');

    expect($porRegiao)->toContain($ombro->id)->not->toContain($this->global->id)
        ->and($porBusca)->toContain($ombro->id)->not->toContain($this->de98->id);
});
