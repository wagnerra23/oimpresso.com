<?php

declare(strict_types=1);

namespace Modules\PaymentGateway\Services;

/**
 * Juros e multa do boleto, configurados na carteira (Sabor Brasil, item 4 do plano de migração).
 *
 * Os percentuais moram no `config_json` da credencial do banco, que é a carteira:
 *   - `juros_mes_pct` — juros de mora em % ao mês (taxa mensal);
 *   - `multa_pct`     — multa em % do valor, cobrada uma vez depois do vencimento.
 *
 * No Delphi eles vinham de `CONTAS.JUROS` e `CONTAS.MULTA`. A unidade do `JUROS` de lá não está
 * declarada no banco (o boleto guardava `JUROS_MORA` = valor × JUROS / 100 em reais), por isso a
 * chave aqui diz a unidade no nome e quem migra escolhe o número.
 *
 * Como cada banco recebe:
 *   - Inter (API v3): `mora` TAXAMENSAL e `multa` PERCENTUAL, com a taxa em %;
 *   - BB e demais CNAB 240: segmento P pos. 118 código 2 (taxa mensal) e 127-141 a taxa;
 *     segmento R pos. 66 código 2 (percentual) e 75-89 o percentual (lib `Cnab240/Banco/Bb.php`).
 *
 * Fora de (0, 100] a chave é ignorada: o boleto sai isento daquele item, como antes.
 */
final class JurosMultaCarteira
{
    /**
     * @param  array<string, mixed>  $config  `config_json` da credencial
     * @return array{juros: array{tipo?: string, percentual?: float}, multa: array{tipo?: string, percentual?: float}}
     */
    public static function daCarteira(array $config): array
    {
        $juros = self::percentual($config['juros_mes_pct'] ?? null);
        $multa = self::percentual($config['multa_pct'] ?? null);

        return [
            'juros' => $juros === null ? [] : ['tipo' => 'taxa_mensal', 'percentual' => $juros],
            'multa' => $multa === null ? [] : ['tipo' => 'percentual', 'percentual' => $multa],
        ];
    }

    /** Percentual com 2 casas (o CNAB 240 leva 13 inteiros + 2 decimais). */
    private static function percentual(mixed $valor): ?float
    {
        if ($valor === null || $valor === '' || ! is_numeric(str_replace(',', '.', (string) $valor))) {
            return null;
        }
        $pct = round((float) str_replace(',', '.', (string) $valor), 2);

        return $pct > 0 && $pct <= 100 ? $pct : null;
    }
}
