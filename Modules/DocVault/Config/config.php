<?php

return [
    'name' => 'DocVault',

    'module_label'       => 'DocVault',
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
        'directory'       => 'docvault',
        'max_size_kb'     => 20480, // 20 MB
        'allowed_mimes'   => ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'txt', 'md', 'log', 'json', 'html'],
    ],
];
