// data-vendas.jsx — Mock de vendas concluídas (Fase 3 / P0 Sells)
const VENDAS_LIST = [
  { id:"V-7821", date:"2026-04-30", time:"14:32", client:"Padaria Estrela",        seller:"Bruna Vendas",  items:3, total:"R$ 1.840,00", payment:"PIX",          installments:1, status:"paga",        osIds:["4831"], origin:"balcão",   notes:"Banner + cartões fidelidade", urgent:false },
  { id:"V-7822", date:"2026-04-30", time:"15:10", client:"Mercado União",          seller:"Carlos Vendas", items:1, total:"R$ 3.420,00", payment:"Boleto 30d",   installments:1, status:"pendente",    osIds:["4832"], origin:"orçamento", notes:"Folder 2.000un",                urgent:false },
  { id:"V-7823", date:"2026-04-30", time:"16:45", client:"Auto Posto Águia",       seller:"Bruna Vendas",  items:2, total:"R$ 2.180,00", payment:"Cartão",       installments:2, status:"paga",        osIds:["4833"], origin:"balcão",   notes:"Adesivo bombas + placa",        urgent:true  },
  { id:"V-7824", date:"2026-04-30", time:"17:20", client:"Consumidor Final",       seller:"Bruna Vendas",  items:1, total:"R$ 380,00",   payment:"Dinheiro",     installments:1, status:"paga",        osIds:[],       origin:"balcão",   notes:"Cartão visita pronta-entrega",  urgent:false },
  { id:"V-7825", date:"2026-04-29", time:"11:05", client:"Farmácia Vida Plena",    seller:"Carlos Vendas", items:1, total:"R$ 4.860,00", payment:"Boleto 30d",   installments:3, status:"faturada",    osIds:["4830"], origin:"orçamento", notes:"Sacolas 5.000un parcelado 3x",  urgent:false },
  { id:"V-7826", date:"2026-04-29", time:"13:48", client:"Pet Shop Latido Feliz",  seller:"Bruna Vendas",  items:2, total:"R$ 980,00",   payment:"PIX",          installments:1, status:"paga",        osIds:["4829"], origin:"balcão",   notes:"Cartão visita + folder",        urgent:false },
  { id:"V-7827", date:"2026-04-29", time:"16:30", client:"Construtora Horizonte",  seller:"Carlos Vendas", items:2, total:"R$ 8.940,00", payment:"Boleto 60d",   installments:2, status:"pendente",    osIds:["4827"], origin:"orçamento", notes:"Banner obra + tapume",          urgent:true  },
  { id:"V-7828", date:"2026-04-28", time:"10:15", client:"Salão Beleza Pura",      seller:"Bruna Vendas",  items:1, total:"R$ 420,00",   payment:"PIX",          installments:1, status:"paga",        osIds:["4824"], origin:"balcão",   notes:"Cartão fidelidade",             urgent:false },
  { id:"V-7829", date:"2026-04-28", time:"14:50", client:"Distribuidora Brasil",   seller:"Carlos Vendas", items:1, total:"R$ 12.400,00",payment:"Boleto 30d",   installments:6, status:"faturada",    osIds:["4825","4826"], origin:"orçamento", notes:"Catálogo 200un parcelado 6x",   urgent:false },
  { id:"V-7830", date:"2026-04-28", time:"17:02", client:"Restaurante Sabor Casa", seller:"Bruna Vendas",  items:1, total:"R$ 1.320,00", payment:"Cartão",       installments:1, status:"cancelada",   osIds:[],       origin:"balcão",   notes:"Cancelada — cliente desistiu",  urgent:false },
];

const VENDAS_PAYMENTS = [
  { id:"pix",      label:"PIX",        icon:"⚡", clearing:"imediato" },
  { id:"dinheiro", label:"Dinheiro",   icon:"💵", clearing:"imediato" },
  { id:"cartao",   label:"Cartão",     icon:"💳", clearing:"D+1 a D+30" },
  { id:"boleto30", label:"Boleto 30d", icon:"📄", clearing:"30 dias" },
  { id:"boleto60", label:"Boleto 60d", icon:"📄", clearing:"60 dias" },
  { id:"transf",   label:"Transferência", icon:"🏦", clearing:"D+0 a D+1" },
];

const VENDAS_STATUS = {
  paga:      { label:"Paga",      color:"oklch(0.50 0.14 145)" },
  pendente:  { label:"Pendente",  color:"oklch(0.60 0.14 70)"  },
  faturada:  { label:"Faturada",  color:"oklch(0.55 0.14 240)" },
  cancelada: { label:"Cancelada", color:"oklch(0.55 0.04 250)" },
};

window.VENDAS_DATA = { VENDAS_LIST, VENDAS_PAYMENTS, VENDAS_STATUS };
