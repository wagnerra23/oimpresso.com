// Sidebar — mapa de destinos (canon ADR 0180). Grupos com hue canon, contadores
// de shell.sidebar_counts, papel simulado, cascata Superadmin no rodapé.
const { useState, useEffect, useRef } = React;

// Marcador de frescor do destino: link válido mas tela não desenhada (mock) ou inexistente (stub).
function WipMark({ routeId }) {
  const st = (MOCK.ROUTE_STATE || {})[routeId];
  if (!st) return null;
  const t = (MOCK.ROUTE_STATE_LABEL || {})[st] || st;
  return <span className={"sb-wip sb-wip--" + st} title={t} aria-label={t} />;
}

// Contadores: no vivo existem 3 (chat · atendimento · tarefas) e vêm do backend.
const COUNT_KEY = { chat: "chat", inbox: "atendimento", tarefas: "tarefas" };
function countOf(id) {
  const k = COUNT_KEY[id];
  return k ? (MOCK.SIDEBAR_COUNTS || {})[k] || null : null;
}
// Papel simulado — item some quando o papel não tem acesso (espelha o can() do vivo).
function podeVer(papel, id) {
  const lista = (MOCK.SIDEBAR_PAPEIS || {})[papel];
  return !lista || lista.indexOf(id) >= 0;
}
// Dica de atalho "G X" — aparece no hover/foco da linha (o teclado real vive no listener abaixo).
function Kbd({ routeId }) {
  const k = (MOCK.MENU_SHORTCUTS || {})[routeId];
  return k ? <span className="sb-kbd">G {k}</span> : null;
}
// Slot da direita: em repouso mostra QUANTAS telas o hub tem; no hover troca pelo atalho.
// Os dois ocupam a mesma célula (grid 1/1), então nada empurra o label.
function ItemEnd({ routeId, ghostCount }) {
  const k = (MOCK.MENU_SHORTCUTS || {})[routeId];
  if (!k && !ghostCount) return null;
  return (
    <span className={"sb-item-end" + (k ? " has-kbd" : "")}>
      {ghostCount ? <span className="sb-ghost-count">{ghostCount}</span> : null}
      <Kbd routeId={routeId} />
    </span>);
}
const hueOf = (meta) => meta && meta.hue != null ? meta.hue : null;

function CompanyPicker({ company, onChange }) {
  const [open, setOpen] = useState(false);
  const ref = useRef(null);
  useEffect(() => {
    if (!open) return;
    const h = (e) => {if (!ref.current?.contains(e.target)) setOpen(false);};
    document.addEventListener("mousedown", h);
    return () => document.removeEventListener("mousedown", h);
  }, [open]);

  return (
    <div className="sb-cp" ref={ref}>
      <button className="sb-cp-btn" onClick={() => setOpen(!open)}>
        <span className={`avatar ${company.grad}`}>{company.initials}</span>
        <span className="name">{company.name}</span>
        <I.chev className="ic chev" />
      </button>
      {open &&
      <div className="sb-dd">
          <div className="sb-dd-h">EMPRESAS</div>
          {MOCK.COMPANIES.map((c) =>
        <div key={c.id} className="sb-dd-i" onClick={() => {onChange(c);setOpen(false);}}>
              <span className={`avatar ${c.grad}`} style={{ width: 18, height: 18, borderRadius: 4, fontSize: 10, fontWeight: 700, color: "#fff", display: "grid", placeItems: "center" }}>{c.initials}</span>
              <span>{c.name}</span>
              {c.id === company.id && <I.check className="ic check" size={14} />}
            </div>
        )}
          <div className="sb-dd-sep" />
          <div className="sb-dd-foot">+ Adicionar empresa</div>
        </div>
      }
    </div>);

}

