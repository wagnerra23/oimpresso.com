<?php

declare(strict_types=1);

namespace Modules\Jana\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * StorePeriodoRequest — criação de MetaPeriodo via POST /jana/metas/{metaId}/periodos.
 *
 * D8.c (Wave 17 governance v3) — substitui `$request->validate([...])` inline
 * do PeriodosController@store, padroniza mensagens PT-BR e endurece regras:
 *  - tipo_periodo whitelist explicita (fail-secure)
 *  - data_fim after_or_equal data_ini (sanity contra periodos invertidos)
 *  - trajetoria nullable mas com whitelist (default linear no service)
 *
 * Multi-tenant Tier 0 (ADR 0093): autorização final no controller checa
 * `Meta::findOrFail($metaId)` ja escopado por business via HasBusinessScope.
 */
class StorePeriodoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'tipo_periodo' => ['required', Rule::in(['mes', 'trim', 'ano', 'custom'])],
            'data_ini'     => ['required', 'date'],
            'data_fim'     => ['required', 'date', 'after_or_equal:data_ini'],
            'valor_alvo'   => ['required', 'numeric', 'min:0'],
            'trajetoria'   => ['nullable', Rule::in(['linear', 'sazonal', 'exponencial', 'manual'])],
        ];
    }

    public function messages(): array
    {
        return [
            'tipo_periodo.in'         => 'Tipo de período inválido. Use mes, trim, ano ou custom.',
            'data_ini.required'       => 'Informe a data inicial.',
            'data_fim.required'       => 'Informe a data final.',
            'data_fim.after_or_equal' => 'A data final deve ser igual ou posterior à inicial.',
            'valor_alvo.required'     => 'Informe o valor alvo.',
            'valor_alvo.min'          => 'O valor alvo deve ser positivo.',
            'trajetoria.in'           => 'Trajetória inválida. Use linear, sazonal, exponencial ou manual.',
        ];
    }
}
