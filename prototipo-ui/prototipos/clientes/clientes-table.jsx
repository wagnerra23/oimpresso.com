// ClientesPage — the page content rendered inside .main-body.
// Composes header, filter bar, active filter chips, and the data table.

const { useState: useST, useMemo: useMT, useEffect: useET } = React;

function ClientesPage({ onPickCliente, focused, density, layoutMode, listState, favoritos, onToggleFav, onOpenCmdK, onOpenCheat }) {
  const C = window.Clientes;
  const all = window.CLIENTES;

  const [search, setSearch] = useST('');
  const [fTipo, setFTipo] = useST('');             // '' | 'PF' | 'PJ'
  const [fStatus, setFStatus] = useST('');         // '' | 'ativo' | 'inativo' | 'bloqueado'
  const [fUf, setFUf] = useST('');
  const [fTags, setFTags] = useST([]);             // multi
  const [fSaldo, setFSaldo] = useST(false);        // só com saldo aberto
  const [fStale, setFStale] = useST('');           // '' | '30' | '90' | '180' | '365'
  const [sort, setSort] = useST({ col: 'nome', dir: 'asc' });

  // Compute filtered + sorted clientes
  const filtered = useMT(() => {
    const q = search.trim().toLowerCase();
    let arr = all.filter(c => {
      if (q) {
        const hay = [c.nome, c.fantasia || '', c.doc, c.cidade, c.contato || '', (c.tags || []).join(' ')].join(' ').toLowerCase();
        if (!hay.includes(q)) return false;
      }
      if (fTipo && c.tipo !== fTipo) return false;
      if (fStatus && c.status !== fStatus) return false;
      if (fUf && c.uf !== fUf) return false;
      if (fTags.length && !fTags.every(t => (c.tags || []).includes(t))) return false;
      if (fSaldo && !(c.saldo > 0)) return false;
      if (fStale) {
        const days = parseInt(fStale);
        const since = (Date.now() - c.ultimaCompra) / 86400000;
        if (since < days) return false;
      }
      return true;
    });
    const dir = sort.dir === 'asc' ? 1 : -1;
    arr = arr.slice().sort((a, b) => {
      const get = (x) => sort.col === 'ultimaCompra' ? x.ultimaCompra
        : sort.col === 'saldo' ? x.saldo
        : sort.col === 'cidade' ? x.cidade
        : x.nome;
      const av = get(a), bv = get(b);
      if (av < bv) return -1 * dir;
      if (av > bv) return 1 * dir;
      return 0;
    });
    return arr;
  }, [search, fTipo, fStatus, fUf, fTags, fSaldo, fStale, sort, all]);

  const clear = () => {
    setSearch(''); setFTipo(''); setFStatus(''); setFUf(''); setFTags([]);
    setFSaldo(false); setFStale('');
  };

  const activeFilterCount = (fTipo ? 1 : 0) + (fStatus ? 1 : 0) + (fUf ? 1 : 0) + fTags.length +
    (fSaldo ? 1 : 0) + (fStale ? 1 : 0);

  const showEmpty = listState === 'vazio';
  const showLoading = listState === 'loading';
  const showNoResults = !showEmpty && !showLoading && filtered.length === 0;

  return (
    <div className="cl-page">
      {/* Page header */}
      <div className="cl-page-h">
        <div>
          <h1>
            Clientes
            <window.KB.KBScore onClick={onOpenCheat} />
          </h1>
          <p>{all.length} cadastrados · {all.filter(c => c.status === 'ativo').length} ativos</p>
        </div>
        <div className="cl-page-h-r">
          <button className="cl-btn-ghost" onClick={onOpenCmdK} title="Buscar tudo (⌘K)">
            <Icon.Search size={13} /> Buscar
            <kbd style={{
              fontFamily: 'var(--font-mono)', fontSize: 10, marginLeft: 6, padding: '1px 5px',
              background: 'var(--bg-2)', border: '1px solid var(--border)', borderRadius: 3,
            }}>⌘K</kbd>
          </button>
          <button className="cl-btn-ghost" title="Importar"><Icon.Upload size={13} /> Importar</button>
          <button className="cl-btn-ghost" title="Exportar"><Icon.Download size={13} /> Exportar</button>
          <button className="cl-btn-primary" onClick={() => onPickCliente('new')}>
            <Icon.UserPlus size={13} /> Novo cliente
          </button>
        </div>
      </div>

      {/* Search + filter bar */}
      <div className="cl-filter-bar">
        <div className="cl-search">
          <Icon.Search size={13} />
          <input
            placeholder="Buscar por nome, CNPJ, cidade, contato…"
            value={search}
            onChange={e => setSearch(e.target.value)}
          />
          {search && <Icon.X size={13} style={{ cursor: 'pointer', opacity: 0.6 }} onClick={() => setSearch('')} />}
        </div>
        <span className="cl-filter-sep"></span>
        <C.FilterDropdown label="Tipo" value={fTipo} onChange={setFTipo}
          options={[{ value: 'PF', label: 'Pessoa física' }, { value: 'PJ', label: 'Pessoa jurídica' }]} />
        <C.FilterDropdown label="Status" value={fStatus} onChange={setFStatus}
          options={[{ value: 'ativo', label: 'Ativo' }, { value: 'inativo', label: 'Inativo' }, { value: 'bloqueado', label: 'Bloqueado' }]} />
        <C.FilterDropdown label="UF" value={fUf} onChange={setFUf} options={window.UF_OPTIONS} />
        <C.FilterDropdown label="Tags" value={fTags} onChange={setFTags} multi options={window.TAG_OPTIONS} />
        <C.FilterDropdown label="Sem compra há" value={fStale} onChange={setFStale}
          options={[
            { value: '30', label: '30 dias' }, { value: '90', label: '90 dias' },
            { value: '180', label: '6 meses' }, { value: '365', label: '1 ano' },
          ]} />
        <button className={`cl-filter ${fSaldo ? 'on' : ''}`} onClick={() => setFSaldo(s => !s)}
          style={fSaldo ? { background: 'oklch(0.93 0.07 25)', color: 'oklch(0.40 0.14 25)', borderColor: 'transparent' } : {}}>
          <Icon.AlertCircle size={11} /> Com saldo
        </button>
        {activeFilterCount > 0 && (
          <button className="cl-filter cl-filter-clear" onClick={clear}>
            Limpar ({activeFilterCount})
          </button>
        )}
      </div>

      {/* Active chip row (compact) */}
      {(activeFilterCount > 0) && (
        <div className="cl-active-chips">
          {fTipo && <C.ActiveChip label={fTipo === 'PF' ? 'Pessoa física' : 'Pessoa jurídica'} onRemove={() => setFTipo('')} />}
          {fStatus && <C.ActiveChip label={`Status: ${fStatus}`} onRemove={() => setFStatus('')} />}
          {fUf && <C.ActiveChip label={`UF: ${fUf}`} onRemove={() => setFUf('')} />}
          {fTags.map(t => <C.ActiveChip key={t} label={t} onRemove={() => setFTags(fTags.filter(x => x !== t))} />)}
          {fSaldo && <C.ActiveChip label="Com saldo aberto" onRemove={() => setFSaldo(false)} />}
          {fStale && <C.ActiveChip label={`Sem compra há ${fStale}d`} onRemove={() => setFStale('')} />}
        </div>
      )}

      {/* Result line */}
      {!showEmpty && !showLoading && !showNoResults && (
        <div className="cl-result-line">
          <b>{filtered.length}</b> {filtered.length === 1 ? 'cliente encontrado' : 'clientes encontrados'}
          {filtered.length !== all.length && ` de ${all.length} no total`}
        </div>
      )}

      {/* Body */}
      <div className="cl-body">
        {showEmpty ? <C.EmptyState /> :
         showLoading ? <C.LoadingSkeleton density={density} /> :
         showNoResults ? <window.KB.NoResultsIA search={search} onClear={clear} /> :
         <ClientesTable rows={filtered} onPick={onPickCliente} focused={focused}
                        sort={sort} setSort={setSort} density={density} layoutMode={layoutMode}
                        favoritos={favoritos} onToggleFav={onToggleFav} />}
      </div>
    </div>
  );
}

