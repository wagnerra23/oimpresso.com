<?php

declare(strict_types=1);
// Cobre UC-FBI-12 (Fisio/Biblioteca/Index.casos.md) — etapa 01 B-06.
// @covers-us US-FISIO-004

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Modules\Fisio\Entities\Exercise;
use Modules\Fisio\Entities\ProgramItem;
use Modules\Fisio\Entities\Protocol;
use Modules\Fisio\Tests\Support\FisioFixtures as F;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * Lint de linguagem Anvisa (ADR 0437 §5): texto que a clínica salva e o paciente pode ler —
 * exercício próprio, protocolo, instruções do programa — com promessa clínica é recusado (422) e
 * a mensagem diz qual termo foi barrado e por quê. Nada é gravado na recusa.
 *
 * A lista vem de `config('fisio.linguagem_anvisa.termos_proibidos')`, cópia literal da ADR; o
 * primeiro caso trava essa cópia. Tenant 98 contra 99. ⚠️ SKIP em SQLite (LC-13).
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

    $this->ex98 = F::exercicio($this->b98, 'Próprio da 98');
    $this->p98 = F::paciente($this->b98, $this->u98->id);
    $this->prog98 = F::programa($this->p98, [F::exercicio(null, 'Ponte')], $this->u98->id);
    $this->item98 = F::itens($this->prog98)[0];
    // SUPERADMIN: fixture de protocolo da clínica 98; o escopo é provado nos asserts.
    $this->prot98 = Protocol::withoutGlobalScopes()->create([
        'business_id' => $this->b98, 'name' => 'Lombar fase 1 '.uniqid(), 'region' => 'lombar',
        'items' => [['exercise_id' => $this->ex98->id, 'sets' => 3, 'reps' => 10]], 'notes' => null,
    ]);
});

/** Salva `$texto` no alvo como a 98 e devolve [resposta, campo que carrega o texto]. */
function flaSalvar($t, string $alvo, string $texto): array
{
    F::como($t, $t->u98);

    return match ($alvo) {
        'exercício próprio (criar)' => [$t->postJson('/fisio/biblioteca/exercicios', [
            'name' => 'Ponte da clínica '.uniqid(), 'region' => 'lombar', 'type' => 'fortalecimento',
            'pose' => 'deitado_costas', 'steps' => ['Deite de costas', $texto], 'times_per_day' => 1,
        ]), 'steps'],
        'exercício próprio (editar)' => [$t->putJson("/fisio/biblioteca/exercicios/{$t->ex98->id}", ['safety_notes' => [$texto]]), 'safety_notes'],
        'protocolo (editar)' => [$t->putJson("/fisio/protocolos/{$t->prot98->id}", ['notes' => $texto]), 'notes'],
        'protocolo (salvar do programa)' => [$t->postJson("/fisio/programas/{$t->prog98->id}/salvar-como-protocolo", ['name' => 'Do programa '.uniqid(), 'notes' => $texto]), 'notes'],
        'programa (criar)' => [$t->postJson("/fisio/pacientes/{$t->p98->id}/programas", ['stop_instruction' => $texto]), 'stop_instruction'],
        'programa (editar)' => [$t->putJson("/fisio/programas/{$t->prog98->id}", ['crisis_items' => ['Respire fundo', $texto]]), 'crisis_items'],
        'programa (observação do exercício)' => [$t->putJson("/fisio/programas/{$t->prog98->id}/itens/{$t->item98}", ['notes' => $texto]), 'notes'],
    };
}

dataset('alvos', [
    'exercício próprio (criar)', 'exercício próprio (editar)', 'protocolo (editar)', 'protocolo (salvar do programa)',
    'programa (criar)', 'programa (editar)', 'programa (observação do exercício)',
]);

// Um caso por termo da ADR 0437 §5, cada um numa frase que o profissional poderia escrever.
dataset('termos', [
    'diagnostica' => ['diagnostica', 'Este exercício diagnostica a causa da dor.'],
    'trata' => ['trata', 'Trata a dor lombar em poucas semanas.'],
    'corrige postura' => ['corrige postura', 'Corrige postura de quem trabalha sentado.'],
    'previne lesão' => ['previne lesão', 'Previne lesão no joelho de corredor.'],
]);

