<?php

namespace Modules\Financeiro\Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

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
 * @see \Modules\Financeiro\Http\Controllers\ConciliacaoController
 */
class ConciliacaoLeExtratoApiTest extends FinanceiroTestCase
{
    use DatabaseTransactions;

    private string $pfx = '';
    private int $contaId = 0;
    private int $accountId = 0;

    protected function setUp(): void
    {
        parent::setUp();

        if (DB::getDriverName() !== 'mysql') {
            $this->markTestSkipped('Requer MySQL (UltimatePOS legacy schema).');
        }

        $this->actAsAdmin();

        // Fase 1 depende da migration que adiciona `status` em fin_extrato_lancamentos.
        if (! Schema::hasTable('fin_extrato_lancamentos')
            || ! Schema::hasColumn('fin_extrato_lancamentos', 'status')) {
            $this->markTestSkipped('Migration Fase 1 (add_conciliacao_cols) ainda não aplicada.');
        }
        if (! Schema::hasTable('fin_titulos') || ! Schema::hasTable('fin_contas_bancarias')) {
            $this->markTestSkipped('Schema Financeiro incompleto.');
        }

        $this->pfx = 'PEST-F1-' . uniqid() . '-';

        // Conta bancária do tenant (FK de fin_extrato_lancamentos.conta_bancaria_id).
        $this->accountId = DB::table('accounts')->insertGetId([
            'business_id'    => $this->business->id,
            'name'           => $this->pfx . 'Conta',
            'account_number' => '999',
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);
        $this->contaId = DB::table('fin_contas_bancarias')->insertGetId([
            'business_id'               => $this->business->id,
            'account_id'                => $this->accountId,
            'banco_codigo'              => '077',
            'agencia'                   => '0001',
            'carteira'                  => '112',
            'beneficiario_documento'    => '12.345.678/0001-99',
            'beneficiario_razao_social' => 'Teste F1',
            'created_at'                => now(),
            'updated_at'                => now(),
        ]);
    }

    protected function tearDown(): void
    {
        // Backstop além do DatabaseTransactions: limpa só o que este teste criou.
        if ($this->business && $this->pfx !== '') {
            DB::table('fin_extrato_lancamentos')->where('business_id', $this->business->id)
                ->where('idempotency_key', 'like', $this->pfx . '%')->delete();
            DB::table('fin_titulos')->where('business_id', $this->business->id)
                ->where('cliente_descricao', 'like', $this->pfx . '%')->delete();
            if ($this->contaId) {
                DB::table('fin_contas_bancarias')->where('id', $this->contaId)->delete();
            }
            if ($this->accountId) {
                DB::table('accounts')->where('id', $this->accountId)->delete();
            }
        }

        parent::tearDown();
    }

    /** Insere uma linha de extrato API (fin_extrato_lancamentos). */
    private function linhaApi(array $over = []): int
    {
        return DB::table('fin_extrato_lancamentos')->insertGetId(array_merge([
            'business_id'       => $this->business->id,
            'conta_bancaria_id' => $this->contaId,
            'data'              => now()->toDateString(),
            'valor'             => 150.00,
            'tipo'              => 'C',
            'descricao'         => $this->pfx . 'PIX recebido',
            'idempotency_key'   => $this->pfx . uniqid(),
            'raw_payload'       => json_encode(['source' => 'test']),
            'created_at'        => now(),
            'updated_at'        => now(),
        ], $over));
    }

    /** Insere um Titulo aberto que case (valor + vencimento) com a linha. */
    private function tituloAberto(float $valor, string $vencimento): int
    {
        $row = [
            'business_id'       => $this->business->id,
            'tipo'              => 'receber',
            'status'            => 'aberto',
            'valor_total'       => $valor,
            'vencimento'        => $vencimento,
            'cliente_descricao' => $this->pfx . 'Cliente match',
            'created_by'        => $this->admin->id, // FK fin_titulos.created_by → users
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

    public function test_index_lista_linha_api_alem_de_ofx(): void
    {
        $this->linhaApi(['descricao' => $this->pfx . 'linha API visível']);

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
    }

    public function test_sugerir_matches_casa_linha_api_e_marca_na_tabela_do_extrato(): void
    {
        $venc = now()->toDateString();
        $this->tituloAberto(150.00, $venc);
        $apiId = $this->linhaApi(['valor' => 150.00, 'tipo' => 'C', 'data' => $venc]);

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
    }

    public function test_match_origem_api_atualiza_tabela_extrato(): void
    {
        $tituloId = $this->tituloAberto(150.00, now()->toDateString());
        $apiId = $this->linhaApi();

        $response = $this->from('/financeiro/conciliacao')->post(
            "/financeiro/conciliacao/{$apiId}/match",
            ['titulo_id' => $tituloId, 'origem' => 'api']
        );
        $response->assertRedirect('/financeiro/conciliacao');
        $response->assertSessionHasNoErrors();

        $linha = DB::table('fin_extrato_lancamentos')->where('id', $apiId)->first();
        $this->assertSame('conciliado', $linha->status);
        $this->assertSame($tituloId, (int) $linha->titulo_id);
    }

    public function test_ignorar_origem_api_atualiza_tabela_extrato(): void
    {
        $apiId = $this->linhaApi();

        $response = $this->from('/financeiro/conciliacao')->post(
            "/financeiro/conciliacao/{$apiId}/ignorar",
            ['origem' => 'api']
        );
        $response->assertRedirect('/financeiro/conciliacao');
        $response->assertSessionHasNoErrors();

        $linha = DB::table('fin_extrato_lancamentos')->where('id', $apiId)->first();
        $this->assertSame('ignorado', $linha->status);
    }

    /**
     * Tier 0: o UPDATE de match filtra por business_id da sessão. Uma linha que
     * pertence a OUTRO business não pode ser conciliada pela sessão atual.
     *
     * FK-safe: usamos um 2º business REAL do banco (não um id fabricado, que
     * violaria a FK business). A linha é do business B; a sessão é do business A
     * (o admin logado). O match não pode tocar a linha de B.
     */
    public function test_match_api_respeita_business_id_tier0(): void
    {
        $bizB = \App\Business::where('id', '!=', $this->business->id)->first();
        if (! $bizB) {
            $this->markTestSkipped('Precisa 2+ businesses reais no banco pra provar isolamento cross-tenant.');
        }

        // Conta bancária do business B (FK conta_bancaria_id).
        $accountB = DB::table('accounts')->insertGetId([
            'business_id' => $bizB->id, 'name' => $this->pfx . 'ContaB',
            'account_number' => '888', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $contaB = DB::table('fin_contas_bancarias')->insertGetId([
            'business_id' => $bizB->id, 'account_id' => $accountB,
            'banco_codigo' => '077', 'agencia' => '0001', 'carteira' => '112',
            'beneficiario_documento' => '00.000.000/0000-00', 'beneficiario_razao_social' => 'B',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $apiB = DB::table('fin_extrato_lancamentos')->insertGetId([
            'business_id'       => $bizB->id,
            'conta_bancaria_id' => $contaB,
            'data'              => now()->toDateString(),
            'valor'             => 150.00,
            'tipo'              => 'C',
            'descricao'         => $this->pfx . 'cross-tenant',
            'idempotency_key'   => $this->pfx . 'cross-' . uniqid(),
            'raw_payload'       => json_encode([]),
            'created_at'        => now(),
            'updated_at'        => now(),
        ]);

        try {
            // Sessão é do business A (admin logado em setUp). Tenta conciliar a linha de B.
            $tituloId = $this->tituloAberto(150.00, now()->toDateString());
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
    }
}
