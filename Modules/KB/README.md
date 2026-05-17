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

- [BRIEFING](../../memory/requisitos/KB/BRIEFING.md) — estado consolidado (atualizar a cada PR de feature)
- [SPEC](../../memory/requisitos/KB/SCHEMA-DB-V1.md) — contrato técnico migrations/tabelas
- [CAPTERRA-FICHA](../../memory/requisitos/KB/CAPTERRA-FICHA.md) — benchmark de mercado
- [CHANGELOG](CHANGELOG.md) — append-only por PR mergeado
- [SCOPE](SCOPE.md) — escopo do módulo (pra agents)

## Permissions

Spatie permissions declaradas em `Resources/permissions.php`:
- `kb.view` · `kb.write` · `kb.softdelete` · `kb.restore` · `kb.history.view`

Legacy: middleware `can:copiloto.mcp.memory.manage` ainda em alguns controllers — dívida técnica pra rename em PR Spatie separado.

## Pré-flight obrigatório antes de editar

1. Ler [BRIEFING](../../memory/requisitos/KB/BRIEFING.md) atual
2. Ler [SCHEMA-DB-V1](../../memory/requisitos/KB/SCHEMA-DB-V1.md) se mexer em migration
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
