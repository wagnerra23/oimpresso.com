---
title: "RUNBOOK — /purchases/create (Compras · criação Inertia + modo grade)"
module: Purchase
tela: Purchase/Create
owner: F
status: ativo
last_validated: "2026-07-02"
related_adrs:
  - 0104-processo-mwart-canonico-unico-caminho
  - 0093-multi-tenant-isolation-tier-0
  - 0114-prototipo-ui-cowork-loop-formalizado
  - 0149-pattern-reuse-mwart-create-edit
  - 0105-cliente-como-sinal-guiar-sem-mandar
spec_ref: memory/requisitos/Compras/SPEC.md
blueprint_cowork: prototipo-ui/prototipos/compras/visual-source.html
---

# RUNBOOK — `/purchases/create` (criar Compra, Inertia/React)

> **Consolidado (P5 · 2026-07-02):** funde o RUNBOOK operacional novo (dual-path + modo grade, `last_validated 2026-06-22`, antes em `Purchase/RUNBOOK-create.md`) com o contrato de referência antigo (Props/Layout/Validação/fases MWART, gerado 2026-05-15). Esta é a **casa canônica** da tela `resources/js/Pages/Purchase/Create.tsx` servida em `/purchases/create` — controller mantém **dual-path** Blade legacy + Inertia.
>
> Convergência C1 ([`compras-purchase-convergencia-c1`](../../../decisions/proposals/compras-purchase-convergencia-c1.md)): o cockpit greenfield `/compras` delega "+ Nova compra" pra cá; NÃO existe `Pages/Compras/Create.tsx`.

## 1. Contexto

- **Rota:** `GET /purchases/create`
- **Controller:** `App\Http\Controllers\PurchaseController@create` (`createInertia`/`store`/`gradeMatrix`)
- **Blade legacy:** `resources/views/purchase/create.blade.php` (~1100 linhas)
- **Inertia destino:** `resources/js/Pages/Purchase/Create.tsx`
- **Linha de compra:** `app/Utils/ProductUtil.php@createOrUpdatePurchaseLines`

## 2. Persona

Maiara/Felipe — escritório, registram entrada de mercadoria (datas, fornecedor, produtos, totais). Canary biz=4 (Larissa) valida o modo grade.

## 3. Dual-path (a pegadinha central)

`PurchaseController@create` decide o que renderizar:

| Condição do request | Render |
|---|---|
| header `X-Inertia: true` OU `?v=2` | `createInertia()` → `Inertia::render('Purchase/Create')` (React) |
| GET normal sem nada | `view('purchase.create')` (Blade legacy) |

A navegação pelo SPA (`router.visit('/purchases/create')`) cai no path React — é o que o cliente enxerga. O `store()` (`POST /purchases`) é **compartilhado** pelos dois paths.

## 4. Dois modos de entrada de item (mesmo `linhas` state, mesmo POST)

A tela acumula tudo num único array de rascunho (`linhas: PurchaseLineDraft[]`) que o `enviar()` manda como `purchases` no POST. `ProductUtil::createOrUpdatePurchaseLines` consome esse array.

| Modo | Como | Vincula produto real? |
|---|---|---|
| **Manual (MVP1)** | busca texto → "Adicionar item" → linha editável | ❌ `product_id`/`variation_id` = null (texto livre) |
| **Grade tam×cor** (US-COM-005) | combobox produto `variable` → matriz `<GradeMatrixInput>` → "Adicionar à compra" | ✅ 1 célula = 1 `variation_id` real |

O modo grade expande cada célula com qty>0 numa `PurchaseLineDraft` `{ product_id, variation_id, quantity, pp_without_discount, purchase_price, purchase_price_inc_tax, discount_percent:0, item_tax:0, purchase_line_tax_id:null }` e dá `push` no `linhas`.

> **Contrato de linha (não inventar):** `createOrUpdatePurchaseLines` lê `purchase_line_tax_id` (não `tax_id`) e roda `num_uf()` em qty/preços. O `form.transform` normaliza as chaves pra TODAS as linhas (manual + grade).

