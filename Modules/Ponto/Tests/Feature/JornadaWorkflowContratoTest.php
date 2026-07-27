<?php

namespace Modules\Ponto\Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Modules\Ponto\Entities\Colaborador;
use Modules\Ponto\Entities\Intercorrencia;

/**
 * Contrato do workflow de intercorrência/aprovação — trio derivado do SDD
 * (agent `sdd-from-source`, ADR 0351).
 *
 * Cada teste cita o UC do `casos.md` da tela (G-2 do casos-gate, ADR 0264):
 *   Aprovacoes/Index.casos.md      → UC-APROV-01..04
 *   Intercorrencias/Show.casos.md  → UC-INTSHOW-01..03
 *
 * Os UC derivam do SDD §6.2 (CU-PONTO-05..07) + CU-PONTO-12, ancorados em
 * US-PONTO-003 (estados canon + trilha de aprovação) e US-PONTO-007 (Tier 0).
 *
 * Tier 0: biz=1 (WR2 interno) — NUNCA biz=4 (ROTA LIVRE, ADR 0101).
 * Sem RefreshDatabase: a lane ponto-pest proíbe (dropa schema + limpa seed biz=1).
 *
 * @covers-us US-PONTO-003 US-PONTO-007
 */
class JornadaWorkflowContratoTest extends PontoTestCase
{
    private const MARCADOR = 'SDD-WORKFLOW-CONTRATO';
    private const BIZ_ALHEIO = 99;

    protected function tearDown(): void
    {
        $this->limparFixtures();
        parent::tearDown();
    }

    private function limparFixtures(): void
    {
        try {
            Intercorrencia::withoutGlobalScopes()
                ->where('codigo', 'like', self::MARCADOR . '%')
                ->forceDelete();

            $ids = Colaborador::withoutGlobalScopes()
                ->where('matricula', 'like', self::MARCADOR . '%')
                ->pluck('id');

            if ($ids->isNotEmpty()) {
                Colaborador::withoutGlobalScopes()->whereIn('id', $ids)->delete();
            }
        } catch (\Throwable $e) {
            // schema ausente — cleanup best-effort
        }
    }

    private function precisaDeSchema(): void
    {
        foreach (['ponto_colaborador_config', 'ponto_intercorrencias'] as $t) {
            if (! Schema::hasTable($t)) {
                $this->markTestSkipped("Tabela {$t} ausente — schema do Ponto não migrado nesta lane.");
            }
        }
    }

    private function criarColaborador(?int $businessId = null): Colaborador
    {
        $colab = new Colaborador();
        $colab->forceFill([
            'business_id'    => $businessId ?? $this->business->id,
            'user_id'        => $this->admin->id,
            'matricula'      => self::MARCADOR . '-' . uniqid(),
            'controla_ponto' => true,
        ])->save();

        return $colab;
    }

    /**
     * Cria intercorrência direto no banco (sem passar pelo global scope na leitura),
     * pra poder montar cenário cross-tenant.
     */
    private function criarIntercorrencia(array $attrs = []): string
    {
        $businessId = $attrs['business_id'] ?? $this->business->id;
        $colaborador = $attrs['colaborador_config_id'] ?? $this->criarColaborador($businessId)->id;
        $id = (string) Str::uuid();

        DB::table('ponto_intercorrencias')->insert(array_merge([
            'id'                    => $id,
            'business_id'           => $businessId,
            'colaborador_config_id' => $colaborador,
            'codigo'                => self::MARCADOR . '-' . substr($id, 0, 8),
            'tipo'                  => 'ATESTADO_MEDICO',
            'data'                  => '2019-03-11',
            'dia_todo'              => 1,
            'justificativa'         => 'Fixture de contrato SDD — atestado médico.',
            'estado'                => Intercorrencia::ESTADO_PENDENTE,
            'prioridade'            => 'NORMAL',
            'solicitante_id'        => $this->admin->id,
            'created_at'            => now(),
            'updated_at'            => now(),
        ], array_diff_key($attrs, ['business_id' => null, 'colaborador_config_id' => null]), [
            'business_id'           => $businessId,
            'colaborador_config_id' => $colaborador,
        ]));

        return $id;
    }

    private function lerCru(string $id): ?object
    {
        return DB::table('ponto_intercorrencias')->where('id', $id)->first();
    }

    // =====================================================================
    // Aprovacoes/Index
    // =====================================================================

