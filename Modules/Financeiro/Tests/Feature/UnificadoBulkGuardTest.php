<?php

declare(strict_types=1);
// @covers-us US-FIN-031

use App\Business;
use App\User;
use Illuminate\Support\Facades\DB;
use Modules\Financeiro\Models\Categoria;
use Modules\Financeiro\Models\ContaBancaria;
use Modules\Financeiro\Models\PlanoConta;
use Modules\Financeiro\Models\Titulo;
use Modules\Financeiro\Models\TituloBaixa;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;

uses(Tests\TestCase::class);

/**
 * US-FIN-031 (Onda 25) — GUARDs do endpoint bulk genérico da Visão Unificada.
 * POST /financeiro/unificado/bulk · {action, ids[], payload{}}
 *
 * Contrato (âncoras): SPEC.md#US-FIN-031 (AC) + Index.charter.md v17 +
 * memory/proibicoes.md REGRA MESTRE valor (dupla confirmação numérica) +
 * ADR 0093 multi-tenant Tier 0.
 *
 * Cobre:
 *  (G1/UC-F04) cross-tenant: 1 id de outro business no lote → 422 e NADA aplica
 *  (G2/UC-F04) baixar em lote: quitação total, soma provada por 2 caminhos
 *              (fin_titulo_baixas criadas × total do audit trail) — REGRA MESTRE
 *  (G3/UC-F04) cancelar em lote: append-only status='cancelado', quitado é pulado
 *  (G4)        plano_conta em lote persiste; plano cross-tenant rejeitado
 *  (G5)        limite 501 ids → 422
 *  (G6)        exportar_csv devolve text/csv com os títulos e não muta nada
 */

function blkBootstrap(): array
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

function blkCreateTitulo(int $businessId, int $userId, float $valor = 100.0, string $status = 'aberto'): Titulo
{
    return Titulo::create([
        'business_id'       => $businessId,
        'numero'            => 'BLK-'.bin2hex(random_bytes(4)),
        'tipo'              => 'receber',
        'status'            => $status,
        'cliente_descricao' => 'BULK guard',
        'valor_total'       => $valor,
        'valor_aberto'      => $status === 'quitado' ? 0 : $valor,
        'moeda'             => 'BRL',
        'emissao'           => now()->toDateString(),
        'vencimento'        => now()->addDays(10)->toDateString(),
        'competencia_mes'   => now()->format('Y-m'),
        'origem'            => 'manual',
        'created_by'        => $userId,
    ]);
}

/** Cleanup raw: fin_titulos não permite delete (DomainException no model). */
function blkCleanup(Titulo ...$titulos): void
{
    foreach ($titulos as $t) {
        DB::table('fin_titulo_baixas')->where('titulo_id', $t->id)->delete();
        DB::table('fin_titulos')->where('id', $t->id)->delete();
    }
}

function blkCleanupAudit(int $businessId): void
{
    DB::table('activity_log')
        ->where('business_id', $businessId)
        ->where('description', 'like', 'bulk_%')
        ->delete();
}

/** Business B ficcional pro cenário cross-tenant (padrão UnificadoPlanoContaGuardTest G7). */
function blkOtherBiz(int $ownerId): int
{
    $otherBizId = (int) (DB::table('business')->max('id') ?? 0) + 77777;
    DB::table('business')->insert([
        'id'          => $otherBizId,
        'name'        => 'BULK-GUARD-OTHER-BIZ',
        'currency_id' => 1,
        'owner_id'    => $ownerId, // FK business_owner_id_foreign é NOT NULL neste schema
        'start_date'  => now()->toDateString(),
        'created_at'  => now(),
        'updated_at'  => now(),
    ]);

    return $otherBizId;
}

// G1 — Tier 0: id de outro tenant no lote rejeita o lote INTEIRO (422, nada aplica)
it('UC-F04 GUARD G1: lote com id de outro business é rejeitado inteiro (422)', function () {
    [$business, $user] = blkBootstrap();
    $meu = blkCreateTitulo($business->id, $user->id, 100.0);
    $otherBizId = blkOtherBiz($user->id);
    $alheio = blkCreateTitulo($otherBizId, $user->id, 55.0);
    $categoria = Categoria::where('business_id', $business->id)->first();

    $resp = $this->actingAs($user)->postJson('/financeiro/unificado/bulk', [
        'action'  => 'categoria',
        'ids'     => [$meu->id, $alheio->id],
        'payload' => ['categoria_id' => $categoria?->id ?? 1],
    ]);

    if (in_array($resp->status(), [403, 404], true)) {
        blkCleanup($meu, $alheio);
        DB::table('business')->where('id', $otherBizId)->delete();
        test()->markTestSkipped('Module gate bloqueia neste env.');
    }

    expect($resp->status())->toBe(422, 'Lote cross-tenant deveria ser 422 — VIOLAÇÃO TIER 0');

    // NADA aplicou — nem no meu, nem no alheio.
    $meu->refresh();
    expect($meu->categoria_id)->toBeNull('422 mas aplicou categoria no título do lote — fail-closed violado');
    $alheioFresh = Titulo::withoutGlobalScopes()->find($alheio->id); // SUPERADMIN: assert cross-tenant no teste
    expect($alheioFresh->categoria_id)->toBeNull();

    blkCleanup($meu, $alheio);
    blkCleanupAudit($business->id);
    DB::table('business')->where('id', $otherBizId)->delete();
});

