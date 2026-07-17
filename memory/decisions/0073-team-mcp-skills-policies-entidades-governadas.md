---
slug: 0073-team-mcp-skills-policies-entidades-governadas
number: 73
title: "Team MCP P0 — skills e policies como entidades governadas (mcp_skills + mcp_policies)"
type: adr
status: superseded
authority: reference
lifecycle: substituido
decided_by: [W]
decided_at: "2026-05-05"
module: copiloto
quarter: 2026-Q2
tags: [mcp, team-mcp, skills, policies, governanca, sync, p0, superseded]
supersedes: []
supersedes_partially: []
superseded_by:
  - 0075-team-mcp-skills-ui-prompt-management-style
related:
  - 0053-mcp-server-governanca-como-produto
  - 0061-conhecimento-canonico-git-mcp-zero-automem
  - 0072-maturacao-memoria-team-mcp-openclaw-soa-2026
pii: false
review_triggers:
  - "Anthropic publicar spec oficial pra skills serializadas"
  - "Letta atingir GA com sleep-time agents (revisitar policies estilo Letta blocks)"
---

# ADR 0073 — Team MCP P0: skills e policies como entidades governadas

> **🔴 SUPERSEDED em 2026-05-05 mesmo dia por [ADR 0075](0075-team-mcp-skills-ui-prompt-management-style.md).**
> Wagner pediu UI mais rica (versionamento DB+git + governance + history + rationale + testes inline). Pesquisa mostrou categoria de "prompt management" cobrindo o pedido. ADR 0075 expande este P0 com 5 tabelas + 5 telas + approval workflow + folder-per-skill. `mcp_policies` (espelho do PolicyEngine) saiu de escopo aqui — vira ADR separada se demanda aparecer.
>
> **Mantida pra histórico.** Não implementar este schema — implementar 0075.

## Contexto

[ADR 0072](0072-maturacao-memoria-team-mcp-openclaw-soa-2026.md) priorizou **P0 = Team MCP completo** como primeiro movimento. Esta ADR detalha o schema, fluxo de sync e tools MCP novas. Implementa o pedido literal de Wagner: *"entregar Team MCP pra regras e conhecimento ficar centralizado"*.

**Estado atual (2026-05-05):**

- **Skills do Claude Code** vivem em `.claude/skills/<nome>/SKILL.md` (versionadas em git). Outro dev (Felipe/Maíra/Luiz/Eliana) só recebe skill nova após `git pull` + reiniciar Claude Code. **Validado** que existem 16 skills hoje (incluindo `ads-decision-flow` e `memoria-recall-flow` mergeadas em PR `claude/stupefied-margulis-ec39fa`, commit `5ebd107e`).
- **Policies do ADS** vivem hardcoded em [`Modules/ADS/Services/PolicyEngine.php`](../../Modules/ADS/Services/PolicyEngine.php) — listas `BLOCK_ALWAYS`, `REQUIRE_BRAIN_B`, `REQUIRE_HUMAN_REVIEW`, `ALLOW_BRAIN_A`. Auditoria de "quem aprovou esta regra" e "quando" exige `git blame` + lookup manual.
- **Já existe infra de sync git→DB→Scout**: [`mcp_memory_documents`](../../Modules/Copiloto/Database/Migrations/2026_04_29_100008_create_mcp_memory_documents_table.php) (ADR 0053), [`IndexarMemoryGitParaDb`](../../Modules/Copiloto/Services/Mcp/IndexarMemoryGitParaDb.php), [`McpSyncMemoryCommand`](../../Modules/Copiloto/Console/Commands/McpSyncMemoryCommand.php), webhook GitHub. Skills/policies podem **reusar** o mesmo pipeline.
- **Tools MCP atuais** (18 em [`Modules/Copiloto/Mcp/Tools/`](../../Modules/Copiloto/Mcp/Tools/)) seguem padrão `class XxxTool extends Tool` com `$name`, `$description`, `schema()`, `handle()`. Adicionar 4 tools novas é trivial.

**Restrições:**

- Multi-tenant `business_id` (LGPD) — skills/policies são **cross-tenant** (mesma skill vale pra biz=4 e biz=1), mas tools de leitura precisam respeitar permissões Spatie por dev.
- ADR 0061 — zero auto-mem privada. Tudo segue git → webhook → DB.
- Não tocar `PolicyEngine` ADS hardcoded ainda — `mcp_policies` é **espelho governado pra leitura/auditoria**, não substitui o firewall em runtime. Substituição vira ADR 0074+ se sinal aparecer.

