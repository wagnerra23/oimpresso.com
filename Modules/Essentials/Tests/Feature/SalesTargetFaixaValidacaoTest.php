<?php

declare(strict_types=1);

use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Essentials\Entities\EssentialsUserSalesTarget;
use Modules\Essentials\Services\SalesTargetFaixaValidator;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * HRM-O6 / PR-4 — achado A5: saveSalesTarget aceitava faixa de meta inválida.
 *
 * target_start/target_end são FAIXA DE VALOR de venda (decimal 22,4), não datas.
 * Quem transforma isso em dinheiro é PayrollController (~L258):
 *
 *     ->where('target_start','<=',$total)->where('target_end','>=',$total)->first()
 *     $comissao = calc_percentage($total, $target->commission_percent)
 *
 * Os 3 defeitos que este teste fixa como contrato:
 *   - faixa invertida (end < start) nunca casa a query ⇒ comissão devida some calada;
 *   - faixas sobrepostas ⇒ o ->first() SEM orderBy paga percentual indefinido;
 *   - percentual fora de 0–100 ⇒ calc_percentage não tem teto (150% paga 1,5x o vendido).
 *
 * Dois caminhos INDEPENDENTES de prova, como manda a regra mestre de valor
 * (memory/proibicoes.md §CÁLCULO DE VALOR ou ESTOQUE):
 *   (A) a regra pura, sem banco nem HTTP — aritmética conferível a olho;
 *   (B) o endpoint ponta-a-ponta — prova que nada é gravado quando a regra reprova
 *       e que a entrada VÁLIDA grava exatamente o mesmo número de antes.
 *
 * Tenant: 98 (ADR 0358 — canônico fictício). Nunca biz=4. Nunca biz=1.
 */
const STFV_ROTA = '/hrm/save-sales-target';

// ---------------------------------------------------------------------------
// (A) Regra pura — roda em qualquer driver, sem banco. Números conferíveis a olho.
// ---------------------------------------------------------------------------

function stfvFaixa(float $start, float $end, float $comissao, string $rotulo = 'no 1'): array
{
    return ['rotulo' => $rotulo, 'start' => $start, 'end' => $end, 'commission' => $comissao];
}

it('A: conjunto valido nao gera erro — faixas contiguas que nao se tocam', function () {
    $erros = SalesTargetFaixaValidator::erros([
        stfvFaixa(0.0, 1000.0, 1.0, 'no 1'),
        stfvFaixa(1000.01, 5000.0, 2.5, 'no 2'),
        stfvFaixa(5000.01, 20000.0, 5.0, 'no 3'),
    ]);

    expect($erros)->toBe([]);
});

it('A: faixa invertida e reprovada (end < start nunca casaria a query da folha)', function () {
    $erros = SalesTargetFaixaValidator::erros([stfvFaixa(10000.0, 5000.0, 3.0)]);

    expect($erros)->toHaveCount(1)
        ->and($erros[0])->toContain('MAIOR que o inicial');
});

it('A: faixa degenerada (start == end) e reprovada — o contrato e end > start', function () {
    $erros = SalesTargetFaixaValidator::erros([stfvFaixa(2000.0, 2000.0, 3.0)]);

    expect($erros)->toHaveCount(1)
        ->and($erros[0])->toContain('MAIOR que o inicial');
});

it('A: comissao acima de 100 e reprovada — calc_percentage nao tem teto', function () {
    // 150% sobre um total de 10.000 pagaria 15.000 de comissao numa venda de 10.000.
    $erros = SalesTargetFaixaValidator::erros([stfvFaixa(0.0, 10000.0, 150.0)]);

    expect($erros)->toHaveCount(1)
        ->and($erros[0])->toContain('entre 0% e 100%');
});

it('A: comissao negativa e reprovada — viraria abatimento na folha', function () {
    $erros = SalesTargetFaixaValidator::erros([stfvFaixa(0.0, 10000.0, -5.0)]);

    expect($erros)->toHaveCount(1)
        ->and($erros[0])->toContain('entre 0% e 100%');
});

it('A: 0% e 100% sao os limites ACEITOS (fronteira inclusiva)', function () {
    expect(SalesTargetFaixaValidator::erros([stfvFaixa(0.0, 10000.0, 0.0)]))->toBe([]);
    expect(SalesTargetFaixaValidator::erros([stfvFaixa(0.0, 10000.0, 100.0)]))->toBe([]);
});

