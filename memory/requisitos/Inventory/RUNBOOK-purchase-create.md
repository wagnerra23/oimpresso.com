---
tela: purchase/create
modulo: Purchase (raiz UltimatePOS — não Modules/)
tipo: FORM (CREATE)
generated_at: 2026-05-15
generated_by: Agent W2-D (Wave2 B5 Stock/Purchase MWART massivo)
status: F3 implementado (aguarda smoke Wagner)
adr_refs: [0104, 0093, 0114, 0149]
spec_ref: memory/requisitos/Inventory/SPEC.md
blueprint_cowork: prototipo-ui/prototipos/compras/visual-source.html
---

# RUNBOOK — `purchase/create` (FORM CREATE)

> Cria nova compra (`transactions.type = 'purchase'`) com itens, descontos, impostos e pagamentos.
> Dual path Blade legacy + Inertia atrás de `?v=2` (mesmo padrão Index/Show já mergeado).

## 1. Contexto

- **Rota:** `GET /purchases/create`
- **Controller:** `App\Http\Controllers\PurchaseController@create`
- **Blade legacy:** `resources/views/purchase/create.blade.php` (~1100 linhas)
- **Inertia destino:** `resources/js/Pages/Purchase/Create.tsx`

## 2. Persona

Maiara/Felipe — escritório, registram entrada de mercadoria. Datas, fornecedor, produtos, totais.

## 3. Multi-tenant (Tier 0 IRREVOGÁVEL)

- `business_id` vem da sessão (`session.user.business_id`) — passado pelo Controller como prop.
- Todas as queries do Controller usam `where('business_id', $business_id)`.
- Permissão `purchase.create` obrigatória (abort 403 caso contrário).
- `permitted_locations` filtra dropdown de filiais.

## 4. Props (Controller → Page)

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

## 5. Layout F3 (Inertia/React)

```
[PageHeader: "Nova compra" + ações: Cancelar / Salvar]
[Card: Dados gerais — 4 cols]
  - Filial (select)
  - Fornecedor (autocomplete) + botão "+ Novo"
  - Ref Nº (opcional, auto-gen se vazio)
  - Data (datetime)
  - Status (select: ordered/pending/received)
[Card: Produtos — tabela editável]
  - Busca produto (autocomplete)
  - Linhas: produto, qtd, unit, custo, desc%, imposto, subtotal
  - Footer: subtotal + impostos
[Card: Desconto + impostos globais — 4 cols]
  - Desconto tipo (fixed/percentage) + valor
  - Tax global (select TaxRate)
  - Shipping charges
  - Total final (read-only)
[Card colapsável: Pagamentos (opcional MVP1) — repeater]
  - Data, método, valor, conta, ref pagamento
[Card colapsável: Notas + custom_field_1..4]
```

## 6. Validação

Mesma regra atual do `store()`:
- `status` required
- `contact_id` required
- `transaction_date` required
- `total_before_tax` required
- `location_id` required
- `final_total` required
- `document` file max constants.document_size_limit

Inertia retorna erros via `useForm().errors` — abre `<details>` se erro em campo colapsado.

## 7. POST `/purchases` (`store()`)

NÃO modificado. Continua aceitando form-data tradicional. `useForm().post('/purchases')` envia FormData compatível.

## 8. F2 BACKEND BASELINE

Pest test `tests/Feature/Purchase/Wave2CreateBaselineTest.php`:
- GET `/purchases/create` (Blade legacy sem `?v=2`) retorna 200 com `view('purchase.create')`.
- Multi-tenant: usuário biz=1 logado vê apenas locations biz=1.

## 9. F3 FRONTEND

- `Create.tsx` com AppShellV2, PageHeader, Card primitives.
- Uso de `useForm()` do Inertia.
- `<Inertia::defer>` em props caras (`taxes`, `accounts`, `customer_groups`).
- TypeScript estrito (zero `any`).
- PT-BR em UI.

## 10. F4 QA

Pest `tests/Feature/Purchase/Wave2CreateInertiaTest.php`:
- Estrutural (existência tela + AppShellV2 + PageHeader + interfaces).
- Multi-tenant: response cross-tenant biz=1 ≠ biz=99 (props.business_locations diferentes).
- Permissão: user sem `purchase.create` → 403.

## 11. F5 CUTOVER

- Default: Blade legacy.
- Opt-in: `?v=2` no querystring (Wagner valida smoke biz=1).
- Promoção a default depende de Wagner aprovar SCREENSHOT (gate visual ADR 0114).
