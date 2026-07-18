<?php

/**
 * RAGAS evaluation config — gate de qualidade IA-pair pra Brief Diário + recall tools.
 *
 * Referências:
 *  - ADR 0037 §GAP-2 (RAGAS gate em CI)
 *  - COMPARATIVO-MCP-ESTADO-DA-ARTE-2026-05-13.md §4 Gap #6 (P1)
 *
 * As 4 métricas RAGAS canônicas (todas score 0..1, maior = melhor):
 *  - faithfulness     — resposta sem alucinações (claims suportados pelo contexto)
 *  - answer_relevancy — resposta relevante pra query original
 *  - context_precision — contexto recuperado bem ranqueado
 *  - context_recall   — contexto recuperado cobre tudo necessário pra ground truth
 *
 * Thresholds calibrados em 2026-05-13 a partir de defaults RAGAS docs + targets
 * de produção Langfuse 2026 (faithfulness ≥0.9 ideal, ≥0.7 aceitável MVP).
 * Wagner ajusta com base em runs reais conforme baseline se forma.
 */
return [

    /*
    |--------------------------------------------------------------------------
    | Habilitar gate RAGAS
    |--------------------------------------------------------------------------
    | Default false — só roda em workflow_dispatch ou cron semanal (segunda 06h BRT).
    | Local dev: liga via .env RAGAS_ENABLED=true + OPENAI_API_KEY definido.
    */
    'enabled' => env('RAGAS_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Modelo judge (LLM-as-a-judge)
    |--------------------------------------------------------------------------
    | gpt-4o-mini = sweet spot custo/qualidade pra avaliação RAGAS-style
    | (~$0.00015 input / $0.0006 output por 1k tokens em 2026-05).
    | Alternativas: gpt-4o (4x mais caro, +5% acurácia), gpt-4.1-mini.
    */
    'judge_model' => env('RAGAS_JUDGE_MODEL', 'gpt-4o-mini'),

    /*
    |--------------------------------------------------------------------------
    | Backend do juiz — 'openai' (egress) | 'ollama' (local zero-egress · US-COPI-137 rota B)
    |--------------------------------------------------------------------------
    | 'openai' (default): manda o prompt pra api.openai.com (o batch RAGAS histórico).
    | 'ollama': manda pra um LLM self-hosted (CT 100), ZERO egress pra terceiro —
    |   é o judge do eval ONLINE quando `jana.online_eval.judge = 'local'` (rota B, [W]).
    |
    | PRÉ-REQS DE INFRA (decisão/execução [W] — o código já roteia atrás desta flag):
    |   1. Instalar um modelo de CHAT no Ollama do CT 100 (`ollama-embedder` só tem
    |      embeddings hoje): `docker exec ollama-embedder ollama pull qwen2.5:3b`
    |      (ou llama3.2:3b — 3B roda em CPU, ~5-30s/judge; escolha do [W] por recurso).
    |   2. Expor esse Ollama ao worker do app (ADR 0062: Hostinger ≠ CT 100). Padrão do
    |      projeto = subdomínio Traefik com IP-whitelist (como langfuse.oimpresso.com):
    |      apontar RAGAS_OLLAMA_URL pra ele. NUNCA expor Ollama sem auth.
    | Só então `RAGAS_JUDGE_BACKEND=ollama` + `jana.online_eval` gates ligam de verdade.
    */
    'judge_backend' => env('RAGAS_JUDGE_BACKEND', 'openai'),
    'ollama_url'    => env('RAGAS_OLLAMA_URL', ''),      // ex: https://ollama.oimpresso.com (CT 100, IP-whitelist)
    'ollama_model'  => env('RAGAS_OLLAMA_MODEL', 'qwen2.5:3b'),

    /*
    |--------------------------------------------------------------------------
    | Sample size por suite
    |--------------------------------------------------------------------------
    | Quantas perguntas avaliar por run (Brief + KbAnswer cada).
    | 5 = MVP barato (~$0.05/run). 20 = produção robusta (~$0.20/run).
    */
    'sample_size' => env('RAGAS_SAMPLE_SIZE', 5),

    /*
    |--------------------------------------------------------------------------
    | Thresholds canônicos (gate fail se métrica abaixo)
    |--------------------------------------------------------------------------
    | MVP 2026-05-13 — calibrado pra ser realistic-strict.
    | Produção alvo (Langfuse 2026): faithfulness ≥0.9, relevancy ≥0.85, precision ≥0.8.
    | Subir thresholds conforme baseline matura (target Q3/2026).
    */
    'thresholds' => [
        'faithfulness'       => env('RAGAS_THRESHOLD_FAITHFULNESS', 0.70),
        'answer_relevancy'   => env('RAGAS_THRESHOLD_RELEVANCY', 0.60),
        'context_precision'  => env('RAGAS_THRESHOLD_PRECISION', 0.70),
        'context_recall'     => env('RAGAS_THRESHOLD_RECALL', 0.60),
    ],

    /*
    |--------------------------------------------------------------------------
    | Timeout judge (segundos)
    |--------------------------------------------------------------------------
    | LLM judge pode demorar com prompts longos. 60s = safe.
    */
    'judge_timeout' => env('RAGAS_JUDGE_TIMEOUT', 60),

    /*
    |--------------------------------------------------------------------------
    | Diretório de output JSON (artefato CI)
    |--------------------------------------------------------------------------
    */
    'output_dir' => env('RAGAS_OUTPUT_DIR', storage_path('app/ragas')),

];
