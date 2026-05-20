<?php

namespace Modules\Fiscal\Http\Controllers;

use Illuminate\Http\Response as HttpResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Fiscal\Services\SpedIcmsIpiGeneratorService;
use Modules\NfeBrasil\Models\NfeEmissao;

/**
 * SPED & Livros (sub-página 7 do design KB-9.75).
 *
 * Wave 8 (PR #8): gerador EFD-ICMS/IPI MVP. Endpoint download .txt CONFAZ layout.
 * PIS/COFINS (EFD-Contribuições separado) + Bloco E apuração + Bloco H inventário
 * ficam pra próximas Waves.
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
            'notice'   => 'SPED Fiscal (EFD ICMS-IPI) — gerador MVP saídas v3.1.1 disponível (PR #8). PIS/COFINS + Bloco E apuração + entradas em próximas Waves.',
        ]);
    }

    /**
     * GET /fiscal/sped/icms-ipi/{ano}/{mes} — download TXT EFD-ICMS/IPI.
     *
     * Layout CONFAZ Guia Prático v3.1.1 (perfil A).
     * Permissão: fiscal.sped.export.
     * Multi-tenant Tier 0: businessId via session (ADR 0093 + cross-tenant guard
     * no Service).
     */
    public function gerar(SpedIcmsIpiGeneratorService $service, int $ano, int $mes): HttpResponse
    {
        if (! auth()->user()->can('superadmin') && ! auth()->user()->can('fiscal.sped.export')) {
            abort(403, 'Sem permissão fiscal.sped.export');
        }

        $businessId = (int) session('user.business_id');

        try {
            $conteudo = $service->gerar($businessId, $ano, $mes);

            Log::info('Fiscal.sped.gerar ok', [
                'business_id' => $businessId,
                'ano'         => $ano,
                'mes'         => $mes,
                'bytes'       => strlen($conteudo),
                'linhas'      => substr_count($conteudo, "\r\n"),
            ]);

            $nomeArquivo = sprintf('EFD-ICMS-IPI-%04d-%02d.txt', $ano, $mes);

            return response($conteudo, 200, [
                'Content-Type'              => 'text/plain; charset=UTF-8',
                'Content-Disposition'       => 'attachment; filename="' . $nomeArquivo . '"',
                'Content-Length'            => (string) strlen($conteudo),
                'X-Sped-Layout-Version'     => '018', // v3.1.1
                'X-Robots-Tag'              => 'noindex',
            ]);
        } catch (\Throwable $e) {
            Log::error('Fiscal.sped.gerar falhou', [
                'business_id' => $businessId,
                'ano'         => $ano,
                'mes'         => $mes,
                'error'       => $e->getMessage(),
            ]);

            return response("Erro na geração SPED: {$e->getMessage()}", 500, ['Content-Type' => 'text/plain']);
        }
    }
}
