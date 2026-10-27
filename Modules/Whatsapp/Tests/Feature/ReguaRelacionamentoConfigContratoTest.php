<?php
// @covers-us US-WA-328

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Modules\Whatsapp\Http\Controllers\Admin\ReguaRelacionamentoController;
use Tests\Support\WithSeededTenant;

/**
 * Configuração da régua de relacionamento por empresa (US-WA-328).
 *
 * Âncora: SPEC US-WA-328 (critérios 1-3) e `JanaTemplates.casos.md` UC-JTPL-04.
 * Tenant 98 contra 99 (ADR 0358). ⚠️ SKIP em SQLite (LC-13).
 */
uses(Tests\TestCase::class, DatabaseTransactions::class, WithSeededTenant::class);

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite' || ! Schema::hasTable('whatsapp_reguas_relacionamento')) {
        $this->markTestSkipped('Requer MySQL com as tabelas da régua.');
    }
    try {
        $this->biz = $this->seededTenant();
        $this->outra = $this->seededSupportClientTenant();
    } catch (\Throwable $e) {
        $this->markTestSkipped('Schema UltimatePOS ausente: '.$e->getMessage());
    }
    $user = \App\User::factory()->create(['business_id' => $this->biz->id]);
    $this->actingAs($user);
    session(['user.business_id' => $this->biz->id, 'user.id' => $user->id]);
    DB::table('whatsapp_reguas_relacionamento')->whereIn('business_id', [$this->biz->id, $this->outra->id])->delete();
    $this->salvar = fn (array $d) => app(ReguaRelacionamentoController::class)
        ->update(Request::create('/atendimento/canais/regua-relacionamento', 'PUT', $d));
});

it('UC-JTPL-04 · US-WA-328 · salvar grava a régua da própria empresa, uma linha por tipo', function () {
    expect(ReguaRelacionamentoController::daEmpresa($this->biz->id)['aniversario']['ativo'])->toBeFalse();

    ($this->salvar)(['tipo' => 'aniversario', 'ativo' => true, 'texto' => 'Parabéns, {nome}!']);
    ($this->salvar)(['tipo' => 'recompra', 'ativo' => true, 'texto' => 'Sentimos sua falta, {nome}.', 'dias_sem_compra' => 45]);
    ($this->salvar)(['tipo' => 'aniversario', 'ativo' => false, 'texto' => 'Feliz aniversário, {nome}!']);

    $r = ReguaRelacionamentoController::daEmpresa($this->biz->id);
    expect($r['aniversario'])->toBe(['ativo' => false, 'texto' => 'Feliz aniversário, {nome}!', 'dias_sem_compra' => null])
        ->and($r['recompra'])->toBe(['ativo' => true, 'texto' => 'Sentimos sua falta, {nome}.', 'dias_sem_compra' => 45]);
    expect(DB::table('whatsapp_reguas_relacionamento')->where('business_id', $this->biz->id)->count())->toBe(2)
        ->and(DB::table('whatsapp_reguas_relacionamento')->where('business_id', $this->outra->id)->count())->toBe(0);
});

it('US-WA-328 · recusa tipo inventado, recompra sem dias ou com menos de 7 dias', function () {
    expect(fn () => ($this->salvar)(['tipo' => 'natal', 'ativo' => true, 'texto' => 'x']))->toThrow(ValidationException::class);
    expect(fn () => ($this->salvar)(['tipo' => 'recompra', 'ativo' => true, 'texto' => 'x']))->toThrow(ValidationException::class);
    expect(fn () => ($this->salvar)(['tipo' => 'recompra', 'ativo' => true, 'texto' => 'x', 'dias_sem_compra' => 2]))->toThrow(ValidationException::class);
    expect(DB::table('whatsapp_reguas_relacionamento')->where('business_id', $this->biz->id)->count())->toBe(0);
});
