---
amendment_id: cockpit-v2.1-refinos
related_request: chat-jana.jsx export 2026-05-15 + CRITIQUE 78/100
charter: resources/js/Pages/Jana/Cockpit.charter.md (NOVO 2026-05-15)
author: Wagner [W] + Claude Code [CL]
date: 2026-05-15
severity: F1.5 round refator único — gate ≥80
trigger: revisão pós Caixa Unificada V4 confirmou pivot · 8 anti-patterns abertos
---

# [W → CC] Amendment Cockpit V2.1 — fechar 8 refinos pra F1.5 ≥80

> **Pivot Cowork ACEITO** — `chat-jana.jsx` (export 2026-05-15) é o novo paradigma do `/jana/cockpit`: Cockpit Analista IA = brief diário + KPIs + análises + ações HITL · com aba IA single-thread. Substitui in-place o `Cockpit.tsx` atual (138 lin · MVP-piloto-em-validacao) que tinha anti-patterns WhatsApp-style.
>
> **Charter vivo:** `resources/js/Pages/Jana/Cockpit.charter.md` (242 lin) declara goals/non-goals/UX targets/anti-patterns/automation hooks/Pest GUARDs/roadmap.
>
> **CRITIQUE atual:** `prototipo-ui/_cowork-export-2026-05-15/CRITIQUE-chat-jana-vs-amendment.md` calibrou 78/100. Gate F1.5 ≥80 → preciso fechar 6 dos 8 refinos abaixo (peso ponderado).

---

## §1 — Refinos pra V2.1 (8 itens · ~3-4h Cowork)

### 1. A1 · `JanaAvatar` quadrado mono primary letra "J"

**Atual** ([chat-jana.css:43-49](_cowork-export-2026-05-15/chat-jana.css)):

```css
.jc-avatar{
  width:44px; height:44px; border-radius:10px;
  display:grid; place-items:center;
  background:linear-gradient(135deg, #8a6cf5, #5b3ec8);  /* ❌ gradient violet */
  color:#fff; font-size:22px;                              /* ❌ emoji 🤖 22px */
}
```

**Substituir** por:

```css
.jc-avatar{
  width:44px; height:44px; border-radius:8px;             /* rounded-md token Cockpit V2 */
  display:grid; place-items:center;
  background: var(--primary, oklch(0.42 0.10 250));       /* mono primary */
  color: var(--primary-foreground, #fff);
  font-size:18px; font-weight:600;
  font-family: ui-sans-serif, system-ui, -apple-system, sans-serif;
}
```

E no JSX [chat-jana.jsx:169](_cowork-export-2026-05-15/chat-jana.jsx) substituir emoji por letra:

```jsx
// ❌ Antes:
<div className="jc-avatar">{person.avatar}</div>  // emoji 🤖

// ✅ Depois:
<div className="jc-avatar">J</div>  // ou primeira letra de person.name
```

Aplicar nos 2 lugares onde JanaAvatar aparece (header + empty state da aba IA quando F2 entrar).

---

### 2. A3 · Bubbles simétricos sem tail

**Atual** ([chat-jana.css:494-507](_cowork-export-2026-05-15/chat-jana.css)):

```css
.jc-bub-user{
  align-self:flex-end;
  background:var(--jc-violet);
  color:#fff;
  border-bottom-right-radius:4px;   /* ❌ tail asimétrico */
}
.jc-bub-jana{
  align-self:flex-start;
  background:#fff;
  color:var(--jc-ink);
  border:1px solid var(--jc-line);
  border-bottom-left-radius:4px;    /* ❌ tail asimétrico */
  box-shadow:0 1px 4px rgba(15,16,20,.04);
  max-width:90%;
}
```

**Substituir** por simétricos `rounded-md` token Cockpit V2:

```css
.jc-bub{
  border-radius: 8px;               /* rounded-md uniforme */
}
.jc-bub-user{
  align-self:flex-end;
  background: var(--primary, oklch(0.42 0.10 250));      /* primary mono */
  color: var(--primary-foreground, #fff);
  /* sem border-bottom-*-radius override */
}
.jc-bub-jana{
  align-self:flex-start;
  background: var(--card, #fff);
  color: var(--foreground, var(--jc-ink));
  border:1px solid var(--border, var(--jc-line));
  /* sem border-bottom-*-radius override */
  box-shadow:0 1px 4px rgba(15,16,20,.04);
  max-width:90%;
}
```

