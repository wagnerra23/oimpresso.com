---
id: resources-js-pages-jana-index-charter
page: /ia
component: resources/js/Pages/Jana/Index.tsx
related_prototype: prototipo-ui/cowork/jana-merge.jsx
owner: wagner
status: live
last_validated: "2026-08-13"
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
charter_version: 6
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

- **Barra ÚNICA da área Jana** — `JanaAreaHeader` (em `Pages/Jana/components/`) É o `<PageHeader>` canon: título `Jana · Analista IA` + business/`biz=` + "Atualizado HH:MM" (botão de reapuração) na Zona L, `JanaSubNav` no slot `subnav`, ações da tela + primary "Conversar" na Zona R. Compartilhado com Chat.tsx e Memoria.tsx. Ver `memory/requisitos/Jana/Chat-header-tabs-visual-comparison.md` (gate F1.5).
- Render < 200ms p95 com `Inertia::defer()` em `metas` paginated + `apuracoes` 12 janelas
- Farol calculado server-side via `ApuracaoService::farol(meta, agora)` — frontend só consome
- Click em meta → drilldown `/copiloto/metas/{id}` (US-COPI-011) com série completa
- CTA "Conversar com a Jana" abre `Chat.tsx` com contexto da meta selecionada
- **Drill-down "de onde vem esse número" (v3 — 2026-08-07):** card de análise abre drawer
  (`_components/JanaDrillDrawer.tsx`) com **Fonte** (tabelas · regra do recorte · método que
  calcula) + **Escopo** (`business_id` da sessão). Um KPI só é clicável quando existe análise
  do **MESMO dado** — "ticket médio não abre faturamento". Hoje 2 dos 4 KPIs abrem
  (Faturamento mês → Faturamento; Inadimplência total → Inadimplência); Ticket médio e PIX hoje
  não têm análise do mesmo dado e permanecem estáticos. Âncora:
  `prototipo-ui/cowork/jana-merge.jsx` §`JmDrillDrawer` + §`JM_KPI_DRILL` — âncora de SÍMBOLO
  (ref de linha apodrece no 1º refactor, §5 2026-07-26; re-localize com
  `grep -n "JmDrillDrawer\|JM_KPI_DRILL" prototipo-ui/cowork/jana-merge.jsx`).
  _Recibo 2026-08-11: no arquivo versionado (`SYNC` com o vivo, sha256 normalizado
  `057bd8ae081bfd1c…`) os símbolos caem em `:640` e `:887` — as duas refs que a v3 citava
  **conferem**. Ficam como símbolo, não linha, porque o número é que é frágil, não a citação._

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
  _**Consertado na âncora em 2026-08-13** (escopo: só `jana-merge.jsx`, decisão [W]). Em
  2026-08-13, ANTES do conserto, o fonte da âncora contava **`frota` 8× · `caçamba` 7×** e
  ensinava a meta "Utilização de frota", o mapeamento de drill `truck → frota`, o toggle "Frota"
  e a fonte `assets + locações abertas`. Hoje: a meta virou "Conversão de orçamentos", o `truck`
  saiu do `JM_KPI_DRILL` (KPI fica não-clicável, que é a regra da tabela), o toggle saiu, e o
  `cfg` força `frota: false` **depois** do merge do `localStorage` — porque Non-Goal é decisão,
  não preferência do usuário. Como o filtro do Painel é `cfg[a.id] !== false`, o card da Frota
  deixa de ser renderizado._

  _⚠️ **O que NÃO foi consertado, e é achado aberto:** a linha **`Locadas`**, a legenda
  "91 caçambas avulsas" e o KPI "FROTA UTILIZAÇÃO" **não vivem no `jana-merge.jsx`** — a medição
  de 2026-08-13 os atribuiu a ele por engano. Eles nascem em `prototipo-ui/cowork/chat-jana.jsx`
  (`:129`, `:137`, `:91`), que o shell carrega junto e que é **banido como âncora** da Jana
  (§5 2026-08-10, emendado em 2026-08-11: o critério é ser **outra tela** — o cockpit de cobrança
  do Martinho —, não o carimbo de tenant). Mexer nele é mexer em artefato de outro dono: decisão
  [W] separada. O `dominio-gate` segue **sem pegar** qualquer um dos dois: seus
  `forbidden_ui_paths` são `Pages/OficinaAuto` e `OficinaAuto/Database/{Seeders,Migrations}` —
  `prototipo-ui/` não está na lista. Ampliar o gate pra varrer protótipo é decisão [W] com FP
  medido antes ([ADR 0336](../../../../memory/decisions/0336-gates-design-promocao-por-mordida-provada-emenda-0314.md)) —
  e esbarra na exceção legítima "Caçambas" como razão social do cliente (§5 2026-06-09)._

