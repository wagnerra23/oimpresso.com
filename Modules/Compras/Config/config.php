<?php

return [
    'name' => 'Compras',
    // Gravada em system.compras_version pelo InstallController (instalado = chave presente).
    'module_version' => '1.0',
    // US-ESTOQUE-003: a flag `curva_abc_reposicao` lê a env em config/compras.php (raiz), por
    // causa da regra do Larastan (noEnvCallsOutsideOfConfig). A justificativa antiga, "env() fora de
    // config/ volta null com o config em cache", não vale para este arquivo: medido em produção em
    // 2026-11-06, o bootstrap/cache/config.php traz as duas chaves (este arquivo é mesclado por
    // mergeConfigFrom durante o config:cache). Não medido com COMPRAS_CUSTO_MEDIO_MOVEL definida:
    // a env não existe no .env de produção, e o valor no cache é o default (false).
    // US-PROD-024 fase 2: custo médio móvel por variação, recalculado a cada compra recebida.
    // Desligado: liga só depois da semente (compras:custo-medio-semear) aprovada pelo [W].
    'custo_medio_movel' => (bool) env('COMPRAS_CUSTO_MEDIO_MOVEL', false),
];
