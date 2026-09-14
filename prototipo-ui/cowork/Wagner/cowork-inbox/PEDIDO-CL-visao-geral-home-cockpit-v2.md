# PEDIDO [CC] → [CL] — "Visão geral": `/home` do Soft wrapper para Cockpit V2

> [CC] 2026-08-27 · destino [CL] (F3) · aprovação [W]
> **Lido no `main` NESTE turno** (trees `33425fc9c9bc` e `8d489de20207`, 2026-08-27T16:39–17:22Z): `resources/js/Pages/Home/Index.charter.md` (v2, integral), `resources/js/Pages/Home/Index.tsx` (integral), tree de `resources/js/Pages/**`. **Não li neste turno** (e por isso não afirmo estado): `HomeController.php`, `resources/views/home/index.blade.php`, `home/partials/*`, `public/js/home.js`, `routes`, `permissions.php`, `AppShellV2.tsx`.
> F1 correspondente: `dash-legacy-page.jsx` (Cowork, rota `dash-legacy`, atalho "Visão geral" em `data.jsx:19`) — 365 linhas, roda dentro de `oimpresso.com.html`, sem `.html` nem rota nova.
> Não commitei, não abri branch, não abri PR. As tools de git aqui são leitura.

---

## 1. Qual é a tela em produção

| | |
|---|---|
| Rota | `/home` (landing pós-login) |
| Componente | `resources/js/Pages/Home/Index.tsx` |
| Charter | `resources/js/Pages/Home/Index.charter.md` — `status: live`, `tier: A`, `charter_version: 2`, `related_prototype: n/a` |
| Estado hoje | **F6 Soft wrapper** (US-DASH-001): 8 KPI cards em 2 grupos, filtro de loja, banner de fallback. Sem período, sem gráfico, sem grade. |
| Fallback | `?legacy=1` → `view('home.index')` Blade com ECharts + widgets pluggable. **Contrato duro: continua funcionando.** |
| Legado que o F1 reconstrói | `resources/views/home/index.blade.php` + `home/partials/*` + `public/js/home.js` + `HomeController@indexLegacy`, servido em `/dashboard-legacy?legacy=1` (`name home.legacy`) |

O F1 **não** é um patch do Soft: é o **Rewrite Cockpit V2** que o próprio charter v2 já agendou. Ele destrava, de uma vez, três itens que hoje estão em "Backlog (não no escopo F6 Soft)":

- **US-DASH-002** — charts em Inertia. O charter diz literalmente "Rewrite Cockpit V2 wave (F1→F4 com protótipo Cowork)". Este é o protótipo.
- **US-DASH-004** — KPI com filtro de datas + loja persistido (hoje o range é fixo no FY corrente).
- **US-DASH-005** — stock alert + dues migrados das DataTables Blade.

**US-DASH-003** (widget registry pluggable React) fica **fora**: o F1 declara isso num `Alert` na própria tela e não porta os 4 slots.

---

## 2. Mapa: seção do F1 → componente DS → o que fazer no repo

| # | Seção no F1 | Componente DS usado | Ação no repo | US |
|---|---|---|---|---|
| 1 | Header "Visão geral" + 3 stats tabulares (vendas · a receber `tone:warn` · despesas) | `PageHeader` | **Reusar** `Components/shared/PageHeader` (ADR 0180, já é o canon do Soft) | — |
| 2 | Barra de período (presets Dia/Semana/Mês + De/Até) | `PeriodBar` (+ `DatePicker`) | **Criar** no repo se não existir equivalente; substitui o range fixo do FY | **004** |
| 3 | `Select` Loja | `Select` | **Reusar** o do Soft — `all_locations` + `is_admin`, partial reload `only:['totals']` já implementado em `Index.tsx` | — |
| 4 | 4 KPI: hero **Líquido** com sparkline · Vendas · A receber · Despesas | `KpiCard` (`hero`/`spark`/`tone`) | **Estender** o KpiCard do repo com `hero` + `spark` | **002** |
| 5 | Painel **Contrapartidas** (Compras · A pagar · Devolução de venda · Devolução de compra) | grid de números mono tabulares | **Criar** — absorve os 4 KPIs do grupo "Compras & Custos" do Soft sem gastar 4 cards | — |
| 6 | Painel **Pendências** (5 linhas clicáveis → aba da grade, com `StatusBadge`) | `StatusBadge` | **Criar** — é a porta de entrada pro stock alert / dues | **005** |
| 7 | **Vendas por dia** (area, 30d) + **Vendas por mês** (bar, ano fiscal, `highlightLast`) | `Chart` | **Criar** — substitui ECharts. **Exige ADR US-DASH-002 antes** (ver §4) | **002** |
| 8 | `TabBar` com 9 grades + `DataTablePro` `density="compact"` + rodapé "N de M linhas" + Exportar CSV | `TabBar`, `DataTablePro` | **Criar** — consome os endpoints AJAX que já existem | **005** |
| 9 | `Drawer` lateral de detalhe da linha | `Drawer` + `DrawerSection` | **Criar** — o Blade abria modal (`.btn-modal → .view_modal`); canon Cockpit V2 é drawer (PT-02) | **005** |
| 10 | `Alert tone="warn"` "Herança do Blade que não foi portada" | `Alert` | **Reusar** — mantém os widgets pluggable declarados como dívida, não escondidos | 003 (fora) |
| 11 | `EmptyState variant="no-perm"` quando falta `dashboard.data` | `EmptyState` | **Reusar** — o Soft hoje mostra um `<section>` com texto solto; padroniza | — |
| 12 | `EmptyState variant="error"` na falha da consulta | `EmptyState` | **Criar** — no Blade o loader girava pra sempre; aqui a falha é dita | — |

