<?php

return [
    'name' => 'TeamMcp',

    /*
    |--------------------------------------------------------------------------
    | Identificação do módulo na UI / instalador
    |--------------------------------------------------------------------------
    */
    'module_label'       => 'Team MCP',
    'module_description' => 'Governança self-host: tokens MCP, DXT, quotas, Kanban e auditoria Claude Code do time.',
    'module_icon'        => 'fa fa-users',
    'module_version'     => '0.1',
    'pid'                => null,

    /*
    |--------------------------------------------------------------------------
    | Loop de Handoff Zero-Paste (Fase 0 · ADR 0283)
    |--------------------------------------------------------------------------
    | Segredo HMAC pra validar a proveniência dos handoffs de design (A1). Vive
    | SÓ no env do servidor + secret do pipeline de export do Cowork — NUNCA
    | versionado, nunca no Cowork, nunca no Code. `handoff:ingest` aborta se vazio.
    */
    'handoff_secret' => env('HANDOFF_SECRET'),
];
