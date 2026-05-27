<?php

declare(strict_types=1);

/**
 * Fixture: contract test do modal Sells/Edit Shipping (legacy Blade).
 *
 * Wagner 2026-05-27 — 9o fixture sob padrao ADR 0205 (apos NFe/Config).
 *
 * Endpoint: PUT /sells/update-shipping/{id} (SellController::updateShipping
 * linha ~3374 — legacy Blade modal acessado via dropdown "Editar entrega"
 * na listagem de vendas).
 *
 * IMPORTANTE — diferencas vs fixtures anteriores (cliente_drawer, etc):
 *
 *  1. NAO ha autosave PATCH per-field — eh form submit unico PUT com payload
 *     completo (mesmo padrao service_order_edit, que tambem usa loop custom).
 *
 *  2. CRITICO: a resposta JSON NAO retorna o Transaction. updateShipping faz:
 *       $transaction->update($input);
 *       return ['success' => 1, 'msg' => trans('lang_v1.updated_success')];
 *     Ou seja — sem `responseRoot`, o runner default nao consegue verificar
 *     roundtrip. Test file usa loop custom inline com **DB roundtrip**:
 *     PUT + query SELECT direta em `transactions` pra ler o valor de volta.
 *
 *  3. Por isso este fixture NAO declara `responseRoot` nem `method` — o test
 *     file ignora a chamada AutosaveContractRunner::run() e implementa o
 *     loop manual (igual ServiceOrderEditAutosaveContractTest).
 *
 * Cobre os 10 campos do $request->only([...]) em updateShipping:
 *   - shipping_details, shipping_address (text livres)
 *   - shipping_status (enum: ordered/packed/shipped/delivered/cancelled)
 *   - delivered_to, delivery_person (string)
 *   - shipping_custom_field_1..5 (string custom fields UPOS)
 *
 * Aliases PT-BR vs canon EN descobertos:
 *   - Nenhum. updateShipping recebe e grava chaves identicas (canon EN UPOS).
 *     Sem alias = sem oportunidade de bug silencioso por chave dropping. Mas
 *     o test ainda vale pq protege contra REGRESSAO se alguem mudar o
 *     $request->only([...]) ou cast no Transaction model.
 *
 * NAO cobre:
 *   - shipping_note (vai pra activityLog, nao pra coluna — diferente proposito)
 *   - shipping_charges (campo separado em outro fluxo, nao no updateShipping)
 *   - Pageshape do modal editShipping (Blade legacy — nao migrou pra Inertia)
 *
 * Multi-tenant Tier 0 (ADR 0093 IRREVOGAVEL): setupSellsContext garante
 * business_id na sessao + transaction stub no biz alvo. updateShipping faz
 * Transaction::where('business_id', $business_id)->findOrFail($id) — Tier 0
 * preservado.
 *
 * @see app/Http/Controllers/SellController.php::updateShipping (linha ~3374)
 * @see routes/web.php linha 647 PUT sells/update-shipping/{id}
 * @see app/Utils/Util.php::shipping_statuses (enum values)
 * @see tests/Contract/README.md — receita
 * @see ADR 0205 — contract tests autosave canon
 */

return [
    'shipping' => [
        // PUT (legacy Blade form submit). Test file usa loop custom — esta
        // chave eh apenas descritiva pra agrupamento e mensagem de erro.
        'endpoint' => '/sells/update-shipping/{id}',
        'method' => 'put',
        'fields' => [
            // Campo texto livre — observacoes do envio. Bug silencioso classico
            // seria typo no $request->only() (ex. 'shipping_detail' singular).
            ['send' => 'shipping_details', 'value' => 'CT-{stamp} detalhes envio teste', 'recv' => 'shipping_details'],

            // Endereco de entrega completo — text field. Verifica que coluna
            // aceita string com quebras de linha (multiline address comum).
            ['send' => 'shipping_address', 'value' => "CT-{stamp} Rua Teste 123\nSala 45 - Centro\nSao Paulo SP", 'recv' => 'shipping_address'],

            // Enum shipping_status — valores vindos de Util::shipping_statuses():
            // ordered/packed/shipped/delivered/cancelled. Testa o estado
            // intermediario "shipped" (em transito) — cobre o roundtrip do enum.
            ['send' => 'shipping_status', 'value' => 'shipped', 'recv' => 'shipping_status'],

            // delivered_to — quem recebeu (string livre). Comum esquecer essa
            // chave em $request->only() ao adicionar nova feature.
            ['send' => 'delivered_to', 'value' => 'CT-{stamp} Joao Recebedor', 'recv' => 'delivered_to'],

            // delivery_person — entregador (migration 2023_06_21 adicionou).
            // Bug silencioso historico: a coluna pode nao existir em instalacoes
            // sem essa migration. Schema pre-flight no test file pega isso.
            ['send' => 'delivery_person', 'value' => 'CT-{stamp} Maria Entregadora', 'recv' => 'delivery_person'],

            // Custom fields 1..5 — string genericos UPOS (migration 2020_12_18).
            // Comum perder algum em refactor — cobrir todos os 5 protege contra
            // typo numerico (ex. esquecer shipping_custom_field_3).
            ['send' => 'shipping_custom_field_1', 'value' => 'CT-{stamp} custom1', 'recv' => 'shipping_custom_field_1'],
            ['send' => 'shipping_custom_field_2', 'value' => 'CT-{stamp} custom2', 'recv' => 'shipping_custom_field_2'],
            ['send' => 'shipping_custom_field_3', 'value' => 'CT-{stamp} custom3', 'recv' => 'shipping_custom_field_3'],
            ['send' => 'shipping_custom_field_4', 'value' => 'CT-{stamp} custom4', 'recv' => 'shipping_custom_field_4'],
            ['send' => 'shipping_custom_field_5', 'value' => 'CT-{stamp} custom5', 'recv' => 'shipping_custom_field_5'],
        ],
    ],
];
