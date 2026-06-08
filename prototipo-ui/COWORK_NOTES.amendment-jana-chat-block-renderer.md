---
amendment_id: chat-block-renderer
related_request: "#316 Jana/Chat + amendment-jana-chat-avatar (2026-05-09)"
charter: resources/js/Pages/Jana/Chat.charter.md
author: Wagner [W] + Claude Code [CL]
date: 2026-05-14
severity: P0 — bloqueia entrada em F3
trigger: revisão pós-handoff `Oimpresso-handoff.zip` (Claude Design export 2026-05-14)
---

# [W → CC] Amendment ao pedido #316 — Jana/Chat block renderer + vocabulário IA (2026-05-14)

**Quem:** Wagner [W] + Claude Code [CL] (esta sessão), revisão crítica do `chat.jsx` exportado pelo Claude Design em 2026-05-14.

**Motivação:** O `chat.jsx` exportado implementa um **chat WhatsApp-style multi-purpose** (atendimento humano + OS + equipe + cliente) ao invés do **chat IA conversacional da Jana** descrito no charter. Atendimento humano omnichannel já vive em `Modules/Whatsapp/` (Z-API + Meta Cloud + Baileys + Inbox Cockpit + Macros + CSAT + Templates HSM + Channels ADR 0135) — **não é responsabilidade do `/jana/chat`**.

Nota da revisão (calibrado contra Glean Chat / ChatGPT Enterprise / Notion AI / Microsoft Copilot M365 — 2026): **24/100**.

P0 fechados: **0/6**. P1 fechados: **1/10**. Charter define 6 P0 IA empresarial — todos ausentes. Sem essa correção, F3 produz "GPT genérico embrulhado em UI WhatsApp" e perde a tese central da Jana (IA com tool registry + audit + business_id scope + citations confiáveis).

---

## §1 — Quadro de divergências (19 pontos)

### Bloco A — Anti-patterns explícitos do charter (P0)

| # | Atual `chat.jsx` | Charter (canon) | Severidade |
|:-:|---|---|:-:|
| A1 | Avatar circular gradient `av av-N` com sigla 2 letras | Quadrado `rounded-md` `bg-primary text-primary-foreground` letra "J" monocromática | P0 — já corrigido no amendment-avatar mas reaparece no `chat.jsx`, **reforçar** |
| A2 | Typing indicator: 3 dots animados em loop infinito | 1 chip `animate-pulse` "Jana está pensando" (sem loop infinito) | P0 |
| A3 | Bubbles com estilo WhatsApp implícito (cor por papel + tail visual) | `rounded-md` Cockpit V2, `bg-primary/5` (user) e `bg-card` (assistant), **sem tail** | P0 |
| A4 | Mock response humano: "Recebido, vou verificar e te respondo já já 👍" | Resposta Jana = bloco estruturado (`markdown` / `tool_use` / `data_table` / `action_card`), nunca texto humano genérico | P0 |
| A5 | `setTimeout` simula resposta em 2.4s | Streaming token-a-token via SSE/Centrifugo; primeiro token <800ms p95 | P0 |

### Bloco B — Vocabulário humano vazado (P0)

| # | Atual | Charter / IA empresarial | Severidade |
|:-:|---|---|:-:|
| B1 | Read receipts `✓` / `✓✓` na bubble do usuário | IA não "lê" mensagem — remover completamente | P0 |
| B2 | Botão `<I.phone>` "Ligar" no header da thread | IA não atende telefone — remover | P0 |
| B3 | Online dot no avatar (`c.online ? 'dot' : ''`) | IA não fica "offline" — remover | P0 |
| B4 | Placeholder composer: `Mensagem para ${conv.title}...` | Placeholder: `Pergunte algo à Jana sobre vendas, OS, financeiro...` | P1 |
| B5 | Tabs lista esquerda: `Todas / OS / Equipes / Clientes` | Tabs canon (charter §Goals): `Todas / Minhas / Compartilhadas / Arquivadas` | P0 |
| B6 | Subtítulo header: `Canal interno da equipe` / `${client} cliente` | Subtítulo: `${ultima_atividade}` ou `${msg_count} mensagens` (sem persona humana) | P1 |
| B7 | Atalhos: só `⌘K` (busca) | Charter manda: `⌘K` busca + `/` foca composer + `J/K` navega mensagens + `Esc` desfoca | P1 |

### Bloco C — Features IA ausentes (P0 — define o produto)

