<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Seeder MINIMAL de tenant pro gate de regressão visual autenticado (US-GOV-013 Fase B).
 *
 * Substitui o DummyBusinessSeeder (demo UltimatePOS de 2018, 1464 linhas) que estava
 * PODRE contra o schema atual: inseria em `contacts.first_name` (coluna removida) →
 * "Unknown column" → sem business → AuthBridgeSmokeTest skipava → telas autenticadas
 * (99% do app, onde mora o risco visual) ficavam fora do gate.
 *
 * Em vez de caçar drift coluna-a-coluna nas ~30 tabelas do demo, este seeder cria o
 * MÍNIMO bootável pro smoke renderizar `/financeiro/unificado` e `/sells`:
 *
 *   - 1 currency (reusa a 1ª de CurrenciesTableSeeder, ou cria BRL fallback)
 *   - business 1 + business_location 1 + invoice_scheme 1 + invoice_layout 1
 *   - admin (user id=1, business_id=1) com role spatie `Admin#1`
 *   - 1 contato default "Walk-In Customer" (POS/Sells referencia)
 *
 * Por que role `Admin#1` basta (sem enumerar permissões): o Gate::before em
 * AuthServiceProvider concede TODAS as abilities a quem `hasRole('Admin#'.$business_id)`.
 *
 * Por que só colunas existentes + as NOT-NULL-sem-default: o gate roda MySQL com
 * `'strict' => false` (config/database.php) → colunas omitidas ganham default implícito.
 *
 * IDEMPOTENTE: re-rodar é no-op (browser tests NÃO usam RefreshDatabase — Pest.php).
 *
 * @see tests/Browser/CoreScreens/AuthBridgeSmokeTest.php
 * @see .github/workflows/visual-regression.yml
 * @see app/Providers/AuthServiceProvider.php (Gate::before — Admin#N = tudo)
 * @see app/Http/Middleware/SetSessionData.php (exige business + currency)
 */
class VisregTenantSeeder extends Seeder
{
    public function run(): void
    {
        if (DB::table('business')->where('id', 1)->exists()) {
            $this->ensureAdminRole();
            $this->ensureProduct();

            return;
        }

        $currencyId = DB::table('currencies')->min('id');
        if (! $currencyId) {
            $currencyId = DB::table('currencies')->insertGetId([
                'country' => 'Brasil', 'currency' => 'Real', 'code' => 'BRL',
                'symbol' => 'R$', 'thousand_separator' => '.', 'decimal_separator' => ',',
            ]);
        }

        // FK circular: business.owner_id → users.id e users.business_id → business.id.
        // Mesma saída do DummyBusinessSeeder legacy: desliga checagem durante o bootstrap.
        DB::statement('SET FOREIGN_KEY_CHECKS = 0');

        try {
            DB::table('business')->insert([
                'id' => 1,
                'name' => 'Tenant Visual Regression',
                'currency_id' => $currencyId,
                'owner_id' => 1,
                'time_zone' => 'America/Sao_Paulo',
            ]);

            DB::table('users')->insert([
                'id' => 1,
                'user_type' => 'user',
                'surname' => 'Sr.',
                'first_name' => 'Admin',
                'last_name' => 'Visreg',
                'username' => 'visreg_admin',
                'email' => 'visreg-admin@example.test',
                'password' => Hash::make('visreg-secret-not-for-prod'),
                'language' => 'pt',
                'business_id' => 1,
                'status' => 'active',
                'allow_login' => 1,
            ]);

            DB::table('invoice_schemes')->insert([
                'id' => 1, 'business_id' => 1, 'name' => 'Default', 'scheme_type' => 'blank',
            ]);

            DB::table('invoice_layouts')->insert([
                'id' => 1, 'business_id' => 1, 'name' => 'Default',
            ]);

            DB::table('business_locations')->insert([
                'id' => 1,
                'business_id' => 1,
                'name' => 'Matriz',
                'country' => 'Brasil',
                'state' => 'SP',
                'city' => 'Sao Paulo',
                'zip_code' => '0000000',
                'invoice_scheme_id' => 1,
                'invoice_layout_id' => 1,
            ]);

            DB::table('contacts')->insert([
                'business_id' => 1,
                'type' => 'customer',
                'name' => 'Walk-In Customer',
                'contact_id' => 'CO0001',
                'mobile' => '',
                'created_by' => 1,
                'is_default' => 1,
            ]);
        } finally {
            DB::statement('SET FOREIGN_KEY_CHECKS = 1');
        }

        $this->ensureAdminRole();
        $this->ensureProduct();
    }

    /**
     * Produto mínimo vendável (Onda Q2 — UC-S01 venda balcão a prazo no e2e-gate).
     *
     * O `/products/list` (ProductUtil::filterProduct) exige products+variations
     * (joins de unit/estoque são LEFT) com is_inactive=0, not_for_selling=0 e
     * type != modifier. `enable_stock=0` evita exigir variation_location_details.
     * Estrutura UltimatePOS single: products 1—1 product_variations(DUMMY) 1—1 variations.
     * IDEMPOTENTE pela sku E2E-0001.
     */
    private function ensureProduct(): void
    {
        if (DB::table('products')->where('business_id', 1)->where('sku', 'E2E-0001')->exists()) {
            return;
        }

        $unitId = DB::table('units')->where('business_id', 1)->value('id');
        if (! $unitId) {
            $unitId = DB::table('units')->insertGetId([
                'business_id' => 1, 'actual_name' => 'Unidade', 'short_name' => 'Un',
                'allow_decimal' => 0, 'created_by' => 1,
            ]);
        }

        $productId = DB::table('products')->insertGetId([
            'name' => 'Produto E2E Balcão', 'business_id' => 1, 'type' => 'single',
            'unit_id' => $unitId, 'sku' => 'E2E-0001', 'barcode_type' => 'C128',
            'enable_stock' => 0, 'not_for_selling' => 0, 'is_inactive' => 0,
            'tax_type' => 'exclusive', 'created_by' => 1,
        ]);

        $pvId = DB::table('product_variations')->insertGetId([
            'product_id' => $productId, 'name' => 'DUMMY', 'is_dummy' => 1,
        ]);

        DB::table('variations')->insert([
            'name' => 'DUMMY', 'product_id' => $productId, 'product_variation_id' => $pvId,
            'sub_sku' => 'E2E-0001',
            'default_purchase_price' => 50, 'dpp_inc_tax' => 50, 'profit_percent' => 0,
            'default_sell_price' => 100, 'sell_price_inc_tax' => 100,
        ]);

        // Sem esta linha o produto NÃO aparece no /products/list: filterProduct
        // aplica ->ForLocation($location_id) → whereHas product_locations
        // (run 27368277842 — "Nenhum produto encontrado").
        DB::table('product_locations')->insert([
            'product_id' => $productId, 'location_id' => 1,
        ]);
    }

    /**
     * Role spatie `Admin#1` (guard web) + vínculo com o admin (model_has_roles).
     * Insert direto (sem o model spatie) pra controlar `roles.business_id` (NOT NULL)
     * e o morph `model_type` = App\User (sem morphMap no projeto).
     */
    private function ensureAdminRole(): void
    {
        $roleId = DB::table('roles')->where('name', 'Admin#1')->where('guard_name', 'web')->value('id');
        if (! $roleId) {
            $roleId = DB::table('roles')->insertGetId([
                'name' => 'Admin#1',
                'guard_name' => 'web',
                'business_id' => 1,
            ]);
        }

        $linked = DB::table('model_has_roles')
            ->where('role_id', $roleId)
            ->where('model_type', \App\User::class)
            ->where('model_id', 1)
            ->exists();

        if (! $linked) {
            DB::table('model_has_roles')->insert([
                'role_id' => $roleId,
                'model_type' => \App\User::class,
                'model_id' => 1,
            ]);
        }
    }
}
