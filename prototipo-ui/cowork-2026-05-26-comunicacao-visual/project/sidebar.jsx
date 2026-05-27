// Sidebar — toggle Chat/Menu, persistência localStorage
const { useState, useEffect, useRef } = React;

function CompanyPicker({ company, onChange }) {
  const [open, setOpen] = useState(false);
  const ref = useRef(null);
  useEffect(() => {
    if (!open) return;
    const h = (e) => { if (!ref.current?.contains(e.target)) setOpen(false); };
    document.addEventListener("mousedown", h);
    return () => document.removeEventListener("mousedown", h);
  }, [open]);

  return (
    <div className="sb-cp" ref={ref}>
      <button className="sb-cp-btn" onClick={() => setOpen(!open)}>
        <span className={`avatar ${company.grad}`}>{company.initials}</span>
        <span className="name">{company.name}</span>
        <I.chev className="ic chev"/>
      </button>
      {open && (
        <div className="sb-dd">
          <div className="sb-dd-h">EMPRESAS</div>
          {MOCK.COMPANIES.map(c => (
            <div key={c.id} className="sb-dd-i" onClick={() => { onChange(c); setOpen(false); }}>
              <span className={`avatar ${c.grad}`} style={{width:18,height:18,borderRadius:4,fontSize:10,fontWeight:700,color:"#fff",display:"grid",placeItems:"center"}}>{c.initials}</span>
              <span>{c.name}</span>
              {c.id === company.id && <I.check className="ic check" size={14}/>}
            </div>
          ))}
          <div className="sb-dd-sep"/>
          <div className="sb-dd-foot">+ Adicionar empresa</div>
        </div>
      )}
    </div>
  );
}

function SidebarTabs({ tab, onTab }) {
  return (
    <div className="sb-tabs">
      <button
        className={`sb-tab ${tab === "chat" ? "active" : ""}`}
        onClick={() => onTab("chat")}>
        <I.chat size={14}/> <span>Chat</span>
      </button>
      <button
        className={`sb-tab ${tab === "menu" ? "active" : ""}`}
        onClick={() => onTab("menu")}>
        <I.hash size={14}/> <span>Menu</span>
      </button>
    </div>
  );
}

// ─── ABA CHAT (estilo da imagem do print) ───
function SidebarChat({ activeConvId, onSelectConv, onSelectRoute }) {
  const company = window.__company;
  const convs = MOCK.CONV[company.id] || [];
  const pinned = convs.filter(c => c.pinned);
  const recents = convs.filter(c => !c.pinned);

  return (
    <div className="sb-chat">
      {/* Ações principais */}
      <div className="sb-actions">
        <div className="sb-action" onClick={() => onSelectRoute("chat")}>
          <I.plus className="ic"/> <span>Nova conversa</span>
          <span className="kbd">⌘N</span>
        </div>
        <div className="sb-action" onClick={() => onSelectRoute("tarefas")}>
          <I.inbox className="ic"/> <span>Tarefas</span>
          <span className="kbd-badge">6</span>
        </div>
        <div className="sb-action">
          <I.bell className="ic"/> <span>Despachos</span>
          <span className="beta">Beta</span>
        </div>
        <div className="sb-action">
          <I.cog className="ic"/> <span>Personalizar</span>
        </div>
      </div>

      <div className="sb-section-h">FIXADAS</div>
      {pinned.length === 0 ? (
        <div className="sb-pin-empty">
          <I.pin className="ic"/> <span>Arraste para fixar</span>
        </div>
      ) : pinned.map(c => (
        <ConvRow key={c.id} c={c} active={c.id === activeConvId}
          onClick={() => onSelectConv(c.id)}/>
      ))}

      <div className="sb-section-h">ROTINAS</div>
      {MOCK.ROUTINES.map(r => (
        <div key={r.id} className="sb-routine">
          <span className="sb-bullet outline"></span>
          <span className="sb-routine-t">{r.title}</span>
          <span className="sb-routine-f">{r.freq}</span>
        </div>
      ))}

      <div className="sb-section-h">RECENTES</div>
      {recents.map(c => (
        <ConvRow key={c.id} c={c} active={c.id === activeConvId}
          onClick={() => onSelectConv(c.id)}/>
      ))}
    </div>
  );
}

function ConvRow({ c, active, onClick }) {
  return (
    <div className={`sb-conv ${active ? "active" : ""}`} onClick={onClick}>
      <span className={`sb-bullet ${c.unread ? "filled" : "outline"}`}></span>
      <span className="sb-conv-t">{c.title}</span>
    </div>
  );
}

