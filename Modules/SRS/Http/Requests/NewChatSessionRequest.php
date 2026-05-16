<?php

declare(strict_types=1);

namespace Modules\SRS\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * D8.c Security Wave 17 — FormRequest pra ChatController::newSession.
 *
 * Endpoint trivial (gera novo session_id) mas FormRequest dedicado padroniza
 * authorize() + telemetria futura (rate limit per-user etc).
 *
 * Multi-tenant Tier 0: session_id é stateless; business_id deduzido da sessão
 * em Read seguinte.
 *
 * @see Modules\SRS\Http\Controllers\ChatController::newSession
 */
class NewChatSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        // Endpoint sem payload — placeholder pra extensão futura
        // (ex: business_context, persona, language preference).
        return [];
    }
}
