---
slug: jana-chat-visual-comparison
title: "Jana/Chat — visual comparison (gate F1.5 layout-only)"
type: visual-comparison
module: Jana
status: approved
target_page: resources/js/Pages/Jana/Chat.tsx
target_charter: resources/js/Pages/Jana/Chat.charter.md
visual_source: memory/requisitos/_DesignSystem/ui_kits/cowork-2026-05-09/chat.jsx
visual_source_html: memory/requisitos/_DesignSystem/ui_kits/cowork-2026-05-09/Oimpresso ERP - Chat.html
related_adrs: [0094, 0104, 0107, 0110, 0114]
date: 2026-05-15
session: gifted-raman-ce3c6b
escopo: layout-only (Wagner aprovou 2026-05-15)
aprovacoes:
  - escopo: 2026-05-15 via AskUserQuestion "Layout-only no Pages/Jana/Chat.tsx (Recomendado)"
  - decisoes_4: 2026-05-15 via AskUserQuestion → labels Charter / mostrar todas tabs / client-side search / badge unread sim (bind ⌘K não)
---

# Chat.tsx — Visual Comparison F1.5

> **Gate canônico** ADR 0107 + ADR 0114 antes de Edit/Write em Page. Wagner pediu *"implementar Oimpresso ERP - Chat.html"*; depois clarificou (em UI question) `Layout-only no Pages/Jana/Chat.tsx` preservando semântica Jana IA do charter.

## TL;DR

A tela **já está live** em `/copiloto` (URL legacy, módulo `Pages/Jana/Chat.tsx`). Thread+composer são delegados a `JanaAssistantUiChat` (lib `assistant-ui`) — **NÃO entram no escopo**. O delta visual está restrito à coluna esquerda (`ConvSidePanel`), que hoje é minimal (sb-action + sb-section-h + sb-conv bullet) e o protótipo Cowork tem rica (header + search + 4 tabs + ConvItem com avatar/preview/badge).

Como `ConversaResumo` (backend) hoje só expõe `id/titulo/unread/origem/ativa`, **avatar/preview/badge ficam fora** (exigiriam migration + ADR de schema — proibido em "layout-only"). Entregamos os enriquecimentos que **não dependem de backend**: header, search local (filtra `titulo` client-side), tabs filtro com **labels do charter** (Todas/Minhas/Compartilhadas/Arquivadas — NÃO os do Cowork OS/Equipes/Clientes que conflitam com Charter §Goals).

## Comparáveis canônicos (Charter §15 — mwart-comparative V4)

- **Linear Inbox** (densidade thread + atalhos J/K) — referência principal mantida
- **Front conversation view** — referência pra `data_table`/`action_card`
- **ChatGPT** — só streaming/composer (bubble grande = anti-pattern aqui)

Cowork `chat.jsx` adiciona **header + search + tabs** úteis ao Charter; outras peças (ConvItem rico, file attachment ✓✓, "Recebido, vou verificar 👍" auto-reply mockada) são **Non-Goals explícitos** do charter ou exigem schema novo.

## 15 dimensões (Cowork chat.jsx ↔ Pages/Jana/Chat.tsx atual ↔ alvo proposto)

