<?php

declare(strict_types=1);

/**
 * Retenção LGPD — Modules/Manufacturing (Wave 14 D7.c sessão 2026-05-16).
 *
 * Política de retenção por categoria de dado de produção/manufatura. Valores em DIAS.
 * Pós-prazo, dados podem ser anonimizados/purgados via job dedicado
 * (futuro: `manufacturing:purge-expired`) — este config define os limites.
 *
 * Bases legais (Brasil):
 *  - **CTN Art. 195** (Código Tributário Nacional) — livros + documentos da
 *    escrituração devem ser conservados até prescrição dos créditos tributários
 *    (5 anos = 1825d). Produção alimenta CMV / inventário fiscal.
 *  - **CC Art. 206** (Código Civil) — escrituração mercantil 10 anos (2555d).
 *  - **LGPD Art. 16** — dados pessoais devem ser eliminados após o término
 *    do tratamento, salvo cumprimento de obrigação legal (CTN justifica retenção).
 *
 * Receitas (mfg_recipes / mfg_recipe_ingredients / mfg_ingredient_groups) NÃO
 * contêm PII direta — são fórmulas industriais. Retenção é INDEFINIDA (negócio):
 * receita continua válida enquanto produto-pai (variation) existe. Quando produto
 * é descontinuado, política é manter histórico pra reconstruir margem retroativa.
 *
 * Multi-tenant Tier 0 (ADR 0093): purga é per-business_id quando rodada.
 *
 * Override via .env: `MANUFACTURING_RETENTION_PRODUCTION_LOGS_DAYS=...`
 *
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 */

return [

    /**
     * Logs de produção — registros de `transactions` type=production_purchase /
     * production_sell + linhas filhas (transaction_sell_lines com mfg_ingredient_group_id).
     *
     * Base: CTN Art. 195 (prescrição tributária 5 anos). Produção feeds CMV e
     * inventário fiscal — pressupõe rastreabilidade quinquenal.
     */
    'production_logs' => [
        'days' => env('MANUFACTURING_RETENTION_PRODUCTION_LOGS_DAYS', 1825),
        'tables' => ['transactions', 'transaction_sell_lines'],
        'filter' => "type IN ('production_purchase','production_sell')",
        'legal_basis' => 'CTN Art. 195 (5 anos prescrição tributária / CMV)',
    ],

    /**
     * Receitas (BOM — Bill of Materials) — mfg_recipes + mfg_recipe_ingredients.
     *
     * RETENÇÃO INDEFINIDA por design: receita é fórmula industrial vinculada
     * a variation (produto). Enquanto produto existe, receita é válida.
     * Sem PII direta (nomes de produto/instruções de preparo).
     */
    'recipes' => [
        'days' => null, // INDEFINIDO — manter enquanto variation-pai existir
        'tables' => ['mfg_recipes', 'mfg_recipe_ingredients'],
        'legal_basis' => 'Negócio — fórmula industrial vinculada a produto (sem PII)',
    ],

    /**
     * Grupos de ingredientes (mfg_ingredient_groups) — agrupamento UI/UX.
     *
     * Sem PII. Retenção indefinida (dado de configuração, não operacional).
     */
    'ingredient_groups' => [
        'days' => null,
        'tables' => ['mfg_ingredient_groups'],
        'legal_basis' => 'Negócio — taxonomia de ingredientes (sem PII)',
    ],

    /**
     * Logs de auditoria (Spatie activity_log) — subject_type filtrado por
     * Modules\Manufacturing\Entities\* (MfgRecipe / MfgRecipeIngredient /
     * MfgIngredientGroup têm LogsActivity ativo).
     *
     * Base: CC Art. 206 (10 anos rastreabilidade escrituração).
     */
    'logs_audit_manufacturing' => [
        'days' => 2555,
        'tables' => ['activity_log'],
        'filter' => "log_name IN ('manufacturing.recipe','manufacturing.recipe_ingredient','manufacturing.ingredient_group')",
        'legal_basis' => 'CC Art. 206 (10 anos rastreabilidade escrituração)',
    ],

];
