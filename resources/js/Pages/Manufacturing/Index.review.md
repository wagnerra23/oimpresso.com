---
page: Manufacturing/Index
file: resources/js/Pages/Manufacturing/Index.tsx
charter: resources/js/Pages/Manufacturing/Index.charter.md
review_round: 1
review_type: static-analysis
review_at: 2026-05-17
reviewer: W31 Bulk Review R1 (agent)
status: draft (aguarda Wagner)
seed_pattern: resources/js/Pages/Admin/GovernanceV4.review.md (referência charter v4)
---

# Review estática R1 — `resources/js/Pages/Manufacturing/Index.tsx`

> Análise estática sem execução. Esqueleto MWART Wave J (2026-05-16). Coexistência com Blade legacy `/manufacturing/production`.

## Resumo

Esqueleto minimal 178 linhas — listar production_purchase via rota nova `/manufacturing/v2/production`. PageHeader inline + 4 SummaryCards (total/finalizadas/pendentes/valor) + tabela 5 colunas (ref/data/local/total/status) + EmptyState com link pra rota legacy. Bem auto-contido — sem dependências externas exóticas. CTA "+ Nova produção" disabled com tooltip apontando rota legacy. Charter existe e é honesto sobre Non-Goals (não migra CRUD, não migra Recipes/BOM).

## Aderência ao canon

| Item | Status | Nota |
|---|---|---|
| Charter ao lado | ✅ EXISTE `Index.charter.md` | Status draft Wave J — owner Wagner. Mission/Non-Goals/UX/Anti-hooks bem definidos |
| RUNBOOK | ❌ AUSENTE per charter (linha 51) | Charter explicita "RUNBOOK em `memory/requisitos/Manufacturing/RUNBOOK-production-index.md`" como próximo passo. MWART bloqueador pra edits futuros |
| Inertia::defer | ⚠️ AINDA NÃO mas charter prevê (linha 50) | "Migrar para Inertia::defer quando queries crescerem" — hoje aceitável (smoke esqueleto); virá P1 ao adicionar filtros |
| Multi-tenant Tier 0 (ADR 0093) | ✅ Declarado charter G4 (linha 16) | "todas queries via ProductionService scoped por business_id". Verificar implementação no Service |
| Localstorage | ✅ N/A | Sem persistência |
| Cor semântica ADR 0110 | ❌ VIOLAÇÃO sistêmica | `bg-blue-600 text-white hover:bg-blue-700` (linha 55), `bg-gray-50/200/500/900` em ≥10 ocorrências (tabela/header), `bg-green-100 text-green-800` (linha 121), `bg-yellow-100 text-yellow-800` (linha 125). PageHeader inline sem usar `@/Components/shared/PageHeader` (que outras telas usam) |
| Não usa `AppShellV2` layout | ⚠️ AUSENTE | Outras telas Inertia exportam `Index.layout = (page) => <AppShellV2>{page}</AppShellV2>`. Esta NÃO declara — pode render sem shell (no sidebar/nav). Verificar default layout global em `app.tsx` |
| Não usa componentes shared canon | ❌ | `SummaryCard`/`EmptyState`/`PageHeader` inline próprios — duplicam `@/Components/shared/{KpiCard,EmptyState,PageHeader}` já existentes (vide kb/Graph.tsx) |
| Persona Larissa 1280px (charter linha 28) | ⚠️ tabela full-width ok | Mas SummaryCards `grid sm:grid-cols-4` pode squeeze em 1280px |

## Top 5 riscos identificados

1. **Componentes shared duplicados** — `SummaryCard`/`EmptyState`/`PageHeader` reinventados localmente. Viola SoC brutal (Princípio #5 Constituição v2). Refactor pra `@/Components/shared/KpiCard` + `@/Components/shared/EmptyState` + `@/Components/shared/PageHeader` (proven kb/Graph.tsx).
2. **AppShellV2 não declarado** — `Index.layout` ausente. Se default global cobre ok; senão tela renderiza sem sidebar/breadcrumb (UX quebrada).
3. **Cores cruas sistêmicas** (≥15 ocorrências `bg-gray-*`, `bg-blue-*`, `bg-green-*`, `bg-yellow-*`) violando ADR 0110.
4. **RUNBOOK AUSENTE bloqueia evolução** — charter prevê próximo passo "Charter MWART completo com RUNBOOK". Sem RUNBOOK próximo Edit em Index.tsx é bloqueado pelo hook `block-mwart-violation.ps1`.
5. **`productions: Production[] = []` default vazio** (linha 39) — se backend falhar, frontend mostra "Nenhuma produção" sem distinguir erro vs zero-state real. Charter G3 menciona "EmptyState honesto" mas faltam fallbacks de erro.

## Pest GUARD recomendados (pendente)

```php
it('renders /manufacturing/v2/production with summary scoped to business_id')
it('isolates productions biz=1 vs biz=4 (cross-tenant)')
it('shows EmptyState with link to legacy /manufacturing/production')
it('uses @/Components/shared/{KpiCard,EmptyState,PageHeader} (not inline copies)')
it('respects ADR 0110 — no raw bg-(gray|blue|green|yellow)-N classes')
it('declares Index.layout = AppShellV2 (or relies on global default verified)')
it('does not mutate state on GET render')
it('does not promote charter to live without Wagner SCREENSHOT approval')
```

## Recomendações priorizadas

| # | Ação | Prioridade | Owner sugerido |
|---|---|---|---|
| 1 | Criar `RUNBOOK-production-index.md` (gate MWART) | P0 bloqueador | Wagner aprova |
| 2 | Refactor inline → `@/Components/shared/{KpiCard,EmptyState,PageHeader}` | P1 | F3 followup |
| 3 | Declarar `Index.layout = AppShellV2` ou confirmar default global | P1 | F3 followup |
| 4 | Refactor cores cruas → tokens Cockpit V2 (batch ≥15 ocorrências) | P1 | F3 followup |
| 5 | Fallback de erro distinto de empty-state (toast/banner) | P2 | F3 followup |
| 6 | Pest GUARD biz=1 vs biz=4 isolation + smoke render | P2 | Agent C |
| 7 | Não promover `status: live` sem screenshot Wagner (charter anti-hook) | P0 governance | Wagner |

## Histórico

| Data | Autor | Mudança |
|---|---|---|
| 2026-05-17 | W31 Bulk R1 | Review estática R1 criada. Aguarda Wagner. Charter EXISTE, RUNBOOK AUSENTE P0. |
