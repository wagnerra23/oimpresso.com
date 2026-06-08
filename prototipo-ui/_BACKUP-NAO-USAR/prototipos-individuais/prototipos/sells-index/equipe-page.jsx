// equipe-page.jsx — Comunicação interna da equipe Oimpresso.
// 2-col: canais (#) + DMs à esquerda, thread à direita.
// NÃO confundir com Caixa unificada (clientes). Aqui é só time interno.
(() => {
const { useState, useMemo, useRef, useEffect } = React;

// ─── Membros da equipe ─────────────────────────────────────────────────
const MEMBERS = {
  wagner:  { id: "wagner",  name: "Wagner Ra.",  role: "Diretor",          init: "WR", avc: 220, status: "online" },
  larissa: { id: "larissa", name: "Larissa M.",  role: "Balcão · ROTA LIVRE", init: "LM", avc: 30,  status: "online" },
  felipe:  { id: "felipe",  name: "Felipe O.",   role: "Produção",        init: "FO", avc: 60,  status: "online" },
  maira:   { id: "maira",   name: "Maíra S.",    role: "Acabamento",      init: "MS", avc: 350, status: "away"   },
  luiz:    { id: "luiz",    name: "Luiz P.",     role: "Motoboy",         init: "LP", avc: 95,  status: "online" },
  eliana:  { id: "eliana",  name: "Eliana C.",   role: "Financeiro",      init: "EC", avc: 280, status: "offline"},
};
const ME = "wagner";

// ─── Canais e DMs ──────────────────────────────────────────────────────
const CHANNELS_INIT = [
  { id: "geral", kind: "channel", name: "geral",       desc: "todos · avisos gerais",        members: 6, unread: 0, lastTouch: "ontem 18:02",
    preview: "Eliana: fechamento de maio amanhã 14h",
    msgs: [
      { d: "ontem", who: "eliana",  t: "Pessoal, fechamento de maio amanhã 14h. Quem tiver lançamento atrasado fala até hoje.", time: "17:50" },
      { d: "ontem", who: "felipe",  t: "Beleza. Mando a planilha da oficina ainda hoje.", time: "18:02" },
      { d: "hoje",  who: "larissa", t: "Bom dia 🌞", time: "08:01" },
    ]},
  { id: "producao", kind: "channel", name: "producao",   desc: "Felipe + Maíra · fila + acabamento", members: 3, unread: 3, lastTouch: "11:42 hoje",
    preview: "Felipe: lona Acme atrasou no Roland",
    msgs: [
      { d: "hoje", who: "felipe", t: "Lona Acme atrasou no Roland — bico entupido, limpando agora.", time: "10:18" },
      { d: "hoje", who: "maira",  t: "Quanto tempo? Tenho corte de adesivo na fila depois.", time: "10:24" },
      { d: "hoje", who: "felipe", t: "30min. Pode adiantar o corte que eu retomo direto.", time: "10:26" },
      { d: "hoje", who: "wagner", t: "Cliente já avisado do atraso?", time: "11:40" },
      { d: "hoje", who: "felipe", t: "Avisando agora pelo WhatsApp.", time: "11:42" },
    ]},
  { id: "financeiro", kind: "channel", name: "financeiro", desc: "Eliana + Wagner · contas + boletos", members: 2, unread: 1, lastTouch: "09:14 hoje",
    preview: "Eliana: Inter caiu — boletos emitindo manual",
    msgs: [
      { d: "hoje", who: "eliana", t: "Galera, Inter caiu agora cedo. Estou emitindo boletos manuais até voltar. Quem precisar avisa.", time: "09:14" },
    ]},
  { id: "vendas", kind: "channel", name: "vendas",      desc: "Larissa + Wagner · pipeline + balcão", members: 2, unread: 0, lastTouch: "ontem 16:30",
    preview: "Wagner: orçamento Diego Vasc. aprovado",
    msgs: [
      { d: "ontem", who: "wagner",  t: "Orçamento do Diego (TechPro) aprovado — 200 adesivos. Já mandei pra produção.", time: "16:25" },
      { d: "ontem", who: "larissa", t: "Beleza. Aviso quando ficar pronto pra ele retirar.", time: "16:30" },
    ]},
  { id: "motoboy", kind: "channel", name: "motoboy",     desc: "Luiz · entregas do dia",      members: 3, unread: 0, lastTouch: "ontem 17:40",
    preview: "Luiz: 4 entregas amanhã confirmadas",
    msgs: [
      { d: "ontem", who: "luiz", t: "4 entregas amanhã confirmadas: Acme, Padaria Estrela, Posto BR, Studio Foco. Saio 9h.", time: "17:38" },
      { d: "ontem", who: "wagner", t: "Top. Boa rota.", time: "17:40" },
    ]},
];

const DMS_INIT = [
  { id: "dm-larissa", kind: "dm", with: "larissa", unread: 1, lastTouch: "11:55 hoje",
    preview: "Larissa: cliente perguntou se abre sábado",
    msgs: [
      { d: "hoje", who: "larissa", t: "Wagner, cliente aqui no balcão perguntando se abre sábado dia 17.", time: "11:54" },
      { d: "hoje", who: "larissa", t: "Posso confirmar ou prefere ver?", time: "11:55" },
    ]},
  { id: "dm-felipe", kind: "dm", with: "felipe", unread: 0, lastTouch: "10:20 hoje",
    preview: "Felipe: tinta amarela chegando 2ª",
    msgs: [
      { d: "ontem", who: "wagner", t: "Felipe, conseguiu ver a tinta amarela do Roland?", time: "16:00" },
      { d: "hoje",  who: "felipe", t: "Sim, comprei 2 cartuchos. Chegam segunda.", time: "10:20" },
    ]},
  { id: "dm-maira", kind: "dm", with: "maira", unread: 0, lastTouch: "ontem 15:10",
    preview: "Você: tudo ok",
    msgs: [
      { d: "ontem", who: "maira",  t: "Saio 16h hoje, dentista. Ok?", time: "15:08" },
      { d: "ontem", who: "wagner", t: "Tudo ok", time: "15:10" },
    ]},
  { id: "dm-eliana", kind: "dm", with: "eliana", unread: 2, lastTouch: "09:30 hoje",
    preview: "Eliana: imposto ISS — preciso falar",
    msgs: [
      { d: "hoje", who: "eliana", t: "Wagner, preciso falar com você sobre o ISS de maio.", time: "09:28" },
      { d: "hoje", who: "eliana", t: "Tem 15min hoje?", time: "09:30" },
    ]},
  { id: "dm-luiz", kind: "dm", with: "luiz", unread: 0, lastTouch: "ontem 17:42",
    preview: "Luiz: ✓",
    msgs: [
      { d: "ontem", who: "wagner", t: "Luiz, amanhã antes do giro, passa no Inter buscar talão.", time: "17:40" },
      { d: "ontem", who: "luiz",   t: "✓", time: "17:42" },
    ]},
];

function EquipePage() {
  const [channels, setChannels] = useState(CHANNELS_INIT);
  const [dms, setDms] = useState(DMS_INIT);
  const [selId, setSelId] = useState("producao");
  const [draft, setDraft] = useState("");
  const threadRef = useRef(null);

  const allConvs = useMemo(() => [...channels, ...dms], [channels, dms]);
  const conv = allConvs.find(c => c.id === selId);
  const dmMember = conv?.kind === "dm" ? MEMBERS[conv.with] : null;
  const totalUnread = allConvs.reduce((s, c) => s + c.unread, 0);
  const onlineCount = Object.values(MEMBERS).filter(m => m.status === "online").length;

  // Auto-scroll
  useEffect(() => {
    if (threadRef.current) threadRef.current.scrollTop = threadRef.current.scrollHeight;
  }, [selId, conv?.msgs.length]);

  // Marca como lido
  useEffect(() => {
    if (!conv || !conv.unread) return;
    const updater = (list) => list.map(c => c.id === selId ? { ...c, unread: 0 } : c);
    if (conv.kind === "channel") setChannels(updater);
    else setDms(updater);
  }, [selId]);

  const sendMsg = () => {
    const t = draft.trim();
    if (!t || !conv) return;
    const updater = (list) => list.map(c => c.id !== selId ? c : {
      ...c,
      msgs: [...c.msgs, { d: "hoje", who: ME, t, time: "agora" }],
      lastFrom: ME,
      preview: "Você: " + t,
    });
    if (conv.kind === "channel") setChannels(updater);
    else setDms(updater);
    setDraft("");
  };

  const renderListItem = (c) => {
    const isDM = c.kind === "dm";
    const m = isDM ? MEMBERS[c.with] : null;
    return (
      <li key={c.id} className={selId === c.id ? "sel" : ""} onClick={() => setSelId(c.id)}>
        {isDM ? (
          <span className="eq-av-wrap">
            <span className="eq-av" style={{ background: `oklch(0.60 0.12 ${m.avc})` }}>{m.init}</span>
            {m.status === "online" && <span className="eq-status on"/>}
            {m.status === "away"   && <span className="eq-status away"/>}
          </span>
        ) : (
          <span className="eq-hash">#</span>
        )}
        <div className="eq-list-text">
          <b>{isDM ? m.name : c.name}</b>
          <small>{c.preview}</small>
        </div>
        {c.unread > 0 && <span className="eq-un">{c.unread}</span>}
      </li>
    );
  };

  return (
    <div className="os-page eq-page" data-screen-label="01 Equipe">
      <div className="os-page-h">
        <div className="os-page-h-l">
          <h1>Equipe</h1>
          <p>{Object.keys(MEMBERS).length} pessoas · {onlineCount} online · {channels.length} canais · {totalUnread > 0 ? `${totalUnread} não lidas` : "tudo em dia"}</p>
        </div>
        <div className="os-page-h-r">
          <button className="os-btn ghost">Canais</button>
          <button className="os-btn ghost">Equipe</button>
          <button className="os-btn primary">+ Mensagem</button>
        </div>
      </div>

      <div className="eq-shell">
        <aside className="eq-list-c">
          <div className="eq-section-h">
            <span>Canais</span>
            <em className="mono">{channels.length}</em>
          </div>
          <ul className="eq-list">{channels.map(renderListItem)}</ul>
          <div className="eq-section-h">
            <span>Mensagens diretas</span>
            <em className="mono">{dms.length}</em>
          </div>
          <ul className="eq-list">{dms.map(renderListItem)}</ul>
        </aside>

        <main className="eq-thread-c">
          {!conv ? (
            <div className="eq-empty">Selecione um canal ou pessoa.</div>
          ) : (
            <>
              <header className="eq-thread-h">
                {conv.kind === "channel" ? (
                  <>
                    <span className="eq-hash lg">#</span>
                    <div className="eq-thread-h-text">
                      <b>{conv.name}</b>
                      <small>{conv.desc} · {conv.members} membros</small>
                    </div>
                  </>
                ) : (
                  <>
                    <span className="eq-av-wrap sm">
                      <span className="eq-av sm" style={{ background: `oklch(0.60 0.12 ${dmMember.avc})` }}>{dmMember.init}</span>
                      {dmMember.status === "online" && <span className="eq-status on sm"/>}
                    </span>
                    <div className="eq-thread-h-text">
                      <b>{dmMember.name}</b>
                      <small>{dmMember.role} · {dmMember.status === "online" ? "online" : dmMember.status === "away" ? "ausente" : "offline"}</small>
                    </div>
                  </>
                )}
                <button className="os-btn ghost" style={{ marginLeft: "auto" }}>Detalhes</button>
              </header>

              <div className="eq-msgs" ref={threadRef}>
                {conv.msgs.map((m, i) => {
                  const showDay = i === 0 || conv.msgs[i-1].d !== m.d;
                  const author = MEMBERS[m.who];
                  const isMe = m.who === ME;
                  const prev = i > 0 ? conv.msgs[i-1] : null;
                  const sameAuthor = prev && prev.who === m.who && prev.d === m.d;
                  return (
                    <React.Fragment key={i}>
                      {showDay && <div className="eq-day-sep"><span>{m.d === "hoje" ? "Hoje" : m.d === "ontem" ? "Ontem" : m.d}</span></div>}
                      <div className={"eq-msg " + (sameAuthor ? "stacked " : "") + (isMe ? "me" : "")}>
                        {!sameAuthor && (
                          <span className="eq-msg-av" style={{ background: `oklch(0.60 0.12 ${author.avc})` }}>{author.init}</span>
                        )}
                        <div className="eq-msg-body">
                          {!sameAuthor && (
                            <div className="eq-msg-meta">
                              <b>{isMe ? "Você" : author.name}</b>
                              <small>{m.time}</small>
                            </div>
                          )}
                          <div className="eq-msg-t">{m.t}</div>
                        </div>
                      </div>
                    </React.Fragment>
                  );
                })}
              </div>

              <div className="eq-input">
                <input
                  value={draft}
                  onChange={e => setDraft(e.target.value)}
                  onKeyDown={e => { if (e.key === "Enter" && !e.shiftKey) { e.preventDefault(); sendMsg(); } }}
                  placeholder={conv.kind === "channel" ? `Mensagem em #${conv.name}` : `Mensagem para ${dmMember.name}`}/>
                <button className="os-btn primary" onClick={sendMsg} disabled={!draft.trim()}>Enviar</button>
              </div>
              <div className="eq-input-hint">
                <span>Use <b className="mono">@nome</b> pra mencionar · <b className="mono">⏎</b> envia · <b className="mono">⇧⏎</b> quebra linha</span>
              </div>
            </>
          )}
        </main>
      </div>
    </div>
  );
}

window.EquipePage = EquipePage;
})();