// G2 — REGRA MESTRE valor: baixa em lote provada por DOIS caminhos independentes
it('UC-F04 GUARD G2: baixar em lote quita e a soma bate por 2 caminhos (baixas × audit)', function () {
    [$business, $user] = blkBootstrap();
    $conta = ContaBancaria::where('business_id', $business->id)->orderBy('id')->first();
    if (! $conta) {
        test()->markTestSkipped('Sem conta bancária no business.');
    }
    $t1 = blkCreateTitulo($business->id, $user->id, 100.00);
    $t2 = blkCreateTitulo($business->id, $user->id, 50.50);
    blkCleanupAudit($business->id);

    $resp = $this->actingAs($user)->post('/financeiro/unificado/bulk', [
        'action' => 'baixar',
        'ids'    => [$t1->id, $t2->id],
    ]);
    if (in_array($resp->status(), [403, 404], true)) {
        blkCleanup($t1, $t2);
        test()->markTestSkipped('Module gate bloqueia neste env.');
    }

    $t1->refresh();
    $t2->refresh();
    expect($t1->status)->toBe('quitado');
    expect($t2->status)->toBe('quitado');
    expect((float) $t1->valor_aberto)->toBe(0.0);
    expect((float) $t2->valor_aberto)->toBe(0.0);

    // Caminho 1: soma das baixas criadas no banco = 100.00 + 50.50 = 150.50
    $somaBaixas = (float) TituloBaixa::whereIn('titulo_id', [$t1->id, $t2->id])->sum('valor_baixa');
    expect(round($somaBaixas, 2))->toBe(150.50);

    // Caminho 2 (independente): total gravado no audit trail do lote = 150.50
    $audit = Activity::query()
        ->where('business_id', $business->id)
        ->where('description', 'bulk_baixar')
        ->latest('id')
        ->first();
    expect($audit)->not->toBeNull('AC US-FIN-031: audit trail do bulk não foi gravado');
    $props = is_array($audit->properties) ? $audit->properties : $audit->properties->toArray();
    expect((int) $props['count'])->toBe(2);
    expect(round((float) $props['total'], 2))->toBe(150.50);
    expect($props['ids'])->toContain($t1->id);
    expect((int) $audit->causer_id)->toBe($user->id);

    blkCleanup($t1, $t2);
    blkCleanupAudit($business->id);
});

// G3 — cancelar em lote: append-only + quitado é pulado
it('UC-F04 GUARD G3: cancelar em lote marca cancelado (append-only) e pula quitado', function () {
    [$business, $user] = blkBootstrap();
    $aberto = blkCreateTitulo($business->id, $user->id, 80.0);
    $quitado = blkCreateTitulo($business->id, $user->id, 40.0, 'quitado');
    blkCleanupAudit($business->id);

    $resp = $this->actingAs($user)->post('/financeiro/unificado/bulk', [
        'action' => 'cancelar',
        'ids'    => [$aberto->id, $quitado->id],
    ]);
    if (in_array($resp->status(), [403, 404], true)) {
        blkCleanup($aberto, $quitado);
        test()->markTestSkipped('Module gate bloqueia neste env.');
    }

    $aberto->refresh();
    $quitado->refresh();
    expect($aberto->status)->toBe('cancelado', 'Cancelar em lote não aplicou no título aberto');
    expect($quitado->status)->toBe('quitado', 'Cancelar em lote NÃO pode tocar título quitado (estorno é outro fluxo)');
    // Append-only: registro continua existindo (nunca delete — Non-Goal charter).
    expect(DB::table('fin_titulos')->where('id', $aberto->id)->exists())->toBeTrue();

    $audit = Activity::query()
        ->where('business_id', $business->id)
        ->where('description', 'bulk_cancelar')
        ->latest('id')
        ->first();
    expect($audit)->not->toBeNull();
    $props = is_array($audit->properties) ? $audit->properties : $audit->properties->toArray();
    expect((int) $props['count'])->toBe(1);
    expect(round((float) $props['total'], 2))->toBe(80.0);

    blkCleanup($aberto, $quitado);
    blkCleanupAudit($business->id);
});

