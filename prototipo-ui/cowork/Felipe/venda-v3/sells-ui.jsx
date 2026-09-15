/* Primitivos locais do guia de Venda — compõem sobre o DS, sem criar cor nova. */
const DS = window.OfficeImpressoDesignSystem_d7f886;
const { Button, Input, Select, Textarea, Switch, Checkbox, StatusBadge, KpiCard, PageHeader, TabBar, DataTable,
  Alert, Drawer, DrawerSection, EmptyState, Skeleton, Pagination, BulkBar, FilterChip, Dimension, Progress, Tooltip,
  AppSidebar, Breadcrumb, Modal, Chart, Avatar, TagChip, DropdownMenu, FsmStepper, Toast, DatePicker, PeriodBar } = DS;

/* Ícone do lucide — única fonte de iconografia (AP4). Nome em PascalCase, ex. "Search". */
function Icon({ name, size = 14, stroke = 1.8, style }) {
  if (!name) return null;
  const L = window.lucide;
  const node = L && (L.icons ? (L.icons[name] || L.icons[name.replace(/([a-z])([A-Z])/g, '$1-$2').toLowerCase()]) : L[name]);
  if (!node) return null;
  const filhos = Array.isArray(node) ? (Array.isArray(node[2]) ? node[2] : node) : (node.children || []);
  return (
    <svg width={size} height={size} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth={stroke}
      strokeLinecap="round" strokeLinejoin="round" aria-hidden="true" focusable="false" style={{ flex: 'none', display: 'block', ...style }}>
      {filhos.map(([tag, attrs], i) => React.createElement(tag, { key: i, ...attrs }))}
    </svg>
  );
}

