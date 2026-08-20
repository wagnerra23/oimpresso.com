// modulos-page.jsx — /modulos · Gerenciador de Módulos (superadmin, app-wide).
// Espelha resources/js/Pages/Modules/Index.tsx (main @0b125f09bd96): header com
// contagens + 4 StatCards + FilterDropdown Área/Status + chips ativos + busca
// debounced + tabela (ícone de área · módulo/alias · área · status · migrations
// · switch ativo · kebab Instalar/Desativar).
// Dados: Modules/ + modules_statuses.json do main (32 módulos, todos ativos).
// Expõe window.ModulosPage.
(() => {
const { useState, useRef, useMemo, useEffect } = React;
const I = window.I;

// ── Fonte: Modules/*/module.json + modules_statuses.json (main) ──
// area = ModuleManagerService::guessArea() (keyword no nome, fallback "Outros")
// version: module.json não declara "version" → fallback "0.0" do Service
const MODULES = [
  { name:"Arquivos", alias:"arquivos", area:"Outros", mig:6, desc:"DMS backbone — todo arquivo anexado da empresa cai aqui (multi-tenant Tier 0). Polimorfismo morph + storage abstraído. ADR 0123." },
  { name:"AssetManagement", alias:"assetmanagement", area:"Operações", mig:3, desc:"Useful for managing all kinds of assets." },
  { name:"Auditoria", alias:"auditoria", area:"Outros", mig:2, desc:"Camada de governança transversal — UI /auditoria + undo sobre activity_log. Distingue User vs IA. ADR 0127." },
  { name:"Cms", alias:"cms", area:"Conteúdo", mig:5, desc:"Mini CMS para gerenciar conteúdo do frontend — landing, blog, contato e demais páginas." },
  { name:"Compras", alias:"compras", area:"Outros", mig:9, desc:"Compras como cockpit operacional: FSM 6 estágios, import XML DF-e, entrada matricial tam×cor." },
  { name:"ComunicacaoVisual", alias:"comunicacao-visual", area:"Outros", mig:11, desc:"Vertical gráfica rápida e comunicação visual BR (CNAE 1813-0/01). Cálculo m² + PCP gráfico + apontamento." },
  { name:"Connector", alias:"connector", area:"Integrações", mig:2, desc:"Provide the API's for POS." },
  { name:"ConsultaOs", alias:"consultaos", area:"Outros", mig:1, desc:"Portal público de consulta de OS — cliente acompanha o pipeline de produção sem login." },
  { name:"Crm", alias:"crm", area:"Comercial", mig:8, desc:"Crm Module." },
  { name:"Essentials", alias:"essentials", area:"Recursos Humanos", mig:24, desc:"Essentials + HRM: documentos, tarefas, feriados, mensagens, base de conhecimento." },
  { name:"Financeiro", alias:"financeiro", area:"Outros", mig:18, desc:"Contas a pagar, a receber, fluxo de caixa e DRE — tudo na mesma tela." },
  { name:"Fiscal", alias:"fiscal", area:"Financeiro", mig:4, desc:"Cockpit fiscal unificado — NF-e/NFC-e + NFS-e + manifesto DF-e + eventos + SPED numa visão só." },
  { name:"Forja", alias:"projectmgmt", area:"Outros", mig:14, desc:"Cockpit de trabalho do time: Kanban + Backlog + Roadmap + My Work + Inbox + Triage. ADR 0070." },
  { name:"Governance", alias:"governance", area:"Outros", mig:7, desc:"Governança consolidada — ActionGate runtime, audit dashboard, ADRs, policies, drift alerts." },
  { name:"Jana", alias:"jana", area:"IA", mig:12, desc:"Chat IA conversacional do business. Conversa, sugere metas, monitora execução." },
  { name:"KB", alias:"kb", area:"Outros", mig:5, desc:"Knowledge Base — ADRs, sessions, runbooks e comparativos sincronizados do git." },
  { name:"Manufacturing", alias:"manufacturing", area:"Operações", mig:4, desc:"Used for businesses where products needs to be manufactured." },
  { name:"NFSe", alias:"nfse", area:"Outros", mig:3, desc:"NFS-e via Sistema Nacional (LC 214/2025) — webservice federal direto, custo zero por emissão." },
  { name:"NfeBrasil", alias:"nfebrasil", area:"Financeiro", mig:10, desc:"Vender com nota fiscal sem virar contador. NFC-e em 1 clique, NF-e B2B sem fricção, SPED pronto." },
  { name:"Officeimpresso", alias:"officeimpresso", area:"Office Impresso", mig:6, desc:"Sistema Office Impresso desktop — licenciamento." },
  { name:"OficinaAuto", alias:"oficina-auto", area:"Outros", mig:5, desc:"Vertical oficinas automotivas BR. CRUD Vehicle + ServiceOrder multi-tenant Tier 0. ADR 0137." },
  { name:"PaymentGateway", alias:"paymentgateway", area:"Outros", mig:8, desc:"Camada técnica de cobrança BR — drivers Inter/C6/Asaas/Pix Automático BCB, webhooks, CNAB." },
  { name:"Ponto", alias:"ponto", area:"Recursos Humanos", mig:16, desc:"Ponto eletrônico conforme Portaria MTP 671/2021 — WR2 Sistemas." },
  { name:"ProductCatalogue", alias:"productcatalogue", area:"Catálogo", mig:2, desc:"Catalogue & Menu module." },
  { name:"RecurringBilling", alias:"recurringbilling", area:"Outros", mig:9, desc:"Cobrança recorrente brasileira: assinaturas, Pix Automático, smart retries, régua de inadimplência." },
  { name:"Repair", alias:"repair", area:"Operações", mig:6, desc:"Useful for all kind of repair shops." },
  { name:"Spreadsheet", alias:"spreadsheet", area:"Integrações", mig:2, desc:"Allows you to create spreadsheet and share with employees, roles & todos." },
  { name:"Superadmin", alias:"superadmin", area:"Administração", mig:5, desc:"Allows you to create packages & sell subscription to multiple businesses." },
  { name:"Vestuario", alias:"vestuario", area:"Outros", mig:4, desc:"Vertical lojas de vestuário/moda BR (CNAE 4781-4/00). Piloto ROTA LIVRE em produção." },
  { name:"VozDoCliente", alias:"vozdocliente", area:"Outros", mig:3, desc:"Canal de sinal dentro do sistema — a dor relatada na tela onde acontece, triada até virar melhoria." },
  { name:"Whatsapp", alias:"whatsapp", area:"Outros", mig:21, desc:"WhatsApp transacional via Z-API + Meta Cloud (fallback). Status OS, boleto/NFe, dunning, bot Jana com HITL." },
  { name:"Woocommerce", alias:"woocommerce", area:"Comercial", mig:3, desc:"Allows you to connect POS with WooCommerce website." },
].map((m) => ({ ...m, version:"0.0", active:true, registered:true, error:null }))
 // usort do ModuleManagerService::list(): ativos primeiro, depois área, depois nome
 .sort((a, b) => (a.active !== b.active ? (a.active ? -1 : 1) : (a.area !== b.area ? a.area.localeCompare(b.area) : a.name.localeCompare(b.name))));

const AREA_ICON = {
  "Recursos Humanos":"users", "Comercial":"product", "Operações":"factory",
  "Financeiro":"cash", "IA":"bot", "Comunicação":"message", "Integrações":"plug",
  "Catálogo":"book", "Administração":"shield", "Conteúdo":"list",
  "Office Impresso":"layers", "Outros":"grid",
};

const STATUS_LABEL = { active:"Ativo", inactive:"Inativo", errored:"Com erro", unregistered:"Não registrado" };
const rowStatus = (m) => m.error ? "errored" : (!m.registered ? "unregistered" : (m.active ? "active" : "inactive"));

// ── FilterDropdown (mesmo visual do Clientes/Usuários) ──
function FilterDropdown({ label, value, options, onChange }) {
  const [open, setOpen] = useState(false);
  const ref = useRef(null);
  useEffect(() => {
    if (!open) return;
    const h = (e) => { if (ref.current && !ref.current.contains(e.target)) setOpen(false); };
    const k = (e) => { if (e.key === "Escape") setOpen(false); };
    document.addEventListener("mousedown", h); document.addEventListener("keydown", k);
    return () => { document.removeEventListener("mousedown", h); document.removeEventListener("keydown", k); };
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

function Kebab({ items }) {
  const [open, setOpen] = useState(false);
  const ref = useRef(null);
  useEffect(() => {
    if (!open) return;
    const h = (e) => { if (ref.current && !ref.current.contains(e.target)) setOpen(false); };
    document.addEventListener("mousedown", h);
    return () => document.removeEventListener("mousedown", h);
  }, [open]);
  return (
    <div className="cli-kebab-wrap" ref={ref}>
      <button className="cli-kebab-btn" onClick={(e) => { e.stopPropagation(); setOpen(!open); }} aria-expanded={open} title="Ações do módulo">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><circle cx="12" cy="5" r="1.6"/><circle cx="12" cy="12" r="1.6"/><circle cx="12" cy="19" r="1.6"/></svg>
      </button>
      {open && (
        <div className="cli-kebab-menu" onClick={(e) => e.stopPropagation()}>
          {items.map((it, i) => it.sep
            ? <div key={i} className="cli-kebab-sep"></div>
            : <button key={i} className={it.danger ? "danger" : ""} disabled={it.disabled} onClick={() => { setOpen(false); it.action?.(); }}>{it.label}</button>)}
        </div>
      )}
    </div>
  );
}

function Switch({ on, onToggle, label }) {
  return (
    <button type="button" role="switch" aria-checked={on} aria-label={label}
      className={`mod-switch ${on ? "on" : ""}`} onClick={onToggle}>
      <span className="mod-switch-knob"></span>
    </button>
  );
}

function StatCard({ label, value, icon, tone }) {
  const Ic = I[icon] || I.grid;
  return (
    <div className="mod-stat">
      <div className="mod-stat-t">
        <span className="mod-stat-l">{label}</span>
        <span className="mod-stat-v">{value}</span>
      </div>
      <div className={`mod-stat-ic ${tone}`}><Ic size={18}/></div>
    </div>
  );
}

// ── Drawer de detalhe (PT-02) ──
function ModuloDrawer({ m, onClose, onInstall, onToggle }) {
  const st = rowStatus(m);
  const Ic = I[AREA_ICON[m.area] || "grid"];
  return (
    <div className="os-drawer-back" onClick={onClose}>
      <aside className="os-drawer mod-drawer" onClick={(e) => e.stopPropagation()} role="dialog" aria-modal="true" aria-label={`Módulo ${m.name}`}>
        <header className="os-drawer-head">
          <div className="os-drawer-head-l">
            <div className="mod-drawer-id">
              <div className="mod-ic-tile lg"><Ic size={18}/></div>
              <div>
                <h2>{m.name}</h2>
                <p className="mod-meta">{m.alias} · v{m.version}</p>
              </div>
            </div>
          </div>
          <button className="icon-btn" onClick={onClose} title="Fechar (Esc)"><I.close size={15}/></button>
        </header>

        <div className="os-drawer-body">
          <div className="mod-drawer-badges">
            <span className={`mod-badge ${st}`}>{STATUS_LABEL[st]}</span>
            <span className="mod-area-pill">{m.area}</span>
          </div>

          <p className="mod-drawer-desc">{m.desc}</p>

          <dl className="mod-drawer-dl">
            <div><dt>Pasta</dt><dd className="mono">Modules/{m.name}</dd></div>
            <div><dt>Alias</dt><dd className="mono">{m.alias}</dd></div>
            <div><dt>Migrations</dt><dd className="mono">{m.mig}</dd></div>
            <div><dt>Registrado</dt><dd>{m.registered ? "sim · modules_statuses.json" : "não"}</dd></div>
          </dl>

          <div className="mod-drawer-note">
            Estado app-wide: ativar ou desativar vale para todos os negócios. Habilitar por negócio é compra de pacote no
            superadmin. Desativar preserva as tabelas do banco.
          </div>
        </div>

        <footer className="os-drawer-actions">
          {m.mig > 0 && (
            <button className="os-btn primary" onClick={onInstall}>{m.active ? "Reinstalar" : "Instalar"}</button>
          )}
          <button className="os-btn" onClick={onToggle}>{m.active ? "Desativar" : "Ativar"}</button>
          <div style={{ flex:1 }}></div>
          <button className="os-btn ghost" onClick={onClose}>Fechar</button>
        </footer>
      </aside>
    </div>
  );
}

function ModulosPage() {
  const [rows, setRows] = useState(MODULES);
  const [searchInput, setSearchInput] = useState("");
  const [search, setSearch] = useState("");
  const [fArea, setFArea] = useState("all");
  const [fStatus, setFStatus] = useState("all");
  const [sort, setSort] = useState({ col:"default", dir:"asc" });
  const [sel, setSel] = useState(null);
  const [toast, setToast] = useState(null);
  const searchRef = useRef(null);

  useEffect(() => {
    const t = setTimeout(() => setSearch(searchInput.trim().toLowerCase()), 300);
    return () => clearTimeout(t);
  }, [searchInput]);

  useEffect(() => {
    if (!toast) return;
    const t = setTimeout(() => setToast(null), 2600);
    return () => clearTimeout(t);
  }, [toast]);

  // Atalhos do shell: "/" foca a busca, Esc fecha o drawer ou limpa a busca
  useEffect(() => {
    const k = (e) => {
      const typing = /^(INPUT|TEXTAREA)$/.test(e.target.tagName);
      if (e.key === "/" && !typing) { e.preventDefault(); searchRef.current?.focus(); }
      if (e.key === "Escape") { if (sel) setSel(null); else if (searchInput) setSearchInput(""); }
    };
    document.addEventListener("keydown", k);
    return () => document.removeEventListener("keydown", k);
  }, [sel, searchInput]);

  const areas = useMemo(() => {
    const m = {};
    rows.forEach((r) => { m[r.area] = (m[r.area] || 0) + 1; });
    return Object.entries(m).sort((a, b) => a[0].localeCompare(b[0], "pt-BR"));
  }, [rows]);

  const counts = useMemo(() => ({
    total: rows.length,
    active: rows.filter((m) => m.active).length,
    inactive: rows.filter((m) => !m.active && !m.error).length,
    errored: rows.filter((m) => m.error !== null).length,
  }), [rows]);

  const filtered = rows.filter((m) => {
    if (fArea !== "all" && m.area !== fArea) return false;
    if (fStatus !== "all" && rowStatus(m) !== fStatus) return false;
    if (!search) return true;
    return [m.name, m.alias, m.desc, m.area].some((v) => v.toLowerCase().includes(search));
  });

  const sorted = useMemo(() => {
    if (sort.col === "default") return filtered;
    const s = sort.dir === "asc" ? 1 : -1;
    return filtered.slice().sort((a, b) => {
      if (sort.col === "mig") return (a.mig - b.mig) * s;
      if (sort.col === "area") return a.area.localeCompare(b.area, "pt-BR") * s || a.name.localeCompare(b.name);
      return a.name.localeCompare(b.name, "pt-BR") * s;
    });
  }, [filtered, sort]);

  const toggleSort = (col) => setSort((s) =>
    s.col !== col ? { col, dir:"asc" }
    : s.dir === "asc" ? { col, dir:"desc" }
    : { col:"default", dir:"asc" });
  const sortMark = (col) => sort.col === col ? (sort.dir === "asc" ? " ↑" : " ↓") : "";

  const setActive = (name, v) => {
    setRows((rs) => rs.map((r) => r.name === name ? { ...r, active: v } : r));
    setSel((s) => s && s.name === name ? { ...s, active: v } : s);
  };

  const toggle = (m) => {
    setActive(m.name, !m.active);
    setToast(`Módulo ${m.name} ${m.active ? "desativado" : "ativado"}.`);
  };
  const install = (m) => {
    if (!window.confirm(`Instalar ${m.name}?\nIsso vai rodar ${m.mig} migration(s) e ativar o módulo.`)) return;
    setActive(m.name, true);
    setToast(`Módulo ${m.name} instalado (migrations OK). Setup completo: permissões + plano de contas pré-populados.`);
  };
  const uninstall = (m) => {
    if (!window.confirm(`Desativar ${m.name}?\nAs tabelas do banco são PRESERVADAS (não são apagadas).`)) return;
    setActive(m.name, false);
    setToast(`Módulo ${m.name} desativado (tabelas preservadas).`);
  };

  const activeF = [fArea, fStatus].filter((v) => v && v !== "all").length;

  return (
    <div className="os-page mod-page" data-screen-label="Módulos · Gerenciador">
      <header className="os-page-h">
        <div className="os-page-h-l">
          <h1>Gerenciador de Módulos</h1>
          <p className="tabular">
            {counts.total} módulos · {counts.active} ativos · {counts.inactive} inativos
            {counts.errored > 0 && <span className="mod-err-count"> · {counts.errored} com erro</span>}
          </p>
        </div>
        <div className="os-page-h-r">
          <span className="mod-kbd-hint"><kbd>/</kbd> buscar</span>
          <span className="mod-scope">app-wide · cross-tenant</span>
        </div>
      </header>

      <div className="mod-stats">
        <StatCard label="Total" value={counts.total} icon="grid" tone="muted"/>
        <StatCard label="Ativos" value={counts.active} icon="check" tone="ok"/>
        <StatCard label="Inativos" value={counts.inactive} icon="close" tone="muted"/>
        <StatCard label="Com erro" value={counts.errored} icon="bell" tone="danger"/>
      </div>

      <div className="mod-toolbar">
        <div className="usr-search">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
          <input ref={searchRef} value={searchInput} onChange={(e) => setSearchInput(e.target.value)} placeholder="Buscar por nome, alias, descrição ou área…" aria-label="Buscar módulo"/>
          {searchInput && <button className="mod-search-x" onClick={() => setSearchInput("")} aria-label="Limpar busca">×</button>}
        </div>
        <div className="usr-filters">
          <FilterDropdown label="Área" value={fArea} onChange={setFArea} options={[
            { id:"all", label:"Todas" }, ...areas.map(([a, n]) => ({ id:a, label:a, count:n })),
          ]}/>
          <FilterDropdown label="Status" value={fStatus} onChange={setFStatus} options={[
            { id:"all", label:"Todos" },
            { id:"active", label:"Ativo", count:counts.active },
            { id:"inactive", label:"Inativo", count:counts.inactive },
            { id:"errored", label:"Com erro", count:counts.errored },
            { id:"unregistered", label:"Não registrado" },
          ]}/>
          {activeF > 0 && <button className="usr-clear" onClick={() => { setFArea("all"); setFStatus("all"); }}>Limpar tudo</button>}
          <span className="usr-count">{sorted.length === rows.length ? `${rows.length} módulos` : `${sorted.length} de ${rows.length} módulos`}</span>
        </div>
      </div>

      {activeF > 0 && (
        <div className="mod-chips">
          {fArea !== "all" && <span className="mod-chip"><b>Área:</b> {fArea}<button onClick={() => setFArea("all")} aria-label="Remover filtro Área">×</button></span>}
          {fStatus !== "all" && <span className="mod-chip"><b>Status:</b> {STATUS_LABEL[fStatus]}<button onClick={() => setFStatus("all")} aria-label="Remover filtro Status">×</button></span>}
        </div>
      )}

      <div className="os-table-wrap">
        <table className="os-table mod-table">
          <thead><tr>
            <th className="mod-th-ic"></th>
            <th><button className="mod-sort" onClick={() => toggleSort("name")}>Módulo{sortMark("name")}</button></th>
            <th className="mod-th-area"><button className="mod-sort" onClick={() => toggleSort("area")}>Área{sortMark("area")}</button></th>
            <th className="mod-th-st">Status</th>
            <th className="mod-th-mig"><button className="mod-sort" onClick={() => toggleSort("mig")}>Migrations{sortMark("mig")}</button></th>
            <th className="mod-th-sw">Ativo</th>
            <th className="mod-th-act"></th>
          </tr></thead>
          <tbody>
            {sorted.map((m) => {
              const st = rowStatus(m);
              const Ic = I[AREA_ICON[m.area] || "grid"];
              return (
                <tr key={m.name} className={`${m.active ? "" : "mod-row-off"} ${sel?.name === m.name ? "mod-row-sel" : ""}`}
                  onClick={() => setSel(m)}>
                  <td className="mod-td-ic"><div className="mod-ic-tile"><Ic size={16}/></div></td>
                  <td>
                    <div className="mod-name">{m.name}</div>
                    <div className="mod-meta">{m.alias} · v{m.version}</div>
                    <div className="mod-desc">{m.desc}</div>
                  </td>
                  <td><span className="mod-area">{m.area}</span></td>
                  <td><span className={`mod-badge ${st}`}>{STATUS_LABEL[st]}</span></td>
                  <td className="mod-td-mig tabular">
                    {m.mig > 0 ? (
                      <span className="mod-mig">
                        <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round"><ellipse cx="12" cy="6" rx="8" ry="3"/><path d="M4 6v6c0 1.7 3.6 3 8 3s8-1.3 8-3V6"/><path d="M4 12v6c0 1.7 3.6 3 8 3s8-1.3 8-3v-6"/></svg>
                        {m.mig}
                      </span>
                    ) : <span className="mod-mig-none">—</span>}
                  </td>
                  <td onClick={(e) => e.stopPropagation()}><Switch on={m.active} onToggle={() => toggle(m)} label={`Ativar/desativar ${m.name}`}/></td>
                  <td className="mod-td-act" onClick={(e) => e.stopPropagation()}>
                    <Kebab items={[
                      { label: "Ver detalhe", action: () => setSel(m) },
                      { sep: true },
                      ...(m.mig > 0 ? [{ label: m.active ? "Reinstalar" : "Instalar", action: () => install(m) }] : []),
                      ...(m.active ? [{ label: "Desativar", action: () => uninstall(m) }] : []),
                      ...(m.mig === 0 && !m.active ? [{ label: "Sem ações disponíveis", disabled: true }] : []),
                    ]}/>
                  </td>
                </tr>
              );
            })}
          </tbody>
        </table>
        {sorted.length === 0 && (
          <div className="mod-empty">
            <b>Nenhum módulo nesse filtro.</b>
            <p>
              {search && <>Nada casa com “{search}”</>}
              {search && (fArea !== "all" || fStatus !== "all") && " · "}
              {fArea !== "all" && <>Área {fArea}</>}
              {fArea !== "all" && fStatus !== "all" && " · "}
              {fStatus !== "all" && <>Status {STATUS_LABEL[fStatus]}</>}
            </p>
            <button className="os-btn" onClick={() => { setSearchInput(""); setFArea("all"); setFStatus("all"); }}>Limpar busca e filtros</button>
          </div>
        )}
      </div>

      {sel && <ModuloDrawer m={sel} onClose={() => setSel(null)} onInstall={() => install(sel)} onToggle={() => toggle(sel)}/>}

      {toast && <div className="mod-toast">{toast}</div>}
    </div>
  );
}

window.ModulosPage = ModulosPage;
})();
