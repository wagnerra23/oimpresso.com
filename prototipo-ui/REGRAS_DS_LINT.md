# REGRAS `ds/*` — spec pro Code (anti-drift, entra no ratchet existente)

> **Pra Code [CL]:** estas regras entram no `eslint.config.js` (flat, ADR 0209) e rodam no ratchet existente — baseline `config/eslint-baseline.json`, regen `npm run lint:baseline:write`, gate `scripts/eslint-baseline.mjs` (`eslint-gate.yml`) **falha só em regressão (delta>0)**. Depois de adicionar as regras, rodar `npm run lint:baseline:write` pra absorver o hand-roll existente; daí o gap **para de crescer** sem travar a dívida atual. O `config/eslint-baseline.json` resultante = o **mapa de drift quantificado dos 745 arquivos** (responde o "todos" com número real).
> **Escopo:** `resources/js/Pages/**` e `resources/js/Modules/**` (telas). **Não** aplicar em `resources/js/Components/ui/**` (é onde os padrões canônicos legitimamente vivem).

---

## 1. Regras via `no-restricted-syntax` (cola direta)

Adicionar ao bloco `rules` do override `files: ['resources/js/**/*.{ts,tsx}']`. Severidade `warn` (o ratchet trata como gate por delta).

```js
'no-restricted-syntax': ['warn',
  {
    // radio nativo → RadioGroup / Segmented. Selector simples e robusto:
    // dispara no atributo type="radio" (sem :has, evita incompatibilidade esquery).
    selector: 'JSXAttribute[name.name="type"][value.value="radio"]',
    message: 'ds/no-native-radio — use <RadioGroup> (@/Components/ui/radio-group) ou <Segmented> pra toggle 2–3 opções. Ver REGISTRY_DS_COMPONENTES.md.',
  },
  {
    // checkbox nativo → Checkbox
    selector: 'JSXAttribute[name.name="type"][value.value="checkbox"]',
    message: 'ds/no-native-checkbox — use <Checkbox> (@/Components/ui/checkbox).',
  },
  {
    // select nativo → Select
    selector: 'JSXOpeningElement[name.name="select"]',
    message: 'ds/no-native-select — use <Select> (@/Components/ui/select).',
  },
  {
    // rounded-xl+ proibido (charter): radius máximo é rounded-md/lg
    selector: 'JSXAttribute[name.name="className"] Literal[value=/\\brounded-(xl|2xl|3xl)\\b/]',
    message: 'ds/no-rounded-xl — radius máximo é rounded-lg (12px). Charter CLAUDE_DESIGN_BRIEFING §4.',
  },
  {
    // cor arbitrária (bg-[#..], text-[#..], border-[#..]) → token semântico
    selector: 'JSXAttribute[name.name="className"] Literal[value=/(bg|text|border|ring|fill|stroke)-\\[#/]',
    message: 'ds/no-arbitrary-color — sem hex cru. Use token semântico (bg-muted, text-foreground, border-border, text-destructive…).',
  },
  {
    // QUALQUER cor crua do palette Tailwind → token. Fecha o EIXO por FORMA
    // (set fechado 22 nomes × 11 steps), não por enumeração de leaks conhecidos.
    // Absorve o antigo no-adhoc-status-text. Casa variante (hover:text-red-500).
    selector: 'JSXAttribute[name.name="className"] Literal[value=/\\b(bg|text|border|ring|divide|fill|stroke|from|via|to|accent|caret|decoration|outline|placeholder|shadow|ring-offset)-(slate|gray|zinc|neutral|stone|red|orange|amber|yellow|lime|green|emerald|teal|cyan|sky|blue|indigo|violet|purple|fuchsia|pink|rose)-(50|100|200|300|400|500|600|700|800|900|950)\\b/]',
    message: 'ds/no-raw-palette-color — sem cor crua do Tailwind. Use token semântico: bg-card/bg-muted, text-foreground/text-muted-foreground, border-border, text-destructive, text-primary…',
  },
  {
    // classe de shell os-btn tem substituto DS (<Button>). Lista CURADA/finita —
    // os-page-h / os-drawer-head são scaffold sem substituto → NÃO entram.
    selector: 'JSXAttribute[name.name="className"] Literal[value=/\\bos-btn\\b/]',
    message: 'ds/no-os-btn — use <Button> (@/Components/ui/button), não a classe de shell os-btn.',
  },
],
```

