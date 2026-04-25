<?php

namespace Modules\Financeiro\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Financeiro\Models\BoletoRemessa;
use Modules\Financeiro\Models\Titulo;
use Modules\Financeiro\Services\TituloService;

/**
 * Tela /financeiro/contas-receber.
 *
 * Lista titulos tipo='receber' com badge de status. Acao "Emitir boleto"
 * chama TituloService que delega pra CnabDirectStrategy.
 *
 * Pattern: ADR 0024 (Inertia + React + UPos).
 */
class ContaReceberController extends Controller
{
    public function index(Request $request): Response
    {
        $businessId = $request->session()->get('business.id');

        $titulos = Titulo::where('business_id', $businessId)
            ->where('tipo', 'receber')
            ->whereNull('deleted_at')
            ->when($request->status, fn ($q, $status) => $q->where('status', $status))
            ->when($request->vence_em, function ($q, $vence) {
                if ($vence === 'hoje') return $q->whereDate('vencimento', today());
                if ($vence === 'atrasado') return $q->whereDate('vencimento', '<', today())->where('status', '!=', 'quitado');
                if ($vence === 'semana') return $q->whereBetween('vencimento', [today(), today()->addDays(7)]);
                return $q;
            })
            ->orderBy('vencimento')
            ->limit(100)
            ->get()
            ->map(function (Titulo $t) {
                $boleto = BoletoRemessa::where('titulo_id', $t->id)
                    ->whereNotIn('status', ['cancelado'])
                    ->orderByDesc('id')
                    ->first();

                return [
                    'id' => $t->id,
                    'numero' => $t->numero,
                    'cliente_descricao' => $t->cliente_descricao,
                    'cliente_id' => $t->cliente_id,
                    'valor_total' => $t->valor_total,
                    'valor_aberto' => $t->valor_aberto,
                    'vencimento' => $t->vencimento?->toDateString(),
                    'status' => $t->status,
                    'origem' => $t->origem,
                    'origem_id' => $t->origem_id,
                    'boleto' => $boleto ? [
                        'id' => $boleto->id,
                        'status' => $boleto->status,
                        'linha_digitavel' => $boleto->linha_digitavel,
                        'nosso_numero' => $boleto->nosso_numero,
                    ] : null,
                ];
            });

        return Inertia::render('Financeiro/ContasReceber/Index', [
            'titulos' => $titulos,
            'filtros' => [
                'status' => $request->status,
                'vence_em' => $request->vence_em,
            ],
        ]);
    }

    public function emitirBoleto(Request $request, int $tituloId, TituloService $service): RedirectResponse
    {
        $businessId = $request->session()->get('business.id');

        $titulo = Titulo::where('business_id', $businessId)->findOrFail($tituloId);

        try {
            $remessa = $service->emitirBoleto(
                $titulo,
                $request->integer('conta_bancaria_id') ?: null
            );

            return back()->with('success', "Boleto gerado: {$remessa->nosso_numero}");
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }
    }
}
