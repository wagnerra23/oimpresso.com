<?php

namespace Modules\Ponto\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Modules\Essentials\Entities\EssentialsLeave;
use Modules\Ponto\Entities\ApuracaoDia;
use Modules\Ponto\Entities\Colaborador;

/**
 * Férias da CLT por colaborador — US-PONTO-018. Só leitura, sem valor em dinheiro.
 *
 * - Período aquisitivo: cada 12 meses a partir da admissão (CLT art. 130).
 * - Período concessivo: os 12 meses seguintes ao fim do aquisitivo (art. 134). Passou dele com
 *   saldo, as férias estão VENCIDAS e o pagamento é em dobro (art. 137).
 * - Dias de direito pela tabela do art. 130, contando as FALTAS INJUSTIFICADAS do aquisitivo:
 *   dia previsto sem nenhuma batida, com falta cheia gravada pela apuração. Licença aprovada e
 *   intercorrência aprovada já zeram a falta na apuração, então não entram.
 * - Dias gozados: licenças aprovadas do HRM cujo tipo tem `ferias_clt = true`, abatidas do
 *   período mais antigo que já estava completo quando a licença começou.
 *
 * Fora daqui, de propósito: 1/3 constitucional e abono pecuniário em dinheiro (são valor e vão
 * com a folha) e a perda do direito do art. 133 (licença remunerada > 30 dias, afastamento > 6
 * meses), que pede dado de afastamento que o HRM ainda não tem.
 *
 * Custo: duas consultas por empresa, qualquer que seja o número de colaboradores — a
 * Conformidade (e o contador das abas do Ponto, que a lê) chama isto a cada tela.
 *
 * Tier 0 (ADR 0093): toda consulta leva o `business_id`, além do global scope.
 */
class FeriasService
{
    public const SITUACAO_EM_AQUISICAO = 'em_aquisicao';
    public const SITUACAO_A_GOZAR      = 'a_gozar';
    public const SITUACAO_VENCIDO      = 'vencido';
    public const SITUACAO_QUITADO      = 'quitado';

    /**
     * Dias de férias pela quantidade de faltas injustificadas no aquisitivo (CLT art. 130).
     *
     * @return int 30, 24, 18, 12 ou 0
     */
    public static function diasDeDireito(int $faltas): int
    {
        if ($faltas <= 5) {
            return 30;
        }
        if ($faltas <= 14) {
            return 24;
        }
        if ($faltas <= 23) {
            return 18;
        }
        if ($faltas <= 32) {
            return 12;
        }

        return 0;
    }

    /**
     * Períodos aquisitivos do colaborador até `$hoje` (ou até o desligamento), do mais antigo
     * ao mais recente. Colaborador sem admissão devolve lista vazia: sem a data não há período.
     *
     * @return array<int, array{
     *   numero:int, inicio:string, fim:string, concessivo_fim:string,
     *   faltas_injustificadas:int, dias_apurados:int, dias_direito:int,
     *   dias_gozados:int, saldo:int, situacao:string, dias_para_vencer:?int
     * }>
     */
    public function periodos(Colaborador $c, Carbon $hoje): array
    {
        if (empty($c->admissao) || empty($c->business_id)) {
            return [];
        }

        return $this->periodosEmLote((int) $c->business_id, collect([$c]), $hoje)[(int) $c->id] ?? [];
    }

    /**
     * Mesmo cálculo de `periodos()` para vários colaboradores do MESMO empregador, com duas
     * consultas no total. Colaborador de outro empregador na coleção é ignorado.
     *
     * @param  Collection<int, Colaborador>  $colabs
     * @return array<int, array<int, array<string, mixed>>>  colaborador_id => períodos
     */
    public function periodosEmLote(int $businessId, Collection $colabs, Carbon $hoje): array
    {
        $hoje   = $hoje->copy()->startOfDay();
        $colabs = $colabs->filter(fn (Colaborador $c) => (int) $c->business_id === $businessId && ! empty($c->admissao));
        if ($colabs->isEmpty()) {
            return [];
        }

        $apuracoes = $this->apuracoes($businessId, $colabs, $hoje);
        $licencas  = $this->licencas($businessId, $colabs);

        $saida = [];
        foreach ($colabs as $c) {
            $saida[(int) $c->id] = $this->calcular(
                $c,
                $hoje,
                $apuracoes[(int) $c->id] ?? [],
                $licencas[(int) $c->user_id] ?? []
            );
        }

        return $saida;
    }

