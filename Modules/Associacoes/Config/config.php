<?php

/*
 * Parâmetros do Modules/Associacoes (ADR 0451).
 *
 * Nada aqui liga o módulo para empresa alguma: habilitação é SÓ pelo pacote
 * `associacoes_module` do superadmin + permissão `associacoes.access` do papel.
 */
return [
    'name' => 'Associacoes',

    // D7 da ADR 0451: a associação avisa mudança de diretoria em até 30 dias (estatuto da rede).
    'prazo_ata_diretoria_dias' => 30,

    // D5 da ADR 0451: consentimento por finalidade, versionado. A versão vigente vem daqui,
    // nunca do corpo do request. Texto de cada finalidade entra com a tela (onda 4).
    'consentimento_versoes' => [
        'associada' => '1',
        'whatsapp' => '1',
        'visivel_rede' => '1',
    ],

    // D6 da ADR 0451 (onda 6): área da associada no app, por link + PIN. Mesmos números do
    // portal do paciente do Fisio (config('fisio.portal')).
    'portal' => [
        'validade_dias' => 30,
        'max_tentativas' => 5,
        'lockout_minutos' => 30,
    ],
];
