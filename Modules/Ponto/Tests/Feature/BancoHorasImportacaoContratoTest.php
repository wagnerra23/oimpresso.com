<?php

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
 * ── Por que Pest `it()` e não classe PHPUnit (conversão de 2026-09-04) ──────
 * O `casos-results-collect.mjs` (manifesto G-7) lê o UC-id do atributo `name` do
 * `<testcase>` no JUnit — ou seja, do TÍTULO. Método PHP não aceita hífen, então
 * `uc_bhshow_01_…` virava o nome humanizado "Uc bhshow 01 …" e o regex canônico
 * (`scripts/lib/uc-regex.mjs`, que exige `UC-BHSHOW-NN`) nunca casava: os 7 UC
 * deste arquivo rodavam VERDES numa lane required e valiam 0 no painel.
 * Medido no JUnit real da lane Financeiro (run 30764392026): dos 82 UCs do
 * manifesto, 82 vêm de título `it()` e 0 de método `test_`. Receita idêntica à do
 * `ConciliacaoLeExtratoApiTest` (#5177/#5180) e à do irmão `BancoHorasIndexContratoTest`.
 *
 * NADA de asserção mudou — os corpos dos 7 casos são os mesmos, verbatim. O que
 * mudou é o invólucro (classe → `it()`), o nome, e os helpers privados, que viraram
 * funções `bhimp*` recebendo por parâmetro o que liam de `$this` (o idioma que o
 * irmão `BancoHorasIndexContratoTest` já usa neste módulo).
 * Baseline a preservar, do JUnit de main (run 33938659118): 7 testcases · 7 passed ·
 * 0 fail · 0 skip · 22 assertions.
 *
 * ⚠️ Sem `declare(strict_types=1)` DE PROPÓSITO, embora o irmão Pest deste módulo o
 * use: o arquivo original não o tinha, e ligá-lo aqui trocaria coerção silenciosa por
 * TypeError numa conversão que promete não mudar comportamento. Uniformidade de idioma
 * não paga o risco de embutir mudança de semântica num PR de invólucro.
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

const BHIMP_MARCADOR = 'SDD-BH-IMP-CONTRATO';

afterEach(function () {
    bhimpLimparFixtures();
    $this->removerBizAlheio(); // depois do cleanup: FK sem CASCADE (ver PontoTestCase)
});

function bhimpLimparFixtures(): void
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
            Colaborador::withoutGlobalScopes()->whereIn('id', $ids)->forceDelete();
        }

        // UC-BHSHOW-05: escala e cargo (categories) marcados. Depois do colaborador (FK da
        // escala) e ANTES do removerBizAlheio (FK de categories pro business 99).
        DB::table('ponto_escalas')->where('nome', 'like', BHIMP_MARCADOR . '%')->delete();
        DB::table('categories')->where('name', 'like', BHIMP_MARCADOR . '%')->delete();

        DB::table('ponto_importacoes')
            ->where('nome_arquivo', 'like', BHIMP_MARCADOR . '%')
            ->delete();
    } catch (\Throwable $e) {
        // schema ausente — cleanup best-effort
    }
}

function bhimpPrecisaDe(array $tabelas): void
{
    foreach ($tabelas as $t) {
        if (! Schema::hasTable($t)) {
            test()->markTestSkipped("Tabela {$t} ausente — schema do Ponto não migrado nesta lane.");
        }
    }
}

/**
 * Um user NOVO do business, para vincular a UM colaborador.
 *
 * Por que nao reusar o admin: um Observer cria `ponto_colaborador_config` por
 * colaborador, e essa tabela tem `user_id` UNIQUE -- dois colaboradores no mesmo
 * user estouram com Duplicate entry (medido na lane: 17 ocorrencias). O admin
 * segue sendo quem AUTENTICA; so o vinculo do colaborador muda.
 */
