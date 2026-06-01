<?php

declare(strict_types=1);

use App\Contact;
use App\ContactAddress;
use App\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Jana\Scopes\ScopeByBusiness;
use Spatie\Permission\PermissionRegistrar;

/**
 * US-CRM-078 — múltiplos endereços por contato.
 *
 * Cobertura:
 *   1. Schema — tabela `contact_addresses` + colunas + softDeletes (migration real).
 *   2. Relações — Contact::addresses() hasMany / default+shipping hasOne / contact() belongsTo.
 *   3. Multi-tenant Tier 0 (ADR 0093 IRREVOGÁVEL) — cross-tenant biz=1 vs biz=99:
 *      HasBusinessScope impede user de biz=1 enxergar endereços de biz=99 (query + relação).
 *   4. Backfill idempotente — endereço inline → 1 `contact_addresses` default; re-run não duplica.
 *
 * biz=1 nunca biz=4 cliente (R6 / ADR 0101) — usa businesses sintéticos do teste.
 *
 * Setup: schema mínimo INLINE (sqlite). UPOS tem migrations MySQL-only (MODIFY
 * COLUMN / ENUM) incompatíveis com sqlite, então CI/local não roda migrate full
 * (modules-pest.yml). Padrão de tests/Feature/Domain/Fsm/GateEmissaoPorVendaTest.
 *
 * @see app/ContactAddress.php
 * @see memory/requisitos/Cliente/SPEC.md §US-CRM-078
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 */

const CA078_MIGRATION = 'migrations/2026_06_01_120000_create_contact_addresses_table.php';

