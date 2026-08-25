// compras-extras.jsx — Pedido de compra · Requisição · Devolução de compra.
// Onda 5 (paridade com o main, lido em 2026-08-22, tree 6a8e45998ee5):
//   · purchase_order/index.blade.php + PurchaseOrderController: colunas literais
//     Ação · Data · Nº ref · Local · Fornecedor · Status · Qtd restante · Status do envio ·
//     Adicionado por. Qtd restante = SUM(quantity − po_quantity_purchased). O valor do
//     pedido é selecionado no controller mas NÃO é coluna da lista — vive no detalhe.
//   · Status do PEDIDO são só três: ordered · partial · completed (não existe "cancelado";
//     o que existe é exclusão). Admin edita o status inline, exceto em completed
//     (edit_status_modal + postEditPurchaseOrderStatus).
//   · Status do ENVIO é outro eixo: ordered · packed · shipped · delivered · cancelled.
//   · Requisição (PurchaseRequisitionController): status ordered · partial · completed,
//     nasce 'ordered', colunas Ação · Data · Nº ref · Local · Status · Necessário até
//     (delivery_date) · Adicionado por. Ações do vivo: ver e excluir. A conversão em pedido
//     acontece do lado do PEDIDO (purchase_requisition_ids → updatePurchaseOrderStatus), e a
//     sugestão de itens vem de estoque ≤ alerta (getRequisitionProducts).
//   · Devolução (purchase_return/index): Ação é a ÚLTIMA coluna, e o rodapé soma total
//     devolvido, contagem por status de pagamento e total devido.
// Expõe window.ComprasExtrasPage.
(() => {
const { useState, useRef } = React;
const DS = () => window.OfficeImpressoPontoWR2DesignSystem_019dd0 || {};
const UI = () => window.PBUI || {};
const MP = () => window.ModuloPadrao || {};
const CU = () => window.CatchupUI || {};
const Ic = ({ name, size = 14 }) => { const F = (window.I || {})[name]; return F ? <F size={size} /> : null; };
const brl = (n) => "R$ " + Number(n || 0).toLocaleString("pt-BR", { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const un = (n) => Number(n || 0).toLocaleString("pt-BR") + " un";

const CM = () => window.COMPRAS_MOCK || { SUPPLIERS: {}, PURCHASES: [] };
const fornecedorDe = (cod) => (CM().SUPPLIERS[cod] || {}).name || cod || "—";
const compraDe = (id) => (CM().PURCHASES || []).find((p) => p.id === id) || null;
// Os três status do documento, iguais nos dois controllers.
const ST = { ordenado: "Ordenado", parcial: "Parcial", concluido: "Concluído" };
const ENVIO = { ordered: "Pedido", packed: "Embalado", shipped: "Enviado", delivered: "Entregue", cancelled: "Cancelado" };

const PEDIDOS0 = [
  { id: 1, data: "20/08/2026", ref: "PO-2026-0114", loc: "Matriz", forn: "SUP-001", status: "ordenado", itens: 6, qtd: 180, recebida: 0, envio: "ordered", quem: "Larissa Prado", total: 12450 },
  { id: 2, data: "18/08/2026", ref: "PO-2026-0113", loc: "Matriz", forn: "SUP-003", status: "parcial", itens: 4, qtd: 96, recebida: 40, envio: "shipped", quem: "Wagner Ramos", total: 7320 },
  { id: 3, data: "14/08/2026", ref: "PO-2026-0111", loc: "Filial Centro", forn: "SUP-002", status: "concluido", itens: 9, qtd: 320, recebida: 320, envio: "delivered", quem: "Larissa Prado", total: 18900 },
  { id: 4, data: "09/08/2026", ref: "PO-2026-0108", loc: "Matriz", forn: "SUP-004", status: "ordenado", itens: 2, qtd: 24, recebida: 0, envio: "cancelled", quem: "Wagner Ramos", total: 2400 },
];
// Requisição e pedido gerado compartilham o local — é assim que o vivo lista as requisições
// disponíveis na criação do pedido (where location_id = pedido.location_id).
const REQ0 = [
  { id: 1, data: "21/08/2026", ref: "RQ-2026-0042", loc: "Matriz", status: "ordenado", prazo: "28/08/2026", quem: "Larissa Prado", itens: 5, po: null, motivo: "Reposição de lona 380g — estoque abaixo do alerta." },
  { id: 2, data: "19/08/2026", ref: "RQ-2026-0041", loc: "Matriz", status: "parcial", prazo: "26/08/2026", quem: "Marcos Vinícius", itens: 6, po: "PO-2026-0114", motivo: "Vinil de recorte para pedido da Prefeitura." },
  { id: 3, data: "12/08/2026", ref: "RQ-2026-0039", loc: "Filial Centro", status: "concluido", prazo: "18/08/2026", quem: "Wagner Ramos", itens: 9, po: "PO-2026-0111", motivo: "Reposição de papel couché e ilhós." },
];
const DEV0 = [
  { id: 1, data: "17/08/2026", ref: "DC-2026-0009", pai: "COMP-2847", loc: "Matriz", total: 1770, itens: 2, motivo: "2 rolos com falha de bobina." },
  { id: 2, data: "11/08/2026", ref: "DC-2026-0008", pai: "COMP-2844", loc: "Matriz", total: 640, itens: 1, motivo: "Vinil fora da cor pedida." },
];
// Crédito de devolução só está ressarcido se a compra de origem estiver quitada.
const creditoDe = (d) => {
  const c = compraDe(d.pai);
  if (c && c.due === 0 && c.paid > 0) return { pg: "Pago", devido: 0 };
  if (c && c.paid > 0) return { pg: "Parcial", devido: d.total };
  return { pg: "Devido", devido: d.total };
};
const proxRef = (pedidos) => "PO-2026-" + String(pedidos.reduce((m, p) => Math.max(m, +String(p.ref).slice(-4) || 0), 0) + 1).padStart(4, "0");
// Estado fora do componente: trocar de aba remonta a página, e uma conversão feita não pode
// desaparecer na navegação.
const STORE = { pedidos: PEDIDOS0, reqs: REQ0, devs: DEV0 };

function ComprasExtrasPage({ view = "cmp-pedidos" }) {
  const M = MP();
  const { Widget, Kebab } = UI();
  const { StatusBadge, Alert } = DS();
  const { Grade, Toolbar, Kpis, Painel, Def, Itens, useNav, itensDe } = CU();
  const [, tick] = useState(0);
  const pedidos = STORE.pedidos, reqs = STORE.reqs, devs = STORE.devs;
  const setPedidos = (fn) => { STORE.pedidos = fn(STORE.pedidos); tick((t) => t + 1); };
  const setReqs = (fn) => { STORE.reqs = fn(STORE.reqs); tick((t) => t + 1); };
  const setDevs = (fn) => { STORE.devs = fn(STORE.devs); tick((t) => t + 1); };
  const [busca, setBusca] = useState("");
  const [densa, setDensa] = useState(false);
  const [sel, setSel] = useState(null);
  const [avisoNode, avisar] = M.useAviso ? M.useAviso() : [null, () => {}];
  const boxRef = useRef(null), buscaRef = useRef(null);
  if (useNav) useNav(boxRef, buscaRef, setDensa);
  const ir = (r) => window.__selectRoute && window.__selectRoute(r);
  const bad = (kind, v) => StatusBadge ? <StatusBadge kind={kind} value={v} /> : v;
  const casa = (s) => !busca || String(s).toLowerCase().includes(busca.toLowerCase());
  const fechar = () => setSel(null);
  const restante = (p) => Math.max(0, p.qtd - p.recebida);

  // ── ações ─────────────────────────────────────────────────────────────────
  const criarCompra = (p) => {
    setPedidos((L) => L.map((x) => x.id === p.id ? { ...x, recebida: x.qtd, status: "concluido", envio: x.envio === "cancelled" ? x.envio : "delivered" } : x));
    fechar(); avisar("Compra criada a partir de " + p.ref + " — é ela que dá entrada de " + un(restante(p)) + " no estoque de " + p.loc + ". O pedido virou Concluído.", "ok");
  };
  const mudarStatus = (p, novo) => {
    setPedidos((L) => L.map((x) => x.id === p.id ? { ...x, status: novo } : x));
    fechar(); avisar(p.ref + ": status alterado para " + ST[novo] + " — fica no histórico de atividade do pedido.", "ok");
  };
  const mudarEnvio = (p, novo) => {
    setPedidos((L) => L.map((x) => x.id === p.id ? { ...x, envio: novo } : x));
    avisar(p.ref + ": envio " + ENVIO[novo].toLowerCase() + ".", "ok");
  };
  const excluirPO = (p) => {
    setPedidos((L) => L.filter((x) => x.id !== p.id));
    setReqs((L) => L.map((r) => r.po === p.ref ? { ...r, po: null, status: "ordenado" } : r));
    fechar(); avisar(p.ref + " excluído — as linhas de compra que apontavam pra ele ficam sem vínculo, e a requisição de origem volta a Ordenado.", "warn");
  };
  const gerarPO = (r) => {
    const ref = proxRef(pedidos);
    setPedidos((L) => [{ id: Date.now(), data: "22/08/2026", ref, loc: r.loc, forn: "SUP-001", status: "ordenado", itens: r.itens, qtd: r.itens * 24, recebida: 0, envio: "ordered", quem: "Larissa Prado", total: Math.round(r.itens * 890) }].concat(L));
    setReqs((L) => L.map((x) => x.id === r.id ? { ...x, status: "parcial", po: ref } : x));
    fechar(); avisar(ref + " criado com as linhas de " + r.ref + " — a requisição foi para Parcial e só fica Concluída quando todas as linhas forem pedidas.", "ok");
  };
  const excluirReq = (r) => {
    setReqs((L) => L.filter((x) => x.id !== r.id));
    fechar(); avisar(r.ref + " excluída — as linhas de compra que vinham dela perdem o vínculo, mas os pedidos já feitos continuam.", "warn");
  };
  const excluirDev = (d) => {
    setDevs((L) => L.filter((x) => x.id !== d.id));
    fechar(); avisar(d.ref + " excluída — o estoque devolvido volta para " + d.loc + ".", "warn");
  };

  // ── Pedidos de compra ─────────────────────────────────────────────────────
  const telaPedidos = () => {
    const rows = pedidos.filter((p) => casa(p.ref + " " + fornecedorDe(p.forn) + " " + p.quem));
    const abertos = pedidos.filter((p) => p.status !== "concluido");
    const qtdRestante = pedidos.reduce((a, p) => a + restante(p), 0);
    const cols = [
      { key: "acao", label: "Ação", width: 84, resizable: false },
      { key: "data", label: "Data", width: 112, mono: true },
      { key: "ref", label: "Nº de referência", width: 150, mono: true, sortable: true },
      { key: "loc", label: "Local", width: 124 },
      { key: "forn", label: "Fornecedor", width: 200, sortable: true },
      { key: "status", label: "Status", width: 128 },
      { key: "rest", label: "Quantidade restante", width: 172, align: "right", mono: true, sortable: true, sortValue: (r) => restante(r._p) },
      { key: "envio", label: "Status do envio", width: 150 },
      { key: "quem", label: "Adicionado por", width: 156 },
    ];
    const linhas = rows.map((p) => ({
      id: p.id, _p: p, state: p.envio === "cancelled" ? "archived" : p.status === "parcial" ? "urgent" : undefined,
      acao: Kebab ? <Kebab acoes={[
        { l: "Ver", ic: "search", on: () => setSel({ k: "po", d: p }) },
        { l: "Impressão", ic: "print", on: () => avisar(p.ref + " na fila de impressão.", "ok") },
        { l: "Baixar PDF", ic: "download", on: () => avisar("PDF de " + p.ref + " gerado com o layout da fatura do local.", "ok") },
        { l: "Editar", ic: "pencil", on: () => avisar("Editando " + p.ref + " — mexer nas linhas reapura o status do pedido.", "ok") },
        ...(p.status !== "concluido" ? [{ l: "Alterar status", ic: "refresh", on: () => setSel({ k: "po", d: p }) }] : []),
        { l: "Editar envio", ic: "truck", on: () => mudarEnvio(p, p.envio === "delivered" ? "shipped" : "delivered") },
        { l: "Enviar notificação", ic: "send", on: () => avisar("Modelo de notificação de " + p.ref + " aberto — nada sai sem você confirmar.", "ok") },
        "-",
        { l: "Excluir", ic: "x", tone: "danger", on: () => excluirPO(p) },
      ]} /> : null,
      data: p.data, ref: p.ref, loc: p.loc, forn: fornecedorDe(p.forn),
      status: bad("documento", ST[p.status]),
      rest: restante(p) ? un(restante(p)) : "—",
      envio: bad("documento", ENVIO[p.envio]), quem: p.quem,
    }));
    return (
      <>
        <Kpis itens={[
          { l: "Pedidos em aberto", v: String(abertos.length), n: "ordenados ou parciais — concluído sai da fila" },
          { l: "Quantidade restante", v: qtdRestante ? un(qtdRestante) : "0 un", tom: qtdRestante ? "warn" : "pos", n: "quantidade pedida que ainda não foi comprada" },
          { l: "Vindos de requisição", v: reqs.filter((r) => r.po).length + " de " + pedidos.length, n: "o resto foi criado direto em Compras" },
        ]} />
        <Widget flush titulo={<><Ic name="orders" size={13} /> Todos os pedidos de compra</>} nota={rows.length + " de " + pedidos.length}>
          <Toolbar busca={busca} setBusca={setBusca} buscaRef={buscaRef} ph="Buscar pedido, fornecedor ou quem adicionou…" densa={densa} setDensa={setDensa}>
            <button className="os-btn sm primary" onClick={() => avisar("Novo pedido de compra — fornecedor, local e data são obrigatórios.", "ok")}><Ic name="plus" size={12} /> Adicionar</button>
          </Toolbar>
          <Grade columns={cols} rows={linhas} densa={densa} onRowClick={(r) => setSel({ k: "po", d: (r.cells || r)._p })} />
          <div className="pb-pag"><span className="pb-help">O pedido não movimenta estoque: quem movimenta é a compra criada a partir dele. Status do pedido e status do envio são eixos separados.</span></div>
        </Widget>
      </>
    );
  };

  // ── Requisições ───────────────────────────────────────────────────────────
  const telaReqs = () => {
    const rows = reqs.filter((r) => casa(r.ref + " " + r.quem));
    const semPedido = reqs.filter((r) => r.status === "ordenado");
    const cols = [
      { key: "acao", label: "Ação", width: 84, resizable: false },
      { key: "data", label: "Data", width: 112, mono: true },
      { key: "ref", label: "Nº de referência", width: 150, mono: true, sortable: true },
      { key: "loc", label: "Local", width: 140 },
      { key: "status", label: "Status", width: 128 },
      { key: "prazo", label: "Necessário até", width: 148, mono: true, sortable: true },
      { key: "quem", label: "Adicionado por", width: 160 },
      { key: "po", label: "Pedido gerado", width: 148, mono: true },
    ];
    const linhas = rows.map((r) => ({
      id: r.id, _r: r, state: r.status === "concluido" ? "archived" : r.status === "ordenado" ? "urgent" : undefined,
      acao: Kebab ? <Kebab acoes={[
        { l: "Ver", ic: "search", on: () => setSel({ k: "req", d: r }) },
        ...(r.po ? [{ l: "Abrir " + r.po, ic: "orders", on: () => { fechar(); ir("cmp-pedidos"); } }] : []),
        "-",
        { l: "Excluir", ic: "x", tone: "danger", on: () => excluirReq(r) },
      ]} /> : null,
      data: r.data, ref: r.ref, loc: r.loc,
      status: bad("documento", ST[r.status]),
      prazo: r.prazo, quem: r.quem, po: r.po || "—",
    }));
    return (
      <>
        <Kpis itens={[
          { l: "Sem pedido ainda", v: String(semPedido.length), tom: semPedido.length ? "warn" : "pos", n: "requisição ordenada esperando o comprador" },
          { l: "Parcialmente pedidas", v: String(reqs.filter((r) => r.status === "parcial").length), n: "parte das linhas já virou pedido" },
          { l: "Itens requisitados", v: un(reqs.filter((r) => r.status !== "concluido").reduce((a, r) => a + r.itens, 0)).replace(" un", " itens"), n: "somando as requisições ainda abertas" },
        ]} />
        <Widget flush titulo={<><Ic name="doc" size={13} /> Requisições de compra</>} nota={rows.length + " de " + reqs.length}>
          <p className="cu-nota" style={{ padding: "0 12px 8px" }}>A requisição é o pedido interno de reposição: o vivo sugere os itens cujo estoque caiu ao nível de alerta no local escolhido.</p>
          <Toolbar busca={busca} setBusca={setBusca} buscaRef={buscaRef} ph="Buscar requisição ou quem adicionou…" densa={densa} setDensa={setDensa}>
            <button className="os-btn sm primary" onClick={() => avisar("Nova requisição — escolha o local e a data em que o material é necessário.", "ok")}><Ic name="plus" size={12} /> Adicionar</button>
          </Toolbar>
          <Grade columns={cols} rows={linhas} densa={densa} altura={280} onRowClick={(r) => setSel({ k: "req", d: (r.cells || r)._r })} />
          <div className="pb-pag"><span className="pb-help">Quem escolhe fornecedor é o comprador: a requisição vira pedido pelo lado do pedido, e o status dela é reapurado a partir das linhas atendidas.</span></div>
        </Widget>
      </>
    );
  };

  // ── Devoluções ────────────────────────────────────────────────────────────
  const telaDevs = () => {
    const rows = devs.filter((d) => casa(d.ref + " " + d.pai + " " + fornecedorDe((compraDe(d.pai) || {}).supplier)));
    const devolvido = devs.reduce((a, d) => a + d.total, 0);
    const devido = devs.reduce((a, d) => a + creditoDe(d).devido, 0);
    const porStatus = devs.reduce((a, d) => { const s = creditoDe(d).pg; a[s] = (a[s] || 0) + 1; return a; }, {});
    const cols = [
      { key: "data", label: "Data", width: 112, mono: true },
      { key: "ref", label: "Nº de referência", width: 146, mono: true, sortable: true },
      { key: "pai", label: "Compra de origem", width: 150, mono: true },
      { key: "loc", label: "Local", width: 124 },
      { key: "forn", label: "Fornecedor", width: 196 },
      { key: "pg", label: "Status do pagamento", width: 156 },
      { key: "total", label: "Valor total", width: 138, align: "right", mono: true },
      { key: "devido", label: "Valor devido", width: 140, align: "right", mono: true },
      { key: "acao", label: "Ação", width: 84, resizable: false },
    ];
    const linhas = rows.map((d) => ({
      id: d.id, _d: d,
      data: d.data, ref: d.ref, pai: d.pai, loc: d.loc, forn: fornecedorDe((compraDe(d.pai) || {}).supplier),
      pg: bad("payment", creditoDe(d).pg),
      total: brl(d.total), devido: brl(creditoDe(d).devido),
      acao: Kebab ? <Kebab acoes={[
        { l: "Ver", ic: "search", on: () => setSel({ k: "dev", d }) },
        { l: "Impressão", ic: "print", on: () => avisar(d.ref + " na impressora.", "ok") },
        { l: "Ver compra de origem", ic: "archive", on: () => { fechar(); ir("compras"); } },
        "-",
        { l: "Excluir", ic: "x", tone: "danger", on: () => excluirDev(d) },
      ]} /> : null,
    }));
    return (
      <>
        {Alert && <Alert tone="info" title="Devolução de compra">Sai do estoque e vira crédito com o fornecedor — a devolução nasce sempre de uma compra existente, nunca avulsa.</Alert>}
        <Kpis itens={[
          { l: "Total devolvido", v: brl(devolvido), n: devs.length + " devolução(ões) no período" },
          { l: "Total devido", v: brl(devido), tom: devido ? "warn" : "pos", n: "compra não quitada não devolve dinheiro" },
          { l: "Por status de pagamento", v: Object.keys(porStatus).map((k) => porStatus[k] + " " + k.toLowerCase()).join(" · ") || "—", n: "mesma contagem do rodapé do vivo" },
        ]} />
        <Widget flush titulo={<><Ic name="truck" size={13} /> Todas as devoluções de compra</>} nota={rows.length + " de " + devs.length}>
          <Toolbar busca={busca} setBusca={setBusca} buscaRef={buscaRef} ph="Buscar devolução, compra ou fornecedor…" densa={densa} setDensa={setDensa} />
          <Grade columns={cols} rows={linhas} densa={densa} altura={260} onRowClick={(r) => setSel({ k: "dev", d: (r.cells || r)._d })} />
          <div className="pb-pag"><span>Total: <b>{brl(rows.reduce((a, d) => a + d.total, 0))}</b> · devido <b>{brl(rows.reduce((a, d) => a + creditoDe(d).devido, 0))}</b></span></div>
        </Widget>
      </>
    );
  };

  // ── Drawer de detalhe (PT-02) ─────────────────────────────────────────────
  const painel = () => {
    if (!sel || !Painel) return null;
    if (sel.k === "po") {
      const p = sel.d, org = reqs.find((r) => r.po === p.ref), aberto = p.status !== "concluido";
      return <Painel aberto onClose={fechar} titulo={p.ref} sub={fornecedorDe(p.forn) + " · " + p.loc} badge={bad("documento", ST[p.status])}
        secoes={[
          { t: "Pedido", c: <Def pares={[["Data", p.data], ["Local", p.loc], ["Fornecedor", fornecedorDe(p.forn)], ["Adicionado por", p.quem], ["Status do envio", ENVIO[p.envio]], ["Origem", org ? "Requisição " + org.ref + " (" + org.quem + ")" : "Criado direto em Compras"]]} /> },
          { t: "Itens (" + p.itens + ")", c: <Itens linhas={itensDe(p.ref, p.itens, p.total)} total={p.total} /> },
          { t: "Quantidade", c: <p className="cu-nota">{"Pedido " + un(p.qtd) + " · comprado " + un(p.recebida) + " · restante " + un(restante(p)) + ". A quantidade restante é o que a lista mostra; o valor do pedido (" + brl(p.total) + ") só aparece aqui, como no vivo."}</p> },
          aberto ? { t: "Alterar status", c: <div className="cu-dr-acoes">{Object.keys(ST).filter((k) => k !== p.status).map((k) => <button key={k} className="os-btn sm" onClick={() => mudarStatus(p, k)}>{ST[k]}</button>)}<span className="cu-nota">Só administrador muda status, e nunca a partir de Concluído.</span></div> } : null,
        ]}
        acoes={<div className="cu-dr-acoes">
          {aberto && <button className="os-btn primary" onClick={() => criarCompra(p)}><Ic name="archive" size={13} /> Criar compra deste pedido</button>}
          <button className="os-btn" onClick={() => avisar("PDF de " + p.ref + " gerado.", "ok")}>Baixar PDF</button>
          <div className="sp" />
          <button className="os-btn danger" onClick={() => excluirPO(p)}>Excluir</button>
        </div>} />;
    }
    if (sel.k === "req") {
      const r = sel.d, po = pedidos.find((p) => p.ref === r.po);
      return <Painel aberto onClose={fechar} titulo={r.ref} sub={r.quem + " · " + r.loc} badge={bad("documento", ST[r.status])}
        secoes={[
          { t: "Requisição", c: <Def pares={[["Data", r.data], ["Local", r.loc], ["Necessário até", r.prazo], ["Adicionado por", r.quem], ["Pedido gerado", r.po || "nenhum ainda"], ["Status do pedido", po ? ST[po.status] : "—"]]} /> },
          { t: "Motivo", c: <p className="cu-nota">{r.motivo}</p> },
          { t: "Itens requisitados (" + r.itens + ")", c: <Itens linhas={itensDe(r.ref, r.itens, r.itens * 890)} /> },
          { t: "Como vira compra", c: <p className="cu-nota">A requisição não escolhe fornecedor. Ela entra em um pedido de compra do mesmo local; o status dela é reapurado a partir das linhas já pedidas — Ordenado, Parcial, Concluído.</p> },
        ]}
        acoes={<div className="cu-dr-acoes">
          {r.status !== "concluido" && <button className="os-btn primary" onClick={() => gerarPO(r)}><Ic name="orders" size={13} /> Criar pedido com estas linhas</button>}
          {r.po && <button className="os-btn" onClick={() => { fechar(); ir("cmp-pedidos"); }}>Abrir {r.po}</button>}
          <div className="sp" />
          <button className="os-btn danger" onClick={() => excluirReq(r)}>Excluir</button>
        </div>} />;
    }
    const d = sel.d, c = compraDe(d.pai), cr = creditoDe(d);
    return <Painel aberto onClose={fechar} titulo={d.ref} sub={fornecedorDe((c || {}).supplier) + " · devolve " + d.pai} badge={bad("payment", cr.pg)}
      secoes={[
        { t: "Devolução", c: <Def pares={[["Data", d.data], ["Local", d.loc], ["Compra de origem", d.pai + (c ? " · " + c.ref : "")], ["Valor total", brl(d.total)], ["Valor devido", brl(cr.devido)]]} /> },
        { t: "Motivo", c: <p className="cu-nota">{d.motivo}</p> },
        { t: "Itens devolvidos (" + d.itens + ")", c: <Itens linhas={itensDe(d.ref, d.itens, d.total)} total={d.total} /> },
        { t: "Crédito com o fornecedor", c: <p className="cu-nota">{c ? (cr.devido ? "A compra " + d.pai + " ainda tem " + brl(c.due) + " em aberto, então o crédito de " + brl(d.total) + " abate o que se deve — não volta em dinheiro." : "Compra quitada: o fornecedor devolveu " + brl(d.total) + ".") : "Compra de origem não encontrada no módulo Compras."}</p> },
      ]}
      acoes={<div className="cu-dr-acoes">
        <button className="os-btn" onClick={() => avisar(d.ref + " na impressora.", "ok")}>Impressão</button>
        <button className="os-btn" onClick={() => { fechar(); ir("compras"); }}>Ver {d.pai}</button>
        <div className="sp" />
        <button className="os-btn danger" onClick={() => excluirDev(d)}>Excluir</button>
      </div>} />;
  };

  const TITULOS = { "cmp-pedidos": "Pedidos de compra", "cmp-requisicoes": "Requisições de compra", "cmp-devolucoes": "Devoluções de compra" };
  return (
    <div className="pb-root vb-root" data-screen-label={"Compras · " + (TITULOS[view] || view)} ref={boxRef}>
      {M.Header &&
        <M.Header modulo="Compras" papel={TITULOS[view] || view}
          contexto={["OFFICEIMPRESSO", "matriz", pedidos.filter((p) => p.status !== "concluido").length + " pedidos em aberto · " + reqs.filter((r) => r.status === "ordenado").length + " requisições sem pedido"]}
          atualizadoAs={new Date().toLocaleTimeString("pt-BR", { hour: "2-digit", minute: "2-digit" })}
          glyph={<Ic name="archive" />}
          acoes={<button className="os-btn" onClick={() => ir("compras")}>Compras recebidas</button>} />}
      <div className="pb-body">
        <nav className="cli-moduletopnav vb-nav" aria-label="Telas de Compras">
          {Object.keys(TITULOS).map((k) => (
            <button key={k} className={"cli-moduletopnav-tab " + (view === k ? "active" : "")} onClick={() => ir(k)}>{TITULOS[k]}</button>
          ))}
        </nav>
        {!Grade ? <p className="pb-help">A base do catch-up (catchup-shared.jsx) não carregou.</p> :
          view === "cmp-pedidos" ? telaPedidos() : view === "cmp-requisicoes" ? telaReqs() : telaDevs()}
      </div>
      {painel()}
      {avisoNode}
    </div>
  );
}

window.ComprasExtrasPage = ComprasExtrasPage;
})();