Charter `Chat.charter.md` já estabelece `bg-primary/5` user · `bg-card` assistant — alinhar tons.

---

### 3. A5 · `mock-stream.js` SSE fake (A2 vira automático)

**Atual** ([chat-jana.jsx:395-399](_cowork-export-2026-05-15/chat-jana.jsx)):

```jsx
const onSend = () => {
  if (!draft.trim()) return;
  setMsgs(m => [...m, { from:"user", text: draft.trim() }]);  // ❌ só ecoa user
  setDraft("");
};
```

**Adicionar** arquivo novo `mock-stream.js`:

```js
// mock-stream.js — fake SSE pra simular streaming Brain B token-a-token
// Substitui setTimeout(reply, 2400) que era anti-pattern A5 do amendment 2026-05-14
window.MockStream = {
  start({ prompt, onDelta, onFinal }) {
    const responses = {
      "regua": ["📨 ", "Régua ", "preparada ", "para ", "8 ", "clientes ", "ouro ", ">90d.\n", "[1] ", "VARGAS ", "R$ [redacted Tier 0]k ", "[2] ", "TORK ", "R$ [redacted Tier 0]k.\n", "Confirma ", "envio?"],
      "default": ["Analisando ", "seu ", "pedido ", "sobre ", `"${prompt.slice(0,40)}..."\n\n`, "Posso ", "consultar ", "o ", "módulo ", "relevante ", "agora?"],
    };
    const key = prompt.toLowerCase().includes("régua") ? "regua" : "default";
    const tokens = responses[key];
    let i = 0;
    const id = setInterval(() => {
      if (i >= tokens.length) {
        clearInterval(id);
        onFinal?.({ text: tokens.join(""), kind: "markdown" });
        return;
      }
      onDelta?.(tokens[i++]);
    }, 60); // 60ms/token = ~16tok/s simulado
    return () => clearInterval(id);
  },
};
```

Registrar em `Oimpresso ERP - Chat.html` antes de `chat-jana.jsx`:

```html
<script src="mock-stream.js"></script>
```

**Reescrever** `onSend` em [chat-jana.jsx:395](_cowork-export-2026-05-15/chat-jana.jsx):

```jsx
const [streaming, setStreaming] = useStateJ(false);
const [streamBuffer, setStreamBuffer] = useStateJ("");

const onSend = () => {
  if (!draft.trim()) return;
  const userMsg = { from:"user", text: draft.trim() };
  setMsgs(m => [...m, userMsg]);
  const prompt = draft.trim();
  setDraft("");
  setStreaming(true);
  setStreamBuffer("");

  window.MockStream.start({
    prompt,
    onDelta: (chunk) => setStreamBuffer(b => b + chunk),
    onFinal: (msg) => {
      setMsgs(m => [...m, { from:"jana", kind: msg.kind, text: msg.text }]);
      setStreamBuffer("");
      setStreaming(false);
    },
  });
};
```

**Renderizar** typing chip + buffer dentro do `.jc-thread`:

```jsx
{streaming && (
  <div className="jc-bub jc-bub-jana jc-streaming">
    {streamBuffer || <span className="jc-thinking-chip">
      <span className="dot animate-pulse"/>
      Jana está pensando
    </span>}
  </div>
)}
```

CSS `.jc-thinking-chip`:

```css
.jc-thinking-chip{
  display:inline-flex; align-items:center; gap:8px;
  font-size:12px; color:var(--jc-ink-3);
}
.jc-thinking-chip .dot{
  width:6px; height:6px; border-radius:50%; background: var(--primary);
}
```

A2 (typing indicator) é resolvido junto com A5 — chip aparece só durante stream e some quando primeiro token chega (buffer popula).

---

### 4. B7 · Atalhos globais `/` `J/K` `Esc`

**Adicionar** `useEffect` no `JanaCockpit` (linha ~456 antes do `if (isChat)`):

