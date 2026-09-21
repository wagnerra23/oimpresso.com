// fiscal-data.jsx — Módulo Fiscal (cockpit unificado) importado do git @main.
// Fonte: Modules/Fiscal (Routes/web.php · CockpitController mocks) + resources/js/Pages/Fiscal/*
// (Cockpit · Nfe · Nfse · Eventos · Dfe · Config · Sped + charters). Docs fictícios (o vivo mascara PII).

const FX_KPIS = { emitidas: 196, autorizadas: 184, autorizadasPct: 93.9, rejeitadas: 3, faturamentoFiscal: 486320.00, dfeAguardando: 5, certificadoValidadeDias: 47 };

const FX_SPARK = {
  emitidas:    [8, 12, 9, 14, 11, 6, 2, 15, 13, 17, 12, 14, 9, 16],
  autorizadas: [8, 11, 9, 13, 11, 6, 2, 14, 13, 16, 11, 13, 9, 15],
  rejeitadas:  [0, 1, 0, 1, 0, 0, 0, 1, 0, 1, 1, 1, 0, 1],
};

const FX_SEFAZ = { uf: "SP", operacional: true, label: "SEFAZ-SP operacional" };

// cstat → label (STATUS_LABEL do Cockpit.tsx)
const FX_STATUS_LABEL = { 100: "Autorizada", 110: "Rejeição", 204: "Duplicidade", 220: "NF-e numérica", 539: "Dest. inválido", 691: "Item rejeitado", 778: "XML inválido", 999: "Processando" };
const FX_REJ_CODES = [110, 204, 220, 539, 691, 778];

// Receita SEFAZ determinística (sefaz-actions.ts) — o que fazer por código.
const FX_SEFAZ_ACTIONS = {
  110: { causa: "Rejeição de regra de validação no cadastro do destinatário.", passos: ["Confirmar IE do destinatário no SINTEGRA da UF", "Corrigir o cadastro do cliente", "Retransmitir a nota (mesma numeração)"] },
  204: { causa: "Duplicidade de NF-e: chave já autorizada na SEFAZ.", passos: ["Consultar a chave na SEFAZ", "Se autorizada, vincular o XML existente à venda", "Não retransmitir — inutilizar a faixa se sobrar número"] },
  539: { causa: "Destinatário inválido — CNPJ/CPF não confere com a IE.", passos: ["Revisar CNPJ e IE no cadastro", "Retransmitir"] },
  999: { causa: "Lote em processamento na SEFAZ.", passos: ["Aguardar retorno automático (até 5 min)", "Reconsultar recibo se passar de 15 min"] },
};

const FX_ALERTS = [
  { level: "crit", icon: "audit",   title: "NF-e 8425 rejeitada (cstat 110)", sub: "IE destinatário inválida no cadastro SP", action: "Abrir nota", goto: "fiscal-nfe", focus: "nfe-8425" },
  { level: "crit", icon: "audit",   title: "NFS-e 2103 rejeitada", sub: "Tomador sem IE municipal — Guarulhos", action: "Abrir nota", goto: "fiscal-nfse", focus: "nfse-2103" },
  { level: "warn", icon: "shield",  title: "Certificado A1 vence em 47 dias", sub: "Agendar renovação com contador", action: "Abrir configuração", goto: "fiscal-config" },
  { level: "info", icon: "receipt", title: "5 DF-e aguardando manifestação", sub: "Prazo legal: 90 dias da emissão", action: "Manifestar", goto: "fiscal-dfe" },
];

const FX_SAVED_VIEWS = [
  { id: "todas",       label: "Todas",              tipo: "todos", status: "todos",       count: 10 },
  { id: "resolver",    label: "Pra resolver hoje",  tipo: "todos", status: "rejeitadas",  count: 2, tone: "bad" },
  { id: "janela24",    label: "Janela 24h aberta",  tipo: "todos", status: "cancelaveis", count: 4, tone: "warn" },
  { id: "processando", label: "Aguardando SEFAZ",   tipo: "todos", status: "processando", count: 1 },
  { id: "nfse",        label: "Só serviço (NFS-e)", tipo: "NFS-e", status: "todos",       count: 2 },
  { id: "nfce",        label: "Só balcão (NFC-e)",  tipo: "NFC-e", status: "todos",       count: 3 },
];

