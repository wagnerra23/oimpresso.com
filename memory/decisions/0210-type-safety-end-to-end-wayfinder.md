---
slug: 0210-type-safety-end-to-end-wayfinder
number: 210
title: "Type safety end-to-end via Wayfinder — eliminar R8/AP-12 (type drift backend↔frontend) na raiz"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
decided_by: [W]
decided_at: '2026-05-28'
module: Infra
quarter: 2026-Q2
tags: [type-safety, wayfinder, inertia, typescript, prevencao-bugs, drift]
supersedes: []
supersedes_partially: []
superseded_by: []
related:
  - 0094-constituicao-v2-7-camadas-8-principios
  - 0104-processo-mwart-canonico-unico-caminho
  - 0208-larastan-baseline-ratchet
  - 0209-eslint-9-flat-config
pii: false
review_triggers:
  - "R8-class bug (controller devolve N, frontend lê < N) reportado em outra tela"
  - "Wayfinder atinge versão 1.0 stable (sair de beta)"
---

# ADR 0210 — Type safety end-to-end via Wayfinder

## Contexto

R8 da sessão Larissa 2026-05-28: `ContactController@getCustomers` devolve **11 campos** no select (`balance`, `selling_price_group_id`, `pay_term_number/type`, `shipping_address`, `address_line_1/2`, etc). Frontend `CustomerSearchResult` lê **5** (`id`, `text`, `mobile`, `city`, `balance`). Drift silencioso: cliente VIP com grupo ATACADO cobrava preço balcão por 15+ dias até Larissa reportar.

Padrão é **AP-12 endpoint reutilizado sem ler payload** catalogado em [`LICOES_F3_FINANCEIRO_REJEITADO.md`](../../prototipo-ui/LICOES_F3_FINANCEIRO_REJEITADO.md). Mas catálogo é doc passivo — sem ferramenta detectando, drift volta a cada migração F3.

Estado-da-arte 2026 ([dossier session](../sessions/2026-05-28-arte-prevencao-bugs-mwart-larissa.md) Frente 2) elenca 3 caminhos:

1. **Laravel Wayfinder** (Laravel team oficial, Jan 2026 beta) — analyser de rotas + FormRequests + Models + Enums + Inertia props → gera `.d.ts` automaticamente. Roda watch em `npm run dev`.
2. **spatie/laravel-data v4 + spatie/typescript-transformer v3** — DTOs PHP `Data` class viram TS interface regenerados.
3. **Zod schemas no client** — runtime validation `schema.parse()`. Combina com TanStack Query: drift explode no fetch.

`composer.json` confirmado: nenhum dos 3 instalados. `package.json` tem `zod ^3.23.0` (presença pontual, provável uso em form).

Alternativas combinadas:

- **Wayfinder primário + Zod em endpoints non-Inertia** = canon Laravel-team oficial + cobertura completa
- **spatie/laravel-data primário** = mais explícito (cada `Inertia::render` declara DTO), mas refactor pesado (12h+ por módulo)
- **Só Zod no client** = drift detectado mas SEM single-source-of-truth backend — duplica esforço

## Decisão

**Adotar Laravel Wayfinder como source-of-truth de tipos** (rotas + FormRequests + Models + Inertia props auto-derivados); **Zod como gap-filler em endpoints JSON não-Inertia** (`/products/list`, `/contacts/customers`).

**Plano de adoção em 3 telas piloto antes de full-roll** (mitigação do risco beta):

**Fase 1 — Install + tooling (2026-06):**

- `composer require laravel/wayfinder` (mesmo após chegar 1.0; betas dependem da maturidade na data de execução)
- `php artisan wayfinder:install`
- `npm install @laravel/wayfinder` (client-side helpers)
- Vite plugin `wayfinder()` em `vite.config.js`
- `resources/js/types/wayfinder/` gerado em watch (`npm run dev`)

**Fase 2 — Pilot 3 telas (2026-06, ~6h IA-pair):**

- `Sells/Create.tsx` — corrige R8 raiz na própria tela que originou
- `Modules/Financeiro/Pages/Unificado/Index.tsx` — segunda tela mais reportada
- Dashboard principal (`Dashboard/Index.tsx`) — máximo número de tipos exercitados

Cada uma:
- Inertia props passam por Wayfinder type-gen
- Backend Controller migrado pra usar `Data` class spatie (próxima fase) OU declarar return type-hint estrito que Wayfinder analisa

**Fase 3 — Zod schemas em endpoints JSON (2026-06, 6h):**

Endpoints que NÃO vão por Inertia (chamados via fetch direto do client):
- `/products/list` → schema Zod, `schema.parse(await fetch(...))` no client
- `/contacts/customers` → idem
- Quaisquer outros endpoint API JSON-only

**Fase 4 — Custom PHPStan rule (depende [ADR 0208](0208-larastan-baseline-ratchet.md) maduro):**

