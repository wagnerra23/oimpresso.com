<?php

declare(strict_types=1);

namespace Modules\Financeiro\Tests\Feature;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Financeiro\Http\Controllers\ExtratoController;
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
            'beneficiario_documento'     => fake()->numerify('##.###.###/####-##'),
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
        // Business sintético SEM conta. Chama selecionar() direto — o método só LÊ
        // ContaBancaria (sem FK) e evita o middleware auth/SetSessionData do UPos,
        // que sobrescreveria a session com o business do usuário autenticado.
        $emptyBusinessId = (int) DB::table('business')->max('id') + 9999;

        $request = Request::create('/financeiro/extrato', 'GET');
        $request->setLaravelSession($this->app['session']->driver());
        $request->session()->put('user.business_id', $emptyBusinessId);

        $response = (new ExtratoController())->selecionar($request);

        $this->assertStringContainsString('/financeiro/contas-bancarias', $response->getTargetUrl());
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
        // Outro business REAL — a FK fin_contas_bancarias.business_id → business.id
        // (ON DELETE CASCADE) exige que o business exista. Skip se o DB só tem 1.
        $otherBusinessId = (int) DB::table('business')
            ->where('id', '!=', $this->business->id)
            ->orderBy('id')
            ->value('id');
        if ($otherBusinessId === 0) {
            $this->markTestSkipped('Precisa de ≥2 business no DB pra testar isolamento cross-tenant.');
        }
        $otherAccountId = DB::table('accounts')->insertGetId([
            'business_id'    => $otherBusinessId,
            'name'           => 'Outro biz NavTest ' . uniqid(),
            'account_number' => '00000000',
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);
        $otherContaId = DB::table('fin_contas_bancarias')->insertGetId([
            'business_id'                => $otherBusinessId,
            'account_id'                 => $otherAccountId,
            'banco_codigo'               => '077',
            'agencia'                    => '0001',
            'carteira'                   => '112',
            'beneficiario_documento'     => fake()->numerify('##.###.###/####-##'),
            'beneficiario_razao_social'  => 'Outro biz',
            'ativo_para_boleto'          => true,
            'saldo_cached'               => 0,
            'saldo_atualizado_em'        => now(),
            'created_at'                 => now(),
            'updated_at'                 => now(),
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
