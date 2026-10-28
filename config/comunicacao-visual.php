<?php

declare(strict_types=1);

/**
 * Chaves de ambiente da Comunicação Visual. O resto da config fica em
 * Modules/ComunicacaoVisual/Config/config.php e é mesclado pelo ServiceProvider (mergeConfigFrom).
 *
 * `env()` mora aqui, e não no config do módulo, porque fora de config/ ele devolve null com a
 * config em cache (regra do Larastan, ratchet do PHPStan).
 */
return [
    // US-COMVIS-005: pós-cálculo da OS (custo orçado × real e margem). Nasce desligada.
    'pos_calculo' => (bool) env('COMVIS_POS_CALCULO_ENABLED', false),
];