## UX targets

- 1 viewport scroll desktop 1280px (ROTA LIVRE monitor)
- Mobile responsivo — stack vertical cards, swipe horizontal não-essencial
- Dark mode obrigatório (`@/Layouts/AppShellV2` default)
- Toast `sonner` em mutations (arquivar meta)
- `KpiCard` shared component pra cada meta (consistência cross-module)
- `EmptyState` shared component se 0 metas — CTA "Pergunte algo a Jana"
- **Demo polish (v2 — CYCLE-06 G3):** badge `JANA V2` no header, KPI strip 3 colunas (Memória ativa / Última conversa / Brain B hoje — placeholders pra Brain B preencher futuro via `Inertia::defer`), card "Próxima ação sugerida" (mock didático), empty state com ícone `Sparkles` + CTA `Pergunte algo a Jana` em vez de texto plano.
  _A **prescrição de COR** deste bullet foi **revogada por [W] em 2026-08-12** (ver v5). Ela dizia
  `badge gradient violet→fuchsia→pink` e `card "Próxima ação sugerida" violet-tinted` — escala crua
  Tailwind, fora do sistema de token e sem par no escuro. A **estrutura** (badge, strip, card, empty
  state) segue valendo tal como o v2 a definiu; só a **cor** passa a vir de token semântico
  (`primary`/`success`/`warning`/`destructive`)._

## Anti-hooks

- ⛔ Re-fetch polling de apuracoes — usa `Inertia::defer()` server-side
- ⛔ Cálculo de farol no frontend — fonte autoritativa `ApuracaoService::farol`
- ⛔ Segunda barra de header na tela — identidade/ações vivem no `JanaAreaHeader` (PageHeader canon), nunca num `<header>` próprio de componente filho
- ⛔ Mutation otimista sem rollback — usar `router.patch` com `onError`
- ⛔ **Citar no drawer de drill-down fonte/serviço que não existe no repo.** O drawer se chama
  "de onde vem esse número" — nome fictício ali é mentira com selo de autoridade. Até
  **2026-08-13** o protótipo listava `AnaliseInadimplenciaService`/`AnaliseFaturamentoService`/etc,
  e **nenhuma das seis existia** (medido 2026-08-07 e re-medido 2026-08-13:
  `git grep -E '(class|interface) Analise[A-Za-z]*Service'` → rc=1). Nessa data a tabela `FONTE` do
  `jana-merge.jsx` passou a citar `SellsCockpitAggregator::<metodo>`, alinhada ao
  `JANA_DRILL_FONTES`; `churn`, que **não tem** método no back, declara isso em texto e o render
  só veste de `<code>` o que contém `::`. A fonte vem lida do código real
  (`app/Services/Sells/SellsCockpitAggregator.php`). Mexeu no aggregator, mexe no
  `JANA_DRILL_FONTES` **e na tabela da âncora** no mesmo PR.
  _Guard: `prototipo-ui/ancora.mjs` acusa símbolo de backend citado na âncora que não exista no
  repo — e desde 2026-08-13 enxerga também o formato `Classe::metodo` (antes ficava cego nele)._
- ⛔ **Prometer no botão do drawer o que a rota não entrega.** `ChatController@novaConversa` não
  aceita pergunta inicial e `Chat.tsx` não lê query param (medido 2026-08-07) — por isso o CTA diz
  "Conversar com a Jana", não "Perguntar sobre isso". Semear a pergunta é PR próprio (backend + Page).

## Skills relevantes

`brief-first` (Tier A) · `multi-tenant-patterns` (Tier A) · `inertia-defer-default` (Tier B) · `mwart-process` (Tier A)

## Charter version log

