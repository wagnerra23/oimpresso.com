---
name: oimpresso-design
description: Use this skill to generate well-branded interfaces and assets for Office Impresso (a.k.a. oimpresso) — a full Brazilian ERP (nível TOTVS) for gráficas, comunicação visual e oficinas, covering CRM/Clientes, Orçamentos, Vendas/PDV, Produção/OP, Fiscal (NF-e), Estoque/Compras, Financeiro, RH/Ponto e BI. Contains essential design guidelines, colors, type, fonts, assets, and UI kit components for prototyping.
user-invocable: true
---

Read the README.md file within this skill, and explore the other available files.

**Se o trabalho vai virar código no repo `wagnerra23/oimpresso.com`, leia [`HANDOFF.md`](HANDOFF.md) antes de qualquer coisa** — é o contrato zero-touch: entrega repo-nativa (Tailwind + tokens reais, nunca CSS cru `.om-*`), leitura do `main` com citação arquivo+linha, mapa componente DS→arquivo real (reusar/estender/criar), gates a rodar, e o bloco a commitar em `prototipo-ui/COWORK_NOTES.md`. **git é SSOT** (ADR 0239); este projeto claude.ai/design é espelho derivado e **NÃO-fonte** (ADR 0315/0299) — escrita espelho→git só com opt-in explícito do Wagner; merge é ato do Wagner (ADR 0283).

Regras duras que não se negociam: **sidebar PRETA (dark-fixo) nos dois modos** (UI-0023) · primary **roxo** `oklch(0.55 0.15 295)` (o blue do shadcn legado é superseded) · PT-BR em toda copy · zero cor crua em arquivo de módulo · componente vem do `shared`/`ui`, não se reinventa · `localStorage` prefixado `oimpresso.*` · ícone só lucide · sem emoji em UI de produto · status badge = dot + tinta ≤10%, nunca fill sólido · um `<main>` por documento.

If creating visual artifacts (slides, mocks, throwaway prototypes, etc), copy assets out and create static HTML files for the user to view. The two ready-made entry points are:

- `ui_kits/site/index.html` — the marketing surface (Header, Hero, Features, Pricing, Footer). Lift `site.css` and the JSX components for any landing-page work.
- `ui_kits/app/index.html` — the operational app shell (Sidebar accordion, PageHeader, KpiCard, StatusBadge, PresenceStrip, ActivityFeed). Lift `app.css` and the components for any in-app screens.

`colors_and_type.css` is the single source of truth for tokens — import it before anything else. The system is **DS v6** (git SSOT, DTCG tokens via Style Dictionary, OKLCH): primary is **roxo/purple** `oklch(0.55 0.15 295)`, the operational shell uses **IBM Plex Sans/Mono** and the `.cockpit` palette (`--bg/--surface/--text/--accent/…`), and sizes come from the `--fs-1..9` Type RAMP. Lucide-style icons only; emoji **only** on marketing FeatureGrid cards.

Voice: PT-BR, sentence case, conversational ("você", "pra", "sem gambiarra"). Domain idioms by module — produção: "OS"/"OP", "cálculo por m²"; vendas: "PDV"; fiscal: "NF-e"/"NFC-e"; ponto (RH): "marcação" not "ponto", "intercorrência" not "ajuste", "colaborador" not "funcionário". See README "CONTENT FUNDAMENTALS".

If working on production code, you can copy assets and read the rules here to become an expert in designing with this brand.

If the user invokes this skill without any other guidance, ask them what they want to build or design, ask some questions (which surface — site or app? which module? which screen?), and act as an expert designer who outputs HTML artifacts _or_ production code, depending on the need.
