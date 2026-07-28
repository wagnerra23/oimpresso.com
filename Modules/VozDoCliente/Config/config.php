<?php

return [
    'name' => 'VozDoCliente',

    /*
     * Severidade a partir da qual o sinal deixa de ser só registro e passa a
     * exigir triagem ativa. Espelha o limiar da skill `feedback-capture`
     * (severity >= 3 abre task) — mantido em config pra não virar número mágico
     * espalhado no código.
     *
     * SEM `env()` de propósito: chamada de env fora de `config/` raiz é erro do
     * Larastan (`noEnvCallsOutsideOfConfig`) — os módulos que fazem isso só
     * passam por estarem no baseline, e criar dívida nova pra um arquivo novo
     * seria pedir grandfather. Quem quiser sobrescrever publica o config
     * (`php artisan vendor:publish --tag=config`) e edita `config/vozdocliente.php`,
     * onde `env()` é legítimo.
     */
    'severidade_que_exige_triagem' => 3,
];
