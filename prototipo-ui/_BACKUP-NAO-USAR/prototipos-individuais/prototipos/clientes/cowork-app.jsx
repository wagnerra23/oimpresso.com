// clientes-page.jsx — Listagem + Detalhe de Clientes (Fase 3)
const { useState: useStateC, useMemo: useMemoC } = React;

function clientStats(client, osList) {
  const own = osList.filter(o => o.client === client.name);
  const open = own.filter(o => !["entregue","cancelado"].includes(o.stage));
  const late = own.filter(o => /atrasada/i.test(o.deadline));
  const totalValue = own.reduce((s, o) => {
    const n = parseFloat((o.value || "0").replace(/[^\d,]/g, "").replace(",", "."));
    return s + (isNaN(n) ? 0 : n);
  }, 0);
  return { count: own.length, openCount: open.length, lateCount: late.length, totalValue, ownList: own };
}

function fmtBRL(n) {
  return n.toLocaleString("pt-BR", { style: "currency", currency: "BRL" });
}

function ClienteRow({ c, stats, onOpen }) {
  const status = stats.lateCount > 0 ? "late" : stats.openCount > 0 ? "active" : "idle";
  return (
    <tr className="os-row" onClick={onOpen}>
      <td>
        <div className="cli-name">
          <div className={`cli-avatar grad-${(c.id.charCodeAt(2) % 5) + 1}`}>
            {c.name.split(" ").slice(0,2).map(s => s[0]).join("").toUpperCase()}
          </div>
          <div>
            <div className="cli-name-text">{c.name}</div>
            <div className="cli-doc">{c.doc}</div>
          </div>
        </div>
      </td>
      <td>
        <div className="cli-contact-line">{c.contact}</div>
        <div className="cli-phone">{c.phone}</div>
      </td>
      <td className="num">{stats.count}</td>
      <td className="num">{stats.openCount}</td>
      <td className="num value">{fmtBRL(stats.totalValue)}</td>
      <td>
        <span className={`cli-status ${status}`}>
          {status === "late" ? "Atrasada" : status === "active" ? "Ativo" : "Sem OS aberta"}
        </span>
      </td>
      <td>{c.lastOs}</td>
    </tr>
  );
}

