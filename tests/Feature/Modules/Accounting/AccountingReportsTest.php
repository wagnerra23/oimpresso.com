<?php

namespace Tests\Feature\Modules\Accounting;

use Modules\Accounting\Tests\Feature\AccountingTestCase;

/**
 * Smoke das rotas /report/accounting/* — ReportController.
 *
 * Controller: Modules\Accounting\Http\Controllers\ReportController
 * Routes: Modules/Accounting/Http/routes.php:113-136
 *
 * Cobre os relatórios principais (Trial Balance, Ledger, Balance Sheet,
 * P&L, Cash Flow, Journal, Budget Overview, AR/AP Ageing).
 *
 * Não valida shape do CSV/PDF — só que o controller não estoura 500.
 */
class AccountingReportsTest extends AccountingTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->actAsAdmin();
    }

    /**
     * @dataProvider reportRoutes
     */
    public function test_report_route_responde_sem_500(string $route): void
    {
        $response = $this->get($route);

        $this->assertContains(
            $response->getStatusCode(),
            [200, 302, 403],
            "Rota {$route} retornou status inesperado."
        );
        $this->assertNotEquals(500, $response->getStatusCode(), "Rota {$route} estourou 500.");
        $this->assertNotEquals(404, $response->getStatusCode(), "Rota {$route} sumiu (404).");
    }

    public static function reportRoutes(): array
    {
        return [
            'index' => ['/report/accounting'],
            'balance_sheet' => ['/report/accounting/balance_sheet'],
            'cash_flow' => ['/report/accounting/cash_flow'],
            'profit_and_loss' => ['/report/accounting/profit_and_loss'],
            'ledger' => ['/report/accounting/ledger'],
            'trial_balance' => ['/report/accounting/trial_balance'],
            'journal' => ['/report/accounting/journal'],
            'budget_overview' => ['/report/accounting/budget_overview'],
            'ar_ageing_summary' => ['/report/accounting/accounts_receivable_ageing_summary'],
            'ar_ageing_detail' => ['/report/accounting/accounts_receivable_ageing_detail'],
            'ap_ageing_summary' => ['/report/accounting/accounts_payable_ageing_summary'],
            'ap_ageing_detail' => ['/report/accounting/accounts_payable_ageing_detail'],
        ];
    }

    public function test_dashboard_get_totals_existe(): void
    {
        $response = $this->get('/accounting/dashboard/get_totals');

        $this->assertNotEquals(404, $response->getStatusCode(), 'Endpoint AJAX dashboard.get_totals deve existir.');
        $this->assertNotEquals(500, $response->getStatusCode());
    }
}
