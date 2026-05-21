// Listagem de Clientes — page header, filter bar, data table, states.
// Designed to live inside the Cockpit shell as the .main-body content.

const { useState: useSC, useMemo: useMC, useEffect: useEC, useRef: useRC } = React;

// ── Subcomponents ────────────────────────────────────────────────────

function StatusPill({ status }) {
  const map = {
    ativo:     { bg: 'oklch(0.93 0.07 145)', fg: 'oklch(0.32 0.10 145)', label: 'Ativo' },
    inativo:   { bg: 'oklch(0.94 0.005 90)', fg: 'oklch(0.50 0.01 80)',  label: 'Inativo' },
    bloqueado: { bg: 'oklch(0.93 0.07 25)',  fg: 'oklch(0.40 0.14 25)',  label: 'Bloqueado' },
  };
  const s = map[status] || map.inativo;
  return (
    <span style={{
      display: 'inline-flex', alignItems: 'center', gap: 5,
      background: s.bg, color: s.fg,
      fontSize: 10.5, fontWeight: 600, lineHeight: 1,
      padding: '3px 8px', borderRadius: 999,
    }}>
      <span style={{ width: 5, height: 5, borderRadius: 999, background: 'currentColor' }}></span>
      {s.label}
    </span>
  );
}

function TipoPill({ tipo }) {
  const isPF = tipo === 'PF';
  return (
    <span style={{
      display: 'inline-flex', alignItems: 'center', gap: 4,
      background: isPF ? 'var(--origin-CRM-bg)' : 'var(--origin-FIN-bg)',
      color:      isPF ? 'var(--origin-CRM-fg)' : 'var(--origin-FIN-fg)',
      fontFamily: 'var(--font-mono)',
      fontSize: 10, fontWeight: 700,
      padding: '2px 6px', borderRadius: 4, letterSpacing: '.03em',
    }}>{tipo}</span>
  );
}

function TagChip({ t }) {
  return (
    <span style={{
      fontSize: 10.5, color: 'var(--text-dim)',
      background: 'var(--bg-2)', border: '1px solid var(--border)',
      padding: '1px 7px', borderRadius: 999, lineHeight: 1.4,
      textTransform: 'lowercase',
    }}>{t}</span>
  );
}

function Avatar({ name, size = 28 }) {
  return (
    <span style={{
      width: size, height: size, borderRadius: '50%',
      background: window.avatarFor(name), color: '#fff',
      display: 'grid', placeItems: 'center',
      fontSize: Math.round(size * 0.36), fontWeight: 700, flex: '0 0 auto',
    }}>{window.initialsFor(name)}</span>
  );
}

// ── Filter bar ────────────────────────────────────────────────────────

function FilterDropdown({ label, value, options, onChange, multi = false, width = 'auto' }) {
  const [open, setOpen] = useSC(false);
  const ref = useRC(null);
  useEC(() => {
    const c = (e) => { if (ref.current && !ref.current.contains(e.target)) setOpen(false); };
    document.addEventListener('mousedown', c);
    return () => document.removeEventListener('mousedown', c);
  }, []);
  const has = multi ? value.length > 0 : !!value;
  const display = multi
    ? (value.length === 0 ? label : value.length === 1 ? value[0] : `${label} (${value.length})`)
    : (value || label);

  return (
    <div ref={ref} style={{ position: 'relative' }}>
      <button type="button" onClick={() => setOpen(o => !o)} className="cl-filter"
        style={has ? { background: 'var(--accent-soft)', color: 'var(--accent)', borderColor: 'transparent' } : {}}>
        <span style={{ textTransform: has ? 'none' : 'capitalize' }}>{display}</span>
        <Icon.ChevronDown size={10} />
      </button>
      {open && (
        <div style={{
          position: 'absolute', top: 'calc(100% + 4px)', left: 0, zIndex: 100,
          minWidth: 180, maxHeight: 320, overflowY: 'auto',
          background: 'var(--surface)', border: '1px solid var(--border)',
          borderRadius: 8, padding: 4, boxShadow: 'var(--shadow-pop)',
        }}>
          {!multi && value && (
            <button onClick={() => { onChange(''); setOpen(false); }}
              className="cl-dd-item" style={{ color: 'var(--text-dim)', borderBottom: '1px solid var(--border-2)', marginBottom: 2 }}>
              Limpar
            </button>
          )}
          {options.map(o => {
            const v = typeof o === 'object' ? o.value : o;
            const lbl = typeof o === 'object' ? o.label : o;
            const on = multi ? value.includes(v) : value === v;
            return (
              <button key={v} className={`cl-dd-item ${on ? 'on' : ''}`}
                onClick={() => {
                  if (multi) {
                    onChange(on ? value.filter(x => x !== v) : [...value, v]);
                  } else {
                    onChange(on ? '' : v);
                    setOpen(false);
                  }
                }}>
                {multi && (
                  <span className={`cl-check ${on ? 'on' : ''}`}>
                    {on && <Icon.Check size={9} />}
                  </span>
                )}
                <span>{lbl}</span>
                {!multi && on && <Icon.Check size={11} style={{ marginLeft: 'auto', color: 'var(--accent)' }} />}
              </button>
            );
          })}
        </div>
      )}
    </div>
  );
}

