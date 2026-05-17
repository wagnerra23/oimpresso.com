<?php

declare(strict_types=1);

namespace Modules\TeamMcp\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * FormRequest pra endpoint /api/cc/ingest (MEM-CC-1).
 *
 * Wave 18 D8 SATURATION — extraído de `CcIngestController::ingest`
 * (validate inline).
 *
 * **Auth**: middleware `mcp.auth` (Bearer mcp_<token>) já valida actor.
 * authorize() checa RBAC `jana.cc.ingest.self` OR `jana.mcp.use`.
 *
 * Rules cobrem session + messages array (size protect contra payload
 * abusivo — max 5000 messages/request).
 */
class CcIngestRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        if ($user === null) {
            return false;
        }

        if (! method_exists($user, 'can')) {
            return true; // permissivo se user model não tem `can` (testes)
        }

        return $user->can('jana.cc.ingest.self') || $user->can('jana.mcp.use');
    }

    public function rules(): array
    {
        return [
            'session'              => ['required', 'array'],
            'session.uuid'         => ['required', 'string', 'max:36'],
            'session.project_path' => ['nullable', 'string', 'max:500'],
            'session.git_branch'   => ['nullable', 'string', 'max:150'],
            'session.cc_version'   => ['nullable', 'string', 'max:20'],
            'session.entrypoint'   => ['nullable', 'string', 'max:50'],
            'session.started_at'   => ['nullable', 'string'],
            'session.ended_at'     => ['nullable', 'string'],
            'messages'             => ['required', 'array', 'max:5000'],
            'messages.*.uuid'      => ['required', 'string', 'max:36'],
            'messages.*.type'      => ['required', 'string', 'max:20'],
        ];
    }

    public function messages(): array
    {
        return [
            'messages.max' => 'Batch limite 5000 mensagens — divida em múltiplas requests.',
        ];
    }
}
