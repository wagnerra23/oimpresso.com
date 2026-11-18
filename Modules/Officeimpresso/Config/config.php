<?php

return [
    'name' => 'Officeimpresso',
    'module_version' => '1.0',
    'pid' => 19,

    // Clients OAuth do desktop Delphi. O listener LogPassportAccessToken só grava em
    // licenca_log o token emitido para um destes ids; os demais clients do sistema
    // (CRM, fieldforce, app) não são login de licença. Lista vazia = nada é gravado.
    // 39 = client Delphi medido em produção em 2026-10-01 (comentário em
    // Modules/Superadmin/Listeners/OnCobrancaVencidaBloqueaSubscription.php). Não
    // re-medido em 2026-11-18: CT 100 inacessível. Ajuste pelo .env, ids separados por vírgula.
    'delphi_client_ids' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('OFFICEIMPRESSO_DELPHI_CLIENT_IDS', '39'))
    ), fn ($id) => $id !== '')),
];