    /**
     * UC-APROV-01 · Rejeitar exige motivo registrado. [must]
     *
     * Contrato: CU-PONTO-06 (SDD §6.2) + US-PONTO-003 (aceitação nomeia
     * `motivo_rejeicao`). Rejeição sem justificativa é passivo trabalhista.
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function uc_aprov_01_rejeitar_exige_motivo(): void
    {
        $this->actAsAdmin();
        $this->precisaDeSchema();

        $id = $this->criarIntercorrencia();

        $this->from('/ponto/aprovacoes')
            ->post("/ponto/aprovacoes/{$id}/rejeitar", []) // sem motivo
            ->assertSessionHasErrors('motivo');

        $this->assertSame(
            Intercorrencia::ESTADO_PENDENTE,
            $this->lerCru($id)->estado,
            'Rejeição sem motivo não pode mudar o estado — a trilha (motivo_rejeicao) '
            . 'é o que sustenta a decisão em reclamatória (CU-PONTO-06).'
        );
    }

    /**
     * UC-APROV-02 · Aprovação em lote não decide fora do meu empregador. [must][T0]
     *
     * Contrato: CU-PONTO-07 (SDD §6.5) + US-PONTO-007 + ADR 0093.
     * O lote recebe LISTA DE IDS do cliente — o vetor mais fácil de forjar do módulo.
     * Hoje a defesa é única (global scope); este UC é o alarme (SDD §9 D-5).
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function uc_aprov_02_lote_nao_decide_fora_do_meu_empregador(): void
    {
        $this->actAsAdmin();
        $this->precisaDeSchema();

        if ((int) $this->business->id === self::BIZ_ALHEIO) {
            $this->markTestSkipped('Business logado colide com o business fictício do teste.');
        }

        $meu    = $this->criarIntercorrencia();
        $alheio = $this->criarIntercorrencia(['business_id' => self::BIZ_ALHEIO]);

        $this->from('/ponto/aprovacoes')
            ->post('/ponto/aprovacoes/lote', ['ids' => [$meu, $alheio]]);

        $this->assertSame(
            Intercorrencia::ESTADO_PENDENTE,
            $this->lerCru($alheio)->estado,
            'Intercorrência de OUTRO empregador não pode ser aprovada por lote forjado '
            . '(Tier 0 IRREVOGÁVEL, ADR 0093).'
        );
        $this->assertNull(
            $this->lerCru($alheio)->aprovador_id,
            'Nenhum aprovador pode ser gravado em intercorrência de outro empregador.'
        );
    }

    /**
     * UC-APROV-03 · A fila abre no que está pendente. [should]
     *
     * Contrato: CU-PONTO-06 + AprovacaoController@index (default ESTADO_PENDENTE)
     * + US-PONTO-003 (6 estados canon).
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function uc_aprov_03_fila_abre_no_pendente(): void
    {
        $this->actAsAdmin();
        $this->precisaDeSchema();

        $this->criarIntercorrencia();

        $resp = $this->inertiaGet('/ponto/aprovacoes');
        $this->assertInertiaComponent($resp, 'Ponto/Aprovacoes/Index');

        $this->assertSame(
            Intercorrencia::ESTADO_PENDENTE,
            $resp->json('props.filtros.estado'),
            'Sem filtro explícito, a fila abre no pendente — é caixa de entrada, '
            . 'não histórico (CU-PONTO-06).'
        );

        $contagens = $this->inertiaPartialContagens();
        $this->assertNotNull(
            $contagens,
            'O painel precisa informar quantas intercorrências existem por estado — '
            . 'é a única visão de backlog do gestor.'
        );
        $this->assertArrayHasKey(
            Intercorrencia::ESTADO_PENDENTE,
            $contagens,
            'A contagem precisa cobrir o estado pendente (US-PONTO-003).'
        );
    }

    /** @return array<string,int>|null */
    private function inertiaPartialContagens(): ?array
    {
        $manifestPath = public_path('build-inertia/manifest.json');
        $version = file_exists($manifestPath) ? md5_file($manifestPath) : '1';

        $resp = $this->withHeaders([
            'X-Inertia'                   => 'true',
            'X-Inertia-Version'           => $version,
            'X-Inertia-Partial-Data'      => 'contagens',
            'X-Inertia-Partial-Component' => 'Ponto/Aprovacoes/Index',
            'Accept'                      => 'text/html',
        ])->get('/ponto/aprovacoes');

        return $resp->json('props.contagens');
    }

