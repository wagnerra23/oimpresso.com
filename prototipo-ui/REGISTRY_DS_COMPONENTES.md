# REGISTRY — Componentes canônicos do DS

> Fonte da verdade do que existe pra consumir. **Se está aqui, não hand-rola.**
> Superfície única de import: **`@/Components/ui`** (shadcn + Radix + CVA, bridge visual via `cowork-fields.css` + variantes `cowork-*`).
> Última atualização: 2026-06-03 (Cowork [CC] + [CL]) · base: leitura de `resources/js/Components/ui/*` @ main.

> 🎨 **Kit DS v6 (régua visual):** os 11 componentes canônicos desenhados (em token, claro/escuro de
> fábrica) vivem em [`ds-v6/showcase.html`](ds-v6/showcase.html). O **reuse-mapping** "kit `c-*` → componente
> React no repo" está em [`ds-v6/REUSE_MAPPING.md`](ds-v6/REUSE_MAPPING.md) — 8/11 já reusam o que está
> abaixo; 3 (`c-id` ficha-360 · `c-tl` unificada · `c-nba`) são buraco do DS (Tier-0, nascem na 1ª tela que
> os consome). A **receita** de como montar tela do kit: [`ds-v6/receita.html`](ds-v6/receita.html).

## Como ler

- **Existe** = componente React já no repo.
- **Cowork** = tem visual canon Cowork aplicado (variante/`.cw-*`) — usar essa, não a shadcn pura.
- **Substitui (anti-pattern)** = o que NÃO se escreve mais à mão. É isto que vira regra `ds/*`.

---

## Form controls (núcleo do drift de cadastro)

| Componente | Import | Existe | Cowork | Substitui (anti-pattern) |
|---|---|---|---|---|
| **Input** | `@/Components/ui/input` | ✅ | ✅ default (`cw-input`, ADR UI-0015) | `<input className="border rounded-md h-9 …">` cru |
| **Textarea** | `@/Components/ui/textarea` | ✅ | ✅ | `<textarea className="border …">` cru |
| **Select** | `@/Components/ui/select` | ✅ | shadcn/Radix | `<select className="h-9 rounded-md border …">` nativo |
| **RadioGroup** / RadioGroupItem | `@/Components/ui/radio-group` | ✅ | Radix | **`<input type="radio">` nativo** ← o drift do Create |
| **Checkbox** | `@/Components/ui/checkbox` | ✅ | Radix | `<input type="checkbox">` nativo |
| **Switch** | `@/Components/ui/switch` | ✅ | Radix | toggle hand-rolled / checkbox pra booleano de setting |
| **Label** | `@/Components/ui/label` | ✅ | Radix | `<label className="text-xs …">` solto |
| **Button** | `@/Components/ui/button` | ✅ | ✅ `variant="cowork-primary\|cowork-ghost"`, `size="cowork"` | `<button className="bg-… rounded-md px-…">` cru |
| **NumericInputPtBR** (moeda/decimal) | `@/Components/ui/numeric-input-ptbr` | ✅ 2026-06-11 (promovido de Sells) | herda Input cowork | `<Input type="number" onChange={Number(...)}>` ← bug R$ Larissa 2026-05-27 (locale) |
| **DocumentInput** (CPF/CNPJ) | `@/Components/ui/document-input` | ✅ 2026-06-11 | herda Input cowork | hand-wiring `maskCPF/maskCNPJ + validateCPF` repetido por tab (drawer Cliente Wave C-FE) |
| **PhoneInput** (telefone BR) | `@/Components/ui/phone-input` | ✅ 2026-06-11 | herda Input cowork | hand-wiring `maskTel` + Input por tela |

## Overlays / navegação / feedback

