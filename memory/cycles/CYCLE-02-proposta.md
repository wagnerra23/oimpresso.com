# CYCLE-02 — Proposta de ciclo de desenvolvimento (revisão 2)

> **Status:** proposto · pendente de aceite Wagner (criar via UI/SQL — `cycles-create` tool não exposta).
> **Janela:** 2026-05-13 → 2026-05-26 (10 dias úteis após CYCLE-01 fechar em 2026-05-12).
> **Project:** COPI
>
> **🔄 REVISÃO 2 (2026-05-05 mesmo dia):** Wagner pediu UI rica de prompt management ([ADR 0075](../decisions/0075-team-mcp-skills-ui-prompt-management-style.md) supersede 0073). P0 cresceu de 5d → 15d. **Não cabe mais em 1 cycle.** Plano novo: P0 vira **CYCLE-02 + CYCLE-03** (2 cycles dedicados). P1 (bi-temporal) escorrega pra CYCLE-04. Detalhes nas seções de Sprint abaixo.

## Contexto

CYCLE-01 fecha em 2026-05-12 com 2/3 goals batidos (Larissa OK, recall_chars OK, dashboard custos pendente). Sessão 2026-05-05 produziu 4 ADRs novas mergeadas em main (commit `70ee8dde`):

- [ADR 0072](../decisions/0072-maturacao-memoria-team-mcp-openclaw-soa-2026.md) — Roadmap memória + Team MCP (P0–P3)
- [ADR 0073](../decisions/0073-team-mcp-skills-policies-entidades-governadas.md) — P0 detalhado (mcp_skills + mcp_policies + 4 tools MCP)
- [ADR 0074](../decisions/0074-temporal-validity-bi-temporal-time-travel.md) — P1 detalhado (bi-temporal)
- Erratum nas 3 acima após levantamento do estado real

**Pivô descoberto:** Wagner pediu UI de gerenciamento via `https://oimpresso.com/ads/admin/skills`. Originalmente fora de escopo P0, agora dentro.

## Goal (outcome-oriented) — revisão 2

> **CYCLE-02:** UI Skills V0.5 funcional — Wagner navega `/ads/admin/skills`, vê 16 skills sincronizadas via webhook, edita uma, vê history+diff+rationale 4 campos. Time descobre via `skills-search` tool MCP. (Test runner + approval queue ficam pro CYCLE-03.)
>
> **CYCLE-03 (planejado, não inicia ainda):** UI Skills V1 completo — test runner contra inputs reais multi-tenant + approval queue obrigatório.
>
> **CYCLE-04 (planejado):** P1 bi-temporal (`event_valid_*` + tool `memoria-historica`). Adiado de CYCLE-02.

## Goals trackados — revisão 2 (CYCLE-02)

| # | Métrica | Alvo | Como medir |
|---|---|---|---|
| 1 | Skills indexadas em `mcp_skills` | ≥ 16 (todas `.claude/skills/*/SKILL.md`) | `SELECT COUNT(*) FROM mcp_skills WHERE deleted_at IS NULL` |
| 2 | Versions registradas em `mcp_skill_versions` | ≥ 16 (1 por skill na sync inicial) | `SELECT COUNT(*) FROM mcp_skill_versions` |
| 3 | UI `/ads/admin/skills` (lista + detalhe + editor) em prod | merged + smoke OK | PR fechado + `curl -I https://oimpresso.com/ads/admin/skills` 200 |
| 4 | Felipe/Maíra usaram `skills-search` tool MCP em 7 dias | ≥ 1 chamada cada | `mcp_audit_log` filter por user + tool |
| 5 | Wagner editou ≥ 1 skill via UI durante o cycle | ≥ 1 entrada em `mcp_skill_versions` com `created_at > cycle.start` | query simples |

**Removidos:** goal de policies (saiu de escopo P0 após ADR 0075 supersede 0073) + goal de bi-temporal (escorrega pra CYCLE-04). Goals 1-5 redefinidos pra UI Skills V0.5.

## Sprint A — Backend Skills (ADR 0075 — 5 dias úteis)

| Dia | Task ID sugerida | Entrega | Files tocados |
|---|---|---|---|
| **A1** seg 13/05 | `COPI-A1` SKILLS-DB-1 | Migrations 5 tabelas (`mcp_skills`, `mcp_skill_versions`, `mcp_skill_labels`, `mcp_skill_approvals`, `mcp_skill_test_runs`) + FKs + indexes | 5 migrations em `Modules/Copiloto/Database/Migrations/` |
| **A2** ter 14/05 | `COPI-A2` SKILLS-DB-2 | Entities Eloquent (5) + relationships + ScopeByBusiness onde aplicável | 5 entities em `Modules/Copiloto/Entities/Mcp/` |
| **A3** qua 15/05 | `COPI-A3` SKILLS-SYNC | `IndexarSkillsParaDb` (lê `.claude/skills/*/SKILL.md`, parse YAML, PII redactor, upsert idempotente) + `mcp:sync-skills` command + integrar no webhook GitHub handler | 1 service + 1 command + 1 controller alterado |
| **A4** qui 16/05 | `COPI-A4` SKILLS-MCP | 4 Tools MCP (`skills-search`, `skills-fetch`, `skills-history`, `skills-test-runs`) + registro em `OimpressoMcpServer` | 5 arquivos em `Modules/Copiloto/Mcp/Tools/` |
| **A5** sex 17/05 | `COPI-A5` SKILLS-PEST | Testes Pest: SkillsSyncTest + SkillsLabelMoveTest + SkillsRationaleRequiredTest + SkillsPiiRedactorTest + RBAC seeder | 5 tests + 1 seeder |

