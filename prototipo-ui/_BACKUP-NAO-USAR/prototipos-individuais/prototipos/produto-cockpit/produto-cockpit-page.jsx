// produto-cockpit-page.jsx — Produtos Cockpit V2
// Design: sidebar 200px + header sticky + filter tabs + list(KPIs+table) + drawer lateral + footer
// Target viewport: 1280×1024 (Larissa, balcão ROTA LIVRE)

const { useState, useMemo } = React;

const fmt = (n) =>
  "R$ " + Number(n || 0).toLocaleString("pt-BR", { minimumFractionDigits: 2, maximumFractionDigits: 2 });

const CAT_COLOR = {
  "Comunicação Visual": "#c97a3a",
  "Vestuário":          "#3a5fc9",
  "Brindes":            "#7a3ac9",
  "Insumo":             "#5b574f",
  "Serviço":            "#1f6d3a",
};
const CAT_INI = {
  "Comunicação Visual": "CV",
  "Vestuário":          "VT",
  "Brindes":            "BR",
  "Insumo":             "IN",
  "Serviço":            "SV",
};

const PRODS = [
  { id: "PROD-018", name: "Banner lona 380gr brilho", cat: "Comunicação Visual", type: "composto",
    sku: "BNR-380-BRI", ncm: "4911.10.90", cfop: "5101", unit: "m²",
    stock: 142, min: 50, max: 400,
    cost: 18.40, prices: { varejo: 55.00, atacado: 42.00, balcao: 48.00 },
    margin: 199, lead: 2, pop: 73, active: true, soldMonth: 48, lastSale: "hoje, 14:22",
    bom: [
      { name: "Lona 380gr branca brilho", sku: "INS-200", qty: 1.05, unit: "m²",  cost: 7.50,   total: 7.88  },
      { name: "Tinta solvente CMYK",      sku: "INS-022", qty: 0.05, unit: "L",   cost: 108.00, total: 5.40  },
      { name: "Ilhós metálico nº12",      sku: "INS-014", qty: 4,    unit: "un",  cost: 0.12,   total: 0.48  },
      { name: "Bastão reforço",           sku: "INS-031", qty: 0.5,  unit: "m",   cost: 3.20,   total: 1.60  },
      { name: "Mão de obra impressão",    sku: "SRV-001", qty: 0.08, unit: "h",   cost: 38.00,  total: 3.04  },
    ],
    variations: null,
    mov: [
      { t: "out", title: "Venda V-7831",      who: "Larissa",  at: "hoje 14:22",    qty: -6,   after: 142 },
      { t: "out", title: "Venda V-7824",      who: "Bruna",    at: "hoje 11:08",    qty: -3,   after: 148 },
      { t: "in",  title: "Compra COMP-2847",  who: "Wagner",   at: "08/05 11:42",   qty: +200, after: 151 },
      { t: "adj", title: "Ajuste inventário", who: "Wagner",   at: "02/05 18:00",   qty: -2,   after: -49 },
      { t: "out", title: "OS-2847",           who: "Produção", at: "30/04 09:30",   qty: -12,  after: -47 },
    ]
  },
  { id: "PROD-022", name: "Vinil adesivo recorte", cat: "Comunicação Visual", type: "simples",
    sku: "VIN-ADES", ncm: "3919.10.00", cfop: "5101", unit: "m²",
    stock: 280, min: 100, max: 600, cost: 5.80, prices: { varejo: 42, atacado: 32, balcao: 38 },
    margin: 624, lead: 1, pop: 88, active: true, soldMonth: 127, lastSale: "hoje, 16:01",
    bom: null, variations: null, mov: null },
  { id: "PROD-031", name: "Camiseta básica algodão 30.1", cat: "Vestuário", type: "composto",
    sku: "CAM-30.1", ncm: "6109.10.00", cfop: "5102", unit: "un",
    stock: 48, min: 30, max: 200, cost: 14.20, prices: { varejo: 39.90, atacado: 28, balcao: 34.90 },
    margin: 181, lead: 0, pop: 65, active: true, soldMonth: 34, lastSale: "ontem",
    bom: null,
    variations: { rows: ["P","M","G","GG"], cols: ["Preto","Branco","Cinza"],
      grid: [[8,12,4],[12,18,6],[10,14,3],[2,5,1]] },
    mov: null },
  { id: "PROD-040", name: "Squeeze plástico 500ml personalizado", cat: "Brindes", type: "composto",
    sku: "SQZ-500", ncm: "3924.10.00", cfop: "5101", unit: "un",
    stock: 12, min: 50, max: 300, cost: 8.90, prices: { varejo: 24.90, atacado: 18, balcao: 22 },
    margin: 148, lead: 5, pop: 42, active: true, soldMonth: 18, lastSale: "08/05",
    bom: null, variations: null, mov: null },
  { id: "PROD-018v", name: "Banner lona 440gr blackout", cat: "Comunicação Visual", type: "composto",
    sku: "BNR-440-BLK", ncm: "4911.10.90", cfop: "5101", unit: "m²",
    stock: 0, min: 30, max: 200, cost: 24.80, prices: { varejo: 75, atacado: 58, balcao: 65 },
    margin: 202, lead: 3, pop: 31, active: true, soldMonth: 6, lastSale: "05/05",
    bom: null, variations: null, mov: null },
  { id: "INS-200", name: "Lona 380gr branca brilho (insumo)", cat: "Insumo", type: "insumo",
    sku: "INS-200", ncm: "3920.62.99", cfop: "1102", unit: "m²",
    stock: 520, min: 200, max: 1500, cost: 7.50, prices: { varejo: 0, atacado: 0, balcao: 0 },
    margin: 0, lead: 7, pop: 0, active: true, soldMonth: 0, lastSale: "—",
    bom: null, variations: null, mov: null },
  { id: "SRV-005", name: "Instalação banner externo", cat: "Serviço", type: "servico",
    sku: "SRV-005", ncm: "-", cfop: "-", unit: "serv",
    stock: null, min: null, max: null, cost: 45, prices: { varejo: 150, atacado: 120, balcao: 135 },
    margin: 233, lead: 1, pop: 58, active: true, soldMonth: 22, lastSale: "hoje",
    bom: null, variations: null, mov: null },
  { id: "PROD-099", name: "Adesivo de parede 1×1m", cat: "Comunicação Visual", type: "simples",
    sku: "ADE-1x1", ncm: "4911.10.90", cfop: "5101", unit: "un",
    stock: 38, min: 20, max: 120, cost: 22, prices: { varejo: 89, atacado: 68, balcao: 78 },
    margin: 305, lead: 2, pop: 24, active: false, soldMonth: 0, lastSale: "15/03",
    bom: null, variations: null, mov: null },
];

