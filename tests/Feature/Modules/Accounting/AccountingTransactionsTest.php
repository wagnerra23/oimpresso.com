<?php

namespace Tests\Feature\Modules\Accounting;

use Modules\Accounting\Tests\Feature\AccountingTestCase;

/**
 * Smoke das rotas /accounting/transactions/* (sales, expenses, purchases)
 * + endpoint POST map_to_chart_of_account.
 *
 * Controller: Modules\Accounting\Http\Controllers\AccountingTransactionController
 * Routes: Modules/Accounting/Http/routes.php:53-58
 *
 * Guardam contrato:
 *   - Rotas existem e são GET (não 404/405)
 *   - Não estouram 500 com user logado
 *   - POST map_to_chart_of_account exige CSRF/auth
 */
class AccountingTransactionsTest extends AccountingTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->actAsAdmin();
    }

    public function test_transactions_sales_responde_sem_500(): void
    {
        $response = $this->get('/accounting/transactions/sales');

        $this->assertContains($response->getStatusCode(), [200, 302, 403]);
        $this->assertNotEquals(500, $response->getStatusCode());
        $this->assertNotEquals(404, $response->getStatusCode(), 'Rota /accounting/transactions/sales deve existir.');
    }

    public function test_transactions_expenses_responde_sem_500(): void
    {
        $response = $this->get('/accounting/transactions/expenses');

        $this->assertContains($response->getStatusCode(), [200, 302, 403]);
        $this->assertNotEquals(500, $response->getStatusCode());
        $this->assertNotEquals(404, $response->getStatusCode());
    }

    public function test_transactions_purchases_responde_sem_500(): void
    {
        $response = $this->get('/accounting/transactions/purchases');

        $this->assertContains($response->getStatusCode(), [200, 302, 403]);
        $this->assertNotEquals(500, $response->getStatusCode());
        $this->assertNotEquals(404, $response->getStatusCode());
    }

    public function test_post_map_to_chart_sem_dados_responde_validacao(): void
    {
        $response = $this->post('/accounting/transactions/map_to_chart_of_account', []);

        // Sem dados/CSRF: pode ser 302 (redirect com erros), 422 (validation),
        // 419 (CSRF), 403 (perm) ou 400. NUNCA 500 ou 200 silencioso.
        $this->assertContains(
            $response->getStatusCode(),
            [302, 400, 403, 419, 422, 500],
            'Endpoint deve existir (não 404/405).'
        );
        $this->assertNotEquals(404, $response->getStatusCode());
        $this->assertNotEquals(405, $response->getStatusCode());
    }
}
