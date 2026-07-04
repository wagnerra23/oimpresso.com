---
page: /governance/policies
component: resources/js/Pages/governance/Policies.tsx
related_us: [US-GOV-002]
owner: wagner
status: live
last_validated: "2026-05-16"
parent_module: Governance
related_adrs: [79, 86, 94, 147]
tier: A
charter_version: 1
---

# Page Charter — /governance/policies

> **Status:** live (MVP). CRUD inline de policies (`mcp_governance_rules`) — Constituição Art. 8 (Policy Gating). Runtime gates enforçados pelo `ActionGate`. MVP cobre list + toggle enabled; edit inline + create new ficam pra próxima iteração com editor JSON.

---

## Mission

Permitir a Wagner editar policies de governança (rules do `ActionGate`) pelo painel `/governance/policies` sem precisar tocar em código. Ativar/desativar uma rule é decisão operacional frequente (ex: relaxar gate em janela de incidente, bloquear rule problemática). Toda mudança deve futuramente virar entry em `mcp_governance_rule_history` (Fase 5+1) pra audit.

---

## Goals — Features (faz)

- AppShellV2 + topnav + `<PageHeader>` shared
- KpiGrid cols=4 com `<KpiCard>` shared (total rules, enabled, triggered total, categorias)
- Rules agrupadas por `category` (ordenação: enabled DESC, category ASC, rule_key ASC)
- Toggle switch (button custom — emerald-500 quando enabled, zinc quando off) via `router.post('/governance/policies/{id}/toggle')` com `preserveScroll` + `preserveState`
- Cada rule mostra: `rule_key` (font-mono), `name` (medium), `description` (zinc-500), version badge, triggered_count
- Empty state via `<EmptyState>` shared (texto pedagógico: "Quando o decision flow ADS criar rules, aparecem aqui")
- Multi-tenant Tier 0: rules são globais (não scopadas business_id no MVP — Constituição vale pra tudo)

---

## Non-Goals — Features (NÃO faz)

- ❌ Create rule via UI (MVP — Wagner cria via seed/migration; UI vem depois com editor JSON)
- ❌ Edit inline de `name`/`description`/`condition_json` (MVP — fica pra próxima iteração)
- ❌ Delete rule (rules são append-only soft — toggle disabled basta; hard delete via tinker)
- ❌ Bulk toggle (1 rule por vez — força reflexão; bulk ops vão pra cron/script artisan)

---

## UX Targets

- p95 first-paint < 600ms (sem queries pesadas — `mcp_governance_rules` poucas dezenas)
- 0 erros JS console
- Toggle visual instantâneo (Inertia partial reload `preserveState`)
- Cores semânticas Cockpit V2: emerald=enabled, info=neutro, zinc=disabled
- Flash message "Policy #X ativada/desativada" via `back()->with('status')` (default Laravel session)

---

## UX Anti-patterns

- ❌ Confirmação modal pra toggle (toggle é reversível — modal só atrita; canon = ação direta + flash)
- ❌ Edit inline com `<input>` que salva on-blur (race condition com toggle — esperar editor JSON dedicado próxima iter)
- ❌ Esconder rules disabled (Wagner precisa ver todas pra reativar — só ordena ao fim)
- ❌ Toggle sem registrar histórico (Fase 5+1 — TODO `mcp_governance_rule_history`; sem isso, audit fica cego)
- ❌ Badge cor crua sem tone (canon = `variant="outline"` shadcn)

---

## Tests anti-regressão

- tests/Feature/Governance/PoliciesToggleTest.php — toggle atualiza `enabled` + `updated_at`
- tests/Feature/Governance/ActionGateTest.php — gate respeita `enabled=0`

---

## Refs

- [ADR 0079 Constituição Governança](../../../../memory/decisions/0079-constituicao-oimpresso-7-camadas-governanca.md) Art. 8 (Policy Gating)
- [ADR 0086 Governance Fase 5 MVP](../../../../memory/decisions/0086-fase-5-mvp-governance-actiongate-warn.md)
- [ADR 0094 Constituição V2](../../../../memory/decisions/0094-constituicao-v2-7-camadas-8-principios.md)
