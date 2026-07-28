<?php

return [
    'name' => 'VozDoCliente',

    /*
     * Severidade a partir da qual o sinal deixa de ser só registro e passa a
     * exigir triagem ativa. Espelha o limiar da skill `feedback-capture`
     * (severity >= 3 abre task) — mantido em config pra não virar número mágico
     * espalhado no código.
     */
    'severidade_que_exige_triagem' => env('VOZ_SEVERIDADE_TRIAGEM', 3),

    /*
     * Teto de sinais que um mesmo usuário pode abrir por hora. Não é anti-spam
     * anônimo (o canal é autenticado) — é guarda contra loop de automação
     * (US-INFRA-003 wire erro→sinal) inundar a caixa de triagem.
     */
    'limite_por_usuario_hora' => env('VOZ_LIMITE_USUARIO_HORA', 20),
];
