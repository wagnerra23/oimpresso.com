<?php

declare(strict_types=1);

namespace App\Domain\Fsm\Modelos;

/**
 * Casos de uso, fontes, portes e o resto de `prototipo-ui/cowork/Wagner/fluxos-data.jsx` (window.FLX)
 * que o CatalogoModelos não guardava: o que a aba Casos de uso da tela consome.
 *
 * Exportado do .jsx por máquina, não digitado. Não edite à mão: mude o protótipo e exporte de novo.
 * `CatalogoModelosFluxoTest` compara com o .jsx.
 */
final class CasosDoPrototipo
{
    /** id => [nome, url] */
    public const FONTES = [
        'mubisys' => ['Mubisys', 'https://mubisys.com/producao'],
        'holdprint' => ['Holdprint', 'https://www.holdprint.net/'],
        'zenite' => ['Zênite', 'https://www.zsl.com.br/'],
        'calcgraf' => ['Calcgraf', 'https://www.calcgraf.com.br/solucao/pcp/'],
        'visua' => ['Visua', 'https://www.visua.com.br/'],
        'calcme' => ['Calcme', 'https://www.calcme.com.br/funcionalidades/'],
        'shopvox' => ['shopVOX', 'https://shopvox.com/features/job-management/'],
        'corebridge' => ['Corebridge', 'https://www.corebridge.net/production-management'],
        'cyrious' => ['Cyrious', 'https://www.cyrious.com/products/control/'],
        'printiq' => ['printIQ', 'https://www.printiq.com/products/core/'],
        'odoo' => ['Odoo MRP', 'https://www.odoo.com/documentation/19.0/applications/inventory_and_mrp/manufacturing/basic_setup/mo_costs.html'],
        'katana' => ['Katana MRP', 'https://support.katanamrp.com/en/articles/5914362-manufacturing-costs-for-products'],
        'oimpresso' => ['oimpresso', ''],
    ];

    /** porte => [rótulo, descrição] */
    public const PORTES = [
        'micro' => ['Micro', 'MEI e micro · até 5 pessoas'],
        'pequena' => ['Pequena', '6 a 20 pessoas'],
        'media' => ['Média', '21 a 100 pessoas'],
        'grande' => ['Grande', 'mais de 100 pessoas'],
    ];

    /** estado do caso no oimpresso => [rótulo, tom] */
    public const ESTADOS = [
        'producao' => ['em produção', 'ok'],
        'prototipo' => ['no protótipo', 'accent'],
        'parcial' => ['parcial', 'warn'],
        'modulo' => ['em outro módulo', 'warn'],
        'planejado' => ['planejado', 'mute'],
        'sem' => ['sem US', 'danger'],
        'verificar' => ['não verificado', 'mute'],
    ];

