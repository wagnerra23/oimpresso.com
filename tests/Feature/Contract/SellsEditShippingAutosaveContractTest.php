<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Contract\AutosaveContractRunner;

/**
 * Contract test do modal Sells/Edit Shipping (SellController::updateShipping).
 *
 * Wagner 2026-05-27 — 9o contract test (apos NFe/Config). Cobre o endpoint
 * legacy Blade PUT /sells/update-shipping/{id} que ainda nao migrou pra Inertia.
 *
 * Ver fixture `tests/Contract/Fixtures/sells_edit_shipping.php` pro detalhe
 * dos 10 campos cobertos + justificativa da escolha de DB roundtrip.
 *
 * ARQUITETURA DO TEST — por que NAO usa AutosaveContractRunner::run():
 *
 *   updateShipping faz:
 *     $transaction->update($input);
 *     return ['success' => 1, 'msg' => trans('lang_v1.updated_success')];
 *
 *   Ou seja: a resposta JSON NAO contem o Transaction atualizado. Sem o
 *   objeto no body, o runner default (que faz `data_get($response->json(),
 *   "{$responseRoot}.{$recvKey}")`) nao tem onde ler o valor de volta.
 *
 *   Solucao: loop custom inline com DB roundtrip — espelha o padrao
 *   ServiceOrderEditAutosaveContractTest (que tambem faz form submit PUT
 *   onde o controller redireciona/retorna minimal).
 *
 *   Fluxo por field:
 *     1. PUT /sells/update-shipping/{id} com payload = [send => sent_value]
 *     2. Assert status 200 (controller retorna JSON {success: 1, msg: ...})
 *     3. Query SQL direta: SELECT * FROM transactions WHERE id = ?
 *     4. matchesValue($sent, $row->{recv}, $matchMode)
 *
 *   Trade-off honesto: DB roundtrip valida persistencia mas NAO valida
 *   shape de leitura — se o GET /sells/{id} algum dia retornar a coluna
 *   com alias diferente, este test NAO pega. Coverage parcial mas suficiente
 *   pra detectar:
 *     - $request->only([...]) com typo => coluna nao recebe valor
 *     - Cast no Model truncando/transformando o valor
 *     - Mass-assignment guarded bloqueando o field
 *     - Migration nao rodou no env => column not found error explicito
 *
 * Multi-tenant Tier 0 (ADR 0093 IRREVOGAVEL): setupSellsContext autentica
 * user + popula session.user.business_id. updateShipping faz Transaction::
 * where('business_id', $business_id)->findOrFail($id) — Tier 0 preservado.
 *
 * Permission: updateShipping aceita is_admin OR access_shipping. O user
 * retornado por setupContext (Business::first user) eh Admin#{business_id}
 * (criado pelo seeder UPOS) — passa no is_admin gate. Se algum env tiver
 * user nao-admin, controller retorna 403 e test falha com mensagem clara.
 *
 * @see app/Http/Controllers/SellController.php::updateShipping (linha ~3374)
 * @see tests/Contract/Fixtures/sells_edit_shipping.php
 * @see ADR 0205 (contract tests autosave canon)
 * @see ADR 0093 (multi-tenant Tier 0)
 */

uses(DatabaseTransactions::class);

beforeEach(function () {
    // Pre-flight 1: DB driver. Schema UPOS exige MySQL — sqlite memory falha
    // em transactions table (tipos diferentes, FK semantics).
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompativel: requer schema MySQL UltimatePOS (ADR 0101)');
    }

    // Pre-flight 2: tabela transactions existe. Setup helper tambem checa,
    // mas duplicamos pra mensagem clara antes de tocar runner.
    if (! Schema::hasTable('transactions')) {
        $this->markTestSkipped('Schema UltimatePOS ausente (transactions) — rode migrations base.');
    }

    // Pre-flight 3: colunas shipping_* existem (migrations 2019_09_18 +
    // 2020_12_18 + 2023_06_21). Em env muito antigo podem faltar — skip
    // graceful em vez de error fatal SQL.
    if (! Schema::hasColumn('transactions', 'shipping_status')
        || ! Schema::hasColumn('transactions', 'shipping_custom_field_1')
        || ! Schema::hasColumn('transactions', 'delivery_person')) {
        $this->markTestSkipped('Migrations shipping (2019_09_18 / 2020_12_18 / 2023_06_21) ausentes neste env.');
    }

    // Setup multi-tenant + cria transaction stub tipo `sell` status=draft.
    // Runner helper cuida de Business::first + User + actingAs + session.
    $ctx = AutosaveContractRunner::setupSellsContext($this);
    $this->business = $ctx['business'];
    $this->user = $ctx['user'];
    $this->contactId = $ctx['contactId'];
    $this->transactionId = $ctx['transactionId'];
});