function SidebarTabs({ tab, onTab }) {
  return (
    <div className="sb-tabs">
      <button
        className={`sb-tab ${tab === "chat" ? "active" : ""}`}
        onClick={() => onTab("chat")}>
        <I.chat size={14} /> <span>Chat</span>
      </button>
      <button
        className={`sb-tab ${tab === "menu" ? "active" : ""}`}
        onClick={() => onTab("menu")}>
        <I.hash size={14} /> <span>Menu</span>
      </button>
    </div>);

}

// ─── ABA CHAT (estilo da imagem do print) ───
function SidebarChat({ activeConvId, onSelectConv, onSelectRoute }) {
  const company = window.__company;
  const convs = MOCK.CONV[company.id] || [];
  const pinned = convs.filter((c) => c.pinned);
  const recents = convs.filter((c) => !c.pinned);

  return (
    <div className="sb-chat">
      {/* Ações principais */}
      <div className="sb-actions">
        <div className="sb-action" onClick={() => onSelectRoute("chat")}>
          <I.plus className="ic" /> <span>Nova conversa</span>
          <span className="kbd">⌘N</span>
        </div>
        <div className="sb-action" onClick={() => onSelectRoute("tarefas")}>
          <I.inbox className="ic" /> <span>Tarefas</span>
          <span className="kbd-badge">{countOf("tarefas")}</span>
        </div>
        <div className="sb-action">
          <I.bell className="ic" /> <span>Despachos</span>
          <span className="beta">Beta</span>
        </div>
        <div className="sb-action">
          <I.cog className="ic" /> <span>Personalizar</span>
        </div>
      </div>

      <div className="sb-section-h">FIXADAS</div>
      {pinned.length === 0 ?
      <div className="sb-pin-empty">
          <I.pin className="ic" /> <span>Arraste para fixar</span>
        </div> :
      pinned.map((c) =>
      <ConvRow key={c.id} c={c} active={c.id === activeConvId}
      onClick={() => onSelectConv(c.id)} />
      )}

      <div className="sb-section-h">ROTINAS</div>
      {MOCK.ROUTINES.map((r) =>
      <div key={r.id} className="sb-routine">
          <span className="sb-bullet outline"></span>
          <span className="sb-routine-t">{r.title}</span>
          <span className="sb-routine-f">{r.freq}</span>
        </div>
      )}

      <div className="sb-section-h">RECENTES</div>
      {recents.map((c) =>
      <ConvRow key={c.id} c={c} active={c.id === activeConvId}
      onClick={() => onSelectConv(c.id)} />
      )}
    </div>);

}

function ConvRow({ c, active, onClick }) {
  return (
    <div className={`sb-conv ${active ? "active" : ""}`} onClick={onClick}>
      <span className={`sb-bullet ${c.unread ? "filled" : "outline"}`}></span>
      <span className="sb-conv-t">{c.title}</span>
    </div>);

}

// ─── Linha de item (hub ou ghost) ───
function ItemRow({ item, active, ghost, groupDot, onSelect, count, anchor, ghostCount }) {
  const Icon = I[item.icon];
  return (
    <div
      className={`sb-item sb-sub${ghost ? " sb-ghost" : ""}${active ? " active" : ""}`}
      role="link" aria-current={active ? "page" : undefined} tabIndex={0}
      onKeyDown={(e) => {if (e.key === "Enter" || e.key === " ") {e.preventDefault();onSelect();}}}
      onClick={onSelect}
      style={active && groupDot ? { borderLeftColor: groupDot } : null}>
      {Icon && <Icon className="ic" />}
      <span className="label" data-comment-anchor={anchor}>{item.label}</span>
      <WipMark routeId={item.id} />
      {count ? <span className="badge">{count}</span> : <ItemEnd routeId={item.id} ghostCount={ghostCount} />}
    </div>);

}

