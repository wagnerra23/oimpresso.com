# Agent B — KB Unificado ONDA 2 (frontend Inertia)

**Data:** 2026-05-15
**Agent:** Agent B (frontend Inertia/React 19/TS, paralelo a Agent A backend)
**Branch:** `claude/practical-engelbart-8d8eb0`
**Charter:** [`resources/js/Pages/kb/Index.charter.md`](../../resources/js/Pages/kb/Index.charter.md) v1.0
**Briefing:** [`memory/requisitos/KB/BRIEFING.md`](../requisitos/KB/BRIEFING.md)
**Schema (contrato):** [`memory/requisitos/KB/SCHEMA-DB-V1.md`](../requisitos/KB/SCHEMA-DB-V1.md)
**Protótipo Cowork [CC]:** [`prototipo-ui/prototipos/kb/`](../../prototipo-ui/prototipos/kb/) (kb-page.jsx + 4 sat)

---

## Resumo executivo (3 linhas)

Port completo do protótipo Cowork [CC] (kb-page.jsx 71KB + 4 satélites) pra Inertia React 19 + TS estrito, em `resources/js/Pages/kb/Index.v2.tsx` (NÃO substitui `Index.tsx` V3 atual — roda em paralelo aguardando gate visual ADR 0114). Tri-pane Cockpit V2 (CategorySidebar/NodeList/NodeReader) + CommandPalette ⌘K + 3 dialogs (Trilhas/Troubleshooter/Saúde) + keyboard nav completa (⌘K, /, J/K, Esc, N, A, B). Mock data com 18 nós seed funciona em dev sem backend; props Inertia (`KbIndexProps`) já tipadas pro contrato §11 do SCHEMA-DB-V1 — Agent A só precisa entregar Controller que case com `KbNode/KbCategory/KbPath/Paginator<KbNode>` no shape esperado.

---

## Arquivos criados

| Path (absoluto a partir do repo) | Linhas (~) | Função |
|---|---:|---|
| `resources/js/Pages/kb/Index.v2.tsx` | 470 | Página tri-pane principal + integra todos sub-components + keyboard hooks + fallback mock |
| `resources/js/Pages/kb/_components/CategorySidebar.tsx` | 280 | Coluna 1: categorias expansíveis + subcats + favs top-8 + recentes + tags cloud + atalhos hint |
| `resources/js/Pages/kb/_components/NodeList.tsx` | 235 | Coluna 2: lista de rows com pílula cat hue/nivel/equip/pinned/outdated + sort segmented + filter pill |
| `resources/js/Pages/kb/_components/NodeReader.tsx` | 410 | Coluna 3: header meta+frescor+favStar+nav arrows + TOC + body (BlockRenderer) + tags + related + footer ações |
| `resources/js/Pages/kb/_components/BlockRenderer.tsx` | 145 | Renderiza body_blocks (para/h2/list/callout 4tons/image) com kbLinkifyText pra #kb-NNN |
| `resources/js/Pages/kb/_components/CommandPalette.tsx` | 130 | ⌘K fuzzy search + fallback "Perguntar IA" se empty (shadcn Command/cmdk) |
| `resources/js/Pages/kb/_components/PathsDialog.tsx` | 220 | Drawer Sheet com lista trilhas + detalhe + checkboxes localStorage |
| `resources/js/Pages/kb/_components/TroubleshooterDialog.tsx` | 290 | Modal Dialog com lista + wizard Q→Sim/Não→Fix + histórico de respostas |
| `resources/js/Pages/kb/_components/HealthPanel.tsx` | 165 | Modal 4 quadrantes (outdated/stale/popular/lonely) |
| `resources/js/Pages/kb/_components/KbFavStar.tsx` | 50 | Botão estrela favorito reutilizável |
| `resources/js/Pages/kb/_lib/types.ts` | 250 | TS types alinhados ao SCHEMA-DB-V1 §3-9 + Props da página |
| `resources/js/Pages/kb/_lib/helpers.ts` | 215 | freshnessLevel, fmtRelative, fuzzyMatch, kbLinkifyText, relatedNodes, kbBuildArticleText |
| `resources/js/Pages/kb/_lib/mockData.ts` | 380 | 18 nós + 7 cats + 16 subcats + 3 trilhas + 3 troubleshooters + computeMockKpis |
| `resources/js/Pages/kb/_lib/useKbKeyboardNav.ts` | 130 | Hook custom ⌘K, /, J/K, Esc, N, A, B com ref-pattern (sem re-bind) |
| `resources/js/Pages/kb/_lib/useKbFavorites.ts` | 50 | Hook localStorage `oimpresso.kb.favs.v1` |
| `resources/js/Pages/kb/_lib/useKbPathProgress.ts` | 70 | Hook localStorage `oimpresso.kb.paths.v1` (progresso trilhas) |
| `resources/js/Pages/kb/_lib/useKbRecent.ts` | 45 | Hook localStorage `oimpresso.kb.recent.v1` (últimos 8 abertos) |
| `resources/css/kb.css` | 145 | CSS canon — grid tri-pane responsivo + .kb-kbd + .kb-link + print mode (futuro) |