| # | Atual | Charter (canon) | Severidade |
|:-:|---|---|:-:|
| C1 | Bubble assistant = só texto livre | 4 tipos tipados por `kind`: `markdown` / `tool_use` / `data_table` / `action_card` | P0 |
| C2 | Sem citations | Citations inline numeradas `[1][2]` clicáveis → expand source card (link OS/venda/doc) | P0 |
| C3 | Sem confirm dialog | `action_card` com `confirm_required: true` → bubble amber + 2 botões "Confirmar" / "Cancelar" antes de destrutiva | P0 |
| C4 | Sem detecção PII no composer | Regex CPF (`/\d{3}\.?\d{3}\.?\d{3}-?\d{2}/`) + CNPJ + cartão → chip amber "Conteúdo sensível detectado" antes de enviar | P1 |
| C5 | Sem suggested prompts no empty state | Empty state "Selecione uma conversa" → 4 chips de prompts iniciais: "Quantas vendas hoje?", "Listar OS atrasadas", "Top 5 clientes em débito", "Resumo financeiro do mês" | P1 |
| C6 | Sem chip "business atual" visível | Header thread ou sidebar: chip pequeno `{business.short_name}` (LARISSA · biz=4) — afirma multi-tenant Tier 0 visualmente | P0 |
| C7 | Sem markdown render | `react-markdown` com sanitizer (XSS — anti-pattern charter explícito `dangerouslySetInnerHTML`) | P1 |

---

## §2 — Correção formal (substitui spec atual onde aplicável)

### §2.1 Estrutura de dados — `Message` schema

`chat.jsx` atualmente trata `m.t` (texto livre) + flags `m.note` / `m.file`. Substituir por **discriminated union** tipado:

```jsonc
// Mensagem do usuário
{ "role": "user", "kind": "text", "text": "...", "ts": "...", "id": "msg_..." }

// Mensagens do assistente (Jana) — 4 kinds canônicos
{ "role": "assistant", "kind": "markdown",    "markdown": "...", "sources": [{ "n": 1, "label": "OS #4521", "href": "/repair/4521" }], "ts": "..." }
{ "role": "assistant", "kind": "tool_use",    "tool": "sells.list", "params": { ... }, "status": "running|done|error", "ts": "..." }
{ "role": "assistant", "kind": "data_table",  "columns": [...], "rows": [...], "caption": "5 vendas atrasadas", "ts": "..." }
{ "role": "assistant", "kind": "action_card", "action": "cancelar_venda", "summary": "Cancelar venda #1234 (R$ 450,00)", "confirm_required": true, "result": null, "ts": "..." }
```

Renderer no `<Thread>` faz `switch(m.kind)` — 1 componente React por kind.

### §2.2 Avatar Jana (reforço amendment 2026-05-09)

Inalterado. Quadrado `rounded-md`, sigla "J" monocromática, `bg-primary text-primary-foreground`, 32×32px header + 28×28px lista. **Sem gradient. Sem círculo. Sem online dot.**

### §2.3 Typing indicator

Substituir bloco `<div className="typing"><span/><span/><span/></div>` (3 dots loop infinito) por:

```jsx
<div className="thinking">
  <span className="dot animate-pulse" />
  <span>Jana está pensando</span>
</div>
```

CSS: 1 dot pulse, fadeIn 200ms. **Aparece só durante stream; some quando primeiro token chega.** Não confundir com "ainda gerando" — aí streaming token-a-token já mostra progresso natural.

### §2.4 Tabs lista esquerda

```jsx
const TABS = [
  { id: 'todas',         label: 'Todas' },
  { id: 'minhas',        label: 'Minhas' },
  { id: 'compartilhadas', label: 'Compartilhadas' },
  { id: 'arquivadas',    label: 'Arquivadas' },
];
```

`tab === 'minhas'` filtra `c.owner_id === current_user.id`. `compartilhadas` filtra `c.shared_with_team === true`. `arquivadas` filtra `c.archived_at !== null`.

### §2.5 Composer

- Placeholder: `Pergunte algo à Jana sobre vendas, OS, financeiro...`
- **Manter**: textarea auto-resize, enter envia, shift+enter nova linha — já está bom no `chat.jsx`.
- **Adicionar**: PII detector (regex CPF/CNPJ/cartão) → quando match, mostra chip amber abaixo do textarea: `⚠️ Conteúdo sensível detectado — Jana redige sem PII no audit log`. Não bloqueia envio.
- **Remover**: botões "Anexo" / "Emoji" / "Mencionar" do toolbar (charter manda anexo backlog M2, emoji/menção não fazem sentido em IA single-channel). Manter só `hint` + `send-btn`.

### §2.6 Header da thread

- **Remover**: botão `<I.phone>` "Ligar"
- **Remover**: online dot
- **Manter**: avatar + título + subtítulo + botão `<I.info>` "Detalhes" + botão `<I.more>` "Mais"
- **Adicionar**: chip pequeno à direita: `{business.short_name}` ex.: `LARISSA · biz=4` (token Cockpit V2, font-mono 11px, `bg-zinc-100 text-zinc-700`)
- **Subtítulo**: `{msg_count} mensagens · última atividade {ts_relative}`

