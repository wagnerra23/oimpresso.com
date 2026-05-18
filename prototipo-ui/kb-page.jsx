// kb-page.jsx — Base de Conhecimento (Módulo KB)
// Tri-pane (categorias · lista · leitor) + Command palette ⌘K + Troubleshooter
// Tokens do shell, hue 240 (PROJETOS & GESTÃO).
(() => {
const { useState, useMemo, useEffect, useRef } = React;

// ─────────────────────────────────────────────────────────────────
// DATA — 18 artigos reais de gráfica + 1 troubleshooter
// ─────────────────────────────────────────────────────────────────

const CATEGORIES = [
  { id: "all",       label: "Tudo",                hue: 240, icon: "📚" },
  { id: "producao",  label: "Produção",            hue: 30,  desc: "Impressão, acabamento, expedição" },
  { id: "equip",     label: "Equipamentos",        hue: 280, desc: "Roland, HP Latex, plotter, laminadora" },
  { id: "arte",      label: "Pré-impressão",       hue: 200, desc: "Sangria, ICC, fontes, PDF" },
  { id: "atendim",   label: "Atendimento",         hue: 145, desc: "Brief, aprovação, retirada" },
  { id: "fiscal",    label: "Fiscal & financeiro", hue: 60,  desc: "NF-e, NFS-e, boleto Inter, SEFAZ" },
  { id: "sistema",   label: "Sistema (ERP)",       hue: 250, desc: "OS, vendas, cadastros, atalhos" },
  { id: "rh",        label: "Pessoas",             hue: 295, desc: "Ponto, escalas, segurança" },
];

const EQUIPAMENTOS = [
  "Roland VS-540",
  "HP Latex 365",
  "Plotter Graphtec",
  "Laminadora Royal",
  "Bobina vinil Avery",
  "—",
];

const NIVEIS = [
  { id: "iniciante",     label: "Iniciante",     hue: 145 },
  { id: "intermediario", label: "Intermediário", hue: 220 },
  { id: "avancado",      label: "Avançado",      hue: 25  },
];

const INITIAL_ARTICLES = [
  {
    id: "a1", cat: "equip", nivel: "intermediario", equip: "Roland VS-540",
    title: "Calibrar perfil ICC na Roland VS-540 (VersaWorks)",
    excerpt: "Passo a passo para gerar e aplicar perfil ICC novo em vinil Avery 900 com tinta Eco-Sol Max 2.",
    author: "Mateus PCP", updated: "há 3 dias", reads: 142, helpful: 28, outdated: 1,
    osLinked: 4, readTime: 6,
    pinned: true, status: "ok",
    tags: ["ICC", "VersaWorks", "vinil", "Eco-Sol Max 2"],
    body: [
      { kind: "para", t: "Use este SOP toda vez que trocar lote de vinil ou tinta. O perfil antigo NÃO serve — desvio de ΔE pode ultrapassar 4, e o cliente rejeita." },
      { kind: "h2", t: "Antes de começar" },
      { kind: "list", items: [
        "Limpeza diária da Roland feita (artigo #a3).",
        "Bobina nova já carregada, ponta cortada reta.",
        "Espectrofotômetro X-Rite i1Pro ligado e calibrado em branco.",
        "VersaWorks 6 aberto, modo Color Management → Profile."
      ]},
      { kind: "h2", t: "1. Imprimir o target" },
      { kind: "list", items: [
        "Em VersaWorks: Color Management → Create Profile → Linearization Chart.",
        "Configure tinta 4 cores, qualidade Alta (8 passos).",
        "Imprima em vinil de teste 60×30cm. Aguarde 30 min de secagem antes de medir."
      ]},
      { kind: "callout", tone: "warn", t: "Não meça com adesivo morno — o cromatismo escorrega. Espere os 30min." },
      { kind: "h2", t: "2. Medir com i1Pro" },
      { kind: "list", items: [
        "Trace o target inteiro em 3 passagens.",
        "Software vai gerar arquivo .icc — salve em VersaWorks/Profiles com nome AveryHP_EcoSolMax2_LOTE-2026-04.icc.",
        "Aplique como perfil padrão para esse material."
      ]},
      { kind: "h2", t: "3. Validar com strip de aferição" },
      { kind: "para", t: "Imprima a strip de aferição (anexo no rodapé). ΔE médio precisa ficar abaixo de 2. Se passar de 4, refaça o passo 1." },
      { kind: "callout", tone: "ok", t: "Boa prática: anote no campo Observações da OS qual ICC foi usado. Facilita rastrear retrabalho." },
    ],
  },
  {
    id: "a2", cat: "equip", nivel: "iniciante", equip: "HP Latex 365",
    title: "Trocar bobina vinil HP Latex 365 sem desperdício",
    excerpt: "Como engatar bobina nova com no máximo 30cm de perda. Inclui truque do alinhamento do tubo.",
    author: "Carla Souza", updated: "há 2 semanas", reads: 89, helpful: 22, outdated: 0,
    osLinked: 2, readTime: 4, status: "ok",
    tags: ["bobina", "vinil", "troca", "HP Latex"],
    body: [
      { kind: "para", t: "A HP Latex tem aprendizado de tração automático, mas se você não respeitar o sentido do enrolamento, perde 80cm." },
      { kind: "h2", t: "Passos" },
      { kind: "list", items: [
        "Pause o job atual no painel. Não aborte — pause.",
        "Recolha a bobina velha pelo botão Unload media.",
        "Posicione a bobina nova com o adesivo de origem para CIMA — esse é o lado da face impressa.",
        "Encaixe o tubo no eixo direito primeiro, depois esquerdo. Trave os flanges.",
        "Auto-feed: a impressora puxa 25cm e mede largura sozinha.",
      ]},
      { kind: "callout", tone: "info", t: "Se a impressora reclamar de 'media skew', cheque se o flange esquerdo está mesmo travado. 9 de 10 vezes é isso." },
    ],
  },
  {
    id: "a3", cat: "equip", nivel: "iniciante", equip: "Roland VS-540",
    title: "Limpeza diária da cabeça de impressão — Roland VS-540",
    excerpt: "Rotina de 8 minutos no início do turno. Faltar 2 dias seguidos entope a cabeça (R$ [redacted Tier 0] de reposição).",
    author: "Mateus PCP", updated: "há 1 mês", reads: 312, helpful: 71, outdated: 0,
    osLinked: 0, readTime: 5, status: "ok", pinned: true,
    tags: ["limpeza", "manutenção", "cabeça impressão"],
    body: [
      { kind: "para", t: "Esta rotina é obrigatória no início de cada turno. Carla cobra na passagem de bastão." },
      { kind: "h2", t: "Material" },
      { kind: "list", items: [
        "Solução de limpeza Roland (frasco amarelo, nunca o azul).",
        "Cotonete absorvente Eco-Sol — 6 unidades.",
        "Luva nitrílica (nunca látex).",
      ]},
      { kind: "h2", t: "Rotina" },
      { kind: "list", items: [
        "Painel: Menu → Cleaning → Daily. A máquina move a cabeça.",
        "Aplique 2 gotas no capping station.",
        "Passe cotonete molhado nas laterais da cabeça — NUNCA na face de jato.",
        "Roda o teste de bicos. Se faltar bico, repete uma vez. Se faltar de novo: abre OS interna 'manutenção'.",
      ]},
      { kind: "callout", tone: "bad", t: "Nunca, jamais, use álcool isopropílico. Resseca a junta da cabeça e fura o orçamento da assistência." },
    ],
  },
  {
    id: "a4", cat: "arte", nivel: "intermediario", equip: "—",
    title: "Sangria e safety zone em banner 3×2m",
    excerpt: "Cliente manda PDF na medida exata. Como expandir 3cm de sangria por lado sem distorcer.",
    author: "Joana Lima", updated: "há 5 dias", reads: 76, helpful: 19, outdated: 0,
    osLinked: 3, readTime: 4, status: "ok",
    tags: ["sangria", "PDF", "Illustrator", "banner"],
    body: [
      { kind: "para", t: "Banner em lona 440g pede 3cm de sangria por lado. Em vinil de fachada, 2cm bastam. Confirme no brief antes de imprimir." },
      { kind: "h2", t: "Quando o cliente manda PDF fechado" },
      { kind: "list", items: [
        "Abra no Illustrator. Document Setup → Bleed: 30mm em todos os lados.",
        "Selecione fundo. Se for sólido, basta esticar com Shift até a sangria.",
        "Se for imagem, use Object → Artboard → Fit Artboard to Bleed e refaça o crop.",
        "Salve como PDF/X-4 com perfil de saída do equipamento (artigo #a1).",
      ]},
      { kind: "callout", tone: "warn", t: "Nunca redimensione PDF fechado em CorelDRAW — quebra fontes embutidas. Sempre Illustrator." },
    ],
  },
  {
    id: "a5", cat: "arte", nivel: "iniciante", equip: "—",
    title: "Fontes fechadas em PDF do cliente — como verificar antes de imprimir",
    excerpt: "Em 3 cliques no Acrobat você vê se as fontes estão embutidas. Falhar nisso retrabalho garantido.",
    author: "Bruna Vendas", updated: "há 1 mês", reads: 58, helpful: 14, outdated: 0,
    osLinked: 1, readTime: 2, status: "ok",
    tags: ["PDF", "fontes", "Acrobat"],
    body: [
      { kind: "para", t: "Abra o PDF no Acrobat Pro → Ferramentas → Verificação preliminar (Preflight) → 'Listar todas as fontes'." },
      { kind: "list", items: [
        "Verde = embutida, segue o jogo.",
        "Amarelo = subset embutido, ok.",
        "Vermelho = não embutida, PARA o pedido e volta pro cliente.",
      ]},
    ],
  },
  {
    id: "a6", cat: "producao", nivel: "intermediario", equip: "Plotter Graphtec",
    title: "Plotter Graphtec FC9000 — recorte de adesivo recortado",
    excerpt: "Pressão de lâmina, offset e ajuste de marca de registro para recorte preciso a 0,1mm.",
    author: "Felipe Acab.", updated: "há 1 semana", reads: 134, helpful: 31, outdated: 0,
    osLinked: 5, readTime: 7, status: "ok", pinned: true,
    tags: ["plotter", "recorte", "marca de registro", "Graphtec"],
    body: [
      { kind: "para", t: "Adesivo recortado fino exige pressão baixa, offset bem ajustado e marca de registro impressa nítida. Erre um e perde a tiragem inteira." },
      { kind: "h2", t: "Parâmetros padrão (vinil 80μ)" },
      { kind: "list", items: [
        "Pressão: 80g · Velocidade: 30cm/s · Offset: 0.25",
        "Lâmina CB09U (azul). Não use a vermelha (CB15U) em adesivo fino.",
        "Marca de registro: 8mm preto pleno, 3 marcas em L.",
      ]},
      { kind: "h2", t: "Calibração de offset" },
      { kind: "list", items: [
        "Pressione TEST no painel → Cut Test.",
        "Veja o quadradinho. Se canto não fechou: aumente offset 0.05.",
        "Se canto passou: diminua 0.05. Repita até quadrado perfeito."
      ]},
      { kind: "callout", tone: "ok", t: "Mateus já documentou os offsets de cada bobina nos painéis no aplicativo. Consulte antes de chutar." },
    ],
  },
  {
    id: "a7", cat: "atendim", nivel: "iniciante", equip: "—",
    title: "Como abrir OS com brief incompleto",
    excerpt: "Cliente quer 'só fazer logo grande' sem mandar arquivo. Use este checklist mínimo.",
    author: "Larissa B.", updated: "há 2 dias", reads: 203, helpful: 47, outdated: 0,
    osLinked: 12, readTime: 3, status: "ok", pinned: true,
    tags: ["brief", "OS", "atendimento", "balcão"],
    body: [
      { kind: "para", t: "Recusar pedido sem brief? Não. Mas você precisa GARANTIR 6 dados mínimos antes de mandar produzir." },
      { kind: "h2", t: "Os 6 dados mínimos" },
      { kind: "list", items: [
        "Material e medida final (lona 440? vinil adesivo? quantos cm?)",
        "Quantidade exata.",
        "Cor de fundo dominante (importa pro cálculo de tinta).",
        "Acabamento (ilhós? laminação? recorte?)",
        "Prazo de retirada — combinado, não 'urgente'.",
        "Arquivo OU referência (foto, link, qualquer coisa visual).",
      ]},
      { kind: "callout", tone: "warn", t: "Sem qualquer um desses 6, abra como ORÇAMENTO, não OS. OS só com brief fechado." },
      { kind: "h2", t: "Atalho no ERP" },
      { kind: "para", t: "Tecle N na lista de OS pra abrir o form. O wizard exige os 6 campos. Se algo faltar, ele bloqueia o salvar." },
    ],
  },
  {
    id: "a8", cat: "atendim", nivel: "iniciante", equip: "—",
    title: "Fluxo de aprovação de arte — quem aprova o quê",
    excerpt: "Versão 1 vai pra cliente. Aprovou? Vira versão final. Diferenças não-óbvias entre 'pré-prova' e 'arte final'.",
    author: "Joana Lima", updated: "há 3 semanas", reads: 67, helpful: 12, outdated: 0,
    osLinked: 4, readTime: 4, status: "ok",
    tags: ["aprovação", "arte", "fluxo"],
    body: [
      { kind: "para", t: "Toda OS de impressão tem 3 estágios de arte: prévia, prova e final. Cada uma exige aprovação diferente." },
      { kind: "list", items: [
        "Prévia (PDF baixa res, sem perfil ICC): aprovação do operador comercial.",
        "Prova (impressa em A4 ou trecho real): aprovação do cliente — REGISTRE no thread da OS.",
        "Final (arquivo pra produção, com perfil ICC do material): aprovação do PCP."
      ]},
    ],
  },
  {
    id: "a9", cat: "fiscal", nivel: "intermediario", equip: "—",
    title: "Erros frequentes SEFAZ na emissão de NF-e — códigos e correção",
    excerpt: "Rejeição 539, 778, 692 e 539 são 80% dos casos. Como interpretar e o que ajustar no cadastro.",
    author: "Eliana Fin.", updated: "há 5 dias", reads: 91, helpful: 24, outdated: 0,
    osLinked: 0, readTime: 6, status: "ok",
    tags: ["NF-e", "SEFAZ", "rejeição", "fiscal"],
    body: [
      { kind: "para", t: "Quando a SEFAZ rejeita, o ERP mostra código + mensagem. Mas mensagem em jargão fiscal não ajuda — esta tabela traduz." },
      { kind: "h2", t: "Top 4" },
      { kind: "list", items: [
        "539: Duplicidade de NF — você já emitiu essa série/número. Cheque o último número e incremente.",
        "692: Inscrição estadual do destinatário inválida. Consulte cliente no SINTEGRA, atualize o cadastro.",
        "778: CFOP inválido para a operação. Operação dentro do estado é 5102; fora, 6102.",
        "402: Origem da mercadoria inválida. Use 0 para nacional, 1 para estrangeira.",
      ]},
      { kind: "callout", tone: "info", t: "Antes de reemitir, INUTILIZE o número rejeitado. Senão fura a sequência e SEFAZ rejeita o próximo também." },
    ],
  },
  {
    id: "a10", cat: "fiscal", nivel: "iniciante", equip: "—",
    title: "Boleto Inter — fluxo completo de emissão a baixa",
    excerpt: "Da geração via API até a conciliação automática. O ERP faz quase tudo — você só precisa entender por quê.",
    author: "Eliana Fin.", updated: "há 1 semana", reads: 124, helpful: 33, outdated: 0,
    osLinked: 8, readTime: 5, status: "ok",
    tags: ["boleto", "Inter", "financeiro", "conciliação"],
    body: [
      { kind: "para", t: "O Inter retorna o PDF do boleto + linha digitável em 2s. Salvamos junto da fatura no Financeiro." },
      { kind: "h2", t: "Por que às vezes demora?" },
      { kind: "list", items: [
        "Token de API expirou → re-autentique em Config → Inter.",
        "Cliente sem CPF/CNPJ → boleto NÃO emite. Atualize o cadastro.",
        "Valor abaixo de R$ [redacted Tier 0] → Inter rejeita. Use cobrança recorrente ou Pix.",
      ]},
    ],
  },
  {
    id: "a11", cat: "sistema", nivel: "iniciante", equip: "—",
    title: "Atalhos de teclado essenciais do ERP",
    excerpt: "30 atalhos que economizam 40 min por dia de balcão. Imprimir e colar do lado do monitor.",
    author: "Wagner R.", updated: "há 4 dias", reads: 287, helpful: 64, outdated: 0,
    osLinked: 0, readTime: 3, status: "ok", pinned: true,
    tags: ["atalhos", "teclado", "produtividade"],
    body: [
      { kind: "para", t: "Larissa testou — sai do balcão 30min mais cedo decorando esses 30 atalhos." },
      { kind: "h2", t: "Globais" },
      { kind: "list", items: [
        "⌘K — Busca global em tudo (clientes, produtos, OS, KB).",
        "⌘\\ — Esconde/mostra sidebar.",
        "⌘⇧\\ — Esconde sidebar TOTAL (modo foco).",
        "G + número — Vai pra módulo (G1 Chat, G2 Tarefas, G3 OS...).",
      ]},
      { kind: "h2", t: "Listagens" },
      { kind: "list", items: [
        "N — Nova OS / cliente / produto (depende da tela).",
        "F — Filtros.",
        "/ — Foco na busca da lista.",
        "J/K — Navega entre linhas (vi-style).",
      ]},
    ],
  },
  {
    id: "a12", cat: "producao", nivel: "intermediario", equip: "Laminadora Royal",
    title: "Laminação fosca vs brilho — quando usar cada uma",
    excerpt: "Cliente não sabe qual escolher. Você não pode chutar. Critério: ambiente, manuseio, fotorrealismo.",
    author: "Felipe Acab.", updated: "há 3 semanas", reads: 52, helpful: 11, outdated: 0,
    osLinked: 2, readTime: 4, status: "ok",
    tags: ["laminação", "acabamento", "fosca", "brilho"],
    body: [
      { kind: "list", items: [
        "Brilho: cor vibrante, mas reflete luz — ruim em ambiente com janela atrás. Bom em adesivo de carro.",
        "Fosca: cor levemente apagada, sem reflexo. Bom em cardápio (mesa com vela acima), painel de loja, fachada.",
        "Texturizada: mascara dedo gordo. Boa em cartão.",
      ]},
    ],
  },
  {
    id: "a13", cat: "atendim", nivel: "iniciante", equip: "—",
    title: "Comunicação cliente: avisar atraso sem queimar o relacionamento",
    excerpt: "Roteiro testado para WhatsApp/telefone quando OS atrasa. 3 frases — não improvise.",
    author: "Bruna Vendas", updated: "há 2 semanas", reads: 119, helpful: 38, outdated: 0,
    osLinked: 0, readTime: 3, status: "ok",
    tags: ["cliente", "comunicação", "atraso"],
    body: [
      { kind: "para", t: "Atraso acontece. O problema é a omissão. Cliente perdoa atraso comunicado; não perdoa o silêncio." },
      { kind: "h2", t: "Roteiro" },
      { kind: "list", items: [
        "1. Reconheça antes de explicar: 'Olá [nome], aqui é da Oimpresso. Sua OS [n] teve um imprevisto.'",
        "2. Diga o novo prazo, NÃO o motivo (motivo só se perguntar).",
        "3. Ofereça algo concreto: entrega expressa, brinde, ou desconto.",
      ]},
      { kind: "callout", tone: "bad", t: "NUNCA diga 'a máquina quebrou'. Cliente lê como 'eles não são confiáveis'. Diga 'estamos finalizando o acabamento'." },
    ],
  },
  {
    id: "a14", cat: "sistema", nivel: "intermediario", equip: "—",
    title: "Lançar custos diretos numa OS para fechar margem real",
    excerpt: "Sem custo lançado, margem é fantasia. Como anexar bobina consumida, tinta e hora-máquina.",
    author: "Wagner R.", updated: "há 1 mês", reads: 73, helpful: 17, outdated: 1,
    osLinked: 0, readTime: 5, status: "ok",
    tags: ["custo", "margem", "OS"],
    body: [
      { kind: "list", items: [
        "Abrir a OS no detalhe → aba Custos.",
        "Adicionar linha: insumo (vinil, tinta, ilhós), quantidade, custo unitário (vem do cadastro).",
        "Adicionar hora-máquina pelo timer do PCP (ou manual em minutos).",
        "Margem real recalcula sozinho no rodapé."
      ]},
      { kind: "callout", tone: "warn", t: "Este artigo está marcado como 'pode estar desatualizado' — campo de custo foi reformulado em maio." },
    ],
  },
  {
    id: "a15", cat: "rh", nivel: "iniciante", equip: "—",
    title: "Bater ponto pelo Ponto WR2 — 3 jeitos",
    excerpt: "Pelo ERP, pelo app celular ou pelo totem da entrada. Como funciona e quando cada um vale.",
    author: "Carla Souza", updated: "há 1 mês", reads: 198, helpful: 41, outdated: 0,
    osLinked: 0, readTime: 3, status: "ok",
    tags: ["ponto", "WR2", "RH"],
    body: [
      { kind: "list", items: [
        "Totem na entrada: válido sempre. Bate biometria.",
        "ERP web: válido só se você estiver na faixa de IP da empresa (Wi-Fi interno).",
        "App celular: válido com geo-fence de 200m do escritório.",
      ]},
    ],
  },
  {
    id: "a16", cat: "fiscal", nivel: "avancado", equip: "—",
    title: "NFS-e — diferença entre RPS lote e envio síncrono",
    excerpt: "Algumas prefeituras aceitam só lote (assíncrono). Como saber qual e configurar.",
    author: "Eliana Fin.", updated: "há 2 meses", reads: 34, helpful: 8, outdated: 2,
    osLinked: 0, readTime: 7, status: "outdated",
    tags: ["NFS-e", "RPS", "prefeitura"],
    body: [
      { kind: "callout", tone: "warn", t: "Conteúdo precisa de revisão — Eliana sinalizou que a integração de São Paulo mudou em abril." },
      { kind: "para", t: "RPS é Recibo Provisório de Serviço — gera enquanto não há resposta da prefeitura." },
    ],
  },
  {
    id: "a17", cat: "producao", nivel: "iniciante", equip: "—",
    title: "Romaneio de motoboy — o que conferir antes de liberar",
    excerpt: "Endereço completo (não só CEP), peça contada, peso aproximado, hora máxima de entrega.",
    author: "Felipe Acab.", updated: "há 1 semana", reads: 56, helpful: 14, outdated: 0,
    osLinked: 6, readTime: 2, status: "ok",
    tags: ["expedição", "motoboy", "romaneio"],
    body: [
      { kind: "list", items: [
        "Endereço completo: rua, número, complemento, bairro. CEP sozinho NÃO basta.",
        "Quantidade conferida em voz alta com o motoboy.",
        "Peso e volume (ele escolhe se vai de moto ou carro com isso).",
        "Janela de horário do cliente (não a sua).",
      ]},
    ],
  },
  {
    id: "a18", cat: "sistema", nivel: "avancado", equip: "—",
    title: "Trocar empresa ativa no ERP sem perder filtros",
    excerpt: "Você está em OI, troca pra WR e os filtros de tela ficam. Como deixar 'limpo' por padrão.",
    author: "Wagner R.", updated: "há 6 meses", reads: 21, helpful: 4, outdated: 3,
    osLinked: 0, readTime: 4, status: "outdated",
    tags: ["multi-empresa", "filtros", "config"],
    body: [
      { kind: "callout", tone: "bad", t: "Conteúdo possivelmente obsoleto — 3 colegas votaram desatualizado. Quem souber, marque como revisado." },
    ],
  },
];

// ─── TROUBLESHOOTER — árvore de decisão ───
const TROUBLE = {
  id: "t1", title: "Roland VS-540 não imprime", equip: "Roland VS-540",
  steps: [
    {
      q: "A impressora liga?",
      yes: 1, no: { fix: "Cheque cabo de força e disjuntor da bancada. Se ok, abrir OS 'manutenção elétrica'." }
    },
    {
      q: "Reconhece a bobina (mostra largura no painel)?",
      yes: 2, no: { fix: "Recarregue: Unload + Load. Se persistir, limpe sensor de mídia (pano seco, área branca à esquerda)." }
    },
    {
      q: "Os bicos (nozzle check) saem completos?",
      yes: 3, no: { fix: "Rotina de limpeza diária (#a3). Se faltar bico após 2 limpezas, abra OS interna 'manutenção cabeça'." }
    },
    {
      q: "O VersaWorks reconhece a impressora (status verde)?",
      yes: 4, no: { fix: "Cabo USB ou rede caiu. Recarregue o serviço Roland Print no Windows (services.msc → restart)." }
    },
    {
      q: "O job inicia mas para no meio?",
      yes: { fix: "Provavelmente perfil ICC errado pro material. Confira artigo #a1 e reaplique perfil correto." },
      no: { fix: "Tudo certo. Refile o job e monitore o primeiro 1m." }
    },
  ],
};

// ─────────────────────────────────────────────────────────────────
// HELPERS
// ─────────────────────────────────────────────────────────────────

function articlesByCat(articles, catId) {
  if (catId === "all") return articles;
  return articles.filter(a => a.cat === catId);
}

function fuzzyMatch(article, q) {
  if (!q) return true;
  const hay = (article.title + " " + article.excerpt + " " + (article.tags || []).join(" ") + " " + article.author).toLowerCase();
  return q.toLowerCase().split(/\s+/).every(part => hay.includes(part));
}

function categoryMeta(id) {
  return CATEGORIES.find(c => c.id === id) || CATEGORIES[0];
}

// ─────────────────────────────────────────────────────────────────
// MAIN COMPONENT
// ─────────────────────────────────────────────────────────────────

function KBPage() {
  const [articles, setArticles] = useState(INITIAL_ARTICLES);
  const [activeId, setActiveId] = useState(null);
  const [cat, setCat] = useState("all");
  const [query, setQuery] = useState("");
  const [tagFilter, setTagFilter] = useState(null);
  const [levelFilter, setLevelFilter] = useState("all");
  const [sortBy, setSortBy] = useState("recent");
  const [paletteOpen, setPaletteOpen] = useState(false);
  const [troubleOpen, setTroubleOpen] = useState(false);
  const [troubleStep, setTroubleStep] = useState(0);
  const [troubleFix, setTroubleFix] = useState(null);
  const [recent, setRecent] = useState(() => {
    try { return JSON.parse(localStorage.getItem("oimpresso.kb.recent") || "[]"); }
    catch (e) { return []; }
  });
  const [toast, setToast] = useState(null);
  const [composer, setComposer] = useState(false);
  const [healthOpen, setHealthOpen] = useState(false);
  const [aiOpen, setAiOpen] = useState(false);
  const [aiHistory, setAiHistory] = useState([]); // [{q, a, sources}]
  const [aiLoading, setAiLoading] = useState(false);
  const [summaryMap, setSummaryMap] = useState({}); // articleId -> string
  const [summarizing, setSummarizing] = useState(null);
  const [pathsOpen, setPathsOpen] = useState(false);
  const [subFilter, setSubFilter] = useState(null);
  const [expandedCats, setExpandedCats] = useState({});
  const [presenting, setPresenting] = useState(false);
  const [mobileView, setMobileView] = useState("list"); // cats | list | reader
  const [troubleEditorOpen, setTroubleEditorOpen] = useState(false);
  const [versionsOpen, setVersionsOpen] = useState(false);
  const [customTroubles, setCustomTroubles] = useState(() => {
    try { return JSON.parse(localStorage.getItem("oimpresso.kb.troubles") || "[]"); } catch (e) { return []; }
  });
  const { versionsFor, snapshot } = (window.useKBVersions ? window.useKBVersions() : { versionsFor: () => [], snapshot: () => {} });
  const { favs, isFav, toggleFav } = (window.useKBFavorites ? window.useKBFavorites() : { favs: [], isFav: () => false, toggleFav: () => {} });
  const [printOpen, setPrintOpen] = useState(false);

  useEffect(() => {
    try { localStorage.setItem("oimpresso.kb.troubles", JSON.stringify(customTroubles)); } catch (e) {}
  }, [customTroubles]);
  const { commentsMap, addComment, removeComment, countFor } = (window.useKBComments ? window.useKBComments() : { commentsMap: {}, addComment: ()=>{}, removeComment: ()=>{}, countFor: ()=>0 });
  const paletteInputRef = useRef(null);
  const filteredRef = useRef([]);
  const openArticleRef = useRef(() => {});

  useEffect(() => {
    try { localStorage.setItem("oimpresso.kb.recent", JSON.stringify(recent.slice(0, 8))); } catch (e) {}
  }, [recent]);

  useEffect(() => {
    if (!toast) return;
    const t = setTimeout(() => setToast(null), 2400);
    return () => clearTimeout(t);
  }, [toast]);

  // ⌘K para abrir palette + J/K + [/] navegação
  useEffect(() => {
    const onKey = (e) => {
      const tag = (document.activeElement || {}).tagName;
      const inField = tag === "INPUT" || tag === "TEXTAREA";
      const mod = e.metaKey || e.ctrlKey;
      if (mod && e.key.toLowerCase() === "k") {
        e.preventDefault();
        setPaletteOpen(true);
        return;
      }
      if (e.key === "Escape") {
        setPaletteOpen(false);
        setTroubleOpen(false);
        setComposer(false);
        setHealthOpen(false);
        setAiOpen(false);
        return;
      }
      if (inField) return;
      if (e.key === "/" && !paletteOpen && !troubleOpen && !composer) {
        e.preventDefault();
        setPaletteOpen(true);
      } else if (e.key === "j" || e.key === "ArrowDown") {
        if (filteredRef.current.length === 0) return;
        e.preventDefault();
        const ids = filteredRef.current.map(a => a.id);
        const i = activeId ? ids.indexOf(activeId) : -1;
        const next = ids[Math.min(ids.length - 1, i + 1)];
        if (next) openArticleRef.current(next);
      } else if (e.key === "k" || e.key === "ArrowUp") {
        if (filteredRef.current.length === 0) return;
        e.preventDefault();
        const ids = filteredRef.current.map(a => a.id);
        const i = activeId ? ids.indexOf(activeId) : ids.length;
        const prev = ids[Math.max(0, i - 1)];
        if (prev) openArticleRef.current(prev);
      } else if (e.key === "n" && !aiOpen) {
        e.preventDefault();
        setComposer({mode:"new"});
      } else if (e.key === "a" && e.shiftKey === false) {
        e.preventDefault();
        setAiOpen(true);
      } else if (e.key === "b" && e.shiftKey === false) {
        if (!activeId) return;
        e.preventDefault();
        toggleFav(activeId);
        setToast(isFav(activeId) ? "Removido dos favoritos" : "Favoritado");
      }
    };
    window.addEventListener("keydown", onKey);
    return () => window.removeEventListener("keydown", onKey);
  }, [paletteOpen, troubleOpen, composer, aiOpen, activeId]);

  useEffect(() => {
    if (paletteOpen) {
      setTimeout(() => paletteInputRef.current && paletteInputRef.current.focus(), 50);
    }
  }, [paletteOpen]);

  const filtered = useMemo(() => {
    let xs = articles;
    xs = articlesByCat(xs, cat);
    if (levelFilter !== "all") xs = xs.filter(a => a.nivel === levelFilter);
    if (tagFilter) xs = xs.filter(a => (a.tags || []).includes(tagFilter));
    if (subFilter && window.kbDeriveSub) xs = xs.filter(a => window.kbDeriveSub(a) === subFilter);
    if (query) xs = xs.filter(a => fuzzyMatch(a, query));

    const sorted = [...xs].sort((a, b) => {
      if (a.pinned && !b.pinned) return -1;
      if (b.pinned && !a.pinned) return 1;
      if (sortBy === "popular") return b.reads - a.reads;
      if (sortBy === "helpful") return b.helpful - a.helpful;
      if (sortBy === "outdated") return b.outdated - a.outdated;
      return 0; // recent é a ordem default
    });
    return sorted;
  }, [articles, cat, query, tagFilter, levelFilter, sortBy]);

  useEffect(() => { filteredRef.current = filtered; }, [filtered]);

  const active = articles.find(a => a.id === activeId);
  const activeIdx = active ? filtered.findIndex(a => a.id === active.id) : -1;
  const prevArticle = activeIdx > 0 ? filtered[activeIdx - 1] : null;
  const nextArticle = activeIdx >= 0 && activeIdx < filtered.length - 1 ? filtered[activeIdx + 1] : null;

  const stats = useMemo(() => {
    const total = articles.length;
    const outdated = articles.filter(a => a.status === "outdated" || a.outdated >= 2).length;
    const totalReads = articles.reduce((s, a) => s + a.reads, 0);
    const linkedOS = articles.reduce((s, a) => s + (a.osLinked || 0), 0);
    return { total, outdated, totalReads, linkedOS };
  }, [articles]);

  const allTags = useMemo(() => {
    const m = {};
    articles.forEach(a => (a.tags || []).forEach(t => { m[t] = (m[t] || 0) + 1; }));
    return Object.entries(m).sort((a, b) => b[1] - a[1]).slice(0, 16);
  }, [articles]);

  const openArticle = (id) => {
    setActiveId(id);
    setArticles(as => as.map(a => a.id === id ? { ...a, reads: a.reads + 1 } : a));
    setRecent(r => [id, ...r.filter(x => x !== id)].slice(0, 8));
    setPaletteOpen(false);
  };
  useEffect(() => { openArticleRef.current = openArticle; }, [articles]);

  const closeArticle = () => setActiveId(null);

  const voteHelpful = (id) => {
    setArticles(as => as.map(a => a.id === id ? { ...a, helpful: a.helpful + 1 } : a));
    setToast("Voto registrado — obrigado!");
  };

  const voteOutdated = (id) => {
    setArticles(as => as.map(a => a.id === id ? { ...a, outdated: a.outdated + 1 } : a));
    setToast("Marcado como possivelmente desatualizado");
  };

  const attachToOS = (id) => {
    setArticles(as => as.map(a => a.id === id ? { ...a, osLinked: (a.osLinked || 0) + 1 } : a));
    setToast("Artigo anexado à OS ativa");
  };

  // Re-verificação: dono confirma que o artigo continua válido
  const reverify = (id) => {
    setArticles(as => as.map(a => a.id === id ? {
      ...a, updated: "agora", outdated: 0, status: a.status === "draft" ? "ok" : a.status === "outdated" ? "ok" : a.status,
    } : a));
    setToast("Artigo re-verificado e marcado como fresco");
  };

  // Summarize via window.claude — guardar resultado em summaryMap
  const summarize = async (id) => {
    const a = articles.find(x => x.id === id);
    if (!a) return;
    if (summaryMap[id]) { setToast("Resumo já disponível abaixo"); return; }
    setSummarizing(id);
    try {
      const text = window.kbBuildArticleText ? window.kbBuildArticleText(a) : a.excerpt;
      const result = await window.claude.complete(
`Resuma este artigo da base de conhecimento de uma gráfica em 3 bullet points objetivos, em português brasileiro, focando no que o operador do balcão precisa lembrar na prática. Sem floreio.

ARTIGO: ${a.title}

${text}

FORMATO:
- bullet 1
- bullet 2
- bullet 3`);
      setSummaryMap(m => ({ ...m, [id]: result }));
      setToast("Resumo gerado pela IA");
    } catch (e) {
      setToast("Falha ao gerar resumo: " + (e.message || "erro"));
    } finally {
      setSummarizing(null);
    }
  };

  // Save final article from full composer (com snapshot da versão antiga)
  const saveArticle = (draft) => {
    const id = draft.id || ("a" + Date.now());
    const tags = typeof draft.tags === "string"
      ? draft.tags.split(",").map(s => s.trim()).filter(Boolean)
      : (draft.tags || []);

    if (draft.id) {
      const existing = articles.find(a => a.id === draft.id);
      if (existing && snapshot) snapshot(existing);
    }

    const novo = {
      id,
      cat: draft.cat,
      nivel: draft.nivel || "iniciante",
      equip: draft.equip || "—",
      title: draft.title || "Rascunho sem título",
      excerpt: draft.excerpt || "—",
      author: draft.author || "você",
      updated: "agora",
      reads: draft.reads || 0,
      helpful: draft.helpful || 0,
      outdated: 0,
      osLinked: draft.osLinked || 0,
      readTime: Math.max(2, Math.ceil((draft.body || []).reduce((s, b) => s + (b.t ? b.t.length : (b.items ? b.items.join(" ").length : 0)), 0) / 800)),
      status: "ok",
      tags,
      body: (draft.body && draft.body.length) ? draft.body : [{ kind: "para", t: draft.excerpt || "—" }],
    };
    setArticles(as => {
      const exists = as.some(a => a.id === id);
      return exists ? as.map(a => a.id === id ? { ...a, ...novo } : a) : [novo, ...as];
    });
    setComposer(false);
    setToast(draft.id ? "Artigo atualizado · versão anterior guardada" : "Artigo publicado: " + novo.title);
    openArticle(id);
  };

  const restoreVersion = (snap) => {
    const id = active.id;
    snapshot(active);
    setArticles(as => as.map(a => a.id === id ? {
      ...a, title: snap.title, excerpt: snap.excerpt, body: snap.body,
      tags: snap.tags, status: snap.status, updated: "agora",
    } : a));
    setVersionsOpen(false);
    setToast("Versão restaurada · a anterior virou histórico");
  };

  const saveCustomTrouble = (payload) => {
    setCustomTroubles(ts => {
      const exists = ts.some(t => t.id === payload.id);
      return exists ? ts.map(t => t.id === payload.id ? payload : t) : [...ts, payload];
    });
    setTroubleEditorOpen(false);
    setToast("Troubleshoot salvo: " + payload.title);
  };

  return (
    <div className="os-page kb-page" data-screen-label="01 Base de Conhecimento">
      <header className="os-page-h kb-page-h">
        <div className="os-page-h-l">
          <h1>Base de Conhecimento</h1>
          <p>
            {stats.total} artigos · {stats.totalReads.toLocaleString("pt-BR")} leituras totais ·
            {" "}{stats.linkedOS} vínculos com OS
            {stats.outdated > 0 && <> · <b style={{color: "oklch(0.50 0.16 25)"}}>{stats.outdated} desatualizados</b></>}
          </p>
        </div>
        <div className="os-page-h-r">
          <button className="os-btn ghost" onClick={() => setPathsOpen(true)}>Trilhas</button>
          <button className="os-btn ghost" onClick={() => setAiOpen(true)}>
            <span style={{color:"oklch(0.55 0.13 240)", fontWeight: 700, marginRight: 4}}>✦</span>Perguntar ao KB
          </button>
          <button className="os-btn ghost" onClick={() => setHealthOpen(true)}>Saúde do KB</button>
          <button className="os-btn ghost" onClick={() => { setTroubleOpen(true); setTroubleStep(0); setTroubleFix(null); }}>
            Troubleshooter
          </button>
          <button className="os-btn ghost" onClick={() => setPaletteOpen(true)}>
            <span className="kb-kbd">⌘K</span> Buscar
          </button>
          <button className="os-btn primary" onClick={() => setComposer({mode:"new"})}>+ Novo artigo</button>
        </div>
      </header>

      <div className="os-stats kb-stats">
        <div className="os-stat">
          <small>Mais lido este mês</small>
          <b>Limpeza diária Roland VS-540</b>
          <span className="fin-stat-hint">312 leituras · 71 úteis</span>
        </div>
        <div className="os-stat">
          <small>Pinados no topo</small>
          <b className="mono">{articles.filter(a => a.pinned).length}</b>
          <span className="fin-stat-hint">artigos essenciais marcados</span>
        </div>
        <div className="os-stat">
          <small>Recentemente atualizados</small>
          <b className="mono">{articles.filter(a => /dias|semana/.test(a.updated)).length}</b>
          <span className="fin-stat-hint">últimos 14 dias</span>
        </div>
        <div className={"os-stat" + (stats.outdated > 0 ? " warn" : "")}>
          <small>Precisam de revisão</small>
          <b className="mono">{stats.outdated}</b>
          <span className="fin-stat-hint">marcados desatualizados</span>
        </div>
      </div>

      <div className="kb-mobile-tabs">
        <button className={"kb-mobile-tab" + (mobileView === "cats" ? " active" : "")}
                onClick={() => setMobileView("cats")}>
          Categorias <span className="kb-mtab-n">{CATEGORIES.length}</span>
        </button>
        <button className={"kb-mobile-tab" + (mobileView === "list" ? " active" : "")}
                onClick={() => setMobileView("list")}>
          Lista <span className="kb-mtab-n">{filtered.length}</span>
        </button>
        <button className={"kb-mobile-tab" + (mobileView === "reader" ? " active" : "")}
                onClick={() => setMobileView("reader")} disabled={!active}>
          Leitor
        </button>
      </div>

      <div className="kb-tri" data-mobile-view={mobileView}>
        {/* COLUNA 1 — Categorias */}
        <aside className="kb-side">
          <div className="kb-side-section">
            <small>Categorias</small>
            <ul>
              {CATEGORIES.map(c => {
                const count = articlesByCat(articles, c.id).length;
                const active = cat === c.id;
                const subs = (window.KB_SUBCATS && window.KB_SUBCATS[c.id]) || [];
                const expanded = !!expandedCats[c.id];
                const hasSub = subs.length > 0 && c.id !== "all";
                return (
                  <li key={c.id}>
                    <button
                      className={"kb-side-btn" + (active ? " active" : "") + (hasSub ? " has-sub" : "")}
                      onClick={() => {
                        if (hasSub) {
                          setExpandedCats(ex => ({ ...ex, [c.id]: !ex[c.id] }));
                        }
                        setCat(c.id);
                        setSubFilter(null);
                        setActiveId(null);
                      }}
                      style={active ? { borderLeftColor: `oklch(0.55 0.13 ${c.hue})` } : null}>
                      {hasSub && (
                        <span className={"kb-side-caret" + (expanded ? " open" : "")}>›</span>
                      )}
                      <span className="kb-side-dot" style={{ background: `oklch(0.62 0.13 ${c.hue})` }}/>
                      <span className="kb-side-l">{c.label}</span>
                      <span className="kb-side-n">{count}</span>
                    </button>
                    {hasSub && expanded && (
                      <ul className="kb-sub-list">
                        {subs.map(s => {
                          const n = articles.filter(a => a.cat === c.id && window.kbDeriveSub(a) === s.id).length;
                          if (n === 0) return null;
                          const isActive = cat === c.id && subFilter === s.id;
                          return (
                            <li key={s.id}>
                              <button
                                className={"kb-sub-btn" + (isActive ? " active" : "")}
                                onClick={(e) => {
                                  e.stopPropagation();
                                  setCat(c.id);
                                  setSubFilter(isActive ? null : s.id);
                                  setActiveId(null);
                                }}>
                                <span className="kb-sub-l">{s.label}</span>
                                <span className="kb-sub-n">{n}</span>
                              </button>
                            </li>
                          );
                        })}
                      </ul>
                    )}
                  </li>
                );
              })}
            </ul>
          </div>

          <div className="kb-side-section">
            <small>Meus favoritos</small>
            {favs.length === 0 ? (
              <p className="kb-side-empty">Marque artigos com a estrela ou tecla B.</p>
            ) : (
              <ul className="kb-side-fav-list">
                {favs.slice(0, 8).map(id => {
                  const a = articles.find(x => x.id === id);
                  if (!a) return null;
                  return (
                    <li key={id}>
                      <button className="kb-side-fav-btn" onClick={() => openArticle(id)}>
                        <svg width="11" height="11" viewBox="0 0 24 24" fill="currentColor" stroke="currentColor" strokeWidth="2" strokeLinejoin="round"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
                        <span className="kb-side-fav-t">{a.title}</span>
                      </button>
                    </li>
                  );
                })}
              </ul>
            )}
          </div>

          <div className="kb-side-section">
            <small>Recentes</small>
            {recent.length === 0 ? (
              <p className="kb-side-empty">Nenhum acesso ainda.</p>
            ) : (
              <ul>
                {recent.map(id => {
                  const a = articles.find(x => x.id === id);
                  if (!a) return null;
                  return (
                    <li key={id}>
                      <button className="kb-side-recent" onClick={() => openArticle(id)}>
                        <span className="kb-side-recent-t">{a.title}</span>
                        <span className="kb-side-recent-m">{a.readTime}min</span>
                      </button>
                    </li>
                  );
                })}
              </ul>
            )}
          </div>

          <div className="kb-side-section">
            <small>Etiquetas populares</small>
            <div className="kb-tags-cloud">
              {allTags.map(([t, n]) => (
                <button
                  key={t}
                  className={"kb-tag" + (tagFilter === t ? " active" : "")}
                  onClick={() => setTagFilter(tagFilter === t ? null : t)}>
                  {t} <span className="kb-tag-n">{n}</span>
                </button>
              ))}
            </div>
          </div>

          <div className="kb-side-section kb-shortcuts">
            <small>Atalhos</small>
            <dl>
              <dt><span className="kb-kbd">⌘K</span> ou <span className="kb-kbd">/</span></dt><dd>Buscar</dd>
              <dt><span className="kb-kbd">Esc</span></dt><dd>Fechar</dd>
              <dt><span className="kb-kbd">N</span></dt><dd>Novo artigo</dd>
            </dl>
          </div>
        </aside>

        {/* COLUNA 2 — Lista de artigos */}
        <section className="kb-list">
          <div className="kb-list-h">
            <div className="kb-list-h-l">
              <b>{categoryMeta(cat).label}</b>
              <span className="kb-list-count">{filtered.length} artigos</span>
            </div>
            <div className="kb-list-h-r">
              <div className="kb-segmented">
                {[
                  { id: "recent",   label: "Recentes" },
                  { id: "popular",  label: "Mais lidos" },
                  { id: "helpful",  label: "Mais úteis" },
                  { id: "outdated", label: "A revisar" },
                ].map(s => (
                  <button
                    key={s.id}
                    className={"kb-seg-btn" + (sortBy === s.id ? " active" : "")}
                    onClick={() => setSortBy(s.id)}>{s.label}</button>
                ))}
              </div>
            </div>
          </div>

          {tagFilter && (
            <div className="kb-filter-pill">
              filtrando: <b>{tagFilter}</b>
              <button onClick={() => setTagFilter(null)}>×</button>
            </div>
          )}

          <div className="kb-list-body">
            {filtered.length === 0 ? (
              <div className="kb-empty">
                <p>Nenhum artigo nesta combinação de filtros.</p>
                <button className="os-btn ghost" onClick={() => { setCat("all"); setTagFilter(null); setQuery(""); }}>Limpar filtros</button>
              </div>
            ) : (
              filtered.map(a => {
                const cm = categoryMeta(a.cat);
                const nm = NIVEIS.find(n => n.id === a.nivel);
                const isOutdated = a.status === "outdated" || a.outdated >= 2;
                return (
                  <article
                    key={a.id}
                    className={"kb-row" + (activeId === a.id ? " active" : "") + (a.pinned ? " pinned" : "") + (isOutdated ? " outdated" : "")}
                    onClick={() => { openArticle(a.id); setMobileView("reader"); }}>
                    <div className="kb-row-top">
                      <span className="kb-cat-pill" style={{ background: `oklch(0.94 0.05 ${cm.hue})`, color: `oklch(0.36 0.10 ${cm.hue})` }}>
                        {cm.label}
                      </span>
                      {nm && <span className="kb-level" style={{ color: `oklch(0.50 0.13 ${nm.hue})` }}>{nm.label}</span>}
                      {a.equip && a.equip !== "—" && <span className="kb-equip">{a.equip}</span>}
                      {a.pinned && <span className="kb-pin">fixo</span>}
                      {isOutdated && <span className="kb-warn-pill">revisar</span>}
                    </div>
                    <h3 className="kb-row-title">{a.title}</h3>
                    <p className="kb-row-excerpt">{a.excerpt}</p>
                    <div className="kb-row-meta">
                      <span>{a.author}</span>
                      <span className="kb-sep">·</span>
                      <span>{a.updated}</span>
                      <span className="kb-sep">·</span>
                      <span className="mono">{a.readTime} min</span>
                      <span className="kb-sep">·</span>
                      <span>{a.reads} leituras</span>
                      {a.osLinked > 0 && (
                        <>
                          <span className="kb-sep">·</span>
                          <span className="kb-os-link">{a.osLinked} OS vinculadas</span>
                        </>
                      )}
                    </div>
                  </article>
                );
              })
            )}
          </div>
        </section>

        {/* COLUNA 3 — Leitor / detalhe */}
        <section className="kb-reader">
          {!active ? (
            <div className="kb-reader-empty">
              <div className="kb-reader-empty-icon">≡</div>
              <h3>Selecione um artigo</h3>
              <p>Ou tecle <span className="kb-kbd">⌘K</span> para buscar em todo o KB.</p>
              <div className="kb-reader-empty-quick">
                <small>Sugestões</small>
                {articles.filter(a => a.pinned).slice(0, 3).map(a => (
                  <button key={a.id} className="kb-quick" onClick={() => openArticle(a.id)}>
                    <b>{a.title}</b>
                    <span>{a.readTime} min · {a.author}</span>
                  </button>
                ))}
              </div>
            </div>
          ) : (
            <ArticleReader
              article={active}
              articles={articles}
              prev={prevArticle}
              next={nextArticle}
              summary={summaryMap[active.id]}
              summarizing={summarizing === active.id}
              comments={commentsMap[active.id] || {}}
              onAddComment={(blockIdx, text) => addComment(active.id, blockIdx, text)}
              onRemoveComment={(blockIdx, idx) => removeComment(active.id, blockIdx, idx)}
              onClose={closeArticle}
              onHelpful={() => voteHelpful(active.id)}
              onOutdated={() => voteOutdated(active.id)}
              onAttach={() => attachToOS(active.id)}
              onTag={(t) => setTagFilter(t)}
              onPrev={() => prevArticle && openArticle(prevArticle.id)}
              onNext={() => nextArticle && openArticle(nextArticle.id)}
              onSummarize={() => summarize(active.id)}
              onReverify={() => reverify(active.id)}
              onEdit={() => setComposer({mode:"edit", article: active})}
              onPresent={() => setPresenting(true)}
              onPrint={() => setPrintOpen(true)}
              onHistory={() => setVersionsOpen(true)}
              historyCount={versionsFor(active.id).length}
              isFav={isFav(active.id)}
              onToggleFav={() => toggleFav(active.id)}
              onPick={openArticle}/>
          )}
        </section>
      </div>

      {/* Command palette ⌘K */}
      {paletteOpen && (
        <CommandPalette
          articles={articles}
          inputRef={paletteInputRef}
          onSelect={openArticle}
          onAskAI={(q) => { setAiOpen(true); /* IA recebe foco e usuário continua digitando */ }}
          onClose={() => setPaletteOpen(false)}/>
      )}

      {/* Troubleshooter — agora usando a biblioteca */}
      {troubleOpen && window.KBTroubleshooterDialog && (
        <window.KBTroubleshooterDialog
          customTroubles={customTroubles}
          onCreateNew={() => setTroubleEditorOpen(true)}
          onPickArticle={(id) => { setTroubleOpen(false); openArticle(id); }}
          onClose={() => setTroubleOpen(false)}/>
      )}
      {/* fallback se kb-trouble-lib não tiver carregado */}
      {troubleOpen && !window.KBTroubleshooterDialog && (
        <TroubleDialog
          step={troubleStep}
          fix={troubleFix}
          onAnswer={(ans) => {
            const step = TROUBLE.steps[troubleStep];
            const next = ans ? step.yes : step.no;
            if (typeof next === "number") setTroubleStep(next);
            else setTroubleFix(next.fix);
          }}
          onRestart={() => { setTroubleStep(0); setTroubleFix(null); }}
          onClose={() => setTroubleOpen(false)}/>
      )}

      {/* Composer de novo artigo / edição (full block editor) */}
      {composer && window.KBComposer && (
        <window.KBComposer
          initial={composer.mode === "edit" ? {
            ...composer.article,
            tags: (composer.article.tags || []).join(", "),
          } : null}
          onClose={() => setComposer(false)}
          onSave={saveArticle}/>
      )}

      {/* Trilhas de aprendizado */}
      {pathsOpen && window.KBPathsDialog && (
        <window.KBPathsDialog
          articles={articles}
          onPick={openArticle}
          onClose={() => setPathsOpen(false)}/>
      )}

      {/* Modo apresentação */}
      {presenting && active && window.KBPresenter && (
        <window.KBPresenter
          article={active}
          onClose={() => setPresenting(false)}
          onPickArticle={(id) => { setPresenting(false); openArticle(id); }}/>
      )}

      {/* Editor visual de troubleshoot */}
      {troubleEditorOpen && window.KBTroubleEditor && (
        <window.KBTroubleEditor
          initial={typeof troubleEditorOpen === "object" ? troubleEditorOpen : null}
          onSave={saveCustomTrouble}
          onClose={() => setTroubleEditorOpen(false)}/>
      )}

      {/* Imprimir SOP com header Oimpresso */}
      {printOpen && active && window.KBPrintSOP && (
        <window.KBPrintSOP
          article={active}
          onClose={() => setPrintOpen(false)}/>
      )}

      {/* Histórico de versões */}
      {versionsOpen && active && window.KBVersionsDialog && (
        <window.KBVersionsDialog
          articleId={active.id}
          articles={articles}
          versions={versionsFor(active.id)}
          onRestore={restoreVersion}
          onClose={() => setVersionsOpen(false)}/>
      )}

      {/* AI Dialog (modo perguntar ao KB) */}
      {aiOpen && window.KBAIDialog && (
        <window.KBAIDialog
          mode="ask"
          articles={articles}
          onClose={() => setAiOpen(false)}/>
      )}

      {/* Painel de saúde do KB */}
      {healthOpen && (
        <HealthPanel
          articles={articles}
          onPick={openArticle}
          onClose={() => setHealthOpen(false)}/>
      )}

      {toast && <div className="kb-toast">✓ {toast}</div>}
    </div>
  );
}

// ─────────────────────────────────────────────────────────────────
// SUB-COMPONENTES
// ─────────────────────────────────────────────────────────────────

// Classifica frescor do artigo a partir de a.updated
function freshnessLevel(updated) {
  if (!updated) return { level: "fresh", label: "novo" };
  const s = updated.toLowerCase();
  if (/agora|hoje|min/.test(s)) return { level: "fresh", label: "novo" };
  if (/dia/.test(s)) {
    const n = parseInt(s.match(/\d+/) || ["1"], 10);
    return n <= 7 ? { level: "fresh", label: "fresco" } : { level: "aging", label: "recente" };
  }
  if (/semana/.test(s)) {
    const n = parseInt(s.match(/\d+/) || ["1"], 10);
    return n <= 3 ? { level: "aging", label: "recente" } : { level: "stale", label: "parado" };
  }
  if (/mês|mes/.test(s)) {
    const n = parseInt(s.match(/\d+/) || ["1"], 10);
    return n <= 2 ? { level: "stale", label: "parado" } : { level: "expired", label: "expirado" };
  }
  return { level: "expired", label: "expirado" };
}

// Helper exposto para a IA construir o texto
window.kbBuildArticleText = function(a) {
  return (a.body || []).map(b => {
    if (b.kind === "para") return b.t;
    if (b.kind === "h2") return "## " + b.t;
    if (b.kind === "list") return (b.items || []).map(i => "- " + i).join("\n");
    if (b.kind === "callout") return "> " + (b.tone || "info").toUpperCase() + ": " + b.t;
    return "";
  }).join("\n\n");
};

function ArticleReader({ article, articles, prev, next, summary, summarizing,
                        comments, onAddComment, onRemoveComment,
                        onClose, onHelpful, onOutdated, onAttach, onTag,
                        onPrev, onNext, onSummarize, onReverify, onEdit, onPresent, onPrint, onHistory, historyCount,
                        isFav, onToggleFav, onPick }) {
  const cm = categoryMeta(article.cat);
  const nm = NIVEIS.find(n => n.id === article.nivel);
  const headings = article.body.filter(b => b.kind === "h2").map(b => b.t);
  const isOutdated = article.status === "outdated" || article.outdated >= 2;
  const fresh = freshnessLevel(article.updated);
  const totalComments = Object.values(comments || {}).reduce((s, b) => s + b.length, 0);

  return (
    <article className="kb-article" data-screen-label="02 Leitor de artigo">
      <header className="kb-art-h">
        <div className="kb-art-eyebrow">
          <span className="kb-cat-pill" style={{ background: `oklch(0.94 0.05 ${cm.hue})`, color: `oklch(0.36 0.10 ${cm.hue})` }}>
            {cm.label}
          </span>
          {nm && <span className="kb-level" style={{ color: `oklch(0.50 0.13 ${nm.hue})` }}>{nm.label}</span>}
          {article.equip && article.equip !== "—" && <span className="kb-equip">{article.equip}</span>}
          {article.pinned && <span className="kb-pin">fixo</span>}
          {isOutdated && <span className="kb-warn-pill">precisa revisão</span>}
          <span className={"kb-fresh " + fresh.level}>
            <span className="kb-fresh-dot"/>{fresh.label}
          </span>
          {window.KBFavStar && (
            <window.KBFavStar active={isFav} onClick={onToggleFav} size={14}/>
          )}
          <span className="kb-nav-arrows">
            <button className="kb-nav-arrow" onClick={onPrev} disabled={!prev} title={prev ? "Anterior: " + prev.title : "Sem anterior"}>‹</button>
            <button className="kb-nav-arrow" onClick={onNext} disabled={!next} title={next ? "Próximo: " + next.title : "Sem próximo"}>›</button>
          </span>
          <button className="kb-x" onClick={onClose}>×</button>
        </div>
        <h2>{article.title}</h2>
        <div className="kb-art-meta">
          <span>{article.author}</span>
          <span className="kb-sep">·</span>
          <span>atualizado {article.updated}</span>
          <span className="kb-sep">·</span>
          <span className="mono">{article.readTime} min de leitura</span>
          <span className="kb-sep">·</span>
          <span>{article.reads} leituras</span>
        </div>
      </header>

      {headings.length > 0 && (
        <div className="kb-art-toc">
          <small>Nesta página</small>
          <ol>
            {headings.map((h, i) => <li key={i}>{h}</li>)}
          </ol>
        </div>
      )}

      {summary && (
        <div className="kb-art-summary">
          <div className="kb-art-summary-h">
            <span style={{color:"oklch(0.55 0.13 240)", fontWeight: 700}}>✦</span>
            <small>Resumo gerado por IA</small>
          </div>
          <div className="kb-ai-a" dangerouslySetInnerHTML={{__html: window.kbRenderMD ? window.kbRenderMD(summary) : summary}}/>
        </div>
      )}

      <div className="kb-art-body">
        {article.body.map((b, i) => {
          let inner = null;
          if (b.kind === "para") inner = <p>{window.kbLinkifyText ? window.kbLinkifyText(b.t, onPick) : b.t}</p>;
          else if (b.kind === "h2") inner = <h3>{b.t}</h3>;
          else if (b.kind === "list") inner = (
            <ol className="kb-art-list">
              {b.items.map((it, j) => <li key={j}>{window.kbLinkifyText ? window.kbLinkifyText(it, onPick) : it}</li>)}
            </ol>
          );
          else if (b.kind === "callout") inner = (
            <div className={"kb-callout kb-callout--" + (b.tone || "info")}>
              <span className="kb-callout-icon">{b.tone === "bad" ? "✕" : b.tone === "warn" ? "!" : b.tone === "ok" ? "✓" : "i"}</span>
              <p>{window.kbLinkifyText ? window.kbLinkifyText(b.t, onPick) : b.t}</p>
            </div>
          );
          else if (b.kind === "image" && window.KBImageBlockView) inner = <window.KBImageBlockView block={b}/>;
          if (!inner) return null;

          const blockComments = (comments && comments[i]) || [];
          if (window.KBCommentBlock) {
            return (
              <window.KBCommentBlock
                key={i}
                articleId={article.id}
                blockIdx={i}
                comments={blockComments}
                onAdd={(text) => onAddComment(i, text)}
                onRemove={(idx) => onRemoveComment(i, idx)}>
                {inner}
              </window.KBCommentBlock>
            );
          }
          return <React.Fragment key={i}>{inner}</React.Fragment>;
        })}
      </div>

      <footer className="kb-art-foot">
        <div className="kb-art-tags">
          {(article.tags || []).map(t => (
            <button key={t} className="kb-tag" onClick={() => onTag(t)}>{t}</button>
          ))}
        </div>
        <div className="kb-art-vote">
          <small>Este artigo foi útil?</small>
          <button className="kb-vote-btn" onClick={onHelpful}>
            <span>✓ Sim</span> <span className="mono">{article.helpful}</span>
          </button>
          <button className="kb-vote-btn warn" onClick={onOutdated}>
            <span>Desatualizado</span> <span className="mono">{article.outdated}</span>
          </button>
          {totalComments > 0 && (
            <span className="kb-comments-count">
              <span style={{color:"oklch(0.55 0.13 240)", fontWeight:700}}>✎</span>
              {totalComments} comentário{totalComments > 1 ? "s" : ""}
            </span>
          )}
        </div>
        <div className="kb-art-actions">
          <button className="os-btn ghost" onClick={onSummarize} disabled={summarizing}>
            <span style={{color: summarizing ? "var(--text-mute)" : "oklch(0.55 0.13 240)", fontWeight: 700, marginRight: 4}}>✦</span>
            {summarizing ? "Resumindo..." : (summary ? "Resumo já gerado" : "Resumir com IA")}
          </button>
          <button className="os-btn ghost" onClick={onReverify} title="Confirmar que continua válido">Re-verificar</button>
          <button className="os-btn ghost" onClick={onHistory} title="Histórico de versões">
            Histórico{historyCount > 0 && <span className="mono" style={{marginLeft:4, fontSize:10.5, color:"var(--text-mute)"}}>{historyCount}</span>}
          </button>
          <button className="os-btn ghost" onClick={onAttach}>Anexar a uma OS</button>
          <button className="os-btn ghost" onClick={onPresent} title="Modo apresentação (slides)">Apresentar</button>
          <button className="os-btn ghost" onClick={onPrint} title="Imprimir SOP com header Oimpresso">Imprimir SOP</button>
          <button className="os-btn primary" onClick={onEdit}>Editar</button>
        </div>
      </footer>

      {window.KBRelated && (
        <window.KBRelated
          article={article}
          articles={articles}
          onPick={onPick}/>
      )}
    </article>
  );
}

function CommandPalette({ articles, inputRef, onSelect, onClose, onAskAI }) {
  const [q, setQ] = useState("");
  const [idx, setIdx] = useState(0);

  const results = useMemo(() => {
    if (!q) return articles.slice(0, 8);
    return articles.filter(a => fuzzyMatch(a, q)).slice(0, 12);
  }, [q, articles]);

  useEffect(() => { setIdx(0); }, [q]);

  const onKey = (e) => {
    if (e.key === "ArrowDown") { e.preventDefault(); setIdx(i => Math.min(results.length - 1, i + 1)); }
    if (e.key === "ArrowUp")   { e.preventDefault(); setIdx(i => Math.max(0, i - 1)); }
    if (e.key === "Enter")     { e.preventDefault(); if (results[idx]) onSelect(results[idx].id); }
  };

  return (
    <>
      <div className="kb-palette-back" onClick={onClose}/>
      <div className="kb-palette" role="dialog" aria-label="Busca rápida">
        <div className="kb-palette-input">
          <span className="kb-palette-hint">Buscar</span>
          <input
            ref={inputRef}
            value={q}
            onChange={e => setQ(e.target.value)}
            onKeyDown={onKey}
            placeholder="Procure por título, etiqueta, autor..."/>
          <span className="kb-kbd">esc</span>
        </div>
        <div className="kb-palette-list">
          {results.length === 0 ? (
            <div className="kb-palette-empty-ai">
              <p>Nenhum artigo bate com <b>"{q}"</b>.</p>
              <button className="kb-palette-ask-btn" onClick={() => { onClose(); onAskAI && onAskAI(q); }}>
                <span style={{fontWeight:700}}>✦</span> Perguntar à IA: "{q}"
              </button>
              <p style={{fontSize:11, color:"var(--text-mute)"}}>A IA busca em todo o KB, não só nos títulos.</p>
            </div>
          ) : (
            results.map((a, i) => {
              const cm = categoryMeta(a.cat);
              return (
                <button
                  key={a.id}
                  className={"kb-palette-row" + (i === idx ? " active" : "")}
                  onMouseEnter={() => setIdx(i)}
                  onClick={() => onSelect(a.id)}>
                  <span className="kb-palette-cat" style={{ background: `oklch(0.62 0.13 ${cm.hue})` }}/>
                  <div className="kb-palette-r">
                    <b>{a.title}</b>
                    <span>{cm.label} · {a.author} · {a.readTime} min</span>
                  </div>
                  <span className="kb-palette-arr">↵</span>
                </button>
              );
            })
          )}
        </div>
        <div className="kb-palette-foot">
          <span><span className="kb-kbd">↑↓</span> navegar</span>
          <span><span className="kb-kbd">↵</span> abrir</span>
          <span><span className="kb-kbd">esc</span> fechar</span>
        </div>
      </div>
    </>
  );
}

function TroubleDialog({ step, fix, onAnswer, onRestart, onClose }) {
  const current = TROUBLE.steps[step];
  return (
    <>
      <div className="kb-modal-back" onClick={onClose}/>
      <div className="kb-modal kb-trouble" role="dialog">
        <header className="kb-modal-h">
          <div>
            <small>Troubleshooter · {TROUBLE.equip}</small>
            <h3>{TROUBLE.title}</h3>
          </div>
          <button className="kb-x" onClick={onClose}>×</button>
        </header>
        <div className="kb-trouble-body">
          {!fix ? (
            <>
              <div className="kb-trouble-step">
                <span className="kb-trouble-n">{step + 1}</span>
                <p>{current.q}</p>
              </div>
              <div className="kb-trouble-actions">
                <button className="kb-tb-yes" onClick={() => onAnswer(true)}>Sim</button>
                <button className="kb-tb-no"  onClick={() => onAnswer(false)}>Não</button>
              </div>
              <div className="kb-trouble-path">
                {TROUBLE.steps.map((_, i) => (
                  <span key={i} className={"kb-trouble-dot" + (i <= step ? " on" : "")}/>
                ))}
              </div>
            </>
          ) : (
            <>
              <div className="kb-trouble-fix">
                <small>Solução sugerida</small>
                <p>{fix}</p>
              </div>
              <div className="kb-trouble-actions">
                <button className="os-btn ghost" onClick={onRestart}>Recomeçar</button>
                <button className="os-btn primary" onClick={onClose}>Resolvi, obrigado</button>
              </div>
            </>
          )}
        </div>
      </div>
    </>
  );
}

function ComposerDialog({ onClose, onSave }) {
  const [draft, setDraft] = useState({ title: "", excerpt: "", cat: "producao", tags: "" });
  return (
    <>
      <div className="kb-modal-back" onClick={onClose}/>
      <div className="kb-modal kb-composer" role="dialog">
        <header className="kb-modal-h">
          <div>
            <small>Novo artigo · rascunho</small>
            <h3>Capturar conhecimento</h3>
          </div>
          <button className="kb-x" onClick={onClose}>×</button>
        </header>
        <div className="kb-composer-body">
          <label>
            <small>Título</small>
            <input value={draft.title} onChange={e => setDraft({...draft, title: e.target.value})} placeholder="Ex.: Trocar filtro de ar do compressor"/>
          </label>
          <label>
            <small>Resumo (1 linha)</small>
            <input value={draft.excerpt} onChange={e => setDraft({...draft, excerpt: e.target.value})} placeholder="O que essa pessoa vai aprender em 1 frase"/>
          </label>
          <label>
            <small>Categoria</small>
            <select value={draft.cat} onChange={e => setDraft({...draft, cat: e.target.value})}>
              {CATEGORIES.filter(c => c.id !== "all").map(c => <option key={c.id} value={c.id}>{c.label}</option>)}
            </select>
          </label>
          <label>
            <small>Etiquetas (separadas por vírgula)</small>
            <input value={draft.tags} onChange={e => setDraft({...draft, tags: e.target.value})} placeholder="manutenção, compressor, semanal"/>
          </label>
          <div className="kb-composer-hint">
            <small>O artigo será criado como rascunho — você adiciona o conteúdo depois.</small>
          </div>
        </div>
        <footer className="kb-composer-foot">
          <button className="os-btn ghost" onClick={onClose}>Cancelar</button>
          <button className="os-btn primary" onClick={() => onSave(draft)}>Criar rascunho</button>
        </footer>
      </div>
    </>
  );
}

function HealthPanel({ articles, onPick, onClose }) {
  const outdated = articles.filter(a => a.status === "outdated" || a.outdated >= 2);
  const stale = articles.filter(a => /mes|meses/.test(a.updated)).slice(0, 6);
  const popular = [...articles].sort((a, b) => b.reads - a.reads).slice(0, 5);
  const lonely = articles.filter(a => a.reads < 50 && !a.pinned).slice(0, 5);

  const Bar = ({ title, items, tone }) => (
    <div className={"kb-health-block kb-health--" + (tone || "")}>
      <div className="kb-health-h">
        <b>{title}</b>
        <span className="kb-health-n">{items.length}</span>
      </div>
      <ul>
        {items.length === 0 && <li className="muted">— nenhum —</li>}
        {items.map(a => (
          <li key={a.id}>
            <button onClick={() => { onPick(a.id); onClose(); }}>
              <span>{a.title}</span>
              <span className="kb-health-m">{a.reads} leituras · {a.updated}</span>
            </button>
          </li>
        ))}
      </ul>
    </div>
  );

  return (
    <>
      <div className="kb-modal-back" onClick={onClose}/>
      <div className="kb-modal kb-health" role="dialog">
        <header className="kb-modal-h">
          <div>
            <small>Diagnóstico</small>
            <h3>Saúde do KB</h3>
          </div>
          <button className="kb-x" onClick={onClose}>×</button>
        </header>
        <div className="kb-health-grid">
          <Bar title="Marcados como desatualizados" items={outdated} tone="bad"/>
          <Bar title="Sem atualização há mais de 30 dias" items={stale} tone="warn"/>
          <Bar title="Mais lidos do mês" items={popular} tone="ok"/>
          <Bar title="Solitários (pouco vistos)" items={lonely}/>
        </div>
      </div>
    </>
  );
}

window.KBPage = KBPage;
})();
