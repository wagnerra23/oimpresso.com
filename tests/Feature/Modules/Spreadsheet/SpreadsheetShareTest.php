<?php

namespace Tests\Feature\Modules\Spreadsheet;

use Modules\Spreadsheet\Tests\Feature\SpreadsheetTestCase;

/**
 * Smoke do compartilhamento de Spreadsheet.
 *
 * Routes:
 *   - GET /spreadsheet/get-sheet/{id}/share  SpreadsheetController@getShareSpreadsheet
 *   - POST /spreadsheet/post-share-sheet      SpreadsheetController@postShareSpreadsheet
 *
 * R-SPRE-001 / R-SPRE-002: tenancy + autorização.
 */
class SpreadsheetShareTest extends SpreadsheetTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->actAsAdmin();
    }

    public function test_get_share_id_inexistente_nao_500(): void
    {
        $response = $this->get('/spreadsheet/get-sheet/999999999/share');

        $this->assertNotEquals(500, $response->getStatusCode());
        // Pode ser 200 (modal vazio), 302 (redirect), 404 (not found) ou 403.
        $this->assertContains($response->getStatusCode(), [200, 302, 403, 404]);
    }

    public function test_post_share_sheet_endpoint_existe(): void
    {
        $response = $this->post('/spreadsheet/post-share-sheet', []);

        $this->assertNotEquals(404, $response->getStatusCode(), 'POST /spreadsheet/post-share-sheet deve existir.');
        $this->assertNotEquals(405, $response->getStatusCode());
    }

    public function test_post_share_sheet_sem_dados_nao_500(): void
    {
        $response = $this->post('/spreadsheet/post-share-sheet', [
            'sheet_id' => null,
        ]);

        // Sem dados válidos: 302/422/403 — nunca 500.
        $this->assertNotEquals(500, $response->getStatusCode());
    }

    public function test_guest_nao_acessa_share(): void
    {
        auth()->logout();
        session()->flush();

        $response = $this->get('/spreadsheet/get-sheet/1/share');

        $this->assertContains($response->getStatusCode(), [302, 401]);
    }
}
