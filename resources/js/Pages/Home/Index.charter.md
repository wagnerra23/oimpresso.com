---
page: /dashboard-legacy
component: resources/js/Pages/Home/Index.tsx
owner: wagner
status: live
last_validated: "2026-09-04"
parent_module: Dashboard
parent_spec: memory/requisitos/Dashboard/SPEC.md
related_runbook: memory/requisitos/Dashboard/RUNBOOK-home-index.md
# Declarado porque o resolver procura por NOME em `memory/requisitos/<mod>/`, e o
# `mod` desta tela é `Home` enquanto o doc vive em `Dashboard/` — sem declarar, a
# porta viva reporta "visual-comparison ausente" mesmo com o arquivo existindo.
# Proveniência explícita > adivinhação por nome.
related_visual_comparison: memory/requisitos/Dashboard/Index-visual-comparison.md
related_adrs: [93, 94, 101, 104]
related_us: [US-DASH-001, US-DASH-004, US-DASH-005, US-DASH-006]
related_prototype: prototipo-ui/cowork/dash-legacy-page.jsx (Cockpit V2 · PT-04 Dashboard)
tier: A
charter_version: 6
---

# Page Charter — /home

> **Status v6 (2026-09-04):** entra o painel **Pendências** — o atalho que estava em §Backlog
> desde a v4 com a nota *"entra se [W] quiser"*. [W] pediu em 2026-09-04. São as **5 abas da
> âncora** (`dash-legacy-page.jsx`, const `PENDENCIAS`), não as 8: pedido de venda, ordem de
> compra e requisição são fluxo em andamento, não pendência.
>
> **Duas decisões de engenharia que o painel carrega, e o porquê de cada uma:** (a) o número vem
> do MESMO `linhas()` que serve a grade — um segundo predicado de "pendente" drifta do primeiro e
> o painel passaria a prometer um número que a aba clicada não mostra, risco que a própria nota
> do backlog já nomeava; (b) a prop é `Inertia::defer`, então as 5 contagens ficam FORA do
> first-paint, que é o alvo de ≤ 800ms deste charter.
>
> **O que NÃO veio da âncora, de propósito:** o selo de severidade das linhas
> (`payment/overdue`, `os/atrasada`). Aqueles rótulos descrevem um predicado mais estrito do que
> o que a aba consulta — `titulosVencendo` traz tudo que vence em até 7 dias, vencido ou não.
> Carimbar "vencido" num conjunto que inclui o que ainda vai vencer é rotular errado. Severidade
> honesta seria um segundo predicado: decisão [W], não wiring.
>
> **Status v5 (2026-08-28):** o **Blade legado saiu**. Foram removidos `views/home/index.blade.php`
> (1.436 ln), os 8 partials de KPI (já órfãos — zero includes), `public/js/home.js` **e a cópia
> byte-idêntica em `dist/js/home.js`**, o `HomeController::indexLegacy()`, o `__chartOptions()`
> que só ela chamava, o ramo `?legacy=1` e o banner "Abrir versão completa".
>
> **O que autorizou a remoção:** as ondas 2 e 3 estão em produção (deploy `success` 2026-08-28
> 12:35Z), e o último motivo restante pra abrir o Blade — os widgets pluggable de outros módulos
> (US-DASH-003) — foi **medido como ponto de extensão VAZIO**: `dashboard_widget()` tem
> **zero produtores nos 32 `DataController`** do repo (controle positivo: a mesma sonda acha
> `user_permissions` em vários). Sair do Blade não tirou capacidade de ninguém.
>
> **Preservados de propósito** (têm consumidor fora do dashboard): `/calendar`, os 4 endpoints
> AJAX, o customer redirect, `home/notification_modal.blade.php` (HomeController) e
> `home/todays_profit_modal.blade.php` (`@include` em `layouts/app` e `layouts/restaurant`).
> Highcharts/`vendor.js` **intocados**: `CommonChart` tem 5 consumidores PHP (Report, Repair×2,
> a classe, e o Home que saiu) e `vendor.js` carrega do partial global de layout.
>
> **Status v4 (2026-08-27):** Onda 3 — entram as **abas de grade** e o **drawer de detalhe**
> (US-DASH-005). É a última capacidade que faltava pro Blade legado poder sair de cena.
>
> **Medição que definiu o escopo (2026-08-27, no arquivo, não no runtime):** o Blade tem
> **8 grades**, não 5 nem 9. Cinco aparecem sempre; três estão atrás de um setting do
> business (`enable_product_expiry`, `enable_purchase_order`, `enable_purchase_requisition`)
> — é por isso que uma sondagem de runtime num business vê 5 e noutro vê 8. O protótipo
> desenha 9; a nona ("Fluxo de caixa") **não tem fonte no Blade** e por isso não existe aqui.
> Todas as 8 vivem dentro do `@if(can('dashboard.data'))` que abre na linha 369 e fecha na
> 1013, e cada uma tem ainda o seu próprio `@can` — as duas camadas são reproduzidas.
>
> **Status v3 (2026-08-27):** Rewrite Cockpit V2, ancorado no protótipo `dash-legacy-page.jsx`.
> Entra PeriodBar (US-DASH-004), o Líquido vira hero e as contrapartidas saem dos cards pro painel.
> Cor crua R1 no `Index.tsx`: 48 → 0 (só tokens do DS).
>
> **Histórico:** o v2 (2026-05-22, "caminho B", 8 KPI em 2 grupos) foi declarado por [W] em
> 2026-08-27 como tentativa descartada — "minha tentativa errada do react". Fica registrado
> como o que foi verdade naquela data, não como decisão revertida à revelia.
> Persona: **Larissa [L]** (dona PME vestuário ROTA LIVRE biz=4) + qualquer user logado UPOS. Desktop ≥1024px.
>
> **Read-only:** todo dado vem de queries existentes do core UltimatePOS (`TransactionUtil::getSellTotals` + `getPurchaseTotals` + `getTransactionTotals`, `BusinessLocation::forDropdown`). Mutations (registrar venda etc.) continuam nas telas dedicadas (`/sells/pos`, `/expense`, etc.).
>
> **Fallback Blade REMOVIDO (2026-08-28):** `?legacy=1` não serve mais nada — a query virou inerte
> e cai na própria tela React (teste `?legacy=1 é inerte`). Não há mais `view('home.index')`.

