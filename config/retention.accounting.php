<?php

declare(strict_types=1);

/**
 * Shim de retenção LGPD — Modules/Accounting (Wave 27 polish 2026-05-17).
 *
 * Este arquivo é apenas um **alias** ao config canônico do módulo, mantido em
 * `Modules/Accounting/Config/retention.php` (Wave 11 D7.c).
 *
 * Motivo do shim:
 *  - Convenção `config/retention.<module>.php` é padrão de Ondas Governance
 *    (ver `config/retention.ads.php` + `config/retention.whatsapp.php`)
 *  - Esse arquivo permite que jobs/CRON globais (futuros: `retention:purge-all`)
 *    iterem sobre `config('retention.*')` sem precisar conhecer cada módulo
 *  - Mantém o canônico DENTRO do módulo (modularidade nWidart preservada)
 *
 * Bases legais detalhadas + categorias completas no canônico do módulo.
 *
 * @see Modules/Accounting/Config/retention.php (canônico — categorias, dias, leis)
 * @see config/retention.ads.php (mesmo pattern shim — referência)
 * @see config/retention.whatsapp.php
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 * @see memory/decisions/0155-module-grade-rubrica-v3.md (D7.c retention)
 */

$modulePath = __DIR__ . '/../Modules/Accounting/Config/retention.php';

if (! file_exists($modulePath)) {
    // Fallback defensivo — se o módulo for desativado, retorna config vazio
    // em vez de quebrar boot global. Cron purge respeita config vazio = no-op.
    return [
        '_warning' => 'Modules/Accounting/Config/retention.php ausente — módulo desativado?',
        '_categories' => [],
    ];
}

return require $modulePath;
