# CYCLE-02 — Proposta de ciclo de desenvolvimento

> **Status:** proposto · pendente de aceite Wagner (criar via UI/SQL — `cycles-create` tool não exposta).
> **Janela:** 2026-05-13 → 2026-05-26 (10 dias úteis após CYCLE-01 fechar em 2026-05-12).
> **Project:** COPI

## Contexto

CYCLE-01 fecha em 2026-05-12 com 2/3 goals batidos (Larissa OK, recall_chars OK, dashboard custos pendente). Sessão 2026-05-05 produziu 4 ADRs novas mergeadas em main (commit `70ee8dde`):

- [ADR 0072](../decisions/0072-maturacao-memoria-team-mcp-openclaw-soa-2026.md) — Roadmap memória + Team MCP (P0–P3)
- [ADR 0073](../decisions/0073-team-mcp-skills-policies-entidades-governadas.md) — P0 detalhado (mcp_skills + mcp_policies + 4 tools MCP)
- [ADR 0074](../decisions/0074-temporal-validity-bi-temporal-time-travel.md) — P1 detalhado (bi-temporal)
- Erratum nas 3 acima após levantamento do estado real

**Pivô descoberto:** Wagner pediu UI de gerenciamento via `https://oimpresso.com/ads/admin/skills`. Originalmente fora de escopo P0, agora dentro.

## Goal (outcome-oriented)

> Time MCP completo: Wagner gerencia skills/policies via UI `/ads/admin/skills` e `/ads/admin/policies`; Felipe/Maíra/Luiz/Eliana descobrem via tools MCP `skills-search` sem precisar `git pull`. Memória bi-temporal permite responder "qual era a verdade em 30/04 (revisada)" vs "qual era o que SABÍAMOS em 30/04".

## Goals trackados (5)

| # | Métrica | Alvo | Como medir |
|---|---|---|---|
| 1 | Skills indexadas em `mcp_skills` | ≥ 16 (todas as `.claude/skills/*/SKILL.md`) | `SELECT COUNT(*) FROM mcp_skills WHERE deleted_at IS NULL` |
| 2 | Policies indexadas em `mcp_policies` | ≥ 4 categorias × N regras (ARQ-0006 ENUMs) | `SELECT category, COUNT(*) FROM mcp_policies GROUP BY category` |
| 3 | UI `/ads/admin/skills` + `/policies` em prod | merged + smoke OK | PR fechado + `curl -I https://oimpresso.com/ads/admin/skills` 200 |
| 4 | Bi-temporal em `copiloto_memoria_facts` | 3 colunas novas + tool `memoria-historica` | migration aplicada + tool registrada em `OimpressoMcpServer` |
| 5 | Felipe/Maíra usaram `skills-search` em 7 dias | ≥ 1 chamada cada | `mcp_audit_log` filter por user + tool |

## Sprint A — P0 Backend (5 dias úteis)

| Dia | Task ID sugerida | Entrega | Files tocados |
|---|---|---|---|
| **A1** seg 13/05 | `COPI-A1` MEM-MCP-SKILLS-1 | Migrations `mcp_skills` + `mcp_policies` + history tables (espelho de `mcp_memory_documents_history`) | 4 migrations em `Modules/Copiloto/Database/Migrations/` |
| **A2** ter 14/05 | `COPI-A2` MEM-MCP-SKILLS-2 | Entities + `IndexarSkillsParaDb` + `IndexarPoliciesParaDb` (copiam padrão `IndexarMemoryGitParaDb`) | 4 services + 2 entities |
| **A3** qua 15/05 | `COPI-A3` MEM-MCP-SKILLS-3 | Commands `mcp:sync-skills` + `mcp:sync-policies` + integrar no webhook handler | 2 commands + 1 controller alterado |
| **A4** qui 16/05 | `COPI-A4` MEM-MCP-SKILLS-4 | 4 Tools MCP (`skills-search/fetch`, `policies-active/fetch`) + registro em `OimpressoMcpServer` | 5 arquivos em `Modules/Copiloto/Mcp/Tools/` |
| **A5** sex 17/05 | `COPI-A5` MEM-MCP-SKILLS-5 | Testes Pest + RBAC permissions seeder + smoke | 4 testes + 1 seeder |

**Critério de aceite Sprint A:**
- Goals 1, 2 e 5 batidos.
- Tools `skills-search`, `skills-fetch`, `policies-active`, `policies-fetch` chamáveis e retornando dados reais.

## Sprint B — UI ADS Admin (3 dias úteis)

UI segue padrão de [`/ads/admin/decisoes`](../../Modules/ADS/Routes/web.php) já em prod: rotas web + Controller Inertia + Pages React.

| Dia | Task ID sugerida | Entrega | Files tocados |
|---|---|---|---|
| **B1** seg 20/05 | `COPI-B1` MEM-MCP-UI-1 | `SkillsController` + `PoliciesController` + 6 rotas (`/admin/skills`, `/admin/skills/{slug}`, `/admin/policies`, `/admin/policies/{slug}` + 2 actions) | 2 controllers em `Modules/ADS/Http/Controllers/Admin/` + `Routes/web.php` |
| **B2** ter 21/05 | `COPI-B2` MEM-MCP-UI-2 | Pages React: `Skills/Index.tsx` (lista + busca) + `Skills/Show.tsx` (markdown render) + `Policies/Index.tsx` + `Policies/Show.tsx` | 4 Pages em `resources/js/Pages/Ads/Admin/` |
| **B3** qua 22/05 | `COPI-B3` MEM-MCP-UI-3 | Permission `ads.admin.skills.read/manage` + `ads.admin.policies.read/manage` + DataController hook · sidebar item AppShellV2 | 1 seeder + `DataController.php` ADS + ajustes `SIDEBAR_GROUPS` |

**Critério de aceite Sprint B:**
- Goal 3 batido.
- Wagner navega `/ads/admin/skills`, vê 16 skills listadas, clica e lê markdown; mesmo pra `/policies`.
- Permissão `ads.admin.skills.read` libera leitura; `manage` futuro libera edição (não escopo deste cycle).

**Out-of-scope deste cycle:** edição inline de skills/policies pela UI. Hoje fonte é git/PR. Edição via UI vira cycle futuro se demanda aparecer.

## Sprint C — P1 Backend Bi-temporal (2 dias úteis)

| Dia | Task ID sugerida | Entrega | Files tocados |
|---|---|---|---|
| **C1** qui 23/05 | `COPI-C1` MEM-BITEMPORAL-1 | Migration 3 colunas (`event_valid_from/until/supersedes_id`) + Entity update + 2 testes schema | 1 migration + `CopilotoMemoriaFato.php` + 2 tests |
| **C2** sex 24/05 | `COPI-C2` MEM-BITEMPORAL-2 | `MeilisearchDriver::atualizar()` preenche `supersedes_id` + `buscar()` aceita `as_of` opcional + Tool MCP `memoria-historica` registrada + 2 tests | 1 service + 1 tool + 2 tests |

**Adiado pra cycle seguinte:** detecção automática de supersedence em `ExtrairFatosAgent` (precisa baseline LongMemEval-PT estável antes de calibrar threshold).

**Critério de aceite Sprint C:**
- Goal 4 batido.
- Tool `memoria-historica` retorna estado correto pra `as_of` em ambas dimensões temporais.

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
