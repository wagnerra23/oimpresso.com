---
id: requisitos-compras-telas-runbook-purchase-show
title: "RUNBOOK — /purchases/{id} (Compras · detalhe Inertia)"
module: Purchase
tela: Purchase/Show
owner: F
status: ativo
last_validated: "2026-09-04"
related_adrs:
  - 0104-processo-mwart-canonico-unico-caminho
  - 0093-multi-tenant-isolation-tier-0
  - 0141-skill-migracao-blade-react
spec_ref: memory/requisitos/Compras/SPEC.md
---

# RUNBOOK — `/purchases/{id}` (detalhe de Compra, Inertia/React)

> Tela `resources/js/Pages/Purchase/Show.tsx` servida em `GET /purchases/{id}`.
> Detalhe **read-only**: confere fornecedor, itens, pagamentos e totais antes de editar,
> imprimir ou lançar pagamento.
> Irmãos: [Index](RUNBOOK-purchase-index.md) · [Create](RUNBOOK-purchase-create.md) · [Edit](RUNBOOK-purchase-edit.md).
> NÃO confundir com o módulo greenfield `/compras` (`Pages/Compras/`) — é outro alvo.

**Escopo deste documento:** descreve o que a tela **faz** (medido no código em `origin/main`,
2026-09-04). Não decide o que ela **deve** fazer — Non-Goals e Anti-hooks são preenchidos
por [W] no [`Show.charter.md`](../../../../resources/js/Pages/Purchase/Show.charter.md), hoje
`status: draft`. Onde o charter marca *"inferência pendente de Wagner"*, este RUNBOOK registra
o comportamento observado e **não** o promove a intenção.

## 1. Contexto

- **Rota:** `GET /purchases/{id}` — registrada em `routes/web.php` **fora** do `Route::resource` (ver §1.1)
- **Controller:** `PurchaseController@show($id)` → delega `showInertia()` (privado)
- **Blade legacy:** `resources/views/purchase/show.blade.php` (modal, 17 ln) que inclui
  [`purchase/partials/show_details.blade.php`](../../../../resources/views/purchase/partials/show_details.blade.php) (430+ ln)
- **Impressão:** `GET /purchases/print/{id}` → `printInvoice` (Blade, `routes/web.php`, vizinha da rota do detalhe)
- **Contrato executável:** [`Show.casos.md`](../../../../resources/js/Pages/Purchase/Show.casos.md) (UC-PURSHW-01..06)

### 1.1 A rota do `show` NÃO está no `Route::resource`

Medido em `routes/web.php` (2026-09-04): o `Route::resource('purchases', …)->except(['show'])`
vive num grupo; o `GET /purchases/{id}` vive num grupo **separado**, com middleware **menor**:

| Rota | Middleware do grupo |
|---|---|
| `resource purchases` (index/create/edit/update/destroy) | `setData, auth, SetSessionData, language, timezone, AdminSidebarMenu, CheckUserLogin` |
| `GET /purchases/{id}` (**show**) | `setData, auth, SetSessionData, language, timezone` |

> Número de linha apodrece — `routes/web.php` passa de 1.200 linhas e muda toda semana.
> Para re-localizar os dois grupos hoje, o oráculo é o `route:list`
> (`php artisan route:list --path=purchases --columns=method,uri,middleware`, no CT 100);
> a leitura estática re-acha os dois pontos com
> `git grep -n "Route::resource('purchases'\|/purchases/{id}" -- routes/web.php` e, a partir
> de cada hit, o `Route::middleware([...])->group(` imediatamente acima.

**Isso é sistêmico, não um acidente do Purchase:** o grupo do `show` reúne rotas de
detalhe/impressão/download (`/purchases/print/{id}`, `/sells/{id}`, os `download-*/pdf`).
Quem "consertar" só o Purchase desalinha a tela das irmãs.

Duas consequências, medidas:

- **`AdminSidebarMenu` ausente NÃO quebra a sidebar do React.** O menu do `AppShellV2` vem de
  `HandleInertiaRequests::share()` → `ShellMenuBuilder` (`shell.menu`), não daquele middleware.
  Conferido antes de afirmar — a assimetria assusta e é inócua neste eixo.
