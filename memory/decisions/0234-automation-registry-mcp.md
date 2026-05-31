---
slug: 0234-automation-registry-mcp
number: 234
title: "Registry de Automações no MCP — hooks/crons/rotinas governados"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
decided_by: [W]
decided_at: "2026-05-29"
proposed_at: "2026-05-29"
module: governance
quarter: 2026-Q2
supersedes: []
related:
  - 0053-mcp-server-governanca-como-produto
  - 0070-jira-style-task-management-current-md-removed
  - 0076-skills-db-primary-git-destino-drift-alert
  - 0079-constituicao-oimpresso-7-camadas-governanca
  - 0080-trust-tiers-operacional-audit-findings
  - 0093-multi-tenant-isolation-tier-0
tags: [governance, automations, hooks, cron, mcp, observability, enforcement]
---

# ADR 234 — Registry de Automações no MCP: hooks/crons/rotinas governados

## Status

**Aceito** — 2026-05-29 (Wagner autorizado). Promovido de proposta (PR #1938) após implementação no PR #1939 (mergeado em `main`). Ver §Emendas para as 4 decisões de implementação ratificadas.

Origem: auditoria [`memory/requisitos/TaskRegistry/AUDIT-TEAM-OS-2026-05-29.md`](../requisitos/TaskRegistry/AUDIT-TEAM-OS-2026-05-29.md), gap **#11**.

## Contexto

O oimpresso governa **decisões** com rigor: ADRs Nygard append-only ([ADR 0070](0070-jira-style-task-management-current-md-removed.md)), Constituição 7 camadas ([ADR 0079](0079-constituicao-oimpresso-7-camadas-governanca.md)), 8 mecanismos de enforcement ([`ENFORCEMENT.md`](../governance/ENFORCEMENT.md)). Mas **automações** — código que roda sozinho, sem humano no loop — não têm rastro nenhum.

Inventário real do que roda hoje, invisível ao MCP server, ao time (Felipe/Maiara/Eliana/Luiz) e à governança:

**~35 hooks** em `.claude/hooks/` (PowerShell `*.ps1` + Node `*.mjs`), entre eles:
- `block-automem.ps1`, `block-bom-encoding.ps1`, `block-destructive.ps1`, `block-memory-drift.ps1`, `block-module-drift.ps1`, `block-mwart-violation.ps1`, `block-routes-string-legacy.ps1`, `block-claim-without-evidence.ps1`, `block-merge-markers.ps1`, `block-pr-without-approval.mjs`, `block-serving-branch-switch.ps1` (bloqueadores `PreToolUse`)
- `pii-redactor.ps1`, `commit-discipline-check.ps1`, `modulo-preflight-warning.ps1`, `mcp-first-warning.ps1`, `memory-pending.ps1`, `charter-validate.ps1`, `check-skills-fresh.ps1`, `tier-a-banner.ps1`, `force-r12-closing-signal.mjs`, `audit-creates-tasks.mjs`, `nudge-diagnosis-without-evidence.ps1`, `nudge-recommend-not-menu.ps1`, `post-merge-ui-smoke-required.ps1` (advisory `SessionStart`/`UserPromptSubmit`/`PostToolUse`)

**~30 crons** em `app/Console/Kernel.php`, entre eles:
- `jana:health-check` (06:00 BRT), `jana:system-audit` (06:15), `jana:weekly-digest`, `jana:cycles:auto-close-expired` (23:55), `jana:freshness-check` (04:30), `jana:retention-purge` (03:00)
- `copiloto:metrics:apurar` (23:55), `copiloto:sintese-semanal`, `copiloto:seed-adrs` (04:45), `copiloto:cleanup-memoria` (semanal)
- `memcofre:sync-memories` (23:00), `module:grade-snapshot` (06:05), `governance:scorecard-snapshot` (07:00), `governance:detect-drift` (06:15), `governance:initiative-sync` (08:00), `mcp:tasks:sync`, `mcp:sync-memory`, `brief:generate` (07/11/14/17/20/23h), `sells:smoke-daily` (06:30), `charter:health` (06:30), `observability:aggregate-daily` (02:00), e o bloco `ads:*` (review/learn/plan/process-brain-b)

**Rotinas** (composições hook + manifesto + comando) — ex: a rotina **"Fechar o Loop"** do IA-OS: `.claude/hooks/loop-fechar-check.ps1` + manifesto `.claude/loop-fechar-o-loop.json`.

O problema é uma **assimetria de governança**: o oimpresso tem trilha append-only para DECISÕES (quem decidiu, quando, por quê) mas **ZERO rastro para AUTOMAÇÕES** que executam sozinhas. Ninguém — nem Wagner, nem o time, nem um agente IA — consegue responder via MCP:

- "Quais automações existem neste projeto?"
- "O que cada uma dispara, e em qual gatilho (matcher de hook / expressão cron / pós-brief)?"
- "Quando rodou por último, e deu `ok` / `warn` / `fail`?"
- "Existe automação no filesystem que não está registrada (drift), ou registrada cujo arquivo sumiu?"

Hoje a resposta exige `git grep` no filesystem + leitura manual de `Kernel.php` — exatamente o anti-padrão que [ADR 0070](0070-jira-style-task-management-current-md-removed.md) eliminou para tasks (markdown paralelo desincronizando) e [ADR 0076](0076-skills-db-primary-git-destino-drift-alert.md) eliminou para skills (DB-primary + drift alert). Automações são o **último artefato operacional sem registry MCP**.

[`ENFORCEMENT.md`](../governance/ENFORCEMENT.md) cataloga 8 mecanismos NIST/Cedar/OPA aplicados às 7 camadas. Nenhum deles **inventaria e observa as próprias automações** — o doc descreve o que cada mecanismo *deveria* fazer, mas não há um lugar único onde o estado vivo (existe? habilitada? rodou quando? drift?) de cada hook/cron/rotina seja consultável. Este registry é a peça que faltava.

**Restrições:**
- Multi-tenant `business_id NULL = global` — automações são infra de plataforma (ver seção [Multi-tenant](#multi-tenant-tier-0)).
- Webhook GitHub continua existindo ([ADR 0053](0053-mcp-server-governanca-como-produto.md)) — o sync pode ser disparado por push, mas o source-of-truth da varredura é o filesystem do repo (igual skills).
- **Sem custo de LLM.** O registry é puramente observabilidade/governança — varredura determinística de arquivos + parse de `Kernel.php`. NÃO depende de Brain B nem de autonomia ADS (decisão Wagner: não ligar 2º cérebro agora).

## Decisão

Criar um **Registry de Automações DB-backed** seguindo **exatamente o padrão [ADR 0076](0076-skills-db-primary-git-destino-drift-alert.md)** (skills): **DB é primary, filesystem é a fonte, sync service espelha, drift detection alerta**. O registry é o **mecanismo de enforcement #9** de [`ENFORCEMENT.md`](../governance/ENFORCEMENT.md) — **inventário + observabilidade de automações**, complementar aos 8 existentes (que protegem camadas; este observa as automações que as protegem).

> **Mapeamento ADR 0076 → 234** (mesma anatomia, semântica diferente):
>
> | ADR 0076 (skills) | ADR 234 (automações) |
> |---|---|
> | `mcp_skills` (entidade) | `mcp_automations` (entidade) |
> | `mcp_skill_versions` (append-only) | `mcp_automation_runs` (append-only) |
> | `.claude/skills/*/SKILL.md` (fonte) | `.claude/hooks/*.{ps1,mjs}` + `Kernel.php` + `.claude/*.json` (fonte) |
> | `ImportarSkillsDoGitService` (sync) | `AutomationRegistrySync` (sync) |
> | `mcp_skill_drift_alerts` | `mcp_alertas` (categoria `automation_drift`) |
> | tools `skills-search`/`skills-fetch` | tool `automations-list` |

### 1. Tabela `mcp_automations` (DB-primary — entidade canônica)

```sql
CREATE TABLE mcp_automations (
  id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  slug            VARCHAR(100) NOT NULL UNIQUE,
  business_id     BIGINT UNSIGNED NULL
                  COMMENT 'NULL = global (registry de infra de plataforma, sem tenant — ADR 0093)',

  tipo            ENUM('hook_sessionstart','hook_pretooluse','hook_posttooluse',
                       'cron','routine','webhook') NOT NULL,

  gatilho         VARCHAR(255) NOT NULL
                  COMMENT 'texto livre: matcher do hook (ex Edit|Write) OU expressao cron (ex 0 6 * * *) OU "SessionStart pos brief-fetch"',
  descricao       TEXT NULL,
  arquivo         VARCHAR(300) NOT NULL
                  COMMENT 'path relativo ao repo (ex .claude/hooks/pii-redactor.ps1)',
  owner           VARCHAR(100) NULL,
  governed_by_adr VARCHAR(100) NULL
                  COMMENT 'slug do ADR que governa esta automacao (nullable)',

  enabled         BOOLEAN NOT NULL DEFAULT TRUE,
  last_run_at     TIMESTAMP NULL,
  last_status     ENUM('ok','warn','fail','skip') NULL,
  last_detail     TEXT NULL,

  created_at      TIMESTAMP NULL,
  updated_at      TIMESTAMP NULL,

  INDEX idx_automations_tipo (tipo),
  INDEX idx_automations_enabled (enabled),
  INDEX idx_automations_last_status (last_status),
  INDEX idx_automations_business (business_id)
);
```

### 2. Tabela `mcp_automation_runs` (audit append-only — opcional)

Espelha `mcp_skill_versions` (append-only puro): rastreia execuções ao longo do tempo, separadas do snapshot vivo em `mcp_automations.last_*` (mesma separação que ADR 0076 faz entre histórico imutável e estado corrente, e que [`ENFORCEMENT.md`](../governance/ENFORCEMENT.md) §L7 exige para audit).

```sql
CREATE TABLE mcp_automation_runs (
  id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  automation_id   BIGINT UNSIGNED NOT NULL,
  ran_at          TIMESTAMP NOT NULL,
  status          ENUM('ok','warn','fail','skip') NOT NULL,
  detail          TEXT NULL,
  actor           VARCHAR(100) NULL
                  COMMENT 'quem/o-que disparou: "scheduler", "claude-code:SessionStart", username...',

  INDEX idx_runs_automation_ran (automation_id, ran_at),
  INDEX idx_runs_status (status),
  CONSTRAINT fk_runs_automation FOREIGN KEY (automation_id)
    REFERENCES mcp_automations(id) ON DELETE CASCADE
);
```

> **Append-only** como `mcp_audit_log` ([`ENFORCEMENT.md`](../governance/ENFORCEMENT.md) §L7): nunca `UPDATE`/`DELETE` em rows existentes. Cada execução é um INSERT. Retenção via `jana:retention-purge` (já existe no Kernel) se o volume crescer.

### 3. Tool MCP `automations-list`

Lista as automações + estado vivo + **drift**. Mesma família de `skills-search` ([ADR 0076](0076-skills-db-primary-git-destino-drift-alert.md) §5) — read-only, multi-tenant-safe, sem custo de LLM.

Retorno por automação:
- `slug`, `tipo`, `gatilho`, `arquivo`, `owner`, `governed_by_adr`, `enabled`
- `last_run_at`, `last_status`, `last_detail`
- `drift`: enum `none` / `orphan_file` (arquivo no filesystem, não registrado) / `missing_file` (registrado, arquivo sumiu)

Filtros: `tipo`, `enabled`, `last_status`, `drift` (ex.: `automations-list drift:missing_file` mostra automações zumbis). Default: todas, ordenadas por `tipo` + `slug`.

### 4. Service `AutomationRegistrySync`

Espelha [`ImportarSkillsDoGitService`](../../Modules/Jana/Services/Mcp/ImportarSkillsDoGitService.php) **traço-a-traço**: varre o filesystem do repo → `upsert` em `mcp_automations` → drift alert em `mcp_alertas`. Idempotente. Envolvido em `OtelHelper::span('jana.mcp.automation_registry_sync', [], ...)` **sem `business_id`** (registry global), igual ao span de import de skills.

3 coletores (um por classe de fonte):

| Coletor | Fonte | Heurística |
|---|---|---|
| `coletarHooks()` | `glob('.claude/hooks/*.ps1') + glob('.claude/hooks/*.mjs')` (exclui `*.test.*`) | slug = basename sem extensão; `tipo` inferido por marcador no header do arquivo OU por `.claude/settings.json` (qual evento o registra); `gatilho` = matcher do evento |
| `coletarCrons()` | parse de `app/Console/Kernel.php` | regex em `$schedule->command('X')->...` → slug = comando; `tipo=cron`; `gatilho` = expressão cron / `dailyAt` resolvida |
| `coletarRotinas()` | `glob('.claude/*.json')` com marcador `"_automation_registry": true` | slug + `tipo=routine` + `gatilho` + `arquivo` lidos do manifesto |

**Upsert** (igual `ImportarSkillsDoGitService`): se `slug` existe → atualiza campos descritivos (não toca `last_run_at`/`last_status`, que são escritos pelas próprias automações); se não existe → cria. Após upsert, **drift em 2 direções**:

```
para cada arquivo no filesystem sem row em mcp_automations:
    McpAlerta::create(category='automation_drift', severity='medium',
                      detail="Automacao no filesystem nao registrada: <arquivo>")
para cada row enabled em mcp_automations cujo `arquivo` sumiu do filesystem:
    McpAlerta::create(category='automation_drift', severity='high',
                      detail="Automacao registrada com arquivo ausente: <slug> -> <arquivo>")
```

Mesma reconciliação declarado-vs-observado do mecanismo #5 (drift detection cron) de [`ENFORCEMENT.md`](../governance/ENFORCEMENT.md), agora aplicada às próprias automações. Comando `php artisan jana:automations:sync` (manual + opcional no schedule diário).

### 5. SEED — a rotina "Fechar o Loop" é a primeira automação registrada

A rotina **"Fechar o Loop"** do IA-OS (manifesto `.claude/loop-fechar-o-loop.json` + check `.claude/hooks/loop-fechar-check.ps1`) é a **primeira automação** do registry — exemplo concreto de uma rotina governada por este próprio ADR:

```sql
INSERT INTO mcp_automations
  (slug, business_id, tipo, gatilho, descricao, arquivo, owner, governed_by_adr, enabled, created_at, updated_at)
VALUES
  ('loop-fechar-o-loop',
   NULL,                                       -- global (infra de plataforma)
   'routine',
   'SessionStart pos brief-fetch',             -- gatilho texto livre
   'Rotina IA-OS Fechar o Loop: verifica loops abertos (decisao sem metrica, US sem evidencia) no inicio da sessao, pos brief-fetch.',
   '.claude/hooks/loop-fechar-check.ps1',      -- arquivo (manifesto: .claude/loop-fechar-o-loop.json)
   'wagner',
   'automation-registry-mcp',                  -- governado por ESTE ADR
   TRUE,
   NOW(), NOW());
```

> O slug `governed_by_adr='automation-registry-mcp'` é auto-referente de propósito: a primeira automação registrada é governada pelo ADR que cria o registry — demonstra o padrão de ponta a ponta. Demais automações apontam para seus ADRs (ex.: `block-mwart-violation` → `0104`, `pii-redactor` → ADR de LGPD, `governance:detect-drift` → `0079`).

### 6. Inventário humano paralelo IMEDIATO — `memory/governance/AUTOMATIONS.md`

Antes mesmo da tabela existir, o doc canônico [`memory/governance/AUTOMATIONS.md`](../governance/AUTOMATIONS.md) (criado **em paralelo** a este ADR) é o inventário legível das automações. Como vive em `memory/`, **já é indexado pelo MCP** via webhook GitHub ([ADR 0053](0053-mcp-server-governanca-como-produto.md)) — ou seja, **resolve hoje** a pergunta do Wagner *"isso fica na rede MCP?"* (consultável via `memoria-search` / `decisions-search`), sem esperar o backend.

Sequência de valor (igual ADR 0076: V0 read-only antes da UI completa):
1. **Hoje (V0):** `AUTOMATIONS.md` em git → indexado MCP → time já enxerga o inventário.
2. **V1 (DB-backed):** `mcp_automations` + `AutomationRegistrySync` + `automations-list` → estado vivo (`last_run`, `enabled`) + drift detection que markdown sozinho não dá.

`AUTOMATIONS.md` continua o **doc legível canônico** (igual SPEC.md continua canônico para US mesmo com `mcp_tasks` — [ADR 0070](0070-jira-style-task-management-current-md-removed.md)); `mcp_automations` é a **evolução DB-backed** com drift + last_run que um markdown não consegue oferecer.

## Multi-tenant (Tier 0)

`mcp_automations` é **GLOBAL by-design**: `business_id NULL` efetivo (sem tenant). Isto é uma **exceção justificada** à regra geral de [ADR 0093](0093-multi-tenant-isolation-tier-0.md) (*"toda entidade de negócio carrega `business_id` em global scope"*), pela mesma razão e com a mesma convenção dos demais `mcp_*` de governança:

| Tabela | `business_id` | Marcador |
|---|---|---|
| `mcp_skills` ([ADR 0076](0076-skills-db-primary-git-destino-drift-alert.md)) | `NULL` (global) | "global por default" no `ImportarSkillsDoGitService` |
| `mcp_governance_rules` ([ADR 0080](0080-trust-tiers-operacional-audit-findings.md)) | sem `business_id` | "Sem business_id by design" |
| **`mcp_automations`** (este ADR) | **`NULL` (global)** | **"Sem business_id by design — infra de plataforma"** |

**Justificativa.** Hooks (`.claude/hooks/`), crons (`Kernel.php`) e rotinas (`.claude/*.json`) são **infra de plataforma**, não dados de tenant: rodam no runtime do oimpresso e governam o repo inteiro, não os dados de um cliente. Um hook `block-pii` ou um cron `jana:health-check` não "pertence" ao business 4 (RotaLivre) nem ao business 7 — pertence à plataforma. A coluna `business_id NULL` é mantida no schema (não removida) por simetria com `mcp_skills`/`mcp_governance_rules` e para abrir a porta, no futuro, a automações *custom por-tenant* (ex.: cron de billing específico de um cliente B2B) sem migração de schema — exatamente como ADR 0076 reservou `business_id NULL` para skills custom por-tenant.

**Tier 0 preservado:** nenhuma query de dados de negócio é afetada. O registry não lê dados de tenant; lê arquivos do repo. O global scope de [ADR 0093](0093-multi-tenant-isolation-tier-0.md) continua IRREVOGÁVEL para toda entidade que carregue dados de cliente — `mcp_automations` simplesmente não é uma delas.

## Consequências

### Positivas

- **Simetria de governança fechada:** decisões (ADR), tasks ([ADR 0070](0070-jira-style-task-management-current-md-removed.md)), skills ([ADR 0076](0076-skills-db-primary-git-destino-drift-alert.md)) e agora **automações** têm registry MCP-queryable. Acaba a assimetria "rastro pra decisão, zero pra automação".
- **Pergunta respondível por agente:** "quais automações existem, o que disparam, quando rodaram, deu ok?" → uma chamada `automations-list`.
- **Drift detection bidirecional:** arquivo órfão (no FS, não registrado) **e** automação zumbi (registrada, arquivo sumiu) viram alerta em `mcp_alertas` — observabilidade que git grep manual não dá.
- **Mecanismo #9 de [`ENFORCEMENT.md`](../governance/ENFORCEMENT.md):** as automações que protegem as 7 camadas agora são, elas próprias, inventariadas e observadas.
- **Time enxerga (Felipe/Maiara/Eliana/Luiz):** via tool MCP + `AUTOMATIONS.md` indexado, ninguém mais precisa de acesso ao filesystem do repo pra saber o que roda sozinho.
- **Zero custo de LLM:** varredura determinística + parse de `Kernel.php`. Não toca Brain B / ADS.
- **Padrão reusado, não inventado:** schema, sync service e drift espelham ADR 0076 — baixo risco, curva zero pra quem já conhece skills.

### Negativas / Trade-offs

- **2 tabelas novas** (`mcp_automations` + `mcp_automation_runs`). Custo de schema esperado pelo escopo; idêntico em peso ao par skills.
- **`last_run_at`/`last_status` exigem que as automações reportem.** O `AutomationRegistrySync` popula descrição/existência, mas o estado de execução só fica fiel se cada hook/cron escrever seu run (via helper ou tool `automations-report`). **Mitigação:** V1 entrega o inventário + drift (alto valor, baixo esforço); instrumentação de `last_run` é incremental, automação a automação — `automations-list` mostra `last_run_at=NULL` honestamente até cada uma ser instrumentada (sem mentir sobre o que não sabe).
- **Parse de `Kernel.php` por regex é frágil** a refactors do arquivo. **Mitigação:** o sync é warn-only no drift de crons (não bloqueia); se o parse falhar para um comando, vira alerta `medium`, não erro fatal. Reusa a robustez do `resolveHeadSha()` do `ImportarSkillsDoGitService` (lê estado direto, tolera formatos variados).
- **Manutenção do `AUTOMATIONS.md` em paralelo ao DB** pode desincronizar (mesmo risco que SPEC.md vs mcp_tasks em [ADR 0070](0070-jira-style-task-management-current-md-removed.md)). **Mitigação:** `AutomationRegistrySync` é a fonte de verdade do *estado*; `AUTOMATIONS.md` é doc legível de *intenção/narrativa*. Um futuro check pode comparar os dois e alertar (como `governance:detect-drift` faz para SCOPE.md).

### Riscos mitigados

- **Não viola [ADR 0093](0093-multi-tenant-isolation-tier-0.md):** exceção `business_id NULL` é a mesma convenção já aceita para `mcp_skills`/`mcp_governance_rules`; nenhuma query de dados de negócio é tocada.
- **Não depende de Brain B/ADS:** decisão Wagner explícita; registry é observabilidade pura.
- **Webhook reusado** ([ADR 0053](0053-mcp-server-governanca-como-produto.md)): sync pode ser disparado por push, mas filesystem é a fonte (igual skills) — sem novo canal de integração.
- **`mcp_automation_runs` append-only** segue a regra de imutabilidade de audit ([`ENFORCEMENT.md`](../governance/ENFORCEMENT.md) §L7).

## Alternativas descartadas

- **(a) Deixar como está (invisível).** Manter hooks/crons/rotinas só no filesystem, descobríveis por `git grep`. ❌ É o gap #11 da auditoria. Reproduz exatamente o anti-padrão que ADR 0070 (tasks) e ADR 0076 (skills) já condenaram: artefato operacional sem registry → ninguém consulta, drift garantido, time sem visibilidade.
- **(b) Só markdown (`AUTOMATIONS.md`), sem DB.** ❌ O markdown resolve *hoje* o inventário legível (e por isso é feito em paralelo, §6) mas **não dá drift detection nem `last_run`** — não há reconciliação automática filesystem-vs-declarado, nem estado de execução vivo. Markdown é foto estática; o registry precisa de loop de observação. (Mesma razão pela qual ADR 0076 não deixou skills só em git.)
- **(c) Reusar `mcp_governance_rules`** ([ADR 0080](0080-trust-tiers-operacional-audit-findings.md)) **para automações.** ❌ Semântica errada. `mcp_governance_rules` modela *políticas de decisão* (ALLOW/REVIEW/BLOCK avaliadas pelo PolicyEngine em runtime); automações são *processos que executam por gatilho* (cron/hook/rotina). Misturar as duas semânticas polui ambas e quebra mutation testing das rules (mecanismo #6 de [`ENFORCEMENT.md`](../governance/ENFORCEMENT.md)).
- **(d) Arquivo YAML no git sem tool MCP.** ❌ Não é queryable por agente. Um `automations.yaml` versionado seria melhor que nada, mas um agente IA (Claude Code) ou o time via MCP precisaria ler o arquivo bruto e parsear — não há `automations-list` com filtros (`drift:missing_file`, `tipo:cron`, `last_status:fail`). Falha o requisito central: *"agente pergunta, MCP responde"*. (Idêntico ao motivo de ADR 0070 ter removido CURRENT.md/TASKS.md em favor de tools.)

## Plano de implementação

V1 = inventário + drift (alto valor, baixo esforço). Espelha o plano V1 de [ADR 0076](0076-skills-db-primary-git-destino-drift-alert.md) (backend-first, V0 read-only antes da UI).

| Passo | Escopo | Estimativa (recalibrada ADR 0106) |
|---|---|---|
| **0** | `AUTOMATIONS.md` em `memory/governance/` (paralelo a este ADR) → push → indexado MCP. **V0 já entrega valor.** | 1h |
| **1** | 2 migrations (`mcp_automations` + `mcp_automation_runs`) idempotentes + 2 Entities (`McpAutomation`, `McpAutomationRun`) + factories | 1h |
| **2** | `AutomationRegistrySync` (3 coletores: hooks/crons/rotinas) espelhando `ImportarSkillsDoGitService` + command `jana:automations:sync` + drift → `mcp_alertas` | 2h |
| **3** | Tool MCP `automations-list` (estado vivo + drift) + permission Spatie `governance.automations.read` (time inteiro) | 1h |
| **4** | Pest: sync idempotente, drift `orphan_file`, drift `missing_file`, seed `loop-fechar-o-loop`, `automations-list` retorna estado + filtros | 1h |
| **5** | **SEED** `loop-fechar-o-loop` (§5) + 1ª varredura real popula o inventário atual (~35 hooks + ~30 crons) | 30min |
| **6** *(incremental, pós-V1)* | Instrumentar `last_run`/`last_status` automação a automação (helper `automations-report` chamado por hooks/crons) | contínuo |

**Não-decisões deliberadas (deferred):**
- ❌ UI Inertia `/governance/automations` — V2 (V0/V1 entregam via tool MCP, como ADR 0070 deferiu Kanban Web).
- ❌ Habilitar/desabilitar automação **pela** tool (write) — V1 é read-only; `enabled` é espelho do estado real, não controle remoto.
- ❌ Instrumentação `last_run` de todas as ~65 automações de uma vez — incremental (passo 6).

## Emendas — Decisões de implementação ratificadas (2026-05-29)

Implementado no PR #1939 (mergeado em `main`). As 4 decisões levantadas na revisão da implementação foram **ratificadas por Wagner** ao aceitar este ADR:

1. **Drift alert grava em `mcp_alertas_eventos`, não `mcp_alertas`.** O pseudo-código de §4 (`McpAlerta::create(category=…, severity=…, detail=…)`) é ilustrativo; a tabela `mcp_alertas` real tem `kind` ENUM fechado, sem `category`/`severity`/`detail`. A implementação canônica grava em **`mcp_alertas_eventos`** (`tipo` / `severidade` / `titulo` / `descricao` / `chave_idempotencia` idempotente + `metadata`), seguindo o padrão de `StalenessDetectorService::alertCritical()`. Funcionalmente idêntico ao previsto: drift vira alerta idempotente queryável.
2. **`McpAutomation` não usa `HasBusinessScope`.** Ratificado: o registry é consultado pelo daemon MCP sem auth de tenant; um global scope esconderia as rows globais. A entidade carrega `business_id NULL` (infra de plataforma) com o marcador `// Sem business_id by design`, mas **sem** o trait de scope automático (diferente de `McpSkill`). Simetria estrita com `McpSkill` fica como follow-up opcional.
3. **A rotina-seed "Fechar o Loop" vive no PR #1938** (`.claude/hooks/loop-fechar-check.ps1` + `.claude/loop-fechar-o-loop.json`, com marcador `_automation_registry`). Foi removida do PR #1939 (dedup) para evitar conflito de merge; o `AutomationRegistrySync` a captura do filesystem em runtime.
4. **Schedule do `jana:automations:sync` no Kernel fica deferred** (era opcional). Pode ser adicionado (~06:25 BRT, junto aos demais drift checkers) num follow-up quando a 1ª varredura real for validada.

> **Nota de honestidade:** o gate PHPStan/Larastan permanece vermelho em `main` por débito **pré-existente** (gate vermelho na própria `main` HEAD — ADR 0208), **não** introduzido por este ADR nem pelo PR #1939, cujo código próprio é phpstan-limpo. Item de cleanup separado, já com worktrees do time em voo.

## Referências

- [ADR 0053 — MCP server governança como produto](0053-mcp-server-governanca-como-produto.md) (webhook + cache pattern reusados)
- [ADR 0070 — Jira-style task management; CURRENT.md/TASKS.md removidos](0070-jira-style-task-management-current-md-removed.md) (registry MCP > markdown paralelo; estilo ADR)
- [ADR 0076 — Skills DB-primary, git destino, drift alert](0076-skills-db-primary-git-destino-drift-alert.md) (**template conceitual**: DB-primary + filesystem fonte + sync service + drift)
- [ADR 0079 — Constituição oimpresso 7 camadas](0079-constituicao-oimpresso-7-camadas-governanca.md)
- [ADR 0080 — Trust Tiers operacional + audit findings](0080-trust-tiers-operacional-audit-findings.md) (`mcp_governance_rules` sem business_id by design)
- [ADR 0093 — Multi-tenant isolation Tier 0 IRREVOGÁVEL](0093-multi-tenant-isolation-tier-0.md) (regra geral business_id global scope; exceção justificada aqui)
- [`memory/governance/ENFORCEMENT.md`](../governance/ENFORCEMENT.md) — 8 mecanismos NIST/Cedar/OPA; este registry é o **mecanismo #9** (inventário + observabilidade de automações)
- [`memory/governance/AUTOMATIONS.md`](../governance/AUTOMATIONS.md) — inventário humano legível (deliverable paralelo, V0)
- [`Modules/Jana/Services/Mcp/ImportarSkillsDoGitService.php`](../../Modules/Jana/Services/Mcp/ImportarSkillsDoGitService.php) — sync service de skills que `AutomationRegistrySync` espelha
- [`memory/requisitos/TaskRegistry/AUDIT-TEAM-OS-2026-05-29.md`](../requisitos/TaskRegistry/AUDIT-TEAM-OS-2026-05-29.md) — origem (gap #11)
