<?php

return [
    'name' => 'Compras',
    // Gravada em system.compras_version pelo InstallController (instalado = chave presente).
    'module_version' => '1.0',
    // US-ESTOQUE-003: curva ABC e sugestão de reposição pelo giro (só leitura). Desligada.
    'curva_abc_reposicao' => (bool) env('COMPRAS_CURVA_ABC_REPOSICAO', false),
];
