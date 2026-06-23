// producao-page.jsx — Operacional de produção: Fila → Acabamento → Expedição
const { useState: useStateProd, useMemo: useMemoProd } = React;

// ─── Mock: equipamentos e atribuições ───
const PROD_EQUIPMENTS = [
  { id: "roland",  label: "Roland 540",       kind: "Impressão", color: "violet" },
  { id: "hp-latex",label: "HP Latex 365",     kind: "Impressão", color: "violet" },
  { id: "plotter", label: "Plotter Recorte",  kind: "Recorte",   color: "cyan" },
  { id: "offset",  label: "Offset Heidelberg",kind: "Impressão", color: "blue" },
];

const ACAB_OPS = [
  { id: "corte",   label: "Corte" },
  { id: "lamin",   label: "Laminação" },
  { id: "aplica",  label: "Aplicação" },
  { id: "dobra",   label: "Dobra/Vinco" },
];

// Enriquece OS_LIST com metadados de produção (equipamento, % progresso, op)
function buildProdQueue() {
  const OS_LIST = (window.OS_DATA && window.OS_DATA.OS_LIST) || [];
  const inProd = OS_LIST.filter(o => o.stage === "producao").map((o, i) => ({
    ...o,
    equip: PROD_EQUIPMENTS[i % PROD_EQUIPMENTS.length].id,
    progress: [12, 38, 67, 22, 84, 50][i % 6] || 30,
    sequence: i + 1,
    eta: ["1h20", "3h40", "45min", "2h", "5h10", "1h"][i % 6],
  }));
  const inAcab = OS_LIST.filter(o => o.stage === "acabamento").map((o, i) => ({
    ...o,
    op: ACAB_OPS[i % ACAB_OPS.length].id,
    pieces: [8, 200, 50, 12, 4][i % 5] || o.qty,
    done: [3, 80, 0, 4, 1][i % 5] || 0,
  }));
  const inExp = OS_LIST.filter(o => o.stage === "expedicao").map((o, i) => ({
    ...o,
    route: ["Centro", "Zona Sul", "Zona Norte", "Retirada balcão"][i % 4],
    courier: ["Loggi", "Próprio", "Cliente retira", "Motoboy"][i % 4],
    package: ["Tubo PVC", "Caixa 50×30", "Envelope rígido", "Sacola"][i % 4],
  }));
  return { inProd, inAcab, inExp };
}

// ─── Card por etapa ───
function ProdCardImpressao({ os, onClick, onPause, onAdvance }) {
  const eq = PROD_EQUIPMENTS.find(e => e.id === os.equip);
  return (
    <div className={"prod-card" + (os.urgent ? " urgent" : "")} onClick={onClick}>
      <div className="prod-card-top">
        <span className="prod-seq">#{os.sequence}</span>
        <span className="prod-os">OS #{os.id}</span>
        {os.urgent && <span className="prod-urgent">urgente</span>}
      </div>
      <div className="prod-card-title">{os.product}</div>
      <div className="prod-card-client">{os.client}</div>
      <div className="prod-equip-row">
        <span className={`prod-equip-pill ${eq.color}`}>{eq.label}</span>
        <span className="prod-eta">{os.eta} restante</span>
      </div>
      <div className="prod-progress">
        <div className="prod-progress-bar" style={{ width: `${os.progress}%` }}/>
        <span className="prod-progress-label">{os.progress}%</span>
      </div>
      <div className="prod-card-foot">
        <span className="prod-deadline">{os.deadline}</span>
        <div className="prod-actions">
          <button className="prod-act" onClick={(e) => { e.stopPropagation(); onPause?.(os); }}><I.clock size={11}/>Pausar</button>
          <button className="prod-act primary" onClick={(e) => { e.stopPropagation(); onAdvance?.(os); }}>Avançar →</button>
        </div>
      </div>
    </div>
  );
}

function ProdCardAcabamento({ os, onClick, onAdvance }) {
  const op = ACAB_OPS.find(o => o.id === os.op);
  const pct = Math.round((os.done / os.pieces) * 100) || 0;
  return (
    <div className={"prod-card" + (os.urgent ? " urgent" : "")} onClick={onClick}>
      <div className="prod-card-top">
        <span className="prod-os">OS #{os.id}</span>
        <span className="prod-op-pill">{op.label}</span>
        {os.urgent && <span className="prod-urgent">urgente</span>}
      </div>
      <div className="prod-card-title">{os.product}</div>
      <div className="prod-card-client">{os.client}</div>
      <div className="prod-pieces-row">
        <span className="prod-pieces">{os.done}<span className="t-mute">/{os.pieces}</span> peças</span>
        <span className="prod-pct">{pct}%</span>
      </div>
      <div className="prod-progress">
        <div className="prod-progress-bar" style={{ width: `${pct}%` }}/>
      </div>
      <div className="prod-card-foot">
        <span className="prod-deadline">{os.deadline}</span>
        <button className="prod-act primary" onClick={(e) => { e.stopPropagation(); onAdvance?.(os); }}>Concluir →</button>
      </div>
    </div>
  );
}

