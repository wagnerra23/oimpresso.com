<?php

namespace Modules\Ponto\Http\Controllers;

use App\Http\Controllers\Controller;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Ponto\Entities\Competencia;
use Modules\Ponto\Services\FechamentoService;

/**
 * Fechamento da competência (ADR 0413). A rota exige `ponto.fechar` (D1) — permissão própria,
 * não `ponto.access`. Não existe ação de reabrir (D1) nem geração de AFD/AEJ aqui (D4).
 */
class FechamentoController extends Controller
{
    public function __construct(private FechamentoService $service)
    {
    }

    /** Ver é `ponto.access` (grupo); fechar é `ponto.fechar` — a tela só oferece o botão a quem pode. */
    public function index(Request $request): Response
    {
        $businessId = (int) (session('business.id') ?: $request->user()->business_id);
        $mes = $request->validate(['competencia' => ['nullable', 'date_format:Y-m']])['competencia'] ?? now()->format('Y-m');
        $competencia = CarbonImmutable::createFromFormat('!Y-m', $mes);

        $fechada = Competencia::query()->with('fechador:id,first_name,last_name')
            ->where('business_id', $businessId)
            ->where('competencia', $competencia->toDateString())
            ->first();

        return Inertia::render('Ponto/Fechamento/Index', [
            'competencia' => $mes,
            'pode_fechar' => $request->user()->can('ponto.fechar'),
            'fechada'     => $fechada ? [
                'fechada_em'        => $fechada->fechada_em->format('d/m/Y H:i'),
                'fechada_por'       => trim(($fechada->fechador->first_name ?? '') . ' ' . ($fechada->fechador->last_name ?? '')),
                'bloqueios_aceitos' => (int) collect($fechada->bloqueios_aceitos ?? [])->where('grave', true)->sum('n'),
            ] : null,
            'bloqueios'   => Inertia::defer(fn () => $this->service->preChecagem($businessId, $competencia)),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $dados = $request->validate([
            'competencia'        => ['required', 'date_format:Y-m'],
            'aceitar_bloqueios'  => ['sometimes', 'boolean'],
        ]);

        $businessId = (int) (session('business.id') ?: $request->user()->business_id);
        $competencia = CarbonImmutable::createFromFormat('!Y-m', $dados['competencia']);

        try {
            $fechada = $this->service->fechar(
                $businessId,
                $competencia,
                (int) $request->user()->id,
                (bool) ($dados['aceitar_bloqueios'] ?? false)
            );
        } catch (DomainException $e) {
            return back()->withErrors(['competencia' => $e->getMessage()]);
        }

        $aceitos = count($fechada->bloqueios_aceitos ?? []);

        return back()->with('success', 'Competência ' . $competencia->format('m/Y') . ' fechada'
            . ($aceitos ? " aceitando {$aceitos} bloqueio(s), registrados com seu nome e a data." : '.'));
    }
}
