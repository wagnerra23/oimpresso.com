<?php

declare(strict_types=1);

/**
 * Chaves do módulo Compras que leem a env (raiz `config/compras.php`).
 *
 * Por que raiz: a regra do Larastan noEnvCallsOutsideOfConfig. Medido em produção em
 * 2026-11-06: com o config em cache, a chave do módulo (`custo_medio_movel`, que lê
 * env() em Modules/Compras/Config/config.php) também está no cache, então não é um caso
 * de "env() volta null" para esse arquivo. O ComprasServiceProvider
 * faz `mergeConfigFrom(Modules/Compras/Config/config.php, 'compras')`, que mantém
 * as chaves daqui e acrescenta as do módulo — `config('compras.*')` segue igual.
 * Mesmo desenho do `config/financeiro.php`.
 */
return [
    // US-ESTOQUE-003: curva ABC e sugestão de reposição pelo giro (só leitura). Desligada.
    'curva_abc_reposicao' => (bool) env('COMPRAS_CURVA_ABC_REPOSICAO', false),
];
