<?php

return [
    'name'           => 'ProjectMgmt',
    'module_version' => '0.1',

    /*
    | Project default — quando o BoardController não recebe filtro `project=`,
    | usa este `key` (lookup em mcp_jira_projects). Wagner trabalha
    | majoritariamente no projeto COPI (Copiloto). Mude pra null pra mostrar
    | tasks de todos os projetos.
    */
    'default_project_key' => env('PROJECTMGMT_DEFAULT_PROJECT', 'COPI'),

    /*
    | Workflow default exibido nas colunas do Kanban. ADR 0070 define ENUM
    | em mcp_tasks.status; este array controla a ORDEM e quais colunas
    | aparecem (cancelled é oculto por default — filtra-se no Backlog).
    */
    'kanban_columns' => [
        'backlog',
        'todo',
        'doing',
        'review',
        'done',
    ],
];
