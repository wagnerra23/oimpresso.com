<?php

namespace Modules\PontoWr2\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class IntercorrenciaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'colaborador_config_id' => 'required|exists:ponto_colaborador_config,id',
            'tipo'                  => 'required|in:CONSULTA_MEDICA,ATESTADO_MEDICO,REUNIAO_EXTERNA,VISITA_CLIENTE,HORA_EXTRA_AUTORIZADA,ESQUECIMENTO_MARCACAO,PROBLEMA_EQUIPAMENTO,OUTRO',
            'data'                  => 'required|date|before_or_equal:today',
            'dia_todo'              => 'boolean',
            'intervalo_inicio'      => 'nullable|required_unless:dia_todo,true|date_format:H:i',
            'intervalo_fim'         => 'nullable|required_unless:dia_todo,true|date_format:H:i|after:intervalo_inicio',
            'justificativa'         => 'required|string|min:10|max:2000',
            'anexo'                 => 'nullable|file|mimes:pdf,jpg,png|max:5120',
            'prioridade'            => 'nullable|in:NORMAL,URGENTE',
            'impacta_apuracao'      => 'boolean',
            'descontar_banco_horas' => 'boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'justificativa.min' => 'A justificativa deve ter pelo menos 10 caracteres.',
            'intervalo_fim.after' => 'O horário final deve ser posterior ao inicial.',
        ];
    }
}
