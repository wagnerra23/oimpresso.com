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

    /** processo => rótulo da ação => [estado, onde]. Toda ação dos processos listados aqui tem entrada. */
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
                    'cobertura' => self::cobertura($id, $erp, $t[2], in_array($tipoPorNome[$t[0]], self::FINAIS, true)),
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
    private static function cobertura(string $id, ?string $erp, string $rotulo, bool $origemFinal): array
    {
        if (isset(self::COBERTURA[$id][$rotulo])) {
            [$estado, $onde] = self::COBERTURA[$id][$rotulo];

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
}
