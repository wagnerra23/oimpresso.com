---
slug: audit-sells-create-vs-blade-larissa
title: "Auditoria Sells/Create.tsx vs Blade legacy — paridade pra Larissa (Rota Livre biz=4)"
type: session-audit
authority: canonical
lifecycle: ativo
session_date: '2026-05-27'
quarter: 2026-Q2
related:
  - '0093'
  - '0104'
  - '0105'
  - '0107'
  - '0114'
pii: false
---

# Auditoria Sells/Create.tsx vs Blade legacy — paridade pra Larissa

> Escopo restrito: **somente "consultar produto" e "consultar cliente"**, conforme reclamação da Larissa @ Rota Livre (biz=4, 1280px, não-técnica). Auditoria de leitura — nenhum código alterado, nenhum commit.

## Resumo executivo

A V2 já está **desligada por hardcode** pra biz=4 em `SellController@create:976` e `SellPosController@create:279` (HOTFIX 2026-05-13). Comentário inline lista 3 bugs reportados pela Larissa: **(1) "traz o mesmo produto com estoque" (duplicação/variação errada)**, **(2) faltam botões "preço diferenciado / tamanho / conversão unidade medida" do Blade**, **(3) erro visível na tela**. A V2 implementa busca + autocomplete passáveis pra cenário simples (1 SKU, 1 variação, cliente já cadastrado), mas regride 8 capacidades Blade críticas pra vestuário (variações tamanho/cor, lote, código de barras leitor físico, custom fields, configure-search, cliente quick-add inline, atalhos Mousetrap POS, sugestões visuais por categoria/marca/featured).

## Inventário Blade (legacy — `resources/views/sale_pos/create.blade.php` + 41 partials)

### Consultar PRODUTO (pos_form.blade.php:29-53 + pos.js 3185 LOC + sidebar + 3 modals)
- **Autocomplete `#search_product`** (jQuery autoCompleter, `pos.js` ~linha 4xx). Endpoint `/products/list?term=X&location_id=Y&search_fields[]=name|sku|lot|product_custom_field1-4`. Multi-campo configurável.
- **Configure search modal** (`configure_search_modal.blade.php`) — checkbox por campo: nome, SKU, **lote (se `enable_lot_number==1`)**, 4 custom_fields. Persistido no localStorage do user.
- **Botão `quickAdd` produto** (linha 49) — modal `.quick_add_product_modal` chama `ProductController@quickAdd`.
- **Botão weighing scale** (linha 44) — modal de balança quando `enable_weighing_scale==1`.
- **Sugestões visuais** (pos_sidebar.blade.php) — 3 vias paralelas pro vendedor não-leitor de SKU:
  - Drawer **Categorias** hierárquico (`#product_category_div`)
  - Drawer **Marcas** (`#product_brand_div`)
  - **Featured products grid** com imagens (`featured_products.blade.php`, `getFeaturedProducts()` por location)
- **Mobile suggestions** (`mobile_product_suggestions.blade.php` quando `isMobile()`).
- **Variações** (`pos.js` — quando produto tipo `variable` retorna múltiplas linhas, abre modal de seleção variação; campos `variation_id`, `sub_sku`).
- **Lote / expiry** (`enable_lot_number==1` ou `enable_product_expiry==1`) — adiciona coluna `lot_no_line_id` + filtro no add (SellPosController:977, 1699).
- **Quantity manipulation** — input numérico com unidade (`unit_id`), suporta decimal, conversion (`unit_conversion`). Atalho **`recent_product_quantity`** (default `Shift+Up`, keyboard_shortcuts.blade.php:78-115) foca última linha pra editar qtd, e timeout 5s devolve foco ao search.
- **Scanner código de barras** — input `#search_product` **sem** classe `mousetrap` (comentário linha 35: "Removed mousetrap class as it was causing issue with barcode scanning"). Permite hardware scanner injetar SKU + Enter.
- **Atalho add new product** (`shortcuts["pos"]["add_new_product"]`, default `Ctrl+Shift+A`) — foca search.

