<?php

/*
 * Parâmetros do Modules/Marketplaces (ADR 0439).
 *
 * Nada aqui liga o módulo para empresa alguma: habilitação é SÓ pelo pacote
 * `marketplaces_module` do superadmin + permissão `marketplaces.access` do papel.
 * As credenciais do aplicativo de cada marketplace (client_id/secret) entram pelas
 * US de OAuth (US-MKT-003); as do VENDEDOR ficam em mkt_account_credentials, por empresa.
 */
return [
    'name' => 'Marketplaces',

    /*
     * Aplicativo do Mercado Livre (US-MKT-003). UM aplicativo da plataforma, criado por [W] em
     * developers.mercadolivre.com.br; os valores vêm do ambiente (Vaultwarden → .env), nunca do
     * git. Vazios = botão de conectar recusa com mensagem, sem chamar a API.
     */
    'mercado_livre' => [
        'client_id' => env('MERCADOLIVRE_CLIENT_ID', ''),
        'client_secret' => env('MERCADOLIVRE_CLIENT_SECRET', ''),
        'redirect_uri' => env('MERCADOLIVRE_REDIRECT_URI', ''),
        'authorize_url' => env('MERCADOLIVRE_AUTHORIZE_URL', 'https://auth.mercadolivre.com.br/authorization'),
        'token_url' => env('MERCADOLIVRE_TOKEN_URL', 'https://api.mercadolibre.com/oauth/token'),
    ],
];
