---
slug: 0211-tanstack-query-data-fetching-padrao
number: 211
title: "TanStack Query como padrão de data-fetching em componentes — eliminar R7 (race condition) na raiz"
type: adr
status: proposto
authority: canonical
lifecycle: ativo
decided_by: [W]
decided_at: '2026-05-28'
module: _DesignSystem
quarter: 2026-Q2
tags: [react, data-fetching, tanstack-query, race-condition, prevencao-bugs]
supersedes: []
supersedes_partially: []
superseded_by: []
related:
  - 0094-constituicao-v2-7-camadas-8-principios
  - 0104-processo-mwart-canonico-unico-caminho
  - 0209-eslint-9-flat-config
  - 0210-type-safety-end-to-end-wayfinder
pii: false
review_triggers:
  - "Race condition em componente novo (similar a R7 Larissa)"
  - "Bundle size aumenta >10kb após install (validar trade-off)"
---

# ADR 0211 — TanStack Query como padrão de data-fetching

## Contexto

R7 da sessão Larissa 2026-05-28: scanner USB injetava SKU em <50ms, debounce 250ms do `useEffect` agendava setTimeout-fetch, Enter chegava antes do timeout disparar, fetch sync do scanner path adicionava produto, MAS o `setTimeout` original ainda em vôo → `setResults+setOpen(true)` DEPOIS do `setQuery('')` → dropdown reabria, Larissa clicava achando que não foi adicionado → **duplicação**.