it('A: sobreposicao parcial e reprovada — o first() sem orderBy pagaria % indefinido', function () {
    // total de vendas 7.000 cai nas DUAS: pagaria 2% (=140) ou 5% (=350) conforme a
    // ordem que o MySQL devolvesse. E o nao-determinismo em VALOR que a regra mata.
    $erros = SalesTargetFaixaValidator::erros([
        stfvFaixa(0.0, 10000.0, 2.0, 'no 1'),
        stfvFaixa(5000.0, 15000.0, 5.0, 'no 2'),
    ]);

    expect($erros)->toHaveCount(1)
        ->and($erros[0])->toContain('se sobrep');
});

it('A: encostar ponta com ponta JA e sobreposicao — a query da folha e <= e >=', function () {
    // [0, 1000] e [1000, 2000]: um total de exatamente 1000 casa nas duas.
    $erros = SalesTargetFaixaValidator::erros([
        stfvFaixa(0.0, 1000.0, 1.0, 'no 1'),
        stfvFaixa(1000.0, 2000.0, 2.0, 'no 2'),
    ]);

    expect($erros)->toHaveCount(1)
        ->and($erros[0])->toContain('se sobrep');
});

it('A: faixa contida dentro de outra e reprovada', function () {
    $erros = SalesTargetFaixaValidator::erros([
        stfvFaixa(0.0, 50000.0, 1.0, 'no 1'),
        stfvFaixa(1000.0, 2000.0, 9.0, 'no 2'),
    ]);

    expect($erros)->toHaveCount(1)
        ->and($erros[0])->toContain('se sobrep');
});

it('A: erros acumulam — um conjunto ruim reporta tudo de uma vez', function () {
    $erros = SalesTargetFaixaValidator::erros([
        stfvFaixa(5000.0, 1000.0, 200.0, 'no 1'), // invertida + comissao fora
        stfvFaixa(0.0, 100.0, 1.0, 'no 2'),
    ]);

    expect($erros)->toHaveCount(2);
});

// ---------------------------------------------------------------------------
// (B) Endpoint ponta-a-ponta — MySQL real (a lane essentials-pest semeia biz=98).
// ---------------------------------------------------------------------------

beforeEach(function () {
    $this->stfvSkipHttp = true;

    if (DB::connection()->getDriverName() === 'sqlite') {
        return; // bloco (A) segue rodando; so o HTTP depende do schema UltimatePOS
    }
    if (! Schema::hasTable('essentials_user_sales_targets')) {
        return;
    }

    $tenant = static::resolveSeededTenant();
    $user = $tenant ? User::where('business_id', $tenant->id)->first() : null;
    if (! $user) {
        return;
    }
    $this->stfvUser = $user;

    // Admin#<biz> → Gate::before autoriza as clausulas de permissao do controller,
    // isolando o teste na REGRA DE FAIXA (nao no gate de permissao nem no de tenant).
    $role = Role::firstOrCreate(
        ['name' => 'Admin#'.$tenant->id, 'guard_name' => 'web'],
        ['business_id' => $tenant->id]
    );
    if (! $user->hasRole($role->name)) {
        $user->assignRole($role);
    }
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    // SetSessionData roda DEPOIS do auth e reconstroi user.business_id do usuario
    // autenticado — setar a sessao a mao aqui deixaria o bloco nao-stale e o
    // business_id chegaria nulo no controller (padrao do SalesTargetShiftCrossTenantTest).
    session()->flush();
    $this->actingAs($user);

    $this->stfvSkipHttp = false;
});

function stfvMetas(int $userId)
{
    return EssentialsUserSalesTarget::withoutGlobalScopes()->where('user_id', $userId)->get();
}

function stfvPular($ctx): void
{
    if ($ctx->stfvSkipHttp ?? true) {
        $ctx->markTestSkipped('Requer MySQL com schema UltimatePOS + tenant semeado (lane essentials-pest).');
    }
}

it('B: faixa invertida no POST → NAO grava e devolve erro', function () {
    stfvPular($this);
    $antes = stfvMetas($this->stfvUser->id)->count();

    $resp = $this->post(STFV_ROTA, [
        'user_id' => $this->stfvUser->id,
        'sales_amount_start' => ['0' => '10000'],
        'sales_amount_end' => ['0' => '5000'],
        'commission' => ['0' => '3'],
    ]);

    $resp->assertRedirect();
    expect(session('status')['success'])->toBeFalse();
    expect(stfvMetas($this->stfvUser->id)->count())->toBe($antes);
});