// Teto canon: 5 ghosts visíveis + "⋯ mais N". A tela ativa é sempre promovida
// pra faixa visível — nunca fica escondida atrás do ⋯ (Vendas tem 18, Produtos 12).
const GHOST_TETO = 5;
function GhostList({ ghosts, activeRoute, groupDot, onSelectRoute }) {
  const [tudo, setTudo] = useState(false);
  let visiveis = ghosts, extras = [];
  if (!tudo && ghosts.length > GHOST_TETO) {
    visiveis = ghosts.slice(0, GHOST_TETO);
    extras = ghosts.slice(GHOST_TETO);
    const i = extras.findIndex((g) => g.id === activeRoute);
    if (i >= 0) {visiveis = visiveis.slice(0, GHOST_TETO - 1).concat(extras[i]);extras = ghosts.slice(GHOST_TETO - 1).filter((g) => g.id !== activeRoute);}
  }
  return (
    <React.Fragment>
      {visiveis.map((g, gi) =>
      <ItemRow key={g.id} item={g} ghost active={activeRoute === g.id} groupDot={groupDot}
      anchor={gi === 0 ? "6e73134b04-span-217-23" : undefined}
      onSelect={() => onSelectRoute(g.id)} />
      )}
      {extras.length > 0 &&
      <button type="button" className="sb-ghost-more" onClick={() => setTudo(true)} aria-expanded="false">
        <span className="sb-ghost-more-d">⋯</span><span>mais {extras.length}</span>
      </button>}
      {tudo && ghosts.length > GHOST_TETO &&
      <button type="button" className="sb-ghost-more" onClick={() => setTudo(false)} aria-expanded="true">
        <span className="sb-ghost-more-d">⌃</span><span>mostrar menos</span>
      </button>}
    </React.Fragment>);
}

// ─── Grupo (accordion) — estado por grupo, mesma chave do vivo ───
function MenuGroup({ entry, meta, items, activeRoute, onSelectRoute, showGhosts }) {
  const key = meta.key || entry.group.toLowerCase();
  const lsKey = `oimpresso.cockpit.group.v2.${key}.expanded`;
  const hasActive = items.some((it) => it.id === activeRoute || (it.ghosts || []).some((g) => g.id === activeRoute));
  const [open, setOpen] = useState(() => {
    try {
      const v = localStorage.getItem(lsKey);
      if (v !== null) return v === "1";
    } catch (e) {}
    return entry.group !== "PLATAFORMA";
  });
  useEffect(() => {
    try {localStorage.setItem(lsKey, open ? "1" : "0");} catch (e) {}
  }, [open, lsKey]);
  useEffect(() => {if (hasActive && !open) setOpen(true);}, [hasActive]);

  const hue = hueOf(meta);
  const groupColor = hue == null ? "var(--text-mute)" : `oklch(0.72 0.09 ${hue})`;
  const groupDot = hue == null ? "var(--text-mute)" : `oklch(0.65 0.14 ${hue})`;

  return (
    <div className={"sb-group" + (open ? " open" : "")} style={hue == null ? null : { ["--gh"]: hue }}>
      <button type="button" className="sb-group-h" onClick={() => setOpen(!open)} aria-expanded={open}>
        <span className="sb-group-dot" style={{ background: groupDot }} />
        <span className="sb-group-l" style={{ color: groupColor }}>{meta.label || entry.group}</span>
        <span className="sb-group-n">{items.length}</span>
        <I.chev className="ic chev" style={{ transform: open ? "rotate(0)" : "rotate(-90deg)", transition: "transform .15s" }} />
      </button>
      {open && items.map((item) => {
        const ghosts = showGhosts ? item.ghosts || [] : [];
        const onGhost = ghosts.some((g) => g.id === activeRoute);
        const isActive = activeRoute === item.id || onGhost;
        const rows = [
        <ItemRow key={item.id} item={item} active={isActive} groupDot={groupDot}
        count={item.badge || countOf(item.id)} ghostCount={(item.ghosts || []).length}
        onSelect={() => onSelectRoute(item.id)} />];

        if (ghosts.length && isActive) {
          rows.push(<GhostList key={item.id + "-gh"} ghosts={ghosts} activeRoute={activeRoute}
          groupDot={groupDot} onSelectRoute={onSelectRoute} />);
        }
        return rows;
      })}
    </div>);

}

