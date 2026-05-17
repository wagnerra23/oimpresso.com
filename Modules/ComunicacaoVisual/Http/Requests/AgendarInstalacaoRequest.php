<?php

declare(strict_types=1);

namespace Modules\ComunicacaoVisual\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * AgendarInstalacaoRequest — validação agendamento de instalação OS.
 *
 * Wave 18 D8 Security.
 *
 * Cobre POST /com-visual/os/{id}/agendar-instalacao.
 * Define data + endereço + responsável de instalação.
 *
 * Multi-tenant Tier 0: os_id resolvido via global scope (não input).
 *
 * @see Modules/ComunicacaoVisual/Entities/Instalacao.php
 */
class AgendarInstalacaoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'data_agendada'    => ['required', 'date', 'after_or_equal:today'],
            'periodo'          => ['nullable', 'in:manha,tarde,noite,integral'],

            // Endereço — limites pra evitar payload abusivo
            'endereco'         => ['required', 'string', 'max:255'],
            'cidade'           => ['required', 'string', 'max:120'],
            'uf'               => ['required', 'string', 'size:2'],
            'cep'              => ['nullable', 'string', 'max:9'],

            'responsavel_id'   => ['nullable', 'integer', 'min:1'],
            'observacoes'      => ['nullable', 'string', 'max:1000'],
            'requer_andaime'   => ['nullable', 'boolean'],
            'altura_metros'    => ['nullable', 'numeric', 'min:0', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'data_agendada.required'        => 'Data da instalação é obrigatória.',
            'data_agendada.after_or_equal'  => 'Data da instalação não pode ser no passado.',
            'endereco.required'             => 'Endereço de instalação obrigatório.',
            'cidade.required'               => 'Cidade obrigatória.',
            'uf.required'                   => 'UF obrigatória.',
            'uf.size'                       => 'UF deve ter 2 caracteres (ex: SC, SP).',
            'altura_metros.max'             => 'Altura máxima 100 metros.',
        ];
    }
}