### Consultar CLIENTE (pos_form.blade.php:2-27)
- **Select2 `#customer_id`** com AJAX (`select2.placeholder='Digite nome do cliente / telefone'`). Endpoint `/contacts/customers?q=TERM`.
- **Hidden inputs** com defaults do walk-in: `default_customer_id`, `default_customer_name`, `default_customer_balance`, `default_customer_address`, `default_selling_price_group` (se cliente tem grupo de preço).
- **Botão `+ add_new_customer`** (linha 23) inline ao lado do select — abre modal `.contact_modal` (`contact.create` com `quick_add=true`). Permission `customer.create`.
- **`contact_due_text`** — linha 26 — pinta vermelho "Saldo devedor: R$ X" sob o campo quando cliente tem balance > 0.
- **Auto-aplicação ao trocar cliente** (`pos.js`):
  - `pay_term_number` + `pay_term_type` herdados do cliente
  - `selling_price_group_id` aplicado automaticamente (recalcula preços da venda inteira)
  - `shipping_address` pré-preenchido
  - `customer_due` atualizado e exibido inline

### Outros (não no escopo da Larissa mas relevantes)
- **Atalhos POS Mousetrap** (`keyboard_shortcuts.blade.php`): express_checkout, cancel, draft, pay_n_checkout, edit_discount, edit_order_tax, add_payment_row, finalize_payment, recent_product_quantity, add_new_product, weighing_scale. Todos configuráveis em settings.
- **Botões pos_form_actions**: Express checkout (verde), Multi-pay, Draft, Quotation, Suspend, Credit sale, Cancel.

## Inventário Inertia V2 (`resources/js/Pages/Sells/Create.tsx` 1409 LOC + 30+ subcomponentes)

### Consultar PRODUTO (`_components/ProductSearchAutocomplete.tsx` 211 LOC)
- ✅ Autocomplete debounce 250ms, MIN 2 chars, top 10 resultados.
- ✅ Reusa `/products/list?term=X&location_id=Y` mesmo endpoint do Blade.
- ✅ Esc fecha, click fora fecha, mostra preço + estoque + SKU.
- ✅ Atalho `/` foca o search (Create.tsx:484-504).
- ❌ **SEM `search_fields[]`** — Blade envia nome/SKU/lote/custom fields configurados; V2 envia só `term` (backend faz fallback default — talvez só nome+SKU).
- ❌ **SEM modal configure-search** — Larissa não consegue ligar busca por código de barras custom_field3 (referência interna Rota Livre).
- ❌ **SEM tratamento de variação** — quando endpoint retorna múltiplas rows do mesmo `product_id` (cada variação separada), `handleAddProduct` (Create.tsx:270-283) adiciona linha mas chave única é `${product_id}-${variation_id}-${idx}` (linha 870). Larissa vê "MESMO produto duplicado" — sintoma exato do bug 1 do hotfix. Sem modal pra escolher variação correta.
- ❌ **SEM scanner barcode confiável** — input é `type="search"`, debounce 250ms quebra fluxo do scanner USB (que envia SKU\n em <50ms; o debounce dispara busca por sub-string e o autocomplete pode abrir antes do `\n` chegar).
- ❌ **SEM lote / expiry** — sem coluna lot, sem filtro por validade.
- ❌ **SEM weighing scale** — sem botão balança.
- ❌ **SEM quick-add produto** — Blade tem `pos_add_quick_product`, V2 não.
- ❌ **SEM sugestões visuais** — sem featured_products grid, sem drawer categorias, sem drawer marcas. Larissa (vestuário) seleciona por aparência, não por SKU.
- 🟡 **Linha do produto sem unidade/conversão** — tabela em Create.tsx:863-953 tem só Qtd/Preço/Desconto/Subtotal. Blade tem coluna **unidade** com dropdown de conversão (peça → caixa, kg → g). Larissa vende "1 calça" mas pode ter unidade de venda "peça" diferente da unidade de compra "fardo".
- 🟡 **Quantity input simples** sem atalho "voltar pra última qtd" do Blade.

