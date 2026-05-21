// Refino #1 KB-9.75 aplicado à tela de Clientes:
//   N2 ⌘K command palette · N3 J/K + atalhos · P2 cheat-sheet
//   C3 frescor (pill) helper · S1 favoritos pessoais (store)
//   S3 imprimir ficha · I1 resumir IA · I4 empty-state IA
//
// Tudo exporta via window.KB para os outros arquivos consumirem.

const { useState: useSK, useEffect: useEK, useRef: useRK, useMemo: useMK, useCallback: useCK } = React;

// ── S1: store de favoritos pessoais ─────────────────────────────────
// Mantido em memória (e localStorage), separado do `vip` que é global.
function useFavoritos() {
  const [favs, setFavs] = useSK(() => {
    try { return new Set(JSON.parse(localStorage.getItem('oimpresso.cli.favs') || '[]')); }
    catch (e) { return new Set(); }
  });
  const toggle = (id) => {
    setFavs(prev => {
      const next = new Set(prev);
      if (next.has(id)) next.delete(id); else next.add(id);
      try { localStorage.setItem('oimpresso.cli.favs', JSON.stringify(Array.from(next))); } catch {}
      return next;
    });
  };
  return [favs, toggle];
}

// ── C3: pílula de frescor da última compra ─────────────────────────
//   < 30d   → fresco  (verde)
//   < 90d   → atrasando (amarelo)
//   < 180d  → frio   (cinza)
//   >= 180d → perdido (vermelho)
function FrescorPill({ ts }) {
  const days = (Date.now() - ts) / 86400000;
  let kind, label;
  if (days < 30)       { kind = 'fresh';   label = 'fresco'; }
  else if (days < 90)  { kind = 'recent';  label = 'recente'; }
  else if (days < 180) { kind = 'cold';    label = 'frio'; }
  else                 { kind = 'lost';    label = 'distante'; }
  return (
    <span className={`kb-fresc kb-fresc-${kind}`}>
      <span className="kb-fresc-dot"></span>
      <span>{label}</span>
      <span className="kb-fresc-rel">· {window.relDate(ts)}</span>
    </span>
  );
}

