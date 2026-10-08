<?php

declare(strict_types=1);

namespace Modules\NfeBrasil\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\NfeBrasil\Http\Requests\RevisaoContadorRequest;
use Modules\NfeBrasil\Models\NfeRevisaoContador;
use Modules\NfeBrasil\Services\Tributacao\RevisaoContadorService;

/**
 * Revisão do contador pelo usuário com `nfe.tributacao.aceitar` (playbook Fiscal thread 15a). JSON;
 * o link assinado para o contador sem conta é a 15b. Toda busca é pelo `business_id` da sessão.
 */
class RevisaoContadorController extends Controller
{
    public function __construct(private readonly RevisaoContadorService $service) {}

    /** GET /nfe-brasil/tributacao/revisoes — pendentes, ajustes pedidos e quantas regras vigentes estão sem aceite. */
    public function index(RevisaoContadorRequest $request): JsonResponse
    {
        $biz = $this->businessId($request);

        $revisoes = NfeRevisaoContador::query()->where('business_id', $biz)
            ->whereIn('status', ['pendente', 'ajuste_pedido'])->orderByDesc('id')
            ->get(['id', 'regra_id', 'regra_anterior_id', 'origem', 'autor_id', 'diff', 'status', 'comentario', 'created_at']);

        return response()->json([
            'revisoes'   => $revisoes,
            // Aviso, não bloqueio (UC-NFTR-21): quantas regras vigentes não têm revisão aceita.
            'sem_aceite' => DB::table('nfe_fiscal_rules as r')
                ->where('r.business_id', $biz)->whereNull('r.deleted_at')
                ->where(fn ($q) => $q->whereNull('r.valida_ate')->orWhere('r.valida_ate', '>=', now()->toDateString()))
                ->whereNotExists(fn ($q) => $q->from('nfe_revisoes_contador as v')
                    ->whereColumn('v.regra_id', 'r.id')->where('v.status', 'aceita'))
                ->count(),
        ]);
    }

    /** POST /nfe-brasil/tributacao/revisoes/{id}/aceitar */
    public function aceitar(RevisaoContadorRequest $request, int $id): JsonResponse
    {
        $r = $this->service->aceitar($this->daEmpresa($request, $id), $request->user(), $request->ip());

        return response()->json(['id' => $r->id, 'status' => $r->status]);
    }

    /** POST /nfe-brasil/tributacao/revisoes/{id}/ajuste — comentário obrigatório. */
    public function ajuste(RevisaoContadorRequest $request, int $id): JsonResponse
    {
        $comentario = trim((string) $request->validated('comentario', ''));
        if ($comentario === '') {
            throw ValidationException::withMessages(['comentario' => 'Diga o que precisa ser ajustado.']);
        }

        $r = $this->service->pedirAjuste($this->daEmpresa($request, $id), $request->user(), $comentario);

        return response()->json(['id' => $r->id, 'status' => $r->status]);
    }

    private function businessId(RevisaoContadorRequest $request): int
    {
        return (int) $request->session()->get('business.id');
    }

    private function daEmpresa(RevisaoContadorRequest $request, int $id): NfeRevisaoContador
    {
        return NfeRevisaoContador::query()
            ->where('business_id', $this->businessId($request))
            ->where('id', $id)
            ->firstOrFail();
    }
}