```jsx
useEffectJ(() => {
  if (!isChat) return; // só na aba IA

  const composerEl = () => document.querySelector('.jc-composer input, .jc-composer textarea');
  const isInputFocused = () => {
    const a = document.activeElement;
    return a && (a.tagName === 'INPUT' || a.tagName === 'TEXTAREA' || a.isContentEditable);
  };

  const onKey = (e) => {
    // ⌘K / Ctrl+K → search histórico (futuro)
    if ((e.metaKey || e.ctrlKey) && e.key === 'k') { e.preventDefault(); /* TODO: focar search */ return; }
    // Filtros de input pra atalhos sem modificador
    if (e.metaKey || e.ctrlKey || e.altKey) return;
    if (isInputFocused() && e.key !== 'Escape') return;

    if (e.key === '/') { e.preventDefault(); composerEl()?.focus(); }
    else if (e.key === 'j' || e.key === 'J') { /* TODO: próxima msg scroll+highlight */ }
    else if (e.key === 'k' || e.key === 'K') { /* TODO: msg anterior */ }
    else if (e.key === 'Escape') { composerEl()?.blur(); }
  };
  document.addEventListener('keydown', onKey);
  return () => document.removeEventListener('keydown', onKey);
}, [isChat]);
```

J/K stubs ok no protótipo (pra fechar Pest GUARD); implementação real fica pra F3 com refs nos bubbles.

---

### 5. C1 · Switch 4 kinds bubble (markdown / tool_use / data_table / action_card)

**Atual**: só `kind: "list-card"` ([chat-jana.jsx:412-433](_cowork-export-2026-05-15/chat-jana.jsx)).

**Substituir** por switch + 4 componentes:

```jsx
function MarkdownBubble({ m }) {
  // Mock: parser **bold** simples — F3 troca por react-markdown
  return (
    <div className="jc-bub jc-bub-jana">
      {m.text.split('\n').map((line, i) => (
        <p key={i}>{line.split(/\*\*(.+?)\*\*/).map((p, k) => k % 2 === 1 ? <b key={k}>{p}</b> : p)}</p>
      ))}
      {m.sources && (
        <div className="jc-citations">
          {m.sources.map(s => <a key={s.n} className="jc-cite" href={s.href}>[{s.n}] {s.label}</a>)}
        </div>
      )}
    </div>
  );
}

function ToolUseChip({ m }) {
  return (
    <div className="jc-bub jc-bub-jana jc-tooluse">
      <span className="jc-tooluse-ic">⚙</span>
      <span><b>Consultou</b> <code>{m.tool}</code></span>
      <span className={"jc-tooluse-st " + m.status}>{m.status === 'running' ? 'rodando…' : m.status === 'done' ? 'pronto' : 'erro'}</span>
    </div>
  );
}

function DataTableBubble({ m }) {
  return (
    <div className="jc-bub jc-bub-jana jc-datatable">
      {m.caption && <b className="jc-datatable-cap">{m.caption}</b>}
      <table>
        <thead><tr>{m.columns.map(c => <th key={c}>{c}</th>)}</tr></thead>
        <tbody>
          {m.rows.slice(0, 5).map((r, i) => (
            <tr key={i}>{r.map((c, j) => <td key={j}>{c}</td>)}</tr>
          ))}
        </tbody>
      </table>
      {m.rows.length > 5 && <button className="jc-datatable-more">ver mais ({m.rows.length - 5})</button>}
    </div>
  );
}

function ActionCardBubble({ m }) {
  const tone = m.confirm_required ? 'amber' : m.result === 'done' ? 'emerald' : m.result === 'error' ? 'rose' : 'amber';
  return (
    <div className={"jc-bub jc-bub-jana jc-actioncard tone-" + tone}>
      <b>{m.summary}</b>
      {m.confirm_required && (
        <div className="jc-actioncard-btns">
          <button className="jc-cta primary">Confirmar</button>
          <button className="jc-cta ghost">Cancelar</button>
        </div>
      )}
    </div>
  );
}
```

E o switch dentro de `<ConverseComJana>`:

