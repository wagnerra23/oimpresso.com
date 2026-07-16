---
page: /kb/decision-trees  # dialog concept — governa TroubleshooterDialog, sem .tsx de página dedicada
component: resources/js/Pages/kb/_components/TroubleshooterDialog.tsx
controller: 'Modules\KB\Http\Controllers\KbDecisionTreeController'
route: kb.decision-trees.{index,show,store,update}
status: draft
owner: wagner
persona_principal: Larissa / operacional gráfica (1280px balcão)
persona_secundaria: Wagner / governança (1440px desktop)
charter_version: 1
charter_at: 2026-05-16
related_us: [US-KB-005]
related_adrs:
  - 0150-kb-unificado-grafo-conhecimento-modulo-ia-central
  - 0093-multi-tenant-isolation-tier-0
  - 0104-processo-mwart-canonico-unico-caminho
related_briefing: ../../../../../memory/requisitos/KB/BRIEFING.md
related_schema: ../../../../../memory/requisitos/KB/SCHEMA-DB-V1.md
governs_components:
  - resources/js/Pages/kb/_components/TroubleshooterDialog.tsx
governs_controller: Modules/KB/Http/Controllers/KbDecisionTreeController.php
---

# Charter — KB Troubleshooter (Árvore de Decisão Q→Y/N)

> Troubleshooters guiados Larissa — árvore Q→Sim/Não→Q'/Fix pra diagnosticar problema operacional (ex: "Cliente reclamou de cor errada — diagnosticar"). Concept-level charter pra `TroubleshooterDialog` + endpoints JSON.

## Mission

Árvore de decisão (`kb_decision_trees` + `kb_decision_tree_steps`) que Larissa percorre clicando "Sim"/"Não" em cada passo até chegar num **fix step** (linka pra `kb_node` com instrução resolutiva). Steps têm `yes_next_step_id` e `no_next_step_id` (FKs circulares), entry-point definido por `root_step_id`.

## Goals (faz)

1. `GET /kb/decision-trees` — lista de trees ativas filtradas por `equip`/`status`
2. `GET /kb/decision-trees/{slug}` — detalhe + todos steps + `root_step_id`
3. `POST /kb/decision-trees` — cria tree atomic (steps em ordem + segundo passe pra setar `*_next_step_id`)
4. `PUT /kb/decision-trees/{slug}` — edita atomic em transação (rebuild steps + relinks)
5. `TroubleshooterDialog` UI — modal partindo de `root_step` mostrando pergunta + 2 botões Sim/Não
6. Trail visível no header — "Q1 Sim → Q3 Não → Fix" (breadcrumb percurso)
7. Fix step abre `kb_node` linkado em sub-drawer com instrução resolutiva
8. Botão "Voltar 1 passo" pra refazer escolha
9. Botão "Reiniciar troubleshooter" volta pra `root_step`

## Non-Goals (NÃO faz)

> Wagner aprova lista. Cada item vira Pest GUARD.

- NÃO duplica `kb_paths` (Path = linear, Tree = condicional Y/N)
- NÃO suporta múltipla escolha (apenas binária Sim/Não em V1)
- NÃO suporta árvore com ciclos (validar `position` topological no store/update)
- NÃO permite step sem `yes_next` E sem `no_next` E sem `fix_node_id` (orphan terminal proibido)
- NÃO faz hard-delete (apenas `status=archived`)
- NÃO armazena percurso server-side em V1 (estado local React suficiente)
- NÃO permite ramificação >2 (pra fluxo complexo, criar path encadeando trees)

## UX Targets

- Dialog abre < 200ms (tree cached em prop deferida)
- Click Sim/Não → próximo step < 50ms (estado local React, sem fetch)
- Fix step com markdown render < 200ms
- Mobile 1280px sem scroll horizontal
- Breadcrumb percurso visível no header sempre

## UX Anti-patterns

- Botões Sim/Não sem indicar pergunta original
- Esconder breadcrumb percurso (Larissa precisa saber onde está)
- Fix step sem link pra `kb_node` resolutivo
- Mostrar todos steps de uma vez (canon = 1 step visível por vez)
- Persistir percurso em sessionStorage entre sessões

## Automation Hooks

- Middleware `auth` + `can:copiloto.mcp.memory.manage` (TODO rename `kb.view.tree` / `kb.publish.tree`)
- `DB::transaction` envolvendo create/update (atomic — FKs circulares dependem de 2 passes)
- Validação `yes_next_step_id`/`no_next_step_id` `exists:kb_decision_tree_steps,id` OR null
- Validação `fix_node_id` `exists:kb_nodes,id` quando step é terminal
- `BelongsToBusinessTrait` no model → `business_id` global scope (ADR 0093)

## Automation Anti-hooks

> Wagner aprova lista. Vira Pest GUARD.

- NÃO envia notificação ao terminar troubleshooter
- NÃO chama Brain B em navegação Y/N (IA fica em ação explícita "Perguntar ao KB" fora do dialog)
- NÃO acessa tree de outro `business_id` (Tier 0 — 404 cross-tenant)
- NÃO dispara Jobs ao abrir dialog
- NÃO escreve no DB ao navegar (read-only — métricas de uso ficam em endpoint separado opcional)
- NÃO loga PII em audit (sanitizer obrigatório)

## Métricas vivas (Pest GUARD pendente)

```php
it('lists trees filtered by status != archived')
it('returns root_step_id in show response')
it('rejects step without yes_next, no_next AND fix_node_id (orphan terminal)')
it('detects and rejects cyclic tree on store/update')
it('returns 404 for cross-tenant slug (biz=1 vs biz=99)')
it('updates tree atomically in transaction (steps rebuild)')
it('archives tree on destroy (no hard delete)')
it('TroubleshooterDialog renders breadcrumb on each click Sim/Não')
it('rejects fix_node_id pointing to soft-deleted kb_node')
```

## Comparáveis canônicos (`mwart-comparative` V4)

- **Intercom Resolution Bot** (Q→Y/N decision tree) — referência principal
- **Zendesk Guide Answer Bot** (árvore + fix article) — referência mental model
- **Akinator** (Q→Y/N/talvez) — referência UX puramente binária
- **Excluir:** Decision Engine enterprise (Drools), motores de regra (overhead), chatbots LLM puros (sem árvore explícita)

## Refs

- Backend: [`Modules/KB/Http/Controllers/KbDecisionTreeController.php`](../../../../../Modules/KB/Http/Controllers/KbDecisionTreeController.php)
- Component: [`TroubleshooterDialog.tsx`](TroubleshooterDialog.tsx)
- [SCHEMA-DB-V1 §11](../../../../../memory/requisitos/KB/SCHEMA-DB-V1.md)
- [ADR 0093 — Multi-tenant Tier 0](../../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md)

## Histórico

| Data | Autor | Mudança |
|---|---|---|
| 2026-05-16 | Wave J | Charter draft criado pra conceito Troubleshooter (Decision Tree). Governa TroubleshooterDialog + KbDecisionTreeController. Pendente Wagner em Non-Goals + Anti-hooks. |
| 2026-07-09 | [CC] | Movido de `kb/Troubleshooter.charter.md` → `kb/_components/TroubleshooterDialog.charter.md` (lado do `.tsx` real que governa). Charter-conceito nunca teve página dedicada — a promoção do IT2 (integrity-check §15) a duro exige charter ao lado de tela viva. Conteúdo intacto; só links relativos reprofundados (+1 nível). Trilha L-22. |
