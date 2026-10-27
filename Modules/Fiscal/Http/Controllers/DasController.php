<?php

declare(strict_types=1);

namespace Modules\Fiscal\Http\Controllers;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;
use Modules\NfeBrasil\Services\Tributacao\ApuracaoDasService;

/**
 * Apuração do DAS (US-FISCAL-030 fatia 2) — tela de leitura sobre o ApuracaoDasService.
 *
 * Só mostra a prévia: não grava, não lança título, não transmite PGDAS-D. O anexo vem da
 * query (`?anexo=`), escolhido pelo contador; sem anexo válido a tela pede para escolher,
 * nunca adivinha. Atrás de `fiscal.das_apuracao_enabled` (default false → 404).
 *
 * Multi-tenant Tier 0 (ADR 0093): o business vem da sessão, nunca da query.
 */
class DasController extends Controller
{
    public function index(Request $request, ApuracaoDasService $das): Response
    {
        abort_unless((bool) config('fiscal.das_apuracao_enabled', false), 404);

        if (! auth()->user()->can('superadmin') && ! auth()->user()->can('fiscal.access')) {
            abort(403, 'Sem permissão fiscal.access');
        }

        $businessId = (int) $request->session()->get('business.id');

        $comp = (string) $request->query('competencia', '');
        $competencia = preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $comp) === 1
            ? CarbonImmutable::createFromFormat('Y-m-d', $comp.'-01')->startOfDay()
            : CarbonImmutable::now()->startOfMonth()->subMonthNoOverflow();

        $anexo = (string) $request->query('anexo', '');
        $anexoValido = isset(ApuracaoDasService::ANEXOS[$anexo]) ? $anexo : null;

        return Inertia::render('Fiscal/Das', [
            'competencia' => $competencia->format('Y-m'),
            'anexo' => $anexoValido,
            'anexos' => array_keys(ApuracaoDasService::ANEXOS),
            'faixas' => $anexoValido ? array_map(
                fn (array $f, int $i) => ['faixa' => $i + 1, 'ate' => $f[0], 'aliquota' => $f[1] / 10_000, 'parcela' => $f[2]],
                ApuracaoDasService::ANEXOS[$anexoValido],
                array_keys(ApuracaoDasService::ANEXOS[$anexoValido]),
            ) : [],
            // Duas somas em `transactions`: adiada (RUNBOOK-inertia-defer-pattern).
            'apuracao' => Inertia::defer(fn () => $anexoValido ? $das->apurar($businessId, $competencia, $anexoValido) : null),
        ]);
    }
}
