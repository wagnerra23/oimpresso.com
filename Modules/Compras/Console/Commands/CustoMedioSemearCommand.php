<?php

declare(strict_types=1);

namespace Modules\Compras\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Modules\Compras\Services\CustoMedioMovel;

/**
 * `compras:custo-medio-semear {business}` — ponto de partida do custo médio móvel (US-PROD-024).
 *
 * Para cada variação da empresa com estoque e sem custo médio guardado, calcula a semente (custo do
 * estoque que ainda resta nos lotes, CustoMedioMovel::semente) e mostra a tabela antes→depois:
 * custo do produto hoje (`dpp_inc_tax`) × custo médio que vai valer. Sem `--aplicar` é só leitura
 * (regra mestre de valor: o [W] vê a tabela antes de qualquer gravação).
 *
 * Com `--aplicar` grava só nas tabelas `compras_custo_medio*`. Nunca muda `variations`, preço ou
 * estoque. Idempotente: variação já semeada é pulada.
 */
class CustoMedioSemearCommand extends Command
{
    protected $signature = 'compras:custo-medio-semear {business : id da empresa} {--aplicar : grava a semente (sem isto é só a tabela)}';

    protected $description = 'Mostra (e com --aplicar grava) o custo médio inicial de cada variação com estoque. Não muda variations nem estoque.';

    public function handle(CustoMedioMovel $servico): int
    {
        $biz = (int) $this->argument('business');
        if ($biz <= 0 || ! DB::table('business')->where('id', $biz)->exists()) {
            $this->error('Empresa inexistente (Tier 0 ADR 0093: informe o business).');

            return self::FAILURE;
        }

        $variacoes = DB::table('variations as v')
            ->join('products as p', 'p.id', '=', 'v.product_id')
            ->leftJoin('compras_custo_medio as cm', fn ($j) => $j->on('cm.variation_id', '=', 'v.id')->where('cm.business_id', $biz))
            ->where('p.business_id', $biz)
            ->whereNull('cm.id')
            ->whereNull('v.deleted_at')
            ->orderBy('v.id')
            ->get(['v.id', 'v.sub_sku', 'p.name', 'v.dpp_inc_tax']);

        $linhas = [];
        $aplicadas = 0;
        foreach ($variacoes as $v) {
            $estoque = $servico->estoque($biz, (int) $v->id);
            if ($estoque <= 0) {
                continue;
            }
            $semente = $servico->semente($biz, (int) $v->id);
            if ($semente === null) {
                $linhas[] = [$v->id, $v->sub_sku, mb_strimwidth((string) $v->name, 0, 30, '…'), $this->n($estoque), $this->n((float) $v->dpp_inc_tax), 'sem lote', '—'];

                continue;
            }
            $antes = (float) $v->dpp_inc_tax;
            $linhas[] = [$v->id, $v->sub_sku, mb_strimwidth((string) $v->name, 0, 30, '…'), $this->n($estoque), $this->n($antes), $this->n($semente), $this->n($semente - $antes)];
            if ($this->option('aplicar') && $servico->semear($biz, (int) $v->id, $semente)) {
                $aplicadas++;
            }
        }

        $this->table(['variação', 'sku', 'produto', 'estoque', 'custo hoje (dpp)', 'custo médio', 'diferença'], $linhas);
        $this->info(count($linhas).' variação(ões) com estoque e sem custo médio. '
            .($this->option('aplicar') ? "Semeadas: {$aplicadas}." : 'Só leitura — nada gravado (use --aplicar).'));

        return self::SUCCESS;
    }

    private function n(float $v): string
    {
        return number_format($v, 4, ',', '.');
    }
}
