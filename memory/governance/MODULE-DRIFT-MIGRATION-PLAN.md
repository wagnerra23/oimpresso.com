---
slug: module-drift-migration-plan
title: "Plano consolidado de migração de drift entre módulos"
type: governance-spec
authority: canonical
lifecycle: ativo
version: 1.3.0
maintained_by: wagner
last_updated: 2026-05-06
charter_adr: 0080
related:
  - 0079-constituicao-oimpresso-7-camadas-governanca
  - 0080-trust-tiers-operacional-audit-findings
pii: false
---

# Plano consolidado de migração de drift entre módulos

> **Verdade canônica única.** Este documento é a **fonte autoritária** de quais controllers migram pra onde nas Fases 3.7-3.10. Cada SCOPE.md aponta pra cá. Não há contradição: se algum módulo declarar `contains[]` algo que outro também declarar, **este plano arbitra**.

---

## §1. Tabela mestre de drift detectado (audit 2026-05-05)

| # | Controller atual (origem errada) | Pertence a (destino correto) | Motivo | Fase | Risco |
|---|---|---|---|---|---|
| 1 | `Modules/Jana/Http/Controllers/MemoriaController.php` | `Modules/KB/Http/Controllers/` | Browse/admin de `mcp_memory_documents` é função do KB, não do chat | 3.7 | baixo |
| 2 | `Modules/Jana/Http/Controllers/FontesController.php` | `Modules/KB/Http/Controllers/` | Knowledge sources são parte do KB | 3.7 | baixo |
| 3 | `Modules/Jana/Http/Controllers/Mcp/CcIngestController.php` | `Modules/TeamMcp/Http/Controllers/` | Ingest de Claude Code sessions é admin do MCP server | 3.7 | médio (webhook) |
| 4 | `Modules/Jana/Http/Controllers/Mcp/HealthController.php` | `Modules/TeamMcp/Http/Controllers/` | Health check do MCP server pertence ao admin do MCP | 3.7 | baixo |
| 5 | `Modules/Jana/Http/Controllers/Mcp/SyncMemoryWebhookController.php` | `Modules/TeamMcp/Http/Controllers/` | Webhook sync git→DB é função do MCP server admin | 3.7 | **alto** (webhook GitHub aponta pra URL atual) |
| 6 | `Modules/Jana/Http/Controllers/Admin/GovernancaController.php` | `Modules/Governance/Http/Controllers/` (NOVO Fase 5) | Governança consolidada vai pra módulo dedicado | 5 | baixo |
| 7 | `Modules/ADS/Http/Controllers/Admin/ProjectsController.php` | `Modules/ProjectMgmt/Http/Controllers/` (depois Project) | Gerencia `mcp_jira_projects` (Jira-style) | 3.7 | baixo |
| 8 | `Modules/ADS/Http/Controllers/Admin/ToolsController.php` | `Modules/TeamMcp/Http/Controllers/` | MCP tools registry pertence ao TeamMcp | 3.7 | baixo |
| 9 | `Modules/ADS/Http/Controllers/Admin/TeamScopesController.php` | `Modules/TeamMcp/Http/Controllers/` | RBAC scopes do MCP server pertence ao TeamMcp | 3.7 | baixo |
| 10 | `Modules/ADS/Http/Controllers/Admin/GraphController.php` | `Modules/KB/Http/Controllers/` | Knowledge graph pertence ao KB | 3.7 | baixo |

**Total:** 10 controllers a migrar. 9 em Fase 3.7. 1 (GovernancaController) em Fase 5 quando módulo Governance for criado.

---

## §2. Riscos e mitigação por controller

### #5 — `Mcp/SyncMemoryWebhookController.php` (RISCO ALTO)

**Por quê é alto.** Webhook GitHub aponta pra URL atual (`https://oimpresso.com/api/mcp/webhook/sync-memory`). Mover sem cuidado quebra sync git→DB de TODOS os memory docs.

**Mitigação:**

1. Migrar controller mantendo URL antiga ativa (rota redirect)
2. Atualizar settings do webhook GitHub pra nova URL `/teammcp/webhook/sync-memory`
3. Validar 2 push tests
4. Só então deletar rota antiga

**Janela:** ~30min de overlapping (rota dupla) durante validação.

### #3 — `Mcp/CcIngestController.php` (RISCO MÉDIO)

**Por quê é médio.** Watcher local dos devs aponta pra URL atual.

