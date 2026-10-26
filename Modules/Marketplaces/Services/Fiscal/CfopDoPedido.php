<?php

declare(strict_types=1);

namespace Modules\Marketplaces\Services\Fiscal;

/**
 * CFOP da NF-e de um pedido de marketplace (US-MKT-009 · ADR 0439 D5 · SPEC §5).
 *
 * ⚠️ O CFOP vem SÓ da configuração (`marketplaces.nfe.cfop_*`), sem valor padrão: depende do
 * contador (D5). Sem ele, devolve null e a nota cai em "não pode". A sugestão do SPEC §5 é 5102 na
 * mesma UF e 6102 em outra.
 *
 * Venda em que o próprio vendedor envia (me1/me2/cross_docking/drop_off): o CFOP de mesma UF ou
 * de outra UF configurado. Venda saída do estoque Full do ML (`fulfillment`) NÃO tem CFOP aqui: a nota da
 * venda Full depende da remessa/retorno em consignação (5910/1411 do SPEC §5) e fica para quando o
 * contador decidir — devolve null e a emissão é recusada com o motivo.
 */
final class CfopDoPedido
{
    public static function resolver(?string $ufEmitente, ?string $ufDestino, ?string $logistica): ?string
    {
        if ($logistica === 'fulfillment') {
            return null;
        }
        $origem = strtoupper(trim((string) $ufEmitente));
        $destino = strtoupper(trim((string) $ufDestino));
        if (strlen($origem) !== 2 || strlen($destino) !== 2) {
            return null;
        }

        $cfop = trim((string) config($origem === $destino ? 'marketplaces.nfe.cfop_mesma_uf' : 'marketplaces.nfe.cfop_outra_uf'));

        // Sem CFOP configurado pelo contador, ou fora de 1xxx–7xxx (regra do NfeService), não há CFOP.
        return preg_match('/^[1-7]\d{3}$/', $cfop) === 1 ? $cfop : null;
    }
}
