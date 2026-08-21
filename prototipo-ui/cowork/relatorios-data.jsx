// relatorios-data.jsx — catálogo do módulo RELATÓRIOS importado dos blades
// `resources/views/report/*` (+ `report/partials/*`) do UltimatePOS/oimpresso.
// Onda 1 (fidelidade): os filtros de cada relatório são agora os `Form::label`/`Form::select`
// LIDOS do blade — nada inferido. As colunas custom-field (`$product_custom_field1..4`) e
// `current_stock_mfg` entram como colunas OPCIONAIS (colvis), como no DataTable do vivo.
// Onda 3: o grupo "Gráfica" são relatórios NOVOS (não existem no Blade) — pendentes de [W].
//   profit_loss ................ Lucros e perdas (resumo + abas profit_by_*)
//   purchase_sell .............. Compras e vendas (dois painéis + diferença)
//   tax_report ................. Fiscal (abas compras/vendas/despesas)
//   contact / customer_group ... Clientes e fornecedores / Grupos de clientes
//   stock_report ............... Estoque (partials/stock_report_table)
//   lot_report / stock_expiry .. Lotes / Vencimento de estoque (+ edit modal)
//   stock_adjustment_report .... Ajustes de estoque (4 totais + tabela)
//   trending_products .......... Produtos em tendência (gráfico)
//   items_report ............... Itens (compra → venda, dois períodos)
//   product_purchase_report .... Compras por produto
//   product_sell_report ........ Vendas por produto (abas venda/lote/produto)
//   purchase_payment_report .... Pagamentos de compra
//   sell_payment_report ........ Pagamentos de venda
//   expense_report ............. Despesas por categoria
//   register_report ............ Caixa (registro)
//   sales_representative ....... Agente comercial (abas vendas/comissão/despesas)
//   service_staff_report ....... Atendentes (módulo Restaurante)
//   activity_log ............... Registro de atividade
//   product_stock_details ...... Detalhes de estoque do produto (resumo)
// Expõe window.RELD.
(() => {
// ─────────── Domínio (os selects dos blades) ───────────
const LOCAIS = ["Matriz", "Filial Centro"];
const CLIENTES = ["Padaria Estrela", "Mercado União", "Auto Posto Águia", "Farmácia Vida Plena", "Construtora Horizonte", "Rota Livre Transportes", "Escola Saber Mais", "Distribuidora Brasil", "Salão Beleza Pura", "Imobiliária Lar Bom"];
const FORNECEDORES = ["Lonas & Vinis Ltda", "Suprema Papéis", "3M do Brasil", "Avery Dennison", "Alumínio Paulista", "Tintas Coral Ind."];
const PRODUTOS = ["Lona 380g brilho impressa", "Vinil adesivo brilho", "Placa ACM 3mm recortada", "Cartão de visita 4x4 — 1.000 un", "Kit fachada completa 3x1m", "Tinta solvente CMYK 5L", "Ilhós metálico nº 12 (mil)", "Perfil de alumínio 30x30", "Adesivo de recorte — vinil colorido", "Banner 440g com bastão"];
const CATEGORIAS = ["Comunicação visual", "Impressos", "Adesivos", "Acabamento", "Insumos", "Serviços"];
const SUBCATEGORIAS = ["Lonas", "Fachadas", "Placas", "Offset", "Digital", "Vinil", "Recorte", "Ilhós", "Tintas"];
const MARCAS = ["Vinilcor", "3M", "Avery", "Coral", "Suprema", "Sem marca"];
const UNIDADES = ["m²", "m", "Un", "cx", "kg", "pç"];
const GRUPOS = ["Varejo", "Atacado", "Corporativo", "Convênio", "Sem grupo"];
const USUARIOS = ["Larissa Prado", "Wagner Ramos", "Eliana Costa", "Martinho Alves"];
const FORMAS = ["Dinheiro", "Pix", "Cartão de crédito", "Cartão de débito", "Boleto", "Transferência"];
const CAT_DESPESA = ["Aluguel", "Energia", "Insumos de produção", "Frete e entrega", "Manutenção de máquina", "Folha de pagamento", "Marketing"];
const MOTIVOS = ["Perda na impressão", "Sobra de bobina", "Recorte com defeito", "Contagem de inventário", "Avaria no transporte"];
const TIPO_AJUSTE = ["Normal", "Anormal"];
const ACOES = ["criado", "editado", "excluído", "pagamento", "visualizado"];
const TIPO_REG = ["Venda", "Compra", "Produto", "Contato", "Ordem de produção", "Ajuste de estoque"];
const NOTAS = ["Desconto liberado pelo balcão", "Alterado após conferência fiscal", "Cancelado a pedido do cliente", "—", "Pagamento parcial registrado"];
const VARIACOES = ["Padrão", "Acabamento - Bastão + ilhós", "Cor - Ciano", "Cor - Branco", "Acabamento - Solda simples"];
const STATUS_PGTO = ["Pago", "Parcial", "Devido", "Vencido"];
const DESCRICOES = ["Impressão digital solvente", "Vinil monomérico brilho", "Chapa com recorte CNC", "Couché 300g 4x4", "Insumo de produção"];
const MAQUINAS = ["Plotter Roland 540", "Plotter Mimaki JV300", "Router CNC 1200", "Laser CO2 100W", "Offset Heidelberg GTO"];
const TURNOS = ["Manhã", "Tarde", "Noite"];
const OS_ETAPAS = ["Arte", "Impressão", "Acabamento", "Instalação", "Entregue"];
const MOTIVO_RETRABALHO = ["Arte reprovada pelo cliente", "Cor fora do padrão", "Medida errada na OS", "Falha de recorte", "Material com defeito"];

// ─────────── Formatação (canon PT-BR do DS) ───────────
const BRL = (n) => "R$ " + Number(n).toLocaleString("pt-BR", { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const QTD = (n) => Number(n).toLocaleString("pt-BR", { maximumFractionDigits: 2 });
const PCT = (n) => Number(n).toLocaleString("pt-BR", { minimumFractionDigits: 1, maximumFractionDigits: 1 }) + "%";
const dia = (d) => String(d.getDate()).padStart(2, "0") + "/" + String(d.getMonth() + 1).padStart(2, "0") + "/" + d.getFullYear();
const hoje = () => { const d = new Date(); d.setHours(0, 0, 0, 0); return d; };
const menos = (n) => { const d = hoje(); d.setDate(d.getDate() - n); return d; };

// Ruído determinístico — o mesmo relatório sempre apura o mesmo número.
const rng = (seed) => { let a = 0; for (const ch of String(seed)) a = (a * 31 + ch.charCodeAt(0)) >>> 0; return () => { a += 0x6D2B79F5; let t = a; t = Math.imul(t ^ t >>> 15, t | 1); t ^= t + Math.imul(t ^ t >>> 7, t | 61); return ((t ^ t >>> 14) >>> 0) / 4294967296; }; };

// ─────────── Colunas ───────────
// opt: coluna OPCIONAL (colvis) — no vivo é o custom field / coluna condicional do módulo.
const t = (k, l, o) => ({ k, l, t: "t", opt: o });
const kk = (k, l, o) => ({ k, l, t: "k", opt: o });
const d = (k, l, o) => ({ k, l, t: "d", opt: o });
const m = (k, l, b, o) => ({ k, l, t: "m", b: b || 2400, opt: o });
const q = (k, l, b, o) => ({ k, l, t: "q", b: b || 90, opt: o });
const p = (k, l, b, o) => ({ k, l, t: "p", b: b || 20, opt: o });
const s = (k, l) => ({ k, l, t: "s" });
// Os 4 custom fields do produto + estoque de manufatura (colunas condicionais do vivo).
const CUSTOM_PROD = [t("cf1", "Campo personalizado 1", 1), t("cf2", "Campo personalizado 2", 1), t("cf3", "Campo personalizado 3", 1), t("cf4", "Campo personalizado 4", 1)];
const MFG_STOCK = q("estoqueMfg", "Estoque atual (Manufatura)", 60, 1);

const POOL = { cliente: CLIENTES, contato: CLIENTES.concat(FORNECEDORES), fornecedor: FORNECEDORES, produto: PRODUTOS, categoria: CATEGORIAS, subcategoria: SUBCATEGORIAS, catDespesa: CAT_DESPESA, marca: MARCAS, unidade: UNIDADES, local: LOCAIS, grupo: GRUPOS, usuario: USUARIOS, forma: FORMAS, motivo: MOTIVOS, tipoAjuste: TIPO_AJUSTE, acao: ACOES, tipoReg: TIPO_REG, nota: NOTAS, variacao: VARIACOES, atendente: USUARIOS, agente: USUARIOS, descricao: DESCRICOES, despesaPara: CLIENTES, maquina: MAQUINAS, turno: TURNOS, operador: USUARIOS, etapa: OS_ETAPAS, motivoRet: MOTIVO_RETRABALHO, cf1: ["Cliente final", "Uso interno", "Amostra"], cf2: ["Lote piloto", "Padrão", "—"], cf3: ["Sim", "Não"], cf4: ["A", "B", "C"], dia: ["Segunda", "Terça", "Quarta", "Quinta", "Sexta", "Sábado"] };

// Valor de uma célula a partir do nome da coluna (as chaves seguem o blade).
const cell = (col, i, r) => {
  const k = col.k;
  if (col.t === "d") {
    const base = menos(i * 3 + (k === "validade" ? -120 : 0) + (k === "fabricacao" ? 300 : 0) + (k === "dataVenda" ? -2 : 0));
    if (k === "abertura" || k === "fechamento") return dia(base) + " " + (k === "abertura" ? "08:12" : "18:47");
    return dia(base);
  }
  if (col.t === "m") { const v = col.b * (0.35 + r() * 1.6); return BRL(k.indexOf("dev") === 0 || k === "desconto" ? v / 6 : v); }
  if (col.t === "q") return QTD(Math.round(col.b * (0.2 + r() * 1.5)) / (col.b < 40 ? 1 : 1));
  if (col.t === "p") return PCT(col.b * (0.3 + r() * 1.5));
  if (col.t === "s") return STATUS_PGTO[Math.floor(r() * STATUS_PGTO.length)];
  if (k === "sku") return "PRD-" + String(1 + (i % 12)).padStart(4, "0");
  if (k === "ref" || k === "refCompra") return "COMP-" + (2840 + i);
  if (k === "fatura" || k === "venda") return "VD-" + (9100 + i * 3);
  if (k === "compra") return "COMP-" + (2840 + i);
  if (k === "os") return "OS-" + (4120 + i);
  if (k === "op") return "OP-" + (881 + i);
  if (k === "lote") return "L-" + (2026000 + i * 7);
  if (k === "contatoId") return "CT-" + (1024 + (i % 10));
  if (k === "cnpj") return String(12 + i % 80).padStart(2, "0") + ".345.678/000" + (1 + i % 4) + "-90";
  if (k === "aliquota") return (i % 3 === 0 ? "18" : i % 3 === 1 ? "12" : "5") + "%";
  if (k === "mes") { const dd = menos(i * 30); return String(dd.getMonth() + 1).padStart(2, "0") + "/" + dd.getFullYear(); }
  const pool = POOL[k];
  if (pool) return pool[Math.floor(r() * pool.length)];
  return "—";
};

const gerar = (rep, cols, n) => {
  const r = rng(rep.id + "|" + cols.map((c) => c.k).join(","));
  const rows = [];
  for (let i = 0; i < n; i++) {
    const cells = {};
    cols.forEach((c) => { cells[c.k] = cell(c, i, r); });
    rows.push({ id: rep.id + "-" + i, cells });
  }
  return rows;
};

// Totais do rodapé (<tfoot> do blade) — só colunas de moeda/quantidade visíveis.
const num = (v) => Number(String(v).replace(/[^\d,-]/g, "").replace(/\./g, "").replace(",", ".")) || 0;
const soma = (rows, k) => rows.reduce((a, r) => a + num(r.cells[k]), 0);
const somaSe = (rows, k, campo, valor) => rows.reduce((a, r) => a + (r.cells[campo] === valor ? num(r.cells[k]) : 0), 0);
const totais = (cols, rows) => {
  const out = {};
  cols.forEach((c) => { if (c.t === "m" || c.t === "q") { const soma = rows.reduce((a, row) => a + num(row.cells[c.k]), 0); out[c.k] = c.t === "m" ? BRL(soma) : QTD(soma); } });
  return out;
};

// ─────────── Filtros (exatamente os `Form::label` de cada blade) ───────────
const F = {
  local: { label: "Local", opts: ["Todos"].concat(LOCAIS) },
  cliente: { label: "Cliente", opts: ["Todos"].concat(CLIENTES) },
  fornecedor: { label: "Fornecedor", opts: ["Todos"].concat(FORNECEDORES) },
  contatoTipo: { label: "Tipo", opts: ["Todos", "Cliente", "Fornecedor", "Cliente e fornecedor"] },
  contatoUm: { label: "Contato", opts: ["Todos"].concat(CLIENTES.slice(0, 5)).concat(FORNECEDORES.slice(0, 3)) },
  categoria: { label: "Categoria", opts: ["Todas"].concat(CATEGORIAS) },
  subcategoria: { label: "Subcategoria", opts: ["Todas"].concat(SUBCATEGORIAS) },
  marca: { label: "Marca", opts: ["Todas"].concat(MARCAS) },
  unidade: { label: "Unidade", opts: ["Todas"].concat(UNIDADES) },
  grupo: { label: "Grupo de clientes", opts: ["Todos"].concat(GRUPOS) },
  usuario: { label: "Usuário", opts: ["Todos"].concat(USUARIOS) },
  agente: { label: "Agente comercial", opts: USUARIOS },
  atendente: { label: "Atendente", opts: ["Todos"].concat(USUARIOS) },
  produto: { label: "Produto", opts: ["Todos"].concat(PRODUTOS) },
  tipoProduto: { label: "Tipo de produto", opts: ["Todos", "Único", "Variável", "Composição"] },
  statusPgto: { label: "Status de pagamento", opts: ["Todos"].concat(STATUS_PGTO) },
  statusCompra: { label: "Status da compra", opts: ["Todos", "Recebido", "Parcial", "Pendente", "Ordenado"] },
  statusCaixa: { label: "Status do caixa", opts: ["Todos", "Aberto", "Fechado"] },
  forma: { label: "Forma de pagamento", opts: ["Todas"].concat(FORMAS) },
  catDespesa: { label: "Categoria", opts: ["Todas"].concat(CAT_DESPESA) },
  tipoAjuste: { label: "Tipo de ajuste", opts: ["Todos"].concat(TIPO_AJUSTE) },
  verEstoque: { label: "Ver estoques", opts: ["Todos", "Só com estoque", "Só zerados", "Abaixo do alerta"] },
  limite: { label: "Nº de produtos", opts: ["5", "10", "20", "50"] },
  hora: { label: "Faixa de hora", opts: ["Dia inteiro", "08:00 – 12:00", "12:00 – 18:00", "18:00 – 22:00"] },
  tipoReg: { label: "Tipo de registro", opts: ["Todos"].concat(TIPO_REG) },
  maquina: { label: "Máquina", opts: ["Todas"].concat(MAQUINAS) },
  turno: { label: "Turno", opts: ["Todos"].concat(TURNOS) },
  operador: { label: "Operador", opts: ["Todos"].concat(USUARIOS) },
  etapa: { label: "Etapa da OS", opts: ["Todas"].concat(OS_ETAPAS) },
};

// ─────────── Ações por linha (a coluna `messages.action` do blade) ───────────
const A = {
  verVenda: { id: "verVenda", label: "Ver venda", icon: "receipt", rota: "vendas" },
  verCompra: { id: "verCompra", label: "Ver compra", icon: "truck", rota: "compras" },
  verProduto: { id: "verProduto", label: "Ver produto", icon: "product", rota: "prod-lista" },
  verContato: { id: "verContato", label: "Ver contato", icon: "clients", rota: "clientes" },
  verOS: { id: "verOS", label: "Ver OS", icon: "orders", rota: "os" },
  verPgto: { id: "verPgto", label: "Ver pagamento", icon: "cash", rota: "financeiro" },
  editarAjuste: { id: "editarAjuste", label: "Editar ajuste", icon: "pencil", rota: "compras" },
  editarValidade: { id: "editarValidade", label: "Editar validade", icon: "clock", modal: "validade" },
  verCaixa: { id: "verCaixa", label: "Ver caixa", icon: "cash", rota: "financeiro" },
  imprimirLinha: { id: "imprimirLinha", label: "Imprimir linha", icon: "printer" },
};

// ─────────── Catálogo ───────────
const REPORTS = [
  { id: "profit_loss", label: "Lucros e perdas", legado: "Relatório de lucros / perdas", blade: "report/profit_loss.blade.php", grupo: "Financeiro", desc: "Estoque inicial e final, compras, vendas, despesas — e o lucro que sobra, quebrado por produto, categoria, marca, local, fatura, data, cliente e dia da semana.", filtros: ["local"], kind: "resumo",
    resumo: { esq: { titulo: "Entradas do período", linhas: [["Estoque inicial (por custo)", 184320], ["Estoque inicial (por venda)", 268400], ["Total de compras (s/ imposto e desconto)", 96420], ["Total de ajuste de estoque", 3180], ["Total de despesas", 28740], ["Frete de compra", 2140], ["Despesas adicionais de compra", 860], ["Frete de transferência", 420], ["Total de descontos em vendas", 6180], ["Total de devoluções de venda", 4260]] },
      dir: { titulo: "Saídas do período", linhas: [["Estoque final (por custo)", 171640], ["Estoque final (por venda)", 249180], ["Total de vendas (s/ imposto e desconto)", 214860], ["Frete de venda", 5320], ["Despesas adicionais de venda", 1180], ["Total de estoque recuperado", 1960], ["Total de devoluções de compra", 2340], ["Total de descontos em compras", 1120], ["Arredondamentos de venda", 42]] },
      fecho: [] },
    tabs: [
      { key: "produtos", label: "Por produto", cols: [t("produto", "Produto"), m("lucro", "Lucro bruto", 9800)], acoes: [A.verProduto] },
      { key: "categorias", label: "Por categoria", cols: [t("categoria", "Categoria"), m("lucro", "Lucro bruto", 18400)] },
      { key: "marcas", label: "Por marca", cols: [t("marca", "Marca"), m("lucro", "Lucro bruto", 14200)] },
      { key: "locais", label: "Por local", cols: [t("local", "Local"), m("lucro", "Lucro bruto", 46000)] },
      { key: "fatura", label: "Por fatura", cols: [kk("fatura", "Nº da fatura"), m("lucro", "Lucro bruto", 2400)], acoes: [A.verVenda] },
      { key: "data", label: "Por data", cols: [d("data", "Data"), m("lucro", "Lucro bruto", 4100)] },
      { key: "cliente", label: "Por cliente", cols: [t("cliente", "Cliente"), m("lucro", "Lucro bruto", 8600)], acoes: [A.verContato] },
      { key: "dia", label: "Por dia da semana", cols: [t("dia", "Dia"), m("lucro", "Lucro bruto", 15800)] }] },

  { id: "purchase_sell", label: "Compras e vendas", legado: "Compre e venda", blade: "report/purchase_sell.blade.php", grupo: "Financeiro", desc: "Os dois lados do caixa lado a lado — compras e vendas com e sem imposto, devoluções e o que ficou a pagar e a receber.", filtros: ["local", "cliente"], kind: "resumo",
    resumo: { esq: { titulo: "Compras", linhas: [["Total de compras", 96420], ["Compras c/ imposto", 113780], ["Devoluções de compra c/ imposto", 2340], ["A pagar (compras)", 18640]] },
      dir: { titulo: "Vendas", linhas: [["Total de vendas", 214860], ["Vendas c/ imposto", 253540], ["Devoluções de venda c/ imposto", 4260], ["A receber (vendas)", 41280]] },
      fecho: [["Diferença (vendas − compras)", 139780, "ok"]] } },

  { id: "tax_report", label: "Fiscal", legado: "Relatório fiscal", blade: "report/tax_report.blade.php", grupo: "Fiscal", desc: "Imposto apurado nas entradas, nas saídas e nas despesas — com a base de cálculo e o nº de inscrição do contato em cada linha.", filtros: ["local", "contatoUm"], kind: "abas",
    tabs: [
      { key: "compras", label: "Imposto de entrada", cols: [d("data", "Data"), kk("ref", "Ref."), t("fornecedor", "Fornecedor"), kk("cnpj", "Nº de inscrição"), m("total", "Valor total"), t("forma", "Forma de pgto."), m("desconto", "Desconto", 900), m("imposto", "Imposto", 1600)], acoes: [A.verCompra] },
      { key: "vendas", label: "Imposto de saída", cols: [d("data", "Data"), kk("fatura", "Nº da fatura"), t("cliente", "Cliente"), kk("cnpj", "Nº de inscrição"), m("total", "Valor total"), t("forma", "Forma de pgto."), m("desconto", "Desconto", 900), m("imposto", "Imposto", 2100)], acoes: [A.verVenda] },
      { key: "despesas", label: "Imposto em despesas", cols: [d("data", "Data"), kk("ref", "Ref."), kk("cnpj", "Nº de inscrição"), m("total", "Valor total", 1400), t("forma", "Forma de pgto."), m("imposto", "Imposto", 320)] }] },

  { id: "contact", label: "Clientes e fornecedores", legado: "Relatório de fornecedor e cliente", blade: "report/contact.blade.php", grupo: "Comercial", desc: "Um extrato por contato: quanto comprou, quanto devolveu, quanto vendemos pra ele e o que ainda está devido.", filtros: ["grupo", "contatoTipo", "local", "contatoUm"], kind: "tabela",
    acoes: [A.verContato, A.verVenda],
    cols: [t("contato", "Contato"), m("compras", "Total de compras", 8600), m("devCompra", "Devolução de compra", 8600), m("vendas", "Total de vendas", 14200), m("devVenda", "Devolução de venda", 14200), m("saldoInicial", "Saldo inicial devido", 2600), m("devido", "Total devido", 4200)] },

  { id: "customer_group", label: "Grupos de clientes", legado: "Relatório de grupos de clientes", blade: "report/customer_group.blade.php", grupo: "Comercial", desc: "Quanto cada grupo de preço vendeu no período — a leitura que decide tabela de desconto.", filtros: ["grupo", "local"], kind: "tabela",
    cols: [t("grupo", "Grupo de clientes"), m("vendas", "Total vendido", 42800)], n: 5 },

  { id: "sale_report", label: "Vendas", legado: "Relatório de vendas", blade: "report/sale_report.blade.php", grupo: "Comercial", desc: "Toda venda do período com total sem e com imposto, desconto e forma de pagamento.", filtros: ["local", "cliente", "statusPgto", "forma"], filtroNota: "os filtros vêm da tela de Vendas (o blade é só a tabela)", kind: "tabela",
    acoes: [A.verVenda, A.verContato, A.imprimirLinha],
    cols: [kk("contatoId", "ID do contato"), t("cliente", "Cliente"), kk("fatura", "Nº da fatura"), d("data", "Data"), m("totalExc", "Total (s/ imposto)"), m("desconto", "Desconto", 900), m("imposto", "Imposto", 430), m("totalInc", "Total (c/ imposto)", 2830), t("forma", "Forma de pgto.")] },

  { id: "purchase_report", label: "Compras", legado: "Relatório de compras", blade: "report/purchase_report.blade.php", grupo: "Comercial", desc: "Compras do período com data de compra e de pagamento — por mês e por dia, como no relatório do vivo.", filtros: ["local", "fornecedor", "statusCompra", "statusPgto"], kind: "tabela",
    acoes: [A.verCompra, A.verContato],
    cols: [kk("contatoId", "ID do contato"), t("fornecedor", "Fornecedor"), kk("ref", "Ref."), kk("mes", "Compra (ano-mês)"), d("data", "Compra (dia)"), m("totalExc", "Total (s/ imposto)", 1800), m("desconto", "Desconto", 700), m("imposto", "Imposto", 320), m("totalInc", "Total (c/ imposto)", 2120), t("forma", "Forma de pgto.")] },

  { id: "sell_payment_report", label: "Pagamentos de venda", legado: "vender relatório de pagamento", blade: "report/sell_payment_report.blade.php", grupo: "Financeiro", desc: "Cada baixa recebida: quando entrou, por qual forma e em qual venda.", filtros: ["cliente", "local", "forma", "grupo"], kind: "tabela",
    acoes: [A.verPgto, A.verVenda],
    cols: [kk("ref", "Ref. do pagamento"), d("data", "Pago em"), m("valor", "Valor", 1400), t("cliente", "Cliente"), t("grupo", "Grupo"), t("forma", "Forma de pgto."), kk("venda", "Venda")] },

  { id: "purchase_payment_report", label: "Pagamentos de compra", legado: "Relatório de pagamento de compra", blade: "report/purchase_payment_report.blade.php", grupo: "Financeiro", desc: "Cada baixa paga a fornecedor: quando saiu, por qual forma e em qual compra.", filtros: ["fornecedor", "local"], kind: "tabela",
    acoes: [A.verPgto, A.verCompra],
    cols: [kk("ref", "Ref. do pagamento"), d("data", "Pago em"), m("valor", "Valor", 1100), t("fornecedor", "Fornecedor"), t("forma", "Forma de pgto."), kk("compra", "Compra")] },

  { id: "expense_report", label: "Despesas", legado: "Relatório de despesas", blade: "report/expense_report.blade.php", grupo: "Financeiro", desc: "Despesa somada por categoria no período — o outro lado do lucro.", filtros: ["local", "catDespesa"], kind: "tabela",
    cols: [t("catDespesa", "Categoria de despesa"), m("total", "Total da despesa", 6200)], n: 7 },

  { id: "register_report", label: "Caixa (registro)", legado: "Relatório de registro", blade: "report/register_report.blade.php", grupo: "Financeiro", desc: "Abertura e fechamento de caixa por operador, com o total por meio de recebimento.", filtros: ["usuario", "statusCaixa"], kind: "tabela",
    acoes: [A.verCaixa, A.imprimirLinha],
    cols: [d("abertura", "Abertura"), d("fechamento", "Fechamento"), t("local", "Local"), t("usuario", "Operador"), m("cartao", "Cartão", 3200), m("cheque", "Cheque", 800), m("dinheiro", "Dinheiro", 2400), m("transferencia", "Transferência", 1900), m("adiantamento", "Adiantamento", 600), m("outros", "Outros", 400), m("total", "Total", 9300)], n: 8 },

  { id: "sales_representative", label: "Agente comercial", legado: "Relatório do Agente Comercial", blade: "report/sales_representative.blade.php", grupo: "Comercial", desc: "A conta do vendedor: vendas atribuídas, comissão apurada, despesas lançadas e pagamentos que entraram com comissão.", filtros: ["agente", "local"], kind: "abas",
    tabs: [
      { key: "vendas", label: "Vendas", cols: [d("data", "Data"), kk("fatura", "Nº da fatura"), t("cliente", "Cliente"), t("local", "Local"), s("status", "Status de pgto."), m("total", "Total"), m("pago", "Total pago", 2000), m("saldo", "Total restante", 800)], acoes: [A.verVenda] },
      { key: "comissao", label: "Comissão", cols: [d("data", "Data"), kk("fatura", "Nº da fatura"), t("cliente", "Cliente"), t("local", "Local"), s("status", "Status de pgto."), m("total", "Total"), m("comissao", "Comissão", 180), m("saldo", "Restante", 800)], acoes: [A.verVenda] },
      { key: "despesas", label: "Despesas", cols: [d("data", "Data"), kk("ref", "Ref."), t("catDespesa", "Categoria"), t("local", "Local"), s("status", "Status de pgto."), m("total", "Total", 1200), t("despesaPara", "Despesa para"), t("nota", "Observação")] },
      { key: "pagamentos", label: "Pagamentos c/ comissão", cols: [kk("ref", "Ref."), d("data", "Pago em"), m("valor", "Valor", 1400), t("cliente", "Cliente"), t("forma", "Forma de pgto."), kk("venda", "Venda")], acoes: [A.verPgto, A.verVenda] }] },

  { id: "stock_report", label: "Estoque", legado: "relatório de estoque", blade: "report/partials/stock_report_table.blade.php", grupo: "Estoque", desc: "Estoque atual por produto e local, com valor a custo e a venda, lucro potencial e o que já saiu.", filtros: ["local", "categoria", "subcategoria", "marca", "unidade"], kind: "tabela",
    acoes: [A.verProduto, A.imprimirLinha], n: 24,
    cols: [kk("sku", "SKU"), t("produto", "Produto"), t("variacao", "Variação"), t("categoria", "Categoria"), t("local", "Local"), m("precoVenda", "Preço de venda unit.", 90), q("estoque", "Estoque atual", 240), m("estoqueCusto", "Valor de estoque (custo)", 4800), m("estoqueVenda", "Valor de estoque (venda)", 7600), m("lucroPot", "Lucro potencial", 2800), q("vendido", "Total vendido", 160), q("transferido", "Transferido", 40), q("ajustado", "Ajustado", 18)].concat(CUSTOM_PROD).concat([MFG_STOCK]) },

  { id: "product_stock_details", label: "Detalhes de estoque do produto", legado: "Detalhes de estoque", blade: "report/product_stock_details.blade.php", grupo: "Estoque", desc: "A conta completa de um produto num local: de onde veio cada unidade e por que o saldo é esse.", filtros: ["produto", "local"], kind: "resumo",
    resumo: { esq: { titulo: "Entradas", linhas: [["Estoque inicial", 420], ["Total comprado", 1860], ["Devoluções de venda", 24], ["Transferido para o local", 180], ["Ajuste de estoque (entrada)", 36]] },
      dir: { titulo: "Saídas", linhas: [["Total vendido", 1624], ["Devoluções de compra", 18], ["Transferido do local", 96], ["Ajuste de estoque (saída)", 58]] },
      fecho: [["Estoque total (calculado)", 724, "ok"], ["Estoque disponível", 712, "warn"]], unidade: "m²" } },

  { id: "lot_report", label: "Lotes", legado: "Relatório em lote", blade: "report/lot_report.blade.php", grupo: "Estoque", desc: "Saldo por número de lote, com validade — a rastreabilidade que a bobina exige.", filtros: ["local", "categoria", "subcategoria", "marca", "unidade"], kind: "tabela",
    acoes: [A.verProduto], n: 18,
    cols: [kk("sku", "SKU"), t("produto", "Produto"), kk("lote", "Nº do lote"), d("validade", "Validade"), q("estoque", "Estoque atual", 180), q("vendido", "Total vendido", 120), q("ajustado", "Total ajustado", 14)] },

  { id: "stock_expiry_report", label: "Vencimento de estoque", legado: "Relatório de vencimento", blade: "report/stock_expiry_report.blade.php", grupo: "Estoque", desc: "O que vence primeiro, por lote e local — pra queimar antes de virar perda.", filtros: ["local", "categoria", "subcategoria", "marca", "unidade", "verEstoque"], kind: "tabela",
    acoes: [A.editarValidade, A.verProduto], n: 16,
    cols: [t("produto", "Produto"), kk("sku", "SKU"), t("local", "Local"), q("restante", "Estoque restante", 90), kk("lote", "Nº do lote"), d("validade", "Validade"), d("fabricacao", "Fabricação")] },

  { id: "stock_adjustment_report", label: "Ajustes de estoque", legado: "relatório de ajuste de ações", blade: "report/stock_adjustment_report.blade.php", grupo: "Estoque", desc: "Toda baixa manual de estoque com motivo, tipo (normal ou anormal) e quanto foi recuperado.", filtros: ["local", "tipoAjuste"], kind: "tabela",
    acoes: [A.editarAjuste, A.imprimirLinha], n: 16,
    cols: [d("data", "Data"), kk("ref", "Ref."), t("local", "Local"), t("tipoAjuste", "Tipo de ajuste"), m("total", "Valor total", 900), m("recuperado", "Valor recuperado", 300), t("motivo", "Motivo do ajuste"), t("usuario", "Criado por")] },

  { id: "product_purchase_report", label: "Compras por produto", legado: "Relatório de compra do produto", blade: "report/product_purchase_report.blade.php", grupo: "Estoque", desc: "Quanto de cada produto entrou, de qual fornecedor e a que preço unitário.", filtros: ["produto", "fornecedor", "local", "marca"], kind: "tabela",
    acoes: [A.verCompra, A.verProduto], n: 20,
    cols: [t("produto", "Produto"), kk("sku", "SKU"), t("fornecedor", "Fornecedor"), kk("ref", "Ref."), d("data", "Data"), q("qtd", "Qtd.", 60), q("ajustado", "Ajustado", 8), m("precoCompra", "Preço unit. de compra", 70), m("subtotal", "Subtotal", 1800)] },

  { id: "product_sell_report", label: "Vendas por produto", legado: "Relatório de venda do produto", blade: "report/product_sell_report.blade.php", grupo: "Estoque", desc: "A saída por produto em três leituras: linha a linha da venda, por lote de origem e o total por produto.", filtros: ["produto", "cliente", "grupo", "local", "categoria", "marca", "hora"], kind: "abas",
    tabs: [
      { key: "venda", label: "Por venda", cols: [t("produto", "Produto"), kk("sku", "SKU")].concat(CUSTOM_PROD.slice(0, 2)).concat([t("cliente", "Cliente"), kk("contatoId", "ID do contato"), kk("fatura", "Nº da fatura"), d("data", "Data"), q("qtd", "Qtd.", 40), m("precoUnit", "Preço unit.", 80), m("desconto", "Desconto", 30), m("imposto", "Imposto", 60), m("precoIncImposto", "Preço c/ imposto", 140), m("total", "Total", 1600), t("forma", "Forma de pgto.")]), acoes: [A.verVenda, A.verProduto], n: 20 },
      { key: "lote", label: "Por lote", cols: [t("produto", "Produto"), kk("sku", "SKU"), t("cliente", "Cliente"), kk("fatura", "Nº da fatura"), d("data", "Data"), kk("refCompra", "Ref. da compra"), kk("lote", "Nº do lote"), t("fornecedor", "Fornecedor"), q("qtd", "Qtd.", 40)], acoes: [A.verVenda, A.verCompra], n: 18 },
      { key: "produto", label: "Por produto", cols: [t("produto", "Produto"), kk("sku", "SKU"), d("data", "Última venda"), q("estoque", "Estoque atual", 200), q("vendido", "Total vendido", 150), m("total", "Total", 6800)], acoes: [A.verProduto] }] },

  { id: "items_report", label: "Itens", legado: "Relatório de Itens", blade: "report/items_report.blade.php", grupo: "Estoque", desc: "A vida de cada item da compra até a venda numa linha só — a rastreabilidade que a auditoria pede.", filtros: ["fornecedor", "cliente", "local"], periodos: ["Data da compra", "Data da venda"], kind: "tabela",
    acoes: [A.verCompra, A.verVenda, A.verProduto], n: 20,
    cols: [t("produto", "Produto"), kk("sku", "SKU"), t("descricao", "Descrição"), d("data", "Data da compra"), kk("compra", "Compra"), kk("lote", "Nº do lote"), t("fornecedor", "Fornecedor"), m("precoCompra", "Preço de compra", 70), d("dataVenda", "Data da venda"), kk("venda", "Venda"), t("cliente", "Cliente"), t("local", "Local"), q("qtd", "Qtd. vendida", 30), m("precoVenda", "Preço de venda", 120), m("subtotal", "Subtotal", 1400)] },

  { id: "trending_products", label: "Produtos em tendência", legado: "Produtos de tendência", blade: "report/trending_products.blade.php", grupo: "Estoque", desc: "O ranking do que está saindo mais no período — filtra por categoria, marca, unidade e tipo.", filtros: ["local", "categoria", "subcategoria", "marca", "unidade", "limite", "tipoProduto"], kind: "grafico",
    acoes: [A.verProduto],
    cols: [t("produto", "Produto"), kk("sku", "SKU"), t("categoria", "Categoria"), q("vendido", "Total vendido", 180), m("total", "Total vendido (R$)", 9800)], n: 8 },

  { id: "service_staff_report", label: "Atendentes", legado: "Informe del personal de servicio", blade: "report/service_staff_report.blade.php", grupo: "Comercial", modulo: "Restaurante", desc: "Pedidos por atendente — do módulo Restaurante, ativo só quando a operação usa mesas.", filtros: ["local", "atendente"], kind: "abas",
    tabs: [
      { key: "pedidos", label: "Pedidos", cols: [d("data", "Data"), kk("fatura", "Nº da fatura"), t("atendente", "Atendente"), t("local", "Local"), m("subtotal", "Subtotal", 800), m("desconto", "Desconto total", 90), m("imposto", "Imposto total", 140), m("total", "Valor total", 950)], acoes: [A.verVenda] },
      { key: "itens", label: "Itens do pedido", cols: [d("data", "Data"), kk("fatura", "Nº da fatura"), t("atendente", "Atendente"), t("produto", "Produto"), q("qtd", "Qtd.", 12), m("precoUnit", "Preço unit.", 70), m("desconto", "Desconto", 20), m("imposto", "Imposto", 30), m("precoLiquido", "Preço líquido", 90), m("total", "Total", 420)], acoes: [A.verVenda] }] },

  { id: "activity_log", label: "Registro de atividade", legado: "Registro de atividade", blade: "report/activity_log.blade.php", grupo: "Sistema", desc: "Quem mexeu em quê e quando — a trilha que resolve discussão de balcão.", filtros: ["usuario", "tipoReg"], kind: "tabela", n: 28,
    cols: [d("data", "Data"), t("tipoReg", "Tipo de registro"), t("acao", "Ação"), t("usuario", "Por"), t("nota", "Observação")] },

  // ─────────── Onda 3 — relatórios NOVOS de gráfica (não existem no Blade) ───────────
  { id: "cv_m2", label: "m² produzido vs vendido", legado: null, blade: null, novo: true, grupo: "Gráfica", desc: "A conta que o UltimatePOS não faz: metro quadrado que saiu da máquina contra o metro quadrado faturado — e a perda no meio.", filtros: ["local", "maquina", "turno", "operador"], kind: "tabela",
    acoes: [A.verOS, A.verProduto], n: 18,
    cols: [d("data", "Data"), t("maquina", "Máquina"), t("turno", "Turno"), t("operador", "Operador"), kk("op", "OP"), q("m2Prod", "m² produzidos", 90), q("m2Vend", "m² vendidos", 84), q("m2Perda", "m² de perda", 8), p("perdaPct", "Perda", 9), m("receita", "Receita", 3800)] },

  { id: "cv_bobina", label: "Sobra de bobina por lote", legado: null, blade: null, novo: true, grupo: "Gráfica", desc: "Quanto sobrou em cada bobina e se a sobra ainda é aproveitável — o dinheiro que fica no rolo em pé no canto.", filtros: ["local", "categoria", "marca", "verEstoque"], kind: "tabela",
    acoes: [A.verProduto, A.editarAjuste], n: 16,
    cols: [kk("lote", "Nº do lote"), t("produto", "Material"), t("marca", "Marca"), t("local", "Local"), q("larguraCm", "Largura (cm)", 100), q("metrosIni", "Metros iniciais", 50), q("metrosRest", "Sobra (m)", 9), p("sobraPct", "Sobra", 14), m("valorSobra", "Valor da sobra", 320), t("aproveita", "Aproveitável")] },

  { id: "cv_lucro_os", label: "Lucro por OS", legado: null, blade: null, novo: true, grupo: "Gráfica", desc: "Receita menos material, hora de máquina e instalação, OS por OS — onde o orçamento errou o preço.", filtros: ["local", "cliente", "etapa"], kind: "tabela",
    acoes: [A.verOS, A.verContato, A.imprimirLinha], n: 18,
    cols: [kk("os", "OS"), t("cliente", "Cliente"), d("data", "Entrega"), t("etapa", "Etapa"), q("m2Vend", "m²", 40), m("receita", "Receita", 4200), m("custoMaterial", "Material", 1600), m("custoMaquina", "Hora de máquina", 480), m("custoInstal", "Instalação", 620), m("lucro", "Lucro", 1400), p("margem", "Margem", 26)] },

  { id: "cv_retrabalho", label: "Retrabalho", legado: null, blade: null, novo: true, grupo: "Gráfica", desc: "Toda OS que voltou: por que voltou, quanto custou refazer e de quem foi a decisão que gerou o refugo.", filtros: ["local", "maquina", "operador", "etapa"], kind: "tabela",
    acoes: [A.verOS, A.verContato], n: 14,
    cols: [kk("os", "OS"), d("data", "Data"), t("cliente", "Cliente"), t("etapa", "Etapa em que voltou"), t("motivoRet", "Motivo"), t("operador", "Responsável"), q("m2Perda", "m² refugados", 14), m("custoRet", "Custo do retrabalho", 640), t("nota", "Observação")] },
];

// GST (Índia) e mesas ficam fora — declarado, não escondido.
const FORA = [
  { blade: "report/gst_sales_report.blade.php", label: "GST sales report", motivo: "Fiscal da Índia — no Brasil a apuração é NF-e/NFS-e (módulo Fiscal)." },
  { blade: "report/gst_purchase_report.blade.php", label: "GST purchase report", motivo: "Fiscal da Índia — sem equivalente na apuração brasileira." },
  { blade: "report/table_report.blade.php", label: "Relatório de mesas", motivo: "Módulo Restaurante, sem uso no piloto de comunicação visual." },
];

const GRUPOS_ORDEM = ["Financeiro", "Comercial", "Estoque", "Fiscal", "Gráfica", "Sistema"];

// Os plates do topo e o fecho do resumo são APURADOS das linhas — no blade esses <th> são o
// rodapé do mesmo dataset (report.total_normal/total_abnormal/total_stock_adjustment/total_recovered;
// profit_loss fecha com a soma dos profit_by_*). Constante ali mentiria sobre o período.
const derivar = (rep, rows, tab) => {
  if (!rows || !rows.length) return null;
  const S = (k) => soma(rows, k);
  if (rep.id === "stock_adjustment_report") {
    const normal = somaSe(rows, "total", "tipoAjuste", "Normal");
    const anormal = somaSe(rows, "total", "tipoAjuste", "Anormal");
    return { kpis: [["Total normal", normal, "info"], ["Total anormal", anormal, "warning"], ["Total de ajuste", normal + anormal, "danger"], ["Total recuperado", S("recuperado"), "success"]] };
  }
  if (rep.id === "profit_loss") {
    const bruto = S("lucro");
    const despesas = 28740; // linha "Total de despesas" do painel de entradas (opening_stock)
    return { fecho: [["Lucro bruto (" + (tab ? tab.label.toLowerCase() : "apuração") + ")", bruto, "ok"], ["Lucro líquido", bruto - despesas, "ok"]] };
  }
  if (rep.id === "cv_m2") {
    const prod = S("m2Prod"), vend = S("m2Vend"), perda = S("m2Perda");
    return { kpis: [["m² produzidos", prod, "info"], ["m² vendidos", vend, "success"], ["m² de perda", perda, "danger"], ["Perda sobre produção", prod ? perda / prod * 100 : 0, "warning"]] };
  }
  if (rep.id === "cv_lucro_os") {
    const receita = S("receita"), custo = S("custoMaterial") + S("custoMaquina") + S("custoInstal"), lucro = S("lucro");
    return { kpis: [["Receita das OS", receita, "info"], ["Custo total", custo, "warning"], ["Lucro", receita - custo, "success"], ["Margem média", receita ? (receita - custo) / receita * 100 : 0, "info"]] };
  }
  if (rep.id === "cv_retrabalho") {
    const TOTAL_OS_PERIODO = 230; // base do período (vem do módulo OS no vivo)
    return { kpis: [["OS retrabalhadas", rows.length, "warning"], ["Custo do retrabalho", S("custoRet"), "danger"], ["m² refugados", S("m2Perda"), "danger"], ["Sobre total de OS", rows.length / TOTAL_OS_PERIODO * 100, "info"]] };
  }
  return null;
};

window.RELD = { REPORTS, FORA, F, A, GRUPOS_ORDEM, gerar, totais, derivar, soma, num, BRL, QTD, PCT, dia, menos, hoje, LOCAIS, USUARIOS, PRODUTOS };
})();
