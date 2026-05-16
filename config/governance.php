<?php

declare(strict_types=1);

/**
 * Config canônico do módulo Governance.
 *
 * Centraliza flags de behavior switch sem precisar tocar Service. Cada flag
 * mapeada pra env var permite ativação por ambiente (dev/staging/prod) sem
 * deploy de código.
 *
 * @see Modules/Governance/Services/ModuleGradeService.php
 * @see memory/decisions/0157-module-grade-v3-d2-detection-hardening.md
 * @see memory/decisions/0158-module-grade-v3-d1-heuristica-hardening.md
 */

return [

    /*
    |--------------------------------------------------------------------------
    | D2 detection hardening (ADR 0157 — aceita Wave 12, ATIVADA Wave 14)
    |--------------------------------------------------------------------------
    |
    | Endurecimento da heurística D2 (Pest cobertura) — 3 frentes:
    |   - D2.a: conta apenas test files em pastas REGISTRADAS no phpunit.xml
    |   - D2.b: além do NOME do arquivo, exige asserção real no corpo
    |   - D2.c: parser XML estruturado (SimpleXMLElement) com granularidade
    |           parcial (1 dir = 2 pts) vs integral (2+ dirs = 4 pts)
    |
    | Default `true` desde Wave 14 (2026-05-16) — Wagner aprovou ativação após
    | ADRs 0157+0158 aceitas e tests Pest cobrindo dual-mode com cenários reais.
    |
    | Quando desativar (`GOVERNANCE_D2_HARDENED=false` no .env):
    |   - Bug catastrófico zera D2 de >5 módulos simultaneamente em prod
    |   - Investigação de regressão exige comparar runs hardened vs legacy
    |   - Rollback temporário de ADR 0157 via emergência (ADR nova de reversão)
    */
    'd2_hardened' => env('GOVERNANCE_D2_HARDENED', true),

];
