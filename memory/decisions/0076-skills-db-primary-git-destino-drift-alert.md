---
slug: 0076-skills-db-primary-git-destino-drift-alert
number: 76
title: "Skills V2 — DB é primary, git é destino auditável; drift por-skill (auto/manual/pinned)"
type: adr
status: proposto
authority: canonical
lifecycle: ativo
decided_by: [W]
decided_at: "2026-05-05"
module: copiloto
quarter: 2026-Q2
tags: [mcp, skills, prompt-management, drift, governance, p0]
supersedes:
  - 0075-team-mcp-skills-ui-prompt-management-style
supersedes_partially: []
superseded_by: []
related:
  - 0053-mcp-server-governanca-como-produto
  - 0061-conhecimento-canonico-git-mcp-zero-automem
  - 0072-maturacao-memoria-team-mcp-openclaw-soa-2026
  - 0075-team-mcp-skills-ui-prompt-management-style
pii: false
review_triggers:
  - "Time crescer e drift manual virar gargalo (Wagner aprovando 5+ alerts/semana) → revisitar default git_sync_mode"
  - "Skills custom por-tenant gerarem demanda de fluxo separado git → DB"
  - "Anthropic publicar tooling oficial pra skills serializadas com fluxo próprio"
---

# ADR 0076 — Skills V2: DB primary, git destino, drift por-skill

## Contexto

Esta ADR **supersede [ADR 0075](0075-team-mcp-skills-ui-prompt-management-style.md)**. ADR 0075 propôs `git push → webhook → DB → UI` (git primary, UI lê e abre PR). Wagner argumentou (2026-05-05): "eu gostaria de ver o banco de dados e gravar caso aprovado no git, **não ao contrário**. Deixa eu decidir, testar, evoluir e resolver isso. A não ser que eu decida colocar no automático. Mas que poder ter segurança nesse processo."

**Mudança fundamental de princípio:**

| | ADR 0075 (revogado) | ADR 0076 (novo) |
|---|---|---|
| Source-of-truth | Git | **DB** |
| Edição UI | Abre PR no git | Direto em `mcp_skill_versions` (draft/staging) |
| Approval | Auto-merge PR | Decisão Wagner em DB |
| Publish git | Implícito ao approve | **Ação explícita separada** ("Publish to git") |
| Modo automático | Sempre | **Opt-in por-skill** (`git_sync_mode`) |