| # | Dimensão | Cowork chat.jsx | Chat.tsx atual | Alvo proposto (layout-only) | Veredito |
|---|---|---|---|---|---|
| 1 | **Layout master/detail** | `.app` grid sidebar+thread | `copiloto-chat-layout` grid 320/1fr | Mantém | ✅ paridade |
| 2 | **Header da lista** | `<h2>Chat</h2>` + filter btn + nova conv btn | sb-actions verticais ("Nova conversa" linha inteira) | Adiciona barra `<header>` topo c/ título "Chat" + 2 icon-buttons à direita | 🟡 add |
| 3 | **Search input** | `<input>` + `⌘K` kbd hint | ausente | Adiciona search controlado, filtra `titulo` client-side (case-insensitive) | 🟡 add |
| 4 | **Tabs/filtros** | 4 pills (Todas/OS/Equipes/Clientes) | apenas separadores "Fixadas"/"Recentes" | 4 pills **labels do Charter** (Todas/Minhas/Compartilhadas/Arquivadas). Filtro client-side só "Todas" funcional hoje (resto vira UI gating até backend expor flag) | 🟡 add charter-compliant |
| 5 | **Conv item — avatar** | `.av` + gradient determinístico + dot online | bullet 10px só | **Fora do escopo** (backend não expõe avatar/online) | ❌ defer |
| 6 | **Conv item — preview/snippet** | última msg em `<div class="preview">` | só título | **Fora do escopo** (backend não retorna `preview`) | ❌ defer |
| 7 | **Conv item — tag tipo** | `.tag.os` / `.tag.eq` (OS/Equipe/Cliente) | ausente | **Fora do escopo** (Charter Non-Goal: tipos OS/Equipe são chat humano-humano, não Jana IA) | ❌ skip (anti-charter) |
| 8 | **Conv item — unread badge** | `<span class="badge">` número | já existe (`unread`) mas não renderizado no `sb-conv` | Adiciona pequena badge ao lado direito do `sb-conv-t` quando `unread > 0` | 🟡 add (backend já expõe) |
| 9 | **Pinned vs Recentes** | grupos com `.list-group-h` "Fixadas"/"Recentes" | `sb-section-h` "Fixadas"/"Recentes" | Mantém | ✅ paridade |
| 10 | **Thread header** | `.th-head` avatar+nome+dot+actions | `ThreadHeader` do `@/Components/cockpit/Thread` (cobre tudo) | Mantém — fora do escopo | ✅ paridade |
| 11 | **Composer** | textarea auto-grow + toolbar + send | `JanaAssistantUiChat` (lib assistant-ui) | Mantém — Charter exige Brain B streaming, não o composer mock do Cowork | ✅ charter-compliant |
| 12 | **Bubble assistant** | "them" simples (texto + ✓✓) | `JanaAssistantUiChat` (markdown + code highlight via assistant-ui) | Mantém | ✅ richer-than-cowork |
| 13 | **Empty state** | `.empty` "Selecione uma conversa" + `⌘K` hint | tela vai direto pra conversa ativa (não tem empty state) | **Fora do escopo** — fluxo Inertia sempre tem conversa default | ✅ N/A |
| 14 | **Atalhos teclado** | `⌘K` hint visual | `⌘N` em `sb-action` Nova conversa | Mantém `⌘N`, adiciona `⌘K` visual no search (sem binding ainda — backlog do Charter §UX Targets) | 🟡 add visual |
| 15 | **CSS scope** | global `:root`+`body` em styles.css 120KB | escopado `.cockpit{}` em cockpit.css + Tailwind utilities (ADR 0110) | Tailwind utilities inline + classes existentes (`sb-*`, `kbd`) — **zero novo CSS global** | ✅ ADR 0110 |

## Delta concreto (diff plan)

**Arquivos tocados (1):** `resources/js/Pages/Jana/Chat.tsx`

**Linhas afetadas:** ~80 (substitui `ConvSidePanel` interno por versão com header+search+tabs+filter; mantém props/types existentes).

**Pseudo-diff:**

```diff
 function ConvSidePanel({ fixadas, recentes, activeConvId, onSelectConv }: { ... }) {
+  const [query, setQuery] = useState('');
+  const [tab, setTab] = useState<'todas'|'minhas'|'compartilhadas'|'arquivadas'>('todas');
+
+  const norm = (s: string) => s.toLowerCase().normalize('NFD').replace(/\p{Diacritic}/gu, '');
+  const matchQ = (c: ConversaResumo) => !query.trim() || norm(c.titulo).includes(norm(query.trim()));
+  const filteredFixadas = fixadas.filter(matchQ);
+  const filteredRecentes = recentes.filter(matchQ);
+
   return (
     <aside className="copiloto-chat-convs">
+      <header className="cs-head"><h2>Chat</h2><button .../><button .../></header>
+      <div className="cs-search">
+        <Search size={12}/><input value={query} onChange={...} placeholder="Buscar conversas..."/>
+        <span className="kbd">⌘K</span>
+      </div>
+      <div className="cs-tabs">
+        {TABS.map(t => <button className={cls('cs-tab', tab===t.id && 'active')} ...>{t.label}</button>)}
+      </div>

       <div className="sb-actions">
         <a href="/jana/conversas/nova" className="sb-action">…</a>
         …
       </div>

       <div className="sb-section-h">Fixadas</div>
-      {fixadas.map(c => …)}
+      {filteredFixadas.map(c => <SbConvItem c={c} active={…} onSelect={…} />)}
       <div className="sb-section-h">Recentes</div>
-      {recentes.map(c => …)}
+      {filteredRecentes.map(c => <SbConvItem c={c} active={…} onSelect={…} />)}
     </aside>
   );
 }

+function SbConvItem({ c, active, onSelect }: { c: ConversaResumo; active: boolean; onSelect: (id: string) => void }) {
+  return (
+    <div className={cls('sb-conv', active && 'active')} onClick={() => onSelect(c.id)}>
+      <span className={cls('sb-bullet', c.unread && 'filled')}/>
+      <span className="sb-conv-t">{c.titulo}</span>
+      {c.unread ? <span className="sb-conv-badge">{c.unread}</span> : null}
+    </div>
+  );
+}
```

## Classes CSS

- ✅ **Reusa existentes** em `resources/css/cockpit.css`: `.sb-actions`, `.sb-action`, `.sb-section-h`, `.sb-conv`, `.sb-bullet`, `.kbd`, `.beta`, `.copiloto-chat-convs`
- 🟡 **Adiciona** (mínimo, escopados em `.cockpit .copiloto-chat-convs`):
  - `.cs-head` — header com título + 2 icon-btn
  - `.cs-search` — wrap pro input + `Search` icon + `⌘K` kbd
  - `.cs-tabs` / `.cs-tab` / `.cs-tab.active` — 4 pills inline
  - `.sb-conv-badge` — badge unread minúscula ao lado direito (`bg-primary text-primary-foreground` mas via CSS pra não brigar com tema)
