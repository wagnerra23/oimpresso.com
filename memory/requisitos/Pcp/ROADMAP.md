---
module: Pcp
type: roadmap
status: proposta 2026-05-12
audience: Wagner
fator_estimate: 10x IA-pair (ADR 0106) + margem 2x; tarefas humano-limitadas (canary 7d, smoke real) mantém relógio mundo real
---

# ROADMAP — Modules/Pcp (5 fases sequenciais)

## Pré-requisitos (bloqueadores)

- ✅ ADR 0143 FSM canon LIVE prod biz=1 (done 2026-05-12)
- ✅ Multi-tenant Tier 0 ADR 0093 (done)
- ✅ Mapping Delphi PRODUCAO_KANBAN (done — research)
- ⏳ **Wagner aprova D1 (Pcp vs IProduction)** — bloqueia Fase 1
- ⏳ **Sinal qualificado piloto** (Vargas OU Extreme paga + reporta) — bloqueia Fase 2+
- ⏳ ADR PCP `accepted` (atualmente `proposed`)

## Fase 1 — Fundação (US-PCP-001..007) — **P0**

**Objetivo:** Schema + Models + Service core idempotent + FSM actions intermediárias funcionando em biz=1 (test environment).

**Entregas:**

- US-PCP-002 — D1 resolvido (rename IProduction OU novo Pcp + delete IProduction)
- US-PCP-001 — 4 migrations + triggers append-only
- US-PCP-003 — Models `Workstation`, `Operation`, `Appointment`, `Schedule` com `HasBusinessScope`
- US-PCP-006 — FSM seed actions intermediárias (5 actions × 2 processos = 10 entries em `sale_stage_actions`)
- US-PCP-007 — `RegisterAppointment` service idempotent + race-condition test
- Pest cross-tenant biz=1 vs biz=99 (ADR 0101)
- ⚠️ Estimate: ~15h IA-pair (margem 2x ADR 0106)
- ⚠️ Gate: smoke `php artisan tinker` cria appointment + verifica trigger append-only

**Bloqueador → Fase 2:** Wagner valida tinker smoke + nenhum cross-tenant leak.

## Fase 2 — UI admin (US-PCP-004, US-PCP-005, US-PCP-020 parcial) — **P0**

**Objetivo:** Cadastros postos + operações funcionando via UI (5 Pages Inertia).

**Entregas:**

- US-PCP-004 — CRUD `pcp_workstations` Inertia (Index/Create/Edit) — MWART process (visual-comparison.md + Pest baseline + Charter)
- US-PCP-005 — CRUD `pcp_operations` Inertia
- US-PCP-020 — RUNBOOK + Charter `.charter.md` por tela
- Seeder de defaults catálogo (8 postos + 12 operações típicas oficina/gráfica)
- ⚠️ Estimate: ~12h IA-pair
- ⚠️ Gate: Wagner aprova SCREENSHOT visual comparison (não tabela — ADR 0107)
- ⚠️ Skill `mwart-comparative` Tier A gera artefato `visual-comparison.md` obrigatório ANTES Edit/Write `.tsx`

**Bloqueador → Fase 3:** sinal qualificado piloto confirmado (Vargas OU Extreme contratado + reporta uso).

## Fase 3 — Mobile PWA + QR scan (US-PCP-008..011) — **P0 DIFERENCIAL**

**Objetivo:** Apontamento real funcionando em campo (mecânico/operador escaneia OS no celular).

**Entregas:**

- US-PCP-010 — QR token na label OS (extends `Modules/Repair/print_label.blade.php`)
- US-PCP-008 — Endpoint API JWT `POST /api/pcp/scan` + Sanctum + rate limit + LGPD audit log
- US-PCP-009 — PWA shell + scanner QR (`@zxing-js/library`) + IndexedDB offline queue + replay
- US-PCP-011 — Kanban shared (Repair `ProducaoOficinaController`) extends com agrupador configurável query param `?grouping=...` (8 agrupadores Delphi)
- Pest: idempotency + dual-scan duplicate prevention + offline replay
- ⚠️ Estimate: ~25h IA-pair + 1 semana relógio-mundo-real (smoke PWA real em celular cliente)
- ⚠️ Gate: piloto real (Vargas mecânico João escaneia 5 OS reais sem bug) + Wagner valida

**Bloqueador → Fase 4:** PWA estável em pelo menos 2 dispositivos (Android Chrome + iOS Safari) por 3 dias úteis sem bug.

## Fase 4 — Insights + dashboard (US-PCP-012..017) — **P1**

**Objetivo:** Dashboard PCP visível + bottleneck alerta + capacidade visão semana + performance operador (LGPD-guarded).

**Entregas:**

