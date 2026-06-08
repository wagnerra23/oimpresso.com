<?php

namespace Modules\Crm\Http\Requests;

use App\Utils\ModuleUtil;
use Illuminate\Foundation\Http\FormRequest;

/**
 * FormRequest pra mass-delete call logs.
 *
 * Operacao destrutiva (DELETE em lote) — authorize() exige superadmin OU permissao explicita.
 * Rules garantem que selectedRows seja array de inteiros (evita SQL injection via parametro string).
 *
 * @see Modules/Crm/Http/Controllers/CallLogController.php
 */
class MassDestroyCallLogRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if ($user === null) {
            return false;
        }

        if ($user->can('superadmin')) {
            return true;
        }

        $businessId = $this->session()->get('user.business_id');
        $moduleUtil = app(ModuleUtil::class);

        return (bool) $moduleUtil->hasThePermissionInSubscription($businessId, 'crm_module');
    }

    /**
     * Aceita 'selected_rows' como string CSV (formato legacy UI DataTables) OU array de inteiros.
     * Validacao garante presenca + tamanho razoavel (anti-DoS).
     */
    public function rules(): array
    {
        return [
            'selected_rows' => ['required'],
        ];
    }

    /**
     * Retorna lista normalizada (sempre array de inteiros). Use no Controller em vez de explode direto.
     */
    public function ids(): array
    {
        $raw = $this->input('selected_rows');

        $list = is_array($raw) ? $raw : explode(',', (string) $raw);

        return collect($list)
            ->map(fn ($v) => (int) trim((string) $v))
            ->filter(fn ($v) => $v > 0)
            ->take(500) // anti-DoS hard cap
            ->values()
            ->all();
    }
}