    /** Na ordem do protótipo: [id, título, processo, etapa, regra, fontes, porte, estado, US] */
    public const CASOS = [
        ['UC-FLX-01', 'Orçamento aprovado vira OS sem redigitar', 'venda', 'Aprovado', 'Cria a OS com itens, medidas e arquivos do orçamento', ['mubisys', 'shopvox', 'printiq', 'calcme'], 'micro', 'producao', 'US-COMVIS-020 (CV) · na calculadora de m² (/comunicacao-visual) o orçamento salvo recebe arquivos e o botão Aprovar e criar OS cria a OS com itens, medidas e arquivos; a venda do core já cria a OS por item (ADR 0436)'],
        ['UC-FLX-02', 'Cliente aprova a arte por link', 'producao', 'Aprovação do cliente', 'Envia link da prova; aprovado avança para Impressão, recusado volta para Arte com o comentário', ['holdprint', 'shopvox', 'corebridge', 'cyrious', 'printiq'], 'pequena', 'parcial', 'US-COMVIS-NEW-004 · na OS por item do PCP o cliente aprova a arte ou pede alteração por link sem login, trocar o arquivo aprovado pede nova aprovação e o Painel da Produção lista as artes esperando o cliente; falta o botão para gerar e enviar o link na tela da OS, o envio por WhatsApp/e-mail e a OS da CV'],
        ['UC-FLX-03', 'Orçamento calcula custo, material e lucro sozinho', 'venda', 'Orçamento', 'Ao salvar, recalcula o custo pela receita e mostra a margem', ['mubisys', 'holdprint'], 'micro', 'parcial', 'US-COMVIS-022 (CV) · a calculadora de m² mostra custo e margem por peça e no total para quem gerencia materiais; falta o alerta de margem abaixo do mínimo ao dar desconto'],
        ['UC-FLX-04', 'Termômetro de acesso da proposta', 'venda', 'Enviado', 'Registra quando o cliente abre; sem abertura em 48 h cria tarefa de retorno', ['mubisys'], 'pequena', 'producao', 'US-SELL-070 · o link público registra cada abertura, sem abertura em 48 h vira tarefa de retorno no CRM, e o detalhe do orçamento e a lista de cotações mostram aberto ou não aberto'],
        ['UC-FLX-05', 'Comissão do vendedor nasce no faturamento', 'venda', 'Faturado', 'Lança a comissão do vendedor sobre o valor faturado', ['mubisys'], 'pequena', 'planejado', 'US-COMM-002 (módulo Comissão dormente, ADR 0151)'],
        ['UC-FLX-06', 'Prazo de entrega calculado pela fila', 'venda', 'Aprovado', 'Calcula a data de entrega pela capacidade livre de cada etapa', ['holdprint', 'calcgraf'], 'media', 'parcial', 'US-PCP-028 · calcula a entrega em dias úteis pela fila dos postos e grava a data prometida na aprovação; a tela mostra Entrega prevista ao converter a cotação e Entrega prometida no detalhe da venda; falta descontar o já apontado da etapa iniciada'],
        ['UC-FLX-07', 'Etapas de produção vêm do cadastro do produto', 'producao', 'OS criada', 'Copia o roteiro de etapas do produto para a OS', ['shopvox'], 'pequena', 'parcial', 'US-PCP-026 · a OS por item copia as etapas do roteiro do produto, e a tela de Roteiros liga e tira produtos do roteiro; falta o roteiro padrão para produto sem roteiro (decisão [W]: hoje não vira OS)'],
        ['UC-FLX-08', 'Cada etapa tem setor e responsável', 'producao', 'qualquer etapa', 'Atribui ao responsável do setor e conta o tempo parado', ['mubisys', 'corebridge', 'calcme', 'cyrious'], 'pequena', 'parcial', 'US-PCP-030 · cada posto tem setor e operadores, editados na gaveta da tela de Postos, e só eles são responsáveis e começam a etapa (posto sem operadores aceita qualquer pessoa da Produção); o SPEC ainda deixa o aceite da tela aberto'],
        ['UC-FLX-09', 'Apontamento pelo celular com QR Code', 'producao', 'Iniciada / Concluída', 'A leitura do QR da OS inicia ou conclui a etapa e grava o tempo', ['mubisys', 'printiq', 'shopvox', 'cyrious'], 'pequena', 'parcial', 'US-COMVIS-004 · na tela do operador (/producao/apontamento) o QR da OS abre a etapa e grava o tempo, e o Concluir da máquina guarda m² impresso e tinta CMYK; falta o painel da máquina e esses campos no app das lojas'],
        ['UC-FLX-10', 'Alocador decide quem e quando começa', 'producao', 'Liberada', 'Escolhe operador e horário para não parar máquina nem faltar material', ['holdprint'], 'media', 'parcial', 'US-PCP-029 · o botão Alocar no cartão do Quadro (/producao/quadro) troca responsável e início da etapa pendente, só para operador do posto e com histórico; falta o aviso de conflito de pessoa, posto e material'],
        ['UC-FLX-11', 'Dados da máquina entram sem digitação', 'producao', 'Impressão', 'Fecha a etapa com metragem e tempo lidos do equipamento', ['zenite', 'calcgraf'], 'grande', 'planejado', 'US-COMVIS-004'],
        ['UC-FLX-12', 'Romaneio de impressão peça a peça', 'producao', 'Pré-impressão → Impressão', 'Gera a lista do que foi enviado ao setor de impressão e corte', ['mubisys'], 'pequena', 'parcial', 'US-PCP-023 · o romaneio peça a peça sai por OS (só API) e em lote ao aceitar o simulador de bobina no Quadro; falta gerar ao entrar na Impressão, o operador marcar a peça e ver ou imprimir o romaneio'],
        ['UC-FLX-13', 'Simulador de aproveitamento de mídia', 'producao', 'Pré-impressão', 'Agrupa os jobs na bobina para reduzir a perda de mídia', ['holdprint', 'printiq', 'calcgraf'], 'media', 'parcial', 'US-PCP-024 · no Quadro o PCP marca as OS da CV, abre Simular bobina, vê as faixas e aceita o arranjo, que vira o romaneio de cada OS; falta baixar a mídia pelo consumo previsto'],
        ['UC-FLX-14', 'Produção baixa o material consumido', 'producao', 'OP concluída', 'Consome do estoque a quantidade real, não a orçada', ['mubisys', 'calcgraf', 'cyrious'], 'micro', 'parcial', 'US-PCP-021 · US-PCP-031 · o botão Material da tela do operador baixa o material e o rolo de cada etapa e abate a reserva feita na venda, que liga no interruptor Reservar material na venda em Fluxos › Empresa (desligado por padrão); a regra dos modelos sem OS não executa e diz o motivo; falta a reserva por m² na CV e mostrar o reservado no saldo'],
        ['UC-FLX-15', 'Previsto × realizado por OS', 'producao', 'Entregue', 'Congela o custo real e compara com o orçado e o aprovado', ['holdprint', 'calcgraf', 'shopvox', 'corebridge', 'printiq'], 'pequena', 'parcial', 'US-COMVIS-005 · orçado × realizado por OS e por venda, com diferença e margem, só por API para o gestor; falta a tela, a margem por cliente, produto e mês e o relatório'],
        ['UC-FLX-16', 'Refugo e retrabalho com motivo', 'producao', 'Reprovada no acabamento', 'Abre retrabalho na etapa de origem e soma o custo à OS', ['calcgraf', 'zenite'], 'pequena', 'parcial', 'US-PCP-027 · o botão Registrar refugo da tela do operador grava peças e motivo do posto, repõe o material e manda para retrabalho, e o custo real da OS soma tudo; falta o refugo no app das lojas e o relatório por motivo e posto'],
        ['UC-FLX-17', 'Compra recebida atualiza custo e propõe preço', 'compra', 'Recebido', 'Recalcula o custo dos insumos (médio ou último) e propõe preço nos produtos que acompanham o custo', ['mubisys', 'holdprint', 'calcgraf'], 'micro', 'parcial', 'US-COM-026 · US-COM-028 · no Recebido aplica a regra de custo e preço da empresa, e o detalhe da compra lista os preços propostos com Aprovar e Recusar; falta a etapa Conferido e a fila na Fabricação e nas Pendências'],
        ['UC-FLX-18', 'Estoque fracionado com rastreio da mídia', 'compra', 'Recebido', 'Abre o rolo como lote e registra em que OS cada fração foi usada', ['mubisys'], 'pequena', 'parcial', 'US-COM-025 · o botão Material da tela do operador escolhe o rolo, e o relatório de Lotes rastreia em que OS cada rolo foi usado, só na tela nova (?tela=nova; o menu abre a antiga); falta separar no relatório a fração usada em OS da venda'],
        ['UC-FLX-19', 'Estoque mínimo vira requisição de compra', 'compra', 'Abaixo do mínimo', 'Cria a requisição com o fornecedor da última compra', ['mubisys'], 'micro', 'producao', 'US-COM-027 · o job diário cria a requisição por local e pelo fornecedor da última compra, com o preço dela; liga no interruptor Requisição pelo estoque mínimo em Fluxos › Empresa (desligado por padrão)'],
        ['UC-FLX-20', 'Custeio RKW e ponto de equilíbrio', 'financeiro', 'Fechamento do mês', 'Rateia despesas fixas por centro de custo e atualiza o custo-hora', ['holdprint', 'zenite'], 'media', 'parcial', 'US-FIN-070 · aba Custeio na DRE com o rateio das fixas, o ponto de equilíbrio e o centro de custo por setor, e a classe fixa ou variável no plano de contas; falta o botão para aplicar o custo-hora nos postos'],
        ['UC-FLX-21', 'Agenda da equipe de instalação', 'entrega', 'Pronto', 'Agenda equipe e veículo; bloqueia se não houver janela', ['shopvox', 'corebridge', 'visua', 'calcme', 'cyrious'], 'pequena', 'parcial', 'US-COMVIS-007 · agenda equipe e veículo na OS da CV, recusa sem janela, avisa o cliente por WhatsApp com opt-in e tem a tela semana × equipe em /comunicacao-visual/agenda, sem link no menu; falta o link, arrastar na grade, o checklist de EPI e o comprovante'],
        ['UC-FLX-22', 'Instalação com foto e assinatura', 'entrega', 'Instalado', 'Só conclui com foto do serviço e assinatura do cliente', ['shopvox', 'printiq'], 'pequena', 'parcial', 'US-OFICINA-047 · US-OFICINA-048 (Oficina) · a entrega da OS exige a foto do serviço e, com o ajuste da empresa ligado, a assinatura do cliente no checklist; o ajuste nasce desligado e só liga por comando, falta a tela para ligar'],
        ['UC-FLX-23', 'Protocolo de entrega', 'entrega', 'Entregue', 'Gera o protocolo com itens, volumes e quem recebeu', ['mubisys', 'corebridge', 'cyrious'], 'micro', 'parcial', 'US-SELL-073 · o app grava itens, volumes e quem recebeu, e o detalhe da venda mostra o protocolo de entrega; falta o protocolo da OS da CV'],
        ['UC-FLX-24', 'Cliente acompanha o status', 'entrega', 'cada transição', 'Avisa o cliente e atualiza o portal de acompanhamento', ['calcgraf', 'corebridge', 'shopvox', 'printiq'], 'pequena', 'parcial', 'US-CONSULTA-004 (Repair) · cada status diz, na tela de status, se avisa o cliente pelo WhatsApp (com opt-out LGPD), e o portal mostra o status novo; a etapa mudada pela FSM ainda não avisa'],
        ['UC-FLX-25', 'Pesquisa de satisfação depois da entrega', 'entrega', 'Entregue + 2 dias', 'Envia a pesquisa; nota baixa abre atendimento', ['mubisys'], 'pequena', 'parcial', 'US-SELL-071 · 2 dias depois da entrega envia a pesquisa e nota baixa abre tarefa no CRM; liga na aba Vendas das configurações (desligada por padrão) e a nota aparece no detalhe da venda; falta a lista das respostas'],
        ['UC-FLX-26', 'Boleto pago emite a NF-e', 'financeiro', 'Pago', 'Emite a NF-e sem ninguém clicar', ['oimpresso'], 'micro', 'producao', 'US-RB-044'],
        ['UC-FLX-28', 'Produção só libera com o sinal pago', 'producao', 'Aguardando liberação → Na fila', 'Guarda: sinal do orçamento pago; o retorno do banco libera a OS sozinho', ['oimpresso'], 'micro', 'parcial', 'US-PCP-025 · o campo Sinal mínimo em Fluxos › Empresa segura a OS em Aguardando liberação até o pago chegar ao percentual, e o pagamento libera a OS; o retorno do banco lançar o pagamento na venda está desligado por variável de ambiente; falta a parcela de sinal na venda'],
        ['UC-FLX-29', 'Régua de cobrança do título vencido', 'financeiro', 'Vencido → Em cobrança', 'Lembretes em D+1, D+5 e D+15 e tarefa ao vendedor', ['oimpresso'], 'micro', 'parcial', 'US-FIN-071 · lembretes por e-mail em D+1, D+5 e D+15 e tarefa ao vendedor, ligados na aba Financeiro das configurações (desligados por padrão), e o painel Lembretes em Contas a Receber; falta WhatsApp e o estado Em cobrança'],
        ['UC-FLX-30', 'ISS retido ajusta o valor do título', 'financeiro', 'NFS-e Autorizada', 'O título passa a cobrar o valor líquido das retenções', ['oimpresso'], 'pequena', 'parcial', 'US-FIN-072 · a NFS-e autorizada lança ISS, PIS, COFINS e CSLL retidos como baixa do título, e Contas a Receber mostra Bruto, Retido e Líquido; a chave nasce desligada e não tem interruptor; falta IR/INSS e as notas já autorizadas'],
        ['UC-FLX-31', 'Natureza fiscal do item decide a nota', 'financeiro', 'Faturado', 'Serviço gráfico sob encomenda → NFS-e · revenda → NF-e · instalação → NFS-e', ['oimpresso'], 'micro', 'parcial', 'US-NFE-069 · a natureza do produto separa a venda em NF-e e NFS-e, e o botão Emitir NF-e + NFS-e da gaveta da venda emite as duas; sem ele a NF-e recusa serviço; falta a instalação ir à NFS-e numa OS com material e o smoke na SEFAZ'],
        ['UC-FLX-32', 'NF-e em contingência quando a SEFAZ cai', 'financeiro', 'Validada → Transmitida', 'Emite em SVC/EPEC e transmite quando a SEFAZ voltar', ['oimpresso'], 'pequena', 'parcial', 'US-NFE-070 · US-NFE-071 · em /fiscal/config a contingência escolhe SVC-AN, SVC-RS ou EPEC; a nota sai com dhCont/xJust e é retransmitida quando a SEFAZ volta; falta a marcação de contingência no DANFE e o smoke na SEFAZ'],
        ['UC-FLX-27', 'Uma OS, duas notas (NF-e + NFS-e)', 'financeiro', 'Faturado', 'Emite NF-e da mercadoria e NFS-e do serviço na mesma OS', ['oimpresso'], 'pequena', 'parcial', 'US-NFE-069 · US-NFE-060 (NFS-e) · o botão Emitir NF-e + NFS-e da gaveta da venda mostra as duas partes e emite as duas notas, com o desconto rateado pelo bruto e o frete na NF-e (decisão [W]); o Faturado não emite sozinho (só por clique); falta a instalação na NFS-e e o smoke na SEFAZ'],
        ['UC-FLX-33', 'Orçamento aprovado guarda o custo do dia', 'venda', 'Aprovado', 'Grava o custo previsto de cada item (material, tinta, hora de máquina, instalação) com a data; compra depois não muda esse valor', ['mubisys', 'shopvox', 'odoo'], 'micro', 'parcial', 'US-SELL-067 (Vendas) · US-COMVIS-019 (material e hora de máquina da CV) · o custo do dia fica guardado e aparece no detalhe do orçamento e na margem da calculadora da CV; tinta e instalação ficaram fora por decisão [W]'],
        ['UC-FLX-34', 'Orçamento aberto avisa que o custo mudou', 'venda', 'Orçamento / Enviado', 'Mostra o custo guardado ao lado do custo de hoje e a margem que sobra; só recalcula se alguém pedir', ['oimpresso'], 'micro', 'producao', 'US-SELL-068'],
        ['UC-FLX-35', 'Preço negociado abaixo da margem pede aprovação', 'venda', 'Aprovado', 'Compara o preço fechado com o custo guardado; abaixo da margem mínima só aprova quem tem permissão', ['oimpresso'], 'pequena', 'producao', 'US-SELL-069'],
        ['UC-FLX-36', 'OS em andamento avisa insumo que mudou de custo', 'producao', 'qualquer etapa antes de Concluída', 'Mostra o custo da abertura e o de hoje; a regra da compra decide se a OS mantém o custo ou acompanha', ['katana'], 'pequena', 'parcial', 'US-PCP-022 (Produção) · a regra da compra (congela | vivo) vale na OS aberta, em JSON; falta mostrar na tela da OS e a previsão do material ainda não apontado'],
        ['UC-FLX-37', 'Material consumido leva o custo do dia da baixa', 'producao', 'Concluída', 'Congela as horas apontadas e o material pelo custo da data da baixa; compra depois não reescreve a OS', ['odoo', 'katana'], 'pequena', 'producao', 'US-PCP-021 (Produção) · a OS baixa o material de cada etapa pelo custo do lote, pelo botão Material da tela do operador; falta o estorno'],
        ['UC-FLX-38', 'Custo do insumo guarda o histórico por data', 'compra', 'Recebido', 'Cada recebimento grava o custo com a data; o custo novo vale daqui pra frente e o anterior nunca é apagado', ['katana', 'odoo'], 'micro', 'parcial', 'US-COM-024 · custo na data pelo método da empresa ou do insumo, escolhido na gaveta de Insumos e no cadastro do produto; o campo do cadastro só aparece com o módulo Compras instalado, e falta o orçamento e a OS lerem o método'],
    ];

