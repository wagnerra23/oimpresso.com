<?php

declare(strict_types=1);
// Cobre UC-FPR-01..UC-FPR-05 (Fisio/Protocolos/Index.casos.md).
// @covers-us US-FISIO-006

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Modules\Fisio\Entities\Protocol;
use Modules\Fisio\Tests\Support\FisioFixtures as F;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * Tela dos protocolos-modelo: o modelo do produto (`business_id` nulo) aparece para toda clínica;
 * o protocolo da clínica, só para ela. Tenant 98 contra 99. ⚠️ SKIP em SQLite (LC-13).
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
    $this->exGlobal = F::exercicio(null, 'Ponte global');
    $this->ex99 = F::exercicio($this->b99, 'Próprio da 99');
    $this->modelo = Protocol::withoutGlobalScopes()->create(['business_id' => null, 'name' => 'Modelo lombar', 'region' => 'lombar',
        'items' => [['exercise_id' => $this->exGlobal->id, 'sets' => 2, 'reps' => 10], ['exercise_id' => $this->ex99->id]]]);
    $this->de98 = Protocol::withoutGlobalScopes()->create(['business_id' => $this->b98, 'name' => 'Da 98', 'items' => [['exercise_id' => $this->exGlobal->id]]]);
    $this->de99 = Protocol::withoutGlobalScopes()->create(['business_id' => $this->b99, 'name' => 'Da 99', 'items' => [['exercise_id' => $this->ex99->id]]]);
});

function fprProtocolos($t, $user): array
{
    return F::props($t, $user, '/fisio/protocolos', 'Fisio/Protocolos/Index', 'protocolos');
}

it('UC-FPR-01 · modelo do produto para as duas; protocolo da clínica só para a dona', function () {
    $v98 = collect(fprProtocolos($this, $this->u98))->pluck('id')->map('intval')->all();
    $v99 = collect(fprProtocolos($this, $this->u99))->pluck('id')->map('intval')->all();

    expect($v98)->toContain($this->modelo->id)->toContain($this->de98->id)->not->toContain($this->de99->id)
        ->and($v99)->toContain($this->modelo->id)->toContain($this->de99->id)->not->toContain($this->de98->id);
});

it('UC-FPR-02 · item sai com o nome do exercício; exercício próprio de outra clínica não aparece', function () {
    $modelo = collect(fprProtocolos($this, $this->u98))->firstWhere('id', $this->modelo->id);

    expect($modelo['modelo_global'])->toBeTrue()
        ->and(array_column($modelo['itens'], 'exercicio'))->toBe([$this->exGlobal->name]) // a fixture acrescenta uniqid() ao nome
        ->and($modelo['itens'][0]['sets'])->toBe(2);
});

it('UC-FPR-03 · quem só tem fisio.access abre a tela e recebe pode_editar falso', function () {
    $leitor = $this->usuarioComPermissoes([F::PERM_ACESSO], $this->seededTenant());
    F::como($this, $leitor);

    $this->get('/fisio/protocolos')->assertOk()->assertInertia(fn ($p) => $p->component('Fisio/Protocolos/Index')->where('pode_editar', false));
    expect(collect(fprProtocolos($this, $leitor))->pluck('id')->map('intval')->all())->toContain($this->de98->id);
});

it('UC-FPR-04 · copiar o modelo cria a cópia na clínica da sessão; o modelo não se edita', function () {
    F::como($this, $this->u98);
    $id = (int) $this->postJson("/fisio/protocolos/{$this->modelo->id}/copiar")->assertCreated()->json('id');

    $copia = Protocol::withoutGlobalScopes()->findOrFail($id);
    expect($copia->business_id)->toBe($this->b98)
        ->and($copia->origin_protocol_id)->toBe($this->modelo->id);
    $this->putJson("/fisio/protocolos/{$this->modelo->id}", ['name' => 'Alterado'])->assertForbidden();
    expect(Protocol::withoutGlobalScopes()->findOrFail($this->modelo->id)->name)->toBe('Modelo lombar');
});

it('UC-FPR-05 · protocolo de outra clínica não se edita nem se copia (404)', function () {
    F::como($this, $this->u98);

    $this->putJson("/fisio/protocolos/{$this->de99->id}", ['name' => 'Invasão'])->assertNotFound();
    $this->postJson("/fisio/protocolos/{$this->de99->id}/copiar")->assertNotFound();
    expect(Protocol::withoutGlobalScopes()->findOrFail($this->de99->id)->name)->toBe('Da 99');
});
