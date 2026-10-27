<?php

return [

    /*
     * US-REL-001 — relatório montável (fonte, campos, filtros, agrupamento, gráfico; salvo e reaberto).
     * Default false: as rotas respondem 404 até [W] ligar. Toggle: RELATORIO_MONTAVEL=true no .env.
     */
    'montavel' => [
        'enabled' => (bool) env('RELATORIO_MONTAVEL', false),
    ],

];
