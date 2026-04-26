<?php

namespace Tests\Feature\Modules\Spreadsheet;

use Modules\Spreadsheet\Tests\Feature\SpreadsheetTestCase;

/**
 * Smoke + auth/redirect das rotas web do Spreadsheet (prefix /spreadsheet).
 *
 * Stack de middlewares (Modules/Spreadsheet/Routes/web.php:14):
 *   ['web', 'authh', 'auth', 'SetSessionData', 'language', 'timezone',
 *    'AdminSidebarMenu']
 *
 * Routes:
 *   - resource sheets (except edit)              SpreadsheetController
 *   - GET get-sheet/{id}/share                   getShareSpreadsheet
 *   - POST post-share-sheet                      postShareSpreadsheet
 *   - POST add-folder                            addFolder
 *   - POST move-to-folder                        moveToFolder
 *
 * Guarda contrato: rotas existem, redirect pra login se guest, sem 500
 * com user logado.
 */
class SpreadsheetRoutesAuthTest extends SpreadsheetTestCase
{
    public function test_guest_redireciona_em_sheets_index(): void
    {
        auth()->logout();
        session()->flush();

        $response = $this->get('/spreadsheet/sheets');

        $this->assertContains($response->getStatusCode(), [302, 401]);
    }

    public function test_admin_acessa_sheets_index_sem_500(): void
    {
        $this->actAsAdmin();

        $response = $this->get('/spreadsheet/sheets');

        // Pode dar 403 se moduleUtil->hasThePermissionInSubscription falhar
        // sem subscription ativa — o importante é não estourar 500.
        $this->assertContains($response->getStatusCode(), [200, 302, 403]);
        $this->assertNotEquals(500, $response->getStatusCode());
    }

    public function test_admin_acessa_sheets_create_sem_500(): void
    {
        $this->actAsAdmin();

        $response = $this->get('/spreadsheet/sheets/create');

        $this->assertContains($response->getStatusCode(), [200, 302, 403]);
        $this->assertNotEquals(500, $response->getStatusCode());
        $this->assertNotEquals(404, $response->getStatusCode());
    }

    public function test_sheets_edit_nao_existe(): void
    {
        $this->actAsAdmin();

        // Resource declarado com ->except(['edit']) — então /sheets/{id}/edit
        // deve dar 404/405, não responder.
        $response = $this->get('/spreadsheet/sheets/1/edit');

        $this->assertContains(
            $response->getStatusCode(),
            [404, 405, 302, 403],
            'Rota sheets.edit foi explicitamente excluída do resource.'
        );
    }

    public function test_show_id_inexistente_nao_500(): void
    {
        $this->actAsAdmin();

        $response = $this->get('/spreadsheet/sheets/999999999');

        $this->assertNotEquals(500, $response->getStatusCode());
        $this->assertContains($response->getStatusCode(), [200, 302, 403, 404]);
    }
}