---

## Mission (1 frase)

Responder **"como foi o período"** numa tela só: o usuário escolhe a janela, o Líquido responde em destaque, e as contrapartidas ficam à vista sem gastar um card cada. Fallback discreto pra Blade legacy enquanto gráficos e grades não migram.

---

## Goals — Features (faz)

- AppShellV2 layout com breadcrumb único `Visão geral`
- Saudação discreta ("Bem-vindo, {primeiro_nome}") no topo, sem ocupar a faixa nobre da tela
- **PeriodBar** (US-DASH-004): presets Dia / Semana / Mês + intervalo De/Até. Janelas ROLANTES
  relativas a hoje — dia = hoje..hoje · semana = hoje-6 · mes = hoje-29 — resolvidas no SERVIDOR
  (`HomeController::resolvePeriod`). Default = FY corrente: sem ação do usuário, nada muda de valor.
- **4 KPI**, com o Líquido em destaque (hero): Líquido no período · Vendas · A receber · Despesas
- **Contrapartidas**: Compras · A pagar · Devolução de venda · Devolução de compra, em números mono
  tabulares — os 4 do outro lado do caixa, sem gastar 4 cards
- Header "Visão geral" + linha de stats (vendas · a receber · despesas) do mesmo período
- Filtro loja quando `all_locations.length > 1 && is_admin` — e o `location_id` CHEGA aos três
  `TransactionUtil::` (até 2026-08-27 a UI mandava o parâmetro e o controller o ignorava)
