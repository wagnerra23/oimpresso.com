---
slug: 0235-ds-v4-accent-roxo-universal
number: 235
title: "DS v4 — accent roxo universal oklch(0.55 0.15 295): estende o primary button (ADR 0190) pro shell inteiro (foco/links/abas/ativo/ring)"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
decided_by: [W]
decided_at: "2026-05-29"
accepted_at: "2026-05-29"
accepted_via: "Wagner 2026-05-29 — briefing Cowork 'APLICAR O DESIGN SYSTEM v4 (ROXO)' (full-roxo aprovado) + escolha explícita de executar fundação+auditoria E correção de tela em 2 PRs."
module: _DesignSystem
quarter: 2026-Q2
tags: [design-system, accent, roxo, ds-v4, shell, amends-0190]
supersedes: []
amends:
  - "0190-primary-button-roxo-universal-295"
superseded_by: []
related:
  - "0094-constituicao-v2-7-camadas-8-principios"
  - "0114-prototipo-ui-cowork-loop-formalizado"
  - "0180-sidebar-v3-5-grupos-ghosts-header"
  - "0189-pageheader-canon-v3-1-cadastro-roxo"
  - "0190-primary-button-roxo-universal-295"
pii: false
review_triggers:
  - Larissa biz=4 testar 7d e relatar saturação visual com roxo em todas as telas
  - Telemetria mostrar drop em CTA/navegação após accent universal
  - 2+ clientes piloto pedirem accent diferente do roxo
---

# ADR 0235 — DS v4: accent roxo universal (estende ADR 0190)

## Contexto

[ADR 0190](0190-primary-button-roxo-universal-295.md) (aceita 2026-05-25) estabeleceu o **primary button** interno = roxo médio `oklch(0.55 0.15 295)`, mantendo *hue-per-grupo* apenas pra agrupamento visual da sidebar. Ela tratou do **botão primário** — não do **accent** (cor de foco, links, abas ativas, item selecionado, ring).

Em 2026-05-29 o Cowork propôs "DS v4 (roxo)" via briefing que afirmava *"supersede ADR 0190 (shell azul)"*. A auditoria do repo ([`prototipo-ui/AUDITORIA_DS_V4.md`](../../prototipo-ui/AUDITORIA_DS_V4.md)) mostrou que essa premissa estava **factualmente incorreta**:

| Afirmação do briefing | Realidade do repo |
|---|---|
| "ADR 0190 = shell azul" | ADR 0190 = *primary button roxo 295*, `supersedes: []`, aceito |
| "vira o shell pra roxo" | `resources/css/cockpit.css:32` **já** tem `--accent: oklch(0.55 0.15 295)` (roxo) |
| "supersede 0190" | Constituição é append-only; e 0190 já é pró-roxo → cabe **amends**, não supersede |

O que de fato faltava: (a) **formalizar** o accent roxo como canon universal (a 0190 só cobriu o primary button); (b) **sincronizar a fundação de referência** (`prototipo-ui/tokens.css` ainda estava `oklch(0.58 0.09 220)` azul).

## Decisão

1. **Accent canônico = roxo** `oklch(0.55 0.15 295)` — com `--accent-2: oklch(0.62 0.15 295)` e `--accent-soft: oklch(0.95 0.04 295)`. Vale pra foco, links, abas ativas, item selecionado, ring e accent em geral do shell.
2. **Hue-per-grupo da sidebar permanece** ([ADR 0180](0180-sidebar-v3-5-grupos-ghosts-header.md)/[0190](0190-primary-button-roxo-universal-295.md)) — é só agrupamento visual, não accent.
3. `prototipo-ui/tokens.css` + `design-system.css` + `Design System v4.html` sincronizados como fundação de referência v4.
4. **Migração das telas é tela-por-tela** (não big-bang). Telas Inertia com `blue-*` hardcoded migram pro token, começando por `Pages/Cliente/` (PR `feat/cliente-accent-roxo`).
5. Bundles CSS que ainda redeclaram `--accent` azul 220 (financeiro/sells — ver auditoria §2) devem herdar o token roxo (ação futura).

## Consequências

**Positivas:** identidade visual única (roxo) coerente entre shell, primary e accent; fundação de referência alinhada com o app; auditoria mapeia todo o débito restante.

**Negativas / riscos:** 106 telas `.tsx` têm azul hardcoded → inconsistência temporária (roxo no shell, azul nas telas não migradas) até a migração tela-por-tela concluir. Mitigado por: migração priorizada (Cliente primeiro) + `review_triggers` (saturação visual Larissa biz=4 em 7d).

**Append-only:** esta ADR **não altera** a 0190; registra a extensão (`amends`). Reversão/ajuste futuro = nova ADR com `supersedes: [0235]`.
