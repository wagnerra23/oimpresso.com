<?php

namespace Modules\Financeiro\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Modules\Financeiro\Models\DdaBoleto;
use Modules\Financeiro\Services\DdaService;

/**
 * Boletos DDA sugeridos como conta a pagar, na Visão Unificada (US-FIN-078).
 *
 * Desligado (`FINANCEIRO_DDA_ENABLED=false`) responde 404 e a tela não mostra a entrada.
 * Decidir (aceitar/descartar) exige `financeiro.contas_pagar.create`. `business_id` sempre da sessão.
 */
class DdaController extends Controller
{
    public function __construct(private readonly DdaService $dda) {}

    /** Sugestões em aberto da empresa, para a prop lazy `dda` da Visão Unificada. Null = desligado. */
    public static function sugestoes(int $businessId): ?array
    {
        if (! config('financeiro.dda.enabled')) {
            return null;
        }

        $boletos = DdaBoleto::where('business_id', $businessId)
            ->where('status', DdaBoleto::STATUS_SUGERIDO)
            ->orderBy('vencimento')
            ->limit(100)
            ->get();

        $nomes = DB::table('contacts')->where('business_id', $businessId)
            ->whereIn('id', $boletos->pluck('contact_id')->filter()->unique()->all())
            ->pluck('name', 'id');

        return $boletos->map(fn (DdaBoleto $b) => [
            'id' => $b->id,
            'beneficiario' => $b->beneficiario_nome,
            'fornecedor' => $b->contact_id ? ($nomes[$b->contact_id] ?? null) : null,
            'valor' => (float) $b->valor,
            'vencimento' => $b->vencimento->toDateString(),
        ])->all();
    }

    public function aceitar(Request $request, int $id): RedirectResponse
    {
        $businessId = $this->autorizar($request);

        try {
            $titulo = $this->dda->aceitar($businessId, $id, (int) $request->user()->id);
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Conta a pagar {$titulo->numero} criada a partir do boleto DDA.");
    }

    public function descartar(Request $request, int $id): RedirectResponse
    {
        $businessId = $this->autorizar($request);

        try {
            $this->dda->descartar($businessId, $id, (int) $request->user()->id);
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Boleto DDA descartado.');
    }

    private function autorizar(Request $request): int
    {
        abort_unless(config('financeiro.dda.enabled'), 404);
        abort_unless(
            $request->user()?->can('financeiro.contas_pagar.create') || $request->user()?->can('superadmin'),
            403,
            'Sem permissão para criar contas a pagar.'
        );

        return (int) session('user.business_id');
    }
}
