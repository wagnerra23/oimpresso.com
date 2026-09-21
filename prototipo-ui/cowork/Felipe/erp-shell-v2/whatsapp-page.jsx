// whatsapp-page.jsx — Atendimento via WhatsApp Cloud API integrado ao ERP.
// Lista de conversas + thread + contexto (OS vinculada, saldo, LTV).
(() => {
const { useState, useMemo, useRef, useEffect } = React;

const CONVS_INIT = [
  { id: "c1", av: "RL", avc: 145, name: "Renato Lopes", company: "Padaria Estrela",
    lastFrom: "them", preview: "Posso retirar amanhã 9h?", unread: 2, online: true,
    ctx: { os: "#4819 · Cardápios A4", saldo: "R$ 380 a receber", history: "4 pedidos · R$ 1.420 LTV", lastTouch: "11:48 hoje" },
    msgs: [
      { d: "ontem", who: "them", t: "Boa tarde! Os cardápios ficaram prontos?", time: "16:20" },
      { d: "ontem", who: "me",   t: "Sim Renato! Estão prontos. Pode passar quando quiser.", time: "16:25" },
      { d: "hoje",  who: "them", t: "Posso passar pra retirar amanhã 9h?", time: "11:48" },
    ]},
  { id: "c2", av: "CD", avc: 30, name: "Camila Diniz", company: "Acme Comércio Ltda",
    lastFrom: "me", preview: "Você: arte aprovada ✓", unread: 0,
    ctx: { os: "#4821 · Banner 3×2m", saldo: "R$ 0 (faturado)", history: "12 pedidos · R$ 8.420 LTV", lastTouch: "10:32 hoje" },
    msgs: [
      { d: "hoje", who: "them", t: "Recebi a prova, mas o azul tá meio escuro. Pode ajustar?", time: "09:14" },
      { d: "hoje", who: "me",   t: "Pode deixar Camila, já mando a v2.", time: "09:20" },
      { d: "hoje", who: "me",   t: "Arte aprovada ✓", time: "10:32" },
    ]},
  { id: "c3", av: "DV", avc: 220, name: "Diego Vasconcellos", company: "TechPro",
    lastFrom: "them", preview: "Quanto fica em 200un?", unread: 1,
    ctx: { os: null, saldo: "R$ 1.840 a receber", history: "3 pedidos · R$ 4.200 LTV", lastTouch: "13:05 hoje" },
    msgs: [
      { d: "hoje", who: "them", t: "Olá! Adesivos novos, 8×8cm recortado.", time: "12:50" },
      { d: "hoje", who: "them", t: "Quanto fica em 200un?", time: "13:05" },
    ]},
  { id: "c4", av: "MV", avc: 295, name: "Marcos Vital", company: "Posto BR Centro",
    lastFrom: "them", preview: "Obrigado! Recebi.", unread: 0,
    ctx: { os: "#4790 · Lona Front-Light", saldo: "R$ 5.620", history: "1 pedido · R$ 5.620 LTV", lastTouch: "ontem 17:48" },
    msgs: [
      { d: "ontem", who: "me",   t: "Marcos, peça pronta e nota emitida.", time: "17:42" },
      { d: "ontem", who: "them", t: "Obrigado! Recebi.", time: "17:48" },
    ]},
  { id: "c5", av: "JL", avc: 60, name: "Joana Lima", company: "Designer interna",
    lastFrom: "them", preview: "Boa tarde, posso ver?", unread: 0,
    ctx: { os: null, saldo: "—", history: "interno", lastTouch: "hoje 14:02" },
    msgs: [
      { d: "hoje", who: "them", t: "Boa tarde, posso ver a v3 do banner Acme?", time: "14:02" },
    ]},
];

const TEMPLATES = [
  { id: "ok",   label: "✓ Pronto pra retirada", body: "Olá! Seu pedido está pronto. Funcionamos de 9h às 18h. 🙌" },
  { id: "art",  label: "🎨 Aprovação de arte",  body: "Segue a arte para aprovação. Responda OK ou indique ajustes." },
  { id: "pay",  label: "💰 Lembrete cobrança",  body: "Olá! Lembramos que o boleto vence em breve. Pode pagar via PIX ou boleto." },
  { id: "ride", label: "🚚 Saiu pra entrega",   body: "Seu pedido saiu para entrega agora e chega ainda hoje 👌" },
];

function WhatsappPage() {
  const [convs, setConvs] = useState(CONVS_INIT);
  const [selId, setSelId] = useState("c1");
  const [draft, setDraft] = useState("");
  const [showTpl, setShowTpl] = useState(false);
  const [bcastOpen, setBcastOpen] = useState(false);
  const [toast, setToast] = useState(null);
  const threadRef = useRef(null);

  useEffect(() => { if (!toast) return; const t = setTimeout(() => setToast(null), 2400); return () => clearTimeout(t); }, [toast]);

  const conv = convs.find(c => c.id === selId);

  // Auto-scroll thread to bottom when conv or msgs change
  useEffect(() => {
    if (threadRef.current) threadRef.current.scrollTop = threadRef.current.scrollHeight;
  }, [selId, conv?.msgs.length]);

  // Marca como lido ao abrir
  useEffect(() => {
    if (conv && conv.unread > 0) {
      setConvs(cs => cs.map(c => c.id === selId ? { ...c, unread: 0 } : c));
    }
  }, [selId]);

  const totalUnread = convs.reduce((s, c) => s + c.unread, 0);

  const sendMsg = (text) => {
    const t = (text || draft).trim();
    if (!t) return;
    setConvs(cs => cs.map(c => c.id !== selId ? c : {
      ...c,
      msgs: [...c.msgs, { d: "hoje", who: "me", t: t, time: "agora" }],
      lastFrom: "me",
      preview: "Você: " + t,
    }));
    setDraft("");
    setShowTpl(false);
  };

  const resolve = () => {
    setConvs(cs => cs.filter(c => c.id !== selId));
    setToast(`Conversa com ${conv.name} marcada como resolvida`);
    const next = convs.find(c => c.id !== selId);
    if (next) setSelId(next.id);
  };

  return (
    <div className="os-page wa-page" data-screen-label="01 WhatsApp">
      <div className="os-page-h">
        <div className="os-page-h-l">
          <h1>WhatsApp</h1>
          <p>Comercial · Cloud API · {convs.length} abertas{totalUnread > 0 ? ` · ${totalUnread} não lidas` : ""}</p>
        </div>
        <div className="os-page-h-r">
          <button className="os-btn ghost" onClick={() => setShowTpl(v => !v)}>Templates</button>
          <button className="os-btn ghost" onClick={() => setBcastOpen(true)}>Broadcast</button>
          <button className="os-btn primary">+ Nova conversa</button>
        </div>
      </div>

      <div className="wa-shell">
        {/* Lista de conversas */}
        <aside className="wa-list-c">
          <div className="wa-list-h">
            <b>Conversas</b>
            <span className="mono">{convs.length}</span>
          </div>
          <ul className="wa-list">
            {convs.map(c => (
              <li key={c.id} className={selId === c.id ? "sel" : ""} onClick={() => setSelId(c.id)}>
                <span className="wa-av" style={{ background: `oklch(0.60 0.12 ${c.avc})` }}>
                  {c.av}
                  {c.online && <span className="wa-online"/>}
                </span>
                <div className="wa-list-text">
                  <b>{c.name}</b>
                  <small>{c.preview}</small>
                </div>
                {c.unread > 0 && <span className="wa-un">{c.unread}</span>}
              </li>
            ))}
          </ul>
        </aside>

        {/* Thread */}
        <main className="wa-thread-c">
          {!conv ? (
            <div className="wa-empty">Selecione uma conversa.</div>
          ) : (
            <>
              <header className="wa-thread-h">
                <span className="wa-av sm" style={{ background: `oklch(0.60 0.12 ${conv.avc})` }}>{conv.av}</span>
                <div>
                  <b>{conv.name}</b>
                  <small>{conv.company} · {conv.online ? "online" : conv.ctx.lastTouch}</small>
                </div>
                <button className="os-btn ghost" onClick={resolve} style={{ marginLeft: "auto" }}>✓ Resolver</button>
              </header>

              <div className="wa-msgs" ref={threadRef}>
                {conv.msgs.map((m, i) => {
                  const showDay = i === 0 || conv.msgs[i-1].d !== m.d;
                  return (
                    <React.Fragment key={i}>
                      {showDay && <div className="wa-day-sep"><span>{m.d === "hoje" ? "Hoje" : m.d === "ontem" ? "Ontem" : m.d}</span></div>}
                      <div className={"wa-bub " + (m.who === "me" ? "me" : "them")}>
                        <span>{m.t}</span>
                        <small>{m.time}{m.who === "me" ? " ✓✓" : ""}</small>
                      </div>
                    </React.Fragment>
                  );
                })}
              </div>

              {showTpl && (
                <div className="wa-tpl">
                  <small>Templates · Cloud API</small>
                  {TEMPLATES.map(t => (
                    <button key={t.id} onClick={() => sendMsg(t.body)}>
                      <b>{t.label}</b>
                      <em>{t.body}</em>
                    </button>
                  ))}
                </div>
              )}

              <div className="wa-input">
                <button className="wa-icon-btn" onClick={() => setShowTpl(v => !v)} title="Templates">📋</button>
                <input
                  value={draft}
                  onChange={e => setDraft(e.target.value)}
                  onKeyDown={e => { if (e.key === "Enter" && !e.shiftKey) { e.preventDefault(); sendMsg(); } }}
                  placeholder="Mensagem ou /comando…"/>
                <button className="os-btn primary" onClick={() => sendMsg()} disabled={!draft.trim()}>Enviar</button>
              </div>
            </>
          )}
        </main>

        {/* Contexto */}
        {conv && (
          <aside className="wa-ctx">
            <div className="wa-list-h"><b>Contexto</b></div>
            <div className="wa-ctx-body">
              {conv.ctx.os && (
                <div className="wa-kv">
                  <small>OS vinculada</small>
                  <b>{conv.ctx.os}</b>
                  <button className="os-btn sm" style={{ marginTop: 6 }}>Abrir OS</button>
                </div>
              )}
              <div className="wa-kv"><small>Saldo cliente</small><b>{conv.ctx.saldo}</b></div>
              <div className="wa-kv"><small>Histórico</small><b>{conv.ctx.history}</b></div>
              <div className="wa-kv"><small>Último contato</small><b>{conv.ctx.lastTouch}</b></div>
              <div className="wa-actions">
                <button className="os-btn sm">📄 Emitir cobrança</button>
                <button className="os-btn sm">🎨 Enviar arte</button>
                <button className="os-btn sm">📞 Ligar</button>
              </div>
            </div>
          </aside>
        )}
      </div>

      {toast && <div className="wa-toast">✓ {toast}</div>}

      {bcastOpen && (
        <>
          <div className="wa-backdrop" onClick={() => setBcastOpen(false)}/>
          <aside className="wa-drawer">
            <header className="wa-drawer-h">
              <div><h2>Broadcast</h2><p>Envia para todos os {convs.length} contatos abertos</p></div>
              <button className="wa-x" onClick={() => setBcastOpen(false)}>✕</button>
            </header>
            <div className="wa-drawer-body">
              <label><small>Template</small></label>
              <select className="wa-select">{TEMPLATES.map(t => <option key={t.id}>{t.label}</option>)}</select>
              <label style={{marginTop: 14}}><small>Mensagem</small></label>
              <textarea rows={5} defaultValue={TEMPLATES[0].body}/>
              <p style={{fontSize:11, color:"var(--text-mute)", margin:"8px 0 14px"}}>Será disparado para {convs.length} conversas · respeitando janela de 24h Cloud API.</p>
              <button className="os-btn primary" onClick={() => { setBcastOpen(false); setToast(`Broadcast disparado para ${convs.length} contatos`); }}>Disparar broadcast</button>
            </div>
          </aside>
        </>
      )}
    </div>
  );
}

window.WhatsappPage = WhatsappPage;
})();
