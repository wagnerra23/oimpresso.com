<?php

declare(strict_types=1);
// @covers-us US-FIN-062

use App\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use Modules\Financeiro\Models\Titulo;
use Spatie\Permission\Models\Permission;

uses(Tests\TestCase::class);

/**
 * Impostos & obrigações — CONTRATO (MV batch 2026-07-06, piloto Módulo Vivo).
 *
 * Complementa ImpostosGuardTest.php (I1-I5) fechando os UCs que lá tinham só
 * cobertura de shape (I1) ou eram manuais (UC-IMP-04/05). Toda asserção deriva
 * do charter/US-FIN-062 (não do código): valores são recomputados de forma
 * independente do Controller pra que um bug no Controller QUEBRE o teste.
 *
 *  (C1) UC-IMP-08 — kpis.a_recolher.valor == soma das guias abertas (calendario)
 *  (C2) UC-IMP-09 — valor recalculado server-side; client não injeta valor/venc
 *  (C3) UC-IMP-10 — costura NF↔título: sem_nf/pct_com_nf derivados de metadata
 *  (C4) UC-IMP-11 — guia quitada sai de a_recolher e do calendario
 *  (C5) UC-IMP-07 — disclaimer de estimativa fixo (copy no rodapé + das_rate que o alimenta)
 *
 * TÍTULO É O QUE CONTA (2026-08-31): o `casos-results-collect` extrai o veredito por UC do
 * NOME do <testcase> no JUnit — UC citado só aqui no docblock satisfaz o G-2 (não é órfão)
 * mas NUNCA vira ✅ (fica no balde `teto_so_docblock` do casos-coverage-guard --report). Por
 * isso UC-IMP-04 e UC-IMP-05 estão nos TÍTULOS de C4/C3, não só nesta lista.
 *
 * Padrão dos GUARDs Financeiro: skip gracioso (greenfield/module gate) + limpeza
 * via DB raw (fin_titulos bloqueia hard delete por DomainException).
 *
 * TENANT: quem resolve é `seededTenant()` (tests/Support/WithSeededTenant.php:78) — biz=**98**,
 * o tenant fictício canônico da ADR 0358, com fallback pro primeiro business em schema montado
 * pelo próprio teste. A linha anterior aqui dizia "biz=1 (ADR 0101)": a 0358 supersede a 0101
 * e o código já resolvia 98 — era prosa desatualizada afirmando o tenant errado (corrigido
 * 2026-08-31, lendo o trait, não o comentário). biz=4 (ROTA LIVRE) é proibido sem exceção.
 */

function impContratoBootstrap(): User
{
    // Tenant canônico via trait WithSeededTenant (biz=1, skip acionável se seed ausente) —
    // NUNCA resolução crua de tenant em teste novo (catraca foundation-ratchet n_business_first).
    try {
        $business = test()->seededTenant();
    } catch (\Throwable $e) {
        test()->markTestSkipped('Tabela business indisponível: '.$e->getMessage());
    }

    $user = User::where('business_id', $business->id)->first();

    if (! $user) {
        test()->markTestSkipped('Sem user no business.');
    }

    Permission::firstOrCreate(['name' => 'financeiro.dashboard.view', 'guard_name' => 'web']);
    if (! $user->hasPermissionTo('financeiro.dashboard.view')) {
        $user->givePermissionTo('financeiro.dashboard.view');
    }

    session([
        'user.business_id' => $business->id,
        'user.id'          => $user->id,
        'business.id'      => $business->id,
        'business.name'    => $business->name,
        'business'         => ['id' => $business->id, 'name' => $business->name, 'currency_symbol' => 'R$'],
        'is_admin'         => true,
    ]);

    return $user;
}

function impContratoCleanup(int ...$tituloIds): void
{
    if (empty($tituloIds)) {
        return;
    }
    DB::table('fin_titulo_baixas')->whereIn('titulo_id', $tituloIds)->delete();
    DB::table('fin_titulos')->whereIn('id', $tituloIds)->delete();
}

function impContratoGet(User $user)
{
    // Lane backend do financeiro-pest não builda o JS → ensure_pages_exist dá
    // falso-negativo mesmo com Index.tsx no repo (mesmo motivo do I1). Desligamos
    // só a checagem de existência de arquivo; component()+props seguem validando.
    config(['inertia.testing.ensure_pages_exist' => false]);

    $response = test()->actingAs($user)->get('/financeiro/impostos');

    if (in_array($response->status(), [403, 404], true)) {
        test()->markTestSkipped('Module gate bloqueia neste env.');
    }

    return $response;
}

