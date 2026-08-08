<?php

return [
    'module_label' => 'Project Mgmt',

    'menu' => [
        // herdadas do Modules/TeamMcp (apagado 2026-07-31)
        'team'        => 'Team Admin',
        'tasks'       => 'Task Board',
        'cc_sessions' => 'CC do time',
        'board'   => 'Board',
        'backlog' => 'Backlog',
        'my_work' => 'My Work',
        'roadmap' => 'Roadmap',
        'inbox'   => 'Inbox',
        'triage'  => 'Triage',
    ],

    'board' => [
        'title'           => 'Board (Kanban)',
        'cycle_active'    => 'Cycle ativo',
        'no_active_cycle' => 'Nenhum cycle ativo neste projeto. Use `cycles-create` via MCP pra começar.',
        'empty_column'    => 'vazio',
        'columns'         => [
            'backlog' => 'Backlog',
            'todo'    => 'A fazer',
            'doing'   => 'Fazendo',
            'review'  => 'Revisão',
            'done'    => 'Concluído',
            'blocked' => 'Bloqueado',
        ],
    ],
];
