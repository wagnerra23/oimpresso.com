<?php

declare(strict_types=1);

namespace Modules\RecurringBilling\Contracts;

/**
 * Contract Wave 23 D2 — reuso cross-module BoletoCredentialResolver.
 *
 * Permite que Financeiro/NfeBrasil/outros módulos consumam credenciais boleto
 * sem acoplar à classe concreta. Container registra
 * `BoletoCredentialResolverInterface => BoletoCredentialResolver` em
 * `Modules/RecurringBilling/Providers/RecurringBillingServiceProvider.php`.
 *
 * Multi-tenant Tier 0 IRREVOGÁVEL (ADR 0093): TODOS os métodos exigem
 * `$businessId` explícito.
 *
 * @see Modules\RecurringBilling\Services\Boleto\BoletoCredentialResolver
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 */
interface BoletoCredentialResolverInterface
{
    /**
     * @return array{banco: string, config: array<string, mixed>, ambiente: string}
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function resolve(int $businessId): array;

    /**
     * Cheap: apenas o nome do banco, sem decifrar config.
     */
    public function resolveDriverName(int $businessId): string;

    /**
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    public function decryptConfig(array $config): array;
}
