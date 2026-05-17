<?php

declare(strict_types=1);

namespace Modules\ConsultaOs\Http\Requests;

/**
 * FeedbackPublicoRequest — feedback opcional cliente apos consulta (Wave 27 D8.b).
 *
 * Endpoint publico para cliente externo enviar feedback rapido (NPS-like)
 * sobre o portal de consulta. Status: scaffold pronto pra US-CONSULTA-002
 * (analytics portal publico).
 *
 * Defesa contra spam/abuso:
 *   - Throttle 5 req/min via middleware (mais restritivo que buscar — feedback
 *     e baixa frequencia natural)
 *   - Comentario opcional max:500 + sanitizacao no Controller
 *   - Nota numerica 1-5 obrigatoria (sem free-text como unico campo)
 *   - PiiRedactor wraps comentario ANTES de persistir (defesa em profundidade
 *     se cliente colar CPF/email por engano)
 *
 * Tier 0 multi-tenant (ADR 0093): rota publica — NAO scopa por business_id.
 * Quando US-CONSULTA-002 ativar persistencia, Repository resolve biz via
 * numero_os lookup (igual padrao buscar) e armazena com tenant correto.
 *
 * LGPD: NAO ha PII no payload obrigatorio (apenas nota + numero_os referenciado).
 * Comentario opcional passa por PiiRedactor antes de persistir. Audit log
 * registrado com IP truncado /24 (igual auditarConsulta).
 *
 * @see Modules/ConsultaOs/Http/Requests/ConsultaPublicaRequest (busca por numero)
 * @see Modules/ConsultaOs/Http/Controllers/ConsultaOsController (auditarConsulta pattern)
 * @see Modules/Jana/Services/Privacy/PiiRedactor (canon redactor)
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 */
class FeedbackPublicoRequest extends \Illuminate\Foundation\Http\FormRequest
{
    public function authorize(): bool
    {
        return true; // rota publica — autoriz via throttle middleware
    }

    public function rules(): array
    {
        return [
            // Numero da OS referenciada (mesma validacao da ConsultaPublicaRequest)
            'numero_os' => [
                'required',
                'string',
                'alpha_num',
                'max:20',
            ],

            // Nota NPS-like 1-5 (escala simples)
            'nota' => [
                'required',
                'integer',
                'min:1',
                'max:5',
            ],

            // Comentario opcional — PiiRedactor wraps antes de persistir
            'comentario' => [
                'nullable',
                'string',
                'max:500',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'numero_os.required' => 'Informe o numero da OS para o feedback.',
            'numero_os.alpha_num' => 'Numero da OS contem apenas letras e numeros.',
            'nota.required' => 'Informe a nota (1-5).',
            'nota.between' => 'Nota fora do intervalo (1-5).',
            'comentario.max' => 'Comentario limitado a 500 caracteres.',
        ];
    }
}
