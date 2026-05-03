<?php

/**
 * TopNav declarativo do ADS — modelo Cognitive Control Panel.
 *
 * Estrutura mental: Decisão → Conhecimento → Evolução → Controle.
 * Items ordenados por essa hierarquia.
 */

return [
    'label' => 'ADS',
    'icon'  => 'Brain',
    'items' => [
        // ─── ESTRATÉGIA ───
        ['label' => 'Projects',          'href' => '/ads/admin/projects',    'icon' => 'FolderKanban','can' => 'ads.access'],

        // ─── DECISÃO ───
        ['label' => 'Decisões',          'href' => '/ads/admin/decisoes',    'icon' => 'Inbox',       'can' => 'ads.decisoes.review'],
        ['label' => 'Conflitos',         'href' => '/ads/admin/conflicts',   'icon' => 'AlertTriangle','can' => 'ads.access'],

        // ─── CONHECIMENTO ───
        ['label' => 'Skills',            'href' => '/ads/admin/skills',      'icon' => 'Zap',         'can' => 'ads.access'],
        ['label' => 'Meta-skills',       'href' => '/ads/admin/meta-skills', 'icon' => 'Brain',       'can' => 'ads.access'],
        ['label' => 'Tools',             'href' => '/ads/admin/tools',       'icon' => 'Wrench',      'can' => 'ads.access'],
        ['label' => 'Knowledge Graph',   'href' => '/ads/admin/graph',       'icon' => 'GitBranch',   'can' => 'ads.access'],

        // ─── EVOLUÇÃO ───
        ['label' => 'Learning Pipeline', 'href' => '/ads/admin/learning',    'icon' => 'Repeat',      'can' => 'ads.access'],

        // ─── CONTROLE ───
        ['label' => 'Métricas',          'href' => '/ads/admin/metricas',    'icon' => 'BarChart3',   'can' => 'ads.access'],
        ['label' => 'Confidence',        'href' => '/ads/admin/confidence',  'icon' => 'TrendingUp',  'can' => 'ads.access'],
        ['label' => 'Policy',            'href' => '/ads/admin/policy',      'icon' => 'ShieldCheck', 'can' => 'ads.policy.manage'],
    ],
];