**Critério de aceite Sprint A:**
- Goals 1, 2, 4 batidos.
- 4 tools MCP retornando dados reais.
- Suite Pest verde.

## Sprint B — UI Skills V0.5 (lista + detalhe + editor — 5 dias úteis)

UI segue padrão de [`/ads/admin/decisoes`](../../Modules/ADS/Routes/web.php) e [`/ads/admin/meta-skills`](../../Modules/ADS/Resources/menus/topnav.php) já em prod.

| Dia | Task ID sugerida | Entrega | Files tocados |
|---|---|---|---|
| **B1** seg 20/05 | `COPI-B1` SKILLS-UI-1 | `SkillsController` + 4 rotas (`GET /skills`, `GET /skills/{slug}`, `GET /skills/{slug}/edit`, `POST /skills/{slug}/submit-review`) + Spatie permissions seeder | 1 controller em `Modules/ADS/Http/Controllers/Admin/` + `Routes/web.php` + 1 seeder |
| **B2** ter 21/05 | `COPI-B2` SKILLS-UI-2 | Page React lista `Skills/Index.tsx` (table + filtros + KPIs) + Page detalhe `Skills/Show.tsx` (two-pane LangSmith-style: versions+labels esquerda, markdown render direita, toggle diff) | 2 Pages em `resources/js/Pages/ads/Admin/` |
| **B3** qua 22/05 | `COPI-B3` SKILLS-UI-3 | Page editor `Skills/Edit.tsx` (Monaco markdown editor + form frontmatter + 4 textareas rationale obrigatórios) + integração GitHub API pra abrir PR ao "Submit for review" | 1 Page + 1 service `GitHubPrService` |
| **B4** qui 23/05 | `COPI-B4` SKILLS-UI-4 | Diff component semantic (frontmatter highlight + body unified diff) + sidebar item AppShellV2 grupo CONHECIMENTO | 1 component + DataController ADS + SIDEBAR_GROUPS |
| **B5** sex 24/05 | `COPI-B5` SKILLS-PEST | Testes Pest UI: SkillsControllerTest (5 rotas × permission) + smoke E2E manual | 1 test + manual checklist |

**Critério de aceite Sprint B:**
- Goals 3 e 5 batidos.
- Wagner navega `/ads/admin/skills`, vê 16 skills listadas, clica em uma, vê markdown render + history + edita uma com Monaco, preenche 4 rationales, clica "Submit for review" → PR aberto no GitHub.
- Permission `ads.admin.skills.read` libera lista+detalhe pra time inteiro; `edit` só Wagner+Felipe.

## Buffer (1 dia útil — seg 26/05)

Smoke integrado + retro CYCLE-02 + criar CYCLE-03 com Sprint C.

## CYCLE-03 (planejado — não inicia agora)

**Janela:** 27/05 → 09/06 (10 dias úteis).
**Goal:** UI Skills V1 completo — test runner contra inputs reais multi-tenant + approval queue obrigatório.

| Sprint | Dias | Entrega |
|---|---|---|
| **C1-C2** Test Runner | 4-5d | Page `Skills/Test.tsx` + `TestRunnerService` (chama `laravel/ai` + PII redactor) + integração com últimas N conversas reais por `business_id` + grava em `mcp_skill_test_runs` |
| **C3-C4** Approval Queue | 4-5d | Page `Skills/Review.tsx` (fila de versions em status `review`) + Approve/Reject actions + auto-merge PR via GitHub API + label `staging` aponta auto |
| **C5** Pest + smoke | 1d | SkillsApprovalTest + SkillsTestRunnerTest + smoke prod |

## CYCLE-04 (planejado — depois CYCLE-03)

**Goal:** P1 bi-temporal ([ADR 0074](../decisions/0074-temporal-validity-bi-temporal-time-travel.md)).
**Esforço estimado:** 2 dias úteis (skill mais simples — só backend).

Sobra capacidade no CYCLE-04 pra começar P2 (score por-memória) se baseline LongMemEval-PT estabilizar até lá.

## Buffer / final (resto da semana 26)

Dia útil 25/05 (segunda) — buffer + smoke integrado + retro CYCLE-02.

## Definition of Done (cycle inteiro)

