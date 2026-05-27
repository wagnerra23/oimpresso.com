<?php

declare(strict_types=1);

/**
 * Fixture: contract test da tela ServiceOrder/Edit (Modules/OficinaAuto).
 *
 * IMPORTANTE — diferenças do fixture cliente_drawer.php:
 *  1. ServiceOrder/Edit NÃO usa autosave PATCH per-field — usa um único
 *     PUT /oficina-auto/ordens-servico/{id} com payload completo (form submit).
 *  2. Por isso o test file (ServiceOrderEditAutosaveContractTest) NÃO usa
 *     AutosaveContractRunner::run() — usa um loop inline customizado que:
 *       a) Envia PUT com [base_payload + 1 campo alterado] por iteração
 *       b) Verifica roundtrip via GET JSON /oficina-auto/service-orders/{id}
 *          (endpoint Accept-aware do show() retornando JSON)
 *  3. Mesmo princípio: contract test "envia X, lê de volta X" — pegando
 *     bugs silenciosos validator dropping unknown keys, aliases não-mapeados,
 *     campo no payload sem coluna destino, etc.
 *
 * Cobre apenas CAMPOS CADASTRAIS do UpdateServiceOrderRequest:
 *  - notes, status, mileage_at_service, entered_at, expected_completion,
 *    completed_at, delivered_at
 *
 * NÃO cobre (separation of concerns):
 *  - FSM Pipeline transitions (current_stage_id) — ADR 0143 tem test próprio
 *    via ServiceOrderFsmActionController + ExecuteStageActionService
 *  - Items CRUD (peças/mão-obra) — ServiceOrderItemController tem
 *    ServiceOrderItemHttpIntegrationTest dedicado
 *  - DVI inspection — DviInspectionController + DviInspectionItemTest
 *  - Vehicle assignment swap — VehicleCrudTest cobre
 *
 * Aliases PT-BR vs canon EN descobertos:
 *  - Nenhum no UpdateServiceOrderRequest (todos campos já canon EN).
 *    Schema histórico V0 nasceu inglês (vehicle_id, mileage_at_service,
 *    entered_at, expected_completion, completed_at, delivered_at) — não
 *    teve fase legacy PT-BR como Cliente teve (nome/doc/tel etc).
 *
 * Multi-tenant Tier 0 (ADR 0093 IRREVOGÁVEL): test setupContext força
 * business_id da sessão; ServiceOrder + Vehicle têm global scope.
 *
 * @see Modules\OficinaAuto\Http\Controllers\ServiceOrderController::update
 * @see Modules\OficinaAuto\Http\Requests\UpdateServiceOrderRequest
 * @see tests/Contract/README.md — receita
 * @see ADR 0205 — contract tests autosave canon
 * @see ADR 0143 — FSM Pipeline (NÃO coberto aqui)
 */

return [
    'cadastrais' => [
        // Endpoint PUT (não PATCH — form submit completo). Test file customiza
        // o invoker pra não passar pelo AutosaveContractRunner::run() default.
        'endpoint' => '/oficina-auto/ordens-servico/{id}',
        'method' => 'put',
        'fields' => [
            // Campo livre — string maior comum, valida persistência texto.
            ['send' => 'notes', 'value' => 'CT-{stamp} obs servico', 'recv' => 'notes'],

            // Status livre na V0 (FSM enum em US-OFICINA-003). Aqui valida
            // que string aceita roundtrips. NÃO testa transição FSM — só
            // grava o campo direto (validator não plumbing pipeline).
            ['send' => 'status', 'value' => 'em_servico', 'recv' => 'status'],

            // Integer cast — mileage_at_service na entrada do veículo.
            ['send' => 'mileage_at_service', 'value' => 45678, 'recv' => 'mileage_at_service', 'match' => 'int'],

            // datetime — entrada do veículo. Match partial pois backend retorna
            // formato ISO completo "2026-05-27T10:00:00.000000Z" e envio
            // "2026-05-27 10:00:00". Substring "2026-05-27" cobre o roundtrip.
            ['send' => 'entered_at', 'value' => '2026-05-27 10:00:00', 'recv' => 'entered_at', 'match' => 'partial'],

            ['send' => 'expected_completion', 'value' => '2026-05-28 18:00:00', 'recv' => 'expected_completion', 'match' => 'partial'],

            // completed_at/delivered_at — só liberados no Update (não Store)
            // conforme docblock UpdateServiceOrderRequest. Cobrir aqui valida
            // o gate de schema sem disparar FSM (que move via ExecuteStageActionService).
            ['send' => 'completed_at', 'value' => '2026-05-29 16:00:00', 'recv' => 'completed_at', 'match' => 'partial'],

            ['send' => 'delivered_at', 'value' => '2026-05-30 09:30:00', 'recv' => 'delivered_at', 'match' => 'partial'],
        ],
    ],
];
