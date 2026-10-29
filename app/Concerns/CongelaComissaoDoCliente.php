<?php

declare(strict_types=1);

namespace App\Concerns;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * P-156 — a venda nasce com o % de comissão do cliente congelado.
 *
 * Só age quando o cliente tem % (`contacts.comissao_representante_percentual`). Cliente sem %,
 * ou venda que já veio com o % (importação do Delphi), fica como está.
 * Cliente lido pelo business da venda (ADR 0093), não pela sessão.
 */
trait CongelaComissaoDoCliente
{
    private static ?bool $temColunaComissaoCliente = null;

    public static function bootCongelaComissaoDoCliente(): void
    {
        static::creating(function ($t) {
            if ($t->type !== 'sell' || ! $t->contact_id || $t->comissao_cliente_percentual !== null) {
                return;
            }
            // Antes da migration rodar, a venda segue sem a coluna (deploy não quebra a venda).
            self::$temColunaComissaoCliente ??= Schema::hasColumn('transactions', 'comissao_cliente_percentual');
            if (! self::$temColunaComissaoCliente) {
                return;
            }
            $p = DB::table('contacts')->where('id', $t->contact_id)->where('business_id', $t->business_id)
                ->value('comissao_representante_percentual');
            if ($p !== null && (float) $p > 0) {
                $t->comissao_cliente_percentual = $p;
            }
        });
    }
}
