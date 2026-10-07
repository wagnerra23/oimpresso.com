<?php

declare(strict_types=1);

namespace Modules\NfeBrasil\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Modules\NfeBrasil\Http\Requests\SugestaoFiscalRequest;
use Modules\NfeBrasil\Models\NfeSugestaoFiscal;
use Modules\NfeBrasil\Services\Tributacao\SugestaoFiscalService;

/**
 * Sugestões da Jana na tributação (playbook Fiscal thread 10 · D-IA · UC-NFTR-10..13). JSON; a tela
 * é outra thread. Toda busca é pelo `business_id` da sessão — sugestão de outra empresa é 404.
 */
class SugestaoFiscalController extends Controller
{
    public function __construct(private readonly SugestaoFiscalService $service) {}

    /** GET /nfe-brasil/tributacao/sugestoes — pendentes da empresa. */
    public function index(SugestaoFiscalRequest $request): JsonResponse
    {
        return response()->json([
            'sugestoes' => NfeSugestaoFiscal::query()
                ->where('business_id', $this->businessId($request))
                ->where('status', 'pendente')
                ->orderByDesc('id')
                ->get(['id', 'tipo', 'alvo_tipo', 'alvo_id', 'valor_sugerido', 'confianca', 'risco', 'motivo']),
        ]);
    }

    /** POST /nfe-brasil/tributacao/sugestoes/gerar — pede sugestões à Jana. Nunca aplica nada. */
    public function gerar(SugestaoFiscalRequest $request): JsonResponse
    {
        $id = $request->validated('product_id');
        $r = $this->service->gerar($this->businessId($request), $id !== null ? (int) $id : null);

        return response()->json($r + [
            'mensagem' => $r['indisponivel'] ? 'Sugestões indisponíveis agora.' : null,
        ]);
    }

    /** POST /nfe-brasil/tributacao/sugestoes/{id}/aceitar */
    public function aceitar(SugestaoFiscalRequest $request, int $id): JsonResponse
    {
        $s = $this->service->aceitar(
            $this->daEmpresa($request, $id),
            $request->user(),
            (bool) $request->validated('confirmou_leitura', false),
        );

        return response()->json(['id' => $s->id, 'status' => $s->status]);
    }

    /** POST /nfe-brasil/tributacao/sugestoes/{id}/descartar */
    public function descartar(SugestaoFiscalRequest $request, int $id): JsonResponse
    {
        $s = $this->service->descartar($this->daEmpresa($request, $id), $request->user());

        return response()->json(['id' => $s->id, 'status' => $s->status]);
    }

    private function businessId(SugestaoFiscalRequest $request): int
    {
        return (int) $request->session()->get('business.id');
    }

    private function daEmpresa(SugestaoFiscalRequest $request, int $id): NfeSugestaoFiscal
    {
        return NfeSugestaoFiscal::query()
            ->where('business_id', $this->businessId($request))
            ->where('id', $id)
            ->firstOrFail();
    }
}
