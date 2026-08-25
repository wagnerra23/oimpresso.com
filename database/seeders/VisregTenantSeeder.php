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
 * ⚠️ PONTO CEGO DECLARADO (medido 2026-08-11) — o que as baselines NÃO fotografam:
 * "todas as abilities" vale pra PERMISSÃO (camada 3), e NÃO instala MÓDULO (camada 1).
 * Este seeder tem ZERO ocorrência de package_details/subscription/enabled_modules, e o
 * `Gate::before` só concede `superadmin` por USERNAME (config/constants.php →
 * ADMINISTRATOR_USERNAMES, ausente no workflow) — o `visreg_admin` não é superadmin.
 * Logo, em 25 módulos o `DataController` cai em `hasThePermissionInSubscription(...)`
 * = false e NUNCA injeta a entrada no `shell.menu`. Consequência medida:
 *   - a SUB-NAV de área some (ex.: `JanaSubNav` faz `return null` sem ghosts) —
 *     10 das 15 telas do manifesto visreg;
 *   - a SIDEBAR sai sem as entradas desses módulos — 15 de 15.
 * A página ABRE (permissão comum basta) e o pixel-diff é estável, então isto NÃO é
 * falso-verde: é BURACO DE COBERTURA. O gate protege o miolo da Page e não vê o chrome
 * derivado de `shell.menu`. Um PR que quebre as abas passa verde.
 *
 * NÃO semeie assinaturas "pra consertar" sem decidir o rebaseline: ligar módulos muda as
 * 15 baselines DE UMA VEZ, e rebaseline em massa é exatamente quando regressão real entra
 * sem ser vista. Se for feito, uma tela por PR, com o diff olhado. Decisão [W].
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
        // Tier 0 · repo PUBLICO: este seeder cria login com senha CONHECIDA no codigo-fonte.
        // Rodar em producao publicaria uma porta de entrada (a senha esta no GitHub, aberta).
        // CI usa APP_ENV=testing e o CT100 usa staging — ambos passam. So producao aborta.
        if (app()->isProduction()) {
            throw new RuntimeException(static::class . ': seeder de fixture com senha publica NAO roda em producao (APP_ENV=production).');
        }

        if (DB::table('business')->where('id', 1)->exists()) {
            $this->ensureAdminRole();
            $this->ensureManageModulesPermission();
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
        $this->ensureManageModulesPermission();
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
    /**
     * Permissao spatie `manage_modules` (guard web) + vinculo direto com o admin.
     *
     * POR QUE NAO BASTA A ROLE `Admin#1`: o `Gate::before` do AuthServiceProvider trata
     * `backup`, `superadmin` e `manage_modules` como abilities de SUPERADMIN — o atalho
     * "tem Admin#<biz> => pode tudo" esta no `else` e NAO se aplica a elas. Para essas tres
     * o Gate so concede por USERNAME (`ADMINISTRATOR_USERNAMES`, ausente no workflow — o
     * ponto cego que o docblock do topo ja declarava). Sem esta concessao a tela `/modulos`
     * responde 403 no harness e nao pode ter baseline de pixel.
     *
     * POR QUE PERMISSAO E NAO A ENV: setar `ADMINISTRATOR_USERNAMES=visreg_admin` tambem
     * concederia `superadmin` e `backup`, o que muda o que OUTRAS telas do manifesto
     * renderizam — e mexeria em baselines que este PR nao toca. A permissao e o corte
     * estreito: da exatamente a ability da tela onboardada.
     *
     * Insert direto (sem o model spatie) pra manter o estilo do `ensureAdminRole` e o
     * morph `model_type` = App\User (sem morphMap no projeto).
     */
    private function ensureManageModulesPermission(): void
    {
        $this->ensurePermission('manage_modules');
        // `backup` — onboarda /backup no gate visual. Sem ela a rota devolve 403 e a
        // baseline nunca nasce (mesmo sintoma medido no /superadmin em 2026-08-19).
        $this->ensurePermission('backup');
    }

    /** Cria a permission se faltar e liga ao visreg_admin (model_id 1). */
    private function ensurePermission(string $nome): void
    {
        $permId = DB::table('permissions')
            ->where('name', $nome)->where('guard_name', 'web')->value('id');

        if (! $permId) {
            $permId = DB::table('permissions')->insertGetId([
                'name' => $nome,
                'guard_name' => 'web',
            ]);
        }

        $linked = DB::table('model_has_permissions')
            ->where('permission_id', $permId)
            ->where('model_type', \App\User::class)
            ->where('model_id', 1)
            ->exists();

        if (! $linked) {
            DB::table('model_has_permissions')->insert([
                'permission_id' => $permId,
                'model_type' => \App\User::class,
                'model_id' => 1,
            ]);
        }
    }
}
