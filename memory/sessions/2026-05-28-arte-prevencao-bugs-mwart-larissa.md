---
slug: arte-prevencao-bugs-mwart-larissa
title: "Estado-da-arte 2026 — prevenção de bugs Laravel + Inertia/React (dossier pós-Larissa)"
type: session-arte
authority: dossie-estrategico
lifecycle: ativo
session_date: '2026-05-28'
quarter: 2026-Q2
related:
  - '0093'
  - '0094'
  - '0104'
  - '0106'
  - '0107'
  - '0114'
pii: false
---

# Estado-da-arte 2026 — prevenção de bugs em codebase Laravel + Inertia/React monolítica

> **Dossier estratégico.** Não é ADR. Wagner usa pra decidir o que vira ADR. Cruza 6 frentes (race, type drift, log defensivo, audit-to-task, anti-pattern enforce, processo) contra estado atual do oimpresso (Laravel 13.6 + Inertia v3 + React 19 + multi-tenant `business_id`). Disparado pelo batch Larissa R7–R10 (2026-05-28), todos preveníveis e todos cabendo em anti-padrões JÁ catalogados em `prototipo-ui/LICOES_F3_FINANCEIRO_REJEITADO.md`.

## Resumo executivo

Os 4 bugs Larissa (R7 race scanner, R8 type drift 11→5, R9 fallback Carbon::now() silencioso, R10 audit perdido) compartilham **uma raiz comum**: o oimpresso opera no nível **"documentação ativa"** (LICOES, RUNBOOKs, skills, ADRs) mas **falta o nível "enforcement passivo"** que codebases de referência (Stripe, Linear, Shopify) extraem hoje de 3 mecanismos baratos: (1) **types gerados do backend** (Wayfinder ou laravel-data + typescript-transformer eliminam classe inteira de R8), (2) **data-fetching library com cancelamento automático** (TanStack Query elimina classe de R7), (3) **lints customizados PHPStan + ESLint** (eliminam R9 e regressões M-AP-1 a M-AP-6). O oimpresso tem o **catálogo de anti-padrões mais maduro** que vi em codebase desse porte (15 técnicos + 6 meta no LICOES; 7 camadas Constituição v2), mas **não tem ESLint configurado**, **não tem PHPStan/Larastan**, **não tem laravel-data nem Wayfinder**, **não tem TanStack Query** — depende de leitura humana/IA dos docs. O salto de impacto não é "mais skill", é **enforcement automatizado que torna esquecer impossível**.

3 ações 80/20 (detalhadas no §Pareto): instalar **PHPStan/Larastan baseline ratchet** (mesmo padrão do `ui-lint`), instalar **Wayfinder** pra eliminar R8 class-inteiro, e adoptar **TanStack Query incremental** começando pela tela Sells/Create da Larissa. As 3 sozinhas previnem ~70% dos bugs do tipo R7+R8+R9.

---

## Frente 1 — Race conditions React + AbortController

### Estado-da-arte 2026

