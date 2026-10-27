<?php

namespace Modules\Ponto\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Modules\Ponto\Entities\ApuracaoDia;
use Modules\Ponto\Entities\BancoHorasSaldo;
use Modules\Ponto\Entities\Colaborador;

/**
 * Exportação do ponto para a folha e a contabilidade — US-PONTO-019.
 *
 * Três arquivos por competência, um colaborador por linha, somando o que o `ApuracaoService`
 * gravou em `ponto_apuracao_dia`. Não reapura e não calcula valor em dinheiro: entrega horas,
 * para a folha da contabilidade importar.
 *
 *   - he          HE 50% diurna · HE noturna · HE de feriado (100%) · adicional noturno · DSR
 *   - atrasos     atrasos · saídas antecipadas · faltas (dias e horas)
 *   - banco-horas crédito e débito do mês · saldo atual
 *
 * Formato: CSV com `;` (padrão do Excel pt-BR), UTF-8 com BOM, horas em `hh:mm`. Colaborador
 * entra se tem dia apurado na competência; sem apuração, o arquivo sai só com o cabeçalho.
 *
 * Tier 0 (ADR 0093): `business_id` explícito em toda consulta, além do global scope.
 */
class ExportacaoFolhaService
{
    public const TIPOS = ['he', 'atrasos', 'banco-horas'];

    /** Dados de dia que entram na soma, por tipo. */
    private const COLUNAS = [
        'he' => [
            'HE 50% diurna'      => 'he_diurna_minutos',
            'HE noturna'         => 'he_noturna_minutos',
            'HE feriado (100%)'  => 'he_feriado_minutos',
            'Adicional noturno'  => 'adicional_noturno_minutos',
            'DSR sobre HE'       => 'dsr_repercussao_minutos',
        ],
        'atrasos' => [
            'Atrasos'            => 'atraso_minutos',
            'Saídas antecipadas' => 'saida_antecipada_minutos',
            'Faltas (horas)'     => 'falta_minutos',
        ],
        'banco-horas' => [
            'Crédito no mês'     => 'banco_horas_credito_minutos',
            'Débito no mês'      => 'banco_horas_debito_minutos',
        ],
    ];

    /**
     * @return array{nome:string, conteudo:string}
     */
    public function gerar(string $tipo, int $businessId, string $mes): array
    {
        if (! in_array($tipo, self::TIPOS, true)) {
            throw new \InvalidArgumentException("Exportação '{$tipo}' não existe.");
        }

        $inicio = Carbon::createFromFormat('Y-m-d', $mes . '-01')->startOfDay();
        $linhas = $this->linhas($tipo, $businessId, $inicio, $inicio->copy()->endOfMonth());

        $csv = "\u{FEFF}";
        foreach ($linhas as $linha) {
            $csv .= implode(';', array_map([$this, 'celula'], $linha)) . "\r\n";
        }

        return ['nome' => "ponto-{$tipo}-{$mes}.csv", 'conteudo' => $csv];
    }

    /**
     * Cabeçalho + uma linha por colaborador com dia apurado, em ordem de matrícula.
     *
     * @return array<int, array<int, string>>
     */
    public function linhas(string $tipo, int $businessId, Carbon $inicio, Carbon $fim): array
    {
        $colunas = self::COLUNAS[$tipo];

        $cabecalho = array_merge(['Matrícula', 'Colaborador'], array_keys($colunas));
        if ($tipo === 'atrasos') {
            $cabecalho[] = 'Faltas (dias)';
        }
        if ($tipo === 'banco-horas') {
            $cabecalho[] = 'Saldo atual';
        }
        $cabecalho[] = 'Dias apurados';

        $dias = ApuracaoDia::where('business_id', $businessId)
            ->whereBetween('data', [$inicio->toDateString(), $fim->toDateString()])
            ->get();

        /** @var Collection<int, Colaborador> $colabs */
        $colabs = Colaborador::where('business_id', $businessId)
            ->whereIn('id', $dias->pluck('colaborador_config_id')->unique()->all())
            ->with('user:id,first_name,last_name')
            ->get()
            ->sortBy(fn (Colaborador $c) => [(string) $c->matricula, $c->id])
            ->values();

        $saldos = $tipo === 'banco-horas'
            ? BancoHorasSaldo::where('business_id', $businessId)
                ->whereIn('colaborador_config_id', $colabs->pluck('id')->all())
                ->pluck('saldo_minutos', 'colaborador_config_id')
            : collect();

        $porColab = $dias->groupBy('colaborador_config_id');

        $linhas = [$cabecalho];
        foreach ($colabs as $c) {
            $doColab = $porColab->get($c->id, collect());
            $linha = [
                (string) ($c->matricula ?? ''),
                trim(optional($c->user)->first_name . ' ' . optional($c->user)->last_name),
            ];
            foreach ($colunas as $campo) {
                $linha[] = $this->hm((int) $doColab->sum($campo));
            }
            if ($tipo === 'atrasos') {
                $linha[] = (string) $doColab->filter(fn (ApuracaoDia $d) => (int) $d->prevista_carga_minutos > 0
                    && (int) $d->realizada_trabalhada_minutos === 0
                    && (int) $d->falta_minutos >= (int) $d->prevista_carga_minutos)->count();
            }
            if ($tipo === 'banco-horas') {
                $linha[] = $this->hm((int) ($saldos[$c->id] ?? 0));
            }
            $linha[] = (string) $doColab->count();
            $linhas[] = $linha;
        }

        return $linhas;
    }

    /** Minutos em `hh:mm`; negativo leva o sinal (saldo de banco de horas devedor). */
    private function hm(int $minutos): string
    {
        $sinal = $minutos < 0 ? '-' : '';
        $m = abs($minutos);

        return sprintf('%s%02d:%02d', $sinal, intdiv($m, 60), $m % 60);
    }

    /** Aspas só quando precisa; neutraliza fórmula no início (injeção de CSV). */
    private function celula(string $v): string
    {
        if ($v !== '' && in_array($v[0], ['=', '+', '@'], true)) {
            $v = "'" . $v;
        }
        if (strpbrk($v, ";\"\r\n") !== false) {
            $v = '"' . str_replace('"', '""', $v) . '"';
        }

        return $v;
    }
}
