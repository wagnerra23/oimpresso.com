---
page: /kb
component: resources/js/Pages/kb/Index.tsx
controller: Modules\KB\Http\Controllers\KbController@index
route: kb.index
status: draft
owner: wagner
persona_principal: Wagner / governança (1440px desktop)
persona_secundaria: Larissa / operacional gráfica (1280px balcão, ONDA 6+)
charter_version: 1.0
charter_at: 2026-05-15
related_adrs:
  - 0150-kb-unificado-grafo-conhecimento-modulo-ia-central # proposta
  - 0039-ui-chat-cockpit-padrao
  - 0104-processo-mwart-canonico-unico-caminho
  - 0114-prototipo-ui-cowork-loop-formalizado
related_briefing: ../../../memory/requisitos/KB/BRIEFING.md
related_schema: ../../../memory/requisitos/KB/SCHEMA-DB-V1.md
related_capterra: ../../../memory/requisitos/KB/CAPTERRA-FICHA.md
related_prototipo: ../../../prototipo-ui/cowork/kb-page.jsx
---

# Charter — `resources/js/Pages/kb/Index.tsx`

## Mission

A tela `/kb` é o **portal de entrada do KB Unificado** — onde Wagner navega, busca, lê e cruza os 143 ADRs + sessions + charters + runbooks + briefings da governança do oimpresso. Layout **tri-pane Cockpit V2** (sidebar categorias + lista nós + leitor markdown) com **command palette ⌘K** dedicada, **keyboard nav J/K/Esc**, e **integração com IA RAG** ("Perguntar ao KB" com citações).

Larissa usa a MESMA tela a partir da ONDA 6 (mudando seed/permissions), com troubleshooters e trilhas operacionais.

## Goals (o que esta tela DEVE fazer bem)

1. **Listar e filtrar 1000+ nós sem degradar performance** — paginação cursor-based, `Inertia::defer` na prop `nodes`, virtualização opcional pra lista (>500 itens).
2. **Render markdown rápido** com cross-link `#kb-XXX` clicável + anchors h2 + callouts coloridos + bloco IMAGEM.
3. **Command palette ⌘K** com fuzzy search local (primeiro 200 nós) + fallback IA quando palette vazia.
4. **Keyboard nav** completa: ⌘K abre palette, `/` foca busca da lista, `J/K` ou setas navegam linhas, Enter abre, Esc fecha, `N` novo artigo (se `kb.write`), `A` abre IA, `B` favorita.
5. **Atalhos visíveis pra Larissa** (rodapé da sidebar) com pílulas `⌘K`, `/`, `Esc`.
6. **Mostrar status de frescor** (novo/fresco/recente/parado/expirado) calculado de `last_verified_at` + `updated_at`.
7. **Trigger trilha + decision tree + grafo** via botões na header (Trilhas, Troubleshooter, Saúde do KB, Grafo).
8. **Anexar nó a contexto** (OS atual, cliente atual) — UX "anexar a OS" com toast confirmando.

## Non-Goals (o que esta tela NÃO faz)

- ❌ Edição rica do conteúdo do nó — composer fica em `Pages/kb/Composer.tsx` (modal ou full-screen separado, ONDA 3)
- ❌ Editor visual de árvore — fica em `Pages/kb/TroubleEditor.tsx` (ONDA 3)
- ❌ Visualização-grafo — fica em `Pages/kb/Graph.tsx` (ONDA 5)
- ❌ Imprimir SOP — fica em modal `KBPrintSOP` invocado da tela leitor
- ❌ Chat IA livre — fica em `Pages/Copiloto/Chat.tsx` (Jana). Aqui só RAG focado no KB com citações.
- ❌ Admin de categorias/subcategorias — fica em `Pages/kb/Admin/Categories.tsx` (ONDA 3, baixa prioridade)
- ❌ Gerenciar permissões — fica em `Pages/admin/Permissions.tsx` (já existe)

## UX targets

- **Persona-principal Wagner 1440px:**
  - Tri-pane: sidebar 220px / lista 360px / leitor flex
  - Densidade média (não tão apertada quanto Larissa 1280px) — `data-density="comfortable"` permitido
  - Markdown renderer com `font-feature-settings: "ss01", "cv11"` (IBM Plex Sans) — legibilidade longa
