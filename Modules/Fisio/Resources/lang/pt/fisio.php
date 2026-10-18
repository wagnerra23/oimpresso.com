<?php

return [
    'modulo' => 'Fisio',
    'sem_acesso' => 'O módulo Fisio não está ativo para esta empresa ou você não tem a permissão de acesso.',

    // US-FISIO-005 — pacientes e prontuário.
    'paciente_criado' => 'Paciente cadastrado.',
    'paciente_salvo' => 'Perfil clínico salvo.',
    'paciente_bloqueado' => 'Paciente bloqueado. O prontuário continua guardado (Lei 13.787/2018).',
    'paciente_ja_bloqueado' => 'Paciente bloqueado: o perfil clínico não aceita alteração.',
    'contato_ja_paciente' => 'Este contato já é paciente nesta clínica.',
    'contato_invalido' => 'Escolha um contato desta empresa.',
    'registro_paciente_bloqueado' => 'Paciente bloqueado: só se registra adendo de correção.',
    'adendo_invalido' => 'O adendo precisa apontar um registro deste paciente.',

    /* Rótulos dos marcadores de condição (config fisio.condition_flags). */
    'condition_flags' => [
        'protese_quadril_posterior' => 'Prótese de quadril (via posterior)',
        'protese_quadril_anterior' => 'Prótese de quadril (via anterior)',
        'pos_op_manguito' => 'Pós-operatório de manguito rotador',
        'pos_lca' => 'Pós-operatório de LCA',
        'gestante' => 'Gestante',
        'osteoporose' => 'Osteoporose',
        'risco_queda' => 'Risco de queda',
        'hipertensao_nao_controlada' => 'Hipertensão não controlada',
    ],

    /* Rótulos dos sinais de alerta (config fisio.red_flags). */
    'red_flags' => [
        'inchaco_vermelhidao_calor' => 'Inchaço, vermelhidão ou calor local',
        'febre' => 'Febre',
        'queda' => 'Queda',
        'dor_noturna_intensa' => 'Dor noturna intensa',
        'dormencia_progressiva' => 'Dormência progressiva',
        'perda_forca_pernas' => 'Perda de força nas pernas',
        'perda_controle_esfincter' => 'Perda de controle de esfíncter',
        'dormencia_regiao_intima' => 'Dormência na região íntima',
        'secrecao_ou_abertura_corte' => 'Secreção ou abertura do corte',
        'panturrilha_inchada_dolorida' => 'Panturrilha inchada e dolorida',
    ],
];