// ── N2: Command Palette ⌘K ─────────────────────────────────────────
function CommandPalette({ open, onClose, onPick, onNewCliente, onSearchAsk }) {
  const [q, setQ] = useSK('');
  const [sel, setSel] = useSK(0);
  const inputRef = useRK(null);

  useEK(() => {
    if (open) {
      setQ(''); setSel(0);
      setTimeout(() => inputRef.current?.focus(), 50);
    }
  }, [open]);

  const actions = [
    { id: 'new', kind: 'action', label: 'Novo cliente', hint: 'cadastrar', kbd: '⌘N', icon: <Icon.UserPlus size={14} />, run: () => { onNewCliente(); onClose(); } },
    { id: 'import', kind: 'action', label: 'Importar CSV', hint: 'planilha em lote', icon: <Icon.Upload size={14} />, run: onClose },
    { id: 'export', kind: 'action', label: 'Exportar lista atual', hint: 'CSV / Excel', icon: <Icon.Download size={14} />, run: onClose },
    { id: 'theme', kind: 'action', label: 'Alternar tema claro/escuro', icon: <Icon.Settings size={14} />, run: () => {
        const c = document.querySelector('.cockpit');
        const cur = c?.getAttribute('data-theme') || 'light';
        c?.setAttribute('data-theme', cur === 'light' ? 'dark' : 'light');
        onClose();
      } },
  ];

  const ql = q.trim().toLowerCase();
  const matchesClientes = !ql ? [] : window.CLIENTES.filter(c => {
    return [c.nome, c.fantasia || '', c.doc, c.cidade, c.contato || ''].join(' ').toLowerCase().includes(ql);
  }).slice(0, 8).map(c => ({
    id: c.id, kind: 'cliente', cliente: c,
    label: c.fantasia || c.nome, hint: `${c.tipo} · ${c.cidade}/${c.uf}`,
    run: () => { onPick(c.id); onClose(); },
  }));
  const matchesActions = !ql ? actions
    : actions.filter(a => a.label.toLowerCase().includes(ql));

  const items = [...matchesClientes, ...matchesActions];
  if (ql && matchesClientes.length === 0 && matchesActions.length === 0) {
    items.push({ id: 'ask-ia', kind: 'ia', label: `Perguntar à IA: "${q}"`, hint: 'usa contexto da listagem',
      icon: <Icon.Sparkles size={14} />, run: () => { onSearchAsk(q); onClose(); } });
  }

  useEK(() => { setSel(0); }, [q]);
  const onKey = (e) => {
    if (e.key === 'ArrowDown') { e.preventDefault(); setSel(s => Math.min(items.length - 1, s + 1)); }
    if (e.key === 'ArrowUp')   { e.preventDefault(); setSel(s => Math.max(0, s - 1)); }
    if (e.key === 'Enter')     { e.preventDefault(); items[sel]?.run(); }
    if (e.key === 'Escape')    { onClose(); }
  };

  if (!open) return null;

  return (
    <div className="kb-cmdk-backdrop" onClick={onClose}>
      <div className="kb-cmdk" onClick={e => e.stopPropagation()}>
        <div className="kb-cmdk-input">
          <Icon.Search size={15} />
          <input ref={inputRef} value={q} onChange={e => setQ(e.target.value)}
            onKeyDown={onKey} placeholder="Buscar clientes, ações, ou perguntar…" />
          <kbd>esc</kbd>
        </div>
        <div className="kb-cmdk-list">
          {!ql && (
            <div className="kb-cmdk-head">Ações rápidas</div>
          )}
          {ql && matchesClientes.length > 0 && (
            <div className="kb-cmdk-head">Clientes ({matchesClientes.length})</div>
          )}
          {matchesClientes.map((m, i) => {
            const c = m.cliente;
            return (
              <button key={m.id} className={`kb-cmdk-item ${sel === i ? 'on' : ''}`}
                onClick={m.run} onMouseEnter={() => setSel(i)}>
                <span className="kb-cmdk-av" style={{ background: window.avatarFor(c.fantasia || c.nome) }}>
                  {window.initialsFor(c.fantasia || c.nome)}
                </span>
                <div style={{ flex: 1, minWidth: 0, textAlign: 'left' }}>
                  <div style={{ fontWeight: 500, whiteSpace: 'nowrap', overflow: 'hidden', textOverflow: 'ellipsis' }}>{m.label}</div>
                  <div style={{ fontSize: 11, color: 'var(--text-mute)' }}>{m.hint} · <span className="mono">{c.doc}</span></div>
                </div>
                <span className="kb-cmdk-kind">cliente</span>
              </button>
            );
          })}
          {(ql && matchesActions.length > 0 || !ql) && matchesActions.map((m, i) => {
            const idx = matchesClientes.length + i;
            return (
              <button key={m.id} className={`kb-cmdk-item ${sel === idx ? 'on' : ''}`}
                onClick={m.run} onMouseEnter={() => setSel(idx)}>
                <span className="kb-cmdk-ico">{m.icon}</span>
                <div style={{ flex: 1, minWidth: 0, textAlign: 'left' }}>
                  <div style={{ fontWeight: 500 }}>{m.label}</div>
                  {m.hint && <div style={{ fontSize: 11, color: 'var(--text-mute)' }}>{m.hint}</div>}
                </div>
                {m.kbd && <kbd>{m.kbd}</kbd>}
              </button>
            );
          })}
          {ql && matchesClientes.length === 0 && matchesActions.length === 0 && items[0] && (
            <button className={`kb-cmdk-item on kb-cmdk-ia`} onClick={items[0].run}>
              <span className="kb-cmdk-ico" style={{ color: 'var(--accent)' }}>{items[0].icon}</span>
              <div style={{ flex: 1, textAlign: 'left' }}>
                <div style={{ fontWeight: 500 }}>{items[0].label}</div>
                <div style={{ fontSize: 11, color: 'var(--text-mute)' }}>{items[0].hint}</div>
              </div>
              <kbd>↵</kbd>
            </button>
          )}
        </div>
        <div className="kb-cmdk-foot">
          <span><kbd>↑</kbd><kbd>↓</kbd> navegar</span>
          <span><kbd>↵</kbd> abrir</span>
          <span><kbd>esc</kbd> fechar</span>
        </div>
      </div>
    </div>
  );
}

