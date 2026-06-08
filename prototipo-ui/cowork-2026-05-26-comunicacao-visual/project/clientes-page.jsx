// clientes-page.jsx — Pessoas (Cliente · Fornecedor · Funcionário · Representante)
// 2026-05-25 — refactor: dispatcher por role. Cada entidade tem vocabulário próprio.
// Charter Cockpit V2 cream-and-navy preservado. Wave A-G features só onde fazem sentido.

const { useState: useStateC, useMemo: useMemoC, useEffect: useEffectC, useRef: useRefC } = React;

// ════════════════════════════════════════════════════════════════════
// HELPERS COMPARTILHADOS
// ════════════════════════════════════════════════════════════════════
function cliHash(s) {
  let h = 0; const str = String(s);
  for (let i = 0; i < str.length; i++) h = (h * 31 + str.charCodeAt(i)) | 0;
  return Math.abs(h);
}

const CLI_AVATAR_PALETTE = [
  { bg: "oklch(0.62 0.13 255)", fg: "#fff" },
  { bg: "oklch(0.65 0.14 145)", fg: "#fff" },
  { bg: "oklch(0.68 0.13 60)",  fg: "#fff" },
  { bg: "oklch(0.62 0.14 25)",  fg: "#fff" },
  { bg: "oklch(0.60 0.10 280)", fg: "#fff" },
  { bg: "oklch(0.55 0.08 200)", fg: "#fff" },
  { bg: "oklch(0.58 0.12 165)", fg: "#fff" },
  { bg: "oklch(0.62 0.13 320)", fg: "#fff" },
];
function avatarColor(name) { return CLI_AVATAR_PALETTE[cliHash(name) % CLI_AVATAR_PALETTE.length]; }
function initialsOf(name) { return name.split(" ").slice(0, 2).map((s) => s[0]).join("").toUpperCase(); }
function fmtBRL(n) { return (n || 0).toLocaleString("pt-BR", { style: "currency", currency: "BRL" }); }
function fmtBRLshort(n) {
  if (!n) return "R$ [redacted Tier 0]";
  if (n >= 1000) return "R$ " + (n / 1000).toFixed(1).replace(".", ",") + "k";
  return fmtBRL(n);
}
function daysSince(iso) {
  if (!iso || iso === "—") return null;
  const [y, m, d] = iso.split("-").map(Number);
  if (!y) return null;
  const then = new Date(y, (m || 1) - 1, d || 1);
  return Math.floor((Date.now() - then.getTime()) / (1000 * 60 * 60 * 24));
}
function fmtAgo(iso) {
  const d = daysSince(iso);
  if (d == null) return "—";
  if (d < 7)  return `há ${d}d`;
  if (d < 60) return `há ${Math.floor(d / 7)}sem`;
  if (d < 365)return `há ${Math.floor(d / 30)}m`;
  return `há ${Math.floor(d / 365)}a`;
}

// ════════════════════════════════════════════════════════════════════
// PRIMITIVOS DE UI (usados por todas as views)
// ════════════════════════════════════════════════════════════════════

// FavStar — favorito persistido por role
function FavStar({ id, namespace, favs, toggle }) {
  const isFav = favs.has(id);
  return (
    <button
      className={`cli-fav-btn ${isFav ? "on" : ""}`}
      onClick={(e) => { e.stopPropagation(); toggle(id); }}
      aria-pressed={isFav} title={isFav ? "Remover dos favoritos" : "Marcar como favorito"}>
      {isFav ? <I.starFill size={14}/> : <I.star size={14}/>}
    </button>
  );
}

// RowKebab — menu ⋮ contextual por role
function RowKebab({ items, onOpenDetail }) {
  const [open, setOpen] = useStateC(false);
  const ref = useRefC(null);
  useEffectC(() => {
    if (!open) return;
    const close = (e) => { if (ref.current && !ref.current.contains(e.target)) setOpen(false); };
    document.addEventListener("mousedown", close);
    return () => document.removeEventListener("mousedown", close);
  }, [open]);
  return (
    <div className="cli-kebab-wrap" ref={ref}>
      <button className="cli-kebab-btn" onClick={(e) => { e.stopPropagation(); setOpen(!open); }}
        aria-expanded={open} title="Mais ações"><I.moreV size={14}/></button>
      {open && (
        <div className="cli-kebab-menu" onClick={(e) => e.stopPropagation()}>
          {items.map((it, i) => it.sep
            ? <div key={i} className="cli-kebab-sep"></div>
            : <button key={i} className={it.danger ? "danger" : ""}
                onClick={() => { setOpen(false); it.action === "open" ? onOpenDetail?.() : it.action?.(); }}>
                {it.icon && <it.icon size={12}/>} {it.label}
              </button>
          )}
        </div>
      )}
    </div>
  );
}

// FilterDropdown
function FilterDropdown({ label, value, options, onChange }) {
  const [open, setOpen] = useStateC(false);
  const ref = useRefC(null);
  useEffectC(() => {
    if (!open) return;
    const close = (e) => { if (ref.current && !ref.current.contains(e.target)) setOpen(false); };
    document.addEventListener("mousedown", close);
    return () => document.removeEventListener("mousedown", close);
  }, [open]);
  const cur = options.find((o) => o.id === value);
  const isActive = value && value !== "all";
  return (
    <div className="cli-fdrop-wrap" ref={ref}>
      <button className={`cli-fdrop-btn ${isActive ? "active" : ""}`} onClick={() => setOpen(!open)} aria-expanded={open}>
        <span className="cli-fdrop-l">{label}</span>
        {isActive && cur && <span className="cli-fdrop-v">{cur.label}</span>}
        <I.chevDown size={11}/>
      </button>
      {open && (
        <div className="cli-fdrop-menu">
          {options.map((o) => (
            <button key={o.id} className={value === o.id ? "active" : ""}
              onClick={() => { onChange(o.id); setOpen(false); }}>
              {o.label}
              {o.count != null && <span className="cli-fdrop-n">{o.count}</span>}
            </button>
          ))}
        </div>
      )}
    </div>
  );
}

// Avatar
function Avatar({ name, size = 32 }) {
  const c = avatarColor(name);
  return (
    <div className={`cli-avatar ${size >= 44 ? "lg" : ""}`} style={{ background: c.bg, color: c.fg, width: size, height: size, flexBasis: size, fontSize: size >= 44 ? 14 : 11 }}>
      {initialsOf(name)}
    </div>
  );
}

// SaldoCell / AmountCell
function SaldoNeg({ value, title = "Em aberto" }) {
  if (!value) return <span className="cli-cell-muted">—</span>;
  return <span className="cli-saldo-neg" title={title}>{fmtBRL(value)}</span>;
}

