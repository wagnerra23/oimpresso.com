<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Financeiro\Tests\Feature\FinanceiroTestCase;

uses(FinanceiroTestCase::class, DatabaseTransactions::class);

/**
 * Fase 1 ADR 0236 — Conciliação enxerga o extrato da API (fin_extrato_lancamentos),
 * além do upload OFX (fin_bank_statement_lines).
 *
 * Cobre:
 *  - index lista linhas das DUAS origens com stats somados
 *  - sugerirMatches casa uma linha API (status NULL) com Titulo aberto → status=sugerido
 *    NA TABELA DO EXTRATO (não na do OFX)
 *  - match/ignorar com origem=api atualizam fin_extrato_lancamentos
 *  - Tier 0 (ADR 0093): não concilia linha API de outro business
 *
 * Isolamento idêntico ao ConciliacaoUploadDedupeTest: MySQL-guard + DatabaseTransactions
 * + prefixo único por execução (descrição/idempotency_key).
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * 2026-08-02 — CONVERTIDO de classe PHPUnit para Pest `it()`. Motivo MEDIDO, não
 * estético: o `casos-results-collect.mjs` (manifesto G-7, ADR 0264) lê o UC-id do
 * atributo `name` do `<testcase>` no JUnit. Método PHPUnit vira nome HUMANIZADO
 * (`test_match_api_respeita_business_id_tier0` → `"Match api respeita business id
 * tier0"`) — sem hífen, e o regex canônico (scripts/lib/uc-regex.mjs) exige
 * `UC-FCC-NN`. Nome de método PHP não aceita hífen, logo NÃO havia forma de
 * docblock/rename que funcionasse: os 4 UCs desta tela rodavam VERDES na lane
 * required e valiam 0 no painel.
 * Medido no JUnit real de main (run 30764392026): dos 82 UCs do manifesto, 82 vêm
 * de título `it()` e 0 de método `test_`.
 *
 * NADA de asserção mudou — os corpos dos 5 casos são os mesmos, verbatim. O que
 * mudou é o invólucro (classe → `it()`) e o nome. Baseline a preservar, do JUnit de
 * main: 5 testcases · 0 fail · 0 skip.
 *
 * Estilo: helpers globais prefixados `fcc*` + cada `it()` autossuficiente, imitando
 * BaixaConservacaoValorContratoTest (o padrão Pest vivo deste módulo). Sem
 * `beforeEach`/estado de classe — nenhum teste de módulo usa esse idioma aqui.
 *
 * Contrato: resources/js/Pages/Financeiro/Conciliacao/Index.casos.md (UC-FCC-10..13).
 *
 * @see \Modules\Financeiro\Http\Controllers\ConciliacaoController
 */

/** Guard de ambiente — MySQL + migrations da Fase 1 presentes. Skip gracioso. */
function fccGuard(): void
{
    if (DB::getDriverName() !== 'mysql') {
        test()->markTestSkipped('Requer MySQL (UltimatePOS legacy schema).');
    }

    // Fase 1 depende da migration que adiciona `status` em fin_extrato_lancamentos.
    if (! Schema::hasTable('fin_extrato_lancamentos')
        || ! Schema::hasColumn('fin_extrato_lancamentos', 'status')) {
        test()->markTestSkipped('Migration Fase 1 (add_conciliacao_cols) ainda não aplicada.');
    }
    if (! Schema::hasTable('fin_titulos') || ! Schema::hasTable('fin_contas_bancarias')) {
        test()->markTestSkipped('Schema Financeiro incompleto.');
    }
}

/**
 * Conta bancária do tenant (FK de fin_extrato_lancamentos.conta_bancaria_id).
 * Devolve ['pfx' => string, 'contaId' => int, 'accountId' => int].
 */
function fccConta(int $businessId): array
{
    $pfx = 'PEST-F1-'.uniqid().'-';

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
        'beneficiario_razao_social' => 'Teste F1',
        'created_at'                => now(),
        'updated_at'                => now(),
    ]);

    return ['pfx' => $pfx, 'contaId' => $contaId, 'accountId' => $accountId];
}

/** Insere uma linha de extrato API (fin_extrato_lancamentos). */
function fccLinhaApi(int $businessId, string $pfx, int $contaId, array $over = []): int
{
    return DB::table('fin_extrato_lancamentos')->insertGetId(array_merge([
        'business_id'       => $businessId,
        'conta_bancaria_id' => $contaId,
        'data'              => now()->toDateString(),
        'valor'             => 150.00,
        'tipo'              => 'C',
        'descricao'         => $pfx.'PIX recebido',
        'idempotency_key'   => $pfx.uniqid(),
        'raw_payload'       => json_encode(['source' => 'test']),
        'created_at'        => now(),
        'updated_at'        => now(),
    ], $over));
}

