<?php

return [
    'name' => 'NfeBrasil',
    'module_version' => '0.1.0',

    /**
     * Default ambiente SEFAZ. Tenant configura por business via wizard.
     */
    'ambiente_default' => 'homologacao',

    /**
     * US-RB-044 — listener EmitirNFeAoReceberPagamento.
     *
     * Quando true, listener tenta autorizar NFe55 via SEFAZ ao receber
     * InvoicePaid de cobrança recorrente. Default false até NfeService
     * estar implementado e validado.
     */
    'auto_emission_on_invoice_paid' => env('NFEBRASIL_AUTO_EMISSION', false),
];
