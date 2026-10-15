// crm-portal.jsx — ONDA 2: o portal do contato do módulo Crm (grupo `prefix('contact')` do
// Routes/web.php, que o primeiro import deixou de fora). Persona diferente: é o CLIENTE logado,
// não o operador. Tradução 1:1 dos blades:
//   dashboard/index.blade.php ........... "painel" (info-boxes de compra/venda/saldo)
//   profile/edit.blade.php .............. "perfil" (dados + troca de senha)
//   purchase/index.blade.php ............ "compras"
//   sell/index.blade.php ................ "vendas"
//   ledger/index.blade.php .............. "extrato" (+ PDF)
//   booking/index + booking/create ...... "agendamentos"
//   order_request/{index,all_list,create,product_row} .. "pedidos" + "novo pedido"
// Casca PRÓPRIA do contato (D6 [W] 2026-10-15): sem a sidebar do ERP, mobile-first (alvos ≥ 44px,
// legível em 390px — Portal.charter.md UX Targets). Rotas crm-portal-<tela> (app.jsx) abrem cada tela.
// A tira .cp-preview (contato simulado, celular/tela cheia, voltar) é só do protótipo — não exportar.
// Só componentes do DS (Logo · Avatar · KpiCard · StatusBadge · Button · Input · Select · Textarea · Alert · EmptyState).
// Expõe window.CrmPortalPage.
(() => {
const { useState, useMemo } = React;
const MP = () => window.ModuloPadrao || {};
const DS = () => window.OfficeImpressoPontoWR2DesignSystem_019dd0 || {};
const UI = () => window.PBUI || {};
const D = () => window.CBD || {};
const U = () => window.CBUI || {};
const Ic = ({ name, size = 14, rotulo = null }) => {
  const F = (window.I || {})[name];
  if (!F) return null;
  return rotulo
    ? <span role="img" aria-label={rotulo} style={{ display: "inline-flex" }}><F size={size} /></span>
    : <span aria-hidden="true" style={{ display: "inline-flex" }}><F size={size} /></span>;
};

const CONTATOS = [
  { id: 1, nome: "Rota Livre Transportes", user: "rotalivre", pessoa: "Daniela Prado", email: "daniela@rotalivre.com.br", tel: "(14) 99812-4410", tipo: "both", doc: "17.221.884/0001-02", end: "Rod. Mal. Rondon, km 302 — Jaú/SP",
    totalVenda: 48210, vendaPaga: 41890, totalCompra: 6400, compraPaga: 6400, abertura: 0 },
  { id: 2, nome: "Martinho Oficina", user: "martinho", pessoa: "Martinho Alves", email: "martinho@oficinamartinho.com.br", tel: "(14) 99640-2299", tipo: "customer", doc: "24.880.112/0001-51", end: "R. Tenente Ary, 118 — Jaú/SP",
    totalVenda: 12480, vendaPaga: 9800, totalCompra: 0, compraPaga: 0, abertura: 400 },
  { id: 3, nome: "Agência Norte", user: "agnorte", pessoa: "Paulo Serra", email: "paulo@agencianorte.com.br", tel: "(14) 3622-1180", tipo: "customer", doc: "31.004.667/0001-19", end: "R. Prudente de Moraes, 402 — Jaú/SP",
    totalVenda: 22150, vendaPaga: 20275, totalCompra: 0, compraPaga: 0, abertura: 0 },
];

const VENDAS = [
  { id: 1, data: "24/08/2026 09:12", inv: "POS-2026-0482", loc: "Matriz", status: "final", pg: "partial", total: 3120, pago: 1500, itens: 8 },
  { id: 2, data: "18/08/2026 11:48", inv: "POS-2026-0470", loc: "Matriz", status: "final", pg: "paid", total: 8420, pago: 8420, itens: 12 },
  { id: 3, data: "02/08/2026 16:05", inv: "POS-2026-0441", loc: "Filial Centro", status: "final", pg: "paid", total: 1875, pago: 1875, itens: 5 },
  { id: 4, data: "21/07/2026 14:30", inv: "POS-2026-0402", loc: "Matriz", status: "final", pg: "overdue", total: 5490, pago: 2000, itens: 9 },
];
const COMPRAS = [
  { id: 1, data: "10/08/2026", ref: "CMP-2026-0088", loc: "Matriz", status: "received", pg: "paid", total: 4200, pago: 4200 },
  { id: 2, data: "12/06/2026", ref: "CMP-2026-0061", loc: "Matriz", status: "received", pg: "paid", total: 2200, pago: 2200 },
];
const EXTRATO = [
  { id: 1, data: "24/08/2026", tipo: "sell", doc: "POS-2026-0482", debito: 3120, credito: 0, saldo: 1620 },
  { id: 2, data: "24/08/2026", tipo: "payment", doc: "PGT-0912", debito: 0, credito: 1500, saldo: 1620 },
  { id: 3, data: "18/08/2026", tipo: "sell", doc: "POS-2026-0470", debito: 8420, credito: 0, saldo: 8420 },
  { id: 4, data: "18/08/2026", tipo: "payment", doc: "PGT-0904", debito: 0, credito: 8420, saldo: 0 },
  { id: 5, data: "21/07/2026", tipo: "sell", doc: "POS-2026-0402", debito: 5490, credito: 0, saldo: 3490 },
  { id: 6, data: "21/07/2026", tipo: "payment", doc: "PGT-0871", debito: 0, credito: 2000, saldo: 3490 },
];
const TIPO_EXTRATO = { sell: "Venda", sell_return: "Devolução de venda", purchase: "Compra", purchase_return: "Devolução de compra", payment: "Pagamento", opening_balance: "Saldo de abertura" };
const AGENDAMENTOS = [
  { id: 1, inicio: "26/08/2026 09:00", fim: "26/08/2026 09:30", status: "booked", loc: "Matriz", nota: "Retirada de 3 banners." },
  { id: 2, inicio: "02/09/2026 14:00", fim: "02/09/2026 15:00", status: "booked", loc: "Filial Centro", nota: "Aprovação de arte no balcão." },
  { id: 3, inicio: "11/08/2026 10:00", fim: "11/08/2026 10:30", status: "completed", loc: "Matriz", nota: "" },
];
const STATUS_AGEND = { booked: ["Agendado", "info"], completed: ["Concluído", "ok"], cancelled: ["Cancelado", "mute"] };
const MEUS_PEDIDOS = [
  { id: 1, data: "24/08/2026 08:22", num: "SO-2026-0091", loc: "Matriz", status: "ordered", restante: 12, total: 4380 },
  { id: 2, data: "12/08/2026 16:04", num: "SO-2026-0087", loc: "Matriz", status: "partial", restante: 3, total: 690 },
  { id: 3, data: "28/07/2026 10:41", num: "SO-2026-0079", loc: "Filial Centro", status: "completed", restante: 0, total: 2210 },
];
const CATALOGO = [
  { id: 1, nome: "Lona 380g brilho impressa", sku: "PRD-0001", un: "m²", preco: 55 },
  { id: 2, nome: "Vinil adesivo brilho", sku: "PRD-0002", un: "m²", preco: 42 },
  { id: 3, nome: "Placa ACM 3mm recortada", sku: "PRD-0003", un: "m²", preco: 161.3 },
  { id: 4, nome: "Cartão de visita 4x4 — 1.000 un", sku: "PRD-0004", un: "cx", preco: 149.8 },
];

const FASES_OS = ["Recepção", "Diagnóstico", "Aguardando peça", "Em reparo", "Pronto"];
const OS_ = (o) => ({ fases: FASES_OS, recebido: o.andamento[o.andamento.length - 1].quando.slice(0, 5) + "/2026", ...o });
const ASSIST = {
  1: [OS_({ os: "JS-2026-0418", eq: "Plotter de recorte · Roland GR2-540", status: { nome: "Orçamento enviado", cor: "oklch(0.68 0.14 75)" }, fase: 1, prev: "29/08/2026", serie: "RL-GR2-88123", orc: "aguardando", codigo: "K7PM-4QXR",
        andamento: [{ ev: "Orçamento enviado pelo WhatsApp", quando: "24/08 17:02" }, { ev: "Diagnóstico concluído", quando: "24/08 15:12", nota: "Correia de tração gasta." }, { ev: "Equipamento recebido no balcão", quando: "24/08 09:40" }] }),
      OS_({ os: "JS-2026-0390", eq: "Impressora látex · Epson S60600", status: { nome: "Pronto p/ retirada", cor: "oklch(0.6 0.13 150)" }, fase: 4, prev: "22/08/2026", serie: "EP-S606-1172", orc: "aprovado", codigo: "3HNW-8TQA", prontoHa: 4,
        andamento: [{ ev: "Pronto para retirada", quando: "20/08 08:15" }, { ev: "Reparo concluído", quando: "19/08 17:30" }, { ev: "Equipamento recebido", quando: "12/08 10:02" }] })],
  2: [OS_({ os: "JS-2026-0417", eq: "Notebook · Dell Latitude 5440", status: { nome: "Aguardando peça", cor: "oklch(0.6 0.18 25)" }, fase: 2, prev: "02/09/2026", serie: "DL-5440-A991", orc: "aprovado", codigo: "R2XD-6MKP",
        andamento: [{ ev: "Peça encomendada ao fornecedor", quando: "24/08 09:05" }, { ev: "Equipamento recebido", quando: "23/08 11:20" }] })],
};
const TITULOS = { assistencia: "Assistência", painel: "Início", perfil: "Meu perfil", compras: "Minhas compras", vendas: "Minhas vendas", extrato: "Extrato", agendamentos: "Agendamentos", agendar: "Pedir horário", pedidos: "Pedidos", novo: "Nova solicitação de pedido" };
const PAI = { agendar: "agendamentos", novo: "pedidos" };
const ICONE = { assistencia: "wrench", painel: "grid", vendas: "receipt", compras: "truck", extrato: "doc", agendamentos: "clock", pedidos: "orders", perfil: "user" };
const PG = { paid: ["Pago", "success"], partial: ["Parcial", "warning"], overdue: ["Vencido", "danger"], due: ["A vencer", "info"] };
const ST_PED = { ordered: ["Solicitado", "info"], partial: ["Parcial", "warning"], completed: ["Atendido", "success"] };
const MESES = ["janeiro", "fevereiro", "março", "abril", "maio", "junho", "julho", "agosto", "setembro", "outubro", "novembro", "dezembro"];
const HORAS = Array.from({ length: 20 }, (_, i) => String(8 + Math.floor(i / 2)).padStart(2, "0") + ":" + (i % 2 ? "30" : "00"));
const brl = (v) => "R$ " + Number(v || 0).toLocaleString("pt-BR", { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const EMPRESA = "WR2 Comunicação Visual";

function Badge({ par }) {
  const { StatusBadge } = DS();
  if (!par) return null;
  return StatusBadge ? <StatusBadge label={par[0]} tone={par[1]} /> : <span>{par[0]}</span>;
}
function Sec({ titulo, nota, acoes, children, flush }) {
  return (
    <section className="cp-sec">
      <div className="cp-sec-h"><h2>{titulo}</h2>{nota && <small>{nota}</small>}<div className="sp" />{acoes}</div>
      {flush ? children : <div className="cp-sec-b">{children}</div>}
    </section>
  );
}

function CrmPortalPage({ view = "painel" }) {
  const M = MP();
  const { Logo, Avatar, KpiCard, Button, Input, Select, Textarea, Alert, EmptyState, FsmStepper, Segmented, DatePicker } = DS();
  const [tela, setTela] = useState(TITULOS[view] ? view : "painel");
  const [quem, setQuem] = useState(CONTATOS[0]);
  const [modo, setModo] = useState("tela");
  const [estado, setEstado] = useState("dados");
  const [tema, setTema] = useState(document.documentElement.dataset.theme === "light" ? "light" : "dark");
  const trocarTema = (v) => { setTema(v); window.__setTweak ? window.__setTweak("theme", v) : (document.documentElement.dataset.theme = v); };
  const [avisoNode, avisar] = M.useAviso ? M.useAviso() : [null, () => {}];
  const [agend, setAgend] = useState({ loc: "Matriz", dia: null, hora: "09:00", dur: "30", nota: "" });
  const [filtro, setFiltro] = useState("todas");
  const [itens, setItens] = useState([{ id: 1, prod: "", qtd: "1" }]);
  const [pedido, setPedido] = useState({ loc: "Matriz", data: "", ref: "" });
  if (!Button || !Input) return <div className="cp-stage"><p className="cp-help" style={{ padding: 24 }}>Carregando o portal…</p></div>;

  const ehFornecedor = quem.tipo === "supplier" || quem.tipo === "both";
  const ehCliente = quem.tipo === "customer" || quem.tipo === "both";
  const aPagar = quem.totalVenda - quem.vendaPaga;
  const totalPedido = itens.reduce((s, i) => { const p = CATALOGO.find((c) => c.nome === i.prod); return s + (p ? p.preco * (parseFloat(String(i.qtd).replace(",", ".")) || 0) : 0); }, 0);
  const abas = ["painel", ...(ehCliente ? ["vendas"] : []), ...(ehFornecedor ? ["compras"] : []), "assistencia", "extrato", "agendamentos", "pedidos"];
  const baixo = ["painel", ehCliente ? "vendas" : "compras", "assistencia", "pedidos", "extrato"];
  const minhasOs = ASSIST[quem.id] || [];
  const ativa = PAI[tela] || tela;
  const ir = (k) => { setTela(k); document.querySelector(".cp-root")?.scrollTo?.(0, 0); };
  const proximo = AGENDAMENTOS.find((g) => g.status === "booked");
  const ultimoPedido = MEUS_PEDIDOS[0];

  const { Skeleton } = DS();
  const Estado = () => estado === "carregando"
    ? <section className="cp-sec" aria-busy="true" aria-label="Carregando"><div className="cp-sec-b">{Skeleton ? <Skeleton variant="row" count={4} /> : <p className="cp-help">Carregando…</p>}</div></section>
    : estado === "erro"
      ? EmptyState && <section className="cp-sec"><div className="cp-sec-b"><EmptyState variant="error" title="Não foi possível carregar" description="A sua conta está certa — foi a conexão. Tente de novo; se continuar, fale com a loja." action={Button && <Button onClick={() => setEstado("dados")}>Tentar de novo</Button>} /></div></section>
      : EmptyState && <section className="cp-sec"><div className="cp-sec-b"><EmptyState variant="first" title={"Nada em " + (TITULOS[tela] || "").toLowerCase() + " ainda"} description="Quando houver movimento na sua conta, ele aparece aqui." /></div></section>;
  const listaComEstado = (corpo) => estado === "dados" ? corpo : <Estado />;
  const telaCorpo = (() => {
    if (tela === "painel") return (<>
      <div><h1 className="cp-h1">Olá, {quem.pessoa.split(" ")[0]}.</h1><p className="cp-sub">{quem.nome} · conta de cliente em {EMPRESA}</p></div>
      {ehCliente && <div className="cp-saldo" data-contract="portal-saldo">
        <div><div className="cp-saldo-l">Você tem a pagar</div><div className="cp-saldo-v">{brl(aPagar)}</div><div className="cp-saldo-s">de {brl(quem.totalVenda)} em vendas · {brl(quem.vendaPaga)} já pagos</div></div>
        <div className="sp" /><Button onClick={() => ir("extrato")}>Ver extrato</Button>
      </div>}
      {(ehFornecedor || !!quem.abertura) && <div className="cp-kpis" data-contract="portal-kpis">
        {ehFornecedor && KpiCard && <KpiCard label="Total em compras" value={brl(quem.totalCompra)} tone="info" />}
        {ehFornecedor && KpiCard && <KpiCard label="Compras a pagar" value={brl(quem.totalCompra - quem.compraPaga)} tone="warning" />}
        {!!quem.abertura && KpiCard && <KpiCard label="Saldo de abertura" value={brl(quem.abertura)} />}
      </div>}
      {minhasOs.filter((o) => o.fase === 4).map((o) => Alert && <Alert key={o.os} tone={o.prontoHa >= 7 ? "warn" : "success"} title={"Pronto para retirada há " + o.prontoHa + (o.prontoHa === 1 ? " dia" : " dias")}>{o.os} · {o.eq}. <button className="rpl-link" onClick={() => ir("assistencia")}>Ver na Assistência</button></Alert>)}
      {minhasOs.filter((o) => o.orc === "aguardando").map((o) => Alert && <Alert key={o.os + "o"} tone="warn" title="Orçamento aguardando sua aprovação">{o.os} · {o.eq}. <button className="rpl-link" onClick={() => ir("assistencia")}>Ver na Assistência</button></Alert>)}
      <div className="cp-acoes" data-contract="portal-atalhos">
        <button className="cp-acao" onClick={() => ir("novo")}><span className="cp-acao-i"><Ic name="plus" size={16} /></span><span>Pedir de novo<small>solicitação com o preço da sua tabela</small></span></button>
        <button className="cp-acao" onClick={() => ir("agendar")}><span className="cp-acao-i"><Ic name="clock" size={16} /></span><span>Agendar retirada<small>{proximo ? "próximo: " + proximo.inicio.slice(0, 5) + " às " + proximo.inicio.slice(11) : "o balcão confirma por WhatsApp"}</small></span></button>
        <button className="cp-acao" onClick={() => ir("pedidos")}><span className="cp-acao-i"><Ic name="orders" size={16} /></span><span>Acompanhar pedidos<small>{ultimoPedido.num} · {ST_PED[ultimoPedido.status][0].toLowerCase()}</small></span></button>
      </div>
    </>);

    if (tela === "assistencia") return (<>
      <div><h1 className="cp-h1">Assistência</h1><p className="cp-sub">Equipamentos seus na assistência técnica. As mesmas OS abrem pelo link com código que a loja manda, sem login.</p></div>
      {estado !== "dados" ? <Estado /> : minhasOs.length && window.OsAcompanhamento
        ? minhasOs.map((o) => <window.OsAcompanhamento key={o.os} os={o} variante="portal" onCopiar={() => avisar("Link copiado: oimpresso.com/os/" + o.codigo.replace("-", ""), "ok")} />)
        : EmptyState && <Sec titulo="Seus equipamentos"><EmptyState variant="first" title="Nenhum equipamento na assistência" description="Quando você deixar um equipamento na loja, a OS aparece aqui e o link de acompanhamento chega por WhatsApp." /></Sec>}
    </>);

    if (tela === "perfil") return (<>
      <div><h1 className="cp-h1">Meu perfil</h1><p className="cp-sub">Nome e CNPJ mudam só pelo balcão — é o cadastro fiscal.</p></div>
      <Sec titulo="Meus dados">
        <div className="cp-grid2" data-contract="portal-perfil">
          <Input label="Nome do contato" value={quem.nome} readOnly help="Muda só pelo balcão." />
          <Input label="CNPJ / CPF" value={quem.doc} readOnly help="Cadastro fiscal." />
          <Input label="Pessoa de contato" defaultValue={quem.pessoa} />
          <Input label="Telefone" defaultValue={quem.tel} />
          <div className="span2"><Input label="E-mail" defaultValue={quem.email} /></div>
          <div className="span2"><Input label="Endereço" defaultValue={quem.end} /></div>
        </div>
        <div className="cp-bar"><div className="sp" /><Button variant="primary" onClick={() => avisar("Perfil atualizado.", "ok")}>Atualizar perfil</Button></div>
      </Sec>
      <Sec titulo="Trocar senha">
        <div className="cp-grid2" data-contract="portal-senha">
          <div className="span2"><Input label="Senha atual" type="password" /></div>
          <Input label="Nova senha" type="password" />
          <Input label="Confirmar nova senha" type="password" />
        </div>
        <div className="cp-bar"><div className="sp" /><Button variant="primary" onClick={() => avisar("Senha atualizada.", "ok")}>Atualizar senha</Button></div>
      </Sec>
    </>);

    if (tela === "vendas" || tela === "compras") {
      const venda = tela === "vendas";
      const base = venda ? VENDAS : COMPRAS;
      const aberto = base.reduce((s, x) => s + (x.total - x.pago), 0);
      const vis = base.filter((x) => filtro === "todas" || (filtro === "aberto" ? x.total > x.pago : x.total <= x.pago));
      return (<>
        <div><h1 className="cp-h1">{TITULOS[tela]}</h1><p className="cp-sub">{base.length} {venda ? "fatura(s)" : "compra(s)"} · em aberto {brl(aberto)}</p></div>
        {listaComEstado(<Sec titulo={venda ? "Faturas" : "Compras"} acoes={Segmented && <Segmented size="sm" value={filtro} onChange={setFiltro} options={[{ value: "todas", label: "Todas", count: base.length }, { value: "aberto", label: "Em aberto", count: base.filter((x) => x.total > x.pago).length }, { value: "quitadas", label: "Quitadas" }]} />} flush>
          <ul className="cp-list" data-contract={"portal-" + tela}>
            {!vis.length && <li><span className="cp-l2">Nada {filtro === "aberto" ? "em aberto" : "quitado"} por aqui.</span></li>}
            {vis.map((x) => (
              <li key={x.id}>
                <div><div className="cp-l1"><b>{venda ? x.inv : x.ref}</b><Badge par={PG[x.pg]} /></div><div className="cp-l2">{x.data} · {x.loc}{venda ? " · " + x.itens + " itens" : ""}</div></div>
                <div className="cp-lv">{brl(x.total)}{x.total - x.pago > 0 ? <small className="dev">falta {brl(x.total - x.pago)}</small> : <small>quitado</small>}</div>
                <div className="cp-la"><Button size="sm" onClick={() => avisar((venda ? "Fatura " + x.inv : "Compra " + x.ref) + " aberta em PDF.", "ok")}><Ic name="printer" size={13} /> PDF</Button></div>
              </li>
            ))}
          </ul>
          <div className="cp-foot"><span>Saldo em aberto</span><div className="sp" /><b>{brl(aberto)}</b></div>
        </Sec>)}
      </>);
    }

    if (tela === "extrato") {
      const devedor = EXTRATO.reduce((s, e) => s + e.debito - e.credito, 0);
      return (<>
        <div><h1 className="cp-h1">Extrato</h1><p className="cp-sub">Débitos e créditos da sua conta · saldo devedor {brl(devedor)}</p></div>
        {listaComEstado(<Sec titulo="Lançamentos" nota={EXTRATO.length + " lançamento(s)"} acoes={<Button size="sm" onClick={() => avisar("Extrato gerado em PDF.", "ok")}><Ic name="printer" size={13} /> PDF</Button>} flush>
          <ul className="cp-list" data-contract="portal-extrato">
            {EXTRATO.map((e, i) => (<React.Fragment key={e.id}>
              {(i === 0 || EXTRATO[i - 1].data.slice(3) !== e.data.slice(3)) && <li className="cp-mes" aria-hidden="false"><span>{MESES[+e.data.slice(3, 5) - 1]} de {e.data.slice(6)}</span></li>}
              <li>
                <div><div className="cp-l1"><b>{e.doc}</b></div><div className="cp-l2">{e.data} · {TIPO_EXTRATO[e.tipo]}</div></div>
                <div className="cp-lv">{e.debito ? brl(e.debito) : <span className="cp-pos">− {brl(e.credito)}</span>}<small>saldo {brl(e.saldo)}</small></div>
              </li></React.Fragment>
            ))}
          </ul>
        </Sec>)}
      </>);
    }

    if (tela === "agendamentos") return (<>
      <div className="cp-bar"><div><h1 className="cp-h1">Agendamentos</h1><p className="cp-sub">Pedido de horário para retirar ou aprovar arte — o balcão confirma.</p></div><div className="sp" /><Button variant="primary" onClick={() => ir("agendar")}><Ic name="plus" size={13} /> Pedir horário</Button></div>
      {listaComEstado(<Sec titulo="Seus horários" nota={AGENDAMENTOS.length + " agendamento(s)"} flush>
        <ul className="cp-list" data-contract="portal-agendamentos">
          {AGENDAMENTOS.map((g) => (
            <li key={g.id}>
              <div><div className="cp-l1"><b>{g.inicio}</b><Badge par={STATUS_AGEND[g.status] && [STATUS_AGEND[g.status][0], { ok: "success", info: "info", mute: "neutral" }[STATUS_AGEND[g.status][1]]]} /></div><div className="cp-l2">{g.loc} · até {g.fim.slice(11)}{g.nota ? " · " + g.nota : ""}</div></div>
            </li>
          ))}
        </ul>
      </Sec>)}
    </>);

    if (tela === "agendar") return (<>
      <button className="cp-back" onClick={() => ir("agendamentos")}>← Agendamentos</button>
      <h1 className="cp-h1">Pedir horário</h1>
      <Sec titulo="Quando e onde">
        <div className="cp-grid2" data-contract="portal-agendar">
          <div className="span2"><Select label="Local" value={agend.loc} onChange={(e) => setAgend({ ...agend, loc: e.target.value })} options={["Matriz", "Filial Centro"].map((l) => ({ value: l, label: l }))} /></div>
          <div className="span2">{DatePicker ? <DatePicker label="Dia" value={agend.dia} min={new Date(2026, 7, 24)} onChange={(d) => setAgend({ ...agend, dia: d })} /> : <Input label="Dia" placeholder="dd/mm/aaaa" />}</div>
          <Select label="Horário" value={agend.hora} onChange={(e) => setAgend({ ...agend, hora: e.target.value })} options={HORAS.map((h) => ({ value: h, label: h }))} />
          <Select label="Duração" value={agend.dur} onChange={(e) => setAgend({ ...agend, dur: e.target.value })} options={[{ value: "30", label: "30 minutos" }, { value: "60", label: "1 hora" }]} />
          <div className="span2"><Textarea label="Observação" value={agend.nota} placeholder="O que você vem retirar ou aprovar" onChange={(e) => setAgend({ ...agend, nota: e.target.value })} /></div>
        </div>
        <p className="cp-help">É um pedido de horário: não reserva a produção. O balcão confirma por WhatsApp.</p>
        <div className="cp-bar"><Button onClick={() => ir("agendamentos")}>Cancelar</Button><div className="sp" /><Button variant="primary" disabled={!agend.dia} onClick={() => { avisar("Horário pedido — o balcão confirma por WhatsApp.", "ok"); ir("agendamentos"); }}>Enviar pedido de horário</Button></div>
      </Sec>
    </>);

    if (tela === "pedidos") return (<>
      <div className="cp-bar"><div><h1 className="cp-h1">Pedidos</h1><p className="cp-sub">Solicitações que você enviou — viram venda quando o balcão confirma.</p></div><div className="sp" /><Button variant="primary" onClick={() => ir("novo")}><Ic name="plus" size={13} /> Nova solicitação</Button></div>
      {listaComEstado(<Sec titulo="Suas solicitações" nota={MEUS_PEDIDOS.length + " pedido(s)"} flush>
        {MEUS_PEDIDOS.length ? <ul className="cp-list" data-contract="portal-pedidos">
          {MEUS_PEDIDOS.map((p) => (
            <li key={p.id}>
              <div><div className="cp-l1"><b>{p.num}</b><Badge par={ST_PED[p.status]} /></div><div className="cp-l2">{p.data} · {p.loc}{p.restante ? " · " + p.restante + " itens a entregar" : ""}</div></div>
              <div className="cp-lv">{brl(p.total)}</div>
            </li>
          ))}
        </ul> : EmptyState && <EmptyState variant="first" title="Nenhuma solicitação ainda" description="Monte um pedido com o preço da sua tabela; o balcão confirma prazo e frete." />}
      </Sec>)}
    </>);

    return (<>
      <button className="cp-back" onClick={() => ir("pedidos")}>← Pedidos</button>
      <h1 className="cp-h1">Nova solicitação de pedido</h1>
      <Sec titulo="Entrega">
        <div className="cp-grid2" data-contract="portal-pedido-novo">
          <div className="span2"><Select label="Local" value={pedido.loc} onChange={(e) => setPedido({ ...pedido, loc: e.target.value })} options={["Matriz", "Filial Centro"].map((l) => ({ value: l, label: l }))} /></div>
          <Input label="Data desejada" value={pedido.data} placeholder="dd/mm/aaaa" onChange={(e) => setPedido({ ...pedido, data: e.target.value })} />
          <Input label="Sua referência" value={pedido.ref} placeholder="OC 2026/114" onChange={(e) => setPedido({ ...pedido, ref: e.target.value })} />
        </div>
      </Sec>
      <Sec titulo="Itens" nota={itens.length + " linha(s)"}>
        <div data-contract="portal-pedido-itens">
          {itens.map((i, ix) => {
            const p = CATALOGO.find((c) => c.nome === i.prod);
            const q = parseFloat(String(i.qtd).replace(",", ".")) || 0;
            return (
              <div className="cp-item" key={i.id}>
                <Select label={"Produto " + (ix + 1)} value={i.prod} onChange={(e) => setItens(itens.map((x) => x.id === i.id ? { ...x, prod: e.target.value } : x))}
                  options={[{ value: "", label: "Escolha o produto" }, ...CATALOGO.map((c) => ({ value: c.nome, label: c.nome + " · " + brl(c.preco) + "/" + c.un }))]} />
                <div className="cp-qtd"><Input label={"Quantidade" + (p ? " (" + p.un + ")" : "")} value={i.qtd} onChange={(e) => setItens(itens.map((x) => x.id === i.id ? { ...x, qtd: e.target.value } : x))} />
                  <div className="cp-qtd-b" role="group" aria-label={"Ajustar quantidade do produto " + (ix + 1)}>
                    <button type="button" aria-label="Diminuir" disabled={q <= 1} onClick={() => setItens(itens.map((x) => x.id === i.id ? { ...x, qtd: String(Math.max(1, q - 1)).replace(".", ",") } : x))}>−</button>
                    <button type="button" aria-label="Aumentar" onClick={() => setItens(itens.map((x) => x.id === i.id ? { ...x, qtd: String(q + 1).replace(".", ",") } : x))}>+</button>
                  </div></div>
                <div className="cp-item-v">{p ? brl(p.preco * q) : "—"}{itens.length > 1 && <> <Button size="sm" variant="ghost" onClick={() => setItens(itens.filter((x) => x.id !== i.id))}>Remover</Button></>}</div>
              </div>
            );
          })}
          <div className="cp-bar" style={{ marginTop: 12 }}><Button onClick={() => setItens([...itens, { id: Date.now(), prod: "", qtd: "1" }])}><Ic name="plus" size={13} /> Adicionar item</Button></div>
        </div>
        <p className="cp-help">O preço é o da sua tabela. O balcão confirma prazo e frete antes de faturar — a solicitação não vira venda sozinha.</p>
        <div className="cp-total">
          <div><div className="cp-saldo-l">Total estimado</div><div className="cp-total-v">{brl(totalPedido)}</div></div>
          <div className="sp" />
          <Button variant="primary" disabled={!itens.some((i) => i.prod)} onClick={() => { avisar("Solicitação enviada — você recebe a confirmação por e-mail.", "ok"); setItens([{ id: 1, prod: "", qtd: "1" }]); ir("pedidos"); }}>Enviar solicitação</Button>
        </div>
      </Sec>
    </>);
  })();

  return (
    <div className="cp-stage" data-screen-label={"Portal do contato · " + (TITULOS[tela] || tela)}>
      <div className="cp-preview" role="region" aria-label="Controles da prévia (só no protótipo)">
        <span><b>Prévia</b> · o portal como o contato vê</span>
        <label style={{ display: "flex", alignItems: "center", gap: 6 }}>Contato
          <select value={quem.id} onChange={(e) => { setQuem(CONTATOS.find((c) => c.id === Number(e.target.value)) || quem); ir("painel"); }}>
            {CONTATOS.map((c) => <option key={c.id} value={c.id}>{c.nome}</option>)}
          </select>
        </label>
        <span className="cp-seg" role="group" aria-label="Tamanho da prévia">
          <button aria-pressed={modo === "celular"} onClick={() => setModo("celular")}>Celular 390</button>
          <button aria-pressed={modo === "tela"} onClick={() => setModo("tela")}>Tela cheia</button>
        </span>
        <span className="cp-seg" role="group" aria-label="Tema">
          <button aria-pressed={tema === "light"} onClick={() => trocarTema("light")}>Claro</button>
          <button aria-pressed={tema === "dark"} onClick={() => trocarTema("dark")}>Escuro</button>
        </span>
        <label style={{ display: "flex", alignItems: "center", gap: 6 }}>Estado
          <select value={estado} onChange={(e) => setEstado(e.target.value)}>
            <option value="dados">Com dados</option><option value="carregando">Carregando</option><option value="erro">Erro</option><option value="vazio">Vazio</option>
          </select>
        </label>
        <div className="sp" />
        <Button size="sm" onClick={() => window.__selectRoute && window.__selectRoute("crm-logins")}>Logins de contato</Button>
        <Button size="sm" onClick={() => window.__selectRoute && window.__selectRoute("crm-painel")}>Voltar ao CRM</Button>
      </div>
      <div className={"cp-viewport " + modo}>
        <div className="cp-frame">
          <div className="cp-root">
            <header className="cp-top">
              <div className="cp-brand">{Logo && <Logo size={26} />}<div className="cp-brand-t"><b>{EMPRESA}</b><small>Portal do cliente</small></div></div>
              <div className="sp" />
              <button className="cp-me" aria-label={"Meu perfil — " + quem.pessoa} aria-current={tela === "perfil" ? "page" : undefined} onClick={() => ir("perfil")}>
                <span className="cp-me-n"><b>{quem.pessoa}</b><small>{quem.nome}</small></span>
                {Avatar ? <Avatar name={quem.pessoa} size="sm" /> : <Ic name="user" size={18} />}
              </button>
              <Button size="sm" variant="ghost" onClick={() => avisar("Sessão encerrada (simulado).", "ok")}><Ic name="exit" size={14} /> Sair</Button>
            </header>
            <nav className="cp-nav" aria-label="Portal do cliente">
              {abas.map((k) => <button key={k} aria-current={ativa === k ? "page" : undefined} onClick={() => ir(k)}><Ic name={ICONE[k]} size={15} />{TITULOS[k]}</button>)}
            </nav>
            <div className="cp-main" role="region" aria-label={TITULOS[tela]}>{telaCorpo}</div>
            <div role="status" aria-live="polite" className="osc-sr">{(TITULOS[tela] || "") + (estado === "carregando" ? " — carregando" : estado === "erro" ? " — não foi possível carregar" : "")}</div>
            <nav className="cp-bottom" aria-label="Portal do cliente (celular)">
              {baixo.map((k) => <button key={k} aria-current={ativa === k ? "page" : undefined} onClick={() => ir(k)}><Ic name={ICONE[k]} size={20} />{{ painel: "Início", vendas: "Vendas", compras: "Compras", assistencia: "Assistência", extrato: "Extrato", agendamentos: "Agenda", pedidos: "Pedidos" }[k]}</button>)}
            </nav>
          </div>
        </div>
      </div>
      {avisoNode}
    </div>
  );
}

window.CrmPortalPage = CrmPortalPage;
})();
