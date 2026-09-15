// comissoes-page.jsx — Apuração de comissão (tela nova: o legado não tem cálculo nenhum).
// Período → base de cálculo por agente → extrato das vendas → fechamento → título a pagar no Financeiro.
// Aprovado por [W] 2026-08-19 ("comissionamento vai ser necessário"). Expõe window.ComissoesPage.
(() => {
const { useState, useMemo } = React;
const { Kpis, Kpi, Chk, Nota, Meta, Bulk } = window.AcessosDS;

const brl = (n) => "R$ " + n.toLocaleString("pt-BR", { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const brl0 = (n) => "R$ " + n.toLocaleString("pt-BR", { maximumFractionDigits: 0 });
const pct = (n) => String(n).replace(".", ",") + "%";
function initials(n){ return n.split(/\s+/).slice(0,2).map(w=>w[0]).join("").toUpperCase(); }
function avColor(n){ const h=[...n].reduce((a,c)=>a+c.charCodeAt(0),0)%360; return { bg:`oklch(0.92 0.04 ${h})`, fg:`oklch(0.42 0.13 ${h})` }; }

// Vendas do período por agente — base do extrato (mock com a forma que o backend precisa entregar)
const VENDAS = {
  1: [
    { num:"VD-4821", data:"03/08", cliente:"Prefeitura de Contagem", total:12800, custo:7900, pago:true },
    { num:"VD-4833", data:"07/08", cliente:"Padaria Pão Nosso",      total:2400,  custo:1350, pago:true },
    { num:"VD-4851", data:"11/08", cliente:"Auto Center Régis",      total:6900,  custo:4100, pago:true },
    { num:"VD-4872", data:"14/08", cliente:"Colégio Santa Clara",    total:18300, custo:10800, pago:true },
    { num:"VD-4890", data:"18/08", cliente:"Mercado Vila Rica",      total:7800,  custo:4600, pago:false },
  ],
  2: [
    { num:"VD-4818", data:"02/08", cliente:"Construtora Lagoa",      total:31200, custo:19400, pago:true },
    { num:"VD-4840", data:"08/08", cliente:"Rede Farmais",           total:22100, custo:12700, pago:true },
    { num:"VD-4869", data:"13/08", cliente:"Hotel Serra Verde",      total:18150, custo:11900, pago:false },
  ],
  3: [
    { num:"VD-4826", data:"05/08", cliente:"Oficina Martinho",       total:15400, custo:9100, pago:true },
    { num:"VD-4859", data:"12/08", cliente:"Transportes Aliança",    total:24500, custo:15200, pago:true },
  ],
  4: [
    { num:"VD-4844", data:"09/08", cliente:"Buffet Encanto",         total:9800,  custo:5900, pago:true },
    { num:"VD-4881", data:"16/08", cliente:"Studio Pilates Norte",   total:8500,  custo:5100, pago:false },
  ],
  5: [
    { num:"VD-4835", data:"07/08", cliente:"Rede Bomtempo (indicação)", total:26700, custo:17300, pago:true },
  ],
};

const AGENTES = [
  { id:1, nome:"Larissa Souza",  regra:"fixa",   pct:2.5, pct2:null, meta:45000, pago:false },
  { id:2, nome:"Bruno Carvalho", regra:"faixa",  pct:3,   pct2:4,    meta:60000, pago:false },
  { id:3, nome:"Patrícia Gomes", regra:"fixa",   pct:3.5, pct2:null, meta:50000, pago:true, pagoEm:"05/08" },
  { id:4, nome:"Joana Lima",     regra:"fixa",   pct:2,   pct2:null, meta:25000, pago:false },
  { id:5, nome:"Ricardo Neves",  regra:"margem", pct:5,   pct2:null, meta:20000, pago:true, pagoEm:"08/08" },
];

const REGRA_L = { fixa:"% fixo sobre o faturado", faixa:"% por faixa de meta", margem:"% sobre a margem" };
const PERIODOS = [{ id:"mes", label:"Este mês" }, { id:"anterior", label:"Mês anterior" }, { id:"tri", label:"Trimestre" }];

// Apuração — a regra que o backend precisa implementar (D6 do trio)
function apurar(a, vendas, soFaturadas) {
  const linhas = vendas.filter((v) => !soFaturadas || v.pago).map((v) => {
    const margem = v.total - v.custo;
    let base, aplicado;
    if (a.regra === "margem") { base = margem; aplicado = a.pct; }
    else { base = v.total; aplicado = a.pct; }
    return { ...v, margem, base, aplicado, comissao: base * aplicado / 100 };
  });
  let comissao = linhas.reduce((s, l) => s + l.comissao, 0);
  const base = linhas.reduce((s, l) => s + l.base, 0);
  const faturado = linhas.reduce((s, l) => s + l.total, 0);
  // faixa: o que passa da meta recebe o segundo %
  if (a.regra === "faixa" && faturado > a.meta) {
    const excedente = faturado - a.meta;
    comissao = (a.meta * a.pct / 100) + (excedente * a.pct2 / 100);
  }
  return { linhas, base, faturado, comissao, excedente: Math.max(faturado - a.meta, 0) };
}

function ExtratoDrawer({ agente, ap, soFaturadas, onClose }) {
  const c = avColor(agente.nome);
  return (
    <>
      <div className="os-drawer-back" onClick={onClose}></div>
      <div className="os-drawer cms-drawer wide">
        <div className="os-drawer-head">
          <div className="os-drawer-head-l">
            <div className="os-drawer-id">Extrato de comissão</div>
            <h2>{agente.nome}</h2>
            <p>{REGRA_L[agente.regra]} · {pct(agente.pct)}{agente.regra === "faixa" ? ` até a meta, ${pct(agente.pct2)} acima` : ""}</p>
          </div>
          <div className="os-drawer-head-r">
            <div className="usr-avatar" style={{ background:c.bg, color:c.fg }}>{initials(agente.nome)}</div>
            <button className="os-btn ghost" onClick={onClose}>Fechar</button>
          </div>
        </div>

        <div className="os-drawer-body">
          <div className="os-drawer-section">
            <h3>Como este número foi feito</h3>
            <div className="cmi-conta">
              <div><small>Vendas no período</small><b className="mono">{ap.linhas.length}</b></div>
              <div><small>Faturado</small><b className="mono">{brl0(ap.faturado)}</b></div>
              <div><small>Base de cálculo</small><b className="mono">{brl0(ap.base)}</b></div>
              <div><small>Comissão</small><b className="mono pos">{brl(ap.comissao)}</b></div>
            </div>
            <p className="cmi-formula">
              {agente.regra === "margem" && <>Base = faturado − custo (margem). Comissão = base × {pct(agente.pct)}.</>}
              {agente.regra === "fixa" && <>Base = faturado. Comissão = base × {pct(agente.pct)}.</>}
              {agente.regra === "faixa" && <>
                Até a meta ({brl0(agente.meta)}): {pct(agente.pct)}. Excedente ({brl0(ap.excedente)}): {pct(agente.pct2)}.
              </>}
              {soFaturadas ? " Só vendas faturadas entram." : " Vendas não faturadas incluídas — o valor pode cair na baixa."}
            </p>
          </div>

          <div className="os-drawer-section">
            <h3>Vendas que entraram</h3>
            <table className="os-table cmi-table">
              <thead><tr>
                <th>Venda</th><th>Cliente</th><th className="cms-th-num">Total</th>
                {agente.regra === "margem" && <th className="cms-th-num">Margem</th>}
                <th className="cms-th-num">Base</th><th className="cms-th-num">%</th><th className="cms-th-num">Comissão</th>
              </tr></thead>
              <tbody>
                {ap.linhas.map((l) => (
                  <tr key={l.num}>
                    <td><span className="cms-num dim">{l.num}</span><small className="cmi-data">{l.data}</small></td>
                    <td>{l.cliente}{!l.pago && <span className="cmi-aberto">a receber</span>}</td>
                    <td className="cms-td-num"><span className="cms-num">{brl0(l.total)}</span></td>
                    {agente.regra === "margem" && <td className="cms-td-num"><span className="cms-num dim">{brl0(l.margem)}</span></td>}
                    <td className="cms-td-num"><span className="cms-num">{brl0(l.base)}</span></td>
                    <td className="cms-td-num"><span className="cms-num dim">{pct(l.aplicado)}</span></td>
                    <td className="cms-td-num"><span className="cms-num pos">{brl(l.comissao)}</span></td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>

          <div className="os-drawer-section">
            <h3>Pagamento</h3>
            {agente.pago ? (
              <div className="cmi-pago">Pago em {agente.pagoEm} · título no Financeiro (contas a pagar).</div>
            ) : (
              <div className="cmi-lanc">
                <p>Lançar gera <b>um título a pagar</b> no Financeiro com {agente.nome} como favorecido, valor {brl(ap.comissao)}, competência do período. Não altera as vendas.</p>
                <button className="os-btn primary" onClick={onClose}>Lançar pagamento de {brl(ap.comissao)}</button>
              </div>
            )}
          </div>
        </div>

        <div className="os-drawer-actions">
          <button className="os-btn ghost" onClick={onClose}>Fechar</button>
          <button className="os-btn">Exportar extrato</button>
        </div>
      </div>
    </>
  );
}

function ComissoesPage() {
  const [periodo, setPeriodo] = useState("mes");
  const [soFaturadas, setSoFaturadas] = useState(true);
  const [sel, setSel] = useState([]);
  const [drawer, setDrawer] = useState(null);
  const [fechado, setFechado] = useState(false);

  const linhas = useMemo(() => AGENTES.map((a) => ({ a, ap: apurar(a, VENDAS[a.id] || [], soFaturadas) })), [soFaturadas]);
  const tot = {
    base: linhas.reduce((s, l) => s + l.ap.base, 0),
    comissao: linhas.reduce((s, l) => s + l.ap.comissao, 0),
    pago: linhas.filter((l) => l.a.pago).reduce((s, l) => s + l.ap.comissao, 0),
    aPagar: linhas.filter((l) => !l.a.pago).reduce((s, l) => s + l.ap.comissao, 0),
  };
  const abertos = linhas.filter((l) => !l.a.pago);
  const toggleSel = (id) => setSel((s) => s.includes(id) ? s.filter((x) => x !== id) : [...s, id]);
  const selTotal = linhas.filter((l) => sel.includes(l.a.id)).reduce((s, l) => s + l.ap.comissao, 0);

  return (
    <div className="os-page usr-page cms-page" data-screen-label="Usuários · Apuração de comissão">
      <header className="os-page-h">
        <div className="os-page-h-l">
          <h1>Apuração de comissão</h1>
          <p>{PERIODOS.find((p) => p.id === periodo).label.toLowerCase()} · {abertos.length} {abertos.length === 1 ? "agente em aberto" : "agentes em aberto"}{fechado ? " · período fechado" : ""}</p>
        </div>
        <div className="os-page-h-r">
          <button className="os-btn ghost" onClick={() => window.__selectRoute?.("comissionados")}>Comissionados</button>
          <button className="os-btn" onClick={() => window.__selectRoute?.("financeiro")}>Ver no financeiro</button>
          <button className={`os-btn ${fechado ? "" : "primary"}`} onClick={() => setFechado(!fechado)}>
            {fechado ? "Reabrir período" : "Fechar período"}
          </button>
        </div>
      </header>

      <Kpis>
        <Kpi v={brl0(tot.base)} l="Base de cálculo" />
        <Kpi v={brl0(tot.comissao)} l="Comissão apurada" tone="info" />
        <Kpi v={brl0(tot.pago)} l="Já pago" tone="success" />
        <Kpi v={brl0(tot.aPagar)} l="A pagar" tone="warning" />
      </Kpis>

      <div className="usr-toolbar">
        <div className="usr-filters">
          {PERIODOS.map((p) => (
            <button key={p.id} className={`os-btn sm ${periodo === p.id ? "primary" : ""}`} onClick={() => setPeriodo(p.id)}>{p.label}</button>
          ))}
          <button className={`usr-clear ${soFaturadas ? "on" : ""}`} onClick={() => setSoFaturadas(!soFaturadas)}>
            {soFaturadas ? "Só vendas faturadas" : "Incluindo a receber"}
          </button>
        </div>
      </div>

      <div className="cmi-aviso">
        <Nota tone="warn" title="Nada disso existe no backend hoje">
          O legado guarda só <code>cmmsn_percent</code> no cadastro. Para virar produção precisa de <b>agente na venda</b>,
          <b> regra por agente</b> (fixa/faixa/margem), <b>fechamento por período</b> e o <b>título a pagar</b>. Decisão D6 do trio.
        </Nota>
      </div>

      <div className="os-table-wrap">
        <table className="os-table cms-table">
          <thead><tr>
            <th className="cmi-th-chk"></th>
            <th>Comissionado</th><th>Regra</th><th className="cms-th-num">Faturado</th>
            <th>Meta</th><th className="cms-th-num">Comissão</th><th>Situação</th><th className="usr-th-act"></th>
          </tr></thead>
          <tbody>
            {linhas.map(({ a, ap }) => {
              const c = avColor(a.nome);
              const pctMeta = a.meta ? Math.round(ap.faturado / a.meta * 100) : 0;
              return (
                <tr key={a.id} className={sel.includes(a.id) ? "cmi-sel" : ""}>
                  <td className="cmi-td-chk">
                    {!a.pago && !fechado && (
                      <Chk on={sel.includes(a.id)} onToggle={() => toggleSel(a.id)} label={"Selecionar " + a.nome} />
                    )}
                  </td>
                  <td>
                    <div className="usr-id">
                      <div className="usr-avatar" style={{ background:c.bg, color:c.fg }}>{initials(a.nome)}</div>
                      <div className="usr-id-meta">
                        <b>{a.nome}</b>
                        <small>{ap.linhas.length} {ap.linhas.length === 1 ? "venda" : "vendas"} · base {brl0(ap.base)}</small>
                      </div>
                    </div>
                  </td>
                  <td className="cmi-td-regra">
                    <span className="cms-pct">{pct(a.pct)}{a.regra === "faixa" ? " / " + pct(a.pct2) : ""}</span>
                    <small className="cmi-regra" title={REGRA_L[a.regra]}>{REGRA_L[a.regra]}</small>
                  </td>
                  <td className="cms-td-num"><span className="cms-num">{brl0(ap.faturado)}</span></td>
                  <td>
                    <Meta pct={pctMeta} />
                  </td>
                  <td className="cms-td-num"><span className={`cms-num ${a.pago ? "dim" : "pos"}`}>{brl(ap.comissao)}</span></td>
                  <td>
                    <span className={`cms-pay ${a.pago ? "pago" : "aberto"}`}>
                      <span className="cms-pay-dot"></span>{a.pago ? "Pago em " + a.pagoEm : "Em aberto"}
                    </span>
                  </td>
                  <td className="usr-td-act">
                    <button className="os-btn sm" onClick={() => setDrawer({ a, ap })}>Extrato</button>
                  </td>
                </tr>
              );
            })}
          </tbody>
          <tfoot><tr>
            <td></td>
            <td><span className="cms-total-l">Total do período</span></td>
            <td></td>
            <td className="cms-td-num"><span className="cms-num">{brl0(linhas.reduce((s, l) => s + l.ap.faturado, 0))}</span></td>
            <td></td>
            <td className="cms-td-num"><span className="cms-num pos">{brl(tot.comissao)}</span></td>
            <td colSpan="2"><span className="cms-total-l">{brl(tot.aPagar)} a pagar</span></td>
          </tr></tfoot>
        </table>
      </div>

      <p className="cms-note">
        Fechar o período trava a apuração: novas vendas com data dentro dele passam a contar no período seguinte.
        O pagamento vira título a pagar no Financeiro — nunca baixa direto no caixa.
      </p>

      {sel.length > 0 && (
        <Bulk count={sel.length} label={`· ${brl(selTotal)}`} onClose={() => setSel([])}
          actions={[{ label: `Lançar ${sel.length === 1 ? "pagamento" : sel.length + " pagamentos"}`, onClick: () => setSel([]) }]} />
      )}

      {drawer && <ExtratoDrawer agente={drawer.a} ap={drawer.ap} soFaturadas={soFaturadas} onClose={() => setDrawer(null)} />}
    </div>
  );
}

window.ComissoesPage = ComissoesPage;
})();