// ════════════════════════════════════════════════════════════════════
// FAVORITES HOOK (namespace por role)
// ════════════════════════════════════════════════════════════════════
function useFavorites(namespace) {
  const key = `oimpresso.${namespace}.favorites`;
  const [favs, setFavs] = useStateC(() => {
    try { return new Set(JSON.parse(localStorage.getItem(key) || "[]")); }
    catch (_) { return new Set(); }
  });
  const toggle = (id) => setFavs((prev) => {
    const next = new Set(prev);
    if (next.has(id)) next.delete(id); else next.add(id);
    try { localStorage.setItem(key, JSON.stringify([...next])); } catch (_) {}
    return next;
  });
  return [favs, toggle];
}

// Atalho `/` pra focar busca
function useSearchShortcut(ref) {
  useEffectC(() => {
    const onKey = (e) => {
      if (e.key === "/" && document.activeElement?.tagName !== "INPUT") {
        e.preventDefault(); ref.current?.focus();
      }
    };
    window.addEventListener("keydown", onKey);
    return () => window.removeEventListener("keydown", onKey);
  }, []);
}

// ════════════════════════════════════════════════════════════════════
// PAGE SHELL — header + role tabs + view dispatch
// ════════════════════════════════════════════════════════════════════
const ROLE_DEFS = {
  customer:       { icon: I.users,     title: "Clientes",       unit: "cadastrados", cta: "Novo cliente" },
  supplier:       { icon: I.truck,     title: "Fornecedores",   unit: "cadastrados", cta: "Novo fornecedor" },
  employee:       { icon: I.briefcase, title: "Funcionários",   unit: "no quadro",   cta: "Novo funcionário" },
  representative: { icon: I.clients,   title: "Representantes", unit: "ativos",      cta: "Novo representante" },
  all:            { icon: I.list,      title: "Pessoas",        unit: "no diretório",cta: null },
};

function CliListPage() {
  const initialRole = window.__PESSOAS_ROLE_INITIAL || "customer";
  const [role, setRole] = useStateC(initialRole);

  // Counts globais pra tabs (fonte: data-people.jsx + OS_DATA)
  const counts = window.PEOPLE_COUNTS || { customer: 0, supplier: 0, employee: 0, representative: 0, all: 0 };
  const def = ROLE_DEFS[role] || ROLE_DEFS.customer;

  const ROLE_TABS = [
    { id: "all",            label: "Todos",          icon: I.list,      n: counts.all },
    { id: "customer",       label: "Clientes",       icon: I.users,     n: counts.customer },
    { id: "supplier",       label: "Fornecedores",   icon: I.truck,     n: counts.supplier },
    { id: "employee",       label: "Funcionários",   icon: I.briefcase, n: counts.employee },
    { id: "representative", label: "Representantes", icon: I.clients,   n: counts.representative },
  ];

  return (
    <div className="os-page cli-page">
      <header className="cli-pageheader">
        <div className="cli-pageheader-l">
          <div className="cli-pageheader-icon"><def.icon size={20}/></div>
          <div className="cli-pageheader-title-wrap">
            <h1>{def.title}</h1>
            <p>
              {role === "all"
                ? <><strong>{counts.all}</strong> {def.unit}</>
                : <><strong>{counts[role] || 0}</strong> {def.unit}</>}
            </p>
          </div>
        </div>
        <div className="cli-pageheader-r">
          <button className="os-btn ghost"><I.upload size={13}/> Importar</button>
          {def.cta && <button className="os-btn primary"><I.plus size={13}/> {def.cta}</button>}
        </div>
      </header>

      <nav className="cli-moduletopnav" aria-label="Tipo de pessoa">
        {ROLE_TABS.map((t) => (
          <button key={t.id}
            className={`cli-moduletopnav-tab ${role === t.id ? "active" : ""}`}
            onClick={() => setRole(t.id)}
            aria-current={role === t.id ? "page" : undefined}>
            <t.icon size={14}/>
            <span>{t.label}</span>
            <span className="cli-moduletopnav-n">{t.n}</span>
          </button>
        ))}
      </nav>

      {role === "customer"       && <CustomerView/>}
      {role === "supplier"       && <SupplierView/>}
      {role === "employee"       && <EmployeeView/>}
      {role === "representative" && <RepresentativeView/>}
      {role === "all"            && <AllView setRole={setRole}/>}
    </div>
  );
}

// ════════════════════════════════════════════════════════════════════
// VIEW: CLIENTE
// Vocabulário: Frescor · Saldo · OS · VIP · Tags
// ════════════════════════════════════════════════════════════════════
const CLI_UFS = ["SP","SP","SP","SP","RJ","MG","PR","RS","SC"];
const CLI_CITIES = {
  SP: ["São Paulo","Campinas","Santo André","Guarulhos","Osasco","Diadema","São Bernardo","Barueri"],
  RJ: ["Rio de Janeiro","Niterói","Petrópolis"], MG: ["Belo Horizonte","Contagem","Juiz de Fora"],
  PR: ["Curitiba","Londrina"], RS: ["Porto Alegre","Caxias do Sul"], SC: ["Florianópolis","Tubarão"],
};
const CLI_TAGS_POOL = ["VIP","Indicador","Atraso recorrente","Premium","Boleto","PIX","Cartão"];

function FrescorPill({ state, label }) {
  return (
    <span className={`cli-frescor cli-frescor-${state}`}>
      <span className="cli-frescor-dot" aria-hidden="true"></span>
      <span className="cli-frescor-state">{state}</span>
      <span className="cli-frescor-sep">·</span>
      <span className="cli-frescor-label">{label}</span>
    </span>
  );
}

function deriveCli(c, stats) {
  const h = cliHash(c.id);
  const tipo = (h % 5 === 0) ? "PF" : "PJ";
  const uf = CLI_UFS[h % CLI_UFS.length];
  const city = CLI_CITIES[uf][(h >> 3) % CLI_CITIES[uf].length];
  let saldo = 0;
  if (stats.lateCount > 0) saldo = stats.totalValue * 0.38;
  else if (stats.openCount > 0 && h % 4 === 0) saldo = stats.totalValue * 0.22;
  let frescor;
  if (stats.count === 0) frescor = { state: "frio", label: "sem histórico" };
  else if (stats.lateCount > 0) { const m = 1 + (h % 11); frescor = { state: "distante", label: `há ${m}m` }; }
  else if (stats.openCount > 0) { const w = 1 + (h % 4); frescor = { state: w <= 2 ? "recente" : "fresc", label: w === 1 ? "há 1sem" : `há ${w}sem` }; }
  else { const m = 2 + (h % 6); frescor = { state: m >= 4 ? "frio" : "distante", label: `há ${m}m` }; }
  const nTags = h % 3 === 0 ? 2 : (h % 5 === 0 ? 1 : 0);
  const tags = [];
  for (let i = 0; i < nTags; i++) tags.push(CLI_TAGS_POOL[(h + i * 7) % CLI_TAGS_POOL.length]);
  if (stats.totalValue > 2000) tags.unshift("VIP");
  return { tipo, uf, city, saldo, frescor, tags, isVip: stats.totalValue > 2000, isNew: h % 7 === 0 };
}