function ActiveChip({ label, onRemove }) {
  return (
    <span style={{
      display: 'inline-flex', alignItems: 'center', gap: 4,
      background: 'var(--accent-soft)', color: 'var(--accent)',
      borderRadius: 999, padding: '3px 6px 3px 10px',
      fontSize: 11.5, fontWeight: 500,
    }}>
      {label}
      <button onClick={onRemove} style={{
        appearance: 'none', border: 0, background: 'transparent', color: 'inherit',
        padding: 2, borderRadius: 999, display: 'grid', placeItems: 'center', cursor: 'pointer', opacity: 0.7,
      }} onMouseEnter={e => e.currentTarget.style.opacity = 1}
         onMouseLeave={e => e.currentTarget.style.opacity = 0.7}>
        <Icon.X size={11} />
      </button>
    </span>
  );
}

// ── States ────────────────────────────────────────────────────────────

function EmptyState() {
  return (
    <div className="cl-empty">
      <div className="cl-empty-ico">
        <Icon.Users size={32} />
      </div>
      <h2>Nenhum cliente cadastrado ainda</h2>
      <p>Bora começar? Cadastre o primeiro cliente da Oimpresso. Você pode importar uma planilha CSV ou colar um CNPJ que a gente preenche o resto.</p>
      <div style={{ display: 'flex', gap: 8, marginTop: 18, justifyContent: 'center' }}>
        <button className="cl-btn-primary"><Icon.UserPlus size={14} /> Novo cliente</button>
        <button className="cl-btn-ghost"><Icon.Upload size={14} /> Importar CSV</button>
      </div>
    </div>
  );
}

function NoResultsState({ search, onClear }) {
  return (
    <div className="cl-empty">
      <div className="cl-empty-ico" style={{ background: 'oklch(0.94 0.04 80)', color: 'oklch(0.55 0.13 65)' }}>
        <Icon.Search size={28} />
      </div>
      <h2>Nada encontrado pra "{search}"</h2>
      <p>Tenta ajustar os filtros ou buscar por outra coisa — nome, CNPJ, cidade ou tag.</p>
      <button className="cl-btn-ghost" onClick={onClear} style={{ marginTop: 12 }}>Limpar busca</button>
    </div>
  );
}

function LoadingSkeleton({ rows = 8, density = 'normal' }) {
  const h = density === 'compact' ? 38 : density === 'comfy' ? 56 : 46;
  return (
    <div>
      {Array.from({ length: rows }).map((_, i) => (
        <div key={i} style={{
          display: 'grid', gridTemplateColumns: '40px minmax(180px, 1.6fr) 60px 150px minmax(120px, 1fr) 130px 100px minmax(150px, 1.4fr) 36px',
          gap: 12, alignItems: 'center', padding: '0 18px', height: h,
          borderBottom: '1px solid var(--border-2)', minWidth: 980,
        }}>
          <div style={{ width: 28, height: 28, borderRadius: '50%', background: 'var(--bg-2)', opacity: 0.8 - i * 0.05 }} />
          {[0,1,2,3,4,5,6].map(c => (
            <div key={c} style={{ height: 10, borderRadius: 5, background: 'var(--bg-2)', width: `${50 + ((i+c)*7)%40}%`, opacity: 0.8 - i * 0.05 }} />
          ))}
          <div />
        </div>
      ))}
    </div>
  );
}

window.Clientes = {
  StatusPill, TipoPill, TagChip, Avatar, FilterDropdown, ActiveChip,
  EmptyState, NoResultsState, LoadingSkeleton,
};
