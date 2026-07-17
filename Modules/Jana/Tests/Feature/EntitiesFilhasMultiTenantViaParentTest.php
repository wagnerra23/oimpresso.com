<?php

declare(strict_types=1);

use App\Business;
use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Jana\Entities\Conversa;
use Modules\Jana\Entities\Mensagem;
use Modules\Jana\Entities\Meta;
use Modules\Jana\Entities\MetaApuracao;
use Modules\Jana\Entities\MetaFonte;
use Modules\Jana\Entities\MetaPeriodo;
use Modules\Jana\Entities\Sugestao;

// DatabaseTransactions: o stub biz=99 (abaixo) + toda a data criada rolam
// back ao fim de cada teste — nada persiste no clone-de-prod. Este teste NÃO
// faz DDL (DROP TRIGGER etc.), então o transaction-wrap é seguro.
uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * Audit Wave 7 — Multi-tenant Tier 0 via parent (ADR 0093 IRREVOGÁVEL).
 *
 * Valida que 5 Entities filhas (sem business_id direto) APLICAM global scope
 * ScopeByBusinessViaParent corretamente:
 *
 *  - Sugestao, Mensagem      → parent = Conversa (jana_conversas.business_id)
 *  - MetaApuracao, MetaFonte, MetaPeriodo → parent = Meta (jana_metas.business_id)
 *
 * Contrato testado: query DIRETA `Sugestao::all()` autenticado como biz=1
 * NÃO retorna Sugestoes cujo conversa.business_id ≠ 1 (cross-tenant fix).
 *
 * ADR 0093 IRREVOGÁVEL (defesa em profundidade).
 * ADR 0101: usa biz=1 (Wagner WR2) e biz=99 (fictício) — NUNCA biz=4 (ROTA LIVRE).
 */

const JANA_VIA_PARENT_BIZ_WAGNER = 1;
const JANA_VIA_PARENT_BIZ_FICTICIO = 99;

beforeEach(function () {
    // SQLite guard: schema UltimatePOS (FKs business/users) só roda em MySQL real.
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: schema UltimatePOS requer MySQL (ADR 0101).');
    }
    foreach (['jana_conversas', 'jana_sugestoes', 'jana_mensagens', 'jana_metas', 'jana_meta_apuracoes', 'jana_meta_fontes', 'jana_meta_periodos'] as $tbl) {
        if (! Schema::hasTable($tbl)) {
            $this->markTestSkipped("Tabela {$tbl} ausente — rode migrate Modules/Jana primeiro.");
        }
    }

    $business = Business::find(JANA_VIA_PARENT_BIZ_WAGNER);
    if (! $business) {
        $this->markTestSkipped('business_id=1 (Wagner WR2) não encontrado — semear DB dev.');
    }
    $user = User::where('business_id', JANA_VIA_PARENT_BIZ_WAGNER)->first();
    if (! $user) {
        $this->markTestSkipped('Sem user em business_id=1.');
    }

    $this->wagnerBusiness = $business;
    $this->wagnerUser = $user;

    // biz=99 adversário (test-only, ADR 0101). O clone-de-prod do CT100 (e o seed
    // biz=1/biz=2 do pest-mysql-setup) NÃO tem biz=99 → o FK
    // jana_conversas/jana_metas.business_id → business(id) rejeita o insert das
    // Conversas/Metas fictícias (QueryException 1452) ANTES do scope rodar, e a
    // asserção de isolamento nunca chega a ser exercitada. Criamos o stub aqui
    // (rollback via DatabaseTransactions). Mesma convenção da
    // Modules/Compras/Tests/Feature/MultiTenantTest.
    if (! Business::find(JANA_VIA_PARENT_BIZ_FICTICIO)) {
        Business::forceCreate([
            'id' => JANA_VIA_PARENT_BIZ_FICTICIO,
            'name' => 'Test Biz Adversario#99 (Wave7)',
            'currency_id' => 1,
            'start_date' => now()->toDateString(),
            'default_profit_percent' => 0,
            'owner_id' => $user->id,
            // NOT NULL sem default no schema real (business) — espelha o seed
            // .github/actions/pest-mysql-setup + Compras/MultiTenantTest.
            'stop_selling_before' => 0,
            'weighing_scale_setting' => '',
            'certificado' => '',
            'officeimpresso_numerodemaquinas' => 0,
        ]);
    }
});

// ------------------------------------------------------------------
// Sugestao (jana_sugestoes — parent Conversa)
// ------------------------------------------------------------------