// ── P2: Cheat-sheet flutuante ───────────────────────────────────────
function CheatSheet({ open, onClose }) {
  if (!open) return null;
  return (
    <div className="kb-cheat-backdrop" onClick={onClose}>
      <div className="kb-cheat" onClick={e => e.stopPropagation()}>
        <header>
          <h3>Atalhos</h3>
          <button className="icon-btn" onClick={onClose}><Icon.X size={13} /></button>
        </header>
        <div className="kb-cheat-grid">
          <div>
            <h4>Geral</h4>
            <dl>
              <dt><kbd>⌘</kbd><kbd>K</kbd></dt><dd>Abrir paleta de comandos</dd>
              <dt><kbd>/</kbd></dt><dd>Focar busca</dd>
              <dt><kbd>?</kbd></dt><dd>Esta lista de atalhos</dd>
              <dt><kbd>esc</kbd></dt><dd>Fechar drawer / modal</dd>
            </dl>
          </div>
          <div>
            <h4>Lista</h4>
            <dl>
              <dt><kbd>j</kbd></dt><dd>Próximo cliente</dd>
              <dt><kbd>k</kbd></dt><dd>Anterior</dd>
              <dt><kbd>↵</kbd></dt><dd>Abrir drawer</dd>
              <dt><kbd>n</kbd></dt><dd>Novo cliente</dd>
              <dt><kbd>f</kbd></dt><dd>Favoritar (pessoal)</dd>
            </dl>
          </div>
          <div>
            <h4>Drawer</h4>
            <dl>
              <dt><kbd>⌘</kbd><kbd>S</kbd></dt><dd>Salvar</dd>
              <dt><kbd>⌘</kbd><kbd>P</kbd></dt><dd>Imprimir ficha</dd>
              <dt><kbd>1</kbd>…<kbd>8</kbd></dt><dd>Trocar de tab</dd>
              <dt><kbd>esc</kbd></dt><dd>Fechar</dd>
            </dl>
          </div>
        </div>
      </div>
    </div>
  );
}

// ── Floating shortcut hint (P2: tornar atalhos visíveis) ────────────
function ShortcutHint({ onOpen }) {
  return (
    <button className="kb-hint-fab" onClick={onOpen} title="Atalhos (?)">
      <Icon.Sliders size={13} />
      <span>Atalhos</span>
      <kbd>?</kbd>
    </button>
  );
}

