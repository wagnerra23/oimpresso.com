<?php

declare(strict_types=1);

namespace Modules\SRS\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * FormRequest pra IngestController::store.
 *
 * D8.c Security — extrai validação inline do Controller pra classe dedicada
 * (SoC brutal ADR 0094 §5). Regras canônicas: tipos restritos por enum,
 * upload com cap 20MB + MIME whitelist (defense vs file-upload exploits),
 * URL HTTPS-only quando aplicável.
 *
 * Multi-tenant Tier 0 (ADR 0093) — `business_id` é injetado no Controller
 * via session, NUNCA aceito do request body.
 */
class StoreIngestRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Autorização real fica nas policies / middleware auth — request só valida payload.
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'type'             => 'required|in:screenshot,chat,error,file,text,url',
            'module_target'    => 'nullable|string|max:64',
            'title'            => 'nullable|string|max:255',
            'description'      => 'nullable|string|max:2000',
            'body_text'        => 'nullable|string|max:50000',
            'source_url'       => 'nullable|url|max:500',

            // Upload: 20MB cap (alinhado com IngestController original).
            // MIME whitelist (defense vs file-upload exploits):
            // - PDF/MD/HTML pra docs
            // - PNG/JPG/WEBP pra screenshots
            // - TXT pra body bruto
            'upload'           => 'nullable|file|max:20480|mimes:pdf,md,html,htm,txt,png,jpg,jpeg,webp',

            // Evidência opcional inicial
            'create_evidence'  => 'boolean',
            'evidence_kind'    => 'nullable|in:bug,rule,flow,quote,screenshot,decision',
            'evidence_content' => 'nullable|string|max:5000',
        ];
    }

    public function messages(): array
    {
        return [
            'type.required' => 'Tipo da fonte é obrigatório.',
            'type.in'       => 'Tipo inválido (use screenshot/chat/error/file/text/url).',
            'upload.max'    => 'Arquivo excede 20MB.',
            'upload.mimes'  => 'Formato não suportado (use PDF/MD/HTML/TXT/PNG/JPG/WEBP).',
            'source_url.url'=> 'URL inválida — use http(s)://...',
        ];
    }
}
