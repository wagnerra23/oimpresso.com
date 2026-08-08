---
id: resources-js-pages-jana-index-charter
page: /ia
component: resources/js/Pages/Jana/Index.tsx
related_prototype: prototipo-ui/cowork/chat-jana.jsx
owner: wagner
status: live
last_validated: "2026-08-07"
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

- **Barra ÚNICA da área Jana** — `JanaAreaHeader` (em `Pages/Jana/components/`) É o `<PageHeader>` canon: título `Jana · Analista IA` + business/`biz=` + "Atualizado HH:MM" (botão de reapuração) na Zona L, `JanaSubNav` no slot `subnav`, ações da tela + primary "Conversar" na Zona R. Compartilhado com Chat.tsx e Memoria.tsx. Ver `memory/requisitos/Jana/Chat-header-tabs-visual-comparison.md` (gate F1.5).
- Render < 200ms p95 com `Inertia::defer()` em `metas` paginated + `apuracoes` 12 janelas
- Farol calculado server-side via `ApuracaoService::farol(meta, agora)` — frontend só consome
- Click em meta → drilldown `/copiloto/metas/{id}` (US-COPI-011) com série completa
- CTA "Conversar com a Jana" abre `Chat.tsx` com contexto da meta selecionada

## Non-Goals

- ⛔ Edição inline de meta (vai em `/copiloto/metas/{id}/edit` — US-COPI-013)
- ⛔ Criação de meta (vai em chat US-COPI-004 ou wizard US-COPI-012)
- ⛔ Comparativo entre business (superadmin tem `/copiloto/admin/governanca`)

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
- ⛔ Cálculo de farol no frontend — fonte autoritativa `ApuracaoService::farol`
- ⛔ Segunda barra de header na tela — identidade/ações vivem no `JanaAreaHeader` (PageHeader canon), nunca num `<header>` próprio de componente filho
- ⛔ Mutation otimista sem rollback — usar `router.patch` com `onError`

## Skills relevantes

`brief-first` (Tier A) · `multi-tenant-patterns` (Tier A) · `inertia-defer-default` (Tier B) · `mwart-process` (Tier A)

## Charter version log

- v1 (2026-05-16) — Charter retroativo Wave M boost Modules/Jana 64→78
- v3 (2026-08-07) — Fatia A da fusão (US-COPI-148). **Duas correções de fato, não de estilo:** (1) o §Goals e o §Anti-hooks citavam `MetricasApurador::farol`, classe que existe (`Modules/Jana/Services/Metricas/MetricasApurador.php`) mas **não tem** método `farol` — a implementação é `ApuracaoService::farol` (`:151`, PR #5394); um charter que aponta pro lugar errado manda a próxima sessão procurar a regra onde ela não está. (2) o §Goals descrevia o header antigo (dot JANA + tabs `Dashboard | Chat`), que esta onda substituiu pela barra única no `<PageHeader>` canon — corrigido no mesmo PR, como manda a regra de precedência (corrigir o perdedor junto).
- v2 (2026-05-16) — Polish demo CYCLE-06 G3: badge gradient `JANA V2`, KPI strip 3 colunas, card "Próxima ação sugerida", empty state polish (ícone Sparkles + CTA "Pergunte algo a Jana"). Logic chat preservado (apenas UI surface — ChatController intacto). Ver `memory/requisitos/Jana/demo-pilot-2026-05-16/SCREENSHOT-GUIDE.md`