it('Sugestao biz=99 NÃO aparece em query direta autenticado como biz=1', function () {
    // Conversa biz=99 (fictícia, sem auth ainda)
    $convFicticia = Conversa::withoutGlobalScopes()->create([
        'business_id' => JANA_VIA_PARENT_BIZ_FICTICIO,
        'user_id'     => $this->wagnerUser->id,
        'titulo'      => 'TESTE-WAVE7 conversa biz99',
        'status'      => 'aberta',
        'iniciada_em' => now(),
    ]);

    $sugFicticia = Sugestao::withoutGlobalScopes()->create([
        'conversa_id'  => $convFicticia->id,
        'payload_json' => ['nome' => 'TESTE-WAVE7 sugestao biz99'],
    ]);

    // Conversa + Sugestao biz=1 (controle positivo)
    $convWagner = Conversa::withoutGlobalScopes()->create([
        'business_id' => JANA_VIA_PARENT_BIZ_WAGNER,
        'user_id'     => $this->wagnerUser->id,
        'titulo'      => 'TESTE-WAVE7 conversa biz1',
        'status'      => 'aberta',
        'iniciada_em' => now(),
    ]);
    $sugWagner = Sugestao::withoutGlobalScopes()->create([
        'conversa_id'  => $convWagner->id,
        'payload_json' => ['nome' => 'TESTE-WAVE7 sugestao biz1'],
    ]);

    // Auth como Wagner biz=1
    $this->actingAs($this->wagnerUser);
    session(['user.business_id' => JANA_VIA_PARENT_BIZ_WAGNER]);

    // Query DIRETA autenticado: scope global via parent filtra
    $ids = Sugestao::query()->pluck('id')->all();

    expect($ids)->toContain($sugWagner->id)
        ->and($ids)->not->toContain($sugFicticia->id);

    // Cleanup
    Sugestao::withoutGlobalScopes()->whereIn('id', [$sugWagner->id, $sugFicticia->id])->delete();
    Conversa::withoutGlobalScopes()->whereIn('id', [$convWagner->id, $convFicticia->id])->delete();
});

// ------------------------------------------------------------------
// Mensagem (jana_mensagens — parent Conversa)
// ------------------------------------------------------------------

it('Mensagem biz=99 NÃO aparece em query direta autenticado como biz=1', function () {
    $convFicticia = Conversa::withoutGlobalScopes()->create([
        'business_id' => JANA_VIA_PARENT_BIZ_FICTICIO,
        'user_id'     => $this->wagnerUser->id,
        'titulo'      => 'TESTE-WAVE7 conv-msg biz99',
        'status'      => 'aberta',
        'iniciada_em' => now(),
    ]);
    $msgFicticia = Mensagem::withoutGlobalScopes()->create([
        'conversa_id' => $convFicticia->id,
        'role'        => 'user',
        'content'     => 'TESTE-WAVE7 msg biz99',
    ]);

    $convWagner = Conversa::withoutGlobalScopes()->create([
        'business_id' => JANA_VIA_PARENT_BIZ_WAGNER,
        'user_id'     => $this->wagnerUser->id,
        'titulo'      => 'TESTE-WAVE7 conv-msg biz1',
        'status'      => 'aberta',
        'iniciada_em' => now(),
    ]);
    $msgWagner = Mensagem::withoutGlobalScopes()->create([
        'conversa_id' => $convWagner->id,
        'role'        => 'user',
        'content'     => 'TESTE-WAVE7 msg biz1',
    ]);

    $this->actingAs($this->wagnerUser);
    session(['user.business_id' => JANA_VIA_PARENT_BIZ_WAGNER]);

    $ids = Mensagem::query()->pluck('id')->all();

    expect($ids)->toContain($msgWagner->id)
        ->and($ids)->not->toContain($msgFicticia->id);

    Mensagem::withoutGlobalScopes()->whereIn('id', [$msgWagner->id, $msgFicticia->id])->delete();
    Conversa::withoutGlobalScopes()->whereIn('id', [$convWagner->id, $convFicticia->id])->delete();
});

// ------------------------------------------------------------------
// MetaApuracao + MetaFonte + MetaPeriodo (jana_meta_* — parent Meta)
// ------------------------------------------------------------------

