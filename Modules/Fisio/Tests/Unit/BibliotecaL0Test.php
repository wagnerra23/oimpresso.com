<?php

declare(strict_types=1);
// @covers-us US-FISIO-004
// Cobre UC-FBI-11 (Fisio/Biblioteca/Index.casos.md).

use Modules\Fisio\Database\Seeders\FisioBibliotecaSeeder;
use Modules\Fisio\Entities\Patient;

// Com TestCase: lê config('fisio.biblioteca.*'), as listas fechadas que a tela usa.
uses(Tests\TestCase::class);

/**
 * Lote L0 da biblioteca global (38 exercícios aprovados pela responsável técnica em
 * 2026-10-19). Não confere o conteúdo clínico — isso é da revisão dela —, confere que cada
 * exercício fala o vocabulário das listas fechadas: posição e equipamento fora da lista somem
 * dos filtros, e contraindicação fora de `Patient::CONDITION_FLAGS` nunca dispara o aviso.
 */
function bl0Lista(): array
{
    return (new FisioBibliotecaSeeder())->exercicios();
}

it('UC-FBI-11 · são 38 exercícios, nomes únicos, todos com passos e dose', function () {
    $lista = bl0Lista();

    expect($lista)->toHaveCount(38);
    expect(count(array_unique(array_column($lista, 'name'))))->toBe(38);

    foreach ($lista as $e) {
        $this->assertNotEmpty($e['steps'], "{$e['name']}: sem passos");
        $this->assertGreaterThanOrEqual(1, (int) $e['times_per_day'], "{$e['name']}: vezes por dia");
        $this->assertTrue(((int) ($e['default_reps'] ?? 0)) > 0 || ((int) ($e['default_hold_s'] ?? 0)) > 0, "{$e['name']}: sem repetição nem tempo");
    }
});

it('UC-FBI-11 · posição, equipamento e contraindicação vêm das listas fechadas', function () {
    $posicoes = array_keys((array) config('fisio.biblioteca.posicoes'));
    $equipamentos = array_keys((array) config('fisio.biblioteca.equipamentos'));
    $this->assertNotEmpty($posicoes);
    $this->assertNotEmpty($equipamentos);

    foreach (bl0Lista() as $e) {
        $this->assertContains($e['pose'], $posicoes, "{$e['name']}: posição fora da lista");
        $this->assertNotEmpty($e['equipment'], "{$e['name']}: sem equipamento (use 'nenhum')");
        foreach ($e['equipment'] as $q) {
            $this->assertContains($q, $equipamentos, "{$e['name']}: equipamento fora da lista");
        }
        foreach ($e['contraindication_flags'] as $f) {
            $this->assertContains($f, Patient::CONDITION_FLAGS, "{$e['name']}: contraindicação fora da lista");
        }
    }
});

it('UC-FBI-11 · texto ao paciente sem promessa clínica (ADR 0437 §5)', function () {
    $proibidas = ['trata', 'cura', 'corrige postura', 'previne lesão', 'diagnostica'];

    foreach (bl0Lista() as $e) {
        $texto = mb_strtolower($e['name'].' '.implode(' ', $e['steps']).' '.implode(' ', (array) ($e['safety_notes'] ?? [])));
        foreach ($proibidas as $p) {
            $this->assertDoesNotMatchRegularExpression('/\b'.preg_quote($p, '/').'\b/u', $texto, "{$e['name']}: usa \"{$p}\"");
        }
    }
});
