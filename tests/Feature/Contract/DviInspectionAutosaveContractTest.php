<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\OficinaAuto\Entities\OaInspectionItem;
use Tests\Contract\AutosaveContractRunner;

/**
 * Contract test do CRUD DviInspection — drawer "VISTORIA DIGITAL · DVI"
 * de Modules/OficinaAuto (Wave 3 US-OFICINA-035).
 *
 * Origem ADR 0205 + roadmap fixtures Tier 1: nona fixture canônica
 * (após cliente_drawer, service_order_edit, sells_create, service_order_items,
 * produto_edit, vehicles_edit, compras_create, nfe_config) cobrindo o terceiro
 * endpoint crítico do drawer da OS — CRUD de itens DVI (vistoria com semáforo).
 *
 * Cobre PUT /oficina-auto/ordens-servico/{order}/dvi/{item} via runner default
 * (suporta `method: put`, `responseRoot: 'item'`, `expectStatus: 200`,
 * `baseFields`). Setup espelha ServiceOrderItemsAutosaveContractTest pattern,
 * acrescentando criação de OaInspectionItem base ANTES de cada test:
 *   - DB driver MySQL (sqlite skip — schema UPOS+OficinaAuto)
 *   - Tabelas vehicles + service_orders + oa_inspection_items
 *   - Cria Vehicle + ServiceOrder + OaInspectionItem base no beforeEach
 *   - Multi-tenant Tier 0 (ADR 0093) via session['user.business_id']
 *
 * DESAFIO técnico — endpoint com 2 placeholders ({order}/{item}):
 *   Runner default só substitui `{id}` no endpoint. Para DVI update precisamos
 *   resolver `{order}` ANTES de chamar runner.run (Opção A do plano), deixando
 *   `{id}` (= $this->itemId) pra runner default resolver.
 *
 *   Solução adotada — clone do fixture com `{order}` já substituído:
 *     $fixture = require __DIR__ . '/../../Contract/Fixtures/dvi_inspection.php';
 *     $fixture['dvi_update']['endpoint'] = str_replace(
 *         '{order}',
 *         (string) $this->orderId,
 *         $fixture['dvi_update']['endpoint']
 *     );
 *     AutosaveContractRunner::run($this, $fixture, $this->itemId);
 *
 *   Quando runner ganhar suporte multi-placeholder, este test migra pra
 *   passar map `['order' => $orderId, 'item' => $itemId]` direto.
 *
 * NÃO cobre (separation of concerns):
 *   - POST .../dvi (store) — DviInspectionItemTest cobre Service::addItem unit
 *   - DELETE .../dvi/{item} — sem bug silencioso possível (204 + soft delete)
 *   - POST .../dvi/{item}/photo — multipart upload, fixture próprio futuro
 *   - `metadata` array livre — sem shape fixo, fica pra iteração futura
 *   - `client_decision` Wave 3b — endpoint separado mobile aprovação
 *   - Cross-OS guard + Policy update — testes dedicados já cobrem
 *
 * Pra adicionar nova tela: ver tests/Contract/README.md + ADR 0205.
 *
 * @see Modules\OficinaAuto\Http\Controllers\DviInspectionController::update
 * @see Modules\OficinaAuto\Http\Requests\UpdateDviRequest
 * @see tests/Contract/Fixtures/dvi_inspection.php
 * @see ADR 0205 (contract tests autosave canon)
 */

uses(DatabaseTransactions::class);

beforeEach(function () {
    // Pré-flight 1: DB driver — schema OficinaAuto exige MySQL (ADR 0101).
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompativel: requer schema MySQL UltimatePOS + OficinaAuto (ADR 0101)');
    }
    // Pré-flight 2: tabelas OficinaAuto + DVI (migration 2026_05_26_120002).
    if (! Schema::hasTable('service_orders')
        || ! Schema::hasTable('vehicles')
        || ! Schema::hasTable('oa_inspection_items')
    ) {
        $this->markTestSkipped('Schema OficinaAuto ausente — rode `php artisan module:migrate OficinaAuto`');
    }

    // Setup multi-tenant (autentica user + session business_id) — reusa runner helper.
    $ctx = AutosaveContractRunner::setupContext($this);
    $this->business = $ctx['business'];
    $this->user = $ctx['user'];
    $this->contactId = $ctx['contactId'];

    // Vehicle base — padrão validado em ServiceOrderEditAutosaveContractTest.
    $plate = 'CTD-' . substr((string) microtime(true), -4);
    $this->vehicleId = DB::table('vehicles')->insertGetId([
        'business_id'  => $this->business->id,
        'plate'        => $plate,
        'vehicle_type' => 'automovel',
        'created_at'   => now(),
        'updated_at'   => now(),
    ]);

    // ServiceOrder base — campos mínimos requeridos. Items DVI atualizados via
    // PUT contra esta OS nos asserts.
    $this->orderId = DB::table('service_orders')->insertGetId([
        'business_id'        => $this->business->id,
        'vehicle_id'         => $this->vehicleId,
        'status'             => 'aberta',
        'mileage_at_service' => 10000,
        'notes'              => 'CT dvi baseline',
        'created_at'         => now(),
        'updated_at'         => now(),
    ]);

    // OaInspectionItem base — item DVI inicial pra cada test atualizar via PUT.
    // Valores iniciais usam constantes canônicas pra evitar mismatch enum DB/PHP.
    $this->itemId = DB::table('oa_inspection_items')->insertGetId([
        'business_id'      => $this->business->id,
        'service_order_id' => $this->orderId,
        'categoria'        => OaInspectionItem::CATEGORIAS[0],          // motor
        'descricao'        => 'CT dvi item baseline',
        'severity'         => OaInspectionItem::SEVERITIES_VALIDAS[0],  // ok
        'sort_order'       => 0,
        'created_at'       => now(),
        'updated_at'       => now(),
    ]);
});

it('DviInspection — PUT /dvi/{item} persiste TODOS os campos cadastrais do fixture (drawer VISTORIA DIGITAL)', function () {
    $fixture = require __DIR__ . '/../../Contract/Fixtures/dvi_inspection.php';

    // Resolve {order} ANTES do runner — runner default só substitui {id}.
    // Estratégia validada no docblock do fixture (Opção A do plano).
    $fixture['dvi_update']['endpoint'] = str_replace(
        '{order}',
        (string) $this->orderId,
        $fixture['dvi_update']['endpoint']
    );

    // Runner aceita resourceId que substitui {id} no endpoint
    // (/oficina-auto/ordens-servico/123/dvi/{id} → .../dvi/456).
    $result = AutosaveContractRunner::run($this, $fixture, $this->itemId);

    if ($result['passed'] !== $result['total']) {
        $msg = "❌ Contract test FALHOU — {$result['passed']}/{$result['total']} OK.\n\n";
        $msg .= "Bugs silenciosos detectados no CRUD DviInspection (drawer VISTORIA DIGITAL · DVI):\n";
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
        $msg .= "Fix: alinhe UpdateDviRequest rules + DviInspectionController::shapeItem JSON +\n";
        $msg .= "      OaInspectionItem casts (categoria/severity enum, valor_recomendado decimal:2).\n";

        expect($result['failures'])->toBeEmpty($msg);
    }

    expect($result['passed'])->toBe($result['total']);
});
