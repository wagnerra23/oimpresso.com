<?php

declare(strict_types=1);

namespace Modules\Marketplaces\Services\MercadoLivre;

use Illuminate\Support\Facades\Http;
use Modules\Marketplaces\Entities\ContaVendedor;
use Modules\Marketplaces\Services\ContasVendedor;

/**
 * Destinatário da NF-e a partir dos dados de faturamento do Mercado Livre (US-MKT-009).
 *
 * Devolve um array com as MESMAS chaves de `contacts` (tax_number, name, inscricao_estadual,
 * indicador_ie, address_line_1, numero, neighborhood, city, city_code, state, zip_code, email),
 * que é a interface combinada com a frente Fiscal para
 * `NfeService::emitirParaTransaction(..., destinatarioExterno: [...])`.
 *
 * ⚠️ HIPÓTESE, não medido: o ML tem dois formatos e a documentação oficial recusou leitura
 * automática (403) em 2026-10-26 — os nomes abaixo vêm da documentação indexada e precisam ser
 * reconferidos contra uma resposta real quando o app existir.
 *  - novo: `/orders/billing-info/{site}/{billing_info_id}` → `billing_info.identification.{type,number}`,
 *    `address.{street_name, street_number, neighborhood, city_name, state.code, zip_code}`,
 *    `taxes.inscriptions.state_registration`;
 *  - legado (descontinuado pelo ML): `/orders/{id}/billing_info` → `billing_info.{doc_type, doc_number}`
 *    + `additional_info[{type, value}]` (STREET_NAME, STREET_NUMBER, NEIGHBORHOOD, CITY_NAME,
 *    STATE_NAME/STATE_CODE, ZIP_CODE, FIRST_NAME, LAST_NAME, BUSINESS_NAME, STATE_REGISTRATION).
 * Campo que não vier fica nulo; sem CPF/CNPJ devolve null (a nota não sai — R-NFE-036).
 */
class DestinatarioMercadoLivre
{
    /** @param  array<string, mixed>  $pedido  JSON do pedido do ML (`raw_payload`) */
    public function buscar(ContaVendedor $conta, array $pedido, string $apiBase): ?array
    {
        $token = app(ContasVendedor::class)->tokenValido($conta);
        $idFaturamento = $pedido['buyer']['billing_info']['id'] ?? ($pedido['billing_info']['id'] ?? null);

        $url = $idFaturamento !== null
            ? $apiBase.'/orders/billing-info/MLB/'.rawurlencode((string) $idFaturamento)
            : $apiBase.'/orders/'.rawurlencode((string) ($pedido['id'] ?? '')).'/billing_info';

        $resposta = Http::withToken($token)->acceptJson()->timeout(15)->get($url);
        if (! $resposta->successful()) {
            return null;
        }

        return self::mapear((array) $resposta->json());
    }

    /** @param  array<string, mixed>  $r */
    public static function mapear(array $r): ?array
    {
        $b = (array) ($r['billing_info'] ?? $r['buyer']['billing_info'] ?? $r);
        $extra = [];
        foreach ((array) ($b['additional_info'] ?? []) as $par) {
            if (isset($par['type'])) {
                $extra[strtoupper((string) $par['type'])] = $par['value'] ?? null;
            }
        }
        $endereco = (array) ($b['address'] ?? []);

        $documento = self::digitos($b['identification']['number'] ?? $b['doc_number'] ?? $extra['DOC_NUMBER'] ?? null);
        if ($documento === null || ! in_array(strlen($documento), [11, 14], true)) {
            return null;
        }

        $nome = trim((string) ($b['name'] ?? $extra['BUSINESS_NAME'] ?? trim(($extra['FIRST_NAME'] ?? '').' '.($extra['LAST_NAME'] ?? ''))));
        if (isset($b['last_name']) && $nome !== '' && ! str_contains($nome, (string) $b['last_name'])) {
            $nome .= ' '.$b['last_name'];
        }
        $ie = self::texto($b['taxes']['inscriptions']['state_registration'] ?? $extra['STATE_REGISTRATION'] ?? null);
        $uf = self::texto($endereco['state']['code'] ?? $extra['STATE_CODE'] ?? null);
        $uf = $uf !== null ? strtoupper(substr(preg_replace('/^BR-/i', '', $uf), 0, 2)) : null;

        return [
            'tax_number' => $documento,
            'name' => $nome !== '' ? $nome : null,
            'inscricao_estadual' => $ie,
            'indicador_ie' => $ie !== null ? 1 : 9,
            'address_line_1' => self::texto($endereco['street_name'] ?? $extra['STREET_NAME'] ?? null),
            'numero' => self::texto($endereco['street_number'] ?? $extra['STREET_NUMBER'] ?? null),
            'neighborhood' => self::texto($endereco['neighborhood'] ?? $extra['NEIGHBORHOOD'] ?? null),
            'city' => self::texto($endereco['city_name'] ?? $extra['CITY_NAME'] ?? null),
            'city_code' => null,
            'state' => $uf,
            'zip_code' => self::digitos($endereco['zip_code'] ?? $extra['ZIP_CODE'] ?? null),
            'email' => null,
        ];
    }

    private static function digitos(mixed $v): ?string
    {
        $d = preg_replace('/\D+/', '', (string) ($v ?? ''));

        return $d !== '' ? $d : null;
    }

    private static function texto(mixed $v): ?string
    {
        $t = trim((string) ($v ?? ''));

        return $t !== '' ? $t : null;
    }
}
