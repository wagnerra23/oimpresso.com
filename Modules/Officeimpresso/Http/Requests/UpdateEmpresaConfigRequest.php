<?php

namespace Modules\Officeimpresso\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Atualizacao de configuracao da empresa officeimpresso (bridge desktop legacy) — D8.c Security.
 *
 * Wave 27 polish (2026-05-17) — par dos FormRequests Store/Update/BulkRevoke
 * de LicencaComputador. Valida payload de superadmin atualizando configs
 * da empresa que afetam comportamento do executavel Delphi:
 *
 *   - caminho_banco_servidor: path local Delphi conecta (whitelist por regex)
 *   - versao_obrigatoria / versao_disponivel: gating de upgrade do exe
 *   - officeimpresso_numerodemaquinas: limite hard do contrato (anti-fraud)
 *
 * Multi-tenant Tier 0 ({@see ADR 0093}): Controller resolve businessId via
 * session/route binding; este FormRequest NÃO valida ownership — só shape.
 * Defesa-em-profundidade vs IDOR no Controller.
 *
 * Lei Software 9.609/98: alterações registradas via LicencaAuditService
 * (audit append-only retention 5y).
 */
class UpdateEmpresaConfigRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Auth checagem fina via policy (superadmin) acontece no Controller.
        // FormRequest valida apenas que usuário está autenticado (gate baseline).
        return $this->user() !== null;
    }

    /**
     * Regras tolerantes (sometimes) — PATCH-friendly. Wagner pode atualizar
     * só 1 campo (ex: liberar nova versão) sem reenviar payload completo.
     *
     * Validações específicas:
     *  - caminho_banco_servidor: anti path traversal (sem `..` ou `~`)
     *  - versao_*: regex semver-like X.Y.Z (até 20 chars — formato legado WR)
     *  - numerodemaquinas: int positivo até 9999 (contrato anti-fraud)
     */
    public function rules(): array
    {
        return [
            'caminho_banco_servidor' => [
                'sometimes',
                'string',
                'max:500',
                'not_regex:/(\\.\\.|~)/', // anti path traversal
            ],
            'versao_obrigatoria' => [
                'sometimes',
                'nullable',
                'string',
                'max:20',
                'regex:/^\\d+(\\.\\d+){0,3}$/', // X / X.Y / X.Y.Z / X.Y.Z.W
            ],
            'versao_disponivel' => [
                'sometimes',
                'nullable',
                'string',
                'max:20',
                'regex:/^\\d+(\\.\\d+){0,3}$/',
            ],
            'officeimpresso_numerodemaquinas' => [
                'sometimes',
                'integer',
                'min:1',
                'max:9999',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'caminho_banco_servidor.not_regex' => 'O caminho do banco não pode conter "..", "~" ou outros padrões de traversal.',
            'caminho_banco_servidor.max'       => 'O caminho do banco não pode passar de 500 caracteres.',
            'versao_obrigatoria.regex'         => 'A versão obrigatória deve seguir o formato X, X.Y, X.Y.Z ou X.Y.Z.W (apenas dígitos e pontos).',
            'versao_obrigatoria.max'           => 'A versão obrigatória não pode passar de 20 caracteres.',
            'versao_disponivel.regex'          => 'A versão disponível deve seguir o formato X, X.Y, X.Y.Z ou X.Y.Z.W (apenas dígitos e pontos).',
            'versao_disponivel.max'            => 'A versão disponível não pode passar de 20 caracteres.',
            'officeimpresso_numerodemaquinas.min' => 'O número de máquinas deve ser ao menos 1.',
            'officeimpresso_numerodemaquinas.max' => 'O número de máquinas não pode exceder 9999 (limite anti-fraud).',
        ];
    }
}
