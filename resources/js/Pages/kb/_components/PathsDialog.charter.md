---
page: /kb/paths  # dialog concept — governa PathsDialog, sem .tsx de página dedicada
component: resources/js/Pages/kb/_components/PathsDialog.tsx
controller: 'Modules\KB\Http\Controllers\KbPathController'
route: kb.paths.{index,show,store,update}
status: draft
owner: wagner
persona_principal: Larissa / operacional gráfica (1280px balcão)
persona_secundaria: Wagner / governança (1440px desktop)
charter_version: 1
charter_at: 2026-05-16
related_us: [US-KB-004]
related_adrs:
  - 0150-kb-unificado-grafo-conhecimento-modulo-ia-central
  - 0093-multi-tenant-isolation-tier-0
  - 0104-processo-mwart-canonico-unico-caminho
related_briefing: ../../../../../memory/requisitos/KB/BRIEFING.md
related_schema: ../../../../../memory/requisitos/KB/SCHEMA-DB-V1.md
governs_components:
  - resources/js/Pages/kb/_components/PathsDialog.tsx
governs_controller: Modules/KB/Http/Controllers/KbPathController.php
---

# Charter — KB Paths (Trilhas de Aprendizado)

> Trilhas guiadas Larissa — sequência ordenada de nós `kb_nodes` pra onboarding/treinamento operacional. Concept-level charter pra `PathsDialog` + endpoints JSON.

## Mission

Trilhas ordenadas de nós KB (`kb_paths` + `kb_path_steps`) que guiam Larissa em fluxos operacionais (ex: "Como atender pedido entrante", "Como emitir NFC-e", "Como tratar inadimplência"). Cada step linka pra 1 `kb_node` e calcula progresso por usuário (`kb_path_progress` futuro).

## Goals (faz)

1. `GET /kb/paths` — lista de trilhas ativas (`status != archived`) filtradas por `equip`/`nivel` opcional
2. `GET /kb/paths/{slug}` — detalhe + steps ordenados por `position` ascendente, JOIN `kb_nodes` pra mostrar título de cada step
3. `POST /kb/paths` — cria trilha (permission `kb.publish.path`) com `slug`, `title`, `description`, `equip`, `nivel`, lista de `node_slugs`
4. `PUT /kb/paths/{slug}` — edita atomic em transação (substitui steps por delete+insert ordenado)
5. `PathsDialog` UI — modal/sheet listando trilhas + click abre step-by-step navigator
6. Step-by-step navigator — botões "Próximo" / "Anterior" / "Marcar concluído"
7. Persistência progresso localStorage prefix `oimpresso.kb.path.{slug}.step`

## Non-Goals (NÃO faz)

> Wagner aprova lista. Cada item vira Pest GUARD.

- NÃO duplica `kb_decision_trees` (Troubleshooter = Q→Sim/Não, Path = sequência linear)
- NÃO emite certificado de conclusão (ONDA 7 — feature futura)
- NÃO permite ramificação condicional em steps (linear apenas — pra Q→Y/N usar DecisionTree)
- NÃO faz hard-delete de path (apenas `status=archived`)
- NÃO duplica conteúdo de nós — step só linka via `node_id` FK
- NÃO permite step sem `node_id` (orphan step proibido)
- NÃO armazena progresso server-side em V1 (localStorage suficiente até ONDA 7)

## UX Targets

- Dialog abre < 200ms (lista cached em prop deferida)
- Navegação Próximo/Anterior < 100ms (estado local React)
- Render markdown do step < 200ms p95
- Mobile 1280px sem scroll horizontal
- Pílulas de progresso "3 de 7" visíveis no header

## UX Anti-patterns

- Múltiplas modais empilhadas (canon = Dialog único + navegação inline)
- Botão "Próximo" sem indicar conclusão de step atual
- Persistir progresso em sessionStorage (canon = localStorage prefixed)
- Mostrar steps fora de ordem
- Esconder botão "Pular trilha" / "Fechar"

## Automation Hooks

- Middleware `auth` + `can:copiloto.mcp.memory.manage` (TODO rename `kb.view.path` / `kb.publish.path`)
- `DB::transaction` envolvendo update de steps (atomic)
- Validação `node_slug exists:kb_nodes,slug` em cada step
- `$businessId` injetado via `BelongsToBusinessTrait` no model

## Automation Anti-hooks

> Wagner aprova lista. Vira Pest GUARD.

- NÃO envia notificação ao concluir step (sem email/WhatsApp)
- NÃO chama Brain B em navegação (IA fica em ação explícita "Perguntar ao KB")
- NÃO acessa path de outro `business_id` (Tier 0 — 404 cross-tenant)
- NÃO dispara Jobs ao abrir dialog
- NÃO escreve no DB ao abrir/navegar (read-only puro até step `Marcar concluído` futuro server-side)
- NÃO loga PII em audit (sanitizer obrigatório)

## Métricas vivas (Pest GUARD pendente)

```php
it('lists paths filtered by status != archived')
it('shows steps in ascending position order')
it('returns 404 for cross-tenant slug (biz=1 vs biz=99)')
it('rejects step with non-existent node_slug')
it('updates path atomically in transaction (steps replaced)')
it('archives path on destroy (no hard delete)')
it('requires kb.publish.path permission on store/update')
it('PathsDialog opens in <200ms with deferred prop')
```

## Comparáveis canônicos (`mwart-comparative` V4)

- **Notion Onboarding Templates** (sequência linear de pages) — referência principal
- **Linear Onboarding** (step navigator densidade) — referência UX
- **Coursera/Udemy** (path = curso, step = aula) — referência mental model
- **Excluir:** SCORM/LMS (overhead enterprise), Articulate (ferramenta autoria pesada)

## Refs

- Backend: [`Modules/KB/Http/Controllers/KbPathController.php`](../../../../../Modules/KB/Http/Controllers/KbPathController.php)
- Component: [`PathsDialog.tsx`](PathsDialog.tsx)
- [SCHEMA-DB-V1 §11](../../../../../memory/requisitos/KB/SCHEMA-DB-V1.md)
- [ADR 0093 — Multi-tenant Tier 0](../../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md)

## Histórico

| Data | Autor | Mudança |
|---|---|---|
| 2026-05-16 | Wave J | Charter draft criado pra conceito Paths (Trilhas). Governa PathsDialog + KbPathController. Pendente Wagner em Non-Goals + Anti-hooks. |
| 2026-07-09 | [CC] | Movido de `kb/Paths.charter.md` → `kb/_components/PathsDialog.charter.md` (lado do `.tsx` real que governa). Charter-conceito nunca teve página dedicada — a promoção do IT2 (integrity-check §15) a duro exige charter ao lado de tela viva. Conteúdo intacto; só links relativos reprofundados (+1 nível). Trilha L-22. |