// ─── ABA MENU ───
function SidebarMenu({ activeRoute, onSelectRoute, papel, showGhosts }) {
  const meta = MOCK.GROUP_META || {};
  const visiveis = MOCK.MENU.filter((e) => e.group ? e.items.some((it) => podeVer(papel, it.id)) : podeVer(papel, e.id));
  if (!visiveis.length) return (
    <nav className="sb-menu" aria-label="Navegação principal">
      <div className="sb-menu-empty">
        <I.hash className="ic" />
        <b>Menu vazio</b>
        <span>Este papel não tem módulos liberados.</span>
      </div>
    </nav>);
  return (
    <nav className="sb-menu" aria-label="Navegação principal">
      {MOCK.MENU.map((entry) => {
        // Atalho de topo (sem grupo)
        if (!entry.group) {
          if (!podeVer(papel, entry.id)) return null;
          const Icon = I[entry.icon];
          const isActive = activeRoute === entry.id;
          const n = entry.badge || countOf(entry.id);
          return (
            <div key={entry.id} className={`sb-item ${isActive ? "active" : ""}`}
            role="link" aria-current={isActive ? "page" : undefined} tabIndex={0}
            onKeyDown={(e) => {if (e.key === "Enter" || e.key === " ") {e.preventDefault();onSelectRoute(entry.id);}}}
            onClick={() => onSelectRoute(entry.id)}>
              <Icon className="ic" />
              <span className="label">{entry.label}</span>
              <WipMark routeId={entry.id} />
              {n ? <span className="badge">{n}</span> : <ItemEnd routeId={entry.id} />}
            </div>);

        }
        // Grupo — some inteiro quando o papel não vê nenhum item (canon PR #1669)
        const items = entry.items.filter((it) => podeVer(papel, it.id));
        if (!items.length) return null;
        return (
          <MenuGroup key={entry.group} entry={entry} meta={meta[entry.group] || { label: entry.group }}
          items={items} activeRoute={activeRoute} onSelectRoute={onSelectRoute} showGhosts={showGhosts} />);

      })}
    </nav>);

}

// ─── Rodapé: usuário ───
function UserMenu({ onClose }) {
  const [sub, setSub] = useState(null);
  const go = (id) => {window.__selectRoute?.(id);onClose?.();};
  const superItems = MOCK.SUPERADMIN_MENU || [];
  return (
    <div className="user-menu" onClick={(e) => e.stopPropagation()}>
      <div className="user-menu-head">
        <span className="avatar">WR</span>
        <div className="meta">
          <b>Wagner Rocha Araujo</b>
          <small>wagner@oimpresso.com.br</small>
        </div>
      </div>
      <div className="um-item" onClick={() => go("perfil")}><I.user className="ic" /> <span className="label">Meu perfil</span></div>
      {/* Itens de usuário — canon repo: vivem no rodapé, não no corpo */}
      {(MOCK.USER_MENU || []).map((it) => {
        const Icon = I[it.icon] || I.cog;
        return (
          <div key={it.id} className="um-item" onClick={() => go(it.id)}>
            <Icon className="ic" /> <span className="label">{it.label}</span>
          </div>);

      })}
      {/* Cascata Superadmin — admin de plataforma fora do menu principal */}
      {superItems.length > 0 &&
      <div className={"um-item um-cascade" + (sub === "super" ? " active" : "")}
      onClick={() => setSub(sub === "super" ? null : "super")}>
          <I.shield className="ic" /> <span className="label">Superadmin</span> <span className="arrow">›</span>
        </div>
      }
      {sub === "super" &&
      <div className="um-sub">
          {superItems.map((it) => {
          const Icon = I[it.icon] || I.cog;
          return (
            <div key={it.id} className="um-item" onClick={() => go(it.id)}>
                <Icon className="ic" /> <span className="label">{it.label}</span>
                <WipMark routeId={it.id} />
              </div>);

        })}
        </div>
      }
      <div className="um-sep" />
      <div className="um-item"><span className="um-status" style={{ background: "oklch(0.72 0.18 145)" }} /> <span className="label">Disponível</span> <span className="arrow">›</span></div>
      <div className="um-item"><I.moon className="ic" /> <span className="label">Aparência</span> <span className="arrow">›</span></div>
      <div className="um-sep" />
      <div className="um-item" onClick={() => {onClose?.();window.__openCmdK?.();}}><I.keyboard className="ic" /> <span className="label">Buscar tela</span> <span className="kbd">⌘K</span></div>
      {(MOCK.FOOTER_LINKS || []).map((it) => {
        const Icon = I[it.icon] || I.book;
        return (
          <div key={it.id} className="um-item" onClick={() => go(it.id)}>
            <Icon className="ic" /> <span className="label">{it.label}</span>
          </div>);

      })}
      <div className="um-item"><I.help className="ic" /> <span className="label">Central de ajuda</span></div>
      <div className="um-sep" />
      <div className="um-item"><I.exit className="ic" /> <span className="label">Sair</span></div>
    </div>);

}