**Mitigação:** mesma estratégia de #5 — overlap rotas + atualizar config dos watchers + validar.

### Outros (RISCO BAIXO)

URLs internas do admin (não expostas a webhooks externos). 301 redirects simples bastam.

---

## §3. Sequência de execução Fase 3.7

```
PR único "feat(refactor): migra 9 drift controllers + renames Copiloto→Jana, PontoWr2→Ponto, MemCofre→SRS"

1. Criar diretórios destino + namespace correto
2. git mv dos 9 controllers
3. Atualizar imports nos use statements
4. Atualizar Routes/web.php de cada módulo afetado
5. Atualizar permissões em DataController hooks (modifyAdminMenu)
6. Adicionar 301 redirects nas URLs antigas (Routes/web.php do módulo origem)
7. Atualizar SCOPE.md de cada módulo (drift_alerts → resolved)
8. Atualizar SCOPE.md ADS removendo controllers migrados
9. Pest tests rodando — todos passing
10. Webhook GitHub: atualizar URL + 2 push tests
11. Watchers Claude Code: atualizar config (oimpresso-cc-watcher-setup skill)
12. Single commit, push, PR review, merge
```

**ETA Fase 3.7:** ~6h.

---

## §4. Renomeações simultâneas em Fase 3.7

Após drift resolvido, mesmo PR (ou PR seguinte) faz renames:

| De | Pra | URLs |
|---|---|---|
| `Modules/Copiloto/` | `Modules/Jana/` | `/copiloto/*` → `/jana/*` (301) |
| `Modules/PontoWr2/` | `Modules/Ponto/` | `/ponto-wr2/*` → `/ponto/*` (301) |
| `Modules/MemCofre/` | `Modules/SRS/` | `/memcofre/*` → `/srs/*` (301) |

**Erratum §4 v1.2 (2026-05-06):** Renames executados em PR-2 do PR #97 foram **PHP-only** (pasta + namespace + ServiceProvider class + module.json + composer.json). URLs, permissions, config keys, env vars, log channel, Pages React, lang namespace e route names **mantidos legacy `copiloto.*`/`pontowr2.*`/`memcofre.*`** por blast radius alto (5993 clientes ROTA LIVRE + watchers + webhook + 30 Inertia::render). Cada dimensão da fachada vira PR-3..8 isolado quando Wagner decidir mover. Ver [ADR 0088](../decisions/0088-module-rename-php-only.md).

**Erratum §4 v1.3 (2026-05-06) — PR-9 executado:** Tabelas DB Jana **renomeadas** `copiloto_*` → `jana_*` (13 tabelas) com views legacy 30d. Classe Eloquent `JanaMemoriaFato` → `MemoriaFato`. Tabelas Ponto/SRS continuam legacy (não estavam no escopo). Ver [ADR 0092](../decisions/0092-tabela-rename-copiloto-para-jana.md). Restam PR-3..8 (URLs/permissions/Pages/config/log/lang) — opt-in evolution.

---

## §5. Renomeação ProjectMgmt → Project (Fase 3.8 + 3.9)

**Fase 3.8** (DELETE Project legado UltimatePOS):

1. SQL audit: `SELECT COUNT(*) FROM projects, project_tasks, project_time_logs, project_invoices`
2. Wagner identifica dados a preservar
3. Migration: rename tabelas legacy `_archived_projects`, `_archived_project_tasks`, etc. (não DELETE — preserva)
4. `git rm -rf Modules/Project/`
5. Remover permissions órfãs (Spatie)

**Fase 3.9** (rename ProjectMgmt → Project):

1. `git mv Modules/ProjectMgmt Modules/Project`
2. Namespace + URLs + permissions
3. 301 redirects `/projectmgmt/*` → `/project/*`

---

## §6. Como o GUARDA detecta drift novo

(Ver `bin/check-scope.php` + `.githooks/pre-commit` + `.github/workflows/scope-guard.yml`)

A cada commit / PR:

1. Lista arquivos staged em `Modules/<X>/Http/Controllers/*.php` (criados ou movidos)
2. Pra cada um, lê `Modules/<X>/SCOPE.md`
3. Verifica se filename está em `contains[]` ou em `drift_alerts[]` (transitório)
4. Se NÃO está em nenhum:
   - Pre-commit: **WARN** (modo dev — permite commit)
   - GitHub Action: **BLOCK** (modo PR — bloqueia merge)
5. Mensagem orienta: "Adicione em SCOPE.md.contains[] OU mova pro módulo correto OU declare em drift_alerts[] (com ADR justificando)"

