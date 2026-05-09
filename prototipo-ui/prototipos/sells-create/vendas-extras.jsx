// vendas-extras.jsx — Sub-páginas do módulo Vendas
// Caixa do dia · Devoluções · Comissões · Relatórios · PDV balcão
// Recibo (térmica + A4) · NF-e drawer
// Compõe via VendasModule wrapper com sub-tabs

const { useState: useStateVE, useMemo: useMemoVE, useEffect: useEffectVE, useRef: useRefVE } = React;

// Helpers compartilhados ────────────────────────────────────────────
const _fmtBRL = (n) => (n || 0).toLocaleString("pt-BR", { style:"currency", currency:"BRL" });
const _parseBRL = (s) => {
  if (typeof s === "number") return s;
  return parseFloat(String(s||"0").replace(/[^\d,-]/g,"").replace(",",".")) || 0;
};

// ═══════════════════════════════════════════════════════════════════
// VENDAS MODULE — wrapper com sub-rotas
// ═══════════════════════════════════════════════════════════════════
function VendasModule() {
  const [sub, setSub] = useStateVE(() => {
    try { return localStorage.getItem("oimpresso.vendas.sub") || "lista"; }
    catch (e) { return "lista"; }
  });
  const [pdvOpen, setPdvOpen] = useStateVE(false);

  useEffectVE(() => {
    try { localStorage.setItem("oimpresso.vendas.sub", sub); } catch (e) {}
  }, [sub]);

  const SUBS = [
    { id:"lista",       label:"Vendas",       icon:"list"     },
    { id:"caixa",       label:"Caixa do dia", icon:"cash"     },
    { id:"devolucoes",  label:"Devoluções",   icon:"refund"   },
    { id:"comissoes",   label:"Comissões",    icon:"trending" },
    { id:"relatorios",  label:"Relatórios",   icon:"chart"    },
  ];

  let body;
  if (sub === "lista")            body = <VendasListPage/>;
  else if (sub === "caixa")       body = <VendasCaixaPage/>;
  else if (sub === "devolucoes")  body = <VendasDevolucoesPage/>;
  else if (sub === "comissoes")   body = <VendasComissoesPage/>;
  else if (sub === "relatorios")  body = <VendasRelatoriosPage/>;

  return (
    <div className="vendas-module">
      <nav className="vm-subnav">
        <div className="vm-subnav-tabs">
          {SUBS.map(s => (
            <button key={s.id}
                    className={"vm-subtab" + (sub === s.id ? " active" : "")}
                    onClick={() => setSub(s.id)}>
              <VMIcon name={s.icon}/>
              <span>{s.label}</span>
            </button>
          ))}
        </div>
        <div className="vm-subnav-r">
          <button className="vm-pdv-btn" onClick={() => setPdvOpen(true)}>
            <VMIcon name="screen"/><span>Abrir PDV balcão</span>
            <kbd>F2</kbd>
          </button>
        </div>
      </nav>
      <div className="vm-body">{body}</div>
      {pdvOpen && <VendasPDVOverlay onClose={() => setPdvOpen(false)}/>}
    </div>
  );
}

// Mini icon set (usa SVG inline pra não depender do icons.jsx)
function VMIcon({ name, size = 13 }) {
  const s = { width:size, height:size, fill:"none", stroke:"currentColor", strokeWidth:1.6, strokeLinecap:"round", strokeLinejoin:"round" };
  switch (name) {
    case "list":     return <svg viewBox="0 0 16 16" {...s}><line x1="3" y1="4" x2="13" y2="4"/><line x1="3" y1="8" x2="13" y2="8"/><line x1="3" y1="12" x2="13" y2="12"/></svg>;
    case "cash":     return <svg viewBox="0 0 16 16" {...s}><rect x="2" y="4" width="12" height="8" rx="1"/><circle cx="8" cy="8" r="1.5"/></svg>;
    case "refund":   return <svg viewBox="0 0 16 16" {...s}><path d="M3 8 a5 5 0 1 0 1.5-3.5"/><polyline points="3,3 3,5 5,5"/></svg>;
    case "trending": return <svg viewBox="0 0 16 16" {...s}><polyline points="2,11 6,7 9,9 14,4"/><polyline points="11,4 14,4 14,7"/></svg>;
    case "chart":    return <svg viewBox="0 0 16 16" {...s}><line x1="3" y1="13" x2="13" y2="13"/><rect x="4" y="8" width="2" height="5"/><rect x="7" y="5" width="2" height="8"/><rect x="10" y="9" width="2" height="4"/></svg>;
    case "screen":   return <svg viewBox="0 0 16 16" {...s}><rect x="2" y="3" width="12" height="8" rx="1"/><line x1="6" y1="13" x2="10" y2="13"/></svg>;
    default: return null;
  }
}