function ProdCardExpedicao({ os, onClick, onAdvance }) {
  return (
    <div className={"prod-card" + (os.urgent ? " urgent" : "")} onClick={onClick}>
      <div className="prod-card-top">
        <span className="prod-os">OS #{os.id}</span>
        <span className="prod-route-pill"><I.truck size={10}/>{os.route}</span>
      </div>
      <div className="prod-card-title">{os.product}</div>
      <div className="prod-card-client">{os.client}</div>
      <dl className="prod-exp-meta">
        <dt>Embalagem</dt><dd>{os.package}</dd>
        <dt>Transporte</dt><dd>{os.courier}</dd>
      </dl>
      <div className="prod-card-foot">
        <span className="prod-deadline">{os.deadline}</span>
        <button className="prod-act primary" onClick={(e) => { e.stopPropagation(); onAdvance?.(os); }}>Entregue ✓</button>
      </div>
    </div>
  );
}

// ─── Coluna de etapa ───
function ProdColumn({ title, count, accent, children, capacity, headRight }) {
  return (
    <section className={"prod-col prod-col-" + accent}>
      <header className="prod-col-head">
        <div className="prod-col-head-l">
          <span className={"prod-col-dot " + accent}/>
          <h3>{title}</h3>
          <span className="prod-col-count">{count}</span>
        </div>
        {headRight || (capacity && <span className="prod-col-cap">{capacity}</span>)}
      </header>
      <div className="prod-col-body">{children}</div>
    </section>
  );
}

