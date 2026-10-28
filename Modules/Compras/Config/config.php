<?php

return [
    'name' => 'Compras',
    // Gravada em system.compras_version pelo InstallController (instalado = chave presente).
    'module_version' => '1.0',
    // US-ESTOQUE-003: a flag `curva_abc_reposicao` lê a env em config/compras.php (raiz),
    // porque env() fora do diretório config/ volta null com o config em cache.
];