function Trilho({ items }) {
  return (
    <nav aria-label="Você está em" style={{ display: 'inline-flex', alignItems: 'center', gap: 6, font: '400 12.5px/1.3 var(--font-sans)', color: 'var(--text)', minWidth: 0 }}>
      {items.map((it, i) => (
        <React.Fragment key={i}>
          {i > 0 && <span aria-hidden="true" style={{ color: 'inherit', opacity: .55 }}>/</span>}
          {it.href && i < items.length - 1
            ? <a href={it.href} style={{ color: 'inherit', opacity: .78, textDecoration: 'none' }}>{it.label}</a>
            : <b style={{ color: 'inherit', fontWeight: 600, overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>{it.label}</b>}
        </React.Fragment>
      ))}
    </nav>
  );
}

/* tom legível nos DOIS temas: mistura com --text (escuro no claro, claro no escuro) */
const tomFg = (c) => 'color-mix(in oklch, ' + c + ' 62%, var(--text))';

/* viewport estreito — usado para trocar tabela por cartão (dimensão 9) */
function useEstreito(q = '(max-width: 640px)') {
  const [v, setV] = React.useState(() => window.matchMedia(q).matches);
  React.useEffect(() => { const m = window.matchMedia(q); const h = () => setV(m.matches); m.addEventListener('change', h); return () => m.removeEventListener('change', h); }, [q]);
  return v;
}

const EST = {
  ok: { l: 'atende', c: 'var(--pos)', s: 'color-mix(in oklch, var(--pos) 12%, var(--surface))' },
  parcial: { l: 'parcial', c: 'var(--warn)', s: 'color-mix(in oklch, var(--warn) 12%, var(--surface))' },
  falta: { l: 'a criar', c: 'var(--neg)', s: 'color-mix(in oklch, var(--neg) 12%, var(--surface))' },
  nv: { l: 'não verificado', c: 'var(--text-mute)', s: 'var(--bg-2)' },
};

function Pill({ children, c = 'var(--text-dim)', s, mono, title, dot }) {
  const neutro = c === 'var(--text-dim)' || c === 'var(--text-mute)' || c === 'transparent';
  const mostraDot = dot === undefined ? !neutro : dot;
  return (
    <span title={title} style={{ display: 'inline-flex', alignItems: 'center', gap: 6, height: 20, color: tomFg(neutro ? 'var(--text-dim)' : c),
      font: (mono ? '600 10.5px/1 var(--font-mono)' : '600 10.5px/1 var(--font-sans)'), letterSpacing: '.04em', textTransform: mono ? 'none' : 'uppercase', whiteSpace: 'nowrap' }}>
      {mostraDot && <span aria-hidden="true" style={{ width: 6, height: 6, borderRadius: 999, background: c, flex: 'none' }}></span>}
      {children}
    </span>
  );
}

function EstPill({ e }) { const x = EST[e] || EST.nv; return <Pill c={x.c} s={x.s}>{x.l}</Pill>; }

function TagV0({ tag }) {
  if (!tag) return null;
  const map = { V0: ['var(--neg)', 'color-mix(in oklch, var(--neg) 12%, var(--surface))'], T0: ['var(--accent)', 'color-mix(in oklch, var(--accent) 12%, var(--surface))'], reg: ['var(--warn)', 'color-mix(in oklch, var(--warn) 12%, var(--surface))'], ux: ['var(--color-info)', 'color-mix(in oklch, var(--color-info) 12%, transparent)'], must: ['var(--text-dim)', 'var(--bg-2)'], should: ['var(--text-mute)', 'var(--bg-2)'] };
  return <>{String(tag).split(' ').filter(Boolean).map((p) => {
    const [c, s] = map[p] || ['var(--text-mute)', 'var(--bg-2)'];
    return <Pill key={p} c={c} s={s} mono>{'[' + p + ']'}</Pill>;
  })}</>;
}

/* Marcador de CU: clicável, abre o drawer do caso de uso */
function CuChip({ id, onOpen }) {
  const cu = window.SD.cuById(id);
  if (!cu) return null;
  const x = EST[cu.e] || EST.nv;
  return (
    <button type="button" onClick={() => onOpen(id)} title={cu.t}
      style={{ display: 'inline-flex', alignItems: 'center', gap: 4, height: 20, padding: '0 8px', borderRadius: 5, cursor: 'pointer', flex: 'none', whiteSpace: 'nowrap', background: x.s, color: x.c, border: '1px solid color-mix(in oklch, ' + x.c + ' 26%, transparent)', font: '600 10.5px/1 var(--font-mono)' }}>
      <span style={{ width: 5, height: 5, borderRadius: 999, background: x.c }}></span>{id.replace('CU-SELL-', 'CU-')}
    </button>
  );
}

function CuRow({ ids, onOpen }) {
  if (!ids || !ids.length) return null;
  return <div style={{ display: 'flex', flexWrap: 'wrap', gap: 4 }}>{ids.map((i) => <CuChip key={i} id={i} onOpen={onOpen} />)}</div>;
}

/* Modo produção: desligado, a tela é só a tela — sem marcador de CU, sem faixa Tier 0 */
const MetaCtx = React.createContext(false);
const useMeta = () => React.useContext(MetaCtx);
function Meta({ children }) { return useMeta() ? <>{children}</> : null; }

/* Card de seção com header colorido por domínio — padrão do módulo Vendas. */
function Sec({ title, sub, hue = 'var(--accent)', ico, right, children, cus, onOpen, pad = 16, dobra, resumo, clip = true }) {
  const meta = useMeta();
  const [aberta, setAberta] = React.useState(dobra !== 'fechada');
  const dobravel = !!dobra;
  return (
    <section style={{ background: 'var(--surface)', border: '1px solid var(--border)', borderRadius: 12, boxShadow: 'var(--shadow-soft)', overflow: clip ? 'hidden' : 'visible' }}>
      <header onClick={dobravel ? () => setAberta(!aberta) : undefined} style={{ display: 'flex', alignItems: 'center', gap: 12, padding: '12px 16px', borderBottom: (dobravel && !aberta) ? 0 : '1px solid var(--border)', background: 'color-mix(in oklch, ' + hue + ' 5%, var(--surface))', cursor: dobravel ? 'pointer' : 'default' }}>
        {ico !== false && <span style={{ width: 30, height: 30, flex: 'none', borderRadius: 8, display: 'inline-flex', alignItems: 'center', justifyContent: 'center', background: 'color-mix(in oklch, ' + hue + ' 14%, transparent)', color: tomFg(hue) }}>
          <Icon name={ico || 'List'} size={15} />
        </span>}
        <div style={{ minWidth: 0 }}>
          <h3 style={{ margin: 0, font: '600 15px/1.3 var(--font-sans)', color: 'var(--text)' }}>{title}</h3>
          {sub && <p style={{ margin: '2px 0 0', font: '11.5px/1.35 var(--font-sans)', color: 'var(--text-dim)' }}>{sub}</p>}
        </div>
        <div style={{ marginLeft: 'auto', display: 'flex', alignItems: 'center', justifyContent: 'flex-end', flexWrap: 'wrap', gap: 8 }}>
          {dobravel && !aberta && resumo && <span style={{ font: '11.5px/1 var(--font-sans)', color: 'var(--text-dim)' }}>{resumo}</span>}
          {meta && cus && <CuRow ids={cus} onOpen={onOpen} />}{right}
          {dobravel && <span style={{ color: 'var(--text-dim)', display: 'inline-flex', transform: aberta ? 'rotate(180deg)' : 'none' }}><Icon name="ChevronDown" size={15} /></span>}
        </div>
      </header>
      {(!dobravel || aberta) && (pad === 0
        ? (clip ? <div className="oi-scroll" style={{ overflowX: 'auto' }}>{children}</div> : <div>{children}</div>)
        : <div style={{ padding: pad }}>{children}</div>)}
    </section>
  );
}

const Grid = ({ cols = 4, gap = 12, children, style }) => <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(min(100%, ' + (cols >= 4 ? 190 : cols === 3 ? 230 : 280) + 'px), 1fr))', gap, ...style }}>{children}</div>;

/* line-height 1.5 e margin 4 = a MESMA caixa de rótulo do Input/Select do DS (15,75 + 4).
   Com line-height 1 os campos locais subiam ~6px ao lado dos do DS na mesma linha. */
const Lbl = ({ children, c = 'var(--text-dim)' }) => {
  const tom = /--(accent|pos|neg|warn|color-info)\b/.test(c) && !c.includes('color-mix') ? tomFg(c) : c;
  return <span style={{ display: 'block', font: '600 10.5px/1.5 var(--font-sans)', letterSpacing: '.04em', textTransform: 'uppercase', color: tom, marginBottom: 4 }}>{children}</span>;
};

/* Campo monetário pt-BR — o guard do incidente num_uf vive aqui */
function Money({ label, value, onChange, onBlur, error, hue = 'var(--text-dim)', suffix, readOnly, help, prefix = 'R$', aria }) {
  const invalido = !!error;
  const base = { fontFamily: 'var(--font-mono)' };
  const est = readOnly ? { ...base, background: 'var(--bg-2)', cursor: 'default', fontWeight: 600 }
    : invalido ? { ...base, borderColor: 'var(--neg)', boxShadow: '0 0 0 3px color-mix(in oklch, var(--neg) 16%, transparent)' } : base;
  return (
    <div>
      {label && <Lbl c={invalido ? 'var(--neg)' : hue}>{label}</Lbl>}
      <div className={'dsfa pre' + (suffix ? ' suf' : '')}>
        <span className="afx l">{prefix}</span>
        <input value={value} readOnly={readOnly} inputMode="decimal" aria-label={aria || label} aria-invalid={invalido || undefined}
          onChange={(e) => onChange && onChange(e.target.value)} onBlur={onBlur} style={est} />
        {suffix && <span className="afx r">{suffix}</span>}
      </div>
      {invalido
        ? <span role="alert" style={{ display: 'flex', alignItems: 'center', gap: 5, marginTop: 4, font: '600 11.5px/1.35 var(--font-sans)', color: tomFg('var(--neg)') }}>
            <Icon name="AlertTriangle" size={12} />{error}
          </span>
        : help && <span style={{ display: 'block', marginTop: 4, font: '11.5px/1.35 var(--font-sans)', color: 'var(--text-dim)' }}>{help}</span>}
    </div>
  );
}

/* Campo de texto com validação — o Input do DS não repassa onBlur, então o fiscal usa este */
function Campo({ label, value, onChange, onBlur, error, help, placeholder, mono = true, readOnly }) {
  const invalido = !!error;
  return (
    <div>
      {label && <Lbl c={invalido ? 'var(--neg)' : 'var(--text-dim)'}>{label}</Lbl>}
      <div className="dsfa">
        <input value={value} placeholder={placeholder} readOnly={readOnly} aria-label={label} aria-invalid={invalido || undefined}
          onChange={(e) => onChange && onChange(e.target.value)} onBlur={onBlur}
          style={invalido ? { fontFamily: mono ? 'var(--font-mono)' : 'var(--font-sans)', borderColor: 'var(--neg)', boxShadow: '0 0 0 3px color-mix(in oklch, var(--neg) 16%, transparent)' } : { fontFamily: mono ? 'var(--font-mono)' : 'var(--font-sans)' }} />
      </div>
      {invalido
        ? <span role="alert" style={{ display: 'flex', alignItems: 'center', gap: 5, marginTop: 4, font: '600 11.5px/1.35 var(--font-sans)', color: tomFg('var(--neg)') }}>
            <Icon name="AlertTriangle" size={12} />{error}
          </span>
        : help && <span style={{ display: 'block', marginTop: 4, font: '11.5px/1.35 var(--font-sans)', color: 'var(--text-dim)' }}>{help}</span>}
    </div>
  );
}

const brl = (n) => (n || n === 0) ? 'R$ ' + Number(n).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) : '—';
const num = (n, d = 2) => Number(n).toLocaleString('pt-BR', { minimumFractionDigits: d, maximumFractionDigits: d });
const fmtBR = (n) => Number(n).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
/* parse pt-BR: tira separador de milhar (SEMPRE 3 dígitos) e troca vírgula decimal — guard CU-SELL-05 */
const parseBR = (s) => { if (typeof s === 'number') return s; const t = String(s || '').replace(/\.(?=\d{3}(\D|$))/g, '').replace(',', '.'); const v = parseFloat(t); return isNaN(v) ? 0 : v; };
/* o que o frontend PODE mandar: 2 casas, sem ambiguidade de locale */
const submitSafe = (n) => Math.round((Number(n) + Number.EPSILON) * 100) / 100;

