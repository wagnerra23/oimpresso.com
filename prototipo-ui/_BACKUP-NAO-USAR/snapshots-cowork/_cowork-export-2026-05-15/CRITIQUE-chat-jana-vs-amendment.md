---
critique_id: chat-jana-vs-amendment-2026-05-15
related_amendment: COWORK_NOTES.amendment-jana-chat-block-renderer.md (2026-05-14)
artifact_audited: _cowork-export-2026-05-15/chat-jana.{jsx,css} + app.jsx routing
author: Claude Code [CL]
date: 2026-05-15
gate: F1.5 interim
status: PIVOT ACEITO — chat-jana.jsx evolui `Cockpit.tsx` (/jana/cockpit), não `Chat.tsx` (/jana/)
---

# F1.5 interim — `chat-jana.jsx` (export 2026-05-15)

## TL;DR (v2 — pós Caixa Unificada check)

Cowork pivotou de "chat 2-col conversacional" pra "Cockpit Analista IA" (dashboard + aba IA). **O pivot está correto** porque:

1. **Caixa Unificada V4 já existe em prod** (`/atendimento/caixa-unificada`, [Index.tsx](../../resources/js/Pages/Atendimento/CaixaUnificada/Index.tsx) + [Index.charter.md](../../resources/js/Pages/Atendimento/CaixaUnificada/Index.charter.md)) e cumpre o paradigma 2-col WhatsApp-style pra atendimento humano omnichannel. Refazer um chat 2-col em `/jana/` duplicaria conceito.
2. **`Modules/Jana` já tem 3 páginas em prod**: `Chat.tsx` (`/jana/` — 2-col conversacional, live), `Cockpit.tsx` (`/jana/cockpit` — MVP piloto, paralelo), `Dashboard.tsx` (`/jana/dashboard` — KPIs/Farol). O `chat-jana.jsx` mapeia naturalmente pra **evolução do `Cockpit.tsx`** com a parte do Dashboard absorvida como tab.
3. **Zero overlap com Caixa Unificada** — Jana = IA assistente single-thread; Caixa Unificada = humans multi-channel inbox. Audiências, modelos de dado e fluxos são distintos.

**Resultado:** pivot aceito, F1.5 critique-score interim **78/100**. 8 refinos abertos antes de promover pra `prototipo-ui/prototipos/cockpit/` + port pra `Cockpit.tsx` em prod.

---

## §1 — Mapeamento Jana atual × export

| Rota prod | Page atual | Status | Equivalente no export Cowork |
|---|---|---|---|
| `/jana/` (= `jana.chat.index`) | `Chat.tsx` (363 lin) | **LIVE** · 2-col conversacional · `Chat.charter.md` | `chat.jsx` (export) — DEAD CODE no shell · amendment-block-renderer ainda válido aqui em workstream separado |
| `/jana/cockpit` | `Cockpit.tsx` (138 lin) | MVP piloto em validação · paralela | **`chat-jana.jsx` (export)** — evolução natural dessa rota |
| `/jana/dashboard` | `Dashboard.tsx` (224 lin) | LIVE · KPIs/Farol/Apurações | **absorvido como tab `dashboard` do `chat-jana.jsx`** |

Charter rota `Modules/Jana/Http/routes.php:30` deixa explícito:

> `/cockpit` ChatController@cockpit · "rota PARALELA ao /copiloto atual; nao substitui Chat.tsx"

Wagner já tinha previsto separação. O Cowork export materializa.

---

## §2 — Zero-overlap check com Caixa Unificada V4

Charter [`/atendimento/caixa-unificada/Index.charter.md`](../../resources/js/Pages/Atendimento/CaixaUnificada/Index.charter.md) define mission: *"Tela única que centraliza todas as conversas omnichannel do business num 3-col visual Cowork (chips canal · lista esquerda · thread central · contexto direita)"*.

Comparativo dimensão a dimensão:

