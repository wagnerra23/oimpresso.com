<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Canal do S0 (Fase 1 · Plano Sustentável de Erros)
    |--------------------------------------------------------------------------
    |
    | 1 webhook/destino pro ÚNICO alerta que interrompe humano — push, destino
    | Slack-compatible, ou WhatsApp de 1 pessoa. [W] seta UMA vez no .env.
    | Sem env → degrada pra log (skip, sem crash). Payload sem PII (LGPD).
    |
    | @see prototipo-ui/handoffs/erros-fase1-classificacao.md
    */
    's0_channel' => env('ERROR_S0_WEBHOOK'),

    /*
    | Janela (minutos) do rate-limit do S0Alert: no máx 1 alerta por grupo
    | (dedupKey) por janela, via Cache::add. Reincidência só não repete.
    | A Fase 2 (E-2) endurece isto com contador persistente em error_groups.
    */
    's0_window_minutes' => (int) env('ERROR_S0_WINDOW_MINUTES', 15),

    /*
    | Janela de decaimento (dias) dos grupos de erro (Fase 2 · E-2): grupo aberto
    | sem ocorrência há N dias → arquivado pelo cron errors:archive-stale-groups.
    */
    'group_decay_days' => (int) env('ERROR_GROUP_DECAY_DAYS', 14),

    /*
    |--------------------------------------------------------------------------
    | Auto-resolução (Fase 2 · E-3) — retry / backoff / dead-letter
    |--------------------------------------------------------------------------
    |
    | Os S1/S2 recuperáveis do Mapa (SEFAZ fora, webhook atrasado, Baileys off)
    | se resolvem sozinhos sem acordar ninguém — sobe o "% auto-resolvido".
    | S0 NUNCA auto-resolve (dinheiro/dado/segurança = humano · ADR 0284 §4).
    | Sem retry infinito: esgotou → promove pra S1 e para.
    |
    | @see prototipo-ui/handoffs/erros-autoresolucao.md
    */
    'auto_resolve' => [
        'enabled' => (bool) env('ERROR_AUTO_RESOLVE_ENABLED', true),

        // Teto de tentativas — sem retry infinito (dead-letter promove pra S1).
        'max_attempts' => (int) env('ERROR_AUTO_RESOLVE_MAX_ATTEMPTS', 5),

        // Backoff exponencial: base · 2^(n-1), saturado no teto (segundos).
        'backoff_base_seconds' => (int) env('ERROR_AUTO_RESOLVE_BACKOFF_BASE_SECONDS', 30),
        'backoff_max_seconds' => (int) env('ERROR_AUTO_RESOLVE_BACKOFF_MAX_SECONDS', 900),

        // Whitelist de donos/domínios recuperáveis (S1/S2). Fora disto → humano.
        'whitelist_owners' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('ERROR_AUTO_RESOLVE_OWNERS', 'fiscal,cobranca,whatsapp,ingest'))
        ))),

        // Fila/conexão do reprocesso. Vazio → default do app. Em prod, aponte a
        // conexão pra 'reprocess' (retry_after alto · @see config/queue.php) pra o
        // worker não reclamar um job em backoff e duplicar efeito.
        'connection' => env('ERROR_AUTO_RESOLVE_CONNECTION') ?: null,
        'queue' => env('ERROR_AUTO_RESOLVE_QUEUE') ?: null,
    ],

];