- Permission gate `dashboard.data` — sem permission, KPI cards somem (shell minimal)
- Customer redirect preservado (`user_type=user_customer` → `Modules/Crm/Http/Controllers/DashboardController`)
- Multi-tenant Tier 0 ADR 0093 IRREVOGÁVEL — `session('user.business_id')` em todas queries
- **Abas de grade** (US-DASH-005): as 8 do Blade, cada uma com o gate REAL medido —
  `venc-venda` (`sell.view` ou `direct_sell.view`) · `venc-compra` (`purchase.view`) ·
  `estoque` (`stock_report.view`) · `validade` (`stock_report.view` + `enable_product_expiry`) ·
  `pedidos` (`so.view_all` ou `so.view_own`) · `compras-abertas` (`purchase_order.view_*` +
  `enable_purchase_order`) · `requisicoes` (`purchase_requisition.view_*` +
  `enable_purchase_requisition`) · `expedicao` (`access_shipping`,
  `access_pending_shipments_only` ou `access_own_shipping`). Sem permissão a aba **SOME** —
  não aparece desabilitada, que é o que o protótipo e o Blade fazem
- Só a aba ABERTA consulta o banco, via `Inertia::defer` — 8 grades de uma vez seriam 8
  queries por render contra o alvo de first-paint ≤ 800ms
- **Drawer de detalhe** (`ui/sheet`) ao clicar na linha, com os campos da linha selecionada.
  Nas duas abas de título ele oferece **navegação** pra `/payments/add_payment/{id}` — rota
  GET, tela dedicada: preserva o atalho que o Blade tinha sem mutação inline
- **Pendências** (v6): painel ao lado das Contrapartidas com as abas que têm algo esperando
  agora — rótulo canônico da aba + total, cada linha um link que troca a aba preservando período
  e loja. Só as **5 da âncora**, só as permitidas, e só as com total > 0 (um zero em "Pendências"
  não é pendência, é ruído). Prop `Inertia::defer`, fora do first-paint
- Estado do período, da loja **e da aba** em QUERY STRING, nunca em session (anti-hook)
- Totais derivados todos do mesmo `getTransactionTotals` + `getSellTotals` + `getPurchaseTotals` — sem AJAX extra

---

## Non-Goals — Features (NÃO faz)

> Anti-alucinação. Cada item vira Pest GUARD test (Non-Goal violado = CI quebra).

- ~~❌ NÃO renderiza charts ECharts — preservados em `?legacy=1`~~ — **entregue** (US-DASH-002):
  o `main` já traz `buildChartsPayload` no controller e `<GraficosVendas>` na tela, ambos deferidos.
  **Reconciliado nesta onda** porque o PR dos gráficos não mexeu no charter: pela regra de
  precedência (teste > casos > charter > SPEC), quem perde se corrige no mesmo PR — e um Non-Goal
  que contradiz código entregue é instrução ativa pra alguém "consertar" a tela removendo o gráfico
- ❌ **NÃO renderiza widgets pluggable** (`moduleUtil->getModuleData('dashboard_widget')`) — segue
  valendo como Non-Goal. O que mudou em 2026-08-28 é só a justificativa, que ficou falsa: **não há
  mais `?legacy=1` onde o mecanismo esteja "preservado"**, e ele não perdeu nada — o ponto de
  extensão tem **zero produtores nos 32 `DataController`** (medido). Backlog US-DASH-003 segue
  aberto; quando existir um produtor, o registry React exige ADR nova
- ❌ **NÃO toca endpoints AJAX** (`/home/get-totals`, `/home/product-stock-alert`,
  `/home/purchase-payment-dues`, `/home/sales-payment-dues`) — rotas e métodos preservados
  intactos. ⚠️ **A razão original caducou:** eles existiam pro `home.js` do Blade, que saiu, e a
  onda 3 acabou servindo as grades por `Inertia::defer` + `GradesService`, **não** por AJAX — então
  hoje são código sem chamador. Aposentá-los é decisão [W] e PR próprio, não carona desta remoção
