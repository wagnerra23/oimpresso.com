<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Business;
use PHPUnit\Framework\Assert;

/**
 * Tenant canônico de teste — biz=1 (ADR 0101: tests SEMPRE business_id=1, nunca cliente real).
 *
 * Substitui o `Business::first` cru espalhado pelos testes da raiz (FV-B4, plano SDD
 * 2026-06-12): resolução explícita do tenant seedado + mensagem acionável quando o seed
 * mínimo não rodou — em vez de null-pointer ou skip genérico "Sem business em DB".
 * (Menções aqui SEM parênteses de propósito: a catraca foundation-ratchet conta texto cru.)
 *
 * Onde o seed canônico nasce:
 *  - CI MySQL: .github/actions/pest-mysql-setup/action.yml (biz=1 fixture + biz=2 Tier 0)
 *  - Nightly CT 100: scripts/tests/ct100-fullsuite.sh (mesmo seed do canon CI)
 *  - Browser/visreg: seeder commitado (transação não cruza o subprocesso do server)
 *
 * Aplicado em Tests\TestCase → disponível em qualquer teste Pest da raiz via
 * `$this->seededTenant()`. Em contexto estático (runners), `static::resolveSeededTenant()`.
 *
 * @see memory/decisions/0101-tests-business-id-1-nunca-cliente.md
 * @see memory/requisitos/Infra/RUNBOOK-ct100-fullsuite.md
 */
trait WithSeededTenant
{
    /** id canônico do tenant de teste (ADR 0101) — biz=1 (WR2/Wagner), NUNCA cliente real. */
    public const SEEDED_TENANT_ID = 1;

    /**
     * Resolve o tenant seedado: biz=1 quando existe (seed canônico); senão o primeiro
     * business do DB (paridade com o antigo `Business::first` em schemas montados pelo
     * próprio teste, onde o id pode não ser 1). Null = seed não rodou.
     */
    public static function resolveSeededTenant(): ?Business
    {
        return Business::query()->whereKey(self::SEEDED_TENANT_ID)->first()
            ?? Business::query()->orderBy('id')->first();
    }

    /**
     * Tenant canônico de teste ou skip-graceful com mensagem acionável.
     *
     * Comportamento idêntico ao padrão `Business::first` + guard que substitui:
     * com seed presente → mesmo Business de antes; sem seed → skip (só que a mensagem
     * agora diz COMO seedar em vez de "Sem business em DB").
     */
    public function seededTenant(): Business
    {
        $tenant = static::resolveSeededTenant();

        if ($tenant === null) {
            Assert::markTestSkipped(
                'Tenant canônico de teste ausente (tabela business vazia). Seed mínimo biz=1: '
                . '.github/actions/pest-mysql-setup (CI MySQL) · scripts/tests/ct100-fullsuite.sh '
                . '(nightly CT 100). Ver ADR 0101.'
            );
        }

        return $tenant;
    }
}
