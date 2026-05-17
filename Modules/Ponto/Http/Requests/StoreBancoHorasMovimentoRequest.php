<?php

declare(strict_types=1);

namespace Modules\Ponto\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * StoreBancoHorasMovimentoRequest — registro de movimento manual de banco de horas
 * (Wave 27 D8.b).
 *
 * Banco de Horas (BH) compensatorio CLT Art. 59 §2º + Lei 13.467/2017 (Reforma
 * Trabalhista 6 meses acordo individual / 1 ano acordo coletivo). Movimentos:
 *
 *   - CREDITO  (saldo positivo — HE nao paga, acumulada pra compensar)
 *   - DEBITO   (saldo negativo — falta/atraso compensado)
 *   - AJUSTE   (correcao manual RH com justificativa fiscal)
 *   - QUITACAO (pagamento de saldo positivo em folha + zera saldo)
 *
 * Apesar de Banco de Horas ter calculo automatico via ApuracaoService, ha
 * cenarios legitimos de movimento MANUAL:
 *   - Ajuste retroativo de acordo coletivo (RH precisa CREDITO/DEBITO manual)
 *   - Quitacao parcial (admin decide pagar 20h e zerar o resto)
 *   - Correcao de bug historico (justificativa fiscal obrigatoria)
 *
 * Tier 0 multi-tenant (ADR 0093): business_id injetado pelo Controller
 * via session/auth. Saldo NAO vem do request — calculado pelo Service apos
 * INSERT (saldo_anterior + delta).
 *
 * Append-only por design: Movimentos NUNCA sao deletados/atualizados. Correcao
 * de movimento errado vira NOVO movimento tipo AJUSTE com referencia.
 *
 * @see Modules/Ponto/Entities/BancoHorasMovimento.php
 * @see Modules/Ponto/Services/BancoHorasService.php
 * @see CLT Art. 59 §2º + Lei 13.467/2017
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 */
class StoreBancoHorasMovimentoRequest extends FormRequest
{
    public const TIPO_CREDITO = 'CREDITO';
    public const TIPO_DEBITO = 'DEBITO';
    public const TIPO_AJUSTE = 'AJUSTE';
    public const TIPO_QUITACAO = 'QUITACAO';

    public const TIPOS_VALIDOS = [
        self::TIPO_CREDITO,
        self::TIPO_DEBITO,
        self::TIPO_AJUSTE,
        self::TIPO_QUITACAO,
    ];

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            // FK colaborador — Service valida cross-tenant business_id match
            'colaborador_config_id' => [
                'required',
                'integer',
                'exists:ponto_colaborador_config,id',
            ],

            // Tipo de movimento
            'tipo' => [
                'required',
                'string',
                'in:'.implode(',', self::TIPOS_VALIDOS),
            ],

            // Quantidade em MINUTOS (granularidade legal — CLT trabalha em fracoes
            // de hora; converter horas->minutos no frontend antes de submeter)
            // Limite max 720min/dia = 12h (CLT Art. 59 limite legal HE)
            'minutos' => [
                'required',
                'integer',
                'min:1',
                'max:43200', // 30 dias * 24h * 60min — limite sanity ajuste mensal
            ],

            // Data competencia — qual dia esse movimento se refere
            // (pode ser retroativo, NAO pode ser futuro)
            'competencia' => [
                'required',
                'date_format:Y-m-d',
                'before_or_equal:today',
                'after:-3 years', // limite sanity — CLT Art. 11 prescricao trabalhista 5 anos
            ],

            // Motivo obrigatorio pra AJUSTE/QUITACAO (auditoria fiscal)
            'motivo' => [
                'nullable',
                'required_if:tipo,AJUSTE,QUITACAO',
                'string',
                'min:10',
                'max:500',
            ],

            // Referencia opcional — ID de movimento anterior sendo corrigido
            'movimento_referencia_id' => [
                'nullable',
                'integer',
                'exists:ponto_banco_horas_movimentos,id',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'tipo.in' => 'Tipo invalido — use CREDITO, DEBITO, AJUSTE ou QUITACAO.',
            'minutos.min' => 'Quantidade minima 1 minuto.',
            'minutos.max' => 'Quantidade excede limite mensal (30d * 24h * 60min).',
            'competencia.before_or_equal' => 'Competencia nao pode ser no futuro.',
            'competencia.after' => 'Competencia muito antiga (CLT Art. 11 prescricao 5 anos).',
            'motivo.required_if' => 'Motivo obrigatorio para AJUSTE/QUITACAO (Portaria 671 Anexo I §4).',
        ];
    }
}