```jsx
{msgs.map((m, i) => {
  if (m.from === 'user') return <div key={i} className="jc-bub jc-bub-user">{m.text}</div>;
  if (m.kind === 'markdown')    return <MarkdownBubble    key={i} m={m}/>;
  if (m.kind === 'tool_use')    return <ToolUseChip       key={i} m={m}/>;
  if (m.kind === 'data_table')  return <DataTableBubble   key={i} m={m}/>;
  if (m.kind === 'action_card') return <ActionCardBubble  key={i} m={m}/>;
  // fallback: list-card legado (mock atual)
  return <LegacyListCardBubble key={i} m={m}/>;
})}
```

**Adicionar mock** no `chat.messages` com pelo menos 1 exemplo de cada kind:

```js
messages: [
  { from:"user", text:"Quais os top 5 devedores?" },
  { from:"jana", kind:"tool_use", tool:"financeiro.devedores.top", status:"done" },
  { from:"jana", kind:"data_table", caption:"5 devedores ativos",
    columns:["Cliente", "Saldo", "Parcelas"],
    rows:[
      ["VARGAS LEANDRO","R$ [redacted Tier 0]","229"],
      ["TORK COMERCIO","R$ [redacted Tier 0]","167"],
      ["AMS SOLDAS","R$ [redacted Tier 0]","71"],
      ["BUSSOLO E PRUDENCIO","R$ [redacted Tier 0]","43"],
      ["FAN COM PECAS","R$ [redacted Tier 0]","166"],
    ]},
  { from:"jana", kind:"markdown",
    text:"Top 5 concentra **R$ [redacted Tier 0]k** (~20% inadimplência). VARGAS sozinho concentra **8,5%** [1] mas é cliente recorrente (229 parcelas) [2].",
    sources:[
      { n:1, label:"Inadimplência por cliente", href:"/financeiro/inadimplencia?cliente=vargas" },
      { n:2, label:"Histórico VARGAS", href:"/clientes/vargas" },
    ]},
  { from:"jana", kind:"action_card",
    summary:"Disparar régua WhatsApp pra VARGAS LEANDRO (último contato 47d)",
    confirm_required:true },
],
```

CSS extra (token suite):

```css
.jc-citations{ margin-top:8px; display:flex; gap:8px; flex-wrap:wrap; }
.jc-cite{ font-size:11px; color: var(--primary); text-decoration:underline; }

.jc-tooluse{ display:flex; align-items:center; gap:8px; background:#f0f7ff; color:#0c4a6e; }
.jc-tooluse-ic{ font-size:14px; }
.jc-tooluse code{ background:rgba(0,0,0,.05); padding:2px 6px; border-radius:4px; font-size:11px; }
.jc-tooluse-st.running{ color:#a35a00; }
.jc-tooluse-st.done{ color:#1f6d3a; }
.jc-tooluse-st.error{ color:#e54848; }

.jc-datatable table{ width:100%; border-collapse:collapse; font-size:12px; margin-top:6px; }
.jc-datatable th,.jc-datatable td{ padding:6px 8px; border-bottom:1px solid var(--jc-line-2); text-align:left; }
.jc-datatable-more{ margin-top:6px; color:var(--primary); font-size:11px; cursor:pointer; }

.jc-actioncard{ border-left:3px solid var(--jc-amber); padding-left:12px; }
.jc-actioncard.tone-amber{ background: var(--jc-amber-soft); border-left-color: var(--jc-amber); }
.jc-actioncard.tone-emerald{ background: var(--jc-green-soft); border-left-color: var(--jc-green); }
.jc-actioncard.tone-rose{ background: var(--jc-rose-soft); border-left-color: var(--jc-rose); }
.jc-actioncard-btns{ display:flex; gap:8px; margin-top:8px; }
```

---

### 6. C2 · Citations inline `[1]` clicáveis

**Coberto pelo C1** — `<MarkdownBubble>` já implementa `m.sources` com `<a>` clicável e o mock data já tem `[1] [2]`. Garantir que os números no markdown ficam como `<sup><a>[1]</a></sup>` (compacto inline) — pode adicionar um regex no parser:

