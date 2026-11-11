<?php

declare(strict_types=1);

namespace Modules\Compras\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Modules\Compras\Services\ReconciliacaoCustoMedio;

/**
 * `compras:custo-medio-reconciliar` — confere o custo médio guardado contra o histórico.
 *
 * Sem `--aplicar` é só leitura: mostra o delta por variação e o resumo por empresa, de todas as
 * empresas ou só da `--business`. Gravar exige `--aplicar` e `--business` juntos (regra mestre de
 * valor: o [W] vê a tabela antes de qualquer gravação). Só escreve em `compras_custo_medio*`.
 * Entrada perdida é sempre só apontada. Não é agendado.
 */
class CustoMedioReconciliarCommand extends Command
{
    protected $signature = 'compras:custo-medio-reconciliar
        {--business= : id da empresa (obrigatório com --aplicar)}
        {--aplicar : grava as correções da empresa informada (sem isto é só a tabela)}';

    protected $description = 'Mostra (e com --aplicar --business grava) a diferença entre o custo médio guardado e o histórico. Não muda variations nem estoque.';

    public function handle(ReconciliacaoCustoMedio $servico): int
    {
        $informada = $this->option('business');
        $aplicar = (bool) $this->option('aplicar');

        if ($aplicar && empty($informada)) {
            $this->error('--aplicar exige --business (Tier 0 ADR 0093: gravação é por empresa).');

            return self::FAILURE;
        }
        if ($informada !== null && ((int) $informada <= 0 || ! DB::table('business')->where('id', (int) $informada)->exists())) {
            $this->error('Empresa inexistente (Tier 0 ADR 0093: informe o business).');

            return self::FAILURE;
        }

        $empresas = $informada !== null ? [(int) $informada] : $servico->empresas();

        $linhas = [];
        $resumo = [];
        $perdidas = [];
        foreach ($empresas as $biz) {
            $variacoes = $servico->variacoes($biz);
            $comDelta = 0;
            $orfas = 0;
            foreach ($variacoes as $variationId) {
                $r = $servico->variacao($biz, $variationId, $aplicar);
                if ($r === null) {
                    continue;
                }
                $comDelta++;
                $orfas += $r['orfas'];
                $linhas[] = [$biz, $r['variation_id'], $this->n($r['antes']), $this->n($r['depois']), $this->delta($r['antes'], $r['depois']), $r['motivo']];
            }
            foreach ($servico->entradasPerdidas($biz) as $p) {
                $perdidas[] = [$biz, $p['variation_id'], $p['transaction_id'], $this->n((float) $p['qtd'])];
            }
            $resumo[] = [$biz, count($variacoes), $comDelta, $orfas, count(array_filter($perdidas, fn ($p) => $p[0] === $biz))];
        }

        $this->table(['empresa', 'variação', 'guardado', 'reconciliado', 'delta', 'motivo'], $linhas);
        $this->table(['empresa', 'variações', 'com delta', 'compras órfãs', 'entradas perdidas'], $resumo);
        if ($perdidas !== []) {
            $this->warn('Entradas perdidas (compra recebida sem movimento): não são gravadas por este comando.');
            $this->table(['empresa', 'variação', 'compra', 'quantidade'], $perdidas);
        }
        $this->info($aplicar ? 'Correções gravadas na empresa '.$informada.'.' : 'Só leitura — nada gravado (use --aplicar --business=N).');

        return self::SUCCESS;
    }

    private function n(?float $v): string
    {
        return $v === null ? '—' : number_format($v, 4, ',', '.');
    }

    private function delta(?float $antes, ?float $depois): string
    {
        return ($antes === null || $depois === null) ? '—' : number_format($depois - $antes, 4, ',', '.');
    }
}
