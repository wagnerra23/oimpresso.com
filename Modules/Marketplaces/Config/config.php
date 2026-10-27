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
];
