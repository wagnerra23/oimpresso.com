<?php

return [
    'name' => 'Officeimpresso',
    'module_version' => '1.0',
    'pid' => 19,

    /*
     * Credencial do Firebird legado lida pelo `officeimpresso:import`.
     *
     * A senha vem SÓ do ambiente: sem default (o default de fábrica do Firebird não
     * pode ser a senha de nenhuma base de cliente) e sem opção de linha de comando
     * (fica no histórico do shell). Sem OFFICEIMPRESSO_FB_PASSWORD o importador recusa
     * conectar no .fdb.
     */
    'firebird_importer' => [
        'user' => env('OFFICEIMPRESSO_FB_USER', 'SYSDBA'),
        'password' => env('OFFICEIMPRESSO_FB_PASSWORD'),
    ],
];
