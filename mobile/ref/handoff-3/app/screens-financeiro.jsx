// ────────────────────────────────────────────────
// FINANCEIRO — Caixa + A Receber + A Pagar (navegação por abas)
// ────────────────────────────────────────────────
const { Ic: IcF } = window;
const MF = window.MOCK;
const { DetailHeader: DHf } = window.Screens;
const BRLf = window.BRL;

function brlFull(n) { return (n < 0 ? "-" : "") + "R$ " + Math.abs(n).toLocaleString("pt-BR", { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
function finMeta(l) {
  if (l.status === "liquidado") return { label: l.tipo === "receber" ? "Recebido" : "Pago", tone: "ok" };
  if (l.status === "vencido") return { label: "Vencido", tone: "danger" };
  return { label: "A vencer", tone: "warn" };
}
const FIN_TONE = { ok: "var(--ok)", danger: "var(--danger)", warn: "var(--warn)", accent: "var(--accent)", muted: "var(--text-mute)" };
function contaNome(id) { return (MF.FIN_CONTAS.find(c => c.id === id) || {}).nome || "—"; }

// ─── Lançamentos = mock + criados no app, com liquidações persistidas ───
function useFinLanc() {
  const [extra, setExtra] = window.useStore("fin.extra", []);
  const [liq, setLiq] = window.useStore("fin.liq", {});
  const all = MF.FIN_LANC.concat(extra).map(l => liq[l.id] ? { ...l, ...liq[l.id] } : l);
  const liquidar = (id) => {
    const l = all.find(x => x.id === id);
    if (!l || l.status === "liquidado") return;
    setLiq(m => ({ ...m, [id]: { status: "liquidado", liqLabel: "Agora", conta: l.conta || "cc" } }));
    window.oiToast && window.oiToast(l.tipo === "receber" ? "Recebimento registrado" : "Pagamento registrado", "ok");
  };
  const criar = (l) => setExtra(e => [l, ...e]);
  return { all, liquidar, criar };
}

// ─── Linha de lançamento ───
function FinRow({ l, nav, onLiquidar }) {
  const inn = l.tipo === "receber";
  const meta = finMeta(l);
  const liq = l.status === "liquidado";
  return (
    <div className="oi-list-row" style={{ alignItems: "center", gap: 11 }} onClick={() => nav.push("transacao", { id: l.id })}>
      <div style={{ width: 36, height: 36, borderRadius: 10, flex: "0 0 auto", display: "grid", placeItems: "center",
                    background: inn ? "color-mix(in oklch, var(--ok) 16%, transparent)" : "color-mix(in oklch, var(--danger) 14%, transparent)",
                    color: inn ? "var(--ok)" : "var(--danger)" }}>
        {React.createElement(inn ? IcF.arrowDown : IcF.arrowUp, { size: 17 })}
      </div>
      <div style={{ flex: 1, minWidth: 0 }}>
        <div style={{ fontSize: 13.5, fontWeight: 600, lineHeight: 1.25, whiteSpace: "nowrap", overflow: "hidden", textOverflow: "ellipsis" }}>{l.parte}</div>
        <div style={{ fontSize: 11.5, color: "var(--text-mute)", whiteSpace: "nowrap", overflow: "hidden", textOverflow: "ellipsis" }}>
          {l.desc}{l.origem && l.origem.match(/^(OS|MAN)/) ? " · " + l.origem : ""}
        </div>
        <div style={{ display: "flex", alignItems: "center", gap: 6, marginTop: 3 }}>
          <span className={"oi-status " + meta.tone} style={{ fontSize: 9.5, padding: "1px 6px" }}><span className="dot" />{meta.label}</span>
          <span className="oi-mono" style={{ fontSize: 10.5, color: liq ? "var(--text-mute)" : meta.tone === "danger" ? "var(--danger)" : "var(--text-mute)" }}>
            {liq ? l.liqLabel : "vence " + l.vencLabel.toLowerCase()}
          </span>
        </div>
      </div>
      <div style={{ display: "flex", flexDirection: "column", alignItems: "flex-end", gap: 5, flex: "0 0 auto" }}>
        <span className="oi-money" style={{ fontSize: 14, fontWeight: 700, color: inn ? "var(--ok)" : "var(--text)" }}>
          {inn ? "+" : "−"}{brlFull(l.valor).replace("R$ ", "")}
        </span>
        {!liq && (
          <button className="oi-btn sm" style={{ height: 26, padding: "0 10px", borderColor: inn ? "var(--ok)" : "var(--danger)", color: inn ? "var(--ok)" : "var(--danger)" }}
                  onClick={(e) => { e.stopPropagation(); onLiquidar(l.id); }}>
            {inn ? "Receber" : "Pagar"}
          </button>
        )}
      </div>
    </div>
  );
}

// ─── Stat tocável (resumo) ───
function FinStat({ label, value, sub, subTone, tone, onClick }) {
  return (
    <button onClick={onClick} style={{ appearance: "none", cursor: "pointer", textAlign: "left", background: "var(--surface)",
              border: "1px solid var(--border)", borderRadius: 12, padding: "11px 12px", display: "flex", flexDirection: "column", gap: 3 }}>
      <span style={{ fontSize: 10.5, fontWeight: 700, letterSpacing: ".04em", textTransform: "uppercase", color: "var(--text-mute)" }}>{label}</span>
      <span className="oi-money" style={{ fontSize: 17, fontWeight: 700, letterSpacing: "-0.01em", color: tone ? FIN_TONE[tone] : "var(--text)" }}>{brlFull(value)}</span>
      {sub && <span style={{ fontSize: 10.5, fontWeight: 600, color: FIN_TONE[subTone || "muted"] }}>{sub}</span>}
    </button>
  );
}

// ─── Tela principal ───
function FinancasScreen({ nav, ctx, params }) {
  const [tab, setTab] = React.useState((params && params.tab) || "resumo");
  const [fStatus, setFStatus] = React.useState("todos"); // filtro das listas
  const [q, setQ] = React.useState("");
  const [conta, setConta] = React.useState("todas");
  const [sheet, setSheet] = React.useState(null); // "novo"
  const { all: LANC, liquidar, criar } = useFinLanc();
  const R = MF.finResumo(LANC);

  const TABS = [{ id: "resumo", label: "Resumo" }, { id: "receber", label: "A receber" }, { id: "pagar", label: "A pagar" }, { id: "extrato", label: "Extrato" }];

  // lista filtrada p/ receber/pagar
  const lista = (tipo) => {
    let l = LANC.filter(x => x.tipo === tipo);
    if (fStatus === "avencer") l = l.filter(x => x.status === "aberto");
    else if (fStatus === "vencido") l = l.filter(x => x.status === "vencido");
    else if (fStatus === "liquidado") l = l.filter(x => x.status === "liquidado");
    else l = l.filter(x => x.status !== "liquidado"); // "todos" = em aberto (a vencer + vencidos)
    if (q) { const k = q.toLowerCase(); l = l.filter(x => (x.parte + " " + x.desc + " " + (x.origem || "")).toLowerCase().includes(k)); }
    return l.sort((a, b) => a.vencDias - b.vencDias);
  };

  return (
    <>
      <DHf nav={nav} title="Financeiro" eyebrow={"Saldo em contas " + brlFull(R.saldoContas)}
           actions={<button className="oi-iconbtn" aria-label="Conciliação bancária" onClick={() => window.oiToast("Conciliação bancária via OFX (simulado)")}><IcF.refresh size={18} /></button>} />

      {/* abas */}
      <div style={{ display: "flex", gap: 4, padding: "8px 16px 10px", borderBottom: "1px solid var(--border)", background: "var(--surface)" }}>
        {TABS.map(t => (
          <button key={t.id} onClick={() => { setTab(t.id); setFStatus("todos"); }}
                  style={{ appearance: "none", cursor: "pointer", flex: 1, height: 32, borderRadius: 8, border: 0, fontFamily: "inherit",
                           background: tab === t.id ? "var(--accent)" : "var(--bg-2)", color: tab === t.id ? "#fff" : "var(--text-mute)",
                           fontSize: 11.5, fontWeight: 700 }}>{t.label}</button>
        ))}
      </div>

      <div className="oi-scroll">
        {tab === "resumo" && <ResumoView R={R} nav={nav} go={setTab} goConta={(id) => { setTab("extrato"); setConta(id); }} />}

        {(tab === "receber" || tab === "pagar") && (() => {
          const tipo = tab === "receber" ? "receber" : "pagar";
          const inn = tipo === "receber";
          const abertos = LANC.filter(x => x.tipo === tipo && x.status === "aberto");
          const vencidos = LANC.filter(x => x.tipo === tipo && x.status === "vencido");
          const itens = lista(tipo);
          return (
            <>
              {/* resumo da aba */}
              <div className="oi-section" style={{ paddingBottom: 0 }}>
                <div style={{ display: "flex", gap: 8 }}>
                  <div style={{ flex: 1, background: "var(--surface)", border: "1px solid var(--border)", borderRadius: 10, padding: "9px 11px" }}>
                    <div style={{ fontSize: 10, fontWeight: 700, textTransform: "uppercase", letterSpacing: ".04em", color: "var(--warn)" }}>A vencer</div>
                    <div className="oi-money" style={{ fontSize: 15, fontWeight: 700 }}>{brlFull(MF.finSum(abertos))}</div>
                    <div style={{ fontSize: 10, color: "var(--text-mute)" }}>{abertos.length} título(s)</div>
                  </div>
                  <div style={{ flex: 1, background: "var(--surface)", border: "1px solid " + (vencidos.length ? "color-mix(in oklch, var(--danger) 45%, var(--border))" : "var(--border)"), borderRadius: 10, padding: "9px 11px" }}>
                    <div style={{ fontSize: 10, fontWeight: 700, textTransform: "uppercase", letterSpacing: ".04em", color: "var(--danger)" }}>Vencidos</div>
                    <div className="oi-money" style={{ fontSize: 15, fontWeight: 700, color: vencidos.length ? "var(--danger)" : "var(--text)" }}>{brlFull(MF.finSum(vencidos))}</div>
                    <div style={{ fontSize: 10, color: "var(--text-mute)" }}>{vencidos.length} título(s)</div>
                  </div>
                </div>
              </div>

              {/* busca + chips */}
              <div className="oi-section" style={{ paddingTop: 12, paddingBottom: 0 }}>
                <div className="oi-search">
                  <IcF.search size={16} />
                  <input placeholder={inn ? "Cliente, OS, descrição…" : "Fornecedor, descrição…"} value={q} onChange={e => setQ(e.target.value)} />
                  {q && <button className="oi-iconbtn" style={{ width: 26, height: 26, background: "transparent" }} onClick={() => setQ("")}><IcF.x size={15} /></button>}
                </div>
                <div className="oi-chips" style={{ marginTop: 10 }}>
                  {[["todos", "Em aberto"], ["avencer", "A vencer"], ["vencido", "Vencidos"], ["liquidado", inn ? "Recebidos" : "Pagos"]].map(([id, l]) => (
                    <button key={id} className={"oi-chip" + (fStatus === id ? " on" : "")} onClick={() => setFStatus(id)}>{l}</button>
                  ))}
                </div>
              </div>

              <div className="oi-section" style={{ paddingTop: 12 }}>
                {itens.length === 0
                  ? <div className="oi-empty" style={{ marginTop: 16 }}><div className="oi-empty-ico ok"><IcF.checkCircle size={24} /></div><b>Nada por aqui</b><small>Nenhum título com esse filtro.</small></div>
                  : <div className="oi-list card">{itens.map(l => <FinRow key={l.id} l={l} nav={nav} onLiquidar={liquidar} />)}</div>}
              </div>
              <div style={{ height: 90 }} />
            </>
          );
        })()}

        {tab === "extrato" && (() => {
          let liq = LANC.filter(x => x.status === "liquidado");
          if (conta !== "todas") liq = liq.filter(x => x.conta === conta);
          liq = liq.sort((a, b) => b.vencDias - a.vencDias);
          const ent = MF.finSum(liq.filter(x => x.tipo === "receber"));
          const sai = MF.finSum(liq.filter(x => x.tipo === "pagar"));
          return (
            <>
              <div className="oi-section" style={{ paddingBottom: 0 }}>
                <div style={{ display: "flex", gap: 8 }}>
                  <div style={{ flex: 1, background: "var(--surface)", border: "1px solid var(--border)", borderRadius: 10, padding: "9px 11px" }}>
                    <div style={{ fontSize: 10, fontWeight: 700, textTransform: "uppercase", letterSpacing: ".04em", color: "var(--ok)" }}>Entradas</div>
                    <div className="oi-money" style={{ fontSize: 15, fontWeight: 700, color: "var(--ok)" }}>{brlFull(ent)}</div>
                  </div>
                  <div style={{ flex: 1, background: "var(--surface)", border: "1px solid var(--border)", borderRadius: 10, padding: "9px 11px" }}>
                    <div style={{ fontSize: 10, fontWeight: 700, textTransform: "uppercase", letterSpacing: ".04em", color: "var(--danger)" }}>Saídas</div>
                    <div className="oi-money" style={{ fontSize: 15, fontWeight: 700 }}>{brlFull(sai)}</div>
                  </div>
                  <div style={{ flex: 1, background: "var(--surface)", border: "1px solid var(--border)", borderRadius: 10, padding: "9px 11px" }}>
                    <div style={{ fontSize: 10, fontWeight: 700, textTransform: "uppercase", letterSpacing: ".04em", color: "var(--text-mute)" }}>Resultado</div>
                    <div className="oi-money" style={{ fontSize: 15, fontWeight: 700, color: ent - sai >= 0 ? "var(--ok)" : "var(--danger)" }}>{brlFull(ent - sai)}</div>
                  </div>
                </div>
              </div>
              <div className="oi-section" style={{ paddingTop: 12, paddingBottom: 0 }}>
                <div className="oi-chips">
                  {[["todas", "Todas as contas"]].concat(MF.FIN_CONTAS.map(c => [c.id, c.nome])).map(([id, l]) => (
                    <button key={id} className={"oi-chip" + (conta === id ? " on" : "")} onClick={() => setConta(id)}>{l}</button>
                  ))}
                </div>
              </div>
              <div className="oi-section" style={{ paddingTop: 12 }}>
                <div className="oi-list card">{liq.map(l => <FinRow key={l.id} l={l} nav={nav} onLiquidar={liquidar} />)}</div>
              </div>
              <div style={{ height: 90 }} />
            </>
          );
        })()}
      </div>

      {(tab === "receber" || tab === "pagar" || tab === "resumo") && (
        <div className="oi-fab" onClick={() => setSheet("novo")} role="button" aria-label="Novo lançamento"><IcF.plus size={26} /></div>
      )}

      {sheet === "novo" && (
        <NovoLancamentoSheet onClose={() => setSheet(null)}
          onCreate={(l) => { criar(l); setSheet(null); window.oiToast("Lançamento " + l.id + " criado", "ok"); }} />
      )}
    </>
  );
}

// ─── Sheet: novo lançamento (receita / despesa) ───
function NovoLancamentoSheet({ onClose, onCreate }) {
  const [tipo, setTipo] = React.useState("receber");
  const [desc, setDesc] = React.useState("");
  const [parte, setParte] = React.useState("");
  const [valor, setValor] = React.useState("");
  const [cat, setCat] = React.useState(null);
  const [venc, setVenc] = React.useState("liquidado"); // liquidado | hoje | 7 | 30
  const cats = tipo === "receber" ? MF.FIN_CATEGORIAS.receita : MF.FIN_CATEGORIAS.custo.concat(MF.FIN_CATEGORIAS.despesa);
  const v = parseFloat(String(valor).replace(/\./g, "").replace(",", "."));
  const ok = desc.trim() && parte.trim() && v > 0;
  const lbl = { fs: 11, fw: 600 };
  const L = ({ children }) => <div style={{ fontSize: 11, fontWeight: 600, textTransform: "uppercase", letterSpacing: ".06em", color: "var(--text-mute)", marginBottom: 6 }}>{children}</div>;
  const criar = () => {
    if (!ok) return;
    const liquidado = venc === "liquidado";
    const dias = venc === "hoje" ? 0 : venc === "7" ? 7 : 30;
    onCreate({
      id: (tipo === "receber" ? "R-" : "P-") + String(Date.now()).slice(-5),
      tipo, desc: desc.trim(), parte: parte.trim(), parteId: null,
      categoria: cat || cats[0], origem: "Manual", valor: v,
      vencDias: liquidado ? 0 : dias,
      vencLabel: liquidado ? "Hoje" : dias === 0 ? "Hoje" : "Em " + dias + " dias",
      status: liquidado ? "liquidado" : "aberto",
      liqLabel: liquidado ? "Agora" : undefined,
      conta: liquidado ? "cc" : undefined,
      meio: tipo === "receber" ? "PIX" : "Boleto",
    });
  };
  return (
    <window.OISheet title="Novo lançamento" onClose={onClose}>
      <div style={{ display: "flex", flexDirection: "column", gap: 14 }}>
        <div style={{ display: "flex", gap: 8 }}>
          {[["receber", "Receita", "var(--ok)"], ["pagar", "Despesa", "var(--danger)"]].map(([id, l, c]) => (
            <button key={id} className="oi-btn" style={{ flex: 1, ...(tipo === id ? { borderColor: c, color: c, background: "color-mix(in oklch, " + c + " 10%, transparent)" } : {}) }}
                    onClick={() => { setTipo(id); setCat(null); }}>
              {React.createElement(id === "receber" ? IcF.arrowDown : IcF.arrowUp, { size: 15 })} {l}
            </button>
          ))}
        </div>
        <div>
          <L>Descrição</L>
          <input className="oi-input" autoFocus placeholder={tipo === "receber" ? "Ex.: Banner 2x1m" : "Ex.: Tinta eco-solvente"} value={desc} onChange={e => setDesc(e.target.value)} />
        </div>
        <div style={{ display: "grid", gridTemplateColumns: "1fr 130px", gap: 10 }}>
          <div>
            <L>{tipo === "receber" ? "Cliente" : "Favorecido"}</L>
            <input className="oi-input" placeholder="Nome" value={parte} onChange={e => setParte(e.target.value)} />
          </div>
          <div>
            <L>Valor (R$)</L>
            <input className="oi-input oi-mono" inputMode="decimal" placeholder="0,00" value={valor} onChange={e => setValor(e.target.value)} />
          </div>
        </div>
        <div>
          <L>Categoria</L>
          <div className="oi-chips">
            {cats.slice(0, 6).map(c => (
              <button key={c} className={"oi-chip" + ((cat || cats[0]) === c ? " on" : "")} onClick={() => setCat(c)}>{c}</button>
            ))}
          </div>
        </div>
        <div>
          <L>Situação</L>
          <div className="oi-chips">
            {[["liquidado", tipo === "receber" ? "Já recebido" : "Já pago"], ["hoje", "Vence hoje"], ["7", "Em 7 dias"], ["30", "Em 30 dias"]].map(([id, l]) => (
              <button key={id} className={"oi-chip" + (venc === id ? " on" : "")} onClick={() => setVenc(id)}>{l}</button>
            ))}
          </div>
        </div>
        <button className="oi-btn block primary" disabled={!ok} onClick={criar}>Salvar lançamento</button>
      </div>
    </window.OISheet>
  );
}

// ─── Resumo ───
function ResumoView({ R, nav, go, goConta }) {
  const maxRes = Math.max(R.recebidoMes, R.pagoMes, 1);
  return (
    <>
      {/* saldo + projetado — Card principal (KPI) */}
      <div className="oi-section">
        <div className="oi-card kpi pad" style={{ gap: 4 }}>
          <div style={{ fontSize: 11.5, opacity: .85, fontWeight: 600 }}>Saldo em contas</div>
          <div className="oi-money" style={{ fontSize: 30, fontWeight: 700, letterSpacing: "-0.02em", lineHeight: 1 }}>{brlFull(R.saldoContas)}</div>
          <div style={{ display: "flex", alignItems: "center", gap: 6, marginTop: 6, fontSize: 11.5, opacity: .92 }}>
            <IcF.trendingUp size={14} /> Projeção c/ títulos em aberto:
            <span className="oi-money" style={{ fontWeight: 700 }}>{brlFull(R.saldoProjetado)}</span>
          </div>
        </div>
      </div>

      {/* 4 stats */}
      <div className="oi-section" style={{ paddingTop: 0 }}>
        <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr", gap: 8 }}>
          <FinStat label="A receber" value={R.aReceber} tone="ok"
                   sub={R.counts.aReceberVenc ? brlFull(R.aReceberVenc) + " vencidos" : R.counts.aReceber + " em aberto"}
                   subTone={R.counts.aReceberVenc ? "danger" : "muted"} onClick={() => go("receber")} />
          <FinStat label="A pagar" value={R.aPagar}
                   sub={R.counts.aPagarVenc ? brlFull(R.aPagarVenc) + " vencidos" : R.counts.aPagar + " em aberto"}
                   subTone={R.counts.aPagarVenc ? "danger" : "muted"} onClick={() => go("pagar")} />
          <FinStat label="Recebido (mês)" value={R.recebidoMes} tone="ok" sub={R.counts.recebido + " lançamentos"} onClick={() => go("extrato")} />
          <FinStat label="Pago (mês)" value={R.pagoMes} sub={R.counts.pago + " lançamentos"} onClick={() => go("extrato")} />
        </div>
      </div>

      {/* resultado do mês */}
      <div className="oi-section" style={{ paddingTop: 0 }}>
        <h3 className="oi-section-h">Resultado do mês</h3>
        <div className="oi-card pad" style={{ gap: 10 }}>
          {[["Entradas", R.recebidoMes, "var(--ok)"], ["Saídas", R.pagoMes, "var(--danger)"]].map(([l, v, c]) => (
            <div key={l} style={{ display: "flex", flexDirection: "column", gap: 4 }}>
              <div style={{ display: "flex", fontSize: 12.5 }}>
                <span style={{ color: "var(--text-dim)" }}>{l}</span>
                <span className="oi-money" style={{ marginLeft: "auto", fontWeight: 600 }}>{brlFull(v)}</span>
              </div>
              <div style={{ height: 7, borderRadius: 99, background: "var(--bg-2)", overflow: "hidden" }}>
                <div style={{ width: (v / maxRes * 100) + "%", height: "100%", background: c, borderRadius: 99 }} />
              </div>
            </div>
          ))}
          <div style={{ height: 1, background: "var(--border-2)", margin: "2px 0" }} />
          <div style={{ display: "flex", alignItems: "baseline" }}>
            <span style={{ fontSize: 13, fontWeight: 600 }}>Resultado</span>
            <span className="oi-money" style={{ marginLeft: "auto", fontSize: 19, fontWeight: 700, color: R.resultadoMes >= 0 ? "var(--ok)" : "var(--danger)" }}>{brlFull(R.resultadoMes)}</span>
          </div>
        </div>
      </div>

      {/* contas */}
      <div className="oi-section" style={{ paddingTop: 0 }}>
        <h3 className="oi-section-h">Contas</h3>
        <div className="oi-list card">
          {MF.FIN_CONTAS.map(c => (
            <div key={c.id} className="oi-list-row" onClick={() => goConta(c.id)}>
              <div style={{ width: 34, height: 34, borderRadius: 9, flex: "0 0 auto", display: "grid", placeItems: "center", background: "var(--bg-2)", color: "var(--text-dim)", border: "1px solid var(--border)" }}>
                {React.createElement(IcF[c.ic] || IcF.dollar, { size: 16 })}
              </div>
              <div style={{ flex: 1, minWidth: 0 }}>
                <div style={{ fontSize: 13, fontWeight: 600 }}>{c.nome}</div>
                <div style={{ fontSize: 11, color: "var(--text-mute)" }}>{c.banco}</div>
              </div>
              <span className="oi-money" style={{ fontSize: 13.5, fontWeight: 700 }}>{brlFull(c.saldo)}</span>
            </div>
          ))}
        </div>
      </div>
      <div style={{ height: 90 }} />
    </>
  );
}

// ─── Detalhe do lançamento ───
function TransacaoDetalheScreen({ nav, ctx, params }) {
  const { all: LANC, liquidar: liquidarId } = useFinLanc();
  const l = LANC.find(x => x.id === params.id) || LANC[0];
  const inn = l.tipo === "receber";
  const meta = finMeta(l);
  const liq = l.status === "liquidado";
  const temOrigem = l.origem && l.origem.match(/^(OS|MAN)/);

  const liquidar = () => liquidarId(l.id);

  return (
    <>
      <DHf nav={nav} title={l.id} eyebrow={(inn ? "A receber" : "A pagar") + " · " + l.categoria} />
      <div className="oi-scroll">
        <div className="oi-section">
          <div className="oi-card pad" style={{ gap: 10 }}>
            <div style={{ display: "flex", alignItems: "center", gap: 10 }}>
              <div style={{ width: 44, height: 44, borderRadius: 12, flex: "0 0 auto", display: "grid", placeItems: "center",
                            background: inn ? "color-mix(in oklch, var(--ok) 16%, transparent)" : "color-mix(in oklch, var(--danger) 14%, transparent)",
                            color: inn ? "var(--ok)" : "var(--danger)" }}>
                {React.createElement(inn ? IcF.arrowDown : IcF.arrowUp, { size: 24 })}
              </div>
              <div style={{ flex: 1, minWidth: 0 }}>
                <div className="oi-money" style={{ fontSize: 26, fontWeight: 700, letterSpacing: "-0.02em", lineHeight: 1, color: inn ? "var(--ok)" : "var(--text)" }}>
                  {inn ? "+" : "−"}{brlFull(l.valor).replace("R$ ", "R$ ")}
                </div>
                <div style={{ marginTop: 5 }}><span className={"oi-status " + meta.tone}><span className="dot" />{meta.label}</span></div>
              </div>
            </div>
            <div style={{ fontSize: 14, fontWeight: 600 }}>{l.desc}</div>
          </div>
        </div>

        <div className="oi-section" style={{ paddingTop: 0 }}>
          <div className="oi-card pad">
            <dl className="oi-dl">
              <dt>{inn ? "Cliente" : "Favorecido"}</dt><dd>{l.parte}</dd>
              <dt>Categoria</dt><dd>{l.categoria}</dd>
              <dt>Vencimento</dt><dd className="oi-mono" style={{ color: l.status === "vencido" ? "var(--danger)" : "var(--text)" }}>{l.vencLabel}</dd>
              {liq && <><dt>{inn ? "Recebido em" : "Pago em"}</dt><dd className="oi-mono">{l.liqLabel}</dd></>}
              {liq && <><dt>Conta</dt><dd>{contaNome(l.conta)}</dd></>}
              <dt>Forma</dt><dd>{l.meio}</dd>
              <dt>Origem</dt><dd className="oi-mono">{l.origem}</dd>
            </dl>
            {temOrigem && (
              <button className="oi-btn sm" style={{ marginTop: 10 }} onClick={() => nav.push(l.origem.startsWith("MAN") ? "manut-os" : "pedido", { id: l.origem })}>
                <IcF.file size={14} /> Ver {l.origem}
              </button>
            )}
          </div>
        </div>
        <div style={{ height: 100 }} />
      </div>

      <div style={{ padding: 12, background: "var(--surface)", borderTop: "1px solid var(--border)", display: "flex", gap: 8 }}>
        {liq ? (
          <button className="oi-btn block" onClick={() => window.oiToast("Abrindo comprovante…")}><IcF.file size={16} /> Ver comprovante</button>
        ) : (
          <>
            {inn && <button className="oi-btn" style={{ flex: "0 0 auto", padding: "0 14px" }} onClick={() => window.oiToast("Cobrança enviada via WhatsApp", "ok")}><IcF.whatsapp size={16} /></button>}
            <button className={"oi-btn block " + (inn ? "action" : "primary")} style={{ flex: 1 }} onClick={liquidar}>
              <IcF.check size={18} /> {inn ? "Registrar recebimento" : "Registrar pagamento"}
            </button>
          </>
        )}
      </div>
    </>
  );
}

window.Screens = window.Screens || {};
Object.assign(window.Screens, { FinancasScreen, TransacaoDetalheScreen });
