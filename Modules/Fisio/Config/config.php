<?php

/*
 * Parâmetros do Modules/Fisio (ADR 0437). Valores padrão de
 * memory/requisitos/Fisio/etapas/02-f1-constancia.md §3.4; os testes da suíte
 * sobrescrevem estes valores nas fixtures (FisioFixtures::configurar()).
 *
 * Nada aqui liga o módulo para empresa alguma: habilitação é SÓ pelo pacote
 * `fisio_module` do superadmin + permissão `fisio.access` do papel.
 */
return [
    'name' => 'Fisio',

    /*
     * Jogo de constância (ADR 0437 §6 e §13.3). XP só por cumprir o prescrito e por
     * registrar o dia (inclusive com dor alta); exceder o prescrito dá 0.
     *  - exercicio_concluido: parcela dos exercícios no dia prescrito (70 de 100).
     *  - checkin: registro do dia (`registro_do_dia` na etapa 02), 30 de 100, sempre.
     *  - normalizacao_dias_base: o XP do dia é multiplicado por base ÷ sessions_per_week.
     *  - teto_semanal: exercícios + registro + missões + desafio.
     */
    'xp' => [
        'exercicio_concluido' => 70,
        'checkin' => 30,
        'normalizacao_dias_base' => 4,
        'missao' => 10,
        'desafio_semanal' => 40,
        'teto_semanal' => 500,
    ],

    /*
     * Nível => XP acumulado mínimo. Começa em 0 e cresce estritamente; nível nunca desce.
     * Nomes na ordem da ADR 0437 §6.
     */
    'niveis' => [
        1 => 0,       // Primeiro Passo
        2 => 150,     // Ritmo
        3 => 400,     // Constância
        4 => 800,     // Base Firme
        5 => 1300,    // Equilíbrio
        6 => 2000,    // Força Leve
        7 => 2900,    // Movimento Livre
        8 => 4000,    // Corpo Forte
        9 => 5400,    // Mestre do Movimento
        10 => 7000,   // Inabalável
    ],

    /*
     * Versão vigente de cada consentimento (fisio_consents, ADR 0437 §7). Consentimento
     * dado numa versão antiga não vale: a versão vem SEMPRE daqui, nunca do corpo do
     * request. Mudar o texto de um termo = subir a versão dele aqui.
     */
    'consent_versions' => [
        'teleatendimento' => 'v1',
        'whatsapp' => 'v1',
        'imagem' => 'v1',
        'marketing' => 'v1',
        'pesquisa_ia' => 'v1',
    ],

    /* Portal do paciente: link + PIN com lockout (padrão da OficinaAuto). */
    'portal' => [
        'max_tentativas' => 5,
        'lockout_minutos' => 30,
    ],

    /* Dor (0–10) a partir da qual o portal sai do jogo e mostra só a instrução do profissional. */
    'pain_alert_threshold_padrao' => 7,

    /*
     * Lista FECHADA de sinais de alerta que o portal aceita (etapa 02 §3.5). Agrupados
     * por região só para leitura; o profissional escolhe no programa quais o paciente vê.
     */
    'red_flags' => [
        // geral
        'inchaco_vermelhidao_calor',
        'febre',
        'queda',
        'dor_noturna_intensa',
        'dormencia_progressiva',
        // lombar
        'perda_forca_pernas',
        'perda_controle_esfincter',
        'dormencia_regiao_intima',
        // pós-operatório
        'secrecao_ou_abertura_corte',
        'panturrilha_inchada_dolorida',
    ],
];