# GOTCHAS — Pegadinhas curadas pro RUNBOOK

> Append-only. Cada incidente vira pegadinha permanente.
> Pegadinhas específicas da tela ficam no §10 do RUNBOOK gerado; estas aqui são as que se aplicam a QUALQUER tela do Cockpit.

## Inertia / React

- ✅ **Ziggy disponível** desde [PR #180](https://github.com/wagnerra23/oimpresso.com/pull/180) (2026-05-07). Usar `route('xxx.yyy', params)` normalmente em Pages React. Tipos globais declarados em `resources/js/global.d.ts`; pacote `tightenco/ziggy` no composer; `@routes` injetado em `inertia.blade.php`. Antes de #180, era ReferenceError silencioso runtime — bug latente em todos os PRs de Pages React anteriores. Auditoria de PRs antes dessa data deve assumir `route()` não funcional no runtime.
- ❌ **Persistent Layouts** (auto-mem `preference_persistent_layouts`) — não envolver Page em `<AppShell>` inline. Usar `<Tela>.layout = (page) => <AppShellV2>{page}</AppShellV2>`. Sintoma: shell duplicado, scroll quebrado, breadcrumb sumindo entre navegações.
- ❌ **Cache/estado preservado** (auto-mem `preference_cache_estado_preservado`) — telas não podem fazer reload total entre cliques. Usar `useForm({...}, { forceFormData: false })`, `<Link preserveScroll preserveState />`, `router.get(url, {}, { only: [...] })`. Nunca `window.location.reload()`. Wagner exigiu 2026-04-26 após regressão.
- ❌ **`sessionStorage` pra estado UI** — perde na nova aba. Sempre `localStorage` com prefixo `oimpresso.` (DESIGN.md §12).

## Build / Deploy

- ❌ **`npm run build` errado** — config padrão gera só Tailwind, não bundles Inertia. Sempre `npm run build:inertia`. Sintoma: tela 404, bundle não aparece em `public/build-inertia/manifest.json`.
- ❌ **`composer install` esquecido pós-deploy** (auto-mem `reference_composer_install_obrigatorio_pos_deploy`) — após push em main com mudança em composer.json/lock, rodar SSH + composer install. Quick-Sync GitHub Action NÃO faz isso. Sintoma: tela branca Inertia (`null.component`). Descoberto 2026-04-25 com upgrade Inertia v2→v3.
- ❌ **`composer install --no-dev` quebra Faker em prod** — `nfephp-org/sped-da` (e outros) referenciam `Faker\Generator` em service provider que carrega em prod, mesmo Faker sendo `require-dev`. Sintoma: `Target class [Faker\Generator] does not exist` em qualquer comando artisan pós-install. Fix: rodar `composer install` sem `--no-dev` na Hostinger. Descoberto 2026-05-07 ao instalar Ziggy ([PR #180](https://github.com/wagnerra23/oimpresso.com/pull/180)) — auto-mem `reference_composer_install_obrigatorio_pos_deploy` já alertava.
- ❌ **`composer-lock-sync.yml` workflow contra `base_branch != main` + força push em rebase = perde commit do lock.** Sintoma: PR principal mergeado, Hostinger `composer install` falha com "package X not present in lock file". Causa: workflow disparado contra branch da feature cria PR de lock que mergeia naquela branch; ao fazer `git fetch origin main && git rebase origin/main`, se você não pulou primeiro `origin/<feature-branch>` (que tem o squash do lock-PR), `force-with-lease` sobrescreve o commit do lock. Fix: SEMPRE disparar `composer-lock-sync.yml` com `base_branch=main` quando possível; se obrigatório usar feature branch, rodar `git pull --rebase origin <feature-branch>` ANTES do rebase em main. Descoberto na sequência [PRs #178/#179/#180/#181](https://github.com/wagnerra23/oimpresso.com/pull/180) em 2026-05-07 (rebase de #180 destruiu #179, salvou disparando workflow de novo contra main → PR #181).

## Tokens / Design System

- ❌ **Cor crua Tailwind** (`bg-blue-500`, `text-gray-700`) viola R-DS-002 e quebra dark mode. Usar tokens semânticos (`bg-primary`, `text-muted-foreground`, `border-border`) ou variáveis do shell (`var(--accent)`). Exceções: cores de status fixo (emerald/amber/red) em KPIs.
- ❌ **`<button>` HTML cru** viola R-DS-001 — perde acessibilidade embutida do `<Button>` shadcn. Sempre `import { Button } from '@/Components/ui/button'`.
- ❌ **Inventar cor solta no CSS** — derive via `oklch()` a partir de `--accent` ou origem do módulo (DESIGN.md §9). Hex/rgb hardcoded não acompanha tweaks (vibe/densidade/accentHue do ADR 0039).
- ❌ **Ícones de bibliotecas alternativas** — só `lucide-react` (R-DS-003). Não importar `@radix-ui/react-icons`, `heroicons`, `react-icons`, emojis nem SVG custom. Bundle penalty + inconsistência de traço.

## Atalhos

- ❌ **Listener sem cleanup** — `useEffect(() => { window.addEventListener(...) })` sem `removeEventListener` no return causa memory leak + atalho disparando em telas erradas após navegação. Sempre retornar cleanup function.
- ❌ **Atalho disparando em `<input>`** — bloquear com `if (e.target instanceof HTMLInputElement) return` no início do handler. Sintoma: usuário digita "j" no campo de busca e a tela navega na lista.

## Multi-tenant

- ❌ **Query sem `business_id`** — vazar dados entre tenants é **o pior bug possível** deste projeto (CLAUDE.md §4). UltimatePOS usa `session('user.business_id')` + global scope. Skill irmã `multi-tenant-patterns` cobre.
- ❌ **`session('business.timezone')` retorna null** (auto-mem `project_session_business_model`) — `session('business')` é Eloquent, dot-notation não funciona. Usar chave dedicada `session('business_timezone')` que foi adicionada.

## I18n / labels

- ❌ **`__('alias::file.key')` em DataController/topnav** — `LegacyMenuAdapter` lê literal, não resolve traduções → labels saem crus em prod. Hardcodar PT-BR (NFSe sempre fez assim). Auto-mem `feedback_topnav_i18n_pattern`.
- ❌ **Blade `{{ }}` dentro de `{!! !!}`** (auto-mem `feedback_blade_double_escape_bug`) — duplo escape quebra parser PHP. Sintoma: erro 500 com "unexpected token <". Fix: chamar função PHP direto sem `{{ }}` aninhado. Encontrado em `register.blade.php:16` em 2026-04-26.

## Datas / timezone

- ❌ **`format_date()` em `tx_date` retroativo** (auto-mem `feedback_format_now_local_e_default_datetime`) — aplica shift histórico +3h. Pra "agora" usar `Util::format_now_local()`. Larissa (ROTA LIVRE) decorou esse comportamento (auto-mem `cliente_rotalivre`); não tentar "corrigir" sem alinhar.
- ❌ **Campo `readonly` sem datetimepicker** — fica congelado. Inicializar datetimepicker mesmo em campos readonly se vai aceitar valor de "agora".

## Hostinger / produção

- ❌ **Daemons no Hostinger** — Reverb, Centrifugo, Horizon, autossh, Meilisearch NÃO rodam em shared hosting. Daemons → CT 100 Proxmox (skill `runtime-rules-hostinger-ct100`).
- ❌ **`composer update` em prod sem PR** — sempre `composer install` no servidor com lockfile do PR mergeado.

## Format / spatie shim

- ❌ **Remover shim `App\View\Helpers\Form`** sem migrar ~6.433 chamadas `Form::` em ~460 Blade views (CLAUDE.md §4).
- ✅ **Form shim normaliza bool attrs** (auto-mem `feedback_form_shim_bool_attrs`) — `disabled/readonly/etc` com `false/null` são omitidos automaticamente. Bug crítico corrigido 2026-04-24 que travava `/sells/create`.

## Cockpit pattern duplicado (2026-05-07)

- ❌ **Sidebar custom em vez de `LinkedAppsPanel`** — quando uma tela tem entidade em foco com contexto multi-módulo (conversa Whatsapp = cliente CRM + OS Repair + Boletos FIN), criar sidebar próprio reinventa ADR 0039 §3 + R-DS-010. Sintoma: usuário precisa abrir outra tela pra ver dados vinculados ao cliente da conversa. Fix: passar `conversaFoco` pro `<AppShellV2>` e usar `LinkedAppsPanel`. Exceção: se a tela tem 0 contexto cross-módulo, custom sidebar OK + ADR per-tela explicando. Descoberto no audit Whatsapp/Conversations 2026-05-07.

- ❌ **Tweaks vars (`--bubble-me`, `--accent`, `--row-h`, `--card-pad`) hardcoded** — `bg-emerald-600` no bubble outbound em vez de `var(--bubble-me)`. AppShellV2 já calcula essas vars dinamicamente baseadas em `vibe`/`density`/`accentHue` (ADR 0039 §5). Hardcode ignora os Tweaks — usuário muda hue no panel e bubble continua verde. Sintoma: dissonância visual quando Wagner muda paleta. Fix: usar `style={{ background: 'var(--bubble-me)' }}` no bubble out + `padding: 'var(--card-pad)'` em cards relevantes. Descoberto no audit Whatsapp/Conversations 2026-05-07.

- ❌ **Avatar duplicado em components/_components da página em vez de shared** — Whatsapp criou `_components/Avatar.tsx` com paleta hash de 10 cores, divergente das 5 origin colors do `cockpit.css` (OS amber, CRM blue, FIN green, PNT violet, MFG orange). Sintoma: cliente "Larissa" no inbox aparece com cor X, nas Apps Vinculados aparece com cor Y. Fix: consolidar em `Components/cockpit/Avatar.tsx` + diferenciar avatar de **pessoa** (hash) vs avatar de **módulo** (origin color via `--origin-{OS|CRM|FIN|PNT|MFG}-{bg|fg}`). Descoberto no audit Whatsapp/Conversations 2026-05-07.

- ❌ **Empty state inline em vez de `<EmptyState/>` shared** — `<div className="text-7xl opacity-20">💬</div>` reinventa o que `Components/shared/EmptyState` já faz com tokens semânticos + props `icon/title/description/primaryAction`. Sintoma: UX inconsistente entre módulos (Whatsapp empty é 💬 emoji, Repair empty é `<EmptyState/>`). Fix: importar `EmptyState` shared. Falta `primaryAction` é finding UX-WARN (Q5 do CHECKLIST §F) — empty deve convidar ação, não só informar. Descoberto no audit Whatsapp/Conversations 2026-05-07.

- ✅ **Ziggy `route()` global instalado em [PR #180](https://github.com/wagnerra23/oimpresso.com/pull/180)** (2026-05-07). Antes do #180 era bug latente: todas as Pages React do oimpresso chamavam `route('xxx.yyy')` mas Ziggy nunca havia sido instalado nem `@routes` injetado no Blade — runtime quebrava silenciosamente (links com `href=undefined`, `router.get(undefined)`). 161 erros TS `Cannot find name 'route'` apontavam pra esse bug, não eram pre-existência tolerada. Fix de 3 linhas: adicionar `tightenco/ziggy` no composer + `@routes` no `inertia.blade.php` + `resources/js/global.d.ts` com declaração global. Lição: erros TS sistêmicos costumam apontar pra bug runtime real, não tolerância tribal.

## UltimatePOS forDropdowns — shape changes + prepend_none (2026-05-08)

3 hotfixes em sequência ao migrar /sells/create (PRs #245, #247, #248) — todos preveníveis com smoke visual ANTES do merge:

- ❌ **Declarar tipo TS sem ver shape JSON real.** PR #244 declarou `taxes: Tax[]` baseado no nome do método; mas `TaxRate::forBusinessDropdown` retorna `pluck('name', 'id')` Collection que vira object `{id: name}` no JSON, não array. Sintoma: tela branca + `TypeError: a.taxes.map is not a function`. Fix: SEMPRE rodar `php artisan tinker` + `var_export(Model::forDropdown(...))` ANTES de declarar tipo TS na Page Inertia.

- ❌ **Funções com flags shape-changing.** `TaxRate::forBusinessDropdown(biz, prepend_none=true, include_attributes=true)` muda completamente o retorno: vira `['tax_rates' => Collection, 'attributes' => array]` em vez do pluck direto. Object.entries iterou keys 'tax_rates' e 'attributes' renderizando os Collections como children → React #31 com keys numéricos {1,2,3}. Fix: SEMPRE ler implementação COMPLETA da função PHP (não só assinatura). Outras com mesmo padrão: `BusinessLocation::forDropdown(receipt_printer_type_attribute=true)`, `User::forDropdown(prepend_none=true, include_assigned_to=true)`. Em PR #247 corrigi extraindo `$taxes['tax_rates']` no controller antes de passar pro Inertia.

- ❌ **`<SelectItem value="" />` quebra Radix.** UltimatePOS prepend_none adiciona key '' = "Nenhum" pro Select2 jQuery legacy. Radix UI recusa value vazio: *"A <Select.Item /> must have a value prop that is not an empty string"*. A escolha vazia já é representada pelo `<SelectValue placeholder>`. Fix: helper `dropdownEntries()` em `Pages/Sells/_components/dropdownEntries.ts` filtra entries com key '' ou null antes do `.map(([id, name]) => <SelectItem.../>)`. **Sempre usar esse helper** ao mapear Records de forDropdowns.

  ```tsx
  // ❌ quebra Radix se houver key '' (prepend_none legacy)
  {Object.entries(props.taxes).map(([id, name]) => <SelectItem value={id}>{name}</SelectItem>)}

  // ✅ filtrado
  import { dropdownEntries } from './_components/dropdownEntries';
  {dropdownEntries(props.taxes).map(([id, name]) => <SelectItem value={id}>{name}</SelectItem>)}
  ```

**Lição meta:** PRs #244 + #245 + #247 + #248 foram custo de não validar runtime real ANTES de mergear. Smoke visual em biz=1 em <5min teria pego o primeiro bug e evitado os outros 3. Considerar `tsc --noEmit` no `mwart-gate.yml` (CI) — não pega tudo (shape JSON é runtime), mas pega tipos inconsistentes em compile-time.

---

**Quando apender:** após qualquer audit de tela ou session log que descubra novo modo de falha. Marcar data + módulo + 1 linha de contexto.

**Última atualização:** 2026-05-08 — 3 lições de migração MWART /sells/create (forDropdowns nested shape, prepend_none key '', helper dropdownEntries).
