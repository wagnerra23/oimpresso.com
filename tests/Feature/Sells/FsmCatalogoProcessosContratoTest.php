<?php

declare(strict_types=1);

use App\Domain\Fsm\Exceptions\InvalidActionForCurrentStageException;
use App\Domain\Fsm\Modelos\CatalogoProcessos;
use App\Domain\Fsm\Modelos\ProcessosDoPrototipo;
use App\Domain\Fsm\Services\ExecuteStageActionService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\Contract\AutosaveContractRunner;

/**
 * Catálogo de processos e cobertura do ERP por transição (pedido [W] 2026-10-16; protótipo
 * aprovado no PR #9498).
 *
 * Contrato: o catálogo é igual ao protótipo `fluxos-processos.jsx`; nos processos com `erp`, o
 * protótipo é igual ao que o seeder grava no motor; nenhuma referência aponta para classe, método
 * ou rota inexistente; o que a cobertura afirma sobre o código continua verdadeiro; e a regra
 * "etapa terminal não executa" é exercida no motor de verdade.
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
    'NotificationController' => \App\Http\Controllers\NotificationController::class,
    'SellPosController' => \App\Http\Controllers\SellPosController::class,
    'NfeEmissaoController' => \Modules\NfeBrasil\Http\Controllers\NfeEmissaoController::class,
    'NfseController' => \Modules\NFSe\Http\Controllers\NfseController::class,
    'PedidoEntregaController' => \App\Http\Controllers\Api\App\PedidoEntregaController::class,
    'CancelarVendaCascade' => \App\Domain\Fsm\SideEffects\CancelarVendaCascade::class,
    'NfeService' => \Modules\NfeBrasil\Services\NfeService::class,
    'ContingenciaController' => \Modules\NfeBrasil\Http\Controllers\ContingenciaController::class,
    'AcoesController' => \Modules\Fiscal\Http\Controllers\AcoesController::class,
    'NfeCartaCorrecaoService' => \Modules\NfeBrasil\Services\NfeCartaCorrecaoService::class,
    'NfeInutilizacaoService' => \Modules\NfeBrasil\Services\NfeInutilizacaoService::class,
    'NfseEmissaoService' => \Modules\NFSe\Services\NfseEmissaoService::class,
    'UnificadoController' => \Modules\Financeiro\Http\Controllers\UnificadoController::class,
    'ConciliacaoController' => \Modules\Financeiro\Http\Controllers\ConciliacaoController::class,
    'OnCobrancaPagaCreateFinanceiroTitulo' => \Modules\Financeiro\Listeners\OnCobrancaPagaCreateFinanceiroTitulo::class,
    'DealPipelineService' => \Modules\Crm\Services\DealPipelineService::class,
    'FunilController' => \Modules\Crm\Http\Controllers\FunilController::class,
    'ContaPagarController' => \Modules\Financeiro\Http\Controllers\ContaPagarController::class,
    'PurchaseOrderController' => \App\Http\Controllers\PurchaseOrderController::class,
    'SellController' => \App\Http\Controllers\SellController::class,
    'PurchaseRequisitionController' => \App\Http\Controllers\PurchaseRequisitionController::class,
    'PurchaseController' => \App\Http\Controllers\PurchaseController::class,
    'ManifestacaoController' => \Modules\NfeBrasil\Http\Controllers\ManifestacaoController::class,
    'TransactionPaymentController' => \App\Http\Controllers\TransactionPaymentController::class,
    'TituloAutoService' => \Modules\Financeiro\Services\TituloAutoService::class,
    'StockAdjustmentController' => \App\Http\Controllers\StockAdjustmentController::class,
    'ApontamentoController' => \Modules\Producao\Http\Controllers\ApontamentoController::class,
    'ApontamentoOperadorService' => \Modules\Producao\Services\ApontamentoOperadorService::class,
    'CustoRealService' => \Modules\Producao\Services\CustoRealService::class,
    'SituacaoDaOrdem' => \Modules\Producao\Services\SituacaoDaOrdem::class,
    'CriarOsDaVenda' => \Modules\Producao\Services\CriarOsDaVenda::class,
    'OsDaVenda' => \Modules\Producao\Services\OsDaVenda::class,
    'InboxController' => \Modules\Whatsapp\Http\Controllers\Admin\InboxController::class,
    'CsatDispatcher' => \Modules\Whatsapp\Services\Csat\CsatDispatcher::class,
    'RecurringBillingController' => \Modules\RecurringBilling\Http\Controllers\RecurringBillingController::class,
    'InvoiceGeneratorService' => \Modules\RecurringBilling\Services\InvoiceGeneratorService::class,
    'IntercorrenciaController' => \Modules\Ponto\Http\Controllers\IntercorrenciaController::class,
    'AprovacaoController' => \Modules\Ponto\Http\Controllers\AprovacaoController::class,
    'ApuracaoService' => \Modules\Ponto\Services\ApuracaoService::class,
    'AgendaInstalacao' => \Modules\ComunicacaoVisual\Services\AgendaInstalacao::class,
    'AplicarModelo' => \App\Domain\Fsm\Modelos\AplicarModelo::class,
    'ExecuteStageActionService' => \App\Domain\Fsm\Services\ExecuteStageActionService::class,
];

/** Seeder que grava cada processo `erp` do protótipo. */
const FLX_SEEDERS = [
    'os_reparo_padrao' => \Database\Seeders\FsmProcessoOsReparoPadraoSeeder::class,
    'oficina_mecanica_os' => \Modules\OficinaAuto\Database\Seeders\OficinaAutoFsmSeeder::class,
    'os_comunicacao_visual' => \Database\Seeders\FsmProcessoComunicacaoVisualSeeder::class,
];