### §2.7 Barra `th-context` (atualmente mostra OS/cliente/etapa/entrega)

Essa barra **não deve aparecer fixa no header da Jana**. Contexto de OS/venda aparece **dentro do bubble** via bloco `tool_use` ou `data_table`. Remover `<div className="th-context">` inteiro.

### §2.8 Empty state

Substituir:

```jsx
<div className="empty">
  <div>
    <div className="ico"><I.chat size={22}/></div>
    <div>Selecione uma conversa para começar</div>
    <div>Pressione ⌘K para buscar</div>
  </div>
</div>
```

Por:

```jsx
<div className="empty">
  <div className="ico"><JanaAvatar size={48} /></div>
  <h3>Como posso ajudar hoje?</h3>
  <p>Pergunte sobre vendas, OS, financeiro ou peça uma ação.</p>
  <div className="prompts-grid">
    <button onClick={() => sendPrompt("Quantas vendas tive hoje?")}>📈 Vendas de hoje</button>
    <button onClick={() => sendPrompt("Listar OS atrasadas")}>⏰ OS atrasadas</button>
    <button onClick={() => sendPrompt("Top 5 clientes em débito")}>💰 Inadimplentes</button>
    <button onClick={() => sendPrompt("Resumo financeiro do mês")}>📊 Financeiro mensal</button>
  </div>
  <kbd>⌘K</kbd> busca histórico · <kbd>/</kbd> foca composer
</div>
```

### §2.9 Atalhos teclado

Adicionar listener global no `<Thread>`:

- `/` (slash) → foca textarea composer (se não estiver focado em outro input)
- `J` (sem modificador) → próxima mensagem (scroll + highlight)
- `K` (sem modificador) → mensagem anterior
- `Esc` → desfoca composer (não fecha modal)
- `⌘K` / `Ctrl+K` → foca search da lista esquerda (já existe)
- `⌘Enter` / `Ctrl+Enter` → envia (alternativa ao Enter sem shift)

### §2.10 Streaming token-a-token

`onSend(t)` → POST `/jana/threads/{id}/messages` → backend enfileira job `ProcessJanaMessage` → resposta vem via Centrifugo (canal `jana:thread:{thread_id}`) em chunks `delta: "token"` até `final: true`.

Frontend mantém buffer local `streamingMessage` que renderiza progressivamente no último bubble da thread. Quando `final: true`, descarta buffer e re-renderiza do server-state (consistente).

**Pause auto-scroll**: detectar se `scrollRef.current.scrollTop < scrollRef.current.scrollHeight - 200` → user rolou pra cima → pausar `scrollTop = scrollHeight`. Quando user volta pro bottom (`scrollTop ≈ scrollHeight`), retoma auto-scroll.

---

## §3 — Itens que ficam INALTERADOS (já estão bons)

| Item | Status |
|---|---|
| Layout 2-col (lista esquerda ~280px + thread fluido) | ✅ canon Cockpit V2 |
| Lista de conversas com pinned + recents | ✅ |
| Search `⌘K` na lista | ✅ |
| Day separator | ✅ |
| Agrupamento de mensagens consecutivas por autor | ✅ |
| Scroll auto-bottom on new msg | ✅ (adicionar pause mid-stream) |
| Textarea auto-resize até 160px | ✅ |
| Enter envia / Shift+Enter nova linha | ✅ |
| PT-BR em todo texto UI | ✅ |
| Estrutura `<section className="thread">` + header + msgs + composer | ✅ |

**Não rebobinar o shell.** Aproveitar a fundação física e trocar conteúdo + vocabulário + adicionar blocos IA.

---

## §4 — Itens removidos do escopo F1 (vão pra backlog ou jamais)

| Item | Destino |
|---|---|
| File attachment no composer | **Backlog M2** — charter (depende Brain B vision policy) |
| Voice input mic | **Backlog M2** — charter explicito |
| Mockup response humano `setTimeout` | **Substituir** por mock SSE stub (chunks fake) |
| Read receipts `✓✓` | **Remover permanente** — anti-feature IA |
| Botão ligar telefone | **Remover permanente** |
| Online dot no avatar | **Remover permanente** |
| Notas internas 📌 do operador | **Mover pra Whatsapp Inbox** (faz sentido lá) |
| Tabs OS/Equipes/Clientes na lista | **Substituir** pelas 4 canon (Todas/Minhas/Compartilhadas/Arquivadas) |
| Modal "Compartilhar thread externa" | **Jamais** — charter Non-Goal (risco PII alto) |
| Comparar respostas multi-modelo lado-a-lado | **Jamais** — charter Non-Goal (não é playground) |

---

## §5 — Próxima ação por papel

### [CC] Claude Cowork (próximo turno)

