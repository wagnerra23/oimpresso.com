---
id: resources-js-pages-jana-index-charter
page: /ia
component: resources/js/Pages/Jana/Index.tsx
related_prototype: prototipo-ui/cowork/chat-jana.jsx
owner: wagner
status: live
last_validated: "2026-05-18"
parent_module: Jana
parent_adr: memory/decisions/0052-memoria-jana-3-angulos-faturamento.md
related_adrs: [26, 31, 35, 36, 52, 93, 94, 107, 114]
related_us: [US-COPI-010, US-COPI-011, US-COPI-012, US-COPI-146, US-COPI-148]
related_charters:
  - resources/js/Pages/Jana/Chat.charter.md
  - resources/js/Pages/Jana/Cockpit.charter.md
related_specs:
  - memory/requisitos/Jana/SPEC.md (US-COPI-010, US-COPI-011, US-COPI-012)
runbook: memory/requisitos/Jana/RUNBOOK-index.md
tier: A
charter_version: 3
permissao: copiloto.access
---

# Page Charter — `/copiloto/dashboard`

> **Status:** `live` — implementada e em uso prod biz=1 desde 2026-04. Charter retroativo Wave M 2026-05-16.

---

## Mission

Visão consolidada das **metas ativas do business** com farol verde/amarelo/vermelho, série temporal últimas 12 janelas e projeção linear. Substitui análise manual em planilha — dono/gestor abre, vê rumo, decide.

Audiência primária: **dono/gestor de business** (Wagner, Larissa). Acesso `business_id` scoped — superadmin vê escopo via switch.

---

## Goals

- **Header sticky área "JANA"** compartilhado com Chat.tsx — dot da área (hue 220) + label "JANA" à esquerda + tabs `Dashboard | Chat` (navegação Inertia entre `/jana/dashboard` e `/jana`). Componente `JanaAreaHeader` em `Pages/Jana/_components/`. Espelha `prototipo-ui/_cowork-export-2026-05-15/app.jsx` Header function (L247-336 do protótipo Cockpit). Ver `memory/requisitos/Jana/Chat-header-tabs-visual-comparison.md` (gate F1.5).
- Render < 200ms p95 com `Inertia::defer()` em `metas` paginated + `apuracoes` 12 janelas
- Farol calculado server-side via `MetricasApurador::farol(meta, periodo)` — frontend só consome
- Click em meta → drilldown `/copiloto/metas/{id}` (US-COPI-011) com série completa
- CTA "Conversar com a Jana" abre `Chat.tsx` com contexto da meta selecionada
- **Drill-down "de onde vem esse número" (v3 — 2026-08-07):** card de análise abre drawer
  (`_components/JanaDrillDrawer.tsx`) com **Fonte** (tabelas · regra do recorte · método que
  calcula) + **Escopo** (`business_id` da sessão). Um KPI só é clicável quando existe análise
  do **MESMO dado** — "ticket médio não abre faturamento". Hoje 2 dos 4 KPIs abrem
  (Faturamento mês → Faturamento; Inadimplência total → Inadimplência); Ticket médio e PIX hoje
  não têm análise do mesmo dado e permanecem estáticos. Âncora:
  `prototipo-ui/cowork/jana-merge.jsx :640` (`JmDrillDrawer`) + `:887` (`JM_KPI_DRILL`).

## Non-Goals

- ⛔ Edição inline de meta (vai em `/copiloto/metas/{id}/edit` — US-COPI-013)
- ⛔ Criação de meta (vai em chat US-COPI-004 ou wizard US-COPI-012)
- ⛔ Comparativo entre business (superadmin tem `/copiloto/admin/governanca`)
- ⛔ **Análise "Frota" do protótipo** — decisão [W] 2026-08-07: **não construir**. Dois motivos
  independentes, cada um suficiente: (a) o card do protótipo rotula `Locadas` / `caçambas`, e
  `memory/dominio/oficina-auto.md` declara `forbidden_ui_terms: ["locacao","cacamba"]` (match sem
  acento/caixa) enforçado pelo `dominio-gate` **required** — construir literal reprova no CI e
  reintroduz a locação erradicada pela [ADR 0265](../../../../memory/decisions/0265-oficina-reparo-erradica-locacao.md);
  (b) a fonte (`Modules/OficinaAuto/Entities/Vehicle`) é OficinaAuto — Martinho biz=164 —, não
  faz sentido pra ROTA LIVRE (vestuário). Reabrir exige decisão [W] nova.

