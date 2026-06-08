---
page: /governance/drift
component: resources/js/Pages/governance/DriftAlerts.tsx
owner: wagner
status: live
last_validated: 2026-05-16
parent_module: Governance
related_adrs: [0079, 0086, 0094, 0147]
tier: A
charter_version: 1
---

# Page Charter — /governance/drift

> **Status:** live. Detecção runtime de drift Constituição Art. 7 (Module Charter) — controllers fora do `SCOPE.md.contains[]` declarado. Mesma lógica do `bin/check-scope.php` em PHP runtime + leitura de `mcp_alertas` quando cron Enforcement #5 ativar.

---

## Mission

Mostrar a Wagner divergência entre intenção declarada (SCOPE.md de cada módulo) e realidade do filesystem (`Modules/<X>/Http/Controllers/`). Drift = controller existe mas não declarado = sintoma de mudança não-registrada (viola REGRA PRIMÁRIA "mexeu, registra"). Apoia disciplina arquitetural antes do time MCP (Felipe/Maiara/Eliana/Luiz) entrar.

---

## Goals — Features (faz)

- AppShellV2 + topnav + `<PageHeader>` shared
- KpiGrid cols=4 com `<KpiCard>` shared (controllers em drift, módulos com drift, sem SCOPE.md, total módulos)
- Tone semântico dinâmico: `warning` se drift > 0, `success` se zero
- Card "Drift detectado em runtime" listando módulo + controllers undeclared + total controllers reais
- Hint inline de remediação ("Adicione em SCOPE.md.contains[] OU mova OU declare em drift_alerts[]")
- Card "Módulos sem SCOPE.md" com badges agrupados
- Card "Histórico (mcp_alertas — últimos 30d)" com empty state explicativo enquanto cron Enforcement #5 pendente
- Filtra boilerplate (DataController/InstallController/SuperadminController/Controller) antes de comparar
- Aceita declared em `contains[]` OU em `drift_alerts[]` (transitório) — flexibilidade pra migração

---

## Non-Goals — Features (NÃO faz)

- ❌ Auto-fix (não altera SCOPE.md automaticamente — humano decide remediação)
- ❌ Suprimir alertas (cada drift exige resolução explícita; sem snooze)
- ❌ Persistir em `mcp_alertas` desta tela (cron Enforcement #5 cuida — Fase 5+1)

---

## UX Targets

- p95 first-paint < 1.2s (scan filesystem de ~30 módulos + YAML parse)
- 0 erros JS console
- Cores semânticas Cockpit V2: amber=warning (drift detectado), emerald=success (zero drift), info=neutro
- Mensagem clara quando YAML parse falha (log estruturado via `Log::error`, UI não quebra)

---

## UX Anti-patterns

- ❌ Eager-load todos controllers de todos módulos sem `Inertia::defer` se scan ficar lento (fica pra otimização — atualmente síncrono)
- ❌ Botão "ignorar drift" (vira gambiarra silenciosa — canon = resolver)
- ❌ Edit inline de SCOPE.md (humano edita no IDE, commita via git — esta tela é só leitura/alerta)
- ❌ Badge cor crua `bg-amber-100` sem tone semântico em `<KpiCard>` (canon = `tone="warning"`)
- ❌ Eager-load filesystem scan em todo render (cache opcional via session/short TTL futuro)

---

## Tests anti-regressão

- [bin/check-scope.php](../../bin/check-scope.php) — CLI equivalente (paridade lógica obrigatória)
- [tests/Feature/Governance/DriftDetectionTest.php](../../tests/Feature/Governance/DriftDetectionTest.php) — módulo fixture com controller undeclared

---

## Refs

- [ADR 0079 Constituição Governança](../../../../memory/decisions/0079-constituicao-governanca-7-artigos.md) Art. 7 (Module Charter)
- [ADR 0086 Governance Fase 5 MVP](../../../../memory/decisions/0086-governance-fase-5-mvp.md)
- [ADR 0094 Constituição V2](../../../../memory/decisions/0094-constituicao-v2-7-camadas-8-principios.md) — REGRA PRIMÁRIA "mexeu, registra"
- [Proibições — REGRA PRIMÁRIA](../../../../memory/proibicoes.md)
