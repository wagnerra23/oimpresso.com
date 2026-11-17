<?php

declare(strict_types=1);

namespace Modules\Fisio\Services\Social;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Relatório agregado para a associação (US-FISIO-062): quem é atendido, quanto e se melhora,
 * por período, para editais, emendas e convênios.
 *
 * SÓ contagem e média, nunca linha por paciente. Grupo com menos de 5 pessoas sai como "<5"
 * (e a média dele não aparece), para não identificar ninguém. Se o período inteiro tem menos de
 * 5 atendidos, só o total sai ("<5"). Municípios com menos de 5 entram juntos em "Outros
 * municípios".
 *
 * Atendido no período = paciente com ao menos um destes no período: atendimento da agenda com
 * presença (chegou, em atendimento, realizado), registro assinado no prontuário, check-in do
 * portal ou questionário respondido. Toda consulta filtra `business_id` explicitamente (ADR 0093).
 */
class RelatorioAssociacao
{
    public const MINIMO = 5;

    public const MENOS_DE_5 = '<5';

    public const PRESENTE = ['chegou', 'em_atendimento', 'realizado'];

    /** Instrumentos do relatório e o sentido da melhora. */
    public const INSTRUMENTOS = [
        'fiqr' => ['rotulo' => 'FIQR — impacto da fibromialgia (0 a 100)', 'melhora' => 'menor'],
        'eva' => ['rotulo' => 'EVA — dor (0 a 10)', 'melhora' => 'menor'],
        'pgic' => ['rotulo' => 'PGIC — impressão de mudança (1 a 7)', 'melhora' => 'maior'],
        'sentar_levantar_30s' => ['rotulo' => 'Sentar e levantar em 30 s (repetições)', 'melhora' => 'maior'],
    ];

    public const FAIXAS_ETARIAS = ['ate_17' => 'Até 17 anos', '18_29' => '18 a 29', '30_39' => '30 a 39',
        '40_49' => '40 a 49', '50_59' => '50 a 59', '60_mais' => '60 ou mais'];

    /** @return array<string, mixed> */
    public function gerar(int $biz, CarbonImmutable $de, CarbonImmutable $ate): array
    {
        $inicio = $de->startOfDay();
        $fim = $ate->endOfDay();
        $ids = $this->atendidos($biz, $inicio, $fim);
        $total = count($ids);

        $base = [
            'periodo' => ['de' => $inicio->toDateString(), 'ate' => $fim->toDateString()],
            'minimo' => self::MINIMO,
            'atendidos' => self::celula($total),
            'suprimido' => $total < self::MINIMO,
        ];
        if ($total < self::MINIMO) {
            return [...$base, 'perfil' => [], 'presenca' => null, 'adesao' => null, 'evolucao' => []];
        }

        return [
            ...$base,
            'perfil' => $this->perfil($biz, $ids, $fim),
            'presenca' => $this->presenca($biz, $inicio, $fim),
            'adesao' => $this->adesao($biz, $ids, $inicio, $fim),
            'evolucao' => $this->evolucao($biz, $ids, $fim),
        ];
    }

    /** @return list<int> ids dos pacientes atendidos no período */
    public function atendidos(int $biz, CarbonImmutable $inicio, CarbonImmutable $fim): array
    {
        $agenda = DB::table('fisio_appointments')->where('business_id', $biz)->whereIn('status', self::PRESENTE)
            ->whereBetween('starts_at', [$inicio, $fim])->select('patient_id');
        $prontuario = DB::table('fisio_records')->where('business_id', $biz)->whereNotNull('signed_at')
            ->whereBetween('signed_at', [$inicio, $fim])->select('patient_id');
        $checkins = DB::table('fisio_checkins')->where('business_id', $biz)
            ->whereBetween('date', [$inicio->toDateString(), $fim->toDateString()])->select('patient_id');
        $questionarios = DB::table('fisio_questionnaires')->where('business_id', $biz)
            ->whereBetween('answered_at', [$inicio, $fim])->select('patient_id');

        return DB::query()->fromSub($agenda->union($prontuario)->union($checkins)->union($questionarios), 'a')
            ->distinct()->pluck('patient_id')->map(fn ($id) => (int) $id)->sort()->values()->all();
    }

