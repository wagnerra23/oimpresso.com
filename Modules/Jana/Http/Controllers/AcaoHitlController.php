<?php

declare(strict_types=1);

namespace Modules\Jana\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Jana\Services\AcaoHitlService;

/**
 * Ações sugeridas do Painel (`/ia`) — prévia + aprovação HITL.
 *
 * Duas rotas, e a assimetria é de propósito:
 *  - `GET  /ia/acoes/{acao}/previa`  → JSON, consumido por `fetch` no modal (é
 *    leitura de overlay, não navegação; uma visita Inertia recarregaria props da
 *    página inteira pra encher um parágrafo).
 *  - `POST /ia/acoes/{acao}/aprovar` → `back()`, visita Inertia normal (é mutação,
 *    e o flash vira toast pelo handler global do `app.tsx`).
 *
 * O `business_id` vem da SESSÃO, nunca do request — mesmo padrão do
 * `IndexController::index()` (multi-tenant Tier 0, ADR 0093).
 *
 * `AcaoHitlController` e não `AcoesController`: esse nome já existe em
 * `Modules\Fiscal\Http\Controllers` (ações de DF-e na SEFAZ — cancelar, CC-e,
 * inutilizar), conceito sem relação com este. Namespaces distintos não colidem em
 * PHP, mas homônimo entre módulos custa a cada `grep`. O sufixo da URL (`/ia/acoes`)
 * e os route names (`jana.acoes.*`) ficam — quem lê a rota quer "ações", não "HITL".
 *
 * @see Modules\Jana\Services\AcaoHitlService
 */
class AcaoHitlController extends Controller
{
    public function previa(Request $request, string $acao, AcaoHitlService $hitl): JsonResponse
    {
        abort_unless($hitl->existe($acao), 404);

        $businessId = (int) $request->session()->get('user.business_id');

        return response()->json($hitl->previa($acao, $businessId));
    }

    public function aprovar(Request $request, string $acao, AcaoHitlService $hitl)
    {
        abort_unless($hitl->existe($acao), 404);

        $businessId = (int) $request->session()->get('user.business_id');

        $hitl->aprovar($acao, $businessId, (int) auth()->id());

        // `back()` e não `redirect('/ia')`: o Inertia trata como visita da mesma
        // página e o Painel não pisca.
        //
        // A chave do flash é `success` (não `sucesso`): é a que
        // `HandleInertiaRequests` expõe e a que o handler global do `app.tsx`
        // (`router.on('success')` → `showFlashToast`) lê. Por isso a Page NÃO
        // precisa — e não deve — montar `useEffect`/`toast` próprio: seria toast
        // em dobro.
        return back()->with('success', 'Aprovação registrada — nada sai antes do envio ser ligado.');
    }
}