function clientStats(client, osList) {
  const own = osList.filter((o) => o.client === client.name);
  const open = own.filter((o) => !["entregue","cancelado"].includes(o.stage));
  const late = own.filter((o) => /atrasada/i.test(o.deadline));
  const totalValue = own.reduce((s, o) => {
    const n = parseFloat((o.value || "0").replace(/[^\d,]/g, "").replace(",", "."));
    return s + (isNaN(n) ? 0 : n);
  }, 0);
  return { count: own.length, openCount: open.length, lateCount: late.length, totalValue, ownList: own };
}

function CustomerView() {
  const OS_DATA = window.OS_DATA || {};
  const OS_LIST = OS_DATA.OS_LIST || [];
  const OS_CLIENTS = OS_DATA.OS_CLIENTS || [];

  const [q, setQ] = useStateC("");
  const [openId, setOpenId] = useStateC(null);
  const [fStatus, setFStatus] = useStateC("all");
  const [fTipo, setFTipo] = useStateC("all");
  const [fUf, setFUf] = useStateC("all");
  const [fTags, setFTags] = useStateC("all");
  const [fSemCompra, setFSemCompra] = useStateC("all");
  const [fSaldo, setFSaldo] = useStateC("all");
  const [favs, toggleFav] = useFavorites("clientes");
  const searchRef = useRefC(null);
  useSearchShortcut(searchRef);

  const enriched = useMemoC(() => OS_CLIENTS.map((c) => {
    const stats = clientStats(c, OS_LIST);
    return { c, stats, derived: deriveCli(c, stats) };
  }), [OS_CLIENTS, OS_LIST]);

  const kpis = useMemoC(() => ({
    total:       enriched.length,
    ativos:      enriched.filter((e) => e.stats.openCount > 0).length,
    vips:        enriched.filter((e) => e.derived.isVip).length,
    comSaldo:    enriched.filter((e) => e.derived.saldo > 0).length,
    sem90d:      enriched.filter((e) => e.derived.frescor.state === "frio" || e.derived.frescor.state === "distante").length,
    novos:       enriched.filter((e) => e.derived.isNew).length,
    faturamento: enriched.reduce((s, e) => s + e.stats.totalValue, 0),
    saldoTotal:  enriched.reduce((s, e) => s + e.derived.saldo, 0),
  }), [enriched]);

  const ufList = useMemoC(() => {
    const m = {}; enriched.forEach((e) => { m[e.derived.uf] = (m[e.derived.uf] || 0) + 1; });
    return Object.entries(m).sort((a, b) => b[1] - a[1]);
  }, [enriched]);

  const filtered = enriched.filter(({ c, stats, derived }) => {
    if (fStatus === "active" && stats.openCount === 0) return false;
    if (fStatus === "late" && stats.lateCount === 0) return false;
    if (fStatus === "idle" && stats.openCount > 0) return false;
    if (fTipo !== "all" && derived.tipo !== fTipo) return false;
    if (fUf !== "all" && derived.uf !== fUf) return false;
    if (fTags === "vip" && !derived.isVip) return false;
    if (fSaldo === "negativo" && derived.saldo <= 0) return false;
    if (fSaldo === "zero" && derived.saldo > 0) return false;
    if (fSemCompra === "90d" && !(derived.frescor.state === "frio" || derived.frescor.state === "distante")) return false;
    if (fSemCompra === "30d" && derived.frescor.state === "recente") return false;
    if (q && !`${c.name} ${c.doc} ${c.contact} ${c.phone} ${derived.city}`.toLowerCase().includes(q.toLowerCase())) return false;
    return true;
  });

  const activeF = [fStatus, fTipo, fUf, fTags, fSemCompra, fSaldo].filter((v) => v && v !== "all").length;
  const open = openId ? enriched.find((e) => e.c.id === openId) : null;

  return (
    <>
      <div className="cli-kpihero">
        <KpiHero l="Clientes ativos" v={kpis.ativos} s="com OS aberta"/>
        <KpiHero l="VIPs" v={kpis.vips} s="prioridade total"/>
        <KpiHero l="Com saldo" v={kpis.comSaldo} aside={kpis.saldoTotal > 0 ? fmtBRLshort(kpis.saldoTotal) : null} s="inadimplência"/>
        <KpiHero l="Sem compra 90d" v={kpis.sem90d} s="risco churn"/>
        <KpiHero l="Novos este mês" v={kpis.novos} s="desde dia 1"/>
        <KpiHero l="Faturamento" v={fmtBRLshort(kpis.faturamento)} s={<>hoje · <span className="cli-kpihero-delta">+12%</span> vs ontem</>} dark/>
      </div>

      <Toolbar searchRef={searchRef} q={q} setQ={setQ}
        placeholder="Buscar nome, CNPJ/CPF, contato, telefone, cidade…"
        filtersCount={activeF} onClear={() => { setFStatus("all"); setFTipo("all"); setFUf("all"); setFTags("all"); setFSemCompra("all"); setFSaldo("all"); }}
        resultCount={filtered.length}>
        <FilterDropdown label="Status" value={fStatus} onChange={setFStatus} options={[
          { id:"all", label:"Todos" }, { id:"active", label:"Com OS aberta", count: kpis.ativos },
          { id:"late", label:"Com atraso" }, { id:"idle", label:"Sem OS aberta" },
        ]}/>
        <FilterDropdown label="Tipo" value={fTipo} onChange={setFTipo} options={[
          { id:"all", label:"Todos" }, { id:"PJ", label:"Pessoa jurídica" }, { id:"PF", label:"Pessoa física" },
        ]}/>
        <FilterDropdown label="UF" value={fUf} onChange={setFUf} options={[
          { id:"all", label:"Todas" }, ...ufList.map(([u, n]) => ({ id: u, label: u, count: n })),
        ]}/>
        <FilterDropdown label="Tags" value={fTags} onChange={setFTags} options={[
          { id:"all", label:"Todas" }, { id:"vip", label:"VIP", count: kpis.vips },
        ]}/>
        <FilterDropdown label="Sem compra há" value={fSemCompra} onChange={setFSemCompra} options={[
          { id:"all", label:"Sem filtro" }, { id:"30d", label:"30 dias" }, { id:"90d", label:"90 dias", count: kpis.sem90d },
        ]}/>
        <FilterDropdown label="Saldo" value={fSaldo} onChange={setFSaldo} options={[
          { id:"all", label:"Todos" }, { id:"negativo", label:"Em aberto", count: kpis.comSaldo }, { id:"zero", label:"Sem saldo" },
        ]}/>
      </Toolbar>

      <div className="os-table-wrap">
        <table className="os-table cli-table cli-table-v2">
          <thead><tr>
            <th>Cliente</th><th>Tipo</th><th>Documento</th><th>Cidade/UF</th>
            <th>Frescor</th><th className="num">Saldo</th><th className="num">OS</th>
            <th>Tags</th><th>Última OS</th><th></th><th></th>
          </tr></thead>
          <tbody>
            {filtered.map(({ c, stats, derived }) => (
              <tr key={c.id} className="cli-row" onClick={() => setOpenId(c.id)}>
                <td className="cli-td-cli">
                  <div className="cli-name">
                    <Avatar name={c.name}/>
                    <div className="cli-name-block">
                      <div className="cli-name-text">
                        {c.name}
                        {derived.isVip && <span className="cli-vip">VIP</span>}
                        {derived.isNew && <span className="cli-new">Novo</span>}
                      </div>
                      <div className="cli-name-sub"><I.phone size={10}/><span>{c.phone}</span></div>
                    </div>
                  </div>
                </td>
                <td><span className={`cli-tipo cli-tipo-${derived.tipo.toLowerCase()}`}>{derived.tipo}</span></td>
                <td><span className="cli-doc-mono">{c.doc}</span></td>
                <td className="cli-td-city">
                  <div className="cli-city-line"><I.mapPin size={10}/><span>{derived.city}</span></div>
                  <div className="cli-city-uf">{derived.uf}</div>
                </td>
                <td><FrescorPill state={derived.frescor.state} label={derived.frescor.label}/></td>
                <td className="num"><SaldoNeg value={derived.saldo}/></td>
                <td className="num">{stats.count || <span className="cli-cell-muted">0</span>}</td>
                <td className="cli-td-tags">
                  {derived.tags.length === 0 && <span className="cli-cell-muted">—</span>}
                  {derived.tags.slice(0, 2).map((t, i) => (
                    <span key={i} className={`cli-tag cli-tag-${t.toLowerCase().replace(/ /g, "-")}`}>{t}</span>
                  ))}
                  {derived.tags.length > 2 && <span className="cli-tag-more">+{derived.tags.length - 2}</span>}
                </td>
                <td>{c.lastOs ? <a className="cli-lastos-link" onClick={(e) => e.stopPropagation()}>{c.lastOs}</a> : <span className="cli-cell-muted">—</span>}</td>
                <td className="cli-td-fav"><FavStar id={c.id} favs={favs} toggle={toggleFav}/></td>
                <td className="cli-td-kebab"><RowKebab onOpenDetail={() => setOpenId(c.id)} items={[
                  { label:"Editar", icon: I.pencil, action: "open" },
                  { label:"Nova OS", icon: I.plus },
                  { label:"Exportar CSV", icon: I.upload },
                  { label:"Gerenciar tags", icon: I.tag },
                  { sep: true },
                  { label:"Excluir", icon: I.close, danger: true },
                ]}/></td>
              </tr>
            ))}
            {filtered.length === 0 && <tr><td colSpan={11} className="os-empty">Nenhum cliente encontrado.</td></tr>}
          </tbody>
        </table>
      </div>

      {open && <ClienteDetailDrawer client={open.c} stats={open.stats} derived={open.derived} osList={OS_LIST} onClose={() => setOpenId(null)}/>}
    </>
  );
}

