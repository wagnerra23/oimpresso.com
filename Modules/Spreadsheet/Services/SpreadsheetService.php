<?php

declare(strict_types=1);

namespace Modules\Spreadsheet\Services;

use App\User;
use App\Util\OtelHelper;
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
        // D9.a OTel (Wave 17): span envolve transaction INSERT.
        // Zero-cost se otel.enabled=false.
        return OtelHelper::spanBiz('spreadsheet.create', function () use ($input, $bizId, $userId) {
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
        }, [
            'module'    => 'Spreadsheet',
            'user_id'   => $userId,
            'folder_id' => $input['folder_id'] ?? 0,
        ]);
    }

    /**
     * Atualiza spreadsheet existente. Retorna true se persistiu.
     *
     * Mantém escopo multi-tenant via where business_id obrigatório.
     */
    public function updateSpreadsheet(int $id, array $input, int $bizId): bool
    {
        return OtelHelper::spanBiz('spreadsheet.update', function () use ($id, $input, $bizId) {
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
        }, [
            'module'         => 'Spreadsheet',
            'spreadsheet_id' => $id,
        ]);
    }

    /**
     * Hard-delete da spreadsheet escopo business_id + created_by.
     */
    public function deleteSpreadsheet(int $id, int $bizId, int $userId): bool
    {
        return OtelHelper::spanBiz('spreadsheet.delete', function () use ($id, $bizId, $userId) {
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
        }, [
            'module'         => 'Spreadsheet',
            'spreadsheet_id' => $id,
            'user_id'        => $userId,
        ]);
    }

    /**
     * Resolve users que devem receber notificação de share (extraído pra reutilização).
     */
    public function resolveNotifyableUsers(int $bizId, array $sharedIds): \Illuminate\Database\Eloquent\Collection
    {
        return OtelHelper::spanBiz('spreadsheet.resolve_notifyable_users', function () use ($bizId, $sharedIds) {
            return User::where('business_id', $bizId)->find($sharedIds);
        }, [
            'module'           => 'Spreadsheet',
            'shared_ids_count' => count($sharedIds),
        ]);
    }

    /**
     * Lista paginada de spreadsheets visíveis ao usuário no tenant.
     *
     * Wave 18 D4: extração canônica do critério de "minhas + compartilhadas comigo",
     * antes inlined no Controller (acoplava UI a query lógica).
     *
     * Multi-tenant Tier 0: bizId obrigatório; query principal sempre filtra
     * `business_id` explicitamente (Spreadsheet NÃO tem global scope porque pre-data
     * convenção ADR 0093 — back-compat preserva manual filter via Controller/Service).
     *
     * @param  int       $bizId    business_id ativo (Tier 0)
     * @param  int       $userId   User logado
     * @param  int|null  $folderId Folder ID (null = root)
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function listForUser(int $bizId, int $userId, ?int $folderId = null, int $perPage = 25): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        return OtelHelper::spanBiz('spreadsheet.list_for_user', function () use ($bizId, $userId, $folderId, $perPage) {
            $query = Spreadsheet::where('business_id', $bizId)
                ->where(function ($q) use ($userId) {
                    // Próprias planilhas OU compartilhadas com o user
                    $q->where('created_by', $userId)
                        ->orWhereHas('shares', function ($sq) use ($userId) {
                            $sq->where('shared_with', 'user')->where('shared_id', $userId);
                        });
                });

            if ($folderId !== null) {
                $query->where('folder_id', $folderId);
            } else {
                $query->whereNull('folder_id');
            }

            return $query->orderByDesc('updated_at')->paginate($perPage);
        }, [
            'module'    => 'Spreadsheet',
            'user_id'   => $userId,
            'folder_id' => $folderId ?? 0,
            'per_page'  => $perPage,
        ]);
    }

    /**
     * Get-by-id com checagem ACL multi-tenant + share.
     *
     * Wave 18 D4: 1 ponto de truth pra "posso abrir essa spreadsheet?".
     * Antes espalhado em find() + where business_id em controllers diferentes.
     *
     * Retorna `null` se: id não existe, business_id diferente, OU user
     * não é criador nem tem share. Frontend trata 404 uniformemente.
     *
     * @param  int  $id      Spreadsheet ID
     * @param  int  $bizId   business_id ativo
     * @param  int  $userId  User logado
     */
    public function getForUser(int $id, int $bizId, int $userId): ?Spreadsheet
    {
        return OtelHelper::spanBiz('spreadsheet.get_for_user', function () use ($id, $bizId, $userId) {
            return Spreadsheet::where('business_id', $bizId)
                ->where('id', $id)
                ->where(function ($q) use ($userId) {
                    $q->where('created_by', $userId)
                        ->orWhereHas('shares', function ($sq) use ($userId) {
                            $sq->where('shared_with', 'user')->where('shared_id', $userId);
                        });
                })
                ->first();
        }, [
            'module'         => 'Spreadsheet',
            'spreadsheet_id' => $id,
            'user_id'        => $userId,
        ]);
    }
}
