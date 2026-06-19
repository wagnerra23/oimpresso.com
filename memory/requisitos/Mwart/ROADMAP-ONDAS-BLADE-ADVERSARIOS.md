---
module: Mwart
doc_type: roadmap
title: "Rota de migração do backbone Blade (UltimatePOS) — 10 ondas governadas por adversário e contrato de completude"
status: aceito
owner: wagner
author: "[CC]"
decide: "[W]"
created: "2026-06-13"
parent_adr: 0104
related_adrs: [0099, 0104, 0106, 0107, 0114, 0179, 0188, 0276]
adr: "memory/decisions/0277-rota-migracao-blade-ondas-completude.md"
origin: "Claude Design F0 (chat51 'Migração em Ondas') — Wagner pediu estudar o produto em etapas, verificar funções das telas Blade e traçar rota que só pare depois de TODAS migradas, em ondas com adversários. Verificado contra routes/web.php@main nesta sessão."
---

# Rota de migração Blade → Cockpit — 10 ondas e adversários

> **Etapa 0 / F0 — censo + contrato.** Este documento NÃO migra código. Ele inventaria o
> backbone **Blade do UltimatePOS** (`resources/views/*`, lido de `routes/web.php@main`) e
> traça a rota completa que só termina quando o **último route Blade for desligado**.
> O *como* migrar cada tela é o processo canônico **MWART** ([ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md), 5 fases F1→F5).
> Este doc é o *quê / quando / em que ordem* — e a **régua de honestidade** que impede declarar "migrado" cedo demais.
>
> Origem: Claude Design F0 (`chat51`). Decisão de adoção: [ADR 0277](../../decisions/0277-rota-migracao-blade-ondas-completude.md) (aceita 2026-06-13) · **[W] decide o que vira fila.**

## §0 · O contrato de completude — o que "migrado" significa

São **dois universos**, não um:

1. Os **36 módulos nWidart** em `Modules/*` — já auditados em `AUDITORIA_MODULOS.md`. É onde Caixa Unificada / Sells / Financeiro React vivem.
2. O **backbone Blade do UltimatePOS** em `resources/views/*` — **653 arquivos `.blade.php`** em **67 pastas de view**, ~50 controllers, **nunca inventariado para migração**. É o que esta rota cobre.

A armadilha que a rota fecha:

| ❌ A contagem desonesta | ✅ A contagem honesta (esta rota) |
|---|---|
| **"existe tela React" = migrado** | **route Blade morto ou 302 → React** |
| O `web.php` está cheio de **coexistência**: `/payments/v2` (Inertia) ao lado de `/payments` (Blade); `/vendas/caixa` ao lado de `/cash-register`; `/cliente` ao lado de `/contacts`. O React nasce, mas o route Blade **continua respondendo** — dois caminhos pra mesma função. | Uma onda só fecha quando o route legado é **removido ou redirecionado** e a view Blade vira lápide — nunca enquanto coexistem. O medidor não é "React existe", é **"Blade não responde mais"**. É isso que faz a rota *"só parar depois de todas migradas"*. |

> **Por que contar por route, não por arquivo `.blade`.** 653 views incham com partials, modais e
> e-mails. A unidade de verdade é a **função** (endpoint nomeado no `web.php`) e o **route Blade vivo**.
> Migrar = nenhum route da família ainda servir HTML AdminLTE. As contagens abaixo são **≈ por família
> de controller** — honestas sobre serem aproximadas (lidas do roteador, não de cada view).

## §1 · Censo das telas Blade — o que cada domínio faz

**~50 controllers** · **653 `.blade.php`** · **12 domínios funcionais (A–L) a migrar** + **1 a erradicar (M)** · **10 ondas até 0 route Blade vivo**.

