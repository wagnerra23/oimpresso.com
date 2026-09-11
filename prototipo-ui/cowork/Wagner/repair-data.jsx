// repair-data.jsx — domínio do módulo Repair (assistência técnica) importado dos blades
// Modules/Repair/Resources/views/* + Entities (JobSheet, RepairStatus, DeviceModel) + lang/pt.
// Campos e vocabulário vêm do legado: job_sheet_no, service_type (carry_in/pick_up/on_site),
// serial_no, defects, product_condition, security_pwd, estimated_cost, delivery_date,
// repair_checklist (pipe), status com cor + ordem + is_completed_status + templates SMS/e-mail.
// Colunas do kanban = KanbanProductionService::COLUMN_ORDER + 'pronto'.
// Expõe window.RepData. Zero JSX aqui — só dados e helpers.
(() => {
const HOJE = new Date(2026, 7, 24); // 24/08/2026

const fmt = (n) => "R$ " + Number(n || 0).toLocaleString("pt-BR", { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const d2 = (iso) => { if (!iso) return "—"; const [a, m, d] = iso.split("-"); return d + "/" + m + "/" + a; };
const iso = (dt) => dt.getFullYear() + "-" + String(dt.getMonth() + 1).padStart(2, "0") + "-" + String(dt.getDate()).padStart(2, "0");
const dias = (a, b) => Math.round((new Date(b) - new Date(a)) / 86400000);

// repair_statuses (2019_03_07 + 2020_07_11): nome, cor, ordem, concluído, templates.
const STATUS = [
  { id: 1, nome: "Recebido no balcão", cor: "oklch(0.62 0.02 250)", ordem: 1, concluido: false, coluna: "recepcao",
    sms: "Olá :cliente, recebemos seu equipamento na OS :os. Avisamos a cada etapa.", assunto: "Recebemos seu equipamento — OS :os" },
  { id: 2, nome: "Em diagnóstico", cor: "oklch(0.58 0.13 250)", ordem: 2, concluido: false, coluna: "diagnostico",
    sms: "OS :os em diagnóstico. Assim que houver laudo, mandamos o orçamento.", assunto: "Seu equipamento está em diagnóstico — OS :os" },
  { id: 3, nome: "Orçamento enviado", cor: "oklch(0.68 0.14 75)", ordem: 3, concluido: false, coluna: "diagnostico",
    sms: "OS :os com orçamento de :valor. Responda aprovando pra liberar o serviço.", assunto: "Orçamento do reparo — OS :os" },
  { id: 4, nome: "Aguardando peça", cor: "oklch(0.6 0.18 25)", ordem: 4, concluido: false, coluna: "aguardando-pecas",
    sms: "OS :os aguardando peça. Avisamos quando chegar.", assunto: "Aguardando peça — OS :os" },
  { id: 5, nome: "Em reparo", cor: "oklch(0.55 0.15 295)", ordem: 5, concluido: false, coluna: "em-execucao",
    sms: "OS :os em reparo pelo técnico :tecnico.", assunto: "Reparo em execução — OS :os" },
  { id: 6, nome: "Em teste final", cor: "oklch(0.6 0.12 200)", ordem: 6, concluido: false, coluna: "em-execucao",
    sms: "OS :os em teste final antes da entrega.", assunto: "Teste final — OS :os" },
  { id: 7, nome: "Pronto p/ retirada", cor: "oklch(0.6 0.13 150)", ordem: 7, concluido: true, coluna: "pronto",
    sms: "OS :os pronta pra retirada. Estamos das 8h às 18h.", assunto: "Seu equipamento está pronto — OS :os" },
  { id: 8, nome: "Entregue", cor: "oklch(0.55 0.1 150)", ordem: 8, concluido: true, coluna: "pronto",
    sms: "OS :os entregue. Garantia de 90 dias no serviço.", assunto: "Equipamento entregue — OS :os" },
  { id: 9, nome: "Sem reparo (devolvido)", cor: "oklch(0.55 0.02 250)", ordem: 9, concluido: true, coluna: "pronto",
    sms: "OS :os devolvida sem reparo. O laudo segue no seu e-mail.", assunto: "Devolução sem reparo — OS :os" },
];
const statusDe = (id) => STATUS.find((s) => s.id === id) || STATUS[0];

const COLUNAS = [
  { id: "recepcao",         label: "Recepção",         board: "backlog" },
  { id: "diagnostico",      label: "Diagnóstico",      board: "todo" },
  { id: "aguardando-pecas", label: "Aguardando peças",  board: "blocked" },
  { id: "em-execucao",      label: "Em execução",      board: "doing" },
  { id: "pronto",           label: "Pronto",           board: "done" },
];

// FSM canônica do Repair (repair_job_sheets.current_stage_id, migration 2026_05_12).
const FASES = ["Recepção", "Diagnóstico", "Aguardando peça", "Em reparo", "Pronto p/ retirada"];
const faseDe = (statusId) => Math.max(0, COLUNAS.findIndex((c) => c.id === statusDe(statusId).coluna));

// device_models (2020_05_05) — checklist de pré-reparo separado por barra vertical no legado.
const DISPOSITIVOS = ["Plotter de recorte", "Impressora látex", "Impressora DTF", "Notebook", "Prensa térmica", "Roteadora CNC"];
const MARCAS = ["Roland", "Epson", "Mimaki", "HP", "Brother", "Dell"];
const MODELOS = [
  { id: 1, nome: "GR2-540", marca: "Roland", dispositivo: "Plotter de recorte", checklist: "Cabeçote de corte|Lâmina e suporte|Sensor de crop mark|Tração de material|Firmware" },
  { id: 2, nome: "SureColor S60600", marca: "Epson", dispositivo: "Impressora látex", checklist: "Cabeça de impressão|Sistema de tinta|Encoder|Rolo de pressão|Teste de purga" },
  { id: 3, nome: "Latex 315", marca: "HP", dispositivo: "Impressora látex", checklist: "Cabeças|Aquecedores|Vácuo da mesa|Sensor de avanço" },
  { id: 4, nome: "CJV150-75", marca: "Mimaki", dispositivo: "Impressora DTF", checklist: "Cabeça|Dampers|Capping station|Corte integrado" },
  { id: 5, nome: "GTX-600", marca: "Brother", dispositivo: "Impressora DTF", checklist: "Cabeças CMYK|Branco|Circulação de tinta|Plataforma" },
  { id: 6, nome: "Latitude 5440", marca: "Dell", dispositivo: "Notebook", checklist: "Bateria|Teclado|Tela|Portas USB|Cooler" },
];
const modeloDe = (id) => MODELOS.find((m) => m.id === id) || MODELOS[0];

const LOCAIS = ["Matriz — Centro", "Filial — Zona Norte"];
const TECNICOS = ["Anderson Prado", "Rafael Uchoa", "Bruna Sales", "Não atribuído"];
const SERVICO = { carry_in: "Balcão", pick_up: "Coleta", on_site: "No local" };

// repair_job_sheets — 14 folhas cobrindo as 5 colunas, pendentes e concluídas.
const FOLHAS = [
  { id: 1,  os: "JS-2026-0418", cliente: "Comunicação Rota Livre", tipoPf: "pj", servico: "carry_in", status: 1, tecnico: "Não atribuído", local: 0, modelo: 1, serie: "RL-GR2-88123", custo: 0,      entrega: "2026-08-27", criado: "2026-08-24", prior: "p2", defeitos: ["Não reconhece crop mark", "Corte fora de registro"], configuracao: "Rolo 1,37 m · lâmina 45°", condicao: "Arranhado na tampa", senha: "—", checklist: [], pecas: [], notificar: true, endereco: "" },
  { id: 2,  os: "JS-2026-0417", cliente: "Gráfica Martinho ME", tipoPf: "pj", servico: "pick_up", status: 1, tecnico: "Não atribuído", local: 1, modelo: 6, serie: "DL-5440-A991", custo: 240,   entrega: "2026-08-26", criado: "2026-08-24", prior: "p3", defeitos: ["Não liga"], configuracao: "i5 · 16 GB · SSD 512", condicao: "Sem avarias visíveis", senha: "padrão 1-4-7-8-9", checklist: [], pecas: [], notificar: true, endereco: "Av. Beira Rio, 1220 — galpão 3" },
  { id: 3,  os: "JS-2026-0412", cliente: "Studio Vento Sul", tipoPf: "pj", servico: "carry_in", status: 2, tecnico: "Anderson Prado", local: 0, modelo: 2, serie: "EP-S606-77401", custo: 1450,  entrega: "2026-08-25", criado: "2026-08-21", prior: "p1", defeitos: ["Falha de tinta magenta", "Linhas horizontais na impressão"], configuracao: "8 canais · tinta S60", condicao: "Uso pesado, painel amassado", senha: "—", checklist: ["Cabeça de impressão", "Sistema de tinta"], pecas: [], notificar: true, endereco: "" },
  { id: 4,  os: "JS-2026-0410", cliente: "Eduardo Pessoa", tipoPf: "pf", servico: "carry_in", status: 2, tecnico: "Bruna Sales", local: 0, modelo: 6, serie: "DL-5440-B120", custo: 380,   entrega: "2026-08-25", criado: "2026-08-20", prior: "p2", defeitos: ["Superaquecimento", "Desliga sozinho"], configuracao: "i7 · 32 GB", condicao: "Base com folga", senha: "senha 4471", checklist: ["Cooler"], pecas: [], notificar: false, endereco: "" },
  { id: 5,  os: "JS-2026-0405", cliente: "Comunicação Rota Livre", tipoPf: "pj", servico: "on_site", status: 3, tecnico: "Rafael Uchoa", local: 0, modelo: 3, serie: "HP-315-55210", custo: 3890,  entrega: "2026-08-24", criado: "2026-08-18", prior: "p0", defeitos: ["Aquecedor traseiro sem potência"], configuracao: "Látex 315 · 1,37 m", condicao: "Equipamento em produção", senha: "—", checklist: ["Aquecedores", "Sensor de avanço"], pecas: [], notificar: true, endereco: "R. das Palmeiras, 88 — sala 2" },
  { id: 6,  os: "JS-2026-0402", cliente: "Sinalize Já Ltda", tipoPf: "pj", servico: "carry_in", status: 3, tecnico: "Anderson Prado", local: 1, modelo: 4, serie: "MK-CJV-31007", custo: 2240,  entrega: "2026-08-28", criado: "2026-08-17", prior: "p2", defeitos: ["Corte integrado desalinhado"], configuracao: "CJV150 · 75 cm", condicao: "Sem avarias", senha: "—", checklist: ["Corte integrado"], pecas: [], notificar: true, endereco: "" },
  { id: 7,  os: "JS-2026-0398", cliente: "Ateliê Casa Forte", tipoPf: "pj", servico: "carry_in", status: 4, tecnico: "Bruna Sales", local: 0, modelo: 5, serie: "BR-GTX-90881", custo: 4120,  entrega: "2026-08-23", criado: "2026-08-14", prior: "p0", defeitos: ["Canal branco entupido"], configuracao: "GTX-600 · branco + CMYK", condicao: "Bandeja trincada", senha: "—", checklist: ["Branco", "Circulação de tinta"], pecas: [{ nome: "Damper branco GTX", qtd: 2, valor: 340, situacao: "encomendado" }], notificar: true, endereco: "" },
  { id: 8,  os: "JS-2026-0394", cliente: "Gráfica Martinho ME", tipoPf: "pj", servico: "carry_in", status: 4, tecnico: "Rafael Uchoa", local: 1, modelo: 1, serie: "RL-GR2-77320", custo: 890,   entrega: "2026-08-26", criado: "2026-08-13", prior: "p2", defeitos: ["Motor de tração travando"], configuracao: "GR2-540", condicao: "Sem avarias", senha: "—", checklist: ["Tração de material"], pecas: [{ nome: "Correia de tração GR2", qtd: 1, valor: 210, situacao: "aprovacao" }], notificar: true, endereco: "" },
  { id: 9,  os: "JS-2026-0390", cliente: "Studio Vento Sul", tipoPf: "pj", servico: "carry_in", status: 5, tecnico: "Anderson Prado", local: 0, modelo: 2, serie: "EP-S606-11902", custo: 1980,  entrega: "2026-08-24", criado: "2026-08-11", prior: "p1", defeitos: ["Encoder sujo", "Erro de posicionamento"], configuracao: "8 canais", condicao: "Sem avarias", senha: "—", checklist: ["Encoder", "Teste de purga"], pecas: [{ nome: "Fita encoder 60\"", qtd: 1, valor: 620, situacao: "ok" }], notificar: true, endereco: "" },
  { id: 10, os: "JS-2026-0386", cliente: "Sinalize Já Ltda", tipoPf: "pj", servico: "carry_in", status: 5, tecnico: "Bruna Sales", local: 1, modelo: 6, serie: "DL-5440-C707", custo: 620,   entrega: "2026-08-25", criado: "2026-08-10", prior: "p3", defeitos: ["Teclado com teclas mortas"], configuracao: "i5 · 16 GB", condicao: "Desgaste normal", senha: "senha 2299", checklist: ["Teclado"], pecas: [{ nome: "Teclado ABNT2 5440", qtd: 1, valor: 290, situacao: "ok" }], notificar: false, endereco: "" },
  { id: 11, os: "JS-2026-0380", cliente: "Ateliê Casa Forte", tipoPf: "pj", servico: "carry_in", status: 6, tecnico: "Rafael Uchoa", local: 0, modelo: 4, serie: "MK-CJV-44112", custo: 1360,  entrega: "2026-08-24", criado: "2026-08-07", prior: "p1", defeitos: ["Capping station ressecada"], configuracao: "CJV150 · 75 cm", condicao: "Sem avarias", senha: "—", checklist: ["Capping station", "Dampers"], pecas: [{ nome: "Kit capping CJV", qtd: 1, valor: 480, situacao: "ok" }], notificar: true, endereco: "" },
  { id: 12, os: "JS-2026-0371", cliente: "Comunicação Rota Livre", tipoPf: "pj", servico: "carry_in", status: 7, tecnico: "Anderson Prado", local: 0, modelo: 1, serie: "RL-GR2-66009", custo: 740,   entrega: "2026-08-22", criado: "2026-08-03", prior: "p2", defeitos: ["Lâmina não desce"], configuracao: "GR2-540", condicao: "Sem avarias", senha: "—", checklist: ["Cabeçote de corte", "Lâmina e suporte"], pecas: [{ nome: "Suporte de lâmina", qtd: 1, valor: 180, situacao: "ok" }], notificar: true, endereco: "" },
  { id: 13, os: "JS-2026-0364", cliente: "Eduardo Pessoa", tipoPf: "pf", servico: "carry_in", status: 8, tecnico: "Bruna Sales", local: 0, modelo: 6, serie: "DL-5440-D318", custo: 430,   entrega: "2026-08-18", criado: "2026-07-30", prior: "p3", defeitos: ["Bateria não carrega"], configuracao: "i7 · 16 GB", condicao: "Sem avarias", senha: "senha 8080", checklist: ["Bateria"], pecas: [{ nome: "Bateria 54Wh", qtd: 1, valor: 310, situacao: "ok" }], notificar: true, endereco: "" },
  { id: 14, os: "JS-2026-0357", cliente: "Sinalize Já Ltda", tipoPf: "pj", servico: "carry_in", status: 9, tecnico: "Rafael Uchoa", local: 1, modelo: 3, serie: "HP-315-22874", custo: 0,      entrega: "2026-08-14", criado: "2026-07-27", prior: "p2", defeitos: ["Placa principal em curto"], configuracao: "Látex 315", condicao: "Oxidação por infiltração", senha: "—", checklist: ["Aquecedores"], pecas: [], notificar: true, endereco: "" },
];

// transactions do Repair (2019_03_08 + 2020_08_07): a venda derivada da folha.
const REPAROS = [
  { id: 1, repair: "REP-2026-0301", fatura: "FAT-9921", folha: 12, garantia: "Serviço 90 dias", pagamento: "paid",    total: 740,  saldo: 0,   emitido: "2026-08-22" },
  { id: 2, repair: "REP-2026-0298", fatura: "FAT-9903", folha: 13, garantia: "Peça 12 meses",  pagamento: "paid",    total: 430,  saldo: 0,   emitido: "2026-08-18" },
  { id: 3, repair: "REP-2026-0294", fatura: "FAT-9880", folha: 11, garantia: "Serviço 90 dias", pagamento: "partial", total: 1360, saldo: 660, emitido: "2026-08-16" },
  { id: 4, repair: "REP-2026-0289", fatura: "FAT-9861", folha: 9,  garantia: "Serviço 90 dias", pagamento: "pending", total: 1980, saldo: 1980, emitido: "2026-08-14" },
  { id: 5, repair: "REP-2026-0277", fatura: "FAT-9814", folha: 14, garantia: "Sem garantia",    pagamento: "due",     total: 180,  saldo: 180, emitido: "2026-08-05" },
];

// repair activities (partials/activities.blade.php) — trilha por folha.
const ATIVIDADES = {
  5:  [{ dia: "2026-08-18", hora: "09:12", quem: "Larissa Nunes", ev: "Folha criada no balcão", nota: "Coleta no local agendada." },
       { dia: "2026-08-19", hora: "14:40", quem: "Rafael Uchoa", ev: "Status alterado para Em diagnóstico", nota: "Aquecedor traseiro sem potência confirmado." },
       { dia: "2026-08-21", hora: "10:05", quem: "Rafael Uchoa", ev: "Status alterado para Orçamento enviado", nota: "Resistência + módulo de potência." }],
  7:  [{ dia: "2026-08-14", hora: "08:33", quem: "Larissa Nunes", ev: "Folha criada no balcão", nota: "Cliente relatou branco falhando." },
       { dia: "2026-08-15", hora: "16:20", quem: "Bruna Sales", ev: "Peça encomendada", nota: "2× damper branco — prazo 5 dias." },
       { dia: "2026-08-19", hora: "09:48", quem: "Bruna Sales", ev: "Status alterado para Aguardando peça", nota: "SMS enviado ao cliente." }],
  9:  [{ dia: "2026-08-11", hora: "11:02", quem: "Larissa Nunes", ev: "Folha criada no balcão", nota: "" },
       { dia: "2026-08-13", hora: "15:15", quem: "Anderson Prado", ev: "Orçamento aprovado pelo cliente", nota: "Fita encoder aprovada por WhatsApp." },
       { dia: "2026-08-20", hora: "08:50", quem: "Anderson Prado", ev: "Status alterado para Em reparo", nota: "Encoder trocado, em calibração." }],
};

const CONFIG = {
  prefixo: "JS-", statusPadrao: 1, produtoPadrao: "Serviço de assistência técnica (MO)",
  mostrar: { statusReparo: true, garantia: true, serie: true, defeitos: true, modelo: true, dispositivo: true, marca: true, checklist: true },
  rotulos: { statusReparo: "Status do reparo", garantia: "Garantia", serie: "Número de série", defeitos: "Defeito relatado", modelo: "Modelo", dispositivo: "Equipamento", marca: "Marca", checklist: "Checklist de pré-reparo" },
  camposCustom: ["Nº do contrato de manutenção", "Nota de coleta", "", "", ""],
  notificar: { email: true, sms: false },
};

// ── Derivadas ──
const pendentes = (fs) => fs.filter((f) => !statusDe(f.status).concluido);
const concluidas = (fs) => fs.filter((f) => statusDe(f.status).concluido);
const atrasada = (f) => !statusDe(f.status).concluido && dias(iso(HOJE), f.entrega) < 0;
const vencendoHoje = (f) => !statusDe(f.status).concluido && f.entrega === iso(HOJE);
const porColuna = (fs, col) => fs.filter((f) => statusDe(f.status).coluna === col);
const contagemStatus = (fs) => STATUS.map((s) => ({ status: s, n: fs.filter((f) => f.status === s.id).length }));
const tendencia = (fs, campo) => {
  const c = {};
  fs.forEach((f) => { const k = campo === "marca" ? modeloDe(f.modelo).marca : campo === "dispositivo" ? modeloDe(f.modelo).dispositivo : modeloDe(f.modelo).nome; c[k] = (c[k] || 0) + 1; });
  return Object.entries(c).map(([label, value]) => ({ label, value })).sort((a, b) => b.value - a.value);
};
const porTecnico = (fs) => {
  const c = {};
  pendentes(fs).forEach((f) => { c[f.tecnico] = (c[f.tecnico] || 0) + 1; });
  return Object.entries(c).map(([tecnico, n]) => ({ tecnico, n })).sort((a, b) => b.n - a.n);
};
const ticketMedio = (fs) => { const l = fs.filter((f) => f.custo > 0); return l.length ? l.reduce((s, f) => s + f.custo, 0) / l.length : 0; };

// ── Permissões reais do legado (Modules/Repair/Http/Controllers/* + migration 2019_03_14) ──
// Nenhum nome inventado: é o que o controller checa antes de responder.
const PERMISSOES = {
  "repair_module":            "assinatura do módulo (hasThePermissionInSubscription)",
  "job_sheet.view_all":       "ver todas as folhas de OS",
  "job_sheet.view_assigned":  "ver só as folhas atribuídas a mim",
  "job_sheet.create":         "abrir folha de OS",
  "job_sheet.edit":           "editar folha e alterar status da folha",
  "job_sheet.delete":         "excluir folha de OS",
  "repair.create":            "abrir reparo (venda derivada) e salvar configurações",
  "repair.view":              "ver todos os reparos faturados",
  "repair.view_own":          "ver só os reparos que eu lancei",
  "repair.update":            "editar reparo",
  "repair.delete":            "excluir reparo",
  "repair_status.update":     "mudar o status do reparo",
  "repair_status.access":     "criar/editar/excluir status",
  "send_notification":        "disparar SMS/e-mail ao cliente",
};

const PAPEIS = {
  administrador: { label: "administrador", quem: "Wagner Rocha", perms: Object.keys(PERMISSOES) },
  balcao:        { label: "balcão", quem: "Larissa Nunes",
    perms: ["repair_module", "job_sheet.view_all", "job_sheet.create", "job_sheet.edit", "repair.create", "repair.view", "repair_status.update", "send_notification"] },
  tecnico:       { label: "técnico", quem: "Anderson Prado",
    perms: ["repair_module", "job_sheet.view_assigned", "job_sheet.edit", "repair.view_own", "repair_status.update"] },
};
const can = (papel, perm) => (PAPEIS[papel] || PAPEIS.administrador).perms.includes(perm);
// job_sheet.view_assigned sem view_all = o técnico só vê o que é dele (JobSheetController:118).
const visiveis = (fs, papel) => can(papel, "job_sheet.view_all") ? fs : fs.filter((f) => f.tecnico === PAPEIS[papel].quem);

// Listas do settings do legado (product_configuration / problem_reported_by_customer /
// condition_of_product são strings separadas por vírgula na configuração do negócio).
const SUGESTOES = {
  defeitos: ["Não liga", "Falha de tinta", "Linhas na impressão", "Corte desalinhado", "Superaquecimento", "Ruído anormal", "Erro de firmware"],
  condicoes: ["Sem avarias visíveis", "Arranhado", "Painel amassado", "Oxidação", "Desgaste normal", "Uso pesado"],
  configuracoes: ["Rolo 1,37 m", "Rolo 1,60 m", "8 canais", "Branco + CMYK", "i5 · 16 GB", "i7 · 32 GB"],
};
const CLIENTES = ["Comunicação Rota Livre", "Gráfica Martinho ME", "Studio Vento Sul", "Sinalize Já Ltda", "Ateliê Casa Forte", "Eduardo Pessoa"];

// Peças buscáveis (job_sheet/add_parts.blade.php busca no estoque do local).
const PECAS_ESTOQUE = [
  { sku: "PC-1188", nome: "Damper branco GTX", valor: 340, saldo: 6 },
  { sku: "PC-2042", nome: "Correia de tração GR2", valor: 210, saldo: 3 },
  { sku: "PC-3310", nome: "Fita encoder 60\"", valor: 620, saldo: 2 },
  { sku: "PC-3411", nome: "Kit capping CJV", valor: 480, saldo: 4 },
  { sku: "PC-5502", nome: "Teclado ABNT2 5440", valor: 290, saldo: 5 },
  { sku: "PC-5610", nome: "Bateria 54Wh", valor: 310, saldo: 0 },
  { sku: "PC-7701", nome: "Resistência aquecedor 315", valor: 1180, saldo: 1 },
  { sku: "MO-0001", nome: "Mão de obra técnica (hora)", valor: 160, saldo: null },
];

// job_sheet/upload_doc.blade.php — documentos anexados por folha.
const DOCS = {
  3: [{ nome: "laudo-magenta.pdf", tam: "412 KB", em: "2026-08-22" }, { nome: "foto-cabecote.jpg", tam: "1,8 MB", em: "2026-08-21" }],
  5: [{ nome: "orcamento-aquecedor.pdf", tam: "196 KB", em: "2026-08-21" }],
  7: [{ nome: "nf-damper.pdf", tam: "88 KB", em: "2026-08-15" }],
};

const proximoNumero = (fs) => {
  const n = fs.reduce((m, f) => Math.max(m, parseInt(String(f.os).split("-").pop(), 10) || 0), 0) + 1;
  return CONFIG.prefixo + "2026-" + String(n).padStart(4, "0");
};

window.RepData = {
  HOJE, iso, fmt, d2, dias, STATUS, statusDe, COLUNAS, FASES, faseDe, DISPOSITIVOS, MARCAS, MODELOS, modeloDe,
  LOCAIS, TECNICOS, SERVICO, FOLHAS, REPAROS, ATIVIDADES, CONFIG, PAPEIS, PERMISSOES, can, visiveis,
  SUGESTOES, CLIENTES, PECAS_ESTOQUE, DOCS, proximoNumero,
  pendentes, concluidas, atrasada, vencendoHoje, porColuna, contagemStatus, tendencia, porTecnico, ticketMedio,
};
})();
