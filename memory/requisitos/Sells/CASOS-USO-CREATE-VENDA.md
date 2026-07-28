---
id: requisitos-sells-casos-uso-create-venda
doc: casos-uso
page: /sells/create
charter: resources/js/Pages/Sells/Create.charter.md
module: Sells
flag: useV2SellsCreate
status: ativo
updated: "2026-06-02"
---

# Casos de uso — `/sells/create` (Adicionar venda · V2 React)

> **Espelho executável do [`Create.charter.md`](../../../resources/js/Pages/Sells/Create.charter.md).** Cada CU = Given/When/Then → 1+ teste Pest (spec executável, padrão do `CASOS-USO-PIPELINE-VENDAS.md`).
>
> **Regra de cutover (decisão A — religar a flag `useV2SellsCreate`):** só religar a V2 (global ou por-biz) quando **todo CU `must` estiver 🟢** (Pest verde) **+ smoke biz=4** (ROTA LIVRE, PRE-MERGE-UI Camada 4). `should`/`could` não bloqueiam; ⚪ Non-Goal documenta o que a V2 **não faz de propósito** (não é regressão).
>
> **Origem:** comparativo Blade ↔ V2 (incidente 2026-06-02, `memory/sessions/2026-06-02-incidente-revert-pr2-sells-endereco.md`). A Blade legada (`sale_pos/create.blade.php`) é o baseline de paridade.

## Rastreabilidade (índice scannable)

| CU | Caso de uso | Prio | V2 | Pest | Status |
|----|-------------|------|----|------|--------|
| CU-01 | Vender pra cliente cadastrado (busca + auto-aplica grupo/prazo/endereço) | must | ✅ | `CustomerAutoApplyOnSelectTest` · `SellsCreatePageTest` | 🟢 |
| CU-02 | Vender pra walk-in ("Cliente padrão") | must | ✅ | `SellsCreatePageTest` (useForm contact_id=walkIn) | 🟢 |
| CU-03 | Cadastrar cliente inline sem sair da venda | must | ✅ | `QuickAddCustomerSheetTest` | 🟢 |
| CU-04 | Buscar produto (nome/SKU/**lote**/código) + **variação/tamanho** | must | ✅ | `ProductSearchAutocompleteRaceTest` · `ProductSearchConfigurableFieldsTest` · `SellsCreatePageTest` | 🟢 |
| CU-05 | Editar linha: qtd/preço/desconto **pt-BR (anti-R$ [redacted Tier 0]k)** | must | ✅ | `SellsCreatePageTest` (NumericInputPtBR/numberPtBR) · `ProductLineCardComponentTest` | 🟢 |
| CU-06 | Pagamento split (N pagamentos) + métodos + conta + **cartão** + saldo (falta/troco/exato) | must | ✅ | `SellsCreatePageTest` (PaymentRow) | 🟢 |
| CU-07 | Desconto do pedido (fixo/%) respeitando permissão | must | ✅ | `SellsCreatePageTest` (editPrice/editDiscount) | 🟢 |
| CU-08 | Status da venda (final/rascunho/cotação/proforma) | must | ✅ | `SellsCreatePageTest` (8 campos + status) | 🟢 |
| CU-09 | Prazo de pagamento + comissionista | should | ✅ | `SellsCreatePageTest` (condicional) · `CommissionSplitEditorTest` | 🟢 |
| CU-10 | Esquema/nº fatura + imposto do pedido | should | ✅ | `SellsCreatePageTest` ("Mais opções") | 🟢 |
| CU-11 | Frete/entrega (endereço + custo + status remessa) | must | ⚠️ básico | `SellsCreatePageTest` (bloco frete) | 🟡 |
| CU-12 | Notas + despesas adicionais | should | ✅ | `SellsCreatePageTest` | 🟢 |
| CU-13 | Salvar / Salvar e imprimir + **auto-save draft** (biz.user) | must | ✅ | `SellsCreatePageTest` (useForm + draft localStorage) | 🟢 |
| CU-14 | Criar OS a partir da venda (comvis/oficina) | should | ✅ | `CriarOsPorVendaTest` | 🟢 |
| CU-15 | Isolamento multi-tenant (biz scope) | must | ✅ | `MultiTenantSqlGuardTest` | 🟢 |
| CU-G1 | Venda recorrente / assinatura | — | ❌ | — | ⚪ Non-Goal (tela `Sells/Subscriptions`) |
| CU-G2 | Resgate de pontos (reward) | could | ❌ | — | ⚪ gap (Blade-only) |
| CU-G3 | Anexar documento à venda | could | ❌ | — | ⚪ gap (Blade-only) |
| CU-G4 | Tipos de serviço (multi-select) | should | ⚠️ | — | 🔴 gap a avaliar |
| CU-G5 | Devolução (esquema Venda/Devolução + fluxo) | — | ❌ | — | ⚪ Non-Goal (fluxo separado) |