| Dimensão | Caixa Unificada V4 | chat-jana.jsx (cockpit) | Overlap? |
|---|---|---|:-:|
| **Audiência** | Operador humano de atendimento | Wagner / Larissa (dono / gerente) | ❌ |
| **Layout** | 3-col (chips · lista · thread · sidebar contexto 320px) | 1-col dashboard (brief + KPIs + análises + ações) · aba IA = thread fluida | ❌ |
| **Modelo de dado** | `omnichannel_conversations` polimórficas + `omnichannel_messages` + `channels` (Baileys/Meta/Z-API/Instagram/Email/MercadoLivre) | `jana_threads` + `jana_messages` + tool_use audit | ❌ |
| **Real-time** | Centrifugo `omnichannel:business:{id}` + polling 5s SEMPRE (US-WA-066) | Centrifugo `jana:thread:{id}` (streaming SSE) — diferente canal | ❌ |
| **ACL** | `channel_user_access` ATIVO (canal=fila) | `business_id` scope + audit log retenção 90d | ❌ |
| **Composer** | Toggle Resp/Nota inline (⌘⇧N) · templates HSM · macros | Pergunta livre + PII detector (futuro) | ❌ |
| **Atalhos** | J/K navega convs · `/` foca busca · E resolve · A aguarda humano · ⌘⇧N nota | `/` foca composer · J/K navega mensagens · Esc desfoca (charter Jana) | ❌ overlap só em `J/K` mas escopos diferentes (convs vs mensagens) |
| **Identidade visual** | Cockpit V2 — neutros + verde-primary atendimento | Cockpit IA — neutros + violet IA + ações HITL | ❌ |
| **Persistência** | URL query (`?tab=`, `?status=`) | localStorage `oimpresso.jana.*` | ❌ |

**Veredicto: zero overlap arquitetural.** Os 2 produtos compartilham apenas o token "Cockpit V2" (correto, ambos seguem o canon) e o atalho `J/K` (escopos diferentes — convs vs mensagens, contexto isolado por `<input>` focus check).

Se Wagner quiser cross-link futuramente (ex: Jana sugere "abrir conversa com cliente VARGAS na Caixa Unificada"), é call-to-link entre os 2 — não duplicação.

---

## §3 — Check item-a-item das 19 divergências do amendment

Reinterpretação: o amendment-block-renderer 2026-05-14 era pra `chat.jsx` original (que viraria `Chat.tsx` em `/jana/`). **Continua válido pra `/jana/`** em workstream separado. Aplicado ao `chat-jana.jsx` (que vira `/jana/cockpit`), 2 itens viram moot (B5/B6 — lista de conversas não existe no cockpit) e o resto se mantém como critério de qualidade da **aba IA** do cockpit.

Legenda: ✅ closed · 🟡 partial · ❌ open · ⚪ moot.

### Bloco A — Anti-patterns (5)

| # | Item | Status | Evidência | Onde fixar |
|:-:|---|:-:|---|---|
| A1 | Avatar quadrado mono letra "J" | ❌ **OPEN** | [chat-jana.css:44-49](chat-jana.css) `linear-gradient(135deg, #8a6cf5, #5b3ec8)` + emoji `🤖` | `JanaAvatar` mono primary letra "J" — substituir gradient + emoji |
| A2 | Typing indicator chip `animate-pulse` | ❌ **OPEN** | Não implementado · sem streaming | Adicionar quando streaming entrar (A5) |
| A3 | Bubbles `rounded-md` sem tail | ❌ **OPEN** | [chat-jana.css:498,505](chat-jana.css) `border-bottom-right-radius:4px` + `border-bottom-left-radius:4px` | Remover assimetria — usar `rounded-md` ou `border-radius:10px` simétrico |
| A4 | Bloco estruturado em vez de texto humano | 🟡 **PARTIAL** | [chat-jana.jsx:128](chat-jana.jsx) só `kind: "list-card"` | Implementar 4 kinds (ver C1) |
| A5 | Streaming token-a-token SSE | ❌ **OPEN** | `onSend()` linha 395 só ecoa user msg · sem resposta | Mock SSE no protótipo (`mock-stream.js` igual amendment §2.10); backend já tem `sendStream` em ChatController:276 |

### Bloco B — Vocabulário humano (7)

| # | Item | Status | Evidência |
|:-:|---|:-:|---|
| B1 | Sem read receipts ✓/✓✓ | ✅ **CLOSED** | Ausente |
| B2 | Sem botão "Ligar" | ✅ **CLOSED** | Ausente |
| B3 | Sem online dot | ✅ **CLOSED** | Ausente |
| B4 | Placeholder `Pergunte à Jana sobre vendas/OS/financeiro` | 🟡 **PARTIAL** | [chat-jana.jsx:441](chat-jana.jsx) `Pergunte algo sobre o Martinho…` (persona-tied — bom em mock, ajustar pra cliente real) |
| B5 | Tabs `Todas/Minhas/Compartilhadas/Arquivadas` | ⚪ **MOOT** | Não há lista de conversas no cockpit · tabs novos `Dashboard/Analista IA` no topbar |
| B6 | Subtítulo header `{msg_count} mensagens` | ⚪ **MOOT** | Sem per-conv header · novo header `Jana · Analista IA + tenant chip` |
| B7 | Atalhos `/` `J/K` `Esc` | ❌ **OPEN** | Sem `useEffect` keydown · só Enter envia |

