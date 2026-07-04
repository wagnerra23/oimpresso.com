---
page: /jana/chat
component: resources/js/Pages/Jana/Chat.tsx
related_us: [US-COPI-001, US-COPI-105]
owner: wagner
status: live
last_validated: "2026-05-18"
parent_module: Jana
related_adrs: [110, 94, 107, 114]
tier: A
charter_version: 2
---

# Page Charter — /jana/chat

> **Status:** novo (P2 do `TELAS_REVIEW_QUEUE.md`). Charter criado ANTES do refator visual pra fixar escopo — evita virar Christmas tree de features. Este é o **único ponto de IA conversacional cliente-facing** do oimpresso (Brain B / Sonnet via gateway interno).

---

## Mission

Conversar com a Jana (IA assistente do oimpresso) pra **consultar dados** (vendas, OS, financeiro, estoque) e **pedir ações cross-módulo** (criar OS, registrar pagamento, listar inadimplentes) via linguagem natural — sem o usuário trocar de tela e sem aprender SQL/atalho de cada módulo.

---

## Goals — Features (faz)

- AppShellV2 + topnav inline com breadcrumb (Cockpit V2 canon)
- **Header sticky área "JANA"** com dot da área (hue 220 — SIDEBAR_GROUP_HUE.ia) + label "JANA" à esquerda + **tabs `Dashboard | Chat`** (navegação Inertia entre `/jana/dashboard` e `/jana`). Espelha `prototipo-ui/_cowork-export-2026-05-15/app.jsx` Header function (L247-336). Componente compartilhado `JanaAreaHeader` plugado em Chat.tsx + Dashboard.tsx. Tabs usam `<Link>` (Inertia router.get) com `data-active` baseado em URL atual. Sticky `top-0 z-10 backdrop-blur` mantém referência visual durante scroll thread. Charter §UX Anti-patterns respeitado: ícones lucide (LayoutDashboard + MessageSquare), nunca emoji.
- Layout 2-col: histórico de conversas (lista esquerda, ~280px) + thread ativa (centro, fluido)
- Sidebar "Conversas" com pills de filtro `rounded-full`: Todas / Minhas / Compartilhadas / Arquivadas
- Thread central com bubbles separadas por papel: `user` (direita, `bg-primary/5`) e `assistant` (esquerda, `bg-card`)
- Cada mensagem do assistente pode renderizar **blocos estruturados** alinhados com o output do Brain B:
  - `tool_use` — chip mostrando ferramenta acionada (ex: "Consultou /sells-list-json")
  - `data_table` — tabela inline read-only quando resposta traz lista (ex: "5 vendas atrasadas")
  - `action_card` — confirmação de ação executada (ex: "Pagamento R$ [redacted Tier 0] registrado em fatura #1234")
  - `markdown` — fallback texto quando resposta livre
- Composer fixo no bottom, multi-line, com `⌘+Enter` / `Ctrl+Enter` pra enviar
- Indicador "Jana está pensando..." (`animate-pulse` curto, sem skeleton infinito) durante stream
- Streaming token-a-token (resposta aparece progressiva, não em bloco)
- Atalhos teclado: `/` foca composer, `J/K` navega entre mensagens da thread, `Esc` desfoca
- Persistência: thread atual + filtro lista em localStorage prefix `oimpresso.jana.*`
- Multi-tenant Tier 0: toda thread, mensagem e ação respeitam `business_id` global scope
- PII: composer mostra aviso sutil quando detecta padrão CPF/CNPJ/cartão antes de enviar

---

## Non-Goals — Features (NÃO faz)

> Anti-alucinação. Cada item vira Pest GUARD test (Non-Goal violado = CI quebra).

- ❌ Voice input (microfone/whisper) — backlog M2
- ❌ Anexar arquivos (PDF/imagem) — backlog M2, depende de Brain B vision policy
- ❌ Compartilhar thread externa (link público) — risco PII alto, backlog
- ❌ Editar mensagem enviada (canon = nova mensagem)
- ❌ Comparar respostas de múltiplos modelos lado-a-lado (não é playground)
- ❌ Configurar system prompt por usuário (system prompt é canon do business — superadmin-only)
- ❌ Executar SQL livre na conversa (toda ação passa por TaskProvider/tool registrado, ADR 0094)
- ❌ Mostrar custo $ por mensagem ao usuário final (custo vai pra `/governance` — Wagner-only)
- ❌ Histórico cross-business (cada business vê só suas threads, ADR 0093)
- ❌ Auto-execução destrutiva sem confirmação (delete/cancel exige `confirm_required` no tool result)

---

## UX Targets

- p95 first-paint < 1000ms (sidebar + thread vazia OU última thread cached)
- Primeiro token Jana < 800ms após enviar (latência percebida)
- 0 erros JS console
- Cabe em monitor 1280px sem scroll horizontal (cliente ROTA LIVRE)
- Thread mantém scroll-bottom durante stream (auto-scroll, mas pausa se user rolou pra cima)
- Tipografia canon ADR 0110: bubble texto 14px, chip 12px, sidebar item 13px
- Cores semânticas Cockpit V2: emerald (ação ok), rose (erro/recusa), amber (warning PII), sky (info/tool_use)
- Composer expande verticalmente até 8 linhas, depois scroll interno (não empurra thread fora)
- Atalhos respondem < 100ms

---

