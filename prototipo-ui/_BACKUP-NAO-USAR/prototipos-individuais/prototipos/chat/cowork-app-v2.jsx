// cowork-app-v2.jsx — Jana Chat V2
// Aplica COWORK_NOTES.amendment-jana-chat-block-renderer.md (2026-05-14)
// Charter: resources/js/Pages/Jana/Chat.charter.md
//
// Diferenças do V1 (chat.jsx export Claude Design):
//   A. Anti-patterns removidos: avatar gradient circular, typing 3-dots loop, mock humano, setTimeout
//   B. Vocabulário humano vazado removido: ✓✓ read receipts, botão ligar, online dot, "Mensagem para X", tabs OS/Equipes/Clientes
//   C. Features IA adicionadas: 4 kinds tipados, citations [1][2], action_card confirm, PII warning, suggested prompts, atalhos /, J, K, Esc, chip business
//
// Depende de: chat-renderer.jsx (BlockRouter, JanaAvatar, ThinkingIndicator, detectPii, PiiWarning), mock-stream.js (mockStream)

const { useState: useStateV2, useRef: useRefV2, useEffect: useEffectV2, useMemo: useMemoV2, useCallback: useCallbackV2 } = React;

// ============================================================================
// Dados mock — threads + business atual
// ============================================================================
const MOCK_BUSINESS = { short_name: 'LARISSA', id: 4 };
const MOCK_USER = { id: 1, name: 'Wagner' };

const MOCK_THREADS = [
  {
    id: 't1',
    title: 'Vendas do dia',
    preview: '7 vendas, R$ 4.382,50 — 3 à vista, 4 a prazo',
    time: '14:32',
    owner_id: 1,
    shared_with_team: false,
    archived_at: null,
    pinned: true,
    msg_count: 4,
    msgs: [
      { role: 'user',      kind: 'text',     text: 'Quantas vendas tive hoje?',                                                         time: '14:30' },
      { role: 'assistant', kind: 'tool_use', tool: 'sells.list_today', params: { date: 'today' }, status: 'done',                       time: '14:30' },
      { role: 'assistant', kind: 'markdown', markdown: 'Hoje você teve **7 vendas** totalizando **R$ 4.382,50** [1]. 3 foram à vista e 4 a prazo [2].',
                                              sources: [
                                                { n: 1, label: 'Modules/Vestuario/Sells/Index', href: '/vestuario/vendas?date=today' },
                                                { n: 2, label: 'Relatório Financeiro',          href: '/financeiro/dre' },
                                              ], time: '14:30' },
      { role: 'assistant', kind: 'data_table',
        caption: 'Vendas de hoje',
        columns: [
          { key: 'id', label: '#' },
          { key: 'cliente', label: 'Cliente' },
          { key: 'total', label: 'Total' },
          { key: 'forma', label: 'Forma' },
        ],
        rows: [
          { id: '#1234', cliente: 'Maria Silva', total: 'R$ 850,00',   forma: 'Pix' },
          { id: '#1235', cliente: 'João Souza',  total: 'R$ 1.200,00', forma: 'Cartão' },
          { id: '#1236', cliente: 'Ana Costa',   total: 'R$ 432,50',   forma: 'À prazo' },
          { id: '#1237', cliente: 'Carlos Lima', total: 'R$ 580,00',   forma: 'Pix' },
          { id: '#1238', cliente: 'Pedro Alves', total: 'R$ 320,00',   forma: 'Pix' },
          { id: '#1239', cliente: 'Beatriz F.',  total: 'R$ 600,00',   forma: 'À prazo' },
          { id: '#1240', cliente: 'Marcos R.',   total: 'R$ 400,00',   forma: 'À prazo' },
        ],
        time: '14:30',
      },
    ],
  },
  {
    id: 't2',
    title: 'OS atrasadas',
    preview: '3 ordens com atraso >5d, todas em Modules/Repair',
    time: '11:08',
    owner_id: 1,
    shared_with_team: true,
    archived_at: null,
    pinned: false,
    msg_count: 3,
    msgs: [
      { role: 'user',      kind: 'text',     text: 'Listar OS atrasadas', time: '11:05' },
      { role: 'assistant', kind: 'tool_use', tool: 'repair.list_delayed', params: { biz: 4 }, status: 'done', time: '11:05' },
      { role: 'assistant', kind: 'markdown', markdown: 'Encontrei **3 OS atrasadas** no Modules/Repair [1]. Todas com mais de 5 dias além do prazo prometido.',
                                              sources: [{ n: 1, label: 'Modules/Repair/JobSheets', href: '/repair/jobsheets?status=atrasada' }],
                                              time: '11:05' },
    ],
  },
  {
    id: 't3',
    title: 'Cancelar venda #1234',
    preview: 'Pendente confirmação — refund e estoque',
    time: '10:42',
    owner_id: 1,
    shared_with_team: false,
    archived_at: null,
    pinned: false,
    msg_count: 3,
    msgs: [
      { role: 'user',      kind: 'text', text: 'Cancela a venda #1234 por favor', time: '10:40' },
      { role: 'assistant', kind: 'markdown', markdown: 'Vou preparar o cancelamento da venda **#1234**. Confirme abaixo para executar.', time: '10:40' },
      { role: 'assistant', kind: 'action_card',
        action: 'cancelar_venda',
        summary: 'Cancelar venda #1234 (R$ 850,00) — Maria Silva. Isso vai liberar estoque (3 peças) e disparar refund se já houver pagamento.',
        confirm_required: true,
        result: null,
        time: '10:40' },
    ],
  },
  {
    id: 't4',
    title: 'Top inadimplentes',
    preview: '5 clientes totalizando R$ 12.450 em atraso',
    time: 'ontem',
    owner_id: 1,
    shared_with_team: true,
    archived_at: null,
    pinned: false,
    msg_count: 3,
    msgs: [],
  },
  {
    id: 't5',
    title: 'Relatório Q1',
    preview: 'Resumo trimestral — arquivada',
    time: '3 dias',
    owner_id: 1,
    shared_with_team: false,
    archived_at: '2026-05-11',
    pinned: false,
    msg_count: 8,
    msgs: [],
  },
];