`NoUntypedInertiaProps`: detecta `Inertia::render('X', $data)` onde `$data` não tem keys batendo com TS interface Wayfinder gerada. Erro tier 5 PHPStan.

**Fase 5 — Anti-padrão LICOES novo:**

`AP-17 "Inertia props não tipadas via Wayfinder/Data class"` — catalogar pattern + exemplo R8.

## Justificativa

**Por que Wayfinder e não spatie/laravel-data primário:** Wayfinder é **Laravel team oficial** (Janeiro 2026), funciona com Eloquent direto sem refactor das ~14k linhas de controller. Spatie/Data exige declarar DTO pra cada `Inertia::render` — refactor pesado em codebase com Modules/<X>/Controllers de 1000+ LOC.

**Por que beta é aceitável agora:** Laravel team valida betas em prod via Forge antes de release. Wayfinder lançado Jan 2026, mature suficiente pra adoção piloto. Risco mitigado por:
- Piloto 3 telas antes de roll-out
- Rollback fácil (deletar pasta `resources/js/types/wayfinder/` e reverter `vite.config.js`)
- Não-blocker: tipos antigos manuais continuam funcionando em paralelo enquanto piloto roda

**Por que Zod complementar:** endpoints JSON-only (`/products/list`, `/contacts/customers`) não passam por Inertia render. Wayfinder não cobre. Zod no client serve dupla função: (1) validação runtime fail-loud, (2) inferência de tipo. Combinado com TanStack Query (próximo ADR), drift explode no fetch.

**Por que pilot 3 telas:** [ADR 0106 recalibração velocidade fator 10x](0106-recalibracao-velocidade-fator-10x-ia-pair.md) — IA-pair acelera 10x em código mas mantém relógio do mundo real em "monitor 30d, smoke real". Wayfinder beta merece esse smoke 30d antes de força-bruta em 50+ telas MWART.

## Consequências

**Positivas:**

- **R8 raiz eliminado:** Controller select 11 campos → TS interface gerada com 11 campos automaticamente. Frontend que tenta ler `customer.shipping_address` quando type não declara = erro `tsc --noEmit` no build.
- AP-12 vira **impossível** em paths cobertos (Inertia props). Doc passa de "lembrar de ler payload" pra "código não compila se drift".
- Onboarding novo dev acelerado: VSCode/PHPStorm autocomplete em `data.customer.<campos visíveis>`.
- Refactor seguro: rename campo no backend explode build se frontend ainda usa nome antigo.

**Negativas / Trade-offs:**

- **Beta risk:** Wayfinder pode ter breaking changes. Mitigação: piloto + rollback pronto.
- **Watch overhead:** `npm run dev` precisa rodar mais um plugin. Performance dev — observar.
- **Refactor inicial:** 3 telas piloto exigem cleanup do tipo TS manual existente. Não é grande mas é ritualizado.
- **Endpoints JSON antigos** (não-Inertia): cobertura via Zod precisa schema escrito caso a caso. Não-automático.
- **CI gate custom rule** (Fase 4) é L esforço (10h). Não imediato.

**Riscos mitigados:**

- R8 type drift (raiz)
- AP-12 endpoint reutilizado sem ler payload (classe inteira)
- Refactor regression silenciosa (campo renomeado backend)

**Riscos não-mitigados:**

- Race conditions (R7) — não cobre. [ADR 0211 TanStack Query](0211-tanstack-query-data-fetching-padrao.md)
- Fallback silencioso (R9) — não cobre. [ADR 0212 Defensive logging](0212-defensive-logging-fallback-paths.md)
- Endpoints externos (Asaas, NFe, etc) — fora de scope Wayfinder. Zod cobre se quisermos.

## Referências

- ADR 0094 — Constituição v2 §princípio 7 (transparência)
- ADR 0104 — Processo MWART canônico
- ADR 0106 — Recalibração velocidade fator 10x IA-pair
- ADR 0208 — Larastan PHPStan baseline ratchet
- [`prototipo-ui/LICOES_F3_FINANCEIRO_REJEITADO.md`](../../prototipo-ui/LICOES_F3_FINANCEIRO_REJEITADO.md) — AP-12 catalogado
- [Laravel blog — Wayfinder end-to-end type safety](https://laravel.com/blog/laravel-wayfinder-end-to-end-type-safety-for-php-and-typescript)
- [Hafiz.dev — Wayfinder type-safe routes + forms Inertia](https://hafiz.dev/blog/laravel-wayfinder-type-safe-routes-and-forms-with-inertia)
- [Spatie — TypeScript transformer with laravel-data](https://spatie.be/docs/laravel-data/v4/advanced-usage/typescript)
- [Vanpachtenbeke — Type-safe Inertia responses with View Models](https://vanpachtenbeke.com/posts/type-safe-inertia-responses-with-view-models/)
- [Josh Karamuth — TanStack + Zod + DTO pattern](https://joshkaramuth.com/blog/tanstack-zod-dto/)
