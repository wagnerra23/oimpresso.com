---
page: /ads/admin/patterns
component: resources/js/Pages/ads/Admin/Patterns.tsx
related_prototype: n/a (herda PT-01 Lista; segue o Padrão de Tela)
owner: wagner
status: draft
last_validated: "2026-07-11"
parent_module: ADS
related_adrs: [114, 101, 93]
tier: B
charter_version: 1
---

# Page Charter — /ads/admin/patterns (DRAFT)

> **Status:** draft criado em 2026-07-11 no lote de cobertura de charters. Wagner aprova **Non-Goals + Anti-hooks** ANTES de virar `status: live`.
>
> Backend: `Modules/ADS/Http/Controllers/Admin/PatternsController@index` (rota `ads.admin.patterns.index`, middleware `auth` — V1 superadmin). Escopa `where('business_id', $businessId)` da sessão. `Inertia::defer` pra `patterns` (tabela + Wilson bound calc), `candidates`, `drifts` e `kpis` via `PatternLearningService`.
>
> PT-01 declarado por assinatura real: a tela tem `<table>`/`<thead>` (tabela `mcp_decision_patterns`) como conteúdo dominante, com faixa de KPIs no topo.

---

## Mission
Listar os padrões aprendidos pelo ADS (ARQ-0007 / T15) da tabela `mcp_decision_patterns`, usando Wilson Score Interval (Lower Bound 95%) pra separar sinal de ruído (3/3 ≠ confiável). É onde Wagner vê quais pares domínio × tipo estão prontos pra promoção a autônomo e quais estão degradando (drift).

---

## Goals — Features (faz)
- 4 KPIs: padrões totais, candidatos a promoção (Wilson LB ≥ 0.80), drifts detectados, já hardcoded.
- Card "Padrões prontos pra promoção": par domínio × event_type, sucessos, LB colorido por faixa + recomendação (mover pra ALLOW_BRAIN_A via PR).
- Card "Drifts detectados": taxa histórica → recente + amostras + recomendação.
- Tabela de padrões: domínio, event_type, amostras (sucesso/total), taxa naïve, Wilson LB (95%), status (Hardcoded / Pronto / Aprendendo).
- EmptyState com dica do cron `ads:learn-patterns` (agendado 02:00); skeleton via `<Deferred>`.

---

## Non-Goals — Features (NÃO faz)
- ❌ NÃO promove padrão nem edita PolicyEngine pela UI — promoção é decisão manual via PR git (a tela só recomenda).
- ❌ NÃO recalcula/reseta contagens de padrão pela UI (isso é o cron `ads:learn-patterns`).
- ❌ NÃO cruza businesses — patterns escopados por business_id da sessão.
- ❌ NÃO pagina nem exporta (lista todos os patterns do business por total_count).

---

## UX targets
- p95 < 1500ms (admin) ; cabe em 1280px (ROTA LIVRE) ; AppShellV2 ; tabela com overflow-x no mobile.

---

## Automation hooks (faz)
- `Inertia::defer` dispara os 4 payloads (Wilson bound + drift + GROUP BY) sob demanda, fora do initial render.
- Recomendações de promoção derivam do Wilson LB calculado a cada load (leitura), não escrevem nada.

---

## Anti-hooks (NÃO faz automaticamente)
- ❌ Sem polling / auto-refresh.
- ❌ Sem auto-promoção a hardcoded (sempre PR humano em PolicyEngine.php).
- ❌ Sem mutação em GET — tela read-only.

---

## Pendências antes de `status: live`
- [ ] Wagner aprova Non-Goals + Anti-hooks
- [ ] Smoke visual 1280/1440 (screenshot) — cobrir com candidatos/drifts e EmptyState
- [ ] Validar limiares (LB 0.80 / 25pp drift) com Wagner