function bhimpNovoUser(int $businessId): User
{
    return User::factory()->create([
        'business_id' => $businessId,
        'user_type'   => 'user',
        'username'    => strtolower(BHIMP_MARCADOR) . '-' . uniqid(),
    ]);
}

/**
 * Colaborador + saldo marcados pro cleanup.
 *
 * `$userBusinessId` é separado de propósito: no original o user vinha SEMPRE do
 * business LOGADO (`$this->novoUserDoBusiness()`), mesmo quando o colaborador nasce
 * no business alheio. Manter os dois eixos explícitos preserva esse comportamento
 * em vez de "consertá-lo" de passagem — mesma assinatura do irmão
 * `bhIdxCriarColaboradorComSaldo`.
 */
function bhimpCriarColaboradorComSaldo(int $businessId, int $userBusinessId): Colaborador
{
    $colab = new Colaborador();
    $colab->forceFill([
        'business_id'    => $businessId,
        'user_id'        => bhimpNovoUser($userBusinessId)->id,
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

/**
 * Entra no tenant canônico de teste (biz 98, fictício — ADR 0358) com um user SEMEADO do 98
 * e `ponto.access` DIRETO, NUNCA a role Admin#98: o Gate::before libera tudo pra essa role,
 * e a 1ª versão do UC-BHSHOW-04 a deu a esse user e derrubou UC-PTF-06/07 (run 36472572442),
 * que provam o 403 sem `ponto.fechar`. User de factory + `ponto.access` tomou 403 no GET
 * (run 36474710700) — a receita do FechamentoContratoTest é a que passa nesta lane.
 *
 * Devolve [business, user] em vez de setar `$this->business`: a propriedade é protected e só
 * a closure do `it()` a enxerga.
 *
 * @return array{0: \App\Business, 1: User}
 */
function bhimpEntrarNoTenant98(): array
{
    $business = test()->seededTenant();
    if ((int) $business->id !== 98) {
        test()->markTestSkipped('Tenant 98 não seedado nesta lane — não caio em business real (ADR 0358).');
    }

    $user = User::where('business_id', $business->id)->orderBy('id')->first();
    if (! $user) {
        test()->markTestSkipped('Sem user no tenant 98 — seed mínimo não rodou.');
    }

    \Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'ponto.access', 'guard_name' => 'web']);
    $user->givePermissionTo('ponto.access');
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    session(['user.business_id' => $business->id, 'business.id' => $business->id]);
    test()->actingAs($user);

    return [$business, $user];
}

function bhimpCriarImportacao(int $businessId, int $usuarioId, array $attrs = []): Importacao
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

// =====================================================================
// BancoHoras/Show
// =====================================================================

/**
 * Contrato: CU-PONTO-09 (SDD §6.3) + US-PONTO-008 ("BancoHorasMovimento::update()
 * e delete() idem — saldo deve ser auditável") + US-PONTO-004 (ledger append-only)
 * + proibicoes.md §append-only.
 *
 * Aqui a imutabilidade tem UMA camada só (override Eloquent; sem trigger DB —
 * SDD §9 D-6). Este UC transforma a única defesa em defesa observada.
 */
it('UC-BHSHOW-01 · movimento gravado não pode ser alterado nem apagado [must][V0][T0]', function () {
    $this->actAsAdmin();
    bhimpPrecisaDe(['ponto_colaborador_config', 'ponto_banco_horas_movimentos']);

    $colab = bhimpCriarColaboradorComSaldo($this->business->id, $this->business->id);

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
 * Contrato: CU-PONTO-09 + BancoHorasController@ajustarManual (minutos required
 * integer + observacao required) + US-PONTO-004 (tipo AJUSTE) + CLT Art. 59 §5º.
 *
 * Prova a FORMA (acréscimo + justificativa), não o valor resultante — assert de
 * saldo exige o protocolo da REGRA MESTRE.
 */
it('UC-BHSHOW-02 · ajuste exige justificativa e vira movimento novo [must][V0]', function () {
    $this->actAsAdmin();
    bhimpPrecisaDe(['ponto_colaborador_config', 'ponto_banco_horas_movimentos']);

    $colab = bhimpCriarColaboradorComSaldo($this->business->id, $this->business->id);
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
 * Contrato: CU-PONTO-12 + US-PONTO-007 + ADR 0093.
 * BancoHorasController@show usa firstOrFail SEM business_id explícito — quem
 * valida o tenant é o global scope, não o firstOrFail (SDD §9 D-5).
 */
it('UC-BHSHOW-03 · extrato de colaborador de outro empregador dá 404 [must][T0]', function () {
    $this->actAsAdmin();
    bhimpPrecisaDe(['ponto_colaborador_config', 'ponto_banco_horas_saldo']);

    if ((int) $this->business->id === PontoTestCase::BIZ_ALHEIO_FICTICIO) {
        $this->markTestSkipped('Business logado colide com o business fictício do teste.');
    }

    $this->garantirBizAlheio();

    // Controle positivo: o MEU extrato abre. Sem ele, 404 de rota quebrada ou
    // permissão ausente passaria por "isolamento funcionando".
    $meu = bhimpCriarColaboradorComSaldo($this->business->id, $this->business->id);
    $this->inertiaGet("/ponto/banco-horas/{$meu->id}")->assertStatus(200);

    $alheio = bhimpCriarColaboradorComSaldo(PontoTestCase::BIZ_ALHEIO_FICTICIO, $this->business->id);

    $this->inertiaGet("/ponto/banco-horas/{$alheio->id}")
        ->assertStatus(404); // saldo é informação salarial — nunca vaza
});

/**
 * Contrato: Show.charter.md §Goals — "Histórico paginado (50/pág) de movimentos"
 * + §Automation hooks ("movimentos vem via Inertia::defer (paginate 50 lazy)").
 *
 * O servidor sempre paginou em 50; a tela é que não tinha controle de página e
 * escondia do movimento 51 em diante. Este caso prova, pelo mesmo partial reload
 * que o botão da tela faz (`only: ['movimentos']`), que a 2ª página existe, é
 * alcançável e traz o que faltou na 1ª — e que a soma das duas é o extrato inteiro.
 *
 * `created_at` distinto por movimento DE PROPÓSITO: o controller ordena só por
 * `created_at desc`, sem desempate por id; com timestamps iguais a fatia de cada
 * página não é determinística no MySQL. O caso fixa a ordem pra medir a paginação,
 * não a estabilidade da ordenação (que fica registrada no PR como achado à parte).
 */
it('UC-BHSHOW-04 · com mais de 50 movimentos a segunda página do extrato é alcançável [must]', function () {
    bhimpPrecisaDe(['ponto_colaborador_config', 'ponto_banco_horas_movimentos']);

    // Tenant canônico de teste = biz 98, fictício (ADR 0358) — não o Business::first
    // do actAsAdmin, que no CT 100 é a WR2 (empresa real, base clone de prod).
    [$this->business, $this->admin] = bhimpEntrarNoTenant98();

    $colab = bhimpCriarColaboradorComSaldo($this->business->id, $this->business->id);

    $base = now()->subDays(60);
    $criados = [];
    for ($i = 0; $i < 51; $i++) {
        $mov = new BancoHorasMovimento();
        $mov->forceFill([
            'business_id'           => $this->business->id,
            'colaborador_config_id' => $colab->id,
            'data_referencia'       => '2019-03-11',
            'tipo'                  => BancoHorasMovimento::TIPO_CREDITO,
            'minutos'               => 1,
            'observacao'            => 'Fixture paginação SDD.',
            'usuario_id'            => $this->admin->id,
            'created_at'            => $base->copy()->addMinutes($i),
        ])->save();
        $criados[] = $mov->id;
    }

    // Pré-condição anti-vácuo: o extrato do colaborador tem de fato 51 linhas.
    $this->assertSame(
        51,
        DB::table('ponto_banco_horas_movimentos')->where('colaborador_config_id', $colab->id)->count(),
        'Fixture precisa montar 51 movimentos — senão não há 2ª página pra alcançar.'
    );

    $url = "/ponto/banco-horas/{$colab->id}";

    $p1 = $this->inertiaPartialGet($url, ['movimentos'], 'Ponto/BancoHoras/Show');
    $p1->assertStatus(200);
    $m1 = $p1->json('props.movimentos');
    $this->assertIsArray($m1, 'A passada partial tem de trazer `movimentos`.');
    $this->assertSame(1, $m1['current_page']);
    $this->assertSame(2, $m1['last_page'], 'Com 51 movimentos e 50/pág o extrato tem 2 páginas (charter §Goals).');
    $this->assertSame(51, $m1['total']);
    $this->assertCount(50, $m1['data']);

    // O link "página 2" que o botão da tela segue existe e aponta pra ?page=2.
    $urlPagina2 = collect($m1['links'])->firstWhere('label', '2')['url'] ?? null;
    $this->assertNotNull($urlPagina2, 'A 1ª página precisa oferecer o link da 2ª.');
    $this->assertStringContainsString('page=2', $urlPagina2);

    $p2 = $this->inertiaPartialGet($url . '?page=2', ['movimentos'], 'Ponto/BancoHoras/Show');
    $p2->assertStatus(200);
    $m2 = $p2->json('props.movimentos');
    $this->assertSame(2, $m2['current_page']);
    $this->assertCount(1, $m2['data'], 'A 2ª página traz o 51º movimento — o que a tela escondia.');

    // As duas páginas juntas são o extrato inteiro, sem repetição nem buraco.
    $vistos = array_merge(array_column($m1['data'], 'id'), array_column($m2['data'], 'id'));
    sort($vistos);
    sort($criados);
    $this->assertSame($criados, $vistos, 'Página 1 + página 2 = todos os 51 movimentos, cada um uma vez.');
});

/**
 * Contrato: Show.charter.md §Goals — KPIs "Teto do acordo" e "Prazo de compensação"
 * (D-BH-KPI, [W] 2026-09-14) + a faixa do colaborador do protótipo (ponto-telas.jsx:370,
 * "matrícula · cargo · escala"). ADR 0093 para o cargo.
 *
 * O cargo vem de `categories` (tabela core, SEM o global scope do Ponto), então o
 * controller filtra `business_id` na mão. O caso monta a armadilha: o mesmo user apontando
 * para uma categoria de OUTRO business não pode fazer o nome dela aparecer.
 */
it('UC-BHSHOW-05 · extrato traz cargo, escala e o acordo do banco de horas; cargo de outro empregador não vaza [must][T0]', function () {
    bhimpPrecisaDe(['ponto_colaborador_config', 'ponto_escalas', 'categories']);

    [$this->business, $this->admin] = bhimpEntrarNoTenant98();
    $this->garantirBizAlheio();

    $colab = bhimpCriarColaboradorComSaldo($this->business->id, $this->business->id);

    $escalaId = DB::table('ponto_escalas')->insertGetId([
        'business_id'           => $this->business->id,
        'nome'                  => BHIMP_MARCADOR . '-Produção 5x2',
        'codigo'                => 'BHSH5',
        'tipo'                  => 'FIXA',
        'carga_diaria_minutos'  => 480,
        'carga_semanal_minutos' => 2400,
        'permite_banco_horas'   => true,
        'ativo'                 => 1,
        'created_at'            => now(),
        'updated_at'            => now(),
    ]);
    DB::table('ponto_colaborador_config')->where('id', $colab->id)->update(['escala_atual_id' => $escalaId]);

    $categoria = fn (int $biz, string $nome) => DB::table('categories')->insertGetId([
        'name' => BHIMP_MARCADOR . '-' . $nome, 'business_id' => $biz, 'parent_id' => 0,
        'created_by' => $this->admin->id, 'category_type' => 'hrm_designation',
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $meuCargo   = $categoria($this->business->id, 'Acabamento');
    $cargoAlheio = $categoria(PontoTestCase::BIZ_ALHEIO_FICTICIO, 'Cargo alheio');

    $userDoColab = DB::table('ponto_colaborador_config')->where('id', $colab->id)->value('user_id');
    $url = "/ponto/banco-horas/{$colab->id}";

    // (a) cargo do meu business + escala + acordo, direto no payload eager
    DB::table('users')->where('id', $userDoColab)->update(['essentials_designation_id' => $meuCargo]);
    $r = $this->inertiaGet($url);
    $r->assertStatus(200);
    $this->assertSame(BHIMP_MARCADOR . '-Acabamento', $r->json('props.saldo.cargo'));
    $this->assertSame(BHIMP_MARCADOR . '-Produção 5x2', $r->json('props.saldo.escala'));
    $this->assertSame(
        ['teto_horas' => 200, 'piso_horas' => -40, 'prazo_meses' => 6],
        $r->json('props.acordo'),
        'Teto, piso e prazo vêm do config pontowr2.banco_horas — o mesmo que o BancoHorasService aplica.'
    );

    // (b) o MESMO user apontando para categoria de outro business: o nome não pode vazar
    DB::table('users')->where('id', $userDoColab)->update(['essentials_designation_id' => $cargoAlheio]);
    $r2 = $this->inertiaGet($url);
    $r2->assertStatus(200);
    $this->assertNull(
        $r2->json('props.saldo.cargo'),
        'Categoria de outro empregador não pode aparecer como cargo (ADR 0093).'
    );
});

// =====================================================================
// Importacoes/Show
// =====================================================================

/**
 * Contrato: CU-PONTO-10 + US-PONTO-002 ("Importação idempotente — mesma AFD pode
 * ser re-uploadada sem duplicar marcacoes") + ImportacaoController@store (dedup
 * por hash_file sha256).
 */
it('UC-IMPSH-01 · reimportar o mesmo arquivo não duplica marcação [must]', function () {
    $this->actAsAdmin();
    bhimpPrecisaDe(['ponto_importacoes']);

    $hash = hash('sha256', 'conteudo-afd-fixture-sdd');
    bhimpCriarImportacao($this->business->id, $this->admin->id, ['hash_arquivo' => $hash]);

    $antes = DB::table('ponto_importacoes')
        ->where('business_id', $this->business->id)
        ->where('hash_arquivo', $hash)
        ->count();

    $this->assertSame(1, $antes, 'Pré-condição: o arquivo já foi importado uma vez.');

    // A dedup é por CONTEÚDO (hash), não por nome — reimportar o mesmo conteúdo
    // não pode criar um segundo registro no mesmo business.
    $duplicou = false;
    try {
        bhimpCriarImportacao($this->business->id, $this->admin->id, ['hash_arquivo' => $hash]);
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
it('UC-IMPSH-02 · a dedup é do meu empregador, não global [must][T0]', function () {
    $this->actAsAdmin();
    bhimpPrecisaDe(['ponto_importacoes']);

    if ((int) $this->business->id === PontoTestCase::BIZ_ALHEIO_FICTICIO) {
        $this->markTestSkipped('Business logado colide com o business fictício do teste.');
    }

    $this->garantirBizAlheio();

    // Dois REP-A do mesmo modelo podem gerar arquivos byte-idênticos.
    $hash = hash('sha256', 'afd-identico-entre-empregadores');

    DB::table('ponto_importacoes')->insert([
        'business_id'   => PontoTestCase::BIZ_ALHEIO_FICTICIO,
        'tipo'          => 'AFD',
        'nome_arquivo'  => BHIMP_MARCADOR . '-alheio.txt',
        'arquivo_path'  => 'ponto/importacoes/alheio.txt',
        'hash_arquivo'  => $hash,
        'tamanho_bytes' => 1024,
        'usuario_id'    => $this->admin->id,
        'created_at'    => now(),
        'updated_at'    => now(),
    ]);

    $minha = bhimpCriarImportacao($this->business->id, $this->admin->id, ['hash_arquivo' => $hash]);

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
 * Contrato: CU-PONTO-12 + US-PONTO-007 + LGPD Art. 7º II.
 * A rota irmã (baixarOriginal) entrega o ARQUIVO BRUTO com as marcações do
 * outro empregador — o risco aqui é maior que nas demais telas (SDD §9 D-5).
 */
it('UC-IMPSH-03 · importação de outro empregador dá 404 [must][T0]', function () {
    $this->actAsAdmin();
    bhimpPrecisaDe(['ponto_importacoes']);

    if ((int) $this->business->id === PontoTestCase::BIZ_ALHEIO_FICTICIO) {
        $this->markTestSkipped('Business logado colide com o business fictício do teste.');
    }

    $this->garantirBizAlheio();

    // Controle positivo: a MINHA abre.
    $minha = bhimpCriarImportacao($this->business->id, $this->admin->id);
    $this->inertiaGet("/ponto/importacoes/{$minha->id}")->assertStatus(200);

    $alheioId = DB::table('ponto_importacoes')->insertGetId([
        'business_id'   => PontoTestCase::BIZ_ALHEIO_FICTICIO,
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
 * Contrato: CU-PONTO-11 (SDD §6.4) + US-PONTO-002 (aceitação: "Importacao registra
 * arquivo + checksum + linhas processadas + erros") + Portaria MTP 671/2021 Anexo I
 * (rastreabilidade).
 *
 * PREDIÇÃO ORIGINAL (2026-08-02): vermelho, denunciando a regressão D-8 (SDD §9) —
 * o controller lia `linhas_criadas`/`linhas_ignoradas`, campos inexistentes na
 * migration (`linhas_total`/`linhas_processadas`/`linhas_sucesso`/`linhas_erro`).
 *
 * ⚠️ A PREDIÇÃO CADUCOU — e a causa foi MEDIDA em 2026-09-04, não suposta: a D-8 foi
 * CORRIGIDA no controller. `ImportacaoController.php:44` e `:116` hoje fazem
 * `'linhas_criadas' => (int) ($i->linhas_sucesso ?? 0)` — a chave que o front consome
 * é MONTADA a partir da coluna real. Por isso este caso está VERDE (JUnit de main da
 * run 33938659118: o arquivo dá 7/7, 0 fail), e está verde pelo motivo CERTO: o assert
 * alcança o comportamento e o comportamento passou a estar correto.
 *
 * ⚠️ DÍVIDA QUE FICA, e é de CONTEÚDO, não deste PR de invólucro: (a) a mensagem do
 * assert abaixo ainda descreve o bug ANTIGO ("o controller lê `linhas_criadas`, que não
 * existe na tabela") — texto que só aparece em falha, mas que hoje mentiria; (b) o
 * `Importacoes/Show.casos.md:30` ainda marca este UC como "🧪 vermelho ESPERADO
 * (predição)". Pela regra de precedência (teste verde > casos > charter > SPEC) os dois
 * são o PERDEDOR e deviam ser corrigidos — NÃO aqui, de propósito: mexer na string do
 * assert destruiria a prova deste PR (21/22 asserções byte-idênticas), e tocar o
 * `.casos.md` acorda gate diff-aware sobre dívida alheia (§5 2026-07-12). Chip à parte.
 *
 * O assert é sobre COMPORTAMENTO ("a contagem exibida reflete o processado") — se a
 * correção for renomear o campo exposto, atualize front e este assert juntos.
 */
it('UC-IMPSH-04 · as contagens exibidas refletem o que a importação processou [must]', function () {
    $this->actAsAdmin();
    bhimpPrecisaDe(['ponto_importacoes']);

    $imp = bhimpCriarImportacao($this->business->id, $this->admin->id, [
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