| # | Domínio & controllers | ~fn | Estado | Onde está / onda |
|---|---|---|---|---|
| A | **Acesso & onboarding** — Auth · Business(register) · Install · SocialAuth | 10 | coexiste | `/login` novo + `/login/old` Blade vivo · register Blade → **Onda 9** |
| B | **Dashboard / início** — Home · DashboardConfigurator | 8 | migrado | `/home` 302 → `/ia/dashboard` (Jana) · `/dashboard-legacy` resta → **desligar** |
| C | **Clientes & contatos** — Contact · CustomerGroup · ContactLookup | 26 | parcial | drawer `/cliente` ([ADR 0179](../../decisions/0179-cliente-drawer-760px-substitui-show-fullpage.md)/[0188](../../decisions/0188-contacts-multi-type-flag-aditiva.md)) vivo · `/contacts` Blade resta → **Onda 2** |
| D | **Produtos & catálogo** — Product · Taxonomy · Brand · Unit · Variation · Warranty · SellingPriceGroup · Barcode · Label · Discount · Import | 55 | parcial | `/products/unificado` Inertia vivo · CRUD + variações + preços Blade → **Onda 3** |
| E | **Vendas & PDV** — Sell · SellPos · SalesOrder · SellReturn · TypesOfService · CommissionAgent | 60 | coexiste | Sells Inertia + FSM + caixa vivos · `pos`/`sell-return`/quotations Blade → **Onda 1** |
| F | **Compras & suprimentos** — Purchase · PurchaseOrder · PurchaseRequisition · PurchaseReturn · Combined | 22 | só Blade | protótipo `compras-page.jsx` no Cowork · repo 100% Blade → **Onda 5** |
| G | **Estoque & inventário** — StockAdjustment · StockTransfer · OpeningStock · Import | 14 | só Blade | ajuste/transferência/estoque inicial Blade → **Onda 4** |
| H | **Caixa (abertura/fecho)** — CashRegister | 6 | coexiste | `/vendas/caixa` Inertia vivo · `/cash-register` Blade resta → **Onda 1** |
| I | **Contábil & tesouraria** — Account · AccountReports · AccountType · Expense · TransactionPayment · LedgerDiscount | 30 | coexiste | módulo Financeiro React ✓ · camada Account Blade (contas/balancete/cash-flow) viva → **Onda 6** |
| J | **Relatórios** — ReportController (≈45 endpoints) | 45 | só Blade | lucro/perda, estoque, fiscal, vendedor, lote… todos Blade → **Onda 8** (a represa) |
| K | **Configurações & admin** — Business · Location · Invoice(Layout/Scheme) · Tax · Printer · Notification · Backup · Role · User · ManageModules | 48 | coexiste | Gerenciador de Módulos React ✓ · resto (locais/impostos/notas/usuários) Blade → **Onda 7** |
| L | **Documentos & notas** — DocumentAndNote · mídia anexada | 7 | só Blade | transversal — anexos já consumidos por drawers React → **Onda 7** (junto de config) |
| M | **Restaurante / mesas** — `Restaurant\{Table·Modifier·Kitchen·Order·Booking}` | 18 | fora | fora do domínio gráfica/oficina — **não migrar, erradicar** (§4) |

**Legenda de estado:** `só Blade` (0 React) · `parcial` (React parcial, Blade vivo) · `coexiste` (gêmeo React + Blade vivo) · `migrado` (falta só desligar Blade).

## §2 · A rota — 10 ondas até zero route Blade vivo

> **Ordem = frequência de balcão da Larissa (1280px) × dependência de dados.** Catálogo antes do que
> vende; estoque antes de compras fechar o ciclo; e **relatórios por último** — eles leem os dados de
> todos os domínios e só ficam honestos quando a fonte já migrou. Cada onda traz seu **adversário**
> (a régua de qualidade do [CD], F1.5 · 15 dimensões) e seu **critério de desligamento**.

### Onda 1 — Vendas, PDV & Caixa `domínios E + H` · coexiste · ≈66 fn
- **Adversário [CD]:** Square POS + Stripe Checkout — régua de **velocidade de venda** (1 mão, teclado, zero recarga) e **recibo/pagamento sem fricção**. Se a Larissa vender mais devagar que num Square, a onda falhou.
- **Absorve:** `pos`, `sells`, drafts, quotations, subscriptions, `sell-return`, shipments, types-of-service, commission-agents, `cash-register`.
- **Já vivo em React:** Sells/Index + Create + drawer FSM + `/vendas/caixa`. Falta o **PDV puro** (`pos`) e devoluções.
- **Critério de desligamento:** 302/remoção de `resource('pos')`, `resource('sell-return')` e `resource('cash-register')`; `sell/*.blade` viram lápide.
- **Dependência:** nenhuma — é a primeira. Só depende do catálogo existir (já existe em Blade; Onda 3 melhora, não bloqueia).
- 📄 Plano F1 detalhado: [ONDA-1-VENDAS-PDV-CAIXA-PLANO.md](ONDA-1-VENDAS-PDV-CAIXA-PLANO.md)

