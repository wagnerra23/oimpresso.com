<?php

declare(strict_types=1);

namespace Modules\ProjectMgmt\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * D8 Security — Wave 17 saturação (97% module-grade).
 *
 * FormRequest pra POST /ads/admin/projects (Admin\ProjectsController@store).
 * Endpoint protegido por middleware `auth` + permission `copiloto.mcp.usage.all`
 * (Controller authorize). Esta camada valida fail-fast estrutura do payload.
 *
 * Multi-tenant Tier 0 (ADR 0093): `business_id` é resolvido da session pelo
 * Controller (não vem no payload) — não validado aqui pra evitar bypass via
 * input forjado. Defense-in-depth: Service exige $businessId no constructor.
 *
 * @see Modules\ProjectMgmt\Http\Controllers\Admin\ProjectsController::store
 * @see Modules\ProjectMgmt\Services\ProjectService::create
 */
class StoreProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Permission `copiloto.mcp.usage.all` enforced upstream pelo middleware
        // do Controller. Aqui só estrutura do payload.
        return true;
    }

    public function rules(): array
    {
        return [
            'nome'           => ['required', 'string', 'max:200'],
            'objetivo_macro' => ['required', 'string', 'max:2000'],
            'codigo'         => ['sometimes', 'string', 'max:30', 'unique:mcp_projects,codigo'],
            'owner'          => ['sometimes', 'string', 'max:50'],
        ];
    }

    public function messages(): array
    {
        return [
            'nome.required'           => 'Campo nome é obrigatório.',
            'nome.max'                => 'Nome deve ter no máximo 200 caracteres.',
            'objetivo_macro.required' => 'Campo objetivo_macro é obrigatório.',
            'objetivo_macro.max'      => 'objetivo_macro deve ter no máximo 2000 caracteres.',
            'codigo.unique'           => 'Já existe project com esse código (mcp_projects.codigo é UNIQUE).',
            'codigo.max'              => 'Código deve ter no máximo 30 caracteres (formato PROJ-YYYYMM-NNN).',
        ];
    }
}
