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

    /**
     * US-NFE-002 fase 1 — listener EmitirNfceAoFinalizarVenda.
     *
     * Quando true, listener escuta `App\Events\SellCreatedOrModified` e
     * dispara `EmitirNfceJob` pra vendas finalizadas (type='sell' + status='final'
     * + payment_status in paid|partial). Default false até business ter cert A1
     * + ncm_default + (opcional) cliente com tax_number configurados. Fase 2
     * implementa submissão SEFAZ real; fase 1 é wire elétrico + idempotência.
     */
    'auto_emission_on_sell_completed' => env('NFEBRASIL_AUTO_EMISSION_NFCE', false),

    /**
     * US-NFE-044 fase 2 — listener EnviarDanfePorEmail.
     *
     * Quando true, ao receber NFeAutorizada event o listener envia DANFE PDF
     * + XML autorizado por e-mail pro destinatário (resolve via Invoice→Contact).
     * Default true: emissões automáticas (recorrência) sempre notificam o cliente.
     * Pode desligar via env quando emissão manual UI quiser controle do envio.
     */
    'email_danfe_on_autorizada' => env('NFEBRASIL_EMAIL_DANFE', true),
];
