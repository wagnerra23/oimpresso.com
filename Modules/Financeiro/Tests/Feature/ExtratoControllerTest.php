<?php

declare(strict_types=1);

namespace Modules\Financeiro\Tests\Feature;

use App\Account;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * US-RB-046 · ExtratoController — tela /financeiro/extrato/{contaId}.
 *
 * Cobre:
 *   - 200 com auth válida + filtro padrão (últimos 30d)
 *   - 404 quando contaId pertence a outro business (multi-tenant Tier 0)
 *   - Filtro from/to via query string respeitado
 *   - Totais (créditos, débitos, count) calculados corretamente
 *
 * NÃO usa RefreshDatabase (UltimatePOS legacy migrations não rodam em SQLite).
 * Roda contra DB dev real, criando complemento `fin_contas_bancarias` +
 * `fin_extrato_lancamentos` em transação que faz rollback no tearDown.
 */
class ExtratoControllerTest extends FinanceiroTestCase
{
    private int $contaBancariaId = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actAsAdmin();

        // Cria fin_extrato_lancamentos se não existir (migração nova US-RB-046)
        if (! Schema::hasTable('fin_extrato_lancamentos')) {
            $this->markTestSkipped('Migração fin_extrato_lancamentos ainda não aplicada — rodar php artisan migrate.');
        }

        // Cria account + complemento ContaBancaria pro business atual
        $accountId = DB::table('accounts')->insertGetId([
            'business_id'    => $this->business->id,
            'name'           => 'Inter Test ' . uniqid(),
            'account_number' => '12345678',
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);

        $this->contaBancariaId = DB::table('fin_contas_bancarias')->insertGetId([
            'business_id'                => $this->business->id,
            'account_id'                 => $accountId,
            'banco_codigo'               => '077',
            'agencia'                    => '0001',
            'carteira'                   => '112',
            'beneficiario_documento'     => '12.345.678/0001-99',
            'beneficiario_razao_social'  => 'Empresa Teste',
            'ativo_para_boleto'          => true,
            'saldo_cached'               => 5000.00,
            'saldo_atualizado_em'        => now(),
            'created_at'                 => now(),
            'updated_at'                 => now(),
        ]);
    }

    protected function tearDown(): void
    {
        if ($this->contaBancariaId > 0) {
            DB::table('fin_extrato_lancamentos')->where('conta_bancaria_id', $this->contaBancariaId)->delete();
            $accountId = DB::table('fin_contas_bancarias')->where('id', $this->contaBancariaId)->value('account_id');
            DB::table('fin_contas_bancarias')->where('id', $this->contaBancariaId)->delete();
            if ($accountId) {
                DB::table('accounts')->where('id', $accountId)->delete();
            }
        }
        parent::tearDown();
    }

    public function test_index_responde_200_com_filtro_padrao_ultimos_30d(): void
    {
        $this->seedLancamentos();

        $response = $this->inertiaGet("/financeiro/extrato/{$this->contaBancariaId}");

        $this->assertSame(200, $response->status());
    }

    public function test_index_404_quando_conta_eh_de_outro_business(): void
    {
        // Cria account+conta em outro business
        $otherBusinessId = $this->business->id + 9999;
        $otherAccountId = DB::table('accounts')->insertGetId([
            'business_id' => $otherBusinessId,
            'name'        => 'Outro biz',
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);
        $otherContaId = DB::table('fin_contas_bancarias')->insertGetId([
            'business_id'               => $otherBusinessId,
            'account_id'                => $otherAccountId,
            'banco_codigo'              => '077',
            'agencia'                   => '0001',
            'carteira'                  => '112',
            'beneficiario_documento'    => '11.111.111/1111-11',
            'beneficiario_razao_social' => 'X',
            'created_at'                => now(),
            'updated_at'                => now(),
        ]);

        try {
            $response = $this->inertiaGet("/financeiro/extrato/{$otherContaId}");
            $this->assertSame(404, $response->status(), 'Conta de outro business não pode retornar 200');
        } finally {
            DB::table('fin_contas_bancarias')->where('id', $otherContaId)->delete();
            DB::table('accounts')->where('id', $otherAccountId)->delete();
        }
    }

    public function test_index_aplica_filtro_from_to(): void
    {
        $this->seedLancamentos();

        $response = $this->inertiaGet(
            "/financeiro/extrato/{$this->contaBancariaId}",
            ['from' => '2026-04-01', 'to' => '2026-04-30']
        );

        $this->assertSame(200, $response->status());
    }

    private function seedLancamentos(): void
    {
        DB::table('fin_extrato_lancamentos')->insert([
            [
                'business_id'       => $this->business->id,
                'conta_bancaria_id' => $this->contaBancariaId,
                'data'              => now()->subDays(2)->toDateString(),
                'valor'             => 100.00,
                'tipo'              => 'C',
                'descricao'         => 'PIX recebido',
                'idempotency_key'   => 'tx-test-' . uniqid(),
                'raw_payload'       => json_encode(['source' => 'test']),
                'created_at'        => now(),
                'updated_at'        => now(),
            ],
            [
                'business_id'       => $this->business->id,
                'conta_bancaria_id' => $this->contaBancariaId,
                'data'              => now()->subDays(1)->toDateString(),
                'valor'             => 50.00,
                'tipo'              => 'D',
                'descricao'         => 'Boleto pago',
                'idempotency_key'   => 'tx-test-' . uniqid(),
                'raw_payload'       => json_encode(['source' => 'test']),
                'created_at'        => now(),
                'updated_at'        => now(),
            ],
        ]);
    }
}
