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
     * ── JOGO DE CONSTÂNCIA (US-FISIO-008 · ADR 0437 §6 e §13.3) ─────────────────────────
     * Números de memory/requisitos/Fisio/etapas/02-f1-constancia.md §3.4. Quem lê é
     * Modules\Fisio\Services\JogoService (e o PainelFisio, para os níveis).
     *
     * XP só por cumprir o PRESCRITO e por registrar o dia (inclusive com dor alta):
     *  - xp.exercicio_concluido: parcela dos exercícios no dia (70 de 100), proporcional às
     *    vezes feitas ÷ vezes prescritas; cada item conta no máximo `times_per_day` vezes.
     *  - xp.checkin: registro do dia (`registro_do_dia` na etapa 02), 30 de 100, SEMPRE.
     *  - normalizacao_dias_base: o XP do dia é multiplicado por base ÷ sessions_per_week,
     *    para quem tem 7 dias na semana não subir de nível quase 2× mais rápido que quem tem 4.
     *  - teto_semanal: limite de XP por semana (segunda a domingo).
     *  - xp.missao e xp.desafio_semanal: reservados para missões e desafio (T-08c, ainda sem código).
     *  - niveis: nível => XP acumulado mínimo; começa em 0 e cresce estritamente; nunca desce.
     *  - dor_de_base: programa com `pain_alert_mode = sobre_basal` (dor crônica, protocolo 11 —
     *    fibromialgia) pausa o jogo e sinaliza a clínica pela dor ACIMA DA BASE da própria
     *    paciente: média das dores da 1ª semana do programa (`janela_dias`, com pelo menos
     *    `minimo_registros`) + `margem`. Sem base ainda, vale o limiar absoluto do programa.
     */
    'jogo' => [
        'xp' => [
            'exercicio_concluido' => 70,
            'checkin' => 30,
            'missao' => 10,
            'desafio_semanal' => 40,
        ],
        'normalizacao_dias_base' => 4,
        'teto_semanal' => 500,
        // Missões e desafio somam até isto por semana, dentro do teto_semanal (etapa 02 §3.4).
        'teto_semanal_bonus' => 100,
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
        'dor_de_base' => [
            'janela_dias' => 7,
            'minimo_registros' => 3,
            'margem' => 2,
        ],
    ],
    /*
     * Versão vigente de cada consentimento (fisio_consents, ADR 0437 §7). Consentimento
     * dado numa versão antiga não vale: a versão vem SEMPRE daqui, nunca do corpo do
     * request. Mudar o texto de um termo = subir a versão dele aqui.
     */
    'consent_versions' => [
        'teleatendimento' => 'v1',
        'whatsapp' => 'v1',
        // Exercício acompanhado pela câmera (F2): processamento no aparelho, só métricas sobem.
        'camera' => 'v1',
        'imagem' => 'v1',
        'marketing' => 'v1',
        'pesquisa_ia' => 'v1',
    ],

    /*
     * Portal do paciente: link + PIN com lockout (padrão da OficinaAuto). O link vale
     * `validade_dias` (gerar outro revoga o anterior); o PIN de 4 dígitos trava após
     * `max_tentativas` erros por `lockout_minutos`.
     */
    'portal' => [
        'validade_dias' => 30,
        'max_tentativas' => 5,
        'lockout_minutos' => 30,
        // "Lembrar este aparelho" (US-FISIO-007b, etapa 02 T-07a): cookie httpOnly cifrado ligado
        // ao link; revogar ou gerar outro link derruba o aparelho e o PIN volta.
        'lembrar_aparelho_dias' => 30,
    ],

    /*
     * Foto ou vídeo curto do exercício PRÓPRIO da clínica (UC-FBI-05..07). Tetos em KB, validados
     * no servidor por tipo. Abaixo do `upload_max_filesize` de public/.user.ini (64M).
     */
    'midia' => [
        'foto_max_kb' => 10240,   // 10 MB
        'video_max_kb' => 51200,  // 50 MB
    ],

    /*
     * Linguagem proibida no texto que a clínica salva (B-06 · UC-FBI-12). Cópia LITERAL da ADR 0437 §5
     * ("Proibido: ..."), nunca ampliada aqui: termo novo entra pela ADR primeiro. Quem lê é
     * Modules\Fisio\Services\LinguagemAnvisa, que compara sem maiúscula, sem acento e por palavra
     * inteira ("tratamento" passa; "trata" não).
     */
    'linguagem_anvisa' => [
        'termos_proibidos' => ['diagnostica', 'trata', 'corrige postura', 'previne lesão'],
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

    /*
     * Facetas da biblioteca de exercícios (US-FISIO-004): listas FECHADAS de
     * `etapas/01-biblioteca.md` §3.3. Valor fora daqui é recusado no cadastro e ignorado no
     * filtro. São rótulos de estrutura, não conteúdo clínico: nenhum exercício nasce daqui.
     *  - posicoes: uma por exercício (`fisio_exercises.pose`).
     *  - equipamentos: várias por exercício (`fisio_exercises.equipment`, lista JSON);
     *    "nenhum" marca o exercício feito sem objeto e não se combina com os outros.
     */
    'biblioteca' => [
        'posicoes' => [
            'deitado_costas' => 'Deitado de costas',
            'deitado_lado' => 'Deitado de lado',
            'de_brucos' => 'De bruços',
            'quatro_apoios' => 'Quatro apoios',
            'ajoelhado' => 'Ajoelhado',
            'sentado' => 'Sentado',
            'em_pe' => 'Em pé',
            'em_pe_apoio' => 'Em pé com apoio',
        ],
        'equipamentos' => [
            'nenhum' => 'Nenhum',
            'cadeira' => 'Cadeira',
            'parede' => 'Parede',
            'toalha' => 'Toalha',
            'cabo_vassoura' => 'Cabo de vassoura',
            'garrafa_pet' => 'Garrafa PET',
            'saco_arroz_feijao' => 'Saco de arroz ou feijão',
            'mochila_peso' => 'Mochila com peso',
            'degrau' => 'Degrau ou escada',
            'travesseiro' => 'Travesseiro ou almofada',
            'elastico' => 'Elástico ou faixa',
            'caneleira' => 'Caneleira',
            'bola' => 'Bola',
        ],
    ],

    /*
     * WhatsApp do Fisio (US-FISIO-009; ADR 0437 §13.1). O Fisio NÃO tem canal próprio: envia pelo
     * módulo `Whatsapp` (número de saída padrão da empresa, driver e saúde resolvidos lá). Estes
     * parâmetros são só do lado do Fisio:
     *  - teto_por_hora / intervalo_*: mitigação (c) da §13.1 — o módulo Whatsapp não limita envio
     *    de saída por número; o Fisio espalha os seus envios (janela móvel de 60 min, intervalo
     *    sorteado entre mensagens). Vale para todo canal automático.
     *  - silencio: nada sai nessa faixa no fuso da clínica; o envio vai para o fim do silêncio.
     *  - pin_delay_segundos: o PIN sai numa 2ª mensagem, depois do link (nunca junto — precedente
     *    OficinaAuto `EnviarLinkAprovacaoWhatsappJob`).
     *  - lembrete_horario: quando o agendador enfileira o lembrete diário (fuso do app).
     */
    'whatsapp' => [
        'teto_por_hora' => 30,
        'intervalo_min_segundos' => 20,
        'intervalo_max_segundos' => 90,
        'silencio' => ['inicio' => '21:00', 'fim' => '07:00'],
        'pin_delay_segundos' => 60,
        'lembrete_horario' => '08:30',
    ],
];
