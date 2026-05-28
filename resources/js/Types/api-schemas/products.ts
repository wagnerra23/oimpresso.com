// resources/js/types/api-schemas/products.ts
//
// Onda 3 (ADR 0210 — type safety end-to-end). Schema Zod do endpoint JSON
// legado `GET /products/list` (ProductController@getProducts →
// ProductUtil@filterProduct, app/Utils/ProductUtil.php:1599-1740).
//
// POR QUE Zod e não Wayfinder aqui: este endpoint NÃO passa por Inertia::render
// (é fetch direto do client em Sells/_components/ProductSearchAutocomplete.tsx).
// Wayfinder cobre rotas + Inertia props; endpoints JSON-only ficam por conta do
// Zod (ADR 0210 Fase 3). `parseProducts()` faz fail-loud: drift de payload
// (R8 — controller muda select, client não acompanha) explode no fetch, não 6
// telas depois.
//
// SHAPE REAL (ProductUtil@filterProduct select, linhas 1715-1734):
//   Base (sempre): product_id, name, type, enable_stock, variation_id,
//                  variation, qty_available, selling_price, sub_sku, unit
//   Condicional price_group_id: variation_group_price
//   Condicional search_fields inclui 'lot': purchase_line_id, lot_number
//   => 13 campos no máximo.
//
// IMPORTANTE — coerção numérica: o endpoint usa json_encode(Eloquent collection).
// Colunas DECIMAL/agregadas do MySQL serializam como STRING no JSON
// (ex selling_price "12.50", qty_available "3.0000"). IDs costumam vir number,
// mas tratamos defensivo. Por isso usamos z.coerce.number() nos campos numéricos:
// aceita string-do-JSON E number, e o tipo inferido sai como `number` —
// estruturalmente compatível com a interface manual ProductSearchResult que o
// componente já declara (e que coage com Number(...) nos pontos de uso).

import { z } from 'zod';

/** Tipo de produto UltimatePOS (products.type). */
export const productTypeSchema = z.enum(['single', 'variable', 'modifier', 'combo']);

/**
 * Uma linha de resultado de `/products/list`. Para produto type='variable',
 * o endpoint devolve N linhas (uma por variação).
 *
 * `.passthrough()` preserva quaisquer campos extras que o backend venha a
 * adicionar (ex product_custom_fieldN quando search_fields os inclui) sem
 * quebrar o parse — mantém fail-loud só nos campos contratados.
 */
export const productSearchResultSchema = z
  .object({
    // --- Base (sempre presentes) ---
    product_id: z.coerce.number(),
    variation_id: z.coerce.number(),
    name: z.string(),
    type: productTypeSchema.optional(),
    // enable_stock vem 0/1 (int ou string "0"/"1"). Normaliza pra 0|1.
    enable_stock: z.coerce.number().pipe(z.union([z.literal(0), z.literal(1)])).optional(),
    variation: z.string().nullable().optional(),
    sub_sku: z.string().nullable().optional(),
    // sku NEM sempre está no select de filterProduct (vem via sub_sku), mas o
    // componente lê r.sku — mantemos opcional pra não falhar quando ausente.
    sku: z.string().nullable().optional(),
    selling_price: z.coerce.number().nullable().optional(),
    qty_available: z.coerce.number().nullable().optional(),
    unit: z.string().nullable().optional(),
    // --- Condicional: price_group_id presente ---
    variation_group_price: z.coerce.number().nullable().optional(),
    // --- Condicional: search_fields inclui 'lot' ---
    purchase_line_id: z.coerce.number().nullable().optional(),
    lot_number: z.string().nullable().optional(),
  })
  .passthrough();

/** Payload completo de `/products/list` — array de linhas. */
export const productsListSchema = z.array(productSearchResultSchema);

/** Tipo inferido de uma linha — single source of truth do shape do endpoint. */
export type ProductSearchResultDTO = z.infer<typeof productSearchResultSchema>;
/** Tipo inferido do array completo. */
export type ProductsListDTO = z.infer<typeof productsListSchema>;

/**
 * Faz parse fail-loud do JSON de `/products/list`.
 * Lança ZodError com path do campo divergente se o backend driftar do contrato.
 *
 * @param json Resultado de `await res.json()` (unknown — não confie no cast).
 * @returns Array de produtos validado + coagido.
 * @throws {z.ZodError} quando o payload não bate com o schema.
 */
export function parseProducts(json: unknown): ProductsListDTO {
  return productsListSchema.parse(json);
}

/**
 * Variante não-lançante (safeParse) pra call-sites que preferem degradar
 * graciosamente (ex: dropdown vazio) em vez de propagar exceção. Retorna
 * `{ success, data | error }`.
 */
export function safeParseProducts(json: unknown) {
  return productsListSchema.safeParse(json);
}
