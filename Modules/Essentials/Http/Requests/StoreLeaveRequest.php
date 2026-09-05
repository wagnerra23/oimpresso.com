<?php

declare(strict_types=1);

namespace Modules\Essentials\Http\Requests;

use App\User;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Essentials\Entities\EssentialsLeaveType;

/**
 * HRM-O6 PR-2 — validação de `EssentialsLeaveController::store` (achado A2).
 *
 * O `store()` recebia `Request` cru e fazia `$request->only([...])`: fim antes do
 * início gravava, motivo vazio gravava, e `employees[]` entrava direto no `foreach`
 * sem conferir o tenant. Este FormRequest fecha os três.
 *
 * ## Datas — por que não `date|after_or_equal`
 *
 * O controller grava via `ModuleUtil::uf_date()`, que parseia com
 * `session('business.date_format')` (default do schema: `m/d/Y`). A regra `date` do
 * Laravel usa `strtotime`, que lê `10/09/2026` como m/d/Y **sempre** — então validar
 * com ela criaria uma SEGUNDA convenção de data, divergente da que grava. Aqui a
 * validação chama **o mesmo conversor**: o que passa na validação é exatamente o que
 * o controller consegue converter.
 *
 * ## Tier 0 (ADR 0093)
 *
 * `essentials_leave_type_id` e `employees.*` são ids que vêm crus do body. O global
 * scope `ScopeByBusiness` filtra SELECT, **não impede INSERT** — sem o gate abaixo,
 * um POST com id de outro negócio cria licença cruzando tenant. É o mesmo gate
 * explícito que `SalesTargetController` já usa (`User::where('business_id', …)`).
 *
 * @see Modules\Essentials\Http\Controllers\EssentialsLeaveController::store()
 * @see Modules\Essentials\Http\Controllers\SalesTargetController (gate Tier 0 espelhado)
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 * @see prototipo-ui/design-docs/cowork-inbox/hrm/Licencas.casos.md (UC-HRM-02/05/15)
 */
class StoreLeaveRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $businessId = (int) $this->session()->get('user.business_id');

        return [
            // Tier 0: o `exists` é escopado no business — tipo de outro negócio é 422 (UC-HRM-05).
            'essentials_leave_type_id' => [
                'required',
                'integer',
                Rule::exists('essentials_leave_types', 'id')->where('business_id', $businessId),
            ],
            'start_date' => ['required', 'string'],
            'end_date'   => ['required', 'string'],
            'reason'     => ['required', 'string', 'max:2000'],
            'employees'  => ['sometimes', 'array'],
            // Tier 0: cada colaborador precisa ser do tenant — fecha o IDOR do `foreach`.
            'employees.*' => [
                'integer',
                Rule::exists('users', 'id')->where('business_id', $businessId),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'essentials_leave_type_id.required' => 'O tipo de licença é obrigatório.',
            'essentials_leave_type_id.exists'   => 'Tipo de licença inválido.',
            'start_date.required'               => 'A data inicial é obrigatória.',
            'end_date.required'                 => 'A data final é obrigatória.',
            'reason.required'                   => 'O motivo é obrigatório.',
            'reason.max'                        => 'O motivo deve ter no máximo 2000 caracteres.',
            'employees.*.exists'                => 'Colaborador inválido.',
        ];
    }

    /**
     * Datas e ordem do período — validadas pelo conversor que grava (ver docblock).
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->hasAny(['start_date', 'end_date'])) {
                return; // `required` já falou; não empilhar mensagem de formato por cima
            }

            $inicio = $this->paraDataMysql($this->input('start_date'));
            $fim    = $this->paraDataMysql($this->input('end_date'));

            if ($inicio === null) {
                $validator->errors()->add('start_date', 'Data inicial inválida.');
            }
            if ($fim === null) {
                $validator->errors()->add('end_date', 'Data final inválida.');
            }

            // UC-HRM-02 — copy literal do casos.md.
            if ($inicio !== null && $fim !== null && $fim < $inicio) {
                $validator->errors()->add('end_date', 'O fim não pode ser antes do início.');
            }
        });
    }

    /**
     * Converte data do formato do negócio para `Y-m-d`, ou null se não converte.
     *
     * Mesma chamada que `EssentialsLeaveController::store()` faz ao gravar — se aqui
     * devolve null, lá lançaria exceção. Comparar `Y-m-d` como string é seguro:
     * o formato é lexicograficamente ordenável.
     */
    private function paraDataMysql(mixed $valor): ?string
    {
        if (! is_string($valor) || $valor === '') {
            return null;
        }

        try {
            $convertida = app(\App\Utils\ModuleUtil::class)->uf_date($valor);
        } catch (\Throwable) {
            return null; // formato não bate com o date_format do negócio
        }

        return is_string($convertida) && $convertida !== '' ? $convertida : null;
    }

    /**
     * Ids de colaboradores já validados como do tenant — usado pelo controller.
     *
     * @return array<int, int>
     */
    public function colaboradoresDoTenant(): array
    {
        $ids = $this->input('employees');

        return is_array($ids) ? array_map('intval', $ids) : [];
    }

    /**
     * Guarda extra de defesa em profundidade: mesmo padrão do `SalesTargetController`.
     *
     * O `exists` escopado acima já barra id alheio. Este método existe para o caso de
     * o controller precisar reconfirmar antes de escrever (o `exists` valida a leitura;
     * este confirma na hora do INSERT, que é onde o scope não alcança).
     */
    public function confirmarColaboradoresDoTenant(): void
    {
        $businessId = (int) $this->session()->get('user.business_id');

        foreach ($this->colaboradoresDoTenant() as $userId) {
            User::where('business_id', $businessId)->findOrFail($userId);
        }
    }
}