// ─── stock status helper ───────────────────────────────────────────────────────
function stkStatus(p) {
  if (p.stock === null) return { cls: "",     val: "—",      pct: 0,                              label: "serviço" };
  if (p.stock === 0)   return { cls: "err",  val: p.stock,  pct: 0,                              label: "zerado"  };
  if (p.stock < p.min) return { cls: "warn", val: p.stock,  pct: Math.min(100,(p.stock/p.max)*100), label: "baixo" };
  return                       { cls: "",     val: p.stock,  pct: Math.min(100,(p.stock/p.max)*100), label: "ok"    };
}

// ─── Drawer ────────────────────────────────────────────────────────────────────
function DrawerView({ p, tab, setTab, close }) {
  const tabs = [
    { id: "resumo",     l: "Resumo"     },
    { id: "composicao", l: "Composição", ct: p.bom?.length || 0,                                          hide: !p.bom },
    { id: "variacoes",  l: "Variações",  ct: p.variations ? p.variations.rows.length * p.variations.cols.length : 0, hide: !p.variations },
    { id: "precos",     l: "Preços"     },
    { id: "movimento",  l: "Movimento",  ct: p.mov?.length || 0,                                          hide: !p.mov },
    { id: "fiscal",     l: "Fiscal"     },
  ].filter(t => !t.hide);

  const stockLabel =
    p.stock === null ? "—" :
    p.stock === 0    ? <span style={{ color: "var(--err)" }}>ZERADO</span> :
    p.stock < p.min  ? <span style={{ color: "var(--warn)" }}>BAIXO</span> :
                       <span style={{ color: "var(--ok)" }}>OK</span>;

  return (
    <aside className="drawer">
      {/* Head */}
      <div className="drw-head">
        <div className="big-thumb" style={{ background: CAT_COLOR[p.cat] }}>{CAT_INI[p.cat]}</div>
        <div style={{ flex: 1, minWidth: 0 }}>
          <h2>{p.name}</h2>
          <div className="meta">{p.cat} · {p.unit}</div>
          <span className="mono">{p.id} · SKU {p.sku}</span>
        </div>
        <button className="x" onClick={close}>✕</button>
      </div>

      {/* Hero KPIs */}
      <div className="hero-kpis">
        <div className="hero-kpi">
          <small>Estoque</small>
          <b>{p.stock !== null ? p.stock : "—"}</b>
          <div style={{ fontSize: 9.5, marginTop: 1, color: "var(--ink-3)" }}>{stockLabel}</div>
        </div>
        <div className="hero-kpi">
          <small>Custo</small>
          <b style={{ color: "var(--warn)" }}>{fmt(p.cost)}</b>
          <div style={{ fontSize: 9.5, marginTop: 1, color: "var(--ink-3)" }}>/{p.unit}</div>
        </div>
        <div className="hero-kpi">
          <small>Preço varejo</small>
          <b>{fmt(p.prices.varejo)}</b>
          <div style={{ fontSize: 9.5, marginTop: 1, color: "var(--ok)" }}>+{p.margin}% margem</div>
        </div>
        <div className="hero-kpi">
          <small>Vendas no mês</small>
          <b>{p.soldMonth}</b>
          <div style={{ fontSize: 9.5, marginTop: 1, color: "var(--ink-3)" }}>últ: {p.lastSale}</div>
        </div>
      </div>

      {/* Tabs */}
      <div className="drw-tabs">
        {tabs.map(t => (
          <button key={t.id} className={tab === t.id ? "active" : ""} onClick={() => setTab(t.id)}>
            {t.l}{t.ct != null && <span className="ct">{t.ct}</span>}
          </button>
        ))}
      </div>

      {/* Body */}
      <div className="drw-body">

        {/* ── RESUMO ── */}
        {tab === "resumo" && (
          <>
            <div className="sec">
              <h4>Identificação</h4>
              <div className="card">
                <div className="field-grid">
                  <div className="f"><label>Código interno</label><span className="mono">{p.id}</span></div>
                  <div className="f"><label>SKU comercial</label><span className="mono">{p.sku}</span></div>
                  <div className="f"><label>Categoria</label><span>{p.cat}</span></div>
                  <div className="f"><label>Unidade</label><span>{p.unit}</span></div>
                  <div className="f"><label>Tipo</label><span style={{ textTransform: "capitalize" }}>{p.type}</span></div>
                  <div className="f"><label>Lead time</label><span className="mono">{p.lead} dia{p.lead === 1 ? "" : "s"}</span></div>
                </div>
              </div>
            </div>
            <div className="sec">
              <h4>Estoque</h4>
              <div className="card">
                {p.stock !== null ? (
                  <div className="field-grid">
                    <div className="f">
                      <label>Atual</label>
                      <span className="mono" style={{ fontWeight: 700, fontSize: 14 }}>{p.stock} {p.unit}</span>
                    </div>
                    <div className="f">
                      <label>Mínimo / Máximo</label>
                      <span className="mono">{p.min} / {p.max}</span>
                    </div>
                    <div className="f full">
                      <label>Faixa de reposição</label>
                      <div style={{ height: 7, background: "var(--line-2)", borderRadius: 99, marginTop: 4, position: "relative", overflow: "visible" }}>
                        <div style={{ position: "absolute", left: `${(p.min / p.max) * 100}%`, top: -3, height: 13, width: 1, background: "var(--warn)" }} title="mínimo" />
                        <div style={{ height: "100%", width: `${Math.min(100, (p.stock / p.max) * 100)}%`, background: p.stock < p.min ? "var(--warn)" : "var(--ok)", borderRadius: 99 }} />
                      </div>
                      <small style={{ display: "block", marginTop: 4, color: "var(--ink-3)", fontSize: 10.5, fontFamily: "var(--mono)" }}>
                        0 ─── mín {p.min} ─── {p.max}
                      </small>
                    </div>
                  </div>
                ) : (
                  <div style={{ color: "var(--ink-3)", fontSize: 12 }}>Serviço · sem controle de estoque</div>
                )}
              </div>
            </div>
            <div className="sec">
              <h4>Popularidade</h4>
              <div className="card">
                <div style={{ display: "flex", alignItems: "center", gap: 10, fontSize: 12 }}>
                  <div style={{ flex: 1, height: 7, background: "var(--line-2)", borderRadius: 99, overflow: "hidden" }}>
                    <div style={{ height: "100%", width: `${p.pop}%`, background: "var(--accent)", borderRadius: 99 }} />
                  </div>
                  <b style={{ fontFamily: "var(--mono)", fontWeight: 700 }}>{p.pop}%</b>
                </div>
                <small style={{ fontSize: 10.5, color: "var(--ink-3)", marginTop: 5, display: "block" }}>
                  posição entre os mais vendidos do mês
                </small>
              </div>
            </div>
          </>
        )}

        {/* ── COMPOSIÇÃO (BOM) ── */}
        {tab === "composicao" && p.bom && (
          <div className="sec">
            <h4>Ficha técnica <span className="badge">{p.bom.length} insumos</span></h4>
            <table className="bom-tbl">
              <thead><tr>
                <th>Insumo</th>
                <th className="num">Qtd</th>
                <th className="num">Custo unit.</th>
                <th className="num">Total</th>
              </tr></thead>
              <tbody>
                {p.bom.map((item, k) => (
                  <tr key={k}>
                    <td><b>{item.name}</b><small>{item.sku}</small></td>
                    <td className="num">{item.qty}<small style={{ textAlign: "right" }}>{item.unit}</small></td>
                    <td className="num">{fmt(item.cost)}</td>
                    <td className="num"><b>{fmt(item.total)}</b></td>
                  </tr>
                ))}
                <tr style={{ background: "#fbf9f3", fontWeight: 700 }}>
                  <td colSpan="3" style={{ textAlign: "right", padding: "8px" }}>Custo total apurado</td>
                  <td className="num">
                    <b style={{ color: "var(--warn)", fontSize: 13 }}>
                      {fmt(p.bom.reduce((s, i) => s + i.total, 0))}
                    </b>
                  </td>
                </tr>
              </tbody>
            </table>
            <div style={{ marginTop: 8, padding: 8, background: "var(--info-soft)", border: "1px solid #c2d6ea", borderRadius: 5, fontSize: 11, color: "var(--info)" }}>
              <b>ℹ Apurado vs cadastrado:</b>{" "}
              custo apurado {fmt(p.bom.reduce((s, i) => s + i.total, 0))} · custo cadastrado {fmt(p.cost)} · diferença{" "}
              {fmt(Math.abs(p.bom.reduce((s, i) => s + i.total, 0) - p.cost))}
            </div>
          </div>
        )}

        {/* ── VARIAÇÕES ── */}
        {tab === "variacoes" && p.variations && (
          <div className="sec">
            <h4>Matriz {p.variations.rows.length} × {p.variations.cols.length}</h4>
            <table className="matrix">
              <thead>
                <tr>
                  <th></th>
                  {p.variations.cols.map(c => <th key={c}>{c}</th>)}
                  <th style={{ background: "var(--accent-soft)", color: "var(--accent)" }}>Total</th>
                </tr>
              </thead>
              <tbody>
                {p.variations.rows.map((r, i) => {
                  const rowSum = p.variations.grid[i].reduce((a, b) => a + b, 0);
                  return (
                    <tr key={r}>
                      <td>{r}</td>
                      {p.variations.grid[i].map((v, j) => (
                        <td key={j} className={v === 0 ? "zero" : v < 3 ? "low" : ""}>{v}</td>
                      ))}
                      <td style={{ background: "var(--accent-soft)", color: "var(--accent)", fontWeight: 700 }}>{rowSum}</td>
                    </tr>
                  );
                })}
                <tr style={{ background: "var(--accent-soft)" }}>
                  <td style={{ color: "var(--accent)" }}>Total</td>
                  {p.variations.cols.map((c, j) => {
                    const colSum = p.variations.grid.reduce((s, row) => s + row[j], 0);
                    return <td key={j} style={{ color: "var(--accent)", fontWeight: 700 }}>{colSum}</td>;
                  })}
                  <td style={{ color: "var(--accent)", fontWeight: 700, background: "#d9e3f0" }}>
                    {p.variations.grid.flat().reduce((a, b) => a + b, 0)}
                  </td>
                </tr>
              </tbody>
            </table>
            <small style={{ display: "block", marginTop: 6, fontSize: 10.5, color: "var(--ink-3)" }}>
              células vermelhas = sem estoque · laranja = abaixo de 3 un
            </small>
          </div>
        )}

        {/* ── PREÇOS ── */}
        {tab === "precos" && (
          <>
            <div className="sec">
              <h4>Tabelas de preço</h4>
              <div className="price-cards">
                <div className="price-card cost">
                  <small>Custo</small><b>{fmt(p.cost)}</b>
                  <div className="margin" style={{ color: "var(--ink-3)" }}>base p/ margens</div>
                </div>
                <div className="price-card">
                  <small>Atacado</small><b>{fmt(p.prices.atacado)}</b>
                  <div className="margin">+{Math.round((p.prices.atacado / p.cost - 1) * 100)}%</div>
                </div>
                <div className="price-card">
                  <small>Balcão</small><b>{fmt(p.prices.balcao)}</b>
                  <div className="margin">+{Math.round((p.prices.balcao / p.cost - 1) * 100)}%</div>
                </div>
              </div>
              <div className="price-cards" style={{ marginTop: 8 }}>
                <div className="price-card" style={{ background: "var(--accent-soft)", borderColor: "#c9d6e8" }}>
                  <small style={{ color: "var(--accent)" }}>Varejo · padrão</small>
                  <b style={{ color: "var(--accent)" }}>{fmt(p.prices.varejo)}</b>
                  <div className="margin">+{p.margin}%</div>
                </div>
                <div className="price-card" style={{ opacity: 0.55, borderStyle: "dashed" }}>
                  <small>Promocional</small><b>—</b>
                  <div className="margin" style={{ color: "var(--ink-3)" }}>não definido</div>
                </div>
                <div className="price-card" style={{ opacity: 0.55, borderStyle: "dashed" }}>
                  <small>Cliente VIP</small><b>—</b>
                  <div className="margin" style={{ color: "var(--ink-3)" }}>não definido</div>
                </div>
              </div>
            </div>
            <div className="sec">
              <h4>Calculadora rápida</h4>
              <div className="card">
                <div className="field-grid">
                  <div className="f"><label>Custo</label><span className="mono" style={{ fontSize: 14, fontWeight: 700 }}>{fmt(p.cost)}</span></div>
                  <div className="f"><label>Markup desejado</label><span className="mono">2,5×</span></div>
                  <div className="f"><label>Preço calculado</label><span className="mono" style={{ fontSize: 14, fontWeight: 700, color: "var(--ok)" }}>{fmt(p.cost * 2.5)}</span></div>
                  <div className="f"><label>Margem efetiva</label><span style={{ color: "var(--ok)", fontWeight: 600 }}>+150%</span></div>
                </div>
              </div>
            </div>
          </>
        )}

        {/* ── MOVIMENTO ── */}
        {tab === "movimento" && p.mov && (
          <div className="sec">
            <h4>Últimos {p.mov.length} movimentos</h4>
            <div className="card" style={{ background: "#fff" }}>
              {p.mov.map((m, k) => (
                <div className="mov-row" key={k}>
                  <div className={`mov-icon ${m.t}`}>{m.t === "in" ? "+" : m.t === "out" ? "−" : "±"}</div>
                  <div>
                    <b>{m.title}</b>
                    <small>{m.who}</small>
                  </div>
                  <span className="date">{m.at}</span>
                  <span className={`qty ${m.t === "in" ? "in" : "out"}`}>
                    {m.qty > 0 ? "+" : ""}{m.qty}
                    <small style={{ display: "block", fontSize: 9.5, color: "var(--ink-3)", fontWeight: 400 }}>→ {m.after}</small>
                  </span>
                </div>
              ))}
            </div>
            <div style={{ marginTop: 8, display: "flex", gap: 6 }}>
              <button className="btn sm">+ Ajuste de estoque</button>
              <button className="btn sm">Ver todos</button>
            </div>
          </div>
        )}

        {/* ── FISCAL ── */}
        {tab === "fiscal" && (
          <>
            <div className="sec">
              <h4>Classificação fiscal</h4>
              <div className="card">
                <div className="field-grid">
                  <div className="f"><label>NCM</label><span className="mono" style={{ fontWeight: 700 }}>{p.ncm}</span></div>
                  <div className="f"><label>CFOP saída</label><span className="mono">{p.cfop}</span></div>
                  <div className="f"><label>CEST</label><span className="mono" style={{ color: "var(--ink-3)" }}>—</span></div>
                  <div className="f"><label>Origem</label><span>0 · Nacional</span></div>
                  <div className="f"><label>Cód. serviço LC 116</label><span className="mono" style={{ color: "var(--ink-3)" }}>{p.type === "servico" ? "13.05" : "n/a"}</span></div>
                  <div className="f"><label>Cód. tributação mun.</label><span className="mono" style={{ color: "var(--ink-3)" }}>—</span></div>
                </div>
              </div>
            </div>
            <div className="sec">
              <h4>Tributação (Simples Nacional)</h4>
              <div className="card">
                <div className="field-grid">
                  <div className="f"><label>CSOSN</label><span className="mono">102 · sem permissão crédito</span></div>
                  <div className="f"><label>Alíq. efetiva DAS</label><span className="mono">5,47%</span></div>
                  <div className="f"><label>PIS / COFINS</label><span style={{ color: "var(--ink-3)", fontSize: 11 }}>recolhido via DAS</span></div>
                  <div className="f"><label>ICMS-ST</label><span style={{ color: "var(--ink-3)", fontSize: 11 }}>não se aplica</span></div>
                </div>
              </div>
            </div>
            <div className="sec">
              <div style={{ padding: 10, background: "var(--warn-soft)", border: "1px solid #f1d499", borderRadius: 5, fontSize: 11.5, color: "var(--warn)" }}>
                <b>⚠ Reforma tributária 2026</b>
                <div style={{ color: "var(--ink-2)", marginTop: 3, fontSize: 11 }}>
                  A partir de jan/2027 este produto passa a operar com CBS (alíq. teste 0,9%) e IBS (0,1%) em fase de transição. Cadastro fiscal pode precisar revisão.
                </div>
              </div>
            </div>
          </>
        )}
      </div>

      {/* Footer */}
      <div className="drw-foot">
        <button className="btn ghost">Duplicar</button>
        <button className="btn ghost">Inativar</button>
        <div style={{ flex: 1 }} />
        <button className="btn">Editar fiscal</button>
        <button className="btn primary">Editar produto →</button>
      </div>
    </aside>
  );
}

