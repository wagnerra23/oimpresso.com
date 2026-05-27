<?php

declare(strict_types=1);

/**
 * Fixture: contract test do CRUD DviInspection (drawer "VISTORIA DIGITAL · DVI"
 * de Modules/OficinaAuto). Wave 3 OficinaAuto US-OFICINA-035.
 *
 * Origem ADR 0205 + roadmap fixtures Tier 1: complementa
 * service_order_edit.php (cadastrais da OS) + service_order_items.php (peças
 * & mão de obra) cobrindo o terceiro endpoint crítico do drawer da OS — o
 * CRUD de itens DVI (vistoria digital com semáforo).
 *
 * O CRUD DviInspection é wedge competitivo CAPTERRA-FICHA Repair gap #3 —
 * RepairShopr/mHelpDesk cobram US$ 49/mês addon DVI; oimpresso entrega inline.
 * Mecânico registra item por item (motor/freios/pneus etc.) com severity
 * ok/atencao/critico + valor recomendado pra cliente aprovar via WhatsApp+PIN
 * (US-OFICINA-035 Wave 3b). Sem teste contract, bug silencioso possível se:
 *   - Validator (UpdateDviRequest) deixar de aceitar `categoria` ou `severity`
 *     enum novo (extensão futura) — silent drop
 *   - Controller shapeItem omitir `recomendacao` ou `valor_recomendado` no
 *     JSON response — frontend trata como null, perda silenciosa
 *   - Cast decimal:2 em `valor_recomendado` revertido pra string sem ponto
 *     decimal (cliente WhatsApp não enxerga "R$ [redacted Tier 0]" e aprova R$ [redacted Tier 0])
 *   - Enum `categoria` aceitar valor inválido por mismatch entre
 *     OaInspectionItem::CATEGORIAS PHP e enum DB column (migration drift)
 *
 * Cobertura honesta (NÃO inventa endpoint que a tela não tem):
 *   - PUT /oficina-auto/ordens-servico/{order}/dvi/{item} (update)
 *     7 campos cadastrais cobertos:
 *       categoria, descricao, severity, recomendacao,
 *       valor_recomendado, sort_order, photo_url
 *
 * NÃO cobre (separation of concerns):
 *   - POST /oficina-auto/ordens-servico/{order}/dvi (store) — Service::addItem
 *     já coberto por DviInspectionItemTest unit + Service tests dedicados
 *   - DELETE /oficina-auto/ordens-servico/{order}/dvi/{item} — sem bug silencioso
 *     possível (204 + soft delete), já coberto
 *   - POST .../dvi/{item}/photo — upload multipart, fixture próprio futuro
 *     (ArquivosService::attach tem comportamento próprio: bucket + signed URLs)
 *   - `metadata` campo array — sem schema fixo, validator aceita qualquer JSON.
 *     Cobertura futura quando frontend padronizar shape (vida_util_pct,
 *     km_restantes, voltagem) — iteração separada
 *   - `client_decision` + `client_decided_at` — Wave 3b mobile aprovação,
 *     endpoint separado quando US-OFICINA-035 Wave 3b sair
 *   - Cross-OS guard (abort_unless service_order_id === order) — já coberto por
 *     DviInspectionControllerTest dedicado (HTTP integration)
 *   - Policy `update(ServiceOrder)` — ServiceOrderPolicyTest cobre Spatie
 *     permission + sameTenant guard
 *
 * Aliases PT-BR vs canon EN descobertos:
 *   - Nenhum no UpdateDviRequest (todos campos já canon PT-BR consistente:
 *     categoria/descricao/severity/recomendacao/valor_recomendado/photo_url/
 *     sort_order). Schema nasceu PT-BR — espelha vocabulário mecânico real
 *     ("vistoria", "semáforo verde/amarelo/vermelho", "recomendação").
 *
 * IMPORTANTE — diferença vs fixture service_order_items.php:
 *   1. Endpoint DVI update tem DOIS placeholders ({order}/{item}), não um só.
 *      Runner default só substitui `{id}`. Solução adotada (Opção A do plano):
 *      o test file substitui `{order}` no endpoint string ANTES de chamar
 *      runner, deixando só `{id}` (= itemId) pra runner resolver.
 *      Endpoint final no fixture mantém `{order}` literal — test file injeta
 *      orderId em runtime via str_replace.
 *   2. Método PUT (não POST) — endpoint update, item já existe (criado no
 *      beforeEach via DB::table insert).
 *
 * Multi-tenant Tier 0 (ADR 0093 IRREVOGÁVEL): test file cria Vehicle +
 * ServiceOrder + OaInspectionItem base ANTES de cada test (setup pattern
 * espelhado de ServiceOrderItemsAutosaveContractTest). business_id derivado
 * de session (NUNCA do payload — global scope OaInspectionItem garante).
 *
 * Setup do test (no test file, não aqui):
 *   1. setupContext() — user autenticado + session business_id
 *   2. DB::table('vehicles')->insert — Vehicle base
 *   3. DB::table('service_orders')->insert — OS base apontando pro vehicle
 *   4. DB::table('oa_inspection_items')->insert — item DVI base apontando pro OS
 *   5. Endpoint resolve `{order}` → $this->orderId em runtime, `{id}` → $itemId
 *
 * @see Modules\OficinaAuto\Http\Controllers\DviInspectionController::update
 * @see Modules\OficinaAuto\Http\Requests\UpdateDviRequest
 * @see Modules\OficinaAuto\Entities\OaInspectionItem (CATEGORIAS, SEVERITIES_VALIDAS)
 * @see Modules\OficinaAuto\Database\Migrations\2026_05_26_120002_create_oa_inspection_items_table.php
 * @see tests/Contract/README.md — receita
 * @see ADR 0205 — contract tests autosave canon
 * @see memory/requisitos/Repair/CAPTERRA-FICHA.md gap #3 DVI (origem competitiva)
 */

