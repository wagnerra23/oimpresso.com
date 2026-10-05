// Pedidos, Produtos, Finanças screens + Detail screens

const { Ic } = window;
const { PEDIDOS, PRODUTOS, TRANSACOES } = window.MOCK;
const { ScreenHeader, DetailHeader, HeaderTopRight, OriginBadge, StageStatus } = window.Screens;
const BRL = window.BRL;
const BRLcompact = window.BRLcompact;

// ────────────────────────────────────────────────
// PEDIDOS
// ────────────────────────────────────────────────
function PedidosScreen({ nav, ctx }) {
  const [filter, setFilter] = React.useState("ativos");
  const [q, setQ] = React.useState("");

  const filtered = PEDIDOS.filter(p => {
    if (filter === "ativos" && p.etapaKey === "done") return false;
    if (filter === "concluidos" && p.etapaKey !== "done") return false;
    if (filter === "urgent" && !p.urgent) return false;
    if (q && !(p.id + " " + p.cliente + " " + p.produto).toLowerCase().includes(q.toLowerCase())) return false;
    return true;
  });

  return (
    <>
      <ScreenHeader
        nav={nav}
        title="Pedidos"
        eyebrow={PEDIDOS.length + " no total · " + PEDIDOS.filter(p => p.etapaKey !== "done").length + " ativos"}
        actions={<HeaderTopRight nav={nav} tenant={ctx.tenant} />}
        search={
          <div style={{ display: "flex", flexDirection: "column", gap: 10, marginTop: 8 }}>
            <div className="oi-search">
              <Ic.search size={16} />
              <input placeholder="OS, cliente, produto…" value={q} onChange={e => setQ(e.target.value)} />
              <Ic.scan size={18} />
            </div>
            <div className="oi-chips">
              {[
                { id: "ativos", label: "Ativos", c: PEDIDOS.filter(p => p.etapaKey !== "done").length },
                { id: "urgent", label: "Urgentes", c: PEDIDOS.filter(p => p.urgent).length },
                { id: "concluidos", label: "Concluídos", c: PEDIDOS.filter(p => p.etapaKey === "done").length },
                { id: "todos", label: "Todos", c: PEDIDOS.length },
              ].map(c => (
                <button key={c.id} className={"oi-chip" + (filter === c.id ? " on" : "")}
                        onClick={() => setFilter(c.id)}>
                  {c.label} <span className="c">{c.c}</span>
                </button>
              ))}
            </div>
          </div>
        }
      />
      <div className="oi-scroll">
        <div className="oi-list">
          {filtered.map(p => (
            <div key={p.id} className="oi-list-row"
                 style={p.urgent ? { borderLeft: "3px solid var(--danger)", paddingLeft: 13 } : {}}
                 onClick={() => nav.push("pedido", { id: p.id })}>
              <div style={{ flex: "1 1 auto", minWidth: 0, display: "flex", flexDirection: "column", gap: 4 }}>
                <div style={{ display: "flex", alignItems: "center", gap: 8 }}>
                  <span className="oi-mono" style={{ fontSize: 11.5, color: "var(--text-mute)", fontWeight: 600 }}>{p.id}</span>
                  <StageStatus etapaKey={p.etapaKey} label={p.etapa} />
                  <span className="oi-money" style={{ marginLeft: "auto", fontSize: 13.5, fontWeight: 600 }}>
                    {BRL(p.valor)}
                  </span>
                </div>
                <div style={{ fontWeight: 600, fontSize: 13.5, letterSpacing: "-0.005em" }}>{p.cliente}</div>
                <div style={{ fontSize: 11.5, color: "var(--text-dim)", whiteSpace: "nowrap", overflow: "hidden", textOverflow: "ellipsis" }}>
                  {p.produto}
                </div>
                <div style={{ display: "flex", alignItems: "center", gap: 10, marginTop: 2 }}>
                  <div className="oi-progress" style={{ flex: 1, height: 4 }}>
                    <i style={{ width: (p.progresso * 100) + "%" }} />
                  </div>
                  <span style={{ fontSize: 10.5, color: p.urgent ? "var(--danger)" : "var(--text-mute)",
                                fontFamily: "var(--font-mono)", fontWeight: p.urgent ? 600 : 400 }}>
                    {p.prazo}
                  </span>
                </div>
              </div>
            </div>
          ))}
        </div>
        <div style={{ height: 80 }} />
      </div>
      <div className="oi-fab" onClick={() => nav.push("novo-pedido")}>
        <Ic.plus size={26} />
      </div>
    </>
  );
}

