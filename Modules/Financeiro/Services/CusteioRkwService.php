<?php

declare(strict_types=1);

namespace Modules\Financeiro\Services;

use App\Contracts\Producao\HorasDosPostos;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Custeio RKW e ponto de equilíbrio do mês (US-FIN-070 · UC-FLX-20). Cálculo de VALOR
 * (regra mestre, proibicoes.md): `calcular()` só LÊ; nada é gravado sem `aplicar()`.
 *
 *   F  = Σ títulos a PAGAR da competência, conta `classe_custo = fixa`     (não cancelados)
 *   V  = Σ títulos a PAGAR da competência, conta `classe_custo = variavel` (não cancelados)
 *   R  = Σ títulos a RECEBER da competência, conta `tipo = receita`        (não cancelados)
 *   H  = Σ segundos apontados (trechos fechados) de todos os postos no mês
 *
 *   rateio do posto p       = F × h_p ÷ H      (maior resto: a soma bate F ao centavo)
 *   parcela fixa por hora   = F × 3600 ÷ H     (igual para todo posto: o rateio é por hora)
 *   custo-hora RKW sugerido = custo_hora digitado do posto + parcela fixa por hora
 *   margem de contribuição  = (R − V) ÷ R
 *   ponto de equilíbrio     = F ÷ margem = F × R ÷ (R − V);  R ≤ 0 ou R ≤ V → null + aviso
 *   centro de custo (setor) = Σ dos postos do setor (`pcp_postos.setor`, decisão [W] 2026-10-21):
 *                             horas e rateio somados dos postos; a soma dos setores bate F ao centavo
 *
 * Conta classificada como fixa/variável é o ÚNICO critério: `natureza` (débito/crédito) e
 * `dre_linha` não dizem se o gasto varia com a produção. Conta sem classe fica fora.
 *
 * Tudo em centavos inteiros, arredondamento meio-para-cima uma vez; sem float.
 * Tier 0 (ADR 0093): `$businessId` por parâmetro em toda consulta; horas via contrato.
 */
final class CusteioRkwService
{
    /**
     * @return array{mes: string, despesas_fixas: string, custos_variaveis: string, receita: string,
     *   horas: string, parcela_fixa_por_hora: ?string, margem_contribuicao_pct: ?string,
     *   ponto_equilibrio: ?string, avisos: list<string>,
     *   postos: list<array{posto_id: int, codigo: string, nome: string, setor: ?string, horas: string, rateio_fixo: string,
     *     custo_hora_atual: string, parcela_fixa_por_hora: string, custo_hora_rkw: string}>,
     *   setores: list<array{setor: ?string, postos: int, horas: string, rateio_fixo: string}>}
     */
    public function calcular(int $businessId, string $mes): array
    {
        if (! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $mes)) {
            throw new DomainException('Mês inválido: use AAAA-MM.');
        }
        $fixas = $this->somaTitulos($businessId, $mes, 'pagar', 'classe_custo', 'fixa');
        $variaveis = $this->somaTitulos($businessId, $mes, 'pagar', 'classe_custo', 'variavel');
        $receita = $this->somaTitulos($businessId, $mes, 'receber', 'tipo', 'receita');

        $horas = app()->bound(HorasDosPostos::class) ? app(HorasDosPostos::class)->noMes($businessId, $mes) : [];
        $totalSeg = array_sum(array_column($horas, 'segundos'));
        $avisos = [];

        $porHora = $totalSeg > 0 ? intdiv($fixas * 3600 * 2 + $totalSeg, 2 * $totalSeg) : null;
        $rateios = $this->rateioMaiorResto($fixas, array_column($horas, 'segundos'));
        if ($totalSeg === 0) {
            $avisos[] = 'Sem horas apontadas no mês: não há base para ratear as despesas fixas.';
        }
        if ($fixas === 0) {
            $avisos[] = 'Nenhuma despesa fixa no mês: classifique as contas do plano como fixa/variável.';
        }

        $postos = [];
        $setores = [];
        foreach ($horas as $i => $p) {
            $atual = $this->centavos($p['custo_hora']);
            $setor = $p['setor'] ?? null;
            // Texto livre: "Corte" e "corte " são o mesmo setor; vale a grafia do 1º posto.
            $chave = $setor === null ? '' : mb_strtolower($setor);
            $setores[$chave] ??= ['setor' => $setor, 'postos' => 0, 'segundos' => 0, 'rateio' => 0];
            $setores[$chave]['postos']++;
            $setores[$chave]['segundos'] += $p['segundos'];
            $setores[$chave]['rateio'] += $rateios[$i];
            $postos[] = ['posto_id' => $p['posto_id'], 'codigo' => $p['codigo'], 'nome' => $p['nome'], 'setor' => $setor,
                'horas' => $this->horas($p['segundos']), 'rateio_fixo' => $this->reais($rateios[$i]),
                'custo_hora_atual' => $this->reais($atual), 'parcela_fixa_por_hora' => $this->reais((int) $porHora),
                'custo_hora_rkw' => $this->reais($atual + (int) $porHora)];
        }