// ── Table view ───────────────────────────────────────────────────────

function HeaderCell({ col, label, sort, setSort, align = 'left' }) {
  const active = sort.col === col;
  const onClick = () => {
    if (active) setSort({ col, dir: sort.dir === 'asc' ? 'desc' : 'asc' });
    else setSort({ col, dir: 'asc' });
  };
  return (
    <div className="cl-th" onClick={onClick} style={{ justifyContent: align === 'right' ? 'flex-end' : 'flex-start' }}>
      <span>{label}</span>
      {active
        ? (sort.dir === 'asc' ? <Icon.ArrowUp size={10} /> : <Icon.ArrowDown size={10} />)
        : <Icon.ArrowUpDown size={10} style={{ opacity: 0.35 }} />}
    </div>
  );
}

function ClientesTable({ rows, onPick, focused, sort, setSort, density, layoutMode, favoritos, onToggleFav }) {
  const C = window.Clientes;
  const KB = window.KB;

  if (layoutMode === 'cards') return <ClientesCards rows={rows} onPick={onPick} focused={focused} favoritos={favoritos} onToggleFav={onToggleFav} />;
  if (layoutMode === 'split') return <ClientesSplit rows={rows} onPick={onPick} focused={focused} favoritos={favoritos} onToggleFav={onToggleFav} />;

  const rowH = density === 'compact' ? 38 : density === 'comfy' ? 56 : 46;

  return (
    <div className="cl-table" style={{ '--row-h': `${rowH}px` }}>
      <div className="cl-thead">
        <div className="cl-thead-row">
          <div className="cl-th"></div>
          <HeaderCell col="nome" label="Nome" sort={sort} setSort={setSort} />
          <div className="cl-th">Tipo</div>
          <div className="cl-th">Documento</div>
          <HeaderCell col="cidade" label="Cidade / UF" sort={sort} setSort={setSort} />
          <HeaderCell col="ultimaCompra" label="Última compra" sort={sort} setSort={setSort} />
          <HeaderCell col="saldo" label="Saldo" sort={sort} setSort={setSort} align="right" />
          <div className="cl-th">Tags</div>
          <div className="cl-th"></div>
        </div>
      </div>
      <div className="cl-tbody">
        {rows.map(c => (
          <div key={c.id}
            className={`cl-tr ${focused === c.id ? 'focused' : ''} ${c.status !== 'ativo' ? 'dim' : ''}`}
            onClick={() => onPick(c.id)}>
            <div className="cl-td"><C.Avatar name={c.fantasia || c.nome} /></div>
            <div className="cl-td">
              <div className="cl-name">
                <b>{c.fantasia || c.nome}</b>
                {c.vip && <Icon.Star size={11} style={{ color: 'oklch(0.72 0.18 75)', fill: 'oklch(0.78 0.16 75)', strokeWidth: 1 }} />}
                {KB.needsRevalidacao(c) && <KB.RevalidarPill inline />}
              </div>
              {c.tipo === 'PJ' && c.fantasia && <small className="cl-sub">{c.contato || c.nome}</small>}
              {c.tipo === 'PF' && <small className="cl-sub">{c.email || ''}</small>}
            </div>
            <div className="cl-td"><C.TipoPill tipo={c.tipo} /></div>
            <div className="cl-td"><span className="mono cl-doc">{c.doc}</span></div>
            <div className="cl-td">
              <div className="cl-stack">
                <span>{c.cidade}</span>
                <small className="cl-sub">{c.uf}</small>
              </div>
            </div>
            <div className="cl-td">
              <KB.FrescorPill ts={c.ultimaCompra} />
            </div>
            <div className="cl-td" style={{ textAlign: 'right' }}>
              {c.saldo > 0
                ? <span style={{ color: 'oklch(0.55 0.18 25)', fontWeight: 600 }}>{window.BRL(c.saldo)}</span>
                : <span style={{ color: 'var(--text-mute)' }}>—</span>}
            </div>
            <div className="cl-td">
              <div className="cl-tags">
                {(c.tags || []).slice(0, 3).map(t => <C.TagChip key={t} t={t} />)}
                {(c.tags || []).length > 3 && <C.TagChip t={`+${c.tags.length - 3}`} />}
                <C.StatusPill status={c.status} />
              </div>
            </div>
            <div className="cl-td">
              <button className={`kb-fav-btn ${favoritos && favoritos.has(c.id) ? 'on' : ''}`}
                onClick={e => { e.stopPropagation(); onToggleFav && onToggleFav(c.id); }}
                title={favoritos && favoritos.has(c.id) ? 'Desfavoritar' : 'Favoritar (pessoal)'}>
                <Icon.Star size={13} />
              </button>
            </div>
          </div>
        ))}
      </div>
      <div className="cl-foot">
        Mostrando <b>{rows.length}</b> {rows.length === 1 ? 'cliente' : 'clientes'} · 
        <button className="cl-link"> Mostrar mais →</button>
      </div>
    </div>
  );
}

