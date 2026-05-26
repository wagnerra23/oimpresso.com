<?php

declare(strict_types=1);

namespace Modules\OficinaAuto\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\OficinaAuto\Entities\OaInspectionItem;

/**
 * D8 Security — FormRequest pra update de item DVI.
 *
 * Wave 3 OficinaAuto US-OFICINA-035. Diff de StoreDviRequest: usa `sometimes` —
 * permite PATCH parcial (UI atualiza só severity sem reenviar tudo).
 *
 * @see Modules\OficinaAuto\Http\Controllers\DviInspectionController::update
 */
class UpdateDviRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if ($user === null) {
            return false;
        }

        return $user->can('superadmin') || $user->can('oficinaauto.service_order.update');
    }

    public function rules(): array
    {
        return [
            'categoria'         => ['sometimes', 'required', 'string', Rule::in(OaInspectionItem::CATEGORIAS)],
            'descricao'         => ['sometimes', 'required', 'string', 'max:150'],
            'severity'          => ['sometimes', 'required', 'string', Rule::in(OaInspectionItem::SEVERITIES_VALIDAS)],
            'recomendacao'      => ['nullable', 'string', 'max:255'],
            'valor_recomendado' => ['nullable', 'numeric', 'min:0'],
            'metadata'          => ['nullable', 'array'],
            'photo_url'         => ['nullable', 'string', 'max:500'],
            'sort_order'        => ['nullable', 'integer', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'categoria.in' => 'Categoria inválida. Permitidas: ' . implode(', ', OaInspectionItem::CATEGORIAS) . '.',
            'severity.in'  => 'Severidade inválida. Permitidas: ok, atencao, critico.',
        ];
    }
}
