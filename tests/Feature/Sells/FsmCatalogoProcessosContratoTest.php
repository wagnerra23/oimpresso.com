<?php

declare(strict_types=1);

use App\Domain\Fsm\Contracts\SideEffectInterface;
use App\Domain\Fsm\Exceptions\InvalidActionForCurrentStageException;
use App\Domain\Fsm\Modelos\CatalogoProcessos;
use App\Domain\Fsm\Modelos\ProcessosDosSeeders;
use App\Domain\Fsm\Services\ExecuteStageActionService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\Contract\AutosaveContractRunner;

/**
 * Catálogo de processos e cobertura do ERP por transição (pedido [W] 2026-10-16).
 *
 * Contrato: a transcrição dos 3 seeders é igual ao que eles gravam; nenhuma referência aponta
 * para classe, método ou rota inexistente; o que a cobertura afirma sobre o código ("sem rota",
 * "o Financeiro não lê") continua verdadeiro; e a regra "etapa terminal não executa" é exercida
 * no motor de verdade. Se um destes cair, a cobertura mudou: atualize o catálogo.
 */
uses(DatabaseTransactions::class);

/** Nome curto citado em `onde` => classe real. Nome novo sem entrada aqui reprova o teste. */
const FLX_REFS = [
    'SellReturnController' => \App\Http\Controllers\SellReturnController::class,
    'TransactionUtil' => \App\Utils\TransactionUtil::class,
    'ProductUtil' => \App\Utils\ProductUtil::class,
    'PurchaseReturnController' => \App\Http\Controllers\PurchaseReturnController::class,
    'CombinedPurchaseReturnController' => \App\Http\Controllers\CombinedPurchaseReturnController::class,
    'RepairFsmActionController' => \Modules\Repair\Http\Controllers\RepairFsmActionController::class,
    'ServiceOrderFsmActionController' => \App\Http\Controllers\ServiceOrderFsmActionController::class,
    'SaleFsmActionController' => \App\Http\Controllers\SaleFsmActionController::class,
];

/** Arquivos PHP de produção (fora de testes, migrations e seeders) que citam `$needle`. */
function flxChamadores(string $needle): array
{
    $hits = [];
    foreach (['app', 'Modules', 'routes'] as $raiz) {
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(base_path($raiz), FilesystemIterator::SKIP_DOTS));
        foreach ($it as $f) {
            $p = str_replace('\\', '/', $f->getPathname());
            if ($f->getExtension() !== 'php' || preg_match('#/(Tests|tests|Migrations|Seeders)/#', $p)) {
                continue;
            }
            if (str_contains((string) file_get_contents($p), $needle)) {
                $hits[] = $p;
            }
        }
    }

    return $hits;
}

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('Requer schema MySQL UltimatePOS (ADR 0358).');
    }
    if (! Schema::hasColumn('sale_process_stages', 'passo')) {
        $this->markTestSkipped('Migration de passos ausente.');
    }
    $ctx = AutosaveContractRunner::setupSellsContext($this);
    $this->biz = (int) $ctx['business']->id;
    $this->venda = (int) $ctx['transactionId'];
});

it('a transcrição é igual ao que os 3 seeders gravam no motor', function () {
    foreach (ProcessosDosSeeders::PROCESSOS as $key => $p) {
        (new $p['seeder']())->runForBusiness($this->biz);
        $proc = DB::table('sale_processes')->where('business_id', $this->biz)->where('key', $key)->first();
        $stages = DB::table('sale_process_stages')->where('process_id', $proc->id)->orderBy('sort_order')->get();
        $porId = $stages->keyBy('id');

        expect($proc->name)->toBe($p['nome'])
            ->and($stages->map(fn ($s) => [$s->key, $s->name, (bool) $s->is_initial, (bool) $s->is_terminal])->all())
            ->toBe($p['estados'], "estados de {$key}");

        $acoes = DB::table('sale_stage_actions')->whereIn('stage_id', $stages->pluck('id'))->orderBy('id')->get()
            ->map(fn ($a) => [$porId[$a->stage_id]->key, $a->target_stage_id ? $porId[$a->target_stage_id]->key : null, $a->key, $a->label, $a->side_effect_class])
            ->all();
        expect($acoes)->toBe($p['transicoes'], "transições de {$key}");
    }
});