## UX targets

- 1 viewport scroll desktop 1280px (ROTA LIVRE monitor)
- Mobile responsivo — stack vertical cards, swipe horizontal não-essencial
- Dark mode obrigatório (`@/Layouts/AppShellV2` default)
- Toast `sonner` em mutations (arquivar meta)
- `KpiCard` shared component pra cada meta (consistência cross-module)
- `EmptyState` shared component se 0 metas — CTA "Pergunte algo a Jana"
- **Demo polish (v2 — CYCLE-06 G3):** badge gradient `JANA V2` violet→fuchsia→pink no header, KPI strip 3 colunas (Memória ativa / Última conversa / Brain B hoje — placeholders pra Brain B preencher futuro via `Inertia::defer`), card "Próxima ação sugerida" violet-tinted (mock didático), empty state com ícone `Sparkles` + CTA `Pergunte algo a Jana` em vez de texto plano

## Anti-hooks

- ⛔ Re-fetch polling de apuracoes — usa `Inertia::defer()` server-side
- ⛔ Cálculo de farol no frontend — fonte autoritativa `MetricasApurador`
- ⛔ Mutation otimista sem rollback — usar `router.patch` com `onError`
- ⛔ **Citar no drawer de drill-down fonte/serviço que não existe no repo.** O drawer se chama
  "de onde vem esse número" — nome fictício ali é mentira com selo de autoridade. O protótipo
  lista `AnaliseInadimplenciaService`/`AnaliseFaturamentoService`/etc, e **nenhuma existe**
  (medido 2026-08-07: `git grep -l 'Analise.*Service' -- Modules/ app/` → rc=1). A fonte vem lida
  do código real (`app/Services/Sells/SellsCockpitAggregator.php`). Mexeu no aggregator, mexe no
  `JANA_DRILL_FONTES` no mesmo PR.
- ⛔ **Prometer no botão do drawer o que a rota não entrega.** `ChatController@novaConversa` não
  aceita pergunta inicial e `Chat.tsx` não lê query param (medido 2026-08-07) — por isso o CTA diz
  "Conversar com a Jana", não "Perguntar sobre isso". Semear a pergunta é PR próprio (backend + Page).

## Skills relevantes

`brief-first` (Tier A) · `multi-tenant-patterns` (Tier A) · `inertia-defer-default` (Tier B) · `mwart-process` (Tier A)

## Charter version log

- v1 (2026-05-16) — Charter retroativo Wave M boost Modules/Jana 64→78
- v3 (2026-08-07) — Drill-down "de onde vem esse número" (`_components/JanaDrillDrawer.tsx`) nos 4
  cards de análise + nos 2 KPIs que têm análise do mesmo dado. Fonte lida do código real, não dos
  nomes fictícios do protótipo (2 anti-hooks novos). Non-Goal novo: análise "Frota" **não** será
  construída (decisão [W]; `forbidden_ui_terms` + OficinaAuto-only). Fatia B do pacote
  `JANA-FUSAO-2026-08-06`, US-COPI-148.
- v2 (2026-05-16) — Polish demo CYCLE-06 G3: badge gradient `JANA V2`, KPI strip 3 colunas, card "Próxima ação sugerida", empty state polish (ícone Sparkles + CTA "Pergunte algo a Jana"). Logic chat preservado (apenas UI surface — ChatController intacto). Ver `memory/requisitos/Jana/demo-pilot-2026-05-16/SCREENSHOT-GUIDE.md`
