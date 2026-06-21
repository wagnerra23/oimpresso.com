<?php

/*
|--------------------------------------------------------------------------
| Jana · memória — namespace `jana.memoria.*` (rename Copiloto→Jana, ADR 0092)
|--------------------------------------------------------------------------
|
| O grosso da config do módulo ainda vive sob `copiloto.*` (rename PHP-only,
| compat — ver JanaServiceProvider::registerConfig). Este arquivo abre o
| namespace `jana.memoria.*` (mesmo precedente de `jana.retention`) SÓ pra
| chaves novas que nascem já no nome final.
|
| Primeira chave: a detecção automática de supersede event-time (ADR 0295
| slice 3), que a ADR define LITERALMENTE como
| `jana.memoria.supersede_detection.enabled`.
*/

return [

    /*
    |--------------------------------------------------------------------------
    | Detecção automática de update temporal (ADR 0295 slice 3)
    |--------------------------------------------------------------------------
    |
    | Um fato novo pode SUBSTITUIR um anterior no event-time (ex.: "a meta agora
    | é R$ 80 mil" supersede "a meta é R$ 50 mil"). Um agent Haiku decide; ao
    | detectar, o fluxo de consolidação marca `event_valid_until` do antigo e
    | grava `supersedes_id` no novo — APPEND-ONLY: NUNCA edita o conteúdo do
    | fato antigo (SupersedeDetector::consolidar).
    |
    | FLAG OFF POR DEFAULT (Tier-0-adjacente): com `enabled=false` o
    | ExtrairFatosDaConversaJob é BYTE-IDÊNTICO ao legado — zero custo LLM, zero
    | query extra. Wagner liga conscientemente SÓ em homolog após validar. O
    | default explícito `false` em config() cobre runtime com config publicada
    | stale (mesmo padrão de `copiloto.peso_real.retrieval_enabled`).
    */
    'supersede_detection' => [
        'enabled' => (bool) env('JANA_SUPERSEDE_DETECTION_ENABLED', false),

        // Confiança mínima (0-100) do agent pra APLICAR o supersede. Abaixo
        // disso o fato novo é só APENDADO (lembrar legado), sem link.
        'confianca_min' => (int) env('JANA_SUPERSEDE_DETECTION_CONFIANCA_MIN', 70),

        // Quantos fatos ATIVOS do (business,user) entram como candidatos no
        // prompt do agent (cap de tokens/custo).
        'max_candidatos' => (int) env('JANA_SUPERSEDE_DETECTION_MAX_CANDIDATOS', 20),

        // Modelo primário (Haiku, via provider `anthropic`) + fallback
        // (gpt-4o-mini, via `openai` — modelo com acesso confirmado no projeto).
        // SupersedeDetector tenta o primário só se ANTHROPIC_API_KEY existir;
        // qualquer falha cai pro fallback e, se ambos falharem, o fato é só
        // apendado (FAILSAFE — detecção nunca quebra a extração).
        'model' => (string) env('JANA_SUPERSEDE_DETECTION_MODEL', 'claude-haiku-4-5-20251001'),
        'fallback_model' => (string) env('JANA_SUPERSEDE_DETECTION_FALLBACK_MODEL', 'gpt-4o-mini'),
    ],

];
