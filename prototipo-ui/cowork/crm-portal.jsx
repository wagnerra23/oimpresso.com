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
// Consome window.CBD/CBUI/PBUI. Expõe window.CrmPortalPage.
(() => {
const { useState, useMemo } = React;
const MP = () => window.ModuloPadrao || {};
const DS = () => window.OfficeImpressoPontoWR2DesignSystem_019dd0 || {};
const UI = () => window.PBUI || {};
const D = () => window.CBD || {};
const U = () => window.CBUI || {};
const Ic = ({ name, size = 14 }) => { const F = (window.I || {})[name]; return F ? <F size={size} /> : null; };

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

const TITULOS = { painel: "Painel", perfil: "Meu perfil", compras: "Minhas compras", vendas: "Minhas vendas", extrato: "Extrato", agendamentos: "Agendamentos", pedidos: "Meus pedidos", novo: "Nova solicitação de pedido" };

function CrmPortalPage({ view = "painel" }) {
  const M = MP();
  const { Widget, Fld, Sel, Modal } = UI();
  const { Grade, Toolbar, Mini, Pill, Badge, Rodape } = U();
  const { fmtBRL, LOCAIS, rotulo, STATUS_PEDIDO } = D();
  const { KpiCard, Alert } = DS();
  const [tela, setTela] = useState(view);
  const [quem, setQuem] = useState(CONTATOS[0]);
  const [avisoNode, avisar] = M.useAviso ? M.useAviso() : [null, () => {}];
  const [agend, setAgend] = useState(null);
  const [itens, setItens] = useState([{ id: 1, prod: "", qtd: "1" }]);
  const [pedido, setPedido] = useState({ loc: "Matriz" });

  if (!Widget || !fmtBRL || !Grade) return <div className="pb-root"><div className="pb-body"><p className="pb-help">Carregando o portal…</p></div></div>;
  const kpi = (label, value, tone) => KpiCard ? <KpiCard label={label} value={value} tone={tone} /> : null;
  const ehFornecedor = quem.tipo === "supplier" || quem.tipo === "both";
  const ehCliente = quem.tipo === "customer" || quem.tipo === "both";
  const totalPedido = itens.reduce((a, i) => {
    const p = CATALOGO.find((c) => c.nome === i.prod);
    return a + (p ? p.preco * (parseFloat(String(i.qtd).replace(",", ".")) || 0) : 0);
  }, 0);

  const corpo = (() => {
    if (tela === "painel") return (
      <>
        {Alert && <Alert tone="info" title={"Olá, " + quem.pessoa.split(" ")[0] + "."}>Este é o portal do contato — a mesma conta que o cliente usa para acompanhar pedidos, extrato e agendamentos.</Alert>}
        <div className="pb-grid c3">
          {ehCliente && kpi("Total em vendas", fmtBRL(quem.totalVenda), "info")}
          {ehCliente && kpi("Total pago", fmtBRL(quem.vendaPaga), "success")}
          {ehCliente && kpi("Total a pagar", fmtBRL(quem.totalVenda - quem.vendaPaga), "warning")}
          {ehFornecedor && kpi("Total em compras", fmtBRL(quem.totalCompra), "info")}
          {ehFornecedor && kpi("Compras pagas", fmtBRL(quem.compraPaga), "success")}
          {ehFornecedor && kpi("Compras a pagar", fmtBRL(quem.totalCompra - quem.compraPaga), "warning")}
          {!!quem.abertura && kpi("Saldo de abertura", fmtBRL(quem.abertura))}
        </div>
        <Widget contrato="portal-atalhos" titulo="O que você pode fazer aqui">
          <div className="pb-filters-h">
            <span className="pb-help">Pedidos, extrato e agendamentos ficam disponíveis 24h — sem precisar ligar para o balcão.</span>
            <div className="sp" />
            <button className="os-btn sm" onClick={() => setTela("extrato")}>Ver extrato</button>
            <button className="os-btn sm" onClick={() => setTela("agendamentos")}>Agendar visita</button>
            <button className="os-btn sm primary" onClick={() => setTela("novo")}><Ic name="plus" size={12} /> Nova solicitação de pedido</button>
          </div>
        </Widget>
      </>
    );

    if (tela === "perfil") return (
      <>
        <Widget contrato="portal-perfil" titulo="Meus dados">
          <div className="pb-grid c2">
            <Fld label="Nome do contato"><input defaultValue={quem.nome} readOnly /></Fld>
            <Fld label="CNPJ / CPF"><input defaultValue={quem.doc} readOnly /></Fld>
            <Fld label="Pessoa de contato"><input defaultValue={quem.pessoa} /></Fld>
            <Fld label="E-mail"><input defaultValue={quem.email} /></Fld>
            <Fld label="Telefone"><input defaultValue={quem.tel} /></Fld>
            <Fld label="Endereço" span={2}><input defaultValue={quem.end} /></Fld>
          </div>
          <div className="pb-filters-h" style={{ marginTop: 12 }}>
            <span className="pb-help">Nome e CNPJ mudam só pelo balcão — é o cadastro fiscal.</span>
            <div className="sp" />
            <button className="os-btn sm primary" onClick={() => avisar("Perfil atualizado.", "ok")}>Atualizar perfil</button>
          </div>
        </Widget>
        <Widget contrato="portal-senha" titulo="Trocar senha">
          <div className="pb-grid c3">
            <Fld label="Senha atual" req><input type="password" /></Fld>
            <Fld label="Nova senha" req><input type="password" /></Fld>
            <Fld label="Confirmar nova senha" req><input type="password" /></Fld>
          </div>
          <div className="pb-filters-h" style={{ marginTop: 12 }}>
            <div className="sp" />
            <button className="os-btn sm primary" onClick={() => avisar("Senha atualizada.", "ok")}>Atualizar senha</button>
          </div>
        </Widget>
      </>
    );

    if (tela === "vendas" || tela === "compras") {
      const venda = tela === "vendas";
      const rows = (venda ? VENDAS : COMPRAS).map((s) => ({
        id: s.id, data: s.data, doc: venda ? s.inv : s.ref, loc: s.loc,
        pg: <Badge kind="payment" value={s.pg} />,
        total: fmtBRL(s.total), pago: fmtBRL(s.pago), saldo: fmtBRL(s.total - s.pago),
        itens: venda ? s.itens : "—",
        acao: <button className="os-btn sm ghost" onClick={() => avisar((venda ? "Fatura " : "Compra ") + (venda ? s.inv : s.ref) + " aberta em PDF.", "ok")}>PDF</button>,
      }));
      const cols = [
        { key: "data", label: "Data", width: 150, mono: true, sortable: true },
        { key: "doc", label: venda ? "Nº da fatura" : "Referência", width: 160, mono: true },
        { key: "loc", label: "Local", width: 140 },
        { key: "pg", label: "Status do pagamento", width: 160 },
        { key: "total", label: "Total", width: 130, align: "right", mono: true },
        { key: "pago", label: "Pago", width: 130, align: "right", mono: true },
        { key: "saldo", label: "Saldo", width: 130, align: "right", mono: true },
        { key: "itens", label: "Itens", width: 90, align: "right", mono: true },
        { key: "acao", label: "Ação", width: 90, resizable: false },
      ];
      const base = venda ? VENDAS : COMPRAS;
      return (
        <Widget contrato={"portal-" + tela} titulo={venda ? "Minhas vendas" : "Minhas compras"} nota={base.length + " lançamento(s)"} flush>
          <Grade columns={cols} rows={rows} altura={300} />
          <Rodape>
            <span className="pb-help">Saldo em aberto</span><div className="sp" />
            <b className="mono">{fmtBRL(base.reduce((a, s) => a + (s.total - s.pago), 0))}</b>
          </Rodape>
        </Widget>
      );
    }

    if (tela === "extrato") return (
      <Widget contrato="portal-extrato" titulo="Extrato" nota={"saldo devedor " + fmtBRL(EXTRATO.reduce((a, e) => a + e.debito - e.credito, 0))} flush>
        <Toolbar busca="" setBusca={() => {}} ph="Buscar documento">
          <button className="os-btn sm" onClick={() => avisar("Extrato gerado em PDF.", "ok")}><Ic name="print" size={12} /> PDF</button>
        </Toolbar>
        <Grade altura={300} columns={[
          { key: "data", label: "Data", width: 130, mono: true },
          { key: "tipo", label: "Tipo", width: 190 },
          { key: "doc", label: "Documento", width: 170, mono: true },
          { key: "debito", label: "Débito", width: 140, align: "right", mono: true },
          { key: "credito", label: "Crédito", width: 140, align: "right", mono: true },
          { key: "saldo", label: "Saldo", width: 140, align: "right", mono: true },
        ]} rows={EXTRATO.map((e) => ({
          id: e.id, data: e.data, tipo: TIPO_EXTRATO[e.tipo], doc: e.doc,
          debito: e.debito ? fmtBRL(e.debito) : "—", credito: e.credito ? fmtBRL(e.credito) : "—", saldo: fmtBRL(e.saldo),
        }))} />
      </Widget>
    );

    if (tela === "agendamentos") return (
      <>
        <Widget contrato="portal-agendamentos" titulo="Agendamentos" nota={AGENDAMENTOS.length + " agendamento(s)"} flush>
          <Toolbar busca="" setBusca={() => {}} ph="Buscar agendamento">
            <button className="os-btn sm primary" onClick={() => setAgend({ loc: "Matriz" })}><Ic name="plus" size={12} /> Adicionar agendamento</button>
          </Toolbar>
          <Grade altura={260} columns={[
            { key: "inicio", label: "Começa", width: 170, mono: true },
            { key: "fim", label: "Termina", width: 170, mono: true },
            { key: "status", label: "Status", width: 140 },
            { key: "loc", label: "Local", width: 150 },
            { key: "nota", label: "Observação", width: 260 },
          ]} rows={AGENDAMENTOS.map((a) => ({
            id: a.id, inicio: a.inicio, fim: a.fim,
            status: <Pill tom={STATUS_AGEND[a.status][1]}>{STATUS_AGEND[a.status][0]}</Pill>,
            loc: a.loc, nota: a.nota || "—",
          }))} />
        </Widget>
        {Modal && agend &&
          <Modal titulo="Adicionar agendamento" onClose={() => setAgend(null)}
            acoes={<>
              <button className="os-btn" onClick={() => setAgend(null)}>Fechar</button>
              <button className="os-btn primary" onClick={() => { setAgend(null); avisar("Agendamento solicitado — o balcão confirma por WhatsApp.", "ok"); }}>Salvar</button>
            </>}>
            <div className="pb-grid c2">
              <Fld label="Local" req><Sel value={agend.loc} onChange={(v) => setAgend({ ...agend, loc: v })} options={LOCAIS.map((l) => ({ id: l.name, name: l.name }))} vazio="Selecione" /></Fld>
              <Fld label="Começa em" req><input value={agend.inicio || ""} onChange={(e) => setAgend({ ...agend, inicio: e.target.value })} placeholder="dd/mm/aaaa hh:mm" /></Fld>
              <Fld label="Termina em" req><input value={agend.fim || ""} onChange={(e) => setAgend({ ...agend, fim: e.target.value })} placeholder="dd/mm/aaaa hh:mm" /></Fld>
              <Fld label="Observação" span={2}><textarea value={agend.nota || ""} onChange={(e) => setAgend({ ...agend, nota: e.target.value })} /></Fld>
            </div>
          </Modal>}
      </>
    );

    if (tela === "pedidos") return (
      <Widget contrato="portal-pedidos" titulo="Meus pedidos" nota={MEUS_PEDIDOS.length + " pedido(s)"} flush>
        <Toolbar busca="" setBusca={() => {}} ph="Buscar pedido">
          <button className="os-btn sm primary" onClick={() => setTela("novo")}><Ic name="plus" size={12} /> Nova solicitação de pedido</button>
        </Toolbar>
        <Grade altura={260} columns={[
          { key: "data", label: "Data", width: 160, mono: true },
          { key: "num", label: "Nº do pedido", width: 160, mono: true },
          { key: "loc", label: "Local", width: 150 },
          { key: "status", label: "Status", width: 180 },
          { key: "restante", label: "Quantidade restante", width: 170, align: "right", mono: true },
          { key: "total", label: "Total", width: 140, align: "right", mono: true },
        ]} rows={MEUS_PEDIDOS.map((p) => ({
          id: p.id, data: p.data, num: p.num, loc: p.loc,
          status: <Pill tom={p.status === "completed" ? "ok" : p.status === "partial" ? "warn" : "info"}>{rotulo(STATUS_PEDIDO, p.status)}</Pill>,
          restante: p.restante, total: fmtBRL(p.total),
        }))} />
      </Widget>
    );

    // order_request/create + product_row
    return (
      <>
        <div className="pb-filters-h">
          <button className="os-btn sm ghost" onClick={() => setTela("pedidos")}>← Meus pedidos</button>
        </div>
        <Widget contrato="portal-pedido-novo" titulo="Nova solicitação de pedido">
          <div className="pb-grid c3">
            <Fld label="Local" req><Sel value={pedido.loc} onChange={(v) => setPedido({ ...pedido, loc: v })} options={LOCAIS.map((l) => ({ id: l.name, name: l.name }))} vazio="Selecione" /></Fld>
            <Fld label="Data desejada de entrega"><input value={pedido.data || ""} onChange={(e) => setPedido({ ...pedido, data: e.target.value })} placeholder="dd/mm/aaaa" /></Fld>
            <Fld label="Sua referência"><input value={pedido.ref || ""} onChange={(e) => setPedido({ ...pedido, ref: e.target.value })} placeholder="OC 2026/114" /></Fld>
          </div>
        </Widget>
        <Widget contrato="portal-pedido-itens" titulo="Itens do pedido" nota={itens.length + " linha(s)"}>
          <table className="pb-tbl" style={{ width: "100%" }}>
            <thead><tr><th>Produto</th><th style={{ width: 120 }}>Unidade</th><th style={{ width: 120 }}>Quantidade</th><th style={{ width: 130, textAlign: "right" }}>Preço</th><th style={{ width: 140, textAlign: "right" }}>Subtotal</th><th style={{ width: 70 }}></th></tr></thead>
            <tbody>
              {itens.map((i, ix) => {
                const p = CATALOGO.find((c) => c.nome === i.prod);
                const q = parseFloat(String(i.qtd).replace(",", ".")) || 0;
                return (
                  <tr key={i.id}>
                    <td>
                      <Sel value={i.prod} onChange={(v) => setItens(itens.map((x) => x.id === i.id ? { ...x, prod: v } : x))}
                        options={CATALOGO.map((c) => ({ id: c.nome, name: c.nome + " · " + c.sku }))} vazio="Escolha o produto" />
                    </td>
                    <td className="mono">{p ? p.un : "—"}</td>
                    <td><input className="num" value={i.qtd} onChange={(e) => setItens(itens.map((x) => x.id === i.id ? { ...x, qtd: e.target.value } : x))} /></td>
                    <td className="mono" style={{ textAlign: "right" }}>{p ? fmtBRL(p.preco) : "—"}</td>
                    <td className="mono" style={{ textAlign: "right" }}>{p ? fmtBRL(p.preco * q) : "—"}</td>
                    <td>{itens.length > 1 && <button className="os-btn sm ghost danger" onClick={() => setItens(itens.filter((x) => x.id !== i.id))}>remover</button>}</td>
                  </tr>
                );
              })}
            </tbody>
          </table>
          <div className="pb-filters-h" style={{ marginTop: 12 }}>
            <button className="os-btn sm" onClick={() => setItens([...itens, { id: Date.now(), prod: "", qtd: "1" }])}><Ic name="plus" size={12} /> Adicionar linha</button>
            <div className="sp" />
            <span className="pb-help">Total estimado</span>
            <b className="mono">{fmtBRL(totalPedido)}</b>
          </div>
          <div className="pb-filters-h" style={{ marginTop: 10 }}>
            <span className="pb-help">O preço é o da sua tabela — o balcão confirma prazo e frete antes de faturar.</span>
            <div className="sp" />
            <button className="os-btn sm primary" onClick={() => {
              if (!itens.some((i) => i.prod)) return avisar("Escolha ao menos um produto.", "warn");
              avisar("Solicitação de pedido enviada — você recebe a confirmação por e-mail.", "ok");
              setItens([{ id: 1, prod: "", qtd: "1" }]); setTela("pedidos");
            }}>Enviar solicitação</button>
          </div>
        </Widget>
      </>
    );
  })();

  const abas = ["painel", "perfil", ...(quem.tipo !== "supplier" ? ["vendas"] : []), ...(quem.tipo === "both" || quem.tipo === "supplier" ? ["compras"] : []), "extrato", "agendamentos", "pedidos"];

  return (
    <div className="pb-root cb-root" data-screen-label={"Portal do contato · " + (TITULOS[tela] || tela)}>
      {M.Header &&
        <M.Header modulo="Portal do contato" papel={TITULOS[tela] || tela}
          contexto={["OFFICEIMPRESSO", quem.nome, quem.pessoa + " · " + quem.user, "visão do cliente logado"]}
          glyph={<Ic name="globe" />}
          acoes={<>
            <div style={{ minWidth: 220 }}>
              <Sel value={quem.nome} onChange={(v) => setQuem(CONTATOS.find((c) => c.nome === v) || quem)} options={CONTATOS.map((c) => ({ id: c.nome, name: c.nome }))} />
            </div>
            <button className="os-btn" onClick={() => window.__selectRoute && window.__selectRoute("crm-logins")}>Logins de contato</button>
            <button className="os-btn primary" onClick={() => setTela("novo")}><Ic name="plus" size={13} /> Novo pedido</button>
          </>} />}
      <div className="pb-body">
        <nav className="cli-moduletopnav cb-nav" aria-label="Telas do portal do contato">
          {abas.map((k) => <button key={k} className={"cli-moduletopnav-tab " + (tela === k || (k === "pedidos" && tela === "novo") ? "active" : "")} onClick={() => setTela(k)}>{TITULOS[k]}</button>)}
        </nav>
        {corpo}
      </div>
      {avisoNode}
    </div>
  );
}

window.CrmPortalPage = CrmPortalPage;
})();