### Consultar CLIENTE (`_components/CustomerSearchAutocomplete.tsx` 245 LOC)
- ✅ Autocomplete debounce 250ms, ArrowUp/Down + Enter, click fora fecha.
- ✅ Reusa `/contacts/customers?q=TERM` mesmo endpoint.
- ✅ Mostra mobile + city no item.
- ✅ Link "Cadastrar X como novo cliente" no empty state (abre `/contacts/create-page?prefill_name=X` em nova aba).
- ✅ postMessage da aba de cadastro retorna `contact_created` event e seleciona automático.
- ❌ **SEM saldo devedor visível** — backend retorna `balance` (ContactController:1892) mas a tipagem em V2 é `{ id, text, mobile, city }` e o componente não exibe `balance`.
- ❌ **SEM auto-aplicar `selling_price_group`** — cliente que tem grupo de preço vinculado deveria recalcular preços de produtos já no carrinho. V2 só seta `contact_id`. Reflete bug 2 do hotfix: "preço diferenciado".
- ❌ **SEM auto-aplicar `pay_term_number/type`** — cliente com prazo de 30 dias deveria pré-preencher; V2 mantém em `''`.
- ❌ **SEM auto-aplicar `shipping_address`** — quando shipping section abre, endereço do cliente não vem.
- ❌ **Quick-add INLINE perdido** — Blade tem botão `+` ao lado do select que abre **modal** sem sair da tela; V2 abre **nova aba** + postMessage. Larissa não-técnica pode perder a venda (aba de cadastro mais visível que a aba origem) e o postMessage é frágil (popup blocker, COOP/COEP).
- 🟡 **Walk-in customer default** funciona (V2 seta `contact_id=walk_in.id`) mas labels mostra "Cliente padrão" sem indicar o nome real, e `onClear` volta pro walk-in mas não há feedback visual de que voltou.

## Tabela de paridade

| # | Dimensão | Blade legacy | V2 Inertia (Create.tsx) | Bucket |
|---|---|---|---|---|
| 1 | Autocomplete produto (nome/SKU) | ✓ select2 AJAX | ✓ debounce 250ms | ✅ APROVADO |
| 2 | Configure search modal (nome/SKU/lote/4 custom_fields) | ✓ modal `#configure_search_modal` | ❌ ausente | ❌ AUSENTE |
| 3 | Busca por lote/expiry | ✓ se `enable_lot_number==1` | ❌ ausente | ❌ AUSENTE |
| 4 | Variação (produto tipo `variable`) | ✓ modal/inline seleção variação | 🟡 endpoint retorna mas UI duplica linha (BUG 1 hotfix 2026-05-13) | 🟡 PARCIAL |
| 5 | Scanner código barras (hardware USB) | ✓ input sem mousetrap, listener Enter | 🟡 debounce 250ms pode interferir | 🟡 PARCIAL |
| 6 | Quick-add produto inline | ✓ modal `.quick_add_product_modal` | ❌ ausente | ❌ AUSENTE |
| 7 | Weighing scale modal | ✓ `weighing_scale_modal.blade.php` | ❌ ausente | ❌ AUSENTE |
| 8 | Sugestões visuais (categorias/marcas/featured) | ✓ pos_sidebar drawer + featured grid | ❌ ausente totalmente | ❌ AUSENTE |
| 9 | Unidade + conversão na linha do produto | ✓ coluna unidade c/ dropdown | ❌ ausente (BUG 2 hotfix) | ❌ AUSENTE |
| 10 | Atalho `recent_product_quantity` (focar última qtd) | ✓ Mousetrap (default Shift+Up) | ❌ ausente | ❌ AUSENTE |
| 11 | Atalho `add_new_product` (focar search) | ✓ Mousetrap customizável | 🟡 hardcoded `/` (Create.tsx:484) | 🟡 PARCIAL |
| 12 | Autocomplete cliente (nome/CPF/CNPJ/telefone) | ✓ select2 AJAX | ✓ + city no item | ✅ APROVADO |
| 13 | Quick-add cliente inline | ✓ modal `.contact_modal` mesmo screen | 🟡 nova aba + postMessage frágil | 🟡 PARCIAL |
| 14 | Saldo devedor (`customer_due`) visível ao trocar cliente | ✓ `small.contact_due_text` vermelho | ❌ ausente (backend devolve mas UI não usa) | ❌ AUSENTE |
| 15 | Auto-aplicar `selling_price_group` do cliente | ✓ recalcula carrinho | ❌ ausente (BUG 2 hotfix) | ❌ AUSENTE |
| 16 | Auto-aplicar `pay_term_number/type` do cliente | ✓ pre-fill | ❌ ausente | ❌ AUSENTE |
| 17 | Auto-aplicar `shipping_address` do cliente | ✓ pre-fill | ❌ ausente | ❌ AUSENTE |
| 18 | Walk-in customer default visível com nome | ✓ select2 mostra | 🟡 mostra mas sem destaque de "está no padrão" | 🟡 PARCIAL |
| 19 | Atalhos POS Mousetrap (express/cancel/draft/discount/...) | ✓ 11 atalhos configuráveis | 🟡 só Ctrl+Enter (submit) + Esc + `/` | 🟡 PARCIAL |
| 20 | Permission `customer.create` desabilita quick-add | ✓ `disabled` no botão | ❌ link sempre clicável (sem checar permission) | 🟡 PARCIAL |

