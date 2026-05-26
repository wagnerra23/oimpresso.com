---
lifecycle: active
owner: [W]
module: Governance
status: aceito
authority: canonical
created_at: 2026-05-16
updated_at: 2026-05-16
related_adrs: [0086, 0094, 0101, 0147, 0153, 0154]
parent_charter: mission.constituicao-v2
tags: [governance, enforcement, actiongate, audit, policies, module-grade]
pii: false
na_justified:
  D4.b: "Módulo de governança não tem state machine (Constituição Art. 8+9 — design intencional)"
  D5: "Cross-tenant intencional Wagner-only (Constituição Art. 6 — tabelas mcp_* sem business_id)"
  D1.a: "BusinessScope N/A: Entities cross-tenant intencional (mcp_governance_rules/mcp_audit_log/etc)"
---

# Modules/Governance — SPEC

> Status: **active** — entregue Fase 5 MVP ([ADR 0086](../../decisions/0086-fase-5-mvp-governance-actiongate-warn.md)), evoluído com ModuleGrade ([ADR 0153](../../decisions/0153-module-grade-rubrica-v1.md), PR #948 mergeado 2026-05-16).
> Última atualização: 2026-05-16 (Wave G — Governance evolve 49→84).

## Mission

`Modules/Governance` é o **enforcer runtime + dashboard humano da Constituição v2** ([ADR 0094](../../decisions/0094-constituicao-v2-7-camadas-8-principios.md)). Concentra UI Inertia + middleware ActionGate + leitura consolidada das tabelas `mcp_*` para que Wagner (e Felipe/Maiara/Eliana/Luiz quando entrarem) consigam (a) **ver compliance do projeto inteiro em 5s**, (b) **drill-down em qualquer dimensão** (policies, audit, drift, module grade), (c) **agir** via ações canônicas (toggle policy, reverter ADR, evoluir módulo). Coabita semanticamente com `Modules/TeamMcp` (Identity Mesh — Trust Tiers + ActorResolver) e usa `mcp_*` tabelas como fonte da verdade transversal.

## Bounded context — cross-tenant INTENCIONAL

> ⚠️ **Exceção formal ao princípio Tier 0 multi-tenant** ([ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md)). Justificada pela **Constituição v2 Art. 6 + Art. 8** ([ADR 0094](../../decisions/0094-constituicao-v2-7-camadas-8-principios.md)) — governança é **L1 transversal** entre tenants, não dado de negócio. Auditável.

Tabelas que **NÃO têm `business_id`** (operam cross-tenant):

| Tabela | Função | Por que cross-tenant |
|---|---|---|
| `mcp_memory_documents` | ADRs, runbooks, SPECs canônicos | Decisão arquitetural vale pra projeto inteiro, não por tenant |
| `mcp_governance_rules` | Policies declarativas (ex: `module.x.requires.adr_for_changes`) | Regra de governança é da plataforma, não do cliente |
| `mcp_audit_log` | Audit trail de tools MCP + kernel actions | Append-only via trigger MySQL ([ADR 0084](../../decisions/0084-triggers-mysql-imutabilidade-mcp-audit-log.md)) — auditor externo vê tudo |
| `mcp_actors` | Identity Mesh — humanos + agentes IA com trust_level | Actor "Claude Code @ Wagner" é único, não replica por business |
| `mcp_skill_versions` | Versão de cada Skill .claude/skills/ com aprovação | Skill é código global do repo, não config de cliente |
| `mcp_skill_approvals` | Pending approvals de skill mudança | Wagner aprova skill no nível plataforma |
| `mcp_module_grades_history` | Snapshot 90d de notas `module-grade-v1` ([ADR 0153](../../decisions/0153-module-grade-rubrica-v1.md)) | Métrica do projeto, não do tenant |

Dados de negócio (transações, contatos, produtos) **continuam scoped via `business_id`**. Governance **lê** dados de negócio só agregados (counts, KPIs) — nunca expõe linha individual cross-tenant.

**Defesa contra vazamento:** rotas `/governance/*` só acessíveis por usuários com permission `governance.*` (default: Wagner superadmin). Stack middlewares completa: `['web','auth','SetSessionData','language','timezone','AdminSidebarMenu','CheckUserLogin']`.

## Personas

| Persona | Contexto | Acesso |
|---|---|---|
| **Wagner (superadmin)** | Dono do projeto, vê projeto inteiro | global, todas permissions `governance.*` |
| **Felipe/Maiara/Eliana/Luiz (time interno)** | Devs+suporte (entram via MCP em breve) | `governance.dashboard.view` + `module-grades.view` (read-only inicialmente) |
| **Auditor externo (futuro)** | Contador, advogado LGPD | `governance.audit.view` + export read-only |
| **Tenant client** | Larissa (ROTA LIVRE) | **NÃO acessa** `/governance/*` — tela é meta-camada da plataforma |

## User Stories

> **Convenção:** `US-GOV-NNN`
> **DoD mínimo:** rota autorizada (`403` se sem permission), shape Inertia JSON-friendly, Pest Feature test (auth + permission), dark mode, mobile responsivo, sem PII real (LGPD).

### Área Dashboard consolidado

#### US-GOV-001 · Dashboard consolidado `/governance` ✅ DONE
- **Rota:** `GET /governance` ([routes.php](../../../Modules/Governance/Http/routes.php))
- **Controller:** `DashboardController@index` ([source](../../../Modules/Governance/Http/Controllers/DashboardController.php))
- **Como** Wagner **quero** abrir `/governance` **para** ver compliance % do projeto + 6 KPIs em <5s sem clique adicional.
- **KPIs lidos (cross-tenant, agregado):**
  - ADRs `status=proposto` pendentes (`mcp_memory_documents`)
  - Active policies count (`mcp_governance_rules.enabled=1`)
  - Skill approvals pending (`mcp_skill_approvals.status=pending`)
  - Audit highlights últimas 24h (`mcp_audit_log` com erro ou kernel_action)
  - Actors count não-revogados (`mcp_actors`)
  - Compliance score heurístico Constituição v2 (8/10 plenos = 80%)
- **Status:** done (ADR 0086 entregue MVP).

### Área Policies (mcp_governance_rules)

#### US-GOV-002 · Policies listagem + toggle ativo/inativo 🟡 PARCIAL
- **Rota:** `GET /governance/policies` + `POST /governance/policies/{id}/toggle`
- **Controller:** `PoliciesController@index` + `toggle`
- **Como** Wagner **quero** ver todas policies declarativas + ligar/desligar **para** calibrar gradualmente sem deploy.
- **DoD extra:** CRUD completo (criar/editar/deletar) **pendente** próxima fase. MVP só lista + toggle.
- **Status:** parcial — apenas index + toggle. Inline editor backlog.

### Área Audit log

#### US-GOV-003 · Audit log drill-down filtrável 🟡 PARCIAL
- **Rota:** `GET /governance/audit`
- **Controller:** `AuditController@index`
- **Como** auditor **quero** filtrar `mcp_audit_log` por actor, action, tool, data range **para** investigar uso de tools MCP + kernel actions.
- **DoD extra:** export LGPD CSV **pendente** próxima fase.
- **Status:** parcial — listagem básica. Filtros avançados + export backlog.

### Área Drift Alerts

#### US-GOV-004 · Drift alerts (Module Charter Art. 7) 🟡 PARCIAL
- **Rota:** `GET /governance/drift`
- **Controller:** `DriftAlertsController@index`
- **Como** Wagner **quero** ver módulos com drift detectado (SCOPE.md violado, ADR ausente, charter stale) **para** agir antes que vire dívida estrutural.
- **DoD extra:** integração com `mcp_alertas` + escalação automática via PR auto-open **pendente**.
- **Status:** parcial — listagem manual hoje. Auto-detect via cron backlog (ver US-GOV-009).

### Área ActionGate Middleware

#### US-GOV-005 · ActionGate middleware (modo warn/strict) ✅ DONE (warn)
- **Componente:** `Modules/Governance/Http/Middleware/ActionGate.php` (alias `actiongate`)
- **Como** Constituição v2 Art. 8 **quero** gate runtime checando actor trust_level + revogação **para** enforcement de Tier 0 IRREVOGÁVEL.
- **Modos:**
  - `off` — middleware loaded mas no-op
  - `warn` (default MVP, env `GOVERNANCE_ACTIONGATE_MODE=warn`) — loga `Log::channel('single')->warning(...)` sem bloquear
  - `strict` — retorna `403` + audit obrigatório
- **DoD extra:** uso em rotas via `actiongate:L1`/`actiongate:L2`/`actiongate:L3` (Trust Tier obrigatório).
- **Status:** done modo warn. Migração warn→strict após 4 semanas calibração (ADR 0086).

### Área Module Grades (ADR 0153 — entregue PR #948)

#### US-GOV-006 · Module Grade Dashboard `/governance/module-grades` ✅ DONE
- **Rota:** `GET /governance/module-grades`
- **Controller:** `ModuleGradeController@index` ([source](../../../Modules/Governance/Http/Controllers/ModuleGradeController.php))
- **Page Inertia:** `resources/js/Pages/governance/ModuleGrades/Index.tsx` + charter ao lado
- **Como** Wagner **quero** ver tabela ordenada com nota 0-100 + bucket de cor pra cada um dos 34 Modules **para** ver maturidade do projeto inteiro em 5s.
- **DoD extra:** filtro por bucket (chips), busca por nome, KPI agregado (média projeto + distribuição buckets), `Inertia::defer` em `gradeAllModules()` (I/O filesystem 1-2s × 34 módulos), cache 5min server-side.
- **Status:** done (PR #948 mergeado 2026-05-16).

#### US-GOV-007 · Module Grade Drill-down + botão Evoluir ✅ DONE
- **Rota:** `GET /governance/module-grades/{name}` (regex `[A-Za-z0-9_-]+`)
- **Controller:** `ModuleGradeController@show`
- **Page Inertia:** `resources/js/Pages/governance/ModuleGrades/Show.tsx` + charter
- **Como** Wagner **quero** clicar num módulo e ver **5 cards dimensões** (D1-D5) com breakdown sub-itens + lista top gaps ordenada **para** entender ONDE está o gap.
- **DoD extra:** botão **"Evoluir"** primário abre drawer com batch tasks-create sugeridas (MVP A: copy-as-markdown; Fase B: integração MCP direta `tasks-create`).
- **Status:** done (PR #948 mergeado 2026-05-16).

#### US-GOV-008 · CLI `php artisan module:grade` (machine-readable JSON) ✅ DONE
- **Command:** `Modules/Governance/Console/Commands/ModuleGradeCommand.php`
- **Service:** `Modules/Governance/Services/ModuleGradeService.php` — método `gradeModule(string $name): ModuleGrade` retorna value object com nota total + breakdown 5 dimensões + lista gaps.
- **Como** Claude Code (Tier B skill `avaliar-modulo`) **quero** rodar `php artisan module:grade <name> --detail --json` **para** parsear output e formatar em chat sem screen-scrape.
- **DoD extra:** flag `--all` agrega todos módulos; `--json` saída machine-readable; `--evolve` gera batch tasks markdown.
- **Status:** done (PR #948 mergeado 2026-05-16).

### Área Tracking 90d (backlog)

#### US-GOV-009 · Cron daily snapshot histórico 90d ❌ BACKLOG
- **Schedule:** `app/Console/Kernel.php` daily 06:00 BRT (alinhado com `jana:health-check`)
- **Comando:** `php artisan module:grade --all --snapshot`
- **Tabela:** `mcp_module_grades_history (module, score, dim1..dim5, snapshot_at)` (cross-tenant)
- **Como** Wagner **quero** ver evolução das notas dos módulos nos últimos 90 dias **para** detectar regressão ou validar Waves de melhoria.
- **DoD extra:** gráfico sparkline na tabela Index + linha temporal no Show.
- **Status:** backlog — Fase B do ADR 0153.

### Área Integração ADS (backlog)

#### US-GOV-010 · Integração ADS Brain B disparar agents auto ❌ BACKLOG
- **Como** Wagner **quero** que botão Evoluir (US-GOV-007) **opcionalmente** dispare agent Brain B Sonnet/Opus que aplica fix pra D1.a BusinessScope ausente (gap mais comum) **para** ganhar velocidade sem perder governança.
- **DoD extra:** gate via ActionGate strict + risk score MED+ no `decide(domain:governance, intent:auto-fix, payload:{module, gap})` (ADR 0086 ADS).
- **Status:** backlog — depende ADS Universal S5 (~jul/2026).

## Status agregado por US

| US | Status | Entregue em |
|---|---|---|
| US-GOV-001 Dashboard consolidado | ✅ done | ADR 0086 (2026-05-05) |
| US-GOV-002 Policies CRUD | 🟡 parcial (index + toggle) | ADR 0086 MVP |
| US-GOV-003 Audit drill-down | 🟡 parcial (listagem básica) | ADR 0086 MVP |
| US-GOV-004 Drift alerts | 🟡 parcial (manual hoje) | ADR 0086 MVP |
| US-GOV-005 ActionGate middleware | ✅ done modo warn | ADR 0086 (2026-05-05) |
| US-GOV-006 ModuleGrade Index | ✅ done | PR #948 (2026-05-16) |
| US-GOV-007 ModuleGrade Show + Evoluir | ✅ done | PR #948 (2026-05-16) |
| US-GOV-008 CLI module:grade JSON | ✅ done | PR #948 (2026-05-16) |
| US-GOV-009 Cron 90d snapshot | ❌ backlog | Fase B ADR 0153 |
| US-GOV-010 ADS auto-fix | ❌ backlog | depende ADS Universal S5 |

## Por que Governance é cross-tenant intencional (vs ADR 0093)

[ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md) declara **`business_id` global scope obrigatório** como Tier 0 IRREVOGÁVEL em todas Eloquent Models que tocam dados de negócio. Governance **não viola** essa regra — opera em **plano L1 MCP CORE** (Constituição v2 Art. 6 — Camada 1) e **L7 Audit/Charter** (Art. 8 — enforcement), ambos transversais por design.

Justificativa formal:

1. **Constituição v2 Art. 6 (Princípio Duro #6 — Multi-tenant)** se refere a **dados de negócio** (transações, contatos, produtos). ADRs, policies, audit log de tools MCP, identity mesh **não são dados de negócio** — são metadados da plataforma.
2. **Constituição v2 Art. 8 (Princípio Duro #8 — Enforcement)** exige gate runtime que conhece **atores** e **regras** independente de tenant. Sem cross-tenant, ActionGate não consegue validar Claude Code (actor único global) contra rule `kernel.adr.append_only` (regra única global).
3. **Cascade Review §10.4** ([ADR 0147](../../decisions/0147-cascade-review-defesa-drift-time-mcp.md)) exige rastreabilidade cross-tenant de mudanças canônicas pra detectar drift entre L5 ADRs e L6 Module Charters.
4. **Defesa contra vazamento:** rotas `/governance/*` exigem permission `governance.*` (default só Wagner). Tenants clients **nunca acessam**. Audit log já é redacted por `PiiRedactor` antes de salvar (ADR 0085).

**Auditável:** qualquer Pest test cross-tenant biz=1 vs biz=99 ([ADR 0101](../../decisions/0101-tests-business-id-1-nunca-cliente.md)) executado em Modules/Governance valida que **dados de negócio agregados** (KPI counts, drift por módulo) não vazam linha individual.

## Permissões Spatie

| Permission | Default actor | Função |
|---|---|---|
| `governance.dashboard.view` | Wagner + time interno | Acesso `/governance` raiz |
| `governance.policies.view` | Wagner | Listagem `/governance/policies` |
| `governance.policies.edit` | Wagner | Toggle + (futuro) CRUD inline |
| `governance.audit.view` | Wagner + auditor externo | `/governance/audit` |
| `governance.drift.view` | Wagner | `/governance/drift` |
| `governance.module-grades.view` | Wagner + time interno | `/governance/module-grades` |
| `governance.module-grades.evolve` | Wagner | Botão Evoluir → batch tasks-create |

Roles Spatie criadas com suffix `#{biz}` quando `roles.business_id` NOT NULL (UltimatePOS). Permissions globais (sem business_id) ficam em registry separado — ver [ADR 0065](../../decisions/0065-permission-registry-contract.md).

## Dependências canônicas

- [ADR 0086](../../decisions/0086-fase-5-mvp-governance-actiongate-warn.md) — Fase 5 MVP scaffold + ActionGate warn (mãe do módulo)
- [ADR 0094](../../decisions/0094-constituicao-v2-7-camadas-8-principios.md) — Constituição v2 (mãe das 7 camadas + 8 princípios)
- [ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md) — Multi-tenant Tier 0 (exceção formal explicada acima)
- [ADR 0101](../../decisions/0101-tests-business-id-1-nunca-cliente.md) — Tests biz=1 nunca cliente
- [ADR 0147](../../decisions/0147-cascade-review-defesa-drift-time-mcp.md) — Cascade Review §10.4
- [ADR 0153](../../decisions/0153-module-grade-rubrica-v1.md) — Rubrica `module-grade-v1` (US-GOV-006..008)
- [ADR 0081](../../decisions/0081-identity-mesh-mcp-actors.md) — Identity Mesh `mcp_actors` (ActorResolver consumido por ActionGate)
- [ADR 0084](../../decisions/0084-triggers-mysql-imutabilidade-mcp-audit-log.md) — Trigger imutabilidade `mcp_audit_log`
- [RUNBOOK Module Grades](RUNBOOK-module-grades.md) — receita operacional da tela

## Onda Audit Sênior 2026-05-25

> Origem: [`memory/requisitos/Compras/AUDIT-SENIOR-2026-05-25.md`](../Compras/AUDIT-SENIOR-2026-05-25.md) + [`memory/requisitos/Jana/AUDIT-SENIOR-2026-05-25.md`](../Jana/AUDIT-SENIOR-2026-05-25.md). 2 achados transversais que afetam ranking module-grade do projeto inteiro.
> Bypass MCP `tasks-create` (mcp_jira_projects ainda não tem entry "Governance") — webhook sincroniza no próximo push.

### US-GOV-011 · [ROI alto] Carregar extension OTel no Herd dev (+2-3pp em 36 módulos D9)

> owner: — · priority: p0 · estimate: 1h · status: todo · type: story
> blocked_by: —

**Sintoma:** `opentelemetry-auto-laravel` instalado via composer mas extension **não carrega no Herd dev** — warning explícito na 1ª linha de `module:grade`:
> Warning: The opentelemetry extension must be loaded in order to autoload the OpenTelemetry Laravel auto-instrumentation in vendor/open-telemetry/opentelemetry-auto-laravel/_register.php on line 13

**Impacto:** D9.b Observability afetada em **TODOS os 36 módulos** do projeto. Eleva média projeto +2-3pp com 0.5h IA-pair.

**Acceptance:**
- [ ] Adicionar `extension=opentelemetry` no php.ini do Herd Windows
- [ ] Verificar `php -m | grep opentelemetry` retorna OK
- [ ] Re-rodar `php artisan module:grade --all` — sem warning
- [ ] Documentar no `memory/reference/herd-setup.md`

**ROI:** MAIOR do projeto inteiro (0.5h IA-pair → +2-3pp média 36 módulos)
**Refs:** Compras/AUDIT-SENIOR-2026-05-25.md (Surpresa Estratégica)

### US-GOV-012 · Investigar ScopedScorecardEvaluator não captura SATURATION markers Jana (gap 25pp grade real)

> owner: — · priority: p1 · estimate: 4h · status: todo · type: story
> blocked_by: —

**Sintoma:** `module:grade Jana` hoje devolve **71/100** (D1 MT 15/30, D7 LGPD 6/10). Realidade canon via BRIEFING Wave 25 + Pest enforcement = **96/100** (D1 SATURATED, D7 SATURATED Wave 18).

**Evidência:** 607 linhas Pest multi-tenant + LgpdComplianceTest 179 linhas + 14 Models com HasBusinessScope + 12+ com BelongsToBusinessViaParent + 8 SATURATION markers explícitos. Mas grade engine NÃO reconhece.

**Implicação:** rubrica module-grade-v3 (ADR 0155) tem bug nos scorers que afeta confiança no batch markdown gerado pra Wagner aprovar/rejeitar batch. Pode estar subestimando outros módulos também.

**Acceptance:**
- [ ] Debugar `ScopedScorecardEvaluator` (Modules/Governance)
- [ ] Identificar por que SATURATION markers Wave 25 não contam
- [ ] Verificar se afeta Crm 88, Financeiro 82, Governance 89 (outros high-scoring)
- [ ] Fix scorer + re-baseline `governance/module-grades-baseline.json`
- [ ] Pest cobre: SATURATION marker reconhecido em ≥3 Models

**Refs:** Jana/AUDIT-SENIOR-2026-05-25.md (Reconciliação §1.1), ADR 0155 (module-grade-v3)