// G4 — plano_conta em lote persiste; plano cross-tenant rejeitado
it('GUARD G4: plano de contas em lote persiste e rejeita plano cross-tenant', function () {
    [$business, $user] = blkBootstrap();
    $titulo = blkCreateTitulo($business->id, $user->id, 60.0);
    $plano = PlanoConta::firstOrCreate(
        ['business_id' => $business->id, 'codigo' => '9.9.99.BLK'],
        [
            'nome'              => 'BULK guard plano',
            'tipo'              => 'receita',
            'nivel'             => 4,
            'natureza'          => 'credito',
            'aceita_lancamento' => true,
            'protegido'         => false,
            'ativo'             => true,
        ],
    );

    $resp = $this->actingAs($user)->post('/financeiro/unificado/bulk', [
        'action'  => 'plano_conta',
        'ids'     => [$titulo->id],
        'payload' => ['plano_conta_id' => $plano->id],
    ]);
    if (in_array($resp->status(), [403, 404], true)) {
        blkCleanup($titulo);
        test()->markTestSkipped('Module gate bloqueia neste env.');
    }

    $titulo->refresh();
    expect($titulo->plano_conta_id)->toBe($plano->id);

    // Plano de OUTRO business → 422 e não altera.
    $otherBizId = blkOtherBiz($user->id);
    $planoAlheio = PlanoConta::create([
        'business_id'       => $otherBizId,
        'codigo'            => '9.9.99.BXT',
        'nome'              => 'BULK cross plano',
        'tipo'              => 'receita',
        'nivel'             => 4,
        'natureza'          => 'credito',
        'aceita_lancamento' => true,
        'protegido'         => false,
        'ativo'             => true,
    ]);
    $resp2 = $this->actingAs($user)->postJson('/financeiro/unificado/bulk', [
        'action'  => 'plano_conta',
        'ids'     => [$titulo->id],
        'payload' => ['plano_conta_id' => $planoAlheio->id],
    ]);
    expect($resp2->status())->toBe(422, 'Plano cross-tenant aceito — VIOLAÇÃO TIER 0');
    $titulo->refresh();
    expect($titulo->plano_conta_id)->toBe($plano->id, 'Plano cross-tenant sobrescreveu o legítimo');

    blkCleanup($titulo);
    blkCleanupAudit($business->id);
    $planoAlheio->forceDelete();
    DB::table('business')->where('id', $otherBizId)->delete();
});

// G5 — limite 500 por chamada
it('GUARD G5: lote com 501 ids é rejeitado (422 — limite 500)', function () {
    [, $user] = blkBootstrap();

    $resp = $this->actingAs($user)->postJson('/financeiro/unificado/bulk', [
        'action' => 'categoria',
        'ids'    => range(1, 501),
        'payload' => ['categoria_id' => 1],
    ]);
    if (in_array($resp->status(), [403, 404], true)) {
        test()->markTestSkipped('Module gate bloqueia neste env.');
    }

    expect($resp->status())->toBe(422);
});

// G6 — exportar_csv devolve CSV e não muta
it('GUARD G6: exportar_csv devolve text/csv com o título e não muta nada', function () {
    [$business, $user] = blkBootstrap();
    $titulo = blkCreateTitulo($business->id, $user->id, 33.0);

    $resp = $this->actingAs($user)->post('/financeiro/unificado/bulk', [
        'action' => 'exportar_csv',
        'ids'    => [$titulo->id],
    ]);
    // streamDownload devolve StreamedResponse (Symfony) — usa getStatusCode(),
    // não o status() do Illuminate\Http\Response.
    $status = $resp->baseResponse->getStatusCode();
    if (in_array($status, [403, 404], true)) {
        blkCleanup($titulo);
        test()->markTestSkipped('Module gate bloqueia neste env.');
    }

    expect($status)->toBe(200);
    expect((string) $resp->headers->get('content-type'))->toContain('text/csv');
    $csv = $resp->streamedContent();
    expect($csv)->toContain($titulo->numero);
    expect($csv)->toContain('Valor aberto');

    $titulo->refresh();
    expect($titulo->status)->toBe('aberto', 'Export não pode mutar o título');
    expect((float) $titulo->valor_aberto)->toBe(33.0);

    blkCleanup($titulo);
    blkCleanupAudit($business->id);
});
