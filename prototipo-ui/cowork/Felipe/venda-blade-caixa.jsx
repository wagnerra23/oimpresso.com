// venda-blade-caixa.jsx — Onda B: os acessórios do balcão que faltavam do legado.
//   cash_register/index + create + register_details + close_register_modal + payment_details
//     ................................................ VendaCaixa (turnos de caixa)
//   sell_return/add.blade.php + partials/product_row .. VendaDevolucaoForm
//   sale_pos/partials/recurring_invoice_modal ......... VendaAssinaturaModal
//   sell/edit + sale_pos/edit (cabeçalho da venda) .... VendaEditarModal
// Expõe window.VendaCaixa, window.VendaDevolucaoForm, window.VendaAssinaturaModal, window.VendaEditarModal.
(() => {
const { useState } = React;
const DS = () => window.OfficeImpressoPontoWR2DesignSystem_019dd0 || {};
const UI = () => window.PBUI || {};
const D = () => window.VBD || {};
const Ic = ({ name, size = 14 }) => { const F = (window.I || {})[name]; return F ? <F size={size} /> : null; };
const brl = (n) => "R$ " + Number(n || 0).toLocaleString("pt-BR", { minimumFractionDigits: 2, maximumFractionDigits: 2 });

const TURNOS = [
  { id: 1, aberto: "22/08/2026 08:02", fechado: null, quem: "Larissa Prado", loc: "Matriz", inicial: 300, vendas: 4586.9, dinheiro: 986.9, cheque: 0, cartao: 2210, transf: 1390, outros: 0, devolucoes: 120, despesas: 85, status: "aberto" },
  { id: 2, aberto: "21/08/2026 08:10", fechado: "21/08/2026 18:41", quem: "Larissa Prado", loc: "Matriz", inicial: 300, vendas: 7120.4, dinheiro: 1240, cheque: 0, cartao: 3980.4, transf: 1900, outros: 0, devolucoes: 0, despesas: 140, status: "fechado", nota: "Conferido com Wagner. Sangria de R$ 1.000 pro cofre." },
  { id: 3, aberto: "20/08/2026 08:00", fechado: "20/08/2026 18:12", quem: "Marcos Vinícius", loc: "Filial Centro", inicial: 200, vendas: 3210, dinheiro: 610, cheque: 320, cartao: 1580, transf: 700, outros: 0, devolucoes: 210, despesas: 0, status: "fechado", nota: "" },
];
const NOTAS = [200, 100, 50, 20, 10, 5, 2];
const MOEDAS = [1, 0.5, 0.25, 0.1, 0.05];

// ─────────── Caixa do dia (espelho do vivo `Sells/Caixa/Index.tsx`) ───────────
// Conferência por forma de pagamento (com prazo de compensação) e por origem
// (balão · oficina · online, ADR 0192), com as OS que geraram venda no dia.
// Os dois cortes descrevem o MESMO conjunto de vendas do dia: no vivo eles chegam do
// mesmo `SellController@inertiaCaixa`, então contagem e total precisam fechar nos dois.
const FORMAS_DIA = [
  { key: "cash", label: "Dinheiro", clearing: "imediato", count: 3, total: 986.9 },
  { key: "pix", label: "Pix", clearing: "imediato", count: 4, total: 1390 },
  { key: "card", label: "Cartão de crédito", clearing: "30 dias", count: 5, total: 2210 },
  { key: "cheque", label: "Cheque", clearing: "compensação", count: 1, total: 320 },
  { key: "boleto", label: "Boleto", clearing: "no vencimento", count: 2, total: 1875.4 },
];
const ORIGENS_DIA = [
  { source: "balcao", label: "Balcão", count: 9, total: 3562.9, refs: [] },
  { source: "oficina", label: "Oficina", count: 4, total: 2210, refs: [{ id: 4815, os_ref: "OS-1184" }, { id: 4820, os_ref: "OS-1188" }] },
  { source: "online", label: "Online", count: 2, total: 1009.4, refs: [] },
];
// Percentual sem estourar 100%: a maior fatia absorve o resto do arredondamento.
const pctPorOrigem = (total) => {
  const brutos = ORIGENS_DIA.map((g) => ({ s: g.source, p: Math.floor((g.total / total) * 100) }));
  const resto = 100 - brutos.reduce((a, x) => a + x.p, 0);
  let maior = 0;
  ORIGENS_DIA.forEach((g, i) => { if (g.total > ORIGENS_DIA[maior].total) maior = i; });
  brutos[maior].p += resto;
  return Object.fromEntries(brutos.map((x) => [x.s, x.p]));
};

function VendaCaixaDia({ avisar }) {
  const { Widget } = UI();
  const [data, setData] = useState(new Date().toISOString().slice(0, 10));
  const totalDia = FORMAS_DIA.reduce((a, f) => a + f.total, 0);
  const countDia = FORMAS_DIA.reduce((a, f) => a + f.count, 0);
  const pct = pctPorOrigem(totalDia);
  const dinheiro = FORMAS_DIA.find((f) => f.key === "cash").total;
  if (!Widget) return null;
  return (
    <>
      <div className="vc-kpis">
        <div className="vc-kpi hero"><span>Faturado no dia</span><b>{brl(totalDia)}</b><em>{countDia} venda(s)</em></div>
        <div className="vc-kpi"><span>Vendas em dinheiro</span><b>{brl(dinheiro)}</b><em>compensação imediata</em></div>
        <div className="vc-kpi"><span>Caixa</span><b className="ok">aberto</b><em>turno de Larissa Prado</em></div>
        <div className="vc-kpi"><span>Origens hoje</span><b>{ORIGENS_DIA.length}</b><em>balcão · oficina · online</em></div>
      </div>
      <div className="vc-dia">
        <Widget titulo={<><Ic name="cash" size={13} /> Por forma de pagamento</>} nota={data.split("-").reverse().join("/")}>
          <div className="vc-dia-topo">
            <input type="date" className="vc-date" value={data} onChange={(e) => setData(e.target.value)} aria-label="Data do caixa" />
            <button className="os-btn sm" onClick={() => avisar("Z do caixa enviado pra impressora.", "ok")}><Ic name="print" size={12} /> Imprimir Z</button>
          </div>
          <table className="pb-tbl">
            <thead><tr><th>Forma</th><th>Compensação</th><th className="r">Vendas</th><th className="r">Total</th></tr></thead>
            <tbody>{FORMAS_DIA.map((f) => (
              <tr key={f.key}><td><b>{f.label}</b></td><td className="pb-help">{f.clearing}</td><td className="r mono">{f.count}</td><td className="r mono">{brl(f.total)}</td></tr>
            ))}</tbody>
            <tfoot><tr><td colSpan={3}><b>Total bruto</b></td><td className="r mono"><b>{brl(totalDia)}</b></td></tr></tfoot>
          </table>
        </Widget>
        <Widget titulo={<><Ic name="chart" size={13} /> Por origem</>} nota="balão · oficina · online">
          {ORIGENS_DIA.map((g) => {
            const p = pct[g.source];
            return (
              <div key={g.source} className="vc-src">
                <div className="vc-src-h"><b>{g.label}</b><span>{g.count} venda(s)</span><em className="mono">{brl(g.total)}</em></div>
                <div className="vc-src-bar"><i style={{ width: p + "%" }} /></div>
                <div className="vc-src-m">
                  <small>{p}% do faturamento do dia</small>
                  {g.refs.length > 0 &&
                    <small>{g.refs.map((r) => <button key={r.id} className="vc-src-os" onClick={() => avisar("Abrindo " + r.os_ref + ".", "ok")}>↗ #{r.os_ref}</button>)}</small>}
                </div>
              </div>
            );
          })}
        </Widget>
      </div>
    </>
  );
}

function VendaCaixa({ avisar, Grade, Toolbar }) {
  const { Widget, Kebab, Modal, Fld, Sel } = UI();
  const { StatusBadge } = DS();
  const [turnos, setTurnos] = useState(TURNOS);
  const [visao, setVisao] = useState("dia");
  const [busca, setBusca] = useState("");
  const [densa, setDensa] = useState(false);
  const [ver, setVer] = useState(null);
  const [abrir, setAbrir] = useState(null);
  const [fechar, setFechar] = useState(null);
  const [cont, setCont] = useState({});
  const rows = turnos.filter((t) => !busca || (t.quem + " " + t.loc).toLowerCase().includes(busca.toLowerCase()));
  const contado = [...NOTAS, ...MOEDAS].reduce((a, d) => a + (Number(cont[d]) || 0) * d, 0);
  const emCaixa = (t) => t.inicial + t.dinheiro - t.despesas - t.devolucoes;

  const cols = [
    { key: "acao", label: "Ação", width: 96, resizable: false },
    { key: "aberto", label: "Aberto em", width: 148, mono: true, sortable: true },
    { key: "fechado", label: "Fechado em", width: 148, mono: true },
    { key: "quem", label: "Usuário", width: 160 },
    { key: "loc", label: "Local", width: 130 },
    { key: "inicial", label: "Valor inicial", width: 124, align: "right", mono: true },
    { key: "vendas", label: "Total de vendas", width: 146, align: "right", mono: true, sortable: true, sortValue: (r) => r._t.vendas },
    { key: "dinheiro", label: "Dinheiro em caixa", width: 156, align: "right", mono: true },
    { key: "status", label: "Situação", width: 120 },
  ];
  const linhas = rows.map((t) => ({
    id: t.id, _t: t, state: t.status === "aberto" ? "urgent" : undefined,
    acao: Kebab ? <Kebab acoes={[
      { l: "Detalhe do turno", ic: "search", on: () => setVer(t) },
      ...(t.status === "aberto" ? [{ l: "Fechar caixa", ic: "cash", on: () => { setFechar(t); setCont({}); } }] : []),
      { l: "Imprimir conferência", ic: "print", on: () => avisar("Conferência do turno de " + t.quem + " na impressora.", "ok") },
    ]} /> : null,
    aberto: t.aberto, fechado: t.fechado || "—", quem: t.quem, loc: t.loc,
    inicial: brl(t.inicial), vendas: brl(t.vendas), dinheiro: brl(emCaixa(t)),
    status: StatusBadge ? <StatusBadge kind="documento" value={t.status === "aberto" ? "Aberto" : "Fechado"} /> : t.status,
  }));

  if (!Widget) return null;
  const { Alert } = DS();
  return (
    <>
      <nav className="cli-moduletopnav vc-abas" aria-label="Visões do caixa">
        <button className={"cli-moduletopnav-tab " + (visao === "dia" ? "active" : "")} onClick={() => setVisao("dia")}>Caixa do dia</button>
        <button className={"cli-moduletopnav-tab " + (visao === "turnos" ? "active" : "")} onClick={() => setVisao("turnos")}>Turnos</button>
      </nav>
      {visao === "dia" && <VendaCaixaDia avisar={avisar} />}
      {visao === "turnos" && <>
      {Alert && <Alert tone="info" title="Turno de gaveta">A conferência do dia está na aba ao lado (espelho de <span className="mono">/vendas/caixa</span>, vivo). Aqui é o <span className="mono">/cash-register</span>: abrir turno, contar a gaveta e fechar — o pedaço que o vivo ainda deixa no legado.</Alert>}
      <Widget flush titulo={<><Ic name="cash" size={13} /> Caixa registradora</>} nota={rows.length + " turno(s)"}>
        <Toolbar busca={busca} setBusca={setBusca} ph="Buscar por usuário ou local…" densa={densa} setDensa={setDensa}>
          <button className="os-btn sm primary" disabled={turnos.some((t) => t.status === "aberto")} onClick={() => setAbrir({ inicial: "", loc: "Matriz", nota: "" })}>
            <Ic name="plus" size={12} /> Abrir caixa
          </button>
        </Toolbar>
        <Grade columns={cols} rows={linhas} densa={densa} altura={300} />
        <div className="pb-pag"><span className="pb-help">Um turno aberto por usuário e local — o legado só deixa abrir outro depois de fechar o atual.</span></div>
      </Widget>
      </>}

      {abrir && Modal &&
        <Modal titulo="Abrir caixa" onClose={() => setAbrir(null)} largura={560}
          acoes={<>
            <button className="os-btn" onClick={() => setAbrir(null)}>Cancelar</button>
            <button className="os-btn primary" onClick={() => {
              setTurnos((ts) => [{ id: Date.now(), aberto: new Date().toLocaleString("pt-BR").replace(",", ""), fechado: null, quem: "Larissa Prado", loc: abrir.loc, inicial: Number(abrir.inicial) || 0, vendas: 0, dinheiro: 0, cheque: 0, cartao: 0, transf: 0, outros: 0, devolucoes: 0, despesas: 0, status: "aberto" }, ...ts]);
              avisar("Caixa aberto com " + brl(Number(abrir.inicial) || 0) + ".", "ok"); setAbrir(null);
            }}>Abrir caixa</button>
          </>}>
          <div className="pb-grid c2">
            <Fld label="Valor inicial" req><input className="num" value={abrir.inicial} onChange={(e) => setAbrir({ ...abrir, inicial: e.target.value })} placeholder="0,00" /></Fld>
            <Fld label="Local do negócio" req><Sel value={abrir.loc} onChange={(v) => setAbrir({ ...abrir, loc: v })} options={(D().LOCAIS || []).map((l) => l.name)} /></Fld>
            <Fld label="Observação de abertura" span={2}><input value={abrir.nota} onChange={(e) => setAbrir({ ...abrir, nota: e.target.value })} placeholder="Ex.: troco conferido com o cofre" /></Fld>
          </div>
        </Modal>}

      {ver && Modal &&
        <Modal titulo={"Turno de " + ver.quem} onClose={() => setVer(null)} largura={720}
          acoes={<button className="os-btn" onClick={() => setVer(null)}>Fechar</button>}>
          <div className="vc-detalhe">
            <div><span>Usuário</span><b>{ver.quem}</b></div>
            <div><span>Local</span><b>{ver.loc}</b></div>
            <div><span>Aberto em</span><b className="mono">{ver.aberto}</b></div>
            <div><span>Fechado em</span><b className="mono">{ver.fechado || "—"}</b></div>
          </div>
          <table className="pb-tbl" style={{ marginTop: 12 }}>
            <tbody>
              <tr><td>Valor inicial</td><td className="r mono">{brl(ver.inicial)}</td></tr>
              <tr><td>Pagamento em dinheiro</td><td className="r mono">{brl(ver.dinheiro)}</td></tr>
              <tr><td>Pagamento em cheque</td><td className="r mono">{brl(ver.cheque)}</td></tr>
              <tr><td>Pagamento em cartão</td><td className="r mono">{brl(ver.cartao)}</td></tr>
              <tr><td>Transferência bancária</td><td className="r mono">{brl(ver.transf)}</td></tr>
              <tr><td>Outros pagamentos</td><td className="r mono">{brl(ver.outros)}</td></tr>
              <tr><td>Devoluções</td><td className="r mono">− {brl(ver.devolucoes)}</td></tr>
              <tr><td>Despesas</td><td className="r mono">− {brl(ver.despesas)}</td></tr>
              <tr><td><b>Total de vendas</b></td><td className="r mono"><b>{brl(ver.vendas)}</b></td></tr>
              <tr><td><b>Dinheiro em caixa</b></td><td className="r mono"><b>{brl(emCaixa(ver))}</b></td></tr>
            </tbody>
          </table>
          {ver.nota && <p className="pb-help" style={{ marginTop: 10 }}><b>Observação de fechamento:</b> {ver.nota}</p>}
        </Modal>}

      {fechar && Modal &&
        <Modal titulo="Fechar caixa" onClose={() => setFechar(null)} largura={720}
          acoes={<>
            <button className="os-btn" onClick={() => setFechar(null)}>Cancelar</button>
            <button className="os-btn primary" onClick={() => {
              const dif = contado - emCaixa(fechar);
              setTurnos((ts) => ts.map((t) => t.id === fechar.id ? { ...t, status: "fechado", fechado: new Date().toLocaleString("pt-BR").replace(",", ""), nota: fechar.nota } : t));
              avisar(Math.abs(dif) < 0.01 ? "Caixa fechado — bateu certinho." : "Caixa fechado com diferença de " + brl(Math.abs(dif)) + (dif > 0 ? " a mais." : " a menos."), Math.abs(dif) < 0.01 ? "ok" : "warn");
              setFechar(null);
            }}>Fechar caixa</button>
          </>}>
          <div className="vc-denom">
            <div>
              <span className="pb-help">Notas</span>
              {NOTAS.map((d) => (
                <label key={d} className="vc-den"><b className="mono">{brl(d)}</b>
                  <input className="num" value={cont[d] || ""} onChange={(e) => setCont({ ...cont, [d]: e.target.value })} placeholder="0" />
                  <em className="mono">{brl((Number(cont[d]) || 0) * d)}</em>
                </label>
              ))}
            </div>
            <div>
              <span className="pb-help">Moedas</span>
              {MOEDAS.map((d) => (
                <label key={d} className="vc-den"><b className="mono">{brl(d)}</b>
                  <input className="num" value={cont[d] || ""} onChange={(e) => setCont({ ...cont, [d]: e.target.value })} placeholder="0" />
                  <em className="mono">{brl((Number(cont[d]) || 0) * d)}</em>
                </label>
              ))}
            </div>
          </div>
          <div className="vc-fecha">
            <div><span>Esperado em caixa</span><b className="mono">{brl(emCaixa(fechar))}</b></div>
            <div><span>Contado</span><b className="mono">{brl(contado)}</b></div>
            <div className={Math.abs(contado - emCaixa(fechar)) < 0.01 ? "" : "warn"}><span>Diferença</span><b className="mono">{brl(contado - emCaixa(fechar))}</b></div>
          </div>
          <Fld label="Observação de fechamento" span={2}>
            <textarea value={fechar.nota || ""} onChange={(e) => setFechar({ ...fechar, nota: e.target.value })} placeholder="Sangria, quebra de caixa, quem conferiu…" />
          </Fld>
        </Modal>}
    </>
  );
}

// ─────────── Devolução de venda (sell_return/add.blade.php) ───────────
function VendaDevolucaoForm({ venda, onSair, avisar }) {
  const { Widget, Fld } = UI();
  const { Alert } = DS();
  const linhas = (window.VENDA_LINHAS || (() => []))(venda || {});
  const [dev, setDev] = useState({});
  const [nota, setNota] = useState("");
  const total = linhas.reduce((a, l, i) => a + (Number(dev[i]) || 0) * l.preco, 0);
  if (!Widget || !venda) return null;
  return (
    <>
      {Alert && <Alert tone="warn" title="Devolução de venda">A devolução volta o item pro estoque e gera crédito pro cliente. O que já foi produzido sob medida costuma não voltar — confira antes de confirmar.</Alert>}
      <Widget titulo={<><Ic name="list" size={13} /> Devolver {venda.inv}</>} nota={venda.cli}>
        <table className="pb-tbl">
          <thead><tr><th>Produto</th><th className="r">Vendido</th><th className="r">Preço unit.</th><th className="r">Devolver</th><th className="r">Subtotal</th></tr></thead>
          <tbody>
            {linhas.map((l, i) => (
              <tr key={i}>
                <td><b>{l.nome}</b><div className="pb-help mono">{l.sku}</div></td>
                <td className="r mono">{l.qtd} {l.un}</td>
                <td className="r mono">{brl(l.preco)}</td>
                <td className="r"><input className="vp-qtd" value={dev[i] || ""} max={l.qtd} onChange={(e) => setDev({ ...dev, [i]: Math.min(l.qtd, Number(e.target.value) || 0) })} placeholder="0" /></td>
                <td className="r mono">{brl((Number(dev[i]) || 0) * l.preco)}</td>
              </tr>
            ))}
          </tbody>
        </table>
        <div className="pb-grid c2" style={{ marginTop: 12 }}>
          <Fld label="Motivo da devolução" span={2}><input value={nota} onChange={(e) => setNota(e.target.value)} placeholder="Ex.: cor fora do pedido, item danificado no transporte" /></Fld>
        </div>
        <div className="pb-pag" style={{ marginTop: 8 }}>
          <span>Total devolvido: <b className="mono">{brl(total)}</b></span>
          <div className="sp" />
          <button className="os-btn sm" onClick={onSair}>Cancelar</button>
          <button className="os-btn sm primary" disabled={!total} onClick={() => { avisar("Devolução de " + brl(total) + " registrada em " + venda.inv + ".", "ok"); onSair?.(); }}>Salvar devolução</button>
        </div>
      </Widget>
    </>
  );
}

// ─────────── Nova assinatura (recurring_invoice_modal) ───────────
function VendaAssinaturaModal({ aberto, onClose, avisar }) {
  const { Modal, Fld, Sel } = UI();
  const [f, setF] = useState({ intervalo: "months", n: 1, repet: 12, inicio: "", cli: "" });
  if (!aberto || !Modal) return null;
  return (
    <Modal titulo="Nova assinatura" onClose={onClose} largura={620}
      acoes={<>
        <button className="os-btn" onClick={onClose}>Cancelar</button>
        <button className="os-btn primary" onClick={() => { avisar("Assinatura criada — a primeira fatura sai em " + (f.inicio || "—") + ".", "ok"); onClose(); }}>Criar assinatura</button>
      </>}>
      <div className="pb-grid c2">
        <Fld label="Cliente" req span={2}><Sel value={f.cli} onChange={(v) => setF({ ...f, cli: v })} options={D().CLIENTES || []} vazio="Selecione" /></Fld>
        <Fld label="Repetir a cada" req><input className="num" value={f.n} onChange={(e) => setF({ ...f, n: e.target.value })} /></Fld>
        <Fld label="Intervalo" req><Sel value={f.intervalo} onChange={(v) => setF({ ...f, intervalo: v })} options={D().INTERVALOS || []} /></Fld>
        <Fld label="Nº de repetições" dica="Vazio = repete até você encerrar."><input className="num" value={f.repet} onChange={(e) => setF({ ...f, repet: e.target.value })} /></Fld>
        <Fld label="Primeira fatura em" req><input value={f.inicio} onChange={(e) => setF({ ...f, inicio: e.target.value })} placeholder="dd/mm/aaaa" /></Fld>
      </div>
    </Modal>
  );
}

// ─────────── Editar venda (`Sells/Edit.tsx` vivo) ───────────
// Onda A3: além do cabeçalho, as LINHAS da venda — quantidade, preço e desconto por item,
// com o total recalculando. Venda finalizada (paga ou faturada) não edita: o vivo manda
// estornar/cancelar, e a tela diz isso em vez de deixar salvar.
function VendaEditarModal({ venda, onClose, avisar }) {
  const { Modal, Fld, Sel } = UI();
  const { Alert } = DS();
  const [f, setF] = useState(venda || {});
  const [linhas, setLinhas] = useState([]);
  React.useEffect(() => {
    setF(venda || {});
    // Parte do SUBTOTAL guardado (que fecha com o total da venda), não do preço
    // unitário arredondado — senão o “novo total” nasce alguns centavos fora.
    setLinhas(venda ? (window.VENDA_LINHAS || (() => []))(venda).map((l, i) => ({ ...l, id: i, desc: 0, tocada: false })) : []);
  }, [venda]);
  if (!venda || !Modal) return null;
  const travada = venda.pg === "paid" || venda.fiscal_status === "autorizada";
  const set = (id, k, v) => setLinhas((ls) => ls.map((l) => l.id === id ? { ...l, [k]: Number(String(v).replace(",", ".")) || 0, tocada: true } : l));
  const linhaTotal = (l) => Math.max(0, (l.tocada ? l.qtd * l.preco : l.subtotal) - (l.desc || 0));
  const total = linhas.reduce((a, l) => a + linhaTotal(l), 0);
  return (
    <Modal titulo={"Editar venda · " + venda.inv} onClose={onClose} largura={860}
      acoes={<>
        <button className="os-btn" onClick={onClose}>Cancelar</button>
        <button className="os-btn primary" disabled={travada} onClick={() => { avisar(venda.inv + " atualizada — novo total " + brl(total) + ".", "ok"); onClose(); }}>Salvar venda</button>
      </>}>
      {travada && Alert &&
        <Alert tone="warn" title="Venda finalizada não se edita">Esta venda está {venda.pg === "paid" ? "quitada" : "faturada"}. O caminho é estorno ou cancelamento — alterar itens aqui bagunçaria estoque e fiscal.</Alert>}
      <div className="pb-grid c3">
        <Fld label="Data da venda" req><input value={f.data || ""} onChange={(e) => setF({ ...f, data: e.target.value })} disabled={travada} /></Fld>
        <Fld label="Cliente" req><Sel value={f.cli} onChange={(v) => setF({ ...f, cli: v })} options={D().CLIENTES || []} /></Fld>
        <Fld label="Local do negócio" req><Sel value={f.loc} onChange={(v) => setF({ ...f, loc: v })} options={(D().LOCAIS || []).map((l) => l.name)} /></Fld>
        <Fld label="Vendedor"><Sel value={f.quem} onChange={(v) => setF({ ...f, quem: v })} options={D().VENDEDORES || []} /></Fld>
        <Fld label="Tipo de serviço"><Sel value={f.serv} onChange={(v) => setF({ ...f, serv: v })} options={D().SERVICOS || []} /></Fld>
        <Fld label="Forma de pagamento"><Sel value={f.forma} onChange={(v) => setF({ ...f, forma: v })} options={D().FORMAS || []} /></Fld>
      </div>
      <table className="pb-tbl" style={{ marginTop: 12 }}>
        <thead><tr><th>Produto</th><th className="r">Qtd</th><th className="r">Preço unit.</th><th className="r">Desconto</th><th className="r">Subtotal</th><th /></tr></thead>
        <tbody>
          {linhas.map((l) => (
            <tr key={l.id}>
              <td><b>{l.nome}</b><div className="pb-help mono">{l.sku} · {l.un}</div></td>
              <td className="r"><input className="vp-qtd" value={l.qtd} disabled={travada} onChange={(e) => set(l.id, "qtd", e.target.value)} /></td>
              <td className="r"><input className="vp-qtd larga" value={l.preco} disabled={travada} onChange={(e) => set(l.id, "preco", e.target.value)} /></td>
              <td className="r"><input className="vp-qtd" value={l.desc || 0} disabled={travada} onChange={(e) => set(l.id, "desc", e.target.value)} /></td>
              <td className="r mono">{brl(linhaTotal(l))}</td>
              <td className="r"><button className="icon-btn" disabled={travada} aria-label="Remover item" onClick={() => setLinhas((ls) => ls.filter((x) => x.id !== l.id))}>✕</button></td>
            </tr>
          ))}
        </tbody>
        <tfoot><tr><td colSpan={4}><b>Novo total</b></td><td className="r mono"><b>{brl(total)}</b></td><td /></tr></tfoot>
      </table>
      <div className="pb-grid c2" style={{ marginTop: 12 }}>
        <Fld label="Observação da venda" span={2}><textarea value={f.obs || ""} disabled={travada} onChange={(e) => setF({ ...f, obs: e.target.value })} /></Fld>
      </div>
      <p className="pb-help" style={{ marginTop: 8 }}>Mudar item recalcula estoque na hora de salvar. Pagamento se ajusta pelo botão “Adicionar pagamento”, não aqui.</p>
    </Modal>
  );
}

Object.assign(window, { VendaCaixa, VendaCaixaDia, VendaDevolucaoForm, VendaAssinaturaModal, VendaEditarModal });
})();
