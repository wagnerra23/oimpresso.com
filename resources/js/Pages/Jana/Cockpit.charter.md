---
page: /jana/cockpit
component: resources/js/Pages/Jana/Cockpit.tsx
owner: wagner
status: draft
status_detail: spec-ahead-of-impl
last_validated: "2026-05-15"
parent_module: Jana
parent_adr: memory/decisions/0035-stack-ai-canonica-wagner-2026-04-26.md
visual_source: prototipo-ui/_cowork-export-2026-05-15/chat-jana.jsx
visual_critique: prototipo-ui/_cowork-export-2026-05-15/CRITIQUE-chat-jana-vs-amendment.md
related_adrs: [35, 39, 94, 104, 107, 110, 114]
related_charters:
  - resources/js/Pages/Jana/Chat.charter.md
  - resources/js/Pages/Atendimento/CaixaUnificada/Index.charter.md
absorbs_when_live:
  - resources/js/Pages/Jana/Dashboard.tsx (vira tab `dashboard`)
supersedes_in_place:
  - resources/js/Pages/Jana/Cockpit.tsx (impl atual = anti-pattern WhatsApp-style do amendment 2026-05-14)
tier: A
charter_version: 1
permissao: copiloto.access
---

# Page Charter — `/jana/cockpit`

> **Status:** `spec-ahead-of-impl` — charter define o destino · `Cockpit.tsx` atual (138 lin) é MVP-piloto-em-validacao com anti-patterns do amendment-block-renderer 2026-05-14 (tabs `Todos/OS/Equipe/Clientes`, `setTimeout` 2400ms, resposta humana literal *"Recebido, vou verificar e te respondo já já 👍"*). Substituição em-place quando F1.5 ≥80 + screenshot Wagner.
>
> **Fonte canônica visual:** [`prototipo-ui/_cowork-export-2026-05-15/chat-jana.jsx`](../../../prototipo-ui/_cowork-export-2026-05-15/chat-jana.jsx) (491 lin) + [`chat-jana.css`](../../../prototipo-ui/_cowork-export-2026-05-15/chat-jana.css) (645 lin) — score F1.5 interim 78/100 ([CRITIQUE](../../../prototipo-ui/_cowork-export-2026-05-15/CRITIQUE-chat-jana-vs-amendment.md)).

---

## Mission

Cockpit do **Analista IA (Jana)** — entrega brief diário, monitora KPIs, detecta anomalias, sugere ações HITL e responde via chat single-thread.

Audiência primária: **Wagner / Larissa** (dono / gerente) — não operador de atendimento. Layout 1-col scrollable em vez de 3-col conversational, porque não há multi-conversa: existe **uma única thread** com a Jana, persistente por business.

Substitui `Cockpit.tsx` atual que erroneamente implementou um chat WhatsApp-style (anti-pattern do amendment-block-renderer 2026-05-14). Coexiste com `Chat.tsx` (`/jana/`, 2-col conversacional) durante canary.

---

## Goals — Features (faz)

### Layout 1-col scrollable

- **Header sticky** — avatar mono primary letra "J" (32×32 `rounded-md`) · "Jana · Analista IA" · chip business `{biz.short_name} · biz=N` (Tier 0 visível) · timestamp "Atualizado HH:MM" · botões Configurar / Exportar
- **2 tabs** no topbar do AppShellV2: `Dashboard` (default) | `Analista IA` (chat tab) — persiste em `localStorage.oimpresso.jana.cockpit.tab`

### Tab `dashboard` (default)

- **Brief diário** — section card primária:
  - Saudação personalizada por hora ("Bom dia/tarde/noite, {nome}")
  - 4 paragraphs ricos: text descritivo + paragraph com "Ação sugerida HOJE" (icon 🎯) + paragraph "Anomalia detectada"
  - Ranges em mono · valores destacados strong/danger
  - Linha de chips de ação rápida (4-6 chips com tone primary/ghost)
  - Botão "▶ Ouvir áudio" (TTS placeholder backlog M2)