- ❌ **Não importa** styles.css do Cowork (escopo global vs `.cockpit{}` — ADR `_DS UI-0012` repo vence)

## Pest GUARD (charter §Métricas vivas — escopo desta PR)

Charter lista 13 testes (`JanaChatCharterTest.php`). Em "layout-only" entra subset que toca lista de conversas:

```php
it('renders search input in ConvSidePanel')                 // novo
it('renders 4 filter tabs (Todas/Minhas/Compartilhadas/Arquivadas)')  // novo
it('filters conv list by titulo (case+accent-insensitive) on query input')  // novo
it('uses localStorage prefix oimpresso.jana.* (never sessionStorage)')  // charter
it('renders at 1280px without horizontal scroll')           // charter
it('isolates threads by business_id')                       // charter (pré-existente — não regrede)
```

Demais testes (`first-paint`, `streaming token`, `auto-scroll pause`, `PII sanitizer`) ficam pra PR seguinte (composer/thread já são delegados a `JanaAssistantUiChat`, fora do escopo).

## Anti-padrões F3 Financeiro evitados explicitamente

Conferindo contra [`prototipo-ui/LICOES_F3_FINANCEIRO_REJEITADO.md`](../../../prototipo-ui/LICOES_F3_FINANCEIRO_REJEITADO.md):

| Anti-padrão | Aplicabilidade aqui | Como evitado |
|---|---|---|
| M-AP-1 (auto-doc ignorada) | Sim | Li Chat.charter.md + RUNBOOK-chat.md + ConversaResumo type + Thread.tsx (callsite CHAT_TABS) ANTES |
| M-AP-2 (marketing > realidade) | Sim | PR title vai ser `feat(jana): ConvSidePanel — header+search+tabs (layout-only, charter §Goals)` — não "Chat F3 completo" |
| M-AP-3 (aceitação tácita = aprovação) | Sim | Wagner aprovou OPÇÃO 1 ("Layout-only"); este doc é o F1.5 gate; aguarda go-ahead explícito antes de Edit |
| M-AP-4 (schema fantasma) | Sim | `ConversaResumo` mantido intacto; sem migration; campos avatar/preview/tag ficam `❌ defer` |
| T-AP (middleware/Models fantasma) | N/A | Sem mudança backend |
| T-AP (regressão fix em prod) | Sim | Chat.tsx vai ser tocado por DIFF cirúrgico — `JanaAssistantUiChat`, `ThreadHeader`, `PropostaCard`, props/types **intocados** |

## Decisões pendentes pra Wagner aprovar (antes de Edit)

1. ✅ **Escopo confirmado** (UI question 2026-05-15): "Layout-only no Pages/Jana/Chat.tsx"
2. ⏳ **Labels dos 4 tabs**: Charter diz `Todas/Minhas/Compartilhadas/Arquivadas` — confirma?
3. ⏳ **Funcionalidade dos tabs hoje**: só "Todas" filtra (resto é UI gating até backend expor `compartilhada`/`arquivada` em `ConversaResumo`). OK ou prefere esconder os 3 não-funcionais até backend?
4. ⏳ **Search**: filtra `titulo` client-side (não chama `/jana/conversas?q=`). OK ou quer roundtrip backend?
5. ⏳ **`⌘K` binding teclado**: charter pede focus composer com `/`; `⌘K` no search é só visual (sem binding nesta PR). OK?
6. ⏳ **Unread badge no `.sb-conv`**: pequena badge à direita quando `unread > 0`. Aprovado?

## Refs

- [Chat.charter.md (LIVE v1)](../../../resources/js/Pages/Jana/Chat.charter.md)
- [RUNBOOK-chat.md](RUNBOOK-chat.md)
- [Cowork chat.jsx (visual source)](../_DesignSystem/ui_kits/cowork-2026-05-09/chat.jsx)
- [ADR 0107 — visual gate F1.5](../../decisions/0107-emendation-0104-visual-comparison-gate-f3.md)
- [ADR 0114 — loop Cowork formalizado](../../decisions/0114-prototipo-ui-cowork-loop-formalizado.md)
- [ADR 0110 — Cockpit Pattern V2](../../decisions/0110-cockpit-pattern-v2-canon-list-detail.md)
- [ADR 0094 — Constituição v2](../../decisions/0094-constituicao-v2-7-camadas-8-principios.md)
- [LICOES_F3_FINANCEIRO_REJEITADO.md (anti-padrões)](../../../prototipo-ui/LICOES_F3_FINANCEIRO_REJEITADO.md)
