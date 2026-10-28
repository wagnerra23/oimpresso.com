<?php

namespace Modules\Financeiro\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Financeiro\Models\Titulo;
use Modules\Financeiro\Models\TituloRateio;

/**
 * Rateio do título em centros de custo (US-FIN-075).
 *
 * ⚠️ REGRA MESTRE — VALOR: a soma das linhas fecha `valor_total` em centavos, sem float.
 * Toda conta é feita em inteiros (centavos e décimos-milésimos de ponto percentual).
 *
 * Dois modos, sem misturar no mesmo título:
 *  - percentual: as linhas somam exatamente 100,0000%. O valor de cada linha é o piso de
 *    total × % e os centavos que sobram vão, um a um, às linhas de maior resto (método do
 *    maior resto; empate fica com a linha que veio primeiro).
 *  - valor: as linhas somam exatamente o valor do título. O percentual é derivado só para
 *    exibição (4 casas, a última linha absorve o arredondamento para fechar 100%).
 *
 * Lista vazia = sem rateio. Título antigo (sem linha nenhuma) continua válido.
 */
class RateioService
{
    public const MODO_PERCENTUAL = 'percentual';

    public const MODO_VALOR = 'valor';

    /** 100% em décimos-milésimos de ponto percentual (4 casas). */
    private const CEM_POR_CENTO = 1_000_000;

    /**
     * Distribui o valor do título. Não toca o banco.
     *
     * @param  array<int, array<string, mixed>>  $linhas  cada linha: centro_custo_id + percentual (modo %) ou valor (modo valor)
     * @return array<int, array{centro_custo_id:int, percentual:string, valor:string}>
     *
     * @throws ValidationException
     */
    public function distribuir(string $valorTotal, string $modo, array $linhas): array
    {
        if ($linhas === []) {
            return [];
        }
        if (! in_array($modo, [self::MODO_PERCENTUAL, self::MODO_VALOR], true)) {
            throw ValidationException::withMessages(['rateio_modo' => 'Modo de rateio inválido.']);
        }

        $ids = array_map(fn ($l) => (int) ($l['centro_custo_id'] ?? 0), $linhas);
        if (in_array(0, $ids, true)) {
            throw ValidationException::withMessages(['rateio' => 'Toda linha do rateio precisa de um centro de custo.']);
        }
        if (count(array_unique($ids)) !== count($ids)) {
            throw ValidationException::withMessages(['rateio' => 'O mesmo centro de custo aparece duas vezes no rateio.']);
        }

        $totalCentavos = self::paraInteiro((string) $valorTotal, 2);
        if ($totalCentavos <= 0) {
            throw ValidationException::withMessages(['rateio' => 'Título sem valor não pode ser rateado.']);
        }

        return $modo === self::MODO_PERCENTUAL
            ? $this->porPercentual($totalCentavos, $ids, $linhas)
            : $this->porValor($totalCentavos, $ids, $linhas);
    }

    /**
     * Grava o rateio do título (substitui o anterior). Centro de outra empresa ou inativo é recusado.
     *
     * @param  array<int, array<string, mixed>>  $linhas
     * @return array<int, array{centro_custo_id:int, percentual:string, valor:string}>
     */
    public function salvar(Titulo $titulo, int $businessId, string $modo, array $linhas): array
    {
        if ((int) $titulo->getAttribute('business_id') !== $businessId) {
            throw ValidationException::withMessages(['rateio' => 'Título de outra empresa.']);
        }

        $distribuido = $this->distribuir((string) $titulo->valor_total, $modo, $linhas);

        $ids = array_column($distribuido, 'centro_custo_id');
        if ($ids !== []) {
            $validos = DB::table('fin_centros_custo')
                ->where('business_id', $businessId)
                ->whereIn('id', $ids)
                ->where('ativo', true)
                ->whereNull('deleted_at')
                ->count();
            if ($validos !== count($ids)) {
                throw ValidationException::withMessages(['rateio' => 'Centro de custo inexistente, inativo ou de outra empresa.']);
            }
        }

        DB::transaction(function () use ($titulo, $businessId, $distribuido): void {
            TituloRateio::query()->where('business_id', $businessId)->where('titulo_id', $titulo->id)->delete();
            foreach ($distribuido as $linha) {
                TituloRateio::query()->create($linha + ['business_id' => $businessId, 'titulo_id' => $titulo->id]);
            }
        });

        return $distribuido;
    }

