---
casos: Histórico de estoque (Kardex) · /products/stock-history/{id}
irmaos: StockHistory.charter.md (lei)
tecnica: Caso de uso = narrativa do operador + critério de aceite verificável (Dado/Quando/Então)
por_que: comportamento é durável — a movimentação que o Kardex audita não muda quando a tela vira aba.
owner: wagner
last_run: "2026-07-21"
---

# Casos de Uso & Aceite — Histórico de estoque (Kardex)

> **Âncora:** `CU-PROD-11` — "Kardex real na tela React" `[must]` do
> [SDD §6.1](../../../../memory/requisitos/Produto/SDD-tela-cadastro-produto-v1.0.md).
> Os UCs derivam do **contrato** (CU-PROD-11), nunca da implementação — teste derivado do código é
> tautológico e trava o desvio em vez de pegá-lo (`proibicoes.md` §5).
>
> **Por que este arquivo nasce agora:** completa o trio da tela (charter existia desde 2026-05-15;
> `casos.md` + teste faltavam — a US-PROD-020 pede `casos.md` das telas críticas, e o gate
> [`casos-coverage-guard.mjs`](../../../../scripts/casos-coverage-guard.mjs) G-1 exige o trio).
>
> **O bug que o trio destrava (fachada → real):** o `StockHistory.tsx` já tinha o
> `<Deferred data="movements">` + tabela desde 2026-05-31, mas o controller (`productStockHistory`)
> **nunca declarava a prop `movements`** no branch Inertia → ela ficava `undefined` pra sempre →
> EmptyState eterno (SDD grade 47 / G-01). O fix (controller passa `movements` via `Inertia::defer`
> + `filters`) entra no **mesmo PR** (failing-first, padrão #4300).
>
> **Status:** ✅ passa (prova no manifesto G-7) · 🧪 teste cita o UC e passa · ⬜ não verificado ·
> ❌ quebrou.

## Rastreabilidade

| UC | Caso de uso | Prio | CU-PROD-11 | Teste | Status |
|----|-------------|------|-----------|-------|--------|
| UC-PSTK-01 | Partial reload retorna a timeline real (venda → saída) | must `[reg]` | item 1/2 | `StockHistoryContratoTest` | ⬜ aguarda CI |
| UC-PSTK-02 | Produto de outro business → 404 (Tier 0) | must `[T0]` | item 4 | `StockHistoryContratoTest` | ⬜ aguarda CI |
| UC-PSTK-03 | `movements` é deferido — ausente no render inicial (`[perf]`) | must `[perf]` | item 1/4 | `StockHistoryContratoTest` | ⬜ aguarda CI |

> **Por que ⬜ e não 🧪:** o teste foi escrito nesta PR mas ainda **não** rodou verde — afirmar
> passagem sem a lane `PHP / Pest (Estoque · MySQL)` provar seria claim sem evidência
> (`proibicoes.md` §"Claim sem evidência"). Vira 🧪 quando o CI da PR fechar verde.

---

## UC-PSTK-01 · Partial reload retorna a timeline real (venda → saída) · `must` `[reg]`
- **Persona:** Larissa / ROTA LIVRE — abre o histórico de uma variação pra entender por que o saldo
  divergiu; precisa ver cada entrada/saída/ajuste, não uma tela vazia que só linka o relatório legado.
- **Aceite:** Dado uma venda `final` de 3 un numa variação/local · Quando o front pede o partial reload
  `only:['movements']` (`GET /products/stock-history/{id}?variation_id=&location_id=`) · Então a prop
  `movements` **existe** (não `undefined`) e traz ≥1 linha com `kind='saida'` e `quantity ≈ -3`.
- **Teste:** [`StockHistoryContratoTest`](../../../../tests/Feature/Produto/StockHistoryContratoTest.php) — `UC-PSTK-01 · partial reload retorna a timeline real (venda → saída)`.
- **Contrato:** `CU-PROD-11` item 1 — *"Controller passa `movements` (JSON) via `Inertia::defer` … Hoje `undefined`"* + item 2 (cor semântica por sinal).
- **Regressão que defende:** o `StockHistory.charter.md` (Goals) promete "Tabela cronológica (deferred)".
  Hoje nada prova que o servidor **entrega** a timeline — o `Inertia::render` só passava
  `product/variations/businessLocations/permissions`. Se o defer sair de novo, a tela volta a ser
  fachada e nada avisa.
- **Status: ⬜** — aguarda a lane `PHP / Pest (Estoque · MySQL)` da PR.

---

## UC-PSTK-02 · Produto de outro business não vaza (Tier 0) · `must` `[T0]`
- **Persona:** qualquer tenant. O pior bug do projeto é o histórico de estoque de um business vazar pro outro.
- **Aceite:** Dado `GET /products/stock-history/{id_de_outro_business}` · Quando abro · Então **404** —
  nenhuma variação/movimentação alheia chega ao payload.
- **Teste:** [`StockHistoryContratoTest`](../../../../tests/Feature/Produto/StockHistoryContratoTest.php) — `UC-PSTK-02 · produto de outro business retorna 404 (multi-tenant Tier 0)`.
- **Contrato:** `CU-PROD-11` item 4 `[T0]` — *"Kardex só do business"* + [ADR 0093](../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md).
- **Regressão que defende:** é o `it('Controller cross-tenant retorna 404')` que o charter promete no
  §Pest GUARD e **nunca existiu**. O `Product::where('business_id',…)->findOrFail($id)` já escopa —
  este teste ancora esse invariante pra ele não regredir num refator do método.
- **Status: ⬜** — aguarda CI.

---

## UC-PSTK-03 · `movements` é deferido — não vem eager no render inicial · `must` `[perf]`
- **Persona:** Larissa em 1280px — a tela tem que abrir rápido; a timeline (query pesada com 6 joins)
  não pode travar o primeiro paint.
- **Aceite:** Dado `GET /products/stock-history/{id}` com `X-Inertia` (render inicial, sem partial) ·
  Quando o payload volta · Então **não** contém `movements` (é deferido), mas **contém** `filters`
  (barato, eager) — senão o front crasha lendo `filters.variationId`.
- **Teste:** [`StockHistoryContratoTest`](../../../../tests/Feature/Produto/StockHistoryContratoTest.php) — `UC-PSTK-03 · movements é deferido — ausente no render Inertia inicial`.
- **Contrato:** `CU-PROD-11` item 1 (via `Inertia::defer`) + item 4 `[perf]` — *"`defer` < 600ms"*.
- **Regressão que defende:** se alguém trocar `Inertia::defer(fn () => …)` por avaliação eager, a query
  de 6 joins passa a rodar em todo primeiro paint (o incidente D-14 que motivou `inertia-defer-default`).
  Este UC trava a prop como deferida.
- **Status: ⬜** — aguarda CI.

---

## Backlog de casos (sem id — entram quando tiverem teste que os defenda)

> Regra G-2: UC declarado sem teste citando o id = órfão = violação no `casos-gate`.

- **Hero KPIs entrada/saída 30d** — `CU-PROD-11` item 3. O charter pede "Estoque atual · Entrada 30d ·
  Saída 30d"; o tsx hoje resume o **período carregado** (entradas/saídas/ajustes/saldo), não uma janela
  fixa de 30d. Vira UC quando a janela 30d for contrato (hoje `getVariationStockHistory` não filtra data).
- **`ref` clicável leva à OS/Compra/Venda** — `CU-PROD-11` item 1 (ref clicável). Hoje `refNo` é texto;
  o link pra transação de origem é paridade de navegação — smoke, não Pest de endpoint.
- **Append-only sob carga (GET não muta)** — `CU-PROD-11` item 2. O branch Inertia é read-only por
  construção; um teste que prove "o GET não escreve em `VariationLocationDetails`" (ao contrário do
  branch ajax legado) merece id próprio quando o path legado for aposentado.

---

## Refs

- Charter (lei): [`StockHistory.charter.md`](StockHistory.charter.md) — `v1`, `draft`, `last_validated: 2026-05-15`
- Irmão que fechou o trio primeiro: [`SellingPrices.casos.md`](SellingPrices.casos.md) ([#4300](https://github.com/wagnerra23/oimpresso.com/pull/4300))
- SDD (âncora dos CU): [`SDD-tela-cadastro-produto-v1.0.md`](../../../../memory/requisitos/Produto/SDD-tela-cadastro-produto-v1.0.md) §6.1 CU-PROD-11
- Controller: `app/Http/Controllers/ProductController.php` — `productStockHistory()` (branch Inertia + defer)
- Gate: `scripts/casos-coverage-guard.mjs` (G-1/G-2/G-5 — ADR 0264) · lane `PHP / Pest (Estoque · MySQL)`
