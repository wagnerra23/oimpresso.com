---
slug: 0075-team-mcp-skills-ui-prompt-management-style
number: 75
title: "Team MCP P0 v2 — UI gestão de skills estilo prompt-management (5 tabelas, 5 telas, approval obrigatório)"
type: adr
status: superseded
authority: reference
lifecycle: substituido
decided_by: [W]
decided_at: "2026-05-05"
module: copiloto
quarter: 2026-Q2
tags: [mcp, team-mcp, skills, prompt-management, governance, approval, p0, superseded]
supersedes:
  - 0073-team-mcp-skills-policies-entidades-governadas
supersedes_partially: []
superseded_by:
  - 0076-skills-db-primary-git-destino-drift-alert
related:
  - 0053-mcp-server-governanca-como-produto
  - 0061-conhecimento-canonico-git-mcp-zero-automem
  - 0072-maturacao-memoria-team-mcp-openclaw-soa-2026
pii: false
review_triggers:
  - "Anthropic publicar UI oficial de gestão de Skills"
  - "Langfuse fechar issue #11284 (approval workflow nativo)"
  - "Time não usar a UI ≥ 5×/semana após 90 dias da V1 → pivota pra read-only"
---

# ADR 0075 — Team MCP P0 v2: UI estilo prompt-management

> **🔴 SUPERSEDED em 2026-05-05 mesmo dia por [ADR 0076](0076-skills-db-primary-git-destino-drift-alert.md).**
> Wagner pediu **inverter o fluxo**: DB primary, git destino auditável (não git → DB). Drift por-skill (auto/manual/pinned). Skills criadas via UI são dinâmicas. ADR 0076 detalha: 6 tabelas (5 + drift_alerts), 6 telas (5 + drift queue), 4 services novos.
>
> **Mantida pra histórico.** Não implementar este schema — implementar 0076.

## Contexto

Esta ADR **supersede [ADR 0073](0073-team-mcp-skills-policies-entidades-governadas.md)** (status: superseded). 0073 propôs `mcp_skills` + `mcp_policies` como espelho governado de `.claude/skills/*/SKILL.md` + `PolicyEngine.php`, com 4 tools MCP simples (search/fetch). Wagner pediu mais (2026-05-05): **versionamento DB+git + governança visível + histórico evolução + rationale "por quê" + testes inline**.

Pesquisa exaustiva ([cofre `prompt_skill_management_2026_05_05.md`](../comparativos/prompt_skill_management_2026_05_05.md)) cobriu 10 ferramentas em 6 categorias (Langfuse/LangSmith/Humanloop/Vellum/PromptLayer/Portkey/Agenta/Helicone/Anthropic Console/Anthropic Skills). Conclusões críticas:

