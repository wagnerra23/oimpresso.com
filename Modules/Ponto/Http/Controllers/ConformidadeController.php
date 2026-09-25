<?php

namespace Modules\Ponto\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Ponto\Services\ConformidadeService;

/**
 * Painel de Conformidade CLT — `/ponto/conformidade` (ADR 0413 D0: somente leitura).
 *
 * Só GET. A correção de qualquer apontamento acontece no Espelho ou em Intercorrências;
 * este controller não tem método de escrita e não deve ganhar um (anti-hook do charter).
 */
class ConformidadeController extends Controller
{
    public function index(Request $request, ConformidadeService $conformidade): Response
    {
        $businessId = (int) (session('business.id') ?: $request->user()->business_id);
        $mes = (string) $request->input('mes', now()->format('Y-m'));
        if (! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $mes)) {
            $mes = now()->format('Y-m');
        }

        // `mes` é estado de filtro (eager). O painel agrega a competência inteira → defer
        // (RUNBOOK-inertia-defer-pattern.md).
        return Inertia::render('Ponto/Conformidade', [
            'mes'    => $mes,
            'painel' => Inertia::defer(fn () => $conformidade->competencia($businessId, $mes)),
        ]);
    }
}
