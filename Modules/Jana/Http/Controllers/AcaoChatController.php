<?php

declare(strict_types=1);

namespace Modules\Jana\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Jana\Entities\AcaoAprovacao;
use Modules\Jana\Services\AcoesDoChat\AcaoPropostaException;
use Modules\Jana\Services\AcoesDoChat\PropostaAcaoService;

/**
 * Os cartões de ação do chat (ADR 0440): listar as propostas da conversa,
 * confirmar e cancelar. O `business_id` sai da sessão; o autor é o usuário logado.
 *
 * Atrás de `copiloto.chat_acoes.enabled` (padrão desligado): com a flag desligada
 * as três rotas respondem 404, como se não existissem.
 */
class AcaoChatController extends Controller
{
    public function __construct(private PropostaAcaoService $propostas) {}

    public function index(Request $request, int $conversa): JsonResponse
    {
        $this->exigirFlag();

        $itens = AcaoAprovacao::where('business_id', $this->businessId($request))
            ->where('origem', 'chat')
            ->where('conversa_id', $conversa)
            ->where('user_id', (int) $request->user()->getAuthIdentifier())
            ->orderBy('id')
            ->limit(50)
            ->get()
            ->map(fn (AcaoAprovacao $p) => $this->forma($p))
            ->values();

        return response()->json(['acoes' => $itens]);
    }

    public function confirmar(Request $request, int $id): JsonResponse
    {
        $this->exigirFlag();

        return $this->decidir(fn () => $this->propostas->confirmar($id, $this->businessId($request), $request->user()));
    }

    public function cancelar(Request $request, int $id): JsonResponse
    {
        $this->exigirFlag();

        return $this->decidir(fn () => $this->propostas->cancelar($id, $this->businessId($request), $request->user()));
    }

    /** @param callable(): AcaoAprovacao $decisao */
    private function decidir(callable $decisao): JsonResponse
    {
        try {
            return response()->json(['acao' => $this->forma($decisao())]);
        } catch (AcaoPropostaException $e) {
            return response()->json(['erro' => $e->getMessage()], $e->getCode() ?: 422);
        }
    }

    /** @return array<string, mixed> */
    private function forma(AcaoAprovacao $p): array
    {
        return [
            'id'        => $p->id,
            'acao'      => $p->acao_key,
            'status'    => $p->status,
            'resumo'    => $p->previa,
            'expira_em' => $p->expira_em?->toIso8601String(),
            'resultado' => $p->resultado,
        ];
    }

    private function businessId(Request $request): int
    {
        return (int) $request->session()->get('user.business_id');
    }

    private function exigirFlag(): void
    {
        abort_unless((bool) config('copiloto.chat_acoes.enabled', false), 404);
    }
}
