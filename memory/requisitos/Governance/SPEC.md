---
id: requisitos-governance-spec
lifecycle: active
owner: [W]
module: Governance
project: COPI
status: ativo
authority: canonical
version: "1.0"
last_updated: "2026-07-02"
created_at: 2026-05-16
updated_at: 2026-06-21
related_adrs:
  - "0086-fase-5-mvp-governance-actiongate-warn"
  - "0094-constituicao-v2-7-camadas-8-principios"
  - "0101-sistema-charter-capterra-governanca-escopo"
  - "0147-cascade-review-defesa-drift-time-mcp"
  - "0153-module-grade-rubrica-v1"
  - "0154-module-grade-v2-na-justificado"
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

### US-GOV-013 · Tornar o gate visual ADR 0108 (visual-regression) REAL — sair do stub

> owner: — · priority: p2 · estimate: 8h · status: todo · type: story
> blocked_by: —

**Contexto (descoberto 2026-06-04, sessão re-skin RecurringBilling #2212):** o gate de pixel `visual-regression.yml` (ADR 0108, Pest 4 Browser) está em **STUB / infra-only mode** — `continue-on-error: true` em Setup Laravel + Run Pest Browser, travado pela **migration-order legacy do UltimatePOS** (ex: `ALTER TABLE contacts ADD regime AFTER contribuinte` falha porque `contribuinte` é adicionado por migration posterior). Consequência: **0 testes browser fora de Sells, 0 baselines `.png`, artifact `screenshots-diff` sobe vazio.** O comentário "Visual Regression Detected" é sinal estrutural (step `failure()`), NÃO diff de pixel real.

**Correção de premissa (importante):** "os gates pegam regressão" vale pro lado **semântico/lint** (ESLint, Stylelint, Module-Grades, **UI-Judge LLM** — reais, rodam verdes), mas **NÃO pro pixel**. Hoje a rede real de mudança visual = UI-Judge + olho/staging.

**Por que importa (keystone do Pilar 6 lado visual — "a máquina cobra, não o Wagner"):**
- Desbloqueia o `foundations.css` (font IBM Plex global, blast radius = toda tela Inertia) com segurança — hoje deferido por falta de rede automática.
- Torna a aprovação das ~44 telas **automatizável** (vs Wagner olhando tela a tela).
- Fecha o Pilar 6 de verdade (semântico já ligado via UI-Judge; falta o pixel).

**Acceptance:**
- [ ] Resolver migration-order legacy em PR dedicado (ou seedar DB de teste por snapshot/sqldump em vez de `migrate` sequencial) → remover `continue-on-error` do Setup Laravel + Run Pest Browser.
- [ ] Pest Browser renderiza telas reais + captura screenshots; baseline `.png` versionado (LFS) por tela-chave.
- [ ] Artifact `screenshots-diff` (actual vs expected) sobe baixável em diff real.
- [ ] Controle-negativo: bug visual injetado → CI vermelho (prova que o gate vê).
- [ ] Cobertura inicial: telas DS-canon (Sells/Index, Financeiro/Unificado, RecurringBilling/Index) + as do batch 44.

**NÃO é bloqueador de agora** — Wagner não está exposto no meio-tempo (UI-Judge cobre o net semântico). ~6-8h, pode virar toca-de-coelho (migration-order). Priorizar quando quiser investir.

**Refs:** ADR 0108 (regressão visual Pest Browser Tier 2) · `.github/workflows/visual-regression.yml` (linhas 100-164, notas INFRA-ONLY) · UI-0013 (Constituição UI v2) · sessão 2026-06-04 (PRs #2209/#2210/#2212/#2216).

### US-GOV-015 · Zelador diário — piloto 14d (reconciliação + triagem por âncora + subtração de ruído)

> owner: claude · priority: p1 · estimate: 8h · status: todo · type: story
> blocked_by: —

Task-âncora do **Zelador** — reconciliador-agente diário (scheduled run 07:00 BRT na máquina do Wagner). Charter canônico: `scripts/governance/ZELADOR.md`. Origem: sessão 2026-06-11, Wagner "estou sofrendo com sistema burro" → "ótimo faça".

**O que ele faz toda manhã:** (1) reconcilia estado declarado (my-work doing/review, HITL, next_steps dos 3 handoffs recentes) vs realidade (gh/git/MCP) — fecha com prova, rebaixa o apodrecido; (2) decide pelo trilho invariante→sinal→meta; só resíduo genuíno escala pro Wagner como draft de 1 OK (máx 3/dia); (3) propõe demote de 1 fonte de ruído/dia (bot/check que não mudou decisão em 30d); (4) roda knowledge-drift.mjs como insumo.

**Relatório diário = comentário NESTA task** (formato fixo no charter, ≤15 linhas). Zero doc novo.

**Métricas do piloto (kill-switch dia 14 — 2026-06-26):**
- M1 itens/dia que chegam ao Wagner → tem que CAIR
- M2 idade média de `doing` → de ~520h (baseline brief #203 2026-06-11) pra <48h

Se M1 e M2 não caírem, o zelador recomenda a própria morte e Wagner decide.

**Cláusula de evolução (loop duplo):** todo domingo o run é META — o zelador aplica o próprio trilho a si mesmo (fechamentos que reabriram, reversões humanas, drafts ignorados) e propõe exatamente 1 emenda/semana ao charter via PR, com viés de subtração; emenda que não melhorar M1/M2 é revertida na META seguinte. Núcleo imutável (NÃO PODE + trilho + kill-switch + a própria cláusula) só muda por decisão explícita do Wagner.

**Poderes/limites:** herda matriz publication-policy (ADR 0040) — pode tasks-update/comment/branch própria/abrir PR; NUNCA mergeia main, não toca prod, não cria ADR/doc em memory/, não cria tasks novas.

### US-GOV-016 · Reestruturação SDD — Semana 0 (12 frentes paralelas)

> owner: wagner · priority: p1 · estimate: 16h · status: todo · type: story
> blocked_by: —

GO Wagner 2026-06-12. Executar o lote 1 do plano `memory/sessions/2026-06-12-plano-reestruturacao-sdd-ondas-paralelas.md` (PR #2586) via workflow `sdd-semana-0`:

- 3 ADRs draft: formato anchor spec↔código · referência ADR por slug (13 colisões) · scorecard SDD 10 métricas + calendário de promoções
- 9 implementações: fix artefato JUnit · composite action pest-mysql · catracas quarentena · hook red-first (WARN) · catraca anti-ghost · codemod ghost-fix (dry-run) · agregador sdd-scorecard.mjs · protocolo refutador de backfill · backfill frontmatter charters · tabela triagem identidade pastas órfãs

**DoD:** 12 PRs draft abertos + auditor adversarial aprova partição/evidência + fila consolidada pro Wagner (decisões: tabela de renames, tabela de identidade, aceite dos 3 ADRs).
Emenda CT 100: nightly full-suite via cron no CT 100 (16 vCPU/32 GB ociosos) — afeta fase 1-2, não este lote.

### US-GOV-017 · Reestruturação SDD — Fase 1+2 (medição real, backfill, burn-down)

> owner: wagner · priority: p1 · estimate: 40h · status: todo · type: story
> blocked_by: —

GO Wagner 2026-06-12 ("pode disparar fase 1 e a dois na sequência"). Continuação da US-GOV-016 (Semana 0, done).

**Fase 1 (workflow sdd-fase-1, em execução):** anchor-lint + workflow advisory (gramática ADR 0273) · codemod --write dos 4 renames aprovados · infra nightly full-suite MySQL no CT 100 (cron + 1º run) · meta-catraca scorecard · gate-selftest com fixtures · fix peso_real flag-OFF (decay ADR 0270 D-4) · comando jana:recall-eval + golden set · RAGAS modo real destravado (aguarda secret).

**Fase 2 (dispara automaticamente na sequência):** triage Q2 do 1º run CT 100 → quarentena em massa Q3 → backfill mecânico de anchors (SA-A4) → burn-down por módulo (B1 Financeiro, B2 NfeBrasil, B4 tests/ raiz; B3 mini-onda) → batch IA de anchors com refutador (SA-A5) → CT 100: re-seed + flag decay + recall-eval cron (C3-C5) → G4/G7/G8.

**Gated em Wagner:** tabela _TRIAGEM-IDENTIDADE (trilha E) · secret OPENAI_API_KEY (RAGAS real) · skim das queries do golden set.

---

### US-GOV-018 · P0 Fase 2b: consertar harness de DB de teste do nightly (3 frentes) — não é "completar schema"

> owner: — · priority: p0 · estimate: 12h · status: done · type: story
> blocked_by: —

**Implementado em:** `scripts/tests/ct100-fullsuite.sh` · `Modules/PaymentGateway/Database/Migrations/2026_06_13_080000_alter_payment_gateway_credentials_config_json_to_longtext.php` · verificado@2026-07-01 — Frente A.1 (mariadb-client + TLS-verify-off, #2640) e Frente B (config_json json→longtext, #2636) verificadas live pelo skeptic Fase 2b da avaliação adversarial; A.2 FK-off REVERTIDO por prova empírica (net-harmful). MCP done desde 2026-06-13 — este campo corrige o status stale do SPEC (`review`) que enganou 2 avaliações seguidas ("US presas em review"); status durável vive no MCP (ADR 0144).

**Aceite:** run limpo do nightly recarrega o dump sem `mysql: not found` nem `ERROR 2026` (greps=0 no junit real de 20260701, provado); zero SQLSTATE 3140 em payment_gateway_credentials; docker run do pest NUNCA seta `FULLSUITE_FK_OFF` (A.2 revertido). Contrato travado no CI barato pelo spec abaixo.

**Testado em:** `tests/fullsuiteHarness.spec.ts` (contract test — 8 asserts derivados deste DoD, `@covers-us`; lane advisory no governance-gate-umbrella)

**Origem:** retest adversarial POR REPRODUÇÃO (2026-06-13, CT 100, DB scratch byte-a-byte) sobre o nightly full-suite MySQL (run `20260613-003042`, sha d14f5436). 3 skeptics reproduziram e refutaram 2 diagnoses anteriores. Substitui a estratégia "quarentena em massa" (revertida) E o P0 "completar schema" (refutado). C1 (#2632, mergeado) flipou a suite pra MySQL e expôs a causa real.

## Número honesto (medido, não estimado)
- **Floor determinístico = 1514** (interseção test-a-test dos 2 runs MySQL code-equivalentes). NÃO é 1636 nem 2075 — esses são pontos ruidosos de runs únicos.
- **Banda de não-determinismo = 683** (561 só-num-run + 122 no-outro). Faixa real **1514–2197**. O eixo que oscila é ERROR (−420 entre runs), não FAILURE: ruído **infraestrutural** (estratégia de DB), confirmando a causa.

## Causa-raiz REAL (reproduzida — NÃO "schema incompleto")
O dump `database/schema/mysql-schema.sql` tem **364 CREATE TABLE** incl. `system`/`permissions`/`business`/`activity_log`/`users` — TODAS presentes. Baseline completo (dump+migrate+seed) ainda deixou ~56% da amostra vermelho. "Completar schema" conserta ZERO.

## 3 FRENTES (com impacto medido)
**Frente A — harness/imagem [~688 `Base table not found` (variável 529–688) + 254 testes 3730 + 72 `mysql: not found`; tudo eixo ERROR, banda 1514–2197, NÃO o floor de 1514]:** a imagem `oimpresso/mcp` **não tem o binário `mysql`/`mariadb`** → o `migrate:fresh`/`schema:load` do RefreshDatabase não reaplica o dump → tabelas core (`business`/`activity_log`/`permissions`) **somem mid-run**. ⚠️ **Refutação adversarial (ADR 0276): só instalar o client NÃO basta** — o `mysql … < dump` que o Laravel emite falha em TLS cert verify (`ERROR 2026`), pois o mariadb-client verifica TLS por default e o repo não seta `MYSQL_ATTR_SSL_VERIFY_SERVER_CERT`. Soma: teardown sem FK-off → "Cannot drop … referenced by FK" (**254 testes**; "508" eram menções raw 2×/testcase no junit). A 3ª sub-causa do brief (migrate PULA migrations PSR-4) foi **REFUTADA** — as skipadas são 100% pré-dump/registradas no INSERT, 0 nomeadas pós-cutoff → **no-op**. **DoD A (implementado em #2640):** `mariadb-client` na imagem (+ apk no harness como fallback) **+ `ssl-verify-server-cert=0`** no container do pest; ~~teardown com FK-off escopado ao nightly (`FULLSUITE_FK_OFF=1`)~~ (REVERTIDO — net-harmful, ver US-GOV-020 A.2; bloco removido do `Tests\TestCase` no ledger §E); ~~consertar PSR-4~~ (refutado, não fazer).

**Frente B — código [212 falhas]:** `payment_gateway_credentials.config_json` declarado `json` (strict no MySQL 8) mas o Model casta `encrypted:array` (blob AES base64). SQLite TEXT aceitava; MySQL rejeita com SQLSTATE 3140 "Invalid JSON text". Reproduzido byte-a-byte; counterfactual `ALTER ... LONGTEXT/TEXT` aceita. **DoD B:** ALTER `config_json` pra TEXT alinhado ao cast `encrypted:array` (migration idempotente + down()).

**Frente C — testes era-sqlite [parte do floor]:** 231-476 arquivos montam `Schema::create` próprio e rodam contra MySQL persistente sem rollback → UniqueConstraint 1062, unknown-column 1054. **DoD C:** trait de reset uniforme (DatabaseTransactions/RefreshDatabase consistente) OU isolamento por-arquivo — pode virar sub-onda mecânica.

## Validação
Re-rodar o nightly full após A+B+C e medir o novo floor (interseção de ≥2 runs com seed fixo). Meta: floor cai de 1514 pra a casa das centenas. **Atenção:** o 1514 é o baseline do estado **QUEBRADO** (nenhum dos runs medidos exercitou os fixes) — a redução é predição até um run validado. Frente A landed em **#2640** (par adversarial ADR 0276 corrigiu A.1 pra incluir TLS-off); Frente B em **#2636**; Frente C segue sub-onda.

## FORA do escopo (backlog separado, não bloqueia)
~385 ExpectationFailed (assertions reais) + ~105 app-bugs (ex `RetentionCleanupCommand.php:194 Undefined variable $businessId`) — dívida de teste/código genuína que NENHUM fix de harness toca.

Ref: retest reproduzido na timeline US-GOV-017 (correção #2) · #2632 (C1) · triage `memory/sessions/2026-06-13-sdd-f2b-triage-q2.md`.

### US-GOV-019 · Re-triage eixo-FAILURE: 7 bugs (design) + 91 quarentena + 11 unclear

> owner: — · priority: p1 · estimate: 16h · status: todo · type: story
> blocked_by: —

Saída da re-triage 32-thread do eixo FAILURE determinístico (155 arquivos, 385 ExpectationFailed) com refutador adversarial ADR 0276. Doc: `memory/sessions/2026-06-13-sdd-retriage-eixo-failure-32threads.md`. **4 quick-wins já em PR** (ads:health #2649, superadmin:health #2647, macro_variant_id #2646, biz=4→1 fixtures #2652) — fora desta task.

## 7 bugs confirmados que precisam de design (sobreviveram ao refutador)

> ✅ **TODOS os 7 resolvidos** — verificação item a item em origin/main 2026-07-02 (sessão mystifying-fermat, pedido Wagner "6-8 tbm"). Evidência em cada checkbox. O que RESTA desta US: 91 quarentena + 11 unclear (seções seguintes).

- [x] **ChannelUserAccess** (Tier 0): ~~UNIQUE em coluna nullable~~ → RESOLVIDO pela migration `2026_06_13_120000_enforce_single_active_channel_user_access.php` (coluna gerada + UNIQUE; a original :57 documenta o histórico e é dropada). Teste: `ChannelUserAccessTest` R-WA-068-005.
- [x] **CSAT**: ~~não dispara DispatchCsatJob~~ → RESOLVIDO — `Admin/InboxController.php:1084` despacha `DispatchCsatJob` em open→resolved (CsatDispatcher idempotente, :1078). Teste: `CsatFlowTest`.
- [x] **Vestuario DataController** (ADR 0024): → RESOLVIDO — `Modules/Vestuario/Http/Controllers/DataController.php` existe.
- [x] **WithoutGlobalScopes** (Tier 0): → RESOLVIDO — chamadas em `TituloAutoService.php` (:108/:138/:200) e `NfeService.php` (:746/:762) todas com `// SUPERADMIN:` + razão; KbCorpusBuilder sem ocorrência. Guard virou REQUIRED 2026-06-30 (`Tier-0 guards`, #3438 zerou as violações).
- [x] **NFSe cancelar()**: → MOOT — `NfseEmissaoService.php` não existe mais (cancel refatorado pra `NfseCancelService.php`) e `Wave28NfsePolishTest` também não; o "confirmar se Wave 28 exige" resolveu-se por remoção do alvo.
- [x] **DESIGN.md**: → RESOLVIDO — scan 2026-07-02: 0 links locais quebrados. O teste `DesignEntryPointAndTombstonesTest` segue no cluster quarentena (Q-B, assert de canon-source contra fonte móvel) — sair da quarentena é burn-down, não bug.
- [x] **PhpunitTestAnnotationGuard**: → RESOLVIDO — grep 2026-07-02: nenhum `/** @test */` fora do próprio guard.

## 91 quarentena (teste stale, produto OK)
`@group legacy-quarantine` com razão. tests/Feature 46 · Financeiro 14 · Whatsapp 11 · Governance 3 · Jana 3 · PaymentGateway 3 · Officeimpresso 2 · tests/Unit 2 · Vestuario 2 · Cms/Connector/ConsultaOs/OficinaAuto/Ponto 1. **Nuance:** alguns são test-FIX rápido (não quarentena cega) — ver doc.

## 33 env-coupled → reconfirmar no floor do run limpo `20260613-100035`.

## 11 unclear (decisão Wagner) — perguntas no doc.

Ref: re-triage workflow wnw19l15c · 52 agents · refutador matou 9 falsos-positivos · ADR 0276.

### US-GOV-020 · Frente C: migrate:fresh do nightly carrega dump incompleto (trigger DEFINER prod / privilégio)

> owner: — · priority: p0 · estimate: 6h · status: done · type: story
> blocked_by: —

**Implementado em:** `scripts/tests/ct100-fullsuite.sh` · verificado@2026-07-01 — grants Frente C (log_bin_trust_function_creators + SET_USER_ID) re-landados no #2728 (squash 47e96ed05, 2026-06-14, 38/38 checks) + deploy gap fechado na mesma data; 188→377 tabelas / 0→4 triggers provado no CT100. MCP done desde 2026-06-14 — campo corrige status stale do SPEC (`review`); status durável no MCP (ADR 0144).

**Aceite:** `migrate:fresh` de clone limpo carrega o dump completo (377 tabelas, 4 triggers — provado isolado no CT100 2026-06-14); sem `ERROR 1419`/`ERROR 1227` no load. Grants presentes no passo 3 (root) do harness, travados pelo spec abaixo.

**Testado em:** `tests/fullsuiteHarness.spec.ts` (contract test — grants Frente C, `@covers-us`; lane advisory no governance-gate-umbrella)

**Root cause PROVADO** (repro byte-level CT100, run `20260613-100035`). O `migrate:fresh` do RefreshDatabase carrega `database/schema/mysql-schema.sql`, cujos triggers têm **DEFINER de PROD** (`u906587222_oimpresso@localhost`, ex `trg_mcp_audit_log_no_update`). Setup carrega via root (OK); migrate:fresh carrega via `fullsuite` (não-SUPER) → `ERROR 1419` (binlog) / `ERROR 1227` (SET_USER_ID/DEFINER) → aborta → schema incompleto → **530 Base-table-not-found**. MySQL 8.0.46 binlog on.

## Fix (188→377 tabelas, 0→4 triggers)
`ct100-fullsuite.sh` passo 3 (root): `SET GLOBAL log_bin_trust_function_creators=1` + `GRANT SET_USER_ID ON *.* TO <fullsuite>`. Provado isolado no CT100.

## Por que re-landar (decisão Wagner 2026-06-14)
O #2657 (grants + revert A.2) foi **fechado sem merge** quando se concluiu que Frente C "não é o lever" — o floor **não caiu** (1870→1928, run `20260613-115507`). **Mas o grant segue necessário**: sem ele o floor **não é reproduzível de clone limpo** (triggers DEFINER de prod + binlog ON abortam o `migrate:fresh`). Re-landado **nesta PR** sobre `origin/main` atual (cherry-pick de `98259e50f` + `7371db9ea`).

## A.2 (FULLSUITE_FK_OFF) — REVERTIDO (resolvido)
Reavaliação concluída: A.2 é **net-harmful** (run `20260613-115507`, floor 1928). O FK-off deixava ~30 testes era-sqlite **dropar tabela CORE compartilhada com sucesso** → cascata `Base table not found`. **DECISÃO: não ligar FK-off** — deixar o drop falhar-seguro (3730 só no teste ofensor; a tabela CORE sobrevive pro resto da suíte). Esta PR remove o `-e FULLSUITE_FK_OFF=1` do passo 6. (O bloco gated em `getenv` no `Tests\TestCase::setUp` — antes deixado inerte — foi REMOVIDO como dead-code no ledger §E, já que a flag nunca mais é setada.)

## Lever real do floor
**Não é harness** — é o isolamento dos ~19-30 testes "era-sqlite" que dropam tabela CORE numa base MySQL persistente compartilhada. Tratado em **US-GOV-021** (front-2). Frente C só torna o nightly **reproduzível**; não baixa o floor sozinha.

Ref: floor `20260613-100035` (1870) / `20260613-115507` (1928) · doc `memory/sessions/2026-06-13-sdd-retriage-eixo-failure-32threads.md` · #2657 (closed) · #2640 (A.1/A.2 origem) · US-GOV-021.

### US-GOV-021 · Isolar os corruptores era-sqlite (o lever REAL do floor)

> owner: [W] · priority: p0 · status: done · type: story
> blocked_by: — (DoD-2 "floor cai em 2 nightlies" deixou de ser bloqueio estrutural com o P14 — `governance/nightly-floor.json` é materializado da órfã no ratchet required; a medição do efeito fica com as nightlies pós-P04. MCP done desde 2026-06-21; lotes 2-3 em 2026-06-30 #3445; corruptors=0 ARMADO no GT-G3)
> parent_plan: us-gov-021-isolamento-era-sqlite
> related_adrs: [275, 276, 279, 283]

**Implementado em:** `scripts/audit/sqlite-test-corruptors.mjs` · `.github/workflows/governance-gate-umbrella.yml` · `Modules/Forja/Tests/Feature/CoworkHandoffCrossTenantTest.php` · `Modules/Jana/Tests/Feature/TaskRegistry/TaskUpdateAtomicTest.php` · verificado@2026-06-30 — **0 corruptores** (auditor `--strict --tier=A` exit 0; 11 dos lotes 2-3 + 1 novo PaymentGateway isolados via GUARDAR-TEARDOWN). DoD-2 (floor cai em 2 nightlies) blocked_by P04.

**Root cause PROVADO** (referenciado em US-GOV-020 "Lever real do floor" `:408-409`): o nightly full-suite roda contra um MySQL **persistente compartilhado**. ~18 testes "era-sqlite" criam tabelas sintéticas via `Schema::create`/`Schema::drop` em `beforeEach`/`afterEach` SEM guarda de driver — projetados pra rodar no sqlite `:memory:`. No MySQL persistente o `Schema::drop` **dropa a tabela real** → o próximo teste na mesma conexão acha tabela ausente → cascata `Base table not found`. Esse isolamento é o **lever real** do floor — **não é tweak de harness** (a Frente C/A.2 de US-GOV-020 já provou que FK-off é net-harmful; falhar-seguro é melhor).

**Fonte da verdade = comportamento, não literal-grep.** A lista canônica vem do auditor `scripts/audit/sqlite-test-corruptors.mjs --json` (classifica por `corruptsOnMysql`, não text-match — a v1 tinha ~48% FP, refutado em ADR 0276). **NÃO usar `git grep "Schema::drop"`** (super-conta: mistura guardados com corruptores).

#### Os 19 corruptores (auditor `--json`, tier A · ANTES dos fixes — 18 originais + 1 novo PaymentGateway detectado 2026-06-30)

| Arquivo | tier | ação | status |
|---|---|---|---|
| `Modules/Forja/Tests/Feature/HandoffToolsTest.php` | A 75 | GUARDAR-TEARDOWN | ✅ isolado |
| `Modules/Forja/Tests/Feature/HandoffIngestTest.php` | A 75 | GUARDAR-TEARDOWN | ✅ isolado |
| `Modules/Forja/Tests/Feature/HandoffLeverToolTest.php` | A 75 | GUARDAR-TEARDOWN | ✅ isolado |
| `Modules/Forja/Tests/Feature/HandoffStaleAlertTest.php` | A 75 | GUARDAR-TEARDOWN | ✅ isolado |
| `Modules/Forja/Tests/Feature/HandoffSubmitToolTest.php` | A 75 | GUARDAR-TEARDOWN | ✅ isolado |
| `Modules/TeamMcp/Tests/Feature/IngestHeartbeatTest.php` | A 75 | GUARDAR-TEARDOWN | ✅ isolado |
| `Modules/TeamMcp/Tests/Feature/IngestLivenessTest.php` | A 60 | GUARDAR-TEARDOWN | ✅ isolado |
| `Modules/Forja/Tests/Feature/CoworkHandoffCrossTenantTest.php` | A 75 | GUARDAR-TEARDOWN | ✅ isolado |
| `Modules/TeamMcp/Tests/Feature/ForjaBacklogServiceTest.php` | A 75 | GUARDAR-TEARDOWN | ✅ isolado |
| `Modules/TeamMcp/Tests/Feature/ForjaChangelogServiceTest.php` | A 75 | GUARDAR-TEARDOWN | ✅ isolado |
| `Modules/TeamMcp/Tests/Feature/ForjaMcpServiceTest.php` | A 75 | GUARDAR-TEARDOWN | ✅ isolado |
| `Modules/TeamMcp/Tests/Feature/ForjaQuadroServiceTest.php` | A 75 | GUARDAR-TEARDOWN | ✅ isolado |
| `Modules/Jana/Tests/Feature/TaskRegistry/ClaimlessMutationWarningTest.php` | A 75 | GUARDAR-TEARDOWN | ✅ isolado |
| `Modules/Jana/Tests/Feature/TaskRegistry/FsmTransitionGuardTest.php` | A 75 | GUARDAR-TEARDOWN | ✅ isolado |
| `Modules/Jana/Tests/Feature/TaskRegistry/TaskUpdateAtomicTest.php` | A 75 | GUARDAR-TEARDOWN | ✅ isolado |
| `Modules/Jana/Tests/Feature/TaskRegistry/AcceptanceRefTest.php` | A 60 | GUARDAR-TEARDOWN | ✅ isolado |
| `Modules/Jana/Tests/Feature/Mcp/WorkLeaseServiceTest.php` | A 60 | GUARDAR-TEARDOWN | ✅ isolado |
| `Modules/Brief/Tests/Feature/LeaseBriefSectionServiceTest.php` | A 60 | GUARDAR-TEARDOWN | ✅ isolado |
| `Modules/PaymentGateway/Tests/Feature/RetryOrphanWebhookCommandTest.php` | A 60 | WRAP-SQLITE-IF | ✅ isolado (novo · 2026-06-30) |

Ação canônica **GUARDAR-TEARDOWN** (preferida nos era-sqlite sintéticos — preserva cobertura no sqlite, neutro no MySQL): `if (config('database.default') !== 'sqlite') { $this->markTestSkipped(...) }` no topo do `beforeEach` + `if (config('database.default') !== 'sqlite') { return; }` no topo do `afterEach`. O auditor reconhece como `corruptsOnMysql=false, quarantined=true` (contrato travado em `tests/sqliteCorruptors.spec.ts:89-108`).

#### DoD (4 itens — anti-trapaça embutido)

1. **US escrita** com DoD, owner, anchor `**Implementado em:**`, e a lista canônica dos corruptores (fonte = auditor, NÃO literal-grep). ✅ (esta seção)
2. **Floor cai de VERDADE** — 2 nightlies CT100 consecutivos: `floor_count` do `governance/nightly-floor.json` (ADR 0279) **diminui** vs baseline pré-fix, por **reduzir `errors`** da cascata "Base table not found", **NÃO** por inflar `skipped`. Anti-trapaça: `delta(errors)` ≥ nº de testes downstream que paravam de cascatear; `delta(skipped)` ≤ nº de corruptores legitimamente quarentenados. ⏳ `blocked_by: P04` (sem o floor no tree, não-observável — não fingir que mediu).
3. **Gate liga:** `node scripts/audit/sqlite-test-corruptors.mjs --strict --tier=A` roda em workflow de PR. Hoje **`corruptors=0` → exit 0** (gate verde). `continue-on-error: true` MANTIDO até **2 verdes consecutivos**; remover daí pra promover a **required** (ADR 0275). ✅ advisory verde, pronto pra required.
4. **Contador:** `corruptors: 19 → 0` via `--json` (18 originais + 1 novo PaymentGateway). ✅ **0** (verificado 2026-06-30: `--strict --tier=A`/`--tier=S`/sem-tier todos exit 0; 266 testes guardados/seguros).

#### Counterfactual (prova de que o gate MORDE)

`Schema::drop('business');` solto num corpo de teste sem guarda sqlite → classifier marca `corruptsOnMysql=true`, `highBlast=['business']`, score ≥80 tier S → `--strict` exit 1. Reverter → null (não-corruptor) → exit 0. Verificado in-memory via `classifySource` (US-GOV-021, 2026-06-21). Coberto em espírito por `tests/sqliteCorruptors.spec.ts:21-35` (SENSIBILIDADE).

**Kill-criteria:** se o piloto (7 corruptores isolados) NÃO derrubar `errors` proporcionalmente no `nightly-floor.json` → o lever não é (só) era-sqlite. Parar escala, reabrir root-cause (precedente: o handoff errou previsão 2×; regra dura: MEDIR cada passo, nunca previsão-como-fato).

Ref: plano `memory/requisitos/_Governanca/roadmap/P03-us-gov-021-isolamento-era-sqlite.md` · auditor `scripts/audit/sqlite-test-corruptors.mjs` · contrato `tests/sqliteCorruptors.spec.ts` · handoff `2026-06-13-1730-sdd-floor-frente-c-era-sqlite.md` · US-GOV-020 (lever) · ADR 0276 (refutador) · ADR 0279 (floor).

### US-GOV-028 · Governance sprint 2 cleanup — remover/atualizar 3 blocos legados do pre-commit

> owner: — · priority: p2 · estimate: 2h · status: todo · type: story
> blocked_by: —
> parent_plan: governance-sprint-2-cleanup

**Iniciativa-plano perdida** recuperada pro backlog (triagem 2026-06-20 · run wf_1bfbefba).
labels: `plano-perdido`, `backlog-2026-06-20`

**Sinal (ADR 0105 · métrica em drift):** pre-commit com 3 blocos legados pendentes de limpeza.

**DoD:**
- Remover/atualizar os 3 blocos legados do pre-commit.
- Validar hook end-to-end.

**Fonte:** memory/requisitos/_processo/BATCH-BACKLOG-34-2026-06-20.md (§Aprovação [W] 2026-06-20)

### US-GOV-029 · IA-OS onda 2 — promover anchor-gate de advisory a required

> owner: — · priority: p2 · estimate: 2h · status: todo · type: story
> blocked_by: —
> parent_plan: ia-os-onda2-endurecer

**Iniciativa-plano perdida** recuperada pro backlog (triagem 2026-06-20 · run wf_1bfbefba).
labels: `plano-perdido`, `backlog-2026-06-20`

**Sinal (ADR 0105 · métrica em drift):** anchor-gate ainda advisory; endurecer pra required (onda 2 IA-OS).

**DoD:**
- Promover anchor-gate a required (após baseline limpo).
- Confirmar que não há falsos-positivos pendentes antes de morder.

**Fonte:** memory/requisitos/_processo/BATCH-BACKLOG-34-2026-06-20.md (§Aprovação [W] 2026-06-20)

### US-GOV-030 · Screen-QA dim16 — adicionar workflow sentinela ausente no CI

> owner: — · priority: p2 · estimate: 3h · status: todo · type: story
> blocked_by: —
> parent_plan: screen-qa-dim16-sentinela

**Iniciativa-plano perdida** recuperada pro backlog (triagem 2026-06-20 · run wf_1bfbefba).
labels: `plano-perdido`, `backlog-2026-06-20`

**Sinal (ADR 0105 · métrica em drift):** o workflow sentinela da dimensão 16 do screen-grade está ausente no CI (catraca sem sentinela = pode regredir).

**DoD:**
- Criar o workflow sentinela dim16 no CI.
- Advisory → required conforme cadência.

**Fonte:** memory/requisitos/_processo/BATCH-BACKLOG-34-2026-06-20.md (§Aprovação [W] 2026-06-20)

### US-GOV-031 · MultiTenantScopeChecker em falso-clean (path Windows) + canário anti-falso-clean + promover guards Tier-0 a required

> owner: — · priority: p1 · estimate: 5h · status: todo · type: story · cycle: CYCLE-SAUDE
> blocked_by: —

**Origem:** auditoria de saúde/integridade 2026-06-21 (risco #3, ADR 0218). Distinto de `US-INFRA-032` (hardcodes business_id).

**Achado:** o `MultiTenantScopeChecker` reporta `drift_count=0` por **bug de separador de path Windows-only** (`not_readable=217`). Não cega o CI Linux (`--diff-only` funciona lá), mas o daily `--all` que pegaria o backlog é não-bloqueante → backlog de models sem global scope fica invisível. Em paralelo, os guards Tier-0 (`WithoutGlobalScopes` + `business_id=4`) estavam **falhando no main** com `continue-on-error` (advisory reportando verde), e `business_id=4` (RotaLivre) reapareceu em fixtures.

**Acceptance:**
- Bug de path do checker corrigido (roda igual em Win/Linux).
- Teste-canário anti-falso-clean: asserta `drift>0` contra fixture sem trait.
- 4 violações dos guards corrigidas + `continue-on-error` removido (promover a required).
- Triar models de tenant sem global scope (OficinaAuto/ComunicacaoVisual/Manufacturing/AssetManagement).

### US-GOV-032 · Criar BRIEFING.md de memory/requisitos/_Governanca/ (front-door) antes de commitar o dir

> owner: — · priority: p2 · estimate: 0.5h · status: todo · type: story
> blocked_by: —

**Origem:** auditoria de saúde/integridade 2026-06-21 (batedor de governança).

**Achado:** `memory/requisitos/_Governanca/` (trabalho em andamento) tem ≥2 `.md` e **não tem `BRIEFING.md`**. Quando o dir for commitado, ele entra no censo de módulos do `knowledge-drift` sem front-door → `front_door_coverage` cai de 100 → 98.6 e a **catraca armada do sdd-scorecard morde** (🔴). Todos os outros meta-dirs `_*` (`_DesignSystem`, `_Ideias`, `_processo`…) têm BRIEFING.

**Acceptance:**
- Criar `memory/requisitos/_Governanca/BRIEFING.md` (front-door auto-contido) junto/antes de commitar o dir.
- Regenerar `governance/sdd-scorecard.json` (`node scripts/governance/sdd-scorecard.mjs`).
- `--ratchet` volta a verde.

### US-GOV-033 · Corrigir links internos residuais (corpos de ADR append-only + dead-links de alvo incerto)

> owner: — · priority: p3 · estimate: 2h · status: todo · type: story
> blocked_by: —

**Origem:** auditoria de saúde/integridade 2026-06-21 (batedor de links). Os links seguros em rules/SPECs já foram corrigidos (#3147, #3152). Restou o que NÃO é auto-fixável:

**Achado:**
- Corpos de ADR (append-only — precisam de bênção): `0250` (3 slugs defasados), `0253:123` (link 0013 aponta pro ADR errado → deveria ser o caminho UI `_DesignSystem/adr/ui/0013-...`), `0254` (slug 0209).
- Dead-links de alvo incerto: `NfeBrasil/SPEC.md` → `app/Manifesto.php` (inexistente); `.claude/rules/README.md:11` → session-log inexistente; `Connector/SPEC.md:124` → placeholder `0021-...` com "(se existir)".
- `memory/decisions/0296-...` (untracked): 2 links (slugs 0053/0084) — corrigir quando commitar.

**Acceptance:**
- Decidir alvo correto de cada item e corrigir.
- Fixes em corpos de ADR só com aprovação (política append-only ADR 0094).

### US-GOV-034 · sqlite-test-corruptors --strict pega tier S (CORE-drop), não só tier A

> owner: — · priority: p1 · estimate: 1h · status: todo · type: story
> blocked_by: —

**Origem:** verificação adversarial 2026-06-21 (PR #3145 · handoff 2026-06-21-1250).
**Problema:** gate roda `--tier=A`; `view = real.filter(r => r.tier === TIER_FILTER)` (~L363) filtra só tier A. `Schema::drop('business')` reintroduzido classifica tier S (CORE-drop) → `view.length===0` → não dá exit 1. O gate não pega o pior caso que o próprio counterfactual promete.
**Fix:** `r.tier === TIER_FILTER || r.tier === 'S'` (tier S sempre morde). Validar `tests/sqliteCorruptors.spec.ts`.
**Acceptance:** counterfactual tier-S → exit 1; meta-teste vitest verde; fazer antes de promover US-GOV-021 a required. Refs: ROADMAP-SDD

### US-GOV-035 · knowledge-drift: isentar _Governanca/roadmap/ (planos citam ghosts legitimamente)

> owner: — · priority: p2 · estimate: 1h · status: todo · type: story
> blocked_by: —

**Origem:** red advisory do PR #3135 (anti-ghost).
**Problema:** os planos do roadmap citam os módulos legados MemCofre/PontoWr2/Copiloto/DocVault (renomeados→SRS/Ponto/Jana ou removidos) em contexto de planejamento da remoção/rename → o detector os conta como ghost vivo. É falso-positivo.
**Fix:** estender `scripts/governance/knowledge-drift.mjs` (já tocado pelo P11/#3155) pra isentar `_Governanca/roadmap/` como já isenta `adr/`.
**Acceptance:** anchor-ghost verde no #3135; doc citando ghost em roadmap/ não dispara. Refs: ROADMAP-SDD

### US-GOV-036 · Isolar corruptores era-sqlite restantes (lotes 2-3 — 11 de 18)

> owner: — · priority: p2 · estimate: 3h · status: todo · type: story
> blocked_by: —

**Origem:** PR #3145 (US-GOV-021) isolou 7/18.
**Problema:** restam 11 corruptores tier A (CoworkHandoffCrossTenant, Forja Backlog/Changelog/Mcp/Quadro Service, Claimless, FsmTransitionGuard, TaskUpdateAtomic, AcceptanceRef, WorkLease, LeaseBriefSection) que ainda dão exit 1 no auditor.
**Fix:** mesma técnica do lote 1 (guard teardown sqlite-only, sem dropar tabela CORE).
**Acceptance:** `sqlite-test-corruptors --strict --tier=A` corruptors=0; aí destrava promover o gate a required. Refs: ROADMAP-SDD

### US-GOV-037 · Backfill related_us em 132 charters sem link (join US→tela do SA-A5)

> owner: — · priority: p2 · estimate: 4h · status: todo · type: story
> blocked_by: —

**Origem:** crítico de completude 2026-06-21 (PR #3157 declarou o campo + migrou 3 legacy).
**Problema:** 132/148 charters sem `related_us` → o join US→tela que alimenta o batch SA-A5 (anchors por IA) ficou sem fonte.
**Fix:** lote IA (Haiku) deriva related_us de SPEC/git/árvore Pages, com refutador G5 + 1 entry no ledger.
**Acceptance:** charters_sem_us cai; lint conta; SA-A5 lê o join. Refs: ROADMAP-SDD

### US-GOV-038 · Ligar alerta do nightly-diff tripwire (NIGHTLY_DIFF_ALERT=1) pós-floor estável

> owner: — · priority: p2 · estimate: 1h · status: todo · type: story
> blocked_by: US-GOV-021

**Origem:** PR #3158 entregou o tripwire advisory com alerta OFF.
**Problema:** ligar o alerta (gh issue / mcp_alertas na `maybeAlert()`) antes do floor estabilizar alertaria ruído de ambiente.
**Fix:** setar `NIGHTLY_DIFF_ALERT=1` + implementar o canal depois que P03/US-GOV-021 estabilizar o floor.
**Acceptance:** classe de falha nova/arquivo novo no floor → 1 issue idempotente/dia; 2 noites iguais → no-op. Refs: ROADMAP-SDD

### US-GOV-039 · TDAD-lite — lane de testes impactados no PR (test-map via pcov + sombra 14d)

> owner: — · priority: p3 · estimate: 4h · status: todo · type: story
> blocked_by: US-GOV-011

**Origem:** crítico de completude 2026-06-21 (ADIADO conscientemente).
**Problema/PORQUÊ-ADIAR:** TDAD acelera o loop, mas o gargalo é o floor sujo (295 + 24% skip). Rodar "só impactados" sobre suíte que já falha mascara regressão. Prematuro até pcov medir (P07) e floor=0 (P04).
**Fix:** test-map per-test via `--coverage-php` + comando `test:impacted` + gate sombra advisory 14d com fallback fail-safe pra suite completa.
**Acceptance:** só DEPOIS de pcov measured + nightly verde. on-hold. Refs: ROADMAP-SDD

### US-GOV-040 · Roadmap-v2 — dobrar correção P01/P02 + entries P14/P15/P16 no _ROADMAP.md

> owner: — · priority: p3 · estimate: 1h · status: todo · type: story
> blocked_by: —

**Origem:** aterramento das ondas tardias 2026-06-21.
**Problema:** o `_ROADMAP.md` (#3135) ainda diz que o read-side do floor "quase resolveu" — mas o HEAD commitado é `not_yet_measured` e o "274" é working-tree sujo do #3020. P02 deve congelar o **295 vivo**, não 274.
**Fix:** corrigir a seção P01/P02 + adicionar entries P14/P15/P16-on-hold, após #3135 mergear.
**Acceptance:** _ROADMAP.md reflete o estado real. Refs: ROADMAP-SDD

### US-GOV-041 · Limpar governance/sdd-scorecard.json sujo (274 fantasma) no working-tree de main

> owner: — · priority: p3 · estimate: 0.5h · status: todo · type: story
> blocked_by: —

**Origem:** git status do checkout principal (não é desta sessão; último commit real #3020).
**Problema:** `M governance/sdd-scorecard.json` não-commitado mostra full_suite=274 (engana a leitura; o HEAD diz not_yet_measured). Atrapalhou o planejamento desta sessão.
**Fix:** quem é dono do working-tree decide: `git checkout` (descartar) OU commitar o valor correto via o job de P01/#3142. NÃO tocar cego.
**Acceptance:** working-tree limpo ou o 274 reconciliado pelo commit-back. Refs: ROADMAP-SDD

### US-GOV-042 · anchor-lint pula status:arquivado + decidir destino do SPEC duplicado MemCofre/SRS

> owner: — · priority: p2 · estimate: 1h · status: todo · type: story
> blocked_by: —

**Origem:** sweep do mês 2026-06-21 (PR #3149, bloco "PR-B · MemCofre (10 dead)").
**Problema:** `anchor-lint.mjs` não filtra specs `status: arquivado`; `memory/requisitos/MemCofre/SPEC.md` é duplicado do módulo renomeado MemCofre→SRS (em DEPRECATION-PLAN) → 10 anchors mortos perenes.
**Fix:** lint pula `status: arquivado` (~3 linhas) + decisão de identidade sobre o SPEC duplicado (re-point pra SRS vs delete).
**Acceptance:** `anchor-lint --json` não conta os 10 dead do MemCofre; SPEC duplicado resolvido. Refs: ROADMAP-SDD (sweep do mês)

### US-GOV-044 · Reconciliar dívida anchor-fidelity residual na main (pós-ADR 0303)

> owner: — · priority: p2 · estimate: 6h · status: todo · type: story
> blocked_by: —

Reconciliar a dívida de fidelidade spec↔código que o lint SA-A2-bis (ADR 0303, [PR #3240](https://github.com/wagnerra23/oimpresso.com/pull/3240)) tornou visível na main, hoje **grandfathered** (gate advisory + diff-aware → não avermelha). É a reconciliação que a onda-0 fez mas cujos commits nunca chegaram na main (a branch derivou 93 atrás).

**Medição full-tree** (origin/main @2e4552a789 · `node scripts/governance/anchor-lint.mjs --json` → `.modules[].{zombie,dead,dead_tests}`):

- **1 anchored_zombie** — Financeiro `US-FIN-013` → `resources/js/Pages/Financeiro/Dashboard/Index.tsx` (tela deprecada/redirect; corrigir a âncora).
- **9 anchored_dead** — MemCofre `US-DOCVAULT-002..011` (SPEC `status: arquivado`, duplicado MemCofre→SRS). **Já coberto por US-GOV-042** (lint pular `arquivado`) — não duplicar aqui.
- **76 dead_tests** (`Testado em:` → teste-fantasma) — Financeiro 14 · NfeBrasil 13 · RecurringBilling 12 · LaravelAI 9 · Accounting 7 · Essentials 7 · Repair 7 · Manufacturing 5 · Crm 1 · _DesignSystem 1.

**Escopo acionável** (descontando MemCofre→042): o 1 zombie + os 76 dead_tests.

**Como fazer:** re-derivar contra a main ATUAL (não copiar os edits antigos da onda-0 — SPECs divergiram). Idealmente 1 PR por módulo (commit-discipline).

**Coordenar:** SPECs com WIP de sessões paralelas (ex: `governance/sdd-scorecard.json` sujo — US-GOV-041). Cruzar antes de tocar.

**Não confundir com:** US-GOV-029 (promover anchor-gate a required = ato pós-merge) nem com o re-arm do baseline do scorecard (ADR 0275 §3 · `anchor_coverage` hoje `armed:false`).

> _ID: o MCP `tasks-create` sugeriu US-GOV-043, mas 043 já é o `charter_refs_broken` da onda-0 (unmerged) — usado **044** pra não forçar renumeração quando o charter-refs landar._

**Acceptance:** `anchor-lint --json` (descontando specs `arquivado`) reporta 0 zombie + 0 dead_tests. Refs: ADR 0303 · ADR 0273 · US-GOV-042 · US-GOV-041

### US-GOV-045 · FV-F4: run inválido do nightly nunca mais é silencioso (post-mortem + alerta)

> owner: — · priority: p1 · estimate: 4h · status: done · type: story
> blocked_by: —

**Implementado em:** `scripts/tests/ct100-fullsuite.sh` · `scripts/tests/junit-summary.mjs` · `scripts/tests/floor-compute.mjs` · `scripts/tests/nightly-diff.mjs` · verificado@2026-07-02 — caminho de erro simulado no CT100 (junit 0 bytes → marcador `invalid` + `[ALERT]`).

**Origem (medida, não hipótese):** 2 dos últimos 5 runs em `/opt/oimpresso-fullsuite/runs/` (`20260629-020001`, `20260701-132941`, `20260702-073601`) morreram com `junit.xml` de **0 bytes** — o Pest roda (`pest-out.txt`/`run.log` completos, `Duration:` presente OU cortado no meio), mas a emissão `--log-junit` só faz flush no fim e o processo morre antes. Padrão de morte: **exit 2 mid-suite sem fatal impresso**, shim containerd deletado (ex `09:03:23` no run de 02/jul), `swap` do CT100 em 2.4G — **pressão de memória / kill externo do container**, NÃO OOM-killer do kernel (`oom_kill 0` no cgroup) nem disco (95% mas com folga). O `floor-compute` exclui run morto corretamente (fail-red se <2 válidos), MAS a única evidência de que morreu era a **ausência** de `summary.json` — se a taxa de morte subir, o floor **congela stale** sem ninguém perceber.

**DoD:**
- **D.1 — post-mortem que sobrevive à morte:** o run do Pest emite `--log-events-text /artifacts/pest-events.txt` além do `--log-junit`. O events-log streama **1 linha por evento com flush imediato**, então sobrevive ao kill e o último `Test Prepared (...)` **nomeia o teste em voo** no instante da morte (provado no CT100 2026-07-02 via `SIGKILL` mid-run: última linha = teste assassinado). O `junit.xml` continua o artefato canônico (FV-F1); os eventos são instrumento de triage, apagados em run válido (disco CT100 ~95%).
- **D.2 — marcador explícito de invalidez:** `junit-summary.mjs --out` grava `{invalid:true, reason}` no `summary.json` quando o XML está ausente/0 bytes/incoerente (`reason ∈ {xml_ausente, xml_0_bytes, coleta_0_testcases, coleta_incoerente}`). O **exit code do tripwire FV-F1 é preservado** (1 = artefato ausente/0 bytes; 2 = coleta incoerente). Run morto deixa **rastro legível por máquina** em vez de sumir.
- **D.3 — leitores ignoram o marcador (dupla guarda):** `floor-compute.mjs` e `nightly-diff.mjs` pulam qualquer run com `invalid:true`, além da guarda pré-existente `!coherent || !n_testcases`.
- **D.4 — alerta estruturado:** o harness emite uma linha `[ALERT] fullsuite_run_invalid ts=... sha=... pest_exit=... junit_summary_exit=... tests_prepared=N last_test_in_flight="..."` (key=value grep-ável) quando o summary sai inválido — o sinal chega ANTES de o floor congelar.

**Aceite:** um run com `junit.xml` de 0 bytes produz (a) `summary.json` com `invalid:true` + `reason`, (b) linha `[ALERT] fullsuite_run_invalid` no `run.log` nomeando o teste em voo, (c) `floor-compute` continua excluindo o run. Um run válido é idêntico ao de hoje (events-log apagado, `summary.json` coerente sem campo `invalid`).

**Testado em:** `scripts/tests/junit-summary.test.mjs` (marcador + exit codes, roda o script real como subprocess) · `scripts/tests/floor-compute.test.mjs` (caso `invalid-marker` excluído) · `tests/fullsuiteHarness.spec.ts` (contrato do harness: events-log presente + tripwire usado + alerta). Anti-tautológico (proibicoes §5): asserts ancorados neste DoD, não no código.

**Fora de escopo:** consertar a causa-raiz da pressão de memória (a suite inteira em 1 processo + coverage separado já é a mitigação estrutural do #3622); esta US garante **observabilidade honesta** da morte, não a elimina. Refs: FV-F1 (junit-summary tripwire) · ADR 0279 (floor) · US-GOV-018 (harness) · #3622 (coverage 2ª invocação)

### US-GOV-046 · Triar drafts acumulados em decisions/proposals/

> owner: — · priority: p2 · status: todo · type: story
> blocked_by: —

Pile de limbo exposto pela sentinela `memory-health` (Check U `proposta-em-limbo`): ~90 drafts de ADR em `memory/decisions/proposals/` sem decaimento (saudável ~25). Triar cada um: **promover** (supersede atômico, ADR 0258), **arquivar** ou **esquecer** (ADR 0316/0270).

**Definition of Done (verificável):** `node scripts/governance/memory-health.mjs` → Check U `proposta-em-limbo` **= 0** (ou abaixo do limiar saudável). Não "achei que terminei".

Origem: sessão 2026-07-04 consolidação de conhecimento. Proposta pela máquina `governance-backlog-sync`. `<!-- gov-sync: proposta-em-limbo -->`

### US-GOV-047 · Consertar links internos quebrados na canon

> owner: — · priority: p2 · status: todo · type: story
> blocked_by: —

Links internos mortos na canon front-facing, expostos pela sentinela `memory-health` (Check V `link-quebrado`): ~15 restantes (após 32 já consertados por nº-de-ADR e basename em #3804/#3806). Os residuais são não-determinísticos: alvo deletado/movido pra fora do repo (sessions renomeadas, `.claude`/`scripts`/`Modules` cross-tree, path Windows absoluto) ou basename ambíguo (`SKILL.md` 72 candidatos). Resolver caso-a-caso: **recriar** o alvo, **repontar** ou **remover** o link.

**Definition of Done (verificável):** `node scripts/governance/memory-health.mjs` → Check V `link-quebrado` **= 0**.

Origem: sessão 2026-07-04. Proposta por `governance-backlog-sync`. `<!-- gov-sync: link-quebrado -->`

### US-GOV-048 · Desambiguar dirs homônimos sob memory/ (dominio/ vs dominios/)

> owner: — · priority: p3 · status: todo · type: story
> blocked_by: —

Homônimo exposto pela sentinela `memory-health` (Check U `dir-homonimo`): `memory/dominio/` (singular, 6 arquivos — dicionário de domínio canon, fonte do gate `dominio:check` ADR 0264) coexiste com `memory/dominios/` (plural, 425 arquivos — Migration Factory legacy, ADR 0118/0119). Ambos vivos mas nome colado racha navegação/recall. **Decisão Wagner:** qual nome ganha o rename (raio alto: 425 arquivos + 12 refs). Renomear um dos dois + reapontar refs.

**Definition of Done (verificável):** `node scripts/governance/memory-health.mjs` → Check U `dir-homonimo` **= 0**.

Origem: sessão 2026-07-04. Proposta por `governance-backlog-sync`. `<!-- gov-sync: dir-homonimo -->`

### US-GOV-049 · Ratificar ADR 0329 (doutrina documentação de processo) — flip proposto→aceito

> owner: wagner · priority: p1 · estimate: 1h · status: todo · type: story
> blocked_by: —

- ADR 0329 mergeada no main (#4008) com `status: proposto` — as 5 propriedades (executável/fonte-única/ligada-ao-gate/cross-plataforma/auto-fresca)
- Ratificar = editar O MESMO arquivo `memory/decisions/0329-doutrina-documentacao-de-processo-executavel.md`: `status: proposto → aceito` (append-only, não move de pasta)
- Depois: `node scripts/governance/adr-index-generate.mjs --write` + commitar índice junto
- Só então a doutrina entra na busca default do `decisions-search` (scopePorStatusAtivo)

### US-GOV-050 · Ratificar 0314 (por-item) + 0299 e mover 0320 aceita presa em proposals/

> owner: wagner · priority: p1 · estimate: 2h · status: todo · type: story
> blocked_by: —

- 0314 (poda de gates): `accepted_via` diz "aguarda ratificação POR ITEM" — decisão [W] item a item; já executou em prod (required 29→22)
- 0299 (figma não é fonte): `status: proposto` mas citada como canon no CLAUDE.md — flip proposto→aceito
- **Bug vivo**: 0320 está `status: aceito` porém PRESA em `memory/decisions/proposals/` → invisível ao MCP (o sync faz glob não-recursivo). Mover pro top-level `memory/decisions/0320-*.md` + `adr-index-generate --write`
- Destrava os consertos P19/P20/P21/P4 da revisão (âncora required = ADR enacted)

### US-GOV-051 · Review + merge PRs #4009 (tombstones P16) e #4010 (ref-integrity P10)

> owner: wagner · priority: p1 · estimate: 1h · status: todo · type: story
> blocked_by: —

- #4009: cura 18 tombstones de path-fantasma (follow-up do knowledge-drift ghost #4006) — CLEAN, verde
- #4010: sentinela `ref-integrity.mjs` advisory (middleware fantasma / colisão de rota / route() inexistente no sidebar / Inertia::render sem .tsx) — os 4 anti-padrões F3 que o PHPStan não vê
- Últimos 2 dos 5 chips da revisão da memória do processo (2026-07-09); os outros 4 já mergeados (#4004/#4005/#4006/#4007)

### US-GOV-052 · Backlog da revisão da memória do processo — consertos M/G restantes

> owner: — · priority: p2 · estimate: 16h · status: todo · type: story
> blocked_by: —

- Relatório completo: artifact "revisao-memoria-processo" (sessão 2026-07-09) — 26 consertos sobreviventes ao adversário, 10 rejeitados (não re-propor)
- Restantes: P24 (portar 5-7 blockers Tier-0 .ps1→.mjs ANTES do time MCP entrar em Mac/Linux) · P31/P32/P33 (hue/skill-tier/fase fonte-única) · P6/P13/P35 (manifest duro-vs-advisory, anti-sentinela-órfã, anti-verde-no-vácuo) · P11-A (excludePaths PHPStan, prova no CT100 antes)
- Regra transversal: cada guarda nasce com fixture good/bad no gate-selftest, ancorada em contrato citado; advisory salvo Tier-0 (0314)
- Follow-up da sentinela #4011: FLAG "ADR pendente" no Daily Brief (server-side, Modules/Jana Brief)

### US-GOV-053 · recall_eval_violations: transporte versionado do cron dominical pro scorecard SDD

> owner: — · priority: p2 · estimate: 4h · status: todo · type: story
> blocked_by: —

**Implementado em:** _pendente_

Última métrica `not_yet_measured` do scorecard SDD (as outras 2 do lote — `drift_alarms` + `read_path_hops` — foram fiadas no PR #4196; ranking adversarial 2026-07-12 item #6). Diferente delas, esta é **write-side novo**: o golden set recall (KL-C2) roda no cron dominical do CT100 mas o resultado não chega ao repo.

**DoD (pattern nightly-floor · ADR 0279 Opção A — espelhar `measureRagasRealUptime`):**
- Write-side CT100: script publica `governance/recall-eval-trend.json` (violations por run + schema versionado) em branch órfã própria, git-push `[skip ci]`.
- Read-side: `measureRecallEvalViolations()` em `sdd-scorecard.mjs` com fallback honesto (ausente/inválido → `not_yet_measured`, NUNCA mente 0) + materialização da órfã nos workflows do scorecard (publish + advisory; ratchet soft enquanto armed:false).
- REGRA DURA (ADR 0275 §3 + ADR 0314): nasce not-armed e advisory — baseline só arma após 3 medições válidas consecutivas, via PR explícito; nada vira required.

**Aceite:** scorecard 13/13 measured após 1º cron pós-merge; `--ratchet` ignora a métrica (sem entrada armada no baseline). Refs: KL-C2 · ADR 0279 · ADR 0318 (espelho ragas).

### US-GOV-054 · Coletar bite-log retroativo dos 3 gates DS required (fechamento empírico ADR 0339 / DR-2a 0336)

> owner: — · priority: p2 · estimate: 3h · status: todo · type: story
> blocked_by: —

**Implementado em:** _pendente_

A promoção de `Layout primitives · ratchet`, `Stylelint · ratchet vs baseline` e `ESLint · ratchet vs baseline` a required (2026-07-15, flip 24→27) desviou da DR-2 da ADR 0336: o bite-log de ≥2 PRs contrafactuais por gate NÃO foi coletado. Foi mantido por exceção soberana [W] (ADR 0238), registrada honestamente na ADR 0339 como desvio consciente.

**Follow-up pra dar fechamento empírico:**
1. Ativar/alimentar `memory/governance/design-gate-bites.jsonl` (DR-2a da 0336) — registrar `{gate, pr, sha, arquivo, quando}` toda vez que um dos 3 reprovaria uma violação que mergeou.
2. Acumular ≥2 mordidas reais por gate ao longo de N semanas.
3. Se algum dos 3 NÃO acumular ≥2 mordidas reais em ~4-6 semanas, reconsiderar a demoção daquele item (gate sem mordida no mundo = só selftest = candidato a advisory de volta). Reversível via `gh api` re-remove do context na branch protection.

**Aceite:** `design-gate-bites.jsonl` ativo + ≥2 mordidas por gate registradas, OU decisão explícita de demoção do(s) gate(s) sem mordida. Contexto: exit-code real (DR-3.1) já cumprido pelos 3; evidência atual fraca (layout 1 fail / stylelint 2 fails / eslint 0 na janela, mas ratchet conta warnings). Refs: ADR 0339 · 0336 (DR-2/DR-2a) · 0314 · 0238 · PRs #4301 (require-safe) + #4307 (registro).

### US-GOV-055 · Âncora ganha eixo TEMPORAL: consumir o `verificado@sha` que a gramática já exigia

> owner: — · priority: p2 · estimate: 3h · status: done · type: story
> blocked_by: —

**Implementado em:** `scripts/governance/anchor-lint.mjs` · `scripts/governance/anchor-stale.test.mjs` · `.github/workflows/anchor-drift.yml` · verificado@387dfcc (2026-07-17)

**Testado em:** [`scripts/governance/anchor-stale.test.mjs`](../../../scripts/governance/anchor-stale.test.mjs)

**Origem (medida, não hipótese — grade de réguas 2026-07-17):** a gramática do campo `**Implementado em:**` ([ADR 0273](../../decisions/0273-anchor-spec-codigo-formato-canonico-fluxo-novo.md) §1) **exige** `verificado@<sha7> (YYYY-MM-DD)` desde sempre, e o `anchor-lint` reprova quem não põe. Varredura contada: o SHA é lido em **2 arquivos / 5 sites** — `SpecAnchorClassifier::GRAMMAR_OK_RE` (que o **captura**) e `TaskParserService::deveFecharPorAncora` — e o único uso é **presença** (`is_string($sha) && $sha !== ''`). **Ninguém comparava com o HEAD.** Ou seja: o projeto cobrava um dado, o dado chegava, e ele morria no parser.

Os 4 vereditos que existiam respondem o **presente**: `dead` (path sumiu) · `zombie` (tela desligada) · `servido` (teve hit). Faltava o **passado**: *"o código mudou desde que alguém verificou?"*.

**Medição de estreia (full-tree, 2026-07-17):** **21** âncoras stale · **123** sem movimento · **298** não-medíveis — destas, **277 (63% do total) por `sha_fora_da_ancestralidade`**. Achado colateral e maior que o chip: **a convenção do carimbo está sistematicamente quebrada** — o agente carimba o sha do HEAD da *própria branch*, e o **squash-merge come esse commit**; o sha resolve no clone de quem fez o PR e some no CI. Só sobrevive quem carimba sha **já na main** (medido: 8 dos 20 SHAs distintos do repo).

**DoD:**
- **D.1 — o eixo existe e morde:** `--stale` marca `anchor_stale` quando ≥1 path da âncora foi tocado por commit entre o `verificado@sha` e o HEAD (`git log <sha>..HEAD --name-only`, **1 chamada por sha DISTINTO** — 20 distintos pra 427 âncoras, não 1 por âncora).
- **D.2 — guard anti-fabricação (o coração):** todo caso ambíguo vira `anchor_stale_unknown` com motivo, **NUNCA "fresco"** — `checkout_shallow` · `sha_ausente` · `sha_fora_da_ancestralidade` · `git_log_falhou`. Razão dura: `git log <sha>..HEAD` sobre sha **não-ancestral não erra — MENTE** (mede desde o merge-base, inflando). O shallow-check é o fino do `sdd-scorecard::isShallowHistory` (boundary de órfã **não** trunca o HEAD), não o `--is-shallow-repository` grosso.
- **D.3 — não contamina o veredito:** fora de `anchor_coverage`, fora da flag 🟢/🟡/🔴, fora de todo `--check*`. É sinal, não veredito.
- **D.4 — invariante fs-puro do caminho required preservada:** sem `--stale` o script **não executa git nenhum** — os jobs required `anchor-lint ADR 0273` e `anchor entry/covers gate` seguem fs-puros ([ADR 0303](../../decisions/0303-anchor-lint-wired-testado-sa-a2-bis.md)). Provado rodando o script com `PATH` **sem git**: exit 0, output byte-idêntico ao HEAD.
- **D.5 — nasce com invocador:** job `anchor-stale` no `anchor-drift.yml` (`fetch-depth: 0`), espelhando o `verde-gate-advisory`. Flag sem quem chame seria a lápide do *chokepoint fantasma* ([proibicoes §5](../../proibicoes.md) 2026-07-09).

**Por que NÃO é a lápide §5 de 2026-07-09** (*"frescor por `verificado_em` vs git-mtime duplica o `briefing-code-staleness`"*): (a) **não é motor novo** — a lápide manda **estender o dono do tema**, e o dono da gramática `**Implementado em:**` é este script; (b) **granularidade inédita** — `briefing-code-staleness` mede PORTA×código-do-MÓDULO, `doc-freshness-score` é score por doc, `distiller_freshness` é `distilled_at`×doc-mais-novo; **nenhum** mede US-âncora × os paths **concretos** que aquela US declara; (c) **não é catraca sobre campo auto-declarado** (a lápide do `last_validated`): o sha é declarado, mas o que se **mede** são os commits reais entre ele e o HEAD — mesma estrutura do churn do `doc-freshness-score` (o declarado é só a base).

**Resíduo honesto (registrado, não escondido):** quem re-carimbar o sha sem re-verificar zera o sinal. Isto detecta **divergência**, não desonestidade — o custo do gaming é um commit auditável no diff do PR. E hoje o eixo só mede ~40% das âncoras; os 60% `unknown` são, eles próprios, o achado.

**Aceite:** `anchor-lint --stale` reporta os 3 números (stale/fresco/não-medível com motivos) e `anchor-stale.test.mjs` passa contra **repo git real** — bite (path tocado → stale), release (path parado → não-stale), os 2 guards (sha ausente e sha não-ancestral → `unknown`, nunca fresco) e a invariante (sem `--stale`, `anchor_stale_on: false` e `anchor_coverage` inalterado). Mutação verificada: desligar o eixo → 3 FAILs; desligar o guard da ancestralidade → 2 FAILs (incluindo o falso "fresco"). Refs: ADR 0273 · ADR 0303 · ADR 0314 (required = Tier 0; este é higiene) · US-GOV-045 (padrão do teste .mjs)

---

### US-GOV-056 · Decidir: `agent-corpus-counterfactual` — dar porta ou aposentar (poda de capacidade)

> owner: wagner · priority: p3 · estimate: 1h · status: blocked · type: story
> blocked_by: decisão [W] (soberania — poda de capacidade)

**Implementado em:** _pendente_ — é decisão, não construção; o código a decidir já existe (`scripts/governance/agent-corpus-counterfactual.mjs`).

Sobra da triagem dos 13 scripts órfãos ([PR #4834](https://github.com/wagnerra23/oimpresso.com/pull/4834), 2026-07-27): **13 → 2** sem invocador executável. Este é 1 dos 2 que exigem dono.

É a aritmética de viabilidade do contrafactual de corpus. O harness de execução foi **podado em 2026-07-17 por [W]** (zero invocador + experimento não autorizado, ADR 0105/0334), e o experimento que ela serviria morreu pela própria resposta dela.

Duas saídas, **ambas oferecidas pelo próprio detector** (`selftest-registry-check --scripts`: *"ligar (agendar/step de CI) ou aposentar (remover + lápide no §5)"*):

- **(a)** porta npm `corpus:power` — mantém como ferramenta sob demanda;
- **(b)** aposentar — deletar + lápide no [§5 de proibicoes.md](../../proibicoes.md).

Poda de capacidade é soberania [W] ([proibicoes.md §Sempre fazer](../../proibicoes.md)). Deixada **de propósito** fora da porta npm no #4834: dar porta a tiraria da lista de órfãos e esconderia esta pendência.

O outro dos 2 (`charter-promote-signal`) **já tem decisão registrada** em 2026-07-26 (`governance-gate-umbrella.yml:73`) — fica manual porque ESCREVE (`draft→live`).

**Aceite:** o `--scripts` para de listar este script (porque ganhou invocador OU porque não existe mais), e a decisão fica registrada — npm script no `package.json` ou lápide no §5.

---

### US-GOV-057 · `handoff-integrity` vermelho há 17d — 3 `PROMPT_PARA_CODE` órfãos da fila Cowork

> owner: wagner · priority: p2 · estimate: 1h · status: blocked · type: story
> blocked_by: decisão de quem opera o loop Cowork (citar na fila vs arquivar)

**Implementado em:** _pendente_ — dívida de fila, não de código; o gate que a denuncia já existe e já morde (`handoff-integrity-guard`).

Medido 2026-07-27: o check **`handoff integrity` está `failure` no `main` desde 2026-07-22** — 5 runs consecutivos (`43bf8e59`, `71b6c7f1`, `e2572aae`, `600fbc6d`, `67eba4ec`). É advisory, então não bloqueia — **e por isso ninguém agiu**.

Dívida: 3 `PROMPT_PARA_CODE_*.md` existem no dir e não são citados acima da linha d'água do `COWORK_NOTES.md` (= tarefa invisível, [`PROCESSO_MEMORIA_CC.md §16`](../../../prototipo-ui/PROCESSO_MEMORIA_CC.md) regra 1 — *"Criou → cita na fila no mesmo passo"*):

- `PROMPT_PARA_CODE_DS-DOMINIO-RETIRAR-DSV6.md`
- `PROMPT_PARA_CODE_DS-ESPELHAR-DOMINIO.md`
- `PROMPT_PARA_CODE_ESTRUTURA-COWORK-ATUALIZADA.md`

Entraram no `main` em 2026-07-10 ([PR #4096](https://github.com/wagnerra23/oimpresso.com/pull/4096)).

Duas saídas que o próprio gate oferece: **(a)** citar na fila ativa do `COWORK_NOTES.md`; **(b)** arquivar (descer abaixo da linha d'água). Se a dívida mudou legitimamente, `npm run handoff:baseline:write` no mesmo PR. Relatório local: `npm run handoff:report`.

Dono do ato = quem opera o loop Cowork. Escalado a [W] porque **17 dias de vermelho sem dono é o sintoma**, não a causa.

**Levantado em 2026-07-27 — evidência PARCIAL, não fecha a decisão:** dos 3, **2 já pousaram no essencial** — `cockpit_domains.css` existe (`scripts/design-sync/mirror-snapshot/`) e os tokens de domínio estão no SSOT (`resources/css/tokens/semantic.tokens.json`, ex. `kind-*-soft`). **Mas o objetivo final não se cumpriu:** `prototipo-ui/cowork/ds-v6/tokens.css` **ainda está no git**, e deletá-lo era justamente o ponto dos dois prompts. Por isso não foi arquivado como "pousado" — seria afirmar conclusão sem prova completa. O 3º (`ESTRUTURA-COWORK-ATUALIZADA`, toca `CLAUDE.md`) não foi verificado. Complicador: a fila do `COWORK_NOTES.md` está **🧊 congelada pra novos itens** (Onda B, [W] 2026-06-16), então "citar na fila ativa" contraria o congelamento — a saída provavelmente é GitHub Issue ou `_arquivo/`, e essa escolha é do dono do loop Cowork.

**Aceite:** `npm run handoff:report` volta a `órfãos 0/0` (ou o baseline reflete a dívida decidida), e o check fica verde no `main`.

---

### US-GOV-058 · Âncora: 65% do `verificado@sha` é cego — carimbo ancestral por construção (forward-only)

> owner: wagner · priority: p2 · estimate: 2h · status: blocked · type: story
> blocked_by: decisão [W] — emenda ao ADR 0303

**Implementado em:** _pendente_ — o detector existe (US-GOV-055, `--stale`); falta a decisão sobre a RECEITA de carimbo.

**O ATO que falta da [US-GOV-055](#us-gov-055--âncora-ganha-eixo-temporal-consumir-o-verificadosha-que-a-gramática-já-exigia).** Aquela US já registrou o achado em 2026-07-17 (*"a convenção do carimbo está sistematicamente quebrada… só sobrevive quem carimba sha já na main"*) — **e nada mudou desde então**. Esta US é o ato, não a re-medição.

**Re-medido 2026-07-27** (clone completo, `is-shallow=false`): 428 âncoras carimbam **25 SHAs distintos**; **10 são ancestrais** do HEAD (cobrem 148 âncoras) e **15 não são** (cobrem **280**). 65% do eixo temporal é estruturalmente não-medível — não por falta de reconciliador, mas porque o `verificado@` grava o sha da **branch** e o squash-merge o descarta. Concentrado: **4 SHAs respondem por 236 das 280** (`dd3ed7c`=136 · `176f9bc`=67 · `3b425d8`=21 · `98cae0a`=16).

**A1 — forward-only (proposta):** a receita de carimbo passa a usar sha **ancestral por construção** (`origin/main` no momento da verificação). Âncora nova nasce medível; legado fica grandfathered. Zero mudança de exit code, zero presence-gate, zero gate novo.

**A2 — lote retroativo (opcional), com trava dura:** `--reverify` em massa é **gaming automatizado** — re-carimbar apagaria as 30 `anchor_stale` sem ninguém olhar o código. Só re-carimba o que o lint prova (existe + wired); as 30 stale seguem exigindo olho humano.

**Contexto que mata o pedido original** (investigação 2026-07-27): o eixo `path::simbolo` **não é o problema** — **0 de 469** renames em 180d deixaram âncora apontando path antigo; `anchored_dead=1` e `anchored_zombie=0` em 981 US; símbolo aparece na lista canônica de só 19 dos 447 campos (4,3%) e **0 estão mortos**. O P6 rename-proof já foi **cortado conscientemente** em 2026-06-23 (*"refatoração de pasta é rara num ERP de 5 devs; `anchored_dead` é ruído visível, não mentira silenciosa"*).

**Aceite:** emenda ao [ADR 0303](../../decisions/0303-anchor-lint-wired-testado-sa-a2-bis.md) (dono do testado-check) define a receita; `anchor-lint --stale` mostra `sha_fora_da_ancestralidade` **caindo** nas âncoras novas. Refs: US-GOV-055 · ADR 0273 · ADR 0303.

### US-GOV-059 · Triar as 43 permissões órfãs — usadas no código, declaradas em lugar nenhum

> owner: — · priority: p2 · status: todo · type: story
> blocked_by: —

**Implementado em:** _pendente_ — o detector existe (`scripts/governance/permission-drift.mjs`); falta a TRIAGEM das 43.

Achado da sessão 2026-08-05, na triagem dos scripts órfãos: o `permission-drift.mjs` estava sem invocador, e rodá-lo revelou a dívida.

**Medido no main** (`node scripts/governance/permission-drift.mjs`, ~2,2s): **357** permissões declaradas · **340** usadas com alvo literal · **30** chamadas com alvo DINÂMICO (quarentena — indecidível por texto) · **43 ÓRFÃS**.

Órfã = usada no código (`can()`, FormRequest, blade) e declarada em lugar **nenhum**. A consequência está no próprio relatório: *"ninguém consegue conceder; com o `Gate::before`, viram 'só admin' por acidente"* — a feature fica inacessível a qualquer papel não-admin, em silêncio. Amostra: `auditoria.revert`, `auditoria.export`, `arquivos.restore`, `brief.purge`, `api.access`, `configure_dashboard`.

**NÃO é pedido de gate.** O output do script declara: *"advisory por construção nesta fase — decidir forma de gate SÓ depois do FP medido"*. O trabalho é a triagem: cada uma das 43 é (a) permissão que falta declarar no seeder/config, (b) chamada morta a remover, ou (c) falso-positivo do detector (alvo dinâmico mal classificado).

**Aceite:** as 43 classificadas nas 3 categorias, com as de tipo (a) declaradas e as de tipo (b) removidas; o número cai e o que sobra tem razão escrita. Refs: sessão 2026-08-05.

#### Triagem executada — 2026-08-06

**43 → 38.** Cinco eram **falso-positivo do detector**, não permissão: a forma `middleware` (`/(?:can|permission):([a-zA-Z][\w.\-]*)/`) casava dentro de **comentário**, e o corpus tem 36 linhas de prosa citando `can:`/`permission:`. Os cinco (`x`, `kb.`, `financeiro.`, `financeiro.dashboard.view.`, `api.access`) foram verificados um a um no arquivo de origem. Corrigido no detector com 5 asserts `FP-4` + 2 controles negativos provando que o strip não come código.

Classificação das **38** restantes:

| Classe | N | O que é |
|---|---:|---|
| **A · bug de acesso confirmado** | 2 | código exige permissão que não existe → feature vira só-admin em silêncio |
| **B · feature legada sem módulo** | 5 | `hms.*` (3) e `restaurant.*` (2) — os módulos correspondentes (HMS/hospitalidade e restaurante, do UltimatePOS upstream) **não existem** nesta árvore; sobrou código no core (`TransactionPaymentController`, `resources/views/restaurant/`). _Paths de módulo não citados de propósito: a catraca anti-ghost trata citação de `Modules/<X>` inexistente como referência podre, e ela está certa — a ausência se comunica sem o path._ |
| **C · módulo nosso, nunca declarada** | ~13 | `auditoria.export/note.write/revert` · `brief.history.view/purge` · `crm.*` (4) · `arquivos.restore` · `financeiro.lancamentos.create` · `ponto.importacoes.criar` · `recurringbilling.*` (2) · `whatsapp.view-all-phones` |
| **D · core UltimatePOS** | ~18 | `admin` · `only_admin` · `subscribe` · `edit_purchase_price` · `configure_dashboard` · `send_notification(s)` · `report.stock_details` · `sale.history.view` · `*_essentials_*` · `edit_repair_settings` … |

**Classe A — os dois casos, com veredito:**

1. **`kb.ai` — RESOLVIDO.** `KbController` montava o flag `ai_ask` da UI com `can('kb.ai')`, mas o registry declara **`kb.ai.ask`** e o endpoint real (`KbAiController`) exige `can:kb.ai.ask|jana.mcp.memory.manage`. A UI escondia o botão de uma feature que o endpoint teria liberado. Corrigido para o nome declarado.

2. **`fiscal.inutilizar` — RESOLVIDO pela opção (i)** ([W] delegou a decisão em 2026-08-06: *"decida sem problemas"*). `CancelarNfeRequest::authorize()` fazia `can('fiscal.inutilizar')`, e esse nome **não é permissão** — é **role** criada por `NfeFiscalActionsSeeder` com a convenção UltimatePOS de sufixo (`fiscal.inutilizar#{business_id}`). `can()` procura permissão, não casa com role homônima, então **a inutilização de faixa fiscal NFe ficava acessível só a admin** (via `Gate::before`), contra a intenção declarada no docblock do `NfeInutilizacaoController` e no comentário da rota.

   As duas opções eram **(i)** `hasRole` com o sufixo — fiel ao documentado, acopla à convenção — e **(ii)** declarar a permissão e concedê-la à role no seeder — mais idiomático em Spatie, porém cria dois objetos de mesmo nome. **Escolhida a (i)**, por quatro fatos verificados no código:

   - a role **tem propósito vivo**: está vinculada à action FSM `inutilizar_faixa` (`is_critical: true`, `requires_confirmation: true`) — não é vestigial, e a (ii) criaria role e permissão homônimas com semânticas diferentes;
   - a intenção **sempre foi role** — declarada em dois lugares independentes (docblock do Controller e comentário da rota);
   - a (ii) exigiria **mexer no seeder do FSM**, risco desnecessário para um gate de endpoint;
   - escopo estreito: `CancelarNfeRequest` tem **um único consumidor** (`NfeInutilizacaoController::store` — a citação no NFSe é só `@see` em docblock).

   **Não afrouxa nada:** quem não tem a role segue barrado e o superadmin continua passando pelo `Gate::before`. A role só existe onde o seeder rodou — e ele **não está no `DatabaseSeeder`**, então nenhum acesso é concedido automaticamente. O fallback sem sufixo espelha o guard do próprio seeder (`Schema::hasColumn('roles','business_id')`).

   Defendido por [`InutilizacaoAuthorizeRoleTest`](../../../Modules/NfeBrasil/Tests/Feature/InutilizacaoAuthorizeRoleTest.php), registrado na allowlist da lane `nfebrasil-pest` (lane lista arquivo — teste fora dela não roda). O teste usa **payload inválido de propósito**: sem role → **403** (gate barrou), com role → **422** (gate passou, validação barrou), o que prova a autorização **sem jamais chamar a SEFAZ**. Cobre também cross-tenant (role de outro business não autoriza) e ausência de contexto de business na sessão.

#### Classe B reaberta — 2 das 5 eram classe A, não legado (2026-08-06)

A classe B dizia *"feature legada sem módulo… sobrou código no core"* e apontava remoção. **Vale para `hms.*` (3), não para `restaurant.*` (2).**

**`restaurant.*` é feature VIVA, não legado.** Medido: rotas ativas (`Route::resource('tables', Restaurant\TableController::class)` + `modifiers`), controllers em `app/Http/Controllers/Restaurant/`, e gate por business via `isModuleEnabled('tables')` — é a **Mesas**, um dos módulos core habilitáveis em `/business/settings` (Camada 2 do CLAUDE.md). Não há diretório de módulo nWidart correspondente porque a feature **nunca foi um módulo** — sempre viveu no core. _(Path não citado de propósito, pela mesma razão da nota da classe B: a catraca anti-ghost trata citação de módulo inexistente como referência podre, e ela está certa.)_

Logo as duas são **bug de acesso (classe A)**, com a forma exata do `kb.ai`:

| Onde | Checava | Endpoint exige | Efeito |
|---|---|---|---|
| `restaurant/table/index` (2 vivos + 2 em bloco comentado) | `restaurant.create` · `restaurant.view` | `access_tables` (`TableController` L21/59/78…) | quem recebe `access_tables` — a **única** permissão que a tela de papéis oferece pra Mesas — abre a tela e não vê botão nem tabela; só admin vê, por `Gate::before` |
| `restaurant/modifier_sets/index` (botão Adicionar) | `restaurant.create` | `product.create` (`ModifierSetsController` L91) | idem |

**Corrigido:** os 5 pontos acima passam a citar a permissão que o endpoint realmente exige. Órfãs **37 → 36** (`restaurant.create` saiu do censo).

**`@can('restaurant.view')` na listagem de `modifier_sets` — DECIDIDO [W] 2026-08-07: fica como está.** O `ModifierSetsController::index()` não tem guard nenhum e as ações por linha usam `product.update`/`product.delete`; não há permissão equivalente a "ver a lista", e as duas saídas (declarar uma × remover o `@can` e alinhar com o endpoint) são desenho de autorização. [W] decidiu **não mexer** — *"pode deixar os botão"* — no mesmo turno em que informou que **a feature Restaurante existe mas não está em uso agora**. Consequência aceita conscientemente: a listagem segue visível só a admin (via `Gate::before`), porque `restaurant.view` continua sem declaração. **Não reabrir sem [W]**; se a feature entrar em uso, é aí que a escolha passa a custar.

> Contexto que essa decisão fixa, e que a triagem original errava: **Restaurante/Mesas é feature existente**, confirmada pelo dono — não "módulo legado que sumiu". A classificação B ("feature legada sem módulo") estava errada quanto ao fato, não só quanto ao efeito.

**`hms.*` (3) — a classe B procede, e a remoção é inerte.** Eles aparecem só em cadeias `OR` com permissões reais (`purchase.payments`, `sell.payments`, `delete_sell_payment`…) em `TransactionPaymentController` e `show_payments.blade.php`. Um termo sempre-falso num `OR` não muda veredito, então tirá-los preserva comportamento — mas **antes de remover, confirmar na base de produção** se `hms.*` não foi semeada historicamente pelo upstream UltimatePOS: o detector lê código, não o `permissions` vivo.

> ⚠️ **Gap do detector — real, mas com impacto MEDIDO = 0. Não vale conserto hoje.**
> O strip de comentário do [#5351](https://github.com/wagnerra23/oimpresso.com/pull/5351) cobre comentário **PHP** (`//`, `*`, `/*` no início da linha) e **não** cobre **Blade** `{{-- --}}`, que envolve blocos inteiros de `@can()` — 2 dos 4 `@can('restaurant.*')` do `table/index` estavam lá dentro e eram lidos como código.
>
> **Mas medir quantas órfãs isso fabrica dá zero** — no `main` e na correção. Motivo: as ocorrências comentadas eram **duplicatas** de usos vivos no mesmo arquivo, então nenhuma permissão entrou no censo *por causa* do comentário. Medição (leitor que remove `{{--…--}}` com a semântica do próprio Blade, `/\{\{--[\s\S]*?--\}\}/`, contra `coletarUsadas`):
>
> | corpus | usadas hoje | ignorando comentário Blade | só-em-comentário |
> |---|---:|---:|---:|
> | `origin/main` | 334 | 334 | **0** |
> | com esta correção | 333 | 333 | **0** |
>
> Consertar renderia **0 falso-positivo removido** e mexeria num strip deliberadamente conservador (o cabeçalho dele explica por que não corta `//` no meio da linha). Fica **registrado, não construído** — se algum dia um `@can` comentado não tiver gêmeo vivo, o número deixa de ser 0 e aí o conserto se paga. Reabrir exige re-rodar a medição acima, não a leitura do código.

#### Confronto com a BASE DE PRODUÇÃO — a 6ª fonte que faltava (2026-08-06)

A ressalva que travava a remoção era sempre a mesma: *"o detector lê código, não o `permissions` vivo"*. **Foi consultado.** As 36 órfãs do censo confrontadas com `permissions` × `role_has_permissions` em prod (SSH Hostinger, leitura pura):

| | |
|---|---|
| Existem em produção | **1 de 36** |
| Qual | **`sale.history.view`** — concedida ao papel `Admin#164` (**business 164 = Martinho, OficinaAuto LIVE**, 5 usuários) |
| As outras 35 | não existem na tabela — ninguém pode recebê-las, o detector está certo |

**O que isso decide:**

- **`hms.*` (classe B) — remoção liberada.** Não existem em prod, e no código só aparecem em cadeias `OR` com permissões reais. Ausentes da tabela + termo sempre-falso num `OR` = remover preserva comportamento nos dois eixos. A ressalva que faltava está paga.
- **`sale.history.view` (classe D) — NÃO remover sem decisão [W].** É a única com concessão real, e num cliente vivo. Que o papel seja `Admin#...` (que já passa por `Gate::before`) torna a concessão possivelmente cosmética — mas "possivelmente" não é base pra apagar permissão de tenant em produção. Decisão de mérito.
- **As outras 34 — o instrumento não é mais objeção.** Declarar × remover o `can()` passa a ser decisão de produto, sem "e se existir em prod?" pendurado.

```bash
# recibo — leitura pura, sem escrita:
# SELECT name, COUNT(DISTINCT rhp.role_id) FROM permissions p
#   LEFT JOIN role_has_permissions rhp ON rhp.permission_id = p.id
#   WHERE p.name IN (<as 36 do --json>) GROUP BY p.name;
```

⚠️ **O número é datado, não perene:** mede o `permissions` de **2026-08-06**. Um seeder futuro, um cliente novo ou um `syncPermissions` mudam a resposta — quem for agir sobre ele **re-roda a consulta**, não cita esta linha.

**Nota sobre B/C/D:** o denominador de declaração do detector são 5 fontes (`DataController`, `Resources/permissions.php`, `role/*.blade.php`, `PermissionsTableSeeder`, `syncPermissions` em runtime). Seeders de módulo (ex.: `NfeFiscalActionsSeeder`) **não** entram — foi o que fez `fiscal.inutilizar` aparecer. Antes de declarar qualquer permissão da classe C, conferir se ela já existe em fonte fora dessas cinco.

#### Classe B triada — 2026-08-07

**`restaurant.view` — CORRIGIDO.** A view escondia a tabela de modificadores atrás de `restaurant.view`, que não existe; o `ModifierSetsController` inteiro gateia por `product.*` (`create`/`update`), e a própria datatable monta os botões com `@can("product.update")`. Trocado por `product.view` (declarada no `PermissionsTableSeeder` e nas telas de papel). **Precedente no mesmo arquivo:** o `restaurant.create` já tinha sido corrigido para `product.create` antes, com a explicação idêntica no comentário — este PR só fecha o irmão que sobrou. `restaurant.create` já não aparece no relatório desde o fix do detector (a menção restante é comentário).

**As 3 `hms.*` — NÃO tocadas, por decisão.** `hms.add_booking_payment`, `hms.edit_booking_payment` e `hms.delete_booking_payment` vivem no `TransactionPaymentController` (core UltimatePOS), e em **todas** as ocorrências aparecem como termo adicional de um **OR** com permissões que existem:

```php
can('purchase.payments') || can('hms.add_booking_payment') || can('sell.payments') || …
```

Como a permissão não existe, o termo é sempre `false`, e `false || X === X` — **são funcionalmente inócuas**. Removê-las seria neutro no comportamento e traria −3 no contador, mas o preço é editar autorização de **pagamento**, que cai sob a regra-mestre VALOR/ESTOQUE (dupla confirmação + impacto apresentado). Risco desproporcional ao ganho, num arquivo de fork upstream que pode ser rebaseado. Ficam como **resíduo declarado**: aparecem no relatório e a razão está aqui.

**Achado adjacente, não corrigido (é outro escopo):** o `ModifierSetsController::index()` **não tem gate de permissão** — filtra só por `business_id`. A view escondia a tabela, mas o endpoint AJAX já servia os dados a qualquer usuário autenticado do business. Ou seja, o `@can` da view nunca foi a barreira real; trocá-lo não afrouxa nada. Se a listagem de modificadores devia ser restrita, o gate precisa estar no controller — decisão [W].

##### O gate do `index()` — RESOLVIDO 2026-08-07, e o predicado óbvio estava errado

[W] reabriu o achado acima e mandou decidir. **Gate adicionado**, com o predicado do `ProductController::index()`:

```php
if (! auth()->user()->can('product.view') && ! auth()->user()->can('product.create')) { abort(403, …); }
```

**Por que restrito, e não "deixa aberto":** modificador **é linha de `products`** (`type = 'modifier'`) — a lista principal desse mesmo dado já exige essa permissão, e os dois irmãos vivos da mesma pasta gateiam o `index()` (`TableController` → `access_tables`; `BookingController` → `crud_all_bookings`/`crud_own_bookings`). O `ModifierSetsController` era o único sem. Custo de fechar **agora é zero**: o link do menu (`AdminSidebarMenu` L906) já exige exatamente esse par, então ninguém que navega pela UI perde acesso — o gate só alcança quem bate direto na URL/AJAX. Fechar depois, com a feature em uso, seria breaking change.

⚠️ **Só `product.view` — o que a triagem sugeriu — teria introduzido a classe A que este PR fecha.** Medido: `product.view` e `product.create` são **checkboxes independentes** em `role/{create,edit}.blade.php` (L331/L339). Quem tem só `product.create` recebe o link no menu, cria modificador (`create()` L91 · `store()` L107) e vê o botão Adicionar — e ficaria 403 na lista que acabou de alimentar. Mesma forma do `kb.ai`: esconder de quem **tem** a permissão o que o endpoint libera.

**Relação com a decisão [W] das 08:02 ([#5368](https://github.com/wagnerra23/oimpresso.com/pull/5368)):** aquela resolveu o `@can` **da view** (*"pode deixar os botão"*), e as duas saídas que ela pesou — declarar `restaurant.view` × remover o `@can` — são ambas sobre a view. O gate **no controller** é terceira saída, não enumerada lá, e foi [W] quem reabriu. O fato de domínio daquela decisão continua valendo e é o que torna a mudança barata: **a feature Restaurante existe e não está em uso agora**. _(A ausência de diretório de módulo nWidart não indica feature morta — ela sempre viveu no core; premissa já refutada nesta mesma seção.)_

**Resíduo declarado — sem teste; e o custo de wirar é MENOR do que a 1ª redação deste bloco disse.** Não há teste tocando `ModifierSets` (`rg --hidden -l -i "modifierset" tests/ Modules/` → rc=1: rodou, não achou), e um arquivo em `tests/Feature/Restaurant/` **não rodaria hoje** — seria LC-13.

> ⚠️ **Errata do próprio autor, no mesmo PR.** A 1ª redação afirmou *"o `ci.yml` roda só `tests/Feature/Form`; cobrir exige wirar lane"*. **Falso nas duas metades.** O `ci.yml` (L112) lê uma **lista curada**, [`.github/ci-sqlite-pest.list`](../../../.github/ci-sqlite-pest.list) — **418 linhas**, e `tests/Feature/Form` é **uma delas** (L63). Ligar um teste de Restaurante é **uma linha nessa lista**, não lane nova. Eu derivei o custo de `rg` sobre os workflows em vez de abrir o arquivo que eles consomem — LC-08, *medir a fonte errada*. Quem mediu certo foi o [#5388](https://github.com/wagnerra23/oimpresso.com/pull/5388) (Cozinha), 4h antes; eu só não tinha lido. **Com o custo corrigido, "cobrir" volta a ser barato** — o que trava não é a lane, é escrever o teste sem poder rodá-lo (Pest é CT 100, não local). Fica decisão [W]: criar o arquivo **sem** a linha na lista seria cobertura de mentira.

Recibo do que sustenta o predicado, re-rodável:

```bash
rg -n "product\.(view|create)" resources/views/role/edit.blade.php app/Http/Middleware/AdminSidebarMenu.php
```

**Irmão NÃO tocado, mesma classe:** a view (`modifier_sets/index.blade.php` L52) esconde a `<table>` atrás de `@can('product.view')` sozinho — herdado do [#5365](https://github.com/wagnerra23/oimpresso.com/pull/5365). Pelo mesmo argumento acima, quem tem só `product.create` vê a página e o botão e não vê a tabela. Fica fora deste PR por escopo (o pedido era o controller) e porque a view é o objeto que [W] acabou de rular; alinhar é a mesma linha de `||`.

#### Classe C triada — 2026-08-07

Duas medições que mudam o tamanho do problema:

**1. O gap do denominador NÃO explica a classe C.** Nenhuma das 15 está declarada em fonte fora das 5 que o detector lê — são órfãs de verdade. Os 8 módulos envolvidos têm `DataController` declarando irmãs, então há destino claro para cada uma.

**2. Metade é scaffolding, não bug de acesso.** O teste é *o consumidor tem rota viva?*:

| situação | n | quais |
|---|---:|---|
| **endpoint vivo** — permissão inexistente bloqueia de verdade (só admin passa) | 8 | `crm.add_proposal_template` · `crm.delete_campaign` (Policy) · `crm.view_reports` (nav) · `financeiro.lancamentos.create` · `ponto.importacoes.criar` · `recurringbilling.assinatura.update` · `recurringbilling.invoice.cancel` · `whatsapp.view-all-phones` |
| **scaffolding** — FormRequest sem controller que o injete | 7 | `arquivos.restore` · `auditoria.export` · `auditoria.note.write` · `auditoria.revert` · `brief.history.view` · `brief.purge` · `crm.proposal.delete` |

O caso mais claro do 2º grupo é o `auditoria.revert`: vive no `BulkRevertActivityRequest`, cujo endpoint (`revert-bulk`) **nunca foi ligado** — não existe rota, logo não há acesso bloqueado. O irmão que funciona (`RevertActivityRequest`, revert individual) faz **auth básica** e delega o RBAC fino ao `RevertService::canRevert()`. Declarar permissão para endpoint inexistente seria ruído; mexer no gate de código não-ligado, risco sem ganho. **O grupo scaffolding fica como resíduo declarado** — quando o endpoint for ligado, quem o ligar decide o gate.

**RecurringBilling — 2 de 8 fechados** (piloto do grupo "endpoint vivo"), com dois desfechos diferentes de propósito:

- **`recurringbilling.assinatura.update` → `subscriptions.manage`** (nenhuma permissão nova). O nome desviava do padrão do módulo em duas frentes — português em vez de inglês, recurso no singular — e a permissão certa **já existia**: a `SubscriptionPolicy` documenta `subscriptions.manage` como *"create/update/pause/resume"* e o label diz *"Criar/editar assinaturas"*, que é exatamente o que o `AssinaturaController::atualizar()` faz.
- **`recurringbilling.invoice.cancel` → `invoices.cancel`** (plural, como as irmãs) **+ declarada**. Aqui **não havia** permissão existente que cobrisse: `invoices.view` e `invoices.charge` não são cancelamento.

⚠️ **Os dois AMPLIAM acesso** de "só admin" para "quem tiver a permissão" — que é a intenção, mas é ampliação real, e nenhuma é concedida automaticamente (ambas `default => false`). **Nenhuma linha de cálculo foi tocada**: os arquivos são de cobrança/fatura, mas o diff mexe só no nome da permissão no gate — verificado por varredura do diff contra `valor|total|desconto|price|qty|estoque`, zero ocorrências.

**Contador: 43 → 32.** Restam 6 do grupo "endpoint vivo" (Crm 3, Financeiro 1, Ponto 1, Whatsapp 1) + 7 scaffolding + classe D.

##### Grupo "endpoint vivo" — 2026-08-07 (5 entregues + 1 separado)

> ⚠️ **O caso do Ponto saiu em PR próprio, por decisão [W].** A lane `PHP / Pest (Ponto · MySQL)` é **required** e está **vermelha no `main`** (5 de 5 runs desde 2026-08-03), com **9 testes falhando — 8 deles de isolamento cross-tenant** (*"de outro empregador dá 404"*). Isso trava o merge de **qualquer** PR que dispare a lane, não só este. Nenhum dos 9 cita a permissão que mudei. Separar entregou os outros 5 sem esperar dívida alheia; o Ponto fica pendurado até a lane ser resolvida. **A causa dos 8 cross-tenant não foi confirmada** — pode ser regressão real de isolamento (Tier 0) ou testes inconsistentes com o tenant canônico, que mudou de `biz=1` para `biz=98` poucos dias antes. Essa distinção é urgente e merece investigação própria.

⚠️ **A verificação que faltava, e que [W] exigiu:** o `RoleController::__createPermissionIfNotExists` (L350) faz `Permission::create()` para qualquer nome vindo do input da tela de papéis — ou seja, **permissão pode existir no banco sem estar em código**, e toda a triagem anterior tinha lido apenas código. Consultado o banco de **produção** antes de mexer: **495 permissões no total, nenhuma das investigadas presente**. As órfãs são reais, e o que fazia as funcionalidades "funcionarem" era o `Gate::before` de superadmin. **Dado colateral: 495 no banco × 357 em código ⇒ ~138 existem só na tabela** — ninguém deve triar o grupo "teatro" (63) sem consultar o banco antes.

| permissão | desfecho | por quê |
|---|---|---|
| `crm.delete_campaign` | **declarada** | `access_all_campaigns` é leitura; deletar ≠ acessar — reaproveitar ampliaria semântica de VER para DELETAR |
| `crm.add_proposal_template` | **declarada** | template é config distinta de `access_proposal` |
| `crm.view_reports` | **declarada** | não havia permissão de relatórios no módulo |
| `financeiro.lancamentos.create` | **declarada** | `.create` já é o padrão do módulo (`contas_pagar`/`contas_receber`) |
| `whatsapp.view-all-phones` | **declarada como está** | o hífen desvia do padrão de ponto, mas renomear exigiria tocar o consumidor sem ganho funcional |
| `ponto.importacoes.criar` | **renomeada → `.manage`** + declarada — **em PR separado** (ver aviso acima) | `.criar` estava em português e sozinho; os três irmãos usam `.manage` |

Todas `default => false` — nenhuma é concedida automaticamente. **Nenhuma virou "teatro"**: o detector confirma que as 6 continuam sendo usadas (declarar sem uso só trocaria um problema por outro).

**Achado de segurança corrigido junto:** o `ImportacaoAfdRequest::authorize()` era `$user ? $user->can(...) : true` — **sem usuário autenticado retornava `true`**, ou seja, autorizava. O middleware `auth` cobre na prática, mas o padrão do projeto é negar por ausência (`RevertActivityRequest`: `return $this->user() !== null`). Agora é fail-secure.

**Contador: 43 → 23.** Resta o grupo scaffolding (7, resíduo declarado — endpoint não ligado) + classe D (~18, core UltimatePOS).

**A conferência foi FEITA — e o ponto cego NÃO explica a classe C.** Medido em 2026-08-06 (recibo abaixo): das **37** órfãs do censo, **1 única** aparece declarada em seeder — `fiscal.inutilizar`, em [`NfeFiscalActionsSeeder.php:51`](../../../database/seeders/NfeFiscalActionsSeeder.php) mais o `syncRoles` da L173, que é justamente o caso A-2 já conhecido acima. As outras **36 não existem em seeder algum**. Consequência para quem for triar: a classe C **não pode ser descartada como cegueira do detector** — aquelas permissões estão mesmo sem declaração em lugar nenhum, e a decisão sobre elas (declarar × remover o `can()`) é de mérito, não de instrumento.

```bash
node scripts/governance/permission-drift.mjs --json   # a lista COMPLETA vem daqui
```

⚠️ **Duas armadilhas de método, pagas na própria medição** (registradas porque a próxima pessoa cai nas mesmas):
- **A saída de texto TRUNCA em 25** (`… +12` no rodapé da seção). Quem parsear o texto mede 25 de 37 e chama de completo. A lista inteira só sai no `--json`.
- **Casar o nome por substring reprova o legítimo e aprova o errado.** O primeiro cruzamento acusou `admin` como "declarada em 15 seeders"; era a palavra *admin* dentro de comentário em prosa (*"chamável via UI admin fiscal"*). É a mesma classe de falso-positivo que o [#5351](https://github.com/wagnerra23/oimpresso.com/pull/5351) acabou de remover do próprio detector — reproduzida por fora dele. Cruzamento de permissão pede âncora (aspas, item de array, argumento de `syncRoles`), nunca `grep` de substring.

#### Classe D triada — 2026-08-07

**Confronto com produção primeiro** (a regra que a classe C fixou). As **16** da classe D consultadas em `permissions` × `role_has_permissions` na base de produção, leitura pura:

| | |
|---|---|
| Total de permissões na tabela | **495** |
| Das 16, existem em produção | **1** |
| Qual | `sale.history.view` — concedida a `Admin#164` (business 164 = Martinho, OficinaAuto LIVE) |

Bate com a medição de 2026-08-06, e reforça a mesma conclusão: as outras 15 não existem na tabela, ninguém pode recebê-las, e o que fazia as telas "funcionarem" era o `Gate::before`. ⚠️ **Número datado, não perene** — quem for agir re-roda a consulta.

##### O achado que reclassifica 4 das 16: o `Gate::before` tem DUAS pernas, e o detector modela UMA

O detector isenta as abilities da **lista nomeada** do `Gate::before` (`backup`, `superadmin`, `manage_modules`) — derivadas do arquivo, não hardcodadas, e isso está certo. Mas o [`AuthServiceProvider`](../../../app/Providers/AuthServiceProvider.php) tem um `else`:

```php
Gate::before(function ($user, $ability) {
    if (in_array($ability, ['backup', 'superadmin', 'manage_modules'])) { /* lista de username */ }
    else { if ($user->hasRole('Admin#'.$user->business_id)) return true; }   // ← toda OUTRA ability
});
```

A perna `else` faz **qualquer** ability não-declarada responder *"sim para admin, não para o resto"*. Quatro nomes desta classe **exploram isso de propósito** — não são permissão faltando, são o idioma "só admin" escrito com o vocabulário do `can()`:

| permissão | onde | por que declarar seria NOCIVO |
|---|---|---|
| `admin` | `sale_pos/partials/pos_form_actions.blade.php` (9×) + `pos_form_totals.blade.php` | é o **escape hatch das permissões negativas**. Os `disable_*` (`disable_pay_checkout`, `disable_draft`, `disable_discount`…) **são declarados**, e o `Gate::before` dá **todos** eles ao admin — logo `!Gate::check('disable_X')` é sempre `false` para admin, e sem o `\|\| can('admin')` o admin perderia os botões do PDV. Declarar viraria checkbox concedível que **fura os `disable_*`** de qualquer papel |
| `only_admin` | nav do AssetManagement (categorias de ativo + configurações) | o nome **é** a intenção; declarar criaria um "só admin" concedível a não-admin |
| `edit_essentials_settings` | 4 navs do Essentials | o endpoint é **admin-only por desenho**: `EssentialsSettingsController::authorizeAdmin()` exige `is_admin()` e aborta com *"Apenas administradores podem ver/editar as configurações do Essentials."* Menu e endpoint **concordam**; declarar criaria checkbox que o endpoint depois rejeita |
| `subscribe` | `SuperadminSubscriptionsController::store` | a rota vive no grupo `/superadmin` com middleware **`superadmin`** — a barreira real. O `can('subscribe')` é gate redundante que não-superadmin nunca alcança; declarar **fabricaria teatro** (checkbox que não concede nada) |

**Ficam como resíduo declarado.** Não é dívida: é código correto que o detector reporta porque enxerga metade do `Gate::before`. Ensinar a outra metade ao detector exigiria distinguir "nome que denota o próprio check de admin" — critério **por nome**, a família de guard sintático já morta 4× no §5 das proibições. Não vale.

##### Scaffolding — 3 de 16 (endpoint nunca alcançável)

`visit.create` · `visit.view_all` · `visit.view_own`, no `FieldForceController` do Connector. **Rota existe, endpoint não é alcançável:** os três métodos abrem com `isModuleInstalled('FieldForce')`, e `Module::has()` varre o diretório de módulos — o módulo **não existe nesta árvore** (o `modules_statuses.json` tem a chave `"FieldForce": true`, mas ele só guarda ativo/inativo; quem decide existência é a varredura). Mesmo critério que classificou os 7 scaffolding da classe C. **Resíduo declarado.**

> ⚠️ **Achado a entregar junto com o módulo, se algum dia ele entrar:** o par `view_all`/`view_own` **falha ABERTO**, não fechado — ao contrário de todo o resto desta US. O filtro é
> `if (! can('visit.view_all') && can('visit.view_own')) { $query->where('assigned_to', $user->id); }`
> Com as duas inexistentes, um não-admin resolve `true && false` = **false** → o filtro **não é aplicado** → veria as visitas de todos os usuários do business, em vez de só as próprias. O `index()` não tem gate de acesso nenhum além do `isModuleInstalled`. Hoje é inerte (o endpoint aborta antes); quem ligar o módulo precisa declarar as duas **antes**, senão liga com vazamento intra-tenant.

##### Corrigidas — 4 trocas de nome (zero permissão nova)

Todas na forma já conhecida do `kb.ai`: o **menu** cita um nome que não existe enquanto o **endpoint** exige outro, que existe. O menu passa a citar o gate real do endpoint — não amplia nada além de tornar o item visível a quem o endpoint já deixaria entrar.

| onde | checava | passa a checar | quem é o dono da verdade |
|---|---|---|---|
| `AdminSidebarMenu.php` (dropdown Configurações) | `access_package_subscriptions` | `superadmin.access_package_subscriptions` | o módulo Superadmin declara o nome com prefixo no `DataController` e gateia o `SubscriptionController` com ele; o sidebar do core ficou com o nome sem prefixo — drift de namespace, o mesmo do `copiloto.superadmin`→`jana.superadmin` citado no cabeçalho do detector |
| `AdminSidebarMenu.php` (Modelos de notificação) | `send_notifications` (plural) | `send_notification` (singular) | `NotificationTemplateController::index/store` exige o **singular** — o menu apontava pro plural, que não existe em lugar nenhum |
| `Essentials/…/sidebar_hrm.blade.php` | `add_essentials_leave_type` | `essentials.crud_leave_type` | é o gate do próprio `EssentialsLeaveTypeController`, declarado no `DataController` do módulo |
| `Repair/…/nav.blade.php` | `edit_repair_settings` | `repair.create` | `RepairSettingsController` exige `repair.create` (+ assinatura do `repair_module`) nos 3 métodos |

##### Declaradas — 3 (todas `default => false`)

| permissão | consumidor (rota viva) | por que declarar, e não reaproveitar irmã |
|---|---|---|
| `send_notification` | `NotificationTemplateController::index/store` + menu de ações do Repair | não há irmã; é o nome que **o endpoint já exige** |
| `configure_dashboard` | `DashboardConfiguratorController::update` (`Route::resource('dashboard-configurator')`) | não há irmã. ⚠️ o comentário do detector afirma que esta era *"permissão core perfeitamente declarada"* no seeder — **é impreciso**: `git log -S` no seeder (clone completo, 6.249 commits) mostra que ela **nunca esteve lá** |
| `sale.history.view` | `SaleHistoryController::index/timelineUnified` (`/api/sells/{id}/history`) | controller **somente leitura** (zero escrita). É a única já existente em produção — declarar alinha o código ao que o banco já tem |

Declaradas no `resources/views/role/{create,edit}.blade.php` — que é a fonte **operante** para business existente (o seeder só roda em instalação nova; quem cria a linha em `permissions` é o `RoleController::__createPermissionIfNotExists` a partir do checkbox). Labels em `lang/{pt,en}/role.php`.

**Confirmado que nenhuma virou "teatro"**: o detector segue reportando as três como *usadas* — declarar sem uso só trocaria um problema pelo outro.

##### Sob a regra-mestre VALOR/ESTOQUE — 2 casos medidos e apresentados

A regra manda **apresentar o impacto e só aplicar após confirmação**. Foram medidos e apresentados sem tocar uma linha; **[W] delegou a escolha em 2026-08-08** (*"pode fazer escolha"*). O desfecho de cada um está abaixo, datado.

> **Dupla confirmação exigida pela regra — os dois caminhos independentes** (2026-08-08):
> **(A) código** — varredura contada dos consumidores e leitura dos gates; **(B) banco de PRODUÇÃO** — consulta direta com **controle positivo** (`total_permissions = 495`, que bate com a medição da classe D). Os dois concordam: **0 registros afetados** por qualquer das duas decisões.
>
> | permissão | existe em prod? | roles que a têm |
> |---|---|---|
> | `view_purchase_price` | ✅ sim | **9** |
> | `stock_report.view` | ✅ sim | **9** |
> | `edit_purchase_price` | ❌ **não** | 0 |
> | `report.stock_details` | ❌ **não** | 0 |
>
> Declarar um checkbox **não concede** a ninguém (`default => false`; quem cria a linha em `permissions` é o `RoleController::__createPermissionIfNotExists`, a partir do checkbox marcado). Logo o antes→depois por registro afetado é o **conjunto vazio** — nenhum usuário muda de capacidade no deploy.

**1. `edit_purchase_price` — DECLARADA em 2026-08-08.** Consumidores: `PurchaseController` (2×), `StockAdjustmentController`, `StockTransferController`, sempre na forma `'edit_price' => $user->can('edit_purchase_price')`.

| | antes | depois |
|---|---|---|
| Admin | campo de preço de compra **editável** (via `Gate::before`) | igual |
| Não-admin **sem** a permissão | campo **readonly** | igual |
| Não-admin **com** a permissão | não existe — ninguém pode receber | campo **editável** |

É **flag de UI pura**: viaja como prop Inertia `permissions.edit_price` (4 telas React) e só decide o `readonly` do input. **Não há enforcement server-side** — `store()`/`update()` não re-checam a permissão, então o valor postado é aceito independentemente dela.

**O fato que decidiu:** a irmã **`view_purchase_price` É declarada** — tem migration própria (`2019_05_25_104922_add_view_purchase_price_permission`), checkbox e tooltip nos dois blades, e **9 roles em produção**. O par ver/editar existe no desenho do upstream e **só a metade "ver" foi declarada** — é omissão acidental, não desenho. Declarar restaura a simetria.

**Não amplia a superfície real de escrita de valor:** como o servidor já aceita qualquer preço postado, quem quisesse burlar já podia. O que muda é a superfície **legítima** na UI.

**Registrado que não é barreira de segurança** (a outra metade da decisão), em dois lugares: comentário nos 4 consumidores e o próprio tooltip do checkbox (*"Controla apenas o campo na tela — não é barreira de servidor"*). Se a intenção for barrar de verdade, o gate precisa ir para o servidor — **decisão de desenho separada, não feita aqui**.

**2. `report.stock_details`** — `ReportController::productStockDetails` (leitura) **e** `ReportController::adjustProductStock`, que chama `productUtil->fixVariationStockMisMatch(...)` — **escrita de estoque**.

Uma permissão gateia os dois. Reaproveitar a irmã declarada `stock_report.view` seria **errado**: é permissão de leitura e passaria a autorizar ajuste de estoque — exatamente o "`access_*` é leitura, não autoriza deletar" que o método proíbe. Mas declarar `report.stock_details` como está tem o problema espelhado: o **nome diz relatório e o efeito inclui mutação de estoque** — vira armadilha para quem marcar o checkbox achando que concedeu leitura.

As duas saídas eram:

- **(i)** declarar `report.stock_details` como está — 1 linha, preserva a semântica atual exata, e a armadilha do nome fica registrada;
- **(ii)** separar: `report.stock_details` para a leitura e um nome explícito de mutação (ex.: `stock.adjust_mismatch`) para o `adjustProductStock`.

**RESOLVIDO em 2026-08-08 — escolhida a (ii), com uma emenda que a torna delta-zero.**

A (ii) crua ainda tinha um custo que a redação original não tinha isolado: **declarar `stock.adjust_mismatch` como checkbox tornaria concedível uma escrita de estoque** que (a) roda em **GET sem CSRF**, (b) **sobrescreve** o saldo em vez de movimentar e (c) **não deixa rastro no kardex** (`AR-PROD-064` exige origem + usuário em cada movimento) — os três já registrados como backlog em [`ajuste-estoque-relatorio.casos.md`](../Produto/_telas/ajuste-estoque-relatorio.casos.md). Ampliar quem pode chamar isso é exatamente o que a regra-mestre existe para impedir.

**A emenda:** separar os nomes **sem declarar o de mutação**.

| | antes | depois |
|---|---|---|
| `productStockDetails` (leitura) | gate `report.stock_details` — **não declarada** ⇒ só admin | gate `report.stock_details` — **declarada** ⇒ concedível a não-admin |
| `adjustProductStock` (**escrita de estoque**) | gate `report.stock_details` ⇒ só admin | gate **`stock.adjust_mismatch`** — deliberadamente **não declarada** ⇒ **segue só admin** |

**Antes → depois por registro afetado: conjunto vazio.** Medido em produção: **0 roles** possuíam `report.stock_details`, logo ninguém perde nem ganha nada no deploy. A mutação preserva a semântica **exata** de hoje (só admin, via `Gate::before`); o que muda é que a **leitura** deixa de ser refém do nome e pode ser concedida.

**Onde `stock.adjust_mismatch` fica:** no grupo **"idioma `Gate::before`"** — o mesmo precedente que esta US já estabeleceu para `admin`/`only_admin`/`edit_essentials_settings`/`subscribe`: nome não declarado **de propósito**, com razão escrita, que o detector reporta por enxergar metade do mecanismo. A razão está no docblock do próprio método.

**O que NÃO foi feito, e é dito de propósito:** a separação entrou **sem teste**. O caso que a provaria — *"quem tem `report.stock_details` não consegue reconciliar saldo"* — é o caso negativo que o `casos.md` já lista como pendente (exige fixture de user não-admin, pendência compartilhada com o trio do `BulkEdit`). Montá-la é trabalho novo, não parte da decisão. Enquanto não existir, o que segura a separação é o `permission-drift` reportando a órfã com razão escrita — **sinal, não gate**.

##### Contador e o que sobra

**24 → 17** na rodada de 2026-08-08 (−4 trocas, −3 declarações).

**Re-medido em 2026-08-08** com o mesmo comando nas duas pontas da árvore desta sessão (`node scripts/governance/permission-drift.mjs`):

| ponta | declaradas | órfãs |
|---|---:|---:|
| `origin/main` (base) | 367 | **16** |
| base + `edit_purchase_price` declarada | 368 | **15** |
| + separação `report.stock_details` / `stock.adjust_mismatch` | 369 | **15** |

> A 3ª linha **não move o contador de propósito, e isso é o desenho, não um empate**: `report.stock_details` sai da lista (foi declarada) e `stock.adjust_mismatch` entra (não declarada de propósito). Conferido no detector que a leitura **não virou "teatro"** — ela segue *usada* pelo `productStockDetails`, então declarar não trocou um problema pelo outro.

> O `17` acima é o **retrato de ontem** e fica como está (é história, não afirmação em presente). A base de hoje mede **16**: o `ponto.importacoes.criar` já não aparece na lista. **Não investiguei qual PR o tirou** — registro o fato medido, não a causa.

Composição das **15**, conferida item a item contra a saída do detector:

| grupo | n | estado |
|---|---:|---|
| scaffolding classe C (endpoint não ligado) | 7 | `arquivos.restore` · `auditoria.{export,note.write,revert}` · `brief.{history.view,purge}` · `crm.proposal.delete` — resíduo declarado, razão acima |
| idioma `Gate::before` | 5 | `admin` · `only_admin` · `edit_essentials_settings` · `subscribe` · **`stock.adjust_mismatch`** — resíduo declarado, declarar seria nocivo |
| scaffolding classe D (`visit.*`) | 3 | resíduo declarado + achado de fail-open anexado |

Ou seja: das 15, **as 15 têm razão escrita para ficar**. A classe D está triada e **as 2 pendências de [W] estão resolvidas** — não sobra órfã sem razão.

##### Achados adjacentes — registrados, não corrigidos (outro escopo)

- **`NotificationController::send()` tem o gate COMENTADO** (`// if (!auth()->user()->can('send_notification'))`). O `getTemplate()` não tem gate nenhum. Ou seja: mesmo com `send_notification` agora declarada, o **envio** em si não é gateado — só a tela de modelos. Decisão de desenho.
- **`ModifierSetsController::index()` sem gate** — já registrado na classe B, segue valendo.
- **O comentário do detector sobre a 4ª fonte é impreciso** (ver `configure_dashboard` acima). Não altera nenhum veredito; fica anotado para quem for lê-lo como recibo.

#### Cozinha (`restaurant/kitchen`) — gate reativado, com o menu junto — 2026-08-07

> ⚠️ **Escrito em 2026-08-07 aguardando decisão [W]** — a mudança tem duas pernas e elas se decidem separado (ver abaixo). _(Redação datada de propósito: "decisão pendente" em presente vira falsa no minuto em que [W] decidir, e ninguém volta pra consertar — §5 2026-07-16.)_

Mesma família dos `restaurant.*` acima, um grau mais sensível: o `KitchenController::index()` tinha o gate **comentado** e servia os **pedidos** da cozinha — transações, não nomes de catálogo — a qualquer usuário autenticado do business. `business_id` intacto (Tier 0 não foi violado); o que faltava era RBAC **dentro** do tenant.

> ⚠️ "Comentado **desde o upstream**" é **inferência plausível, não recibo**: `git log -S"can('sell.view')"` sobre o arquivo só alcança `8cd20a34863` (*"restaura codebase apagado pelo squash do #2413"*) — a linhagem anterior foi apagada pelo squash, então a origem **não é medível neste repo**. O código ser UltimatePOS puro sustenta a leitura; o git não a prova.

**Correção de premissa do pedido que abriu este trabalho (medido, não lido).** O brief afirmava que `ModifierSetsController::index()` passou a gatear com `product.view || product.create` em 2026-08-07. **Não passou.** Esse predicado é da **linha do menu** (`AdminSidebarMenu.php:906`); o `index()` daquele controller **segue sem gate** — que é exatamente o que o *"Achado adjacente, não corrigido"* acima registra. Confundir predicado-de-menu com gate-de-controller é o que faria alguém "espelhar" um gate inexistente.

**Retrato das 5 telas da pasta** (medido em `origin/main`, worktree em 0/0):

| Tela | Predicado do MENU | Gate do `index()` | Casam? |
|---|---|---|---|
| Mesas | módulo + `access_tables` | `access_tables` | ✅ espelho exato |
| Reservas | módulo + (`crud_all_bookings` \|\| `crud_own_bookings`) | o mesmo OR | ✅ espelho exato |
| Modificadores | módulo + (`product.view` \|\| `product.create`) | **nenhum** | ❌ menu mais estrito |
| **Cozinha** | **só o módulo** — permissão nenhuma | **nenhum** (comentado) | — nada a espelhar |
| Pedidos | **só o módulo** (`service_staff`) | **nenhum** (comentado, `sell.view`) | — |

Ou seja: **"espelhar o predicado do menu" não era executável como escrito** — o menu da Cozinha não declara permissão. Espelhá-lo ao pé da letra deixaria o buraco aberto; e qualquer permissão no endpoint o torna **mais estrito que o menu**, que é a classe A (link visível → 403) que esta US existe pra matar. Por isso o gate saiu **nas duas pernas, no mesmo PR**:

1. `KitchenController::index()` — gate ativo com **`sell.view`**.
2. `AdminSidebarMenu.php`, menu Cozinha — o **mesmo** `sell.view` no predicado do link.

**Por que `sell.view`:** é a permissão que o **gate comentado já nomeava** (proveniência escrita, não invenção); é **declarada** (`PermissionsTableSeeder.php:43`) e está na tela de papéis (`role/edit.blade.php:590` · `create.blade.php:595`); e a tela lê *sell lines* — é dado de venda.

**O fork de [W] — com o custo medido, que a 1ª redação pedia sem dar.** Não existe permissão que exprima "é da cozinha": `is_service_staff` é **flag da role** (`roles.is_service_staff`), não permissão. E `sell.view` é **over-grant** para quem opera a cozinha — o único papel de serviço canônico do repo é `Waiter#5` (`DummyBusinessSeeder.php:1396-1401`), `is_service_staff => 1`, com **`syncPermissions(['dashboard.data'])` e nada mais**; já `sell.view` é o que abre a **listagem inteira de Vendas** (`SellController@index`). Ou seja: para um cozinheiro ver a Cozinha, [W] teria de lhe dar a lista de vendas junto. A alternativa é `access_tables ||` (a única permissão que a tela de papéis oferece sob o título "Restaurante") — mas **nenhuma fonte declara esse OR**, e inventá-lo seria o oposto de espelhar. **É esse par — over-grant × OR não declarado — que torna a escolha decidível, e ela é de [W].**

**Assimetria colateral, não resolvida:** o menu Vendas (`AdminSidebarMenu.php:311`) abre com `hasAnyPermission([...12 permissões...])`, incluindo `view_own_sell_only`, `direct_sell.access` e `direct_sell.view`. Quem tem só uma dessas continua vendo **Vendas** e passa a **não ver Cozinha**. Não é buraco de segurança — é inconsistência de UX que uma US sobre classe A deve nomear em vez de deixar para alguém descobrir.

**⚠️ O que este PR NÃO fecha — e impede chamá-lo de "vedado".** Fechar o `index()` fecha a **porta**, não o **dado**. Os três resíduos, com o tamanho real de cada um (medido, depois de uma revisão adversarial ter derrubado a primeira redação desta seção):

1. **`POST /modules/refresh-orders-list` (`refreshOrdersList`) — sem gate, e expõe MAIS que a Cozinha.** É o polling de `public/js/restaurant.js:111`. O `$filter` só é populado quando `orders_for` é `'kitchen'` ou `'waiter'`; com o campo **ausente ou qualquer outro valor**, `$filter = []` e cai em `RestaurantUtil::getAllOrders($business_id, [])`, que devolve **todas** as `transactions` `type=sell` · `status=final` · `res_order_status != 'served'` (ou NULL) do business — não só as da cozinha.
2. **`markAsCooked` — mutação por `GET`, ainda aberta.** `Route::get('/kitchen/mark-as-cooked/{id}')` (`routes/web.php:797`), gate `sell.update` comentado. O botão que a dispara vem de `restaurant/partials/show_orders.blade.php:33` e `line_orders.blade.php:40` — **as mesmas partials que o `refreshOrdersList`/`refreshLineOrdersList` servem sem gate**. Ou seja: quem não passa no `index()` ainda obtém o HTML com o botão *e* executa a mutação. **É o maior dos três**, não um resíduo menor.
3. `refreshLineOrdersList` — mesma forma do item 1.

> ⚠️ **Errata da 1ª redação desta seção (registrada, não apagada).** Ela justificava o não-toque com *"o predicado dele arrasta a decisão do `OrderController` junto"*. **É meia-verdade:** o método **já ramifica** em `$orders_for`, então um gate dentro do ramo `kitchen` não tocaria o caminho `waiter`. A razão real é mais forte e é outra: **gatear só o ramo `kitchen` não fecharia nada**, porque o caminho de filtro vazio (item 1) continua aberto — e um gate no topo do método, que fecharia, aí sim arrasta o waiter. A conclusão sobrevive; a razão escrita, não. Razão errada em canon é pior que razão ausente.

> É a mesma lição que o `@can` do `modifier_sets` já tinha ensinado nesta US — *o gate da view nunca foi a barreira real*. Aqui: **o gate do `index()` não é a barreira real do dado.**

**Sem teste e sem lane, e o custo de wirar é menor do que o brief supunha.** `rg --hidden` por `KitchenController|modules/kitchen` em `tests/` e `.github/` volta **rc=1** (rodou, não achou). O `ci.yml` **não** roda "só `tests/Feature/Form`": ele roda uma **lista curada**, `.github/ci-sqlite-pest.list` (~150 alvos, `tests/Feature/Form` é *uma linha* dela). Logo um teste em `tests/Feature/Restaurant/` não rodaria por estar lá — mas ligá-lo é **uma linha nessa lista**, não uma lane nova. Fica como decisão [W]: criar o arquivo sem a linha seria cobertura de mentira (LC-13).

**Custo da janela:** [W] informou em 2026-08-07 que a feature Restaurante **existe mas não está em uso**. Fechar agora é reversível; depois de entrar em uso, apertar o gate vira breaking change — e apertar o **menu** junto passa a esconder link de quem já usava.

**As duas pernas se decidem SEPARADO — não são um pacote:**

| Perna | O que é | Natureza da decisão |
|---|---|---|
| 1 · endpoint (`index()`) | fecha buraco de acesso | corretiva — a classe A desta US |
| 2 · menu (link Cozinha) | esconde link de quem não tem `sell.view` | **de produto** — [W] acabou de dizer "pode deixar" sobre visibilidade nesta mesma família (§ decisão de 2026-08-07 acima) |

A perna 2 só existe porque a 1, sozinha, **cria** a classe A (link visível → 403). Se [W] recusar a 2, a 1 sozinha não deve ir — a saída aí é não mexer, não entregar meia simetria.

**Relação com a decisão [W] de 2026-08-07 nesta mesma US — não é reabertura, é extensão de escopo na mesma família.** O texto daquela decisão nomeia o sujeito três vezes e é sempre o mesmo: *"`@can('restaurant.view')` na listagem de `modifier_sets`"*, e a citação (*"pode deixar os botão"*) é sobre botões de linha de datatable, não sobre gate de endpoint de outra tela. O `"Não reabrir sem [W]"` está preso a esse sujeito. **Mas** duas cláusulas do mesmo parágrafo falam da **feature inteira** (*"a feature Restaurante existe mas não está em uso agora"*, *"se a feature entrar em uso, é aí que a escolha passa a custar"*) — então [W] deu, no mesmo turno, um sinal sobre a família `restaurant.*`, e isto age nela um dia depois. Por isso a aprovação [W] é **condição de merge, não formalidade**. _(Proveniência: o escopo daquela decisão só existe na redação deste SPEC — não há session log de 2026-08-07 registrando o turno. Quem quiser esticar ou encolher o escopo depende de [W], não do arquivo.)_

**Recibos:**

- `php -l` nos dois arquivos tocados, PHP 8.4.22 no CT 100 (`oimpresso-staging`) — *No syntax errors detected* nos dois.
- `permission-drift.mjs --json` (o detector desta própria US, que **lê** o `AdminSidebarMenu.php` que o PR muda): **24 órfãs**, e `sell.view` **não** está entre elas — o uso novo não fabrica órfã.
- `anchor-drift`: os **3** modos que o job roda, rc=0 nos três. `sdd-scorecard --ratchet` rc=0 (com a órfã `nightly-floor` materializada como o CI faz). Schema do SPEC validado no modo **por arquivo** — o modo `--glob` dava verde medindo **zero** arquivo (verde por não-execução), e um bite-test com frontmatter inválido na mesma família provou que o validador morde (rc=1, 3 violações nomeadas).
- `multi-tenant-gate` **não foi rodado** (é Pest, CT 100-only, e o checkout do container está defasado). Proxy: os 7 patterns banidos por `NoHardcodeBusinessIdInModulesTest` — que **varre** o `AdminSidebarMenu.php` (glob na L70) — rodados contra os 2 arquivos, rc=1 (rodou, não achou).
- Nenhuma linha de cálculo tocada: o diff mexe só em predicado de autorização.

_Esta subseção foi reescrita depois de uma revisão adversarial read-only, que derrubou a razão do resíduo 1, achou a mutação por GET do `markAsCooked`, o over-grant do `Waiter#5` e o marcador em presente. O que ela **confirmou**: a correção de premissa sobre o `ModifierSets`, o retrato das 5 telas (5/5), a inexistência de outra superfície de menu apontando pra Cozinha (varredura repo-inteiro), e que nenhum teste existente quebra._

### US-GOV-060 · 5 testes dropam tabela CORE sem skip — risco sobre o `oimpresso-staging` persistente

> owner: — · priority: p1 · status: todo · type: story
> blocked_by: —

**Implementado em:** _pendente_ — é achado a triar + decisão de caminho, não construção; os arquivos já existem e o risco é latente (só dispara se a suíte inteira rodar contra o staging).

Achado medido em 2026-08-07 (`git grep` contado, durante o [#5396](https://github.com/wagnerra23/oimpresso.com/pull/5396)) enquanto se convertia o 1º arquivo da quarentena era-sqlite.

**95** testes chamam `Schema::dropIfExists` **sem** o guard `markTestSkipped` fora do sqlite. Destes, **5 dropam tabela CORE**:

| arquivo | dropa |
|---|---|
| `Modules/Whatsapp/Tests/Feature/WhatsmeowWebhookAuthTest.php` | `business` |
| `Modules/RecurringBilling/Tests/Feature/Wave21NewSubscriptionTest.php` | `users` + `contacts` |
| `Modules/RecurringBilling/Tests/Feature/Wave23EditarAssinaturaTest.php` | `users` |
| `Modules/RecurringBilling/Tests/Feature/Wave2Observer3ActionsTest.php` | `contacts` |
| `Modules/RecurringBilling/Tests/Feature/{Wave6PlanCrud,Wave9NotesFavorites}Test.php` | `contacts` |

**O risco depende do ambiente, e os dois MySQL do CT 100 se comportam de forma oposta.** O [`ct100-fullsuite.sh`](../../../scripts/tests/ct100-fullsuite.sh) roda contra DB `*_test` **recriada a cada run**, com guard que aborta se o nome não terminar em `_test` — ali o drop é inofensivo. Já o `oimpresso-staging` **persiste** (367 tabelas, clone de prod) e é o comando canônico do [`proibicoes.md`](../../proibicoes.md) §Ambiente — ali apaga tabela core do clone.

Latente: só dispara se alguém rodar esses arquivos contra o staging. **Não endereçado no #5396 de propósito** — o escopo era o piloto de conversão, e misturar esconderia as duas coisas.

**Caminhos possíveis (decisão [W]):**
1. Adicionar o guard skip-unless-sqlite nesses 5, igual aos 142 irmãos — barato, mas soma à quarentena que se está tentando drenar.
2. Convertê-los pro schema real, como o piloto fez — custo medido: helper de FK + asserções reescritas + run no CT 100.
3. Proteger na origem: recusar `dropIfExists` de tabela CORE quando o nome da DB não terminar em `_test`. Fecha a classe inteira, inclusive os 142, mas é máquina nova — exige FP medido antes (regra "LIGUE A MÁQUINA" item 4).

Refs: [#5396](https://github.com/wagnerra23/oimpresso.com/pull/5396) · [handoff 2026-08-07 15:30](../../handoffs/2026-08-07-1530-quarentena-era-sqlite-piloto-lane-whatsapp.md) · [ADR 0062](../../decisions/0062-separacao-runtime-hostinger-ct100.md)
