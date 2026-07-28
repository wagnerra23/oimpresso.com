<?php

namespace Modules\Ponto\Tests\Feature;

use App\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Ponto\Entities\BancoHorasMovimento;
use Modules\Ponto\Entities\BancoHorasSaldo;
use Modules\Ponto\Entities\Colaborador;
use Modules\Ponto\Entities\Importacao;

/**
 * Contrato de banco de horas + importação AFD — trio derivado do SDD
 * (agent `sdd-from-source`, ADR 0351).
 *
 * Cada teste cita o UC do `casos.md` da tela (G-2 do casos-gate, ADR 0264):
 *   BancoHoras/Show.casos.md   → UC-BHSHOW-01..03
 *   Importacoes/Show.casos.md  → UC-IMPSHOW-01..04
 *
 * Os UC derivam do SDD §6.3/§6.4 (CU-PONTO-08..11) + CU-PONTO-12, ancorados em
 * US-PONTO-002/004/007/008 e CLT Art. 59 §5º.
 *
 * ⚠️ UC-IMPSHOW-04 é FAILING-FIRST por desenho: denuncia a regressão D-8 do SDD §9
 * (o controller lê `linhas_criadas`/`linhas_ignoradas`, campos que NÃO existem —
 * a migration tem `linhas_sucesso`/`linhas_erro`). Vermelho aqui é o ACHADO.
 *
 * ⚠️ [V0] — minuto de jornada é valor (SDD §3.2). Os UC de banco de horas provam a
 * FORMA (append-only, justificativa obrigatória), NUNCA o valor calculado: assert
 * sobre saldo exige o protocolo da REGRA MESTRE (2 caminhos + antes→depois).
 *
 * Tier 0: biz=1 (WR2 interno) — NUNCA biz=4 (ROTA LIVRE, ADR 0101).
 * Sem RefreshDatabase: a lane ponto-pest proíbe (dropa schema + limpa seed biz=1).
 *
 * @covers-us US-PONTO-002 US-PONTO-004 US-PONTO-007 US-PONTO-008
 */
class BancoHorasImportacaoContratoTest extends PontoTestCase
{
    private const MARCADOR = 'SDD-BH-IMP-CONTRATO';
    private const BIZ_ALHEIO = 99;

    protected function tearDown(): void
    {
        $this->limparFixtures();
        parent::tearDown();
    }

    private function limparFixtures(): void
    {
        try {
            $ids = Colaborador::withoutGlobalScopes()
                ->where('matricula', 'like', self::MARCADOR . '%')
                ->pluck('id');

            if ($ids->isNotEmpty()) {
                // Ledger é append-only via Eloquent — no cleanup de teste usamos
                // DB::table de propósito (jamais em código de produção).
                DB::table('ponto_banco_horas_movimentos')->whereIn('colaborador_config_id', $ids)->delete();
                DB::table('ponto_banco_horas_saldo')->whereIn('colaborador_config_id', $ids)->delete();
                Colaborador::withoutGlobalScopes()->whereIn('id', $ids)->delete();
            }

            DB::table('ponto_importacoes')
                ->where('nome_arquivo', 'like', self::MARCADOR . '%')
                ->delete();
        } catch (\Throwable $e) {
            // schema ausente — cleanup best-effort
        }
    }

    private function precisaDe(array $tabelas): void
    {
        foreach ($tabelas as $t) {
            if (! Schema::hasTable($t)) {
                $this->markTestSkipped("Tabela {$t} ausente — schema do Ponto não migrado nesta lane.");
            }
        }
    }

    private function criarColaboradorComSaldo(?int $businessId = null): Colaborador
    {
        $businessId = $businessId ?? $this->business->id;

        $colab = new Colaborador();
        $colab->forceFill([
            'business_id'    => $businessId,
            'user_id'        => $this->novoUserDoBusiness()->id,
            'matricula'      => self::MARCADOR . '-' . uniqid(),
            'controla_ponto' => true,
            'usa_banco_horas' => true,
        ])->save();

        $saldo = new BancoHorasSaldo();
        $saldo->forceFill([
            'business_id'           => $businessId,
            'colaborador_config_id' => $colab->id,
            'saldo_minutos'         => 120,
        ])->save();

        return $colab;
    }

    // =====================================================================
    // BancoHoras/Show
    // =====================================================================

