<?php

declare(strict_types=1);

namespace Modules\NfeBrasil\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * D8.c Security Wave S — FormRequest extraído de NfeInutilizacaoController::store.
 *
 * Cancela/inutiliza faixa de números NFe junto à SEFAZ (CONFAZ SINIEF 07/2005 Art. 14).
 *
 * REGRAS FISCAIS SEFAZ — NÃO RELAXAR:
 *   - justificativa: ABRASF exige 15..255 chars.
 *   - modelo: 55 (NFe) ou 65 (NFC-e) — único enum válido.
 *   - serie: 1..3 chars (SEFAZ aceita 1-999).
 *   - numero_de / numero_ate: ints ≥ 1; range_check via withValidator.
 *
 * Multi-tenant Tier 0: business_id sempre da sessão no Controller.
 */
class CancelarNfeRequest extends FormRequest
{
    /**
     * `fiscal.inutilizar` é ROLE, não permissão — daí `hasRole`, não `can`.
     *
     * Era `can('fiscal.inutilizar')`, e esse nome não existe como permissão
     * Spatie em fonte nenhuma. Quem o cria é o `NfeFiscalActionsSeeder`, como
     * ROLE per-business na convenção UltimatePOS (sufixo `#{business_id}`), pra
     * autorizar a action FSM crítica `inutilizar_faixa`. `can()` procura
     * permissão e nunca casa com role homônima ⇒ o gate caía sempre em false e
     * só o superadmin passava, pelo `Gate::before` — contra a intenção que o
     * docblock do `NfeInutilizacaoController` e o comentário da rota já
     * declaravam ("role per-business — seeder NfeFiscalActionsSeeder").
     * Achado pelo `permission-drift` (US-GOV-059, classe A).
     *
     * NÃO afrouxa: quem não tem a role segue barrado, e o superadmin continua
     * passando pelo `Gate::before`. A role só existe onde o seeder rodou — ele
     * não está no `DatabaseSeeder`, então nada é concedido automaticamente.
     *
     * O fallback sem sufixo espelha o guard do próprio seeder
     * (`Schema::hasColumn('roles', 'business_id')`): onde a coluna não existe,
     * ele cria a role com o nome puro.
     */
    public function authorize(): bool
    {
        $user = $this->user();
        if ($user === null) {
            return false;
        }

        // Multi-tenant Tier 0 (ADR 0093): business_id SEMPRE da sessão, nunca do request.
        $businessId = (int) $this->session()->get('business.id', 0);
        if ($businessId === 0) {
            return false;
        }

        return $user->hasRole("fiscal.inutilizar#{$businessId}")
            || $user->hasRole('fiscal.inutilizar');
    }

    public function rules(): array
    {
        return [
            'modelo' => ['required', 'string', Rule::in(['55', '65'])],
            'serie' => ['required', 'string', 'max:3'],
            'numero_de' => ['required', 'integer', 'min:1'],
            'numero_ate' => ['required', 'integer', 'min:1'],
            'justificativa' => ['required', 'string', 'min:15', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'modelo.in' => 'Modelo deve ser 55 (NFe) ou 65 (NFC-e).',
            'justificativa.min' => 'Justificativa SEFAZ exige no mínimo 15 caracteres.',
            'justificativa.max' => 'Justificativa SEFAZ aceita no máximo 255 caracteres.',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($v) {
            $de = (int) $this->input('numero_de');
            $ate = (int) $this->input('numero_ate');
            if ($de > 0 && $ate > 0 && $ate < $de) {
                $v->errors()->add(
                    'numero_ate',
                    'numero_ate deve ser maior ou igual a numero_de.'
                );
            }
        });
    }
}
