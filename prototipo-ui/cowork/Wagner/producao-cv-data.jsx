// producao-cv-data.jsx — dados do módulo Produção (comunicação visual), padrão "OS como projeto".
// Mock realista de gráfica de grandes formatos: lona, adesivo, ACM, fachada, letra-caixa.
// Âncoras de domínio no main: memory/requisitos/Pcp/SPEC.md (postos, operações, apontamento append-only),
// research/2026-05-prospeccao/33-grade-producao-concorrentes.md (22 funções). Expõe window.PCVD.
(() => {
const HOJE = "2026-10-15";
const fmt = (n) => "R$ " + Number(n || 0).toLocaleString("pt-BR", { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const d2 = (iso) => { if (!iso) return "—"; const [a, m, d] = iso.split("-"); return d + "/" + m; };
const h1 = (n) => Number(n || 0).toLocaleString("pt-BR", { minimumFractionDigits: 1, maximumFractionDigits: 1 });
const dias = (a, b) => Math.round((new Date(b) - new Date(a)) / 86400000);

// Setores (colunas do quadro) — ordem do fluxo
const SETORES = [
  { id: "arte",       nome: "Arte e aprovação", cor: "oklch(0.62 0.13 295)" },
  { id: "impressao",  nome: "Impressão",        cor: "oklch(0.6 0.13 240)" },
  { id: "acabamento", nome: "Acabamento",       cor: "oklch(0.65 0.13 75)" },
  { id: "serralheria",nome: "Serralheria",      cor: "oklch(0.6 0.1 30)" },
  { id: "instalacao", nome: "Instalação",       cor: "oklch(0.6 0.12 160)" },
  { id: "expedicao",  nome: "Expedição",        cor: "oklch(0.58 0.04 250)" },
];

// Turno da empresa (D-TURNO): configurável em pcp_turnos, padrão 08h–18h. É o fim do turno que marca o estouro na
// Linha do tempo e que fecha o cronômetro esquecido — não um "18h" fixo na tela.
const TURNO = { inicio: 8, fim: 18, rotulo: "08h–18h" };
// Calendário de produção (UC-FLX-06): pula sábado, domingo e feriados cadastrados no RH. "Hoje" é quinta 15/10;
// o próximo dia útil é sexta 16/10.
const DIAS = { hoje: { iso: "2026-10-15", rotulo: "Hoje, qui 15/10" }, proximo: { iso: "2026-10-16", rotulo: "Próximo dia útil, sex 16/10" } };

// Postos / máquinas (pcp_workstations) com capacidade por hora
// horasDia = capacidade do posto no dia (pcp_postos.horas_por_dia, D4) — é contra ela que a carga vira gargalo.
// operadores = quem pode ser responsável pela etapa naquele posto (UC-FLX-08 · US-PCP-030). `operador` é o principal.
const MAQUINAS = [
  { id: "latex",  nome: "HP Latex 365",     setor: "impressao",  cap: "12 m²/h", capH: 12, un: "m²", operador: "Diego", operadores: ["Diego"], horasDia: 10 },
  { id: "eco",    nome: "Roland VG3-640",   setor: "impressao",  cap: "9 m²/h",  capH: 9,  un: "m²", operador: "Diego", operadores: ["Diego"], horasDia: 10 },
  { id: "uv",     nome: "Mimaki UJF-6042",  setor: "impressao",  cap: "4 m²/h",  capH: 4,  un: "m²", operador: "Paula", operadores: ["Paula"], horasDia: 8 },
  { id: "recorte",nome: "Plotter de recorte GR2", setor: "acabamento", cap: "20 m/h", capH: 20, un: "m", operador: "Felipe", operadores: ["Felipe"], horasDia: 10 },
  { id: "lamin",  nome: "Laminadora a frio", setor: "acabamento", cap: "30 m²/h", capH: 30, un: "m²", operador: "Felipe", operadores: ["Felipe"], horasDia: 10 },
  { id: "cnc",    nome: "Router CNC 2x3",   setor: "serralheria",cap: "3 chapas/h", capH: 3, un: "chapa", operador: "Marcos", operadores: ["Marcos"], horasDia: 9 },
  { id: "solda",  nome: "Bancada de solda", setor: "serralheria",cap: "—", capH: 0, un: "h", operador: "Marcos", operadores: ["Marcos"], horasDia: 9 },
];

const PESSOAS = {
  Ana: "Arte", Diego: "Impressão", Paula: "Impressão UV", Felipe: "Acabamento", Marcos: "Serralheria",
  Jonas: "Instalação", Rafa: "Instalação", Larissa: "Balcão",
};

// Roteiros por FAMÍLIA de produto. Regra de simplicidade (para treinar a equipe):
//   · no máximo 2 trilhas em paralelo, que sempre se juntam numa etapa de junção;
//   · etapa opcional liga pela opção marcada no orçamento ("com laminação", "com instalação") — ninguém escolhe na hora;
//   · sem editor livre de fluxograma: o PCP escolhe a família e ajusta tempos, não desenha caminhos.
// Formato: lista de passos; um passo é [setor, nome, máquina|horas, opcional?] ou { paralelo: [trilhaA, trilhaB], junta: "nome" }.
const ROTEIROS = {
  lona:    { nome: "Lona impressa", familia: "Impresso simples", etapas: [["arte","Arte e prova",0.5],["impressao","Impressão látex","latex"],["acabamento","Bainha + ilhós",1],["acabamento","Bastão e corda",0.5,"com bastão"],["expedicao","Embalar e liberar",0.25]] },
  adesivo: { nome: "Adesivo impresso e recortado", familia: "Impresso simples", etapas: [["arte","Arte e prova",0.5],["impressao","Impressão eco-solvente","eco"],["acabamento","Laminação","lamin","com laminação"],["acabamento","Recorte eletrônico","recorte"],["instalacao","Aplicação em campo",2,"com aplicação"],["expedicao","Embalar",0.25]] },
  acm:     { nome: "Placa em ACM com UV", familia: "Placa rígida", etapas: [["arte","Arte e arquivo de corte",1],
             { paralelo: [[["serralheria","Corte CNC","cnc"]], [["impressao","Preparo da mesa UV",0.3]]], junta: "Impressão UV na placa" },
             ["impressao","Impressão UV na placa","uv"],["expedicao","Proteção e embalagem",0.5]] },
  fachada: { nome: "Fachada em ACM + estrutura", familia: "Projeto com instalação", etapas: [["arte","Projeto e medição",2],
             { paralelo: [[["serralheria","Estrutura metálica",6],["serralheria","Corte CNC do ACM","cnc"]], [["impressao","Adesivo de aplicação","eco"],["acabamento","Laminação","lamin","com laminação"]]], junta: "Montagem na fábrica" },
             ["serralheria","Montagem na fábrica",2],["instalacao","Instalação em campo",6]] },
  letra:   { nome: "Letra-caixa iluminada", familia: "Projeto com instalação", etapas: [["arte","Vetor e gabarito",1],
             { paralelo: [[["serralheria","Corte CNC das faces","cnc"],["serralheria","Dobra e solda das laterais",4]], [["acabamento","Elétrica: LED e fonte",2]]], junta: "Montagem + teste de acendimento" },
             ["serralheria","Montagem + teste de acendimento",1.5],["instalacao","Instalação",3,"com instalação"]] },
};

// OS-projeto: cada uma com etapas instanciadas do roteiro, apontamentos, custo orçado x real
const OS = [
  { id: "OS-2611", cliente: "Rota Livre Transportes", titulo: "Adesivagem de frota — 6 baús", produto: "adesivo", qtd: "86 m²", prazo: "2026-10-17", prioridade: "alta",
    arte: "aprovada", aprovadaEm: "2026-10-13 16:40", resp: "Larissa", orcado: 6880, real: 5210, perda: 4.1,
    etapas: [["arte","Arte e prova","Ana","feito",3,3.5],["impressao","Impressão eco-solvente","Diego","andamento",9.6,6.2,"eco"],["acabamento","Laminação","Felipe","fila",2.9,0,"lamin"],["acabamento","Recorte eletrônico","Felipe","fila",4.3,0,"recorte"],["expedicao","Embalar","Larissa","fila",0.5,0]] },
  { id: "OS-2608", cliente: "Padaria Pão Dourado", titulo: "Fachada ACM 8×1,2 m + letreiro", produto: "fachada", qtd: "9,6 m²", prazo: "2026-10-16", prioridade: "alta",
    arte: "aprovada", aprovadaEm: "2026-10-09 11:02", resp: "Larissa", orcado: 9400, real: 8120, perda: 0,
    etapas: [["arte","Projeto e medição","Ana","feito",2,2.5],["serralheria","Estrutura metálica","Marcos","feito",6,7.5,"solda","A"],["serralheria","Corte CNC do ACM","Marcos","andamento",3.2,2,"cnc","A"],["impressao","Adesivo de aplicação","Diego","fila",1.1,0,"eco","B"],["serralheria","Montagem na fábrica","Marcos","fila",2,0],["instalacao","Instalação em campo","Jonas","agendada",6,0]], junta: "Montagem na fábrica" },
  { id: "OS-2614", cliente: "Clínica Sorriso", titulo: "Placas de sinalização interna (24 un)", produto: "acm", qtd: "24 placas", prazo: "2026-10-20", prioridade: "normal",
    arte: "aguardando", resp: "Larissa", orcado: 3120, real: 380, perda: 0,
    etapas: [["arte","Arte e arquivo de corte","Ana","andamento",2,1.2],["serralheria","Corte CNC","Marcos","fila",2.7,0,"cnc"],["impressao","Impressão UV direta","Paula","fila",3.6,0,"uv"],["expedicao","Proteção e embalagem","Larissa","fila",0.5,0]] },
  { id: "OS-2603", cliente: "Supermercado Bom Preço", titulo: "Lonas de oferta 5×2 m (12 un)", produto: "lona", qtd: "120 m²", prazo: "2026-10-15", prioridade: "urgente",
    arte: "aprovada", aprovadaEm: "2026-10-12 09:15", resp: "Larissa", orcado: 4320, real: 4610, perda: 7.8,
    etapas: [["arte","Arte e prova","Ana","feito",1,0.8],["impressao","Impressão látex","Diego","feito",10,11.5,"latex"],["acabamento","Bainha + ilhós","Felipe","andamento",6,4.5],["expedicao","Embalar e liberar","Larissa","fila",0.5,0]],
    refugo: [{ etapa: "Impressão látex", motivo: "Banding na cabeça 3", m2: 9.4, custo: 338, quem: "Diego", quando: "14/10 15:20" }] },
  { id: "OS-2617", cliente: "Academia Força Total", titulo: "Letra-caixa iluminada \"FORÇA\"", produto: "letra", qtd: "5 letras", prazo: "2026-10-24", prioridade: "normal",
    arte: "aprovada", liberada: false, aprovadaEm: "2026-10-14 10:30", resp: "Larissa", orcado: 7650, real: 900, perda: 0,
    etapas: [["arte","Vetor e gabarito","Ana","feito",1,1],["serralheria","Corte CNC das faces","Marcos","fila",1.7,0,"cnc","A"],["serralheria","Dobra e solda das laterais","Marcos","fila",4,0,"solda","A"],["acabamento","Elétrica: LED e fonte","Felipe","fila",2,0,null,"B"],["serralheria","Montagem + teste de acendimento","Marcos","fila",1.5,0],["instalacao","Instalação","Rafa","a agendar",3,0]], junta: "Montagem + teste de acendimento" },
  { id: "OS-2619", cliente: "Supermercado Bom Preço", titulo: "Banner roll-up 0,8×2 m (4 un)", produto: "lona", qtd: "6,4 m²", prazo: "2026-10-18", prioridade: "normal",
    arte: "ajuste", resp: "Larissa", orcado: 960, real: 120, perda: 0,
    etapas: [["arte","Arte e prova","Ana","andamento",0.5,0.6],["impressao","Impressão látex","Diego","fila",0.6,0,"latex"],["acabamento","Bainha + ilhós","Felipe","fila",0.5,0],["expedicao","Embalar e liberar","Larissa","fila",0.25,0]] },
  // Liberada sem material (US-PCP-032 · D3): a chave da empresa está ligada e falta lona, então a OS foi de Na fila
  // para Aguardando material e nenhuma etapa começa até o PCP conferir o material.
  { id: "OS-2622", cliente: "Escola Crescer", titulo: "Painel de fachada em lona 6×2 m", produto: "lona", qtd: "12 m²", prazo: "2026-10-21", prioridade: "normal",
    arte: "aprovada", aprovadaEm: "2026-10-14 15:10", material: "aguardando", resp: "Larissa", orcado: 1480, real: 40, perda: 0,
    etapas: [["arte","Arte e prova","Ana","feito",0.5,0.5],["impressao","Impressão látex","Diego","fila",1,0,"latex"],["acabamento","Bainha + ilhós","Felipe","fila",1,0],["expedicao","Embalar e liberar","Larissa","fila",0.25,0]] },
  { id: "OS-2598", cliente: "Construtora Alicerce", titulo: "Tapume impresso 40 m", produto: "lona", qtd: "88 m²", prazo: "2026-10-14", prioridade: "alta",
    arte: "aprovada", aprovadaEm: "2026-10-08 14:00", resp: "Larissa", orcado: 3960, real: 3720, perda: 2.2,
    etapas: [["arte","Arte e prova","Ana","feito",1,1],["impressao","Impressão látex","Diego","feito",7.3,7.1,"latex"],["acabamento","Bainha + ilhós","Felipe","feito",4,3.8],["expedicao","Embalar e liberar","Larissa","andamento",0.5,0.3]] },
];

// Agenda de máquina (linha do tempo) — blocos em horas a partir de 08:00, hoje e amanhã
const AGENDA = [
  { maq: "latex",   os: "OS-2603", ini: 8,  fim: 9.5,  estado: "feito" },
  { maq: "latex",   os: "OS-2619", ini: 10, fim: 10.6, estado: "fila" },
  { maq: "latex",   os: "OS-2598", ini: 13, fim: 14,   estado: "feito" },
  { maq: "eco",     os: "OS-2611", ini: 8,  fim: 14.2, estado: "andamento" },
  { maq: "eco",     os: "OS-2608", ini: 14.5, fim: 15.6, estado: "fila" },
  { maq: "eco",     os: "OS-2611", ini: 15.8, fim: 18.9, estado: "fila" },
  { maq: "uv",      os: "OS-2614", ini: 13, fim: 16.6, estado: "fila", bloqueio: "arte aguardando aprovação" },
  { maq: "recorte", os: "OS-2611", ini: 15, fim: 17.5, estado: "fila" },
  { maq: "lamin",   os: "OS-2611", ini: 14.3, fim: 15,  estado: "fila", material: "aguardando material: laminado fosco 1,37 m (compra a caminho)" },
  { maq: "cnc",     os: "OS-2608", ini: 8,  fim: 11.2, estado: "andamento" },
  { maq: "cnc",     os: "OS-2614", ini: 11.5, fim: 14.2, estado: "fila", bloqueio: "arte aguardando aprovação" },
  { maq: "cnc",     os: "OS-2617", ini: 14.5, fim: 16.2, estado: "fila" },
  { maq: "solda",   os: "OS-2617", ini: 9,  fim: 17,   estado: "fila" },
];

// Orçamentos aprovados que ainda não viraram OS (passo orçamento → OS)
const ORCAMENTOS = [
  { id: "ORC-1184", cliente: "Mercado União", produto: "adesivo", desc: "Adesivo de vitrine 4 portas", qtd: 18, un: "m²", valor: 1620, opcoes: { "com laminação": true, "com aplicação": true }, prazo: "2026-10-21" },
  { id: "ORC-1187", cliente: "Pet Shop Amigo", produto: "fachada", desc: "Fachada ACM 6×1 m", qtd: 6, un: "m²", valor: 6900, opcoes: { "com laminação": false }, prazo: "2026-10-28" },
];

// Estouro de turno (D-TURNO): sai do fim do turno da empresa, não de um "18h" escrito à mão. Mesmo texto que a
// Linha do tempo monta quando o PCP arrasta um bloco para depois do turno.
const hhmm = (h) => String(Math.floor(h)).padStart(2, "0") + ":" + String(Math.round((h % 1) * 60)).padStart(2, "0");
AGENDA.forEach((b) => { if (b.fim > TURNO.fim) b.conflito = "termina " + hhmm(b.fim) + " — passa do fim do turno (" + hhmm(TURNO.fim) + ")"; });

// Próximo dia útil (sex 16/10) — usado pela linha do tempo. OS-2608 tem entrega prometida para este dia.
const AGENDA_AMANHA = [
  { maq: "latex", os: "OS-2619", ini: 8, fim: 8.6, estado: "fila" },
  { maq: "eco", os: "OS-2611", ini: 8, fim: 9.6, estado: "fila" },
  { maq: "uv", os: "OS-2614", ini: 8, fim: 11.6, estado: "fila", bloqueio: "arte aguardando aprovação" },
  { maq: "lamin", os: "OS-2611", ini: 9.8, fim: 10.5, estado: "fila" },
  { maq: "recorte", os: "OS-2611", ini: 10.5, fim: 12.8, estado: "fila" },
  { maq: "cnc", os: "OS-2617", ini: 8, fim: 9.7, estado: "fila" },
  { maq: "solda", os: "OS-2608", ini: 8, fim: 10, estado: "fila" },
  { maq: "solda", os: "OS-2617", ini: 10, fim: 18, estado: "fila" },
];

// Material por OS: empenhado no orçamento × consumido (baixa feita no apontamento)
const MATERIAIS = {
  "OS-2611": [["Vinil branco brilho 1,37 m", "m²", 92, 61], ["Laminado fosco 1,37 m", "m²", 92, 0], ["Tinta eco-solvente (CMYK)", "ml", 1400, 980]],
  "OS-2608": [["ACM 3 mm branco", "chapa", 4, 4], ["Metalon 30×30", "m", 26, 28.5], ["Vinil de aplicação", "m²", 10, 0]],
  "OS-2603": [["Lona 440 g 1,60 m", "m²", 124, 133.4], ["Ilhós latão nº 0", "un", 288, 192], ["Tinta látex", "ml", 2600, 2900]],
  "OS-2614": [["ACM 3 mm branco", "chapa", 3, 0], ["Tinta UV branca + CMYK", "ml", 600, 0]],
  "OS-2617": [["Acrílico 3 mm leitoso", "chapa", 2, 0], ["Chapa galvanizada 0,5", "m²", 3.2, 0], ["Módulo LED 3 lâmpadas", "un", 60, 0]],
  "OS-2619": [["Lona fosca 440 g", "m²", 7, 0], ["Estrutura roll-up", "un", 4, 0]],
  "OS-2598": [["Lona 440 g 1,60 m", "m²", 90, 90.8], ["Ilhós latão nº 0", "un", 160, 160]],
  "OS-2622": [["Lona 440 g 3,20 m", "m²", 13, 0], ["Ilhós latão nº 0", "un", 40, 0]],
};

// Perdas recentes de outras OS (relatório por motivo)
const PERDAS = [
  { os: "OS-2603", maq: "latex", motivo: "Banding / falha de cabeça", m2: 9.4, custo: 338 },
  { os: "OS-2590", maq: "eco", motivo: "Cor fora do padrão", m2: 4.2, custo: 151 },
  { os: "OS-2587", maq: "recorte", motivo: "Medida errada", m2: 2.6, custo: 94 },
  { os: "OS-2585", maq: "latex", motivo: "Banding / falha de cabeça", m2: 3.1, custo: 112 },
  { os: "OS-2581", maq: "cnc", motivo: "Erro de arte", m2: 1.5, custo: 210 },
];

// Eventos — fonte ÚNICA (append-only), lida pelo cartão do quadro, painel, linha, ficha, celular e instalação.
// tipo: etapa · arte · cliente · refugo · pcp · sistema · material · instalacao
const EVENTOS = [
  { quando: "15/10 11:14", os: "OS-2611", quem: "Diego", tipo: "material", maq: "eco", texto: "Baixou 58 m² de vinil branco brilho" },
  { quando: "15/10 10:52", os: "OS-2603", quem: "Felipe", tipo: "etapa", texto: "Iniciou Bainha + ilhós" },
  { quando: "15/10 10:31", os: "OS-2608", quem: "Marcos", tipo: "etapa", maq: "cnc", texto: "Pausou Corte CNC do ACM — troca de fresa" },
  { quando: "15/10 09:41", os: "OS-2622", quem: "Sistema", tipo: "material", texto: "Foi para Aguardando material: falta 8,0 m² de Lona 440 g 3,20 m" },
  { quando: "15/10 09:40", os: "OS-2622", quem: "Larissa", tipo: "pcp", texto: "Liberou a OS para a produção" },
  { quando: "15/10 09:12", os: "OS-2614", quem: "Sistema", tipo: "arte", texto: "Reenviou o link de prova ao cliente" },
  { quando: "15/10 08:47", os: "OS-2619", quem: "Cliente", tipo: "cliente", texto: "Pediu ajuste: aumentar o logo e trocar o telefone" },
  { quando: "15/10 08:05", os: "OS-2611", quem: "Diego", tipo: "etapa", maq: "eco", texto: "Iniciou Impressão eco-solvente (QR na máquina)" },
  { quando: "15/10 08:00", os: "OS-2608", quem: "Marcos", tipo: "etapa", maq: "cnc", texto: "Iniciou Corte CNC do ACM" },
  { quando: "14/10 18:00", os: "OS-2603", quem: "Sistema", tipo: "sistema", maq: "latex", texto: "Fechou automaticamente o apontamento aberto (fim do turno)" },
  { quando: "14/10 17:40", os: "OS-2611", quem: "Ana", tipo: "etapa", texto: "Concluiu Arte e prova" },
  { quando: "14/10 15:20", os: "OS-2603", quem: "Diego", tipo: "refugo", maq: "latex", texto: "Refugo de 9,4 m² — banding na cabeça 3" },
  { quando: "14/10 10:30", os: "OS-2617", quem: "Cliente", tipo: "cliente", texto: "Aprovou a arte pelo link" },
  { quando: "14/10 09:10", os: "OS-2598", quem: "Larissa", tipo: "etapa", texto: "Liberou para retirada" },
  { quando: "13/10 16:40", os: "OS-2611", quem: "Cliente", tipo: "cliente", texto: "Aprovou a arte pelo link" },
  { quando: "13/10 11:05", os: "OS-2611", quem: "Larissa", tipo: "pcp", texto: "Orçamento aprovado virou OS (roteiro Adesivo impresso e recortado)" },
];
// Relógio simulado do protótipo: o dia é 15/10 e "agora" é 11:18 (a linha vermelha da Linha do tempo).
// Cada evento novo anda 1 minuto, para a ordem da lista sempre bater com a hora.
let relogio = 11 * 60 + 18;
const agora = () => { const m = relogio++; return "15/10 " + String(Math.floor(m / 60)).padStart(2, "0") + ":" + String(m % 60).padStart(2, "0"); };
const registrar = (ev) => { EVENTOS.unshift({ quando: agora(), quem: "Você", ...ev }); window.dispatchEvent(new CustomEvent("pcv:evento")); };
const eventosDe = (os) => EVENTOS.filter((e) => e.os === os);

// Retalhos de mídia guardados (sobra com etiqueta QR) — o sistema avisa quando uma OS pequena cabe num deles
const RETALHOS = [
  { id: "RT-031", midia: "Lona 440 g", larg: 1.6, comp: 2.4, origem: "OS-2603", local: "Prateleira B2" },
  { id: "RT-028", midia: "Vinil branco brilho", larg: 1.37, comp: 1.1, origem: "OS-2590", local: "Prateleira A1" },
  { id: "RT-024", midia: "Lona 440 g", larg: 0.9, comp: 3.2, origem: "OS-2585", local: "Prateleira B2" },
];
// Ferramentas e veículos da instalação (retirar e devolver por QR)
const FERRAMENTAS = [
  { id: "FER-07", nome: "Andaime 6 m", tipo: "equipamento" }, { id: "FER-12", nome: "Furadeira de impacto", tipo: "ferramenta" },
  { id: "FER-15", nome: "Nível a laser", tipo: "ferramenta" }, { id: "VEI-02", nome: "Fiorino ABC-1D23", tipo: "veículo" },
];

// Instalações (agenda de campo)
const INSTALACOES = [
  { id: "I-118", os: "OS-2608", cliente: "Padaria Pão Dourado", end: "R. Edgard Ferraz, 412 — Centro, Jaú/SP", dia: "2026-10-16", hora: "08:00", dur: 6, equipe: ["Jonas", "Rafa"], estado: "agendada",
    checklist: ["Andaime e EPI", "Nível e prumo", "Teste de vedação", "Foto antes", "Foto depois", "Assinatura do cliente"], veiculo: "Fiorino ABC-1D23" },
  { id: "I-121", os: "OS-2617", cliente: "Academia Força Total", end: "Av. Brasil, 1890 — Jaú/SP", dia: "2026-10-24", hora: "14:00", dur: 3, equipe: ["Rafa"], estado: "a confirmar",
    checklist: ["Ponto elétrico conferido", "Fixação química", "Teste de acendimento", "Foto antes", "Foto depois", "Assinatura do cliente"], veiculo: "Fiorino ABC-1D23" },
  { id: "I-109", os: "OS-2588", cliente: "Farmácia Vida", end: "R. Major Prado, 77 — Jaú/SP", dia: "2026-10-13", hora: "09:00", dur: 2, equipe: ["Jonas"], estado: "concluída",
    checklist: ["Foto antes", "Foto depois", "Assinatura do cliente"], assinado: "Marta Ribeiro", fotos: 4, veiculo: "Strada DEF-4G56" },
];

// Aproveitamento de mídia (encaixe na bobina 1,60 m) — por máquina, hoje
const ENCAIXE = { bobina: "Lona 440 g · 1,60 m", usado: 92.4, desperdicio: 7.6 };

// Falta de material da OS em Aguardando material (US-PCP-032 · UC-PCMAT-02/03). Por insumo da receita:
// necessário = receita × quantidade da OS · disponível = saldo no local da venda menos reservas ativas de outras OS e
// vendas · a caminho = compras pedidas e ainda não recebidas. Só lê estoque: conferir não baixa nada.
const FALTA = {
  "OS-2622": [{ insumo: "Lona 440 g 3,20 m", un: "m²", necessario: 13, disponivel: 5, caminho: [{ compra: "PC-0412", qtd: 30, chega: "2026-10-17", fornecedor: "Distribuidora Sul Mídias" }] }],
};
const faltaDe = (id) => (FALTA[id] || []).map((f) => ({ ...f, falta: Math.max(0, f.necessario - f.disponivel) })).filter((f) => f.falta > 0);
// Simulação do protótipo: a compra chega ao estoque. A OS NÃO sai sozinha de Aguardando material — o PCP confere.
const receberCompra = (id) => {
  (FALTA[id] || []).forEach((f) => { const q = f.caminho.reduce((s, c) => s + c.qtd, 0); if (q) registrar({ os: id, quem: "Estoque", tipo: "material", texto: "Recebeu a compra " + f.caminho.map((c) => c.compra).join(", ") + ": " + h1(q) + " " + f.un + " de " + f.insumo }); f.disponivel += q; f.caminho = []; });
};

// Derivadas
const etapaObj = (e) => ({ setor: e[0], nome: e[1], resp: e[2], estado: e[3], prev: e[4], real: e[5], maq: e[6] || null, trilha: e[7] || null });
const etapas = (os) => os.etapas.map(etapaObj);
const atual = (os) => etapas(os).find((e) => e.estado === "andamento") || etapas(os).find((e) => e.estado !== "feito" && e.estado !== "pronto") || etapas(os)[etapas(os).length - 1];
const progresso = (os) => { const es = etapas(os); const t = es.reduce((s, e) => s + e.prev, 0); const f = es.reduce((s, e) => s + (e.estado === "feito" || e.estado === "pronto" ? e.prev : e.estado === "andamento" ? Math.min(e.real, e.prev) : 0), 0); return t ? Math.round((f / t) * 100) : 0; };
// OS em produção = OS cuja etapa atual ainda não está pronta. Um número só: o indicador do Painel e o cabeçalho da
// página leem daqui (antes o cabeçalho mostrava o total menos 1, fixo, e divergia do indicador).
const emProducao = () => OS.filter((o) => atual(o).estado !== "pronto");
const atraso = (os) => { const d = dias(HOJE, os.prazo); return d < 0 ? "atrasada" : d === 0 ? "vence hoje" : d === 1 ? "amanhã" : null; };
const desvio = (os) => os.orcado ? ((os.real - os.orcado * progresso(os) / 100) / os.orcado) * 100 : 0;
const setorDe = (id) => SETORES.find((s) => s.id === id) || SETORES[0];
const maqDe = (id) => MAQUINAS.find((m) => m.id === id);
const osDe = (id) => OS.find((o) => o.id === id);
// OS por item: cada OS nasce de UM item da venda quando o cliente aprova. [venda, nº do item, itens que geram OS, outros itens]
const ORIGEM = { "OS-2611": ["V-8406", 1, 1, ""], "OS-2608": ["V-8398", 1, 1, ""], "OS-2617": ["V-8413", 1, 1, ""], "OS-2614": ["V-8411", 1, 1, "projeto de sinalização (serviço, sem OS)"],
  "OS-2603": ["V-8401", 1, 2, ""], "OS-2619": ["V-8401", 2, 2, ""], "OS-2598": ["V-8390", 1, 1, ""], "OS-2622": ["V-8417", 1, 1, ""] };
const origemDe = (id) => { const o = ORIGEM[id]; return o ? { venda: o[0], item: o[1], total: o[2], outros: o[3], irmas: Object.keys(ORIGEM).filter((k) => ORIGEM[k][0] === o[0]) } : null; };

// Gargalos: carga hoje por máquina (h previstas na agenda) contra a capacidade do posto (horasDia)
const cargaMaquina = (id) => AGENDA.filter((b) => b.maq === id).reduce((s, b) => s + (b.fim - b.ini), 0);

window.PCVD = { TURNO, DIAS, RETALHOS, FERRAMENTAS, EVENTOS, registrar, eventosDe, FALTA, faltaDe, receberCompra, HOJE, fmt, d2, h1, dias, SETORES, MAQUINAS, PESSOAS, ROTEIROS, OS, ORCAMENTOS, AGENDA, AGENDA_AMANHA, MATERIAIS, PERDAS, INSTALACOES, ENCAIXE,
  etapas, atual, progresso, emProducao, atraso, desvio, setorDe, maqDe, osDe, origemDe, cargaMaquina };
})();