function PedidoDetalheScreen({ nav, ctx, params }) {
  const p = PEDIDOS.find(x => x.id === params.id) || PEDIDOS[0];
  const steps = [
    { k: "orc",     label: "Orçamento",  cta: "Aprovar orçamento", icCta: "checkCircle" },
    { k: "aprov",   label: "Aprovação",  cta: "Aprovar arte",      icCta: "checkCircle" },
    { k: "prod",    label: "Produção",   cta: "Liberar produção",  icCta: "zap" },
    { k: "entrega", label: "Entrega",    cta: "Saiu para entrega", icCta: "truck" },
    { k: "done",    label: "Concluído",  cta: "Confirmar entrega", icCta: "check" },
  ];
  const stepIdx = Math.max(0, steps.findIndex(s => s.k === p.etapaKey));
  const isDone = p.etapaKey === "done";
  const nextStep = !isDone && stepIdx >= 0 ? steps[stepIdx] : null;
  const futureStep = !isDone ? steps[stepIdx + 1] : null;

  const [advanced, setAdvanced] = React.useState(false);

  return (
    <>
      <DetailHeader nav={nav} title={p.id} eyebrow={p.cliente}
                    actions={<button className="oi-iconbtn"><Ic.dotsV /></button>} />
      <div className="oi-scroll">
        {/* Hero card */}
        <div className="oi-section">
          <div className="oi-card pad">
            <div style={{ display: "flex", alignItems: "baseline", gap: 8 }}>
              <StageStatus etapaKey={p.etapaKey} label={p.etapa} />
              {p.urgent && <span className="oi-status danger"><Ic.flame size={12} color="currentColor" /> Urgente</span>}
              <span style={{ marginLeft: "auto", fontSize: 11.5, color: "var(--text-mute)" }} className="oi-mono">
                Prazo {p.prazo}
              </span>
            </div>
            <div style={{ marginTop: 8 }}>
              <div style={{ fontSize: 15, fontWeight: 600, lineHeight: 1.35 }}>{p.produto}</div>
              <div className="oi-money" style={{ fontSize: 28, fontWeight: 600, letterSpacing: "-0.02em", marginTop: 6 }}>
                {BRL(p.valor)}
              </div>
            </div>
          </div>
        </div>

        {/* Stepper interativo — tap em qualquer etapa avança até ela */}
        <div className="oi-section">
          <h3 className="oi-section-h">
            Andamento
            {nextStep && !isDone && (
              <span style={{ marginLeft: "auto", fontSize: 11, color: "var(--text-mute)", textTransform: "none", letterSpacing: 0, fontWeight: 500 }}>
                próxima: <b style={{ color: "var(--accent)", fontWeight: 600 }}>{nextStep.label}</b>
              </span>
            )}
          </h3>
          <div className="oi-card">
            <div style={{ display: "flex", justifyContent: "space-between", position: "relative" }}>
              <div style={{ position: "absolute", top: 11, left: 11, right: 11, height: 2, background: "var(--border)" }} />
              <div style={{ position: "absolute", top: 11, left: 11, height: 2, background: "var(--accent)",
                            width: "calc(" + (stepIdx / (steps.length - 1) * 100) + "% - 22px * " + (stepIdx / (steps.length - 1)) + ")" }} />
              {steps.map((s, i) => {
                const done = i < stepIdx;
                const cur = i === stepIdx;
                return (
                  <div key={s.k} style={{ display: "flex", flexDirection: "column", alignItems: "center", gap: 6, position: "relative", zIndex: 1, flex: "0 0 auto", width: 60 }}>
                    <div style={{
                      width: 22, height: 22, borderRadius: 999,
                      background: done || cur ? "var(--accent)" : "var(--surface)",
                      border: "2px solid " + (done || cur ? "var(--accent)" : "var(--border)"),
                      color: "#fff",
                      display: "grid", placeItems: "center", fontSize: 10, fontWeight: 700,
                    }}>
                      {done ? <Ic.check size={11} color="#fff" strokeWidth={3} /> : (i + 1)}
                    </div>
                    <small style={{ fontSize: 10.5, color: cur ? "var(--text)" : "var(--text-mute)",
                                  fontWeight: cur ? 600 : 500, textAlign: "center", lineHeight: 1.2 }}>{s.label}</small>
                  </div>
                );
              })}
            </div>
            {/* Quick-advance buttons (nas situações onde faz sentido pular ou voltar) */}
            {!isDone && (
              <div style={{ display: "flex", gap: 6, marginTop: 14, paddingTop: 12, borderTop: "1px solid var(--border-2)" }}>
                <button className="oi-btn sm" style={{ flex: 1 }} disabled={stepIdx === 0}
                        onClick={() => alert("Voltar etapa")}>
                  <Ic.chevL size={14} /> Voltar
                </button>
                {futureStep && (
                  <button className="oi-btn sm" style={{ flex: 1 }}
                          onClick={() => alert("Pular para " + futureStep.label)}>
                    Pular <Ic.chevR size={14} />
                  </button>
                )}
                <button className="oi-btn sm danger" style={{ flex: 1 }}>
                  <Ic.x size={14} /> Cancelar OS
                </button>
              </div>
            )}
          </div>
        </div>

        {/* Detalhes */}
        <div className="oi-section">
          <h3 className="oi-section-h">Detalhes</h3>
          <div className="oi-card pad">
            <dl className="oi-dl">
              <dt>Nº OS</dt>      <dd className="oi-mono">{p.id}</dd>
              <dt>Cliente</dt>    <dd>{p.cliente}</dd>
              <dt>Produto</dt>    <dd>{p.produto}</dd>
              <dt>Valor</dt>      <dd className="oi-money" style={{ fontWeight: 600 }}>{BRL(p.valor)}</dd>
              <dt>Pagamento</dt>  <dd>Boleto · 7 dias</dd>
              <dt>Criado</dt>     <dd>{p.criado}</dd>
              <dt>Atualizado</dt> <dd>há {p.atualizado}</dd>
            </dl>
          </div>
        </div>

        {/* Arte */}
        {(p.etapaKey === "aprov" || p.etapaKey === "prod") && (
          <div className="oi-section">
            <h3 className="oi-section-h">Arte</h3>
            <div className="oi-card pad" style={{ flexDirection: "row", alignItems: "center", gap: 14 }}>
              <div style={{ width: 64, height: 64, borderRadius: 8, background: "var(--bg-2)",
                            border: "1px solid var(--border)", display: "grid", placeItems: "center", color: "var(--text-mute)" }}>
                <Ic.file size={26} />
              </div>
              <div style={{ flex: "1 1 auto", minWidth: 0 }}>
                <div style={{ fontWeight: 600, fontSize: 13.5 }}>{p.id.toLowerCase().replace("os-", "arte_")}_v3.pdf</div>
                <div className="oi-mono" style={{ fontSize: 11, color: "var(--text-mute)" }}>2.4 MB · 300dpi · CMYK</div>
              </div>
              <button className="oi-btn sm"><Ic.eye size={14} /></button>
            </div>
          </div>
        )}

        {/* Contato */}
        <div className="oi-section">
          <h3 className="oi-section-h">Contato</h3>
          <div className="oi-card pad" style={{ gap: 0 }}>
            <ContactRow ic="phone" label="(11) 95412-8821" />
            <ContactRow ic="whatsapp" label="WhatsApp" sub="visto há 3min" />
            <ContactRow ic="mail" label="marilia@exemplo.com" last />
          </div>
        </div>

        <div style={{ height: 100 }} />
      </div>

      {/* Action bar — explícito sobre qual etapa avançar */}
      <div style={{ padding: 12, background: "var(--surface)", borderTop: "1px solid var(--border)",
                    display: "flex", gap: 8 }}>
        {isDone ? (
          <>
            <button className="oi-btn" style={{ flex: "0 0 auto", padding: "0 14px" }}>
              <Ic.printer size={18} />
            </button>
            <button className="oi-btn primary" style={{ flex: 1 }}>
              <Ic.refresh size={16} /> Reabrir pedido
            </button>
          </>
        ) : (
          <>
            <button className="oi-btn" style={{ flex: "0 0 auto", padding: "0 12px" }} title="WhatsApp">
              <Ic.whatsapp size={18} />
            </button>
            <button className="oi-btn" style={{ flex: "0 0 auto", padding: "0 12px" }} title="Imprimir OS">
              <Ic.printer size={18} />
            </button>
            <button className="oi-btn primary" style={{ flex: 1 }} onClick={() => nav.pop()}>
              {nextStep && React.createElement(Ic[nextStep.icCta] || Ic.checkCircle, { size: 18 })}
              <span>{nextStep ? nextStep.cta : "Atualizar"}</span>
            </button>
          </>
        )}
      </div>
    </>
  );
}

