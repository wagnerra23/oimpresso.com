<?php

/*
|--------------------------------------------------------------------------
| Permission Registry — NFSe
|--------------------------------------------------------------------------
| Declarado para o PermissionRegistry (app/Services/PermissionRegistry.php)
| capturar via auto-discovery. As permissions Spatie já existem (declaradas
| em DataController@user_permissions) — este arquivo só anota metadados
| (risk, requires) pra Usuário 360°.
|
| Risk levels:
|   low      — apenas leitura, sem efeito fiscal/financeiro
|   medium   — escrita reversível
|   high     — escrita externa/fiscal (efeito sobre prefeitura/SEFAZ)
|   critical — irreversível ou destrutivo
*/

return [
    'group' => 'NFSe',
    'icon'  => 'file-invoice',
    'permissions' => [
        [
            'key'      => 'nfse.view',
            'label'    => 'NFSe: visualizar notas',
            'risk'     => 'low',
            'requires' => [],
        ],
        [
            'key'      => 'nfse.emit',
            'label'    => 'NFSe: emitir nota fiscal de serviço',
            'risk'     => 'high',
            'requires' => ['nfse.view'],
        ],
        [
            'key'      => 'nfse.cancel',
            'label'    => 'NFSe: cancelar nota fiscal',
            'risk'     => 'critical',
            'requires' => ['nfse.view'],
        ],
        [
            'key'      => 'nfse.settings',
            'label'    => 'NFSe: gerenciar configurações fiscais',
            'risk'     => 'high',
            'requires' => [],
        ],
    ],
];
