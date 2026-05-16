---
critique_id: chat-jana-vs-amendment-2026-05-15
related_amendment: COWORK_NOTES.amendment-jana-chat-block-renderer.md (2026-05-14)
artifact_audited: _cowork-export-2026-05-15/chat-jana.{jsx,css} + app.jsx routing
author: Claude Code [CL]
date: 2026-05-15
gate: F1.5 interim — depende de decisão Wagner sobre PIVOT
status: BLOCKED — pivot arquitetural detectado, amendment original parcialmente moot
---

# F1.5 interim — `chat-jana.jsx` (export 2026-05-15) vs amendment 19 divergências P0

## TL;DR

**Cowork pivotou o produto.** O export 2026-05-15 não é "V2 do chat conversacional 2-col" — é **uma tela diferente** ("Cockpit do Analista IA" — dashboard de KPIs/análises/ações com chat embutido como tab `ia`). Routing em [`app.jsx:474`](app.jsx) confirma: `route === "chat"` → `<window.JanaCockpit/>` (novo), e `chat.jsx` (WhatsApp shell) virou dead code no shell.

Comparar item-a-item com o amendment é parcialmente moot: ~6 dos 19 itens deixam de existir porque a coluna esquerda de conversas sumiu. Os outros 13 itens dividem-se entre: **5 fechados, 4 ainda violados, 4 abertos não implementados**.

**Score F1.5 condicional:**

- Se Wagner aceitar o pivot (Jana = cockpit-com-aba-IA, modelo Glean Home / Copilot M365): score do **dashboard** ≈ **78/100** (forte, mas falta refinar A1+A3 e implementar 4 kinds no chat tab).
- Se Wagner rejeitar o pivot (Jana = chat conversacional 2-col, como o charter manda): score = **22/100** — Cowork entregou produto errado, amendment não foi atendido em essência.

**Recomendação:** Wagner decide pivot ANTES de F1.5 critique-score formal. Eu não posso decidir sozinho — é shift de produto, não detalhe visual.

---

## §1 — A pivot detectada

### Antes (amendment 2026-05-14)

```
┌─ topbar ─────────────────────────────────────────────┐
├─ [coluna esquerda]                                   │
│  ConvList ConvItem ConvItem                          │
│  ─ tabs: Todas/Minhas/Compartilhadas/Arquivadas ─    │   ←  amendment §2.4
│  ─ search ⌘K ─                                       │
├─                                                     │
│  ─ thread header ─ Jana · subtítulo · biz chip ─    │   ←  amendment §2.6
│  ─ msg msg msg msg ─                                 │
│  ─ block renderer: markdown/tool_use/data_table/    │   ←  amendment §2.1 + C1
│    action_card ─                                     │
│  ─ composer ─ placeholder Jana + PII detector ─      │   ←  amendment §2.5
└──────────────────────────────────────────────────────┘
```

### Depois (export 2026-05-15)

```
┌─ topbar ─ JANA · [Dashboard|Analista IA] ────────────┐
├─ jc-header — avatar + Jana · Analista IA + tenant chip │   ←  parecido com amendment §2.6
├─ tab=dashboard:                                      │
│  ┌─ jc-brief — Brief diário · greeting + 4 paragrafos│
│  │   + 4 chips de ação ─┐                            │
│  │                                                   │
│  ├─ jc-kpis — 4 KPI cards (receita/inadimplência/    │
│  │   ticket médio/frota) ──┐                         │
│  │                                                   │
│  ├─ jc-grid — 6 AnaliseCards                         │
│  │   (buckets/sparkline/bars/list/donut/text) ─┐     │
│  │                                                   │
│  └─ jc-acoes — 4 AcaoRow                             │
│      (régua/reativação/outbound/cleanup) ─┐          │
│                                                      │
├─ tab=ia:                                             │
│  └─ jc-converse — header simples + thread + composer │
│      + 5 sugestões chips ─┐                          │
└──────────────────────────────────────────────────────┘
```

Não há `ConvList`. Não há tabs `Todas/Minhas/Compartilhadas/Arquivadas`. Não há per-conversation header. **O chat virou 1 tab de um dashboard**, não a tela inteira.

### Comparáveis 2026

