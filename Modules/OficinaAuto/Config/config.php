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

    /*
     * Consulta de placa (digita placa → dados do veículo) — charter Create v2.
     *
     * Adapter agnóstico (decisão Wagner 2026-06-09): driver `stub` é o padrão
     * (dev/CI, sem custo, sem rede). Pra plugar um fornecedor BR real, defina
     * OFICINA_PLACA_DRIVER=http + as variáveis http.* (api_key vai no Vaultwarden,
     * NUNCA commitada — ADR 0061 / feedback-nunca-publicar-credenciais).
     *
     * Escopo LGPD: SÓ dados técnicos. Nenhum campo de proprietário é consultado
     * nem armazenado — sem PII de terceiro.
     */
    'placa_lookup' => [
        'driver'    => env('OFICINA_PLACA_DRIVER', 'stub'),
        'cache_ttl' => (int) env('OFICINA_PLACA_CACHE_TTL', 86400),

        'http' => [
            'base_url'  => env('OFICINA_PLACA_BASE_URL'),
            'api_key'   => env('OFICINA_PLACA_API_KEY'),
            // Como a key é enviada: 'query' (?token=) | 'bearer' | 'header'.
            'auth_mode' => env('OFICINA_PLACA_AUTH_MODE', 'query'),
            'auth_key'  => env('OFICINA_PLACA_AUTH_PARAM', 'token'),
            'timeout'   => (int) env('OFICINA_PLACA_TIMEOUT', 8),

            // Mapeia campo-do-fornecedor → campo canônico (cada fornecedor usa
            // nomes diferentes). Suporta dot-notation pra respostas aninhadas.
            // Edite estes nomes aqui ao plugar um fornecedor (não vão no .env —
            // são estrutura de resposta, não credencial/knob de runtime).
            'field_map' => [
                'brand'            => 'marca',
                'model'            => 'modelo',
                'manufacture_year' => 'ano',
                'model_year'       => 'anoModelo',
                'color'            => 'cor',
                'fuel_type'        => 'combustivel',
                'chassis'          => 'chassi',
                'engine'           => 'motor',
                'renavam'          => 'renavam',
            ],
        ],
    ],
];