## 5. Endpoint da grade — `GET /purchases/grade-matrix` (`gradeMatrix`)

Dado `product_id` (+ `location_id` opcional), devolve o layout pronto pro `<GradeMatrixInput>`:

```json
{ "product_id":1, "product_name":"Camiseta", "type":"variable", "mode":"2d|matrix-1d|single",
  "rows":[{"id","label"}], "cols":[{"id","label"}], "cellVariationMap":{"<rowId>__<colId>": <variation_id> }, "unit_cost":0 }
```

Detecção (helper `detectGradeLayout`):
- `type != variable` ou ≤1 variação → `single` (1 input qty).
- nomes de variação **compostos e parseáveis** (split por `/ | · x × -`, 2 partes, combinações únicas) → `2d` (linhas=parte1, cols=parte2).
- senão → `matrix-1d` (linhas=variações reais, 1 coluna "Qtd"). **Sempre funciona.**

> UltimatePOS guarda variação em **1 eixo** (`variation.name` = 1 valor). O 2D só "acende" se o catálogo usar nomes compostos. O `mode` detectado é logado (`Log::info purchase.grade_matrix.detect`) — nunca fallback silencioso (AP-18). Canary biz=4 (Larissa) confirma o formato real; aí decide-se convenção/schema V2.

## 6. Props (Controller → Page)

| Prop | Tipo | Origem |
|---|---|---|
| `business_locations` | `Record<id, name>` | `BusinessLocation::forDropdown($business_id, false, true)` |
| `bl_attributes` | `Record<id, Record<string, unknown>>` | `BusinessLocation::forDropdown(..., true)` (attributes) |
| `taxes` | `{id, name, amount}[]` | `TaxRate::where('business_id', $business_id)->ExcludeForTaxGroup()->get()` |
| `order_statuses` | `Record<key, label>` | `ProductUtil::orderStatuses()` |
| `default_purchase_status` | `'received' \| null` | `null` se setting `enable_purchase_status=1` |
| `payment_types` | `Record<key, label>` | `ProductUtil::payment_types(null, true, $business_id)` |
| `currency` | `{symbol, code, thousand_separator, decimal_separator, decimal}` | `TransactionUtil::purchaseCurrencyDetails($business_id)` |
| `customer_groups` | `Record<id, name>` | `CustomerGroup::forDropdown($business_id)` |
| `accounts` | `Record<id, name>` | `ModuleUtil::accountsDropdown($business_id, true)` |
| `default_datetime` | `string` (ISO local) | `now()->format('Y-m-d H:i:s')` |
| `permissions` | `{ create_supplier, create_customer, edit_price, view_purchase_price }` | `auth()->user()->can(...)` |
| `common_settings` | `Record<string, mixed>` | `session('business.common_settings')` |

## 7. Layout F3 (Inertia/React)

```
[PageHeader: "Nova compra" + ações: Cancelar / Salvar]
[Card: Dados gerais — 4 cols] Filial · Fornecedor (+ Novo) · Ref Nº · Data · Status
[Card: Produtos — tabela editável] Busca produto · linhas (produto/qtd/unit/custo/desc%/imposto/subtotal) · footer subtotal+impostos · bloco "Adicionar por grade"
[Card: Desconto + impostos globais — 4 cols] Desconto (fixed/percentage) · Tax global · Shipping · Total final (read-only)
[Card colapsável: Pagamentos (opcional MVP1)] repeater data/método/valor/conta/ref
[Card colapsável: Notas + custom_field_1..4]
```

`<Inertia::defer>` em props caras (`taxes`, `accounts`, `customer_groups`). TypeScript estrito (zero `any`). PT-BR na UI.

## 8. Validação + POST `/purchases` (`store()`)

