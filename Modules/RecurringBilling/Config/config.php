<?php

return [
    'name' => 'RecurringBilling',
    'module_version' => '0.1.0',

    // US-RB-057: cobrança paga pelo PaymentGateway (origem_type=invoice) marca a fatura paga e
    // dispara InvoicePaid. Default OFF: ligar é decisão [W] (com a NF-e automática ligada, emite NF-e).
    'marcar_fatura_paga_por_cobranca' => env('RB_MARCAR_FATURA_PAGA_POR_COBRANCA', false),
    // US-RB-021: cobrança automática da fatura pelo Pix Automático do BCB (decisão [W] 2026-10-26).
    // Default OFF. Ligar é decisão [W] — mexe em valor (cobra o cliente).
    'cobrar_pix_automatico' => env('RB_COBRAR_PIX_AUTOMATICO', false),
    // US-RB-011 (reaberta [W] 2026-10-27): fatura paga no cartão guarda o TOKEN do cartão (nunca o
    // número) e liga à assinatura. Default OFF; não mexe em valor.
    'salvar_cartao' => env('RB_SALVAR_CARTAO', false),
];
