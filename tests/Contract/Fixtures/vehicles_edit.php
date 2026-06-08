<?php

declare(strict_types=1);

/**
 * Fixture: contract test da tela Vehicles/Edit (Modules/OficinaAuto V0 — ADR 0137).
 *
 * Mesma estratégia do fixture service_order_edit.php — não usa
 * AutosaveContractRunner::run() default pq Vehicles/Edit é PUT form submit
 * (não PATCH per-field). Test file (VehiclesEditAutosaveContractTest) implementa
 * loop inline customizado:
 *   a) PUT /oficina-auto/veiculos/{id} com base_payload + 1 campo alterado
 *   b) GET /oficina-auto/veiculos/{id}/edit com header X-Inertia: true
 *      → lê page.props.vehicle.{recv} pra validar roundtrip
 *
 * Não existe endpoint JSON Accept-aware pra Vehicle (diferente de
 * ServiceOrder que tem alias /oficina-auto/service-orders/{id} retornando JSON
 * shape limpo). Usamos Inertia partial response pra ler props.
 *
 * Cobre TODOS os 14 campos do UpdateVehicleRequest:
 *  - plate (required), secondary_plate, chassis, secondary_chassis,
 *    contact_id, manufacture_year, model_year, renavam, vehicle_type (required),
 *    engine, mileage_at_entry, fuel_type, color, notes
 *
 * NÃO cobre (separation of concerns):
 *  - current_status FSM transition — controlado por ServiceOrder hooks
 *    (createRental/closeRental) em Wave 5+
 *  - current_rental_id — soft FK setada por ServiceOrder, não pelo Edit form
 *  - capacity_m3 — só caçamba_estacionaria, schema extension separada
 *  - legacy_id — UpdateVehicleRequest NÃO permite update (preserva trilha
 *    Firebird importer); StoreVehicleRequest aceita. Test cobre só Update.
 *
 * Aliases PT-BR vs canon EN descobertos:
 *  - Nenhum no UpdateVehicleRequest (todos campos já canon EN). Schema V0
 *    nasceu inglês (plate, vehicle_type, mileage_at_entry, fuel_type) — sem
 *    fase legacy PT-BR como Cliente teve. Mesma situação de ServiceOrder.
 *
 * Validações sintéticas seguras (NÃO PII de cliente real):
 *  - plate: 'TST-{stamp}' (sintético, máx 10 chars — bem dentro do limit
 *    e formato padrão BR sem confundir com placa real)
 *  - chassis: 'CT{stamp}CHASSIS' (sintético, ~15 chars — válido sob max 30)
 *  - renavam: '12345678901' (11 chars exatos, todos dígitos — formato DENATRAN)
 *  - contact_id: usa $this->contactId do setupContext (existe + mesmo business)
 *
 * Multi-tenant Tier 0 (ADR 0093 IRREVOGÁVEL): test setupContext força
 * business_id da sessão; Vehicle tem global scope automático.
 *
 * @see Modules\OficinaAuto\Http\Controllers\VehicleController::update
 * @see Modules\OficinaAuto\Http\Requests\UpdateVehicleRequest
 * @see tests/Contract/README.md — receita
 * @see tests/Contract/Fixtures/service_order_edit.php — fixture irmã (mesmo padrão PUT form)
 * @see ADR 0205 — contract tests autosave canon
 */

return [
    'cadastrais' => [
        // Endpoint PUT (não PATCH — form submit completo). Test file customiza
        // o invoker pra não passar pelo AutosaveContractRunner::run() default.
        'endpoint' => '/oficina-auto/veiculos/{id}',
        'method' => 'put',
        'fields' => [
            // ────────────────────────────────────────────────────────────────
            // Identificação obrigatória (required no validator)
            // ────────────────────────────────────────────────────────────────

            // plate: máx 10 chars. 'TST-{stamp}' = TST- (4) + CT (2) + 4 dígitos = 10 chars.
            // Formato sintético — claramente teste, não confunde com placa Mercosul/antiga real.
            ['send' => 'plate', 'value' => 'TST-{stamp}', 'recv' => 'plate'],

            // vehicle_type: enum required. 'caminhao' existe em VehicleController::vehicleTypes()
            // e cobre sub-vertical 4 mecânica pesada (Martinho).
            ['send' => 'vehicle_type', 'value' => 'caminhao', 'recv' => 'vehicle_type'],

            // ────────────────────────────────────────────────────────────────
            // Identificação opcional (nullable)
            // ────────────────────────────────────────────────────────────────

            // secondary_plate — atende cavalo+reboque (Vargas case ADR 0137 §"20% veículos").
            // Formato curto 'CT{stamp}-2' = CT (2) + 6 chars stamp + -2 (2) = 10 chars (max:10 cap).
            ['send' => 'secondary_plate', 'value' => 'CT{stamp}-2', 'recv' => 'secondary_plate'],

            // chassis — VIN 17 chars padrão, mas validator aceita até 30. Sintético.
            ['send' => 'chassis', 'value' => 'CT{stamp}CHASSIS', 'recv' => 'chassis'],

            // secondary_chassis — chassi do reboque/semi-reboque
            ['send' => 'secondary_chassis', 'value' => 'CT{stamp}CHASSIS2', 'recv' => 'secondary_chassis'],

            // renavam — 11 dígitos exatos (max 11 no validator, padrão DENATRAN)
            ['send' => 'renavam', 'value' => '12345678901', 'recv' => 'renavam'],

            // ────────────────────────────────────────────────────────────────
            // Anos (integer 1900-2100)
            // ────────────────────────────────────────────────────────────────

            ['send' => 'manufacture_year', 'value' => 2024, 'recv' => 'manufacture_year', 'match' => 'int'],
            ['send' => 'model_year', 'value' => 2025, 'recv' => 'model_year', 'match' => 'int'],

            // ────────────────────────────────────────────────────────────────
            // Características técnicas (nullable strings + int)
            // ────────────────────────────────────────────────────────────────

            ['send' => 'engine', 'value' => 'CT-{stamp} V8 Turbo', 'recv' => 'engine'],
            ['send' => 'mileage_at_entry', 'value' => 87654, 'recv' => 'mileage_at_entry', 'match' => 'int'],
            ['send' => 'fuel_type', 'value' => 'diesel', 'recv' => 'fuel_type'],
            ['send' => 'color', 'value' => 'branco', 'recv' => 'color'],

            // ────────────────────────────────────────────────────────────────
            // FK + texto livre
            // ────────────────────────────────────────────────────────────────

            // contact_id — preenchido em runtime no test (depende do setupContext
            // criar contact base no mesmo business). Marker 'CONTACT_ID' substituído
            // pelo test loop antes do PUT.
            ['send' => 'contact_id', 'value' => 'CONTACT_ID', 'recv' => 'contact_id', 'match' => 'int'],

            // notes — texto livre, valida persistência string longa
            ['send' => 'notes', 'value' => 'CT-{stamp} observacao veiculo teste', 'recv' => 'notes'],
        ],
    ],
];
