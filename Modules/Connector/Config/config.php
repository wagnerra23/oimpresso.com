<?php

return [
    'name' => 'Connector',
    'module_version' => '2.0',
    'pid' => 9,

    // Usuários centrais da WR que o desktop Delphi usa para gravar em vários
    // negócios (medido 2026-10-01 em licenca_log + oauth_access_tokens: só
    // user_id=1). Usado pelo guard de salvar-equipamento/{business_id}.
    // Sem env(): fora de config/ o Larastan barra (null com config:cache).
    'delphi_master_user_ids' => [1],
];
