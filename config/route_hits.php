<?php

/*
 * route_hits — telemetria LEVE de execução real por rota (sinal "servido").
 *
 * Fecha o gap "verificação runtime" da grade v3 (régua Coverband/Wallarm):
 * anchor-lint/charter-live-signal provam ESTÁTICO (existe + roteado); este
 * contador prova DINÂMICO (rota de fato servida nos últimos N dias).
 *
 * Tier 0 de produção — performance primeiro:
 *   - default OFF (ROUTE_HITS_ENABLED=false) → zero comportamento novo até canary
 *   - contagem em terminate() (pós-response) via Cache::increment — NUNCA write
 *     síncrono em DB por request; flush batch diário move cache → tabela agregada
 *   - ZERO PII / ZERO dimensão de tenant: só nome-da-rota (ou URI-pattern) + data
 */

return [
    // Liga a coleta no middleware. Rollout: canary com ROUTE_HITS_ENABLED=true
    // no .env de prod SÓ após aprovação Wagner (ver RUNBOOK-route-hits.md).
    'enabled' => (bool) env('ROUTE_HITS_ENABLED', false),

    // Amostragem probabilística [0.0..1.0]. 1.0 = conta toda request. Se o
    // increment em cache file pesar no shared hosting, baixar (ex 0.25) — o
    // export registra o sample_rate vigente pro consumidor ponderar.
    'sample_rate' => (float) env('ROUTE_HITS_SAMPLE_RATE', 1.0),

    // TTL (horas) dos contadores em cache — só precisam viver até o flush
    // diário; 48h dá folga pra flush perdido sem acumular lixo no driver file.
    'cache_ttl_horas' => (int) env('ROUTE_HITS_CACHE_TTL_HORAS', 48),

    // Retenção (dias) das linhas agregadas em route_hits — --prune do flush
    // apaga mais antigas. 90d cobre a janela padrão de 30d do export com folga.
    'retencao_dias' => (int) env('ROUTE_HITS_RETENCAO_DIAS', 90),
];
