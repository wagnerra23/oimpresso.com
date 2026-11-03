<?php

namespace Modules\Repair\Concerns;

use App\Services\FeatureFlagService;

/**
 * Decide se uma tela do Repair renderiza a Page React (Inertia) ou o Blade legado.
 *
 * Duas portas, qualquer uma liga (OU):
 *
 *  1. `config/mwart.php` (env `MWART_REPAIR_*` + allowlist `_BIZ`) — o caminho
 *     antigo, mantido intacto. Medido no .env de produção em 2026-11-03: 5 telas
 *     ligadas assim, todas só para business_id=1 (index, status, device-models
 *     index, dashboard, job-sheet index). Mexer no .env de produção não é o
 *     caminho (regra "mexeu, registra"), por isso a 2ª porta.
 *  2. Flag GrowthBook por empresa (`useV2Repair*`, atributo `business_id`) —
 *     liga uma tela para uma empresa sem deploy nem edição de .env. Sem regra no
 *     GrowthBook a flag cai no fallback do FeatureFlagService, que é false para
 *     todas estas: nada muda até alguém criar a regra.
 *
 * Antes deste trait havia 6 cópias privadas de `mwartEnabled` nos controllers —
 * uma regra só, num lugar só.
 */
trait TelaReactPorEmpresa
{
    /**
     * Chave de config/mwart.php → flag GrowthBook por empresa.
     *
     * @var array<string, string>
     */
    private static array $flagPorTela = [
        'repair_index' => 'useV2RepairIndex',
        'repair_show' => 'useV2RepairShow',
        'repair_status_index' => 'useV2RepairStatus',
        'repair_device_models_index' => 'useV2RepairDeviceModels',
        'repair_device_models_create' => 'useV2RepairDeviceModelsCreate',
        'repair_device_models_edit' => 'useV2RepairDeviceModelsEdit',
        'repair_dashboard_index' => 'useV2RepairDashboard',
        'repair_settings_index' => 'useV2RepairSettings',
        'repair_job_sheet_index' => 'useV2RepairJobSheet',
        'repair_job_sheet_create' => 'useV2RepairJobSheetCreate',
        'repair_job_sheet_show' => 'useV2RepairJobSheetShow',
        'repair_job_sheet_edit' => 'useV2RepairJobSheetEdit',
        'repair_job_sheet_add_parts' => 'useV2RepairJobSheetAddParts',
    ];

    protected function mwartEnabled(string $key, int $business_id): bool
    {
        if (config("mwart.{$key}.enabled")) {
            $beta = (array) config("mwart.{$key}.business_ids", []);

            if (empty($beta) || in_array($business_id, $beta, true)) {
                return true;
            }
        }

        $flag = self::$flagPorTela[$key] ?? null;

        if ($flag === null || $business_id <= 0) {
            return false;
        }

        return app(FeatureFlagService::class)->isOn($flag, ['business_id' => $business_id]);
    }

    /** Nome da flag GrowthBook de uma tela (null se a chave não for do Repair). */
    public static function flagDaTelaRepair(string $key): ?string
    {
        return self::$flagPorTela[$key] ?? null;
    }
}