Consome **pedido #316 + amendment-avatar (2026-05-09) + ESTE amendment como trio**, gera **V2 do protótipo** em `prototipo-ui/prototipos/chat/`:

- `cowork-app.jsx` reescrito sem os anti-patterns Bloco A/B
- `chat-renderer.jsx` novo arquivo com 4 componentes:
  - `<MarkdownBubble />` (react-markdown sanitized + citations inline)
  - `<ToolUseChip />` (sky `bg-sky-50 text-sky-700` + ícone + label tool)
  - `<DataTableBubble />` (KpiCard-like inline, max 5 rows + "ver mais")
  - `<ActionCardBubble />` (emerald se done, amber se confirm_required, rose se error + botões)
- `mock-stream.js` substitui `setTimeout` por fake SSE com chunks `delta`/`final`
- Avatar JanaAvatar reusável (já corrigido no amendment 2026-05-09)
- `keymap.js` listener global atalhos `/` `J` `K` `Esc`
- Empty state com 4 prompts iniciais
- PII detector regex (CPF/CNPJ/cartão) no composer

### [CD] Claude Design (F1.5 critique)

Score perde **≥15 pontos** se reaparecer **qualquer item Bloco A/B**. Score perde **≥10 pontos por kind IA ausente** (esperado todos 4: markdown / tool_use / data_table / action_card).

### [W2] Wagner (F2 screenshot)

Aprovação síncrona — checklist visual:
- [ ] Avatar Jana quadrado monocromático letra "J"
- [ ] Bubble assistant rendera 4 kinds diferentes
- [ ] Citations inline `[1]` visíveis e clicáveis
- [ ] Action card com confirm_required mostra 2 botões
- [ ] Empty state com 4 prompt chips
- [ ] Composer detecta CPF colado → chip amber
- [ ] Atalho `/` foca composer
- [ ] Cabe em 1280px sem scroll horizontal

### [CL] Claude Code (F3 — depois de F2 aprovado)

Implementa em `resources/js/Pages/Jana/Chat.tsx`:
- AppShellV2 + 2-col layout
- Sub-componentes em `resources/js/Pages/Jana/_components/`
- Backend `JanaController::index() + send()` + `ProcessJanaMessage` job + `BrainBClient` stream
- Models `JanaThread`, `JanaMessage` com `business_id` global scope
- Tabela `jana_audit_log` retenção 90d
- Pest GUARD tests (já listados no charter §Métricas vivas)

---

## §6 — Critério de "Done F1.5" (CD aprova)

Protótipo V2 entrega **score ≥ 80 / 100** medindo:

| Categoria | Itens | Peso |
|---|---|:-:|
| **Anti-patterns Bloco A eliminados** (5 itens) | 0 violações | 25% |
| **Vocabulário Bloco B corrigido** (7 itens) | 0 violações | 20% |
| **4 kinds renderer Bloco C** | todos funcionando com mock | 30% |
| **Citations + PII + suggested prompts** | todos 3 visíveis | 15% |
| **Shell preservado §3** | regressão zero | 10% |

Abaixo de 80 → 1 round de refator (charter §F1.5 critério). Abaixo de 70 → discussão com [W].

---

## §7 — Referências

- [Chat charter vivo](../resources/js/Pages/Jana/Chat.charter.md) — fonte primária
- [Amendment avatar 2026-05-09](COWORK_NOTES.md) — antecedente
- [PROTOCOL.md](PROTOCOL.md) §4 — formato amendment
- [ADR 0114](../memory/decisions/0114-prototipo-ui-cowork-loop-formalizado.md) — loop Cowork ↔ CC
- [ADR 0094](../memory/decisions/0094-constituicao-v2-7-camadas-8-principios.md) §IA — audit + business_id scope
- [ADR 0107](../memory/decisions/0107-emendation-0104-visual-comparison-gate-f3.md) — gate F1.5
- [Modules/Whatsapp/SCOPE.md](../Modules/Whatsapp/SCOPE.md) — atendimento humano (separado de Jana)
- [Glean Chat — citation + streaming + multi-turn](https://www.glean.com/blog/glean-chat-launch-announcement)
- [Designing Agentic AI — Smashing 2026](https://www.smashingmagazine.com/2026/02/designing-agentic-ai-practical-ux-patterns/)

---

## §8 — Histórico

| Data | Autor | Mudança |
|---|---|---|
| 2026-05-14 | Wagner [W] + Claude Code [CL] | Amendment criado após revisão do export Claude Design `Oimpresso-handoff.zip`. Nota 24/100 calibrada vs Glean/ChatGPT Enterprise/Notion AI/Copilot 2026. 19 divergências catalogadas. V2 do protótipo bloqueia F3 — Chat.tsx só nasce após F1.5 ≥80. |
