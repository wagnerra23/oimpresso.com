// vendas-page.jsx — Vendas/Index + Vendas/Create (P0 do protocolo PR #295)
const { useState: useStateV, useMemo: useMemoV, useEffect: useEffectV } = React;

// ── Transportadoras (mock) — derivadas do id da venda + total + cliente ──
const TRANSPORTADORAS = [
  "Retira no balcão",
  "Frete próprio",
  "Jadlog",
  "Correios PAC",
  "Correios SEDEX",
  "Transp. Patrus",
  "TransRoma",
  "Braspress",
];
function pickTransp(v) {
  if (v.transp) return v.transp; // permite sobrescrever via mock se quiser
  const total = parseFloat(String(v.total).replace(/[^\d,]/g,"").replace(",","."));
  if (v.client === "Consumidor Final") return "Retira no balcão";
  if (total < 500)  return "Retira no balcão";
  if (total < 1500) return "Frete próprio";
  // Hash determinístico do id pra cair em uma transportadora
  const n = parseInt((v.id.match(/\d+/) || ["0"])[0], 10);
  return TRANSPORTADORAS[2 + (n % 6)];
}

// ───────────── INDEX ─────────────
function VendasListPage() {
  const { VENDAS_LIST, VENDAS_STATUS } = window.VENDAS_DATA;
  const [statusFilter, setStatusFilter] = useStateV("todas");
  const [query, setQuery] = useStateV("");
  const [openId, setOpenId] = useStateV(null);
  const [createOpen, setCreateOpen] = useStateV(false);

  // Atalho N — abre nova venda (Larissa keyboard-first)
  useEffectV(() => {
    const onKey = (e) => {
      if (e.target.tagName === "INPUT" || e.target.tagName === "TEXTAREA") return;
      if (e.key === "n" || e.key === "N") { e.preventDefault(); setCreateOpen(true); }
      if (e.key === "Escape") { setOpenId(null); setCreateOpen(false); }
    };
    window.addEventListener("keydown", onKey);
    return () => window.removeEventListener("keydown", onKey);
  }, []);

  const filtered = useMemoV(() => {
    let out = VENDAS_LIST;
    if (statusFilter !== "todas") out = out.filter(v => v.status === statusFilter);
    if (query.trim()) {
      const q = query.toLowerCase();
      out = out.filter(v =>
        v.id.toLowerCase().includes(q) ||
        v.client.toLowerCase().includes(q) ||
        v.notes.toLowerCase().includes(q) ||
        pickTransp(v).toLowerCase().includes(q)
      );
    }
    return out;
  }, [VENDAS_LIST, statusFilter, query]);

  const stats = useMemoV(() => {
    const today = VENDAS_LIST.filter(v => v.date === "2026-04-30" && v.status !== "cancelada");
    const totalDay = today.reduce((s,v) => s + parseFloat(v.total.replace(/[^\d,]/g,"").replace(",",".")), 0);
    const pending  = VENDAS_LIST.filter(v => v.status === "pendente").length;
    const tickets  = today.length;
    const avg      = tickets > 0 ? totalDay / tickets : 0;
    return { totalDay, pending, tickets, avg };
  }, [VENDAS_LIST]);

  const fmt = (n) => n.toLocaleString("pt-BR", { style:"currency", currency:"BRL" });
  const open = openId ? VENDAS_LIST.find(v => v.id === openId) : null;

  return (
    <div className="os-page vendas-page">
      <header className="os-head">
        <div className="os-head-l">
          <h1>Vendas</h1>
          <p>Pedidos confirmados, faturamento e nota fiscal</p>
        </div>
        <div className="os-head-r">
          <button className="os-btn ghost"><I.printer size={11}/>Imprimir caixa</button>
          <button className="os-btn primary" onClick={() => setCreateOpen(true)}>
            <I.plus size={11}/>Nova venda <kbd className="kbd-hint">N</kbd>
          </button>
        </div>
      </header>

      <div className="os-kpis">
        <div className="os-kpi">
          <span className="os-kpi-label">Faturado hoje</span>
          <span className="os-kpi-value">{fmt(stats.totalDay)}</span>
          <span className="os-kpi-sub">{stats.tickets} venda{stats.tickets !== 1 ? "s" : ""}</span>
        </div>
        <div className="os-kpi">
          <span className="os-kpi-label">Ticket médio</span>
          <span className="os-kpi-value">{fmt(stats.avg)}</span>
          <span className="os-kpi-sub">hoje</span>
        </div>
        <div className={"os-kpi" + (stats.pending > 0 ? " os-kpi-alert" : "")}>
          <span className="os-kpi-label">A receber</span>
          <span className="os-kpi-value">{stats.pending}</span>
          <span className="os-kpi-sub">vendas pendentes</span>
        </div>
        <div className="os-kpi">
          <span className="os-kpi-label">PIX hoje</span>
          <span className="os-kpi-value">
            {VENDAS_LIST.filter(v => v.date === "2026-04-30" && v.payment === "PIX").length}
          </span>
          <span className="os-kpi-sub">imediato</span>
        </div>
      </div>

      <div className="os-tabs">
        {["todas","paga","pendente","faturada","cancelada"].map(s => (
          <button key={s}
                  className={"os-tab" + (statusFilter === s ? " active" : "")}
                  onClick={() => setStatusFilter(s)}>
            {s === "todas" ? "Todas" : VENDAS_STATUS[s].label}
            <span className="count">
              {s === "todas" ? VENDAS_LIST.length : VENDAS_LIST.filter(v => v.status === s).length}
            </span>
          </button>
        ))}
        <div className="os-tabs-r">
          <div className="os-search">
            <I.search size={12}/>
            <input type="text" placeholder="Buscar venda, cliente, transportadora, observação..."
                   value={query} onChange={e => setQuery(e.target.value)}/>
          </div>
        </div>
      </div>

      <div className="os-table-wrap">
        <table className="os-table vendas-table">
          <thead>
            <tr>
              <th style={{width:90}}>Venda</th>
              <th style={{width:100}}>Data</th>
              <th>Cliente</th>
              <th style={{width:160}}>Transportadora</th>
              <th style={{width:140}}>Pagamento</th>
              <th style={{width:90}}>Itens</th>
              <th style={{width:120}}>Total</th>
              <th style={{width:100}}>Status</th>
              <th style={{width:100}}>OS gerada</th>
            </tr>
          </thead>
          <tbody>
            {filtered.map(v => (
              <tr key={v.id} className={"os-row" + (v.urgent ? " urgent" : "")} onClick={() => setOpenId(v.id)}>
                <td className="vd-id">#{v.id}</td>
                <td className="vd-date">
                  <div>{v.date.slice(8,10)}/{v.date.slice(5,7)}</div>
                  <div className="vd-time">{v.time}</div>
                </td>
                <td className="vd-client">
                  <div className="vd-client-name">{v.client}</div>
                  <div className="vd-notes">{v.notes}</div>
                </td>
                <td className="vd-transp">
                  {(() => {
                    const t = pickTransp(v);
                    const isRetira = t === "Retira no balcão";
                    const isProprio = t === "Frete próprio";
                    return (
                      <span className={"vd-transp-pill" + (isRetira ? " retira" : isProprio ? " proprio" : "")}>
                        {isRetira ? "🏪" : isProprio ? "🚚" : "📦"} {t}
                      </span>
                    );
                  })()}
                </td>
                <td className="vd-pay">
                  <div>{v.payment}</div>
                  {v.installments > 1 && <div className="vd-inst">{v.installments}×</div>}
                </td>
                <td className="vd-items">{v.items}</td>
                <td className="vd-total">{v.total}</td>
                <td>
                  <span className="os-stage" style={{
                    background: VENDAS_STATUS[v.status].color + "1f",
                    color: VENDAS_STATUS[v.status].color
                  }}>
                    {VENDAS_STATUS[v.status].label}
                  </span>
                </td>
                <td className="vd-osids">
                  {v.osIds.length === 0
                    ? <span className="vd-no-os">pronta-entrega</span>
                    : v.osIds.map(id => <span key={id} className="vd-os-pill">#{id}</span>)}
                </td>
              </tr>
            ))}
            {filtered.length === 0 && (
              <tr><td colSpan={9} className="os-empty">Nenhuma venda encontrada</td></tr>
            )}
          </tbody>
        </table>
      </div>

      {open && <VendaDetailDrawer venda={open} onClose={() => setOpenId(null)}/>}
      {createOpen && <VendaCreateDrawer onClose={() => setCreateOpen(false)}/>}
    </div>
  );
}

// ───────────── DETAIL ─────────────
function VendaDetailDrawer({ venda, onClose }) {
  const { VENDAS_STATUS } = window.VENDAS_DATA;
  return (
    <div className="os-drawer-back" onClick={onClose}>
      <aside className="os-drawer wide" onClick={e => e.stopPropagation()}>
        <header className="os-drawer-head">
          <div className="os-drawer-head-l">
            <span className="os-drawer-id">Venda #{venda.id}</span>
            <h2>{venda.client}</h2>
            <p>{venda.date.slice(8,10)}/{venda.date.slice(5,7)}/{venda.date.slice(0,4)} às {venda.time} · {venda.seller}</p>
          </div>
          <div className="os-drawer-head-r">
            <span className="os-stage" style={{
              background: VENDAS_STATUS[venda.status].color + "1f",
              color: VENDAS_STATUS[venda.status].color
            }}>{VENDAS_STATUS[venda.status].label}</span>
            <button className="icon-btn" onClick={onClose}><I.close size={14}/></button>
          </div>
        </header>

        <div className="os-drawer-body">
          <section className="vd-section">
            <h3>Resumo</h3>
            <dl className="vd-meta">
              <dt>Origem</dt><dd>{venda.origin}</dd>
              <dt>Pagamento</dt><dd>{venda.payment}{venda.installments > 1 ? ` em ${venda.installments}×` : ""}</dd>
              <dt>Itens</dt><dd>{venda.items}</dd>
              <dt>Total</dt><dd className="vd-total-strong">{venda.total}</dd>
              <dt>Observações</dt><dd>{venda.notes}</dd>
            </dl>
          </section>

          {venda.osIds.length > 0 && (
            <section className="vd-section">
              <h3>Ordens de Serviço vinculadas</h3>
              <div className="vd-os-list">
                {venda.osIds.map(id => (
                  <div key={id} className="vd-os-link">
                    <span className="vd-os-pill">#{id}</span>
                    <span>Ver na produção →</span>
                  </div>
                ))}
              </div>
            </section>
          )}

          <section className="vd-section">
            <h3>Comprovantes</h3>
            <div className="vd-docs">
              <button className="os-btn ghost"><I.printer size={11}/>Imprimir recibo</button>
              <button className="os-btn ghost"><I.folder size={11}/>Emitir NF-e</button>
              <button className="os-btn ghost"><I.message size={11}/>Enviar por e-mail</button>
            </div>
          </section>
        </div>

        <footer className="os-drawer-actions">
          {venda.status === "pendente" && (
            <button className="os-btn primary"><I.check size={11}/>Confirmar pagamento</button>
          )}
          {venda.status === "paga" && (
            <button className="os-btn primary"><I.folder size={11}/>Faturar (NF-e)</button>
          )}
          <button className="os-btn ghost">Ações ▾</button>
        </footer>
      </aside>
    </div>
  );
}

// ───────────── CREATE (P0 PRINCIPAL) ─────────────
function VendaCreateDrawer({ onClose }) {
  const { OS_CLIENTS, OS_PRODUCTS } = window.OS_DATA;
  const { VENDAS_PAYMENTS } = window.VENDAS_DATA;

  // Stepper
  const [step, setStep] = useStateV(1);

  // S1: Cliente
  const [clientQuery, setClientQuery] = useStateV("");
  const [client, setClient] = useStateV(null);
  const [contact, setContact] = useStateV("");
  const [phone, setPhone] = useStateV("");

  const clientMatches = useMemoV(() => {
    if (!clientQuery.trim()) return OS_CLIENTS.slice(0, 6);
    const q = clientQuery.toLowerCase();
    return OS_CLIENTS.filter(c =>
      c.name.toLowerCase().includes(q) ||
      (c.cnpj || "").includes(q) ||
      (c.contact || "").toLowerCase().includes(q)
    ).slice(0, 8);
  }, [clientQuery, OS_CLIENTS]);

  // S2: Itens
  const [items, setItems] = useStateV([]);
  const [prodQuery, setProdQuery] = useStateV("");
  const prodMatches = useMemoV(() => {
    const q = prodQuery.trim().toLowerCase();
    if (!q) return OS_PRODUCTS.slice(0, 6);
    return OS_PRODUCTS.filter(p =>
      (p.name || p.label || "").toLowerCase().includes(q)
    ).slice(0, 6);
  }, [prodQuery, OS_PRODUCTS]);

  const fmtPrice = (n) => typeof n === "number"
    ? n.toLocaleString("pt-BR", { style:"currency", currency:"BRL" })
    : (n || "R$ 0,00");
  const addItem = (p) => {
    setItems(prev => [...prev, {
      key: Date.now() + Math.random(),
      product: p.name || p.label || "—",
      qty: 1,
      unitPrice: fmtPrice(p.price),
      generatesOs: !(p.readyStock || false),
    }]);
    setProdQuery("");
  };
  const updateItem = (k, patch) => setItems(prev => prev.map(it => it.key === k ? { ...it, ...patch } : it));
  const removeItem = (k) => setItems(prev => prev.filter(it => it.key !== k));

  const subtotal = useMemoV(() => items.reduce((s, it) => {
    const p = parseFloat((it.unitPrice || "0").replace(/[^\d,]/g,"").replace(",","."));
    return s + (isNaN(p) ? 0 : p) * (parseInt(it.qty) || 0);
  }, 0), [items]);

  // S3: Pagamento
  const [payment, setPayment] = useStateV("pix");
  const [installments, setInstallments] = useStateV(1);
  const [discount, setDiscount] = useStateV(0);
  const total = Math.max(0, subtotal - discount);

  const fmt = (n) => n.toLocaleString("pt-BR", { style:"currency", currency:"BRL" });

  // Stepper config
  const steps = [
    { n:1, label:"Cliente",     ok: !!client },
    { n:2, label:"Itens",       ok: items.length > 0 },
    { n:3, label:"Pagamento",   ok: !!payment },
    { n:4, label:"Confirmar",   ok: false },
  ];
  const canNext = steps[step-1].ok;
  const generatesAnyOs = items.some(it => it.generatesOs);

  return (
    <div className="os-drawer-back" onClick={onClose}>
      <aside className="os-drawer wide vd-create" onClick={e => e.stopPropagation()}>
        <header className="os-drawer-head">
          <div className="os-drawer-head-l">
            <span className="os-drawer-id">Nova venda</span>
            <h2>Balcão · {new Date().toLocaleDateString("pt-BR")}</h2>
            <p>Atalho: <kbd>Esc</kbd> cancelar · <kbd>Enter</kbd> avançar</p>
          </div>
          <button className="icon-btn" onClick={onClose}><I.close size={14}/></button>
        </header>

        <nav className="vd-stepper">
          {steps.map((s, i) => (
            <button key={s.n}
                    className={"vd-step" + (step === s.n ? " active" : "") + (s.ok ? " done" : "")}
                    onClick={() => s.ok || step > s.n ? setStep(s.n) : null}>
              <span className="vd-step-num">{s.ok && step !== s.n ? "✓" : s.n}</span>
              <span>{s.label}</span>
              {i < steps.length - 1 && <span className="vd-step-sep">›</span>}
            </button>
          ))}
        </nav>

        <div className="os-drawer-body vd-create-body">
          {step === 1 && (
            <section className="vd-section">
              <h3>Cliente</h3>
              {!client ? (
                <>
                  <div className="vd-search-wrap">
                    <I.search size={12}/>
                    <input autoFocus type="text" placeholder="Nome, CNPJ ou telefone..."
                           value={clientQuery}
                           onChange={e => setClientQuery(e.target.value)}/>
                    <button className="vd-walkin"
                            onClick={() => setClient({ id:"walkin", name:"Consumidor Final", cnpj:"—", contact:"—", phone:"" })}>
                      Consumidor Final
                    </button>
                  </div>
                  <div className="vd-client-list">
                    {clientMatches.map(c => (
                      <button key={c.id} className="vd-client-card"
                              onClick={() => { setClient(c); setContact(c.contact || ""); setPhone(c.phone || ""); }}>
                        <div className="vd-client-card-name">{c.name}</div>
                        <div className="vd-client-card-meta">{c.cnpj || "—"} · {c.contact || "—"}</div>
                      </button>
                    ))}
                  </div>
                </>
              ) : (
                <div className="vd-client-selected">
                  <div>
                    <strong>{client.name}</strong>
                    <div className="vd-meta">{client.cnpj || "—"}</div>
                  </div>
                  <div className="vd-fields">
                    <label>Contato<input value={contact} onChange={e => setContact(e.target.value)}/></label>
                    <label>Telefone<input value={phone} onChange={e => setPhone(e.target.value)}/></label>
                  </div>
                  <button className="os-btn ghost" onClick={() => setClient(null)}>Trocar cliente</button>
                </div>
              )}
            </section>
          )}

          {step === 2 && (
            <section className="vd-section">
              <h3>Itens</h3>
              <div className="vd-search-wrap">
                <I.search size={12}/>
                <input autoFocus type="text" placeholder="Adicionar produto do catálogo..."
                       value={prodQuery} onChange={e => setProdQuery(e.target.value)}/>
              </div>
              {prodQuery && (
                <div className="vd-prod-suggest">
                  {prodMatches.map((p, i) => (
                    <button key={i} className="vd-prod-row" onClick={() => addItem(p)}>
                      <span>{p.name || p.label}</span>
                      <span className="vd-prod-price">{p.price || "—"}</span>
                    </button>
                  ))}
                  {prodMatches.length === 0 && <div className="vd-empty-mini">Nenhum produto</div>}
                </div>
              )}

              {items.length === 0 ? (
                <div className="vd-empty-state">Nenhum item adicionado. Use o campo acima para buscar do catálogo.</div>
              ) : (
                <table className="vd-items-table">
                  <thead>
                    <tr><th>Produto</th><th style={{width:80}}>Qtd</th><th style={{width:130}}>Unit.</th><th style={{width:130}}>Subtotal</th><th style={{width:100}}>Gera OS?</th><th style={{width:36}}></th></tr>
                  </thead>
                  <tbody>
                    {items.map(it => {
                      const p = parseFloat((it.unitPrice || "0").replace(/[^\d,]/g,"").replace(",","."));
                      const sub = (isNaN(p) ? 0 : p) * (parseInt(it.qty) || 0);
                      return (
                        <tr key={it.key}>
                          <td>{it.product}</td>
                          <td><input type="number" min="1" value={it.qty}
                                     onChange={e => updateItem(it.key, { qty: e.target.value })}/></td>
                          <td><input value={it.unitPrice}
                                     onChange={e => updateItem(it.key, { unitPrice: e.target.value })}/></td>
                          <td className="vd-strong">{fmt(sub)}</td>
                          <td>
                            <label className="vd-toggle">
                              <input type="checkbox" checked={it.generatesOs}
                                     onChange={e => updateItem(it.key, { generatesOs: e.target.checked })}/>
                              <span>{it.generatesOs ? "Sim" : "Pronta-entrega"}</span>
                            </label>
                          </td>
                          <td>
                            <button className="icon-btn" onClick={() => removeItem(it.key)}><I.close size={12}/></button>
                          </td>
                        </tr>
                      );
                    })}
                  </tbody>
                  <tfoot>
                    <tr><td colSpan={3} className="vd-foot-l">Subtotal</td><td colSpan={3} className="vd-foot-r">{fmt(subtotal)}</td></tr>
                  </tfoot>
                </table>
              )}
            </section>
          )}

          {step === 3 && (
            <section className="vd-section">
              <h3>Pagamento</h3>
              <div className="vd-pay-grid">
                {VENDAS_PAYMENTS.map(p => (
                  <button key={p.id}
                          className={"vd-pay-card" + (payment === p.id ? " active" : "")}
                          onClick={() => setPayment(p.id)}>
                    <span className="vd-pay-icon">{p.icon}</span>
                    <span className="vd-pay-label">{p.label}</span>
                    <span className="vd-pay-clear">{p.clearing}</span>
                  </button>
                ))}
              </div>

              {(payment === "cartao" || payment.startsWith("boleto")) && (
                <div className="vd-fields">
                  <label>Parcelas
                    <select value={installments} onChange={e => setInstallments(parseInt(e.target.value))}>
                      {[1,2,3,4,5,6,10,12].map(n => <option key={n} value={n}>{n}× de {fmt(total/n)}</option>)}
                    </select>
                  </label>
                  <label>Desconto
                    <input type="number" min="0" value={discount}
                           onChange={e => setDiscount(parseFloat(e.target.value) || 0)}/>
                  </label>
                </div>
              )}

              <dl className="vd-totals">
                <dt>Subtotal</dt><dd>{fmt(subtotal)}</dd>
                <dt>Desconto</dt><dd>-{fmt(discount)}</dd>
                <dt className="vd-total-row">Total</dt><dd className="vd-total-row">{fmt(total)}</dd>
              </dl>
            </section>
          )}

          {step === 4 && (
            <section className="vd-section vd-confirm">
              <h3>Confirmar venda</h3>
              <div className="vd-confirm-grid">
                <div className="vd-confirm-block">
                  <span className="vd-confirm-label">Cliente</span>
                  <strong>{client?.name || "—"}</strong>
                  <span className="vd-meta">{client?.cnpj || "—"}</span>
                </div>
                <div className="vd-confirm-block">
                  <span className="vd-confirm-label">Itens</span>
                  <strong>{items.length} {items.length === 1 ? "item" : "itens"}</strong>
                  <span className="vd-meta">{items.filter(it => it.generatesOs).length} gera{items.filter(it => it.generatesOs).length === 1 ? "" : "m"} OS</span>
                </div>
                <div className="vd-confirm-block">
                  <span className="vd-confirm-label">Pagamento</span>
                  <strong>{(VENDAS_PAYMENTS.find(p => p.id === payment) || {}).label}</strong>
                  <span className="vd-meta">{installments > 1 ? `${installments}× de ${fmt(total/installments)}` : "À vista"}</span>
                </div>
                <div className="vd-confirm-block vd-confirm-total">
                  <span className="vd-confirm-label">Total</span>
                  <strong className="vd-total-big">{fmt(total)}</strong>
                </div>
              </div>

              {generatesAnyOs && (
                <div className="vd-callout">
                  <strong>Esta venda gerará {items.filter(it => it.generatesOs).length} OS</strong> automaticamente após confirmação. As OS irão para a fila de produção da etapa <em>Pré-impressão</em>.
                </div>
              )}
              {!generatesAnyOs && items.length > 0 && (
                <div className="vd-callout vd-callout-ok">
                  <strong>Pronta-entrega.</strong> Nenhuma OS será gerada — entregue os itens ao cliente direto do estoque/balcão.
                </div>
              )}
            </section>
          )}
        </div>

        <footer className="os-drawer-actions vd-foot">
          <div className="vd-foot-summary">
            {items.length > 0 && (
              <>
                <span>{items.length} {items.length === 1 ? "item" : "itens"}</span>
                <span className="vd-foot-total">{fmt(total)}</span>
              </>
            )}
          </div>
          <div className="vd-foot-actions">
            {step > 1 && <button className="os-btn ghost" onClick={() => setStep(step-1)}>← Voltar</button>}
            {step < 4 && <button className="os-btn primary" disabled={!canNext} onClick={() => setStep(step+1)}>Avançar →</button>}
            {step === 4 && (
              <button className="os-btn primary" onClick={() => { alert("Venda registrada (mock)"); onClose(); }}>
                <I.check size={11}/>Confirmar venda
              </button>
            )}
          </div>
        </footer>
      </aside>
    </div>
  );
}

window.VendasListPage = VendasListPage;