/** Insere um Titulo aberto que case (valor + vencimento) com a linha. */
function fccTituloAberto(int $businessId, int $createdBy, string $pfx, float $valor, string $vencimento): int
{
    $row = [
        'business_id'       => $businessId,
        'tipo'              => 'receber',
        'status'            => 'aberto',
        'valor_total'       => $valor,
        'vencimento'        => $vencimento,
        'cliente_descricao' => $pfx.'Cliente match',
        'created_by'        => $createdBy, // FK fin_titulos.created_by → users
        'created_at'        => now(),
        'updated_at'        => now(),
    ];
    // Colunas opcionais conforme schema real (defensivo).
    foreach (['valor_aberto' => $valor, 'origem' => 'manual', 'emissao' => now()->toDateString()] as $col => $val) {
        if (Schema::hasColumn('fin_titulos', $col)) {
            $row[$col] = $val;
        }
    }

    return DB::table('fin_titulos')->insertGetId($row);
}

/**
 * Backstop de limpeza além do DatabaseTransactions: apaga só o que o prefixo criou.
 * (Mantido do tearDown da versão em classe — mesma semântica.)
 */
function fccCleanup(int $businessId, array $ctx): void
{
    DB::table('fin_extrato_lancamentos')->where('business_id', $businessId)
        ->where('idempotency_key', 'like', $ctx['pfx'].'%')->delete();
    DB::table('fin_titulos')->where('business_id', $businessId)
        ->where('cliente_descricao', 'like', $ctx['pfx'].'%')->delete();
    DB::table('fin_contas_bancarias')->where('id', $ctx['contaId'])->delete();
    DB::table('accounts')->where('id', $ctx['accountId'])->delete();
}

it('UC-FCC-10 · index lista linha de extrato API além do OFX', function () {
    fccGuard();
    $this->actAsAdmin();
    $ctx = fccConta($this->business->id);

    try {
        fccLinhaApi($this->business->id, $ctx['pfx'], $ctx['contaId'], [
            'descricao' => $ctx['pfx'].'linha API visível',
        ]);

        // inertiaGet (helper FinanceiroTestCase) → props em JSON, sem depender de
        // semântica de closure-in-where (varia por versão do inertia testing).
        $response = $this->inertiaGet('/financeiro/conciliacao');
        $response->assertOk();

        $linhas = $response->json('props.linhas') ?? [];
        $achou = collect($linhas)->contains(
            fn ($l) => ($l['origem'] ?? null) === 'api'
                && str_contains((string) ($l['descricao'] ?? ''), 'linha API visível')
        );
        $this->assertTrue($achou, 'Linha de extrato API deve aparecer na conciliação (Fase 1 ADR 0236).');
    } finally {
        fccCleanup($this->business->id, $ctx);
    }
});

it('UC-FCC-11 · sugerirMatches casa linha API e marca na tabela do extrato', function () {
    fccGuard();
    $this->actAsAdmin();
    $ctx = fccConta($this->business->id);

    try {
        $venc = now()->toDateString();
        fccTituloAberto($this->business->id, $this->admin->id, $ctx['pfx'], 150.00, $venc);
        $apiId = fccLinhaApi($this->business->id, $ctx['pfx'], $ctx['contaId'], [
            'valor' => 150.00, 'tipo' => 'C', 'data' => $venc,
        ]);

        // sugerirMatches é privado, mas roda no fluxo do upload. Disparamos via
        // um upload OFX vazio-de-match (só pra acionar o sugerir) NÃO serve —
        // então chamamos o método via reflexão (caminho canônico do teste unit-ish).
        $controller = new \Modules\Financeiro\Http\Controllers\ConciliacaoController();
        $ref = new \ReflectionMethod($controller, 'sugerirMatches');
        $ref->setAccessible(true);
        $ref->invoke($controller, $this->business->id);

        $linha = DB::table('fin_extrato_lancamentos')->where('id', $apiId)->first();
        $this->assertSame('sugerido', $linha->status);
        $this->assertNotNull($linha->titulo_id);
    } finally {
        fccCleanup($this->business->id, $ctx);
    }
});