function ClienteDetailDrawer({ client, stats, derived, osList, onClose }) {
  const own = stats.ownList.slice().sort((a, b) => parseInt(b.id) - parseInt(a.id));
  return (
    <div className="os-drawer-back" onClick={onClose}>
      <div className="os-drawer wide cli-drawer" onClick={(e) => e.stopPropagation()}>
        <div className="os-drawer-head">
          <div className="cli-head">
            <Avatar name={client.name} size={44}/>
            <div>
              <div className="cli-head-name">{client.name}{derived.isVip && <span className="cli-vip">VIP</span>}</div>
              <div className="cli-head-doc">
                <span className={`cli-tipo cli-tipo-${derived.tipo.toLowerCase()}`}>{derived.tipo}</span>
                <span>{client.doc}</span><span className="cli-head-sep">·</span>
                <span>{derived.city}/{derived.uf}</span>
              </div>
            </div>
          </div>
          <button className="os-icon-btn" onClick={onClose}><I.close size={16}/></button>
        </div>
        <div className="os-drawer-body">
          <div className="cli-kpis">
            <div className="cli-kpi"><div className="cli-kpi-v">{stats.count}</div><div className="cli-kpi-l">OS no total</div></div>
            <div className="cli-kpi"><div className="cli-kpi-v">{stats.openCount}</div><div className="cli-kpi-l">Em aberto</div></div>
            <div className={`cli-kpi ${stats.lateCount > 0 ? "danger" : ""}`}><div className="cli-kpi-v">{stats.lateCount}</div><div className="cli-kpi-l">Atrasadas</div></div>
            <div className="cli-kpi"><div className="cli-kpi-v">{fmtBRL(stats.totalValue)}</div><div className="cli-kpi-l">Valor total</div></div>
          </div>
          <div className="cli-section">
            <div className="cli-section-title">Frescor & Saldo</div>
            <div className="cli-info-grid">
              <div><div className="cli-info-l">Frescor</div><div className="cli-info-v"><FrescorPill state={derived.frescor.state} label={derived.frescor.label}/></div></div>
              <div><div className="cli-info-l">Saldo em aberto</div><div className="cli-info-v"><SaldoNeg value={derived.saldo}/></div></div>
              <div><div className="cli-info-l">Tags</div><div className="cli-info-v cli-info-tags">
                {derived.tags.length === 0 && <span className="cli-cell-muted">—</span>}
                {derived.tags.map((t, i) => <span key={i} className="cli-tag">{t}</span>)}
              </div></div>
            </div>
          </div>
          <div className="cli-section">
            <div className="cli-section-title">Contato</div>
            <div className="cli-info-grid">
              <div><div className="cli-info-l">Nome</div><div className="cli-info-v">{client.contact}</div></div>
              <div><div className="cli-info-l">Telefone</div><div className="cli-info-v">{client.phone}</div></div>
              <div><div className="cli-info-l">CNPJ/CPF</div><div className="cli-info-v cli-doc-mono">{client.doc}</div></div>
              <div><div className="cli-info-l">Última OS</div><div className="cli-info-v">{client.lastOs || "—"}</div></div>
            </div>
          </div>
          <div className="cli-section">
            <div className="cli-section-title">Histórico de OS ({own.length})</div>
            <div className="cli-history">
              {own.length === 0 && <div className="cli-empty">Nenhuma OS registrada.</div>}
              {own.map((o) => (
                <div className="cli-os" key={o.id}>
                  <div className="cli-os-id">#{o.id}</div>
                  <div className="cli-os-prod">{o.product}</div>
                  <div className={`cli-os-stage stage-${o.stage}`}>{window.OS_DATA.OS_STAGES.find((s) => s.id === o.stage)?.label || o.stage}</div>
                  <div className="cli-os-deadline">{o.deadline}</div>
                  <div className="cli-os-value">{o.value}</div>
                </div>
              ))}
            </div>
          </div>
        </div>
        <div className="os-drawer-actions">
          <button className="os-btn primary"><I.plus size={14}/> Nova OS</button>
          <button className="os-btn"><I.pencil size={14}/> Editar cliente</button>
          <button className="os-btn ghost">Ver financeiro completo</button>
        </div>
      </div>
    </div>
  );
}

