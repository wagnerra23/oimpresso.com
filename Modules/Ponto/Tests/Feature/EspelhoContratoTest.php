<?php

namespace Modules\Ponto\Tests\Feature;

use App\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Ponto\Entities\ApuracaoDia;
use Modules\Ponto\Entities\Colaborador;
use Modules\Ponto\Entities\Marcacao;

/**
 * Contrato do Espelho de Ponto — trio derivado do SDD (agent `sdd-from-source`, ADR 0351).
 *
 * Cada teste cita o UC do `casos.md` da tela (G-2 do casos-gate, ADR 0264):
 *   Espelho/Index.casos.md → UC-ESPIDX-01..03
 *   Espelho/Show.casos.md  → UC-ESPSHOW-01..05
 *
 * Os UC derivam do SDD §6.1 (CU-PONTO-01..04) + CU-PONTO-12/13, ancorados em
 * LEI (CLT Art. 66/71/74 §2º · Portaria MTP 671/2021) e na Blade legada
 * `Modules/Ponto/Resources/views/espelho/*.blade.php` — NUNCA no `Show.tsx`
 * (teste derivado do código é tautológico — proibicoes §5 2026-06-05).
 *
 * ⚠️ UC-ESPSHOW-01 é FAILING-FIRST por desenho: ele denuncia a regressão D-1 do
 * SDD §9 (o espelho lê `tem_divergencia`, atributo que NÃO existe — varredura
 * contada: 2 ocorrências no repo, ambas no EspelhoController; não é coluna, não é
 * accessor). Vermelho aqui é o ACHADO, não bug do teste. Correção é decisão [W].
 *
 * Tier 0: biz=1 (WR2 interno) — NUNCA biz=4 (ROTA LIVRE, ADR 0101).
 * Sem RefreshDatabase: a lane ponto-pest proíbe (dropa schema + limpa seed biz=1).
 *
 * @covers-us US-PONTO-005 US-PONTO-007 US-PONTO-008
 */
class EspelhoContratoTest extends PontoTestCase
{
    /** Marcador pra cleanup — todo fixture criado aqui carrega ele. */
    private const MARCADOR = 'SDD-ESPELHO-CONTRATO';

    /** Mês sintético bem no passado: não colide com dado real de biz=1. */
    private const ANO = 2019;
    private const MES = 3; // março/2019 — 31 dias

    protected function tearDown(): void
    {
        $this->limparFixtures();
        $this->removerBizAlheio(); // depois do cleanup: FK sem CASCADE (ver PontoTestCase)
        parent::tearDown();
    }

    private function limparFixtures(): void
    {
        try {
            $ids = Colaborador::withoutGlobalScopes()
                ->where('matricula', 'like', self::MARCADOR . '%')
                ->pluck('id');

            if ($ids->isNotEmpty()) {
                // ApuracaoDia é recalculável (não append-only) — delete direto OK.
                ApuracaoDia::withoutGlobalScopes()->whereIn('colaborador_config_id', $ids)->delete();
                // Marcacao é append-only (trigger + Eloquent) — remoção só via DB::table
                // no cleanup de teste, jamais em código de produção.
                DB::table('ponto_marcacoes')->whereIn('colaborador_config_id', $ids)->delete();
                Colaborador::withoutGlobalScopes()->whereIn('id', $ids)->delete();
            }
        } catch (\Throwable $e) {
            // schema ausente / driver sem suporte — cleanup best-effort
        }
    }

    private function precisaDeSchema(): void
    {
        foreach (['ponto_colaborador_config', 'ponto_apuracao_dia', 'ponto_marcacoes'] as $t) {
            if (! Schema::hasTable($t)) {
                $this->markTestSkipped("Tabela {$t} ausente — schema do Ponto não migrado nesta lane.");
            }
        }
    }

    /**
     * Cria um colaborador do business logado, marcado pro cleanup.
     */
    private function criarColaborador(array $extra = []): Colaborador
    {
        return Colaborador::create(array_merge([
            'business_id'    => $this->business->id,
            'user_id'        => $this->novoUserDoBusiness()->id,
            'matricula'      => self::MARCADOR . '-' . uniqid(),
            'controla_ponto' => true,
            'admissao'       => '2019-01-01',
        ], $extra));
    }

    private function mesRef(): string
    {
        return sprintf('%04d-%02d', self::ANO, self::MES);
    }

