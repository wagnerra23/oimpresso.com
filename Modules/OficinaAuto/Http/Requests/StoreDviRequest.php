<?php

declare(strict_types=1);

namespace Modules\OficinaAuto\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\OficinaAuto\Entities\OaInspectionItem;

/**
 * D8 Security — FormRequest pra criação de item DVI.
 *
 * Wave 3 OficinaAuto US-OFICINA-035. authorize() casa com Policy update(OS) —
 * só quem pode editar a OS pode adicionar itens DVI nela.
 *
 * Multi-tenant Tier 0 ([ADR 0093]): business_id derivado da sessão pelo Model creating()
 * hook — request nunca pode injetar (proteção mass-assignment cross-tenant).
 *
 * @see Modules\OficinaAuto\Http\Controllers\DviInspectionController::store
 * @see memory/requisitos/OficinaAuto/SPEC.md US-OFICINA-035
 */
class StoreDviRequest extends FormRequest
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
            'categoria'         => ['required', 'string', Rule::in(OaInspectionItem::CATEGORIAS)],
            'descricao'         => ['required', 'string', 'max:150'],
            'severity'          => ['required', 'string', Rule::in(OaInspectionItem::SEVERITIES_VALIDAS)],
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
            'categoria.in'  => 'Categoria inválida. Permitidas: ' . implode(', ', OaInspectionItem::CATEGORIAS) . '.',
            'severity.in'   => 'Severidade inválida. Permitidas: ok, atencao, critico.',
            'descricao.required' => 'Descrição obrigatória.',
        ];
    }
}
