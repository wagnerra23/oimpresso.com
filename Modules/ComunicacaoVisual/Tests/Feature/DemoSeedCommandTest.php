<?php

declare(strict_types=1);

use App\Business;
use App\User;
use Illuminate\Support\Facades\DB;
use Modules\ComunicacaoVisual\Entities\Apontamento;
use Modules\ComunicacaoVisual\Entities\Orcamento;
use Modules\ComunicacaoVisual\Entities\OrcamentoItem;
use Modules\ComunicacaoVisual\Entities\Os;

uses(Tests\TestCase::class);

/**
 * Testes do comvis:demo-seed — seeder demo end-to-end.
 *
 * Valida: registro de command, validação de args, criação de entidades,
 * auto-seed de materiais, comportamento --clean e isolamento multi-tenant.
 *
 * Tests biz=1 (Wagner WR2) conforme ADR 0101 — nunca biz=4 (cliente ROTA LIVRE).
 * Multi-tenant Tier 0 (ADR 0093): business_id explícito via --business.
 *
 * @see Modules\ComunicacaoVisual\Console\Commands\DemoSeedCommand
 * @see memory/requisitos/ComunicacaoVisual/SPEC.md
 * @see memory/decisions/0101-tests-business-id-1-nunca-cliente.md
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 */

/** Marker usado pelo DemoSeedCommand para identificar dados de demo. */
const DEMO_MARKER_TEST = '[CV-DEMO]';

/**
 * Limpa todos os dados de demo criados para o business indicado.
 * Respeita ordem FK: apontamentos → itens → OS → orçamentos.
 */
function limparDemoTest(int $bizId): void
{
    $osIds = DB::table('comvis_os')
        ->where('business_id', $bizId)
        ->where('observacoes', 'like', '%[CV-DEMO]%')
        ->pluck('id');

    if ($osIds->isNotEmpty()) {
        DB::table('comvis_apontamentos')
            ->whereIn('os_id', $osIds)
            ->delete();
    }

    $orcIds = DB::table('comvis_orcamentos')
        ->where('business_id', $bizId)
        ->where('observacoes', 'like', '%[CV-DEMO]%')
        ->pluck('id');

    if ($orcIds->isNotEmpty()) {
        DB::table('comvis_orcamento_itens')
            ->whereIn('orcamento_id', $orcIds)
            ->delete();
    }

    DB::table('comvis_os')
        ->where('business_id', $bizId)
        ->where('observacoes', 'like', '%[CV-DEMO]%')
        ->delete();

    DB::table('comvis_orcamentos')
        ->where('business_id', $bizId)
        ->where('observacoes', 'like', '%[CV-DEMO]%')
        ->delete();
}

/**
 * Bootstrap do ambiente de teste: verifica business=1 e limpa demo anterior.
 * Retorna o business ou skippa o teste se DB não está disponível.
 */
function bootstrapDemoTest(): Business
{
    try {
        $business = Business::find(1) ?? Business::first();
    } catch (\Throwable $e) {
        test()->markTestSkipped('Tabela business indisponível: ' . $e->getMessage());
    }

    if (! $business) {
        test()->markTestSkipped('Sem business no banco — rode seeder UltimatePOS antes.');
    }

    limparDemoTest($business->id);

    return $business;
}

// ------------------------------------------------------------------
// Teste 1: command registrado em artisan list
// ------------------------------------------------------------------

it('comvis:demo-seed está registrado em artisan list', function () {
    try {
        $output = \Illuminate\Support\Facades\Artisan::all();
    } catch (\Throwable $e) {
        test()->markTestSkipped('Artisan indisponível: ' . $e->getMessage());
    }

    expect(array_key_exists('comvis:demo-seed', $output))
        ->toBeTrue('comvis:demo-seed deve estar registrado no artisan');
});

// ------------------------------------------------------------------
// Teste 2: --business ausente → exit code 1 + mensagem PT-BR clara
// ------------------------------------------------------------------

it('sem --business retorna exit 1 e mensagem PT-BR clara', function () {
    $exitCode = \Illuminate\Support\Facades\Artisan::call('comvis:demo-seed');

    expect($exitCode)->toBe(1);

    $output = \Illuminate\Support\Facades\Artisan::output();
    expect($output)->toContain('--business é obrigatório');
});

// ------------------------------------------------------------------
// Teste 3: Cria 1 orçamento + 3 itens + 1 OS + 1 apontamento
// ------------------------------------------------------------------

it('cria 1 orçamento + 3 itens + 1 OS + 1 apontamento para biz=1', function () {
    $business = bootstrapDemoTest();
    $bizId    = $business->id;

    $exitCode = \Illuminate\Support\Facades\Artisan::call('comvis:demo-seed', [
        '--business' => $bizId,
    ]);

    expect($exitCode)->toBe(0);

    // Orçamento criado com marker
    $orcamentos = DB::table('comvis_orcamentos')
        ->where('business_id', $bizId)
        ->where('observacoes', 'like', '%[CV-DEMO]%')
        ->count();
    expect($orcamentos)->toBe(1);

    // 3 itens no orçamento
    $orcId = DB::table('comvis_orcamentos')
        ->where('business_id', $bizId)
        ->where('observacoes', 'like', '%[CV-DEMO]%')
        ->value('id');

    $itens = DB::table('comvis_orcamento_itens')
        ->where('orcamento_id', $orcId)
        ->where('business_id', $bizId)
        ->count();
    expect($itens)->toBe(3);

    // 1 OS criada com marker
    $osCount = DB::table('comvis_os')
        ->where('business_id', $bizId)
        ->where('observacoes', 'like', '%[CV-DEMO]%')
        ->count();
    expect($osCount)->toBe(1);

    // 1 apontamento vinculado à OS
    $osId = DB::table('comvis_os')
        ->where('business_id', $bizId)
        ->where('observacoes', 'like', '%[CV-DEMO]%')
        ->value('id');

    $apontamentos = DB::table('comvis_apontamentos')
        ->where('os_id', $osId)
        ->count();
    expect($apontamentos)->toBe(1);

    // Apontamento tem duração ≈ 90min (5400s ± 5s tolerância de execução)
    $apont = DB::table('comvis_apontamentos')
        ->where('os_id', $osId)
        ->first();
    expect($apont->duracao_segundos)->toBeGreaterThanOrEqual(5390);
    expect($apont->duracao_segundos)->toBeLessThanOrEqual(5410);
    expect((float) $apont->m2_produzido)->toBe(4.5);

    limparDemoTest($bizId);
});

