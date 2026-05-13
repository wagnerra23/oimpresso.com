<?php

/**
 * TopNav declarativo do OficinaAuto.
 *
 * Lido pelo LegacyMenuAdapter::buildTopNavs() e exposto em
 * `shell.topnavs.OficinaAuto` via Inertia. Renderizado como navbar
 * horizontal abaixo do topbar do AppShellV2 quando o usuário está numa
 * tela /oficina-auto/* (auto-detect via useAutoModuleNav).
 *
 * Permissions: usa 'oficina_auto_module' subscription check feita no
 * Controller (padrão UltimatePOS legacy).
 *
 * @see Modules/Repair/Resources/menus/topnav.php (pattern referência)
 * @see memory/decisions/0137-modules-oficinaauto-qualificada.md
 */

return [
    'label' => 'Oficina Auto',
    'icon'  => 'Wrench',
    'items' => [
        ['label' => 'Caçambas',          'href' => '/oficina-auto/veiculos',       'icon' => 'Truck'],
        ['label' => 'Ordens de Serviço', 'href' => '/oficina-auto/ordens-servico', 'icon' => 'ClipboardList'],
    ],
];
