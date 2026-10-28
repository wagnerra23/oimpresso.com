<?php

declare(strict_types=1);

namespace App\Domain\Fsm\Modelos;

use App\Domain\Fsm\SideEffects\CancelarVendaCascade;
use App\Domain\Fsm\SideEffects\ConsumirEstoque;
use App\Domain\Fsm\SideEffects\LiberarReserva;
use App\Domain\Fsm\SideEffects\ReservarEstoque;

/**
 * Os processos da aba Processos (ProcessosDoPrototipo, aprovado por [W] em 2026-10-16) com quanto
 * do ERP já executa cada transição. É o que a aba Processos da tela "Fluxos e regras" consome.
 *
 * Cobertura de cada transição:
 * - devolução e devolução de compra: lida no código (rota + classe), anotada em COBERTURA;
 * - processos com `erp` (as 3 OS semeadas no motor): derivada — a ação existe no motor e a rota que
 *   executa ações daquele documento existe — com uma regra dura do motor: ação cuja etapa de origem
 *   é terminal nunca roda (ExecuteStageActionService recusa), então conta como `nao`;
 * - os demais: `nao_verificado` — o processo existe no protótipo e ninguém conferiu o código ainda.
 *
 * cobertura.estado: `sim` (o ERP executa), `parcial` (executa só uma parte, ou o código existe sem
 * rota que o chame), `nao` (não existe ou o motor recusa) e `nao_verificado` (não conferido).
 */
final class CatalogoProcessos
{
    public const ESTADOS_COBERTURA = ['sim', 'parcial', 'nao', 'nao_verificado'];

    /** Tipos de estado do protótipo que são finais. */
    public const FINAIS = ['ok', 'x'];

    /** Texto do efeito no protótipo para cada side effect do motor (a transcrição usa este mapa). */
    public const EFEITO_DO_SIDE_EFFECT = [
        ReservarEstoque::class => 'Reserva o estoque dos itens',
        ConsumirEstoque::class => 'Baixa o estoque reservado',
        LiberarReserva::class => 'Libera a reserva de estoque',
        CancelarVendaCascade::class => 'Cancela em cascata: nota, cobrança e aviso ao cliente',
    ];

    /** Rota que executa as ações do motor para o documento de cada processo semeado. */
    public const ROTA_ACAO = [
        'os_reparo_padrao' => ['sim', 'POST /repair/job-sheets/{id}/fsm-action (RepairFsmActionController@execute)'],
        'oficina_mecanica_os' => ['sim', 'ServiceOrderFsmActionController@execute (Modules/OficinaAuto/Routes/web.php)'],
        // A OrdemProducao da ComVis não tem rota que execute ação; a venda tem a rota genérica, mas
        // nenhuma tela inicia venda neste processo (process_key só chega por chamada direta).
        'os_comunicacao_visual' => ['parcial', 'POST /sells/{id}/fsm-action (SaleFsmActionController@execute) — sem tela que inicie a venda neste processo; OrdemProducao sem rota de ação'],
    ];

