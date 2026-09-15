// hrm-data.jsx — dados do módulo HRM (Essentials/hrm do main @b719732f3188).
// Modelo lido no main NESTE turno: Routes/web.php (prefixo /hrm), nav_hrm.blade,
// EssentialsLeave(Type)Controller, AttendanceController, ShiftController,
// PayrollController, EssentialsHolidayController, SalesTargetController,
// EssentialsSettingsController (já Inertia), hrm_dashboard.blade.
// Nomes dos colaboradores seguem window.EMPLOYEES (data-people.jsx).
// Expõe window.HRM.
(() => {
const brl = (n) => "R$ " + n.toLocaleString("pt-BR", { minimumFractionDigits: 2, maximumFractionDigits: 2 });

// ── Colaboradores (users com allow_login=1 + essentials_salary/pay_period) ──
const EMP = [
  { id:"e-1", nome:"Larissa Andrade",  cargo:"Atendente comercial",   setor:"Comercial",  local:"Matriz",  salario:2650,  periodo:"month", turno:"Turno A", admissao:"15/08/2024", comissao:1.5 },
  { id:"e-2", nome:"Wagner Oliveira",  cargo:"Gestor",                setor:"Diretoria",  local:"Matriz",  salario:12000, periodo:"month", turno:"Flexível", admissao:"10/01/2015", comissao:0 },
  { id:"e-3", nome:"Eliana Pereira",   cargo:"Financeiro",            setor:"Adm/Fin",    local:"Matriz",  salario:3900,  periodo:"month", turno:"Turno A", admissao:"02/03/2020", comissao:0 },
  { id:"e-4", nome:"Tiago Mendes",     cargo:"Operador de impressão", setor:"Produção",   local:"Matriz",  salario:3200,  periodo:"month", turno:"Turno B", admissao:"20/06/2022", comissao:0 },
  { id:"e-5", nome:"Camila Bezerra",   cargo:"Designer",              setor:"Criação",    local:"Matriz",  salario:4100,  periodo:"month", turno:"Flexível", admissao:"04/11/2023", comissao:0 },
  { id:"e-6", nome:"Rafael Costa",     cargo:"Acabamento",            setor:"Produção",   local:"Filial Norte", salario:2480, periodo:"month", turno:"Turno B", admissao:"12/02/2024", comissao:0 },
  { id:"e-7", nome:"Júlia Ferreira",   cargo:"Estagiária comercial",  setor:"Comercial",  local:"Matriz",  salario:1200,  periodo:"month", turno:"Turno A", admissao:"01/09/2025", comissao:1 },
];

// ── Tipos de licença (essentials_leave_types: leave_type · max_leave_count · leave_count_interval) ──
const TIPOS = [
  { id:1, nome:"Férias",                 max:30, intervalo:"year",  usadas:22 },
  { id:2, nome:"Licença médica",          max:15, intervalo:"month", usadas:6 },
  { id:3, nome:"Falta justificada",       max:6,  intervalo:"year",  usadas:4 },
  { id:4, nome:"Licença não remunerada",  max:0,  intervalo:"year",  usadas:2 },
  { id:5, nome:"Licença-paternidade",     max:5,  intervalo:"year",  usadas:5 },
];

// ── Licenças (essentials_leaves) — status: pending · approved · cancelled (LeaveRequestService) ──
const LIC = [
  { id:1, ref:"LIC0009", tipo:1, emp:"e-4", ini:"2026-09-07", fim:"2026-09-21", status:"pending",   motivo:"Férias programadas — 15 dias.", nota:"" },
  { id:2, ref:"LIC0008", tipo:2, emp:"e-6", ini:"2026-08-19", fim:"2026-08-21", status:"approved",  motivo:"Atestado de 3 dias (clínico).", nota:"Atestado recebido no RH." },
  { id:3, ref:"LIC0007", tipo:3, emp:"e-7", ini:"2026-08-20", fim:"2026-08-20", status:"pending",   motivo:"Prova na faculdade à tarde.", nota:"" },
  { id:4, ref:"LIC0006", tipo:1, emp:"e-3", ini:"2026-08-24", fim:"2026-09-02", status:"approved",  motivo:"Férias — 10 dias.", nota:"Aprovado; escala coberta pela Larissa." },
  { id:5, ref:"LIC0005", tipo:4, emp:"e-5", ini:"2026-07-14", fim:"2026-07-18", status:"cancelled", motivo:"Viagem pessoal.", nota:"Cancelado pela colaboradora." },
  { id:6, ref:"LIC0004", tipo:2, emp:"e-1", ini:"2026-07-02", fim:"2026-07-02", status:"approved",  motivo:"Consulta médica.", nota:"" },
  { id:7, ref:"LIC0003", tipo:5, emp:"e-4", ini:"2026-05-11", fim:"2026-05-15", status:"approved",  motivo:"Nascimento do filho.", nota:"5 dias — CLT art. 473, X." },
  { id:8, ref:"LIC0002", tipo:3, emp:"e-6", ini:"2026-04-28", fim:"2026-04-28", status:"cancelled", motivo:"Assunto pessoal.", nota:"Sem cobertura no turno B." },
  { id:9, ref:"LIC0001", tipo:1, emp:"e-1", ini:"2026-01-06", fim:"2026-01-17", status:"approved",  motivo:"Férias de início de ano.", nota:"" },
];

// ── Feriados (essentials_holidays: name · start_date · end_date · location_id · note) ──
const FER = [
  { id:1, nome:"Independência",           ini:"2026-09-07", fim:"2026-09-07", local:null,            nota:"Feriado nacional." },
  { id:2, nome:"Nossa Senhora Aparecida", ini:"2026-10-12", fim:"2026-10-12", local:null,            nota:"" },
  { id:3, nome:"Finados",                 ini:"2026-11-02", fim:"2026-11-02", local:null,            nota:"" },
  { id:4, nome:"Aniversário da cidade",   ini:"2026-11-20", fim:"2026-11-20", local:"Filial Norte",  nota:"Só a filial para; matriz opera." },
  { id:5, nome:"Recesso de fim de ano",   ini:"2026-12-24", fim:"2027-01-02", local:null,            nota:"Produção para; plantão de atendimento." },
  { id:6, nome:"Corpus Christi",          ini:"2026-06-04", fim:"2026-06-05", local:null,            nota:"Emenda de sexta aprovada." },
];

// ── Turnos (essentials_shifts: type fixed_shift|flexible_shift · holidays[] · auto clock-out) ──
const TUR = [
  { id:1, nome:"Turno A", tipo:"fixed_shift",    ini:"08:00", fim:"17:00", folgas:["saturday","sunday"], autoOut:true,  autoOutAs:"17:30", pessoas:3 },
  { id:2, nome:"Turno B", tipo:"fixed_shift",    ini:"13:00", fim:"22:00", folgas:["sunday"],            autoOut:true,  autoOutAs:"22:30", pessoas:2 },
  { id:3, nome:"Flexível", tipo:"flexible_shift", ini:null,   fim:null,    folgas:["sunday"],            autoOut:false, autoOutAs:null,    pessoas:2 },
];

// ── Presença web (essentials_attendances: clock_in/out_time · note · ip · location · shift) ──
const PRE = [
  { id:1, emp:"e-1", data:"2026-08-21", ent:"07:58", sai:null,    turno:"Turno A", ip:"189.4.22.10",  local:"Matriz — portaria", nEnt:"", nSai:"" },
  { id:2, emp:"e-3", data:"2026-08-21", ent:"08:12", sai:null,    turno:"Turno A", ip:"189.4.22.14",  local:"Matriz — sala fin.", nEnt:"Trânsito na Marginal.", nSai:"" },
  { id:3, emp:"e-7", data:"2026-08-21", ent:"08:05", sai:"12:00", turno:"Turno A", ip:"189.4.22.31",  local:"Matriz", nEnt:"", nSai:"Saída para prova (licença pendente)." },
  { id:4, emp:"e-4", data:"2026-08-21", ent:"13:02", sai:null,    turno:"Turno B", ip:"189.4.22.44",  local:"Matriz — impressão", nEnt:"", nSai:"" },
  { id:5, emp:"e-1", data:"2026-08-20", ent:"08:01", sai:"17:04", turno:"Turno A", ip:"189.4.22.10",  local:"Matriz", nEnt:"", nSai:"" },
  { id:6, emp:"e-4", data:"2026-08-20", ent:"13:00", sai:"22:11", turno:"Turno B", ip:"189.4.22.44",  local:"Matriz", nEnt:"", nSai:"Fechou tiragem do painel." },
  { id:7, emp:"e-6", data:"2026-08-20", ent:"13:20", sai:"22:00", turno:"Turno B", ip:"201.9.14.7",   local:"Filial Norte", nEnt:"Atraso — ônibus.", nSai:"" },
  { id:8, emp:"e-5", data:"2026-08-20", ent:"10:15", sai:"19:40", turno:"Flexível", ip:"189.4.22.55", local:"", nEnt:"", nSai:"" },
  { id:9, emp:"e-3", data:"2026-08-19", ent:"08:00", sai:"17:00", turno:"Turno A", ip:"189.4.22.14",  local:"Matriz", nEnt:"", nSai:"" },
  { id:10, emp:"e-1", data:"2026-08-19", ent:"08:03", sai:null,   turno:"Turno A", ip:"189.4.22.10",  local:"Matriz", nEnt:"", nSai:"" },
];

// ── Ganhos e deduções recorrentes (essentials_allowance_and_deductions) ──
const GD = [
  { id:1, desc:"Vale-transporte",        tipo:"deduction", forma:"percent", valor:6,    aplicaEm:"Todos os CLT" },
  { id:2, desc:"Adiantamento salarial",  tipo:"deduction", forma:"fixed",   valor:500,  aplicaEm:"Tiago Mendes" },
  { id:3, desc:"Insalubridade",          tipo:"allowance", forma:"percent", valor:20,   aplicaEm:"Produção" },
  { id:4, desc:"Vale-refeição",          tipo:"allowance", forma:"fixed",   valor:660,  aplicaEm:"Todos" },
  { id:5, desc:"Prêmio de produção",     tipo:"allowance", forma:"fixed",   valor:400,  aplicaEm:"Turno B" },
];

// ── Folha (transactions type=payroll + essentials_payroll_groups) ──
const LOTES = [
  { id:1, nome:"Folha 07/2026 — Matriz", mes:"07/2026", local:"Matriz", status:"final", pagamento:"paid",    bruto:29310, criadoPor:"Eliana Pereira", criadoEm:"31/07/2026", itens:6 },
  { id:2, nome:"Folha 07/2026 — Filial", mes:"07/2026", local:"Filial Norte", status:"final", pagamento:"paid", bruto:2880, criadoPor:"Eliana Pereira", criadoEm:"31/07/2026", itens:1 },
  { id:3, nome:"Folha 08/2026 — Matriz", mes:"08/2026", local:"Matriz", status:"draft", pagamento:"due",     bruto:29840, criadoPor:"Eliana Pereira", criadoEm:"20/08/2026", itens:6 },
];

const FOLHA = [
  { id:1, ref:"FP0012", emp:"e-1", mes:"08/2026", lote:3, base:2650,  ganhos:[["Vale-refeição",660],["Comissão de venda",372.4],["Comissão de meta",180]], deducoes:[["Vale-transporte",159]], pagamento:"due",  horas:168, faltas:0 },
  { id:2, ref:"FP0011", emp:"e-3", mes:"08/2026", lote:3, base:3900,  ganhos:[["Vale-refeição",660]], deducoes:[["Vale-transporte",234]], pagamento:"due",  horas:160, faltas:6 },
  { id:3, ref:"FP0010", emp:"e-4", mes:"08/2026", lote:3, base:3200,  ganhos:[["Vale-refeição",660],["Insalubridade",640],["Prêmio de produção",400]], deducoes:[["Vale-transporte",192],["Adiantamento salarial",500]], pagamento:"due", horas:176, faltas:0 },
  { id:4, ref:"FP0009", emp:"e-5", mes:"08/2026", lote:3, base:4100,  ganhos:[["Vale-refeição",660]], deducoes:[], pagamento:"due",  horas:150, faltas:0 },
  { id:5, ref:"FP0008", emp:"e-7", mes:"08/2026", lote:3, base:1200,  ganhos:[["Vale-refeição",660],["Comissão de venda",96.2]], deducoes:[["Vale-transporte",72]], pagamento:"due", horas:120, faltas:1 },
  { id:6, ref:"FP0007", emp:"e-2", mes:"08/2026", lote:3, base:12000, ganhos:[], deducoes:[], pagamento:"due", horas:0, faltas:0 },
  { id:7, ref:"FP0006", emp:"e-6", mes:"07/2026", lote:2, base:2480,  ganhos:[["Vale-refeição",660],["Insalubridade",496],["Prêmio de produção",400]], deducoes:[["Vale-transporte",148.8]], pagamento:"paid", horas:172, faltas:0 },
  { id:8, ref:"FP0005", emp:"e-1", mes:"07/2026", lote:1, base:2650,  ganhos:[["Vale-refeição",660],["Comissão de venda",298]], deducoes:[["Vale-transporte",159]], pagamento:"paid", horas:170, faltas:1 },
  { id:9, ref:"FP0004", emp:"e-4", mes:"07/2026", lote:1, base:3200,  ganhos:[["Vale-refeição",660],["Insalubridade",640]], deducoes:[["Vale-transporte",192]], pagamento:"partial", horas:174, faltas:0 },
];

// ── Metas de venda (essentials_user_sales_targets: faixa + % comissão) ──
const METAS = {
  "e-1": [ { id:1, ini:0, fim:20000, pct:1 }, { id:2, ini:20000.01, fim:40000, pct:1.5 }, { id:3, ini:40000.01, fim:80000, pct:2.5 } ],
  "e-7": [ { id:4, ini:0, fim:10000, pct:0.5 }, { id:5, ini:10000.01, fim:25000, pct:1 } ],
  "e-2": [],
  "e-3": [], "e-4": [], "e-5": [], "e-6": [],
};
const REALIZADO = { "e-1":{ mes:24826, anterior:19870 }, "e-7":{ mes:9620, anterior:11240 }, "e-2":{ mes:0, anterior:0 }, "e-3":{ mes:0, anterior:0 }, "e-4":{ mes:0, anterior:0 }, "e-5":{ mes:0, anterior:0 }, "e-6":{ mes:0, anterior:0 } };

// ── Configurações (businesses.essentials_settings — controller já é Inertia) ──
const CFG = {
  leave_ref_no_prefix: "LIC",
  leave_instructions: "Peça a licença com no mínimo 15 dias de antecedência. Atestado médico entra no RH em até 48 h.",
  payroll_ref_no_prefix: "FP",
  essentials_todos_prefix: "TAR",
  grace_before_checkin: "10",
  grace_after_checkin: "10",
  grace_before_checkout: "5",
  grace_after_checkout: "30",
  is_location_required: true,
  calculate_sales_target_commission_without_tax: true,
};

// ── Achados da leitura do main (viram aviso na tela, não decoração) ──
const ACHADOS = [
  { id:"A1", tom:"danger", t:"A tradução PT do módulo está quebrada na cara do cliente", d:"Resources/lang/pt/lang.php traduz leave como “Sair”, all_leaves como “Todas as folhas”, attendance como “Comparecimento” e holidays como “Férias”. A tela do balcão mostra isso hoje. Esta reconstrução usa o vocabulário certo (licença · presença · feriado) e o F3 precisa corrigir as chaves." },
  { id:"A2", tom:"danger", t:"Pedir licença não valida nada", d:"EssentialsLeaveController::store lê essentials_leave_type_id, start_date, end_date e reason cru — sem FormRequest. Fim antes do início passa, tipo de outro negócio passa, período vazio passa." },
  { id:"A3", tom:"warn",   t:"max_leave_count do tipo não é aplicado", d:"O limite existe no cadastro e aparece no resumo, mas nada bloqueia a 31ª diária de férias: a checagem não existe no store." },
  { id:"A4", tom:"warn",   t:"Turno e tipo de licença não têm exclusão", d:"ShiftController::destroy e EssentialsLeaveTypeController::destroy estão vazios — o registro fica pra sempre. A tela reflete isso: só editar." },
  { id:"A5", tom:"warn",   t:"Meta de venda aceita faixas sobrepostas", d:"saveSalesTarget grava faixa a faixa sem comparar; a consulta usa target_start<=venda<=target_end e pega a primeira — duas faixas sobrepostas viram comissão indeterminada." },
  { id:"A6", tom:"info",   t:"Presença aqui não é o ponto legal", d:"Este é o clock-in/out web do Essentials (IP + geolocalização opcional). O ponto sob a Portaria MTP 671/2021 é o módulo Ponto WR2 — os dois convivem e não se conversam hoje." },
  { id:"A7", tom:"info",   t:"Importar presença insere sem conferir duplicidade", d:"importAttendance valida e-mail e turno linha a linha, mas o insert final não usa a mesma checagem de sobreposição do validateClockInClockOut." },
];

const DIA = { monday:"seg", tuesday:"ter", wednesday:"qua", thursday:"qui", friday:"sex", saturday:"sáb", sunday:"dom" };

// ── Papéis simulados (onda O9) — a permissão real vem do Spatie; aqui é afordância de protótipo ──
const PAPEIS = {
  admin: { l:"Administrador", d:"is_admin — vê e faz tudo no negócio", pode:["ver_todos","aprovar","gerir_licenca","gerir_turno","gerir_feriado","gerir_folha","gerir_meta","config","importar","marcar"] },
  gestor: { l:"Gestor de equipe", d:"crud_all_leave · approve_leave · crud_all_attendance", pode:["ver_todos","aprovar","gerir_licenca","importar","marcar"] },
  colab:  { l:"Colaborador", d:"crud_own_leave · view_own_attendance · allow_users_for_attendance_from_web", pode:["marcar"] },
};
const ESTADOS = { normal:"Com dados", primeira:"Primeira vez", demo:"Demonstração" };
const ST_LIC = { pending:{ l:"Pendente", t:"warn" }, approved:{ l:"Aprovada", t:"ok" }, cancelled:{ l:"Cancelada", t:"danger" } };
const ST_PAG = { paid:{ l:"Pago", t:"ok" }, partial:{ l:"Parcial", t:"warn" }, due:{ l:"A pagar", t:"danger" } };

const dias = (ini, fim) => Math.round((new Date(fim) - new Date(ini)) / 86400000) + 1;
// Concordância: frase inteira por quantidade — nunca concatenar rótulo de enum com contagem
const plural = (n, um, muitos) => n === 1 ? um : muitos.replace("{n}", n);
const dt = (iso) => { if (!iso) return "—"; const [a, m, d] = iso.split("-"); return `${d}/${m}/${a}`; };
const emp = (id) => EMP.find((e) => e.id === id) || { nome:"—", cargo:"—", setor:"—" };
const totalFolha = (f) => f.base + f.ganhos.reduce((s, g) => s + g[1], 0) - f.deducoes.reduce((s, d) => s + d[1], 0);

window.HRM = { EMP, TIPOS, LIC, FER, TUR, PRE, GD, LOTES, FOLHA, METAS, REALIZADO, CFG, ACHADOS, DIA, PAPEIS, ESTADOS, ST_LIC, ST_PAG, brl, dias, dt, emp, totalFolha, plural };
})();