- **4 KPI cards** — grid responsivo (2×2 em 1280px):
  - Receita do mês (delta vs mês anterior)
  - A receber vencido (emphasize quando crítico — bg vermelho-soft)
  - Ticket médio (delta tendência)
  - Frota / utilização (sub-text com paradas)
  - Cada KPI: label uppercase · value bold · delta com seta + classe (down/up/info) · sub opcional
- **6 Análises principais** — grid responsivo:
  - Cada análise: ícone + title + sub + pill de tone (CRÍTICO/QUEDA/OK/REATIVAR/PARADAS) + big value + render variant
  - 6 variants suportadas: `buckets` (barras horizontais com cor por bucket) · `sparkline` (curva 24 meses suave) · `bars` (barras horizontais simples) · `list` (rows label/value) · `donut` (SVG segmentos coloridos) · `text` (lista bulletada com markers)
  - Footer ou footnote opcional
- **4 Ações HITL** — rows full-width:
  - Tone color-coded (rose/violet/peach/grey) — soft bg
  - Ícone + title + sub + CTA com tone (danger/violet/orange/dark)
  - Click no CTA dispara confirm flow (NÃO fire-and-forget — humano-in-loop)

### Tab `ia` (chat single-thread)

- **Header simples** — só Jana avatar + nome + tenant chip
- **Thread fluida** — single conversation persistente por business · sem lista lateral de conversas (dif vs `/jana/` que tem multi-conv)
- **4 kinds de bubble assistant** (`m.kind` switch):
  - `markdown` — `react-markdown` + `rehype-sanitize` · suporta listas/links/code/bold/italic
  - `tool_use` — chip sky `bg-sky-50 text-sky-700` mostrando ferramenta acionada (ex: "Consultou /sells-list-json")
  - `data_table` — tabela inline read-only (max 5 rows + "ver mais") com columns/rows tipados
  - `action_card` — bg amber se `confirm_required: true` · 2 botões Confirmar/Cancelar · bg emerald se `done` · bg rose se `error`
- **Bubble user** — single kind `text` · simétrico `rounded-md` (sem tail) · `bg-primary/5`
- **Citations inline** — `[1]` `[2]` numerados clicáveis dentro do markdown · expand pra source card (label + href interno)
- **Streaming token-a-token** — POST `/jana/conversas/{id}/mensagens/stream` (já existe em `ChatController:276`) · resposta vem via Centrifugo `jana:thread:{thread_id}` em chunks `delta: "token"` até `final: true` · pause auto-scroll quando user rola pra cima
- **Typing indicator** — chip `animate-pulse` "Jana está pensando" · aparece só durante stream · some quando primeiro token chega · NÃO 3-dots-loop-infinito
- **Empty state** — quando `msgs.length === 0`:
  - JanaAvatar 48×48 + h3 "Como posso ajudar hoje?"
  - Grid de 4 prompts iniciais ("📈 Vendas de hoje", "⏰ OS atrasadas", "💰 Inadimplentes", "📊 Financeiro mensal")
  - Atalhos visíveis `⌘K` busca · `/` foca composer
- **Sugestões persistentes** — quando `msgs.length > 0`, chips menores abaixo do composer (4-6 sugestões contextuais)
- **Composer**:
  - Placeholder `Pergunte algo à Jana sobre vendas, OS, financeiro...`
  - Textarea auto-resize (max 160px) · Enter envia · Shift+Enter nova linha · ⌘+Enter alternativa
  - **PII detector** — regex CPF/CNPJ/cartão · onChange testa · chip amber abaixo `⚠️ Conteúdo sensível detectado — Jana redige sem PII no audit log` · NÃO bloqueia envio (audit log lado server tem `PiiRedactor`)
  - Sem botões anexo/emoji/mencionar (charter `Chat.charter.md` Non-Goal)

