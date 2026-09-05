<?php

declare(strict_types=1);

use App\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Ponto\Entities\BancoHorasMovimento;
use Modules\Ponto\Entities\BancoHorasSaldo;
use Modules\Ponto\Entities\Colaborador;
use Modules\Ponto\Entities\Importacao;
use Modules\Ponto\Tests\Feature\PontoTestCase;

uses(PontoTestCase::class);

/**
 * Contrato de banco de horas + importação AFD — trio derivado do SDD
 * (agent `sdd-from-source`, ADR 0351).
 *
 * Cada teste cita o UC no TÍTULO do `it()` (G-2 do casos-gate, ADR 0264):
 *   BancoHoras/Show.casos.md   → UC-BHSHOW-01..03
 *   Importacoes/Show.casos.md  → UC-IMPSH-01..04
 *
 * Os UC derivam do SDD §6.3/§6.4 (CU-PONTO-08..11) + CU-PONTO-12, ancorados em
 * US-PONTO-002/004/007/008 e CLT Art. 59 §5º.
 *
 * ── Por que Pest `it()` e não método PHPUnit (conversão de 2026-09-04) ────────
 * O manifesto do G-7 (`casos-results-collect.mjs`) casa o UC-id contra o `name` do
 * `<testcase>` do JUnit, e o regex canônico (`scripts/lib/uc-regex.mjs`) exige a forma
 * `UC-BHSHOW-01` — com hífen e maiúsculas. Em PHPUnit o `name` é o NOME DO MÉTODO, e PHP
 * não aceita `-` em identificador: `uc_bhshow_01_…` nunca casa, então os 7 UC deste
 * arquivo rodavam VERDES valendo ZERO no painel (`casos:report` os listava como `⛓` —
 * eram os 7 últimos do módulo Ponto). Em Pest o `name` é o título do `it()`, e o id chega
 * ao manifesto. Isto é conversão de FORMA: nenhuma asserção foi alterada. Mesmo idioma
 * dos irmãos já convertidos (`Espelho`, `JornadaWorkflow`, `ImportacaoIndex`,
 * `BancoHorasIndex`, `Intercorrencia`).
 *
 * ⚠️ UC-IMPSH-04 NASCEU failing-first por desenho: denunciava a regressão D-8 do SDD §9 —
 * o `ImportacaoController` lia `linhas_criadas`/`linhas_ignoradas`, que não são coluna nem
 * accessor (a migration tem `linhas_sucesso`/`linhas_erro`), e o `?? 0` mascarava o buraco.
 * FATO DATADO 2026-09-04: a predição CADUCOU — o D-8 foi corrigido em `20a2757a5e`
 * (2026-08-03, "5 atributos fantasma", o MESMO commit que fechou o D-1 do Espelho). Das
 * duas correções que o caso admitia, valeu a que preserva a chave exposta: hoje o
 * controller monta `'linhas_criadas' => (int) ($i->linhas_sucesso ?? 0)` (`:44` no `index`,
 * `:116` no `show`), lendo a coluna real. Medido na lane `ponto-pest` do main
 * (run 33939672971): `✓ uc impshow 04 contagens exibidas refletem o processado 0.33s` —
 * os 7 passam. Logo a conversão colhe `verdict: pass`, não `fail`. O assert segue INTACTO
 * justamente pra que quem responda seja a lane, não este docblock (G-7).
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

/** Marcador pra cleanup — todo fixture criado aqui carrega ele. */
const BHIMP_MARCADOR = 'SDD-BH-IMP-CONTRATO';

function bhImpLimparFixtures(): void
{
    try {
        $ids = Colaborador::withoutGlobalScopes()
            ->where('matricula', 'like', BHIMP_MARCADOR . '%')
            ->pluck('id');

        if ($ids->isNotEmpty()) {
            // Ledger é append-only via Eloquent — no cleanup de teste usamos
            // DB::table de propósito (jamais em código de produção).
            DB::table('ponto_banco_horas_movimentos')->whereIn('colaborador_config_id', $ids)->delete();
            DB::table('ponto_banco_horas_saldo')->whereIn('colaborador_config_id', $ids)->delete();
            Colaborador::withoutGlobalScopes()->whereIn('id', $ids)->delete();
        }

        DB::table('ponto_importacoes')
            ->where('nome_arquivo', 'like', BHIMP_MARCADOR . '%')
            ->delete();
    } catch (\Throwable $e) {
        // schema ausente — cleanup best-effort
    }
}

/**
 * @param  array<int,string>  $tabelas
 */
