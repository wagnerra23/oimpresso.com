<?php

declare(strict_types=1);

/*
 * Biblioteca L0 do Fisio — 38 exercícios GLOBAIS (business_id nulo), lote L0 da etapa 01 §3.2
 * ("seed do código, T-04 · testes e demo"). Consumido por FisioBibliotecaSeeder.
 *
 * ⚠️ CONTEÚDO CLÍNICO PENDENTE DE REVISÃO DA DRA. DEJEANE (responsável técnica, ADR 0437).
 * Tudo aqui nasce `review_status = em_revisao` e NÃO aparece para as clínicas até a revisão
 * ser registrada (etapa 01 §3.8: autor ≠ revisor, CREFITO gravado). O que a revisão precisa
 * conferir, item a item: nome, passos, dose padrão (`default_*`, `times_per_day`), avisos
 * (`safety_notes`), se pede apoio (`needs_support`) e o mapa de contraindicação
 * (`contraindication_flags`, códigos de `config('fisio.condition_flags')`, rascunho da §3.3.1).
 *
 * A etapa 01 NÃO lista os 38 nomes: a lista foi montada para cobrir (a) os exercícios do
 * protocolo-modelo 11 (fibromialgia) marcados "a conferir na lista do seed (US-FISIO-004)" e
 * (b) as regiões dos protocolos 1–10 da §3.4. Linguagem: sem "trata", "cura", "corrige
 * postura", "previne lesão", "diagnostica" (ADR 0437 §5).
 *
 * `code` é estável: o seed é idempotente por ele e o protocolo-modelo vai referenciá-lo.
 */
