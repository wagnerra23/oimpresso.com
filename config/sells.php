<?php

declare(strict_types=1);

/**
 * Config canônica do módulo Sells (UltimatePOS core, não Laravel module).
 *
 * Onda 2.5 — feature flags pra integração progressiva Jana Copiloto real
 * no painel ✦ IA do drawer SaleSheet (POST /sells/{id}/ai-ask).
 *
 * Refs:
 *  - ADR 0035 Stack-alvo IA (gpt-4o-mini Brain A)
 *  - Modules/Jana/Ai/Agents/SaleInsightAgent.php
 *  - app/Http/Controllers/SellController.php::aiAsk
 *  - memory/requisitos/_DesignSystem/RUNBOOK-onda-cowork.md
 */
return [
    'ai' => [
        /*
         * Feature flag: tenta SaleInsightAgent (laravel/ai gpt-4o-mini) primeiro,
         * com fallback automático pro stub determinístico on error/timeout.
         *
         * Default: false (canary controlado). Wagner ativa via .env quando
         * OPENAI_API_KEY estiver configurada e quiser canary biz=1.
         *
         * Toggle prod:
         *   SELLS_AI_USE_JANA_REAL=true em .env Hostinger
         *
         * Toggle dev:
         *   php artisan config:cache && tinker → config(['sells.ai.use_jana_real'=>true])
         *
         * Pest: sempre false (determinístico) salvo override explícito por test.
         */
        'use_jana_real' => env('SELLS_AI_USE_JANA_REAL', false),

        /*
         * Modelo padrão pra SaleInsightAgent. Override só pra A/B test.
         * Default Brain A barato (gpt-4o-mini ~$0.0003/call).
         * Brain B (gpt-4o) sob demanda — ~10x mais caro, só pra suggest crítico.
         */
        'model' => env('SELLS_AI_MODEL', 'gpt-4o-mini'),

        /*
         * Timeout em segundos pra cada chamada. laravel/ai default 60s,
         * mas pro drawer (UX síncrono) queremos resposta <8s ou fallback.
         */
        'timeout_seconds' => (int) env('SELLS_AI_TIMEOUT', 8),
    ],
];
