<?php

declare(strict_types=1);

/**
 * Política de retenção LGPD — Modules/ProductCatalogue (Wave 25 D7.c).
 *
 * ProductCatalogue é catálogo público QR — exibe produtos do business via URL
 * `/catalogue/{business_id}/{location_id}` sem auth (vínculo único = QR code).
 * Não armazena PII do cliente (consumidor final scan QR, não cria conta).
 * Persiste apenas metadados de produto (já no schema core: `products`, `categories`,
 * `discounts`, `business_locations`).
 *
 * Por que ter retention.php mesmo sem PII direta?
 *  - **LGPD Art. 16**: dado de produto é dado da empresa (não titular), mas
 *    `product_catalogue_version` (table própria do módulo) pode acumular versões
 *    históricas — limite pra cleanup ad-hoc.
 *  - **Logs/Telemetria**: spans `product_catalogue.build_*_payload` via OpenTelemetry
 *    podem capturar IP do scanner (PII indireta em sample bursty) — retention OTel
 *    é gerido pelo collector central, não aqui.
 *  - **Compliance Audit Wave 25 D7.c**: rubrica governance v3 exige `retention.php`
 *    canônico em todo módulo functional_horizontal pra fechar a dimensão.
 *
 * Multi-tenant Tier 0 IRREVOGÁVEL ([ADR 0093]):
 * `business_id` global scope em toda query (ProductCatalogueRepository defesa em
 * profundidade — rota pública, atacante pode tentar enumerar tenants via QR).
 *
 * Append-only contrato:
 * `activity_log` (se aplicado a Product/Discount via core) é AUDITORIA —
 * NUNCA purgada pelo módulo.
 *
 * @see Modules/ProductCatalogue/Services/CatalogueService.php
 * @see Modules/ProductCatalogue/Database/Migrations/2020_09_29_184909_add_product_catalogue_version.php
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 */

return [
    /*
    |--------------------------------------------------------------------------
    | Habilitar política de retenção
    |--------------------------------------------------------------------------
    | Default false — sem job purge implementado (ADR 0105 sinal qualificado).
    | Cliente que use catálogo QR em escala pede ativação manual.
    */
    'enabled' => env('PRODUCTCATALOGUE_RETENTION_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Retenção por entidade (em DIAS)
    |--------------------------------------------------------------------------
    | product_catalogue_version: 1095 (3 anos) — versão histórica do catálogo
    |   QR exibido publicamente; útil pra rollback. Pós 3y purga.
    | Outras entidades (products/categories/discounts/business_locations) são core
    |   UltimatePOS — retention é responsabilidade do core, NÃO deste módulo.
    */
    'entities' => [
        'product_catalogue_version' => 1095,  // 3 anos
    ],

    /*
    |--------------------------------------------------------------------------
    | Estratégia de purge
    |--------------------------------------------------------------------------
    | 'soft_delete' = marca `deleted_at`
    | 'hard_delete' = DELETE definitivo (recomendado pra versões antigas)
    | 'anonymize'   = N/A (sem PII no schema)
    */
    'strategy' => env('PRODUCTCATALOGUE_RETENTION_STRATEGY', 'hard_delete'),

    /*
    |--------------------------------------------------------------------------
    | Janela de aviso prévio (em DIAS)
    |--------------------------------------------------------------------------
    | Sem titular (dado de empresa) — janela mantida pra alinhar com padrão D7.c.
    */
    'notice_period_days' => 30,
];