```jsx
// Em MarkdownBubble, antes do split de **bold**:
const renderInline = (text) => {
  return text.split(/(\[\d+\])/g).map((part, i) => {
    const match = part.match(/^\[(\d+)\]$/);
    if (match) {
      const n = parseInt(match[1]);
      const src = m.sources?.find(s => s.n === n);
      return src
        ? <sup key={i}><a className="jc-cite-inline" href={src.href}>[{n}]</a></sup>
        : <sup key={i}>[{n}]</sup>;
    }
    return part.split(/\*\*(.+?)\*\*/).map((p, k) => k % 2 === 1 ? <b key={`${i}-${k}`}>{p}</b> : p);
  });
};
```

```css
.jc-cite-inline{ color: var(--primary); text-decoration:none; font-weight:600; font-size:10px; }
.jc-cite-inline:hover{ text-decoration:underline; }
```

---

### 7. C4 · PII detector regex no composer

**Atual** ([chat-jana.jsx:436-443](_cowork-export-2026-05-15/chat-jana.jsx)) — composer simples sem PII check.

**Substituir** por:

```jsx
const PII_REGEX = {
  cpf:    /\b\d{3}\.?\d{3}\.?\d{3}-?\d{2}\b/,
  cnpj:   /\b\d{2}\.?\d{3}\.?\d{3}\/?\d{4}-?\d{2}\b/,
  cartao: /\b\d{4}[\s-]?\d{4}[\s-]?\d{4}[\s-]?\d{4}\b/,
};

const piiDetected = (() => {
  for (const [k, rx] of Object.entries(PII_REGEX)) if (rx.test(draft)) return k;
  return null;
})();

return (
  <div className="jc-composer">
    <input
      value={draft}
      onChange={e => setDraft(e.target.value)}
      onKeyDown={e => e.key === "Enter" && !e.shiftKey && onSend()}
      placeholder={`Pergunte algo à Jana sobre vendas, OS, financeiro...`}/>
    <button className="jc-cta primary" onClick={onSend} disabled={streaming}>Enviar</button>
    {piiDetected && (
      <div className="jc-pii-warn">
        ⚠️ Conteúdo sensível detectado ({piiDetected.toUpperCase()}) — Jana redige sem PII no audit log
      </div>
    )}
  </div>
);
```

CSS:

```css
.jc-pii-warn{
  position:absolute; bottom:-28px; left:0; right:0;
  background: var(--jc-amber-soft); color: var(--jc-amber);
  font-size:11px; padding:6px 10px; border-radius:6px;
}
.jc-composer{ position:relative; }
```

NÃO bloqueia envio. Server-side `PiiRedactor` faz o trabalho pesado.

Também atualizar placeholder pra menos persona-tied (Charter §B4):

```jsx
placeholder="Pergunte algo à Jana sobre vendas, OS, financeiro..."
```

---

### 8. C7 · Markdown render via parser robusto (não regex custom)

No protótipo Cowork (sem build), manter regex custom é OK porque substituir por `react-markdown` exige bundler. Mas:

- **Documentar no comment do `<MarkdownBubble>`**: `// F3 produção: trocar por react-markdown + rehype-sanitize`
- **Evitar `dangerouslySetInnerHTML`** em qualquer lugar
- **Suportar pelo menos**: `**bold**`, `*italic*`, links `[txt](url)`, listas `- ...`, code `` `inline` ``

Versão um pouco mais robusta que cobre 4 markers (bold/italic/link/code):