const FX_NOTAS = [
  { id: "nfse-2104", tipo: "NFS-e", kind: "nfse", num: "2104", serie: null, when: "05/2026", competencia: "05/2026",
    cliente: "TechPro Equipamentos", doc: "18.402.771/0001-06", uf: "São Paulo/SP", venda: null, ref: "OS #4807",
    keyOrCode: "14.05", codServ: "14.05", iss: 5, status: "autorizada", statusKind: "nfse", rejMsg: null, modelo: null,
    value: 2840.00, prazoCancel: null, prazoCce: null },
  { id: "nfe-8428", tipo: "NF-e", kind: "nfe", num: "8428", serie: "1", when: "18/05 14:20",
    cliente: "Imobiliária Horizonte", doc: "07.914.556/0001-72", uf: "SP", venda: "V-4821", ref: null,
    keyOrCode: "35260507914556000172550010000084281994512037", status: 100, statusKind: "sefaz", rejMsg: null,
    modelo: 55, value: 540.00, prazoCancel: { label: "19h20", urgency: "ok" }, prazoCce: null },
  { id: "nfce-9012", tipo: "NFC-e", kind: "nfe", num: "9012", serie: "9", when: "18/05 13:10",
    cliente: "Consumidor", doc: "—", uf: "SP", venda: "V-4825", ref: null,
    keyOrCode: "35260504982117000145650090000090121884400218", status: 100, statusKind: "sefaz", rejMsg: null,
    modelo: 65, value: 84.00, prazoCancel: { label: "18h50", urgency: "ok" }, prazoCce: null },
  { id: "nfce-9011", tipo: "NFC-e", kind: "nfe", num: "9011", serie: "9", when: "18/05 12:34",
    cliente: "Consumidor (CPF na nota)", doc: "•••.412.338-••", uf: "SP", venda: "V-4824", ref: null,
    keyOrCode: "35260504982117000145650090000090111884400105", status: 100, statusKind: "sefaz", rejMsg: null,
    modelo: 65, value: 142.00, prazoCancel: { label: "17h18", urgency: "warn" }, prazoCce: null },
  { id: "nfe-8427", tipo: "NF-e", kind: "nfe", num: "8427", serie: "1", when: "18/05 11:02",
    cliente: "Imobiliária Horizonte", doc: "07.914.556/0001-72", uf: "SP", venda: "V-4820", ref: null,
    keyOrCode: "35260507914556000172550010000084271994511930", status: 100, statusKind: "sefaz", rejMsg: null,
    modelo: 55, value: 560.00, prazoCancel: { label: "16h05", urgency: "warn" }, prazoCce: null },
  { id: "nfe-8425", tipo: "NF-e", kind: "nfe", num: "8425", serie: "1", when: "18/05 09:23",
    cliente: "Gráfica Ribeirão Ltda", doc: "22.641.309/0001-88", uf: "SP", venda: "V-4815", ref: null,
    keyOrCode: "35260522641309000188550010000084251994511707", status: 110, statusKind: "sefaz",
    rejMsg: "IE destinatário inválida no cadastro SP", modelo: 55, value: 1840.00, prazoCancel: null, prazoCce: null,
    itens: [
      { nome: "Banner 3x1m lona impressa", codigo: "BNL-3X1", qtd: 2, vl: 720.00 },
      { nome: "Adesivo recortado 50x30cm", codigo: "ADV-RC50", qtd: 10, vl: 40.00 },
    ],
    arquivos: [{ tipo: "XML", nome: "8425-rejeitada.xml", tamanho: "12.4 KB", status: "gerado" }],
    emails: [],
    auditoria: [
      { quando: "18/05 09:23", autor: "Eliana", acao: "tentou transmitir → SEFAZ retornou 110" },
      { quando: "18/05 09:12", autor: "Wagner", acao: "criou venda V-4815" },
    ],
    eventos: [] },
  { id: "nfe-8424", tipo: "NF-e", kind: "nfe", num: "8424", serie: "1", when: "17/05 16:41",
    cliente: "AutoCenter Premium", doc: "31.775.220/0001-19", uf: "SP", venda: "V-4810", ref: null,
    keyOrCode: "35260531775220000119550010000084241994511584", status: 100, statusKind: "sefaz", rejMsg: null,
    modelo: 55, value: 3200.00, prazoCancel: null, prazoCce: { label: "29d", urgency: "ok" },
    itens: [
      { nome: "Envelopamento veicular Hilux completo", codigo: "ENV-HLX-FULL", qtd: 1, vl: 2800.00 },
      { nome: "Película insulfilm G20 vidros laterais", codigo: "PEL-G20-LAT", qtd: 4, vl: 100.00 },
    ],
    boleto: { id: "BOL-4810", venc: "15/06/2026", valor: 3200.00, status: "pendente" },
    arquivos: [
      { tipo: "XML", nome: "8424-procNFe.xml", tamanho: "14.8 KB", status: "gerado" },
      { tipo: "PDF", nome: "8424-DANFE.pdf", tamanho: "128 KB", status: "gerado" },
    ],
    emails: [{ tipo: "XML + DANFE pro cliente", para: "compras@autocenterpremium.com.br", quando: "17/05 18:02", status: "entregue" }],
    auditoria: [
      { quando: "17/05 16:41", autor: "Eliana", acao: "autorizou e enviou pro cliente" },
      { quando: "16/05 10:20", autor: "Wagner", acao: "criou venda V-4810 com 2 itens" },
    ],
    eventos: [{ id: "evt-1", tipo: "Carta de Correção", sequencia: 1, descricao: "Corrigir info adicional natureza operação", emit: "18/05 11:40", autor: "Eliana", sefaz: 100 }] },
  { id: "nfse-2103", tipo: "NFS-e", kind: "nfse", num: "2103", serie: null, when: "05/2026", competencia: "05/2026",
    cliente: "Construtora Vale", doc: "09.330.481/0001-54", uf: "Guarulhos/SP", venda: null, ref: "OS #4805",
    keyOrCode: "14.05", codServ: "14.05", iss: 5, status: "rejeitada", statusKind: "nfse",
    rejMsg: "Tomador sem IE municipal — Guarulhos", modelo: null, value: 1200.00, prazoCancel: null, prazoCce: null },
  { id: "nfe-8423", tipo: "NF-e", kind: "nfe", num: "8423", serie: "1", when: "16/05 15:07",
    cliente: "Vargas Distribuidor", doc: "44.207.885/0001-30", uf: "RJ", venda: "V-4805", ref: null,
    keyOrCode: "35260544207885000130550010000084231994511461", status: 999, statusKind: "sefaz", rejMsg: null,
    modelo: 55, value: 4250.00, prazoCancel: null, prazoCce: null },
  { id: "nfce-9008", tipo: "NFC-e", kind: "nfe", num: "9008", serie: "9", when: "16/05 12:15",
    cliente: "Consumidor", doc: "—", uf: "SP", venda: "V-4802", ref: null,
    keyOrCode: "35260504982117000145650090000090081884399981", status: 100, statusKind: "sefaz", rejMsg: null,
    modelo: 65, value: 67.00, prazoCancel: null, prazoCce: null },
];