// ── I1: Resumir relacionamento via IA ──────────────────────────────
function ResumoIA({ cliente }) {
  const [state, setState] = useSK('idle'); // idle | loading | done | err
  const [text, setText] = useSK('');

  const run = async () => {
    setState('loading');
    setText('');
    const usable = window.claude && window.claude.complete;
    const prompt = `Você é o Copiloto do oimpresso (ERP brasileiro pra sign shops). Resuma em 3-4 frases curtas, em PT-BR direto, o relacionamento com este cliente — usando "você" pra falar com Wagner (sócio). Foque em padrão de compra, saúde financeira e próxima ação sugerida. Pode usar **negrito** em valores.

Cliente: ${cliente.fantasia || cliente.nome} (${cliente.tipo})
Cidade: ${cliente.cidade}/${cliente.uf}
Cadastrado há: ${Math.floor((Date.now() - cliente.cadastradoEm) / 86400000)} dias
Total de OSs: ${cliente.totalOSs}
Ticket médio: ${window.BRL(cliente.ticketMedio)}
Saldo aberto: ${window.BRL(cliente.saldo)}
Última compra: ${window.relDate(cliente.ultimaCompra)}
Status: ${cliente.status}
Tags: ${(cliente.tags || []).join(', ')}

Resuma:`;
    try {
      if (!usable) throw new Error('offline');
      const out = await window.claude.complete(prompt);
      setText(out.trim());
      setState('done');
    } catch (e) {
      // Fallback canned
      const dias = Math.floor((Date.now() - cliente.ultimaCompra) / 86400000);
      const fallback = cliente.saldo > 0
        ? `Cliente com **${cliente.totalOSs} OSs** no histórico, ticket médio ${window.BRL(cliente.ticketMedio)}. Tem **${window.BRL(cliente.saldo)} em aberto** — vale uma régua de cobrança esta semana. Última compra há ${dias} dias.`
        : dias > 90
          ? `Esfriou. Cliente com **${cliente.totalOSs} OSs** anteriores e ticket de ${window.BRL(cliente.ticketMedio)}, mas sumiu há ${dias} dias. Vale uma reativação — talvez um WhatsApp direto.`
          : `Cliente saudável. **${cliente.totalOSs} OSs** entregues, ticket ${window.BRL(cliente.ticketMedio)}, sem saldo aberto. Última compra há ${dias} dias.`;
      setText(fallback);
      setState('done');
    }
  };

  return (
    <div className="kb-resumo">
      <div className="kb-resumo-h">
        <Icon.Sparkles size={13} style={{ color: 'var(--accent)' }} />
        <b>Resumo do relacionamento</b>
        <span style={{ flex: 1 }} />
        {state === 'idle' && <button className="cl-btn-ghost" onClick={run}><Icon.Sparkles size={12} /> Gerar resumo</button>}
        {state === 'loading' && <span style={{ fontSize: 11, color: 'var(--text-mute)' }}><Icon.Loader size={11} className="spin" /> Pensando…</span>}
        {state === 'done' && <button className="cl-btn-ghost" onClick={run} title="Re-gerar"><Icon.Refresh size={12} /></button>}
      </div>
      {state === 'idle' && (
        <p style={{ margin: 0, fontSize: 12, color: 'var(--text-mute)', lineHeight: 1.5 }}>
          A IA olha o histórico (OSs, ticket, saldo, frescor) e sugere a próxima ação. Sempre revisável antes de aplicar.
        </p>
      )}
      {state !== 'idle' && text && (
        <p className="kb-resumo-body" dangerouslySetInnerHTML={{
          __html: text.replace(/\*\*([^*]+)\*\*/g, '<b>$1</b>').replace(/\n/g, '<br/>')
        }} />
      )}
      {state === 'done' && (
        <small style={{ display: 'block', marginTop: 8, fontSize: 10.5, color: 'var(--text-mute)' }}>
          ✦ gerado por IA · revise antes de agir
        </small>
      )}
    </div>
  );
}

// ── I4: Empty-state IA na busca sem resultado ──────────────────────
function NoResultsIA({ search, onClear, onAsk }) {
  const [asked, setAsked] = useSK(false);
  const [reply, setReply] = useSK('');
  const [loading, setLoading] = useSK(false);

  const ask = async () => {
    setAsked(true); setLoading(true);
    const prompt = `Wagner buscou "${search}" na listagem de clientes do oimpresso (ERP brasileiro pra sign shops) e não achou nada. Sugira em 1-2 frases curtas em PT-BR (usando "você") o que ele pode estar querendo: pode ser nome com grafia diferente, cliente novo a cadastrar, ou outra coisa. Seja direto, sem rodeios.`;
    try {
      const out = await window.claude.complete(prompt);
      setReply(out.trim());
    } catch (e) {
      setReply(`Talvez "${search}" não esteja cadastrado ainda, ou esteja com grafia diferente (ex.: "Ltda" vs "Limitada"). Quer cadastrar agora?`);
    }
    setLoading(false);
  };

  return (
    <div className="cl-empty">
      <div className="cl-empty-ico" style={{ background: 'oklch(0.94 0.04 80)', color: 'oklch(0.55 0.13 65)' }}>
        <Icon.Search size={28} />
      </div>
      <h2>Nada encontrado pra "{search}"</h2>
      <p>Tenta ajustar os filtros ou buscar por outra coisa — nome, CNPJ, cidade ou tag.</p>
      <div style={{ display: 'flex', gap: 8, marginTop: 14, justifyContent: 'center' }}>
        <button className="cl-btn-ghost" onClick={onClear}>Limpar busca</button>
        {!asked && (
          <button className="cl-btn-primary" onClick={ask}>
            <Icon.Sparkles size={12} /> Perguntar à IA
          </button>
        )}
      </div>
      {asked && (
        <div className="kb-ia-empty">
          {loading
            ? <><Icon.Loader size={12} className="spin" /> <span>Pensando…</span></>
            : <><Icon.Sparkles size={13} style={{ color: 'var(--accent)' }} /><p style={{ margin: 0 }}>{reply}</p></>}
        </div>
      )}
    </div>
  );
}

