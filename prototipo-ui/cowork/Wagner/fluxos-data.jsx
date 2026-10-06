// fluxos-data.jsx — catálogo de modelos de fluxo (FSM por porte × atividade) e casos de uso
// levantados nos concorrentes. Fonte da pesquisa: memory/research/2026-05-prospeccao/
// 02-concorrentes-zenite-mubisys.md + 33-grade-producao-concorrentes.md (lidos no main em 2026-10-06)
// + páginas públicas da Holdprint (holdprint.net). É o que cada fornecedor ANUNCIA; nada foi testado.
// Expõe window.FLX.
(() => {
const FONTES = {
  mubisys:    { n: "Mubisys",    url: "https://mubisys.com/producao" },
  holdprint:  { n: "Holdprint",  url: "https://www.holdprint.net/" },
  zenite:     { n: "Zênite",     url: "https://www.zsl.com.br/" },
  calcgraf:   { n: "Calcgraf",   url: "https://www.calcgraf.com.br/solucao/pcp/" },
  visua:      { n: "Visua",      url: "https://www.visua.com.br/" },
  calcme:     { n: "Calcme",     url: "https://www.calcme.com.br/funcionalidades/" },
  shopvox:    { n: "shopVOX",    url: "https://shopvox.com/features/job-management/" },
  corebridge: { n: "Corebridge", url: "https://www.corebridge.net/production-management" },
  cyrious:    { n: "Cyrious",    url: "https://www.cyrious.com/products/control/" },
  printiq:    { n: "printIQ",    url: "https://www.printiq.com/products/core/" },
  oimpresso:  { n: "oimpresso",  url: "" },
};

const PORTES = [
  { v: "micro",   l: "Micro",   d: "MEI e micro · até 5 pessoas" },
  { v: "pequena", l: "Pequena", d: "6 a 20 pessoas" },
  { v: "media",   l: "Média",   d: "21 a 100 pessoas" },
  { v: "grande",  l: "Grande",  d: "mais de 100 pessoas" },
];
const ORDEM_PORTE = { micro: 0, pequena: 1, media: 2, grande: 3 };

const ATIVIDADES = [
  { v: "cv",      l: "Comunicação visual" },
  { v: "rapida",  l: "Gráfica rápida e digital" },
  { v: "offset",  l: "Gráfica offset" },
  { v: "brindes", l: "Brindes e sublimação" },
  { v: "textil",  l: "Têxtil e DTF" },
  { v: "instal",  l: "Fachadas e instalação" },
  { v: "oficina", l: "Oficina mecânica" },
];

const PROCESSOS = { venda: "Venda", producao: "Produção", compra: "Compra", entrega: "Entrega", financeiro: "Financeiro" };

// estado no oimpresso — mesma régua do doc 33 (planejado = US escrita sem código).
const ESTADOS = {
  producao:  { l: "em produção",     tone: "ok" },
  prototipo: { l: "no protótipo",    tone: "accent" },
  parcial:   { l: "parcial",         tone: "warn" },
  modulo:    { l: "em outro módulo", tone: "warn" },
  planejado: { l: "planejado",       tone: "mute" },
  sem:       { l: "sem US",          tone: "danger" },
  verificar: { l: "não verificado",  tone: "mute" },
};

// Caso de uso = transição + efeito. "regra" está escrita no formato da FSM: ao entrar em <etapa> → efeito.
const CASOS = [
  { id: "UC-FLX-01", t: "Orçamento aprovado vira OS sem redigitar", proc: "venda", etapa: "Aprovado", regra: "Cria a OS com itens, medidas e arquivos do orçamento", fontes: ["mubisys", "shopvox", "printiq", "calcme"], porte: "micro", estado: "planejado", us: "US-COMVIS-003" },
  { id: "UC-FLX-02", t: "Cliente aprova a arte por link", proc: "producao", etapa: "Aprovação do cliente", regra: "Envia link da prova; aprovado avança para Impressão, recusado volta para Arte com o comentário", fontes: ["holdprint", "shopvox", "corebridge", "cyrious", "printiq"], porte: "pequena", estado: "planejado", us: "US-COMVIS-NEW-004" },
  { id: "UC-FLX-03", t: "Orçamento calcula custo, material e lucro sozinho", proc: "venda", etapa: "Orçamento", regra: "Ao salvar, recalcula o custo pela receita e mostra a margem", fontes: ["mubisys", "holdprint"], porte: "micro", estado: "parcial", us: "calculadora de m²" },
  { id: "UC-FLX-04", t: "Termômetro de acesso da proposta", proc: "venda", etapa: "Enviado", regra: "Registra quando o cliente abre; sem abertura em 48 h cria tarefa de retorno", fontes: ["mubisys"], porte: "pequena", estado: "sem", us: "" },
  { id: "UC-FLX-05", t: "Comissão do vendedor nasce no faturamento", proc: "venda", etapa: "Faturado", regra: "Lança a comissão do vendedor sobre o valor faturado", fontes: ["mubisys"], porte: "pequena", estado: "sem", us: "" },
  { id: "UC-FLX-06", t: "Prazo de entrega calculado pela fila", proc: "venda", etapa: "Aprovado", regra: "Calcula a data de entrega pela capacidade livre de cada etapa", fontes: ["holdprint", "calcgraf"], porte: "media", estado: "planejado", us: "SPEC do PCP" },
  { id: "UC-FLX-07", t: "Etapas de produção vêm do cadastro do produto", proc: "producao", etapa: "OS criada", regra: "Copia o roteiro de etapas do produto para a OS", fontes: ["shopvox"], porte: "pequena", estado: "planejado", us: "SPEC do PCP" },
  { id: "UC-FLX-08", t: "Cada etapa tem setor e responsável", proc: "producao", etapa: "qualquer etapa", regra: "Atribui ao responsável do setor e conta o tempo parado", fontes: ["mubisys", "corebridge", "calcme", "cyrious"], porte: "pequena", estado: "modulo", us: "Repair / Oficina" },
  { id: "UC-FLX-09", t: "Apontamento pelo celular com QR Code", proc: "producao", etapa: "Iniciada / Concluída", regra: "A leitura do QR da OS inicia ou conclui a etapa e grava o tempo", fontes: ["mubisys", "printiq", "shopvox", "cyrious"], porte: "pequena", estado: "parcial", us: "US-COMVIS-004" },
  { id: "UC-FLX-10", t: "Alocador decide quem e quando começa", proc: "producao", etapa: "Liberada", regra: "Escolhe operador e horário para não parar máquina nem faltar material", fontes: ["holdprint"], porte: "media", estado: "planejado", us: "SPEC do PCP (parado, ADR 0152)" },
  { id: "UC-FLX-11", t: "Dados da máquina entram sem digitação", proc: "producao", etapa: "Impressão", regra: "Fecha a etapa com metragem e tempo lidos do equipamento", fontes: ["zenite", "calcgraf"], porte: "grande", estado: "planejado", us: "US-COMVIS-004" },
  { id: "UC-FLX-12", t: "Romaneio de impressão peça a peça", proc: "producao", etapa: "Pré-impressão → Impressão", regra: "Gera a lista do que foi enviado ao setor de impressão e corte", fontes: ["mubisys"], porte: "pequena", estado: "sem", us: "" },
  { id: "UC-FLX-13", t: "Simulador de aproveitamento de mídia", proc: "producao", etapa: "Pré-impressão", regra: "Agrupa os jobs na bobina para reduzir a perda de mídia", fontes: ["holdprint", "printiq", "calcgraf"], porte: "media", estado: "sem", us: "" },
  { id: "UC-FLX-14", t: "Produção baixa o material consumido", proc: "producao", etapa: "OP concluída", regra: "Consome do estoque a quantidade real, não a orçada", fontes: ["mubisys", "calcgraf", "cyrious"], porte: "micro", estado: "prototipo", us: "Fabricação" },
  { id: "UC-FLX-15", t: "Previsto × realizado por OS", proc: "producao", etapa: "Entregue", regra: "Congela o custo real e compara com o orçado e o aprovado", fontes: ["holdprint", "calcgraf", "shopvox", "corebridge", "printiq"], porte: "pequena", estado: "planejado", us: "US-COMVIS-005" },
  { id: "UC-FLX-16", t: "Refugo e retrabalho com motivo", proc: "producao", etapa: "Reprovada no acabamento", regra: "Abre retrabalho na etapa de origem e soma o custo à OS", fontes: ["calcgraf", "zenite"], porte: "pequena", estado: "planejado", us: "SPEC do PCP" },
  { id: "UC-FLX-17", t: "Compra recebida atualiza custo e propõe preço", proc: "compra", etapa: "Recebido", regra: "Recalcula o custo dos insumos (médio ou último) e propõe preço nos produtos que acompanham o custo", fontes: ["mubisys", "holdprint", "calcgraf"], porte: "micro", estado: "prototipo", us: "Compras · regras das etapas" },
  { id: "UC-FLX-18", t: "Estoque fracionado com rastreio da mídia", proc: "compra", etapa: "Recebido", regra: "Abre o rolo como lote e registra em que OS cada fração foi usada", fontes: ["mubisys"], porte: "pequena", estado: "sem", us: "" },
  { id: "UC-FLX-19", t: "Estoque mínimo vira requisição de compra", proc: "compra", etapa: "Abaixo do mínimo", regra: "Cria a requisição com o fornecedor da última compra", fontes: ["mubisys"], porte: "micro", estado: "planejado", us: "Compras · requisições" },
  { id: "UC-FLX-20", t: "Custeio RKW e ponto de equilíbrio", proc: "financeiro", etapa: "Fechamento do mês", regra: "Rateia despesas fixas por centro de custo e atualiza o custo-hora", fontes: ["holdprint", "zenite"], porte: "media", estado: "sem", us: "" },
  { id: "UC-FLX-21", t: "Agenda da equipe de instalação", proc: "entrega", etapa: "Pronto", regra: "Agenda equipe e veículo; bloqueia se não houver janela", fontes: ["shopvox", "corebridge", "visua", "calcme", "cyrious"], porte: "pequena", estado: "planejado", us: "US-COMVIS-007" },
  { id: "UC-FLX-22", t: "Instalação com foto e assinatura", proc: "entrega", etapa: "Instalado", regra: "Só conclui com foto do serviço e assinatura do cliente", fontes: ["shopvox", "printiq"], porte: "pequena", estado: "modulo", us: "Oficina (fotos)" },
  { id: "UC-FLX-23", t: "Protocolo de entrega", proc: "entrega", etapa: "Entregue", regra: "Gera o protocolo com itens, volumes e quem recebeu", fontes: ["mubisys", "corebridge", "cyrious"], porte: "micro", estado: "planejado", us: "US-COMVIS-007" },
  { id: "UC-FLX-24", t: "Cliente acompanha o status", proc: "entrega", etapa: "cada transição", regra: "Avisa o cliente e atualiza o portal de acompanhamento", fontes: ["calcgraf", "corebridge", "shopvox", "printiq"], porte: "pequena", estado: "modulo", us: "Repair (portal)" },
  { id: "UC-FLX-25", t: "Pesquisa de satisfação depois da entrega", proc: "entrega", etapa: "Entregue + 2 dias", regra: "Envia a pesquisa; nota baixa abre atendimento", fontes: ["mubisys"], porte: "pequena", estado: "sem", us: "" },
  { id: "UC-FLX-26", t: "Boleto pago emite a NF-e", proc: "financeiro", etapa: "Pago", regra: "Emite a NF-e sem ninguém clicar", fontes: ["oimpresso"], porte: "micro", estado: "producao", us: "US-RB-044" },
  { id: "UC-FLX-28", t: "Produção só libera com o sinal pago", proc: "producao", etapa: "Aguardando liberação → Na fila", regra: "Guarda: sinal do orçamento pago; o retorno do banco libera a OS sozinho", fontes: ["oimpresso"], porte: "micro", estado: "verificar", us: "" },
  { id: "UC-FLX-29", t: "Régua de cobrança do título vencido", proc: "financeiro", etapa: "Vencido → Em cobrança", regra: "Lembretes em D+1, D+5 e D+15 e tarefa ao vendedor", fontes: ["oimpresso"], porte: "micro", estado: "verificar", us: "" },
  { id: "UC-FLX-30", t: "ISS retido ajusta o valor do título", proc: "financeiro", etapa: "NFS-e Autorizada", regra: "O título passa a cobrar o valor líquido das retenções", fontes: ["oimpresso"], porte: "pequena", estado: "verificar", us: "" },
  { id: "UC-FLX-31", t: "Natureza fiscal do item decide a nota", proc: "financeiro", etapa: "Faturado", regra: "Serviço gráfico sob encomenda → NFS-e · revenda → NF-e · instalação → NFS-e", fontes: ["oimpresso"], porte: "micro", estado: "verificar", us: "Súmula 156 STJ" },
  { id: "UC-FLX-32", t: "NF-e em contingência quando a SEFAZ cai", proc: "financeiro", etapa: "Validada → Transmitida", regra: "Emite em SVC/EPEC e transmite quando a SEFAZ voltar", fontes: ["oimpresso"], porte: "pequena", estado: "verificar", us: "" },
  { id: "UC-FLX-27", t: "Uma OS, duas notas (NF-e + NFS-e)", proc: "financeiro", etapa: "Faturado", regra: "Emite NF-e da mercadoria e NFS-e do serviço na mesma OS", fontes: ["oimpresso"], porte: "pequena", estado: "planejado", us: "ADR 0129 · US-NFE-060" },
];

// Modelos: processos (etapas + regras por etapa) + casos cobertos + parâmetros da regra de custo da compra
// (aplicados em window.OiFsmRegras quando o modelo é escolhido).
const MODELOS = [
  { id: "balcao", n: "Balcão enxuto", para: "Quem orça, imprime e entrega no mesmo dia, com poucas pessoas.",
    portes: ["micro"], ativs: ["cv", "rapida", "brindes", "textil"], insp: ["mubisys"],
    processos: [
      { dom: "venda", etapas: ["Orçamento", "Aprovado", "Pronto", "Entregue", "Pago"], regras: [["Aprovado", "Baixa o material do estoque", "UC-FLX-14"], ["Pago", "Emite a NF-e sozinho", "UC-FLX-26"]] },
      { dom: "compra", etapas: ["Pedido", "Recebido", "Pago"], regras: [["Recebido", "Custo = último preço; preço de venda não muda", "UC-FLX-17"]] },
    ],
    casos: ["UC-FLX-01", "UC-FLX-03", "UC-FLX-14", "UC-FLX-17", "UC-FLX-19", "UC-FLX-23", "UC-FLX-26"],
    compra: { etapa: "recebido", metodo: "ultimo", preco: "so_custo", regraEm: "produto", aprovacao: "recebimento", ops: "congela" } },
  { id: "cv-pequena", n: "Comunicação visual · pequena", para: "Produção por OS com arte, aprovação do cliente e instalação própria.",
    portes: ["pequena"], ativs: ["cv", "instal"], insp: ["mubisys", "holdprint", "shopvox"],
    processos: [
      { dom: "venda", etapas: ["Orçamento", "Aprovado", "Faturado", "Entregue", "Pago"], regras: [["Aprovado", "Cria a OS com itens e arquivos", "UC-FLX-01"], ["Faturado", "NF-e da mercadoria + NFS-e do serviço", "UC-FLX-27"]] },
      { dom: "producao", etapas: ["Arte", "Aprovação do cliente", "Impressão", "Acabamento", "Instalação", "Entregue"], regras: [["Aprovação do cliente", "Link de aprovação; recusa volta para Arte", "UC-FLX-02"], ["Impressão", "Apontamento pelo celular", "UC-FLX-09"], ["Entregue", "Protocolo + pesquisa em 2 dias", "UC-FLX-23"]] },
      { dom: "compra", etapas: ["Pedido", "Em trânsito", "Recebido", "Conferido", "Pago"], regras: [["Recebido", "Custo médio; propõe preço, aprova no recebimento", "UC-FLX-17"], ["Conferido", "Gera conta a pagar", ""]] },
    ],
    casos: ["UC-FLX-01", "UC-FLX-02", "UC-FLX-03", "UC-FLX-08", "UC-FLX-09", "UC-FLX-14", "UC-FLX-15", "UC-FLX-17", "UC-FLX-21", "UC-FLX-23", "UC-FLX-25", "UC-FLX-26", "UC-FLX-27"],
    compra: { etapa: "recebido", metodo: "medio", preco: "propor", regraEm: "produto", aprovacao: "recebimento", ops: "congela" } },
  { id: "cv-pcp", n: "Comunicação visual · média com PCP", para: "Vários setores e turnos; o gargalo é decidir o que entra na máquina.",
    portes: ["media", "grande"], ativs: ["cv", "instal", "offset"], insp: ["holdprint", "printiq", "corebridge"],
    processos: [
      { dom: "venda", etapas: ["Orçamento", "Enviado", "Aprovado", "Faturado", "Entregue", "Pago"], regras: [["Enviado", "Termômetro de acesso + retorno em 48 h", "UC-FLX-04"], ["Aprovado", "Prazo calculado pela fila", "UC-FLX-06"], ["Faturado", "Comissão do vendedor", "UC-FLX-05"]] },
      { dom: "producao", etapas: ["Pré-impressão", "Aprovação do cliente", "Liberada", "Impressão", "Acabamento", "Expedição"], regras: [["Pré-impressão", "Aproveitamento de mídia", "UC-FLX-13"], ["Liberada", "Alocador escolhe quem e quando", "UC-FLX-10"], ["Impressão", "Romaneio peça a peça", "UC-FLX-12"], ["Expedição", "Previsto × realizado", "UC-FLX-15"]] },
      { dom: "compra", etapas: ["Requisição", "Pedido", "Em trânsito", "Recebido", "Conferido", "Pago"], regras: [["Requisição", "Nasce do estoque mínimo", "UC-FLX-19"], ["Conferido", "Custo médio; propostas vão à fila da Fabricação", "UC-FLX-17"]] },
    ],
    casos: ["UC-FLX-01", "UC-FLX-02", "UC-FLX-04", "UC-FLX-05", "UC-FLX-06", "UC-FLX-07", "UC-FLX-08", "UC-FLX-09", "UC-FLX-10", "UC-FLX-12", "UC-FLX-13", "UC-FLX-15", "UC-FLX-16", "UC-FLX-17", "UC-FLX-19", "UC-FLX-21", "UC-FLX-22", "UC-FLX-24", "UC-FLX-27"],
    compra: { etapa: "conferido", metodo: "medio", preco: "propor", regraEm: "produto", aprovacao: "fabricacao", ops: "congela" } },
  { id: "grafica", n: "Gráfica digital e offset", para: "Produção seriada; papel e tinta mudam de preço com frequência.",
    portes: ["pequena", "media", "grande"], ativs: ["offset", "rapida"], insp: ["calcgraf", "zenite", "printiq"],
    processos: [
      { dom: "venda", etapas: ["Orçamento", "Aprovado", "Faturado", "Entregue", "Pago"], regras: [["Aprovado", "Cria a OP com o roteiro do produto", "UC-FLX-07"]] },
      { dom: "producao", etapas: ["Pré-impressão", "Prova", "Impressão", "Acabamento", "Expedição"], regras: [["Prova", "Aprovação online da prova", "UC-FLX-02"], ["Impressão", "Dados da máquina sem digitação", "UC-FLX-11"], ["Acabamento", "Refugo com motivo", "UC-FLX-16"]] },
      { dom: "compra", etapas: ["Pedido", "Recebido", "Conferido", "Pago"], regras: [["Recebido", "Custo médio; preço acompanha o papel automaticamente", "UC-FLX-17"]] },
      { dom: "financeiro", etapas: ["Emitido", "Conferido", "Conciliado", "Liquidado"], regras: [["Liquidado", "Custeio RKW no fechamento do mês", "UC-FLX-20"]] },
    ],
    casos: ["UC-FLX-01", "UC-FLX-02", "UC-FLX-07", "UC-FLX-11", "UC-FLX-13", "UC-FLX-15", "UC-FLX-16", "UC-FLX-17", "UC-FLX-20"],
    compra: { etapa: "recebido", metodo: "medio", preco: "aplicar", regraEm: "produto", aprovacao: "recebimento", ops: "congela" } },
  { id: "brindes", n: "Brindes, sublimação e DTF", para: "Catálogo com variação de cor e tamanho; compra por grade.",
    portes: ["micro", "pequena"], ativs: ["brindes", "textil"], insp: ["mubisys", "shopvox"],
    processos: [
      { dom: "venda", etapas: ["Orçamento", "Aprovado", "Produção", "Entregue", "Pago"], regras: [["Produção", "Baixa a peça e o insumo pela grade", "UC-FLX-14"], ["Entregue", "Cliente avisado por mensagem", "UC-FLX-24"]] },
      { dom: "compra", etapas: ["Pedido", "Recebido", "Pago"], regras: [["Recebido", "Último preço; aplica o preço sozinho", "UC-FLX-17"]] },
    ],
    casos: ["UC-FLX-01", "UC-FLX-03", "UC-FLX-14", "UC-FLX-17", "UC-FLX-19", "UC-FLX-24", "UC-FLX-26"],
    compra: { etapa: "recebido", metodo: "ultimo", preco: "aplicar", regraEm: "categoria", aprovacao: "recebimento", ops: "vivo" } },
  { id: "instalacao", n: "Fachadas e instalação sob encomenda", para: "Cada job é um projeto: visita, projeto, produção e equipe em campo.",
    portes: ["pequena", "media"], ativs: ["instal", "cv"], insp: ["holdprint", "visua", "shopvox"],
    processos: [
      { dom: "venda", etapas: ["Visita técnica", "Projeto", "Orçamento", "Aprovado", "Faturado", "Pago"], regras: [["Aprovado", "Prazo pela fila de produção", "UC-FLX-06"], ["Faturado", "NF-e + NFS-e", "UC-FLX-27"]] },
      { dom: "entrega", etapas: ["Pronto", "Agendado", "Em campo", "Instalado", "Entregue"], regras: [["Agendado", "Agenda equipe e veículo", "UC-FLX-21"], ["Instalado", "Exige foto e assinatura", "UC-FLX-22"], ["Entregue", "Pesquisa de satisfação", "UC-FLX-25"]] },
      { dom: "compra", etapas: ["Pedido", "Recebido", "Conferido", "Pago"], regras: [["Recebido", "Custo médio; propõe preço, aprova no recebimento", "UC-FLX-17"]] },
    ],
    casos: ["UC-FLX-02", "UC-FLX-06", "UC-FLX-15", "UC-FLX-17", "UC-FLX-21", "UC-FLX-22", "UC-FLX-23", "UC-FLX-25", "UC-FLX-27"],
    compra: { etapa: "recebido", metodo: "medio", preco: "propor", regraEm: "produto", aprovacao: "recebimento", ops: "congela" } },
  { id: "oficina", n: "Oficina mecânica", para: "OS de veículo; a peça comprada destrava o serviço.",
    portes: ["micro", "pequena", "media"], ativs: ["oficina"], insp: ["corebridge"],
    processos: [
      { dom: "venda", etapas: ["Diagnóstico", "Aguardando peça", "Em serviço", "Pronto", "Entregue"], regras: [["Pronto", "Cliente avisado", "UC-FLX-24"], ["Entregue", "Fotos do serviço", "UC-FLX-22"]] },
      { dom: "compra", etapas: ["Pedido", "Recebido", "Pago"], regras: [["Recebido", "Libera as OS em Aguardando peça; custo médio", "UC-FLX-17"]] },
    ],
    casos: ["UC-FLX-17", "UC-FLX-22", "UC-FLX-24", "UC-FLX-27"],
    compra: { etapa: "recebido", metodo: "medio", preco: "so_custo", regraEm: "produto", aprovacao: "recebimento", ops: "congela" } },
];

// Encaixe 0–4: porte (2) + atividade (2); porte vizinho vale 1.
function encaixe(m, perfil) {
  let s = 0;
  if (m.portes.includes(perfil.porte)) s += 2;
  else if (m.portes.some((p) => Math.abs(ORDEM_PORTE[p] - ORDEM_PORTE[perfil.porte]) === 1)) s += 1;
  if (m.ativs.includes(perfil.ativ)) s += 2;
  return s;
}
const casoPorId = (id) => CASOS.find((c) => c.id === id);

window.FLX = { FONTES, PORTES, ATIVIDADES, PROCESSOS, ESTADOS, CASOS, MODELOS, encaixe, casoPorId };
})();