### Onda 2 — Clientes & contatos `domínio C` · parcial · ≈26 fn
- **Adversário [CD]:** Attio — CRM de **ficha viva** (histórico, vendas, pagamentos, anexos, pontos num drawer denso, sem trocar de página). Régua: **contexto sem cliques**.
- **Absorve:** `contacts` (customer/supplier/employee/representative), ledger, import, mapa, customer-group, lookup CNPJ.
- **Já vivo em React:** `/cliente` drawer 760px + abas anexos/vendas/pagamentos/assinaturas (fix 2026-06-08).
- **Critério de desligamento:** `resource('contacts')` redireciona pra `/cliente` nos 6 tipos; ledger e import portados; `contact/*.blade` lápide.
- **Dependência:** leve sobre Onda 1 (a venda referencia cliente). Pode rodar em paralelo.

### Onda 3 — Produtos & catálogo `domínio D` · parcial · ≈55 fn
- **Adversário [CD]:** Linear (densidade) + Shopify Admin (produto) — grade densa navegável por teclado + editor de produto com variações que não vira formulário infinito. Régua: **cadastrar uma variação sem medo**.
- **Absorve:** `products` CRUD, variações, selling-prices, BOM, combo, quick-add, bulk, taxonomies, brands, units, variation-templates, warranties, selling-price-group, barcodes, labels, discount, import-products, opening-stock.
- **Já vivo em React:** `/products/unificado` (5 sub-telas) + `produtos-page` Cowork (grid P1, candidato a DataGrid shared).
- **Critério de desligamento:** `resource('products')`, `taxonomies`, `brands`, `units`, `barcodes`, `discount` e satélites desligados; editor de variação cobre 100% do que `product/edit.blade` fazia.
- **Dependência:** pré-requisito de **qualidade** das Ondas 1, 4 e 8 (todas leem produto), mas não bloqueia a 1 (catálogo Blade serve enquanto isso).

### Onda 4 — Estoque & inventário `domínio G` · só Blade · ≈14 fn
- **Adversário [CD]:** Linear + Cron (operação rápida) — ajuste é tarefa de **poucos campos, alta confiança**. Régua: **registro auditável em 2 cliques** com rastro de quem/quando, sem planilha.
- **Absorve:** `stock-adjustments`, `stock-transfers` (+ print, update-status), opening-stock, import-opening-stock.
- **Já vivo em React:** nada — 100% Blade. Reusa o DataGrid shared candidato da Onda 3.
- **Critério de desligamento:** `resource('stock-adjustments')` e `resource('stock-transfers')` desligados; movimentos preservam append-only/auditoria.
- **Dependência:** **depende da Onda 3** (produto é a entidade do movimento). Roda depois do catálogo.

### Onda 5 — Compras & suprimentos `domínio F` · só Blade · ≈22 fn
- **Adversário [CD]:** Ramp / Procurement moderno — compra é **fluxo de aprovação + recebimento**. Régua: requisição → pedido → entrada de estoque **costurado**, com status que conta a verdade.
- **Absorve:** `purchases`, `purchase-order`, `purchase-requisition`, `purchase-return`, combined-return, import-purchase-products.
- **Já vivo em React:** protótipo `compras-page.jsx` no Cowork (semente de F1); repo 100% Blade.
- **Critério de desligamento:** `resource('purchases')`, `purchase-order`, `purchase-return` desligados; entrada de compra alimenta o estoque da Onda 4.
- **Dependência:** **depende das Ondas 3 + 4** (produto + estoque). Fecha o ciclo de entrada.

### Onda 6 — Contábil & tesouraria `domínio I` · coexiste · ≈30 fn
- **Adversário [CD]:** Mercury + QuickBooks — leitura calma de saldo/fluxo (Mercury) + balancete/conciliação sem assustar (QuickBooks). Régua: **número grande e honesto**, conciliação que fecha.
- **Absorve:** `account` (+ fund-transfer, deposit, cash-flow, balance-sheet, trial-balance, payment-account-report, link-account), account-types, `expenses`, expense-categories, `payments`, ledger-discount.
- **Já vivo em React:** módulo Financeiro (lista/drawer 9.75/impostos) + `/payments/v2` Inertia. A camada de **contas contábeis** Blade é separada.
- **Critério de desligamento:** `resource('account')`, `account-types`, `payments`, `expenses` desligados; balancete e cash-flow nativos no cockpit.
- **Dependência:** depende de Vendas (1) + Compras (5) pra fonte de lançamentos. Costura o caixa ao contábil.