### Atalhos teclado globais (filtra `<input>`/`<textarea>`/`contentEditable` focus)

- `/` (slash) → foca textarea composer
- `J` → próxima mensagem (scroll + highlight)
- `K` → mensagem anterior
- `Esc` → desfoca composer
- `⌘K` / `Ctrl+K` → foca search histórico
- `⌘Enter` / `Ctrl+Enter` → envia (alternativa)

### Multi-tenant Tier 0 (ADR 0093 IRREVOGÁVEL)

- `JanaThread` + `JanaMessage` com `business_id` global scope (Eloquent)
- Centrifugo channel segregado por `business_id` (`jana:thread:{biz}:{thread_id}`)
- Mock data por business (`getJanaData(company)` linha 7) — em prod plugar service real respeitando `business_id`

---

## Non-Goals — Features (NÃO faz nesta tela)

> Anti-alucinação. Cada item vira Pest GUARD test (Non-Goal violado = CI quebra).

- ❌ **Multi-conversação / lista de threads à esquerda** — esse é o paradigma do `/jana/` (`Chat.tsx`). Cockpit é single-thread.
- ❌ **Multi-channel (WhatsApp/Email/Instagram/etc)** — esse é `/atendimento/caixa-unificada` (Caixa Unificada V4). Jana = single channel IA.
- ❌ **Atendimento humano · operador / fila / SLA / assignee** — pertence a Caixa Unificada V4. Cockpit não tem fila nem assignee.
- ❌ **Voice input (microfone/whisper)** — backlog M2 (charter Chat.charter.md já marcou)
- ❌ **File attachment no composer** — backlog M2 (depende Brain B vision policy)
- ❌ **Read receipts ✓/✓✓** — IA não "lê" mensagem
- ❌ **Botão "Ligar" telefone** — IA não atende telefone
- ❌ **Online dot no avatar** — IA não fica offline
- ❌ **Tabs `Todos/OS/Equipe/Clientes`** (anti-pattern Cockpit.tsx atual) — não há lista de conversas pra filtrar
- ❌ **Read receipts ou notas internas 📌** — Cockpit é dialog Wagner-Jana, não atendimento
- ❌ **Modal "Compartilhar thread externa"** — risco PII alto (Non-Goal Chat.charter.md)
- ❌ **Comparar respostas multi-modelo lado-a-lado** — não é playground (Non-Goal Chat.charter.md)
- ❌ **`dangerouslySetInnerHTML` em qualquer markdown render** — `react-markdown` + `rehype-sanitize` ou nada
- ❌ **Mock `setTimeout(reply, 2400)` simulando resposta humana** — anti-pattern A5 do amendment · streaming SSE real ou mock-stream.js explícito

---

## UX Targets

- **Cabe em 1280px** sem scroll horizontal (ROTA LIVRE / Larissa)
- **First-paint:** p95 < 1500ms em Hostinger frio (`Inertia::defer` em props caras — KPIs e Análises são closures defer)
- **Switch tab Dashboard ↔ Analista IA:** p95 < 100ms (state local, sem network)
- **Streaming primeiro token:** p95 < 800ms (Brain B Sonnet via gateway interno)
- **0 erros JS console** com config válida
- **Atalho `/` foca composer** funcional

---

## UX Anti-patterns (catalogados — fechar antes de F3)

> Violações detectadas no `chat-jana.jsx` (export 2026-05-15). Refator Cowork V2.1 obrigatório antes de F3.

