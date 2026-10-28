<?php

return [
    'name' => 'Compras',
    // Gravada em system.compras_version pelo InstallController (instalado = chave presente).
    'module_version' => '1.0',
    // US-ESTOQUE-003: a flag `curva_abc_reposicao` lê a env em config/compras.php (raiz),
    // porque env() fora do diretório config/ volta null com o config em cache.
    // US-PROD-024 fase 2: custo médio móvel por variação, recalculado a cada compra recebida.
    // Desligado: liga só depois da semente (compras:custo-medio-semear) aprovada pelo [W].
    'custo_medio_movel' => (bool) env('COMPRAS_CUSTO_MEDIO_MOVEL', false),
];
