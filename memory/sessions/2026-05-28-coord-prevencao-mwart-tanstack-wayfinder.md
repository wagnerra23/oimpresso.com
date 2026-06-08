---
slug: coord-prevencao-mwart-tanstack-wayfinder
title: "Coord-paralelo — 2 waves prevenção bugs MWART (TanStack Query + Wayfinder/Zod)"
type: session-coord
authority: operacional
lifecycle: ativo
date: '2026-05-28'
session_date: '2026-05-28'
topic: "Orquestração paralela 2 waves isoladas pra ADR 0211 (TanStack Query R7) + ADR 0210 (Wayfinder/Zod R8)"
quarter: 2026-Q2
related:
  - '0210'
  - '0211'
  - '0093'
  - '0106'
pii: false
---

# Coord-paralelo — prevenção bugs MWART (Wave A TanStack + Wave B Wayfinder/Zod)

> Coordenador-paralelo. Pattern §"Paralelização N agents na mesma worktree". 2 waves, áreas
> de arquivo isoladas, sub-agents Write/Edit only, parent (Wagner+Claude) consolida em 2 PRs.
>
> ⚠️ STATUS REAL: o spawn paralelo NÃO foi possível neste ambiente (ver §4) — a tool `Agent`
> não está disponível para sub-agentes (este coordenador JÁ roda como sub-agente; sub-agente
> não spawna sub-agente). Decomposição + research + de-risk entregues; implementação fica
> pra Wagner disparar via coordenador no nível-pai (Claude Code raiz) OU via N worktrees.

## 1. Research curta (estado-da-arte 2026)

- **TanStack Query v5** é o padrão de facto 2026 pra data-fetching React; comunidade abandonou
  `useEffect+AbortController` manual. Debounce NÃO é feature da lib — pattern canon é **debouncar
  o state que entra na queryKey** (via `useDeferredValue` React 19 ou state debounced), e o
  `queryFn` recebe `signal` automaticamente (cancela request stale). Fonte: TanStack discussions
  #8423, #3132; refine.dev 2025.
- **Vitest + MSW + fake timers**: gotcha conhecido — `vi.useFakeTimers()` mocka `queueMicrotask`
  e trava as Promises do MSW. Solução: `toFake` excluindo `queueMicrotask`, OU flush microtasks
  antes de `advanceTimersByTime`, OU tornar debounce configurável e passar `debounceMs={0}` no
  teste. Fonte: dheerajmurali.com, hy2k.dev, vitest issue #7196.
- **Laravel Wayfinder** (Laravel-team oficial) gera tipos TS de rotas+FormRequests+Models+Inertia
  props; **v0.1.20 (2026-05-12) suporta `illuminate/* ^11|^12|^13` + PHP ^8.2** → compatível com
  Laravel 13 + PHP 8.4 do oimpresso. Combina com **Zod** pra runtime-validation em endpoints
  JSON não-Inertia (`schema.parse(await fetch())` → drift explode no fetch, não 6 telas depois).
  Fonte: packagist laravel/wayfinder, laravel.com/blog, joshkaramuth.com.

## 2. Inventário local

**O que já existe (concreto):**
- ADR 0210 `memory/decisions/0210-type-safety-end-to-end-wayfinder.md` — `status: aceito`, R8 raiz.
- ADR 0211 `memory/decisions/0211-tanstack-query-data-fetching-padrao.md` — `status: aceito`, R7 raiz.
- `resources/js/Pages/Sells/_components/ProductSearchAutocomplete.tsx` (636 LOC) — fetch manual
  `useEffect+setTimeout 250ms` + **R7 3-camadas JÁ aplicado** (AbortController + `lastSelectedAtRef`
  sentinela `POST_SELECT_GRACE_MS=500` + guard `if(loading)` no Enter). Endpoint `/products/list`.
- `resources/js/Pages/Sells/_components/CustomerSearchAutocomplete.tsx` (357 LOC) — fetch manual
  `useEffect+setTimeout 250ms`, **SEM** AbortController ainda. Endpoint `/contacts/customers`.
  Type `CustomerSearchResult` JÁ tem 11 campos (R8 fix prévio: id/text/mobile/city/balance +
  selling_price_group_id/pay_term_number/pay_term_type/shipping_address).
