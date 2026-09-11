// Sidebar — mapa de destinos (canon ADR 0180). Grupos com hue canon, contadores
// de shell.sidebar_counts, papel simulado, cascata Superadmin no rodapé.
const { useState, useEffect, useRef } = React;

// Presença — conceito de UI sem receptor no backend (não existe tabela de
// status no main lido). Estado local declarado: muda o ponto e o rótulo, nada
// mais. Se ganhar receptor, vira prop.
const PRESENCAS = [
{ id: "disponivel", label: "Disponível", cor: "oklch(0.72 0.18 145)" },
{ id: "ocupado", label: "Ocupado", cor: "oklch(0.62 0.20 25)" },
{ id: "ausente", label: "Ausente", cor: "oklch(0.75 0.15 75)" },
{ id: "invisivel", label: "Invisível", cor: "oklch(0.55 0.01 280)" }];
// Tema — dark é o padrão do projeto ([W] 2026-06-03); claro segue disponível.
const TEMAS = [
{ id: "dark", label: "Escuro", desc: "Padrão do balcão" },
{ id: "light", label: "Claro", desc: "Escritório, luz alta" }];
// Modo de trabalho (Vibes) — PUXADO do vivo em 2026-09-10, árvore 6fc8b8fac31d.
// Fonte: Components/cockpit/Sidebar.tsx (trigger :1257-1275 · VibesSubpanel
// :1348-1420) + shared.ts (:153-158 os 3 ids · :177 a chave de localStorage).
// Copy e cores são literais do vivo; ver COWORK_NOTES do ciclo pro que NÃO veio.
const VIBES = [
{ id: "workspace", label: "workspace", desc: "Denso e formal — padrão", dot: "oklch(0.55 0.15 295)" },
{ id: "daylight", label: "daylight", desc: "Tons quentes, mais ar", dot: "oklch(0.72 0.13 60)" },
{ id: "focus", label: "focus", desc: "Alto contraste, monocromático", dot: "oklch(0.45 0.02 240)" }];
// O dono do estado é o tweak do shell (app.jsx → root.dataset.vibe/theme),
// que já persiste. Aqui só leio e mando.
const lerShell = (k, padrao) => {
  try {return document.documentElement.dataset[k] || padrao;} catch (e) {return padrao;}
};

// Marcador de frescor do destino: link válido mas tela não desenhada (mock) ou inexistente (stub).
// A2: `aria-label` em `<span>` mudo é ignorado pela AT — precisa de `role="img"` pra virar nome acessível.
function WipMark({ routeId }) {
  const st = (MOCK.ROUTE_STATE || {})[routeId];
  if (!st) return null;
  const t = (MOCK.ROUTE_STATE_LABEL || {})[st] || st;
  return <span className={"sb-wip sb-wip--" + st} role="img" title={t} aria-label={t} />;
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
// A4: decorativa — o atalho funciona sem ela; anunciar "G V" no meio do nome do item é ruído.
function Kbd({ routeId }) {
  const k = (MOCK.MENU_SHORTCUTS || {})[routeId];
  return k ? <span className="sb-kbd" aria-hidden="true">G {k}</span> : null;
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
      <button type="button" className="sb-cp-btn" onClick={() => setOpen(!open)}
      aria-haspopup="menu" aria-expanded={open} aria-label={`Empresa: ${company.name}. Trocar de empresa`}>
        <span className={`avatar ${company.grad}`} aria-hidden="true">{company.initials}</span>
        <span className="name">{company.name}</span>
        <I.chev className="ic chev" />
      </button>
      {open &&
      <div className="sb-dd" role="menu">
          <div className="sb-dd-h">EMPRESAS</div>
          {MOCK.COMPANIES.map((c) =>
        <button type="button" key={c.id} className="sb-dd-i" role="menuitemradio" aria-checked={c.id === company.id}
        onClick={() => {onChange(c);setOpen(false);}}>
              <span className={`avatar ${c.grad}`} aria-hidden="true" style={{ width: 18, height: 18, borderRadius: 4, fontSize: 10, fontWeight: 700, color: "#fff", display: "grid", placeItems: "center" }}>{c.initials}</span>
              <span>{c.name}</span>
              {c.id === company.id && <I.check className="ic check" size={14} />}
            </button>
        )}
          <div className="sb-dd-sep" />
          <button type="button" className="sb-dd-foot" role="menuitem" onClick={() => {setOpen(false);window.__selectRoute?.("sa-negocios");}}>+ Adicionar empresa</button>
        </div>
      }
    </div>);

}

