// compras-page.jsx — m\u00f3dulo Compras embedado no shell unificado.
// Migrado de Compras.html. CSS pr\u00f3prio em compras-page.css (escopado em .compras-root).
// IIFE: tudo encapsulado, exp\u00f5e window.ComprasPage.
(() => {
const { useState, useMemo } = React;

const fmt = (n) => "R$ " + Number(n || 0).toLocaleString("pt-BR", { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const fmtDate = (d) => new Date(d).toLocaleDateString("pt-BR", { day: "2-digit", month: "2-digit" });

// ─── FSM compras ───
const STAGES = [
  { id: "rascunho", l: "Rascunho",   ic: "○" },
  { id: "pedido",   l: "Pedido",     ic: "✎" },
  { id: "transito", l: "Em trânsito",ic: "⇨" },
  { id: "recebido", l: "Recebido",   ic: "⊞" },
  { id: "conferido",l: "Conferido",  ic: "✓" },
  { id: "pago",     l: "Pago",       ic: "$" },
];

// ─── MOCK ───
const SUPPLIERS = {
  "SUP-001": { name: "Lonas & Vinis Ltda",       doc: "12.345.678/0001-90", city: "São Paulo · SP" },
  "SUP-002": { name: "Tintas Coral - Distrib.",  doc: "23.456.789/0001-01", city: "Guarulhos · SP" },
  "SUP-003": { name: "Papel & Cia Atacado",      doc: "34.567.890/0001-12", city: "Osasco · SP" },
  "SUP-004": { name: "Importadora Têxtil RH",    doc: "45.678.901/0001-23", city: "Americana · SP" },
};

const PURCHASES = [
  { id: "COMP-2847", ref: "NF-e 4521", supplier: "SUP-001", date: "2026-05-08", stage: "recebido", total: 8420.00, paid: 0,       due: 8420.00, items: 5,  locName: "Matriz",
    xmlChave: "35260512345678000190550010000045211000045210", xmlEmit: "2026-05-07T14:23",
    payTerm: "30 dias", desc: 0, shipping: 120, expenses: 0, notes: "Reposição lona 380gr · pedido P-882",
    products: [
      { name: "Lona 380gr brilho",       sku: "PROD-200", qty: 200,  unit: "m²", costBefore: 7.50,   disc: 0, net: 7.50,   tax: 18, total: 1770.00, sellPrice: 55,   margin: 633, lot: "L2847" },
      { name: "Lona blackout 440gr",     sku: "PROD-201", qty: 80,   unit: "m²", costBefore: 11.20,  disc: 5, net: 10.64,  tax: 18, total: 1003.78, sellPrice: 75,   margin: 605, lot: "L2848" },
      { name: "Vinil adesivo brilho",    sku: "PROD-022", qty: 300,  unit: "m²", costBefore: 5.80,   disc: 0, net: 5.80,   tax: 18, total: 2053.20, sellPrice: 42,   margin: 624, lot: "L2849" },
      { name: "Ilhós metálico nº 12",    sku: "INS-014",  qty: 5000, unit: "un", costBefore: 0.12,   disc: 0, net: 0.12,   tax: 18, total: 708.00,  sellPrice: 0.45, margin: 275, lot: "-" },
      { name: "Tinta solvente CMYK 5L",  sku: "INS-022",  qty: 4,    unit: "un", costBefore: 540.00, disc: 8, net: 496.80, tax: 18, total: 2344.61, sellPrice: 780,  margin: 57,  lot: "L2851" },
    ],
    timeline: [
      { t: "now",  by: "Wagner", at: "2026-05-08 11:42", title: "Mercadoria recebida · conferindo NF-e", notes: "5 volumes · sem avarias visíveis." },
      { t: "ok",   by: "Bruna",  at: "2026-05-08 09:15", title: "Trânsito → Recebido (portaria)" },
      { t: "ok",   by: "Sist.",  at: "2026-05-07 14:30", title: "XML NF-e 4521 importado · supplier match automático" },
      { t: "ok",   by: "Wagner", at: "2026-05-02 16:20", title: "Pedido P-882 enviado ao fornecedor" },
    ]},
  { id: "COMP-2846", ref: "NF-e 0998", supplier: "SUP-002", date: "2026-05-07", stage: "pago",      total: 3240.00,  paid: 3240.00, due: 0,       items: 2,  locName: "Matriz",
    xmlChave: "35260523456789000101550010000009981000009980", payTerm: "À vista", desc: 5, shipping: 0,   notes: "" },
  { id: "COMP-2845", ref: "NF-e 7711", supplier: "SUP-003", date: "2026-05-06", stage: "conferido", total: 1180.50,  paid: 0,       due: 1180.50, items: 8,  locName: "Matriz",
    xmlChave: "35260534567890000112550010000077111000077110", payTerm: "15 dias", desc: 0, shipping: 45,  notes: "" },
  { id: "COMP-2844", ref: "NF-e 0234", supplier: "SUP-001", date: "2026-05-05", stage: "transito",  total: 5620.80,  paid: 1000,    due: 4620.80, items: 3,  locName: "Matriz",
    xmlChave: "35260512345678000190550010000002341000002340", payTerm: "30/60",   desc: 0, shipping: 200, notes: "Previsão chegada: 09/05" },
  { id: "COMP-2843", ref: "-",         supplier: "SUP-004", date: "2026-05-05", stage: "pedido",    total: 12450.00, paid: 0,       due: 0,       items: 12, locName: "Matriz",
    xmlChave: "", payTerm: "45 dias", desc: 0, shipping: 0, notes: "PO emitido · aguardando confirmação fornecedor" },
  { id: "COMP-2842", ref: "-",         supplier: "SUP-002", date: "2026-05-04", stage: "rascunho",  total: 0,        paid: 0,       due: 0,       items: 0,  locName: "Matriz",
    xmlChave: "", payTerm: "", desc: 0, shipping: 0, notes: "Em elaboração" },
  { id: "COMP-2841", ref: "NF-e 0997", supplier: "SUP-002", date: "2026-05-03", stage: "pago",      total: 1860.40,  paid: 1860.40, due: 0,       items: 4,  locName: "Matriz",
    xmlChave: "35260523456789000101550010000009971000009970", payTerm: "À vista", desc: 0, shipping: 0, notes: "" },
];

function ComprasPage() {
  const [sel, setSel] = useState("COMP-2847");
  const [tab, setTab] = useState("resumo");
  const [filter, setFilter] = useState("all");

  const filtered = useMemo(() => {
    if (filter === "all") return PURCHASES;
    if (filter === "abertas") return PURCHASES.filter(p => p.due > 0);
    if (filter === "rascunhos") return PURCHASES.filter(p => p.stage === "rascunho");
    if (filter === "transito") return PURCHASES.filter(p => p.stage === "transito");
    return PURCHASES;
  }, [filter]);

  const selected = PURCHASES.find(p => p.id === sel);

  const kpi = useMemo(() => ({
    aberto: PURCHASES.filter(p => p.due > 0).reduce((s, p) => s + p.due, 0),
    transito: PURCHASES.filter(p => p.stage === "transito").length,
    mes: PURCHASES.reduce((s, p) => s + p.total, 0),
    fornec: new Set(PURCHASES.map(p => p.supplier)).size,
  }), []);

  const stageIdx = (s) => STAGES.findIndex(x => x.id === s);

  return (
    <div className="compras-root" data-screen-label="01 Compras">
      <div className="cmp-main">
        {/* HEAD */}
        <header className="hd">
          <div className="crumbs">ERP · Operação · <b style={{ color: "var(--cmp-ink-2)" }}>Compras</b></div>
          <h1>Compras</h1>
          <span className="count">{filtered.length} de {PURCHASES.length}</span>
          <div className="sp"></div>
          <div className="search">
            <span>⌕</span>
            <input placeholder="Buscar NF-e, fornecedor, ref, chave..." />
            <kbd style={{ fontSize: 9, fontFamily: "var(--cmp-mono)", color: "var(--cmp-ink-3)", background: "var(--cmp-line-2)", padding: "1px 5px", borderRadius: 3 }}>/</kbd>
          </div>
          <button className="btn">↓ Importar XML</button>
          <button className="btn primary">+ Nova compra</button>
        </header>

        {/* TABS */}
        <nav className="tbs">
          <a className={filter === "all" ? "active" : ""} onClick={() => setFilter("all")}>Todas <span className="ct">{PURCHASES.length}</span></a>
          <a className={filter === "abertas" ? "active" : ""} onClick={() => setFilter("abertas")}>A pagar <span className="ct">{PURCHASES.filter(p => p.due > 0).length}</span></a>
          <a className={filter === "rascunhos" ? "active" : ""} onClick={() => setFilter("rascunhos")}>Rascunhos <span className="ct">{PURCHASES.filter(p => p.stage === "rascunho").length}</span></a>
          <a className={filter === "transito" ? "active" : ""} onClick={() => setFilter("transito")}>Em trânsito <span className="ct">{PURCHASES.filter(p => p.stage === "transito").length}</span></a>
          <a>Cancelados</a>
          <div className="sp"></div>
          <div className="filters">
            <span style={{ fontSize: 10.5, textTransform: "uppercase", letterSpacing: ".04em", color: "var(--cmp-ink-3)" }}>filtros</span>
            <span className="filter-pill">Fornecedor: todos</span>
            <span className="filter-pill">Local: Matriz</span>
            <span className="filter-pill">Maio/2026</span>
            <span className="filter-pill on">Pagamento: todos</span>
          </div>
        </nav>

        {/* BODY */}
        <div className={`bd ${selected ? "with-drawer" : ""}`}>
          {/* LISTA */}
          <main className="list">
            <div className="kpis">
              <div className="kpi warn">
                <small>A pagar</small><b>{fmt(kpi.aberto)}</b>
                <div className="ln">{PURCHASES.filter(p => p.due > 0).length} compras em aberto · próx. venc. 09/05</div>
              </div>
              <div className="kpi">
                <small>Em trânsito</small><b>{kpi.transito}</b>
                <div className="ln">aguardando entrega · {fmt(PURCHASES.filter(p => p.stage === "transito").reduce((s, p) => s + p.total, 0))}</div>
              </div>
              <div className="kpi">
                <small>Volume do mês</small><b>{fmt(kpi.mes)}</b>
                <div className="ln">+12,4% vs. abr/26 · meta {fmt(45000)}</div>
              </div>
              <div className="kpi ok">
                <small>Fornecedores ativos</small><b>{kpi.fornec}</b>
                <div className="ln">3 com compra recorrente · 1 novo</div>
              </div>
            </div>

            <div className="tbl">
              <table className="purchases">
                <thead>
                  <tr>
                    <th style={{ width: "100px" }}>Compra</th>
                    <th>Fornecedor</th>
                    <th style={{ width: "95px" }}>Data</th>
                    <th style={{ width: "100px" }}>Estágio</th>
                    <th style={{ width: "60px", textAlign: "right" }}>Itens</th>
                    <th style={{ width: "100px", textAlign: "right" }}>Total</th>
                    <th style={{ width: "100px", textAlign: "right" }}>A pagar</th>
                    <th style={{ width: "80px" }}>NF-e</th>
                  </tr>
                </thead>
                <tbody>
                  {filtered.map(p => {
                    const s = SUPPLIERS[p.supplier];
                    return (
                      <tr key={p.id} className={sel === p.id ? "sel" : ""} onClick={() => { setSel(p.id); setTab("resumo"); }}>
                        <td className="mono"><b>{p.id}</b><small>{p.ref}</small></td>
                        <td><b>{s.name}</b><small>{s.city}</small></td>
                        <td className="mono">{fmtDate(p.date)}</td>
                        <td><span className={`pill ${p.stage}`}>{STAGES.find(x => x.id === p.stage).l}</span></td>
                        <td className="num">{p.items}</td>
                        <td className="num"><b>{fmt(p.total)}</b></td>
                        <td className="num" style={{ color: p.due > 0 ? "var(--cmp-warn)" : "var(--cmp-ok)", fontWeight: 600 }}>
                          {p.due > 0 ? fmt(p.due) : "✓"}
                        </td>
                        <td className="mono" style={{ color: p.xmlChave ? "var(--cmp-ok)" : "var(--cmp-ink-3)", fontSize: 11 }}>
                          {p.xmlChave ? "✓ XML" : "—"}
                        </td>
                      </tr>
                    );
                  })}
                </tbody>
              </table>
            </div>
          </main>

          {/* DRAWER */}
          {selected && <DrawerView p={selected} tab={tab} setTab={setTab} stageIdx={stageIdx} close={() => setSel(null)} />}
        </div>

        {/* FOOTER */}
        <footer className="ft">
          <b>{filtered.length}</b> compras listadas · total <b>{fmt(filtered.reduce((s, p) => s + p.total, 0))}</b> · a pagar <b style={{ color: "var(--cmp-warn)" }}>{fmt(filtered.reduce((s, p) => s + p.due, 0))}</b>
          <div className="sp"></div>
          <span>Atalhos: <kbd>/</kbd> buscar · <kbd>N</kbd> nova · <kbd>I</kbd> importar XML · <kbd>↑↓</kbd> navegar · <kbd>Esc</kbd> fechar</span>
        </footer>
      </div>
    </div>
  );
}

// ─── DRAWER ───
function DrawerView({ p, tab, setTab, stageIdx, close }) {
  const s = SUPPLIERS[p.supplier];
  const idx = stageIdx(p.stage);
  const subtotal = p.products ? p.products.reduce((s, i) => s + i.total, 0) : p.total;
  const itemsTabs = [
    { id: "resumo",     l: "Resumo",     ct: null },
    { id: "itens",      l: "Itens",      ct: p.items },
    { id: "documentos", l: "Documentos", ct: p.xmlChave ? 1 : 0 },
    { id: "pagamentos", l: "Pagamentos", ct: null },
    { id: "historico",  l: "Histórico",  ct: p.timeline ? p.timeline.length : 0 },
  ];

  return (
    <aside className="drawer">
      <div className="drw-head">
        <div>
          <h2>{s.name}</h2>
          <div style={{ fontSize: 11.5, color: "var(--cmp-ink-3)", marginTop: 2 }}>
            {p.items} ite{p.items === 1 ? "m" : "ns"} · {p.locName} · {p.payTerm || "—"}
          </div>
          <span className="mono">{p.id} · {p.ref}</span>
        </div>
        <button className="x" onClick={close}>✕</button>
      </div>

      <div className="fsm">
        <div className="fsm-track">
          {STAGES.map((st, i) => (
            <div key={st.id} className={`fsm-step ${i < idx ? "done" : i === idx ? "now" : ""}`} title={st.l}>
              <span className="ic">{st.ic}</span>{st.l}
            </div>
          ))}
        </div>
      </div>

      <div className="drw-tabs">
        {itemsTabs.map(t => (
          <button key={t.id} className={tab === t.id ? "active" : ""} onClick={() => setTab(t.id)}>
            {t.l}{t.ct != null && <span className="ct">{t.ct}</span>}
          </button>
        ))}
      </div>

      <div className="drw-body">
        {tab === "resumo" && (
          <>
            <div className="sec">
              <h4>Fornecedor</h4>
              <div className="card">
                <div className="field-grid">
                  <div className="f"><label>CNPJ</label><span className="mono">{s.doc}</span></div>
                  <div className="f"><label>Cidade</label><span>{s.city}</span></div>
                  <div className="f full"><label>Endereço de cobrança</label><span>Rua Industrial, 200 · Centro · CEP 04123-000</span></div>
                </div>
              </div>
            </div>

            <div className="sec">
              <h4>Dados da compra</h4>
              <div className="card">
                <div className="field-grid">
                  <div className="f"><label>Ref. interna</label><span className="mono">{p.ref}</span></div>
                  <div className="f"><label>Data emissão</label><span className="mono">{fmtDate(p.date)}</span></div>
                  <div className="f"><label>Local</label><span>{p.locName}</span></div>
                  <div className="f"><label>Cond. pagamento</label><span>{p.payTerm || "—"}</span></div>
                  <div className="f"><label>Desconto</label><span>{p.desc > 0 ? p.desc + "%" : "—"}</span></div>
                  <div className="f"><label>Frete</label><span className="mono">{fmt(p.shipping)}</span></div>
                </div>
              </div>
            </div>

            {p.notes && (
              <div className="sec">
                <h4>Observações</h4>
                <div className="card" style={{ fontSize: 12, color: "var(--cmp-ink-2)", lineHeight: 1.5, background: "#fff" }}>{p.notes}</div>
              </div>
            )}

            <div className="sec">
              <h4>Resumo financeiro</h4>
              <div className="card" style={{ padding: 0 }}>
                <table style={{ width: "100%", fontSize: 12 }}>
                  <tbody>
                    <tr><td style={{ padding: "6px 13px", color: "var(--cmp-ink-3)" }}>Subtotal</td><td style={{ padding: "6px 13px", textAlign: "right", fontFamily: "var(--cmp-mono)" }}>{fmt(subtotal)}</td></tr>
                    {p.desc > 0 && <tr><td style={{ padding: "6px 13px", color: "var(--cmp-ink-3)" }}>Desconto ({p.desc}%)</td><td style={{ padding: "6px 13px", textAlign: "right", fontFamily: "var(--cmp-mono)", color: "var(--cmp-err)" }}>−{fmt(subtotal * p.desc / 100)}</td></tr>}
                    {p.shipping > 0 && <tr><td style={{ padding: "6px 13px", color: "var(--cmp-ink-3)" }}>Frete</td><td style={{ padding: "6px 13px", textAlign: "right", fontFamily: "var(--cmp-mono)" }}>+{fmt(p.shipping)}</td></tr>}
                    <tr style={{ borderTop: "1px solid var(--cmp-line)" }}>
                      <td style={{ padding: "8px 13px", fontWeight: 700 }}>Total da compra</td>
                      <td style={{ padding: "8px 13px", textAlign: "right", fontFamily: "var(--cmp-mono)", fontWeight: 700, fontSize: 14 }}>{fmt(p.total)}</td>
                    </tr>
                    <tr><td style={{ padding: "6px 13px", color: "var(--cmp-ok)" }}>Pago</td><td style={{ padding: "6px 13px", textAlign: "right", fontFamily: "var(--cmp-mono)", color: "var(--cmp-ok)" }}>{fmt(p.paid)}</td></tr>
                    <tr><td style={{ padding: "6px 13px", color: "var(--cmp-warn)", fontWeight: 600 }}>A pagar</td><td style={{ padding: "6px 13px", textAlign: "right", fontFamily: "var(--cmp-mono)", fontWeight: 700, color: "var(--cmp-warn)" }}>{fmt(p.due)}</td></tr>
                  </tbody>
                </table>
              </div>
            </div>
          </>
        )}

        {tab === "itens" && (
          <div className="sec">
            <h4>Itens recebidos <span className="badge">{p.items}</span></h4>
            {p.products ? (
              <table className="items-tbl">
                <thead><tr>
                  <th>Produto</th><th className="num">Qtd</th><th className="num">Custo unit.</th><th className="num">Total</th><th className="num">Venda</th><th className="num">Margem</th>
                </tr></thead>
                <tbody>
                  {p.products.map((it, i) => (
                    <tr key={i}>
                      <td><b>{it.name}</b><small>{it.sku} · lote {it.lot}</small></td>
                      <td className="num">{it.qty}<small style={{ textAlign: "right" }}>{it.unit}</small></td>
                      <td className="num">{fmt(it.net)}{it.disc > 0 && <small style={{ textAlign: "right", color: "var(--cmp-ok)" }}>−{it.disc}%</small>}</td>
                      <td className="num"><b>{fmt(it.total)}</b></td>
                      <td className="num">{fmt(it.sellPrice)}</td>
                      <td className="num" style={{ color: "var(--cmp-ok)", fontWeight: 600 }}>+{it.margin}%</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            ) : (
              <div className="card" style={{ textAlign: "center", color: "var(--cmp-ink-3)", fontSize: 11.5, padding: 18 }}>Itens detalhados não disponíveis nesta visualização.</div>
            )}
          </div>
        )}

        {tab === "documentos" && (
          <>
            {p.xmlChave ? (
              <>
                <div className="sec">
                  <h4>NF-e de entrada</h4>
                  <div className="xml-badge">
                    <span style={{ fontSize: 18 }}>⊞</span>
                    <div style={{ flex: 1 }}>
                      <b>NF-e {p.ref}</b> autorizada · status SEFAZ <b>100</b>
                      <div style={{ fontSize: 10.5, marginTop: 3 }}>chave de acesso</div>
                      <div className="key">{p.xmlChave}</div>
                    </div>
                  </div>
                  <div style={{ display: "flex", gap: 6, marginTop: 8 }}>
                    <button className="btn sm">↓ XML</button>
                    <button className="btn sm">↓ DANFE PDF</button>
                    <button className="btn sm">Manifestar destinatário</button>
                  </div>
                </div>
                <div className="sec">
                  <h4>Manifesto destinatário</h4>
                  <div className="card" style={{ background: "var(--cmp-ok-soft)", borderColor: "#b8d9c0", fontSize: 11.5 }}>
                    <b style={{ color: "var(--cmp-ok)" }}>✓ Confirmação da operação</b>
                    <div style={{ color: "var(--cmp-ink-2)", marginTop: 3 }}>Manifestada em 08/05/26 09:18 · protocolo 135260012345</div>
                  </div>
                </div>
              </>
            ) : (
              <div className="card" style={{ textAlign: "center", padding: 24, color: "var(--cmp-ink-3)" }}>
                <div style={{ fontSize: 24, marginBottom: 8 }}>⊠</div>
                <b style={{ color: "var(--cmp-ink-2)", fontSize: 13, display: "block", marginBottom: 3 }}>Nenhuma NF-e vinculada</b>
                <small style={{ fontSize: 11 }}>Importe o XML ou cole a chave de 44 dígitos</small>
                <div style={{ marginTop: 10 }}><button className="btn sm">↓ Importar XML</button></div>
              </div>
            )}
          </>
        )}

        {tab === "pagamentos" && (
          <div className="sec">
            <h4>Pagamentos</h4>
            <div className="card">
              {p.paid > 0 && (
                <div className="pay-row">
                  <div className="pay-icon" style={{ background: "var(--cmp-ok-soft)", color: "var(--cmp-ok)" }}>PIX</div>
                  <div>
                    <b>Pagamento à vista</b>
                    <small>05/05/26 14:23 · Banco do Brasil · doc 8842</small>
                  </div>
                  <span className="val paid">{fmt(p.paid)}</span>
                </div>
              )}
              {p.due > 0 && (
                <div className="pay-row">
                  <div className="pay-icon" style={{ background: "var(--cmp-warn-soft)", color: "var(--cmp-warn)" }}>BL</div>
                  <div>
                    <b>Boleto · vence 07/06/26</b>
                    <small>30 dias · cód. barras gerado · 837900000...</small>
                  </div>
                  <span className="val due">{fmt(p.due)}</span>
                </div>
              )}
              {p.paid === 0 && p.due === 0 && (
                <div style={{ textAlign: "center", color: "var(--cmp-ink-3)", fontSize: 12, padding: 14 }}>Sem pagamentos lançados</div>
              )}
            </div>
            {p.due > 0 && (
              <div style={{ display: "flex", gap: 6, marginTop: 10 }}>
                <button className="btn sm primary">+ Registrar pagamento</button>
                <button className="btn sm">Agendar no financeiro</button>
              </div>
            )}
          </div>
        )}

        {tab === "historico" && (
          <div className="sec">
            <h4>Linha do tempo</h4>
            {p.timeline ? (
              <div className="tl">
                {p.timeline.map((e, i) => (
                  <div key={i} className={`tl-item ${e.t}`}>
                    <b>{e.title}</b>
                    <div className="when">{e.at} · {e.by}</div>
                    {e.notes && <p>{e.notes}</p>}
                  </div>
                ))}
              </div>
            ) : (
              <div className="card" style={{ textAlign: "center", color: "var(--cmp-ink-3)", fontSize: 12, padding: 18 }}>Sem eventos registrados</div>
            )}
          </div>
        )}
      </div>

      <div className="drw-foot">
        <div className="total">
          Total da compra
          <b>{fmt(p.total)}</b>
        </div>
        {p.stage === "transito" && <button className="btn">Marcar recebida</button>}
        {p.stage === "recebido" && <button className="btn">Conferir itens</button>}
        {p.stage === "conferido" && p.due > 0 && <button className="btn warn">Pagar agora</button>}
        {p.stage === "rascunho" && <button className="btn">Enviar pedido</button>}
        <button className="btn primary">Executar →</button>
      </div>
    </aside>
  );
}

window.ComprasPage = ComprasPage;
})();