it('Sells/Edit shipping — PUT endpoint persiste TODOS os campos shipping_* (roundtrip via DB)', function () {
    $fixture = require __DIR__ . '/../../Contract/Fixtures/sells_edit_shipping.php';
    $stamp = 'CT' . substr((string) microtime(true), -4);
    $passed = 0;
    $total = 0;
    $failures = [];

    foreach ($fixture as $tabName => $tabSpec) {
        $endpoint = str_replace('{id}', (string) $this->transactionId, $tabSpec['endpoint']);
        $method = $tabSpec['method'] ?? 'put';

        foreach ($tabSpec['fields'] as $field) {
            $total++;
            $sendKey = $field['send'];
            $recvKey = $field['recv'];
            $match = $field['match'] ?? 'equals';

            // Substitui {stamp} pra valor unico por iteracao (evita falso-positivo
            // de valor pre-existente em coluna do stub).
            $sent = is_string($field['value'])
                ? str_replace('{stamp}', $stamp, $field['value'])
                : $field['value'];

            // Payload = apenas 1 campo. updateShipping faz $request->only([...]),
            // entao chaves extras sao filtradas — nao precisamos enviar baseFields.
            // Isso TAMBEM eh o ponto do test: se backend filtrar erroneamente o
            // sendKey (typo no only), update($input) recebe array vazio, valor
            // NAO persiste, e este test pega o bug.
            $payload = [$sendKey => $sent];

            // 1) PUT — envia payload, espera JSON 200 com {success: 1}.
            $response = $this->putJson($endpoint, $payload);
            $status = $response->status();

            // 2) DB roundtrip — le valor de volta direto da coluna. Bypassa
            // qualquer cache/HTTP/serializer — verifica persistencia bruta.
            $row = DB::table('transactions')->where('id', $this->transactionId)->first();
            $received = $row ? data_get($row, $recvKey) : null;

            $valueOk = matchesShippingValue($sent, $received, $match);
            $statusOk = $status === 200;

            if (! $statusOk || ! $valueOk) {
                $failures[] = [
                    'tab' => $tabName,
                    'endpoint' => $endpoint,
                    'method' => $method,
                    'send' => $sendKey,
                    'value_sent' => is_string($sent) ? substr($sent, 0, 60) : $sent,
                    'recv' => $recvKey,
                    'value_received' => is_string($received) ? substr((string) $received, 0, 60) : $received,
                    'status' => $status,
                    'match_mode' => $match,
                ];
            } else {
                $passed++;
            }
        }
    }

    if ($passed !== $total) {
        $msg = "❌ Contract test FALHOU — {$passed}/{$total} OK.\n\n";
        $msg .= "Bugs silenciosos detectados (PUT 200 mas valor nao persistiu em DB):\n";
        foreach ($failures as $f) {
            $msg .= sprintf(
                "  [%s] %s %s · send=%s · value_sent=%s · recv=%s · value_received=%s · status=%d · match=%s\n",
                $f['tab'], strtoupper($f['method']), $f['endpoint'],
                $f['send'], var_export($f['value_sent'], true),
                $f['recv'], var_export($f['value_received'], true),
                $f['status'], $f['match_mode']
            );
        }
        $msg .= "\nADR 0205 — todo PR que regrida contract test bloqueia merge.\n";
        $msg .= "Fix: alinhe \$request->only([...]) em SellController::updateShipping\n";
        $msg .= "       + verifique cast/fillable no Transaction model + migration da coluna.\n";

        expect($failures)->toBeEmpty($msg);
    }

    expect($passed)->toBe($total);
});

/**
 * Helper inline pra match modes — mesma semantica de
 * AutosaveContractRunner::matches() (private). Nome distinto
 * (matchesShippingValue) pra evitar colisao com matchesValue() ja
 * declarado em ServiceOrderEditAutosaveContractTest no mesmo namespace
 * global do Pest. Quando runner ganhar suporte canonico a DB roundtrip,
 * este helper migra pra metodo statico publico do runner.
 */
function matchesShippingValue(mixed $sent, mixed $received, string $match): bool
{
    return match ($match) {
        'partial' => $received !== null && is_string($received)
            && str_contains((string) $received, is_string($sent) ? $sent : (string) $sent),
        'bool' => (bool) $received === (bool) $sent,
        'int' => (int) $received === (int) $sent,
        'array_eq' => json_encode($received) === json_encode($sent),
        default => $received === $sent || (string) $received === (string) $sent,
    };
}
