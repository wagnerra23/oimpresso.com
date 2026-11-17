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
            'acr2016' => $this->acr2016($respostas),
            'fiqr' => $this->fiqr($respostas),
            'pgic' => $this->categoria($respostas, 1, 7),
            'sentar_levantar_30s' => $this->categoria($respostas, 0, 60),
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
     * Valor inteiro único: PGIC (categoria 1–7) e sentar e levantar em 30 s (nº de repetições).
     *
     * @param  array<string, mixed>  $r
     */
    private function categoria(array $r, int $min, int $max): array
    {
        $valor = $this->numero($r['valor'] ?? null, $min, $max, 'respostas.valor', true);

        return ['escore' => $valor, 'detalhe' => [], 'respostas' => ['valor' => (int) $valor]];
    }

    /**
     * FIQR (Bennett et al. 2009; FIQR-Br, Lupi et al. 2017): 21 itens de 0 a 10.
     * Função = soma 1–9 ÷ 3; impacto geral = soma 10–11; sintomas = soma 12–21 ÷ 2; total = soma.
     *
     * @param  array<string, mixed>  $r
     */
    private function fiqr(array $r): array
    {
        $itens = $this->itens($r, 21, 0, 10, false);
        $funcao = array_sum(array_slice($itens, 0, 9, true)) / 3;
        $impacto = (float) array_sum(array_slice($itens, 9, 2, true));
        $sintomas = array_sum(array_slice($itens, 11, 10, true)) / 2;

        return [
            'escore' => round($funcao + $impacto + $sintomas, 2),
            'detalhe' => ['funcao' => round($funcao, 2), 'impacto' => round($impacto, 2), 'sintomas' => round($sintomas, 2)],
            'respostas' => ['itens' => $itens],
        ];
    }

    /**
     * Critérios ACR 2016 (Wolfe et al. 2016). Itens 1–19 = áreas do WPI (0/1); 20–22 = fadiga, sono
     * não reparador e sintomas cognitivos (0–3); 23–25 = cefaleia, dor ou cólica abdominal baixa e
     * depressão nos últimos 6 meses (0/1); 26 = sintomas há 3 meses ou mais (0/1).
     *
     * O resultado dos critérios é INFORMAÇÃO para o profissional, nunca diagnóstico automático
     * (ADR 0437 §5): a publicação diz que o diagnóstico é clínico e vale com outros diagnósticos.
     *
     * @param  array<string, mixed>  $r
     */
    private function acr2016(array $r): array
    {
        $entrada = $r['itens'] ?? null;
        if (! is_array($entrada)) {
            throw new RespostaInvalidaException('respostas.itens', 'Responda os 26 itens.');
        }

        $itens = [];
        for ($n = 1; $n <= 26; $n++) {
            $valor = $entrada[$n] ?? $entrada[(string) $n] ?? null;
            if (is_bool($valor)) {
                $valor = $valor ? 1 : 0;
            }
            $max = ($n >= 20 && $n <= 22) ? 3 : 1;
            $itens[$n] = (int) $this->numero($valor, 0, $max, "respostas.itens.{$n}", true);
        }
        $extras = array_diff(array_map('strval', array_keys($entrada)), array_map('strval', range(1, 26)));
        if ($extras !== []) {
            throw new RespostaInvalidaException('respostas.itens', 'O questionário tem 26 itens.');
        }

        $wpi = 0;
        $regioes = [];
        foreach (CatalogoQuestionarios::ACR2016_AREAS as $n => $a) {
            if ($itens[$n] === 1) {
                $wpi++;
                if (! in_array($a['area'], CatalogoQuestionarios::ACR2016_FORA_DA_DOR_GENERALIZADA, true)) {
                    $regioes[$a['regiao']] = true;
                }
            }
        }
        $sss = $itens[20] + $itens[21] + $itens[22] + $itens[23] + $itens[24] + $itens[25];

        $limiares = ($wpi >= 7 && $sss >= 5) || ($wpi >= 4 && $wpi <= 6 && $sss >= 9);
        $generalizada = count($regioes) >= 4;
        $duracao = $itens[26] === 1;

        return [
            'escore' => (float) ($wpi + $sss),
            'detalhe' => [
                'wpi' => (float) $wpi,
                'sss' => (float) $sss,
                'regioes_com_dor' => (float) count($regioes),
                'criterio_wpi_sss' => $limiares ? 1.0 : 0.0,
                'dor_generalizada' => $generalizada ? 1.0 : 0.0,
                'sintomas_3_meses' => $duracao ? 1.0 : 0.0,
                'criterios_acr2016_atendidos' => ($limiares && $generalizada && $duracao) ? 1.0 : 0.0,
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
