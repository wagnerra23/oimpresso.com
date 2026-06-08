<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Contract\AutosaveContractRunner;

/**
 * Contract test da tela ServiceOrder/Edit (Modules/OficinaAuto V0 — ADR 0137).
 *
 * Ver fixture `tests/Contract/Fixtures/service_order_edit.php` pro detalhe
 * dos campos cobertos + justificativa de por que NÃO usa AutosaveContractRunner::run()
 * default (ServiceOrder/Edit usa PUT form submit, não PATCH per-field autosave).
 *
 * Pattern do test segue ClienteDrawerAutosaveContractTest mas com 3 adaptações:
 *   1. Cria Vehicle base (via Vehicle::withoutGlobalScopes per pattern ServiceOrderCrudTest)
 *      + ServiceOrder base apontando pro vehicle ANTES de cada test
 *   2. Loop custom inline (não chama runner.run): PUT com base_payload + 1 campo,
 *      depois GET JSON pra ler resposta e validar roundtrip
 *   3. Permite skip graceful em ambiente sqlite memory OU sem schema OficinaAuto
 *
 * Multi-tenant Tier 0 (ADR 0093 IRREVOGÁVEL) preservado via setupContext +
 * session['user.business_id'] + global scope Vehicle/ServiceOrder.
 *
 * FSM Pipeline (ADR 0143) NÃO coberto aqui — `current_stage_id` tem test próprio
 * em ServiceOrderStagePipelineTest + ExecuteStageActionService. Aqui cobrimos
 * apenas o pipeline do form Edit V0 (status livre + datas + notes).
 *
 * @see Modules\OficinaAuto\Http\Controllers\ServiceOrderController::update
 * @see tests/Contract/Fixtures/service_order_edit.php
 * @see ADR 0205 (contract tests autosave canon)
 */

uses(DatabaseTransactions::class);

beforeEach(function () {
    // Pré-flight 1: DB driver — schema OficinaAuto exige MySQL.
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: requer schema MySQL UltimatePOS + OficinaAuto (ADR 0101)');
    }
    // Pré-flight 2: tabelas OficinaAuto. Quando módulo ainda não instalou neste env.
    if (! Schema::hasTable('service_orders') || ! Schema::hasTable('vehicles')) {
        $this->markTestSkipped('Schema OficinaAuto ausente — rode `php artisan module:migrate OficinaAuto`');
    }
    // Pré-flight 3: vertical OficinaAuto enabled no biz alvo. (Setup ctx vai pegar biz=1.)
    // Não usamos isModuleInstalled aqui pra evitar dep circular nos tests CI fresh.

    // Setup multi-tenant (autentica user + session business_id) — reusa runner helper.
    $ctx = AutosaveContractRunner::setupContext($this);
    $this->business = $ctx['business'];
    $this->user = $ctx['user'];
    $this->contactId = $ctx['contactId'];

    // Cria Vehicle base usando padrão validado em ServiceOrderCrudTest (withoutGlobalScopes
    // pq estamos em context "superadmin de teste" — Vehicle Model exige business_id no creating
    // hook senão herda da session, que já está populada acima).
    $plate = 'CT-' . substr((string) microtime(true), -4);
    $this->vehicleId = DB::table('vehicles')->insertGetId([
        'business_id'  => $this->business->id,
        'plate'        => $plate,
        'vehicle_type' => 'automovel',
        'created_at'   => now(),
        'updated_at'   => now(),
    ]);

    // Cria ServiceOrder base — campos mínimos requeridos pela validator (vehicle_id + status).
    // contact_id opcional aqui (não exigido em V0). Cliente derivado via vehicle no Producao.
    $this->orderId = DB::table('service_orders')->insertGetId([
        'business_id'        => $this->business->id,
        'vehicle_id'         => $this->vehicleId,
        'status'             => 'aberta',
        'mileage_at_service' => 10000,
        'notes'              => 'CT setup baseline',
        'created_at'         => now(),
        'updated_at'         => now(),
    ]);
});