// ============================================================================
// ConvList — Lista esquerda. Tabs: Todas / Minhas / Compartilhadas / Arquivadas (charter canon)
// ============================================================================
function ConvList({ threads, activeId, onSelect, query, setQuery, tab, setTab }) {
  const filtered = useMemoV2(() => {
    const q = query.trim().toLowerCase();
    let list = threads;
    if (tab === 'minhas')        list = list.filter(t => t.owner_id === MOCK_USER.id && !t.archived_at);
    else if (tab === 'compartilhadas') list = list.filter(t => t.shared_with_team && !t.archived_at);
    else if (tab === 'arquivadas')     list = list.filter(t => t.archived_at !== null);
    else                              list = list.filter(t => !t.archived_at);
    if (q) list = list.filter(t => t.title.toLowerCase().includes(q) || (t.preview || '').toLowerCase().includes(q));
    return list;
  }, [threads, query, tab]);

  const pinned = filtered.filter(t => t.pinned);
  const rest = filtered.filter(t => !t.pinned);

  return (
    <section className="list">
      <div className="list-h">
        <h2>Jana</h2>
        <span className="biz-chip" title={`Business ${MOCK_BUSINESS.id}`}>
          {MOCK_BUSINESS.short_name} · biz={MOCK_BUSINESS.id}
        </span>
        <button className="icon-btn primary" title="Nova conversa"><I.plus size={14}/></button>
      </div>
      <div className="search">
        <I.search className="ic"/>
        <input
          id="jana-search"
          placeholder="Buscar conversas..."
          value={query}
          onChange={e => setQuery(e.target.value)}
        />
        <span className="kbd">⌘K</span>
      </div>
      <div className="list-tabs">
        {[
          { id: 'todas',          label: 'Todas' },
          { id: 'minhas',         label: 'Minhas' },
          { id: 'compartilhadas', label: 'Compartilhadas' },
          { id: 'arquivadas',     label: 'Arquivadas' },
        ].map(t => (
          <span key={t.id} className={'list-tab' + (tab === t.id ? ' active' : '')} onClick={() => setTab(t.id)}>
            {t.label}
          </span>
        ))}
      </div>
      <div className="list-body">
        {pinned.length > 0 && <div className="list-group-h"><I.pin size={11}/> Fixadas</div>}
        {pinned.map(t => <ConvItem key={t.id} t={t} active={t.id === activeId} onSelect={onSelect}/>)}
        {rest.length > 0 && <div className="list-group-h">Recentes</div>}
        {rest.map(t => <ConvItem key={t.id} t={t} active={t.id === activeId} onSelect={onSelect}/>)}
        {filtered.length === 0 && (
          <div className="empty-list">Nenhuma conversa encontrada.</div>
        )}
      </div>
    </section>
  );
}

function ConvItem({ t, active, onSelect }) {
  return (
    <div className={'conv' + (active ? ' active' : '')} onClick={() => onSelect(t.id)}>
      <JanaAvatar size={28} />
      <div className="conv-top">
        <b>{t.title}</b>
        <time>{t.time}</time>
      </div>
      <div className="conv-preview">{t.preview}</div>
    </div>
  );
}

