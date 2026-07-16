---
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

**Implementado em:** `scripts/audit/sqlite-test-corruptors.mjs` · `.github/workflows/governance-gate-umbrella.yml` · `Modules/TeamMcp/Tests/Feature/CoworkHandoffCrossTenantTest.php` · `Modules/Jana/Tests/Feature/TaskRegistry/TaskUpdateAtomicTest.php` · verificado@2026-06-30 — **0 corruptores** (auditor `--strict --tier=A` exit 0; 11 dos lotes 2-3 + 1 novo PaymentGateway isolados via GUARDAR-TEARDOWN). DoD-2 (floor cai em 2 nightlies) blocked_by P04.

**Root cause PROVADO** (referenciado em US-GOV-020 "Lever real do floor" `:408-409`): o nightly full-suite roda contra um MySQL **persistente compartilhado**. ~18 testes "era-sqlite" criam tabelas sintéticas via `Schema::create`/`Schema::drop` em `beforeEach`/`afterEach` SEM guarda de driver — projetados pra rodar no sqlite `:memory:`. No MySQL persistente o `Schema::drop` **dropa a tabela real** → o próximo teste na mesma conexão acha tabela ausente → cascata `Base table not found`. Esse isolamento é o **lever real** do floor — **não é tweak de harness** (a Frente C/A.2 de US-GOV-020 já provou que FK-off é net-harmful; falhar-seguro é melhor).

**Fonte da verdade = comportamento, não literal-grep.** A lista canônica vem do auditor `scripts/audit/sqlite-test-corruptors.mjs --json` (classifica por `corruptsOnMysql`, não text-match — a v1 tinha ~48% FP, refutado em ADR 0276). **NÃO usar `git grep "Schema::drop"`** (super-conta: mistura guardados com corruptores).

#### Os 19 corruptores (auditor `--json`, tier A · ANTES dos fixes — 18 originais + 1 novo PaymentGateway detectado 2026-06-30)

| Arquivo | tier | ação | status |
|---|---|---|---|
| `Modules/TeamMcp/Tests/Feature/HandoffToolsTest.php` | A 75 | GUARDAR-TEARDOWN | ✅ isolado |
| `Modules/TeamMcp/Tests/Feature/HandoffIngestTest.php` | A 75 | GUARDAR-TEARDOWN | ✅ isolado |
| `Modules/TeamMcp/Tests/Feature/HandoffLeverToolTest.php` | A 75 | GUARDAR-TEARDOWN | ✅ isolado |
| `Modules/TeamMcp/Tests/Feature/HandoffStaleAlertTest.php` | A 75 | GUARDAR-TEARDOWN | ✅ isolado |
| `Modules/TeamMcp/Tests/Feature/HandoffSubmitToolTest.php` | A 75 | GUARDAR-TEARDOWN | ✅ isolado |
| `Modules/TeamMcp/Tests/Feature/IngestHeartbeatTest.php` | A 75 | GUARDAR-TEARDOWN | ✅ isolado |
| `Modules/TeamMcp/Tests/Feature/IngestLivenessTest.php` | A 60 | GUARDAR-TEARDOWN | ✅ isolado |
| `Modules/TeamMcp/Tests/Feature/CoworkHandoffCrossTenantTest.php` | A 75 | GUARDAR-TEARDOWN | ✅ isolado |
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
