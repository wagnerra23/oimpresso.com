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

    /**
     * US-NFE-002 fase 2B: enviar DANFE NFC-e (modelo 65) por e-mail quando NFCeAutorizada.
     *
     * Default false: NFC-e venda balcão B2C frequentemente é "consumidor anônimo"
     * sem email cadastrado — silencioso quando não há email é o comportamento
     * desejado, mas habilitar cega o caso comum só pra notificar minoria.
     * Cliente liga via UI quando quer envio automático (ex: e-commerce que
     * captura email no checkout e quer DANFE como recibo).
     *
     * Resolve email via `transactions.contact_id` → `Contact.email`.
     */
    'email_danfe_nfce_on_autorizada' => env('NFEBRASIL_EMAIL_DANFE_NFCE', false),

    /**
     * Responsável técnico (cstat 972 — obrigatório no XML NF-e/NFC-e 4.00).
     *
     * Convenção oimpresso: WR2 Sistemas (Wagner) é o desenvolvedor do sistema.
     * Pode ser sobrescrito via env NFEBRASIL_RESPTEC_* pra cada deploy/cliente.
     *
     * Se cnpj vazio, tag <infRespTec> NÃO é incluída — útil pra dev/test sem
     * resp tec configurado, mas SEFAZ rejeita (cstat 972) na maioria dos casos.
     */
    'resp_tec' => [
        'cnpj'    => env('NFEBRASIL_RESPTEC_CNPJ', ''),
        'contato' => env('NFEBRASIL_RESPTEC_CONTATO', 'WR2 Sistemas'),
        'email'   => env('NFEBRASIL_RESPTEC_EMAIL', ''),
        'fone'    => env('NFEBRASIL_RESPTEC_FONE', ''),
    ],
];
