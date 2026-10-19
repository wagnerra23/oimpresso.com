<?php

declare(strict_types=1);
// @covers-us US-FISIO-005
// Cobre UC-FPI-20 (Fisio/Pacientes/Index.casos.md) e UC-FPS-30, UC-FPS-31, UC-FPS-32 (Fisio/Pacientes/Show.casos.md).

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Modules\Fisio\Entities\Patient;
use Modules\Fisio\Services\ResponsavelPaciente;
use Modules\Fisio\Tests\Support\FisioFixtures as F;
use Spatie\Activitylog\Models\Activity;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * Fisioterapeuta responsável pelo paciente e transferência registrada.
 *
 * Tenant 98 contra o 99, os dois COM o pacote (o 403 do módulo não pode mascarar o isolamento).
 * A transferência vai para o activity_log; o teste lê a linha pelo banco, com o business_id do
 * paciente, e confere que transferir de novo ACRESCENTA uma linha (nada se apaga).
 *
 * ⚠️ SKIP em SQLite (LC-13): conte os passed.
 */
beforeEach(function () {
    if ($motivo = F::motivoDeSkip(['activity_log'])) {
        $this->markTestSkipped($motivo);
    }
    F::configurar();
    $this->b98 = (int) $this->seededTenant()->id;
    $this->b99 = (int) $this->seededSupportClientTenant()->id;
    // Dois fisioterapeutas na 98, um usuário da 98 sem acesso ao Fisio, e um fisioterapeuta na 99.
    $this->ana = $this->usuarioComPermissoes([F::PERM_ACESSO, F::PERM_EDITAR], $this->seededTenant());
    $this->bia = $this->usuarioComPermissoes([F::PERM_ACESSO, F::PERM_EDITAR], $this->seededTenant());
    $this->leitor = $this->usuarioComPermissoes([F::PERM_ACESSO], $this->seededTenant());
    $this->semFisio = $this->usuarioComPermissoes([], $this->seededTenant());
    $this->outra = $this->usuarioComPermissoes([F::PERM_ACESSO, F::PERM_EDITAR], $this->seededSupportClientTenant());
    F::pacote([$this->b98, $this->b99]);
});

function frsTransferencias(Patient $p): array
{
    return Activity::query()
        ->where('subject_type', $p->getMorphClass())
        ->where('subject_id', $p->id)
        ->where('description', ResponsavelPaciente::EVENTO)
        ->orderBy('id')
        ->get()
        ->all();
}

it('UC-FPS-30 · quem cadastra é o responsável; responsible_user_id do corpo é ignorado', function () {
    F::como($this, $this->ana);
    $contato = F::contato($this->b98, $this->ana->id, 'Carla Mendes');

    $this->post('/fisio/pacientes', [
        'contact_id' => $contato, 'sessions_per_week' => 3, 'main_complaint' => 'Dor lombar',
        'responsible_user_id' => $this->outra->id,
    ])->assertSessionHasNoErrors()->assertRedirect();

    $p = Patient::query()->where('contact_id', $contato)->firstOrFail();
    expect((int) $p->responsible_user_id)->toBe((int) $this->ana->id);

    // A ficha mostra o responsável pelo nome.
    $this->get("/fisio/pacientes/{$p->id}")->assertOk()
        ->assertInertia(fn ($pg) => $pg->component('Fisio/Pacientes/Show', false)
            ->where('paciente.responsavel.id', (int) $this->ana->id)
            ->where('paciente.responsavel.nome', ResponsavelPaciente::nome($this->ana)));
});

