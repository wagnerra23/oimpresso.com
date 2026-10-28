<?php

return [
    'name' => 'Producao',

    // US-COMVIS-NEW-004 · UC-PCART-06 — manda o link da prova ao cliente por WhatsApp quando o link
    // é gerado ou trocado. Nasce desligado: ligar é decisão por ambiente (PRODUCAO_ARTE_WHATSAPP=true).
    'aprovacao_arte' => [
        'whatsapp' => (bool) env('PRODUCAO_ARTE_WHATSAPP', false),
    ],
];