// Eventos (timeline append-only · CC-e 110110 · cancelamento 110111 · inutilização · EPEC · manifesto)
const FX_EVENTOS = [
  { id: "evt-1", tipo: "Carta de Correção", kind: "cce", nota: "NF-e 8424", sequencia: 1, descricao: "Corrigir info adicional natureza operação", emit: "18/05 11:40", autor: "Eliana", sefaz: 100 },
  { id: "evt-2", tipo: "Cancelamento", kind: "cancel", nota: "NF-e 8420", descricao: "Cliente desistiu da compra antes de envelopamento", emit: "18/05 06:30", autor: "Eliana", sefaz: 101 },
  { id: "evt-3", tipo: "Inutilização", kind: "inutilizacao", nota: "Faixa 8418-8419", descricao: "Inutilização de faixa numérica saltada (erro de digitação)", emit: "17/05 14:12", autor: "Wagner", sefaz: 102 },
  { id: "evt-4", tipo: "Manifestação destinatário", kind: "manifest", nota: "NF-e entrada 982", descricao: "Confirmação operação fornecedor TechSupply Ltda", emit: "16/05 09:48", autor: "Wagner", sefaz: 135 },
  { id: "evt-5", tipo: "Cancelamento", kind: "cancel", nota: "NFC-e 9005", descricao: "Cliente devolveu mercadoria na mesma data", emit: "15/05 17:22", autor: "Larissa", sefaz: 101 },
];

