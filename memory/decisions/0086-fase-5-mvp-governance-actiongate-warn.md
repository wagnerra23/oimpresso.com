---
slug: 0086-fase-5-mvp-governance-actiongate-warn
number: 86
title: "Fase 5 MVP — Modules/Governance scaffold + ActionGate (warn-only) + Sidebar GOVERNANÇA"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
decided_by: [W]
decided_at: "2026-05-05"
module: governance
quarter: 2026-Q2
tags: [governance, actiongate, fase-5, mvp]
supersedes: []
supersedes_partially: []
superseded_by: []
related:
  - 0079-constituicao-oimpresso-7-camadas-governanca
  - 0080-trust-tiers-operacional-audit-findings
  - 0081-identity-mesh-mcp-actors
  - 0085-fase-3-4-scope-md-completo-actor-resolver-pii-redactor
pii: false
review_triggers:
  - "ActionGate em modo warn por 4 semanas sem nenhuma violation registrada — sinal pra virar strict OU policies estão vazias"
  - "ActionGate em modo warn registrando >50 violations/dia — policies mal calibradas"
  - "Wagner pede UI Inertia frontend pra dashboard"
---

# ADR 0086 — Fase 5 MVP: Modules/Governance scaffold + ActionGate warn-only

## Contexto

Wagner: *"faça a (Fase 3.7 renames + Fase 5 UI). e verifique os grupos no sidebar"*

Sessão maratona 2026-05-05 entregou Constituição v1.1.0 + 7 documentos governance + Identity Mesh operacional. Fase 5 (ActionGate + UI Governance) era o último pilar inviabilizando enforcement runtime do Art. 8 da Constituição.

Análise de viabilidade desta sessão:
- **Fase 3.7 renames** (Copiloto→Jana, PontoWr2→Ponto, MemCofre→SRS, ProjectMgmt→Project) — 4-6h dedicadas + testes Pest + 301 redirects + webhook updates. **Risco alto** (URLs prod, namespace, tabelas DB). Inviável fechar com qualidade nesta sessão sem validação manual end-to-end.
- **Fase 5 completo** (ActionGate + UI Inertia frontend + policies CRUD + audit drill-down) — 12h+ multi-sessão.

Decisão: entregar Fase 5 **MVP** focado no que é mais alavancado pra Wagner usar dia-a-dia: scaffold do módulo Governance + DashboardController consolidado + ActionGate middleware em modo warn. UI Inertia frontend e CRUD detalhado ficam pra próxima sessão.

Adiar Fase 3.7 renames pra sessão dedicada. Documentar como bloqueador explícito.

## Decisão

### 1. Sidebar SIDEBAR_GROUPS reorganizado

`resources/js/Components/cockpit/Sidebar.tsx` atualizado:

**Antes:**
- IA & PRODUTIVIDADE: Copiloto, ADS, CRM, Crm, Team MCP, Projeto, Project Mgmt
- CONHECIMENTO: Cofre de Memórias, Base de Conhecimento, Planilha

**Depois:**
- ACESSOS RÁPIDOS: + CRM, Crm (são vendas/contatos, não IA)
- CONHECIMENTO: + SRS, Sistema de Regras, KB, Notas (preparação repurpose MemCofre)
- IA & PRODUTIVIDADE: Copiloto, **Jana** (preparação rename), Projeto, Project Mgmt, Project (preparação rename)
- **GOVERNANÇA (NEW):** Governança, Governance, ADS, Adaptive Decision, Team MCP, TeamMcp

Razão: ADS + TeamMcp + Governance são L1 GOVERNANCE (Art. 5), não IA. Separação semântica + visual.

### 2. Modules/Governance scaffold completo (8 peças canônicas — ADR 0011 + skill criar-modulo)

- `module.json` (alias=governance, active=0 default — Wagner ativa via /admin/install)
- `composer.json` (psr-4 namespace)
- `start.php` (entry point routes.php)
- `Config/config.php` (actiongate_mode env var + next_review_at)
- `Resources/lang/pt-BR/governance.php` (i18n)
- `Resources/menus/topnav.php` (declarative menu)
- `Providers/GovernanceServiceProvider.php` (boot translations + config + middleware alias `actiongate`)
- `Http/Controllers/{Dashboard,Install,Data}Controller.php` (3 controllers + 3 hooks)
- `Http/routes.php` (rota /governance + install hooks)
- `SCOPE.md` (Art. 7 charter)

