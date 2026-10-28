<?php

declare(strict_types=1);

namespace App\Domain\Producao;

use App\Contracts\Compras\CustoDoInsumo;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Custo do produto fabricado — UMA conta para a Fabricação (`Modules/Manufacturing`) e a Produção
 * (`Modules/Producao`) (US-PCP-034). Cálculo de VALOR (regra mestre, proibicoes.md): só lê, nada grava.
 *
 *   material    = Σ quantidade do insumo × custo MÉDIO do insumo na data
 *   mão de obra = recebida já calculada (na Produção: `CustoRealService::daOs`, máquina + pessoa)
 *   perdas      = (material + mão de obra) × quantidade perdida ÷ quantidade boa
 *   total       = material + mão de obra + perdas  ·  por unidade = total ÷ quantidade boa
 *
 * As quantidades dos insumos são as da quantidade BOA. As perdas são o custo de repor as unidades
 * perdidas, por isso entram por fora e o custo por unidade divide só pelo que entrou no estoque.
 *
 * De onde vêm os insumos NÃO é desta classe: hoje cada módulo tem a sua composição (`mfg_recipes`
 * na Fabricação, consumo apontado na Produção). A fonte única é o `product_bom` (decisão [W]
 * 2026-10-13, D2 de memory/decisions/proposals/2026-10-09-fabricacao-vs-producao-decisao-w.md),
 * ainda sem ADR nem código. Quando existir, ela alimenta esta conta sem mudar a fórmula.
 *
 * O custo médio vem pela interface `App\Contracts\Compras\CustoDoInsumo` (módulo Compras), sempre
 * com o método `medio`, qualquer que seja a regra da empresa. Sem o módulo, ou insumo sem compra
 * recebida até a data, o insumo fica fora do material e a resposta sai `incompleto`.
 *
 * Arredondamento igual ao `CustoRealService`: produto a 4 casas pelo MySQL (DECIMAL exato, sem
 * float), soma em décimos de milésimo inteiros, e UMA ida para centavos por tipo, meio para cima.
 *
 * Nasce desligada: nenhum consumidor usa esta conta enquanto `ligada()` for falso.
 */
final class CustoDoFabricado
{
    public static function ligada(): bool
    {
        return (bool) config('custo_fabricado.unificado', false);
    }

    /**
     * @param  list<array{variation_id:int, quantidade:string}>  $insumos
     * @return array{material:string, mao_de_obra:string, perdas:string, total:string, por_unidade:string,
     *               metodo:string, incompleto:bool, sem_custo:list<int>}
     */
    public function calcular(int $businessId, array $insumos, string $maoDeObra, string $quantidadeBoa,
        string $quantidadePerdida, CarbonInterface|string $data): array
    {
        if ($this->dezMilesimos($quantidadeBoa) <= 0) {
            throw new InvalidArgumentException('A quantidade boa precisa ser maior que zero.');
        }
        if ($this->dezMilesimos($quantidadePerdida) < 0 || $this->dezMilesimos($maoDeObra) < 0) {
            throw new InvalidArgumentException('Perda e mão de obra não podem ser negativas.');
        }

        $custo = app()->bound(CustoDoInsumo::class) ? app(CustoDoInsumo::class) : null;
        $material = 0;
        $semCusto = [];
        foreach ($insumos as $insumo) {
            $variationId = (int) $insumo['variation_id'];
            $unitario = $custo?->naData($businessId, $variationId, $data, 'medio')['custo'];
            if ($unitario === null) {
                $semCusto[] = $variationId;

                continue;
            }
            $material += $this->dezMilesimos($this->sql('ROUND(? * ?, 4)', [(string) $insumo['quantidade'], $unitario]));
        }

        $centavos = [
            'material' => $this->paraCentavos($material),
            'mao_de_obra' => $this->paraCentavos($this->dezMilesimos($maoDeObra)),
        ];
        $base = $centavos['material'] + $centavos['mao_de_obra'];
        $centavos['perdas'] = $this->paraCentavos($this->dezMilesimos(
            $this->sql('ROUND(? * ? / ?, 4)', [$this->textoCentavos($base), $quantidadePerdida, $quantidadeBoa])
        ));
        $centavos['total'] = $base + $centavos['perdas'];

        $saida = array_map(fn (int $c) => $this->textoCentavos($c), $centavos);
        $saida['por_unidade'] = $this->sql('ROUND(? / ?, 4)', [$saida['total'], $quantidadeBoa]);
        $saida['metodo'] = 'medio';
        $saida['incompleto'] = $semCusto !== [];
        $saida['sem_custo'] = $semCusto;

        return $saida;
    }

    /**
     * Cada `?` vira DECIMAL antes da conta: parâmetro em texto faz o MySQL calcular em DOUBLE, e o
     * meio-para-cima da 5ª casa deixa de ser exato.
     *
     * @param  list<string>  $valores
     */
    private function sql(string $expressao, array $valores): string
    {
        $exata = str_replace('?', 'CAST(? AS DECIMAL(24,8))', $expressao);

        return $this->quatro((string) DB::selectOne("SELECT {$exata} AS v", $valores)->v);
    }

    private function paraCentavos(int $dezMilesimos): int
    {
        return intdiv($dezMilesimos + 50, 100);
    }

    private function textoCentavos(int $c): string
    {
        return sprintf('%d.%02d', intdiv($c, 100), $c % 100);
    }

    private function quatro(string $valor): string
    {
        [$inteiro, $fracao] = array_pad(explode('.', trim($valor) === '' ? '0' : trim($valor), 2), 2, '');

        return ($inteiro === '' ? '0' : $inteiro).'.'.substr(str_pad($fracao, 4, '0'), 0, 4);
    }

    private function dezMilesimos(string $valor): int
    {
        $negativo = str_starts_with(trim($valor), '-');
        [$inteiro, $fracao] = explode('.', $this->quatro(ltrim(trim($valor), '-')), 2);
        $n = (int) $inteiro * 10000 + (int) $fracao;

        return $negativo ? -$n : $n;
    }
}