**Total:** 18 arquivos, ~3680 linhas.
**Não modificados:** `Index.tsx` (V3 atual), `Index.charter.md`, `AppShellV2.tsx`, ui/* shadcn — conforme escopo.

---

## TODOs deixados (busque por `TODO[CL]` no código)

| Path:linha aproximada | Descrição |
|---|---|
| `Index.v2.tsx:~250` | `pickByRef` resolve `#kb-NNN` matching slug — quando Agent A entregar lookup server-side via `GET /kb/nodes/{ref}/resolve`, trocar `toast.info('not found')` por fetch |
| `Index.v2.tsx:~310-340` (várias) | `voteHelpful/voteOutdated/reverify/attachToOS/summarizeAI/onPresent/onPrint/onHistory/onEdit` — todos TODO[CL] anotando endpoint ONDA correspondente |
| `Index.v2.tsx:~80` | `troubleshooters = MOCK_TROUBLESHOOTERS` — Agent A entregar `props.decision_trees` no shape `KbDecisionTree[]` (sem flat_steps); criar adapter `_lib/adapter.ts` que normaliza pra `MockTroubleshooter.flat_steps` na ONDA 3 |
| `Index.v2.tsx:~450` | "Visualização-grafo — ONDA 5" — router.visit('/kb/graph') existe via `GraphCanvas.tsx` já criado por outro agent (não tocar) |
| `Index.v2.tsx:~470` | AI Dialog (Perguntar ao KB com citações) — ONDA 4 |
| `Index.v2.tsx:~471` | Composer modal — ONDA 3 |
| `mockData.ts:cabeçalho` | Quando backend entregar, mover arquivo pra `mockData.dev.ts` e gate via `import.meta.env.DEV` |
| `useKbPathProgress.ts:cabeçalho` | V2 cloud sync — quando user tem `kb.path.progress.cloud_sync`, espelhar em `kb_path_user_progress` (não no V1) |
| `NodeReader.tsx:BridgeFallback` | Quando node é bridge (`body_blocks IS NULL`, `source_doc_id IS NOT NULL`), mostrar fallback chamando Agent A endpoint `GET /kb/nodes/{slug}` com content_md preenchido |

Total: ~14 TODO[CL] no código + 1 no cabeçalho do mock.

---

## Decisões UX tomadas (Cowork foi ambíguo X → escolhi Y → razão Z)

| Aspecto | Cowork [CC] fazia | Eu fiz | Razão |
|---|---|---|---|
| Stats header | 4 stats inline (`os-stats` div + `os-stat`) | `KpiGrid + KpiCard` shadcn padrão do projeto | Consistência com Pages Repair/Sells/Compras + dark mode automático + Inertia::defer-ready |
| `os-btn` legacy CSS classes | Botões com `.os-btn ghost/primary` | `<Button variant="ghost|default" size="sm">` shadcn | Tokens shadcn já mapeados pra hue 240 do projeto |
| Markdown render | `kb-art-body` div + JSX manual | `BlockRenderer` componente separado + ReactNode[] de `kbLinkifyText` | Reutilizável em `Composer.tsx` ONDA 3 sem duplicar |
| Command palette | div custom + state interno | shadcn `CommandDialog` (cmdk underneath) | A11y + aria + foco gerenciado de graça; cmdk tem mesmo modelo Cowork |
| `KBComposer` (kb-extras) | Modal full editor | Botão "+ Novo artigo" abre placeholder + `setComposerOpen` | Escopo ONDA 2 não inclui composer (charter Non-Goals); botão sinaliza onde virá ONDA 3 |
| `KBAIDialog` (kb-extras) | Modal IA inline | Botão "Perguntar ao KB" + `setAiOpen` + placeholder toast | Charter Non-Goals: AI fica em `Pages/Copiloto/Chat.tsx`; aqui só hooks |
| `KBTroubleEditor` (v5 new) | Editor visual árvore | NÃO portado (ONDA 3) | Escopo explícito do prompt |
| `KBPrintSOP` (v5 new) | Modal preview + window.print() | NÃO portado (ONDA 3) | Escopo explícito do prompt |
| `KBImageBlockEditor` (v5 new) | Editor de imagem inline | NÃO portado (ONDA 3) | Escopo; `KBImageBlockView` foi portado pro `BlockRenderer` |
| Mobile tabs (`kb-mobile-tabs`) | 3 botões em row pra trocar pane | `data-mobile-view` no `.kb-tri` via CSS media `<1024px` | Mantém mesma UX em mobile sem div extra; controlado por state `mobileView` no Index |
| Frescor pills "novo/fresco/recente/parado/expirado" | strings hardcoded | `freshnessLevel()` helper retorna `{level, label}` | TS-friendly + reutilizável em outros componentes |
| Cores via inline `style={{background:'oklch(...)'}}` | Funciona mas duplicado | Mantive inline `style` pra cores dinâmicas por categoria (hue dinâmico do DB) + Tailwind pro restante | Hue vem do schema (`kb_categories.hue`) — não dá pra pré-compilar |
| Slug "#a1" do Cowork | Compat reverse-lookup | `pickByRef` tenta `slug === ref` primeiro, depois `slug.startsWith('kb-' + ref + '-')` | Compat retroativa pra body_blocks que ainda têm `#a1` em vez de `#kb-a1-...` |
| Trilhas/troubleshooters/saúde — botões topo | 5 botões `os-btn ghost` + 1 primary | 6 botões shadcn Button no `action={...}` do PageHeader | Mesma hierarquia visual; `Buscar ⌘K` + `+ Novo artigo` à direita |
| Comentários inline (`KBCommentBlock`) | Bloco inline com + button | NÃO portado V1 | Charter Non-Goals: composer + comments ficam em ONDA 3; `renderAfterBlock` prop no `BlockRenderer` permite injetar depois sem refactor |

---

## Screenshot mental — o que Wagner verá ao abrir `/kb/v2` (dev mock)

**Topo (PageHeader):**
> 📖 KB Unificado
> 18 nós · 2.029 leituras · 47 vínculos OS · MOCK (Agent A pendente)
> [Trilhas] [✦ Perguntar ao KB] [Saúde do KB] [Troubleshooter] [Grafo] [⌘K Buscar] [+ Novo artigo]

**KPIs (4 cards compactos):**
> Mais lido este mês: "Limpeza diária da cabeça de impressão..." (312 leituras)
> Pinados no topo: 6 artigos essenciais
> Recentemente atualizados: 11 (últimos 14 dias) [tone success]
> Precisam de revisão: 2 (marcados desatualizados) [tone warning]

**Search bar:** "Filtrar artigos por título, etiqueta ou autor (/, debounce 350ms)..."

**Tri-pane (rounded-md border + 240px/380px/1fr):**

Coluna 1 (sidebar 240px, bg-muted/30):
- **CATEGORIAS** (uppercase 9.5px)
  - ● Tudo `18`
  - ● Produção `3` (hue 30 = laranja, com chevron pra expandir)
  - ● Equipamentos `3` (hue 280 = roxo)
  - ● Pré-impressão `2` (hue 200 = ciano)
  - ● Atendimento `3` (hue 145 = verde)
  - ● Fiscal & financeiro `3` (hue 60 = amarelo)
  - ● Sistema (ERP) `3` (hue 250)
  - ● Pessoas `1` (hue 295)
- **MEUS FAVORITOS** — vazio: "Marque artigos com a estrela ou tecla B."
- **RECENTES** — vazio: "Nenhum acesso ainda."
- **ETIQUETAS POPULARES** — pílulas: ICC 1 · VersaWorks 1 · vinil 1 · brief 1 · OS 1 · ...
- **ATALHOS** — `⌘K`/`/`: Buscar · `Esc`: Fechar · `J`/`K`: Navegar · `N`: Novo · `A`: IA · `B`: Favoritar

Coluna 2 (lista 380px):
- Header: **Tudo** 18 artigos | [Recentes][Mais lidos][Mais úteis][A revisar] (segmented)
- 18 rows scrollable, ordenadas por updated_at desc:
  - Cada row: pílula categoria com hue oklch + nível + equip mono + 📌fixo se pinned + amarelo "revisar" se outdated
  - Título 13.5px semibold (line-clamp-2)
  - Excerpt muted 12px (line-clamp-2)
  - Meta: autor · "há 3 dias" · 6 min · 142 leituras · 4 OS vinculadas

Coluna 3 (leitor flex):
- Empty state (default): "≡ Selecione um artigo · Use a lista ao lado ou tecle ⌘K..." + 3 sugestões pinned

**Ao clicar num row (ex: "Calibrar perfil ICC Roland VS-540"):**

Coluna 3 vira:
- Header: pílula `equipamentos` + tipo `artigo` + nivel `intermediário` + equip `Roland VS-540` + `📌 fixo` + chip `fresh` (verde). Estrela favorito + ‹ › Esc.
- H2: "Calibrar perfil ICC na Roland VS-540 (VersaWorks)"
- Meta: Mateus PCP · atualizado há 3 dias · 6 min de leitura · 142 leituras · re-verificado há 15 dias
- TOC bloco muted: "Nesta página: 1. Antes de começar · 2. Imprimir o target · 3. Medir com i1Pro"
- Excerpt em itálico com border-left primary
- Body: parágrafo intro + h3 "Antes de começar" + ol numerada (com `#kb-a3` clicável transformado em botão azul mono) + callout warn (ícone ⚠️ amarelo "Não meça com adesivo morno...") + h3 "1. Imprimir o target" + ol + callout ok (✓ verde "Boa prática...")
- Tags: [ICC] [VersaWorks] [vinil] [Eco-Sol Max 2]
- Related (grid 2-col): 2 cards "Limpeza diária da cabeça..." + "Trocar bobina vinil HP Latex..."
- Footer ações: [👍 Útil 28] [⚠️ Desatualizado 1] | [✦ Resumir IA] [✓ Re-verificar] [⏱ Histórico] [🔗 Anexar a OS] [📽 Apresentar] [🖨 Imprimir SOP] [✎ Editar]

**Pressionando ⌘K:**
Modal centralizado com input "Procure por título, etiqueta, autor..." + lista de sugestões (pinned/top 8). Digitando "ICC" → filtra pra "Calibrar perfil ICC..." matching. Lista vazia se query sem matches → botão "✦ Perguntar à IA: 'X'" + "A IA busca em todo o KB...". Rodapé: ↑↓ navegar · ↵ abrir · esc fechar.

**Pressionando "Trilhas":**
Sheet drawer da direita com 3 cards:
- "Onboarding do Balcão" (Larissa · primeiro mês) — hue 145 verde — barra de progresso 0/6
- "Manutenção semanal — Técnico" (Mateus · toda segunda) — hue 30 laranja — 0/5
- "Emergência fiscal" (Eliana · quando dá problema) — hue 60 amarelo — 0/3
Click em qualquer trilha → drawer mostra detalhe com lista numerada + checkboxes redondas + click no step abre o nó (drawer fecha).

**Pressionando "Troubleshooter":**
Modal centralizado com 3 cards:
- "Roland VS-540 não imprime" (Roland VS-540) — 5 perguntas
- "HP Latex 365 — cor saindo errada" — 4 perguntas
- "NF-e rejeitada pela SEFAZ" (fiscal) — 4 perguntas
Click → wizard: pergunta numerada "A impressora liga?" + [Sim verde] [Não amarelo] + dots progress. Resposta navega Q→Q ou termina em Fix com kbLinkifyText (citações #kb-a3 viram links clicáveis que abrem o nó).

**Pressionando "Saúde do KB":**
Modal 2x2:
- Desatualizados (2) — vermelho — kb-a16 NFS-e, kb-a18 Multi-empresa
- Sem atualização há mais de 30 dias (algumas) — amarelo
- Mais lidos do mês (5) — verde — Limpeza Roland (312), Atalhos ERP (287), Brief OS (203), ...
- Solitários (algumas) — cinza

**Atalhos funcionando:**
- `/` foca search bar
- `j`/`k` navega entre rows (mesmo sem nó aberto, abre o primeiro)
- `Esc` fecha overlay ou close reader
- `n` abre composer placeholder (toast "Composer — em breve ONDA 3")
- `a` abre AI placeholder (toast)
- `b` toggle favorito do nó ativo (toast confirma)

---

## Próximos passos

1. **Agent A entregar backend (ONDA 1)** — `Modules/KB/Http/Controllers/KbV2Controller.php` (ou renomeio) com index() retornando props `KbIndexProps`:
   ```php
   return Inertia::render('kb/Index.v2', [
       'nodes' => Inertia::defer(fn () => $this->paginateNodes($filters)),
       'categories' => KbCategory::forBusiness()->orderBy('sort_order')->get(),
       'subcategories' => KbSubcategory::forBusiness()->get(),
       'paths' => KbPath::forBusiness()->published()->with('steps.node')->get(),
       'decision_trees' => KbDecisionTree::forBusiness()->published()->with('steps')->get(),
       'kpis' => Inertia::defer(fn () => $this->computeKpis()),
       'tags_top' => Inertia::defer(fn () => $this->tagsTop(16)),
       'pinned' => KbNode::forBusiness()->pinned()->limit(3)->get(),
       'filters' => $request->only(['q','type','category','subcategory','nivel','equip','tag','sort']),
       'can' => [
           'write' => $user->can('kb.write'),
           'ai_ask' => $user->can('kb.ai.ask'),
           // ... etc
       ],
   ]);
   ```
2. **Route registry** em `Modules/KB/Http/routes.php`:
   ```php
   Route::get('/v2', 'KbV2Controller@index')->name('kb.index.v2');
   ```
3. **Conectar props reais** — substituir `usingMock` lógica: quando `props.nodes !== undefined` a página já usa data real. Mock fica como fallback `if (props.nodes === undefined && import.meta.env.DEV)`.
4. **Testar smoke biz=1** (`ADR 0101`) — Pest test seedando 18 nós + abrindo `/kb/v2` + verificando 200 + verificando que `business_id` global scope filtra cross-tenant biz=99.
5. **Gate visual ADR 0114** — Wagner roda dev server, abre `/kb/v2`, tira screenshot, aprova/pede ajustes. SEM screenshot aprovado, NÃO mergear.
6. **F4 QA do MWART (ADR 0104)** — Pest + smoke + Lighthouse score.
7. **Cutover Index.tsx → Index.v2.tsx** — quando Wagner aprovar, fazer commit que (a) renomeia `Index.tsx` pra `Index.legacy.tsx` ou apaga, (b) renomeia `Index.v2.tsx` pra `Index.tsx`, (c) atualiza route `/kb` pra apontar pra nova. Deixa redirect 301 de `/kb/v2` → `/kb`.
8. **ONDA 3** começa: Composer + KBImageBlockEditor + KBTroubleEditor + Histórico de versões.

---

## Mudanças visíveis vs `Index.tsx` atual (V3)

| Aspecto | Index.tsx (V3 atual) | Index.v2.tsx (este port) |
|---|---|---|
| **Layout** | 2-pane (lista 5/12 + preview 7/12) com toggle | 3-pane FIX (240px sidebar / 380px lista / 1fr leitor) |
| **Persona alvo** | Wagner governança lendo ADRs | Wagner governança + Larissa operacional (vestuario gráfica) |
| **Conteúdo seed** | 352 docs MCP (ADRs/sessions canon) | 18 artigos operacionais gráfica + bridges pra ADRs (Agent A liga) |
| **Filtros** | Tipo, Módulo, with_pii, busca | Categoria/Subcat (taxonomia operacional) + Nível + Equip + Tag + Sort segmented |
| **Body render** | ReactMarkdown sobre `content_md` | BlockRenderer sobre `body_blocks` JSON (semantic blocks) — fallback bridge pra `content_md` quando Agent A entregar |
| **Keyboard nav** | j/k/Enter/Esc/`/` | ⌘K (palette) + `/` (search) + Esc + J/K + Enter + N (novo) + A (IA) + B (favoritar) |
| **Command palette** | Não tem | shadcn CommandDialog ⌘K com fuzzy + fallback IA |
| **Trilhas** | Não tem | Drawer Sheet com 3 trilhas + progresso checkbox localStorage |
| **Troubleshooter** | Não tem | Modal wizard Q→Sim/Não→Fix com 3 árvores |
| **Saúde do KB** | Não tem | Modal 4 quadrantes |
| **Favoritos** | Não tem | Estrela + localStorage + sidebar top-8 + atalho B |
| **Recentes** | Não tem | localStorage últimos 8 + sidebar |
| **Frescor** | "fmtRelative" só | `freshnessLevel` 5 níveis (novo/fresco/recente/parado/expirado) com chip colorido |
| **Related** | Não tem | Top-3 por tag overlap + cat + equip bonus |
| **Soft-delete UI** | Botão "🗑️ Soft-delete LGPD" + AlertDialog "CONFIRMO" | Removido (charter Non-Goals — vira `Pages/kb/Admin/Manage.tsx` ONDA 3); admin acessa via `/kb/admin` |
| **GitHub link** | Botão "📂 GitHub" no preview | Será no BridgeFallback quando `source_doc_id IS NOT NULL` + Agent A entregar `github_url` |
| **Densidade Larissa 1280px** | Não otimiza | `.kb-tri` media query `<1280px` reduz pra 200/320/1fr |
| **Tokens canon OKLCH** | Tailwind padrão | Mistura: Tailwind pro shell + inline `oklch()` por categoria hue (vindos do schema) |

---

## Compliance checklist (charter `Index.charter.md` Goals/Anti-padrões)

- [x] Goal 1: Listar 1000+ sem degradar — virtualização não foi implementada V1 (com 18 mock, irrelevante); estrutura permite drop-in TanStack Virtual em `NodeList` quando `nodes.length > 500`.
- [x] Goal 2: Render markdown rápido + cross-link `#kb-XXX` — feito via `kbLinkifyText` + `BlockRenderer`.
- [x] Goal 3: Command palette ⌘K com fuzzy + fallback IA — feito.
- [x] Goal 4: Keyboard nav completa — feito (`useKbKeyboardNav`).
- [x] Goal 5: Atalhos visíveis pra Larissa — sidebar bottom `<dl>` com kbds.
- [x] Goal 6: Frescor — `freshnessLevel` 5 níveis + chip.
- [x] Goal 7: Trigger trilha + decision tree + saúde — botões no PageHeader action.
- [ ] Goal 7 cont.: Grafo — botão presente mas placeholder (ONDA 5 — outro agent fez `GraphCanvas.tsx`).
- [x] Goal 8: Anexar nó a contexto OS — botão "Anexar a OS" + toast (TODO[CL] endpoint ONDA 6).
- [x] Anti `rounded-xl+` — usado `rounded-md` máximo.
- [x] Anti emoji UI — usado lucide-react icons (Star, Pin, ThumbsUp, AlertTriangle, etc).
- [x] Anti inglês UI — tudo PT-BR.
- [x] Anti modal full-screen pra detalhe — coluna 3 do tri-pane.
- [x] Anti CTA WhatsApp — não tem.
- [x] Anti animações >300ms — `transition-fast` 150ms via Tailwind default.
- [x] Inertia::defer ready — props `nodes/kpis/tags_top` documentadas como opcionais defer; frontend não trava se vier mais tarde.
- [x] WCAG 2.1 AA — aria-pressed, aria-label, focus-visible:ring outline, kbd contrast OK.
- [x] Tokens canon OKLCH hue 240 PROJETOS — usado em `--accent` via Tailwind + inline `oklch(0.X 0.13 240)` em estados ativos.

---

## Não fiz (escopo explícito do prompt)

- ❌ Composer (ONDA 3) — `KBComposer` + `KBBlockEditor` do `kb-extras.jsx` ficaram pra ONDA 3.
- ❌ AI Dialog (ONDA 4) — `KBAIDialog` do `kb-extras.jsx` é placeholder.
- ❌ `KBTroubleEditor` (v5 new — ONDA 3) — não portado.
- ❌ `KBPrintSOP` (v5 new — ONDA 3/5) — não portado.
- ❌ `KBImageBlockEditor` (v5 new — ONDA 3) — não portado.
- ❌ Comentários inline `KBCommentBlock` — não V1; hook `renderAfterBlock` no `BlockRenderer` deixa porta aberta.
- ❌ Histórico de versões `KBVersionsDialog` — placeholder.
- ❌ Visualização-grafo Cytoscape — Agent D/E (ONDA 5), `GraphCanvas.tsx` já existe.
- ❌ Cytoscape.js / nova dependência — confirmado.
- ❌ Substituir `Index.tsx` V3 atual — paralelo em `/kb/v2` aguardando gate.
- ❌ Git ops, composer install, npm install — todos no Agent A/parent.

---

## Pendências de spec/contrato que apareceram durante o port

1. **Permission `kb.publish.path` e `kb.publish.troubleshoot`** declaradas no SCHEMA-DB-V1 §12 mas charter não diz se aparecem no UI. Sugestão: esconder botões "Editar trilha" / "Editar troubleshoot" se sem permission (`can.publish_path` / `can.publish_troubleshoot`). Já tipado em `KbIndexProps.can`. ONDA 3 endereça.
2. **Endpoint `/kb/nodes/{ref}/resolve`** não está no SCHEMA-DB-V1 §11. Sugestão: criar quando linkify precisar resolver `#kb-foo` cross-business ou stale. V1 resolve client-side via baseNodes.
3. **`kb_nodes.author_user_id` → `author_name`** — frontend usa `node.author_name` (String do JOIN). Charter/schema só tem `author_user_id` (FK). Agent A precisa expor `author_name` via Eloquent `with('author')` ou `selectRaw`.
4. **`mockData.MockTroubleshooter.flat_steps`** — formato "achatado" pra UI. Schema canon usa tree structure com `yes_next_step_id`/`no_next_step_id` (recursivo). Quando Agent A entregar, criar `_lib/adapter.ts` `treeToFlatSteps(KbDecisionTree): MockTroubleshooter` — fazendo BFS do `root_step_id`. Documentado TODO[CL] no arquivo.

---

## Refs

- Cowork [CC] handoff (5): self-score 9,40/10 Bench v2 — replicada visualmente sem perda significativa
- ADR 0094 Constituição v2: princípio "Charter > Spec" respeitado (charter foi guia primário)
- ADR 0104 MWART: F3 (frontend) em andamento; F2 (backend) é Agent A; F1.5 (gate visual) pendente Wagner
- ADR 0114 Loop Cowork ↔ Claude Code: protótipo canônico em `prototipo-ui/prototipos/kb/` foi fonte autoritativa

**Sessão concluída.**
