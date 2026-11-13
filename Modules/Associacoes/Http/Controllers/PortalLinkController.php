<?php

declare(strict_types=1);

namespace Modules\Associacoes\Http\Controllers;

use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Associacoes\Entities\Associada;
use Modules\Associacoes\Services\AssociacoesAcesso;
use Modules\Associacoes\Services\PortalAssociadaService;

/**
 * Gerar e revogar o link da área da associada (ADR 0451 D6 · US-ASSOC-005). Endpoint JSON para o
 * botão da tela de associadas da onda 4 chamar depois do merge; esta onda não edita aquela tela.
 *
 * Quem pode: `associacoes.editar` (ou superadmin) E alcance sobre a associação da associada (D4):
 * a diretoria com `associacoes.rede` alcança a rede, o representante só a associação dele. Fora do
 * alcance, ou de outra empresa, é 404 — a associada "não existe" para quem não a enxerga.
 *
 * Gerar devolve link e PIN UMA vez; o banco guarda só os hashes. Gerar de novo revoga o anterior.
 */
class PortalLinkController extends Controller
{
    public function __construct(
        private readonly PortalAssociadaService $tokens,
        private readonly AssociacoesAcesso $acesso,
    ) {}

    public function store(Request $request, int $id): JsonResponse
    {
        $associada = $this->associadaAlcancavel($request, $id);

        try {
            $r = $this->tokens->emitir($associada, (int) $request->user()->id);
        } catch (DomainException) {
            return response()->json(['erro' => 'associada_desligada'], 422);
        }

        return response()->json([
            'link' => url('/associacoes/m/'.$r['token']),
            'pin' => $r['pin'],
            'expira_em' => $r['expires_at']->toDateString(),
        ], 201);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $associada = $this->associadaAlcancavel($request, $id);

        return response()->json(['revogados' => $this->tokens->revogar($associada)]);
    }

    private function associadaAlcancavel(Request $request, int $id): Associada
    {
        $user = $request->user();
        abort_unless($user->can(AssociacoesAcesso::PERMISSAO_EDITAR) || $user->can('superadmin'), 403);

        // Empresa explícita além do escopo global (que não filtra sem `user.business_id` na sessão);
        // o alcance D4 restringe às associações do usuário.
        $biz = (int) $request->session()->get('user.business_id');
        abort_if($biz <= 0, 403);
        $query = Associada::query()->where('assoc_associadas.business_id', $biz)->whereKey($id);

        /** @var Associada $associada */
        $associada = $this->acesso->restringir($query, $user)->firstOrFail();

        return $associada;
    }
}
