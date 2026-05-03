<?php

/**
 * TopNav declarativo do ADS.
 *
 * Espelho React do padrão UltimatePOS. Lido pelo LegacyMenuAdapter::buildTopNavs()
 * e exposto em `shell.topnavs.ADS` via Inertia.
 *
 * Convenções (feedback_topnav_i18n_pattern):
 * - Ordem do array = ordem na barra horizontal (esquerda → direita)
 * - `can` opcional — Spatie filtra por permissão do user
 * - `icon` usa nome Lucide
 * - `href` é path relativo (/ads/...)
 * - `label` aceita literal OU chave `ads::ads.x` (resolveLabel converte)
 */

return [
    'label' => 'ads::ads.module_label',
    'icon'  => 'Brain',
    'items' => [
        [
            'label' => 'ads::ads.menu.decisoes',
            'href'  => '/ads/admin/decisoes',
            'icon'  => 'Inbox',
            'can'   => 'ads.decisoes.review',
        ],
    ],
];
