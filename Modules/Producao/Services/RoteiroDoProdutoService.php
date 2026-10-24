<?php

declare(strict_types=1);

namespace Modules\Producao\Services;

use App\Contracts\Producao\RoteiroDoProduto;
use Illuminate\Support\Facades\DB;

/**
 * Roteiro do produto para a consulta de produto (UC-FLX-07 · UC-FLX-08 · UC-PIDX-29).
 *
 * Lê o mesmo vínculo que a OS usa ao nascer (`pcp_produto_roteiro`, CriarOsDaVenda): as etapas
 * vêm na ordem do roteiro (bloco, trilha, ordem), cada uma com o posto e o setor do posto. Ao
 * contrário da OS, a etapa opcional aparece, marcada, porque a consulta mostra o roteiro inteiro.
 *
 * Tier 0 (ADR 0093): vínculo, roteiro, etapas e postos filtrados pela empresa. Vínculo que
 * aponta roteiro de outra empresa conta como produto sem roteiro, como na OS (UC-PCROT-04).
 */
final class RoteiroDoProdutoService implements RoteiroDoProduto
{
    public function doProduto(int $businessId, int $productId): ?array
    {
        $roteiro = DB::table('pcp_produto_roteiro as pr')
            ->join('pcp_roteiros as r', fn ($j) => $j->on('r.id', '=', 'pr.roteiro_id')->where('r.business_id', $businessId))
            ->where('pr.business_id', $businessId)
            ->where('pr.product_id', $productId)
            ->first(['r.id', 'r.nome', 'r.ativo']);
        if ($roteiro === null) {
            return null;
        }

        $etapas = DB::table('pcp_roteiro_etapas as e')
            ->leftJoin('pcp_postos as p', fn ($j) => $j->on('p.id', '=', 'e.posto_id')->where('p.business_id', $businessId))
            ->where('e.business_id', $businessId)
            ->where('e.roteiro_id', $roteiro->id)
            ->orderBy('e.bloco')->orderBy('e.trilha')->orderBy('e.ordem')->orderBy('e.id')
            ->get(['e.nome', 'e.horas', 'e.trilha', 'e.opcional_por', 'p.nome as posto', 'p.setor'])
            ->map(fn ($e): array => [
                'nome' => (string) $e->nome,
                'posto' => $e->posto === null ? null : (string) $e->posto,
                'setor' => $e->setor === null || $e->setor === '' ? null : (string) $e->setor,
                'horas' => $e->horas === null ? null : (float) $e->horas,
                'opcional' => $e->opcional_por !== null && $e->opcional_por !== '',
                'paralela' => (int) $e->trilha > 0,
            ])
            ->values()
            ->all();

        return ['nome' => (string) $roteiro->nome, 'ativo' => (bool) $roteiro->ativo, 'etapas' => $etapas];
    }
}
