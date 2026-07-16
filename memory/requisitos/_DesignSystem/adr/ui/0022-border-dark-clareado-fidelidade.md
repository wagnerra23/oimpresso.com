# ADR UI-0022 · Border/input neutro no dark clareado 0.30→0.335 (emenda à ADR UI-0020)

- **Status**: accepted
- **Data**: 2026-07-08
- **Aprovado em**: 2026-07-08 — Wagner autorizou o merge ("merge e salve tudo"), confirmando o escopo **app-wide** recomendado. PR #3958 (merge `4b666ed4f1`).
- **Decisores**: Wagner (aprovação + escopo), Claude Code (medição + execução)
- **Categoria**: ui · fundações · tokens
- **Amends**: [UI-0020](0020-dark-warm-ds-v6-tokens.md) — só os valores DARK de `border`/`input` (inertia) e do `--border` (cockpit); o light permanece intacto.
- **Refs**:
  - [UI-0021](0021-primary-dark-clareado-0190.md) — mesmíssimo fluxo (DTCG source → `tokens:build` → `_generated-*.css`), aplicado ao primary. Esta é a companion neutra.
  - Fonte da medição: smoke dark do Financeiro/Unificado 2026-07-08 (sonda DOM + `style-fingerprint.mjs`, campo `borderColor`).
  - ADR 0300 (DTCG SSOT) · ADR 0299 (protótipo Cowork = fonte de design).

## Contexto

O fingerprint v3 (`style-fingerprint.mjs --compare` proto×prod) acusou `borderColor 56/57 ⚠ SISTEMÁTICO` no dark do Financeiro. A sonda DOM ao vivo (browser MCP, prod `oimpresso.com/financeiro/unificado`) mediu que a **borda neutra dominante são 575 lados em `oklch(0.30 0.012 282)`**, enquanto o protótipo Cowork (fonte ADR 0299) pede `oklch(0.335 0.012 282)` (as "linhas mais claras" que o Wagner apontou). Delta = +0.035 de L, mesmo croma/hue.

As 575 bordas vêm de **dois tokens de fundação** (medido por sentinela: pintar cada var de uma cor distinta e recontar):
- `--color-border` (utility Tailwind `border-border`/`border-input`) → 151 lados;
- `--border` (token do cockpit, `var(--border)` no CSS de componente) → 424 lados.

### Por que a sessão anterior concluiu "NÃO é fix de token" (correção de diagnóstico)

O handoff 2026-07-08 registrou "bumpar `--color-border` não muda a borda → é hardcode, sweep arquivo-por-arquivo". **Isso era uma misdiagnose**, e a causa mecânica exata foi medida agora:

> O `.cockpit` carrega `data-theme="dark"` (confirmado: `cockpit.matches('[data-theme="dark"]') === true`). Logo a regra única `.dark, [data-theme="dark"] { --color-border: … }` **re-aplica o valor no nível do `.cockpit`**, sombreando qualquer override feito **só na raiz `<html>`** (que foi como a var foi testada no DevTools). Setar a var na raiz é sombreado → parece "morto".

Prova de que **é** token-driven: setar `--color-border` **no próprio elemento** vira a borda; e editar o **valor do token** (que alimenta a regra única casando `<html>` E `.cockpit`) move as 575 → `575 → 0` no valor velho, `575` no alvo `0.335`. Não há literal `oklch(0.3… 282)` em `resources/css` fora dos `_generated-*.css` (grep vazio) — a borda é 100% token.

## Decisão

**No dark, `--color-border`, `--color-input` (inertia) e `--border` (cockpit) passam de `oklch(0.30 0.012 282)` para `oklch(0.335 0.012 282)` — app inteiro.** É token de fundação → afeta a borda neutra dark de **todos os módulos** (utilities `border-*` + shell cockpit), não só o Financeiro. Igual à UI-0021 (primary "app inteiro"): decisão de fundação, escopo global explícito.

- Editado na **fonte DTCG** (`semantic.tokens.json` → `border`/`input`/`cockpit.border` `.$extensions.com.oimpresso.dark`), regenerado via `npm run tokens:build`. Muda só `_generated-inertia-dark.css` (2 vars) + `_generated-cockpit-dark.css` (1 var). Light e demais tokens intactos. Equivalência DTCG↔CSS preservada (`dtcg-equivalence.mjs`: 296/296 fiéis, 0 divergências).

## Consequências

- **Positivas:** a borda neutra no dark fica fiel ao protótipo em todo o app; o `borderColor` sistemático do fingerprint deixa de acusar as 575.
- **Blast radius:** toda tela em dark com borda neutra muda ~+0.035 de L → **baselines de regressão visual regeneradas** (modo UPDATE do VRT, pós-merge; `resources/css/**` é skip-as-pass, não dispara VRT sozinho).
- **Residual honesto (fora do escopo desta emenda, 1 PR = 1 intent):**
  - `--fin-line` (Financeiro, `oklch(0.92 0.005 240)` — **claro e frio, sem override dark**): 49 lados renderizam uma linha clara errada no dark. É var própria do Financeiro (specificity `.fin-cowork .fin-curadoria`), não token de fundação → alinhá-la ao neutro é varredura Financeiro-scoped própria (companion da superfície).
  - Residuais frios `oklch(0.28 0.008 240)` (~11 lados) hardcoded no shell.
  - **Superfície (`bgEfetivo 56/57`):** o topo (header + KPIs + barra de filtro) achata no bg da página `oklch(0.165 0.008 282)`; o proto quer um painel ~`oklch(0.238 0.009 282)` atrás do filtro (a tabela já tem painel `0.205`). Precisa do proto rodando pra cravar container + valor → **PR próprio**.