it('nenhuma referência aponta para classe, método ou rota inexistente', function () {
    $refs = 0;
    foreach (ProcessosDosSeeders::PROCESSOS as $p) {
        expect(class_exists($p['seeder']))->toBeTrue($p['seeder']);
        foreach ($p['transicoes'] as $t) {
            if ($t[4] !== null) {
                expect(is_subclass_of($t[4], SideEffectInterface::class))->toBeTrue($t[4]);
                $refs++;
            }
        }
    }

    foreach (CatalogoProcessos::processos() as $id => $p) {
        foreach ($p['transicoes'] as $t) {
            preg_match_all('/\b([A-Z][A-Za-z]+)(?:@|::)([a-zA-Z]+)\b/', $t['cobertura']['onde'], $m, PREG_SET_ORDER);
            foreach ($m as [, $curto, $metodo]) {
                if ($curto === 'DevolucaoService') {
                    continue; // há dois; conferidos pelo caminho no teste abaixo
                }
                expect(array_key_exists($curto, FLX_REFS))->toBeTrue("{$id}: {$curto} sem entrada em FLX_REFS");
                expect(method_exists(FLX_REFS[$curto], $metodo))->toBeTrue("{$curto}::{$metodo}");
                $refs++;
            }
        }
    }
    expect(method_exists(\Modules\NfeBrasil\Services\DevolucaoService::class, 'emitirDevolucao'))->toBeTrue()
        ->and(method_exists(\Modules\Vestuario\Services\DevolucaoService::class, 'registrarDevolucao'))->toBeTrue();

    $uris = collect(Route::getRoutes()->getRoutes())->map->uri()->all();
    foreach (['sell-return', 'purchase-return', 'purchase-return/save', 'repair/job-sheets/{id}/fsm-action', 'sells/{id}/fsm-action'] as $uri) {
        expect(in_array($uri, $uris, true))->toBeTrue("rota {$uri}");
    }
    expect($refs)->toBeGreaterThan(20);
});

it('o que a cobertura afirma sobre o código continua verdadeiro', function () {
    // "existe sem rota nem chamador" — se alguém ligar, a cobertura sobe e este teste avisa.
    expect(flxChamadores('emitirDevolucao('))->toBe([base_path('Modules/NfeBrasil/Services/DevolucaoService.php')])
        ->and(flxChamadores('registrarDevolucao('))->toBe([base_path('Modules/Vestuario/Services/DevolucaoService.php')])
        ->and(collect(flxChamadores('DevolucaoController'))->filter(fn ($p) => str_contains(strtolower($p), '/routes/'))->all())->toBe([])
        // "o Financeiro não lê sell_return" (controle positivo: ele lê 'sell').
        ->and(collect(flxChamadores("'sell_return'"))->filter(fn ($p) => str_contains($p, 'Modules/Financeiro/'))->all())->toBe([])
        ->and(collect(flxChamadores("'sell'"))->filter(fn ($p) => str_contains($p, 'Modules/Financeiro/'))->isNotEmpty())->toBeTrue();
});

it('ação em etapa terminal é recusada pelo motor, como a cobertura diz', function () {
    (new \Database\Seeders\FsmProcessoComunicacaoVisualSeeder())->runForBusiness($this->biz);
    $proc = DB::table('sale_processes')->where('business_id', $this->biz)->where('key', 'os_comunicacao_visual')->first();
    $entregue = DB::table('sale_process_stages')->where('process_id', $proc->id)->where('key', 'entregue_completo')->value('id');
    DB::table('transactions')->where('id', $this->venda)->update(['process_id' => $proc->id, 'current_stage_id' => $entregue]);

    $venda = \App\Transaction::find($this->venda);
    expect(fn () => app(ExecuteStageActionService::class)->execute($venda, 'aplicar_garantia'))
        ->toThrow(InvalidActionForCurrentStageException::class, 'terminal');

    $t = collect(CatalogoProcessos::processos()['os_comunicacao_visual']['transicoes'])->firstWhere('acao', 'aplicar_garantia');
    expect($t['cobertura']['estado'])->toBe('nao');
});

it('devolução completa: todo estado não final tem saída e o resumo soma as transições', function () {
    foreach (['devolucao', 'devolucao_compra'] as $id) {
        $p = CatalogoProcessos::processos()[$id];
        $chaves = array_column($p['estados'], 'chave');
        foreach ($p['transicoes'] as $t) {
            expect(in_array($t['de'], $chaves, true))->toBeTrue()
                ->and($t['para'] === null || in_array($t['para'], $chaves, true))->toBeTrue()
                ->and(CatalogoProcessos::ESTADOS_COBERTURA)->toContain($t['cobertura']['estado']);
        }
        $comSaida = array_unique(array_column(array_filter($p['transicoes'], fn ($t) => $t['para'] !== null), 'de'));
        foreach ($p['estados'] as $e) {
            if (! in_array($e['tipo'], ['ok', 'x'], true)) {
                expect(in_array($e['chave'], $comSaida, true))->toBeTrue("{$id}: {$e['chave']} sem saída");
            }
        }
    }

    expect(CatalogoProcessos::resumo('devolucao'))->toBe(['sim' => 1, 'parcial' => 3, 'nao' => 6, 'nao_verificado' => 0, 'total' => 10])
        ->and(CatalogoProcessos::resumo('devolucao_compra'))->toBe(['sim' => 2, 'parcial' => 0, 'nao' => 0, 'nao_verificado' => 1, 'total' => 3]);

    foreach (array_keys(ProcessosDosSeeders::PROCESSOS) as $key) {
        expect(CatalogoProcessos::resumo($key)['total'])->toBe(count(ProcessosDosSeeders::PROCESSOS[$key]['transicoes']));
    }
});
