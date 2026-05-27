// crm-page.jsx — CRM completo em React: kanban interativo com drag-drop, drawer, filtros.
// Substitui o mockup estático. Tweaks ao vivo, state real, atualização em tempo real.
(() => {
const { useState, useMemo, useEffect } = React;

const STAGES = [
  { id: "lead",     label: "Lead",        accent: "oklch(0.55 0.02 250)" },
  { id: "qual",     label: "Qualificado", accent: "oklch(0.55 0.13 270)" },
  { id: "prop",     label: "Proposta",    accent: "oklch(0.55 0.13 70)"  },
  { id: "negoc",    label: "Negociação",  accent: "oklch(0.55 0.16 30)"  },
  { id: "ganho",    label: "Ganho",       accent: "oklch(0.55 0.13 145)" },
];

const INITIAL_DEALS = [
  { id: "d1",  stage: "lead",  client: "Padaria Estrela",       contact: "Renato Lopes",        value: 480,   source: "WhatsApp",    age: "2h",     badge: "novo",           av: "PE", avc: 0 },
  { id: "d2",  stage: "lead",  client: "Clínica Vida Plena",    contact: "Marcos Vinícius",     value: 1890,  source: "Indicação",   age: "ontem",  badge: "indicação",     av: "MV", avc: 30 },
  { id: "d3",  stage: "lead",  client: "João Reis (PF)",        contact: "—",                   value: 320,   source: "Site",        age: "há 3d",  badge: null,            av: "JR", avc: 145 },
  { id: "d4",  stage: "qual",  client: "Acme Comércio Ltda",    contact: "Camila Diniz",        value: 3200,  source: "Telefone",    age: "hoje",   badge: "orçamento ok",  ok: true,    av: "AC", avc: 220 },
  { id: "d5",  stage: "qual",  client: "TechPro Equipamentos",  contact: "Diego Vasconcellos",  value: 1640,  source: "Indicação",   age: "1d",     badge: "aguarda decisão", warn: true, av: "TP", avc: 60 },
  { id: "d6",  stage: "prop",  client: "Construtora Vértice",   contact: "Eduardo Pessoa",      value: 3200,  source: "Site",        age: "3d sem resp.", badge: "3d sem resp.", warn: true, av: "CV", avc: 295 },
  { id: "d7",  stage: "prop",  client: "Mercado União",         contact: "João Inst.",          value: 8420,  source: "Indicação",   age: "revisada", badge: "negociação", info: true, av: "MU", avc: 145 },
  { id: "d8",  stage: "negoc", client: "Posto BR Centro",       contact: "Marcos Vinícius",     value: 5620,  source: "Telefone",    age: "decide sex", badge: "decisão sexta", bad: true, av: "PF", avc: 30 },
  { id: "d9",  stage: "negoc", client: "Imobiliária Horizonte", contact: "Luís",                value: 4200,  source: "WhatsApp",    age: "fechando", badge: "fechando hoje", ok: true, av: "IH", avc: 250 },
  { id: "d10", stage: "ganho", client: "Studio Foco",           contact: "Marina T.",           value: 2480,  source: "Site",        age: "10/05",  badge: "contrato assinado", win: true, av: "SF", avc: 0 },
  { id: "d11", stage: "ganho", client: "Farmácia Saúde Total",  contact: "Bruno",               value: 1280,  source: "Indicação",   age: "08/05",  badge: "NFe emitida", win: true, av: "FA", avc: 145 },
];

const fmtBRL = (n) => "R$ " + n.toLocaleString("pt-BR", { minimumFractionDigits: 0, maximumFractionDigits: 0 });

function CrmPage() {
  const [deals, setDeals] = useState(INITIAL_DEALS);
  const [drawerDeal, setDrawerDeal] = useState(null);
  const [draggingId, setDraggingId] = useState(null);
  const [filter, setFilter] = useState("all"); // all | hot (com badge warn/bad)
  const [newOpen, setNewOpen] = useState(false);

  // Toast efêmero quando deal muda de stage
  const [toast, setToast] = useState(null);
  useEffect(() => {
    if (!toast) return;
    const t = setTimeout(() => setToast(null), 2400);
    return () => clearTimeout(t);
  }, [toast]);

  const stats = useMemo(() => {
    const open = deals.filter(d => d.stage !== "ganho");
    const won = deals.filter(d => d.stage === "ganho");
    const pipeline = open.reduce((s, d) => s + d.value, 0);
    const wonValue = won.reduce((s, d) => s + d.value, 0);
    return { pipeline, wonValue, open: open.length, won: won.length, conv: deals.length > 0 ? Math.round((won.length / deals.length) * 100) : 0 };
  }, [deals]);

  const byCol = useMemo(() => {
    const m = {};
    STAGES.forEach(s => { m[s.id] = []; });
    const filtered = filter === "hot" ? deals.filter(d => d.warn || d.bad) : deals;
    filtered.forEach(d => { if (m[d.stage]) m[d.stage].push(d); });
    return m;
  }, [deals, filter]);

  const moveDeal = (id, toStage) => {
    setDeals(ds => ds.map(d => d.id === id ? { ...d, stage: toStage } : d));
    const d = deals.find(x => x.id === id);
    const stageLabel = STAGES.find(s => s.id === toStage)?.label;
    if (d && stageLabel) setToast(`${d.client} → ${stageLabel}`);
  };

  const handleDragStart = (id) => (e) => {
    setDraggingId(id);
    e.dataTransfer.effectAllowed = "move";
    e.dataTransfer.setData("text/plain", id);
  };
  const handleDragEnd = () => setDraggingId(null);
  const handleDragOver = (e) => { e.preventDefault(); e.dataTransfer.dropEffect = "move"; };
  const handleDrop = (toStage) => (e) => {
    e.preventDefault();
    const id = e.dataTransfer.getData("text/plain");
    if (id) moveDeal(id, toStage);
  };

  return (
    <div className="os-page crm-page" data-screen-label="01 CRM">
      <div className="os-page-h">
        <div className="os-page-h-l">
          <h1>CRM</h1>
          <p>Funil comercial · {stats.open} oportunidades · {fmtBRL(stats.pipeline)} em pipeline · conversão {stats.conv}%</p>
        </div>
        <div className="os-page-h-r">
          <button className={"os-btn ghost" + (filter === "hot" ? " active" : "")} onClick={() => setFilter(filter === "hot" ? "all" : "hot")}>
            {filter === "hot" ? "✓ " : ""}Só quentes
          </button>
          <button className="os-btn ghost">Importar leads</button>
          <button className="os-btn primary" onClick={() => setNewOpen(true)}>+ Novo lead</button>
        </div>
      </div>

      <div className="os-stats crm-stats">
        <div className="os-stat fin-stat-hero">
          <small>Pipeline ativo</small>
          <b className="mono">{fmtBRL(stats.pipeline)}</b>
          <span className="fin-stat-hint">{stats.open} oportunidades em aberto</span>
        </div>
        <div className="os-stat">
          <small>Ganhas mês</small>
          <b className="mono" style={{ color: "oklch(0.42 0.13 145)" }}>{fmtBRL(stats.wonValue)}</b>
          <span className="fin-stat-hint">{stats.won} deals · ticket {fmtBRL(stats.won > 0 ? stats.wonValue/stats.won : 0)}</span>
        </div>
        <div className="os-stat">
          <small>Taxa conversão</small>
          <b className="mono">{stats.conv}%</b>
          <span className="fin-stat-hint">↑ 4pp vs abril</span>
        </div>
        <div className="os-stat">
          <small>Ciclo médio</small>
          <b className="mono">12 dias</b>
          <span className="fin-stat-hint">lead → venda</span>
        </div>
      </div>

      <div className="crm-board">
        {STAGES.map(stage => {
          const cards = byCol[stage.id];
          const colTotal = cards.reduce((s, d) => s + d.value, 0);
          return (
            <section key={stage.id} className="crm-col" onDragOver={handleDragOver} onDrop={handleDrop(stage.id)}>
              <header className="crm-col-h" style={{ borderTopColor: stage.accent }}>
                <span className="crm-col-dot" style={{ background: stage.accent }}/>
                <b>{stage.label}</b>
                <small className="mono">{fmtBRL(colTotal).replace("R$ ", "R$ ")}</small>
                <span className="crm-col-n">{cards.length}</span>
              </header>
              <div className="crm-col-body">
                {cards.length === 0 && <div className="crm-empty">arraste aqui</div>}
                {cards.map(d => (
                  <article key={d.id}
                    className={"crm-card" + (draggingId === d.id ? " dragging" : "") + (d.win ? " win" : "") + (d.bad ? " bad" : "") + (d.warn ? " warn" : "")}
                    draggable
                    onDragStart={handleDragStart(d.id)}
                    onDragEnd={handleDragEnd}
                    onClick={() => setDrawerDeal(d)}>
                    <div className="crm-card-top">
                      <span className="crm-av" style={{ background: `oklch(0.85 0.04 ${d.avc})`, color: `oklch(0.30 0.06 ${d.avc})` }}>{d.av}</span>
                      <b>{d.client}</b>
                    </div>
                    <div className="crm-card-meta">{d.contact} · {d.source}</div>
                    <div className="crm-card-val mono">{fmtBRL(d.value)}</div>
                    <div className="crm-card-foot">
                      {d.badge && <span className={"crm-badge" + (d.ok ? " ok" : d.warn ? " warn" : d.bad ? " bad" : d.win ? " win" : d.info ? " info" : "")}>{d.badge}</span>}
                      <span className="crm-card-age">{d.age}</span>
                    </div>
                  </article>
                ))}
              </div>
            </section>
          );
        })}
      </div>

      {toast && <div className="crm-toast">✓ {toast}</div>}

      {drawerDeal && (
        <>
          <div className="crm-backdrop" onClick={() => setDrawerDeal(null)}/>
          <aside className="crm-drawer">
            <header className="crm-drawer-h">
              <span className="crm-av lg" style={{ background: `oklch(0.85 0.04 ${drawerDeal.avc})`, color: `oklch(0.30 0.06 ${drawerDeal.avc})` }}>{drawerDeal.av}</span>
              <div>
                <h2>{drawerDeal.client}</h2>
                <p>{drawerDeal.contact} · {drawerDeal.source}</p>
              </div>
              <button className="crm-x" onClick={() => setDrawerDeal(null)}>✕</button>
            </header>
            <div className="crm-drawer-body">
              <div className="crm-drawer-val">
                <small>Valor da oportunidade</small>
                <b className="mono">{fmtBRL(drawerDeal.value)}</b>
              </div>
              <div className="crm-drawer-stage">
                <small>Estágio atual</small>
                <div className="crm-flow">
                  {STAGES.map((s, i) => {
                    const curIdx = STAGES.findIndex(x => x.id === drawerDeal.stage);
                    return (
                      <button key={s.id}
                              className={"crm-flow-step" + (i < curIdx ? " done" : i === curIdx ? " cur" : "")}
                              onClick={() => { moveDeal(drawerDeal.id, s.id); setDrawerDeal({ ...drawerDeal, stage: s.id }); }}>
                        {s.label}
                      </button>
                    );
                  })}
                </div>
              </div>
              <div className="crm-drawer-actions">
                <button className="os-btn">📞 Ligar</button>
                <button className="os-btn">💬 WhatsApp</button>
                <button className="os-btn">📄 Orçamento</button>
                <button className="os-btn primary">Próxima ação →</button>
              </div>
              <div className="crm-drawer-tl">
                <small>Histórico</small>
                <ul>
                  <li><b>Lead criado</b> · {drawerDeal.source} · {drawerDeal.age}</li>
                  <li><b>Primeiro contato</b> · {drawerDeal.contact}</li>
                  {drawerDeal.win && <li><b style={{color: "var(--ok)"}}>✓ Negócio fechado</b> · contrato assinado</li>}
                </ul>
              </div>
            </div>
          </aside>
        </>
      )}

      {newOpen && (
        <>
          <div className="crm-backdrop" onClick={() => setNewOpen(false)}/>
          <aside className="crm-drawer">
            <header className="crm-drawer-h">
              <div><h2>+ Novo lead</h2><p>Preencha e crie em Leads.</p></div>
              <button className="crm-x" onClick={() => setNewOpen(false)}>✕</button>
            </header>
            <div className="crm-drawer-body">
              <div className="crm-drawer-val">
                <small>(prévia)</small>
                <p style={{margin:0, color:"var(--text-mute)", fontSize:12.5}}>Form de cadastro seria aqui — nome, contato, fonte, valor estimado.</p>
              </div>
              <div className="crm-drawer-actions">
                <button className="os-btn" onClick={() => setNewOpen(false)}>Cancelar</button>
                <button className="os-btn primary" onClick={() => {
                  const nx = { id: "d" + Date.now(), stage: "lead", client: "Novo Lead (demo)", contact: "—", value: 1000, source: "Manual", age: "agora", badge: "novo", av: "NL", avc: 220 };
                  setDeals(d => [nx, ...d]);
                  setNewOpen(false);
                  setToast("Lead criado: " + nx.client);
                }}>Criar lead</button>
              </div>
            </div>
          </aside>
        </>
      )}
    </div>
  );
}

window.CrmPage = CrmPage;
})();
