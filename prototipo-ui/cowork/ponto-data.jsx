// ponto-data.jsx — dados do módulo Ponto, espelhando campo-a-campo as telas Blade do main
// (Modules/Ponto/Resources/views/*) + Config/config.php + lang/pt/ponto.php.
// Nada inventado de estrutura: matrícula/PIS/CPF, estados de apuração, enums de
// intercorrência, origens de marcação, estados de importação e as regras CLT vêm do repo.
// Expõe window.PONTO.
(() => {
const MES = "2026-08";
const MES_EXTENSO = "Agosto/2026";
const HOJE = 20; // 20/08/2026
// Competências com apuração no protótipo (o vivo pagina por mês; aqui duas bastam pra navegar).
const MESES = [
  { key: "2026-07", extenso: "Julho/2026", ano: 2026, mesNum: 7, dias: 31, ate: 31 },
  { key: "2026-08", extenso: "Agosto/2026", ano: 2026, mesNum: 8, dias: 31, ate: HOJE },
];

const TIPOS_INTERC = {
  CONSULTA_MEDICA: "Consulta médica",
  ATESTADO_MEDICO: "Atestado médico",
  REUNIAO_EXTERNA: "Reunião externa",
  VISITA_CLIENTE: "Visita a cliente",
  HORA_EXTRA_AUTORIZADA: "Hora extra autorizada",
  ESQUECIMENTO_MARCACAO: "Esquecimento de marcação",
  PROBLEMA_EQUIPAMENTO: "Problema no equipamento",
  OUTRO: "Outro",
};
const ESTADOS_INTERC = {
  RASCUNHO: "Rascunho", PENDENTE: "Pendente", APROVADA: "Aprovada",
  REJEITADA: "Rejeitada", APLICADA: "Aplicada", CANCELADA: "Cancelada",
};
const ORIGENS_MARCACAO = {
  REP_P: "REP-P (equipamento)", REP_C: "REP-C (relógio)", REP_A: "REP-A (terminal)",
  AFD: "Importação AFD", AFDT: "Importação AFDT",
  MANUAL: "Lançamento manual", INTEGRACAO: "Integração API", ANULACAO: "Anulação",
};
const ESTADOS_APURACAO = ["PENDENTE", "CALCULADO", "DIVERGENCIA", "AJUSTADO", "CONSOLIDADO", "FECHADO"];
const TIPOS_ESCALA = { FIXA: "Fixa", FLEXIVEL: "Flexível", ESCALA_12X36: "12x36", ESCALA_6X1: "6x1", ESCALA_5X2: "5x2" };

// ── Escalas (ponto_escalas) ──
const ESCALAS = [
  { id: 1, codigo: "ADM-44", nome: "Administrativo 44h", tipo: "FIXA", carga_diaria_minutos: 528, carga_semanal_minutos: 2640, permite_banco_horas: true,
    turnos: [
      { dia_semana: "Segunda a quinta", entrada: "08:00", saida_almoco: "12:00", retorno_almoco: "13:00", saida: "18:00" },
      { dia_semana: "Sexta",            entrada: "08:00", saida_almoco: "12:00", retorno_almoco: "13:00", saida: "17:00" },
    ] },
  { id: 2, codigo: "PROD-5X2", nome: "Produção 5x2", tipo: "ESCALA_5X2", carga_diaria_minutos: 480, carga_semanal_minutos: 2400, permite_banco_horas: true,
    turnos: [
      { dia_semana: "Segunda a sexta", entrada: "07:00", saida_almoco: "11:30", retorno_almoco: "12:30", saida: "16:30" },
    ] },
  { id: 3, codigo: "BALC-FLEX", nome: "Balcão flexível", tipo: "FLEXIVEL", carga_diaria_minutos: 480, carga_semanal_minutos: 2400, permite_banco_horas: true,
    turnos: [
      { dia_semana: "Segunda a sexta", entrada: "09:00", saida_almoco: "13:00", retorno_almoco: "14:00", saida: "18:00" },
      { dia_semana: "Sábado (alternado)", entrada: "09:00", saida_almoco: "—", retorno_almoco: "—", saida: "13:00" },
    ] },
  { id: 4, codigo: "PLANT-1236", nome: "Plantão instalação 12x36", tipo: "ESCALA_12X36", carga_diaria_minutos: 720, carga_semanal_minutos: 2160, permite_banco_horas: false,
    turnos: [
      { dia_semana: "Escala 12x36", entrada: "07:00", saida_almoco: "12:00", retorno_almoco: "13:00", saida: "19:00" },
    ] },
];

// ── Colaboradores (ponto_colaborador_config + user do HRM) ──
const COLABORADORES = [
  { id: 1, matricula: "0001", nome: "Wagner Ramos", email: "wagner@rotalivre.com.br", cpf: "312.884.907-21", pis: "12345678901", escala_atual_id: 1, controla_ponto: false, usa_banco_horas: false, admissao: "02/01/2019", desligamento: null, user_id: 4, cargo: "Sócio-administrador" },
  { id: 2, matricula: "0007", nome: "Larissa Bueno", email: "larissa@rotalivre.com.br", cpf: "048.117.332-90", pis: "20458812334", escala_atual_id: 3, controla_ponto: true, usa_banco_horas: true, admissao: "11/03/2022", desligamento: null, user_id: 12, cargo: "Atendimento balcão" },
  { id: 3, matricula: "0011", nome: "Eliana Prado", email: "eliana@rotalivre.com.br", cpf: "701.229.884-15", pis: "13399277461", escala_atual_id: 1, controla_ponto: true, usa_banco_horas: true, admissao: "04/07/2021", desligamento: null, user_id: 15, cargo: "Financeiro" },
  { id: 4, matricula: "0014", nome: "Felipe Andrade", email: "felipe@rotalivre.com.br", cpf: "556.010.443-77", pis: "16620094587", escala_atual_id: 2, controla_ponto: true, usa_banco_horas: true, admissao: "19/09/2020", desligamento: null, user_id: 18, cargo: "Acabamento" },
  { id: 5, matricula: "0018", nome: "Joana Lima", email: "joana@rotalivre.com.br", cpf: "223.667.900-04", pis: "17722018890", escala_atual_id: 2, controla_ponto: true, usa_banco_horas: true, admissao: "05/02/2023", desligamento: null, user_id: 21, cargo: "Design / pré-impressão" },
  { id: 6, matricula: "0021", nome: "Marcos Teixeira", email: "marcos@rotalivre.com.br", cpf: "889.334.207-38", pis: "18830145622", escala_atual_id: 4, controla_ponto: true, usa_banco_horas: false, admissao: "14/06/2024", desligamento: null, user_id: 24, cargo: "Instalação externa" },
  { id: 7, matricula: "0024", nome: "Renata Coelho", email: "renata@rotalivre.com.br", cpf: "410.775.663-52", pis: "19944072215", escala_atual_id: 3, controla_ponto: true, usa_banco_horas: true, admissao: "08/01/2025", desligamento: null, user_id: 27, cargo: "Atendimento / orçamentos" },
  { id: 8, matricula: "0029", nome: "Diego Salles", email: "diego@rotalivre.com.br", cpf: "677.221.008-46", pis: null, escala_atual_id: 2, controla_ponto: true, usa_banco_horas: true, admissao: "17/02/2026", desligamento: null, user_id: 31, cargo: "Impressão digital" },
  { id: 9, matricula: "0031", nome: "Priscila Nunes", email: null, cpf: "150.998.226-73", pis: null, escala_atual_id: null, controla_ponto: false, usa_banco_horas: false, admissao: "03/03/2026", desligamento: "31/07/2026", user_id: 33, cargo: "Auxiliar administrativo" },
];

// ── Apuração diária + marcações (ponto_apuracao_dia / ponto_marcacoes) ──
// Gerador determinístico: mesma tela em todo reload, sem Math.random.
const fmtMin = (min) => {
  min = Math.trunc(min || 0);
  const sinal = min < 0 ? "−" : "";
  min = Math.abs(min);
  return sinal + String(Math.floor(min / 60)).padStart(2, "0") + ":" + String(min % 60).padStart(2, "0");
};
const DIA_SEMANA = ["Dom", "Seg", "Ter", "Qua", "Qui", "Sex", "Sáb"];
const addMin = (hhmm, delta) => {
  const [h, m] = hhmm.split(":").map(Number);
  const t = h * 60 + m + delta;
  return String(Math.floor(t / 60)).padStart(2, "0") + ":" + String(t % 60).padStart(2, "0");
};

function apuracaoDoMes(colab, comp) {
  const esc = ESCALAS.find((e) => e.id === colab.escala_atual_id) || ESCALAS[0];
  const t = esc.turnos[0];
  const carga = esc.carga_diaria_minutos;
  const dias = [];
  const hashDia = (d, i) => { let h = "", x = (colab.id * 7919 + d * 104729 + i * 31 + comp.mesNum * 7) || 1; while (h.length < 40) { x = (x * 1103515245 + 12345) % 2147483648; h += (x >>> 5).toString(16); } return h.slice(0, 40); };
  for (let d = 1; d <= comp.ate; d++) {
    const dow = new Date(comp.ano, comp.mesNum - 1, d).getDay();
    const semana = dow >= 1 && dow <= 5;
    const seed = (colab.id * 31 + d * 17 + comp.mesNum * 5) % 23;
    if (!semana) {
      dias.push({ dia: d, dow, folga: true, estado: "CALCULADO", prevista_entrada: null, prevista_saida: null,
        realizada_entrada: null, realizada_saida: null, marcacoes: [], trabalhado: 0, atraso: 0, saida_antecipada: 0,
        falta: 0, he_diurna: 0, he_noturna: 0, adicional_noturno: 0, bh_credito: 0, bh_debito: 0 });
      continue;
    }
    // 1 falta injetada, 1 divergência, alguns atrasos e horas extras.
    // Também injeta os casos que o painel de conformidade precisa demonstrar:
    // almoço curto (Art. 71), HE acima de 2h (Art. 59) e NSR fora de ordem (Anexo I).
    const falta = seed === 3;
    const divergencia = seed === 7;
    const almocoCurto = seed === 5;
    const heEstourada = seed === 9;
    const nsrFora = seed === 11;
    const atraso = seed % 11 === 1 ? 8 + seed % 5 : seed % 7 === 4 ? 3 : 0;
    const he = heEstourada ? 150 : seed % 9 === 2 ? 45 + seed % 30 : seed % 13 === 5 ? 20 : 0;
    const entrada = falta ? null : addMin(t.entrada, atraso);
    const saida = falta ? null : addMin(t.saida, he);
    const retorno = almocoCurto ? addMin(t.saida_almoco, 35) : t.retorno_almoco;
    const trabalhado = falta ? 0 : carga - atraso + he + (almocoCurto ? 25 : 0);
    const nsr0 = comp.mesNum * 40000 + d * 4 + colab.id;
    const mk = (hora, origem, i, nsrDelta) => ({ hora, origem, nsr: nsr0 + i + (nsrDelta || 0), hash: hashDia(d, i), rep: origem === "REP_P" ? "20250320114500042" : origem === "MANUAL" ? null : "20240115083012001" });
    const marcacoes = falta ? []
      : divergencia
        ? [mk(entrada, "REP_P", 0), mk(t.saida_almoco, "REP_P", 1)]
        : [mk(entrada, "REP_C", 0), mk(t.saida_almoco, "REP_C", 1), mk(retorno, seed % 8 === 6 ? "MANUAL" : "REP_C", 2, nsrFora ? -9 : 0), mk(saida, "REP_C", 3)];
    const fechado = comp.key !== MES;
    dias.push({
      dia: d, dow, folga: false,
      estado: fechado ? (falta || divergencia ? "AJUSTADO" : "FECHADO") : falta || divergencia ? "DIVERGENCIA" : d < 16 ? "CONSOLIDADO" : "CALCULADO",
      prevista_entrada: t.entrada, prevista_saida: t.saida,
      realizada_entrada: entrada, realizada_saida: divergencia ? null : saida,
      marcacoes,
      trabalhado: divergencia ? Math.round(carga / 2) : trabalhado,
      atraso, saida_antecipada: 0,
      falta: falta ? carga : 0,
      he_diurna: he, he_noturna: 0, adicional_noturno: 0,
      bh_credito: colab.usa_banco_horas ? he : 0,
      bh_debito: colab.usa_banco_horas ? atraso + (falta ? carga : 0) : 0,
    });
  }
  return dias;
}

// APURACOES[mesKey][colaboradorId] — o mês corrente também fica em APURACOES_MES_ATUAL.
const APURACOES = {};
MESES.forEach((comp) => {
  APURACOES[comp.key] = {};
  COLABORADORES.forEach((c) => { APURACOES[comp.key][c.id] = apuracaoDoMes(c, comp); });
});

function totaisEspelho(dias) {
  const t = { trabalhado: 0, atraso: 0, saida_antecipada: 0, falta: 0, he_diurna: 0, he_noturna: 0, adicional_noturno: 0, bh_credito: 0, bh_debito: 0, divergencias: 0 };
  dias.forEach((d) => {
    t.trabalhado += d.trabalhado; t.atraso += d.atraso; t.saida_antecipada += d.saida_antecipada;
    t.falta += d.falta; t.he_diurna += d.he_diurna; t.he_noturna += d.he_noturna;
    t.adicional_noturno += d.adicional_noturno; t.bh_credito += d.bh_credito; t.bh_debito += d.bh_debito;
    if (d.estado === "DIVERGENCIA") t.divergencias++;
  });
  return t;
}

// ── Intercorrências (ponto_intercorrencias) ──
const INTERCORRENCIAS = [
  { id: "a1f3c8d2", codigo: "INT-2026-0148", colaborador_config_id: 2, tipo: "ESQUECIMENTO_MARCACAO", data: "18/08/2026", dia_todo: false, intervalo_inicio: "13:00", intervalo_fim: "14:00", estado: "PENDENTE", prioridade: "URGENTE", impacta_apuracao: true, descontar_banco_horas: false, justificativa: "Retorno do almoço não registrou no REP — o leitor biométrico ficou piscando vermelho e o atendimento estava com fila. Marcação confirmada pela câmera da recepção às 14:02.", anexo_path: null, solicitante: "Larissa Bueno", created_at: "18/08/2026 15:12", aprovador: null, aprovado_em: null, motivo_rejeicao: null },
  { id: "b7e0192a", codigo: "INT-2026-0147", colaborador_config_id: 4, tipo: "HORA_EXTRA_AUTORIZADA", data: "17/08/2026", dia_todo: false, intervalo_inicio: "18:00", intervalo_fim: "21:30", estado: "PENDENTE", prioridade: "NORMAL", impacta_apuracao: true, descontar_banco_horas: false, justificativa: "Instalação da fachada do Posto BR precisava sair na madrugada de terça. Autorizado por Wagner no grupo da produção.", anexo_path: null, solicitante: "Felipe Andrade", created_at: "17/08/2026 21:44", aprovador: null, aprovado_em: null, motivo_rejeicao: null },
  { id: "c2b41f77", codigo: "INT-2026-0146", colaborador_config_id: 3, tipo: "CONSULTA_MEDICA", data: "14/08/2026", dia_todo: false, intervalo_inicio: "09:00", intervalo_fim: "11:30", estado: "APROVADA", prioridade: "NORMAL", impacta_apuracao: true, descontar_banco_horas: false, justificativa: "Consulta de rotina no cardiologista, com encaminhamento do convênio. Comprovante anexado.", anexo_path: "intercorrencias/2026/08/comprovante-eliana.pdf", solicitante: "Eliana Prado", created_at: "12/08/2026 08:31", aprovador: "Wagner Ramos", aprovado_em: "13/08/2026 09:05", motivo_rejeicao: null },
  { id: "d9a55e03", codigo: "INT-2026-0145", colaborador_config_id: 6, tipo: "PROBLEMA_EQUIPAMENTO", data: "13/08/2026", dia_todo: true, intervalo_inicio: null, intervalo_fim: null, estado: "APLICADA", prioridade: "NORMAL", impacta_apuracao: true, descontar_banco_horas: false, justificativa: "Dia inteiro em obra externa (Mercado União) — REP-P do celular sem sinal no galpão, nenhuma das quatro marcações subiu.", anexo_path: null, solicitante: "Marcos Teixeira", created_at: "13/08/2026 18:20", aprovador: "Wagner Ramos", aprovado_em: "14/08/2026 08:12", motivo_rejeicao: null },
  { id: "e4c7b810", codigo: "INT-2026-0144", colaborador_config_id: 5, tipo: "VISITA_CLIENTE", data: "12/08/2026", dia_todo: false, intervalo_inicio: "14:00", intervalo_fim: "17:00", estado: "APLICADA", prioridade: "NORMAL", impacta_apuracao: false, descontar_banco_horas: false, justificativa: "Levantamento de medidas na loja da Acme para o projeto de fachada — saída direto do escritório.", anexo_path: null, solicitante: "Joana Lima", created_at: "11/08/2026 17:02", aprovador: "Wagner Ramos", aprovado_em: "12/08/2026 08:44", motivo_rejeicao: null },
  { id: "f1d2033c", codigo: "INT-2026-0143", colaborador_config_id: 8, tipo: "ATESTADO_MEDICO", data: "11/08/2026", dia_todo: true, intervalo_inicio: null, intervalo_fim: null, estado: "REJEITADA", prioridade: "NORMAL", impacta_apuracao: true, descontar_banco_horas: true, justificativa: "Atestado de 1 dia por dor lombar.", anexo_path: null, solicitante: "Diego Salles", created_at: "11/08/2026 07:58", aprovador: "Wagner Ramos", aprovado_em: "11/08/2026 10:30", motivo_rejeicao: "Atestado não anexado — reenviar com o documento legível (CID e CRM do médico) para reprocessar o dia." },
  { id: "0a8f61bd", codigo: "INT-2026-0142", colaborador_config_id: 7, tipo: "REUNIAO_EXTERNA", data: "07/08/2026", dia_todo: false, intervalo_inicio: "10:00", intervalo_fim: "12:00", estado: "APLICADA", prioridade: "NORMAL", impacta_apuracao: false, descontar_banco_horas: false, justificativa: "Reunião no sindicato do comércio sobre a tabela de preços de comunicação visual.", anexo_path: null, solicitante: "Renata Coelho", created_at: "06/08/2026 16:40", aprovador: "Wagner Ramos", aprovado_em: "07/08/2026 08:10", motivo_rejeicao: null },
  { id: "1b9c72de", codigo: null, colaborador_config_id: 2, tipo: "OUTRO", data: "19/08/2026", dia_todo: false, intervalo_inicio: "16:00", intervalo_fim: "18:00", estado: "RASCUNHO", prioridade: "NORMAL", impacta_apuracao: true, descontar_banco_horas: true, justificativa: "Saída antecipada para resolver matrícula escolar — vou compensar na sexta.", anexo_path: null, solicitante: "Larissa Bueno", created_at: "19/08/2026 11:25", aprovador: null, aprovado_em: null, motivo_rejeicao: null },
];

// ── Banco de horas (ponto_banco_horas: saldo + movimentos) ──
const BH_SALDOS = [
  { colaborador_config_id: 2, saldo_minutos: 412, updated_at: "19/08/2026 18:04" },
  { colaborador_config_id: 3, saldo_minutos: 95, updated_at: "19/08/2026 18:04" },
  { colaborador_config_id: 4, saldo_minutos: 1268, updated_at: "19/08/2026 21:30" },
  { colaborador_config_id: 5, saldo_minutos: -180, updated_at: "18/08/2026 17:12" },
  { colaborador_config_id: 7, saldo_minutos: -46, updated_at: "15/08/2026 18:00" },
  { colaborador_config_id: 8, saldo_minutos: 0, updated_at: "11/08/2026 09:40" },
];
const BH_MOVIMENTOS = {
  4: [
    { created_at: "19/08/2026 21:30", data_referencia: "17/08/2026", origem: "APURACAO", minutos: 210, observacao: "HE noturna convertida — instalação Posto BR" },
    { created_at: "14/08/2026 18:02", data_referencia: "14/08/2026", origem: "APURACAO", minutos: 65, observacao: "Excedente de jornada" },
    { created_at: "08/08/2026 09:12", data_referencia: "07/08/2026", origem: "AJUSTE_MANUAL", minutos: -120, observacao: "Compensação de saída antecipada acordada com o setor" },
    { created_at: "05/08/2026 18:30", data_referencia: "05/08/2026", origem: "APURACAO", minutos: 48, observacao: "Excedente de jornada" },
    { created_at: "31/07/2026 23:59", data_referencia: "31/07/2026", origem: "FECHAMENTO", minutos: 1065, observacao: "Saldo transportado do fechamento de jul/26" },
  ],
  2: [
    { created_at: "19/08/2026 18:04", data_referencia: "18/08/2026", origem: "APURACAO", minutos: 32, observacao: "Excedente de jornada" },
    { created_at: "12/08/2026 18:10", data_referencia: "12/08/2026", origem: "APURACAO", minutos: -20, observacao: "Atraso além da tolerância de 5 min" },
    { created_at: "31/07/2026 23:59", data_referencia: "31/07/2026", origem: "FECHAMENTO", minutos: 400, observacao: "Saldo transportado do fechamento de jul/26" },
  ],
  5: [
    { created_at: "18/08/2026 17:12", data_referencia: "18/08/2026", origem: "AJUSTE_MANUAL", minutos: -180, observacao: "Folga de meio período concedida (ponte do feriado)" },
  ],
};

// ── Importações AFD (ponto_importacoes) ──
const IMPORTACOES = [
  { id: 42, nome_arquivo: "AFD_00000000000191_20260819.txt", tipo: "AFD", tamanho_bytes: 1487321, estado: "CONCLUIDA", usuario: "Wagner Ramos", created_at: "19/08/2026 19:40", iniciado_em: "19/08/2026 19:40", concluido_em: "19/08/2026 19:43", hash_arquivo: "8f434346648f6b96df89dda901c5176b10a6d83961dd3c1ac88b59b2dc327aa4", linhas_total: 4820, linhas_processadas: 4820, linhas_sucesso: 4818, linhas_erro: 2, log: "Cabeçalho tipo 1 validado (CNPJ 00.000.000/0001-91).\nRegistros tipo 3 (marcação): 4.812\nRegistros tipo 7 (marcação REP-P): 6\nTrailer tipo 9 conferido: contagem OK.\n2 registros ignorados — PIS não cadastrado.", erros_amostra: [ { linha: 2314, nsr: "0000921", tipo: "3", erro: "PIS 20458812999 não cadastrado em ponto_colaborador_config" }, { linha: 3902, nsr: "0001588", tipo: "3", erro: "PIS 20458812999 não cadastrado em ponto_colaborador_config" } ] },
  { id: 41, nome_arquivo: "AFDT_00000000000191_20260810.txt", tipo: "AFDT", tamanho_bytes: 512044, estado: "CONCLUIDA_COM_ERROS", usuario: "Eliana Prado", created_at: "10/08/2026 08:22", iniciado_em: "10/08/2026 08:22", concluido_em: "10/08/2026 08:26", hash_arquivo: "1cbb0e6a5b0d0f4d9ba0a72e5b1f0e2c2a3d5e6f70819293a4b5c6d7e8f90112", linhas_total: 1960, linhas_processadas: 1960, linhas_sucesso: 1802, linhas_erro: 158, log: "Arquivo tratado (AFDT) aceito.\n158 linhas com jornada divergente da escala vigente — apuração marcada como DIVERGENCIA para conferência manual.", erros_amostra: [ { linha: 120, nsr: "0000044", tipo: "4", erro: "Jornada informada (12:00) divergente da escala ADM-44 (08:48)" }, { linha: 340, nsr: "0000131", tipo: "4", erro: "Colaborador sem escala vigente em 03/08/2026" }, { linha: 512, nsr: "0000208", tipo: "4", erro: "Intervalo intrajornada menor que 60 min (Art. 71 CLT)" } ] },
  { id: 40, nome_arquivo: "AFD_00000000000191_20260801.txt", tipo: "AFD", tamanho_bytes: 1502877, estado: "PROCESSANDO", usuario: "Wagner Ramos", created_at: "20/08/2026 08:05", iniciado_em: "20/08/2026 08:05", concluido_em: null, hash_arquivo: "b4c1d2e3f405162738495a6b7c8d9e0f1a2b3c4d5e6f708192a3b4c5d6e7f809", linhas_total: 4900, linhas_processadas: 2360, linhas_sucesso: 2360, linhas_erro: 0, log: "Enfileirado em ProcessarImportacaoAfdJob — chunk de 1.000 linhas.", erros_amostra: [] },
  { id: 39, nome_arquivo: "rep-c-recepcao-julho.txt", tipo: "AFD", tamanho_bytes: 88110, estado: "FALHOU", usuario: "Eliana Prado", created_at: "02/08/2026 14:18", iniciado_em: "02/08/2026 14:18", concluido_em: "02/08/2026 14:18", hash_arquivo: "5e6f708192a3b4c5d6e7f8091a2b3c4d5e6f708192a3b4c5d6e7f8091a2b3c4d", linhas_total: 0, linhas_processadas: 0, linhas_sucesso: 0, linhas_erro: 0, log: "Cabeçalho fora do layout da Portaria 671/2021 Anexo I: campo 2 esperado tipo 1, encontrado 'REP'.\nEncoding detectado UTF-8 (esperado ISO-8859-1).\nNenhuma linha processada — arquivo rejeitado antes do chunk 1.", erros_amostra: [] },
  { id: 38, nome_arquivo: "AFD_00000000000191_20260701.txt", tipo: "AFD", tamanho_bytes: 1440902, estado: "CONCLUIDA", usuario: "Wagner Ramos", created_at: "01/07/2026 07:55", iniciado_em: "01/07/2026 07:55", concluido_em: "01/07/2026 07:58", hash_arquivo: "aa11bb22cc33dd44ee55ff6677889900aabbccddeeff00112233445566778899", linhas_total: 4655, linhas_processadas: 4655, linhas_sucesso: 4655, linhas_erro: 0, log: "Cabeçalho tipo 1 validado. Trailer conferido. Nenhum erro.", erros_amostra: [] },
];

// ── REPs cadastrados (ponto_reps) ──
const REPS = [
  { id: 1, tipo: "REP_C", identificador: "20240115083012001", descricao: "Relógio da recepção (biométrico)", local: "Recepção matriz", cnpj: "00000000000191" },
  { id: 2, tipo: "REP_P", identificador: "20250320114500042", descricao: "App do técnico — marcação em obra", local: "Externo / campo", cnpj: "00000000000191" },
  { id: 3, tipo: "REP_A", identificador: "20260210090015117", descricao: "Terminal do galpão de produção", local: "Galpão — corte e acabamento", cnpj: "00000000000191" },
];

// ── Catálogo de relatórios (RelatorioController@index — inclui a flag `disponivel`) ──
const RELATORIOS = [
  { chave: "afd", titulo: "AFD (Portaria 671/2021)", descricao: "Arquivo Fonte de Dados", icone: "receipt", disponivel: false },
  { chave: "afdt", titulo: "AFDT", descricao: "Arquivo Fonte de Dados Tratados", icone: "receipt", disponivel: false },
  { chave: "aej", titulo: "AEJ", descricao: "Apuração Eletrônica de Jornada", icone: "list", disponivel: false },
  { chave: "espelho", titulo: "Espelho de Ponto", descricao: "PDF mensal por colaborador", icone: "calendar", disponivel: true },
  { chave: "he", titulo: "Horas Extras", descricao: "Relatório consolidado do mês", icone: "clock", disponivel: false },
  { chave: "banco-horas", titulo: "Banco de Horas", descricao: "Saldos e movimentações", icone: "coins", disponivel: false },
  { chave: "atrasos", titulo: "Atrasos e Faltas", descricao: "Por colaborador/departamento", icone: "alert", disponivel: false },
  { chave: "esocial", titulo: "Eventos eSocial", descricao: "S-1010 / S-2230 / S-2240", icone: "send", disponivel: false },
];

// ── Configurações (Modules/Ponto/Config/config.php — somente leitura na UI) ──
const CONFIG = {
  clt: { tolerancia_minutos_por_marcacao: 5, tolerancia_maxima_diaria_minutos: 10, interjornada_minima_horas: 11, intrajornada_minima_minutos: 60, hora_noturna_ficta_segundos: 3150, adicional_noturno_percentual: 20, limite_he_diaria_horas: 2, adicional_he_percentual: 50, adicional_dsr_percentual: 100 },
  banco_horas: { habilitado: true, prazo_compensacao_meses: 6, saldo_maximo_horas: 200, saldo_minimo_horas: -40, multiplicador_credito: 1.0, multiplicador_debito: 1.0, converter_he_em_bh_default: true },
  rep: { tipos_permitidos: ["REP_P", "REP_C", "REP_A"], nsr_verificar_sequencia: true, assinar_marcacoes: true, certificado_icp_path: null },
  afd: { encoding: "ISO-8859-1", max_filesize_mb: 50, chunk_size_linhas: 1000, validar_hash_registros: true },
  marcacao: { janela_correcao_minutos: 5, forcar_append_only: true, hash_algoritmo: "sha256" },
  esocial: { ambiente: "homologacao", eventos: ["S-1010", "S-2230", "S-2240"], tp_amb: 2 },
  ai: { enabled: false, classificacao_intercorrencia: false, explicacao_divergencia: false, geracao_justificativa: false, model: "gpt-4o-mini" },
};

// ── Atividade recente (últimas marcações do dia) ──
const ATIVIDADE = [
  { hora: "13:02", nome: "Renata Coelho", tipo: "RETORNO_ALMOCO", nsr: 102918, origem: "REP_C" },
  { hora: "12:58", nome: "Diego Salles", tipo: "RETORNO_ALMOCO", nsr: 102917, origem: "REP_C" },
  { hora: "12:04", nome: "Larissa Bueno", tipo: "SAIDA_ALMOCO", nsr: 102916, origem: "REP_C" },
  { hora: "11:31", nome: "Felipe Andrade", tipo: "SAIDA_ALMOCO", nsr: 102915, origem: "REP_A" },
  { hora: "09:12", nome: "Marcos Teixeira", tipo: "ENTRADA", nsr: 102914, origem: "REP_P" },
  { hora: "08:47", nome: "Eliana Prado", tipo: "ENTRADA", nsr: 102913, origem: "REP_C" },
  { hora: "08:03", nome: "Larissa Bueno", tipo: "ENTRADA", nsr: 102912, origem: "REP_C" },
];
const TIPOS_MARCACAO = { ENTRADA: "Entrada", SAIDA_ALMOCO: "Saída para almoço", RETORNO_ALMOCO: "Retorno do almoço", SAIDA: "Saída", INTERVALO: "Intervalo", RETORNO_INTERVALO: "Retorno do intervalo" };

// ── KPIs do dashboard (DashboardController@index) ──
const ativos = COLABORADORES.filter((c) => c.controla_ponto && !c.desligamento);
const KPIS = {
  colaboradores_ativos: ativos.length,
  presentes_agora: 5,
  atrasos_hoje: 2,
  faltas_hoje: 1,
  he_mes_minutos: COLABORADORES.reduce((s, c) => s + totaisEspelho(APURACOES[MES][c.id]).he_diurna, 0),
  aprovacoes_pendentes: INTERCORRENCIAS.filter((i) => i.estado === "PENDENTE").length,
};

window.PONTO = {
  MES, MES_EXTENSO, HOJE, MESES, DIA_SEMANA,
  TIPOS_INTERC, ESTADOS_INTERC, ORIGENS_MARCACAO, ESTADOS_APURACAO, TIPOS_ESCALA, TIPOS_MARCACAO,
  ESCALAS, COLABORADORES, APURACOES, INTERCORRENCIAS, BH_SALDOS, BH_MOVIMENTOS,
  IMPORTACOES, REPS, RELATORIOS, CONFIG, ATIVIDADE, KPIS,
  fmtMin, totaisEspelho,
  dias: (mes, id) => (APURACOES[mes] || APURACOES[MES])[id] || [],
  comp: (mes) => MESES.find((m) => m.key === mes) || MESES[MESES.length - 1],
  colab: (id) => COLABORADORES.find((c) => c.id === id) || null,
  escala: (id) => ESCALAS.find((e) => e.id === id) || null,
};
})();
