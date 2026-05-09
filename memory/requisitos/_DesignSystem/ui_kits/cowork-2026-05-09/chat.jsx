// Lista de conversas + Thread + Composer
const { useState: useState2, useRef: useRef2, useEffect: useEffect2, useMemo } = React;

function ConvList({ conversations, activeId, onSelect, query, setQuery, tab, setTab }) {
  const filtered = useMemo(() => {
    const q = query.trim().toLowerCase();
    let list = conversations;
    if (tab !== 'todas') list = list.filter(c => c.kind === tab || (tab === 'fixadas' && c.pinned));
    if (q) list = list.filter(c => c.title.toLowerCase().includes(q) || c.preview.toLowerCase().includes(q));
    return list;
  }, [conversations, query, tab]);

  const pinned = filtered.filter(c => c.pinned);
  const rest = filtered.filter(c => !c.pinned);

  return (
    <section className="list">
      <div className="list-h">
        <h2>Chat</h2>
        <button className="icon-btn" title="Filtros"><I.inbox size={14}/></button>
        <button className="icon-btn primary" title="Nova conversa"><I.plus size={14}/></button>
      </div>
      <div className="search">
        <I.search className="ic"/>
        <input placeholder="Buscar conversas, OS, clientes..." value={query} onChange={e => setQuery(e.target.value)}/>
        <span className="kbd">⌘K</span>
      </div>
      <div className="list-tabs">
        {[
          {id:'todas', label:'Todas'},
          {id:'os',    label:'OS'},
          {id:'team',  label:'Equipes'},
          {id:'client',label:'Clientes'},
        ].map(t => (
          <span key={t.id} className={"list-tab" + (tab===t.id?" active":"")} onClick={() => setTab(t.id)}>{t.label}</span>
        ))}
      </div>
      <div className="list-body">
        {pinned.length > 0 && <div className="list-group-h"><I.pin size={11}/> Fixadas</div>}
        {pinned.map(c => <ConvItem key={c.id} c={c} active={c.id===activeId} onSelect={onSelect}/>)}
        {rest.length > 0 && <div className="list-group-h">Recentes</div>}
        {rest.map(c => <ConvItem key={c.id} c={c} active={c.id===activeId} onSelect={onSelect}/>)}
        {filtered.length === 0 && (
          <div style={{padding:'40px 16px',textAlign:'center',color:'var(--text-mute)',fontSize:12}}>
            Nenhuma conversa encontrada.
          </div>
        )}
      </div>
    </section>
  );
}

function ConvItem({ c, active, onSelect }) {
  const tagCls = c.kind === 'os' ? 'tag os' : c.kind === 'team' ? 'tag eq' : 'tag';
  return (
    <div className={"conv" + (active?" active":"")} onClick={() => onSelect(c.id)}>
      <div className={`av ${c.grad} ${c.online?'dot':''}`}>{c.av}</div>
      <div className="top">
        <b>{c.title}</b>
        <time>{c.time}</time>
      </div>
      <div className="preview">
        <span className={tagCls}>{c.tag}</span>{c.preview}
      </div>
      {c.unread > 0 && <span className="badge">{c.unread}</span>}
    </div>
  );
}