use Modules\OficinaAuto\Entities\OaInspectionItem;

return [
    // ============================================================
    // PUT /oficina-auto/ordens-servico/{order}/dvi/{id} (update)
    // ============================================================
    // Resposta wrapped em `item`:
    //   { item: { id, categoria, descricao, severity, recomendacao,
    //             valor_recomendado, metadata, photo_url, sort_order } }
    // responseRoot 'item' = ler chave dentro do wrapper item.
    //
    // baseFields injeta os campos `required` (sometimes+required do
    // UpdateDviRequest fica required quando enviado) em TODA iteração pra
    // payload sempre passar validator. Quando o test varia `categoria` ou
    // `severity`, baseFields fica sobrescrito pelo array_merge no
    // AutosaveContractRunner::buildPayload.
    'dvi_update' => [
        // {order} resolvido pelo test file (str_replace antes do runner.run).
        // {id} resolvido pelo runner default = OaInspectionItem id.
        'endpoint' => '/oficina-auto/ordens-servico/{order}/dvi/{id}',
        'method' => 'put',
        'responseRoot' => 'item',        // resposta wrapped em { item: {...} }
        'expectStatus' => 200,           // update retorna 200 OK
        'payloadShape' => 'flat',
        'baseFields' => [
            // Campos `sometimes required` do UpdateDviRequest — quando o
            // request inclui qualquer um destes, ele DEVE ser válido. Como
            // PUT envia payload completo (com baseFields), garantir valores
            // padrão válidos pra não disparar 422.
            // OaInspectionItem::CATEGORIAS[0] = 'motor'
            // OaInspectionItem::SEVERITIES_VALIDAS[0] = 'ok'
            'categoria' => OaInspectionItem::CATEGORIAS[0],
            'descricao' => 'CT-{stamp} baseline dvi',
            'severity'  => OaInspectionItem::SEVERITIES_VALIDAS[0],
        ],
        'fields' => [
            // categoria — enum Rule::in(CATEGORIAS). Bug silencioso se DB enum
            // ficar desalinhado com const PHP. Teste com valor != baseline pra
            // forçar mudança detectável (motor → freios).
            ['send' => 'categoria', 'value' => 'freios', 'recv' => 'categoria'],

            // descricao — string max 150. Bug silencioso clássico se Controller
            // shapeItem deixar de incluir no JSON. Limite 150 testado com
            // substring curta pra não estourar com `CT-{stamp}` prefix.
            ['send' => 'descricao', 'value' => 'CT-{stamp} desc dvi', 'recv' => 'descricao'],

            // severity — enum ok/atencao/critico. Crítico pro semáforo visual
            // + soma "TOTAL RECOMENDADO · CLIENTE" (só atencao+critico entram).
            // Bug silencioso: mismatch enum DB vs const PHP cascateia visual
            // errado (item amarelo renderiza verde).
            ['send' => 'severity', 'value' => 'atencao', 'recv' => 'severity'],

            // recomendacao — nullable string max 255. Texto que o mecânico
            // escreve pro cliente ("Trocar nas próximas 5.000km"). Crítico
            // pra UX WhatsApp aprovação — cliente lê isso. Sem teste, validator
            // pode dropar e cliente aprova sem ver recomendação.
            ['send' => 'recomendacao', 'value' => 'CT-{stamp} trocar em 5000km', 'recv' => 'recomendacao'],

            // valor_recomendado — numeric min 0, cast decimal:2 no model.
            // Bug silencioso típico: backend retorna string "180.00", frontend
            // espera number; ou cast decimal:2 vira int e perde centavos.
            // Match `int` normaliza — sent 180, recv 180.00 → (int)180 == (int)180.
            // NOTE: cast decimal:2 + (float) no shapeItem garante numeric, mas
            // PHP json_encode pode preservar ".00". `int` evita brittleness.
            ['send' => 'valor_recomendado', 'value' => 180, 'recv' => 'valor_recomendado', 'match' => 'int'],

            // sort_order — nullable integer min 0. Controla ordem visual da
            // lista DVI no drawer. Bug silencioso se Controller cast pra string
            // (cast 'integer' no model resolve, mas regressão possível).
            ['send' => 'sort_order', 'value' => 3, 'recv' => 'sort_order', 'match' => 'int'],

            // photo_url — nullable string max 500. URL da foto do item (futuro
            // S3 signed URL via Modules/Arquivos). Bug silencioso se Controller
            // dropar campo ou truncar. Valor sintético plausível (não disparado
            // pro Storage real — é só string field gravado).
            ['send' => 'photo_url', 'value' => 'https://example.test/dvi/CT-{stamp}.jpg', 'recv' => 'photo_url'],
        ],
    ],
];
