<?php

declare(strict_types=1);

namespace Modules\Financeiro\Services;

use Illuminate\Support\Facades\DB;

/**
 * Configuração POR NEGÓCIO da régua de cobrança (US-FIN-071 · UC-FLX-29).
 *
 * Mora em `business.common_settings['financeiro_regua_cobranca']` — o JSON que o núcleo já usa
 * para liga/desliga por negócio (mesmo padrão de `Modules\Arquivos\Services\AvisoTitularCanais`).
 * Nada de tabela nova.
 *
 * **Default = DESLIGADA.** Nenhum cliente recebe lembrete até o negócio ligar: ligar é ato
 * consciente, não efeito colateral de deploy. Dias padrão 1/5/15 após o vencimento.
 *
 * Multi-tenant Tier 0 (ADR 0093): `business_id` é argumento explícito.
 */
class ReguaCobrancaConfig
{
    public const CHAVE = 'financeiro_regua_cobranca';

    public const DIAS_PADRAO = [1, 5, 15];

    /** @return array{ativo: bool, dias: list<int>} */
    public function de(int $businessId): array
    {
        $config = $this->ler($businessId)[self::CHAVE] ?? [];
        $dias = array_values(array_unique(array_filter(
            array_map('intval', (array) ($config['dias'] ?? self::DIAS_PADRAO)),
            fn (int $d) => $d > 0
        )));
        sort($dias);

        return [
            'ativo' => (bool) ($config['ativo'] ?? false),
            'dias' => $dias ?: self::DIAS_PADRAO,
        ];
    }

    /**
     * @param  list<int>|null  $dias
     * @return array{ativo: bool, dias: list<int>}
     */
    public function definir(int $businessId, bool $ativo, ?array $dias = null): array
    {
        $settings = $this->ler($businessId);
        $settings[self::CHAVE] = ['ativo' => $ativo, 'dias' => $dias ?? $this->de($businessId)['dias']];

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
