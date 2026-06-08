<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Contract\AutosaveContractRunner;

/**
 * Contract test da tela Vehicles/Edit (Modules/OficinaAuto V0 — ADR 0137).
 *
 * Ver fixture `tests/Contract/Fixtures/vehicles_edit.php` pro detalhe dos
 * 14 campos cobertos + justificativa de por que NÃO usa
 * AutosaveContractRunner::run() default (Vehicles/Edit é PUT form submit,
 * não PATCH per-field autosave — mesmo padrão de ServiceOrder/Edit).
 *
 * Pattern segue ServiceOrderEditAutosaveContractTest mas com 1 adaptação:
 *   - GET roundtrip lê via Inertia partial response (X-Inertia: true)
 *     da rota /oficina-auto/veiculos/{id}/edit, extraindo page.props.vehicle.{field}.
 *     ServiceOrder usa endpoint JSON Accept-aware (/service-orders/{id}),
 *     mas Vehicle não tem alias JSON — Inertia é o canal disponível.
 *
 * Multi-tenant Tier 0 (ADR 0093 IRREVOGÁVEL) preservado via setupContext +
 * session['user.business_id'] + global scope Vehicle/Contact.
 *
 * Vertical OficinaAuto pode estar disabled em algum biz — skip graceful
 * via Schema::hasTable check (mesmo padrão fixture irmã).
 *
 * @see Modules\OficinaAuto\Http\Controllers\VehicleController::update
 * @see Modules\OficinaAuto\Http\Requests\UpdateVehicleRequest
 * @see tests/Contract/Fixtures/vehicles_edit.php
 * @see tests/Feature/Contract/ServiceOrderEditAutosaveContractTest.php (test irmão)
 * @see ADR 0205 (contract tests autosave canon)
 */

uses(DatabaseTransactions::class);

beforeEach(function () {
    // Pré-flight 1: DB driver — schema OficinaAuto exige MySQL.
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: requer schema MySQL UltimatePOS + OficinaAuto (ADR 0101)');
    }
    // Pré-flight 2: tabela OficinaAuto. Quando módulo ainda não instalou neste env.
    if (! Schema::hasTable('vehicles')) {
        $this->markTestSkipped('Schema OficinaAuto ausente — rode `php artisan module:migrate OficinaAuto`');
    }

    // Setup multi-tenant (autentica user + session business_id) — reusa runner helper.
    // Cria também contact base que usamos como contact_id válido (mesmo business).
    $ctx = AutosaveContractRunner::setupContext($this);
    $this->business = $ctx['business'];
    $this->user = $ctx['user'];
    $this->contactId = $ctx['contactId'];

    // Cria Vehicle base com campos mínimos requeridos pelo validator (plate + vehicle_type).
    // Inserção direta no DB pra evitar fillable/observer side-effects no setup.
    // business_id explícito pra honrar global scope (session já populada acima).
    $plate = 'CTS-' . substr((string) microtime(true), -4);
    $this->vehicleId = DB::table('vehicles')->insertGetId([
        'business_id'  => $this->business->id,
        'plate'        => $plate,
        'vehicle_type' => 'automovel',
        'created_at'   => now(),
        'updated_at'   => now(),
    ]);
});

it('Vehicles/Edit — PUT endpoint persiste TODOS os 14 campos do fixture (roundtrip via Inertia GET)', function () {
    $fixture = require __DIR__ . '/../../Contract/Fixtures/vehicles_edit.php';
    $stamp = 'CT' . substr((string) microtime(true), -4);
    $passed = 0;
    $total = 0;
    $failures = [];

    foreach ($fixture as $tabName => $tabSpec) {
        $endpoint = str_replace('{id}', (string) $this->vehicleId, $tabSpec['endpoint']);
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

            // Substitui marker CONTACT_ID pelo contactId real do setupContext
            // (contact_id precisa ser FK válida em contacts mesmo business).
            if ($sent === 'CONTACT_ID') {
                $sent = $this->contactId;
            }

            // PUT precisa de payload completo (plate + vehicle_type required).
            // Mergeamos base + 1 campo alterado pra cada iteração — outros campos
            // ficam com valor neutro pra não interferir.
            $base = [
                'plate'        => 'BASE-' . substr((string) microtime(true), -3),
                'vehicle_type' => 'automovel',
            ];
            $payload = array_merge($base, [$sendKey => $sent]);

            // 1) PUT — envia payload. Controller redireciona pro show (302) — sucesso.
            $putResponse = $this->put($endpoint, $payload);
            $putStatus = $putResponse->status();

            // PUT retorna 302 (redirect pra show) em caso sucesso ou 422 em validation fail.
            $putOk = in_array($putStatus, [200, 302], true);

            // 2) GET Inertia — lê page.props.vehicle.{recv} de volta via Edit route.
            // X-Inertia header faz o middleware retornar JSON envelope (não HTML wrapper).
            $getResponse = $this->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => '1'])
                ->get('/oficina-auto/veiculos/' . $this->vehicleId . '/edit');
            $getStatus = $getResponse->status();

            $page = $getResponse->headers->get('X-Inertia')
                ? json_decode($getResponse->getContent(), true)
                : null;
            $received = data_get($page, 'props.vehicle.' . $recvKey);

            $valueOk = matchesVehicleValue($sent, $received, $match);

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
    // ServiceOrderEditAutosaveContractTest pattern.
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
        $msg .= "Fix: alinhe validator UpdateVehicleRequest + props vehicle no Edit + frontend Edit.tsx.\n";

        expect($failures)->toBeEmpty($msg);
    }

    expect($passed)->toBe($total);
});

/**
 * Helper inline pra match modes — mesma semântica de
 * AutosaveContractRunner::matches() (private) e ServiceOrderEditAutosaveContractTest.
 * Duplicado aqui pra evitar coupling cross-file até runner ganhar suporte a
 * PUT + Inertia roundtrip configurable.
 *
 * Nome `matchesVehicleValue` (não `matchesValue`) pra evitar function collision
 * com o helper de ServiceOrderEditAutosaveContractTest quando Pest carrega ambos
 * tests no mesmo run.
 */
function matchesVehicleValue(mixed $sent, mixed $received, string $match): bool
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
