<?php

namespace Modules\PontoWr2\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ImportacaoAfdRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        return $user ? $user->can('ponto.importacoes.criar') : true;
    }

    public function rules(): array
    {
        $maxKb = config('pontowr2.afd.max_filesize_mb', 50) * 1024;

        return [
            'tipo'     => 'required|in:AFD,AFDT,CSV_CADASTRO,CSV_ESCALA',
            'arquivo'  => "required|file|max:{$maxKb}|mimes:txt,csv",
        ];
    }

    public function messages(): array
    {
        return [
            'arquivo.max'   => 'O arquivo excede o tamanho máximo permitido.',
            'arquivo.mimes' => 'Apenas arquivos .txt (AFD/AFDT) ou .csv são aceitos.',
        ];
    }
}