**Score:** 2 APROVADO · 7 PARCIAL · 11 AUSENTE pra paridade core de "consultar produto" + "consultar cliente".

## Top 5 dores prioritárias pra Larissa

### Dor 1 — Variação duplicada como produto distinto (BUG 1 do hotfix)
- **Onde:** `resources/js/Pages/Sells/_components/ProductSearchAutocomplete.tsx:174-200` (loop render) + `resources/js/Pages/Sells/Create.tsx:270-283` (handleAddProduct).
- **Causa:** Endpoint `/products/list` retorna **N rows** pra produto tipo `variable` (1 por variação). V2 renderiza N items no dropdown com mesmo `name` e SKU diferente, sem agrupamento. Quando Larissa clica num, vai a linha; se clica em outro do mesmo "produto base", vai outra linha — parece duplicação. Blade resolve com modal de seleção de variação.
- **Esforço:** **M** — agrupar por `product_id`, mostrar 1 row no dropdown, ao clicar abrir popover/Select com variações. Reusa Radix Select; nada de modal novo. Inverter sintoma chave do reject.
- **Snippet Blade que funcionava** (`public/js/pos.js` busca pela seleção variação — referência conceitual; o código tem ~3185 linhas, o relevante é o `addProductRow` invocado no select):
```php
{{-- pos_form.blade.php:36-39 — input sem mousetrap pra scanner também --}}
{!! Form::text('search_product', null, ['class' => 'form-control', 'id' => 'search_product',
    'placeholder' => __('lang_v1.search_product_placeholder'),
    'disabled' => is_null($default_location)? true : false,
    'autofocus' => is_null($default_location)? false : true,
]); !!}
```
A lógica de variação está no JS (`pos.js` chama `posProduct.addProductRow(productId, variationId, locationId)` — quando recebe N variações, abre Popover de seleção; em V2 hoje cada variação vira candidato isolado no autocomplete).

### Dor 2 — Cliente trocado mas preço/prazo NÃO recalcula (BUG 2 do hotfix — "preço diferenciado")
- **Onde:** `resources/js/Pages/Sells/_components/CustomerSearchAutocomplete.tsx:149-156` (handleSelect) + `resources/js/Pages/Sells/Create.tsx:766` (onSelect callback).
- **Causa:** V2 chama `setData('contact_id', c.id)` e nada mais. Não aplica `selling_price_group_id`, não recalcula `unit_price` das linhas já no carrinho, não pre-fill `pay_term_number/type`, não pre-fill `shipping_address`, não exibe `balance`. Endpoint já devolve tudo (ContactController:1879-1904) — UI descarta.
- **Esforço:** **M** — expandir tipo `CustomerSearchResult` pra incluir `balance/selling_price_group_id/pay_term_number/pay_term_type/shipping_address`; após `onSelect`, no parent: `setData('price_group_id', c.selling_price_group_id)` + recalcular `data.products[i].unit_price` via reqfetch ao endpoint `/products/list` filtrado por `price_group`. Loop sobre `data.products`.
- **Snippet Blade conceitual** (`pos_form.blade.php:16-19`):
```html
@if(!empty($walk_in_customer['price_calculation_type']) && $walk_in_customer['price_calculation_type'] == 'selling_price_group')
  <input type="hidden" id="default_selling_price_group"
    value="{{ $walk_in_customer['selling_price_group_id'] ?? ''}}" >
@endif
```
E em `pos.js` `customer_id.on('change')` faz POST recalc; UI mostra `contact_due_text` (linha 26: `<small class="text-danger hide contact_due_text"><strong>@lang('account.customer_due'):</strong> <span></span></small>`).