it('B: faixas sobrepostas no POST → NAO grava nenhuma das duas', function () {
    stfvPular($this);
    $antes = stfvMetas($this->stfvUser->id)->count();

    $resp = $this->post(STFV_ROTA, [
        'user_id' => $this->stfvUser->id,
        'sales_amount_start' => ['0' => '0', '1' => '5000'],
        'sales_amount_end' => ['0' => '10000', '1' => '15000'],
        'commission' => ['0' => '2', '1' => '5'],
    ]);

    $resp->assertRedirect();
    expect(session('status')['success'])->toBeFalse();
    expect(stfvMetas($this->stfvUser->id)->count())->toBe($antes);
});

it('B: comissao de 150% no POST → NAO grava', function () {
    stfvPular($this);
    $antes = stfvMetas($this->stfvUser->id)->count();

    $resp = $this->post(STFV_ROTA, [
        'user_id' => $this->stfvUser->id,
        'sales_amount_start' => ['0' => '0'],
        'sales_amount_end' => ['0' => '10000'],
        'commission' => ['0' => '150'],
    ]);

    $resp->assertRedirect();
    expect(session('status')['success'])->toBeFalse();
    expect(stfvMetas($this->stfvUser->id)->count())->toBe($antes);
});

it('B: entrada VALIDA grava o valor exato — a regra rejeita, nao recalcula', function () {
    stfvPular($this);

    $resp = $this->post(STFV_ROTA, [
        'user_id' => $this->stfvUser->id,
        'sales_amount_start' => ['0' => '0', '1' => '10000,01'],
        'sales_amount_end' => ['0' => '10000', '1' => '50000'],
        'commission' => ['0' => '1,5', '1' => '3'],
    ]);

    $resp->assertRedirect();
    expect(session('status')['success'])->toBeTrue();

    $metas = stfvMetas($this->stfvUser->id)->sortBy('target_start')->values();
    expect($metas)->toHaveCount(2);
    expect((float) $metas[0]->target_start)->toBe(0.0);
    expect((float) $metas[0]->target_end)->toBe(10000.0);
    expect((float) $metas[0]->commission_percent)->toBe(1.5);
    expect((float) $metas[1]->target_start)->toBe(10000.01);
    expect((float) $metas[1]->target_end)->toBe(50000.0);
    expect((float) $metas[1]->commission_percent)->toBe(3.0);
});

it('B: milhar pt-BR continua sendo lido por num_uf — a validacao nao toca a conversao', function () {
    stfvPular($this);

    // "1.500,50" e 1500,50 na heuristica pt-BR canonica (Util::num_uf) — e nao 150050.
    // Prova que validar antes de gravar nao reintroduziu o incidente de 2026-06-05.
    $resp = $this->post(STFV_ROTA, [
        'user_id' => $this->stfvUser->id,
        'sales_amount_start' => ['0' => '1.500,50'],
        'sales_amount_end' => ['0' => '25.000'],
        'commission' => ['0' => '2,25'],
    ]);

    $resp->assertRedirect();
    expect(session('status')['success'])->toBeTrue();

    $metas = stfvMetas($this->stfvUser->id);
    expect($metas)->toHaveCount(1);
    expect((float) $metas[0]->target_start)->toBe(1500.5);
    expect((float) $metas[0]->target_end)->toBe(25000.0);
    expect((float) $metas[0]->commission_percent)->toBe(2.25);
});

it('B: a linha em branco do modal (0/0/0) continua ignorada, nao vira erro', function () {
    stfvPular($this);

    // O modal Blade sempre envia uma linha vazia no fim. Se a regra a reprovasse,
    // NENHUM salvamento passaria — e a razao de o criterio de "linha ignorada" ser
    // o mesmo de antes deste PR.
    $resp = $this->post(STFV_ROTA, [
        'user_id' => $this->stfvUser->id,
        'sales_amount_start' => ['0' => '0', '1' => '0'],
        'sales_amount_end' => ['0' => '10000', '1' => '0'],
        'commission' => ['0' => '2', '1' => '0'],
    ]);

    $resp->assertRedirect();
    expect(session('status')['success'])->toBeTrue();
    expect(stfvMetas($this->stfvUser->id))->toHaveCount(1);
});