## Decisão

Criar **2 tabelas** + **1 service de sync** + **4 tools MCP novas**, todas reusando padrão de [`mcp_memory_documents`](../../Modules/Copiloto/Database/Migrations/2026_04_29_100008_create_mcp_memory_documents_table.php).

### 1. Tabela `mcp_skills`

```sql
CREATE TABLE mcp_skills (
  id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  slug            VARCHAR(100) NOT NULL UNIQUE
                  COMMENT 'Nome da skill = pasta = name do frontmatter, ex: ads-decision-flow',
  source          ENUM('claude-code', 'plugin', 'custom') NOT NULL DEFAULT 'claude-code'
                  COMMENT 'claude-code = .claude/skills/; plugin = ~/.claude/plugins/; custom = futura UI',

  title           VARCHAR(250) NOT NULL,
  description     TEXT NOT NULL
                  COMMENT 'Frontmatter description — usado pelo harness pra matching',
  body_md         MEDIUMTEXT NOT NULL
                  COMMENT 'Body do SKILL.md sem frontmatter (já redactado por PII)',
  frontmatter     JSON NOT NULL
                  COMMENT 'YAML parseado: name, description + extras opcionais',

  scope_required  VARCHAR(100) NULL
                  COMMENT 'Se setado, exige Spatie permission. null = pública pra time',
  admin_only      BOOLEAN NOT NULL DEFAULT FALSE,
  module          VARCHAR(50) NULL
                  COMMENT 'copiloto | ads | financeiro | core | infra (extraído de tags ou path)',

  git_sha         VARCHAR(40) NULL,
  git_path        VARCHAR(300) NOT NULL
                  COMMENT 'Caminho original: .claude/skills/<slug>/SKILL.md',

  pii_redactions_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,

  indexed_at      TIMESTAMP NULL,
  created_at      TIMESTAMP NULL,
  updated_at      TIMESTAMP NULL,
  deleted_at      TIMESTAMP NULL,

  INDEX mcp_skills_source_idx (source),
  INDEX mcp_skills_module_idx (module),
  INDEX mcp_skills_perms_idx (scope_required, admin_only),
  FULLTEXT mcp_skills_fulltext_idx (title, description, body_md)
);
```

**Decisões de design:**
- `slug = nome da pasta` é única chave funcional (mesma de `mcp_memory_documents`).
- `body_md` separado de `frontmatter` (JSON) pra queries estruturadas (ex.: "skills do módulo copiloto" via `JSON_EXTRACT(frontmatter, '$.module')`).
- `source` antecipa skills de plugin (Anthropic-skills:*) e UI custom — não bloqueia futuro.

### 2. Tabela `mcp_policies`

```sql
CREATE TABLE mcp_policies (
  id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  slug            VARCHAR(100) NOT NULL UNIQUE
                  COMMENT 'Ex: ads-block-always-env-files, ads-require-brainb-migrations',

  category        ENUM('block_always', 'require_brain_b', 'require_human_review', 'allow_brain_a') NOT NULL
                  COMMENT 'Espelha PolicyEngine ADS (ARQ-0006)',
  source          ENUM('ads', 'compliance', 'security', 'manual') NOT NULL DEFAULT 'ads'
                  COMMENT 'ads = espelho do PolicyEngine.php; outras = futuro',
  active          BOOLEAN NOT NULL DEFAULT TRUE
                  COMMENT 'Inativa não impede runtime do PolicyEngine — só some das tools de leitura',

  title           VARCHAR(250) NOT NULL,
  description     TEXT NOT NULL,
  pattern         VARCHAR(500) NOT NULL
                  COMMENT 'Glob/regex/path absoluto da regra. Ex: "**/.env*", "Modules/ADS/Services/PolicyEngine.php"',
  rationale_md    MEDIUMTEXT NULL
                  COMMENT 'Por quê existe (justificativa). Markdown.',

  decided_by      VARCHAR(50) NULL COMMENT 'W | F | M | L | E (mesmo vocabulário das ADRs)',
  decided_at      DATE NULL,
  related_adr     VARCHAR(50) NULL COMMENT 'Slug da ADR que motivou (ex: ARQ-0006)',

  git_sha         VARCHAR(40) NULL,
  git_path        VARCHAR(300) NULL
                  COMMENT 'Onde a regra é definida em código (PolicyEngine.php) ou em memory/policies/<slug>.md (futuro)',

  indexed_at      TIMESTAMP NULL,
  created_at      TIMESTAMP NULL,
  updated_at      TIMESTAMP NULL,
  deleted_at      TIMESTAMP NULL,

  INDEX mcp_policies_category_idx (category, active),
  INDEX mcp_policies_source_idx (source),
  FULLTEXT mcp_policies_fulltext_idx (title, description, pattern, rationale_md)
);
```