/**
 * Seed de 1 recebível quitado com baixa no dia informado (gera receita recebida
 * na competência → DAS estimado > 0). $comp = 'YYYY-MM'. Devolve [tituloId, baixaId].
 */
function impContratoSeedRecebido(int $businessId, int $userId, string $comp, float $valor = 1000.0): array
{
    $dia = $comp.'-05';
    $titulo = Titulo::create([
        'business_id'       => $businessId,
        'numero'            => 'IMPC-'.bin2hex(random_bytes(5)),
        'tipo'              => 'receber',
        'status'            => 'quitado',
        'cliente_descricao' => 'CONTRATO impostos — recebido',
        'valor_total'       => $valor,
        'valor_aberto'      => 0.0,
        'moeda'             => 'BRL',
        'emissao'           => $dia,
        'vencimento'        => $dia,
        'competencia_mes'   => $comp,
        'origem'            => 'manual',
        'created_by'        => $userId,
    ]);
    $baixaId = (int) DB::table('fin_titulo_baixas')->insertGetId([
        'business_id'     => $businessId,
        'titulo_id'       => $titulo->id,
        'valor_baixa'     => $valor,
        'juros'           => 0,
        'multa'           => 0,
        'desconto'        => 0,
        'data_baixa'      => $dia,
        'meio_pagamento'  => 'pix',
        'idempotency_key' => (string) Str::uuid(),
        'created_by'      => $userId,
        'created_at'      => now(),
    ]);

    return [$titulo->id, $baixaId];
}

// ── C1 · UC-IMP-08 ──────────────────────────────────────────────────────────
it('UC-IMP-08 · C1: kpis.a_recolher.valor é a soma das guias abertas (não só shape)', function () {
    $user = impContratoBootstrap();
    $bizId = (int) $user->business_id;
    $comp = now()->format('Y-m');

    // Receita recebida garante que o DAS estimado (guia aberta) exista no mês.
    [$recId, $baixaId] = impContratoSeedRecebido($bizId, $user->id, $comp, 1000.0);

    try {
        impContratoGet($user)->assertInertia(function (AssertableInertia $page) {
            $props = $page->toArray()['props'];
            $abertas = collect($props['calendario'] ?? []);

            // O KPI é DERIVADO das abertas — recompomos aqui pra pegar drift do Controller.
            $somaAbertas = round((float) $abertas->sum('valor'), 2);
            $kpiValor = round((float) data_get($props, 'kpis.a_recolher.valor'), 2);
            $kpiQtd = (int) data_get($props, 'kpis.a_recolher.qtd');

            expect($abertas->count())->toBeGreaterThan(0); // DAS estimado presente
            expect($kpiValor)->toEqualWithDelta($somaAbertas, 0.01);
            expect($kpiQtd)->toBe($abertas->count());

            // Nenhuma guia paga pode entrar no calendário (invariante do KPI só-abertas).
            expect($abertas->pluck('status'))->not->toContain('paga');
        });
    } finally {
        impContratoCleanup($recId, $baixaId);
    }
});

// ── C2 · UC-IMP-09 ──────────────────────────────────────────────────────────
it('UC-IMP-09 · C2: valor é recalculado server-side — client não injeta valor/vencimento', function () {
    $user = impContratoBootstrap();
    $bizId = (int) $user->business_id;
    $comp = now()->format('Y-m');

    [$recId, $baixaId] = impContratoSeedRecebido($bizId, $user->id, $comp, 1000.0);
    $criados = [];

    try {
        // Client tenta injetar valor absurdo + vencimento + status. Controller deve ignorar.
        $r = test()->actingAs($user)->post('/financeiro/impostos/lancar', [
            'competencia' => $comp,
            'valor'       => 999999.99,
            'vencimento'  => '2099-01-01',
            'status'      => 'quitado',
        ]);

        if (in_array($r->status(), [403, 404], true)) {
            test()->markTestSkipped('Module gate bloqueia neste env.');
        }
        $r->assertRedirect();

        $guia = Titulo::where('business_id', $bizId)
            ->where('tipo', 'pagar')
            ->where('metadata->guia', "das-{$comp}")
            ->first();
        expect($guia)->not->toBeNull();
        $criados[] = $guia->id;

        // Valor = 6% de R$ 1000,00 = R$ 60,00 (recalculado), NUNCA 999999.99.
        expect((float) $guia->valor_total)->toEqualWithDelta(60.0, 0.5);
        expect((float) $guia->valor_total)->toBeLessThan(1000.0);

        // Vencimento = dia 20 do mês seguinte à competência, não o do payload.
        $vencEsperado = \Carbon\Carbon::createFromFormat('Y-m', $comp)
            ->startOfMonth()->addMonthNoOverflow()->setDay(20)->toDateString();
        expect(substr((string) $guia->vencimento, 0, 10))->toBe($vencEsperado);

        // Status inicial é sempre 'aberto', nunca o 'quitado' injetado.
        expect($guia->status)->toBe('aberto');
    } finally {
        impContratoCleanup(...array_merge([$recId, $baixaId], $criados));
    }
});