```jsx
const renderMarkdownInline = (text) => {
  const tokens = [];
  let buf = '';
  let i = 0;
  while (i < text.length) {
    if (text.slice(i, i+2) === '**') {
      const end = text.indexOf('**', i+2);
      if (end > -1) { if (buf) tokens.push(buf); buf=''; tokens.push(<b key={i}>{text.slice(i+2, end)}</b>); i = end+2; continue; }
    }
    if (text[i] === '`') {
      const end = text.indexOf('`', i+1);
      if (end > -1) { if (buf) tokens.push(buf); buf=''; tokens.push(<code key={i}>{text.slice(i+1, end)}</code>); i = end+1; continue; }
    }
    const linkMatch = text.slice(i).match(/^\[([^\]]+)\]\(([^)]+)\)/);
    if (linkMatch) { if (buf) tokens.push(buf); buf=''; tokens.push(<a key={i} href={linkMatch[2]}>{linkMatch[1]}</a>); i += linkMatch[0].length; continue; }
    buf += text[i]; i++;
  }
  if (buf) tokens.push(buf);
  return tokens;
};
```

Combinar com o `renderInline` de C2 (citations).

---

## §2 — Critério "Done F1.5" (CD aprova ≥80)

| Categoria | Itens | Peso |
|---|---|:-:|
| **Anti-patterns Bloco A eliminados** (A1+A2+A3+A4+A5 — 5 itens) | 0 violações | 25% |
| **Atalhos B7** | listener funcional + filtra input focus | 10% |
| **4 kinds renderer C1** | switch + 4 componentes + mock 1 exemplo cada | 30% |
| **Citations C2 + PII C4** | ambos visíveis no protótipo | 15% |
| **Streaming + typing chip A5+A2** | mock-stream.js funcional | 10% |
| **Charter §3 inalterado** (shell preservado) | regressão zero no dashboard tab | 10% |

Score atual interim **78/100**. Refinos 1-3 + 5 + 7 fechados → ~+12 pontos = **90/100** (folga acima do gate).

Abaixo de 80 → 1 round refator extra. Abaixo de 70 → discussão com [W].

---

## §3 — Itens INALTERADOS (já bons no chat-jana.jsx · NÃO mexer)

- ✅ Layout 1-col scrollable (1280px Larissa fit)
- ✅ Brief diário (greeting + 4 paragraphs + 4 chips de ação)
- ✅ 4 KPI cards grid responsivo (Receita / A receber / Ticket médio / Frota)
- ✅ 6 Análises principais (buckets / sparkline / bars / list / donut / text variants)
- ✅ 4 Ações HITL (rose / violet / peach / grey tones com CTA tone-coded)
- ✅ Tenant chip Tier 0 visível (`<span className="jc-tenant">{company.name}</span>`)
- ✅ Tabs `Dashboard / Analista IA` topbar
- ✅ Mock data Martinho Caçambas crível (R$ [redacted Tier 0]M inadimplência, frota 91 caçambas)
- ✅ PT-BR em todo label / copy / comentário
- ✅ Estrutura `<JanaCockpit>` + `<JanaHeader>` + `<BriefDiario>` + `<KPICard>` + `<AnaliseCard>` + `<AcaoRow>` + `<ConverseComJana>`

**Não rebobinar nada do dashboard.** Foco é aba IA.

---

## §4 — Itens REMOVIDOS do escopo (vão pra backlog ou nunca)

| Item | Destino |
|---|---|
| Botão "▶ Ouvir áudio" no Brief | Backlog M2 (TTS Brain B) — placeholder no protótipo |
| Compartilhar thread externa | **Jamais** — Non-Goal Charter (risco PII) |
| Comparar respostas multi-modelo lado-a-lado | **Jamais** — Non-Goal Charter (não é playground) |
| Voice input mic | Backlog M2 — Non-Goal Charter |
| File attachment composer | Backlog M2 (depende Brain B vision policy) |
| Multi-conversação / lista threads esquerda | **Jamais** — paradigma `/jana/` (Chat.tsx) separado |
| Multi-channel (WhatsApp/Email/Instagram) | **Jamais** — paradigma `/atendimento/caixa-unificada` |
| Atendimento humano (fila/SLA/assignee) | **Jamais** — Caixa Unificada cumpre |
| Tabs `Todos/OS/Equipe/Clientes` | **Removido** — anti-pattern Cockpit.tsx atual |
| Read receipts / online dot / botão ligar | **Removido permanente** — anti-features IA |

---

## §5 — Próxima ação por papel

### [CC] Claude Cowork (próximo turno)

Aplicar refinos §1 (8 itens) no `chat-jana.jsx` + `chat-jana.css` + criar `mock-stream.js`. Manter `getJanaData(company)` mock como está + adicionar mensagens novas no `chat.messages` com 1 exemplo de cada kind (markdown / tool_use / data_table / action_card).

Testar render manual em `Oimpresso ERP - Chat.html`:
- aba Dashboard: brief + KPIs + 6 análises + 4 ações sem regressão
- aba Analista IA: thread com 5 mensagens mock (user + 4 kinds Jana) + composer com PII chip ao colar CPF + atalho `/` foca composer + streaming aparece quando envia

### [CD] Claude Design (F1.5 critique)

Score perde **≥15 pts** se reaparecer A1 (gradient) ou A3 (tail). Score perde **≥10 pts/kind ausente**. Score perde **≥5 pts** se streaming não funcionar.

### [W2] Wagner (F2 screenshot)

Aprovação síncrona — checklist visual:
- [ ] Avatar Jana quadrado mono primary letra "J"
- [ ] Bubbles user e Jana com `rounded-md` simétrico (sem rabinho)
- [ ] Aba IA renderiza pelo menos 4 kinds diferentes
- [ ] Citations `[1]` `[2]` clicáveis no markdown
- [ ] Action card amber com 2 botões Confirmar/Cancelar
- [ ] Composer mostra PII chip ao colar CPF
- [ ] `/` foca composer (testar)
- [ ] Streaming aparece quando enviar (typing chip + buffer)
- [ ] Cabe em 1280px sem scroll horizontal
- [ ] Aba Dashboard SEM regressão (brief + KPIs + análises + ações iguais)

### [CL] Claude Code (F3 — só depois F2 aprovado)

Implementar em `resources/js/Pages/Jana/Cockpit.tsx` (substitui in-place 138 lin atual):
- AppShellV2 + 2-tab topbar (Dashboard / Analista IA)
- Sub-componentes em `resources/js/Pages/Jana/_components/Cockpit/` (BriefDiario / KPICard / AnaliseCard / AcaoRow / MarkdownBubble / ToolUseChip / DataTableBubble / ActionCardBubble / JanaAvatar / TypingIndicator)
- Backend `ChatController::cockpit()` evolui — `Inertia::defer` em props brief/kpis/análises/ações
- Models `JanaThread`, `JanaMessage` com `business_id` global scope (criar migrations F2)
- Tabela `jana_audit_log` retenção 90d
- 7 Pest GUARDs §Charter Métricas vivas (R-JANA-COCKPIT-001..007)
- React-markdown + rehype-sanitize substituindo regex custom

---

## §6 — Referências

- [Cockpit.charter.md V2](../resources/js/Pages/Jana/Cockpit.charter.md) — destino arquitetural (NOVO 2026-05-15)
- [`_cowork-export-2026-05-15/CRITIQUE-chat-jana-vs-amendment.md`](_cowork-export-2026-05-15/CRITIQUE-chat-jana-vs-amendment.md) — score F1.5 78/100 + 19 divergências detalhadas
- [`_cowork-export-2026-05-15/_SNAPSHOT.md`](_cowork-export-2026-05-15/_SNAPSHOT.md) — contexto do export
- [`COWORK_NOTES.amendment-jana-chat-block-renderer.md`](COWORK_NOTES.amendment-jana-chat-block-renderer.md) — amendment original 2026-05-14 (válido pra `/jana/` Chat.tsx em workstream separado)
- [`/atendimento/caixa-unificada/Index.charter.md`](../resources/js/Pages/Atendimento/CaixaUnificada/Index.charter.md) — referência paradigma humano (zero overlap)
- [`/jana/Chat.charter.md`](../resources/js/Pages/Jana/Chat.charter.md) — chat conversacional 2-col (continua live)
- [`memory/requisitos/Jana/RUNBOOK-cockpit.md`](../memory/requisitos/Jana/RUNBOOK-cockpit.md) — runbook MVP atual (status: active-superseded-by-v2 desde 2026-05-15)
- [PROTOCOL.md §3 §5](PROTOCOL.md) — fases + overrides
- [ADR 0114](../memory/decisions/0114-prototipo-ui-cowork-loop-formalizado.md) — loop formalizado
- [ADR 0107](../memory/decisions/0107-emendation-0104-visual-comparison-gate-f3.md) — gate F1.5

---

## §7 — Histórico

| Data | Autor | Mudança |
|---|---|---|
| 2026-05-15 | Wagner [W] + Claude Code [CL] | Amendment criado pós CRITIQUE 78/100 + decisão Wagner caminho A (pivot aceito). 8 refinos catalogados com snippets executáveis (CSS + JSX + JS). Critério F1.5 ≥80 com peso ponderado por categoria. Itens §3 inalterados explicitamente listados pra não-rebobinar. |
