<?php

return [
    'name' => 'MemCofre',

    'module_label'       => 'MemCofre',
    'module_description' => 'Documentação viva · evidências → requisitos',
    'module_icon'        => 'fa fa-folder-open',
    'module_version'     => '0.1',

    /*
    |--------------------------------------------------------------------------
    | Onde ficam os requisitos funcionais consolidados
    |--------------------------------------------------------------------------
    */
    'requirements_dir' => base_path('memory/requisitos'),

    /*
    |--------------------------------------------------------------------------
    | Pastas de memória lidas pela tela /docs/memoria
    |--------------------------------------------------------------------------
    | project_dir: memória do projeto, versionada no git (handoff, ADRs
    |   globais, sessions, convenções). Exclui subpastas dedicadas que já
    |   aparecem em outras telas (requisitos/, modulos/, memory_backup/).
    |
    | claude_dir: memória pessoal persistida pelo Claude Code entre sessões
    |   (user profile, feedback, references). Vive fora do repo, no
    |   perfil do usuário. Caminho derivado de USERPROFILE; configurável
    |   por CLAUDE_MEMORY_DIR no .env pra outros ambientes.
    */
    'memory' => [
        // Root 1 — primer do projeto (CLAUDE.md + AGENTS.md se existir).
        'primer_files' => [
            base_path('CLAUDE.md'),
            base_path('AGENTS.md'),
        ],

        // Root 2 — memória do projeto (versionada no git).
        'project_dir' => base_path('memory'),

        // Root 3 — memória persistente do Claude Code (fora do repo).
        'claude_dir'  => env(
            'CLAUDE_MEMORY_DIR',
            ($_SERVER['USERPROFILE'] ?? $_SERVER['HOME'] ?? '')
                . DIRECTORY_SEPARATOR . '.claude'
                . DIRECTORY_SEPARATOR . 'projects'
                . DIRECTORY_SEPARATOR . 'D--oimpresso-com'
                . DIRECTORY_SEPARATOR . 'memory'
        ),
    ],

    /*
    |--------------------------------------------------------------------------
    | Tipos de fonte aceitos no ingest
    |--------------------------------------------------------------------------
    */
    'source_types' => [
        'screenshot' => 'Captura de tela',
        'chat'       => 'Conversa / chat log',
        'error'      => 'Erro reportado',
        'file'       => 'Arquivo (PDF, DOC, MD, etc)',
        'text'       => 'Texto/nota livre',
        'url'        => 'Link (issue, wiki, documento)',
    ],

    /*
    |--------------------------------------------------------------------------
    | Status do workflow de evidência
    |--------------------------------------------------------------------------
    */
    'evidence_status' => [
        'pending'   => 'Pendente (aguarda classificação)',
        'triaged'   => 'Triado (classificado, aguarda aprovação)',
        'applied'   => 'Aplicado (virou requisito)',
        'rejected'  => 'Rejeitado (ruído)',
        'duplicate' => 'Duplicado',
    ],

    /*
    |--------------------------------------------------------------------------
    | Integração com IA (Fase 2 — opcional por ora)
    |--------------------------------------------------------------------------
    */
    'ai' => [
        'enabled'      => env('DOCVAULT_AI_ENABLED', false),
        'model'        => env('DOCVAULT_AI_MODEL', 'gpt-4o-mini'),
        'max_tokens'   => 800,
        'temperature'  => 0.2,
    ],

    /*
    |--------------------------------------------------------------------------
    | Upload
    |--------------------------------------------------------------------------
    */
    'upload' => [
        'disk'            => 'public',
        'directory'       => 'memcofre',
        'max_size_kb'     => 20480, // 20 MB
        'allowed_mimes'   => ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'txt', 'md', 'log', 'json', 'html'],
    ],
];
