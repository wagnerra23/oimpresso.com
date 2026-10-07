<?php

declare(strict_types=1);

// @covers-us US-NFE-010 — aceite do contador por versão de regra (D-SUPORTE · D-CONTADOR, thread 15a).
// Contrato da tela: resources/js/Pages/NfeBrasil/Tributacao/Index.casos.md — UC-NFTR-19 · 20 · 21

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Modules\NfeBrasil\Events\FiscalRuleCreated;
use Modules\NfeBrasil\Events\FiscalRuleDeleted;
use Modules\NfeBrasil\Events\FiscalRuleUpdated;
use Modules\NfeBrasil\Models\NfeFiscalRule;
use Modules\NfeBrasil\Models\NfeRevisaoContador;
use Modules\NfeBrasil\Services\MotorTributarioService;
use Modules\NfeBrasil\Services\Tributacao\ImportRegrasCsvService;
use Modules\NfeBrasil\Services\Tributacao\ProdutoFiscalContext;
use Modules\NfeBrasil\Services\Tributacao\RevisaoContadorService;
use Spatie\Permission\Models\Role;

uses(Tests\TestCase::class);

/**
 * Os casos derivam do aceite da thread 15 (D-CONTADOR: "o admin não aceita pelo contador";
 * D-SUPORTE: "aceite não bloqueia emissão") — não do controller. MySQL-only · tenant 98 (ADR 0358).
 * NCMs 7715xxxx reservados a este arquivo.
 */

function aceBiz(): int
{
    return test()->seededTenant()->id;
}

function aceLimpar(): void
{
    DB::statement('SET FOREIGN_KEY_CHECKS=0');
    $regras = DB::table('nfe_fiscal_rules')->where('business_id', aceBiz())->where('ncm', 'like', '7715%')->pluck('id');
    $rev = DB::table('nfe_revisoes_contador')->whereIn('regra_id', $regras)->pluck('id');
    DB::table('activity_log')->where('subject_type', NfeRevisaoContador::class)->whereIn('subject_id', $rev)->delete();
    DB::table('nfe_revisoes_contador')->whereIn('id', $rev)->delete();
    DB::table('nfe_fiscal_rules')->whereIn('id', $regras)->delete();
    DB::statement('SET FOREIGN_KEY_CHECKS=1');
}

function aceComo(\App\User $u): void
{
    test()->actingAs($u)->withSession(['business.id' => aceBiz(), 'user.business_id' => aceBiz()]);
}

function acePayload(string $ncm, float $icms): array
{
    return ['ncm' => $ncm, 'uf_origem' => 'SP', 'uf_destino' => null, 'cfop' => '5102', 'csosn' => '102',
        'aliquota_icms' => $icms, 'aliquota_pis' => 0, 'aliquota_cofins' => 0, 'aliquota_ipi' => 0];
}

/** Dono da empresa: papel Admin#98 (o Gate::before libera qualquer can() para ele). */
function aceAdmin(): \App\User
{
    $u = \App\User::factory()->create(['business_id' => aceBiz()]);
    $papel = Role::firstOrCreate(['name' => 'Admin#' . aceBiz(), 'business_id' => aceBiz(), 'guard_name' => 'web']);
    $u->assignRole($papel);
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

    return \App\User::findOrFail($u->id);
}

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('MySQL-only: tabela nfe_revisoes_contador vem da migração 2026_10_07_000005.');
    }
    if (! Schema::hasTable('nfe_revisoes_contador') || ! Schema::hasColumn('nfe_fiscal_rules', 'valida_de')) {
        $this->markTestSkipped('Migrações da thread 07/15a não rodaram — rode as migrations do módulo.');
    }
    Event::fake([FiscalRuleCreated::class, FiscalRuleUpdated::class, FiscalRuleDeleted::class]);
    NfeFiscalRule::esquecerVersionamento();
    RevisaoContadorService::esquecerTabela();
    aceLimpar();
});

