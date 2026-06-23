---
title: "RUNBOOK — /purchases/create (Compras · criação Inertia + modo grade)"
module: Purchase
tela: Purchase/Create
owner: F
status: ativo
last_validated: "2026-06-22"
related_adrs:
  - 0104-processo-mwart-canonico-unico-caminho
  - 0093-multi-tenant-isolation-tier-0
  - 0114-prototipo-ui-cowork-loop-formalizado
  - 0149-pattern-reuse-mwart-create-edit
  - 0105-cliente-como-sinal-guiar-sem-mandar
---

# RUNBOOK — `/purchases/create` (criar Compra, Inertia/React)

> Tela `resources/js/Pages/Purchase/Create.tsx` servida em `/purchases/create`.
> Migração MWART Wave2 B5 — controller mantém **dual-path** Blade legacy + Inertia.
> Convergência C1 ([`compras-purchase-convergencia-c1`](../../decisions/proposals/compras-purchase-convergencia-c1.md)):
> o cockpit greenfield `/compras` delega "+ Nova compra" pra cá; NÃO existe `Pages/Compras/Create.tsx`.

## 1. Dual-path (a pegadinha central)

`PurchaseController@create` decide o que renderizar:

| Condição do request | Render |
|---|---|
| header `X-Inertia: true` OU `?v=2` | `createInertia()` → `Inertia::render('Purchase/Create')` (React) |
| GET normal sem nada | `view('purchase.create')` (Blade legacy) |

A navegação pelo SPA (`router.visit('/purchases/create')`) cai no path React — é o que o cliente enxerga.
O `store()` (`POST /purchases`) é **compartilhado** pelos dois paths.

## 2. Dois modos de entrada de item (mesmo `linhas` state, mesmo POST)

A tela acumula tudo num único array de rascunho (`linhas: PurchaseLineDraft[]`) que o `enviar()`
manda como `purchases` no POST. `ProductUtil::createOrUpdatePurchaseLines` consome esse array.

| Modo | Como | Vincula produto real? |
|---|---|---|
| **Manual (MVP1)** | busca texto → "Adicionar item" → linha editável | ❌ `product_id`/`variation_id` = null (texto livre) |
| **Grade tam×cor** (US-COM-005) | combobox produto `variable` → matriz `<GradeMatrixInput>` → "Adicionar à compra" | ✅ 1 célula = 1 `variation_id` real |

O modo grade expande cada célula com qty>0 numa `PurchaseLineDraft` `{ product_id, variation_id, quantity, pp_without_discount, purchase_price, purchase_price_inc_tax, discount_percent:0, item_tax:0, purchase_line_tax_id:null }` e dá `push` no `linhas`.

> **Contrato de linha (não inventar):** `createOrUpdatePurchaseLines` lê `purchase_line_tax_id` (não `tax_id`)
> e roda `num_uf()` em qty/preços. O `form.transform` normaliza as chaves pra TODAS as linhas (manual + grade).

## 3. Endpoint da grade — `GET /purchases/grade-matrix` (`gradeMatrix`)

Dado `product_id` (+ `location_id` opcional), devolve o layout pronto pro `<GradeMatrixInput>`:

```json
{ "product_id":1, "product_name":"Camiseta", "type":"variable", "mode":"2d|matrix-1d|single",
  "rows":[{"id","label"}], "cols":[{"id","label"}], "cellVariationMap":{"<rowId>__<colId>": <variation_id> }, "unit_cost":0 }
```

Detecção (helper `detectGradeLayout`):
- `type != variable` ou ≤1 variação → `single` (1 input qty).
- nomes de variação **compostos e parseáveis** (split por `/ | · x × -`, 2 partes, combinações únicas) → `2d` (linhas=parte1, cols=parte2).
- senão → `matrix-1d` (linhas=variações reais, 1 coluna "Qtd"). **Sempre funciona.**

> UltimatePOS guarda variação em **1 eixo** (`variation.name` = 1 valor). O 2D só "acende" se o catálogo
> usar nomes compostos. O `mode` detectado é logado (`Log::info purchase.grade_matrix.detect`) — nunca fallback
> silencioso (AP-18). Canary biz=4 (Larissa) confirma o formato real; aí decide-se convenção/schema V2.

## 4. Quando esta tela quebra (sintomas)

- 403 → user sem `purchase.create`.
- Grade vazia / "sem variações" → produto não é `variable` OU variações sem `variation_location_details`.
- Grade abre em `matrix-1d` quando se esperava 2D → nomes de variação não são compostos (ver §3 / log).
- "All Inertia requests must receive a valid Inertia response" → alguma ação chamou `router.visit` pra rota Blade.
- POST falha silencioso → conferir `final_total`/`total_before_tax` (validados no `store`) e `contact_id`/`location_id`.

## 5. Tier 0 — invariantes (ADR 0093 IRREVOGÁVEL)
- ✅ `gradeMatrix` usa `session('user.business_id')` e `Product::where('business_id',$biz)->...->firstOrFail()` (cross-tenant = 404).
- ✅ `store()` valida que todo `purchases[].variation_id` pertence a produto do `business_id` da sessão (anti payload forjado).
- ❌ NÃO `auth()->user()->business_id` em controller (canon UPOS é `session('user.business_id')` — T-AP-8).
- ❌ NÃO `business_id` hardcoded na Page.

## 6. Smoke (R1 — evidência, não narração)

```bash
curl -sv "https://oimpresso.com/purchases/create?v=2" 2>&1 | grep '^< HTTP'   # 200/302
# grade endpoint (autenticado): GET /purchases/grade-matrix?product_id=<id variável>
```

Chrome MCP pós-deploy (1280px = monitor Larissa): abrir `/purchases/create?v=2`, escolher produto variável,
preencher a grade com Tab/Enter, "Adicionar à compra", conferir linhas acumuladas + total, salvar.

## 7. Refs
- Controller: `app/Http/Controllers/PurchaseController.php` (`create`/`createInertia`/`store`/`gradeMatrix`)
- Linha: `app/Utils/ProductUtil.php@createOrUpdatePurchaseLines`
- Tela: `resources/js/Pages/Purchase/Create.tsx` · componentes `resources/js/Pages/Purchase/_components/{GradeMatrixInput,GradeProductCombobox}.tsx`
- Charter: `resources/js/Pages/Purchase/Create.charter.md` · Visual: `create-visual-comparison.md` (ao lado)
- SPEC: [`memory/requisitos/Compras/SPEC.md`](../Compras/SPEC.md) US-COM-005
- [ADR 0104 MWART](../../decisions/0104-processo-mwart-canonico-unico-caminho.md) · [ADR 0093 Tier 0](../../decisions/0093-multi-tenant-isolation-tier-0.md) · [ADR 0114 gate visual](../../decisions/0114-prototipo-ui-cowork-loop-formalizado.md)