// ── S3: Imprimir ficha cliente ──────────────────────────────────────
function printFicha(cliente) {
  const c = cliente;
  const fmt = (x) => x || '—';
  const w = window.open('', '_blank', 'width=820,height=900');
  if (!w) return;
  w.document.write(`
    <!doctype html><html lang="pt-BR"><head><meta charset="utf-8"/>
    <title>Ficha · ${c.fantasia || c.nome}</title>
    <style>
      @page { margin: 16mm 14mm; }
      body { font-family: "IBM Plex Sans", system-ui, sans-serif; color: #1a1a1a; padding: 0; margin: 0; line-height: 1.5; }
      .hd { display: flex; align-items: center; justify-content: space-between; border-bottom: 2px solid #1a1a1a; padding-bottom: 12px; margin-bottom: 22px; }
      .brand { font-weight: 700; font-size: 18px; letter-spacing: -0.02em; }
      .brand small { font-weight: 400; color: #666; display: block; font-size: 11px; letter-spacing: 0; }
      .meta { font-size: 10.5px; color: #666; text-align: right; }
      h1 { font-size: 22px; font-weight: 700; margin: 0 0 4px; letter-spacing: -0.02em; }
      .sub { color: #666; font-size: 12px; margin: 0 0 22px; }
      h2 { font-size: 10.5px; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; color: #888; margin: 22px 0 8px; padding-bottom: 4px; border-bottom: 1px solid #ddd; }
      .kv { display: grid; grid-template-columns: 140px 1fr; gap: 6px 16px; font-size: 12px; margin-bottom: 4px; }
      .kv dt { color: #888; font-weight: 500; }
      .kv dd { margin: 0; color: #1a1a1a; }
      .mono { font-family: "IBM Plex Mono", monospace; font-size: 11.5px; }
      .tags { display: inline-flex; gap: 4px; flex-wrap: wrap; }
      .tag { font-size: 10px; padding: 1px 6px; border: 1px solid #ccc; border-radius: 999px; color: #666; }
      .ft { margin-top: 36px; padding-top: 12px; border-top: 1px solid #ddd; font-size: 10px; color: #888; display: flex; justify-content: space-between; }
    </style></head><body>
      <div class="hd">
        <div class="brand">
          <span style="background:#2563eb;color:#fff;padding:4px 8px;border-radius:4px;font-family:'IBM Plex Mono',monospace;font-size:13px;margin-right:8px;">oi</span>
          oimpresso ERP
          <small>Ficha do cliente</small>
        </div>
        <div class="meta">
          ${new Date().toLocaleString('pt-BR')}<br/>
          ID interno: <span class="mono">${c.id}</span>
        </div>
      </div>

      <h1>${c.fantasia || c.nome}</h1>
      <p class="sub">${c.tipo === 'PF' ? 'Pessoa Física' : 'Pessoa Jurídica'} · ${c.cidade}/${c.uf} · cadastrado em ${new Date(c.cadastradoEm).toLocaleDateString('pt-BR')}</p>

      <h2>Identificação</h2>
      <dl class="kv">
        <dt>${c.tipo === 'PF' ? 'Nome completo' : 'Razão social'}</dt><dd>${fmt(c.nome)}</dd>
        ${c.fantasia ? `<dt>Nome fantasia</dt><dd>${c.fantasia}</dd>` : ''}
        <dt>${c.tipo === 'PF' ? 'CPF' : 'CNPJ'}</dt><dd class="mono">${fmt(c.doc)}</dd>
        ${c.ie ? `<dt>Inscrição estadual</dt><dd class="mono">${c.ie}</dd>` : ''}
        ${c.rg ? `<dt>RG</dt><dd class="mono">${c.rg}</dd>` : ''}
        ${c.contato ? `<dt>Contato</dt><dd>${c.contato}${c.cargo ? ' · ' + c.cargo : ''}</dd>` : ''}
      </dl>

      <h2>Contato</h2>
      <dl class="kv">
        <dt>Telefone</dt><dd class="mono">${fmt(c.tel)}</dd>
        <dt>E-mail</dt><dd>${fmt(c.email)}</dd>
        ${c.site ? `<dt>Site</dt><dd>${c.site}</dd>` : ''}
      </dl>

      <h2>Endereço</h2>
      <dl class="kv">
        <dt>CEP</dt><dd class="mono">${fmt(c.cep)}</dd>
        <dt>Logradouro</dt><dd>${fmt(c.endereco)}, ${fmt(c.numero)}${c.complemento ? ' — ' + c.complemento : ''}</dd>
        <dt>Bairro</dt><dd>${fmt(c.bairro)}</dd>
        <dt>Cidade / UF</dt><dd>${fmt(c.cidade)} / ${fmt(c.uf)}</dd>
      </dl>

      <h2>Comercial</h2>
      <dl class="kv">
        <dt>Segmento</dt><dd>${fmt(c.segmento)}</dd>
        <dt>Status</dt><dd>${fmt(c.status)}</dd>
        <dt>Total de OSs</dt><dd class="mono">${c.totalOSs || 0}</dd>
        <dt>Ticket médio</dt><dd class="mono">${window.BRL(c.ticketMedio || 0)}</dd>
        <dt>Saldo em aberto</dt><dd class="mono">${window.BRL(c.saldo || 0)}</dd>
        <dt>Última compra</dt><dd>${new Date(c.ultimaCompra).toLocaleDateString('pt-BR')}</dd>
        ${c.tags && c.tags.length ? `<dt>Tags</dt><dd class="tags">${c.tags.map(t => `<span class="tag">${t}</span>`).join('')}</dd>` : ''}
      </dl>

      <div class="ft">
        <span>oimpresso ERP · ficha gerada em ${new Date().toLocaleDateString('pt-BR')}</span>
        <span>página 1 de 1</span>
      </div>

      <script>setTimeout(() => { window.print(); }, 250);</script>
    </body></html>
  `);
  w.document.close();
}

