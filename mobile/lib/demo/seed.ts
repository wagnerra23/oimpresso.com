import type {
  Approval,
  Artwork,
  Company,
  CompanySettings,
  Customer,
  FiscalDoc,
  InventoryItem,
  Movement,
  OP,
  PaymentRow,
  PedidoItem,
  PedidoRow,
  PriceTable,
  Produto,
  QuoteItem,
  QuoteRow,
  ServiceOrderRow,
  SOItem,
  SOPhoto,
  Store,
  Transacao,
  UUID,
  VehicleRow,
  WaMessage,
} from "./types";

/**
 * Sample data for demo mode: a sign shop (comunicação visual) with a small
 * auto-repair side, so every module has something to show. All names,
 * documents and phone numbers are fictional.
 */

// Deterministic, valid UUIDs: `kind` is a 2-hex-digit entity tag. The first
// block is hashed because screens show `id.slice(0, 8)` as the record number.
export function sid(kind: string, n: number): UUID {
  const head = (Math.imul(n + parseInt(kind, 16) * 131, 2654435761) >>> 0).toString(16).padStart(8, "0");
  return `${head}-0000-4000-8000-${kind}${String(n).padStart(10, "0")}`;
}

export function daysAgo(days: number, hour = 10, minute = 0): string {
  const d = new Date();
  d.setDate(d.getDate() - days);
  d.setHours(hour, minute, 0, 0);
  return d.toISOString();
}

export function daysAhead(days: number): string {
  return daysAgo(-days, 18);
}

export const round2 = (v: number) => Math.round(v * 100) / 100;

// ─── Builders (fill every nullable column with null) ─────────────────────────

export function customer(p: Partial<Customer> & Pick<Customer, "id" | "nome">): Customer {
  return {
    tipo: "PJ",
    documento: null,
    telefone: null,
    email: null,
    endereco: null,
    observacoes: null,
    papeis: ["cliente"],
    razaoSocial: null,
    nomeFantasia: null,
    inscricaoEstadual: null,
    indicadorIe: null,
    cep: null,
    logradouro: null,
    numero: null,
    complemento: null,
    bairro: null,
    cidade: "Campinas",
    uf: "SP",
    codigoMunicipioIbge: "3509502",
    whatsapp: p.telefone ?? null,
    emailNfe: null,
    aceitaWhatsapp: true,
    aceitaEmail: true,
    aceitaSms: false,
    consentimentoData: daysAgo(120),
    consentimentoIp: null,
    classificacao: "B",
    limiteCredito: null,
    prazoPadraoDias: 28,
    createdAt: daysAgo(180),
    updatedAt: daysAgo(20),
    ...p,
  };
}

export function produto(p: Partial<Produto> & Pick<Produto, "id" | "nome" | "categoria" | "preco">): Produto {
  return {
    tipoItem: "produto",
    sku: null,
    gtin: null,
    ncm: null,
    cfop: "5102",
    cest: null,
    origem: "0",
    unidade: "UN",
    precoCusto: null,
    precoPromo: null,
    margemLucroPercent: null,
    controlaEstoque: false,
    estoqueAtual: null,
    estoqueMinimo: null,
    localizacao: null,
    fornecedorId: null,
    imagemPrincipal: null,
    gramatura: null,
    acabamento: null,
    descricao: null,
    ...p,
  };
}

export function stock(p: Partial<InventoryItem> & Pick<InventoryItem, "id" | "nome" | "quantidade" | "estoqueMinimo" | "custoUnit">): InventoryItem {
  return {
    produtoId: null,
    codigo: null,
    unidade: "UN",
    precoVenda: null,
    fornecedorPrincipal: null,
    localizacao: null,
    ativo: true,
    createdAt: daysAgo(150),
    updatedAt: daysAgo(3),
    ...p,
  };
}

// ─── Ids shared across entities ─────────────────────────────────────────────

const C = {
  padaria: sid("c0", 1),
  autoCenter: sid("c0", 2),
  clinica: sid("c0", 3),
  mercado: sid("c0", 4),
  academia: sid("c0", 5),
  carlos: sid("c0", 6),
  mariana: sid("c0", 7),
  roberto: sid("c0", 8),
  lonaCia: sid("c0", 9),
  vinilSul: sid("c0", 10),
};

const P = {
  banner: sid("a0", 1),
  adesivo: sid("a0", 2),
  fachada: sid("a0", 3),
  placa: sid("a0", 4),
  letraCaixa: sid("a0", 5),
  logo: sid("a0", 6),
  envelopamento: sid("a0", 7),
  instalacao: sid("a0", 8),
  revisao: sid("a0", 9),
  freio: sid("a0", 10),
};

const PED = Array.from({ length: 9 }, (_, i) => sid("b0", i + 1));
const VEH = Array.from({ length: 4 }, (_, i) => sid("e0", i + 1));
const OS = Array.from({ length: 5 }, (_, i) => sid("e1", i + 1));
const INV = Array.from({ length: 9 }, (_, i) => sid("f0", i + 1));
const QT = Array.from({ length: 4 }, (_, i) => sid("a1", i + 1));

// Small inline SVG "photos" so the OS gallery and artwork previews render
// without any network access.
export function svgPhoto(label: string, bg: string): string {
  const svg =
    `<svg xmlns="http://www.w3.org/2000/svg" width="640" height="480" viewBox="0 0 640 480">` +
    `<rect width="640" height="480" fill="${bg}"/>` +
    `<circle cx="470" cy="150" r="70" fill="#ffffff" opacity=".18"/>` +
    `<path d="M0 380 L180 250 L300 340 L420 220 L640 380 L640 480 L0 480Z" fill="#000" opacity=".22"/>` +
    `<text x="40" y="440" font-family="Arial, sans-serif" font-size="30" fill="#fff">${label}</text>` +
    `</svg>`;
  return `data:image/svg+xml;charset=utf-8,${encodeURIComponent(svg)}`;
}

// ─── Seed ───────────────────────────────────────────────────────────────────

