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
            'Aprovar devolução' => ['nao', 'Sem ocorrência no ERP. A NF-e de devolução tem serviço (Modules/NfeBrasil/Services/DevolucaoService::emitirDevolucao) sem rota nem chamador fora dos testes; DevolucaoController (legado) não tem rota.'],
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
            'Cliente aprova' => ['parcial', 'Ação FSM cliente_aprovou com ReservarEstoque muda a etapa; o link público (SellPosController@showInvoice, GET /quote/{token}) só mostra. Não cria OS por item nem parcelas de sinal e saldo; sem checagem de validade; a reserva espera payload.items, que a tela não manda.'],
            'Marcar perdido' => ['parcial', 'Ação FSM cliente_rejeitou leva a Cancelada: não existe a etapa Perdido, o motivo não é pedido e nada alimenta funil (DealPipelineService::moverStage do Crm, que leva a perdido com motivo, não tem chamador).'],
            'Faturar' => ['parcial', 'Ação FSM faturar sai de "Pronto pra faturar" e só muda a etapa. NF-e (NfeEmissaoController@emitir) e NFS-e (NfseController@emitirParaTransaction) são botões separados, sem decidir pela natureza fiscal do item.'],
            'Concluir' => ['parcial', 'Ações FSM entregar e concluir só mudam a etapa (entregar exige Paga antes; no app, PedidoEntregaController@registrar). Sem checagem de OS pronta e sem pesquisa de satisfação depois da entrega.'],
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
            'Retorno de autorização' => ['parcial', 'NfseEmissaoService::emitir grava emitida com número e chave. Não envia ao tomador e não ajusta o título pelo ISS retido (o Financeiro não referencia NFS-e).'],
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
                    'cobertura' => self::cobertura($id, $erp, $t[2], $t[1], in_array($tipoPorNome[$t[0]], self::FINAIS, true)),
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
    private static function cobertura(string $id, ?string $erp, string $rotulo, ?string $para, bool $origemFinal): array
    {
        $chave = self::chaveCobertura($id, $rotulo, $para);
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

    /** Chave de COBERTURA desta ação: "rótulo → destino" se existir, senão "rótulo"; null se nenhuma. */
    public static function chaveCobertura(string $id, string $rotulo, ?string $para): ?string
    {
        foreach ([$rotulo . ' → ' . ($para ?? 'mesmo estado'), $rotulo] as $chave) {
            if (isset(self::COBERTURA[$id][$chave])) {
                return $chave;
            }
        }

        return null;
    }
}