- ❌ **NÃO toca `/calendar`** (`getCalendar` continua Blade)
- ❌ **NÃO toca customer dashboard** (`Modules/Crm/Http/Controllers/DashboardController`)
- ~~❌ NÃO substitui filtros de data — range fixo no FY~~ — **entregue em 2026-08-27** (US-DASH-004).
  O default segue o FY, então o Non-Goal virou o comportamento-padrão em vez de uma proibição.
- ❌ **NÃO permite mutação** — sem botões "criar venda" inline. Atalhos viram menu / navegação separada.
  O protótipo põe um **"Lançar pagamento"** no rodapé do Drawer; ele viola este Non-Goal e por isso
  virou **link** pra tela dedicada de pagamento, não ação no drawer
- ❌ **NÃO renderiza a aba "Fluxo de caixa"** do protótipo — ela não tem fonte no Blade legado.
  Desenhar aba sem fonte é inventar capacidade
- ❌ **NÃO mostra contagem por aba** (o `count` do protótipo) — seriam 8 queries de `COUNT` por
  render só pra pintar um pill. O total real da aba aberta aparece no rodapé da tabela.
  ⚠️ **O painel de Pendências (v6) NÃO revogou este Non-Goal**, e a distinção não é retórica: o
  pill seria **8** contagens no caminho do **render**; o painel são **5**, e por `Inertia::defer`,
  fora do first-paint. Um `count` por aba continua proibido
- ❌ **NÃO exporta CSV** no rodapé da grade (o protótipo tem o botão) — não existe no Blade legado

---

## UX targets (mensuráveis)

- **First-paint ≤ 800ms** com 4 KPI cards (queries `TransactionUtil::getSellTotals` indexadas por `business_id`)
- **0 erros JS console** (Pest GUARD valida)
- **Larissa entende KPIs em ≤ 5s** — cards com label PT-BR + tom semântico
- **Acesso ao legacy em ≤ 1 clique** — link discreto no rodapé com text claro

---

## Anti-hooks (sinais de drift)

> Quando esta tela "ganhar" funcionalidade, suspeite — fica fácil escorregar pra F6 Hard sem ADR.

- ⚠️ Aparecer **chart NOVO** além dos dois de US-DASH-002 (vendas por dia · vendas por mês) sem ADR
  própria — os dois entregues são o escopo; um terceiro é Rewrite, não refino
- ⚠️ Aparecer **widget de outro módulo** sem registry — drift pra Blade-only break
- ⚠️ Aparecer **botão "criar venda" inline** — drift, KPI screen vira shortcuts
- ~~⚠️ Quebrar contrato "fallback `?legacy=1` continua funcionando" — todo cliente ainda depende~~
  — **contrato encerrado em 2026-08-28** por decisão [W] ("a versão blade vai ter que sumir"), depois
  de as ondas 2 e 3 entrarem em produção e o último motivo (widgets) ser medido como vazio. Fica
  como registro do que era verdade até essa data, não como regra viva
- ⚠️ Aparecer **session storage** para filtros — preferir query string (`?location_id=`)

---

## Contrato visual

> Copy literal + ordem das seções. Verificado em CI por
> `node scripts/contrato-de-tela.mjs --contract prototipo-ui/contrato/dashboard-visao-geral.contract.json --contract-alvo resources/js/Pages/Home`.
> Mudar qualquer string abaixo **quebra o gate de propósito** — copy de tela é decisão, não detalhe.
>
> **Derivado da ÂNCORA**, não da tela: cada string abaixo foi medida em
> `prototipo-ui/cowork/dash-legacy-page.jsx` (render servido localmente + sonda no DOM,
> 2026-08-28). O RUNBOOK §3 manda que o contrato não seja escrito por quem é julgado por ele —
> por isso a fonte é o protótipo, e a ratificação é de [W] no merge.

Ordem das âncoras `data-contract`, de cima pra baixo:

`cabecalho` → `kpis` → `contrapartidas` → `pendencias` → `graficos` → `grades`