1. **A categoria existe** — chama-se "prompt management". Boom 2024-2025.
2. **Padrões convergentes em ≥3 ferramentas:** versions imutáveis + labels móveis pra deploy / diff view inline / commit message por versão / playground side-by-side / test datasets / environments como ponteiros / RBAC nativo.
3. **NENHUMA das 10 cobre o que Wagner pediu por completo.** 4 gaps abertos no mercado:
   - Bridge filesystem (`.claude/skills/<slug>/` folder com SKILL.md+scripts+refs) ↔ DB ↔ git PR
   - Approval workflow obrigatório pré-`production` ([Langfuse #11284](https://github.com/orgs/langfuse/discussions/11284) aberta)
   - Rationale **estruturado em 4 campos** (problema/hipótese/métrica/rollback) — todas têm só commit-message livre
   - Testes contra inputs **reais multi-tenant** com PII redactor
4. **Inspiração principal:** Langfuse + LangSmith + Anthropic Skills híbrido. Construir nativo Laravel — **NÃO usar Langfuse direto** (mais um daemon JS/TS no CT 100, project-RBAC pago, modela 1 prompt = string).

**Restrições:**
- ADR 0061 — git é fonte canônica (skills permanecem em `.claude/skills/<slug>/SKILL.md`). DB é cache governado + ponto de entrada da UI.
- ADR 0053 — webhook GitHub→DB já existe pra `mcp_memory_documents`. Reusa pipeline.
- Multi-tenant `business_id`. Skills podem ser globais (`business_id NULL`) ou por-tenant.
- LGPD — testes com inputs reais exigem PII redactor obrigatório (mascararDocumentos).

## Decisão

**Construir UI Inertia/React própria** copiando padrões do Langfuse (versions+labels+webhook), LangSmith (diff two-pane), Anthropic Skills (folder-per-skill + git PR governance), Humanloop (approval workflow), Vellum (Test Suites). **5 tabelas + 5 telas + 4 tools MCP**.

### 1. Schema (5 tabelas)

#### 1.1 `mcp_skills` (entidade canônica)

```sql
CREATE TABLE mcp_skills (
  id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  slug            VARCHAR(100) NOT NULL UNIQUE
                  COMMENT 'Nome da skill = pasta = name do frontmatter, ex: ads-decision-flow',
  business_id     BIGINT UNSIGNED NULL
                  COMMENT 'NULL = skill global (visível pra todo tenant). Setado = só esse business',

  source          ENUM('claude-code', 'plugin', 'custom') NOT NULL DEFAULT 'claude-code',
  status          ENUM('draft', 'review', 'published', 'archived') NOT NULL DEFAULT 'draft',
  current_version_id  BIGINT UNSIGNED NULL
                  COMMENT 'FK pra mcp_skill_versions; NULL antes da v1',
  module          VARCHAR(50) NULL
                  COMMENT 'copiloto | ads | financeiro | core | infra (extraído de tags ou path)',

  git_path        VARCHAR(300) NOT NULL
                  COMMENT 'Caminho original: .claude/skills/<slug>/SKILL.md',

  created_at      TIMESTAMP NULL,
  updated_at      TIMESTAMP NULL,
  deleted_at      TIMESTAMP NULL,

  INDEX idx_skills_status_business (status, business_id),
  INDEX idx_skills_module (module),
  INDEX idx_skills_source (source),
  CONSTRAINT fk_skills_current_version FOREIGN KEY (current_version_id)
    REFERENCES mcp_skill_versions(id) ON DELETE SET NULL
);
```

#### 1.2 `mcp_skill_versions` (append-only — histórico imutável)

```sql
CREATE TABLE mcp_skill_versions (
  id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  skill_id        BIGINT UNSIGNED NOT NULL,
  version         INT UNSIGNED NOT NULL
                  COMMENT 'Auto-increment por skill (v1, v2, v3, ...)',

  body_markdown   MEDIUMTEXT NOT NULL
                  COMMENT 'Body do SKILL.md sem frontmatter (já redactado por PII)',
  frontmatter_json JSON NOT NULL
                  COMMENT 'YAML parseado: name, description + extras opcionais',

  -- Rationale estruturado em 4 campos (gap §4.3 do mercado)
  rationale_problem        TEXT NULL
                  COMMENT 'Problema observado que motivou a mudança (ex: skill não matchando em queries de Larissa)',
  rationale_hypothesis     TEXT NULL
                  COMMENT 'Hipótese de fix (ex: ajustar description pra incluir "faturamento")',
  rationale_success_metric TEXT NULL
                  COMMENT 'Como vamos saber que deu certo (ex: hits 7d sobe de 8 → 15)',
  rationale_rollback       TEXT NULL
                  COMMENT 'Plano de rollback se piorar (ex: mover label production de volta pra v3)',

  git_sha         VARCHAR(40) NULL
                  COMMENT 'SHA do commit GitHub que persistiu esta versão',
  pr_number       INT UNSIGNED NULL
                  COMMENT 'Número do PR que mergeou esta versão (audit trail)',

  pii_redactions_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,

  created_by      BIGINT UNSIGNED NOT NULL,
  created_at      TIMESTAMP NULL,

  UNIQUE KEY uk_skill_version (skill_id, version),
  INDEX idx_versions_skill_created (skill_id, created_at),
  CONSTRAINT fk_versions_skill FOREIGN KEY (skill_id) REFERENCES mcp_skills(id) ON DELETE CASCADE,
  CONSTRAINT fk_versions_user FOREIGN KEY (created_by) REFERENCES users(id)
);
```

#### 1.3 `mcp_skill_labels` (Langfuse-style — labels móveis)

```sql
CREATE TABLE mcp_skill_labels (
  id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  skill_id        BIGINT UNSIGNED NOT NULL,
  label           ENUM('production', 'staging', 'dev') NOT NULL,
  version_id      BIGINT UNSIGNED NOT NULL
                  COMMENT 'FK pra mcp_skill_versions — pra qual versão o label aponta',

  -- Audit de movimentação (rollback é mover label, queremos rastreabilidade)
  moved_by        BIGINT UNSIGNED NOT NULL,
  moved_at        TIMESTAMP NOT NULL,
  previous_version_id BIGINT UNSIGNED NULL
                  COMMENT 'Versão anterior antes do label mover (audit)',
  reason          TEXT NULL
                  COMMENT 'Por quê moveu (ex: rollback after regression)',

  UNIQUE KEY uk_skill_label (skill_id, label),
  INDEX idx_labels_version (version_id),
  CONSTRAINT fk_labels_skill FOREIGN KEY (skill_id) REFERENCES mcp_skills(id) ON DELETE CASCADE,
  CONSTRAINT fk_labels_version FOREIGN KEY (version_id) REFERENCES mcp_skill_versions(id),
  CONSTRAINT fk_labels_user FOREIGN KEY (moved_by) REFERENCES users(id)
);
```

#### 1.4 `mcp_skill_approvals` (gap §4.2 do mercado)

```sql
CREATE TABLE mcp_skill_approvals (
  id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  version_id      BIGINT UNSIGNED NOT NULL,

  approver_id     BIGINT UNSIGNED NOT NULL,
  decision        ENUM('approve', 'reject', 'request_changes') NOT NULL,
  comment         TEXT NULL,
  decided_at      TIMESTAMP NOT NULL,

  -- Test runs anexados (não aprova sem ≥1 run verde — regra de processo, validada em service)
  test_runs_count INT UNSIGNED NOT NULL DEFAULT 0,
  test_runs_pass  INT UNSIGNED NOT NULL DEFAULT 0,

  INDEX idx_approvals_version_decided (version_id, decided_at),
  CONSTRAINT fk_approvals_version FOREIGN KEY (version_id) REFERENCES mcp_skill_versions(id),
  CONSTRAINT fk_approvals_user FOREIGN KEY (approver_id) REFERENCES users(id)
);
```

#### 1.5 `mcp_skill_test_runs` (gap §4.4 — testes contra inputs reais multi-tenant)

```sql
CREATE TABLE mcp_skill_test_runs (
  id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  version_id      BIGINT UNSIGNED NOT NULL,

  input_source    ENUM('manual', 'real_conversations', 'fixture') NOT NULL,
  input_json      JSON NOT NULL
                  COMMENT 'Input enviado: prompt + contexto. PII redacted antes de gravar',
  output          MEDIUMTEXT NULL,
  output_tokens   INT UNSIGNED NULL,
  latency_ms      INT UNSIGNED NULL,

  business_id_scope BIGINT UNSIGNED NULL
                  COMMENT 'Se input_source=real_conversations, qual business_id foi usado',
  pii_redactions_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,

  passed          BOOLEAN NULL
                  COMMENT 'Manual pelo dev: clicou approve no resultado? null=não avaliado',
  pass_reason     TEXT NULL,

  executed_by     BIGINT UNSIGNED NOT NULL,
  executed_at     TIMESTAMP NOT NULL,

  INDEX idx_test_runs_version (version_id, executed_at),
  CONSTRAINT fk_test_runs_version FOREIGN KEY (version_id) REFERENCES mcp_skill_versions(id),
  CONSTRAINT fk_test_runs_user FOREIGN KEY (executed_by) REFERENCES users(id)
);
```

### 2. UI Inertia (5 telas)

| Tela | Rota | O que faz |
|---|---|---|
| **Lista** | `GET /ads/admin/skills` | Tabela: slug, status, label production atual, último editor, hits 7d. Filtros: status, módulo, source, scope (global/business). |
| **Detalhe (two-pane LangSmith-style)** | `GET /ads/admin/skills/{slug}` | Esquerda: lista de versions + labels (clicáveis). Direita: markdown render + frontmatter form + toggle "Diff vs vN" (semantic diff frontmatter vs body). |
| **Editor** | `GET /ads/admin/skills/{slug}/edit` | Monaco editor markdown + form frontmatter + **4 campos rationale obrigatórios** + botão "Submit for review" (status `draft` → `review` + abre PR via GitHub API). |
| **Test Runner** | `GET /ads/admin/skills/{slug}/test` | Input: JSON manual OU "últimas N conversas reais do `business_id`" (com PII redactor obrigatório). Side-by-side com versão `production`. Resultado grava em `mcp_skill_test_runs`. |
| **Approval Queue** | `GET /ads/admin/skills/review` | Fila de versions em status `review`. Approver vê diff + rationale + test runs anexados + aprova/rejeita com comment. Approve → merge PR auto via GitHub API → version vira `published` → label `staging` aponta pra ela. |

**Promoção `staging` → `production`** é mover label manualmente (rollback é mover de volta — audit em `mcp_skill_labels.previous_version_id`).

### 3. Tools MCP (4 — backend reusável)

| Tool | Schema | Output |
|---|---|---|
| `skills-search` | `query`, `limit`, `module?`, `status?` | top-N skills por FULLTEXT em `body_markdown + frontmatter_json` (label `production` por default) |
| `skills-fetch` | `slug`, `version?` (default: production) | SKILL.md inteira (body + frontmatter) |
| `skills-history` | `slug`, `limit?` | versions + labels + approvals + last test runs (audit completo) |
| `skills-test-runs` | `slug`, `version?`, `business_id?` | últimos test runs com pass/fail summary |

### 4. Fluxo bidirecional git ↔ DB ↔ filesystem

```
[Filesystem .claude/skills/<slug>/SKILL.md]
              ↑                ↓
           webhook         git pull
              ↑                ↓
[GitHub PR ←——— UI Editor "Submit"]
              ↓
        [DB mcp_skill_versions append-only]
              ↓
        [UI exibe + permite test/diff/approve]
              ↓
        [Approval → merge PR auto]
              ↓
        [Webhook → bumps version + label staging]
              ↓
        [Wagner move label → production manual]
```

**Reusa 100% infra ADR 0053:** webhook GitHub já roda no CT 100 sincronizando `mcp_memory_documents`. Adiciona handler novo pra `.claude/skills/`.

### 5. Permissions (Spatie)

| Permissão | Quem ganha (V1) | O que libera |
|---|---|---|
| `ads.admin.skills.read` | Time inteiro (W/F/M/L/E) | Ver lista + detalhe + history + test runs |
| `ads.admin.skills.edit` | W/F (V1) | Editor + submit for review |
| `ads.admin.skills.test` | W/F/M | Rodar test runner |
| `ads.admin.skills.approve` | Wagner (V1) | Approval queue + decisão |
| `ads.admin.skills.publish` | Wagner | Mover label `production` |

`approve` ≠ `publish` — segundo par de olhos virá quando time crescer. V1 é Wagner em ambos.

### 6. Testes anti-regressão (Pest)

- `tests/Feature/Mcp/SkillsSyncTest.php` — webhook GitHub atualiza `mcp_skill_versions` ao merge de PR.
- `tests/Feature/Mcp/SkillsApprovalTest.php` — versão sem ≥1 test run verde não pode ser aprovada.
- `tests/Feature/Mcp/SkillsLabelMoveTest.php` — mover label `production` registra `previous_version_id` em `mcp_skill_labels` (rollback path).
- `tests/Feature/Mcp/SkillsRationaleRequiredTest.php` — submit for review sem 4 campos rationale falha.
- `tests/Feature/Mcp/SkillsPiiRedactorTest.php` — test runner com input real chama PII redactor antes de gravar.
- `tests/Feature/Skills/SkillsControllerTest.php` — 5 rotas Inertia retornam 200 com permission correta + 403 sem.

## Justificativa

**Por que NÃO usar Langfuse direto.** Analisado em [`prompt_skill_management_2026_05_05.md`](../comparativos/prompt_skill_management_2026_05_05.md) §7 caminho A. 4 razões: (a) +1 daemon JS/TS no CT 100, (b) project-level RBAC só Enterprise, (c) modela 1 prompt = string, não folder Anthropic Skills style, (d) JS SDK runtime = dependency externa indesejada num projeto Laravel monolith.

**Por que 5 tabelas em vez de estender `mcp_memory_documents`.** Skills têm `body_markdown` + `frontmatter_json` separados (queries estruturadas: "skills do módulo copiloto" via JSON_EXTRACT). Versions append-only + labels móveis exigem schema separado. Approvals + test runs são entidades distintas. Estender 1 tabela genérica = polui semantica + perde performance em FULLTEXT.

**Por que rationale em 4 campos estruturados.** Commit message livre (Langfuse, LangSmith, Agenta) é o que TODOS fazem. Mas Wagner viu valor em forçar pensamento Anthropic-style: "qual problema observei? qual hipótese? como meço sucesso? qual rollback?". Custo: 4 textareas obrigatórios. Ganho: zero edição às cegas + auditoria de aprendizado.

**Por que approval obrigatório pré-`production`.** Resolve gap §4.2 do mercado (Langfuse #11284 aberta há 6 meses). Fricção mínima (V1 Wagner aprova só dele mesmo). Quando time crescer, vira segundo par de olhos sem mudar schema.

**Por que test runner com inputs reais multi-tenant.** Resolve gap §4.4. Playgrounds dos concorrentes são single-tenant + input mock. Skill nova testada contra 50 conversas reais do `business_id=4` (Larissa) + PII redactor + side-by-side com `production` = sinal real antes de aprovar. Ninguém faz.

**Por que `business_id NULL = global` em `mcp_skills`.** Skill `criar-modulo` é universal. Skill custom de Larissa (biz=4) só ela vê. Multi-tenant nativo. Ninguém faz.

**Por que SUPERSEDE 0073 em vez de complementar.** Schema 0073 (`mcp_skills` simples + `mcp_policies`) é subset deste. ADR 0073 ainda em status `proposto` — sem implementação que precise migrar. `mcp_policies` (espelho do PolicyEngine) **não entra mais nesta ADR** — vira ADR separada futura se aparecer demanda. Não pertence ao mesmo escopo de "UI estilo prompt-management".

## Consequências

**Positivas:**
- Wagner edita skill via UI nativa, vê history, aprova com rationale estruturado.
- Time MCP completo: Felipe/Maíra/Luiz/Eliana descobrem via `skills-search` tool MCP **e** via UI.
- Multi-tenant nativo (skills custom por business_id) — diferencial vs 100% das ferramentas de mercado.
- Test runner com inputs reais previne regressão silenciosa em prod.
- Approval workflow obrigatório força segundo par de olhos.
- Bridge filesystem ↔ DB ↔ git completo (3 lados sincronizados).
- 4 campos rationale forçam pensamento estruturado antes de mudar.

**Negativas / Trade-offs:**
- 5 tabelas novas (vs 2 do ADR 0073). Schema cresceu — esperado pra cobrir o pedido completo.
- 5 telas Inertia + 1 Editor Monaco + 1 Diff component = trabalho de UI maior.
- Approval queue gera fricção pra mudanças triviais (typo, exemplo). Mitigação: V2 pode ter "fast-track" pra mudanças de baixo impacto via flag.
- Time precisa adotar fluxo "submit for review" em vez de PR git direto. Risco: continuar editando direto via VS Code + git. Mitigação: medir uso UI ≥ 5×/semana em 90 dias (review trigger).
- Estimativa pode estourar — V1 são 3 sprints (~15 dias úteis), comprime pra 7d em paralelo só com foco total.

**Riscos mitigados:**
- ADR 0073 está superseded — sem migration de schema antigo (não existia em prod).
- ADR 0061 git-first preservado — DB é cache, edição **sempre** vira PR.
- ADR 0053 webhook reusado — sem novo daemon.
- PII redactor existente reusado em test runner.
- `mcp_audit_log` cobre auditoria automaticamente.

## Como medir sucesso

V1 entregue + 90 dias em prod:

| Métrica | Alvo | Como medir |
|---|---|---|
| Skills indexadas | ≥ 16 (todas as `.claude/skills/*/SKILL.md`) | `SELECT COUNT(*) FROM mcp_skills WHERE deleted_at IS NULL` |
| Versões criadas via UI | ≥ 5 | `SELECT COUNT(*) FROM mcp_skill_versions WHERE created_at > V1 launch` |
| Test runs em versões aprovadas | ≥ 80% das aprovações | `mcp_skill_approvals` join `mcp_skill_test_runs` |
| Adoção UI vs git direto (review trigger) | ≥ 5×/semana W+F+M | `mcp_audit_log` filter route `/ads/admin/skills/*/edit` |
| Rationale 4-campos preenchido | 100% | constraint NOT NULL no submit |
| Rollback 1-click via label | ≥ 1 caso real auditado | `mcp_skill_labels.previous_version_id` populated |

**Métrica de fé (90 dias):** se time não usar UI ≥ 5×/semana, **pivota pra V0 read-only** (catálogo só pra busca/leitura) e mantém edição via VS Code+git. Risco real — fricção de approval pode empurrar time pra atalho git.

## Plano de implementação (estimativa V1)

V1 = telas 1+2+3 + test runner básico + approval queue. **3 sprints sequenciais (~15 dias)** ou comprime pra **7 dias** com paralelismo total.

| Sprint | Dias | Entrega | Files tocados |
|---|---|---|---|
| **A** Backend | 5d | 5 migrations + Entities + IndexarSkillsParaDb + 4 Tools MCP + 6 Pest tests | `Modules/Copiloto/Database/Migrations/` + `Entities/Mcp/` + `Services/Mcp/` + `Mcp/Tools/` |
| **B** UI Lista + Detalhe + Editor | 5d | 3 Pages React + SkillsController + 4 rotas + Monaco editor + diff two-pane semantic + Spatie permissions seeder | `Modules/ADS/Http/Controllers/Admin/SkillsController.php` + `resources/js/Pages/ads/Admin/Skills*.tsx` |
| **C** UI Test + Approval | 5d | 2 Pages React + TestRunnerService (chama `laravel/ai` + PII redactor) + ApprovalQueue + 2 actions | `resources/js/Pages/ads/Admin/Skills{Test,Approval}.tsx` + Services |

**Buffer/V2 (não entra V1):** branching estilo Agenta variants, eval automático estilo LangSmith, fast-track sem approval, edição inline scripts/refs do folder, integração CI/CD pra rebuild quando merger.

## Não-decisões deliberadas (fora do escopo)

- ❌ Adotar Langfuse SDK como dependency runtime — analisado, contraindica ([cofre](../comparativos/prompt_skill_management_2026_05_05.md) §7.A).
- ❌ Workflow visual estilo Vellum — overkill V1.
- ❌ Eval automático estilo LangSmith — categoria adjacente coberta em [ADR 0041](0041-stack-qa-ia-vizra-langfuse-deepeval.md).
- ❌ Edição via UI sem PR git — quebra ADR 0061.
- ❌ Substituir `PolicyEngine.php` runtime por DB — princípio ARQ-0006 preservado.
- ❌ `mcp_policies` espelho do PolicyEngine — ADR 0073 propunha; tirado deste escopo. Vira ADR separada futura se aparecer demanda.

## Referências

- [ADR 0072 — Roadmap maturação memória + Team MCP](0072-maturacao-memoria-team-mcp-openclaw-soa-2026.md)
- [ADR 0073 — Team MCP P0 (superseded por esta ADR)](0073-team-mcp-skills-policies-entidades-governadas.md)
- [ADR 0053 — MCP server governança como produto](0053-mcp-server-governanca-como-produto.md)
- [ADR 0061 — Conhecimento canônico git→MCP, zero auto-mem](0061-conhecimento-canonico-git-mcp-zero-automem.md)
- [Comparativo cofre — Prompt/skill management 2026](../comparativos/prompt_skill_management_2026_05_05.md)
- [ARQ-0006 — Policy Engine firewall](../requisitos/ADS/adr/arq/ARQ-0006-policy-engine-firewall.md)
- [Padrão UI ADS `/ads/admin/decisoes` + `/meta-skills`](../../Modules/ADS/Http/Controllers/Admin/) (imitar)
- [Langfuse data-model](https://langfuse.com/docs/prompt-management/data-model)
- [LangSmith Diff View](https://changelog.langchain.com/announcements/diff-view-in-langsmith-s-prompt-hub)
- [Anthropic Skills repo](https://github.com/anthropics/skills)