// ── C3 · UC-IMP-10 ──────────────────────────────────────────────────────────
it('UC-IMP-05 · UC-IMP-10 · C3: costura NF↔título — sem_nf e pct_com_nf derivam de metadata.nfe', function () {
    $user = impContratoBootstrap();
    $bizId = (int) $user->business_id;
    $venc = now()->setDay(15)->toDateString(); // dentro do mês corrente
    $criados = [];

    try {
        // Recebível SEM NF vinculada.
        $semNf = Titulo::create([
            'business_id'       => $bizId,
            'numero'            => 'IMPC-'.bin2hex(random_bytes(5)),
            'tipo'              => 'receber',
            'status'            => 'aberto',
            'cliente_descricao' => 'CONTRATO NF — sem nota',
            'valor_total'       => 500.0,
            'valor_aberto'      => 500.0,
            'moeda'             => 'BRL',
            'emissao'           => $venc,
            'vencimento'        => $venc,
            'competencia_mes'   => now()->format('Y-m'),
            'origem'            => 'manual',
            'created_by'        => $user->id,
        ]);
        $criados[] = $semNf->id;

        // Recebível COM NF vinculada (metadata.nfe_numero).
        $comNf = Titulo::create([
            'business_id'       => $bizId,
            'numero'            => 'IMPC-'.bin2hex(random_bytes(5)),
            'tipo'              => 'receber',
            'status'            => 'aberto',
            'cliente_descricao' => 'CONTRATO NF — com nota',
            'valor_total'       => 700.0,
            'valor_aberto'      => 700.0,
            'moeda'             => 'BRL',
            'emissao'           => $venc,
            'vencimento'        => $venc,
            'competencia_mes'   => now()->format('Y-m'),
            'origem'            => 'manual',
            'metadata'          => ['nfe_numero' => '12345'],
            'created_by'        => $user->id,
        ]);
        $criados[] = $comNf->id;

        impContratoGet($user)->assertInertia(function (AssertableInertia $page) use ($semNf, $comNf) {
            $props = $page->toArray()['props'];
            $semNfProps = collect($props['sem_nf'] ?? []);

            // O sem-NF entra no painel; o com-NF não.
            expect($semNfProps->pluck('numero'))->toContain($semNf->numero);
            expect($semNfProps->pluck('numero'))->not->toContain($comNf->numero);

            // pct_com_nf está entre 0 e 100 e reflete que NEM tudo tem NF (< 100).
            $pct = (int) data_get($props, 'kpis.pct_com_nf');
            expect($pct)->toBeGreaterThanOrEqual(0)->toBeLessThan(100);

            // sem_nf_qtd conta ao menos o nosso título sem NF.
            expect((int) data_get($props, 'kpis.sem_nf_qtd'))->toBeGreaterThanOrEqual(1);
        });
    } finally {
        impContratoCleanup(...$criados);
    }
});

