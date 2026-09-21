// crm-blade.jsx — Módulo CRM importado dos blades `Modules/Crm/Resources/views/*`.
// Tradução 1:1 do menu do módulo (crm::layouts.nav) pro Cockpit V2 — nada de tela inventada:
//   crm_dashboard/index.blade.php ..... "Painel"                → tela "painel"
//   lead/index.blade.php .............. "Leads" (lista + kanban)→ tela "leads"
//   schedule/index.blade.php .......... "Acompanhamentos" (+ recorrentes) → tela "acompanhamentos"
//   schedule/create.blade.php ......... modal "Adicionar acompanhamento"
//   campaign/index.blade.php .......... "Campanhas"             → tela "campanhas"
//   contact_login/* ................... "Login de contatos" + "Comissões" → telas "logins"/"comissoes"
//   call_logs/index.blade.php ......... "Registro de chamadas"  → tela "chamadas"
//   reports/index.blade.php ........... "Relatórios"            → tela "relatorios"
//   proposal_template/index + proposal/index .. "Modelo de proposta"/"Propostas"
//   marketplace/index.blade.php ....... "Marketplace B2B"       → tela "marketplace"
//   order_request/index.blade.php ..... "Pedido de ordem"       → tela "pedidos"
//   settings/index.blade.php .......... "Configurações"         → tela "config"
// O funil de negócios (crm-page.jsx) NÃO foi refeito: a aba "Funil" aponta pra rota viva `crm`.
// Pele: classes .pb-* de produto-blade.css (mesma importação de blade) + window.PBUI + DS v6.
// Expõe window.CrmBladePage + window.CBD (domínio/mock) + window.CBUI (peças).
(() => {
const { useState, useMemo, useEffect } = React;
const MP = () => window.ModuloPadrao || {};
const DS = () => window.OfficeImpressoPontoWR2DesignSystem_019dd0 || {};
const UI = () => window.PBUI || {};
const Ic = ({ name, size = 14 }) => { const F = (window.I || {})[name]; return F ? <F size={size} /> : null; };

// ─────────── Domínio (selects dos blades: sources, life_stages, followup_category, statuses) ───────────
const FONTES = [
  { id: "site", name: "Site" }, { id: "whats", name: "WhatsApp" }, { id: "indic", name: "Indicação" },
  { id: "tel", name: "Telefone" }, { id: "balcao", name: "Balcão" }, { id: "insta", name: "Instagram" },
  { id: "ml", name: "Mercado Livre" },
];
const FASES = [
  { id: "novo", name: "Novo" }, { id: "contatado", name: "Contatado" }, { id: "qualificado", name: "Qualificado" },
  { id: "proposta", name: "Proposta enviada" }, { id: "negociacao", name: "Negociação" },
  { id: "cliente", name: "Cliente" }, { id: "perdido", name: "Perdido" },
];
const STATUS = [
  { id: "scheduled", name: "Agendado" }, { id: "open", name: "Aberto" },
  { id: "completed", name: "Concluído" }, { id: "canceled", name: "Cancelado" },
];
const TIPOS = [{ id: "call", name: "Ligação" }, { id: "meeting", name: "Encontro" }, { id: "sms", name: "SMS" }, { id: "email", name: "E-mail" }];
const CATEGORIAS = [
  { id: "prospec", name: "Prospecção" }, { id: "orcamento", name: "Orçamento" }, { id: "posvenda", name: "Pós-venda" },
  { id: "cobranca", name: "Cobrança" }, { id: "instalacao", name: "Instalação" },
];
const USUARIOS = ["Larissa Prado", "Wagner Ramos", "Eliana Souza", "Marcos Vinícius"];
const LOCAIS = [{ id: 1, name: "Matriz" }, { id: 2, name: "Filial Centro" }];
// follow_up_by do blade (schedule.index / advance follow up)
const POR = [{ id: "payment_status", name: "Status do pagamento" }, { id: "orders", name: "Pedidos" }];
const NOTIFICAR = [{ id: "minute", name: "Minuto" }, { id: "hour", name: "Hora" }];

const fmtBRL = (n) => n == null ? "—" : "R$ " + Number(n).toLocaleString("pt-BR", { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const dia = (d) => { const x = new Date(); x.setDate(x.getDate() - d); return String(x.getDate()).padStart(2, "0") + "/" + String(x.getMonth() + 1).padStart(2, "0") + "/" + x.getFullYear(); };
const hm = (h, m) => String(h).padStart(2, "0") + ":" + String(m).padStart(2, "0");
const rotulo = (lista, id) => (lista.find((x) => x.id === id) || {}).name || id || "—";
// Período (o daterangepicker dos blades) — filtra pela data de início do acompanhamento.
const PERIODOS = [{ id: "hoje", name: "Hoje", d: 0 }, { id: "7", name: "Últimos 7 dias", d: 7 }, { id: "30", name: "Últimos 30 dias", d: 30 }];
const diasAtras = (br) => {
  const m = /(\d{2})\/(\d{2})\/(\d{4})/.exec(br || "");
  if (!m) return 9999;
  const dt = new Date(+m[3], +m[2] - 1, +m[1]);
  return Math.round((Date.now() - dt.getTime()) / 86400000);
};
const noPeriodo = (id, br) => { if (!id) return true; const p = PERIODOS.find((x) => x.id === id); const n = diasAtras(br); return p ? n <= p.d && n >= (p.id === "hoje" ? 0 : -365) : true; };

// ONDA 3 · Permissões com os nomes LITERAIS do legado, lidos no crm::layouts.nav e nos
// controllers: crm.access_all_leads / access_own_leads, access_all_schedule, access_all_campaigns,
// access_contact_login, view_all_call_log, view_reports, add_proposal_template, access_proposal,
// access_b2b_marketplace, access_sources, access_life_stage, business.settings.
const PERMS = {
  administrador: { label: "Administrador", nega: [] },
  comercial: { label: "Comercial (Larissa)", nega: ["crm.view_reports", "crm.access_b2b_marketplace", "crm.access_contact_login", "crm.add_proposal_template", "business.settings"] },
  financeiro: { label: "Financeiro (Eliana)", nega: ["crm.access_all_leads", "crm.access_all_campaigns", "crm.access_b2b_marketplace", "crm.access_sources", "business.settings"] },
};
const PERM_DE = {
  leads: "crm.access_all_leads", acompanhamentos: "crm.access_all_schedule", campanhas: "crm.access_all_campaigns",
  logins: "crm.access_contact_login", comissoes: "crm.access_contact_login", chamadas: "crm.view_all_call_log",
  relatorios: "crm.view_reports", modelo: "crm.add_proposal_template", propostas: "crm.access_proposal",
  marketplace: "crm.access_b2b_marketplace", taxonomias: "crm.access_sources", config: "business.settings",
};

// Leads — colunas do lead/index.blade.php (Contact com type=lead + custom_field_1..10).
const LEADS = [
  { id: 1, cod: "CO-0412", nome: "Padaria Estrela", tipo: "pj", cel: "(14) 99812-4410", email: "renato@padariaestrela.com.br", fonte: "whats", ult: "há 2h", ultTom: "recente", prox: dia(-1) + " " + hm(9, 30), fase: "novo", quem: "Larissa Prado", end: "Av. Bandeirantes, 1.204 — Jaú/SP", doc: "12.345.678/0001-90", criado: dia(0), cf1: "Renato Lopes", cf2: "Fachada + adesivos" },
  { id: 2, cod: "CO-0411", nome: "Clínica Vida Plena", tipo: "pj", cel: "(14) 3622-1180", email: "contato@vidaplena.com.br", fonte: "indic", ult: "ontem", ultTom: "recente", prox: dia(-2) + " " + hm(14, 0), fase: "contatado", quem: "Marcos Vinícius", end: "R. XV de Novembro, 88 — Jaú/SP", doc: "09.887.221/0001-14", criado: dia(1), cf1: "Marcos Vinícius", cf2: "Sinalização interna" },
  { id: 3, cod: "CO-0409", nome: "João Reis", tipo: "pf", cel: "(14) 99140-7722", email: "joao.reis@gmail.com", fonte: "site", ult: "há 3d", ultTom: "fresc", prox: "—", fase: "novo", quem: "Larissa Prado", end: "R. Amaral Gurgel, 45 — Jaú/SP", doc: "321.998.440-11", criado: dia(3), cf1: "", cf2: "Placa de porta" },
  { id: 4, cod: "CO-0404", nome: "Acme Comércio Ltda", tipo: "pj", cel: "(14) 3624-9010", email: "compras@acmecomercio.com.br", fonte: "tel", ult: "hoje", ultTom: "recente", prox: dia(0) + " " + hm(16, 30), fase: "qualificado", quem: "Wagner Ramos", end: "Rod. Mal. Rondon, km 302 — Jaú/SP", doc: "45.110.902/0001-77", criado: dia(6), cf1: "Camila Diniz", cf2: "Orçamento 3 lonas" },
  { id: 5, cod: "CO-0398", nome: "TechPro Equipamentos", tipo: "pj", cel: "(14) 99771-3388", email: "diego@techpro.com.br", fonte: "indic", ult: "há 1d", ultTom: "recente", prox: dia(-3) + " " + hm(10, 0), fase: "proposta", quem: "Marcos Vinícius", end: "R. Tenente Ary, 300 — Jaú/SP", doc: "22.400.118/0001-05", criado: dia(9), cf1: "Diego Vasconcellos", cf2: "Aguarda decisão" },
  { id: 6, cod: "CO-0392", nome: "Construtora Vértice", tipo: "pj", cel: "(14) 3602-4400", email: "eduardo@vertice.eng.br", fonte: "site", ult: "há 3d", ultTom: "fresc", prox: dia(-1) + " " + hm(8, 0), fase: "proposta", quem: "Wagner Ramos", end: "Av. Zeferino Ferrari, 91 — Jaú/SP", doc: "31.552.006/0001-63", criado: dia(14), cf1: "Eduardo Pessoa", cf2: "Tapume de obra" },
  { id: 7, cod: "CO-0388", nome: "Mercado União", tipo: "pj", cel: "(14) 99630-1187", email: "uniao@mercadouniao.com.br", fonte: "indic", ult: "há 6d", ultTom: "fresc", prox: dia(-5) + " " + hm(11, 0), fase: "negociacao", quem: "Larissa Prado", end: "R. Sete de Setembro, 512 — Jaú/SP", doc: "18.904.771/0001-20", criado: dia(21), cf1: "João Inst.", cf2: "Revisão de proposta" },
  { id: 8, cod: "CO-0377", nome: "Posto BR Centro", tipo: "pj", cel: "(14) 3622-8877", email: "financeiro@postobrcentro.com.br", fonte: "tel", ult: "há 12d", ultTom: "frio", prox: dia(-4) + " " + hm(15, 0), fase: "negociacao", quem: "Wagner Ramos", end: "Av. Dr. Manoel Rocha, 1.900 — Jaú/SP", doc: "05.221.480/0001-38", criado: dia(30), cf1: "Marcos Vinícius", cf2: "Decide sexta" },
  { id: 9, cod: "CO-0361", nome: "Studio Foco", tipo: "pj", cel: "(14) 99442-0021", email: "marina@studiofoco.com.br", fonte: "insta", ult: "há 21d", ultTom: "frio", prox: "—", fase: "cliente", quem: "Larissa Prado", end: "R. Prudente de Moraes, 77 — Jaú/SP", doc: "40.118.552/0001-92", criado: dia(48), cf1: "Marina T.", cf2: "Convertido em cliente" },
  { id: 10, cod: "CO-0344", nome: "Bar do Zeca", tipo: "pf", cel: "(14) 99118-4402", email: "", fonte: "balcao", ult: "há 62d", ultTom: "distante", prox: "—", fase: "perdido", quem: "Marcos Vinícius", end: "R. Rui Barbosa, 210 — Jaú/SP", doc: "112.887.330-04", criado: dia(90), cf1: "", cf2: "Preço fora do orçamento" },
];

// Acompanhamentos (crm_schedules) — colunas do schedule/index.blade.php.
const FOLLOWUPS = [
  { id: 101, contato: "Padaria Estrela", inicio: dia(0) + " " + hm(9, 30), fim: dia(0) + " " + hm(10, 0), status: "scheduled", tipo: "call", cat: "prospec", quem: ["Larissa Prado"], desc: "Confirmar medidas da fachada antes do orçamento.", extra: "Cliente prefere ligação de manhã.", titulo: "Ligar — medidas da fachada", por: "Larissa Prado", criado: dia(1), prazo: "fresh" },
  { id: 102, contato: "Acme Comércio Ltda", inicio: dia(0) + " " + hm(16, 30), fim: dia(0) + " " + hm(17, 0), status: "open", tipo: "meeting", cat: "orcamento", quem: ["Wagner Ramos"], desc: "Apresentar orçamento das 3 lonas 380g.", extra: "", titulo: "Reunião — orçamento 3 lonas", por: "Wagner Ramos", criado: dia(2), prazo: "aging" },
  { id: 103, contato: "Mercado União", inicio: dia(1) + " " + hm(11, 0), fim: dia(1) + " " + hm(11, 30), status: "completed", tipo: "call", cat: "orcamento", quem: ["Larissa Prado"], desc: "Revisão de valores do orçamento COT-2026-0190.", extra: "Pediu desconto de 6%.", titulo: "Follow-up orçamento", por: "Larissa Prado", criado: dia(4), prazo: "fresh" },
  { id: 104, contato: "Posto BR Centro", inicio: dia(-4) + " " + hm(15, 0), fim: dia(-4) + " " + hm(15, 30), status: "scheduled", tipo: "meeting", cat: "prospec", quem: ["Wagner Ramos", "Marcos Vinícius"], desc: "Visita técnica ao totem do posto.", extra: "Levar amostra de lona translúcida.", titulo: "Visita — totem", por: "Wagner Ramos", criado: dia(6), prazo: "fresh" },
  { id: 105, contato: "Supermercado Bom Dia", inicio: dia(3) + " " + hm(9, 0), fim: dia(3) + " " + hm(9, 20), status: "completed", tipo: "email", cat: "posvenda", quem: ["Marcos Vinícius"], desc: "Enviar arte final aprovada.", extra: "", titulo: "Enviar arte final", por: "Marcos Vinícius", criado: dia(5), prazo: "fresh" },
  { id: 106, contato: "Construtora Vértice", inicio: dia(6) + " " + hm(14, 0), fim: dia(6) + " " + hm(14, 30), status: "canceled", tipo: "call", cat: "prospec", quem: ["Wagner Ramos"], desc: "Cliente remarcou — obra parada.", extra: "Retomar em 30 dias.", titulo: "Ligar — tapume", por: "Wagner Ramos", criado: dia(8), prazo: "late" },
  { id: 107, contato: "Rota Livre Transportes", inicio: dia(-1) + " " + hm(8, 30), fim: dia(-1) + " " + hm(9, 0), status: "scheduled", tipo: "call", cat: "cobranca", quem: ["Eliana Souza"], desc: "Boleto POS-2026-0482 com saldo de R$ 1.620,00.", extra: "", titulo: "Cobrança — saldo parcial", por: "Eliana Souza", criado: dia(2), prazo: "aging" },
  { id: 108, contato: "Prefeitura de Jaú", inicio: dia(-3) + " " + hm(10, 0), fim: dia(-3) + " " + hm(11, 0), status: "open", tipo: "meeting", cat: "orcamento", quem: ["Wagner Ramos"], desc: "Empenho 2026/114 — conferir documentação.", extra: "Levar certidões.", titulo: "Reunião — empenho", por: "Wagner Ramos", criado: dia(7), prazo: "fresh" },
  { id: 109, contato: "Martinho Oficina", inicio: dia(2) + " " + hm(17, 0), fim: dia(2) + " " + hm(17, 15), status: "completed", tipo: "sms", cat: "posvenda", quem: ["Larissa Prado"], desc: "Aviso de retirada do banner.", extra: "", titulo: "Aviso de retirada", por: "Larissa Prado", criado: dia(3), prazo: "fresh" },
  { id: 110, contato: "TechPro Equipamentos", inicio: dia(-3) + " " + hm(10, 0), fim: dia(-3) + " " + hm(10, 30), status: "scheduled", tipo: "call", cat: "prospec", quem: ["Marcos Vinícius"], desc: "Retomar proposta enviada há 9 dias.", extra: "Sem resposta no e-mail.", titulo: "Retomar proposta", por: "Marcos Vinícius", criado: dia(9), prazo: "expired" },
  { id: 111, contato: "Agência Norte", inicio: dia(4) + " " + hm(13, 0), fim: dia(4) + " " + hm(13, 30), status: "completed", tipo: "call", cat: "instalacao", quem: ["Larissa Prado"], desc: "Combinar janela de instalação no shopping.", extra: "", titulo: "Agendar instalação", por: "Larissa Prado", criado: dia(6), prazo: "fresh" },
  { id: 112, contato: "Clínica Vida Plena", inicio: dia(-2) + " " + hm(14, 0), fim: dia(-2) + " " + hm(14, 30), status: "scheduled", tipo: "meeting", cat: "prospec", quem: ["Marcos Vinícius"], desc: "Levantamento de sinalização interna.", extra: "", titulo: "Levantamento no local", por: "Marcos Vinícius", criado: dia(1), prazo: "fresh" },
];

// Acompanhamentos recorrentes (is_recursive = 1) — segunda aba do blade.
const RECORRENTES = [
  { id: 201, status: "open", tipo: "call", cat: "cobranca", por: "payment_status", dias: 7, quem: ["Eliana Souza"], desc: "Cobrança automática de títulos vencidos há 7 dias.", extra: "Gera 1 acompanhamento por título.", titulo: "Cobrança 7 dias", autor: "Eliana Souza", criado: dia(40) },
  { id: 202, status: "open", tipo: "call", cat: "posvenda", por: "orders", dias: 90, quem: ["Larissa Prado"], desc: "Cliente sem pedido nos últimos 90 dias.", extra: "", titulo: "Reativação 90 dias", autor: "Wagner Ramos", criado: dia(120) },
  { id: 203, status: "canceled", tipo: "email", cat: "prospec", por: "orders", dias: 30, quem: ["Marcos Vinícius"], desc: "Sem pedido em 30 dias — desativado por volume.", extra: "Substituído pelo de 90 dias.", titulo: "Reativação 30 dias", autor: "Wagner Ramos", criado: dia(150) },
];

// Painel (CrmDashboardController) — números do crm_dashboard/index.blade.php.
const PAINEL = {
  todaysFollowups: 4, myLeads: 6, myConversion: 3,
  meusPorStatus: { scheduled: 5, open: 2, completed: 4, canceled: 1 },
  chamadas: { hoje: 7, ontem: 11, mes: 148 },
  totalClientes: 312, totalLeads: 10, totalFontes: FONTES.length, totalFases: FASES.length,
  porFonte: [
    { fonte: "Site", leads: 2, conv: "18%" }, { fonte: "WhatsApp", leads: 1, conv: "34%" },
    { fonte: "Indicação", leads: 3, conv: "52%" }, { fonte: "Telefone", leads: 2, conv: "21%" },
    { fonte: "Balcão", leads: 1, conv: "12%" }, { fonte: "Instagram", leads: 1, conv: "9%" },
    { fonte: "Mercado Livre", leads: 0, conv: "0 %" },
  ],
  porFase: FASES.map((f) => ({ fase: f.name, total: LEADS.filter((l) => l.fase === f.id).length })),
  aniversariosHoje: [{ id: 1, nome: "Renato Lopes — Padaria Estrela" }, { id: 2, nome: "Camila Diniz — Acme Comércio" }],
  aniversariosProximos: [
    { id: 3, nome: "Eduardo Pessoa — Construtora Vértice", quando: "27 de ago" },
    { id: 4, nome: "Marina T. — Studio Foco", quando: "02 de set" },
    { id: 5, nome: "Diego Vasconcellos — TechPro", quando: "09 de set" },
  ],
  porUsuario: USUARIOS.map((u, i) => ({
    user: u,
    scheduled: [3, 2, 1, 2][i], open: [1, 1, 0, 1][i], completed: [4, 3, 2, 2][i], canceled: [0, 1, 0, 1][i],
    nenhum: [0, 0, 1, 0][i], total: [8, 7, 4, 6][i],
  })),
  conversao: [{ quem: "Larissa Prado", total: 3 }, { quem: "Wagner Ramos", total: 2 }, { quem: "Marcos Vinícius", total: 1 }],
  chamadasPorUsuario: USUARIOS.map((u, i) => ({ user: u, hoje: [4, 2, 0, 1][i], mes: [62, 41, 8, 37][i], todas: [512, 388, 96, 301][i] })),
};

// Campanhas (crm_campaigns) — campaign/index.blade.php.
const CAMPANHAS = [
  { id: 1, nome: "Aniversariantes de agosto", tipo: "email", por: "Larissa Prado", criado: dia(2) + " " + hm(9, 12), assunto: "Parabéns! 10% em qualquer adesivo", destinos: 34, enviado: dia(2), corpo: "Feliz aniversário! Até o fim do mês, 10% em qualquer adesivo de recorte." },
  { id: 2, nome: "Retomada de orçamentos parados", tipo: "email", por: "Wagner Ramos", criado: dia(9) + " " + hm(15, 40), assunto: "Seu orçamento ainda está valendo", destinos: 12, enviado: dia(9), corpo: "Seu orçamento segue válido por 7 dias. Responda este e-mail que a gente fecha." },
  { id: 3, nome: "Semana da comunicação visual", tipo: "sms", por: "Larissa Prado", criado: dia(21) + " " + hm(8, 5), assunto: "12% OFF na semana da CV", destinos: 208, enviado: dia(21), corpo: "12% OFF em lonas e adesivos até sábado. Office Impresso — (14) 3622-0000." },
  { id: 4, nome: "Clientes sem compra há 90 dias", tipo: "sms", por: "Marcos Vinícius", criado: dia(45) + " " + hm(11, 22), assunto: "Sentimos sua falta", destinos: 76, enviado: dia(45), corpo: "Faz tempo! Traga sua arte e a gente imprime hoje mesmo." },
];

// Propostas (crm_proposals) + modelo (crm_proposal_templates).
const MODELO = { assunto: "Proposta comercial — Office Impresso", anexos: ["tabela-precos-2026.pdf", "portfolio-fachadas.pdf"], atualizado: dia(11) };
const PROPOSTAS = [
  { id: 1, contato: "Construtora Vértice", assunto: "Proposta comercial — tapume de obra 42 m²", por: "Wagner Ramos", data: dia(1) + " " + hm(17, 4), anexos: 2 },
  { id: 2, contato: "TechPro Equipamentos", assunto: "Proposta comercial — sinalização interna", por: "Marcos Vinícius", data: dia(9) + " " + hm(10, 26), anexos: 1 },
  { id: 3, contato: "Mercado União", assunto: "Proposta comercial — fachada + toldo", por: "Larissa Prado", data: dia(12) + " " + hm(9, 2), anexos: 3 },
  { id: 4, contato: "Prefeitura de Jaú", assunto: "Proposta comercial — placas de obra (lote 2026/114)", por: "Wagner Ramos", data: dia(18) + " " + hm(14, 47), anexos: 2 },
];

// Registro de chamadas (crm_call_logs) — call_logs/index.blade.php.
const CHAMADAS = [
  { id: 1, inicio: dia(0) + " " + hm(9, 12), fim: dia(0) + " " + hm(9, 18), dur: "00:06:12", tipo: "outgoing", numero: "(14) 99812-4410", contato: "Padaria Estrela", user: "Larissa Prado", criador: "Larissa Prado" },
  { id: 2, inicio: dia(0) + " " + hm(10, 2), fim: dia(0) + " " + hm(10, 5), dur: "00:02:48", tipo: "incoming", numero: "(14) 3622-1180", contato: "Clínica Vida Plena", user: "Marcos Vinícius", criador: "Marcos Vinícius" },
  { id: 3, inicio: dia(0) + " " + hm(11, 40), fim: dia(0) + " " + hm(11, 41), dur: "00:00:22", tipo: "missed", numero: "(14) 99140-7722", contato: "João Reis", user: "Larissa Prado", criador: "Larissa Prado" },
  { id: 4, inicio: dia(1) + " " + hm(14, 22), fim: dia(1) + " " + hm(14, 39), dur: "00:17:03", tipo: "outgoing", numero: "(14) 3624-9010", contato: "Acme Comércio Ltda", user: "Wagner Ramos", criador: "Wagner Ramos" },
  { id: 5, inicio: dia(1) + " " + hm(16, 50), fim: dia(1) + " " + hm(16, 58), dur: "00:08:31", tipo: "incoming", numero: "(14) 99630-1187", contato: "Mercado União", user: "Larissa Prado", criador: "Larissa Prado" },
  { id: 6, inicio: dia(2) + " " + hm(8, 45), fim: dia(2) + " " + hm(8, 52), dur: "00:07:14", tipo: "outgoing", numero: "(14) 3622-8877", contato: "Posto BR Centro", user: "Wagner Ramos", criador: "Wagner Ramos" },
  { id: 7, inicio: dia(3) + " " + hm(15, 5), fim: dia(3) + " " + hm(15, 9), dur: "00:04:02", tipo: "outgoing", numero: "(14) 99771-3388", contato: "TechPro Equipamentos", user: "Marcos Vinícius", criador: "Marcos Vinícius" },
  { id: 8, inicio: dia(4) + " " + hm(9, 30), fim: dia(4) + " " + hm(9, 33), dur: "00:03:19", tipo: "incoming", numero: "(14) 99442-0021", contato: "Studio Foco", user: "Larissa Prado", criador: "Larissa Prado" },
];
const TIPO_CHAMADA = { incoming: "Recebida", outgoing: "Efetuada", missed: "Perdida" };

// Login de contatos (users com contact_id) + comissões (crm_contact_person_commissions).
const LOGINS = [
  { id: 1, contato: "Rota Livre Transportes", user: "rotalivre", nome: "Daniela Prado", email: "daniela@rotalivre.com.br", depto: "Compras", cargo: "Analista" },
  { id: 2, contato: "Martinho Oficina", user: "martinho", nome: "Martinho Alves", email: "martinho@oficinamartinho.com.br", depto: "Diretoria", cargo: "Proprietário" },
  { id: 3, contato: "Prefeitura de Jaú", user: "pmj.compras", nome: "Regina Toledo", email: "compras@jau.sp.gov.br", depto: "Licitações", cargo: "Pregoeira" },
  { id: 4, contato: "Agência Norte", user: "agnorte", nome: "Paulo Serra", email: "paulo@agencianorte.com.br", depto: "Atendimento", cargo: "Diretor de arte" },
];
const COMISSOES = [
  { id: 1, data: dia(1), contato: "Agência Norte", nome: "Paulo Serra", cel: "(14) 3622-1180", inv: "POS-2026-0479", loc: "Filial Centro", valor: 93.77 },
  { id: 2, data: dia(2), contato: "Rota Livre Transportes", nome: "Daniela Prado", cel: "(14) 99812-4410", inv: "POS-2026-0482", loc: "Matriz", valor: 156.0 },
  { id: 3, data: dia(5), contato: "Martinho Oficina", nome: "Martinho Alves", cel: "(14) 99640-2299", inv: "POS-2026-0478", loc: "Matriz", valor: 37.1 },
  { id: 4, data: dia(9), contato: "Prefeitura de Jaú", nome: "Regina Toledo", cel: "(14) 3602-9000", inv: "POS-2026-0470", loc: "Filial Centro", valor: 624.0 },
];

// Pedidos de ordem (order_request — transaction type sales_order do portal do contato).
const PEDIDOS = [
  { id: 1, data: dia(0) + " " + hm(8, 22), num: "SO-2026-0091", loc: "Matriz", status: "ordered", contato: "Rota Livre Transportes", restante: 12, total: 4380 },
  { id: 2, data: dia(1) + " " + hm(16, 4), num: "SO-2026-0090", loc: "Matriz", status: "partial", contato: "Martinho Oficina", restante: 3, total: 690 },
  { id: 3, data: dia(4) + " " + hm(10, 41), num: "SO-2026-0088", loc: "Filial Centro", status: "completed", contato: "Agência Norte", restante: 0, total: 2210 },
  { id: 4, data: dia(8) + " " + hm(9, 15), num: "SO-2026-0084", loc: "Matriz", status: "ordered", contato: "Prefeitura de Jaú", restante: 24, total: 12480 },
];
const STATUS_PEDIDO = [{ id: "ordered", name: "Pedido" }, { id: "partial", name: "Parcialmente atendido" }, { id: "completed", name: "Atendido" }];

const CBD = { FONTES, FASES, STATUS, TIPOS, CATEGORIAS, USUARIOS, LOCAIS, POR, NOTIFICAR, LEADS, FOLLOWUPS, RECORRENTES, PAINEL, CAMPANHAS, MODELO, PROPOSTAS, CHAMADAS, TIPO_CHAMADA, LOGINS, COMISSOES, PEDIDOS, STATUS_PEDIDO, fmtBRL, dia, hm, rotulo };
window.CBD = CBD;

// ─────────── Peças (mesmas do produto/venda-blade: components.filters + widget + DataTablePro) ───────────
const TOM = { scheduled: "info", open: "warn", completed: "ok", canceled: "mute", novo: "info", contatado: "info", qualificado: "warn", proposta: "warn", negociacao: "warn", cliente: "ok", perdido: "neg", incoming: "info", outgoing: "ok", missed: "neg", ordered: "info", partial: "warn", completed_p: "ok" };
function Pill({ tom = "mute", children }) {
  // Sucesso = --pos/--pos-soft (tokens do shell). --ok/--ok-soft só existem dentro de .mockup-page:
  // fora dela a declaração cai como inválida e a pílula verde perdia fundo e virava borda branca.
  const cor = { ok: ["var(--pos)", "var(--pos-soft)"], warn: ["var(--warn)", "var(--warn-soft)"], neg: ["var(--neg)", "var(--neg-soft)"], info: ["var(--accent)", "var(--accent-soft)"], mute: ["var(--text-mute)", "var(--bg-2)"] }[tom] || ["var(--text-mute)", "var(--bg-2)"];
  return <span style={{ display: "inline-flex", alignItems: "center", gap: 4, fontSize: 10.5, fontWeight: 600, padding: "2px 7px", borderRadius: 999, color: cor[0], background: cor[1], border: "1px solid " + cor[0], borderColor: "color-mix(in oklch, " + cor[0] + " 25%, transparent)", whiteSpace: "nowrap" }}>{children}</span>;
}
const Badge = ({ kind, value, rel }) => { const { StatusBadge } = DS(); return StatusBadge ? <StatusBadge kind={kind} value={value} rel={rel} /> : <Pill>{value}</Pill>; };

function Filtros({ campos, f, setF, nota }) {
  const { Widget, Fld, Sel } = UI();
  const [aberto, setAberto] = useState(true);
  if (!Widget) return null;
  return (
    <Widget contrato="crm-filtros" titulo={<><Ic name="search" size={13} /> Filtros</>} nota={aberto ? null : "recolhidos"}>
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

function Grade({ columns, rows, densa, altura = 420, selectable, onSelectionChange, onRowClick }) {
  const { DataTablePro, EmptyState } = DS();
  if (!rows.length) {
    return EmptyState
      ? <div style={{ padding: 24 }}><EmptyState variant="no-results" icon={<Ic name="search" size={18} />} title="Nada com esses filtros" description="Nenhum registro bate com o que está filtrado. Limpe um filtro ou amplie o período." /></div>
      : <p className="pb-help" style={{ padding: 16 }}>Nada com esses filtros.</p>;
  }
  if (!DataTablePro) return <p className="pb-help" style={{ padding: 16 }}>A grade do DS não carregou.</p>;
  return <div className="pb-grid-pro"><DataTablePro columns={columns} rows={rows} height={altura} density={densa ? "compact" : "comfortable"} selectable={selectable} onSelectionChange={onSelectionChange} onRowClick={onRowClick} /></div>;
}

function Toolbar({ busca, setBusca, ph, densa, setDensa, children }) {
  return (
    <div className="pb-toolbar" data-contract="crm-toolbar">
      <div className="pb-busca"><Ic name="search" size={12} /><input value={busca} onChange={(e) => setBusca(e.target.value)} placeholder={ph} /></div>
      <div className="sp" />
      {setDensa &&
        <div className="pb-seg" role="group" aria-label="Densidade da tabela">
          <button className={densa ? "" : "on"} onClick={() => setDensa(false)}>Confortável</button>
          <button className={densa ? "on" : ""} onClick={() => setDensa(true)}>Compacto</button>
        </div>}
      {children}
    </div>
  );
}
const Rodape = ({ children }) => <div className="pb-pag" data-contract="crm-rodape">{children}</div>;

// Tabela simples (as `table no-margin` dos boxes do painel — chave/valor, sem DataTable).
function Mini({ head, rows, vazio = "Sem dados" }) {
  return (
    <table className="pb-tbl" style={{ width: "100%" }}>
      {head && <thead><tr>{head.map((h, i) => <th key={i} style={i > 0 ? { textAlign: "right" } : null}>{h}</th>)}</tr></thead>}
      <tbody>
        {rows.length === 0 && <tr><td colSpan={(head || [1]).length} style={{ textAlign: "center", color: "var(--text-mute)" }}>{vazio}</td></tr>}
        {rows.map((r, i) => <tr key={i}>{r.map((c, j) => <td key={j} className={j > 0 ? "mono" : ""} style={j > 0 ? { textAlign: "right" } : null}>{c}</td>)}</tr>)}
      </tbody>
    </table>
  );
}

window.CBUI = { Filtros, Grade, Toolbar, Rodape, Mini, Pill, Badge, Ic, TOM };

// ─────────── 1. Painel (crm_dashboard/index.blade.php) ───────────
function TelaPainel({ avisar }) {
  const { Widget } = UI();
  const { KpiCard, Chart } = DS();
  const [marcados, setMarcados] = useState([]);
  const P = PAINEL;
  const kpi = (label, value, tone) => KpiCard ? <KpiCard label={label} value={value} tone={tone} /> : <div className="pb-widget"><div className="pb-widget-b"><small>{label}</small><b>{value}</b></div></div>;
  const toggle = (id) => setMarcados((m) => m.includes(id) ? m.filter((x) => x !== id) : [...m, id]);

  return (
    <>
      <div className="pb-grid c4">
        {kpi("Acompanhamentos de hoje", String(P.todaysFollowups), "info")}
        {kpi("Meus leads", String(P.myLeads), "info")}
        {kpi("Meus leads convertidos", String(P.myConversion), "success")}
        {kpi("Chamadas hoje", String(P.chamadas.hoje), "warning")}
      </div>
      <div className="pb-grid c2">
        <Widget contrato="crm-painel-meus" titulo="Meus acompanhamentos">
          <Mini rows={STATUS.map((s) => [s.name, P.meusPorStatus[s.id] ?? 0])} />
        </Widget>
        <Widget contrato="crm-painel-chamadas" titulo="Meus registros de chamadas">
          <Mini rows={[["Chamadas hoje", P.chamadas.hoje], ["Chamadas ontem", P.chamadas.ontem], ["Chamadas neste mês", P.chamadas.mes]]} />
        </Widget>
      </div>
      <div className="pb-grid c4">
        {kpi("Clientes", String(P.totalClientes))}
        {kpi("Leads", String(P.totalLeads))}
        {kpi("Fontes", String(P.totalFontes), "warning")}
        {kpi("Estágios de vida", String(P.totalFases), "warning")}
      </div>
      <div className="pb-grid c2">
        <Widget contrato="crm-painel-fontes" titulo="Fontes">
          <Mini head={["Fonte", "Total", "Conversão"]} rows={P.porFonte.map((r) => [r.fonte, r.leads, r.conv])} />
        </Widget>
        <Widget contrato="crm-painel-fases" titulo="Estágios de vida">
          <Mini head={["Estágio", "Total"]} rows={P.porFase.map((r) => [r.fase, r.total])} />
        </Widget>
      </div>
      <Widget contrato="crm-painel-aniversarios" titulo={<>Aniversários</>} nota={marcados.length ? marcados.length + " selecionado(s)" : null}>
        <div className="pb-filters-h" style={{ marginBottom: 10 }}>
          <span className="pb-help">Marque quem vai receber e mande os parabéns como campanha.</span>
          <button className="os-btn sm primary" onClick={() => marcados.length ? avisar("Campanha de aniversário criada para " + marcados.length + " contato(s).", "ok") : avisar("Selecione os contatos para enviar os desejos.", "warn")}>
            <Ic name="send" size={12} /> Enviar desejos
          </button>
        </div>
        <div className="pb-grid c2">
          <div>
            <p className="pb-help" style={{ marginBottom: 6, fontWeight: 700 }}>Hoje</p>
            {P.aniversariosHoje.map((b) => (
              <label key={b.id} className="pb-chk" style={{ padding: "5px 0" }}>
                <input type="checkbox" checked={marcados.includes(b.id)} onChange={() => toggle(b.id)} /><b>{b.nome}</b>
              </label>
            ))}
          </div>
          <div>
            <p className="pb-help" style={{ marginBottom: 6, fontWeight: 700 }}>Próximos</p>
            {P.aniversariosProximos.map((b) => (
              <label key={b.id} className="pb-chk" style={{ padding: "5px 0" }}>
                <input type="checkbox" checked={marcados.includes(b.id)} onChange={() => toggle(b.id)} />
                <b>{b.nome}<small>{b.quando}</small></b>
              </label>
            ))}
          </div>
        </div>
      </Widget>
      <Widget contrato="crm-painel-por-usuario" titulo="Acompanhamentos por usuário" flush>
        <Grade altura={220} columns={[
          { key: "user", label: "Usuário", width: 200 },
          ...STATUS.map((s) => ({ key: s.id, label: s.name, width: 118, align: "right", mono: true })),
          { key: "nenhum", label: "Nenhum", width: 100, align: "right", mono: true },
          { key: "total", label: "Acompanhamentos totais", width: 190, align: "right", mono: true },
        ]} rows={P.porUsuario.map((r) => ({ ...r, id: r.user }))} />
      </Widget>
      <div className="pb-grid c2">
        <Widget contrato="crm-painel-conversao" titulo="Leads convertidos em cliente">
          <Mini head={["Convertido por", "Total"]} rows={P.conversao.map((r) => [r.quem, r.total])} />
        </Widget>
        <Widget contrato="crm-painel-chamadas-todos" titulo="Registro de chamadas — todos os usuários">
          <Mini head={["Usuário", "Hoje", "No mês", "Todas"]} rows={P.chamadasPorUsuario.map((r) => [r.user, r.hoje, r.mes, r.todas])} />
        </Widget>
      </div>
    </>
  );
}

// ─────────── 2. Leads (lead/index.blade.php — list_view + kanban) ───────────
function TelaLeads({ avisar, densa, setDensa, abrir, perms }) {
  const { Widget, Kebab } = UI();
  const { Drawer, DrawerSection, Modal } = DS();
  const [f, setF] = useState({});
  const [busca, setBusca] = useState("");
  const [vista, setVista] = useState("list_view");
  const [sel, setSel] = useState([]);
  const [ver, setVer] = useState(null);
  const [leads, setLeads] = useState(LEADS);
  const [arrastando, setArrastando] = useState(null);
  const [alvoCol, setAlvoCol] = useState(null);
  // ONDA 4 · post-life-stage do blade: converter pede a fase seguinte antes de mandar pra Clientes.
  const [converter, setConverter] = useState(null);

  const rows = useMemo(() => leads.filter((l) =>
    (!f.fonte || l.fonte === f.fonte) && (!f.fase || l.fase === f.fase) && (!f.quem || l.quem === f.quem) &&
    (!busca || (l.nome + " " + l.cod + " " + l.cel).toLowerCase().includes(busca.toLowerCase()))), [leads, f, busca]);

  const mover = (id, fase) => {
    setLeads((ls) => ls.map((l) => l.id === id ? { ...l, fase } : l));
    const l = leads.find((x) => x.id === id);
    if (l && l.fase !== fase) avisar(l.nome + " → " + rotulo(FASES, fase), "ok");
  };

  const cols = [
    { key: "acao", label: "Ação", width: 92, resizable: false },
    { key: "cod", label: "ID do contato", width: 118, mono: true, sortable: true },
    { key: "nome", label: "Nome", width: 210, sortable: true },
    { key: "cel", label: "Celular", width: 138, mono: true },
    { key: "email", label: "E-mail", width: 226 },
    { key: "fonte", label: "Fonte", width: 126 },
    { key: "ult", label: "Último acompanhamento", width: 178 },
    { key: "prox", label: "Próximo acompanhamento", width: 178, mono: true },
    { key: "fase", label: "Estágio de vida", width: 150 },
    { key: "quem", label: "Atribuído a", width: 150 },
    { key: "end", label: "Endereço", width: 250 },
    { key: "doc", label: "CNPJ / CPF", width: 158, mono: true },
    { key: "criado", label: "Adicionado em", width: 128, mono: true },
    { key: "cf1", label: "Pessoa de contato", width: 160 },
    { key: "cf2", label: "Interesse", width: 190 },
  ];
  const grade = rows.map((l) => ({
    id: l.id, _s: l,
    acao: Kebab ? <Kebab acoes={[
      { l: "Exibir lead", ic: "search", on: () => abrir("ficha", l) },
      { l: "Adicionar acompanhamento", ic: "plus", on: () => abrir("novo-acomp", l) },
      { l: "Converter para cliente", ic: "check", on: () => setConverter({ lead: l, fase: "cliente" }) },
    ]} /> : <button className="os-btn sm ghost" onClick={() => abrir("ficha", l)}>Ver</button>,
    cod: l.cod, nome: l.nome, cel: l.cel, email: l.email || "—",
    fonte: rotulo(FONTES, l.fonte),
    ult: <Badge kind="frescor" value={l.ultTom} rel={l.ult} />,
    prox: l.prox, fase: <Pill tom={TOM[l.fase]}>{rotulo(FASES, l.fase)}</Pill>,
    quem: l.quem, end: l.end, doc: l.doc, criado: l.criado, cf1: l.cf1 || "—", cf2: l.cf2 || "—",
  }));

  const colunasKanban = FASES.filter((x) => x.id !== "perdido");

  return (
    <>
      <Filtros nota="Fonte, estágio de vida e responsável — os três selects do blade." f={f} setF={setF} campos={[
        { k: "fonte", l: "Fonte", op: FONTES },
        ...(vista === "list_view" ? [{ k: "fase", l: "Estágio de vida", op: FASES }] : []),
        { k: "quem", l: "Atribuído a", op: USUARIOS.map((u) => ({ id: u, name: u })) },
      ]} />
      <Widget contrato="crm-leads" titulo="Todos os leads" nota={rows.length + " de " + leads.length} flush>
        <Toolbar busca={busca} setBusca={setBusca} ph="Buscar por nome, ID ou celular" densa={densa} setDensa={vista === "list_view" ? setDensa : null}>
          <div className="pb-seg" role="group" aria-label="Modo de exibição">
            <button className={vista === "list_view" ? "on" : ""} onClick={() => setVista("list_view")}>Exibição de lista</button>
            <button className={vista === "kanban" ? "on" : ""} onClick={() => setVista("kanban")}>Kanban</button>
          </div>
          <button className="os-btn sm primary" onClick={() => avisar("Formulário de lead aberto.", "ok")}><Ic name="plus" size={12} /> Adicionar</button>
        </Toolbar>
        {vista === "list_view"
          ? <>
              <Grade columns={cols} rows={grade} densa={densa} altura={430} selectable onSelectionChange={setSel} onRowClick={(r) => setVer(r._s)} />
              <Rodape>
                <span className="pb-help">{sel.length ? sel.length + " lead(s) selecionado(s)" : "Selecione linhas para mudar o local do contato."}</span>
                <div className="sp" />
                <button className="os-btn sm" disabled={!sel.length} onClick={() => avisar(sel.length + " contato(s) adicionados ao local.", "ok")}>Adicionar ao local</button>
                <button className="os-btn sm" disabled={!sel.length} onClick={() => avisar(sel.length + " contato(s) removidos do local.", "ok")}>Remover do local</button>
              </Rodape>
            </>
          : <div className="cb-kanban">
              {colunasKanban.map((c) => {
                const cards = rows.filter((l) => l.fase === c.id);
                return (
                  <section key={c.id} className={"cb-kcol" + (alvoCol === c.id ? " alvo" : "")}
                    onDragOver={(e) => { e.preventDefault(); e.dataTransfer.dropEffect = "move"; setAlvoCol(c.id); }}
                    onDragLeave={() => setAlvoCol((a) => a === c.id ? null : a)}
                    onDrop={(e) => { e.preventDefault(); setAlvoCol(null); const id = +e.dataTransfer.getData("text/plain"); if (id) mover(id, c.id); }}>
                    <header><b>{c.name}</b><span className="mono">{cards.length}</span></header>
                    <div className="cb-kbody">
                      {cards.length === 0 && <p className="pb-help">arraste aqui</p>}
                      {cards.map((l) => (
                        <article key={l.id} className={"cb-kcard" + (arrastando === l.id ? " arrastando" : "")}
                          draggable
                          onDragStart={(e) => { setArrastando(l.id); e.dataTransfer.effectAllowed = "move"; e.dataTransfer.setData("text/plain", String(l.id)); }}
                          onDragEnd={() => { setArrastando(null); setAlvoCol(null); }}
                          onClick={() => abrir("ficha", l)}>
                          <b>{l.nome}</b>
                          <small>{rotulo(FONTES, l.fonte)} · {l.quem}</small>
                          <div className="cb-kfoot"><Badge kind="frescor" value={l.ultTom} rel={l.ult} /><span className="mono">{l.prox !== "—" ? l.prox.split(" ")[1] : "—"}</span></div>
                        </article>
                      ))}
                    </div>
                  </section>
                );
              })}
            </div>}
      </Widget>
      {Modal && converter &&
        <Modal open={!!converter} onClose={() => setConverter(null)} title={"Converter " + converter.lead.nome + " em cliente"}
          footer={<>
            <button className="os-btn" onClick={() => setConverter(null)}>Cancelar</button>
            <button className="os-btn primary" onClick={() => {
              mover(converter.lead.id, converter.fase);
              avisar(converter.lead.nome + " convertido em cliente — abrindo o cadastro.", "ok");
              setConverter(null);
              if (window.__selectRoute) window.__selectRoute("clientes");
            }}>Converter e abrir em Clientes</button>
          </>}>
          <p className="pb-help">O contato deixa de ser lead e passa a cliente. Escolha em que estágio de vida ele fica depois da conversão (post-life-stage do módulo).</p>
          <div style={{ display: "flex", flexWrap: "wrap", gap: 8, marginTop: 12 }}>
            {FASES.filter((x) => x.id !== "perdido").map((x) => (
              <button key={x.id} className={"os-btn sm" + (converter.fase === x.id ? " primary" : "")} onClick={() => setConverter({ ...converter, fase: x.id })}>{x.name}</button>
            ))}
          </div>
        </Modal>}
      {Drawer && ver &&
        <Drawer open={!!ver} onClose={() => setVer(null)} badge={rotulo(FASES, ver.fase)} title={ver.nome} subtitle={ver.cod + " · " + rotulo(FONTES, ver.fonte) + " · " + ver.quem}
          footer={<>
            <button className="os-btn" onClick={() => { setVer(null); abrir("ficha", ver); }}>Abrir ficha completa</button>
            <button className="os-btn primary" onClick={() => { setVer(null); setConverter({ lead: ver, fase: "cliente" }); }}>Converter para cliente</button>
          </>}>
          <DrawerSection title="Informação do lead">
            <Mini rows={[["Celular", ver.cel], ["E-mail", ver.email || "—"], ["CNPJ / CPF", ver.doc], ["Tipo", <Badge kind="tipo" value={ver.tipo} />], ["Endereço", ver.end], ["Adicionado em", ver.criado]]} />
          </DrawerSection>
          <DrawerSection title="Acompanhamento">
            <Mini rows={[["Último", ver.ult], ["Próximo", ver.prox], ["Pessoa de contato", ver.cf1 || "—"], ["Interesse", ver.cf2 || "—"]]} />
          </DrawerSection>
        </Drawer>}
    </>
  );
}

// ─────────── 3. Acompanhamentos (schedule/index.blade.php + schedule/create) ───────────
function TelaAcompanhamentos({ avisar, densa, setDensa, abrir }) {
  const { Widget, Kebab, Fld, Sel, Modal } = UI();
  const { Drawer, DrawerSection } = DS();
  const [f, setF] = useState({});
  const [aba, setAba] = useState("todos");
  const [busca, setBusca] = useState("");
  const [novo, setNovo] = useState(null);
  const [ver, setVer] = useState(null);
  const [lista, setLista] = useState(FOLLOWUPS);

  const rows = useMemo(() => lista.filter((s) =>
    (!f.contato || s.contato === f.contato) && (!f.quem || s.quem.includes(f.quem)) && (!f.status || s.status === f.status) &&
    (!f.tipo || s.tipo === f.tipo) && (!f.cat || s.cat === f.cat) && (!f.por || true) && noPeriodo(f.periodo, s.inicio) &&
    (!busca || (s.titulo + " " + s.contato).toLowerCase().includes(busca.toLowerCase()))), [lista, f, busca]);

  const cols = [
    { key: "acao", label: "Ação", width: 92, resizable: false },
    { key: "contato", label: "Contato", width: 200, sortable: true },
    { key: "inicio", label: "Início", width: 150, mono: true, sortable: true },
    { key: "fim", label: "Fim", width: 150, mono: true },
    { key: "status", label: "Status", width: 126 },
    { key: "tipo", label: "Tipo de acompanhamento", width: 168 },
    { key: "cat", label: "Categoria", width: 138 },
    { key: "quem", label: "Atribuído a", width: 178 },
    { key: "desc", label: "Descrição", width: 280 },
    { key: "extra", label: "Informação adicional", width: 230 },
    { key: "titulo", label: "Título", width: 200 },
    { key: "por", label: "Adicionado por", width: 150 },
    { key: "criado", label: "Adicionado em", width: 128, mono: true },
  ];
  const grade = rows.map((s) => ({
    id: s.id, _s: s,
    acao: Kebab ? <Kebab acoes={[
      { l: "Ver acompanhamento", ic: "search", on: () => setVer(s) },
      { l: "Editar", ic: "pencil", on: () => abrir("editar-acomp", { valor: s, onSalvar: (v) => setLista((ls) => ls.map((x) => x.id === s.id ? { ...x, ...v, quem: [v.quem] } : x)) }) },
      { l: "Ver log", ic: "clock", on: () => abrir("logs", s) },
    ]} /> : <button className="os-btn sm ghost" onClick={() => setVer(s)}>Ver</button>,
    contato: s.contato, inicio: s.inicio, fim: s.fim,
    status: <Pill tom={TOM[s.status]}>{rotulo(STATUS, s.status)}</Pill>,
    tipo: rotulo(TIPOS, s.tipo), cat: rotulo(CATEGORIAS, s.cat), quem: s.quem.join(", "),
    desc: s.desc, extra: s.extra || "—", titulo: s.titulo, por: s.por, criado: s.criado,
  }));

  const colsRec = [
    { key: "acao", label: "Ação", width: 92, resizable: false },
    { key: "status", label: "Status", width: 126 },
    { key: "tipo", label: "Tipo de acompanhamento", width: 168 },
    { key: "cat", label: "Categoria", width: 138 },
    { key: "por", label: "Acompanhamento por", width: 178 },
    { key: "dias", label: "Em dias", width: 100, align: "right", mono: true },
    { key: "quem", label: "Atribuído a", width: 178 },
    { key: "desc", label: "Descrição", width: 300 },
    { key: "extra", label: "Informação adicional", width: 230 },
    { key: "titulo", label: "Título", width: 190 },
    { key: "autor", label: "Adicionado por", width: 150 },
    { key: "criado", label: "Adicionado em", width: 128, mono: true },
  ];
  const gradeRec = RECORRENTES.map((s) => ({
    id: s.id, _s: s,
    acao: <button className="os-btn sm ghost" onClick={() => abrir("recorrente")}>Editar</button>,
    status: <Pill tom={TOM[s.status]}>{rotulo(STATUS, s.status)}</Pill>,
    tipo: rotulo(TIPOS, s.tipo), cat: rotulo(CATEGORIAS, s.cat), por: rotulo(POR, s.por), dias: s.dias,
    quem: s.quem.join(", "), desc: s.desc, extra: s.extra || "—", titulo: s.titulo, autor: s.autor, criado: s.criado,
  }));

  const contagem = (campo, l) => l.map((x) => ({ l: x.name, n: rows.filter((r) => r[campo] === x.id).length })).filter((x) => x.n);

  return (
    <>
      <Filtros nota="Contato, responsável, status, tipo, período, acompanhamento por e categoria." f={f} setF={setF} campos={[
        { k: "contato", l: "Contato", op: LEADS.map((l) => ({ id: l.nome, name: l.nome })) },
        { k: "quem", l: "Atribuído", op: USUARIOS.map((u) => ({ id: u, name: u })) },
        { k: "status", l: "Status", op: STATUS },
        { k: "tipo", l: "Tipo de acompanhamento", op: TIPOS },
        { k: "periodo", l: "Intervalo de datas", op: PERIODOS },
        { k: "por", l: "Acompanhamento por", op: POR },
        { k: "cat", l: "Categoria", op: CATEGORIAS },
      ]} />
      <Widget contrato="crm-acompanhamentos" titulo="Todos os acompanhamentos" nota={rows.length + " de " + lista.length} flush>
        <Toolbar busca={busca} setBusca={setBusca} ph="Buscar por título ou contato" densa={densa} setDensa={setDensa}>
          <button className="os-btn sm" onClick={() => abrir("recorrente")}><Ic name="plus" size={12} /> Recorrente</button>
          <button className="os-btn sm" onClick={() => abrir("antecipado")}><Ic name="plus" size={12} /> Acompanhamento antecipado</button>
          <button className="os-btn sm primary" onClick={() => setNovo({ status: "scheduled", tipo: "call", notificar: false, via: { mail: true, sms: false }, antes: 30, unidade: "minute" })}><Ic name="plus" size={12} /> Adicionar</button>
        </Toolbar>
        <nav className="cli-moduletopnav" aria-label="Abas de acompanhamento" style={{ padding: "0 12px" }}>
          <button className={"cli-moduletopnav-tab " + (aba === "todos" ? "active" : "")} onClick={() => setAba("todos")}>Acompanhamentos</button>
          <button className={"cli-moduletopnav-tab " + (aba === "recor" ? "active" : "")} onClick={() => setAba("recor")}>Acompanhamento recorrente</button>
        </nav>
        {aba === "todos"
          ? <>
              <Grade columns={cols} rows={grade} densa={densa} altura={430} onRowClick={(r) => setVer(r._s)} />
              <Rodape>
                <span className="pb-help">Total: {rows.length}</span>
                <div className="sp" />
                <span className="pb-help">{contagem("status", STATUS).map((x) => x.l + ": " + x.n).join(" · ") || "—"}</span>
                <span className="pb-help">{contagem("tipo", TIPOS).map((x) => x.l + ": " + x.n).join(" · ") || "—"}</span>
              </Rodape>
            </>
          : <Grade columns={colsRec} rows={gradeRec} densa={densa} altura={300} />}
      </Widget>

      {Modal && novo &&
        <Modal titulo="Adicionar acompanhamento" onClose={() => setNovo(null)}
          acoes={<>
            <button className="os-btn" onClick={() => setNovo(null)}>Fechar</button>
            <button className="os-btn primary" onClick={() => {
              if (!novo.titulo || !novo.contato) return avisar("Título e cliente/lead são obrigatórios.", "warn");
              setLista((ls) => [{
                id: Date.now(), contato: novo.contato, inicio: novo.inicio || dia(0) + " " + hm(9, 0), fim: novo.fim || dia(0) + " " + hm(9, 30),
                status: novo.status, tipo: novo.tipo, cat: novo.cat || "prospec", quem: [novo.quem || "Wagner Ramos"],
                desc: novo.desc || "", extra: "", titulo: novo.titulo, por: "Wagner Ramos", criado: dia(0), prazo: "fresh",
              }, ...ls]);
              setNovo(null); avisar("Acompanhamento salvo.", "ok");
            }}>Salvar</button>
          </>}>
          <div className="pb-grid c3">
            <Fld label="Título" req span={2}><input value={novo.titulo || ""} onChange={(e) => setNovo({ ...novo, titulo: e.target.value })} placeholder="Ligar — medidas da fachada" /></Fld>
            <Fld label="Cliente / lead" req><Sel value={novo.contato || ""} onChange={(v) => setNovo({ ...novo, contato: v })} options={LEADS.map((l) => ({ id: l.nome, name: l.nome }))} vazio="Selecione" /></Fld>
            <Fld label="Status"><Sel value={novo.status} onChange={(v) => setNovo({ ...novo, status: v })} options={STATUS} vazio="Selecione" /></Fld>
            <Fld label="Início" req><input value={novo.inicio || ""} onChange={(e) => setNovo({ ...novo, inicio: e.target.value })} placeholder="dd/mm/aaaa hh:mm" /></Fld>
            <Fld label="Fim" req><input value={novo.fim || ""} onChange={(e) => setNovo({ ...novo, fim: e.target.value })} placeholder="dd/mm/aaaa hh:mm" /></Fld>
            <Fld label="Descrição" span={3}><textarea value={novo.desc || ""} onChange={(e) => setNovo({ ...novo, desc: e.target.value })} /></Fld>
            <Fld label="Tipo de acompanhamento" req><Sel value={novo.tipo} onChange={(v) => setNovo({ ...novo, tipo: v })} options={TIPOS} vazio="Selecione" /></Fld>
            <Fld label="Categoria" req><Sel value={novo.cat || ""} onChange={(v) => setNovo({ ...novo, cat: v })} options={CATEGORIAS} vazio="Selecione" /></Fld>
            <Fld label="Atribuído" req><Sel value={novo.quem || ""} onChange={(v) => setNovo({ ...novo, quem: v })} options={USUARIOS.map((u) => ({ id: u, name: u }))} vazio="Selecione" /></Fld>
          </div>
          <label className="pb-chk" style={{ marginTop: 12 }}>
            <input type="checkbox" checked={novo.notificar} onChange={(e) => setNovo({ ...novo, notificar: e.target.checked })} />
            <b>Enviar notificação<small>A notificação automática sai no tempo escolhido antes do início do acompanhamento.</small></b>
          </label>
          {novo.notificar &&
            <div className="pb-grid c3" style={{ marginTop: 10 }}>
              <Fld label="Notificar via">
                <div style={{ display: "flex", gap: 14 }}>
                  <label className="pb-chk"><input type="checkbox" checked={novo.via.sms} onChange={(e) => setNovo({ ...novo, via: { ...novo.via, sms: e.target.checked } })} /><b>SMS</b></label>
                  <label className="pb-chk"><input type="checkbox" checked={novo.via.mail} onChange={(e) => setNovo({ ...novo, via: { ...novo.via, mail: e.target.checked } })} /><b>E-mail</b></label>
                </div>
              </Fld>
              <Fld label="Notificar antes" req><input className="num" value={novo.antes} onChange={(e) => setNovo({ ...novo, antes: e.target.value })} /></Fld>
              <Fld label="Unidade"><Sel value={novo.unidade} onChange={(v) => setNovo({ ...novo, unidade: v })} options={NOTIFICAR} vazio="Selecione" /></Fld>
            </div>}
        </Modal>}

      {Drawer && ver &&
        <Drawer open={!!ver} onClose={() => setVer(null)} badge={rotulo(STATUS, ver.status)} title={ver.titulo} subtitle={ver.contato + " · " + rotulo(TIPOS, ver.tipo)}
          footer={<>
            <button className="os-btn" onClick={() => { setVer(null); abrir("logs", ver); }}>Log de acompanhamento</button>
            <button className="os-btn primary" onClick={() => { setLista((ls) => ls.map((x) => x.id === ver.id ? { ...x, status: "completed" } : x)); avisar(ver.titulo + " concluído.", "ok"); setVer(null); }}>Marcar concluído</button>
          </>}>
          <DrawerSection title="Informações de acompanhamento">
            <Mini rows={[["Início", ver.inicio], ["Fim", ver.fim], ["Categoria", rotulo(CATEGORIAS, ver.cat)], ["Atribuído a", ver.quem.join(", ")], ["Prazo", <Badge kind="sla" value={ver.prazo} />], ["Adicionado por", ver.por + " · " + ver.criado]]} />
          </DrawerSection>
          <DrawerSection title="Descrição">
            <p className="pb-help">{ver.desc}</p>
            {ver.extra && <p className="pb-help" style={{ marginTop: 6 }}><b>Informação adicional:</b> {ver.extra}</p>}
          </DrawerSection>
        </Drawer>}
    </>
  );
}

// ─────────── Página do módulo (crm::layouts.nav) ───────────
const TITULOS = {
  painel: "Painel", leads: "Leads", acompanhamentos: "Acompanhamentos", campanhas: "Campanhas",
  logins: "Login de contatos", comissoes: "Comissões", chamadas: "Registro de chamadas",
  relatorios: "Relatórios", modelo: "Modelo de proposta", propostas: "Propostas",
  marketplace: "Marketplace B2B", pedidos: "Pedido de ordem", taxonomias: "Fontes e estágios", config: "Configurações",
};
const ABAS = ["painel", "leads", "acompanhamentos", "campanhas", "logins", "comissoes", "chamadas", "relatorios", "modelo", "propostas", "marketplace", "pedidos", "taxonomias", "config"];
const ROTA_DE = { painel: "crm-painel", leads: "crm-leads", acompanhamentos: "crm-followups", campanhas: "crm-campanhas", logins: "crm-logins", comissoes: "crm-comissoes", chamadas: "crm-chamadas", relatorios: "crm-relatorios", modelo: "crm-modelo", propostas: "crm-propostas", marketplace: "crm-marketplace", pedidos: "crm-pedidos", taxonomias: "crm-taxonomias", config: "crm-config" };
// Títulos das telas-formulário (ONDA 1) — não são abas, são destinos de dentro de uma aba.
const TITULO_SUB = { ficha: "Exibir lead", campanha: "Campanha", antecipado: "Acompanhamento antecipado", recorrente: "Acompanhamento recorrente" };

const SemPermissao = ({ o }) => {
  const { EmptyState } = DS();
  return EmptyState
    ? <EmptyState variant="no-perm" icon={<Ic name="shield" size={18} />} title="Sem permissão" description={"Seu papel não tem “" + o + "”. Peça ao administrador para liberar em Funções e permissões."} />
    : <p className="pb-help">Sem permissão: {o}</p>;
};

function CrmBladePage({ view = "painel", dense = false, papel = "administrador" }) {
  const M = MP();
  const T = () => window.CrmBladeTelas || {};
  const F = () => window.CrmBladeForms || {};
  const [tela, setTela] = useState(view);
  const [densa, setDensa] = useState(dense);
  const [quemSou, setQuemSou] = useState(() => window.__crmPapel || papel);
  const [sub, setSub] = useState(null);
  const [hora, setHora] = useState(() => new Date().toLocaleTimeString("pt-BR", { hour: "2-digit", minute: "2-digit" }));
  const [avisoNode, avisar] = M.useAviso ? M.useAviso() : [null, () => {}];
  useEffect(() => { setTela(view); setSub(null); }, [view]);
  useEffect(() => { setDensa(dense); }, [dense]);
  // Espera ativa curta pelo PBUI (produto-blade.jsx na fila de carga) — sem isso a tela ficaria
  // no "Carregando" pra sempre, porque nada dispara re-render quando o arquivo chega.
  const [, tick] = useState(0);
  useEffect(() => {
    if ((window.PBUI || {}).Widget) return;
    const t = setInterval(() => { if ((window.PBUI || {}).Widget) { clearInterval(t); tick((n) => n + 1); } }, 200);
    return () => clearInterval(t);
  }, []);

  const perms = PERMS[quemSou] || PERMS.administrador;
  const SelUI = UI().Sel;
  const pode = (p) => !p || perms.nega.indexOf(p) === -1;
  const onIr = (destino) => { setTela(destino); setSub(null); const r = ROTA_DE[destino]; if (r && window.__selectRoute) window.__selectRoute(r); };
  // abrir() = ir pra uma tela-formulário sem trocar de aba (o blade faria um GET próprio).
  const abrir = (tipo, dado) => {
    if (tipo === "novo-acomp") { onIr("acompanhamentos"); avisar("Abra “Adicionar” para o acompanhamento de " + (dado ? dado.nome : "") + ".", "default"); return; }
    setSub({ tipo, dado });
  };
  const fechar = () => setSub(null);

  const props = { avisar, densa, setDensa, Grade, Toolbar, Filtros, Rodape, Mini, Pill, Badge, abrir, perms, pode };
  const X = T(); const Y = F();

  const corpoTela = () => {
    const p = PERM_DE[tela];
    if (!pode(p)) return <SemPermissao o={p} />;
    if (tela === "painel") return <TelaPainel avisar={avisar} />;
    if (tela === "leads") return <TelaLeads {...props} />;
    if (tela === "acompanhamentos") return <TelaAcompanhamentos {...props} />;
    if (tela === "campanhas" && X.Campanhas) return <X.Campanhas {...props} />;
    if (tela === "logins" && X.Logins) return <X.Logins {...props} />;
    if (tela === "comissoes" && X.Comissoes) return <X.Comissoes {...props} />;
    if (tela === "chamadas" && X.Chamadas) return <X.Chamadas {...props} />;
    if (tela === "relatorios" && X.Relatorios) return <X.Relatorios {...props} />;
    if (tela === "modelo" && X.Modelo) return <X.Modelo {...props} onIr={onIr} />;
    if (tela === "propostas" && X.Propostas) return <X.Propostas {...props} onIr={onIr} />;
    if (tela === "marketplace" && X.Marketplace) return <X.Marketplace {...props} />;
    if (tela === "pedidos" && X.Pedidos) return <X.Pedidos {...props} />;
    if (tela === "taxonomias" && X.Taxonomias) return <X.Taxonomias {...props} />;
    if (tela === "config" && X.Config) return <X.Config {...props} />;
    return <p className="pb-help">Tela do módulo não carregada.</p>;
  };

  // As peças de formulário vêm de produto-blade.jsx (window.PBUI). Se a fila de carga ainda não
  // passou por lá, espera em vez de renderizar meio módulo e estourar.
  const corpo = !UI().Widget ? <p className="pb-help">Carregando o módulo CRM…</p> :
    sub && sub.tipo === "ficha" && Y.FichaLead ? <Y.FichaLead lead={sub.dado} avisar={avisar} onVoltar={fechar}
      onConverter={(l) => { avisar(l.nome + " convertido em cliente — abrindo o cadastro.", "ok"); if (window.__selectRoute) window.__selectRoute("clientes"); }}
      onNovoAcompanhamento={(l) => abrir("novo-acomp", l)} /> :
    sub && sub.tipo === "campanha" && Y.CampanhaForm ? <Y.CampanhaForm campanha={sub.dado} avisar={avisar} onVoltar={fechar} /> :
    sub && sub.tipo === "antecipado" && Y.AntecipadoForm ? <Y.AntecipadoForm avisar={avisar} onVoltar={fechar} /> :
    sub && sub.tipo === "recorrente" && Y.RecorrenteForm ? <Y.RecorrenteForm avisar={avisar} onVoltar={fechar} /> :
    corpoTela();

  const abas = ABAS.filter((k) => pode(PERM_DE[k]));

  return (
    <div className={"pb-root cb-root" + (densa ? " pb-dense" : "")} data-screen-label={"CRM · " + (TITULOS[tela] || tela)}>
      {M.Header &&
        <M.Header modulo="CRM" papel={(sub && TITULO_SUB[sub.tipo]) || TITULOS[tela] || tela}
          contexto={["OFFICEIMPRESSO", "matriz", LEADS.length + " leads · " + FOLLOWUPS.length + " acompanhamentos · " + PROPOSTAS.length + " propostas", "papel: " + perms.label]}
          atualizadoAs={hora}
          onRefresh={() => { setHora(new Date().toLocaleTimeString("pt-BR", { hour: "2-digit", minute: "2-digit" })); avisar("CRM reapurado agora.", "ok"); }}
          glyph={<Ic name="clients" />}
          acoes={<>
            <div style={{ minWidth: 190 }}>
              {SelUI && <SelUI value={quemSou} onChange={(p) => { window.__crmPapel = p; setQuemSou(p); }} options={Object.keys(PERMS).map((k) => ({ id: k, name: PERMS[k].label }))} />}
            </div>
            <button className="os-btn" onClick={() => window.__selectRoute && window.__selectRoute("crm-portal")}>Portal do contato</button>
            <button className="os-btn primary" onClick={() => onIr("leads")}><Ic name="plus" size={13} /> Adicionar lead</button>
          </>} />}
      <div className="pb-body">
        <nav className="cli-moduletopnav cb-nav" aria-label="Telas do módulo CRM">
          {abas.map((k) => <button key={k} className={"cli-moduletopnav-tab " + (tela === k ? "active" : "")} onClick={() => onIr(k)}>{TITULOS[k]}</button>)}
        </nav>
        {corpo}
      </div>
      {avisoNode}
    </div>
  );
}

window.CrmBladePage = CrmBladePage;
})();
