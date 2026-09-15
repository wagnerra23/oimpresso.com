<?php

declare(strict_types=1);

// @covers-us US-COPI-149 — a tool nao escreve mais no checkout do servidor e
// nao afirma durabilidade. Os 2 casos abaixo medem o EFEITO (o SPEC nao muda;
// o markdown e o id continuam vindo), nao a presenca de string no codigo.

use Illuminate\Support\Facades\File;
use Modules\Jana\Services\TaskRegistry\TaskCrudService;

uses(Tests\TestCase::class);

/**
 * US-COPI-149 — GUARD: `tasks-create` NAO escreve no checkout do servidor.
 *
 * POR QUE EXISTE: ate 2026-09-15 o ramo feliz do createCanonical() fazia
 * file_put_contents() no SPEC e a tool respondia "criada e adicionada". O
 * arquivo vive num checkout que o deploy reseta — medido no incidente: 5 US
 * criadas assim sumiram no pull seguinte (SPEC de 89.531 para 82.068 bytes),
 * e o `git add` que a mensagem instruia teria de rodar NO SERVIDOR, que e
 * proibicao Tier 0. Nenhum teste cobria o `written` deste servico — por isso
 * o defeito sobreviveu.
 *
 * O QUE ESTE TESTE MORDE: se alguem reintroduzir a escrita, o byte-count do
 * SPEC muda e o caso 1 cai. Nao mede presenca de string no codigo (isso seria
 * presence-gate): mede o EFEITO no arquivo.
 */
beforeEach(function () {
    test()->modulo = "ZzGuardUsCopi149";
    test()->dir    = base_path("memory/requisitos/" . test()->modulo);
    test()->spec   = test()->dir . "/SPEC.md";
    File::ensureDirectoryExists(test()->dir);
    File::put(test()->spec, "---
module: ZzGuardUsCopi149
version: \"1.0.0\"
last_updated: \"2026-09-15\"
---

## 2. User stories
");
});

afterEach(function () {
    if (isset(test()->dir) && File::isDirectory(test()->dir)) {
        File::deleteDirectory(test()->dir);
    }
});

test("create() NAO altera o SPEC do servidor (US-COPI-149)", function () {
    $antes = File::get(test()->spec);

    $r = app(TaskCrudService::class)->create([
        "module" => test()->modulo,
        "title"  => "Guard da US-COPI-149",
        "author" => "pest",
    ]);

    $depois = File::get(test()->spec);

    // o EFEITO: o arquivo nao foi tocado
    expect($depois)->toBe($antes,
        "o SPEC do servidor foi alterado — a escrita voltou (US-COPI-149)");
    expect(strlen($depois))->toBe(strlen($antes));

    // e o contrato devolvido continua honesto
    expect($r["written"])->toBeFalse("written deve ser sempre false apos a US-COPI-149");
});

test("create() ainda DEVOLVE o markdown e o id — a capacidade nao regrediu", function () {
    $r = app(TaskCrudService::class)->create([
        "module" => test()->modulo,
        "title"  => "Guard da capacidade",
        "author" => "pest",
    ]);

    expect($r["task_id"])->toMatch("/^US-[A-Z0-9]+-\d+$/",
        "o id precisa continuar sendo gerado — sem ele o chamador nao tem o que colar");
    expect($r["markdown"])->not->toBeNull("sem markdown o chamador fica sem o bloco pra commitar");
    expect($r["markdown"])->toContain($r["task_id"]);
    expect($r["spec_path"])->toContain("memory/requisitos/" . test()->modulo);
});
