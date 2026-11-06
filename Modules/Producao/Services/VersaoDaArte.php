<?php

declare(strict_types=1);

namespace Modules\Producao\Services;

use Illuminate\Support\Facades\DB;

/**
 * Versão da arte de uma OS (UC-PCART-09, decisão [W] 2026-11-06): v1, v2, v3… para o operador pedir
 * "imprime a v2" sem ambiguidade.
 *
 * A versão só sobe quando o ARQUIVO muda em relação ao envio anterior da mesma OS. Reenviar o link do
 * mesmo arquivo gera um pedido novo (link novo, o anterior vence), mas é a mesma arte: mesma versão.
 * Voltar a um arquivo antigo depois de outro conta como versão nova (A, B, A = v1, v2, v3), porque é o
 * que o cliente recebeu por último.
 *
 * Tier 0 (ADR 0093): a numeração é por OS dentro da empresa; nada cruza `business_id`.
 */
final class VersaoDaArte
{
    /** Versão do próximo envio, dado o arquivo e a versão do envio anterior da OS (null se é o primeiro). */
    public static function seguinte(?string $arquivoAnterior, ?int $versaoAnterior, string $arquivo): int
    {
        if ($arquivoAnterior === null || $versaoAnterior === null) {
            return 1;
        }

        return $arquivoAnterior === $arquivo ? $versaoAnterior : $versaoAnterior + 1;
    }

    /**
     * Numera os envios já gravados, OS a OS, na ordem em que foram feitos (o `id`). Usado uma vez pela
     * migration que cria a coluna; reexecutar dá o mesmo resultado. Devolve quantas linhas mudaram.
     */
    public static function preencher(): int
    {
        $alteradas = 0;
        $chaves = DB::table('pcp_aprovacoes_arte')->select('business_id', 'ordem_id')->distinct()->get();
        foreach ($chaves as $k) {
            [$arquivoAnterior, $versaoAnterior] = [null, null];
            $envios = DB::table('pcp_aprovacoes_arte')->where('business_id', $k->business_id)->where('ordem_id', $k->ordem_id)
                ->orderBy('id')->get(['id', 'arquivo', 'versao']);
            foreach ($envios as $e) {
                $versao = self::seguinte($arquivoAnterior, $versaoAnterior, (string) $e->arquivo);
                if ((int) $e->versao !== $versao) {
                    DB::table('pcp_aprovacoes_arte')->where('business_id', $k->business_id)->where('id', $e->id)->update(['versao' => $versao]);
                    $alteradas++;
                }
                [$arquivoAnterior, $versaoAnterior] = [(string) $e->arquivo, $versao];
            }
        }

        return $alteradas;
    }
}