function ContactRow({ ic, label, sub, last }) {
  return (
    <div style={{ display: "flex", alignItems: "center", gap: 12, padding: "10px 0",
                  borderBottom: last ? 0 : "1px solid var(--border-2)" }}>
      <div style={{ color: "var(--text-mute)" }}>{React.createElement(Ic[ic], { size: 18 })}</div>
      <div style={{ flex: "1 1 auto", minWidth: 0 }}>
        <div style={{ fontSize: 13.5, color: "var(--text)" }}>{label}</div>
        {sub && <div style={{ fontSize: 11, color: "var(--text-mute)" }}>{sub}</div>}
      </div>
      <Ic.chevR color="var(--text-mute)" size={16} />
    </div>
  );
}

// ────────────────────────────────────────────────
// PRODUTOS
// ────────────────────────────────────────────────
function ProdutosScreen({ nav, ctx }) {
  const [cat, setCat] = React.useState("todos");
  const [q, setQ] = React.useState("");
  const [view, setView] = React.useState("list"); // list | grid
  const cats = ["todos", ...new Set(PRODUTOS.map(p => p.cat))];
  const filtered = PRODUTOS.filter(p => {
    if (cat !== "todos" && p.cat !== cat) return false;
    if (q && !(p.nome + " " + p.sku).toLowerCase().includes(q.toLowerCase())) return false;
    return true;
  });
  const baixo = PRODUTOS.filter(p => p.estoque < p.min).length;

  return (
    <>
      <ScreenHeader
        nav={nav}
        title="Produtos"
        eyebrow={PRODUTOS.length + " itens · " + baixo + " com estoque baixo"}
        actions={<>
          <button className="oi-iconbtn" onClick={() => setView(v => v === "list" ? "grid" : "list")}>
            {view === "list" ? <Ic.layers /> : <Ic.box />}
          </button>
          <HeaderTopRight nav={nav} tenant={ctx.tenant} />
        </>}
        search={
          <div style={{ display: "flex", flexDirection: "column", gap: 10, marginTop: 8 }}>
            <div className="oi-search">
              <Ic.search size={16} />
              <input placeholder="Nome ou SKU…" value={q} onChange={e => setQ(e.target.value)} />
              <Ic.scan size={18} />
            </div>
            <div className="oi-chips">
              {cats.map(c => (
                <button key={c} className={"oi-chip" + (cat === c ? " on" : "")}
                        onClick={() => setCat(c)}>
                  {c === "todos" ? "Todos" : c}
                </button>
              ))}
            </div>
          </div>
        }
      />
      <div className="oi-scroll">
        {view === "list" ? (
          <div className="oi-list">
            {filtered.map(p => (
              <div key={p.id} className="oi-list-row" onClick={() => nav.push("produto", { id: p.id })}>
                <div style={{ width: 44, height: 44, borderRadius: 8, background: "var(--bg-2)",
                              border: "1px solid var(--border)", display: "grid", placeItems: "center",
                              color: "var(--text-mute)", flex: "0 0 auto" }}>
                  {React.createElement(Ic[p.img] || Ic.box, { size: 22 })}
                </div>
                <div style={{ flex: "1 1 auto", minWidth: 0 }}>
                  <div style={{ fontWeight: 600, fontSize: 13.5, lineHeight: 1.3,
                                whiteSpace: "nowrap", overflow: "hidden", textOverflow: "ellipsis" }}>
                    {p.nome}
                  </div>
                  <div style={{ display: "flex", gap: 8, fontSize: 11.5, color: "var(--text-mute)", marginTop: 2 }}>
                    <span className="oi-mono">{p.sku}</span>
                    <span>·</span>
                    <span style={{ color: p.estoque < p.min ? "var(--danger)" : "var(--text-dim)", fontWeight: p.estoque < p.min ? 600 : 400 }}>
                      {p.estoque} {p.un}
                    </span>
                  </div>
                </div>
                <div style={{ textAlign: "right", flex: "0 0 auto" }}>
                  <div className="oi-money" style={{ fontSize: 13.5, fontWeight: 600 }}>{BRL(p.preco)}</div>
                  <div style={{ fontSize: 10.5, color: "var(--text-mute)" }}>/{p.un}</div>
                </div>
              </div>
            ))}
          </div>
        ) : (
          <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr", gap: 10, padding: 16 }}>
            {filtered.map(p => (
              <div key={p.id} className="oi-card tight" onClick={() => nav.push("produto", { id: p.id })}
                   style={{ cursor: "pointer", padding: 10, gap: 6 }}>
                <div style={{ aspectRatio: "1.4 / 1", borderRadius: 8, background: "var(--bg-2)",
                              border: "1px solid var(--border)", display: "grid", placeItems: "center",
                              color: "var(--text-mute)" }}>
                  {React.createElement(Ic[p.img] || Ic.box, { size: 32 })}
                </div>
                <div style={{ fontWeight: 600, fontSize: 12.5, lineHeight: 1.25, minHeight: 32 }}>{p.nome}</div>
                <div style={{ display: "flex", alignItems: "baseline", gap: 6 }}>
                  <span className="oi-money" style={{ fontWeight: 600, fontSize: 13 }}>{BRL(p.preco)}</span>
                  <span style={{ marginLeft: "auto", fontSize: 10.5,
                                color: p.estoque < p.min ? "var(--danger)" : "var(--text-mute)", fontWeight: p.estoque < p.min ? 600 : 500 }}>
                    {p.estoque}{p.estoque < p.min ? " ↓" : ""}
                  </span>
                </div>
              </div>
            ))}
          </div>
        )}
        <div style={{ height: 80 }} />
      </div>
      <div className="oi-fab" onClick={() => nav.push("novo-produto")}>
        <Ic.plus size={26} />
      </div>
    </>
  );
}