function SidebarUser() {
  const [open, setOpen] = useState(false);
  const ref = useRef(null);
  useEffect(() => {
    if (!open) return;
    const h = (e) => {if (!ref.current?.contains(e.target)) setOpen(false);};
    document.addEventListener("mousedown", h);
    return () => document.removeEventListener("mousedown", h);
  }, [open]);

  return (
    <div className="sb-user" ref={ref}>
      {open && <UserMenu onClose={() => setOpen(false)} />}
      <button className="sb-user-btn" onClick={() => setOpen(!open)}>
        <span className="avatar">WR</span>
        <div className="who">
          <b>Wagner Rocha</b>
          <small>Administrador</small>
        </div>
        <I.chevUd className="ic chev" />
      </button>
    </div>);

}

// ─── Rail (sidebar compacta) ───
function CompanyPickerRail({ company, onChange }) {
  const [open, setOpen] = useState(false);
  const ref = useRef(null);
  useEffect(() => {
    if (!open) return;
    const h = (e) => {if (!ref.current?.contains(e.target)) setOpen(false);};
    document.addEventListener("mousedown", h);
    return () => document.removeEventListener("mousedown", h);
  }, [open]);
  return (
    <div className="sb-cp sb-cp-rail" ref={ref}>
      <button className="sb-rail-btn sb-cp-rail-btn" onClick={() => setOpen(!open)} data-tip={company.name}>
        <span className={`avatar ${company.grad}`}>{company.initials}</span>
      </button>
      {open &&
      <div className="sb-dd sb-dd-rail">
          <div className="sb-dd-h">EMPRESAS</div>
          {MOCK.COMPANIES.map((c) =>
        <div key={c.id} className="sb-dd-i" onClick={() => {onChange(c);setOpen(false);}}>
              <span className={`avatar ${c.grad}`} style={{ width: 18, height: 18, borderRadius: 4, fontSize: 10, fontWeight: 700, color: "#fff", display: "grid", placeItems: "center" }}>{c.initials}</span>
              <span>{c.name}</span>
              {c.id === company.id && <I.check className="ic check" size={14} />}
            </div>
        )}
          <div className="sb-dd-sep" />
          <div className="sb-dd-foot">+ Adicionar empresa</div>
        </div>
      }
    </div>);

}

