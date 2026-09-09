// norte-data.jsx — As 7 cenas do fluxo do caminhão (entrada → retirada).
// Cada cena: o módulo, a persona, o que ela SENTE, a mini-tela, e a COSTURA
// (a passagem pro próximo módulo — o herói). Expõe window.NORTE.
(() => {
const SCENES = [
  {
    id: "recepcao", stage: "var(--stage-slate)", mod: "OFICINA", persona: ["Balcão · ", "Larissa"],
    title: "O caminhão chega ao balcão.",
    felt: "A Larissa olha a placa. Em 2 segundos conhece o caminhão e a frota inteira — sem recadastrar nada.",
    screen: { bar: "oimpresso · oficina / recepção", body: [
      { t: "head", plate: "RBA-2H78", veh: "VW Constellation 24.280", sub: "412.500 km · Frota Boa Esperança", os: "OS #8821 · nova" },
      { t: "sec", v: "Sintoma reportado" },
      { t: "text", v: "Perda de força e fumaça preta na subida da serra. Motorista relata cheiro de queimado." },
      { t: "fields", rows: [["Frota", "Boa Esperança (8 veículos)"], ["Contato", "Anderson · motorista"], ["Telefone", "(34) 9 9988-7766", "mono"], ["Box", "a alocar"]] },
      { t: "note", tone: "accent", text: "Cliente já no CRM — frota, contatos e histórico dos 8 caminhões vieram juntos com a placa." },
    ]},
    seam: { tag: "Costura · Entrada", from: "CRM", to: "Oficina",
      h2: "O cliente nunca é recadastrado.",
      body: "A Larissa digita a placa e o ERP puxa a frota inteira do CRM: contato, telefone, histórico. A OS nasce com contexto — não em branco.",
      auto: ["Zero retrabalho", "placa → ficha completa"] },
  },
  {
    id: "diagnostico", stage: "var(--stage-indigo)", mod: "OFICINA · DVI", persona: ["Oficina · ", "Técnico"],
    title: "O diagnóstico vira orçamento sozinho.",
    felt: "O técnico fotografa, marca o semáforo da vistoria — e o orçamento se escreve, já com peça e serviço separados.",
    screen: { bar: "oimpresso · oficina / diagnóstico · DVI", body: [
      { t: "head", plate: "RBA-2H78", veh: "VW Constellation 24.280", sub: "Box 2 · em diagnóstico", os: "OS #8821" },
      { t: "sec", v: "Vistoria digital · DVI" },
      { t: "dvi", rows: [
        { s: "ok",   b: "Motor · óleo + filtro", n: "nível ok · troca em 8.000 km", tag: ["ok", "ok"] },
        { s: "bad",  b: "Turbina · pressão", n: "vazamento de pressão · troca imediata", tag: ["bad", "crítico"] },
        { s: "warn", b: "Freios · lonas traseiras", n: "40% de vida · trocar agora", tag: ["warn", "atenção"] },
        { s: "warn", b: "Injeção · bico 3", n: "spray irregular · limpeza", tag: ["warn", "atenção"] },
      ]},
      { t: "sec", v: "Orçamento gerado" },
      { t: "kpis", items: [["Peças", "R$ 4.180", "accent"], ["Mão de obra", "R$ 1.260", "pos"], ["Total", "R$ 5.440", ""]] },
    ]},
    seam: { tag: "Costura · Diagnóstico → Orçamento", from: "DVI", to: "Orçamento",
      h2: "Peça e serviço já nascem separados.",
      body: "Cada item do DVI vira linha do orçamento já classificado: peça (futura NF-e) ou serviço (NFS-e). A separação fiscal acontece aqui, no diagnóstico — não na emissão.",
      auto: ["Fiscal certo desde a origem", "NF-e ⟂ NFS-e"] },
  },
  {
    id: "aprovacao", stage: "var(--stage-rose)", mod: "INBOX", persona: ["Cliente · ", "gestor da frota"],
    title: "O cliente aprova pelo WhatsApp.",
    felt: "O gestor da frota responde na conversa. A oficina nem precisou ligar — e a OS destrava sozinha.",
    screen: { bar: "oimpresso · inbox / caixa unificada", body: [
      { t: "note", tone: "lock", text: "Execução travada — aguardando o cliente aprovar o orçamento." },
      { t: "msg", side: "them", text: "Boa tarde! Orçamento do Constellation: turbina + freios + injeção = R$ 5.440. Posso liberar?", when: "14:02" },
      { t: "msg", side: "me", text: "Aprovado 👍 pode tocar, preciso dele sexta.", when: "14:09" },
      { t: "note", tone: "pos", text: "Cliente aprovou → a OS #8821 destravou sozinha. Gate de execução liberado." },
    ]},
    seam: { tag: "Costura crítica · OS ↔ Cliente", from: "Oficina", to: "Inbox",
      h2: "A regra que protege a oficina.",
      body: "O orçamento sai pelo mesmo Inbox do WhatsApp. O cliente aprova na conversa e a OS destrava sozinha — execução não começa sem o sim. A costura mais importante do negócio.",
      auto: ["O gate é a regra", "sem aprovação, sem execução"] },
  },
  {
    id: "execucao", stage: "var(--stage-emerald)", mod: "OFICINA", persona: ["Oficina · ", "Técnico"],
    title: "A execução e o estoque andam juntos.",
    felt: "Box 2, turbina na bancada. Cada peça que entra no caminhão sai do estoque na mesma hora.",
    screen: { bar: "oimpresso · oficina / execução", body: [
      { t: "head", plate: "RBA-2H78", veh: "VW Constellation 24.280", sub: "Box 2 · em execução", os: "OS #8821" },
      { t: "progress", pct: 65, label: "65% · resta ~2h40" },
      { t: "sec", v: "Peças & mão de obra aplicadas" },
      { t: "items", rows: [
        { ic: "peca", b: "Turbina Garrett GT2256", q: "1 un", v: "R$ 3.200" },
        { ic: "peca", b: "Kit lonas de freio traseiro", q: "1 jg", v: "R$ 980" },
        { ic: "serv", b: "Mão de obra · turbina + freios", q: "6 h", v: "R$ 1.260" },
      ]},
      { t: "note", tone: "accent", text: "Cada peça aplicada baixa do estoque (Compras) na hora — e dispara reposição se bater o mínimo." },
    ]},
    seam: { tag: "Costura · Execução → Estoque", from: "Oficina", to: "Compras",
      h2: "O estoque anda junto com a chave de fenda.",
      body: "Quando o mecânico aplica a peça na OS, ela baixa do estoque imediatamente. O Compras já sabe que o turbo saiu — sem inventário paralelo, sem conferência de fim de dia.",
      auto: ["Estoque sempre real", "aplicou = baixou"] },
  },
  {
    id: "venda", stage: "var(--stage-green)", mod: "VENDAS", persona: ["Sistema · ", "Jana"],
    title: "Pronto. E a venda já está lá.",
    felt: "O caminhão fica pronto — e a venda aparece sozinha, com tudo da OS. O balcão só confere e fatura.",
    screen: { bar: "oimpresso · vendas", body: [
      { t: "note", tone: "accent", text: "✨ OS #8821 chegou em 'Pronto p/ retirar' → venda criada automaticamente." },
      { t: "head", plate: "RBA-2H78", veh: "VW Constellation 24.280", sub: "Frota Boa Esperança", os: "Venda #4471 · origin: oficina" },
      { t: "kpis", items: [["Peças (NF-e)", "R$ 4.180", "accent"], ["Serviço (NFS-e)", "R$ 1.260", "pos"], ["Total", "R$ 5.440", ""]] },
      { t: "note", tone: "pos", text: "Itens, valores, cliente e separação fiscal vieram da OS. Ninguém digitou a venda." },
    ]},
    seam: { tag: "Costura · OS → Vendas", from: "Oficina", to: "Vendas",
      h2: "Ninguém digita a venda duas vezes.",
      body: "OS pronta = venda criada. Peças, mão de obra, cliente e a classificação fiscal já vêm da OS. A 'digitação dupla' clássica do ERP simplesmente não existe.",
      auto: ["Venda automática", "OS pronta → venda pronta"] },
  },
  {
    id: "nota", stage: "var(--accent)", mod: "FISCAL", persona: ["Financeiro · ", "Eliana"],
    title: "Duas notas certas, num clique.",
    felt: "A Eliana clica emitir. Peça vira NF-e, serviço vira NFS-e — o ERP já sabia o que era o quê.",
    screen: { bar: "oimpresso · fiscal", body: [
      { t: "head", plate: "RBA-2H78", veh: "VW Constellation 24.280", sub: "Frota Boa Esperança", os: "Venda #4471" },
      { t: "sec", v: "Notas emitidas" },
      { t: "fiscal", rows: [
        { tipo: "NF-e modelo 55 · peças", num: "Nº 001.214 · R$ 4.180,00" },
        { tipo: "NFS-e · mão de obra", num: "Nº 000.087 · R$ 1.260,00" },
      ]},
      { t: "note", tone: "pos", text: "Uma venda, duas notas certas — porque a separação fiscal veio lá do DVI, no diagnóstico." },
    ]},
    seam: { tag: "Costura · Vendas → Fiscal", from: "Vendas", to: "Fiscal",
      h2: "Duas notas, zero decisão na hora.",
      body: "Peça emite NF-e 55, serviço emite NFS-e — e o ERP já sabia o que era o quê desde o DVI. A emissão é um clique, não uma planilha de classificação fiscal.",
      auto: ["SEFAZ num clique", "classificação herdada do DVI"] },
  },
  {
    id: "financeiro", stage: "var(--stage-green)", mod: "FINANCEIRO", persona: ["Financeiro · ", "Eliana + Balcão"],
    title: "O ciclo volta pro começo.",
    felt: "PIX caiu, caixa baixou. E a frota já ganha data pra voltar — que vira o próximo check-in.",
    screen: { bar: "oimpresso · financeiro / caixa", body: [
      { t: "head", plate: "RBA-2H78", veh: "VW Constellation 24.280", sub: "Frota Boa Esperança", os: "Venda #4471 · paga" },
      { t: "kpis", items: [["Recebido", "R$ 5.440", "pos"], ["Forma", "PIX", ""], ["Margem", "38%", "pos"]] },
      { t: "sec", v: "E o ciclo volta pro CRM" },
      { t: "note", tone: "pos", text: "Frota Boa Esperança: +1 serviço no histórico do Constellation. Próxima revisão agendada p/ 432.000 km." },
    ]},
    seam: { tag: "Costura de fechamento · Financeiro → CRM", from: "Financeiro", to: "CRM",
      h2: "O fim alimenta o começo.",
      body: "Pago e baixado no caixa, o serviço entra no histórico da frota no CRM. E o ERP já agenda a próxima revisão — que vira o próximo check-in. O fluxo é um círculo, não uma linha.",
      auto: ["O ciclo se fecha", "financeiro → CRM → próxima OS"] },
  },
];

// rótulos curtos pro spine
const SPINE = ["Recepção", "Diagnóstico", "Aprovação", "Execução", "Venda", "Nota", "Financeiro"];

window.NORTE = { SCENES, SPINE };
})();