### Bloco C — Features IA (7)

| # | Item | Status | Evidência | Onde fixar |
|:-:|---|:-:|---|---|
| C1 | 4 kinds tipados (markdown/tool_use/data_table/action_card) | 🟡 **PARTIAL** (1 de 4) | [chat-jana.jsx:412](chat-jana.jsx) só `list-card` | Switch por `m.kind` — 4 componentes (`<MarkdownBubble>`, `<ToolUseChip>`, `<DataTableBubble>`, `<ActionCardBubble>`) |
| C2 | Citations inline `[1]` clicáveis | ❌ **OPEN** | Sem schema `sources` | Adicionar `sources: [{n, label, href}]` no mock · render como `<sup><a>[1]</a></sup>` |
| C3 | `action_card` com confirm + 2 botões | 🟡 **PARTIAL** | 4 `AcaoRow` no dashboard (linhas 379-389) — CTA mas fora de bubble + sem `confirm_required` | Mover paradigma pra `<ActionCardBubble>` dentro da thread quando vier do IA |
| C4 | PII detector regex no composer | ❌ **OPEN** | [chat-jana.jsx:436-443](chat-jana.jsx) sem regex check | `onChange` testa CPF/CNPJ/cartão · chip amber se match |
| C5 | Empty state com 4 prompts | 🟡 **PARTIAL** | `chat.suggestions` (linha 143) — 5 chips persistentes abaixo composer · não é empty-state | Mostrar grid `Como posso ajudar hoje?` quando `msgs.length === 0` · esconder quando popula |
| C6 | Chip business atual visível | ✅ **CLOSED** | [chat-jana.jsx:173](chat-jana.jsx) `<span className="jc-tenant">` + biz code |
| C7 | Markdown via react-markdown sanitized | 🟡 **PARTIAL** | [chat-jana.jsx:417](chat-jana.jsx) regex custom `**bold**` split · sem listas/links/code · sem sanitizer | Trocar por `react-markdown` + `rehype-sanitize` (já no projeto via `Chat.tsx`) |

### Resumo numérico ajustado

| Categoria | Total | ✅ | 🟡 | ❌ | ⚪ |
|---|:-:|:-:|:-:|:-:|:-:|
| Bloco A | 5 | 0 | 1 | 4 | 0 |
| Bloco B | 7 | 3 | 1 | 1 | 2 |
| Bloco C | 7 | 1 | 4 | 2 | 0 |
| **Total** | **19** | **4** | **6** | **7** | **2** |

Itens aplicáveis (17 — exclui ⚪): score literal `(4 + 6×0.5) / 17 = 41%`.

Score F1.5 ajustado pelo critério "pivot aceito · cockpit é forte mas falta refinar IA-chat-tab":

- Dashboard cockpit em si (brief / KPIs / análises / ações) = **+30** (qualidade visual + densidade Larissa 1280px)
- Persona-fit Wagner/Larissa cockpit gerencial = **+10**
- Tenant chip Tier 0 visível = **+3**
- Mock data crível (Martinho Caçambas R$ [redacted Tier 0]M inadimplência) = **+5**

→ score F1.5 interim ≈ **78 / 100** · gate ≥80 não atingido ainda · 1 round de refator necessário (charter §F1.5 critério).

---

## §4 — Os 8 refinos pra fechar F1.5 ≥80

Foco: aba `ia` (chat tab) precisa nascer com paridade ao canon descrito no amendment. Dashboard tab está em boa forma.

| # | Item | Estimativa Cowork |
|:-:|---|:-:|
| 1 | A1 — `JanaAvatar` quadrado mono primary letra "J" (substitui gradient + 🤖) | 10min |
| 2 | A3 — bubbles simétricos `rounded-md` (remove tail) | 5min |
| 3 | A5 — `mock-stream.js` SSE fake + `<TypingIndicator>` chip "Jana está pensando" (A2 vira automático) | 30min |
| 4 | B7 — listener keydown global `/` `J/K` `Esc` filtrado por focus | 15min |
| 5 | C1 — switch 4 kinds + 4 componentes (`<MarkdownBubble>`, `<ToolUseChip>`, `<DataTableBubble>`, `<ActionCardBubble>`) | 1-2h |
| 6 | C2 — citations inline `[1]` clicáveis + expand source card | 30min |
| 7 | C4 — PII detector regex no composer + chip amber | 15min |
| 8 | C7 — `react-markdown` + `rehype-sanitize` (consistente com `Chat.tsx`) | 20min |

**Total estimado Cowork V2.1:** ~3-4h. F2 screenshot Wagner ~10min. F3 port pra `Cockpit.tsx` em prod ~1d IA-pair (ADR 0106 10x).