`store()` **NÃO modificado** — continua aceitando form-data tradicional; `useForm().post('/purchases')` envia FormData compatível. Regras required: `status`, `contact_id`, `transaction_date`, `total_before_tax`, `location_id`, `final_total`, `document` (max `constants.document_size_limit`). Inertia retorna erros via `useForm().errors` — abre `<details>` se erro em campo colapsado.

## 9. Quando esta tela quebra (sintomas)

- 403 → user sem `purchase.create`.
- Grade vazia / "sem variações" → produto não é `variable` OU variações sem `variation_location_details`.
- Grade abre em `matrix-1d` quando se esperava 2D → nomes de variação não são compostos (ver §5 / log).
- "All Inertia requests must receive a valid Inertia response" → alguma ação chamou `router.visit` pra rota Blade.
- POST falha silencioso → conferir `final_total`/`total_before_tax` (validados no `store`) e `contact_id`/`location_id`.

## 10. Tier 0 — invariantes (ADR 0093 IRREVOGÁVEL)

- ✅ `gradeMatrix` usa `session('user.business_id')` e `Product::where('business_id',$biz)->...->firstOrFail()` (cross-tenant = 404).
- ✅ `store()` valida que todo `purchases[].variation_id` pertence a produto do `business_id` da sessão (anti payload forjado).
- ✅ `permitted_locations` filtra o dropdown de filiais.
- ❌ NÃO `auth()->user()->business_id` em controller (canon UPOS é `session('user.business_id')` — T-AP-8).
- ❌ NÃO `business_id` hardcoded na Page.

## 11. Smoke (R1 — evidência, não narração)

```bash
curl -sv "https://oimpresso.com/purchases/create?v=2" 2>&1 | grep '^< HTTP'   # 200/302
# grade endpoint (autenticado): GET /purchases/grade-matrix?product_id=<id variável>
```

Chrome MCP pós-deploy (1280px = monitor Larissa): abrir `/purchases/create?v=2`, escolher produto variável, preencher a grade com Tab/Enter, "Adicionar à compra", conferir linhas acumuladas + total, salvar.

## 12. Fases MWART (histórico F2-F5 — ADR 0104)

- **F2 baseline:** `tests/Feature/Purchase/Wave2CreateBaselineTest.php` — GET `/purchases/create` (Blade sem `?v=2`) 200 + multi-tenant biz=1 só locations biz=1.
- **F3 frontend:** `Create.tsx` com AppShellV2 + PageHeader + Card primitives + `useForm()`.
- **F4 QA:** `tests/Feature/Purchase/Wave2CreateInertiaTest.php` — estrutural + cross-tenant biz=1≠biz=99 + 403 sem `purchase.create`.
- **F5 cutover:** default Blade; opt-in `?v=2`; promoção a default depende de Wagner aprovar SCREENSHOT (gate visual [ADR 0114](../../../decisions/0114-prototipo-ui-cowork-loop-formalizado.md)).

## 13. Refs

- Controller: `app/Http/Controllers/PurchaseController.php` (`create`/`createInertia`/`store`/`gradeMatrix`)
- Linha: `app/Utils/ProductUtil.php@createOrUpdatePurchaseLines`
- Tela: `resources/js/Pages/Purchase/Create.tsx` · componentes `resources/js/Pages/Purchase/_components/{GradeMatrixInput,GradeProductCombobox}.tsx`
- Charter: `resources/js/Pages/Purchase/Create.charter.md` · Visual: [`purchase-create-visual-comparison.md`](purchase-create-visual-comparison.md) (ao lado)
- SPEC: [`memory/requisitos/Compras/SPEC.md`](../SPEC.md) US-COM-005
- [ADR 0104 MWART](../../../decisions/0104-processo-mwart-canonico-unico-caminho.md) · [ADR 0093 Tier 0](../../../decisions/0093-multi-tenant-isolation-tier-0.md) · [ADR 0114 gate visual](../../../decisions/0114-prototipo-ui-cowork-loop-formalizado.md)
