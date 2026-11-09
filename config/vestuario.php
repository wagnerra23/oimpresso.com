<?php

return [
    'etiqueta' => [
        // VEST-D1: com false (padrão) só registra em log quem seria bloqueado; com true dá 403.
        'exigir_permissao' => (bool) env('VESTUARIO_ETIQUETA_EXIGIR_PERMISSAO', false),
    ],
];
