<?php

declare(strict_types=1);

/**
 * Política de retenção LGPD — Modules/ProductCatalogue (Wave 25 D7.c).
 *
 * Estado medido em 2026-11-11 — este arquivo é inerte e descreve algo que não existe:
 *
 *  - **Não há tabela `product_catalogue_version`.** A única migration do módulo
 *    (`2020_09_29_184909_add_product_catalogue_version.php`) grava a linha
 *    `productcatalogue_version` na tabela `system` do núcleo (a versão do módulo
 *    instalado). Não há histórico de catálogo nem dado a reter.
 *  - **Nenhum código de produção lê este arquivo.** O ServiceProvider só faz
 *    `mergeConfigFrom` do `config.php`; `config('productcatalogue.retention')` não
 *    existe e as variáveis `PRODUCTCATALOGUE_RETENTION_*` só aparecem aqui. O único
 *    leitor é o `Tests/Feature/Wave26ProductCatalogueSaturationTest.php`, que não roda
 *    em lane e sai no #10784.
 *  - **Os spans não carregam IP.** `product_catalogue.build_*_payload` declaram só
 *    `business_id`, `location_id` e `product_id` (`Services/CatalogueService.php`).
 *  - **LGPD Art. 16:** a vitrine não coleta dado pessoal (o visitante é anônimo,
 *    sem conta nem formulário), coerente com `module.json` `retention_days: 0`.
 *  - O motivo original (rubrica "Compliance Audit Wave 25 D7.c", governance v3)
 *    foi aposentado pela ADR 0399.
 *
 * Não ligar purge nem retenção aqui: num ERP o controle é por permissão de acesso,
 * não por apagar dado (proibicoes.md §5 2026-07-27). Os valores abaixo ficam como
 * estavam só por não terem efeito; se este arquivo sair, sai inteiro.
 *
 * Isolamento por empresa (ADR 0093): não vem de global scope (`App\Product` não tem);
 * vem do `where('business_id', ...)` de cada consulta do ProductCatalogueRepository.
 *
 * @see Modules/ProductCatalogue/Database/Migrations/2020_09_29_184909_add_product_catalogue_version.php
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 */

return [
    /*
    |--------------------------------------------------------------------------
    | Habilitar política de retenção
    |--------------------------------------------------------------------------
    | false e sem job de purge. Não ligar (proibicoes.md §5 2026-07-27).
    */
    'enabled' => env('PRODUCTCATALOGUE_RETENTION_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Retenção por entidade (em DIAS)
    |--------------------------------------------------------------------------
    | `product_catalogue_version` NÃO é tabela (ver docblock): a chave não aponta
    |   para dado nenhum. products/categories/discounts/business_locations são do
    |   núcleo, não deste módulo.
    */
    'entities' => [
        'product_catalogue_version' => 1095,  // 3 anos
    ],

    /*
    |--------------------------------------------------------------------------
    | Estratégia de purge
    |--------------------------------------------------------------------------
    | Valor sem efeito: nenhum código de produção lê este arquivo e não há tabela a purgar.
    */
    'strategy' => env('PRODUCTCATALOGUE_RETENTION_STRATEGY', 'hard_delete'),

    /*
    |--------------------------------------------------------------------------
    | Janela de aviso prévio (em DIAS)
    |--------------------------------------------------------------------------
    | Sem efeito, pelo mesmo motivo.
    */
    'notice_period_days' => 30,
];
