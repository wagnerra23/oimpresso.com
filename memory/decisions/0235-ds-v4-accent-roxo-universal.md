---
slug: 0235-ds-v4-accent-roxo-universal
number: 235
title: "DS v4 — design system roxo universal (accent oklch 0.55 0.15 295); supersede ADR 0190; Claude Design plugin vira owner da interface"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
decided_by: [W]
decided_at: "2026-05-29"
accepted_at: "2026-05-29"
accepted_via: "Wagner 2026-05-29 — 'aplique o roxo e rebaixe o 190, nao vou usar mais, estou trocando o design; a partir de agora vou instalar o design system, quem vai cuidar da interface sera o Claude Design.'"
module: _DesignSystem
quarter: 2026-Q2
tags: [design-system, ds-v4, accent, roxo, supersede-0190, claude-design, governanca-ui]
supersedes:
  - "0190-primary-button-roxo-universal-295"
amends: []
superseded_by: []
related:
  - "0094-constituicao-v2-7-camadas-8-principios"
  - "0109-claude-design-plugin-integrado-processo-mwart"
  - "0114-prototipo-ui-cowork-loop-formalizado"
  - "0180-sidebar-v3-5-grupos-ghosts-header"
  - "0190-primary-button-roxo-universal-295"
pii: false
review_triggers:
  - Larissa biz=4 testar 7d e relatar saturação visual com roxo em todas as telas
  - Telemetria mostrar drop em CTA/navegação após accent universal
---

# ADR 0235 — DS v4: design system roxo universal + Claude Design como owner da UI

> **Supersede [ADR 0190](0190-primary-button-roxo-universal-295.md)** por decisão do Wagner 2026-05-29 (*"rebaixe o 190, não vou usar mais"*). A 0190 (primary button roxo + hue-per-grupo na sidebar) sai de cena; o DS v4 (roxo universal) passa a ser o regime único, sob responsabilidade do **Claude Design**.

## Contexto

[ADR 0190](0190-primary-button-roxo-universal-295.md) (2026-05-25) estabeleceu o **primary button** roxo `oklch(0.55 0.15 295)`, mantendo *hue-per-grupo* na sidebar. Em 2026-05-29 o Wagner decidiu **trocar o design system inteiro** pro DS v4 (roxo universal) e **aposentar a 0190** — não apenas estendê-la.

Nota de implementação (auditoria [`prototipo-ui/AUDITORIA_DS_V4.md`](../../prototipo-ui/AUDITORIA_DS_V4.md)): o app já vinha aplicando o roxo no shell (`resources/css/cockpit.css:32` já tinha `--accent: oklch(0.55 0.15 295)`). O DS v4 formaliza isso como regime e sincroniza a fundação de referência (`prototipo-ui/tokens.css`, que estava em azul 220).

## Decisão

1. **DS v4 é o design system canônico.** Accent = roxo `oklch(0.55 0.15 295)` (`--accent-2: 0.62 0.15 295`, `--accent-soft: 0.95 0.04 295`) — foco, links, abas, item ativo, ring e primary. Token semântico Tailwind nas Pages Inertia: **`primary`** (`resources/css/inertia.css`).
2. **ADR 0190 fica `superseded`** (`superseded_by: 0235`). A autoridade dela — primary roxo **e** a regra de *hue-per-grupo* da sidebar — deixa de valer; o DS v4 assume.
3. **Owner da interface = Claude Design plugin** (`design:*` — design-system, design-critique, ux-copy, accessibility-review, design-handoff). A partir de 2026-05-29, decisão e evolução de UI passam pelo Claude Design. [ADR 0109](0109-claude-design-plugin-integrado-processo-mwart.md) já o integrava ao MWART; agora ele é o **responsável** pela UI, não só uma etapa. Loop Cowork↔Code permanece ([ADR 0114](0114-prototipo-ui-cowork-loop-formalizado.md)).
4. **Migração tela-por-tela.** Telas Inertia com `blue-*` hardcoded migram pro token `primary`; `Pages/Cliente/` (a tela `/contacts`) foi a primeira. A sidebar `hue-per-grupo` (código `cockpit/shared.ts SIDEBAR_GROUP_HUE` + [ADR 0180](0180-sidebar-v3-5-grupos-ghosts-header.md)) continua no código por ora; a decisão de unificá-la em roxo fica com o Claude Design — **sem mudança de código neste momento**.
5. Débito residual (auditoria): 6 bundles CSS redeclaram `--accent` azul 220; ~105 telas `.tsx` com azul hardcoded.

## Consequências

**Positivas:** regime de design único e roxo; owner claro (Claude Design); fundação de referência alinhada ao app; auditoria mapeia o débito.

**Negativas / riscos:** inconsistência temporária (roxo no shell, azul em telas não migradas) até a migração concluir; a sidebar `hue-per-grupo` fica num limbo de autoridade (mantida no código) até o Claude Design decidir. Mitigado por migração priorizada + `review_triggers` (saturação Larissa biz=4 em 7d).

**Append-only:** esta ADR não edita o conteúdo da 0190; apenas marca seu lifecycle como `superseded` (mecanismo canônico de rebaixamento). Ajuste futuro = nova ADR com `supersedes: [0235]`.
