<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Contract\AutosaveContractRunner;

/**
 * Contract test do drawer 760 Cliente (ADR 0179) — TODOS os 5 endpoints
 * PATCH + ~32 campos auto-verificados.
 *
 * Wagner 2026-05-27 — proposta canon ADR 0205 (contract tests autosave).
 *
 * Runner invoca cada [endpoint, field, value, response_key] do fixture,
 * faz PATCH, verifica status 200 + valor retornado bate com enviado
 * (modos: equals/partial/bool/int/array_eq).
 *
 * Falha aqui = bug silencioso (PATCH 200 mas dado nao persistiu OU
 * resposta retornou chave diferente que frontend nao consegue ler).
 *
 * Tipo de bug evitado:
 *   - Aliases PT-BR -> EN nao mapeados (Daniela @ Martinho #1773)
 *   - Coluna duplicada (ie vs inscricao_estadual #1767)
 *   - Campo orfao sem coluna destino (contato sem schema)
 *   - shapeContactResponse vs validator out-of-sync
 *
 * Pra adicionar nova tela: criar fixture `tests/Contract/Fixtures/<tela>.php`
 * + 1 test file similar a este. Ver `tests/Contract/README.md`.
 */

uses(DatabaseTransactions::class);

beforeEach(function () {
    $ctx = AutosaveContractRunner::setupContext($this);
    $this->business = $ctx['business'];
    $this->user = $ctx['user'];
    $this->contactId = $ctx['contactId'];
});

it('Cliente drawer — todos os 5 endpoints autosave persistem TODOS os campos do fixture', function () {
    $fixture = require __DIR__ . '/../../Contract/Fixtures/cliente_drawer.php';
    $result = AutosaveContractRunner::run($this, $fixture, $this->contactId);

    // Falha legivel pra dev (lista cada bug catalogado).
    if ($result['passed'] !== $result['total']) {
        $msg = "❌ Contract test FALHOU — {$result['passed']}/{$result['total']} OK.\n\n";
        $msg .= "Bugs silenciosos detectados:\n";
        foreach ($result['failures'] as $f) {
            $msg .= sprintf(
                "  [%s] PATCH %s · send=%s · value_sent=%s · recv=%s · value_received=%s · status=%d · match=%s\n",
                $f['tab'], $f['endpoint'], $f['send'], var_export($f['value_sent'], true),
                $f['recv'], var_export($f['value_received'], true), $f['status'], $f['match_mode']
            );
        }
        $msg .= "\nADR 0205 — todo PR que regrida contract test bloqueia merge.\n";
        $msg .= "Fix: alinhe validator backend + shapeContactResponse + payload rows + frontend (alias se necessario).\n";

        expect($result['failures'])->toBeEmpty($msg);
    }

    expect($result['passed'])->toBe($result['total']);
});
