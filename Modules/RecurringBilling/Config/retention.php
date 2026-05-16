<?php

declare(strict_types=1);

/**
 * Política de retenção de dados — RecurringBilling.
 *
 * Wave 10 D7 LGPD (2026-05-16). Espelha pattern Crm/retention.php.
 *
 * Embasamento legal:
 *   - **CTN Art. 195 / 173**: documentos fiscais devem ser preservados por
 *     5 anos a partir do exercício seguinte (Receita Federal). Aplica-se a
 *     `rb_invoices` (cobranças geradas → suportam NFe/NFS-e).
 *   - **Lei 8.078/90 (CDC) Art. 27 e Decreto 6.523/08 Art. 9**: registros
 *     de relação de consumo + queixas/contestações devem ficar por até
 *     5 anos contados do término. Aplica-se a `rb_charge_attempts`.
 *   - **Decreto 7.962/13 + Resolução CMN 4.658/18**: registros de operações
 *     bancárias preservados por **7 anos** (chargebacks/disputas e
 *     comunicação com Bacen).
 *   - **LGPD Art. 16**: ao fim da retenção, dados devem ser anonimizados
 *     ou eliminados. Cron daily `php artisan rb:retention-purge` faz o GC
 *     (US futura — placeholder agora).
 *
 * Unidade: dias inteiros (purge job calcula `created_at < now()->subDays($n)`).
 *
 * @see memory/proibicoes.md § LGPD
 * @see Modules/Crm/Config/retention.php (template Wave 9 — não existe ainda,
 *      este RecurringBilling é o primeiro retention.php do projeto)
 */
return [

    /*
    |--------------------------------------------------------------------------
    | Subscriptions
    |--------------------------------------------------------------------------
    | Assinaturas ATIVAS (status active/trialing/past_due/paused) ficam
    | INDEFINIDAMENTE. Apenas após `status=canceled` o relógio começa.
    | Mantém 5 anos pós-cancelamento (suportar consulta histórica + contestação).
    */
    'subscriptions_canceled_days' => 1825, // 5 anos pós-cancelamento

    /*
    |--------------------------------------------------------------------------
    | Invoices (faturas / rb_invoices)
    |--------------------------------------------------------------------------
    | 5 anos (CTN Art. 195) — Receita Federal exige preservação de doc fiscal.
    | Cobre situação onde fatura gerou NFe/NFS-e (Modules/NfeBrasil).
    */
    'invoices_days' => 1825, // 5 anos

    /*
    |--------------------------------------------------------------------------
    | Charge Attempts (rb_charge_attempts — tentativas de cobrança)
    |--------------------------------------------------------------------------
    | 7 anos — chargebacks e contestações Asaas/Inter podem ser abertos por
    | até 540 dias pelo banco emissor + manutenção pós-disputa.
    | Append-only: nunca atualizamos, só descartamos no fim do prazo.
    */
    'charge_attempts_days' => 2555, // 7 anos

    /*
    |--------------------------------------------------------------------------
    | Webhook events (pg_webhook_events — Asaas/Inter)
    |--------------------------------------------------------------------------
    | 365 dias — payload já é redactado (PiiRedactor) mas evento bruto não
    | tem valor além de debug + audit incidente recente. Reduz volume DB.
    */
    'webhook_events_days' => 365, // 1 ano

    /*
    |--------------------------------------------------------------------------
    | Plans
    |--------------------------------------------------------------------------
    | Planos antigos (soft-deleted/inativos) — mantém 5 anos por audit fiscal
    | (NFe emitida cita preço do plano vigente na data; histórico necessário).
    */
    'plans_inactive_days' => 1825, // 5 anos

    /*
    |--------------------------------------------------------------------------
    | Activity Log (Spatie — activity_log table)
    |--------------------------------------------------------------------------
    | Audit trail de mudanças em Subscription/Invoice/ChargeAttempt/Plan/
    | BoletoCredential. 7 anos — mesmo prazo dos charge_attempts pra cruzar
    | informação durante contestação.
    */
    'activity_log_days' => 2555, // 7 anos

    /*
    |--------------------------------------------------------------------------
    | Anonymization strategy
    |--------------------------------------------------------------------------
    | Pra dados que precisam permanecer (estatística/aggregate) MAS perderam
    | razão de identificação direta. Usar o redactor padrão LGPD do projeto.
    */
    'anonymization' => [
        'driver' => 'pii_redactor', // Modules\Jana\Services\Privacy\PiiRedactor
        'mode'   => 'hash',         // hash determinístico curto pra cross-reference
    ],

    /*
    |--------------------------------------------------------------------------
    | Purge job (placeholder — US futura)
    |--------------------------------------------------------------------------
    | Cron diário 04:00 BRT que executa `rb:retention-purge`. Comando ainda
    | não implementado — esta config é o contrato. Próxima US.
    */
    'purge_command'      => 'rb:retention-purge',
    'purge_schedule_cron' => '0 4 * * *',
    'purge_enabled'      => env('RB_RETENTION_PURGE_ENABLED', false),
];
