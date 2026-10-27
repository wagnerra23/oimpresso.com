<?php

return [
    'name' => 'RecurringBilling',
    'module_version' => '0.1.0',

    // US-RB-021: cobrança automática da fatura pelo Pix Automático do BCB (decisão [W] 2026-10-26).
    // Default OFF. Ligar é decisão [W] — mexe em valor (cobra o cliente).
    'cobrar_pix_automatico' => env('RB_COBRAR_PIX_AUTOMATICO', false),
];
