<?php

declare(strict_types=1);

namespace Modules\Financeiro\Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Financeiro\Models\ContaBancaria;
use Modules\Financeiro\Models\Concerns\BusinessScopeImpl;

/**
 * Fix B4 · /financeiro/extrato (sem id) — resolver de navegação.
 *
 * O sidebar/topnav apontam pra /financeiro/extrato SEM contaBancariaId, mas a
 * tela de detalhe (extrato.index) exige id numérico (whereNumber). Antes do fix
 * o link dava 404. ExtratoController::selecionar agora resolve pra primeira
 * conta do business (orderBy id) ou manda cadastrar conta.
 *
 * Cobre:
 *   (a) GET /financeiro/extrato com business QUE TEM conta → 302 pra
 *       /financeiro/extrato/{idDaPrimeiraContaDoBusiness}.
 *   (b) GET /financeiro/extrato com business SEM conta → 302 pra
 *       /financeiro/contas-bancarias (cadastrar conta).
 *   (c) /financeiro/extrato/{id} continua entregando a página Inertia (smoke).
 *   (d) Multi-tenant Tier 0 — conta de outro business NUNCA é o alvo do redirect.
 *
 * NÃO usa RefreshDatabase (UltimatePOS legacy migrations não rodam em SQLite).
 * Cria complemento `fin_contas_bancarias` + `accounts` e limpa no tearDown.
 */
class ExtratoNavRedirectTest extends FinanceiroTestCase
{
    private int $contaBancariaId = 0;
    private int $accountId = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actAsAdmin();

        // Cria account + complemento ContaBancaria pro business atual.
        $this->accountId = DB::table('accounts')->insertGetId([
            'business_id'    => $this->business->id,
            'name'           => 'Inter NavTest ' . uniqid(),
            'account_number' => '99887766',
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);

        $this->contaBancariaId = DB::table('fin_contas_bancarias')->insertGetId([
            'business_id'                => $this->business->id,
            'account_id'                 => $this->accountId,
            'banco_codigo'               => '077',
            'agencia'                    => '0001',
            'carteira'                   => '112',
            'beneficiario_documento'     => '12.345.678/0001-99',
            'beneficiario_razao_social'  => 'Empresa NavTest',
            'ativo_para_boleto'          => true,
            'saldo_cached'               => 1000.00,
            'saldo_atualizado_em'        => now(),
            'created_at'                 => now(),
            'updated_at'                 => now(),
        ]);
    }

    protected function tearDown(): void
    {
        if ($this->contaBancariaId > 0) {
            DB::table('fin_contas_bancarias')->where('id', $this->contaBancariaId)->delete();
        }
        if ($this->accountId > 0) {
            DB::table('accounts')->where('id', $this->accountId)->delete();
        }
        parent::tearDown();
    }

    public function test_extrato_sem_id_redireciona_pra_primeira_conta_do_business(): void
    {
        // Alvo esperado = menor id de conta do PRÓPRIO business (orderBy id),
        // computado sem global scope pra ser determinístico mesmo se o DB dev
        // já tiver outras contas seedadas no business.
        $expectedId = (int) ContaBancaria::query()
            ->withoutGlobalScope(BusinessScopeImpl::class)
            ->where('business_id', $this->business->id)
            ->orderBy('id')
            ->value('id');

        $this->assertGreaterThan(0, $expectedId, 'Pré-condição: business deve ter ao menos uma conta.');

        $response = $this->get('/financeiro/extrato');

        $response->assertStatus(302);
        $response->assertRedirect('/financeiro/extrato/' . $expectedId);
    }

    public function test_extrato_sem_id_sem_conta_manda_cadastrar_conta(): void
    {
        // Simula um business SEM nenhuma conta bancária (id sintético que não
        // existe no DB dev). Sem auth, can('superadmin') = false → global scope
        // ativo, então a query não vê contas de nenhum outro business.
        $emptyBusinessId = (int) DB::table('businesses')->max('id') + 9999;

        auth()->logout();
        session([
            'user.business_id' => $emptyBusinessId,
            'business.id'      => $emptyBusinessId,
        ]);

        $response = $this->get('/financeiro/extrato');

        $response->assertStatus(302);
        $response->assertRedirect('/financeiro/contas-bancarias');
    }

    public function test_extrato_com_id_ainda_entrega_pagina_inertia(): void
    {
        if (! Schema::hasTable('fin_extrato_lancamentos')) {
            $this->markTestSkipped('Migração fin_extrato_lancamentos ainda não aplicada — rodar php artisan migrate.');
        }

        // Rota de detalhe intacta — não pode quebrar com a adição do resolver.
        $response = $this->inertiaGet('/financeiro/extrato/' . $this->contaBancariaId);

        $this->assertSame(200, $response->status());
    }

    public function test_multi_tenant_redirect_nunca_aponta_pra_conta_de_outro_business(): void
    {
        // Cria account + conta em OUTRO business (id sintético).
        $otherBusinessId = (int) DB::table('businesses')->max('id') + 9999;
        $otherAccountId = DB::table('accounts')->insertGetId([
            'business_id' => $otherBusinessId,
            'name'        => 'Outro biz NavTest',
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
            // Acessando como NOSSO business: o redirect resolve pra uma conta do
            // nosso business, NUNCA pra conta do outro business.
            $response = $this->get('/financeiro/extrato');

            $response->assertStatus(302);
            $response->assertRedirectContains('/financeiro/extrato/');
            $this->assertStringNotContainsString(
                '/financeiro/extrato/' . $otherContaId,
                (string) $response->headers->get('Location'),
                'Redirect vazou conta de outro business (Tier 0 violado).'
            );

            // E a conta alvo do redirect pertence ao nosso business.
            $targetId = (int) str_replace('/financeiro/extrato/', '', parse_url(
                (string) $response->headers->get('Location'),
                PHP_URL_PATH
            ));
            $targetBusinessId = (int) ContaBancaria::query()
                ->withoutGlobalScope(BusinessScopeImpl::class)
                ->where('id', $targetId)
                ->value('business_id');
            $this->assertSame(
                $this->business->id,
                $targetBusinessId,
                'Conta alvo do redirect não pertence ao business autenticado.'
            );
        } finally {
            DB::table('fin_contas_bancarias')->where('id', $otherContaId)->delete();
            DB::table('accounts')->where('id', $otherAccountId)->delete();
        }
    }
}
