<?php

namespace Modules\Ponto\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ImportacaoAfdRequest extends FormRequest
{
    /**
     * US-GOV-059 classe C — duas correções no mesmo gate:
     *
     * 1. `ponto.importacoes.manage` em vez de `.criar`. O nome antigo não existia
     *    em código nem na tabela `permissions` (verificado em prod: 495 permissões,
     *    nenhuma delas) — ninguém podia recebê-lo, então importar AFD só funcionava
     *    pra admin via Gate::before. `.manage` é o padrão dos irmãos do módulo
     *    (`aprovacoes.manage`, `colaboradores.manage`, `configuracoes.manage`);
     *    `.criar` estava em português e sozinho na convenção.
     *
     * 2. Fail-SECURE. Era `$user ? $user->can(...) : true` — sem usuário
     *    autenticado retornava TRUE, ou seja, autorizava. O middleware `auth`
     *    cobre na prática, mas o padrão do projeto é negar por ausência (ver
     *    RevertActivityRequest: `return $this->user() !== null`).
     */
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && $user->can('ponto.importacoes.manage');
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
