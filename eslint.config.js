// ESLint 9 flat config — Onda 1.2 prevenção bugs MWART (ADR 0209).
//
// Modo ratchet: padrão idêntico ao `ui-lint.yml` PHP-side. Baseline JSON
// `.eslintrc-baseline.json` absorve violações pre-existentes. CI workflow
// `eslint-gate.yml` falha só em REGRESSÃO (delta > 0).
//
// Comando local: `npm run lint`
//
// Refs:
//   - ADR 0209 — ESLint 9 flat-config baseline ratchet
//   - memory/sessions/2026-05-28-arte-prevencao-bugs-mwart-larissa.md Frente 5 F5-B
//   - react.dev — https://react.dev/reference/eslint-plugin-react-hooks/lints/exhaustive-deps

import js from '@eslint/js';
import tsParser from '@typescript-eslint/parser';
import tsPlugin from '@typescript-eslint/eslint-plugin';
import reactHooks from 'eslint-plugin-react-hooks';
import jsxA11y from 'eslint-plugin-jsx-a11y';
import reactRefresh from 'eslint-plugin-react-refresh';
import globals from 'globals';

export default [
  // Ignore patterns globais — evita análise em dist/, vendor/, etc
  {
    ignores: [
      'node_modules/**',
      'vendor/**',
      'public/**',
      'storage/**',
      'bootstrap/cache/**',
      // Build outputs
      'public/build/**',
      'public/build-inertia/**',
      // Generated Wayfinder types (futuro ADR 0210)
      'resources/js/types/wayfinder/**',
      // Bundle entry compiled
      'resources/js/types/**/*.d.ts',
      // Vendor JS legacy UPOS
      'public/js/**',
      'public/vendor/**',
    ],
  },

  // Base recommended pra todos os arquivos JS/TS
  js.configs.recommended,

  // TypeScript files
  {
    files: ['resources/js/**/*.{ts,tsx}'],
    languageOptions: {
      parser: tsParser,
      ecmaVersion: 'latest',
      sourceType: 'module',
      parserOptions: {
        ecmaFeatures: { jsx: true },
      },
      globals: {
        ...globals.browser,
        ...globals.es2024,
        // Globals Inertia/Laravel
        route: 'readonly',
        Ziggy: 'readonly',
        // UPOS legacy globals
        _: 'readonly',  // lodash
        $: 'readonly',  // jQuery
        jQuery: 'readonly',
        moment: 'readonly',
        toastr: 'readonly',
        swal: 'readonly',
        Swal: 'readonly',
        axios: 'readonly',
        echo: 'readonly',
        Echo: 'readonly',
        Pusher: 'readonly',
        // Tier 0 multi-tenant signals
        __current_business_id: 'readonly',
        __mc_business_id: 'readonly',
      },
    },
    plugins: {
      '@typescript-eslint': tsPlugin,
      'react-hooks': reactHooks,
      'jsx-a11y': jsxA11y,
      'react-refresh': reactRefresh,
    },
    rules: {
      // === TypeScript recommended subset (pragmático, não-pedante) ===
      ...tsPlugin.configs.recommended.rules,
      // `no-undef` DESLIGADO em TS — recomendação canônica do typescript-eslint: a regra
      // não entende tipos/namespaces TS e gera falso-positivo em referências de TIPO
      // (ex: `React`, `EventListener`/`HeadersInit` da lib DOM, interfaces locais). Quem
      // pega identificador realmente indefinido é o próprio compilador TS (npm run typecheck).
      // Mantê-la ligada só obrigava a inflar a allowlist de globals. Ref: typescript-eslint.io/troubleshooting/faqs/eslint/#no-undef
      'no-undef': 'off',
      '@typescript-eslint/no-explicit-any': 'warn',
      '@typescript-eslint/no-unused-vars': ['warn', {
        argsIgnorePattern: '^_',
        varsIgnorePattern: '^_',
      }],
      // Permite `!` (non-null assertion) — UPOS usa em vários lugares
      '@typescript-eslint/no-non-null-assertion': 'off',

      // === React Hooks recommended (CRÍTICO — captura R7-class bugs) ===
      // ADR 0209: `exhaustive-deps` sozinho teria detectado o useEffect race
      // condition do R7 ANTES do PR mergear.
      ...reactHooks.configs.recommended.rules,

      // === A11y recommended subset (sem deprecated) ===
      ...jsxA11y.configs.recommended.rules,
      // Larissa opera teclado/scanner — a11y de input crítica
      'jsx-a11y/no-autofocus': 'off', // necessário em Sells/Create input search
      'jsx-a11y/click-events-have-key-events': 'warn', // common pattern shadcn
      'jsx-a11y/no-static-element-interactions': 'warn',

      // === React Refresh (Vite HMR compat) ===
      'react-refresh/only-export-components': ['warn', { allowConstantExport: true }],
    },
  },

  // === DS guard (ds/*) — anti-drift · ratchet ADR 0209 ===
  // Escopo: TELAS (Pages/** + Modules/**). NÃO roda em Components/ui/** (camada
  // canônica onde os padrões legitimamente vivem) nem em Pages/_Showcase/** (stories).
  // Severidade `warn` — o ratchet (config/eslint-baseline.json) trata como gate por
  // delta>0: baseline absorve o hand-roll atual; PR novo que hand-rolar regride.
  // Selectors casam Literal puro E dentro de BinaryExpression/CallExpression (clsx).
  // Ref: prototipo-ui/REGRAS_DS_LINT.md §1 · REGISTRY_DS_COMPONENTES.md · PR-A 9d28f56a0
  {
    files: [
      'resources/js/Pages/**/*.{ts,tsx}',
      'resources/js/Modules/**/*.{ts,tsx}',
    ],
    ignores: [
      'resources/js/Components/ui/**',
      'resources/js/Pages/_Showcase/**',
    ],
    rules: {
      'no-restricted-syntax': ['warn',
        {
          // radio nativo → RadioGroup / Segmented. Dispara no atributo type="radio".
          selector: 'JSXAttribute[name.name="type"][value.value="radio"]',
          message: 'ds/no-native-radio — use <RadioGroup> (@/Components/ui/radio-group) ou <Segmented> pra toggle 2–3 opções. Ver REGISTRY_DS_COMPONENTES.md.',
        },
        {
          // checkbox nativo → Checkbox
          selector: 'JSXAttribute[name.name="type"][value.value="checkbox"]',
          message: 'ds/no-native-checkbox — use <Checkbox> (@/Components/ui/checkbox).',
        },
        {
          // select nativo → Select. <select> lowercase = nativo; <Select> Radix não casa.
          selector: 'JSXOpeningElement[name.name="select"]',
          message: 'ds/no-native-select — use <Select> (@/Components/ui/select).',
        },
        {
          // rounded-xl+ proibido (charter): radius máximo é rounded-md/lg
          selector: 'JSXAttribute[name.name="className"] Literal[value=/\\brounded-(xl|2xl|3xl)\\b/]',
          message: 'ds/no-rounded-xl — radius máximo é rounded-lg (12px). Charter CLAUDE_DESIGN_BRIEFING §4.',
        },
        {
          // cor arbitrária (bg-[#..], text-[#..], border-[#..]…) → token semântico
          selector: 'JSXAttribute[name.name="className"] Literal[value=/(bg|text|border|ring|fill|stroke)-\\[#/]',
          message: 'ds/no-arbitrary-color — sem hex cru. Use token semântico (bg-muted, text-foreground, border-border, text-destructive…).',
        },
        {
          // ds/no-raw-palette-color — QUALQUER cor crua do palette Tailwind num
          // prefixo que carrega cor → token semântico. Fecha o EIXO por FORMA, não
          // por enumeração: o palette é set FECHADO (22 nomes × 11 steps), então
          // nenhum leak de cor novo (red-400, amber-700, cor imprevista) passa —
          // completo por construção. Absorve o antigo no-adhoc-status-text (text-
          // rose/emerald cru) e cobre bg/border/ring/from/via/to/shadow/etc.
          // Casa também variante (hover:text-red-500) por não estar ancorado.
          // Só muda se o Tailwind inventar nome novo (raro → 1 update). Ref §"Onde
          // NÃO dá pra virar máquina" em REGRAS_DS_LINT.md §1.
          selector: 'JSXAttribute[name.name="className"] Literal[value=/\\b(bg|text|border|ring|divide|fill|stroke|from|via|to|accent|caret|decoration|outline|placeholder|shadow|ring-offset)-(slate|gray|zinc|neutral|stone|red|orange|amber|yellow|lime|green|emerald|teal|cyan|sky|blue|indigo|violet|purple|fuchsia|pink|rose)-(50|100|200|300|400|500|600|700|800|900|950)\\b/]',
          message: 'ds/no-raw-palette-color — sem cor crua do Tailwind (bg/text/border-<cor>-<n>). Use token semântico: bg-card/bg-muted, text-foreground/text-muted-foreground, border-border, text-destructive, text-primary…',
        },
        {
          // ds/no-os-btn — classe de shell os-btn tem substituto DS (<Button>).
          // NÃO é eixo-de-valor: é component-substitute, lista CURADA e finita
          // (cresce só quando um shell class ganha equivalente DS). os-page-h /
          // os-drawer-head são scaffold SEM substituto ainda → NÃO entram aqui.
          selector: 'JSXAttribute[name.name="className"] Literal[value=/\\bos-btn\\b/]',
          message: 'ds/no-os-btn — use <Button> (@/Components/ui/button, variant/size), não a classe de shell os-btn.',
        },
        {
          // ds/no-radix-item-empty-value (ADVISORY EXTRA — a defesa REAL é o
          // componente <SafeSelectItem>). Radix Select CRASHA o render INTEIRO
          // (tela branca em prod) se um <SelectItem> tiver value="" literal.
          // NÓ ÚNICO de propósito: pega só a forma LITERAL vazia — value data-
          // driven (map/reduce/helper/`?? ''`/distinct) NÃO é cobrível por lint
          // sintático; pra isso use <SafeSelectItem> (@/Components/ui/SafeSelectItem).
          // Ref: memory/proibicoes.md §5 (2026-06-29) + PR #3405/#3411.
          selector: 'JSXOpeningElement[name.name="SelectItem"] > JSXAttribute[name.name="value"][value.value=""]',
          message: 'ds/no-radix-item-empty-value — <SelectItem value=""> derruba o render do Radix. Use um sentinela não-vazio (ex: __all__) pro item "Todos", ou <SafeSelectItem> pra value data-driven.',
        },
        {
          // idem, forma entre chaves: value={''} / value={""}
          selector: 'JSXOpeningElement[name.name="SelectItem"] > JSXAttribute[name.name="value"] > JSXExpressionContainer > Literal[value=""]',
          message: 'ds/no-radix-item-empty-value — value vazio entre chaves derruba o render do Radix. Use um sentinela não-vazio (ex: __all__) ou <SafeSelectItem>.',
        },
        {
          // ds/no-db-jargon-in-ui — nome de coluna/SQL cru no TEXTO VISÍVEL da UI.
          // Pega JSXText literal com snake_case de coluna (foo_id/foo_total/foo_status/
          // foo_at/foo_amount…) ou "distinct <col>" — jargão de dev que vaza pro usuário
          // final (ex vividos em /compras: "distinct contact_id", "soma final_total mês
          // corrente"). EIXO POR FORMA: qualquer snake_case-de-coluna em texto visível,
          // não uma enumeração de palavras. Só LITERAL — texto data-driven ({x.foo_id})
          // NÃO é cobrível por lint sintático (mesma fronteira honesta do
          // no-radix-item-empty-value acima; a raiz disso se conserta no dado, não aqui).
          // EXCLUI <code>/<pre>/<kbd>: ali o nome de tabela/coluna/rota é DOC TÉCNICA
          // intencional (telas de admin/dev), não jargão vazando — falso-positivo removido
          // na raiz em vez de tolerado no baseline (verificado 2026-07-14, 5 dos 11 hits).
          selector: 'JSXText[value=/\\b(distinct\\s+\\w+|\\w+_(id|total|status|at|amount|qty|price|count))\\b/]:not(JSXElement[openingElement.name.name=/^(code|pre|kbd)$/] > JSXText)',
          message: 'ds/no-db-jargon-in-ui — texto visível com nome de coluna/SQL cru. Use linguagem de negócio PT (ex: "distinct contact_id" → "fornecedores no período"; "final_total" → "total").',
        },
        {
          // ds/no-inline-tablist — COMPONENT-SUBSTITUTE (tipo 2 do ADR 0338, lista
          // curada): a "barra de abas de topo" canoniza em <PageHeaderTabs>
          // (@/Components/shared), consumida via *SubNav do módulo (FinanceiroSubNav/
          // JanaSubNav/PontoSubNav). Hand-rolar `role="tablist"` na tela foi a CAUSA
          // dos 8 topnavs divergentes (dark quebrado, radius errado, abas coladas).
          // Ratchet absorve os tablists legados — só o NOVO hand-roll de tela quebra
          // o delta. Regra de decisão (DOIS papéis, DOIS alvos — onda in-page aberta
          // [W] 2026-07-15): aba de topo que NAVEGA por URL → <PageHeaderTabs>; switch
          // in-page CONTROLADO (value/onChange, sem URL) → <SubNav>. Fronteira honesta:
          // um lint sintático não distingue os dois papéis — por isso ratchet + mensagem
          // que nomeia os dois alvos, não proibição cega. O detector `--roles` cataloga
          // qual papel cada hand-roll cumpre (advisory).
          selector: 'JSXAttribute[name.name="role"][value.value="tablist"]',
          message: 'ds/no-inline-tablist — não hand-role `role="tablist"` na tela. Barra de abas de topo (navega por URL) = <PageHeaderTabs> (@/Components/shared, via *SubNav do módulo); switch in-page controlado (value/onChange, sem URL) = <SubNav> (@/Components/shared). Ver REGISTRY_DS_COMPONENTES.md §"barra de abas de topo" / §"Sub-navegação contextual".',
        },
        {
          // ds/no-inline-raw-color — EIXO valor-vs-token (tipo 1 do ADR 0338) num
          // SURFACE NOVO: o className já fecha por forma (no-raw-palette-color /
          // no-arbitrary-color), mas `style={{ borderBottomColor: 'oklch(0.93 …)' }}`
          // inline escapava de TODO gate — o conformance-gate.mjs e o stylelint
          // color-no-hex só olham arquivos .css, nunca style inline de JSX/TSX. Foi o
          // BURACO DO DARK: hardcode de tom claro (L alto) num inline quebra o modo
          // escuro sem alarme (bug tab-nav pego pelo [W] no olho, 2026-07). Fecha por
          // FORMA DO VALOR: qualquer função de cor (rgb/rgba/hsl/hsla/oklch/oklab/lab/
          // lch/color) ou hex literal DENTRO de um style attribute. `var(--x)` NÃO casa
          // (é token dark-aware) → é exatamente a saída correta. Completo por construção
          // pro surface inline; residual honesto = nome de cor nu ('white'/'red') não
          // casa (ambíguo vs 'transparent'/'inherit'/'currentColor') — fica humano.
          // Escopo do bloco = telas (Pages/Modules); os componentes canônicos
          // (Components/ui + shared, ex PageHeaderTabs) legitimamente carregam o valor
          // do token na camada DS e estão FORA deste files[] — por design.
          selector: 'JSXAttribute[name.name="style"] Literal[value=/(#[0-9a-fA-F]{3,8}\\b|rgba?\\(|hsla?\\(|oklch\\(|oklab\\(|lab\\(|lch\\(|color\\()/]',
          message: 'ds/no-inline-raw-color — sem cor/borda/sombra crua em style inline. Use token dark-aware: var(--accent)/var(--border)/var(--text)/var(--surface)… ou classe utilitária semântica. Hardcode de tom claro quebra o dark sem alarme (bug tab-nav 2026-07).',
        },
        {
          // ds/no-handrolled-combobox — COMPONENT-SUBSTITUTE (tipo 2 do ADR 0338,
          // lista curada): o "campo de busca com dropdown" (combobox/autocomplete)
          // canoniza na composição Popover + Command (cmdk, @/Components/ui/{popover,
          // command}) — o Command é o MOTOR (input de busca + lista filtrada +
          // navegação de teclado + a11y role=combobox/listbox/option de fábrica).
          // Hand-rolar o combobox (input próprio + <ul role="listbox"> + onKeyDown
          // ArrowUp/Down) reimplementa esse motor de um jeito ligeiramente diferente
          // a cada tela → a11y divergente/bugada é a CAUSA (5 hand-rolls catalogados:
          // Cliente/PlanoConta-Combobox, GradeProductCombobox, Customer/Product-
          // SearchAutocomplete). SELECTOR PRECISO (não broad): pega só os signals que
          // NUNCA aparecem no consumo canônico — `aria-autocomplete` (o cmdk trata a11y
          // internamente, a tela nunca escreve isso) e `role="combobox"` num <input>
          // NATIVO (o padrão canônico põe role=combobox no <Button> trigger, jamais no
          // input). Assim ServiceOrders/Create (Button role=combobox + Command) NÃO é
          // pego. Ratchet absorve os hand-rolls atuais; só o NOVO quebra o delta.
          // Fronteira honesta (fica pro detector --roles, advisory): o hand-roll com
          // <Button> trigger + <ul role="listbox"> à mão é indistinguível do canônico
          // sem análise de import — quem pega isso é component-registry-check --roles.
          selector: 'JSXAttribute[name.name="aria-autocomplete"], JSXOpeningElement[name.name="input"] > JSXAttribute[name.name="role"][value.value="combobox"]',
          message: 'ds/no-handrolled-combobox — não hand-role o combobox/autocomplete (input + lista role="listbox" à mão). Campo de busca com dropdown = <Command> (@/Components/ui/command, motor cmdk) dentro de <Popover> (@/Components/ui/popover). Ref: Pages/OficinaAuto/ServiceOrders/Create.tsx. Ver REGISTRY_DS_COMPONENTES.md §"Combobox".',
        },
        {
          // ds/no-handrolled-status-pill — COMPONENT-SUBSTITUTE (tipo 2 do ADR 0338,
          // lista curada). O papel "pílula de STATUS" (estado success/warning/danger/
          // info/neutral) canoniza em <Badge variant="…"> (@/Components/ui/badge) OU no
          // wrapper de domínio <StatusBadge kind value> (@/Components/shared/StatusBadge,
          // que mapeia string-de-domínio → tone+label sobre Badge). Hand-rolar o pill
          // (`<span className="… rounded-full … px-… bg-*-soft text-*-fg">`) reintroduz o
          // shape fora da camada DS — foi o mesmo vetor dos topnavs divergentes.
          // Fecha o subconjunto MECÂNICO: um className Literal que junta, no MESMO
          // string, rounded-full + padding horizontal (px-) + um TOKEN de status
          // (success/warning/destructive/info)-(soft/fg). O token é o sinal preciso
          // (não é palette cru — esse já cai no no-raw-palette-color; não dupla-conta):
          // é EXATAMENTE o pill que hoje escapa de TODO gate por usar o token certo mas
          // hand-rolar a forma. `px-` exclui o círculo de ícone (h-10 w-10 rounded-full
          // sem padding). Fronteira honesta (a confiança termina no AST): cor-em-variável
          // (StatusPill/FrescorPill — o token vem de um mapa) fica em literais separados
          // e não casa; e `rounded` reto (não -full) não casa. Esses ficam pro detector
          // de papel (component-registry-check --roles) + migração humana. Ver
          // REGISTRY_DS_COMPONENTES.md §"pílula de status" · REGRAS_DS_LINT.md §1.
          selector: 'JSXAttribute[name.name="className"] Literal[value=/(?=[\\s\\S]*\\brounded-full\\b)(?=[\\s\\S]*\\bpx-\\d)(?=[\\s\\S]*\\b(?:success|warning|destructive|info)-(?:soft|fg)\\b)/]',
          message: 'ds/no-handrolled-status-pill — não hand-role a pílula de status. Use <Badge variant="success|warning|danger|info|neutral"> (@/Components/ui/badge) ou <StatusBadge kind value> (@/Components/shared/StatusBadge). Ver REGISTRY_DS_COMPONENTES.md §"pílula de status".',
        },
      ],
    },
  },

  // JS files (vite.config.js, etc) — config legibility only
  {
    files: ['**/*.{js,mjs,cjs}'],
    languageOptions: {
      ecmaVersion: 'latest',
      sourceType: 'module',
      globals: {
        ...globals.node,
        ...globals.es2024,
      },
    },
    rules: {
      'no-unused-vars': ['warn', { argsIgnorePattern: '^_' }],
    },
  },
];