    /**
     * @param  list<int>  $ids
     * @return array<string, list<array{codigo: string, rotulo: string, n: int|string}>>
     */
    private function perfil(int $biz, array $ids, CarbonImmutable $fim): array
    {
        $linhas = DB::table('fisio_patients as p')
            ->join('contacts as c', fn ($j) => $j->on('c.id', '=', 'p.contact_id')->where('c.business_id', $biz))
            ->leftJoin('fisio_patient_social as s', fn ($j) => $j->on('s.patient_id', '=', 'p.id')->where('s.business_id', $biz))
            ->where('p.business_id', $biz)->whereIn('p.id', $ids)
            ->get(['c.dob', 'c.city', 'c.state', 's.sexo', 's.renda_familiar', 's.situacao_trabalho']);

        $faixas = [];
        $municipios = [];
        foreach ($linhas as $l) {
            $faixas[] = self::faixaEtaria($l->dob !== null ? (string) $l->dob : null, $fim);
            $cidade = trim((string) $l->city);
            $municipios[] = $cidade === '' ? null : $cidade.($l->state ? '/'.$l->state : '');
        }

        return [
            'faixa_etaria' => self::distribuicao($faixas, self::FAIXAS_ETARIAS),
            'sexo' => self::distribuicao($linhas->pluck('sexo')->all(), DadosSociais::OPCOES['sexo']),
            'municipio' => self::municipios($municipios),
            'renda_familiar' => self::distribuicao($linhas->pluck('renda_familiar')->all(), DadosSociais::OPCOES['renda_familiar']),
            'situacao_trabalho' => self::distribuicao($linhas->pluck('situacao_trabalho')->all(), DadosSociais::OPCOES['situacao_trabalho']),
        ];
    }

    /**
     * Atendimentos da agenda no período (sem os cancelados). Taxa de presença = presentes ÷
     * (presentes + faltas).
     *
     * @return array{agendados: int, presentes: int, faltas: int, taxa: ?int}
     */
    private function presenca(int $biz, CarbonImmutable $inicio, CarbonImmutable $fim): array
    {
        $por = DB::table('fisio_appointments')->where('business_id', $biz)->whereBetween('starts_at', [$inicio, $fim])
            ->where('status', '!=', 'cancelado')->groupBy('status')->selectRaw('status, count(*) as n')->pluck('n', 'status');
        $presentes = (int) collect(self::PRESENTE)->sum(fn ($s) => (int) ($por[$s] ?? 0));
        $faltas = (int) ($por['faltou'] ?? 0);

        return [
            'agendados' => (int) $por->sum(),
            'presentes' => $presentes,
            'faltas' => $faltas,
            'taxa' => ($presentes + $faltas) > 0 ? (int) round($presentes * 100 / ($presentes + $faltas)) : null,
        ];
    }

    /**
     * Adesão ao programa em casa: dias com check-in com ao menos um exercício feito ÷ dias
     * prescritos no período (meta semanal × semanas), teto 100%, média entre os atendidos que
     * têm programa. O mesmo critério do painel (AdesaoCalculator), esticado ao período.
     *
     * @param  list<int>  $ids
     * @return array{n: int|string, media: ?int}
     */
    private function adesao(int $biz, array $ids, CarbonImmutable $inicio, CarbonImmutable $fim): array
    {
        $comPrograma = DB::table('fisio_programs')->where('business_id', $biz)->whereIn('patient_id', $ids)
            ->distinct()->pluck('patient_id')->map(fn ($i) => (int) $i)->all();
        $meta = DB::table('fisio_patients')->where('business_id', $biz)->whereIn('id', $comPrograma)->pluck('sessions_per_week', 'id');
        $dias = DB::table('fisio_checkins')->where('business_id', $biz)->whereIn('patient_id', $comPrograma)
            ->whereBetween('date', [$inicio->toDateString(), $fim->toDateString()])
            ->get(['patient_id', 'date', 'done_item_ids'])
            ->filter(fn ($c) => count((array) json_decode((string) $c->done_item_ids, true)) >= 1)
            ->groupBy('patient_id')->map(fn ($cs) => $cs->pluck('date')->unique()->count());

        $semanas = max(1, (int) round(((int) $inicio->diffInDays($fim) + 1) / 7));
        $pcts = [];
        foreach ($comPrograma as $id) {
            $prescritos = max(1, (int) ($meta[$id] ?? 1)) * $semanas;
            $pcts[] = min(100, (int) round(((int) ($dias[$id] ?? 0)) * 100 / $prescritos));
        }

        return count($pcts) < self::MINIMO
            ? ['n' => self::celula(count($pcts)), 'media' => null]
            : ['n' => count($pcts), 'media' => (int) round(array_sum($pcts) / count($pcts))];
    }

