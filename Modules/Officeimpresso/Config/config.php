<?php

return [
    'name' => 'Officeimpresso',
    'module_version' => '1.0',
    'pid' => 19,

    // Clients OAuth do desktop Delphi. O listener LogPassportAccessToken so grava em
    // licenca_log o token emitido para um destes ids; os demais clients do sistema
    // (CRM, fieldforce, app) nao sao login de licenca. Lista vazia = nada e gravado.
    // 39 = client Delphi medido em producao em 2026-10-01 (comentario em
    // Modules/Superadmin/Listeners/OnCobrancaVencidaBloqueaSubscription.php). Nao
    // re-medido em 2026-11-18: CT 100 inacessivel. Sem env(): fora de config/ o
    // Larastan barra (mesma regra do delphi_master_user_ids do Connector).
    'delphi_client_ids' => ['39'],

    /*
     * Credencial do Firebird legado lida pelo `officeimpresso:import`.
     *
     * A senha vem SÓ do ambiente: sem default (o default de fábrica do Firebird não
     * pode ser a senha de nenhuma base de cliente) e sem opção de linha de comando
     * (fica no histórico do shell). Sem OFFICEIMPRESSO_FB_PASSWORD o importador recusa
     * conectar no .fdb. Aqui o env() é necessário (segredo não vai pro git) e fica no
     * baseline do PHPStan, como o de Arquivos, Compras e Marketplaces.
     */
    'firebird_importer' => [
        'user' => env('OFFICEIMPRESSO_FB_USER', 'SYSDBA'),
        'password' => env('OFFICEIMPRESSO_FB_PASSWORD'),
    ],
];