it('UC-FPI-20 · lista traz o responsável e filtra por "meus" e por profissional', function () {
    $daAna = F::paciente($this->b98, $this->ana->id, ['responsible_user_id' => $this->ana->id]);
    $daBia = F::paciente($this->b98, $this->ana->id, ['responsible_user_id' => $this->bia->id]);
    $semResp = F::paciente($this->b98, $this->ana->id);

    $todos = collect(F::props($this, $this->ana, '/fisio/pacientes', 'Fisio/Pacientes/Index', 'pacientes'))->keyBy('id');
    expect($todos->keys()->all())->toContain($daAna->id, $daBia->id, $semResp->id)
        ->and($todos[$daBia->id]['responsavel']['nome'])->toBe(ResponsavelPaciente::nome($this->bia))
        ->and($todos[$semResp->id]['responsavel'])->toBeNull();

    // "Meus pacientes" para a Ana: só os dela — o filtro troca, não esvazia.
    $meus = collect(F::props($this, $this->ana, '/fisio/pacientes?responsavel=meus', 'Fisio/Pacientes/Index', 'pacientes'))->pluck('id');
    expect($meus->all())->toContain($daAna->id)->not->toContain($daBia->id)->not->toContain($semResp->id);

    // "Meus pacientes" para a Bia devolve os dela (discriminante: o mesmo filtro, outro usuário).
    $daBiaVisto = collect(F::props($this, $this->bia, '/fisio/pacientes?responsavel=meus', 'Fisio/Pacientes/Index', 'pacientes'))->pluck('id');
    expect($daBiaVisto->all())->toContain($daBia->id)->not->toContain($daAna->id);

    // Por profissional (id).
    $porId = collect(F::props($this, $this->ana, '/fisio/pacientes?responsavel='.$this->bia->id, 'Fisio/Pacientes/Index', 'pacientes'))->pluck('id');
    expect($porId->all())->toContain($daBia->id)->not->toContain($daAna->id);
});

it('UC-FPI-20 · a lista de profissionais é só da empresa e só de quem tem acesso ao Fisio', function () {
    $ids = collect(F::props($this, $this->ana, '/fisio/pacientes', 'Fisio/Pacientes/Index', 'profissionais'))->pluck('id')->map('intval');

    expect($ids->all())->toContain((int) $this->ana->id, (int) $this->bia->id, (int) $this->leitor->id)
        ->not->toContain((int) $this->semFisio->id)
        ->not->toContain((int) $this->outra->id);

    // Discriminante: vista da 99, aparece o profissional da 99 e nenhum da 98.
    $ids99 = collect(F::props($this, $this->outra, '/fisio/pacientes', 'Fisio/Pacientes/Index', 'profissionais'))->pluck('id')->map('intval');
    expect($ids99->all())->toContain((int) $this->outra->id)->not->toContain((int) $this->ana->id);
});

it('UC-FPS-31 · transferir troca o responsável e registra quem, de quem, para quem e quando', function () {
    $p = F::paciente($this->b98, $this->ana->id, ['responsible_user_id' => $this->ana->id]);
    F::como($this, $this->ana);

    $this->post("/fisio/pacientes/{$p->id}/responsavel", ['para_user_id' => $this->bia->id])
        ->assertSessionHasNoErrors()->assertRedirect();

    expect((int) $p->fresh()->responsible_user_id)->toBe((int) $this->bia->id);
    $log = frsTransferencias($p);
    expect($log)->toHaveCount(1);
    $a = $log[0];
    expect((int) $a->business_id)->toBe($this->b98)
        ->and((int) $a->causer_id)->toBe((int) $this->ana->id)
        ->and((int) $a->properties['de_user_id'])->toBe((int) $this->ana->id)
        ->and((int) $a->properties['para_user_id'])->toBe((int) $this->bia->id)
        ->and($a->created_at)->not->toBeNull();

    // Transferir de novo ACRESCENTA uma linha; a primeira continua igual.
    F::como($this, $this->bia);
    $this->post("/fisio/pacientes/{$p->id}/responsavel", ['para_user_id' => $this->leitor->id])->assertSessionHasNoErrors();
    $log = frsTransferencias($p);
    expect($log)->toHaveCount(2)
        ->and((int) $log[0]->id)->toBe((int) $a->id)
        ->and((int) $log[0]->properties['para_user_id'])->toBe((int) $this->bia->id)
        ->and((int) $log[1]->properties['de_user_id'])->toBe((int) $this->bia->id)
        ->and((int) $log[1]->causer_id)->toBe((int) $this->bia->id);

    // A ficha recebe o histórico, mais recente primeiro.
    $hist = F::props($this, $this->ana, "/fisio/pacientes/{$p->id}", 'Fisio/Pacientes/Show', 'transferencias');
    expect($hist)->toHaveCount(2)
        ->and($hist[0]['para'])->toBe(ResponsavelPaciente::nome($this->leitor))
        ->and($hist[1]['de'])->toBe(ResponsavelPaciente::nome($this->ana));
});

