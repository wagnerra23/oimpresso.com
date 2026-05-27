<?php

declare(strict_types=1);

/**
 * Fixture: contract test da tela Produto/Edit (UPOS legacy ProductController::update).
 *
 * IMPORTANTE — diferenças do fixture cliente_drawer.php:
 *  1. Produto/Edit NÃO usa autosave PATCH per-field — usa um único
 *     PUT /products/{id} com payload completo (form submit clássico que
 *     redireciona pra /products após sucesso).
 *  2. Por isso o test file (ProdutoEditAutosaveContractTest) NÃO usa
 *     AutosaveContractRunner::run() default — usa loop inline customizado:
 *       a) PUT com [base_payload + 1 campo alterado] por iteração
 *       b) Verifica roundtrip via Product::find($id) (DB direto, pq o
 *          response é redirect 302 sem JSON; e o show JSON via X-Inertia
 *          só expõe um subset do que update pode escrever — `weight`,
 *          `product_description`, custom_fields não estão lá).
 *  3. Mesmo princípio: contract test "envia X, lê de volta X" — pegando
 *     bugs silenciosos do tipo $request->only() dropping unknown keys
 *     (regressão exatamente análoga à do drawer Cliente 2026-05-27 com
 *     aliases PT-BR; e a regressão UPOS 6.7 que sumiu `cpf_cnpj`).
 *
 * Cobre CAMPOS CADASTRAIS do `$request->only([...])` em update() §948:
 *  name, sku, alert_quantity, weight, product_description, barcode_type,
 *  tax_type, product_custom_field1, product_custom_field2, product_custom_field3,
 *  preparation_time_in_minutes
 *
 * NÃO cobre (separation of concerns):
 *  - Variations (single_dpp, single_dsp, sub_sku) — gravadas em `variations`
 *    table via Variation::find($single_variation_id), exigem produto setup
 *    com row variations criada. Próxima iteração (ver "próximas variantes").
 *  - product_locations sync (M2M business_locations) — exige location seedada
 *  - Image upload (multipart) — fora do escopo contract autosave
 *  - Expiry (expiry_period_type/expiry_period) — gated por session
 *    `business.enable_product_expiry` que não conseguimos forçar via runner.
 *  - Stock adjustment — endpoint /stock-adjustments distinto, ServiceOrder pattern.
 *
 * Aliases PT-BR vs canon EN:
 *  - Nenhum detectado no $request->only() do update — todos campos já canon
 *    EN herdados do UPOS upstream. Mesmo assim cobrir é valioso: se um dia
 *    alguém adicionar alias `descricao => product_description` no controller
 *    sem mapear ambos, este test pega.
 *
 * Multi-tenant Tier 0 (ADR 0093 IRREVOGÁVEL): test setupContext força
 * business_id da sessão; Product tem global scope via business_id no WHERE
 * direto do update() (`Product::where('business_id', $business_id)`).
 *
 * @see app/Http/Controllers/ProductController::update §940
 * @see app/Product.php
 * @see tests/Contract/README.md — receita
 * @see ADR 0205 — contract tests autosave canon
 */

return [
    'cadastrais' => [
        // Endpoint PUT — Route::resource('products', ...) gera PUT/PATCH /products/{id}
        // automático. Controller faz update() e redireciona pra /products (302).
        'endpoint' => '/products/{id}',
        'method' => 'put',
        'fields' => [
            // Nome do produto — string básica, valida persistência do mais
            // crítico (campo NOT NULL no schema, primeiro do $request->only()).
            ['send' => 'name', 'value' => 'PROD-{stamp}', 'recv' => 'name'],

            // SKU — se vazio o controller gera via generateProductSku no store,
            // no update vem direto do request. Bug clássico: se mudarem o pipeline
            // pra "auto-trim se contém espaço" o test pega.
            ['send' => 'sku', 'value' => 'SKU-{stamp}', 'recv' => 'sku'],

            // Decimal — alert_quantity passa por num_uf (parsing pt-BR locale).
            // Match int pois Eloquent retorna "10.0000" string pelo cast decimal(22,4)
            // e num_uf transforma input em float — comparação int garante "10" === "10".
            // Valor sem casa decimal pra evitar formatting quirks "10,0000" vs "10".
            ['send' => 'alert_quantity', 'value' => '10', 'recv' => 'alert_quantity', 'match' => 'int'],

            // Peso — string livre na schema (decimal 22,4 mas controller atribui
            // direto sem num_uf). Match partial pq DB pode normalizar "1.500"
            // vs "1.5000". Substring "1.5" cobre.
            ['send' => 'weight', 'value' => '1.5', 'recv' => 'weight', 'match' => 'partial'],

            // Description — text livre, sem transformação no controller.
            ['send' => 'product_description', 'value' => 'CT desc {stamp}', 'recv' => 'product_description'],

            // Barcode type enum — fixo ['C39','C128','EAN-13','EAN-8','UPC-A','UPC-E','ITF-14'].
            // C128 é o default UPOS. Mudamos pra EAN-13 pra forçar persistência diferente.
            ['send' => 'barcode_type', 'value' => 'EAN-13', 'recv' => 'barcode_type'],

            // Tax type enum — ['inclusive', 'exclusive']. Default exclusive.
            ['send' => 'tax_type', 'value' => 'inclusive', 'recv' => 'tax_type'],

            // Custom fields livres (product_custom_field1..20 todos no $request->only).
            // Cobrimos 3 — suficiente pra detectar se alguém retirar o `?? ''` fallback
            // do controller (que silenciosamente zeraria campos não-enviados).
            ['send' => 'product_custom_field1', 'value' => 'CT cf1 {stamp}', 'recv' => 'product_custom_field1'],
            ['send' => 'product_custom_field2', 'value' => 'CT cf2 {stamp}', 'recv' => 'product_custom_field2'],
            ['send' => 'product_custom_field3', 'value' => 'CT cf3 {stamp}', 'recv' => 'product_custom_field3'],

            // Preparation time — integer (minutos), módulo Restaurant feature.
            // Match int pq DB retorna int e frontend envia string às vezes.
            ['send' => 'preparation_time_in_minutes', 'value' => 45, 'recv' => 'preparation_time_in_minutes', 'match' => 'int'],
        ],
    ],
];
