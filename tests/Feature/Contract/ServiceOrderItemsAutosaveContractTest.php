<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Contract\AutosaveContractRunner;

/**
 * Contract test do CRUD ServiceOrderItem — drawer "PECAS & MAO DE OBRA"
 * de Modules/OficinaAuto (Wave 1.3 US-OFICINA-027).
 *
 * Origem sub-agent ServiceOrder (PR #1795) + ADR 0205: terceira fixture
 * sob padrao canon (apos drawer Cliente + Sells/Create + ServiceOrder/Edit).
 *
 * Cobre POST /oficina-auto/ordens-servico/{id}/items via runner default
 * (suporta `method: post`, `responseRoot: ''`, `expectStatus: 201`,
 * `baseFields`). Setup espelha ServiceOrderEditAutosaveContractTest:
 *   - DB driver MySQL (sqlite skip — schema UPOS+OficinaAuto)
 *   - Tabelas vehicles + service_orders + oficina_service_order_items
 *   - Cria Vehicle + ServiceOrder base ANTES de cada test
 *   - Multi-tenant Tier 0 (ADR 0093) via session['user.business_id']
 *
 * NAO cobre (separation of concerns):
 *   - PUT /items/{item} — endpoint double placeholder, runner default so
 *     substitui {id}. Cobertura: ServiceOrderItemHttpIntegrationTest (cross-OS).
 *   - DELETE /items/{item} — sem bug silencioso possivel. Ja coberto.
 *   - Cross-tenant rejeicao — ja coberto por ServiceOrderItemTest unit.
 *
 * Pra adicionar nova tela: ver tests/Contract/README.md + ADR 0205.
 *
 * @see Modules\OficinaAuto\Http\Controllers\ServiceOrderItemController::store
 * @see tests/Contract/Fixtures/service_order_items.php
 * @see ADR 0205 (contract tests autosave canon)
 */

uses(DatabaseTransactions::class);

beforeEach(function () {
    // Pre-flight 1: DB driver — schema OficinaAuto exige MySQL.
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompativel: requer schema MySQL UltimatePOS + OficinaAuto (ADR 0101)');
    }
    // Pre-flight 2: tabelas OficinaAuto + items (migration 2026_05_17_000010).
    if (! Schema::hasTable('service_orders')
        || ! Schema::hasTable('vehicles')
        || ! Schema::hasTable('oficina_service_order_items')
    ) {
        $this->markTestSkipped('Schema OficinaAuto ausente — rode `php artisan module:migrate OficinaAuto`');
    }

    // Setup multi-tenant (autentica user + session business_id).
    $ctx = AutosaveContractRunner::setupContext($this);
    $this->business = $ctx['business'];
    $this->user = $ctx['user'];
    $this->contactId = $ctx['contactId'];

    // Vehicle base — padrao validado em ServiceOrderEditAutosaveContractTest.
    $plate = 'CTI-' . substr((string) microtime(true), -4);
    $this->vehicleId = DB::table('vehicles')->insertGetId([
        'business_id'  => $this->business->id,
        'plate'        => $plate,
        'vehicle_type' => 'automovel',
        'created_at'   => now(),
        'updated_at'   => now(),
    ]);

    // ServiceOrder base — campos minimos requeridos. Items serao criados
    // contra esta OS via POST nos asserts.
    $this->orderId = DB::table('service_orders')->insertGetId([
        'business_id'        => $this->business->id,
        'vehicle_id'         => $this->vehicleId,
        'status'             => 'aberta',
        'mileage_at_service' => 10000,
        'notes'              => 'CT items baseline',
        'created_at'         => now(),
        'updated_at'         => now(),
    ]);
});

it('ServiceOrder Items — POST /items persiste TODOS os campos do fixture (drawer PECAS & MAO DE OBRA)', function () {
    $fixture = require __DIR__ . '/../../Contract/Fixtures/service_order_items.php';

    // Runner aceita resourceId que substitui {id} no endpoint
    // (/oficina-auto/ordens-servico/{id}/items → .../ordens-servico/123/items).
    $result = AutosaveContractRunner::run($this, $fixture, $this->orderId);

    if ($result['passed'] !== $result['total']) {
        $msg = "❌ Contract test FALHOU — {$result['passed']}/{$result['total']} OK.\n\n";
        $msg .= "Bugs silenciosos detectados no CRUD ServiceOrderItem (drawer PECAS & MAO DE OBRA):\n";
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
        $msg .= "Fix: alinhe StoreServiceOrderItemRequest rules + Controller-store shape JSON +\n";
        $msg .= "      Service::addItem default handling (quantidade/valor_unitario/valor_total).\n";

        expect($result['failures'])->toBeEmpty($msg);
    }

    expect($result['passed'])->toBe($result['total']);
});
