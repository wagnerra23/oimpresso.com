<?php

declare(strict_types=1);

namespace Modules\ComunicacaoVisual\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * AgendarInstalacaoRequest — POST /comunicacao-visual/api/os/{os}/instalacao (US-COMVIS-007 · UC-FLX-21).
 *
 * Janela [inicio, fim), equipe (ids de users) e veículo em texto (placa/apelido — não há
 * cadastro de frota). A equipe ser da mesma empresa e a janela estar livre são regras do
 * AgendaInstalacao, não daqui: dependem do banco e devolvem a mensagem do conflito.
 *
 * Até 2026-10-20 este arquivo era código morto (rota inexistente, contrato de endereço/UF que
 * nada usava); foi reescrito para o contrato real da agenda.
 *
 * Tier 0: a OS vem da rota e o business da sessão — nunca do corpo.
 */
class AgendarInstalacaoRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = auth()->user();

        return $user !== null && ($user->can('superadmin') || $user->can('comvis.os.update_status'));
    }

    public function rules(): array
    {
        return [
            'inicio' => ['required', 'date'],
            'fim' => ['required', 'date', 'after:inicio'],
            'equipe' => ['required', 'array', 'min:1', 'max:10'],
            'equipe.*' => ['integer', 'min:1', 'distinct'],
            'veiculo' => ['nullable', 'string', 'max:60'],
            'observacoes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'inicio.required' => 'Informe o início da instalação.',
            'fim.required' => 'Informe o fim da instalação.',
            'fim.after' => 'O fim da instalação tem que ser depois do início.',
            'equipe.required' => 'Informe ao menos uma pessoa da equipe.',
            'equipe.min' => 'Informe ao menos uma pessoa da equipe.',
            'equipe.max' => 'A equipe tem no máximo 10 pessoas.',
            'equipe.*.distinct' => 'A mesma pessoa aparece duas vezes na equipe.',
            'veiculo.max' => 'O veículo tem no máximo 60 caracteres.',
        ];
    }
}
