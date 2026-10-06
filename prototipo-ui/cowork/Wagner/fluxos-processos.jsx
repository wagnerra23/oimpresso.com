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
    estados: [S("agl", "Aguardando liberação", "ini"), S("fila", "Na fila"), S("exe", "Em execução"), S("pau", "Pausada", "esp", 1), S("ret", "Retrabalho", "nor", 1), S("conc", "Concluída", "ok"), S("canc", "Cancelada", "x")],
    trans: [
      T("Aguardando liberação", "Na fila", "Liberar", "Arte aprovada · sinal pago (se exigido) · material disponível ou reservado", "Reserva o material · calcula prazo pela fila", "PCP / sistema"),
      T("Na fila", "Em execução", "Iniciar etapa", "Operador do setor da etapa", "Começa a contar o tempo (QR ou tela)", "Operador"),
      T("Em execução", "Pausada", "Pausar", "Motivo: falta material · máquina · aguardando cliente", "Para o relógio; alerta se passar do prazo", "Operador"),
      T("Pausada", "Em execução", "Retomar", "", "Volta a contar o tempo", "Operador"),
      T("Em execução", null, "Concluir etapa", "Etapa seguinte do roteiro existe", "Passa para a próxima etapa (ou para as paralelas)", "Operador"),
      T("Em execução", "Retrabalho", "Reprovar no controle", "Motivo + etapa de origem", "Soma custo de refugo à OS", "Acabamento / qualidade"),
      T("Retrabalho", "Em execução", "Refazer", "", "Reabre a etapa de origem", "PCP"),
      T("Em execução", "Concluída", "Concluir item", "Última etapa do roteiro feita", "Baixa o material real · congela custo · se todos os itens: OS pronta", "Operador"),
      T("Aguardando liberação", "Cancelada", "Cancelar item", "Nada consumido · ou consumo lançado como perda", "Libera reserva", "Gerente"),
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
    porque: "Defeito, erro de arte ou troca. Decide se refaz, devolve dinheiro ou emite nota de devolução.",
    estados: [S("abe", "Aberta", "ini"), S("ana", "Em análise"), S("ref", "Refazer"), S("dev", "Devolver valor"), S("enc", "Encerrada", "ok"), S("neg", "Negada", "x")],
    trans: [
      T("Aberta", "Em análise", "Analisar", "Fotos ou peça recebida", "", "Atendimento"),
      T("Em análise", "Refazer", "Aprovar refação", "Dentro da garantia", "Cria OS de retrabalho sem cobrança", "Gerente"),
      T("Em análise", "Devolver valor", "Aprovar devolução", "", "NF-e de devolução (se houve NF-e) · estorno do título", "Gerente"),
    ], liga: ["producao", "nfe", "cobranca"] },

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
        {lista.length === 0 ? <p className="flp-fim">Nenhuma ação sai de <b>{est}</b> — o documento termina aqui.</p> :
        <div className="flp-tw">
          <table className="flp-t">
            <thead><tr><th scope="col">De → para</th><th scope="col">Ação</th><th scope="col">Só passa se</th><th scope="col">Efeito</th><th scope="col">Quem</th></tr></thead>
            <tbody>{lista.map((t, i) => (
              <tr key={i}>
                <td><span className="flp-de">{t.de}</span><span className="flp-para">{t.para ? "→ " + t.para : "sem mudar de estado"}</span></td>
                <td><b>{t.acao}</b></td>
                <td>{t.guarda || <span className="flx-dim">—</span>}</td>
                <td>{t.efeito || <span className="flx-dim">—</span>}</td>
                <td className="flp-quem">{t.quem}</td>
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
                    <span>{x.n}</span><small>{x.estados.length} estados · {x.trans.length} transições</small>
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

window.FLX_PROC = { AREAS, PROCS, NFX, TELAS };
window.FluxosProcessos = FluxosProcessos;
})();