function ProdutoDetalheScreen({ nav, ctx, params }) {
  const p = PRODUTOS.find(x => x.id === params.id) || PRODUTOS[0];
  const baixo = p.estoque < p.min;
  return (
    <>
      <DetailHeader nav={nav} title={p.nome} eyebrow={p.cat}
                    actions={<button className="oi-iconbtn" onClick={() => nav.push("editar-produto", { id: p.id })}><Ic.edit size={20} /></button>} />
      <div className="oi-scroll">
        <div className="oi-section">
          <div style={{ aspectRatio: "16/9", borderRadius: 12, background: "var(--bg-2)",
                        border: "1px solid var(--border)", display: "grid", placeItems: "center",
                        color: "var(--text-mute)" }}>
            {React.createElement(Ic[p.img] || Ic.box, { size: 72 })}
          </div>
        </div>

        <div className="oi-section">
          <div style={{ display: "flex", alignItems: "baseline", gap: 10 }}>
            <div className="oi-money" style={{ fontSize: 30, fontWeight: 600, letterSpacing: "-0.02em" }}>
              {BRL(p.preco)}
            </div>
            <span style={{ fontSize: 13, color: "var(--text-mute)" }}>/{p.un}</span>
          </div>
          <div style={{ display: "flex", gap: 6, marginTop: 8 }}>
            <span className="oi-status accent"><span className="dot" />Disponível para venda</span>
            {baixo && <span className="oi-status danger"><Ic.arrowDown size={12} /> Estoque baixo</span>}
          </div>
        </div>

        {/* Estoque */}
        <div className="oi-section">
          <h3 className="oi-section-h">Estoque</h3>
          <div className="oi-card pad">
            <div style={{ display: "flex", alignItems: "baseline" }}>
              <div>
                <div style={{ fontSize: 11, color: "var(--text-mute)", fontWeight: 600, letterSpacing: ".06em", textTransform: "uppercase" }}>Em estoque</div>
                <div className="oi-money" style={{ fontSize: 26, fontWeight: 600 }}>{p.estoque} <small style={{ fontSize: 13, color: "var(--text-mute)", fontWeight: 500 }}>{p.un}</small></div>
              </div>
              <div style={{ marginLeft: "auto", textAlign: "right" }}>
                <div style={{ fontSize: 11, color: "var(--text-mute)" }}>Mínimo</div>
                <div className="oi-mono" style={{ fontSize: 15, fontWeight: 600 }}>{p.min}</div>
              </div>
            </div>
            <div className={"oi-progress " + (baixo ? "danger" : p.estoque < p.min * 1.5 ? "warn" : "ok")}>
              <i style={{ width: Math.min(100, (p.estoque / (p.min * 2)) * 100) + "%" }} />
            </div>
            <div style={{ display: "flex", gap: 8, marginTop: 4 }}>
              <button className="oi-btn sm" style={{ flex: 1 }}><Ic.plus size={14} /> Entrada</button>
              <button className="oi-btn sm" style={{ flex: 1 }}><Ic.printer size={14} /> Etiqueta</button>
            </div>
          </div>
        </div>

        {/* Detalhes */}
        <div className="oi-section">
          <h3 className="oi-section-h">Especificações</h3>
          <div className="oi-card pad">
            <dl className="oi-dl">
              <dt>SKU</dt>       <dd className="oi-mono">{p.sku}</dd>
              <dt>Categoria</dt> <dd>{p.cat}</dd>
              <dt>Unidade</dt>   <dd>{p.un}</dd>
              <dt>Custo</dt>     <dd className="oi-money">{BRL(p.preco * 0.62)}</dd>
              <dt>Margem</dt>    <dd>38%</dd>
              <dt>Fornecedor</dt><dd>Suzano Papéis</dd>
            </dl>
          </div>
        </div>

        {/* Movimentações */}
        <div className="oi-section">
          <h3 className="oi-section-h">Últimas movimentações</h3>
          <div className="oi-card" style={{ padding: 0 }}>
            {[
              { ic: "arrow-down", color: "var(--danger)", t: "Hoje 09:14", l: "Saída · OS-3041", v: -10 },
              { ic: "arrow-up",   color: "var(--ok)",     t: "Ontem 14:02", l: "Entrada · NF 8821",  v: 50 },
              { ic: "arrow-down", color: "var(--danger)", t: "Sex 11:30",   l: "Saída · OS-3035", v: -8 },
            ].map((m, i, arr) => (
              <div key={i} style={{ display: "flex", alignItems: "center", gap: 12, padding: "12px 14px",
                                    borderBottom: i < arr.length - 1 ? "1px solid var(--border-2)" : 0 }}>
                <div style={{ color: m.color }}>{React.createElement(Ic[m.ic], { size: 16 })}</div>
                <div style={{ flex: "1 1 auto" }}>
                  <div style={{ fontSize: 13, fontWeight: 500 }}>{m.l}</div>
                  <div style={{ fontSize: 11, color: "var(--text-mute)" }}>{m.t}</div>
                </div>
                <div className="oi-mono" style={{ fontSize: 13, fontWeight: 600, color: m.color }}>
                  {m.v > 0 ? "+" : ""}{m.v}
                </div>
              </div>
            ))}
          </div>
        </div>

        <div style={{ height: 100 }} />
      </div>
    </>
  );
}

