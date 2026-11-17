<?php

declare(strict_types=1);

use App\Business;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Superadmin\Observers\BusinessAutoSubscriptionObserver;
use Modules\Superadmin\Services\BusinessAuditService;

uses(Tests\TestCase::class);

/**
 * A empresa operadora do SaaS vem de `config('app.saas_owner_business_id')`, não de `1` fixo.
 *
 * Auditoria do Superadmin (2026-11-05, eixo 4 G11): o módulo usava `business_id = 1` em 5 lugares
 * (+1 guard de exclusão), ignorando o config que o Financeiro e o PaymentGateway já leem. Com o
 * config no padrão (1) nada muda; o teste troca o config para o tenant fictício 98 e prova que é o
 * config que decide — e o controle com o config de volta em 1 prova que o caso discrimina.
 *
 * Tenant 98 (ADR 0358). Nunca biz=4.
 */
beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('Requer schema MySQL UltimatePOS + Superadmin.');
    }
    if (! Schema::hasTable('subscriptions') || ! Schema::hasTable('packages') || ! Schema::hasTable('system')) {
        $this->markTestSkipped('Schema Superadmin/System ausente.');
    }
});

/** Quantas consultas a `subscriptions` o observer faz ao ver `created()` do negócio. */
function operadoraConsultasDoObserver(Business $negocio): int
{
    DB::flushQueryLog();
    DB::enableQueryLog();
    (new BusinessAutoSubscriptionObserver())->created($negocio);
    $log = DB::getQueryLog();
    DB::disableQueryLog();

    return count(array_filter($log, fn ($q) => str_contains((string) $q['query'], 'subscriptions')));
}

it('o observer pula a empresa operadora do config, e segue para as outras', function () {
    $tenant = test()->seededTenant();

    // Sem pacote padrão o observer não cria nada em nenhum caminho: o teste não grava.
    $propriedadeAntes = DB::table('system')->where('key', 'default_saas_package_id')->value('value');
    DB::table('system')->where('key', 'default_saas_package_id')->delete();

    try {
        // O tenant 98 É a operadora: o observer volta antes de qualquer consulta.
        config(['app.saas_owner_business_id' => (int) $tenant->id]);
        expect(operadoraConsultasDoObserver($tenant))->toBe(0);

        // Controle: com a operadora em outro id, o mesmo tenant passa da guarda e o observer
        // consulta as assinaturas dele. Antes da troca, quem decidia era o `1` fixo.
        config(['app.saas_owner_business_id' => (int) $tenant->id + 100000]);
        expect(operadoraConsultasDoObserver($tenant))->toBeGreaterThan(0);
    } finally {
        if ($propriedadeAntes !== null) {
            DB::table('system')->insert(['key' => 'default_saas_package_id', 'value' => $propriedadeAntes]);
        }
    }
});

it('a empresa operadora do config é protegida contra exclusão, e o id 1 deixa de ser especial', function () {
    $tenant = test()->seededTenant();
    $servico = app(BusinessAuditService::class);

    config(['app.saas_owner_business_id' => (int) $tenant->id]);
    $operadora = $servico->canDestroy((int) $tenant->id, 0);
    expect($operadora['can_destroy'])->toBeFalse();
    expect($operadora['reason'])->toContain('protegido');

    // Controle: com a operadora em outro id, o mesmo tenant não cai na proteção.
    config(['app.saas_owner_business_id' => (int) $tenant->id + 100000]);
    expect($servico->canDestroy((int) $tenant->id, 0)['reason'])->not->toContain('protegido');
});
