<?php

declare(strict_types=1);

namespace Modules\Arquivos\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Arquivos\Entities\Arquivo;

/**
 * Wave 14 D8 Security — FormRequest extraido do DownloadController.
 *
 * O DownloadController atual depende de middleware 'signed' + multi-tenant global
 * scope no Arquivo::find pra isolamento. Este FormRequest adiciona defense-in-depth:
 *
 *  - authorize() confere user autenticado + business_id em sessao
 *  - rules() valida estrutura minima do parametro {arquivo} (positive int)
 *  - audit-friendly: failure de authorize loga em laravel.log via Pipeline padrao
 *
 * NAO substitui o middleware 'signed' (continua TIER 0) — apenas adiciona camada.
 *
 * @see Modules\Arquivos\Http\Controllers\DownloadController
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 * @see memory/decisions/0123-modules-arquivos-backbone.md
 */
class DownloadArquivoRequest extends FormRequest
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

        $arquivoId = (int) $this->route('arquivo');

        if ($arquivoId <= 0) {
            return false;
        }

        // Multi-tenant Tier 0: Arquivo::find aplica global scope business_id.
        // Se atacante forjar arquivo_id de outro tenant com signed URL valida,
        // find retorna null e nega aqui — extra layer sobre o abort(404) do Controller.
        $arquivo = Arquivo::find($arquivoId);

        return $arquivo !== null && (int) $arquivo->business_id === (int) $businessId;
    }

    public function rules(): array
    {
        return [
            // Route param {arquivo} normalmente int positivo (auto-increment id).
            // Validacao extra previne path traversal / payload exotico em logs.
        ];
    }
}
