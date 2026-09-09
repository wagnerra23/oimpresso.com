// Sidebar — accordion menu (paridade com AppShell.tsx)
const MENU = [
  { label: 'Painel', icon: 'home', href: '/home' },
  { label: 'Ponto WR2', icon: 'calendar-clock', children: [
    { label: 'Visão geral', href: '/ponto', active: true },
    { label: 'Espelho de ponto', href: '/ponto/espelho' },
    { label: 'Intercorrências', href: '/ponto/intercorrencias', badge: 12 },
    { label: 'Aprovações', href: '/ponto/aprovacoes', badge: 5 },
    { label: 'Escalas', href: '/ponto/escalas' },
  ]},
  { label: 'Financeiro', icon: 'dollar-sign', children: [
    { label: 'Contas a pagar', href: '/financeiro/pagar' },
    { label: 'Contas a receber', href: '/financeiro/receber' },
    { label: 'Boletos', href: '/financeiro/boletos' },
  ]},
  { label: 'Vendas / PDV', icon: 'shopping-cart', href: '#' },
  { label: 'Estoque', icon: 'package', href: '#' },
  { label: 'Notas fiscais', icon: 'file-text', href: '#' },
  { label: 'Relatórios & BI', icon: 'bar-chart-3', href: '#' },
  { label: 'MemCofre', icon: 'lock', href: '#' },
  { label: 'Equipe & RH', icon: 'users', href: '#' },
];

function MenuEntry({ item, depth = 0 }) {
  const [open, setOpen] = React.useState(item.children && item.children.some(c => c.active));
  const hasChildren = !!item.children;

  if (!hasChildren) {
    const cls = "menu-item" + (item.active ? ' menu-item--active' : '') + (depth ? ' menu-item--child' : '');
    return (
      <li>
        <a href={item.href || '#'} className={cls}>
          {depth === 0 && <Icon name={item.icon} size={16}/>}
          <span style={{flex:1}}>{item.label}</span>
          {item.badge != null && <span className="menu-item__badge">{item.badge}</span>}
        </a>
      </li>
    );
  }

  const childActive = item.children.some(c => c.active);
  return (
    <li>
      <button type="button" onClick={() => setOpen(v => !v)} className={"menu-item menu-item--branch" + (childActive ? ' menu-item--parent-active' : '')} aria-expanded={open}>
        <Icon name={item.icon} size={16}/>
        <span style={{flex:1, textAlign:'left'}}>{item.label}</span>
        <Icon name={open ? 'chevron-down' : 'chevron-right'} size={14}/>
      </button>
      {open && (
        <ul className="menu-children">
          {item.children.map((c) => <MenuEntry key={c.label} item={c} depth={depth+1}/>)}
        </ul>
      )}
    </li>
  );
}

function Sidebar() {
  return (
    <aside className="sidebar">
      <div className="sidebar__brand">
        <span className="sidebar__brand-name">OI Impresso</span>
        <span className="sidebar__brand-dot" title="Online"/>
      </div>
      <nav className="sidebar__nav">
        <ul className="menu">
          {MENU.map((m) => <MenuEntry key={m.label} item={m}/>)}
        </ul>
      </nav>
      <div className="sidebar__footer">
        <div className="avatar">WR</div>
        <div className="sidebar__user">
          <span>Wagner Rabello</span>
          <span>wagner@oimpresso.com.br</span>
        </div>
        <button className="iconbtn" title="Sair"><Icon name="log-out" size={14}/></button>
      </div>
    </aside>
  );
}
window.Sidebar = Sidebar;
