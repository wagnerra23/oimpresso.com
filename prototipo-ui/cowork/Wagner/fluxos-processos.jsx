// fluxos-processos.jsx — catálogo de TODOS os processos do ERP que têm fluxo (FSM):
// estados (com terminais), transições com guarda (condição), efeito e quem pode (ADR 0129: RBAC por
// transição). Inclui o comparativo NF-e × NFS-e e a produção por item com etapas em paralelo.
// Expõe window.FLX_PROC (dados) e window.FluxosProcessos (aba "Processos" de fluxos-page.jsx).
(() => {
const { useState } = React;
const ds = () => window.OfficeImpressoPontoWR2DesignSystem_019dd0 || {};

// tipo do estado: ini (inicial) · nor · esp (espera — parado por algo externo) · ok (terminal feliz) · x (terminal de exceção)
// lado = desvio fora do caminho principal (só aparece na trilha quando é a etapa atual)
const S = (k, l, tipo, lado) => ({ k, l, tipo: tipo || "nor", lado: !!lado });
// transição: de, para (null = ação sem mudar de estado), ação, guarda (só passa se…), efeito, quem
const T = (de, para, acao, guarda, efeito, quem) => ({ de, para, acao, guarda: guarda || "", efeito: efeito || "", quem: quem || "" });

// Onde cada processo aparece no app (rota do shell) — o painel de etapa (fsm-etapa.jsx) está montado nelas.
const TELAS = {
  venda: [["vendas", "Vendas · detalhe da venda"]], producao: [["os", "Ordens de Serviço · detalhe"]], compra: [["compras", "Compras · detalhe da compra"]],
  nfe: [["fiscal-nfe", "Fiscal · NF-e"]], nfse: [["fiscal-nfse", "Fiscal · NFS-e"]], cobranca: [["cobranca", "Cobrança · detalhe da cobrança"]],
  inventario: [["estoque", "Estoque · contagem"]], entrega: [["entregas", "Entregas e instalação"]], ponto: [["ponto", "Ponto · intercorrências"]],
  atendimento: [["inbox", "Atendimento · contexto da conversa"]], contrato: [["recurring", "Cobrança recorrente · assinatura"]],
};
const AREAS = ["Comercial", "Produção", "Fiscal", "Financeiro", "Suprimentos", "Pós-venda", "Pessoas"];

const PROCS = [
  { id: "venda", area: "Comercial", n: "Orçamento e venda", doc: "Orçamento → pedido", prot: true,
    porque: "Do primeiro preço até a venda faturada. Decide quando nasce a OS, a nota e a cobrança.",
    estados: [S("orc", "Orçamento", "ini"), S("env", "Enviado"), S("apr", "Aprovado"), S("fat", "Faturado"), S("ent", "Entregue", "ok"), S("perd", "Perdido", "x"), S("canc", "Cancelado", "x")],
    trans: [
      T("Orçamento", "Enviado", "Enviar ao cliente", "Cliente com contato válido", "Registra o envio; mede abertura (termômetro)", "Vendedor"),
      T("Enviado", "Aprovado", "Cliente aprova", "Validade do orçamento não venceu", "Cria a OS por item · gera as parcelas (sinal + saldo)", "Vendedor · cliente pelo link"),
      T("Enviado", "Perdido", "Marcar perdido", "Motivo obrigatório", "Alimenta o funil (motivo de perda)", "Vendedor"),
      T("Aprovado", "Faturado", "Faturar", "Itens com natureza fiscal definida", "Emite NF-e e/ou NFS-e conforme o item", "Financeiro"),
      T("Faturado", "Entregue", "Concluir", "OS pronta ou entregue", "Abre pesquisa de satisfação em 2 dias", "Expedição"),
      T("Aprovado", "Cancelado", "Cancelar", "Nenhuma nota autorizada · ou notas canceladas antes", "Libera reserva de estoque · estorna sinal", "Gerente"),
    ], liga: ["producao", "nfe", "nfse", "cobranca"] },

  { id: "producao", area: "Produção", n: "OS e produção por item", doc: "Ordem de serviço / OP", prot: true,
    porque: "A OS anda por item: cada item tem o próprio roteiro. A OS só fica pronta quando todos os itens terminam.",
    estados: [S("agl", "Aguardando liberação", "ini"), S("agm", "Aguardando material", "esp", 1), S("fila", "Na fila"), S("exe", "Em execução"), S("pau", "Pausada", "esp", 1), S("ret", "Retrabalho", "nor", 1), S("conc", "Concluída", "ok"), S("canc", "Cancelada", "x")],
    trans: [
      T("Aguardando liberação", "Na fila", "Liberar", "Arte aprovada · sinal pago (se exigido)", "Reserva o material · calcula prazo pela fila", "PCP / sistema"),
      T("Na fila", "Aguardando material", "Aguardar material", "Chave da empresa ligada · falta insumo da receita (saldo do local menos reservas de outras OS e vendas)", "Guarda o que faltava · a etapa não começa", "Sistema, logo depois de liberar"),
      T("Aguardando material", "Na fila", "Conferir material", "Não falta mais nada", "Volta para a fila · a etapa pode começar", "PCP"),
      T("Na fila", "Em execução", "Iniciar etapa", "Operador do setor da etapa", "Começa a contar o tempo (QR ou tela)", "Operador"),
      T("Em execução", "Pausada", "Pausar", "Motivo: falta material · máquina · aguardando cliente", "Para o relógio; alerta se passar do prazo", "Operador"),
      T("Pausada", "Em execução", "Retomar", "", "Volta a contar o tempo", "Operador"),
      T("Em execução", null, "Concluir etapa", "Etapa seguinte do roteiro existe", "Passa para a próxima etapa (ou para as paralelas)", "Operador"),
      T("Em execução", "Retrabalho", "Reprovar no controle", "Motivo + etapa de origem", "Soma custo de refugo à OS", "Acabamento / qualidade"),
      T("Retrabalho", "Em execução", "Refazer", "", "Reabre a etapa de origem", "PCP"),
      T("Em execução", "Concluída", "Concluir item", "Última etapa do roteiro feita", "Baixa o material real · congela custo · se todos os itens: OS pronta", "Operador"),
      T("Aguardando liberação", "Cancelada", "Cancelar item", "Nada consumido · ou consumo lançado como perda", "Libera reserva", "Gerente"),
      T("Aguardando material", "Cancelada", "Cancelar item", "Nada consumido · ou consumo lançado como perda", "Libera reserva", "Gerente"),
    ],
    roteiro: { titulo: "Exemplo: fachada em ACM com letra caixa", trilhas: [["Arte", "Aprovação"], ["Impressão do adesivo", "Laminação"], ["Corte ACM", "Serralheria"]], junta: ["Montagem", "Instalação"] },
    liga: ["venda", "requisicao", "entrega", "cobranca"] },

  { id: "nfe", area: "Fiscal", n: "NF-e · mercadoria (modelo 55)", doc: "Nota fiscal de produto", prot: false,
    porque: "Documenta circulação de mercadoria (ICMS/IPI). Autorizada pela SEFAZ do estado.",
    estados: [S("ras", "Rascunho", "ini"), S("val", "Validada"), S("tra", "Transmitida", "esp"), S("aut", "Autorizada", "ok"), S("rej", "Rejeitada", "nor", 1), S("den", "Denegada", "x"), S("can", "Cancelada", "x"), S("inu", "Inutilizada", "x")],
    trans: [
      T("Rascunho", "Validada", "Validar", "Schema + regras de negócio + certificado A1 válido", "", "Sistema"),
      T("Validada", "Transmitida", "Transmitir", "SEFAZ no ar — senão, contingência (SVC/EPEC)", "Reserva o número da série", "Sistema"),
      T("Transmitida", "Autorizada", "Retorno 100", "", "Envia XML + DANFE ao cliente · baixa estoque (se for a regra) · liga ao título", "SEFAZ"),
      T("Transmitida", "Rejeitada", "Retorno de rejeição", "", "Mostra o código e o campo; volta para correção", "SEFAZ"),
      T("Rejeitada", "Validada", "Corrigir e revalidar", "", "", "Fiscal"),
      T("Transmitida", "Denegada", "Retorno de denegação", "", "Número queimado; avisa o fiscal — irregularidade cadastral", "SEFAZ"),
      T("Autorizada", null, "Carta de correção (CC-e)", "Não corrige valor, imposto, destinatário nem data", "Evento vinculado à nota", "Fiscal"),
      T("Autorizada", "Cancelada", "Cancelar", "Dentro do prazo (em regra 24 h, varia por UF) · mercadoria não circulou", "Estorna estoque · cancela o título se não pago", "Fiscal"),
      T("Rascunho", "Inutilizada", "Inutilizar número", "Número pulado na sequência", "Comunica a faixa à SEFAZ", "Fiscal"),
    ], liga: ["venda", "cobranca", "devolucao"] },

  { id: "nfse", area: "Fiscal", n: "NFS-e · serviço (padrão nacional)", doc: "Nota fiscal de serviço", prot: false,
    porque: "Documenta prestação de serviço (ISS). Desde 1º/01/2026 todo município segue o padrão nacional (LC 214/2025).",
    estados: [S("ras", "Rascunho", "ini"), S("val", "Validada"), S("env", "Enviada", "esp"), S("aut", "Autorizada", "ok"), S("rej", "Rejeitada", "nor", 1), S("can", "Cancelada", "x"), S("sub", "Substituída", "x")],
    trans: [
      T("Rascunho", "Validada", "Validar", "Código do serviço (LC 116) + município de incidência + retenções", "Calcula ISS e retenções", "Sistema"),
      T("Validada", "Enviada", "Enviar DPS", "Emissor nacional ou do município (integrado ao ADN)", "", "Sistema"),
      T("Enviada", "Autorizada", "Retorno de autorização", "", "Envia ao tomador · ajusta o título pelo ISS retido", "Prefeitura / ADN"),
      T("Enviada", "Rejeitada", "Retorno de rejeição", "", "Volta para correção", "Prefeitura / ADN"),
      T("Rejeitada", "Validada", "Corrigir e revalidar", "", "", "Fiscal"),
      T("Autorizada", "Substituída", "Substituir", "Nova NFS-e emitida no lugar desta", "Vincula a substituta · recalcula o título", "Fiscal"),
      T("Autorizada", "Cancelada", "Cancelar", "Regra e prazo do emissor/município; fora do prazo pode exigir análise", "Cancela o título se não pago", "Fiscal"),
    ], liga: ["venda", "cobranca"] },

  { id: "cobranca", area: "Financeiro", n: "Cobrança · título a receber", doc: "Parcela, boleto ou PIX", prot: true,
    porque: "Cada parcela tem vida própria: sinal, saldo, renegociação. O pagamento pode destravar produção e nota.",
    estados: [S("prev", "Previsto", "ini"), S("emi", "Emitido"), S("ven", "Vencido"), S("cob", "Em cobrança", "esp", 1), S("par", "Pago parcial", "nor", 1), S("pag", "Pago"), S("con", "Conciliado", "ok"), S("ren", "Renegociado", "x"), S("can", "Cancelado", "x"), S("per", "Baixado por perda", "x")],
    trans: [
      T("Previsto", "Emitido", "Registrar boleto/PIX", "Conta de cobrança configurada", "Envia ao cliente pelo canal preferido", "Financeiro / sistema"),
      T("Emitido", "Vencido", "Vencimento passou", "D+1 sem pagamento", "", "Sistema"),
      T("Vencido", "Em cobrança", "Iniciar régua", "", "Lembretes em D+1, D+5 e D+15 · tarefa ao vendedor", "Sistema"),
      T("Emitido", "Pago", "Retorno do banco", "Valor recebido ≥ valor do título", "Se for sinal: libera produção · se a regra for nota no pagamento: emite a nota · comissão", "Banco"),
      T("Emitido", "Pago parcial", "Retorno do banco", "Valor recebido < valor do título", "Gera saldo remanescente", "Banco"),
      T("Pago", "Conciliado", "Conciliar extrato", "Lançamento do extrato casado", "Fecha no caixa", "Financeiro"),
      T("Em cobrança", "Renegociado", "Renegociar", "Alçada do gerente", "Cancela este e gera novas parcelas", "Gerente"),
      T("Em cobrança", "Baixado por perda", "Baixar", "Alçada da diretoria · motivo", "Lança perda; bloqueia novo crédito do cliente", "Diretoria"),
    ], liga: ["venda", "producao", "nfe", "nfse"] },

  { id: "pagar", area: "Financeiro", n: "Conta a pagar", doc: "Título a pagar", prot: false,
    porque: "Nasce da compra conferida ou de despesa avulsa; aprovação por alçada antes de pagar.",
    estados: [S("lan", "Lançado", "ini"), S("apr", "Aprovado"), S("agd", "Agendado"), S("pag", "Pago"), S("con", "Conciliado", "ok"), S("can", "Cancelado", "x")],
    trans: [
      T("Lançado", "Aprovado", "Aprovar", "Valor dentro da alçada do aprovador", "", "Gerente / diretoria"),
      T("Aprovado", "Agendado", "Agendar no banco", "Saldo projetado suficiente", "Entra no fluxo de caixa", "Financeiro"),
      T("Agendado", "Pago", "Retorno do banco", "", "Baixa a compra (estágio Pago)", "Banco"),
      T("Pago", "Conciliado", "Conciliar", "", "", "Financeiro"),
    ], liga: ["compra"] },

  { id: "compra", area: "Suprimentos", n: "Compra", doc: "Pedido de compra / NF-e de entrada", prot: true,
    porque: "Já configurável em Compras › Regras das etapas: recebimento atualiza custo e propõe preço.",
    estados: [S("req", "Requisição", "ini"), S("ped", "Pedido"), S("tra", "Em trânsito", "esp"), S("rec", "Recebido"), S("conf", "Conferido"), S("pag", "Pago", "ok"), S("can", "Cancelado", "x")],
    trans: [
      T("Requisição", "Pedido", "Aprovar requisição", "Alçada", "Envia o pedido ao fornecedor", "Comprador"),
      T("Pedido", "Em trânsito", "Marcar em trânsito", "Fornecedor confirmou o envio", "Registra a previsão de chegada", "Comprador"),
      T("Requisição", "Cancelado", "Cancelar", "Motivo obrigatório", "", "Comprador"),
      T("Pedido", "Cancelado", "Cancelar", "Fornecedor avisado", "Libera o orçamento de compra", "Comprador"),
      T("Em trânsito", "Recebido", "Receber", "Quantidade conferida", "Entrada no estoque · custo médio · propõe preço", "Almoxarifado"),
      T("Recebido", "Conferido", "Conferir NF-e de entrada", "Divergência dentro da tolerância", "Gera conta a pagar · manifesta a nota", "Fiscal"),
      T("Conferido", "Pago", "Pagar", "Conta a pagar aprovada na alçada", "Baixa o título · fecha a compra", "Financeiro"),
    ], liga: ["pagar", "requisicao"] },

  { id: "requisicao", area: "Suprimentos", n: "Requisição de material", doc: "Saída do almoxarifado", prot: false,
    porque: "A produção pede o material da OS; o almoxarifado separa e entrega. Rastreia onde cada rolo foi usado.",
    estados: [S("sol", "Solicitada", "ini"), S("sep", "Em separação"), S("ent", "Entregue", "ok"), S("fal", "Em falta", "esp", 1), S("can", "Cancelada", "x")],
    trans: [
      T("Solicitada", "Em separação", "Separar", "Saldo disponível", "Reserva a fração do rolo/lote", "Almoxarifado"),
      T("Solicitada", "Em falta", "Sem saldo", "", "Abre requisição de compra · pausa a etapa da OS", "Sistema"),
      T("Em separação", "Entregue", "Entregar", "", "Baixa do estoque para a OS", "Almoxarifado"),
    ], liga: ["producao", "compra"] },

  { id: "inventario", area: "Suprimentos", n: "Contagem de inventário", doc: "Contagem", prot: true,
    porque: "Contar sem parar a operação; a diferença só ajusta o estoque depois de aprovada.",
    estados: [S("abe", "Aberta", "ini"), S("con", "Contando"), S("rev", "Em revisão"), S("apr", "Ajuste aprovado", "ok"), S("can", "Cancelada", "x")],
    trans: [
      T("Aberta", "Contando", "Iniciar", "Locais escolhidos", "Congela o saldo de referência", "Almoxarifado"),
      T("Contando", "Em revisão", "Fechar contagem", "Todos os itens contados", "Calcula divergência por item", "Almoxarifado"),
      T("Em revisão", "Ajuste aprovado", "Aprovar ajuste", "Divergência acima do limite exige gerente", "Lança o ajuste e o custo da perda", "Gerente"),
    ], liga: [] },

  { id: "entrega", area: "Pós-venda", n: "Entrega e instalação", doc: "Romaneio / ordem de instalação", prot: false,
    porque: "Do pronto até o cliente assinar. Instalação em altura ou fachada exige agenda e registro de campo.",
    estados: [S("pro", "Pronto", "ini"), S("agd", "Agendado"), S("cam", "Em campo"), S("ins", "Instalado"), S("ent", "Entregue", "ok"), S("rea", "Reagendado", "esp", 1)],
    trans: [
      T("Pronto", "Agendado", "Agendar", "Equipe e veículo livres na janela", "Avisa o cliente com data e hora", "Expedição"),
      T("Agendado", "Em campo", "Sair para o campo", "Material e equipe conferidos", "Avisa o cliente que a equipe saiu", "Instalador"),
      T("Reagendado", "Agendado", "Confirmar nova data", "", "Novo aviso ao cliente", "Expedição"),
      T("Agendado", "Reagendado", "Reagendar", "Motivo (chuva, acesso, cliente ausente)", "Novo aviso ao cliente", "Expedição"),
      T("Em campo", "Instalado", "Concluir instalação", "Foto do serviço + assinatura do cliente", "Relatório de instalação na OS", "Instalador"),
      T("Instalado", "Entregue", "Fechar", "", "Protocolo de entrega · pesquisa em 2 dias", "Sistema"),
    ], liga: ["producao", "venda"] },

  { id: "devolucao", area: "Pós-venda", n: "Devolução, troca e garantia", doc: "Ocorrência pós-venda", prot: false,
    porque: "Defeito, erro de arte ou troca. Decide se refaz, devolve dinheiro (total ou parcial) ou troca por crédito, e termina sempre em Encerrada ou Negada.",
    estados: [S("abe", "Aberta", "ini"), S("ana", "Em análise"), S("ref", "Refazer"), S("dev", "Devolver valor"), S("tro", "Trocar com crédito"), S("enc", "Encerrada", "ok"), S("neg", "Negada", "x")],
    trans: [
      T("Aberta", "Em análise", "Analisar", "Fotos ou peça recebida", "", "Atendimento"),
      T("Em análise", "Refazer", "Aprovar refação", "Dentro da garantia", "Cria OS de retrabalho sem cobrança", "Gerente"),
      T("Em análise", "Devolver valor", "Aprovar devolução", "", "NF-e de devolução (se houve NF-e) · estorno do título", "Gerente"),
      T("Em análise", "Trocar com crédito", "Aprovar troca com crédito", "Cliente identificado", "", "Gerente"),
      T("Em análise", "Negada", "Negar", "Motivo obrigatório", "Avisa o cliente com o motivo", "Gerente"),
      T("Refazer", "Encerrada", "Concluir refação", "OS de retrabalho entregue", "Encerra a ocorrência sem cobrança", "Produção"),
      T("Devolver valor", null, "Registrar itens devolvidos", "Quantidade até a vendida (devolução total ou parcial)", "Volta o estoque dos itens devolvidos", "Atendimento"),
      T("Devolver valor", "Encerrada", "Estornar valor", "NF-e de devolução emitida, se a venda teve NF-e", "Estorna o título ou devolve o dinheiro", "Financeiro"),
      T("Trocar com crédito", "Encerrada", "Lançar crédito", "", "Credita o saldo do cliente (validade de 6 meses)", "Atendimento"),
    ], liga: ["producao", "nfe", "cobranca"] },

  { id: "devolucao-compra", area: "Suprimentos", n: "Devolução de compra", doc: "Devolução ao fornecedor", prot: false,
    porque: "Material com defeito ou a mais volta ao fornecedor, total ou parcialmente, e o crédito ou estorno fecha a devolução.",
    estados: [S("abe", "Aberta", "ini"), S("reg", "Registrada"), S("enc", "Encerrada", "ok"), S("can", "Cancelada", "x")],
    trans: [
      T("Aberta", "Registrada", "Registrar devolução ao fornecedor", "Quantidade até a comprada", "Baixa o estoque dos itens devolvidos", "Compras"),
      T("Registrada", "Encerrada", "Receber crédito ou estorno", "", "Quita a devolução contra o título a pagar", "Financeiro"),
      T("Registrada", "Cancelada", "Excluir devolução", "", "Desfaz a devolução e volta o estoque", "Compras"),
    ], liga: ["compra", "pagar"] },

  { id: "os-reparo", area: "Produção", n: "OS de reparo", doc: "Ordem de serviço de reparo (Repair)", prot: false, erp: "os_reparo_padrao",
    porque: "Equipamento entra, é diagnosticado, o cliente aprova o orçamento e o reparo é executado e entregue.",
    estados: [S("recebido_para_diagnostico", "Recebido pra diagnóstico", "ini"), S("em_diagnostico", "Em diagnóstico"), S("diagnosticado_aguardando_aprovacao", "Diagnosticado — aguardando aprovação"), S("orcamento_aprovado", "Orçamento aprovado"), S("orcamento_rejeitado", "Orçamento rejeitado", "x"), S("aguardando_pecas", "Aguardando peças"), S("pecas_chegadas", "Peças chegaram"), S("em_execucao", "Em execução"), S("pausado", "Pausado"), S("concluido_aguardando_retirada", "Concluído — aguardando retirada"), S("entregue_completo", "Entregue ao cliente", "ok"), S("cancelado", "Cancelado", "x"), S("garantia_acionada", "Garantia acionada", "x")],
    trans: [
      T("Recebido pra diagnóstico", "Em diagnóstico", "Iniciar diagnóstico", "", "", "Técnico"),
      T("Em diagnóstico", "Diagnosticado — aguardando aprovação", "Finalizar diagnóstico e gerar orçamento", "", "", "Técnico"),
      T("Diagnosticado — aguardando aprovação", "Orçamento aprovado", "Cliente aprovou orçamento", "", "Reserva o estoque dos itens", "Vendedor"),
      T("Diagnosticado — aguardando aprovação", "Orçamento rejeitado", "Cliente rejeitou orçamento", "", "", "Vendedor"),
      T("Orçamento aprovado", "Aguardando peças", "Pedir peças ao fornecedor", "", "", "Estoque"),
      T("Aguardando peças", "Peças chegaram", "Confirmar chegada das peças", "", "", "Estoque"),
      T("Orçamento aprovado", "Em execução", "Iniciar execução do reparo", "", "", "Técnico"),
      T("Peças chegaram", "Em execução", "Iniciar execução do reparo", "", "", "Técnico"),
      T("Em execução", "Pausado", "Pausar execução", "", "", "Técnico"),
      T("Pausado", "Em execução", "Retomar execução", "", "", "Técnico"),
      T("Em execução", "Concluído — aguardando retirada", "Concluir execução do reparo", "", "Baixa o estoque reservado", "Técnico"),
      T("Concluído — aguardando retirada", "Entregue ao cliente", "Entregar ao cliente", "", "", "Recepção · Logística"),
      T("Orçamento rejeitado", "Entregue ao cliente", "Entregar ao cliente (sem conserto)", "", "", "Recepção · Logística"),
      T("Recebido pra diagnóstico", "Cancelado", "Cancelar OS", "", "Libera a reserva de estoque", "Gerente"),
      T("Em diagnóstico", "Cancelado", "Cancelar OS", "", "Libera a reserva de estoque", "Gerente"),
      T("Diagnosticado — aguardando aprovação", "Cancelado", "Cancelar OS", "", "Libera a reserva de estoque", "Gerente"),
      T("Orçamento aprovado", "Cancelado", "Cancelar OS", "", "Libera a reserva de estoque", "Gerente"),
      T("Aguardando peças", "Cancelado", "Cancelar OS", "", "Libera a reserva de estoque", "Gerente"),
      T("Peças chegaram", "Cancelado", "Cancelar OS", "", "Libera a reserva de estoque", "Gerente"),
      T("Em execução", "Cancelado", "Cancelar OS", "", "Libera a reserva de estoque", "Gerente"),
      T("Pausado", "Cancelado", "Cancelar OS", "", "Libera a reserva de estoque", "Gerente"),
      T("Concluído — aguardando retirada", "Cancelado", "Cancelar OS", "", "Libera a reserva de estoque", "Gerente"),
      T("Entregue ao cliente", "Garantia acionada", "Registrar acionamento de garantia", "", "", "Gerente"),
    ], liga: ["venda", "compra", "cobranca"] },

  { id: "oficina-os", area: "Produção", n: "Oficina — OS de mecânica", doc: "Ordem de serviço de veículo (Oficina)", prot: false, erp: "oficina_mecanica_os",
    porque: "Caminhão entra para manutenção ou troca de peça: diagnóstico, aprovação, peças, execução e retirada.",
    estados: [S("recepcao", "Recepção", "ini"), S("em_diagnostico", "Diagnóstico"), S("aguardando_aprovacao", "Aguardando aprovação"), S("aguardando_pecas", "Aguardando peças"), S("em_execucao", "Em execução"), S("pronto_retirada", "Pronto p/ retirar"), S("entregue", "Entregue", "ok"), S("cancelado", "Cancelado", "x"), S("garantia_acionada", "Garantia acionada", "x")],
    trans: [
      T("Recepção", "Diagnóstico", "Iniciar diagnóstico", "", "", "Mecânico · Gerente"),
      T("Diagnóstico", "Aguardando aprovação", "Enviar orçamento pra aprovação", "", "", "Mecânico · Gerente"),
      T("Aguardando aprovação", "Aguardando peças", "Cliente aprovou — pedir peças", "", "", "Gerente · Mecânico"),
      T("Aguardando aprovação", "Em execução", "Cliente aprovou — já executar", "", "", "Gerente · Mecânico"),
      T("Aguardando aprovação", "Cancelado", "Cliente recusou orçamento", "", "", "Gerente"),
      T("Aguardando peças", "Em execução", "Peças chegaram — iniciar execução", "", "", "Mecânico · Gerente"),
      T("Em execução", "Pronto p/ retirar", "Concluir serviço", "", "", "Mecânico · Gerente"),
      T("Pronto p/ retirar", "Entregue", "Entregar ao cliente", "", "", "Mecânico · Gerente"),
      T("Pronto p/ retirar", "Garantia acionada", "Acionar garantia", "", "", "Gerente"),
      T("Recepção", "Cancelado", "Cancelar OS", "", "", "Gerente"),
      T("Diagnóstico", "Cancelado", "Cancelar OS", "", "", "Gerente"),
      T("Aguardando peças", "Cancelado", "Cancelar OS", "", "", "Gerente"),
      T("Em execução", "Cancelado", "Cancelar OS", "", "", "Gerente"),
      T("Pronto p/ retirar", "Cancelado", "Cancelar OS", "", "", "Gerente"),
    ], liga: ["compra", "cobranca"] },

  { id: "os-cv", area: "Produção", n: "OS Comunicação Visual", doc: "Ordem de serviço gráfica", prot: false, erp: "os_comunicacao_visual",
    porque: "Orçamento, arte aprovada pelo cliente, impressão, acabamento e instalação ou retirada no balcão.",
    estados: [S("quote_draft", "Orçamento — Rascunho", "ini"), S("quote_sent", "Orçamento — Enviado"), S("quote_approved", "Aprovado pelo cliente"), S("arte_em_aprovacao", "Arte em aprovação"), S("arte_aprovada", "Arte aprovada"), S("aguardando_maquina", "Aguardando máquina (PCP)"), S("em_impressao", "Em impressão"), S("impressao_concluida", "Impressão concluída"), S("aguardando_acabamento", "Aguardando acabamento"), S("acabamento_concluido", "Acabamento concluído"), S("aguardando_instalacao", "Aguardando instalação"), S("em_instalacao", "Em instalação"), S("instalado_aguardando_aprovacao_final", "Instalado — aguardando aprovação final"), S("entregue_completo", "Entregue ao cliente", "ok"), S("cancelado", "Cancelado", "x"), S("garantia_acionada", "Garantia acionada", "x")],
    trans: [
      T("Orçamento — Rascunho", "Orçamento — Enviado", "Enviar orçamento ao cliente", "", "", "Atendimento"),
      T("Orçamento — Enviado", "Aprovado pelo cliente", "Cliente aprovou orçamento", "", "Reserva o estoque dos itens", "Atendimento · Gerente"),
      T("Orçamento — Enviado", "Cancelado", "Cliente rejeitou orçamento", "", "", "Atendimento"),
      T("Aprovado pelo cliente", "Arte em aprovação", "Enviar arte pra aprovação cliente", "", "", "Designer · Gerente"),
      T("Arte em aprovação", "Arte aprovada", "Arte aprovada pelo cliente", "", "", "Sistema · Gerente"),
      T("Arte em aprovação", "Arte em aprovação", "Rejeitar arte (volta pra designer)", "", "", "Sistema · Gerente"),
      T("Arte aprovada", "Em impressão", "Iniciar impressão", "", "", "Operador · Gerente"),
      T("Arte aprovada", "Aguardando máquina (PCP)", "Aguardar liberação de máquina (PCP industrial)", "", "", "Operador · Gerente"),
      T("Aguardando máquina (PCP)", "Em impressão", "Iniciar impressão (máquina liberada)", "", "", "Operador · Gerente"),
      T("Em impressão", "Impressão concluída", "Concluir impressão", "", "Baixa o estoque reservado", "Operador · Gerente"),
      T("Em impressão", "Em impressão", "Refazer impressão (problema detectado)", "", "", "Operador · Gerente"),
      T("Impressão concluída", "Aguardando acabamento", "Iniciar acabamento", "", "", "Operador"),
      T("Aguardando acabamento", "Acabamento concluído", "Concluir acabamento", "", "", "Operador · Gerente"),
      T("Acabamento concluído", "Entregue ao cliente", "Cliente busca no balcão", "", "", "Atendimento"),
      T("Acabamento concluído", "Aguardando instalação", "Agendar instalação", "", "", "Atendimento · Gerente"),
      T("Aguardando instalação", "Em instalação", "Iniciar instalação (chegou no cliente)", "", "", "Instalador"),
      T("Aguardando instalação", "Aguardando instalação", "Reagendar instalação", "", "", "Atendimento · Gerente"),
      T("Em instalação", "Instalado — aguardando aprovação final", "Concluir instalação", "", "", "Instalador · Gerente"),
      T("Instalado — aguardando aprovação final", "Entregue ao cliente", "Cliente aprovou instalação final", "", "", "Sistema · Atendimento · Gerente"),
      T("Entregue ao cliente", null, "Emitir NFe55 (mercadoria) + NFSe56 (instalação) paralelo", "", "", "Financeiro · Fiscal · Gerente"),
      T("Entregue ao cliente", "Garantia acionada", "Acionar garantia (abre OS filha)", "", "", "Gerente"),
      T("Orçamento — Rascunho", "Cancelado", "Cancelar OS", "", "Cancela em cascata: nota, cobrança e aviso ao cliente", "Gerente"),
      T("Orçamento — Enviado", "Cancelado", "Cancelar OS", "", "Cancela em cascata: nota, cobrança e aviso ao cliente", "Gerente"),
      T("Aprovado pelo cliente", "Cancelado", "Cancelar OS", "", "Cancela em cascata: nota, cobrança e aviso ao cliente", "Gerente"),
      T("Arte em aprovação", "Cancelado", "Cancelar OS", "", "Cancela em cascata: nota, cobrança e aviso ao cliente", "Gerente"),
      T("Arte aprovada", "Cancelado", "Cancelar OS", "", "Cancela em cascata: nota, cobrança e aviso ao cliente", "Gerente"),
      T("Aguardando máquina (PCP)", "Cancelado", "Cancelar OS", "", "Cancela em cascata: nota, cobrança e aviso ao cliente", "Gerente"),
      T("Em impressão", "Cancelado", "Cancelar OS", "", "Cancela em cascata: nota, cobrança e aviso ao cliente", "Gerente"),
      T("Impressão concluída", "Cancelado", "Cancelar OS", "", "Cancela em cascata: nota, cobrança e aviso ao cliente", "Gerente"),
      T("Aguardando acabamento", "Cancelado", "Cancelar OS", "", "Cancela em cascata: nota, cobrança e aviso ao cliente", "Gerente"),
      T("Acabamento concluído", "Cancelado", "Cancelar OS", "", "Cancela em cascata: nota, cobrança e aviso ao cliente", "Gerente"),
      T("Aguardando instalação", "Cancelado", "Cancelar OS", "", "Cancela em cascata: nota, cobrança e aviso ao cliente", "Gerente"),
      T("Em instalação", "Cancelado", "Cancelar OS", "", "Cancela em cascata: nota, cobrança e aviso ao cliente", "Gerente"),
      T("Instalado — aguardando aprovação final", "Cancelado", "Cancelar OS", "", "Cancela em cascata: nota, cobrança e aviso ao cliente", "Gerente"),
    ], liga: ["venda", "producao", "entrega", "nfe", "nfse"] },

  { id: "atendimento", area: "Comercial", n: "Atendimento", doc: "Conversa / ticket", prot: true,
    porque: "Conversa que entra pelos canais e precisa de dono e prazo (SLA).",
    estados: [S("nov", "Nova", "ini"), S("and", "Em atendimento"), S("agc", "Aguardando cliente", "esp"), S("res", "Resolvida", "ok"), S("arq", "Arquivada", "x")],
    trans: [
      T("Nova", "Em atendimento", "Assumir", "", "Começa o SLA de resposta", "Atendente"),
      T("Em atendimento", "Aguardando cliente", "Pedir retorno", "", "Pausa o SLA", "Atendente"),
      T("Aguardando cliente", "Em atendimento", "Cliente respondeu", "", "Retoma o SLA", "Sistema"),
      T("Em atendimento", "Resolvida", "Resolver", "Assunto ligado (orçamento, OS ou cobrança)", "Pesquisa rápida de atendimento", "Atendente"),
    ], liga: ["venda"] },

  { id: "contrato", area: "Financeiro", n: "Contrato recorrente", doc: "Locação de painel, manutenção, assinatura", prot: true,
    porque: "Gera cobrança e nota todo mês até acabar ou ser cancelado.",
    estados: [S("ras", "Rascunho", "ini"), S("ati", "Ativo"), S("sus", "Suspenso", "esp"), S("enc", "Encerrado", "ok"), S("can", "Cancelado", "x")],
    trans: [
      T("Rascunho", "Ativo", "Ativar", "Cliente assinou", "Agenda as cobranças do período", "Comercial"),
      T("Ativo", null, "Dia do ciclo", "", "Emite a cobrança e a nota do mês", "Sistema"),
      T("Ativo", "Suspenso", "Suspender", "2 parcelas vencidas", "Para de gerar cobranças · avisa o cliente", "Sistema"),
      T("Suspenso", "Ativo", "Reativar", "Parcelas em atraso pagas ou renegociadas", "Volta a agendar as cobranças", "Financeiro"),
      T("Ativo", "Encerrado", "Encerrar", "Fim do prazo do contrato", "Emite a última cobrança proporcional", "Sistema"),
      T("Ativo", "Cancelado", "Cancelar", "Aviso prévio do contrato cumprido", "Cancela as cobranças futuras", "Comercial"),
    ], liga: ["cobranca", "nfse"] },

  { id: "ponto", area: "Pessoas", n: "Intercorrência de ponto", doc: "Pedido de ajuste de marcação", prot: true,
    porque: "Marcação esquecida ou errada vira pedido com aprovação e trilha (Portaria MTP 671/2021).",
    estados: [S("ras", "Rascunho", "ini"), S("pen", "Pendente", "esp"), S("apr", "Aprovada"), S("apl", "Aplicada", "ok"), S("rej", "Rejeitada", "x"), S("can", "Cancelada", "x")],
    trans: [
      T("Rascunho", "Pendente", "Submeter", "Justificativa preenchida", "Notifica o gestor · entra na fila de aprovações", "Colaborador"),
      T("Pendente", "Aprovada", "Aprovar", "Gestor do colaborador", "Fica pronta para aplicar na apuração", "Gestor"),
      T("Pendente", "Rejeitada", "Rejeitar", "Motivo obrigatório", "Avisa o colaborador", "Gestor"),
      T("Aprovada", "Aplicada", "Aplicar", "Período de apuração aberto", "Ajusta o espelho; a marcação original fica preservada", "RH"),
      T("Rascunho", "Cancelada", "Descartar", "", "", "Colaborador"),
    ], liga: [] },
];

// Cobertura no ERP por transição, na ordem de `trans` — EXPORTADA do CatalogoProcessos do main
// (#9496 + #9505 + #9507) em 16/10/2026, não digitada; "producao" reexportado em 28/10/2026 (Aguardando material). estado: sim · parcial · nao · nao_verificado; onde = classe/rota ou motivo.
// Regenerar do catálogo quando ele mudar (não editar à mão).
const COB = {
  "venda": [
    ["parcial", "Ação FSM enviar_orcamento (SaleFsmActionController@execute, POST /sells/{id}/fsm-action) só muda a etapa. O envio de verdade é NotificationController@send (template new_quotation), fora da FSM. Sem conferência de contato e sem medição de abertura."],
    ["parcial", "Ação FSM cliente_aprovou com ReservarEstoque muda a etapa; o link público (SellPosController@showInvoice, GET /quote/{token}) só mostra. Não cria OS por item nem parcelas de sinal e saldo; sem checagem de validade; a reserva espera payload.items, que a tela não manda."],
    ["parcial", "Ação FSM cliente_rejeitou leva a Cancelada: não existe a etapa Perdido, o motivo não é pedido e nada alimenta funil (DealPipelineService::moverStage do Crm, que leva a perdido com motivo, não tem chamador)."],
    ["parcial", "Ação FSM faturar sai de \"Pronto pra faturar\" e só muda a etapa. NF-e (NfeEmissaoController@emitir) e NFS-e (NfseController@emitirParaTransaction) são botões separados, sem decidir pela natureza fiscal do item."],
    ["parcial", "Ações FSM entregar e concluir só mudam a etapa (entregar exige Paga antes; no app, PedidoEntregaController@registrar). Sem checagem de OS pronta e sem pesquisa de satisfação depois da entrega."],
    ["parcial", "Ação FSM cancelar_venda com CancelarVendaCascade::execute libera a reserva. Não cobra \"nenhuma nota autorizada\": tenta cancelar as notas, mas lê transaction_documents, que nenhum código grava. Não estorna sinal já pago (só cancela boleto pendente)."],
  ],
  "producao": [
    ["parcial", "SituacaoDaOrdem::liberar executa Liberar na OS por item (ADR 0436); as etapas nascem do roteiro do produto quando o cliente aprova (CriarOsDaVenda). Sem tela ainda; não reserva material nem calcula prazo pela fila."],
    ["parcial", "SituacaoDaOrdem::liberar, com a chave producao_aguarda_material da empresa ligada e faltando insumo da receita, leva a OS de Na fila para Aguardando material e guarda no histórico o que faltava (D3, US-PCP-032). A chave ainda não tem campo na aba Fluxos."],
    ["parcial", "SituacaoDaOrdem::conferirMaterial (papel producao.liberar) devolve a OS para Na fila quando não falta mais nada e, faltando, devolve a lista. Não confere sozinho quando a compra é recebida, e a tela da OS ainda não mostra a falta."],
    ["parcial", "ApontamentoOperadorService::iniciar abre o apontamento por QR ou tela e, na OS por item, leva a OS de Na fila a Em execução (SituacaoDaOrdem). A trava é o responsável da etapa, não o setor; etapa sem posto não aponta."],
    ["parcial", "ApontamentoOperadorService::pausar exige motivo do cadastro do posto e, sem outra etapa da OS rodando, leva a OS por item a Pausada. Não há alerta de prazo."],
    ["sim", "Iniciar de novo com a OS por item Pausada executa Retomar (SituacaoDaOrdem::aoIniciar) e o relógio volta."],
    ["parcial", "ApontamentoOperadorService::concluir conclui a etapa e registra Concluir etapa na OS por item; etapa de um bloco só começa com o bloco anterior (as duas trilhas) feito. O aviso ao próximo segue a ordem prevista, não a junta."],
    ["parcial", "SituacaoDaOrdem::reprovar (motivo de refugo do posto + etapa de origem já feita) leva a OS a Retrabalho e reabre a etapa de origem como etapa nova; o custo entra pelas horas dela. Sem tela ainda."],
    ["parcial", "SituacaoDaOrdem::refazer volta a OS por item a Em execução. Sem tela ainda."],
    ["parcial", "A última etapa concluída executa Concluir item (SituacaoDaOrdem::concluirItemSeTerminou): OS pronta e custo real congelado (CustoRealService::daOs). Não baixa o material real (decisão pendente)."],
    ["parcial", "SituacaoDaOrdem::cancelarItem, antes de liberar, cancela as etapas programadas. Não lança consumo como perda nem libera reserva. Sem tela ainda."],
    ["parcial", "SituacaoDaOrdem::cancelarItem também cancela a OS em Aguardando material e as etapas programadas. Não lança consumo como perda nem libera reserva. Sem tela ainda."],
  ],
  "nfe": [
    ["parcial", "Não há estado Rascunho nem Validada: as regras (valor, NCM, item de serviço) rodam dentro de NfeService::emitirInterno (POST /nfe-brasil/transactions/{tx}/emitir). Sem validação XSD explícita; o certificado só é checado por ativo, não por validade."],
    ["parcial", "NfeService::emitirInterno reserva o número da série e transmite. Se a SEFAZ cai a nota vira erro_envio: contingência só manual (ContingenciaController@ativar, tpEmis 4), sem SVC nem EPEC, e RetentarContingenciaJob não tem quem o dispare."],
    ["parcial", "NfeService::processarRetorno grava autorizada, XML e DANFE. O e-mail sozinho só sai no boleto pago; na venda, só por NfeEmissaoController@reenviarEmail. Não baixa estoque nem liga ao título."],
    ["sim", "NfeService::processarRetorno grava rejeitada com cStat e motivo; a tela de nota mostra o resultado. O campo com erro não é extraído (a orientação vem de um mapa na tela)."],
    ["parcial", "AcoesController@retransmitir → NfeService::retransmitir (POST /fiscal/acoes/nfe/{emissao}/retransmitir) não volta a nota para correção: marca a antiga inutilizada no banco local e emite outra, com outro número."],
    ["parcial", "NfeService::processarRetorno grava denegada e só escreve log: não avisa o fiscal, e NfeService::retransmitir aceita denegada (deixa reemitir em vez de tratar o número como queimado)."],
    ["parcial", "AcoesController@cartaCorrecao → NfeCartaCorrecaoService::aplicar (POST /fiscal/acoes/nfe/{emissao}/cce) envia o evento 110110 e o liga à nota. A regra \"não corrige valor, imposto, destinatário nem data\" não é conferida (texto livre)."],
    ["parcial", "AcoesController@cancelarNfe → NfeService::cancelar (POST /fiscal/acoes/nfe/{emissao}/cancelar) envia o evento 110111 e marca cancelada. Não confere o prazo localmente e não estorna estoque nem cancela o título."],
    ["sim", "AcoesController@inutilizar → NfeInutilizacaoService::inutilizar (POST /fiscal/acoes/nfe/inutilizar) comunica a faixa à SEFAZ e marca as emissões da faixa. Não confere localmente que os números foram pulados (quem barra é a SEFAZ)."],
  ],
  "nfse": [
    ["parcial", "NfseController@store (POST /nfse/emitir) confere o código LC 116 e calcula ISS e retenções federais. Não existe município de incidência (usa o do prestador) nem o estado Validada, e o rascunho criado pela venda não tem rota para validar."],
    ["sim", "NfseController@store → EmitirNfseJob → NfseEmissaoService::emitir envia a DPS assinada à Sefin Nacional (SnNfseAdapter). Só o emissor nacional está ligado; não há emissor municipal."],
    ["parcial", "NfseEmissaoService::emitir grava emitida com número e chave. Não envia ao tomador e não ajusta o título pelo ISS retido (o Financeiro não referencia NFS-e)."],
    ["parcial", "A rejeição vira status erro com a mensagem (NfseEmissaoService::marcarErro), mas a nota não volta para correção: não pode ser editada."],
    ["nao", "Não há rota de corrigir ou reemitir a nota rejeitada (Modules/NFSe/Routes/web.php só tem listar, emitir, emitir por venda, ver, cancelar e PDF); \"Nova emissão\" cria outra do zero."],
    ["nao", "Não existe substituição de NFS-e: nenhuma rota, campo ou vínculo com a nota substituta."],
    ["parcial", "NfseController@cancelar → NfseEmissaoService::cancelar (POST /nfse/{nfse}/cancelar) marca cancelada sem olhar a resposta da Sefin (fica cancelada mesmo se ela recusar). Não confere regra nem prazo e não cancela o título."],
  ],
  "cobranca": [
    ["parcial", "UnificadoController@emitirBoletoTitulo (POST /financeiro/unificado/{tituloId}/boleto) registra o boleto no Inter com conta ativa para boleto. Não envia ao cliente (CobrancaEmitida não é disparado), não muda o estado do título e não gera PIX por esse caminho."],
    ["parcial", "Não há estado vencido gravado nem cron: a tela calcula \"atrasado\" ao ler (UnificadoController::deriveStatus), já no D+0. CobrancaVencida é disparado no retorno CNAB, mas nenhum listener o ouve."],
    ["nao", "Não existe régua D+1/D+5/D+15 sobre título nem tarefa ao vendedor. A régua do RecurringBilling é fixa e só de leitura (\"Em breve\"); o lembrete agendado do núcleo (pos:autoSendPaymentReminder) é sobre vendas, não títulos."],
    ["parcial", "OnCobrancaPagaCreateFinanceiroTitulo::baixarTituloExistente quita o título quando o pagamento zera o saldo, mas só para o business dono do SaaS (saas_owner_business_id); webhook de boleto Inter/C6/Asaas só grava o evento. Não libera produção, não emite nota (EmitirNFeAoReceberPagamento só ouve o RecurringBilling, com NFEBRASIL_AUTO_EMISSION desligado) e não lança comissão."],
    ["parcial", "Pelo banco, OnCobrancaPagaCreateFinanceiroTitulo::baixarTituloExistente reduz o valor em aberto e o título segue aberto (mesmas restrições do pagamento total). Na baixa manual, UnificadoController@baixar (POST /financeiro/unificado/{id}/baixar) divide em título quitado + saldo."],
    ["parcial", "ConciliacaoController@match (POST /financeiro/conciliacao/{lineId}/match) casa a linha do extrato com o título e marca a linha. O título não muda de estado, não se confere se está quitado e nada é lançado no caixa."],
    ["nao", "Não existe renegociação: nenhuma ação, alçada ou geração de parcelas novas (só o cancelamento em lote, sem substitutos)."],
    ["nao", "Não existe baixa por perda: nenhum motivo, alçada, lançamento de perda ou bloqueio de crédito ligado a ela (o limite de crédito do núcleo é outra coisa)."],
  ],
  "pagar": [
    ["parcial", "UnificadoController@aprovar (POST /financeiro/unificado/{id}/aprovar) aprova de verdade, mas o único filtro é a permissão financeiro.titulo.aprovar: não existe alçada por valor."],
    ["nao", "Não existe estado Agendado (o título só tem aberto, parcial, quitado e cancelado), nem envio de pagamento ao banco, nem conferência de saldo projetado."],
    ["nao", "O retorno automático do banco só cria título a receber. Para conta a pagar só há a baixa manual (ContaPagarController@pagar), que não cria pagamento na compra: a compra não fica paga."],
    ["parcial", "ConciliacaoController@match marca a linha do extrato como conciliada, ligada ao título; o título não ganha estado Conciliado e a sugestão automática só procura títulos ainda em aberto."],
  ],
  "compra": [
    ["parcial", "PurchaseOrderController@store (POST /purchase-order, com as requisições) cria o pedido e TransactionUtil::updatePurchaseOrderStatus avança a requisição. Não há passo de aprovação nem alçada, e o envio ao fornecedor é notificação manual."],
    ["parcial", "SellController@updateShipping (PUT /sells/update-shipping/{id}, o mesmo modal do pedido) marca o envio como shipped. Não confere a confirmação do fornecedor nem registra previsão de chegada."],
    ["parcial", "Não existe estado Cancelado na requisição: PurchaseRequisitionController@destroy (DELETE /purchase-requisition/{id}) exclui, sem pedir motivo."],
    ["parcial", "Não existe estado Cancelado no pedido: PurchaseOrderController@destroy (DELETE /purchase-order/{id}) exclui, ou o envio é marcado cancelado. Não avisa o fornecedor e não há orçamento de compra para liberar."],
    ["parcial", "PurchaseController@updateStatus (POST /purchases/update-status) com received dá entrada no estoque. Não há custo médio (troca pelo preço da última compra), proposta de preço nem conferência de quantidade."],
    ["nao", "Não existe estado Conferido nem checagem de divergência; a conta a pagar nasce ao criar a compra. PurchaseXmlController não tem rota, e a manifestação (ManifestacaoController@confirmar) não tem ligação com a compra."],
    ["parcial", "TransactionPaymentController@store (POST /payments) → TituloAutoService::registrarPagamento dá baixa no título e a compra fica paga, sem exigir título aprovado. Com a compra toda paga, o título termina cancelado em vez de quitado (BUG-4 registrado em teste)."],
  ],
  "requisicao": [
    ["nao", "Não existe requisição de material da OS (a única requisição é a de compra). A reserva do motor é por venda, variação e local, sem lote nem fração de rolo."],
    ["nao", "Nada abre requisição de compra quando falta saldo (a de compra é manual) e nada pausa a etapa da OS."],
    ["nao", "Não há entrega de material do almoxarifado para a OS; a única baixa ligada a documento é a do motor, sobre reservas da venda."],
  ],
  "inventario": [
    ["nao", "Não existe contagem de inventário: nenhuma tabela ou rota de contagem, nenhum congelamento de saldo."],
    ["nao", "Não existe cálculo de divergência por item (não há contagem)."],
    ["parcial", "StockAdjustmentController@store (POST /stock-adjustments) lança ajuste manual que só tira do estoque, com valor unitário e recuperado. Não vem de contagem, não tem alçada do gerente e não lança sobra."],
  ],
  "entrega": [
    ["parcial", "Ação FSM agendar_instalacao (SaleFsmActionController@execute) só muda a etapa. Não há agenda de equipe e veículo nem aviso ao cliente."],
    ["parcial", "Ação FSM iniciar_instalacao só muda a etapa: não confere material e equipe nem avisa o cliente."],
    ["nao", "Não existe a etapa Reagendado nem a ação de confirmar nova data; o status de reagendamento de cv_instalacoes não é gravado por nenhum código."],
    ["parcial", "Ação FSM reagendar_instalacao volta para a mesma etapa: não pede motivo, não tem etapa Reagendado e não avisa o cliente."],
    ["parcial", "Ação FSM concluir_instalacao muda a etapa. Foto e assinatura são só colunas de cv_instalacoes, sem quem grave nelas: não são exigidas e não há relatório na OS."],
    ["parcial", "Ação FSM aprovacao_final_cliente muda para entregue. O protocolo só sai em PedidoEntregaController@registrar (venda, no app), e não há pesquisa em 2 dias."],
  ],
  "devolucao": [
    ["nao", "Não existe ocorrência pós-venda no ERP: a devolução começa direto no lançamento."],
    ["nao", "Nenhum código cria OS de retrabalho; as ações de garantia das OS semeadas só mudam a etapa."],
    ["nao", "Sem ocorrência no ERP. A NF-e de devolução tem serviço (Modules/NfeBrasil/Services/DevolucaoService::emitirDevolucao) sem rota nem chamador fora dos testes; DevolucaoController (legado) não tem rota."],
    ["nao", "Sem ocorrência no ERP (ver Analisar)."],
    ["nao", "Sem ocorrência no ERP (ver Analisar)."],
    ["nao", "Depende da OS de retrabalho, que não existe."],
    ["sim", "resource sell-return (routes/web.php) → SellReturnController@store → TransactionUtil::addSellReturn (quantidade por item; volta o estoque por ProductUtil::updateProductQuantity)"],
    ["parcial", "A devolução de venda vira transação sell_return com situação de pagamento (TransactionUtil::updatePaymentStatus); o Modules/Financeiro não lê sell_return, então o título da venda não é estornado."],
    ["parcial", "Modules/Vestuario/Services/DevolucaoService::registrarDevolucao (tipo credito_ficha → vestuario_creditos_cliente) existe, sem rota nem chamador fora dos testes. A validade de 6 meses vem do ERP: constante VALIDADE_CREDITO_MESES = 6 do mesmo serviço (CDC Art. 50), gravada em expira_em e zerada por expirarCreditosVencidos; é fixa no código, não parâmetro da empresa."],
  ],
  "devolucao-compra": [
    ["sim", "resource purchase-return → PurchaseReturnController@store (ProductUtil::decreaseProductQuantity) e /purchase-return/save → CombinedPurchaseReturnController@save"],
    ["nao_verificado", "A devolução de compra tem situação de pagamento (TransactionUtil::updatePaymentStatus); não conferi se o Financeiro a liga ao título a pagar."],
    ["sim", "PurchaseReturnController@destroy (resource purchase-return)"],
  ],
  "os-reparo": [
    ["sim", "Ação \"Iniciar diagnóstico\" do processo os_reparo_padrao — POST /repair/job-sheets/{id}/fsm-action (RepairFsmActionController@execute)"],
    ["sim", "Ação \"Finalizar diagnóstico e gerar orçamento\" do processo os_reparo_padrao — POST /repair/job-sheets/{id}/fsm-action (RepairFsmActionController@execute)"],
    ["sim", "Ação \"Cliente aprovou orçamento\" do processo os_reparo_padrao — POST /repair/job-sheets/{id}/fsm-action (RepairFsmActionController@execute)"],
    ["sim", "Ação \"Cliente rejeitou orçamento\" do processo os_reparo_padrao — POST /repair/job-sheets/{id}/fsm-action (RepairFsmActionController@execute)"],
    ["sim", "Ação \"Pedir peças ao fornecedor\" do processo os_reparo_padrao — POST /repair/job-sheets/{id}/fsm-action (RepairFsmActionController@execute)"],
    ["sim", "Ação \"Confirmar chegada das peças\" do processo os_reparo_padrao — POST /repair/job-sheets/{id}/fsm-action (RepairFsmActionController@execute)"],
    ["sim", "Ação \"Iniciar execução do reparo\" do processo os_reparo_padrao — POST /repair/job-sheets/{id}/fsm-action (RepairFsmActionController@execute)"],
    ["sim", "Ação \"Iniciar execução do reparo\" do processo os_reparo_padrao — POST /repair/job-sheets/{id}/fsm-action (RepairFsmActionController@execute)"],
    ["sim", "Ação \"Pausar execução\" do processo os_reparo_padrao — POST /repair/job-sheets/{id}/fsm-action (RepairFsmActionController@execute)"],
    ["sim", "Ação \"Retomar execução\" do processo os_reparo_padrao — POST /repair/job-sheets/{id}/fsm-action (RepairFsmActionController@execute)"],
    ["sim", "Ação \"Concluir execução do reparo\" do processo os_reparo_padrao — POST /repair/job-sheets/{id}/fsm-action (RepairFsmActionController@execute)"],
    ["sim", "Ação \"Entregar ao cliente\" do processo os_reparo_padrao — POST /repair/job-sheets/{id}/fsm-action (RepairFsmActionController@execute)"],
    ["nao", "Ação \"Entregar ao cliente (sem conserto)\" cadastrada no processo os_reparo_padrao, mas a etapa de origem é terminal: o ExecuteStageActionService recusa."],
    ["sim", "Ação \"Cancelar OS\" do processo os_reparo_padrao — POST /repair/job-sheets/{id}/fsm-action (RepairFsmActionController@execute)"],
    ["sim", "Ação \"Cancelar OS\" do processo os_reparo_padrao — POST /repair/job-sheets/{id}/fsm-action (RepairFsmActionController@execute)"],
    ["sim", "Ação \"Cancelar OS\" do processo os_reparo_padrao — POST /repair/job-sheets/{id}/fsm-action (RepairFsmActionController@execute)"],
    ["sim", "Ação \"Cancelar OS\" do processo os_reparo_padrao — POST /repair/job-sheets/{id}/fsm-action (RepairFsmActionController@execute)"],
    ["sim", "Ação \"Cancelar OS\" do processo os_reparo_padrao — POST /repair/job-sheets/{id}/fsm-action (RepairFsmActionController@execute)"],
    ["sim", "Ação \"Cancelar OS\" do processo os_reparo_padrao — POST /repair/job-sheets/{id}/fsm-action (RepairFsmActionController@execute)"],
    ["sim", "Ação \"Cancelar OS\" do processo os_reparo_padrao — POST /repair/job-sheets/{id}/fsm-action (RepairFsmActionController@execute)"],
    ["sim", "Ação \"Cancelar OS\" do processo os_reparo_padrao — POST /repair/job-sheets/{id}/fsm-action (RepairFsmActionController@execute)"],
    ["sim", "Ação \"Cancelar OS\" do processo os_reparo_padrao — POST /repair/job-sheets/{id}/fsm-action (RepairFsmActionController@execute)"],
    ["nao", "Ação \"Registrar acionamento de garantia\" cadastrada no processo os_reparo_padrao, mas a etapa de origem é terminal: o ExecuteStageActionService recusa."],
  ],
  "oficina-os": [
    ["sim", "Ação \"Iniciar diagnóstico\" do processo oficina_mecanica_os — ServiceOrderFsmActionController@execute (Modules/OficinaAuto/Routes/web.php)"],
    ["sim", "Ação \"Enviar orçamento pra aprovação\" do processo oficina_mecanica_os — ServiceOrderFsmActionController@execute (Modules/OficinaAuto/Routes/web.php)"],
    ["sim", "Ação \"Cliente aprovou — pedir peças\" do processo oficina_mecanica_os — ServiceOrderFsmActionController@execute (Modules/OficinaAuto/Routes/web.php)"],
    ["sim", "Ação \"Cliente aprovou — já executar\" do processo oficina_mecanica_os — ServiceOrderFsmActionController@execute (Modules/OficinaAuto/Routes/web.php)"],
    ["sim", "Ação \"Cliente recusou orçamento\" do processo oficina_mecanica_os — ServiceOrderFsmActionController@execute (Modules/OficinaAuto/Routes/web.php)"],
    ["sim", "Ação \"Peças chegaram — iniciar execução\" do processo oficina_mecanica_os — ServiceOrderFsmActionController@execute (Modules/OficinaAuto/Routes/web.php)"],
    ["sim", "Ação \"Concluir serviço\" do processo oficina_mecanica_os — ServiceOrderFsmActionController@execute (Modules/OficinaAuto/Routes/web.php)"],
    ["sim", "Ação \"Entregar ao cliente\" do processo oficina_mecanica_os — ServiceOrderFsmActionController@execute (Modules/OficinaAuto/Routes/web.php)"],
    ["sim", "Ação \"Acionar garantia\" do processo oficina_mecanica_os — ServiceOrderFsmActionController@execute (Modules/OficinaAuto/Routes/web.php)"],
    ["sim", "Ação \"Cancelar OS\" do processo oficina_mecanica_os — ServiceOrderFsmActionController@execute (Modules/OficinaAuto/Routes/web.php)"],
    ["sim", "Ação \"Cancelar OS\" do processo oficina_mecanica_os — ServiceOrderFsmActionController@execute (Modules/OficinaAuto/Routes/web.php)"],
    ["sim", "Ação \"Cancelar OS\" do processo oficina_mecanica_os — ServiceOrderFsmActionController@execute (Modules/OficinaAuto/Routes/web.php)"],
    ["sim", "Ação \"Cancelar OS\" do processo oficina_mecanica_os — ServiceOrderFsmActionController@execute (Modules/OficinaAuto/Routes/web.php)"],
    ["sim", "Ação \"Cancelar OS\" do processo oficina_mecanica_os — ServiceOrderFsmActionController@execute (Modules/OficinaAuto/Routes/web.php)"],
  ],
  "os-cv": [
    ["parcial", "Ação \"Enviar orçamento ao cliente\" do processo os_comunicacao_visual — POST /sells/{id}/fsm-action (SaleFsmActionController@execute) — sem tela que inicie a venda neste processo; OrdemProducao sem rota de ação"],
    ["parcial", "Ação \"Cliente aprovou orçamento\" do processo os_comunicacao_visual — POST /sells/{id}/fsm-action (SaleFsmActionController@execute) — sem tela que inicie a venda neste processo; OrdemProducao sem rota de ação"],
    ["parcial", "Ação \"Cliente rejeitou orçamento\" do processo os_comunicacao_visual — POST /sells/{id}/fsm-action (SaleFsmActionController@execute) — sem tela que inicie a venda neste processo; OrdemProducao sem rota de ação"],
    ["parcial", "Ação \"Enviar arte pra aprovação cliente\" do processo os_comunicacao_visual — POST /sells/{id}/fsm-action (SaleFsmActionController@execute) — sem tela que inicie a venda neste processo; OrdemProducao sem rota de ação"],
    ["parcial", "Ação \"Arte aprovada pelo cliente\" do processo os_comunicacao_visual — POST /sells/{id}/fsm-action (SaleFsmActionController@execute) — sem tela que inicie a venda neste processo; OrdemProducao sem rota de ação"],
    ["parcial", "Ação \"Rejeitar arte (volta pra designer)\" do processo os_comunicacao_visual — POST /sells/{id}/fsm-action (SaleFsmActionController@execute) — sem tela que inicie a venda neste processo; OrdemProducao sem rota de ação"],
    ["parcial", "Ação \"Iniciar impressão\" do processo os_comunicacao_visual — POST /sells/{id}/fsm-action (SaleFsmActionController@execute) — sem tela que inicie a venda neste processo; OrdemProducao sem rota de ação"],
    ["parcial", "Ação \"Aguardar liberação de máquina (PCP industrial)\" do processo os_comunicacao_visual — POST /sells/{id}/fsm-action (SaleFsmActionController@execute) — sem tela que inicie a venda neste processo; OrdemProducao sem rota de ação"],
    ["parcial", "Ação \"Iniciar impressão (máquina liberada)\" do processo os_comunicacao_visual — POST /sells/{id}/fsm-action (SaleFsmActionController@execute) — sem tela que inicie a venda neste processo; OrdemProducao sem rota de ação"],
    ["parcial", "Ação \"Concluir impressão\" do processo os_comunicacao_visual — POST /sells/{id}/fsm-action (SaleFsmActionController@execute) — sem tela que inicie a venda neste processo; OrdemProducao sem rota de ação"],
    ["parcial", "Ação \"Refazer impressão (problema detectado)\" do processo os_comunicacao_visual — POST /sells/{id}/fsm-action (SaleFsmActionController@execute) — sem tela que inicie a venda neste processo; OrdemProducao sem rota de ação"],
    ["parcial", "Ação \"Iniciar acabamento\" do processo os_comunicacao_visual — POST /sells/{id}/fsm-action (SaleFsmActionController@execute) — sem tela que inicie a venda neste processo; OrdemProducao sem rota de ação"],
    ["parcial", "Ação \"Concluir acabamento\" do processo os_comunicacao_visual — POST /sells/{id}/fsm-action (SaleFsmActionController@execute) — sem tela que inicie a venda neste processo; OrdemProducao sem rota de ação"],
    ["parcial", "Ação \"Cliente busca no balcão\" do processo os_comunicacao_visual — POST /sells/{id}/fsm-action (SaleFsmActionController@execute) — sem tela que inicie a venda neste processo; OrdemProducao sem rota de ação"],
    ["parcial", "Ação \"Agendar instalação\" do processo os_comunicacao_visual — POST /sells/{id}/fsm-action (SaleFsmActionController@execute) — sem tela que inicie a venda neste processo; OrdemProducao sem rota de ação"],
    ["parcial", "Ação \"Iniciar instalação (chegou no cliente)\" do processo os_comunicacao_visual — POST /sells/{id}/fsm-action (SaleFsmActionController@execute) — sem tela que inicie a venda neste processo; OrdemProducao sem rota de ação"],
    ["parcial", "Ação \"Reagendar instalação\" do processo os_comunicacao_visual — POST /sells/{id}/fsm-action (SaleFsmActionController@execute) — sem tela que inicie a venda neste processo; OrdemProducao sem rota de ação"],
    ["parcial", "Ação \"Concluir instalação\" do processo os_comunicacao_visual — POST /sells/{id}/fsm-action (SaleFsmActionController@execute) — sem tela que inicie a venda neste processo; OrdemProducao sem rota de ação"],
    ["parcial", "Ação \"Cliente aprovou instalação final\" do processo os_comunicacao_visual — POST /sells/{id}/fsm-action (SaleFsmActionController@execute) — sem tela que inicie a venda neste processo; OrdemProducao sem rota de ação"],
    ["nao", "Ação \"Emitir NFe55 (mercadoria) + NFSe56 (instalação) paralelo\" cadastrada no processo os_comunicacao_visual, mas a etapa de origem é terminal: o ExecuteStageActionService recusa."],
    ["nao", "Ação \"Acionar garantia (abre OS filha)\" cadastrada no processo os_comunicacao_visual, mas a etapa de origem é terminal: o ExecuteStageActionService recusa."],
    ["parcial", "Ação \"Cancelar OS\" do processo os_comunicacao_visual — POST /sells/{id}/fsm-action (SaleFsmActionController@execute) — sem tela que inicie a venda neste processo; OrdemProducao sem rota de ação"],
    ["parcial", "Ação \"Cancelar OS\" do processo os_comunicacao_visual — POST /sells/{id}/fsm-action (SaleFsmActionController@execute) — sem tela que inicie a venda neste processo; OrdemProducao sem rota de ação"],
    ["parcial", "Ação \"Cancelar OS\" do processo os_comunicacao_visual — POST /sells/{id}/fsm-action (SaleFsmActionController@execute) — sem tela que inicie a venda neste processo; OrdemProducao sem rota de ação"],
    ["parcial", "Ação \"Cancelar OS\" do processo os_comunicacao_visual — POST /sells/{id}/fsm-action (SaleFsmActionController@execute) — sem tela que inicie a venda neste processo; OrdemProducao sem rota de ação"],
    ["parcial", "Ação \"Cancelar OS\" do processo os_comunicacao_visual — POST /sells/{id}/fsm-action (SaleFsmActionController@execute) — sem tela que inicie a venda neste processo; OrdemProducao sem rota de ação"],
    ["parcial", "Ação \"Cancelar OS\" do processo os_comunicacao_visual — POST /sells/{id}/fsm-action (SaleFsmActionController@execute) — sem tela que inicie a venda neste processo; OrdemProducao sem rota de ação"],
    ["parcial", "Ação \"Cancelar OS\" do processo os_comunicacao_visual — POST /sells/{id}/fsm-action (SaleFsmActionController@execute) — sem tela que inicie a venda neste processo; OrdemProducao sem rota de ação"],
    ["parcial", "Ação \"Cancelar OS\" do processo os_comunicacao_visual — POST /sells/{id}/fsm-action (SaleFsmActionController@execute) — sem tela que inicie a venda neste processo; OrdemProducao sem rota de ação"],
    ["parcial", "Ação \"Cancelar OS\" do processo os_comunicacao_visual — POST /sells/{id}/fsm-action (SaleFsmActionController@execute) — sem tela que inicie a venda neste processo; OrdemProducao sem rota de ação"],
    ["parcial", "Ação \"Cancelar OS\" do processo os_comunicacao_visual — POST /sells/{id}/fsm-action (SaleFsmActionController@execute) — sem tela que inicie a venda neste processo; OrdemProducao sem rota de ação"],
    ["parcial", "Ação \"Cancelar OS\" do processo os_comunicacao_visual — POST /sells/{id}/fsm-action (SaleFsmActionController@execute) — sem tela que inicie a venda neste processo; OrdemProducao sem rota de ação"],
    ["parcial", "Ação \"Cancelar OS\" do processo os_comunicacao_visual — POST /sells/{id}/fsm-action (SaleFsmActionController@execute) — sem tela que inicie a venda neste processo; OrdemProducao sem rota de ação"],
    ["parcial", "Ação \"Cancelar OS\" do processo os_comunicacao_visual — POST /sells/{id}/fsm-action (SaleFsmActionController@execute) — sem tela que inicie a venda neste processo; OrdemProducao sem rota de ação"],
  ],
  "atendimento": [
    ["parcial", "InboxController@assign (PATCH /atendimento/inbox/{id}/assign) só preenche o responsável; o status não muda e o SLA conta a partir das mensagens, não de assumir."],
    ["nao", "Não existe status \"aguardando cliente\" nem pausa de SLA (awaiting_human é o robô passando para humano)."],
    ["nao", "A mensagem que chega nunca muda o status de uma conversa que já existe; sem status de espera não há retomada de SLA."],
    ["parcial", "InboxController@updateStatus com resolved → CsatDispatcher::dispatchOnResolve manda a pesquisa (só canal Baileys, flag ligada por padrão). Não exige assunto ligado: a conversa não tem vínculo com orçamento, OS ou cobrança."],
  ],
  "contrato": [
    ["parcial", "RecurringBillingController@store (POST /recurring-billing) já cria a assinatura ativa: não há Rascunho, ação Ativar nem conferência de assinatura do cliente. As cobranças saem do job diário."],
    ["parcial", "InvoiceGeneratorService::run (rb:generate-invoices, diário, só em live) emite a fatura e avança o vencimento. A nota do mês não sai no ciclo: só quando a fatura é paga, e com a flag de emissão automática ligada."],
    ["nao", "past_due existe no enum, mas nenhum código grava esse status: não há contagem de parcelas vencidas nem aviso. A única suspensão é a pausa manual (RecurringBillingController@pausar)."],
    ["parcial", "RecurringBillingController@reativar (POST /recurring-billing/{id}/reativar) volta para ativo e o job retoma. Não confere se as parcelas em atraso foram pagas, e Suspenso nunca é atingido."],
    ["nao", "Não existe estado Encerrado, data de fim do contrato nem cobrança proporcional."],
    ["sim", "RecurringBillingController@cancelar (POST /recurring-billing/{id}/cancelar) cancela e o job para de gerar faturas. Não confere aviso prévio, e faturas já abertas se cancelam em outra rota."],
  ],
  "ponto": [
    ["parcial", "IntercorrenciaController@submeter (POST /ponto/intercorrencias/{id}/submeter) passa a pendente e entra na fila de aprovações. Não avisa o gestor, e a justificativa é exigida no cadastro, não na submissão."],
    ["sim", "AprovacaoController@aprovar (POST /ponto/aprovacoes/{id}/aprovar) aprova, grava quem e quando e reapura o dia. Não confere se quem aprova é o gestor do colaborador (só o acesso ao módulo)."],
    ["parcial", "AprovacaoController@rejeitar exige motivo e grava rejeitada, mas não avisa o colaborador e não exige que esteja pendente."],
    ["parcial", "ApuracaoService::aplicarIntercorrencias ajusta o espelho na aprovação, com a marcação original preservada. Não existe ação Aplicar do RH, nem quem grave APLICADA, nem checagem de período aberto."],
    ["sim", "IntercorrenciaController@cancelar (POST /ponto/intercorrencias/{id}/cancelar) cancela o rascunho (aceita também pendente). Não confere se quem descarta é o próprio colaborador."],
  ],
};
const COB_EST = { sim: { l: "executa", tone: "ok" }, parcial: { l: "parcial", tone: "warn" }, nao: { l: "não existe", tone: "danger" }, nao_verificado: { l: "não verificado", tone: "mute" } };
// Resumo do processo: quantas transições o ERP executa de ponta a ponta, de quantas.
function resumoCob(id) {
  const r = { sim: 0, parcial: 0, nao: 0, nao_verificado: 0, total: 0 };
  (COB[id] || []).forEach(([e]) => { r[e] += 1; r.total += 1; });
  return r;
}
function CobItem({ id }) {
  const r = resumoCob(id);
  if (!r.total) return null;
  if (r.nao_verificado === r.total) return <span className="flx-pill mute flp-cob">ERP não verificado</span>;
  const tone = r.sim === r.total ? "ok" : r.sim + r.parcial > 0 ? "warn" : "danger";
  return <span className={"flx-pill flp-cob " + tone} title={r.sim + " executa · " + r.parcial + " parcial · " + r.nao + " não existe · " + r.nao_verificado + " não verificado"}>{r.sim} de {r.total} no ERP{r.parcial ? " · " + r.parcial + (r.parcial === 1 ? " parcial" : " parciais") : ""}</span>;
}

// Comparativo NF-e × NFS-e — o que muda na FSM e no financeiro.
const NFX = [
  ["Documenta", "Circulação de mercadoria", "Prestação de serviço"],
  ["Imposto", "ICMS (e IPI, se industrializa)", "ISS"],
  ["Quem autoriza", "SEFAZ do estado", "Emissor nacional ou do município, sempre integrado ao ADN (padrão nacional desde 1º/01/2026)"],
  ["Corrigir depois", "Carta de correção (CC-e) — sem mexer em valor, imposto, destinatário ou data", "Substituição: emite-se uma nova NFS-e no lugar da anterior"],
  ["Cancelar", "Prazo curto (em regra 24 h, varia por UF) e só se a mercadoria não circulou", "Regra e prazo do emissor/município"],
  ["Estado só dela", "Denegada · Inutilizada · contingência (SVC/EPEC)", "Substituída"],
  ["Retenções", "Raras (ICMS-ST é outra coisa)", "ISS retido pelo tomador e, para PJ, retenções federais — o título recebe o valor líquido"],
  ["Onde se recolhe", "UF de origem / destino (DIFAL)", "Em regra no município do prestador; algumas atividades no local da execução (LC 116, art. 3º)"],
  ["Reforma tributária", "Ganha campos de IBS/CBS (transição 2026–2032)", "Ganha campos de IBS/CBS (transição 2026–2032)"],
];

function Estados({ p, est, setEst }) {
  const sai = (l) => p.trans.filter((t) => t.de === l).length;
  return (
    <div className="flp-est" role="group" aria-label="Filtrar transições pelo estado de origem">
      <button type="button" className={"flp-est-b todos" + (!est ? " on" : "")} aria-pressed={!est} onClick={() => setEst(null)}>Todos<small>{p.trans.length}</small></button>
      {p.estados.map((s) => { const n = sai(s.l); return (
        <button key={s.k} type="button" aria-pressed={est === s.l} className={"flp-est-b t-" + s.tipo + (est === s.l ? " on" : "")} onClick={() => setEst(est === s.l ? null : s.l)}
          title={n ? n + (n === 1 ? " ação sai daqui" : " ações saem daqui") : "Nenhuma ação sai daqui"}>
          {s.l}<small>{n || "fim"}</small>
        </button>); })}
    </div>
  );
}

function Detalhe({ p, onIr }) {
  const { Widget, Alert } = ds();
  const [est, setEst] = useState(null);
  const lista = est ? p.trans.filter((t) => t.de === est) : p.trans;
  const fiscal = p.id === "nfe" || p.id === "nfse";
  return (
    <div className="flp-det">
      <div className="flp-det-h">
        <span className="flx-lbl">{p.area} · {p.doc}</span>
        <h3>{p.n}</h3>
        <p>{p.porque}</p>
      </div>
      <Widget title="Estados" note="Clique numa etapa para filtrar as ações. O número é quantas ações saem dali.">
        <Estados p={p} est={est} setEst={setEst} />
        <div className="flp-leg" aria-hidden="true"><span className="t-ini">início</span><span className="t-esp">esperando algo de fora</span><span className="t-ok">fim</span><span className="t-x">fim por exceção</span></div>
      </Widget>
      {p.roteiro && (
        <Widget title="Roteiro por item, com etapas em paralelo" note={p.roteiro.titulo}>
          <div className="flp-rot">
            <div className="flp-rot-tr">{p.roteiro.trilhas.map((tr, i) => <ol key={i}>{tr.map((e) => <li key={e}>{e}</li>)}</ol>)}</div>
            <span className="flp-rot-j" aria-hidden="true"></span>
            <ol className="flp-rot-fim">{p.roteiro.junta.map((e) => <li key={e}>{e}</li>)}</ol>
          </div>
          <p className="flx-nota">As três trilhas andam juntas; Montagem só libera quando as três concluem. O estado da OS é o do item mais atrasado.</p>
        </Widget>
      )}
      <Widget title={est ? "A partir de " + est : "Transições"} note={est ? lista.length + (lista.length === 1 ? " ação possível" : " ações possíveis") : "Só passa se = condição para a ação acontecer · efeito = o que o sistema faz sozinho"} flush
        actions={est ? <button type="button" className="flx-link" onClick={() => setEst(null)}>Ver todas</button> : null}>
        {(() => { const r = resumoCob(p.id); return r.total ? <p className="flp-cob-r">No oimpresso: <b>{r.sim}</b> executa · <b>{r.parcial}</b> parcial · <b>{r.nao}</b> não existe · <b>{r.nao_verificado}</b> não verificado</p> : null; })()}
        {lista.length === 0 ? <p className="flp-fim">Nenhuma ação sai de <b>{est}</b> — o documento termina aqui.</p> :
        <div className="flp-tw">
          <table className="flp-t">
            <thead><tr><th scope="col">De → para</th><th scope="col">Ação</th><th scope="col">Só passa se</th><th scope="col">Efeito</th><th scope="col">Quem</th><th scope="col">No oimpresso</th></tr></thead>
            <tbody>{lista.map((t, i) => (
              <tr key={i}>
                <td><span className="flp-de">{t.de}</span><span className="flp-para">{t.para ? "→ " + t.para : "sem mudar de estado"}</span></td>
                <td><b>{t.acao}</b></td>
                <td>{t.guarda || <span className="flx-dim">—</span>}</td>
                <td>{t.efeito || <span className="flx-dim">—</span>}</td>
                <td className="flp-quem">{t.quem}</td>
                <td className="flp-cobc">{(() => { const c = (COB[p.id] || [])[p.trans.indexOf(t)]; if (!c) return <span className="flx-dim">—</span>; const e = COB_EST[c[0]]; return <><span className={"flx-pill " + e.tone}>{e.l}</span>{c[1] && <small title={c[1]}>{c[1]}</small>}</>; })()}</td>
              </tr>))}
            </tbody>
          </table>
        </div>}
      </Widget>
      {fiscal && (
        <Widget title="NF-e × NFS-e" note="As duas são FSMs diferentes — e numa OS de comunicação visual podem nascer as duas.">
          <div className="flp-tw">
            <table className="flp-t flp-cmp">
              <thead><tr><th scope="col"></th><th scope="col">NF-e (modelo 55)</th><th scope="col">NFS-e (padrão nacional)</th></tr></thead>
              <tbody>{NFX.map((r) => <tr key={r[0]}><th scope="row">{r[0]}</th><td>{r[1]}</td><td>{r[2]}</td></tr>)}</tbody>
            </table>
          </div>
          <div className="flp-alerta">
            <Alert tone="warn" title="Qual nota sai é decisão fiscal do item, não da tela">
              Impresso personalizado e sob encomenda, em regra, é serviço — só ISS (Súmula 156 do STJ), mesmo levando material. Revenda de material sem personalização é mercadoria (NF-e). Instalação é serviço. Há exceções (impresso que vira embalagem ou insumo de indústria). Por isso cada item carrega a <b>natureza fiscal</b>, definida com o contador, e o faturamento decide NF-e, NFS-e ou as duas.
            </Alert>
          </div>
        </Widget>
      )}
      <div className="flp-liga"><span className="flx-lbl">Aparece nas telas</span>
        {(TELAS[p.id] || []).length ? TELAS[p.id].map(([rota, l]) => <button key={rota} type="button" className="flp-chip" onClick={() => window.__selectRoute && window.__selectRoute(rota)}>{l}</button>)
          : <span className="flx-dim">ainda sem tela — entra na próxima onda</span>}
      </div>
      {p.liga.length > 0 && (
        <div className="flp-liga"><span className="flx-lbl">Conversa com</span>
          {p.liga.map((id) => { const o = PROCS.find((x) => x.id === id); return o ? <button key={id} type="button" className="flp-chip" onClick={() => onIr(id)}>{o.n}</button> : null; })}
        </div>
      )}
    </div>
  );
}

function FluxosProcessos(props) {
  const [selL, setSelL] = useState("producao");
  const sel = props.sel || selL;
  const ir = (id) => { (props.onSel || setSelL)(id); };
  const p = PROCS.find((x) => x.id === sel) || PROCS[0];
  return (
    <div className="flp">
      <p className="flx-intro">Cada documento passa por <b>etapas</b>. Para mudar de etapa alguém faz uma <b>ação</b>, que só vale se uma <b>condição</b> for cumprida; ao passar, o sistema faz coisas sozinho — o <b>efeito</b>. O modelo da empresa escolhe quais processos usar e com quais regras.</p>
      <div className="flp-wrap">
        <nav className="flp-nav" aria-label="Processos com fluxo">
          {AREAS.map((a) => {
            const ps = PROCS.filter((x) => x.area === a);
            if (!ps.length) return null;
            return (
              <div key={a} className="flp-grp">
                <span className="flx-lbl">{a}</span>
                {ps.map((x) => (
                  <button key={x.id} type="button" aria-current={x.id === sel ? "true" : undefined} className={"flp-item" + (x.id === sel ? " on" : "")} onClick={() => ir(x.id)}>
                    <span>{x.n}</span><small>{x.estados.length} estados · {x.trans.length} transições</small><CobItem id={x.id} />
                  </button>
                ))}
              </div>
            );
          })}
        </nav>
        <Detalhe key={p.id} p={p} onIr={ir} />
      </div>
    </div>
  );
}

window.FLX_PROC = { AREAS, PROCS, NFX, TELAS, COB, resumoCob };
window.FluxosProcessos = FluxosProcessos;
})();
