<?php

return [
    'menu_title' => 'Oficina Auto',

    'submenu' => [
        'vehicles'       => 'Veículos',
        'service_orders' => 'Ordens de Serviço',
    ],

    'permissions' => [
        'access'              => 'Acessar módulo Oficina Auto',
        'vehicle_view'        => 'Oficina Auto: ver veículos',
        'vehicle_create'      => 'Oficina Auto: criar veículos',
        'vehicle_update'      => 'Oficina Auto: editar veículos',
        'vehicle_delete'      => 'Oficina Auto: excluir veículos',
        'service_order_view'  => 'Oficina Auto: ver ordens de serviço',
        'service_order_create' => 'Oficina Auto: criar ordens de serviço',
        'service_order_update' => 'Oficina Auto: editar ordens de serviço',
        'service_order_delete' => 'Oficina Auto: excluir ordens de serviço',
    ],

    'install' => [
        'success'   => 'Módulo Oficina Auto instalado com sucesso (V0 — Vehicle + ServiceOrder CRUD).',
        'uninstall' => 'Módulo Oficina Auto desinstalado.',
        'update'    => 'Módulo Oficina Auto atualizado para a versão mais recente.',
    ],
];