// ────────────────────────────────────────────────
// FINANÇAS / TRANSAÇÕES
// ────────────────────────────────────────────────
function FinancasScreen({ nav, ctx }) {
  const [tipo, setTipo] = React.useState("todos");
  const filtered = TRANSACOES.filter(t => tipo === "todos" || t.tipo === tipo);
  const entradas = TRANSACOES.filter(t => t.tipo === "in").reduce((s, t) => s + t.valor, 0);
  const saidas = TRANSACOES.filter(t => t.tipo === "out").reduce((s, t) => s + t.valor, 0);
  const saldo = entradas - saidas;

  // Group by date
  const groups = {};
  filtered.forEach(t => {
    const k = t.data.startsWith("Vence") ? "Hoje" : t.data.split(" ")[0];
    (groups[k] = groups[k] || []).push(t);
  });

  return (
    <>
      <ScreenHeader
        nav={nav}
        title="Finanças"
        eyebrow="Maio · 16 dias"
        actions={<HeaderTopRight nav={nav} tenant={ctx.tenant} />}
      />
      <div className="oi-scroll">
        {/* Saldo hero */}
        <div className="oi-section">
          <div className="oi-card hi" style={{ padding: 16 }}>
            <small style={{ fontSize: 10.5, fontWeight: 700, letterSpacing: ".08em", textTransform: "uppercase", color: "var(--text-mute)" }}>
              Saldo do mês
            </small>
            <div className="oi-money" style={{ fontSize: 30, fontWeight: 600, letterSpacing: "-0.02em", color: saldo >= 0 ? "var(--ok)" : "var(--danger)" }}>
              {saldo >= 0 ? "+" : ""}{BRL(saldo)}
            </div>
            <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr", gap: 12 }}>
              <div>
                <small style={{ fontSize: 10.5, color: "var(--text-mute)", fontWeight: 600, letterSpacing: ".06em", textTransform: "uppercase" }}>
                  <Ic.arrowDown size={11} style={{ verticalAlign: "middle", color: "var(--ok)" }} /> Entradas
                </small>
                <div className="oi-money" style={{ fontSize: 16, fontWeight: 600, color: "var(--ok)" }}>{BRL(entradas)}</div>
              </div>
              <div>
                <small style={{ fontSize: 10.5, color: "var(--text-mute)", fontWeight: 600, letterSpacing: ".06em", textTransform: "uppercase" }}>
                  <Ic.arrowUp size={11} style={{ verticalAlign: "middle", color: "var(--danger)" }} /> Saídas
                </small>
                <div className="oi-money" style={{ fontSize: 16, fontWeight: 600, color: "var(--danger)" }}>{BRL(saidas)}</div>
              </div>
            </div>
          </div>
        </div>

        {/* Contas */}
        <div className="oi-section">
          <h3 className="oi-section-h">Contas</h3>
          <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr", gap: 8 }}>
            <div className="oi-card tight" style={{ padding: 12 }}>
              <div style={{ display: "flex", alignItems: "center", gap: 6 }}>
                <Ic.qr size={14} color="var(--accent)" />
                <small style={{ fontSize: 11, color: "var(--text-dim)", fontWeight: 500 }}>PIX</small>
              </div>
              <div className="oi-money" style={{ fontSize: 17, fontWeight: 600 }}>R$ 2.140,00</div>
            </div>
            <div className="oi-card tight" style={{ padding: 12 }}>
              <div style={{ display: "flex", alignItems: "center", gap: 6 }}>
                <Ic.shield size={14} color="var(--text-dim)" />
                <small style={{ fontSize: 11, color: "var(--text-dim)", fontWeight: 500 }}>C/C Itaú</small>
              </div>
              <div className="oi-money" style={{ fontSize: 17, fontWeight: 600 }}>R$ 8.412,00</div>
            </div>
          </div>
        </div>

        {/* Tabs */}
        <div className="oi-section">
          <div className="oi-chips">
            {[
              { id: "todos", label: "Todas", c: TRANSACOES.length },
              { id: "in", label: "Entradas", c: TRANSACOES.filter(t => t.tipo === "in").length },
              { id: "out", label: "Saídas", c: TRANSACOES.filter(t => t.tipo === "out").length },
            ].map(c => (
              <button key={c.id} className={"oi-chip" + (tipo === c.id ? " on" : "")}
                      onClick={() => setTipo(c.id)}>
                {c.label} <span className="c">{c.c}</span>
              </button>
            ))}
          </div>
        </div>

        {/* Lista por dia */}
        {Object.entries(groups).map(([day, items]) => (
          <div key={day} className="oi-section" style={{ paddingTop: 4 }}>
            <h3 className="oi-section-h">{day}</h3>
            <div className="oi-list card">
              {items.map(t => (
                <div key={t.id} className="oi-list-row" onClick={() => nav.push("transacao", { id: t.id })}>
                  <div style={{ width: 38, height: 38, borderRadius: 10,
                                background: t.tipo === "in" ? "color-mix(in oklch, var(--ok) 18%, transparent)" : "color-mix(in oklch, var(--danger) 16%, transparent)",
                                color: t.tipo === "in" ? "var(--ok)" : "var(--danger)",
                                display: "grid", placeItems: "center", flex: "0 0 auto" }}>
                    {React.createElement(Ic[t.tipo === "in" ? "arrow-down" : "arrow-up"], { size: 18 })}
                  </div>
                  <div style={{ flex: "1 1 auto", minWidth: 0 }}>
                    <div style={{ fontSize: 13.5, fontWeight: 500, whiteSpace: "nowrap", overflow: "hidden", textOverflow: "ellipsis" }}>
                      {t.desc}
                    </div>
                    <div style={{ fontSize: 11.5, color: "var(--text-mute)", display: "flex", gap: 8 }}>
                      <span>{t.categoria}</span>
                      <span>·</span>
                      <span>{t.meio}</span>
                    </div>
                  </div>
                  <div style={{ textAlign: "right", flex: "0 0 auto" }}>
                    <div className="oi-money" style={{ fontSize: 13.5, fontWeight: 600,
                                                       color: t.tipo === "in" ? "var(--ok)" : "var(--text)" }}>
                      {t.tipo === "in" ? "+" : "−"}{BRL(t.valor)}
                    </div>
                    {t.status === "warn" && <div style={{ fontSize: 10.5, color: "var(--warn)", fontWeight: 600 }}>vence hoje</div>}
                  </div>
                </div>
              ))}
            </div>
          </div>
        ))}

        <div style={{ height: 80 }} />
      </div>
      <div className="oi-fab" onClick={() => alert("Nova transação")}>
        <Ic.plus size={26} />
      </div>
    </>
  );
}