### As 9 grades do F1 (§8), com a permissão que cada uma exige

`venc-venda` (`sell.view`) · `venc-compra` (`purchase.view`) · `estoque` (`stock_report.view`) · `validade` (`stock_report.view`) · `pedidos` (`so.view`) · `compras-abertas` (`purchase.view`) · `requisicoes` (`purchase.view`) · `expedicao` (`access_shipping`) · `caixa` (`account.access`).

A aba só existe se o papel tem a permissão — o F1 filtra as abas, não desabilita. **[CL]: validar cada string contra `permissions.php` real; as do F1 saíram do Blade e não foram conferidas por mim neste turno.**

### Fontes de dados

Reusar o que o Soft já usa (`TransactionUtil::getSellTotals` + `getPurchaseTotals` + `getTransactionTotals`, `BusinessLocation::forDropdown`) e os 4 endpoints que o charter manda **não tocar**: `/home/get-totals`, `/home/product-stock-alert`, `/home/purchase-payment-dues`, `/home/sales-payment-dues`. As grades novas consomem esses endpoints; nenhum deles muda de assinatura, senão o Blade legacy quebra.

---

## 3. O que NÃO pode acontecer (Non-Goals + anti-hooks do charter v2, viram GUARD)

- `?legacy=1` **continua devolvendo Blade** — teste 4 do charter. Todo cliente ainda depende.
- Os 4 endpoints AJAX **intactos** — teste do charter, o Blade legacy depende.
- **Tier 0 multi-tenant (ADR 0093, IRREVOGÁVEL)**: `session('user.business_id')` em toda query nova das grades. Teste 5 do charter.
- `totals` mantém os **8 campos canônicos** (teste 6): `total_sell, net, invoice_due, total_expense, total_purchase, purchase_due, total_sell_return, total_purchase_return`. O F1 usa exatamente esses 8 + `net` derivado.
- **Sem mutação inline.** O F1 tem um botão "Lançar pagamento" no rodapé do Drawer: **isso é anti-hook declarado** ("botão criar venda inline → drift"). Ou navega pra tela dedicada, ou sai.
- `?location_id=` em query string, **não em session** (anti-hook explícito).
- Customer redirect preservado (`user_type=user_customer` → `Modules/Crm/.../DashboardController`).
- **Um `<main>` por documento** (AP9) e chain de overflow `min-h-0` (AP10) — o F1 já nasce com `flex:1 1 auto; min-height:0; overflow-y:auto`.
- Sidebar preta nos dois modos (UI-0023). O F1 roda no shell do Cowork; em produção é `AppShellV2`.

---

## 4. Decisões que são de [W], não minhas

1. **Charts entram?** É o gatilho do anti-hook "chart inline sem ADR US-DASH-002 = drift". Sem ADR aprovada, §7 do mapa **não vai**. Com ela, o charter sai de v2 (F6 Soft) para **v3 (Rewrite Cockpit V2)** e `related_prototype` passa de `n/a` para `dash-legacy-page.jsx` — o gate `pt_declarado` casa `/PT-0[1-5]/`, e o arquétipo aqui é **PT-05 (Dashboard)**.
2. **4 KPI + Contrapartidas, ou os 8 cards do Soft?** O Soft entregou 8 cards em 2 grupos (aprovado por [W] em 2026-05-22, caminho B). O F1 propõe 4 cards + um painel de 4 números. É uma reversão parcial de decisão sua — não aplico sem palavra.
3. **9 abas de grade é muito para a onda 1.** Sugiro onda 1 = `venc-venda`, `venc-compra`, `estoque` (as 3 que o `Pendências` aponta e que já têm endpoint AJAX pronto). As outras 6 na onda 2.
4. **"Exportar CSV"** no rodapé da grade é escopo novo, não existe no Blade. Cortar da onda 1 ou abrir US própria.
5. **Widgets pluggable (US-DASH-003)** continuam só no `?legacy=1`, ou ganham registry? O `Alert` do F1 assume que continuam fora.