- ❌ **Avatar gradient + emoji 🤖** — `linear-gradient(135deg, #8a6cf5, #5b3ec8)` em [chat-jana.css:44-49](../../../prototipo-ui/_cowork-export-2026-05-15/chat-jana.css). Substituir por `JanaAvatar` mono `bg-primary text-primary-foreground` letra "J".
- ❌ **Bubbles com tail asimétrico** — `border-bottom-right-radius:4px` (user) + `border-bottom-left-radius:4px` (jana) em [chat-jana.css:498,505](../../../prototipo-ui/_cowork-export-2026-05-15/chat-jana.css). Substituir por simétrico.
- ❌ **Streaming ausente** — `onSend()` linha 395 só ecoa user msg. Implementar `mock-stream.js` SSE fake com chunks `delta`/`final`.
- ❌ **Atalhos globais ausentes** — sem `useEffect` keydown. Implementar listener `/` `J` `K` `Esc`.
- ❌ **Apenas 1 kind bubble** — só `list-card` em [chat-jana.jsx:412](../../../prototipo-ui/_cowork-export-2026-05-15/chat-jana.jsx). Implementar switch 4 kinds.
- ❌ **Citations ausentes** — sem schema `sources`. Adicionar.
- ❌ **PII detector ausente** — composer sem regex. Adicionar.
- ❌ **Markdown render frágil** — regex custom `**bold**` split (linha 156-163) sem sanitizer. Trocar por `react-markdown` + `rehype-sanitize` (já no projeto via `Chat.tsx`).

8 itens P0 → score F1.5 ≥80 quando 6+ fechados.

---

## Automation Hooks

- **Backend**: `ChatController::sendStream($id)` em [Modules/Jana/Http/Controllers/ChatController.php:276](../../../Modules/Jana/Http/Controllers/ChatController.php) já existe — reusar.
- **Centrifugo subscribe**: `jana:thread:{business_id}:{thread_id}` no `useEffect` da tab IA · cleanup no unmount + pausa visibilityState.
- **Token Centrifugo**: backend emite via `CentrifugoTokenIssuer::issue` em cada `Inertia::render` (mesmo padrão `/atendimento/caixa-unificada`).
- **HITL action confirm**: `<ActionCardBubble onConfirm>` → POST `/jana/sugestoes/{id}/escolher` · `onCancel` → POST `/jana/sugestoes/{id}/rejeitar` (rotas já existem em `Modules/Jana/Http/routes.php:40-41`).
- **Brief diário fetch**: tab `dashboard` carrega via `Inertia::defer(fn () => $this->buildBriefPayload($businessId))` — primeiro paint do shell <500ms, brief completa async ~1-2s.

---

## Automation Anti-hooks

- ❌ **Não chama Brain B direto no `Inertia::render`** — backend orquestra via job assíncrono `ProcessJanaMessage` (constructor recebe `$businessId` explicito · session() não funciona em fila).
- ❌ **Não emite token Centrifugo no frontend** — backend emite (consistência com Caixa Unificada V4).
- ❌ **Não persiste filtros em DB** — query string + `localStorage` per-user per-browser (sem leakage cross-tenant).
- ❌ **Não usa `withoutGlobalScopes` em `JanaThread`/`JanaMessage`** sem comentário `// SUPERADMIN: <razão>`.
- ❌ **Não loga PII em `mcp_audit_log`** — `PiiRedactor` aplicado server-side antes do INSERT.

---

## Métricas vivas (Pest GUARD)

| Status | Test | Arquivo |
|---|---|---|
| 🟡 spec | `R-JANA-COCKPIT-001 — happy path render dashboard tab + KPIs/análises/ações` | `Modules/Jana/Tests/Feature/CockpitControllerTest.php` (criar em F2) |
| 🟡 spec | `R-JANA-COCKPIT-002 — cross-tenant biz=99 invisível pra biz=1 (Tier 0)` | mesmo arquivo |
| 🟡 spec | `R-JANA-COCKPIT-003 — switch tab IA mantém thread persistente por business` | mesmo arquivo |
| 🟡 spec | `R-JANA-COCKPIT-004 — Non-Goal "multi-conversação" — assert layout 1-col, sem ConvList` | mesmo arquivo |
| 🟡 spec | `R-JANA-COCKPIT-005 — Non-Goal "atendimento humano" — assert sem fila/assignee/SLA` | mesmo arquivo |
| 🟡 spec | `R-JANA-COCKPIT-006 — PII redacted no audit log mesmo se composer envia CPF` | mesmo arquivo |
| 🟡 spec | `R-JANA-COCKPIT-007 — markdown render usa rehype-sanitize (XSS guard)` | Vitest component test |