function TransacaoDetalheScreen({ nav, ctx, params }) {
  const t = TRANSACOES.find(x => x.id === params.id) || TRANSACOES[0];
  const inn = t.tipo === "in";
  return (
    <>
      <DetailHeader nav={nav} title={t.id} eyebrow={t.meio} />
      <div className="oi-scroll">
        <div className="oi-section">
          <div className="oi-card pad" style={{ alignItems: "center", textAlign: "center", padding: "24px 16px" }}>
            <div style={{ width: 56, height: 56, borderRadius: 16,
                          background: inn ? "color-mix(in oklch, var(--ok) 18%, transparent)" : "color-mix(in oklch, var(--danger) 16%, transparent)",
                          color: inn ? "var(--ok)" : "var(--danger)",
                          display: "grid", placeItems: "center" }}>
              {React.createElement(Ic[inn ? "arrow-down" : "arrow-up"], { size: 26 })}
            </div>
            <div style={{ fontSize: 11, fontWeight: 700, letterSpacing: ".08em", textTransform: "uppercase",
                          color: "var(--text-mute)" }}>
              {inn ? "Entrada" : "Saída"}
            </div>
            <div className="oi-money" style={{ fontSize: 34, fontWeight: 600, letterSpacing: "-0.02em",
                                             color: inn ? "var(--ok)" : "var(--text)" }}>
              {inn ? "+" : "−"}{BRL(t.valor)}
            </div>
            <div style={{ fontSize: 13.5, color: "var(--text-dim)" }}>{t.desc}</div>
            <span className={"oi-status " + (t.status === "warn" ? "warn" : "ok")}>
              <span className="dot" />{t.status === "warn" ? "Pendente · vence hoje" : "Confirmado"}
            </span>
          </div>
        </div>

        <div className="oi-section">
          <h3 className="oi-section-h">Detalhes</h3>
          <div className="oi-card pad">
            <dl className="oi-dl">
              <dt>Categoria</dt> <dd>{t.categoria}</dd>
              <dt>Meio</dt>      <dd>{t.meio}</dd>
              <dt>Conta</dt>     <dd>{t.conta}</dd>
              <dt>Data</dt>      <dd>{t.data}</dd>
              <dt>Vinculado</dt> <dd className="oi-mono">{t.categoria.includes("OS") ? t.categoria : "—"}</dd>
            </dl>
          </div>
        </div>

        <div className="oi-section">
          <button className="oi-btn block primary">
            <Ic.eye size={16} /> Ver comprovante
          </button>
        </div>

        <div style={{ height: 100 }} />
      </div>
    </>
  );
}