| Seção | Copy que a tela precisa mostrar |
|---|---|
| `cabecalho` | `Visão geral` |
| `kpis` | `Líquido no período` · `Vendas` · `A receber` · `Despesas` |
| `contrapartidas` | `Contrapartidas` · `Compras` · `A pagar` · `Devolução de venda` · `Devolução de compra` |
| `pendencias` | `Pendências` |
| `graficos` | `Vendas por dia` · `Vendas por mês` |
| `grades` | `clique para abrir o detalhe` |

⚠️ **`clique para abrir o detalhe` não é enfeite.** É a única coisa na tela que anuncia que a
linha abre o drawer. Ela foi entregue ausente em 2026-08-27 e o `onRowClick` ficou invisível —
por isso a string está no contrato, e não só no protótipo.


## Test plan (Pest GUARD)

> **Reconciliado 2026-08-28 (trio):** esta lista dizia **14** e a suíte tem **18** — faltavam os
> três testes de ordenação/allowlist, entregues no fix de 2026-08-28 (`#6395`) sem passar por aqui.
> Pela regra de precedência (**teste verde > casos > charter > SPEC**) quem perde se corrige no
> mesmo PR, e o perdedor era este charter. Cada item agora cita o **UC** que o defende em
> [`Index.casos.md`](Index.casos.md), e o título de cada teste cita o mesmo id (gate G-2).
>
> ⚠️ O `✅` abaixo é **herdado** desta lista antes da reconciliação e significa "o teste existe e
> passou quando foi escrito" — não é veredito de run. O veredito por-UC é **derivado** do manifesto
> `scripts/casos-test-results.json` (gate G-7) e vive no `casos.md`, onde os 16 UC estão 🧪 até a
> primeira run publicar. Não repita status de run aqui: dois lugares afirmando o mesmo drifam.

Cobertos em `tests/Feature/Home/HomeIndexInertiaTest.php` (6 testes · UC-DASH-01..06):

1. ✅ `UC-DASH-01` · `renderiza Inertia component Home/Index com shape esperado` (user_name, is_admin, can_dashboard_data, totals, endpoints)
2. ✅ `UC-DASH-02` · `customer redirect preservado` (`user_type=user_customer` → 302)
3. ✅ `UC-DASH-03` · `sem permission dashboard.data → totals é null` (shell minimal)
4. ✅ `UC-DASH-04` · `?legacy=1 é inerte — não existe mais fallback Blade` (v5: prova que a query cai na tela React)
5. ✅ `UC-DASH-05` · `Tier 0 multi-tenant — não vaza locations de outro business` — invariante ADR 0093
6. ✅ `UC-DASH-06` · `totals expõe 8 campos canônicos` — guard charter v2 (total_sell, net, invoice_due, total_expense, total_purchase, purchase_due, total_sell_return, total_purchase_return)

Cobertos em `tests/Feature/Home/GradesDoPainelTest.php` (15 testes · UC-DASH-07..16 + UC-DASH-19 — v4 US-DASH-005, ordenação v5, Pendências v6):

7. ✅ `UC-DASH-07` · `aba sem permissão NÃO aparece` — cada grade tem o seu próprio gate
8. ✅ `UC-DASH-08` · `sem dashboard.data NÃO há aba nenhuma` — a camada externa do Blade (linhas 369→1013)
9. ✅ `UC-DASH-09` · `setting desligado esconde a aba condicional` (`enable_product_expiry`)
10. ✅ `UC-DASH-10` · `aba desconhecida na URL cai na primeira PERMITIDA` — e aba sem permissão não é servida
    nem quando pedida explicitamente. **São 2 testes, e o par é o ponto:** só a degradação passaria
    num servidor que aceitasse qualquer aba pedida
11. ✅ `UC-DASH-11` · `Tier 0 multi-tenant — a grade não devolve linha de outro business` — invariante ADR 0093
12. ✅ `UC-DASH-12` · `PARIDADE com o endpoint legado` — mesmo total em `venc-venda` vs `/home/sales-payment-dues`.
    Trava a duplicação de critério que o Non-Goal dos endpoints impõe
