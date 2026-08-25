// financeiro-legado.jsx — as telas do Financeiro que o menu do legado tem e o protótipo
// só listava como destino sem rota (FIN_SUBNAV_OVERFLOW), depois das ondas 1-3 de refino.
//   expense/index.blade.php ....... Despesas (colunas literais do blade)
//   expense_category .............. Categorias de despesa
//   account/index.blade.php ....... Contas bancárias (+ contas de capital) e Extrato
//   transaction_payment ........... Contas a pagar · Contas a receber
// Refino: (1) nada de número paralelo — "a pagar" é DERIVADO das compras do módulo Compras
// e das despesas desta tela, o gasto por categoria é derivado das despesas, o saldo da conta
// e o extrato derivam dos mesmos movimentos; (2) baixa de verdade: registrar pagamento muda
// o título, a despesa, o extrato e o saldo juntos; (3) teclado j/k · ↵ · / · d.
// Expõe window.FinanceiroLegadoPage.
(() => {
const { useState, useRef } = React;
const DS = () => window.OfficeImpressoPontoWR2DesignSystem_019dd0 || {};
const UI = () => window.PBUI || {};
const MP = () => window.ModuloPadrao || {};
const CU = () => window.CatchupUI || {};
const Ic = ({ name, size = 14 }) => { const F = (window.I || {})[name]; return F ? <F size={size} /> : null; };
// Sinal antes do símbolo (canon do DS): "− R$ 8.420,00", nunca "R$ -8.420,00".
const brl = (n) => (Number(n) < 0 ? "− " : "") + "R$ " + Math.abs(Number(n) || 0).toLocaleString("pt-BR", { minimumFractionDigits: 2, maximumFractionDigits: 2 });

const CAT_DESPESA = [
  { id: 1, nome: "Aluguel", cod: "ALU", sub: ["Matriz", "Filial Centro"] },
  { id: 2, nome: "Energia e água", cod: "ENE", sub: ["Energia", "Água"] },
  { id: 3, nome: "Insumos de produção", cod: "INS", sub: ["Tintas", "Mídias"] },
  { id: 4, nome: "Frota", cod: "FRO", sub: ["Combustível", "Manutenção"] },
  { id: 5, nome: "Impostos e taxas", cod: "IMP", sub: [] },
];
// Campos na ordem do expense/index vivo: recorrência, imposto e "adicionado por" existem na
// grade do legado e faltavam no F1.
const DESPESAS0 = [
  { id: 1, data: "21/08/2026", venc: "21/08/2026", ref: "DSP-2026-0231", cat: "Insumos de produção", sub: "Tintas", loc: "Matriz", total: 1840, pago: 1840, imposto: 0, recorrencia: null, para: "Wagner Ramos", contato: "Coral Tintas", conta: "Banco do Brasil", quem: "Wagner Ramos", nota: "Tinta solvente CMYK — 4 galões." },
  { id: 2, data: "20/08/2026", venc: "20/08/2026", ref: "DSP-2026-0230", cat: "Frota", sub: "Combustível", loc: "Matriz", total: 420, pago: 420, imposto: 0, recorrencia: null, para: "Douglas (motoboy)", contato: "Posto Rota", conta: "Caixa balcão", quem: "Larissa Prado", nota: "" },
  { id: 3, data: "18/08/2026", venc: "25/08/2026", ref: "DSP-2026-0229", cat: "Aluguel", sub: "Filial Centro", loc: "Filial Centro", total: 3200, pago: 0, imposto: 0, recorrencia: "a cada 1 mês · vence dia 25", para: "—", contato: "Imobiliária Central", conta: "Banco Inter", quem: "Eliana Souza", nota: "Aluguel da filial." },
  { id: 4, data: "15/08/2026", venc: "19/08/2026", ref: "DSP-2026-0227", cat: "Energia e água", sub: "Energia", loc: "Matriz", total: 1980, pago: 1000, imposto: 178.2, recorrencia: "a cada 1 mês", para: "—", contato: "Elektro", conta: "Banco Inter", quem: "Eliana Souza", nota: "Parcelado em 2×." },
];
// account/index vivo: a grade tem Tipo de conta (grupo) e Subtipo, "Detalhes da conta",
// "Adicionado por", e um filtro Ativa/Fechada — conta com movimento não se exclui, fecha.
// A aba "Contas de capital" está comentada no blade; a segunda aba viva é "Tipos de conta".
const CONTAS = [
  { id: 1, nome: "Caixa balcão", num: "—", tipo: "Ativo", sub: "Caixa", nota: "Gaveta da loja — conferido no fechamento do turno.", detalhes: "Conferência no fechamento do turno", quem: "Wagner Ramos", base: 1286.9, situacao: "Ativa" },
  { id: 2, nome: "Banco Inter", num: "077 · ag 0001 · cc 41882-3", tipo: "Ativo", sub: "Conta corrente", nota: "Recebimento de Pix e boleto.", detalhes: "Pix e boleto · conciliação diária", quem: "Wagner Ramos", base: 48210.44, situacao: "Ativa" },
  { id: 3, nome: "Banco do Brasil", num: "001 · ag 1234 · cc 5678-9", tipo: "Ativo", sub: "Conta corrente", nota: "Folha e impostos.", detalhes: "Folha, DAS e FGTS", quem: "Eliana Souza", base: 15740.2, situacao: "Ativa" },
  { id: 4, nome: "Capital de giro", num: "—", tipo: "Patrimônio", sub: "Conta de capital", nota: "Aporte dos sócios — não movimenta venda.", detalhes: "Aporte 2026 dos sócios", quem: "Wagner Ramos", base: 60000, situacao: "Ativa" },
  { id: 5, nome: "Santander (antiga)", num: "033 · ag 3312 · cc 90211-7", tipo: "Ativo", sub: "Conta corrente", nota: "Conta encerrada em jun/26 — histórico preservado.", detalhes: "Fechada, não recebe movimento", quem: "Wagner Ramos", base: 0, situacao: "Fechada" },
];
const EXTRATO_BASE = [
  { id: "e1", data: "22/08/2026", conta: "Banco Inter", tipo: "Entrada", desc: "Recebimento Pix · VD-2026-4821", ref: "PG-4821", valor: 3120, saldo: 48210.44 },
  { id: "e2", data: "22/08/2026", conta: "Caixa balcão", tipo: "Entrada", desc: "Venda em dinheiro · POS-2026-0481", ref: "PG-0481", valor: 486.9, saldo: 1286.9 },
  { id: "e3", data: "21/08/2026", conta: "Banco Inter", tipo: "Saída", desc: "Pagamento fornecedor · COMP-2846", ref: "PG-C846", valor: -3240, saldo: 45090.44 },
  { id: "e4", data: "21/08/2026", conta: "Banco do Brasil", tipo: "Saída", desc: "Despesa · DSP-2026-0231", ref: "DSP-0231", valor: -1840, saldo: 15740.2 },
  { id: "e5", data: "20/08/2026", conta: "Caixa balcão", tipo: "Saída", desc: "Sangria para o cofre", ref: "SNG-0044", valor: -1000, saldo: 800 },
];
const RECEBER0 = [
  { id: "r1", tipo: "receber", venc: "25/08/2026", doc: "VD-2026-4818", contato: "Supermercado Bom Dia", cat: "Venda", total: 5490, pago: 2000 },
  { id: "r2", tipo: "receber", venc: "16/08/2026", doc: "VD-2026-4805", contato: "Prefeitura de Jaú", cat: "Venda", total: 12480, pago: 0 },
  { id: "r3", tipo: "receber", venc: "02/09/2026", doc: "VD-2026-4814", contato: "Agência Norte", cat: "Venda", total: 640, pago: 0 },
];
// Vencimento das compras: o mock do módulo Compras não guarda a data de vencimento, então
// ela vive aqui — uma vez, e não repetida em cada linha da grade.
const VENC_COMPRA = { "COMP-2847": "07/09/2026", "COMP-2844": "28/08/2026", "COMP-2845": "31/08/2026", "COMP-2843": "24/08/2026" };
// Estado fora do componente: trocar de aba remonta a página, e uma baixa registrada não pode
// desaparecer na navegação (o extrato e o saldo têm de continuar contando o movimento).
const STORE = { despesas: DESPESAS0, receber: RECEBER0, pagoCompra: {}, movs: [] };
const CM = () => window.COMPRAS_MOCK || { SUPPLIERS: {}, PURCHASES: [] };
const fornDe = (cod) => (CM().SUPPLIERS[cod] || {}).name || cod || "—";

function FinanceiroLegadoPage({ view = "fin-receber" }) {
  const M = MP();
  const { Widget, Kebab } = UI();
  const { StatusBadge, Alert } = DS();
  const { Grade, Toolbar, Kpis, Painel, Def, Itens, useNav, itensDe, diasAte } = CU();
  const [, tick] = useState(0);
  const despesas = STORE.despesas, receber = STORE.receber, pagoCompra = STORE.pagoCompra, movs = STORE.movs;
  const setDespesas = (fn) => { STORE.despesas = fn(STORE.despesas); tick((t) => t + 1); };
  const setReceber = (fn) => { STORE.receber = fn(STORE.receber); tick((t) => t + 1); };
  const setPagoCompra = (fn) => { STORE.pagoCompra = fn(STORE.pagoCompra); tick((t) => t + 1); };
  const setMovs = (fn) => { STORE.movs = fn(STORE.movs); tick((t) => t + 1); };
  const [busca, setBusca] = useState("");
  const [densa, setDensa] = useState(false);
  const [sel, setSel] = useState(null);
  const [contaSit, setContaSit] = useState("Ativa");
  const [avisoNode, avisar] = M.useAviso ? M.useAviso() : [null, () => {}];
  const boxRef = useRef(null), buscaRef = useRef(null);
  if (useNav) useNav(boxRef, buscaRef, setDensa);
  const ir = (r) => window.__selectRoute && window.__selectRoute(r);
  const bad = (kind, v) => StatusBadge ? <StatusBadge kind={kind} value={v} /> : v;
  const casa = (s) => !busca || String(s).toLowerCase().includes(busca.toLowerCase());
  const fechar = () => setSel(null);

  // ── derivações ────────────────────────────────────────────────────────────
  const saldoDe = (nome) => {
    const c = CONTAS.find((x) => x.nome === nome);
    return (c ? c.base : 0) + movs.filter((m) => m.conta === nome).reduce((a, m) => a + m.valor, 0);
  };
  const extrato = () => movs.concat(EXTRATO_BASE);
  // A pagar não é uma lista à mão: é toda compra com saldo no módulo Compras + toda despesa
  // com valor devido. Baixar em qualquer tela mexe na mesma fonte.
  const pagar = () => {
    const compras = (CM().PURCHASES || []).filter((c) => c.due > 0).map((c) => {
      const pago = c.paid + (pagoCompra[c.id] || 0);
      return { id: "c" + c.id, tipo: "pagar", origem: "compra", doc: c.id, ref: c.ref, contato: fornDe(c.supplier), cat: "Compra", total: c.total, pago, venc: VENC_COMPRA[c.id] || c.date, itens: c.items, _c: c };
    }).filter((t) => t.total - t.pago > 0.005);
    const desp = despesas.filter((d) => d.total - d.pago > 0.005).map((d) => ({ id: "d" + d.id, tipo: "pagar", origem: "despesa", doc: d.ref, contato: d.contato, cat: d.cat, total: d.total, pago: d.pago, venc: d.venc, _d: d }));
    return compras.concat(desp);
  };
  const titulos = (tipo) => (tipo === "receber" ? receber.filter((t) => t.total - t.pago > 0.005).map((t) => ({ ...t, origem: "venda" })) : pagar())
    .map((t) => ({ ...t, dias: diasAte(t.venc), saldo: +(t.total - t.pago).toFixed(2) }))
    .sort((a, b) => a.dias - b.dias);
  const gastoCat = (nome) => despesas.filter((d) => d.cat === nome).reduce((a, d) => a + d.total, 0);
  const nCat = (nome) => despesas.filter((d) => d.cat === nome).length;

  // ── ações que mudam estado ────────────────────────────────────────────────
  const lancarMov = (conta, valor, desc, ref) => {
    const saldo = +(saldoDe(conta) + valor).toFixed(2);
    setMovs((L) => [{ id: "m" + Date.now(), data: "22/08/2026", conta, tipo: valor < 0 ? "Saída" : "Entrada", desc, ref, valor, saldo }].concat(L));
  };
  const baixar = (t) => {
    const v = t.saldo, conta = t.origem === "despesa" ? (t._d.conta || "Banco Inter") : "Banco Inter";
    if (t.origem === "compra") setPagoCompra((m) => ({ ...m, [t.doc]: (m[t.doc] || 0) + v }));
    if (t.origem === "despesa") setDespesas((L) => L.map((d) => d.id === t._d.id ? { ...d, pago: d.total } : d));
    if (t.origem === "venda") setReceber((L) => L.map((r) => r.id === t.id ? { ...r, pago: r.total } : r));
    lancarMov(t.origem === "venda" ? "Banco Inter" : conta, t.origem === "venda" ? v : -v, (t.origem === "venda" ? "Recebimento · " : "Pagamento · ") + t.doc, "PG-" + String(t.doc).slice(-4));
    fechar();
    avisar(brl(v) + (t.origem === "venda" ? " recebidos em " : " pagos em ") + t.doc + " — extrato e saldo da conta já contam esse movimento.", "ok");
  };
  const pagarDespesa = (d) => {
    const v = +(d.total - d.pago).toFixed(2);
    setDespesas((L) => L.map((x) => x.id === d.id ? { ...x, pago: x.total } : x));
    lancarMov(d.conta || "Banco Inter", -v, "Despesa · " + d.ref, d.ref.replace("DSP-2026-", "DSP-"));
    fechar(); avisar(brl(v) + " pagos em " + d.ref + " — o título saiu de Contas a pagar.", "ok");
  };
  const situacao = (t) => t.pago <= 0 ? "Devido" : t.pago >= t.total - 0.005 ? "Pago" : "Parcial";

  // ── Contas a pagar / a receber ────────────────────────────────────────────
  const telaTitulos = (tipo) => {
    const base = titulos(tipo);
    const rows = base.filter((t) => casa(t.doc + " " + t.contato));
    const aberto = base.reduce((a, t) => a + t.saldo, 0);
    const vencido = base.filter((t) => t.dias < 0).reduce((a, t) => a + t.saldo, 0);
    const semana = base.filter((t) => t.dias >= 0 && t.dias <= 7).reduce((a, t) => a + t.saldo, 0);
    const cols = [
      { key: "acao", label: "Ação", width: 84, resizable: false },
      { key: "venc", label: "Vencimento", width: 122, mono: true, sortable: true, sortValue: (r) => r._t.dias },
      { key: "prazo", label: "Prazo", width: 126 },
      { key: "doc", label: "Documento", width: 150, mono: true },
      { key: "contato", label: tipo === "receber" ? "Cliente" : "Fornecedor / credor", width: 210 },
      { key: "cat", label: "Categoria", width: 150 },
      { key: "total", label: "Valor", width: 128, align: "right", mono: true },
      { key: "pago", label: tipo === "receber" ? "Recebido" : "Pago", width: 126, align: "right", mono: true },
      { key: "saldo", label: "Em aberto", width: 130, align: "right", mono: true, sortable: true, sortValue: (r) => r._t.saldo },
    ];
    const linhas = rows.map((t) => ({
      id: t.id, _t: t, state: t.dias < 0 ? "urgent" : undefined,
      acao: Kebab ? <Kebab acoes={[
        { l: tipo === "receber" ? "Registrar recebimento" : "Registrar pagamento", ic: "cash", on: () => baixar(t) },
        { l: "Ver título", ic: "search", on: () => setSel({ k: "titulo", d: t }) },
        { l: "Enviar cobrança", ic: "send", on: () => avisar("Cobrança de " + t.doc + " preparada — nada é enviado sem você confirmar.", "ok") },
      ]} /> : null,
      venc: t.venc,
      prazo: bad("sla", t.dias < 0 ? Math.abs(t.dias) + "d em atraso" : t.dias === 0 ? "vence hoje" : "vence em " + t.dias + "d"),
      doc: t.doc, contato: t.contato, cat: t.cat,
      total: brl(t.total), pago: brl(t.pago), saldo: brl(t.saldo),
    }));
    return (
      <>
        <Kpis itens={[
          { l: tipo === "receber" ? "A receber" : "A pagar", v: brl(aberto), n: base.length + " título(s) em aberto" },
          { l: "Vencido", v: brl(vencido), tom: vencido ? "warn" : "pos", n: vencido ? base.filter((t) => t.dias < 0).length + " título(s) passaram da data" : "nada em atraso" },
          { l: "Próximos 7 dias", v: brl(semana), n: "vence até 29/08" },
        ]} />
        <Widget flush titulo={<><Ic name="cash" size={13} /> {tipo === "receber" ? "Contas a receber" : "Contas a pagar"}</>} nota={rows.length + " título(s)"}>
          <Toolbar busca={busca} setBusca={setBusca} buscaRef={buscaRef} ph="Buscar documento ou nome…" densa={densa} setDensa={setDensa} />
          <Grade columns={cols} rows={linhas} densa={densa} altura={280} onRowClick={(r) => setSel({ k: "titulo", d: (r.cells || r)._t })} vazio={{ t: "Nenhum título em aberto", d: tipo === "receber" ? "Tudo recebido no período." : "Nada a pagar — compras e despesas estão quitadas." }} />
          <div className="pb-pag"><span className="pb-help">{tipo === "pagar" ? "Derivado: toda compra com saldo no módulo Compras e toda despesa com valor devido viram título aqui." : "Título vem do documento de origem (venda) — não se cria título solto."}</span></div>
        </Widget>
      </>
    );
  };

  // ── Despesas ──────────────────────────────────────────────────────────────
  const telaDespesas = () => {
    const rows = despesas.filter((d) => casa(d.ref + " " + d.cat + " " + d.contato));
    const total = despesas.reduce((a, d) => a + d.total, 0);
    const devido = despesas.reduce((a, d) => a + (d.total - d.pago), 0);
    const cols = [
      { key: "acao", label: "Ação", width: 84, resizable: false },
      { key: "data", label: "Data", width: 112, mono: true },
      { key: "ref", label: "Nº de referência", width: 152, mono: true, sortable: true },
      { key: "recorrencia", label: "Detalhes de recorrência", width: 200 },
      { key: "cat", label: "Categoria de despesa", width: 176 },
      { key: "sub", label: "Subcategoria", width: 134 },
      { key: "loc", label: "Local", width: 124 },
      { key: "pg", label: "Status do pagamento", width: 152 },
      { key: "imposto", label: "Imposto", width: 112, align: "right", mono: true },
      { key: "total", label: "Valor total", width: 126, align: "right", mono: true },
      { key: "devido", label: "Valor devido", width: 128, align: "right", mono: true },
      { key: "para", label: "Despesa para", width: 154 },
      { key: "contato", label: "Contato", width: 164 },
      { key: "nota", label: "Nota de despesas", width: 220 },
      { key: "quem", label: "Adicionado por", width: 150 },
    ];
    const linhas = rows.map((d) => ({
      id: d.id, _d: d, state: d.total - d.pago > 0.005 ? "urgent" : undefined,
      acao: Kebab ? <Kebab acoes={[
        { l: "Ver despesa", ic: "search", on: () => setSel({ k: "despesa", d }) },
        ...(d.total - d.pago > 0.005 ? [{ l: "Adicionar pagamento", ic: "cash", on: () => pagarDespesa(d) }] : []),
        { l: "Duplicar", ic: "copy", on: () => avisar("Despesa duplicada a partir de " + d.ref + ".", "ok") },
        "-",
        { l: "Excluir", ic: "x", tone: "danger", on: () => avisar(d.ref + " excluída — o título em Contas a pagar sai junto.", "warn") },
      ]} /> : null,
      data: d.data, ref: d.ref, recorrencia: d.recorrencia || "não recorrente", cat: d.cat, sub: d.sub, loc: d.loc,
      pg: bad("payment", situacao(d)),
      imposto: d.imposto ? brl(d.imposto) : "—",
      total: brl(d.total), devido: d.total - d.pago > 0.005 ? brl(d.total - d.pago) : "—", para: d.para, contato: d.contato, nota: d.nota || "—", quem: d.quem,
    }));
    return (
      <>
        <Kpis itens={[
          { l: "Despesas em ago/26", v: brl(total), n: despesas.length + " lançamento(s)" },
          { l: "Ainda a pagar", v: brl(devido), tom: devido ? "warn" : "pos", n: devido ? "aparece em Contas a pagar" : "todas quitadas" },
          { l: "Categorias usadas", v: String(new Set(despesas.map((d) => d.cat)).size) + " de " + CAT_DESPESA.length, n: "categorias com lançamento no mês" },
        ]} />
        <Widget flush titulo={<><Ic name="receipt" size={13} /> Despesas</>} nota={rows.length + " de " + despesas.length}>
          <Toolbar busca={busca} setBusca={setBusca} buscaRef={buscaRef} ph="Buscar despesa, categoria ou contato…" densa={densa} setDensa={setDensa}>
            <button className="os-btn sm primary" onClick={() => avisar("Nova despesa — categoria e local são obrigatórios.", "ok")}><Ic name="plus" size={12} /> Adicionar</button>
          </Toolbar>
          <Grade columns={cols} rows={linhas} densa={densa} altura={280} onRowClick={(r) => setSel({ k: "despesa", d: (r.cells || r)._d })} />
          <div className="pb-pag">
            <span>Total: <b>{brl(rows.reduce((a, d) => a + d.total, 0))}</b> · devido <b>{brl(rows.reduce((a, d) => a + (d.total - d.pago), 0))}</b></span>
            <div className="sp" />
            <span className="pb-help">{Object.keys(rows.reduce((a, d) => { const s = situacao(d); a[s] = (a[s] || 0) + 1; return a; }, {})).map((k) => k).join(" · ") || "—"}</span>
          </div>
        </Widget>
      </>
    );
  };

  // ── Categorias de despesa ─────────────────────────────────────────────────
  const telaCategorias = () => {
    const rows = CAT_DESPESA.filter((c) => casa(c.nome));
    const cols = [
      { key: "nome", label: "Nome da categoria", width: 220, sortable: true },
      { key: "cod", label: "Código de categoria", width: 168, mono: true },
      { key: "sub", label: "Subcategorias", width: 240 },
      { key: "n", label: "Lançamentos", width: 130, align: "right", mono: true },
      { key: "mes", label: "Gasto em ago/26", width: 156, align: "right", mono: true, sortable: true, sortValue: (r) => gastoCat(r._c.nome) },
      { key: "acao", label: "Ação", width: 84, resizable: false },
    ];
    const linhas = rows.map((c) => ({
      id: c.id, _c: c,
      acao: Kebab ? <Kebab acoes={[
        { l: "Ver categoria", ic: "search", on: () => setSel({ k: "cat", d: c }) },
        { l: "Editar", ic: "pencil", on: () => avisar("Editando " + c.nome + ".", "ok") },
        { l: "Adicionar subcategoria", ic: "plus", on: () => avisar("Subcategoria nova em " + c.nome + ".", "ok") },
        "-",
        { l: "Excluir", ic: "x", tone: "danger", on: () => avisar(nCat(c.nome) ? c.nome + " tem " + nCat(c.nome) + " despesa(s) lançada(s) — o servidor recusa a exclusão." : c.nome + " excluída.", nCat(c.nome) ? "warn" : "ok") },
      ]} /> : null,
      nome: c.nome, cod: c.cod, sub: c.sub.length ? c.sub.join(" · ") : "—",
      n: nCat(c.nome) || "—", mes: gastoCat(c.nome) ? brl(gastoCat(c.nome)) : "—",
    }));
    return (
      <>
        <Kpis itens={[
          { l: "Categorias", v: String(CAT_DESPESA.length), n: new Set(despesas.map((d) => d.cat)).size + " com lançamento no mês" },
          { l: "Gasto classificado", v: brl(despesas.reduce((a, d) => a + d.total, 0)), n: "soma das despesas de ago/26" },
        ]} />
        <Widget flush titulo={<><Ic name="folder" size={13} /> Categorias de despesa</>} nota={rows.length + " categoria(s)"}>
          <Toolbar busca={busca} setBusca={setBusca} buscaRef={buscaRef} ph="Buscar categoria…" densa={densa} setDensa={setDensa}>
            <button className="os-btn sm primary" onClick={() => avisar("Nova categoria de despesa.", "ok")}><Ic name="plus" size={12} /> Adicionar</button>
          </Toolbar>
          <Grade columns={cols} rows={linhas} densa={densa} altura={240} onRowClick={(r) => setSel({ k: "cat", d: (r.cells || r)._c })} />
          <div className="pb-pag"><span className="pb-help">No legado esta grade tem só nome, código e ação; lançamentos, subcategorias e gasto do mês são derivação do F1 a partir de Despesas — categoria não guarda total próprio.</span></div>
        </Widget>
      </>
    );
  };

  // ── Contas bancárias ──────────────────────────────────────────────────────
  const telaContas = () => {
    const rows = CONTAS.filter((c) => c.situacao === contaSit && casa(c.nome + " " + c.num));
    const cols = [
      { key: "nome", label: "Nome", width: 176, sortable: true },
      { key: "tipo", label: "Tipo de conta", width: 132 },
      { key: "sub", label: "Subtipo de conta", width: 156 },
      { key: "num", label: "Número da conta", width: 222, mono: true },
      { key: "nota", label: "Observação", width: 250 },
      { key: "saldo", label: "Saldo", width: 146, align: "right", mono: true, sortable: true, sortValue: (r) => saldoDe(r._c.nome) },
      { key: "detalhes", label: "Detalhes da conta", width: 230 },
      { key: "quem", label: "Adicionado por", width: 150 },
      { key: "acao", label: "Ação", width: 84, resizable: false },
    ];
    const linhas = rows.map((c) => ({
      id: c.id, _c: c,
      acao: Kebab ? <Kebab acoes={[
        { l: "Ver conta", ic: "search", on: () => setSel({ k: "conta", d: c }) },
        { l: "Ver extrato", ic: "list", on: () => ir("fin-extrato") },
        { l: "Editar conta", ic: "pencil", on: () => avisar("Editando " + c.nome + ".", "ok") },
        "-",
        ...(c.situacao === "Ativa"
        ? [{ l: "Fechar conta", ic: "x", tone: "danger", on: () => avisar("Conta com movimento não se exclui — o legado fecha (desativa), e ela passa a aparecer no filtro Fechada.", "warn") }]
        : [{ l: "Reativar conta", ic: "refresh", on: () => avisar(c.nome + " reativada — volta a receber movimento.", "ok") }]),
      ]} /> : null,
      nome: c.nome, tipo: c.tipo, sub: c.sub, num: c.num, nota: c.nota,
      saldo: brl(saldoDe(c.nome)), detalhes: c.detalhes, quem: c.quem,
    }));
    const ativas = CONTAS.filter((c) => c.situacao === "Ativa");
    const disp = ativas.filter((c) => c.sub !== "Conta de capital").reduce((a, c) => a + saldoDe(c.nome), 0);
    return (
      <>
        <Kpis itens={[
          { l: "Disponível", v: brl(disp), n: "contas correntes e caixa, sem capital" },
          { l: "Conta de capital", v: brl(ativas.filter((c) => c.sub === "Conta de capital").reduce((a, c) => a + saldoDe(c.nome), 0)), n: "aporte dos sócios — a aba de capital está desativada no vivo" },
          { l: "Total do rodapé", v: brl(rows.reduce((a, c) => a + saldoDe(c.nome), 0)), n: "soma dos saldos com o filtro atual" },
        ]} />
        <Widget flush titulo={<><Ic name="cash" size={13} /> Contas</>} nota={rows.length + " conta(s) " + contaSit.toLowerCase()}>
          <Toolbar busca={busca} setBusca={setBusca} buscaRef={buscaRef} ph="Buscar conta…" densa={densa} setDensa={setDensa}>
            <div className="pb-seg" role="group" aria-label="Situação da conta">
              <button className={contaSit === "Ativa" ? "on" : ""} onClick={() => setContaSit("Ativa")}>Ativa</button>
              <button className={contaSit === "Fechada" ? "on" : ""} onClick={() => setContaSit("Fechada")}>Fechada</button>
            </div>
            <button className="os-btn sm primary" onClick={() => avisar("Nova conta — nome, tipo e subtipo obrigatórios.", "ok")}><Ic name="plus" size={12} /> Adicionar</button>
          </Toolbar>
          <Grade columns={cols} rows={linhas} densa={densa} altura={240} onRowClick={(r) => setSel({ k: "conta", d: (r.cells || r)._c })} />
          <div className="pb-pag"><span className="pb-help">Saldo é derivado do extrato: cada baixa registrada nesta sessão já entrou na conta. A outra aba da tela viva é <b>Tipos de conta</b>, com os subtipos aninhados.</span></div>
        </Widget>
      </>
    );
  };

  // ── Extrato ───────────────────────────────────────────────────────────────
  const telaExtrato = () => {
    const base = extrato();
    const rows = base.filter((e) => casa(e.desc + " " + e.conta + " " + e.ref));
    const ent = base.filter((e) => e.valor > 0).reduce((a, e) => a + e.valor, 0);
    const sai = base.filter((e) => e.valor < 0).reduce((a, e) => a + e.valor, 0);
    const cols = [
      { key: "data", label: "Data", width: 112, mono: true },
      { key: "conta", label: "Conta", width: 160 },
      { key: "tipo", label: "Tipo", width: 116 },
      { key: "desc", label: "Descrição", width: 300 },
      { key: "ref", label: "Referência", width: 132, mono: true },
      { key: "valor", label: "Valor", width: 136, align: "right", mono: true },
      { key: "saldo", label: "Saldo depois", width: 146, align: "right", mono: true },
    ];
    const linhas = rows.map((e) => ({
      id: e.id, _e: e,
      data: e.data, conta: e.conta, tipo: bad("tipo", e.tipo), desc: e.desc, ref: e.ref,
      valor: brl(e.valor), saldo: brl(e.saldo),
    }));
    return (
      <>
        <Kpis itens={[
          { l: "Entradas", v: brl(ent), tom: "pos", n: base.filter((e) => e.valor > 0).length + " lançamento(s)" },
          { l: "Saídas", v: brl(sai), n: base.filter((e) => e.valor < 0).length + " lançamento(s)" },
          { l: "Resultado do período", v: brl(ent + sai), tom: ent + sai >= 0 ? "pos" : "warn", n: "entradas menos saídas" },
        ]} />
        <Widget flush titulo={<><Ic name="list" size={13} /> Extrato</>} nota={rows.length + " lançamento(s)"}>
          <Toolbar busca={busca} setBusca={setBusca} buscaRef={buscaRef} ph="Buscar lançamento…" densa={densa} setDensa={setDensa}>
            <button className="os-btn sm" onClick={() => avisar("Extrato exportado em CSV com o filtro atual.", "ok")}><Ic name="sheet" size={12} /> Exportar</button>
          </Toolbar>
          <Grade columns={cols} rows={linhas} densa={densa} altura={280} onRowClick={(r) => setSel({ k: "mov", d: (r.cells || r)._e })} />
          <div className="pb-pag"><span className="pb-help">O extrato é derivado: cada linha aponta o documento que a gerou — venda, compra, despesa ou sangria.</span></div>
        </Widget>
      </>
    );
  };

  // ── Drawer de detalhe (PT-02) ─────────────────────────────────────────────
  const painel = () => {
    if (!sel || !Painel) return null;
    if (sel.k === "titulo") {
      const t = sel.d;
      return <Painel aberto onClose={fechar} titulo={t.doc} sub={t.contato + " · " + t.cat} badge={bad("payment", situacao(t))}
        secoes={[
          { t: "Título", c: <Def pares={[["Vencimento", t.venc + (t.dias < 0 ? " (" + Math.abs(t.dias) + "d em atraso)" : "")], ["Origem", t.origem === "compra" ? "Compra " + t.doc + (t.ref ? " · " + t.ref : "") : t.origem === "despesa" ? "Despesa lançada em " + t.doc : "Venda " + t.doc], ["Valor", brl(t.total)], ["Em aberto", brl(t.saldo)]]} /> },
          t.origem === "compra" && t.itens ? { t: "Itens da compra (" + t.itens + ")", c: <Itens linhas={itensDe(t.doc, t.itens, t.total)} total={t.total} /> } : null,
          { t: "O que a baixa faz", c: <p className="cu-nota">{"Registrar " + (t.tipo === "receber" ? "recebimento" : "pagamento") + " de " + brl(t.saldo) + " lança um movimento em " + (t.origem === "despesa" ? (t._d.conta || "Banco Inter") : "Banco Inter") + ", muda o saldo da conta e tira o título desta lista. O documento de origem continua no lugar — nada é apagado."}</p> },
        ]}
        acoes={<div className="cu-dr-acoes">
          <button className="os-btn primary" onClick={() => baixar(t)}><Ic name="cash" size={13} /> {t.tipo === "receber" ? "Registrar recebimento" : "Registrar pagamento"}</button>
          <button className="os-btn" onClick={() => { fechar(); ir(t.origem === "compra" ? "compras" : t.origem === "despesa" ? "fin-despesas" : "venda-todas"); }}>Ver documento</button>
        </div>} />;
    }
    if (sel.k === "despesa") {
      const d = sel.d, falta = +(d.total - d.pago).toFixed(2);
      return <Painel aberto onClose={fechar} titulo={d.ref} sub={d.cat + " · " + d.sub} badge={bad("payment", situacao(d))}
        secoes={[
          { t: "Despesa", c: <Def pares={[["Data", d.data], ["Vencimento", d.venc], ["Local", d.loc], ["Contato", d.contato], ["Despesa para", d.para], ["Conta de pagamento", d.conta]]} /> },
          { t: "Valores", c: <Def pares={[["Valor total", brl(d.total)], ["Pago", brl(d.pago)], ["A pagar", falta ? brl(falta) : "—"], ["Em Contas a pagar", falta ? "sim, como título " + d.ref : "não — quitada"]]} /> },
          d.nota ? { t: "Observação", c: <p className="cu-nota">{d.nota}</p> } : null,
        ]}
        acoes={<div className="cu-dr-acoes">
          {falta > 0 && <button className="os-btn primary" onClick={() => pagarDespesa(d)}><Ic name="cash" size={13} /> Pagar {brl(falta)}</button>}
          <button className="os-btn" onClick={() => avisar("Editando " + d.ref + ".", "ok")}>Editar</button>
          <div className="sp" />
          {falta > 0 && <button className="os-btn" onClick={() => { fechar(); ir("fin-pagar"); }}>Ver em Contas a pagar</button>}
        </div>} />;
    }
    if (sel.k === "cat") {
      const c = sel.d, lista = despesas.filter((d) => d.cat === c.nome);
      return <Painel aberto onClose={fechar} titulo={c.nome} sub={"Código " + c.cod} largura={480}
        secoes={[
          { t: "Categoria", c: <Def pares={[["Código", c.cod], ["Subcategorias", c.sub.length ? c.sub.join(" · ") : "nenhuma"], ["Lançamentos no mês", String(lista.length)], ["Gasto em ago/26", brl(gastoCat(c.nome))]]} /> },
          { t: "Despesas nesta categoria", c: lista.length
            ? <Itens linhas={lista.map((d) => ({ nome: d.ref + " · " + (d.sub || d.loc), qtd: 1, preco: d.total, sub: d.total }))} total={gastoCat(c.nome)} />
            : <p className="cu-nota">Nenhuma despesa lançada nesta categoria no mês — por isso ela ainda pode ser excluída.</p> },
        ]}
        acoes={<div className="cu-dr-acoes"><button className="os-btn" onClick={() => { fechar(); ir("fin-despesas"); }}>Abrir Despesas</button></div>} />;
    }
    if (sel.k === "conta") {
      const c = sel.d, linhas = extrato().filter((e) => e.conta === c.nome);
      return <Painel aberto onClose={fechar} titulo={c.nome} sub={c.sub + (c.num !== "—" ? " · " + c.num : "")} largura={520}
        secoes={[
          { t: "Conta", c: <Def pares={[["Tipo de conta", c.tipo], ["Subtipo", c.sub], ["Número", c.num], ["Situação", c.situacao], ["Saldo atual", brl(saldoDe(c.nome))], ["Movimentos", String(linhas.length)], ["Adicionado por", c.quem], ["Detalhes", c.detalhes]]} /> },
          { t: "Observação", c: <p className="cu-nota">{c.nota}</p> },
          { t: "Últimos movimentos", c: linhas.length
            ? <Itens linhas={linhas.slice(0, 6).map((e) => ({ nome: e.data + " · " + e.desc, qtd: 1, preco: e.valor, sub: e.valor }))} total={linhas.reduce((a, e) => a + e.valor, 0)} />
            : <p className="cu-nota">Sem movimento no período.</p> },
        ]}
        acoes={<div className="cu-dr-acoes"><button className="os-btn" onClick={() => { fechar(); ir("fin-extrato"); }}>Ver extrato completo</button></div>} />;
    }
    const e = sel.d;
    return <Painel aberto onClose={fechar} titulo={e.ref} sub={e.conta + " · " + e.data} badge={bad("tipo", e.tipo)} largura={460}
      secoes={[
        { t: "Movimento", c: <Def pares={[["Data", e.data], ["Conta", e.conta], ["Tipo", e.tipo], ["Valor", brl(e.valor)], ["Saldo depois", brl(e.saldo)]]} /> },
        { t: "De onde vem", c: <p className="cu-nota">{e.desc + ". O extrato não se digita: ele é o rastro do documento que gerou o movimento."}</p> },
      ]}
      acoes={<div className="cu-dr-acoes"><button className="os-btn" onClick={() => { fechar(); ir("fin-bancos"); }}>Ver a conta</button></div>} />;
  };

  const TITULOS = {
    "fin-receber": "Contas a receber", "fin-pagar": "Contas a pagar", "fin-despesas": "Despesas",
    "fin-categorias": "Categorias de despesas", "fin-bancos": "Contas de pagamento", "fin-extrato": "Extrato",
  };
  const emAberto = pagar().reduce((a, t) => a + (t.total - t.pago), 0);
  return (
    <div className="pb-root vb-root" data-screen-label={"Financeiro · " + (TITULOS[view] || view)} ref={boxRef}>
      {M.Header &&
        <M.Header modulo="Financeiro" papel={TITULOS[view] || view}
          contexto={["OFFICEIMPRESSO", "matriz", brl(emAberto) + " a pagar em aberto"]}
          atualizadoAs={new Date().toLocaleTimeString("pt-BR", { hour: "2-digit", minute: "2-digit" })}
          glyph={<Ic name="cash" />}
          acoes={<button className="os-btn" onClick={() => ir("financeiro")}>Visão unificada</button>} />}
      <div className="pb-body">
        <nav className="cli-moduletopnav vb-nav" aria-label="Telas do Financeiro">
          {Object.keys(TITULOS).map((k) => (
            <button key={k} className={"cli-moduletopnav-tab " + (view === k ? "active" : "")} onClick={() => ir(k)}>{TITULOS[k]}</button>
          ))}
        </nav>
        {!Grade ? <p className="pb-help">A base do catch-up (catchup-shared.jsx) não carregou.</p> :
          view === "fin-receber" ? telaTitulos("receber") : view === "fin-pagar" ? telaTitulos("pagar") :
          view === "fin-despesas" ? telaDespesas() : view === "fin-categorias" ? telaCategorias() :
          view === "fin-bancos" ? telaContas() : telaExtrato()}
      </div>
      {painel()}
      {avisoNode}
    </div>
  );
}

window.FinanceiroLegadoPage = FinanceiroLegadoPage;
})();
