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
     * CNAEs cobertos por esta vertical (ADR 0137 §"Decisão"):
     * - 4520-0/01 — Manutenção e reparação mecânica de veículos automotores (oficinas gerais)
     * - 2212-9/00 — Recapagem de pneumáticos (Vargas — recapagem caçamba caminhão)
     * - 4581-4/00 — Locação de veículos (Martinho — caçambas estacionárias avulsas)
     */
    'cnaes' => ['4520-0/01', '2212-9/00', '4581-4/00'],
    'cnae_principal' => '4520-0/01',

    /*
     * Política de retenção LGPD (Art. 16) — vide Config/retention.php pro detalhe.
     * 1825 dias (5 anos) cobre Marco Civil Art. 15, CTN Art. 174, CONFAZ SINIEF 07/2005.
     */
    'retention_days' => 1825,
];
