// essenciais-data.jsx — dados do módulo Essentials (aba "Essenciais" do nav_essentials.blade):
// todo · document · memos · reminder · messages. Espelha as entidades do main:
// Modules/Essentials/Entities/{ToDo,EssentialsMessage,Reminder}.php + document/memos (media + notes).
// Colaboradores vêm de window.HRM.EMP (hrm-data.jsx). Expõe window.ESSENCIAIS.
(() => {
const H = window.HRM || {};
const EMP = H.EMP || [];
const nome = (id) => (EMP.find((e) => e.id === id) || {}).nome || "—";

// ToDo::getTaskStatus() — new · in_progress · on_hold · completed
const ST_TAR = {
  new:         { l:"Nova",          tone:"info" },
  in_progress: { l:"Em andamento",  tone:"warn" },
  on_hold:     { l:"Em espera",     tone:"muted" },
  completed:   { l:"Concluída",     tone:"ok" },
};
// ToDo::getTaskPriorities() — low · medium · high · urgent
const PRIO = {
  low:    { l:"Baixa",   tone:"muted" },
  medium: { l:"Média",   tone:"info" },
  high:   { l:"Alta",    tone:"warn" },
  urgent: { l:"Urgente", tone:"danger" },
};

// essentials_todos — task_id · task · status · priority · date · end_date · estimated_hours ·
// created_by (assigned_by) · users[] (essentials_todo_users) · description · media · comments
const TAREFAS = [
  { id:1, ref:"TAR2026/0012", tarefa:"Refazer prova do painel de fachada — cliente pediu Pantone 2925 C",
    status:"in_progress", prio:"urgent", ini:"19/08/2026", fim:"22/08/2026", horas:6, criado:"18/08/2026 09:12",
    por:"e-2", a:["e-5","e-4"], desc:"O cliente reprovou a primeira prova: o azul saiu esverdeado no Latex. Refazer o arquivo com o Pantone 2925 C convertido no perfil da HP, imprimir tira de controle e mandar foto antes de produzir os 12 m².",
    docs:[{ n:"prova-fachada-v2.pdf", t:"PDF · 2,4 MB" }, { n:"pantone-2925c.png", t:"PNG · 380 KB" }],
    coments:[{ por:"e-5", q:"19/08 10:40", t:"Arquivo convertido, tira de controle na Roland. Mando foto ainda hoje." },
             { por:"e-2", q:"19/08 11:02", t:"Ok. Só produza depois do de-acordo por escrito do cliente." }], hist:[["18/08/2026 09:12","new","e-2"],["19/08/2026 10:38","in_progress","e-5"]] },
  { id:2, ref:"TAR2026/0011", tarefa:"Conferir estoque de lona 440 g antes da compra de setembro",
    status:"new", prio:"high", ini:"20/08/2026", fim:"25/08/2026", horas:2, criado:"18/08/2026 16:30",
    por:"e-2", a:["e-3"], desc:"Contar bobina por bobina no depósito e comparar com o saldo do sistema. O saldo do mês passado fechou com 3 bobinas de diferença.",
    docs:[], coments:[], hist:[["18/08/2026 16:30","new","e-2"]] },
  { id:3, ref:"TAR2026/0010", tarefa:"Ligar para os 8 orçamentos parados há mais de 10 dias",
    status:"in_progress", prio:"medium", ini:"18/08/2026", fim:"21/08/2026", horas:4, criado:"17/08/2026 08:05",
    por:"e-2", a:["e-1","e-7"], desc:"Lista puxada do funil: orçamentos aprovados internamente e sem retorno do cliente. Registrar o motivo da recusa no CRM — não deixar só \"sem resposta\".",
    docs:[{ n:"orcamentos-parados.xlsx", t:"XLSX · 41 KB" }],
    coments:[{ por:"e-1", q:"18/08 14:20", t:"5 de 8 respondidos. Dois pediram desconto, um fechou." }], hist:[["17/08/2026 08:05","new","e-2"],["18/08/2026 09:10","in_progress","e-1"]] },
  { id:4, ref:"TAR2026/0009", tarefa:"Trocar a lâmina do plotter de recorte e registrar no patrimônio",
    status:"completed", prio:"medium", ini:"14/08/2026", fim:"15/08/2026", horas:1, criado:"13/08/2026 17:44",
    por:"e-4", a:["e-6"], desc:"Lâmina de 45° gastando o vinil nas curvas. Trocar e lançar a manutenção no módulo de Patrimônio.",
    docs:[], coments:[{ por:"e-6", q:"15/08 09:10", t:"Trocada e lançada. Sobrou 1 lâmina no estoque." }], hist:[["13/08/2026 17:44","new","e-4"],["14/08/2026 08:20","in_progress","e-6"],["15/08/2026 09:10","completed","e-6"]] },
  { id:5, ref:"TAR2026/0008", tarefa:"Montar kit de amostras de ACM para a visita na construtora",
    status:"on_hold", prio:"low", ini:"12/08/2026", fim:"", horas:3, criado:"11/08/2026 11:20",
    por:"e-1", a:["e-6"], desc:"Parado: a construtora remarcou a visita para setembro. Retomar quando a data voltar.",
    docs:[], coments:[], hist:[["11/08/2026 11:20","new","e-1"],["12/08/2026 15:02","on_hold","e-1"]] },
  { id:6, ref:"TAR2026/0007", tarefa:"Revisar textos do site institucional (página de serviços)",
    status:"new", prio:"low", ini:"21/08/2026", fim:"31/08/2026", horas:5, criado:"10/08/2026 15:02",
    por:"e-2", a:["e-5"], desc:"Tirar o jargão. Cada serviço com uma frase de uma linha e o prazo médio de entrega.",
    docs:[], coments:[], hist:[["10/08/2026 15:02","new","e-2"]] },
  { id:7, ref:"TAR2026/0006", tarefa:"Fechar a conciliação bancária de julho",
    status:"completed", prio:"high", ini:"03/08/2026", fim:"08/08/2026", horas:8, criado:"01/08/2026 09:00",
    por:"e-2", a:["e-3"], desc:"Conciliar Itaú e Mercado Pago. Diferença de R$ 148,90 do mês passado precisa aparecer no relatório.",
    docs:[{ n:"extrato-itau-julho.ofx", t:"OFX · 96 KB" }],
    coments:[{ por:"e-3", q:"08/08 17:35", t:"Fechado. A diferença era taxa de antecipação não lançada." }], hist:[["01/08/2026 09:00","new","e-2"],["03/08/2026 08:40","in_progress","e-3"],["08/08/2026 17:35","completed","e-3"]] },
  { id:8, ref:"TAR2026/0005", tarefa:"Instalar totem do estacionamento — obra liberada",
    status:"in_progress", prio:"high", ini:"20/08/2026", fim:"20/08/2026", horas:6, criado:"07/08/2026 10:15",
    por:"e-4", a:["e-6","e-4"], desc:"Equipe sai 07:00 com a caminhonete. Levar furadeira SDS, buchas químicas e nível a laser.",
    docs:[], coments:[], hist:[["07/08/2026 10:15","new","e-4"],["20/08/2026 07:05","in_progress","e-6"]] },
];

// essentials_documents (media do model Document) — nome do arquivo · descrição · upload · quem subiu
const DOCS = [
  { id:1, arq:"contrato-social-2026.pdf", tipo:"PDF", tam:"1,8 MB", desc:"Contrato social consolidado com a última alteração de quadro societário.", data:"12/08/2026 10:22", por:"e-2", comp:[{ q:"Eliana Pereira", f:"Financeiro" }] },
  { id:2, arq:"tabela-precos-cv-ago2026.xlsx", tipo:"XLSX", tam:"212 KB", desc:"Tabela de preços de comunicação visual vigente — m² por material e acabamento.", data:"05/08/2026 08:40", por:"e-2", comp:[{ q:"Larissa Andrade", f:"Comercial" }, { q:"Júlia Ferreira", f:"Comercial" }] },
  { id:3, arq:"manual-hp-latex-315.pdf", tipo:"PDF", tam:"6,4 MB", desc:"Manual do fabricante — calibração, limpeza de cabeças e códigos de erro.", data:"28/07/2026 14:05", por:"e-4", comp:[] },
  { id:4, arq:"apolice-frota-2026.pdf", tipo:"PDF", tam:"920 KB", desc:"Apólice da caminhonete de instalação. Vence em 03/2027.", data:"19/07/2026 16:50", por:"e-3", comp:[{ q:"Wagner Oliveira", f:"Diretoria" }] },
  { id:5, arq:"perfil-icc-lona440.icc", tipo:"ICC", tam:"1,1 MB", desc:"Perfil de cor da lona 440 g no Latex — usar sempre este na prova.", data:"11/07/2026 09:18", por:"e-5", comp:[{ q:"Tiago Mendes", f:"Produção" }] },
];

// document?type=memos — heading · description · created_at
const MEMOS = [
  { id:1, tit:"Ponto: intercorrência só até o dia 5", desc:"Pedido de ajuste de marcação do mês anterior entra até o dia 5. Depois disso o espelho já foi fechado e o ajuste vira acerto na folha seguinte.", data:"17/08/2026 09:00", por:"e-3", comp:[{ q:"Todos os colaboradores", f:"Geral" }] },
  { id:2, tit:"Entrega de arte pelo cliente — formato aceito", desc:"Só aceitar arquivo fechado em PDF/X-4 ou AI com fontes convertidas. JPG de WhatsApp não entra em produção: pedir o arquivo original ou cobrar tratamento.", data:"14/08/2026 11:30", por:"e-5", comp:[{ q:"Larissa Andrade", f:"Comercial" }, { q:"Júlia Ferreira", f:"Comercial" }] },
  { id:3, tit:"Feriado de 07/09 — plantão de instalação", desc:"Oficina e balcão fechados. Instalação com plantão das 8h às 12h para a obra do shopping. Quem estiver de plantão folga no dia 08.", data:"10/08/2026 15:45", por:"e-2", comp:[{ q:"Produção", f:"Setor" }] },
  { id:4, tit:"Uso da caminhonete", desc:"Reservar no calendário de Lembretes antes de sair. Quem tirar devolve com o tanque acima da metade e o vale no financeiro no mesmo dia.", data:"02/08/2026 08:10", por:"e-2", comp:[] },
];

// essentials_reminders — name · date · time · end_time · repeat
const REPETE = { one_time:"Uma vez", every_day:"Todo dia", every_week:"Toda semana", every_month:"Todo mês" };
// origem: manual (essentials_reminders) · financeiro (título a vencer) · ponto (fechamento do mês)
const LEMB = [
  { id:1, nome:"Instalação totem — estacionamento Zona Sul", data:"2026-08-20", h:"07:00", fim:"12:00", rep:"one_time", por:"e-4", origem:"manual" },
  { id:2, nome:"Reunião de produção", data:"2026-08-24", h:"08:30", fim:"09:15", rep:"every_week", por:"e-2", origem:"manual" },
  { id:3, nome:"Backup do servidor — conferir log", data:"2026-08-21", h:"18:00", fim:"18:20", rep:"every_day", por:"e-2", origem:"manual" },
  { id:4, nome:"Fechamento do ponto do mês", data:"2026-08-31", h:"16:00", fim:"18:00", rep:"every_month", por:"e-3", origem:"ponto" },
  { id:5, nome:"Vence a parcela do Latex (banco)", data:"2026-08-28", h:"09:00", fim:"", rep:"every_month", por:"e-3", origem:"financeiro", valor:"R$ 4.180,00" },
  { id:6, nome:"Visita à construtora Andrade (amostras ACM)", data:"2026-09-03", h:"14:00", fim:"15:30", rep:"one_time", por:"e-1", origem:"manual" },
  { id:7, nome:"Manutenção preventiva do plotter de recorte", data:"2026-08-26", h:"17:00", fim:"18:00", rep:"every_month", por:"e-6", origem:"manual" },
  { id:8, nome:"Vence o aluguel da Filial Norte", data:"2026-08-25", h:"09:00", fim:"", rep:"every_month", por:"e-3", origem:"financeiro", valor:"R$ 2.900,00" },
  { id:9, nome:"Intercorrência de ponto fecha (dia 5)", data:"2026-09-05", h:"17:00", fim:"", rep:"every_month", por:"e-3", origem:"ponto" },
];
const ORIGEM = {
  manual:     { l:"Lembrete",  tone:"accent", d:"essentials_reminders — criado por alguém da equipe." },
  financeiro: { l:"Financeiro", tone:"warn",  d:"Título a vencer no módulo Financeiro. Só leitura aqui: quem paga baixa lá." },
  ponto:      { l:"Ponto",      tone:"info",  d:"Prazo do módulo Ponto/HRM (fechamento e intercorrência)." },
};

// essentials_messages — mural interno por localidade (business_location)
const LOCAIS = ["Matriz", "Filial Norte"];
const MSG = [
  { id:1, lida:true, por:"e-2", local:"Matriz", q:"18/08/2026 08:12", t:"Bom dia. A partir de hoje toda OS com instalação sai com foto do local antes de furar. Sem foto, a instalação não é fechada." },
  { id:2, lida:true, por:"e-1", local:"Matriz", q:"18/08/2026 08:31", t:"Beleza. Já avisei os dois clientes de hoje que o técnico vai fotografar antes." },
  { id:3, lida:true, por:"e-4", local:"Matriz", q:"18/08/2026 10:05", t:"A Latex ficou 40 min parada de manhã — erro 0x21 de cabeça. Limpei e voltou. Se repetir, chamo a assistência." },
  { id:4, lida:false, por:"e-6", local:"Filial Norte", q:"18/08/2026 13:40", t:"Chegou a lona 440 do fornecedor novo. Rolo com 1 cm de emenda na ponta, avisei o Tiago." },
  { id:5, lida:false, por:"e-3", local:"Matriz", q:"19/08/2026 09:15", t:"Lembrete: nota do mês fecha dia 31. Quem tem despesa de viagem manda o comprovante até dia 28." },
  { id:6, lida:false, por:"e-5", local:"Matriz", q:"19/08/2026 11:48", t:"Subi o perfil ICC da lona 440 nos Documentos. Usem esse na prova, o antigo puxava pro verde." },
];

// Permissões do módulo (Spatie): essentials.assign_todos · add_todos · view_message ·
// create_message · essentials.upload_documents. No protótipo mapeadas por papel.
const PERMS = {
  admin:  ["atribuir", "add_tarefa", "editar_tarefa", "excluir", "subir_doc", "compartilhar", "add_memo", "add_lembrete", "ver_msg", "criar_msg", "ver_todas", "gerir_kb", "config"],
  gestor: ["atribuir", "add_tarefa", "editar_tarefa", "subir_doc", "compartilhar", "add_lembrete", "ver_msg", "criar_msg", "ver_todas", "gerir_kb"],
  colab:  ["add_tarefa", "subir_doc", "add_lembrete", "ver_msg", "criar_msg"],
};

// Funções do Spatie usadas no compartilhamento (document_share/edit.blade: por usuário OU por função)
const FUNCOES = ["Admin", "Gestor de equipe", "Comercial", "Produção", "Financeiro", "Instalação"];

// Tipos aceitos no upload (config constants.document_upload_mimes_types) + limite do businesses
const UPLOAD = { tipos:["pdf", "jpg", "png", "xlsx", "csv", "icc", "zip"], limite:"10 MB" };

// knowledge_base (essentials_knowledge_bases: title · content · parent_id — categoria → seção → artigo)
const KB = [
  { id:1, t:"Balcão e atendimento", c:"Como o pedido entra: do orçamento ao de-acordo por escrito.", filhos:[
    { id:11, t:"Receber arte do cliente", c:"Só entra em produção arquivo fechado em PDF/X-4 ou AI com fontes convertidas. JPG de WhatsApp não vale: peça o original ou lance o tratamento de arte no orçamento.",
      blocos:[{ t:"h", x:"O que aceitar" },
              { t:"li", x:"PDF/X-4 com sangria e marcas" },
              { t:"li", x:"AI ou EPS com fontes convertidas em curva" },
              { t:"li", x:"Imagem em alta no tamanho final (mínimo 72 dpi a 1:1)" },
              { t:"h", x:"O que devolver pro cliente" },
              { t:"p", x:"Print de tela, foto de arte impressa e JPG de WhatsApp voltam com pedido do arquivo original — ou entram como tratamento de arte cobrado por hora." }],
      filhos:[
      { id:111, t:"Checklist do arquivo", c:"Sangria de 2 cm em lona · resolução mínima de 72 dpi no tamanho final · cor em CMYK com o perfil ICC do material · texto a mais de 5 cm da borda quando tem acabamento." },
      { id:112, t:"Quando cobrar tratamento", c:"Arquivo em baixa, vetor sem fonte, arte montada em Word ou Canva sem exportação. Cobrança por hora conforme a tabela vigente." }]},
    { id:12, t:"De-acordo do cliente", c:"Prova enviada por e-mail e resposta escrita antes de produzir. Aprovação por telefone não conta — registre no CRM quem aprovou e quando.", filhos:[] }]},
  { id:2, t:"Produção", c:"Regras de máquina, material e acabamento.", filhos:[
    { id:21, t:"Calibração da Latex", c:"Calibrar cor no começo do turno e depois de cada troca de material. Imprimir tira de controle antes de rodar tiragem acima de 5 m².",
      blocos:[{ t:"h", x:"Rotina do turno" },
              { t:"li", x:"Calibrar cor ao ligar e a cada troca de material" },
              { t:"li", x:"Tira de controle antes de qualquer tiragem acima de 5 m²" },
              { t:"li", x:"Registrar a calibração no diário da máquina" },
              { t:"p", x:"Sem tira de controle não se produz painel de fachada: a cor reprovada volta como retrabalho e o material já foi." }],
      filhos:[
      { id:211, t:"Erro 0x21 (cabeça)", c:"Limpeza automática duas vezes. Se voltar, trocar o cartucho de limpeza e abrir chamado na assistência — não insistir, risca a cabeça." }]},
    { id:22, t:"Lona 440 g", c:"Usar sempre o perfil ICC publicado em Documentos. O perfil antigo puxava para o verde nos azuis.", filhos:[] }]},
  { id:3, t:"Administrativo", c:"Ponto, compras e fechamento.", filhos:[
    { id:31, t:"Intercorrência de ponto", c:"Pedido de ajuste da marcação do mês anterior entra até o dia 5. Depois disso, o espelho já foi fechado e o ajuste vira acerto na folha seguinte.", filhos:[] },
    { id:32, t:"Compra de material", c:"Contar o estoque físico antes de pedir. Pedido acima de R$ 3.000 passa pelo gestor.", filhos:[] }]},
];

// essentials_settings do módulo (EssentialsSettingsController — parte já é Inertia no main)
const CFG = {
  essentials_todos_prefix: "TAR",
  todo_assign_default: "gestor",
  document_upload_max: "10",
  memo_share_default: "funcao",
  reminder_default_repeat: "one_time",
  message_locations: true,
  kb_public: false,
};

window.ESSENCIAIS = { TAREFAS, DOCS, MEMOS, LEMB, MSG, KB, CFG, ST_TAR, PRIO, REPETE, ORIGEM, LOCAIS, FUNCOES, UPLOAD, PERMS, nome };
})();
