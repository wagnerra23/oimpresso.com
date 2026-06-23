<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Seeder do TENANT-VAZIO pro gate L2 de ESTADOS ISOLADOS (snapshot do estado `empty`).
 *
 * POR QUE EXISTE — o buraco que fecha:
 *   O VisregTenantSeeder (biz=1) traz scaffold + 1 produto + Walk-In, mas ZERO transações/
 *   compras/OS. Já é "quase vazio" pra varias listas — MAS isso é frágil: se um dia o biz=1
 *   ganhar dado de seed (ou outro gate semear nele), o snapshot `default` muda e o estado
 *   vazio deixa de ser coberto silenciosamente. Este seeder fixa um tenant que é vazio POR
 *   CONSTRUÇÃO (scaffold minimo bootavel, NENHUM dado de dominio) → o snapshot `empty` das
 *   telas-lista (Sells/Index, Clientes, Compras, Financeiro, Oficina) é estável e
 *   independente da evolução do seed do biz=1.
 *
 * O QUE SEEDA (so o minimo pro SetSessionData/IsInstalled bootar a tela — nada listavel):
 *   - currency (reusa a 1ª; fallback BRL) + business 98 + business_location 98
 *   - invoice_scheme 98 + invoice_layout 98
 *   - admin (user id=98, business_id=98) com role spatie `Admin#98`
 *   - NENHUM contact / product / transaction → as listas caem no empty-state REAL.
 *
 * Por que role `Admin#98` basta (sem enumerar permissões): o Gate::before em
 * AuthServiceProvider concede TODAS as abilities a quem `hasRole('Admin#'.$business_id)`.
 *
 * CONVENÇÃO biz (ADR 0101): biz=1 = self canônico, biz=99 = adversário sentinela (leak),
 *   biz=98 = tenant-vazio (este). NUNCA biz=4 (cert fiscal da Larissa — risco de smoke real).
 *
 * IDEMPOTENTE: re-rodar é no-op (browser tests NÃO usam RefreshDatabase — Pest.php). Espelha
 *   o idioma do VisregTenantSeeder/VisregTenantBLeakSeeder (FK_CHECKS off no bootstrap,
 *   inserts diretos só com colunas existentes + NOT-NULL-sem-default, role via insert direto).
 *
 * @see database/seeders/VisregTenantSeeder.php (padrão espelhado — biz=1 self)
 * @see database/seeders/VisregTenantBLeakSeeder.php (biz=99 adversário — mesmo idioma)
 * @see tests/Browser/CoreScreens/IsolatedStatesBaselineTest.php (o gate que consome este seed)
 * @see .github/workflows/visual-regression.yml (roda este seeder junto dos demais)
 * @see memory/decisions/0101-tests-business-id-1-nunca-cliente.md (convenção biz)
 */
class VisregEmptyTenantSeeder extends Seeder
{
    /** Business vazio por construção (ADR 0101 — improvável de ser tenant real). */
    public const BIZ_EMPTY = 98;

    /** User admin do biz=98. id alto pra não colidir com admin id=1 nem com o id=99 do leak. */
    public const USER_EMPTY = 98;

    public function run(): void
    {
        // Idempotente: se o business 98 já existe, garante só a role e sai.
        if (DB::table('business')->where('id', self::BIZ_EMPTY)->exists()) {
            $this->ensureAdminRole();

            return;
        }

        // currencies já vem do VisregTenantSeeder (ou CurrenciesTableSeeder). Reusa a 1ª;
        // fallback BRL só por defesa (este seeder roda DEPOIS do VisregTenantSeeder no CI).
        $currencyId = DB::table('currencies')->min('id');
        if (! $currencyId) {
            $currencyId = DB::table('currencies')->insertGetId([
                'country' => 'Brasil', 'currency' => 'Real', 'code' => 'BRL',
                'symbol' => 'R$', 'thousand_separator' => '.', 'decimal_separator' => ',',
            ]);
        }

        // FK circular business.owner_id ↔ users.business_id: mesma saída dos outros seeders.
        DB::statement('SET FOREIGN_KEY_CHECKS = 0');

        try {
            DB::table('business')->insert([
                'id' => self::BIZ_EMPTY,
                'name' => 'Tenant Vazio (visreg estado empty)',
                'currency_id' => $currencyId,
                'owner_id' => self::USER_EMPTY,
                'time_zone' => 'America/Sao_Paulo',
            ]);

            DB::table('users')->insert([
                'id' => self::USER_EMPTY,
                'user_type' => 'user',
                'surname' => 'Sr.',
                'first_name' => 'Admin',
                'last_name' => 'Vazio',
                'username' => 'visreg_admin_empty',
                'email' => 'visreg-admin-empty@example.test',
                'password' => Hash::make('visreg-secret-not-for-prod'),
                'language' => 'pt',
                'business_id' => self::BIZ_EMPTY,
                'status' => 'active',
                'allow_login' => 1,
            ]);

            DB::table('invoice_schemes')->insert([
                'id' => self::BIZ_EMPTY, 'business_id' => self::BIZ_EMPTY, 'name' => 'Default', 'scheme_type' => 'blank',
            ]);

            DB::table('invoice_layouts')->insert([
                'id' => self::BIZ_EMPTY, 'business_id' => self::BIZ_EMPTY, 'name' => 'Default',
            ]);

            DB::table('business_locations')->insert([
                'id' => self::BIZ_EMPTY,
                'business_id' => self::BIZ_EMPTY,
                'name' => 'Matriz-Vazia',
                'country' => 'Brasil',
                'state' => 'SP',
                'city' => 'Sao Paulo',
                'zip_code' => '9800000',
                'invoice_scheme_id' => self::BIZ_EMPTY,
                'invoice_layout_id' => self::BIZ_EMPTY,
            ]);
        } finally {
            DB::statement('SET FOREIGN_KEY_CHECKS = 1');
        }

        $this->ensureAdminRole();
    }

    /**
     * Role spatie `Admin#98` (guard web) + vínculo com o admin do biz=98 (model_has_roles).
     * Insert direto (sem o model spatie) — espelha VisregTenantSeeder::ensureAdminRole()
     * pra controlar `roles.business_id` (NOT NULL) e o morph `model_type` = App\User.
     */
    private function ensureAdminRole(): void
    {
        $roleName = 'Admin#' . self::BIZ_EMPTY;

        $roleId = DB::table('roles')->where('name', $roleName)->where('guard_name', 'web')->value('id');
        if (! $roleId) {
            $roleId = DB::table('roles')->insertGetId([
                'name' => $roleName,
                'guard_name' => 'web',
                'business_id' => self::BIZ_EMPTY,
            ]);
        }

        $linked = DB::table('model_has_roles')
            ->where('role_id', $roleId)
            ->where('model_type', \App\User::class)
            ->where('model_id', self::USER_EMPTY)
            ->exists();

        if (! $linked) {
            DB::table('model_has_roles')->insert([
                'role_id' => $roleId,
                'model_type' => \App\User::class,
                'model_id' => self::USER_EMPTY,
            ]);
        }
    }
}