Fix aplicado em [PR #1824](https://github.com/wagnerra23/oimpresso.com/pull/1824) (R7): AbortController + sentinela `lastSelectedAtRef` + guard loading no Enter handler. **Resolveu sintoma mas pattern continua frágil** — toda próxima tela MWART com `useEffect+fetch+setTimeout+debounce` vai repetir o mesmo bug se programador esquecer das 3 camadas.

Estado-da-arte 2026 ([dossier session](../sessions/2026-05-28-arte-prevencao-bugs-mwart-larissa.md) Frente 1) confirma: comunidade abandonou `useEffect` manual em favor de **TanStack Query** ou **SWR**. TanStack Query resolve 4 classes de bug por padrão:

1. **Cancellation automática** — passa `signal` ao `queryFn`, AbortController gerenciado pela lib
2. **Resultados stale ignorados** — request cuja key não-é-mais-atual nem chega ao state
3. **Dedup concorrentes** — 2 componentes chamam mesmo query? 1 request, 2 subscribers
4. **Cache + retry + DevTools** — observabilidade grátis em dev

`package.json` atual:
- ✅ Tem `@tanstack/react-table` (~5MB)
- ❌ Não tem `@tanstack/react-query`
- ❌ Não tem `swr`
- ❌ Não tem `msw`

`Sells/Create.tsx` (1409 LOC) faz fetch manual com `useEffect` + `setTimeout` 250ms — exatamente o anti-pattern. `ProductSearchAutocomplete` + `CustomerSearchAutocomplete` são os 2 primeiros candidatos pra migração piloto.

Alternativas avaliadas:

1. **TanStack Query v5** (~16KB gzip) — feature-rich, DevTools, cache infra, padrão de facto 2026
2. **SWR** (~5KB) — simpler API mas faltam Mutations + DevTools maduras
3. **Manter useEffect manual** + skill `preflight-component-fetch` (Tier B) — só doc, esquerda Tier 0 da prevenção

## Decisão

**Adotar `@tanstack/react-query` v5 como padrão pra todo data-fetching em componente React** do oimpresso. `useEffect + fetch + setTimeout + debounce` manual passa a ser **anti-padrão** (AP-16 novo no LICOES).

**Plano de adoção:**

**Fase 1 — Install + Provider (S, 2h):**

- `npm install @tanstack/react-query @tanstack/react-query-devtools`
- `QueryClient` instanciado em `AppShellV2` ou layout-raiz
- `<QueryClientProvider client={...}>` envolve toda a app
- `<ReactQueryDevtools initialIsOpen={false} />` em DEV
- Configuração default:
  - `staleTime: 60_000` (1min — Larissa raramente recarrega)
  - `gcTime: 5 * 60_000` (5min — cache after unmount)
  - `retry: 1` (UPOS endpoint failures geralmente são definitivos — 401, 422)

**Fase 2 — Pilot 2 autocompletes Sells (M, 4-6h):**

- `ProductSearchAutocomplete.tsx` — substitui `useEffect+setTimeout` por `useQuery({queryKey: ['products', term, locationId], queryFn: ({ signal }) => fetch(..., { signal })})`
- `CustomerSearchAutocomplete.tsx` — idem
- Mantém os 3 fixes do R7 (AbortController, sentinela, guard loading) como **defesa-em-profundidade** durante transição
- Pest tests R7 atuais (11 estruturais, 22 assertions) continuam verde

**Fase 3 — MSW + scanner race test (M, 4h):**

- `npm install -D msw vitest @vitest/ui`
- `vitest.config.ts` setup mínimo (não-blocker — Pest PHP continua canon)
- Suite `tests/scanner-race.test.tsx` simula KeyboardEvent sequence scanner USB <50ms
- Roda em CI workflow `vitest-gate.yml` separado

**Fase 4 — Anti-padrão LICOES novo (S, 1h):**

`AP-16 "Debounce + Promise sem cancelamento"` — catalogar pattern + exemplo R7.

**Fase 5 — Custom ESLint rule (depende [ADR 0209 ESLint](0209-eslint-9-flat-config.md), opcional):**

`no-uncancelled-fetch-in-effect`: detecta `useEffect` com `fetch()` sem `AbortController` ou sem `useQuery`. Custom rule via `@typescript-eslint/utils`.

**NÃO faz parte desta decisão (escopo separado):**

- Mutations via TanStack Query (POST/PATCH) — Inertia useForm continua canon pra Inertia-managed submits
- Optimistic updates — feature avançada, depois de adoção base
- Suspense integration — esperar React 19 stable maturity

## Justificativa

**Por que TanStack Query e não SWR:** Mutations + DevTools + ecosystem (TanStack family integra-se com react-table que já temos). 16KB vs 5KB diferença marginal pro user (gzipped, cached em CDN). Padrão de facto 2026 — comunidade migrou.

**Por que pilot 2 autocompletes Sells e não big-bang:** [ADR 0106 recalibração velocidade](0106-recalibracao-velocidade-fator-10x-ia-pair.md) — IA-pair acelera código mas mantém smoke real Larissa. 2 telas exercita o pattern + smoke prod biz=4 → 50+ telas MWART em sequência.

**Por que manter os 3 fixes R7 durante transição:** "Cinto + suspensório" — TanStack Query resolve race mas migração não é atômica. Cada componente migra individualmente. Fixes R7 ficam até última migração concluir.

**Por que MSW (Frente 1 ação F1-C):** simula `/products/list` em teste sem hit backend. Scanner USB sequence é difícil reproduzir em Pest PHP estrutural — Vitest tem `useFakeTimers()` pra controle determinístico do debounce.

**Por que NÃO mutations TanStack agora:** Inertia useForm tem semantics próprias (CSRF, validation errors via shared props, transform). Trocar quebraria 30+ telas existentes. Adoção restrita a **GET data-fetching** pra início.

## Consequências

**Positivas:**

- **R7 raiz eliminado** em paths com TanStack Query. Race condition é literalmente impossível: queryKey muda → request anterior é cancelado.
- DevTools ativo em DEV — Wagner vê network requests + cache state inline
- Cache infra grátis: trocar de cliente e voltar → não refaz fetch (staleTime)
- Pattern reutilizável: 50+ telas MWART futuras seguem mesmo padrão sem aprendizado
- Reduz LOC: `Sells/Create.tsx` 1409 LOC pode encolher ~100-150 LOC (debounce manual + useEffect + AbortController + sentinela R7)

**Negativas / Trade-offs:**

- **Bundle size +16KB gzip** — measurável mas não-crítico em prod Hostinger
- **Curva de aprendizado:** queryKey design, staleTime tuning, invalidation patterns. Mitigação: skill `tanstack-query-patterns` (Tier C on-demand, após pilot)
- **Provider em layout-raiz:** modificação Tier 0 visual de `AppShellV2` — review cuidadoso pra não vazar mudança em prod sem Wagner aprovar
- **Refactor incremental:** 50+ telas eventualmente, com smoke prod a cada lote. Não-baratos overall, mas amortiza ao longo de meses.

**Riscos mitigados:**

- R7 race condition (classe inteira)
- Memory leak `setTimeout` órfão pós-unmount
- Concurrent fetches duplicados (não-bug óbvio, mas waste)
- Bundle size em refresh excessivo (cache hits poupam network)

**Riscos não-mitigados:**

- Type drift (R8) — não cobre. [ADR 0210 Wayfinder](0210-type-safety-end-to-end-wayfinder.md) cobre
- Fallback silencioso backend (R9) — não cobre. [ADR 0212](0212-defensive-logging-fallback-paths.md) cobre
- Inertia useForm legacy patterns — escopo separado, não-objetivo

## Referências

- ADR 0094 — Constituição v2 §princípio 5 (SoC brutal — data layer separado de UI)
- ADR 0104 — Processo MWART canônico
- ADR 0106 — Recalibração velocidade fator 10x IA-pair
- ADR 0209 — ESLint 9 flat config (habilita custom rule no-uncancelled-fetch)
- [`prototipo-ui/LICOES_F3_FINANCEIRO_REJEITADO.md`](../../prototipo-ui/LICOES_F3_FINANCEIRO_REJEITADO.md) — AP-16 a catalogar
- [PR #1824 R7 fix scanner race](https://github.com/wagnerra23/oimpresso.com/pull/1824) — sintoma originário
- [Refine — React Query vs TanStack vs SWR 2025](https://refine.dev/blog/react-query-vs-tanstack-query-vs-swr-2025/)
- [Leapcell — Pitfalls of manual useEffect data fetching](https://leapcell.io/blog/the-pitfalls-of-manual-data-fetching-with-useeffect-and-why-tanstack-query-is-your-best-bet)
- [TanStack Query official docs](https://tanstack.com/query/latest)