> **Nota:** os selectors de className casam tanto `Literal` puro quanto o `Literal` dentro de `BinaryExpression` (ex.: `'rounded-xl border ' + (danger ? …)`) — pega os dois. `<select>` lowercase = nativo; `<Select>` (Radix) não casa (esquery é case-sensitive).

> ### Por que FORMA > enumeração (a decisão "virar máquina")
>
> Enumerar leak-a-leak (`stone|slate|gray`…) = **sempre um leak atrás**: incompleto por construção (mesma lição da âncora-guard, `memory/proibicoes.md` 2026-06-30). A regra `no-raw-palette-color` casa a **forma** do eixo — todo o palette Tailwind, que é **conjunto fechado**. Assim nenhum valor cru novo (`red-400`, `amber-700`, cor imprevista) passa: **completo por construção pra o eixo de cor**. Fica seguro porque **forma-completa + ratchet**: a regra acende em centenas de usos legados, o `lint:baseline:write` absorve toda a dívida, e só o **delta novo** quebra CI — sem flag day.
>
> **O que NÃO fecha por forma (residual honesto, fica humano/charter):**
> - **Component-substitute** (`os-btn`→`<Button>`, `<select>`→`<Select>`): não tem shape genérico sem pegar scaffold legítimo → lista **curada e finita** (cresce só quando surge substituto). `os-page-h`/`os-drawer-head` **não** entram.
> - **Uso semântico errado** (`text-foreground` onde era `text-muted-foreground`), spacing/layout drift, família de classe nova não-mapeada. Nenhum lint pega "tokenizado, mas no token errado".

## 2. Regra custom (form-section composto — AST não pega via string simples)

`<section>`/`<div>` com `className` contendo `rounded-lg border` **e** (`p-4`|`p-5`) = form-section hand-rolled. Pequeno plugin local `eslint-plugin-ds` (ou regra inline com `JSXAttribute` + checagem do value):

```
ds/no-handrolled-form-section
  alvo: JSXOpeningElement section|div com className casando /rounded-lg.*\bborder\b/ E /\bp-[45]\b/
  msg:  use <FormSection> (@/Components/ui/form-section) — header + .form-grid embutidos.
```

## 3. (Opcional) lockdown de import de ícone

Casa com o `icon-registry` já existente no repo:

```js
'no-restricted-imports': ['warn', { paths: [{
  name: 'lucide-react',
  message: 'ds/icons — importe via @/Components/ui/icon-registry, não direto de lucide-react.',
}]}],
```

---

## 4. Relatório → GovernanceV4 (não construir painel novo)

Script `npm run ds:report` (node): roda ESLint com formato JSON, agrupa violações `ds/*` por módulo (`Pages/<Mod>` / `Modules/<Mod>`), emite:
- contagem por módulo + total
- `% de Pages que importam só de @/Components/ui`

Saída vira **dimensão "Adoção DS"** num scorecard YAML (`memory/governance/scorecards/`) e/ou alimenta o padrão do `DriftAlertBanner` (verde quando 0). Métrica de saúde: total `ds/*` **tende a 0**; nenhum PR aumenta o baseline.

---

## 5. Definição de pronto (todo componente DS futuro)

Promover componente = entregar o **tripé**: impl em `@/Components/ui` + regra `ds/no-handrolled-<x>` + story em `Pages/_Showcase/`. Sem isso, não é "pronto" — é CSS órfão que a tela vai hand-rolar em volta.

---

## Resumo das regras

| ID | Pega | Use |
|---|---|---|
| `ds/no-native-radio` | `<input type=radio>` | RadioGroup / Segmented |
| `ds/no-native-checkbox` | `<input type=checkbox>` | Checkbox |
| `ds/no-native-select` | `<select>` | Select |
| `ds/no-rounded-xl` | `rounded-xl\|2xl\|3xl` | rounded-lg máx |
| `ds/no-arbitrary-color` | `bg-[#..]`, `text-[#..]` | token semântico |
| `ds/no-raw-palette-color` | `bg/text/border-<cor>-<n>` (palette Tailwind, set fechado) | token semântico |
| `ds/no-os-btn` | classe de shell `os-btn` | `<Button>` |
| `ds/no-handrolled-form-section` | `rounded-lg border p-4\|p-5` | FormSection |
| `ds/icons` (opt) | import direto `lucide-react` | icon-registry |
