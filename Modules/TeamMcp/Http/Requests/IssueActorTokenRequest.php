<?php

namespace Modules\TeamMcp\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * FormRequest pra emissao de token MCP pra um user (TeamController@gerarToken).
 *
 * Endpoint POST /team-mcp/team/{user}/token — superadmin only.
 *
 * Responsabilidade DESTE FormRequest:
 *   - Validar 'note' (rotulo opcional do token — ate 120 chars)
 *
 * Responsabilidade NAO COBERTA AQUI (preservada upstream — Tier 0 segredo):
 *   - Permission gate `copiloto.mcp.usage.all` — middleware no Controller construtor
 *   - Token NUNCA logado / NUNCA em error message — gerado por McpToken::gerar()
 *     que devolve [$model, $raw], raw apenas no response 1x (response().json([
 *     'aviso' => 'COPIE AGORA — nao sera mostrado de novo.']))
 *   - Hash sha256 gravado, raw descartado apos response — preserva contrato
 *
 * Wagner-only segurança IRREVOGÁVEL (ADR 0081 team-internal):
 *   - Authorize() so libera quem ja passou middleware permission gate
 *   - Quem chama: Wagner (superadmin) atraves de /copiloto/admin/team
 *
 * D8.c Security — Wave S Batch 2.
 *
 * @see Modules\TeamMcp\Http\Controllers\TeamController::gerarToken
 * @see Modules\Jana\Entities\Mcp\McpToken::gerar (helper canonico)
 * @see memory/decisions/0081-team-internal-modulos.md
 */
class IssueActorTokenRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Permission gate (`copiloto.mcp.usage.all`) ja foi aplicada pelo
        // middleware do Controller construtor. Aqui so confirmamos user logado.
        // Em fila ou request fora de sessao, retorna false (fail-secure).
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'note' => ['nullable', 'string', 'max:120'],
        ];
    }

    public function messages(): array
    {
        return [
            'note.max' => 'Rotulo do token muito longo (max 120 caracteres).',
            'note.string' => 'Rotulo do token deve ser texto.',
        ];
    }

    /**
     * Trim do note antes de validar — evita name=' '.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('note') && is_string($this->input('note'))) {
            $this->merge(['note' => trim($this->input('note'))]);
        }
    }
}
