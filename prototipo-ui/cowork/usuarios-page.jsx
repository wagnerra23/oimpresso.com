// usuarios-page.jsx — Lista de usuários (gerenciar acessos do ERP). Cockpit V2.
// Redesign do datatable legado UltimatePOS (ManageUserController index):
// colunas Usuário · Nome · Função (role) · E-mail · Ações + status/último acesso.
// Reusa classes compartilhadas os-* e cli-* do shell. Expõe window.UsuariosPage.
(() => {
const { useState, useRef, useMemo } = React;
const { Kpis, Kpi, Vazio, Confirm } = window.AcessosDS;

// ── Mock fiel ao contexto WR2 / ROTA LIVRE ──
const USERS = [
  { id:1, username:"wagner",   name:"Wagner Rocha",     email:"wagner@wr2.com.br",      role:"Administrador", status:"active",  last:"agora", you:true, criado:"12/03/2024", locais:"Todos", vendas:0,  os:0,  mfa:true },
  { id:2, username:"larissa",  name:"Larissa Souza",    email:"larissa@rotalivre.com",  role:"Atendente",     status:"active",  last:"há 5 min", criado:"04/07/2025", locais:"ROTA LIVRE — Balcão", vendas:184, os:96, mfa:false, comissionado:true },
  { id:3, username:"eliana",   name:"Eliana Martins",   email:"eliana@wr2.com.br",      role:"Financeiro",    status:"active",  last:"há 1 h", criado:"19/09/2024", locais:"Todos", vendas:0, os:0, mfa:true },
  { id:4, username:"rafael",   name:"Rafael Lima",      email:"rafael@rotalivre.com",   role:"Produção",      status:"active",  last:"ontem", criado:"11/01/2025", locais:"ROTA LIVRE — Produção", vendas:0, os:212, mfa:false },
  { id:5, username:"joana",    name:"Joana Lima",       email:"joana@rotalivre.com",    role:"Atendente",     status:"active",  last:"há 2 dias", criado:"02/02/2026", locais:"ROTA LIVRE — Balcão", vendas:63, os:31, mfa:false, comissionado:true },
  { id:6, username:"marcos",   name:"Marcos Antunes",   email:"marcos@wr2.com.br",      role:"Produção",      status:"inactive", last:"há 3 semanas", criado:"08/05/2025", locais:"ROTA LIVRE — Produção", vendas:0, os:8, mfa:false },
  { id:7, username:"patricia", name:"Patrícia Gomes",   email:"patricia@wr2.com.br",    role:"Vendas",        status:"active",  last:"há 30 min", criado:"23/06/2025", locais:"Todos", vendas:47, os:0, mfa:false, comissionado:true },
  { id:8, username:"bruno",    name:"Bruno Carvalho",   email:"bruno@rotalivre.com",    role:"Vendas",        status:"active",  last:"há 4 h", criado:"14/10/2025", locais:"Todos", vendas:29, os:0, mfa:false, comissionado:true },
  { id:9, username:"sandra",   name:"Sandra Reis",      email:"sandra@wr2.com.br",      role:"Financeiro",    status:"inactive", last:"há 2 meses", criado:"30/11/2024", locais:"Todos", vendas:0, os:0, mfa:false },
];

const ATIVIDADE = {
  2: ["Fechou a OS 2318 · há 12 min", "Criou o orçamento ORC-1188 · há 40 min", "Abriu o caixa do balcão · 08:04"],
  3: ["Baixou 4 títulos a receber · há 1 h", "Exportou o DRE de julho · ontem"],
  4: ["Apontou 3 h na OP 884 · ontem", "Mudou a OS 2301 para acabamento · ontem"],
};

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

// ── Drawer de detalhe (PT-02) ──
function UserDrawer({ u, onClose, onExcluir }) {
  const c = avColor(u.name);
  const tone = ROLE_TONE[u.role] || ROLE_TONE["Atendente"];
  const [reset, setReset] = useState(false);
  const acts = ATIVIDADE[u.id];
  return (
    <>
      <div className="os-drawer-back" onClick={onClose}></div>
      <div className="os-drawer usr-drawer">
        <div className="os-drawer-head">
          <div className="os-drawer-head-l">
            <div className="os-drawer-id">Usuário</div>
            <h2>{u.name}</h2>
            <p>@{u.username} · {u.email}</p>
          </div>
          <div className="os-drawer-head-r">
            <div className="usr-avatar" style={{ background:c.bg, color:c.fg }}>{initials(u.name)}</div>
            <button className="os-btn ghost" onClick={onClose}>Fechar</button>
          </div>
        </div>

        <div className="os-drawer-body">
          <div className="os-drawer-section">
            <h3>Acesso</h3>
            <div className="usr-dr-grid">
              <div><small>Função</small><b><span className="usr-role" style={{ background:tone.bg, color:tone.fg, borderColor:tone.bd }}>{u.role}</span></b></div>
              <div><small>Situação</small><b><span className={`usr-status ${u.status}`}><span className="usr-status-dot"></span>{u.status === "active" ? "Ativo" : "Inativo"}</span></b></div>
              <div><small>Último acesso</small><b>{u.last}</b></div>
              <div><small>No sistema desde</small><b>{u.criado}</b></div>
              <div><small>Locais</small><b>{u.locais}</b></div>
              <div><small>Verificação em 2 etapas</small><b className={u.mfa ? "" : "usr-dr-warn"}>{u.mfa ? "Ativa" : "Desligada"}</b></div>
            </div>
            <div className="usr-dr-links">
              <button className="os-btn sm" onClick={() => window.__selectRoute?.("funcoes")}>Ver permissões da função</button>
              {u.comissionado && <button className="os-btn sm" onClick={() => window.__selectRoute?.("comissoes")}>Comissão dele</button>}
            </div>
          </div>

          {acts && (
            <div className="os-drawer-section">
              <h3>Atividade recente</h3>
              <ul className="usr-dr-acts">{acts.map((a, i) => <li key={i}>{a}</li>)}</ul>
            </div>
          )}

          <div className="os-drawer-section">
            <h3>O que está no nome dele</h3>
            <div className="usr-dr-vinc">
              <span>{u.vendas} {u.vendas === 1 ? "venda" : "vendas"}</span>
              <span>{u.os} {u.os === 1 ? "ordem de serviço" : "ordens de serviço"}</span>
              {u.comissionado && <span>cadastro de comissionado</span>}
            </div>
            <p className="usr-dr-nota">
              {u.vendas + u.os > 0
                ? "Por isso este usuário não pode ser excluído — o histórico ficaria órfão. Desativar tira o acesso e preserva o rastro."
                : "Sem movimento no nome dele: pode ser excluído sem deixar histórico órfão."}
            </p>
          </div>

          <div className="os-drawer-section">
            <h3>Senha</h3>
            {reset ? (
              <div className="usr-dr-reset ok">Link de redefinição enviado para {u.email}. Vale 60 minutos.</div>
            ) : (
              <div className="usr-dr-reset">
                <p>O sistema envia um link de redefinição — ninguém, nem o administrador, vê a senha.</p>
                <button className="os-btn sm" onClick={() => setReset(true)}>Enviar link de redefinição</button>
              </div>
            )}
          </div>
        </div>

        <div className="os-drawer-actions">
          <button className="os-btn ghost danger" onClick={() => onExcluir(u)}>Excluir</button>
          <span className="usr-dr-spacer"></span>
          <button className="os-btn">{u.status === "active" ? "Desativar" : "Ativar"}</button>
          <button className="os-btn primary">Editar usuário</button>
        </div>
      </div>
    </>
  );
}

// ── Convite ──
function ConviteDrawer({ onClose }) {
  const [f, setF] = useState({ nome:"", email:"", role:"Atendente", local:"ROTA LIVRE — Balcão" });
  const [enviado, setEnviado] = useState(false);
  const set = (k) => (e) => setF((s) => ({ ...s, [k]: e.target.value }));
  return (
    <>
      <div className="os-drawer-back" onClick={onClose}></div>
      <div className="os-drawer usr-drawer">
        <div className="os-drawer-head">
          <div className="os-drawer-head-l">
            <div className="os-drawer-id">Novo usuário</div>
            <h2>{enviado ? "Convite enviado" : "Convidar por e-mail"}</h2>
            <p>{enviado ? "Ele define a própria senha no primeiro acesso." : "Sem senha provisória circulando por WhatsApp."}</p>
          </div>
          <div className="os-drawer-head-r"><button className="os-btn ghost" onClick={onClose}>Fechar</button></div>
        </div>
        <div className="os-drawer-body">
          {enviado ? (
            <div className="os-drawer-section">
              <div className="usr-dr-reset ok">
                Convite enviado para <b>{f.email}</b> como <b>{f.role}</b>. Vale 7 dias — até aceitar, ele aparece na lista como “convite pendente”.
              </div>
            </div>
          ) : (
            <div className="os-drawer-section">
              <h3>Dados</h3>
              <div className="cms-form">
                <div className="cms-f-row two">
                  <div className="cms-field"><label>Nome<i> *</i></label><input value={f.nome} onChange={set("nome")} /></div>
                  <div className="cms-field"><label>E-mail<i> *</i></label><input value={f.email} onChange={set("email")} placeholder="nome@empresa.com.br" /></div>
                </div>
                <div className="cms-f-row two">
                  <div className="cms-field">
                    <label>Função</label>
                    <select value={f.role} onChange={set("role")}>
                      {Object.keys(ROLE_TONE).map((r) => <option key={r} value={r}>{r}</option>)}
                    </select>
                    <small>Define o que ele pode fazer. Dá pra trocar depois.</small>
                  </div>
                  <div className="cms-field">
                    <label>Local</label>
                    <select value={f.local} onChange={set("local")}>
                      <option>ROTA LIVRE — Balcão</option>
                      <option>ROTA LIVRE — Produção</option>
                      <option>Todos</option>
                    </select>
                  </div>
                </div>
              </div>
            </div>
          )}
        </div>
        <div className="os-drawer-actions">
          <button className="os-btn ghost" onClick={onClose}>{enviado ? "Fechar" : "Cancelar"}</button>
          {!enviado && <button className="os-btn primary" disabled={!f.nome || !f.email} onClick={() => setEnviado(true)}>Enviar convite</button>}
        </div>
      </div>
    </>
  );
}

// ── Exclusão com guarda ──
function ExcluirModal({ u, onClose }) {
  const bloqueado = u.you || u.vendas + u.os > 0;
  const [txt, setTxt] = useState("");
  return (
    <Confirm open onClose={onClose}
      title={bloqueado ? "Este usuário não pode ser excluído" : "Excluir " + u.name + "?"}
      cta={bloqueado ? null : "Excluir definitivamente"} ctaDisabled={txt !== u.username}
      ctaAlt={bloqueado && !u.you ? <button className="os-btn primary" onClick={onClose}>Desativar em vez de excluir</button> : null}>
      {u.you ? (
        <p>É a sua própria conta. Peça a outro administrador.</p>
      ) : bloqueado ? (
        <>
          <p>
            Tem <b>{u.vendas} {u.vendas === 1 ? "venda" : "vendas"}</b> e <b>{u.os} {u.os === 1 ? "OS" : "OS"}</b> no nome dele.
            Excluir deixaria esse histórico sem responsável — e relatório, comissão e auditoria passariam a mentir.
          </p>
          <p className="usr-modal-alt">Desativar tira o acesso na hora e preserva tudo. É o que você quer em 99% dos casos.</p>
        </>
      ) : (
        <>
          <p>Não há venda, OS ou lançamento no nome dele — a exclusão não deixa histórico órfão. A ação não tem volta.</p>
          <p className="usr-modal-alt">Para confirmar, digite <b>{u.username}</b>.</p>
          <input className="usr-modal-input" value={txt} onChange={(e) => setTxt(e.target.value)} placeholder={u.username} />
        </>
      )}
    </Confirm>
  );
}

function UsuariosPage() {
  const [q, setQ] = useState("");
  const [drawer, setDrawer] = useState(null);
  const [convite, setConvite] = useState(false);
  const [excluir, setExcluir] = useState(null);
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
            onClick={() => window.__selectRoute?.("funcoes")}>Funções</button>
          <button className="os-btn primary" onClick={() => setConvite(true)}>
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.2" strokeLinecap="round"><path d="M12 5v14M5 12h14"/></svg>
            Novo usuário
          </button>
        </div>
      </header>

      <Kpis>
        <Kpi v={kpis.total} l="Total de usuários" />
        <Kpi v={kpis.active} l="Ativos" tone="success" />
        <Kpi v={kpis.inactive} l="Inativos" />
        <Kpi v={kpis.roles} l="Funções" />
      </Kpis>

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
                <tr key={u.id} className="os-row" onClick={() => setDrawer(u)}>
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
                      { label: "Ver detalhes", action: () => setDrawer(u) },
                      { label: "Editar", action: () => setDrawer(u) },
                      { label: "Enviar link de redefinição", action: () => setDrawer(u) },
                      { sep: true },
                      { label: u.status === "active" ? "Desativar" : "Ativar", action: () => {} },
                      { label: "Excluir", danger: true, action: () => setExcluir(u) },
                    ]}/>
                  </td>
                </tr>
              );
            })}
          </tbody>
        </table>
        {filtered.length === 0 && <Vazio title="Nenhum usuário encontrado." description="Ajuste a busca ou os filtros de função e situação." />}
      </div>
      {drawer && <UserDrawer u={drawer} onClose={() => setDrawer(null)} onExcluir={(u) => { setDrawer(null); setExcluir(u); }} />}
      {convite && <ConviteDrawer onClose={() => setConvite(false)} />}
      {excluir && <ExcluirModal u={excluir} onClose={() => setExcluir(null)} />}
    </div>
  );
}

window.UsuariosPage = UsuariosPage;
})();