| Componente | Import | Existe | Substitui (anti-pattern) |
|---|---|---|---|
| **Dialog** | `@/Components/ui/dialog` | ✅ | modal hand-rolled com `fixed inset-0` |
| **AlertDialog** | `@/Components/ui/alert-dialog` | ✅ | confirm() / modal de confirmação caseiro |
| **Sheet** (drawer) | `@/Components/ui/sheet` | ✅ | drawer lateral hand-rolled |
| **Popover** | `@/Components/ui/popover` | ✅ | dropdown posicionado na mão |
| **DropdownMenu** | `@/Components/ui/dropdown-menu` | ✅ | menu kebab caseiro |
| **Tooltip** | `@/Components/ui/tooltip` | ✅ | `title=""` / tooltip CSS ad-hoc |
| **Command** (⌘K) | `@/Components/ui/command` | ✅ | palette hand-rolled |
| **Alert** | `@/Components/ui/alert` | ✅ | banner `bg-red-50 border …` cru |
| **Badge** | `@/Components/ui/badge` | ✅ | pílula `<span className="rounded-full px-2 …">` cru |
| **Avatar** | `@/Components/ui/avatar` | ✅ | inicial em `<div>` redondo na mão |
| **Card** | `@/Components/ui/card` | ✅ | `<div className="rounded-lg border bg-card p-…">` |
| **Skeleton** | `@/Components/ui/skeleton` | ✅ | `animate-pulse bg-muted` na mão |
| **Separator** | `@/Components/ui/separator` | ✅ | `<hr>` / `border-t` solto |
| **ScrollArea** · **Resizable** | `@/Components/ui/{scroll-area,resizable}` | ✅ | — |
| **icon-registry** | `@/Components/ui/icon-registry` | ✅ | import solto de `lucide-react` fora do registry |

> Os ✅ "Existe" são fato (lidos do diretório). Detalhe de variante confirmado pra Input/Button/RadioGroup; demais seguem convenção shadcn + bridge cowork. Code valida ao criar as stories.

---

## Navegação de página (barra de abas de topo)

> Papel **"barra de abas de topo"** = a fita horizontal de sub-navegação abaixo do título (`Unificado · Pagar · Receber · ⋯ Mais`). Fiel ao protótipo `prototipo-ui/cowork/clientes-page.css` `.cli-moduletopnav-tab`. Foi a origem do drift catalogado: **8 componentes divergentes** (dark quebrado por cor inline hardcoded, radius errado). Consolidado num único canônico. Detalhe: [ADR proposta tab-nav-canonico](../memory/decisions/proposals/2026-07-15-tab-nav-canonico-e-componente-por-papel.md).

| Componente | Import | Existe | Substitui (anti-pattern) |
|---|---|---|---|
| **PageHeaderTabs** | `@/Components/shared/PageHeaderTabs` | ✅ canon | `ModuleTopNav` · `PageHeaderModuleNav` · `FiscalModuleTopNav` · **`role="tablist"` hand-rolado na tela** · qualquer `<nav>` de sub-tabs com cor/borda em `style={{}}` inline |

> ⚠️ **`SubNav` (`@/Components/shared/SubNav`) NÃO entra aqui.** É papel DISTINTO — sub-navegação contextual in-page (seção abaixo), não navegação de topo por URL. Decisão DS [W] 2026-07-15.

> **Como consumir:** não renderize `PageHeaderTabs` cru na tela — passe pelo `*SubNav` do módulo (`FinanceiroSubNav`/`JanaSubNav`/`PontoSubNav`), que resolve os ghosts + active a partir do `shell.menu`. Fidelidade da aba ativa (radius 0 · underline `var(--accent)` · pill accent-soft · font 600) travada por `tests/pageHeaderTabsFidelity.spec.tsx`.
>
> **Catracas ativas:** regra `ds/no-inline-tablist` (barra `role="tablist"` hand-rolada) + `ds/no-inline-raw-color` (cor/borda/sombra crua em `style` inline — o buraco do dark) no `eslint.config.js`; detector de papel-duplicado `node scripts/governance/component-registry-check.mjs --roles` (advisory). Migração dos independentes = incremental por tela.