| Referência | Produto |
|---|---|
| [Glean Home](https://www.glean.com/blog/glean-chat-launch-announcement) | Dashboard com cards de KPIs + chat embedded |
| Microsoft Copilot M365 Home | Brief diário + chips + chat lateral |
| Notion AI workspace | Dashboard + ask AI inline |
| ChatGPT Enterprise | Pure conversational shell (2-col threads + thread) |
| Front / Glean Assist | 2-col conversation shell (mais próximo do amendment original) |

Cowork claramente foi pra **lado A** (Glean Home / Copilot M365), não **lado B** (ChatGPT Enterprise / Front). Ambos são legítimos em 2026 — não é "Cowork errou", é "Cowork tomou outro caminho".

---

## §2 — Check item-a-item das 19 divergências P0

Legenda:

- ✅ **CLOSED** — corrigido
- 🟡 **PARTIAL** — parcialmente atendido / atendido em outro paradigma
- ❌ **OPEN** — ainda violado / não implementado
- ⚪ **MOOT** — item perdeu sentido porque shell mudou

### Bloco A — Anti-patterns explícitos do charter (P0)

| # | Item amendment | Status | Evidência |
|:-:|---|:-:|---|
| **A1** | Avatar quadrado `rounded-md bg-primary` letra "J" mono | ❌ **OPEN** | [chat-jana.css:43-49](chat-jana.css) — `border-radius:10px` (ok square-ish) MAS `background:linear-gradient(135deg, #8a6cf5, #5b3ec8)` (gradient violet — viola "monocromática") + JSX usa emoji `🤖` em vez de letra "J" |
| **A2** | Typing indicator: chip `animate-pulse` "Jana está pensando" | ❌ **OPEN** | Não implementado. `chat-jana.jsx` não tem streaming nem indicator. `onSend()` linha 395 só faz `setMsgs([..., user_msg])` — sem resposta da Jana |
| **A3** | Bubbles `rounded-md` sem tail | ❌ **OPEN** | [chat-jana.css:498](chat-jana.css) `border-bottom-right-radius:4px` (user) + linha 505 `border-bottom-left-radius:4px` (jana) = **tail visual mantido** (assimetria WhatsApp-style). Charter pede simétrico |
| **A4** | Resposta Jana = bloco estruturado, não texto humano | 🟡 **PARTIAL** | Mock `kind: "list-card"` (linha 128) é estruturado ✅. MAS é **apenas 1 kind** dos 4 canônicos (markdown/tool_use/data_table/action_card). Ver C1 |
| **A5** | Streaming token-a-token SSE/Centrifugo | ❌ **OPEN** | Não implementado. `onSend()` não dispara resposta nenhuma — só ecoa msg do usuário. Sem mock-stream.js do tarball ZIP de 2026-05-09 |

### Bloco B — Vocabulário humano vazado (P0)

| # | Item amendment | Status | Evidência |
|:-:|---|:-:|---|
| **B1** | Read receipts `✓` / `✓✓` | ✅ **CLOSED** | Ausentes do `chat-jana.jsx` |
| **B2** | Botão `<I.phone>` "Ligar" no header | ✅ **CLOSED** | Não presente |
| **B3** | Online dot no avatar | ✅ **CLOSED** | Não presente |
| **B4** | Placeholder: `Pergunte algo à Jana sobre vendas, OS, financeiro...` | 🟡 **PARTIAL** | [chat-jana.jsx:441](chat-jana.jsx) — `Pergunte algo sobre o {Martinho}…` — usa **nome do cliente** (Martinho), não "Jana sobre vendas/OS/financeiro". Conceitualmente próximo mas literalmente divergente |
| **B5** | Tabs `Todas / Minhas / Compartilhadas / Arquivadas` | ⚪ **MOOT** | Não há lista de conversas. Tabs novos do topbar são `Dashboard / Analista IA` ([app.jsx:267](app.jsx)) — taxonomia diferente |
| **B6** | Subtítulo header: `{msg_count} mensagens · última atividade` | ⚪ **MOOT** | Não há per-conversation header (só 1 thread). Header novo mostra `Jana · Analista IA + tenant chip` — diferente conceito |
| **B7** | Atalhos `/` `J/K` `Esc` globais | ❌ **OPEN** | Nenhum `useEffect` registra `keydown` listener no `chat-jana.jsx`. Só Enter envia |

### Bloco C — Features IA ausentes (P0 — definem o produto)

| # | Item amendment | Status | Evidência |
|:-:|---|:-:|---|
| **C1** | 4 kinds tipados: `markdown` / `tool_use` / `data_table` / `action_card` | 🟡 **PARTIAL** (1 de 4) | [chat-jana.jsx:412](chat-jana.jsx) só renderiza `kind: "list-card"` — não tem switch pelos 4. As "análises" do dashboard (buckets/sparkline/bars/list/donut/text) são tipos **dentro do dashboard**, não bubbles. Charter pede tipos **dentro do bubble** |
| **C2** | Citations inline `[1]` `[2]` clicáveis | ❌ **OPEN** | Não há schema `sources` no mock chat (linha 128-141). Só footnotes em prosa |
| **C3** | `action_card` com `confirm_required: true` + 2 botões | 🟡 **PARTIAL** | As 4 `AcaoRow` no dashboard (linhas 379-389) têm CTA, mas não são dentro de bubble + não têm `confirm_required` flag explícita. Conceito relacionado mas em paradigma diferente |
| **C4** | PII detector regex CPF/CNPJ/cartão no composer | ❌ **OPEN** | [chat-jana.jsx:436-443](chat-jana.jsx) — composer é `<input>` simples sem `onChange` regex check |
| **C5** | Empty state com 4 suggested prompts | 🟡 **PARTIAL** | `chat.suggestions` (linha 143-149) tem 5 chips sempre visíveis abaixo do composer — não é **empty state** (mensagens já populadas no mock), mas é spirit-aligned. Charter pede sumir quando há mensagens |
| **C6** | Chip business atual visível | ✅ **CLOSED** | [chat-jana.jsx:173](chat-jana.jsx) `<span className="jc-tenant">{company?.name?.toUpperCase()}</span>` + `{biz.code}` no header |
| **C7** | Markdown render via `react-markdown` sanitized | 🟡 **PARTIAL** | [chat-jana.jsx:417](chat-jana.jsx) — parsing custom `**bold**` por split regex (linhas 156-163 + 416-419). Funciona pra negrito mas não cobre links/listas/code. Sem sanitizer. Vulnerável a `dangerouslySetInnerHTML` se evoluir |

---

## §3 — Resumo numérico

| Categoria | Total | ✅ | 🟡 | ❌ | ⚪ |
|---|:-:|:-:|:-:|:-:|:-:|
| Bloco A anti-patterns | 5 | 0 | 1 | 4 | 0 |
| Bloco B vocabulário | 7 | 3 | 1 | 1 | 2 |
| Bloco C features IA | 7 | 1 | 4 | 2 | 0 |
| **Total** | **19** | **4** | **6** | **7** | **2** |

**Sob critério literal do amendment** (cada `✅` vale 1, `🟡` vale 0.5, `❌` vale 0, `⚪` neutralizado):

`(4 + 6×0.5) / 17 itens-aplicáveis = 7/17 = 41%` → **score 41/100** (literal)

**Sob critério "Cowork pivotou de boa-fé e o pivot é defensável":**

- Dashboard cockpit em si é forte (Brief diário + KPIs + análises + ações) → **+25 pontos extra**
- Persona-fit Larissa/Wagner (1280px) ok → **+10 pontos extra**
- Tenant chip Tier 0 visível → **+3 pontos extra**

→ score ajustado ≈ **78/100** (com pivot aceito)

**Sob critério "Wagner queria CHAT, ponto, não dashboard":**

→ score ≈ **22/100** — Cowork ignorou request central.

---

## §4 — Recomendação concreta

### Pergunta única a fechar com Wagner

> *"O `/jana/chat` (rota `chat` no AppShell) deve ser:*
> *(A) **Cockpit Analista IA** com aba Dashboard + aba Chat — modelo Glean Home / Copilot M365. Cowork entregou esse caminho.*
> *(B) **Chat conversacional 2-col** com lista de threads à esquerda — modelo ChatGPT Enterprise / Front. Charter atual + amendment 2026-05-14 descrevem esse caminho.*
> *(C) **AMBOS** — `/jana/dashboard` recebe (A) e `/jana/chat` recebe (B), separadas. Custo: 2 charters, 2 implementações."*

### Se Wagner escolher (A) — aceitar pivot

1. **Reescrever charter** [`resources/js/Pages/Jana/Chat.charter.md`](../../resources/js/Pages/Jana/Chat.charter.md) → renomear pra `Cockpit.charter.md` com novos goals (Brief diário > Chat livre).
2. **Reescrever amendment-block-renderer** marcando B5/B6 como `moot`, mantendo A1/A2/A3/A5/C1/C2/C4/C7 como ainda aplicáveis no chat tab.
3. **Pedir [CC] V2.1** focado nos 4 itens A ainda violados + 4 itens C abertos.
4. F1.5 critique-score formal só depois disso.

### Se Wagner escolher (B) — rejeitar pivot

1. Mandar amendment direto: **"Cowork, descarte chat-jana.jsx. Volte ao paradigma 2-col conversacional. Mantenha apenas o conceito do tenant chip (C6 fechado)."**
2. Aproveitar trabalho: o `BriefDiario` + `KPICard` + `AnaliseCard` deste export podem virar componentes de **outra tela** (`/jana/dashboard` separada) — não desperdiça código.

### Se Wagner escolher (C) — ambos

1. Duas charters separadas.
2. `chat-jana.jsx` deste export = base do `/jana/dashboard` (precisa só refinar A1/A3 e adicionar streaming no chat tab pra ficar consistente).
3. `chat.jsx` original do shell + amendment original = base do `/jana/chat`.
4. Cowork precisa entregar V2 do `chat.jsx` paralelo.

---

## §5 — Anti-pattern detectado (meta)

> "Cowork mudou paradigma sem amendment formal."

PROTOCOL.md §3 pede que mudanças de escopo entrem via amendment Wagner → Cowork. O export 2026-05-15 não veio acompanhado de `COWORK_NOTES.amendment-jana-pivot-cockpit.md` justificando o shift de "chat 2-col" pra "dashboard com chat tab". É o mesmo anti-pattern catalogado em [LICOES_F3_FINANCEIRO_REJEITADO.md](../LICOES_F3_FINANCEIRO_REJEITADO.md) (Cowork inferindo escopo).

Solução possível: incluir no PROTOCOL.md §5 (overrides) uma regra "/pivot-detected" — quando export muda paradigma fundamental, [CL] gera CRITIQUE bloqueando F1.5 até [W] ratificar/rejeitar o pivot. Este doc é o primeiro caso real. Vale virar emenda menor ao PROTOCOL.md depois.

---

## §6 — Próximos passos sugeridos (não automáticos — espera Wagner)

1. **Wagner responde §4** — A/B/C
2. Se A ou C → [CL] reescreve charter + amendment
3. Se B → [CL] manda novo amendment "rejeitar pivot" pra Cowork
4. F1.5 critique-score formal só sai depois de §1 ratificada
5. Pivot virar emenda do PROTOCOL.md §5 (override `/pivot-detected`) — task separada

---

## §7 — Referências

- [`_SNAPSHOT.md`](./_SNAPSHOT.md) — contexto do snapshot
- [`chat-jana.jsx`](chat-jana.jsx) · [`chat-jana.css`](chat-jana.css) · [`app.jsx`](app.jsx) — fontes auditadas
- [`../COWORK_NOTES.amendment-jana-chat-block-renderer.md`](../COWORK_NOTES.amendment-jana-chat-block-renderer.md) — 19 divergências P0
- [`../PROTOCOL.md`](../PROTOCOL.md) §3 §5 — fases + overrides
- [`../LICOES_F3_FINANCEIRO_REJEITADO.md`](../LICOES_F3_FINANCEIRO_REJEITADO.md) — anti-patterns Cowork
- [ADR 0114](../../memory/decisions/0114-prototipo-ui-cowork-loop-formalizado.md) — loop formalizado
- [ADR 0107](../../memory/decisions/0107-emendation-0104-visual-comparison-gate-f3.md) — gate F1.5