- **`CheckUserLogin` ausente muda a superfície de autorização.** Esse middleware aborta 403 quando
  `user_type != 'user'` **ou** `allow_login != 1`; ele guarda a listagem e **não** guarda o detalhe.
  ⚠️ **Isto é observação medida, não achado:** não varri quem pode deter `purchase.view` com
  `user_type` diferente, e não escrevi teste vermelho. Fica registrado para [W] decidir se a
  divergência é deliberada (grupo de "detalhe/impressão") ou dívida — não conserte no escuro.

## 2. Persona

Maiara/Felipe conferindo uma compra antes de editar ou lançar pagamento. Larissa
(ROTA LIVRE, biz=4, monitor **1280px**) abre o detalhe para conferir custo e saldo a pagar.

## 3. Dual-path (a pegadinha central)

`PurchaseController@show` bifurca **uma vez**, e a condição é a negativa do header:

| Condição do request | Render |
|---|---|
| `request()->ajax() && ! request()->header('X-Inertia')` | `view('purchase.show')` — **modal AJAX Blade legacy** |
| qualquer outro caso (navegação normal, visita Inertia) | `showInertia()` → `Inertia::render('Purchase/Show')` |

O que o cliente enxerga pelo Cockpit é **sempre** o path React: uma visita Inertia manda
`X-Inertia: true`, então a primeira perna nunca casa. O Blade só aparece para telas antigas que
ainda abrem o detalhe como modal AJAX.

> ⚠️ **O path Blade está quebrado em prod** (§4) — está preservado por retro-compat, não por
> funcionar; o comentário do próprio controller diz isso. Portanto **não** é um fallback, e a
> paridade Blade × React aqui **não** é meta: a substituição é.

**Ação nova nesta tela abre rota Blade via `window.open`, nunca `router.visit`** — mesma regra do
[Index §5](RUNBOOK-purchase-index.md). O botão Imprimir já segue
(`window.open('/purchases/print/{id}', '_blank')`); `router.visit` numa rota Blade produz
*"All Inertia requests must receive a valid Inertia response"*.

## 4. Quando esta tela quebra (sintomas)

| Sintoma | Causa provável |
|---|---|
| **500 no modal AJAX legado** | `show_details.blade.php:430` chama `DNS1D::getBarcodePNG($purchase->ref_no, …)`. É o bug que a migração matou **por omissão**. |
| `403` ao abrir o detalhe | Usuário sem `purchase.view` **e** sem `view_own_purchase` — o `abort(403)` é a primeira linha de `show()`. |
| `404` ao abrir o detalhe | `{id}` inexistente **ou** de outro tenant — `firstOrFail()` sobre query já escopada (§6). |
| *"All Inertia requests must receive a valid Inertia response"* | alguma ação chamou `router.visit` para rota Blade (`/purchases/print/{id}`). Use `window.open`. |
| Card **Empresa** vazio | `$purchase->business` é lido no `showInertia` mas **não** está no `with()` do `show()` — resolve por lazy load; se a relação falhar, o card vem vazio, não estoura. |
| Link do documento anexo não aparece | `document_path`/`document_name` são **accessors** do model `Transaction` (`asset('/uploads/documents/'.$this->document)`), não colunas. Sem `document`, o accessor devolve `null` e o link não é renderizado. |

### 4.1 O barcode é um bug-fix POR OMISSÃO — e por isso é frágil

O detalhe React **não** renderiza código de barras. Nada no `Show.tsx` explica a ausência: o
arquivo simplesmente não tem o trecho. Quem for buscar paridade com o Blade reintroduz o
barcode de boa-fé e **ressuscita o 500**.

- Guarda hoje: `ShowPageTest` — *"Page NÃO renderiza barcode (bug-fix por omissão — linha 430 Blade DNS1D)"*.
- Etiqueta/código de barras é **fluxo dedicado** (`/labels/show?purchase_id=…`, [Index §2](RUNBOOK-purchase-index.md)), não responsabilidade desta tela.
- Contrato: UC-PURSHW-03 em [`Show.casos.md`](../../../../resources/js/Pages/Purchase/Show.casos.md).

