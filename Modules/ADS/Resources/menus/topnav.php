<?php

/**
 * TopNav declarativo do ADS — Cognitive Control Panel consolidado.
 *
 * 13 → 10 itens (Movement D consolidação): Conflitos/Confidence/Learning Pipeline
 * NÃO aparecem mais no menu — agora são tabs internas das telas principais.
 *
 * Estrutura: Estratégia → Decisão → Conhecimento → Governança → Medição.
 */

return [
    'label' => 'ADS',
    'icon'  => 'Brain',
    'items' => [
        // ─── ESTRATÉGIA ───
        ['label' => 'Projects',          'href' => '/ads/admin/projects',    'icon' => 'FolderKanban','can' => 'ads.access'],

        // ─── DECISÃO ───
        // (Conflitos virou tab dentro de Decisões)
        ['label' => 'Decisões',          'href' => '/ads/admin/decisoes',    'icon' => 'Inbox',       'can' => 'ads.decisoes.review'],

        // ─── CONHECIMENTO ───
        ['label' => 'KB →',              'href' => '/kb',                    'icon' => 'BookOpen',    'can' => 'ads.access'],
        ['label' => 'Skills',            'href' => '/ads/admin/skills',      'icon' => 'Zap',         'can' => 'ads.access'],
        // (Confidence virou tab dentro de Skills)
        ['label' => 'Tools',             'href' => '/ads/admin/tools',       'icon' => 'Wrench',      'can' => 'ads.access'],
        ['label' => 'Knowledge Graph',   'href' => '/ads/admin/graph',       'icon' => 'GitBranch',   'can' => 'ads.access'],

        // ─── GOVERNANÇA ───
        ['label' => 'Meta-skills',       'href' => '/ads/admin/meta-skills', 'icon' => 'Brain',       'can' => 'ads.access'],
        ['label' => 'Team Scopes',       'href' => '/ads/admin/team-scopes', 'icon' => 'Users',       'can' => 'ads.access'],
        ['label' => 'Policy',            'href' => '/ads/admin/policy',      'icon' => 'ShieldCheck', 'can' => 'ads.policy.manage'],

        // ─── MEDIÇÃO ───
        // (Learning Pipeline virou tab dentro de Métricas)
        ['label' => 'Métricas',          'href' => '/ads/admin/metricas',    'icon' => 'BarChart3',   'can' => 'ads.access'],
    ],
];