// ============================================================================
// Thread — central. Bubbles tipadas via BlockRouter. Streaming. Pause auto-scroll.
// ============================================================================
function Thread({ thread, onSend, onSendPrompt }) {
  const [text, setText] = useStateV2('');
  const [streaming, setStreaming] = useStateV2(false);          // 'pensando' antes do 1º token
  const [streamingText, setStreamingText] = useStateV2('');      // buffer durante stream
  const [autoScroll, setAutoScroll] = useStateV2(true);
  const taRef = useRefV2(null);
  const scrollRef = useRefV2(null);
  const piiKind = detectPii(text);

  // Auto-resize textarea até 8 linhas (~160px)
  useEffectV2(() => {
    if (!taRef.current) return;
    taRef.current.style.height = 'auto';
    taRef.current.style.height = Math.min(taRef.current.scrollHeight, 160) + 'px';
  }, [text]);

  // Auto-scroll bottom on new msg, PAUSADO se user rolou pra cima
  useEffectV2(() => {
    if (!scrollRef.current) return;
    if (autoScroll) scrollRef.current.scrollTop = scrollRef.current.scrollHeight;
  }, [thread?.msgs?.length, streaming, streamingText, autoScroll]);

  // Detecta se user rolou pra cima (>200px do bottom) → pausa auto-scroll
  const onScroll = () => {
    const el = scrollRef.current;
    if (!el) return;
    const nearBottom = el.scrollHeight - el.scrollTop - el.clientHeight < 200;
    setAutoScroll(nearBottom);
  };

  if (!thread) {
    return (
      <section className="thread">
        <EmptyState onSendPrompt={onSendPrompt}/>
      </section>
    );
  }

  const handleSend = (overrideText) => {
    const t = (overrideText ?? text).trim();
    if (!t) return;
    onSend(t);
    setText('');
    setStreaming(true);
    setStreamingText('');

    // Mock streaming — substitui setTimeout do V1
    mockStream(t,
      ({ delta }) => setStreamingText(prev => prev + delta),
      ({ blocks }) => {
        setStreaming(false);
        setStreamingText('');
        // Envia blocks finais pro pai concatenar na thread
        blocks.forEach(b => onSend(null, b));
      }
    );
  };

  const onKey = (e) => {
    if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); handleSend(); }
    // ⌘+Enter ou Ctrl+Enter também envia (charter)
    if (e.key === 'Enter' && (e.metaKey || e.ctrlKey)) { e.preventDefault(); handleSend(); }
  };

  return (
    <section className="thread">
      <header className="th-head">
        <JanaAvatar size={32} />
        <div className="who">
          <b>Jana</b>
          <small>
            {thread.msgs.length} mensagens · última atividade {thread.time}
          </small>
        </div>
        <div className="actions">
          <button className="icon-btn" title="Detalhes"><I.info size={14}/></button>
          <button className="icon-btn" title="Mais"><I.more size={14}/></button>
        </div>
      </header>

      <div className="msgs" ref={scrollRef} onScroll={onScroll}>
        {thread.msgs.map((m, i) => (
          <div key={i} className={'row ' + (m.role === 'user' ? 'me' : 'them')}>
            {m.role === 'assistant' && m.kind !== 'tool_use' && <JanaAvatar size={24} />}
            {m.role === 'user' ? (
              <div className="bubble bubble-user">
                <div className="bubble-content">{m.text}</div>
                <span className="meta">{m.time}</span>
              </div>
            ) : (
              <BlockRouter msg={m} />
            )}
          </div>
        ))}

        {streaming && streamingText === '' && (
          <div className="row them">
            <JanaAvatar size={24} />
            <ThinkingIndicator />
          </div>
        )}
        {streaming && streamingText !== '' && (
          <div className="row them">
            <JanaAvatar size={24} />
            <div className="bubble bubble-md bubble-streaming">
              <div className="bubble-content">{streamingText}<span className="caret-blink">▍</span></div>
            </div>
          </div>
        )}
      </div>

      <div className="composer">
        <PiiWarning kind={piiKind} />
        <div className="composer-inner">
          <textarea
            ref={taRef}
            id="jana-composer"
            value={text}
            onChange={e => setText(e.target.value)}
            onKeyDown={onKey}
            placeholder="Pergunte algo à Jana sobre vendas, OS, financeiro..."
            rows={1}
          />
          <div className="composer-toolbar">
            <span className="hint">
              <kbd>Enter</kbd> envia · <kbd>⇧</kbd>+<kbd>Enter</kbd> nova linha · <kbd>/</kbd> foca · <kbd>Esc</kbd> desfoca
            </span>
            <span className="spacer"/>
            <button className="send-btn" disabled={!text.trim() || streaming} onClick={() => handleSend()}>
              Enviar <I.send size={13}/>
            </button>
          </div>
        </div>
      </div>
    </section>
  );
}

