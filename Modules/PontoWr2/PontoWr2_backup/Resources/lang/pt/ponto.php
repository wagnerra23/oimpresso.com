<?php

/*
|--------------------------------------------------------------------------
| Traduções do módulo PontoWr2 (pt — padrão UltimatePOS)
|--------------------------------------------------------------------------
|
| Pasta usa código curto "pt" (não "pt-BR") para casar com a estrutura do
| UltimatePOS, ver Modules/Jana/Resources/lang/ para referência.
|
*/

return [
    'module_label'       => 'Ponto WR2',
    'module_description' => 'Ponto Eletrônico · Portaria MTP 671/2021',

    'menu' => [
        'dashboard'       => 'Dashboard',
        'espelho'         => 'Espelho de Ponto',
        'aprovacoes'      => 'Aprovações',
        'intercorrencias' => 'Intercorrências',
        'banco_horas'     => 'Banco de Horas',
        'escalas'         => 'Escalas',
        'importacoes'     => 'Importações',
        'relatorios'      => 'Relatórios',
        'colaboradores'   => 'Colaboradores',
        'configuracoes'   => 'Configurações',
    ],

    // Rótulos de permissão (usados em DataController::user_permissions)
    'permissao_acesso'         => 'Ponto — Acessar módulo',
    'permissao_colaboradores'  => 'Ponto — Gerenciar colaboradores',
    'permissao_aprovacoes'     => 'Ponto — Aprovar intercorrências',
    'permissao_relatorios'     => 'Ponto — Visualizar relatórios',
    'permissao_configuracoes'  => 'Ponto — Gerenciar configurações',

    'intercorrencia' => [
        'tipos' => [
            'CONSULTA_MEDICA'        => 'Consulta médica',
            'ATESTADO_MEDICO'        => 'Atestado médico',
            'REUNIAO_EXTERNA'        => 'Reunião externa',
            'VISITA_CLIENTE'         => 'Visita a cliente',
            'HORA_EXTRA_AUTORIZADA'  => 'Hora extra autorizada',
            'ESQUECIMENTO_MARCACAO'  => 'Esquecimento de marcação',
            'PROBLEMA_EQUIPAMENTO'   => 'Problema no equipamento',
            'OUTRO'                  => 'Outro',
        ],
        'estados' => [
            'RASCUNHO'  => 'Rascunho',
            'PENDENTE'  => 'Pendente',
            'APROVADA'  => 'Aprovada',
            'REJEITADA' => 'Rejeitada',
            'APLICADA'  => 'Aplicada',
            'CANCELADA' => 'Cancelada',
        ],
    ],

    'marcacao' => [
        'origens' => [
            'REP_P'      => 'REP-P (equipamento)',
            'AFD'        => 'Importação AFD',
            'AFDT'       => 'Importação AFDT',
            'MANUAL'     => 'Lançamento manual',
            'INTEGRACAO' => 'Integração API',
            'ANULACAO'   => 'Anulação',
        ],
    ],
];