    /**
     * UC-APROV-04 · Urgente sobe na fila. [should]
     *
     * Contrato: CU-PONTO-06 + SDD §5.3 F5 (ordenação por FIELD(prioridade,...)).
     * `orderBy('prioridade')` puro ordenaria ALFABETICAMENTE (NORMAL antes de
     * URGENTE) e inverteria a fila em silêncio.
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function uc_aprov_04_urgente_sobe_na_fila(): void
    {
        $this->actAsAdmin();
        $this->precisaDeSchema();

        // A normal é criada DEPOIS — se a ordem fosse só por data, ela viria antes.
        $this->criarIntercorrencia(['prioridade' => 'URGENTE']);
        $this->criarIntercorrencia(['prioridade' => 'NORMAL']);

        $manifestPath = public_path('build-inertia/manifest.json');
        $version = file_exists($manifestPath) ? md5_file($manifestPath) : '1';

        $resp = $this->withHeaders([
            'X-Inertia'                   => 'true',
            'X-Inertia-Version'           => $version,
            'X-Inertia-Partial-Data'      => 'aprovacoes',
            'X-Inertia-Partial-Component' => 'Ponto/Aprovacoes/Index',
            'Accept'                      => 'text/html',
        ])->get('/ponto/aprovacoes');
        $resp->assertStatus(200);

        $prioridades = collect($resp->json('props.aprovacoes.data') ?? [])
            ->pluck('prioridade')
            ->filter()
            ->values();

        if ($prioridades->isEmpty()) {
            $this->markTestSkipped('Fila vazia nesta base — sem dado pra ordenar.');
        }

        $posUrgente = $prioridades->search('URGENTE');
        $posNormal  = $prioridades->search('NORMAL');

        if ($posUrgente === false || $posNormal === false) {
            $this->markTestSkipped('Base sem as duas prioridades simultaneamente.');
        }

        $this->assertLessThan(
            $posNormal,
            $posUrgente,
            'Intercorrência URGENTE precisa vir antes da NORMAL — priorização não pode '
            . 'depender de sorte (CU-PONTO-06).'
        );
    }

    // =====================================================================
    // Intercorrencias/Show
    // =====================================================================

    /**
     * UC-INTSHOW-01 · Só rascunho pode ser editado. [must]
     *
     * Contrato: CU-PONTO-05 + US-PONTO-003 (ciclo RASCUNHO → PENDENTE → ...)
     * + IntercorrenciaController@edit (abort_unless RASCUNHO, 403).
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function uc_intshow_01_so_rascunho_pode_ser_editado(): void
    {
        $this->actAsAdmin();
        $this->precisaDeSchema();

        $aprovada = $this->criarIntercorrencia(['estado' => Intercorrencia::ESTADO_APROVADA]);

        $this->get("/ponto/intercorrencias/{$aprovada}/edit")
            ->assertStatus(403);

        $this->assertSame(
            Intercorrencia::ESTADO_APROVADA,
            $this->lerCru($aprovada)->estado,
            'Intercorrência já decidida não volta a ser editável — mexer nela por fora '
            . 'quebra a trilha da decisão (CU-PONTO-05).'
        );
    }

    /**
     * UC-INTSHOW-02 · Intercorrência de outro empregador → 404. [must][T0]
     *
     * Contrato: CU-PONTO-12 + US-PONTO-007 + ADR 0093 + LGPD Art. 7º II.
     * O detalhe expõe motivo médico e justificativa em texto livre.
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function uc_intshow_02_intercorrencia_de_outro_empregador_da_404(): void
    {
        $this->actAsAdmin();
        $this->precisaDeSchema();

        if ((int) $this->business->id === self::BIZ_ALHEIO) {
            $this->markTestSkipped('Business logado colide com o business fictício do teste.');
        }

        $alheio = $this->criarIntercorrencia(['business_id' => self::BIZ_ALHEIO]);

        $this->inertiaGet("/ponto/intercorrencias/{$alheio}")
            ->assertStatus(404); // nunca 200 com o conteúdo alheio
    }

    /**
     * UC-INTSHOW-03 · O detalhe mostra quem decidiu e por quê. [must]
     *
     * Contrato: CU-PONTO-06 + US-PONTO-003 (aprovador_id, aprovado_em, motivo_rejeicao).
     * Pareia com UC-APROV-01: aquele garante que o motivo EXISTE, este que ele APARECE.
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function uc_intshow_03_detalhe_mostra_quem_decidiu_e_por_que(): void
    {
        $this->actAsAdmin();
        $this->precisaDeSchema();

        $motivo = 'Atestado sem CID legível — reapresentar.';
        $id = $this->criarIntercorrencia([
            'estado'          => Intercorrencia::ESTADO_REJEITADA,
            'aprovador_id'    => $this->admin->id,
            'motivo_rejeicao' => $motivo,
        ]);

        $resp = $this->inertiaGet("/ponto/intercorrencias/{$id}");
        $this->assertInertiaComponent($resp, 'Ponto/Intercorrencias/Show');

        $inc = $resp->json('props.intercorrencia');

        $this->assertSame(
            Intercorrencia::ESTADO_REJEITADA,
            $inc['estado'] ?? null,
            'O detalhe precisa mostrar o estado da decisão.'
        );
        $this->assertSame(
            $motivo,
            $inc['motivo_rejeicao'] ?? null,
            'O motivo da rejeição precisa aparecer no detalhe — exigir a justificativa na '
            . 'entrada e não exibi-la na saída é trilha que não serve pra nada (CU-PONTO-06).'
        );
        $this->assertNotEmpty(
            $inc['aprovador']['nome'] ?? null,
            'Quem decidiu precisa estar identificado no documento (US-PONTO-003).'
        );
    }
}