**Decisões de design:**
- `category` espelha vocabulário canônico de [ARQ-0006](../../memory/requisitos/ADS/adr/arq/ARQ-0006-policy-engine-firewall.md). Se ARQ-0006 evoluir, esta ENUM evolui via migration.
- `active = false` é **soft-disable de visibilidade** — não desliga firewall em runtime (que continua código). Mudar isso exigiria nova ADR (ADR 0074+).
- `rationale_md` separado de `description` permite raciocínio longo sem poluir o resumo.

### 3. Service de sync — `IndexarSkillsParaDb` e `IndexarPoliciesParaDb`

Imitam [`IndexarMemoryGitParaDb`](../../Modules/Copiloto/Services/Mcp/IndexarMemoryGitParaDb.php) — mesma interface, fontes diferentes:

| Service | Lê de | Comando wrapper |
|---|---|---|
| `IndexarSkillsParaDb` | `glob('.claude/skills/*/SKILL.md')` | `php artisan mcp:sync-skills` |
| `IndexarPoliciesParaDb` | Reflection sobre `Modules\ADS\Services\PolicyEngine` (constantes públicas) | `php artisan mcp:sync-policies` |

**Webhook GitHub** existente (do ADR 0053) ganha 2 chamadas extras — sync de skills/policies em paralelo a `mcp:sync-memory`. Latência alvo mantida: < 60s pós-push.

### 4. Tools MCP novas (4)

| Tool | Schema (input) | Output |
|---|---|---|
| `skills-search` | `query: string`, `limit: int (default 5)`, `module: string?` | top-N skills com slug + título + trecho relevante (FULLTEXT MySQL) |
| `skills-fetch` | `slug: string` | SKILL.md inteira (frontmatter + body) |
| `policies-active` | `category?: enum`, `source?: enum` | listagem de policies com `active=true`, ordenadas por category |
| `policies-fetch` | `slug: string` | policy completa (incluindo `rationale_md` + `related_adr`) |

Implementação: 4 classes em [`Modules/Copiloto/Mcp/Tools/`](../../Modules/Copiloto/Mcp/Tools/), seguindo padrão de [`DecisionsSearchTool`](../../Modules/Copiloto/Mcp/Tools/DecisionsSearchTool.php) e [`DecisionsFetchTool`](../../Modules/Copiloto/Mcp/Tools/DecisionsFetchTool.php). Registradas em [`OimpressoMcpServer.php`](../../Modules/Copiloto/Mcp/OimpressoMcpServer.php).

### 5. RBAC (Spatie)

| Permissão (Spatie) | Quem ganha | Tool |
|---|---|---|
| `copiloto.mcp.skills.read` | Todo dev autenticado | `skills-search`, `skills-fetch` |
| `copiloto.mcp.policies.read` | Todo dev autenticado | `policies-active`, `policies-fetch` |
| `copiloto.mcp.skills.manage` | Wagner (e quem ele autorizar) | UI `/copiloto/admin/skills` (futuro, fora desta ADR) |
| `copiloto.mcp.policies.manage` | Wagner | UI `/copiloto/admin/policies` (futuro) |

UIs de gerenciamento são **fora do escopo P0** — entram só quando aparecer demanda (gestão hoje é via PR git).

### 6. Testes anti-regressão

Imitam padrão de [`tests/Feature/Skills/SkillReferencesTest.php`](../../tests/Feature/Skills/SkillReferencesTest.php) recém-criado:

