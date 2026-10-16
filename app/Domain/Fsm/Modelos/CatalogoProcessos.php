<?php

declare(strict_types=1);

namespace App\Domain\Fsm\Modelos;

/**
 * Processos com estados e transições, e quanto do ERP já executa cada transição (pedido [W]
 * 2026-10-16). É o que a aba Processos da tela "Fluxos e regras" consome.
 *
 * Duas origens:
 * - `devolucao` e `devolucao_compra`: o processo "Devolução, troca e garantia" do protótipo
 *   (`fluxos-processos.jsx`, id devolucao), completado — Refazer, Devolver valor e Negada tinham
 *   entrada sem saída — e escrito a partir do que o ERP já tem para devolução. A cobertura de cada
 *   transição foi lida no código (rota + classe), anotada em `onde`.
 * - os 3 processos semeados no motor (ProcessosDosSeeders): a cobertura é derivada da própria
 *   ação FSM — a ação existe e a rota que executa ações daquele documento existe — com uma regra
 *   dura do motor: ação cuja etapa de origem é terminal nunca roda (ExecuteStageActionService
 *   recusa), então conta como `nao`, mesmo cadastrada.
 *
 * cobertura.estado: `sim` (o ERP executa), `parcial` (executa só uma parte ou o código existe sem
 * rota que o chame), `nao` (não existe ou o motor recusa) e `nao_verificado` (não conferi).
 */
final class CatalogoProcessos
{
    public const ESTADOS_COBERTURA = ['sim', 'parcial', 'nao', 'nao_verificado'];

    /** Rota que executa as ações do motor para o documento de cada processo semeado. */
    public const ROTA_ACAO = [
        'os_reparo_padrao' => ['sim', 'POST /repair/job-sheets/{id}/fsm-action (RepairFsmActionController@execute)'],
        'oficina_mecanica_os' => ['sim', 'ServiceOrderFsmActionController@execute (Modules/OficinaAuto/Routes/web.php)'],
        // A OrdemProducao da ComVis não tem rota que execute ação; a venda tem a rota genérica, mas
        // nenhuma tela inicia venda neste processo (process_key só chega por chamada direta).
        'os_comunicacao_visual' => ['parcial', 'POST /sells/{id}/fsm-action (SaleFsmActionController@execute) — sem tela que inicie a venda neste processo; OrdemProducao sem rota de ação'],
    ];

    /**
     * Devolução: estados [chave, nome, tipo] (tipo: ini | esp | ok | x | null) e transições
     * [de, para|null, ação, guarda, efeito, quem, [estado, onde]]. para null = continua no estado.
     */
    private const DEVOLUCAO = [
        'devolucao' => [
            'nome' => 'Devolução, troca e garantia',
            'dominio' => 'venda',
            'estados' => [
                ['aberta', 'Aberta', 'ini'], ['em_analise', 'Em análise', null], ['refazer', 'Refazer', null],
                ['devolver_valor', 'Devolver valor', null], ['trocar', 'Trocar com crédito', null],
                ['encerrada', 'Encerrada', 'ok'], ['negada', 'Negada', 'x'],
            ],
            'transicoes' => [
                ['aberta', 'em_analise', 'Analisar', 'Fotos ou peça recebida', '', 'Atendimento',
                    ['nao', 'Não existe ocorrência pós-venda no ERP: a devolução começa direto no lançamento.']],
                ['em_analise', 'refazer', 'Aprovar refação', 'Dentro da garantia', 'Cria OS de retrabalho sem cobrança', 'Gerente',
                    ['nao', 'Nenhum código cria OS de retrabalho. As ações de garantia dos processos semeados só mudam a etapa.']],
                ['em_analise', 'devolver_valor', 'Aprovar devolução', '', '', 'Gerente',
                    ['nao', 'Sem ocorrência no ERP (ver "Analisar").']],
                ['em_analise', 'trocar', 'Aprovar troca com crédito', '', '', 'Gerente',
                    ['nao', 'Sem ocorrência no ERP (ver "Analisar").']],
                ['em_analise', 'negada', 'Negar', 'Motivo obrigatório', 'Avisa o cliente com o motivo', 'Gerente',
                    ['nao', 'Sem ocorrência no ERP (ver "Analisar").']],
                ['refazer', 'encerrada', 'Concluir refação', 'OS de retrabalho entregue', 'Encerra a ocorrência', 'Produção',
                    ['nao', 'Depende da OS de retrabalho, que não existe.']],
                ['devolver_valor', null, 'Registrar itens devolvidos (total ou parcial)', 'Quantidade até o vendido', 'Lança a devolução de venda e volta o estoque', 'Atendimento',
                    ['sim', 'resource sell-return (routes/web.php) → SellReturnController@store → TransactionUtil::addSellReturn (quantidade por item; volta o estoque por ProductUtil::updateProductQuantity)']],
                ['devolver_valor', null, 'Emitir NF-e de devolução', 'Houve NF-e autorizada', 'NF-e finNFe 4 referenciando a original', 'Fiscal',
                    ['parcial', 'Modules/NfeBrasil/Services/DevolucaoService::emitirDevolucao existe, sem rota nem chamador fora dos testes; DevolucaoController (legado, XML) não tem rota']],
                ['devolver_valor', 'encerrada', 'Estornar valor ao cliente', '', 'Estorna o título ou devolve o dinheiro', 'Financeiro',
                    ['parcial', 'A devolução de venda vira transação sell_return com situação de pagamento (TransactionUtil::updatePaymentStatus); o Modules/Financeiro não lê sell_return, então o título da venda não é estornado']],
                ['trocar', 'encerrada', 'Lançar crédito ao cliente', 'Cliente identificado', 'Credita o saldo (validade de 6 meses)', 'Atendimento',
                    ['parcial', 'Modules/Vestuario/Services/DevolucaoService::registrarDevolucao (tipo credito_ficha → vestuario_creditos_cliente) existe, sem rota nem chamador fora dos testes']],
            ],
        ],
        'devolucao_compra' => [
            'nome' => 'Devolução de compra',
            'dominio' => 'compra',
            'estados' => [
                ['aberta', 'Aberta', 'ini'], ['registrada', 'Registrada', null],
                ['encerrada', 'Encerrada', 'ok'], ['cancelada', 'Cancelada', 'x'],
            ],
            'transicoes' => [
                ['aberta', 'registrada', 'Registrar devolução ao fornecedor', 'Quantidade até o comprado', 'Baixa o estoque e lança a devolução de compra', 'Compras',
                    ['sim', 'resource purchase-return → PurchaseReturnController@store (ProductUtil::decreaseProductQuantity) e /purchase-return/save → CombinedPurchaseReturnController@save']],
                ['registrada', 'encerrada', 'Receber crédito ou estorno do fornecedor', '', 'Quita a devolução de compra', 'Financeiro',
                    ['nao_verificado', 'A devolução de compra tem situação de pagamento (TransactionUtil::updatePaymentStatus); não conferi se o Financeiro a liga ao título a pagar']],
                ['registrada', 'cancelada', 'Excluir devolução', '', 'Desfaz a devolução e o estoque', 'Compras',
                    ['sim', 'PurchaseReturnController@destroy (resource purchase-return)']],
            ],
        ],
    ];