---

## Sub-navegação contextual (in-page section switch)

> Papel **"sub-navegação contextual"** = trocar de seção DENTRO de uma mesma página **sem mudar a URL** — estado controlado (`value`/`onChange`), baixo contraste. **Não confundir com a barra de abas de topo acima:** aquela é navegação por URL (`href`/`shell.menu`, slot `action` do PageHeader); esta é um switch local (variantes `underline`/`segmented`), tipicamente dentro de um card. Papel distinto reconhecido por decisão DS [W] 2026-07-15 (antes o detector `--roles` o confundia com tab-nav por proximidade de nome).

| Componente | Import | Existe | Substitui (anti-pattern) |
|---|---|---|---|
| **SubNav** | `@/Components/shared/SubNav` | ✅ canon | `useState` + grupo de `<button>`/pill segmented hand-rolado dentro da tela pra alternar seção sem URL |

> **Como consumir:** `<SubNav items={tabs} value={aba} onChange={setAba} />` (modo controlado) ou `variant="segmented"` pra pill inline num card. O modo `href` (Link Inertia) existe na API mas o caso vivo é controlado — pra navegação real por URL use a barra de abas de topo (`PageHeaderTabs` via `*SubNav` do módulo), não este.
>
> **Consumidor vivo:** `Jana/Admin/Governanca/Index.tsx` (sub-tabs de modo de gráfico). Rastreado como papel próprio (`sub-navegacao-contextual`) no detector `--roles`.

---

## Combobox (campo de busca com dropdown)

> Papel **"combobox"** = campo que combina um input de busca com um dropdown de opções filtradas (autocomplete / busca-e-seleciona). **Não** é um componente único — canoniza na **composição `Popover` + `Command`** (shadcn/cmdk), onde o `Command` é o **motor**: input de busca + lista filtrada + navegação de teclado + a11y (`role=combobox/listbox/option`, `aria-activedescendant`) **de fábrica**. Hand-rolar isso (input próprio + `<ul role="listbox">` + `onKeyDown` ArrowUp/Down) reimplementa o motor de um jeito ligeiramente diferente a cada tela — a11y divergente/bugada é a CAUSA. Detalhe: [ADR proposta tab-nav-canonico §Ondas futuras](../memory/decisions/proposals/2026-07-15-tab-nav-canonico-e-componente-por-papel.md).

| Componente | Import | Existe | Substitui (anti-pattern) |
|---|---|---|---|
| **Command** (motor) + **Popover** (shell) | `@/Components/ui/command` + `@/Components/ui/popover` | ✅ canon | `ClienteCombobox` · `PlanoContaCombobox` · `GradeProductCombobox` · `CustomerSearchAutocomplete` · `ProductSearchAutocomplete` · qualquer **`<input role="combobox">`** / **`aria-autocomplete`** + `<ul role="listbox">` hand-rolado na tela |

> **Como consumir** (imite `Pages/OficinaAuto/ServiceOrders/Create.tsx` — a referência viva): `<Popover>` com um `<Button role="combobox">` de trigger + `<PopoverContent><Command><CommandInput/><CommandList><CommandEmpty/><CommandGroup>…<CommandItem/></CommandGroup></CommandList></Command></PopoverContent>`. Busca **client-side** (lista pré-carregada) = default do `Command`; busca **server-side/async** (debounce, texto livre) = `Command` com `shouldFilter={false}` alimentando `CommandItem`s dos resultados — o motor cmdk ainda dá a a11y e a navegação de teclado, a tela só troca a fonte dos itens.
>
> **Catracas ativas:** regra `ds/no-handrolled-combobox` (`aria-autocomplete` ou `role="combobox"` num `<input>` nativo — os signals que NUNCA aparecem no consumo canônico; o `<Button role="combobox">` do padrão certo **não** é pego) no `eslint.config.js`; detector de papel-duplicado `node scripts/governance/component-registry-check.mjs --roles` (advisory · papel `combobox`, âncora = importar `@/Components/ui/command`). **Fronteira honesta:** o hand-roll com `<Button>` trigger + `<ul role="listbox">` à mão é indistinguível do canônico sem análise de import — por isso o lint pega só o signal preciso do `<input>` e quem cataloga o resto é o detector `--roles`. Migração dos 5 independentes = incremental por tela (fora do escopo desta onda).