/** Argumentos de uma chamada S(...)/T(...) do .jsx: strings, null e inteiros, na ordem. */
function flxArgs(string $args): array
{
    preg_match_all('/"((?:[^"\\\\]|\\\\.)*)"|\b(null)\b|\b(\d+)\b/', $args, $m, PREG_SET_ORDER);

    return array_map(function ($a) {
        if (($a[2] ?? '') === 'null') {
            return null;
        }
        if (($a[3] ?? '') !== '') {
            return (int) $a[3];
        }

        return json_decode('"' . $a[1] . '"');
    }, $m);
}

/** Lê PROCS do protótipo: id => [n, area, doc, erp, liga, estados[k,l,tipo,lado], trans[6]]. */
function flxProtoProcessos(): array
{
    $src = (string) file_get_contents(base_path('prototipo-ui/cowork/Wagner/fluxos-processos.jsx'));
    $bloco = substr($src, strpos($src, 'const PROCS = ['));
    $bloco = substr($bloco, 0, strpos($bloco, "\n];"));
    $chamada = '\(((?:"(?:[^"\\\\]|\\\\.)*"|[^()"])*)\)';
    $out = [];
    foreach (array_slice(preg_split('/\n  \{ id: /', $bloco), 1) as $p) {
        preg_match('/^"([^"]+)"/', $p, $id);
        $campo = fn (string $k) => preg_match('/\b' . $k . ': "((?:[^"\\\\]|\\\\.)*)"/', $p, $m) ? json_decode('"' . $m[1] . '"') : null;
        preg_match('/liga: \[([^\]]*)\]/', $p, $liga);
        preg_match_all('/\bS' . $chamada . '/', $p, $s);
        preg_match_all('/\bT' . $chamada . '/', $p, $t);
        $out[$id[1]] = [
            'n' => $campo('n'), 'area' => $campo('area'), 'doc' => $campo('doc'), 'erp' => $campo('erp'),
            'liga' => flxArgs($liga[1] ?? ''),
            'estados' => array_map(function ($a) {
                $x = flxArgs($a);

                return [$x[0], $x[1], $x[2] ?? 'nor', (bool) ($x[3] ?? false)];
            }, $s[1]),
            'trans' => array_map(fn ($a) => array_pad(flxArgs($a), 6, ''), $t[1]),
        ];
    }

    return $out;
}

