<?php

declare(strict_types=1);

namespace Modules\Governance\Contracts;

use Modules\Governance\Services\DriftCheckResult;

/**
 * Interface canônica DriftChecker — ADR 0216.
 *
 * Cada checker implementa scan de drift em UM domínio (secrets, multi-tenant,
 * deps CVE, ADR links, etc.). Registry orquestra via {@see \Modules\Governance\Services\DriftCheckerRegistry}.
 *
 * Convenções (Wagner):
 * - Severity: 'critical|high|medium|low|info' (Datadog/CSPM model)
 * - Enforcement: 'advisory|warn|block' (HashiCorp Sentinel model)
 * - Cadence: 'on_commit|on_pr|hourly|daily|weekly'
 * - Idempotência: persistirAlerta via trait PersistsDriftAlert (chave_idempotencia diária)
 *
 * Referências:
 * - ADR 0216 §Decisão (interface canônica)
 * - ADR 0215 §Camadas (mapping 1:1 pra secrets como case particular)
 * - ADR 0093 (multi-tenant Tier 0 — checkers per-business futuros respeitam)
 */
interface DriftChecker
{
    /**
     * Identificador canônico, snake_case, único no registry.
     * Convenção: '<dominio>_<acao>' — ex: 'multi_tenant_scope', 'composer_audit', 'adr_link_rot'.
     */
    public function name(): string;

    /**
     * Descrição humana 1-line para CLI table + dashboard.
     */
    public function description(): string;

    /**
     * Tags pra filtragem via `--tag=<tag>`.
     * Convenção: ['tier_0', 'tier_1', 'tier_2'] + dominio ['security', 'compliance', 'tech_debt'].
     *
     * @return array<int, string>
     */
    public function tags(): array;

    /**
     * Severity baseline do checker (sobrescrita por finding individual em runtime).
     * 'critical' = Tier 0 IRREVOGÁVEL (multi-tenant leak, secret leak).
     * 'high' = supply chain CVE, ADR canon broken link.
     * 'medium' = drift module scope, route zombie.
     * 'low|info' = feature flag zumbi, charter desatualizada.
     */
    public function severity(): string;

    /**
     * Nível de enforcement (Sentinel model).
     * 'advisory' = sempre passa, só registra.
     * 'warn' = CI comment + Brief Jana, NÃO bloqueia merge.
     * 'block' = pre-commit hook + CI gate fail.
     */
    public function enforcement(): string;

    /**
     * Cadência sugerida do scheduler.
     * 'on_commit' = pre-commit hook (diff-only mode)
     * 'on_pr' = GH Action PR trigger
     * 'hourly|daily|weekly' = cron Kernel.php
     */
    public function cadence(): string;

    /**
     * Executa scan. Opcionais via $opts:
     * - 'diff_only' (bool) — só staged files (pre-commit mode)
     * - 'business_id' (?int) — escopo per-business (default null = repo-wide)
     * - 'auto_pr' (bool) — emitir RemediationProposal pra orchestrator abrir PR
     *
     * @param array<string, mixed> $opts
     */
    public function check(array $opts = []): DriftCheckResult;
}