// ─── ABA MENU (accordion 1-por-módulo, com cores por área) ───
function SidebarMenu({ activeRoute, onSelectRoute }) {
  // qual grupo contém a rota ativa? auto-abre ele
  const groupOfRoute = MOCK.MENU.find(e => e.group && e.items.some(i => i.id === activeRoute))?.group;

  const [expanded, setExpanded] = useState(() => {
    try {
      const saved = localStorage.getItem("oimpresso.menu.expanded");
      if (saved) {
        const obj = JSON.parse(saved);
        if (groupOfRoute) obj[groupOfRoute] = true;
        return obj;
      }
    } catch (e) {}
    const init = {};
    MOCK.MENU.forEach(e => { if (e.group) init[e.group] = false; });
    if (groupOfRoute) init[groupOfRoute] = true;
    else init.OFFICEIMPRESSO = true;
    return init;
  });

  useEffect(() => {
    if (groupOfRoute && !expanded[groupOfRoute]) {
      setExpanded(e => ({ ...e, [groupOfRoute]: true }));
    }
  }, [groupOfRoute]);

  useEffect(() => {
    try { localStorage.setItem("oimpresso.menu.expanded", JSON.stringify(expanded)); } catch (e) {}
  }, [expanded]);

  const toggle = (g) => setExpanded(e => ({ ...e, [g]: !e[g] }));

  return (
    <div className="sb-menu">
      {MOCK.MENU.map((entry) => {
        // Item de topo (sem grupo)
        if (!entry.group) {
          const Icon = I[entry.icon];
          const isActive = activeRoute === entry.id;
          return (
            <div key={entry.id} className={`sb-item ${isActive ? "active" : ""}`}
                 onClick={() => onSelectRoute(entry.id)}>
              <Icon className="ic"/>
              <span className="label">{entry.label}</span>
              {entry.badge ? <span className="badge">{entry.badge}</span> : null}
            </div>
          );
        }
        // Grupo (accordion com cor)
        const isOpen = !!expanded[entry.group];
        const meta = (MOCK.GROUP_META || {})[entry.group] || { label: entry.group };
        const hue = meta.hue ?? 220;
        const groupColor = `oklch(0.46 0.10 ${hue})`;
        const groupDot   = `oklch(0.62 0.13 ${hue})`;
        return (
          <div key={entry.group} className={"sb-group" + (isOpen ? " open" : "")} style={{["--gh"]: hue}}>
            <div className="sb-group-h" onClick={() => toggle(entry.group)}>
              <span className="sb-group-dot" style={{background: groupDot}}/>
              <span className="sb-group-l" style={{color: groupColor}}>{meta.label || entry.group}</span>
              <span className="sb-group-n">{entry.items.length}</span>
              <I.chev className="ic chev" style={{transform: isOpen ? "rotate(0)" : "rotate(-90deg)", transition: "transform .15s"}}/>
            </div>
            {isOpen && entry.items.map(item => {
              const Icon = I[item.icon];
              const isActive = activeRoute === item.id;
              return (
                <div key={item.id}
                     className={`sb-item sb-sub ${isActive ? "active" : ""}`}
                     onClick={() => onSelectRoute(item.id)}
                     style={isActive ? {borderLeftColor: groupDot} : null}>
                  <Icon className="ic"/>
                  <span className="label">{item.label}</span>
                  {item.badge ? <span className="badge">{item.badge}</span> : null}
                </div>
              );
            })}
          </div>
        );
      })}
    </div>
  );
}

// ─── Rodapé: usuário ───
function UserMenu({ onClose }) {
  return (
    <div className="user-menu" onClick={(e) => e.stopPropagation()}>
      <div className="user-menu-head">
        <span className="avatar">WR</span>
        <div className="meta">
          <b>Wagner Rocha Araujo</b>
          <small>wagner@oimpresso.com.br</small>
        </div>
      </div>
      <div className="um-item"><I.user className="ic"/> <span className="label">Meu perfil</span></div>
      <div className="um-item"><span className="um-status" style={{background:"oklch(0.72 0.18 145)"}}/> <span className="label">Disponível</span> <span className="arrow">›</span></div>
      <div className="um-item"><I.moon className="ic"/> <span className="label">Aparência</span> <span className="arrow">›</span></div>
      <div className="um-sep"/>
      <div className="um-item"><I.keyboard className="ic"/> <span className="label">Atalhos</span> <span className="kbd">⌘/</span></div>
      <div className="um-item"><I.help className="ic"/> <span className="label">Central de ajuda</span></div>
      <div className="um-sep"/>
      <div className="um-item"><I.exit className="ic"/> <span className="label">Sair</span></div>
    </div>
  );
}

