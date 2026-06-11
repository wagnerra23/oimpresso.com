---
title: "Árvore canônica de componentes — camadas UI-0013 viram pastas enforçadas"
status: proposed
date: "2026-06-11"
decisores: [Wagner (aprova), Claude Code (autor)]
related_adrs:
  - 0093-multi-tenant-isolation-tier-0
  - 0240-derivado-enforcado-sobrevive
  - 0253-primitivos-layout
related_adrs_ui: [UI-0013, UI-0015, 0189, 0190]
origem: "Auditoria 2026-06-11 — Wagner: 'quais componentes eu tenho? e como deveria ser o otimizado? pontue' → 57/100 → 'pode fazer, e que essa organização sobreviva ao tempo'"
prs: ["#2539 (moves)", "#2540 (BR inputs)", "este (guard + rule)"]
---

# Árvore canônica de componentes — camadas UI-0013 viram pastas enforçadas

## Contexto

Auditoria 2026-06-11 da árvore `resources/js/` (461 páginas) deu **57/100**: base forte
(`ui/` 28 primitivos com 930 imports · `shared/` com DataTable/PageFilters/EmptyState ·
governança de docs 9/10), mas a hierarquia de 4 camadas da Constituição UI v2 (UI-0013)
**não se refletia nas pastas**: 7 pastas de domínio dentro da global `Components/`,
4 arquivos soltos na raiz, BR-masks hand-wiradas por tela, e nenhum gate impedindo a
próxima feature de recriar `Components/<MeuModulo>/`.

A investigação também CORRIGIU 3 hipóteses da auditoria inicial:

1. **PageHeader duplicado já é governado** — F4 (MANUAL-CSS-JS §5) congela o legacy
   `shared/PageHeader` via ratchet `pageheader-gate` (104 telas, contador só desce);
   migração é tela-a-tela com aprovação visual. Não havia nada a construir — só
   sinalizar (`@deprecated` JSDoc, este PR).
2. **`layout/` não é camada morta** — é ADR 0253 com piloto (ProvaViva) + guard próprio
   (`layout:check`), adoção incremental por catraca. 23 sites de import reais.
3. **`board/`, `cockpit/`, `Site/`, `NfeBrasil/` não são poluição** — são cross-módulo
   (board: OficinaAuto+ProjectMgmt), Shell (cockpit: AppShellV2 consome, SIDEBAR_GROUP_HUE
   source of truth), surface pública (Site+SiteLayout) e domínio fiscal consumido por
   módulo ≠ dono (NfeBrasil ← Sells).

## Decisão proposta

### 1. Árvore canônica (camada UI-0013 → pasta) — JÁ EXECUTADA em #2539/#2540

| Camada | Pasta | Regra de criação |
|---|---|---|
| Fundações/primitivos | `Components/ui/` | kebab-case + entrada no `REGISTRY_DS_COMPONENTES.md` |
| Shell | `Layouts/AppShellV2` + `Components/PageHeader/` (canon) + `cockpit/` | mudança = ADR (PRE-MERGE camada 2) |
| Compostos cross-módulo | `Components/shared/` | só com ≥2 módulos consumidores (R-DS-001 regra-de-2) |
| Layout primitives | `Components/layout/` | só via ADR (0253) |
| Módulo (camada 4) | `Pages/<Mod>/_components/` (COM underscore) | domínio de 1 módulo NUNCA na global |

Allowlist do top-level da global: `ui · shared · layout · PageHeader · cockpit · board ·
Site · NfeBrasil` + 4 arquivos raiz (CommandPalette, Icon, MentionInput, ThemeToggle).

### 2. Enforcement (ADR 0240: derivado + enforcado sobrevive) — ESTE PR

- `scripts/components-tree-guard.mjs` + workflow `components-tree-guard.yml`:
  CHECK 1 allowlist do top-level (entrada nova = editar o script no MESMO PR — decisão
  consciente no diff) · CHECK 2 convenção `_components` sob Pages (4 pré-existentes sem
  underscore grandfathered, lista não cresce).
- `.claude/rules/components.md` (path-scoped): tabela "onde criar componente" + catracas
  ativas + pegadinha dos baselines path-keyed em moves.
- `@deprecated` JSDoc no `shared/PageHeader.tsx` apontando canon + F4.

### 3. Roadmap (NÃO neste PR — cada item exige ADR própria + piloto + gate visual)

- **`Components/patterns/` — PT-01 Lista como código (`ListPage`)**: template componível
  dos 6 slots. POR QUE ainda não: o Slot 1 do PT-01 referencia o PageHeader *legacy*
  enquanto a F4 migra 104 telas pro canon — template hardcoded agora criaria uma 3ª
  superfície brigando com a catraca. QUANDO: após F4 avançar o suficiente OU decidindo o
  template já nascer canon-only pra telas novas. Processo: ADR → piloto 1 tela → screenshot
  Wagner → guard de adoção (espelha ADR 0253).
- **Promoções shared← _components por regra-de-2**: quando 2º módulo precisar de algo que
  vive em `Pages/<Mod>/_components/`, promove no PR que cria o 2º uso (idioma
  NumericInputPtBR, #2540).
- **Migração das 4 pastas `components` sem underscore** (Compras, Financeiro×2, Jana):
  quando a tela for tocada — catraca impede novas.

## Consequências

### Positivas
- Pedido "cria componente X" vira determinístico — a rule + o guard respondem ONDE.
- Regressão estrutural impossível sem aparecer no diff (allowlist vive no script, não em doc).
- Score projetado da auditoria: 57 → ~75/100 (consolidação sem mudança visual).

### Negativas
- Pasta cross-módulo nova legítima exige editar o guard (fricção intencional, ~1 linha).
- 3 baselines path-keyed (eslint, layout, ui-lint) exigem re-key manual em moves —
  documentado na rule; candidato futuro: helper `npm run baselines:rekey <de> <para>`.

### Aprovação
Wagner aprova esta proposal → vira ADR numerada via PR (append-only).
