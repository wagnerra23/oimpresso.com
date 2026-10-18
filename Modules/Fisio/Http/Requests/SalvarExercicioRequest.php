<?php

declare(strict_types=1);

namespace Modules\Fisio\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Exercício PRÓPRIO da clínica (US-FISIO-004 · UC-FBI-02/03). O `business_id` nunca vem do
 * corpo: o controller grava o da sessão (o campo, se enviado, é ignorado).
 *
 * POST exige o exercício inteiro; PUT aceita só os campos enviados (`sometimes`), mas o que
 * vier passa pelas mesmas regras — nome e passos não podem ficar vazios.
 * Facetas e contraindicações são listas fechadas de `config('fisio.*')`.
 */
class SalvarExercicioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('fisio.editar');
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $req = $this->isMethod('post') ? 'required' : 'sometimes';
        $lista = fn (string $chave) => Rule::in(array_keys((array) config("fisio.biblioteca.{$chave}")));

        return [
            'name' => [$req, 'string', 'min:2', 'max:160'],
            'region' => [$req, 'string', $lista('regioes')],
            'type' => [$req, 'string', $lista('tipos')],
            'pose' => ['sometimes', 'nullable', 'string', $lista('posicoes')],
            'equipment' => ['sometimes', 'nullable', 'array'],
            'equipment.*' => ['string', 'distinct', $lista('equipamentos')],
            'steps' => [$req, 'array', 'min:1', 'max:8'],
            'steps.*' => ['required', 'string', 'max:200'],
            'default_sets' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:20'],
            'default_reps' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:200'],
            'default_hold_s' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:600'],
            'times_per_day' => ['sometimes', 'integer', 'min:1', 'max:6'],
            'safety_notes' => ['sometimes', 'nullable', 'array', 'max:6'],
            'safety_notes.*' => ['required', 'string', 'max:200'],
            'contraindication_flags' => ['sometimes', 'nullable', 'array'],
            'contraindication_flags.*' => ['string', 'distinct', Rule::in((array) config('fisio.condition_flags'))],
            'needs_support' => ['sometimes', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'name' => 'nome',
            'region' => 'região',
            'type' => 'tipo',
            'pose' => 'posição',
            'equipment' => 'equipamento',
            'steps' => 'passos',
            'times_per_day' => 'vezes por dia',
            'safety_notes' => 'avisos',
            'contraindication_flags' => 'contraindicações',
        ];
    }

    /** Só os campos de conteúdo validados — nunca business_id, is_custom, code nem review_*. */
    public function conteudo(): array
    {
        return $this->safe()->only([
            'name', 'region', 'type', 'pose', 'equipment', 'steps', 'default_sets', 'default_reps',
            'default_hold_s', 'times_per_day', 'safety_notes', 'contraindication_flags', 'needs_support',
        ]);
    }
}