function SidebarMenuRail({ activeRoute, onSelectRoute, papel, showGhosts }) {
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
    if (!anchor) {setFlyout(null);return;}
    const r = anchor.getBoundingClientRect();
    setFlyoutPos({ top: r.top, left: r.right + 6 });
    setFlyout(key);
  };
  const itemsOf = (entry) => entry.items.filter((it) => podeVer(papel, it.id));

  let sepPosto = false;
  return (
    <nav className="sb-menu sb-menu-rail" aria-label="Navegação principal">
      {MOCK.MENU.map((entry) => {
        // Atalho de topo
        if (!entry.group) {
          if (!podeVer(papel, entry.id)) return null;
          const Icon = I[entry.icon];
          const isActive = activeRoute === entry.id;
          return (
            <button key={entry.id}
            ref={(el) => itemRefs.current[entry.id] = el}
            className={`sb-rail-btn ${isActive ? "active" : ""}`}
            aria-current={isActive ? "page" : undefined}
            onClick={() => {onSelectRoute(entry.id);setFlyout(null);}}
            aria-label={entry.label} data-tip={entry.label}>
              <Icon className="ic" />
              {countOf(entry.id) ? <span className="sb-rail-dot-badge" /> : null}
            </button>);

        }
        // Grupo (botão com cor + flyout)
        const items = itemsOf(entry);
        if (!items.length) return null;
        const meta = (MOCK.GROUP_META || {})[entry.group] || { label: entry.group };
        const hue = hueOf(meta);
        const groupDot = hue == null ? "var(--text-mute)" : `oklch(0.62 0.13 ${hue})`;
        const hasActive = items.some((i) => i.id === activeRoute || (i.ghosts || []).some((g) => g.id === activeRoute));
        const GroupIcon = meta.icon ? I[meta.icon] : null;
        const sep = sepPosto ? null : (sepPosto = true, <div key="sep" className="sb-rail-sep" aria-hidden="true" />);
        return (
          <React.Fragment key={entry.group}>{sep}
          <button
          ref={(el) => itemRefs.current[entry.group] = el}
          className={`sb-rail-btn sb-rail-group ${hasActive ? "active" : ""} ${flyout === entry.group ? "open" : ""}`}
          onClick={() => flyout === entry.group ? setFlyout(null) : openFlyout(entry.group)}
          aria-label={meta.label || entry.group} aria-expanded={flyout === entry.group}
          data-tip={meta.label || entry.group}
          style={hue == null ? null : { ["--gh"]: hue }}>
            {GroupIcon ?
            <GroupIcon className="ic" style={{ color: groupDot }} /> :
            <span className="sb-rail-group-pill" style={{ background: groupDot }}>{(meta.label || entry.group).slice(0, 2).toUpperCase()}</span>
            }
          </button></React.Fragment>);

      })}

      {flyout && (() => {
        const entry = MOCK.MENU.find((e) => e.group === flyout);
        if (!entry) return null;
        const meta = (MOCK.GROUP_META || {})[entry.group] || { label: entry.group };
        const hue = hueOf(meta);
        const groupColor = hue == null ? "var(--text-mute)" : `oklch(0.72 0.09 ${hue})`;
        const groupDot = hue == null ? "var(--text-mute)" : `oklch(0.65 0.14 ${hue})`;
        const items = itemsOf(entry);
        return (
          <div className="sb-rail-flyout" ref={flyoutRef}
          style={{ top: flyoutPos.top, left: flyoutPos.left }}>
            <div className="sb-rail-flyout-h">
              <span className="sb-group-dot" style={{ background: groupDot }} />
              <span style={{ color: groupColor }}>{meta.label || entry.group}</span>
              <span className="sb-group-n">{items.length}</span>
            </div>
            {items.map((item) => {
              const ghosts = showGhosts ? item.ghosts || [] : [];
              const onGhost = ghosts.some((g) => g.id === activeRoute);
              const isActive = activeRoute === item.id || onGhost;
              const rows = [
              <ItemRow key={item.id} item={item} active={isActive} groupDot={groupDot}
              count={item.badge || countOf(item.id)} ghostCount={(item.ghosts || []).length}
              onSelect={() => {onSelectRoute(item.id);setFlyout(null);}} />];

              if (ghosts.length && isActive) {
                rows.push(<GhostList key={item.id + "-gh"} ghosts={ghosts} activeRoute={activeRoute}
                groupDot={groupDot} onSelectRoute={(id) => {onSelectRoute(id);setFlyout(null);}} />);
              }
              return rows;
            })}
          </div>);

      })()}
    </nav>);

}

