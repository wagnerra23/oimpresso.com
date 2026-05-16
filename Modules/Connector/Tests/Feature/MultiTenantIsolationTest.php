<?php

declare(strict_types=1);

use App\Contact;
use App\Product;
use App\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(Tests\TestCase::class);

/**
 * Isolamento multi-tenant Tier 0 IRREVOGÁVEL ([ADR 0093]) na API Connector.
 *
 * Connector é o ÚNICO ponto onde um cliente externo (Delphi/Woo/SaaS) acessa
 * dados de negócio via token Passport. Vazar dados entre tenants aqui é o
 * pior bug possível — exposição cruzada de Contacts/Products/Transactions
 * entre clientes pagos.
 *
 * Regra: token Passport carrega `business_id` via `user.business_id`.
 * Toda query DEVE respeitar global scope.
 *
 * NUNCA usar biz=4 (ROTA LIVRE — cliente Larissa produção) — conforme ADR 0101.
 * Tests usam biz=1 (Wagner WR2) e biz=99 (fictício isolamento).
 *
 * Estratégia: insere dado em biz=1 via withoutGlobalScopes; simula sessão
 * biz=99; valida que query SEM withoutGlobalScopes NÃO retorna o registro.
 *
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 * @see memory/decisions/0101-tests-business-id-1-nunca-cliente.md
 */

const BIZ_WAGNER = 1;
const BIZ_FICTICIO = 99;

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: Connector + UltimatePOS Models requerem schema MySQL (ADR 0101)');
    }
    if (! Schema::hasTable('contacts') || ! Schema::hasTable('products') || ! Schema::hasTable('business')) {
        $this->markTestSkipped('Schema UltimatePOS incompleto — rode migrate primeiro');
    }
});

/**
 * Helper: simula sessão authenticada de um business sem precisar de Passport real.
 * Global scope `BusinessIdScope` de UltimatePOS lê `session('user.business_id')`.
 */
function setConnectorBizSession(int $businessId): void
{
    session(['user.business_id' => $businessId]);
}

// ------------------------------------------------------------------
// Contact (App\Contact) — endpoint /connector/api/contactapi
// ------------------------------------------------------------------

it('Contact biz=1 NÃO aparece quando session é biz=99 (Connector contactapi)', function () {
    // Insere contato cru no biz=1 (SUPERADMIN: bypass scope só pra setup do test).
    $contactId = DB::table('contacts')->insertGetId([ // SUPERADMIN: seed direto pra isolar test do scope
        'business_id'  => BIZ_WAGNER,
        'type'         => 'customer',
        'name'         => 'CONNECTOR-ISOLATION-TEST-' . uniqid(),
        'contact_id'   => 'CT-ISO-' . uniqid(),
        'created_at'   => now(),
        'updated_at'   => now(),
    ]);

    try {
        // Simula token Passport de user no biz=99
        setConnectorBizSession(BIZ_FICTICIO);

        // Query SEM withoutGlobalScopes — global scope DEVE filtrar
        $resultado = Contact::where('id', $contactId)->get();

        expect($resultado)->toHaveCount(0, 'VAZAMENTO TIER 0: Contact biz=1 visível com session biz=99!');
    } finally {
        DB::table('contacts')->where('id', $contactId)->delete(); // SUPERADMIN: cleanup
    }
});

it('Contact biz=1 aparece quando session é biz=1 (controle positivo)', function () {
    $contactId = DB::table('contacts')->insertGetId([ // SUPERADMIN: seed direto
        'business_id'  => BIZ_WAGNER,
        'type'         => 'customer',
        'name'         => 'CONNECTOR-ISO-POS-' . uniqid(),
        'contact_id'   => 'CT-POS-' . uniqid(),
        'created_at'   => now(),
        'updated_at'   => now(),
    ]);

    try {
        setConnectorBizSession(BIZ_WAGNER);

        $resultado = Contact::where('id', $contactId)->get();
        expect($resultado)->toHaveCount(1, 'Contact biz=1 não aparece com session biz=1 — scope quebrado!');
    } finally {
        DB::table('contacts')->where('id', $contactId)->delete(); // SUPERADMIN: cleanup
    }
});

// ------------------------------------------------------------------
// Product (App\Product) — endpoint /connector/api/product
// ------------------------------------------------------------------