it('ServiceOrder/Edit — PUT endpoint persiste TODOS os campos cadastrais do fixture (roundtrip via GET JSON)', function () {
    $fixture = require __DIR__ . '/../../Contract/Fixtures/service_order_edit.php';
    $stamp = 'CT' . substr((string) microtime(true), -4);
    $passed = 0;
    $total = 0;
    $failures = [];

    foreach ($fixture as $tabName => $tabSpec) {
        $endpoint = str_replace('{id}', (string) $this->orderId, $tabSpec['endpoint']);
        $method = $tabSpec['method'] ?? 'patch';

        foreach ($tabSpec['fields'] as $field) {
            $total++;
            $sendKey = $field['send'];
            $recvKey = $field['recv'];
            $match = $field['match'] ?? 'equals';

            // Substitui {stamp} pra valor único por iteração (evita falso-positivo
            // de cache HTTP retornar estado anterior).
            $sent = is_string($field['value'])
                ? str_replace('{stamp}', $stamp, $field['value'])
                : $field['value'];

            // PUT precisa de payload completo (vehicle_id + status são required).
            // Mergeamos base + 1 campo alterado pra cada iteração — outros campos
            // ficam com valor neutro pra não interferir.
            $base = [
                'vehicle_id' => (string) $this->vehicleId,
                'status'     => 'aberta',
            ];
            $payload = array_merge($base, [$sendKey => $sent]);

            // 1) PUT — envia payload. Controller redireciona pro show (302) — sucesso.
            $putResponse = $this->put($endpoint, $payload);
            $putStatus = $putResponse->status();

            // PUT retorna 302 (redirect pra show) em caso sucesso ou 422 em validation fail.
            $putOk = in_array($putStatus, [200, 302], true);

            // 2) GET JSON show — lê valor de volta via endpoint Accept-aware.
            // Endpoint alias /oficina-auto/service-orders/{id} (Wave 7+) retorna JSON shape
            // limpo definido em ServiceOrderController::show §397+.
            $getResponse = $this->getJson('/oficina-auto/service-orders/' . $this->orderId);
            $getStatus = $getResponse->status();
            $received = data_get($getResponse->json(), $recvKey);

            $valueOk = matchesValue($sent, $received, $match);

            if (! $putOk || $getStatus !== 200 || ! $valueOk) {
                $failures[] = [
                    'tab' => $tabName,
                    'endpoint' => $endpoint,
                    'method' => $method,
                    'send' => $sendKey,
                    'value_sent' => is_string($sent) ? substr($sent, 0, 60) : $sent,
                    'recv' => $recvKey,
                    'value_received' => is_string($received) ? substr((string) $received, 0, 60) : $received,
                    'put_status' => $putStatus,
                    'get_status' => $getStatus,
                    'match_mode' => $match,
                ];
            } else {
                $passed++;
            }
        }
    }

    // Falha legível pra dev (lista cada bug catalogado) — espelha
    // ClienteDrawerAutosaveContractTest pattern.
    if ($passed !== $total) {
        $msg = "❌ Contract test FALHOU — {$passed}/{$total} OK.\n\n";
        $msg .= "Bugs silenciosos detectados (PUT 302 + GET 200, mas valor não bate):\n";
        foreach ($failures as $f) {
            $msg .= sprintf(
                "  [%s] %s %s · send=%s · value_sent=%s · recv=%s · value_received=%s · put=%d get=%d · match=%s\n",
                $f['tab'], strtoupper($f['method']), $f['endpoint'], $f['send'], var_export($f['value_sent'], true),
                $f['recv'], var_export($f['value_received'], true), $f['put_status'], $f['get_status'], $f['match_mode']
            );
        }
        $msg .= "\nADR 0205 — todo PR que regrida contract test bloqueia merge.\n";
        $msg .= "Fix: alinhe validator UpdateServiceOrderRequest + payload show JSON + frontend Edit.tsx.\n";

        expect($failures)->toBeEmpty($msg);
    }

    expect($passed)->toBe($total);
});

/**
 * Helper inline pra match modes — mesma semântica de
 * AutosaveContractRunner::matches() (private). Duplicado aqui pq runner default
 * não cobre PUT (somente patchJson). Quando runner ganhar suporte a method
 * configurable, este test migra pra invocar runner direto.
 */
function matchesValue(mixed $sent, mixed $received, string $match): bool
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
