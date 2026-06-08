<?php

declare(strict_types=1);

/**
 * Fixture: contract test do CRUD ServiceOrderItem (drawer "PECAS & MAO DE OBRA"
 * de Modules/OficinaAuto). Wave 1.3 US-OFICINA-027.
 *
 * Origem ADR 0205 + sugestao sub-agent ServiceOrder (PR #1795): a drawer
 * "PECAS & MAO DE OBRA" da OS chama POST /oficina-auto/ordens-servico/{id}/items
 * pra criar linhas de peca / mao-de-obra / servico terceiro. Sem teste, bug
 * silencioso possivel se Controller-store deixar de retornar algum campo no
 * shape JSON ou se validator dropar chave inesperada.
 *
 * Cobertura honesta (NAO inventa endpoint que a tela nao tem):
 *   - POST /oficina-auto/ordens-servico/{id}/items (store)
 *     7 campos cobertos: tipo, descricao, quantidade, valor_unitario,
 *     valor_total, product_id, notes
 *
 * NAO cobre (separation of concerns):
 *   - PUT /items/{item} — endpoint usa DOUBLE placeholder {order}+{item},
 *     runner default so substitui {id}. Quando runner ganhar suporte
 *     multi-placeholder, migrar pra cobrir update aqui. Por enquanto
 *     ServiceOrderItemHttpIntegrationTest cobre cross-OS guard + DELETE.
 *   - DELETE /items/{item} — sem bug silencioso possivel (resposta booleana
 *     deleted=true / id; ServiceOrderItemHttpIntegrationTest ja cobre).
 *   - Auto-calc valor_total no Model creating/updating hook — ja coberto por
 *     ServiceOrderItemTest unitario.
 *
 * Bugs evitados por este fixture:
 *   - Validator dropar `notes` (cliente final precisa enxergar observacao da
 *     peca pra aprovar via WhatsApp+PIN — G2 CAPTERRA OficinaAuto)
 *   - Controller-store mudar `responseRoot` (atualmente flat top-level)
 *   - Cast decimal:3 em quantidade ser revertido pra int (suporta 0.250L oleo)
 *   - Enum `tipo` aceitar valor invalido (apenas peca/mao_obra/servico_terceiro)
 *
 * Multi-tenant Tier 0 (ADR 0093 IRREVOGAVEL): test file cria Vehicle +
 * ServiceOrder base ANTES de cada test (setup pattern espelhado de
 * ServiceOrderEditAutosaveContractTest). business_id derivado de session
 * (NUNCA do payload — ja garantido por ServiceOrderItemController + Service).
 *
 * Setup do test (no test file, nao aqui):
 *   1. setupContext() — user autenticado + session business_id
 *   2. DB::table('vehicles')->insert — Vehicle base
 *   3. DB::table('service_orders')->insert — OS base apontando pro vehicle
 *   4. Endpoint usa {id} = $this->orderId
 *
 * @see Modules\OficinaAuto\Http\Controllers\ServiceOrderItemController::store
 * @see Modules\OficinaAuto\Http\Requests\StoreServiceOrderItemRequest
 * @see Modules\OficinaAuto\Services\ServiceOrderItemService::addItem
 * @see Modules\OficinaAuto\Entities\ServiceOrderItem
 * @see tests/Contract/README.md — receita
 * @see ADR 0205 — contract tests autosave canon
 * @see memory/requisitos/OficinaAuto/CAPTERRA-FICHA.md G1 (origem P0 fatal)
 */

return [
    // ============================================================
    // POST /oficina-auto/ordens-servico/{id}/items (store)
    // ============================================================
    // Endpoint cria item novo a cada iteracao. Resposta flat top-level:
    //   { id, tipo, descricao, quantidade, valor_unitario, valor_total,
    //     product_id, notes, created_at }
    // responseRoot '' = ler chave direto da raiz JSON.
    //
    // baseFields injeta os campos `required` do StoreServiceOrderItemRequest
    // (tipo + descricao) em TODA iteracao pra payload sempre passar validator.
    // Quando o test varia `tipo` ou `descricao`, baseFields fica sobrescrito
    // pelo array_merge no AutosaveContractRunner::buildPayload.
    'pecas_mao_obra' => [
        'endpoint' => '/oficina-auto/ordens-servico/{id}/items',
        'method' => 'post',
        'responseRoot' => '',           // resposta flat — sem wrapper
        'expectStatus' => 201,          // store retorna 201 Created
        'payloadShape' => 'flat',
        'baseFields' => [
            // Campos required (Store validator) — sempre presentes.
            'tipo' => 'peca',
            'descricao' => 'CT-{stamp} peca baseline',
            // Campos opcionais com defaults seguros (validator aceita).
            'quantidade' => 1,
            'valor_unitario' => 0,
        ],
        'fields' => [
            // Enum `tipo` — valido: peca / mao_obra / servico_terceiro.
            // Match equals — backend retorna string canon.
            ['send' => 'tipo', 'value' => 'mao_obra', 'recv' => 'tipo'],

            // descricao — string ate 255. Bug silencioso classico se Controller
            // deixar de incluir no shape de resposta.
            ['send' => 'descricao', 'value' => 'CT-{stamp} Troca pastilha freio', 'recv' => 'descricao'],

            // quantidade — decimal:3 (suporta 0.250L oleo). Controller faz cast
            // (float) na resposta, entao 2 vai chegar como 2 (int em json) ou 2.0.
            // match `int` pra normalizar comparacao independente de PHP json_encode.
            ['send' => 'quantidade', 'value' => 2, 'recv' => 'quantidade', 'match' => 'int'],

            // valor_unitario — decimal:2. Mesma logica: cast (float) na resposta.
            // Match equals — 99.99 == 99.99 sobrevive cast (PHP json_encode preserva
            // 2 casas como "99.99"). Se backend retornar string "99.99" vs float 99.99
            // o `(string)$received === (string)$sent` no default match cobre.
            ['send' => 'valor_unitario', 'value' => 99.99, 'recv' => 'valor_unitario'],

            // valor_total — override (Service aceita pra descontos/promo). Quando
            // enviado, NAO recalcula auto (modelo so recalcula se ausente).
            // Garante que enviar 50.00 explicito persiste 50.00 (nao 1 x 0 = 0).
            ['send' => 'valor_total', 'value' => 50.00, 'recv' => 'valor_total'],

            // product_id — nullable integer. Catalogo UPOS legacy. Testa que NULL
            // explicito persiste como null no shape de retorno (Controller usa
            // $item->product_id direto, sem coerce). Sem teste, bug silencioso
            // se Controller fizer (int) $item->product_id e virar 0.
            // Valor sintetico: 1 (id integer valido, sem validar FK exists pq
            // validator nao tem rule `exists` em product_id — schema legacy varia).
            ['send' => 'product_id', 'value' => 1, 'recv' => 'product_id', 'match' => 'int'],

            // notes — nullable string ate 1000. CRITICO pra CAPTERRA G1:
            // cliente final lê notes na aprovacao WhatsApp+PIN ("Pastilha freio
            // R$ [redacted Tier 0] — observacao: pastilha original Bosch"). Sem teste, validator
            // pode dropar e cliente aprova sem ver detalhe que mata confianca.
            ['send' => 'notes', 'value' => 'CT-{stamp} pastilha original Bosch', 'recv' => 'notes'],
        ],
    ],
];