// ════════════════════════════════════════════════════════════════════
// VIEW: FORNECEDOR
// Vocabulário: Categoria · Lead time · Frequência · A pagar · Crítico
// ════════════════════════════════════════════════════════════════════
function SupplierView() {
  const SUPPLIERS = window.SUPPLIERS || [];
  const [q, setQ] = useStateC("");
  const [fCat, setFCat] = useStateC("all");
  const [fStatus, setFStatus] = useStateC("all");
  const [fAPagar, setFAPagar] = useStateC("all");
  const [favs, toggleFav] = useFavorites("fornecedores");
  const searchRef = useRefC(null);
  useSearchShortcut(searchRef);

  const kpis = useMemoC(() => ({
    total:    SUPPLIERS.length,
    criticos: SUPPLIERS.filter((s) => s.critical).length,
    aPagar:   SUPPLIERS.reduce((sum, s) => sum + (s.aPagar || 0), 0),
    aPagarN:  SUPPLIERS.filter((s) => s.aPagar > 0).length,
    leadAvg:  Math.round(SUPPLIERS.reduce((sum, s) => sum + s.leadDays, 0) / Math.max(SUPPLIERS.length, 1)),
    ativos:   SUPPLIERS.filter((s) => s.openOrders > 0).length,
  }), [SUPPLIERS]);

  const catList = useMemoC(() => {
    const m = {}; SUPPLIERS.forEach((s) => { m[s.category] = (m[s.category] || 0) + 1; });
    return Object.entries(m).sort((a, b) => b[1] - a[1]);
  }, [SUPPLIERS]);

  const filtered = SUPPLIERS.filter((s) => {
    if (fCat !== "all" && s.category !== fCat) return false;
    if (fStatus === "critical" && !s.critical) return false;
    if (fStatus === "active" && s.openOrders === 0) return false;
    if (fAPagar === "pending" && s.aPagar <= 0) return false;
    if (fAPagar === "zero" && s.aPagar > 0) return false;
    if (q && !`${s.name} ${s.doc} ${s.contact} ${s.category}`.toLowerCase().includes(q.toLowerCase())) return false;
    return true;
  });

  const activeF = [fCat, fStatus, fAPagar].filter((v) => v !== "all").length;

  return (
    <>
      <div className="cli-kpihero" style={{ gridTemplateColumns: "repeat(5, 1fr)" }}>
        <KpiHero l="Fornecedores" v={kpis.total} s="cadastrados"/>
        <KpiHero l="Críticos" v={kpis.criticos} s="atenção urgente" tone={kpis.criticos > 0 ? "danger" : null}/>
        <KpiHero l="Com pedido aberto" v={kpis.ativos} s={`${SUPPLIERS.reduce((a, s) => a + s.openOrders, 0)} ordens`}/>
        <KpiHero l="Lead time médio" v={`${kpis.leadAvg}d`} s="da emissão à entrega"/>
        <KpiHero l="A pagar" v={fmtBRLshort(kpis.aPagar)} s={`${kpis.aPagarN} fornecedores`} dark/>
      </div>

      <Toolbar searchRef={searchRef} q={q} setQ={setQ}
        placeholder="Buscar fornecedor, CNPJ, contato, categoria…"
        filtersCount={activeF} onClear={() => { setFCat("all"); setFStatus("all"); setFAPagar("all"); }}
        resultCount={filtered.length}>
        <FilterDropdown label="Categoria" value={fCat} onChange={setFCat} options={[
          { id: "all", label: "Todas" }, ...catList.map(([c, n]) => ({ id: c, label: c, count: n })),
        ]}/>
        <FilterDropdown label="Status" value={fStatus} onChange={setFStatus} options={[
          { id: "all", label: "Todos" },
          { id: "active", label: "Com pedido aberto", count: kpis.ativos },
          { id: "critical", label: "Críticos", count: kpis.criticos },
        ]}/>
        <FilterDropdown label="A pagar" value={fAPagar} onChange={setFAPagar} options={[
          { id: "all", label: "Todos" },
          { id: "pending", label: "Com saldo a pagar", count: kpis.aPagarN },
          { id: "zero", label: "Em dia" },
        ]}/>
      </Toolbar>

      <div className="os-table-wrap">
        <table className="os-table cli-table cli-table-v2">
          <thead><tr>
            <th>Fornecedor</th><th>Categoria</th><th>CNPJ</th>
            <th className="num">Lead</th><th>Frequência</th>
            <th className="num">A pagar</th><th>Vencimento</th>
            <th>Último pedido</th><th className="num">Pedidos abertos</th>
            <th></th><th></th>
          </tr></thead>
          <tbody>
            {filtered.map((s) => (
              <tr key={s.id} className="cli-row">
                <td className="cli-td-cli">
                  <div className="cli-name">
                    <Avatar name={s.name}/>
                    <div className="cli-name-block">
                      <div className="cli-name-text">
                        {s.name}
                        {s.critical && <span className="cli-pill-danger">Crítico</span>}
                      </div>
                      <div className="cli-name-sub"><I.phone size={10}/><span>{s.contact} · {s.phone}</span></div>
                    </div>
                  </div>
                </td>
                <td><span className="cli-tag-cat">{s.category}</span></td>
                <td><span className="cli-doc-mono">{s.doc}</span></td>
                <td className="num"><span className="cli-num-strong">{s.leadDays}d</span></td>
                <td className="cli-cell-muted" style={{ textTransform: "capitalize" }}>{s.freq}</td>
                <td className="num"><SaldoNeg value={s.aPagar} title="Saldo a pagar"/></td>
                <td><span className="cli-cell-mono">{s.dueDate}</span></td>
                <td>{fmtAgo(s.lastOrder)}</td>
                <td className="num">{s.openOrders || <span className="cli-cell-muted">0</span>}</td>
                <td className="cli-td-fav"><FavStar id={s.id} favs={favs} toggle={toggleFav}/></td>
                <td className="cli-td-kebab"><RowKebab items={[
                  { label:"Ver fornecedor", icon: I.pencil },
                  { label:"Novo pedido", icon: I.plus },
                  { label:"Lançar contas a pagar", icon: I.briefcase },
                  { label:"Histórico de cotações", icon: I.list },
                  { sep: true },
                  { label:"Desativar", icon: I.close, danger: true },
                ]}/></td>
              </tr>
            ))}
            {filtered.length === 0 && <tr><td colSpan={11} className="os-empty">Nenhum fornecedor encontrado.</td></tr>}
          </tbody>
        </table>
      </div>
    </>
  );
}