it('UC-FBI-12 · a lista de termos é a cópia literal da ADR 0437 §5', function () {
    expect(config('fisio.linguagem_anvisa.termos_proibidos'))
        ->toBe(['diagnostica', 'trata', 'corrige postura', 'previne lesão']);
});

it('UC-FBI-12 · termo proibido é recusado com 422 e a mensagem diz qual e por quê', function (string $termo, string $frase, string $alvo) {
    [$resposta, $campo] = flaSalvar($this, $alvo, $frase);

    $resposta->assertStatus(422)->assertJsonValidationErrors([$campo]);
    $mensagem = (string) $resposta->json("errors.{$campo}.0");
    expect($mensagem)->toContain("\"{$termo}\"");
    expect($mensagem)->toContain('Anvisa');
    expect($mensagem)->toContain('decisão clínica é do profissional');
})->with('termos')->with('alvos');

it('UC-FBI-12 · controle negativo: texto limpo grava (e "tratamento" não é "trata")', function (string $alvo) {
    [$resposta] = flaSalvar($this, $alvo, 'Siga o tratamento combinado e respire devagar.');

    $resposta->assertSuccessful();
})->with('alvos');

it('UC-FBI-12 · sem maiúscula e sem acento o termo continua barrado', function () {
    [$resposta, $campo] = flaSalvar($this, 'exercício próprio (criar)', 'PREVINE   LESAO no ombro');

    $resposta->assertStatus(422)->assertJsonValidationErrors([$campo]);
});

it('UC-FBI-12 · recusa não grava nada', function () {
    $antes = $this->prog98->stop_instruction;

    flaSalvar($this, 'programa (editar)', 'Trata a dor.')[0]->assertStatus(422);
    flaSalvar($this, 'programa (observação do exercício)', 'Trata a dor.')[0]->assertStatus(422);
    flaSalvar($this, 'exercício próprio (editar)', 'Diagnostica a dor.')[0]->assertStatus(422);

    expect($this->prog98->fresh()->stop_instruction)->toBe($antes);
    expect($this->prog98->fresh()->crisis_items)->toBeNull();
    // SUPERADMIN: verificação direta do registro da 98.
    expect(ProgramItem::withoutGlobalScopes()->findOrFail($this->item98)->notes)->toBeNull();
    expect(Exercise::withoutGlobalScopes()->findOrFail($this->ex98->id)->safety_notes)->toBe(['Não prenda a respiração']);
});

it('UC-FBI-12 · 99 não alcança o protocolo nem o exercício da 98: 404 antes do lint, nada muda', function () {
    F::como($this, $this->u99);

    $this->putJson("/fisio/protocolos/{$this->prot98->id}", ['notes' => 'Trata a dor.'])->assertNotFound();
    $this->putJson("/fisio/biblioteca/exercicios/{$this->ex98->id}", ['safety_notes' => ['Trata a dor.']])->assertNotFound();
    $this->putJson("/fisio/programas/{$this->prog98->id}", ['crisis_items' => ['Trata a dor.']])->assertNotFound();

    // SUPERADMIN: verificação direta do registro da 98.
    expect(Protocol::withoutGlobalScopes()->findOrFail($this->prot98->id)->notes)->toBeNull();

    // A 99 grava o próprio texto limpo; o lint vale igual para ela.
    $this->postJson('/fisio/biblioteca/exercicios', [
        'name' => 'Ponte da 99 '.uniqid(), 'region' => 'lombar', 'type' => 'fortalecimento',
        'pose' => 'deitado_costas', 'steps' => ['Deite de costas', 'Eleve o quadril'], 'times_per_day' => 1,
    ])->assertCreated();
    $this->postJson('/fisio/biblioteca/exercicios', [
        'name' => 'Ponte da 99 '.uniqid(), 'region' => 'lombar', 'type' => 'fortalecimento',
        'pose' => 'deitado_costas', 'steps' => ['Corrige postura'], 'times_per_day' => 1,
    ])->assertStatus(422)->assertJsonValidationErrors(['steps']);
});
