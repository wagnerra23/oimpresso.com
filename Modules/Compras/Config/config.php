<?php

return [
    'name' => 'Compras',
    // Gravada em system.compras_version pelo InstallController (instalado = chave presente).
    'module_version' => '1.0',
    // US-PROD-024 fase 2: custo médio móvel por variação, recalculado a cada compra recebida.
    // Desligado: liga só depois da semente (compras:custo-medio-semear) aprovada pelo [W].
    'custo_medio_movel' => (bool) env('COMPRAS_CUSTO_MEDIO_MOVEL', false),
];
