<?php

return [
    'module_label' => 'Project Mgmt',

    'menu' => [
        // herdadas do Modules/TeamMcp (apagado 2026-07-31)
        'team'        => 'Team Admin',
        'tasks'       => 'Task Board',
        'cc_sessions' => 'CC sessions',
        'board'   => 'Board',
        'backlog' => 'Backlog',
        'my_work' => 'My Work',
        'roadmap' => 'Roadmap',
        'inbox'   => 'Inbox',
        'triage'  => 'Triage',
    ],

    'board' => [
        'title'           => 'Board (Kanban)',
        'cycle_active'    => 'Active cycle',
        'no_active_cycle' => 'No active cycle on this project. Use `cycles-create` via MCP to start.',
        'empty_column'    => 'empty',
        'columns'         => [
            'backlog' => 'Backlog',
            'todo'    => 'To do',
            'doing'   => 'Doing',
            'review'  => 'Review',
            'done'    => 'Done',
            'blocked' => 'Blocked',
        ],
    ],
];