function SidebarUser() {
  const [open, setOpen] = useState(false);
  const ref = useRef(null);
  useEffect(() => {
    if (!open) return;
    const h = (e) => { if (!ref.current?.contains(e.target)) setOpen(false); };
    document.addEventListener("mousedown", h);
    return () => document.removeEventListener("mousedown", h);
  }, [open]);

  return (
    <div className="sb-user" ref={ref}>
      {open && <UserMenu onClose={() => setOpen(false)}/>}
      <button className="sb-user-btn" onClick={() => setOpen(!open)}>
        <span className="avatar">WR</span>
        <div className="who">
          <b>Wagner Rocha</b>
          <small>Administrador</small>
        </div>
        <I.chevUd className="ic chev"/>
      </button>
    </div>
  );
}

// ─── Rail (sidebar compacta) ───
function CompanyPickerRail({ company, onChange }) {
  const [open, setOpen] = useState(false);
  const ref = useRef(null);
  useEffect(() => {
    if (!open) return;
    const h = (e) => { if (!ref.current?.contains(e.target)) setOpen(false); };
    document.addEventListener("mousedown", h);
    return () => document.removeEventListener("mousedown", h);
  }, [open]);
  return (
    <div className="sb-cp sb-cp-rail" ref={ref}>
      <button className="sb-rail-btn sb-cp-rail-btn" onClick={() => setOpen(!open)} data-tip={company.name}>
        <span className={`avatar ${company.grad}`}>{company.initials}</span>
      </button>
      {open && (
        <div className="sb-dd sb-dd-rail">
          <div className="sb-dd-h">EMPRESAS</div>
          {MOCK.COMPANIES.map(c => (
            <div key={c.id} className="sb-dd-i" onClick={() => { onChange(c); setOpen(false); }}>
              <span className={`avatar ${c.grad}`} style={{width:18,height:18,borderRadius:4,fontSize:10,fontWeight:700,color:"#fff",display:"grid",placeItems:"center"}}>{c.initials}</span>
              <span>{c.name}</span>
              {c.id === company.id && <I.check className="ic check" size={14}/>}
            </div>
          ))}
          <div className="sb-dd-sep"/>
          <div className="sb-dd-foot">+ Adicionar empresa</div>
        </div>
      )}
    </div>
  );
}

function SidebarMenuRail({ activeRoute, onSelectRoute }) {
  const [flyout, setFlyout] = useState(null); // group key
  const [flyoutPos, setFlyoutPos] = useState({ top: 0, left: 0 });
  const flyoutRef = useRef(null);
  const itemRefs = useRef({});

  useEffect(() => {
    if (!flyout) return;
    const h = (e) => {
      if (flyoutRef.current?.contains(e.target)) return;
      const anchor = itemRefs.current[flyout];
      if (anchor && anchor.contains(e.target)) return;
      setFlyout(null);
    };
    document.addEventListener("mousedown", h);
    return () => document.removeEventListener("mousedown", h);
  }, [flyout]);

  const openFlyout = (key) => {
    const anchor = itemRefs.current[key];
    if (!anchor) { setFlyout(null); return; }
    const r = anchor.getBoundingClientRect();
    setFlyoutPos({ top: r.top, left: r.right + 6 });
    setFlyout(key);
  };

  return (
    <div className="sb-menu sb-menu-rail">
      {MOCK.MENU.map((entry) => {
        // Item de topo
        if (!entry.group) {
          const Icon = I[entry.icon];
          const isActive = activeRoute === entry.id;
          return (
            <button key={entry.id}
              ref={el => itemRefs.current[entry.id] = el}
              className={`sb-rail-btn ${isActive ? "active" : ""}`}
              onClick={() => { onSelectRoute(entry.id); setFlyout(null); }}
              data-tip={entry.label}>
              <Icon className="ic"/>
              {entry.badge ? <span className="sb-rail-dot-badge"/> : null}
            </button>
          );
        }
        // Grupo (botão com cor + flyout)
        const meta = (MOCK.GROUP_META || {})[entry.group] || { label: entry.group };
        const hue = meta.hue ?? 220;
        const groupDot = `oklch(0.62 0.13 ${hue})`;
        const hasActive = entry.items.some(i => i.id === activeRoute);
        const GroupIcon = meta.icon ? I[meta.icon] : null;
        return (
          <button key={entry.group}
            ref={el => itemRefs.current[entry.group] = el}
            className={`sb-rail-btn sb-rail-group ${hasActive ? "active" : ""} ${flyout === entry.group ? "open" : ""}`}
            onClick={() => flyout === entry.group ? setFlyout(null) : openFlyout(entry.group)}
            data-tip={meta.label || entry.group}
            style={{ ["--gh"]: hue }}>
            {GroupIcon
              ? <GroupIcon className="ic" style={{ color: groupDot }}/>
              : <span className="sb-rail-group-pill" style={{ background: groupDot }}>{(meta.label || entry.group).slice(0,2).toUpperCase()}</span>
            }
          </button>
        );
      })}

      {flyout && (() => {
        const entry = MOCK.MENU.find(e => e.group === flyout);
        if (!entry) return null;
        const meta = (MOCK.GROUP_META || {})[entry.group] || { label: entry.group };
        const hue = meta.hue ?? 220;
        const groupColor = `oklch(0.46 0.10 ${hue})`;
        const groupDot = `oklch(0.62 0.13 ${hue})`;
        return (
          <div className="sb-rail-flyout" ref={flyoutRef}
            style={{ top: flyoutPos.top, left: flyoutPos.left }}>
            <div className="sb-rail-flyout-h">
              <span className="sb-group-dot" style={{ background: groupDot }}/>
              <span style={{ color: groupColor }}>{meta.label || entry.group}</span>
              <span className="sb-group-n">{entry.items.length}</span>
            </div>
            {entry.items.map(item => {
              const Icon = I[item.icon];
              const isActive = activeRoute === item.id;
              return (
                <div key={item.id}
                  className={`sb-item sb-sub ${isActive ? "active" : ""}`}
                  onClick={() => { onSelectRoute(item.id); setFlyout(null); }}
                  style={isActive ? {borderLeftColor: groupDot} : null}>
                  <Icon className="ic"/>
                  <span className="label">{item.label}</span>
                  {item.badge ? <span className="badge">{item.badge}</span> : null}
                </div>
              );
            })}
          </div>
        );
      })()}
    </div>
  );
}

