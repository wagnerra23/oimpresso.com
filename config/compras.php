<?php

declare(strict_types=1);

/**
 * Chaves do módulo Compras que leem a env (raiz `config/compras.php`).
 *
 * Por que raiz: `env()` fora do diretório `config/` devolve null quando o config
 * está em cache (Larastan noEnvCallsOutsideOfConfig). O ComprasServiceProvider
 * faz `mergeConfigFrom(Modules/Compras/Config/config.php, 'compras')`, que mantém
 * as chaves daqui e acrescenta as do módulo — `config('compras.*')` segue igual.
 * Mesmo desenho do `config/financeiro.php`.
 */
return [
    // US-ESTOQUE-003: curva ABC e sugestão de reposição pelo giro (só leitura). Desligada.
    'curva_abc_reposicao' => (bool) env('COMPRAS_CURVA_ABC_REPOSICAO', false),
];