// ─── Página principal ───
function ProducaoPage() {
  const [view, setView] = useStateProd(() => {
    // clamp: valor de localStorage fora do domínio (versão antiga) deixava view órfã
    // → NENHUM bloco renderizava (kanban fantasma, achado no fluxo 2026-06-10)
    try { const v = localStorage.getItem("oimpresso.prod.view"); return v === "list" ? "list" : "kanban"; } catch (e) { return "kanban"; }
  });
  const [equipFilter, setEquipFilter] = useStateProd("all");
  const [selected, setSelected] = useStateProd(null);

  React.useEffect(() => {
    try { localStorage.setItem("oimpresso.prod.view", view); } catch (e) {}
  }, [view]);

  const queue = useMemoProd(() => buildProdQueue(), []);
  const inProd = equipFilter === "all" ? queue.inProd : queue.inProd.filter(o => o.equip === equipFilter);

  const totals = {
    fila: queue.inProd.length,
    acab: queue.inAcab.length,
    exp: queue.inExp.length,
    urgent: [...queue.inProd, ...queue.inAcab, ...queue.inExp].filter(o => o.urgent).length,
    valor: [...queue.inProd, ...queue.inAcab, ...queue.inExp]
      .reduce((a, o) => a + parseFloat(o.value.replace(/[^\d,]/g,'').replace(',','.')), 0),
  };

  return (
    <div className="prod-page">
      {/* Header */}
      <div className="prod-header">
        <div className="prod-header-l">
          <h1>Produção</h1>
          <p>Fila de impressão, acabamento e expedição em tempo real</p>
        </div>
        <div className="prod-header-r">
          <div className="prod-view-toggle">
            <button className={view === "kanban" ? "active" : ""} onClick={() => setView("kanban")}>
              <I.grid size={11}/>Kanban
            </button>
            <button className={view === "list" ? "active" : ""} onClick={() => setView("list")}>
              <I.list size={11}/>Lista
            </button>
          </div>
          <button className="os-btn ghost"><I.printer size={11}/>Imprimir fila</button>
          <button className="os-btn primary"><I.plus size={11}/>Nova OS</button>
        </div>
      </div>

      {/* KPIs */}
      <div className="prod-kpis">
        <div className="prod-kpi">
          <span className="prod-kpi-label">Em produção</span>
          <span className="prod-kpi-value">{totals.fila}</span>
          <span className="prod-kpi-sub">{PROD_EQUIPMENTS.length} equipamentos ativos</span>
        </div>
        <div className="prod-kpi">
          <span className="prod-kpi-label">Em acabamento</span>
          <span className="prod-kpi-value">{totals.acab}</span>
          <span className="prod-kpi-sub">{queue.inAcab.reduce((a,o) => a+o.pieces, 0)} peças</span>
        </div>
        <div className="prod-kpi">
          <span className="prod-kpi-label">A expedir</span>
          <span className="prod-kpi-value">{totals.exp}</span>
          <span className="prod-kpi-sub">{queue.inExp.filter(o => o.deadline.includes("hoje")).length} hoje</span>
        </div>
        <div className="prod-kpi prod-kpi-urgent">
          <span className="prod-kpi-label">Urgentes</span>
          <span className="prod-kpi-value">{totals.urgent}</span>
          <span className="prod-kpi-sub">prazo crítico</span>
        </div>
        <div className="prod-kpi">
          <span className="prod-kpi-label">Valor em curso</span>
          <span className="prod-kpi-value">R$ {totals.valor.toLocaleString("pt-BR", {minimumFractionDigits:0, maximumFractionDigits:0})}</span>
          <span className="prod-kpi-sub">faturamento previsto</span>
        </div>
      </div>

      {/* Filtros equipamento */}
      <div className="prod-equip-filters">
        <button className={"prod-equip-tab" + (equipFilter === "all" ? " active" : "")}
                onClick={() => setEquipFilter("all")}>
          Todos os equipamentos <span className="count">{queue.inProd.length}</span>
        </button>
        {PROD_EQUIPMENTS.map(e => {
          const n = queue.inProd.filter(o => o.equip === e.id).length;
          return (
            <button key={e.id}
                    className={"prod-equip-tab" + (equipFilter === e.id ? " active" : "")}
                    onClick={() => setEquipFilter(e.id)}>
              <span className={`prod-equip-dot ${e.color}`}/>
              {e.label} <span className="count">{n}</span>
            </button>
          );
        })}
      </div>

      {/* Kanban */}
      {view === "kanban" && (
        <div className="prod-kanban">
          <ProdColumn title="Fila de impressão" count={inProd.length} accent="violet"
                      capacity={`${inProd.length}/12 cap.`}>
            {inProd.length === 0 && <div className="prod-empty">Sem ordens nesse equipamento</div>}
            {inProd.map(os => (
              <ProdCardImpressao key={os.id} os={os}
                                 onClick={() => setSelected(os)}/>
            ))}
          </ProdColumn>

          <ProdColumn title="Acabamento" count={queue.inAcab.length} accent="amber"
                      headRight={
                        <div className="prod-col-ops">
                          {ACAB_OPS.map(o => (
                            <span key={o.id} className="prod-col-op-tag">{o.label}</span>
                          ))}
                        </div>
                      }>
            {queue.inAcab.map(os => (
              <ProdCardAcabamento key={os.id} os={os}
                                  onClick={() => setSelected(os)}/>
            ))}
          </ProdColumn>

          <ProdColumn title="Expedição" count={queue.inExp.length} accent="cyan"
                      capacity={`${queue.inExp.filter(o => o.deadline.includes("hoje")).length} hoje`}>
            {queue.inExp.map(os => (
              <ProdCardExpedicao key={os.id} os={os}
                                 onClick={() => setSelected(os)}/>
            ))}
          </ProdColumn>
        </div>
      )}

      {/* Lista plana */}
      {view === "list" && (
        <div className="prod-list">
          <table className="os-table">
            <thead>
              <tr>
                <th>OS</th>
                <th>Cliente</th>
                <th>Produto</th>
                <th>Etapa</th>
                <th>Equipamento/Op</th>
                <th>Progresso</th>
                <th>Prazo</th>
              </tr>
            </thead>
            <tbody>
              {[...queue.inProd, ...queue.inAcab, ...queue.inExp].map(os => {
                const eq = PROD_EQUIPMENTS.find(e => e.id === os.equip);
                const op = ACAB_OPS.find(o => o.id === os.op);
                return (
                  <tr key={os.id} className={os.urgent ? "row-urgent" : ""}
                      onClick={() => setSelected(os)}>
                    <td className="mono">#{os.id}</td>
                    <td>{os.client}</td>
                    <td className="t-truncate">{os.product}</td>
                    <td><span className={`stage-pill stage-${os.stage}`}>{
                      os.stage === "producao" ? "Produção" :
                      os.stage === "acabamento" ? "Acabamento" : "Expedição"
                    }</span></td>
                    <td>{eq?.label || op?.label || os.route || "—"}</td>
                    <td>{os.progress != null ? `${os.progress}%` : os.done != null ? `${os.done}/${os.pieces}` : "pronto"}</td>
                    <td>{os.deadline}</td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        </div>
      )}

      {/* Drawer de detalhe */}
      {selected && (
        <div className="prod-drawer-backdrop" onClick={() => setSelected(null)}>
          <aside className="prod-drawer" onClick={(e) => e.stopPropagation()}>
            <header className="prod-drawer-head">
              <div>
                <div className="prod-drawer-eyebrow">OS #{selected.id} · {selected.stage}</div>
                <h2>{selected.product}</h2>
                <p>{selected.client} · {selected.contact}</p>
              </div>
              <button className="icon-btn" onClick={() => setSelected(null)}><I.x size={14}/></button>
            </header>
            <div className="prod-drawer-body">
              <dl className="prod-drawer-meta">
                <dt>Quantidade</dt><dd>{selected.qty}</dd>
                <dt>Valor</dt><dd>{selected.value}</dd>
                <dt>Responsável</dt><dd>{selected.responsible}</dd>
                <dt>Time</dt><dd>{selected.team}</dd>
                <dt>Prazo</dt><dd className={selected.urgent ? "t-urgent" : ""}>{selected.deadline}</dd>
                <dt>Atualizada</dt><dd>{selected.updated}</dd>
              </dl>
              <div className="prod-drawer-actions">
                <button className="os-btn ghost"><I.message size={11}/>Abrir conversa</button>
                <button className="os-btn ghost"><I.folder size={11}/>Anexos</button>
                <button className="os-btn primary">Avançar etapa →</button>
              </div>
            </div>
          </aside>
        </div>
      )}
    </div>
  );
}

window.ProducaoPage = ProducaoPage;