**Veredito de paridade:** núcleo da venda ✅ coberto + testado. Pendências reais: **CU-11** (frete estruturado de 1ª classe — era o PR2 revertido #2104, **re-fazer com smoke**) e **CU-G4** (tipos de serviço). O resto dos gaps é Non-Goal documentado.

---

## CU-01 · Vender pra cliente cadastrado · `must`
**Given** `/sells/create` aberta (biz=4, flag V2 on)
**When** o vendedor digita ≥2 chars ("Vargas"), seleciona o cliente no autocomplete
**Then** `contact_id` muda · endereço de cobrança/entrega aparecem · grupo de preço re-aplica (recalcula linhas) · prazo de pagamento pré-preenche
- **V2:** `CustomerSearchAutocomplete` + `handleCustomerSelect`
- **Pest:** `tests/Feature/Sells/CustomerAutoApplyOnSelectTest.php` · `SellsCreatePageTest.php`
- **Smoke biz=4:** buscar cliente real → dropdown popula, **sem 500** (regressão do incidente era flag, não código)

## CU-02 · Vender pra walk-in · `must`
**Given** tela aberta sem cliente selecionado
**When** o vendedor adiciona produtos e finaliza
**Then** a venda usa `walkInCustomer` (Cliente padrão) como `contact_id`
- **V2:** `useForm({ contact_id: props.walkInCustomer.id })` · **Pest:** `SellsCreatePageTest.php`

## CU-03 · Cadastrar cliente inline · `must`
**Given** busca de cliente sem resultado ("Cliente X")
**When** clica "Cadastrar 'X'" → abre Sheet lateral (não nova aba)
**Then** salva cliente → fecha Sheet → seleciona automático na venda (draft preservado)
- **V2:** `QuickAddCustomerSheet` · **Pest:** `QuickAddCustomerSheetTest.php`

## CU-04 · Buscar produto (nome/SKU/lote/código) + variação · `must`
**Given** seção Produtos
**When** digita/bipa termo (nome, SKU, **nº lote**, código de barras)
**Then** dropdown lista resultados (debounce 250ms) · variação/tamanho via Popover quando >1 · mostra lote quando backend retorna
- **V2:** `ProductSearchAutocomplete` (`search_fields[] = name/sku/lot`)
- **Pest:** `ProductSearchAutocompleteRaceTest.php` · `ProductSearchConfigurableFieldsTest.php` · `SellsCreatePageTest.php`

## CU-05 · Editar linha pt-BR (anti-R$ [redacted Tier 0]k) · `must`
**Given** produto na tabela (5 colunas)
**When** edita qtd/preço/desconto digitando "1.500,00"
**Then** parse pt-BR correto (vírgula decimal) · subtotal/total via useMemo · **sem ×10 nem ponto-vírgula trocado**
- **V2:** `NumericInputPtBR` + `Lib/numberPtBR` (`parseDecimalPtBR`)
- **Pest:** `SellsCreatePageTest.php` (NumericInputPtBR) · `ProductLineCardComponentTest.php`
- **Smoke biz=4:** digitar `12550` → R$ [redacted Tier 0] (caso real Larissa 2026-05-27)

## CU-06 · Pagamento split + cartão + saldo · `must`
**Given** total > 0
**When** adiciona N pagamentos (dinheiro/cartão/cheque/transferência/…), conta bancária, e (cartão) os 7 campos
**Then** mostra saldo `falta`/`troco`/`exato` · permite venda a prazo/fiado (pago < total) · remove linha se >1
- **V2:** `PaymentRow` (split + cartão em `<details>`) · **Pest:** `SellsCreatePageTest.php`

## CU-07 · Desconto do pedido · `must`
**Given** venda com itens
**When** aplica desconto fixo ou % (se tiver permissão `editDiscount`)
**Then** total recalcula · campo readonly sem permissão
- **V2:** `discount_type/amount` + `permissions` · **Pest:** `SellsCreatePageTest.php`

## CU-08 · Status da venda · `must`
**Given** tela aberta
**When** escolhe status final/rascunho/cotação/proforma
**Then** payload reflete o status (cotação/proforma → FSM stage inicial)
- **V2:** `status` select · **Pest:** `SellsCreatePageTest.php`

## CU-11 · Frete / entrega · `must` · 🟡 parcial
**Given** venda com entrega
**When** preenche endereço de entrega + custo + status da remessa + entregue a
**Then** persiste em `transactions.shipping_*`
- **V2 (atual):** bloco frete free-text dentro de "Mais opções" — ✅ funcional, ⚠️ **não estruturado**
- **Pendente (PR2 #2104, revertido):** endereço de **1ª classe** lendo `contact.addresses[]` (Destinatário ↔ Local de entrega) + gatilho MDF-e por `city_code`. **Re-fazer com smoke biz=4 antes de religar.** Ver US-SELL-044 (PR3 fiscal).
- **Pest:** `SellsCreatePageTest.php` (bloco frete)

## CU-13 · Salvar + auto-save draft · `must`
**Given** venda em preenchimento
**When** o vendedor é interrompido (F5/telefone) e volta
**Then** draft recuperado (localStorage `oimpresso.sells.create.draft.{biz}.{user}`, TTL 24h) · ao salvar com sucesso, draft limpa
- **V2:** auto-save debounced 500ms + AlertDialog de recover · **Pest:** `SellsCreatePageTest.php`
- **Tier 0:** key inclui `{biz}.{user}` (ADR 0093 — não vazar draft entre tenants)

## CU-15 · Isolamento multi-tenant · `must`
**Given** biz=4 logado
**When** busca clientes/produtos/contas
**Then** só dados do business 4 (global scope `business_id`)
- **Pest:** `MultiTenantSqlGuardTest.php` (biz=1 vs biz=99)

---

## Gaps & Non-Goals (Blade tem, V2 não — de propósito ou pendente)

| ID | Feature Blade | Decisão | Razão |
|----|---------------|---------|-------|
| CU-G1 | Assinatura/recorrência | ⚪ Non-Goal | Vive na tela `Sells/Subscriptions` (separada) |
| CU-G2 | Resgate de pontos | ⚪ gap | UPOS legado; sem sinal de cliente (ADR 0105) |
| CU-G3 | Anexar documento à venda | ⚪ gap | Idem — avaliar se algum cliente usa |
| CU-G4 | Tipos de serviço (multi-select) | 🔴 a avaliar | Prop existe (`typesOfService`), sem UI na V2 — confirmar se comvis/oficina precisa |
| CU-G5 | Devolução (Venda/Devolução) | ⚪ Non-Goal | Fluxo de retorno é separado, não o "adicionar venda" |

> Marcar um item como Non-Goal aqui é **decisão de produto** (Wagner) — o teste não cobra, e ninguém chama de "regressão". Se um cliente reclamar de um destes, vira US com sinal qualificado (ADR 0105).

## Refs
- Charter: [`resources/js/Pages/Sells/Create.charter.md`](../../../resources/js/Pages/Sells/Create.charter.md)
- Pest: `tests/Feature/Sells/*` (15 suites)
- Pipeline FSM: [`CASOS-USO-PIPELINE-VENDAS.md`](CASOS-USO-PIPELINE-VENDAS.md) (orçamento→produção→faturamento)
- Incidente origem: [`memory/sessions/2026-06-02-incidente-revert-pr2-sells-endereco.md`](../../sessions/2026-06-02-incidente-revert-pr2-sells-endereco.md)
- Gate: [`_DesignSystem/PRE-MERGE-UI.md`](../_DesignSystem/PRE-MERGE-UI.md) · ADR 0110 (Cockpit V2) · ADR 0105 (cliente como sinal)