---

## §7. Validação pós-migração

Critérios de "Fase 3.7 concluída":

- [ ] 9 controllers movidos pra módulos corretos
- [ ] SCOPE.md de origem com `drift_alerts[]` removido
- [ ] SCOPE.md de destino com controller em `contains[]`
- [ ] URLs antigas → 301 redirects funcionando (curl test)
- [ ] Webhook GitHub apontando pra URL nova (2 push tests OK)
- [ ] Watchers Claude Code atualizados (config nova nos `.claude/settings.local.json` dos devs)
- [ ] Pest test suite full PASS
- [ ] Permissions Spatie migradas (no orphans)
- [ ] GUARDA `bin/check-scope.php` retorna 0 errors em main

---

## §8. Estado atual — pronto pra Fase 3.7?

| Pré-requisito | Status |
|---|---|
| 6 SCOPE.md críticos criados | ✅ ADS, Jana, KB, MemCofre, TeamMcp, ProjectMgmt |
| Drift mapeado em cada SCOPE.md | ✅ |
| Plano consolidado (este doc) | ✅ |
| GUARDA implementado | ⏸️ esta sessão |
| 24 SCOPE.md restantes (módulos não-críticos) | ⏸️ Fase 3.4 (delegável) |
| Wagner aprovou planos | ⏸️ amanhã |

**Bloqueador pra começar Fase 3.7:** Wagner aprovar este plano + 6 SCOPE.md.

---

## Histórico

- **v1.0.0** (2026-05-05) — Plano inicial. 10 drift controllers mapeados. Renomeações + depreciações sequenciadas. GUARDA implementação iniciada.
- **v1.1.0** (2026-05-06) — **Fase 3.7 PR-1 executada.** 9 dos 10 drift controllers movidos pros donos corretos (#1..#5 + #7..#10 da tabela §1). URLs **mantidas** (zero break) via `use` imports atualizadas em Routes/web.php da Jana/ADS — namespace prefix dos route groups + tuple `[Class::class, 'method']` apontam pros novos namespaces. SCOPE.md dos 5 módulos afetados (Jana/ADS/KB/TeamMcp/ProjectMgmt) com `drift_alerts[]` zerado. **Erratum §1**: o plano original confundiu MemoriaController (Jana) com browse de mcp_memory_documents — na verdade é tela LGPD pessoal US-COPI-MEM-012 sobre `copiloto_memoria_facts`. FontesController também é data source de meta de faturamento, não knowledge sources. Wagner confirmou destino KB pra ambos mesmo assim (decisão arquitetural L1, registrada nesta versão). PR-1 NÃO inclui renames Copiloto→Jana / PontoWr2→Ponto / MemCofre→SRS — esses ficam pra **PR-2** separado (URLs mudam aí). **Pendente §1 #6**: GovernancaController fica em Fase 5 (Modules/Governance já existe, mas absorção do controller é decisão separada).
- **v1.2.0** (2026-05-06) — **Fase 3.7 PR-2 executada.** 3 renames de módulo aplicados: `Modules/Copiloto`→`Modules/Jana`, `Modules/PontoWr2`→`Modules/Ponto`, `Modules/MemCofre`→`Modules/SRS`. **Erratum §4**: o plano §4 original previa URLs novas com 301 redirect (`/copiloto/*`→`/jana/*` etc), mas a execução escolheu **rename PHP-only** — apenas pasta + namespace + ServiceProvider class + module.json + composer.json mudaram. Mantidos legacy: URLs (`/copiloto/*`, `/pontowr2/*`, `/memcofre/*`), permissions Spatie (`copiloto.*` etc), config keys + env vars (`COPILOTO_*` etc), log channels (`copiloto-ai`), Pages React dirs (`Pages/Copiloto/`), lang namespaces (`copiloto::` etc), tabelas DB (`copiloto_*`, `ponto_*`, `docs_*`). **Razão**: blast radius de mudar tudo de uma vez é alto demais (5993 clientes ROTA LIVRE + watchers Claude Code + webhook GitHub + 30 `Inertia::render('Copiloto/...')`). PR posteriores podem mover URLs/permissions/Pages com 301 + backfill DB se Wagner decidir. Tamanho real do PR-2: ~370 arquivos PHP, 314 modificados pelo bulk replace (320 substituições distintas). Validação: `bin/check-scope.php` passou; Pest pulado (worktree sem vendor; CI valida).