    private function porPercentual(int $totalCentavos, array $ids, array $linhas): array
    {
        $pcts = array_map(fn ($l) => self::paraInteiro((string) ($l['percentual'] ?? '0'), 4), $linhas);
        foreach ($pcts as $p) {
            if ($p <= 0) {
                throw ValidationException::withMessages(['rateio' => 'Todo percentual do rateio precisa ser maior que zero.']);
            }
        }
        if (array_sum($pcts) !== self::CEM_POR_CENTO) {
            throw ValidationException::withMessages(['rateio' => 'Os percentuais do rateio precisam somar 100%.']);
        }

        $valores = [];
        $restos = [];
        foreach ($pcts as $i => $p) {
            $produto = $totalCentavos * $p;
            $valores[$i] = intdiv($produto, self::CEM_POR_CENTO);
            $restos[$i] = $produto % self::CEM_POR_CENTO;
        }

        $sobra = $totalCentavos - array_sum($valores);
        $ordem = array_keys($restos);
        usort($ordem, fn ($a, $b) => $restos[$b] <=> $restos[$a] ?: $a <=> $b);
        for ($k = 0; $k < $sobra; $k++) {
            $valores[$ordem[$k]]++;
        }

        $out = [];
        foreach ($ids as $i => $id) {
            if ($valores[$i] <= 0) {
                throw ValidationException::withMessages(['rateio' => 'Um percentual do rateio é pequeno demais para o valor do título.']);
            }
            $out[] = ['centro_custo_id' => $id, 'percentual' => self::deInteiro($pcts[$i], 4), 'valor' => self::deInteiro($valores[$i], 2)];
        }

        return $out;
    }

    private function porValor(int $totalCentavos, array $ids, array $linhas): array
    {
        $valores = array_map(fn ($l) => self::paraInteiro((string) ($l['valor'] ?? '0'), 2), $linhas);
        foreach ($valores as $v) {
            if ($v <= 0) {
                throw ValidationException::withMessages(['rateio' => 'Todo valor do rateio precisa ser maior que zero.']);
            }
        }
        if (array_sum($valores) !== $totalCentavos) {
            throw ValidationException::withMessages(['rateio' => 'Os valores do rateio precisam somar o valor do título.']);
        }

        $out = [];
        $acum = 0;
        $ultimo = count($valores) - 1;
        foreach ($ids as $i => $id) {
            $pct = $i === $ultimo
                ? self::CEM_POR_CENTO - $acum
                : intdiv($valores[$i] * self::CEM_POR_CENTO * 2 + $totalCentavos, $totalCentavos * 2);
            $acum += $pct;
            $out[] = ['centro_custo_id' => $id, 'percentual' => self::deInteiro($pct, 4), 'valor' => self::deInteiro($valores[$i], 2)];
        }

        return $out;
    }

    /**
     * "1234.5678" → inteiro na escala pedida, arredondando metade para cima. Aceita vírgula
     * decimal ("1.234,56" vira 1234,56 se tiver vírgula). Sem float em nenhum passo.
     */
    public static function paraInteiro(string $valor, int $casas): int
    {
        $v = trim($valor);
        if (str_contains($v, ',')) {
            $v = str_replace(['.', ','], ['', '.'], $v);
        }
        if (! preg_match('/^(-?)(\d*)(?:\.(\d*))?$/', $v, $m) || ($m[2] === '' && ($m[3] ?? '') === '')) {
            throw ValidationException::withMessages(['rateio' => "Número inválido no rateio: {$valor}"]);
        }
        $frac = str_pad($m[3] ?? '', $casas + 1, '0');
        $n = (int) (($m[2] === '' ? '0' : $m[2]).substr($frac, 0, $casas));
        if ((int) $frac[$casas] >= 5) {
            $n++;
        }

        return $m[1] === '-' ? -$n : $n;
    }

    public static function deInteiro(int $n, int $casas): string
    {
        $neg = $n < 0;
        $s = str_pad((string) abs($n), $casas + 1, '0', STR_PAD_LEFT);

        return ($neg ? '-' : '').substr($s, 0, -$casas).'.'.substr($s, -$casas);
    }
}
