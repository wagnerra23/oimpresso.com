<?php

namespace Modules\Officeimpresso\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Revogacao (toggle bloqueio) de licenca-computador legacy — D8.c Security.
 *
 * Aciona LicencaComputadorController.toggleBlock($id) — flag `bloqueado` é flipada.
 * ID validado na rota via route-model-binding/exists; payload geralmente vazio,
 * mas se vier `motivo` opcional fica registrado pra audit futuro.
 *
 * Bridge legacy: Delphi le `bloqueado=1` e impede login no executavel cliente.
 */
class RevokeLicencaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Payload minimo — toggle nao recebe novo estado (flip server-side).
     * `motivo` opcional reservado pra audit log futuro (LicencaLogController).
     */
    public function rules(): array
    {
        return [
            'motivo' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'motivo.max' => 'O motivo nao pode ter mais de 500 caracteres.',
        ];
    }
}