- **Persona-secundária Larissa 1280px (ONDA 6+):**
  - Tri-pane comprime: sidebar 200px / lista 320px / leitor flex
  - Densidade alta `data-density="dense"` — sem `rounded-xl`, sem espaçamento desperdiçado
- **Performance:**
  - First Contentful Paint <800ms (lista + 1ª prop não-defer)
  - Tempo até interativo <1.5s (defer carrega resto)
  - Switch de nó na lista <100ms (preview cached client-side dos últimos 20 abertos)
- **Acessibilidade WCAG 2.1 AA ([ADR 0094](../../../../memory/decisions/0094-constituicao-v2-7-camadas-8-principios.md) §5):**
  - Tab navegável (cada elemento focável tem outline visível 2px accent)
  - Contrast ratio >=4.5:1 em todo texto principal
  - aria-pressed nas pílulas de filtro
  - aria-label nos botões só-ícone
  - Atalhos de teclado documentados em hint visível
- **Empty states com personalidade:**
  - Lista vazia ("Nenhum nó nesta combinação") oferece "Limpar filtros" + "Perguntar à IA"
  - Leitor sem nó selecionado mostra 3 nós pinned como sugestão
  - Palette vazia oferece "Perguntar à IA com o texto digitado"

## Anti-padrões / Proibições visuais (tokens canon)

Conforme [`prototipo-ui/CLAUDE_DESIGN_BRIEFING.md`](../../../../prototipo-ui/CLAUDE_DESIGN_BRIEFING.md) §4:

- ❌ `rounded-xl+` (Larissa balcão) — usar `rounded-md` máximo, `rounded-sm` preferido
- ❌ Cores fora dos tokens OKLCH (`--accent`, `--bg`, `--surface`, hue 240 PROJETOS pra KB)
- ❌ Emoji em UI cliente-facing — usar ícones lucide-flavored (já em `icons.jsx` do protótipo)
- ❌ Inglês em UI cliente-facing — tudo PT-BR
- ❌ Modal full-screen pra detalhe do nó — usar a 3ª coluna do tri-pane
- ❌ CTA WhatsApp na tela (nunca)
- ❌ Animações >300ms (Larissa percebe lag) — usar `transition-fast` 150ms max
- ❌ Mixar familia tipográfica — IBM Plex Sans / IBM Plex Mono são as únicas

## Automation hooks (eventos que outras telas/skills/agents podem consumir)

A tela emite eventos via tela mãe ou pode disparar:

- `kb.node.opened(node_id)` — pra contadores de leitura (já implementado no protótipo Cowork)
- `kb.node.voted_helpful(node_id)` / `kb.node.voted_outdated(node_id)`
- `kb.node.reverified(node_id)` — dono confirma frescor
- `kb.search.empty(query)` — palette vazia, oferece IA
- `kb.ai.asked(query, sources[])` — pra métrica de RAG quality
- `kb.path.step_completed(path_id, step_idx)` — pra progresso (localStorage)
- `kb.trouble.fix_reached(tree_id, path[])` — pra métrica de resolution rate

## Restrições Tier 0 IRREVOGÁVEIS

- `business_id` global scope na query do Controller (multi-tenant Tier 0 — [ADR 0093](../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md))
- `Inertia::defer()` em props com SQL pesado (`paginate()`, `count()` aggregated) — [RUNBOOK-inertia-defer-pattern](../../../../memory/requisitos/_DesignSystem/RUNBOOK-inertia-defer-pattern.md)
- F3 MWART canônico 5 fases — [ADR 0104](../../../../memory/decisions/0104-processo-mwart-canonico-unico-caminho.md)
- Gate visual screenshot Wagner antes de F4 merge — [ADR 0114](../../../../memory/decisions/0114-prototipo-ui-cowork-loop-formalizado.md)
- Pest tests biz=1 + cross-tenant biz=99 — [ADR 0101](../../../../memory/decisions/0101-tests-business-id-1-nunca-cliente.md)

## Versionamento desta charter

| Versão | Data | Mudança |
|---|---|---|
| 1.0 | 2026-05-15 | Draft inicial — ONDA 0 fundação. Aguarda port F3 da ONDA 2. |
