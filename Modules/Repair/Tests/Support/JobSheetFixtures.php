<?php

declare(strict_types=1);

namespace Modules\Repair\Tests\Support;

use App\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;

/**
 * Fixtures dos contratos de OS (JobSheet) — Create · Edit · Show · AddParts.
 *
 * POR QUE UMA CLASSE, E NÃO FUNÇÕES SOLTAS NO ARQUIVO DE TESTE
 * ------------------------------------------------------------
 * Os quatro contratos precisam do mesmo arranjo (tenant + user + cliente + status +
 * OS). Repetir funções globais em quatro arquivos Pest da MESMA suíte colide
 * ("cannot redeclare"), e copiá-las com nomes diferentes faria quatro cópias drifarem
 * — a doença que este módulo já tem nos `Wave3B6*`.
 *
 * Mora em `Tests/Support/` (e não em `Tests/Feature/Support/`) de propósito: o
 * phpunit.xml varre `./Modules/Repair/Tests/Feature` recursivamente, e um arquivo sem
 * classe de teste lá dentro vira warning a cada run.
 *
 * TENANT — biz=98 fictício (ADR 0358), NUNCA biz=4 (cliente real) nem biz=1 (empresa
 * real; no CT 100 a base é clone de produção e não se limpa entre runs). Fabricar
 * fixture AQUI é livre porque 98 é fictício por construção — a proibição de 2026-08-24
 * é sobre plantar dado no tenant tratado como real.
 *
 * NÃO SEMEIA PRÉ-CONDIÇÃO GLOBAL: nada aqui escreve em `system`, em permissão de outro
 * tenant ou em `business` alheio. Quem semeia o ambiente é o seed
 * (.github/actions/pest-mysql-setup · scripts/tests/ct100-fullsuite.sh).
 *
 * @see memory/decisions/0358-doutrina-de-teste-tenant-98-supersede-0101.md
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 */
final class JobSheetFixtures
{
    /**
     * Guard de ambiente comum aos quatro contratos.
     *
     * O schema UltimatePOS de `business`/`contacts`/`repair_job_sheets` é MySQL-only
     * (ENUM, MODIFY COLUMN), e a lane `modules-pest.yml` roda em sqlite `:memory:` SEM
     * migrate. Sem este guard os quatro arquivos morreriam com erro de driver em vez de
     * pular — e erro de driver na lane esconde o que interessa.
     *
     * @return string|null motivo do skip, ou null quando o ambiente serve
     */
    public static function motivoDeSkip(): ?string
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            return 'SQLite-incompatível: o schema UltimatePOS exige MySQL (ADR 0358). '
                .'A prova destes UCs sai do CT 100, não desta lane.';
        }

        foreach (['business', 'users', 'permissions', 'contacts', 'repair_job_sheets', 'repair_statuses'] as $tabela) {
            if (! Schema::hasTable($tabela)) {
                return "Schema incompleto — tabela {$tabela} ausente; rode migrate + seed mínimo.";
            }
        }

        return null;
    }

    /** Usuário do tenant dado. `superadmin` satisfaz o primeiro ramo do gate dos controllers. */
    public static function usuario(int $businessId, bool $superadmin = true): User
    {
        $user = User::factory()->create([
            'business_id' => $businessId,
            'username' => 'js_'.uniqid(),
        ]);

        if ($superadmin) {
            $user->givePermissionTo(Permission::firstOrCreate(['name' => 'superadmin', 'guard_name' => 'web']));
        }

        return $user;
    }

    /**
     * Sessão mínima que o layout global e os controllers exigem.
     *
     * `resources/views/layouts/app.blade.php` lê `session('currency')['code']` sem
     * coalescência: com a sessão nua do `actingAs`, o render do caminho Blade estoura
     * "Trying to access array offset on null" e a rota devolve 500 — defeito do layout
     * legado em teste, não da tela. Espelha `repairSettingsSessao()` do vizinho
     * RepairSettingsContratoTest, que já renderiza Blade nesta mesma lane.
     */
    public static function sessao(int $businessId, int $userId): void
    {
        session([
            'user.business_id' => $businessId,
            'user.id' => $userId,
            'business.id' => $businessId,
            'business.currency_symbol_placement' => 'before',
            'currency' => ['code' => 'BRL', 'symbol' => 'R$', 'thousand_separator' => '.', 'decimal_separator' => ','],
        ]);
    }

    /** Cliente do tenant. `mobile` e `type` são NOT NULL sem default no schema real. */
    public static function cliente(int $businessId, int $criadoPor, string $nome = 'Cliente Contrato'): int
    {
        return (int) DB::table('contacts')->insertGetId([
            'business_id' => $businessId,
            'type' => 'customer',
            'name' => $nome,
            'mobile' => '',
            'created_by' => $criadoPor,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** Status legado da OS (RepairStatus) do tenant. */
    public static function status(int $businessId, string $nome = 'Recebido'): int
    {
        return (int) DB::table('repair_statuses')->insertGetId([
            'business_id' => $businessId,
            'name' => $nome,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * OS gravada direto no banco (sem passar pelo controller).
     *
     * De propósito: o arranjo de um contrato de `show`/`update`/`saveParts` não pode
     * depender de o `store` estar correto — senão o teste mede dois contratos de uma
     * vez e o vermelho não diz qual dos dois quebrou.
     */
    public static function os(int $businessId, int $contatoId, int $statusId, int $criadoPor, array $extra = []): int
    {
        return (int) DB::table('repair_job_sheets')->insertGetId(array_merge([
            'business_id' => $businessId,
            'contact_id' => $contatoId,
            'job_sheet_no' => 'OS-CT-'.uniqid(),
            'service_type' => 'carry_in',
            'serial_no' => '',
            'status_id' => $statusId,
            'created_by' => $criadoPor,
            'created_at' => now(),
            'updated_at' => now(),
        ], $extra));
    }

    /**
     * Peça vendável (produto + variação) do tenant, devolvendo o `variation_id`.
     *
     * É o eixo do UC de isolamento das peças: o JSON `parts` da OS guarda `variation_id`
     * cru, e `variations` não tem `business_id` (o dono do tenant é `products`). Para
     * provar — ou refutar — o isolamento é preciso uma variação de CADA tenant.
     */
    public static function peca(int $businessId, int $criadoPor, string $nome = 'Peca Contrato'): int
    {
        $unidadeId = (int) DB::table('units')->insertGetId([
            'business_id' => $businessId,
            'actual_name' => 'Unidade',
            'short_name' => 'un',
            'allow_decimal' => 0,
            'created_by' => $criadoPor,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $produtoId = (int) DB::table('products')->insertGetId([
            'name' => $nome,
            'business_id' => $businessId,
            'unit_id' => $unidadeId,
            'type' => 'single',
            'tax_type' => 'inclusive',
            'sku' => 'SKU-'.uniqid(),
            'barcode_type' => 'C128',
            'enable_stock' => 1,
            'created_by' => $criadoPor,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $variacaoTemplateId = (int) DB::table('product_variations')->insertGetId([
            'name' => 'DUMMY',
            'product_id' => $produtoId,
            'is_dummy' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (int) DB::table('variations')->insertGetId([
            'name' => 'DUMMY',
            'product_id' => $produtoId,
            'product_variation_id' => $variacaoTemplateId,
            'sub_sku' => 'SUB-'.uniqid(),
            'default_purchase_price' => 10,
            'dpp_inc_tax' => 10,
            'profit_percent' => 0,
            'default_sell_price' => 20,
            'sell_price_inc_tax' => 20,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
