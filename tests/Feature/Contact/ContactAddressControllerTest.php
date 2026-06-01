<?php

declare(strict_types=1);

use App\Contact;
use App\ContactAddress;
use App\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Crm\Http\Controllers\ContactAddressController;
use Modules\Jana\Scopes\ScopeByBusiness;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * US-CRM-078 PR2 backend — ContactAddressController (CRUD endereços).
 *
 * Cobertura (chamando o controller direto — schema mínimo sqlite = CI):
 *   1. Multi-tenant Tier 0 (ADR 0093) — store em contato de biz=99 sob sessão
 *      biz=1 → 404 (não vaza existência).
 *   2. Permission gate — sem customer.update → 403.
 *   3. Primeiro endereço vira default+shipping automaticamente + espelha inline.
 *   4. Invariante 1-default — novo default desmarca o anterior + re-espelha inline.
 *
 * @see Modules/Crm/Http/Controllers/ContactAddressController.php
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 */

const CA078C_MIGRATION = 'migrations/2026_06_01_120000_create_contact_addresses_table.php';

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
        $t->primary(['permission_id', 'model_id', 'model_type'], 'ca078c_mhp_pk');
    });
    Schema::create('model_has_roles', function (Blueprint $t) {
        $t->unsignedBigInteger('role_id');
        $t->string('model_type');
        $t->unsignedBigInteger('model_id');
        $t->primary(['role_id', 'model_id', 'model_type'], 'ca078c_mhr_pk');
    });
    Schema::create('role_has_permissions', function (Blueprint $t) {
        $t->unsignedBigInteger('permission_id');
        $t->unsignedBigInteger('role_id');
        $t->primary(['permission_id', 'role_id'], 'ca078c_rhp_pk');
    });
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

    (require database_path(CA078C_MIGRATION))->up();

    Permission::firstOrCreate(['name' => 'customer.update', 'guard_name' => 'web']);
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

function ca078cBusiness(): int
{
    return (int) DB::table('business')->insertGetId([
        'name' => 'Biz '.uniqid(), 'created_at' => now(), 'updated_at' => now(),
    ]);
}

function ca078cContact(int $bizId): Contact
{
    $c = new Contact();
    $c->business_id = $bizId;
    $c->type = 'customer';
    $c->name = 'Cliente Teste';
    $c->contact_status = 'active';
    $c->save();

    return $c;
}

function ca078cUser(int $bizId, bool $withPerm = true): User
{
    $u = User::forceCreate([
        'username' => 'u_'.$bizId.'_'.uniqid(),
        'password' => bcrypt('x'),
        'business_id' => $bizId,
    ]);
    if ($withPerm) {
        $u->givePermissionTo('customer.update');
    }

    return $u;
}

/** Liga a sessão multi-tenant ao request global (controller usa request()->session()). */
function ca078cBindRequest(string $method, string $uri, array $body, int $bizId): Request
{
    $req = Request::create($uri, $method, $body);
    $req->setLaravelSession(app('session.store'));
    app()->instance('request', $req);
    $req->session()->put('user.business_id', $bizId);

    return $req;
}

// ── testes ──────────────────────────────────────────────────────────────

test('store cross-tenant biz=99 sob sessão biz=1 retorna 404', function () {
    $biz1 = ca078cBusiness();
    $biz99 = ca078cBusiness();
    $foreign = ca078cContact($biz99);

    $this->actingAs(ca078cUser($biz1));
    $req = ca078cBindRequest('POST', "/cliente/{$foreign->id}/enderecos", [
        'label' => 'Hack', 'city' => 'Tubarão', 'state' => 'SC',
    ], $biz1);

    $resp = (new ContactAddressController())->store($req, $foreign->id);

    expect($resp->getStatusCode())->toBe(404);
    // Nada foi criado no contato do outro tenant.
    expect(ContactAddress::withoutGlobalScope(ScopeByBusiness::class)->count())->toBe(0);
});

test('store sem permissão customer.update retorna 403', function () {
    $biz = ca078cBusiness();
    $contact = ca078cContact($biz);

    $this->actingAs(ca078cUser($biz, withPerm: false));
    $req = ca078cBindRequest('POST', "/cliente/{$contact->id}/enderecos", [
        'label' => 'Matriz',
    ], $biz);

    $resp = (new ContactAddressController())->store($req, $contact->id);

    expect($resp->getStatusCode())->toBe(403);
});

test('primeiro endereço vira default+shipping e espelha nos campos inline de contacts', function () {
    $biz = ca078cBusiness();
    $contact = ca078cContact($biz);

    $this->actingAs(ca078cUser($biz));
    $req = ca078cBindRequest('POST', "/cliente/{$contact->id}/enderecos", [
        'label' => 'Matriz',
        'zip_code' => '88701-000',
        'address_line_1' => 'Av Marcolino Martins Cabral',
        'numero' => '1578',
        'city' => 'Tubarão',
        'state' => 'SC',
    ], $biz);

    $resp = (new ContactAddressController())->store($req, $contact->id);
    expect($resp->getStatusCode())->toBe(201);

    $addr = ContactAddress::withoutGlobalScope(ScopeByBusiness::class)->firstOrFail();
    expect($addr->is_default)->toBeTrue();
    expect($addr->is_shipping)->toBeTrue();

    // Espelho inline: contacts recebeu os campos do endereço default.
    $fresh = Contact::findOrFail($contact->id);
    expect($fresh->address_line_1)->toBe('Av Marcolino Martins Cabral');
    expect($fresh->numero)->toBe('1578');
    expect($fresh->city)->toBe('Tubarão');
    expect($fresh->state)->toBe('SC');
});

test('invariante 1-default: novo endereço default desmarca o anterior + re-espelha inline', function () {
    $biz = ca078cBusiness();
    $contact = ca078cContact($biz);
    $controller = new ContactAddressController();
    $user = ca078cUser($biz);

    // 1º endereço (auto default+shipping).
    $this->actingAs($user);
    $r1 = ca078cBindRequest('POST', "/cliente/{$contact->id}/enderecos", [
        'label' => 'Matriz', 'city' => 'Tubarão', 'state' => 'SC',
    ], $biz);
    $controller->store($r1, $contact->id);

    // 2º endereço marcado como default.
    $r2 = ca078cBindRequest('POST', "/cliente/{$contact->id}/enderecos", [
        'label' => 'Filial', 'city' => 'Criciúma', 'state' => 'SC', 'is_default' => true,
    ], $biz);
    $resp = $controller->store($r2, $contact->id);
    expect($resp->getStatusCode())->toBe(201);

    $defaults = ContactAddress::withoutGlobalScope(ScopeByBusiness::class)
        ->where('contact_id', $contact->id)->where('is_default', true)->get();
    expect($defaults)->toHaveCount(1);
    expect($defaults->first()->label)->toBe('Filial');

    // Espelho inline acompanha o novo default.
    expect(Contact::findOrFail($contact->id)->city)->toBe('Criciúma');
});