    /**
     * Evolução média entre a primeira e a última aplicação de cada instrumento, até o fim do
     * período, só de quem respondeu ao menos duas vezes. Com menos de 5 pessoas, sem médias.
     *
     * @param  list<int>  $ids
     * @return list<array<string, mixed>>
     */
    private function evolucao(int $biz, array $ids, CarbonImmutable $fim): array
    {
        $saida = [];
        foreach (self::INSTRUMENTOS as $codigo => $meta) {
            $grupos = DB::table('fisio_questionnaires')->where('business_id', $biz)->whereIn('patient_id', $ids)
                ->where('instrument', $codigo)->whereNotNull('score')->where('answered_at', '<=', $fim)
                ->orderBy('answered_at')->orderBy('id')->get(['patient_id', 'score'])->groupBy('patient_id')
                ->filter(fn ($rs) => $rs->count() >= 2);
            $n = $grupos->count();
            $linha = ['codigo' => $codigo, 'rotulo' => $meta['rotulo'], 'melhora' => $meta['melhora'], 'n' => self::celula($n),
                'primeira' => null, 'ultima' => null, 'diferenca' => null];
            if ($n >= self::MINIMO) {
                $primeira = (float) $grupos->avg(fn ($rs) => (float) $rs->first()->score);
                $ultima = (float) $grupos->avg(fn ($rs) => (float) $rs->last()->score);
                $linha = [...$linha, 'primeira' => round($primeira, 1), 'ultima' => round($ultima, 1), 'diferenca' => round($ultima - $primeira, 1)];
            }
            $saida[] = $linha;
        }

        return $saida;
    }

    public static function celula(int $n): int|string
    {
        return $n > 0 && $n < self::MINIMO ? self::MENOS_DE_5 : $n;
    }

    public static function faixaEtaria(?string $nascimento, CarbonImmutable $em): ?string
    {
        if ($nascimento === null || $nascimento === '') {
            return null;
        }
        $idade = (int) CarbonImmutable::parse($nascimento)->diffInYears($em);

        return match (true) {
            $idade < 18 => 'ate_17', $idade < 30 => '18_29', $idade < 40 => '30_39',
            $idade < 50 => '40_49', $idade < 60 => '50_59', default => '60_mais',
        };
    }

    /**
     * Contagem por código, na ordem da lista, com "Não informado" no fim. Contagem de 1 a 4 sai "<5".
     *
     * @param  list<?string>  $valores
     * @param  array<string, string>  $rotulos
     * @return list<array{codigo: string, rotulo: string, n: int|string}>
     */
    public static function distribuicao(array $valores, array $rotulos): array
    {
        $contagem = array_count_values(array_map(fn ($v) => $v === null || $v === '' ? '_nao_informado' : (string) $v, $valores));
        $saida = [];
        foreach ([...$rotulos, '_nao_informado' => 'Não informado'] as $codigo => $rotulo) {
            $saida[] = ['codigo' => (string) $codigo, 'rotulo' => $rotulo, 'n' => self::celula((int) ($contagem[$codigo] ?? 0))];
        }

        return $saida;
    }

    /**
     * Municípios com 5 ou mais aparecem pelo nome; os menores se juntam em "Outros municípios".
     *
     * @param  list<?string>  $valores
     * @return list<array{codigo: string, rotulo: string, n: int|string}>
     */
    public static function municipios(array $valores): array
    {
        $contagem = array_count_values(array_map(fn ($v) => $v ?? '_nao_informado', $valores));
        arsort($contagem);
        $saida = [];
        $outros = 0;
        foreach ($contagem as $nome => $n) {
            if ($nome === '_nao_informado') {
                continue;
            }
            if ($n >= self::MINIMO) {
                $saida[] = ['codigo' => (string) $nome, 'rotulo' => (string) $nome, 'n' => $n];
            } else {
                $outros += $n;
            }
        }
        $saida[] = ['codigo' => '_outros', 'rotulo' => 'Outros municípios', 'n' => self::celula($outros)];
        $saida[] = ['codigo' => '_nao_informado', 'rotulo' => 'Não informado', 'n' => self::celula((int) ($contagem['_nao_informado'] ?? 0))];

        return $saida;
    }
}
