<?php

declare(strict_types=1);

namespace App\Services\Sells;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Venda sem internet no caixa (US-SELL-054) — o lado do servidor.
 *
 * O navegador gera uma chave por venda (`offline_uuid`) no momento em que ela é
 * finalizada sem rede, e reenvia o MESMO payload até receber resposta. Aqui:
 *  - `jaRecebida` devolve a venda já gravada com aquela chave (o reenvio vira no-op);
 *  - `registrar` grava a chave DENTRO da transação da venda. O UNIQUE da tabela
 *    garante que dois reenvios simultâneos não gravam duas vendas: o segundo insert
 *    falha, a transação inteira volta (sem venda e sem baixa de estoque) e o
 *    próximo reenvio cai em `jaRecebida`.
 *
 * Não recalcula nada: o total, os pagamentos e o estoque seguem o store() de sempre.
 * Tier 0: toda leitura e escrita leva o business_id explícito.
 */
final class VendaOffline
{
    public static function habilitado(int $businessId): bool
    {
        if (! config('sells.offline.enabled')) {
            return false;
        }

        $empresas = (array) config('sells.offline.business_ids', []);

        return empty($empresas) || in_array($businessId, $empresas, true);
    }

    /** Chave da venda offline, ou null se a flag está desligada ou a chave é inválida. */
    public static function chave(Request $request, int $businessId): ?string
    {
        $uuid = (string) $request->input('offline_uuid', '');

        return $uuid !== '' && Str::isUuid($uuid) && self::habilitado($businessId) ? $uuid : null;
    }

    public static function jaRecebida(int $businessId, string $uuid): ?int
    {
        $id = DB::table('vendas_offline_recebidas')
            ->where('business_id', $businessId)
            ->where('offline_uuid', $uuid)
            ->value('transaction_id');

        return $id ? (int) $id : null;
    }

    public static function registrar(int $businessId, string $uuid, int $transactionId, ?string $vendidaEm): void
    {
        DB::table('vendas_offline_recebidas')->insert([
            'business_id'    => $businessId,
            'offline_uuid'   => $uuid,
            'transaction_id' => $transactionId,
            'vendida_em'     => $vendidaEm ? date('Y-m-d H:i:s', (int) strtotime($vendidaEm)) : null,
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);
    }

    /** Resposta para o reenvio do caixa: JSON simples, sem redirect. */
    public static function resposta(bool $sucesso, ?int $transactionId, string $msg, bool $duplicada = false): array
    {
        return [
            'success'        => $sucesso ? 1 : 0,
            'msg'            => $msg,
            'transaction_id' => $transactionId,
            'duplicada'      => $duplicada,
        ];
    }
}