it('Product biz=1 NÃO aparece quando session é biz=99 (Connector product)', function () {
    $productId = DB::table('products')->insertGetId([ // SUPERADMIN: seed direto
        'business_id'   => BIZ_WAGNER,
        'name'          => 'CONNECTOR-PRD-ISO-' . uniqid(),
        'type'          => 'single',
        'unit_id'       => 1,
        'sku'           => 'SKU-ISO-' . uniqid(),
        'enable_stock'  => 0,
        'created_at'    => now(),
        'updated_at'    => now(),
    ]);

    try {
        setConnectorBizSession(BIZ_FICTICIO);

        $resultado = Product::where('id', $productId)->get();

        expect($resultado)->toHaveCount(0, 'VAZAMENTO TIER 0: Product biz=1 visível com session biz=99!');
    } finally {
        DB::table('products')->where('id', $productId)->delete(); // SUPERADMIN: cleanup
    }
});

it('Product biz=1 aparece quando session é biz=1 (controle positivo)', function () {
    $productId = DB::table('products')->insertGetId([ // SUPERADMIN: seed direto
        'business_id'   => BIZ_WAGNER,
        'name'          => 'CONNECTOR-PRD-POS-' . uniqid(),
        'type'          => 'single',
        'unit_id'       => 1,
        'sku'           => 'SKU-POS-' . uniqid(),
        'enable_stock'  => 0,
        'created_at'    => now(),
        'updated_at'    => now(),
    ]);

    try {
        setConnectorBizSession(BIZ_WAGNER);

        $resultado = Product::where('id', $productId)->get();
        expect($resultado)->toHaveCount(1, 'Product biz=1 não aparece com session biz=1 — scope quebrado!');
    } finally {
        DB::table('products')->where('id', $productId)->delete(); // SUPERADMIN: cleanup
    }
});

// ------------------------------------------------------------------
// Transaction (sells/expense) — endpoint /connector/api/sell + /expense
// ------------------------------------------------------------------

it('Transaction biz=1 (venda) NÃO aparece quando session é biz=99', function () {
    if (! Schema::hasTable('transactions')) {
        $this->markTestSkipped('transactions table missing — rode UltimatePOS migrate');
    }

    $transactionId = DB::table('transactions')->insertGetId([ // SUPERADMIN: seed direto
        'business_id'   => BIZ_WAGNER,
        'location_id'   => 1,
        'type'          => 'sell',
        'status'        => 'final',
        'payment_status'=> 'due',
        'invoice_no'    => 'INV-CONN-ISO-' . substr(uniqid(), -8),
        'transaction_date' => now(),
        'total_before_tax' => 100.00,
        'final_total'   => 100.00,
        'created_at'    => now(),
        'updated_at'    => now(),
    ]);

    try {
        setConnectorBizSession(BIZ_FICTICIO);

        // Query direta — sem global scope explícito, mas Transaction model deve ter o scope.
        $resultado = \App\Transaction::where('id', $transactionId)->get();

        expect($resultado)->toHaveCount(0, 'VAZAMENTO TIER 0: Transaction biz=1 visível com session biz=99!');
    } finally {
        DB::table('transactions')->where('id', $transactionId)->delete(); // SUPERADMIN: cleanup
    }
});

// ------------------------------------------------------------------
// User (App\User) — endpoint /connector/api/user
// ------------------------------------------------------------------

it('User biz=1 NÃO aparece quando session é biz=99 (Connector user listing)', function () {
    $userId = DB::table('users')->insertGetId([ // SUPERADMIN: seed direto
        'business_id'   => BIZ_WAGNER,
        'username'      => 'conn-iso-' . substr(uniqid(), -10),
        'password'      => bcrypt('x'),
        'first_name'    => 'IsolationTest',
        'surname'       => 'Connector',
        'language'      => 'pt_BR',
        'created_at'    => now(),
        'updated_at'    => now(),
    ]);

    try {
        setConnectorBizSession(BIZ_FICTICIO);

        // User model tem global scope business_id em UltimatePOS
        $resultado = User::where('id', $userId)->get();

        expect($resultado)->toHaveCount(0, 'VAZAMENTO TIER 0: User biz=1 visível com session biz=99!');
    } finally {
        DB::table('users')->where('id', $userId)->delete(); // SUPERADMIN: cleanup
    }
});