// ============================================================================
// EmptyState — quando nenhuma thread selecionada. 4 prompts iniciais clicáveis.
// ============================================================================
function EmptyState({ onSendPrompt }) {
  const PROMPTS = [
    { icon: '📈', label: 'Vendas de hoje',     prompt: 'Quantas vendas tive hoje?' },
    { icon: '⏰', label: 'OS atrasadas',       prompt: 'Listar OS atrasadas' },
    { icon: '💰', label: 'Inadimplentes',      prompt: 'Top 5 clientes em débito' },
    { icon: '📊', label: 'Financeiro mensal',  prompt: 'Resumo financeiro do mês' },
  ];
  return (
    <div className="empty empty-jana">
      <div className="empty-ico"><JanaAvatar size={56} /></div>
      <h3>Como posso ajudar hoje?</h3>
      <p>Pergunte sobre vendas, OS, financeiro ou peça uma ação.</p>
      <div className="prompts-grid">
        {PROMPTS.map(p => (
          <button key={p.label} className="prompt-chip" onClick={() => onSendPrompt(p.prompt)}>
            <span className="prompt-icon">{p.icon}</span>
            <span>{p.label}</span>
          </button>
        ))}
      </div>
      <div className="empty-shortcuts">
        <kbd>⌘K</kbd> busca histórico · <kbd>/</kbd> foca composer · <kbd>J/K</kbd> navega
      </div>
    </div>
  );
}

// ============================================================================
// JanaChatApp — root. Atalhos globais /, J, K, Esc, ⌘K.
// ============================================================================
function JanaChatApp() {
  const [threads, setThreads] = useStateV2(MOCK_THREADS);
  const [activeId, setActiveId] = useStateV2('t1');
  const [query, setQuery] = useStateV2('');
  const [tab, setTab] = useStateV2('todas');
  const active = threads.find(t => t.id === activeId);

  // Atalhos globais
  useEffectV2(() => {
    const handler = (e) => {
      const tag = (e.target.tagName || '').toLowerCase();
      const isInput = tag === 'input' || tag === 'textarea';

      // ⌘K / Ctrl+K → foca search
      if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'k') {
        e.preventDefault();
        document.getElementById('jana-search')?.focus();
        return;
      }

      // Atalhos sem modificador só quando NÃO está em input
      if (isInput) {
        if (e.key === 'Escape') { e.target.blur(); }
        return;
      }

      // / → foca composer
      if (e.key === '/') {
        e.preventDefault();
        document.getElementById('jana-composer')?.focus();
        return;
      }

      // J/K → navega mensagens (próxima/anterior) — scroll na thread
      if (e.key === 'j' || e.key === 'J') {
        const sc = document.querySelector('.msgs');
        if (sc) sc.scrollBy({ top: 100, behavior: 'smooth' });
      }
      if (e.key === 'k' || e.key === 'K') {
        const sc = document.querySelector('.msgs');
        if (sc) sc.scrollBy({ top: -100, behavior: 'smooth' });
      }
    };
    window.addEventListener('keydown', handler);
    return () => window.removeEventListener('keydown', handler);
  }, []);

  const handleSend = useCallbackV2((userText, assistantBlock) => {
    setThreads(prev => prev.map(t => {
      if (t.id !== activeId) return t;
      const now = new Date().toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
      const newMsgs = [...t.msgs];
      if (userText) newMsgs.push({ role: 'user', kind: 'text', text: userText, time: now });
      if (assistantBlock) newMsgs.push({ ...assistantBlock, time: now });
      return { ...t, msgs: newMsgs, msg_count: newMsgs.length };
    }));
  }, [activeId]);

  const handleSendPrompt = useCallbackV2((promptText) => {
    // Cria thread nova ou usa primeira não-arquivada
    const firstOpen = threads.find(t => !t.archived_at) || threads[0];
    setActiveId(firstOpen.id);
    // Espera state propagar antes de enviar
    setTimeout(() => {
      handleSend(promptText);
      // Dispara stream via Thread component re-render — simulação
      mockStream(promptText,
        ({ delta }) => {/* ignored aqui — Thread controla streaming local */},
        ({ blocks }) => blocks.forEach(b => handleSend(null, b))
      );
    }, 50);
  }, [threads, handleSend]);

  return (
    <div className="jana-chat-app">
      <ConvList
        threads={threads}
        activeId={activeId}
        onSelect={setActiveId}
        query={query}
        setQuery={setQuery}
        tab={tab}
        setTab={setTab}
      />
      <Thread
        thread={active}
        onSend={handleSend}
        onSendPrompt={handleSendPrompt}
      />
    </div>
  );
}

// Mount
const rootEl = document.getElementById('app');
if (rootEl) {
  ReactDOM.createRoot(rootEl).render(<JanaChatApp />);
}

window.JanaChatApp = JanaChatApp;