// ── C4 · UC-IMP-04 + UC-IMP-11 ──────────────────────────────────────────────
//
// UC-IMP-04 entra no TÍTULO (2026-08-31) — não é teste novo: o UC-IMP-11 nasceu no MV batch
// 2026-07-06 declarando-se "refina UC-IMP-04 pra asserção backend", e o critério de aceite dos
// dois é o mesmo ("guia quitada aparece com status paga e sai do KPI A recolher e do
// calendário"). Faltava só o id no título, de onde o veredito é extraído — mesmo caminho que
// o UC-IMP-05 seguiu em 2026-07-27 (#4882), e o oposto de escrever duplicata.
it('UC-IMP-04 · UC-IMP-11 · C4: guia quitada aparece como paga mas sai de a_recolher e do calendario', function () {
    $user = impContratoBootstrap();
    $bizId = (int) $user->business_id;
    $criados = [];

    try {
        // Guia (título payable com descritivo de guia) já QUITADA, vencida no mês.
        $guiaPaga = Titulo::create([
            'business_id'       => $bizId,
            'numero'            => 'P-'.random_int(90000, 99999),
            'tipo'              => 'pagar',
            'status'            => 'quitado',
            'cliente_descricao' => 'DAS · Simples Nacional (CONTRATO quitado)',
            'valor_total'       => 123.45,
            'valor_aberto'      => 0.0,
            'moeda'             => 'BRL',
            'emissao'           => now()->subDays(10)->toDateString(),
            'vencimento'        => now()->subDays(3)->toDateString(),
            'competencia_mes'   => now()->subMonthNoOverflow()->format('Y-m'),
            'origem'            => 'manual',
            'created_by'        => $user->id,
        ]);
        $criados[] = $guiaPaga->id;

        impContratoGet($user)->assertInertia(function (AssertableInertia $page) use ($guiaPaga) {
            $props = $page->toArray()['props'];

            $guias = collect($props['guias'] ?? []);
            $calendario = collect($props['calendario'] ?? []);

            // Aparece na tabela de guias com status 'paga'.
            $linha = $guias->firstWhere('lanc', $guiaPaga->numero);
            expect($linha)->not->toBeNull();
            expect($linha['status'])->toBe('paga');

            // NÃO entra no calendário (só abertas).
            expect($calendario->pluck('lanc'))->not->toContain($guiaPaga->numero);

            // NÃO soma no a_recolher — asserção DIRETA sobre o KPI (2026-08-31). Antes esta
            // linha era `firstWhere(...)->toBeNull()`, que repetia o assert de cima e deixava
            // a metade "sai do KPI A recolher" do UC-IMP-04 provada só por inferência.
            // Aqui o KPI é amarrado às abertas e o valor da guia paga é excluído dos dois.
            $kpiValor = round((float) data_get($props, 'kpis.a_recolher.valor'), 2);
            $somaAbertas = round((float) $calendario->sum('valor'), 2);
            expect($kpiValor)->toEqualWithDelta($somaAbertas, 0.01);
            expect((int) data_get($props, 'kpis.a_recolher.qtd'))->toBe($calendario->count());
        });
    } finally {
        impContratoCleanup(...$criados);
    }
});

// ── C5 · UC-IMP-07 ──────────────────────────────────────────────────────────
//
// O UC-IMP-07 era o ÚNICO órfão desta tela (`uc-orphan:...#UC-IMP-07` no
// scripts/casos-coverage-baseline.json) e o único órfão de risco de negócio do projeto:
// "Disclaimer sempre visível" defende o anti-pattern nº 1 do charter — apresentar
// ESTIMATIVA como APURAÇÃO numa tela de imposto.
//
// ÂNCORA (contrato, não código): Index.casos.md UC-IMP-07 cita a copy com reticências —
// "Estimativa visual … apuração oficial … módulo Fiscal" — e o charter a lista em Goals
// ("Disclaimer fixo … sempre visível no rodapé") e em Automation Anti-hooks ("Agente NÃO
// remove o disclaimer de estimativa" · "NÃO altera a alíquota ≈6% sem ADR"). Os 3 fragmentos
// abaixo são os do contrato, na ordem do contrato — não foram lidos do .tsx.
//
// DUAS PERNAS SEPARADAS DE PROPÓSITO, e o motivo é mecânico: o coletor de veredito
// (casos-results-collect) resolve o UC como `pass` se ≥1 testcase rodou e nenhum falhou —
// skip não conta como pass. A perna de COPY é filesystem puro e NUNCA pula; a de PROP
// depende de DB/module gate e pode pular. Juntas num teste só, um env sem banco silenciaria
// também o guard de copy — que é justamente o que defende o anti-hook.
//
// LIMITE DECLARADO: isto prova que a copy não sumiu da FONTE e que o dado que a alimenta
// continua vindo do servidor. NÃO prova renderização visível na tela (isso exige e2e/axe ou
// visual-regression, que esta tela ainda não tem — registrado como resíduo no casos.md).

