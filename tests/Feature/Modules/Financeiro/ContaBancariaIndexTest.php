<?php

namespace Tests\Feature\Modules\Financeiro;

use Modules\Financeiro\Tests\Feature\FinanceiroTestCase;

/**
 * Regressao: /financeiro/contas-bancarias dava 500 em prod com user logado
 * porque o controller selecionava `accounts.account_type` — coluna removida
 * pela migration core 2019_10_18_155633_create_account_types_table.php
 * (DROP COLUMN). O FK novo é `accounts.account_type_id`.
 *
 * Esse teste hits a rota com user real e garante 200 (sem SQL exception).
 * Roda contra DB dev real (mesmo pattern do CategoriaCrudTest).
 */
class ContaBancariaIndexTest extends FinanceiroTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->actAsAdmin();
    }

    public function test_index_responde_200_e_nao_quebra_em_accounts_account_type(): void
    {
        $response = $this->inertiaGet('/financeiro/contas-bancarias');

        $response->assertStatus(200);

        $payload = json_decode($response->getContent(), true);
        $this->assertIsArray($payload);
        $this->assertArrayHasKey('props', $payload);
        $this->assertArrayHasKey('accounts', $payload['props']);
        $this->assertArrayHasKey('bancos_suportados', $payload['props']);
    }

    public function test_resposta_nao_inclui_coluna_removida_account_type(): void
    {
        $response = $this->inertiaGet('/financeiro/contas-bancarias');
        $response->assertStatus(200);

        $payload = json_decode($response->getContent(), true);
        $accounts = $payload['props']['accounts'] ?? [];

        if (empty($accounts)) {
            $this->markTestSkipped('Nenhuma account no business pra inspecionar shape.');
        }

        $first = $accounts[0];
        $this->assertArrayNotHasKey('account_type', $first, 'account_type foi removida — não deve voltar do controller.');
        $this->assertArrayHasKey('id', $first);
        $this->assertArrayHasKey('name', $first);
    }
}