afterEach(function () {
    if (DB::connection()->getDriverName() !== 'sqlite' && Schema::hasTable('nfe_revisoes_contador')) {
        aceLimpar();
    }
});

// ---------------------------------------------------------------------------------------
// UC-NFTR-19 · Aceite é por versão e exige permissão própria  [T0] [fiscal]
// ---------------------------------------------------------------------------------------
it('UC-NFTR-19 · aceite por versão, permissão própria', function () {
    $gestor    = test()->usuarioComPermissoes(['nfe.tributacao.manage']);
    $contador  = test()->usuarioComPermissoes(['nfe.tributacao.aceitar']);
    $dono      = aceAdmin();

    aceComo($gestor);
    $this->post('/nfe-brasil/tributacao/regras', acePayload('77150001', 0.12))->assertSessionHasNoErrors();
    $regra = (int) DB::table('nfe_fiscal_rules')->where('business_id', aceBiz())->where('ncm', '77150001')->value('id');
    $rev = NfeRevisaoContador::query()->where('regra_id', $regra)->firstOrFail();
    expect($rev->status)->toBe('pendente')
        ->and($rev->origem)->toBe('manual')
        ->and($rev->autor_id)->toBe($gestor->id)
        ->and($rev->diff['aliquota_icms'])->toBe([null, 0.12]);

    // O dono da empresa (Admin#98, que o Gate::before deixa passar em tudo) NÃO aceita pelo contador.
    aceComo($dono);
    $this->postJson("/nfe-brasil/tributacao/revisoes/{$rev->id}/aceitar")->assertForbidden();
    // Quem só gerencia a tributação também não.
    aceComo($gestor);
    $this->postJson("/nfe-brasil/tributacao/revisoes/{$rev->id}/aceitar")->assertForbidden();
    expect($rev->fresh()->status)->toBe('pendente');

    // Com nfe.tributacao.aceitar: grava quem, quando e de onde, e loga.
    aceComo($contador);
    $this->postJson("/nfe-brasil/tributacao/revisoes/{$rev->id}/aceitar")->assertOk()->assertJsonPath('status', 'aceita');
    $rev->refresh();
    expect($rev->aceito_por_user_id)->toBe($contador->id)
        ->and($rev->aceito_por_email)->toBe($contador->email)
        ->and($rev->aceito_em)->not->toBeNull()
        ->and($rev->aceito_ip)->not->toBeNull()
        ->and(DB::table('activity_log')->where('subject_id', $rev->id)->where('description', 'aceite.registrado')->count())->toBe(1);

    // Editar a regra aceita: versão nova nasce PENDENTE com o de → para; a antiga mantém o aceite.
    aceComo($gestor);
    $this->put("/nfe-brasil/tributacao/regras/{$regra}", acePayload('77150001', 0.18))->assertSessionHasNoErrors();
    $nova = NfeRevisaoContador::query()->where('regra_anterior_id', $regra)->firstOrFail();
    expect($nova->regra_id)->not->toBe($regra)
        ->and($nova->status)->toBe('pendente')
        ->and($nova->diff['aliquota_icms'])->toBe([0.12, 0.18])
        ->and($rev->fresh()->status)->toBe('aceita');

    // CONTROLE POSITIVO — o lote do Import CSV entra com origem "csv".
    app(ImportRegrasCsvService::class)->aplicar(aceBiz(), [acePayload('77150002', 0.07)]);
    $csv = DB::table('nfe_fiscal_rules')->where('business_id', aceBiz())->where('ncm', '77150002')->value('id');
    expect(NfeRevisaoContador::query()->where('regra_id', $csv)->value('origem'))->toBe('csv');
});