    /**
     * Todos os processos no mesmo formato.
     *
     * @return array<string, array{nome:string, dominio:string, origem:string, chave_motor:?string,
     *     estados:list<array{chave:string, nome:string, tipo:?string}>,
     *     transicoes:list<array{de:string, para:?string, acao:string, rotulo:string, guarda:string, efeito:string, quem:string, cobertura:array{estado:string, onde:string}}>}>
     */
    public static function processos(): array
    {
        $out = [];
        foreach (self::DEVOLUCAO as $id => $p) {
            $out[$id] = [
                'nome' => $p['nome'], 'dominio' => $p['dominio'], 'origem' => 'prototipo', 'chave_motor' => null,
                'estados' => array_map(fn ($e) => ['chave' => $e[0], 'nome' => $e[1], 'tipo' => $e[2]], $p['estados']),
                'transicoes' => array_map(fn ($t) => [
                    'de' => $t[0], 'para' => $t[1], 'acao' => CatalogoModelos::chaveEtapa($t[2]), 'rotulo' => $t[2],
                    'guarda' => $t[3], 'efeito' => $t[4], 'quem' => $t[5],
                    'cobertura' => ['estado' => $t[6][0], 'onde' => $t[6][1]],
                ], $p['transicoes']),
            ];
        }

        foreach (ProcessosDosSeeders::PROCESSOS as $key => $p) {
            $terminal = [];
            $estados = [];
            foreach ($p['estados'] as [$chave, $nome, $inicial, $fim]) {
                $terminal[$chave] = $fim;
                $estados[] = ['chave' => $chave, 'nome' => $nome, 'tipo' => $inicial ? 'ini' : ($fim ? 'fim' : null)];
            }
            [$estadoRota, $rota] = self::ROTA_ACAO[$key];
            $out[$key] = [
                'nome' => $p['nome'], 'dominio' => 'producao', 'origem' => 'seeder', 'chave_motor' => $key,
                'estados' => $estados,
                'transicoes' => array_map(function ($t) use ($terminal, $estadoRota, $rota) {
                    [$de, $para, $acao, $rotulo, $efeito] = $t;
                    $cobertura = $terminal[$de]
                        ? ['estado' => 'nao', 'onde' => "Ação FSM {$acao} cadastrada, mas a etapa de origem é terminal: o ExecuteStageActionService recusa."]
                        : ['estado' => $estadoRota, 'onde' => "Ação FSM {$acao} — {$rota}" . ($efeito ? ' · side effect ' . class_basename($efeito) : '')];

                    return [
                        'de' => $de, 'para' => $para, 'acao' => $acao, 'rotulo' => $rotulo,
                        'guarda' => '', 'efeito' => $efeito ? class_basename($efeito) : '', 'quem' => '',
                        'cobertura' => $cobertura,
                    ];
                }, $p['transicoes']),
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
}