// ────────────────────────────────────────────────
// NOTIFICAÇÕES + PERFIL + EMPRESA
// ────────────────────────────────────────────────
function NotifsScreen({ nav, ctx }) {
  return (
    <>
      <DetailHeader nav={nav} title="Notificações"
                    actions={<button className="oi-iconbtn"><Ic.check size={22} /></button>} />
      <div className="oi-scroll">
        <div className="oi-list">
          {window.MOCK.NOTIFS.map(n => (
            <div key={n.id} className="oi-list-row" style={{ background: n.unread ? "var(--accent-soft)" : undefined }}>
              <div style={{ flex: "1 1 auto", minWidth: 0, display: "flex", flexDirection: "column", gap: 4 }}>
                <div style={{ display: "flex", alignItems: "center", gap: 8 }}>
                  <OriginBadge origin={n.origin} />
                  <span style={{ marginLeft: "auto", fontSize: 11, color: "var(--text-mute)" }} className="oi-mono">
                    {n.when}
                  </span>
                </div>
                <div style={{ fontWeight: 600, fontSize: 13.5 }}>{n.title}</div>
                <div style={{ fontSize: 11.5, color: "var(--text-dim)" }}>{n.sub}</div>
              </div>
              {n.unread && <div style={{ width: 8, height: 8, borderRadius: 99, background: "var(--accent)", flex: "0 0 auto" }} />}
            </div>
          ))}
        </div>
      </div>
    </>
  );
}

function PerfilScreen({ nav, ctx, setTweak }) {
  const t = window.MOCK.TENANTS.find(x => x.id === ctx.tenant) || window.MOCK.TENANTS[0];
  return (
    <>
      <DetailHeader nav={nav} title="Perfil" />
      <div className="oi-scroll">
        <div className="oi-section" style={{ paddingTop: 18, textAlign: "center" }}>
          <div className={"oi-av oi-av-2"}
               style={{ width: 76, height: 76, borderRadius: "50%", fontSize: 26, fontWeight: 700,
                        margin: "0 auto 10px" }}>
            {window.MOCK.USER.initials}
          </div>
          <div style={{ fontSize: 18, fontWeight: 600, letterSpacing: "-0.01em" }}>{window.MOCK.USER.nome}</div>
          <div style={{ fontSize: 13, color: "var(--text-dim)" }}>{window.MOCK.USER.email}</div>
          <div style={{ marginTop: 6 }}>
            <span className="oi-status accent"><span className="dot" />{window.MOCK.USER.role}</span>
          </div>
        </div>

        <div className="oi-section">
          <h3 className="oi-section-h">Empresa ativa</h3>
          <div className="oi-list card">
            <div className="oi-list-row" onClick={() => nav.push("empresa")}>
              <div className={"oi-av " + t.color} style={{ width: 36, height: 36, borderRadius: 8, fontSize: 13 }}>
                {t.short}
              </div>
              <div style={{ flex: "1 1 auto", minWidth: 0 }}>
                <div style={{ fontWeight: 600, fontSize: 13.5 }}>{t.name}</div>
                <div className="oi-mono" style={{ fontSize: 11, color: "var(--text-mute)" }}>{t.cnpj}</div>
              </div>
              <Ic.chevR color="var(--text-mute)" />
            </div>
          </div>
        </div>

        <div className="oi-section">
          <h3 className="oi-section-h">Preferências</h3>
          <div className="oi-list card">
            <SettingsRow ic="moon" label="Tema escuro"
                        right={<Toggle on={ctx.theme === "dark"} onChange={v => setTweak("theme", v ? "dark" : "light")} />} />
            <SettingsRow ic="layers" label="Densidade"
                        right={<span style={{ fontSize: 12, color: "var(--text-mute)", textTransform: "capitalize" }}>{ctx.density}</span>}
                        onClick={() => setTweak("density", ctx.density === "compact" ? "normal" : ctx.density === "normal" ? "comfy" : "compact")} />
            <SettingsRow ic="bell" label="Notificações push" right={<Toggle on={true} />} />
            <SettingsRow ic="shield" label="Privacidade" right={<Ic.chevR color="var(--text-mute)" />} last />
          </div>
        </div>

        <div className="oi-section">
          <h3 className="oi-section-h">Conta</h3>
          <div className="oi-list card">
            <SettingsRow ic="user" label="Dados pessoais" right={<Ic.chevR color="var(--text-mute)" />} />
            <SettingsRow ic="settings" label="Configurações" right={<Ic.chevR color="var(--text-mute)" />} />
            <SettingsRow ic="logout" label="Sair" danger last />
          </div>
        </div>

        <div style={{ height: 24, fontSize: 11, color: "var(--text-mute)", textAlign: "center", padding: "8px 16px 24px" }}>
          Oimpresso ERP Mobile · v0.4.1 (build 2026.05)
        </div>
      </div>
    </>
  );
}