// ════════════════════════════════════════════════════════════════════
// VIEW: FUNCIONÁRIO
// Vocabulário: Cargo · Setor · Vínculo · Admissão · Acesso · Aniversário
// ════════════════════════════════════════════════════════════════════
function EmployeeView() {
  const EMPLOYEES = window.EMPLOYEES || [];
  const [q, setQ] = useStateC("");
  const [fDept, setFDept] = useStateC("all");
  const [fVinc, setFVinc] = useStateC("all");
  const [fStatus, setFStatus] = useStateC("all");
  const [favs, toggleFav] = useFavorites("funcionarios");
  const searchRef = useRefC(null);
  useSearchShortcut(searchRef);

  const kpis = useMemoC(() => {
    const today = new Date();
    const month = today.getMonth() + 1;
    const aniv = EMPLOYEES.filter((e) => {
      const m = parseInt((e.birth || "").split("/")[1]);
      return m === month;
    }).length;
    const ferias = EMPLOYEES.filter((e) => e.status === "férias").length;
    const dept = {};
    EMPLOYEES.forEach((e) => { dept[e.department] = (dept[e.department] || 0) + 1; });
    const topDept = Object.entries(dept).sort((a, b) => b[1] - a[1])[0] || ["—", 0];
    return {
      total:    EMPLOYEES.length,
      ativos:   EMPLOYEES.filter((e) => e.status === "ativo").length,
      producao: dept["Produção"] || 0,
      comercial:dept["Comercial"] || 0,
      aniv, ferias, topDept,
    };
  }, [EMPLOYEES]);

  const deptList = useMemoC(() => {
    const m = {}; EMPLOYEES.forEach((e) => { m[e.department] = (m[e.department] || 0) + 1; });
    return Object.entries(m).sort((a, b) => b[1] - a[1]);
  }, [EMPLOYEES]);

  const filtered = EMPLOYEES.filter((e) => {
    if (fDept !== "all" && e.department !== fDept) return false;
    if (fVinc !== "all" && e.vinculo !== fVinc) return false;
    if (fStatus !== "all" && e.status !== fStatus) return false;
    if (q && !`${e.name} ${e.doc} ${e.role} ${e.department}`.toLowerCase().includes(q.toLowerCase())) return false;
    return true;
  });

  const activeF = [fDept, fVinc, fStatus].filter((v) => v !== "all").length;

  return (
    <>
      <div className="cli-kpihero" style={{ gridTemplateColumns: "repeat(5, 1fr)" }}>
        <KpiHero l="No quadro" v={kpis.total} s={`${kpis.ativos} ativos`}/>
        <KpiHero l="Comercial" v={kpis.comercial} s="atendimento e vendas"/>
        <KpiHero l="Produção" v={kpis.producao} s="impressão e acabamento"/>
        <KpiHero l="Em férias" v={kpis.ferias} s="ausentes no momento" tone={kpis.ferias > 0 ? "warning" : null}/>
        <KpiHero l="Aniversários" v={kpis.aniv} s="este mês" dark/>
      </div>

      <Toolbar searchRef={searchRef} q={q} setQ={setQ}
        placeholder="Buscar nome, CPF, cargo, setor…"
        filtersCount={activeF} onClear={() => { setFDept("all"); setFVinc("all"); setFStatus("all"); }}
        resultCount={filtered.length}>
        <FilterDropdown label="Setor" value={fDept} onChange={setFDept} options={[
          { id: "all", label: "Todos" }, ...deptList.map(([d, n]) => ({ id: d, label: d, count: n })),
        ]}/>
        <FilterDropdown label="Vínculo" value={fVinc} onChange={setFVinc} options={[
          { id: "all", label: "Todos" },
          { id: "CLT", label: "CLT" }, { id: "PJ", label: "PJ" },
          { id: "Estagiário", label: "Estagiário" }, { id: "Sócio", label: "Sócio" },
        ]}/>
        <FilterDropdown label="Status" value={fStatus} onChange={setFStatus} options={[
          { id: "all", label: "Todos" },
          { id: "ativo", label: "Ativo" }, { id: "férias", label: "Em férias" },
          { id: "afastado", label: "Afastado" },
        ]}/>
      </Toolbar>

      <div className="os-table-wrap">
        <table className="os-table cli-table cli-table-v2">
          <thead><tr>
            <th>Funcionário</th><th>Cargo</th><th>Setor</th><th>Vínculo</th>
            <th>Admissão</th><th>Turno</th><th>Acesso</th>
            <th>Status</th><th>Aniversário</th>
            <th></th><th></th>
          </tr></thead>
          <tbody>
            {filtered.map((e) => (
              <tr key={e.id} className="cli-row">
                <td className="cli-td-cli">
                  <div className="cli-name">
                    <Avatar name={e.name}/>
                    <div className="cli-name-block">
                      <div className="cli-name-text">{e.name}</div>
                      <div className="cli-name-sub"><span className="cli-doc-mono">{e.doc}</span></div>
                    </div>
                  </div>
                </td>
                <td>{e.role}</td>
                <td><span className="cli-tag-dept">{e.department}</span></td>
                <td><span className={`cli-tipo cli-tipo-${e.vinculo.toLowerCase().replace(/[^a-z]/g, "")}`}>{e.vinculo}</span></td>
                <td className="cli-cell-mono">{e.admittedAt}</td>
                <td className="cli-cell-muted">{e.shift}</td>
                <td><span className="cli-pill-access">{e.access}</span></td>
                <td>
                  <span className={`cli-status-pill cli-status-${e.status === "férias" ? "ferias" : e.status}`}>
                    {e.status}
                  </span>
                </td>
                <td className="cli-cell-mono">{e.birth}</td>
                <td className="cli-td-fav"><FavStar id={e.id} favs={favs} toggle={toggleFav}/></td>
                <td className="cli-td-kebab"><RowKebab items={[
                  { label:"Ver perfil", icon: I.pencil },
                  { label:"Editar acesso", icon: I.briefcase },
                  { label:"Registrar férias", icon: I.list },
                  { label:"Folha de pagamento", icon: I.upload },
                  { sep: true },
                  { label:"Desligar", icon: I.close, danger: true },
                ]}/></td>
              </tr>
            ))}
            {filtered.length === 0 && <tr><td colSpan={11} className="os-empty">Nenhum funcionário encontrado.</td></tr>}
          </tbody>
        </table>
      </div>
    </>
  );
}

