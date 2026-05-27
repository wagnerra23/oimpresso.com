<?php

// @memcofre
//   controller: FluxoController
//   tela: /financeiro/fluxo-caixa
//   module: Financeiro
//   status: stub-mock-data
//   tests: Modules/Financeiro/Tests/Feature/FluxoControllerTest

namespace Modules\Financeiro\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class FluxoController extends Controller
{
    public function index(Request $request): Response
    {
        $today = Carbon::today();
        $saldoHoje = 18420.50;
        $margemMinima = 5000.00;

        // TODO[CL]: substituir por FluxoCaixaService->projetar(tenantId, dias=35)
        $dias = collect(range(-2, 32))->map(function (int $offset) use ($today) {
            $d = $today->copy()->addDays($offset);
            $entradas = $offset >= 0 ? rand(0, 1500) : 0;
            $saidas = $offset >= 0 ? rand(0, 1200) : 0;
            return [
                'data' => $d->toDateString(),
                'data_label' => $d->translatedFormat('d M'),
                'is_today' => $offset === 0,
                'is_past' => $offset < 0,
                'entradas' => (float) $entradas,
                'saidas' => (float) $saidas,
                'liquido' => (float) ($entradas - $saidas),
                'saldo_acumulado' => 0.0, // calculado abaixo
                'eventos' => [],
            ];
        });

        $saldo = $saldoHoje;
        $dias = $dias->map(function ($d) use (&$saldo) {
            if (! $d['is_past']) {
                $saldo += $d['liquido'];
            }
            $d['saldo_acumulado'] = $saldo;
            return $d;
        });

        $piorDia = $dias->where('is_past', false)->sortBy('saldo_acumulado')->first();

        return Inertia::render('Financeiro/Fluxo/Index', [
            'saldo_hoje' => $saldoHoje,
            'saldo_30d' => $dias->last()['saldo_acumulado'],
            'pior_dia' => [
                'saldo' => $piorDia['saldo_acumulado'],
                'data_label' => $piorDia['data_label'],
            ],
            'margem_minima' => $margemMinima,
            'conta' => 'Itaú PJ · ag 0438 cc 4521-7',
            'dias' => $dias->values(),
        ]);
    }
}
