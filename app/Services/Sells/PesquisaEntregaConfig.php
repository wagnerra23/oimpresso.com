<?php

declare(strict_types=1);

namespace App\Services\Sells;

use Illuminate\Support\Facades\DB;

/**
 * Configuração POR NEGÓCIO da pesquisa de satisfação depois da entrega (US-SELL-071 · UC-FLX-25).
 *
 * Mora em `business.common_settings['sells_pesquisa_entrega']` — o mesmo JSON de liga/desliga por
 * negócio que a régua de cobrança usa (`Modules\Financeiro\Services\ReguaCobrancaConfig`).
 *
 * **Default = DESLIGADA.** Nenhum cliente recebe pesquisa até a empresa ligar. Nota baixa padrão:
 * 2 ou menos (escala 1-5 do CSAT do WhatsApp, `CsatResponse::SCORE_MIN..SCORE_MAX`).
 *
 * Multi-tenant Tier 0 (ADR 0093): `business_id` é argumento explícito.
 */
class PesquisaEntregaConfig
{
    public const CHAVE = 'sells_pesquisa_entrega';

    public const NOTA_BAIXA_PADRAO = 2;

    /** @return array{ativo: bool, nota_baixa: int} */
    public function de(int $businessId): array
    {
        $config = $this->ler($businessId)[self::CHAVE] ?? [];
        $nota = (int) ($config['nota_baixa'] ?? self::NOTA_BAIXA_PADRAO);

        return [
            'ativo' => (bool) ($config['ativo'] ?? false),
            'nota_baixa' => ($nota >= 1 && $nota <= 5) ? $nota : self::NOTA_BAIXA_PADRAO,
        ];
    }

    /** @return array{ativo: bool, nota_baixa: int} */
    public function definir(int $businessId, bool $ativo, ?int $notaBaixa = null): array
    {
        $settings = $this->ler($businessId);
        $settings[self::CHAVE] = ['ativo' => $ativo, 'nota_baixa' => $notaBaixa ?? $this->de($businessId)['nota_baixa']];

        DB::table('business')->where('id', $businessId)->update(['common_settings' => json_encode($settings)]);

        return $this->de($businessId);
    }

    /** @return array<string, mixed> */
    private function ler(int $businessId): array
    {
        $bruto = DB::table('business')->where('id', $businessId)->value('common_settings');
        $settings = is_string($bruto) ? json_decode($bruto, true) : null;

        return is_array($settings) ? $settings : [];
    }
}