function Thread({ conv, onSend }) {
  const [text, setText] = useState2("");
  const [typing, setTyping] = useState2(false);
  const taRef = useRef2(null);
  const scrollRef = useRef2(null);

  useEffect2(() => {
    if (scrollRef.current) scrollRef.current.scrollTop = scrollRef.current.scrollHeight;
  }, [conv?.msgs?.length, typing]);

  useEffect2(() => {
    if (!taRef.current) return;
    taRef.current.style.height = 'auto';
    taRef.current.style.height = Math.min(taRef.current.scrollHeight, 160) + 'px';
  }, [text]);

  if (!conv) {
    return (
      <section className="thread">
        <div className="empty">
          <div>
            <div className="ico"><I.chat size={22}/></div>
            <div>Selecione uma conversa para começar</div>
            <div style={{fontSize:11.5, marginTop:6, color:'var(--text-mute)'}}>
              Pressione <kbd style={{fontFamily:'var(--font-mono)',fontSize:11}}>⌘K</kbd> para buscar
            </div>
          </div>
        </div>
      </section>
    );
  }

  const handleSend = () => {
    const t = text.trim();
    if (!t) return;
    onSend(t);
    setText("");
    // simula resposta
    setTimeout(() => setTyping(true), 600);
    setTimeout(() => {
      setTyping(false);
      onSend(null, { who: conv.msgs[0]?.who || "Equipe", grad: conv.msgs[0]?.grad || "av-1", t: "Recebido, vou verificar e te respondo já já 👍", side: "them" });
    }, 2400);
  };

  const onKey = (e) => {
    if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); handleSend(); }
  };

  // group consecutive messages by author
  const rows = conv.msgs.map((m, i) => {
    const prev = conv.msgs[i-1];
    const continued = prev && prev.side === m.side && prev.who === m.who && !m.note;
    const showDay = !prev || prev.d !== m.d;
    return { ...m, continued, showDay, _i: i };
  });

  return (
    <section className="thread">
      <header className="th-head">
        <div className={`av ${conv.grad}`}>{conv.av}</div>
        <div className="who">
          <b>{conv.title}</b>
          <small>
            {conv.online && <span className="dot"/>}
            {conv.kind === 'os' ? `OS ${conv.os} · ${conv.client}` :
             conv.kind === 'team' ? 'Canal interno da equipe' :
             conv.client || 'Cliente'}
          </small>
        </div>
        <div className="actions">
          <button className="icon-btn" title="Ligar"><I.phone size={14}/></button>
          <button className="icon-btn" title="Detalhes"><I.info size={14}/></button>
          <button className="icon-btn" title="Mais"><I.more size={14}/></button>
        </div>
      </header>

      {(conv.os || conv.stage) && (
        <div className="th-context">
          {conv.os && <span>OS <span className="pill">{conv.os}</span></span>}
          {conv.client && <span><b>{conv.client}</b></span>}
          {conv.stage && <span className="stage">● {conv.stage}</span>}
          <span style={{marginLeft:'auto',fontSize:11}}>Entrega prevista: <b style={{color:'var(--text)'}}>30/04</b></span>
        </div>
      )}

      <div className="msgs" ref={scrollRef}>
        {rows.map((m, i) => (
          <React.Fragment key={i}>
            {m.showDay && <div className="day-sep">{m.d}</div>}
            <div className={"row " + m.side + (m.continued ? " continued" : "")}>
              {m.side === 'them' && <div className={`av ${m.grad||'av-1'}`}>{(m.who||'').slice(0,2).toUpperCase()}</div>}
              {m.note ? (
                <div className="bubble note">
                  <span className="author">📌 Nota interna · {m.who}</span>
                  {m.t}
                  <span className="meta">{m.time}</span>
                </div>
              ) : m.file ? (
                <div className={"bubble file"}>
                  <div className="fic"><I.paperclip size={18}/></div>
                  <div>
                    <b>{m.file.name}</b>
                    <small>{m.file.size} · PDF</small>
                  </div>
                </div>
              ) : (
                <div className="bubble">
                  {m.side === 'them' && !m.continued && <span className="author">{m.who}</span>}
                  {m.t}
                  <span className="meta">
                    {m.time}
                    {m.side === 'me' && <span style={{marginLeft:5}}>{m.read ? '✓✓' : '✓'}</span>}
                  </span>
                </div>
              )}
            </div>
          </React.Fragment>
        ))}
        {typing && (
          <div className="row" style={{maxWidth:'auto'}}>
            <div className={`av ${conv.msgs[0]?.grad||'av-1'}`}>{(conv.msgs[0]?.who||'').slice(0,2).toUpperCase()}</div>
            <div className="typing"><span/><span/><span/></div>
          </div>
        )}
      </div>

      <div className="composer">
        <div className="composer-inner">
          <textarea
            ref={taRef}
            value={text}
            onChange={e => setText(e.target.value)}
            onKeyDown={onKey}
            placeholder={`Mensagem para ${conv.title}...`}
            rows={1}
          />
          <div className="composer-toolbar">
            <button className="icon-btn" title="Anexo"><I.paperclip size={14}/></button>
            <button className="icon-btn" title="Emoji"><I.smile size={14}/></button>
            <button className="icon-btn" title="Mencionar"><I.hash size={14}/></button>
            <span className="spacer"/>
            <span className="hint"><kbd>Enter</kbd> envia · <kbd>⇧</kbd>+<kbd>Enter</kbd> nova linha</span>
            <button className="send-btn" disabled={!text.trim()} onClick={handleSend}>
              Enviar <I.send size={13}/>
            </button>
          </div>
        </div>
      </div>
    </section>
  );
}

window.ConvList = ConvList;
window.Thread = Thread;
