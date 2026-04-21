<?php

namespace Modules\PontoWr2\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Modules\PontoWr2\Entities\ApuracaoDia;
use Modules\PontoWr2\Entities\Colaborador;
use Modules\PontoWr2\Entities\Marcacao;
use Modules\PontoWr2\Services\ReportService;

class EspelhoController extends Controller
{
    protected $reports;

    public function __construct(ReportService $reports)
    {
        $this->reports = $reports;
    }

    public function index(Request $request): View
    {
        $businessId = session('business.id') ?: $request->user()->business_id;
        $mes = $request->input('mes', now()->format('Y-m'));

        $colaboradores = Colaborador::where('business_id', $businessId)
            ->where('controla_ponto', true)
            ->whereNull('desligamento')
            ->with('user')
            ->orderBy('matricula')
            ->paginate(25);

        return view('pontowr2::espelho.index', compact('colaboradores', 'mes'));
    }

    public function show(Request $request, $colaboradorId): View
    {
        $businessId = session('business.id') ?: $request->user()->business_id;
        $mes = $request->input('mes', now()->format('Y-m'));
        list($ano, $mesNum) = explode('-', $mes);

        $colaborador = Colaborador::where('business_id', $businessId)
            ->with(['user', 'escalaAtual'])
            ->findOrFail($colaboradorId);

        $apuracoes = ApuracaoDia::where('colaborador_config_id', $colaboradorId)
            ->whereYear('data', $ano)
            ->whereMonth('data', $mesNum)
            ->orderBy('data')
            ->get();

        $marcacoes = Marcacao::where('colaborador_config_id', $colaboradorId)
            ->whereYear('momento', $ano)
            ->whereMonth('momento', $mesNum)
            ->whereNotIn('origem', [Marcacao::ORIGEM_ANULACAO])
            ->orderBy('momento')
            ->get()
            ->groupBy(function ($m) { return $m->momento->toDateString(); });

        return view('pontowr2::espelho.show', compact('colaborador', 'apuracoes', 'marcacoes', 'mes'));
    }

    public function imprimir(Request $request, $colaboradorId)
    {
        $businessId = session('business.id') ?: $request->user()->business_id;
        $mes = $request->input('mes', now()->format('Y-m'));

        $colaborador = Colaborador::where('business_id', $businessId)
            ->with(['user', 'escalaAtual'])
            ->findOrFail($colaboradorId);

        $pdf = $this->reports->espelhoPdf($colaborador, $mes);
        $nome = $this->reports->espelhoPdfNome($colaborador, $mes);

        // stream() abre inline no browser; download() força save
        return $pdf->stream($nome);
    }
}
