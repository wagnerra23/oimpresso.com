<?php

/**
 * Config Modules/OficinaAuto (ADR 0137).
 *
 * Vertical oficinas automotivas BR. V0 em construção.
 * Schema multi-placa nullable atende: PLACA única (Martinho) + cavalo+reboque (Vargas).
 */
return [
    'name' => 'OficinaAuto',
    'module_version' => '0.1.0',

    /*
     * CNAEs cobertos por esta vertical (ADR 0137 §"Decisão" · amendado por ADR 0194):
     * - 4520-0/01 — Manutenção e reparação mecânica de veículos automotores (oficinas gerais + Martinho mecânica pesada caminhão basculante · sub-vertical 4 LIVE prod biz=164)
     * - 2212-9/00 — Recapagem de pneumáticos (Vargas · sub-vertical 2 V1)
     * - 4581-4/00 — Locação de veículos (sub-vertical 3 hipotético locação caçamba container · sem cliente real ancorado pós-ADR 0194 — pré-correção dizia "Martinho caçambas estacionárias avulsas")
     */
    'cnaes' => ['4520-0/01', '2212-9/00', '4581-4/00'],
    'cnae_principal' => '4520-0/01',
];
