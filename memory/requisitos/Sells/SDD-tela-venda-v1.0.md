---
id: requisitos-sells-sdd-tela-venda-v1-0
slug: sells-sdd
title: "SDD — Família de telas de Venda (domínio Sells / coração do faturamento)"
type: sdd
module: Sells
status: ativo
owner: wagner
version: 1.0.0
last_updated: 2026-07-27
related_docs:
  - SPEC.md
  - BRIEFING.md
  - CASOS-USO-CREATE-VENDA.md
  - CASOS-USO-PIPELINE-VENDAS.md
  - CAPTERRA-FICHA.md
  - SUPERFICIE.md
  - RUNBOOK-create.md
  - RUNBOOK-index.md
related_adrs:
  - '0093-multi-tenant-isolation-tier-0'
  - '0104-processo-mwart-canonico-unico-caminho'
  - '0110-cockpit-pattern-v2-canon-list-detail'
  - '0129-state-machine-canonica-fsm-rbac'
  - '0143-fsm-pipeline-live-prod-marco-2026-05-12'
  - '0264-governanca-executavel-trio-dominio-e2e'
  - '0351-sdd-from-source'
related_us:
  - US-SELL-001
  - US-SELL-005
  - US-SELL-008
  - US-SELL-011
---

# SDD — Software Design Document · Família de telas de Venda (domínio Sells)

> **Derivado, não escrito do zero.** Este documento nasceu pelo agent
> [`sdd-from-source`](../../../.claude/agents/sdd-from-source.md) ([ADR 0351](../../decisions/0351-sdd-from-source.md))
> triangulando as fontes na ordem canônica de [`how-trabalhar.md`](../../how-trabalhar.md).
> As partes marcadas `<!-- derivado -->` são **re-rodáveis do fonte**; as marcadas
> `<!-- curado -->` são foto datada que envelhece.

---

## 0. Base empírica: as fontes cruzadas

<!-- derivado: re-rodável do fonte -->

### 0.1 As fontes de verdade (e a que NÃO existe)

| # | Fonte | Onde | Estado |
|---|---|---|---|
| 1 | **Documentação canon** | [`CASOS-USO-CREATE-VENDA.md`](CASOS-USO-CREATE-VENDA.md) (15 CU + 5 gaps) · [`CASOS-USO-PIPELINE-VENDAS.md`](CASOS-USO-PIPELINE-VENDAS.md) (7 CU FSM) · [`SPEC.md`](SPEC.md) (51 US) · 8 charters | ✅ rica — é a âncora deste §6 |
| 2 | **React / Laravel atual** | `resources/js/Pages/Sells/**` (8 telas) · `SellController` · `SellPosController` · `TransactionUtil` · `ProductUtil` | ✅ vivo — é o §5 |
| 3 | **Blade AdminLTE legada** | **`resources/views/sell/create.blade.php` (998 linhas)** — ver §0.2, a armadilha | ✅ existe |
| 4 | **Delphi / Office Comercial** | — | ❌ **GAP DECLARADO** |

> ⚠️ **A fonte 4 não existe para Sells.** `find memory -iname "*ANTI-REGRESSAO*"` devolve **2**
> arquivos, **ambos do módulo Produto**. Logo a triangulação aqui é de **3 fontes**, e o
> contrato de paridade com o legado Delphi é **mais fraco** que o do Produto. Isto é declarado,
> não contornado: **nenhum CU deste documento afirma comportamento do Office Comercial.**
> Se [W] quiser essa perna, o caminho é destilar o manual WR Comercial num
> `ANTI-REGRESSAO-venda-legacy.md` — trabalho que **não** foi feito aqui e não deve ser inventado.

### 0.2 A armadilha da Blade homônima — resolvida por medição

<!-- derivado: re-rodável do fonte (routes/web.php · SellController@create · SellPosController@create) -->

O chip que abriu este SDD apontava `sale_pos/create.blade.php` como fonte 3. **Medido, é a
homônima errada.** Existem **dois** `create` que renderizam o **mesmo** componente React:

| Rota | Controller | Se flag `useV2SellsCreate` ON | Se OFF (Blade) | Tamanho da Blade |
|---|---|---|---|---|
| `/sells/create` ← **o que o operador abre** | `SellController@create` | `Inertia::render('Sells/Create')` | **`view('sell.create')`** | **998 linhas** |
| `/sale-pos/create` (POS) | `SellPosController@create` | `Inertia::render('Sells/Create')` | `view('sale_pos.create')` | **131 linhas** |

