<?php

declare(strict_types=1);

namespace Modules\AssetManagement\Http\Requests;

use App\Utils\ModuleUtil;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Wave 14 D8 Security — FormRequest novo pra AssetMaintenance@store.
 *
 * Complementa Wave 10 (StoreAssetRequest + UpdateAssetRequest) + Wave 14
 * (StoreAssetAllocationRequest). AssetMaintenance store legacy aceitava
 * $request->only('status', 'priority', 'asset_id', 'maintenance_note') sem validation
 * + upload de attachments sem MIME check.
 *
 * Esta classe introduz:
 *  - status enum (open/in_progress/completed) — back-compat aceita qualquer string nao-vazia
 *  - priority enum (low/medium/high)
 *  - attachments MIME whitelist (imagens + PDF + Office) — previne RCE upload PHP/JS
 */
class StoreAssetMaintenanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if ($user === null) {
            return false;
        }

        $businessId = $this->session()->get('user.business_id');

        if (empty($businessId)) {
            return false;
        }

        if ($user->can('superadmin')) {
            return true;
        }

        // Mesma logica do Controller@store: permission OR subscription module.
        $hasViewPerm = $user->can('asset.view_all_maintenance') && $user->can('asset.view_own_maintenance');

        if ($hasViewPerm) {
            return true;
        }

        $moduleUtil = app(ModuleUtil::class);

        return $moduleUtil->hasThePermissionInSubscription($businessId, 'assetmanagement_module');
    }

    public function rules(): array
    {
        return [
            'asset_id' => ['required', 'integer', 'exists:assets,id'],
            'status' => [
                'required',
                'string',
                Rule::in(['open', 'in_progress', 'completed', 'cancelled']),
            ],
            'priority' => [
                'nullable',
                'string',
                Rule::in(['low', 'medium', 'high', 'urgent']),
            ],
            'maintenance_note' => ['nullable', 'string', 'max:5000'],
            // Wave 14 D8: attachments com MIME whitelist anti-RCE.
            'attachments' => ['nullable', 'array', 'max:10'],
            'attachments.*' => [
                'file',
                'max:51200', // 50 MB
                'mimes:jpg,jpeg,png,gif,webp,pdf,xlsx,xls,docx,doc,txt',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'attachments.*.mimes' => 'Tipo de anexo nao permitido. Aceitos: imagens, PDF, planilhas, documentos Office.',
            'attachments.*.max' => 'Cada anexo deve ter ate 50 MB.',
            'attachments.max' => 'Maximo 10 anexos por manutencao.',
        ];
    }
}