/** Arquivos PHP de produção (fora de testes, migrations e seeders) que citam `$needle`. */
function flxChamadores(string $needle): array
{
    $hits = [];
    foreach (['app', 'Modules', 'routes'] as $raiz) {
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(base_path($raiz), FilesystemIterator::SKIP_DOTS));
        foreach ($it as $f) {
            $p = str_replace(DIRECTORY_SEPARATOR, '/', $f->getPathname());
            // O próprio catálogo cita os nomes que a cobertura descreve: não conta como chamador.
            if ($f->getExtension() !== 'php' || preg_match('#/(Tests|tests|Migrations|Seeders)/|/Domain/Fsm/Modelos/#', $p)) {
                continue;
            }
            if (str_contains((string) file_get_contents($p), $needle)) {
                $hits[] = $p;
            }
        }
    }

    return $hits;
}

function flxCaminho(string $relativo): string
{
    return str_replace(DIRECTORY_SEPARATOR, '/', base_path($relativo));
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

it('o catálogo é igual ao protótipo fluxos-processos.jsx, processo a processo', function () {
    $proto = flxProtoProcessos();
    expect(array_keys(ProcessosDoPrototipo::PROCESSOS))->toBe(array_keys($proto))
        ->and(count($proto))->toBe(18);

    foreach (ProcessosDoPrototipo::PROCESSOS as $id => $p) {
        expect([$p['n'], $p['area'], $p['doc'], $p['erp'] ?? null, $p['liga']])
            ->toBe([$proto[$id]['n'], $proto[$id]['area'], $proto[$id]['doc'], $proto[$id]['erp'], $proto[$id]['liga']], "cabeçalho de {$id}")
            ->and($p['estados'])->toBe($proto[$id]['estados'], "estados de {$id}")
            ->and($p['trans'])->toBe($proto[$id]['trans'], "transições de {$id}");
    }
});

it('nos processos com erp, o protótipo é igual ao que o seeder grava no motor', function () {
    $conferidos = 0;
    foreach (ProcessosDoPrototipo::PROCESSOS as $id => $p) {
        if (! isset($p['erp'])) {
            continue;
        }
        $seeder = FLX_SEEDERS[$p['erp']];
        (new $seeder())->runForBusiness($this->biz);
        $proc = DB::table('sale_processes')->where('business_id', $this->biz)->where('key', $p['erp'])->first();
        $stages = DB::table('sale_process_stages')->where('process_id', $proc->id)->orderBy('sort_order')->get();
        $porId = $stages->keyBy('id');

        expect($stages->map(fn ($s) => [$s->key, $s->name, (bool) $s->is_initial, (bool) $s->is_terminal])->all())
            ->toBe(array_map(fn ($e) => [$e[0], $e[1], $e[2] === 'ini', in_array($e[2], CatalogoProcessos::FINAIS, true)], $p['estados']), "estados de {$id}");

        $acoes = DB::table('sale_stage_actions')->whereIn('stage_id', $stages->pluck('id'))->orderBy('id')->get()
            ->map(fn ($a) => [
                $porId[$a->stage_id]->name,
                $a->target_stage_id ? $porId[$a->target_stage_id]->name : null,
                $a->label,
                $a->side_effect_class ? CatalogoProcessos::EFEITO_DO_SIDE_EFFECT[$a->side_effect_class] : '',
            ])->all();
        expect($acoes)->toBe(array_map(fn ($t) => [$t[0], $t[1], $t[2], $t[4]], $p['trans']), "transições de {$id}");
        $conferidos++;
    }
    expect($conferidos)->toBe(3);
});

it('nenhuma referência aponta para classe, método ou rota inexistente', function () {
    $refs = 0;
    foreach (array_keys(CatalogoProcessos::EFEITO_DO_SIDE_EFFECT) as $classe) {
        expect(is_subclass_of($classe, \App\Domain\Fsm\Contracts\SideEffectInterface::class))->toBeTrue($classe);
    }
    foreach (CatalogoProcessos::processos() as $id => $p) {
        foreach ($p['transicoes'] as $t) {
            preg_match_all('/\b([A-Z][A-Za-z]+)(?:@|::)([a-zA-Z]+)\b/', $t['cobertura']['onde'], $m, PREG_SET_ORDER);
            foreach ($m as [, $curto, $metodo]) {
                if ($curto === 'DevolucaoService') {
                    continue; // há dois; conferidos pelo caminho logo abaixo
                }
                expect(array_key_exists($curto, FLX_REFS))->toBeTrue("{$id}: {$curto} sem entrada em FLX_REFS");
                expect(method_exists(FLX_REFS[$curto], $metodo))->toBeTrue("{$curto}::{$metodo}");
                $refs++;
            }
        }
    }
    expect(method_exists(\Modules\NfeBrasil\Services\DevolucaoService::class, 'emitirDevolucao'))->toBeTrue()
        ->and(method_exists(\Modules\Vestuario\Services\DevolucaoService::class, 'registrarDevolucao'))->toBeTrue()
        // "validade de 6 meses" do protótipo = a do ERP (Lançar crédito).
        ->and(\Modules\Vestuario\Services\DevolucaoService::VALIDADE_CREDITO_MESES)->toBe(6);

    $uris = collect(Route::getRoutes()->getRoutes())->map->uri()->all();
    $rotas = [
        'sell-return', 'purchase-return', 'purchase-return/save', 'repair/job-sheets/{id}/fsm-action', 'sells/{id}/fsm-action',
        'quote/{token}', 'nfe-brasil/transactions/{tx}/emitir', 'nfe-brasil/contingencia/ativar',
        'fiscal/acoes/nfe/{emissao}/retransmitir', 'fiscal/acoes/nfe/{emissao}/cce', 'fiscal/acoes/nfe/{emissao}/cancelar',
        'fiscal/acoes/nfe/inutilizar', 'nfse/emitir', 'nfse/transactions/{tx}/emitir', 'nfse/{nfse}/cancelar',
        'financeiro/unificado/{tituloId}/boleto', 'financeiro/unificado/{id}/baixar', 'financeiro/conciliacao/{lineId}/match',
        'financeiro/unificado/{id}/aprovar', 'purchase-order', 'sells/update-shipping/{id}', 'purchases/update-status',
        'stock-adjustments', 'recurring-billing', 'recurring-billing/{id}/reativar', 'recurring-billing/{id}/cancelar',
        'ponto/intercorrencias/{id}/submeter', 'ponto/intercorrencias/{id}/cancelar', 'ponto/aprovacoes/{id}/aprovar',
        'ponto/aprovacoes/{id}/rejeitar', 'atendimento/inbox/{id}/assign',
    ];
    foreach ($rotas as $uri) {
        expect(in_array($uri, $uris, true))->toBeTrue("rota {$uri}");
    }
    expect($refs)->toBeGreaterThan(20);
});

it('o que a cobertura afirma sobre o código continua verdadeiro', function () {
    // R-NFE-039: a NF-e de devolução tem um chamador só, a ação fiscal com rota e permissão.
    // Se a rota sumir, ou se aparecer outro chamador, a cobertura escrita fica falsa e este teste avisa.
    $devolucaoRota = collect(Route::getRoutes()->getRoutes())->first(fn ($r) => $r->uri() === 'fiscal/acoes/nfe/{emissao}/devolucao');
    $acoes = (string) file_get_contents(base_path('Modules/Fiscal/Http/Controllers/AcoesController.php'));
    expect(flxChamadores('emitirDevolucao('))->toEqualCanonicalizing([
        flxCaminho('Modules/NfeBrasil/Services/DevolucaoService.php'),
        flxCaminho('Modules/Fiscal/Http/Controllers/AcoesController.php'),
    ])
        ->and($devolucaoRota)->not->toBeNull()
        ->and($devolucaoRota?->methods())->toContain('POST')
        ->and($devolucaoRota?->getActionName())->toBe(\Modules\Fiscal\Http\Controllers\AcoesController::class.'@devolucao')
        ->and(str_contains($acoes, "can('fiscal.nfe.acoes')"))->toBeTrue()
        ->and(flxChamadores('registrarDevolucao('))->toBe([flxCaminho('Modules/Vestuario/Services/DevolucaoService.php')])
        ->and(collect(flxChamadores('DevolucaoController'))->filter(fn ($p) => str_contains(strtolower($p), '/routes/'))->all())->toBe([])
        // "o Financeiro não lê sell_return" (controle positivo: ele lê 'sell').
        ->and(collect(flxChamadores("'sell_return'"))->filter(fn ($p) => str_contains($p, 'Modules/Financeiro/'))->all())->toBe([])
        ->and(collect(flxChamadores("'sell'"))->filter(fn ($p) => str_contains($p, 'Modules/Financeiro/'))->isNotEmpty())->toBeTrue();

    // Venda, NF-e, NFS-e e cobrança.
    $nfseService = (string) file_get_contents(base_path('Modules/NFSe/Services/NfseEmissaoService.php'));
    // US-NFE-070 · UC-FLX-32: o agendamento nfe:retentar-contingencia passou a ser o chamador.
    expect(flxChamadores('RetentarContingenciaJob::dispatch'))->toBe([flxCaminho('Modules/NfeBrasil/Console/Commands/RetentarContingenciaCommand.php')])
        ->and(flxChamadores('new RetentarContingenciaJob'))->toBe([])
        ->and(flxChamadores('TransactionDocument::create'))->toBe([])
        ->and(flxChamadores('new TransactionDocument'))->toBe([])
        // US-CRM funil (#9883): o kanban do funil passou a ser o único chamador; a venda perdida segue sem alimentar o funil.
        ->and(collect(flxChamadores('moverStage('))->reject(fn ($p) => str_ends_with($p, 'DealPipelineService.php'))->values()->all())->toBe([flxCaminho('Modules/Crm/Http/Controllers/FunilController.php')])
        // US-FIN-072 · UC-FLX-30: o Financeiro passou a lançar a retenção da NFS-e como baixa (desligado por padrão).
        ->and(class_exists(\Modules\Financeiro\Services\RetencaoNfseTituloService::class))->toBeTrue()
        ->and(collect(flxChamadores('iss_retido'))->filter(fn ($p) => str_contains($p, 'Modules/Financeiro/'))->isNotEmpty())->toBeTrue()
        ->and(collect(flxChamadores('renegoci'))->filter(fn ($p) => str_contains($p, 'Modules/Financeiro/'))->all())->toBe([])
        // NfseEmissaoService::cancelar chama o provedor como instrução solta: a resposta não é usada.
        ->and(str_contains($nfseService, "\n        \$this->provider->cancelar(\$emissao->numero, \$motivo);\n"))->toBeTrue()
        // A tela calcula o atraso ao ler; o título não tem estado vencido.
        ->and(method_exists(\Modules\Financeiro\Http\Controllers\UnificadoController::class, 'deriveStatus'))->toBeTrue();

    // Os outros 9 processos.
    $rotas = collect(Route::getRoutes()->getRoutes())->map(fn ($r) => $r->getActionName())->implode("\n");
    // Produção (OS por item, ADR 0436): as afirmações viraram comportamento e são provadas pelos
    // contratos do Modules/Producao (ProducaoCriarOsDaVenda, SituacaoDaOrdem, ConcluirItem).
    expect(str_contains($rotas, 'PurchaseXmlController'))->toBeFalse()
        ->and(collect(flxChamadores("'past_due'"))->filter(fn ($p) => str_contains($p, 'Modules/RecurringBilling/') && preg_match('/status[\'"]?\s*=>\s*[\'"]past_due/', (string) file_get_contents($p)))->all())->toBe([])
        // "o status de reagendamento de cv_instalacoes não é gravado por nenhum código" (Confirmar nova
        // data = nao): só a Entity cita o valor. A agenda (UC-FLX-21) grava em cv_instalacoes, mas nunca esse status.
        ->and(collect(flxChamadores("'aguardando_reagendamento'"))->reject(fn ($p) => str_contains($p, '/Entities/'))->all())->toBe([])
        // controle positivo: a sonda acha o valor onde ele mora (senão o [] acima seria vácuo).
        ->and(collect(flxChamadores("'aguardando_reagendamento'"))->filter(fn ($p) => str_contains($p, '/Entities/Instalacao.php'))->isNotEmpty())->toBeTrue();
});

it('ação em etapa terminal é recusada pelo motor, como a cobertura diz', function () {
    (new \Database\Seeders\FsmProcessoComunicacaoVisualSeeder())->runForBusiness($this->biz);
    $proc = DB::table('sale_processes')->where('business_id', $this->biz)->where('key', 'os_comunicacao_visual')->first();
    $entregue = DB::table('sale_process_stages')->where('process_id', $proc->id)->where('key', 'entregue_completo')->value('id');
    DB::table('transactions')->where('id', $this->venda)->update(['process_id' => $proc->id, 'current_stage_id' => $entregue]);

    $venda = \App\Transaction::find($this->venda);
    expect(fn () => app(ExecuteStageActionService::class)->execute($venda, 'aplicar_garantia'))
        ->toThrow(InvalidActionForCurrentStageException::class, 'terminal');

    $t = collect(CatalogoProcessos::processos()['os-cv']['transicoes'])->firstWhere('rotulo', 'Acionar garantia (abre OS filha)');
    expect($t['cobertura']['estado'])->toBe('nao');
});

it('devolução completa, cobertura sem órfão e resumos', function () {
    foreach (['devolucao', 'devolucao-compra'] as $id) {
        $p = CatalogoProcessos::processos()[$id];
        $comSaida = array_unique(array_column(array_filter($p['transicoes'], fn ($t) => $t['para'] !== null), 'de'));
        $comEntrada = array_unique(array_column($p['transicoes'], 'para'));
        foreach ($p['estados'] as $e) {
            $final = in_array($e['tipo'], CatalogoProcessos::FINAIS, true);
            expect($final || in_array($e['chave'], $comSaida, true))->toBeTrue("{$id}: {$e['chave']} sem saída")
                ->and(! $final || in_array($e['chave'], $comEntrada, true))->toBeTrue("{$id}: {$e['chave']} sem entrada");
        }
    }

    // Toda ação dos processos com cobertura escrita tem entrada, e não sobra entrada para ação que não existe.
    foreach (CatalogoProcessos::COBERTURA as $id => $mapa) {
        $p = CatalogoProcessos::processos()[$id];
        $nomes = array_column($p['estados'], 'nome', 'chave');
        $usadas = array_map(fn ($t) => CatalogoProcessos::chaveCobertura($id, $t['rotulo'], $t['para'] !== null ? $nomes[$t['para']] : null, $nomes[$t['de']]), $p['transicoes']);
        expect(in_array(null, $usadas, true))->toBeFalse("{$id}: ação sem cobertura escrita")
            ->and(array_keys($mapa))->toEqualCanonicalizing($usadas, "{$id}: cobertura sobrando ou repetida");
    }

    foreach (CatalogoProcessos::processos() as $p) {
        foreach ($p['transicoes'] as $t) {
            expect(CatalogoProcessos::ESTADOS_COBERTURA)->toContain($t['cobertura']['estado']);
        }
    }

    expect(CatalogoProcessos::resumo('devolucao'))->toBe(['sim' => 1, 'parcial' => 2, 'nao' => 6, 'nao_verificado' => 0, 'total' => 9])
        ->and(CatalogoProcessos::resumo('devolucao-compra'))->toBe(['sim' => 2, 'parcial' => 0, 'nao' => 0, 'nao_verificado' => 1, 'total' => 3])
        ->and(CatalogoProcessos::resumo('os-reparo'))->toBe(['sim' => 21, 'parcial' => 0, 'nao' => 2, 'nao_verificado' => 0, 'total' => 23])
        ->and(CatalogoProcessos::resumo('oficina-os'))->toBe(['sim' => 14, 'parcial' => 0, 'nao' => 0, 'nao_verificado' => 0, 'total' => 14])
        ->and(CatalogoProcessos::resumo('os-cv'))->toBe(['sim' => 0, 'parcial' => 32, 'nao' => 2, 'nao_verificado' => 0, 'total' => 34])
        ->and(CatalogoProcessos::resumo('venda'))->toBe(['sim' => 0, 'parcial' => 6, 'nao' => 0, 'nao_verificado' => 0, 'total' => 6])
        ->and(CatalogoProcessos::resumo('nfe'))->toBe(['sim' => 2, 'parcial' => 7, 'nao' => 0, 'nao_verificado' => 0, 'total' => 9])
        ->and(CatalogoProcessos::resumo('nfse'))->toBe(['sim' => 1, 'parcial' => 4, 'nao' => 2, 'nao_verificado' => 0, 'total' => 7])
        ->and(CatalogoProcessos::resumo('cobranca'))->toBe(['sim' => 0, 'parcial' => 5, 'nao' => 3, 'nao_verificado' => 0, 'total' => 8])
        ->and(CatalogoProcessos::resumo('pagar'))->toBe(['sim' => 0, 'parcial' => 2, 'nao' => 2, 'nao_verificado' => 0, 'total' => 4])
        ->and(CatalogoProcessos::resumo('compra'))->toBe(['sim' => 0, 'parcial' => 6, 'nao' => 1, 'nao_verificado' => 0, 'total' => 7])
        ->and(CatalogoProcessos::resumo('requisicao'))->toBe(['sim' => 0, 'parcial' => 0, 'nao' => 3, 'nao_verificado' => 0, 'total' => 3])
        ->and(CatalogoProcessos::resumo('inventario'))->toBe(['sim' => 0, 'parcial' => 1, 'nao' => 2, 'nao_verificado' => 0, 'total' => 3])
        ->and(CatalogoProcessos::resumo('producao'))->toBe(['sim' => 1, 'parcial' => 11, 'nao' => 0, 'nao_verificado' => 0, 'total' => 12])
        ->and(CatalogoProcessos::resumo('entrega'))->toBe(['sim' => 0, 'parcial' => 5, 'nao' => 1, 'nao_verificado' => 0, 'total' => 6])
        ->and(CatalogoProcessos::resumo('atendimento'))->toBe(['sim' => 0, 'parcial' => 2, 'nao' => 2, 'nao_verificado' => 0, 'total' => 4])
        ->and(CatalogoProcessos::resumo('contrato'))->toBe(['sim' => 1, 'parcial' => 3, 'nao' => 2, 'nao_verificado' => 0, 'total' => 6])
        ->and(CatalogoProcessos::resumo('ponto'))->toBe(['sim' => 2, 'parcial' => 3, 'nao' => 0, 'nao_verificado' => 0, 'total' => 5]);

    // Todo processo do protótipo tem cobertura: só sobra "não verificado" onde está escrito o porquê.
    $semEscrito = collect(CatalogoProcessos::processos())->flatMap(fn ($p) => $p['transicoes'])
        ->filter(fn ($t) => $t['cobertura']['onde'] === 'Processo só do protótipo; o código do ERP ainda não foi conferido para esta transição.');
    expect($semEscrito->count())->toBe(0);
});