// ═══════════════════════════════════════════════════════════════════
// 1) CAIXA DO DIA — fechamento, sangria, conferência
// ═══════════════════════════════════════════════════════════════════
function VendasCaixaPage() {
  const { VENDAS_LIST, VENDAS_PAYMENTS } = window.VENDAS_DATA;
  const [date, setDate] = useStateVE("2026-04-30");
  const [openingCash, setOpeningCash] = useStateVE(200.00);
  const [countedCash, setCountedCash] = useStateVE(580.00);
  const [moves, setMoves] = useStateVE([
    { id:1, time:"08:30", type:"abertura",   amount:200,   note:"Saldo inicial",       user:"Larissa" },
    { id:2, time:"10:15", type:"suprimento", amount:300,   note:"Troco do cofre",      user:"Larissa" },
    { id:3, time:"13:40", type:"sangria",    amount:-500,  note:"Depósito banco",      user:"Larissa" },
    { id:4, time:"16:50", type:"sangria",    amount:-200,  note:"Pagamento fornecedor", user:"Larissa" },
  ]);
  const [moveDraft, setMoveDraft] = useStateVE({ type:"sangria", amount:"", note:"" });

  const dayVendas = useMemoVE(() =>
    VENDAS_LIST.filter(v => v.date === date && v.status !== "cancelada"),
  [VENDAS_LIST, date]);

  // Total por forma de pagamento
  const byPayment = useMemoVE(() => {
    const map = {};
    VENDAS_PAYMENTS.forEach(p => { map[p.label] = { count:0, total:0, payment:p }; });
    map["Boleto 30d"] = map["Boleto 30d"] || { count:0, total:0, payment:{ label:"Boleto 30d", icon:"📄", clearing:"30 dias" } };
    map["Boleto 60d"] = map["Boleto 60d"] || { count:0, total:0, payment:{ label:"Boleto 60d", icon:"📄", clearing:"60 dias" } };
    dayVendas.forEach(v => {
      const k = v.payment;
      if (!map[k]) map[k] = { count:0, total:0, payment:{ label:k, icon:"💰", clearing:"—" } };
      map[k].count += 1;
      map[k].total += _parseBRL(v.total);
    });
    return Object.values(map).filter(x => x.count > 0 || ["PIX","Dinheiro","Cartão"].includes(x.payment.label));
  }, [dayVendas]);

  const cashSales = byPayment.find(x => x.payment.label === "Dinheiro")?.total || 0;
  const movesSum = moves.reduce((s,m) => s + m.amount, 0);
  const expectedCash = movesSum + cashSales;
  const diff = countedCash - expectedCash;

  const totalDay = dayVendas.reduce((s,v) => s + _parseBRL(v.total), 0);

  const addMove = () => {
    if (!moveDraft.amount) return;
    const sign = moveDraft.type === "sangria" ? -1 : 1;
    setMoves(prev => [...prev, {
      id: Date.now(),
      time: new Date().toLocaleTimeString("pt-BR", { hour:"2-digit", minute:"2-digit" }),
      type: moveDraft.type,
      amount: sign * Math.abs(parseFloat(moveDraft.amount) || 0),
      note: moveDraft.note || "—",
      user: "Larissa",
    }]);
    setMoveDraft({ type:"sangria", amount:"", note:"" });
  };

  return (
    <div className="os-page vc-page">
      <header className="os-head">
        <div className="os-head-l">
          <h1>Caixa do dia</h1>
          <p>Conferência por forma de pagamento, sangrias e fechamento</p>
        </div>
        <div className="os-head-r">
          <input type="date" className="vc-date" value={date} onChange={e => setDate(e.target.value)}/>
          <button className="os-btn ghost"><VMIcon name="cash" size={11}/>Imprimir Z</button>
          <button className="os-btn primary">Fechar caixa</button>
        </div>
      </header>

      <div className="os-kpis">
        <div className="os-kpi">
          <span className="os-kpi-label">Faturado no dia</span>
          <span className="os-kpi-value">{_fmtBRL(totalDay)}</span>
          <span className="os-kpi-sub">{dayVendas.length} venda{dayVendas.length !== 1 ? "s" : ""}</span>
        </div>
        <div className="os-kpi">
          <span className="os-kpi-label">Esperado em caixa</span>
          <span className="os-kpi-value">{_fmtBRL(expectedCash)}</span>
          <span className="os-kpi-sub">dinheiro + sangrias</span>
        </div>
        <div className="os-kpi">
          <span className="os-kpi-label">Conferido</span>
          <span className="os-kpi-value">{_fmtBRL(countedCash)}</span>
          <span className="os-kpi-sub">contagem física</span>
        </div>
        <div className={"os-kpi" + (Math.abs(diff) > 0.01 ? " os-kpi-alert" : "")}>
          <span className="os-kpi-label">Diferença</span>
          <span className="os-kpi-value" style={{
            color: Math.abs(diff) < 0.01 ? "oklch(0.50 0.14 145)" : (diff < 0 ? "oklch(0.55 0.18 25)" : "oklch(0.55 0.14 70)")
          }}>{diff >= 0 ? "+" : ""}{_fmtBRL(diff)}</span>
          <span className="os-kpi-sub">{Math.abs(diff) < 0.01 ? "ok" : (diff < 0 ? "falta" : "sobra")}</span>
        </div>
      </div>

      <div className="vc-grid">
        <section className="vc-card">
          <header className="vc-card-h">
            <h3>Por forma de pagamento</h3>
            <span className="vc-muted">{date.split("-").reverse().join("/")}</span>
          </header>
          <table className="vc-pay-table">
            <thead><tr><th>Forma</th><th>Compensação</th><th>Vendas</th><th>Total</th></tr></thead>
            <tbody>
              {byPayment.map(x => (
                <tr key={x.payment.label}>
                  <td><span className="vc-pay-icon">{x.payment.icon}</span> {x.payment.label}</td>
                  <td className="vc-muted">{x.payment.clearing}</td>
                  <td className="vc-num">{x.count}</td>
                  <td className="vc-num strong">{_fmtBRL(x.total)}</td>
                </tr>
              ))}
            </tbody>
            <tfoot>
              <tr><td colSpan="3">Total bruto</td><td className="vc-num strong">{_fmtBRL(totalDay)}</td></tr>
            </tfoot>
          </table>
        </section>

        <section className="vc-card">
          <header className="vc-card-h">
            <h3>Movimentos do caixa</h3>
            <span className="vc-muted">{moves.length} lançamentos</span>
          </header>
          <ul className="vc-moves">
            {moves.map(m => (
              <li key={m.id} className={"vc-move vc-move-" + m.type}>
                <span className="vc-move-time">{m.time}</span>
                <span className="vc-move-type">{m.type}</span>
                <span className="vc-move-note">{m.note}</span>
                <span className={"vc-move-amount " + (m.amount < 0 ? "neg" : "pos")}>
                  {m.amount >= 0 ? "+" : ""}{_fmtBRL(m.amount)}
                </span>
              </li>
            ))}
          </ul>
          <div className="vc-move-add">
            <select value={moveDraft.type} onChange={e => setMoveDraft({...moveDraft, type:e.target.value})}>
              <option value="sangria">Sangria</option>
              <option value="suprimento">Suprimento</option>
            </select>
            <input type="number" placeholder="Valor" value={moveDraft.amount}
                   onChange={e => setMoveDraft({...moveDraft, amount:e.target.value})}/>
            <input placeholder="Motivo" value={moveDraft.note}
                   onChange={e => setMoveDraft({...moveDraft, note:e.target.value})}/>
            <button className="os-btn primary" onClick={addMove}>Lançar</button>
          </div>
        </section>

        <section className="vc-card">
          <header className="vc-card-h">
            <h3>Conferência física</h3>
            <span className="vc-muted">conte e digite</span>
          </header>
          <div className="vc-counter">
            <label>
              <span>Saldo de abertura</span>
              <input type="number" step="0.01" value={openingCash}
                     onChange={e => setOpeningCash(parseFloat(e.target.value)||0)}/>
            </label>
            <label>
              <span>Total contado em espécie</span>
              <input type="number" step="0.01" className="vc-counter-big" value={countedCash}
                     onChange={e => setCountedCash(parseFloat(e.target.value)||0)}/>
            </label>
            <dl className="vc-counter-summary">
              <dt>+ Vendas em dinheiro</dt><dd>{_fmtBRL(cashSales)}</dd>
              <dt>+ Suprimentos</dt><dd>{_fmtBRL(moves.filter(m=>m.amount>0).reduce((s,m)=>s+m.amount,0))}</dd>
              <dt>− Sangrias</dt><dd>{_fmtBRL(Math.abs(moves.filter(m=>m.amount<0).reduce((s,m)=>s+m.amount,0)))}</dd>
              <dt>= Esperado</dt><dd className="strong">{_fmtBRL(expectedCash)}</dd>
            </dl>
          </div>
        </section>
      </div>
    </div>
  );
}