// ---------------------------------------------------------------------------------------
// UC-NFTR-20 · Pedir ajuste exige comentário e fica registrado  [fiscal]
// ---------------------------------------------------------------------------------------
it('UC-NFTR-20 · pedir ajuste com comentário', function () {
    $gestor   = test()->usuarioComPermissoes(['nfe.tributacao.manage']);
    $contador = test()->usuarioComPermissoes(['nfe.tributacao.aceitar']);

    aceComo($gestor);
    $this->post('/nfe-brasil/tributacao/regras', acePayload('77150003', 0.12))->assertSessionHasNoErrors();
    $regra = (int) DB::table('nfe_fiscal_rules')->where('business_id', aceBiz())->where('ncm', '77150003')->value('id');
    $rev = NfeRevisaoContador::query()->where('regra_id', $regra)->firstOrFail();

    aceComo($contador);
    $this->postJson("/nfe-brasil/tributacao/revisoes/{$rev->id}/ajuste")->assertStatus(422);
    $this->postJson("/nfe-brasil/tributacao/revisoes/{$rev->id}/ajuste", ['comentario' => '   '])->assertStatus(422);
    expect($rev->fresh()->status)->toBe('pendente');

    $this->postJson("/nfe-brasil/tributacao/revisoes/{$rev->id}/ajuste", ['comentario' => 'NCM 7715 é ICMS 7% em SP.'])
        ->assertOk()->assertJsonPath('status', 'ajuste_pedido');
    expect($rev->fresh()->comentario)->toBe('NCM 7715 é ICMS 7% em SP.')
        ->and(DB::table('activity_log')->where('subject_id', $rev->id)->where('description', 'aceite.ajuste_pedido')->count())->toBe(1);

    // A empresa vê o pedido na lista.
    $lista = $this->getJson('/nfe-brasil/tributacao/revisoes')->assertOk()->json('revisoes');
    expect(collect($lista)->firstWhere('id', $rev->id)['comentario'] ?? null)->toBe('NCM 7715 é ICMS 7% em SP.');

    // CONTROLE POSITIVO — corrigida a regra (versão nova), o item volta pendente para o contador.
    aceComo($gestor);
    $this->put("/nfe-brasil/tributacao/regras/{$regra}", acePayload('77150003', 0.07))->assertSessionHasNoErrors();
    expect(NfeRevisaoContador::query()->where('regra_anterior_id', $regra)->value('status'))->toBe('pendente');
});

// ---------------------------------------------------------------------------------------
// UC-NFTR-21 · Falta de aceite não bloqueia emissão  [fiscal]
// ---------------------------------------------------------------------------------------
it('UC-NFTR-21 · sem aceite emite normal', function () {
    $gestor   = test()->usuarioComPermissoes(['nfe.tributacao.manage']);
    $contador = test()->usuarioComPermissoes(['nfe.tributacao.aceitar']);

    aceComo($gestor);
    $this->post('/nfe-brasil/tributacao/regras', acePayload('77150004', 0.12))->assertSessionHasNoErrors();
    $regra = (int) DB::table('nfe_fiscal_rules')->where('business_id', aceBiz())->where('ncm', '77150004')->value('id');

    // Sem aceite: o motor calcula normalmente — 1.000 × 0,12 = 120,00.
    $t = (new MotorTributarioService())->calcular(new ProdutoFiscalContext(ncm: '77150004', valor: 1000.0), aceBiz(), 'SP', 'SP');
    expect($t->valor_icms)->toBe(120.0)->and($t->regra_id)->toBe($regra);

    aceComo($contador);
    $antes = $this->getJson('/nfe-brasil/tributacao/revisoes')->assertOk()->json('sem_aceite');
    expect($antes)->toBeGreaterThanOrEqual(1);

    // CONTROLE POSITIVO — aceita, o aviso de "sem aceite" diminui em 1 e o cálculo segue igual.
    $rev = NfeRevisaoContador::query()->where('regra_id', $regra)->firstOrFail();
    $this->postJson("/nfe-brasil/tributacao/revisoes/{$rev->id}/aceitar")->assertOk();
    expect($this->getJson('/nfe-brasil/tributacao/revisoes')->json('sem_aceite'))->toBe($antes - 1);
    expect((new MotorTributarioService())->calcular(new ProdutoFiscalContext(ncm: '77150004', valor: 1000.0), aceBiz(), 'SP', 'SP')->valor_icms)
        ->toBe(120.0);
});
