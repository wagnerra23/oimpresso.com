<?php

declare(strict_types=1);

namespace Modules\Financeiro\Services;

use Illuminate\Support\Facades\Log;
use Modules\Jana\Services\Privacy\PiiRedactor;

/**
 * FinanceiroAuditLogger — wrapper de Log que redaciona PII brasileira
 * antes de persistir entradas. Wave 14 D7.a (LGPD compliance).
 *
 * **Motivação:** services do Financeiro logam payload de baixa/cobrança/emissão
 * de boleto que pode conter:
 *  - CPF/CNPJ do cliente (via `cliente_descricao` em Titulo)
 *  - CPF/CNPJ do beneficiário (via `beneficiario_documento` em ContaBancaria)
 *  - Email/telefone do contato (via `transaction_payment.note` ou `observacoes`)
 *
 * Logs em arquivo (storage/logs/laravel.log) ou stack drivers (Stackdriver,
 * Sentry) saem fora do perímetro tenant — LGPD Art. 7 §IX exige minimização
 * mesmo em legítimo interesse operacional.
 *
 * **Multi-tenant Tier 0** (ADR 0093): mantém `business_id` no contexto sem
 * redacionar (chave operacional, não PII).
 *
 * **Reuso PiiRedactor canônico** (`Modules\Jana\Services\Privacy\PiiRedactor`):
 * mesmo regex set BR (CPF/CNPJ/CEP/email/telefone), modo `placeholder` default
 * pra preservar legibilidade em troubleshooting.
 *
 * Uso:
 * ```php
 * app(FinanceiroAuditLogger::class)->info(
 *     'titulo_baixa.skip_no_conta',
 *     ['business_id' => 4, 'titulo_id' => 123, 'observacoes' => 'CPF 123.456.789-00']
 * );
 * // Log final: ['observacoes' => 'CPF [REDACTED:CPF]', business_id intacto]
 * ```
 *
 * @see Modules\Jana\Services\Privacy\PiiRedactor
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 */
class FinanceiroAuditLogger
{
    /**
     * Chaves do payload que NUNCA são redacionadas (chaves operacionais
     * críticas pra correlacionar eventos em log). business_id é Tier 0 —
     * preservar é dever de auditoria.
     */
    private const KEYS_SKIP_REDACTION = [
        'business_id', 'titulo_id', 'tx_id', 'tp_id',
        'conta_bancaria_id', 'boleto_remessa_id', 'baixa_id',
        'transaction_id', 'transaction_payment_id', 'invoice_no',
        'idempotency_key', 'status', 'tipo', 'origem', 'origem_id',
        'parcela_numero', 'cliente_id',
    ];

    public function __construct(private readonly PiiRedactor $redactor)
    {
    }

    public function info(string $message, array $context = []): void
    {
        Log::info($message, $this->redactContext($context));
    }

    public function warning(string $message, array $context = []): void
    {
        Log::warning($message, $this->redactContext($context));
    }

    public function error(string $message, array $context = []): void
    {
        Log::error($message, $this->redactContext($context));
    }

    public function debug(string $message, array $context = []): void
    {
        Log::debug($message, $this->redactContext($context));
    }

    /**
     * Redaciona valores STRING do contexto, preservando chaves operacionais
     * (KEYS_SKIP_REDACTION) e tipos não-string (int/float/bool/array).
     *
     * Arrays aninhados são tratados recursivamente via PiiRedactor::redactArray.
     */
    private function redactContext(array $context): array
    {
        $out = [];
        foreach ($context as $key => $value) {
            if (in_array($key, self::KEYS_SKIP_REDACTION, true)) {
                $out[$key] = $value;
                continue;
            }
            if (is_string($value)) {
                $out[$key] = $this->redactor->redact($value);
            } elseif (is_array($value)) {
                $out[$key] = $this->redactor->redactArray($value);
            } else {
                $out[$key] = $value;
            }
        }
        return $out;
    }
}
