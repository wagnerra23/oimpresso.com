<?php

declare(strict_types=1);

namespace Modules\Arquivos\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Arquivos\Entities\Arquivo;

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

        // Thread 03 (PR-7): o arquivo tem que ser DO business da sessão — mesma defesa da
        // ReclassifyArquivoRequest. `find()` sem trashed: excluir o já excluído não existe.
        $arquivoId = (int) ($this->route('arquivo') ?? $this->input('arquivo_id', 0));
        $arquivo = $arquivoId > 0 ? Arquivo::find($arquivoId) : null;

        return $arquivo !== null && (int) $arquivo->business_id === (int) $businessId;
    }

    public function rules(): array
    {
        return [
            // Razão LGPD (audit log). Obrigatória desde a thread 03 (PR-7): a tela grava o
            // motivo na linha `soft_delete` da trilha — mesmo mínimo do classificar.
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ];
    }
}