function bhImpPrecisaDe(array $tabelas): void
{
    foreach ($tabelas as $t) {
        if (! Schema::hasTable($t)) {
            test()->markTestSkipped("Tabela {$t} ausente — schema do Ponto não migrado nesta lane.");
        }
    }
}

/**
 * Um user NOVO do business informado, para vincular a UM colaborador.
 *
 * Por que nao reusar o admin: um Observer cria `ponto_colaborador_config` por
 * colaborador, e essa tabela tem `user_id` UNIQUE -- dois colaboradores no mesmo
 * user estouram com Duplicate entry (medido na lane: 17 ocorrencias). O admin
 * segue sendo quem AUTENTICA; so o vinculo do colaborador muda.
 */
function bhImpNovoUser(int $businessId): User
{
    return User::factory()->create([
        'business_id' => $businessId,
        'user_type'   => 'user',
    ]);
}

/**
 * Colaborador com saldo de banco de horas, marcado pro cleanup.
 *
 * Recebe DOIS business_id de propósito: o colaborador (e o saldo) nascem no business
 * ALVO, mas o user nasce no business LOGADO — era exatamente o que `novoUserDoBusiness()`
 * fazia ao ser chamado de dentro do helper, e mudar isso alteraria o cenário cross-tenant
 * do UC-BHSHOW-03 numa conversão que só pode mudar a forma.
 *
 * O `business_id` vem por PARÂMETRO (e não de `$this->business`) porque em Pest o helper
 * é função de arquivo, fora do escopo da classe — e a propriedade é `protected`. Quem
 * passa é o `it()`, que É escopo da classe. Mesmo idioma dos irmãos convertidos.
 */
