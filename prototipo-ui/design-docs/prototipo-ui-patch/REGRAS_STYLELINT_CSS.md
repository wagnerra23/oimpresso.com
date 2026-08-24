# REGRAS Stylelint `.css` — a metade que o guard ESLint NÃO cobre

> **Pra Code [CL]:** o guard `ds/*` (PR-B, ADR 0209) é AST em `className` de TSX — por design **não toca `.css`** (`PR-B-guard-ds.md`: "definir `--cw-ok: oklch(…)` é a definição do token"). Resultado: **hex cru dentro de `.css` de módulo passa batido** (ex.: `compras` tinha ~40 hex + namespace `--cmp-*` paralelo; `cowork-financeiro-bundle.css` tem stage-colors `#fef3c7`, avatares `#5b6cff`…). Este doc fecha esse buraco com **Stylelint**, no mesmo padrão de ratchet (falha só em regressão).
> **Origem:** Cowork [CC] · 2026-05-31 · **PR alvo:** `feat/ds-guard-stylelint-css`

---

## 1. Por que Stylelint e não ESLint

ESLint lê JS/TS. Quem entende seletor/declaração CSS é o **Stylelint**. As duas ferramentas são complementares:

| Camada | Ferramenta | Pega |
|---|---|---|
| `className` em `.tsx` | ESLint `ds/*` (PR-B, já no main) | `bg-[#..]`, `rounded-xl`, `<select>`, `text-rose-600` |
| declaração em `.css` | **Stylelint (este doc)** | `color:#1f3a5f`, `background:#fbf9f3`, `border-radius:5px` |

## 2. Escopo (crítico pra não dar falso-positivo)

- **Aplicar em:** `resources/css/**/*.css` e qualquer `*-page.css` / módulo que **consome** tokens.
- **NUNCA aplicar em:** o(s) arquivo(s) que **definem** os tokens. **[CC 2026-05-31 · correção §10.4]** A fonte canônica real no repo é **`resources/css/app.css`** (`LARAVEL_REPO_CONTEXT §10.4`: "use sempre as variáveis do shell, definidas em `app.css`") — NÃO `tokens.css` (esse é espelho do protótipo Cowork). **[CL]: antes de fixar `ignoreFiles`, grepe o `main`** (`grep -rl '^\s*--accent' resources/css`) pra confirmar QUAIS arquivos realmente DEFINEM os tokens hoje e liste só esses. Não confie no meu palpite — o canon pode ter mudado; você valida contra o `main` (§10.4). Onde `--surface:#ffffff` é a definição legítima, é fonte, não drift.

## 3. Config (cola direta — `stylelint.config.js`)

```js
/** @type {import('stylelint').Config} */
module.exports = {
  ignoreFiles: [
    // [CC 2026-05-31] confirmar via grep no main (§10.4: fonte = resources/css/app.css,
    // NÃO tokens.css que é espelho do protótipo). Listar SÓ os arquivos que DEFINEM token.
    'resources/css/app.css',
    'resources/css/tokens.css',       // se existir no main
    'resources/css/design-system.css',// se existir no main
    '**/node_modules/**',
  ],
  rules: {
    // ds/css-no-hex — sem hex cru fora dos arquivos de token.
    // Exceção: #fff/#ffffff (branco puro = --surface) é tolerado p/ não floodar.
    'color-no-hex': [true, {
      message: 'ds/css-no-hex — sem hex cru. Use token (var(--accent), var(--text), var(--border)…) ou color-mix sobre token. Definição de token vive só em tokens.css/design-system.css.',
      severity: 'warning',
    }],
    // ds/css-radius-scale — radius só pela escala do token.
    'declaration-property-value-disallowed-list': [{
      'border-radius': ['/^(?!.*(var\\(--radius|99px|50%|9999px|0)).*\\d+px/'],
    }, {
      message: 'ds/css-radius-scale — radius pela escala: var(--radius-sm|--radius|--radius-md|--radius-lg). 99px/50% (pill/dot) ok.',
      severity: 'warning',
    }],
  },
};
```

