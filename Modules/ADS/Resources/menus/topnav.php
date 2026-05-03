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
    'label' => 'ADS',
    'icon'  => 'Brain',
    'items' => [
        ['label' => 'Decisões',   'href' => '/ads/admin/decisoes',   'icon' => 'Inbox',       'can' => 'ads.decisoes.review'],
        ['label' => 'Métricas',   'href' => '/ads/admin/metricas',   'icon' => 'BarChart3',   'can' => 'ads.access'],
        ['label' => 'Confidence', 'href' => '/ads/admin/confidence', 'icon' => 'TrendingUp',  'can' => 'ads.access'],
        ['label' => 'Padrões',    'href' => '/ads/admin/patterns',   'icon' => 'Zap',         'can' => 'ads.access'],
        ['label' => 'Policy',     'href' => '/ads/admin/policy',     'icon' => 'ShieldCheck', 'can' => 'ads.policy.manage'],
    ],
];