// ─── Alerta de certificado A1 (paridade com NfeCertBadge do vivo) ───
// Âncora: Components/cockpit/NfeCertBadge.tsx (lido no main 2026-09-10). Posição documentada
// em :25 — "após CompanyPicker, antes do SidebarMenu". No vivo lê shell.nfe_cert_status
// (HandleInertiaRequests::nfeCertStatus); aqui vem de MOCK.NFE_CERT.
// Silencioso em "ok" e "sem_cert" — sem_cert é legítimo pra quem não emite NF-e.
// Cores cruas são a exceção R-DS-002 (status fixo de alerta), copiadas do vivo, não inventadas.
const NFE_CERT_CORES = {
  vencido:  { border: "oklch(0.55 0.20 25)", bg: "oklch(0.32 0.10 25 / 0.40)", fg: "oklch(0.78 0.10 25)" },
  vencendo: { border: "oklch(0.78 0.15 80)", bg: "oklch(0.32 0.08 80 / 0.40)", fg: "oklch(0.82 0.10 80)" }
};

function useNfeCert() {
  const c = MOCK.NFE_CERT;
  if (!c || c.status !== "vencendo" && c.status !== "vencido") return null;
  const dias = c.dias_restantes ?? 0;
  const vencido = c.status === "vencido";
  const abs = Math.abs(dias);
  return {
    vencido,
    cores: NFE_CERT_CORES[vencido ? "vencido" : "vencendo"],
    label: vencido ? "Certificado vencido" : "Cert vence em breve",
    detalhe: vencido ? `há ${abs} dia${abs === 1 ? "" : "s"}` : `${dias} dia${dias === 1 ? "" : "s"} restantes`
  };
}

function NfeCertBadge({ onSelectRoute }) {
  const c = useNfeCert();
  if (!c) return null;
  const Icon = c.vencido ? I.shield : I.alert;
  return (
    <button type="button" className="sb-cert" onClick={() => onSelectRoute?.("fiscal-config")}
    title={`${c.label} — ${c.detalhe}. Clique pra renovar.`}
    style={{ borderColor: c.cores.border, background: c.cores.bg, color: c.cores.fg }}>
      <Icon className="ic" size={14} />
      <span className="sb-cert-txt">
        <b>{c.label}</b>
        <span>{c.detalhe}</span>
      </span>
    </button>);

}

// No rail (56px) o texto não cabe: vira só o ícone, com o mesmo nome acessível do tooltip.
function NfeCertBadgeRail({ onSelectRoute }) {
  const c = useNfeCert();
  if (!c) return null;
  const Icon = c.vencido ? I.shield : I.alert;
  return (
    <button type="button" className="sb-rail-btn sb-cert-rail" onClick={() => onSelectRoute?.("fiscal-config")}
    aria-label={`${c.label} — ${c.detalhe}. Clique pra renovar.`} data-tip={c.label}
    style={{ borderColor: c.cores.border, background: c.cores.bg, color: c.cores.fg }}>
      <Icon className="ic" size={16} />
    </button>);

}