// DF-e recebidos (NF-e emitidas CONTRA o CNPJ — manifesto destinatário, prazo legal 90d)
const FX_DFE = [
  { id: "dfe-982", emitente: "TechSupply Componentes Ltda", cnpj: "62.118.409/0001-77", chave: "35260562118409000177550010000009821994231145", num: "982", emitido: "12/05/2026", valor: 8420.00, status: "pendente", prazo: { label: "83d", urgency: "ok" } },
  { id: "dfe-1174", emitente: "Lona & Cia Distribuidora", cnpj: "08.552.164/0001-92", chave: "35260508552164000192550010000011741994230882", num: "1174", emitido: "28/04/2026", valor: 15300.00, status: "pendente", prazo: { label: "68d", urgency: "ok" } },
  { id: "dfe-745", emitente: "Tintas Prisma S/A", cnpj: "55.901.238/0001-45", chave: "35260555901238000145550010000007451994230649", num: "745", emitido: "02/03/2026", valor: 2190.00, status: "pendente", prazo: { label: "12d", urgency: "crit" } },
  { id: "dfe-611", emitente: "Ferramentas Bandeirante", cnpj: "13.884.702/0001-08", chave: "35260513884702000108550010000006111994230416", num: "611", emitido: "18/04/2026", valor: 640.00, status: "confirmada", prazo: null },
  { id: "dfe-508", emitente: "Papelaria Central ME", cnpj: "27.605.913/0001-61", chave: "35260527605913000161550010000005081994230283", num: "508", emitido: "09/04/2026", valor: 318.00, status: "desconhecida", prazo: null },
  { id: "dfe-402", emitente: "Vidros Norte Ltda", cnpj: "39.117.845/0001-24", chave: "35260539117845000124550010000004021994230150", num: "402", emitido: "27/03/2026", valor: 1120.00, status: "nao_realizada", prazo: null },
];

const FX_DFE_STATUS = [
  { id: "pendente",      label: "Pendentes" },
  { id: "confirmada",    label: "Confirmadas" },
  { id: "desconhecida",  label: "Desconhecidas" },
  { id: "nao_realizada", label: "Não realizadas" },
  { id: "todas",         label: "Todas" },
];

// Config (read-only por design — edição vive em NfeBrasil/Configuracao/Certificado)
const FX_CONFIG = {
  cert: { tipo: "A1", titular: "OFFICE IMPRESSO COMUNICACAO VISUAL LTDA", cnpj: "04.982.117/0001-45", validoAte: "04/07/2026", dias: 47, ambiente: "Produção", serie: "1" },
  regime: { nome: "Simples Nacional", anexo: "Anexo III", crt: 1, csosnDefault: "102", cfopInterno: "5102", cfopInterestadual: "6102" },
  tributacao: [
    { label: "Origem da mercadoria", valor: "0 · Nacional" },
    { label: "CSOSN default", valor: "102 · Tributada sem permissão de crédito" },
    { label: "PIS / COFINS", valor: "CST 49 · Outras operações" },
    { label: "ISS (serviço)", valor: "5,00% · município São Paulo/SP" },
    { label: "Item 14.05 LC 116", valor: "Serviços gráficos e comunicação visual" },
  ],
  emails: { contador: "contador@example.com.br", envioAutomatico: true, copiaCliente: true },
};

