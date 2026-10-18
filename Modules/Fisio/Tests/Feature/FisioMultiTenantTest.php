<?php

declare(strict_types=1);
// Cobre UC-FPI-02 (Fisio/Pacientes/Index.casos.md), UC-FPS-01 (Fisio/Pacientes/Show.casos.md) e UC-FPN-06 (Fisio/Painel.casos.md).

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Modules\Fisio\Entities\Checkin;
use Modules\Fisio\Entities\Patient;
use Modules\Fisio\Entities\Program;
use Modules\Fisio\Entities\Record;
use Modules\Fisio\Tests\Support\FisioFixtures as F;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * Isolamento multi-tenant Tier 0 (ADR 0093) do Fisio — dois businesses, ambos COM o pacote,
 * para que o 403 do módulo não mascare o isolamento: o que se prova aqui é o escopo por
 * `business_id`, não o gate de módulo (esse é o FisioModuloVisibilidadeTest).
 *
 * Tenant 98 contra o 99. NUNCA biz=4. ⚠️ SKIP em SQLite (LC-13).
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

    $ex = F::exercicio(null);
    $this->p98 = F::paciente($this->b98, $this->u98->id);
    $this->p99 = F::paciente($this->b99, $this->u99->id);
    $this->prog98 = F::programa($this->p98, [$ex], $this->u98->id);
    $this->prog99 = F::programa($this->p99, [$ex], $this->u99->id);
    F::checkin($this->p98, $this->prog98, now()->subDay()->toDateString(), F::itens($this->prog98), 3);
    F::checkin($this->p99, $this->prog99, now()->subDay()->toDateString(), F::itens($this->prog99), 3);
});

it('UC-FPI-02 · Eloquent sob sessão da 98 enxerga só a 98, em todas as tabelas com business_id', function () {
    F::como($this, $this->u98);

    expect(Patient::query()->pluck('business_id')->map('intval')->unique()->values()->all())->toBe([$this->b98])
        ->and(Program::query()->pluck('business_id')->map('intval')->unique()->values()->all())->toBe([$this->b98])
        ->and(Checkin::query()->pluck('business_id')->map('intval')->unique()->values()->all())->toBe([$this->b98])
        ->and(Patient::query()->find($this->p99->id))->toBeNull();

    // Discriminante: a mesma consulta como 99 devolve só a 99 (o filtro troca, não esvazia).
    F::como($this, $this->u99);
    expect(Patient::query()->pluck('id')->all())->toContain($this->p99->id)->not->toContain($this->p98->id);

    // Controle negativo: sem escopo as duas linhas existem — o filtro de cima é real.
    // SUPERADMIN: controle negativo do teste de isolamento.
    expect(Patient::withoutGlobalScopes()->whereIn('id', [$this->p98->id, $this->p99->id])->count())->toBe(2);
});

it('UC-FPI-02 · lista de pacientes da 99 não traz paciente da 98', function () {
    $ids = collect(F::props($this, $this->u99, '/fisio/pacientes', 'Fisio/Pacientes/Index', 'pacientes'))->pluck('id');

    expect($ids)->toContain($this->p99->id)->not->toContain($this->p98->id);
});

it('UC-FPS-01 · ficha, edição, bloqueio, prontuário e programa de outro business respondem 404 e nada muda', function () {
    F::como($this, $this->u99);
    $id = $this->p98->id;
    $prog = $this->prog98->id;
    $item = F::itens($this->prog98)[0];

    $this->get("/fisio/pacientes/{$id}")->assertNotFound();
    $this->put("/fisio/pacientes/{$id}", ['main_complaint' => 'Invadido', 'sessions_per_week' => 2])->assertNotFound();
    $this->delete("/fisio/pacientes/{$id}")->assertNotFound();
    $this->post("/fisio/pacientes/{$id}/registros", ['kind' => 'evolucao', 'body' => ['conduta' => 'x']])->assertNotFound();
    $this->post("/fisio/programas/{$prog}/itens", ['exercise_id' => F::exercicio(null)->id])->assertNotFound();
    $this->put("/fisio/programas/{$prog}/itens/{$item}", ['sets' => 9])->assertNotFound();
    $this->delete("/fisio/programas/{$prog}/itens/{$item}")->assertNotFound();
    $this->post("/fisio/programas/{$prog}/enviar")->assertNotFound();

    $fresco = Patient::withoutGlobalScopes()->find($id); // SUPERADMIN: verificação pós-invasão
    expect($fresco->main_complaint)->toBe('Lombalgia mecânica')
        ->and($fresco->blocked_at)->toBeNull()
        ->and(Record::withoutGlobalScopes()->where('patient_id', $id)->count())->toBe(0) // SUPERADMIN
        ->and(F::itens($this->prog98))->toHaveCount(1);
});

it('UC-FPS-01 · criar paciente com contact de outro business é recusado', function () {
    F::como($this, $this->u98);
    $contatoAlheio = F::contato($this->b99, $this->u99->id);

    $this->post('/fisio/pacientes', ['contact_id' => $contatoAlheio, 'sessions_per_week' => 3, 'main_complaint' => 'x'])
        ->assertSessionHasErrors('contact_id');
    expect(Patient::withoutGlobalScopes()->where('contact_id', $contatoAlheio)->exists())->toBeFalse(); // SUPERADMIN
});

it('UC-FPN-06 · painel da 98 não conta paciente nem check-in da 99', function () {
    $linhas = collect(F::props($this, $this->u98, '/fisio', 'Fisio/Painel', 'pacientes'));
    $kpis = F::props($this, $this->u98, '/fisio', 'Fisio/Painel', 'kpis');

    expect($linhas->pluck('id')->all())->toBe([$this->p98->id])
        ->and($kpis['pacientes_ativos'])->toBe(1);
});
