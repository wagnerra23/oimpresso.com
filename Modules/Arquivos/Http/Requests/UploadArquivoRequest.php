<?php

declare(strict_types=1);

namespace Modules\Arquivos\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Wave 14 D8 Security — FormRequest reutilizavel pra rotas que recebem upload de
 * arquivo via Modules/Arquivos (backbone DMS ADR 0123).
 *
 * Uso recomendado: Controllers consumindo trait HasArquivos devem typehint este
 * Request pra garantir validacao defensiva consistente (MIME, tamanho, anti-PHP).
 *
 * Bucket whitelist espelha config('arquivos.buckets') — categorias canon:
 *  - public, internal, sensitive, vault (ADR 0123 §3)
 *
 * MIME whitelist conservadora: imagens + PDF + ZIP (NFe) + Office. PHP/JS bloqueados
 * via reject_extensions pra evitar RCE em disk publico.
 *
 * @see Modules\Arquivos\Services\ArquivosService
 * @see memory/decisions/0123-modules-arquivos-backbone.md §3 buckets
 */
class UploadArquivoRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if ($user === null) {
            return false;
        }

        // business_id obrigatorio em sessao — sem isso o ArquivosService nao
        // consegue stampar tenant. Tier 0 multi-tenant.
        $businessId = $this->session()->get('user.business_id');

        return ! empty($businessId);
    }

    public function rules(): array
    {
        return [
            'file' => [
                'required',
                'file',
                // 50 MB default (em KB). Override em config se precisar maior.
                'max:51200',
                // MIMEs whitelist — bloqueia .php/.exe/.sh/.bat por extensao implicita.
                'mimes:jpg,jpeg,png,gif,webp,pdf,xml,zip,csv,xlsx,xls,docx,doc,txt',
            ],
            'bucket' => [
                'nullable',
                'string',
                Rule::in(['public', 'internal', 'sensitive', 'vault']),
            ],
            'consumer_type' => ['nullable', 'string', 'max:64'],
            'consumer_id' => ['nullable', 'integer'],
        ];
    }

    public function messages(): array
    {
        return [
            'file.mimes' => 'Tipo de arquivo nao permitido. Aceitos: imagens, PDF, ZIP, planilhas, documentos Office.',
            'file.max' => 'Arquivo excede 50 MB.',
            'bucket.in' => 'Bucket invalido. Use: public, internal, sensitive ou vault.',
        ];
    }
}