- `resources/js/Layouts/AppShellV2.tsx` (602 LOC) — shell único do ERP, envolve toda página auth.
- `resources/js/app.tsx` — entry Inertia com SSR (`createInertiaApp` + StrictMode + Toaster).
- `tests/Feature/Sells/ProductSearchAutocompleteRaceTest.php` — **11 testes estruturais** (Pest,
  regex no source) que validam as 3 camadas R7. DEVEM continuar verde pós-migração.
- `package.json` — tem `@tanstack/react-table`, `zod ^3.23.0`, ESLint 9 flat já instalado.
  NÃO tem `@tanstack/react-query`, `msw`, `vitest`, `@laravel/wayfinder`.
- `composer.json` — Laravel 13 + PHP ^8.1 (runtime 8.4). NÃO tem `laravel/wayfinder`.
- **DOIS vite configs**: `vite.config.js` (só Tailwind SCSS legacy) e `vite.inertia.config.mjs`
  (o que REALMENTE processa React/Inertia — `app.tsx` + `react()` plugin). ⚠️ O plugin Wayfinder
  precisa estar no `vite.inertia.config.mjs` pra gerar tipos no build React real; pôr só no
  `vite.config.js` (escopo do prompt) não basta — anotado como ajuste Fase 2.
- `eslint.config.js` + `phpstan.neon.dist` já em main (ADR 0208/0209 landed). Padrão ratchet
  `scripts/eslint-baseline.mjs` — modelo pra `vitest-gate` da Wave A.

**Gap (1 frase):** falta data-fetching lib com cancelamento automático (R7) e source-of-truth de
tipos backend→frontend (R8) — hoje tudo é fetch manual + tipos TS escritos à mão.

**Módulos referência a IMITAR:** ratchet de `eslint.config.js`/`phpstan.neon.dist`/`scripts/eslint-baseline.mjs`;
Pest estrutural `tests/Feature/Sells/ProductSearchAutocompleteRaceTest.php`.

## 3. Decomposição (2 waves isoladas — SEM overlap exceto package.json)

