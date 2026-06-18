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

];
