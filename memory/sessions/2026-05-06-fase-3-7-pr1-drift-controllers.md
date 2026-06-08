---
date: 2026-05-06
slot: tarde
title: "Fase 3.7 PR-1 — 9 drift controllers movidos pros donos corretos (URLs preservadas)"
participants: [W, C]
duration_min: 90
tags: [governance, refactor, drift-resolution, module-charter, pr-97]
---

# 2026-05-06 tarde — Fase 3.7 PR-1: drift controllers

## Trajetória

Wagner retomou via `/continuar`, escolheu "os nomes" (renames) do P0 do handoff. Ao ler o `MODULE-DRIFT-MIGRATION-PLAN` v1.0.0, optei por dividir em 2 PRs (drift vs renames) — Wagner aprovou ("pode fazer voce").

Durante leitura dos 9 controllers a mover, descobri **erratum no plano §1**: `MemoriaController` e `FontesController` (Jana) NÃO são "browse de mcp_memory_documents" e "knowledge sources" como descritos. São, respectivamente, tela LGPD pessoal sobre `copiloto_memoria_facts` e data source da meta (`MetaFonte`). Wagner confirmou destino KB mesmo assim ("kb é o correto") — decisão arquitetural L1, registrada como erratum no plano v1.1.0.

PR-1 entregue. PR-2 (renames Jana→Jana / PontoWr2→Ponto / MemCofre→SRS) deferred pra sessão dedicada após auditoria de tamanho (~370 arquivos PHP) + 7 decisões satélites pendentes (Pages React dir, URLs, permissions Spatie, log channel, config keys, env vars, lang dir).

## Entregas (commit `850ac349`)

**18 files changed, +82 −121.**

| Item | Status |
|---|---|
| 9 git mv preservando history (96-99% similarity) | ✅ |
| 9 namespace updates (`Modules\Jana/ADS\…` → `\KB/TeamMcp/ProjectMgmt`) | ✅ |
| Routes em Jana/Http/routes.php + ADS/Routes/web.php (use imports + ns prefix) | ✅ |
| 2 tests com use atualizado | ✅ |
| 5 SCOPE.md (`drift_alerts: []`, `contains[]` redistribuído) | ✅ |
| Plano canônico v1.0.0 → v1.1.0 com erratum §1 | ✅ |
| GUARDA `bin/check-scope.php` | ✅ 0 drift / 29 módulos |
| Pest local | ⏭️ pulado (worktree sem vendor; CI valida na main) |

## Movimentos exatos

| De → Pra | Controllers |
|---|---|
| Jana → KB | `MemoriaController`, `FontesController` |
| Jana/Mcp → TeamMcp/Mcp | `CcIngestController`, `HealthController`, `SyncMemoryWebhookController` |
| ADS/Admin → KB/Admin | `GraphController` |
| ADS/Admin → TeamMcp/Admin | `ToolsController`, `TeamScopesController` |
| ADS/Admin → ProjectMgmt/Admin | `ProjectsController` |

## Decisão técnica chave: URLs preservadas

Plano §3-4 sugere mover URLs com 301 redirect. Optei por **manter URLs inalteradas** nesta PR-1 pra zero break em Pages React, bookmarks, watchers Claude Code, webhook GitHub. Implementação:

- `/copiloto/memoria*`, `/copiloto/metas/{id}/fonte` → tuple `[\Modules\KB\Http\Controllers\…::class, 'method']` na Jana/Http/routes.php
- `/api/mcp/*`, `/api/cc/*` → trocou só o `'namespace'` prefix do route group de `Modules\Jana\Http\Controllers\Mcp` pra `Modules\TeamMcp\Http\Controllers\Mcp`
- `/ads/admin/{tools,team-scopes,graph,projects}` → swap de `use` imports no topo do ADS/Routes/web.php

Riscos alto/médio do plano §2 (webhook GitHub, watchers locais) **não materializaram** porque URLs públicas não mudaram.

## Erratum §1 do plano (registrado em v1.1.0)

Plano original confundiu nomes:
- `MemoriaController` (Jana) — descrito como "browse de mcp_memory_documents". Realidade: tela LGPD pessoal US-COPI-MEM-012 sobre `copiloto_memoria_facts`. Browse de mcp_memory_documents virou `KbController` em 2026-05-03.
- `FontesController` (Jana) — descrito como "knowledge sources". Realidade: data source da meta de faturamento (driver sql/php/http) sobre `MetaFonte`.

Wagner confirmou destino KB pra ambos como decisão arquitetural L1 — KB engloba todo "conhecimento gerenciado", incluindo memória LGPD pessoal e configuração de fontes de dados. Registrado no plano v1.1.0 como erratum, não retificação retroativa.

## PR-2 deferred (sessão dedicada)

Tamanho real medido:
- Jana: 217 PHP / 220 total
- PontoWr2: 97 PHP / 104 total
- MemCofre: 41 PHP / 45 total
- **~370 arquivos**

7 decisões satélites pendentes antes de tocar arquivo:

| Item | Renomeia? | Risco |
|---|---|---|
| Namespace PHP `\Jana` → `\Jana` | óbvio sim | composer dump-autoload em prod |
| Pages React dir `Pages/Jana/` | indeciso | ~30 `Inertia::render('Jana/…')` |
| URLs `/copiloto/*` → `/jana/*` | plano §4 sim com 301 | bookmarks, watchers, links externos |
| Permissions Spatie `copiloto.*` → `jana.*` | indeciso | usuários perdem acesso até backfill |
| Log channel `copiloto-ai` | indeciso | grep em logs históricos |
| Config keys `copiloto.*` + env `COPILOTO_*` | indeciso | .env Hostinger |
| Lang namespace `copiloto::` + dir | indeciso | view rendering |

Cada uma vira sim/não em 5min em sessão dedicada com plan claro.

## Pendências externas

- ⚠️ **Webhook GitHub** aponta pra `/api/mcp/sync-memory` — URL mantida nesta PR-1, **transparente**.
- ⚠️ **Watchers Claude Code** apontam pra `/api/cc/ingest` — URL mantida, **transparente**.
- 🟢 Risco alto/médio do plano §2 não materializou.

## Aprendizado meta

**Plano canônico não é infalível.** O erratum §1 foi pego ao LER os arquivos antes de mover (não só pelos nomes). Pattern: antes de executar um plano `authority: canonical`, validar os asserts dele com leitura do código real.

Skills `feedback_decida_nao_pergunte` e `publication-policy` foram bem aplicadas: escolhi conservador onde Wagner não detalhou (manter URLs, parar PR-2 antes de encarar 7 decisões cinza), executei direto onde regra era clara (drift mecânico).

## Próximo

1. Wagner revisa [oimpresso.com#97](https://github.com/wagnerra23/oimpresso.com/pull/97).
2. Após merge, sessão dedicada PR-2 com plan claro pras 7 decisões satélites.
3. Pendente cycle CYCLE-01 (vence 12/05): COPI-22 (driver MCP na Jana, due hoje) e COPI-21 (frontmatter YAML SPECs, due 09/05).
