<?php

/**
 * TopNav declarativo do Repair (Reparar).
 *
 * Lido pelo LegacyMenuAdapter::buildTopNavs() e exposto em
 * `shell.topnavs.Repair` via Inertia. Renderizado como navbar horizontal
 * abaixo do topbar do AppShellV2 quando o usuário está numa tela MWART
 * de /repair/* (auto-detect via useAutoModuleNav).
 *
 * Itens refletem as 4 telas Sprint 2.5 já em prod + Settings.
 *
 * Permissions: usa 'repair_module' que é a subscription check feita no
 * controller (idem padrão UltimatePOS legacy).
 */

return [
    'label' => 'Reparar',
    'icon'  => 'Wrench',
    'items' => [
        ['label' => 'Dashboard',        'href' => '/repair/dashboard',     'icon' => 'LayoutDashboard'],
        ['label' => 'Produção · Oficina', 'href' => '/repair/producao-oficina', 'icon' => 'KanbanSquare'],
        ['label' => 'Folhas de OS',     'href' => '/repair/job-sheet',     'icon' => 'ClipboardList'],
        ['label' => 'Status',           'href' => '/repair/status',        'icon' => 'Flag'],
        ['label' => 'Modelos',          'href' => '/repair/device-models', 'icon' => 'Smartphone'],
        ['label' => 'Configurações',    'href' => '/repair/repair-settings', 'icon' => 'Settings'],
    ],
];
