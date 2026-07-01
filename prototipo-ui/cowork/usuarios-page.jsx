// usuarios-page.jsx — Lista de usuários (gerenciar acessos do ERP). Cockpit V2.
// Redesign do datatable legado UltimatePOS (ManageUserController index):
// colunas Usuário · Nome · Função (role) · E-mail · Ações + status/último acesso.
// Reusa classes compartilhadas os-* e cli-* do shell. Expõe window.UsuariosPage.
(() => {
const { useState, useRef, useMemo } = React;

// ── Mock fiel ao contexto WR2 / ROTA LIVRE ──
const USERS = [
  { id:1, username:"wagner",   name:"Wagner Rocha",     email:"wagner@wr2.com.br",      role:"Administrador", status:"active",  last:"agora", you:true },
  { id:2, username:"larissa",  name:"Larissa Souza",    email:"larissa@rotalivre.com",  role:"Atendente",     status:"active",  last:"há 5 min" },
  { id:3, username:"eliana",   name:"Eliana Martins",   email:"eliana@wr2.com.br",      role:"Financeiro",    status:"active",  last:"há 1 h" },
  { id:4, username:"rafael",   name:"Rafael Lima",      email:"rafael@rotalivre.com",   role:"Produção",      status:"active",  last:"ontem" },
  { id:5, username:"joana",    name:"Joana Lima",       email:"joana@rotalivre.com",    role:"Atendente",     status:"active",  last:"há 2 dias" },
  { id:6, username:"marcos",   name:"Marcos Antunes",   email:"marcos@wr2.com.br",      role:"Produção",      status:"inactive", last:"há 3 semanas" },
  { id:7, username:"patricia", name:"Patrícia Gomes",   email:"patricia@wr2.com.br",    role:"Vendas",        status:"active",  last:"há 30 min" },
  { id:8, username:"bruno",    name:"Bruno Carvalho",   email:"bruno@rotalivre.com",    role:"Vendas",        status:"active",  last:"há 4 h" },
  { id:9, username:"sandra",   name:"Sandra Reis",      email:"sandra@wr2.com.br",      role:"Financeiro",    status:"inactive", last:"há 2 meses" },
];

// Cor da função (badge) — tons dentro da paleta de tokens
const ROLE_TONE = {
  "Administrador": { bg:"oklch(0.94 0.04 280)", fg:"oklch(0.42 0.16 280)", bd:"oklch(0.85 0.06 280)" },
  "Financeiro":    { bg:"oklch(0.94 0.05 150)", fg:"oklch(0.40 0.13 150)", bd:"oklch(0.84 0.07 150)" },
  "Atendente":     { bg:"oklch(0.94 0.05 230)", fg:"oklch(0.42 0.14 230)", bd:"oklch(0.84 0.06 230)" },
  "Produção":      { bg:"oklch(0.95 0.05 70)",  fg:"oklch(0.44 0.12 70)",  bd:"oklch(0.86 0.07 70)" },
  "Vendas":        { bg:"oklch(0.94 0.05 25)",  fg:"oklch(0.46 0.15 25)",  bd:"oklch(0.85 0.07 25)" },
};

function initials(n){ return n.split(/\s+/).slice(0,2).map(w=>w[0]).join("").toUpperCase(); }
function avColor(n){ const h=[...n].reduce((a,c)=>a+c.charCodeAt(0),0)%360; return { bg:`oklch(0.92 0.04 ${h})`, fg:`oklch(0.42 0.13 ${h})` }; }

// Kebab
function Kebab({ items }) {
  const [open, setOpen] = useState(false);
  const ref = useRef(null);
  React.useEffect(() => {
    if (!open) return;
    const h = (e) => { if (ref.current && !ref.current.contains(e.target)) setOpen(false); };
    document.addEventListener("mousedown", h);
    return () => document.removeEventListener("mousedown", h);
  }, [open]);
  return (
    <div className="cli-kebab-wrap" ref={ref}>
      <button className="cli-kebab-btn" onClick={(e) => { e.stopPropagation(); setOpen(!open); }} aria-expanded={open} title="Mais ações">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><circle cx="12" cy="5" r="1.6"/><circle cx="12" cy="12" r="1.6"/><circle cx="12" cy="19" r="1.6"/></svg>
      </button>
      {open && (
        <div className="cli-kebab-menu" onClick={(e) => e.stopPropagation()}>
          {items.map((it, i) => it.sep
            ? <div key={i} className="cli-kebab-sep"></div>
            : <button key={i} className={it.danger ? "danger" : ""} onClick={() => { setOpen(false); it.action?.(); }}>{it.label}</button>)}
        </div>
      )}
    </div>
  );
}

// FilterDropdown (mesmo visual do Clientes)
function FilterDropdown({ label, value, options, onChange }) {
  const [open, setOpen] = useState(false);
  const ref = useRef(null);
  React.useEffect(() => {
    if (!open) return;
    const h = (e) => { if (ref.current && !ref.current.contains(e.target)) setOpen(false); };
    document.addEventListener("mousedown", h);
    return () => document.removeEventListener("mousedown", h);
  }, [open]);
  const cur = options.find((o) => o.id === value);
  const active = value && value !== "all";
  return (
    <div className="cli-fdrop-wrap" ref={ref}>
      <button className={`cli-fdrop-btn ${active ? "active" : ""}`} onClick={() => setOpen(!open)} aria-expanded={open}>
        <span className="cli-fdrop-l">{label}</span>
        {active && cur && <span className="cli-fdrop-v">{cur.label}</span>}
        <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.4" strokeLinecap="round" strokeLinejoin="round"><path d="m6 9 6 6 6-6"/></svg>
      </button>
      {open && (
        <div className="cli-fdrop-menu">
          {options.map((o) => (
            <button key={o.id} className={value === o.id ? "active" : ""} onClick={() => { onChange(o.id); setOpen(false); }}>
              {o.label}{o.count != null && <span className="cli-fdrop-n">{o.count}</span>}
            </button>
          ))}
        </div>
      )}
    </div>
  );
}

function UsuariosPage() {
  const [q, setQ] = useState("");
  const [fRole, setFRole] = useState("all");
  const [fStatus, setFStatus] = useState("all");
  const searchRef = useRef(null);

  const roleList = useMemo(() => {
    const m = {};
    USERS.forEach((u) => { m[u.role] = (m[u.role] || 0) + 1; });
    return Object.entries(m);
  }, []);

  const filtered = USERS.filter((u) => {
    if (fRole !== "all" && u.role !== fRole) return false;
    if (fStatus !== "all" && u.status !== fStatus) return false;
    if (q) {
      const s = q.toLowerCase();
      if (![u.name, u.username, u.email, u.role].some((v) => v.toLowerCase().includes(s))) return false;
    }
    return true;
  });

  const kpis = {
    total: USERS.length,
    active: USERS.filter((u) => u.status === "active").length,
    inactive: USERS.filter((u) => u.status === "inactive").length,
    roles: roleList.length,
  };
  const activeF = [fRole, fStatus].filter((v) => v && v !== "all").length;

  return (
    <div className="os-page usr-page" data-screen-label="Usuários · Lista">
      <header className="os-page-h">
        <div className="os-page-h-l">
          <h1>Usuários</h1>
          <p>{kpis.total} usuários · {kpis.active} ativos · {kpis.roles} funções</p>
        </div>
        <div className="os-page-h-r">
          <button className="os-btn ghost"
            onClick={() => window.__selectRoute?.("perfil")}>Meu perfil</button>
          <button className="os-btn primary">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.2" strokeLinecap="round"><path d="M12 5v14M5 12h14"/></svg>
            Novo usuário
          </button>
        </div>
      </header>

      <div className="usr-kpis">
        <div className="usr-kpi"><span className="usr-kpi-v">{kpis.total}</span><span className="usr-kpi-l">Total de usuários</span></div>
        <div className="usr-kpi"><span className="usr-kpi-v">{kpis.active}</span><span className="usr-kpi-l">Ativos</span></div>
        <div className="usr-kpi"><span className="usr-kpi-v">{kpis.inactive}</span><span className="usr-kpi-l">Inativos</span></div>
        <div className="usr-kpi"><span className="usr-kpi-v">{kpis.roles}</span><span className="usr-kpi-l">Funções</span></div>
      </div>

      <div className="usr-toolbar">
        <div className="usr-search">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
          <input ref={searchRef} value={q} onChange={(e) => setQ(e.target.value)} placeholder="Buscar nome, usuário, e-mail ou função…" />
        </div>
        <div className="usr-filters">
          <FilterDropdown label="Função" value={fRole} onChange={setFRole} options={[
            { id:"all", label:"Todas" }, ...roleList.map(([r, n]) => ({ id: r, label: r, count: n })),
          ]}/>
          <FilterDropdown label="Status" value={fStatus} onChange={setFStatus} options={[
            { id:"all", label:"Todos" }, { id:"active", label:"Ativos", count: kpis.active }, { id:"inactive", label:"Inativos", count: kpis.inactive },
          ]}/>
          {activeF > 0 && <button className="usr-clear" onClick={() => { setFRole("all"); setFStatus("all"); }}>Limpar</button>}
          <span className="usr-count">{filtered.length} de {USERS.length}</span>
        </div>
      </div>

      <div className="os-table-wrap">
        <table className="os-table usr-table">
          <thead><tr>
            <th>Usuário</th><th>Função</th><th>E-mail</th><th>Status</th><th>Último acesso</th><th className="usr-th-act"></th>
          </tr></thead>
          <tbody>
            {filtered.map((u) => {
              const c = avColor(u.name);
              const tone = ROLE_TONE[u.role] || ROLE_TONE["Atendente"];
              return (
                <tr key={u.id}>
                  <td>
                    <div className="usr-id">
                      <div className="usr-avatar" style={{ background: c.bg, color: c.fg }}>{initials(u.name)}</div>
                      <div className="usr-id-meta">
                        <b>{u.name}{u.you && <span className="usr-you">você</span>}</b>
                        <small>@{u.username}</small>
                      </div>
                    </div>
                  </td>
                  <td>
                    <span className="usr-role" style={{ background: tone.bg, color: tone.fg, borderColor: tone.bd }}>{u.role}</span>
                  </td>
                  <td><span className="usr-email">{u.email}</span></td>
                  <td>
                    <span className={`usr-status ${u.status}`}>
                      <span className="usr-status-dot"></span>{u.status === "active" ? "Ativo" : "Inativo"}
                    </span>
                  </td>
                  <td><span className="usr-last">{u.last}</span></td>
                  <td className="usr-td-act">
                    <Kebab items={[
                      { label: "Editar", action: () => {} },
                      { label: "Ver detalhes", action: () => {} },
                      { label: "Redefinir senha", action: () => {} },
                      { sep: true },
                      { label: u.status === "active" ? "Desativar" : "Ativar", action: () => {} },
                      { label: "Excluir", danger: true, action: () => {} },
                    ]}/>
                  </td>
                </tr>
              );
            })}
          </tbody>
        </table>
        {filtered.length === 0 && <div className="usr-empty">Nenhum usuário encontrado.</div>}
      </div>
    </div>
  );
}

window.UsuariosPage = UsuariosPage;
})();
