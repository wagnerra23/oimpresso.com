/**
 * AppSidebar — operational shell navigation rail, aligned with production
 * (oimpresso.com · resources/js/Components/cockpit/Sidebar.tsx, sidebar v3 / ADR 0180),
 * with a premium dark-cockpit treatment: depth bloom, hue-tinted active pills with
 * a glowing rail, soft hairline surfaces, and a thin custom scrollbar.
 *
 * Top → bottom: CompanyPicker (gradient avatar + business dropdown) ·
 * SidebarShortcuts (IA · Forja · Atendimento, fixed top — NOT groups) ·
 * 8 canon collapsible groups (CADASTRO → COMERCIAL → FINANÇAS → FISCAL →
 * PRODUÇÃO → ESTOQUE → RH → SISTEMA), each headed by its OKLCH hue dot ·
 * SidebarFooter (user button + dropdown: perfil · Superadmin · status ·
 * Aparência · Modo de trabalho · atalhos · sair).
 *
 * Dark-fixed (Wagner: "menu fundo black") via the --sb-* cockpit tokens.
 * Dependency-free (global React + tokens). Group hues mirror SIDEBAR_GROUP_HUE.
 *
 * Pass `active` (an item label, case-insensitive) to highlight the current screen.
 */
export function AppSidebar({
  active = 'Clientes',
  company = 'Office Impresso',
  businesses = [
    { id: 1, nome: 'Office Impresso', iniciais: 'OI', ativa: true },
    { id: 4, nome: 'Gráfica Central', iniciais: 'GC' },
    { id: 7, nome: 'Martinho Caçambas', iniciais: 'MC' },
  ],
  user = { nome: 'Wagner Ramos', nomeCurto: 'Wagner', email: 'wagner@officeimpresso.com', cargo: 'Administrador', iniciais: 'WR' },
}) {
  const h = React.createElement;
  const { useState, useEffect, useRef } = React;

  // ── canon hues (SIDEBAR_GROUP_HUE) ────────────────────────────────────
  const hueColor = (hue, l, c) => `oklch(${l} ${c} ${hue})`;
  const tint = (hue, a, l, c) => `oklch(${l || 0.66} ${c || 0.16} ${hue} / ${a})`;
  const gradientFor = (id) => {
    const hue = (id * 47) % 360;
    return `linear-gradient(135deg, oklch(0.58 0.16 ${hue}), oklch(0.66 0.16 ${(hue + 60) % 360}))`;
  };

  // premium chrome: thin scrollbar + reduced-motion-safe injected once
  useEffect(() => {
    const id = 'ds-appsidebar-chrome';
    if (document.getElementById(id)) return;
    const el = document.createElement('style');
    el.id = id;
    el.textContent = `[data-ds-sb]{scrollbar-width:thin;scrollbar-color:var(--sb-border) transparent}` +
      `[data-ds-sb]::-webkit-scrollbar{width:7px}` +
      `[data-ds-sb]::-webkit-scrollbar-thumb{background:var(--sb-border);border-radius:99px;border:2px solid transparent;background-clip:content-box}` +
      `[data-ds-sb]::-webkit-scrollbar-thumb:hover{background:var(--sb-text-dim);background-clip:content-box}` +
      `[data-ds-sb]::-webkit-scrollbar-track{background:transparent}`;
    document.head.appendChild(el);
  }, []);

  // ── canon taxonomy: 8 groups, items in their production-correct group ──
  const SHORTCUTS = [
    { key: 'ia', label: 'IA', hue: 215, badge: '3' },
    { key: 'forja', label: 'Forja', hue: 275 },
    { key: 'atendimento', label: 'Atendimento', hue: 30, badge: '6' },
  ];
  const GROUPS = [
    { key: 'cadastro', label: 'CADASTRO', hue: 202, items: [
      { label: 'Contatos' }, { label: 'Clientes', count: '15' }, { label: 'Produtos' }, { label: 'Catálogo' } ] },
    { key: 'comercial', label: 'COMERCIAL', hue: 55, items: [
      { label: 'CRM' }, { label: 'Vendas' }, { label: 'Oficina Auto', count: '4' } ] },
    { key: 'financas', label: 'FINANÇAS', hue: 145, items: [
      { label: 'Caixa' }, { label: 'Cobrança', flag: 'F1' }, { label: 'Financeiro', count: '5' }, { label: 'Cobrança Recorrente' } ] },
    { key: 'fiscal', label: 'FISCAL', hue: 175, items: [
      { label: 'Notas Fiscais', flag: 'NF-e' }, { label: 'Manifestação' }, { label: 'Certificado' } ] },
    { key: 'producao', label: 'PRODUÇÃO', hue: 8, items: [
      { label: 'Ordens de Serviço', count: '16' }, { label: 'Comunicação Visual' }, { label: 'Reparar' } ] },
    { key: 'estoque', label: 'ESTOQUE', hue: 315, items: [
      { label: 'Compras' }, { label: 'Transferências de ações' }, { label: 'Ajuste de estoque' }, { label: 'Gestão de ativos' } ] },
    { key: 'pessoas', label: 'RH', hue: 88, items: [
      { label: 'HRM' }, { label: 'Ponto', count: '12' }, { label: 'Folha' } ] },
    { key: 'sistema', label: 'SISTEMA', hue: 245, items: [
      { label: 'Auditoria' }, { label: 'Relatórios' }, { label: 'Modelos de notificação' }, { label: 'Planilha' } ] },
  ];

  const isActive = (label) => label.toLowerCase() === String(active).toLowerCase();

  // ── state ─────────────────────────────────────────────────────────────
  const lsRead = (k, def) => { try { const v = localStorage.getItem(k); return v === null ? def : v === '1'; } catch (e) { return def; } };
  const lsWrite = (k, b) => { try { localStorage.setItem(k, b ? '1' : '0'); } catch (e) {} };

  const [companyOpen, setCompanyOpen] = useState(false);
  const [userOpen, setUserOpen] = useState(false);
  const [sub, setSub] = useState(null); // 'super' | 'status' | 'aparencia' | 'vibes' | null
  const [theme, setTheme] = useState('dark');
  const [vibe, setVibe] = useState('workspace');
  const [status, setStatus] = useState('disponivel');
  const [groups, setGroups] = useState(() => {
    const o = {}; GROUPS.forEach((g) => { o[g.key] = lsRead('oimpresso.ds.sb.group.' + g.key, true); }); return o;
  });
  const toggleGroup = (k) => setGroups((s) => { const v = !s[k]; lsWrite('oimpresso.ds.sb.group.' + k, v); return { ...s, [k]: v }; });

  const cpRef = useRef(null);
  const userRef = useRef(null);
  useEffect(() => {
    const fn = (e) => {
      if (cpRef.current && !cpRef.current.contains(e.target)) setCompanyOpen(false);
      if (userRef.current && !userRef.current.contains(e.target)) { setUserOpen(false); setSub(null); }
    };
    document.addEventListener('mousedown', fn);
    return () => document.removeEventListener('mousedown', fn);
  }, []);

  // ── shared bits ─────────────────────────────────────────────────────────
  const chevron = (dir, size) => h('svg', { width: size || 12, height: size || 12, viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', strokeWidth: 2.2, strokeLinecap: 'round', strokeLinejoin: 'round', style: { flex: 'none', transition: 'transform .18s var(--ease, ease)' } },
    dir === 'down' ? h('path', { d: 'M6 9l6 6 6-6' }) : dir === 'up' ? h('path', { d: 'M18 15l-6-6-6 6' }) : h('path', { d: 'M9 6l6 6-6 6' }));
  const check = (size) => h('svg', { width: size || 14, height: size || 14, viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', strokeWidth: 2.4, strokeLinecap: 'round', strokeLinejoin: 'round', style: { flex: 'none' } }, h('path', { d: 'M5 12l5 5L20 6' }));
  const dot = (hue, size, glow) => h('span', { style: { width: size || 7, height: size || 7, borderRadius: 99, flex: 'none', background: hueColor(hue, 0.68, 0.16), boxShadow: glow === false ? 'none' : `0 0 7px ${tint(hue, 0.55, 0.7, 0.18)}` } });

  const badge = (txt, hue) => h('span', { style: {
    marginLeft: 'auto', display: 'inline-flex', alignItems: 'center', justifyContent: 'center',
    minWidth: 16, height: 16, padding: '0 5px', borderRadius: 99,
    background: hueColor(hue, 0.62, 0.17), color: '#fff', font: '600 9.5px/1 var(--font-mono)',
    boxShadow: `0 0 0 1px ${tint(hue, 0.35, 0.7, 0.18)}, 0 1px 4px ${tint(hue, 0.45, 0.5, 0.18)}`,
  } }, txt);
  const count = (txt, on, hue) => h('span', { style: { marginLeft: 'auto', font: '600 10px/1 var(--font-mono)', color: on ? hueColor(hue, 0.82, 0.10) : 'var(--sb-text-dim)' } }, txt);
  const flag = (txt) => h('span', { style: { marginLeft: 'auto', font: '600 9px/1 var(--font-mono)', color: 'var(--warn)', letterSpacing: '.02em' } }, txt);

  const rowHover = (cur) => ({
    onMouseEnter: (e) => { if (e.currentTarget.dataset.on !== '1') e.currentTarget.style.background = 'var(--sb-hover)'; },
    onMouseLeave: (e) => { if (e.currentTarget.dataset.on !== '1') e.currentTarget.style.background = cur || 'transparent'; },
  });
  const activePill = (hue) => `linear-gradient(90deg, ${tint(hue, 0.22, 0.62, 0.16)}, ${tint(hue, 0.05, 0.62, 0.16)})`;

  // ── CompanyPicker ───────────────────────────────────────────────────────
  const ativa = businesses.find((b) => b.ativa) || businesses[0] || { id: 1, nome: company, iniciais: company.slice(0, 2).toUpperCase() };
  const companyPicker = h('div', { ref: cpRef, style: { position: 'relative', marginBottom: 8 } },
    h('button', { type: 'button', onClick: () => setCompanyOpen((v) => !v), ...rowHover('var(--sb-bg-2)'), style: {
      display: 'flex', alignItems: 'center', gap: 10, width: '100%', padding: '8px 9px', border: '1px solid var(--sb-border)',
      borderRadius: 11, background: 'linear-gradient(180deg, color-mix(in oklch, var(--sb-bg-2) 86%, #fff 6%), var(--sb-bg-2))',
      color: 'var(--sb-text-hi)', cursor: 'pointer', textAlign: 'left', font: 'inherit',
      boxShadow: 'inset 0 1px 0 rgba(255,255,255,.05), 0 1px 2px rgba(0,0,0,.25)',
    } },
      h('span', { style: { display: 'inline-flex', width: 30, height: 30, alignItems: 'center', justifyContent: 'center', borderRadius: 8, background: gradientFor(ativa.id), color: '#fff', font: '600 11px/1 var(--font-mono)', flex: 'none', boxShadow: 'inset 0 0 0 1px rgba(255,255,255,.14), 0 2px 6px rgba(0,0,0,.3)' } }, ativa.iniciais),
      h('span', { style: { minWidth: 0, flex: 1 } },
        h('span', { style: { display: 'block', fontWeight: 600, fontSize: 13, letterSpacing: '-0.01em', whiteSpace: 'nowrap', overflow: 'hidden', textOverflow: 'ellipsis' } }, ativa.nome),
        h('span', { style: { display: 'block', fontSize: 10, color: 'var(--sb-text-dim)', textTransform: 'uppercase', letterSpacing: '.06em', marginTop: 1 } }, 'trocar empresa')),
      h('span', { style: { color: 'var(--sb-text-dim)' } }, chevron('down', 14))),
    companyOpen && h('div', { style: {
      position: 'absolute', top: '100%', left: 0, right: 0, marginTop: 6, zIndex: 30, padding: 5,
      background: 'var(--sb-bg-2)', border: '1px solid var(--sb-border)', borderRadius: 11, boxShadow: 'var(--shadow-pop)',
    } },
      h('div', { style: { padding: '6px 9px 4px', font: '600 9.5px/1 var(--font-sans)', letterSpacing: '.08em', textTransform: 'uppercase', color: 'var(--sb-text-dim)' } }, 'Empresas'),
      businesses.map((b) => h('button', { key: b.id, type: 'button', onClick: () => setCompanyOpen(false), ...rowHover('transparent'), style: {
        display: 'flex', alignItems: 'center', gap: 9, width: '100%', padding: '6px 8px', borderRadius: 8, border: 'none',
        background: 'transparent', color: 'var(--sb-text)', cursor: 'pointer', textAlign: 'left', font: 'inherit', fontSize: 12.5,
      } },
        h('span', { style: { display: 'inline-flex', width: 22, height: 22, alignItems: 'center', justifyContent: 'center', borderRadius: 6, background: gradientFor(b.id), color: '#fff', font: '600 9px/1 var(--font-mono)', flex: 'none', boxShadow: 'inset 0 0 0 1px rgba(255,255,255,.12)' } }, b.iniciais),
        h('span', { style: { flex: 1, whiteSpace: 'nowrap', overflow: 'hidden', textOverflow: 'ellipsis', color: b.ativa ? 'var(--sb-text-hi)' : 'var(--sb-text)' } }, b.nome),
        b.ativa && h('span', { style: { color: 'var(--accent-2)' } }, check(14)))),
      h('div', { style: { height: 1, background: 'var(--sb-border)', margin: '5px 0' } }),
      h('button', { type: 'button', ...rowHover('transparent'), style: { display: 'flex', alignItems: 'center', width: '100%', padding: '6px 8px', borderRadius: 8, border: 'none', background: 'transparent', color: 'var(--sb-text-dim)', cursor: 'pointer', font: 'inherit', fontSize: 12 } }, '+ Adicionar empresa')));

  // ── SidebarShortcuts (fixed top) ─────────────────────────────────────────
  const shortcuts = h('div', { style: { display: 'flex', flexDirection: 'column', gap: 3, marginBottom: 4 } },
    SHORTCUTS.map((s) => {
      const on = isActive(s.label);
      return h('a', { key: s.key, href: '#', 'data-on': on ? '1' : '0', ...rowHover(on ? activePill(s.hue) : 'transparent'), style: {
        display: 'flex', alignItems: 'center', gap: 10, padding: '7px 10px', borderRadius: 9, textDecoration: 'none',
        transition: 'background .14s', background: on ? activePill(s.hue) : 'transparent',
        boxShadow: on ? `inset 0 0 0 1px ${tint(s.hue, 0.20, 0.7, 0.18)}` : 'none',
        color: on ? 'var(--sb-text-hi)' : 'var(--sb-text)', fontWeight: on ? 600 : 500, fontSize: 13,
      } },
        dot(s.hue, 8),
        s.label,
        s.badge ? badge(s.badge, s.hue) : null);
    }));

  // ── Groups (collapsible accordion) ───────────────────────────────────────
  const groupBlocks = GROUPS.map((g) => {
    const open = groups[g.key];
    return h('div', { key: g.key, style: { marginTop: 3 } },
      h('button', { type: 'button', onClick: () => toggleGroup(g.key), style: {
        display: 'flex', alignItems: 'center', gap: 8, width: '100%', padding: '9px 9px 6px', border: 'none', background: 'transparent',
        cursor: 'pointer', font: '700 9.5px/1 var(--font-sans)', letterSpacing: '.1em', textTransform: 'uppercase', color: 'var(--sb-text-dim)',
        opacity: open ? 1 : 0.82, transition: 'opacity .14s',
      },
        onMouseEnter: (e) => { e.currentTarget.style.opacity = 1; },
        onMouseLeave: (e) => { e.currentTarget.style.opacity = open ? 1 : 0.82; },
      },
        h('span', { style: { color: 'var(--sb-text-dim)', transform: open ? 'rotate(0)' : 'rotate(-90deg)', transition: 'transform .18s', display: 'inline-flex' } }, chevron('down', 10)),
        dot(g.hue, 7),
        h('span', { style: { color: hueColor(g.hue, 0.82, 0.07) } }, g.label),
        h('span', { style: { marginLeft: 'auto', font: '600 10px/1 var(--font-mono)', color: 'var(--sb-text-dim)', opacity: 0.7 } }, g.items.length)),
      open && h('div', { style: { display: 'flex', flexDirection: 'column', gap: 1 } },
        g.items.map((it) => {
          const on = isActive(it.label);
          return h('a', { key: it.label, href: '#', 'data-on': on ? '1' : '0', ...rowHover(on ? activePill(g.hue) : 'transparent'), style: {
            display: 'flex', alignItems: 'center', gap: 10, padding: '6px 9px 6px 11px', borderRadius: 9, textDecoration: 'none',
            transition: 'background .14s', background: on ? activePill(g.hue) : 'transparent',
            boxShadow: on ? `inset 0 0 0 1px ${tint(g.hue, 0.18, 0.7, 0.18)}` : 'none',
            color: on ? 'var(--sb-text-hi)' : 'var(--sb-text)', fontWeight: on ? 600 : 400, fontSize: 13, whiteSpace: 'nowrap',
          } },
            h('span', { style: { width: 3, height: 15, borderRadius: 2, flex: 'none', background: on ? hueColor(g.hue, 0.7, 0.16) : 'transparent', boxShadow: on ? `0 0 9px ${tint(g.hue, 0.7, 0.7, 0.18)}` : 'none' } }),
            h('span', { style: { width: 5, height: 5, borderRadius: 99, flex: 'none', background: on ? hueColor(g.hue, 0.7, 0.16) : 'var(--sb-text-dim)', opacity: on ? 1 : 0.5 } }),
            it.label,
            it.badge ? badge(it.badge, g.hue) : it.count ? count(it.count, on, g.hue) : it.flag ? flag(it.flag) : null);
        })));
  });

  // ── SidebarFooter (user dropdown) ─────────────────────────────────────────
  const umItem = (label, opts = {}) => h(opts.button ? 'button' : 'a', {
    key: label, href: opts.button ? undefined : (opts.href || '#'), type: opts.button ? 'button' : undefined,
    onClick: opts.onClick, 'data-on': opts.active ? '1' : '0', ...rowHover(opts.active ? 'var(--sb-active)' : 'transparent'), style: {
      display: 'flex', alignItems: 'center', gap: 9, width: '100%', padding: '7px 9px', borderRadius: 8, border: 'none', textDecoration: 'none',
      background: opts.active ? 'var(--sb-active)' : 'transparent', color: 'var(--sb-text)', cursor: 'pointer', textAlign: 'left', font: 'inherit', fontSize: 12.5,
    } },
    opts.lead,
    h('span', { style: { flex: 1, color: opts.dim ? 'var(--sb-text-dim)' : undefined } }, label),
    opts.trail);

  const STATUS = { disponivel: { l: 'Disponível', h: 145 }, ausente: { l: 'Ausente', h: 80 }, ocupado: { l: 'Não perturbe', h: 25 } };
  const VIBES = [
    { id: 'workspace', label: 'workspace', desc: 'Denso e formal — padrão', hue: 295 },
    { id: 'daylight', label: 'daylight', desc: 'Tons quentes, mais ar', hue: 60 },
    { id: 'focus', label: 'focus', desc: 'Alto contraste', hue: 240 },
  ];
  const statusDot = (hue, size) => h('span', { style: { width: size || 9, height: size || 9, borderRadius: 99, flex: 'none', background: hueColor(hue, 0.72, 0.18), boxShadow: `0 0 7px ${tint(hue, 0.6, 0.72, 0.18)}` } });

  const subPanel = (title, lead, rows) => h('div', { style: {
    position: 'absolute', bottom: 0, left: 'calc(100% + 6px)', width: 210, padding: 5, zIndex: 40,
    background: 'var(--sb-bg-2)', border: '1px solid var(--sb-border)', borderRadius: 11, boxShadow: 'var(--shadow-pop)',
  } },
    h('div', { style: { display: 'flex', alignItems: 'center', gap: 8, padding: '7px 9px 5px', font: '600 11px/1 var(--font-sans)', color: 'var(--sb-text-hi)' } }, lead, title),
    rows);

  const userMenu = userOpen && h('div', { style: {
    position: 'absolute', bottom: 'calc(100% + 8px)', left: 0, right: 0, zIndex: 35, padding: 5,
    background: 'var(--sb-bg-2)', border: '1px solid var(--sb-border)', borderRadius: 11, boxShadow: 'var(--shadow-pop)',
  } },
    h('div', { style: { display: 'flex', alignItems: 'center', gap: 9, padding: '8px 9px 9px' } },
      h('span', { style: { display: 'inline-flex', width: 32, height: 32, alignItems: 'center', justifyContent: 'center', borderRadius: 9, background: gradientFor(2), color: '#fff', font: '600 11px/1 var(--font-mono)', flex: 'none', boxShadow: 'inset 0 0 0 1px rgba(255,255,255,.14)' } }, user.iniciais),
      h('span', { style: { minWidth: 0 } },
        h('span', { style: { display: 'block', fontWeight: 600, fontSize: 13, color: 'var(--sb-text-hi)' } }, user.nome),
        h('span', { style: { display: 'block', fontSize: 10.5, color: 'var(--sb-text-dim)', whiteSpace: 'nowrap', overflow: 'hidden', textOverflow: 'ellipsis' } }, user.email))),
    h('div', { style: { height: 1, background: 'var(--sb-border)', margin: '0 4px 4px' } }),
    umItem('Meu perfil', { href: '#', lead: dot(245, 6) }),

    // Superadmin cascade
    h('div', { style: { position: 'relative' } },
      umItem('Superadmin', { button: true, active: sub === 'super', onClick: () => setSub((s) => s === 'super' ? null : 'super'), lead: statusDot(25, 8), trail: h('span', { style: { color: 'var(--sb-text-dim)' } }, chevron('right', 12)) }),
      sub === 'super' && subPanel('Superadmin', statusDot(25, 8),
        ['Módulos', 'Backup', 'CMS', 'Conector', 'Personalizar'].map((l) => umItem(l, { href: '#', lead: dot(25, 5) })))),

    // Status cascade
    h('div', { style: { position: 'relative' } },
      umItem(STATUS[status].l, { button: true, active: sub === 'status', onClick: () => setSub((s) => s === 'status' ? null : 'status'), lead: statusDot(STATUS[status].h, 9), trail: h('span', { style: { color: 'var(--sb-text-dim)' } }, chevron('right', 12)) }),
      sub === 'status' && subPanel('Status', statusDot(STATUS[status].h, 9),
        Object.keys(STATUS).map((k) => umItem(STATUS[k].l, { button: true, active: status === k, onClick: () => { setStatus(k); setSub(null); }, lead: statusDot(STATUS[k].h, 9), trail: status === k ? h('span', { style: { color: 'var(--accent-2)' } }, check(13)) : null })))),

    // Aparência cascade
    h('div', { style: { position: 'relative' } },
      umItem('Aparência', { button: true, active: sub === 'aparencia', onClick: () => setSub((s) => s === 'aparencia' ? null : 'aparencia'), lead: dot(280, 6), trail: h('span', { style: { color: 'var(--sb-text-dim)' } }, chevron('right', 12)) }),
      sub === 'aparencia' && subPanel('Aparência', dot(280, 6),
        [['light', 'Claro'], ['dark', 'Escuro'], ['system', 'Sistema']].map(([k, l]) => umItem(l, { button: true, active: theme === k, onClick: () => { setTheme(k); setSub(null); }, lead: dot(280, 5), trail: theme === k ? h('span', { style: { color: 'var(--accent-2)' } }, check(13)) : null })))),

    // Modo de trabalho cascade
    h('div', { style: { position: 'relative' } },
      umItem('Modo de trabalho', { button: true, active: sub === 'vibes', onClick: () => setSub((s) => s === 'vibes' ? null : 'vibes'), lead: dot(295, 6), trail: h('span', { style: { color: 'var(--sb-text-dim)' } }, chevron('right', 12)) }),
      sub === 'vibes' && subPanel('Modo de trabalho', dot(295, 6),
        VIBES.map((v) => umItem(v.label, { button: true, active: vibe === v.id, onClick: () => { setVibe(v.id); setSub(null); }, lead: dot(v.hue, 9), trail: vibe === v.id ? h('span', { style: { color: 'var(--accent-2)' } }, check(13)) : null })))),

    h('div', { style: { height: 1, background: 'var(--sb-border)', margin: '4px 4px' } }),
    umItem('Atalhos', { href: '#', lead: dot(200, 6), trail: h('span', { style: { font: '600 10px/1 var(--font-mono)', color: 'var(--sb-text-dim)' } }, '⌘/') }),
    umItem('Central de ajuda', { href: '#', lead: dot(215, 6) }),
    h('div', { style: { height: 1, background: 'var(--sb-border)', margin: '4px 4px' } }),
    umItem('Sair', { href: '#', lead: dot(25, 6), dim: true }));

  const footer = h('div', { ref: userRef, style: { position: 'relative', marginTop: 10, paddingTop: 10, borderTop: '1px solid var(--sb-border)' } },
    userMenu,
    h('button', { type: 'button', onClick: () => { setUserOpen((v) => !v); setSub(null); }, ...rowHover('transparent'), style: {
      display: 'flex', alignItems: 'center', gap: 9, width: '100%', padding: '7px 8px', border: '1px solid transparent', borderRadius: 10,
      background: 'transparent', color: 'var(--sb-text)', cursor: 'pointer', textAlign: 'left', font: 'inherit',
    } },
      h('span', { style: { display: 'inline-flex', width: 30, height: 30, alignItems: 'center', justifyContent: 'center', borderRadius: 8, background: gradientFor(2), color: '#fff', font: '600 10px/1 var(--font-mono)', flex: 'none', boxShadow: 'inset 0 0 0 1px rgba(255,255,255,.12)' } }, user.iniciais),
      h('span', { style: { minWidth: 0, flex: 1 } },
        h('span', { style: { display: 'block', fontWeight: 600, fontSize: 12.5, color: 'var(--sb-text-hi)' } }, user.nomeCurto),
        h('span', { style: { display: 'block', fontSize: 10.5, color: 'var(--sb-text-dim)' } }, user.cargo)),
      h('span', { style: { color: 'var(--sb-text-dim)' } }, chevron('up', 12))));

  // ── shell ─────────────────────────────────────────────────────────────
  return h('aside', { 'data-ds-sb': '', style: {
    position: 'relative', width: 260, flex: 'none', color: 'var(--sb-text)',
    background: 'linear-gradient(180deg, color-mix(in oklch, var(--sb-bg) 90%, #fff 4%) 0%, var(--sb-bg) 42%, var(--sb-bg-2) 100%)',
    borderRight: '1px solid var(--sb-border)', display: 'flex', flexDirection: 'column', padding: '12px 10px', overflow: 'auto',
    fontFamily: 'var(--font-sans)',
  } },
    // depth bloom (accent purple, top) — premium atmosphere
    h('div', { 'aria-hidden': 'true', style: { position: 'absolute', top: -60, left: -20, right: -20, height: 200, pointerEvents: 'none', background: 'radial-gradient(120% 100% at 30% 0%, oklch(0.55 0.15 295 / 0.16), transparent 70%)', zIndex: 0 } }),
    h('div', { style: { position: 'relative', zIndex: 1, flex: 1, display: 'flex', flexDirection: 'column', minHeight: 0 } },
      companyPicker,
      shortcuts,
      h('div', { style: { flex: 1 } }, groupBlocks),
      footer));
}