// ── Cards layout (alternative) ───────────────────────────────────────

function ClientesCards({ rows, onPick, focused }) {
  const C = window.Clientes;
  return (
    <div className="cl-cards">
      {rows.map(c => (
        <div key={c.id} className={`cl-card-c ${focused === c.id ? 'focused' : ''}`} onClick={() => onPick(c.id)}>
          <div className="cl-card-h">
            <C.Avatar name={c.fantasia || c.nome} size={36} />
            <div style={{ flex: 1, minWidth: 0 }}>
              <div className="cl-name" style={{ marginBottom: 2 }}>
                <b style={{ whiteSpace: 'nowrap', overflow: 'hidden', textOverflow: 'ellipsis' }}>{c.fantasia || c.nome}</b>
                {c.vip && <Icon.Star size={11} style={{ color: 'oklch(0.72 0.18 75)', fill: 'oklch(0.78 0.16 75)', strokeWidth: 1 }} />}
              </div>
              <small className="cl-sub">{c.cidade}/{c.uf}</small>
            </div>
            <C.TipoPill tipo={c.tipo} />
          </div>
          <div className="cl-card-meta">
            <span className="mono" style={{ fontSize: 11, color: 'var(--text-dim)' }}>{c.doc}</span>
            <div className="cl-stack" style={{ alignItems: 'flex-end' }}>
              <small className="cl-sub">Última compra</small>
              <span style={{ fontSize: 12 }}>{window.relDate(c.ultimaCompra)}</span>
            </div>
          </div>
          <div className="cl-card-foot">
            <div className="cl-tags">
              {(c.tags || []).slice(0, 2).map(t => <C.TagChip key={t} t={t} />)}
              <C.StatusPill status={c.status} />
            </div>
            {c.saldo > 0 && (
              <span style={{ color: 'oklch(0.55 0.18 25)', fontWeight: 600, fontSize: 12 }}>
                {window.BRL(c.saldo)}
              </span>
            )}
          </div>
        </div>
      ))}
    </div>
  );
}