const PAY = { paid: ['Pago', 'var(--pos)', 'color-mix(in oklch, var(--pos) 12%, var(--surface))'], partial: ['Parcial', 'var(--warn)', 'color-mix(in oklch, var(--warn) 12%, var(--surface))'], due: ['A receber', 'var(--neg)', 'color-mix(in oklch, var(--neg) 12%, var(--surface))'] };
function PayPill({ p, atraso }) { const [l, c, s] = PAY[p] || PAY.due; return <Pill c={c} s={s}>{atraso && p !== 'paid' ? 'Vencido' : l}</Pill>; }

const FISCAL = { autorizada: ['NF-e autorizada', 'var(--pos)'], pendente: ['NF-e pendente', 'var(--warn)'], cancelada: ['NF-e cancelada', 'var(--neg)'], nao_emitida: ['sem NF', 'var(--text-mute)'] };
function FiscalPill({ f }) { const [l, c] = FISCAL[f] || FISCAL.nao_emitida; return <Pill c={c} s={'color-mix(in oklch, ' + c + ' 10%, transparent)'} mono>{l}</Pill>; }

const ESTAGIO_HUE = { orcamento: 'var(--text-mute)', aprovada: 'var(--color-info)', producao: 'var(--warn)', faturada: 'var(--accent)', entregue: 'var(--pos)', cancelada: 'var(--neg)' };
function EstagioPill({ k }) { const f = window.SD.fsm.find((x) => x.key === k) || {}; const c = ESTAGIO_HUE[k] || 'var(--text-mute)'; return <Pill c={c} s={'color-mix(in oklch, ' + c + ' 12%, transparent)'}>{f.l || k}</Pill>; }

