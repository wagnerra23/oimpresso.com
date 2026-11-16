<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Crm\Http\Controllers\CrmMarketplaceController;

/**
 * Importação de leads do marketplace B2B (CrmMarketplaceController@importLeads) reaproveita lead já
 * existente pelo e-mail. Hoje o recurso está desligado (`constants.enable_b2b_marketplace = false`),
 * mas a busca precisa respeitar a empresa para quando for ligado.
 *
 * Tier 0 (ADR 0093): lead com o mesmo e-mail em outra empresa → não encontrado (controle negativo);
 * lead da própria empresa → encontrado (controle positivo, em par).
 */
uses(DatabaseTransactions::class);

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite' || ! Schema::hasTable('contacts')) {
        $this->markTestSkipped('Requer schema MySQL UltimatePOS (ADR 0358).');
    }
    $this->biz = (int) $this->seededTenant()->id;
    $this->outro = (int) \App\Business::where('id', '!=', $this->biz)->orderBy('id')->value('id');
    if (! $this->outro) {
        $this->markTestSkipped('Lane sem 2º business — contrato cross-tenant não exercitável.');
    }
    $this->criador = (int) DB::table('users')->where('business_id', $this->biz)->value('id');
});

function crmMktLead(int $biz, int $criador, string $email): int
{
    return (int) DB::table('contacts')->insertGetId([
        'business_id' => $biz, 'type' => 'lead', 'name' => 'Lead Mkt ' . uniqid(), 'email' => $email,
        'contact_status' => 'active', 'created_by' => $criador, 'created_at' => now(), 'updated_at' => now(),
    ]);
}

function crmMktBuscar(int $biz, string $email)
{
    $m = new ReflectionMethod(CrmMarketplaceController::class, 'buscarLeadPorEmail');
    $m->setAccessible(true);

    return $m->invoke(app(CrmMarketplaceController::class), $biz, $email);
}

it('não reaproveita lead de outra empresa com o mesmo e-mail', function () {
    $email = 'lead-' . uniqid() . '@exemplo.test';
    crmMktLead($this->outro, $this->criador, $email);

    expect(crmMktBuscar($this->biz, $email))->toBeNull();
});

it('reaproveita lead da própria empresa (controle positivo)', function () {
    $email = 'lead-' . uniqid() . '@exemplo.test';
    $meu = crmMktLead($this->biz, $this->criador, $email);

    expect(crmMktBuscar($this->biz, $email)?->id)->toBe($meu);
});
