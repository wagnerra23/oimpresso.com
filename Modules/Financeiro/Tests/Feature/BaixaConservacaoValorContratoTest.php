<?php

declare(strict_types=1);

use App\Business;
use App\User;
use Illuminate\Support\Facades\DB;
use Modules\Financeiro\Models\ContaBancaria;
use Modules\Financeiro\Models\Titulo;
use Modules\Financeiro\Models\TituloBaixa;
use Spatie\Permission\Models\Permission;

uses(Tests\TestCase::class);

/**
 * CONTRATO da baixa de título — SDD Financeiro §6.1 (CU-FIN-02..05).
 *
 * Derivado do CONTRATO (SDD §6 + US-FIN-003 + charter Unificado v12 + decisão [W]
 * 2026-06-04 "baixa parcial vira SPLIT"), NÃO da implementação — a asserção central
 * é a INVARIANTE DE CONSERVAÇÃO de valor, não o nome de campo que a realiza:
 *
 *     Σ(filhos.valor_total) + pai.valor_aberto  ==  valor original do pai
 *
 * Essa invariante segue verdadeira se amanhã o split virar outra estrutura; um assert
 * em `status == 'parcial'` (o que o casos.md antigo pedia) já estaria FALSO hoje.
 *
 * ⚠️ REGRA MESTRE valor ([memory/proibicoes.md]) — Tier 0. Cada UC prova o valor por
 * DOIS caminhos independentes:
 *   (a) a soma dos títulos (pai + filhos), e
 *   (b) a soma das linhas de baixa em fin_titulo_baixas.
 * Os dois têm que fechar no MESMO número; um só não vale.
 *
 * ⚠️ ANTI-VÁCUO ([memory/proibicoes.md] §5 2026-07-24): "preservou X" medido sem provar
 * que a operação ACONTECEU mede não-execução. Por isso todo caso chama
 * `exigeQueTenhaBaixado()` ANTES de asserir a invariante — sem prova de que a baixa
 * gravou, o teste FALHA em vez de passar no vácuo.
 *
 * @covers-us US-FIN-003
 */

/** Bootstrap de sessão/permissão (nomes prefixados `cv` — funções Pest são globais). */
function cvBootstrap(): array
{
    try {
        $business = Business::first();
    } catch (\Throwable $e) {
        test()->markTestSkipped('Tabela business indisponível: '.$e->getMessage());
    }
    if (! $business) {
        test()->markTestSkipped('Sem business no banco.');
    }
    $user = User::where('business_id', $business->id)->first();
    if (! $user) {
        test()->markTestSkipped('Sem user no business.');
    }
    Permission::firstOrCreate(['name' => 'financeiro.dashboard.view', 'guard_name' => 'web']);
    if (! $user->hasPermissionTo('financeiro.dashboard.view')) {
        $user->givePermissionTo('financeiro.dashboard.view');
    }
    session([
        'user.business_id' => $business->id,
        'user.id'          => $user->id,
        'business.id'      => $business->id,
        'business.name'    => $business->name,
        'business'         => ['id' => $business->id, 'name' => $business->name, 'currency_symbol' => 'R$'],
        'is_admin'         => true,
    ]);

    return [$business, $user];
}

function cvConta(int $businessId): ?ContaBancaria
{
    return ContaBancaria::where('business_id', $businessId)->orderBy('id')->first();
}

function cvTitulo(int $businessId, int $userId, float $valor): Titulo
{
    return Titulo::create([
        'business_id'       => $businessId,
        'numero'            => 'CV-'.bin2hex(random_bytes(4)),
        'tipo'              => 'receber',
        'status'            => 'aberto',
        'cliente_descricao' => 'CONTRATO conservacao',
        'valor_total'       => $valor,
        'valor_aberto'      => $valor,
        'moeda'             => 'BRL',
        'emissao'           => now()->toDateString(),
        'vencimento'        => now()->addDays(10)->toDateString(),
        'competencia_mes'   => now()->format('Y-m'),
        'origem'            => 'manual',
        'created_by'        => $userId,
    ]);
}