**Não vai pra produção de jeito nenhum:** os tweaks de protótipo — `Select` "Papel simulado" (4 papéis) e `Switch` "Simular falha". São instrumentos de F1 pra provar os estados `no-perm` e `error`; em produção quem decide é a permissão real e a resposta real do endpoint.

---

## 5. Ordem de PRs sugerida

1. **PR 1 — lei, sem código de tela.** ADR US-DASH-002 + `Home/Index.charter.md` v3 (`related_prototype: dash-legacy-page.jsx`, arquétipo PT-05, backlog reescrito) + `Home/Index.casos.md` (o charter v2 **não tem** casos.md irmão; `scripts/qa/prototipo-readiness.mjs` exige o trio) + `prototipo-ui/contrato/visao-geral.contract.json` (ADR 0286) declarando as seções e a copy literal.
2. **PR 2 — GUARD vermelho de propósito.** Estende `tests/Feature/Home/HomeIndexInertiaTest.php` com os casos novos (período, grades por permissão, falha dita). Reprova enquanto a tela é o Soft — é o ponto.
3. **PR 3 — onda 1 sem gráfico.** Período (US-DASH-004) + KPI/Contrapartidas + Pendências + as 3 grades + Drawer PT-02 + os dois EmptyState. Não toca chart.
4. **PR 4 — onda 2: gráficos** (US-DASH-002, só se ADR aprovada no PR 1).
5. **PR 5 — onda 3: as 6 grades restantes** + decisão de CSV.

**Gates a rodar em cada PR:** `npm run contrato:check -- prototipo-ui/contrato/visao-geral.contract.json` · `node scripts/qa/prototipo-readiness.mjs` · `casos:check` · Pest `HomeIndexInertiaTest` · anchor gates (`anchor-content-required`, `anchor-drift`) · `cowork-ssot-guard.mjs`.

---

## 6. Ponte do build (o F1 em si)

O arquivo é `dash-legacy-page.jsx` no Cowork. **Não reenviar o snapshot de sync de 24/08**: `sync/bundle.manifest.json` (`bundleId 5023b274…99e4d`, `generatedAt 2026-08-24T22:49:15.818Z`, 43 partes, 255 arquivos, `mode: snapshot`) **já é o `active-bundle.json` do `main`** (`scripts/design-sync/state/active-bundle.json:1538`, com `application-report.json` de 2026-08-25T18:24Z). Aplicá-lo de novo é no-op na melhor hipótese e reverte 3 dias na pior. Rodar delta — **em dois lados, não num shell só**:

**Errata (2026-08-27):** os dois comandos abaixo estavam escritos como se rodassem juntos no clone do repo, com `--root .`. Está errado. `gerar-payload-partes.mjs` **roda no lado que tem a árvore viva** — `--root <dir-do-vivo>` é o espelho/export do Cowork, não o checkout do Laravel. Com `--root .` dentro do repo, o gerador emite um payload **do próprio repo** e o delta não significa nada.

1. **Lado do vivo** (quem tem a árvore do espelho Cowork em disco — não sou eu: aqui não há shell). Precisa do manifesto anterior à mão; ele mora no repo em `scripts/design-sync/state/active-bundle.json` (é o `bundle.manifest.json` de 24/08), então copiar pro lado do vivo antes:

```bash
node scripts/design-sync/gerar-payload-partes.mjs \
  --root <dir-do-espelho-cowork> --out sync-novo \
  --previous <copia-do-active-bundle.json>
```

2. **Lado do repo** ([CL], no clone do `main`), com as partes geradas no passo 1:

```bash
node scripts/design-sync/aplicar-payload.mjs sync-novo/payload.part*.json --dry --require-complete-shell
```

Se o passo 1 não tem onde rodar, o caminho é o de sempre: [W] cola o build 1× (zero-toque) ou Issue `cowork-intake` → PR. Não há delta sem árvore viva.

---

## 7. Limites desta entrega

- Escrevi, não executei: nenhum comando acima rodou. Não há teste verde aqui.
- Os paths e nomes de gate vêm de leitura do `main` **nesta sessão** onde marcado no cabeçalho; o resto está marcado como não lido. Se algum path divergir, **recuse o pedido e documente** (§10.4) — prompt stale não se conserta por adivinhação.
- O `charter_version: 2` que li declara `last_validated: "2026-05-22"`. Se alguém tocou `/home` depois disso, minha leitura do estado é boa mas o histórico não é meu.