function ClienteDetailDrawer({ client, stats, osList, onClose }) {
  const own = stats.ownList.slice().sort((a,b) => parseInt(b.id) - parseInt(a.id));
  return (
    <div className="os-drawer-back" onClick={onClose}>
      <div className="os-drawer wide cli-drawer" onClick={(e) => e.stopPropagation()}>
        <div className="os-drawer-head">
          <div className="cli-head">
            <div className={`cli-avatar lg grad-${(client.id.charCodeAt(2) % 5) + 1}`}>
              {client.name.split(" ").slice(0,2).map(s => s[0]).join("").toUpperCase()}
            </div>
            <div>
              <div className="cli-head-name">{client.name}</div>
              <div className="cli-head-doc">{client.doc}</div>
            </div>
          </div>
          <button className="os-icon-btn" onClick={onClose} title="Fechar"><I.close size={16}/></button>
        </div>

        <div className="os-drawer-body">
          <div className="cli-kpis">
            <div className="cli-kpi"><div className="cli-kpi-v">{stats.count}</div><div className="cli-kpi-l">OS no total</div></div>
            <div className="cli-kpi"><div className="cli-kpi-v">{stats.openCount}</div><div className="cli-kpi-l">Em aberto</div></div>
            <div className={`cli-kpi ${stats.lateCount>0?"danger":""}`}><div className="cli-kpi-v">{stats.lateCount}</div><div className="cli-kpi-l">Atrasadas</div></div>
            <div className="cli-kpi"><div className="cli-kpi-v">{fmtBRL(stats.totalValue)}</div><div className="cli-kpi-l">Valor total</div></div>
          </div>

          <div className="cli-section">
            <div className="cli-section-title">Contato</div>
            <div className="cli-info-grid">
              <div><div className="cli-info-l">Nome</div><div className="cli-info-v">{client.contact}</div></div>
              <div><div className="cli-info-l">Telefone</div><div className="cli-info-v">{client.phone}</div></div>
              <div><div className="cli-info-l">CNPJ/CPF</div><div className="cli-info-v">{client.doc}</div></div>
              <div><div className="cli-info-l">Última OS</div><div className="cli-info-v">{client.lastOs}</div></div>
            </div>
          </div>

          <div className="cli-section">
            <div className="cli-section-title">Histórico de OS ({own.length})</div>
            <div className="cli-history">
              {own.length === 0 && <div className="cli-empty">Nenhuma OS registrada.</div>}
              {own.map(o => (
                <div className="cli-os" key={o.id}>
                  <div className="cli-os-id">#{o.id}</div>
                  <div className="cli-os-prod">{o.product}</div>
                  <div className={`cli-os-stage stage-${o.stage}`}>
                    {window.OS_DATA.OS_STAGES.find(s => s.id === o.stage)?.label || o.stage}
                  </div>
                  <div className="cli-os-deadline">{o.deadline}</div>
                  <div className="cli-os-value">{o.value}</div>
                </div>
              ))}
            </div>
          </div>

          <div className="cli-section">
            <div className="cli-section-title">Financeiro resumido</div>
            <div className="cli-fin">
              <div className="cli-fin-row"><span>OS faturadas</span><b>{fmtBRL(stats.totalValue * 0.62)}</b></div>
              <div className="cli-fin-row"><span>Em aberto</span><b>{fmtBRL(stats.totalValue * 0.38)}</b></div>
              <div className="cli-fin-row"><span>Inadimplência</span><b className="danger">R$ 0,00</b></div>
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

function CliListPage() {
  const OS_DATA = window.OS_DATA || {};
  const OS_LIST = OS_DATA.OS_LIST || [];
  const OS_CLIENTS = OS_DATA.OS_CLIENTS || [];
  const [q, setQ] = useStateC("");
  const [filter, setFilter] = useStateC("all"); // all / active / late / idle
  const [openId, setOpenId] = useStateC(null);

  const enriched = useMemoC(() =>
    OS_CLIENTS.map(c => ({ c, stats: clientStats(c, OS_LIST) }))
  , [OS_CLIENTS, OS_LIST]);

  const filtered = enriched.filter(({ c, stats }) => {
    if (filter === "active" && stats.openCount === 0) return false;
    if (filter === "late" && stats.lateCount === 0) return false;
    if (filter === "idle" && stats.openCount > 0) return false;
    if (q && !(`${c.name} ${c.doc} ${c.contact} ${c.phone}`).toLowerCase().includes(q.toLowerCase())) return false;
    return true;
  });

  const totals = useMemoC(() => ({
    total: enriched.length,
    active: enriched.filter(e => e.stats.openCount > 0).length,
    late: enriched.filter(e => e.stats.lateCount > 0).length,
    revenue: enriched.reduce((s, e) => s + e.stats.totalValue, 0),
  }), [enriched]);

  const open = openId ? enriched.find(e => e.c.id === openId) : null;

  return (
    <div className="os-page cli-page">
      <div className="os-head">
        <div className="os-head-top">
          <div>
            <h1 className="os-title">Clientes</h1>
            <div className="os-sub">Cadastro, histórico de OS e situação financeira</div>
          </div>
          <div className="os-head-actions">
            <button className="os-btn"><I.upload size={14}/> Importar</button>
            <button className="os-btn primary"><I.plus size={14}/> Novo cliente</button>
          </div>
        </div>
        <div className="os-kpis">
          <div className="os-kpi"><div className="os-kpi-v">{totals.total}</div><div className="os-kpi-l">Cadastrados</div></div>
          <div className="os-kpi"><div className="os-kpi-v">{totals.active}</div><div className="os-kpi-l">Com OS aberta</div></div>
          <div className={`os-kpi ${totals.late>0?"danger":""}`}><div className="os-kpi-v">{totals.late}</div><div className="os-kpi-l">Com atraso</div></div>
          <div className="os-kpi"><div className="os-kpi-v">{fmtBRL(totals.revenue)}</div><div className="os-kpi-l">Faturamento total</div></div>
        </div>
      </div>

      <div className="os-toolbar">
        <div className="os-tabs">
          {[
            { id: "all", label: "Todos" },
            { id: "active", label: "Com OS aberta" },
            { id: "late", label: "Com atraso" },
            { id: "idle", label: "Sem OS aberta" },
          ].map(t => (
            <button key={t.id} className={`os-tab ${filter === t.id ? "active" : ""}`} onClick={() => setFilter(t.id)}>
              {t.label} <span className="os-tab-count">{
                t.id === "all" ? enriched.length :
                t.id === "active" ? enriched.filter(e => e.stats.openCount > 0).length :
                t.id === "late" ? enriched.filter(e => e.stats.lateCount > 0).length :
                enriched.filter(e => e.stats.openCount === 0).length
              }</span>
            </button>
          ))}
        </div>
        <div className="os-search">
          <I.search size={14}/>
          <input placeholder="Buscar por nome, CNPJ, contato, telefone…" value={q} onChange={(e) => setQ(e.target.value)}/>
        </div>
      </div>

      <div className="os-table-wrap">
        <table className="os-table cli-table">
          <thead>
            <tr>
              <th>Cliente</th>
              <th>Contato</th>
              <th className="num">OS total</th>
              <th className="num">Em aberto</th>
              <th className="num">Valor total</th>
              <th>Status</th>
              <th>Última OS</th>
            </tr>
          </thead>
          <tbody>
            {filtered.map(({ c, stats }) => (
              <ClienteRow key={c.id} c={c} stats={stats} onOpen={() => setOpenId(c.id)}/>
            ))}
            {filtered.length === 0 && (
              <tr><td colSpan={7} className="os-empty">Nenhum cliente encontrado com esses filtros.</td></tr>
            )}
          </tbody>
        </table>
      </div>

      {open && (
        <ClienteDetailDrawer
          client={open.c}
          stats={open.stats}
          osList={OS_LIST}
          onClose={() => setOpenId(null)}
        />
      )}
    </div>
  );
}

window.CliListPage = CliListPage;
