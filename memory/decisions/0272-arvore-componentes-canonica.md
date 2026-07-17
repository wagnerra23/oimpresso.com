---
slug: 0272-arvore-componentes-canonica
number: 272
title: "Árvore canônica de componentes — camadas UI-0013 viram pastas enforçadas (allowlist + shared flat + regra-de-2)"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
kind: decision
decided_by: [W]
decided_at: "2026-06-11"
accepted_at: "2026-06-11"
accepted_via: "Wagner 2026-06-11 no chat: auditoria 'quais componentes eu tenho? pontue' (57/100) → 'pode fazer, e que essa organização sobreviva ao tempo' → dedup 'faça o necessário' → aceite textual 'eu aprovo'. Redação por Claude Code."
module: governance
quarter: 2026-Q2
tags: [components, design-system, arquitetura, enforcement, catraca, dedup, ui-0013, anti-regressao]
supersedes: []
supersedes_partially: []
superseded_by: []
related: [0253-primitivos-layout, 0271-revisao-gates-ci-estado-real-required-e-subtracao-segura]
---

# ADR 0272 — Árvore canônica de componentes: camadas UI-0013 viram pastas enforçadas

> Formaliza a proposal [`proposals/2026-06-11-arvore-componentes-canonica.md`](proposals/2026-06-11-arvore-componentes-canonica.md), já executada nos PRs #2539 · #2540 · #2542 · #2547 · #2549 (todos mergeados 2026-06-11 com gates verdes e zero mudança visual).

## Contexto

Auditoria 2026-06-11 da árvore `resources/js/` (461 páginas): **57/100**. Base forte (ui/ 28 primitivos · 930 imports; governança de docs), mas a hierarquia de 4 camadas da Constituição UI v2 (ADR UI-0013) não se refletia nas pastas: 7 pastas de domínio na global `Components/`, domínio escondido DENTRO de `shared/` (ponto/), BR-masks hand-wiradas por tela, colisões de nome (2 `ModuleTopNav`, 2 `CommandPalette`) e nenhum gate impedindo recriar `Components/<MeuModulo>/`.

A investigação corrigiu 3 hipóteses da auditoria: PageHeader duplicado **já é governado** (F4 · ratchet `pageheader-gate`, 104→0); `layout/` **não é camada morta** (ADR 0253, piloto + guard); `board/cockpit/Site/NfeBrasil` são cross-módulo/Shell/surface justificados.

## Decisão

### 1. Árvore canônica (camada → pasta)

| Camada UI-0013 | Pasta | Regra de criação |
|---|---|---|
| Fundações/primitivos | `Components/ui/` | kebab-case + entrada no `REGISTRY_DS_COMPONENTES.md` |
| Shell | `Layouts/AppShellV2` + `Components/PageHeader/` (canon v3.8) + `cockpit/` | mudança = ADR (PRE-MERGE camada 2) |
| Compostos cross-módulo | `Components/shared/` — **FLAT, sem subpastas** | só com ≥2 módulos consumidores (R-DS-001 regra-de-2) |
| Layout primitives | `Components/layout/` | só via ADR (0253) |
| Módulo (camada 4) | `Pages/<Mod>/_components/` (COM underscore) | domínio de 1 módulo NUNCA na global |

Allowlist do top-level da global: `ui · shared · layout · PageHeader · cockpit · board · Site · NfeBrasil` + 4 arquivos raiz (CommandPalette, Icon, MentionInput, ThemeToggle). Entrada nova = editar o guard no MESMO PR (decisão consciente no diff).

### 2. Enforcement (lei ADR 0240: derivado + enforcado sobrevive)

`scripts/components-tree-guard.mjs` + workflow + `npm run components:check` — CHECK 1 allowlist top-level · CHECK 2 convenção `_components` sob Pages (4 sem-underscore grandfathered, lista não cresce) · CHECK 3 `shared/` flat (sem grandfather). Rule path-scoped `.claude/rules/components.md`. Colisão de nome é erro: implementação própria homônima de componente canônico renomeia com prefixo do módulo (precedente: `FiscalModuleTopNav`, `KbCommandPalette`). Catraca `reuse-duplicates-baseline` apertada 25→21 — só desce.

### 3. Promoção por regra-de-2

Componente local vira `ui/`/`shared/` no PR que cria o 2º consumidor (precedente: `NumericInputPtBR` promovido de Sells → `ui/numeric-input-ptbr`, junto com `DocumentInput` e `PhoneInput` novos — BR inputs canônicos, 11 testes).

### 4. Roadmap (cada item exige ADR própria + piloto + gate visual Wagner)

- `Components/patterns/ListPage` (PT-01 como código) — adiado conscientemente: Slot 1 do PT-01 referencia o PageHeader legacy enquanto F4 migra 104 telas; processo espelha ADR 0253 (ADR → piloto → screenshot → guard de adoção).
- Duplicatas remanescentes com dono: `Cobranca/atoms.tsx` + `cn` local → F5 (bundle dissolve tela-a-tela) · `Pages/MemCofre/*` → deleção verificada · `MercosulPlate` shim ADR 0251 → remoção pós-#2544 (mergeado — liberado).

## Consequências

- **Positivas:** pedido "cria componente X" é determinístico (rule + guard respondem ONDE); regressão estrutural impossível sem aparecer no diff; score da auditoria projetado 57 → ~75 sem mudança visual.
- **Negativas:** pasta cross-módulo nova legítima exige editar o guard (fricção intencional ~1 linha); baselines path-keyed (eslint, layout, ui-lint `\/`-escapado) exigem re-key manual em moves — documentado na rule; candidato futuro `npm run baselines:rekey`.

## Histórico

- 2026-06-11: proposal criada e executada em 5 PRs (#2539 moves domínio · #2540 BR inputs · #2542 guard+rule · #2547 shared flat · #2549 renames+catraca) — aceita por Wagner ("eu aprovo") no mesmo dia.