// ════════════════════════════════════════════════════════════════════
// VIEW: REPRESENTANTE
// Vocabulário: Região · Comissão · Carteira · Vendas mês · A pagar comissão
// ════════════════════════════════════════════════════════════════════
function RepresentativeView() {
  const REPS = window.REPRESENTATIVES || [];
  const [q, setQ] = useStateC("");
  const [fRegion, setFRegion] = useStateC("all");
  const [fStatus, setFStatus] = useStateC("all");
  const [favs, toggleFav] = useFavorites("representantes");
  const searchRef = useRefC(null);
  useSearchShortcut(searchRef);

  const kpis = useMemoC(() => {
    const vendasMes = REPS.reduce((s, r) => s + r.vendasMes, 0);
    const comissao = REPS.reduce((s, r) => s + r.aPagarComissao, 0);
    const carteira = REPS.reduce((s, r) => s + r.portfolio, 0);
    const top = REPS.slice().sort((a, b) => b.vendasMes - a.vendasMes)[0];
    return {
      total:   REPS.length,
      ativos:  REPS.filter((r) => r.status === "ativo").length,
      vendasMes, comissao, carteira, top: top?.name || "—",
    };
  }, [REPS]);

  const regionsList = useMemoC(() => {
    const m = {};
    REPS.forEach((r) => r.regions.forEach((reg) => { m[reg] = (m[reg] || 0) + 1; }));
    return Object.entries(m).sort((a, b) => b[1] - a[1]);
  }, [REPS]);

  const filtered = REPS.filter((r) => {
    if (fRegion !== "all" && !r.regions.includes(fRegion)) return false;
    if (fStatus !== "all" && r.status !== fStatus) return false;
    if (q && !`${r.name} ${r.doc} ${r.contact} ${r.regions.join(" ")}`.toLowerCase().includes(q.toLowerCase())) return false;
    return true;
  });

  const activeF = [fRegion, fStatus].filter((v) => v !== "all").length;

  return (
    <>
      <div className="cli-kpihero" style={{ gridTemplateColumns: "repeat(5, 1fr)" }}>
        <KpiHero l="Representantes" v={kpis.total} s={`${kpis.ativos} ativos`}/>
        <KpiHero l="Carteira total" v={kpis.carteira} s="clientes sob representação"/>
        <KpiHero l="Vendas no mês" v={fmtBRLshort(kpis.vendasMes)} s="faturamento via representante"/>
        <KpiHero l="Top performer" v={kpis.top.split(" ")[0]} s="maior vendas no mês"/>
        <KpiHero l="A pagar comissão" v={fmtBRLshort(kpis.comissao)} s="ciclo corrente" dark/>
      </div>

      <Toolbar searchRef={searchRef} q={q} setQ={setQ}
        placeholder="Buscar representante, CNPJ, contato, região…"
        filtersCount={activeF} onClear={() => { setFRegion("all"); setFStatus("all"); }}
        resultCount={filtered.length}>
        <FilterDropdown label="Região" value={fRegion} onChange={setFRegion} options={[
          { id: "all", label: "Todas" }, ...regionsList.map(([r, n]) => ({ id: r, label: r, count: n })),
        ]}/>
        <FilterDropdown label="Status" value={fStatus} onChange={setFStatus} options={[
          { id: "all", label: "Todos" },
          { id: "ativo", label: "Ativos" }, { id: "ociosa", label: "Ociosos" },
        ]}/>
      </Toolbar>

      <div className="os-table-wrap">
        <table className="os-table cli-table cli-table-v2">
          <thead><tr>
            <th>Representante</th><th>CNPJ</th><th>Região</th>
            <th className="num">Comissão</th><th className="num">Carteira</th>
            <th className="num">Vendas no mês</th><th className="num">A pagar</th>
            <th>Última venda</th><th>Status</th>
            <th></th><th></th>
          </tr></thead>
          <tbody>
            {filtered.map((r) => (
              <tr key={r.id} className="cli-row">
                <td className="cli-td-cli">
                  <div className="cli-name">
                    <Avatar name={r.name}/>
                    <div className="cli-name-block">
                      <div className="cli-name-text">{r.name}</div>
                      <div className="cli-name-sub"><I.phone size={10}/><span>{r.contact} · {r.phone}</span></div>
                    </div>
                  </div>
                </td>
                <td><span className="cli-doc-mono">{r.doc}</span></td>
                <td>{r.regions.map((reg, i) => <span key={i} className="cli-tag-region">{reg}</span>)}</td>
                <td className="num"><span className="cli-num-strong">{r.pct}%</span></td>
                <td className="num">{r.portfolio}</td>
                <td className="num"><span className="cli-num-strong">{fmtBRLshort(r.vendasMes)}</span></td>
                <td className="num"><SaldoNeg value={r.aPagarComissao} title="Comissão a pagar"/></td>
                <td>{fmtAgo(r.lastDeal)}</td>
                <td>
                  <span className={`cli-status-pill cli-status-${r.status === "ociosa" ? "ociosa" : "ativo"}`}>{r.status}</span>
                </td>
                <td className="cli-td-fav"><FavStar id={r.id} favs={favs} toggle={toggleFav}/></td>
                <td className="cli-td-kebab"><RowKebab items={[
                  { label:"Ver representante", icon: I.pencil },
                  { label:"Ver carteira", icon: I.list },
                  { label:"Lançar comissão", icon: I.briefcase },
                  { label:"Histórico de vendas", icon: I.upload },
                  { sep: true },
                  { label:"Desativar", icon: I.close, danger: true },
                ]}/></td>
              </tr>
            ))}
            {filtered.length === 0 && <tr><td colSpan={11} className="os-empty">Nenhum representante encontrado.</td></tr>}
          </tbody>
        </table>
      </div>
    </>
  );
}

