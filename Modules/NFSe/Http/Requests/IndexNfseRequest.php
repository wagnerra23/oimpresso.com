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
 *   - de/ate: competência (mês, AAAA-MM). Antes de 2026-11-16 a regra pedia Y-m-d e a
 *     tela mandava AAAA-MM: todo filtro de competência voltava com erro de validação.
 *   - q: busca livre — limite chars pra evitar LIKE %x% massivo.
 *
 * RBAC `nfse.view` continua no Controller (gate diferente do FormRequest authorize).
 *
 * @see Modules\NFSe\Http\Controllers\NfseController::index
 */
class IndexNfseRequest extends FormRequest
{
    /** Vocabulário único de status — o mesmo do StatusBadge kind="nfse" e do filtro da tela. */
    public const STATUS = ['rascunho', 'processando', 'emitida', 'cancelada', 'erro'];

    public function authorize(): bool
    {
        // RBAC `nfse.view` checado via $this->authorize() no Controller.
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            // Os 5 status que a coluna `nfse_emissoes.status` aceita (enum da migration do
            // módulo). 'pendente', 'autorizada' e 'rejeitada' saíram em 2026-11-16: nada grava
            // esses valores, e o filtro que os pedia voltava sempre vazio.
            'status' => ['nullable', Rule::in(self::STATUS)],
            // Competência é mês. A tela manda o valor do <input type="month">: AAAA-MM.
            'de'  => ['nullable', 'date_format:Y-m'],
            'ate' => ['nullable', 'date_format:Y-m', 'after_or_equal:de'],
            'q'   => ['nullable', 'string', 'max:120'],
        ];
    }

    public function messages(): array
    {
        return [
            'de.date_format'  => 'Competência inicial deve estar no formato AAAA-MM.',
            'ate.date_format' => 'Competência final deve estar no formato AAAA-MM.',
            'ate.after_or_equal' => 'Competência final deve ser igual ou posterior à inicial.',
            'q.max'           => 'Busca limitada a 120 caracteres.',
        ];
    }
}