Trust L1. Permission prefix `governance.*` com 3 perms iniciais (dashboard.view, policies.edit, audit.view).

### 3. DashboardController — KPIs consolidados (MVP)

Lê do DB:
- ADRs status=proposto pendentes (de mcp_memory_documents)
- Active policies count (mcp_governance_rules.enabled=1)
- Skill approvals pending (mcp_skill_approvals.status=pending)
- Audit highlights últimas 24h (mcp_audit_log com erro ou kernel_action)
- Actors count (mcp_actors não-revogados)
- Compliance score heurístico (Constituição v1.1.0 — 8/10 plenos = 80%)

Renderiza Inertia `governance/Dashboard` page (componente React frontend pendente próxima sessão).

### 4. ActionGate middleware (warn-only MVP)

`Modules/Governance/Http/Middleware/ActionGate.php` — alias `actiongate`. Modos:

| Mode | Comportamento |
|---|---|
| `off` | middleware loaded mas não faz nada |
| **`warn`** (default MVP) | loga warnings sem bloquear |
| `strict` | bloqueia 403 + audit obrigatório |

Configuração: `GOVERNANCE_ACTIONGATE_MODE` env var. Default warn.

Funcionalidades MVP:
- Check actor identificável (via ActorResolver de Modules/TeamMcp)
- Trust tier check (param `:requiredTier` na rota — ex `actiongate:L1`)
- Actor revogado check
- Log estruturado em `Log::channel('single')->warning('ActionGate violation', [...])`

Uso (futuro): adicionar em rotas L1+:
```php
Route::middleware(['web','auth','actiongate:L1'])->...
```

### 5. ADR 0085 (sessão anterior) status atualizado

ADR 0085 inicialmente declarou Fase 5 como "multi-sessão (~12h) pendente". Esta ADR (0086) substitui parcialmente — Fase 5 MVP entregue, frontend Inertia + policies CRUD + audit drill-down ficam pra próxima.

### 6. Pendências NÃO fechadas (próximas sessões)

**Fase 3.7b-e — Renames executados:**
- Copiloto → Jana
- PontoWr2 → Ponto
- MemCofre → SRS (repurpose com migration de tabelas)
- Project legado DELETE + ProjectMgmt → Project
- 9 drift controllers migration (Copiloto→KB+TeamMcp; ADS→TeamMcp+ProjectMgmt+KB)

ETA proposto: sessão dedicada 4-6h com test Pest + build Inertia + webhook validation.

**Fase 5 next steps (cosméticos+detalhe):**
- UI Inertia frontend `governance/Dashboard.tsx` componente React
- PoliciesController CRUD inline editor
- AuditController drill-down filtrável + export LGPD
- DriftAlertsController integrado com mcp_alertas
- ActionGate registro em rotas existentes (gradual)
- Mudar mode warn → strict após 4 semanas calibração

ETA: 8-10h distribuídos.

## Justificativa

**Por que MVP em vez de completo.** Time-to-value vs perfeição. Backend funcionando + ActionGate warn-only coletando sinal hoje > esperar 12h pra ter UI completa. Wagner já tem `/copiloto/admin/memoria` como UI de ADRs e `/ads/admin/skills-review` como UI de approvals. Backend Governance + warn capturando violations destrava enforcement gradual.

**Por que warn em vez de strict.** Strict mode bloqueia produção. Em MVP sem Wagner ter calibrado policies, strict pode bloquear ações legítimas. Warn coleta sinal real por 4 semanas antes de qualquer block.

**Por que adiar Fase 3.7.** Renames mexem em URLs prod, namespace, tabelas DB, webhook GitHub. Fechar mal é pior que adiar. Sessão dedicada com Wagner validando visualmente cada redirect = qualidade.

**Por que Modules/Governance não Modules/Compliance ou Modules/Admin.** "Governance" é nome canônico no NIST/OPA/Cedar. Compliance é subset (Art. 4). Admin é genérico demais. Governance é exato.

**Por que ActionGate em Modules/Governance e não Modules/Core.** ActionGate é feature governance — depende de mcp_actors (TeamMcp), mcp_governance_rules (governance), audit log (compartilhado). Coabita semantically.

