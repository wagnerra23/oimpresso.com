<?php

declare(strict_types=1);

/**
 * LGPD Retention Policy — Modules/Superadmin (Wave 11 D7.c).
 *
 * Política de retenção de dados append-only do módulo Superadmin (cross-tenant
 * Wagner-only). Valores em DIAS pra consumo por jobs de purge/anonymize futuros
 * (`Modules\Superadmin\Console\RetentionCleanupCommand` — TODO próximo PR).
 *
 * Base legal:
 * - LGPD Art. 16: dados pessoais eliminados ao fim do tratamento, salvo:
 *   - Art. 16 II: cumprimento obrigação legal/regulatória
 *   - Art. 16 III: estudo por órgão de pesquisa
 *   - Art. 16 IV: transferência a terceiros (vedado p/ Superadmin)
 *   - Art. 16 V: uso exclusivo controlador, vedado acesso terceiro
 * - CC Art. 206 §5º I: prescrição 10 anos pra cobrança dívida líquida
 *   constante em instrumento público/particular (subscriptions, faturas)
 * - LGPD Art. 7º X: legítimo interesse para fraud detection
 * - ANPD Resolução CD/ANPD nº 02/2022: auditoria deve permitir reconstrução
 *
 * Constituição Art. 4 (Compliance LGPD) + ADR 0093 (Multi-tenant Tier 0).
 *
 * Convenção: valor `null` = retenção indefinida (não purgar).
 * Convenção: valor inteiro = dias após `created_at` (ou `deleted_at` em soft-delete).
 */

return [
    /*
    |--------------------------------------------------------------------------
    | Audit append-only (activity_log Spatie)
    |--------------------------------------------------------------------------
    */

    // Trail de admin actions cross-tenant (qualquer mudança em Package,
    // Subscription, business via superadmin). CC Art. 206 §5º I — 10 anos
    // prescrição decenal aplicável a registros financeiros/contratuais.
    'admin_actions' => 2555, // 10 anos (365×7 + 366×3 ≈ 2557; arredondado)

    // Histórico de feature_flags / settings globais. Governança requer trail
    // permanente pra reconstruir contexto de decisão arquitetural. Indefinida
    // = poda apenas via ADR mãe nova (Constituição append-only ADRs §10.4).
    'feature_flags_history' => null,

    // Mudanças em System settings (SMTP, gateway keys excluídos — vão pro
    // Vaultwarden; aqui apenas metadata: quem mudou, quando, qual chave —
    // valor mascarado). 5 anos cobre fiscalização ANPD + auditoria interna.
    'settings_changes' => 1825, // 5 anos (365×5)

    /*
    |--------------------------------------------------------------------------
    | Communicator (mensagens cross-tenant)
    |--------------------------------------------------------------------------
    */

    // Logs de comunicação mass-direct a businesses (subject/message/recipients).
    // Subject/message podem conter avisos legais/manutenção. 5 anos cobre
    // disputa fiscal (CC Art. 206 §3º V — reparação civil) + prova de aviso.
    // Conteúdo redactado via PiiRedactor antes de log (D7.a).
    'communicator_logs' => 1825, // 5 anos

    /*
    |--------------------------------------------------------------------------
    | Frontend Pages (Termos/Privacidade)
    |--------------------------------------------------------------------------
    */

    // Versões históricas de Termos de Uso / Política de Privacidade.
    // LGPD Art. 9º exige informação ao titular — manter versões antigas
    // permite provar qual termo estava ativo quando consent foi dado.
    // Indefinida — auditoria ANPD pode exigir reconstrução de qualquer época.
    'frontend_pages_history' => null,

    /*
    |--------------------------------------------------------------------------
    | Subscriptions (eventos fiscais cross-tenant)
    |--------------------------------------------------------------------------
    */

    // Subscriptions soft-deleted. CC Art. 206 §5º I — 10 anos prescrição.
    // Hard-delete só após esse prazo (ainda assim manter resumo financeiro
    // em activity_log permanente).
    'subscriptions_soft_deleted' => 3650, // 10 anos

    // Tentativas de pagamento offline pendentes não-resolvidas. Após 2 anos
    // sem resolução, anonymize (remove payer_name/email do payload).
    'pending_offline_payments' => 730, // 2 anos
];
