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
 */

return [

    /*
    |--------------------------------------------------------------------------
    | D2 detection hardening (ADR 0157)
    |--------------------------------------------------------------------------
    |
    | Endurecimento da heurística D2 (Pest cobertura) — 3 frentes:
    |   - D2.a: conta apenas test files em pastas REGISTRADAS no phpunit.xml
    |   - D2.b: além do NOME do arquivo, exige asserção real no corpo
    |   - D2.c: parser XML estruturado (SimpleXMLElement) com granularidade
    |           parcial (1 dir = 2 pts) vs integral (2+ dirs = 4 pts)
    |
    | Default `false` = comportamento legacy v3 (ADR 0155) preservado.
    | Ativar via `GOVERNANCE_D2_HARDENED=true` no .env quando baseline novo
    | aceito Wagner (Fase 2 do plano da ADR 0157).
    */
    'd2_hardened' => env('GOVERNANCE_D2_HARDENED', false),

];