## 4. Ratchet (mesmo padrão do ESLint, falha só em regressão)

Stylelint não tem baseline nativo. Duas opções (escolher a mais barata no repo):

- **A (preferida):** `stylelint --formatter json` → script `scripts/stylelint-baseline.mjs` que conta warnings e compara com `config/stylelint-baseline.json` (espelha o padrão de `scripts/eslint-baseline.mjs`, ADR 0209). Gate falha se `delta>0`.
- **B (mais simples):** `stylelint --max-warnings <N>` no CI, onde `N` = contagem atual congelada. Sobe o piso a cada migração, nunca desce.

Rodar uma vez pra congelar o baseline (absorve o hand-roll atual: compras já foi zerado no protótipo, falta o repo). Daí o gap **para de crescer**.

## 5. Adição ao CI (`.github/workflows/`)

Step novo no `eslint-gate.yml` (ou workflow irmão `stylelint-gate.yml`):

```yaml
- run: npx stylelint "resources/css/**/*.css" --formatter json > stylelint-report.json || true
- run: node scripts/stylelint-baseline.mjs   # falha se delta>0
```

## 6. Dívida atual conhecida (P0 da migração CSS)

| Arquivo | Drift | Status protótipo |
|---|---|---|
| `compras-page.css` | namespace `--cmp-*` + ~40 hex + radius 4/5px | ✅ migrado no Cowork (0 hex) — espelhar no repo |
| `cowork-financeiro-bundle.css` | stage-colors `#fef3c7…`, avatares gradiente `#5b6cff…`, status `#b91c1c` | ⬜ pendente |
| `tokens.css` | era azul 220, **dessincronizado do DS v4 roxo (ADR 0235)** | ✅ sincronizado p/ `oklch(0.55 0.15 295)` |

> Meta: warnings `ds/css-*` → 0, igual ao `ds/*` do ESLint. Os dois baselines juntos = mapa completo do drift (TSX + CSS).

---

## 7. `ds/css-no-accent-redeclare` — NÃO redefinir `--accent` fora da fonte (origem: P1 faxina · L-10/L-19)

> **[CC 2026-05-31]** A faxina de tokens (P1 do diagnóstico) achou o buraco que nem o ESLint nem as regras §3 cobrem: **redeclarar a custom-property `--accent`** (e `--accent-2`/`--accent-soft`) **em CSS que não é o arquivo-fonte**. Foi a causa-raiz de **dois** incidentes: L-10 (azul preso porque `.mockup-page` redefinia `--accent`) e L-19 (`.mockup-page` com `--accent` hardcoded ignorando o tweak `accentHue`).

**Invariante a travar:** `--accent`, `--accent-2`, `--accent-soft` (e o set canônico de tokens) só podem ser **DEFINIDOS** no(s) arquivo(s)-fonte (`app.css` por §10.4 — confirmar no `main`). Redefinir em qualquer outro `.css` = drift silencioso. No runtime quem vence é `app.jsx` (inline via `accentHue`); os `:root` de stylesheet são só fallback — então redeclarar não só é redundante como **destoa** do que o usuário vê.

**[CL]: você escaneia o padrão, não eu.** Grepe o `main` (`grep -rn -- '--accent\s*:' resources/css`) pra ver onde HOJE se redefine; o que estiver fora do arquivo-fonte é alvo. Mecanismo Stylelint sugerido (escolha o mais limpo — `ignoreFiles` já exclui a fonte legítima):

```js
// merge no objeto declaration-property-value-disallowed-list existente (§3):
'/^--accent($|-)/': ['/[\\s\\S]+/'],   // qualquer valor → fora do ignoreFiles é violação
// message: 'ds/css-no-accent-redeclare — --accent só é DEFINIDO em app.css. Aqui herde; não redeclare. Runtime real = app.jsx inline (L-10/L-19).'
```

Mesmo ratchet do §4 (falha só em regressão). **Natureza: Tier 0 (tooling), aguardando [W]** — junto do guard §1-6, não é PR autônomo. Não disparar isolado.
