<?php

namespace Modules\PontoWr2\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Modules\PontoWr2\Entities\ApuracaoDia;
use Modules\PontoWr2\Entities\Colaborador;
use Modules\PontoWr2\Entities\Intercorrencia;
use Modules\PontoWr2\Entities\Marcacao;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $businessId = session('business.id') ?: $request->user()->business_id;
        $hoje = now()->toDateString();

        $kpis = [
            'colaboradores_ativos' => Colaborador::where('business_id', $businessId)
                ->where('controla_ponto', true)
                ->whereNull('desligamento')
                ->count(),
            'presentes_agora' => Marcacao::where('business_id', $businessId)
                ->whereDate('momento', $hoje)
                ->where('tipo', Marcacao::TIPO_ENTRADA)
                ->distinct('colaborador_config_id')
                ->count('colaborador_config_id'),
            'atrasos_hoje' => ApuracaoDia::where('business_id', $businessId)
                ->where('data', $hoje)
                ->where('atraso_minutos', '>', config('pontowr2.clt.tolerancia_maxima_diaria_minutos', 10))
                ->count(),
            'faltas_hoje' => ApuracaoDia::where('business_id', $businessId)
                ->where('data', $hoje)
                ->where('falta_minutos', '>', 0)
                ->count(),
            'he_mes_minutos' => ApuracaoDia::where('business_id', $businessId)
                ->whereMonth('data', now()->month)
                ->sum(\DB::raw('he_diurna_minutos + he_noturna_minutos')),
            'aprovacoes_pendentes' => Intercorrencia::where('business_id', $businessId)
                ->pendentes()
                ->count(),
        ];

        $aprovacoes = Intercorrencia::where('business_id', $businessId)
            ->pendentes()
            ->with(['colaborador.user'])
            ->orderByDesc('prioridade')
            ->orderByDesc('created_at')
            ->limit(5)
            ->get();

        $atividadeRecente = Marcacao::where('business_id', $businessId)
            ->with(['colaborador.user', 'rep'])
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        return view('pontowr2::dashboard.index', compact('kpis', 'aprovacoes', 'atividadeRecente'));
    }
}