/* DataCampo — campo de data com calendário em PORTAL.
   Por que não o DatePicker do DS: o popover dele é irmão absoluto dentro do campo, então
   QUALQUER ancestral com overflow:auto o corta — medido: 176px cortados no modal de
   lançamento e 202px no drawer, mais barra horizontal. Aqui o calendário vive no body,
   com posição fixa calculada do gatilho, e escapa de todo scroller. (Exceção AP2 nº3.) */
const DIAS_SEMANA = ['D', 'S', 'T', 'Q', 'Q', 'S', 'S'];
const MESES = ['janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho', 'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'];
const dParse = (v) => {
  if (!v) return null;
  if (v instanceof Date) return isNaN(v) ? null : v;
  const br = /^(\d{2})\/(\d{2})\/(\d{4})$/.exec(String(v));
  if (br) return new Date(+br[3], +br[2] - 1, +br[1]);
  const iso = /^(\d{4})-(\d{2})-(\d{2})/.exec(String(v));
  if (iso) return new Date(+iso[1], +iso[2] - 1, +iso[3]);
  const d = new Date(v);
  return isNaN(d) ? null : d;
};
const dTexto = (d) => d ? String(d.getDate()).padStart(2, '0') + '/' + String(d.getMonth() + 1).padStart(2, '0') + '/' + d.getFullYear() : '';
const mesmoDia = (a, b) => !!a && !!b && a.getFullYear() === b.getFullYear() && a.getMonth() === b.getMonth() && a.getDate() === b.getDate();

