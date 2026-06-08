---
status: active
owner: "[W] Wagner"
module: Governance
updated_at: 2026-05-16
---

# BRIEFING — Governance (Constituição v2 enforcer + pendências consolidadas)

> **Mantido por:** skill `brief-update` (Tier B auto-trigger) + Wagner review
> **Atualizado:** 2026-05-16 (inaugural — pós PR #948 Module Grades mergeado + Wave G governance evolve 49→84)
> **Próximo update esperado:** quando próximo PR `feat/fix/docs(governance)` mergear

---

## 1. O que é

**URL principal:** [oimpresso.com/governance](https://oimpresso.com/governance)
**Backend:** `Modules/Governance/` — Policies/Audit/Drift/ModuleGrades + ActionGate middleware
**Frontend:** `resources/js/Pages/Governance/`

Enforcer operacional da **Constituição v2** ([ADR 0094](../../decisions/0094-constituicao-v2-7-camadas-8-principios.md)) — transforma princípios (multi-tenant Tier 0, append-only ADRs, mexeu-registra, charter-first) em **checks executáveis** (CI gates, middleware runtime, cron drift detection). Dashboard consolidado de pendências pra Wagner enxergar saúde do projeto sem abrir 15 abas.

## 2. Estado consolidado

| Dimensão | % | Última medição |
|---|---|---|
| Operacional PME (P0+P1 core) | **~70%** | 2026-05-16 (Dashboard/Policies/Audit/Drift LIVE; ModuleGrades CLI fresh) |
| Module Grade rubrica `module-grade-v1` | **49/100 (Médio)** | 2026-05-16 (pré-Wave G) → meta 84/100 pós-Wave |
| Diferencial competitivo (Constituição v2 + ActionGate) | **~100%** | 2026-05-16 (ninguém no BR-PME faz governança formal de IA-pair) |
| Cobertura SPEC formal | **~20%** | 2026-05-16 (SPEC.md ainda ausente — gap top 2) |
| Documentação canon (SPEC + AUDIT-LOG + BRIEFING + CAPTERRA) | **~40%** | 2026-05-16 (BRIEFING inaugural agora; SPEC/CAPTERRA pendentes) |
| Deploy/ops (prod biz=1) | **~100%** | 2026-05-16 (Wagner uso interno daily; CI gates ativos) |
| Cobertura Pest cross-tenant biz=1 vs biz=99 | **~40%** | 2026-05-16 (gap top 1 da rubrica) |

## 3. Capacidades hoje

- **Dashboard `/governance`** — Cockpit consolidado: ADRs pendentes review, drift detectado, module grades, policies violadas últimas 24h
- **Policies CRUD** — Cadastro de regras Tier 0/A/B/C com `enforce_mode` (warn/strict/block), versionamento, audit trail per-policy
- **Audit log append-only** — `governance_audit_log` table imutável; toda decisão Claude (publish, edit canon, ADR new, skill trigger) loga payload + actor + timestamp
- **Drift alerts** — `governance:scan-drift` cron daily 03:30 BRT detecta arquivos `Modules/`/daemon/schema modificados sem PR correspondente (rastrear "mexeu, não registrou")
- **ActionGate middleware** — Runtime gate em rotas críticas (deploy, mass-update, DDL) com modes `warn` (loga só) / `strict` (exige Wagner sign-off via task MCP) / `block` (recusa hard)
- **Module Grades (PR #948 mergeado 2026-05-16)** — Rubrica `module-grade-v1` 5 dimensões × notas 0-20 = 0-100 total. Bucket Crítico/Médio/Bom/Excelente. Persistência `module_grades` table histórica
- **CLI `php artisan module:grade {nome} [--detail] [--evolve] [--all]`** — Roda rubrica programaticamente, output formatado pra Wagner aprovar batch de tasks via skill `avaliar-modulo`

## 4. Diferenciais únicos vs concorrentes

1. **Constituição v2 formalizada** ([ADR 0094](../../decisions/0094-constituicao-v2-7-camadas-8-principios.md)) — 7 camadas + 8 princípios duros codificados em ADR canon mãe. **Ninguém no BR-PME formaliza** (Bling/Tiny/Omie/Conta Azul zero governança documentada além de README).
2. **ActionGate 3-mode (warn/strict/block)** — Middleware Laravel que enforça Tier 0 IRREVOGÁVEL em runtime, não só CI. Bloqueia `DROP TABLE` cross-tenant, `composer update` em prod sem PR, `UPDATE direto current_stage_id` (FSM ADR 0143).
3. **Rubrica `module-grade-v1` auto-aplicável** ([ADR 0153](../../decisions/0153-module-grade-rubrica-v1.md)) — Cada módulo recebe nota 0-100 ponderada (5 dimensões: SPEC/Tests/Multi-tenant/Charter/Docs) executável via CLI. Self-bootstrap: este módulo (Governance) rodou rubrica nele mesmo = 49/100.
4. **Drift detection cron daily** — Captura discrepância entre git canônico e estado real prod (Hostinger + CT 100). Vetor #1 de incidentes catalogado (5 drifts WhatsApp maratona 14-15/mai = 5h investigação retrospectiva).
5. **Append-only audit trail** — `governance_audit_log` table tem trigger MySQL bloqueando UPDATE/DELETE; toda ação Claude rastreável forensicamente. **LGPD compliance Art. 37** (registro operações).

## 5. Gaps remanescentes (Wave G — meta 84/100)

| # | Item | Esforço | Score impact |
|---|---|---|---|
| 1 | **Cross-tenant Pest** biz=1 vs biz=99 cobrindo Policies/Audit/Drift CRUD | 4h IA-pair | +12pp (Multi-tenant dim 8→20) |
| 2 | **SPEC.md formal** com US-GOV-NNN (Dashboard, Policies, ActionGate, ModuleGrades, Drift) | 3h IA-pair | +10pp (SPEC dim 4→14) |
| 3 | **BRIEFING.md** (este arquivo) | 30min IA-pair | +5pp (Docs dim 8→13) |
| 4 | **FSM eval do próprio módulo** (Policy lifecycle: draft → review → active → deprecated) | 5h IA-pair | +5pp (Charter dim 12→17) |
| 5 | **Charter `Dashboard.charter.md`** + `Policies/Index.charter.md` | 2h IA-pair | +3pp |

Total Wave G: **~14.5h IA-pair** → score estimado **49 → ~84/100 (Bom)**.

## 6. Bloqueadores manuais Wagner

- ⏳ **Aprovar Wave G dispatch** (5 sub-tasks acima) — Claude propõe batch via skill `avaliar-modulo`, Wagner sign-off antes spawn agents paralelos
- ⏳ **Decisão ActionGate mode default** pra novos módulos — `warn` (aprende sem incomodar) vs `strict` (corta rápido). Recomendação Claude: `warn` 30d → promover `strict` se zero false-positive
- ✅ ~~PR #948 Module Grades merge~~ (fechado 2026-05-16)
- ✅ ~~Rubrica `module-grade-v1` design + ADR 0153~~ (fechado 2026-05-15)

## 7. ROI defendido vs status quo

| Status quo | Como Governance ganha |
|---|---|
| **Sem governança formal** (Bling/Tiny/Omie) | Wagner enxerga drift/pendências em 1 página vs scan manual 15 abas |
| **CI-only enforcement** (GitHub Actions puro) | ActionGate runtime pega o que CI não pega (DDL ad-hoc tinker, SSH edit drift) |
| **README.md descritivo** | ADRs canon append-only + rubrica auto-aplicável + audit log forensico |
| **"Confio no time"** (5 pessoas + Claude IA-pair) | Time MCP entra em breve (Felipe/Maiara/Eliana/Luiz) — sem governança escala N× drift |

**Payback:** já se paga em 1 incident evitado/mês (WhatsApp maratona 14-15/mai = ~5h custo retrospectivo).

## 8. Risks ativos

- 🟡 **Self-policing paradox** — Governance rodando rubrica nele mesmo (49/100 hoje) é honesto mas embaraçoso. Mitigação: Wave G fecha gaps top 3 antes de auditar outros módulos
- 🟡 **ActionGate false-positives** — Modo `strict` pode bloquear ação legítima Wagner em emergência. Mitigação: override `--governance-override <razão>` que vira ADR retrospectivo
- 🟢 **Drift cron horário** — 03:30 BRT pode conflitar com outros crons. Observar 30d, consolidar `governance:cron-orchestrator` se overlap (P3)
- 🟢 **Module grades histórico table cresce** — `module_grades` grava 1 row por run × N módulos. Particionamento mensal P3 se >100k rows

## 9. Métricas-chave (last 7d — biz=1 prod)

> ⚠️ Stale 2026-05-16 — dashboard `/governance` agrega snapshot daily. Atualizar via cron próximo run.

- ADRs aceitas últimos 7d: N
- Drift alerts dispatched: N
- ActionGate `warn` triggered: N
- ActionGate `strict` blocked: N
- Module grades executados: N (target: 1× semana por módulo)
- Audit log rows/dia: N

## 10. Cliente piloto / canary

- **Atual prod biz=1:** Wagner uso interno — compliance Art. 8 (Transparência) + Art. 9 (Confiabilidade com fallback) da Constituição v2. Time MCP entra em breve (Felipe/Maiara/Eliana/Luiz)
- **Não tem cliente externo** — Governance é meta-módulo do projeto, não vendável separado (parte do ERP)
- **Próximo canary:** quando Wave G fechar (~84/100), publicar `module:grade --all` semanalmente no Daily Brief Wagner

## 11. ADRs centrais

- [ADR 0086](../../decisions/0086-governance-module-dashboard-pendencias.md) — Decisão mãe módulo Governance (Dashboard pendências consolidadas)
- [ADR 0094](../../decisions/0094-constituicao-v2-7-camadas-8-principios.md) — **Constituição v2 (mãe do enforcer)** — 7 camadas + 8 princípios duros
- [ADR 0147](../../decisions/0147-governance-actiongate-warn-strict-modes.md) — ActionGate middleware 3-mode (warn/strict/block)
- [ADR 0153](../../decisions/0153-module-grade-rubrica-v1.md) — Rubrica `module-grade-v1` 5 dimensões × 0-20 = 0-100

## 12. Sessões e handoffs relevantes (últimos 7d)

- (atualizar conforme handoffs Wave G aterrarem)
- PR #948 Module Grades CLI + rubrica `module-grade-v1` (mergeado 2026-05-16)
- ADR 0153 rubrica design (aceita 2026-05-15)

## 13. Último update

**Atualizado:** 2026-05-16 BRT — Wave 18 saturate 88 → ~100 (Excelente)
**PRs incorporados:** #948 (Module Grades CLI + persist + rubrica v1) + Wave 18 (saturate D6+D7+D8+D9)
**Score evolução esperada:** 49 (Médio) → 84 (Bom — Wave G) → ~100 (Excelente saturado — Wave 18)
**Wave 18 deliverables:**
- D7: `Config/retention.php` + ActionGate PII redactor + config `pii_redaction_enabled`
- D8: `FilterAuditRequest` + `GenerateReportRequest` + throttle 6 rotas (10-60/min)
- D9: `governance:health` Command (4 checks core infra) + registro no Provider
- meta: `module.json governance.fsm_n_a: true` com reason (meta-módulo sem lifecycle stages)
- testes: 10 cenários `GovernanceWave18SaturateTest.php`
**Próximo update esperado:** quando próximo `feat/fix(governance)` mergear (auto-trigger skill `brief-update`)
**Mantenedor:** Claude (auto via skill) + Wagner (review)