// SPED (últimas competências + gerador EFD-ICMS/IPI layout CONFAZ v3.1.1 perfil A)
const FX_SPED = [
  { comp: "05/2026", label: "maio/2026",      notas: 184, valor: 486320.00, status: "aberto",    entrega: "15/06/2026" },
  { comp: "04/2026", label: "abril/2026",     notas: 209, valor: 542180.00, status: "pronto",    entrega: "15/05/2026" },
  { comp: "03/2026", label: "março/2026",     notas: 178, valor: 431900.00, status: "entregue",  entrega: "15/04/2026" },
  { comp: "02/2026", label: "fevereiro/2026", notas: 141, valor: 358440.00, status: "entregue",  entrega: "15/03/2026" },
  { comp: "01/2026", label: "janeiro/2026",   notas: 133, valor: 322760.00, status: "entregue",  entrega: "15/02/2026" },
];

const FX_SPED_BLOCOS = [
  { id: "0", nome: "Abertura, identificação e referências", registros: 7, registrosIds: "0000 · 0001 · 0005 · 0100 · 0150 · 0190 · 0200" },
  { id: "C", nome: "Documentos fiscais I — mercadorias (NF-e)", registros: 9, registrosIds: "C001 · C100 · C110 · C170 · C190 · C500 · C590 · C990" },
  { id: "E", nome: "Apuração do ICMS e do IPI", registros: 4, registrosIds: "E001 · E100 · E110 · E990" },
  { id: "H", nome: "Inventário físico (esqueleto IND_MOV=1)", registros: 2, registrosIds: "H001 · H990" },
  { id: "9", nome: "Controle e encerramento do arquivo", registros: 1, registrosIds: "9001 · 9900 · 9990 · 9999" },
];

// Write-off de auditoria mensal (determinístico, sem IA — WriteOffAuditoriaCard do vivo)
const FX_WRITEOFF = { totalCandidates: 2470, totalValor: 770000.00, oldestAge: 1847, category: "incobravel", scopeLabel: "Inadimplência >365d" };

// Enviar p/ contabilidade (drawer do header)
const FX_CONTABIL = {
  periodo: "maio/2026",
  destinatario: "contador@example.com.br",
  validacoes: [
    { ok: true,   label: "184 NF-e autorizadas no período" },
    { ok: "warn", label: "3 NF-e rejeitadas — não entram no pacote", action: "Ver rejeitadas", goto: "fiscal-nfe" },
    { ok: true,   label: "5 DF-e manifestadas (4 confirmadas + 1 desconhecida)" },
    { ok: "warn", label: "Certificado A1 vence em 47d — renovar antes do próximo fechamento", action: "Renovar", goto: "fiscal-config" },
    { ok: true,   label: "SPED EFD ICMS/IPI pronto pra gerar (último: abr/2026)" },
  ],
  totais: { autorizadas: 184, nfse: 12, eventos: 5 },
  historico: [
    { id: "hist-1", periodo: "abril/2026",     enviadoEm: "03/05 09:23", metodo: "e-mail",  destino: "contador@example.com.br", pacote: "4,3 MB" },
    { id: "hist-2", periodo: "março/2026",     enviadoEm: "02/04 10:15", metodo: "e-mail",  destino: "contador@example.com.br", pacote: "3,9 MB" },
    { id: "hist-3", periodo: "fevereiro/2026", enviadoEm: "04/03 11:48", metodo: "download", destino: "eliana@local",           pacote: "2,1 MB" },
  ],
};