function DataCampo({ label, value, onChange, help, disabled, placeholder = 'dd/mm/aaaa' }) {
  const sel = dParse(value);
  const [aberto, setAberto] = React.useState(false);
  const [pos, setPos] = React.useState(null);
  const [vista, setVista] = React.useState(() => { const d = sel || new Date(); return { a: d.getFullYear(), m: d.getMonth() }; });
  const gatilho = React.useRef(null);
  const painel = React.useRef(null);

  const medir = () => {
    const el = gatilho.current && gatilho.current.querySelector('input');
    if (!el) return;
    const r = el.getBoundingClientRect();
    const alt = 300, larg = 268;
    const abaixo = window.innerHeight - r.bottom > alt + 8;
    setPos({
      left: Math.max(8, Math.min(r.left, window.innerWidth - larg - 8)),
      top: abaixo ? r.bottom + 4 : Math.max(8, r.top - alt - 4),
    });
  };
  const abrir = () => { if (disabled) return; const d = sel || new Date(); setVista({ a: d.getFullYear(), m: d.getMonth() }); medir(); setAberto(true); };

  React.useEffect(() => {
    if (!aberto) return;
    const fora = (e) => { if (!painel.current || painel.current.contains(e.target)) return; if (gatilho.current && gatilho.current.contains(e.target)) return; setAberto(false); };
    const tecla = (e) => {
      if (e.key !== 'Escape') return;
      /* corta o evento em window/captura: o Modal e o Drawer do DS têm handler de Escape
         no document e, registrados antes, fechariam o overlay junto — stopPropagation
         não basta (mesmo nó exige stopImmediatePropagation). */
      e.preventDefault();
      e.stopImmediatePropagation();
      e.stopPropagation();
      setAberto(false);
    };
    const remede = () => medir();
    document.addEventListener('mousedown', fora, true);
    window.addEventListener('keydown', tecla, true);
    window.addEventListener('resize', remede);
    window.addEventListener('scroll', remede, true);
    return () => {
      document.removeEventListener('mousedown', fora, true);
      window.removeEventListener('keydown', tecla, true);
      window.removeEventListener('resize', remede);
      window.removeEventListener('scroll', remede, true);
    };
  }, [aberto]);

  const escolher = (d) => { onChange && onChange(d); setAberto(false); };
  const primeiro = new Date(vista.a, vista.m, 1);
  const inicio = primeiro.getDay();
  const dias = new Date(vista.a, vista.m + 1, 0).getDate();
  const hoje = new Date();
  const celulas = [];
  for (let i = 0; i < inicio; i++) celulas.push(null);
  for (let i = 1; i <= dias; i++) celulas.push(new Date(vista.a, vista.m, i));
  const passo = (n) => setVista((v) => { const d = new Date(v.a, v.m + n, 1); return { a: d.getFullYear(), m: d.getMonth() }; });

  const cal = (
    <div ref={painel} role="dialog" aria-label="Escolher data"
      style={{ position: 'fixed', left: (pos || {}).left || 0, top: (pos || {}).top || 0, zIndex: 90, width: 268, padding: 10, background: 'var(--surface, #fff)', backgroundColor: 'var(--surface, #fff)', border: '1px solid var(--border)', borderRadius: 12, boxShadow: '0 20px 50px -10px color-mix(in oklch, var(--text) 45%, transparent)' }}>
      <div style={{ display: 'flex', alignItems: 'center', gap: 6, marginBottom: 8 }}>
        <button type="button" aria-label="Mês anterior" onClick={() => passo(-1)} style={{ width: 26, height: 26, borderRadius: 6, border: '1px solid var(--border)', background: 'var(--surface)', color: 'var(--text-dim)', cursor: 'pointer', display: 'inline-flex', alignItems: 'center', justifyContent: 'center' }}><Icon name="ChevronLeft" size={14} /></button>
        <b style={{ flex: 1, textAlign: 'center', font: '600 12.5px/1 var(--font-sans)', color: 'var(--text)' }}>{MESES[vista.m]} {vista.a}</b>
        <button type="button" aria-label="Próximo mês" onClick={() => passo(1)} style={{ width: 26, height: 26, borderRadius: 6, border: '1px solid var(--border)', background: 'var(--surface)', color: 'var(--text-dim)', cursor: 'pointer', display: 'inline-flex', alignItems: 'center', justifyContent: 'center' }}><Icon name="ChevronRight" size={14} /></button>
      </div>
      <div style={{ display: 'grid', gridTemplateColumns: 'repeat(7, 1fr)', gap: 2 }}>
        {DIAS_SEMANA.map((d, i) => <span key={i} aria-hidden="true" style={{ textAlign: 'center', font: '600 10px/20px var(--font-sans)', color: 'var(--text-dim)' }}>{d}</span>)}
        {celulas.map((d, i) => d === null ? <span key={'v' + i}></span> : (
          <button key={i} type="button" onClick={() => escolher(d)}
            aria-current={mesmoDia(d, sel) ? 'date' : undefined}
            style={{ height: 28, borderRadius: 6, cursor: 'pointer', font: (mesmoDia(d, sel) || mesmoDia(d, hoje) ? '600 ' : '') + '12px/1 var(--font-mono)',
              border: '1px solid ' + (mesmoDia(d, sel) ? 'var(--accent)' : mesmoDia(d, hoje) ? 'var(--border)' : 'transparent'),
              background: mesmoDia(d, sel) ? 'var(--accent)' : 'transparent',
              color: mesmoDia(d, sel) ? 'var(--accent-fg)' : 'var(--text)' }}>{d.getDate()}</button>
        ))}
      </div>
      <div style={{ display: 'flex', gap: 6, marginTop: 8, paddingTop: 8, borderTop: '1px solid var(--border)' }}>
        <Button size="sm" onClick={() => escolher(new Date())}>Hoje</Button>
        {sel && <Button size="sm" onClick={() => escolher(null)}>Limpar</Button>}
      </div>
    </div>
  );

  return (
    <div ref={gatilho} onClick={abrir}>
      <Input label={label} help={help} disabled={disabled} readOnly value={dTexto(sel)} placeholder={placeholder} onChange={() => {}} />
      {aberto && pos && ReactDOM.createPortal(cal, document.querySelector('.cockpit') || document.body)}
    </div>
  );
}