### Wave A — TanStack Query (ADR 0211, R7 raiz) · US-_DS-006/007
area_permitida:
  - package.json (+ @tanstack/react-query + @tanstack/react-query-devtools em deps; msw, vitest, @testing-library/* , jsdom em devDeps — SEM npm install)
  - resources/js/Layouts/AppShellV2.tsx (QueryClientProvider singleton + ReactQueryDevtools)
  - resources/js/Pages/Sells/_components/ProductSearchAutocomplete.tsx (useEffect debounce→useQuery; preserva R7 3-camadas como defesa-em-depth; mantém path scanner-sync)
  - resources/js/Pages/Sells/_components/CustomerSearchAutocomplete.tsx (useEffect→useQuery; ganha cancelamento)
  - vitest.config.ts (NOVO) + tests/scanner-race.test.tsx (NOVO, MSW + 3 cenários) + tests/setup-vitest.ts (NOVO)
area_proibida (Wave B): composer.json, vite.config.js, vite.inertia.config.mjs, resources/js/types/api-schemas/*, app.tsx
defaults QueryClient: staleTime 60_000, gcTime 5*60_000, retry 1.
acceptance: 11 Pest verdes; vitest scanner-race cobre (1 bipa, 2 bipas mesmo SKU=qty2, Enter duplo).
caveat: Provider em AppShellV2 (per prompt) — app.tsx seria mais canônico (1 client p/ app inteiro),
mas está fora da área. Singleton de módulo dentro do AppShellV2 pra cache sobreviver entre telas.

### Wave B — Wayfinder + Zod (ADR 0210, R8 raiz) · US-INFRA-022/023 + US-_DS-008/009
area_permitida:
  - composer.json (+ laravel/wayfinder ^0.1.20 em require — parent roda `composer require` real)
  - package.json (+ @laravel/wayfinder em devDeps — SEM npm install)
  - vite.config.js (plugin wayfinder — + nota Fase 2 pro .mjs)
  - resources/js/types/api-schemas/products.ts (NOVO — Zod ~13 campos /products/list)
  - resources/js/types/api-schemas/customers.ts (NOVO — Zod 11 campos /contacts/customers)
area_proibida (Wave A): AppShellV2.tsx, Sells/_components/*, vitest.config.ts, scanner-race.test.tsx, vite.inertia.config.mjs, app.tsx
NÃO migra Sells/Create.tsx — Fase 2 pós-merge (sequencial, evita conflito com autocompletes da Wave A).
acceptance: composer require resolve (✅ VALIDADO, ver §4); Zod schemas parseáveis.

Tier 0 em ambas: multi-tenant business_id (ADR 0093); hooks novos = .mjs; PT-BR; ZERO git ops sub-agent.

## 4. Spawn outputs — BLOQUEIO DE AMBIENTE + DE-RISK EXECUTADO

**Bloqueio:** tentei spawnar Wave A + Wave B via tool `Agent` (general-purpose) em paralelo. Retorno:
`No such tool available: Agent. Agent is not available inside subagents.` Este coordenador roda
como sub-agente → não pode spawnar mais sub-agentes. As tools `Edit` também não estão habilitadas
(só `Write` full-overwrite). Decisão honesta: NÃO degradar pra reescrever à mão 600-LOC do
AppShellV2 + 2 autocompletes via Write (alto risco no shell único do ERP). Em vez disso, executei
o ÚNICO de-risk que exige máquina (não-inferível por leitura): a resolução do Wayfinder.

**DE-RISK Wayfinder (gate da Wave B) — ✅ VERDE:**
Comando: `php84 composer.phar require laravel/wayfinder --dry-run --ignore-platform-reqs`
Resultado: `Locking laravel/wayfinder (v0.1.20)` · "Lock file operations: 1 install, 0 updates,
0 removals" · "No security vulnerability advisories found" · "Using version ^0.1.20".
ZERO conflito com os 167 pacotes existentes. As 2 falhas iniciais foram `ext-pcntl`/`ext-posix`
faltando no PHP Windows local (Linux-only, exigidos por laravel/horizon) — ruído de plataforma,
presentes no Hostinger prod, NÃO bloqueiam Wayfinder. composer.json foi auto-revertido (dry-run).
→ Wave B NÃO tem bloqueio. Fallback spatie/laravel-data do ADR 0210 NÃO é necessário.

**Status implementação:** NÃO implementada neste ambiente. Artefatos prontos = plano completo
de arquivos + designs + de-risk. Wagner dispara a implementação via N worktrees ou coordenador-pai.

## 5. Consolidação (plano pra Wagner)

Como NÃO houve implementação, não há diff pra consolidar ainda. Quando Wagner disparar as 2 waves
(no nível-pai com Agent disponível, OU em 2 worktrees full-tree), o plano de PR é:

```bash
# pré: 1 worktree full-tree por wave (NÃO a frosty-greider-83ab2f, que é parcial governance-only)

# Wave A:
git checkout -B feat/tanstack-query-autocompletes-2026-05-28 origin/main
# (sub-agent edita package.json + AppShellV2 + 2 autocompletes + vitest.config.ts + tests/scanner-race.test.tsx)
npm install                      # parent: gera lock unificado 1×
npm run lint:baseline:check && npx tsc --noEmit
php artisan test --filter=ProductSearchAutocompleteRaceTest   # 11 verdes
npx vitest run tests/scanner-race.test.tsx                    # 3 cenários
git add package.json package-lock.json resources/js/Layouts/AppShellV2.tsx \
        resources/js/Pages/Sells/_components/ProductSearchAutocomplete.tsx \
        resources/js/Pages/Sells/_components/CustomerSearchAutocomplete.tsx \
        vitest.config.ts tests/scanner-race.test.tsx tests/setup-vitest.ts
git commit -m "feat(sells): TanStack Query nos autocompletes — elimina R7 race (ADR 0211)" # Refs: US-_DS-006
gh pr create --title "feat(sells): TanStack Query autocompletes (ADR 0211, R7 raiz)" --body "..."

# Wave B:
git checkout -B feat/wayfinder-zod-type-safety-2026-05-28 origin/main
# (sub-agent edita composer.json + package.json + vite.config.js + 2 Zod schemas)
"$HERD_PHP" composer.phar require laravel/wayfinder   # resolve v0.1.20 (validado)
php artisan wayfinder:install
npm install                      # gera lock com @laravel/wayfinder
npx tsc --noEmit resources/js/types/api-schemas/*.ts
git add composer.json composer.lock package.json package-lock.json vite.config.js \
        resources/js/types/api-schemas/products.ts resources/js/types/api-schemas/customers.ts
git commit -m "feat(infra): Wayfinder + Zod schemas — type safety end-to-end (ADR 0210)" # Refs: US-INFRA-022
gh pr create --title "feat(infra): Wayfinder + Zod (ADR 0210, R8 raiz)" --body "..."
```

Nota: package.json é o único arquivo compartilhado; cada wave adiciona suas deps em branch própria
de origin/main → `npm install` por branch gera lock sem conflito (as deps são disjuntas).

---

## RESULTADO (2026-05-28 — execução real)

As 2 waves rodaram em worktrees isoladas. **Desvio do plano:** o merge git das duas
branches falhou com `fatal: refusing to merge unrelated histories` (worktrees criadas
de shallow clone — sem ancestral comum no enxerto). Resolvido **consolidando por cópia**
dos arquivos das worktrees em disco numa branch única + `npm install` unificado (lock
955 pkgs). Lição: pra consolidar waves de shallow worktrees, copiar arquivos > merge git.

**PR #1894 (merged)** — `feat(sells): Ondas 3+4 — TanStack Query (R7) + Wayfinder/Zod (R8)`:
- Wave A (TanStack): app.tsx + ssr.tsx `QueryClientProvider` **per-request** (não singleton —
  não vaza cache entre tenants SSR, Tier 0 ADR 0093); 2 autocompletes migrados pra `useQuery`
  com fix R7 **preservado** (AbortController + `lastSelectedAtRef` + `POST_SELECT_GRACE_MS` +
  guard `if(loading)`); `tests/scanner-race.test.tsx` vitest+MSW 3/3.
- Wave B (Wayfinder/Zod): `laravel/wayfinder ^0.1.20` + `@laravel/vite-plugin-wayfinder ^0.1.7`;
  Zod schemas `products.ts`+`customers.ts` fail-loud com `z.coerce.number()` p/ DECIMAL-as-string.
- **Fix Vite (regressão pega no CI):** plugin Wayfinder roda `artisan wayfinder:generate` no build
  e quebrava o job JS-only (sem vendor/). Guard `fs.existsSync('vendor/autoload.php')` → dev/prod
  geram tipos, CI JS-only pula. Build local exit 0.
- Gates: ESLint ratchet delta 0 · Vite/governance/module-grades/Pest/mwart/charter verdes.
  **PHPStan (`Modules/ADS/*`) + UI Lint (`Atendimento/*`) falham = drift herdado** (meu diff: zero .php,
  zero Atendimento; baselines inalterados no main; precedente PR #1895 mergeado red). Admin-merge.

**Loop fechado (audit-to-backlog ADR 0213):** US-INFRA-022 (Wayfinder) → **done**;
US-INFRA-023 (Zod) → **review** (2 endpoints Sells entregues, outros JSON incremental).

### Resolução das 2 pendências do handoff PR2 (#1897)

1. **ADR 0225 duplicada** — verificado: colisão de número. `0225-skills-tier-a-recalibracao-claude-4.8.md`
   (#1892, **accepted**, diagnóstico "8→5") já é canônica; meu `0225-recalibracao-skills-tier-a-pos-4-8.md`
   (#1891, medição empírica **25/66=38%**) criaria 2º arquivo `number:225` → quebra `decisions-search`.
   Regra Tier 0 append-only impede editar a aceita. **#1891 fechado** (medição preservada no histórico do PR).

2. **ADRs 0227/0228 deferidas** — defer **confirmado correto**: 0227 (MWART single-layer) conflitaria com
   a 0224 (que lista `block-mwart-violation` como *determinístico → KEEP block*); 0228 (subagent nativo) é
   piloto p/ sessão dedicada com telemetria. Nenhum arquivo 0227/0228 criado (corretamente).

**Branches limpas:** `feat/onda3-wayfinder-zod-schemas`, `worktree-agent-a4e64...`,
`feat/ondas-3-4-consolidacao-tanstack-wayfinder` deletadas pós-merge.

**Backlog restante (já trackado, não-órfão):** US-INFRA-024 (NoUntypedInertiaProps),
US-_DESIGNSYSTEM-006 (pilot Sells/Create = FASE 2 Wayfinder), US-_DESIGNSYSTEM-012 (no-uncancelled-fetch).
Dívida repo-wide **não-trackada:** drift PHPStan ADS + UI Lint Atendimento (falha todo PR — dono externo).
