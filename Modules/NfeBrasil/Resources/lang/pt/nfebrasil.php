<?php

return [
    'module_label' => 'NF-e Brasil',

    'permissao_acesso'           => 'NF-e: Acesso ao módulo',
    'permissao_emit_manage'      => 'NF-e: Emitir notas',
    'permissao_consult_view'     => 'NF-e: Consultar notas',
    'permissao_sped_view'        => 'NF-e: Ver SPED Fiscal',
    'permissao_settings_manage'  => 'NF-e: Configurações',

    'menu' => [
        'dashboard'    => 'Painel',
        'emit'         => 'Emitir NF-e/NFC-e',
        'consult'      => 'Consultar notas',
        'sped'         => 'SPED Fiscal',
        'certificado'  => 'Certificado A1',
    ],

    'certificado' => [
        'titulo'           => 'Certificado Digital A1',
        'descricao'        => 'Certificado e-CNPJ A1 (.pfx) usado para assinar NF-e / NFC-e.',
        'upload_label'     => 'Upload do certificado',
        'renovar_label'    => 'Renovar / substituir certificado',
        'alerta_vencido'   => 'Vencido',
        'alerta_proximo'   => 'Vence em :dias d',
        'alerta_valido'    => 'Válido · :dias d restantes',
        'sem_certificado'  => 'Nenhum certificado ativo. Faça upload para começar a emitir NF-e.',
        'salvo_sucesso'    => 'Certificado A1 salvo — válido até :data.',
    ],
];
