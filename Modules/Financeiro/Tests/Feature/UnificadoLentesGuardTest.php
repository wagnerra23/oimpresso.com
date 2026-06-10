<?php

declare(strict_types=1);

use App\Business;
use App\User;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;
use Modules\Financeiro\Models\Titulo;
use Spatie\Permission\Models\Permission;

uses(Tests\TestCase::class);

/**
 * US-FIN-029 (2026-06-10) — header "3 lentes" (Caixa · A receber · A pagar).
 *
 * GUARDs do contrato de comportamento (MWART unificado-3-lentes-visual-comparison.md
 * + charter v14). Cada Δ = CI quebra:
 *  (L1)  `?lente=` clamp default 'caixa' (sem param E valor inválido)
 *  (L2)  lente → conjunto de estados correto (receber = só tipo receber · pagar = só pagar)
 *  (L3)  chips refinam DENTRO da lente (lifecycle incompatível não vaza a lente)
 *  (L4)  Tier 0 intacto (business_id — título de outro business nunca aparece, ADR 0093)
 *  (L5)  GET é leitura pura (sem mutação de Titulo)
 *
 * Skip gracioso quando DB greenfield ou module gate bloqueia (padrão dos GUARDs Unificado).
 * Limpeza via DB raw (fin_titulos bloqueia delete por DomainException — padrão uniCleanup).
 */

function lentesBootstrap(): User
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

    return $user;
}

function lentesTitulo(int $businessId, int $userId, array $overrides = []): Titulo
{
    $dia = now()->toDateString();

    return Titulo::create(array_merge([
        'business_id'       => $businessId,
        'numero'            => 'LEN-'.bin2hex(random_bytes(5)),
        'tipo'              => 'receber',
        'status'            => 'aberto',
        'cliente_descricao' => 'GUARD lentes US-FIN-029',
        'valor_total'       => 100.0,
        'valor_aberto'      => 100.0,
        'moeda'             => 'BRL',
        'emissao'           => $dia,
        'vencimento'        => $dia,
        'competencia_mes'   => now()->format('Y-m'),
        'origem'            => 'manual',
        'created_by'        => $userId,
    ], $overrides));
}

function lentesCleanup(int ...$tituloIds): void
{
    if (empty($tituloIds)) {
        return;
    }
    DB::table('fin_titulo_baixas')->whereIn('titulo_id', $tituloIds)->delete();
    DB::table('fin_titulos')->whereIn('id', $tituloIds)->delete();
}

function lentesGet(User $user, string $qs = '')
{
    // per_page alto: GUARDs asseram presença na lista — não podem cair em página 2.
    $sep = str_contains($qs, '?') ? '&' : '?';
    $response = test()->actingAs($user)->get('/financeiro/unificado'.$qs.$sep.'per_page=500');

    if (in_array($response->status(), [403, 404], true)) {
        test()->markTestSkipped('Module gate bloqueia neste env.');
    }

    return $response;
}

function lentesIds($response)
{
    $ids = null;
    $response->assertInertia(function (AssertableInertia $page) use (&$ids) {
        $ids = collect($page->toArray()['props']['lancamentos'] ?? [])->pluck('id');
    });

    return $ids ?? collect();
}

it('GUARD L1: sem ?lente= o clamp devolve caixa (default)', function () {
    $user = lentesBootstrap();

    lentesGet($user)->assertInertia(fn (AssertableInertia $page) => $page
        ->where('filters.lente', 'caixa')
    );
});

it('GUARD L1b: ?lente=invalida cai pra caixa (sanitização anti-tampering)', function () {
    $user = lentesBootstrap();

    lentesGet($user, '?lente=fiscal')->assertInertia(fn (AssertableInertia $page) => $page
        ->where('filters.lente', 'caixa')
    );
    lentesGet($user, '?lente=')->assertInertia(fn (AssertableInertia $page) => $page
        ->where('filters.lente', 'caixa')
    );
});

it('GUARD L2: lente=receber traz só tipo receber · lente=pagar só pagar', function () {
    $user = lentesBootstrap();
    $bizId = (int) $user->business_id;

    $rec = lentesTitulo($bizId, $user->id, ['tipo' => 'receber']);
    $pay = lentesTitulo($bizId, $user->id, ['tipo' => 'pagar']);

    try {
        $idsReceber = lentesIds(lentesGet($user, '?lente=receber'));
        expect($idsReceber)->toContain($rec->id);
        expect($idsReceber)->not->toContain($pay->id);

        $idsPagar = lentesIds(lentesGet($user, '?lente=pagar'));
        expect($idsPagar)->toContain($pay->id);
        expect($idsPagar)->not->toContain($rec->id);
    } finally {
        lentesCleanup($rec->id, $pay->id);
    }
});

it('GUARD L3: chip incompatível com a lente não vaza (interseção lifecycle∩lente)', function () {
    $user = lentesBootstrap();
    $bizId = (int) $user->business_id;

    $rec = lentesTitulo($bizId, $user->id, ['tipo' => 'receber']);
    $pay = lentesTitulo($bizId, $user->id, ['tipo' => 'pagar']);

    try {
        // lifecycle=ap (a pagar) é incompatível com lente=receber → backend usa a lente
        // inteira (ar+re); título 'pagar' NUNCA pode vazar pra dentro da lente receber.
        $ids = lentesIds(lentesGet($user, '?lente=receber&lifecycle=ap'));
        expect($ids)->toContain($rec->id);
        expect($ids)->not->toContain($pay->id);
    } finally {
        lentesCleanup($rec->id, $pay->id);
    }
});

it('GUARD L4: Tier 0 — título de outro business nunca aparece em nenhuma lente (ADR 0093)', function () {
    $user = lentesBootstrap();

    $outroBiz = Business::where('id', '!=', $user->business_id)->first();
    if (! $outroBiz) {
        test()->markTestSkipped('Só 1 business no banco — cross-tenant não testável aqui.');
    }

    $alheio = lentesTitulo((int) $outroBiz->id, $user->id, ['tipo' => 'receber']);

    try {
        foreach (['caixa', 'receber', 'pagar'] as $lente) {
            $ids = lentesIds(lentesGet($user, '?lente='.$lente));
            expect($ids)->not->toContain($alheio->id);
        }
    } finally {
        lentesCleanup($alheio->id);
    }
});

it('GUARD L5: GET com lente é leitura pura — nenhum Titulo mutado', function () {
    $user = lentesBootstrap();
    $bizId = (int) $user->business_id;

    $t = lentesTitulo($bizId, $user->id, ['tipo' => 'receber']);

    try {
        $countAntes = Titulo::count();
        $stampAntes = $t->fresh()->updated_at?->toIso8601String();

        lentesGet($user, '?lente=receber');
        lentesGet($user, '?lente=pagar&lifecycle=ap');

        expect(Titulo::count())->toBe($countAntes);
        expect($t->fresh()->updated_at?->toIso8601String())->toBe($stampAntes);
    } finally {
        lentesCleanup($t->id);
    }
});