it('MetaApuracao + MetaFonte + MetaPeriodo biz=99 NÃO aparecem autenticado como biz=1', function () {
    // Meta fictícia biz=99 + filhas
    $metaFicticia = Meta::withoutGlobalScopes()->create([
        'business_id' => JANA_VIA_PARENT_BIZ_FICTICIO,
        'slug'        => 'teste-wave7-biz99-'.uniqid(),
        'nome'        => 'TESTE-WAVE7 meta biz99',
        'unidade'     => 'BRL',
        'tipo_agregacao' => 'soma',
        'ativo'       => true,
        'origem'      => 'manual',
    ]);
    $apurFicticia = MetaApuracao::withoutGlobalScopes()->create([
        'meta_id'           => $metaFicticia->id,
        'data_ref'          => now()->toDateString(),
        'valor_realizado'   => 100,
        'calculado_em'      => now(),
        'fonte_query_hash'  => str_repeat('a', 64),
    ]);
    $fonteFicticia = MetaFonte::withoutGlobalScopes()->create([
        'meta_id'     => $metaFicticia->id,
        'driver'      => 'sql',
        'config_json' => ['sql' => 'SELECT 1'],
        'cadencia'    => 'diaria',
    ]);
    $periodoFicticio = MetaPeriodo::withoutGlobalScopes()->create([
        'meta_id'      => $metaFicticia->id,
        'tipo_periodo' => 'mes',
        'data_ini'     => now()->startOfMonth(),
        'data_fim'     => now()->endOfMonth(),
        'valor_alvo'   => 1000,
        'trajetoria'   => 'linear',
    ]);

    // Meta biz=1 + filhas (controle positivo)
    $metaWagner = Meta::withoutGlobalScopes()->create([
        'business_id' => JANA_VIA_PARENT_BIZ_WAGNER,
        'slug'        => 'teste-wave7-biz1-'.uniqid(),
        'nome'        => 'TESTE-WAVE7 meta biz1',
        'unidade'     => 'BRL',
        'tipo_agregacao' => 'soma',
        'ativo'       => true,
        'origem'      => 'manual',
    ]);
    $apurWagner = MetaApuracao::withoutGlobalScopes()->create([
        'meta_id'           => $metaWagner->id,
        'data_ref'          => now()->toDateString(),
        'valor_realizado'   => 200,
        'calculado_em'      => now(),
        'fonte_query_hash'  => str_repeat('b', 64),
    ]);
    $fonteWagner = MetaFonte::withoutGlobalScopes()->create([
        'meta_id'     => $metaWagner->id,
        'driver'      => 'sql',
        'config_json' => ['sql' => 'SELECT 2'],
        'cadencia'    => 'diaria',
    ]);
    $periodoWagner = MetaPeriodo::withoutGlobalScopes()->create([
        'meta_id'      => $metaWagner->id,
        'tipo_periodo' => 'mes',
        'data_ini'     => now()->startOfMonth(),
        'data_fim'     => now()->endOfMonth(),
        'valor_alvo'   => 2000,
        'trajetoria'   => 'linear',
    ]);

    $this->actingAs($this->wagnerUser);
    session(['user.business_id' => JANA_VIA_PARENT_BIZ_WAGNER]);

    $apuracaoIds = MetaApuracao::query()->pluck('id')->all();
    $fonteIds    = MetaFonte::query()->pluck('id')->all();
    $periodoIds  = MetaPeriodo::query()->pluck('id')->all();

    expect($apuracaoIds)->toContain($apurWagner->id)
        ->and($apuracaoIds)->not->toContain($apurFicticia->id);
    expect($fonteIds)->toContain($fonteWagner->id)
        ->and($fonteIds)->not->toContain($fonteFicticia->id);
    expect($periodoIds)->toContain($periodoWagner->id)
        ->and($periodoIds)->not->toContain($periodoFicticio->id);

    // Cleanup (ordem inversa FK)
    MetaApuracao::withoutGlobalScopes()->whereIn('id', [$apurWagner->id, $apurFicticia->id])->delete();
    MetaFonte::withoutGlobalScopes()->whereIn('id', [$fonteWagner->id, $fonteFicticia->id])->delete();
    MetaPeriodo::withoutGlobalScopes()->whereIn('id', [$periodoWagner->id, $periodoFicticio->id])->delete();
    Meta::withoutGlobalScopes()->whereIn('id', [$metaWagner->id, $metaFicticia->id])->delete();
});

// ------------------------------------------------------------------
// Eager-load (regression guard — pattern existente $conversa->sugestoes não deve quebrar)
// ------------------------------------------------------------------

it('Eager-load $conversa->sugestoes continua funcionando após scope global filha', function () {
    $convWagner = Conversa::withoutGlobalScopes()->create([
        'business_id' => JANA_VIA_PARENT_BIZ_WAGNER,
        'user_id'     => $this->wagnerUser->id,
        'titulo'      => 'TESTE-WAVE7 eager biz1',
        'status'      => 'aberta',
        'iniciada_em' => now(),
    ]);
    $sug = Sugestao::withoutGlobalScopes()->create([
        'conversa_id'  => $convWagner->id,
        'payload_json' => ['x' => 1],
    ]);

    $this->actingAs($this->wagnerUser);
    session(['user.business_id' => JANA_VIA_PARENT_BIZ_WAGNER]);

    $convReload = Conversa::query()->with('sugestoes')->find($convWagner->id);

    expect($convReload)->not->toBeNull()
        ->and($convReload->sugestoes->pluck('id')->all())->toContain($sug->id);

    Sugestao::withoutGlobalScopes()->where('id', $sug->id)->delete();
    Conversa::withoutGlobalScopes()->where('id', $convWagner->id)->delete();
});
