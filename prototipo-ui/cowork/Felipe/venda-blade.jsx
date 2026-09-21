// venda-blade.jsx — Módulo VENDA importado dos blades do legado (menu "Vendas" do sidebar.blade.php).
// Tradução 1:1 dos blades que ainda não existiam no Cockpit V2 — nada de tela inventada:
//   sale_pos/index.blade.php ........... "lista de POS"      → view "pos"
//   sale_pos/draft.blade.php ........... "Lista de rascunhos"→ view "rascunhos"
//   sale_pos/quotations.blade.php ...... "Lista de compromissos" (cotações) → view "cotacoes"
//   sell/shipments.blade.php ........... "Remessas"          → view "remessas"
//   discount/index + discount/create ... "Descontos"         → view "descontos"
//   sale_pos/subscriptions.blade.php ... "Assinaturas"       → view "assinaturas"
//   import_sales/index.blade.php ....... "Importação de vendas" → view "importar"
// Os itens que JÁ existem no protótipo NÃO foram refeitos (Todas as vendas, Adicionar venda,
// POS, lista de devolução): as rotas apontam pro VendasModule vivo (vendas-extras.jsx).
// Pele: reusa as classes .pb-* de produto-blade.css (mesma importação de blade) e window.PBUI.
// Expõe window.VendaBladePage + window.VBD (mock compartilhado).
(() => {
const { useState, useMemo, useEffect } = React;
const MP = () => window.ModuloPadrao || {};
const DS = () => window.OfficeImpressoPontoWR2DesignSystem_019dd0 || {};
const UI = () => window.PBUI || {};
const Ic = ({ name, size = 14 }) => { const F = (window.I || {})[name]; return F ? <F size={size} /> : null; };

// ─────────── Domínio (selects dos blades) ───────────
const LOCAIS = [{ id: 1, name: "Matriz" }, { id: 2, name: "Filial Centro" }];
const VENDEDORES = ["Larissa Prado", "Wagner Ramos", "Eliana Souza", "Marcos Vinícius"];
const CLIENTES = ["Rota Livre Transportes", "Martinho Oficina", "Prefeitura de Jaú", "Supermercado Bom Dia", "Agência Norte", "Cliente balcão"];
const PGTO = [{ id: "paid", name: "Pago" }, { id: "due", name: "Devido" }, { id: "partial", name: "Parcial" }, { id: "overdue", name: "Vencido" }];
const ENVIO = [{ id: "ordered", name: "Pedido" }, { id: "packed", name: "Embalado" }, { id: "shipped", name: "Enviado" }, { id: "delivered", name: "Entregue" }, { id: "cancelled", name: "Cancelado" }];
const ENTREGADORES = ["Motoboy Douglas", "Transportadora Rota Livre", "Retirada no balcão", "Equipe de instalação"];
const FORMAS = ["Dinheiro", "Pix", "Cartão de débito", "Cartão de crédito", "Boleto", "Múltiplas"];
const SERVICOS = ["Balcão", "Entrega", "Instalação"];
const INTERVALOS = [{ id: "days", name: "Dias" }, { id: "months", name: "Meses" }, { id: "years", name: "Anos" }];

const fmtBRL = (n) => n == null ? "—" : "R$ " + Number(n).toLocaleString("pt-BR", { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const dia = (d) => { const x = new Date(); x.setDate(x.getDate() - d); return String(x.getDate()).padStart(2, "0") + "/" + String(x.getMonth() + 1).padStart(2, "0") + "/" + x.getFullYear(); };
const hora = (h, m) => String(h).padStart(2, "0") + ":" + String(m).padStart(2, "0");

// Vendas de POS (transaction: is_direct_sale = 0) — colunas do partials/sales_table.blade.php.
const POS = [
  { id: 1, data: dia(0) + " " + hora(9, 12), inv: "POS-2026-0481", cli: "Cliente balcão", tel: "—", loc: "Matriz", pg: "paid", forma: "Pix", total: 486.9, pago: 486.9, saldo: 0, devDev: 0, envio: "delivered", itens: 3, serv: "Balcão", quem: "Larissa Prado", obs: "" },
  { id: 2, data: dia(0) + " " + hora(10, 34), inv: "POS-2026-0482", cli: "Rota Livre Transportes", tel: "(14) 99812-4410", loc: "Matriz", pg: "partial", forma: "Múltiplas", total: 3120, pago: 1500, saldo: 1620, devDev: 0, envio: "packed", itens: 8, serv: "Entrega", quem: "Larissa Prado", obs: "Retira sexta, 14h." },
  { id: 3, data: dia(1) + " " + hora(16, 5), inv: "POS-2026-0479", cli: "Agência Norte", tel: "(14) 3622-1180", loc: "Filial Centro", pg: "due", forma: "Boleto", total: 1875.4, pago: 0, saldo: 1875.4, envio: "ordered", devDev: 0, itens: 5, serv: "Balcão", quem: "Marcos Vinícius", obs: "" },
  { id: 4, data: dia(1) + " " + hora(11, 48), inv: "POS-2026-0478", cli: "Martinho Oficina", tel: "(14) 99640-2299", loc: "Matriz", pg: "paid", forma: "Cartão de crédito", total: 742, pago: 742, saldo: 0, devDev: 120, envio: "delivered", itens: 2, serv: "Balcão", quem: "Larissa Prado", obs: "Devolução parcial de 1 banner." },
  { id: 5, data: dia(2) + " " + hora(15, 21), inv: "POS-2026-0475", cli: "Supermercado Bom Dia", tel: "(14) 3624-7788", loc: "Matriz", pg: "overdue", forma: "Boleto", total: 5490, pago: 2000, saldo: 3490, devDev: 0, envio: "shipped", itens: 12, serv: "Instalação", quem: "Wagner Ramos", obs: "" },
  { id: 6, data: dia(3) + " " + hora(9, 3), inv: "POS-2026-0470", cli: "Prefeitura de Jaú", tel: "(14) 3602-9000", loc: "Filial Centro", pg: "due", forma: "Boleto", total: 12480, pago: 0, saldo: 12480, envio: "ordered", devDev: 0, itens: 24, serv: "Entrega", quem: "Wagner Ramos", obs: "Empenho 2026/114." },
  { id: 7, data: dia(4) + " " + hora(17, 42), inv: "POS-2026-0466", cli: "Cliente balcão", tel: "—", loc: "Matriz", pg: "paid", forma: "Dinheiro", total: 98.5, pago: 98.5, saldo: 0, devDev: 0, envio: "delivered", itens: 1, serv: "Balcão", quem: "Larissa Prado", obs: "" },
  { id: 8, data: dia(5) + " " + hora(13, 27), inv: "POS-2026-0461", cli: "Agência Norte", tel: "(14) 3622-1180", loc: "Filial Centro", pg: "paid", forma: "Cartão de débito", total: 2210, pago: 2210, saldo: 0, devDev: 0, envio: "delivered", itens: 6, serv: "Entrega", quem: "Marcos Vinícius", obs: "" },
];

// Rascunhos e cotações — mesma tabela do blade (/sells/draft-dt?is_quotation=0|1).
const RASCUNHOS = [
  { id: 21, data: dia(0) + " " + hora(8, 40), ref: "RASC-2026-0044", cli: "Rota Livre Transportes", tel: "(14) 99812-4410", loc: "Matriz", itens: 6, quem: "Larissa Prado", total: 4380 },
  { id: 22, data: dia(1) + " " + hora(14, 12), ref: "RASC-2026-0043", cli: "Martinho Oficina", tel: "(14) 99640-2299", loc: "Matriz", itens: 2, quem: "Larissa Prado", total: 690 },
  { id: 23, data: dia(2) + " " + hora(10, 2), ref: "RASC-2026-0042", cli: "Supermercado Bom Dia", tel: "(14) 3624-7788", loc: "Filial Centro", itens: 11, quem: "Marcos Vinícius", total: 8120 },
  { id: 24, data: dia(6) + " " + hora(16, 55), ref: "RASC-2026-0041", cli: "Cliente balcão", tel: "—", loc: "Matriz", itens: 1, quem: "Larissa Prado", total: 149.9 },
];
const COTACOES = [
  { id: 31, data: dia(0) + " " + hora(11, 20), ref: "COT-2026-0192", cli: "Prefeitura de Jaú", tel: "(14) 3602-9000", loc: "Filial Centro", itens: 18, quem: "Wagner Ramos", total: 23890, validade: dia(-15) },
  { id: 32, data: dia(1) + " " + hora(9, 5), ref: "COT-2026-0191", cli: "Agência Norte", tel: "(14) 3622-1180", loc: "Matriz", itens: 4, quem: "Marcos Vinícius", total: 3260, validade: dia(-9) },
  { id: 33, data: dia(3) + " " + hora(15, 44), ref: "COT-2026-0190", cli: "Supermercado Bom Dia", tel: "(14) 3624-7788", loc: "Matriz", itens: 9, quem: "Larissa Prado", total: 11450, validade: dia(-7) },
  { id: 34, data: dia(8) + " " + hora(10, 31), ref: "COT-2026-0187", cli: "Rota Livre Transportes", tel: "(14) 99812-4410", loc: "Matriz", itens: 3, quem: "Wagner Ramos", total: 1890, validade: dia(-2) },
];

const REMESSAS = POS.filter((s) => s.envio !== "delivered" || s.serv !== "Balcão").map((s, i) => ({
  ...s, entregador: ENTREGADORES[i % ENTREGADORES.length], atendente: s.quem,
  rastreio: i % 2 ? "BR" + (881204410 + i * 7) + "OI" : "", docs: i % 3 === 0 ? "Romaneio + NF-e" : "NF-e",
}));

const DESCONTOS = [
  { id: 1, nome: "Semana da comunicação visual", inicio: dia(3), fim: dia(-4), tipo: "percentage", valor: 12, prio: 1, marca: "Vinilcor", cat: "Comunicação visual", produtos: 14, loc: "Matriz", ativo: true },
  { id: 2, nome: "Adesivo de recorte — atacado", inicio: dia(20), fim: dia(-40), tipo: "percentage", valor: 8, prio: 2, marca: "Avery", cat: "Adesivos", produtos: 6, loc: "Todos", ativo: true },
  { id: 3, nome: "Queima de banner 440g", inicio: dia(45), fim: dia(9), tipo: "fixed", valor: 15, prio: 3, marca: "Sem marca", cat: "Comunicação visual", produtos: 2, loc: "Filial Centro", ativo: false },
  { id: 4, nome: "Cartão de visita 1.000 un", inicio: dia(2), fim: dia(-28), tipo: "fixed", valor: 20, prio: 1, marca: "Suprema", cat: "Impressos", produtos: 3, loc: "Matriz", ativo: true },
];

const ASSINATURAS = [
  { id: 1, data: dia(60), num: "ASS-0031", cli: "Rota Livre Transportes", loc: "Matriz", intervalo: "1 mês", repet: 12, geradas: 4, ultima: dia(4), proxima: dia(-26), valor: 1890 },
  { id: 2, data: dia(120), num: "ASS-0028", cli: "Supermercado Bom Dia", loc: "Matriz", intervalo: "3 meses", repet: 8, geradas: 2, ultima: dia(28), proxima: dia(-62), valor: 5400 },
  { id: 3, data: dia(200), num: "ASS-0019", cli: "Prefeitura de Jaú", loc: "Filial Centro", intervalo: "1 ano", repet: 3, geradas: 1, ultima: dia(160), proxima: dia(-205), valor: 24800 },
];

const IMPORTACOES = [
  { lote: "2026-0007", quando: dia(6) + " " + hora(18, 12), quem: "Wagner Ramos", faturas: ["POS-2026-0410", "POS-2026-0411", "POS-2026-0412"], extra: 27 },
  { lote: "2026-0006", quando: dia(34) + " " + hora(9, 46), quem: "Eliana Souza", faturas: ["VD-2026-0288", "VD-2026-0289"], extra: 11 },
];
// import_fields do ImportSalesController — rótulo + instrução, verbatim do blade.
const CAMPOS_IMPORT = [
  { l: "Nº da fatura", i: "Agrupa as linhas numa mesma venda (campo escolhido em “Agrupar por”)." },
  { l: "Nome do cliente", i: "Cliente novo é criado se não existir pelo telefone/e-mail." },
  { l: "Telefone do cliente", i: "Obrigatório o telefone OU o e-mail do cliente." },
  { l: "E-mail do cliente", i: "Obrigatório o telefone OU o e-mail do cliente." },
  { l: "Data da venda", i: "Data em formato reconhecível; linha com data inválida para a importação." },
  { l: "Nome do produto", i: "Obrigatório o nome do produto OU o SKU." },
  { l: "SKU do produto", i: "Obrigatório o nome do produto OU o SKU." },
  { l: "Quantidade", i: "Obrigatório." },
  { l: "Unidade do produto", i: "Nome ou sigla da unidade; subunidade multiplica a quantidade." },
  { l: "Preço unitário", i: "Obrigatório." },
  { l: "Imposto do item", i: "Nome exato da taxa cadastrada." },
  { l: "Desconto do item", i: "Valor fixo abatido do preço unitário." },
  { l: "Descrição do item", i: "Vira a observação da linha da venda." },
  { l: "Total do pedido", i: "Em branco, o total é somado das linhas." },
];

// Idade em dias de cada venda (o daterangepicker do blade filtra por transaction_date).
const DIAS = { 1: 0, 2: 0, 3: 1, 4: 1, 5: 2, 6: 3, 7: 4, 8: 5 };
const PERIODOS = [{ id: "hoje", name: "Hoje", d: 0 }, { id: "7", name: "Últimos 7 dias", d: 7 }, { id: "30", name: "Últimos 30 dias", d: 30 }];
const noPeriodo = (id, dias) => !id || dias <= ((PERIODOS.find((p) => p.id === id) || {}).d ?? 9999);
// Permissões com os nomes LITERAIS do legado, lidos nos controllers no `main`:
// `sell.view|create|delete` (SellController/SellPosController/ImportSalesController),
// `direct_sell.access`, `access_shipping`, `access_sell_return`, `discount.access`
// (DiscountController — uma só permissão para ver, editar e excluir), `so.view_all`.
const PERMS = {
  administrador: { label: "Administrador", pode: () => true },
  balcao: { label: "Balcão (Larissa)", pode: (p) => !["sell.delete", "discount.access", "so.view_all"].includes(p) },
  financeiro: { label: "Financeiro (Eliana)", pode: (p) => !["sell.create", "direct_sell.access"].includes(p) },
};

const VBD = { LOCAIS, VENDEDORES, CLIENTES, PGTO, ENVIO, ENTREGADORES, FORMAS, SERVICOS, INTERVALOS, POS, RASCUNHOS, COTACOES, REMESSAS, DESCONTOS, ASSINATURAS, IMPORTACOES, CAMPOS_IMPORT, DIAS, PERIODOS, fmtBRL };
window.VBD = VBD;

// ─────────── Peças ───────────
const Badge = ({ kind, value }) => { const { StatusBadge } = DS(); return StatusBadge ? <StatusBadge kind={kind} value={value} /> : <span>{value}</span>; };
const rotular = (lista, id) => (lista.find((x) => x.id === id) || {}).name || id;

// components.filters do blade — o mesmo bloco recolhível das outras importações.
function Filtros({ campos, f, setF, nota }) {
  const { Widget, Fld, Sel } = UI();
  const [aberto, setAberto] = useState(true);
  if (!Widget) return null;
  return (
    <Widget contrato="venda-filtros" titulo={<><Ic name="search" size={13} /> Filtros</>} nota={aberto ? null : "recolhidos"}>
      <div className="pb-filters-h" style={{ marginBottom: aberto ? 12 : 0 }}>
        <span className="pb-help">{nota}</span>
        <button className="os-btn sm ghost" onClick={() => setAberto((a) => !a)}>{aberto ? "Recolher" : "Expandir"}</button>
      </div>
      {aberto &&
        <div className="pb-grid c4">
          {campos.map((c) => (
            <Fld key={c.k} label={c.l}>
              {c.tipo === "texto"
                ? <input value={f[c.k] || ""} onChange={(e) => setF({ ...f, [c.k]: e.target.value })} placeholder={c.ph || ""} />
                : <Sel value={f[c.k] || ""} onChange={(v) => setF({ ...f, [c.k]: v })} options={c.op} vazio={c.vazio || "Todos"} />}
            </Fld>
          ))}
        </div>}
    </Widget>
  );
}

// Grade — DataTablePro do DS (header fixo, resize, ordenação, densidade).
function Grade({ columns, rows, densa, altura = 420, selectable, onSelectionChange }) {
  const { DataTablePro, EmptyState } = DS();
  if (!rows.length) {
    return EmptyState
      ? <div style={{ padding: 24 }}><EmptyState variant="no-results" icon={<Ic name="search" size={18} />} title="Nada com esses filtros" description="Nenhum registro bate com o que está filtrado. Limpe um filtro ou amplie o período." /></div>
      : <p className="pb-help" style={{ padding: 16 }}>Nada com esses filtros.</p>;
  }
  if (!DataTablePro) return <p className="pb-help" style={{ padding: 16 }}>A grade do DS não carregou.</p>;
  return <div className="pb-grid-pro"><DataTablePro columns={columns} rows={rows} height={altura} density={densa ? "compact" : "comfortable"} selectable={selectable} onSelectionChange={onSelectionChange} /></div>;
}

function Toolbar({ busca, setBusca, ph, densa, setDensa, children }) {
  return (
    <div className="pb-toolbar" data-contract="venda-toolbar">
      <div className="pb-busca">
        <Ic name="search" size={12} />
        <input value={busca} onChange={(e) => setBusca(e.target.value)} placeholder={ph} />
      </div>
      <div className="sp" />
      <div className="pb-seg" role="group" aria-label="Densidade da tabela">
        <button className={densa ? "" : "on"} onClick={() => setDensa(false)}>Confortável</button>
        <button className={densa ? "on" : ""} onClick={() => setDensa(true)}>Compacto</button>
      </div>
      {children}
    </div>
  );
}

const Rodape = ({ children }) => <div className="pb-pag" data-contract="venda-rodape">{children}</div>;

// ─────────── 1. lista de POS (sale_pos/index.blade.php) ───────────
function TelaPos({ avisar, densa, setDensa, onIr, perms }) {
  const { Widget, Kebab } = UI();
  const [f, setF] = useState({});
  const [busca, setBusca] = useState("");
  const [ver, setVer] = useState(null);
  const [pagar, setPagar] = useState(null);
  const [recibo, setRecibo] = useState(null);
  const [editar, setEditar] = useState(null);
  const rows = useMemo(() => POS.filter((s) =>
    (!f.loc || s.loc === f.loc) && (!f.cli || s.cli === f.cli) && (!f.pg || s.pg === f.pg) &&
    (!f.quem || s.quem === f.quem) && (!f.serv || s.serv === f.serv) && noPeriodo(f.periodo, DIAS[s.id] ?? 0) &&
    (!busca || (s.inv + " " + s.cli).toLowerCase().includes(busca.toLowerCase()))), [f, busca]);
  const tot = rows.reduce((a, s) => ({ total: a.total + s.total, pago: a.pago + s.pago, saldo: a.saldo + s.saldo }), { total: 0, pago: 0, saldo: 0 });
  // Rodapé do blade: contagem por status de pagamento e por forma (footer_payment_status_count / payment_method_count).
  const porStatus = PGTO.map((p) => ({ l: p.name, n: rows.filter((s) => s.pg === p.id).length })).filter((x) => x.n);
  const porForma = FORMAS.map((m) => ({ l: m, n: rows.filter((s) => s.forma === m).length })).filter((x) => x.n);

  const cols = [
    { key: "acao", label: "Ação", width: 96, resizable: false },
    { key: "data", label: "Data", width: 132, mono: true, sortable: true, sortValue: (r) => r._s.id * -1 },
    { key: "inv", label: "Nº da fatura", width: 148, mono: true, sortable: true },
    { key: "cli", label: "Cliente", width: 210, sortable: true },
    { key: "tel", label: "Contato", width: 138, mono: true },
    { key: "loc", label: "Local", width: 120 },
    { key: "pg", label: "Status do pagamento", width: 150 },
    { key: "forma", label: "Forma de pagamento", width: 158 },
    { key: "total", label: "Valor total", width: 128, align: "right", mono: true, sortable: true, sortValue: (r) => r._s.total },
    { key: "pago", label: "Total pago", width: 126, align: "right", mono: true },
    { key: "saldo", label: "Saldo devedor", width: 134, align: "right", mono: true, sortable: true, sortValue: (r) => r._s.saldo },
    { key: "devDev", label: "Devolução devida", width: 148, align: "right", mono: true },
    { key: "envio", label: "Status de envio", width: 140 },
    { key: "itens", label: "Itens", width: 78, align: "right", mono: true },
    { key: "serv", label: "Tipo de serviço", width: 132 },
    { key: "quem", label: "Adicionado por", width: 148 },
    { key: "obs", label: "Observação da venda", width: 240 },
  ];
  const linhas = rows.map((s) => ({
    id: s.id, _s: s, state: s.pg === "overdue" ? "urgent" : undefined,
    acao: Kebab ? <Kebab acoes={[
      { l: "Ver detalhe", ic: "search", on: () => setVer(s) },
      { l: "Editar venda", ic: "pencil", on: () => setEditar(s) },
      { l: "Imprimir recibo", ic: "print", on: () => setRecibo(s) },
      "-",
      ...(s.saldo ? [{ l: "Adicionar pagamento", ic: "cash", on: () => setPagar(s) }] : []),
      { l: "Devolver venda", ic: "list", on: () => onIr("devolver", s) },
      { l: "Enviar remessa", ic: "truck", on: () => onIr("remessas", s) },
      "-",
      ...(perms.pode("sell.delete")
        ? [{ l: "Excluir", ic: "x", tone: "danger", on: () => avisar("Venda " + s.inv + " excluída.", "warn") }]
        : [{ l: "Excluir (sem permissão sell.delete)", ic: "x", on: () => avisar("Seu papel não tem sell.delete — peça ao administrador.", "warn") }]),
    ]} /> : null,
    data: s.data, inv: s.inv, cli: s.cli, tel: s.tel, loc: s.loc,
    pg: <Badge kind="payment" value={rotular(PGTO, s.pg)} />,
    forma: s.forma, total: fmtBRL(s.total), pago: fmtBRL(s.pago), saldo: fmtBRL(s.saldo), devDev: s.devDev ? fmtBRL(s.devDev) : "—",
    envio: <Badge kind="sla" value={rotular(ENVIO, s.envio)} />,
    itens: s.itens, serv: s.serv, quem: s.quem, obs: s.obs || "—",
  }));

  return (
    <>
      <Filtros nota="Mesmos filtros do índice de POS: local, cliente, status de pagamento, vendedor e tipo de serviço."
        f={f} setF={setF} campos={[
          { k: "loc", l: "Local do negócio", op: LOCAIS.map((l) => l.name) },
          { k: "cli", l: "Cliente", op: CLIENTES, vazio: "Todos" },
          { k: "pg", l: "Status do pagamento", op: PGTO },
          { k: "periodo", l: "Período", op: PERIODOS, vazio: "Tudo" },
          { k: "quem", l: "Vendedor", op: VENDEDORES },
          { k: "serv", l: "Tipo de serviço", op: SERVICOS },
        ]} />
      <Widget flush titulo={<><Ic name="cash" size={13} /> Lista de POS</>} nota={rows.length + " de " + POS.length}>
        <Toolbar busca={busca} setBusca={setBusca} ph="Buscar fatura ou cliente…" densa={densa} setDensa={setDensa}>
          <button className="os-btn sm" onClick={() => window.__vendasPdvOpen ? window.__vendasPdvOpen() : onIr("pdv")}><Ic name="plus" size={12} /> Abrir POS</button>
        </Toolbar>
        <Grade columns={cols} rows={linhas} densa={densa} />
        <Rodape>
          <span>Total: <b>{fmtBRL(tot.total)}</b> · pago <b>{fmtBRL(tot.pago)}</b> · em aberto <b>{fmtBRL(tot.saldo)}</b></span>
          <div className="sp" />
          <span className="pb-help">{porStatus.map((x) => x.l + ": " + x.n).join(" · ")} — {porForma.map((x) => x.l + ": " + x.n).join(" · ")}</span>
        </Rodape>
      </Widget>
      {ver && window.VendaDetalhe &&
        <window.VendaDetalhe venda={ver} onClose={() => setVer(null)} avisar={avisar}
          onAcao={(a, v) => {
            if (a === "pagamento") { setVer(null); setPagar(v); return; }
            if (a === "devolver") { setVer(null); onIr("devolver", v); return; }
            avisar("NF-e de " + v.inv + " na fila de transmissão SEFAZ.", "ok");
          }} />}
      {window.VendaRecibo && recibo && <window.VendaRecibo venda={recibo} onClose={() => setRecibo(null)} avisar={avisar} />}
      {window.VendaEditarModal && editar && <window.VendaEditarModal venda={editar} onClose={() => setEditar(null)} avisar={avisar} />}
      {window.VendaPagamentoModal &&
        <window.VendaPagamentoModal aberto={!!pagar} venda={pagar} onClose={() => setPagar(null)} avisar={avisar} onConfirm={() => setPagar(null)} />}
    </>
  );
}

// ─────────── 2/3. Rascunhos e cotações ───────────
// ⚠️ Produção passou o blade: `SellController@getDrafts/getQuotations` renderizam
// `Sells/Drafts.tsx` e `Sells/Quotations.tsx` (Inertia, vivos, lidos no `main`).
// Esta tela ESPELHA o vivo — idade do rascunho · Continuar venda · Editar+Enviar.
// Idade do rascunho — mesma fórmula do vivo (`Drafts.tsx`: floor((agora − data)/86400000)),
// derivada da data da PRÓPRIA linha; "dd/mm/aaaa hh:mm" do mock vira Date.
const diasDe = (txt) => {
  const [d, m, a] = String(txt || "").split(" ")[0].split("/").map(Number);
  if (!d || !m || !a) return 0;
  const alvo = new Date(a, m - 1, d);
  const hoje = new Date(); hoje.setHours(0, 0, 0, 0);
  return Math.max(0, Math.floor((hoje - alvo) / 86400000));
};
const idadeTom = (d) => d <= 2 ? "fresh" : d <= 7 ? "aging" : "stale";
const idadeLabel = (d) => d === 0 ? "hoje" : d === 1 ? "1 dia" : d + " dias";
function TelaDraft({ tipo, avisar, densa, setDensa, onIr }) {
  const { Widget } = UI();
  const cot = tipo === "cotacoes";
  const [base, setBase] = useState(cot ? COTACOES : RASCUNHOS);
  const [f, setF] = useState({});
  const [busca, setBusca] = useState("");
  useEffect(() => { setBase(cot ? COTACOES : RASCUNHOS); }, [cot]);
  const rows = base.filter((s) => (!f.loc || s.loc === f.loc) && (!f.cli || s.cli === f.cli) && (!f.quem || s.quem === f.quem) &&
    (!busca || (s.ref + " " + s.cli).toLowerCase().includes(busca.toLowerCase())));

  const cols = [
    { key: "data", label: "Data", width: 118, mono: true },
    ...(cot ? [] : [{ key: "idade", label: "Idade", width: 104 }]),
    { key: "ref", label: cot ? "Nº cotação" : "Nº rascunho", width: 152, mono: true, sortable: true },
    { key: "cli", label: "Cliente", width: 210, sortable: true },
    { key: "loc", label: "Local", width: 130 },
    { key: "itens", label: "Itens", width: 92, align: "right", mono: true, sortable: true, sortValue: (r) => r._s.itens },
    { key: "total", label: "Valor", width: 124, align: "right", mono: true, sortable: true, sortValue: (r) => r._s.total },
    { key: "acao", label: "Ações", width: cot ? 172 : 160, align: "right", resizable: false },
  ];
  const linhas = rows.map((s) => {
    const dias = diasDe(s.data);
    return {
      id: s.id, _s: s,
      data: s.data.split(" ")[0],
      idade: <span className={"vb-idade " + idadeTom(dias)}>{idadeLabel(dias)}</span>,
      ref: s.ref, cli: s.cli, loc: s.loc, itens: s.itens, total: fmtBRL(s.total),
      acao: cot
        ? <div className="vb-acoes-linha">
            <button className="os-btn sm" onClick={() => onIr("editar", s)}>Editar</button>
            <button className="os-btn sm primary" onClick={() => avisar(s.ref + " aberta para envio ao cliente.", "ok")}>Enviar</button>
          </div>
        : <button className="os-btn sm" onClick={() => onIr("editar", s)}>Continuar venda</button>,
    };
  });

  return (
    <>
      <Filtros nota={"Filtros do blade: local, cliente, período e usuário."} f={f} setF={setF} campos={[
        { k: "loc", l: "Local do negócio", op: LOCAIS.map((l) => l.name) },
        { k: "cli", l: "Cliente", op: CLIENTES },
        { k: "periodo", l: "Período", op: PERIODOS, vazio: "Tudo" },
        { k: "quem", l: "Usuário", op: VENDEDORES },
      ]} />
      <Widget flush titulo={<><Ic name="quote" size={13} /> {cot ? "Cotações" : "Rascunhos"}</>} nota={rows.length + " de " + (cot ? COTACOES : RASCUNHOS).length}>
        <Toolbar busca={busca} setBusca={setBusca} ph="Buscar por nº ou cliente…" densa={densa} setDensa={setDensa}>
          <button className="os-btn sm primary" onClick={() => onIr("nova", { status: cot ? "quotation" : "draft" })}>
            <Ic name="plus" size={12} /> {cot ? "Nova cotação" : "Nova venda"}
          </button>
        </Toolbar>
        <Grade columns={cols} rows={linhas} densa={densa} altura={360} />
        <Rodape>
          <span className="pb-help">{cot
            ? "Propostas formais — enviar pro cliente e converter em venda quando aprovado."
            : "Vendas salvas como rascunho — finalizar depois. A idade sinaliza abandono: até 2 dias fresco, até 7 esfriando, acima disso frio."}</span>
        </Rodape>
      </Widget>
    </>
  );
}

// ─────────── 4. Remessas (sell/shipments.blade.php) ───────────
function TelaRemessas({ avisar, densa, setDensa }) {
  const { Widget, Kebab, Modal, Fld, Sel } = UI();
  const [f, setF] = useState({});
  const [busca, setBusca] = useState("");
  const [alvo, setAlvo] = useState(null);
  const [form, setForm] = useState({});
  const rows = REMESSAS.filter((s) => (!f.loc || s.loc === f.loc) && (!f.cli || s.cli === f.cli) && (!f.pg || s.pg === f.pg) &&
    (!f.envio || s.envio === f.envio) && (!f.entregador || s.entregador === f.entregador) &&
    (!busca || (s.inv + " " + s.cli).toLowerCase().includes(busca.toLowerCase())));

  const cols = [
    { key: "acao", label: "Ação", width: 96, resizable: false },
    { key: "data", label: "Data", width: 132, mono: true },
    { key: "inv", label: "Nº da fatura", width: 148, mono: true, sortable: true },
    { key: "cli", label: "Cliente", width: 210, sortable: true },
    { key: "tel", label: "Contato", width: 138, mono: true },
    { key: "loc", label: "Local", width: 118 },
    { key: "entregador", label: "Entregador", width: 190 },
    { key: "envio", label: "Status de envio", width: 142 },
    { key: "docs", label: "Documentos da remessa", width: 172 },
    { key: "rastreio", label: "Rastreio", width: 150, mono: true },
    { key: "pg", label: "Status do pagamento", width: 152 },
    { key: "atendente", label: "Atendente", width: 148 },
  ];
  const linhas = rows.map((s) => ({
    id: s.id, _s: s,
    acao: Kebab ? <Kebab acoes={[
      { l: "Editar remessa", ic: "pencil", on: () => { setAlvo(s); setForm({ envio: s.envio, entregador: s.entregador, rastreio: s.rastreio, docs: s.docs }); } },
      { l: "Imprimir romaneio", ic: "print", on: () => avisar("Romaneio de " + s.inv + " na fila de impressão.", "ok") },
      { l: "Ver venda", ic: "search", on: () => avisar("Abrindo " + s.inv + ".", "ok") },
    ]} /> : null,
    data: s.data, inv: s.inv, cli: s.cli, tel: s.tel, loc: s.loc, entregador: s.entregador,
    envio: <Badge kind="sla" value={rotular(ENVIO, s.envio)} />,
    docs: s.docs, rastreio: s.rastreio || "—",
    pg: <Badge kind="payment" value={rotular(PGTO, s.pg)} />,
    atendente: s.atendente,
  }));

  return (
    <>
      <Filtros nota="Filtros do blade de remessas: local, cliente, período, usuário, status de pagamento, status de envio e entregador."
        f={f} setF={setF} campos={[
          { k: "loc", l: "Local do negócio", op: LOCAIS.map((l) => l.name) },
          { k: "cli", l: "Cliente", op: CLIENTES },
          { k: "periodo", l: "Período", op: PERIODOS, vazio: "Tudo" },
          { k: "quem", l: "Usuário", op: VENDEDORES },
          { k: "pg", l: "Status do pagamento", op: PGTO },
          { k: "envio", l: "Status de envio", op: ENVIO },
          { k: "entregador", l: "Entregador", op: ENTREGADORES },
        ]} />
      <Widget flush titulo={<><Ic name="truck" size={13} /> Remessas</>} nota={rows.length + " de " + REMESSAS.length}>
        <Toolbar busca={busca} setBusca={setBusca} ph="Buscar fatura ou cliente…" densa={densa} setDensa={setDensa} />
        <Grade columns={cols} rows={linhas} densa={densa} />
        <Rodape><span className="pb-help">Status de envio e entregador vêm da própria venda — editar aqui atualiza a transação, não cria documento novo.</span></Rodape>
      </Widget>
      {alvo && Modal &&
        <Modal titulo={"Editar remessa · " + alvo.inv} onClose={() => setAlvo(null)} largura={620}
          acoes={<>
            <button className="os-btn" onClick={() => setAlvo(null)}>Cancelar</button>
            <button className="os-btn primary" onClick={() => { avisar("Remessa de " + alvo.inv + " atualizada para “" + rotular(ENVIO, form.envio) + "”.", "ok"); setAlvo(null); }}>Salvar remessa</button>
          </>}>
          <div className="pb-grid c2">
            <Fld label="Status de envio" req><Sel value={form.envio} onChange={(v) => setForm({ ...form, envio: v })} options={ENVIO} /></Fld>
            <Fld label="Entregador"><Sel value={form.entregador} onChange={(v) => setForm({ ...form, entregador: v })} options={ENTREGADORES} vazio="Sem entregador" /></Fld>
            <Fld label="Código de rastreio"><input value={form.rastreio || ""} onChange={(e) => setForm({ ...form, rastreio: e.target.value })} placeholder="BR000000000OI" /></Fld>
            <Fld label="Documentos da remessa"><input value={form.docs || ""} onChange={(e) => setForm({ ...form, docs: e.target.value })} placeholder="NF-e, romaneio…" /></Fld>
          </div>
        </Modal>}
    </>
  );
}

// ─────────── 5. Descontos (discount/index + create) ───────────
function TelaDescontos({ avisar, densa, setDensa }) {
  const { Widget, Kebab, Modal, Fld, Sel } = UI();
  const { Alert } = DS();
  const [busca, setBusca] = useState("");
  const [sel, setSel] = useState([]);
  const [novo, setNovo] = useState(null);
  const rows = DESCONTOS.filter((d) => !busca || d.nome.toLowerCase().includes(busca.toLowerCase()));

  const cols = [
    { key: "acao", label: "Ação", width: 96, resizable: false },
    { key: "nome", label: "Nome", width: 260, sortable: true },
    { key: "inicio", label: "Começa em", width: 124, mono: true },
    { key: "fim", label: "Termina em", width: 124, mono: true },
    { key: "valor", label: "Valor do desconto", width: 152, align: "right", mono: true, sortable: true, sortValue: (r) => r._d.valor },
    { key: "prio", label: "Prioridade", width: 106, align: "right", mono: true, sortable: true, sortValue: (r) => r._d.prio },
    { key: "marca", label: "Marca", width: 130 },
    { key: "cat", label: "Categoria", width: 168 },
    { key: "produtos", label: "Produtos", width: 106, align: "right", mono: true },
    { key: "loc", label: "Local", width: 130 },
    { key: "ativo", label: "Situação", width: 118 },
  ];
  const linhas = rows.map((d) => ({
    id: d.id, _d: d, state: d.ativo ? undefined : "archived",
    acao: Kebab ? <Kebab acoes={[
      { l: "Editar", ic: "pencil", on: () => setNovo({ ...d }) },
      { l: d.ativo ? "Desativar" : "Reativar", ic: "cog", on: () => avisar("“" + d.nome + "” " + (d.ativo ? "desativado" : "reativado") + ".", d.ativo ? "warn" : "ok") },
      { l: "Excluir", ic: "x", tone: "danger", on: () => avisar("Desconto “" + d.nome + "” excluído.", "warn") },
    ]} /> : null,
    nome: d.nome, inicio: d.inicio, fim: d.fim,
    valor: d.tipo === "percentage" ? d.valor.toLocaleString("pt-BR") + "%" : fmtBRL(d.valor),
    prio: d.prio, marca: d.marca, cat: d.cat, produtos: d.produtos, loc: d.loc,
    ativo: <Badge kind="documento" value={d.ativo ? "Ativo" : "Inativo"} />,
  }));

  return (
    <>
      {Alert && <Alert tone="warn" title="Uma permissão só">No legado o acesso é <span className="mono">discount.access</span> — quem entra também edita e exclui, não há “só ver”. A tela do blade ainda checa <span className="mono">brand.view/brand.create</span> nos botões: diverge do controller.</Alert>}
      <Widget flush titulo={<><Ic name="cash" size={13} /> Descontos</>} nota={rows.length + " de " + DESCONTOS.length}>
        <Toolbar busca={busca} setBusca={setBusca} ph="Buscar desconto…" densa={densa} setDensa={setDensa}>
          <button className="os-btn sm primary" onClick={() => setNovo({ tipo: "percentage", prio: 1 })}><Ic name="plus" size={12} /> Adicionar</button>
        </Toolbar>
        <Grade columns={cols} rows={linhas} densa={densa} altura={340} selectable onSelectionChange={setSel} />
        <Rodape>
          <span className="pb-help">O desconto vale no PDV e na venda direta enquanto estiver no período — prioridade menor ganha do maior. Escolher produtos apaga marca e categoria: o servidor guarda um ou outro, nunca os dois.</span>
          <div className="sp" />
          <button className="os-btn sm" disabled={!sel.length} onClick={() => avisar(sel.length + " desconto(s) desativados.", "warn")}>Desativar selecionados</button>
        </Rodape>
      </Widget>
      {novo && Modal &&
        <Modal titulo={novo.id ? "Editar desconto" : "Adicionar desconto"} onClose={() => setNovo(null)} largura={720}
          acoes={<>
            <button className="os-btn" onClick={() => setNovo(null)}>Cancelar</button>
            <button className="os-btn primary" onClick={() => { avisar("Desconto salvo — vale no PDV a partir da data de início.", "ok"); setNovo(null); }}>Salvar</button>
          </>}>
          <div className="pb-grid c2">
            <Fld label="Nome" req span={2}><input value={novo.nome || ""} onChange={(e) => setNovo({ ...novo, nome: e.target.value })} placeholder="Semana da comunicação visual" /></Fld>
            <Fld label="Produtos" span={2}><input value={novo.prods || ""} onChange={(e) => setNovo({ ...novo, prods: e.target.value })} placeholder="Buscar produtos (deixe vazio para valer por marca/categoria)" /></Fld>
            <Fld label="Marca"><Sel value={novo.marca} onChange={(v) => setNovo({ ...novo, marca: v })} options={["Vinilcor", "3M", "Avery", "Coral", "Suprema", "Sem marca"]} vazio="Selecione" /></Fld>
            <Fld label="Categoria"><Sel value={novo.cat} onChange={(v) => setNovo({ ...novo, cat: v })} options={["Comunicação visual", "Impressos", "Adesivos", "Acabamento", "Insumos", "Serviços"]} vazio="Selecione" /></Fld>
            <Fld label="Local" req><Sel value={novo.loc} onChange={(v) => setNovo({ ...novo, loc: v })} options={LOCAIS.map((l) => l.name)} vazio="Selecione" /></Fld>
            <Fld label="Prioridade" req dica="Quando dois descontos batem no mesmo produto, vale o de prioridade menor."><input className="num" value={novo.prio ?? ""} onChange={(e) => setNovo({ ...novo, prio: e.target.value })} /></Fld>
            <Fld label="Tipo de desconto" req><Sel value={novo.tipo} onChange={(v) => setNovo({ ...novo, tipo: v })} options={[{ id: "fixed", name: "Fixo" }, { id: "percentage", name: "Percentual" }]} /></Fld>
            <Fld label="Valor do desconto" req><input className="num" value={novo.valor ?? ""} onChange={(e) => setNovo({ ...novo, valor: e.target.value })} placeholder={novo.tipo === "fixed" ? "R$" : "%"} /></Fld>
            <Fld label="Começa em" req><input value={novo.inicio || ""} onChange={(e) => setNovo({ ...novo, inicio: e.target.value })} placeholder="dd/mm/aaaa" /></Fld>
            <Fld label="Termina em" req><input value={novo.fim || ""} onChange={(e) => setNovo({ ...novo, fim: e.target.value })} placeholder="dd/mm/aaaa" /></Fld>
            <Fld label="Grupo de preço de venda"><Sel value={novo.spg} onChange={(v) => setNovo({ ...novo, spg: v })} options={["Varejo", "Atacado", "Convênio", "Funcionário"]} vazio="Todos" /></Fld>
            <div className="pb-fld" style={{ justifyContent: "flex-end" }}>
              <label className="pb-chk"><input type="checkbox" checked={novo.ativo !== false} onChange={(e) => setNovo({ ...novo, ativo: e.target.checked })} /><b>Ativo</b><small>Desconto inativo não aparece no PDV.</small></label>
            </div>
          </div>
        </Modal>}
    </>
  );
}

// ─────────── 6. Assinaturas ───────────
// ⚠️ Vivo: `SellPosController@listSubscriptions` renderiza `Sells/Subscriptions.tsx`.
// Espelhado daqui: KPIs Total/Ativas/Pausadas, colunas do vivo e o toggle Pausar/Retomar
// (`toggleRecurringInvoices`, GET legado). Criar assinatura NÃO existe na tela — nasce
// de uma venda recorrente, por isso o primário é "Nova venda".
function TelaAssinaturas({ avisar, densa, setDensa, onIr, perms }) {
  const { Widget } = UI();
  const [busca, setBusca] = useState("");
  const [paradas, setParadas] = useState({ 3: true });
  const { Alert, StatusBadge } = DS();
  const rows = ASSINATURAS.filter((a) => !busca || (a.num + " " + a.cli).toLowerCase().includes(busca.toLowerCase()));
  const ativas = ASSINATURAS.filter((a) => !paradas[a.id]).length;
  const cols = [
    { key: "data", label: "Data início", width: 122, mono: true },
    { key: "num", label: "Nº cobrança", width: 142, mono: true, sortable: true },
    { key: "cli", label: "Cliente", width: 220, sortable: true },
    { key: "intervalo", label: "Intervalo", width: 120 },
    { key: "proxima", label: "Próxima fatura", width: 138, mono: true },
    { key: "geradas", label: "Faturas geradas", width: 138, align: "right", mono: true },
    { key: "status", label: "Status", width: 124 },
    { key: "acao", label: "Ações", width: 130, align: "right", resizable: false },
  ];
  const linhas = rows.map((a) => {
    const parada = !!paradas[a.id];
    return {
      id: a.id, _a: a, state: parada ? "archived" : undefined,
      data: a.data, num: a.num, cli: a.cli, intervalo: a.intervalo,
      proxima: parada ? "—" : a.proxima, geradas: a.geradas + "/" + a.repet,
      status: StatusBadge ? <StatusBadge kind="documento" value={parada ? "Pausada" : "Ativa"} /> : (parada ? "Pausada" : "Ativa"),
      acao: <button className="os-btn sm" disabled={!perms.pode("direct_sell.access")}
        onClick={() => { setParadas((p) => ({ ...p, [a.id]: !parada })); avisar(parada ? a.num + " retomada — volta a gerar fatura." : a.num + " pausada — não gera mais fatura até retomar.", parada ? "ok" : "warn"); }}>
        {parada ? "Retomar" : "Pausar"}</button>,
    };
  });
  return (
    <>
      {Alert && <Alert tone="info" title="Cobranças recorrentes">A assinatura repete a venda no intervalo escolhido até bater o número de repetições. Pausar não cancela: a assinatura fica parada até você retomar.</Alert>}
      <Widget flush titulo={<><Ic name="clock" size={13} /> Assinaturas</>} nota={ativas + " ativa(s) · " + (ASSINATURAS.length - ativas) + " pausada(s)"}>
        <Toolbar busca={busca} setBusca={setBusca} ph="Buscar por nº ou cliente…" densa={densa} setDensa={setDensa}>
          <button className="os-btn sm primary" onClick={() => onIr("nova", { status: "final" })}><Ic name="plus" size={12} /> Nova venda</button>
        </Toolbar>
        <Grade columns={cols} rows={linhas} densa={densa} altura={300} />
        <Rodape><span className="pb-help">A assinatura nasce marcando a venda como recorrente no formulário de venda — não existe cadastro avulso aqui, igual ao vivo.</span></Rodape>
      </Widget>
    </>
  );
}

// ─────────── 7. Importação de vendas (import_sales/index.blade.php) ───────────
function TelaImportar({ avisar, onPreview }) {
  const { Widget, Fld, Sel } = UI();
  const [arquivo, setArquivo] = useState("");
  const [loc, setLoc] = useState("");
  const PASSOS = [
    "Envie os dados em formato Excel (.xlsx).",
    "Escolha o local do negócio e por qual campo agrupar as linhas em vendas.",
    "Mapeie as colunas da planilha com os campos de venda correspondentes — a prévia já chega pré-mapeada por semelhança de nome.",
    "Revise a prévia antes de confirmar — a venda importada nasce finalizada e baixa estoque.",
  ];
  return (
    <>
      <Widget titulo={<><Ic name="upload" size={13} /> Enviar planilha</>}>
        <div className="pb-grid c3">
          <Fld label="Arquivo para importar" req span={2}>
            <input type="file" onChange={(e) => setArquivo(e.target.files?.[0]?.name || "")} accept=".xlsx,.xls,.csv" />
          </Fld>
          <Fld label="Local do negócio" req><Sel value={loc} onChange={setLoc} options={LOCAIS.map((l) => l.name)} vazio="Selecione" /></Fld>
        </div>
        <div className="pb-filters-h" style={{ marginTop: 12 }}>
          <span className="pb-help">{arquivo ? "Selecionado: " + arquivo : "Nenhum arquivo escolhido."}</span>
          <button className="os-btn sm" onClick={() => avisar("Modelo import_sales_template.xlsx baixado.", "ok")}><Ic name="sheet" size={12} /> Baixar arquivo modelo</button>
          <button className="os-btn sm primary" disabled={!arquivo || !loc} onClick={() => onPreview?.(arquivo)}>Enviar e revisar</button>
        </div>
      </Widget>

      <Widget titulo={<><Ic name="info" size={13} /> Instruções</>}>
        <ol className="vb-passos">{PASSOS.map((p, i) => <li key={i}>{p}</li>)}</ol>
        <table className="pb-tbl" style={{ marginTop: 12 }}>
          <thead><tr><th>Campos importáveis</th><th>Instruções</th></tr></thead>
          <tbody>{CAMPOS_IMPORT.map((c) => <tr key={c.l}><td><b>{c.l}</b></td><td className="pb-help">{c.i}</td></tr>)}</tbody>
        </table>
      </Widget>

      <Widget titulo={<><Ic name="clock" size={13} /> Importações</>} nota={IMPORTACOES.length + " lote(s)"}>
        <table className="pb-tbl">
          <thead><tr><th>Lote de importação</th><th>Hora da importação</th><th>Criado por</th><th>Faturas</th><th>Ação</th></tr></thead>
          <tbody>
            {IMPORTACOES.map((im) => (
              <tr key={im.lote}>
                <td className="mono">{im.lote}</td>
                <td className="mono">{im.quando}</td>
                <td>{im.quem}</td>
                <td>{im.faturas.join(", ")}<div className="pb-help">e mais {im.extra} fatura(s)</div></td>
                <td><button className="os-btn sm ghost danger" onClick={() => avisar("Lote " + im.lote + " revertido — as vendas do lote foram excluídas (exige sell.delete).", "warn")}>Excluir lote</button></td>
              </tr>
            ))}
          </tbody>
        </table>
      </Widget>
    </>
  );
}

// ─────────── Página do módulo ───────────
const TITULOS = {
  pdv: "POS", pos: "Lista de POS", nova: "Adicionar venda", rascunhos: "Lista de rascunhos", cotacoes: "Lista de compromissos",
  remessas: "Remessas", descontos: "Descontos", assinaturas: "Assinaturas", importar: "Importação de vendas", pedidos: "Pedido de venda",
  caixa: "Caixa registradora", devolver: "Devolver venda",
};
const ROTA_DE = { pdv: "venda-pdv", pos: "venda-pos", nova: "venda-nova", rascunhos: "venda-rascunhos", cotacoes: "venda-cotacoes", remessas: "venda-remessas", descontos: "venda-descontos", assinaturas: "venda-assinaturas", importar: "venda-importar", pedidos: "venda-pedidos", caixa: "venda-caixa", devolver: "venda-devolver" };
// Telas que são AÇÃO, não visao própria: herdam o destaque da aba de onde saíram
// (senão nenhuma aba casa e a barra fica sem nenhum item marcado).
const ABA_DE = { pdv: "pos", nova: "pos", devolver: "pos" };
const SemPermissao = ({ o }) => {
  const { EmptyState } = DS();
  return EmptyState
    ? <EmptyState variant="no-perm" icon={<Ic name="shield" size={18} />} title="Sem permissão" description={"Seu papel não tem “" + o + "”. Peça ao administrador para liberar em Funções e permissões."} />
    : <p className="pb-help">Sem permissão: {o}</p>;
};

function VendaBladePage({ view = "pos", dense = false, papel = "administrador", status = "final" }) {
  const M = MP();
  const [tela, setTela] = useState(view);
  const [densa, setDensa] = useState(dense);
  const [preview, setPreview] = useState(null);
  const [hora, setHora] = useState(() => new Date().toLocaleTimeString("pt-BR", { hour: "2-digit", minute: "2-digit" }));
  const [avisoNode, avisar] = M.useAviso ? M.useAviso() : [null, () => {}];
  const perms = PERMS[papel] || PERMS.administrador;
  // A venda-alvo da devolução vive FORA do React: trocar de rota remonta a página
  // (RouteErrorBoundary usa key={route}) e zeraria um estado local — mesmo padrão do __vendaNovaStatus.
  const [alvo, setAlvo] = useState(() => window.__vendaDevolverAlvo || null);
  useEffect(() => { setTela(view); setPreview(null); }, [view]);
  useEffect(() => { setDensa(dense); }, [dense]);

  // Ir pra outra tela do módulo — reflete na rota do shell (senão a sidebar mente).
  const onIr = (destino, alvoVenda) => {
    if (destino === "editar") { avisar("Abrindo " + (alvoVenda.inv || alvoVenda.ref) + " para edição.", "ok"); return; }
    if (destino === "devolver") { window.__vendaDevolverAlvo = alvoVenda || window.__vendaDevolverAlvo; setAlvo(window.__vendaDevolverAlvo); }
    if (destino === "nova") window.__vendaNovaStatus = (alvoVenda || {}).status || "final";
    setTela(destino);
    const r = ROTA_DE[destino];
    if (r && window.__selectRoute) window.__selectRoute(r);
  };

  const corpo =
    tela === "pdv" ? (window.VendaPos ? <window.VendaPos avisar={avisar} onSair={() => onIr("pos")} /> : null) :
    tela === "nova" ? (window.VendaNova ? <window.VendaNova status={window.__vendaNovaStatus || status} avisar={avisar} onVoltar={() => onIr("pos")} /> : null) :
    tela === "pos" ? <TelaPos avisar={avisar} densa={densa} setDensa={setDensa} onIr={onIr} perms={perms} /> :
    tela === "rascunhos" ? <TelaDraft tipo="rascunhos" avisar={avisar} densa={densa} setDensa={setDensa} onIr={onIr} /> :
    tela === "cotacoes" ? <TelaDraft tipo="cotacoes" avisar={avisar} densa={densa} setDensa={setDensa} onIr={onIr} /> :
    tela === "remessas" ? <TelaRemessas avisar={avisar} densa={densa} setDensa={setDensa} /> :
    tela === "descontos" ? (perms.pode("discount.access") ? <TelaDescontos avisar={avisar} densa={densa} setDensa={setDensa} /> : <SemPermissao o="discount.access" />) :
    tela === "assinaturas" ? <TelaAssinaturas avisar={avisar} densa={densa} setDensa={setDensa} onIr={onIr} perms={perms} /> :
    tela === "pedidos" ? (window.VendaPedidos ? <window.VendaPedidos avisar={avisar} densa={densa} setDensa={setDensa} Grade={Grade} Toolbar={Toolbar} Filtros={Filtros} /> : null) :
    tela === "caixa" ? (window.VendaCaixa ? <window.VendaCaixa avisar={avisar} Grade={Grade} Toolbar={Toolbar} /> : null) :
    tela === "devolver" ? (window.VendaDevolucaoForm && (alvo || window.__vendaDevolverAlvo || POS[0])
      ? <window.VendaDevolucaoForm venda={alvo || window.__vendaDevolverAlvo || POS[0]} avisar={avisar} onSair={() => { window.__vendaDevolverAlvo = null; onIr("pos"); }} />
      : null) :
    tela === "importar" ? (preview
      ? (window.VendaImportPreview ? <window.VendaImportPreview arquivo={preview} avisar={avisar} onVoltar={() => setPreview(null)} /> : null)
      : <TelaImportar avisar={avisar} onPreview={(a) => setPreview(a || "vendas.xlsx")} />) : null;

  return (
    <div className={"pb-root vb-root" + (densa ? " pb-dense" : "")} data-screen-label={"Venda · " + (TITULOS[tela] || tela)}>
      {M.Header &&
        <M.Header modulo="Vendas" papel={TITULOS[tela] || tela}
          contexto={["OFFICEIMPRESSO", "matriz", POS.length + " vendas de POS · " + RASCUNHOS.length + " rascunhos · " + COTACOES.length + " cotações", "papel: " + perms.label]}
          atualizadoAs={hora}
          onRefresh={() => { setHora(new Date().toLocaleTimeString("pt-BR", { hour: "2-digit", minute: "2-digit" })); avisar("Vendas reapuradas agora.", "ok"); }}
          glyph={<Ic name="cash" />}
          acoes={<>
            <button className="os-btn" onClick={() => window.__selectRoute && window.__selectRoute("vendas")}>Todas as vendas</button>
            <button className="os-btn" onClick={() => onIr("pdv")}>POS</button>
            {perms.pode("direct_sell.access") &&
              <button className="os-btn primary" onClick={() => onIr("nova", { status: "final" })}><Ic name="plus" size={13} /> Adicionar venda</button>}
          </>} />}
      <div className="pb-body">
        <nav className="cli-moduletopnav vb-nav" aria-label="Telas do módulo Venda">
          {["pos", "rascunhos", "cotacoes", "remessas", "descontos", "assinaturas", "pedidos", "caixa", "importar"].map((k) => (
            <button key={k} className={"cli-moduletopnav-tab " + (tela === k || ABA_DE[tela] === k ? "active" : "")} onClick={() => onIr(k)}>{TITULOS[k]}</button>
          ))}
        </nav>
        {corpo}
      </div>
      {avisoNode}
    </div>
  );
}

window.VendaBladePage = VendaBladePage;
})();