function SidebarUserRail() {
  const [open, setOpen] = useState(false);
  const ref = useRef(null);
  useEffect(() => {
    if (!open) return;
    const h = (e) => { if (!ref.current?.contains(e.target)) setOpen(false); };
    document.addEventListener("mousedown", h);
    return () => document.removeEventListener("mousedown", h);
  }, [open]);
  return (
    <div className="sb-user sb-user-rail" ref={ref}>
      {open && <UserMenu onClose={() => setOpen(false)}/>}
      <button className="sb-rail-btn sb-user-rail-btn" onClick={() => setOpen(!open)} data-tip="Wagner Rocha">
        <span className="avatar">WR</span>
      </button>
    </div>
  );
}

// ─── Sidebar principal ───
function Sidebar({ company, onCompany, tab, onTab, activeConvId, onSelectConv, activeRoute, onSelectRoute, mode = "expanded", onModeChange }) {
  const rail = mode === "rail";
  const nextMode = rail ? "expanded" : "rail";
  const toggleTitle = rail ? "Expandir sidebar (⌘\\)" : "Recolher sidebar (⌘\\)";

  return (
    <aside className={"sb" + (rail ? " sb--rail" : "")}>
      <div className="sb-top">
        {rail
          ? <CompanyPickerRail company={company} onChange={onCompany}/>
          : <CompanyPicker company={company} onChange={onCompany}/>}
      </div>
      <div className="sb-body">
        {rail
          ? <SidebarMenuRail activeRoute={activeRoute} onSelectRoute={onSelectRoute}/>
          : <SidebarMenu activeRoute={activeRoute} onSelectRoute={onSelectRoute}/>}
      </div>
      {rail ? <SidebarUserRail/> : <SidebarUser/>}

      {/* Alça de colapsar/expandir na borda direita */}
      <button
        className="sb-collapse-handle"
        onClick={() => onModeChange?.(nextMode)}
        title={toggleTitle}
        aria-label={toggleTitle}>
        <svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" strokeWidth="2.2" strokeLinecap="round" strokeLinejoin="round">
          {rail
            ? <path d="m9 6 6 6-6 6"/>
            : <path d="m15 6-6 6 6 6"/>}
        </svg>
      </button>
    </aside>
  );
}

// Alça flutuante para reabrir quando sidebar está oculta
function SidebarReopenHandle({ onOpen }) {
  return (
    <button
      className="sb-reopen-handle"
      onClick={onOpen}
      title="Mostrar sidebar (⌘⇧\\)"
      aria-label="Mostrar sidebar">
      <svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" strokeWidth="2.2" strokeLinecap="round" strokeLinejoin="round">
        <path d="m9 6 6 6-6 6"/>
      </svg>
    </button>
  );
}

window.Sidebar = Sidebar;
window.SidebarReopenHandle = SidebarReopenHandle;
