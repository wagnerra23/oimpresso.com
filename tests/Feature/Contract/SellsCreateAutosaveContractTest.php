<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Contract\AutosaveContractRunner;

/**
 * Contract test do fluxo Sells/Create (PDV) — endpoints companheiros.
 *
 * Wagner 2026-05-27 — segundo fixture sob padrao ADR 0205 (apos drawer Cliente).
 *
 * Cobertura honesta (NAO inventa endpoints que a tela nao tem):
 *   1. POST /contacts          — QuickAddCustomerSheet embed no fluxo PDV (5 campos)
 *   2. PATCH /sells/{id}/commission-split — Editor split ADR 0192 (2 campos)
 *
 * Total: 7 campos auto-verificados. Bugs evitados:
 *   - $request->only(...) sem `cpf_cnpj` => coluna BR nao persiste (regressao
 *     UPOS 6.7 ja aconteceu — migration 2026_05_21_140000 reintroduziu).
 *   - Payload commission_split chegando flat em vez de nested => 422 silencioso.
 *   - Shape canon mecanico_pct retornando string vs frontend esperando number.
 *
 * NAO cobre POST /pos (submit principal) — full-form, erros visiveis 422,
 * sem bug silencioso possivel. Ver fixture .php pra justificativa completa.
 *
 * Setup: usa `setupSellsContext` em vez de `setupContext` porque commission-split
 * precisa de transaction `sell` base no business. Cria contact + sell stub
 * (status=draft) em transacao -- rollback automatico ao fim do test.
 *
 * Pra adicionar nova tela: ver `tests/Contract/README.md` + ADR 0205.
 */

uses(DatabaseTransactions::class);

beforeEach(function () {
    $ctx = AutosaveContractRunner::setupSellsContext($this);
    $this->business = $ctx['business'];
    $this->user = $ctx['user'];
    $this->contactId = $ctx['contactId'];
    $this->transactionId = $ctx['transactionId'];
});

it('Sells/Create — endpoints companheiros (quick-add cliente + commission-split) persistem campos do fixture', function () {
    $fixture = require __DIR__ . '/../../Contract/Fixtures/sells_create.php';

    // Resolve mecanico_id placeholder (0) -> id real do user autenticado (validator
    // backend exige users.id existente no mesmo business, Rule::exists Tier 0).
    if (isset($fixture['commission_split']['baseFields']['mecanico_id'])) {
        $fixture['commission_split']['baseFields']['mecanico_id'] = (int) $this->user->id;
    }

    // Endpoint POST /contacts nao tem {id} — runner so substitui se presente,
    // entao nao quebra. Usamos transactionId como resourceId porque o segundo
    // endpoint precisa dele. Quick-add ignora (sem placeholder).
    $result = AutosaveContractRunner::run($this, $fixture, $this->transactionId);

    if ($result['passed'] !== $result['total']) {
        $msg = "❌ Contract test FALHOU — {$result['passed']}/{$result['total']} OK.\n\n";
        $msg .= "Bugs silenciosos detectados no fluxo Sells/Create:\n";
        foreach ($result['failures'] as $f) {
            $msg .= sprintf(
                "  [%s] %s %s · send=%s · value_sent=%s · recv=%s · value_received=%s · status=%d · match=%s\n",
                $f['tab'], strtoupper($f['method']), $f['endpoint'],
                $f['send'], var_export($f['value_sent'], true),
                $f['recv'], var_export($f['value_received'], true),
                $f['status'], $f['match_mode']
            );
        }
        $msg .= "\nADR 0205 — todo PR que regrida contract test bloqueia merge.\n";
        $msg .= "Fix: alinhe validator backend (\$request->only) + response shape (data.X vs commission_split.Y)\n";
        $msg .= "       + payloadShape (flat vs nested) + alias se necessario.\n";

        expect($result['failures'])->toBeEmpty($msg);
    }

    expect($result['passed'])->toBe($result['total']);
});
