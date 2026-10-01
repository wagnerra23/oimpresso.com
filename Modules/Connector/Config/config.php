<?php

return [
    'name' => 'Connector',
    'module_version' => '2.0',
    'pid' => 9,

    // Usuários centrais da WR que o desktop Delphi usa para gravar em vários
    // negócios (medido 2026-10-01: só user_id=1). Lista CSV; usado pelo guard
    // de salvar-equipamento/{business_id}. Decisão [W] pendente — ver o PR.
    'delphi_master_user_ids' => array_values(array_filter(array_map(
        'intval',
        explode(',', (string) env('CONNECTOR_DELPHI_MASTER_USER_IDS', '1'))
    ))),
];
