<?php

declare(strict_types=1);

namespace Modules\Financeiro\Console\Commands;

use App\Transaction;
use Illuminate\Console\Command;
use Modules\Financeiro\Services\DevolucaoVendaTituloService;
use Modules\Financeiro\Services\TituloAutoService;

/**
 * Aplica a regra "devolução abate o título da venda" às devoluções gravadas antes dela.
 *
 * Até 2026-11-03 o Financeiro ignorava `sell_return`: o título a receber da venda devolvida
 * seguia aberto pelo valor inteiro (31 casos em produção naquela data, 30 na ROTA LIVRE). A
 * partir do DevolucaoVendaTituloService as novas devoluções abatem sozinhas; este comando
 * passa pelas antigas.
 *
 * SEGURO: só mostra o antes→depois por padrão. `--apply` grava. `--business` é obrigatório
 * (Tier 0). Idempotente: devolução que já abateu o valor certo não muda nada.
 *
 * Duas contas por título, para conferir: o aberto depois pelo serviço (total − baixas) e o
 * aberto antes menos o abatimento calculado aqui. As duas têm de bater.
 *
 * Uso:
 *   php artisan financeiro:devolucoes-abater --business=4            # só mostra
 *   php artisan financeiro:devolucoes-abater --business=4 --apply
 */
class DevolucoesAbaterCommand extends Command
{
    protected $signature = 'financeiro:devolucoes-abater
        {--business= : business_id (obrigatório)}
        {--apply : Grava as baixas. Sem isso só mostra o antes→depois}';

    protected $description = 'Abate o título da venda pelas devoluções antigas (dry-run por padrão)';

    public function handle(DevolucaoVendaTituloService $devolucoes, TituloAutoService $titulos): int
    {
        $businessId = (int) $this->option('business');
        if ($businessId <= 0) {
            $this->error('--business=N é obrigatório.');

            return self::FAILURE;
        }

        $apply = (bool) $this->option('apply');

        // SUPERADMIN: comando de console sem session — devoluções do business informado
        $lista = Transaction::query()
            ->withoutGlobalScopes()
            ->where('business_id', $businessId)
            ->where('type', 'sell_return')
            ->whereNotNull('return_parent_id')
            ->orderBy('id')
            ->get();

        $linhas = [];
        $totais = ['abate' => 0.0, 'antes' => 0.0, 'depois' => 0.0, 'n' => 0, 'quita' => 0, 'diverge' => 0];

        foreach ($lista as $devolucao) {
            $titulo = $devolucoes->tituloDaVenda($devolucao);
            if (! $titulo || $titulo->status === 'cancelado') {
                continue;
            }

            $ativa = $devolucoes->baixaAtiva($devolucao);
            $antes = (float) $titulo->valor_aberto;
            $valor = $devolucoes->valorAAbater($devolucao, $titulo, $ativa);
            $jaAbatido = (float) ($ativa?->valor_baixa ?? 0);

            if (abs($valor - $jaAbatido) < 0.005) {
                continue;
            }

            $esperado = round($antes + $jaAbatido - $valor, 2);

            if ($apply) {
                $devolucoes->sincronizar($devolucao);
                $titulo->refresh();
                $depois = (float) $titulo->valor_aberto;
            } else {
                $depois = $titulos->abertoPorBaixas((int) $titulo->id, (float) $titulo->valor_total) + $jaAbatido - $valor;
                $depois = round(max(0.0, $depois), 2);
            }

            $confere = abs($depois - $esperado) < 0.005;
            $totais['n']++;
            $totais['abate'] += $valor - $jaAbatido;
            $totais['antes'] += $antes;
            $totais['depois'] += $depois;
            $totais['quita'] += $depois <= 0 ? 1 : 0;
            $totais['diverge'] += $confere ? 0 : 1;

            $linhas[] = [
                $devolucao->invoice_no,
                $titulo->numero,
                number_format($antes, 2, ',', '.'),
                number_format($valor - $jaAbatido, 2, ',', '.'),
                number_format($depois, 2, ',', '.'),
                $confere ? 'ok' : 'DIVERGE ('.number_format($esperado, 2, ',', '.').')',
            ];
        }

        $this->table(['Devolução', 'Título', 'Aberto antes', 'Abate', 'Aberto depois', 'Confere'], $linhas);
        $this->line(sprintf(
            '%s · %d título(s) · aberto %s → %s · abate %s · %d quitam · %d divergem',
            $apply ? 'APLICADO' : 'SIMULAÇÃO (use --apply para gravar)',
            $totais['n'],
            number_format($totais['antes'], 2, ',', '.'),
            number_format($totais['depois'], 2, ',', '.'),
            number_format($totais['abate'], 2, ',', '.'),
            $totais['quita'],
            $totais['diverge'],
        ));

        return $totais['diverge'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