O ponto de entrada do operador é o botão **"Nova venda"** → `/sells/create`
(`resources/js/Components/PageHeader/PageHeaderPrimary.tsx`), e o próprio `Create.charter.md`
diz na Mission *"substitui `sell.create.blade.php` legacy"*. **Comparar contra a homônima de 131
linhas teria dado "paridade OK" falsa** — é exatamente a classe de erro que a
[ADR 0351](../../decisions/0351-sdd-from-source.md) manda evitar.

**Blade de referência deste SDD: `resources/views/sell/create.blade.php`.**
Re-localizar: `grep -n "return view('sell.create')" app/Http/Controllers/SellController.php`.

---

## 1. Visão geral

**Sells é o coração do faturamento do oimpresso** — a venda é o evento que move dinheiro
(`final_total`, pagamentos) **e** estoque (`decreaseProductQuantity`) no mesmo request. Por isso
todo este domínio vive sob a **REGRA MESTRE valor/estoque** ([proibicoes.md](../../proibicoes.md)).

### 1.1 Família de telas

<!-- derivado: re-rodável (node scripts/governance/requisitos-status.mjs Sells) -->

| Tela | Arquivo | Papel | Contrato (`casos.md`) |
|---|---|---|---|
| **Create** | `Sells/Create.tsx` (2004 L) | adicionar venda completa — a tela-âncora | ✅ UC-S01/S02 + UC-SCRE-* |
| **Index** | `Sells/Index.tsx` (1811 L) | lista/cobrança — "quem está devendo?" | ✅ UC-S10/S11/S12 + UC-SIDX-* |
| **Edit** | `Sells/Edit.tsx` (1100 L) | editar venda emitida | ❌ sem `casos.md` |
| **Show** | `Sells/Show.tsx` (772 L) | ficha da venda | ❌ sem `casos.md` |
| **Caixa/Index** | `Sells/Caixa/Index.tsx` (364 L) | caixa do dia | ❌ sem `casos.md` |
| **Subscriptions** | `Sells/Subscriptions.tsx` (326 L) | assinatura/recorrência | ❌ sem `casos.md` |
| **Quotations** | `Sells/Quotations.tsx` | cotações | ❌ sem `casos.md` |
| **Drafts** | `Sells/Drafts.tsx` | rascunhos | ❌ sem `casos.md` |

---

## 2. Público-alvo e personas

<!-- curado: foto que envelhece -->

- **P1 · Larissa — ROTA LIVRE (biz=4, vestuário SC)** — 99% do volume de vendas do oimpresso
  novo. Balcão, monitor **1280px**, atende telefone no meio da venda (origem do auto-save draft,
  CU-SELL-13). É quem sofre primeiro qualquer erro de valor.
- **P2 · Wagner — WR2 SC (biz=1)** — operador-dono e cobaia segura. **Todo smoke/Pest usa biz=1,
  nunca biz=4** ([ADR 0101](../../decisions/0101-tests-business-id-1-nunca-cliente.md)).
- **P3 · Guilherme / Kamila @ biz=4** — operadores de retaguarda; reportaram o incidente da
  "setinha de retorno" (2026-07-03) que virou UC-S12.

---

## 3. Governança aplicável

### 3.1 Tier 0 — IRREVOGÁVEL

| Invariante | Fonte | Como aparece aqui |
|---|---|---|
| **REGRA MESTRE valor/estoque** | [proibicoes.md](../../proibicoes.md) | todo CU que toca preço/total/desconto/`final_total`/`num_uf`/estoque é `[V0]` — exige dupla-confirmação por 2 caminhos + tabela antes→depois + OK [W] |
| **Multi-tenant `business_id`** | [ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md) | CU `[T0]`. ⚠️ `App\Transaction` **não tem global scope** (padrão UltimatePOS) — o escopo é **manual por query**, o que torna cada subquery crua um vetor (ver §9 D-1) |
| **FSM canônica** | [ADR 0143](../../decisions/0143-fsm-pipeline-live-prod-marco-2026-05-12.md) | mudança de estágio só via `ExecuteStageActionService`; UPDATE direto em `current_stage_id` é bloqueado pelo trait `GuardsFsmTransitions` |
| **MWART** | [ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md) | a Blade legada é o baseline de paridade — feature não some sem Non-Goal explícito |

### 3.2 O incidente que é contrato de não-regressão

<!-- curado: foto datada -->

**2026-06-05 · biz=4 ROTA LIVRE.** `Util::num_uf` interpretou o ponto decimal de um total
fracionado (desconto %) como separador de milhar → o `final_total` gravado ficou inflado em
**~×100.000**. **16 vendas** corrompidas + pagamentos, antes de detectar. Fix #2279.

