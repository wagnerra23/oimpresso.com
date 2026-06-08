---
name: S6 Deep Dive — Playbooks (L4) + Strangler State Machine
description: Pesquisa estado-da-arte 2026 pra Sprint 6. Distinção runbook vs playbook, Intelligent Runbook Execution, LLM como steering files, frontmatter com last_tested, Strangler + canary feature flag.
type: project
created: 2026-05-06
related_sprint: S6
sources_count: 2
---

# S6 — Playbooks L4 + Strangler State Machine (deep-dive)

> **Objetivo da pesquisa:** validar nossos 6 playbooks + state machine LEGACY→DELETED
> contra estado-da-arte 2026 sobre runbook automation e strangler fig.

---

## Achado #1 — "Runbook" e "Playbook" são coisas DIFERENTES no mercado

[Atlassian — Incident Response Playbook](https://www.atlassian.com/incident-management/incident-response/how-to-create-an-incident-response-playbook), [Incident.io — Runbook Automation 2026](https://incident.io/blog/runbook-automation-tools-2026-the-complete-guide):

> "**A runbook is a tactical, step-by-step checklist** to fix a specific technical problem. **A playbook is a high-level, strategic guide** that defines how your people and organization respond to any incident, covering roles, communication, and escalation policies."

### Implicação no plano S6

Nossos 6 "playbooks" do plano original são na verdade **mistos**:

| Nome no plano | É runbook ou playbook? |
|---|---|
| `migrate-route.md` | **Runbook** (passos técnicos exatos) |
| `add-page-charter.md` | **Runbook** (criar arquivo + sync) |
| `incident-prod.md` | **Playbook** (estratégico, roles, comms) |
| `onboard-new-claude.md` | **Runbook** (steps técnicos onboarding) |
| `onboard-new-dev.md` | **Playbook** (mistura técnico + cultural) |
| `deprecate-route.md` | **Runbook** (steps técnicos delete) |

🟡 **Recomendação:** ajustar nomenclatura no S6:
- Diretório `memory/playbooks/` → `memory/operational/` com 2 sub-diretórios:
  - `memory/operational/runbooks/` — `migrate-route.md`, `add-page-charter.md`, `onboard-new-claude.md`, `deprecate-route.md`
  - `memory/operational/playbooks/` — `incident-prod.md`, `onboard-new-dev.md`

Nomenclatura clara ajuda LLM e humano achar o tipo certo.

---

## Achado #2 — LLM-consumed playbooks são "steering files"

[Quaxel — Runbooks to Agents](https://medium.com/@Quaxel/runbooks-to-agents-automating-the-boring-80-of-on-call-5b4d763cfe8b):

> "AI-playbooks are designed to be consumed by an integrated development environment 'IDE' leveraging a large language model 'LLM' as **'steering files' or 'skills'**."

### Conexão com nossas Skills

🟢 **Confirma o design:** nossas skills (`.claude/skills/*/SKILL.md`) são o equivalente do "steering files" do mercado. Diferença é que skills disparam por contexto enquanto runbooks são acionados explicitamente.

Convergência possível: **runbook pode virar skill on-demand (Tier C)**.

Exemplo: `migrate-route.md` runbook poderia ser `.claude/skills/migrate-route/SKILL.md` Tier C — ativada via `/migrate-route <rota>` slash command.

### Implicação no plano S6

🟡 **Plano original previa runbook como markdown puro.** Refinamento: criar tabela de mapeamento:

| Runbook | Tipo | Skill correspondente? |
|---|---|---|
| `migrate-route.md` | technical procedure | ✅ skill Tier C `/migrate-route` |
| `add-page-charter.md` | technical procedure | ✅ skill Tier C `/add-charter` |
| `incident-prod.md` | strategic playbook | ❌ não vira skill (é manual humano) |
| `onboard-new-claude.md` | technical procedure | ✅ skill Tier C `/onboard-claude` |
| `onboard-new-dev.md` | strategic playbook | ❌ não vira skill |
| `deprecate-route.md` | technical procedure | ✅ skill Tier C `/deprecate-route` |

Resultado: **4 dos 6 viram skills automatizáveis.** 2 ficam como playbook humano.

---

## Achado #3 — Metadata canônica de runbook 2026

[The Good Shell — Incident Runbook Template SRE 2026](https://thegoodshell.com/incident-runbook-template/), [Christian Emmer — Runbook Template](https://emmer.dev/blog/an-effective-incident-runbook-template/):

Frontmatter padrão:

```yaml
---
title: Migrate Route Blade → Inertia
runbook_id: rb.migrate-route
version: 1.2
last_updated: 2026-05-06
last_tested: 2026-04-30   # ← CRÍTICO em 2026
owner: felipe
slack_channel: '#oimpresso-ops'
estimated_duration: 90min
risk_level: medium       # low/medium/high/critical
approval_required: wagner-or-felipe
adrs: [0093, 0094]
---
```

🟢 **`last_tested` é o campo killer.** Runbook que não foi testado em >90d alerta no Cockpit. Evita "runbook desatualizado mata noite de oncall".

### Implicação no plano S6

✅ **Adicionar `last_tested` + `risk_level` + `approval_required`** no frontmatter dos 6 runbooks.
Cockpit S7 lista runbooks `last_tested > 90d` em painel de alerta.

---

## Achado #4 — Intelligent Runbook Execution > scripts puros

[Incident.io 2026](https://incident.io/blog/runbook-automation-tools-2026-the-complete-guide):

> "Runbook automation in 2026 has moved beyond bash scripts to **Intelligent Runbook Execution**: context-aware, human-in-the-loop workflows that coordinate people, tools, and communications automatically."

### Implicação no plano S6

🟡 Nosso `migrate-route.md` precisa ser executado por humano + Claude pareados. Padrão correto:

```markdown
# Runbook: migrate-route

## Quando usar
Migrar tela Blade pra Inertia/React seguindo padrão MWART.

## Pré-requisitos
- [ ] Charter da página existe (`*.charter.md`)
- [ ] Sprint 4 estável (charter-fetch funcional)
- [ ] Feature flag config em `config/mwart.php`

## Steps (HITL = human-in-the-loop)

### 1. [Claude] Ler charter e identificar invariants
- Tool: `mcp__oimpresso__charter-fetch <charter_id>`
- Output esperado: lista de invariants + file scope

### 2. [Claude] Criar branch + worktree
- Comando: `git worktree add .claude/worktrees/<feat-slug>`

### 3. [Claude] Implementar React component conforme charter §5
- Bloqueia se charter §3 (invariants) violado

### 4. [Wagner] Validar visualmente em staging
- URL: `https://staging.oimpresso.com/<rota>?MWART_<X>=true`

### 5. [Claude] Rodar suite Pest + Playwright visual
- `php artisan test --filter Repair` + `npx playwright test`

### 6. [Claude] Abrir PR com refs sprint
- `gh pr create --title "..." --body "Refs: SPRINT-X PASSO Y"`

### 7. [Wagner] Aprovar merge

### 8. [Claude] Soak 48h, monitorar Sentry + Telescope p95

### 9. [Wagner] Promover state LEGACY → MIGRATING → CANARY → MIGRATED

## Verificação de sucesso
- [ ] Pest 100% pass
- [ ] Playwright visual diff <5%
- [ ] p95 < 400ms (charter invariant)
- [ ] Sentry zero erros 48h

## Quando reverter
- error_rate > baseline Blade
- Wagner ou cliente reporta bug visual

## Próximos runbooks
- `deprecate-route.md` (após MIGRATED 14d estável)
```

🟢 **Cada step prefixado com [Claude] / [Wagner]** = HITL explícito. Skill correspondente (se existir) executa só os [Claude] steps automaticamente.

---

## Achado #5 — Strangler Fig com canary é o padrão 2026

[Accesto — Strangler Pattern in Practice](https://accesto.com/blog/strangler-pattern-in-practice/), [Curotec](https://www.curotec.com/insights/modernizing-a-legacy-application-using-the-strangler-fig-pattern/):

> "Feature flags allow developers to deploy new microservices into production without immediately activating them for all users. **A percentage of traffic is sent to the new system, increasing over time as confidence grows** — essentially a canary deployment applied to migration."

### Implicação no plano S6

State machine original: `LEGACY → MIGRATING → CANARY → MIGRATED → DELETED` ✅

Refinamento — campo `canary_pct`:

```sql
CREATE TABLE mcp_route_migration_state (
  ...
  state ENUM('LEGACY','MIGRATING','CANARY','MIGRATED','DELETED'),
  canary_pct TINYINT DEFAULT 0,           -- 0-100, só usado em state=CANARY
  canary_start_at TIMESTAMP NULL,
  canary_baseline_error_rate DECIMAL(6,4) NULL,
  canary_current_error_rate DECIMAL(6,4) NULL,
  ...
);
```

Workflow de canary:

```
1. state=MIGRATING → developer cria React + flag MWART_X=false
2. Wagner promove para state=CANARY com canary_pct=10
3. Skill canary-monitor (cron 1h):
   - Lê Sentry/Telescope para os 10% que receberam React
   - Compara error_rate vs baseline Blade
   - Se rate ≤ baseline +20% por 24h → bumpa pra 25%
   - Se rate ≤ baseline +20% por 48h em 25% → 50%
   - Se rate ≤ baseline +20% por 48h em 50% → 100% (state=MIGRATED)
   - Se rate > baseline +50% em qualquer momento → ROLLBACK pra LEGACY
4. Após state=MIGRATED por 14d estável → skill blade-cleanup propõe PR delete
```

### Implicação no plano S6

🟡 **Adicionar skill `canary-monitor`** ao plano S6 (não estava). Tier C trust_level 2, roda cron 1h, abre HITL em rollback.

---

## Achado #6 — ADR é append-only — confirma nossa convenção

[ADR Lifecycle Best Practices — pogopaule](https://github.com/pogopaule/architecture_decision_record/blob/master/adr_lifecycle.md), [Martin Fowler — ADR](https://martinfowler.com/bliki/ArchitectureDecisionRecord.html):

> "The ADR serves as an **append-only log**. Don't go back and edit accepted records. If a decision changes, write a new record that supersedes the original and link the two together."

🟢 **Confirma exatamente o que CLAUDE.md §6 já diz** (regra anti-regressão: "NUNCA editar; criar ADR nova com supersedes"). Sem mudança no plano.

---

## Recomendações pro plano S6 (revisões)

### O que manter

- 6 procedures (mas renomear como tabela acima)
- State machine `LEGACY → MIGRATING → CANARY → MIGRATED → DELETED`
- Tabela `mcp_route_migration_state`
- Skill `blade-cleanup` (Tier C)
- Tool MCP `migration-state-list`

### O que mudar

| Item | Plano original | Revisão pós deep-dive |
|---|---|---|
| Diretório | `memory/playbooks/` | **`memory/operational/{runbooks,playbooks}/`** (separa tactical vs strategic) |
| Frontmatter runbook | informal | **`title, runbook_id, version, last_updated, last_tested, owner, channel, duration, risk_level, approval_required, adrs`** (10 campos canônicos) |
| Steps no runbook | numerados puros | **prefixados `[Claude]` ou `[Wagner]` (HITL explícito)** |
| State CANARY | flag binária | **`canary_pct` (0-100) com auto-bump baseado em error_rate** |
| Cleanup | só skill `blade-cleanup` | **+ skill `canary-monitor` (auto-promove ou rollback)** |

### O que adicionar

- [ ] Skill `canary-monitor` (Tier C, cron 1h)
- [ ] Cockpit S7 alerta runbooks `last_tested > 90d`
- [ ] 4 runbooks viram skills Tier C (slash commands `/migrate-route`, `/add-charter`, `/onboard-claude`, `/deprecate-route`)
- [ ] 2 playbooks ficam como markdown puro (`incident-prod.md`, `onboard-new-dev.md`)

### Estimativa revisada

Plano original: 4–5 dias.
Pós deep-dive: **5–7 dias** (canary-monitor é trabalho extra; 4 skills Tier C são pequenos mas multiplicam).

---

## Sources

- [Runbook automation tools 2026 — incident.io](https://incident.io/blog/runbook-automation-tools-2026-the-complete-guide)
- [Atlassian — How to create an incident response playbook](https://www.atlassian.com/incident-management/incident-response/how-to-create-an-incident-response-playbook)
- [Quaxel — Runbooks to Agents: Automating Boring 80% of On-Call](https://medium.com/@Quaxel/runbooks-to-agents-automating-the-boring-80-of-on-call-5b4d763cfe8b)
- [Incident Runbook Template SRE 2026 — The Good Shell](https://thegoodshell.com/incident-runbook-template/)
- [Christian Emmer — An Effective Incident Runbook Template](https://emmer.dev/blog/an-effective-incident-runbook-template/)
- [Strangler Pattern in Practice — Accesto](https://accesto.com/blog/strangler-pattern-in-practice/)
- [Strangler Fig Pattern — Curotec](https://www.curotec.com/insights/modernizing-a-legacy-application-using-the-strangler-fig-pattern/)
- [ADR Lifecycle Best Practices — pogopaule](https://github.com/pogopaule/architecture_decision_record/blob/master/adr_lifecycle.md)
- [Martin Fowler — Architecture Decision Record](https://martinfowler.com/bliki/ArchitectureDecisionRecord.html)