/** Remove pai + filhos + baixas (fin_titulos recusa forceDelete — DomainException). */
function cvCleanup(Titulo $pai): void
{
    $ids = Titulo::where('titulo_pai_id', $pai->id)->pluck('id')->all();
    $ids[] = $pai->id;
    DB::table('fin_titulo_baixas')->whereIn('titulo_id', $ids)->delete();
    DB::table('fin_titulos')->whereIn('id', $ids)->delete();
}

/**
 * ANTI-VÁCUO: prova que a operação de baixa realmente gravou algo pro título
 * (ou pra algum filho dele) antes de qualquer asserção sobre conservação.
 */
function cvExigeQueTenhaBaixado(Titulo $pai): float
{
    $ids = Titulo::where('titulo_pai_id', $pai->id)->pluck('id')->all();
    $ids[] = $pai->id;
    $linhas = TituloBaixa::whereIn('titulo_id', $ids)->get();

    expect($linhas->count())->toBeGreaterThan(
        0,
        'ANTI-VÁCUO: nenhuma linha em fin_titulo_baixas — a baixa não executou, '
        .'logo a invariante abaixo seria vacuamente verdadeira.'
    );

    return (float) $linhas->sum(fn ($l) => (float) $l->valor_baixa);
}

// ---------------------------------------------------------------------------
// UC-FUNI-01 · CU-FIN-02 — baixa parcial faz SPLIT e CONSERVA o valor [V0]
// ---------------------------------------------------------------------------
it('UC-FUNI-01 · baixa parcial conserva o valor no split (2 caminhos: títulos e baixas)', function () {
    [$business, $user] = cvBootstrap();
    $conta = cvConta($business->id);
    if (! $conta) {
        test()->markTestSkipped('Sem conta bancária semeada no business.');
    }

    $original = 100.00;
    $baixa    = 37.45; // centavos de propósito — o bug de valor mora no arredondamento
    $titulo   = cvTitulo($business->id, $user->id, $original);

    $response = $this->actingAs($user)->post("/financeiro/unificado/{$titulo->id}/baixar", [
        'valor_baixa'       => $baixa,
        'conta_bancaria_id' => $conta->id,
        'meio_pagamento'    => 'pix',
    ]);
    if (in_array($response->status(), [403, 404], true)) {
        cvCleanup($titulo);
        test()->markTestSkipped('Module gate bloqueia neste env.');
    }

    // (b) CAMINHO 2 — soma das linhas de baixa. Roda ANTES por ser o anti-vácuo.
    $somaBaixas = cvExigeQueTenhaBaixado($titulo);

    // (a) CAMINHO 1 — soma dos títulos (pai que sobrou + filhos gerados).
    $titulo->refresh();
    $filhos    = Titulo::where('titulo_pai_id', $titulo->id)->get();
    $somaTitulos = round(
        (float) $filhos->sum(fn ($f) => (float) $f->valor_total) + (float) $titulo->valor_aberto,
        2
    );

    // INVARIANTE DE CONSERVAÇÃO — nada some, nada nasce do nada.
    expect($somaTitulos)->toBe(round($original, 2), 'conservação quebrou: Σ(filhos)+pai ≠ original');

    // Os DOIS caminhos têm que fechar no mesmo recebido.
    expect(round($somaBaixas, 2))->toBe(round($baixa, 2), 'as linhas de baixa não somam o valor recebido');
    expect(round($original - (float) $titulo->valor_aberto, 2))
        ->toBe(round($baixa, 2), 'o que saiu do aberto do pai ≠ o que as baixas registram');

    // O pai NÃO quita (segue devendo o restante) — contrato do split.
    expect($titulo->status)->not->toBe('quitado');

    cvCleanup($titulo);
});