**Lição técnica perene:** separador de milhar tem SEMPRE 3 dígitos; o frontend **nunca** manda
float locale-ambíguo pro parser pt-BR — arredonda a 2 casas no submit.

Defendido hoje por `CU-SELL-05` / `UC-S02` (`tests/Feature/Calculo/CalculoValorSellsTest.php`)
+ os guards de isolamento `tests/Unit/Utils/IncidentValorInfladoNumUfTest.php`.

---

## 4. Design system aplicável

<!-- curado -->

- **Cockpit Pattern V2** ([ADR 0110](../../decisions/0110-cockpit-pattern-v2-canon-list-detail.md)) —
  sticky header com filter pills + sticky footer com ações (Create); list-detail (Index).
- **Constituição UI v2** ([UI-0013](../_DesignSystem/adr/ui/0013-constituicao-ui-v2-camadas.md)) —
  Fundações → Shell (AppShellV2 + PageHeader) → Padrão de Tela → Módulo.
- **Restrição dura da persona:** cabe em **1280px sem scroll horizontal** (P1 Larissa).
- ⛔ **Resíduo declarado:** `CustomerSearchAutocomplete.tsx` e `ProductSearchAutocomplete.tsx`
  **não** migram pro canon `Command` — decisão [W] 2026-07-15 registrada no §5 de
  [proibicoes.md](../../proibicoes.md) (são Tier 0 valor/estoque + carregam o hotfix do sufixo).

---

## 5. Arquitetura

### 5.1 Visão em camadas

<!-- derivado: re-rodável do fonte -->

```
Pages/Sells/*.tsx (React 19 + Inertia v3)
        │
        ├── GET  /sells/create      → SellController@create      ─┐ dual-response
        ├── GET  /sale-pos/create   → SellPosController@create   ─┘ por feature flag
        ├── POST /pos               → SellPosController@store      (o WRITER da venda)
        ├── GET  /sells-list-json   → SellController@inertiaList   (payload da lista)
        ├── GET  /sells/{id}/sheet-data → SellController@sheetData (drawer)
        └── GET  /vendas/caixa      → SellController@inertiaCaixa
                    │
                    ├── ProductUtil::calculateInvoiceTotal   ← passa por Util::num_uf  [V0]
                    ├── TransactionUtil::createSellTransaction
                    ├── TransactionUtil::createOrUpdateSellLines
                    ├── TransactionUtil::createOrUpdatePaymentLines
                    ├── ProductUtil::decreaseProductQuantity ← ESTOQUE               [V0]
                    └── app/Domain/Fsm/ExecuteStageActionService                     (FSM)
```

**Dual-response por flag** (`FeatureFlagService::isOn('useV2SellsCreate', ['business_id' => …])`):
o **mesmo** método de controller devolve Inertia **ou** Blade. Isso é o mecanismo de cutover
MWART — e é por isso que a Blade legada continua sendo o baseline de paridade vivo, não morto.

### 5.2 Modelo de dados (núcleo)

<!-- derivado -->

| Tabela | Papel | Coluna Tier 0 |
|---|---|---|
| `transactions` | a venda (`type='sell'`) — `final_total`, `total_before_tax`, `discount_amount`, `payment_status`, `status`, `current_stage_id` | `business_id` |
| `transaction_sell_lines` | as linhas (qtd × preço) | via `transaction_id` |
| `transaction_payments` | pagamentos (split) — `is_return` distingue devolução | via `transaction_id` |
| `variation_location_details` | estoque por local (o que `decreaseProductQuantity` move) | `business_id` |
| `sale_stage_history` | audit append-only do FSM | via processo |

**Devolução:** uma transação `type='sell_return'` com `return_parent_id` apontando pra venda.
Este é o critério canônico (mesmo do JOIN `SR` em `TransactionUtil::getSellsCurrentFy`).

### 5.3 Fluxos críticos

**F1 · Salvar a venda (`store`) — o caminho Tier 0 do módulo:** <!-- derivado -->
`Sells/Create.tsx` (`post('/pos')`, L717) → `POST /pos` (`Route::resource('pos', SellPosController::class)`)
→ `SellPosController@store` → dentro de uma transação:
`ProductUtil::calculateInvoiceTotal($products, $tax_rate_id, $discount)` **[V0 — passa por `Util::num_uf`,
vetor do incidente 2026-06-05]** → `TransactionUtil::createSellTransaction($business_id, $input, $invoice_total, $user_id)`
→ `createOrUpdateSellLines` → `createOrUpdatePaymentLines` → `ProductUtil::decreaseProductQuantity`
**[V0 — estoque]**. Re-localizar: `grep -n "calculateInvoiceTotal\|createSellTransaction" app/Http/Controllers/SellPosController.php`.

