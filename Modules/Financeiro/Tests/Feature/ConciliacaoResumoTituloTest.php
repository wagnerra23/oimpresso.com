<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Financeiro\Tests\Feature\FinanceiroTestCase;

uses(FinanceiroTestCase::class, DatabaseTransactions::class);

/**
 * FIN-4b (2026-09-23) — a Conciliação passa a entregar o que a forma do protótipo
 * (TelaConciliacao) pede e a produção não tinha:
 *  - `resumo` (prop DEFERIDA): período + entradas/saídas do extrato, das DUAS origens,
 *    todas as situações — mesmo universo dos 4 contadores de `stats`;
 *  - `linhas[].titulo`: resumo do título vinculado, pra coluna "Sistema".
 *
 * É VALOR na tela (regra mestre §"CÁLCULO DE VALOR"): o total é provado por DOIS
 * caminhos independentes — (a) soma à mão das fixtures; (b) soma dos `valor` que a
 * própria tela recebe em `props.linhas` pras mesmas linhas.
 *
 * Os asserts de total são por DELTA (depois − antes): o business do FinanceiroTestCase
 * é o primeiro do banco e pode já ter extrato; o delta isola o que este teste inseriu.
 *
 * Contrato: resources/js/Pages/Financeiro/Conciliacao/Index.casos.md (UC-FCC-14..16).
 *
 * @see \Modules\Financeiro\Http\Controllers\ConciliacaoController::resumoExtrato
 * @see \Modules\Financeiro\Http\Controllers\ConciliacaoController::anexarTitulos
 */

function fcrGuard(): void
{
    if (DB::getDriverName() !== 'mysql') {
        test()->markTestSkipped('Requer MySQL (UltimatePOS legacy schema).');
    }
    if (! Schema::hasTable('fin_extrato_lancamentos')
        || ! Schema::hasColumn('fin_extrato_lancamentos', 'status')) {
        test()->markTestSkipped('Migration Fase 1 (add_conciliacao_cols) ainda não aplicada.');
    }
    if (! Schema::hasTable('fin_bank_statement_lines') || ! Schema::hasTable('fin_titulos')) {
        test()->markTestSkipped('Schema Financeiro incompleto.');
    }
}

/** Conta bancária do tenant (FK de fin_extrato_lancamentos.conta_bancaria_id). */
function fcrConta(int $businessId, string $pfx): array
{
    $accountId = DB::table('accounts')->insertGetId([
        'business_id'    => $businessId,
        'name'           => $pfx.'Conta',
        'account_number' => '999',
        'created_at'     => now(),
        'updated_at'     => now(),
    ]);
    $contaId = DB::table('fin_contas_bancarias')->insertGetId([
        'business_id'               => $businessId,
        'account_id'                => $accountId,
        'banco_codigo'              => '077',
        'agencia'                   => '0001',
        'carteira'                  => '112',
        'beneficiario_documento'    => '00.000.000/0000-00', // pii-allowlist — CNPJ fixture de teste (nao-PII real)
        'beneficiario_razao_social' => 'Teste FIN-4b',
        'created_at'                => now(),
        'updated_at'                => now(),
    ]);

    return ['contaId' => $contaId, 'accountId' => $accountId];
}

function fcrLinhaOfx(int $businessId, string $pfx, array $over = []): int
{
    return DB::table('fin_bank_statement_lines')->insertGetId(array_merge([
        'business_id'    => $businessId,
        'fitid'          => $pfx.uniqid(),
        'data_movimento' => now()->toDateString(),
        'descricao'      => $pfx.'linha OFX',
        'valor'          => 10.00,
        'tipo'           => 'credit',
        'status'         => 'pendente',
        'created_at'     => now(),
        'updated_at'     => now(),
    ], $over));
}