// ═══════════════════════════════════════════════════════════════════
// 2) DEVOLUÇÕES — fluxo de cancelar venda paga
// ═══════════════════════════════════════════════════════════════════
const _MOCK_DEVOLUCOES = [
  { id:"D-104", date:"2026-04-29", vendaId:"V-7818", client:"Café Aroma Doce",     reason:"defeito",   items:1, total:680,    refund:"PIX",      status:"concluida",  user:"Bruna Vendas"   },
  { id:"D-105", date:"2026-04-30", vendaId:"V-7820", client:"Loja Mini Shopping",   reason:"erro_arte", items:2, total:1240,   refund:"crédito",  status:"em_analise", user:"Carlos Vendas"  },
  { id:"D-106", date:"2026-04-30", vendaId:"V-7825", client:"Farmácia Vida Plena",  reason:"atraso",    items:1, total:1620,   refund:"estorno",  status:"em_analise", user:"Wagner"         },
];

function VendasDevolucoesPage() {
  const { VENDAS_LIST } = window.VENDAS_DATA;
  const [list, setList] = useStateVE(_MOCK_DEVOLUCOES);
  const [openId, setOpenId] = useStateVE(null);
  const [createOpen, setCreateOpen] = useStateVE(false);

  const stats = {
    pendentes: list.filter(d => d.status === "em_analise").length,
    mes:       list.length,
    valor:     list.reduce((s,d) => s + d.total, 0),
  };

  const open = openId ? list.find(d => d.id === openId) : null;
  const STATUS = {
    em_analise: { label:"Em análise", color:"oklch(0.60 0.14 70)"  },
    concluida:  { label:"Concluída",  color:"oklch(0.50 0.14 145)" },
    recusada:   { label:"Recusada",   color:"oklch(0.55 0.18 25)"  },
  };
  const REASONS = {
    defeito:   "Defeito de impressão",
    erro_arte: "Erro de arte / aprovação",
    atraso:    "Atraso na entrega",
    desistencia: "Desistência do cliente",
    outro:     "Outro",
  };

  return (
    <div className="os-page vd-dev-page">
      <header className="os-head">
        <div className="os-head-l">
          <h1>Devoluções e trocas</h1>
          <p>Cancelamento de venda paga, geração de crédito e estorno</p>
        </div>
        <div className="os-head-r">
          <button className="os-btn primary" onClick={() => setCreateOpen(true)}>+ Nova devolução</button>
        </div>
      </header>

      <div className="os-kpis">
        <div className={"os-kpi" + (stats.pendentes > 0 ? " os-kpi-alert" : "")}>
          <span className="os-kpi-label">Pendentes</span>
          <span className="os-kpi-value">{stats.pendentes}</span>
          <span className="os-kpi-sub">aguardando análise</span>
        </div>
        <div className="os-kpi">
          <span className="os-kpi-label">Devoluções no mês</span>
          <span className="os-kpi-value">{stats.mes}</span>
          <span className="os-kpi-sub">total registrado</span>
        </div>
        <div className="os-kpi">
          <span className="os-kpi-label">Valor estornado</span>
          <span className="os-kpi-value">{_fmtBRL(stats.valor)}</span>
          <span className="os-kpi-sub">crédito + estorno</span>
        </div>
      </div>

      <div className="os-table-wrap">
        <table className="os-table">
          <thead>
            <tr>
              <th style={{width:90}}>Devolução</th>
              <th style={{width:100}}>Data</th>
              <th style={{width:100}}>Venda orig.</th>
              <th>Cliente</th>
              <th>Motivo</th>
              <th style={{width:120}}>Tipo retorno</th>
              <th style={{width:120}}>Total</th>
              <th style={{width:120}}>Status</th>
            </tr>
          </thead>
          <tbody>
            {list.map(d => (
              <tr key={d.id} className="os-row" onClick={() => setOpenId(d.id)}>
                <td className="vd-id">#{d.id}</td>
                <td>{d.date.slice(8,10)}/{d.date.slice(5,7)}</td>
                <td className="vd-id">#{d.vendaId}</td>
                <td><strong>{d.client}</strong></td>
                <td>{REASONS[d.reason]}</td>
                <td>{d.refund}</td>
                <td className="vd-total">{_fmtBRL(d.total)}</td>
                <td>
                  <span className="os-stage" style={{ background: STATUS[d.status].color + "1f", color: STATUS[d.status].color }}>
                    {STATUS[d.status].label}
                  </span>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>

      {open && (
        <div className="os-drawer-back" onClick={() => setOpenId(null)}>
          <aside className="os-drawer wide" onClick={e => e.stopPropagation()}>
            <header className="os-drawer-head">
              <div className="os-drawer-head-l">
                <span className="os-drawer-id">Devolução #{open.id}</span>
                <h2>{open.client}</h2>
                <p>Venda original #{open.vendaId} · {open.date.split("-").reverse().join("/")} · solicitado por {open.user}</p>
              </div>
              <div className="os-drawer-head-r">
                <span className="os-stage" style={{ background: STATUS[open.status].color + "1f", color: STATUS[open.status].color }}>
                  {STATUS[open.status].label}
                </span>
                <button className="icon-btn" onClick={() => setOpenId(null)}>×</button>
              </div>
            </header>
            <div className="os-drawer-body">
              <section className="vd-section">
                <h3>Motivo</h3>
                <p className="vdv-reason">{REASONS[open.reason]}</p>
              </section>
              <section className="vd-section">
                <h3>Resolução</h3>
                <dl className="vd-meta">
                  <dt>Itens devolvidos</dt><dd>{open.items}</dd>
                  <dt>Tipo de retorno</dt><dd>{open.refund}</dd>
                  <dt>Valor</dt><dd className="vd-total-strong">{_fmtBRL(open.total)}</dd>
                </dl>
              </section>
              <section className="vd-section">
                <h3>Linha do tempo</h3>
                <ul className="vdv-timeline">
                  <li><span className="vdv-tl-dot ok"/>Solicitação registrada · 08:42</li>
                  <li><span className="vdv-tl-dot active"/>Análise pelo gestor · pendente</li>
                  <li><span className="vdv-tl-dot"/>Estorno / crédito processado</li>
                  <li><span className="vdv-tl-dot"/>NF-e de devolução emitida</li>
                </ul>
              </section>
            </div>
            <footer className="os-drawer-actions">
              {open.status === "em_analise" && (
                <>
                  <button className="os-btn" style={{ color:"oklch(0.55 0.18 25)" }}>Recusar</button>
                  <button className="os-btn primary">Aprovar e estornar</button>
                </>
              )}
              <button className="os-btn ghost">Imprimir comprovante</button>
            </footer>
          </aside>
        </div>
      )}

      {createOpen && <DevolucaoCreateDrawer onClose={() => setCreateOpen(false)} onSave={(d) => { setList(prev => [d, ...prev]); setCreateOpen(false); }}/>}
    </div>
  );
}

function DevolucaoCreateDrawer({ onClose, onSave }) {
  const { VENDAS_LIST } = window.VENDAS_DATA;
  const [vendaId, setVendaId] = useStateVE("");
  const [reason, setReason] = useStateVE("defeito");
  const [refund, setRefund] = useStateVE("PIX");
  const [items, setItems] = useStateVE(1);
  const [note, setNote] = useStateVE("");
  const venda = VENDAS_LIST.find(v => v.id === vendaId);

  const matches = vendaId.trim() ? VENDAS_LIST.filter(v =>
    v.id.toLowerCase().includes(vendaId.toLowerCase()) ||
    v.client.toLowerCase().includes(vendaId.toLowerCase())
  ).slice(0,5) : [];

  return (
    <div className="os-drawer-back" onClick={onClose}>
      <aside className="os-drawer" onClick={e => e.stopPropagation()}>
        <header className="os-drawer-head">
          <div className="os-drawer-head-l">
            <span className="os-drawer-id">Nova devolução</span>
            <h2>Selecione a venda original</h2>
            <p>Apenas vendas <strong>pagas</strong> ou <strong>faturadas</strong> podem ser devolvidas</p>
          </div>
          <button className="icon-btn" onClick={onClose}>×</button>
        </header>
        <div className="os-drawer-body">
          <section className="vd-section">
            <h3>Venda</h3>
            <input className="vdv-input" placeholder="Buscar por código ou cliente..."
                   value={vendaId} onChange={e => setVendaId(e.target.value)}/>
            {matches.length > 0 && !venda && (
              <div className="vdv-matches">
                {matches.map(v => (
                  <button key={v.id} className="vdv-match" onClick={() => setVendaId(v.id)}>
                    <span className="vd-id">#{v.id}</span>
                    <span>{v.client}</span>
                    <span className="vd-total">{v.total}</span>
                  </button>
                ))}
              </div>
            )}
            {venda && (
              <div className="vdv-selected">
                <strong>{venda.client}</strong>
                <span className="vd-total">{venda.total}</span>
                <span className="vd-meta">{venda.notes}</span>
              </div>
            )}
          </section>
          <section className="vd-section">
            <h3>Motivo</h3>
            <div className="vdv-reasons">
              {["defeito","erro_arte","atraso","desistencia","outro"].map(r => (
                <button key={r} className={"vdv-reason-btn" + (reason === r ? " active" : "")} onClick={() => setReason(r)}>
                  {{defeito:"Defeito impressão",erro_arte:"Erro de arte",atraso:"Atraso entrega",desistencia:"Desistência",outro:"Outro"}[r]}
                </button>
              ))}
            </div>
            <textarea className="vdv-textarea" placeholder="Detalhes (opcional)..." value={note} onChange={e => setNote(e.target.value)}/>
          </section>
          <section className="vd-section">
            <h3>Resolução</h3>
            <div className="vd-fields">
              <label>Itens a devolver
                <input type="number" min="1" max={venda?.items || 99} value={items} onChange={e => setItems(parseInt(e.target.value)||1)}/>
              </label>
              <label>Tipo de retorno
                <select value={refund} onChange={e => setRefund(e.target.value)}>
                  <option>PIX</option>
                  <option>Estorno cartão</option>
                  <option>Crédito em conta</option>
                  <option>Troca por outro produto</option>
                </select>
              </label>
            </div>
          </section>
        </div>
        <footer className="os-drawer-actions">
          <button className="os-btn ghost" onClick={onClose}>Cancelar</button>
          <button className="os-btn primary" disabled={!venda} onClick={() => {
            onSave({
              id:"D-" + (200 + Math.floor(Math.random()*100)),
              date: new Date().toISOString().slice(0,10),
              vendaId: venda.id,
              client: venda.client,
              reason, refund, items,
              total: _parseBRL(venda.total) * (items / Math.max(1, venda.items)),
              status:"em_analise",
              user:"Larissa",
            });
          }}>Registrar devolução</button>
        </footer>
      </aside>
    </div>
  );
}

// ═══════════════════════════════════════════════════════════════════
// 3) COMISSÕES — relatório por vendedor
// ═══════════════════════════════════════════════════════════════════
function VendasComissoesPage() {
  const { VENDAS_LIST } = window.VENDAS_DATA;
  const [period, setPeriod] = useStateVE("mes");

  // Regras de comissão por forma de pagamento
  const COM_RATES = { "PIX":3, "Dinheiro":3, "Cartão":2, "Boleto 30d":2.5, "Boleto 60d":2, "Transferência":2.5 };

  const sellers = useMemoVE(() => {
    const map = {};
    VENDAS_LIST.filter(v => v.status !== "cancelada").forEach(v => {
      const s = v.seller;
      if (!map[s]) map[s] = { name:s, vendas:0, faturado:0, comissao:0, ticketMaior:0, formas:{} };
      const total = _parseBRL(v.total);
      const rate = COM_RATES[v.payment] || 2;
      map[s].vendas += 1;
      map[s].faturado += total;
      map[s].comissao += total * (rate/100);
      map[s].ticketMaior = Math.max(map[s].ticketMaior, total);
      map[s].formas[v.payment] = (map[s].formas[v.payment] || 0) + 1;
    });
    return Object.values(map).sort((a,b) => b.faturado - a.faturado);
  }, [VENDAS_LIST]);

  const totalGeral = sellers.reduce((s,x) => s + x.faturado, 0);
  const maxFat = Math.max(1, ...sellers.map(s => s.faturado));

  return (
    <div className="os-page vco-page">
      <header className="os-head">
        <div className="os-head-l">
          <h1>Comissões</h1>
          <p>Performance por vendedor · faturamento e comissão calculada por forma de pagamento</p>
        </div>
        <div className="os-head-r">
          <div className="vco-period">
            {[{id:"semana",l:"Semana"},{id:"mes",l:"Mês"},{id:"trimestre",l:"Trimestre"}].map(p => (
              <button key={p.id} className={period === p.id ? "active" : ""} onClick={() => setPeriod(p.id)}>{p.l}</button>
            ))}
          </div>
          <button className="os-btn ghost">Exportar CSV</button>
        </div>
      </header>

      <div className="os-kpis">
        <div className="os-kpi">
          <span className="os-kpi-label">Faturamento</span>
          <span className="os-kpi-value">{_fmtBRL(totalGeral)}</span>
          <span className="os-kpi-sub">{period}</span>
        </div>
        <div className="os-kpi">
          <span className="os-kpi-label">Comissão a pagar</span>
          <span className="os-kpi-value">{_fmtBRL(sellers.reduce((s,x) => s + x.comissao, 0))}</span>
          <span className="os-kpi-sub">total time</span>
        </div>
        <div className="os-kpi">
          <span className="os-kpi-label">Vendedores ativos</span>
          <span className="os-kpi-value">{sellers.length}</span>
          <span className="os-kpi-sub">com vendas no período</span>
        </div>
        <div className="os-kpi">
          <span className="os-kpi-label">Top vendedor</span>
          <span className="os-kpi-value" style={{ fontSize:18 }}>{sellers[0]?.name.split(" ")[0] || "—"}</span>
          <span className="os-kpi-sub">{_fmtBRL(sellers[0]?.faturado || 0)}</span>
        </div>
      </div>

      <div className="vco-grid">
        {sellers.map(s => (
          <article key={s.name} className="vco-card">
            <header>
              <div className="vco-avatar">{s.name.split(" ").map(w => w[0]).slice(0,2).join("")}</div>
              <div>
                <h3>{s.name}</h3>
                <span>{s.vendas} venda{s.vendas !== 1 ? "s" : ""} · ticket maior {_fmtBRL(s.ticketMaior)}</span>
              </div>
              <div className="vco-rank">#{sellers.indexOf(s)+1}</div>
            </header>
            <div className="vco-bar">
              <div className="vco-bar-fill" style={{ width: (100*s.faturado/maxFat) + "%" }}/>
            </div>
            <div className="vco-numbers">
              <div>
                <span>Faturado</span>
                <strong>{_fmtBRL(s.faturado)}</strong>
              </div>
              <div className="vco-com">
                <span>Comissão</span>
                <strong>{_fmtBRL(s.comissao)}</strong>
              </div>
            </div>
            <div className="vco-mix">
              {Object.entries(s.formas).map(([k,n]) => (
                <span key={k} className="vco-chip">{k} <em>{n}</em></span>
              ))}
            </div>
          </article>
        ))}
      </div>

      <section className="vco-table-card">
        <h3>Regras de comissão</h3>
        <table className="vco-rules">
          <thead><tr><th>Forma de pagamento</th><th>% comissão</th><th>Critério</th></tr></thead>
          <tbody>
            {Object.entries(COM_RATES).map(([k,v]) => (
              <tr key={k}><td>{k}</td><td>{v.toFixed(1)}%</td><td className="vd-meta">sobre o total da venda</td></tr>
            ))}
          </tbody>
        </table>
      </section>
    </div>
  );
}

// ═══════════════════════════════════════════════════════════════════
// 4) RELATÓRIOS — gráficos + filtros + export
// ═══════════════════════════════════════════════════════════════════
function VendasRelatoriosPage() {
  const { VENDAS_LIST } = window.VENDAS_DATA;
  const [view, setView] = useStateVE("periodo");

  // Por dia
  const byDay = useMemoVE(() => {
    const map = {};
    VENDAS_LIST.filter(v => v.status !== "cancelada").forEach(v => {
      map[v.date] = (map[v.date] || 0) + _parseBRL(v.total);
    });
    return Object.entries(map).sort((a,b) => a[0].localeCompare(b[0])).map(([d,t]) => ({ date:d, total:t }));
  }, [VENDAS_LIST]);

  const maxDay = Math.max(1, ...byDay.map(d => d.total));
  const totalPeriodo = byDay.reduce((s,d) => s + d.total, 0);
  const ticketMedio = totalPeriodo / Math.max(1, VENDAS_LIST.filter(v => v.status !== "cancelada").length);

  // Por cliente
  const byClient = useMemoVE(() => {
    const map = {};
    VENDAS_LIST.filter(v => v.status !== "cancelada").forEach(v => {
      if (!map[v.client]) map[v.client] = { client:v.client, n:0, total:0 };
      map[v.client].n += 1;
      map[v.client].total += _parseBRL(v.total);
    });
    return Object.values(map).sort((a,b) => b.total - a.total);
  }, [VENDAS_LIST]);
  const maxClient = Math.max(1, ...byClient.map(c => c.total));

  // Por origem
  const byOrigin = useMemoVE(() => {
    const map = { balcão:0, orçamento:0 };
    VENDAS_LIST.filter(v => v.status !== "cancelada").forEach(v => {
      map[v.origin] = (map[v.origin] || 0) + _parseBRL(v.total);
    });
    return map;
  }, [VENDAS_LIST]);
  const totalOrigin = byOrigin.balcão + byOrigin.orçamento;

  return (
    <div className="os-page vrep-page">
      <header className="os-head">
        <div className="os-head-l">
          <h1>Relatórios de vendas</h1>
          <p>Vendas por período · cliente · origem · vendedor</p>
        </div>
        <div className="os-head-r">
          <button className="os-btn ghost">Exportar CSV</button>
          <button className="os-btn ghost">PDF</button>
        </div>
      </header>

      <nav className="vrep-tabs">
        {[
          {id:"periodo",  l:"Por período"},
          {id:"cliente",  l:"Por cliente"},
          {id:"origem",   l:"Origem"},
        ].map(t => (
          <button key={t.id} className={view === t.id ? "active" : ""} onClick={() => setView(t.id)}>{t.l}</button>
        ))}
      </nav>

      <div className="vrep-summary">
        <div><span>Total no período</span><strong>{_fmtBRL(totalPeriodo)}</strong></div>
        <div><span>Vendas</span><strong>{VENDAS_LIST.filter(v => v.status !== "cancelada").length}</strong></div>
        <div><span>Ticket médio</span><strong>{_fmtBRL(ticketMedio)}</strong></div>
        <div><span>Maior venda</span><strong>{_fmtBRL(Math.max(...VENDAS_LIST.map(v => _parseBRL(v.total))))}</strong></div>
      </div>

      {view === "periodo" && (
        <section className="vrep-card">
          <h3>Faturamento por dia</h3>
          <div className="vrep-bars">
            {byDay.map(d => (
              <div key={d.date} className="vrep-bar-col">
                <span className="vrep-bar-val">{_fmtBRL(d.total)}</span>
                <div className="vrep-bar" style={{ height: (100*d.total/maxDay) + "%" }}/>
                <span className="vrep-bar-lbl">{d.date.slice(8,10)}/{d.date.slice(5,7)}</span>
              </div>
            ))}
          </div>
        </section>
      )}

      {view === "cliente" && (
        <section className="vrep-card">
          <h3>Top clientes por faturamento</h3>
          <ul className="vrep-clients">
            {byClient.map((c,i) => (
              <li key={c.client}>
                <span className="vrep-rank">{i+1}</span>
                <span className="vrep-cli-name">{c.client}</span>
                <span className="vrep-cli-n">{c.n} venda{c.n !== 1 ? "s" : ""}</span>
                <div className="vrep-cli-bar"><div style={{ width: (100*c.total/maxClient) + "%" }}/></div>
                <span className="vrep-cli-total">{_fmtBRL(c.total)}</span>
              </li>
            ))}
          </ul>
        </section>
      )}

      {view === "origem" && (
        <section className="vrep-card">
          <h3>Origem da venda</h3>
          <div className="vrep-origin">
            <div className="vrep-donut" style={{
              background: `conic-gradient(
                var(--accent) 0 ${360*byOrigin.balcão/totalOrigin}deg,
                oklch(0.85 0.06 145) ${360*byOrigin.balcão/totalOrigin}deg 360deg)`
            }}>
              <span>{Math.round(100*byOrigin.balcão/totalOrigin)}%</span>
              <small>balcão</small>
            </div>
            <dl className="vrep-origin-legend">
              <dt><span className="vrep-dot" style={{ background:"var(--accent)" }}/>Balcão</dt>
              <dd>{_fmtBRL(byOrigin.balcão)} · {Math.round(100*byOrigin.balcão/totalOrigin)}%</dd>
              <dt><span className="vrep-dot" style={{ background:"oklch(0.65 0.10 145)" }}/>Orçamento</dt>
              <dd>{_fmtBRL(byOrigin.orçamento)} · {Math.round(100*byOrigin.orçamento/totalOrigin)}%</dd>
            </dl>
          </div>
        </section>
      )}
    </div>
  );
}

// ═══════════════════════════════════════════════════════════════════
// 5) PDV BALCÃO — overlay full-screen
// ═══════════════════════════════════════════════════════════════════
function VendasPDVOverlay({ onClose }) {
  const { OS_PRODUCTS } = window.OS_DATA;
  const { VENDAS_PAYMENTS } = window.VENDAS_DATA;
  const [items, setItems] = useStateVE([]);
  const [scan, setScan] = useStateVE("");
  const [client, setClient] = useStateVE("Consumidor Final");
  const [payment, setPayment] = useStateVE("pix");
  const [recibo, setRecibo] = useStateVE(null);
  const inputRef = useRefVE(null);

  useEffectVE(() => {
    const onKey = (e) => {
      if (e.key === "Escape") { e.preventDefault(); onClose(); }
      if (e.key === "F4") { e.preventDefault(); finalize(); }
    };
    window.addEventListener("keydown", onKey);
    inputRef.current?.focus();
    return () => window.removeEventListener("keydown", onKey);
  // eslint-disable-next-line
  }, [items, payment]);

  const matches = scan.trim() ? OS_PRODUCTS.filter(p =>
    (p.name || p.label || "").toLowerCase().includes(scan.toLowerCase())
  ).slice(0,4) : [];

  const addItem = (p) => {
    const price = _parseBRL(p.price);
    setItems(prev => {
      const exists = prev.find(it => it.product === (p.name || p.label));
      if (exists) return prev.map(it => it === exists ? { ...it, qty: it.qty + 1 } : it);
      return [...prev, { key: Date.now()+Math.random(), product: p.name || p.label, qty:1, unitPrice: price }];
    });
    setScan("");
    inputRef.current?.focus();
  };

  const total = items.reduce((s,it) => s + it.unitPrice * it.qty, 0);

  const finalize = () => {
    if (items.length === 0) return;
    setRecibo({
      id: "V-" + (8000 + Math.floor(Math.random()*999)),
      date: new Date().toLocaleDateString("pt-BR"),
      time: new Date().toLocaleTimeString("pt-BR", { hour:"2-digit", minute:"2-digit" }),
      client, payment: VENDAS_PAYMENTS.find(p => p.id === payment)?.label || "PIX",
      items, total, seller: "Larissa",
    });
  };

  if (recibo) return <ReciboView recibo={recibo} onNew={() => { setItems([]); setRecibo(null); inputRef.current?.focus(); }} onClose={onClose}/>;

  return (
    <div className="pdv-overlay">
      <header className="pdv-head">
        <div className="pdv-head-l">
          <span className="pdv-brand">PDV BALCÃO</span>
          <span className="pdv-sep">·</span>
          <span>{new Date().toLocaleDateString("pt-BR")} {new Date().toLocaleTimeString("pt-BR", { hour:"2-digit", minute:"2-digit" })}</span>
          <span className="pdv-sep">·</span>
          <span>op: Larissa</span>
        </div>
        <div className="pdv-head-r">
          <span className="pdv-shortcut"><kbd>F4</kbd> finalizar</span>
          <span className="pdv-shortcut"><kbd>Esc</kbd> sair</span>
          <button className="pdv-close" onClick={onClose}>×</button>
        </div>
      </header>

      <div className="pdv-grid">
        <div className="pdv-left">
          <div className="pdv-scan">
            <span className="pdv-scan-label">Código ou nome do produto</span>
            <input ref={inputRef} className="pdv-scan-input" value={scan}
                   placeholder="Bipe ou digite..."
                   onChange={e => setScan(e.target.value)}
                   onKeyDown={e => { if (e.key === "Enter" && matches[0]) addItem(matches[0]); }}/>
            {matches.length > 0 && (
              <ul className="pdv-suggest">
                {matches.map((p,i) => (
                  <li key={i} onClick={() => addItem(p)}>
                    <span>{p.name || p.label}</span>
                    <strong>{p.price}</strong>
                  </li>
                ))}
              </ul>
            )}
          </div>

          <div className="pdv-items-wrap">
            {items.length === 0 ? (
              <div className="pdv-empty">
                <div className="pdv-empty-big">Aguardando primeiro item</div>
                <div>Bipe um código ou digite o nome no campo acima</div>
              </div>
            ) : (
              <table className="pdv-items">
                <thead><tr><th style={{width:60}}>Qtd</th><th>Produto</th><th style={{width:140}}>Unit.</th><th style={{width:140}}>Subtotal</th><th style={{width:40}}/></tr></thead>
                <tbody>
                  {items.map(it => (
                    <tr key={it.key}>
                      <td>
                        <div className="pdv-qty">
                          <button onClick={() => setItems(prev => prev.map(x => x.key === it.key ? {...x, qty: Math.max(1, x.qty-1)} : x))}>−</button>
                          <span>{it.qty}</span>
                          <button onClick={() => setItems(prev => prev.map(x => x.key === it.key ? {...x, qty: x.qty+1} : x))}>+</button>
                        </div>
                      </td>
                      <td className="pdv-prod">{it.product}</td>
                      <td className="pdv-num">{_fmtBRL(it.unitPrice)}</td>
                      <td className="pdv-num strong">{_fmtBRL(it.unitPrice * it.qty)}</td>
                      <td><button className="pdv-rm" onClick={() => setItems(prev => prev.filter(x => x.key !== it.key))}>×</button></td>
                    </tr>
                  ))}
                </tbody>
              </table>
            )}
          </div>
        </div>

        <aside className="pdv-right">
          <div className="pdv-client">
            <span className="pdv-r-label">Cliente</span>
            <input value={client} onChange={e => setClient(e.target.value)}/>
          </div>

          <div className="pdv-pay">
            <span className="pdv-r-label">Pagamento</span>
            <div className="pdv-pay-grid">
              {VENDAS_PAYMENTS.slice(0,4).map(p => (
                <button key={p.id} className={"pdv-pay-btn" + (payment === p.id ? " active" : "")} onClick={() => setPayment(p.id)}>
                  <span className="pdv-pay-icon">{p.icon}</span>
                  <span>{p.label}</span>
                </button>
              ))}
            </div>
          </div>

          <div className="pdv-total-block">
            <span className="pdv-total-label">TOTAL</span>
            <strong className="pdv-total-value">{_fmtBRL(total)}</strong>
            <span className="pdv-total-meta">{items.reduce((s,it) => s + it.qty, 0)} itens</span>
          </div>

          <button className="pdv-finalize" disabled={items.length === 0} onClick={finalize}>
            Finalizar venda <kbd>F4</kbd>
          </button>
          <button className="pdv-cancel" onClick={() => setItems([])}>Cancelar tudo</button>
        </aside>
      </div>
    </div>
  );
}

// ═══════════════════════════════════════════════════════════════════
// 6) RECIBO / CUPOM (térmica + A4)
// ═══════════════════════════════════════════════════════════════════
function ReciboView({ recibo, onNew, onClose }) {
  const [layout, setLayout] = useStateVE("termica");
  return (
    <div className="pdv-overlay pdv-recibo-overlay">
      <header className="pdv-head">
        <div className="pdv-head-l">
          <span className="pdv-brand">RECIBO #{recibo.id}</span>
          <span className="pdv-sep">·</span>
          <span>{recibo.date} {recibo.time}</span>
        </div>
        <div className="pdv-head-r">
          <div className="rec-layout">
            <button className={layout === "termica" ? "active" : ""} onClick={() => setLayout("termica")}>Térmica 80mm</button>
            <button className={layout === "a4" ? "active" : ""} onClick={() => setLayout("a4")}>A4</button>
          </div>
          <button className="os-btn ghost" onClick={() => window.print()}>Imprimir</button>
          <button className="os-btn primary" onClick={onNew}>Nova venda</button>
          <button className="pdv-close" onClick={onClose}>×</button>
        </div>
      </header>
      <div className="rec-stage">
        {layout === "termica" ? <ReciboTermica recibo={recibo}/> : <ReciboA4 recibo={recibo}/>}
      </div>
    </div>
  );
}

function ReciboTermica({ recibo }) {
  return (
    <div className="rec-paper rec-termica">
      <div className="rec-tx-header">
        <div className="rec-tx-title">OIMPRESSO COMUNICAÇÃO VISUAL</div>
        <div>CNPJ 12.345.678/0001-90</div>
        <div>Rua das Gráficas, 123 — Centro</div>
        <div>—————————————————</div>
        <div>CUPOM NÃO FISCAL</div>
        <div>—————————————————</div>
      </div>
      <div className="rec-tx-meta">
        <div>Venda: <b>#{recibo.id}</b></div>
        <div>Data: {recibo.date} {recibo.time}</div>
        <div>Op.: {recibo.seller}</div>
        <div>Cliente: {recibo.client}</div>
      </div>
      <div className="rec-tx-divider">— — — — — — — — — — — —</div>
      <table className="rec-tx-items">
        {recibo.items.map(it => (
          <React.Fragment key={it.key}>
            <tr><td colSpan="3" className="rec-tx-prod">{it.product}</td></tr>
            <tr>
              <td>{it.qty} × {_fmtBRL(it.unitPrice)}</td>
              <td/>
              <td className="rec-tx-r">{_fmtBRL(it.unitPrice * it.qty)}</td>
            </tr>
          </React.Fragment>
        ))}
      </table>
      <div className="rec-tx-divider">— — — — — — — — — — — —</div>
      <div className="rec-tx-totals">
        <div><span>SUBTOTAL</span><span>{_fmtBRL(recibo.total)}</span></div>
        <div className="rec-tx-total"><span>TOTAL</span><span>{_fmtBRL(recibo.total)}</span></div>
      </div>
      <div className="rec-tx-pay">Pagamento: <b>{recibo.payment}</b></div>
      <div className="rec-tx-divider">— — — — — — — — — — — —</div>
      <div className="rec-tx-foot">
        <div>Obrigado pela preferência!</div>
        <div>oimpresso.com</div>
        <div className="rec-tx-barcode">||| | ||| || | || ||| | |</div>
      </div>
    </div>
  );
}

function ReciboA4({ recibo }) {
  return (
    <div className="rec-paper rec-a4">
      <header className="rec-a4-h">
        <div>
          <div className="rec-a4-logo">OIMPRESSO</div>
          <div className="rec-a4-tag">Comunicação Visual</div>
        </div>
        <div className="rec-a4-meta">
          <div>CNPJ 12.345.678/0001-90</div>
          <div>Rua das Gráficas, 123 — Centro</div>
          <div>(11) 4000-1234 · oimpresso.com</div>
        </div>
      </header>
      <div className="rec-a4-title">
        <h1>RECIBO DE VENDA #{recibo.id}</h1>
        <span>{recibo.date} às {recibo.time}</span>
      </div>
      <section className="rec-a4-block">
        <h3>Cliente</h3>
        <div>{recibo.client}</div>
      </section>
      <section className="rec-a4-block">
        <h3>Itens</h3>
        <table className="rec-a4-items">
          <thead><tr><th>Produto</th><th>Qtd</th><th>Unit.</th><th>Subtotal</th></tr></thead>
          <tbody>
            {recibo.items.map(it => (
              <tr key={it.key}>
                <td>{it.product}</td>
                <td>{it.qty}</td>
                <td>{_fmtBRL(it.unitPrice)}</td>
                <td>{_fmtBRL(it.unitPrice * it.qty)}</td>
              </tr>
            ))}
          </tbody>
        </table>
      </section>
      <div className="rec-a4-totals">
        <dl>
          <dt>Subtotal</dt><dd>{_fmtBRL(recibo.total)}</dd>
          <dt>Pagamento</dt><dd>{recibo.payment}</dd>
          <dt className="rec-a4-total">Total</dt><dd className="rec-a4-total">{_fmtBRL(recibo.total)}</dd>
        </dl>
      </div>
      <footer className="rec-a4-foot">
        <div>Atendido por {recibo.seller}</div>
        <div>Obrigado pela preferência. Documento não fiscal.</div>
      </footer>
    </div>
  );
}

// ═══════════════════════════════════════════════════════════════════
// 7) NF-e DRAWER — emissão fiscal
// ═══════════════════════════════════════════════════════════════════
function NFeDrawer({ venda, onClose }) {
  const [step, setStep] = useStateVE(1);
  const [cfop, setCfop] = useStateVE("5102");
  const [ncm, setNcm] = useStateVE("4911.99.00");
  const [transp, setTransp] = useStateVE("propria");
  const [obs, setObs] = useStateVE("");

  const STEPS = ["Dados fiscais", "Transporte", "Revisão"];
  const CFOPS = [
    { code:"5102", label:"Venda mercadoria UF" },
    { code:"5101", label:"Venda produção UF" },
    { code:"6102", label:"Venda mercadoria fora UF" },
  ];

  const fakeNumber = "000.001." + (Math.floor(Math.random()*9000)+1000);
  const fakeKey = "35260512345678000190550010000" + fakeNumber.replace(/\./g,"") + "180000001";

  return (
    <div className="os-drawer-back" onClick={onClose}>
      <aside className="os-drawer wide" onClick={e => e.stopPropagation()}>
        <header className="os-drawer-head">
          <div className="os-drawer-head-l">
            <span className="os-drawer-id">NF-e · Venda #{venda?.id || "—"}</span>
            <h2>Emissão de Nota Fiscal Eletrônica</h2>
            <p>Cliente: {venda?.client || "—"} · Total: {venda?.total || "—"}</p>
          </div>
          <button className="icon-btn" onClick={onClose}>×</button>
        </header>

        <nav className="vd-stepper">
          {STEPS.map((s,i) => (
            <button key={i} className={"vd-step" + (step === i+1 ? " active" : "") + (step > i+1 ? " done" : "")} onClick={() => setStep(i+1)}>
              <span className="vd-step-num">{step > i+1 ? "✓" : i+1}</span>
              <span>{s}</span>
              {i < STEPS.length-1 && <span className="vd-step-sep">›</span>}
            </button>
          ))}
        </nav>

        <div className="os-drawer-body">
          {step === 1 && (
            <section className="vd-section">
              <h3>Natureza da operação</h3>
              <div className="nfe-cfop">
                {CFOPS.map(c => (
                  <button key={c.code} className={"nfe-cfop-btn" + (cfop === c.code ? " active" : "")} onClick={() => setCfop(c.code)}>
                    <strong>CFOP {c.code}</strong>
                    <span>{c.label}</span>
                  </button>
                ))}
              </div>
              <div className="vd-fields">
                <label>NCM
                  <input value={ncm} onChange={e => setNcm(e.target.value)}/>
                </label>
                <label>Origem mercadoria
                  <select defaultValue="0">
                    <option value="0">0 — Nacional</option>
                    <option value="1">1 — Importação direta</option>
                    <option value="2">2 — Importação adquirida no mercado interno</option>
                  </select>
                </label>
              </div>
              <h3>Tributação</h3>
              <div className="nfe-tax-grid">
                <div><span>ICMS</span><strong>Simples Nacional</strong></div>
                <div><span>CSOSN</span><strong>102 — sem permissão crédito</strong></div>
                <div><span>PIS / COFINS</span><strong>incluso no Simples</strong></div>
                <div><span>IPI</span><strong>NT — não tributado</strong></div>
              </div>
            </section>
          )}

          {step === 2 && (
            <section className="vd-section">
              <h3>Modalidade de frete</h3>
              <div className="nfe-transp">
                {[
                  { id:"propria",  l:"Por conta do emitente",  meta:"frete CIF — somos responsáveis"  },
                  { id:"cliente",  l:"Por conta do destinatário", meta:"frete FOB — cliente retira"   },
                  { id:"terceiro", l:"Por conta de terceiros",  meta:"transportadora cadastrada"      },
                  { id:"sem",      l:"Sem frete",                meta:"entrega no balcão"              },
                ].map(t => (
                  <button key={t.id} className={"nfe-transp-btn" + (transp === t.id ? " active" : "")} onClick={() => setTransp(t.id)}>
                    <strong>{t.l}</strong>
                    <span>{t.meta}</span>
                  </button>
                ))}
              </div>
              {transp === "terceiro" && (
                <div className="vd-fields">
                  <label>Transportadora
                    <select>
                      <option>Logística Sul</option>
                      <option>Expresso Rodoviário Brasil</option>
                      <option>Mercúrio Cargas</option>
                    </select>
                  </label>
                  <label>Veículo (placa)
                    <input placeholder="ABC-1234"/>
                  </label>
                </div>
              )}
              <h3>Volume</h3>
              <div className="vd-fields">
                <label>Quantidade<input type="number" defaultValue="1"/></label>
                <label>Espécie<input defaultValue="caixa"/></label>
                <label>Peso bruto (kg)<input type="number" step="0.1" defaultValue="2.5"/></label>
                <label>Peso líquido (kg)<input type="number" step="0.1" defaultValue="2.0"/></label>
              </div>
              <h3>Observações</h3>
              <textarea className="vdv-textarea" placeholder="Informação complementar..." value={obs} onChange={e => setObs(e.target.value)}/>
            </section>
          )}

          {step === 3 && (
            <section className="vd-section nfe-review">
              <h3>Revise antes de transmitir à SEFAZ</h3>
              <div className="nfe-review-grid">
                <div className="nfe-review-card">
                  <span>Número provisório</span>
                  <strong className="mono">{fakeNumber}</strong>
                </div>
                <div className="nfe-review-card">
                  <span>CFOP</span>
                  <strong>{cfop}</strong>
                </div>
                <div className="nfe-review-card">
                  <span>NCM</span>
                  <strong>{ncm}</strong>
                </div>
                <div className="nfe-review-card">
                  <span>Frete</span>
                  <strong>{ {propria:"Emitente",cliente:"Destinatário",terceiro:"Terceiros",sem:"Sem frete"}[transp] }</strong>
                </div>
                <div className="nfe-review-card span">
                  <span>Chave de acesso (preview)</span>
                  <strong className="mono small">{fakeKey}</strong>
                </div>
                <div className="nfe-review-card">
                  <span>Total da nota</span>
                  <strong className="vd-total-strong">{venda?.total || "—"}</strong>
                </div>
                <div className="nfe-review-card">
                  <span>Cliente</span>
                  <strong>{venda?.client || "—"}</strong>
                </div>
              </div>
              <div className="nfe-callout">
                A nota será transmitida em <strong>ambiente de produção</strong> e enviada por e-mail ao cliente. Cancelamento permitido em até 24h.
              </div>
            </section>
          )}
        </div>

        <footer className="os-drawer-actions">
          {step > 1 && <button className="os-btn ghost" onClick={() => setStep(step-1)}>← Voltar</button>}
          {step < 3 && <button className="os-btn primary" onClick={() => setStep(step+1)}>Avançar →</button>}
          {step === 3 && <button className="os-btn primary">Transmitir SEFAZ</button>}
        </footer>
      </aside>
    </div>
  );
}

// Expose globals
Object.assign(window, {
  VendasModule,
  VendasCaixaPage,
  VendasDevolucoesPage,
  VendasComissoesPage,
  VendasRelatoriosPage,
  VendasPDVOverlay,
  ReciboView,
  NFeDrawer,
});