function SidebarUserRail() {
  const [open, setOpen] = useState(false);
  const ref = useRef(null);
  useEffect(() => {
    if (!open) return;
    const h = (e) => {if (!ref.current?.contains(e.target)) setOpen(false);};
    document.addEventListener("mousedown", h);
    return () => document.removeEventListener("mousedown", h);
  }, [open]);
  return (
    <div className="sb-user sb-user-rail" ref={ref}>
      {open && <UserMenu onClose={() => setOpen(false)} />}
      <button className="sb-rail-btn sb-user-rail-btn" onClick={() => setOpen(!open)} data-tip="Wagner Rocha">
        <span className="avatar">WR</span>
      </button>
    </div>);

}

// ─── Sidebar principal ───
function Sidebar({ company, onCompany, tab, onTab, activeConvId, onSelectConv, activeRoute, onSelectRoute, mode = "expanded", onModeChange, papel = "wagner (admin)", showGhosts = true }) {
  const rail = mode === "rail";
  const nextMode = rail ? "expanded" : "rail";
  const toggleTitle = rail ? "Expandir sidebar (⌘\\)" : "Recolher sidebar (⌘\\)";

  return (
    <aside className={"sb" + (rail ? " sb--rail" : "")}>
      <div className="sb-top" data-comment-anchor="817b4e58ef-div-473-7">
        {rail ?
        <CompanyPickerRail company={company} onChange={onCompany} /> :
        <CompanyPicker company={company} onChange={onCompany} />}
      </div>
      <div className="sb-body">
        {rail ?
        <SidebarMenuRail activeRoute={activeRoute} onSelectRoute={onSelectRoute} papel={papel} showGhosts={showGhosts} /> :
        <SidebarMenu activeRoute={activeRoute} onSelectRoute={onSelectRoute} papel={papel} showGhosts={showGhosts} />}
      </div>
      {rail ? <SidebarUserRail /> : <SidebarUser />}

      {/* Alça de colapsar/expandir na borda direita */}
      <button
        className="sb-collapse-handle"
        onClick={() => onModeChange?.(nextMode)}
        title={toggleTitle}
        aria-label={toggleTitle}>
        <svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" strokeWidth="2.2" strokeLinecap="round" strokeLinejoin="round">
          {rail ?
          <path d="m9 6 6 6-6 6" /> :
          <path d="m15 6-6 6 6 6" />}
        </svg>
      </button>
    </aside>);

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
        <path d="m9 6 6 6-6 6" />
      </svg>
    </button>);

}

// ─── Sequência "G X": arma no G, navega na letra seguinte (janela de 1.5s) ───
(function () {
  let armado = 0;
  const editando = (el) => el && (el.tagName === "INPUT" || el.tagName === "TEXTAREA" || el.isContentEditable);
  document.addEventListener("keydown", (e) => {
    if (e.metaKey || e.ctrlKey || e.altKey || editando(document.activeElement)) return;
    const k = (e.key || "").toUpperCase();
    if (Date.now() - armado < 1500) {
      armado = 0;
      const rota = (MOCK.SHORTCUT_TO_ROUTE || {})[k];
      if (rota) {e.preventDefault();window.__selectRoute?.(rota);}
      return;
    }
    if (k === "G") {armado = Date.now();}
  });
})();

window.Sidebar = Sidebar;
window.SidebarReopenHandle = SidebarReopenHandle;