// ------------------------------------------------------------------
// Teste 4: materiais auto-seeded se ausentes (count >= 5 após run)
// ------------------------------------------------------------------

it('auto-seed materiais quando count < 5 para o business', function () {
    $business = bootstrapDemoTest();
    $bizId    = $business->id;

    // Garantir que não há materiais pra este business antes de rodar
    DB::table('comvis_materiais')
        ->where('business_id', $bizId)
        ->delete();

    $countAntes = DB::table('comvis_materiais')
        ->where('business_id', $bizId)
        ->count();
    expect($countAntes)->toBe(0);

    $exitCode = \Illuminate\Support\Facades\Artisan::call('comvis:demo-seed', [
        '--business' => $bizId,
    ]);

    expect($exitCode)->toBe(0);

    $countDepois = DB::table('comvis_materiais')
        ->where('business_id', $bizId)
        ->count();
    expect($countDepois)->toBeGreaterThanOrEqual(5);

    limparDemoTest($bizId);
});

// ------------------------------------------------------------------
// Teste 5: --clean deleta rows demo anteriores antes de recriar
//          Resultado: count permanece 1 orçamento + 3 itens + 1 OS + 1 apontamento
// ------------------------------------------------------------------

it('--clean deleta dados demo anteriores sem acumular (run × 2 = 1+3+1+1)', function () {
    $business = bootstrapDemoTest();
    $bizId    = $business->id;

    // Primeira execução
    \Illuminate\Support\Facades\Artisan::call('comvis:demo-seed', [
        '--business' => $bizId,
    ]);

    // Segunda execução com --clean (deve limpar a primeira antes de criar)
    $exitCode = \Illuminate\Support\Facades\Artisan::call('comvis:demo-seed', [
        '--business' => $bizId,
        '--clean'    => true,
    ]);

    expect($exitCode)->toBe(0);

    // Deve ter exatamente 1 orçamento (não 2)
    $orcamentos = DB::table('comvis_orcamentos')
        ->where('business_id', $bizId)
        ->where('observacoes', 'like', '%[CV-DEMO]%')
        ->count();
    expect($orcamentos)->toBe(1, 'Após --clean, deve haver apenas 1 orçamento demo (não acumulando)');

    // Deve ter exatamente 3 itens (não 6)
    $orcId = DB::table('comvis_orcamentos')
        ->where('business_id', $bizId)
        ->where('observacoes', 'like', '%[CV-DEMO]%')
        ->value('id');

    $itens = DB::table('comvis_orcamento_itens')
        ->where('orcamento_id', $orcId)
        ->where('business_id', $bizId)
        ->count();
    expect($itens)->toBe(3, 'Deve ter exatamente 3 itens (não duplicados pelo --clean)');

    // Deve ter exatamente 1 OS
    $osCount = DB::table('comvis_os')
        ->where('business_id', $bizId)
        ->where('observacoes', 'like', '%[CV-DEMO]%')
        ->count();
    expect($osCount)->toBe(1, 'Deve ter apenas 1 OS demo após --clean');

    limparDemoTest($bizId);
});

// ------------------------------------------------------------------
// Teste 6: multi-tenant — --business=1 não cria rows para biz=99
// ------------------------------------------------------------------

it('multi-tenant: --business=1 cria só pra biz=1, biz=99 fica sem rows demo', function () {
    $business = bootstrapDemoTest();
    $bizId    = $business->id; // biz=1

    $exitCode = \Illuminate\Support\Facades\Artisan::call('comvis:demo-seed', [
        '--business' => $bizId,
    ]);

    expect($exitCode)->toBe(0);

    // biz=1 tem demo
    $orcBiz1 = DB::table('comvis_orcamentos')
        ->where('business_id', $bizId)
        ->where('observacoes', 'like', '%[CV-DEMO]%')
        ->count();
    expect($orcBiz1)->toBe(1);

    // biz=99 NÃO deve ter demo
    $orcBiz99 = DB::table('comvis_orcamentos')
        ->where('business_id', 99)
        ->where('observacoes', 'like', '%[CV-DEMO]%')
        ->count();
    expect($orcBiz99)->toBe(0, 'biz=99 não deve ter rows de demo criadas pra biz=1');

    $osBiz99 = DB::table('comvis_os')
        ->where('business_id', 99)
        ->where('observacoes', 'like', '%[CV-DEMO]%')
        ->count();
    expect($osBiz99)->toBe(0, 'biz=99 não deve ter OS demo criadas pra biz=1');

    limparDemoTest($bizId);
});
