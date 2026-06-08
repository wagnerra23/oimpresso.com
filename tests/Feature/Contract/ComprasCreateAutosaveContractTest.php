<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Contract\AutosaveContractRunner;

/**
 * Contract test do fluxo Compras/Create (MWART Wave2 B5 — PurchaseCreate.tsx).
 *
 * Wagner 2026-05-27 — quarto fixture sob padrao ADR 0205 (apos drawer Cliente,
 * Sells/Create, ServiceOrder/Edit). Compras/Create faz submit full-form
 * `form.post('/purchases', ...)` que retorna redirect — sem JSON. Por isso
 * cobrimos os endpoints COMPANHEIROS do fluxo (quick-add Fornecedor + check
 * ref_no) onde bug silencioso e´ possivel.
 *
 * Cobertura honesta (NAO inventa endpoints que a tela nao tem):
 *   1. POST /contacts (type=supplier)  — Quick-add Fornecedor (5 campos)
 *      Mesma rota de Sells, mas type=supplier ativa shape PJ diferente
 *      (supplier_business_name como primaria, NAO first_name).
 *   2. POST /purchases/check_ref_number  — DESATIVADO temporario (runner nao
 *      suporta raw body response). Ver fixture pra justificativa + reativacao.
 *
 * Total: 5 campos auto-verificados. Bugs evitados (ver fixture):
 *   - supplier_business_name removido do $request->only() => regressao UPOS 6.7
 *   - cpf_cnpj fornecedor nao persiste => emissao NFe falha em prod
 *   - city silencioso => relatorios fiscais sem cidade
 *
 * NAO cobre POST /purchases (submit principal) — full-form redirect-flow, erros
 * 422 visiveis, sem bug silencioso possivel. Mesma justificativa que
 * SellsCreateAutosaveContractTest pra POST /pos.
 *
 * Setup: usa `setupSellsContext` em vez de `setupContext` porque alguns endpoints
 * futuros do fluxo Compras (update-status quando coberto) precisam de transaction
 * stub no biz. Hoje so quick-add nao precisa, mas mantemos paridade pra extensao.
 * Custo: cria 1 sell stub extra que e´ rollback-ado — trivial.
 *
 * @see tests/Contract/Fixtures/compras_create.php
 * @see app/Http/Controllers/PurchaseController.php (createInertia §425+, store §475+)
 * @see resources/js/Pages/Purchase/Create.tsx
 * @see ADR 0205 — contract tests autosave canon
 * @see ADR 0093 — multi-tenant Tier 0 IRREVOGAVEL
 */

uses(DatabaseTransactions::class);

beforeEach(function () {
    // Pré-flight 1: DB driver — schema UPOS Transaction + contact requer MySQL real.
    // Skip graceful em sqlite memory (CI smoke) — runner do AutosaveContractRunner ja
    // faz seu proprio skip, mas reforcamos aqui pra mensagem de erro mais clara.
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: requer schema MySQL UltimatePOS (ADR 0093 Tier 0 + ADR 0101)');
    }

    // Pré-flight 2: tabelas. setupSellsContext ja faz skip via Schema::hasTable,
    // mas explicitamos contacts.cpf_cnpj (coluna BR Slice 1 — migration 2026_05_21_140000).
    if (! Schema::hasColumn('contacts', 'cpf_cnpj')) {
        $this->markTestSkipped('Coluna contacts.cpf_cnpj ausente — rode `php artisan migrate` (Slice 1 BR).');
    }
    if (! Schema::hasColumn('contacts', 'supplier_business_name')) {
        $this->markTestSkipped('Coluna contacts.supplier_business_name ausente — schema UPOS upstream.');
    }

    // Setup multi-tenant Tier 0 (ADR 0093): autentica user + session + cria contact
    // base + sell stub (status=draft). Sell stub nao e´ usado por este fixture hoje,
    // mas mantem paridade com Sells/Create pattern pra futura extensao
    // (PATCH /purchases/{id}/something quando algum endpoint autosave for adicionado).
    $ctx = AutosaveContractRunner::setupSellsContext($this);
    $this->business = $ctx['business'];
    $this->user = $ctx['user'];
    $this->contactId = $ctx['contactId'];
    $this->transactionId = $ctx['transactionId'];
});

it('Compras/Create — endpoint companheiro quick-add Fornecedor (POST /contacts type=supplier) persiste campos do fixture', function () {
    $fixture = require __DIR__ . '/../../Contract/Fixtures/compras_create.php';

    // POST /contacts nao usa {id} placeholder — runner so substitui se presente,
    // entao passar transactionId como resourceId nao quebra (e fica disponivel pra
    // futuros endpoints que tenham {id}).
    $result = AutosaveContractRunner::run($this, $fixture, $this->transactionId);

    if ($result['passed'] !== $result['total']) {
        $msg = "❌ Contract test FALHOU — {$result['passed']}/{$result['total']} OK.\n\n";
        $msg .= "Bugs silenciosos detectados no fluxo Compras/Create:\n";
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
        $msg .= "Fix: alinhe validator backend (\$request->only() + StoreContactRequest rules)\n";
        $msg .= "       + response shape (data.X path) + alias se necessario.\n";
        $msg .= "Atencao: Compras quick-add usa type=supplier — shape difere de Sells quick-add\n";
        $msg .= "       (supplier_business_name primario vs first_name primario).\n";

        expect($result['failures'])->toBeEmpty($msg);
    }

    expect($result['passed'])->toBe($result['total']);
});
