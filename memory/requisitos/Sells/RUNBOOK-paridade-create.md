---
name: RUNBOOK paridade /sells/create — Blade legacy vs Inertia React
description: Checklist exaustivo de features Blade vs Inertia · base para iteração design SEM regressão · prioridade alta canary Martinho biz=164
type: runbook
status: live
ultima_atualizacao: 2026-05-14
relacionado:
  - resources/views/sell/create.blade.php (Blade legacy 996 LOC) + partials/
  - resources/views/sale_pos/create.blade.php (POS rápido — espelho)
  - resources/views/sale_pos/partials/ (40 partials POS)
  - resources/js/Pages/Sells/Create.tsx (Inertia React 56KB · 1391 LOC)
  - resources/js/Pages/Sells/Create.charter.md
  - resources/js/Pages/Sells/_components/ (PaymentRow, CustomerSearch, ProductSearch)
  - app/Http/Controllers/SellController.php@create() (linhas 676-880)
gate_paridade: obrigatorio_antes_iteracao_design
canary_target: martinho_cacambas_biz_164_2026-05-19
personas_canary: lara_estoque · dani_financeiro · kamila_decisora
---

# RUNBOOK paridade `/sells/create` — Blade legacy ↔ Inertia React

> Wagner pediu 2026-05-14 noite: *"eu quero o layout do designer mas sempre fica incompleto · prefiro deixar como já estava funcionando · o cliente já usa, no mínimo teria que ter o que já existe"*. Esta checklist é **gate obrigatório** antes de qualquer iteração design futura.