function fcrLinhaApi(int $businessId, string $pfx, int $contaId, array $over = []): int
{
    return DB::table('fin_extrato_lancamentos')->insertGetId(array_merge([
        'business_id'       => $businessId,
        'conta_bancaria_id' => $contaId,
        'data'              => now()->toDateString(),
        'valor'             => 10.00,
        'tipo'              => 'C',
        'descricao'         => $pfx.'linha API',
        'idempotency_key'   => $pfx.uniqid(),
        'raw_payload'       => json_encode(['source' => 'test']),
        'created_at'        => now(),
        'updated_at'        => now(),
    ], $over));
}

function fcrTitulo(int $businessId, int $createdBy, string $pfx, float $valor, string $vencimento): int
{
    $row = [
        'business_id'       => $businessId,
        'tipo'              => 'receber',
        'status'            => 'aberto',
        'valor_total'       => $valor,
        'vencimento'        => $vencimento,
        'cliente_descricao' => $pfx.'Cliente',
        'created_by'        => $createdBy,
        'created_at'        => now(),
        'updated_at'        => now(),
    ];
    foreach (['valor_aberto' => $valor, 'origem' => 'manual', 'emissao' => now()->toDateString()] as $col => $val) {
        if (Schema::hasColumn('fin_titulos', $col)) {
            $row[$col] = $val;
        }
    }

    return DB::table('fin_titulos')->insertGetId($row);
}

/** Partial reload da prop deferida `resumo`, com os headers que o cliente Inertia manda. */
function fcrResumo($test): array
{
    $manifestPath = public_path('build-inertia/manifest.json');
    $version = file_exists($manifestPath) ? md5_file($manifestPath) : '1';

    $resp = $test->withHeaders([
        'X-Inertia'                   => 'true',
        'X-Inertia-Version'           => $version,
        'X-Inertia-Partial-Component' => 'Financeiro/Conciliacao/Index',
        'X-Inertia-Partial-Data'      => 'resumo',
        'X-Requested-With'            => 'XMLHttpRequest',
        'Accept'                      => 'text/html',
    ])->get('/financeiro/conciliacao');

    $resp->assertOk();
    $resumo = $resp->json('props.resumo');
    expect($resumo)->toBeArray();

    return $resumo;
}

it('UC-FCC-14 · resumo soma as duas origens com o sinal que a tela mostra', function () {
    fcrGuard();
    $this->actAsAdmin();
    $biz = $this->business->id;
    $pfx = 'PEST-F4B-'.uniqid().'-';
    $conta = fcrConta($biz, $pfx);

    $antes = fcrResumo($this);

    // Fixtures com sinais e situações diferentes nas duas origens.
    fcrLinhaOfx($biz, $pfx, ['valor' => 100.50, 'tipo' => 'credit', 'data_movimento' => '1999-01-02']);
    fcrLinhaOfx($biz, $pfx, ['valor' => -30.25, 'tipo' => 'debit', 'status' => 'conciliado']);
    fcrLinhaApi($biz, $pfx, $conta['contaId'], ['valor' => 150.00, 'tipo' => 'C', 'data' => '2099-12-30']);
    fcrLinhaApi($biz, $pfx, $conta['contaId'], ['valor' => 20.00, 'tipo' => 'D', 'status' => 'ignorado']);
    // Excluída (soft delete) NÃO entra — nem no total, nem no período.
    fcrLinhaOfx($biz, $pfx, ['valor' => 999.00, 'data_movimento' => '1998-01-01', 'deleted_at' => now()]);

    $depois = fcrResumo($this);

    // Caminho (a): soma à mão. Entradas 100,50 + 150,00; saídas −30,25 − 20,00.
    expect(round($depois['entradas'] - $antes['entradas'], 2))->toBe(250.5);
    expect(round($depois['saidas'] - $antes['saidas'], 2))->toBe(-50.25);
    expect($depois['linhas'] - $antes['linhas'])->toBe(4);

    // Período: o mínimo vem do OFX, o máximo do API — as duas origens contam; a excluída não.
    expect($depois['periodo_inicio'])->toBe('1999-01-02');
    expect($depois['periodo_fim'])->toBe('2099-12-30');

    // Caminho (b): os mesmos 4 valores, lidos do que a tela recebe em props.linhas.
    $linhas = collect($this->inertiaGet('/financeiro/conciliacao', ['incluir_resolvidos' => 1])->json('props.linhas'))
        ->filter(fn ($l) => str_starts_with((string) $l['descricao'], $pfx));
    expect($linhas)->toHaveCount(4);
    $entradasTela = round($linhas->where('valor', '>', 0)->sum('valor'), 2);
    $saidasTela = round($linhas->where('valor', '<', 0)->sum('valor'), 2);
    expect($entradasTela)->toBe(round($depois['entradas'] - $antes['entradas'], 2));
    expect($saidasTela)->toBe(round($depois['saidas'] - $antes['saidas'], 2));
});