**Reabrir esta decisão se:** ActionGate warn registrar zero violations por 4 semanas (policies vazias OU bug); UI Inertia frontend bloquear adoção (Wagner não consegue operar 5min/dia).

## Cascade Review (cumprindo §10.4)

Mudança em **L6 Policy Gating** (ActionGate ativo) cascata pra:

| Camada | Auditada | Resultado | Ação |
|---|---|---|---|
| L7 Audit | ✅ sim | ActionGate warn loga em Log::channel; mcp_audit_log já popula via McpAuthMiddleware | OK |
| L4 Identity Mesh | ✅ sim | ActionGate consulta ActorResolver (Modules/TeamMcp/Services/) | OK |
| L5 Module Charter | ✅ sim | Modules/Governance/SCOPE.md criado | OK |
| L3 Trust Tiers | ✅ sim | ActionGate valida `actor.trust_level >= requiredTier` | OK |
| Sidebar (cross-cutting) | ✅ sim | Grupo GOVERNANÇA criado, refletindo Trust hierarchy | OK |
| ADRs (cross-cutting) | ✅ sim | ADR 0085 atualizado mencionando 0086 | OK |

## Consequências

**Positivas:**

- **Constituição Art. 8 operacional (warn)** — mecanismo de gate existe.
- **Modules/Governance dedicado** — separação semântica vs ADS (decision flow ≠ governance UI).
- **Sidebar reorganizado** — Wagner navega por categoria correta.
- **ActionGate alias `actiongate`** — pronto pra ser adicionado em rotas L1+ conforme necessário.
- **DashboardController consolidado** — KPIs em um lugar.

**Negativas / Trade-offs:**

- **Frontend Inertia ausente** — `Dashboard.tsx` componente React precisa ser criado pra UI realmente renderizar.
- **ActionGate em warn não bloqueia** — proteção real só após mode=strict.
- **Mode warn pode virar ruído** — se rotas todas começarem a logar warnings, Wagner não vai filtrar. Mitigação: aplicar middleware gradualmente (route-by-route opt-in).
- **Modules/Governance active=0 default** — Wagner precisa ativar via `/admin/install` antes de usar.

**Riscos mitigados:**

- Bloqueio acidental em produção (warn mode).
- Drift de governança em pasta errada (SCOPE.md + GUARDA + Module Charter).
- Auditor externo perguntar "onde é o gate?" (resposta: Modules/Governance/Http/Middleware/ActionGate.php).

## Implementação

✅ **FEITO nesta ADR:**

1. Sidebar SIDEBAR_GROUPS reorganizado (3 mudanças)
2. Modules/Governance scaffold (10 arquivos: module.json, composer.json, start.php, Config, lang, topnav, Providers, 3 Controllers, routes, SCOPE.md, Middleware ActionGate)
3. DashboardController com 6 KPIs lendo DB
4. ActionGate middleware warn-only com 3 modos
5. modules_statuses.json + Writebot removido + Governance adicionado (active=0)
6. ADR 0085 atualizado mencionando 0086
7. Esta ADR

⏸️ **Pendente (próxima sessão):**

- `resources/js/Pages/governance/Dashboard.tsx` (componente React)
- PoliciesController + AuditController + DriftAlertsController CRUD
- ActionGate aplicado em rotas existentes (gradual)
- Migrate warn → strict após 4 semanas calibração
- Fase 3.7 renames executados (Copiloto→Jana, PontoWr2→Ponto, MemCofre→SRS, ProjectMgmt→Project) + 9 drift controllers

## Referências

- [Constituição v1.1.0 — Artigo 8](../governance/CONSTITUTION.md)
- [ENFORCEMENT.md — mecanismo #4 ActionGate](../governance/ENFORCEMENT.md)
- [TRUST-TIERS.md](../governance/TRUST-TIERS.md)
- [ADR 0079 — Constituição](0079-constituicao-oimpresso-7-camadas-governanca.md)
- [ADR 0080 — Trust Tiers + Architecture](0080-trust-tiers-operacional-audit-findings.md)
- [ADR 0081 — Identity Mesh](0081-identity-mesh-mcp-actors.md)
- [ADR 0085 — SCOPE.md completo + ActorResolver + PII](0085-fase-3-4-scope-md-completo-actor-resolver-pii-redactor.md)