    /**
     * processo => rótulo da ação => [estado, onde]. Toda ação dos processos listados aqui tem entrada.
     * Quando duas ações têm o mesmo rótulo, a chave é "rótulo → estado de destino".
     */
    public const COBERTURA = [
        'devolucao' => [
            'Analisar' => ['nao', 'Não existe ocorrência pós-venda no ERP: a devolução começa direto no lançamento.'],
            'Aprovar refação' => ['nao', 'Nenhum código cria OS de retrabalho; as ações de garantia das OS semeadas só mudam a etapa.'],
            'Aprovar devolução' => ['nao', 'Sem ocorrência no ERP. Não há passo de aprovação. A NF-e de devolução existe e tem rota: POST fiscal/acoes/nfe/{emissao}/devolucao (fiscal.acoes.nfe.devolucao) → Modules/Fiscal/Http/Controllers/AcoesController@devolucao, que exige a permissão fiscal.nfe.acoes (ou superadmin) e chama Modules/NfeBrasil/Services/DevolucaoService::emitirDevolucao (R-NFE-039). O estorno do título não acontece (ver Estornar valor). DevolucaoController (legado) não tem rota.'],
            'Aprovar troca com crédito' => ['nao', 'Sem ocorrência no ERP (ver Analisar).'],
            'Negar' => ['nao', 'Sem ocorrência no ERP (ver Analisar).'],
            'Concluir refação' => ['nao', 'Depende da OS de retrabalho, que não existe.'],
            'Registrar itens devolvidos' => ['sim', 'resource sell-return (routes/web.php) → SellReturnController@store → TransactionUtil::addSellReturn (quantidade por item; volta o estoque por ProductUtil::updateProductQuantity)'],
            'Estornar valor' => ['parcial', 'A devolução de venda vira transação sell_return com situação de pagamento (TransactionUtil::updatePaymentStatus); o Modules/Financeiro não lê sell_return, então o título da venda não é estornado.'],
            'Lançar crédito' => ['parcial', 'Modules/Vestuario/Services/DevolucaoService::registrarDevolucao (tipo credito_ficha → vestuario_creditos_cliente) existe, sem rota nem chamador fora dos testes. A validade de 6 meses vem do ERP: constante VALIDADE_CREDITO_MESES = 6 do mesmo serviço (CDC Art. 50), gravada em expira_em e zerada por expirarCreditosVencidos; é fixa no código, não parâmetro da empresa.'],
        ],
        'devolucao-compra' => [
            'Registrar devolução ao fornecedor' => ['sim', 'resource purchase-return → PurchaseReturnController@store (ProductUtil::decreaseProductQuantity) e /purchase-return/save → CombinedPurchaseReturnController@save'],
            'Receber crédito ou estorno' => ['nao_verificado', 'A devolução de compra tem situação de pagamento (TransactionUtil::updatePaymentStatus); não conferi se o Financeiro a liga ao título a pagar.'],
            'Excluir devolução' => ['sim', 'PurchaseReturnController@destroy (resource purchase-return)'],
        ],
        // Venda: o motor tem venda_com_producao (ADR 0129), sem guarda; nenhuma condição "só passa se" existe no código.
        'venda' => [
            'Enviar ao cliente' => ['parcial', 'Ação FSM enviar_orcamento (SaleFsmActionController@execute, POST /sells/{id}/fsm-action) só muda a etapa. O envio de verdade é NotificationController@send (template new_quotation), fora da FSM. Sem conferência de contato e sem medição de abertura.'],
            'Cliente aprova' => ['parcial', 'Ação FSM cliente_aprovou com ReservarEstoque muda a etapa; o link público (SellPosController@showInvoice, GET /quote/{token}) só mostra. No legado não cria OS por item; na venda de modelo, entrar em Aprovado cria uma OS por item com roteiro (CriarOsDaVenda). Não gera parcelas de sinal e saldo; sem checagem de validade; a reserva espera payload.items, que a tela não manda.'],
            'Marcar perdido' => ['parcial', 'Na venda de modelo com a etapa Enviado (cv-pcp), AplicarModelo::aplicar monta a etapa Perdido e a ação marcar_perdido, que o motor recusa sem motivo (ExecuteStageActionService::execute); o motivo fica no histórico da venda. No legado, cliente_rejeitou segue levando a Cancelada sem pedir motivo. A venda perdida não alimenta o funil: DealPipelineService::moverStage (Crm, leva a perdido com motivo) só é chamado pelo kanban do funil (FunilController@mover, PATCH /crm/funil/{id}/etapa), que move o negócio à mão.'],
            'Faturar' => ['parcial', 'Ação FSM faturar sai de "Pronto pra faturar" e só muda a etapa. NF-e (NfeEmissaoController@emitir) e NFS-e (NfseController@emitirParaTransaction) são botões separados; o botão "Emitir NF-e + NFS-e" divide a venda pela natureza fiscal do item (US-NFE-069), mas a ação faturar não dispara nenhuma nota.'],
            'Concluir' => ['parcial', 'Ações FSM entregar e concluir só mudam a etapa (entregar exige Paga antes; no app, PedidoEntregaController@registrar). No legado, sem checagem de OS pronta; na venda de modelo, Entregue exige as OS por item prontas (OsDaVenda). Sem pesquisa de satisfação depois da entrega.'],
            'Cancelar' => ['parcial', 'Ação FSM cancelar_venda com CancelarVendaCascade::execute libera a reserva. Não cobra "nenhuma nota autorizada": tenta cancelar as notas, mas lê transaction_documents, que nenhum código grava. Não estorna sinal já pago (só cancela boleto pendente).'],
        ],
        'nfe' => [
            'Validar' => ['parcial', 'Não há estado Rascunho nem Validada: as regras (valor, NCM, item de serviço) rodam dentro de NfeService::emitirInterno (POST /nfe-brasil/transactions/{tx}/emitir). Sem validação XSD explícita; o certificado só é checado por ativo, não por validade.'],
            'Transmitir' => ['parcial', 'NfeService::emitirInterno reserva o número da série e transmite. Se a SEFAZ cai a nota vira erro_envio: contingência só manual (ContingenciaController@ativar, tpEmis 4), sem SVC nem EPEC, e RetentarContingenciaJob não tem quem o dispare.'],
            'Retorno 100' => ['parcial', 'NfeService::processarRetorno grava autorizada, XML e DANFE. O e-mail sozinho só sai no boleto pago; na venda, só por NfeEmissaoController@reenviarEmail. Não baixa estoque nem liga ao título.'],
            'Retorno de rejeição' => ['sim', 'NfeService::processarRetorno grava rejeitada com cStat e motivo; a tela de nota mostra o resultado. O campo com erro não é extraído (a orientação vem de um mapa na tela).'],
            'Corrigir e revalidar' => ['parcial', 'AcoesController@retransmitir → NfeService::retransmitir (POST /fiscal/acoes/nfe/{emissao}/retransmitir) não volta a nota para correção: marca a antiga inutilizada no banco local e emite outra, com outro número.'],
            'Retorno de denegação' => ['parcial', 'NfeService::processarRetorno grava denegada e só escreve log: não avisa o fiscal, e NfeService::retransmitir aceita denegada (deixa reemitir em vez de tratar o número como queimado).'],
            'Carta de correção (CC-e)' => ['parcial', 'AcoesController@cartaCorrecao → NfeCartaCorrecaoService::aplicar (POST /fiscal/acoes/nfe/{emissao}/cce) envia o evento 110110 e o liga à nota. A regra "não corrige valor, imposto, destinatário nem data" não é conferida (texto livre).'],
            'Cancelar' => ['parcial', 'AcoesController@cancelarNfe → NfeService::cancelar (POST /fiscal/acoes/nfe/{emissao}/cancelar) envia o evento 110111 e marca cancelada. Não confere o prazo localmente e não estorna estoque nem cancela o título.'],
            'Inutilizar número' => ['sim', 'AcoesController@inutilizar → NfeInutilizacaoService::inutilizar (POST /fiscal/acoes/nfe/inutilizar) comunica a faixa à SEFAZ e marca as emissões da faixa. Não confere localmente que os números foram pulados (quem barra é a SEFAZ).'],
        ],
        'nfse' => [
            'Validar' => ['parcial', 'NfseController@store (POST /nfse/emitir) confere o código LC 116 e calcula ISS e retenções federais. Não existe município de incidência (usa o do prestador) nem o estado Validada, e o rascunho criado pela venda não tem rota para validar.'],
            'Enviar DPS' => ['sim', 'NfseController@store → EmitirNfseJob → NfseEmissaoService::emitir envia a DPS assinada à Sefin Nacional (SnNfseAdapter). Só o emissor nacional está ligado; não há emissor municipal.'],
            'Retorno de autorização' => ['parcial', 'NfseEmissaoService::emitir grava emitida com número e chave. Não envia ao tomador. O ajuste do título pela retenção (ISS retido + federais, como baixa) existe no Financeiro (RetencaoNfseTituloService, US-FIN-072 · UC-FLX-30), desligado por padrão em cada empresa.'],
            'Retorno de rejeição' => ['parcial', 'A rejeição vira status erro com a mensagem (NfseEmissaoService::marcarErro), mas a nota não volta para correção: não pode ser editada.'],
            'Corrigir e revalidar' => ['nao', 'Não há rota de corrigir ou reemitir a nota rejeitada (Modules/NFSe/Routes/web.php só tem listar, emitir, emitir por venda, ver, cancelar e PDF); "Nova emissão" cria outra do zero.'],
            'Substituir' => ['nao', 'Não existe substituição de NFS-e: nenhuma rota, campo ou vínculo com a nota substituta.'],
            'Cancelar' => ['parcial', 'NfseController@cancelar → NfseEmissaoService::cancelar (POST /nfse/{nfse}/cancelar) marca cancelada sem olhar a resposta da Sefin (fica cancelada mesmo se ela recusar). Não confere regra nem prazo e não cancela o título.'],
        ],
        // Cobrança = fin_titulos (enum aberto/parcial/quitado/cancelado): Emitido, Vencido, Em cobrança e Conciliado não existem como estado.
        'cobranca' => [
            'Registrar boleto/PIX' => ['parcial', 'UnificadoController@emitirBoletoTitulo (POST /financeiro/unificado/{tituloId}/boleto) registra o boleto no Inter com conta ativa para boleto. Não envia ao cliente (CobrancaEmitida não é disparado), não muda o estado do título e não gera PIX por esse caminho.'],
            'Vencimento passou' => ['parcial', 'Não há estado vencido gravado nem cron: a tela calcula "atrasado" ao ler (UnificadoController::deriveStatus), já no D+0. CobrancaVencida é disparado no retorno CNAB, mas nenhum listener o ouve.'],
            'Iniciar régua' => ['nao', 'Não existe régua D+1/D+5/D+15 sobre título nem tarefa ao vendedor. A régua do RecurringBilling é fixa e só de leitura ("Em breve"); o lembrete agendado do núcleo (pos:autoSendPaymentReminder) é sobre vendas, não títulos.'],
            'Retorno do banco → Pago' => ['parcial', 'OnCobrancaPagaCreateFinanceiroTitulo::baixarTituloExistente quita o título quando o pagamento zera o saldo, mas só para o business dono do SaaS (saas_owner_business_id); webhook de boleto Inter/C6/Asaas só grava o evento. Não libera produção, não emite nota (EmitirNFeAoReceberPagamento só ouve o RecurringBilling, com NFEBRASIL_AUTO_EMISSION desligado) e não lança comissão.'],
            'Retorno do banco → Pago parcial' => ['parcial', 'Pelo banco, OnCobrancaPagaCreateFinanceiroTitulo::baixarTituloExistente reduz o valor em aberto e o título segue aberto (mesmas restrições do pagamento total). Na baixa manual, UnificadoController@baixar (POST /financeiro/unificado/{id}/baixar) divide em título quitado + saldo.'],
            'Conciliar extrato' => ['parcial', 'ConciliacaoController@match (POST /financeiro/conciliacao/{lineId}/match) casa a linha do extrato com o título e marca a linha. O título não muda de estado, não se confere se está quitado e nada é lançado no caixa.'],
            'Renegociar' => ['nao', 'Não existe renegociação: nenhuma ação, alçada ou geração de parcelas novas (só o cancelamento em lote, sem substitutos).'],
            'Baixar' => ['nao', 'Não existe baixa por perda: nenhum motivo, alçada, lançamento de perda ou bloqueio de crédito ligado a ela (o limite de crédito do núcleo é outra coisa).'],
        ],
        'pagar' => [
            'Aprovar' => ['parcial', 'UnificadoController@aprovar (POST /financeiro/unificado/{id}/aprovar) aprova de verdade, mas o único filtro é a permissão financeiro.titulo.aprovar: não existe alçada por valor.'],
            'Agendar no banco' => ['nao', 'Não existe estado Agendado (o título só tem aberto, parcial, quitado e cancelado), nem envio de pagamento ao banco, nem conferência de saldo projetado.'],
            'Retorno do banco' => ['nao', 'O retorno automático do banco só cria título a receber. Para conta a pagar só há a baixa manual (ContaPagarController@pagar), que não cria pagamento na compra: a compra não fica paga.'],
            'Conciliar' => ['parcial', 'ConciliacaoController@match marca a linha do extrato como conciliada, ligada ao título; o título não ganha estado Conciliado e a sugestão automática só procura títulos ainda em aberto.'],
        ],
        // Compra = núcleo UltimatePOS (purchase_requisition, purchase_order, purchase): não há estado Cancelado nem Conferido.
        'compra' => [
            'Aprovar requisição' => ['parcial', 'PurchaseOrderController@store (POST /purchase-order, com as requisições) cria o pedido e TransactionUtil::updatePurchaseOrderStatus avança a requisição. Não há passo de aprovação nem alçada, e o envio ao fornecedor é notificação manual.'],
            'Marcar em trânsito' => ['parcial', 'SellController@updateShipping (PUT /sells/update-shipping/{id}, o mesmo modal do pedido) marca o envio como shipped. Não confere a confirmação do fornecedor nem registra previsão de chegada.'],
            'Requisição → Cancelado · Cancelar' => ['parcial', 'Não existe estado Cancelado na requisição: PurchaseRequisitionController@destroy (DELETE /purchase-requisition/{id}) exclui, sem pedir motivo.'],
            'Pedido → Cancelado · Cancelar' => ['parcial', 'Não existe estado Cancelado no pedido: PurchaseOrderController@destroy (DELETE /purchase-order/{id}) exclui, ou o envio é marcado cancelado. Não avisa o fornecedor e não há orçamento de compra para liberar.'],
            'Receber' => ['parcial', 'PurchaseController@updateStatus (POST /purchases/update-status) com received dá entrada no estoque. Não há custo médio (troca pelo preço da última compra), proposta de preço nem conferência de quantidade.'],
            'Conferir NF-e de entrada' => ['nao', 'Não existe estado Conferido nem checagem de divergência; a conta a pagar nasce ao criar a compra. PurchaseXmlController não tem rota, e a manifestação (ManifestacaoController@confirmar) não tem ligação com a compra.'],
            'Pagar' => ['parcial', 'TransactionPaymentController@store (POST /payments) → TituloAutoService::registrarPagamento dá baixa no título e a compra fica paga, sem exigir título aprovado. Com a compra toda paga, o título termina cancelado em vez de quitado (BUG-4 registrado em teste).'],
        ],
        'requisicao' => [
            'Separar' => ['nao', 'Não existe requisição de material da OS (a única requisição é a de compra). A reserva do motor é por venda, variação e local, sem lote nem fração de rolo.'],
            'Sem saldo' => ['nao', 'Nada abre requisição de compra quando falta saldo (a de compra é manual) e nada pausa a etapa da OS.'],
            'Entregar' => ['nao', 'Não há entrega de material do almoxarifado para a OS; a única baixa ligada a documento é a do motor, sobre reservas da venda.'],
        ],
        'inventario' => [
            'Iniciar' => ['nao', 'Não existe contagem de inventário: nenhuma tabela ou rota de contagem, nenhum congelamento de saldo.'],
            'Fechar contagem' => ['nao', 'Não existe cálculo de divergência por item (não há contagem).'],
            'Aprovar ajuste' => ['parcial', 'StockAdjustmentController@store (POST /stock-adjustments) lança ajuste manual que só tira do estoque, com valor unitário e recuperado. Não vem de contagem, não tem alçada do gerente e não lança sobra.'],
        ],
        // Produção = PCP (Modules/Producao): as etapas vivem em pcp_programacoes, e nenhum código de produção grava nessa tabela.
        'producao' => [
            'Liberar' => ['parcial', 'SituacaoDaOrdem::liberar executa Liberar na OS por item (ADR 0436); as etapas nascem do roteiro do produto quando o cliente aprova (CriarOsDaVenda). Sem tela ainda; não reserva material nem calcula prazo pela fila.'],
            'Aguardar material' => ['parcial', 'SituacaoDaOrdem::liberar, com a chave producao_aguarda_material da empresa ligada e faltando insumo da receita, leva a OS de Na fila para Aguardando material e guarda no histórico o que faltava (D3, US-PCP-032). A chave ainda não tem campo na aba Fluxos.'],
            'Conferir material' => ['parcial', 'SituacaoDaOrdem::conferirMaterial (papel producao.liberar) devolve a OS para Na fila quando não falta mais nada e, faltando, devolve a lista. Não confere sozinho quando a compra é recebida, e a tela da OS ainda não mostra a falta.'],
            'Iniciar etapa' => ['parcial', 'ApontamentoOperadorService::iniciar abre o apontamento por QR ou tela e, na OS por item, leva a OS de Na fila a Em execução (SituacaoDaOrdem). A trava é o responsável da etapa, não o setor; etapa sem posto não aponta.'],
            'Pausar' => ['parcial', 'ApontamentoOperadorService::pausar exige motivo do cadastro do posto e, sem outra etapa da OS rodando, leva a OS por item a Pausada. Não há alerta de prazo.'],
            'Retomar' => ['sim', 'Iniciar de novo com a OS por item Pausada executa Retomar (SituacaoDaOrdem::aoIniciar) e o relógio volta.'],
            'Concluir etapa' => ['parcial', 'ApontamentoOperadorService::concluir conclui a etapa e registra Concluir etapa na OS por item; etapa de um bloco só começa com o bloco anterior (as duas trilhas) feito. O aviso ao próximo segue a ordem prevista, não a junta.'],
            'Reprovar no controle' => ['parcial', 'SituacaoDaOrdem::reprovar (motivo de refugo do posto + etapa de origem já feita) leva a OS a Retrabalho e reabre a etapa de origem como etapa nova; o custo entra pelas horas dela. Sem tela ainda.'],
            'Refazer' => ['parcial', 'SituacaoDaOrdem::refazer volta a OS por item a Em execução. Sem tela ainda.'],
            'Concluir item' => ['parcial', 'A última etapa concluída executa Concluir item (SituacaoDaOrdem::concluirItemSeTerminou): OS pronta e custo real congelado (CustoRealService::daOs). Não baixa o material real (decisão pendente).'],
            'Cancelar item' => ['parcial', 'SituacaoDaOrdem::cancelarItem, antes de liberar, cancela as etapas programadas. Não lança consumo como perda nem libera reserva. Sem tela ainda.'],
            'Aguardando material → Cancelada · Cancelar item' => ['parcial', 'SituacaoDaOrdem::cancelarItem também cancela a OS em Aguardando material e as etapas programadas. Não lança consumo como perda nem libera reserva. Sem tela ainda.'],
        ],
        // Entrega: as ações vivem no processo os_comunicacao_visual (instalação), que nenhuma tela inicia.
        'entrega' => [
            'Agendar' => ['parcial', 'Ação FSM agendar_instalacao (SaleFsmActionController@execute) só muda a etapa e não exige agenda. A agenda de equipe e veículo existe na OS da CV (AgendaInstalacao::agendar recusa horário com conflito), sem tela e sem aviso ao cliente.'],
            'Sair para o campo' => ['parcial', 'Ação FSM iniciar_instalacao só muda a etapa: não confere material e equipe nem avisa o cliente.'],
            'Confirmar nova data' => ['nao', 'Não existe a etapa Reagendado nem a ação de confirmar nova data; o status de reagendamento de cv_instalacoes não é gravado por nenhum código.'],
            'Reagendar' => ['parcial', 'Ação FSM reagendar_instalacao volta para a mesma etapa: não pede motivo, não tem etapa Reagendado e não avisa o cliente.'],
            'Concluir instalação' => ['parcial', 'Ação FSM concluir_instalacao muda a etapa. Foto e assinatura são só colunas de cv_instalacoes, sem quem grave nelas: não são exigidas e não há relatório na OS.'],
            'Fechar' => ['parcial', 'Ação FSM aprovacao_final_cliente muda para entregue. O protocolo só sai em PedidoEntregaController@registrar (venda, no app), e não há pesquisa em 2 dias.'],
        ],
        // Atendimento = conversas do WhatsApp (status open, awaiting_human, resolved, archived).
        'atendimento' => [
            'Assumir' => ['parcial', 'InboxController@assign (PATCH /atendimento/inbox/{id}/assign) só preenche o responsável; o status não muda e o SLA conta a partir das mensagens, não de assumir.'],
            'Pedir retorno' => ['nao', 'Não existe status "aguardando cliente" nem pausa de SLA (awaiting_human é o robô passando para humano).'],
            'Cliente respondeu' => ['nao', 'A mensagem que chega nunca muda o status de uma conversa que já existe; sem status de espera não há retomada de SLA.'],
            'Resolver' => ['parcial', 'InboxController@updateStatus com resolved → CsatDispatcher::dispatchOnResolve manda a pesquisa (só canal Baileys, flag ligada por padrão). Não exige assunto ligado: a conversa não tem vínculo com orçamento, OS ou cobrança.'],
        ],
        // Contrato = assinatura do RecurringBilling (status trialing, active, paused, canceled, past_due).
        'contrato' => [
            'Ativar' => ['parcial', 'RecurringBillingController@store (POST /recurring-billing) já cria a assinatura ativa: não há Rascunho, ação Ativar nem conferência de assinatura do cliente. As cobranças saem do job diário.'],
            'Dia do ciclo' => ['parcial', 'InvoiceGeneratorService::run (rb:generate-invoices, diário, só em live) emite a fatura e avança o vencimento. A nota do mês não sai no ciclo: só quando a fatura é paga, e com a flag de emissão automática ligada.'],
            'Suspender' => ['nao', 'past_due existe no enum, mas nenhum código grava esse status: não há contagem de parcelas vencidas nem aviso. A única suspensão é a pausa manual (RecurringBillingController@pausar).'],
            'Reativar' => ['parcial', 'RecurringBillingController@reativar (POST /recurring-billing/{id}/reativar) volta para ativo e o job retoma. Não confere se as parcelas em atraso foram pagas, e Suspenso nunca é atingido.'],
            'Encerrar' => ['nao', 'Não existe estado Encerrado, data de fim do contrato nem cobrança proporcional.'],
            'Cancelar' => ['sim', 'RecurringBillingController@cancelar (POST /recurring-billing/{id}/cancelar) cancela e o job para de gerar faturas. Não confere aviso prévio, e faturas já abertas se cancelam em outra rota.'],
        ],
        // Ponto: intercorrências (Portaria 671/2021), marcação original preservada (append-only).
        'ponto' => [
            'Submeter' => ['parcial', 'IntercorrenciaController@submeter (POST /ponto/intercorrencias/{id}/submeter) passa a pendente e entra na fila de aprovações. Não avisa o gestor, e a justificativa é exigida no cadastro, não na submissão.'],
            'Aprovar' => ['sim', 'AprovacaoController@aprovar (POST /ponto/aprovacoes/{id}/aprovar) aprova, grava quem e quando e reapura o dia. Não confere se quem aprova é o gestor do colaborador (só o acesso ao módulo).'],
            'Rejeitar' => ['parcial', 'AprovacaoController@rejeitar exige motivo e grava rejeitada, mas não avisa o colaborador e não exige que esteja pendente.'],
            'Aplicar' => ['parcial', 'ApuracaoService::aplicarIntercorrencias ajusta o espelho na aprovação, com a marcação original preservada. Não existe ação Aplicar do RH, nem quem grave APLICADA, nem checagem de período aberto.'],
            'Descartar' => ['sim', 'IntercorrenciaController@cancelar (POST /ponto/intercorrencias/{id}/cancelar) cancela o rascunho (aceita também pendente). Não confere se quem descarta é o próprio colaborador.'],
        ],
    ];