    /**
     * Colaboradores ativos do empregador com férias vencidas ou que vencem em até
     * `$antecedencia` dias. Um item por período nessa situação.
     *
     * @return array<int, array{colaborador_id:int, matricula:?string, periodo:array}>
     */
    public function alertas(int $businessId, Carbon $hoje, int $antecedencia = 60): array
    {
        $colabs = Colaborador::where('business_id', $businessId)
            ->where('controla_ponto', true)
            ->whereNull('desligamento')
            ->whereNotNull('admissao')
            ->orderBy('id')
            ->get();

        $porColab = $this->periodosEmLote($businessId, $colabs, $hoje);

        $alertas = [];
        foreach ($colabs as $c) {
            foreach ($porColab[(int) $c->id] ?? [] as $p) {
                $vencido = $p['situacao'] === self::SITUACAO_VENCIDO;
                $vence   = $p['situacao'] === self::SITUACAO_A_GOZAR
                    && $p['dias_para_vencer'] !== null
                    && $p['dias_para_vencer'] <= $antecedencia;

                if ($vencido || $vence) {
                    $alertas[] = [
                        'colaborador_id' => (int) $c->id,
                        'matricula'      => $c->matricula,
                        'periodo'        => $p,
                    ];
                }
            }
        }

        return $alertas;
    }

    /**
     * @param  array<int, array{0:string, 1:bool}>  $dias  [data, é falta injustificada]
     * @param  array<int, array{0:string, 1:string}>  $licencas  [início, fim], em ordem
     */
    private function calcular(Colaborador $c, Carbon $hoje, array $dias, array $licencas): array
    {
        $limite = $hoje->copy();
        if (! empty($c->desligamento) && Carbon::parse($c->desligamento)->lt($limite)) {
            $limite = Carbon::parse($c->desligamento)->startOfDay();
        }

        $periodos = [];
        $inicio   = Carbon::parse($c->admissao)->startOfDay();
        $n        = 1;
        while ($inicio->lte($limite)) {
            $fim      = $inicio->copy()->addYear()->subDay();
            $contaAte = ($fim->lt($hoje) ? $fim : $hoje)->toDateString();
            $de       = $inicio->toDateString();

            $apurados = 0;
            $faltas   = 0;
            foreach ($dias as [$data, $falta]) {
                if ($data >= $de && $data <= $contaAte) {
                    $apurados++;
                    $faltas += $falta ? 1 : 0;
                }
            }

            $periodos[] = [
                'numero'                => $n,
                'inicio'                => $de,
                'fim'                   => $fim->toDateString(),
                'concessivo_fim'        => $fim->copy()->addYear()->toDateString(),
                'faltas_injustificadas' => $faltas,
                'dias_apurados'         => $apurados,
                'dias_direito'          => self::diasDeDireito($faltas),
                'dias_gozados'          => 0,
                'saldo'                 => 0,
                'situacao'              => self::SITUACAO_EM_AQUISICAO,
                'dias_para_vencer'      => null,
            ];

            $inicio = $fim->copy()->addDay();
            $n++;
        }

        $this->abaterGozadas($periodos, $licencas);

        foreach ($periodos as &$p) {
            $p['saldo'] = max(0, $p['dias_direito'] - $p['dias_gozados']);
            $fim        = Carbon::parse($p['fim']);
            $conc       = Carbon::parse($p['concessivo_fim']);

            if ($hoje->lte($fim)) {
                $p['situacao'] = self::SITUACAO_EM_AQUISICAO;
            } elseif ($p['saldo'] === 0) {
                $p['situacao'] = self::SITUACAO_QUITADO;
            } elseif ($hoje->gt($conc)) {
                $p['situacao'] = self::SITUACAO_VENCIDO;
            } else {
                $p['situacao']         = self::SITUACAO_A_GOZAR;
                $p['dias_para_vencer'] = (int) $hoje->diffInDays($conc);
            }
        }
        unset($p);

        return $periodos;
    }

