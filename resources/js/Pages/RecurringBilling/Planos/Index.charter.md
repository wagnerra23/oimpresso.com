---
page: /recurring-billing/planos
component: resources/js/Pages/RecurringBilling/Planos/Index.tsx
owner: wagner
status: live
last_validated: 2026-05-17
parent_module: RecurringBilling
related_adrs: [0093, 0094, 0101, 0104, 0107, 0110, 0114, 0143]
tier: A
charter_version: 1
visual_source: prototipo-ui/prototipos/recurring/recurring-page.jsx
canon_method: Cowork KB-9.75
sidebar_group: fin (FINANCEIRO)
---

# Page Charter — /recurring-billing/planos (Planos · v1 Cowork)

> **Status:** live · Onda 6 do plano [Index-visual-comparison.md](../../../memory/requisitos/RecurringBilling/Index-visual-comparison.md).
>
> **Origem visual:** mesma família visual da Page /recurring-billing — tabela canônica + 4 KPIs + Tailwind 4 puro (sem `.rec-*` CSS).

---

## Mission

Listar planos de assinatura recorrente do business, com criação/edição/exclusão protegida (planos com assinatura ativa não podem ser deletados) — sub-rota da família Cobrança Recorrente.

---

## Goals — Features (faz · v1)

- AppShellV2 layout (sidebar canon + breadcrumb implícito via Header)
- Header `Planos · cobrança recorrente` + subtitle mono `N PLANOS · M ATIVOS · MRR potencial R$ X` + CTA `+ Novo plano`
- 4 KPI cards: total planos / ativos / MRR potencial / distribuição ciclos (barras horizontais)
- Tabela:
  - Coluna nome + slug (mono pequeno embaixo)
  - Coluna ciclo (label PT-BR: mensal/trimestral/semestral/anual/customizado)
  - Coluna valor (BRL right-aligned)
  - Coluna assinaturas ativas (count)
  - Coluna fiscal type (badge: NFe / NFS-e / Não emite)
  - Coluna ativo (badge verde/cinza)
  - Coluna ações (Editar link · Excluir botão danger)
- Busca server-side por nome ou slug (Enter dispara reload partial `only:[plans, kpis]`)
- Empty state: ícone + texto + CTA pra criar primeiro plano
- Inertia::defer pra `plans` paginados + `kpis` agregados (skill `inertia-defer-default` Tier B)
- Skeleton fallback enquanto props defer chegam
- Multi-tenant Tier 0: HasBusinessScope automático em Plan + Controller scopa explícito + Pest cross-tenant biz=1 vs biz=99
- Permission gate: `recurringbilling.access` OR `superadmin`
- Flash messages (success/error) renderizados em banner no topo (vem de `usePage().props.flash`)

---

## Non-Goals — Features (NÃO faz neste PR)

- ❌ Reordenar planos (drag-drop) — Onda futura
- ❌ Clonar plano — Onda futura
- ❌ Histórico de mudança de preço (Spatie Activitylog já registra, mas Page não mostra) — Onda futura
- ❌ Toggle ativo inline (precisa abrir Edit) — simplifica v1
- ❌ Filtros por ciclo/fiscal_type/ativo na sidebar — busca cobre v1
- ❌ Import/export CSV — Onda futura
- ❌ Modal de confirmação custom — usa `confirm()` nativo no v1 (Onda 9 padroniza ConfirmDialog component)

---

## UX Targets

- p95 first-paint < 1500ms (KPIs + tabela paginada 25 linhas)
- 0 erros JS console em smoke biz=1
- Cabe em monitor 1280px sem scroll horizontal
- Excluir plano com assinatura ativa retorna mensagem clara (banner vermelho, sem stacktrace)
- Tipografia canon: title 24px/700, subtitle mono 11px uppercase, valor tabular-nums

---

## UX Anti-patterns

- ❌ Inflar lógica de cálculo MRR/ciclo dentro do TSX (canon = computa no Controller `buildKpisPayload`)
- ❌ Modal/Dialog pra Create/Edit — canon = páginas dedicadas (Create.tsx / Edit.tsx) reusando layout
- ❌ Esconder erro de FK/integridade ("plano com assinatura ativa") — sempre mostra contagem exata
- ❌ Soft delete sem aviso visual ("o plano sumiu" sem flash) — sempre flash success
- ❌ Hard delete por engano — Plan tem SoftDeletes trait, destroy() chama `delete()` (vira soft)

---

## Endpoints alimentadores

| Método | Rota | Retorna |
|---|---|---|
| GET | `/recurring-billing/planos` | Inertia render `RecurringBilling/Planos/Index` props `{filters, plans (defer), kpis (defer)}` |
| GET | `/recurring-billing/planos/novo` | redirect via link → Create.tsx |
| DELETE | `/recurring-billing/planos/{id}` | redirect + flash (success ou error 422 se assinatura ativa) |

---

## Tests anti-regressão

- [Modules/RecurringBilling/Tests/Feature/Wave6PlanCrudTest.php](../../../../Modules/RecurringBilling/Tests/Feature/Wave6PlanCrudTest.php) — 5+ cenários:
  1. store() cria plano biz=1 com slug auto-gerado
  2. update() atualiza campos preservando slug se enviado igual
  3. destroy() soft-deleta plano sem assinatura ativa
  4. destroy() retorna 422 quando plano tem Subscription ativa
  5. Cross-tenant: plano biz=1 NÃO acessível via biz=99 (404 em edit/update/destroy)

---

## Refs

- [Index.charter.md](../Index.charter.md) — charter Page Cobrança Recorrente parent
- [Index-visual-comparison.md](../../../../memory/requisitos/RecurringBilling/Index-visual-comparison.md) — visual canon Onda 6 linha 112
- [SPEC.md US-RB-001](../../../../memory/requisitos/RecurringBilling/SPEC.md) — DoD cadastrar plano
- [ADR 0093 Multi-tenant Tier 0](../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md)
- [ADR 0101 Tests biz=1](../../../../memory/decisions/0101-tests-business-id-1-nunca-cliente.md)
- [ADR 0104 MWART](../../../../memory/decisions/0104-processo-mwart-canonico-unico-caminho.md)
- Skill [inertia-defer-default](../../../../.claude/skills/inertia-defer-default/SKILL.md)