    /**
     * UC-BHSHOW-01 · Movimento gravado não pode ser alterado nem apagado. [must][V0][T0]
     *
     * Contrato: CU-PONTO-09 (SDD §6.3) + US-PONTO-008 ("BancoHorasMovimento::update()
     * e delete() idem — saldo deve ser auditável") + US-PONTO-004 (ledger append-only)
     * + proibicoes.md §append-only.
     *
     * Aqui a imutabilidade tem UMA camada só (override Eloquent; sem trigger DB —
     * SDD §9 D-6). Este UC transforma a única defesa em defesa observada.
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function uc_bhshow_01_movimento_do_ledger_nao_muda_nem_some(): void
    {
        $this->actAsAdmin();
        $this->precisaDe(['ponto_colaborador_config', 'ponto_banco_horas_movimentos']);

        $colab = $this->criarColaboradorComSaldo();

        $mov = new BancoHorasMovimento();
        $mov->forceFill([
            'business_id'           => $this->business->id,
            'colaborador_config_id' => $colab->id,
            'data_referencia'       => '2019-03-11',
            'tipo'                  => BancoHorasMovimento::TIPO_CREDITO,
            'minutos'               => 60,
            'observacao'            => 'Fixture de contrato SDD.',
            'usuario_id'            => $this->admin->id,
        ])->save();

        $id = $mov->id;
        $minutosOriginais = (int) DB::table('ponto_banco_horas_movimentos')->where('id', $id)->value('minutos');

        // (a) alterar deve falhar
        $alterou = false;
        try {
            $mov->minutos = 999;
            $mov->save();
            $alterou = true;
        } catch (\Throwable $e) {
            // esperado — append-only
        }
        $this->assertFalse(
            $alterou,
            'Alterar movimento do ledger tem de falhar — o extrato só vale como prova '
            . 'se for acumulativo (US-PONTO-008).'
        );

        // (b) remover deve falhar
        $removeu = false;
        try {
            $mov->delete();
            $removeu = true;
        } catch (\Throwable $e) {
            // esperado — append-only
        }
        $this->assertFalse(
            $removeu,
            'Remover movimento do ledger tem de falhar (US-PONTO-008).'
        );

        // (c) e o registro continua intacto no banco
        $atual = DB::table('ponto_banco_horas_movimentos')->where('id', $id)->first();
        $this->assertNotNull($atual, 'O movimento precisa continuar existindo.');
        $this->assertSame(
            $minutosOriginais,
            (int) $atual->minutos,
            'O valor do movimento não pode ter mudado — saldo auditável é o contrato.'
        );
    }

    /**
     * UC-BHSHOW-02 · Ajuste manual exige justificativa e vira movimento novo. [must][V0]
     *
     * Contrato: CU-PONTO-09 + BancoHorasController@ajustarManual (minutos required
     * integer + observacao required) + US-PONTO-004 (tipo AJUSTE) + CLT Art. 59 §5º.
     *
     * Prova a FORMA (acréscimo + justificativa), não o valor resultante — assert de
     * saldo exige o protocolo da REGRA MESTRE.
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function uc_bhshow_02_ajuste_exige_justificativa_e_vira_movimento_novo(): void
    {
        $this->actAsAdmin();
        $this->precisaDe(['ponto_colaborador_config', 'ponto_banco_horas_movimentos']);

        $colab = $this->criarColaboradorComSaldo();
        $antes = DB::table('ponto_banco_horas_movimentos')
            ->where('colaborador_config_id', $colab->id)->count();

        // (a) sem observação → recusado
        $this->from("/ponto/banco-horas/{$colab->id}")
            ->post("/ponto/banco-horas/{$colab->id}/ajuste", ['minutos' => 30])
            ->assertSessionHasErrors('observacao');

        $this->assertSame(
            $antes,
            DB::table('ponto_banco_horas_movimentos')->where('colaborador_config_id', $colab->id)->count(),
            'Ajuste sem justificativa não pode gerar movimento — correção anônima destrói '
            . 'a rastreabilidade do saldo (CU-PONTO-09).'
        );

        // (b) com observação → NOVO movimento, sem alterar os anteriores
        $this->from("/ponto/banco-horas/{$colab->id}")
            ->post("/ponto/banco-horas/{$colab->id}/ajuste", [
                'minutos'    => 30,
                'observacao' => 'Acordo com o colaborador — contrato SDD.',
            ]);

        $this->assertSame(
            $antes + 1,
            DB::table('ponto_banco_horas_movimentos')->where('colaborador_config_id', $colab->id)->count(),
            'Ajuste manual é ACRÉSCIMO no ledger, nunca UPDATE no saldo (CU-PONTO-09).'
        );
    }

    /**
     * UC-BHSHOW-03 · Extrato de colaborador de outro empregador → 404. [must][T0]
     *
     * Contrato: CU-PONTO-12 + US-PONTO-007 + ADR 0093.
     * BancoHorasController@show usa firstOrFail SEM business_id explícito — quem
     * valida o tenant é o global scope, não o firstOrFail (SDD §9 D-5).
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function uc_bhshow_03_extrato_de_outro_empregador_da_404(): void
    {
        $this->actAsAdmin();
        $this->precisaDe(['ponto_colaborador_config', 'ponto_banco_horas_saldo']);

        if ((int) $this->business->id === self::BIZ_ALHEIO) {
            $this->markTestSkipped('Business logado colide com o business fictício do teste.');
        }

        $alheio = $this->criarColaboradorComSaldo(self::BIZ_ALHEIO);

        $this->inertiaGet("/ponto/banco-horas/{$alheio->id}")
            ->assertStatus(404); // saldo é informação salarial — nunca vaza
    }

    // =====================================================================
    // Importacoes/Show
    // =====================================================================

    private function criarImportacao(array $attrs = []): Importacao
    {
        $imp = new Importacao();
        $imp->forceFill(array_merge([
            'business_id'   => $this->business->id,
            'tipo'          => 'AFD',
            'nome_arquivo'  => self::MARCADOR . '-' . uniqid() . '.txt',
            'arquivo_path'  => 'ponto/importacoes/fixture.txt',
            'hash_arquivo'  => hash('sha256', uniqid('sdd', true)),
            'tamanho_bytes' => 1024,
            'usuario_id'    => $this->admin->id,
        ], $attrs))->save();

        return $imp;
    }

    /**
     * UC-IMPSHOW-01 · Reimportar o mesmo arquivo não duplica marcação. [must]
     *
     * Contrato: CU-PONTO-10 + US-PONTO-002 ("Importação idempotente — mesma AFD pode
     * ser re-uploadada sem duplicar marcacoes") + ImportacaoController@store (dedup
     * por hash_file sha256).
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function uc_impshow_01_reimportar_mesmo_arquivo_nao_duplica(): void
    {
        $this->actAsAdmin();
        $this->precisaDe(['ponto_importacoes']);

        $hash = hash('sha256', 'conteudo-afd-fixture-sdd');
        $this->criarImportacao(['hash_arquivo' => $hash]);

        $antes = DB::table('ponto_importacoes')
            ->where('business_id', $this->business->id)
            ->where('hash_arquivo', $hash)
            ->count();

        $this->assertSame(1, $antes, 'Pré-condição: o arquivo já foi importado uma vez.');

        // A dedup é por CONTEÚDO (hash), não por nome — reimportar o mesmo conteúdo
        // não pode criar um segundo registro no mesmo business.
        $duplicou = false;
        try {
            $this->criarImportacao(['hash_arquivo' => $hash]);
            $duplicou = DB::table('ponto_importacoes')
                ->where('business_id', $this->business->id)
                ->where('hash_arquivo', $hash)
                ->count() > 1;
        } catch (\Throwable $e) {
            // unique(business_id, hash_arquivo) barrou — é o comportamento desejado
        }

        $this->assertFalse(
            $duplicou,
            'O mesmo arquivo não pode gerar duas importações no mesmo empregador — '
            . 'marcação duplicada infla a jornada apurada e vira HE paga em duplicidade '
            . '(CU-PONTO-10, US-PONTO-002).'
        );
    }

    /**
     * UC-IMPSHOW-02 · A dedup é do meu empregador, não global. [must][T0]
     *
     * Contrato: CU-PONTO-10 + ImportacaoController@store (busca de duplicata é
     * where business_id + hash_arquivo) + ADR 0093.
     *
     * Vetor INVERSO do vazamento: aqui o risco é negação de serviço cross-tenant —
     * o arquivo de um empregador bloqueando a importação de outro.
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function uc_impshow_02_dedup_e_por_empregador_nao_global(): void
    {
        $this->actAsAdmin();
        $this->precisaDe(['ponto_importacoes']);

        if ((int) $this->business->id === self::BIZ_ALHEIO) {
            $this->markTestSkipped('Business logado colide com o business fictício do teste.');
        }

        // Dois REP-A do mesmo modelo podem gerar arquivos byte-idênticos.
        $hash = hash('sha256', 'afd-identico-entre-empregadores');

        DB::table('ponto_importacoes')->insert([
            'business_id'   => self::BIZ_ALHEIO,
            'tipo'          => 'AFD',
            'nome_arquivo'  => self::MARCADOR . '-alheio.txt',
            'arquivo_path'  => 'ponto/importacoes/alheio.txt',
            'hash_arquivo'  => $hash,
            'tamanho_bytes' => 1024,
            'usuario_id'    => $this->admin->id,
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        $minha = $this->criarImportacao(['hash_arquivo' => $hash]);

        $this->assertNotNull(
            DB::table('ponto_importacoes')->where('id', $minha->id)->first(),
            'Arquivo idêntico já importado por OUTRO empregador não pode bloquear a minha '
            . 'importação — a dedup é escopada por business (CU-PONTO-10, ADR 0093).'
        );
    }

    /**
     * UC-IMPSHOW-03 · Importação de outro empregador → 404. [must][T0]
     *
     * Contrato: CU-PONTO-12 + US-PONTO-007 + LGPD Art. 7º II.
     * A rota irmã (baixarOriginal) entrega o ARQUIVO BRUTO com as marcações do
     * outro empregador — o risco aqui é maior que nas demais telas (SDD §9 D-5).
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function uc_impshow_03_importacao_de_outro_empregador_da_404(): void
    {
        $this->actAsAdmin();
        $this->precisaDe(['ponto_importacoes']);

        if ((int) $this->business->id === self::BIZ_ALHEIO) {
            $this->markTestSkipped('Business logado colide com o business fictício do teste.');
        }

        $alheioId = DB::table('ponto_importacoes')->insertGetId([
            'business_id'   => self::BIZ_ALHEIO,
            'tipo'          => 'AFD',
            'nome_arquivo'  => self::MARCADOR . '-alheio-show.txt',
            'arquivo_path'  => 'ponto/importacoes/alheio-show.txt',
            'hash_arquivo'  => hash('sha256', uniqid('alheio', true)),
            'tamanho_bytes' => 1024,
            'usuario_id'    => $this->admin->id,
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        $this->inertiaGet("/ponto/importacoes/{$alheioId}")
            ->assertStatus(404);
    }

    /**
     * UC-IMPSHOW-04 · As contagens exibidas refletem o que a importação processou. [must]
     *
     * Contrato: CU-PONTO-11 (SDD §6.4) + US-PONTO-002 (aceitação: "Importacao registra
     * arquivo + checksum + linhas processadas + erros") + Portaria MTP 671/2021 Anexo I
     * (rastreabilidade).
     *
     * PREDIÇÃO: vermelho. Regressão D-8 (SDD §9) — o controller lê `linhas_criadas` e
     * `linhas_ignoradas`, campos que NÃO existem: a migration `ponto_importacoes` tem
     * `linhas_total`, `linhas_processadas`, `linhas_sucesso`, `linhas_erro`. O `?? 0`
     * mascara o buraco e a tela informa ZERO marcações criadas para toda importação
     * bem-sucedida. Varredura contada: 9 ocorrências (3 no controller, 6 no front).
     *
     * O assert é sobre COMPORTAMENTO ("a contagem exibida reflete o processado") — se a
     * correção for renomear o campo exposto, atualize front e este assert juntos.
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function uc_impshow_04_contagens_exibidas_refletem_o_processado(): void
    {
        $this->actAsAdmin();
        $this->precisaDe(['ponto_importacoes']);

        $imp = $this->criarImportacao([
            'estado'             => 'CONCLUIDA',
            'linhas_total'       => 10,
            'linhas_processadas' => 10,
            'linhas_sucesso'     => 7,
            'linhas_erro'        => 3,
        ]);

        $resp = $this->inertiaGet("/ponto/importacoes/{$imp->id}");
        $this->assertInertiaComponent($resp, 'Ponto/Importacoes/Show');

        $payload = $resp->json('props.importacao');

        $this->assertSame(
            10,
            (int) ($payload['linhas_processadas'] ?? -1),
            'A tela precisa informar quantas linhas foram processadas (US-PONTO-002).'
        );

        $this->assertGreaterThan(
            0,
            (int) ($payload['linhas_criadas'] ?? 0),
            'Importação que registrou 7 linhas com sucesso não pode exibir ZERO marcações '
            . 'criadas. O controller lê `linhas_criadas`, que não existe na tabela '
            . '(a coluna é `linhas_sucesso`) — SDD §9 D-8.'
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
