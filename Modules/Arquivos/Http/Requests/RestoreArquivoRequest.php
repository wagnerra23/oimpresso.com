<?php

declare(strict_types=1);

namespace Modules\Arquivos\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Arquivos\Entities\Arquivo;

/**
 * FormRequest pra RESTORE de Arquivo soft-deleted.
 *
 * Wave 18 D8 SATURATION — par de DeleteArquivoRequest.
 *
 * Restaurar arquivo é operação sensível (recupera dados que foram
 * marcados como deletados — UX clara). Authorize exige permissão
 * elevada quando configurado.
 */
class RestoreArquivoRequest extends FormRequest
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

        // Thread 03 (PR-7): o arquivo tem que ser DO business da sessão. `withTrashed` porque
        // só se restaura o que está excluído; o global scope do model segue valendo.
        $arquivoId = (int) ($this->route('arquivo') ?? $this->input('arquivo_id', 0));
        $arquivo = $arquivoId > 0 ? Arquivo::withTrashed()->find($arquivoId) : null;
        if ($arquivo === null || (int) $arquivo->business_id !== (int) $businessId) {
            return false;
        }

        // Restore é operação de governança — `superadmin` sempre permitido.
        // Caso queira permission granular, adicione `arquivos.restore`.
        if ($user->can('superadmin')) {
            return true;
        }

        return method_exists($user, 'can') ? $user->can('arquivos.restore') : true;
    }

    public function rules(): array
    {
        return [
            // Justificativa registrada em audit log
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ];
    }
}