    /**
     * Dias apurados de cada colaborador, da admissão mais antiga até `$hoje`.
     *
     * @return array<int, array<int, array{0:string, 1:bool}>>
     */
    private function apuracoes(int $businessId, Collection $colabs, Carbon $hoje): array
    {
        $desde = $colabs->map(fn (Colaborador $c) => Carbon::parse($c->admissao)->toDateString())->min();

        $porColab = [];
        ApuracaoDia::where('business_id', $businessId)
            ->whereIn('colaborador_config_id', $colabs->pluck('id')->all())
            ->whereBetween('data', [$desde, $hoje->toDateString()])
            ->get(['colaborador_config_id', 'data', 'prevista_carga_minutos', 'realizada_trabalhada_minutos', 'falta_minutos'])
            ->each(function (ApuracaoDia $a) use (&$porColab) {
                $falta = (int) $a->prevista_carga_minutos > 0
                    && (int) $a->realizada_trabalhada_minutos === 0
                    && (int) $a->falta_minutos >= (int) $a->prevista_carga_minutos;
                $porColab[(int) $a->colaborador_config_id][] = [$a->data->toDateString(), $falta];
            });

        return $porColab;
    }

    /**
     * Licenças de férias aprovadas por usuário, em ordem de início.
     *
     * @return array<int, array<int, array{0:string, 1:string}>>
     */
    private function licencas(int $businessId, Collection $colabs): array
    {
        $users = $colabs->pluck('user_id')->filter()->map(fn ($id) => (int) $id)->unique()->values()->all();
        if ($users === []) {
            return [];
        }

        $porUser = [];
        EssentialsLeave::where('essentials_leaves.business_id', $businessId)
            ->whereIn('essentials_leaves.user_id', $users)
            ->where('essentials_leaves.status', 'approved')
            ->join('essentials_leave_types as t', function ($j) use ($businessId) {
                $j->on('t.id', '=', 'essentials_leaves.essentials_leave_type_id')
                    ->where('t.business_id', '=', $businessId);
            })
            ->where('t.ferias_clt', true)
            ->orderBy('essentials_leaves.start_date')
            ->orderBy('essentials_leaves.id')
            ->get(['essentials_leaves.user_id', 'essentials_leaves.start_date', 'essentials_leaves.end_date'])
            ->each(function ($l) use (&$porUser) {
                $porUser[(int) $l->user_id][] = [(string) $l->start_date, (string) $l->end_date];
            });

        return $porUser;
    }

    /**
     * Abate cada licença de férias do período mais antigo que já estava completo no dia em que
     * ela começou e ainda tem saldo. O que não couber em período nenhum (férias antecipadas)
     * não é abatido: fica para o RH ver, não some.
     *
     * @param  array<int, array{0:string, 1:string}>  $licencas
     */
    private function abaterGozadas(array &$periodos, array $licencas): void
    {
        foreach ($licencas as [$inicioLic, $fimLic]) {
            $ini  = Carbon::parse($inicioLic)->startOfDay();
            $dias = (int) $ini->diffInDays(Carbon::parse($fimLic)->startOfDay()) + 1;

            foreach ($periodos as &$p) {
                if ($dias <= 0) {
                    break;
                }
                if (Carbon::parse($p['fim'])->gte($ini)) {
                    continue; // período ainda não completo quando a licença começou
                }
                $livre = $p['dias_direito'] - $p['dias_gozados'];
                if ($livre <= 0) {
                    continue;
                }
                $usa                = min($livre, $dias);
                $p['dias_gozados'] += $usa;
                $dias              -= $usa;
            }
            unset($p);
        }
    }
}