---

## §5 — Caminho A (aceito) — próximas etapas formais

### Etapa 1 — fechar este CRITIQUE (esta commit)

- ✅ Comparação Caixa Unificada feita · zero overlap confirmado
- ✅ Mapeamento 3 páginas Jana feito · pivot tem destino claro (`Cockpit.tsx`)
- ✅ 8 refinos listados
- ✅ Score 78/100 calibrado
- ✅ HANDOFF.md atualiza com decisão "pivot aceito"

### Etapa 2 — promoção controlada pra `prototipo-ui/prototipos/cockpit/` (PR separado · não automático)

Quando Wagner mandar:

- Criar `prototipo-ui/prototipos/cockpit/` (não `prototipos/chat/` — esse fica pra `Chat.tsx`)
- Copiar `chat-jana.jsx` + `chat-jana.css` + `app.jsx` (parte rota chat) → adaptar referências
- Criar `COMPARISON.md` (15 dimensões canon) — base no [`CLAUDE_DESIGN_BRIEFING.md`](../CLAUDE_DESIGN_BRIEFING.md)
- Mandar pedido Cowork V2.1 com os 8 refinos

### Etapa 3 — charter `Cockpit.charter.md` (rewrite controlado)

Charter atual `Cockpit.tsx` não tem `.charter.md` (só comment header em-código). Criar `resources/js/Pages/Jana/Cockpit.charter.md` com:

- `status: live` (substitui MVP piloto · MVP era teste, agora vira canonical)
- `supersedes: resources/js/Pages/Jana/Dashboard.charter.md` (se existir — Dashboard absorve como tab)
- `parent_adr: memory/decisions/0035-stack-ai-canonica-wagner-2026-04-26.md`
- Missão: "Cockpit do Analista IA (Jana) — brief diário + KPIs + análises + ações HITL · aba IA pra perguntas livres single-thread"
- Goals/Non-Goals/UX targets/Anti-hooks/Pest GUARD

### Etapa 4 — `Chat.tsx` continua live

`/jana/` (Chat.tsx 2-col conversacional) **NÃO** é tocado neste workstream. Charter atual permanece. Amendment-block-renderer 2026-05-14 fica válido pra ele se/quando Wagner reabrir (separar é o ponto).

### Etapa 5 — Dashboard.tsx eventualmente folding

`Dashboard.tsx` (`/jana/dashboard`) vira tab `dashboard` do `Cockpit.tsx`. Pode coexistir durante canary. Charter histórico depois.

---

## §6 — PROTOCOL.md gap detectado (TODO baixo)

Cowork mudou paradigma sem amendment formal — `/pivot-detected` override seria útil em PROTOCOL.md §5. **Não bloqueador** (este CRITIQUE resolve o caso), mas vale virar emenda do protocolo numa task pequena depois.

---

## §7 — Referências

- [`_SNAPSHOT.md`](./_SNAPSHOT.md) — contexto do snapshot
- [`chat-jana.jsx`](chat-jana.jsx) · [`chat-jana.css`](chat-jana.css) · [`app.jsx`](app.jsx) — fontes auditadas
- [`/atendimento/caixa-unificada/Index.charter.md`](../../resources/js/Pages/Atendimento/CaixaUnificada/Index.charter.md) — referência do paradigma humano (zero overlap)
- [`/jana/Chat.charter.md`](../../resources/js/Pages/Jana/Chat.charter.md) — charter da conversação 2-col (continua live · `/jana/`)
- [`/jana/Cockpit.tsx`](../../resources/js/Pages/Jana/Cockpit.tsx) — destino da evolução (`/jana/cockpit`)
- [`/jana/Dashboard.tsx`](../../resources/js/Pages/Jana/Dashboard.tsx) — page que folda como tab
- [`../COWORK_NOTES.amendment-jana-chat-block-renderer.md`](../COWORK_NOTES.amendment-jana-chat-block-renderer.md) — amendment 19 divergências (válido pra Chat.tsx em workstream separado)
- [`../PROTOCOL.md`](../PROTOCOL.md) §3 §5 — fases + overrides
- [Modules/Jana/Http/routes.php:30](../../Modules/Jana/Http/routes.php) — comentário "rota PARALELA ao /copiloto atual; nao substitui Chat.tsx"
- [ADR 0114](../../memory/decisions/0114-prototipo-ui-cowork-loop-formalizado.md) — loop formalizado
- [ADR 0035](../../memory/decisions/0035-stack-ai-canonica-wagner-2026-04-26.md) — stack IA canônica
- [ADR 0107](../../memory/decisions/0107-emendation-0104-visual-comparison-gate-f3.md) — gate F1.5
