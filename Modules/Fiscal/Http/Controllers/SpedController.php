<?php

namespace Modules\Fiscal\Http\Controllers;

use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;
use Modules\NfeBrasil\Models\NfeEmissao;

/**
 * SPED & Livros (sub-página 7 do design KB-9.75).
 *
 * Placeholder no PR — implementação completa exige integração com gerador
 * SPED Fiscal/EFD (Modules/NfeBrasil futuro). Por agora exibe panorama
 * dos períodos com contagens de notas autorizadas (substituto cru de
 * "competências fechadas").
 */
class SpedController extends Controller
{
    public function index(): Response
    {
        if (! auth()->user()->can('superadmin') && ! auth()->user()->can('fiscal.sped.export')) {
            abort(403, 'Sem permissão fiscal.sped.export');
        }

        // 5 últimos meses — contagem agregada de notas autorizadas
        $periodos = [];
        for ($i = 0; $i < 5; $i++) {
            $start = now()->startOfMonth()->subMonths($i);
            $end   = $start->copy()->endOfMonth();

            $count = NfeEmissao::query()
                ->where('status', 'autorizada')
                ->whereBetween('emitido_em', [$start, $end])
                ->count();
            $valor = (float) NfeEmissao::query()
                ->where('status', 'autorizada')
                ->whereBetween('emitido_em', [$start, $end])
                ->sum('valor_total');

            $periodos[] = [
                'mes'           => $start->format('m/Y'),
                'mesIso'        => $start->format('Y-m'),
                'notasAutorizadas' => $count,
                'valorAutorizado'  => $valor,
                'status'        => $i === 0 ? 'aberto' : ($i === 1 ? 'pronto' : 'entregue'),
                'prazoEntrega'  => $i === 0 ? null : $start->copy()->addMonth()->day(15)->format('d/m/Y'),
            ];
        }

        return Inertia::render('Fiscal/Sped', [
            'periodos' => $periodos,
            'notice'   => 'SPED Fiscal (EFD ICMS-IPI) + PIS/COFINS · gerador completo em desenvolvimento. Dados agregados de NfeEmissao mostrados como referência.',
        ]);
    }
}