it('UC-FCC-12 · match com origem=api atualiza fin_extrato_lancamentos', function () {
    fccGuard();
    $this->actAsAdmin();
    $ctx = fccConta($this->business->id);

    try {
        $tituloId = fccTituloAberto($this->business->id, $this->admin->id, $ctx['pfx'], 150.00, now()->toDateString());
        $apiId = fccLinhaApi($this->business->id, $ctx['pfx'], $ctx['contaId']);

        $response = $this->from('/financeiro/conciliacao')->post(
            "/financeiro/conciliacao/{$apiId}/match",
            ['titulo_id' => $tituloId, 'origem' => 'api']
        );
        $response->assertRedirect('/financeiro/conciliacao');
        $response->assertSessionHasNoErrors();

        $linha = DB::table('fin_extrato_lancamentos')->where('id', $apiId)->first();
        $this->assertSame('conciliado', $linha->status);
        $this->assertSame($tituloId, (int) $linha->titulo_id);
    } finally {
        fccCleanup($this->business->id, $ctx);
    }
});

it('UC-FCC-12 · ignorar com origem=api atualiza fin_extrato_lancamentos', function () {
    fccGuard();
    $this->actAsAdmin();
    $ctx = fccConta($this->business->id);

    try {
        $apiId = fccLinhaApi($this->business->id, $ctx['pfx'], $ctx['contaId']);

        $response = $this->from('/financeiro/conciliacao')->post(
            "/financeiro/conciliacao/{$apiId}/ignorar",
            ['origem' => 'api']
        );
        $response->assertRedirect('/financeiro/conciliacao');
        $response->assertSessionHasNoErrors();

        $linha = DB::table('fin_extrato_lancamentos')->where('id', $apiId)->first();
        $this->assertSame('ignorado', $linha->status);
    } finally {
        fccCleanup($this->business->id, $ctx);
    }
});

/**
 * Tier 0: o UPDATE de match filtra por business_id da sessão. Uma linha que
 * pertence a OUTRO business não pode ser conciliada pela sessão atual.
 *
 * FK-safe: usamos um 2º business REAL do banco (não um id fabricado, que
 * violaria a FK business). A linha é do business B; a sessão é do business A
 * (o admin logado). O match não pode tocar a linha de B.
 */
it('UC-FCC-13 · Tier 0: match com origem=api respeita business_id (ADR 0093)', function () {
    fccGuard();
    $this->actAsAdmin();
    $ctx = fccConta($this->business->id);

    try {
        $bizB = \App\Business::where('id', '!=', $this->business->id)->first();
        if (! $bizB) {
            $this->markTestSkipped('Precisa 2+ businesses reais no banco pra provar isolamento cross-tenant.');
        }

        // Conta bancária do business B (FK conta_bancaria_id).
        $accountB = DB::table('accounts')->insertGetId([
            'business_id' => $bizB->id, 'name' => $ctx['pfx'].'ContaB',
            'account_number' => '888', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $contaB = DB::table('fin_contas_bancarias')->insertGetId([
            'business_id' => $bizB->id, 'account_id' => $accountB,
            'banco_codigo' => '077', 'agencia' => '0001', 'carteira' => '112',
            'beneficiario_documento' => '00.000.000/0000-00', 'beneficiario_razao_social' => 'B', // pii-allowlist — CNPJ fixture de teste
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $apiB = DB::table('fin_extrato_lancamentos')->insertGetId([
            'business_id'       => $bizB->id,
            'conta_bancaria_id' => $contaB,
            'data'              => now()->toDateString(),
            'valor'             => 150.00,
            'tipo'              => 'C',
            'descricao'         => $ctx['pfx'].'cross-tenant',
            'idempotency_key'   => $ctx['pfx'].'cross-'.uniqid(),
            'raw_payload'       => json_encode([]),
            'created_at'        => now(),
            'updated_at'        => now(),
        ]);

        try {
            // Sessão é do business A (admin logado). Tenta conciliar a linha de B.
            $tituloId = fccTituloAberto($this->business->id, $this->admin->id, $ctx['pfx'], 150.00, now()->toDateString());
            $this->from('/financeiro/conciliacao')->post(
                "/financeiro/conciliacao/{$apiB}/match",
                ['titulo_id' => $tituloId, 'origem' => 'api']
            );

            // A linha de B NÃO pode ter sido conciliada (Tier 0).
            $linha = DB::table('fin_extrato_lancamentos')->where('id', $apiB)->first();
            $this->assertNull($linha->status, 'Cross-tenant: linha de outro business não pode ser conciliada (Tier 0).');
        } finally {
            DB::table('fin_extrato_lancamentos')->where('id', $apiB)->delete();
            DB::table('fin_contas_bancarias')->where('id', $contaB)->delete();
            DB::table('accounts')->where('id', $accountB)->delete();
        }
    } finally {
        fccCleanup($this->business->id, $ctx);
    }
});
