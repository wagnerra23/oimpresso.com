<?php

declare(strict_types=1);

namespace Modules\OficinaAuto\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * D8 Security — FormRequest pra upload de foto/laudo num item DVI.
 *
 * Gap 1 (2026-05-26) — substitui placeholder V2 FOTOS no drawer ServiceOrderRichSheet
 * por upload real via `Modules/Arquivos` trait HasArquivos polimórfica (ADR 0123).
 *
 * Best-practice 2026 (AutoVitals/Tekmetric): foto inline em CADA item DVI, não
 * anexo solto da OS. Motorista caminhão basculante leva foto antes/depois pra
 * ressarcir transportadora 3a / seguradora (sub-vertical 4 mecânica pesada ADR 0194).
 *
 * Multi-tenant Tier 0 ([ADR 0093]): business_id auto-fill via ArquivosService::attach
 * (lê session('user.business_id') / session('business.id')). Request nunca injeta.
 *
 * Validação:
 * - `photo`: required file, image MIME jpeg/png/webp/heic/heif, max 10MB (HEIC iPhone ~3-4MB)
 *
 * @see Modules\OficinaAuto\Http\Controllers\DviInspectionController::uploadPhoto
 * @see Modules\Arquivos\Services\ArquivosService::attach
 * @see memory/sessions/2026-05-26-plano-gap-1-upload-foto-laudo-drawer.md
 */
class UploadDviPhotoRequest extends FormRequest
{
    /**
     * Autorização — mesma policy da edição da OS (Spatie + sameTenant guard).
     */
    public function authorize(): bool
    {
        $user = $this->user();

        if ($user === null) {
            return false;
        }

        return $user->can('superadmin') || $user->can('oficinaauto.service_order.update');
    }

    /**
     * Validação MIME whitelist + tamanho máximo.
     *
     * - max:10240 KB = 10 MB. HEIC iPhone padrão fica em 3-4MB; foto câmera traseira
     *   Android 12MP em JPEG fica ~3-5MB. Margem generosa.
     * - MIME whitelist explícito evita SVG (XSS via embedded JS) e file extension spoofing.
     * - `dimensions` opcional NÃO incluído — DVI foto pode vir de câmera baixa qualidade
     *   ou recorte. Validar dimensões mata UX em caso real (rebocador na chuva).
     */
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
            'photo.required'   => 'Arquivo de foto obrigatório.',
            'photo.file'       => 'Upload inválido — não é um arquivo.',
            'photo.mimes'      => 'Formato inválido. Use JPEG, PNG, WebP, HEIC ou HEIF.',
            'photo.mimetypes'  => 'Tipo MIME inválido. Apenas imagens (jpeg/png/webp/heic/heif).',
            'photo.max'        => 'Foto excede 10 MB. Reduza a qualidade antes de subir.',
        ];
    }
}