/* Faixa Tier 0 — só no modo produção */
function TierBar({ children, tone = 'neg' }) {
  if (!useMeta()) return null;
  const c = tone === 'accent' ? 'var(--accent)' : 'var(--neg)';
  return (
    <div style={{ display: 'flex', alignItems: 'center', gap: 12, padding: '8px 12px', borderRadius: 8, background: tone === 'accent' ? 'color-mix(in oklch, var(--accent) 12%, var(--surface))' : 'color-mix(in oklch, var(--neg) 12%, var(--surface))', border: '1px solid color-mix(in oklch, ' + c + ' 24%, transparent)', font: '12.5px/1.4 var(--font-sans)', color: 'var(--text-dim)' }}>
      <Pill c={c} s="transparent">{tone === 'accent' ? 'Tier 0 · multi-tenant' : 'Tier 0 · Regra Mestre'}</Pill>
      <span style={{ minWidth: 0 }}>{children}</span>
    </div>
  );
}

/* Tela sem contrato — as 6 do §1.1 sem casos.md */
function SemContrato({ tela, onda, children }) {
  const t = window.SD.telaByKey(tela);
  return (
    <div style={{ display: 'flex', flexDirection: 'column', gap: 16 }}>
      <Meta><Alert tone="warn" title={'Esta tela não tem ' + '\u0060casos.md\u0060' + ' — nenhum CU a defende'}>
        <p style={{ margin: 0 }}>{t.papel}</p>
        <p style={{ margin: '6px 0 0' }}>Escrever o contrato é o item <b>{onda}</b> do roteiro (onda 6 · "Fechar o trio e virar a flag"). Até lá, o que esta tela faz em produção não é coberto por teste nenhum — e ela mexe/exibe valor de venda emitida.</p>
      </Alert></Meta>
      {children}
    </div>
  );
}

Object.assign(window, { DS, Icon, Trilho, Campo, DataCampo, dParse, dTexto, tomFg, useEstreito, Skeleton, Button, Input, Select, Textarea, Switch, Checkbox, StatusBadge, KpiCard, PageHeader, TabBar, DataTable, Alert, Drawer, DrawerSection, EmptyState, Pagination, BulkBar, FilterChip, Dimension, Progress, Tooltip, AppSidebar, Breadcrumb, Modal, Chart, Avatar, TagChip, DropdownMenu, FsmStepper, Toast, DatePicker, PeriodBar, EST, Pill, EstPill, TagV0, CuChip, CuRow, Sec, Grid, Lbl, Money, brl, num, parseBR, fmtBR, submitSafe, PayPill, FiscalPill, EstagioPill, ESTAGIO_HUE, TierBar, MetaCtx, useMeta, Meta, SemContrato });