> ⚠️ **`SellController@store` está VAZIO** (corpo `//`), mas `Route::resource('sells', SellController::class)->except(['show'])`
> **registra `POST /sells`**. Logo existe um endpoint vivo que aceita POST e não faz nada
> (200 com corpo vazio). Não é o writer — o writer é `POST /pos`. Ver §9 D-2.

**F2 · Listar as vendas (`inertiaList`):** <!-- derivado -->
`Sells/Index.tsx` → `GET /sells-list-json` → `SellController@inertiaList` → query base escopada
(`->where('transactions.business_id', $business_id)->where('type','sell')->where('status','final')`)
+ `leftJoin('nfe_emissoes')` escopado + subquery `return_exists` + `$totalsQuery->selectRaw(...)`
pros somatórios de rodapé (`sum_final_total`, `sum_total_paid`) **[V0 — valor agregado exibido]**.
Re-localizar: `grep -n "function inertiaList" app/Http/Controllers/SellController.php`.

> 🐛 **A subquery de devolução NÃO é escopada por business** — nem no React, nem no legado:
> `(SELECT COUNT(*) FROM transactions sr WHERE sr.return_parent_id = transactions.id AND sr.type = 'sell_return')`
> e o `leftjoin('transactions as SR', …)` de `getSellsCurrentFy` filtram só `type` e
> `return_parent_id`. Como `App\Transaction` **não tem global scope**, nada supre isso.
> Contrato novo: **`CU-SELL-32` / `UC-SIDX-01`**. Detalhe e limite honesto de severidade em §9 D-1.

**F3 · Pipeline FSM (mudança de estágio):** <!-- derivado -->
`FsmActionPanel.tsx` (drawer `SaleSheet`) → action → `ExecuteStageActionService::execute(subject, action_key, user, payload)`
→ RBAC (`sale_stage_action_roles`; `is_critical` exige role) → side-effects
(`ReservarEstoque`/`ConsumirEstoque`/`LiberarReserva`/`CancelarVendaCascade`) → `sale_stage_history`
(append-only). UPDATE direto em `current_stage_id` é bloqueado pelo trait `GuardsFsmTransitions`.

**F4 · Cutover MWART por flag:** <!-- derivado -->
`useV2SellsCreate` no GrowthBook self-hosted, avaliado **por business**. Enquanto a flag existir,
`sell/create.blade.php` é código VIVO — paridade é obrigação, não arqueologia.

