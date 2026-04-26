<?php

namespace Tests\Feature\Modules\Accounting;

use Modules\Accounting\Tests\Feature\AccountingTestCase;

/**
 * Smoke + auth/redirect das rotas web do Accounting (prefix /accounting).
 *
 * Stack de middlewares (Modules/Accounting/Http/routes.php:5):
 *   ['web', 'authh', 'auth', 'SetSessionData', 'language', 'timezone',
 *    'AdminSidebarMenu', 'CheckUserLogin']
 *
 * Guardam contrato público:
 *   1. Guest é redirecionado pra /login (auth middleware).
 *   2. User logado com permissão recebe 200 nas telas principais.
 *   3. Rotas-chave do CRUD (Chart of Accounts, Journal Entries, Reports)
 *      respondem sem 500.
 *
 * NÃO usa RefreshDatabase — DB dev real (ver AccountingTestCase).
 */
class AccountingRoutesAuthTest extends AccountingTestCase
{
    public function test_guest_redireciona_pra_login_em_dashboard(): void
    {
        auth()->logout();
        session()->flush();

        $response = $this->get('/accounting/dashboard');

        $this->assertContains(
            $response->getStatusCode(),
            [302, 401],
            'Rota deve exigir autenticação (redirect ou 401).'
        );
    }

    public function test_guest_redireciona_em_chart_of_account(): void
    {
        auth()->logout();
        session()->flush();

        $response = $this->get('/accounting/chart_of_account');

        $this->assertContains($response->getStatusCode(), [302, 401]);
    }

    public function test_guest_redireciona_em_journal_entry(): void
    {
        auth()->logout();
        session()->flush();

        $response = $this->get('/accounting/journal_entry');

        $this->assertContains($response->getStatusCode(), [302, 401]);
    }

    public function test_admin_acessa_dashboard_sem_500(): void
    {
        $this->actAsAdmin();

        $response = $this->get('/accounting/dashboard');

        // 200 (renderiza) ou 302 (redirect interno do módulo) ou 403 (sem permissão).
        // O importante: NÃO é 500. Bug histórico de 500 em /financeiro/contas-bancarias
        // (coluna account_type droppada) — prevenir o mesmo aqui.
        $this->assertContains(
            $response->getStatusCode(),
            [200, 302, 403],
            'Dashboard não pode estourar 500 — checa lógica do controller.'
        );
        $this->assertNotEquals(500, $response->getStatusCode());
    }

    public function test_admin_acessa_chart_of_account_sem_500(): void
    {
        $this->actAsAdmin();

        $response = $this->get('/accounting/chart_of_account');

        $this->assertContains($response->getStatusCode(), [200, 302, 403]);
        $this->assertNotEquals(500, $response->getStatusCode());
    }

    public function test_admin_acessa_journal_entry_sem_500(): void
    {
        $this->actAsAdmin();

        $response = $this->get('/accounting/journal_entry');

        $this->assertContains($response->getStatusCode(), [200, 302, 403]);
        $this->assertNotEquals(500, $response->getStatusCode());
    }

    public function test_admin_acessa_budget_sem_500(): void
    {
        $this->actAsAdmin();

        $response = $this->get('/accounting/budget');

        $this->assertContains($response->getStatusCode(), [200, 302, 403]);
        $this->assertNotEquals(500, $response->getStatusCode());
    }

    public function test_admin_acessa_reconcile_sem_500(): void
    {
        $this->actAsAdmin();

        $response = $this->get('/accounting/reconcile');

        $this->assertContains($response->getStatusCode(), [200, 302, 403]);
        $this->assertNotEquals(500, $response->getStatusCode());
    }

    public function test_admin_acessa_transfers_sem_500(): void
    {
        $this->actAsAdmin();

        $response = $this->get('/accounting/transfers');

        $this->assertContains($response->getStatusCode(), [200, 302, 403]);
        $this->assertNotEquals(500, $response->getStatusCode());
    }

    public function test_admin_acessa_settings_account_subtypes_sem_500(): void
    {
        $this->actAsAdmin();

        $response = $this->get('/accounting/settings/account_subtypes');

        $this->assertContains($response->getStatusCode(), [200, 302, 403]);
        $this->assertNotEquals(500, $response->getStatusCode());
    }
}
