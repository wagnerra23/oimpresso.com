---
tela: purchase/show
tipo: DETAIL
captured_at: 2026-05-11
captured_by: [CL]
blade_path: resources/views/purchase/show.blade.php
partial_main: resources/views/purchase/partials/show_details.blade.php
controller: app/Http/Controllers/PurchaseController.php@show (linha 555-622)
route: GET /purchases/{id} (Route::resource purchases — auto)
bug_critico: "BUG PROD 500 em show_details.blade.php:430 — DNS1D::getBarcodePNG() Trying to access array offset on null"
mockup_cowork: (sem mockup específico de show — usar runbook-DETAIL.template.md)
status: snapshot-complete
---

# Snapshot — `purchase/show` (DETAIL)

## 1. Identificação

- **Blade legacy:** [show.blade.php](../../../resources/views/purchase/show.blade.php) (modal wrapper) + [show_details.blade.php](../../../resources/views/purchase/partials/show_details.blade.php) (430+ linhas)
- **Controller:** `PurchaseController@show($id)` linha 555-622
- **Bug crítico em prod:** linha 430 — `DNS1D::getBarcodePNG()` quebra → **show 500** em qualquer compra
- **Page destino:** `resources/js/Pages/Purchase/Show.tsx`

## 2. Rota + permissão

- `GET /purchases/{id}` (Route::resource['purchases'] auto — não está no `->except(['show'])`)
- **⚠️ Permission check comentada** no Controller (linhas 557-559) — Wagner ou alguém comentou. Vou re-adicionar `purchase.view` no path Inertia.

## 3. Eloquent eager load (Controller@show linha 566-578)

`Transaction::with(['contact', 'purchase_lines', 'purchase_lines.product', 'purchase_lines.product.unit', 'purchase_lines.product.second_unit', 'purchase_lines.variations', 'purchase_lines.variations.product_variation', 'purchase_lines.sub_unit', 'location', 'payment_lines', 'tax'])`

## 4. Seções da view (paridade)

| Seção | Origem Blade | Migração |
|-------|--------------|----------|
| Header (ref_no + date) | linhas 1-15 | `PageHeader` shared |
| Supplier card | 17-39 (col-sm-4) | `Card` esquerda |
| Business card | 41-68 | `Card` meio |
| Resumo (ref/date/status/payment) | 70-99 | `Card` direita |
| Purchase order info | 100-138 | omitir MVP (type=purchase_order específico) |
| Tabela items | 142-247 | `<table>` Tailwind |
| Pagamentos | 249-294 | Card abaixo da tabela |
| Totals | 296-415 | Card lateral |
| Notas adicionais | 407-416 | Card |
| Activity log | 418-425 | omitir MVP |
| **Barcode (BUG!)** | 428-432 | **omitir** (bug 500) |

## 5. Campos cobertos no MVP (paridade essencial)

✅ ref_no, transaction_date, type, status, payment_status
✅ Supplier: name, contact_address, tax_number, mobile, email, document_path
✅ Business+Location: name, landmark, city, state, country, tax_label_1/2
✅ Items: name, sku, quantity, unit, purchase_price, discount, tax, subtotal
✅ Payments: paid_on, payment_ref_no, amount, method
✅ Totals: net_total, discount, tax, shipping_charges, final_total
✅ Notas adicionais

🟡 Pular MVP (v0.2): activity log, barcode, custom fields, shipping detail, additional expenses, purchase_order specific fields

## 6. Permissões

- `purchase.view` (re-adicionar — comentada no Controller)
- `purchase.update` (botão Editar)
- `purchase.delete` (botão Excluir)

## 7. Tier 0 (preservar)

✅ `business_id = session('user.business_id')` (linha 561)
✅ `Transaction::where('business_id', $business_id)->where('id', $id)->firstOrFail()` (linha 564) — Tier 0 OK
✅ `TaxRate::where('business_id', $business_id)` (linha 562)

## 8. Decisão arquitetural

**Substituir COMPLETAMENTE Blade legacy** (não dual-path). Razões:
1. Blade legacy **está 500 em prod** (linha 430 quebrada)
2. Manter dual = continuar exposing path quebrado
3. Inertia mata o bug por substituição

**Estratégia URL:**
- `GET /purchases/{id}` (browser direto OU Inertia router) → **Inertia::render**
- `GET /purchases/{id}` com `request()->ajax() && !request()->header('X-Inertia')` (modal AJAX legacy do Blade index) → manter view legacy (mas Blade está quebrado, então tanto faz — quem usa essa via vai ver erro Flare)

Após PR /purchases lista virar 100% Inertia (futuro), remover path AJAX legacy.

## Checklist STEP 1

- [x] Snapshot escrito
- [x] Rotas + permissão enumeradas
- [x] Campos/seções enumeradas
- [x] Eager load mapeado
- [x] Tipo (DETAIL) confirmado
- [x] Tier 0 preservação mapeada
- [x] Bug crítico identificado (linha 430 DNS1D)
- [ ] Screenshot Blade legacy (✗ não consigo — Blade está 500 em prod)

---

**Refs:** [ADR 0141](../../decisions/0141-skill-migracao-blade-react.md) · [runbook-DETAIL](../../../.claude/skills/migracao-blade-react/runbook-DETAIL.template.md) · [ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md)
