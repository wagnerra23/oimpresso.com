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
    // texto de status colorido na mão → FieldError/FieldSuccess/Alert
    selector: 'JSXAttribute[name.name="className"] Literal[value=/\\btext-(rose|red|emerald|green)-(500|600|700)\\b/]',
    message: 'ds/no-adhoc-status-text — use <FieldError>/<FieldSuccess> ou <Alert> (cores semânticas), não text-rose/emerald cru.',
  },
],
```

> **Nota:** os selectors de className casam tanto `Literal` puro quanto o `Literal` dentro de `BinaryExpression` (ex.: `'rounded-xl border ' + (danger ? …)`) — pega os dois. `<select>` lowercase = nativo; `<Select>` (Radix) não casa (esquery é case-sensitive).

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
| `ds/no-adhoc-status-text` | `text-rose/emerald-600` | FieldError/Success/Alert |
| `ds/no-handrolled-form-section` | `rounded-lg border p-4\|p-5` | FormSection |
| `ds/icons` (opt) | import direto `lucide-react` | icon-registry |