// ─── Linha de item (hub ou ghost) ───
// A1 (2026-09-10): era `<div role="link" tabIndex={0}>` com onKeyDown à mão. Virou `<button>` real —
// foco, Enter/Espaço e papel vêm do agente do usuário, não de código nosso.
// UI-0011 (2026-09-10): SidebarTabs · SidebarChat · ConvRow removidos daqui — zero call sites,
// e o vivo aposentou a aba Chat em 2026-05-05 (conv switcher vive em Pages/Copiloto/Chat.tsx).
function ItemRow({ item, active, ghost, groupDot, onSelect, count, anchor, ghostCount }) {
  const Icon = I[item.icon];
  return (
    <button type="button"
      className={`sb-item sb-sub${ghost ? " sb-ghost" : ""}${active ? " active" : ""}`}
      aria-current={active ? "page" : undefined}
      onClick={onSelect}
      style={active && groupDot ? { borderLeftColor: groupDot } : null}>
      {Icon && <Icon className="ic" />}
      <span className="label" data-comment-anchor={anchor}>{item.label}</span>
      <WipMark routeId={item.id} />
      {count ? <span className="badge">{count}</span> : <ItemEnd routeId={item.id} ghostCount={ghostCount} />}
    </button>);

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
      <button type="button" className="sb-ghost-more" onClick={() => setTudo(true)}
      aria-expanded="false" aria-label={`Mostrar mais ${extras.length} telas`}>
        <span className="sb-ghost-more-d" aria-hidden="true">⋯</span><span>mais {extras.length}</span>
      </button>}
      {tudo && ghosts.length > GHOST_TETO &&
      <button type="button" className="sb-ghost-more" onClick={() => setTudo(false)}
      aria-expanded="true" aria-label="Mostrar menos telas">
        <span className="sb-ghost-more-d" aria-hidden="true">⌃</span><span>mostrar menos</span>
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
            <button type="button" key={entry.id} className={`sb-item ${isActive ? "active" : ""}`}
            aria-current={isActive ? "page" : undefined}
            onClick={() => onSelectRoute(entry.id)}>
              <Icon className="ic" />
              <span className="label">{entry.label}</span>
              <WipMark routeId={entry.id} />
              {n ? <span className="badge">{n}</span> : <ItemEnd routeId={entry.id} />}
            </button>);

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
  const [vibe, setVibe] = useState(() => lerShell("vibe", "workspace"));
  const [tema, setTema] = useState(() => lerShell("theme", "dark"));
  const [presenca, setPresenca] = useState("disponivel");
  const [confSair, setConfSair] = useState(false);
  const pres = PRESENCAS.find((p) => p.id === presenca) || PRESENCAS[0];
  // Manda no tweak do shell (dono único). Sem __setTweak não finjo que mudou.
  const mandarShell = (chave, valor, refletir) => {
    if (typeof window.__setTweak === "function") {window.__setTweak(chave, valor);refletir(valor);}
  };
  const escolherVibe = (id) => mandarShell("vibe", id, setVibe);
  const escolherTema = (id) => mandarShell("theme", id, setTema);
  return (
    <div className="user-menu" onClick={(e) => e.stopPropagation()}>
      <div className="user-menu-head">
        <span className="avatar" aria-hidden="true">WR</span>
        <div className="meta">
          <b>Wagner Rocha Araujo</b>
          <small>wagner@oimpresso.com.br</small>
        </div>
      </div>
      <button type="button" className="um-item" onClick={() => go("perfil")}><I.user className="ic" /> <span className="label">Meu perfil</span></button>
      {/* Itens de usuário — canon repo: vivem no rodapé, não no corpo */}
      {(MOCK.USER_MENU || []).map((it) => {
        const Icon = I[it.icon] || I.cog;
        return (
          <button type="button" key={it.id} className="um-item" onClick={() => go(it.id)}>
            <Icon className="ic" /> <span className="label">{it.label}</span>
          </button>);

      })}
      {/* Cascata Superadmin — admin de plataforma fora do menu principal */}
      {superItems.length > 0 &&
      <button type="button" className={"um-item um-cascade" + (sub === "super" ? " active" : "")}
      aria-expanded={sub === "super"}
      onClick={() => setSub(sub === "super" ? null : "super")}>
          <I.shield className="ic" /> <span className="label">Superadmin</span> <span className="arrow" aria-hidden="true">›</span>
        </button>
      }
      {sub === "super" &&
      <div className="um-sub">
          {superItems.map((it) => {
          const Icon = I[it.icon] || I.cog;
          return (
            <button type="button" key={it.id} className="um-item" onClick={() => go(it.id)}>
                <Icon className="ic" /> <span className="label">{it.label}</span>
                <WipMark routeId={it.id} />
              </button>);

        })}
        </div>
      }
      <div className="um-sep" />
      <button type="button" className={"um-item um-cascade" + (sub === "pres" ? " active" : "")}
      aria-expanded={sub === "pres"}
      onClick={() => setSub(sub === "pres" ? null : "pres")}>
        <span className="um-status" aria-hidden="true" style={{ background: pres.cor }} /> <span className="label">{pres.label}</span> <span className="arrow" aria-hidden="true">›</span>
      </button>
      {sub === "pres" &&
      <div className="um-sub">
          {PRESENCAS.map((p) =>
        <button type="button" key={p.id} className={"um-item" + (presenca === p.id ? " active" : "")}
        aria-pressed={presenca === p.id}
        onClick={() => setPresenca(p.id)}>
              <span className="um-status" aria-hidden="true" style={{ background: p.cor }} />
              <span className="label">{p.label}</span>
              {presenca === p.id && <I.check className="ic um-vibe-ck" />}
            </button>
        )}
        </div>
      }
      <button type="button" className={"um-item um-cascade" + (sub === "tema" ? " active" : "")}
      aria-expanded={sub === "tema"}
      onClick={() => setSub(sub === "tema" ? null : "tema")}>
        <I.moon className="ic" /> <span className="label">Aparência</span> <span className="um-vibe-cur">{tema === "dark" ? "escuro" : "claro"}</span> <span className="arrow" aria-hidden="true">›</span>
      </button>
      {sub === "tema" &&
      <div className="um-sub">
          {TEMAS.map((t) =>
        <button type="button" key={t.id} className={"um-item um-vibe" + (tema === t.id ? " active" : "")}
        aria-pressed={tema === t.id}
        onClick={() => escolherTema(t.id)}>
              <I.moon className="ic" style={t.id === "light" ? { opacity: 0.45 } : null} />
              <span className="label">{t.label}<em className="um-vibe-d">{t.desc}</em></span>
              {tema === t.id && <I.check className="ic um-vibe-ck" />}
            </button>
        )}
        </div>
      }
      {/* 4ª cascata — Modo de trabalho. O vivo mostra um kbd "⌘/" aqui
          (Sidebar.tsx:1273) que NENHUM listener liga (o AppShellV2 liga só ⌘K
          e ⌘\); não porto atalho morto — virou resíduo pra [W]. */}
      <button type="button" className={"um-item um-cascade" + (sub === "vibes" ? " active" : "")}
      aria-expanded={sub === "vibes"}
      onClick={() => setSub(sub === "vibes" ? null : "vibes")}>
        <I.palette className="ic" /> <span className="label">Modo de trabalho</span> <span className="um-vibe-cur">{vibe}</span> <span className="arrow" aria-hidden="true">›</span>
      </button>
      {sub === "vibes" &&
      <div className="um-sub">
          {VIBES.map((v) =>
        <button type="button" key={v.id} className={"um-item um-vibe" + (vibe === v.id ? " active" : "")}
        aria-pressed={vibe === v.id}
        onClick={() => escolherVibe(v.id)}>
              <span className="um-status" aria-hidden="true" style={{ background: v.dot }} />
              <span className="label">{v.label}<em className="um-vibe-d">{v.desc}</em></span>
              {vibe === v.id && <I.check className="ic um-vibe-ck" />}
            </button>
        )}
        </div>
      }
      <div className="um-sep" />
      <button type="button" className="um-item" onClick={() => {onClose?.();window.__openCmdK?.();}}><I.keyboard className="ic" /> <span className="label">Buscar tela</span> <span className="kbd" aria-hidden="true">⌘K</span></button>
      {(MOCK.FOOTER_LINKS || []).map((it) => {
        const Icon = I[it.icon] || I.book;
        return (
          <button type="button" key={it.id} className="um-item" onClick={() => go(it.id)}>
            <Icon className="ic" /> <span className="label">{it.label}</span>
          </button>);

      })}
      <div className="um-sep" />
      {confSair ?
      <div className="um-sub um-sair">
          <p className="um-sair-q">Encerrar a sessão?</p>
          <div className="um-sair-acoes">
            <button type="button" className="um-item um-sair-ok" onClick={() => window.location.reload()}>
              <I.exit className="ic" /> <span className="label">Encerrar</span>
            </button>
            <button type="button" className="um-item" onClick={() => setConfSair(false)}>
              <span className="label">Cancelar</span>
            </button>
          </div>
        </div> :

      <button type="button" className="um-item" onClick={() => setConfSair(true)}><I.exit className="ic" /> <span className="label">Sair</span></button>
      }
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
      <button type="button" className="sb-user-btn" onClick={() => setOpen(!open)} aria-haspopup="menu" aria-expanded={open}>
        <span className="avatar" aria-hidden="true">WR</span>
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
      <button type="button" className="sb-rail-btn sb-cp-rail-btn" onClick={() => setOpen(!open)}
      aria-haspopup="menu" aria-expanded={open} aria-label={`Empresa: ${company.name}. Trocar de empresa`} data-tip={company.name}>
        <span className={`avatar ${company.grad}`} aria-hidden="true">{company.initials}</span>
      </button>
      {open &&
      <div className="sb-dd sb-dd-rail" role="menu">
          <div className="sb-dd-h">EMPRESAS</div>
          {MOCK.COMPANIES.map((c) =>
        <button type="button" key={c.id} className="sb-dd-i" role="menuitemradio" aria-checked={c.id === company.id} onClick={() => {onChange(c);setOpen(false);}}>
              <span className={`avatar ${c.grad}`} style={{ width: 18, height: 18, borderRadius: 4, fontSize: 10, fontWeight: 700, color: "#fff", display: "grid", placeItems: "center" }}>{c.initials}</span>
              <span>{c.name}</span>
              {c.id === company.id && <I.check className="ic check" size={14} />}
            </button>
        )}
          <div className="sb-dd-sep" />
          <button type="button" className="sb-dd-foot" role="menuitem" onClick={() => {setOpen(false);window.__selectRoute?.("sa-negocios");}}>+ Adicionar empresa</button>
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
      <button type="button" className="sb-rail-btn sb-user-rail-btn" onClick={() => setOpen(!open)} aria-haspopup="menu" aria-expanded={open} aria-label="Wagner Rocha — menu do usuário" data-tip="Wagner Rocha">
        <span className="avatar" aria-hidden="true">WR</span>
      </button>
    </div>);

}

