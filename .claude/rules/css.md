---
paths:
  - "resources/css/**/*.css"
  - "resources/js/Components/ui/**/*.tsx"
  - "resources/js/Components/layout/**/*.tsx"
---

# Rule path-scoped — CSS / identidade / layout

> Carrega quando Claude toca CSS ou primitivos de UI/layout.
> **SSOT (ler 1º):** [INDEX-DESIGN-MEMORIAS.md](../../memory/requisitos/_DesignSystem/INDEX-DESIGN-MEMORIAS.md) — fonte única de design (regra de ouro + conflitos reconciliados).
> **Depois:** [MANUAL-CSS-JS.md](../../memory/requisitos/_DesignSystem/MANUAL-CSS-JS.md) — regimento CSS/JS (do/don't + arquitetura-alvo + roadmap convergência F0–F7).

## Identidade (JÁ CANON — não re-decidir)
Primário roxo `oklch(0.55 0.15 295)` ([ADR 0235](../../memory/decisions/0235-ds-v4-accent-roxo-universal.md)) + tokens semânticos **DS v6** ([ADR 0249](../../memory/decisions/0249-ds-v6-naming-amends-0235.md)). Azul de **marca** = débito a migrar; azul **semântico** (status/origin-badge) sobrevive (SSOT §4).
**Não-convergência atual:** `.cockpit` CSS-vars + `@apply` Blade legacy competem com o `@theme`. **Alvo:** token único em `@theme`.

## Regras duras
- ❌ **Nunca** criar `.css` novo em `resources/css/` sem ADR. CSS por-tela (`*-cowork.css`) é anti-padrão.
- ❌ Nada de hex/px solto, `!important`, ou CSS de protótipo colado. Token sempre (`bg-primary`, `gap-4`).
- ✅ Estilo mora no JSX (Tailwind). Repetição → componente CVA, não arquivo `.css`.
- ✅ Layout → primitivos `Components/layout/` (Box/Stack/Inline/Grid/Container/Text) quando existirem (MANUAL §2.1 + F3 do roadmap §5).
- ✅ Tocou tela com bundle Cowork? **Delete a fatia** correspondente do `.css` (métrica-mãe: linhas ↓ PR a PR).

## Gates antes do PR
`npm run typecheck` · `lint` · `stylelint` · `foundation:check` · `conformance:check` — baseline só **encolhe**.

## Arquitetura-alvo
`@theme` (token único) → `Components/layout/` + `Components/ui/` → Padrões de Tela → Página (zero `.css`).
Hierarquia herda da [Constituição UI v2 · ADR UI-0013](../../memory/requisitos/_DesignSystem/adr/ui/0013-constituicao-ui-v2-camadas.md).