// ─── Main page ─────────────────────────────────────────────────────────────────
function ProdutosCockpit() {
  const [selId,   setSel]    = useState("PROD-018");
  const [tab,     setTab]    = useState("resumo");
  const [filter,  setFilter] = useState("ativos");
  const [query,   setQuery]  = useState("");

  const filtered = useMemo(() => {
    let list = PRODS;
    if (filter === "ativos")   list = list.filter(p => p.active);
    else if (filter === "baixo")    list = list.filter(p => p.stock !== null && p.stock < p.min);
    else if (filter === "zerado")   list = list.filter(p => p.stock === 0);
    else if (filter === "servicos") list = list.filter(p => p.type === "servico");
    else if (filter === "insumos")  list = list.filter(p => p.type === "insumo");
    if (query.trim()) {
      const q = query.toLowerCase();
      list = list.filter(p =>
        p.name.toLowerCase().includes(q) ||
        p.id.toLowerCase().includes(q) ||
        p.sku.toLowerCase().includes(q) ||
        p.ncm.toLowerCase().includes(q)
      );
    }
    return list;
  }, [filter, query]);

  const selected = PRODS.find(p => p.id === selId) || null;

  const kpi = useMemo(() => ({
    total:  PRODS.length,
    valor:  PRODS.reduce((s, p) => s + (p.stock || 0) * p.cost, 0),
    baixo:  PRODS.filter(p => p.stock !== null && p.stock < p.min).length,
    margem: Math.round(
      PRODS.filter(p => p.margin > 0).reduce((s, p) => s + p.margin, 0) /
      PRODS.filter(p => p.margin > 0).length
    ),
  }), []);

  const filteredValor   = filtered.reduce((s, p) => s + (p.stock || 0) * p.cost, 0);
  const filteredMargem  = Math.round(
    filtered.filter(p => p.margin > 0).reduce((s, p) => s + p.margin, 0) /
    Math.max(1, filtered.filter(p => p.margin > 0).length)
  );

  function selectProd(id) {
    setSel(id);
    setTab("resumo");
  }

  return (
    <div className="app">
      {/* ── SIDEBAR ── */}
      <aside className="sb">
        <div className="brand">
          <b>Oimpresso</b>
          <small>ERP · Rota Livre</small>
        </div>
        <h4>Operação</h4>
        <a><span className="ic">▢</span>Dashboard</a>
        <a><span className="ic">↗</span>Vendas <span className="ct">82</span></a>
        <a><span className="ic">↙</span>Compras <span className="ct">7</span></a>
        <a className="active"><span className="ic">▣</span>Produtos <span className="ct">{PRODS.length}</span></a>
        <a><span className="ic">⏵</span>OS · Produção <span className="ct">23</span></a>
        <a><span className="ic">$</span>Financeiro</a>
        <h4>Cadastros</h4>
        <a><span className="ic">●</span>Clientes</a>
        <a><span className="ic">○</span>Fornecedores <span className="ct">4</span></a>
        <a><span className="ic">⚙</span>Configurações</a>
        <div className="biz">
          <b>Matriz · Larissa</b>
          regime simples nacional<br />04.123.456/0001-78
        </div>
      </aside>

      {/* ── MAIN ── */}
      <div className="main">
        {/* Header */}
        <header className="hd">
          <div className="crumbs">ERP · Operação · <b style={{ color: "var(--ink-2)" }}>Produtos</b></div>
          <h1>Produtos</h1>
          <span className="count">{filtered.length} de {PRODS.length}</span>
          <div className="sp" />
          <div className="search">
            <span>⌕</span>
            <input
              placeholder="Buscar nome, SKU, NCM, categoria..."
              value={query}
              onChange={e => setQuery(e.target.value)}
            />
            <kbd style={{ fontSize: 9, fontFamily: "var(--mono)", color: "var(--ink-3)", background: "var(--line-2)", padding: "1px 5px", borderRadius: 3 }}>/</kbd>
          </div>
          <button className="btn">↑ Importar planilha</button>
          <button className="btn primary">+ Novo produto</button>
        </header>

        {/* Filter tabs */}
        <nav className="tbs">
          {[
            { id: "all",      l: "Todos",        count: PRODS.length },
            { id: "ativos",   l: "Ativos",       count: PRODS.filter(p => p.active).length },
            { id: "baixo",    l: "Estoque baixo", count: PRODS.filter(p => p.stock !== null && p.stock < p.min).length },
            { id: "zerado",   l: "Zerados",      count: PRODS.filter(p => p.stock === 0).length },
            { id: "servicos", l: "Serviços",     count: PRODS.filter(p => p.type === "servico").length },
            { id: "insumos",  l: "Insumos",      count: PRODS.filter(p => p.type === "insumo").length },
          ].map(t => (
            <a key={t.id} className={filter === t.id ? "active" : ""} onClick={() => setFilter(t.id)}>
              {t.l} <span className="ct">{t.count}</span>
            </a>
          ))}
          <div className="sp" />
          <div className="filters">
            <span style={{ fontSize: 10.5, textTransform: "uppercase", letterSpacing: ".04em", color: "var(--ink-3)" }}>filtros</span>
            <span className="filter-pill on">Categoria: todas</span>
            <span className="filter-pill">Margem &gt; 100%</span>
            <span className="filter-pill">Mais vendidos</span>
          </div>
        </nav>

        {/* Body: list + drawer */}
        <div className={`bd ${selected ? "with-drawer" : ""}`}>
          <main className="list">
            {/* KPI strip */}
            <div className="kpis">
              <div className="kpi">
                <small>Total SKUs</small>
                <b>{kpi.total}</b>
                <div className="ln">{PRODS.filter(p => p.active).length} ativos · {PRODS.filter(p => !p.active).length} inativo</div>
              </div>
              <div className="kpi ok">
                <small>Valor em estoque</small>
                <b>{fmt(kpi.valor)}</b>
                <div className="ln">a custo · gira 2,3×/mês</div>
              </div>
              <div className="kpi warn">
                <small>Estoque baixo</small>
                <b>{kpi.baixo}</b>
                <div className="ln">abaixo do mínimo · ação requerida</div>
              </div>
              <div className="kpi">
                <small>Margem média</small>
                <b>{kpi.margem}%</b>
                <div className="ln">acima da meta de 120% · subindo</div>
              </div>
            </div>

            {/* Table */}
            <div className="tbl">
              <table className="prods">
                <thead>
                  <tr>
                    <th>Produto</th>
                    <th style={{ width: 100 }}>SKU</th>
                    <th style={{ width: 130 }}>Categoria</th>
                    <th style={{ width: 110 }}>Estoque</th>
                    <th style={{ width: 95, textAlign: "right" }}>Custo</th>
                    <th style={{ width: 95, textAlign: "right" }}>Preço</th>
                    <th style={{ width: 75, textAlign: "right" }}>Margem</th>
                    <th style={{ width: 70 }}>Status</th>
                  </tr>
                </thead>
                <tbody>
                  {filtered.map(p => {
                    const s = stkStatus(p);
                    return (
                      <tr
                        key={p.id}
                        className={selId === p.id ? "sel" : ""}
                        onClick={() => selectProd(p.id)}
                      >
                        <td>
                          <div className="prod-cell">
                            <div className="thumb" style={{ background: CAT_COLOR[p.cat] }}>{CAT_INI[p.cat]}</div>
                            <div className="info">
                              <b>{p.name}</b>
                              <small>
                                {p.id}
                                {p.type === "composto" && " · composto"}
                                {p.type === "servico"  && " · serviço"}
                                {p.type === "insumo"   && " · insumo"}
                              </small>
                            </div>
                          </div>
                        </td>
                        <td className="mono">{p.sku}</td>
                        <td><span style={{ fontSize: 11, color: "var(--ink-2)" }}>{p.cat}</span></td>
                        <td>
                          {p.stock !== null ? (
                            <div className={`stk-bar ${s.cls}`}>
                              <span style={{ minWidth: 32, textAlign: "right" }}>{s.val}</span>
                              <div className="bar"><i style={{ width: s.pct + "%" }} /></div>
                              <small style={{ color: "var(--ink-3)", fontSize: 10 }}>/{p.max}</small>
                            </div>
                          ) : (
                            <small style={{ color: "var(--ink-3)" }}>—</small>
                          )}
                        </td>
                        <td className="num">{fmt(p.cost)}</td>
                        <td className="num"><b>{fmt(p.prices.varejo)}</b></td>
                        <td className="num" style={{ color: p.margin > 0 ? "var(--ok)" : "var(--ink-3)", fontWeight: 600 }}>
                          {p.margin > 0 ? `+${p.margin}%` : "—"}
                        </td>
                        <td>
                          {!p.active       ? <span className="pill inativo">Inativo</span>  :
                           p.type === "servico"  ? <span className="pill servico">Serviço</span>  :
                           p.type === "composto" ? <span className="pill composto">Composto</span> :
                                                   <span className="pill ativo">Ativo</span>}
                        </td>
                      </tr>
                    );
                  })}
                  {filtered.length === 0 && (
                    <tr>
                      <td colSpan="8" style={{ textAlign: "center", padding: "28px 0", color: "var(--ink-3)" }}>
                        Nenhum produto encontrado
                      </td>
                    </tr>
                  )}
                </tbody>
              </table>
            </div>
          </main>

          {selected && (
            <DrawerView
              p={selected}
              tab={tab}
              setTab={setTab}
              close={() => setSel(null)}
            />
          )}
        </div>

        {/* Footer */}
        <footer className="ft">
          <b>{filtered.length}</b> produtos · estoque <b>{fmt(filteredValor)}</b> · margem média{" "}
          <b style={{ color: "var(--ok)" }}>+{filteredMargem}%</b>
          <div className="sp" />
          <span>Atalhos: <kbd>/</kbd> buscar · <kbd>N</kbd> novo · <kbd>↑↓</kbd> navegar · <kbd>Esc</kbd> fechar</span>
        </footer>
      </div>
    </div>
  );
}

window.ProdutosCockpit = ProdutosCockpit;
