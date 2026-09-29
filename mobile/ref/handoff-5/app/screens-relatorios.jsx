// ────────────────────────────────────────────────
// RELATÓRIOS — relatórios distintos, calculados sobre os dados reais
// ────────────────────────────────────────────────
const { Ic: IcRp } = window;
const MRp = window.MOCK;
const { DetailHeader: DHrp } = window.OIUi;
const BRLrp = window.BRL;
const BRLcp = window.BRLcompact;
function brl2(n) { return (n < 0 ? "-" : "") + "R$ " + Math.abs(n).toLocaleString("pt-BR", { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }

// ─── Cálculos a partir dos dados ───
function rpData() {
  const L = MRp.FIN_LANC, G = MRp.FIN_GRUPO;
  const recebidas = L.filter(l => l.tipo === "receber" && l.status === "liquidado");
  const pagas     = L.filter(l => l.tipo === "pagar"   && l.status === "liquidado");
  const aReceber  = L.filter(l => l.tipo === "receber" && l.status !== "liquidado");
  const aPagar    = L.filter(l => l.tipo === "pagar"   && l.status !== "liquidado");
  const sum = a => a.reduce((s, l) => s + l.valor, 0);

  // receita por categoria (recebidas)
  const porCat = {};
  recebidas.forEach(l => { porCat[l.categoria] = (porCat[l.categoria] || 0) + l.valor; });
  const receitaCat = Object.entries(porCat).map(([k, v]) => ({ k, v })).sort((a, b) => b.v - a.v);

  // DRE
  const receita = sum(recebidas);
  const custos = sum(pagas.filter(l => G[l.categoria] === "custo"));
  const despesas = sum(pagas.filter(l => G[l.categoria] === "despesa"));
  const lucroBruto = receita - custos;
  const resultado = lucroBruto - despesas;

  // aging
  const bucket = (d) => d < 0 ? "venc" : d <= 7 ? "b7" : d <= 15 ? "b15" : d <= 30 ? "b30" : "b30p";
  const agingOf = (arr) => {
    const o = { venc: 0, b7: 0, b15: 0, b30: 0, b30p: 0 };
    arr.forEach(l => { o[bucket(l.vencDias)] += l.valor; });
    return o;
  };

  // por parte (faturamento total = aberto+liquidado receber)
  const porParte = {};
  L.filter(l => l.tipo === "receber").forEach(l => {
    porParte[l.parte] = porParte[l.parte] || { nome: l.parte, fat: 0, venc: 0 };
    porParte[l.parte].fat += l.valor;
    if (l.status === "vencido") porParte[l.parte].venc += l.valor;
  });
  const clientes = Object.values(porParte).sort((a, b) => b.fat - a.fat);
  const inadimplentes = clientes.filter(c => c.venc > 0).sort((a, b) => b.venc - a.venc);

  // oficina
  const OS = MRp.OFICINA_OS || [];
  const statusCount = {};
  OS.forEach(o => { statusCount[o.status] = (statusCount[o.status] || 0) + 1; });
  const porTipoManut = {};
  OS.forEach(o => { porTipoManut[o.tipoManut] = (porTipoManut[o.tipoManut] || 0) + MRp.osTotal(o); });
  const porMec = (MRp.OFICINA_MECANICOS || []).map(m => {
    const os = OS.filter(o => o.mecanico === m.id);
    return { nome: m.nome, os: os.length, valor: os.reduce((s, o) => s + MRp.osTotal(o), 0) };
  }).sort((a, b) => b.valor - a.valor);

  // estoque
  const baixa = (MRp.PRODUTOS || []).filter(p => p.estoque < p.min)
    .map(p => ({ nome: p.nome, estoque: p.estoque, min: p.min, un: p.un }));
  (MRp.OFICINA_CATALOGO || []).filter(c => c.tipo === "peca" && c.estoque === 0)
    .forEach(c => baixa.push({ nome: c.nome, estoque: 0, min: 1, un: c.un }));
  const valorEstoque = (MRp.PRODUTOS || []).reduce((s, p) => s + p.estoque * p.preco, 0)
    + (MRp.OFICINA_CATALOGO || []).filter(c => c.tipo === "peca").reduce((s, c) => s + c.estoque * c.preco, 0);

  return {
    receita, custos, despesas, lucroBruto, resultado, receitaCat, recebidas, pagas,
    aReceberTot: sum(aReceber), aPagarTot: sum(aPagar),
    agingReceber: agingOf(aReceber), agingPagar: agingOf(aPagar),
    clientes, inadimplentes, statusCount, porTipoManut, porMec, baixa, valorEstoque,
    ticket: receita / Math.max(1, recebidas.length),
  };
}

const REL_DEFS = [
  { id: "faturamento", kind: "faturamento", titulo: "Faturamento", ic: "trending-up", desc: "Receita por categoria e período", cor: "var(--ok)" },
  { id: "fluxo",       kind: "fluxo",       titulo: "Fluxo de caixa", ic: "dollar",    desc: "Entradas × saídas e projeção", cor: "var(--accent)" },
  { id: "dre",         kind: "dre",         titulo: "DRE gerencial", ic: "chart",       desc: "Receita − custos − despesas", cor: "var(--accent)" },
  { id: "vencimentos", kind: "aging",       titulo: "Vencimentos", ic: "calendar",      desc: "A receber e a pagar por faixa", cor: "var(--warn)" },
  { id: "inadimplencia", kind: "inad",      titulo: "Inadimplência & clientes", ic: "user", desc: "Devedores e ranking de faturamento", cor: "var(--danger)" },
  { id: "oficina",     kind: "oficina",     titulo: "Oficina & produção", ic: "wrench",  desc: "OS por status e produtividade", cor: "var(--origin-OFI-fg)" },
  { id: "estoque",     kind: "estoque",     titulo: "Estoque", ic: "box",                desc: "Reposição e valor parado", cor: "var(--text-dim)" },
];

// ─── helpers visuais ───
function RankBars({ rows, max, fmt, color }) {
  return (
    <div className="oi-card pad" style={{ gap: 12 }}>
      {rows.map((r, i) => (
        <div key={i} style={{ display: "flex", flexDirection: "column", gap: 5 }}>
          <div style={{ display: "flex", fontSize: 12.5, gap: 8 }}>
            <span style={{ fontWeight: 500, flex: 1, minWidth: 0, whiteSpace: "nowrap", overflow: "hidden", textOverflow: "ellipsis" }}>{r.label}</span>
            <span className="oi-money" style={{ color: "var(--text-dim)", fontWeight: 600 }}>{fmt(r.value)}</span>
          </div>
          <div style={{ height: 7, borderRadius: 99, background: "var(--bg-2)", overflow: "hidden" }}>
            <div style={{ width: (r.value / max * 100) + "%", height: "100%", background: r.color || color || "var(--accent)", borderRadius: 99 }} />
          </div>
        </div>
      ))}
    </div>
  );
}

// ─── Lista de relatórios ───
function RelatoriosScreen({ nav, ctx }) {
  const [per, setPer] = React.useState("mes");
  const D = rpData();
  const FAT_7D = [2180, 1640, 2980, 2210, 3420, 1240, 1840];
  const dias = ["Seg", "Ter", "Qua", "Qui", "Sex", "Sáb", "Hoje"];
  const max7 = Math.max(...FAT_7D);
  const kpiOf = (kind) => {
    if (kind === "faturamento") return brl2(D.receita).replace(",00", "");
    if (kind === "fluxo") return (D.resultado >= 0 ? "+" : "") + BRLcp(D.resultado);
    if (kind === "dre") return ((D.resultado / Math.max(1, D.receita)) * 100).toFixed(0) + "%";
    if (kind === "aging") return BRLcp(D.aReceberTot);
    if (kind === "inad") return BRLcp(D.inadimplentes.reduce((s, c) => s + c.venc, 0));
    if (kind === "oficina") return (MRp.OFICINA_OS || []).length + " OS";
    if (kind === "estoque") return D.baixa.length + " baixo";
    return "";
  };

  return (
    <>
      <DHrp nav={nav} title="Relatórios" eyebrow="Indicadores e exportações" />
      <div className="oi-head" style={{ paddingTop: 0, borderBottom: "1px solid var(--border)" }}>
        <div className="oi-chips">
          {[["hoje", "Hoje"], ["semana", "Semana"], ["mes", "Mês"], ["ano", "Ano"]].map(([v, l]) => (
            <button key={v} className={"oi-chip" + (per === v ? " on" : "")} onClick={() => setPer(v)}>{l}</button>
          ))}
        </div>
      </div>
      <div className="oi-scroll">
        {/* KPIs gerais */}
        <div className="oi-section">
          <div className="oi-kpis" style={{ gridTemplateColumns: "1fr 1fr" }}>
            <div className="oi-kpi"><small>Recebido (mês)</small><b className="oi-money" style={{ fontSize: 19 }}>{brl2(D.receita).replace(",00", "")}</b><span className="trend"><IcRp.trendingUp size={12} /> +12% vs mês ant.</span></div>
            <div className="oi-kpi"><small>Resultado</small><b className="oi-money" style={{ fontSize: 19, color: D.resultado >= 0 ? "var(--ok)" : "var(--danger)" }}>{brl2(D.resultado).replace(",00", "")}</b><span style={{ fontSize: 11, color: "var(--text-mute)", marginTop: 2 }}>margem {((D.resultado / D.receita) * 100).toFixed(0)}%</span></div>
            <div className="oi-kpi"><small>A receber</small><b className="oi-money" style={{ fontSize: 19 }}>{brl2(D.aReceberTot).replace(",00", "")}</b><span style={{ fontSize: 11, color: "var(--danger)", marginTop: 2 }}>{brl2(D.agingReceber.venc).replace(",00", "")} vencidos</span></div>
            <div className="oi-kpi"><small>Ticket médio</small><b className="oi-money" style={{ fontSize: 19 }}>{brl2(D.ticket).replace(",00", "")}</b><span className="trend"><IcRp.trendingUp size={12} /> +4%</span></div>
          </div>
        </div>

        {/* mini gráfico */}
        <div className="oi-section" style={{ paddingTop: 0 }}>
          <h3 className="oi-section-h">Recebido — últimos 7 dias</h3>
          <div className="oi-card pad">
            <div style={{ display: "flex", alignItems: "flex-end", gap: 8, height: 120 }}>
              {FAT_7D.map((v, i) => (
                <div key={i} style={{ flex: 1, display: "flex", flexDirection: "column", alignItems: "center", gap: 6, height: "100%", justifyContent: "flex-end" }}>
                  <span className="oi-mono" style={{ fontSize: 9, color: "var(--text-mute)" }}>{(v / 1000).toFixed(1)}k</span>
                  <div style={{ width: "100%", maxWidth: 26, height: (v / max7 * 86) + "px", borderRadius: 5, background: i === FAT_7D.length - 1 ? "var(--accent)" : "color-mix(in oklch, var(--accent) 38%, var(--bg-2))" }} />
                  <span style={{ fontSize: 9, color: "var(--text-mute)", fontWeight: 600 }}>{dias[i]}</span>
                </div>
              ))}
            </div>
          </div>
        </div>

        {/* lista de relatórios */}
        <div className="oi-section" style={{ paddingTop: 0 }}>
          <h3 className="oi-section-h">Relatórios</h3>
          <div className="oi-list card">
            {REL_DEFS.map(r => (
              <div key={r.id} className="oi-list-row" onClick={() => nav.push("relatorio", { id: r.id, kind: r.kind, titulo: r.titulo })}>
                <div style={{ width: 36, height: 36, borderRadius: 10, background: "color-mix(in oklch, " + r.cor + " 16%, transparent)", color: r.cor, display: "grid", placeItems: "center", flex: "0 0 auto" }}>
                  {React.createElement(IcRp[r.ic] || IcRp.chart, { size: 18 })}
                </div>
                <div style={{ flex: 1, minWidth: 0 }}>
                  <div style={{ fontSize: 13.5, fontWeight: 600 }}>{r.titulo}</div>
                  <div style={{ fontSize: 11, color: "var(--text-mute)" }}>{r.desc}</div>
                </div>
                <span className="oi-mono" style={{ fontSize: 12, fontWeight: 700, color: "var(--text-dim)" }}>{kpiOf(r.kind)}</span>
                <IcRp.chevR size={16} color="var(--text-mute)" />
              </div>
            ))}
          </div>
        </div>
        <div style={{ height: 24 }} />
      </div>
    </>
  );
}

// ─── Detalhe (renderiza por tipo) ───
function RelatorioDetalheScreen({ nav, ctx, params }) {
  const kind = (params && params.kind) || "faturamento";
  const titulo = (params && params.titulo) || "Relatório";
  const D = rpData();

  return (
    <>
      <DHrp nav={nav} title={titulo} eyebrow="Relatório · Mês atual"
            actions={<button className="oi-iconbtn" onClick={() => window.oiToast("Compartilhando relatório…")}><IcRp.send size={18} /></button>} />
      <div className="oi-scroll">
        {kind === "faturamento" && <RepFaturamento D={D} />}
        {kind === "fluxo" && <RepFluxo D={D} />}
        {kind === "dre" && <RepDre D={D} />}
        {kind === "aging" && <RepAging D={D} />}
        {kind === "inad" && <RepInad D={D} nav={nav} />}
        {kind === "oficina" && <RepOficina D={D} />}
        {kind === "estoque" && <RepEstoque D={D} />}

        <div className="oi-section">
          <div className="oi-btn-row">
            <button className="oi-btn" onClick={() => window.oiToast("Imprimindo relatório…")}><IcRp.printer size={16} /> Imprimir</button>
            <button className="oi-btn" onClick={() => window.oiToast("Exportando para PDF/Excel…")}><IcRp.file size={16} /> Exportar</button>
          </div>
        </div>
        <div style={{ height: 24 }} />
      </div>
    </>
  );
}

function BigStat({ label, value, tone }) {
  return (
    <div className="oi-card pad" style={{ gap: 2 }}>
      <div style={{ fontSize: 11.5, color: "var(--text-mute)", fontWeight: 600 }}>{label}</div>
      <div className="oi-money" style={{ fontSize: 26, fontWeight: 700, letterSpacing: "-0.02em", color: tone || "var(--text)" }}>{brl2(value).replace(",00", "")}</div>
    </div>
  );
}

function RepFaturamento({ D }) {
  const max = Math.max(...D.receitaCat.map(c => c.v), 1);
  return (
    <>
      <div className="oi-section"><BigStat label="Recebido no mês" value={D.receita} tone="var(--ok)" /></div>
      <div className="oi-section" style={{ paddingTop: 0 }}>
        <h3 className="oi-section-h">Por categoria</h3>
        <RankBars rows={D.receitaCat.map(c => ({ label: c.k, value: c.v }))} max={max} fmt={brl2} color="var(--ok)" />
      </div>
      <div className="oi-section" style={{ paddingTop: 0 }}>
        <h3 className="oi-section-h">Recebimentos</h3>
        <div className="oi-list card">
          {D.recebidas.map(l => (
            <div key={l.id} className="oi-list-row">
              <div style={{ flex: 1, minWidth: 0 }}><div style={{ fontSize: 13, fontWeight: 600 }}>{l.parte}</div><div style={{ fontSize: 11, color: "var(--text-mute)" }}>{l.categoria}</div></div>
              <span className="oi-money" style={{ fontSize: 13, fontWeight: 600, color: "var(--ok)" }}>+{brl2(l.valor).replace("R$ ", "")}</span>
            </div>
          ))}
        </div>
      </div>
    </>
  );
}

function RepFluxo({ D }) {
  const proj = D.recebidas.reduce((s, l) => s + l.valor, 0);
  return (
    <>
      <div className="oi-section">
        <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr", gap: 8 }}>
          <BigStat label="Entradas (mês)" value={D.receita} tone="var(--ok)" />
          <BigStat label="Saídas (mês)" value={D.custos + D.despesas} tone="var(--danger)" />
        </div>
      </div>
      <div className="oi-section" style={{ paddingTop: 0 }}>
        <h3 className="oi-section-h">Realizado × previsto</h3>
        <div className="oi-card pad" style={{ gap: 12 }}>
          {[["Entradas realizadas", D.receita, "var(--ok)"], ["Saídas realizadas", D.custos + D.despesas, "var(--danger)"],
            ["A receber (previsto)", D.aReceberTot, "color-mix(in oklch, var(--ok) 55%, var(--bg-2))"], ["A pagar (previsto)", D.aPagarTot, "color-mix(in oklch, var(--danger) 55%, var(--bg-2))"]].map(([l, v, c], i) => {
            const max = Math.max(D.receita, D.custos + D.despesas, D.aReceberTot, D.aPagarTot, 1);
            return (
              <div key={i} style={{ display: "flex", flexDirection: "column", gap: 5 }}>
                <div style={{ display: "flex", fontSize: 12.5 }}><span style={{ color: "var(--text-dim)" }}>{l}</span><span className="oi-money" style={{ marginLeft: "auto", fontWeight: 600 }}>{brl2(v).replace(",00", "")}</span></div>
                <div style={{ height: 7, borderRadius: 99, background: "var(--bg-2)", overflow: "hidden" }}><div style={{ width: (v / max * 100) + "%", height: "100%", background: c, borderRadius: 99 }} /></div>
              </div>
            );
          })}
        </div>
      </div>
      <div className="oi-section" style={{ paddingTop: 0 }}>
        <div className="oi-card pad" style={{ gap: 6 }}>
          <div style={{ display: "flex", fontSize: 13 }}><span style={{ color: "var(--text-dim)" }}>Saldo projetado</span><span className="oi-money" style={{ marginLeft: "auto", fontSize: 18, fontWeight: 700 }}>{brl2(MRp.finResumo().saldoProjetado).replace(",00", "")}</span></div>
          <div style={{ fontSize: 11, color: "var(--text-mute)" }}>Saldo atual + a receber − a pagar.</div>
        </div>
      </div>
    </>
  );
}

function RepDre({ D }) {
  const linhas = [
    { l: "Receita bruta", v: D.receita, b: true, c: "var(--ok)" },
    { l: "(−) Custos (CMV)", v: -D.custos },
    { l: "(=) Lucro bruto", v: D.lucroBruto, b: true, sub: ((D.lucroBruto / D.receita) * 100).toFixed(1) + "% da receita" },
    { l: "(−) Despesas operacionais", v: -D.despesas },
    { l: "(=) Resultado líquido", v: D.resultado, b: true, c: D.resultado >= 0 ? "var(--ok)" : "var(--danger)", sub: ((D.resultado / D.receita) * 100).toFixed(1) + "% margem líquida" },
  ];
  return (
    <>
      <div className="oi-section">
        <div className="oi-card pad" style={{ gap: 0 }}>
          {linhas.map((r, i) => (
            <div key={i} style={{ display: "flex", alignItems: "baseline", padding: "11px 0", borderBottom: i < linhas.length - 1 ? "1px solid var(--border-2)" : 0 }}>
              <div style={{ flex: 1 }}>
                <div style={{ fontSize: r.b ? 13.5 : 12.5, fontWeight: r.b ? 700 : 500, color: r.b ? "var(--text)" : "var(--text-dim)" }}>{r.l}</div>
                {r.sub && <div style={{ fontSize: 10.5, color: "var(--text-mute)" }}>{r.sub}</div>}
              </div>
              <span className="oi-money" style={{ fontSize: r.b ? 15 : 13, fontWeight: r.b ? 700 : 500, color: r.c || (r.v < 0 ? "var(--danger)" : "var(--text)") }}>{brl2(r.v).replace(",00", "")}</span>
            </div>
          ))}
        </div>
      </div>
      <div className="oi-section" style={{ paddingTop: 0 }}>
        <h3 className="oi-section-h">Receita por categoria</h3>
        <RankBars rows={D.receitaCat.map(c => ({ label: c.k, value: c.v }))} max={Math.max(...D.receitaCat.map(c => c.v), 1)} fmt={brl2} color="var(--ok)" />
      </div>
    </>
  );
}

function AgingBlock({ titulo, aging, tone }) {
  const faixas = [["venc", "Vencidos", "var(--danger)"], ["b7", "Até 7 dias", tone], ["b15", "8–15 dias", tone], ["b30", "16–30 dias", tone], ["b30p", "+30 dias", tone]];
  const max = Math.max(...Object.values(aging), 1);
  const tot = Object.values(aging).reduce((s, v) => s + v, 0);
  return (
    <div className="oi-section" style={{ paddingTop: 0 }}>
      <h3 className="oi-section-h">{titulo}<span className="oi-money" style={{ marginLeft: "auto", fontWeight: 700, textTransform: "none", letterSpacing: 0 }}>{brl2(tot).replace(",00", "")}</span></h3>
      <div className="oi-card pad" style={{ gap: 11 }}>
        {faixas.map(([k, l, c]) => (
          <div key={k} style={{ display: "flex", flexDirection: "column", gap: 5 }}>
            <div style={{ display: "flex", fontSize: 12 }}><span style={{ color: k === "venc" ? "var(--danger)" : "var(--text-dim)", fontWeight: k === "venc" ? 600 : 500 }}>{l}</span><span className="oi-money" style={{ marginLeft: "auto", fontWeight: 600 }}>{brl2(aging[k]).replace(",00", "")}</span></div>
            <div style={{ height: 6, borderRadius: 99, background: "var(--bg-2)", overflow: "hidden" }}><div style={{ width: (aging[k] / max * 100) + "%", height: "100%", background: c, borderRadius: 99 }} /></div>
          </div>
        ))}
      </div>
    </div>
  );
}
function RepAging({ D }) {
  return (
    <>
      <AgingBlock titulo="A receber" aging={D.agingReceber} tone="color-mix(in oklch, var(--ok) 55%, var(--bg-2))" />
      <AgingBlock titulo="A pagar" aging={D.agingPagar} tone="color-mix(in oklch, var(--danger) 50%, var(--bg-2))" />
    </>
  );
}

function RepInad({ D, nav }) {
  const maxFat = Math.max(...D.clientes.map(c => c.fat), 1);
  return (
    <>
      <div className="oi-section">
        <h3 className="oi-section-h">Inadimplência (vencido)</h3>
        {D.inadimplentes.length === 0
          ? <div className="oi-card pad" style={{ alignItems: "center", color: "var(--text-mute)", padding: 18 }}>Sem títulos vencidos 🎉</div>
          : <div className="oi-list card">{D.inadimplentes.map((c, i) => (
              <div key={i} className="oi-list-row">
                <div style={{ width: 30, height: 30, borderRadius: 8, flex: "0 0 auto", display: "grid", placeItems: "center", background: "color-mix(in oklch, var(--danger) 14%, transparent)", color: "var(--danger)" }}><IcRp.alert size={15} /></div>
                <div style={{ flex: 1, minWidth: 0 }}><div style={{ fontSize: 13, fontWeight: 600 }}>{c.nome}</div><div style={{ fontSize: 11, color: "var(--text-mute)" }}>faturamento {brl2(c.fat).replace(",00", "")}</div></div>
                <span className="oi-money" style={{ fontSize: 13, fontWeight: 700, color: "var(--danger)" }}>{brl2(c.venc).replace(",00", "")}</span>
              </div>
            ))}</div>}
      </div>
      <div className="oi-section" style={{ paddingTop: 0 }}>
        <h3 className="oi-section-h">Ranking de faturamento</h3>
        <RankBars rows={D.clientes.slice(0, 7).map(c => ({ label: c.nome, value: c.fat }))} max={maxFat} fmt={v => brl2(v).replace(",00", "")} color="var(--accent)" />
      </div>
    </>
  );
}

function RepOficina({ D }) {
  const statusList = Object.entries(D.statusCount).map(([k, v]) => ({ k, v }));
  const maxMec = Math.max(...D.porMec.map(m => m.valor), 1);
  const tipos = Object.entries(D.porTipoManut).map(([k, v]) => ({ k, v }));
  const maxTipo = Math.max(...tipos.map(t => t.v), 1);
  return (
    <>
      <div className="oi-section">
        <h3 className="oi-section-h">OS por status</h3>
        <div className="oi-card pad" style={{ display: "grid", gridTemplateColumns: "1fr 1fr", gap: 8 }}>
          {statusList.map(s => (
            <div key={s.k} style={{ background: "var(--bg-2)", borderRadius: 9, padding: "8px 10px", display: "flex", alignItems: "center", gap: 8 }}>
              <span className="oi-mono" style={{ fontSize: 18, fontWeight: 700 }}>{s.v}</span>
              <span style={{ fontSize: 11, color: "var(--text-dim)", lineHeight: 1.2 }}>{s.k}</span>
            </div>
          ))}
        </div>
      </div>
      <div className="oi-section" style={{ paddingTop: 0 }}>
        <h3 className="oi-section-h">Receita por tipo de manutenção</h3>
        <RankBars rows={tipos.map(t => ({ label: t.k, value: t.v }))} max={maxTipo} fmt={v => brl2(v).replace(",00", "")} color="var(--origin-OFI-fg)" />
      </div>
      <div className="oi-section" style={{ paddingTop: 0 }}>
        <h3 className="oi-section-h">Produtividade por mecânico</h3>
        <div className="oi-list card">
          {D.porMec.map((m, i) => (
            <div key={i} className="oi-list-row">
              <div style={{ flex: 1, minWidth: 0 }}><div style={{ fontSize: 13, fontWeight: 600 }}>{m.nome}</div><div style={{ fontSize: 11, color: "var(--text-mute)" }}>{m.os} OS no período</div></div>
              <span className="oi-money" style={{ fontSize: 13, fontWeight: 600 }}>{brl2(m.valor).replace(",00", "")}</span>
            </div>
          ))}
        </div>
      </div>
    </>
  );
}

function RepEstoque({ D }) {
  return (
    <>
      <div className="oi-section"><BigStat label="Valor parado em estoque" value={D.valorEstoque} /></div>
      <div className="oi-section" style={{ paddingTop: 0 }}>
        <h3 className="oi-section-h">Reposição necessária <span style={{ marginLeft: "auto", color: "var(--text-mute)", fontWeight: 500, textTransform: "none", letterSpacing: 0 }}>{D.baixa.length} itens</span></h3>
        {D.baixa.length === 0
          ? <div className="oi-card pad" style={{ alignItems: "center", color: "var(--text-mute)", padding: 18 }}>Estoque saudável.</div>
          : <div className="oi-list card">{D.baixa.map((p, i) => (
              <div key={i} className="oi-list-row">
                <div style={{ width: 30, height: 30, borderRadius: 8, flex: "0 0 auto", display: "grid", placeItems: "center", background: p.estoque === 0 ? "color-mix(in oklch, var(--danger) 14%, transparent)" : "color-mix(in oklch, var(--warn) 14%, transparent)", color: p.estoque === 0 ? "var(--danger)" : "var(--warn)" }}>
                  {React.createElement(p.estoque === 0 ? IcRp.alert : IcRp.box, { size: 15 })}
                </div>
                <div style={{ flex: 1, minWidth: 0 }}><div style={{ fontSize: 13, fontWeight: 600 }}>{p.nome}</div><div style={{ fontSize: 11, color: "var(--text-mute)" }}>mínimo {p.min} {p.un}</div></div>
                <span className="oi-mono" style={{ fontSize: 13, fontWeight: 700, color: p.estoque === 0 ? "var(--danger)" : "var(--warn)" }}>{p.estoque} {p.un}</span>
              </div>
            ))}</div>}
      </div>
    </>
  );
}

window.Screens = window.Screens || {};
Object.assign(window.Screens, { RelatoriosScreen, RelatorioDetalheScreen });
