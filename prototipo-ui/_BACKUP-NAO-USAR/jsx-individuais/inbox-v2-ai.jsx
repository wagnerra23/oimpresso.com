// inbox-v2-ai.jsx — Refino #2 da Caixa Unificada (IA dentro do thread)
// SummarizeThread · AskInbox · SuggestReply · Empty-palette IA
(() => {
const { useState, useEffect, useMemo, useRef } = React;

// ─────────────────────────────────────────────────────────────────
// HELPERS — conversa → texto
// ─────────────────────────────────────────────────────────────────
function convToText(conv) {
  if (!conv || !conv.msgs) return "";
  return conv.msgs.map(m => {
    const who = m.internal ? `[nota equipe ${m.time}]` : m.who === "me" ? `[Atendente ${m.time}]` : `[Cliente ${m.time}]`;
    return `${who} ${m.t}`;
  }).join("\n");
}

function convCtxBlock(conv) {
  if (!conv) return "";
  return `Cliente: ${conv.name} (${conv.company || "—"})
Canal: ${conv.handle}
Status: ${conv.status}
Tags: ${(conv.tags || []).join(", ") || "—"}
OS vinculada: ${conv.ctx?.os || "—"}
Saldo: ${conv.ctx?.saldo || "—"}
Histórico: ${conv.ctx?.history || "—"}
Último contato: ${conv.ctx?.lastTouch || "—"}`;
}

// Render markdown leve (reuso do estilo do KB)
function mdInline(s) {
  return s
    .replace(/\*\*([^*]+)\*\*/g, "<b>$1</b>")
    .replace(/`([^`]+)`/g, "<code>$1</code>");
}
function renderMD(text) {
  if (!text) return "";
  const esc = (s) => s.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;");
  const lines = esc(text).split("\n").filter(l => l.trim() !== "");
  let html = "", inList = false;
  for (const ln of lines) {
    const t = ln.trim();
    if (/^[-*•]\s+/.test(t)) {
      if (!inList) { html += "<ul>"; inList = true; }
      html += `<li>${mdInline(t.replace(/^[-*•]\s+/, ""))}</li>`;
    } else {
      if (inList) { html += "</ul>"; inList = false; }
      html += `<p>${mdInline(t)}</p>`;
    }
  }
  if (inList) html += "</ul>";
  return html;
}

// ─────────────────────────────────────────────────────────────────
// SUMMARIZE THREAD DIALOG
// ─────────────────────────────────────────────────────────────────
function SummarizeThreadDialog({ conv, onClose }) {
  const [loading, setLoading] = useState(true);
  const [result, setResult] = useState("");
  const [err, setErr] = useState(null);

  useEffect(() => {
    run();
  }, []);

  const run = async () => {
    setLoading(true); setErr(null); setResult("");
    try {
      const text = convToText(conv);
      const ctx = convCtxBlock(conv);
      const ans = await window.claude.complete(
`Você é a IA da Caixa Unificada da gráfica Oimpresso. Resuma esta conversa em 3-4 bullets curtos pra um operador que vai pegar o atendimento agora. Foque em: (1) o que o cliente quer, (2) o que já foi combinado, (3) o que falta. Português brasileiro, conciso, sem floreio.

CONTEXTO DO CLIENTE:
${ctx}

CONVERSA:
${text}

FORMATO:
- bullet 1
- bullet 2
- bullet 3
- (se aplicável) próximo passo recomendado`);
      setResult(ans);
    } catch (e) {
      setErr((e && e.message) || "Falha ao resumir conversa.");
    } finally {
      setLoading(false);
    }
  };

  return (
    <React.Fragment>
      <div className="om-palette-back" onClick={onClose}/>
      <div className="om-ai-modal" role="dialog">
        <header className="om-ai-modal-h">
          <div>
            <small>IA · Resumir conversa</small>
            <h3>{conv.name} <span style={{color:"var(--text-mute)", fontWeight:500}}>· {conv.msgs.length} mensagens</span></h3>
          </div>
          <button className="om-x" onClick={onClose}>✕</button>
        </header>
        <div className="om-ai-modal-body">
          {loading && (
            <div className="om-ai-loading">
              <div className="om-ai-dots"><span></span><span></span><span></span></div>
              <small>Lendo {conv.msgs.length} mensagens...</small>
            </div>
          )}
          {!loading && result && (
            <div className="om-ai-answer" dangerouslySetInnerHTML={{__html: renderMD(result)}}/>
          )}
          {!loading && err && <div className="om-ai-err">{err}</div>}
        </div>
        <footer className="om-ai-modal-foot">
          <small>Resumo gerado por IA. Confirme detalhes no thread original.</small>
          {!loading && (
            <button className="os-btn ghost" onClick={run}>Refazer</button>
          )}
        </footer>
      </div>
    </React.Fragment>
  );
}
window.SummarizeThreadDialog = SummarizeThreadDialog;

// ─────────────────────────────────────────────────────────────────
// ASK INBOX DIALOG — RAG no histórico do cliente + outras conversas + KB
// ─────────────────────────────────────────────────────────────────
function AskInboxDialog({ conv, allConvs, kbArticles, initialQuery, onClose }) {
  const [query, setQuery] = useState(initialQuery || "");
  const [loading, setLoading] = useState(false);
  const [history, setHistory] = useState([]);
  const [err, setErr] = useState(null);
  const inputRef = useRef(null);

  useEffect(() => {
    setTimeout(() => inputRef.current && inputRef.current.focus(), 60);
    if (initialQuery && initialQuery.trim()) {
      run(initialQuery);
    }
  }, []);

  const run = async (q) => {
    const question = (q || query || "").trim();
    if (!question) return;
    setLoading(true); setErr(null);
    try {
      // Histórico deste cliente (todas conversas com mesmo nome/empresa)
      const sameCustomer = (allConvs || []).filter(c =>
        c.id !== conv?.id &&
        (c.name === conv?.name || (conv?.company && c.company === conv.company))
      );
      const customerBlob = sameCustomer.length ? sameCustomer.map(c => `${c.name} · ${c.preview}`).join("\n") : "(sem outras conversas registradas deste cliente)";

      const currentThread = conv ? convToText(conv).slice(0, 2400) : "";
      const kbBlob = (kbArticles || []).slice(0, 8).map(a =>
        `[${a.id}] ${a.title}: ${a.excerpt}`
      ).join("\n");

      const prompt =
`Você é a IA da Caixa Unificada Oimpresso. Use APENAS o contexto abaixo. Se não estiver lá, diga "Não tenho essa informação no histórico." Português brasileiro, prático.

CLIENTE ATIVO: ${conv?.name || "—"} (${conv?.company || "—"})

THREAD ATUAL:
${currentThread || "(sem conversa selecionada)"}

OUTRAS CONVERSAS DESTE CLIENTE:
${customerBlob}

CONTEXTO ERP:
${convCtxBlock(conv)}

ARTIGOS RELEVANTES DO KB (cite com [a3]):
${kbBlob || "(nenhum)"}

PERGUNTA: ${question}

RESPOSTA (3-6 frases + citações [a3] se usar KB):`;

      const ans = await window.claude.complete(prompt);
      setHistory(h => [...h, { q: question, a: ans }]);
      setQuery("");
    } catch (e) {
      setErr((e && e.message) || "Falha ao consultar IA.");
    } finally {
      setLoading(false);
    }
  };

  const onKey = (e) => {
    if (e.key === "Enter" && !e.shiftKey) { e.preventDefault(); run(); }
  };

  return (
    <React.Fragment>
      <div className="om-palette-back" onClick={onClose}/>
      <div className="om-ai-modal lg" role="dialog">
        <header className="om-ai-modal-h">
          <div>
            <small>IA · Perguntar ao histórico</small>
            <h3>{conv?.name || "Cliente"}</h3>
            <p className="om-ai-sub">Acessa thread atual, outras conversas do cliente e artigos do KB.</p>
          </div>
          <button className="om-x" onClick={onClose}>✕</button>
        </header>
        <div className="om-ai-modal-body">
          {history.length === 0 && !loading && (
            <div className="om-ai-suggestions">
              <small>Sugestões — clique pra perguntar</small>
              {[
                "Já atendi esse cliente antes? O que ele costuma pedir?",
                "Esse cliente tem boleto atrasado?",
                "Qual o ICC certo pra material que ele costuma comprar?",
              ].map((s, i) => (
                <button key={i} className="om-ai-sugg" onClick={() => run(s)}>{s}</button>
              ))}
            </div>
          )}
          {history.map((h, i) => (
            <div key={i} className="om-ai-turn">
              <div className="om-ai-q"><b>Você:</b> {h.q}</div>
              <div className="om-ai-answer" dangerouslySetInnerHTML={{__html: renderMD(h.a)}}/>
            </div>
          ))}
          {loading && (
            <div className="om-ai-loading">
              <div className="om-ai-dots"><span></span><span></span><span></span></div>
              <small>Consultando contexto do cliente...</small>
            </div>
          )}
          {err && <div className="om-ai-err">{err}</div>}
        </div>
        <div className="om-ai-input-row">
          <textarea
            ref={inputRef}
            value={query}
            onChange={e => setQuery(e.target.value)}
            onKeyDown={onKey}
            placeholder="Ex.: já atendi esse cliente antes?"
            rows={2}/>
          <button className="os-btn primary" disabled={loading || !query.trim()} onClick={() => run()}>
            {loading ? "..." : "Perguntar"}
          </button>
        </div>
      </div>
    </React.Fragment>
  );
}
window.AskInboxDialog = AskInboxDialog;

// ─────────────────────────────────────────────────────────────────
// SUGGEST REPLY — IA propõe próxima mensagem + tag de fila
// ─────────────────────────────────────────────────────────────────
async function suggestReply(conv) {
  if (!conv) throw new Error("Sem conversa.");
  const text = convToText(conv);
  const ctx = convCtxBlock(conv);

  const prompt =
`Você é o atendente experiente da gráfica Oimpresso. Com base no histórico abaixo, escreva a próxima resposta ao cliente. Tom: cordial, direto, em português brasileiro. Nunca prometa algo que dependa de informação que você não tem. Se faltar info, peça.

NÃO use saudações longas. NÃO use emoji. Frase única quando possível, ou 2 frases curtas. Considere a última mensagem do cliente como ponto de partida.

CONTEXTO:
${ctx}

CONVERSA:
${text}

Responda APENAS em JSON, formato:
{"reply": "texto pronto pra enviar", "queue_suggestion": "vendas|posvenda|fin|prod|geral", "intent": "compra|suporte|cobrança|informação|reclamação"}`;

  const ans = await window.claude.complete(prompt);
  const match = ans.match(/\{[\s\S]*\}/);
  if (!match) throw new Error("IA retornou formato inesperado.");
  return JSON.parse(match[0]);
}
window.suggestReplyAI = suggestReply;

// Botão wrapper que tu plugas no composer
function SuggestReplyButton({ conv, onApply }) {
  const [loading, setLoading] = useState(false);
  const [out, setOut] = useState(null);
  const [err, setErr] = useState(null);
  const [expanded, setExpanded] = useState(false);

  const run = async () => {
    setLoading(true); setErr(null); setOut(null);
    try {
      const result = await suggestReply(conv);
      setOut(result);
      setExpanded(true);
    } catch (e) {
      setErr((e && e.message) || "Falha");
    } finally {
      setLoading(false);
    }
  };

  if (loading) {
    return (
      <button className="om-suggest-btn loading" disabled>
        <span className="om-ai-dots-tiny"><span></span><span></span><span></span></span>
        Pensando...
      </button>
    );
  }

  if (!expanded || !out) {
    return (
      <button className="om-suggest-btn" onClick={run} title="IA sugere resposta com base no histórico" disabled={!conv}>
        <span className="om-suggest-spark">✦</span>
        Sugerir resposta
      </button>
    );
  }

  return (
    <div className="om-suggest-result">
      <div className="om-suggest-head">
        <span className="om-suggest-spark">✦</span>
        <b>Sugestão da IA</b>
        <span className="om-suggest-tags">
          <span className="om-suggest-tag">→ fila {out.queue_suggestion}</span>
          <span className="om-suggest-tag intent">{out.intent}</span>
        </span>
        <button className="om-suggest-close" onClick={() => setExpanded(false)} title="Descartar">✕</button>
      </div>
      <p className="om-suggest-text">{out.reply}</p>
      <div className="om-suggest-actions">
        <button className="os-btn ghost" onClick={run}>Outra</button>
        <button className="os-btn primary" onClick={() => { onApply(out); setExpanded(false); }}>
          Usar essa
        </button>
      </div>
    </div>
  );
}
window.SuggestReplyButton = SuggestReplyButton;

})();