    /**
     * Todos os processos do protótipo, com chaves de estado nas transições e a cobertura.
     *
     * @return array<string, array{id:string, area:string, nome:string, doc:string, porque:string, chave_motor:?string,
     *     liga:list<string>, roteiro:?array, estados:list<array{chave:string, nome:string, tipo:string, lado:bool}>,
     *     transicoes:list<array{de:string, para:?string, acao:string, rotulo:string, guarda:string, efeito:string, quem:string, cobertura:array{estado:string, onde:string}}>}>
     */
    public static function processos(): array
    {
        $out = [];
        foreach (ProcessosDoPrototipo::PROCESSOS as $id => $p) {
            $chavePorNome = [];
            $tipoPorNome = [];
            $estados = [];
            foreach ($p['estados'] as [$k, $nome, $tipo, $lado]) {
                $chavePorNome[$nome] = $k;
                $tipoPorNome[$nome] = $tipo;
                $estados[] = ['chave' => $k, 'nome' => $nome, 'tipo' => $tipo, 'lado' => $lado];
            }
            $erp = $p['erp'] ?? null;

            $out[$id] = [
                'id' => $id, 'area' => $p['area'], 'nome' => $p['n'], 'doc' => $p['doc'], 'porque' => $p['porque'],
                'chave_motor' => $erp, 'liga' => $p['liga'], 'roteiro' => $p['roteiro'] ?? null,
                'estados' => $estados,
                'transicoes' => array_map(fn ($t) => [
                    'de' => $chavePorNome[$t[0]],
                    'para' => $t[1] !== null ? $chavePorNome[$t[1]] : null,
                    'acao' => CatalogoModelos::chaveEtapa($t[2]),
                    'rotulo' => $t[2], 'guarda' => $t[3], 'efeito' => $t[4], 'quem' => $t[5],
                    'cobertura' => self::cobertura($id, $erp, $t[2], $t[1], $t[0], in_array($tipoPorNome[$t[0]], self::FINAIS, true)),
                ], $p['trans']),
            ];
        }

        return $out;
    }