### Onda 7 — Configurações, admin & documentos `domínios K + L` · coexiste · ≈55 fn
- **Adversário [CD]:** Stripe Settings + Vercel — config é **raramente visitada, alto risco**. Régua: **busca + agrupamento claro**, defaults sãos, nunca um muro de 200 toggles AdminLTE.
- **Absorve:** business-settings, business-location (+settings), invoice-layouts, invoice-schemes, tax-rates, group-taxes, printers, notification-templates, types-of-service, backup, roles, users, sign-in-as-user, note-documents/mídia.
- **Já vivo em React:** Gerenciador de Módulos (substituto do AdminLTE quebrado) + preferências de tema/sidebar.
- **Critério de desligamento:** cada `resource()` de settings desligado por grupo; `settings_custom_labels.blade` (37 KB!) reescrito como tela de busca.
- **Dependência:** tax-rates/invoice-schemes são pré-requisito fiscal das Ondas 1 e 6 — porém o Blade serve até serem portados. Baixa frequência → cabe aqui.

### Onda 8 — Relatórios, a represa final `domínio J` · só Blade · ≈45 fn
- **Adversário [CD]:** Metabase + Stripe Sigma — relatório é **resposta a uma pergunta**, não export de tabela. Régua: **filtro vivo + visual que conta a história**, drill-down, e export que o contador aceita.
- **Absorve:** lucro/perda, estoque (valor/expiry/lote), purchase-sell, customer-supplier, fiscal (GST/tax), vendedor (comissão/total), trending, registro, despesa, items-report… (≈45).
- **Já vivo em React:** nada — 100% Blade. Mas **não pode vir antes**: relatório que lê dado de domínio não-migrado mente.
- **Critério de desligamento:** todos os `/reports/*` desligados; números batem com os domínios já migrados (1–7) — **prova de integridade da rota inteira**.
- **Dependência:** **depende de TODAS as ondas 1–7.** É a represa: só enche quando os afluentes migraram. **É aqui que a rota "só para depois de todas migradas".**

### Onda 9 — Acesso & onboarding `domínio A` · coexiste · ≈10 fn
- **Adversário [CD]:** WorkOS + Linear (auth) — a porta da frente. Régua: **login limpo, social, sem AdminLTE**. Baixa frequência (faz-se 1×), por isso tarde — mas é a primeira impressão.
- **Absorve:** login, register, password reset, business-register (+ checks), social-auth (Google/Microsoft), install wizard.
- **Já vivo em React:** `/login` canônico + social já existem; `/login/old` e register Blade ainda vivos pela transição.
- **Critério de desligamento:** `/login/old` removido; register e password reset em React; `auth/*.blade` e `install/*` lápide.
- **Dependência:** independente — pode rodar em paralelo a qualquer onda. Posta tarde só por prioridade de balcão.

### Onda 10 — Desligamento & prova de zero-Blade · gate de honestidade
- **Adversário [CX] — o adversário permanente:** não uma tela de referência, e sim o **red-team do processo**: *"qual route Blade ainda responde escondido atrás do React?"* (§3).
- **Faz:** grep no `web.php` por `resource()` e `view()` Blade vivos; cada um vira lápide ou 302. **Gate de CI: 0 view AdminLTE servida em rota autenticada.**
- **Prova:** smoke `/_smoke-probe` + visual-regression (US-GOV-013) atravessam todas as rotas sem cair em layout legado.
- **Critério de desligamento:** **contador de routes Blade vivos = 0.** A rota PAROU. Antes disso, nenhuma onda anterior pode ser chamada de "concluída".
- **Dependência:** depende de 1–9. É o portão que transforma 9 entregas em uma migração de verdade.

## §3 · O adversário em duas camadas

"Ondas com adversários" tem **dois sentidos**, e a rota precisa dos dois. Confundi-los é como o sistema vira número que ninguém questiona.

