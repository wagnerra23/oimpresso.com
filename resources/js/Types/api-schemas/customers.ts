// resources/js/types/api-schemas/customers.ts
//
// Onda 3 (ADR 0210 — type safety end-to-end). Schema Zod do endpoint JSON
// legado `GET /contacts/customers?q=TERM` (ContactController@getCustomers,
// app/Http/Controllers/ContactController.php:2125-2185).
//
// POR QUE Zod e não Wayfinder: endpoint NÃO passa por Inertia::render — é fetch
// direto de Sells/_components/CustomerSearchAutocomplete.tsx. Wayfinder cobre
// rotas + Inertia props; JSON-only fica por Zod (ADR 0210 Fase 3).
//
// CONTEXTO R8 (o bug que originou o ADR 0210): o controller devolve ~25 campos
// no select (balance, selling_price_group_id, pay_term_number/type,
// shipping_address, discount_percent, etc), mas a interface manual antiga lia só
// 5. Cliente VIP com grupo de preço ATACADO cobrava preço balcão por 15+ dias
// (Larissa 2026-05-28). Este schema declara o contrato real → parse fail-loud
// se o backend driftar.
//
// SHAPE REAL (getCustomers select, ContactController.php:2150-2176):
//   id, text (CONCAT name + contact_id), mobile, address_line_1, address_line_2,
//   city, state, country, zip_code, shipping_address, pay_term_number,
//   pay_term_type, balance, supplier_business_name,
//   discount_percent (cg.amount), price_calculation_type,
//   selling_price_group_id (cg.selling_price_group_id),
//   shipping_custom_field_details, is_export, export_custom_field_1..6,
//   total_rp (só quando business.enable_rp == 1).
//
// COERÇÃO NUMÉRICA: json_encode(Eloquent) serializa colunas DECIMAL como STRING
// (balance "150.00", pay_term_number "30"). IDs podem vir number ou string. Por
// isso z.coerce.number() nos campos numéricos — aceita string-do-JSON E number;
// tipo inferido sai `number`, compatível com o uso defensivo no componente.

import { z } from 'zod';

/** Prazo de pagamento — 'days' | 'months' (contacts.pay_term_type). */
export const payTermTypeSchema = z.enum(['days', 'months']);

/**
 * Uma linha de resultado de `/contacts/customers`.
 *
 * `.passthrough()` preserva campos extras (ex futuros custom fields) sem
 * quebrar o parse — fail-loud só nos campos contratados.
 *
 * Campos numéricos que podem vir null/string do MySQL usam
 * `z.coerce.number().nullable()` — null preservado, string/number coagido.
 */
export const customerSearchResultSchema = z
  .object({
    // --- Identificação / contato (core que o componente lê) ---
    id: z.coerce.number(),
    text: z.string(),
    mobile: z.string().nullable().optional(),
    // --- Endereço ---
    address_line_1: z.string().nullable().optional(),
    address_line_2: z.string().nullable().optional(),
    city: z.string().nullable().optional(),
    state: z.string().nullable().optional(),
    country: z.string().nullable().optional(),
    zip_code: z.string().nullable().optional(),
    shipping_address: z.string().nullable().optional(),
    // --- Comercial (R8 raiz — auto-aplicar prazo + grupo de preço ao trocar cliente) ---
    pay_term_number: z.coerce.number().nullable().optional(),
    // pay_term_type pode vir 'days'/'months' OU null OU string desconhecida —
    // union tolerante mantém fail-loud só pro tipo errado (ex number).
    pay_term_type: z.union([payTermTypeSchema, z.string()]).nullable().optional(),
    balance: z.coerce.number().nullable().optional(),
    selling_price_group_id: z.coerce.number().nullable().optional(),
    discount_percent: z.coerce.number().nullable().optional(),
    // --- Demais campos do select (preservados pra paridade de contrato) ---
    supplier_business_name: z.string().nullable().optional(),
    price_calculation_type: z.string().nullable().optional(),
    shipping_custom_field_details: z.string().nullable().optional(),
    is_export: z.coerce.number().nullable().optional(),
    export_custom_field_1: z.string().nullable().optional(),
    export_custom_field_2: z.string().nullable().optional(),
    export_custom_field_3: z.string().nullable().optional(),
    export_custom_field_4: z.string().nullable().optional(),
    export_custom_field_5: z.string().nullable().optional(),
    export_custom_field_6: z.string().nullable().optional(),
    // --- Condicional: business.enable_rp == 1 ---
    total_rp: z.coerce.number().nullable().optional(),
  })
  .passthrough();

/** Payload completo de `/contacts/customers` — array de linhas. */
export const customersListSchema = z.array(customerSearchResultSchema);

/** Tipo inferido de uma linha — single source of truth do shape do endpoint. */
export type CustomerSearchResultDTO = z.infer<typeof customerSearchResultSchema>;
/** Tipo inferido do array completo. */
export type CustomersListDTO = z.infer<typeof customersListSchema>;

/**
 * Faz parse fail-loud do JSON de `/contacts/customers`.
 * Lança ZodError com path do campo divergente se o backend driftar (R8).
 *
 * @param json Resultado de `await res.json()` (unknown — não confie no cast).
 * @returns Array de clientes validado + coagido.
 * @throws {z.ZodError} quando o payload não bate com o schema.
 */
export function parseCustomers(json: unknown): CustomersListDTO {
  return customersListSchema.parse(json);
}

/**
 * Variante não-lançante (safeParse) pra call-sites que degradam graciosamente
 * em vez de propagar exceção. Retorna `{ success, data | error }`.
 */
export function safeParseCustomers(json: unknown) {
  return customersListSchema.safeParse(json);
}