> Cliente piloto: **MARTINHO CAÇAMBAS LTDA** (biz=164). Canary semana 19/maio. Personas que verão a tela: **Lara** (filha, estoque) + **Dani** (financeiro) + **Kamila** (esposa Jair, #2 decisora). Pain #1 da reunião 13/maio: *"velocidade pra abrir uma venda"*. **Regressão funcional = fracasso canary.**

---

## 1. Objetivo

Comparar **feature por feature** entre o `/sells/create` Blade legacy (que ROTA LIVRE biz=4 usa em produção 2+ anos) e o `/sells/create` Inertia React atual (que Martinho biz=164 vai canary 19/maio). Catalogar gaps (P0/P1/P2), validar paridade funcional mínima, e servir de base segura pra **iteração de design supervisionada** (ADR 0107 Visual Comparison Gate F3).

A premissa do oimpresso é que ferramenta legacy nunca regride sem aviso: ROTA LIVRE = 99% do volume; perder uma funcionalidade que Larissa usa hoje = perda imediata de receita + confiança. Mesma régua aplica a Martinho (canary) a partir de 19/maio.

Esta auditoria **não toca código**. Só lê + documenta.

---

## 2. Fontes analisadas

| Arquivo | LOC | Notas |
|---|---|---|
| `resources/views/sell/create.blade.php` | 996 | Tela "Add Sale" longa — modo formulário direto (não POS rápido). Usada por SellController@create. |
| `resources/views/sale_pos/create.blade.php` | 6.432 (orquestrador) | Tela POS rápido — usa `pos_form` + `pos_sidebar`. Usada por SellPosController@create. |
| `resources/views/sale_pos/create_old.blade.php` | 12.536 | Versão antiga preservada — IGNORADA. |
| `resources/views/sale_pos/partials/pos_form.blade.php` | 244 | Cabeçalho POS: cliente + busca produto + invoice_layout + commission + transaction_date + exchange_rate + price_group + types_of_service + invoice_scheme + is_recurring + kitchen + tabela produtos. |
| `resources/views/sale_pos/partials/pos_form_actions.blade.php` | 174 | Botões fundo POS: Express checkout (cash/card), Multi-pay, Suspend, Credit sale, Draft, Quotation, Recent transactions, Total payable. |
| `resources/views/sale_pos/partials/pos_form_totals.blade.php` | ~ | Totais POS: discount, order tax, RP redeem. |
| `resources/views/sale_pos/partials/pos_sidebar.blade.php` | ~470 | Coluna direita: featured products, categories grid drawer, brands, product list paginado. |
| `resources/views/sale_pos/partials/payment_modal.blade.php` | 367 | Modal pagamento multi-row + change_return + sale_note + shipping summary + customer reward points. |
| `resources/views/sale_pos/partials/payment_row_form.blade.php` | 143 | Linha pagamento: amount + paid_on + method + account + cash denomination + payment_type_details (cartão/cheque/banco/custom). |
| `resources/views/sale_pos/partials/payment_type_details.blade.php` | 117 | Campos extras por método: cartão (nº/holder/tipo/mês/ano/cvv/transaction_no), cheque (nº), TED (conta), custom_pay (transaction_no). |
| `resources/views/sale_pos/partials/edit_shipping_modal.blade.php` | 218 | Modal frete: details + address + charges + status + delivered_to + delivery_person + 5 shipping_custom_fields + shipping_documents (file upload múltiplo). |
| `resources/views/sale_pos/partials/edit_discount_modal.blade.php` | ~100 | Modal desconto: discount_type + discount_amount + RP redeem. |
| `resources/views/sale_pos/partials/edit_order_tax_modal.blade.php` | 37 | Modal imposto pedido: tax_rate_id + tax_calculation_amount. |
| `resources/views/sale_pos/partials/configure_search_modal.blade.php` | 80 | Modal: checkboxes pra search by name/sku/lot/4 custom fields. |
| `resources/views/sale_pos/partials/keyboard_shortcuts.blade.php` | 132 | Mousetrap binds: express_checkout, cancel, draft, pay_n_ckeckout, edit_discount, edit_order_tax, add_payment_row, finalize_payment, recent_product_quantity, add_new_product, weighing_scale. |
| `resources/views/sale_pos/partials/keyboard_shortcuts_details.blade.php` | 122 | Tabela exibe atalhos pro usuário. |
| `resources/views/sale_pos/partials/recurring_invoice_modal.blade.php` | ~80 | Modal assinatura recorrente (cron). |
| `resources/views/sale_pos/partials/recent_transactions_modal.blade.php` | 42 | Modal últimas transações. |
| `resources/views/sale_pos/partials/weighing_scale_modal.blade.php` | 32 | Modal balança (serial port HTML5). |
| `resources/views/sale_pos/partials/suspend_note_modal.blade.php` | 31 | Modal nota suspensão. |
| `resources/views/sale_pos/partials/suspended_sales_modal.blade.php` | 60 | Modal listar vendas suspensas. |
| `resources/views/sale_pos/partials/service_staff_availability_modal.blade.php` | 93 | Modal staff atendimento (restaurant). |
| `resources/views/sale_pos/partials/service_staff_replacement_modal.blade.php` | 130 | Modal substituir staff. |
| `resources/views/sale_pos/partials/invoice_url_modal.blade.php` | 30 | Modal URL fatura compartilhar. |
| `resources/views/sale_pos/partials/show_invoice.blade.php` | 42 | Trigger preview fatura. |
| `resources/views/sale_pos/partials/sale_line_details.blade.php` | 182 | Tabela linhas venda (read-only — usada em show.blade.php). |
| `resources/views/sale_pos/partials/featured_products.blade.php` | ~25 | Grid produtos em destaque. |
| `resources/views/sale_pos/partials/product_list.blade.php` + `_box` + `_paginator` | ~70 | Lista produtos paginada lateral. |
| `resources/views/sale_pos/partials/row_edit_product_price_modal.blade.php` | 84 | Modal editar preço linha (price diff). |
| `resources/views/sale_pos/partials/guest_payment_form.blade.php` | 176 | Pagamento por convidado (link público). |
| `resources/views/sale_pos/partials/pos_details.blade.php` | ~260 | Detalhes da tela POS pra modal show. |
| `resources/views/sale_pos/partials/product_row.blade.php` (em sale_pos/) | 18.339 chars | Template linha de produto JS render (substitui no DOM). **POS jQuery DOM-driven**. |
| `public/js/pos.js` | ~3.178 (estimado pelo charter) | Lógica jQuery do POS. **NÃO LIDO em profundidade** (read-only audit do flow). |
| `resources/js/Pages/Sells/Create.tsx` | 1.391 | Inertia React atual — ler integral. |
| `resources/js/Pages/Sells/Create.charter.md` | 90 | Charter da Page (mission/goals/non-goals). |
| `resources/js/Pages/Sells/_components/PaymentRow.tsx` | 295 | Componente linha pagamento. |
| `resources/js/Pages/Sells/_components/ProductSearchAutocomplete.tsx` | ~250 (limit 80 lido) | Autocomplete produto. |
| `resources/js/Pages/Sells/_components/CustomerSearchAutocomplete.tsx` | ~250 (limit 80 lido) | Autocomplete cliente. |
| `app/Http/Controllers/SellController.php@create()` | linhas 676-880 | Contrato controller — props enviadas pra Inertia + props enviadas pra Blade. |

**Não lido por escopo** (delegado a Wagner se quiser aprofundar):
- `public/js/pos.js` linha por linha — ~3k LOC jQuery, complexo. Inferi comportamento por leitura dos partials Blade.
- `app/Http/Controllers/SellPosController.php@store()` (linhas 352-680) — só interessa pro Edit/store, fora do escopo /sells/create rendering.

---

## 3. Inventário Features Blade legacy (50 itens)

Tabela exaustiva. Coluna `Inertia` resposta:
- ✅ paridade
- ⚠️ parcial (existe mas diferente / incompleto)
- ❌ ausente
- 🟦 N/A (não aplicável a vertical Vestuário/Caçambas — POS rápido, restaurante, balança, etc)

Criticidade (`Crit.`):
- **P0** bloqueante — sem isso, Lara/Dani/Kamila não conseguem fechar venda
- **P1** importante — afeta workflow normal
- **P2** nice-to-have / vertical-específico

### 3.1 Cabeçalho / Dados da venda

| # | Feature | Tipo | Loc Blade | Inertia? | Crit. | Notas |
|---|---|---|---|---|---|---|
| 1 | Título dinâmico ("Adicionar venda" / "Add quotation" / "Add draft" / "Sales order") | label | sell/create.blade.php:4-15 | ⚠️ | P1 | Inertia tem `<h1>Adicionar venda</h1>` fixo. Quotation/draft/sales_order trocam via status field, mas título não muda. Importante: usuária precisa saber que está fazendo orçamento, não venda. |
| 2 | Select localização (`select_location_id`) com `autofocus` + tooltip | select | sell/create.blade.php:41-50 | ✅ | P0 | Inertia: `data.location_id` + `<Select>` no card Dados. Sem autofocus inicial — usuária precisa clicar (1 clique a mais). |
| 3 | Select price group (`price_group`) — só renderiza se >1 group | select | sell/create.blade.php:67-94 | ✅ | P1 | Inertia colapsado em "Mais opções". Detalhe: condicional `hasMultiplePriceGroups` correto. |
| 4 | Select types_of_service + tooltip + price_group_text dinâmico | select | sell/create.blade.php:98-116 | ❌ | P2 | Apenas se módulo `types_of_service` instalado. Não aplicável a Vestuário nem Caçambas (estoque/financeiro). |
| 5 | Checkbox `is_recurring` + botão modal `recurringInvoiceModal` | checkbox+modal | sell/create.blade.php:118-126 | ❌ | P2 | Faturamento recorrente. Não bloqueante pra Caçambas (factory shop). ROTA LIVRE não usa. |
| 6 | Select cliente (`contact_id`) com search via Mousetrap + balance hidden + walk_in defaults | autocomplete | sell/create.blade.php:128-152 | ✅ | P0 | Inertia: `CustomerSearchAutocomplete` debounce 250ms + min 2 chars. Endpoint `/contacts/customers?q=` reusado. Sem `class="mousetrap"`. |
| 7 | Botão `add_new_customer` (+) abre modal contact_modal | btn+modal | sell/create.blade.php:147-149 | ❌ | P0 | Lara/Dani precisam cadastrar cliente novo no meio da venda. Inertia tem `postMessage` listener (linha 555-566 Create.tsx) mas **sem botão (+) visível no UI**. Hoje precisaria abrir nova aba `/contacts/create-page`. **GAP CRÍTICO.** |
| 8 | Display billing_address + shipping_address abaixo do select (livre) | display | sell/create.blade.php:153-169 | ❌ | P1 | Mostra endereço cobrança + envio do cliente selecionado. Dani precisa conferir endereço pra NFe. **GAP** — Inertia não exibe nada após selecionar. |
| 9 | `customer_due_text` (saldo devedor) hidden + revela ao selecionar | display | sell/create.blade.php:151 | ❌ | P1 | Alerta de inadimplência. Lara perguntaria "essa cliente já me deve?". |
| 10 | Pay term `pay_term_number` + `pay_term_type` (months/days) — required configurável | number+select | sell/create.blade.php:172-189 | ✅ | P1 | Inertia: colapsado em "Mais opções". `pay_term_number`/`pay_term_type`. Sem required-config. |
| 11 | Select commission_agent — só renderiza se cmsn_agent configurado | select | sell/create.blade.php:191-202 | ✅ | P2 | Inertia: colapsado, condicional `hasCommissionAgent`. Caçambas não usa. |
| 12 | Campo `transaction_date` (datetime picker readonly) | datetime | sell/create.blade.php:203-213 | ⚠️ | P0 | Inertia: `<Input type="datetime-local">` nativo. Funciona, mas Larissa/Lara estão acostumadas ao moment.js datepicker (Mousetrap suporte). |
| 13 | Select `status` (final/draft/quotation/proforma) — required | select | sell/create.blade.php:214-227 | ✅ | P0 | Inertia: `data.status` default `final`. Sem option `sales_order` separada (mapeada via `sub_type`). |
| 14 | Select `invoice_scheme_id` — hide quando sales_order | select | sell/create.blade.php:228-235 | ✅ | P1 | Inertia: colapsado, default `props.defaultInvoiceScheme?.id`. |
| 15 | Input `invoice_no` (custom) + help "keep blank to autogenerate" — permission `edit_invoice_number` | text | sell/create.blade.php:236-244 | ✅ | P1 | Inertia: colapsado, placeholder "Auto-gerado se vazio". **Mas Inertia NÃO checa `@can('edit_invoice_number')`** — exibe sempre. Pode confundir Dani que não tem permissão. |
| 16 | 4 custom_field_1..4 venda (label dinâmico + required dinâmico via business_settings) | text x4 | sell/create.blade.php:246-322 | ❌ | P1 | Cada business pode configurar 4 labels custom pra venda. Caçambas pode usar pra "tipo de obra", "nº contrato cliente final" etc. **GAP** — Inertia não tem. |
| 17 | File upload `sell_document` (anexo) + accept mime types + help size | file | sell/create.blade.php:323-332 | ❌ | P1 | Anexar contrato/foto autorização. Lara/Dani precisam anexar PDF assinado. **GAP**. |
| 18 | Multi-select `sales_order_ids[]` — vincular pedidos a venda | select multi | sell/create.blade.php:335-343 | ❌ | P2 | Apenas se `enable_sales_order` ou `is_order_request_enabled`. Não bloqueante. |
| 19 | Restaurant module span (tables/service_staff) — render JS injetado | span | sell/create.blade.php:345-348 | 🟦 | — | Não aplicável (Caçambas não é restaurante). |

### 3.2 Busca de produtos + tabela produtos

| # | Feature | Tipo | Loc Blade | Inertia? | Crit. | Notas |
|---|---|---|---|---|---|---|
| 20 | Input `search_product` com class `mousetrap` (atalhos) | autocomplete | sell/create.blade.php:358-361 | ✅ | P0 | Inertia: `ProductSearchAutocomplete`. Endpoint `/products/list?term=`. Reusado. |
| 21 | Botão `configure_search_modal` (config busca por name/sku/lot/4 custom fields) | btn+modal | sell/create.blade.php:356 | ❌ | P2 | Configura quais campos pesquisar. Lara raramente toca. **Mas se busca atual não acha produto que ela queria (search só por nome), ela vai perguntar "por que não acha pelo código de barras?"**. |
| 22 | Botão `pos_add_quick_product` (+) abre modal quick_add_product | btn+modal | sell/create.blade.php:362-364 | ❌ | P1 | Lara cadastra produto novo no meio da venda (caçamba customizada). **GAP** — Inertia não tem botão "+novo produto" inline. |
| 23 | Tabela `pos_table` com colunas dinâmicas: Produto/Qtd/[Service staff]/Unit price/Discount/Tax/Price inc tax/[Warranty]/Subtotal/× | table | sell/create.blade.php:382-419 | ⚠️ | P0 | Inertia: 5 colunas (Produto, Qtd, Preço unit, Desconto, Subtotal). **AUSENTES**: tax inline, price inc tax (preço c/ imposto), warranty, service staff. Caçambas usa imposto? Wagner confirmar. |
| 24 | Permission `edit_product_price_from_sale_screen` esconde coluna Unit price | column hide | sell/create.blade.php:397 | ⚠️ | P1 | Inertia: `disabled={!props.permissions.editPrice}` no input. Mas mostra a coluna (não esconde). Dani sem permissão consegue VER, não EDITAR. |
| 25 | Permission `edit_product_discount_from_sale_screen` esconde coluna Desconto | column hide | sell/create.blade.php:400 | ⚠️ | P1 | Mesma observação 24. |
| 26 | Esconder colunas tax (`{$hide_tax}`) se `enable_inline_tax = 0` | column hide | sell/create.blade.php:376-381 | 🟦 | — | Inertia não tem coluna tax inline mesmo. |
| 27 | Coluna Warranty se `enable_product_warranty` | column | sell/create.blade.php:409-411 | ❌ | P2 | Garantia por linha. Não aplicável Caçambas. Para gráfica (ComVis) pode importar. |
| 28 | Footer tabela: total_quantity + price_total | display | sell/create.blade.php:421-435 | ✅ | P0 | Inertia: `<tfoot>` com Subtotal + KPI cards no topo (Itens / Total venda). Melhor visualmente. |
| 29 | Hidden `sell_price_tax`, `product_row_count` | hidden | sell/create.blade.php:371-375 | ✅ | — | Inertia: managed via state, não precisa expor hidden inputs. |

### 3.3 Desconto + RP + Imposto + Notas

| # | Feature | Tipo | Loc Blade | Inertia? | Crit. | Notas |
|---|---|---|---|---|---|---|
| 30 | Select `discount_type` (fixed/percentage) — hide quando sales_order | select | sell/create.blade.php:439-449 | ✅ | P0 | Inertia: `data.discount_type` no card Resumo. |
| 31 | Input `discount_amount` com `data-max-discount` (per user) + msg erro | number | sell/create.blade.php:464-474 | ✅ | P0 | Inertia: `validateDiscount()` + `discountError` state. Bom. Inertia exibe `(máx X%)` inline. |
| 32 | Display "Discount amount: -X" texto | display | sell/create.blade.php:475-478 | ✅ | P1 | Inertia: linha "Desconto do pedido" no resumo. |
| 33 | Reward Points (`rp_redeemed_modal` + `rp_redeemed_amount`) — só se `enable_rp` business setting | number+display | sell/create.blade.php:480-502 | ❌ | P2 | Programa de pontos. Não aplicável Caçambas. ROTA LIVRE não usa. |
| 34 | Select `tax_rate_id` (order tax) + `tax_calculation_amount` hidden | select | sell/create.blade.php:504-517 | ⚠️ | P1 | Inertia: colapsado, sem `tax_calculation_amount` (importante pra impostos compostos brasileiros — Wagner confirmar relevância pra Martinho NFe). |
| 35 | Display "Order tax: +X" | display | sell/create.blade.php:518-521 | ❌ | P1 | Inertia não mostra linha "Imposto do pedido: +R$ X" no resumo. Total geral não bate se houver tax. **Verificar cálculo Inertia inclui tax**. |
| 36 | Textarea `sale_note` | textarea | sell/create.blade.php:523-528 | ✅ | P1 | Inertia: `data.notes`. **Atenção**: Blade chama `sale_note`, Inertia chama `notes` mas remapeia via `transform()` linha 379. OK. |
| 37 | Hidden `is_direct_sale=1` | hidden | sell/create.blade.php:529 | ✅ | P0 | Inertia: linha 373 `is_direct_sale: 1` na transform. Crítico — sem isso controller cai em cashRegister check. |

### 3.4 Frete / Shipping (5 campos + 5 custom fields + documento)

| # | Feature | Tipo | Loc Blade | Inertia? | Crit. | Notas |
|---|---|---|---|---|---|---|
| 38 | Textarea `shipping_details` | textarea | sell/create.blade.php:532-537 | ⚠️ | P1 | Inertia: `<Input>` (não textarea). Caçambas usa pra "tipo de transporte / placa veículo". Larissa usa pra "complemento, ponto referência". |
| 39 | Textarea `shipping_address` | textarea | sell/create.blade.php:538-543 | ⚠️ | P1 | Mesma observação — `<Input>` virou texto curto. Endereço pode ter 2 linhas. |
| 40 | Input `shipping_charges` (input_number) | text | sell/create.blade.php:544-554 | ✅ | P1 | Inertia: `shipping.cost`. OK. |
| 41 | Select `shipping_status` | select | sell/create.blade.php:556-561 | ✅ | P1 | Inertia: `shipping.status`. |
| 42 | Input `delivered_to` | text | sell/create.blade.php:562-567 | ✅ | P1 | Inertia: `shipping.deliver_to`. |
| 43 | Select `delivery_person` (users dropdown) | select | sell/create.blade.php:568-573 | ❌ | P1 | Quem entrega? Motorista. Caçambas tem motorista dedicado por caminhão. **GAP** — Inertia não tem campo delivery_person. |
| 44 | 5 `shipping_custom_field_1..5` (label dinâmico + required configurável) — pre-fill do cliente | text x5 | sell/create.blade.php:574-670 | ❌ | P1 | Cliente pode ter "campo personalizado entrega" salvo (ex: "código portaria"). **GAP** — não recupera. Lara teria que digitar todo dia. |
| 45 | File `shipping_documents[]` (multi-upload) | file multi | sell/create.blade.php:671-680 | ❌ | P2 | Foto comprovante entrega. Caçambas pode usar (foto carga descarregada). |
| 46 | Toggle button "Adicionar despesas adicionais" + tabela 4 expense rows (key+value) | btn+table | sell/create.blade.php:682-728 | ✅ | P1 | Inertia: `<details>` "Despesas adicionais" + 4 rows. Bom. |
| 47 | Round-off display + hidden `round_off_amount` | display | sell/create.blade.php:729-735 | ❌ | P2 | Arredondamento do total. Configurável business. Não bloqueante Caçambas. |
| 48 | Display "Total payable" + hidden `final_total` | display | sell/create.blade.php:736-740 | ✅ | P0 | Inertia: KPI "Total venda" + "Total geral" no resumo. Mais visual. |

### 3.5 Export internacional + Reward Points

| # | Feature | Tipo | Loc Blade | Inertia? | Crit. | Notas |
|---|---|---|---|---|---|---|
| 49 | Checkbox `is_export` + 6 `export_custom_field_*` (pre-fill cliente) | checkbox+text x6 | sell/create.blade.php:744-764 | ❌ | P2 | Venda exportação. Não aplicável Caçambas/ROTA LIVRE. |

### 3.6 Pagamento (na tela `/sells/create`, não modal)

| # | Feature | Tipo | Loc Blade | Inertia? | Crit. | Notas |
|---|---|---|---|---|---|---|
| 50 | Select `prefer_payment_method` + `prefer_payment_account` (PDF) — só se `enable_download_pdf` | select x2 | sell/create.blade.php:776-801 | ❌ | P2 | "Método preferido pra PDF". Microsetting. |
| 51 | Display advance_balance + hidden | display | sell/create.blade.php:806-810 | ❌ | P1 | Saldo adiantado do cliente. Dani precisa ver pra abater. **GAP**. |
| 52 | Include `payment_row_form` (row_index=0, show_date, show_denomination) | partial | sell/create.blade.php:811 | ⚠️ | P0 | Inertia: `PaymentRow` por linha. **AUSENTE**: cash_denomination tabela (notas R$200/100/50/20/10/5/2). Lara em caixa físico Caçambas conta nota a nota — ela ESPERA isso. |
| 53 | Display change_return + hidden + change_return_method/account quando troco | display+select | sell/create.blade.php:813-871 | ⚠️ | P0 | Inertia: card "Troco" no resumo. Mas **NÃO permite definir método/conta do troco** separado. Lara dá troco em cash de venda em pix → precisa mudar método. **GAP**. |
| 54 | Display balance_due (saldo a pagar) | display | sell/create.blade.php:866-870 | ✅ | P1 | Inertia: card "Falta R$ X" no Status pgto + resumo. Bom. |

### 3.7 Botões ação + atalhos

| # | Feature | Tipo | Loc Blade | Inertia? | Crit. | Notas |
|---|---|---|---|---|---|---|
| 55 | Botão `submit-sell` (Save) + `save-and-print` (Save & Print) | btn x2 | sell/create.blade.php:880-882 | ✅ | P0 | Inertia: 2 botões footer sticky: Salvar venda + Salvar e Imprimir. Bom. |
| 56 | Atalho Mousetrap (`{{$shortcuts.pos.express_checkout}}` etc) — bind dinâmico via business config | shortcut | sale_pos/partials/keyboard_shortcuts.blade.php | ⚠️ | P1 | Inertia: hardcoded Ctrl+Enter (submit) + Esc (blur). **AUSENTES**: shift+1..9 (recent product qty), shift+P (add product focus), F2 (edit discount), F4 (edit tax), shift+S (suspend), shift+D (draft). Caçambas: Lara faz 30 vendas/dia, atalhos são P0. **GAP grande**. |
| 57 | Atalho `recent_product_quantity` (foca qtd última linha) | shortcut | keyboard_shortcuts.blade.php:74-115 | ❌ | P1 | Lara escaneia 1 produto → quer ajustar qtd sem tirar mão do teclado. **GAP**. |
| 58 | Atalho `add_new_product` (foca campo busca) | shortcut | keyboard_shortcuts.blade.php:118-122 | ❌ | P0 | Em vendas com 10+ produtos, foco automático no search é crítico. Inertia tem botão "Buscar produto" mas exige clique. **GAP**. |
| 59 | Atalho `weighing_scale` (abre modal balança) | shortcut | keyboard_shortcuts.blade.php:125-131 | 🟦 | — | Caçambas não pesa nem ROTA LIVRE. |

### 3.8 Modais auxiliares

| # | Feature | Tipo | Loc Blade | Inertia? | Crit. | Notas |
|---|---|---|---|---|---|---|
| 60 | Modal `contact_modal` (quick add cliente) | modal | sell/create.blade.php:892-894 | ❌ | P0 | Já catalogado item 7. Cadastrar cliente no meio. **GAP CRÍTICO**. |
| 61 | Modal `register_details_modal` + `close_register_modal` | modal | sell/create.blade.php:896-901 | 🟦 | — | Caixa registradora (cash register check). Inertia bypassa via `is_direct_sale=1`. OK. |
| 62 | Modal `quick_add_product_modal` | modal | sell/create.blade.php:903-904 | ❌ | P1 | Já catalogado item 22. |
| 63 | Modal `types_of_service_modal` | modal | sell/create.blade.php:906 | ❌ | P2 | Tipo de serviço — não Caçambas. |
| 64 | Modal `configure_search_modal` (config search products) | modal | sell/create.blade.php:909 | ❌ | P2 | Já catalogado item 21. |
| 65 | Modal `recurringInvoiceModal` (assinatura) | modal | sell/create.blade.php:886-887 | ❌ | P2 | Já catalogado item 5. |

### 3.9 Comportamentos JS específicos

| # | Feature | Tipo | Loc Blade | Inertia? | Crit. | Notas |
|---|---|---|---|---|---|---|
| 66 | `status` change → mostra/esconde `payment_rows_div` | event | sell/create.blade.php:924-930 | ✅ | P1 | Inertia: payment section sempre visível. Diferença sutil — Lara em draft (rascunho) talvez não queira ver pagamentos. |
| 67 | `transaction_date` + `paid_on` Datetimepicker (jQuery) | widget | sell/create.blade.php:931-934 | ⚠️ | P1 | Inertia: `<Input type="datetime-local">` nativo (formato YYYY-MM-DD). Larissa/Lara acostumadas DD/MM/AAAA HH:mm. Inertia tem `toDatetimeLocal/fromDatetimeLocal` conversão. OK funcional, sub-óptimo UX. |
| 68 | `shipping_documents` Fileinput plugin | widget | sell/create.blade.php:936-941 | ❌ | P2 | Já catalogado item 45. |
| 69 | `prefer_payment_method` onChange → preenche `prefer_payment_account` default | event | sell/create.blade.php:943-958 | ❌ | P2 | Já catalogado item 50. |
| 70 | `is_export` checkbox toggle `.export_div` show/hide | event | sell/create.blade.php:982-988 | ❌ | P2 | Já catalogado item 49. |
| 71 | Amount rounding (`pos_settings.amount_rounding_method`) — hidden + display dinâmico | logic | sell/create.blade.php:26, 730 | ❌ | P2 | Já catalogado item 47. |
| 72 | `is_overselling_allowed` hidden — desliga alerta estoque negativo | hidden | sell/create.blade.php:27-29 | ❌ | P1 | Caçambas usa? Wagner confirmar. Permite vender produto sem estoque. **Risco silencioso**. |
| 73 | `reward_point_enabled` hidden | hidden | sell/create.blade.php:30-32 | ❌ | P2 | Já catalogado item 33. |
| 74 | `item_addition_method` hidden (controla "soma qtd" vs "nova linha") | hidden | sell/create.blade.php:57 | ❌ | P1 | Business setting: ao escanear produto repetido, soma qtd ou cria 2ª linha? Inertia **sempre cria 2ª linha** (FOR loop em handleAddProduct linha 270-283). Caçambas pode preferir soma. **GAP comportamento**. |

### 3.10 Comportamentos do POS (sale_pos/create.blade.php — espelho)

> POS é "tela rápida" usada **principalmente por Kamila legacy Delphi** — ela continua Delphi pós-canary. Mas Lara pode entrar no POS via menu. Catalogar gaps relevantes mesmo assim.

| # | Feature POS | Loc POS Blade | Inertia? | Crit. | Notas |
|---|---|---|---|---|---|
| 75 | Express checkout cash (botão verde "Pagar dinheiro") + atalho | pos_form_actions.blade.php:30-34 | ❌ | P2 | Inertia não é tela POS — POS continua Blade. Sub-tela `/sale-pos/create`. |
| 76 | Express checkout card | pos_form_actions:83-89 | ❌ | P2 | Mesma observação. |
| 77 | Multi-pay checkout (botão azul "Pay with multiple") | pos_form_actions:22-27 | ❌ | P2 | Inertia tem multi-pay inline (não modal). Filosoficamente diferente. |
| 78 | Suspend sale (pausa venda → retoma depois) | pos_form_actions:60-69 | ❌ | P1 | Lara atende telefone, suspende venda, atende, retoma. **Inertia tem draft localStorage** (linha 496-552 Create.tsx) — equivalente, MAS só 1 draft por user, não named. Suspended sales no Blade são named + listáveis. **GAP parcial**. |
| 79 | Credit sale (venda fiado direto) | pos_form_actions:72-82 | ❌ | P2 | Atalho pra venda fiado. Lara faz fiado pra cliente conhecido. Hoje no Inertia: status=final + pagamento R$0 = fiado implícito. Funciona, sub-óptimo. |
| 80 | Draft button (rascunho) | pos_form_actions:46-51 | ⚠️ | P1 | Inertia: status=draft no select. Mas Blade tem botão dedicado (1 clique). Lara fala "salvar rascunho" → no Inertia ela vai pro status. |
| 81 | Quotation button | pos_form_actions:53-58 | ⚠️ | P1 | Mesma observação. |
| 82 | Recent transactions button (top right) | pos_form_actions:137-143 | ❌ | P1 | Botão "Últimas vendas" — Lara perde uma venda no meio, abre rapidamente histórico. **GAP**. |
| 83 | Pos sidebar: featured products / categories drawer / brands / product paginator | pos_sidebar.blade.php:1-470 | ❌ | P2 | Tela POS extra. Inertia (Sells/Create longa) não tem sidebar. Para Caçambas (~5 categorias caçamba — 3m/4m/6m/8m + acessório) sidebar com bigshot category buttons aceleraria 10 vendas/dia. Não bloqueante MVP. |
| 84 | Configure search modal | configure_search_modal.blade.php | ❌ | P2 | Já catalogado item 21. |
| 85 | Recent transactions modal | recent_transactions_modal.blade.php | ❌ | P1 | Já catalogado item 82. |
| 86 | Weighing scale modal | weighing_scale_modal.blade.php | 🟦 | — | Caçambas não pesa. |
| 87 | Service staff modals (restaurant) | service_staff_*.blade.php | 🟦 | — | Não restaurante. |
| 88 | Mobile product suggestions | mobile_product_suggestions.blade.php | ❌ | P2 | Mobile-specific. Inertia responsive mas sem suggestions. |
| 89 | Invoice URL modal (share link) | invoice_url_modal.blade.php | ❌ | P2 | Pós-venda. |
| 90 | Row edit product price modal (preço diferenciado individual) | row_edit_product_price_modal.blade.php | ⚠️ | P1 | Inertia: input direto "preço unit." na linha (sem modal). Funciona, mas modal Blade tem "discount type/amount per line" + "tax per line" — Inertia só tem `discount` (sem tipo). **Caçambas: preço diferenciado por cliente é comum**. |

---

## 4. Cross-check Sells/Create.tsx → Blade

Features que **Inertia adicionou** ou modificou:

| Feature Inertia | Loc | Blade tem? | Necessário canary? |
|---|---|---|---|
| Filter pills sticky com scroll-spy (Dados/Produtos/Pagamento/Resumo/Mais opções) | Create.tsx:625-666 | ❌ | NICE — Lara nunca viu mas curva fácil. |
| 4 KPIs gigantes (Itens / Total / Pago / Status pgto) | Create.tsx:673-734 | ❌ | NICE — visual quente. Status pgto colorido = bom feedback. |
| Auto-save draft localStorage 500ms debounce + recover ao montar + TTL 24h | Create.tsx:496-552 | ❌ | SIM — Larissa atende telefone. Equivalente parcial ao "Suspend" do Blade (item 78). |
| Cmd+Enter / Ctrl+Enter submit | Create.tsx:452-462 | ❌ | NICE — adicional aos atalhos legacy. |
| Esc top-level blur | Create.tsx:464-477 | ❌ | NICE. |
| Auto-open `<details>` "Mais opções" quando erro em campo colapsado | Create.tsx:479-494 | ❌ | SIM — fix gap UX (US-SELL-010). |
| FieldError inline `role="alert"` por campo | Create.tsx:124-131 | ⚠️ Blade tem help-block | SIM — acessibilidade. |
| Footer sticky com botões + texto de status validação | Create.tsx:1352-1385 | ❌ | NICE — botões sempre visíveis em form longo. |
| postMessage listener pra contato criado | Create.tsx:555-566 | ❌ | NICE — mas sem botão (+) visível, recurso fica oculto. |
| Conversão `toDatetimeLocal`/`fromDatetimeLocal` | Create.tsx:135-147 | ❌ (Blade usa datetimepicker BR direto) | OBRIGATÓRIO — mas piora UX por trocar formato. |

---

## 5. Gaps críticos (P0 — bloqueantes)

> **5 gaps P0 — sem isso, Lara/Dani/Kamila não conseguem fechar venda como hoje no Blade.**

### P0-1. Botão (+) cadastrar cliente no meio da venda — AUSENTE

- **Blade:** `<button class="add_new_customer">` abre `contact_modal` (linha 147-149 sell/create.blade.php)
- **Inertia:** tem `postMessage` listener (linha 555-566) que aceita contato criado em outra aba, mas **sem botão (+) na UI**. Lara não sabe que precisa abrir `/contacts/create-page` em nova aba.
- **Risco canary:** Lara fala "esse cliente é novo" — vai abrir Backoffice menu → Contatos → Novo → preencher → voltar. Lentidão = atrita demo. Wagner pediu **velocidade** (Pain #1 reunião 13/maio).
- **Fix mínimo:** botão "+" ao lado do `CustomerSearchAutocomplete` que abre `/contacts/create-page` em popup; postMessage já funciona.

### P0-2. Botão (+) cadastrar produto no meio da venda — AUSENTE

- **Blade:** `<button class="pos_add_quick_product">` abre `quick_add_product_modal` (linha 362-364)
- **Inertia:** nada equivalente.
- **Risco canary:** Lara precisa criar SKU novo "Caçamba 5m³ customizada" no momento da venda. Hoje vai pra Catálogo → Produtos → Novo → preencher 15 campos → voltar. 5 min perdidos. **Pain real Caçambas — produtos customizados são comuns**.
- **Fix mínimo:** botão "+ novo produto" ao lado do `ProductSearchAutocomplete` → popup `/products/quick-add` → postMessage retorna produto criado.

### P0-3. Atalhos teclado pra navegação rápida — AUSENTES (~7 atalhos)

- **Blade:** Mousetrap binds dinâmicos por business config: `add_new_product` (foca search), `recent_product_quantity` (foca qtd última linha), `edit_discount`, `edit_order_tax`, `add_payment_row`, `pay_n_ckeckout` (multi-pay), `cancel`, `draft`, `quotation`, `suspend`, `weighing_scale`, `express_checkout` cash.
- **Inertia:** só Ctrl+Enter (submit) + Esc (blur).
- **Risco canary:** Lara faz **30 vendas/dia** (estimativa). Cada venda usa 3-5 atalhos. Tirar atalhos = +30s/venda × 30 = +15min/dia perdidos. Pain #1 era velocidade — esse gap **viola diretamente** o pedido.
- **Fix mínimo:** binds Mousetrap-equivalentes em React: tecla `/` foca search, `Alt+P` adiciona row pagamento, `F2` foca discount, `F9` submit. Configurável via props.

### P0-4. Cash denomination (contagem nota a nota) — AUSENTE

- **Blade:** `payment_row_form.blade.php:58-121` — tabela com cada denominação (R$200, R$100, R$50, R$20, R$10, R$5, R$2, R$1) e count.
- **Inertia:** só campo valor total.
- **Risco canary:** Lara em caixa físico Caçambas (mesa Dani) recebe R$ 1.350 em cash — quer registrar "1×R$1000 + 3×R$100 + 1×R$50". Inertia força ela a somar mental. **Pain real** + erro contábil futuro (relatório fechamento caixa não bate).
- **Fix mínimo:** expandir `PaymentRow.tsx` com seção opcional "Detalhar denominações" — só renderiza se `pos_settings.enable_cash_denomination_on` setado.

### P0-5. Display endereço cobrança/envio do cliente + saldo devedor — AUSENTE

- **Blade:** após selecionar cliente, mostra `billing_address_div` + `shipping_address_div` + `contact_due_text` (linha 153-169 sell/create.blade.php)
- **Inertia:** após selecionar, só guarda `contact_id`. Sem display.
- **Risco canary:** Dani precisa conferir CNPJ + endereço pra NFe; Lara perguntaria "essa cliente já me deve?". **Confiança quebra** — equivale a "achei que tinha selecionado certo, mas não tenho como conferir".
- **Fix mínimo:** após onSelect do `CustomerSearchAutocomplete`, fetch `/contacts/{id}` e mostrar card com nome + CNPJ + endereço + saldo devedor.

---

## 6. Gaps menores (P1/P2)

### P1 — importantes (workflow normal degrada sem)

- **P1-1.** 4 custom_fields venda (item 16) — Caçambas pode usar pra "tipo de obra / nº contrato" — **Wagner confirmar**.
- **P1-2.** Upload arquivo `sell_document` (item 17) — contrato assinado / autorização caçamba.
- **P1-3.** Tabela produtos sem coluna **Tax / Price inc tax** (item 23) — se Caçambas usa NFe com ICMS, precisa ver imposto por linha. **Wagner confirmar relevância pra Caçambas (CNAE 4520-0/01).**
- **P1-4.** Permissions UI inconsistentes — `edit_invoice_number` ignorado (item 15), `edit_product_price` mostra coluna disabled em vez de esconder (item 24-25). Dani vai ver botão e não conseguir clicar → confunde.
- **P1-5.** Display saldo adiantado cliente `advance_balance` (item 51) — Dani precisa abater.
- **P1-6.** Cash denomination linha do troco — método/conta separados (item 53). Lara troco em cash de venda em pix.
- **P1-7.** `delivery_person` campo (item 43) — Caçambas tem motorista dedicado por caminhão.
- **P1-8.** 5 `shipping_custom_fields` pre-fill cliente (item 44) — "código portaria" por cliente.
- **P1-9.** `item_addition_method` (item 74) — somar qtd vs nova linha em produto repetido. **Wagner confirmar setting Caçambas**.
- **P1-10.** Botão Suspend dedicado (item 78) — atalho 1-clique pra pausar venda nomeada.
- **P1-11.** Botão Recent transactions (item 82) — Lara perde venda no meio, quer histórico rápido.
- **P1-12.** Datetime picker formato BR (item 12, 67) — `datetime-local` HTML5 vs jQuery moment.js. Larissa/Lara acostumadas ao formato BR.
- **P1-13.** Title dinâmico orçamento/rascunho/sales_order (item 1) — Wagner confirmar se Caçambas faz orçamento de caçamba antes da entrega.
- **P1-14.** Status mostra/esconde payment section (item 66) — em draft talvez não queira pagamento.
- **P1-15.** Order tax display no resumo (item 35) — verificar se cálculo total inclui tax.

### P2 — nice-to-have / vertical-específico

- Reward Points (33), Recurring invoice (5), Types of service (4), Sales order links (18), Warranty (27), Weighing scale (59, 86), Restaurant (19, 87), Export (49), Round-off (47), Prefer payment PDF (50), Configure search modal (21), POS sidebar categories (83), Mobile suggestions (88), Invoice URL share (89), Express checkout cash/card buttons (75-77), Credit sale dedicado (79), Featured products (83).

> Todos P2 são candidatos a **adiar pós-canary**. Lara/Dani/Kamila não dependem deles.

---

## 7. Features Inertia novas (vs Blade)

Inertia adicionou 10 features ausentes no Blade:

| Feature Inertia | Validação canary necessária |
|---|---|
| Filter pills sticky (Dados/Produtos/Pagamento/Resumo/Mais opções) | NICE — verificar se Lara entende navegação por pill (curva fácil). |
| 4 KPIs gigantes coloridos | NICE — Status pgto colorido (rose/amber/emerald) ajuda confirmar fechamento. |
| Auto-save draft localStorage debounced | SIM — equivale parcialmente ao Suspend (item 78). Confirmar Lara/Dani entendem que F5 não perde dados. |
| Cmd+Enter / Ctrl+Enter submit | NICE — atalho extra aos legacy. |
| Esc blur top-level | NICE. |
| Auto-open Mais opções quando erro colapsado | SIM (fix gap UX detectado design-arte 13/maio). |
| FieldError inline `role="alert"` | SIM — acessibilidade Lei BR. |
| Footer sticky com texto status validação ("Falta R$ X pra fechar") | NICE — bom feedback. |
| postMessage listener contato criado | NICE — recurso oculto sem botão (+). Resolve P0-1 quando botão for adicionado. |
| `<details>` "Mais opções" colapsável + persist localStorage | NICE — esconde 10 campos secundários. Risk: usuária pode não saber onde fica `pay_term`. |

---

## 8. Recomendação executiva (5 bullets)

> Wagner: o trauma é justificado. **Inertia atual tem ~75% de paridade funcional com Blade legacy**. Os 25% de gap incluem 5 features P0 que Lara/Dani/Kamila ESPERAM ver (cadastro cliente/produto inline, atalhos teclado, cash denomination, display endereço cliente). Sem fix desses 5, canary Martinho 19/maio é arriscado.

1. **Não iterar design agora.** Antes de mexer em pill/KPI/filter, fechar **paridade P0** (5 gaps). Iteração visual em cima de base incompleta = perda dupla (cliente nota gap + repete pain trauma).

2. **5 PRs cirúrgicos pré-canary (estimativa fator 10x ADR 0106 + margem 2x):**
   - PR-1: botão (+) cliente inline + popup `/contacts/create-page` + display nome/CNPJ/endereço/saldo após selecionar (~3h)
   - PR-2: botão (+) produto inline + popup `/products/quick-add` (~2h)
   - PR-3: 7 atalhos teclado (`/`, `Alt+P`, `F2`, `F9`, etc) via library `react-hotkeys-hook` ou similar (~3h)
   - PR-4: cash denomination expansível no PaymentRow (só renderiza se setting on) (~2h)
   - PR-5: troco método/conta separados (~1h)
   - **Total ~11h foco**. 2 dias úteis 15-16/maio antes canary 19/maio.

3. **Validar P1 com Wagner antes de codar:** confirmar quais P1 Caçambas usa de verdade (custom_fields venda, tax/price inc tax inline, item_addition_method setting, delivery_person, shipping_custom_fields). **Não codar P1 sem sinal qualificado** (ADR 0105 — cliente sinaliza, não Claude assume).

4. **Adiar todos P2** pra pós-canary. Reward Points, Recurring, Restaurant, Weighing scale, Export internacional, Featured products grid → backlog ADR-feature-wish.

5. **Iteração design supervisionada APÓS paridade P0 fechada** (ADR 0107 Visual Comparison Gate F3): Claude Design plugin gera mockup, Wagner aprova SCREENSHOT (não tabela), só então mexe em pill/KPI/typography. **Sem refactor visual sem paridade — disciplina que faltou nas 6 ondas anteriores**.

---

## 9. Refs

- [Charter Sells/Create](../../../resources/js/Pages/Sells/Create.charter.md)
- [RUNBOOK-create.md](RUNBOOK-create.md) — RUNBOOK migração MWART original (não paridade)
- [SPEC.md Sells](SPEC.md) — US-SELL-001..010
- [ADR 0093 Multi-tenant Tier 0](../../decisions/0093-multi-tenant-isolation-tier-0.md)
- [ADR 0104 MWART canônico](../../decisions/0104-processo-mwart-canonico-unico-caminho.md)
- [ADR 0105 Cliente como sinal qualificado](../../decisions/0105-cliente-como-sinal-guiar-sem-mandar.md)
- [ADR 0106 Recalibração velocidade fator 10x](../../decisions/0106-recalibracao-velocidade-fator-10x-ia-pair.md)
- [ADR 0107 Visual Comparison Gate F3](../../decisions/0107-emendation-0104-visual-comparison-gate-f3.md)
- [ADR 0110 Cockpit Pattern V2](../../decisions/0110-cockpit-pattern-v2-canon-list-detail.md)
- [ADR 0121 Modular especializado por vertical](../../decisions/0121-oimpresso-modular-especializado-por-vertical.md)
- [CASOS-USO-PIPELINE-VENDAS.md](CASOS-USO-PIPELINE-VENDAS.md)
- [CAPTERRA-DESIGN-FICHA.md](CAPTERRA-DESIGN-FICHA.md)
- Demo script Martinho (sessão 2026-05-13 noite) + CHECKLIST pós-reunião 13/maio
- Cliente Martinho perfil: `memory/clientes/martinho-cacambas/` (se existir)

---

## Anexo A — Resumo estatístico

- **Total features Blade catalogadas:** 90
- **Features ✅ paridade ou 🟦 N/A:** 27 (30%)
- **Features ⚠️ parcial:** 15 (17%)
- **Features ❌ ausentes:** 48 (53%)
- **Mas considerando criticidade:**
  - **P0 (10 features):** 5 ✅ + 5 ❌ → **50% paridade P0**
  - **P1 (~35 features):** 18 ✅/⚠️ + 17 ❌ → **51% paridade P1**
  - **P2 (~45 features):** 4 ✅/⚠️ + 41 ❌ → **9% paridade P2** (esperado — P2 são extras)

> **Paridade ponderada por criticidade (P0×4 + P1×2 + P2×1):** ~52%. Distante do "no mínimo o que já existe" pedido por Wagner. Os 5 fixes P0 sobem isso pra ~75%.

---

## Anexo B — Confirmações pendentes Wagner

Pra validar criticidade definitiva, Wagner precisa confirmar:

1. Caçambas usa imposto inline por linha (NFe ICMS)? Se sim → tax columns viram P0.
2. Caçambas faz orçamento de caçamba antes da entrega? Se sim → title dinâmico vira P1+.
3. Caçambas tem motorista dedicado por caminhão? Se sim → `delivery_person` vira P1.
4. Caçambas usa custom_fields venda pra "tipo de obra / nº contrato cliente final"? Se sim → 4 custom_fields viram P1.
5. Caçambas usa `item_addition_method=sum` (somar qtd) ou `=new_row`? Default behavior.
6. Cash denomination é usada em Caçambas (cash escritório Dani)? Se sim P0 confirmado, se não desce pra P1.
7. Larissa biz=4 usa hoje cadastro cliente inline botão (+) — Lara/Dani esperam mesma feature? (sim, presumido).
