<?php

declare(strict_types=1);

namespace Modules\Jana\Services\Lgpd;

/**
 * DsrEsquecimentoResult — DTO de retorno do DsrService::esquecerTitular().
 *
 * LGPD Art. 18 §VI (direito de eliminação) exige que o controlador comprove
 * que executou a solicitação. Este DTO carrega o trail completo: refs encontradas
 * por entidade + IDs afetados + audit_trail_id (Spatie ActivityLog) + timestamp.
 *
 * @see Modules\Jana\Services\Lgpd\DsrService
 */
class DsrEsquecimentoResult
{
    /**
     * @param  string  $cpfOuCnpj  Documento normalizado (só dígitos)
     * @param  int  $businessId  Tenant onde o titular foi esquecido
     * @param  array<string,array{rows_matched:int,rows_anonymized:int,rows_deleted:int,ids:array<int,int>}>  $refsByEntity
     *        Map por entidade canon: rows_matched (total achado), rows_anonymized
     *        (PII redacted), rows_deleted (hard delete), ids (PKs afetados — útil
     *        pra audit forense)
     * @param  string  $auditTrailId  UUID do batch ActivityLog (rastreia tudo em 1 query)
     * @param  string  $startedAt  ISO 8601
     * @param  string  $finishedAt  ISO 8601
     * @param  int  $durationMs  Latência ms (prazo LGPD <30d — geralmente <5s)
     * @param  string  $status  'ok' | 'partial' | 'failed'
     * @param  string|null  $errorMessage  Quando status != 'ok'
     */
    public function __construct(
        public readonly string $cpfOuCnpj,
        public readonly int $businessId,
        public readonly array $refsByEntity,
        public readonly string $auditTrailId,
        public readonly string $startedAt,
        public readonly string $finishedAt,
        public readonly int $durationMs,
        public readonly string $status,
        public readonly ?string $errorMessage = null,
    ) {}

    /**
     * Total de refs encontradas cross-entity (rows_matched soma).
     */
    public function totalRefsEncontradas(): int
    {
        return array_sum(array_map(
            fn (array $r) => $r['rows_matched'],
            $this->refsByEntity,
        ));
    }

    /**
     * Total efetivamente anonimizado/deletado.
     */
    public function totalAcaoTomada(): int
    {
        return array_sum(array_map(
            fn (array $r) => $r['rows_anonymized'] + $r['rows_deleted'],
            $this->refsByEntity,
        ));
    }

    /**
     * @return array<string,mixed>
     */
    public function toArray(): array
    {
        return [
            'cpf_ou_cnpj_hash' => substr(hash('sha256', $this->cpfOuCnpj), 0, 12),
            'business_id' => $this->businessId,
            'refs_by_entity' => $this->refsByEntity,
            'total_refs' => $this->totalRefsEncontradas(),
            'total_acao' => $this->totalAcaoTomada(),
            'audit_trail_id' => $this->auditTrailId,
            'started_at' => $this->startedAt,
            'finished_at' => $this->finishedAt,
            'duration_ms' => $this->durationMs,
            'status' => $this->status,
            'error_message' => $this->errorMessage,
        ];
    }
}
