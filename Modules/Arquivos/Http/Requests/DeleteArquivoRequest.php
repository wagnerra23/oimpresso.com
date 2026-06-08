<?php

declare(strict_types=1);

namespace Modules\Arquivos\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * FormRequest pra DELETE de Arquivo (soft-delete pela trait SoftDeletes).
 *
 * Wave 18 D8 SATURATION — fecha CRUD com Upload/Download/Delete/Restore.
 *
 * **Tier 0 multi-tenant** ({@see ADR 0093}): business_id em sessão obrigatório.
 * Controller resolve `arquivo->business_id == session(business_id)` antes
 * de chamar service — authorize() apenas gate sessão presente + scope LGPD.
 *
 * **LGPD**: soft-delete preserva histórico (audit log Spatie); purge real
 * via `arquivos:retention-cleanup` command (não via UI).
 */
class DeleteArquivoRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        if ($user === null) {
            return false;
        }

        $businessId = $this->session()->get('user.business_id');
        if (empty($businessId)) {
            return false; // Tier 0 — sem business_id, NUNCA permite delete
        }

        return true;
    }

    public function rules(): array
    {
        return [
            // Razão LGPD recomendada (audit log) — opcional mas registrada se vier
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }
}
