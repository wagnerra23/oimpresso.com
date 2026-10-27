<?php

declare(strict_types=1);
// Cobre UC-PPAS-01, UC-PPAS-02, UC-PPAS-03, UC-PPAS-04, UC-PPAS-05, UC-PPAS-06, UC-PPAS-07 (Home/Index.casos.md).

use App\Business;
use App\Services\Dashboard\PrimeirosPassosService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Onboarding guiado (US-SUPER-012) — card "Primeiros passos" da Visão geral.
 * Tenant de teste 98 x cliente fictício 99 (ADR 0358). Nunca biz=4.
 *
 * @covers-us US-SUPER-012
 */
uses(DatabaseTransactions::class);

beforeEach(function () {
    if (! Schema::hasTable('business') || ! Schema::hasColumn('business', 'common_settings')) {
        $this->markTestSkipped('Schema UltimatePOS ausente (sqlite memory) — rode com DB_CONNECTION=mysql.');
    }

    $this->business = $this->seededTenant();
    $this->outra = $this->seededSupportClientTenant();
    $this->servico = app(PrimeirosPassosService::class);
    $this->versaoInertia = app(\App\Http\Middleware\HandleInertiaRequests::class)->version(request());
    config(['feature-flags.forced_on' => 'onboardingPrimeirosPassos']);
});

function ppasEntrar($teste, array $permissoes): void
{
    $user = $teste->usuarioComPermissoes($permissoes, $teste->business);
    $teste->actingAs($user);
    session(['user.business_id' => $teste->business->id, 'user.id' => $user->id, 'business.id' => $teste->business->id,
        'user.first_name' => 'Teste', 'business.enabled_modules' => []]);
}

function ppasStatus(array $estado, string $chave): string
{
    return collect($estado['passos'])->firstWhere('chave', $chave)['status'];
}

it('UC-PPAS-01 flag desligada: a Visão geral não registra a prop e o endpoint responde 404', function () {
    config(['feature-flags.forced_on' => '']);
    ppasEntrar($this, ['business_settings.access']);

    $r = $this->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => $this->versaoInertia, 'X-Requested-With' => 'XMLHttpRequest'])
        ->get('/dashboard-legacy');
    $r->assertOk();
    expect($r->json('props.primeiros_passos'))->toBeNull();

    $this->post('/home/primeiros-passos', ['acao' => 'dispensar'])->assertNotFound();
});

it('UC-PPAS-02 flag ligada e quem configura a empresa: a prop é deferida e traz os 6 passos na ordem', function () {
    ppasEntrar($this, ['business_settings.access']);

    $r = $this->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => $this->versaoInertia, 'X-Requested-With' => 'XMLHttpRequest'])
        ->get('/dashboard-legacy');
    $r->assertOk();
    expect(collect($r->json('deferredProps'))->flatten()->all())->toContain('primeiros_passos');

    $parcial = $this->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => $this->versaoInertia, 'X-Requested-With' => 'XMLHttpRequest',
        'X-Inertia-Partial-Component' => 'Home/Index', 'X-Inertia-Partial-Data' => 'primeiros_passos'])
        ->get('/dashboard-legacy');
    $parcial->assertOk();
    expect(array_column($parcial->json('props.primeiros_passos.passos'), 'chave'))
        ->toBe(['empresa', 'fiscal', 'produto', 'cliente', 'venda', 'equipe']);
});

it('UC-PPAS-03 sem business_settings.access o card não existe e o endpoint responde 404', function () {
    ppasEntrar($this, ['dashboard.data']);

    $r = $this->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => $this->versaoInertia, 'X-Requested-With' => 'XMLHttpRequest'])
        ->get('/dashboard-legacy');
    expect($r->json('props.primeiros_passos'))->toBeNull();

    $this->post('/home/primeiros-passos', ['acao' => 'dispensar'])->assertNotFound();
});

it('UC-PPAS-04 o passo é FEITO pelo dado da própria empresa, e o dado de outra empresa não conta', function () {
    DB::table('business')->where('id', $this->business->id)->update(['tax_number_1' => '']);
    DB::table('business')->where('id', $this->outra->id)->update(['tax_number_1' => '00000000000191']);
    expect(ppasStatus($this->servico->estado($this->business->id), 'empresa'))->toBe('aberto');

    DB::table('business')->where('id', $this->business->id)->update(['tax_number_1' => '00000000000272']);
    expect(ppasStatus($this->servico->estado($this->business->id), 'empresa'))->toBe('feito');

    if (Schema::hasTable('nfe_certificados')) {
        // Sem certificado ativo da PRÓPRIA empresa o passo fica aberto, tenha a outra empresa ou não.
        DB::table('nfe_certificados')->where('business_id', $this->business->id)->delete();
        expect(ppasStatus($this->servico->estado($this->business->id), 'fiscal'))->toBe('aberto');
    }
});

it('UC-PPAS-05 pular tira o passo da conta sem marcar feito, e voltar desfaz — gravado na empresa', function () {
    ppasEntrar($this, ['business_settings.access']);
    DB::table('business')->where('id', $this->business->id)->update(['tax_number_1' => '']);

    $this->from('/dashboard-legacy')->post('/home/primeiros-passos', ['acao' => 'pular', 'passo' => 'empresa'])->assertStatus(303);
    $estado = $this->servico->estado($this->business->id);
    expect(ppasStatus($estado, 'empresa'))->toBe('pulado');
    expect($estado['pulados'])->toBe(1);

    $comum = Business::query()->whereKey($this->business->id)->value('common_settings');
    $comum = is_string($comum) ? json_decode($comum, true) : $comum;
    expect($comum['primeiros_passos']['pulados'])->toBe(['empresa']);

    $this->from('/dashboard-legacy')->post('/home/primeiros-passos', ['acao' => 'voltar', 'passo' => 'empresa'])->assertStatus(303);
    expect(ppasStatus($this->servico->estado($this->business->id), 'empresa'))->toBe('aberto');
});

it('UC-PPAS-06 dispensar esconde o card para a empresa e retomar devolve, sem tocar a outra empresa', function () {
    ppasEntrar($this, ['business_settings.access']);
    DB::table('business')->where('id', $this->business->id)->update(['tax_number_1' => '']);
    $outraAntes = DB::table('business')->where('id', $this->outra->id)->value('common_settings');

    $this->from('/dashboard-legacy')->post('/home/primeiros-passos', ['acao' => 'dispensar'])->assertStatus(303);
    expect($this->servico->estado($this->business->id)['visivel'])->toBeFalse();
    expect($this->servico->estado($this->business->id)['dispensado'])->toBeTrue();

    $this->from('/dashboard-legacy')->post('/home/primeiros-passos', ['acao' => 'retomar'])->assertStatus(303);
    expect($this->servico->estado($this->business->id)['visivel'])->toBeTrue();

    expect(DB::table('business')->where('id', $this->outra->id)->value('common_settings'))->toBe($outraAntes);
});

it('UC-PPAS-07 ação ou passo desconhecido é recusado e nada é gravado', function () {
    ppasEntrar($this, ['business_settings.access']);
    $antes = DB::table('business')->where('id', $this->business->id)->value('common_settings');

    $this->from('/dashboard-legacy')->post('/home/primeiros-passos', ['acao' => 'pular', 'passo' => 'inexistente'])
        ->assertSessionHasErrors('passo');
    $this->from('/dashboard-legacy')->post('/home/primeiros-passos', ['acao' => 'apagar'])
        ->assertSessionHasErrors('acao');

    expect(DB::table('business')->where('id', $this->business->id)->value('common_settings'))->toBe($antes);
});
