<?php

namespace Modules\Copiloto\Services;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Serviço de agregação de custos de IA do Copiloto (US-COPI-070).
 *
 * Lê tokens das mensagens (`copiloto_mensagens.tokens_in/tokens_out`) via join
 * com `copiloto_conversas` (pra scope por business) e calcula:
 *  - KPIs do período (R$, mensagens, tokens, usuários ativos)
 *  - Breakdown por usuário (tabela)
 *  - Série diária (gráfico de área)
 *
 * Cálculo R$:
 *   custo_brl = (tokens_in × pricing.input + tokens_out × pricing.output) × cambio
 *
 * Pricing e câmbio vêm de `config/copiloto.php`. Modelo default é
 * `config('copiloto.ai.pricing_default_model')` enquanto não persistirmos
 * `modelo` em `copiloto_mensagens`.
 */
class CustosService
{
    /**
     * Agrega tudo que a tela US-COPI-070 precisa.
     *
     * @return array{
     *   kpis: array{custo_brl: float, mensagens: int, tokens: int, usuarios_ativos: int},
     *   por_usuario: array<int, array{user_id: int, nome: string, conversas: int, mensagens: int, tokens: int, custo_brl: float}>,
     *   serie_diaria: array<int, array{data: string, custo_brl: float, tokens: int, mensagens: int}>,
     *   periodo: array{inicio: string, fim: string, label: string},
     * }
     */
    public function painel(int $businessId, CarbonInterface $inicio, CarbonInterface $fim): array
    {
        $iniSql = $inicio->copy()->startOfDay()->toDateTimeString();
        $fimSql = $fim->copy()->endOfDay()->toDateTimeString();

        $base = DB::table('copiloto_mensagens as m')
            ->join('copiloto_conversas as c', 'c.id', '=', 'm.conversa_id')
            ->where('c.business_id', $businessId)
            ->whereBetween('m.created_at', [$iniSql, $fimSql]);

        $totais = (clone $base)
            ->selectRaw('
                COUNT(*) AS mensagens,
                COALESCE(SUM(m.tokens_in), 0)  AS tokens_in,
                COALESCE(SUM(m.tokens_out), 0) AS tokens_out,
                COUNT(DISTINCT c.user_id)      AS usuarios_ativos
            ')
            ->first();

        $tokensIn  = (int) ($totais->tokens_in ?? 0);
        $tokensOut = (int) ($totais->tokens_out ?? 0);

        $kpis = [
            'custo_brl'       => $this->calcularCustoBrl($tokensIn, $tokensOut),
            'mensagens'       => (int) ($totais->mensagens ?? 0),
            'tokens'          => $tokensIn + $tokensOut,
            'usuarios_ativos' => (int) ($totais->usuarios_ativos ?? 0),
        ];

        $porUsuario = (clone $base)
            ->leftJoin('users as u', 'u.id', '=', 'c.user_id')
            ->selectRaw("
                c.user_id,
                COALESCE(NULLIF(TRIM(CONCAT_WS(' ', u.first_name, u.last_name)), ''), u.username, CONCAT('#', c.user_id)) AS nome,
                COUNT(DISTINCT c.id)            AS conversas,
                COUNT(*)                        AS mensagens,
                COALESCE(SUM(m.tokens_in), 0)   AS tokens_in,
                COALESCE(SUM(m.tokens_out), 0)  AS tokens_out
            ")
            ->groupBy('c.user_id', 'u.first_name', 'u.last_name', 'u.username')
            ->orderByDesc(DB::raw('COALESCE(SUM(m.tokens_in), 0) + COALESCE(SUM(m.tokens_out), 0)'))
            ->get()
            ->map(function ($r) {
                $tIn  = (int) $r->tokens_in;
                $tOut = (int) $r->tokens_out;

                return [
                    'user_id'   => (int) $r->user_id,
                    'nome'      => (string) $r->nome,
                    'conversas' => (int) $r->conversas,
                    'mensagens' => (int) $r->mensagens,
                    'tokens'    => $tIn + $tOut,
                    'custo_brl' => $this->calcularCustoBrl($tIn, $tOut),
                ];
            })
            ->values()
            ->all();

        $serieDb = (clone $base)
            ->selectRaw('
                DATE(m.created_at)             AS data,
                COUNT(*)                       AS mensagens,
                COALESCE(SUM(m.tokens_in), 0)  AS tokens_in,
                COALESCE(SUM(m.tokens_out), 0) AS tokens_out
            ')
            ->groupBy(DB::raw('DATE(m.created_at)'))
            ->orderBy(DB::raw('DATE(m.created_at)'))
            ->get()
            ->keyBy('data');

        $serieDiaria = $this->preencherSerie($inicio, $fim, $serieDb);

        return [
            'kpis'         => $kpis,
            'por_usuario'  => $porUsuario,
            'serie_diaria' => $serieDiaria,
            'periodo'      => [
                'inicio' => $inicio->toDateString(),
                'fim'    => $fim->toDateString(),
                'label'  => $this->labelPeriodo($inicio, $fim),
            ],
        ];
    }

    /**
     * Calcula R$ de um par (tokens_in, tokens_out) com base na config canônica.
     * Pública pra ser chamada do test e do middleware de orçamento (US-COPI-072).
     */
    public function calcularCustoBrl(int $tokensIn, int $tokensOut, ?string $modelo = null): float
    {
        $modelo ??= config('copiloto.ai.pricing_default_model', 'gpt-4o-mini');

        $pricing = config("copiloto.ai.pricing.{$modelo}")
            ?? config('copiloto.ai.pricing.gpt-4o-mini')
            ?? ['input' => 0.0, 'output' => 0.0];

        $cambio = (float) config('copiloto.ai.cambio_brl_usd', 5.50);

        $custoUsd = ($tokensIn  / 1000) * (float) $pricing['input']
                  + ($tokensOut / 1000) * (float) $pricing['output'];

        return round($custoUsd * $cambio, 2);
    }

    /**
     * Resolve presets de período pra startOfDay/endOfDay.
     *
     * @return array{inicio: CarbonInterface, fim: CarbonInterface}
     */
    public function resolverPeriodo(string $preset, ?string $de = null, ?string $ate = null): array
    {
        return match ($preset) {
            'mes_anterior' => [
                'inicio' => now()->subMonthNoOverflow()->startOfMonth(),
                'fim'    => now()->subMonthNoOverflow()->endOfMonth(),
            ],
            '90d' => [
                'inicio' => now()->subDays(89)->startOfDay(),
                'fim'    => now()->endOfDay(),
            ],
            'custom' => [
                'inicio' => $de  ? Carbon::parse($de)->startOfDay()  : now()->startOfMonth(),
                'fim'    => $ate ? Carbon::parse($ate)->endOfDay()   : now()->endOfDay(),
            ],
            default => [
                'inicio' => now()->startOfMonth(),
                'fim'    => now()->endOfDay(),
            ],
        };
    }

    private function labelPeriodo(CarbonInterface $inicio, CarbonInterface $fim): string
    {
        return $inicio->format('d/m/Y') . ' — ' . $fim->format('d/m/Y');
    }

    /**
     * Preenche dias sem mensagem com zero pra série virar contínua.
     *
     * @param  Collection<string, object>  $serieDb
     * @return array<int, array{data: string, custo_brl: float, tokens: int, mensagens: int}>
     */
    private function preencherSerie(CarbonInterface $inicio, CarbonInterface $fim, Collection $serieDb): array
    {
        $serie = [];
        $cursor = $inicio->copy()->startOfDay();
        $stop = $fim->copy()->startOfDay();

        while ($cursor->lte($stop)) {
            $key = $cursor->toDateString();
            $row = $serieDb->get($key);

            $tIn  = (int) ($row->tokens_in  ?? 0);
            $tOut = (int) ($row->tokens_out ?? 0);

            $serie[] = [
                'data'      => $key,
                'mensagens' => (int) ($row->mensagens ?? 0),
                'tokens'    => $tIn + $tOut,
                'custo_brl' => $this->calcularCustoBrl($tIn, $tOut),
            ];

            $cursor->addDay();
        }

        return $serie;
    }
}