export function createSeed(): Store {
  const companies: Company[] = [
    {
      id: sid("00", 1),
      ownerUserId: 1,
      nome: "Oimpresso Comunicação Visual",
      vertical: "cv",
      ativa: true,
      createdAt: daysAgo(240),
      updatedAt: daysAgo(10),
      role: "owner",
    },
    {
      id: sid("00", 2),
      ownerUserId: 1,
      nome: "Oimpresso Auto Center",
      vertical: "mecanica",
      ativa: true,
      createdAt: daysAgo(90),
      updatedAt: daysAgo(10),
      role: "admin",
    },
  ];

  const customers: Customer[] = [
    customer({
      id: C.padaria,
      nome: "Padaria Pão Dourado",
      razaoSocial: "Pão Dourado Panificadora Ltda",
      nomeFantasia: "Padaria Pão Dourado",
      documento: "12.345.678/0001-90", // pii-allowlist (documento fictício da demonstração)
      telefone: "(19) 99812-4410",
      email: "compras@paodourado.exemplo.com.br",
      logradouro: "Av. Brasil",
      numero: "1520",
      bairro: "Jardim Guanabara",
      cep: "13073-148",
      endereco: "Av. Brasil, 1520 — Jardim Guanabara",
      indicadorIe: "contribuinte",
      inscricaoEstadual: "244.118.502.110",
      classificacao: "A",
      limiteCredito: 8000,
    }),
    customer({
      id: C.autoCenter,
      nome: "Auto Center Silva",
      razaoSocial: "Silva & Filhos Serviços Automotivos Ltda",
      documento: "23.456.789/0001-01", // pii-allowlist (documento fictício da demonstração)
      telefone: "(19) 99745-2231",
      email: "contato@autosilva.exemplo.com.br",
      endereco: "R. Barão de Jaguara, 880 — Centro",
      classificacao: "B",
    }),
    customer({
      id: C.clinica,
      nome: "Clínica Sorriso",
      razaoSocial: "Sorriso Odontologia Integrada S/S",
      documento: "34.567.890/0001-12", // pii-allowlist (documento fictício da demonstração)
      telefone: "(19) 99630-7788",
      email: "adm@clinicasorriso.exemplo.com.br",
      endereco: "R. Coronel Quirino, 410 — Cambuí",
      classificacao: "A",
    }),
    customer({
      id: C.mercado,
      nome: "Mercado Bom Preço",
      razaoSocial: "Bom Preço Supermercados Ltda",
      documento: "45.678.901/0001-23", // pii-allowlist (documento fictício da demonstração)
      telefone: "(19) 99521-3304",
      endereco: "Av. John Boyd Dunlop, 3100 — Jardim Ipaussurama",
      classificacao: "B",
      prazoPadraoDias: 35,
    }),
    customer({
      id: C.academia,
      nome: "Studio Fit Academia",
      documento: "56.789.012/0001-34", // pii-allowlist (documento fictício da demonstração)
      telefone: "(19) 99410-9087",
      email: "studiofit@exemplo.com.br",
      endereco: "R. Maria Monteiro, 77 — Cambuí",
      classificacao: "C",
    }),
    customer({
      id: C.carlos,
      nome: "Carlos Eduardo Ramos",
      tipo: "PF",
      documento: "123.456.789-09", // pii-allowlist (documento fictício da demonstração)
      telefone: "(19) 99302-1150",
      email: "carlos.ramos@exemplo.com.br",
      endereco: "R. Dr. Quirino, 55 — Botafogo",
      classificacao: "B",
      prazoPadraoDias: null,
    }),
    customer({
      id: C.mariana,
      nome: "Mariana Lopes",
      tipo: "PF",
      documento: "987.654.321-00", // pii-allowlist (documento fictício da demonstração)
      telefone: "(19) 99277-6612",
      email: "mariana.lopes@exemplo.com.br",
      endereco: "Av. Orosimbo Maia, 900 — Vila Itapura",
      classificacao: "A",
      prazoPadraoDias: null,
    }),
    customer({
      id: C.roberto,
      nome: "Transportadora Rota Sul",
      documento: "67.890.123/0001-45", // pii-allowlist (documento fictício da demonstração)
      telefone: "(19) 99188-4521",
      email: "frota@rotasul.exemplo.com.br",
      endereco: "Rod. Anhanguera, km 98 — Distrito Industrial",
      papeis: ["cliente", "transportadora"],
      classificacao: "A",
      limiteCredito: 15000,
    }),
    customer({
      id: C.lonaCia,
      nome: "Lona & Cia Distribuidora",
      documento: "78.901.234/0001-56", // pii-allowlist (documento fictício da demonstração)
      telefone: "(11) 98877-1020",
      email: "vendas@lonaecia.exemplo.com.br",
      cidade: "São Paulo",
      codigoMunicipioIbge: "3550308",
      papeis: ["fornecedor"],
      classificacao: null,
    }),
    customer({
      id: C.vinilSul,
      nome: "Vinil Sul Materiais",
      documento: "89.012.345/0001-67", // pii-allowlist (documento fictício da demonstração)
      telefone: "(41) 98765-3344",
      cidade: "Curitiba",
      uf: "PR",
      codigoMunicipioIbge: "4106902",
      papeis: ["fornecedor"],
      classificacao: null,
    }),
  ];

  const produtos: Produto[] = [
    produto({
      id: P.banner,
      nome: "Banner lona 440g",
      categoria: "Impressão digital",
      preco: 55,
      sku: "CV-BAN-440",
      ncm: "3921.90.19",
      unidade: "M2",
      precoCusto: 21,
      margemLucroPercent: 61.8,
      controlaEstoque: true,
      estoqueAtual: 62,
      estoqueMinimo: 80,
      gramatura: "440 g/m²",
      acabamento: "Bainha + ilhós a cada 50 cm",
      fornecedorId: C.lonaCia,
      localizacao: "Prateleira A2",
      descricao: "Lona frontlight fosca com impressão solvente 1440 dpi.",
    }),
    produto({
      id: P.adesivo,
      nome: "Adesivo vinil recortado",
      categoria: "Adesivos",
      preco: 42,
      sku: "CV-ADE-REC",
      ncm: "3919.90.00",
      unidade: "M2",
      precoCusto: 14,
      margemLucroPercent: 66.7,
      controlaEstoque: true,
      estoqueAtual: 140,
      estoqueMinimo: 50,
      acabamento: "Recorte eletrônico + máscara de transferência",
      fornecedorId: C.vinilSul,
    }),
    produto({
      id: P.fachada,
      nome: "Fachada em ACM",
      categoria: "Fachadas",
      preco: 380,
      sku: "CV-FAC-ACM",
      ncm: "7606.12.90",
      unidade: "M2",
      precoCusto: 190,
      margemLucroPercent: 50,
      acabamento: "Estrutura metalon 20x20 + ACM 3 mm",
    }),
    produto({
      id: P.placa,
      nome: "Placa PVC 3 mm",
      categoria: "Placas",
      preco: 120,
      sku: "CV-PLA-PVC3",
      unidade: "M2",
      precoCusto: 48,
      margemLucroPercent: 60,
      controlaEstoque: true,
      estoqueAtual: 18,
      estoqueMinimo: 10,
      acabamento: "Adesivo impresso laminado",
    }),
    produto({
      id: P.letraCaixa,
      nome: "Letra caixa em aço escovado",
      categoria: "Fachadas",
      preco: 260,
      sku: "CV-LET-ACO",
      unidade: "UN",
      precoCusto: 120,
      margemLucroPercent: 53.8,
      descricao: "Altura até 40 cm, com LED interno.",
    }),
    produto({
      id: P.logo,
      nome: "Criação de logotipo",
      categoria: "Design",
      preco: 650,
      tipoItem: "servico",
      sku: "SV-DES-LOGO",
      cfop: "5933",
      unidade: "SV",
      descricao: "3 propostas + manual de marca simplificado.",
    }),
    produto({
      id: P.envelopamento,
      nome: "Envelopamento de frota",
      categoria: "Adesivos",
      preco: 1850,
      tipoItem: "servico",
      sku: "SV-ENV-FROTA",
      cfop: "5933",
      unidade: "VEIC",
      descricao: "Arte + impressão + aplicação por veículo utilitário.",
    }),
    produto({
      id: P.instalacao,
      nome: "Instalação em altura",
      categoria: "Serviços",
      preco: 280,
      tipoItem: "servico",
      sku: "SV-INST-ALT",
      cfop: "5933",
      unidade: "H",
    }),
    produto({
      id: P.revisao,
      nome: "Revisão completa (mão de obra)",
      categoria: "Oficina",
      preco: 320,
      tipoItem: "servico",
      sku: "MEC-REV-COMP",
      cfop: "5933",
      unidade: "SV",
    }),
    produto({
      id: P.freio,
      nome: "Pastilha de freio dianteira",
      categoria: "Peças",
      preco: 189,
      sku: "MEC-PAS-DIA",
      gtin: "7891234567895",
      ncm: "8708.30.90",
      unidade: "JG",
      precoCusto: 96,
      margemLucroPercent: 49.2,
      controlaEstoque: true,
      estoqueAtual: 3,
      estoqueMinimo: 4,
      localizacao: "Box peças — B1",
    }),
  ];

  // Pedidos (orders) + their line items.
  const pedidoItems: PedidoItem[] = [];
  const pedidos: PedidoRow[] = [];
  function addPedido(
    idx: number,
    customerId: string,
    cliente: string,
    status: PedidoRow["status"],
    days: number,
    items: { produtoId: string | null; descricao: string; quantidade: number; valorUnit: number }[],
    tipo = "Venda",
  ) {
    const pedidoId = PED[idx];
    let total = 0;
    items.forEach((it, i) => {
      const valorTotal = round2(it.quantidade * it.valorUnit);
      total += valorTotal;
      pedidoItems.push({ id: sid("b1", idx * 10 + i + 1), pedidoId, ...it, valorTotal });
    });
    pedidos.push({
      id: pedidoId,
      cliente,
      produto: items[0].descricao,
      valor: round2(total),
      status,
      data: daysAgo(days, 9 + (idx % 7)),
      tipo,
      customerId,
    });
  }
  addPedido(0, C.padaria, "Padaria Pão Dourado", "execucao", 9, [
    { produtoId: P.fachada, descricao: "Fachada em ACM 4,0 × 1,2 m", quantidade: 4.8, valorUnit: 380 },
    { produtoId: P.letraCaixa, descricao: "Letra caixa em aço escovado", quantidade: 6, valorUnit: 260 },
  ]);
  addPedido(1, C.autoCenter, "Auto Center Silva", "aprovado", 4, [
    { produtoId: P.adesivo, descricao: "Adesivo vinil recortado — vitrine", quantidade: 8, valorUnit: 42 },
    { produtoId: P.instalacao, descricao: "Instalação em altura", quantidade: 2, valorUnit: 280 },
  ]);
  addPedido(2, C.clinica, "Clínica Sorriso", "aprovado", 2, [
    { produtoId: P.placa, descricao: "Placa PVC 3 mm — sinalização interna", quantidade: 3.5, valorUnit: 120 },
  ]);
  addPedido(3, C.mercado, "Mercado Bom Preço", "novo", 1, [
    { produtoId: P.banner, descricao: "Banner lona 440g — ofertas 3 × 1 m", quantidade: 12, valorUnit: 55 },
  ]);
  addPedido(4, C.academia, "Studio Fit Academia", "novo", 0, [
    { produtoId: P.logo, descricao: "Criação de logotipo", quantidade: 1, valorUnit: 650 },
  ]);
  addPedido(5, C.roberto, "Transportadora Rota Sul", "execucao", 6, [
    { produtoId: P.envelopamento, descricao: "Envelopamento de frota — Fiorino", quantidade: 3, valorUnit: 1850 },
  ]);
  addPedido(6, C.mariana, "Mariana Lopes", "entregue", 16, [
    { produtoId: P.banner, descricao: "Banner lona 440g — aniversário", quantidade: 3, valorUnit: 55 },
  ]);
  addPedido(7, C.padaria, "Padaria Pão Dourado", "entregue", 24, [
    { produtoId: P.adesivo, descricao: "Adesivo vinil recortado — cardápio", quantidade: 5, valorUnit: 42 },
    { produtoId: P.banner, descricao: "Banner lona 440g — inauguração", quantidade: 6, valorUnit: 55 },
  ]);
  addPedido(8, C.clinica, "Clínica Sorriso", "entregue", 41, [
    { produtoId: P.letraCaixa, descricao: "Letra caixa em aço escovado", quantidade: 8, valorUnit: 260 },
  ]);

  const ops: OP[] = [
    { id: sid("b2", 1), pedidoId: PED[0], cliente: "Padaria Pão Dourado", produto: "Fachada em ACM 4,0 × 1,2 m", status: "andamento", customerId: C.padaria },
    { id: sid("b2", 2), pedidoId: PED[1], cliente: "Auto Center Silva", produto: "Adesivo vinil recortado — vitrine", status: "fila", customerId: C.autoCenter },
    { id: sid("b2", 3), pedidoId: PED[5], cliente: "Transportadora Rota Sul", produto: "Envelopamento de frota — Fiorino", status: "revisao", customerId: C.roberto },
    { id: sid("b2", 4), pedidoId: PED[6], cliente: "Mariana Lopes", produto: "Banner lona 440g — aniversário", status: "concluido", customerId: C.mariana },
    { id: sid("b2", 5), pedidoId: PED[7], cliente: "Padaria Pão Dourado", produto: "Adesivo vinil recortado — cardápio", status: "concluido", customerId: C.padaria },
  ];

  const transacoes: Transacao[] = [
    { id: sid("c1", 1), tipo: "receita", descricao: "Entrada 50% — Fachada Pão Dourado", valor: 1692, data: daysAgo(8), categoria: "Vendas" },
    { id: sid("c1", 2), tipo: "receita", descricao: "Banner aniversário — Mariana Lopes", valor: 165, data: daysAgo(15), categoria: "Vendas" },
    { id: sid("c1", 3), tipo: "receita", descricao: "Cardápio + inauguração — Pão Dourado", valor: 540, data: daysAgo(22), categoria: "Vendas" },
    { id: sid("c1", 4), tipo: "receita", descricao: "OS 1003 — Revisão Hilux", valor: 1486, data: daysAgo(3), categoria: "Serviços" },
    { id: sid("c1", 5), tipo: "receita", descricao: "OS 1001 — Freios Gol", valor: 689, data: daysAgo(19), categoria: "Serviços" },
    { id: sid("c1", 6), tipo: "receita", descricao: "Letras caixa — Clínica Sorriso", valor: 2080, data: daysAgo(40), categoria: "Vendas" },
    { id: sid("c1", 14), tipo: "receita", descricao: "Envelopamento 1ª Fiorino — Rota Sul", valor: 1850, data: daysAgo(25), categoria: "Vendas" },
    { id: sid("c1", 15), tipo: "receita", descricao: "Faixas e banners — Mercado Bom Preço", valor: 1215, data: daysAgo(17), categoria: "Vendas" },
    { id: sid("c1", 16), tipo: "receita", descricao: "Placas de sinalização — Clínica Sorriso", valor: 780, data: daysAgo(13), categoria: "Vendas" },
    { id: sid("c1", 17), tipo: "receita", descricao: "OS 0998 — Revisão Strada (Auto Center)", valor: 920, data: daysAgo(9), categoria: "Serviços" },
    { id: sid("c1", 18), tipo: "receita", descricao: "Balcão: impressões e plotagens", valor: 1340, data: daysAgo(6), categoria: "Vendas" },
    { id: sid("c1", 7), tipo: "despesa", descricao: "Chapas de ACM 3 mm (6 un)", valor: 1380, data: daysAgo(7), categoria: "Matéria-prima" },
    { id: sid("c1", 8), tipo: "despesa", descricao: "Rolo lona 440g 3,20 × 50 m", valor: 890, data: daysAgo(12), categoria: "Matéria-prima" },
    { id: sid("c1", 9), tipo: "despesa", descricao: "Tinta solvente — kit CMYK", valor: 640, data: daysAgo(18), categoria: "Matéria-prima" },
    { id: sid("c1", 10), tipo: "despesa", descricao: "Aluguel do galpão", valor: 3200, data: daysAgo(5), categoria: "Aluguel" },
    { id: sid("c1", 11), tipo: "despesa", descricao: "Energia elétrica", valor: 742.5, data: daysAgo(11), categoria: "Utilidades" },
    { id: sid("c1", 12), tipo: "despesa", descricao: "Pastilhas e discos (fornecedor)", valor: 410, data: daysAgo(27), categoria: "Peças" },
    { id: sid("c1", 13), tipo: "despesa", descricao: "Aluguel do galpão", valor: 3200, data: daysAgo(35), categoria: "Aluguel" },
  ];

  const inventory: InventoryItem[] = [
    stock({ id: INV[0], produtoId: P.banner, codigo: "MP-LONA-440", nome: "Lona 440g frontlight", unidade: "M2", quantidade: 62, estoqueMinimo: 80, custoUnit: 5.6, precoVenda: 55, fornecedorPrincipal: "Lona & Cia Distribuidora", localizacao: "Prateleira A2" }),
    stock({ id: INV[1], produtoId: P.adesivo, codigo: "MP-VIN-BR", nome: "Vinil adesivo branco brilho", unidade: "M2", quantidade: 140, estoqueMinimo: 50, custoUnit: 6.2, precoVenda: 42, fornecedorPrincipal: "Vinil Sul Materiais", localizacao: "Prateleira A3" }),
    stock({ id: INV[2], codigo: "MP-ACM-3", nome: "Chapa ACM 3 mm 1,22 × 5 m", unidade: "CH", quantidade: 4, estoqueMinimo: 3, custoUnit: 230, localizacao: "Área de chapas" }),
    stock({ id: INV[3], codigo: "MP-TNT-C", nome: "Tinta solvente ciano", unidade: "L", quantidade: 1.5, estoqueMinimo: 2, custoUnit: 160, localizacao: "Armário de tintas" }),
    stock({ id: INV[4], produtoId: P.placa, codigo: "MP-PVC-3", nome: "Placa PVC expandido 3 mm", unidade: "M2", quantidade: 18, estoqueMinimo: 10, custoUnit: 38, precoVenda: 120, localizacao: "Área de chapas" }),
    stock({ id: INV[5], codigo: "MP-ILH-10", nome: "Ilhós latão nº 10 (cento)", unidade: "CT", quantidade: 22, estoqueMinimo: 5, custoUnit: 9.9 }),
    stock({ id: INV[6], produtoId: P.freio, codigo: "PC-PAS-DIA", nome: "Pastilha de freio dianteira", unidade: "JG", quantidade: 3, estoqueMinimo: 4, custoUnit: 96, precoVenda: 189, localizacao: "Box peças — B1" }),
    stock({ id: INV[7], codigo: "PC-OLE-5W30", nome: "Óleo 5W30 sintético", unidade: "L", quantidade: 36, estoqueMinimo: 12, custoUnit: 34, precoVenda: 62, localizacao: "Box peças — B2" }),
    stock({ id: INV[8], codigo: "PC-FIL-OLE", nome: "Filtro de óleo", unidade: "UN", quantidade: 9, estoqueMinimo: 6, custoUnit: 22, precoVenda: 45, localizacao: "Box peças — B2" }),
  ];

  const movements: Movement[] = [
    { id: sid("f1", 1), inventoryId: INV[0], tipo: "entrada", quantidade: 160, saldoApos: 160, motivo: "NF 4521 — Lona & Cia", referenciaTipo: null, referenciaId: null, custoUnitMovimento: 5.6, createdAt: daysAgo(30) },
    { id: sid("f1", 2), inventoryId: INV[0], tipo: "saida", quantidade: 18, saldoApos: 142, motivo: "Pedido Pão Dourado", referenciaTipo: "pedido", referenciaId: PED[7], custoUnitMovimento: null, createdAt: daysAgo(24) },
    { id: sid("f1", 3), inventoryId: INV[0], tipo: "saida", quantidade: 44, saldoApos: 98, motivo: "Banners Mercado Bom Preço", referenciaTipo: null, referenciaId: null, custoUnitMovimento: null, createdAt: daysAgo(14) },
    { id: sid("f1", 4), inventoryId: INV[0], tipo: "perda", quantidade: 6, saldoApos: 92, motivo: "Impressão com falha de cabeça", referenciaTipo: null, referenciaId: null, custoUnitMovimento: null, createdAt: daysAgo(10) },
    { id: sid("f1", 5), inventoryId: INV[0], tipo: "saida", quantidade: 30, saldoApos: 62, motivo: "Ofertas da semana", referenciaTipo: null, referenciaId: null, custoUnitMovimento: null, createdAt: daysAgo(2) },
    { id: sid("f1", 6), inventoryId: INV[6], tipo: "entrada", quantidade: 6, saldoApos: 6, motivo: "Compra balcão", referenciaTipo: null, referenciaId: null, custoUnitMovimento: 96, createdAt: daysAgo(28) },
    { id: sid("f1", 7), inventoryId: INV[6], tipo: "saida", quantidade: 3, saldoApos: 3, motivo: "OS 1001", referenciaTipo: "os", referenciaId: OS[0], custoUnitMovimento: null, createdAt: daysAgo(19) },
    { id: sid("f1", 8), inventoryId: INV[3], tipo: "ajuste", quantidade: 1.5, saldoApos: 1.5, motivo: "Contagem física", referenciaTipo: null, referenciaId: null, custoUnitMovimento: null, createdAt: daysAgo(6) },
  ];

  const vehicles: VehicleRow[] = [
    { id: VEH[0], customerId: C.carlos, placa: "RTA2B45", chassi: null, marca: "Volkswagen", modelo: "Gol 1.6", ano: 2016, cor: "Prata", kmAtual: 128400, observacoes: null, createdAt: daysAgo(60), updatedAt: daysAgo(19) },
    { id: VEH[1], customerId: C.mariana, placa: "QWE1C23", chassi: null, marca: "Honda", modelo: "Civic EXL", ano: 2018, cor: "Preto", kmAtual: 76250, observacoes: "Cliente prefere contato por WhatsApp.", createdAt: daysAgo(45), updatedAt: daysAgo(1) },
    { id: VEH[2], customerId: C.roberto, placa: "FRT3D67", chassi: "9BWZZZ377VT004251", marca: "Toyota", modelo: "Hilux SRV", ano: 2019, cor: "Branco", kmAtual: 154900, observacoes: "Frota Rota Sul — carro 07.", createdAt: daysAgo(40), updatedAt: daysAgo(3) },
    { id: VEH[3], customerId: C.autoCenter, placa: "GHJ4521", chassi: null, marca: "Fiat", modelo: "Strada Freedom", ano: 2021, cor: "Vermelho", kmAtual: 48800, observacoes: null, createdAt: daysAgo(20), updatedAt: daysAgo(0) },
  ];

  const soItems: SOItem[] = [];
  const serviceOrders: ServiceOrderRow[] = [];
  function addOS(
    idx: number,
    p: Omit<ServiceOrderRow, "id" | "numero" | "valorPecas" | "valorMaoObra" | "valorTotal" | "createdAt" | "updatedAt" | "aprovacaoIp" | "aprovacaoComentario" | "aprovacaoData"> & Partial<ServiceOrderRow>,
    items: Omit<SOItem, "id" | "serviceOrderId" | "valorTotal" | "createdAt" | "codigo" | "fornecedor">[],
  ) {
    const id = OS[idx];
    let pecas = 0;
    let mao = 0;
    items.forEach((it, i) => {
      const valorTotal = round2(it.quantidade * it.valorUnit);
      if (it.tipo === "peca") pecas += valorTotal;
      else mao += valorTotal;
      soItems.push({ id: sid("e2", idx * 10 + i + 1), serviceOrderId: id, codigo: null, fornecedor: null, ...it, valorTotal, createdAt: p.dataEntrada });
    });
    serviceOrders.push({
      aprovacaoIp: null,
      aprovacaoComentario: null,
      aprovacaoData: p.aprovacaoCliente ? p.dataEntrada : null,
      ...p,
      id,
      numero: 1001 + idx,
      valorPecas: round2(pecas),
      valorMaoObra: round2(mao),
      valorTotal: round2(pecas + mao),
      createdAt: p.dataEntrada,
      updatedAt: daysAgo(0, 8),
    });
  }
  addOS(0, { customerId: C.carlos, vehicleId: VEH[0], queixaCliente: "Chiado ao frear", diagnostico: "Pastilhas dianteiras no fim; discos dentro da tolerância.", kmEntrada: 128350, kmSaida: 128400, status: "entregue", dataEntrada: daysAgo(20, 8), dataPrevista: daysAgo(19, 17), dataSaida: daysAgo(19, 16), aprovacaoCliente: true }, [
    { tipo: "peca", descricao: "Pastilha de freio dianteira", quantidade: 1, valorUnit: 189, tempoEstimadoHoras: null, mecanicoResponsavel: null },
    { tipo: "servico", descricao: "Troca de pastilhas + sangria", quantidade: 1, valorUnit: 180, tempoEstimadoHoras: 1.5, mecanicoResponsavel: "Jorge" },
    { tipo: "servico", descricao: "Retífica de discos", quantidade: 1, valorUnit: 320, tempoEstimadoHoras: 2, mecanicoResponsavel: "Jorge" },
  ]);
  addOS(1, { customerId: C.mariana, vehicleId: VEH[1], queixaCliente: "Luz de injeção acesa e falha em marcha lenta", diagnostico: "Bobina do 3º cilindro com falha intermitente.", kmEntrada: 76250, kmSaida: null, status: "aguardando_aprovacao", dataEntrada: daysAgo(1, 9), dataPrevista: daysAhead(1), dataSaida: null, aprovacaoCliente: null }, [
    { tipo: "peca", descricao: "Bobina de ignição", quantidade: 1, valorUnit: 420, tempoEstimadoHoras: null, mecanicoResponsavel: null },
    { tipo: "peca", descricao: "Jogo de velas iridium", quantidade: 1, valorUnit: 360, tempoEstimadoHoras: null, mecanicoResponsavel: null },
    { tipo: "servico", descricao: "Diagnóstico eletrônico + troca", quantidade: 1, valorUnit: 250, tempoEstimadoHoras: 1.5, mecanicoResponsavel: "Paulo" },
  ]);
  addOS(2, { customerId: C.roberto, vehicleId: VEH[2], queixaCliente: "Revisão dos 150 mil km", diagnostico: "Revisão completa conforme plano do fabricante.", kmEntrada: 154900, kmSaida: 154900, status: "pronto", dataEntrada: daysAgo(3, 8), dataPrevista: daysAgo(0, 17), dataSaida: null, aprovacaoCliente: true }, [
    { tipo: "peca", descricao: "Óleo 5W30 sintético", quantidade: 7, valorUnit: 62, tempoEstimadoHoras: null, mecanicoResponsavel: null },
    { tipo: "peca", descricao: "Filtro de óleo", quantidade: 1, valorUnit: 45, tempoEstimadoHoras: null, mecanicoResponsavel: null },
    { tipo: "peca", descricao: "Filtro de ar + cabine", quantidade: 1, valorUnit: 187, tempoEstimadoHoras: null, mecanicoResponsavel: null },
    { tipo: "servico", descricao: "Revisão completa (mão de obra)", quantidade: 1, valorUnit: 320, tempoEstimadoHoras: 4, mecanicoResponsavel: "Jorge" },
    { tipo: "servico", descricao: "Alinhamento e balanceamento", quantidade: 1, valorUnit: 500, tempoEstimadoHoras: 1, mecanicoResponsavel: "Paulo" },
  ]);
  addOS(3, { customerId: C.autoCenter, vehicleId: VEH[3], queixaCliente: "Ar-condicionado não gela", diagnostico: null, kmEntrada: 48800, kmSaida: null, status: "diagnostico", dataEntrada: daysAgo(0, 8, 30), dataPrevista: daysAhead(2), dataSaida: null, aprovacaoCliente: null }, [
    { tipo: "servico", descricao: "Teste de pressão e vazamento do A/C", quantidade: 1, valorUnit: 150, tempoEstimadoHoras: 1, mecanicoResponsavel: "Paulo" },
  ]);
  addOS(4, { customerId: C.carlos, vehicleId: VEH[0], queixaCliente: "Barulho na suspensão dianteira", diagnostico: "Bieleta esquerda com folga.", kmEntrada: 128400, kmSaida: null, status: "em_execucao", dataEntrada: daysAgo(1, 14), dataPrevista: daysAhead(0), dataSaida: null, aprovacaoCliente: true }, [
    { tipo: "peca", descricao: "Bieleta dianteira", quantidade: 2, valorUnit: 98, tempoEstimadoHoras: null, mecanicoResponsavel: null },
    { tipo: "servico", descricao: "Troca de bieletas", quantidade: 1, valorUnit: 140, tempoEstimadoHoras: 1, mecanicoResponsavel: "Jorge" },
  ]);

  const soPhotos: SOPhoto[] = [
    { id: sid("e3", 1), serviceOrderId: OS[2], tipo: "entrada", fileKey: svgPhoto("Hilux FRT3D67 — entrada", "#3f4a5a"), descricao: "Painel com 154.900 km", createdAt: daysAgo(3, 8, 10) },
    { id: sid("e3", 2), serviceOrderId: OS[2], tipo: "durante", fileKey: svgPhoto("Troca de filtros", "#5a4a3f"), descricao: null, createdAt: daysAgo(2, 11) },
    { id: sid("e3", 3), serviceOrderId: OS[2], tipo: "saida", fileKey: svgPhoto("Pronto para entrega", "#2f5a48"), descricao: null, createdAt: daysAgo(0, 15) },
    { id: sid("e3", 4), serviceOrderId: OS[1], tipo: "entrada", fileKey: svgPhoto("Civic QWE1C23 — entrada", "#4a3f5a"), descricao: "Luz de injeção acesa", createdAt: daysAgo(1, 9, 5) },
  ];

  const priceTables: PriceTable[] = [
    { id: sid("a2", 1), material: "Lona 440g frontlight", precoPorM2: 55, acabamentoExtra: 8, ativo: true, createdAt: daysAgo(200) },
    { id: sid("a2", 2), material: "Lona backlight", precoPorM2: 78, acabamentoExtra: 8, ativo: true, createdAt: daysAgo(200) },
    { id: sid("a2", 3), material: "Vinil adesivo impresso", precoPorM2: 48, acabamentoExtra: 12, ativo: true, createdAt: daysAgo(200) },
    { id: sid("a2", 4), material: "Placa PVC 3 mm", precoPorM2: 120, acabamentoExtra: 0, ativo: true, createdAt: daysAgo(150) },
    { id: sid("a2", 5), material: "ACM 3 mm com estrutura", precoPorM2: 380, acabamentoExtra: 0, ativo: true, createdAt: daysAgo(150) },
  ];

  const quoteItems: QuoteItem[] = [];
  const quotes: QuoteRow[] = [];
  function addQuote(
    idx: number,
    p: Pick<QuoteRow, "customerId" | "titulo" | "status" | "observacoes">,
    days: number,
    items: { descricao: string; material: string; larguraCm: number; alturaCm: number; quantidade: number; precoPorM2: number; acabamento: string | null }[],
  ) {
    const id = QT[idx];
    let total = 0;
    items.forEach((it, i) => {
      const areaM2 = round2((it.larguraCm * it.alturaCm * it.quantidade) / 10000);
      const valorTotal = round2(areaM2 * it.precoPorM2);
      total += valorTotal;
      quoteItems.push({ id: sid("a3", idx * 10 + i + 1), quoteId: id, ...it, areaM2, valorTotal });
    });
    quotes.push({ id, ...p, validadeDias: 15, valorTotal: round2(total), pdfKey: null, createdAt: daysAgo(days), updatedAt: daysAgo(Math.max(0, days - 1)) });
  }
  addQuote(0, { customerId: C.mercado, titulo: "Banners ofertas de fim de mês", status: "enviado", observacoes: "Entrega em até 3 dias úteis após aprovação." }, 2, [
    { descricao: "Banner ofertas", material: "Lona 440g frontlight", larguraCm: 300, alturaCm: 100, quantidade: 4, precoPorM2: 55, acabamento: "Bainha + ilhós" },
    { descricao: "Faixa de gôndola", material: "Vinil adesivo impresso", larguraCm: 100, alturaCm: 10, quantidade: 30, precoPorM2: 48, acabamento: null },
  ]);
  addQuote(1, { customerId: C.academia, titulo: "Fachada nova Studio Fit", status: "rascunho", observacoes: null }, 0, [
    { descricao: "Fachada principal", material: "ACM 3 mm com estrutura", larguraCm: 600, alturaCm: 120, quantidade: 1, precoPorM2: 380, acabamento: "Letras em adesivo recortado" },
  ]);
  addQuote(2, { customerId: C.clinica, titulo: "Sinalização interna", status: "aprovado", observacoes: "Cliente aprovou por WhatsApp." }, 5, [
    { descricao: "Placas de porta", material: "Placa PVC 3 mm", larguraCm: 30, alturaCm: 15, quantidade: 12, precoPorM2: 120, acabamento: "Fita dupla face" },
    { descricao: "Totem de recepção", material: "Placa PVC 3 mm", larguraCm: 60, alturaCm: 160, quantidade: 1, precoPorM2: 120, acabamento: null },
  ]);
  addQuote(3, { customerId: C.padaria, titulo: "Fachada + letreiro", status: "convertido", observacoes: null }, 12, [
    { descricao: "Fachada em ACM", material: "ACM 3 mm com estrutura", larguraCm: 400, alturaCm: 120, quantidade: 1, precoPorM2: 380, acabamento: null },
  ]);

  const payments: PaymentRow[] = [
    { id: sid("c2", 1), userId: 1, customerId: C.padaria, referenciaTipo: "pedido", referenciaId: PED[0], valor: 1692, descricao: "Saldo 50% — Fachada Pão Dourado", metodoPreferido: "pix", status: "pendente", providerPaymentId: "pay_demo_001", paymentUrl: null, vencimento: daysAhead(3).slice(0, 10), pagoEm: null, netValue: null, createdAt: daysAgo(8), updatedAt: daysAgo(8) },
    { id: sid("c2", 2), userId: 1, customerId: C.roberto, referenciaTipo: "pedido", referenciaId: PED[5], valor: 2775, descricao: "Entrada envelopamento de frota", metodoPreferido: "boleto", status: "vencido", providerPaymentId: "pay_demo_002", paymentUrl: null, vencimento: daysAgo(2).slice(0, 10), pagoEm: null, netValue: null, createdAt: daysAgo(6), updatedAt: daysAgo(1) },
    { id: sid("c2", 3), userId: 1, customerId: C.roberto, referenciaTipo: "os", referenciaId: OS[2], valor: 1486, descricao: "OS 1003 — Revisão Hilux", metodoPreferido: "pix", status: "pago", providerPaymentId: "pay_demo_003", paymentUrl: null, vencimento: daysAgo(3).slice(0, 10), pagoEm: daysAgo(3, 16), netValue: 1471.14, createdAt: daysAgo(3), updatedAt: daysAgo(3) },
    { id: sid("c2", 4), userId: 1, customerId: C.mariana, referenciaTipo: "os", referenciaId: OS[1], valor: 1030, descricao: "OS 1002 — Bobina + velas", metodoPreferido: "cartao", status: "pendente", providerPaymentId: "pay_demo_004", paymentUrl: null, vencimento: daysAhead(5).slice(0, 10), pagoEm: null, netValue: null, createdAt: daysAgo(1), updatedAt: daysAgo(1) },
    { id: sid("c2", 5), userId: 1, customerId: C.mariana, referenciaTipo: "pedido", referenciaId: PED[6], valor: 165, descricao: "Banner aniversário", metodoPreferido: "pix", status: "pago", providerPaymentId: "pay_demo_005", paymentUrl: null, vencimento: daysAgo(16).slice(0, 10), pagoEm: daysAgo(15, 11), netValue: 163.35, createdAt: daysAgo(16), updatedAt: daysAgo(15) },
  ];

  const fiscalDocs: FiscalDoc[] = [
    { id: sid("c3", 1), tipo: "NFe", referenciaTipo: "pedido", referenciaId: PED[8], customerId: C.clinica, status: "autorizado", chaveAcesso: "35260912345678000190550010000004211000004210", numero: "421", serie: "1", providerRef: "demo-421", valor: 2080, pdfKey: null, xmlKey: null, erroMensagem: null, ambiente: "homologacao", createdAt: daysAgo(40), updatedAt: daysAgo(40) },
    { id: sid("c3", 2), tipo: "NFSe", referenciaTipo: "os", referenciaId: OS[0], customerId: C.carlos, status: "autorizado", chaveAcesso: null, numero: "88", serie: null, providerRef: "demo-88", valor: 500, pdfKey: null, xmlKey: null, erroMensagem: null, ambiente: "homologacao", createdAt: daysAgo(19), updatedAt: daysAgo(19) },
    { id: sid("c3", 3), tipo: "NFe", referenciaTipo: "pedido", referenciaId: PED[7], customerId: C.padaria, status: "rejeitado", chaveAcesso: null, numero: null, serie: "1", providerRef: "demo-rej", valor: 540, pdfKey: null, xmlKey: null, erroMensagem: "Rejeição 539: duplicidade de NF-e com diferença na chave de acesso.", ambiente: "homologacao", createdAt: daysAgo(23), updatedAt: daysAgo(23) },
  ];

  const companySettings: CompanySettings = {
    id: sid("c4", 1),
    razaoSocial: "Oimpresso Comunicação Visual Ltda",
    nomeFantasia: "Oimpresso",
    cnpj: "11.222.333/0001-81", // pii-allowlist (documento fictício da demonstração)
    inscricaoEstadual: "244.556.778.119",
    inscricaoMunicipal: "1.045.332-0",
    regimeTributario: "simples_nacional",
    cep: "13015-100",
    endereco: "R. Conceição, 233 — Centro",
    cidade: "Campinas",
    uf: "SP",
    telefone: "(19) 3232-4455",
    email: "contato@oimpresso.exemplo.com.br",
    createdAt: daysAgo(240),
    updatedAt: daysAgo(30),
  };

  const artworks: Artwork[] = [
    { id: sid("a4", 1), opId: sid("b2", 1), quoteId: null, titulo: "Fachada Pão Dourado — v2", descricao: "Ajuste de cor do logotipo (Pantone 7409).", fileKey: "demo/fachada-pao-dourado-v2.svg", mimeType: "image/svg+xml", fileSizeBytes: 48210, status: "aprovado", publicToken: "demo-art-1", createdAt: daysAgo(8), updatedAt: daysAgo(7), fileUrl: svgPhoto("Fachada Pão Dourado — v2", "#8a5a1f") },
    { id: sid("a4", 2), opId: sid("b2", 3), quoteId: null, titulo: "Envelopamento Rota Sul", descricao: "Laterais e traseira da Fiorino.", fileKey: "demo/envelopamento-rota-sul.svg", mimeType: "image/svg+xml", fileSizeBytes: 61877, status: "pendente", publicToken: "demo-art-2", createdAt: daysAgo(2), updatedAt: daysAgo(2), fileUrl: svgPhoto("Envelopamento Rota Sul", "#1f4a8a") },
  ];

  const approvals: Approval[] = [
    { id: sid("a5", 1), artworkId: sid("a4", 1), decisao: "rejeitado", comentario: "O amarelo ficou claro demais.", aprovadorNome: "Dona Célia", aprovadorEmail: "compras@paodourado.exemplo.com.br", ipAddress: null, createdAt: daysAgo(8, 15) },
    { id: sid("a5", 2), artworkId: sid("a4", 1), decisao: "aprovado", comentario: "Perfeito, pode produzir.", aprovadorNome: "Dona Célia", aprovadorEmail: "compras@paodourado.exemplo.com.br", ipAddress: null, createdAt: daysAgo(7, 10) },
  ];

  const waMessages: WaMessage[] = [
    { id: sid("c5", 1), customerId: C.roberto, telefone: "(19) 99188-4521", mensagem: "Olá! Sua Hilux FRT3D67 está pronta para retirada. 🚗", tipo: "enviada", referenciaTipo: "os", referenciaId: OS[2], providerMessageId: "wa-demo-1", erro: null, createdAt: daysAgo(0, 15, 20) },
    { id: sid("c5", 2), customerId: C.mariana, telefone: "(19) 99277-6612", mensagem: "Segue o orçamento da OS 1002 para aprovação.", tipo: "enviada", referenciaTipo: "os", referenciaId: OS[1], providerMessageId: "wa-demo-2", erro: null, createdAt: daysAgo(1, 11) },
    { id: sid("c5", 3), customerId: C.mariana, telefone: "(19) 99277-6612", mensagem: "Pode me mandar as fotos da bobina, por favor?", tipo: "recebida", referenciaTipo: "os", referenciaId: OS[1], providerMessageId: "wa-demo-3", erro: null, createdAt: daysAgo(1, 11, 40) },
    { id: sid("c5", 4), customerId: C.padaria, telefone: "(19) 99812-4410", mensagem: "Seu pedido entrou em produção!", tipo: "enviada", referenciaTipo: "pedido", referenciaId: PED[0], providerMessageId: "wa-demo-4", erro: null, createdAt: daysAgo(8, 9) },
  ];

  return {
    currentCompanyId: companies[0].id,
    companies,
    customers,
    produtos,
    pedidos,
    pedidoItems,
    ops,
    transacoes,
    inventory,
    movements,
    vehicles,
    serviceOrders,
    soItems,
    soPhotos,
    soTokens: [],
    quotes,
    quoteItems,
    priceTables,
    payments,
    fiscalDocs,
    companySettings,
    artworks,
    approvals,
    waMessages,
  };
}
