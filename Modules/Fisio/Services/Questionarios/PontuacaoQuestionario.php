<?php

declare(strict_types=1);

namespace Modules\Fisio\Services\Questionarios;

/**
 * Pontuação dos questionários de uso livre (US-FISIO-011) — serviço PURO: sem DB, sem sessão.
 *
 * ⚠️ Regras de pontuação aguardam revisão da Dra. Dejeane (responsável técnica, ADR 0437) contra
 * a publicação de cada versão brasileira. Fontes consultadas para a ESTRUTURA (2026-10-18):
 *  - RMDQ: 24 itens sim/não, escore = nº de "sim" (0–24), item não marcado conta 0 (Roland & Morris 1983).
 *  - PSFS: 1 a 5 atividades de 0 a 10, escore = soma ÷ nº de atividades (Stratford et al. 1995,
 *    Physiother Can 47:258-63).
 *  - LEFS: 20 itens de 0 a 4, escore = soma (0–80) (Binkley et al. 1999, Phys Ther 79(4):371-83).
 *  - SPADI: 13 itens de 0 a 10; dor = itens 1–5 ÷ 50 × 100; incapacidade = itens 6–13 ÷ 80 × 100;
 *    total = soma 1–13 ÷ 130 × 100 (Roach et al. 1991).
 *    ⚠️ Divergência entre fontes, A CONFERIR com Martins et al. 2010: (a) total como MÉDIA das duas
 *    subescalas em vez de ÷130; (b) regra de item "não se aplica". Por isso este serviço EXIGE os 13
 *    itens (não faz rateio de item faltante) e devolve as duas subescalas separadas.
 *  - EVA/END: valor de 0 a 10 marcado pelo paciente.
 *
 * Exigir TODOS os itens (sem rateio) é a escolha conservadora enquanto a regra de dado faltante de
 * cada manual não for conferida: melhor recusar do que calcular um escore que a publicação não
 * reconhece.
 *
 * O resultado é número, nunca interpretação ("melhorou", "grave", conduta): isso seria SaMD
 * (ADR 0437 §5). A decisão clínica é do profissional.
 */
final class PontuacaoQuestionario
{
    /**
     * @param  array<string, mixed>  $respostas
     * @return array{escore: float, detalhe: array<string, float>, respostas: array<string, mixed>}
     *
     * @throws RespostaInvalidaException
     */
    public function pontuar(string $instrumento, array $respostas): array
    {
        if (in_array($instrumento, CatalogoQuestionarios::BLOQUEADOS, true)) {
            throw new RespostaInvalidaException('instrumento', 'Questionário bloqueado até haver licença de uso (ADR 0437 §8).');
        }

        return match ($instrumento) {
            'eva' => $this->eva($respostas),
            'rmdq' => $this->rmdq($respostas),
            'psfs' => $this->psfs($respostas),
            'lefs' => $this->lefs($respostas),
            'spadi' => $this->spadi($respostas),
            default => throw new RespostaInvalidaException('instrumento', 'Questionário desconhecido.'),
        };
    }

    /** @param  array<string, mixed>  $r */
    private function eva(array $r): array
    {
        $valor = $this->numero($r['valor'] ?? null, 0, 10, 'respostas.valor', false);

        return ['escore' => $valor, 'detalhe' => [], 'respostas' => ['valor' => $valor]];
    }

    /** @param  array<string, mixed>  $r */
    private function rmdq(array $r): array
    {
        $itens = $this->itens($r, 24, 0, 1, true);

        return ['escore' => (float) array_sum($itens), 'detalhe' => [], 'respostas' => ['itens' => $itens]];
    }

    /** @param  array<string, mixed>  $r */
    private function psfs(array $r): array
    {
        $atividades = $r['atividades'] ?? null;
        if (! is_array($atividades) || $atividades === [] || count($atividades) > 5 || ! array_is_list($atividades)) {
            throw new RespostaInvalidaException('respostas.atividades', 'Informe de 1 a 5 atividades.');
        }

        $limpas = [];
        foreach ($atividades as $i => $a) {
            $descricao = is_array($a) ? trim((string) ($a['descricao'] ?? '')) : '';
            if ($descricao === '' || mb_strlen($descricao) > 255) {
                throw new RespostaInvalidaException("respostas.atividades.{$i}.descricao", 'Descreva a atividade com as palavras do paciente (até 255 caracteres).');
            }
            $nota = $this->numero($a['nota'] ?? null, 0, 10, "respostas.atividades.{$i}.nota", true);
            $limpas[] = ['descricao' => $descricao, 'nota' => (int) $nota];
        }

        $media = array_sum(array_column($limpas, 'nota')) / count($limpas);

        return ['escore' => round($media, 2), 'detalhe' => [], 'respostas' => ['atividades' => $limpas]];
    }

    /** @param  array<string, mixed>  $r */
    private function lefs(array $r): array
    {
        $itens = $this->itens($r, 20, 0, 4, false);

        return ['escore' => (float) array_sum($itens), 'detalhe' => [], 'respostas' => ['itens' => $itens]];
    }

    /** @param  array<string, mixed>  $r */
    private function spadi(array $r): array
    {
        $itens = $this->itens($r, 13, 0, 10, false);
        $dor = array_sum(array_slice($itens, 0, 5, true));
        $incapacidade = array_sum(array_slice($itens, 5, 8, true));

        return [
            'escore' => round(($dor + $incapacidade) / 130 * 100, 2),
            'detalhe' => [
                'dor' => round($dor / 50 * 100, 2),
                'incapacidade' => round($incapacidade / 80 * 100, 2),
            ],
            'respostas' => ['itens' => $itens],
        ];
    }

    /**
     * Itens numerados de 1 a N, todos obrigatórios, inteiros na faixa.
     *
     * @param  array<string, mixed>  $r
     * @return array<int, int>  chave = número do item
     */
    private function itens(array $r, int $quantos, int $min, int $max, bool $simNao): array
    {
        $entrada = $r['itens'] ?? null;
        if (! is_array($entrada)) {
            throw new RespostaInvalidaException('respostas.itens', "Responda os {$quantos} itens.");
        }

        $itens = [];
        for ($n = 1; $n <= $quantos; $n++) {
            $valor = $entrada[$n] ?? $entrada[(string) $n] ?? null;
            if ($simNao && is_bool($valor)) {
                $valor = $valor ? 1 : 0;
            }
            $itens[$n] = (int) $this->numero($valor, $min, $max, "respostas.itens.{$n}", true);
        }

        $extras = array_diff(array_map('strval', array_keys($entrada)), array_map('strval', range(1, $quantos)));
        if ($extras !== []) {
            throw new RespostaInvalidaException('respostas.itens', "O questionário tem {$quantos} itens.");
        }

        return $itens;
    }

    private function numero(mixed $valor, int $min, int $max, string $campo, bool $inteiro): float
    {
        $ok = is_int($valor) || is_float($valor) || (is_string($valor) && is_numeric($valor));
        $num = $ok ? (float) $valor : null;

        if ($num === null || $num < $min || $num > $max || ($inteiro && floor($num) !== $num)) {
            $tipo = $inteiro ? 'um número inteiro' : 'um número';
            throw new RespostaInvalidaException($campo, "Informe {$tipo} de {$min} a {$max}.");
        }

        return round($num, 1);
    }
}