        // Setores em ordem alfabética; postos sem setor por último.
        // (string): chave numérica ("10") vira int no array, e o arquivo é strict_types.
        uksort($setores, fn ($a, $b) => ((string) $a === '') <=> ((string) $b === '') ?: strcmp((string) $a, (string) $b));
        $centros = array_values(array_map(fn ($c) => ['setor' => $c['setor'], 'postos' => $c['postos'],
            'horas' => $this->horas($c['segundos']), 'rateio_fixo' => $this->reais($c['rateio'])], $setores));

        $margem = $receita - $variaveis;
        $pe = null;
        $mcPct = null;
        if ($receita <= 0 || $margem <= 0) {
            $avisos[] = 'Margem de contribuição não positiva no mês: ponto de equilíbrio não se aplica.';
        } else {
            $mcPct = sprintf('%.2f', intdiv($margem * 10000 * 2 + $receita, 2 * $receita) / 100);
            $pe = $this->reais(intdiv(2 * $fixas * $receita + $margem, 2 * $margem));
        }

        return ['mes' => $mes, 'despesas_fixas' => $this->reais($fixas), 'custos_variaveis' => $this->reais($variaveis),
            'receita' => $this->reais($receita), 'horas' => $this->horas($totalSeg),
            'parcela_fixa_por_hora' => $porHora === null ? null : $this->reais($porHora),
            'margem_contribuicao_pct' => $mcPct, 'ponto_equilibrio' => $pe, 'avisos' => $avisos, 'postos' => $postos, 'setores' => $centros];
    }

    /**
     * Ação explícita: grava o custo-hora RKW sugerido no posto. Apontamentos já gravados
     * mantêm a cópia do custo do início (UC-PCCH-01). Null = posto sem horas nesta empresa.
     *
     * @return array{posto_id: int, antes: string, depois: string}|null
     */
    public function aplicar(int $businessId, string $mes, int $postoId): ?array
    {
        $linha = collect($this->calcular($businessId, $mes)['postos'])->firstWhere('posto_id', $postoId);
        if ($linha === null || ! app()->bound(HorasDosPostos::class)) {
            return null;
        }
        $troca = app(HorasDosPostos::class)->trocarCustoHora($businessId, $postoId, $linha['custo_hora_rkw']);

        return $troca === null ? null : ['posto_id' => $postoId] + $troca;
    }

    private function somaTitulos(int $businessId, string $mes, string $tipo, string $colunaPlano, string $valor): int
    {
        $soma = DB::table('fin_titulos as t')
            ->join('fin_planos_conta as p', function ($j) {
                $j->on('p.id', '=', 't.plano_conta_id')->on('p.business_id', '=', 't.business_id');
            })
            ->where('t.business_id', $businessId)->where('t.competencia_mes', $mes)
            ->where('t.tipo', $tipo)->where('t.status', '!=', 'cancelado')->whereNull('t.deleted_at')
            ->where('p.'.$colunaPlano, $valor)
            ->sum('t.valor_total');

        return $this->centavos((string) $soma);
    }

    /**
     * @param  list<int>  $pesos
     * @return list<int>
     */
    private function rateioMaiorResto(int $total, array $pesos): array
    {
        $soma = array_sum($pesos);
        if ($soma === 0) {
            return array_fill(0, count($pesos), 0);
        }
        $partes = [];
        $restos = [];
        foreach ($pesos as $i => $peso) {
            $partes[$i] = intdiv($total * $peso, $soma);
            $restos[$i] = ($total * $peso) % $soma;
        }
        arsort($restos);
        foreach (array_slice(array_keys($restos), 0, $total - array_sum($partes)) as $i) {
            $partes[$i]++;
        }

        return $partes;
    }

    /** "1234.5678" → centavos, meio para cima na 3ª casa. */
    private function centavos(string $valor): int
    {
        $negativo = str_starts_with($valor, '-');
        [$inteiro, $fracao] = array_pad(explode('.', ltrim($valor, '-'), 2), 2, '0');
        $dezMilesimos = (int) $inteiro * 10000 + (int) str_pad(substr($fracao, 0, 4), 4, '0');
        $c = intdiv($dezMilesimos + 50, 100);

        return $negativo ? -$c : $c;
    }

    private function reais(int $centavos): string
    {
        return ($centavos < 0 ? '-' : '').sprintf('%d.%02d', intdiv(abs($centavos), 100), abs($centavos) % 100);
    }

    private function horas(int $segundos): string
    {
        return sprintf('%.2f', intdiv($segundos * 2 + 36, 72) / 100);
    }
}