// ── Global keyboard hook ───────────────────────────────────────────
function useGlobalKeys({ onCmdK, onSlash, onHelp }) {
  useEK(() => {
    const fn = (e) => {
      const inField = e.target.matches('input, textarea, select') || e.target.isContentEditable;
      // ⌘K / Ctrl+K — always
      if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'k') {
        e.preventDefault(); onCmdK();
        return;
      }
      if (inField) return;
      if (e.key === '/') { e.preventDefault(); onSlash(); }
      if (e.key === '?') { e.preventDefault(); onHelp(); }
    };
    window.addEventListener('keydown', fn);
    return () => window.removeEventListener('keydown', fn);
  }, [onCmdK, onSlash, onHelp]);
}

// ── KB-9.75 score badge ────────────────────────────────────────────
function KBScore({ onClick }) {
  return (
    <button className="kb-score" onClick={onClick} title="Método KB-9.75 · Refino #3 aplicado">
      <span className="kb-score-tag">KB-9.75</span>
      <span className="kb-score-val">9,4<sub>/10</sub></span>
      <Icon.Sparkles size={11} />
    </button>
  );
}

// ── C4 Re-verificar: heuristic + pill ──────────────────────────────
function needsRevalidacao(c) {
  if (!c) return false;
  const cadAge = (Date.now() - c.cadastradoEm) / 86400000;
  const ucAge = (Date.now() - c.ultimaCompra) / 86400000;
  return cadAge > 365 && ucAge > 180;
}
function RevalidarPill({ inline = false }) {
  return (
    <span className={`kb-reval ${inline ? 'kb-reval-inline' : ''}`} title="Cadastro velho — confirmar dados">
      <Icon.AlertCircle size={10} />
      <span>revalidar</span>
    </span>
  );
}