**Padrão validado:** [Langfuse](https://langfuse.com/docs/prompt-management) e [LangSmith](https://docs.langchain.com/langsmith/manage-prompts) — DB primary, git é integração opcional de output. ADR 0061 (zero auto-mem privada / git-first canônico) **não é violado** — ADR 0061 cobre **conhecimento canônico** (ADRs, sessions, runbooks, comparativos). Skills do Claude Code são **artefatos operacionais** com lifecycle próprio (iteração rápida, teste, descarte).

**Restrições:**
- Webhook GitHub continua existindo (ADR 0053). Mudança: agora detecta **drift** em vez de ser fonte autoritativa.
- 16 skills atuais em `.claude/skills/<slug>/SKILL.md` precisam ser importadas pra DB **uma vez** (one-time seed).
- Multi-tenant `business_id NULL = global` mantido.
- LGPD — testes com inputs reais exigem PII redactor (mesma regra de 0075).

## Decisão

**DB é primary. Git é destino auditável.** Drift detection por-skill via flag `git_sync_mode`. 6 tabelas (5 do 0075 + ajustes + tabela de import seed). 6 telas (5 + drift queue). 4 services novos.

### 1. Fluxos canônicos

**Fluxo 1 — Editar skill via UI (caminho default):**
```
Wagner abre /ads/admin/skills/{slug}/edit
  → Monaco editor + form frontmatter + 4 campos rationale
  → "Submit for review"
  → INSERT em mcp_skill_versions (status implícito: latest, label=staging)
  → UI permite testar (Test Runner → mcp_skill_test_runs)
  → Approval queue
  → Approve → label production move pra esta version (em DB)
  → Skill ativa pro time imediatamente (via tools MCP skills-search/fetch)
  → [opcional, ação separada] "Publish to git" → cria PR + commit em .claude/skills/<slug>/SKILL.md
  → PR mergeado → webhook só CONFIRMA (atualiza git_sha; não cria version nova)
```

**Fluxo 2 — Skill nova criada via UI (dinâmica):**
```
Wagner abre /ads/admin/skills/new
  → Form: slug + frontmatter + body
  → INSERT em mcp_skills (origin='created', git_sync_mode='manual' default)
  → INSERT em mcp_skill_versions v1 (status: draft)
  → Workflow segue Fluxo 1
  → Quando publish to git: cria FOLDER NOVO em .claude/skills/<slug>/SKILL.md via PR
```

**Fluxo 3 — Edição direta no git (drift) — POR-SKILL:**
```
Dev edita .claude/skills/<slug>/SKILL.md no VS Code → commit → push em main
  → webhook GitHub dispara DetectarDriftSkillsHandler
  → para cada SKILL.md tocado:
       SELECT mcp_skills.git_sync_mode WHERE slug = <slug>
       SWITCH:
         'auto'   → cria mcp_skill_versions nova (status: from_git, git_sha setado)
                  → label production move automaticamente pra esta
                  → log em mcp_audit_log
         'manual' → cria mcp_skill_versions nova (status: drift_pending)
                  → INSERT em mcp_skill_drift_alerts (skill_id, version_id, detected_at)
                  → UI /ads/admin/skills/drift mostra fila pra Wagner decidir
                  → Wagner: Accept (vira production) OU Reject (cria PR de revert)
         'pinned' → ignora completamente (skill "congelada" no estado DB)
                  → log em mcp_audit_log com warning
```

**Fluxo 4 — Import inicial (one-time):**
```
php artisan mcp:skills:import-from-git --once
  → Para cada .claude/skills/<slug>/SKILL.md:
       INSERT em mcp_skills (slug, origin='imported', git_sync_mode='manual' default)
       INSERT em mcp_skill_versions v1 (body, frontmatter, git_sha=HEAD)
       INSERT em mcp_skill_labels (production, version_id=v1)
  → Roda 1× depois do deploy de Sprint A
```

### 2. Schema (6 tabelas)

#### 2.1 `mcp_skills` (entidade canônica — **mudanças vs ADR 0075 marcadas com 🆕**)

```sql
CREATE TABLE mcp_skills (
  id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  slug            VARCHAR(100) NOT NULL UNIQUE,
  business_id     BIGINT UNSIGNED NULL,

  source          ENUM('claude-code', 'plugin', 'custom') NOT NULL DEFAULT 'claude-code',
  status          ENUM('draft', 'review', 'published', 'archived') NOT NULL DEFAULT 'draft',
  current_version_id  BIGINT UNSIGNED NULL,
  module          VARCHAR(50) NULL,

  -- 🆕 origem da skill — afeta default de git_sync_mode
  origin          ENUM('imported', 'created') NOT NULL DEFAULT 'imported'
                  COMMENT 'imported = veio do git no seed inicial; created = criada via UI',

  -- 🆕 política por-skill de como reagir a edição direta no git
  git_sync_mode   ENUM('auto', 'manual', 'pinned') NOT NULL DEFAULT 'manual'
                  COMMENT 'auto=aceita drift sem revisar; manual=drift alert (Wagner decide); pinned=ignora git',

  -- 🆕 permite "publish ao approve" sem ação extra
  auto_publish_to_git BOOLEAN NOT NULL DEFAULT FALSE
                  COMMENT 'Quando approve em UI: TRUE = cria PR git automático; FALSE = ação manual separada',

  git_path        VARCHAR(300) NULL
                  COMMENT 'NULL pra skills criadas na UI antes do primeiro publish',

  created_at      TIMESTAMP NULL,
  updated_at      TIMESTAMP NULL,
  deleted_at      TIMESTAMP NULL,

  INDEX idx_skills_status_business (status, business_id),
  INDEX idx_skills_module (module),
  INDEX idx_skills_source (source),
  INDEX idx_skills_sync_mode (git_sync_mode),
  CONSTRAINT fk_skills_current_version FOREIGN KEY (current_version_id)
    REFERENCES mcp_skill_versions(id) ON DELETE SET NULL
);
```

#### 2.2 `mcp_skill_versions` (append-only, **status enum 🆕**)

```sql
CREATE TABLE mcp_skill_versions (
  id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  skill_id        BIGINT UNSIGNED NOT NULL,
  version         INT UNSIGNED NOT NULL,

  body_markdown   MEDIUMTEXT NOT NULL,
  frontmatter_json JSON NOT NULL,

  -- Rationale 4 campos (mantém de 0075)
  rationale_problem        TEXT NULL,
  rationale_hypothesis     TEXT NULL,
  rationale_success_metric TEXT NULL,
  rationale_rollback       TEXT NULL,

  -- 🆕 origem desta version (de onde veio?)
  origin          ENUM('ui', 'git_drift', 'git_seed') NOT NULL DEFAULT 'ui'
                  COMMENT 'ui=editor humano; git_drift=detectado via webhook; git_seed=import inicial',

  -- 🆕 status pra drift workflow
  status          ENUM('draft', 'review', 'published', 'drift_pending', 'archived') NOT NULL DEFAULT 'draft',

  git_sha         VARCHAR(40) NULL,
  pr_number       INT UNSIGNED NULL
                  COMMENT 'Número do PR criado por Publish-to-git (NULL pra versions só em DB)',
  published_to_git_at TIMESTAMP NULL
                  COMMENT 'Quando esta version foi escrita no git (NULL = só em DB)',

  pii_redactions_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,

  created_by      BIGINT UNSIGNED NULL
                  COMMENT 'NULL pra versions origin=git_drift criadas pelo webhook',
  created_at      TIMESTAMP NULL,

  UNIQUE KEY uk_skill_version (skill_id, version),
  INDEX idx_versions_skill_status (skill_id, status),
  INDEX idx_versions_origin (origin),
  CONSTRAINT fk_versions_skill FOREIGN KEY (skill_id) REFERENCES mcp_skills(id) ON DELETE CASCADE
);
```

#### 2.3 `mcp_skill_labels` (igual ADR 0075 — production/staging/dev móveis)

#### 2.4 `mcp_skill_approvals` (igual ADR 0075 — approve/reject + comment + test_runs anexados)

#### 2.5 `mcp_skill_test_runs` (igual ADR 0075 — testes contra inputs reais multi-tenant + PII)

#### 2.6 `mcp_skill_drift_alerts` 🆕 (nova — fila de revisão de drift)

```sql
CREATE TABLE mcp_skill_drift_alerts (
  id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  skill_id        BIGINT UNSIGNED NOT NULL,
  drift_version_id BIGINT UNSIGNED NOT NULL
                  COMMENT 'mcp_skill_versions.id criada com status=drift_pending',
  baseline_version_id BIGINT UNSIGNED NOT NULL
                  COMMENT 'mcp_skill_versions.id que estava em production antes do drift',

  detected_at     TIMESTAMP NOT NULL,
  detected_git_sha VARCHAR(40) NOT NULL,
  detected_pr_number INT UNSIGNED NULL
                  COMMENT 'PR que introduziu o drift (se identificável via webhook payload)',
  detected_author VARCHAR(100) NULL
                  COMMENT 'GitHub username que pushou (pra audit)',

  decision        ENUM('pending', 'accept', 'reject') NOT NULL DEFAULT 'pending',
  decided_by      BIGINT UNSIGNED NULL,
  decided_at      TIMESTAMP NULL,
  decision_comment TEXT NULL,
  revert_pr_number INT UNSIGNED NULL
                  COMMENT 'Se decision=reject: número do PR de revert criado automaticamente',

  INDEX idx_drift_decision (decision, detected_at),
  INDEX idx_drift_skill (skill_id),
  CONSTRAINT fk_drift_skill FOREIGN KEY (skill_id) REFERENCES mcp_skills(id) ON DELETE CASCADE,
  CONSTRAINT fk_drift_version FOREIGN KEY (drift_version_id) REFERENCES mcp_skill_versions(id),
  CONSTRAINT fk_drift_baseline FOREIGN KEY (baseline_version_id) REFERENCES mcp_skill_versions(id)
);
```

### 3. Services (4 novos)

| Service | Quando roda | O que faz |
|---|---|---|
| `ImportarSkillsDoGitService` | One-time via command `mcp:skills:import-from-git` | Lê `glob('.claude/skills/*/SKILL.md')`, parseia frontmatter, INSERT em `mcp_skills` (origin=imported) + version v1 (origin=git_seed) + label production. PII redactor obrigatório. Idempotente. |
| `PublicarSkillNoGitService` | Ação manual UI ou auto se `auto_publish_to_git=true` | Pega version published, gera SKILL.md (frontmatter YAML + body), abre PR via GitHub API com mensagem rationale. Quando merge: webhook seta `pr_number` + `published_to_git_at` na version. |
| `DetectarDriftSkillsService` | Webhook GitHub (push em main que toca `.claude/skills/*/SKILL.md`) | Compara git_sha do commit com latest version do DB. Se diff: roteia por `git_sync_mode` (auto/manual/pinned). Manual cria drift alert. |
| `ResolverDriftAlertService` | Action UI (accept/reject) | Accept: muda status da version pra `published` + label production move. Reject: cria PR de revert via GitHub API + marca alert resolved. |

### 4. UI Inertia (6 telas — 5 do 0075 + 1 nova)

| Tela | Rota | Diferenças vs 0075 |
|---|---|---|
| Lista | `GET /ads/admin/skills` | Coluna nova: `git_sync_mode` (badge auto/manual/pinned). Toggle inline pra mudar. Coluna `auto_publish` toggle. |
| Detalhe | `GET /ads/admin/skills/{slug}` | Mostra timeline com origens (ui/git_drift/git_seed) + estado git (synced/pending publish/drift). |
| Editor | `GET /ads/admin/skills/{slug}/edit` | Igual 0075. Add ação "Publish to git" no header (separada do approve). |
| Test Runner | `GET /ads/admin/skills/{slug}/test` | Igual 0075. |
| Approval Queue | `GET /ads/admin/skills/review` | Igual 0075 (versões UI aguardando approve). |
| **🆕 Drift Queue** | `GET /ads/admin/skills/drift` | **Nova.** Lista versões com `status=drift_pending`. Mostra diff vs baseline + detected_author + detected_pr. Actions: Accept (vira production) / Reject (cria revert PR). |

### 5. Tools MCP (4 — atualizadas)

| Tool | Mudança vs 0075 |
|---|---|
| `skills-search` | Filtra por `status=published` por default (skills draft/drift_pending não aparecem) |
| `skills-fetch` | Aceita parâmetro `version` opcional + retorna `git_sync_mode` da skill |
| `skills-history` | Inclui `origin` em cada version + drift alerts |
| `skills-test-runs` | Igual 0075 |

### 6. Permissions (Spatie — granularidade aumentada)

| Permissão | Quem (V1) | O que libera |
|---|---|---|
| `ads.admin.skills.read` | Time inteiro | Lista + detalhe + history |
| `ads.admin.skills.edit` | W/F | Editor + criar version draft |
| `ads.admin.skills.test` | W/F/M | Test Runner |
| `ads.admin.skills.approve` | Wagner | Approval queue + Drift queue (accept/reject) |
| 🆕 `ads.admin.skills.publish` | Wagner | Publicar version no git (ação separada) |
| 🆕 `ads.admin.skills.config` | Wagner | Toggle `git_sync_mode` + `auto_publish_to_git` por-skill |

### 7. Anti-loop webhook ↔ publish

Risco: `PublicarSkillNoGit` cria PR → merge → webhook detecta → `DetectarDriftSkills` cria drift alert ou nova version → loop.

**Mitigação:** quando `PublicarSkillNoGit` cria o PR, **registra** o `pr_number` na `mcp_skill_versions.pr_number` ANTES do merge. Quando webhook recebe push, primeiro verifica:
```sql
SELECT EXISTS(
  SELECT 1 FROM mcp_skill_versions
  WHERE pr_number = ? AND published_to_git_at IS NULL
)
```
Se TRUE: webhook só **confirma** (seta `published_to_git_at = NOW()`, atualiza `git_sha`), **não cria version nova**. Loop fechado.

## Justificativa

**Por que inverter primary de git pra DB.**

Pesquisa cofre [`prompt_skill_management_2026_05_05.md`](../comparativos/prompt_skill_management_2026_05_05.md) mostra que **todas as 10 ferramentas** (Langfuse, LangSmith, Humanloop, Vellum, PromptLayer, Portkey, Agenta, Helicone, Anthropic Console) usam **DB primary**. Anthropic Skills repo é exceção (git-first) mas é **catálogo público sem governance interna** — Wagner tem requisitos de governance, multi-tenant, LGPD que git-first sozinho não cobre.

Wagner argumentou (2026-05-05): iteração rápida na UI sem PR pra cada experimento. Vale.

**Por que `git_sync_mode` por-skill em vez de global.**

Wagner pediu literal: "eu vou decidir os que eu não quero que atualiza automático, e os que eu quero. Os novos vão ser dinâmicos."
- Skills críticas (ex.: `oimpresso-stack`, `multi-tenant-patterns`) → `manual` (Wagner aprova drift)
- Skills experimentais → `auto` (drift aceito sem fricção)
- Skills "congeladas" pós-aprovação → `pinned` (Wagner decide quando descongelar)
- Skills criadas via UI → `manual` default (origin=created, fluxo dinâmico até estabilizar)

**Por que NÃO violar ADR 0061 (zero auto-mem privada / git-first canônico).**

ADR 0061 cobre **conhecimento canônico**: ADRs (`memory/decisions/`), session logs (`memory/sessions/`), runbooks (`memory/requisitos/<Mod>/`), comparativos (`memory/comparativos/`). Esses **continuam git-first**. Skills (`.claude/skills/<slug>/SKILL.md`) são **artefatos operacionais** com lifecycle próprio — escapam ADR 0061 explicitamente nesta decisão.

**Por que tabela `mcp_skill_drift_alerts` separada.**

Status `drift_pending` em `mcp_skill_versions` poderia bastar, mas alerts têm metadata específico (decided_at, decision_comment, revert_pr_number) que não pertence ao histórico imutável de versions. Separação mantém versions append-only puro + drift alert é audit estruturado.

**Por que `auto_publish_to_git` por-skill em vez de só `git_sync_mode`.**

São 2 dimensões ortogonais:
- `git_sync_mode` = como reagir a **inbound** (git → DB)
- `auto_publish_to_git` = como agir em **outbound** (DB → git, ao approve)

Skill pode ser `git_sync_mode=manual` (Wagner revisa drift) **e** `auto_publish_to_git=true` (approve em UI = PR auto). Combinações válidas.

## Consequências

**Positivas:**
- Iteração rápida na UI sem PR pra cada experimento.
- Skills criadas dinamicamente via UI (sem ferramenta externa).
- Drift detection por-skill — Wagner controla onde quer fricção.
- Multi-tenant nativo + LGPD-compliant + audit completo.
- Anti-loop webhook ↔ publish via `pr_number` tracking.
- Time descobre skills via tools MCP (mesmas 4 do 0075).

**Negativas / Trade-offs:**
- 6 tabelas (vs 5 do 0075). Schema cresceu — esperado pelo escopo.
- Drift queue pode virar gargalo se Wagner sair de férias e ninguém revisar (default `manual`). Mitigação: dashboard mostra alerts > 7 dias pendentes; review trigger formal aciona se passar de 5 alerts/semana.
- Skills criadas via UI sem publish ficam só em DB — risco de "perder" se DB cair. Mitigação: `auto_publish_to_git=true` é opção 1-click; backup MySQL diário cobre.
- Editor humano que pousha direto no git pode ser surpreendido por skill `pinned` (mudança ignorada). Mitigação: warning visível na UI + audit log + email opcional.

**Riscos mitigados:**
- ADR 0075 `proposto` (nunca implementado) → sem migration de schema antigo.
- ADR 0061 preservado pra ADRs/sessions/runbooks (skills explicitamente fora).
- ADR 0053 webhook reusado com handler novo.
- PII redactor existente reusado em test runner.
- Race condition entre publish e drift webhook: tracking via `pr_number` fecha loop.

## Como medir sucesso

V1 entregue + 90 dias em prod:

| Métrica | Alvo | Como medir |
|---|---|---|
| Skills importadas em seed inicial | 16 (todas atuais `.claude/skills/*/SKILL.md`) | `SELECT COUNT(*) FROM mcp_skills WHERE origin='imported'` |
| Skills criadas via UI (dinâmicas) | ≥ 3 | `SELECT COUNT(*) FROM mcp_skills WHERE origin='created'` |
| Versions criadas via UI | ≥ 5 | `SELECT COUNT(*) FROM mcp_skill_versions WHERE origin='ui' AND created_at > V1 launch` |
| Drift alerts decididos em ≤ 24h | ≥ 80% | join `mcp_skill_drift_alerts` decided_at − detected_at |
| Skills com `git_sync_mode=auto` | ≥ 2 | configuração explícita Wagner |
| Skills com `git_sync_mode=pinned` | ≥ 1 | configuração explícita Wagner |
| Publish-to-git funcionando | ≥ 1 PR criado e mergeado via UI | `SELECT COUNT(*) FROM mcp_skill_versions WHERE published_to_git_at IS NOT NULL` |

**Métrica de fé (90 dias):** se time não usar UI ≥ 5×/semana **OU** drift queue acumular > 10 alerts pendentes, **revisita** modelo (default `auto` em vez de `manual`? Aplicar review trigger pra revisitar nesta data).

## Plano de implementação V1

V1 = telas 1+2+3 + drift queue + test runner básico + approval queue. **3 sprints sequenciais** (ou comprime com paralelismo).

| Sprint | Dias | Entrega |
|---|---|---|
| **A** Backend | 5d | 6 migrations + Entities + 4 Services (Importar/Publicar/DetectarDrift/ResolverDrift) + 4 Tools MCP + 8 Pest tests |
| **B** UI core | 5d | 4 Pages React (Index/Show/Edit/Drift) + SkillsController + DriftController + 5 rotas + Spatie permissions seeder + Monaco editor + diff component |
| **C** UI test+approval | 5d | 2 Pages React (Test/Review) + TestRunnerService (PII redactor) + Approval action + Publish-to-git action |

**Comprimível pra 7 dias** com paralelismo. Sprint A é quase independente de B/C — pode rodar em paralelo se 2 devs.

**Sequência recomendada de execução:**
1. **Dia 0** (antes Sprint A): rodar `php artisan mcp:skills:import-from-git --once` em prod pra popular `mcp_skills` com 16 atuais.
2. Sprint A inteiro foca em backend — V0 read-only via tools MCP funciona ao final do A.
3. Sprint B entrega UI navegável (lista + detalhe + editor) — Wagner já testa edição.
4. Sprint C fecha o ciclo (test + approval + publish).

## Não-decisões deliberadas

- ❌ Editor de scripts/refs do folder (skill = mais que SKILL.md) — V2.
- ❌ Branching estilo Agenta variants — V2.
- ❌ Eval automático estilo LangSmith — categoria adjacente ([ADR 0041](0041-stack-qa-ia-vizra-langfuse-deepeval.md)).
- ❌ Webhook bidirecional síncrono (DB → git em real-time) — usa GitHub API com PR (assíncrono, auditável).
- ❌ Substituir `PolicyEngine.php` runtime por DB — princípio ARQ-0006 preservado.

## Referências

- [ADR 0075 (superseded por esta)](0075-team-mcp-skills-ui-prompt-management-style.md)
- [ADR 0072 — Roadmap maturação memória](0072-maturacao-memoria-team-mcp-openclaw-soa-2026.md)
- [ADR 0053 — MCP server governança](0053-mcp-server-governanca-como-produto.md)
- [ADR 0061 — Conhecimento canônico git→MCP (cobre ADRs/sessions, NÃO skills)](0061-conhecimento-canonico-git-mcp-zero-automem.md)
- [Comparativo cofre — Prompt/skill management 2026](../comparativos/prompt_skill_management_2026_05_05.md)
- [Padrão UI ADS `/ads/admin/decisoes` + `/meta-skills`](../../Modules/ADS/Http/Controllers/Admin/) (imitar)
- [Langfuse data-model](https://langfuse.com/docs/prompt-management/data-model)
- [LangSmith manage-prompts](https://docs.langchain.com/langsmith/manage-prompts)