it('UC-FPS-32 · sem fisio.editar responde 403 e nada muda', function () {
    $p = F::paciente($this->b98, $this->ana->id, ['responsible_user_id' => $this->ana->id]);
    F::como($this, $this->leitor);

    $this->post("/fisio/pacientes/{$p->id}/responsavel", ['para_user_id' => $this->bia->id])->assertForbidden();

    expect((int) $p->fresh()->responsible_user_id)->toBe((int) $this->ana->id)
        ->and(frsTransferencias($p))->toHaveCount(0);
});

it('UC-FPS-32 · destino de outra empresa, sem acesso ao Fisio ou inexistente responde 422 e nada muda', function () {
    $p = F::paciente($this->b98, $this->ana->id, ['responsible_user_id' => $this->ana->id]);
    F::como($this, $this->ana);

    foreach ([(int) $this->outra->id, (int) $this->semFisio->id, 999999999] as $destino) {
        $this->postJson("/fisio/pacientes/{$p->id}/responsavel", ['para_user_id' => $destino])
            ->assertStatus(422)->assertJsonValidationErrors('para_user_id');
    }

    expect((int) $p->fresh()->responsible_user_id)->toBe((int) $this->ana->id)
        ->and(frsTransferencias($p))->toHaveCount(0);

    // Controle positivo: o mesmo endpoint aceita um destino válido (o 422 acima não é do endpoint todo).
    $this->postJson("/fisio/pacientes/{$p->id}/responsavel", ['para_user_id' => $this->bia->id])->assertRedirect();
    expect((int) $p->fresh()->responsible_user_id)->toBe((int) $this->bia->id);
});

it('UC-FPS-32 · paciente de outra empresa responde 404 e o registro da 98 fica intacto', function () {
    $p98 = F::paciente($this->b98, $this->ana->id, ['responsible_user_id' => $this->ana->id]);
    $outra2 = $this->usuarioComPermissoes([F::PERM_ACESSO, F::PERM_EDITAR], $this->seededSupportClientTenant());
    $p99 = F::paciente($this->b99, $this->outra->id, ['responsible_user_id' => $this->outra->id]);
    F::como($this, $this->outra);

    $this->post("/fisio/pacientes/{$p98->id}/responsavel", ['para_user_id' => $this->outra->id])->assertNotFound();

    // Controle positivo: a mesma rota, na própria empresa, transfere — o 404 acima é do escopo, não de rota ausente.
    $this->post("/fisio/pacientes/{$p99->id}/responsavel", ['para_user_id' => $outra2->id])->assertSessionHasNoErrors()->assertRedirect();
    expect((int) $p99->fresh()->responsible_user_id)->toBe((int) $outra2->id);

    // SUPERADMIN: leitura de verificação do registro da 98 a partir da sessão da 99.
    expect((int) Patient::withoutGlobalScopes()->findOrFail($p98->id)->responsible_user_id)->toBe((int) $this->ana->id)
        ->and(frsTransferencias($p98))->toHaveCount(0);
});

it('UC-FPS-32 · paciente bloqueado não é transferido', function () {
    $p = F::paciente($this->b98, $this->ana->id, ['responsible_user_id' => $this->ana->id, 'blocked_at' => now()]);
    F::como($this, $this->ana);

    $this->postJson("/fisio/pacientes/{$p->id}/responsavel", ['para_user_id' => $this->bia->id])
        ->assertStatus(422)->assertJsonValidationErrors('para_user_id');

    expect((int) $p->fresh()->responsible_user_id)->toBe((int) $this->ana->id);
});