---

## Roadmap — fases até live

### F1.5 (atual) — Cowork V2.1 com 8 refinos · gate ≥80

Lista canônica: ver §UX Anti-patterns acima. Estimativa Cowork: ~3-4h. CRITIQUE vivo em [`_cowork-export-2026-05-15/CRITIQUE-chat-jana-vs-amendment.md`](../../../prototipo-ui/_cowork-export-2026-05-15/CRITIQUE-chat-jana-vs-amendment.md).

### F2 — Backend baseline · Pest fixtures

- Criar `JanaThread`, `JanaMessage` migrations com `business_id` global scope
- `ProcessJanaMessage` job → `BrainBClient::stream`
- Tabela `jana_audit_log` retenção 90d
- Pest GUARDs §Métricas vivas (7 testes mínimos)

### F3 — Frontend Cockpit.tsx (substitui in-place)

- AppShellV2 + 2-tab topbar (Dashboard / Analista IA)
- Sub-componentes em `resources/js/Pages/Jana/_components/Cockpit/` (BriefDiario · KPICard · AnaliseCard · AcaoRow · MarkdownBubble · ToolUseChip · DataTableBubble · ActionCardBubble · JanaAvatar · TypingIndicator)
- `chat-jana.jsx` refinado vira fonte de tradução (preserva Cockpit V2 tokens · descarta `<script type="text/babel">` · usa TypeScript + Tailwind 4)
- `Inertia::defer` em props brief/kpis/analises/acoes (Tier 0 desde 2026-05-15)
- Substitui `Cockpit.tsx` atual em-place (138 lin → ~250-400 lin)

### F3.5 — A11y review (WCAG 2.1 AA)

- Contraste OK pra `bg-violet` user bubble (verificar #7c5cd9 vs #fff)
- Focus management nos atalhos `/` `J/K`
- ARIA labels em SVG (sparkline, donut)

### F4 — Cutover + canary 7d

- Coexiste com `Cockpit.tsx` antigo durante 0d (substituição in-place — sem rollback fácil)
- Sidebar Jana entry pode renomear "Cockpit" → "Analista" (consistente com screenshot Wagner 2026-05-15: `Jana · Analista`)
- Métricas: tempo médio sessão dashboard tab vs IA tab · taxa de uso PII detector · taxa de confirm em action_card

### F5 — Folding `Dashboard.tsx` como tab

- Pós-canary aprovado: `Dashboard.tsx` (`/jana/dashboard`) vira tab `dashboard` do `Cockpit.tsx`
- Charter `Dashboard.charter.md` (criar se não existir) → `status: historical`
- Redirect `/jana/dashboard` → `/jana/cockpit?tab=dashboard` (301)
- Remover `Pages/Jana/Dashboard.tsx` em PR seguinte (~1h)

---

## Histórico

| Data | Autor | Mudança |
|---|---|---|
| 2026-05-15 | Wagner [W] + Claude Code [CL] | Charter inicial. Criado pós Cowork export 2026-05-15 + decisão Wagner caminho A (pivot aceito). Status `spec-ahead-of-impl` — `Cockpit.tsx` atual (138 lin MVP-piloto WhatsApp anti-pattern) será substituído em-place quando Cowork V2.1 entregar 8 refinos + screenshot Wagner. Fonte canônica visual: `_cowork-export-2026-05-15/chat-jana.{jsx,css}` (78/100 interim). Zero overlap arquitetural com `/atendimento/caixa-unificada` (Caixa Unificada V4) e diferenciação clara vs `/jana/` (Chat.tsx 2-col conversacional). |
