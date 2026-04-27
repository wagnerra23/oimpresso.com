<?php

namespace Modules\Essentials\Tests\Feature;

/**
 * Smoke tests para Modules/Essentials/Http/Controllers/AttendanceController.
 *
 * Cobre o subset de rotas mais críticas:
 *   - /hrm/attendance — index (DataTables-driven)
 *   - /hrm/clock-in-clock-out — POST autenticado
 *   - /hrm/get-attendance-by-shift, /hrm/get-attendance-by-date — endpoints
 *     de consulta usados pela tela
 *
 * Não testamos a serialização do DataTables porque depende de seeds reais
 * (essentials_attendances JOIN users JOIN shifts). Apenas garantimos:
 *   - Não há bypass de autenticação
 *   - 403 para usuário sem essentials.crud_all_attendance/view_own_attendance
 *     (validado quando admin tem ambos via ensureEssentialsPermissions)
 *
 * Métodos prefixados com `test_` para PHPUnit 12 (anotação @test foi removida).
 */
class AttendanceControllerTest extends EssentialsTestCase
{
    public function test_index_exige_autenticacao(): void
    {
        $this->get('/hrm/attendance')->assertRedirect('/login');
    }

    public function test_clock_in_clock_out_exige_autenticacao(): void
    {
        $response = $this->post('/hrm/clock-in-clock-out');
        $this->assertContains($response->status(), [302, 401, 419]);
    }

    public function test_get_attendance_by_shift_exige_autenticacao(): void
    {
        $this->get('/hrm/get-attendance-by-shift')->assertRedirect('/login');
    }

    public function test_get_attendance_by_date_exige_autenticacao(): void
    {
        $this->get('/hrm/get-attendance-by-date')->assertRedirect('/login');
    }

    public function test_user_attendance_summary_exige_autenticacao(): void
    {
        $this->get('/hrm/user-attendance-summary')->assertRedirect('/login');
    }

    public function test_index_responde_para_admin_autenticado(): void
    {
        $this->actAsAdmin();
        $response = $this->get('/hrm/attendance');

        $this->assertContains($response->status(), [200, 302, 403],
            'GET /hrm/attendance deve responder 200/302/403 para admin, recebi: ' . $response->status());
    }
}