// ─── Onda 1 · Procedência por superfície (CU-FISC-16 @main) ───
// O vivo declara 6 superfícies servidas por dado de demonstração; aqui elas ficam nomeadas.
const FX_PROC = {
  kpis:        { kind: "real",     label: "leitura real",     explica: "Contagem e soma em NfeEmissao do mês corrente, cache 60s por business." },
  alerts:      { kind: "real",     label: "leitura real",     explica: "Receita determinística: rejeições 7d + certificado <60d + DF-e pendente. Sem IA." },
  spark:       { kind: "real",     label: "leitura real",     explica: "Uma consulta agrupada por dia (14 dias), sem repetir query por dia." },
  notas:       { kind: "demo",     label: "demonstração",     explica: "Lista unificada NF-e/NFC-e/NFS-e é mock do Controller. Real depende de NotasUnifiedService (pendência declarada no vivo)." },
  eventos:     { kind: "demo",     label: "demonstração",     explica: "Timeline de eventos do cabeçalho é mock. Real depende da query em nfe_eventos." },
  viewCounts:  { kind: "derivado", label: "derivado da lista", explica: "No vivo os contadores são fixos no código; aqui são calculados sobre a lista exibida." },
  sefaz:       { kind: "demo",     label: "demonstração",     explica: "Situação da SEFAZ é fixa. Real depende de consumir o webservice de status por UF." },
  contabil:    { kind: "demo",     label: "demonstração",     explica: "Pacote da contabilidade é mock; o envio real (e-mail/SFTP) não existe." },
  writeoff:    { kind: "demo",     label: "demonstração",     explica: "Candidatos a baixa são mock. Real depende de consulta em fin_titulos >365d sem pagamento." },
  dfe:         { kind: "real",     label: "leitura real",     explica: "NfeDfeRecebido com escopo de business; prazo vem do valor calculado pela SEFAZ." },
  dfeHist:     { kind: "demo",     label: "demonstração",     explica: "Histórico de manifestações tem ator e observação inventados — decisão [W] pendente no vivo." },
  config:      { kind: "real",     label: "leitura real",     explica: "Certificado, regime e tributação vêm do NfeBrasil (escopo aplicado à mão a partir da sessão)." },
  series:      { kind: "real",     label: "leitura real",     explica: "Séries por local vindas do emissor — proposta [CC] desta sessão: filial inventada removida." },
  ambiente:    { kind: "flag",     label: "atrás de gate",    explica: "Trocar ambiente e enviar certificado exigem fiscal.config.ambiente (gate próprio, só superadmin) — proposta [CC] desta sessão." },
  sped:        { kind: "real",     label: "leitura real",     explica: "Panorama agrega NfeEmissao autorizada por competência. Prazo dia 15 é heurística visual." },
  spedGerador: { kind: "flag",     label: "atrás de trava",   explica: "Gerador existe, mas o download vive atrás da flag sped_simples_only_lock (fail-secure) — só superadmin passa." },
};

// Séries fiscais por local (aba do vivo em Config) — proposta [CC]: leitura real, sem filial inventada
const FX_SERIES = [
  { id: "s1", local: "Matriz — São Paulo/SP", modelo: "55 · NF-e",  serie: "1", proximo: 8429, ambiente: "Produção" },
  { id: "s2", local: "Matriz — São Paulo/SP", modelo: "65 · NFC-e", serie: "9", proximo: 9013, ambiente: "Produção" },
  { id: "s3", local: "Matriz — São Paulo/SP", modelo: "56 · NFS-e", serie: "—", proximo: 2105, ambiente: "Produção" },
];

// Histórico de manifestações DF-e (aba do vivo em Dfe)
const FX_DFE_HISTORICO = [
  { id: "h1", num: "611",  emitente: "Ferramentas Bandeirante", acao: "Confirmação da operação",  quando: "20/04/2026 09:12", autor: "Wagner", obs: "Material recebido no galpão", sefaz: 135 },
  { id: "h2", num: "508",  emitente: "Papelaria Central ME",    acao: "Desconhecimento",          quando: "11/04/2026 15:40", autor: "Eliana", obs: "CNPJ homônimo — não somos o destinatário", sefaz: 136 },
  { id: "h3", num: "402",  emitente: "Vidros Norte Ltda",       acao: "Operação não realizada",   quando: "29/03/2026 11:05", autor: "Wagner", obs: "Carga recusada na portaria", sefaz: 136 },
];

