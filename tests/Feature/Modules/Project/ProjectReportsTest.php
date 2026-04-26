<?php

namespace Tests\Feature\Modules\Project;

use Modules\Project\Tests\Feature\ProjectTestCase;

/**
 * Smoke dos relatórios do Project — ReportController + InvoiceController@getProjectInvoiceTaxReport.
 *
 * Routes:
 *   - GET /project/project-employee-timelog-reports  ReportController@getEmployeeTimeLogReport
 *   - GET /project/project-timelog-reports           ReportController@getProjectTimeLogReport
 *   - GET /project/project-reports                   ReportController@index
 *   - GET /project/project-invoice-tax-report        InvoiceController@getProjectInvoiceTaxReport
 */
class ProjectReportsTest extends ProjectTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->actAsAdmin();
    }

    public function test_project_reports_index_sem_500(): void
    {
        $response = $this->get('/project/project-reports');

        $this->assertContains($response->getStatusCode(), [200, 302, 403]);
        $this->assertNotEquals(500, $response->getStatusCode());
        $this->assertNotEquals(404, $response->getStatusCode());
    }

    public function test_employee_timelog_reports_sem_500(): void
    {
        $response = $this->get('/project/project-employee-timelog-reports');

        $this->assertContains($response->getStatusCode(), [200, 302, 403]);
        $this->assertNotEquals(500, $response->getStatusCode());
        $this->assertNotEquals(404, $response->getStatusCode());
    }

    public function test_project_timelog_reports_sem_500(): void
    {
        $response = $this->get('/project/project-timelog-reports');

        $this->assertContains($response->getStatusCode(), [200, 302, 403]);
        $this->assertNotEquals(500, $response->getStatusCode());
        $this->assertNotEquals(404, $response->getStatusCode());
    }

    public function test_project_invoice_tax_report_sem_500(): void
    {
        $response = $this->get('/project/project-invoice-tax-report');

        $this->assertContains($response->getStatusCode(), [200, 302, 403]);
        $this->assertNotEquals(500, $response->getStatusCode());
        $this->assertNotEquals(404, $response->getStatusCode());
    }
}
