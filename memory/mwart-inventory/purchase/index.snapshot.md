---
tela: purchase/index
modulo: purchase (raiz UltimatePOS, não Modules/)
tipo: LIST
captured_at: 2026-05-11
captured_by: [CL]
blade_path: resources/views/purchase/index.blade.php
partial_table: resources/views/purchase/partials/purchase_table.blade.php
controller: app/Http/Controllers/PurchaseController.php
controller_action: index
route_legacy: GET /purchases (name=purchases.index)
route_resource: Route::resource('purchases', PurchaseController::class)->except(['show'])
mockup_cowork: prototipo-ui/prototipos/compras/visual-source.html (37,9 KB)
status: snapshot-complete (STEP 1 da skill migracao-blade-react)
---

# Snapshot paridade — `purchase/index` (LIST)

> STEP 1 do pipeline [migracao-blade-react](../../../.claude/skills/migracao-blade-react/SKILL.md).
> Antes de STEP 2 TRADUÇÃO VISUAL, este snapshot **DEVE** estar completo.

## 1. Identificação

- **Tela:** Lista de Compras (Purchases)
- **Tipo:** LIST (template: [runbook-LIST](../../../.claude/skills/migracao-blade-react/runbook-LIST.template.md))
- **Blade legacy:** [resources/views/purchase/index.blade.php](../../../resources/views/purchase/index.blade.php) (7,8 KB)
- **Partial tabela:** [resources/views/purchase/partials/purchase_table.blade.php](../../../resources/views/purchase/partials/purchase_table.blade.php)
- **Controller:** `app/Http/Controllers/PurchaseController.php` linha 60–223 (`index()`)
- **Rota canônica:** `Route::resource('purchases', PurchaseController::class)->except(['show'])` ([routes/web.php:239](../../../routes/web.php#L239))
- **Mockup Cowork:** [prototipo-ui/prototipos/compras/visual-source.html](../../../prototipo-ui/prototipos/compras/visual-source.html) (37,9 KB)

## 2. Rotas + middleware

| Verbo | URI | Action | Middleware |
|-------|-----|--------|------------|
| GET | `/purchases` | `PurchaseController@index` | `web, auth, language, timezone, AdminSidebarMenu` (UPOS canon) |
| POST | `/purchases/update-status` | `PurchaseController@updateStatus` | idem |
| POST | `/purchases/check_ref_number` | `PurchaseController@checkRefNumber` | idem |
| GET | `/purchases/get_products` | `PurchaseController@getProducts` | idem |
| GET | `/purchases/get_suppliers` | `PurchaseController@getSuppliers` | idem |
| GET | `/purchases/print/{id}` | `PurchaseController@printInvoice` | idem |
| GET | `/purchases/{id}` | `PurchaseController@show` | idem |

## 3. Permissões (Spatie `@can`)

| Permission | Onde aplica |
|------------|-------------|
| `purchase.view` | Ver lista + ver detalhe (modal) + print + barcode + download document |
| `purchase.create` | Botão "+ Novo" (header da listagem) |
| `purchase.update` | Ação Editar + Purchase Return + Update Status |
| `purchase.delete` | Ação Excluir |
| `purchase.update_status` | Alternativa a `purchase.update` pra status |
| `purchase.payments` | Add/View pagamentos |
| `edit_purchase_payment` | Editar pagamento |
| `delete_purchase_payment` | Excluir pagamento |
| `view_own_purchase` | Ver só os próprios (`created_by == user.id`) |

**Lógica de acesso (do `index()` linha 62-64):**
```php
if (! auth()->user()->can('purchase.view')
    && ! auth()->user()->can('purchase.create')
    && ! auth()->user()->can('view_own_purchase')) {
    abort(403);
}
```

**Filtragem condicional por ownership (linha 100-102):**
```php
if (! auth()->user()->can('purchase.view') && auth()->user()->can('view_own_purchase')) {
    $purchases->where('transactions.created_by', request()->session()->get('user.id'));
}
```

## 4. Filtros (do header `components.filters`)

| Filtro | Form input | Tipo | Default | Endpoint backend |
|--------|------------|------|---------|------------------|
| `purchase_list_filter_location_id` | select | `BusinessLocation::forDropdown($business_id)` | "all" placeholder | `request()->location_id` (linha 77) |
| `purchase_list_filter_supplier_id` | select | `Contact::suppliersDropdown($business_id, false)` | "all" | `request()->supplier_id` (linha 74) |
| `purchase_list_filter_status` | select | `$productUtil->orderStatuses()` (ordered/received/pending) | "all" | `request()->status` (linha 89) |
| `purchase_list_filter_payment_status` | select | `paid / due / partial / overdue` | "all" | `request()->input('payment_status')` (linha 80-87) |
| `purchase_list_filter_date_range` | daterangepicker | text input | — | `request()->start_date` + `end_date` (linha 93-98) |

**Overdue special handling (linha 82-87):**
```php
} elseif (request()->input('payment_status') == 'overdue') {
    $purchases->whereIn('transactions.payment_status', ['due', 'partial'])
        ->whereNotNull('transactions.pay_term_number')
        ->whereNotNull('transactions.pay_term_type')
        ->whereRaw("IF(transactions.pay_term_type='days', DATE_ADD(transactions.transaction_date, INTERVAL transactions.pay_term_number DAY) < CURDATE(), DATE_ADD(transactions.transaction_date, INTERVAL transactions.pay_term_number MONTH) < CURDATE())");
}
```

## 5. Colunas da tabela (`purchase_table.blade.php` + DataTables editColumn)

| # | Coluna | Origem | Renderização | Sortable | Searchable |
|---|--------|--------|--------------|:--------:|:----------:|
| 1 | Action | dropdown HTML | botões inline | ☐ | ☐ |
| 2 | Date | `transaction_date` | `@format_datetime($transaction_date)` | ☑ | ☑ |
| 3 | Ref No | `ref_no` + return badge se `return_exists` | string + label vermelho se retorno | ☑ | ☑ |
| 4 | Location | `transactions.location_id → BusinessLocation.name` | string | ☑ | ☑ |
| 5 | Supplier | `supplier_business_name` (se != null) + `name` | "Empresa, Nome" inline | ☑ | ☑ |
| 6 | Purchase Status | `transactions.status` | `@transaction_status($status)` badge clicável | ☑ | ☑ |
| 7 | Payment Status | calculado via `Transaction::getPaymentStatus($row)` | partial view `sell.partials.payment_status` | ☑ | ☐ |
| 8 | Grand Total | `final_total` | `@format_currency` | ☑ | ☐ |
| 9 | Payment Due | `final_total - amount_paid` (+ return due se aplicável) | "Purchase: R$ X / Return: R$ Y" | ☐ | ☐ |
| 10 | Added By | usuário criador | string | ☑ | ☑ |

## 6. Botões de ação inline (dropdown por linha)

| # | Ação | Condição | Permission | Endpoint |
|---|------|----------|------------|----------|
| 1 | View | sempre se can view | `purchase.view` | modal AJAX `PurchaseController@show` |
| 2 | Print | sempre se can view | `purchase.view` | `PurchaseController@printInvoice` |
| 3 | Edit | sempre se can update | `purchase.update` | `PurchaseController@edit` |
| 4 | Delete | sempre se can delete | `purchase.delete` | `PurchaseController@destroy` (AJAX) |
| 5 | Barcode/labels | sempre | (nenhuma — sempre exibe) | `LabelsController@show?purchase_id={id}` |
| 6 | Download document | se `row.document != null` | `purchase.view` | `uploads/documents/{document}` |
| 7 | View document (img) | se document é imagem | `purchase.view` | inline view |
| 8 | Add Payment | se `payment_status != 'paid'` | `purchase.payments / edit_purchase_payment / delete_purchase_payment` | `TransactionPaymentController@addPayment` |
| 9 | View Payments | sempre se can payments | idem | `TransactionPaymentController@show` |
| 10 | Purchase Return | se can update | `purchase.update` | `PurchaseReturnController@add` |
| 11 | Update Status | se can update OR can update_status | `purchase.update` OR `purchase.update_status` | modal AJAX → `PurchaseController@updateStatus` |
| 12 | Email Notification (ordered) | se `status=ordered` | (sem permission check explícita) | `NotificationController@getTemplate?template_for=new_order` |
| 13 | Email Notification (received) | se `status=received` | idem | template `items_received` |
| 14 | Email Notification (pending) | se `status=pending` | idem | template `items_pending` |

**Row click handler (`setRowAttr` linha 205-212):**
```php
'data-href' => function ($row) {
    if (auth()->user()->can('purchase.view')) {
        return action([PurchaseController::class, 'show'], [$row->id]);
    }
    return '';
}
```

## 7. Eventos disparados

- `purchase_table.ajax.reload()` em todos os filtros (frontend only)
- `PurchaseController@updateStatus` provavelmente dispara observer/event de Transaction
- `PurchaseController@destroy` provavelmente dispara delete event

**TODO smoke:** confirmar via `grep Event::dispatch app/Http/Controllers/PurchaseController.php` antes do STEP 5 Pest.

## 8. Multi-tenant scope (Tier 0 — IRREVOGÁVEL)

✅ `$business_id = request()->session()->get('user.business_id')` (linha 65)
✅ Query `getListPurchases($business_id)` faz join + where business_id
✅ `BusinessLocation::forDropdown($business_id)` (linha 217)
✅ `Contact::suppliersDropdown($business_id, false)` (linha 218)
✅ `permitted_locations` filtering (linha 69-72) — multi-location dentro do tenant

**Nenhuma quebra de Tier 0 observada.** Preservar tal qual no STEP 3 ADAPTAÇÃO CONTROLLER.

## 9. Modais e overlays (DOM atual)

| Modal | Função |
|-------|--------|
| `.product_modal` | exibir detalhe do produto (popover ao clicar) |
| `.payment_modal` | exibir pagamentos do título |
| `.edit_payment_modal` | editar pagamento |
| `.view_modal` | modal genérico pra view/print/notifications |
| `#update_purchase_status_modal` | atualizar status (POST /purchases/update-status) |

**Decisão STEP 2:** drawer/Sheet à direita pode substituir `.view_modal` (padrão Cockpit V2). Modais de payment ficam separados.

## 10. JavaScripts inline (linha 117-170 do index.blade.php)

- `daterangepicker` inicialização
- `update_status` click handler → abre modal
- `update_purchase_status_form` submit AJAX
- Reload DataTable via `purchase_table.ajax.reload()`

**Substituição React:**
- daterangepicker → shadcn `<Calendar>` ou date input pair
- update_status → Sheet com select + submit Inertia router.post
- DataTable AJAX reload → `router.get` com `preserveState`

## 11. Screenshot Blade legacy

**TODO STEP 4** (gate visual): capturar via Chrome MCP em `http://oimpresso.test/purchases` logado com user biz=1 (ADR 0101).

Path destino: `memory/mwart-inventory/purchase/index.blade-screenshot.png`

## 12. Classificação final

| Critério | Valor |
|----------|------:|
| Tipo de tela | **LIST** |
| Complexidade visual | 🟡 Média (filtros 5 + colunas 10 + ações 14 inline) |
| Backend complexity | 🟡 Médio (filters condicionais + permitted_locations + overdue special) |
| Risco Tier 0 | 🟢 Baixo (scope já correto, preservar tal qual) |
| Mockup Cowork | ✅ Disponível (37,9 KB) |
| Pronto pra STEP 2? | ✅ SIM |

## 13. Checklist STEP 1 (cumprido)

- [x] Snapshot existe (este arquivo)
- [x] Rotas + permissões enumeradas
- [x] Campos/colunas/filtros enumerados
- [x] Tipo classificado (LIST)
- [x] Multi-tenant scope mapeado (Tier 0 OK)
- [ ] Screenshot Blade atual capturado (**pendente — STEP 4**)
- [ ] Events::dispatch confirmados via grep (**pendente — STEP 5**)

---

**Refs:**
- [ADR 0141 — skill migracao-blade-react](../../decisions/0141-skill-migracao-blade-react.md)
- [runbook-LIST.template.md](../../../.claude/skills/migracao-blade-react/runbook-LIST.template.md)
- [ADR 0093 — Multi-tenant Tier 0 IRREVOGÁVEL](../../decisions/0093-multi-tenant-isolation-tier-0.md)
- [ADR 0104 — Processo MWART canônico](../../decisions/0104-processo-mwart-canonico-unico-caminho.md)
