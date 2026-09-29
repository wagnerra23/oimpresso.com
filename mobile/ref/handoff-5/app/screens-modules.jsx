// Pedidos, Produtos, Finanças screens + Detail screens

const { Ic } = window;
const { PEDIDOS, PRODUTOS } = window.MOCK;
const { ScreenHeader, DetailHeader, HeaderTopRight, OriginBadge, StageStatus } = window.OIUi;
const BRL = window.BRL;
const BRLcompact = window.BRLcompact;

// ────────────────────────────────────────────────
// PEDIDOS
// ────────────────────────────────────────────────
function PedidosScreen({ nav, ctx }) {
  const [filter, setFilter] = React.useState("ativos");
  const [q, setQ] = React.useState("");
  const [mut] = window.useStore("pedidos.mut", window.OIPedidos.EMPTY_MUT);
  const all = window.OIPedidos.apply(PEDIDOS, mut);

  const filtered = all.filter(p => {
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
        eyebrow={all.length + " no total · " + all.filter(p => p.etapaKey !== "done").length + " ativos"}
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
                { id: "ativos", label: "Ativos", c: all.filter(p => p.etapaKey !== "done").length },
                { id: "urgent", label: "Urgentes", c: all.filter(p => p.urgent).length },
                { id: "concluidos", label: "Concluídos", c: all.filter(p => p.etapaKey === "done").length },
                { id: "todos", label: "Todos", c: all.length },
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
        <div style={{ padding: "4px 14px 0", display: "flex", flexDirection: "column", gap: 10 }}>
          {filtered.map(p => (
            <window.Widgets.OrderCard key={p.id} p={p} nav={nav} />
          ))}
          {filtered.length === 0 && (
            <div className="oi-empty" style={{ marginTop: 28 }}>
              <div className="oi-empty-ico"><Ic.inbox size={24} /></div>
              <b>Nenhum pedido</b>
              <small>Ajuste os filtros ou crie um novo pedido no botão abaixo.</small>
            </div>
          )}
        </div>
        <div style={{ height: 90 }} />
      </div>
      <div className="oi-fab" onClick={() => nav.push("novo-pedido")}>
        <Ic.plus size={26} />
      </div>
    </>
  );
}

function PedidoDetalheScreen({ nav, ctx, params }) {
  const [mut, setMut] = window.useStore("pedidos.mut", window.OIPedidos.EMPTY_MUT);
  const all = window.OIPedidos.apply(PEDIDOS, mut);
  const p = all.find(x => x.id === params.id) || all[0];
  const [sheet, setSheet] = React.useState(null); // "opcoes" | "faturar"
  const [parc, setParc] = React.useState(1);
  const [meioPg, setMeioPg] = React.useState("Boleto");
  const fat = window.OIFlow.faturamentoDe(p.id);
  const steps = [
    { k: "orc",     label: "Orçamento",  cta: "Aprovar orçamento", icCta: "checkCircle" },
    { k: "aprov",   label: "Aprovação",  cta: "Aprovar arte",      icCta: "checkCircle" },
    { k: "prod",    label: "Produção",   cta: "Finalizar produção", icCta: "checkCircle" },
    { k: "faturar", label: "Faturar",    cta: "Faturar pedido",    icCta: "dollar" },
    { k: "entrega", label: "Entrega",    cta: "Confirmar entrega", icCta: "truck" },
    { k: "done",    label: "Concluído",  cta: null,                icCta: "check" },
  ];
  const stepIdx = Math.max(0, steps.findIndex(s => s.k === p.etapaKey));
  const isDone = p.etapaKey === "done";
  const cur = steps[stepIdx];

  const avancar = () => {
    // Etapa de faturamento abre a folha de parcelas (não avança direto)
    if (p.etapaKey === "faturar") { setSheet("faturar"); return; }
    const next = window.OIPedidos.advance(p.etapaKey);
    setMut(m => ({ ...m, etapas: { ...m.etapas, [p.id]: next } }));
    window.oiToast(next === "done" ? p.id + " concluído" : "Etapa: " + window.OIPedidos.LABEL[next], "ok");
  };

  // Confirma o faturamento: cria título+parcelas no financeiro e avança p/ entrega
  const confirmarFaturamento = () => {
    const r = window.OIFlow.faturar({
      origemId: p.id, parte: p.cliente, parteId: p.clienteId,
      desc: p.nome, valor: p.valor, parcelas: parc, primeiroVencDias: 7, meio: meioPg,
      categoria: "Venda de impressos",
    });
    setSheet(null);
    if (!r.ok) { window.oiToast("Pedido já faturado", "warn"); return; }
    setMut(m => ({ ...m, etapas: { ...m.etapas, [p.id]: "entrega" } }));
    window.oiToast(parc > 1 ? `Faturado em ${parc}× · títulos no financeiro` : "Faturado · título no financeiro", "ok");
  };

  return (
    <>
      <DetailHeader nav={nav} title={p.id} eyebrow={p.cliente}
                    actions={<button className="oi-iconbtn" aria-label="Opções do pedido" onClick={() => setSheet("opcoes")}><Ic.dotsV /></button>} />
      <div className="oi-scroll">
        {/* Hero — bloco de registro com miniatura (modelo) */}
        <div className="oi-section">
          <div className="oi-card pad" style={{ flexDirection: "row", alignItems: "center", gap: 14 }}>
            <window.Widgets.ProductThumb thumb={p.thumb} size={66} />
            <div style={{ flex: "1 1 auto", minWidth: 0 }}>
              <div style={{ display: "flex", alignItems: "baseline", gap: 8 }}>
                <div style={{ fontWeight: 600, fontSize: 15, letterSpacing: "-0.01em", flex: "1 1 auto", minWidth: 0 }}>{p.nome}</div>
                <span className="oi-money" style={{ fontSize: 16, fontWeight: 700 }}>{BRL(p.valor)}</span>
              </div>
              <div style={{ fontSize: 11.5, color: "var(--text-dim)", marginTop: 3 }}>{p.specs}</div>
              <div style={{ display: "flex", alignItems: "baseline", gap: 8, marginTop: 4 }}>
                <span style={{ fontSize: 11.5, color: "var(--text-mute)", flex: "1 1 auto", minWidth: 0, overflow: "hidden", textOverflow: "ellipsis", whiteSpace: "nowrap" }}>Cliente: {p.cliente}</span>
                {p.prazo && p.prazo !== "—" && (
                  <span style={{ fontSize: 11.5, fontWeight: 600, fontFamily: "var(--font-mono)", color: p.urgent ? "var(--danger)" : "var(--text-mute)" }}>{p.prazo}</span>
                )}
              </div>
            </div>
          </div>
        </div>

        {/* Stepper grande (display) */}
        <div className="oi-section" style={{ paddingTop: 4 }}>
          <div className="oi-card pad">
            <window.Widgets.OrderStepper etapaKey={p.etapaKey} size="lg" />
          </div>
        </div>

        {/* Linha do tempo */}
        <div className="oi-section">
          <h3 className="oi-section-h">Linha do tempo</h3>
          <div className="oi-card pad">
            <OrderTimeline p={p} />
          </div>
        </div>

        {/* Detalhes do pedido */}
        <div className="oi-section">
          <h3 className="oi-section-h">Detalhes do pedido</h3>
          <div className="oi-card pad">
            <dl className="oi-dl">
              <dt>Data do pedido</dt>      <dd className="oi-mono">{p.criado}</dd>
              <dt>Previsão de entrega</dt> <dd className="oi-mono">{p.prazo}</dd>
              <dt>Forma de pagamento</dt>  <dd>Boleto · 7 dias</dd>
              <dt>Vendedor</dt>            <dd>Wagner Ribeiro</dd>
              <dt>Observações</dt>         <dd style={{ color: "var(--text-mute)" }}>—</dd>
            </dl>
          </div>
        </div>

        {/* Faturamento — títulos gerados (visível após faturar) */}
        {fat.faturado && (
          <div className="oi-section">
            <h3 className="oi-section-h">Faturamento</h3>
            <div className="oi-card pad">
              <div style={{ display: "flex", alignItems: "center", gap: 8, marginBottom: 10 }}>
                <span style={{ fontSize: 12, fontWeight: 700, color: "var(--ok)" }}>●</span>
                <span style={{ fontSize: 12.5, fontWeight: 600 }}>{fat.parcelas.length}× · {BRL(fat.total)} no financeiro</span>
                <button className="oi-btn sm" style={{ marginLeft: "auto", height: 28, padding: "0 10px" }}
                        onClick={() => nav.push("financeiro")}>Ver em A receber</button>
              </div>
              <div style={{ display: "flex", flexDirection: "column", gap: 6 }}>
                {fat.parcelas.map(t => (
                  <div key={t.id} onClick={() => nav.push("transacao", { id: t.id })}
                       style={{ display: "flex", justifyContent: "space-between", alignItems: "center", padding: "7px 0", borderTop: "1px solid var(--border)", cursor: "pointer" }}>
                    <span style={{ fontSize: 12, color: "var(--text-dim)" }}>{t.desc}</span>
                    <span style={{ display: "flex", gap: 10, alignItems: "baseline" }}>
                      <span style={{ fontSize: 11, color: t.status === "vencido" ? "var(--danger)" : "var(--text-mute)", fontFamily: "var(--font-mono)" }}>{t.vencLabel}</span>
                      <span className="oi-mono" style={{ fontSize: 12.5, fontWeight: 700 }}>{BRL(t.valor)}</span>
                    </span>
                  </div>
                ))}
              </div>
            </div>
          </div>
        )}

        <div style={{ height: 100 }} />
      </div>

      {/* Action bar — comunicação (modelo) */}
      <div style={{ padding: 12, background: "var(--surface)", borderTop: "1px solid var(--border)",
                    display: "flex", gap: 10 }}>
        <button className="oi-btn secondary" style={{ flex: 1 }} onClick={() => window.oiToast("Abrindo conversa…")}>
          <Ic.inbox size={17} /> Conversar
        </button>
        {!isDone ? (
          <button className="oi-btn primary" style={{ flex: 1.3 }} onClick={avancar}>
            {React.createElement(Ic[cur.icCta] || Ic.check, { size: 17 })} {cur.cta}
          </button>
        ) : (
          <button className="oi-btn primary" style={{ flex: 1.3 }} onClick={() => window.oiToast("Lembrete enviado", "ok")}>
            <Ic.clock size={17} /> Enviar lembrete
          </button>
        )}
      </div>

      {sheet === "faturar" && (
        <window.OISheet title="Faturar pedido" onClose={() => setSheet(null)}
          footer={<button className="oi-btn primary block" onClick={confirmarFaturamento}><Ic.checkCircle size={18} /> Confirmar faturamento</button>}>
          <div style={{ display: "flex", flexDirection: "column", gap: 16 }}>
            <div style={{ display: "flex", justifyContent: "space-between", alignItems: "baseline" }}>
              <span style={{ fontSize: 12.5, color: "var(--text-mute)" }}>Total do pedido</span>
              <span className="oi-mono" style={{ fontSize: 19, fontWeight: 700 }}>{BRL(p.valor)}</span>
            </div>
            <div>
              <div style={{ fontSize: 11, fontWeight: 700, textTransform: "uppercase", letterSpacing: ".05em", color: "var(--text-mute)", marginBottom: 7 }}>Parcelas</div>
              <div style={{ display: "flex", gap: 6 }}>
                {[1, 2, 3, 6].map(n => (
                  <button key={n} onClick={() => setParc(n)}
                          style={{ flex: 1, height: 38, borderRadius: 8, cursor: "pointer", fontFamily: "inherit", fontSize: 13, fontWeight: 700,
                                   border: "1px solid " + (parc === n ? "var(--accent)" : "var(--border)"),
                                   background: parc === n ? "var(--accent)" : "var(--bg-2)", color: parc === n ? "#fff" : "var(--text)" }}>{n}×</button>
                ))}
              </div>
            </div>
            <div>
              <div style={{ fontSize: 11, fontWeight: 700, textTransform: "uppercase", letterSpacing: ".05em", color: "var(--text-mute)", marginBottom: 7 }}>Meio</div>
              <div style={{ display: "flex", gap: 6 }}>
                {["Boleto", "PIX", "Cartão"].map(mp => (
                  <button key={mp} onClick={() => setMeioPg(mp)}
                          style={{ flex: 1, height: 38, borderRadius: 8, cursor: "pointer", fontFamily: "inherit", fontSize: 12.5, fontWeight: 600,
                                   border: "1px solid " + (meioPg === mp ? "var(--accent)" : "var(--border)"),
                                   background: meioPg === mp ? "var(--accent-soft)" : "var(--bg-2)", color: meioPg === mp ? "var(--accent)" : "var(--text-dim)" }}>{mp}</button>
                ))}
              </div>
            </div>
            <div style={{ background: "var(--bg-2)", borderRadius: 10, padding: "10px 12px" }}>
              <div style={{ fontSize: 11, color: "var(--text-mute)", marginBottom: 6 }}>Prévia das parcelas</div>
              {window.OIFlow.gerarParcelas(p.valor, parc, 7).map(pp => (
                <div key={pp.numero} style={{ display: "flex", justifyContent: "space-between", padding: "3px 0", fontSize: 12.5 }}>
                  <span style={{ color: "var(--text-dim)" }}>{parc > 1 ? pp.numero + "ª parcela" : "À vista"} · <span style={{ fontFamily: "var(--font-mono)", color: "var(--text-mute)" }}>{pp.vencLabel}</span></span>
                  <span className="oi-mono" style={{ fontWeight: 700 }}>{BRL(pp.valor)}</span>
                </div>
              ))}
            </div>
          </div>
        </window.OISheet>
      )}

      {sheet === "opcoes" && (
        <window.OISheet title="Opções do pedido" onClose={() => setSheet(null)}>
          <div style={{ display: "flex", flexDirection: "column", gap: 8 }}>
            <button className="oi-btn block" style={{ justifyContent: "flex-start", gap: 10 }}
                    onClick={() => { setSheet(null); window.oiToast("Pedido duplicado como rascunho", "ok"); }}>
              <Ic.plus size={17} /> Duplicar pedido
            </button>
            <button className="oi-btn block" style={{ justifyContent: "flex-start", gap: 10 }}
                    onClick={() => { setSheet(null); window.oiToast("Exportando PDF… (simulado)"); }}>
              <Ic.file size={17} /> Exportar PDF
            </button>
            {!isDone && (
              <button className="oi-btn block" style={{ justifyContent: "flex-start", gap: 10, color: "var(--danger)" }}
                      onClick={() => { setMut(m => ({ ...m, etapas: { ...m.etapas, [p.id]: "done" } })); setSheet(null); window.oiToast("Pedido arquivado"); }}>
                <Ic.x size={17} /> Arquivar pedido
              </button>
            )}
          </div>
        </window.OISheet>
      )}
    </>
  );
}

// Linha do tempo de um pedido — derivada do pipeline + etapa atual
function OrderTimeline({ p }) {
  const cur = window.Widgets.STAGE_IDX[p.etapaKey];
  const curIdx = cur != null ? cur : 0;
  const date = (p.criado || "Hoje").split(" ")[0];
  const rows = [
    { title: "Orçamento enviado",  who: "Wagner Ribeiro", time: "09:15" },
    { title: "Arte enviada",       who: "Wagner Ribeiro", time: "10:42" },
    { title: curIdx === 2 ? "Aguardando aprovação do cliente" : "Aprovação concluída", who: p.cliente, time: "11:08" },
    { title: "Produção",           who: null, time: null },
    { title: "Entrega",            who: null, time: null },
  ];
  return (
    <div style={{ display: "flex", flexDirection: "column" }}>
      {rows.map((r, i) => {
        const done = i < curIdx;
        const isCur = i === curIdx;
        const last = i === rows.length - 1;
        let node;
        if (done) node = <span style={{ width: 22, height: 22, borderRadius: 99, background: "var(--ok)", display: "grid", placeItems: "center" }}><Ic.check size={12} color="#fff" strokeWidth={3} /></span>;
        else if (isCur) node = <span style={{ width: 22, height: 22, borderRadius: 99, background: "var(--warn)", display: "grid", placeItems: "center" }}><Ic.clock size={13} color="#fff" /></span>;
        else node = <span style={{ width: 22, height: 22, borderRadius: 99, border: "2px solid var(--border)", background: "var(--surface)" }} />;
        const active = done || isCur;
        return (
          <div key={i} style={{ display: "flex", gap: 12, alignItems: "flex-start" }}>
            <div className="oi-mono" style={{ width: 64, fontSize: 11, color: active ? "var(--text-dim)" : "var(--text-mute)", paddingTop: 3, textAlign: "right", flex: "0 0 auto" }}>
              {active && r.time ? date + " " + r.time : ""}
            </div>
            <div style={{ display: "flex", flexDirection: "column", alignItems: "center", flex: "0 0 auto" }}>
              {node}
              {!last && <span style={{ width: 2, flex: 1, minHeight: 22, background: done ? "var(--ok)" : "var(--border)", marginTop: 2 }} />}
            </div>
            <div style={{ flex: "1 1 auto", paddingBottom: last ? 0 : 16 }}>
              <div style={{ fontSize: 13, fontWeight: active ? 600 : 500, color: active ? "var(--text)" : "var(--text-mute)", lineHeight: 1.3 }}>{r.title}</div>
              {active && r.who && <div style={{ fontSize: 11, color: "var(--text-mute)", marginTop: 1 }}>{r.who}</div>}
            </div>
          </div>
        );
      })}
    </div>
  );
}


function ContactRow({ ic, label, sub, last, onClick }) {
  return (
    <div onClick={onClick} style={{ display: "flex", alignItems: "center", gap: 12, padding: "10px 0", cursor: onClick ? "pointer" : "default",
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
  const base = PRODUTOS.find(x => x.id === params.id) || PRODUTOS[0];
  const [estoqueMut, setEstoqueMut] = window.useStore("produtos.estoque", {});
  const p = estoqueMut[base.id] != null ? { ...base, estoque: estoqueMut[base.id] } : base;
  const baixo = p.estoque < p.min;
  const [sheet, setSheet] = React.useState(null); // "entrada"
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
              <button className="oi-btn sm" style={{ flex: 1 }} onClick={() => setSheet("entrada")}><Ic.plus size={14} /> Entrada</button>
              <button className="oi-btn sm" style={{ flex: 1 }} onClick={() => window.oiToast("Gerando etiqueta… (simulado)")}><Ic.printer size={14} /> Etiqueta</button>
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
            {((estoqueMut["movs." + p.id] || [])).concat([
              { ic: "arrow-down", color: "var(--danger)", t: "Hoje 09:14", l: "Saída · OS-3041", v: -10 },
              { ic: "arrow-up",   color: "var(--ok)",     t: "Ontem 14:02", l: "Entrada · NF 8821",  v: 50 },
              { ic: "arrow-down", color: "var(--danger)", t: "Sex 11:30",   l: "Saída · OS-3035", v: -8 },
            ]).map((m, i, arr) => (
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

      {sheet === "entrada" && (
        <EntradaEstoqueSheet p={p} onClose={() => setSheet(null)}
          onConfirm={(qtd) => {
            setEstoqueMut(m => ({
              ...m,
              [p.id]: p.estoque + qtd,
              ["movs." + p.id]: [{ ic: "arrow-up", color: "var(--ok)", t: "Agora", l: "Entrada · manual", v: qtd },
                                 ...(m["movs." + p.id] || [])],
            }));
            setSheet(null);
            window.oiToast("+" + qtd + " " + p.un + " em estoque", "ok");
          }} />
      )}
    </>
  );
}

// ─── Sheet: entrada de estoque ───
function EntradaEstoqueSheet({ p, onClose, onConfirm }) {
  const [qtd, setQtd] = React.useState(10);
  return (
    <window.OISheet title="Entrada de estoque" onClose={onClose}>
      <div style={{ display: "flex", flexDirection: "column", gap: 14 }}>
        <div style={{ fontSize: 13, color: "var(--text-dim)" }}>{p.nome} · em estoque: <b className="oi-mono">{p.estoque} {p.un}</b></div>
        <div style={{ display: "flex", alignItems: "center", justifyContent: "center", gap: 18 }}>
          <button className="oi-iconbtn" aria-label="Diminuir" style={{ width: 44, height: 44, background: "var(--bg-2)", borderRadius: 12 }}
                  onClick={() => setQtd(q => Math.max(1, q - (q > 10 ? 10 : 1)))}><Ic.minus size={18} /></button>
          <div style={{ textAlign: "center", minWidth: 90 }}>
            <div className="oi-mono" style={{ fontSize: 34, fontWeight: 700 }}>{qtd}</div>
            <div style={{ fontSize: 11, color: "var(--text-mute)" }}>{p.un}</div>
          </div>
          <button className="oi-iconbtn" aria-label="Aumentar" style={{ width: 44, height: 44, background: "var(--bg-2)", borderRadius: 12 }}
                  onClick={() => setQtd(q => q + (q >= 10 ? 10 : 1))}><Ic.plus size={18} /></button>
        </div>
        <button className="oi-btn block primary" onClick={() => onConfirm(qtd)}>Confirmar entrada</button>
      </div>
    </window.OISheet>
  );
}

function NotifsScreen({ nav, ctx }) {
  const [read, setRead] = window.useStore("notifs.read", []);
  const notifs = window.MOCK.NOTIFS.map(n => ({ ...n, unread: n.unread && !read.includes(n.id) }));
  const unreadCount = notifs.filter(n => n.unread).length;
  // destino de cada notificação (navegar à origem)
  const NOTIF_TO = {
    n1: () => nav.push("pedido", { id: "OS-3041" }),
    n2: () => nav.push("transacao", { id: "T-1042" }),
    n3: () => nav.push("producao-job", { id: "MFG-12" }),
    n4: () => nav.push("cliente", { id: "c4" }),
    n5: () => nav.push("tarefa", { id: "t-pnt-5" }),
  };
  const open = (n) => {
    setRead(r => r.includes(n.id) ? r : [...r, n.id]);
    const go = NOTIF_TO[n.id];
    if (go) go(); else window.oiToast("Sem detalhe vinculado");
  };
  const markAll = () => {
    if (unreadCount === 0) return;
    setRead(window.MOCK.NOTIFS.map(n => n.id));
    window.oiToast("Todas marcadas como lidas", "ok");
  };
  return (
    <>
      <DetailHeader nav={nav} title="Notificações"
                    eyebrow={unreadCount > 0 ? unreadCount + " não lidas" : "Tudo lido"}
                    actions={<button className="oi-iconbtn" aria-label="Marcar todas como lidas" onClick={markAll} style={unreadCount === 0 ? { opacity: .4 } : {}}><Ic.check size={22} /></button>} />
      <div className="oi-scroll">
        <div className="oi-list">
          {notifs.map(n => (
            <div key={n.id} className="oi-list-row" onClick={() => open(n)} style={{ cursor: "pointer", background: n.unread ? "var(--accent-soft)" : undefined }}>
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
            <SettingsRow ic="bell" label="Notificações push" right={<Toggle on={true} onChange={v => window.oiToast(v ? "Notificações ativadas" : "Notificações silenciadas")} />} />
            <SettingsRow ic="shield" label="Privacidade" right={<Ic.chevR color="var(--text-mute)" />} onClick={() => window.oiToast("Abrindo privacidade…")} last />
          </div>
        </div>

        <div className="oi-section">
          <h3 className="oi-section-h">Conta</h3>
          <div className="oi-list card">
            <SettingsRow ic="user" label="Dados pessoais" right={<Ic.chevR color="var(--text-mute)" />} onClick={() => window.oiToast("Abrindo dados pessoais…")} />
            <SettingsRow ic="settings" label="Configurações" right={<Ic.chevR color="var(--text-mute)" />} onClick={() => window.oiToast("Abrindo configurações…")} />
            <SettingsRow ic="logout" label="Sair" danger last onClick={() => window.oiToast("Saindo da conta…")} />
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
          <button className="oi-btn block" onClick={() => window.oiToast("Adicionar empresa ao grupo")}><Ic.plus size={16} /> Adicionar empresa</button>
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
            <button className="oi-btn block secondary" style={{ height: 50 }}>
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
  NotifsScreen, PerfilScreen, EmpresaScreen,
  LoginScreen,
});