    /**
     * Pede as props diferidas (Inertia::defer) numa segunda passada.
     *
     * @param  array<int,string>  $props
     */
    private function inertiaPartial(string $url, array $props, string $component)
    {
        $manifestPath = public_path('build-inertia/manifest.json');
        $version = file_exists($manifestPath) ? md5_file($manifestPath) : '1';

        return $this->withHeaders([
            'X-Inertia'                  => 'true',
            'X-Inertia-Version'          => $version,
            'X-Inertia-Partial-Data'     => implode(',', $props),
            'X-Inertia-Partial-Component' => $component,
            'Accept'                     => 'text/html',
        ])->get($url);
    }

    // =====================================================================
    // Espelho/Show
    // =====================================================================

    /**
     * UC-ESPSHOW-01 · Dia com divergência de apuração aparece sinalizado. [must][V0]
     *
     * Contrato: CU-PONTO-02 (SDD §6.1) + US-PONTO-005 (aceitação cita Art. 66 e
     * Art. 71 §4º) + Blade legada, que contava por `estado === 'DIVERGENCIA'`.
     *
     * O assert é sobre COMPORTAMENTO ("o dia divergente aparece sinalizado"), não
     * sobre a chave literal do payload — há 2 correções legítimas (criar accessor
     * OU o controller passar a ler `estado`) e um assert por chave reprovaria uma
     * delas arbitrariamente.
     *
     * PREDIÇÃO: vermelho. Veredito real vem da lane, não desta leitura (G-7).
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function uc_espshow_01_dia_com_divergencia_aparece_sinalizado(): void
    {
        $this->actAsAdmin();
        $this->precisaDeSchema();

        $colaborador = $this->criarColaborador();

        // Estado do mundo: a apuração DETECTOU violação de interjornada (Art. 66).
        // É exatamente o que ApuracaoService::addDivergencia() grava.
        ApuracaoDia::create([
            'business_id'                    => $this->business->id,
            'colaborador_config_id'          => $colaborador->id,
            'data'                           => sprintf('%04d-%02d-11', self::ANO, self::MES),
            'realizada_trabalhada_minutos'   => 540,
            'interjornada_violacao_minutos'  => 120,
            'estado'                         => ApuracaoDia::ESTADO_DIVERGENCIA,
            'divergencias'                   => [[
                'codigo'    => 'interjornada_insuficiente',
                'mensagem'  => 'Interjornada de 9h abaixo do mínimo de 11h (Art. 66 CLT).',
            ]],
        ]);

        $url = "/ponto/espelho/{$colaborador->id}?mes=" . $this->mesRef();
        $resp = $this->inertiaPartial($url, ['totais', 'linhas'], 'Ponto/Espelho/Show');
        $resp->assertStatus(200);

        $props = $resp->json('props');

        // (a) O mês informa que existe pelo menos 1 dia divergente.
        $contador = $props['totais']['divergencias'] ?? null;
        $this->assertNotNull(
            $contador,
            'O espelho precisa informar quantos dias do mês estão divergentes (CU-PONTO-02).'
        );
        $this->assertGreaterThanOrEqual(
            1,
            (int) $contador,
            'Apuração gravada em estado DIVERGENCIA (violação de interjornada, Art. 66 CLT) '
            . 'não foi contada pelo espelho. A Blade legada contava por `estado`; o React lê '
            . '`tem_divergencia`, que não existe como coluna nem accessor (SDD §9 D-1).'
        );

        // (b) O dia específico está marcado na tabela dia-a-dia.
        $linhas = $props['linhas'] ?? [];
        $diaAlvo = collect($linhas)->firstWhere('data', sprintf('%04d-%02d-11', self::ANO, self::MES));
        $this->assertNotNull($diaAlvo, 'O dia com apuração precisa existir na tabela dia-a-dia.');
        $this->assertTrue(
            (bool) ($diaAlvo['divergencia'] ?? false),
            'O dia cuja apuração violou a CLT precisa vir sinalizado na linha (SDD §9 D-1).'
        );
    }

    /**
     * UC-ESPSHOW-02 · Espelho cobre todos os dias do mês, não só os com marcação. [must]
     *
     * Contrato: CU-PONTO-01 + charter §Goals ("todos os dias do mês") + Blade legada.
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function uc_espshow_02_espelho_cobre_todos_os_dias_do_mes(): void
    {
        $this->actAsAdmin();
        $this->precisaDeSchema();

        $colaborador = $this->criarColaborador();

        // Uma única apuração no mês — o espelho ainda assim deve trazer o mês inteiro.
        ApuracaoDia::create([
            'business_id'           => $this->business->id,
            'colaborador_config_id' => $colaborador->id,
            'data'                  => sprintf('%04d-%02d-05', self::ANO, self::MES),
            'estado'                => ApuracaoDia::ESTADO_CALCULADO,
        ]);

        $url = "/ponto/espelho/{$colaborador->id}?mes=" . $this->mesRef();
        $resp = $this->inertiaPartial($url, ['linhas'], 'Ponto/Espelho/Show');
        $resp->assertStatus(200);

        $linhas = $resp->json('props.linhas') ?? [];
        $diasDoMes = (int) date('t', mktime(0, 0, 0, self::MES, 1, self::ANO));

        $this->assertCount(
            $diasDoMes,
            $linhas,
            "O espelho de {$this->mesRef()} precisa ter {$diasDoMes} linhas (todos os dias), "
            . 'inclusive dias sem marcação — dia vazio é informação (falta/folga/feriado).'
        );
    }

    /**
     * UC-ESPSHOW-03 · Marcação anulada não conta como jornada. [must]
     *
     * Contrato: CU-PONTO-13 + US-PONTO-008 ("para corrigir: criar marcação com
     * origem=ANULACAO") + Portaria MTP 671/2021 (append-only).
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function uc_espshow_03_marcacao_anulada_nao_conta_como_jornada(): void
    {
        $this->actAsAdmin();
        $this->precisaDeSchema();

        $colaborador = $this->criarColaborador();
        $dia = sprintf('%04d-%02d-07', self::ANO, self::MES);

        // `usuario_criador_id` é obrigatório: coluna `int NOT NULL` sem default, guardada
        // pela FK `ponto_marcacoes_usuario_criador_id_foreign` → `users`. O Model NÃO
        // preenche (sem default, sem observer); quem exige é o Service —
        // `MarcacaoService::registrar()` lança `RuntimeException` se vier vazio. Este caso
        // monta o estado pelo Model de propósito, pra testar a LEITURA do espelho, então
        // precisa passar o campo à mão. Sem ele o MySQL usa o 0 implícito, a FK reprova
        // com 1452, e o caso morre no fixture — antes de exercer o contrato de anulação
        // (CU-PONTO-13 · Portaria MTP 671/2021). Os 6 testes verdes do módulo que criam
        // Marcacao pelo Model já passam o campo; só este arquivo não passava.
        Marcacao::create([
            'business_id'           => $this->business->id,
            'colaborador_config_id' => $colaborador->id,
            'momento'               => $dia . ' 08:00:00',
            'origem'                => Marcacao::ORIGEM_REP_P,
            'tipo'                  => Marcacao::TIPO_ENTRADA,
            'usuario_criador_id'    => $this->admin->id,
        ]);

        // A correção legal: NÃO edita a anterior — acrescenta a anulação.
        Marcacao::create([
            'business_id'           => $this->business->id,
            'colaborador_config_id' => $colaborador->id,
            'momento'               => $dia . ' 09:00:00',
            'origem'                => Marcacao::ORIGEM_ANULACAO,
            'tipo'                  => Marcacao::TIPO_ENTRADA,
            'usuario_criador_id'    => $this->admin->id,
        ]);

        $url = "/ponto/espelho/{$colaborador->id}?mes=" . $this->mesRef();
        $resp = $this->inertiaPartial($url, ['linhas'], 'Ponto/Espelho/Show');
        $resp->assertStatus(200);

        $linha = collect($resp->json('props.linhas') ?? [])->firstWhere('data', $dia);
        $this->assertNotNull($linha, 'O dia com marcação precisa existir no espelho.');

        $origens = collect($linha['marcacoes'] ?? [])->pluck('origem')->all();

        $this->assertContains(
            Marcacao::ORIGEM_REP_P,
            $origens,
            'A marcação original permanece no espelho — append-only preserva o registro.'
        );
        $this->assertNotContains(
            Marcacao::ORIGEM_ANULACAO,
            $origens,
            'Marcação de anulação não pode aparecer como jornada — somaria o registro '
            . 'corrigido E a correção, inflando a jornada apurada (CU-PONTO-13).'
        );
    }

    /**
     * UC-ESPSHOW-04 · Espelho de colaborador de outro empregador → 404. [must][T0]
     *
     * Contrato: CU-PONTO-12 + US-PONTO-007 + ADR 0093 + LGPD Art. 7º II.
     * biz=1 contra id fora do tenant — NUNCA biz=4 (ADR 0101).
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function uc_espshow_04_espelho_de_outro_empregador_da_404(): void
    {
        $this->actAsAdmin();
        $this->precisaDeSchema();

        // Colaborador que existe, mas pertence a OUTRO business (fictício, nunca biz=4).
        if ((int) $this->business->id === self::BIZ_ALHEIO_FICTICIO) {
            $this->markTestSkipped('Business logado colide com o business fictício do teste.');
        }
        $bizAlheio = $this->garantirBizAlheio();

        $alheioId = DB::table('ponto_colaborador_config')->insertGetId([
            'business_id'    => $bizAlheio,
            'user_id'        => $this->novoUserDoBusiness()->id,
            'matricula'      => self::MARCADOR . '-ALHEIO-' . uniqid(),
            'controla_ponto' => 1,
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);

        try {
            $this->inertiaGet("/ponto/espelho/{$alheioId}")
                ->assertStatus(404); // nunca 200 com dado alheio, nunca 500
        } finally {
            DB::table('ponto_colaborador_config')->where('id', $alheioId)->delete();
        }
    }

    /**
     * UC-ESPSHOW-05 · Totais e linhas chegam sob demanda, sem quebrar o contrato. [should]
     *
     * Contrato: CU-PONTO-01 + charter §Automation hooks (Inertia::defer em totais/linhas)
     * + RUNBOOK-inertia-defer-pattern.
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function uc_espshow_05_props_caras_chegam_sob_demanda(): void
    {
        $this->actAsAdmin();
        $this->precisaDeSchema();

        $colaborador = $this->criarColaborador();
        $url = "/ponto/espelho/{$colaborador->id}?mes=" . $this->mesRef();

        // 1ª passada: o cabeçalho barato já vem resolvido.
        $primeira = $this->inertiaGet($url);
        $this->assertInertiaComponent($primeira, 'Ponto/Espelho/Show');
        $this->assertNotNull(
            $primeira->json('props.colaborador'),
            'O cabeçalho do colaborador é eager — chega já na primeira resposta.'
        );

        // 2ª passada: quando peço, o dado caro chega COMPLETO.
        $segunda = $this->inertiaPartial($url, ['totais', 'linhas'], 'Ponto/Espelho/Show');
        $segunda->assertStatus(200);

        $this->assertNotNull(
            $segunda->json('props.totais'),
            'Prop diferida `totais` precisa resolver quando solicitada — senão a tela '
            . 'fica em skeleton eterno.'
        );
        $this->assertNotEmpty(
            $segunda->json('props.linhas') ?? [],
            'Prop diferida `linhas` precisa resolver quando solicitada.'
        );
    }

    // =====================================================================
    // Espelho/Index
    // =====================================================================

    /**
     * UC-ESPIDX-01 · Só entra na lista quem tem controle de ponto ativo e não foi desligado. [must]
     *
     * Contrato: CU-PONTO-04 + charter §Mission + Blade `espelho/index.blade.php`
     * + CLT Art. 74 §2º (o registro é de quem está sujeito a controle).
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function uc_espidx_01_lista_so_traz_quem_controla_ponto_e_esta_ativo(): void
    {
        $this->actAsAdmin();
        $this->precisaDeSchema();

        $ativo      = $this->criarColaborador();
        $semControle = $this->criarColaborador(['controla_ponto' => false]);
        $desligado  = $this->criarColaborador(['desligamento' => '2019-02-01']);

        $resp = $this->inertiaPartial('/ponto/espelho', ['colaboradores'], 'Ponto/Espelho/Index');
        $resp->assertStatus(200);

        $ids = collect($resp->json('props.colaboradores.data') ?? [])->pluck('id')->all();

        $this->assertContains($ativo->id, $ids, 'Colaborador ativo com controle de ponto deve aparecer.');
        $this->assertNotContains(
            $semControle->id,
            $ids,
            'Quem não tem controle de ponto não tem jornada a auditar (CU-PONTO-04).'
        );
        $this->assertNotContains(
            $desligado->id,
            $ids,
            'Colaborador desligado não entra no fechamento do mês (CU-PONTO-04).'
        );
    }

    /**
     * UC-ESPIDX-02 · A lista não atravessa empregadores. [must][T0]
     *
     * Contrato: CU-PONTO-12 + US-PONTO-007 + ADR 0093 + LGPD Art. 7º II.
     * A lista expõe matrícula/CPF/e-mail — PII a cada carga.
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function uc_espidx_02_lista_nao_atravessa_empregadores(): void
    {
        $this->actAsAdmin();
        $this->precisaDeSchema();

        if ((int) $this->business->id === self::BIZ_ALHEIO_FICTICIO) {
            $this->markTestSkipped('Business logado colide com o business fictício do teste.');
        }
        $bizAlheio = $this->garantirBizAlheio();

        // O MEU, que TEM de aparecer — é a pré-condição anti-vácuo (ver asserção abaixo).
        $meu = $this->criarColaborador();

        $alheioId = DB::table('ponto_colaborador_config')->insertGetId([
            'business_id'    => $bizAlheio,
            'user_id'        => $this->novoUserDoBusiness()->id,
            'matricula'      => self::MARCADOR . '-ALHEIO-' . uniqid(),
            'controla_ponto' => 1,
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);

        try {
            $resp = $this->inertiaPartial('/ponto/espelho', ['colaboradores'], 'Ponto/Espelho/Index');
            $resp->assertStatus(200);

            $ids = collect($resp->json('props.colaboradores.data') ?? [])->pluck('id')->all();

            // Sem isto, "o alheio não está na lista" seria verdade por LISTA VAZIA — o
            // caso ficaria verde com o escopo quebrado, com a query quebrada, ou com a
            // tela em branco. É o mesmo par que os casos verdes do módulo já usam.
            $this->assertContains(
                $meu->id,
                $ids,
                'O colaborador do MEU empregador tem de estar na lista — senão o caso '
                . 'não exerce isolamento nenhum, só constata que a lista veio vazia.'
            );
            $this->assertNotContains(
                $alheioId,
                $ids,
                'Colaborador de outro empregador não pode aparecer na lista do espelho (Tier 0, ADR 0093).'
            );
        } finally {
            DB::table('ponto_colaborador_config')->where('id', $alheioId)->delete();
        }
    }

    /**
     * UC-ESPIDX-03 · O mês escolhido viaja junto para o espelho. [should]
     *
     * Contrato: CU-PONTO-04 + charter §Goals + comentário literal da Blade legada
     * ("Seletor de mês é propagado para o show via querystring").
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function uc_espidx_03_mes_escolhido_viaja_para_o_espelho(): void
    {
        $this->actAsAdmin();
        $this->precisaDeSchema();

        $mes = $this->mesRef();

        // A lista carrega o mês escolhido...
        $lista = $this->inertiaGet('/ponto/espelho', ['mes' => $mes]);
        $this->assertInertiaComponent($lista, 'Ponto/Espelho/Index');
        $this->assertSame($mes, $lista->json('props.mes'), 'A lista mantém o mês selecionado.');

        // ...e o espelho abre NAQUELE mês, não no corrente.
        $colaborador = $this->criarColaborador();
        $show = $this->inertiaGet("/ponto/espelho/{$colaborador->id}", ['mes' => $mes]);
        $this->assertInertiaComponent($show, 'Ponto/Espelho/Show');
        $this->assertSame(
            $mes,
            $show->json('props.mes'),
            'O espelho precisa abrir na competência escolhida na lista — conferir o mês '
            . 'errado no fechamento é erro silencioso e caro (CU-PONTO-04).'
        );
    }

    /**
     * Um user NOVO do business logado, para vincular a UM colaborador.
     *
     * Por que nao reusar o admin: um Observer cria `ponto_colaborador_config` por
     * colaborador, e essa tabela tem `user_id` UNIQUE -- dois colaboradores no mesmo
     * user estouram com Duplicate entry (medido na lane: 17 ocorrencias). O admin
     * segue sendo quem AUTENTICA; so o vinculo do colaborador muda.
     */
    private function novoUserDoBusiness(): User
    {
        return User::factory()->create([
            'business_id' => $this->business->id,
            'user_type'   => 'user',
        ]);
    }
}
