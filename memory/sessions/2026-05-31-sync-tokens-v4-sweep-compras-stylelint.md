# 2026-05-31 — Sync tokens v4 (roxo) + sweep CSS Compras + ponte Stylelint

**Pedido [W]:** "dá pra aplicar o eslint aqui? e padronizar o DS v4?" — depois: "não sei, faça o melhor e não pergunte, já tens as instruções."

**O que foi feito ([CC], escopo F1 + ponte):**
1. **`tokens.css` sincronizado ao DS v4.** `--accent` estava **azul `oklch(0.58 0.09 220)` (stale)**; DS v4 + bundle Financeiro já usavam roxo. Trocado p/ `oklch(0.55 0.15 295)` (`--accent-2`/`--accent-soft` idem). Alinha o token vivo do app ao canon **ADR 0235** (roxo universal). Header do arquivo: "Source v3" → "v4".
2. **`compras-page.css` migrado (0 hex).** Namespace paralelo `--cmp-*` (navy/cream `#1f3a5f`) remapeado 1:1 nos tokens DS (`--cmp-accent:var(--accent)` etc.). ~40 hex inline → token/`color-mix`. Radius `4/5px` → `var(--radius-sm|--radius)`. Font stack → `var(--font-sans)`. Compras agora é roxo canon, sem paleta própria (cumpre STATUS item #2 + D-05).
3. **Ponte que faltava: `REGRAS_STYLELINT_CSS.md`.** O guard ESLint `ds/*` (PR-B) é AST em `className` de TSX — **não toca `.css`**. Hex cru em `.css` passava batido. Spec Stylelint (`color-no-hex` + radius-scale, `ignoreFiles` = tokens.css/design-system.css, ratchet igual ADR 0209) cobre a metade CSS.

**Decisões:**
- ESLint literal **não roda aqui** (read-only git, é F3 do [CL]); guard `ds/*` **já existe** (PR-B) — não reinventei. Complemento = Stylelint (metade CSS).
- Sync tokens→roxo **não é mudança de canon** (canon já é roxo ADR 0235); é correção de drift do arquivo vivo → dentro do meu escopo F1, sem proposta-constituição.

**Residual / próximo passo:**
- Espelhar `compras-page.css` migrado + `tokens.css` no repo (Code).
- `cowork-financeiro-bundle.css` ainda tem hex (stage-colors, avatares gradiente) — próximo alvo do sweep CSS.
- Ponte zero-toque do Stylelint (URL pública gerada, vale ~1h) — montar prompt `PROMPT_PARA_CODE_STYLELINT-CSS-HEX.md` quando [W] disparar.

**Refs:** ADR 0235 (roxo), ADR 0209 (ratchet), PR-B-guard-ds.md, REGRAS_DS_LINT.md, REGRAS_STYLELINT_CSS.md, tokens.css, compras-page.css.