// Amostra do TXT EFD-ICMS/IPI (perfil A) — o vivo tem 23 registros canônicos
const FX_SPED_TXT = [
  "|0000|018|0|01052026|31052026|OFFICE IMPRESSO COMUNICACAO VISUAL LTDA|04982117000145|SP|123456789||3550308|||A|1|",
  "|0001|0|",
  "|0005|OFFICE IMPRESSO|01310930|AV PAULISTA|1000||SAO PAULO|1130000000||fiscal@example.com.br|",
  "|0150|1|GRAFICA RIBEIRAO LTDA|1058|22641309000188||3543402|RUA DAS ARTES|450||",
  "|0190|UN|UNIDADE|",
  "|0200|BNL-3X1|BANNER 3X1M LONA IMPRESSA||||UN|49019900|||0|",
  "|C001|0|",
  "|C100|1|1|1|55|00|1|8428|35260507914556000172550010000084281994512037|18052026|18052026|540,00|1|0,00|...",
  "|C170|1|BNL-3X1|BANNER 3X1M LONA IMPRESSA|2|UN|1440,00|0,00|0|102|5102|...",
  "|C190|102|5102|18,00|1440,00|0,00|0,00|259,20|0,00|0,00|0,00|0,00|0,00||",
  "|E001|0|",
  "|E110|259,20|0,00|0,00|0,00|0,00|0,00|0,00|0,00|0,00|0,00|0,00|0,00|0,00|",
  "|H001|1|",
  "|9999|1284|",
];

// Onda 5 · débitos que não são de UI (declarados nos casos.md do vivo)
const FX_DEBITOS = [
  { tela: "fiscal-nfse", tom: "warn",   titulo: "Município da prestação não aparece", texto: "A coluna não existe no schema em produção — duelo de duas migrations para nfse_emissoes, resolvido revertendo o Controller para o schema antigo. Hoje o campo volta vazio por desenho." },
  { tela: "fiscal-nfse", tom: "info",   titulo: "Cancelamento de NFS-e fora de escopo", texto: "Varia por município — declarado backlog no charter da tela." },
  { tela: "fiscal-nfe",  tom: "danger", titulo: "Gate fiscal.nfe.view sem teste", texto: "O guard existe no Controller e o charter dá como coberto, mas nenhum teste o exercita. Precisa de lane com users + permissions." },
  { tela: "fiscal-nfe",  tom: "warn",   titulo: "Retransmitir sem validação provada", texto: "A whitelist de status é checada depois do firstOrFail() — exige a tabela nfe_emissoes, indisponível nas lanes de teste atuais." },
  { tela: "fiscal-sped", tom: "danger", titulo: "Gerador não validado no PVA-EFD", texto: "Os testes dos blocos são source-grep (procuram nome de método no fonte); nenhum golden file, nenhum smoke no validador oficial da CONFAZ." },
  { tela: "fiscal-eventos", tom: "info", titulo: "Justificativa truncada em 200 caracteres", texto: "Corte feito no Controller para não vazar PII do xMotivo do XML; a tela não re-expande." },
];

Object.assign(window, { FISCAL_DATA: { KPIS: FX_KPIS, SPARK: FX_SPARK, SEFAZ: FX_SEFAZ, STATUS_LABEL: FX_STATUS_LABEL, REJ_CODES: FX_REJ_CODES, SEFAZ_ACTIONS: FX_SEFAZ_ACTIONS, ALERTS: FX_ALERTS, SAVED_VIEWS: FX_SAVED_VIEWS, NOTAS: FX_NOTAS, EVENTOS: FX_EVENTOS, DFE: FX_DFE, DFE_STATUS: FX_DFE_STATUS, CONFIG: FX_CONFIG, SPED: FX_SPED, SPED_BLOCOS: FX_SPED_BLOCOS, WRITEOFF: FX_WRITEOFF, CONTABIL: FX_CONTABIL, PROC: FX_PROC, SERIES: FX_SERIES, DFE_HISTORICO: FX_DFE_HISTORICO, SPED_TXT: FX_SPED_TXT, DEBITOS: FX_DEBITOS } });
