<?php

declare(strict_types=1);

uses(Tests\TestCase::class)->in(__DIR__);

/**
 * Smoke unit test do trait HasBusinessScope.
 *
 * Não testa isolamento real cross-tenant em DB (cada Model precisa Pest Feature
 * próprio). Esta suite garante apenas que:
 *   1. Trait existe e é carregável
 *   2. Adiciona ScopeByBusiness ao boot do Model
 *   3. Models que usam trait têm o scope no array de globalScopes
 *
 * Tests de isolamento real ficam por Model em Modules/<Mod>/Tests/Feature/.
 */

use App\Concerns\HasBusinessScope;
use Modules\Jana\Scopes\ScopeByBusiness;

test('trait HasBusinessScope existe', function () {
    expect(trait_exists(HasBusinessScope::class))->toBeTrue();
});

test('trait usa ScopeByBusiness do Modules\Jana\Scopes', function () {
    expect(class_exists(ScopeByBusiness::class))->toBeTrue();

    // Verifica via reflection que bootHasBusinessScope chama addGlobalScope
    $traitFile = (new ReflectionClass(HasBusinessScope::class))->getFileName();
    $contents = file_get_contents($traitFile);
    expect($contents)->toContain('addGlobalScope(new ScopeByBusiness)');
});

test('Subscription usa HasBusinessScope', function () {
    $usesTraitsRecursive = function ($class) use (&$usesTraitsRecursive) {
        $traits = class_uses_recursive($class);
        return $traits;
    };

    $traits = $usesTraitsRecursive(\Modules\RecurringBilling\Models\Subscription::class);
    expect($traits)->toHaveKey(HasBusinessScope::class);
});

test('Invoice usa HasBusinessScope', function () {
    $traits = class_uses_recursive(\Modules\RecurringBilling\Models\Invoice::class);
    expect($traits)->toHaveKey(HasBusinessScope::class);
});

test('NfeCertificado usa HasBusinessScope', function () {
    $traits = class_uses_recursive(\Modules\NfeBrasil\Models\NfeCertificado::class);
    expect($traits)->toHaveKey(HasBusinessScope::class);
});

test('NfeEmissao usa HasBusinessScope', function () {
    $traits = class_uses_recursive(\Modules\NfeBrasil\Models\NfeEmissao::class);
    expect($traits)->toHaveKey(HasBusinessScope::class);
});

test('MemoriaFato usa HasBusinessScope', function () {
    $traits = class_uses_recursive(\Modules\Jana\Entities\MemoriaFato::class);
    expect($traits)->toHaveKey(HasBusinessScope::class);
});

test('Conversa migrada do addGlobalScope manual pro trait', function () {
    $traits = class_uses_recursive(\Modules\Jana\Entities\Conversa::class);
    expect($traits)->toHaveKey(HasBusinessScope::class);

    // Conversa NÃO deve mais ter boot/booted manual com addGlobalScope
    // (paridade após migração do PR atual)
    $reflection = new ReflectionClass(\Modules\Jana\Entities\Conversa::class);
    $contents = file_get_contents($reflection->getFileName());
    expect($contents)->not->toContain('addGlobalScope(new ScopeByBusiness)');
});

test('Meta migrada do addGlobalScope manual pro trait', function () {
    $traits = class_uses_recursive(\Modules\Jana\Entities\Meta::class);
    expect($traits)->toHaveKey(HasBusinessScope::class);

    $reflection = new ReflectionClass(\Modules\Jana\Entities\Meta::class);
    $contents = file_get_contents($reflection->getFileName());
    expect($contents)->not->toContain('addGlobalScope(new ScopeByBusiness)');
});

test('Repair JobSheet usa HasBusinessScope', function () {
    $traits = class_uses_recursive(\Modules\Repair\Entities\JobSheet::class);
    expect($traits)->toHaveKey(HasBusinessScope::class);
});

test('Repair RepairStatus usa HasBusinessScope', function () {
    $traits = class_uses_recursive(\Modules\Repair\Entities\RepairStatus::class);
    expect($traits)->toHaveKey(HasBusinessScope::class);
});

test('Repair DeviceModel usa HasBusinessScope', function () {
    $traits = class_uses_recursive(\Modules\Repair\Entities\DeviceModel::class);
    expect($traits)->toHaveKey(HasBusinessScope::class);
});
