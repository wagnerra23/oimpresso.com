<?php

return [
    'module_label' => 'Project Mgmt',

    'menu' => [
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