beforeEach(function () {
    foreach ([
        'contact_addresses', 'activity_log', 'role_has_permissions', 'model_has_roles',
        'model_has_permissions', 'roles', 'permissions', 'contacts', 'business', 'users',
    ] as $tbl) {
        Schema::dropIfExists($tbl);
    }

    Schema::create('users', function (Blueprint $t) {
        $t->increments('id');
        $t->string('username')->unique();
        $t->string('password');
        $t->integer('business_id')->nullable();
        $t->rememberToken();
        $t->softDeletes();
        $t->timestamps();
    });

    Schema::create('business', function (Blueprint $t) {
        $t->increments('id');
        $t->string('name');
        $t->timestamps();
    });

    // Spatie permission tables — ScopeByBusiness chama $user->can('jana.superadmin').
    Schema::create('permissions', function (Blueprint $t) {
        $t->bigIncrements('id');
        $t->string('name');
        $t->string('guard_name');
        $t->timestamps();
        $t->unique(['name', 'guard_name']);
    });
    Schema::create('roles', function (Blueprint $t) {
        $t->bigIncrements('id');
        $t->string('name');
        $t->string('guard_name');
        $t->timestamps();
        $t->unique(['name', 'guard_name']);
    });
    Schema::create('model_has_permissions', function (Blueprint $t) {
        $t->unsignedBigInteger('permission_id');
        $t->string('model_type');
        $t->unsignedBigInteger('model_id');
        $t->primary(['permission_id', 'model_id', 'model_type'], 'ca078_mhp_pk');
    });
    Schema::create('model_has_roles', function (Blueprint $t) {
        $t->unsignedBigInteger('role_id');
        $t->string('model_type');
        $t->unsignedBigInteger('model_id');
        $t->primary(['role_id', 'model_id', 'model_type'], 'ca078_mhr_pk');
    });
    Schema::create('role_has_permissions', function (Blueprint $t) {
        $t->unsignedBigInteger('permission_id');
        $t->unsignedBigInteger('role_id');
        $t->primary(['permission_id', 'role_id'], 'ca078_rhp_pk');
    });

    // activity_log — Contact usa Spatie LogsActivity; save() insere aqui.
    Schema::create('activity_log', function (Blueprint $t) {
        $t->bigIncrements('id');
        $t->string('log_name')->nullable();
        $t->text('description')->nullable();
        $t->unsignedBigInteger('subject_id')->nullable();
        $t->string('subject_type')->nullable();
        $t->unsignedBigInteger('causer_id')->nullable();
        $t->string('causer_type')->nullable();
        $t->json('properties')->nullable();
        $t->string('event')->nullable();
        $t->uuid('batch_uuid')->nullable();
        $t->timestamps();
    });

    // contacts — subset UPOS suficiente pro ContactAddress + backfill inline.
    Schema::create('contacts', function (Blueprint $t) {
        $t->increments('id');
        $t->unsignedInteger('business_id')->index();
        $t->string('type')->nullable();
        $t->string('name')->nullable();
        $t->string('contact_status')->nullable();
        $t->string('zip_code')->nullable();
        $t->string('address_line_1')->nullable();
        $t->string('numero')->nullable();
        $t->string('address_line_2')->nullable();
        $t->string('neighborhood')->nullable();
        $t->string('city')->nullable();
        $t->string('state')->nullable();
        $t->string('city_code')->nullable();
        $t->softDeletes();
        $t->timestamps();
    });

    // contact_addresses — roda a migration REAL (valida up() em sqlite).
    (require database_path(CA078_MIGRATION))->up();

    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

afterEach(function () {
    foreach ([
        'contact_addresses', 'activity_log', 'role_has_permissions', 'model_has_roles',
        'model_has_permissions', 'roles', 'permissions', 'contacts', 'business', 'users',
    ] as $tbl) {
        Schema::dropIfExists($tbl);
    }
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

// ── helpers ─────────────────────────────────────────────────────────────

function ca078Business(): int
{
    return (int) DB::table('business')->insertGetId([
        'name' => 'Biz '.uniqid(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

function ca078User(int $bizId): User
{
    return User::forceCreate([
        'username' => 'u_'.$bizId.'_'.uniqid(),
        'password' => bcrypt('x'),
        'business_id' => $bizId,
    ]);
}

function ca078Contact(int $bizId, array $overrides = []): Contact
{
    $c = new Contact();
    $c->business_id = $bizId;
    $c->type = $overrides['type'] ?? 'customer';
    $c->name = $overrides['name'] ?? 'Cliente Teste';
    $c->contact_status = 'active';
    foreach ($overrides as $k => $v) {
        $c->{$k} = $v;
    }
    $c->save();

    return $c;
}

function ca078Endereco(int $bizId, int $contactId, array $overrides = []): ContactAddress
{
    $a = new ContactAddress();
    $a->business_id = $bizId;
    $a->contact_id = $contactId;
    $a->fill($overrides);
    $a->save();

    return $a;
}

// ── testes ──────────────────────────────────────────────────────────────

test('tabela e colunas de contact_addresses existem (migration real)', function () {
    expect(Schema::hasTable('contact_addresses'))->toBeTrue();

    foreach ([
        'business_id', 'contact_id', 'label', 'zip_code', 'address_line_1', 'numero',
        'address_line_2', 'neighborhood', 'city', 'state', 'city_code',
        'is_default', 'is_shipping', 'deleted_at',
    ] as $col) {
        expect(Schema::hasColumn('contact_addresses', $col))
            ->toBeTrue("coluna contact_addresses.{$col} ausente");
    }
});

test('relações Contact/ContactAddress estão definidas', function () {
    $contact = new Contact();
    expect($contact->addresses())->toBeInstanceOf(HasMany::class);
    expect($contact->defaultAddress())->toBeInstanceOf(HasOne::class);
    expect($contact->shippingAddress())->toBeInstanceOf(HasOne::class);
    expect((new ContactAddress())->contact())->toBeInstanceOf(BelongsTo::class);
});

test('contato tem muitos endereços com default + shipping', function () {
    $biz = ca078Business();
    $contact = ca078Contact($biz);

    ca078Endereco($biz, $contact->id, [
        'label' => 'Matriz', 'is_default' => true, 'is_shipping' => true,
        'city' => 'Tubarão', 'state' => 'SC',
    ]);
    ca078Endereco($biz, $contact->id, ['label' => 'Obra', 'city' => 'Criciúma', 'state' => 'SC']);

    $this->actingAs(ca078User($biz));
    session(['user.business_id' => $biz]);

    expect($contact->addresses()->count())->toBe(2);
    expect($contact->defaultAddress->label)->toBe('Matriz');
    expect($contact->shippingAddress->label)->toBe('Matriz');
});

test('isolamento cross-tenant biz=1 vs biz=99 via global scope (ADR 0093)', function () {
    $biz1 = ca078Business();
    $biz99 = ca078Business();

    $c1 = ca078Contact($biz1, ['name' => 'Cliente biz1']);
    $c99 = ca078Contact($biz99, ['name' => 'Cliente biz99']);

    $a1 = ca078Endereco($biz1, $c1->id, ['label' => 'Matriz biz1']);
    $a99 = ca078Endereco($biz99, $c99->id, ['label' => 'Matriz biz99']);

    // Autenticado como user de biz1 + sessão multi-tenant.
    $this->actingAs(ca078User($biz1));
    session(['user.business_id' => $biz1]);

    // Scope ativo: só o endereço de biz1 é visível.
    $visible = ContactAddress::all();
    expect($visible)->toHaveCount(1);
    expect($visible->first()->id)->toBe($a1->id);

    // Endereço de biz99 não localizável sob o scope de biz1.
    expect(ContactAddress::find($a99->id))->toBeNull();

    // Nem via relação a partir do contact de biz99.
    expect($c99->addresses()->count())->toBe(0);

    // withoutGlobalScope enxerga os 2 — prova que o filtro é do scope.
    expect(ContactAddress::withoutGlobalScope(ScopeByBusiness::class)->count())->toBe(2);
});

test('backfill inline é idempotente e preserva business_id', function () {
    $biz = ca078Business();

    // Contact COM endereço inline → gera 1 contact_addresses default/shipping.
    $withAddr = ca078Contact($biz, [
        'name' => 'Com endereço',
        'zip_code' => '88701-000',
        'address_line_1' => 'Av Marcolino Martins Cabral',
        'numero' => '1578',
        'city' => 'Tubarão',
        'state' => 'SC',
    ]);

    // Contact SEM endereço inline → não gera nada.
    ca078Contact($biz, ['name' => 'Sem endereço']);

    $inserted = ContactAddress::backfillInline($biz);
    expect($inserted)->toBe(1);

    $addr = ContactAddress::withoutGlobalScope(ScopeByBusiness::class)
        ->where('contact_id', $withAddr->id)->first();
    expect($addr)->not->toBeNull();
    expect($addr->is_default)->toBeTrue();
    expect($addr->is_shipping)->toBeTrue();
    expect($addr->label)->toBe('Principal');
    expect($addr->numero)->toBe('1578');
    expect((int) $addr->business_id)->toBe($biz);

    // Re-run: idempotente — não duplica.
    expect(ContactAddress::backfillInline($biz))->toBe(0);
    expect(
        ContactAddress::withoutGlobalScope(ScopeByBusiness::class)
            ->where('contact_id', $withAddr->id)->count()
    )->toBe(1);
});
