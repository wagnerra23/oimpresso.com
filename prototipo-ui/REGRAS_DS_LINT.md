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
  {
    // ds/no-inline-tablist — COMPONENT-SUBSTITUTE (tipo 2, lista curada). Barra de
    // abas de topo canoniza em <PageHeaderTabs> (@/Components/shared). Hand-rolar
    // role="tablist" na tela foi a CAUSA dos 8 topnavs divergentes.
    selector: 'JSXAttribute[name.name="role"][value.value="tablist"]',
    message: 'ds/no-inline-tablist — barra de abas de topo = <PageHeaderTabs>, não role="tablist" hand-rolado.',
  },
  {
    // ds/no-inline-raw-color — EIXO valor-vs-token num SURFACE NOVO (style inline).
    // O className fecha por forma; o style={{}} inline escapava de TODO gate (o
    // conformance-gate.mjs + stylelint só olham .css). Foi o BURACO DO DARK.
    selector: 'JSXAttribute[name.name="style"] Literal[value=/(#[0-9a-fA-F]{3,8}\\b|rgba?\\(|hsla?\\(|oklch\\(|oklab\\(|lab\\(|lch\\(|color\\()/]',
    message: 'ds/no-inline-raw-color — sem cor/borda/sombra crua em style inline. Use token dark-aware (var(--accent)…).',
  },
  {
    // ds/no-handrolled-combobox — COMPONENT-SUBSTITUTE (tipo 2, lista curada).
    // Campo de busca com dropdown canoniza na composição Popover + Command (cmdk,
    // @/Components/ui/{popover,command}) — o Command é o MOTOR (input + lista +
    // teclado + a11y de fábrica). SELECTOR PRECISO: pega só os signals que NUNCA
    // aparecem no consumo canônico — aria-autocomplete e role="combobox" num <input>
    // nativo (o padrão certo põe role=combobox no <Button> trigger). Assim
    // ServiceOrders/Create (Button + Command) não é pego.
    selector: 'JSXAttribute[name.name="aria-autocomplete"], JSXOpeningElement[name.name="input"] > JSXAttribute[name.name="role"][value.value="combobox"]',
    message: 'ds/no-handrolled-combobox — campo de busca com dropdown = <Command> (motor cmdk) dentro de <Popover>, não input + role="listbox" à mão. Ref: ServiceOrders/Create.tsx.',
  },
  {
    // ds/no-handrolled-status-pill — COMPONENT-SUBSTITUTE (tipo 2, lista curada). A
    // "pílula de status" canoniza em <Badge variant="success|warning|danger|info|neutral">
    // (@/Components/ui/badge) OU no wrapper de domínio <StatusBadge kind value>
    // (@/Components/shared). Fecha o subconjunto MECÂNICO: um className Literal que junta,
    // no MESMO string, rounded-full + px- + TOKEN de status (não palette cru — esse já cai
    // no no-raw-palette-color). É o pill que hoje escapa de TODO gate por usar o token
    // certo mas hand-rolar a forma. px- exclui círculo de ícone (h-10 w-10 sem padding).
    selector: 'JSXAttribute[name.name="className"] Literal[value=/(?=[\\s\\S]*\\brounded-full\\b)(?=[\\s\\S]*\\bpx-\\d)(?=[\\s\\S]*\\b(?:success|warning|destructive|info)-(?:soft|fg)\\b)/]',
    message: 'ds/no-handrolled-status-pill — use <Badge variant="…"> ou <StatusBadge kind value>, não a pílula de status hand-rolada.',
  },
],
```

> ### Combobox: selector PRECISO, não broad ([ADR proposta tab-nav-canonico §Ondas futuras])
>
> Ao contrário do `ds/no-inline-tablist` (broad: pega TODO `role="tablist"` e deixa o ratchet absorver os in-panel), o combobox **não** tem um signal broad limpo — `role="listbox"` é sobrecarregado (listas de mensagem, keyboard-nav de qualquer coisa usam listbox e **não** são combobox). Então a regra fecha por signal PRECISO do input hand-rolado: `aria-autocomplete` (a tela nunca escreve isso quando usa `Command` — o cmdk trata a a11y internamente) e `role="combobox"` num `<input>` **nativo** (o consumo canônico põe `role=combobox` no `<Button>` trigger, jamais no input). Isso pega os hand-rolls de input (`ClienteCombobox`, `PlanoContaCombobox`) sem falso-positivar o padrão certo. **Residual honesto:** o hand-roll com `<Button>` trigger + `<ul role="listbox">` à mão (`GradeProductCombobox`, `Customer/ProductSearchAutocomplete`) é indistinguível do canônico sem análise de **import** — quem cataloga esses é o detector `component-registry-check --roles` (papel `combobox`, âncora = importar `@/Components/ui/command`), advisory. Lint = signal per-nó; detector = análise de import. Divisão honesta de trabalho, os dois fecham por forma no que cada um consegue ver.

> ### O surface INLINE (`style={{}}`) — o buraco do dark ([ADR proposta tab-nav-canonico])
>
> O eixo valor-vs-token do [ADR 0338](../memory/decisions/0338-ds-lint-eixo-valor-token-fecha-por-forma.md) fechava o **className**. Faltava o **style inline**: `style={{ borderBottomColor: 'oklch(0.93 …)' }}` não é pego por `no-raw-palette-color` (é objeto JS, não classe), nem pelo `conformance-gate.mjs`/stylelint (só olham `.css`). Foi por aí que um **hardcode de tom claro** entrou numa aba e **quebrou o dark sem alarme**. `ds/no-inline-raw-color` fecha esse surface **por forma do valor** — qualquer função de cor (`rgb/rgba/hsl/hsla/oklch/oklab/lab/lch/color`) ou hex literal dentro de um `style`. `var(--x)` **não** casa (é a saída dark-aware correta). Residual honesto: **nome de cor nu** (`'white'`/`'red'`) não casa — ambíguo vs `'transparent'`/`'inherit'`/`'currentColor'` — fica humano. Os componentes canônicos (`Components/ui` + `shared`) estão **fora** do `files[]` do bloco: ali o valor do token vive legitimamente na camada DS.

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
| `ds/no-inline-tablist` | `role="tablist"` hand-rolado na tela | `<PageHeaderTabs>` (barra de abas de topo) |
| `ds/no-inline-raw-color` | cor/borda/sombra crua em `style={{}}` inline | token dark-aware (`var(--…)`) |
| `ds/no-handrolled-combobox` | `aria-autocomplete` / `role="combobox"` num `<input>` nativo | `<Command>` (motor cmdk) dentro de `<Popover>` — campo de busca com dropdown |
| `ds/no-handrolled-status-pill` | pill `rounded-full` + `px-` + token de status (`*-soft/-fg`) inline | `<Badge variant="…">` / `<StatusBadge kind value>` |
| `ds/no-handrolled-form-section` | `rounded-lg border p-4\|p-5` | FormSection |
| `ds/icons` (opt) | import direto `lucide-react` | icon-registry |