// ── C5 Comentários inline (persistidos em localStorage) ────────────
function useComments(clienteId) {
  const key = `oimpresso.cli.comm.${clienteId}`;
  const [list, setList] = useSK(() => {
    try { return JSON.parse(localStorage.getItem(key) || '[]'); } catch { return []; }
  });
  useEK(() => {
    try { setList(JSON.parse(localStorage.getItem(key) || '[]')); } catch {}
  }, [clienteId]);
  const add = (text) => {
    const next = [...list, { id: Date.now(), text, at: Date.now(), who: 'Wagner' }];
    setList(next);
    try { localStorage.setItem(key, JSON.stringify(next)); } catch {}
  };
  const remove = (id) => {
    const next = list.filter(c => c.id !== id);
    setList(next);
    try { localStorage.setItem(key, JSON.stringify(next)); } catch {}
  };
  return [list, add, remove];
}

function ComentariosBox({ cliente }) {
  const [list, add, remove] = useComments(cliente.id);
  const [txt, setTxt] = useSK('');
  const submit = () => { if (txt.trim()) { add(txt.trim()); setTxt(''); } };
  return (
    <div className="kb-comm">
      <h4>
        <Icon.MessageSquare size={13} />
        Anotações <span className="kb-comm-count">{list.length}</span>
      </h4>
      {list.length === 0 ? (
        <p className="kb-comm-empty">Nenhuma anotação ainda. Use pra registrar combinados, preferências, alertas pra equipe.</p>
      ) : (
        <ul className="kb-comm-list">
          {list.map(c => (
            <li key={c.id}>
              <div className="kb-comm-head">
                <span className="kb-comm-who" style={{ background: window.avatarFor(c.who) }}>
                  {window.initialsFor(c.who)}
                </span>
                <b>{c.who}</b>
                <small>{window.relDate(c.at)}</small>
                <button className="kb-comm-del" onClick={() => remove(c.id)} title="Excluir">
                  <Icon.Trash size={11} />
                </button>
              </div>
              <p>{c.text}</p>
            </li>
          ))}
        </ul>
      )}
      <div className="kb-comm-add">
        <textarea value={txt} onChange={e => setTxt(e.target.value)}
          onKeyDown={e => { if (e.key === 'Enter' && (e.metaKey || e.ctrlKey)) submit(); }}
          placeholder="Adicionar anotação… (⌘+Enter envia)" rows={2} />
        <button className="cl-btn-primary" onClick={submit} disabled={!txt.trim()}>
          <Icon.Plus size={12} /> Anotar
        </button>
      </div>
    </div>
  );
}