| | **[CD] Adversário da onda — o comparável** | **[CX] Adversário permanente — o chato fixo** |
|---|---|---|
| **O quê** | O melhor-da-classe que cada onda precisa **bater** (Square, Attio, Linear, Ramp, Mercury, Metabase…). Régua de design do **F1.5**: a tela migrada é pontuada nas 15 dimensões. | O red-team do **processo**, não da tela (já desenhado em `O Adversário Permanente`). Ataca a honestidade: route fantasma, "migrado" que coexiste, número que não bate com a fonte. |
| **Pergunta** | *"Essa tela está tão boa quanto o adversário dela?"* | *"Onde a rota está cega? Qual Blade ainda responde?"* |
| **Output** | nota ≥80 (ou ≥9 no método KB-9.75). Trava a onda no visual. | furo → proposta de gate/ADR. **É quem garante a Onda 10.** Relatório limpo demais é suspeito. |

Alinhado a [ADR 0276](../../decisions/0276-decisao-pelo-fluxo-classes-pares-adversariais.md) (decisão pelo fluxo / pares adversariais) e [ADR 0107](../../decisions/0107-emendation-0104-visual-comparison-gate-f3.md) + [ADR 0114](../../decisions/0114-prototipo-ui-cowork-loop-formalizado.md) (gate visual F1.5/F3).

## §4 · Fora do domínio — erradicar, não migrar

**Restaurante / Mesas (domínio M) — `Restaurant\*`, ≈18 funções.** Mesas, modificadores, cozinha (KDS),
pedidos e reservas são herança do UltimatePOS genérico. **Não pertencem a uma gráfica/oficina** (Larissa,
Martinho). Migrar seria carregar peso morto — a mesma classe de erro da **locação de caçambas** que [W]
mandou erradicar (alucinação, não legado).

- **Recomendação:** remover do menu e do roteador, **não enfileirar onda**.
- **Gate Tier 0:** confirmar com [W] antes de deletar views — pode haver tenant que use (improvável no piloto). Seguir [ADR 0099](../../decisions/0099-project-legacy-discovery-pre-deletion.md) (legacy discovery pré-deleção).

## §5 · Verificação contra `@main` (esta sessão)

Censo e contrato **conferidos contra o repo real** (`routes/web.php`, `resources/views/`) — não transcrição cega:

- **653** arquivos `.blade.php` em **67** pastas de view (doc F0 dizia ≈655 — confirmado ≈).
- Pares de coexistência **todos presentes** no `routes/web.php`:
  - `/login/old` Blade vivo + `/login` canônico — confirmado.
  - `/home` → 302 `/ia/dashboard` e `/dashboard-legacy` resta — confirmado.
  - `/cliente` drawer (ADR 0188) + `resource('contacts')` Blade — confirmado.
  - `/products/unificado` Inertia — confirmado.
  - `/vendas/caixa` Inertia + `resource('cash-register')` Blade — confirmado.
  - `/payments/v2` Inertia ⊕ `resource('payments')` Blade — confirmado (o próprio `web.php` já chama "Wave Blade T1 Migration B").
  - `reports` — 43 menções de rota (doc dizia ≈45) — confirmado ≈.
- Famílias `resource()` confirmadas: `pos`, `sell-return`, `cash-register`, `contacts`, `products`, `purchases`, `stock-adjustments`, `stock-transfers`, `account`, `expenses`, `payments`.

## §6 · Como esta rota vira fila

1. Esta Etapa 0 (este doc) = **censo + contrato**. Nada commitado vira execução sem [W].
2. As 10 ondas viram **backlog rastreado** como `US-MWART-NNN` (uma por onda) — ver [SPEC.md](SPEC.md).
3. Cada onda roda o ciclo **MWART** ([ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md)): [CC] produz F1 no Cowork → [CD] roda F1.5 contra o **adversário da onda** → [CL] porta pro repo (5 fases) → [CX] (Onda 10) só assina quando o contador de routes Blade vivos chega a **zero**.
4. A decisão de adotar o **contrato de completude** + ordenação + erradicação é canon em [ADR 0277](../../decisions/0277-rota-migracao-blade-ondas-completude.md) (aceita 2026-06-13).

---
*Oimpresso ERP · [CC] · migração do backbone Blade UltimatePOS em ondas governadas. Contagens ≈ por família de controller, lidas de `routes/web.php@main`. Estimates recalibradas — [ADR 0106](../../decisions/0106-recalibracao-velocidade-fator-10x-ia-pair.md).*
