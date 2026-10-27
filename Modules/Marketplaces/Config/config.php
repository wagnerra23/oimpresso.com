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

    /*
     * Loja Nuvemshop (ADR 0443). DESLIGADA: `ativo` é a flag que liga a integração inteira, e só o
     * [W] liga, depois de criar o app de parceiro em partners.nuvemshop.com.br. app_id e
     * client_secret vêm do ambiente (Vaultwarden → .env), nunca do git. O client_secret também é a
     * chave do HMAC que assina os webhooks da Nuvemshop. `user_agent` é exigido em toda chamada
     * à API (sem ele a Nuvemshop devolve 400).
     */
    'nuvemshop' => [
        'ativo' => (bool) env('MARKETPLACES_NUVEMSHOP_ATIVO', false),
        'app_id' => env('NUVEMSHOP_APP_ID', ''),
        'client_secret' => env('NUVEMSHOP_CLIENT_SECRET', ''),
        'authorize_url' => env('NUVEMSHOP_AUTHORIZE_URL', 'https://www.nuvemshop.com.br/apps/{app_id}/authorize'),
        'token_url' => env('NUVEMSHOP_TOKEN_URL', 'https://www.nuvemshop.com.br/apps/authorize/token'),
        'user_agent' => env('NUVEMSHOP_USER_AGENT', 'oimpresso ERP (suporte@oimpresso.com)'),
    ],

    /*
     * NF-e do pedido de marketplace (US-MKT-009 · ADR 0439 D5). DESLIGADA até [W] conferir o CFOP
     * com o contador. O CFOP NÃO tem valor padrão de propósito: depende do contador (D5). Sem ele
     * configurado, a nota do pedido cai em "não pode" com o motivo. O mapeamento provisório do
     * SPEC §5 (5102 mesma UF · 6102 outra UF) é só a sugestão a conferir.
     */
    'nfe' => [
        'automatica' => (bool) env('MARKETPLACES_NFE_AUTOMATICA', false),
        'cfop_mesma_uf' => env('MARKETPLACES_CFOP_MESMA_UF'),
        'cfop_outra_uf' => env('MARKETPLACES_CFOP_OUTRA_UF'),
    ],
];
