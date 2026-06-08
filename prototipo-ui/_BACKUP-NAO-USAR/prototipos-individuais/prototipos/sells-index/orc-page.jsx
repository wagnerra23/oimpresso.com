// orc-page.jsx — Listagem de Orçamentos (Fase 3)
const { useState: useStateQ, useEffect: useEffectQ, useMemo: useMemoQ } = React;

const ORC_STATUS = {
  rascunho:   { label:"Rascunho",   color:"oklch(0.65 0.02 240)" },
  enviado:    { label:"Enviado",    color:"oklch(0.55 0.14 240)" },
  negociacao: { label:"Negociação", color:"oklch(0.60 0.14 70)"  },
  aprovado:   { label:"Aprovado",   color:"oklch(0.50 0.14 145)" },
  perdido:    { label:"Perdido",    color:"oklch(0.55 0.14 25)"  },
};

function OrcListPage() {
  const all = ORC_DATA.ORC_LIST;
  const [statusFilter, setStatusFilter] = useStateQ("ativos");
  const [query, setQuery] = useStateQ("");

  const filtered = useMemoQ(() => {
    let out = all;
    if (statusFilter === "ativos")     out = out.filter(o => ["rascunho","enviado","negociacao"].includes(o.status));
    else if (statusFilter === "ganhos") out = out.filter(o => o.status === "aprovado");
    else if (statusFilter === "perdidos") out = out.filter(o => o.status === "perdido");
    else if (statusFilter !== "todos") out = out.filter(o => o.status === statusFilter);
    if (query.trim()) {
      const q = query.toLowerCase();
      out = out.filter(o => o.client.toLowerCase().includes(q) || o.id.toLowerCase().includes(q) || o.items.toLowerCase().includes(q));
    }
    return out;
  }, [all, statusFilter, query]);

  const stats = useMemoQ(() => {
    const ativos = all.filter(o => ["rascunho","enviado","negociacao"].includes(o.status));
    const totalAtivo = ativos.reduce((s,o) => s + parseFloat(o.value.replace(/[^\d,]/g,'').replace(',','.')), 0);
    const ganhos = all.filter(o => o.status === "aprovado");
    const totalGanho = ganhos.reduce((s,o) => s + parseFloat(o.value.replace(/[^\d,]/g,'').replace(',','.')), 0);
    return {
      ativos: ativos.length,
      totalAtivo: "R$ " + totalAtivo.toLocaleString('pt-BR',{minimumFractionDigits:0,maximumFractionDigits:0}),
      ganhos: ganhos.length,
      totalGanho: "R$ " + totalGanho.toLocaleString('pt-BR',{minimumFractionDigits:0,maximumFractionDigits:0}),
      conversao: ganhos.length + ativos.length > 0 ? Math.round(ganhos.length / (ganhos.length + all.filter(o=>o.status==="perdido").length || 1) * 100) : 0,
    };
  }, [all]);

  const TABS = [
    { id:"ativos",   label:"Ativos",    count: stats.ativos },
    { id:"ganhos",   label:"Aprovados", count: stats.ganhos },
    { id:"perdidos", label:"Perdidos",  count: all.filter(o => o.status === "perdido").length },
    { id:"todos",    label:"Todos",     count: all.length },
  ];

  return (
    <>
      <div className="os-page-h">
        <div className="os-page-h-l">
          <h1>Orçamentos</h1>
          <p>Propostas em aberto, aprovadas e perdidas</p>
        </div>
        <div className="os-page-h-r">
          <button className="os-btn ghost"><I.search size={13}/> Filtros</button>
          <button className="os-btn primary"><I.plus size={13}/> Novo orçamento</button>
        </div>
      </div>

      <div className="os-stats">
        <div className="os-stat"><small>Em aberto</small><b>{stats.ativos}</b></div>
        <div className="os-stat"><small>Valor em aberto</small><b>{stats.totalAtivo}</b></div>
        <div className="os-stat"><small>Aprovados</small><b className="ok">{stats.ganhos}</b></div>
        <div className="os-stat"><small>Valor aprovado</small><b>{stats.totalGanho}</b></div>
        <div className="os-stat"><small>Conversão</small><b>{stats.conversao}%</b></div>
      </div>

      <div className="os-toolbar">
        <div className="os-tabs">
          {TABS.map(t => (
            <button key={t.id} className={`os-tab ${statusFilter===t.id?"active":""}`} onClick={() => setStatusFilter(t.id)}>
              {t.label} <span className="os-tab-count">{t.count}</span>
            </button>
          ))}
        </div>
        <div className="os-toolbar-r">
          <div className="os-search">
            <I.search size={13}/>
            <input type="text" placeholder="Cliente, item, número…" value={query} onChange={e => setQuery(e.target.value)}/>
          </div>
        </div>
      </div>

      <div className="os-table-wrap">
        <table className="os-table">
          <thead>
            <tr>
              <th>Número</th>
              <th>Cliente</th>
              <th>Itens</th>
              <th className="num">Valor</th>
              <th>Validade</th>
              <th>Responsável</th>
              <th>Status</th>
              <th className="num">Prob.</th>
            </tr>
          </thead>
          <tbody>
            {filtered.map(o => {
              const s = ORC_STATUS[o.status];
              return (
                <tr key={o.id} className="os-row">
                  <td className="os-cell-id mono">{o.id}</td>
                  <td><div className="cli-name">{o.client}</div><div className="cli-sub">{o.contact}</div></td>
                  <td className="orc-items">{o.items}</td>
                  <td className="cli-num mono">{o.value}</td>
                  <td className="cli-last">{o.validity}</td>
                  <td className="cli-city">{o.responsible}</td>
                  <td>
                    <span className="orc-status" style={{ background: s.color + "22", color: s.color, borderColor: s.color + "55" }}>
                      {s.label}
                    </span>
                    {o.osId && <span className="orc-os mono"> → OS #{o.osId}</span>}
                  </td>
                  <td className="cli-num mono">
                    <div className="orc-prob">
                      <div className="orc-prob-fill" style={{ width: o.prob + "%" }}/>
                      <span>{o.prob}%</span>
                    </div>
                  </td>
                </tr>
              );
            })}
            {filtered.length === 0 && (
              <tr><td colSpan={8} className="os-empty-row">
                <div className="os-empty">
                  <div className="os-empty-ico"><I.quote size={20}/></div>
                  <b>Nenhum orçamento</b>
                  <small>Ajuste os filtros ou crie um novo</small>
                </div>
              </td></tr>
            )}
          </tbody>
        </table>
      </div>
    </>
  );
}

window.OrcListPage = OrcListPage;