### Dor 3 — Sem busca por código de barras / lote / custom fields (regressão configure-search)
- **Onde:** `resources/js/Pages/Sells/_components/ProductSearchAutocomplete.tsx:66-67` (URL builder não envia `search_fields[]`).
- **Causa:** Blade tem modal que permite Larissa marcar checkbox "buscar por SKU/lote/código fornecedor" e o front envia `search_fields[]=name&search_fields[]=sku&search_fields[]=product_custom_field3`. V2 manda só `term=X` → backend filtra só por default. Resultado: scanner físico ou referência interna "PC-1234" do fornecedor da Rota Livre não acha nada.
- **Esforço:** **S** — adicionar dropdown ou popover de configuração persistido em localStorage com mesmo set de fields; passar como `search_fields[]` array no URLSearchParams. Backend já aceita (não muda nada server).
- **Snippet Blade** (`configure_search_modal.blade.php:14-71`):
```html
<div class="checkbox">
  <label>{!! Form::checkbox('search_fields[]', 'name', true, ['class' => 'search_fields']); !!} @lang('product.product_name')</label>
</div>
<div class="checkbox">
  <label>{!! Form::checkbox('search_fields[]', 'sku', true, ['class' => 'search_fields']); !!} @lang('product.sku')</label>
</div>
@if(request()->session()->get('business.enable_lot_number') == 1)
<div class="checkbox">
  <label>{!! Form::checkbox('search_fields[]', 'lot', true, ['class' => 'search_fields']); !!} @lang('lang_v1.lot_number')</label>
</div>
@endif
```

### Dor 4 — Quick-add cliente inline perdido (abre nova aba que pode bloquear)
- **Onde:** `resources/js/Pages/Sells/_components/CustomerSearchAutocomplete.tsx:231-240`.
- **Causa:** Blade abre **modal in-place** (`.contact_modal` no Create:76-78 carrega `contact.create` com `quick_add=true`). V2 usa `<a target="_blank">` + `window.postMessage` — Larissa pode perder a aba origem, popup blocker pode quebrar, COOP/COEP pode bloquear postMessage entre origens com headers Inertia diferentes. Também ignora permission `customer.create`.
- **Esforço:** **M** — usar componente shadcn Dialog/Drawer com formulário inline (mesmos campos do quick_add Blade: name, mobile, email, type=customer). POST `/contacts` retorna JSON, fecha modal, seleciona automático. Reusa Drawer já existente em `_components` (ex CobrancaDrawer).
- **Snippet Blade** (`create.blade.php:76-78`):
```html
<div class="modal fade contact_modal" tabindex="-1" role="dialog">
  @include('contact.create', ['quick_add' => true])
</div>
```
e em `pos_form.blade.php:23`:
```html
<button type="button" class="add_new_customer" @if(!auth()->user()->can('customer.create')) disabled @endif>
  <i class="fa fa-plus-circle"></i>
</button>
```

### Dor 5 — Saldo devedor do cliente invisível
- **Onde:** `resources/js/Pages/Sells/_components/CustomerSearchAutocomplete.tsx:13-18` (tipo CustomerSearchResult) + `Create.tsx:761-773` (renderiza só campo, sem feedback).
- **Causa:** Backend devolve `balance` (ContactController:1892) mas tipo V2 omite. Larissa precisa saber **antes de fechar a venda** se o cliente está devendo — se sim, talvez exigir pagamento à vista. Blade pinta vermelho automaticamente.
- **Esforço:** **S** — adicionar `balance?: number` na tipagem; ao `onSelect` se `balance > 0`, mostrar `<p class="text-destructive">` abaixo do campo igual ao Blade.
- **Snippet Blade** (`pos_form.blade.php:26`):
```html
<small class="text-danger hide contact_due_text">
  <strong>@lang('account.customer_due'):</strong> <span></span>
</small>
```
JS preenche o span + remove `.hide` quando balance > 0.

## Recomendação de ataque