it('UC-FCC-15 · linha sugerida chega com o resumo do título vinculado', function () {
    fcrGuard();
    $this->actAsAdmin();
    $biz = $this->business->id;
    $pfx = 'PEST-F4B-'.uniqid().'-';

    $tituloId = fcrTitulo($biz, $this->admin->id, $pfx, 150.00, '2026-07-10');
    fcrLinhaOfx($biz, $pfx, [
        'valor' => 150.00, 'status' => 'sugerido', 'titulo_id' => $tituloId, 'match_score' => 0.93,
        'descricao' => $pfx.'sugerida',
    ]);
    fcrLinhaOfx($biz, $pfx, ['descricao' => $pfx.'sem titulo']);

    $linhas = collect($this->inertiaGet('/financeiro/conciliacao')->json('props.linhas'))
        ->keyBy('descricao');

    $com = $linhas->get($pfx.'sugerida');
    expect($com['titulo'])->toBeArray();
    expect($com['titulo']['id'])->toBe($tituloId);
    expect($com['titulo']['valor_total'])->toBe(150.0);
    expect($com['titulo']['vencimento'])->toBe('2026-07-10');
    expect($com['titulo']['descricao'])->toBe($pfx.'Cliente');

    expect($linhas->get($pfx.'sem titulo')['titulo'])->toBeNull();
});

it('UC-FCC-16 · Tier 0: extrato e título de outro business ficam fora (ADR 0093)', function () {
    fcrGuard();
    $this->actAsAdmin();
    $biz = $this->business->id;
    $pfx = 'PEST-F4B-'.uniqid().'-';

    $bizB = \App\Business::where('id', '!=', $biz)->first();
    if (! $bizB) {
        $this->markTestSkipped('Precisa 2+ businesses reais no banco pra provar isolamento cross-tenant.');
    }
    $contaB = fcrConta($bizB->id, $pfx);

    $antes = fcrResumo($this);
    fcrLinhaApi($bizB->id, $pfx, $contaB['contaId'], ['valor' => 777.00, 'tipo' => 'C']);
    fcrLinhaOfx($bizB->id, $pfx, ['valor' => -333.00, 'tipo' => 'debit']);
    $depois = fcrResumo($this);

    // Nada do business B entra no resumo do business A.
    expect(round($depois['entradas'] - $antes['entradas'], 2))->toBe(0.0);
    expect(round($depois['saidas'] - $antes['saidas'], 2))->toBe(0.0);
    expect($depois['linhas'] - $antes['linhas'])->toBe(0);

    // Linha de A apontando para título de B: o título NÃO é anexado.
    $tituloB = fcrTitulo($bizB->id, $this->admin->id, $pfx, 150.00, '2026-07-10');
    fcrLinhaOfx($biz, $pfx, [
        'status' => 'sugerido', 'titulo_id' => $tituloB, 'descricao' => $pfx.'aponta para B',
    ]);
    $linha = collect($this->inertiaGet('/financeiro/conciliacao')->json('props.linhas'))
        ->firstWhere('descricao', $pfx.'aponta para B');
    expect($linha)->not->toBeNull();
    expect($linha['titulo'])->toBeNull();
});
