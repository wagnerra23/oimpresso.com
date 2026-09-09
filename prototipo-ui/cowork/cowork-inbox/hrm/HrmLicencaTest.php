<?php

namespace Modules\Essentials\Tests\Feature;

use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Essentials\Entities\EssentialsLeave;
use Modules\Essentials\Entities\EssentialsLeaveType;
use Tests\TestCase;

/**
 * HRM · Licenças — prova mínima do trio (HRM-O5).
 *
 * ATENÇÃO: 4 casos NASCEM VERMELHOS de propósito — são os achados A2, A3 e A5
 * lidos no main (tree b719732f3188). Ficam verdes com os PRs 2, 3 e 4 do
 * pedido cowork-inbox/hrm/PEDIDO-CL-hrm.md.
 *
 * @group hrm
 */
class HrmLicencaTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User { /* factory do negócio piloto + essentials_module na subscription */ }

    /** UC-HRM-01 */
    public function test_indice_lista_licencas_do_negocio_com_contagem_coerente(): void
    {
        // Dado 3 licenças (2 pendentes) Quando GET /hrm/leave (ajax)
        // Então recordsTotal = 3 e o filtro status=pending devolve 2
        $this->markTestIncomplete('escrever com a factory do módulo');
    }

    /** UC-HRM-02 · ❌ nasce vermelho — achado A2 */
    public function test_recusa_licenca_com_fim_antes_do_inicio(): void
    {
        $admin = $this->admin();
        $tipo = EssentialsLeaveType::factory()->create(['business_id' => $admin->business_id]);

        $r = $this->actingAs($admin)->postJson('/hrm/leave', [
            'essentials_leave_type_id' => $tipo->id,
            'start_date' => '10/09/2026',
            'end_date'   => '01/09/2026',
            'reason'     => 'período invertido',
        ]);

        $r->assertStatus(422);
        $this->assertDatabaseCount('essentials_leaves', 0);
    }

    /** UC-HRM-15 · ❌ nasce vermelho — achado A2 */
    public function test_recusa_licenca_sem_motivo_e_sem_tipo(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->postJson('/hrm/leave', [
            'start_date' => '01/09/2026', 'end_date' => '02/09/2026', 'reason' => '',
        ])->assertStatus(422);

        $this->assertDatabaseCount('essentials_leaves', 0);
    }

    /** UC-HRM-03 · ❌ nasce vermelho — achado A3 */
    public function test_recusa_pedido_que_estoura_o_limite_do_tipo(): void
    {
        $admin = $this->admin();
        $tipo = EssentialsLeaveType::factory()->create([
            'business_id' => $admin->business_id,
            'max_leave_count' => 30, 'leave_count_interval' => 'year',
        ]);
        EssentialsLeave::factory()->create([
            'business_id' => $admin->business_id, 'user_id' => $admin->id,
            'essentials_leave_type_id' => $tipo->id, 'status' => 'approved',
            'start_date' => '2026-01-06', 'end_date' => '2026-01-27', // 22 dias
        ]);

        $r = $this->actingAs($admin)->postJson('/hrm/leave', [
            'essentials_leave_type_id' => $tipo->id,
            'start_date' => '07/09/2026', 'end_date' => '21/09/2026', // 15 dias -> 37 > 30
            'reason' => 'férias',
        ]);

        $r->assertStatus(422);
        $r->assertSee('8'); // saldo restante dito na mensagem
    }

    /** UC-HRM-09 · ❌ nasce vermelho — achado A3 (aprovar também estoura) */
    public function test_aprovar_licenca_que_estoura_o_limite_e_recusado(): void
    {
        $this->markTestIncomplete('espelha o caso acima via POST /hrm/change-status');
    }

    /** UC-HRM-05 · Tier 0 (ADR 0093) */
    public function test_tipo_de_licenca_de_outro_negocio_e_recusado(): void
    {
        $admin = $this->admin();
        $tipoAlheio = EssentialsLeaveType::factory()->create(['business_id' => 999999]);

        $this->actingAs($admin)->postJson('/hrm/leave', [
            'essentials_leave_type_id' => $tipoAlheio->id,
            'start_date' => '01/09/2026', 'end_date' => '02/09/2026', 'reason' => 'x',
        ])->assertStatus(422);
    }

    /** UC-HRM-04 */
    public function test_quem_tem_apenas_crud_own_leave_ve_somente_as_proprias(): void
    {
        $this->markTestIncomplete('criar 2 users + 2 licenças e conferir o escopo do índice');
    }

    /** UC-HRM-08 */
    public function test_trocar_situacao_exige_approve_leave_e_notifica_o_colaborador(): void
    {
        $this->markTestIncomplete('Notification::fake() + assertSentTo(LeaveStatusNotification)');
    }

    /** UC-HRM-14 */
    public function test_criar_para_varios_colaboradores_gera_referencia_com_prefixo_configurado(): void
    {
        $this->markTestIncomplete('essentials_settings.leave_ref_no_prefix = LIC -> LIC0001..');
    }

    /** UC-HRM-18 · ❌ nasce vermelho — achado A4 */
    public function test_excluir_tipo_de_licenca_em_uso_e_recusado_com_motivo(): void
    {
        // destroy() está VAZIO hoje: a rota responde 200 e nada acontece.
        $this->markTestIncomplete('vira verde com o PR-5 (destroy com guarda de uso)');
    }
}