function SettingsRow({ ic, label, right, danger, last, onClick }) {
  return (
    <div className="oi-list-row" onClick={onClick}
         style={{ minHeight: 52, ...(last ? { borderBottom: 0 } : {}) }}>
      <div style={{ color: danger ? "var(--danger)" : "var(--text-mute)" }}>
        {React.createElement(Ic[ic], { size: 18 })}
      </div>
      <div style={{ flex: "1 1 auto", fontSize: 13.5, fontWeight: 500, color: danger ? "var(--danger)" : "var(--text)" }}>
        {label}
      </div>
      {right}
    </div>
  );
}

function Toggle({ on, onChange }) {
  return (
    <button onClick={(e) => { e.stopPropagation(); onChange && onChange(!on); }}
            style={{
              appearance: "none", border: 0, padding: 0,
              width: 42, height: 24, borderRadius: 99,
              background: on ? "var(--accent)" : "var(--border)",
              position: "relative", transition: "background .15s", cursor: "pointer",
              flex: "0 0 auto",
            }}>
      <div style={{
        position: "absolute", top: 2, left: on ? 20 : 2,
        width: 20, height: 20, borderRadius: 99,
        background: "#fff", transition: "left .15s",
        boxShadow: "0 1px 3px rgba(0,0,0,.2)",
      }} />
    </button>
  );
}

function EmpresaScreen({ nav, ctx, setTweak }) {
  return (
    <>
      <DetailHeader nav={nav} title="Empresa ativa" />
      <div className="oi-scroll">
        <div className="oi-section" style={{ paddingTop: 6 }}>
          <p style={{ fontSize: 12.5, color: "var(--text-dim)", margin: "0 0 12px" }}>
            Você tem acesso a {window.MOCK.TENANTS.length} empresas. Toque para alternar — todas as telas se atualizam.
          </p>
          <div className="oi-list card">
            {window.MOCK.TENANTS.map(t => (
              <div key={t.id} className="oi-list-row"
                   onClick={() => { setTweak("tenant", t.id); nav.pop(); }}>
                <div className={"oi-av " + t.color} style={{ width: 40, height: 40, borderRadius: 8, fontSize: 13 }}>
                  {t.short}
                </div>
                <div style={{ flex: "1 1 auto", minWidth: 0 }}>
                  <div style={{ fontWeight: 600, fontSize: 14 }}>{t.name}</div>
                  <div className="oi-mono" style={{ fontSize: 11, color: "var(--text-mute)" }}>{t.cnpj}</div>
                </div>
                {ctx.tenant === t.id ? <Ic.checkCircle color="var(--accent)" /> : <Ic.chevR color="var(--text-mute)" />}
              </div>
            ))}
          </div>
        </div>
        <div className="oi-section">
          <button className="oi-btn block"><Ic.plus size={16} /> Adicionar empresa</button>
        </div>
      </div>
    </>
  );
}

// ────────────────────────────────────────────────
// LOGIN (only used as standalone artboard variant)
// ────────────────────────────────────────────────
function LoginScreen() {
  return (
    <>
      <div style={{ position: "relative", flex: 1, display: "flex", flexDirection: "column",
                    overflow: "hidden" }}>
        {/* Topo institucional — gradiente da marca */}
        <div style={{
          padding: "56px 24px 40px",
          background: "linear-gradient(150deg, var(--brand-deep), var(--brand-purple) 55%, var(--brand-magenta))",
          display: "flex", flexDirection: "column", alignItems: "center", gap: 14,
          borderBottomLeftRadius: 28, borderBottomRightRadius: 28,
        }}>
          <div style={{ width: 88, height: 88, borderRadius: 22, background: "rgba(255,255,255,.12)",
                        backdropFilter: "blur(4px)", display: "grid", placeItems: "center",
                        border: "1px solid rgba(255,255,255,.18)" }}>
            <img src="assets/oimpresso-logo.png" alt="Oimpresso" style={{ width: 52, height: "auto" }} />
          </div>
          <div style={{ textAlign: "center" }}>
            <div style={{ fontSize: 27, fontWeight: 700, letterSpacing: "-0.02em", color: "#fff" }}>Oimpresso</div>
            <div style={{ fontSize: 12, color: "rgba(255,255,255,.8)", marginTop: 2, letterSpacing: ".02em" }}>
              Gestão para comunicação visual
            </div>
          </div>
        </div>

        <div className="oi-scroll" style={{ padding: "32px 24px 24px" }}>
          <div style={{ fontSize: 16, fontWeight: 600, marginBottom: 4 }}>Bem-vindo de volta</div>
          <div style={{ fontSize: 13, color: "var(--text-dim)", marginBottom: 20 }}>
            Entre para acessar suas empresas
          </div>
          <div style={{ display: "flex", flexDirection: "column", gap: 10 }}>
            <button className="oi-btn block primary" style={{ height: 50 }}>
              <Ic.mail size={18} /> Continuar com Manus
            </button>
            <button className="oi-btn block" style={{ height: 50 }}>
              <Ic.shield size={18} /> Login OAuth corporativo
            </button>
          </div>
          <div style={{ textAlign: "center", marginTop: 24, fontSize: 11.5, color: "var(--text-mute)" }}>
            Ao continuar você aceita os <a style={{ color: "var(--accent)" }}>termos</a> e a <a style={{ color: "var(--accent)" }}>política</a>.
          </div>
        </div>
      </div>
    </>
  );
}

window.Screens = window.Screens || {};
Object.assign(window.Screens, {
  PedidosScreen, PedidoDetalheScreen,
  ProdutosScreen, ProdutoDetalheScreen,
  FinancasScreen, TransacaoDetalheScreen,
  NotifsScreen, PerfilScreen, EmpresaScreen,
  LoginScreen,
});
