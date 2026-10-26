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

    /** Etapa acima de um ano é digitação errada, não régua: fica de fora. */
    public const DIAS_MAXIMO = 365;

    /**
     * `dias` = etapas DEPOIS do vencimento (padrão 1/5/15). `dias_antes` = avisos ANTES do
     * vencimento (US-FIN-033) — **vazio por padrão**: negócio que já tem a régua ligada não passa
     * a mandar aviso novo aos clientes por efeito de deploy; ele escolhe os dias.
     *
     * @return array{ativo: bool, dias: list<int>, dias_antes: list<int>}
     */
    public function de(int $businessId): array
    {
        $config = $this->ler($businessId)[self::CHAVE] ?? [];

        return [
            'ativo' => (bool) ($config['ativo'] ?? false),
            'dias' => $this->lista($config['dias'] ?? self::DIAS_PADRAO) ?: self::DIAS_PADRAO,
            'dias_antes' => $this->lista($config['dias_antes'] ?? []),
        ];
    }

    /**
     * A tela de configuração (/business/settings) grava os dias como o texto digitado,
     * "1, 5, 15"; `definir()` grava lista. As duas formas valem.
     *
     * @return list<int>
     */
    private function lista(mixed $brutos): array
    {
        if (is_string($brutos)) {
            $brutos = preg_split('/[^0-9]+/', $brutos, -1, PREG_SPLIT_NO_EMPTY);
        }
        $dias = array_values(array_unique(array_filter(
            array_map('intval', (array) $brutos),
            fn (int $d) => $d > 0 && $d <= self::DIAS_MAXIMO
        )));
        sort($dias);

        return $dias;
    }

    /**
     * @param  list<int>|null  $dias
     * @param  list<int>|null  $diasAntes
     * @return array{ativo: bool, dias: list<int>, dias_antes: list<int>}
     */
    public function definir(int $businessId, bool $ativo, ?array $dias = null, ?array $diasAntes = null): array
    {
        $atual = $this->de($businessId);
        $settings = $this->ler($businessId);
        $settings[self::CHAVE] = [
            'ativo' => $ativo,
            'dias' => $dias ?? $atual['dias'],
            'dias_antes' => $diasAntes ?? $atual['dias_antes'],
        ];

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
