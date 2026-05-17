<?php

declare(strict_types=1);

/**
 * Retention canônico ComunicacaoVisual — espelho governance-grade do
 * Modules/ComunicacaoVisual/Config/retention.php (canon module-level).
 *
 * Wave 26 — fix D7.c rubrica ModuleGradeService::dim7LgpdCompliance() busca este
 * path exato `config/retention.{modulo}.php` (case-insensitive) pra pontuar D7.c=3.
 *
 * Conteúdo idêntico ao module-level (single source of truth via require).
 * Justificativa: rubrica não consegue scanear `Modules/<X>/Config/retention.php`
 * direto — depende de path canônico base_path/config/ pra evitar duplicação por módulo
 * no scan global. Este shim mantém compat sem duplicar declaração legal.
 *
 * Multi-tenant Tier 0 ([ADR 0093](memory/decisions/0093-multi-tenant-isolation-tier-0.md)):
 * job de expurgo SEMPRE recebe $businessId no constructor (session() não roda em fila).
 *
 * @see Modules/ComunicacaoVisual/Config/retention.php (canon)
 * @see Modules/Governance/Services/ModuleGradeService::dim7LgpdCompliance()
 */

return require base_path('Modules/ComunicacaoVisual/Config/retention.php');
