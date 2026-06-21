<?php

declare(strict_types=1);

namespace Modules\OficinaAuto\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * F3 OS-V2-1 — FormRequest pra upload de foto do laudo OS-level (Fotos & Laudo).
 *
 * Diferente do UploadDviPhotoRequest (foto POR item DVI): aqui a foto é anexada à
 * própria ServiceOrder (morph polimórfico via HasArquivos), compondo o laudo geral
 * da vistoria que entra na folha A4 impressa ("Fotos da vistoria").
 *
 * Espelha o protótipo Cowork aprovado [W] 2026-06-09 (seção "Fotos & Laudo" do
 * drawer · oficina-page.jsx). Persona Técnico Repair (tablet, câmera traseira).
 *
 * Multi-tenant Tier 0 ([ADR 0093]): business_id auto-fill via ArquivosService::attach
 * (lê session). Request nunca injeta business_id.
 *
 * Validação: required file, image MIME whitelist, max 10MB (HEIC iPhone ~3-4MB).
 *
 * @see Modules\OficinaAuto\Http\Controllers\ServiceOrderPhotoController::store
 * @see Modules\Arquivos\Services\ArquivosService::attach
 */
class StoreServiceOrderPhotoRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if ($user === null) {
            return false;
        }

        return $user->can('superadmin') || $user->can('oficinaauto.service_order.update');
    }

    public function rules(): array
    {
        return [
            'photo' => [
                'required',
                'file',
                'mimes:jpeg,jpg,png,webp,heic,heif',
                'mimetypes:image/jpeg,image/png,image/webp,image/heic,image/heif',
                'max:10240', // 10 MB em kilobytes
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'photo.required'  => 'Arquivo de foto obrigatório.',
            'photo.file'      => 'Upload inválido — não é um arquivo.',
            'photo.mimes'     => 'Formato inválido. Use JPEG, PNG, WebP, HEIC ou HEIF.',
            'photo.mimetypes' => 'Tipo MIME inválido. Apenas imagens (jpeg/png/webp/heic/heif).',
            'photo.max'       => 'Foto excede 10 MB. Reduza a qualidade antes de subir.',
        ];
    }
}