- v6 (2026-08-13) — **A âncora consertada**, não só sinalizada. O v5 (#5719) deixou os 3 defeitos
  REGISTRADOS com recibo; este PR os conserta em `prototipo-ui/cowork/jana-merge.jsx` +
  `chat-jana.css`. (1) **P-1** — os 6 `Analise*Service` inexistentes viraram
  `SellsCockpitAggregator::<metodo>`, lidos do `JANA_DRILL_FONTES`; `churn` declara em texto que
  não tem método no back, e o render deixou de vestir de `<code>` o que não é símbolo. (2) **P-2**
  — Frota/caçamba saiu do `jana-merge.jsx` (meta, drill, toggle, `cfg`, textos mock); o que ficou
  está em `chat-jana.jsx`, arquivo de outro dono, e virou achado aberto acima. (3) **P-3** — os 12
  fundos `*-soft` de status passaram a `color-mix(cor 12%, var(--surface))`: o shell força os
  `*-soft` CLAROS nos dois temas, e no escuro isso reprovava o AA (neg 2,19 · warn 1,60 · pos 1,93
  → 4,22 · 5,68 · 5,08), sem regredir o claro. O `accent` FICOU como estava — medido, ele passa
  hoje (4,41) e o mesmo mix o reprovaria (2,35). Junto: o detector do `ancora.mjs` passou a
  enxergar `Classe::metodo` (ele ficaria cego no formato correto — FP medido antes, zero).
- v5 (2026-08-12) — **Paridade de tema escuro** do Painel (`/ia`), sobre o pedido [CC] rev.2.
  Remove do §UX targets a prescrição de **cor** do "Demo polish (v2)" — **decisão [W] nesta data**,
  tomada com o conflito na mesa: o `Index.tsx` já contava **6 violações `no-restricted-syntax`** no
  [`config/eslint-baseline.json`](../../../../config/eslint-baseline.json) (regra
  `ds/no-raw-palette-color`, ratchet [ADR 0209](../../../../memory/decisions/0209-eslint-9-flat-config.md)),
  ou seja, o guard DS já tratava aquelas cores como **dívida** enquanto este charter as declarava
  **alvo de UX**. Os dois não podiam estar certos. Corrigido o perdedor no mesmo PR, como manda a
  regra de precedência ([`proibicoes.md`](../../../../memory/proibicoes.md) §Precedência).
  A estrutura do v2 fica intacta; muda só a origem da cor.
- v1 (2026-05-16) — Charter retroativo Wave M boost Modules/Jana 64→78
- v4 (2026-08-08) — **Fatia A** da fusão (US-COPI-148): barra ÚNICA no `<PageHeader>` canon (ver §Goals). Duas correções de fato, não de estilo: (1) o §Goals e o §Anti-hooks citavam `MetricasApurador::farol`, classe que existe (`Modules/Jana/Services/Metricas/MetricasApurador.php`) mas **não tem** método `farol` — a implementação é `ApuracaoService::farol` (`:151`, PR #5394); charter que aponta pro lugar errado manda a próxima sessão procurar a regra onde ela não está. (2) o §Goals descrevia o header antigo (dot JANA + tabs `Dashboard | Chat`), que esta onda substituiu — corrigido no mesmo PR, como manda a regra de precedência (corrigir o perdedor junto). _Nasceu numerado v3 e virou v4 no merge: a Fatia B (abaixo) landou primeiro e já tinha tomado o v3._
- v3 (2026-08-07) — Drill-down "de onde vem esse número" (`_components/JanaDrillDrawer.tsx`) nos 4
  cards de análise + nos 2 KPIs que têm análise do mesmo dado. Fonte lida do código real, não dos
  nomes fictícios do protótipo (2 anti-hooks novos). Non-Goal novo: análise "Frota" **não** será
  construída (decisão [W]; `forbidden_ui_terms` + OficinaAuto-only). Fatia B do pacote
  `JANA-FUSAO-2026-08-06`, US-COPI-148.
- v2 (2026-05-16) — Polish demo CYCLE-06 G3: badge gradient `JANA V2`, KPI strip 3 colunas, card "Próxima ação sugerida", empty state polish (ícone Sparkles + CTA "Pergunte algo a Jana"). Logic chat preservado (apenas UI surface — ChatController intacto). Ver `memory/requisitos/Jana/demo-pilot-2026-05-16/SCREENSHOT-GUIDE.md`