## UX Anti-patterns

- ❌ Modal pra detalhe de mensagem (canon = expandir inline ou abrir Sheet lateral)
- ❌ Avatar circular emoji-style (canon = letra/glyph monocromático em quadrado `rounded-md`)
- ❌ Cor crua `bg-(blue|green|red)-N` em status de tool_use (canon = sky/emerald/rose semântico)
- ❌ Bubble com `rounded-2xl` ou maior (canon = `rounded-md` cards, não-WhatsApp)
- ❌ Indicador "digitando..." com 3 dots animados em loop infinito (canon = `animate-pulse` 1 chip "Jana está pensando...")
- ❌ Streaming via `dangerouslySetInnerHTML` no markdown (XSS — usar `react-markdown` com sanitizer)
- ❌ Thread infinite-scroll sem cursor estável (canon = paginação por cursor `before_message_id`)
- ❌ `sessionStorage` (canon = `localStorage` com prefix `oimpresso.jana.*`)
- ❌ Auto-enviar em colar texto longo (gatilho explícito via Enter/botão)

---

## Automation Hooks

- Endpoint `JanaController::index()` carrega lista threads do business + thread ativa (cursor)
- `POST /jana/threads/{id}/messages` envia mensagem → enfileira job `ProcessJanaMessage` → stream via SSE/Centrifugo
- `ProcessJanaMessage` chama `BrainBClient` (gateway Sonnet interno) com tool registry filtrado pelo business
- Tool execution roda em transação separada com `business_id` scope; resultado volta como bloco `tool_use` + `action_card`
- Multi-tenant: query usa `business_id` global scope nos models `JanaThread`, `JanaMessage`
- Audit: toda mensagem assistant + tool_use registrada em `jana_audit_log` (lifecycle ativo, retenção 90d)

---

## Automation Anti-hooks

> O que essa tela NUNCA dispara. Vira Pest GUARD.

- ❌ Não dispara emails ao abrir (read da thread é puro)
- ❌ Não dispara SMS
- ❌ Não escreve no banco no render inicial (só no POST de mensagem)
- ❌ Não chama Brain B no render (só após user submit)
- ❌ Não acessa thread de outro `business_id` (multi-tenant Tier 0)
- ❌ Não persiste credencial Brain B no client (token vive no backend)
- ❌ Não roda tool sem auth check do tool registry (cada tool declara permission required)
- ❌ Não loga PII em plain text (sanitizer obrigatório antes de `jana_audit_log`)

---

## Métricas vivas (Pest GUARD — a escrever em F1.5)

```php
// Modules/Jana/Tests/Charters/JanaChatCharterTest.php

it('renders under 1000ms p95 with empty thread')
it('streams first token under 800ms p95')
it('does not emit emails on render or send')
it('does not dispatch jobs on render (only on POST)')
it('does not call BrainB on render')
it('isolates threads by business_id')
it('returns 404 for cross-tenant thread access')
it('sanitizes PII before audit log')
it('requires confirm_required for destructive tool_use')
it('renders at 1280px without horizontal scroll')
it('uses localStorage prefix oimpresso.jana.* (never sessionStorage)')
it('debounces repeated submit keystrokes within 100ms')
it('pauses auto-scroll when user scrolls up mid-stream')
```

---

## Comparáveis canônicos (15 dimensões — `mwart-comparative` V4)

- **Linear Inbox** (densidade thread + atalhos J/K) — referência principal
- **Front conversation view** (3-col, message blocks tipados) — referência pra `data_table`/`action_card`
- **ChatGPT** — apenas pra streaming e composer behavior; **não** pra visual (bubble grande/avatar circular = anti-pattern aqui)
- **Excluir:** Notion AI sidebar (densidade incompatível Larissa), Intercom Resolution Bot (UI muito "marketing")

---

## Refs

- [DESIGN.md §16 Cockpit V2](../../../../DESIGN.md)
- [ADR 0110 Cockpit Pattern V2](../../../../memory/decisions/0110-cockpit-pattern-v2-canon-list-detail.md)
- [ADR 0094 Constituição V2](../../../../memory/decisions/0094-constituicao-v2-7-camadas-8-principios.md) — IA + audit
- [ADR 0107 Visual gate F1.5](../../../../memory/decisions/0107-emendation-0104-visual-comparison-gate-f3.md)
- [prototipo-ui/CLAUDE_DESIGN_BRIEFING.md §7 proibições](../../../../prototipo-ui/CLAUDE_DESIGN_BRIEFING.md)

---

## Histórico

| Data | Autor | Mudança |
|---|---|---|
| 2026-05-09 | [CC] (Cowork) + [W] | Charter criado em P2 do TELAS_REVIEW_QUEUE.md, antes de qualquer refator visual. Disparado pela auditoria dos 9 charters P0/P1 (sessão 2026-05-09). |
| 2026-05-18 | [CL] Claude Code + [W] | charter_version → 2. Goal novo: header sticky com tabs `Dashboard | Chat` espelhando `app.jsx` Header function (linhas 247-336 protótipo Cockpit). Componente compartilhado `JanaAreaHeader` em Chat.tsx + Dashboard.tsx. Gate F1.5: `memory/requisitos/Jana/Chat-header-tabs-visual-comparison.md`. Pareado com PR #1053 (Fase 1+2 sidebar reordenada: shortcut Chat→IA). |