    /** modelo => [inspirado em, regra de custo da compra] */
    public const MODELOS = [
        'balcao' => [['mubisys'], ['etapa' => 'recebido', 'metodo' => 'ultimo', 'preco' => 'so_custo', 'regraEm' => 'produto', 'aprovacao' => 'recebimento', 'ops' => 'congela']],
        'cv-pequena' => [['mubisys', 'holdprint', 'shopvox'], ['etapa' => 'recebido', 'metodo' => 'medio', 'preco' => 'propor', 'regraEm' => 'produto', 'aprovacao' => 'recebimento', 'ops' => 'congela']],
        'cv-pcp' => [['holdprint', 'printiq', 'corebridge'], ['etapa' => 'conferido', 'metodo' => 'medio', 'preco' => 'propor', 'regraEm' => 'produto', 'aprovacao' => 'fabricacao', 'ops' => 'congela']],
        'grafica' => [['calcgraf', 'zenite', 'printiq'], ['etapa' => 'recebido', 'metodo' => 'medio', 'preco' => 'aplicar', 'regraEm' => 'produto', 'aprovacao' => 'recebimento', 'ops' => 'congela']],
        'brindes' => [['mubisys', 'shopvox'], ['etapa' => 'recebido', 'metodo' => 'ultimo', 'preco' => 'aplicar', 'regraEm' => 'categoria', 'aprovacao' => 'recebimento', 'ops' => 'vivo']],
        'instalacao' => [['holdprint', 'visua', 'shopvox'], ['etapa' => 'recebido', 'metodo' => 'medio', 'preco' => 'propor', 'regraEm' => 'produto', 'aprovacao' => 'recebimento', 'ops' => 'congela']],
        'oficina' => [['corebridge'], ['etapa' => 'recebido', 'metodo' => 'medio', 'preco' => 'so_custo', 'regraEm' => 'produto', 'aprovacao' => 'recebimento', 'ops' => 'congela']],
    ];
}