    /** @return array{sim:int, parcial:int, nao:int, nao_verificado:int, total:int} */
    public static function resumo(string $id): array
    {
        $r = array_fill_keys(self::ESTADOS_COBERTURA, 0);
        foreach (self::processos()[$id]['transicoes'] ?? [] as $t) {
            $r[$t['cobertura']['estado']]++;
        }

        return $r + ['total' => array_sum($r)];
    }

    /** @return array{estado:string, onde:string} */
    private static function cobertura(string $id, ?string $erp, string $rotulo, ?string $para, string $de, bool $origemFinal): array
    {
        $chave = self::chaveCobertura($id, $rotulo, $para, $de);
        if ($chave !== null) {
            [$estado, $onde] = self::COBERTURA[$id][$chave];

            return ['estado' => $estado, 'onde' => $onde];
        }
        if ($erp !== null) {
            if ($origemFinal) {
                return ['estado' => 'nao', 'onde' => "Ação \"{$rotulo}\" cadastrada no processo {$erp}, mas a etapa de origem é terminal: o ExecuteStageActionService recusa."];
            }
            [$estado, $rota] = self::ROTA_ACAO[$erp];

            return ['estado' => $estado, 'onde' => "Ação \"{$rotulo}\" do processo {$erp} — {$rota}"];
        }

        return ['estado' => 'nao_verificado', 'onde' => 'Processo só do protótipo; o código do ERP ainda não foi conferido para esta transição.'];
    }

    /**
     * Chave de COBERTURA desta ação, da mais específica para a mais geral: "origem → destino · rótulo",
     * "rótulo → destino", "rótulo". null se nenhuma.
     */
    public static function chaveCobertura(string $id, string $rotulo, ?string $para, ?string $de = null): ?string
    {
        $destino = $para ?? 'mesmo estado';
        $candidatas = [$rotulo . ' → ' . $destino, $rotulo];
        if ($de !== null) {
            array_unshift($candidatas, $de . ' → ' . $destino . ' · ' . $rotulo);
        }
        foreach ($candidatas as $chave) {
            if (isset(self::COBERTURA[$id][$chave])) {
                return $chave;
            }
        }

        return null;
    }
}