// ── Split (list left + preview right) — preview rendered inside the
// drawer style. For simplicity the focused row drives the drawer too.
function ClientesSplit({ rows, onPick, focused }) {
  const C = window.Clientes;
  return (
    <div className="cl-split">
      <div className="cl-split-list">
        {rows.map(c => (
          <div key={c.id}
            className={`cl-split-row ${focused === c.id ? 'focused' : ''}`}
            onClick={() => onPick(c.id)}>
            <C.Avatar name={c.fantasia || c.nome} size={32} />
            <div style={{ flex: 1, minWidth: 0 }}>
              <div style={{ display: 'flex', alignItems: 'center', gap: 6 }}>
                <b style={{ fontSize: 13, whiteSpace: 'nowrap', overflow: 'hidden', textOverflow: 'ellipsis' }}>{c.fantasia || c.nome}</b>
                {c.vip && <Icon.Star size={11} style={{ color: 'oklch(0.72 0.18 75)', fill: 'oklch(0.78 0.16 75)', strokeWidth: 1, flex: '0 0 auto' }} />}
              </div>
              <small className="cl-sub">{c.cidade}/{c.uf} · {window.relDate(c.ultimaCompra)}</small>
            </div>
            <C.TipoPill tipo={c.tipo} />
          </div>
        ))}
      </div>
      <div className="cl-split-preview">
        {focused
          ? <SplitPreview cliente={window.CLIENTES.find(c => c.id === focused)} onPick={onPick} />
          : <div className="cl-split-empty">
              <Icon.User size={32} style={{ opacity: 0.35 }} />
              <p>Selecione um cliente à esquerda</p>
            </div>}
      </div>
    </div>
  );
}

function SplitPreview({ cliente, onPick }) {
  const C = window.Clientes;
  return (
    <div className="cl-preview">
      <header style={{ display: 'flex', alignItems: 'center', gap: 14, marginBottom: 22 }}>
        <C.Avatar name={cliente.fantasia || cliente.nome} size={56} />
        <div style={{ flex: 1, minWidth: 0 }}>
          <h2 style={{ margin: 0, fontSize: 18, fontWeight: 600 }}>{cliente.fantasia || cliente.nome}</h2>
          <p style={{ margin: '4px 0 0', fontSize: 12.5, color: 'var(--text-dim)' }}>
            {cliente.tipo === 'PJ' ? (cliente.contato || '—') : (cliente.email || '—')}
          </p>
        </div>
        <button className="cl-btn-primary" onClick={() => onPick(cliente.id)}>
          <Icon.Edit size={12} /> Editar
        </button>
      </header>
      <dl className="cl-preview-dl">
        <dt>{cliente.tipo === 'PF' ? 'CPF' : 'CNPJ'}</dt><dd className="mono">{cliente.doc}</dd>
        <dt>Telefone</dt><dd className="mono">{cliente.tel}</dd>
        <dt>E-mail</dt><dd>{cliente.email}</dd>
        <dt>Endereço</dt><dd>{cliente.endereco}, {cliente.numero} — {cliente.bairro}, {cliente.cidade}/{cliente.uf}</dd>
        <dt>Status</dt><dd><C.StatusPill status={cliente.status} /></dd>
        <dt>Tags</dt><dd><div className="cl-tags">{(cliente.tags || []).map(t => <C.TagChip key={t} t={t} />)}</div></dd>
      </dl>
    </div>
  );
}

window.ClientesPage = ClientesPage;
