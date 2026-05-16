<?php

declare(strict_types=1);

/**
 * Política de retenção de dados — ProductCatalogue.
 *
 * Wave 11 D7 LGPD (2026-05-16). Espelha pattern Modules/RecurringBilling/Config/retention.php
 * e Modules/Crm/Config/retention.php.
 *
 * Embasamento legal:
 *   - **CTN Art. 195 / 173**: documentos fiscais correlatos a produtos preservados
 *     5 anos a partir do exercício seguinte. ProductCatalogue NÃO emite NFe, mas
 *     produtos referenciados em NFe (via `App\Product`) seguem essa retenção.
 *   - **LGPD Art. 16**: ao fim da retenção, dados devem ser anonimizados ou
 *     eliminados. ProductCatalogue não armazena PII visitante (catálogo READ-ONLY),
 *     mas produtos INATIVOS (`App\Product::is_inactive=1`) seguem janela definida
 *     pra suportar consulta histórica + auditoria fiscal.
 *   - **CDC Art. 27**: prescrição quinquenal pra registros de relação consumo —
 *     5 anos cobre demanda histórica.
 *
 * Unidade: dias inteiros (purge job futuro calculará `updated_at < now()->subDays($n)`).
 *
 * Nota: ProductCatalogue NÃO tem job de purge próprio neste momento — relata na
 * Wave futura quando US explícita emergir (cliente sinal qualificado ADR 0105).
 * Por ora, é POLÍTICA DECLARATIVA — operador de DPO pode auditar e executar
 * limpeza manual via `App\Product::where('is_inactive', 1)->where(...)`.
 *
 * @see memory/proibicoes.md § LGPD
 * @see memory/requisitos/ProductCatalogue/SPEC.md § D7 LGPD
 * @see Modules/RecurringBilling/Config/retention.php (template Wave 10)
 */
return [

    /*
    |--------------------------------------------------------------------------
    | Produtos inativos
    |--------------------------------------------------------------------------
    | Produtos com `App\Product::is_inactive=1` ficam por 5 anos (1825 dias)
    | contados de `updated_at` (quando foram marcados inativos). Após esse
    | prazo, podem ser hard-deletados ou anonimizados (nome → "PRODUTO REMOVIDO #ID").
    |
    | 5 anos cobre CTN Art. 195 (fiscal) + CDC Art. 27 (consumo).
    */
    'products_inactive_days' => 1825, // 5 anos pós-inativação

    /*
    |--------------------------------------------------------------------------
    | Categorias órfãs
    |--------------------------------------------------------------------------
    | Categorias com `App\Category::deleted_at NOT NULL` (soft-delete) há mais
    | de 5 anos podem ser hard-deletadas — não estão mais associadas a
    | produtos ativos e atravessaram período fiscal.
    */
    'categories_softdeleted_days' => 1825, // 5 anos pós-soft-delete

    /*
    |--------------------------------------------------------------------------
    | Activity log do catálogo
    |--------------------------------------------------------------------------
    | Entries em `activity_log` (Spatie) referentes a CRUD de `App\Product` /
    | `App\Category` ficam 5 anos. Auditoria fiscal exige preservação.
    | Anonimização (não eliminação) é preferida pra manter trilha histórica.
    */
    'activity_log_days' => 1825, // 5 anos

    /*
    |--------------------------------------------------------------------------
    | Logs de erro do catálogo público
    |--------------------------------------------------------------------------
    | Logs do Laravel (`storage/logs/laravel.log`) com PII já redacionada
    | (via CatalogueLogger) seguem rotação padrão do servidor (`logrotate`
    | diário, 14 dias). Sem regra específica do módulo.
    */
    'application_logs_days' => 14, // rotação default Hostinger

];
