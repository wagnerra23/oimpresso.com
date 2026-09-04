<?php

return [
    'module_label'       => 'Fiscal',
    'nfe_label'          => 'NF-e · NFC-e',
    'cockpit_label'      => 'Cockpit fiscal',

    // Permissões (DataController::user_permissions)
    'permissao_acesso'       => 'Fiscal · acesso ao módulo',
    'permissao_nfe_view'     => 'Fiscal · ver NF-e e NFC-e',
    'permissao_nfe_acoes'    => 'Fiscal · ações (cancelar, retransmitir, CC-e)',
    'permissao_nfse_view'    => 'Fiscal · ver NFS-e',
    'permissao_dfe_manage'   => 'Fiscal · manifestar DF-e (futuro)',
    'permissao_sped_export'  => 'Fiscal · exportar SPED (futuro)',
    'permissao_config_edit'  => 'Fiscal · editar certificado e configuração',
    // Separada de config_edit DE PROPÓSITO: as duas ações abaixo mudam o valor
    // fiscal de toda nota emitida depois delas. Não é o mesmo risco que editar
    // configuração. Concessão é ato de [W] em /roles/{id}/edit.
    'permissao_config_ambiente' => 'Fiscal · trocar ambiente SEFAZ e substituir certificado',
];
