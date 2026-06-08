<?php

declare(strict_types=1);

namespace Modules\Ponto\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Ponto\Entities\Marcacao;

/**
 * AnularMarcacaoRequest — fluxo de anulacao de marcacao errada (Wave 27 D8.a).
 *
 * Marcacao de ponto e APPEND-ONLY (Portaria MTP 671/2021 Art. 85) — NUNCA pode
 * ser deletada ou atualizada. Quando ha erro de digitacao (ex: funcionario bate
 * 2x ENTRADA por engano), a correcao acontece via uma NOVA marcacao com:
 *
 *   - origem = ANULACAO
 *   - marcacao_anulada_id = ID da marcacao errada
 *   - motivo = justificativa textual (auditoria fiscal MTE)
 *
 * A marcacao original PERMANECE registrada (fiscalizacao ve historia completa).
 *
 * Tier 0 multi-tenant (ADR 0093): business_id NAO vem do request — injetado
 * pelo Controller via session()/auth. Servico valida tambem se marcacao_anulada_id
 * pertence ao mesmo business_id (anti-tampering cross-tenant + tampering
 * temporal contra fiscalizacao).
 *
 * Permissao: somente RH/gestor (verificado via middleware/policy no Controller —
 * funcionario raso NAO pode anular suas proprias marcacoes — Portaria 671 Anexo
 * I §3 — segregacao de funcoes).
 *
 * @see Modules/Ponto/Entities/Marcacao.php (constante ORIGEM_ANULACAO + helper anular())
 * @see Modules/Ponto/Tests/Feature/CustomerJourneyTest.php (passo 6 anulacao)
 * @see Modules/Ponto/Tests/Feature/CrossTenantMarcacaoTest.php (cenario 5 isolamento)
 * @see Portaria MTP 671/2021 Art. 85 + Anexo I
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 */
class AnularMarcacaoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            // ID UUID da marcacao a anular — Service valida exists + business_id match
            'marcacao_anulada_id' => [
                'required',
                'string',
                'size:36',
                'regex:/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i',
            ],

            // Motivo obrigatorio — auditoria fiscal MTE precisa de justificativa
            // (Portaria 671 Anexo I §4 — "registros devem permitir conferencia da
            // jornada efetivamente trabalhada")
            'motivo' => [
                'required',
                'string',
                'min:10',
                'max:500',
            ],

            // Tipo da NOVA marcacao (mesmo da original via Service lookup;
            // request opcional pra cenarios especiais)
            'tipo' => [
                'nullable',
                'string',
                'in:'.implode(',', [
                    Marcacao::TIPO_ENTRADA,
                    Marcacao::TIPO_SAIDA,
                ]),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'marcacao_anulada_id.required' => 'Informe o ID da marcacao a anular (UUID).',
            'marcacao_anulada_id.regex' => 'ID invalido — esperado formato UUID v4.',
            'motivo.required' => 'Motivo obrigatorio (Portaria 671 Anexo I §4).',
            'motivo.min' => 'Motivo precisa ter ao menos 10 caracteres (justificativa fiscal).',
            'motivo.max' => 'Motivo limitado a 500 caracteres.',
        ];
    }
}