**F5 · Devolução:** fluxo separado (`/sell-return/add/{id}`), alcançado pelo menu ⋮ da lista
(**UC-S11** — o ponto de entrada que sumiu no rewrite #1032 e voltou como contrato).

---

## 6. Casos de uso

> ⚙️+🖐 **misto** — o **enunciado** de cada CU é derivado das fontes (canon + React + Blade) e é
> re-rodável; o **estado** (✅/🟡/🔴/⬜) é curado e sai do **veredito da lane**, nunca da leitura
> do código.
>
> **Convenção:** `[must]`/`[should]` prioridade · `[T0]` invariante multi-tenant · `[V0]`
> **REGRA MESTRE valor/estoque** · `[ux]`.
>
> 📐 **Como a porta viva conta os CU daqui (pra ninguém ler o número errado).**
> `scripts/governance/requisitos-status.mjs` extrai CU por
> `/^####\s+(CU-[A-Z]{2,8}-\d{2,4})/` — ou seja, conta **só os que têm heading `####`**, não as
> linhas de tabela. Hoje isso dá **6** (os que este chip contratou ou que carregam peso Tier 0),
> enquanto o §6 **documenta 33**. O número da porta não está errado: ele mede *"CU com verbete
> próprio"*, que é um subconjunto proposital. Promover um CU de linha-de-tabela pra `####` é o
> ato de **assumir o contrato dele** — e a porta então passa a cobrar o UC correspondente.
> Re-rodar: `node scripts/governance/requisitos-status.mjs Sells`.

> 🔢 **Namespace.** Este SDD introduz `CU-SELL-NN`. Os documentos canon anteriores usam `CU-NN`
> **sem namespace** — e **colidem entre si**: `CASOS-USO-CREATE-VENDA.md` e
> `CASOS-USO-PIPELINE-VENDAS.md` ambos têm `CU-01..07` para casos **diferentes**. O mapa
> abaixo reconcilia; os ids antigos ficam como referência histórica, não são reusados.

### 6.1 Núcleo da venda (`CU-SELL-01..15`) — mapeia `CASOS-USO-CREATE-VENDA.md`

| CU | Caso | Prio | Legado | Estado (curado) |
|---|---|---|---|---|
| **CU-SELL-01** | Vender pra cliente cadastrado (auto-aplica grupo de preço/prazo/endereço) | `[must]` `[V0]` | CU-01 | ✅ |
| **CU-SELL-02** | Vender pra walk-in ("Cliente padrão") | `[must]` | CU-02 | ✅ |
| **CU-SELL-03** | Cadastrar cliente inline sem sair da venda | `[must]` | CU-03 | ✅ |
| **CU-SELL-04** | Buscar produto (nome/SKU/lote/código) + variação | `[must]` | CU-04 | ✅ |
| **CU-SELL-05** | Editar linha pt-BR — **anti-inflação de decimal** | `[must]` `[V0]` | CU-05 | ✅ `UC-S02` |
| **CU-SELL-06** | Pagamento split + cartão + saldo (falta/troco/exato) | `[must]` `[V0]` | CU-06 | ✅ `UC-S01` |
| **CU-SELL-07** | Desconto do pedido (fixo/%) respeitando permissão | `[must]` `[V0]` | CU-07 | ✅ |
| **CU-SELL-08** | Status da venda (final/rascunho/cotação/proforma) | `[must]` | CU-08 | ✅ |
| **CU-SELL-09** | Prazo de pagamento + comissionista | `[should]` | CU-09 | ✅ |
| **CU-SELL-10** | Esquema/nº fatura + imposto do pedido | `[should]` | CU-10 | ✅ |
| **CU-SELL-11** | Frete/entrega (endereço + custo + status remessa) | `[must]` | CU-11 | 🟡 **parcial** — free-text, não estruturado (PR2 #2104 revertido) |
| **CU-SELL-12** | Notas + despesas adicionais | `[should]` | CU-12 | ✅ |
| **CU-SELL-13** | Salvar + auto-save draft por `{biz}.{user}` | `[must]` `[T0]` | CU-13 | ✅ |
| **CU-SELL-14** | Criar OS a partir da venda (comvis/oficina) | `[should]` | CU-14 | ✅ |
| **CU-SELL-15** | Isolamento multi-tenant nos dropdowns/buscas | `[must]` `[T0]` | CU-15 | ✅ |

#### CU-SELL-05 — Editar linha em pt-BR sem inflar o decimal `[must]` `[V0]` ✅
*Dado* um produto na tabela; *quando* o operador digita valor com vírgula decimal e aplica
desconto percentual; *então* o `final_total` calculado é o total real **e nunca** um valor
inflado por leitura do ponto decimal como separador de milhar.
1. `[V0]` Round-trip `num_uf(num_f(x)) == x` na precisão de moeda.
2. `[V0]` Invariante `final_total ≤ total_before_tax` vale sempre.
3. `[V0]` **Divergência caracterizada, não unificada:** `getTotalPaid` é **líquido**
   (`SUM(IF(is_return=0, amount, amount*-1))`) e é a fonte do `payment_status`;
   `getTotalAmountPaid` é **bruto**. Unificar = mudança de valor em prod → **US separada sob
   REGRA MESTRE**, nunca carona.
4. Contrato de tela: `UC-S02` · teste `tests/Feature/Calculo/CalculoValorSellsTest.php`.

#### CU-SELL-06 — Venda a prazo (fiado) fecha com saldo devedor `[must]` `[V0]` ✅
*Dado* cliente e produto no carrinho; *quando* salva **sem** informar pagamento; *então* a tela
acusa o saldo devedor **antes** do submit e o backend grava `payment_status='due'` — não bloqueia.
Decisão [W] 2026-05-27: paridade com o POS Blade, que sempre permitiu finalizar sem pagamento.
Contrato de tela: `UC-S01`.

### 6.2 Pipeline FSM (`CU-SELL-20..26`) — mapeia `CASOS-USO-PIPELINE-VENDAS.md`

<!-- derivado -->

| CU | Caso | Prio | Legado | US |
|---|---|---|---|---|
| **CU-SELL-20** | Cancelar NFe **não** pula sequencial | `[must]` | CU-01 (G1+G2) | US-SELL-029/030 |
| **CU-SELL-21** | Action FSM crítica exige role obrigatória (fail-secure) | `[must]` `[T0]` | CU-02 (G3) | US-SELL-031 |
| **CU-SELL-22** | UPDATE direto em `current_stage_id` é bloqueado | `[must]` | CU-03 (G4) | US-SELL-032 |
| **CU-SELL-23** | Processo "Venda Com Produção" canônico | `[must]` | CU-04 (G5) | US-SELL-033 |
| **CU-SELL-24** | Cancelamento em cascata (`CancelarVendaCascade`) | `[must]` `[V0]` | CU-05 (G6) | US-SELL-034 |
| **CU-SELL-25** | Voltar de estágio exige autorização explícita | `[must]` | CU-06 | US-031+033 |
| **CU-SELL-26** | Timeline auditável visível ao operador | `[should]` | CU-07 (G7) | US-SELL-035 |

> 🚦 **Todos os 7 são "verde impossível" hoje.** Os testes existem
> (`tests/Feature/Domain/Fsm/*`) mas **nenhuma lane de CI os executa no PR** — medido por
> `node scripts/governance/anchor-lint.mjs memory/requisitos/Sells/SPEC.md` (12 US fora de lane).
> Isto é dívida de **infra de teste**, não de contrato. Ver §9 D-3.

### 6.3 Lista e cobrança (`CU-SELL-30..32`) — a tela Index

<!-- derivado: re-rodável do fonte (SellController@inertiaList) -->

#### CU-SELL-30 — Enxergar a cobrança num relance `[must]` `[ux]` ✅
*Dado* a lista carregada; *então* o título **Vendas** renderiza e as pílulas de status por
pagamento aparecem (default **Todas**; pagas/a-receber derivadas de `payment_status`).
Contrato de tela: `UC-S10`.

#### CU-SELL-31 — Reconhecer, na lista, o que já teve devolução `[must]` ✅
*Dado* uma transação `sell_return` com `return_parent_id` apontando pra venda; *quando* a lista
carrega; *então* o payload traz `has_return: true` e a linha ganha o indicador de retorno.
1. `[must]` O ponto de entrada da devolução (menu ⋮ → `/sell-return/add/{id}`) **existe na linha** —
   sumiu no rewrite #1032 e voltou como contrato (`UC-S11`).
2. **Regressão que defende:** a "setinha de retorno" existia no Blade legado
   (`SellController@index` → `return_exists`, `fa-undo`) e sumiu no rewrite Cowork #1032
   (incidente 2026-07-03, reportado por Guilherme @ biz=4).
Contrato de tela: `UC-S11` + `UC-S12`.

#### CU-SELL-32 — O indicador de devolução só conta devoluções **do mesmo business** `[must]` `[T0]` ⬜
*Dado* uma venda do business A; *quando* existe uma transação `sell_return` de **outro** business
cujo `return_parent_id` aponta pra ela; *então* o payload da lista de A traz `has_return: false`
— o indicador reflete **apenas** devoluções do próprio tenant.
1. `[T0]` A derivação de `has_return` é escopada por `business_id` ([ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md)).
2. **Por que não é automático:** `App\Transaction` **não** tem global scope; a derivação é uma
   subquery/JOIN **cru** sobre `transactions`, então nada supre o escopo.
3. **Estado:** ⬜ **não verificado** — o contrato nasce neste PR; o veredito é da lane.
Contrato de tela: `UC-SIDX-01`.

#### CU-SELL-33 — O totalizador de dinheiro do rodapé não soma venda de outro business `[must]` `[V0]` `[T0]` ⬜
*Dado* uma venda `final` de valor conhecido no business B; *quando* o operador do business A
carrega a lista; *então* `sum_final_total` / `sum_total_paid` de A **não** variam por causa dela.
1. `[V0]` Número de dinheiro exibido ao operador → REGRA MESTRE (o assert é **antes → depois**).
2. `[T0]` O agregado herda o escopo `business_id` da query base.
3. ⚠️ **O que este CU NÃO afirma:** que o agregado seja igual à soma das **linhas retornadas**.
   Medido: `$totalsQuery` é uma query **separada, sem o `limit`** da listagem — cobre o conjunto
   filtrado inteiro, não a página. Afirmar "soma == linhas visíveis" geraria **falso-vermelho
   contra comportamento correto**. O contrato é o **escopo**, não a paginação.
4. **Estado:** ⬜ **não verificado** — contrato novo. Contrato de tela: `UC-SIDX-02`.

### 6.4 Non-goals explícitos (por design, não regressão)

<!-- curado: decisão de produto — só [W] muda -->

| ID | Feature | Decisão | Razão |
|---|---|---|---|
| NG-01 | Assinatura/recorrência **no Create** | ⚪ Non-Goal | vive na tela `Sells/Subscriptions` |
| NG-02 | Devolução **no Create** | ⚪ Non-Goal | fluxo separado (`/sell-return/add/{id}`) |
| NG-03 | POS rápido | ⚪ Non-Goal | vai pra `/sale-pos/create` |
| NG-04 | Cotação | ⚪ Non-Goal | `/sells/quotation/create` (FSM `quote_draft`) |
| NG-05 | Print direto | ⚪ Non-Goal | rota Blade separada `/sells/{id}/print` |
| NG-06 | Resgate de pontos (reward) | ⚪ gap | UPOS legado; sem sinal de cliente ([ADR 0105](../../decisions/0105-cliente-como-sinal-guiar-sem-mandar.md)) |
| NG-07 | Anexar documento à venda | ⚪ gap | idem |
| NG-08 | Tipos de serviço (multi-select) | 🔴 **a avaliar** | prop `typesOfService` existe, sem UI na V2 — **decisão [W] pendente** |

> Marcar Non-Goal é **decisão de produto ([W])**. O teste não cobra, e ninguém chama de regressão.

---

## 7. Requisitos não-funcionais

<!-- curado -->

| NFR | Alvo | Origem |
|---|---|---|
| First-paint p95 | < 1200 ms | `Create.charter.md` |
| Save click → response | < 800 ms | `Create.charter.md` |
| Viewport | 1280px sem scroll horizontal | persona P1 |
| Erros JS no console | 0 | `Create.charter.md` |
| Props caras | `Inertia::defer` por default | [RUNBOOK-inertia-defer-pattern](../_DesignSystem/RUNBOOK-inertia-defer-pattern.md) |
| Isolamento | 0 vazamento cross-tenant | [ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md) |

---

## 8. Estratégia de qualidade e rollout

### 8.1 Testes — e a lane que **não existia**

<!-- derivado: re-rodável (grep em .github/workflows/ + phpunit.xml + shards-plan.mjs) -->

As **três portas** de "roda / é cobrado", medidas separadamente (nunca deduzidas uma da outra):

| Pergunta | Porta consultada | Resposta para `tests/Feature/Sells` (72 arquivos) |
|---|---|---|
| roda em **algum** lugar? | `phpunit.xml` (`<directory>./tests/Feature</directory>`) + `scripts/tests/shards-plan.mjs` | ✅ **sim** — enumerado como shard; roda no full-suite nightly do CT 100 |
| roda **no PR**? | allowlist dos workflows + `.github/ci-sqlite-pest.list` | ❌ **não** — `grep -rn "Feature/Sells" .github/` = **0** |
| **bloqueia merge**? | `governance/required-checks-baseline.json` | ❌ **não** — nenhuma entrada Sells |

> 📌 **Correção de premissa:** o chip que abriu este SDD dizia que um teste em `tests/Feature/Sells`
> nasceria *"verde impossível"*. Medido: ele **roda** (nightly), só não **no PR**. A distinção
> importa — "verde impossível" é o que o `anchor-lint` chama de *fora de lane de JUnit*, e é
> resolvido pela lane nova, não pela mudança de casa do arquivo.

**Lane criada por este SDD:** [`.github/workflows/sells-pest.yml`](../../../.github/workflows/sells-pest.yml)
— MySQL real + seed biz=1/biz=2, espelhando `compras-pest.yml`. **Nasce advisory**
(fora do `required-checks-baseline.json`, que este chip não toca). Allowlist explícita:
reprova visível, **não bloqueia merge**.

### 8.2 Rollout canônico

Flag `useV2SellsCreate` por business → smoke biz=1 → canary → cutover.
**Regra de cutover (decisão A):** só religar a V2 quando **todo CU `must` estiver verde na lane**
+ smoke biz=4 (PRE-MERGE-UI Camada 4).

---

## 9. Riscos e dívidas conhecidas

<!-- derivado: cada item tem varredura contada + âncora -->

**D-1 · `has_return` deriva de subquery/JOIN não escopado por `business_id`.**
Dois sites medidos: `SellController@inertiaList` (subquery `DB::raw`) e
`TransactionUtil::getSellsCurrentFy` (`leftjoin('transactions as SR', …)`). Ambos filtram só
`type='sell_return'` + `return_parent_id`. `App\Transaction` não tem global scope
(`grep -n "addGlobalScope" app/Transaction.php` = 0).
**Limite honesto de severidade:** `return_parent_id` não é controlado pelo usuário no fluxo
normal, então **não há vazamento provado** — é gap de **defesa em profundidade**, e o texto
acima é o fato (o escopo ausente), não uma afirmação de exploração. Contrato: `CU-SELL-32`.
**Não corrigido aqui** (mudança de query de produção = decisão [W]); o contrato é que denuncia.

**D-2 · `POST /sells` é um endpoint vivo que não faz nada.**
`SellController@store` tem corpo `//`; `Route::resource('sells', SellController::class)->except(['show'])`
registra a rota mesmo assim. O writer real é `POST /pos`. Risco: consumidor futuro (ou integração)
apontar pro endpoint errado e receber 200 sem persistir. **Reportado, não corrigido** —
mexer em rota de venda é Tier 0.

**D-3 · 12 US com teste fora de lane ("verde impossível").**
`anchor-lint` acusa US-SELL-011..014, 029..036 — todas apontando `tests/Feature/Domain/Fsm/*`,
que **não está em lane nenhuma**. A lane criada aqui cobre `tests/Feature/Sells`, **não**
`tests/Feature/Domain/Fsm` (fora da área deste chip, e uma lane de Domain é decisão de escopo
compartilhado). **Reportado.**

**D-4 · A porta viva `requisitos-status.mjs` não enxerga os UC de Sells.**
O extrator usa `/\b(UC-[A-Z0-9]{2,10}-\d{2,3})\b/` — exige **3 segmentos**. Sells é o único
módulo com convenção de **2** segmentos (`UC-S01`), então seus **5 UC reais** são reportados como
"casos.md existe mas não declara nenhum UC". Censo contado: `UC-S` = 5; todos os outros 19
prefixos do repo são de 3 segmentos. **Reportado, não corrigido** (`scripts/` está fora da área).
Mitigação aplicada aqui: **todo UC novo nasce com 3 segmentos** (`UC-SCRE-*`, `UC-SIDX-*`), e os
antigos ficam (renomeá-los quebraria a citação dos testes que já os defendem — G-2).

**D-5 · Colisão de namespace entre os dois docs canon de CU.**
`CASOS-USO-CREATE-VENDA.md` e `CASOS-USO-PIPELINE-VENDAS.md` usam ambos `CU-01..07` para casos
diferentes. Resolvido **forward-only** pelo namespace `CU-SELL-NN` deste §6; os docs antigos
seguem como referência histórica.

**D-6 · CU-SELL-11 (frete estruturado) segue parcial.** Era o PR2 #2104, revertido
(incidente 2026-06-02). Re-fazer exige smoke biz=4 antes de religar.

---

## 10. Roadmap de evolução

| # | Item | Destrava |
|---|---|---|
| 1 | **Lane `sells-pest` verde e estável** (advisory) | tira 72 testes do escuro do nightly |
| 2 | Contrato das **6 telas sem `casos.md`** (Edit · Show · Caixa · Subscriptions · Quotations · Drafts) | fecha o trio do módulo |
| 3 | Decidir D-1 (escopar `has_return`) | fecha `CU-SELL-32` |
| 4 | Lane para `tests/Feature/Domain/Fsm` | tira 12 US do "verde impossível" (D-3) |
| 5 | Retomar CU-SELL-11 (frete de 1ª classe) com smoke biz=4 | D-6 |
| 6 | Decidir NG-08 (tipos de serviço) | **[W]** |
| 7 | Destilar `ANTI-REGRESSAO-venda-legacy.md` (fonte 4) | dá a 4ª perna de paridade |

---

## 11. Referências

- [`CASOS-USO-CREATE-VENDA.md`](CASOS-USO-CREATE-VENDA.md) · [`CASOS-USO-PIPELINE-VENDAS.md`](CASOS-USO-PIPELINE-VENDAS.md) · [`SPEC.md`](SPEC.md)
- Charters: `resources/js/Pages/Sells/*.charter.md` (8)
- Contratos: `Create.casos.md` · `Index.casos.md`
- [ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md) · [ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md) · [ADR 0110](../../decisions/0110-cockpit-pattern-v2-canon-list-detail.md) · [ADR 0143](../../decisions/0143-fsm-pipeline-live-prod-marco-2026-05-12.md) · [ADR 0264](../../decisions/0264-governanca-executavel-trio-dominio-e2e.md) · [ADR 0351](../../decisions/0351-sdd-from-source.md)
- [proibicoes.md](../../proibicoes.md) §REGRA MESTRE valor/estoque · §Precedência

## Changelog

- **v1.0.0 · 2026-07-27** · [CC] — nascimento. Derivado de 3 fontes (canon + React/Laravel + Blade
  `sell/create.blade.php`); fonte 4 (Delphi) declarada ausente. §5.3 com F1–F5 medidos; §6 com
  33 CU namespaced (`CU-SELL-*`) reconciliando a colisão `CU-01..07` dos dois docs canon.
  6 dívidas registradas (D-1..D-6), 4 delas achados novos desta corrida.