- `tests/Feature/Mcp/SkillsSyncTest.php` — verifica que `IndexarSkillsParaDb::run()` indexa todos os SKILL.md em git, com PII redactor, idempotente.
- `tests/Feature/Mcp/PoliciesSyncTest.php` — verifica que constantes de `PolicyEngine` viraram linhas em `mcp_policies` (ordem mantida, slug previsível).
- `tests/Feature/Mcp/SkillsSearchTest.php` — full-text retorna ≥1 resultado pra query "ads-decision-flow"; respeita `module` filter.
- `tests/Feature/Mcp/PoliciesActiveTest.php` — `active=false` some da listagem; `category` filter funciona.

Smoke standalone (sem vendor): adicionar entry em `tests/Feature/Skills/smoke-skill-references.php` que valida frontmatter de skills sincronizadas.

## Justificativa

**Por que tabelas separadas (não reusar `mcp_memory_documents`)?**
- `mcp_memory_documents` tem `type ENUM('adr','session','reference','spec','handoff','current','tasks','other')` — adicionar `'skill'` e `'policy'` poluiria semanticamente. Skills têm `body_md` + `frontmatter` separados; policies têm `category` + `pattern` que não fazem sentido em memory_documents.
- Tabelas separadas = índices dedicados (FULLTEXT por categoria) = queries 5-10× mais rápidas em catálogos pequenos.
- Migrations futuras (ex.: P3 do ADR 0072 — `skill_hint` em recall) ficam contidas.

**Por que NÃO substituir PolicyEngine.php em runtime?**
- Firewall em DB tem 1 dependência adicional (DB up) vs PHP puro (sempre disponível). Em modo "Brain A não consegue acessar DB" (incidente raro mas existe), firewall vira no-op = perigo.
- ARQ-0006 já fala que PolicyEngine é "código PHP puro, sem DB" — princípio canônico. Mudar exigiria ADR superseder.
- `mcp_policies` é **plano de leitura** (auditoria, MCP discovery, UI Wagner). Runtime continua código. Se aparecer demanda forte (Wagner: "preciso editar policy sem deploy"), vira ADR 0074+ com 2-fase commit.

**Por que reflection sobre `PolicyEngine` em vez de YAML?**
- Single source of truth. Hoje o código É a verdade. Mudar pra YAML+code-gen = 2 fontes pra divergir.
- Adicionar uma nova policy continua sendo PR em PHP — nada muda no fluxo de Wagner.
- Quando vier ADR de "edição UI sem deploy", reverte: YAML/DB vira fonte, code-gen produz PHP.

**Por que slug humano em `mcp_policies` em vez de hash do pattern?**
- Auditoria. `policies-fetch slug:ads-block-always-env-files` é legível em log; hash não é.
- PR review: revisor lê slug e entende a regra antes de aprovar mudança.

## Consequências

**Positivas:**
- Time MCP completo no critério de aceite literal (ADR 0072 P0): Felipe/Maíra/Luiz/Eliana enxergam skills/policies via tools MCP, sem `git pull`.
- Auditoria de "qual policy estava ativa em data X" via [tabela history espelhando `mcp_memory_documents_history`](../../Modules/Copiloto/Database/Migrations/2026_04_29_100009_create_mcp_memory_documents_history_table.php) (mesmo trigger pattern).
- ADR 0072 destrava P1 (temporal validity) — schema lá pode reusar coluna `valid_from/valid_until` adicionada agora se aplicável.
- Reusa 100% da infra ADR 0053 (sync, redactor, history, RBAC). Menor superfície de teste novo.

**Negativas / Trade-offs:**
- 2 tabelas novas em `Modules/Copiloto/Database/Migrations/`. Schema cresceu — esperado.
- Webhook GitHub fica com 3 jobs (memory + skills + policies) — latência sobe ~40% (de ~25s pra ~35s estimado). Aceitável.
- Reflection sobre `PolicyEngine` tem ponto de ruptura: se ARQ-0006 mudar nome de constante (ex.: `BLOCK_ALWAYS` → `BLOCKED`), `IndexarPoliciesParaDb` quebra. Mitigação: teste anti-regressão (`PoliciesSyncTest`) detecta no PR antes de merge.
- Skills carregadas pelo harness Claude Code continuam vindo do filesystem (Anthropic não suporta carregar de DB hoje). MCP tools só **expõem leitura** — não substituem mecanismo de carga. Esperado e documentado.

**Riscos mitigados:**
- Sem alteração runtime do firewall = zero risco de incidente em produção.
- PII redactor já testado em `mcp_memory_documents` (1 ano em prod sem incidente) reusado.
- Soft-delete LGPD existente cobre skills/policies sem schema novo.