---

## Primitivos de layout ([ADR 0253](../memory/decisions/0253-primitivos-layout.md) · F3)

> A camada entre tokens DS v6 e telas. **Layout é COMPOSIÇÃO destes primitivos**, nunca `<div className="flex gap-4">` solto nem `.css` bespoke. Props = token (enumerado via CVA) → espaço/tipo nunca vêm de px literal. Superfície única: **`@/Components/layout`**.

| Componente | Import | Faz | Substitui (anti-pattern) |
|---|---|---|---|
| **Box** | `@/Components/layout` | container neutro c/ espaço/cor via token | `<div>` com padding/cor literal |
| **Stack** | `@/Components/layout` | empilha vertical com `gap` token | `<div className="flex flex-col gap-4">` solto |
| **Inline** | `@/Components/layout` | alinha horizontal com `gap` + wrap | `<div className="flex items-center gap-2">` solto |
| **Grid** | `@/Components/layout` | grid responsivo por colunas-token | `<div className="grid grid-cols-…">` solto |
| **Container** | `@/Components/layout` | largura máx + padding de página | wrapper de largura/padding na mão |
| **Text** | `@/Components/layout` | tipografia 100% via type-scale token | `text-[22px]` / tamanho literal solto |

> Testados em `tests/layout-primitives.test.tsx`. Estes existem mas estavam **fora do REGISTRY** — registrados aqui pra serem descobertos/reusados (o ponto do F3: parar de hand-rolar flex). Adoção em telas = incremental, por gate visual.

---

## Onda F — a criar em `@/Components/ui` (hoje só CSS no DS)

Estes 4 ainda **não** têm impl React. Enquanto não existirem, a tela hand-rola — por isso entram primeiro.

| Componente novo | CSS DS (v4.1) | Substitui | Primeiro consumidor |
|---|---|---|---|
| **`Segmented`** | `.segmented` (+`.accent`) | `<input type="radio">` de PF/PJ, Cliente/Fornecedor; e RadioGroup quando é toggle 2–3 opções | `Cliente/Create` (tipo + pessoa) |
| **`FormSection`** | `.form-section` + `.form-grid` | `<section className="rounded-lg border p-4\|p-5">` (hoje duplicado: Create p-4, DadosFiscaisBR p-5) | `Cliente/Create` + `DadosFiscaisBRSection` |
| **`InputGroup`** (+ `ig-btn`/`ig-addon`) | `.input-group` | `<div className="flex"> input + button` do "Buscar CNPJ"/ViaCEP; addon `R$`/`%` | `DadosFiscaisBRSection` (CNPJ) |
| **`FieldState`** (`FieldError`/`FieldSuccess`/`FieldValidating`) | `.field-error` (A2) · `.field-success` · `.field-validating` · `.req` | `<p className="text-rose-600">` / `<p className="text-emerald-600">` soltos | `DadosFiscaisBRSection` (lookup) |

**Pattern (não é componente):** `.form-layout` + `.form-rail` (form + rail de contexto sticky) — o que deu o salto 6,2→9,5 no Contacts. Vira layout reusável de qualquer **Create**.

---

## Regra de ouro

> Precisa de algo que **não está** neste registry? **Não hand-rola na tela.** Atualiza o DS (vira Onda nova com o tripé), aí consome. Toda exceção é dívida que o `ds/*` vai cobrar no PR.
