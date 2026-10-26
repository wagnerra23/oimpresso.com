<?php

declare(strict_types=1);

namespace Modules\NfeBrasil\Support;

use Illuminate\Support\Facades\DB;
use Modules\NfeBrasil\Models\NfeCertificado;
use RuntimeException;

/**
 * R-NFE-041 · CNPJ da empresa para falar com a SEFAZ (distribuição de DF-e e manifestação).
 *
 * Ordem: o titular do certificado ativo → `business.cnpj` (o campo que a emissão usa em
 * `NfeService::buildXml`) → `business.tax_number_1`. O valor padrão da coluna `business.cnpj`
 * (só zeros) não conta. Sem nenhum dos três a chamada é recusada com a saída —
 * nunca vai à SEFAZ com CNPJ vazio.
 *
 * Origem: em produção o biz=1 tinha `cnpj_titular` e `tax_number_1` vazios e só `business.cnpj`
 * preenchido; os serviços liam só o `tax_number_1` e o job de DF-e falhava todo dia (#9833).
 */
final class CnpjDoEmitente
{
    public static function resolver(int $businessId): ?string
    {
        $titular = NfeCertificado::withoutGlobalScopes() // SUPERADMIN: roda em fila; escopo no where
            ->where('business_id', $businessId)
            ->where('ativo', true)
            ->orderByDesc('id')
            ->value('cnpj_titular');

        $business = DB::table('business')->where('id', $businessId)->first(['cnpj', 'tax_number_1']);

        foreach ([$titular, $business->cnpj ?? null, $business->tax_number_1 ?? null] as $candidato) {
            $digitos = preg_replace('/\D/', '', (string) $candidato) ?? '';
            if (strlen($digitos) === 14 && $digitos !== str_repeat('0', 14)) {
                return $digitos;
            }
        }

        return null;
    }

    /** @throws RuntimeException quando a empresa não tem CNPJ em nenhum dos três lugares */
    public static function obrigatorio(int $businessId): string
    {
        return self::resolver($businessId) ?? throw new RuntimeException(
            "CNPJ da empresa {$businessId} não configurado — preencha em Fiscal › Configuração (ou cadastre o certificado A1)."
        );
    }
}
