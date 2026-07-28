<?php

namespace Modules\VozDoCliente\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreSinalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check()
            && (auth()->user()->can('superadmin') || auth()->user()->can('vozdocliente.reportar'));
    }

    public function rules(): array
    {
        return [
            // 2000 é generoso de propósito: cortar o relato pela metade perde
            // exatamente o detalhe que torna o sinal acionável.
            'texto'      => ['required', 'string', 'min:5', 'max:2000'],
            'severidade' => ['nullable', 'integer', 'between:0,4'],
            // Vem do front (a URL onde a pessoa estava), não digitada.
            'url_vista'  => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'texto.required'   => 'Escreva o que aconteceu.',
            'texto.min'        => 'Conte um pouco mais — com poucas palavras não dá pra investigar.',
            'texto.max'        => 'Relato muito longo. Resuma em até 2000 caracteres.',
            'severidade.between' => 'A severidade vai de 0 (incômodo) a 4 (impede trabalhar).',
        ];
    }
}
