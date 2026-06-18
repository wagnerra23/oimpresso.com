<?php

declare(strict_types=1);

namespace App\Support\Errors;

/**
 * Classification — o carimbo de um erro na origem (Fase 1 · E-1).
 *
 * Imutável. NUNCA carrega trace nem PII — o `operatorMessage` é texto humano
 * genérico de recuperação, o `dedupKey` é um hash (Fase 2 agrupa por ele).
 *
 * @see prototipo-ui/handoffs/erros-fase1-classificacao.md
 */
final readonly class Classification
{
    public function __construct(
        public Severity $severity,
        public Audience $audience,
        /** Dono do erro (ex: 'plataforma', 'fiscal', 'cobranca', 'app'). */
        public string $owner,
        /** hash(classe + local + business_id) — Fase 2 agrupa; aqui já carimba. */
        public string $dedupKey,
        /** Texto de recuperação pro operador. NUNCA o trace. */
        public string $operatorMessage,
    ) {}

    /**
     * Campos seguros pra log/auditoria — sem trace, sem PII (LGPD).
     *
     * @return array{severity: string, audience: string, owner: string, dedup_key: string}
     */
    public function toAuditArray(): array
    {
        return [
            'severity'  => $this->severity->value,
            'audience'  => $this->audience->value,
            'owner'     => $this->owner,
            'dedup_key' => $this->dedupKey,
        ];
    }
}
