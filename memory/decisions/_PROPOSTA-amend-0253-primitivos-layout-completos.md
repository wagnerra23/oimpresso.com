---
slug: _PROPOSTA-amend-0253-primitivos-layout-completos
proposes: amend
amends: 0253-primitivos-layout
title: "Completar os primitivos de layout — superfície no Box, divider em Stack/Inline, auto-fit no Grid, max-w v4-safe no Container, mono/tabular/tons/escala no Text"
type: adr-proposta
status: proposta
authority: proposed
decided_by: pendente-[W]
proposed_by: "[CC] Cowork"
proposed_at: "2026-06-07"
module: _DesignSystem
related: ["0253-primitivos-layout", "0235-ds-v4-accent-roxo-universal", "0249-ds-v6-naming-amends-0235", "0013-constituicao-ui-v2-camadas"]
pii: false
---

# PROPOSTA — emenda à ADR 0253 · completar os primitivos de layout

> **[CC] propõe; [CL] numera/versiona; [W] decide.** NÃO é lei até [W] aceitar (soberania da
> constituição). Esta proposta NÃO inventa token novo — consome o `@theme` existente (`inertia.css`).

## Por que fazer (o problema que isto resolve)

A ADR 0253 criou a camada `Components/layout/` — decisão certa, arquitetura estado-da-arte (CVA
token-only, `asChild`, TS strict). **Mas a v1 entregou o genérico, não o que o próprio Oimpresso usa
todo dia.** Uma avaliação contra a própria ADR 0253 + MANUAL §2.1 + o estado-da-arte que ela cita
(Polaris/Radix Themes/Geist/Primer) + o DS v6 visual real apontou 5 lacunas que **forçam a tela a cair
de volta no `className` solto** — exatamente o que a camada existe pra matar. Ou seja: sem completar,
a camada não "pega" no caso de maior volume (telas de número).

| Gap (v1) | Por que dói | Custo de não-fazer |
|---|---|---|
| **Text sem `mono`/`tabular`, sem tons `success/destructive`, teto `3xl`** | O ERP é movido a número (money, placa, km, NF-e). KPI canônico é `4xl/36px` (DESIGN.md §16.3). KPI +/− precisa de cor semântica. | Toda tela de vendas/financeiro/OS volta a usar `text-[36px]`/`text-emerald-700` solto → o sprawl que o MANUAL combate. |
| **Container `max-w-screen-*`** | Utilities **removidas no Tailwind v4** → limite de largura vira no-op silencioso. | Páginas sem medida-máx; regressão invisível em todo wrapper. |
| **Grid sem auto-fit/responsivo** | Personas 1280 (Larissa) × 1440 (Wagner). `cols=3` fixo quebra; grade de cards do DS usa `repeat(auto-fill,minmax())`. | Dashboard/grade-de-card não migra de verdade — metade do valor da camada. |
| **Box sem cor/superfície** | A ADR 0253 nomeou "espaço **e cor** via token"; a v1 só fez padding. | O "card" vira o próximo `.css` bespoke. |
| **Stack/Inline sem divider** | Hairline entre itens e "·" entre meta-itens são padrão visual onipresente. | Reescrito à mão por tela. |

## O que a emenda entrega (refino v2 — código pronto no handoff)

Tudo aditivo, sem breaking change (exports e props existentes intactos):

1. **Box** — `bg` (card/muted/background/secondary/accent/primary) · `rounded` (sm/md/lg) · `border`. Tokens reais do `@theme`.
2. **Stack** — `divider` (`divide-y divide-border`).
3. **Inline** — `divider` (`divide-x divide-border`).
4. **Grid** — `min` (sm/md/lg → `repeat(auto-fill,minmax(<token>,1fr))`) pro modo responsivo auto-fit; `cols` segue pro fixo.
5. **Container** — `size` remapeado pra escala `max-w-*` que **existe no TW v4** (corrige o no-op).
6. **Text** — `family` (sans/mono) · `numeric` (tabular) · `leading` · `tone` +`success`/`warning`/`destructive` · `size` até `5xl`.

**Princípio mantido:** todo valor é enum CVA → px/hex literal segue recusado em compile-time. Nenhum
`.css` novo. Nenhum token novo (só consome `@theme`).

## Pendência Tier 0 (só [W]) — fora desta proposta

- **`--font-mono` no `@theme`** (`"IBM Plex Mono", ui-monospace, …`): hoje o `inertia.css` só define
  `--font-sans`. O `family="mono"` do Text funciona (cai no mono do sistema), mas pra ter Plex Mono de
  verdade o token precisa entrar — **decisão de Fundações = Tier 0**, não entra junto.

## Critério de pronto (herdado da ADR 0253, agora cobrável)

1. `typecheck` + `lint` + `stylelint` verdes; baseline não cresce.
2. `npm run foundation:check` + `npm run conformance:check` verdes.
3. **Build confirma** que `Container size="xl"` produz `max-w-7xl` no DOM (fecha o risco TW v4).
4. ≥1 tela piloto 100% primitivos (zero `flex` solto, zero `.css`) — KPI em `Text mono tabular` + grade
   em `Grid min` como prova.
5. Doc no DS + entrada no REGISTRY.

## Refs

- ADR 0253 (mãe) · MANUAL-CSS-JS §2.1/§5 · DESIGN.md §16.3 (KPI 4xl) · inertia.css `@theme` (tokens reais)
- Relatório de avaliação (Cowork): `Avaliacao - Primitivos de Layout (ADR 0253).html`