// ════════════════════════════════════════════════════════════════════
// VIEW: TODOS (diretório minimal — só campos comuns)
// Vocabulário: Nome · Tipo · Documento · Contato · ⭐
// ════════════════════════════════════════════════════════════════════
function AllView({ setRole }) {
  const OS_CLIENTS  = window.OS_DATA?.OS_CLIENTS || [];
  const SUPPLIERS   = window.SUPPLIERS || [];
  const EMPLOYEES   = window.EMPLOYEES || [];
  const REPS        = window.REPRESENTATIVES || [];

  const all = useMemoC(() => [
    ...OS_CLIENTS.map((c) => ({ id: `c-${c.id}`, kind: "customer", name: c.name, doc: c.doc, contact: c.contact, sub: c.phone })),
    ...SUPPLIERS.map((s)   => ({ id: s.id, kind: "supplier", name: s.name, doc: s.doc, contact: s.contact, sub: s.category })),
    ...EMPLOYEES.map((e)   => ({ id: e.id, kind: "employee", name: e.name, doc: e.doc, contact: e.role,    sub: e.department })),
    ...REPS.map((r)        => ({ id: r.id, kind: "representative", name: r.name, doc: r.doc, contact: r.contact, sub: r.regions.join(", ") })),
  ], [OS_CLIENTS, SUPPLIERS, EMPLOYEES, REPS]);

  const [q, setQ] = useStateC("");
  const [fKind, setFKind] = useStateC("all");
  const [favs, toggleFav] = useFavorites("pessoas-all");
  const searchRef = useRefC(null);
  useSearchShortcut(searchRef);

  const kpis = useMemoC(() => ({
    total:    all.length,
    customer: all.filter((p) => p.kind === "customer").length,
    supplier: all.filter((p) => p.kind === "supplier").length,
    employee: all.filter((p) => p.kind === "employee").length,
    rep:      all.filter((p) => p.kind === "representative").length,
  }), [all]);

  const filtered = all.filter((p) => {
    if (fKind !== "all" && p.kind !== fKind) return false;
    if (q && !`${p.name} ${p.doc} ${p.contact}`.toLowerCase().includes(q.toLowerCase())) return false;
    return true;
  });

  const KIND_LABEL = {
    customer: "Cliente", supplier: "Fornecedor",
    employee: "Funcionário", representative: "Representante",
  };

  return (
    <>
      <div className="cli-kpihero" style={{ gridTemplateColumns: "repeat(5, 1fr)" }}>
        <KpiHero l="No diretório" v={kpis.total} s="todas as pessoas"/>
        <KpiHero l="Clientes" v={kpis.customer} s="abrir tela ›" onClick={() => setRole("customer")}/>
        <KpiHero l="Fornecedores" v={kpis.supplier} s="abrir tela ›" onClick={() => setRole("supplier")}/>
        <KpiHero l="Funcionários" v={kpis.employee} s="abrir tela ›" onClick={() => setRole("employee")}/>
        <KpiHero l="Representantes" v={kpis.rep} s="abrir tela ›" onClick={() => setRole("representative")} dark/>
      </div>

      <div className="cli-all-hint">
        <I.info size={12}/>
        <span>Esta é uma <strong>visão consolidada</strong> com campos comuns. Pra ver KPIs e colunas específicas de cada tipo, abra a aba dedicada.</span>
      </div>

      <Toolbar searchRef={searchRef} q={q} setQ={setQ}
        placeholder="Buscar em todas as pessoas…"
        filtersCount={fKind !== "all" ? 1 : 0} onClear={() => setFKind("all")}
        resultCount={filtered.length}>
        <FilterDropdown label="Tipo" value={fKind} onChange={setFKind} options={[
          { id: "all", label: "Todos" },
          { id: "customer", label: "Clientes", count: kpis.customer },
          { id: "supplier", label: "Fornecedores", count: kpis.supplier },
          { id: "employee", label: "Funcionários", count: kpis.employee },
          { id: "representative", label: "Representantes", count: kpis.rep },
        ]}/>
      </Toolbar>

      <div className="os-table-wrap">
        <table className="os-table cli-table cli-table-v2">
          <thead><tr>
            <th>Nome</th><th>Tipo</th><th>Documento</th>
            <th>Contato / Cargo / Categoria</th><th>Detalhe</th>
            <th></th><th></th>
          </tr></thead>
          <tbody>
            {filtered.map((p) => (
              <tr key={p.id} className="cli-row" onClick={() => setRole(p.kind)}>
                <td className="cli-td-cli">
                  <div className="cli-name">
                    <Avatar name={p.name}/>
                    <div className="cli-name-block">
                      <div className="cli-name-text">{p.name}</div>
                    </div>
                  </div>
                </td>
                <td><span className={`cli-kind cli-kind-${p.kind}`}>{KIND_LABEL[p.kind]}</span></td>
                <td><span className="cli-doc-mono">{p.doc}</span></td>
                <td>{p.contact}</td>
                <td className="cli-cell-muted">{p.sub}</td>
                <td className="cli-td-fav"><FavStar id={p.id} favs={favs} toggle={toggleFav}/></td>
                <td className="cli-td-kebab"><RowKebab items={[
                  { label:"Abrir na aba dedicada", icon: I.pencil, action: () => setRole(p.kind) },
                ]}/></td>
              </tr>
            ))}
            {filtered.length === 0 && <tr><td colSpan={7} className="os-empty">Nenhum resultado.</td></tr>}
          </tbody>
        </table>
      </div>
    </>
  );
}

// ════════════════════════════════════════════════════════════════════
// KPI Hero card + Toolbar (compartilhados)
// ════════════════════════════════════════════════════════════════════
function KpiHero({ l, v, s, aside, dark, tone, onClick }) {
  const Tag = onClick ? "button" : "div";
  return (
    <Tag className={`cli-kpihero-card ${dark ? "cli-kpihero-dark" : ""} ${tone ? `cli-kpihero-tone-${tone}` : ""} ${onClick ? "cli-kpihero-clickable" : ""}`}
      onClick={onClick}>
      <div className="cli-kpihero-l">{l}</div>
      <div className="cli-kpihero-v">
        {v}
        {aside && <span className="cli-kpihero-v-aside">{aside}</span>}
      </div>
      <div className="cli-kpihero-s">{s}</div>
    </Tag>
  );
}

function Toolbar({ searchRef, q, setQ, placeholder, filtersCount, onClear, resultCount, children }) {
  return (
    <div className="cli-toolbar-v2">
      <div className="cli-toolbar-search-v2">
        <I.search size={13}/>
        <input ref={searchRef} placeholder={placeholder} value={q} onChange={(e) => setQ(e.target.value)}/>
        <span className="cli-toolbar-hint"><kbd>/</kbd> pra focar · <kbd>⌘K</kbd> global</span>
      </div>
      <div className="cli-fdrop-row">
        {children}
        {filtersCount > 0 && <button className="cli-fdrop-clear" onClick={onClear}>Limpar ({filtersCount})</button>}
        <div className="cli-toolbar-count">{resultCount} registros</div>
      </div>
    </div>
  );
}

window.CliListPage = CliListPage;