it('UC-IMP-07 · C5a: disclaimer de estimativa está na fonte da tela, na ordem do contrato', function () {
    $tsx = __DIR__.'/../../../../resources/js/Pages/Financeiro/Impostos/Index.tsx';
    expect(file_exists($tsx))->toBeTrue('Index.tsx da tela Impostos não encontrado em '.$tsx);

    // Texto RENDERIZÁVEL. Duas normalizações, e a PRIMEIRA é a que dá validade ao guard:
    //
    // (1) COMENTÁRIOS SAEM. O cabeçalho do próprio Index.tsx (linhas 11-12) repete a copy em
    //     prosa: "…apuração oficial, cálculo por anexo e emissão de guia moram no módulo
    //     Fiscal." Varrendo o arquivo inteiro, alguém podia DELETAR o <p> do disclaimer e o
    //     assert seguia verde pelo comentário — presence-gate mudo (LC-11), o defeito que
    //     este teste existe pra não ter. Medido antes de escrever: com comentário incluso o
    //     controle negativo (disclaimer apagado) passava; sem, ele falha nos 3 fragmentos.
    // (2) Expressões JSX e tags saem, pra o assert falar da COPY e não da marcação — sem isso
    //     "módulo <b …>Fiscal</b>" nunca casaria o fragmento "módulo Fiscal" do contrato.
    $src = (string) file_get_contents($tsx);
    $texto = (string) preg_replace('~/\*[\s\S]*?\*/~u', ' ', $src);            // comentário de bloco
    $texto = implode("\n", array_map(                                          // comentário de linha
        // só linha que COMEÇA com // — não come "https://" no meio de string
        static fn (string $l): string => preg_match('~^\s*//~', $l) === 1 ? ' ' : $l,
        (array) preg_split('/\r?\n/', $texto)
    ));
    $texto = (string) preg_replace('/\{[^{}]*\}/u', ' ', $texto);              // expressões JSX
    $texto = (string) preg_replace('/<[^>]*>/u', ' ', $texto);                 // tags
    $texto = (string) preg_replace('/\s+/u', ' ', $texto);                     // colapsa espaço

    $fragmentos = ['Estimativa visual', 'apuração oficial', 'módulo Fiscal'];

    // Forma `expect(<bool>)->toBeTrue($msg)` de propósito, e não `->not->toBeFalse($msg)`:
    // a mensagem custom em matcher NEGADO (OppositeExpectation) não tem um só precedente no
    // repo — 0 ocorrências de `->not->toX(…, 'msg')` — enquanto `toBeTrue($msg)` e
    // `toBeGreaterThan($x, $msg)` têm. Sem poder rodar Pest local (ADR 0062), escolho a
    // forma que a árvore já prova em vez da que eu teria de supor.
    $pos = -1;
    foreach ($fragmentos as $frag) {
        $achado = mb_strpos($texto, $frag);
        expect($achado !== false)->toBeTrue(
            "Disclaimer de estimativa perdeu o fragmento «{$frag}» (charter Automation Anti-hook: ".
            'o agente NÃO remove o disclaimer). Apresentar estimativa como apuração é o '.
            'anti-pattern nº 1 da tela — se a copy mudou de propósito, atualize o UC-IMP-07 '.
            'em Index.casos.md no MESMO PR.'
        );
        // Ordem do contrato: "Estimativa visual … apuração oficial … módulo Fiscal".
        expect((int) $achado)->toBeGreaterThan($pos, "Fragmento «{$frag}» fora da ordem do contrato.");
        $pos = (int) $achado;
    }
});

it('UC-IMP-07 · C5b: das_rate continua vindo do servidor — sem ela o disclaimer mente', function () {
    $user = impContratoBootstrap();

    // O disclaimer imprime a alíquota a partir da PROP `das_rate` (Math.round(das_rate*100)%).
    // Se o Controller parar de mandá-la, a frase continua na fonte (C5a segue verde) mas
    // renderiza "NaN%" — disclaimer presente e mentindo, que é o pior dos mundos numa tela
    // fiscal. Não duplica o I2 do ImpostosGuardTest: lá o 6% é verificado no VALOR do título
    // lançado; aqui, na prop que alimenta o texto. São duas superfícies distintas.
    impContratoGet($user)->assertInertia(function (AssertableInertia $page) {
        $props = $page->toArray()['props'];

        expect($props)->toHaveKey('das_rate');
        // ≈6% é decisão de regime (charter: "NÃO altera a alíquota ≈6% sem ADR").
        expect((float) data_get($props, 'das_rate'))->toEqualWithDelta(0.06, 0.0001);
        // receita_recebida é o outro dado do disclaimer ("… sobre R$ X recebidos no mês").
        expect($props)->toHaveKey('receita_recebida');
    });
});