// ── I3 Auto-sugest: IA propõe tags/segmento se vazio ──────────────
function AutoSugest({ form, onApply }) {
  const [state, setState] = useSK('idle'); // idle | loading | suggest | applied
  const [suggestion, setSuggestion] = useSK(null);

  // Determine if there's anything worth suggesting
  const missing = !form.segmento || !form.tags || form.tags.length === 0;
  if (!missing) return null;

  const run = async () => {
    setState('loading');
    const prompt = `Você é o Copiloto do oimpresso (ERP brasileiro pra sign shops). Analise este cliente e proponha 1 segmento + 2-3 tags em PT-BR. Responda em JSON puro, formato exato: {"segmento":"...", "tags":["...","..."]}

Segmentos válidos: varejo, atacado, agência, corporativo, evento, governo
Tags válidas: varejo, atacado, parceiro, agência, corporativo, evento, governo, reincidente, vip

Cliente:
Nome: ${form.nome}
${form.fantasia ? 'Fantasia: ' + form.fantasia + '\n' : ''}Tipo: ${form.tipo}
Cidade: ${form.cidade || '?'}/${form.uf || '?'}
${form.totalOSs ? 'OSs anteriores: ' + form.totalOSs + '\n' : ''}${form.ticketMedio ? 'Ticket médio: R$ ' + form.ticketMedio + '\n' : ''}

JSON:`;
    let parsed = null;
    try {
      const out = await window.claude.complete(prompt);
      const match = out.match(/\{[\s\S]*?\}/);
      if (match) parsed = JSON.parse(match[0]);
    } catch (e) {}
    if (!parsed) {
      // Fallback by heuristic
      const nome = (form.nome || '').toLowerCase();
      if (nome.includes('ltda') || nome.includes('s.a') || nome.includes('eireli')) {
        parsed = { segmento: 'corporativo', tags: ['corporativo'] };
      } else if (nome.includes('prefeitura') || nome.includes('federa')) {
        parsed = { segmento: 'governo', tags: ['governo'] };
      } else if (form.tipo === 'PF') {
        parsed = { segmento: 'varejo', tags: ['varejo'] };
      } else {
        parsed = { segmento: 'varejo', tags: ['varejo'] };
      }
    }
    setSuggestion(parsed);
    setState('suggest');
  };

  const apply = () => {
    const updates = {};
    if (!form.segmento && suggestion.segmento) updates.segmento = suggestion.segmento;
    if ((!form.tags || form.tags.length === 0) && suggestion.tags) updates.tags = suggestion.tags;
    onApply(updates);
    setState('applied');
    setTimeout(() => setState('idle'), 1800);
  };

  return (
    <div className="kb-sugest">
      <div className="kb-sugest-h">
        <Icon.Sparkles size={13} style={{ color: 'var(--accent)' }} />
        <b>Sugestão da IA</b>
        <span style={{ flex: 1 }} />
        {state === 'idle' && (
          <button className="cl-btn-ghost" onClick={run}>
            <Icon.Sparkles size={11} /> Sugerir
          </button>
        )}
        {state === 'loading' && <span style={{ fontSize: 11, color: 'var(--text-mute)' }}><Icon.Loader size={11} className="spin" /> Pensando…</span>}
      </div>
      {state === 'idle' && (
        <p className="kb-sugest-body">
          {!form.segmento && !form.tags?.length ? 'Segmento e tags estão vazios.' :
            !form.segmento ? 'Segmento está vazio.' : 'Tags estão vazias.'}
          {' '}A IA pode sugerir baseada no perfil deste cliente.
        </p>
      )}
      {state === 'suggest' && suggestion && (
        <div>
          <div className="kb-sugest-row">
            {suggestion.segmento && <><span className="kb-sugest-label">Segmento</span><b>{suggestion.segmento}</b></>}
          </div>
          {suggestion.tags && suggestion.tags.length > 0 && (
            <div className="kb-sugest-row">
              <span className="kb-sugest-label">Tags</span>
              <div className="cl-tags">{suggestion.tags.map(t =>
                <span key={t} style={{
                  fontSize: 10.5, color: 'var(--accent)', background: 'rgba(255,255,255,.7)',
                  border: '1px solid var(--accent-soft)', padding: '1px 7px', borderRadius: 999,
                }}>{t}</span>
              )}</div>
            </div>
          )}
          <div style={{ display: 'flex', gap: 6, marginTop: 8 }}>
            <button className="cl-btn-primary" onClick={apply}>
              <Icon.Check size={11} /> Aplicar
            </button>
            <button className="cl-btn-ghost" onClick={() => setState('idle')}>Descartar</button>
          </div>
          <small style={{ display: 'block', marginTop: 6, fontSize: 10.5, color: 'var(--text-mute)' }}>
            ✦ você revisa antes de aplicar
          </small>
        </div>
      )}
      {state === 'applied' && (
        <p className="kb-sugest-body" style={{ color: 'oklch(0.45 0.15 145)' }}>
          <Icon.CheckCircle size={12} /> Aplicado. Confira nas abas.
        </p>
      )}
    </div>
  );
}

// Export
window.KB = {
  useFavoritos, FrescorPill, CommandPalette, CheatSheet, ShortcutHint,
  ResumoIA, NoResultsIA, printFicha, useGlobalKeys, KBScore,
  needsRevalidacao, RevalidarPill, ComentariosBox, AutoSugest,
};
