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
            // Mesma regra do upload compartilhado (ValidacaoUpload / config arquivos.*): uma
            // fonte só, para o form e o serviço não divergirem. Ainda não há rota de upload
            // genérica no módulo; cada consumidor valida na tela dele e o serviço revalida.
            'file' => [
                'required',
                'file',
                'max:' . ((int) config('arquivos.upload_max_mb', 50) * 1024),
                'mimetypes:' . implode(',', (array) config('arquivos.upload_mimes', [])),
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
            'file.mimetypes' => 'Tipo de arquivo nao permitido. Aceitos: imagens, PDF, video MP4, XML, ZIP, planilhas, documentos Office.',
            'file.max' => 'Arquivo acima do limite.',
            'bucket.in' => 'Bucket invalido. Use: public, internal, sensitive ou vault.',
        ];
    }
}