**Atacar Dor 1 primeiro** (variação duplicada). Razões:
1. É **bug "do mesmo produto com estoque"** explicitamente citado no hotfix 2026-05-13 — é literalmente o gatilho do rollback biz=4.
2. Sintoma mais visível (Larissa vê linhas duplicadas e abandona a tela).
3. Esforço M, código localizado em 2 arquivos.
4. Sem fix dela, ligar V2 pra biz=4 de novo é certeza de rollback.

Sequência sugerida (cada PR ≤300 linhas, 1 PR = 1 intent):

| PR # | Intent | Lê | Modifica | Esforço |
|---|---|---|---|---|
| 1 | fix(sells/create): agrupar variações no autocomplete + Popover de seleção | ProductSearchAutocomplete + Create.tsx handleAddProduct | 1 component + 1 callback | M (~200 LOC) |
| 2 | feat(sells/create): aplicar selling_price_group/pay_term/shipping ao trocar cliente | CustomerSearchAutocomplete + Create.tsx onSelect | 1 component + tipos + callback | M (~150 LOC) |
| 3 | feat(sells/create): saldo devedor inline ao trocar cliente | CustomerSearchAutocomplete tipo + UI | 1 component + Create.tsx tipo | S (~50 LOC) |
| 4 | feat(sells/create): configure-search modal com search_fields[] persist | ProductSearchAutocomplete + 1 Popover novo | 1 component + 1 popover + URLSearchParams | S (~120 LOC) |
| 5 | feat(sells/create): quick-add cliente inline via Drawer | CustomerSearchAutocomplete + novo Drawer | 1 novo Drawer reuso shadcn Dialog | M (~200 LOC) |

Após os 5 PRs: rodar smoke MCP browser em biz=4 com Larissa, validar 3 bugs do hotfix resolvidos, então **remover o guard `$business_id !== 4`** em `SellController:976` + `SellPosController:279` num **PR separado** (1 PR = remover regra, com canary 7d skill `runtime-rules-hostinger-ct100` + monitor 30d ADR 0106).

Dores 6-10 (sugestões visuais, weighing scale, lote/expiry, atalhos POS adicionais, unidade/conversão) ficam como backlog Capterra-style — apenas após sinal qualificado de outro cliente pagante (ADR 0105 cliente-como-sinal). Larissa específico não usa peso, não usa lote (vestuário), e atalhos avançados são P3 pra não-técnico.

## Anti-padrões F3 catalogados aplicáveis

Cruzando com `prototipo-ui/LICOES_F3_FINANCEIRO_REJEITADO.md`:

- **M-AP-1 (Auto-aprendizado ignorado sob pressão de delivery):** US-SELL-005 (ProductSearchAutocomplete) foi entregue **sem ler o equivalente Blade `pos.js`**, perdeu a lógica de variação. Mesmo padrão Financeiro 2026-05-09 — agente entregou componente sem inspecionar o legacy de cobertura.
- **M-AP-2 (Marketing otimista vs realidade WIP):** Comentário US-SELL-005 chama "bloco produtos real (busca + tabela + cálculos)". Reality: busca não suporta variação/lote/configure-search. Title honesto seria `feat(sells): produto-search MVP (sem variação/lote/configure-search)`.
- **AP-12 (Endpoint reutilizado sem ler payload completo):** ContactController:1879-1904 devolve `balance/selling_price_group_id/pay_term_number/pay_term_type/shipping_address/...` — V2 só lê `id/text/mobile/city`. Mesmo erro do Financeiro: assumir shape do payload sem `Read` do server.

## Próximos passos

1. Wagner aprova ataque PR 1 (Dor 1 — variação) — não criar via this audit, pendente Wagner OK.
2. Skill `wagner-request-refiner` se Wagner quiser ampliar escopo (ex: adicionar weighing scale agora).
3. Após 5 PRs verdes (Pest + browser MCP smoke em biz=4 dev): PR remover guard hardcoded.
4. Cycle goal "Sells V2 paridade Blade biz=4" — registrar via `tasks-create` (ADR 0070).

---

**Arquivo:** `D:/oimpresso.com/memory/sessions/2026-05-27-audit-sells-create-vs-blade-larissa.md`
**Status:** audit-only, nenhum código alterado, nenhum PR criado.
