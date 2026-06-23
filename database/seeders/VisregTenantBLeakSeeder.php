<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Seeder do TENANT-B SENTINELA pro gate de isolamento multi-tenant no RENDER (L3 / Tier 0).
 *
 * POR QUE EXISTE — o buraco que fecha:
 *   Hoje o isolamento `business_id` (princípio #1 IRREVOGÁVEL, ADR 0093) é asserido só no
 *   Model/SQL (testes Feature com biz=1 vs biz=99). NUNCA no RENDER do browser. O
 *   VisregTenantSeeder é MONO-TENANT (só biz=1) → é impossível provar não-vazamento numa
 *   tela carregada: sem um segundo tenant com dado RECONHECÍVEL, `assertDontSee` não tem o
 *   que procurar. Este seeder adiciona o adversário (business 99) ao mesmo schema-squash,
 *   com um TOKEN SENTINELA único `ZZLEAK99` em campos visíveis — assim o Tier0RenderIsolation
 *   pode visitar a tela como admin do biz=1 e provar que `ZZLEAK99` NÃO aparece.
 *
 * PRÉ-CONDIÇÃO DO TOKEN: `ZZLEAK99` é uma string que JAMAIS pode existir em dado real
 *   (não é CPF/CNPJ/telefone/nome plausível). Se um dia colidir com dado de produção, a
 *   premissa do gate quebra — por isso o sufixo 99 + prefixo ZZLEAK improvável.
 *
 * CONVENÇÃO biz (ADR 0101): biz=1 = self canônico (Wagner/WR2), biz=99 = adversário fictício
 *   "improvável de existir como tenant real". NUNCA biz=4 (cert fiscal da Larissa — risco de
 *   smoke real). Este seeder é o LADO 99 do contraste 1↔99.
 *
 * COMO ESPELHA o VisregTenantSeeder (mesmo idioma):
 *   - FK_CHECKS off no bootstrap (business.owner_id ↔ users.business_id circular)
 *   - inserts diretos (DB::table), só colunas existentes + as NOT-NULL-sem-default
 *   - IDEMPOTENTE: re-rodar é no-op (browser tests NÃO usam RefreshDatabase — Pest.php)
 *   - role spatie `Admin#99` (guard web) via insert direto em roles+model_has_roles
 *     (controla roles.business_id NOT NULL + morph model_type = App\User sem morphMap)
 *
 * Por que role `Admin#99` basta (sem enumerar permissões): o Gate::before em
 * AuthServiceProvider concede TODAS as abilities a quem `hasRole('Admin#'.$business_id)`.
 *
 * @see database/seeders/VisregTenantSeeder.php (padrão espelhado — biz=1 self)
 * @see tests/Browser/CoreScreens/Tier0RenderIsolationTest.php (o gate que consome este seed)
 * @see .github/workflows/visual-regression.yml (roda este seeder + o VisregTenantSeeder)
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md (princípio #1 IRREVOGÁVEL)
 * @see memory/decisions/0101-tests-business-id-1-nunca-cliente.md (biz=1 self, 99 adversário)
 */
class VisregTenantBLeakSeeder extends Seeder
{
    /** Token sentinela único — JAMAIS em dado real (pré-condição do gate). */
    public const LEAK_TOKEN = 'ZZLEAK99';

    /** Business adversário (ADR 0101 — 99 improvável de ser tenant real). */
    public const BIZ_B = 99;

    /** User admin do biz=99. id alto pra não colidir com o admin id=1 do VisregTenantSeeder. */
    public const USER_B = 99;

    public function run(): void
    {
        // Idempotente: se o business 99 já existe, garante só role + dado sentinela e sai.
        if (DB::table('business')->where('id', self::BIZ_B)->exists()) {
            $this->ensureAdminRole();
            $this->ensureLeakData();

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

        // FK circular business.owner_id → users.id e users.business_id → business.id:
        // mesma saída do VisregTenantSeeder — desliga a checagem durante o bootstrap.
        DB::statement('SET FOREIGN_KEY_CHECKS = 0');

        try {
            DB::table('business')->insert([
                'id' => self::BIZ_B,
                'name' => 'Tenant-B Sentinela ' . self::LEAK_TOKEN,
                'currency_id' => $currencyId,
                'owner_id' => self::USER_B,
                'time_zone' => 'America/Sao_Paulo',
            ]);

            DB::table('users')->insert([
                'id' => self::USER_B,
                'user_type' => 'user',
                'surname' => 'Sr.',
                'first_name' => 'AdminB',
                'last_name' => 'Sentinela',
                'username' => 'visreg_admin_b',
                'email' => 'visreg-admin-b@example.test',
                'password' => Hash::make('visreg-secret-not-for-prod'),
                'language' => 'pt',
                'business_id' => self::BIZ_B,
                'status' => 'active',
                'allow_login' => 1,
            ]);

            DB::table('invoice_schemes')->insert([
                'id' => self::BIZ_B, 'business_id' => self::BIZ_B, 'name' => 'Default', 'scheme_type' => 'blank',
            ]);

            DB::table('invoice_layouts')->insert([
                'id' => self::BIZ_B, 'business_id' => self::BIZ_B, 'name' => 'Default',
            ]);

            DB::table('business_locations')->insert([
                'id' => self::BIZ_B,
                'business_id' => self::BIZ_B,
                'name' => 'Matriz-B',
                'country' => 'Brasil',
                'state' => 'SP',
                'city' => 'Sao Paulo',
                'zip_code' => '9900000',
                'invoice_scheme_id' => self::BIZ_B,
                'invoice_layout_id' => self::BIZ_B,
            ]);
        } finally {
            DB::statement('SET FOREIGN_KEY_CHECKS = 1');
        }

        $this->ensureAdminRole();
        $this->ensureLeakData();
    }

    /**
     * Dado SENTINELA visível do biz=99 — o que o gate vai procurar (e NÃO deve achar
     * logado como biz=1). O token `ZZLEAK99` vai em CAMPOS QUE RENDERIZAM:
     *   - contacts.name  → coluna do nome na listagem /cliente + /sells/seletor de cliente
     *   - contacts.tax_number / cpf_cnpj → colunas fiscais visíveis no cadastro/lista
     *   - contacts.mobile → coluna de contato visível
     *   - transactions.invoice_no → nº de documento visível em /sells, /compras, /financeiro
     *   - transactions.final_total = 999999.99 → valor-sentinela (improvável em dado real)
     *
     * IDEMPOTENTE pelo contact_id 'COZZ99' e invoice_no 'INV-ZZLEAK99'.
     */
    private function ensureLeakData(): void
    {
        $adminB = self::USER_B;

        $contactId = DB::table('contacts')
            ->where('business_id', self::BIZ_B)
            ->where('contact_id', 'COZZ99')
            ->value('id');

        if (! $contactId) {
            $contactId = DB::table('contacts')->insertGetId([
                'business_id' => self::BIZ_B,
                'type' => 'customer',
                'is_customer' => 1,
                'name' => 'Cliente ' . self::LEAK_TOKEN,
                'contact_id' => 'COZZ99',
                // Campos fiscais visíveis: token sentinela onde o CPF/CNPJ apareceria.
                'tax_number' => self::LEAK_TOKEN,
                'cpf_cnpj' => self::LEAK_TOKEN,
                'mobile' => self::LEAK_TOKEN,
                'created_by' => $adminB,
                'is_default' => 0,
            ]);
        }

        // Transação (venda) do biz=99 com nº de documento + valor sentinela — é o dado que
        // /sells, /compras e /financeiro/unificado renderizam por business_id.
        $hasTx = DB::table('transactions')
            ->where('business_id', self::BIZ_B)
            ->where('invoice_no', 'INV-' . self::LEAK_TOKEN)
            ->exists();

        if (! $hasTx) {
            DB::table('transactions')->insert([
                'business_id' => self::BIZ_B,
                'location_id' => self::BIZ_B,
                'type' => 'sell',
                'status' => 'final',
                'payment_status' => 'due',
                'contact_id' => $contactId,
                'invoice_no' => 'INV-' . self::LEAK_TOKEN,
                'ref_no' => self::LEAK_TOKEN,
                'transaction_date' => now()->toDateTimeString(),
                'total_before_tax' => 999999.99,
                'final_total' => 999999.99, // valor-sentinela improvável em dado real
                'created_by' => $adminB,
                // essentials_duration é NOT NULL sem default no schema UPOS — explícito por
                // segurança (mesmo com strict=false no gate, evita depender do implícito).
                'essentials_duration' => 0,
            ]);
        }
    }

    /**
     * Role spatie `Admin#99` (guard web) + vínculo com o admin do biz=99 (model_has_roles).
     * Insert direto (sem o model spatie) — espelha VisregTenantSeeder::ensureAdminRole()
     * pra controlar `roles.business_id` (NOT NULL) e o morph `model_type` = App\User.
     */
    private function ensureAdminRole(): void
    {
        $roleName = 'Admin#' . self::BIZ_B;

        $roleId = DB::table('roles')->where('name', $roleName)->where('guard_name', 'web')->value('id');
        if (! $roleId) {
            $roleId = DB::table('roles')->insertGetId([
                'name' => $roleName,
                'guard_name' => 'web',
                'business_id' => self::BIZ_B,
            ]);
        }

        $linked = DB::table('model_has_roles')
            ->where('role_id', $roleId)
            ->where('model_type', \App\User::class)
            ->where('model_id', self::USER_B)
            ->exists();

        if (! $linked) {
            DB::table('model_has_roles')->insert([
                'role_id' => $roleId,
                'model_type' => \App\User::class,
                'model_id' => self::USER_B,
            ]);
        }
    }
}
