<?php

namespace Tests\Feature\Modules\Spreadsheet;

use Modules\Spreadsheet\Entities\Spreadsheet;
use Modules\Spreadsheet\Tests\Feature\SpreadsheetTestCase;

/**
 * CRUD do Spreadsheet — store/update/destroy + folders.
 *
 * Routes (Modules/Spreadsheet/Routes/web.php):
 *   - POST /spreadsheet/sheets                  store
 *   - PUT /spreadsheet/sheets/{id}              update
 *   - DELETE /spreadsheet/sheets/{id}           destroy
 *   - POST /spreadsheet/add-folder              addFolder
 *   - POST /spreadsheet/move-to-folder          moveToFolder
 */
class SpreadsheetCrudTest extends SpreadsheetTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->actAsAdmin();
    }

    public function test_store_sem_dados_retorna_validacao(): void
    {
        $response = $this->post('/spreadsheet/sheets', []);

        $this->assertContains(
            $response->getStatusCode(),
            [302, 400, 403, 419, 422]
        );
        $this->assertNotEquals(500, $response->getStatusCode());
    }

    public function test_destroy_id_inexistente_nao_500(): void
    {
        $response = $this->delete('/spreadsheet/sheets/999999999');

        $this->assertNotEquals(500, $response->getStatusCode());
    }

    public function test_add_folder_endpoint_existe(): void
    {
        $response = $this->post('/spreadsheet/add-folder', []);

        $this->assertNotEquals(404, $response->getStatusCode(), 'POST /spreadsheet/add-folder deve existir.');
        $this->assertNotEquals(405, $response->getStatusCode());
    }

    public function test_move_to_folder_endpoint_existe(): void
    {
        $response = $this->post('/spreadsheet/move-to-folder', []);

        $this->assertNotEquals(404, $response->getStatusCode(), 'POST /spreadsheet/move-to-folder deve existir.');
        $this->assertNotEquals(405, $response->getStatusCode());
    }

    public function test_spreadsheet_model_tabela_tem_business_id(): void
    {
        $this->assertTrue(class_exists(Spreadsheet::class));

        $sheet = new Spreadsheet;
        $table = $sheet->getTable();

        $this->assertTrue(
            \Schema::hasColumn($table, 'business_id'),
            "Tabela {$table} deve ter business_id para tenancy."
        );
    }
}