## 5. Props (Controller → Page)

`Inertia::render('Purchase/Show', …)` envia **duas** chaves. Tudo já vem calculado — a Page
formata, não recalcula (UC-PURSHW-05).

| Prop | Tipo | Origem |
|---|---|---|
| `purchase.id` · `ref_no` · `transaction_date` · `type` · `status` · `payment_status` · `additional_notes` | escalares | colunas de `Transaction` |
| `purchase.supplier_*` (`name`, `business_name`, `address`, `tax_number`, `mobile`, `email`) | escalares | relação `contact` |
| `purchase.business_*` (`name`, `tax_label_1/2`, `tax_number_1/2`) | escalares | relação `business` — ⚠️ **não** eager-loaded (lazy) |
| `purchase.location_*` (`name`, `landmark`, `city_state`, `mobile`, `email`) | escalares | relação `location`; `city_state` = `implode` de city/state/country não-vazios |
| `purchase.document_path` · `document_name` | string ou null | **accessors** de `Transaction`, não colunas |
| `purchase.purchase_lines[]` | objeto[] | `purchase_lines` mapeado — `product_name`, `sku`, `variation_name`, `quantity`, `unit_name`, `pp_without_discount`, `discount_percent`, `purchase_price`, `item_tax`, `tax_name`, `purchase_price_inc_tax`, `subtotal`, `lot_number`, `mfg_date`, `exp_date` |
| `purchase.payment_lines[]` | objeto[] | `payment_lines` mapeado — `paid_on`, `payment_ref_no`, `amount`, `method_label`, `note` |
| `purchase.net_total` | float | soma de `quantity × purchase_price` das linhas |
| `purchase.discount_type` · `discount_amount` · `discount_value` | string/float | `discount_value` resolve `percentage` como `discount_amount × net_total / 100` |
| `purchase.tax_breakdown[]` | `{name, amount}[]` | `sumGroupTaxDetails` quando `is_tax_group`; senão o nome do tributo com `tax_amount` |
| `purchase.shipping_charges` · `final_total` | float | colunas |
| `purchase.amount_paid` · `payment_due` | float | soma de `payment_lines.amount`; e `final_total` menos `amount_paid` |
| `permissions.update` · `delete` · `payments` | bool | `purchase.update` · `purchase.delete` · `purchase.payments` ou `edit_purchase_payment` ou `delete_purchase_payment` |

### 5.1 O que o `show()` calcula e **não** envia ao Inertia

`show()` monta quatro valores que só o Blade consome — o path React os ignora:
`$activities` (audit via `Activity::forSubject`), `$statuses`, `$purchase_order_nos` e
`$purchase_order_dates`. É trabalho pago em toda visita React.

Corresponde ao MVP declarado no
[`show-visual-comparison.md`](show-visual-comparison.md) §Limitações: activity log, custom
fields, shipping detail e additional expenses ainda não migrados. **O dado já está na mão** —
quem for migrar o activity log não precisa de query nova, só passar a variável adiante.

## 6. Tier 0 — invariantes (ADR 0093 IRREVOGÁVEL)

- ❌ **`Transaction` não tem global scope de `business_id`.** O isolamento é escrito **à mão** em
  cada query. Em `show()` ele está em
  `Transaction::where('business_id', $business_id)->where('id', $id)->…->firstOrFail()`.
  Remover esse `where` é vazamento cross-tenant, e não há scope global para te salvar.
- ✅ **Ordem dos gates — e é ela que dissolve o "404 vs 403" do charter:**
  1. **403** — sem `purchase.view` **e** sem `view_own_purchase` (primeira linha do método);
  2. **404** — com permissão, mas `{id}` de outro tenant (o `firstOrFail()` não acha).

  As três fontes concordam com o **404**: o código (`firstOrFail` sobre query escopada), o
  critério de aceite do [`show-visual-comparison.md`](show-visual-comparison.md)
  (*"acessar `/purchases/{id}` de outro tenant → 404"*) e a convenção registrada no
  [SPEC do módulo irmão](../SPEC.md) (*"show retorna 404 (não 403) cross-tenant"*).
  ⚠️ O charter lista *"confirmar 404 vs 403"* como pendência de [W] — isso é sobre a **intenção**;
  o comportamento **de hoje** é o descrito acima.
