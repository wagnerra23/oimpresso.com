// inbox-v2-cur.jsx — Refino #3 da Caixa Unificada (Curadoria + Guia)
// Cross-link · Comentários inline · Troubleshooters de atendimento · Trilhas · Histórico
(() => {
const { useState, useEffect, useMemo, useRef } = React;

// ─────────────────────────────────────────────────────────────────
// CROSS-LINK no thread — #a3 (KB), #os4821 (OS), #c1 (conv), #q-vendas (fila)
// ─────────────────────────────────────────────────────────────────
function linkifyMessage(text, handlers) {
  if (!text || typeof text !== "string") return text;
  const parts = [];
  const re = /#(a\d+|os\d+|c\d+|q-[a-z]+)/g;
  let last = 0, m, i = 0;
  while ((m = re.exec(text)) !== null) {
    if (m.index > last) parts.push(text.slice(last, m.index));
    const ref = m[1];
    const kind = ref.startsWith("a") ? "kb" : ref.startsWith("os") ? "os" : ref.startsWith("c") ? "conv" : "queue";
    parts.push(
      <button
        key={"lk" + i++}
        className={"om-link om-link--" + kind}
        onClick={(e) => { e.stopPropagation(); handlers[kind] && handlers[kind](ref); }}
        title={kind === "kb" ? "Abrir artigo do KB" : kind === "os" ? "Abrir OS" : kind === "conv" ? "Abrir conversa" : "Mover pra fila"}>
        #{ref}
      </button>
    );
    last = m.index + m[0].length;
  }
  if (last < text.length) parts.push(text.slice(last));
  return parts;
}
window.linkifyMessage = linkifyMessage;

// ─────────────────────────────────────────────────────────────────
// COMENTÁRIOS INLINE POR MENSAGEM
// Storage: oimpresso.inbox.msgComments = { convId: { msgIdx: [{author, text, when}] } }
// ─────────────────────────────────────────────────────────────────
function useMsgComments() {
  const [m, setM] = useState(() => {
    try { return JSON.parse(localStorage.getItem("oimpresso.inbox.msgComments") || "{}"); }
    catch (e) { return {}; }
  });
  useEffect(() => {
    try { localStorage.setItem("oimpresso.inbox.msgComments", JSON.stringify(m)); } catch (e) {}
  }, [m]);

  const add = (convId, msgIdx, text, author) => {
    setM(prev => {
      const c = prev[convId] || {};
      const list = c[msgIdx] || [];
      return { ...prev, [convId]: { ...c, [msgIdx]: [...list, { author: author || "você", text, when: "agora" }] } };
    });
  };
  const remove = (convId, msgIdx, i) => {
    setM(prev => {
      const c = { ...(prev[convId] || {}) };
      const list = (c[msgIdx] || []).filter((_, idx) => idx !== i);
      if (list.length) c[msgIdx] = list; else delete c[msgIdx];
      return { ...prev, [convId]: c };
    });
  };
  const forMsg = (convId, msgIdx) => ((m[convId] || {})[msgIdx] || []);
  return { add, remove, forMsg };
}
window.useMsgComments = useMsgComments;

// Wrapper que envolve a bubble e adiciona botão "+" + lista de comentários
function MsgCommentWrap({ comments, onAdd, onRemove, children }) {
  const [open, setOpen] = useState(false);
  const [text, setText] = useState("");
  const submit = () => {
    const t = text.trim();
    if (!t) return;
    onAdd(t);
    setText("");
    setOpen(false);
  };
  return (
    <div className="om-msg-wrap">
      <div className="om-msg-content">{children}</div>
      <button
        className="om-msg-comment-add"
        onClick={() => setOpen(o => !o)}
        title="Comentar essa mensagem (só equipe vê)">
        {open ? "×" : "+"}
      </button>
      {(comments.length > 0 || open) && (
        <div className="om-msg-comments">
          {comments.map((c, i) => (
            <div key={i} className="om-msg-comment">
              <div className="om-msg-comment-h">
                <b>{c.author}</b>
                <span className="mono">{c.when}</span>
                <button onClick={() => onRemove(i)} title="Remover">×</button>
              </div>
              <p>{c.text}</p>
            </div>
          ))}
          {open && (
            <div className="om-msg-comment-new">
              <textarea
                autoFocus
                value={text}
                onChange={e => setText(e.target.value)}
                onKeyDown={e => {
                  if (e.key === "Enter" && (e.metaKey || e.ctrlKey)) { e.preventDefault(); submit(); }
                  if (e.key === "Escape") { setOpen(false); setText(""); }
                }}
                placeholder="Anotar essa mensagem... (⌘↵ envia)"
                rows={2}/>
              <div className="om-msg-comment-row">
                <small>só equipe vê</small>
                <button className="os-btn ghost" onClick={() => { setOpen(false); setText(""); }}>Cancelar</button>
                <button className="os-btn primary" onClick={submit} disabled={!text.trim()}>Comentar</button>
              </div>
            </div>
          )}
        </div>
      )}
    </div>
  );
}
window.MsgCommentWrap = MsgCommentWrap;

// ─────────────────────────────────────────────────────────────────
// TROUBLESHOOTERS DE ATENDIMENTO
// ─────────────────────────────────────────────────────────────────
const INBOX_TROUBLES = [
  {
    id: "it-objpreco",
    title: "Cliente diz que tá caro",
    when: "objeção de preço aparece em qualquer etapa",
    hue: 60,
    steps: [
      { q: "Cliente já fez orçamento com concorrente?",
        yes: 1,
        no:  { fix: "Pergunte de quanto é o orçamento dele. Sem comparação, é só percepção — explique custo de matéria-prima e prazo (sangria, acabamento, ICC). Ofereça opção mais simples (gramatura menor)." } },
      { q: "A diferença é maior que 20%?",
        yes: { fix: "Provavelmente é gráfica online em massa, sem acabamento profissional. Mostre que entrega no balcão, retoque grátis, atendimento humano. Não bate preço. Veja #a13 (roteiro de comunicação)." },
        no:  { fix: "Dá pra negociar até 10% à vista. Se passar disso, oferece parcelar no PIX em 2x. Anote a regra: nunca tomar a decisão sem o Wagner se passar de 15%." } },
    ]
  },
  {
    id: "it-cobranca",
    title: "Cliente com boleto atrasado pede pra produzir novo pedido",
    when: "tem saldo a receber e pediu orçamento novo",
    hue: 25,
    steps: [
      { q: "O atraso é maior que 15 dias?",
        yes: 1,
        no:  { fix: "Recebe o pedido, mas exige PIX antes de soltar produção. Lembra do boleto antigo no mesmo papo, sem culpar — 'aproveitando que entrou em contato, ainda temos aquele boleto da OS X'. Veja #a10." } },
      { q: "Cliente recorrente (LTV > R$ [redacted Tier 0]k)?",
        yes: { fix: "Tratamento VIP — Wagner negocia diretamente. Não bloquear nada antes dele decidir. Move conversa pra fila #q-fin com nota interna." },
        no:  { fix: "Cobrança em dia primeiro, produção depois. Sem exceção. Aplique macro /cobrar e mova pra fila #q-fin. Resposta padrão em #a10." } },
    ]
  },
  {
    id: "it-prazo",
    title: "Cliente pede prazo impossível",
    when: "cliente quer urgência fora do nosso SLA",
    hue: 280,
    steps: [
      { q: "É cliente fiel (já fez 3+ pedidos)?",
        yes: 1,
        no:  { fix: "Não prometa. Dê o prazo real + 1 dia de buffer. Cliente novo aceita melhor 'em 4 dias certo' do que 'em 3 dias talvez'." } },
      { q: "Tem material em estoque?",
        yes: { fix: "Topa fazer fora do SLA, mas cobra 25% de urgência. Wagner aprova por padrão. Move pra fila #q-prod com tag 'urgência'." },
        no:  { fix: "Bobina chega só amanhã. Seja honesto: 'pra essa medida o material chega quinta, posso entregar sexta 18h'. Cliente fiel respeita." } },
    ]
  },
];
window.INBOX_TROUBLES = INBOX_TROUBLES;

// ─────────────────────────────────────────────────────────────────
// TRILHAS DE ONBOARDING ATENDENTE
// ─────────────────────────────────────────────────────────────────
const INBOX_PATHS = [
  {
    id: "obd-atend-novo",
    title: "Onboarding · Atendente novo",
    audience: "primeira semana",
    desc: "8 passos pra atender sozinho com segurança.",
    hue: 220,
    steps: [
      { kind: "kb",     ref: "a7",  note: "Brief mínimo — 6 dados que toda OS precisa" },
      { kind: "kb",     ref: "a13", note: "Comunicar atraso sem queimar relacionamento" },
      { kind: "trouble",ref: "it-objpreco", note: "Como responder 'tá caro'" },
      { kind: "trouble",ref: "it-cobranca", note: "Cliente com boleto atrasado pedindo novo" },
      { kind: "trouble",ref: "it-prazo",    note: "Prazo impossível — quando aceitar com 25%" },
      { kind: "kb",     ref: "a11", note: "Atalhos do ERP" },
      { kind: "kb",     ref: "a5",  note: "Conferir PDF do cliente antes de produzir" },
      { kind: "kb",     ref: "a10", note: "Boleto Inter — quando vai e quando não vai" },
    ]
  },
  {
    id: "obd-cobranca",
    title: "Onboarding · Cobrança",
    audience: "atendente que vai cuidar de fila #q-fin",
    desc: "Roteiros e ferramentas de cobrança.",
    hue: 295,
    steps: [
      { kind: "kb",     ref: "a10", note: "Fluxo Inter completo" },
      { kind: "kb",     ref: "a9",  note: "Rejeições SEFAZ — quando culpa é nossa, quando é do cliente" },
      { kind: "trouble",ref: "it-cobranca", note: "Cliente atrasado pedindo nova produção" },
    ]
  },
];
window.INBOX_PATHS = INBOX_PATHS;

// ─────────────────────────────────────────────────────────────────
// DIALOG: Troubleshooter de Atendimento (reusa visual do KB)
// ─────────────────────────────────────────────────────────────────
function InboxTroubleDialog({ onPickArticle, onClose }) {
  const [activeId, setActiveId] = useState(null);
  const [step, setStep] = useState(0);
  const [fix, setFix] = useState(null);
  const [path, setPath] = useState([]);
  const active = INBOX_TROUBLES.find(t => t.id === activeId);

  const answer = (ans) => {
    const s = active.steps[step];
    const next = ans ? s.yes : s.no;
    setPath(p => [...p, { stepIdx: step, q: s.q, answer: ans }]);
    if (typeof next === "number") setStep(next);
    else setFix(next.fix);
  };
  const back = () => { setActiveId(null); setStep(0); setFix(null); setPath([]); };

  const linkify = (text) => {
    if (!text) return text;
    return window.linkifyMessage(text, {
      kb: (ref) => {
        try { localStorage.setItem("oimpresso.route", "kb"); window.location.reload(); } catch(e){}
      },
      queue: (ref) => onClose(),
    });
  };

  if (!active) {
    return (
      <React.Fragment>
        <div className="om-palette-back" onClick={onClose}/>
        <div className="om-ai-modal" role="dialog" style={{maxWidth:"640px", width:"min(640px, 94vw)"}}>
          <header className="om-ai-modal-h">
            <div>
              <small>Diagnóstico guiado</small>
              <h3>Troubleshooters de atendimento</h3>
            </div>
            <button className="om-x" onClick={onClose}>✕</button>
          </header>
          <div className="om-ai-modal-body" style={{gap:8}}>
            {INBOX_TROUBLES.map(t => (
              <button key={t.id} className="om-tb-card" onClick={() => setActiveId(t.id)}
                      style={{borderLeftColor: `oklch(0.55 0.13 ${t.hue})`}}>
                <small style={{color: `oklch(0.42 0.13 ${t.hue})`}}>atendimento</small>
                <h4>{t.title}</h4>
                <p>Use quando {t.when}.</p>
                <span className="om-tb-card-n">{t.steps.length} perguntas</span>
              </button>
            ))}
          </div>
        </div>
      </React.Fragment>
    );
  }

  const current = active.steps[step];
  return (
    <React.Fragment>
      <div className="om-palette-back" onClick={onClose}/>
      <div className="om-ai-modal" role="dialog" style={{width:"min(540px, 94vw)"}}>
        <header className="om-ai-modal-h">
          <div>
            <small>
              <button className="om-link-btn" onClick={back}>‹ Troubleshooters</button>
              {" · objeção"}
            </small>
            <h3>{active.title}</h3>
          </div>
          <button className="om-x" onClick={onClose}>✕</button>
        </header>
        <div className="om-ai-modal-body">
          {path.length > 0 && (
            <div className="om-tb-history">
              {path.map((p, i) => (
                <div key={i} className="om-tb-history-row">
                  <span>{p.q}</span>
                  <span className={"om-tb-ans " + (p.answer ? "yes" : "no")}>{p.answer ? "Sim" : "Não"}</span>
                </div>
              ))}
            </div>
          )}
          {!fix ? (
            <React.Fragment>
              <div className="om-tb-step">
                <span className="om-tb-n" style={{background: `oklch(0.94 0.06 ${active.hue})`, color: `oklch(0.36 0.13 ${active.hue})`}}>
                  {path.length + 1}
                </span>
                <p>{current.q}</p>
              </div>
              <div className="om-tb-actions">
                <button className="om-tb-yes" onClick={() => answer(true)}>Sim</button>
                <button className="om-tb-no"  onClick={() => answer(false)}>Não</button>
              </div>
            </React.Fragment>
          ) : (
            <React.Fragment>
              <div className="om-tb-fix" style={{background: `oklch(0.97 0.025 ${active.hue})`, borderColor: `oklch(0.86 0.06 ${active.hue})`}}>
                <small style={{color: `oklch(0.42 0.13 ${active.hue})`}}>Solução sugerida</small>
                <p>{linkify(fix)}</p>
              </div>
              <div className="om-tb-actions">
                <button className="os-btn ghost" onClick={() => { setStep(0); setFix(null); setPath([]); }}>Recomeçar</button>
                <button className="os-btn ghost" onClick={back}>Outro</button>
                <button className="os-btn primary" onClick={onClose}>Resolvi</button>
              </div>
            </React.Fragment>
          )}
        </div>
      </div>
    </React.Fragment>
  );
}
window.InboxTroubleDialog = InboxTroubleDialog;

// ─────────────────────────────────────────────────────────────────
// DIALOG: Trilhas de Onboarding (reuso pattern)
// ─────────────────────────────────────────────────────────────────
function InboxPathsDialog({ onPickKB, onPickTrouble, onClose }) {
  const [active, setActive] = useState(null);
  const [progress, setProgress] = useState(() => {
    try { return JSON.parse(localStorage.getItem("oimpresso.inbox.paths") || "{}"); }
    catch (e) { return {}; }
  });
  useEffect(() => {
    try { localStorage.setItem("oimpresso.inbox.paths", JSON.stringify(progress)); } catch (e) {}
  }, [progress]);

  const toggle = (pathId, idx) => {
    setProgress(p => {
      const cur = p[pathId] || {};
      return { ...p, [pathId]: { ...cur, [idx]: !cur[idx] } };
    });
  };
  const pathProg = (path) => {
    const done = (progress[path.id] || {});
    const c = Object.values(done).filter(Boolean).length;
    return { done: c, total: path.steps.length, pct: Math.round((c / path.steps.length) * 100) };
  };

  if (active) {
    const path = INBOX_PATHS.find(p => p.id === active);
    const done = progress[path.id] || {};
    const p = pathProg(path);
    return (
      <React.Fragment>
        <div className="om-palette-back" onClick={onClose}/>
        <aside className="om-paths-drawer">
          <header className="om-paths-h">
            <button className="om-link-btn" onClick={() => setActive(null)}>‹ Trilhas</button>
            <button className="om-x" onClick={onClose}>✕</button>
          </header>
          <div className="om-paths-hero" style={{background: `oklch(0.96 0.04 ${path.hue})`}}>
            <small style={{color: `oklch(0.36 0.13 ${path.hue})`}}>Trilha · {path.audience}</small>
            <h2>{path.title}</h2>
            <p>{path.desc}</p>
            <div className="om-paths-pbar-row">
              <div className="om-paths-pbar"><div className="om-paths-pfill" style={{width: p.pct + "%", background: `oklch(0.52 0.13 ${path.hue})`}}/></div>
              <span className="mono">{p.done}/{p.total} · {p.pct}%</span>
            </div>
          </div>
          <ol className="om-paths-steps">
            {path.steps.map((step, i) => {
              const ok = !!done[i];
              return (
                <li key={i} className={ok ? "done" : ""}>
                  <button className="om-paths-check" onClick={() => toggle(path.id, i)} aria-pressed={ok}>
                    {ok ? "✓" : (i + 1)}
                  </button>
                  <div className="om-paths-step-c">
                    <button className="om-paths-step-title" onClick={() => {
                      if (step.kind === "kb") onPickKB(step.ref);
                      else if (step.kind === "trouble") onPickTrouble(step.ref);
                    }}>
                      <b>{step.note}</b>
                      <span className="om-paths-step-tag">{step.kind === "kb" ? "artigo KB" : "troubleshoot"}</span>
                    </button>
                    <small className="mono">#{step.ref}</small>
                  </div>
                </li>
              );
            })}
          </ol>
        </aside>
      </React.Fragment>
    );
  }

  return (
    <React.Fragment>
      <div className="om-palette-back" onClick={onClose}/>
      <aside className="om-paths-drawer">
        <header className="om-paths-h">
          <div>
            <small>Aprendizado guiado</small>
            <h3>Trilhas — Caixa unificada</h3>
          </div>
          <button className="om-x" onClick={onClose}>✕</button>
        </header>
        <div className="om-paths-list">
          {INBOX_PATHS.map(path => {
            const p = pathProg(path);
            return (
              <button key={path.id} className="om-paths-card" onClick={() => setActive(path.id)}
                      style={{borderLeftColor: `oklch(0.52 0.13 ${path.hue})`}}>
                <small style={{color: `oklch(0.42 0.13 ${path.hue})`}}>{path.audience}</small>
                <h4>{path.title}</h4>
                <p>{path.desc}</p>
                <div className="om-paths-pbar-row">
                  <div className="om-paths-pbar"><div className="om-paths-pfill" style={{width: p.pct + "%", background: `oklch(0.52 0.13 ${path.hue})`}}/></div>
                  <span className="mono">{p.done}/{p.total}</span>
                </div>
              </button>
            );
          })}
        </div>
      </aside>
    </React.Fragment>
  );
}
window.InboxPathsDialog = InboxPathsDialog;

})();
