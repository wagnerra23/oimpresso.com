<?php

declare(strict_types=1);

use App\Business;
use App\Contact;
use App\ContactAddress;
use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Modules\Crm\Http\Controllers\ContactAddressController;
use Modules\Jana\Scopes\ScopeByBusiness;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * US-CRM-078 PR2 backend — ContactAddressController CRUD (cross-tenant + invariantes).
 *
 * Roda NO CT 100 contra o MySQL real, NUNCA sqlite (proibicoes.md §Ambiente):
 *   docker exec -e DB_CONNECTION=mysql oimpresso-staging php -d memory_limit=512M \
 *     vendor/bin/pest tests/Feature/Contact/ContactAddressControllerTest.php
 * DatabaseTransactions → rollback (não polui staging). Skip-graceful em sqlite/CI.
 * Asserts escopados aos contatos criados (resistente a dados de staging).
 *
 * @see Modules/Crm/Http/Controllers/ContactAddressController.php
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 */

uses(DatabaseTransactions::class);

beforeEach(function () {
    if (! Schema::hasTable('contacts') || ! Schema::hasTable('contact_addresses')) {
        $this->markTestSkipped('Schema UPos/contact_addresses ausente — rode no CT 100 (MySQL real). NAO sqlite.');
    }
    Permission::firstOrCreate(['name' => 'customer.update', 'guard_name' => 'web']);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

function ca078cBiz(): int
{
    return (int) Business::create([
        'name' => 'Biz US078c '.uniqid(),
        'currency_id' => 1,
        'start_date' => '2026-01-01',
        'default_profit_percent' => 25.0,
        'owner_id' => 1,
    ])->id;
}

function ca078cContact(int $bizId): Contact
{
    $c = new Contact();
    $c->business_id = $bizId;
    $c->type = 'customer';
    $c->name = 'Cliente US078c';
    $c->contact_status = 'active';
    $c->created_by = 1; // NOT NULL no MySQL real
    $c->save();

    return $c;
}

function ca078cUser(int $bizId, bool $withPerm = true): User
{
    $u = User::factory()->create(['business_id' => $bizId]);
    if ($withPerm) {
        $u->givePermissionTo('customer.update');
    }

    return $u;
}

/** Liga a sessão multi-tenant ao request global (controller usa request()->session()). */
function ca078cBindReq(string $method, string $uri, array $body, int $bizId): Request
{
    $req = Request::create($uri, $method, $body);
    $req->setLaravelSession(app('session.store'));
    app()->instance('request', $req);
    $req->session()->put('user.business_id', $bizId);

    return $req;
}

test('store cross-tenant biz=99 sob sessao biz=1 retorna 404', function () {
    $biz1 = ca078cBiz();
    $biz99 = ca078cBiz();
    $foreign = ca078cContact($biz99);

    $this->actingAs(ca078cUser($biz1));
    $req = ca078cBindReq('POST', "/cliente/{$foreign->id}/enderecos", [
        'label' => 'Hack', 'city' => 'Tubarão', 'state' => 'SC',
    ], $biz1);

    $resp = (new ContactAddressController())->store($req, $foreign->id);

    expect($resp->getStatusCode())->toBe(404);
    expect(
        ContactAddress::withoutGlobalScope(ScopeByBusiness::class)
            ->where('contact_id', $foreign->id)->count()
    )->toBe(0);
});

test('store sem permissao customer.update retorna 403', function () {
    $biz = ca078cBiz();
    $contact = ca078cContact($biz);

    $user = ca078cUser($biz, withPerm: false);
    if ($user->can('customer.update') || $user->can('supplier.update')) {
        $this->markTestSkipped('User factory ja vem com customer/supplier.update neste ambiente.');
    }
    $this->actingAs($user);
    $req = ca078cBindReq('POST', "/cliente/{$contact->id}/enderecos", ['label' => 'Matriz'], $biz);

    expect((new ContactAddressController())->store($req, $contact->id)->getStatusCode())->toBe(403);
});

test('primeiro endereco vira default+shipping e espelha inline em contacts', function () {
    $biz = ca078cBiz();
    $contact = ca078cContact($biz);

    $this->actingAs(ca078cUser($biz));
    $req = ca078cBindReq('POST', "/cliente/{$contact->id}/enderecos", [
        'label' => 'Matriz',
        'zip_code' => '88701-000',
        'address_line_1' => 'Av Marcolino Martins Cabral',
        'numero' => '1578',
        'city' => 'Tubarão',
        'state' => 'SC',
    ], $biz);

    expect((new ContactAddressController())->store($req, $contact->id)->getStatusCode())->toBe(201);

    $addr = ContactAddress::withoutGlobalScope(ScopeByBusiness::class)
        ->where('contact_id', $contact->id)->first();
    expect($addr->is_default)->toBeTrue();
    expect($addr->is_shipping)->toBeTrue();

    $fresh = Contact::findOrFail($contact->id);
    expect($fresh->address_line_1)->toBe('Av Marcolino Martins Cabral');
    expect($fresh->numero)->toBe('1578');
    expect($fresh->city)->toBe('Tubarão');
});

test('invariante 1-default: novo default desmarca anterior + re-espelha inline', function () {
    $biz = ca078cBiz();
    $contact = ca078cContact($biz);
    $controller = new ContactAddressController();

    $this->actingAs(ca078cUser($biz));

    $r1 = ca078cBindReq('POST', "/cliente/{$contact->id}/enderecos", [
        'label' => 'Matriz', 'city' => 'Tubarão', 'state' => 'SC',
    ], $biz);
    $controller->store($r1, $contact->id);

    $r2 = ca078cBindReq('POST', "/cliente/{$contact->id}/enderecos", [
        'label' => 'Filial', 'city' => 'Criciúma', 'state' => 'SC', 'is_default' => true,
    ], $biz);
    expect($controller->store($r2, $contact->id)->getStatusCode())->toBe(201);

    $defaults = ContactAddress::withoutGlobalScope(ScopeByBusiness::class)
        ->where('contact_id', $contact->id)->where('is_default', true)->get();
    expect($defaults)->toHaveCount(1);
    expect($defaults->first()->label)->toBe('Filial');

    expect(Contact::findOrFail($contact->id)->city)->toBe('Criciúma');
});