// ---------------------------------------------------------------------------
// UC-FUNI-02 · CU-FIN-03 — baixa nunca excede o aberto (clamp superior) [V0]
// ---------------------------------------------------------------------------
it('UC-FUNI-02 · baixa acima do aberto quita exatamente o aberto (sem crédito nem negativo)', function () {
    [$business, $user] = cvBootstrap();
    $conta = cvConta($business->id);
    if (! $conta) {
        test()->markTestSkipped('Sem conta bancária semeada no business.');
    }

    $original = 80.00;
    $titulo   = cvTitulo($business->id, $user->id, $original);

    $response = $this->actingAs($user)->post("/financeiro/unificado/{$titulo->id}/baixar", [
        'valor_baixa'       => 999999.99, // muito acima do devido
        'conta_bancaria_id' => $conta->id,
        'meio_pagamento'    => 'dinheiro',
    ]);
    if (in_array($response->status(), [403, 404], true)) {
        cvCleanup($titulo);
        test()->markTestSkipped('Module gate bloqueia neste env.');
    }

    $somaBaixas = cvExigeQueTenhaBaixado($titulo);
    $titulo->refresh();

    expect(round($somaBaixas, 2))->toBe(round($original, 2), 'baixou MAIS que o devido');
    expect((float) $titulo->valor_aberto)->toBe(0.0);
    expect((float) $titulo->valor_aberto)->toBeGreaterThanOrEqual(0.0, 'aberto ficou negativo');
    // Excesso não pode virar título-filho de crédito.
    expect(Titulo::where('titulo_pai_id', $titulo->id)->count())->toBe(0);

    cvCleanup($titulo);
});

// ---------------------------------------------------------------------------
// UC-FUNI-03 · CU-FIN-04 — quitado/cancelado recusa baixa (append-only contábil)
// ---------------------------------------------------------------------------
it('UC-FUNI-03 · título cancelado recusa baixa e não cria nenhuma linha contábil', function () {
    [$business, $user] = cvBootstrap();
    $conta = cvConta($business->id);
    if (! $conta) {
        test()->markTestSkipped('Sem conta bancária semeada no business.');
    }

    $titulo = cvTitulo($business->id, $user->id, 50.00);
    $titulo->fill(['status' => 'cancelado'])->save();

    $response = $this->actingAs($user)->post("/financeiro/unificado/{$titulo->id}/baixar", [
        'valor_baixa'       => 50.00,
        'conta_bancaria_id' => $conta->id,
        'meio_pagamento'    => 'pix',
    ]);
    if (in_array($response->status(), [403, 404], true)) {
        cvCleanup($titulo);
        test()->markTestSkipped('Module gate bloqueia neste env.');
    }

    $titulo->refresh();
    expect(TituloBaixa::where('titulo_id', $titulo->id)->count())->toBe(0, 'gravou baixa em título cancelado');
    expect($titulo->status)->toBe('cancelado');
    expect((float) $titulo->valor_aberto)->toBe(50.0, 'mexeu no aberto de título cancelado');

    cvCleanup($titulo);
});

// ---------------------------------------------------------------------------
// UC-FUNI-04 · CU-FIN-05 — conta de OUTRO business é recusada (Tier 0, fail-closed)
// ---------------------------------------------------------------------------
it('UC-FUNI-04 · Tier 0: conta bancária de outro business é recusada sem gravar baixa', function () {
    [$business, $user] = cvBootstrap();

    $contaAlheia = ContaBancaria::where('business_id', '!=', $business->id)->orderBy('id')->first();
    if (! $contaAlheia) {
        test()->markTestSkipped('Sem conta bancária de outro business pra provar cross-tenant.');
    }

    $titulo = cvTitulo($business->id, $user->id, 120.00);

    $response = $this->actingAs($user)->post("/financeiro/unificado/{$titulo->id}/baixar", [
        'valor_baixa'       => 120.00,
        'conta_bancaria_id' => $contaAlheia->id,
        'meio_pagamento'    => 'pix',
    ]);
    if (in_array($response->status(), [403, 404], true)) {
        cvCleanup($titulo);
        test()->markTestSkipped('Module gate bloqueia neste env.');
    }

    $titulo->refresh();
    expect(TituloBaixa::where('titulo_id', $titulo->id)->count())->toBe(0, 'gravou baixa com conta alheia');
    expect((float) $titulo->valor_aberto)->toBe(120.0);
    expect($titulo->status)->toBe('aberto');

    cvCleanup($titulo);
});
