---
page: kb/Node (drawer concept — não tem .tsx dedicado)
controller: Modules\KB\Http\Controllers\KbNodeController
route: kb.nodes.{index,show,store,update,destroy,restore,reverify}
status: draft
owner: [W] Wagner
persona_principal: Wagner / governança (1440px desktop)
persona_secundaria: Larissa / operacional gráfica (1280px balcão, ONDA 6+)
charter_version: 1.0
charter_at: 2026-05-16
related_adrs:
  - 0150-kb-unificado-grafo-conhecimento-modulo-ia-central (proposta)
  - 0093-multi-tenant-isolation-tier-0
  - 0061-conhecimento-canonico-git-mcp-zero-automem
  - 0104-processo-mwart-canonico-unico-caminho
related_briefing: ../../../memory/requisitos/KB/BRIEFING.md
related_schema: ../../../memory/requisitos/KB/SCHEMA-DB-V1.md
governs_components:
  - resources/js/Pages/kb/_components/NodeReader.tsx
  - resources/js/Pages/kb/_components/NodeList.tsx
  - resources/js/Pages/kb/_components/BlockRenderer.tsx
governs_controller: Modules/KB/Http/Controllers/KbNodeController.php
governs_service: Modules/KB/Services/KbArticleService.php
---

# Charter — KB Node CRUD (drawer + endpoints JSON)

> KB não tem `Create.tsx` / `Edit.tsx` / `Show.tsx` separadas — CRUD acontece via drawer dentro de `Index.tsx`/`Index.v2.tsx` chamando endpoints JSON do `KbNodeController`. Este charter governa o **conceito CRUD** (componentes + controller + service).

## Mission

CRUD de `kb_nodes` (artigos editáveis Larissa Cowork + leitura de bridges canônicos ADR/session/charter). Garantir que **bridges nunca são editáveis via UI** (invariante Tier 0: `is_editable=false ⇒ body_blocks IS NULL`) e que artigos têm autosnap em `kb_node_versions` via Observer.

## Goals (faz)

1. `GET /kb/nodes` — paginação cursor com filtros `type`, `category`, `subcategory`, `q`, `pinned`, `editable_only`, `bridge_only`
2. `GET /kb/nodes/{slug}` — detalhe + body com JOIN `mcp_memory_documents` se bridge; incrementa `reads_count` atomic (`DB::increment`, sem race)
3. `POST /kb/nodes` — cria artigo (`type=article|external_file`, `is_editable=true`, `author_user_id=Auth::id()`)
4. `PUT /kb/nodes/{slug}` — edita artigo; rejeita 422 com `NODE_NOT_EDITABLE` se `is_editable=false`
5. `DELETE /kb/nodes/{slug}` — soft-delete LGPD (restaurável 30 dias) com `confirm=CONFIRMO` obrigatório
6. `POST /kb/nodes/{slug}/restore` — restore via `onlyTrashed()`
7. `POST /kb/nodes/{slug}/reverify` — botão "Re-verificar" (marca `last_verified_at=now()` sem mudar body)
8. UI drawer (Sheet 480px) — NodeReader render markdown com BlockRenderer + edit-in-place

## Non-Goals (NÃO faz)

> Wagner aprova lista. Cada item vira Pest GUARD.

- NÃO permite editar bridge canônico (ADR 0061 — bridges editam-se via git em `memory/*`)
- NÃO faz hard-delete (apenas soft via `delete()`)
- NÃO duplica `mcp_memory_documents` — bridge tem `source_doc_id` FK pra reuso
- NÃO expõe `body_blocks` raw quando `is_bridge=true` (fallback pra `sourceDoc.content_md`)
- NÃO altera `reads_count` em `index()` (só em `show()`)
- NÃO cria página dedicada `Create.tsx`/`Edit.tsx` — CRUD via drawer
- NÃO permite mudança de `slug` em update (slug é estável; criar novo + soft-delete antigo)

## UX Targets