// ─── Sidebar principal ───
function Sidebar({ company, onCompany, activeRoute, onSelectRoute, mode = "expanded", onModeChange, papel = "wagner (admin)", showGhosts = true }) {
  const rail = mode === "rail";
  const nextMode = rail ? "expanded" : "rail";
  const toggleTitle = rail ? "Expandir sidebar (⌘\\)" : "Recolher sidebar (⌘\\)";
  const asideRef = useRef(null);
  // Dica do rail em camada FIXA (não é `::after` dentro do .sb-body).
  // Por que: pseudo absoluto conta como overflow rolável do ancestral, então a
  // dica projetada ~124px além de uma coluna de 45px pedia barra horizontal —
  // e matar isso com `overflow-x:hidden` clipava a própria dica (o ancestral
  // com overflow em UM eixo clipa nos dois). Mesmo padrão do `.sb-rail-flyout`.
  const [tip, setTip] = useState(null);
  useEffect(() => {
    if (!rail) {setTip(null);return;}
    const raiz = asideRef.current;
    if (!raiz) return;
    const mostrar = (e) => {
      const alvo = e.target.closest?.("[data-tip]");
      if (!alvo || !raiz.contains(alvo)) {setTip(null);return;}
      const r = alvo.getBoundingClientRect();
      setTip({ texto: alvo.getAttribute("data-tip"), top: r.top + r.height / 2, left: r.right + 8 });
    };
    const esconder = (e) => {
      if (e.target.closest?.("[data-tip]")) setTip(null);
    };
    raiz.addEventListener("mouseover", mostrar);
    raiz.addEventListener("mouseout", esconder);
    raiz.addEventListener("focusin", mostrar);
    raiz.addEventListener("focusout", esconder);
    return () => {
      raiz.removeEventListener("mouseover", mostrar);
      raiz.removeEventListener("mouseout", esconder);
      raiz.removeEventListener("focusin", mostrar);
      raiz.removeEventListener("focusout", esconder);
    };
  }, [rail]);

  return (
    <aside className={"sb" + (rail ? " sb--rail" : "")} ref={asideRef}>
      <div className="sb-top">
        {rail ?
        <CompanyPickerRail company={company} onChange={onCompany} /> :
        <CompanyPicker company={company} onChange={onCompany} />}
      </div>
      {rail ?
      <NfeCertBadgeRail onSelectRoute={onSelectRoute} /> :
      <NfeCertBadge onSelectRoute={onSelectRoute} />}
      <div className="sb-body">
        {rail ?
        <SidebarMenuRail activeRoute={activeRoute} onSelectRoute={onSelectRoute} papel={papel} showGhosts={showGhosts} /> :
        <SidebarMenu activeRoute={activeRoute} onSelectRoute={onSelectRoute} papel={papel} showGhosts={showGhosts} />}
      </div>
      {rail ? <SidebarUserRail /> : <SidebarUser />}
      {tip &&
      <div className="sb-rail-tip" role="presentation" style={{ top: tip.top, left: tip.left }}>{tip.texto}</div>}

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
