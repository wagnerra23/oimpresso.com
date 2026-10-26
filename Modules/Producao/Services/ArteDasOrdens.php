<?php

declare(strict_types=1);

namespace Modules\Producao\Services;

use Illuminate\Support\Facades\DB;

/**
 * Situação da arte de cada OS por item (UC-PCV-04 · thread 06), lida de `pcp_aprovacoes_arte`.
 *
 * Vale o pedido mais recente da OS: sem decisão = "aguardando" o cliente; recusado = "ajuste" pedido
 * (a etapa de arte voltou). Aprovada, ou OS sem pedido nenhum, não trava — e não entra no resultado.
 * Quadro (filtro "Travadas por arte") e Painel (indicador e fila) leem daqui, então o número é um só.
 *
 * Tier 0 (ADR 0093): consulta por `$businessId` explícito.
 */
final class ArteDasOrdens
{
    public const AGUARDANDO = 'aguardando';

    public const AJUSTE = 'ajuste';

    /**
     * @param  list<int>  $ordemIds
     * @return array<int, array{estado: string, desde: string, comentario: ?string, expira_em: string, arquivo: string}>
     */
    public function travadas(int $businessId, array $ordemIds): array
    {
        if ($ordemIds === []) {
            return [];
        }
        $pedidos = DB::table('pcp_aprovacoes_arte')->where('business_id', $businessId)->whereIn('ordem_id', $ordemIds)
            ->orderByDesc('id')->get(['ordem_id', 'decisao', 'comentario', 'decidido_em', 'expira_em', 'arquivo', 'created_at']);

        $saida = [];
        foreach ($pedidos->unique('ordem_id') as $p) {
            if ($p->decisao === 'aprovada') {
                continue;
            }
            $ajuste = $p->decisao === 'recusada';
            $saida[(int) $p->ordem_id] = [
                'estado' => $ajuste ? self::AJUSTE : self::AGUARDANDO,
                'desde' => substr((string) ($ajuste ? $p->decidido_em : $p->created_at), 0, 16),
                'comentario' => $ajuste ? $p->comentario : null,
                'expira_em' => substr((string) $p->expira_em, 0, 16),
                'arquivo' => (string) $p->arquivo,
            ];
        }

        return $saida;
    }
}