return [
    // ── Lombar e tronco ────────────────────────────────────────────────────────────
    ['code' => 'FIS-LOM-001', 'name' => 'Respiração diafragmática', 'region' => 'lombar', 'type' => 'respiracao', 'pose' => 'deitado', 'equipment' => ['travesseiro'],
        'steps' => ['Deite de costas com os joelhos dobrados', 'Ponha uma mão na barriga', 'Puxe o ar pelo nariz enchendo a barriga', 'Solte o ar devagar pela boca'],
        'default_sets' => 2, 'default_reps' => 10, 'default_hold_s' => 0, 'times_per_day' => 2,
        'safety_notes' => ['Respire sem forçar; pare se sentir tontura'], 'contraindication_flags' => [], 'needs_support' => false],
    ['code' => 'FIS-LOM-002', 'name' => 'Gato-camelo', 'region' => 'lombar', 'type' => 'mobilidade', 'pose' => 'quatro_apoios', 'equipment' => [],
        'steps' => ['Fique em quatro apoios', 'Arredonde as costas olhando para o umbigo', 'Afunde as costas olhando para frente', 'Alterne devagar, sem dor'],
        'default_sets' => 2, 'default_reps' => 8, 'default_hold_s' => 0, 'times_per_day' => 1,
        'safety_notes' => ['Movimento suave, dentro do que não dói', 'Não prenda a respiração'], 'contraindication_flags' => [], 'needs_support' => false],
    ['code' => 'FIS-LOM-003', 'name' => 'Rotação de tronco deitado', 'region' => 'lombar', 'type' => 'mobilidade', 'pose' => 'deitado', 'equipment' => [],
        'steps' => ['Deite de costas com os joelhos dobrados e juntos', 'Leve os joelhos para um lado', 'Volte ao centro', 'Repita para o outro lado'],
        'default_sets' => 2, 'default_reps' => 8, 'default_hold_s' => 0, 'times_per_day' => 1,
        'safety_notes' => ['Os ombros ficam no chão', 'Vá só até onde não dói'], 'contraindication_flags' => ['protese_quadril_posterior'], 'needs_support' => false],
    ['code' => 'FIS-LOM-004', 'name' => 'Joelho ao peito', 'region' => 'lombar', 'type' => 'alongamento', 'pose' => 'deitado', 'equipment' => [],
        'steps' => ['Deite de costas', 'Abrace um joelho e traga para o peito', 'Segure o tempo indicado', 'Volte devagar e troque a perna'],
        'default_sets' => 3, 'default_reps' => 1, 'default_hold_s' => 20, 'times_per_day' => 1,
        'safety_notes' => ['Não prenda a respiração'], 'contraindication_flags' => ['protese_quadril_posterior', 'gestante'], 'needs_support' => false],
    ['code' => 'FIS-LOM-005', 'name' => 'Ponte de glúteo', 'region' => 'lombar', 'type' => 'fortalecimento', 'pose' => 'deitado', 'equipment' => [],
        'steps' => ['Deite de costas com os joelhos dobrados', 'Aperte o bumbum', 'Eleve o quadril até alinhar com as coxas', 'Desça devagar'],
        'default_sets' => 2, 'default_reps' => 10, 'default_hold_s' => 0, 'times_per_day' => 1,
        'safety_notes' => ['Solte o ar na subida', 'Não arqueie a lombar'], 'contraindication_flags' => ['gestante'], 'needs_support' => false],
    ['code' => 'FIS-LOM-006', 'name' => 'Perdigueiro', 'region' => 'lombar', 'type' => 'controle_motor', 'pose' => 'quatro_apoios', 'equipment' => [],
        'steps' => ['Fique em quatro apoios', 'Estique um braço e a perna oposta', 'Segure com o tronco parado', 'Volte e troque o lado'],
        'default_sets' => 2, 'default_reps' => 8, 'default_hold_s' => 3, 'times_per_day' => 1,
        'safety_notes' => ['Mantenha as costas retas, sem girar o quadril', 'Não prenda a respiração'], 'contraindication_flags' => [], 'needs_support' => false],
    ['code' => 'FIS-LOM-007', 'name' => 'Inseto morto', 'region' => 'lombar', 'type' => 'controle_motor', 'pose' => 'deitado', 'equipment' => [],
        'steps' => ['Deite de costas com braços e joelhos para cima', 'Desça um braço e a perna oposta', 'Mantenha a lombar encostada no chão', 'Volte e troque o lado'],
        'default_sets' => 2, 'default_reps' => 8, 'default_hold_s' => 0, 'times_per_day' => 1,
        'safety_notes' => ['Movimento lento', 'Não prenda a respiração'], 'contraindication_flags' => ['gestante'], 'needs_support' => false],
    ['code' => 'FIS-LOM-008', 'name' => 'Prancha nos joelhos', 'region' => 'lombar', 'type' => 'fortalecimento', 'pose' => 'de_brucos', 'equipment' => [],
        'steps' => ['Apoie antebraços e joelhos no chão', 'Alinhe cabeça, tronco e quadril', 'Segure o tempo indicado', 'Descanse e repita'],
        'default_sets' => 3, 'default_reps' => 1, 'default_hold_s' => 20, 'times_per_day' => 1,
        'safety_notes' => ['Respire durante todo o tempo', 'Pare se a lombar doer'], 'contraindication_flags' => ['hipertensao_nao_controlada'], 'needs_support' => false],
    ['code' => 'FIS-LOM-009', 'name' => 'Postura da criança', 'region' => 'lombar', 'type' => 'alongamento', 'pose' => 'ajoelhado', 'equipment' => ['travesseiro'],
        'steps' => ['Ajoelhe e sente sobre os calcanhares', 'Leve os braços à frente no chão', 'Relaxe o tronco', 'Segure respirando devagar'],
        'default_sets' => 3, 'default_reps' => 1, 'default_hold_s' => 30, 'times_per_day' => 1,
        'safety_notes' => ['Use travesseiro sob os joelhos se incomodar'], 'contraindication_flags' => [], 'needs_support' => false],

    // ── Cervical ───────────────────────────────────────────────────────────────────
    ['code' => 'FIS-CER-001', 'name' => 'Retração cervical', 'region' => 'cervical', 'type' => 'controle_motor', 'pose' => 'sentado', 'equipment' => ['cadeira'],
        'steps' => ['Sente com as costas apoiadas', 'Leve o queixo para trás, sem abaixar a cabeça', 'Segure 3 segundos', 'Relaxe'],
        'default_sets' => 2, 'default_reps' => 10, 'default_hold_s' => 3, 'times_per_day' => 2,
        'safety_notes' => ['Movimento pequeno e sem dor'], 'contraindication_flags' => [], 'needs_support' => false],
    ['code' => 'FIS-CER-002', 'name' => 'Inclinação lateral do pescoço', 'region' => 'cervical', 'type' => 'alongamento', 'pose' => 'sentado', 'equipment' => ['cadeira'],
        'steps' => ['Sente com os ombros relaxados', 'Leve a orelha em direção ao ombro', 'Segure o tempo indicado', 'Troque o lado'],
        'default_sets' => 3, 'default_reps' => 1, 'default_hold_s' => 20, 'times_per_day' => 1,
        'safety_notes' => ['Não puxe a cabeça com a mão', 'Pare se formigar o braço'], 'contraindication_flags' => [], 'needs_support' => false],
    ['code' => 'FIS-CER-003', 'name' => 'Rotação do pescoço', 'region' => 'cervical', 'type' => 'mobilidade', 'pose' => 'sentado', 'equipment' => ['cadeira'],
        'steps' => ['Sente com as costas retas', 'Vire a cabeça devagar para um lado', 'Volte ao centro', 'Vire para o outro lado'],
        'default_sets' => 2, 'default_reps' => 8, 'default_hold_s' => 0, 'times_per_day' => 1,
        'safety_notes' => ['Pare se sentir tontura'], 'contraindication_flags' => [], 'needs_support' => false],

    // ── Torácica e escápulas ───────────────────────────────────────────────────────
    ['code' => 'FIS-TOR-001', 'name' => 'Retração escapular', 'region' => 'toracica', 'type' => 'controle_motor', 'pose' => 'sentado', 'equipment' => ['cadeira'],
        'steps' => ['Sente com os braços ao lado do corpo', 'Aproxime as escápulas, para trás e para baixo', 'Segure 3 segundos', 'Relaxe'],
        'default_sets' => 2, 'default_reps' => 10, 'default_hold_s' => 3, 'times_per_day' => 1,
        'safety_notes' => ['Não suba os ombros'], 'contraindication_flags' => [], 'needs_support' => false],
    ['code' => 'FIS-TOR-002', 'name' => 'Extensão torácica na cadeira', 'region' => 'toracica', 'type' => 'mobilidade', 'pose' => 'sentado', 'equipment' => ['cadeira'],
        'steps' => ['Sente com as mãos atrás da cabeça', 'Incline o peito para cima sobre o encosto', 'Volte devagar'],
        'default_sets' => 2, 'default_reps' => 8, 'default_hold_s' => 0, 'times_per_day' => 1,
        'safety_notes' => ['Use cadeira firme, sem rodinhas'], 'contraindication_flags' => [], 'needs_support' => false],
    ['code' => 'FIS-TOR-003', 'name' => 'Abertura de tórax deitado de lado', 'region' => 'toracica', 'type' => 'mobilidade', 'pose' => 'deitado_lado', 'equipment' => ['travesseiro'],
        'steps' => ['Deite de lado com os joelhos dobrados', 'Braços estendidos à frente, mãos juntas', 'Abra o braço de cima acompanhando com o olhar', 'Volte devagar'],
        'default_sets' => 2, 'default_reps' => 8, 'default_hold_s' => 0, 'times_per_day' => 1,
        'safety_notes' => ['Os joelhos ficam juntos no chão'], 'contraindication_flags' => [], 'needs_support' => false],

    // ── Ombro ──────────────────────────────────────────────────────────────────────
    ['code' => 'FIS-OMB-001', 'name' => 'Pendular', 'region' => 'ombro', 'type' => 'mobilidade', 'pose' => 'em_pe', 'equipment' => ['cadeira'],
        'steps' => ['Apoie a mão boa numa mesa ou cadeira', 'Incline o tronco e solte o braço', 'Faça pequenos círculos com o braço solto', 'Troque o sentido'],
        'default_sets' => 2, 'default_reps' => 20, 'default_hold_s' => 0, 'times_per_day' => 2,
        'safety_notes' => ['O braço balança solto, sem força', 'Faça perto de um apoio firme'], 'contraindication_flags' => [], 'needs_support' => true],
    ['code' => 'FIS-OMB-002', 'name' => 'Flexão de ombro com cabo de vassoura', 'region' => 'ombro', 'type' => 'mobilidade', 'pose' => 'deitado', 'equipment' => ['cabo_vassoura'],
        'steps' => ['Deite de costas segurando o cabo com as duas mãos', 'Leve o cabo acima da cabeça', 'Volte devagar'],
        'default_sets' => 2, 'default_reps' => 10, 'default_hold_s' => 0, 'times_per_day' => 1,
        'safety_notes' => ['Vá só até onde não dói'], 'contraindication_flags' => ['pos_op_manguito'], 'needs_support' => false],
    ['code' => 'FIS-OMB-003', 'name' => 'Rotação externa com elástico', 'region' => 'ombro', 'type' => 'fortalecimento', 'pose' => 'em_pe', 'equipment' => ['elastico', 'toalha'],
        'steps' => ['Cotovelo colado ao corpo, dobrado em 90 graus', 'Toalha enrolada entre o braço e o corpo', 'Gire o antebraço para fora puxando o elástico', 'Volte devagar'],
        'default_sets' => 2, 'default_reps' => 12, 'default_hold_s' => 0, 'times_per_day' => 1,
        'safety_notes' => ['O cotovelo não sai do lugar', 'Não prenda a respiração'], 'contraindication_flags' => ['pos_op_manguito'], 'needs_support' => false],
    ['code' => 'FIS-OMB-004', 'name' => 'Deslizamento na parede', 'region' => 'ombro', 'type' => 'mobilidade', 'pose' => 'em_pe', 'equipment' => ['parede', 'toalha'],
        'steps' => ['De frente para a parede, mãos sobre uma toalha', 'Deslize as mãos para cima', 'Volte devagar'],
        'default_sets' => 2, 'default_reps' => 10, 'default_hold_s' => 0, 'times_per_day' => 1,
        'safety_notes' => ['Não suba os ombros em direção às orelhas'], 'contraindication_flags' => ['pos_op_manguito'], 'needs_support' => false],
    ['code' => 'FIS-OMB-005', 'name' => 'Remada com elástico', 'region' => 'ombro', 'type' => 'fortalecimento', 'pose' => 'sentado', 'equipment' => ['elastico', 'cadeira'],
        'steps' => ['Sente e prenda o elástico à frente', 'Puxe levando os cotovelos para trás', 'Aproxime as escápulas', 'Volte devagar'],
        'default_sets' => 2, 'default_reps' => 10, 'default_hold_s' => 0, 'times_per_day' => 1,
        'safety_notes' => ['Solte o ar ao puxar', 'Tronco parado'], 'contraindication_flags' => ['pos_op_manguito'], 'needs_support' => false],
    ['code' => 'FIS-OMB-006', 'name' => 'Alongamento posterior do ombro', 'region' => 'ombro', 'type' => 'alongamento', 'pose' => 'em_pe', 'equipment' => [],
        'steps' => ['Leve um braço cruzado à frente do peito', 'Com a outra mão, puxe o braço de leve', 'Segure o tempo indicado', 'Troque o lado'],
        'default_sets' => 3, 'default_reps' => 1, 'default_hold_s' => 20, 'times_per_day' => 1,
        'safety_notes' => ['Sinta alongar, nunca doer'], 'contraindication_flags' => ['pos_op_manguito'], 'needs_support' => false],

    // ── Quadril ────────────────────────────────────────────────────────────────────
    ['code' => 'FIS-QUA-001', 'name' => 'Ostra', 'region' => 'quadril', 'type' => 'fortalecimento', 'pose' => 'deitado_lado', 'equipment' => ['travesseiro'],
        'steps' => ['Deite de lado com os joelhos dobrados', 'Pés juntos, abra o joelho de cima', 'Não gire o quadril para trás', 'Feche devagar'],
        'default_sets' => 2, 'default_reps' => 12, 'default_hold_s' => 0, 'times_per_day' => 1,
        'safety_notes' => ['Travesseiro sob a cabeça'], 'contraindication_flags' => [], 'needs_support' => false],
    ['code' => 'FIS-QUA-002', 'name' => 'Abdução de quadril deitado de lado', 'region' => 'quadril', 'type' => 'fortalecimento', 'pose' => 'deitado_lado', 'equipment' => [],
        'steps' => ['Deite de lado, perna de baixo dobrada', 'Eleve a perna de cima estendida', 'Desça devagar'],
        'default_sets' => 2, 'default_reps' => 10, 'default_hold_s' => 0, 'times_per_day' => 1,
        'safety_notes' => ['A perna sobe para o lado, não para frente'], 'contraindication_flags' => [], 'needs_support' => false],
    ['code' => 'FIS-QUA-003', 'name' => 'Extensão de quadril em pé', 'region' => 'quadril', 'type' => 'fortalecimento', 'pose' => 'em_pe', 'equipment' => ['cadeira'],
        'steps' => ['Segure o encosto de uma cadeira', 'Leve uma perna estendida para trás', 'Não arqueie a lombar', 'Volte e troque a perna'],
        'default_sets' => 2, 'default_reps' => 10, 'default_hold_s' => 0, 'times_per_day' => 1,
        'safety_notes' => ['Faça perto de um apoio firme'], 'contraindication_flags' => ['protese_quadril_anterior'], 'needs_support' => true],
    ['code' => 'FIS-QUA-004', 'name' => 'Alongamento dos flexores do quadril', 'region' => 'quadril', 'type' => 'alongamento', 'pose' => 'ajoelhado', 'equipment' => ['travesseiro'],
        'steps' => ['Ajoelhe com um pé à frente', 'Leve o quadril à frente, tronco reto', 'Segure o tempo indicado', 'Troque o lado'],
        'default_sets' => 3, 'default_reps' => 1, 'default_hold_s' => 20, 'times_per_day' => 1,
        'safety_notes' => ['Travesseiro sob o joelho de apoio'], 'contraindication_flags' => ['protese_quadril_anterior'], 'needs_support' => false],
    ['code' => 'FIS-QUA-005', 'name' => 'Alongamento do glúteo deitado', 'region' => 'quadril', 'type' => 'alongamento', 'pose' => 'deitado', 'equipment' => [],
        'steps' => ['Deite de costas com os joelhos dobrados', 'Cruze um tornozelo sobre o outro joelho', 'Traga as pernas em direção ao peito', 'Segure e troque o lado'],
        'default_sets' => 3, 'default_reps' => 1, 'default_hold_s' => 20, 'times_per_day' => 1,
        'safety_notes' => ['Sinta alongar, nunca doer'], 'contraindication_flags' => ['protese_quadril_posterior'], 'needs_support' => false],

    // ── Joelho ─────────────────────────────────────────────────────────────────────
    ['code' => 'FIS-JOE-001', 'name' => 'Isometria de quadríceps', 'region' => 'joelho', 'type' => 'fortalecimento', 'pose' => 'deitado', 'equipment' => ['toalha'],
        'steps' => ['Deite com uma toalha enrolada sob o joelho', 'Empurre o joelho contra a toalha', 'Segure o tempo indicado', 'Relaxe'],
        'default_sets' => 1, 'default_reps' => 10, 'default_hold_s' => 5, 'times_per_day' => 2,
        'safety_notes' => ['Não prenda a respiração'], 'contraindication_flags' => [], 'needs_support' => false],
    ['code' => 'FIS-JOE-002', 'name' => 'Elevação da perna estendida', 'region' => 'joelho', 'type' => 'fortalecimento', 'pose' => 'deitado', 'equipment' => [],
        'steps' => ['Deite de costas, uma perna dobrada', 'Estique a outra perna e contraia a coxa', 'Eleve a perna estendida até a altura do outro joelho', 'Desça devagar'],
        'default_sets' => 2, 'default_reps' => 10, 'default_hold_s' => 0, 'times_per_day' => 1,
        'safety_notes' => ['O joelho fica esticado na subida'], 'contraindication_flags' => ['gestante'], 'needs_support' => false],
    ['code' => 'FIS-JOE-003', 'name' => 'Extensão de joelho sentado', 'region' => 'joelho', 'type' => 'fortalecimento', 'pose' => 'sentado', 'equipment' => ['cadeira'],
        'steps' => ['Sente com os pés no chão', 'Estique um joelho até a perna ficar reta', 'Desça devagar', 'Troque a perna'],
        'default_sets' => 2, 'default_reps' => 10, 'default_hold_s' => 0, 'times_per_day' => 1,
        'safety_notes' => ['Movimento lento, sem chutar'], 'contraindication_flags' => ['pos_lca'], 'needs_support' => false],
    ['code' => 'FIS-JOE-004', 'name' => 'Sentar e levantar da cadeira', 'region' => 'joelho', 'type' => 'fortalecimento', 'pose' => 'sentado', 'equipment' => ['cadeira'],
        'steps' => ['Sente na beira da cadeira, pés afastados', 'Incline o tronco e levante sem usar as mãos', 'Sente devagar'],
        'default_sets' => 2, 'default_reps' => 8, 'default_hold_s' => 0, 'times_per_day' => 1,
        'safety_notes' => ['Cadeira encostada na parede', 'Solte o ar ao levantar'], 'contraindication_flags' => [], 'needs_support' => false],
    ['code' => 'FIS-JOE-005', 'name' => 'Mini agachamento na parede', 'region' => 'joelho', 'type' => 'fortalecimento', 'pose' => 'em_pe', 'equipment' => ['parede'],
        'steps' => ['Encoste as costas na parede, pés à frente', 'Desça dobrando um pouco os joelhos', 'Suba devagar'],
        'default_sets' => 2, 'default_reps' => 8, 'default_hold_s' => 0, 'times_per_day' => 1,
        'safety_notes' => ['Os joelhos não passam da ponta dos pés', 'Não prenda a respiração'], 'contraindication_flags' => [], 'needs_support' => false],
    ['code' => 'FIS-JOE-006', 'name' => 'Alongamento de isquiotibiais sentado', 'region' => 'joelho', 'type' => 'alongamento', 'pose' => 'sentado', 'equipment' => ['cadeira'],
        'steps' => ['Sente na beira da cadeira com uma perna estendida', 'Incline o tronco à frente com as costas retas', 'Segure o tempo indicado', 'Troque a perna'],
        'default_sets' => 2, 'default_reps' => 1, 'default_hold_s' => 20, 'times_per_day' => 1,
        'safety_notes' => ['Sinta alongar atrás da coxa, sem dor'], 'contraindication_flags' => [], 'needs_support' => false],
    ['code' => 'FIS-JOE-007', 'name' => 'Subida no degrau', 'region' => 'joelho', 'type' => 'fortalecimento', 'pose' => 'em_pe', 'equipment' => ['degrau'],
        'steps' => ['De frente para um degrau, segure no corrimão', 'Suba com uma perna', 'Desça com a mesma perna', 'Troque a perna'],
        'default_sets' => 2, 'default_reps' => 8, 'default_hold_s' => 0, 'times_per_day' => 1,
        'safety_notes' => ['Sempre com apoio firme', 'Degrau baixo no começo'], 'contraindication_flags' => ['risco_queda'], 'needs_support' => true],

    // ── Tornozelo e pé ─────────────────────────────────────────────────────────────
    ['code' => 'FIS-PE-001', 'name' => 'Elevação de panturrilha', 'region' => 'tornozelo', 'type' => 'fortalecimento', 'pose' => 'em_pe', 'equipment' => ['cadeira'],
        'steps' => ['Segure o encosto de uma cadeira', 'Fique na ponta dos pés', 'Desça devagar'],
        'default_sets' => 2, 'default_reps' => 10, 'default_hold_s' => 0, 'times_per_day' => 1,
        'safety_notes' => ['Faça perto de um apoio firme'], 'contraindication_flags' => [], 'needs_support' => true],
    ['code' => 'FIS-PE-002', 'name' => 'Bombeamento de tornozelo', 'region' => 'tornozelo', 'type' => 'mobilidade', 'pose' => 'deitado', 'equipment' => [],
        'steps' => ['Deite ou sente com as pernas estendidas', 'Puxe a ponta dos pés para você', 'Empurre a ponta dos pés para longe'],
        'default_sets' => 2, 'default_reps' => 20, 'default_hold_s' => 0, 'times_per_day' => 3,
        'safety_notes' => ['Movimento contínuo e sem dor'], 'contraindication_flags' => [], 'needs_support' => false],
    ['code' => 'FIS-PE-003', 'name' => 'Apoio em uma perna', 'region' => 'tornozelo', 'type' => 'equilibrio', 'pose' => 'em_pe', 'equipment' => ['cadeira'],
        'steps' => ['Fique de frente para um apoio firme', 'Tire um pé do chão', 'Segure o tempo indicado', 'Troque a perna'],
        'default_sets' => 3, 'default_reps' => 1, 'default_hold_s' => 20, 'times_per_day' => 1,
        'safety_notes' => ['Sempre perto de um apoio firme', 'Pare se sentir tontura'], 'contraindication_flags' => ['risco_queda'], 'needs_support' => true],
    ['code' => 'FIS-PE-004', 'name' => 'Alongamento de panturrilha na parede', 'region' => 'tornozelo', 'type' => 'alongamento', 'pose' => 'em_pe', 'equipment' => ['parede'],
        'steps' => ['Mãos na parede, uma perna atrás', 'Calcanhar de trás no chão, joelho estendido', 'Leve o quadril à frente', 'Segure e troque a perna'],
        'default_sets' => 3, 'default_reps' => 1, 'default_hold_s' => 30, 'times_per_day' => 1,
        'safety_notes' => ['Sinta alongar, nunca doer'], 'contraindication_flags' => [], 'needs_support' => false],
    ['code' => 'FIS-PE-005', 'name' => 'Puxar a toalha com os dedos', 'region' => 'tornozelo', 'type' => 'fortalecimento', 'pose' => 'sentado', 'equipment' => ['toalha', 'cadeira'],
        'steps' => ['Sente com o pé descalço sobre uma toalha', 'Puxe a toalha dobrando os dedos', 'Solte e repita'],
        'default_sets' => 2, 'default_reps' => 10, 'default_hold_s' => 0, 'times_per_day' => 1,
        'safety_notes' => ['O calcanhar fica no chão'], 'contraindication_flags' => [], 'needs_support' => false],
];