- ❌ **Nunca comentar a checagem de permissão.** Ela **já esteve comentada** neste método — por
  isso `ShowPageTest` asserta *"permission re-adicionada (não comentada)"*. Um `//` numa linha de
  autorização é a mudança mais barata de escrever e a mais cara de descobrir.
- ❌ **Nenhum `business_id` hardcoded na Page** — o recorte chega pronto nas props (UC-PURSHW-06).
- ❌ **A Page não recalcula valor.** Totais, descontos e impostos vêm prontos; a tela só formata
  em pt-BR. Mexer em cálculo aqui cai na regra mestre de **VALOR/ESTOQUE**
  ([proibicoes](../../../proibicoes.md)): dupla prova + antes→depois + aprovação [W].

## 7. Smoke (R1 — evidência, não narração)

> ⚠️ **Não executado nesta sessão** (sem acesso a prod daqui). O bloco abaixo é a **receita**;
> um recibo é a saída colada, com o status literal de cada hop.

```bash
curl -sv https://oimpresso.com/purchases/24796 2>&1 | grep '^< HTTP'
curl -sv https://oimpresso.com/purchases/print/24796 2>&1 | grep '^< HTTP'
curl -sv https://oimpresso.com/purchases 2>&1 | grep '^< HTTP'
curl -sv https://oimpresso.com/sells/1 2>&1 | grep '^< HTTP'
```

Esperado: `200` autenticado (ou `302` para login sem sessão). As duas últimas são **regressão
adjacente** — não devem mudar quando esta tela mudar.

Chrome MCP obrigatório pós-merge de UI (hook `post-merge-ui-smoke-required`): navegar
`/purchases/{id}` a **1280px** (monitor Larissa) e conferir — 3 cards de contexto, tabela de
itens, bloco de pagamentos, bloco de totais com **A pagar**, e que **não** há barcode.

## 8. Estado da prova (o que os testes NÃO provam)

[`ShowPageTest`](../../../../tests/Feature/Purchase/ShowPageTest.php) é **estrutural**: casa texto
em `Show.tsx` e em `PurchaseController.php` e emite **zero requests HTTP**. Ele pega a *remoção*
de um trecho; não monta tenant nem valida resposta — classe LC-11 (presence-gate).

Consequência: **nenhum UC desta tela tem prova de comportamento.** Não existe teste que crie a
compra no tenant vizinho e prove o 404 desta rota. A tabela de dívida por UC é mantida em
[`Show.casos.md`](../../../../resources/js/Pages/Purchase/Show.casos.md) §Dívida de prova — ele é
o dono desse número; não o repita aqui.

## 9. Refs

- Controller: [`app/Http/Controllers/PurchaseController.php`](../../../../app/Http/Controllers/PurchaseController.php) — `show()` + `showInertia()`
- Tela: [`resources/js/Pages/Purchase/Show.tsx`](../../../../resources/js/Pages/Purchase/Show.tsx) · charter: [`Show.charter.md`](../../../../resources/js/Pages/Purchase/Show.charter.md) (`draft`) · casos: [`Show.casos.md`](../../../../resources/js/Pages/Purchase/Show.casos.md)
- Teste: [`tests/Feature/Purchase/ShowPageTest.php`](../../../../tests/Feature/Purchase/ShowPageTest.php)
- Comparativo visual: [`show-visual-comparison.md`](show-visual-comparison.md)
- SPEC: [`memory/requisitos/Compras/SPEC.md`](../SPEC.md)
- [ADR 0104 MWART](../../../decisions/0104-processo-mwart-canonico-unico-caminho.md) · [ADR 0093 Tier 0](../../../decisions/0093-multi-tenant-isolation-tier-0.md) · [ADR 0141 skill migracao-blade-react](../../../decisions/0141-skill-migracao-blade-react.md)