- [ ] 5 goals trackados batidos
- [ ] Suite Pest verde (`vendor/bin/pest Modules/Copiloto/Tests/ Modules/ADS/Tests/`)
- [ ] Smoke skills (`tests/Feature/Skills/smoke-skill-references.php`) passa
- [ ] Smoke ADRs (`tests/Feature/Skills/smoke-adr-frontmatter.php`) passa
- [ ] Sem regressão em CYCLE-01 (suite Copiloto continua 81 passed)
- [ ] Wagner valida UI manualmente em prod
- [ ] Felipe/Maíra confirmam que `skills-search` retorna resultado em 1 query

## Não-decisões deliberadas (fora deste cycle)

- **Edição inline de skills via UI** — fonte continua git/PR. Vira cycle só se demanda real.
- **P2 (score por-memória + pruning)** — depende de baseline estável + golden set. Cycle separado.
- **P3 (action-aware retrieval)** — gate Recall@5 ≥ 0.85. Hoje baseline 0.125. Cycles depois.
- **Detecção automática supersedence** — adiada (precisa golden set estável).
- **Migrar `PolicyEngine.php` runtime pra DB** — princípio ARQ-0006 preservado. Vira ADR superseder se aparecer demanda forte.

## Riscos identificados

| Risco | Probabilidade | Mitigação |
|---|---|---|
| Bug em uma das 18 tools MCP existentes (ADR 0071) afeta wiring das 4 novas | média | rodar smoke `mcp_audit_log` no dia A4 antes de adicionar novas; se houver bug, fix em cycle paralelo |
| `IndexarPoliciesParaDb` reflection sobre `PolicyEngine` quebra se ARQ-0006 mudar nome de constante | baixa | teste anti-regressão `PoliciesSyncTest` no dia A5 detecta no PR antes de merge |
| UI ADS sem permission padrão deixa skill exposta a outros tenants | média (se mal configurado) | seeder cria permission `ads.admin.skills.read` antes de DataController publicar item no sidebar |
| Sprint B (UI) estoura 3 dias se design exigir polimento | média | escopo: lista + show simples (markdown render). Sem filtros avançados nem edição. Polimento vai pra cycle seguinte |
| Sprint C (bi-temporal) some entre Sprint A e B | baixa | colocada DEPOIS da UI pra não pressionar deadline; se Sprint A/B atrasarem, C escorrega 1-2 dias sem prejudicar goals 1-3 |

## Como criar este ciclo no MCP

A tool `cycles-create` não está exposta como deferred tool. Caminhos:

**Opção 1 — UI Wagner** (se existir tela de gestão de cycles): criar CYCLE-02 com goal acima + adicionar 5 goals trackados + adicionar 13 tasks (Sprint A: 5, B: 3, C: 2, buffer: 3).

**Opção 2 — SQL direto** no banco MCP server (`mcp_cycles`, `mcp_cycle_goals`, `mcp_tasks`):

```sql
-- Cycle
INSERT INTO mcp_cycles (project_key, key, title, goal, starts_at, ends_at, status)
VALUES ('COPI', 'CYCLE-02', 'Cycle 02 — Team MCP UI + bi-temporal',
        '<goal acima>', '2026-05-13', '2026-05-26', 'planned');

-- Goals (5 linhas em mcp_cycle_goals)
-- Tasks (13 linhas em mcp_tasks com cycle_id apontando pro novo cycle)
```

**Opção 3 — comando MCP direto** se Wagner expor tool `cycles-create` no servidor MCP via PR rápida no `Modules/Copiloto/Mcp/Tools/CyclesCreateTool.php` (5 linhas + 1 registro).

Recomendado: **Opção 3** — vira tool reutilizável. Pode ser parte do Sprint A1 deste próprio cycle, ironicamente.

## Quando rodar `cycles-close CYCLE-01`

Após dia 12/05 com retro:

```
mcp__Oimpresso_MCP___Wagner__cycles-close
  cycle: CYCLE-01
  rollover_to: CYCLE-02
  retro_sucessos: ["MEM-FAT-1 ROTA LIVRE prod", "ADR 0072-0074 + erratum", "/sync-skills + hook"]
  retro_falhas: ["dashboard /copiloto/admin/custos pendente (US-COPI-070)"]
  retro_licao: "Levantar estado real ANTES de propor mudança grande — descoberta tardia (50% bi-temporal já em prod) custou 1 turno de erratum"
```

Tasks blocked (COPI-23, COPI-24) rolam pra CYCLE-02 automaticamente.

## Referências

- [ADR 0070 — Jira-style task management (CURRENT.md/TASKS.md removidos)](../decisions/0070-jira-style-task-management-current-md-removed.md)
- [ADR 0072](../decisions/0072-maturacao-memoria-team-mcp-openclaw-soa-2026.md), [ADR 0073](../decisions/0073-team-mcp-skills-policies-entidades-governadas.md), [ADR 0074](../decisions/0074-temporal-validity-bi-temporal-time-travel.md)
- [Padrão UI ADS `/ads/admin/decisoes`](../../Modules/ADS/Http/Controllers/Admin/) (ler antes de Sprint B)
- [Padrão tools MCP](../../Modules/Copiloto/Mcp/Tools/) (ler antes de Sprint A4)
- [Cycle ativo CYCLE-01](../decisions/0070-jira-style-task-management-current-md-removed.md) — fecha 2026-05-12