- US-PCP-013 — View agregada capacidade dia/semana
- US-PCP-012 — Cron `pcp:detect-bottleneck` 15min + alerta UI + (futuro) Jana RAG context
- US-PCP-014 — Performance operador (view + dashboard table com `pcp.performance.view` permission)
- US-PCP-015 — Agendamento `pcp_schedules` drag-drop FullCalendar
- US-PCP-016 — Integração `MfgRecipe` consumo automático ao concluir operação (hook `apontar_fim_operacao`)
- US-PCP-017 — Dashboard PCP Inertia (4 cards KPI + Kanban embed + heatmap)
- US-PCP-018 — Broadcast Centrifugo realtime (channel `pcp.{biz}.workstation.{id}`)
- ⚠️ Estimate: ~30h IA-pair + 2 semanas relógio-mundo-real (Larissa Vargas dão feedback)
- ⚠️ Gate: piloto valida dashboard como ferramenta de decisão real (não apenas relatório post-hoc)

**Bloqueador → Fase 5:** dashboard usado por gerente piloto pelo menos 3x/semana durante 2 semanas (sinal métrico — ADR 0105).

## Fase 5 — Cutover + canary 7d ROTA LIVRE (US-PCP-019) — **P2**

**Objetivo:** PCP ativo em biz=4 ROTA LIVRE (99% volume) ou outro piloto produção.

**Entregas:**

- Aviso prévio Larissa 7 dias antes (skill `commit-discipline` Tier A — comm com cliente)
- Canary 7 dias com `business_id IN (4)` no flag rollout
- Smoke real em prod (biz=4): mínimo 10 appointments criados + 0 bug crítico
- Monitor 30 dias `php artisan jana:health-check` extended check `pcp_drift` (paridade `fsm:scan-drift`)
- Rollback plan: feature flag desligar via `.env` + revert migration (revertível)
- ⚠️ Estimate: ~8h IA-pair + **7 dias canary + 30 dias monitor** (relógio mundo real — ADR 0106 humano-limitado)
- ⚠️ Gate: Wagner aprova prod (skill `publication-policy` Tier A) após smoke verde

## Backlog P2-P3 (pós-Fase 5)

- US-PCP-021 — Jana Brain B análise contextual gargalo (substitui regra simples por insight conversacional) — após ADS canon estável
- US-PCP-022 — MRP sugestão compras automática (consumo BoM × estoque atual × lead time) — concorre com Frepple/SAP
- US-PCP-023 — Manutenção preventiva máquinas (`pcp_workstations.maintenance_schedule`) — concorre com SAP PM
- US-PCP-024 — Qualidade detalhada (motivo refugo, foto antes/depois, fluxo aprovação) — ComVis específico
- US-PCP-025 — Capacity Scheduling Board visual (Gantt-like) — concorre com SAP scheduling board
- US-PCP-026 — Modules/Vestuario integration (costura/corte multi-operadora) — quando sinal qualificado novo cliente

## Riscos & mitigações

| Risco | Mitigação |
|---|---|
| **PWA câmera iOS Safari quirks** | Capacitor wrap fallback como Plano B (US-PCP-009 mantém PWA como default; Capacitor wrap apenas se >2 dispositivos quebrarem) |
| **Operador esquece stop → appointment vazio 24h+** | Cron `pcp:auto-close-stale-appointments` daily 23h BRT força close + alerta gerente |
| **Cross-tenant leak via `Modules/IProduction` legacy permission `iproduction.*`** | Audit + delete Modules/IProduction (D1 opção c) limpa zombie ANTES de Fase 1 |
| **FSM action intermediária quebrar transição automática `concluir_producao`** | Pest exhaustive: "100% operações done deve disparar transição" + 0143 `fsm:scan-drift` detecta orphan |
| **Sinal qualificado piloto não chegar até jul/2026** | Re-priorizar — Vestuario (ROTA LIVRE biz=4) tem PCP simples (costura) que pode validar Fase 1-3 mesmo sem Vargas/Extreme |
| **LGPD performance operador exposição indevida** | Permission `pcp.performance.view` gate + PII redactor logs + audit log access |

## Critério de "feito"

- Todas Fases 1-5 mergeadas + health-check daily verde 30d
- Pelo menos 1 cliente vertical (Vargas OU Extreme OU ROTA LIVRE) usando PCP em produção
- `pcp:scan-drift` zero alertas em 7d consecutivos
- Charter `.charter.md` atualizado em todas Pages
- ADR PCP movido pra `accepted` (após Fase 1 mergeada + smoke verde)

---
**Versão inicial 2026-05-12** — proposta sequencial fundamentada em ADR 0106 estimates 10x IA-pair + ADR 0105 sinal qualificado + ADR 0104 MWART.
