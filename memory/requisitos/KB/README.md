# Modules/KB — Knowledge Base

Biblioteca compartilhada de ADRs, sessions, runbooks, comparativos. Split do Copiloto (Etapa 2 modularização — 2026-05-03).

## Em 1 linha

`/kb` mostra docs sincronizados de `memory/*` (git canônico) → `mcp_memory_documents` via webhook GitHub, com edição opcional de "artigos KB" próprios em `kb_nodes` + grafo de conhecimento (edges, paths, decision trees).

## Estado atual

- ✅ 12 entities + 12 migrations (Wave KB ONDA 1)
- ✅ Multi-tenant Tier 0 IRREVOGÁVEL — `BelongsToBusinessTrait` + global scope (Wave 11)
- ✅ Pest cross-tenant 10+ cenários (Wave 11 + Wave 18 RETRY)
- ✅ Services: `KbArticleService`, `KbRagService`, `KbCorpusBuilder`, `KbEdgeAutoDeriver`, `KbBridgeStateService` (Wave 17)
- ✅ LGPD compliance: audit trail Spatie + retention (Wave 11)
- ✅ FSM N/A declarado (Wave 18 RETRY) — KB é read-mostly, sem ciclo transacional

## Docs canônicas

- [BRIEFING](BRIEFING.md) — estado consolidado (atualizar a cada PR de feature)
- [SPEC](SCHEMA-DB-V1.md) — contrato técnico migrations/tabelas
- [CAPTERRA-FICHA](CAPTERRA-FICHA.md) — benchmark de mercado
- [CHANGELOG](CHANGELOG.md) — append-only por PR mergeado
- [SCOPE](../../../Modules/KB/SCOPE.md) — escopo do módulo (pra agents)

## Permissions

Spatie permissions declaradas em `Resources/permissions.php`:
- `kb.view` · `kb.write` · `kb.softdelete` · `kb.restore` · `kb.history.view`

Legacy: middleware `can:jana.mcp.memory.manage` (coarse) ainda em alguns controllers — os `kb.*` granulares acima seguem declarativos. O prefixo `copiloto.` foi corrigido em 2026-07-27: a migration `2026_05_09_140000_rename_copiloto_permissions_to_jana` já tinha renomeado o **banco** em maio e o **código** ficou para trás, checando um nome que não existia mais.

## Pré-flight obrigatório antes de editar

1. Ler [BRIEFING](BRIEFING.md) atual
2. Ler [SCHEMA-DB-V1](SCHEMA-DB-V1.md) se mexer em migration
3. Skill Tier A `preflight-modulo` auto-trigger via hook
4. Skill Tier A `multi-tenant-patterns` — toda Eloquent Model usa `BelongsToBusinessTrait`
5. Tests biz=1 OR biz=99 — NUNCA biz=4 (ROTA LIVRE)

## Tests local

```bash
php artisan test --filter=Modules\\\\KB
```

## Não inventar

- ⛔ Edits diretos em `mcp_memory_documents` via tinker — vem do webhook git. Edits canon só via PR no repo origem.
- ⛔ Cross-link de artigos sem `edge_type` cadastrado (referencia/contradiz/superseded/exemplo).
- ⛔ Decisão arquitetural sem ADR — propor proposal em `memory/decisions/proposals/`.
