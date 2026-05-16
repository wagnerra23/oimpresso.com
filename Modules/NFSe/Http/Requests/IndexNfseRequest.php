<?php

declare(strict_types=1);

namespace Modules\NFSe\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * D8.c Security Wave 17 — FormRequest pra NfseController::index (US-NFSE-008).
 *
 * Filtros de listagem. Sem validação:
 *   - status arbitrário (SQL injection-like via where('status', $unsafe)) — mitigado por
 *     PDO bind mas pode quebrar índices ou retornar inesperado.
 *   - de/ate: datas livres caem em Carbon::parse() que aceita formatos exóticos
 *     ("now -100 years") podendo causar tempo de query alto.
 *   - q: busca livre — limite chars pra evitar LIKE %x% massivo.
 *
 * RBAC `nfse.view` continua no Controller (gate diferente do FormRequest authorize).
 *
 * @see Modules\NFSe\Http\Controllers\NfseController::index
 */
class IndexNfseRequest extends FormRequest
{
    public function authorize(): bool
    {
        // RBAC `nfse.view` checado via $this->authorize() no Controller.
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'status' => ['nullable', Rule::in([
                'rascunho', 'pendente', 'processando',
                'emitida', 'autorizada', 'rejeitada',
                'cancelada', 'erro',
            ])],
            'de'  => ['nullable', 'date_format:Y-m-d'],
            'ate' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:de'],
            'q'   => ['nullable', 'string', 'max:120'],
        ];
    }

    public function messages(): array
    {
        return [
            'de.date_format'  => 'Data inicial deve estar em formato Y-m-d.',
            'ate.date_format' => 'Data final deve estar em formato Y-m-d.',
            'ate.after_or_equal' => 'Data final deve ser igual ou posterior à inicial.',
            'q.max'           => 'Busca limitada a 120 caracteres.',
        ];
    }
}