## Como medir sucesso

Checkpoint após sprint de implementação:

| Métrica | Alvo | Como medir |
|---|---|---|
| Skills indexadas | 16+ (todas as `.claude/skills/*/SKILL.md`) | `SELECT COUNT(*) FROM mcp_skills WHERE deleted_at IS NULL` |
| Policies indexadas | ≥ 4 categorias × N regras (4 ENUMs do PolicyEngine) | `SELECT category, COUNT(*) FROM mcp_policies GROUP BY category` |
| Latência sync git→DB | < 60s pós-push (mantido) | webhook log + timestamp `indexed_at` |
| Devs validando | Felipe + Maíra usaram `skills-search` em 7 dias | `mcp_audit_log` filter por user + tool |
| Zero regressão runtime | PolicyEngine inalterado | suite Pest verde, `git diff Modules/ADS/Services/PolicyEngine.php` vazio |

Se algum não bater no fim do sprint, ADR fica `aceito` mas com tarefa de débito explícita em `mcp_tasks`.

## Plano de implementação (sprint, 5 dias úteis)

| Dia | Entrega | Files tocados |
|---|---|---|
| 1 | Migrations `mcp_skills` + `mcp_policies` + history tables | 4 arquivos em `Modules/Copiloto/Database/Migrations/` |
| 2 | Entities + `IndexarSkillsParaDb` + `IndexarPoliciesParaDb` | 4 services em `Modules/Copiloto/Services/Mcp/` + 2 entities |
| 3 | Commands `mcp:sync-skills` + `mcp:sync-policies` + integrar no webhook handler | 2 commands + 1 controller alterado |
| 4 | 4 Tools MCP (`skills-search/fetch`, `policies-active/fetch`) + registro em `OimpressoMcpServer` | 5 arquivos |
| 5 | Testes Pest + RBAC permissions + smoke | 4 testes + seeder de permissions |

Não criar UI `/admin/skills` ou `/admin/policies` neste sprint — backlog separado. Validar primeiro via tools MCP por 2 semanas antes de investir em UI.

## Erratum — 2026-05-05 (mesmo dia, levantamento exaustivo)

Levantamento confirmou: **0% das tabelas e tools de P0 existem hoje**. ADR 0073 está bem dimensionada — plano de 5 dias mantém, infra de [ADR 0053](0053-mcp-server-governanca-como-produto.md) é 100% reusável.

**Único ajuste:** o levantamento contou **40 tabelas `mcp_*` + `copiloto_*`** já existentes (eu estimei ~15 ao escrever o ADR). Adicionar `mcp_skills` + `mcp_policies` não pesa em nada esse universo. Padrão das **18 tools MCP** existentes em [`Modules/Copiloto/Mcp/Tools/`](../../Modules/Copiloto/Mcp/Tools/) é claro e reusa autenticação/RBAC/audit já consolidados.

**ADR 0071** (auditoria 2026-05-05 — bugs e workarounds em tools MCP) é dependência implícita: se houver bugs nos 18 tools, podem afetar wiring das 4 novas. Verificar antes de iniciar Sprint P0.

**Status:** mantido em `proposto`. Pronto para implementação.

## Referências

- [ADR 0072 — Maturação memória + Team MCP (P0–P3)](0072-maturacao-memoria-team-mcp-openclaw-soa-2026.md)
- [ADR 0053 — MCP server governança como produto](0053-mcp-server-governanca-como-produto.md)
- [ADR 0061 — Conhecimento canônico git→MCP, zero auto-mem](0061-conhecimento-canonico-git-mcp-zero-automem.md)
- [ARQ-0006 — Policy Engine firewall](../../memory/requisitos/ADS/adr/arq/ARQ-0006-policy-engine-firewall.md)
- [Migration `mcp_memory_documents`](../../Modules/Copiloto/Database/Migrations/2026_04_29_100008_create_mcp_memory_documents_table.php)
- [Padrão `IndexarMemoryGitParaDb`](../../Modules/Copiloto/Services/Mcp/IndexarMemoryGitParaDb.php)
- [Padrão `DecisionsSearchTool`](../../Modules/Copiloto/Mcp/Tools/DecisionsSearchTool.php)
- [`PolicyEngine.php`](../../Modules/ADS/Services/PolicyEngine.php) (fonte de espelhamento)
