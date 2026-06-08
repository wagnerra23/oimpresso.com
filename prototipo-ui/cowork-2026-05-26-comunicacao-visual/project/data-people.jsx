// data-people.jsx — Mocks per-role (fornecedor / funcionário / representante)
// Cada entidade tem semântica e vocabulário próprios. NÃO misturar.

// ───────────────────────── FORNECEDORES ─────────────────────────
// Vocabulário: Categoria · Lead time · Frequência · A pagar · Crítico
window.SUPPLIERS = [
  { id:"f-1", name:"Papel & Cia Distribuidora",  doc:"22.111.333/0001-44", contact:"Carlos Bertoni",   phone:"(11) 3344-5566", category:"Papel",     leadDays:7,  freq:"semanal",   aPagar:18420, dueDate:"2026-05-30", lastOrder:"2026-05-22", openOrders:3, critical:false, tags:["preferido"] },
  { id:"f-2", name:"Tintas Brasil S/A",          doc:"33.222.444/0001-55", contact:"Mariana Lopes",    phone:"(11) 2233-4455", category:"Tinta",     leadDays:14, freq:"mensal",    aPagar:0,     dueDate:"—",          lastOrder:"2026-05-12", openOrders:1, critical:false, tags:["homologado"] },
  { id:"f-3", name:"Chapas & Bobinas Norte",     doc:"44.333.555/0001-66", contact:"Roberto Aoki",     phone:"(41) 3322-6677", category:"Substrato", leadDays:21, freq:"mensal",    aPagar:34800, dueDate:"2026-06-05", lastOrder:"2026-05-08", openOrders:2, critical:true,  tags:["alto-ticket"] },
  { id:"f-4", name:"Plotter Suprimentos LTDA",   doc:"55.444.666/0001-77", contact:"Júlia Tanaka",     phone:"(11) 4455-7788", category:"Insumo",    leadDays:3,  freq:"semanal",   aPagar:5240,  dueDate:"2026-05-27", lastOrder:"2026-05-23", openOrders:1, critical:false, tags:["express"] },
  { id:"f-5", name:"Embalagens RJ",              doc:"66.555.777/0001-88", contact:"Hugo Pacheco",     phone:"(21) 2233-9988", category:"Embalagem", leadDays:10, freq:"quinzenal", aPagar:9800,  dueDate:"2026-06-02", lastOrder:"2026-05-18", openOrders:0, critical:false, tags:[] },
  { id:"f-6", name:"Solventes & Químicos Sul",   doc:"77.666.888/0001-99", contact:"Diego Marçal",     phone:"(51) 3344-1122", category:"Químico",   leadDays:30, freq:"trimestral",aPagar:0,     dueDate:"—",          lastOrder:"2026-02-14", openOrders:0, critical:true,  tags:["risco"] },
  { id:"f-7", name:"Serv. Acabamento Pronta",    doc:"88.777.999/0001-10", contact:"Bianca Reis",      phone:"(11) 5566-3344", category:"Serviço",   leadDays:5,  freq:"sob demanda",aPagar:12300,dueDate:"2026-05-29", lastOrder:"2026-05-21", openOrders:4, critical:false, tags:["terceiro"] },
];

// ───────────────────────── FUNCIONÁRIOS ─────────────────────────
// Vocabulário: Cargo · Setor · Admissão · Vínculo · Acesso ao sistema
window.EMPLOYEES = [
  { id:"e-1", name:"Larissa Andrade",   doc:"123.456.789-00", role:"Atendente comercial", department:"Comercial",  admittedAt:"2024-08-15", vinculo:"CLT",        access:"operador",  birth:"03/12",  shift:"Manhã",  status:"ativo",   tags:["balcão","key-user"] },
  { id:"e-2", name:"Wagner Oliveira",   doc:"234.567.890-11", role:"Gestor",              department:"Diretoria",  admittedAt:"2015-01-10", vinculo:"Sócio",      access:"admin",     birth:"22/04",  shift:"Integral",status:"ativo",  tags:["fundador"] },
  { id:"e-3", name:"Eliana Pereira",    doc:"345.678.901-22", role:"Financeiro",          department:"Adm/Fin",    admittedAt:"2020-03-02", vinculo:"CLT",        access:"financeiro",birth:"17/09",  shift:"Tarde",  status:"ativo",   tags:["pix-aprovador"] },
  { id:"e-4", name:"Tiago Mendes",      doc:"456.789.012-33", role:"Operador de impressão",department:"Produção",  admittedAt:"2022-06-20", vinculo:"CLT",        access:"produção",  birth:"11/02",  shift:"Manhã",  status:"férias",  tags:["digital"] },
  { id:"e-5", name:"Camila Bezerra",    doc:"567.890.123-44", role:"Designer",            department:"Criação",    admittedAt:"2023-11-04", vinculo:"PJ",         access:"design",    birth:"28/07",  shift:"Integral",status:"ativo",  tags:["arte-final"] },
  { id:"e-6", name:"Rafael Costa",      doc:"678.901.234-55", role:"Acabamento",          department:"Produção",   admittedAt:"2024-02-12", vinculo:"CLT",        access:"produção",  birth:"05/05",  shift:"Tarde",  status:"ativo",   tags:["corte","wire-o"] },
  { id:"e-7", name:"Júlia Ferreira",    doc:"789.012.345-66", role:"Estagiária comercial",department:"Comercial",  admittedAt:"2025-09-01", vinculo:"Estagiário", access:"operador",  birth:"15/11",  shift:"Tarde",  status:"ativo",   tags:["estágio"] },
];

// ───────────────────────── REPRESENTANTES ─────────────────────────
// Vocabulário: Região · Comissão % · Carteira · Vendas mês · A pagar comissão
window.REPRESENTATIVES = [
  { id:"r-1", name:"Beto Vendas (BV Rep.)",   doc:"99.888.777/0001-66", contact:"Beto Carvalho",  phone:"(11) 99887-7766", regions:["SP","MG"],      pct:8,  portfolio:34, vendasMes:48720, aPagarComissao:3898, lastDeal:"2026-05-23", status:"ativo",     tags:["top-1","sp-capital"] },
  { id:"r-2", name:"NorteSul Comercial",      doc:"88.777.666/0001-55", contact:"Camila Souza",   phone:"(51) 98765-4321", regions:["RS","SC","PR"], pct:7,  portfolio:21, vendasMes:24380, aPagarComissao:1707, lastDeal:"2026-05-20", status:"ativo",     tags:["sul"] },
  { id:"r-3", name:"Triângulo Mineiro Rep.",  doc:"77.666.555/0001-44", contact:"Lúcio Marques",  phone:"(34) 99887-1234", regions:["MG"],           pct:6,  portfolio:12, vendasMes:8240,  aPagarComissao:494,  lastDeal:"2026-04-18", status:"ociosa",    tags:[] },
];

// ───────────────────────── COUNTS GLOBAIS ─────────────────────────
// usado pelo Role Tabs no header — fonte única.
window.PEOPLE_COUNTS = {
  customer:       (window.OS_DATA?.OS_CLIENTS?.length || 0),
  supplier:       window.SUPPLIERS.length,
  employee:       window.EMPLOYEES.length,
  representative: window.REPRESENTATIVES.length,
};
window.PEOPLE_COUNTS.all =
  window.PEOPLE_COUNTS.customer +
  window.PEOPLE_COUNTS.supplier +
  window.PEOPLE_COUNTS.employee +
  window.PEOPLE_COUNTS.representative;
