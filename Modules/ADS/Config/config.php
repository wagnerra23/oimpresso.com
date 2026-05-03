<?php

return [
    'name' => 'ADS',

    /*
     * Token Bearer obrigatório em POST /api/ads/route.
     * Usado pelo Brain A daemon e integrações server-to-server.
     */
    'api_key' => env('ADS_API_KEY'),

    /*
     * Thresholds padrão do Decision Router (substituídos por mcp_decision_thresholds em runtime).
     * ARQ-0003 + ARQ-0004
     */
    'brain_a_risk_max'  => 0.30,
    'brain_a_conf_min'  => 0.70,
    'brain_b_risk_max'  => 0.70,

    /*
     * Janela de cancelamento HiTL-1 em segundos. ARQ-0008.
     */
    'hitl1_cancel_window_seconds' => 600,

    /*
     * Expiração de mutex por arquivo em segundos. ARQ-0003.
     */
    'file_lock_ttl_seconds' => 1800,

    /*
     * Peso de modificação humana no Confidence Engine. ARQ-0005.
     */
    'confidence_human_modify_weight' => 3.0,

    /*
     * Decaimento temporal de outcomes > 90 dias. ARQ-0005.
     */
    'confidence_decay_days'  => 90,
    'confidence_decay_factor' => 0.5,

    /*
     * Valor inicial de confiança para domínio/tipo nunca visto. ARQ-0005.
     */
    'confidence_initial' => 0.50,

    /*
     * Mínimo de execuções antes de calibração automática de threshold. ARQ-0007.
     */
    'learning_min_samples' => 10,
];