13. ✅ `UC-DASH-13` · `Non-Goal GUARD — os 4 endpoints AJAX seguem respondendo`
14. ✅ `UC-DASH-14` · `catálogo declara as 8 grades do Blade — e nenhuma inventada`
15. ✅ `UC-DASH-15` · `ordenacao: coluna da allowlist ordena de verdade` **+** `coluna FORA da allowlist
    nao vira SQL` — 2 testes, positivo e controle negativo. A ordenação já esteve **inerte** em prod
    (fix 2026-08-28): um teste que só checasse a recusa teria ficado verde o tempo todo
16. ✅ `UC-DASH-16` · `ordenaveis() espelha o sortable da ancora — situacao NAO ordena`. Impede a UI
    oferecer ordenação que o servidor recusa; `situacao` é derivada em PHP, não é coluna de banco
17. `UC-DASH-19` · Pendências — **3 testes**: (a) concordância, `pendencias()` é idêntico nos
    DOIS sentidos ao derivado de `linhas()` sobre as 5 abas da âncora, **e** as 3 abas de fluxo
    não entram mesmo com permissão concedida; (b) gate, pendência de aba sem permissão não
    aparece; (c) casca, sem `dashboard.data` a prop nem é registrada. O (a) **não faz skip** de
    propósito — a primeira versão fazia, e pulou na run de 2026-09-04 deixando a invariante
    central sem execução com a lane verde (ver a nota no `casos.md`)

---

## Backlog (não no escopo F6 Soft)

- ~~US-DASH-002 — Charts ECharts em Inertia~~ — **entregue** (vendas por dia + vendas por mês,
  deferidos). O charter só registrou na v4 porque o PR que os entregou não passou por aqui
- **US-DASH-003 — Widget registry pluggable React** — ADR nova obrigatória
- ~~US-DASH-004 — filtro de datas + loja~~ — **entregue 2026-08-27** (PeriodBar + `location_id` nos 3 `TransactionUtil::`)
- ~~US-DASH-005 — Stock alert + dues tabelas DataTables migradas~~ — **entregue 2026-08-27**
  (8 abas + drawer). Os endpoints AJAX do Blade seguem intactos, como o Non-Goal manda:
  o React lê por `app/Services/Dashboard/GradesDoPainelService.php`, que tem query própria
  porque os endpoints legados devolvem HTML dentro das células
- ~~**Pendências** (o painel lateral do protótipo que lista "1 título vencido" e leva pra aba)~~
  — **entregue na v6 (2026-09-04)**, a pedido de [W]. As duas objeções da nota original foram
  respondidas em vez de contornadas: o `COUNT` a mais por render virou `Inertia::defer` (sai do
  first-paint), e o "repete o que as abas já dizem" virou a garantia — o número vem do mesmo
  `linhas()` da grade, então repetir é exatamente o contrato (UC-DASH-19)

---

## Refs

- [memory/requisitos/Dashboard/SPEC.md](../../../../memory/requisitos/Dashboard/SPEC.md) — US-DASH-001
- [memory/requisitos/Dashboard/RUNBOOK-home-index.md](../../../../memory/requisitos/Dashboard/RUNBOOK-home-index.md)
- [memory/requisitos/Dashboard/BRIEFING.md](../../../../memory/requisitos/Dashboard/BRIEFING.md)
- [ADR 0093](../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md) — Multi-tenant Tier 0 IRREVOGÁVEL
- [ADR 0094](../../../../memory/decisions/0094-constituicao-v2-7-camadas-8-principios.md) — Constituição v2
- [ADR 0104](../../../../memory/decisions/0104-processo-mwart-canonico-unico-caminho.md) — Processo MWART canônico
- Pattern Soft wrapper precedente: PR [#1288 Caixa](https://github.com/wagnerra23/oimpresso.com/pull/1288)
- `app/Http/Controllers/HomeController.php` — Controller adaptado
- `resources/views/home/index.blade.php` — Blade legacy preservado (fallback `?legacy=1`)
