<?php

declare(strict_types=1);

namespace Modules\Spreadsheet\Services;

use App\User;
use App\Utils\ModuleUtil;
use Illuminate\Support\Facades\DB;
use Modules\Spreadsheet\Entities\Spreadsheet;
use Modules\Spreadsheet\Entities\SpreadsheetShare;

/**
 * SpreadsheetService — Service layer canônica do módulo Spreadsheet (Wave 16 D4 architecture).
 *
 * Extrai lógica do `SpreadsheetController` (567 linhas → controller fino + service testável).
 * Métodos principais expostos:
 *   - createSpreadsheet(array $input, int $bizId, int $userId): Spreadsheet
 *   - updateSpreadsheet(int $id, array $input, int $bizId): bool
 *   - deleteSpreadsheet(int $id, int $bizId, int $userId): bool
 *
 * Multi-tenant Tier 0 ([ADR 0093]): bizId é argumento obrigatório, evitando dependência
 * implícita de `session()` no service layer (que NÃO funciona em jobs/CLI).
 *
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 * @see memory/decisions/0155-module-grade-v3.md D4 architecture
 */
class SpreadsheetService
{
    public function __construct(
        protected ModuleUtil $moduleUtil
    ) {}

    /**
     * Cria nova spreadsheet com transação + business_id Tier 0.
     *
     * @param  array  $input    Dados (name, sheet_data, folder_id)
     * @param  int    $bizId    business_id (Tier 0 obrigatório)
     * @param  int    $userId   User criador
     * @return Spreadsheet      Modelo criado (ou null se falha)
     */
    public function createSpreadsheet(array $input, int $bizId, int $userId): ?Spreadsheet
    {
        $payload = [
            'name'        => ! empty($input['name']) ? $input['name'] : 'My Spreadsheet',
            'sheet_data'  => $input['sheet_data'] ?? null,
            'business_id' => $bizId,
            'created_by'  => $userId,
            'folder_id'   => $input['folder_id'] ?? null,
        ];

        DB::beginTransaction();
        try {
            $sheet = Spreadsheet::create($payload);
            DB::commit();

            return $sheet;
        } catch (\Throwable $e) {
            DB::rollBack();
            \Log::emergency('SpreadsheetService::createSpreadsheet ' . $e->getMessage(), [
                'business_id' => $bizId,
                'user_id'     => $userId,
            ]);

            return null;
        }
    }

    /**
     * Atualiza spreadsheet existente. Retorna true se persistiu.
     *
     * Mantém escopo multi-tenant via where business_id obrigatório.
     */
    public function updateSpreadsheet(int $id, array $input, int $bizId): bool
    {
        $payload = [
            'name'       => ! empty($input['name']) ? $input['name'] : 'My Spreadsheet',
            'sheet_data' => isset($input['sheet_data']) ? json_encode($input['sheet_data']) : null,
        ];

        DB::beginTransaction();
        try {
            $affected = Spreadsheet::where('business_id', $bizId)
                ->where('id', $id)
                ->update($payload);

            DB::commit();

            return $affected > 0;
        } catch (\Throwable $e) {
            DB::rollBack();
            \Log::emergency('SpreadsheetService::updateSpreadsheet ' . $e->getMessage(), [
                'business_id' => $bizId,
                'id'          => $id,
            ]);

            return false;
        }
    }

    /**
     * Hard-delete da spreadsheet escopo business_id + created_by.
     */
    public function deleteSpreadsheet(int $id, int $bizId, int $userId): bool
    {
        DB::beginTransaction();
        try {
            $affected = Spreadsheet::where('business_id', $bizId)
                ->where('created_by', $userId)
                ->where('id', $id)
                ->delete();

            DB::commit();

            return $affected > 0;
        } catch (\Throwable $e) {
            DB::rollBack();
            \Log::emergency('SpreadsheetService::deleteSpreadsheet ' . $e->getMessage(), [
                'business_id' => $bizId,
                'id'          => $id,
                'user_id'     => $userId,
            ]);

            return false;
        }
    }

    /**
     * Resolve users que devem receber notificação de share (extraído pra reutilização).
     */
    public function resolveNotifyableUsers(int $bizId, array $sharedIds): \Illuminate\Database\Eloquent\Collection
    {
        return User::where('business_id', $bizId)->find($sharedIds);
    }
}