- Drawer abre < 300ms (1 fetch `GET /kb/nodes/{slug}` retornando body completo)
- Markdown render < 200ms p95 por nó
- Edit autosave (1500ms debounce) com toast "Salvando…" → "Salvo"
- BlockRenderer suporta callouts coloridos + bloco IMAGEM + code-fence syntax highlight
- Botão "Re-verificar" visível no header do drawer (só pra `is_editable=true`)

## UX Anti-patterns

- Modal pra detalhe (canon = Sheet lateral 480px)
- Edit form com botão "Salvar" obrigatório (canon = autosave debounced)
- Mostrar `body_blocks` JSON cru quando bridge (canon = render markdown do source)
- Permitir Larissa apagar artigo de outra pessoa sem `kb.softdelete` permission
- Skin loading sem skeleton (canon = skeleton no Sheet body)

## Automation Hooks

- Middleware `auth` + `can:copiloto.mcp.memory.manage` (dívida técnica — rename pra `kb.write`/`kb.softdelete` em PR Spatie separado)
- KbNodeObserver::updating() cria snapshot em `kb_node_versions` ANTES de aplicar `save()`
- `KbArticleService::applyFilters(Builder, Request)` centraliza filter logic (thin extraction)
- `BelongsToBusinessTrait` no model KbNode → `business_id` global scope (ADR 0093)
- `Auth::id()` injeta `author_user_id` no `store()`

## Automation Anti-hooks

> Wagner aprova lista. Vira Pest GUARD.

- NÃO envia notificação ao salvar (sem email/SMS/WhatsApp em CRUD)
- NÃO chama Brain B no save (IA fica em ação explícita)
- NÃO acessa node de outro `business_id` (Tier 0 — `firstOrFail()` retorna 404 cross-tenant)
- NÃO loga PII em audit (sanitizer obrigatório no Observer)
- NÃO indexa em Meilisearch síncrono no save (Job assíncrono via Observer::saved)
- NÃO permite Bypass de `is_editable` via mass-assignment (`$fillable` whitelist explícito)

## Métricas vivas (Pest GUARD pendente)

```php
it('lists nodes paginated with filters type/category/q/pinned')
it('returns 422 NODE_NOT_EDITABLE when editing a bridge')
it('snapshots in kb_node_versions before update')
it('increments reads_count atomically on show')
it('returns 404 for cross-tenant slug access (biz=1 vs biz=99)')
it('requires confirm=CONFIRMO on destroy')
it('restores from onlyTrashed within 30 days')
it('reverify only updates last_verified_at, not body')
it('store injects Auth::id() as author_user_id')
it('rejects body_blocks payload when is_editable=false on store')
```

## Comparáveis canônicos (`mwart-comparative` V4)

- **Notion** (block-based editor + autosave) — referência principal
- **Obsidian** (markdown + cross-link) — referência render
- **Excluir:** Confluence (XHTML legacy), Wiki.js (sem block editor), Google Docs (sem markdown nativo)

## Refs

- Backend: [`Modules/KB/Http/Controllers/KbNodeController.php`](../../../Modules/KB/Http/Controllers/KbNodeController.php)
- Service: [`Modules/KB/Services/KbArticleService.php`](../../../Modules/KB/Services/KbArticleService.php) (criado Wave J 2026-05-16)
- Entity: [`Modules/KB/Entities/KbNode.php`](../../../Modules/KB/Entities/KbNode.php)
- [SCHEMA-DB-V1 §3 + §11](../../../memory/requisitos/KB/SCHEMA-DB-V1.md)
- [ADR 0093 — Multi-tenant Tier 0](../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md)
- [ADR 0061 — Zero auto-mem privada](../../../memory/decisions/0061-conhecimento-canonico-git-mcp-zero-automem.md)

## Histórico

| Data | Autor | Mudança |
|---|---|---|
| 2026-05-16 | Wave J | Charter draft criado pra conceito CRUD Node (governa controller + service + componentes). Pendente Wagner em Non-Goals + Anti-hooks pra `status: live`. |
