<?php

/*
|--------------------------------------------------------------------------
| Menu NFSe — topnav UltimatePOS
|--------------------------------------------------------------------------
| Ativado quando módulo NFSe estiver habilitado para o business.
| Permissão: nfse.view
*/

return [
    [
        'label'      => __('nfse::lang.nfse'),
        'icon'       => 'fas fa-file-invoice',
        'route'      => 'nfse.index',
        'permission' => 'nfse.view',
        'active'     => 'nfse.*',
    ],
];