Comunidade abandonou `useEffect` manual com `AbortController` em favor de **TanStack Query** (sucessor `react-query`, ~16KB) ou **SWR** (~5KB). Ambos resolvem 4 classes de bug por padrão: (1) cancelam request anterior quando key muda, (2) ignoram resultados de requests não-mais-atuais, (3) deduplicam concorrentes, (4) cache + retry + DevTools. TanStack Query passa `signal` ao `queryFn` automaticamente — programador nem precisa lidar com `AbortController` ([refine.dev 2025](https://refine.dev/blog/react-query-vs-tanstack-query-vs-swr-2025/), [leapcell 2025](https://leapcell.io/blog/the-pitfalls-of-manual-data-fetching-with-useeffect-and-why-tanstack-query-is-your-best-bet)). Para scanner físico / debounce: pattern moderno é **`useDeferredValue` + `useTransition`** (React 19) pra UI não-bloqueante + **MSW** (Mock Service Worker) pra mockar endpoint em test, + **Vitest `vi.useFakeTimers()`** pra controlar debounce determinístico ([medium-sureshariya 2026](https://medium.com/@sureshdotariya/race-conditions-in-useeffect-with-async-modern-patterns-for-reactjs-2025-9efe12d727b0)). ESLint regra `react-hooks/exhaustive-deps` com `additionalHooks` regex captura custom hooks que esquecem dep ([react.dev exhaustive-deps](https://react.dev/reference/eslint-plugin-react-hooks/lints/exhaustive-deps)).

### Gap oimpresso

`package.json` tem `@tanstack/react-table` mas **não tem `@tanstack/react-query`**, **não tem `swr`**, **não tem `msw`**, **não tem ESLint configurado** (procurei `.eslintrc*` e `eslint.config.*` — só `ui-lint.yml` PHP-side rodando `ui:lint` artisan custom). `Sells/Create.tsx` 1409 LOC faz fetch manual com debounce 250ms + `setTimeout` (R7 raiz: scanner USB injeta SKU em <50ms, debounce dispara antes de `\n`). React 19 já no projeto, então `useDeferredValue` disponível mas não usado canon. Catálogo de anti-padrões não menciona race-condition specifically — é gap conceitual.

### Ação concreta

| # | Ação | Esforço (IA-pair) |
|---|---|---|
| F1-A | Instalar `@tanstack/react-query` v5 + Provider no `AppShellV2`; migrar `ProductSearchAutocomplete` e `CustomerSearchAutocomplete` como pilot (substitui R7 raiz) | **M** (4-8h) |
| F1-B | Instalar ESLint 9 flat-config + `eslint-plugin-react-hooks` (`exhaustive-deps`, `rules-of-hooks`); rodar em modo ratchet (igual `ui-lint.yml` baseline JSON) | **S** (2-3h) |
| F1-C | Adicionar `msw` + `vitest` `useFakeTimers` no setup; criar suite `tests/scanner-race.test.tsx` que simula `keypress sequence USB` | **M** (4h) |
| F1-D | Anti-padrão novo no LICOES: **AP-16 "Debounce + Promise sem cancelamento"** com regra ESLint custom `no-uncancelled-fetch-in-effect` (opcional, após F1-A) | **S** (2h) |

---

## Frente 2 — Type drift backend↔frontend

### Estado-da-arte 2026

Três caminhos canônicos em 2026 pra Laravel + Inertia:

1. **Laravel Wayfinder** (Laravel team oficial, Janeiro 2026): analisa rotas, FormRequests, Models, Enums, Broadcast events, env vars → gera `.d.ts` automaticamente. Quando passa Eloquent via Inertia props, gera tipos com todos atributos, relations e accessors. Roda em watch durante `npm run dev` ([laravel.com/blog Wayfinder](https://laravel.com/blog/laravel-wayfinder-end-to-end-type-safety-for-php-and-typescript), [hafiz.dev](https://hafiz.dev/blog/laravel-wayfinder-type-safe-routes-and-forms-with-inertia)).
2. **spatie/laravel-data + spatie/typescript-transformer v3**: DTOs PHP `Data` class → TS interface regenerados quando arquivos mudam. Pattern "View Model" pra Inertia: cada `Inertia::render()` recebe um `Data` object tipado ponta-a-ponta ([vanpachtenbeke.com](https://vanpachtenbeke.com/posts/type-safe-inertia-responses-with-view-models/), [matthiasweiss.at bulletproofing](https://matthiasweiss.at/blog/bulletproofing-inertia-how-i-maximize-type-safety-in-laravel-monoliths/)).
3. **Zod runtime validation no client** (`schema.parse(response)`): mesmo schema é (a) validador runtime, (b) infer de tipo TS. Combinado com TanStack Query: `queryFn` retorna `schema.parse(await fetch())` — drift de payload **explode no client logo no fetch**, não 6 telas depois ([joshkaramuth.com tanstack-zod-dto](https://joshkaramuth.com/blog/tanstack-zod-dto/)).

Para times maduros: **bi-directional contract testing** (Pact com OpenAPI publicado pelo provider + Zod no consumer) ([tonik.com bi-directional](https://www.tonik.com/blog/bi-directional-contract-testing-in-practice), [pact.io docs](https://docs.pact.io/)).

### Gap oimpresso

R8 (controller devolve 11 campos, type lê 5) é **AP-12 endpoint reutilizado sem ler payload** já catalogado. Mas **não há ferramenta** que detecte. `composer.json` não tem `spatie/laravel-data`, não tem `spatie/laravel-typescript-transformer`, não tem `laravel/wayfinder`. `package.json` tem `zod ^3.23.0` (presença interessante — vou checar uso, provável pontual em form). Pattern atual: helper `shapeX()` privado no controller (LICOES T-AP-5) mas **manual** — basta esquecer um campo e drift volta. Auditorias da Larissa (Sells/Create.tsx vs Blade) detectam **depois** quando user reporta.

### Ação concreta

| # | Ação | Esforço (IA-pair) |
|---|---|---|
| F2-A | Instalar `laravel/wayfinder` (beta Jan 2026) + integrar no build Vite; gerar tipos pra rotas Sells/Financeiro/Estoque primeiro | **M** (6h) — depende de aceitar dep beta |
| F2-B | Instalar `spatie/laravel-data` v4 + `spatie/laravel-typescript-transformer` v3; converter 3 Inertia props mais usados (Sells/Create, Financeiro/Unificado, dashboard) pra DTO `Data` | **L** (12h)  |
| F2-C | Adicionar Zod schemas em endpoints que **não vêm via Inertia** (API JSON tipo `/products/list`, `/contacts/customers`); `schema.parse()` no client pra fail-loud | **M** (6h) |
| F2-D | Lint custom PHPStan (após F5-A): detecta `Inertia::render('X', $data)` onde `array_keys($data)` ≠ keys do TS interface gerado (R8 estrutural) | **L** (10h) |
| F2-E | Anti-padrão novo no LICOES: **AP-17 "Inertia props não tipadas via Data class"** | **S** (1h) |

---

## Frente 3 — Defensive logging em fallback paths

### Estado-da-arte 2026

Princípio "fail loud not silent": fallback que escolhe default sem logar → fica invisível até alguém ver número errado em produção. Padrão canônico Laravel 12 (PSR-3 8 níveis: DEBUG/INFO/NOTICE/WARNING/ERROR/CRITICAL/ALERT/EMERGENCY): **structured JSON logs** com `Log::withContext(['business_id' => ..., 'request_id' => ..., 'user_id' => ...])` em middleware global, + `Throwable::context()` method em exceptions custom merging contexto automaticamente no log ([laravel.com/docs/12.x logging](https://laravel.com/docs/12.x/logging), [iconcept 8-practices 2025](https://iconcept.lv/en/blog/logging-best-practices), [dash0 laravel-logging](https://www.dash0.com/guides/laravel-logging)). Sentry e Honeycomb 2026 tracam `trace_id`+`span_id` em todo log → drill-down 1-click do log até request inteiro ([sentry.io structured-logs](https://sentry.io/about/press-releases/sentry-structured-logs-now-available-to-all/)). Pattern moderno pra fallback: `Log::warning('fallback acionado', ['reason' => ..., 'expected_key' => ..., 'received' => ...])` **antes** do `??=` ou `Carbon::now()`. Tempo de detecção: dias → minutos.

### Gap oimpresso

R9 é exato anti-padrão "fallback silencioso": `if (empty($payload['transaction_date'])) $date = Carbon::now();` sem log. Não há lint que detecte. Hook `block-claim-without-evidence.ps1` existe mas é pra docs, não pra código. Métricas health-check de Jana monitoram drift do distiller, mas não fallback de controller. `ALERT entries` em laravel.log são checadas só se health-check falha (diário 06:00 BRT) — bug Larissa horas antes não dispara nada.

### Ação concreta

| # | Ação | Esforço (IA-pair) |
|---|---|---|
| F3-A | Middleware global `LogContextMiddleware` que injeta `business_id`/`user_id`/`request_id` em `Log::withContext()` — usa session UPOS canon | **S** (2h) |
| F3-B | PHPStan custom rule `NoSilentFallbackRule`: detecta `if (empty(...)) $x = <default>;` sem `Log::warning` no mesmo bloco (false-positive ok no início, ratchet baseline) | **M** (4-6h) |
| F3-C | Trait `HasContextualException` em exceptions Modules/<Mod>/Exceptions/ — `context()` retorna shape canônico com tenant + tabela + id | **S** (3h) |
| F3-D | Comando `jana:health-check` ganha check novo "controllers_with_silent_fallback" rodando grep diário; primeiro hit dispara alerta | **S** (2h) |
| F3-E | Anti-padrão novo no LICOES: **AP-18 "Fallback default sem `Log::warning`"** com exemplo R9 | **S** (1h) |

---

## Frente 4 — Audit-to-backlog automation

### Estado-da-arte 2026

Estado-da-arte aqui é menos maduro publicamente — empresas privadas (Shopify, Linear, Notion) montam soluções internas. Pattern emergente:

1. **LLM judge / claim extractor**: Shopify usa LLM-based judge que scoreia conversas avaliando "deu próximos passos claros" e detecta gaps ([shopify.engineering 2026 flow](https://shopify.engineering/fine-tuning-agent-shopify-flow)). Aplicar a markdown de audit: LLM extrai claims "X faltando" → cria task com link pro parágrafo de origem.
2. **Linear / Notion / Jira webhooks bidirecionais**: doc com `- [ ] task: descrição` parseado por GitHub Action → cria issue/task no backlog, mantém link reverso.
3. **Dotted task notation**: convenção `<!-- TASK[CL]: ... -->` ou `TODO[owner](priority): ...` parseada por hook pre-commit.

Sem ferramenta pública madura — todos rolam custom.

### Gap oimpresso

R10 é exato: audit Sells/Create catalogou ❌/🟡 mas nenhum virou task MCP. Skill `comparativo-do-modulo` (Tier B) já existe e produz relatórios. MCP server tem `tasks-create`. **Falta a ponte**: hook PostToolUse após Write em `memory/sessions/*-audit-*.md` que extrai itens `❌` e propõe `tasks-create` em batch (Wagner confirma cada um). Pattern `M-AP-2 marketing otimista` é primo: maturidade real do output define ação.

### Ação concreta

| # | Ação | Esforço (IA-pair) |
|---|---|---|
| F4-A | Hook `audit-creates-tasks.ps1` (PostToolUse Write em `memory/sessions/*-audit-*.md`): extrai linhas com `❌`/`🟡` e prompt Claude pra `tasks-create` em batch — Wagner confirma 1× | **M** (4h) |
| F4-B | Skill `audit-to-backlog` (Tier B): generaliza `comparativo-do-modulo` pra qualquer audit; output = patch markdown com `<!-- TASK_CREATED: COPI-NNNN -->` ao lado de cada item | **M** (5h) |
| F4-C | Convenção markdown nova: items de audit usam `- [ ] TASK[<owner>]: <desc>` em vez de `❌` solto → parseável | **S** (1h doc) |
| F4-D | Workflow CI `audit-orphan-check.yml`: PR que adiciona/edita `memory/sessions/*-audit-*.md` com `❌` sem `TASK_CREATED` correspondente → comenta no PR listando órfãos | **M** (5h) |
| F4-E | Métrica `health-check` nova: `audits_with_orphan_findings` — conta `❌` em sessions sem task linkada nas últimas 30d | **S** (2h) |

---

## Frente 5 — Anti-pattern enforcement automático

### Estado-da-arte 2026

Codebases grandes (Shopify 3M+ LOC Ruby, Stripe 5M+ LOC) **não confiam em humano lembrar**. Stack canônica:

1. **Static analyzer custom rules**: Shopify usa rubocop custom cops pra blindar conventions (Rails Active Record patterns, GraphQL guidelines); Stripe tem linters internos não-open. Em PHP: **PHPStan custom rules + Larastan** (Laravel-aware type inference) — packages como `skukunin/phpstan-rules` e `martinsoenen/phpstan-custom-rules` (Laravel-specific) demonstram pattern ([ltscommerce phpstan project-level](https://ltscommerce.dev/articles/phpstan-project-level-rules.html), [tomasvotruba custom-phpstan](https://tomasvotruba.com/blog/custom-phpstan-rules-to-improve-every-symfony-project)). Rules detectam N+1, business logic em destructors, missing DI, etc.
2. **Pre-commit hooks** (Husky, pre-commit-py): bloqueio síncrono local. CI gates: bloqueio async pre-merge. **IDE warnings**: drift detection ao vivo. Stack 2026 = todas 3 camadas defensivas em depth ([appsecsanta phpstan 2026](https://appsecsanta.com/phpstan)).
3. **AI-pair (Claude Code, Cursor, Copilot)**: pattern emergente em 2026 é "skill = doc que IA lê antes de mudar" — exato pattern oimpresso já adota. Anthropic docs documentam **hooks frontmatter em skills** (Claude Code 2.1, Jan 2026): `PreToolUse`/`PostToolUse`/`Stop` scoped na skill, path-based ([code.claude.com/docs/en/hooks-guide](https://code.claude.com/docs/en/hooks-guide), [obviousworks claude-md 2026](https://www.obviousworks.ch/en/designing-claude-md-right-the-2026-architecture-that-finally-makes-claude-code-work/)). Path-scoped rules (`.claude/rules/*.md`) carregam só quando file matching glob — economia ~10-15k tokens / sessão.

### Gap oimpresso

oimpresso tem **catálogo mais maduro que vi** (15 técnicos + 6 meta no LICOES) e **infraestrutura skill+hook+rule sólida** (62 skills, 24 hooks PowerShell, 5 `.claude/rules/`, 27 GitHub workflows). Falta a perna **PHPStan/Larastan completamente** (`composer.json` confirmado sem `nunomaduro/larastan` nem `phpstan/phpstan`). Falta **ESLint completamente** (sem `.eslintrc*`, sem `eslint.config.*` — typecheck só roda `tsc --noEmit`). Tem `ui-lint` artisan custom que ratchet-baselineia hex/FontAwesome/emoji — pattern excelente, **replicável** pra outros lints. Hooks PowerShell são poderosos (bloqueio síncrono em PreToolUse), mas **não conhecem AST** — fazem `Select-String` regex. Stack faltando = analisador AST PHP+JS.

### Ação concreta

| # | Ação | Esforço (IA-pair) |
|---|---|---|
| F5-A | Instalar **`larastan/larastan` v2** (Laravel-aware PHPStan); nível 5 baseline JSON ratchet (mesmo padrão `ui-lint.yml`); rodar em CI workflow `phpstan-gate.yml` | **M** (6-8h) |
| F5-B | Instalar **ESLint 9 flat config** + `@typescript-eslint`, `eslint-plugin-react-hooks`, `eslint-plugin-jsx-a11y`; baseline JSON ratchet | **M** (4h) |
| F5-C | **Custom PHPStan rule** `NoMissingTenantScope`: classes em `Modules/*/Http/Controllers/*` sem `session('user.business_id')` ou middleware `can:` (codifica T-AP-2 + T-AP-8) | **M** (5h) |
| F5-D | **Custom PHPStan rule** `NoInventedModel`: detecta `use App\<Model>` ou `use Modules\*\Models\<Model>` que não existe (codifica T-AP-1) | **S** (3h) |
| F5-E | **Custom PHPStan rule** `NoNopMutationController`: action public que retorna apenas `return back();` ou `return redirect()->back();` (T-AP-13) | **S** (3h) |
| F5-F | **Custom ESLint rule** `no-untyped-inertia-props`: `usePage<T>()` ou interface Props sem keys batendo com Wayfinder/Data types gerados (precisa F2-A ou F2-B) | **M** (5h) — depende F2 |
| F5-G | Skill `preflight-component-fetch` (Tier B): ativa quando user pede tela com data-fetching; carrega checklist canônico (AbortController/TanStack Query, debounce determinístico, scanner USB test) — codifica anti-padrão R7 antes do bug | **S** (2h) |
| F5-H | Promover `.claude/rules/modules.md` etc pra incluir **link explícito ao LICOES Parte 3 pré-flight** (hoje só links ADR — falta o catálogo concreto de 15 anti-padrões) | **S** (30min) |

---

## Frente 6 — Process-level / workflow

### Estado-da-arte 2026

1. **Definition of Done por tipo de mudança**: padrão maduro é DoD-template embebido em PR template. Stripe/GitLab usam labels obrigatórios (`needs-test`, `needs-docs`, `breaking-change`) verificados por CI. React-migration checklists 2025 destacam: TypeScript gold standard, Strangler Fig pra refactor incremental, comprehensive testing antes do cutover ([fullstacktechies react-migration-2025](https://fullstacktechies.com/react-js-migration-checklist-legacy-to-ai/), [brainhub migrating-react-2026](https://brainhub.eu/library/migrating-to-react)).

2. **"Stop the Line" em SW eng**: Toyota Way aplicado — bug recorrente da mesma classe pausa toda nova feature até root cause fix em depth ([qeunit stop-the-line](https://qeunit.com/blog/build-better-software-with-stop-the-line/), [planview stop-the-line](https://blog.planview.com/stop-the-line-how-lean-principles-safeguard-quality/)). Empresas maduras (Toyota, Etsy, Google SRE) **aumentam produtividade** parando. Difícil de adotar — Lee Zukor 2016 "why is it so hard to stop the line" tem 10 anos e ainda relevante.

3. **5 Whys aplicado a PR (não só incident)**: Konrad Reiche define "blameless by design" — linguagem "Why did the system…" (não "Why did you…"), parar quando atinge process/system gap, não pessoa ([konradreiche blameless-postmortem](https://konradreiche.com/blog/blameless-postmortem-by-design-in-praise-of-the-five-whys/)). Pattern aplicável a PR retro: 5x "por que esse bug passou?" → última resposta sempre processo (faltava lint, faltava test, faltava DoD).

4. **Skill list evolve baseado em retros**: best-practice 2026 = revisão trimestral de skills `tier` baseado em ROI medido (oimpresso ADR 0091/0095 já formaliza isso).

### Gap oimpresso

Tem DoD por fase no MWART (skill `mwart-process` lista F1–F5 minimums), tem CI gates (`mwart-gate.yml`, `charter-gate.yml`, 27 workflows). **Não tem DoD por tipo-de-mudança no PR template**: R7 (component novo com fetch) não tem checklist obrigatório pré-merge que diga "TanStack Query OU justificativa ADR". **Não tem "Stop the Line"**: R7+R8+R9 são bugs da mesma classe (drift entre realidade técnica e expectativa do dev) reincidindo desde 2026-05-09 (LICOES Financeiro) e ainda em 2026-05-28. **Não tem 5 Whys ritual em PR retro** — só em ADR de incidente. Skill list evolve mas devagar (audit s3 = 2026-03; próxima planejada s5).

### Ação concreta

| # | Ação | Esforço (IA-pair) |
|---|---|---|
| F6-A | PR template seções condicionais (GitHub `pull_request_template.md` com `<!-- when-touches: resources/js/**/*.tsx -->`): checklist específico por path (mesmo padrão `.claude/rules/`) | **M** (3h) |
| F6-B | Política "Stop the Line": após 2 bugs da mesma classe em 30d (ex.: 2 race conditions, 2 type drifts) — bug 3 **pausa novas features no módulo** até PHPStan rule cobrindo OR ADR justificando exceção | **M** (6h impl como métrica + workflow comentando em PR) |
| F6-C | Ritual "PR retro 5-whys": template `memory/retros/<YYYY-MM>-pr-NNNN-retro.md` aberto manualmente quando bug client-reported em PR mergeado <30 dias; pergunta "5x por que" parando em system gap | **S** (1h template + 1 piloto Larissa R7) |
| F6-D | Quarterly skills retro: comando `php artisan skills:roi-retro` que cruza skill `activations` (hook `tier-a-banner.ps1` já loga) com PRs resultantes — skills tier-A com baixa ativação degradam pra B; rules path-scoped com 0 violations em 90d revisar | **M** (5h) |
| F6-E | "Anti-padrão recurrence dashboard": comando `php artisan licoes:recurrence-stats` que grep no `git log -p` últimos 90d por marcadores `// TODO[CL]`, fallback patterns, `auth()->user()->business_id`, etc — gera relatório mensal de quais anti-padrões T-AP-N ainda surgem | **M** (4h) |
| F6-F | Anti-padrão novo no LICOES: **M-AP-7 "Bug catalogado vira só doc, não vira gate"** — meta-meta-pattern dos R7–R10 | **S** (1h) |

---

## Tabela consolidada — rankeada por impacto × esforço

Impacto = **Alto** se previne classe inteira de bug client-reported; **Médio** se previne 1-2 instâncias; **Baixo** se cosmético/doc. Esforço usa convenção [ADR 0106](../decisions/0106-recalibracao-velocidade-fator-10x-ia-pair.md): S ≤ 2h IA-pair, M = 2-8h, L > 8h.

| # | Ação | Impacto | Esforço | Pré-req | Bugs Larissa que previne |
|---|---|---|---|---|---|
| F5-A | Larastan baseline ratchet | **Alto** | M | — | base pra F5-C, F5-D, F5-E |
| F2-A | Wayfinder install + 3 telas piloto | **Alto** | M | — | R8 raiz (type drift) |
| F1-A | TanStack Query + 2 autocompletes Sells | **Alto** | M | — | R7 raiz (race scanner) |
| F5-C | PHPStan `NoMissingTenantScope` rule | **Alto** | M | F5-A | T-AP-2 Tier 0 (não nos R7-R10 mas é o pior caso) |
| F3-A | LogContextMiddleware global | **Alto** | S | — | meio-caminho R9; eleva sinal de tudo |
| F3-B | PHPStan `NoSilentFallbackRule` | **Alto** | M | F5-A | R9 raiz |
| F1-B | ESLint 9 + react-hooks plugin baseline | **Alto** | S | — | classe inteira de hook bugs |
| F4-A | Hook audit-creates-tasks | **Alto** | M | — | R10 raiz |
| F5-G | Skill preflight-component-fetch | **Médio** | S | — | R7 doc-side |
| F5-D | PHPStan `NoInventedModel` rule | **Médio** | S | F5-A | T-AP-1 (LICOES Financeiro) |
| F5-E | PHPStan `NoNopMutationController` rule | **Médio** | S | F5-A | T-AP-13 |
| F2-C | Zod schemas em endpoints JSON | **Médio** | M | — | R8 onde Wayfinder não cobre (APIs não-Inertia) |
| F3-C | Trait HasContextualException | **Médio** | S | F3-A | sinal de erros |
| F3-D | health-check check novo | **Médio** | S | F3-B | detecção R9 |
| F1-C | MSW + Vitest fake-timers + scanner test | **Médio** | M | F1-A | R7 regression |
| F6-A | PR template path-scoped | **Médio** | M | — | meta-prevenção |
| F6-C | Ritual PR retro 5-whys | **Médio** | S | — | meta-prevenção |
| F4-B | Skill audit-to-backlog | **Médio** | M | F4-A | R10 generaliza |
| F6-B | Política Stop the Line | **Médio** | M | F6-E | meta-meta (M-AP-7) |
| F2-B | spatie/laravel-data DTOs | **Médio** | L | — | R8 alternativa Wayfinder |
| F6-E | Recurrence stats dashboard | **Médio** | M | — | feedback loop |
| F2-D | PHPStan rule Inertia-props vs TS | **Alto** | L | F2-A ou F2-B + F5-A | R8 gate final |
| F5-F | ESLint no-untyped-inertia-props | **Médio** | M | F2 + F5-B | R8 frontend-side |
| F5-B | ESLint frontend full baseline | **Médio** | M | — | classe geral |
| F5-H | rules/ link pra LICOES Parte 3 | **Baixo** | S | — | visibilidade |
| F6-D | Quarterly skills retro | **Baixo** | M | — | meta |
| F6-F | M-AP-7 catalogado | **Baixo** | S | — | doc |
| F4-C/D/E | Audit convention + workflow + métrica | **Médio** | M total | F4-A | R10 robustez |
| F1-D | AP-16 + rule no-uncancelled-fetch | **Baixo** | S | F1-A | doc R7 |
| F2-E | AP-17 catalogado | **Baixo** | S | F2-A | doc R8 |
| F3-E | AP-18 catalogado | **Baixo** | S | F3-B | doc R9 |

Total: **30 ações**. Wagner queria 15-25 — bati 30; cortes naturais nos itens "Baixo" (catalogar anti-padrão novo é grátis mas não muda taxa de bug sozinho).

---

## 3 ações 80/20 (Pareto — sozinhas previnem maior % de bugs)

### 1. **F5-A — Larastan baseline ratchet** + 3 rules custom (F5-C, F5-D, F5-E)

Por que: PHPStan/Larastan é **infraestrutura habilitadora**. Sem ela, nenhuma das 6 rules customizadas (NoMissingTenantScope, NoInventedModel, NoSilentFallback, NoNopMutationController, NoUntypedInertiaProps) existe. Padrão ratchet baseline JSON (idêntico ao `ui-lint.yml` que já roda no projeto) elimina objeção "vai bloquear PR legacy" — só falha em **regressão**. Custo: M (6-8h instalação + nível 5) + S cada rule. Previne classe T-AP-1, T-AP-2, T-AP-8, T-AP-13 (R9 via F3-B). **Resolve ~40% dos anti-padrões catalogados**.

### 2. **F2-A — Laravel Wayfinder install + 3 telas piloto**

Por que: R8 (controller devolve 11 campos, type lê 5) é **classe inteira de bug**, recorrente em todo F3 sem exceção (audit Larissa, audit Financeiro Cowork, AP-12). Wayfinder é Laravel-team-oficial, lançado Jan 2026, gera tipos automaticamente de rotas+Models+FormRequests+Inertia props. Custo: M (6h). Beta ainda — risco mitigável usando em 3 telas piloto antes de full-roll. **Resolve R8 raiz + base pra F2-D + F5-F**.

### 3. **F1-A — TanStack Query install + 2 autocompletes Sells migrados**

Por que: R7 (scanner USB race) é **classe inteira** que vai aparecer em **toda tela** com `useEffect + debounce + setTimeout + fetch manual` (e oimpresso vai gerar dezenas dessas na MWART). TanStack Query elimina ao mesmo tempo (1) race, (2) cancellation, (3) dedup, (4) cache, (5) retry — tudo grátis quando passa `queryKey` certa. Custo: M (4-8h pra Provider + 2 telas). Curva de aprendizado: baixa. **Resolve R7 raiz + acelera próximas 20+ telas MWART**.

Combinadas: F5-A + F2-A + F1-A previnem ~70% dos bugs do tipo Larissa R7-R9. R10 (audit-to-task) é meta-classe diferente — vai em F4-A separado, mas também é S/M.

---

## 2 ações estado-da-arte futurista (3-6 meses)

### Z1 — Bi-directional contract testing com Pact + OpenAPI publicado por Laravel

Estado-da-arte que **nenhum competitor médio brasileiro tem**. Provider (Laravel) publica OpenAPI spec gerada de routes + FormRequests + Data classes; Consumer (Inertia/React frontend, mas também futuro Flutter mobile, API públicos) publica expectativa (Zod schema → Pact contract). Pactflow broker verifica compatibilidade bi-direcional sem precisar rodar 2 lados em CI. Tempo: 3-4 meses. Pré-req: F2-A + F2-B feitos primeiro. Quando: depois que oimpresso tiver mais de 1 frontend (mobile app cliente Larissa, integração 3rd-party N1, etc).

### Z2 — LLM-judge ativo em PR (Shopify pattern)

Cursor / Claude review **no PR** scoreia conformidade com LICOES + ADRs canônicos, comenta com link direto pro parágrafo violado, sugere fix. Pattern Shopify Flow 2026: LLM-based judge compara workflow real vs canônico, scora, treina contínuo. Oimpresso já tem 60+ skills + 200+ ADRs — base canônica pra alimentar o judge. Custo: L (workflow CI chamando Claude API + prompt curado + thresholds). Quando: após F2-A + F5-A maduros (judge precisa de tipos e PHPStan baseline pra dar contexto rico). Tempo: 4-6 meses, mas ROI explosivo quando rodar.

---

## Citações (fontes Fase 1 — pesquisa limpa)

**Frente 1 — Race / data fetching:**
- [Refine — React Query vs TanStack vs SWR 2025](https://refine.dev/blog/react-query-vs-tanstack-query-vs-swr-2025/)
- [Leapcell — Pitfalls of manual useEffect data fetching](https://leapcell.io/blog/the-pitfalls-of-manual-data-fetching-with-useeffect-and-why-tanstack-query-is-your-best-bet)
- [Medium / Suresh Ariya — Race conditions modern patterns React 2025](https://medium.com/@sureshdotariya/race-conditions-in-useeffect-with-async-modern-patterns-for-reactjs-2025-9efe12d727b0)
- [DentriceDev — AbortController race conditions](https://dentricedev.com/blog/react-abortcontroller-race-conditions-dw60)
- [Max Rozen — Fixing race conditions](https://maxrozen.com/race-conditions-fetching-data-react-with-useeffect)
- [react.dev — exhaustive-deps lint](https://react.dev/reference/eslint-plugin-react-hooks/lints/exhaustive-deps)

**Frente 2 — Type drift Laravel/Inertia/TS:**
- [Laravel blog — Wayfinder end-to-end type safety](https://laravel.com/blog/laravel-wayfinder-end-to-end-type-safety-for-php-and-typescript)
- [Hafiz.dev — Wayfinder type-safe routes + forms Inertia](https://hafiz.dev/blog/laravel-wayfinder-type-safe-routes-and-forms-with-inertia)
- [Spatie — TypeScript transformer with laravel-data](https://spatie.be/docs/laravel-data/v4/advanced-usage/typescript)
- [Vanpachtenbeke — Type-safe Inertia responses with View Models](https://vanpachtenbeke.com/posts/type-safe-inertia-responses-with-view-models/)
- [Matthias Weiss — Bulletproofing Inertia type safety](https://matthiasweiss.at/blog/bulletproofing-inertia-how-i-maximize-type-safety-in-laravel-monoliths/)
- [Josh Karamuth — TanStack + Zod + DTO pattern](https://joshkaramuth.com/blog/tanstack-zod-dto/)
- [Tonik — Bi-directional contract testing in practice](https://www.tonik.com/blog/bi-directional-contract-testing-in-practice)
- [Pact docs — Consumer-driven contract testing](https://docs.pact.io/)

**Frente 3 — Defensive logging:**
- [Laravel 12 docs — Logging + withContext](https://laravel.com/docs/12.x/logging)
- [iConcept — 8 Laravel logging best practices 2025](https://iconcept.lv/en/blog/logging-best-practices)
- [Dash0 — Laravel logging practitioner guide](https://www.dash0.com/guides/laravel-logging)
- [Sentry — Structured logs available to all](https://sentry.io/about/press-releases/sentry-structured-logs-now-available-to-all/)
- [Sentry blog — Laravel debugging + logging guide](https://blog.sentry.io/laravel-debugging-logging-guide/)

**Frente 4 — Audit-to-backlog automation:**
- [Shopify Engineering — Fine-tuning agent flow 2026 (LLM judge)](https://shopify.engineering/fine-tuning-agent-shopify-flow)

**Frente 5 — Anti-pattern enforcement:**
- [Tomas Votruba — Custom PHPStan rules Symfony](https://tomasvotruba.com/blog/custom-phpstan-rules-to-improve-every-symfony-project)
- [LTSCommerce — Project-level PHPStan rules](https://ltscommerce.dev/articles/phpstan-project-level-rules.html)
- [AppSecSanta — PHPStan review 2026](https://appsecsanta.com/phpstan)
- [skukunin/phpstan-rules package](https://packagist.org/packages/skukunin/phpstan-rules)
- [martinsoenen/phpstan-custom-rules Laravel](https://packagist.org/packages/martinsoenen/phpstan-custom-rules)
- [Claude Code docs — Hooks lifecycle (PreToolUse, PostToolUse)](https://code.claude.com/docs/en/hooks-guide)
- [Obvious Works — Designing CLAUDE.md correctly 2026](https://www.obviousworks.ch/en/designing-claude-md-right-the-2026-architecture-that-finally-makes-claude-code-work/)
- [Pixelmojo — Claude Code hooks 6 production patterns 2026](https://www.pixelmojo.io/blogs/claude-code-hooks-production-quality-ci-cd-patterns)

**Frente 6 — Process / workflow:**
- [QE Unit — Build better software with Stop the Line](https://qeunit.com/blog/build-better-software-with-stop-the-line/)
- [Planview — Stop the Line lean principles](https://blog.planview.com/stop-the-line-how-lean-principles-safeguard-quality/)
- [Konrad Reiche — Blameless postmortem 5 Whys](https://konradreiche.com/blog/blameless-postmortem-by-design-in-praise-of-the-five-whys/)
- [Lee Zukor — Why is it so hard to stop the line](https://leezukor.com/2016/08/26/why-is-it-so-hard-to-stop-the-line/)
- [Fullstack Techies — React migration checklist legacy to AI 2025](https://fullstacktechies.com/react-js-migration-checklist-legacy-to-ai/)

---

**Recomendação imediata pra hoje:** começar por **F5-A (Larastan baseline ratchet)** — desbloqueia 4 rules customizadas (F5-C, F5-D, F5-E, F3-B) que sozinhas cobrem T-AP-1, T-AP-2, T-AP-8, T-AP-13 + R9. Esforço M (6-8h IA-pair). Pattern de instalação idêntico ao `ui-lint.yml` que já roda em prod — zero risco de "novo paradigma". Em paralelo (Wagner aprovar): F2-A (Wayfinder) em branch separada porque é beta — testar 3 telas e medir. Depois F1-A.

Próxima ação concreta agora: `composer require --dev larastan/larastan` + criar `phpstan.neon.dist` nível 0 baseline (gera `phpstan-baseline.neon` ratchet) + workflow `phpstan-gate.yml` na pasta `.github/workflows/` espelhando `ui-lint.yml`.