function bhImpCriarColaboradorComSaldo(int $businessId, int $userBusinessId): Colaborador
{
    $colab = new Colaborador();
    $colab->forceFill([
        'business_id'    => $businessId,
        'user_id'        => bhImpNovoUser($userBusinessId)->id,
        'matricula'      => BHIMP_MARCADOR . '-' . uniqid(),
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

afterEach(function () {
    bhImpLimparFixtures();
    $this->removerBizAlheio(); // depois do cleanup: FK sem CASCADE (ver PontoTestCase)
});

// =====================================================================
// BancoHoras/Show
// =====================================================================

/**
 * UC-BHSHOW-01 · [must][V0][T0]
 *
 * Contrato: CU-PONTO-09 (SDD §6.3) + US-PONTO-008 ("BancoHorasMovimento::update()
 * e delete() idem — saldo deve ser auditável") + US-PONTO-004 (ledger append-only)
 * + proibicoes.md §append-only.
 *
 * Aqui a imutabilidade tem UMA camada só (override Eloquent; sem trigger DB —
 * SDD §9 D-6). Este UC transforma a única defesa em defesa observada.
 */
it('UC-BHSHOW-01 · Movimento gravado não pode ser alterado nem apagado', function () {
    $this->actAsAdmin();
    bhImpPrecisaDe(['ponto_colaborador_config', 'ponto_banco_horas_movimentos']);

    $colab = bhImpCriarColaboradorComSaldo($this->business->id, $this->business->id);

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
});

/**
 * UC-BHSHOW-02 · [must][V0]
 *
 * Contrato: CU-PONTO-09 + BancoHorasController@ajustarManual (minutos required
 * integer + observacao required) + US-PONTO-004 (tipo AJUSTE) + CLT Art. 59 §5º.
 *
 * Prova a FORMA (acréscimo + justificativa), não o valor resultante — assert de
 * saldo exige o protocolo da REGRA MESTRE.
 */
it('UC-BHSHOW-02 · Ajuste manual exige justificativa e vira movimento novo', function () {
    $this->actAsAdmin();
    bhImpPrecisaDe(['ponto_colaborador_config', 'ponto_banco_horas_movimentos']);

    $colab = bhImpCriarColaboradorComSaldo($this->business->id, $this->business->id);
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
});

/**
 * UC-BHSHOW-03 · [must][T0]
 *
 * Contrato: CU-PONTO-12 + US-PONTO-007 + ADR 0093.
 * BancoHorasController@show usa firstOrFail SEM business_id explícito — quem
 * valida o tenant é o global scope, não o firstOrFail (SDD §9 D-5).
 */
it('UC-BHSHOW-03 · Extrato de colaborador de outro empregador → 404', function () {
    $this->actAsAdmin();
    bhImpPrecisaDe(['ponto_colaborador_config', 'ponto_banco_horas_saldo']);

    // `garantirBizAlheio()` é idempotente e DEVOLVE o id do fictício — usar o retorno
    // mantém o guard ancorado na const `BIZ_ALHEIO_FICTICIO` da base (era `self::`, que
    // não resolve em closure Pest). Chamar ANTES do guard é inerte: se o business logado
    // FOR o fictício, ele já existe e o helper não cria nada.
    $bizAlheio = $this->garantirBizAlheio();

    if ((int) $this->business->id === $bizAlheio) {
        $this->markTestSkipped('Business logado colide com o business fictício do teste.');
    }

    // Controle positivo: o MEU extrato abre. Sem ele, 404 de rota quebrada ou
    // permissão ausente passaria por "isolamento funcionando".
    $meu = bhImpCriarColaboradorComSaldo($this->business->id, $this->business->id);
    $this->inertiaGet("/ponto/banco-horas/{$meu->id}")->assertStatus(200);

    $alheio = bhImpCriarColaboradorComSaldo($bizAlheio, $this->business->id);

    $this->inertiaGet("/ponto/banco-horas/{$alheio->id}")
        ->assertStatus(404); // saldo é informação salarial — nunca vaza
});

// =====================================================================
// Importacoes/Show
// =====================================================================

/**
 * Importação do business informado, marcada pro cleanup.
 *
 * `business_id` e `usuario_id` vêm por PARÂMETRO pelo mesmo motivo do helper de
 * colaborador: em Pest a função está fora do escopo da classe.
 *
 * @param  array<string,mixed>  $attrs
 */
function bhImpCriarImportacao(int $businessId, int $usuarioId, array $attrs = []): Importacao
{
    $imp = new Importacao();
    $imp->forceFill(array_merge([
        'business_id'   => $businessId,
        'tipo'          => 'AFD',
        'nome_arquivo'  => BHIMP_MARCADOR . '-' . uniqid() . '.txt',
        'arquivo_path'  => 'ponto/importacoes/fixture.txt',
        'hash_arquivo'  => hash('sha256', uniqid('sdd', true)),
        'tamanho_bytes' => 1024,
        'usuario_id'    => $usuarioId,
    ], $attrs))->save();

    return $imp;
}

/**
 * UC-IMPSH-01 · [must]
 *
 * Contrato: CU-PONTO-10 + US-PONTO-002 ("Importação idempotente — mesma AFD pode
 * ser re-uploadada sem duplicar marcacoes") + ImportacaoController@store (dedup
 * por hash_file sha256).
 */
it('UC-IMPSH-01 · Reimportar o mesmo arquivo não duplica marcação', function () {
    $this->actAsAdmin();
    bhImpPrecisaDe(['ponto_importacoes']);

    $hash = hash('sha256', 'conteudo-afd-fixture-sdd');
    bhImpCriarImportacao($this->business->id, $this->admin->id, ['hash_arquivo' => $hash]);

    $antes = DB::table('ponto_importacoes')
        ->where('business_id', $this->business->id)
        ->where('hash_arquivo', $hash)
        ->count();

    $this->assertSame(1, $antes, 'Pré-condição: o arquivo já foi importado uma vez.');

    // A dedup é por CONTEÚDO (hash), não por nome — reimportar o mesmo conteúdo
    // não pode criar um segundo registro no mesmo business.
    $duplicou = false;
    try {
        bhImpCriarImportacao($this->business->id, $this->admin->id, ['hash_arquivo' => $hash]);
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
});

/**
 * UC-IMPSH-02 · [must][T0]
 *
 * Contrato: CU-PONTO-10 + ADR 0093. Vetor INVERSO do vazamento: aqui o risco é
 * negação de serviço cross-tenant — o arquivo de um empregador bloqueando a
 * importação de outro.
 *
 * ⚠️ O QUE ESTE CASO MEDE, exatamente: a camada de SCHEMA — que a unicidade é
 * `(business_id, hash_arquivo)` e não `hash_arquivo` global. Se o índice fosse
 * global, o segundo INSERT estouraria. É prova real, e é a que sustenta a
 * garantia mesmo se o controller mudar.
 *
 * ⚠️ O QUE ELE NÃO MEDE: o `where('business_id')->where('hash_arquivo')` do
 * `ImportacaoController@store`. A redação anterior citava esse método como
 * contrato, mas o caso nunca chamou a rota — exercitá-la exige POST com upload
 * e a permissão `ponto.importacoes.criar`, que o `PontoTestCase` não concede
 * (é o escopo do PR da permissão AFD). Enquanto isso não existir, o texto aqui
 * declara o alcance em vez de prometer o que não entrega.
 */
it('UC-IMPSH-02 · A dedup é do meu empregador, não global', function () {
    $this->actAsAdmin();
    bhImpPrecisaDe(['ponto_importacoes']);

    $bizAlheio = $this->garantirBizAlheio();

    if ((int) $this->business->id === $bizAlheio) {
        $this->markTestSkipped('Business logado colide com o business fictício do teste.');
    }

    // Dois REP-A do mesmo modelo podem gerar arquivos byte-idênticos.
    $hash = hash('sha256', 'afd-identico-entre-empregadores');

    DB::table('ponto_importacoes')->insert([
        'business_id'   => $bizAlheio,
        'tipo'          => 'AFD',
        'nome_arquivo'  => BHIMP_MARCADOR . '-alheio.txt',
        'arquivo_path'  => 'ponto/importacoes/alheio.txt',
        'hash_arquivo'  => $hash,
        'tamanho_bytes' => 1024,
        'usuario_id'    => $this->admin->id,
        'created_at'    => now(),
        'updated_at'    => now(),
    ]);

    $minha = bhImpCriarImportacao($this->business->id, $this->admin->id, ['hash_arquivo' => $hash]);

    $this->assertNotNull(
        DB::table('ponto_importacoes')->where('id', $minha->id)->first(),
        'Arquivo idêntico já importado por OUTRO empregador não pode bloquear a minha '
        . 'importação — a dedup é escopada por business (CU-PONTO-10, ADR 0093).'
    );

    // Pré-condição anti-vácuo: prova que a COLISÃO foi de fato montada. Sem isto,
    // "a minha entrou" seria verdade também se a linha alheia nunca tivesse
    // existido — e o caso passaria sem haver colisão nenhuma pra escopar.
    $this->assertSame(
        2,
        DB::table('ponto_importacoes')->where('hash_arquivo', $hash)->count(),
        'As duas importações (minha + alheia) têm de coexistir com o MESMO hash — '
        . 'é isso que prova que a unicidade é (business_id, hash_arquivo) e não global.'
    );
});

/**
 * UC-IMPSH-03 · [must][T0]
 *
 * Contrato: CU-PONTO-12 + US-PONTO-007 + LGPD Art. 7º II.
 * A rota irmã (baixarOriginal) entrega o ARQUIVO BRUTO com as marcações do
 * outro empregador — o risco aqui é maior que nas demais telas (SDD §9 D-5).
 */
it('UC-IMPSH-03 · Importação de outro empregador → 404', function () {
    $this->actAsAdmin();
    bhImpPrecisaDe(['ponto_importacoes']);

    $bizAlheio = $this->garantirBizAlheio();

    if ((int) $this->business->id === $bizAlheio) {
        $this->markTestSkipped('Business logado colide com o business fictício do teste.');
    }

    // Controle positivo: a MINHA abre.
    $minha = bhImpCriarImportacao($this->business->id, $this->admin->id);
    $this->inertiaGet("/ponto/importacoes/{$minha->id}")->assertStatus(200);

    $alheioId = DB::table('ponto_importacoes')->insertGetId([
        'business_id'   => $bizAlheio,
        'tipo'          => 'AFD',
        'nome_arquivo'  => BHIMP_MARCADOR . '-alheio-show.txt',
        'arquivo_path'  => 'ponto/importacoes/alheio-show.txt',
        'hash_arquivo'  => hash('sha256', uniqid('alheio', true)),
        'tamanho_bytes' => 1024,
        'usuario_id'    => $this->admin->id,
        'created_at'    => now(),
        'updated_at'    => now(),
    ]);

    $this->inertiaGet("/ponto/importacoes/{$alheioId}")
        ->assertStatus(404);
});

/**
 * UC-IMPSH-04 · [must]
 *
 * Contrato: CU-PONTO-11 (SDD §6.4) + US-PONTO-002 (aceitação: "Importacao registra
 * arquivo + checksum + linhas processadas + erros") + Portaria MTP 671/2021 Anexo I
 * (rastreabilidade).
 *
 * Sobre a predição de vermelho: ver o docblock do topo — ela é fato datado da criação
 * do caso, e o D-8 foi corrigido depois (`20a2757a5e`, 2026-08-03), preservando a chave
 * exposta e trocando a LEITURA pra `linhas_sucesso`. Veredito real vem da lane (G-7),
 * não daqui.
 *
 * O assert é sobre COMPORTAMENTO ("a contagem exibida reflete o processado") — se um dia
 * a correção for renomear o campo exposto, atualize front e este assert juntos.
 */
it('UC-IMPSH-04 · As contagens exibidas refletem o que foi processado', function () {
    $this->actAsAdmin();
    bhImpPrecisaDe(['ponto_importacoes']);

    $imp = bhImpCriarImportacao($this->business->id, $this->admin->id, [
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
});
