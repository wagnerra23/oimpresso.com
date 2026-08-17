<?php

declare(strict_types=1);

use App\User;
use Spatie\Permission\Models\Permission;

uses(Tests\TestCase::class);

/**
 * Painel da Jana (`/ia`) — CONTRATO. Fecha os UC órfãos do `Index.casos.md`.
 *
 * Cada `it()` CITA o UC que defende — sem a citação o `casos-gate` (G-2) conta o
 * caso como órfão, e um teste que não se liga ao contrato é só código verde.
 *
 * ── POR QUE NÃO É TAUTOLÓGICO (§5 2026-06-05) ───────────────────────────────
 * As asserções vêm de TRÊS fontes externas ao `.tsx`, nesta ordem:
 *   1. `resources/js/Pages/Jana/Index.charter.md` (lei) — §Goals/§Anti-hooks
 *   2. `prototipo-ui/contrato/jana-painel.contract.json` — copy LITERAL + ordem
 *   3. `memory/requisitos/Jana/SPEC.md` — US-COPI-010/011/148 + ADR 0093
 * Nenhum valor foi lido do Controller/Page pra depois ser "confirmado" nele.
 *
 * ── DUAS NATUREZAS DE ASSERÇÃO, DE PROPÓSITO ────────────────────────────────
 * UC-01/02/03/07 são de RUNTIME (HTTP + Inertia) — precisam de tenant semeado.
 * UC-04/05/06/09/10 são de ARQUIVO (copy e vocabulário vivem no .tsx, não no
 * payload). Asserção de arquivo aqui não é preguiça: a copy do contrato É o
 * artefato, e o `contrato-de-tela` que a vigia é ADVISORY — não bloqueia merge.
 * Este teste dá a ela um dente que morde na lane que roda de verdade.
 *
 * UC-COPI-PAINEL-08 (skeleton) ganhou caso quando o conserto nasceu — nunca antes.
 *
 * Tenant: `seededTenant()` (trait WithSeededTenant) — nunca resolução crua.
 * Skip acionável se o seed não rodou (UPos não migra em SQLite; a lane real é
 * MySQL no CI/CT 100). ⚠️ skip sai exit 0 — leia ASSERTIONS, não "0 failed" (LC-13).
 */
const PAINEL_TSX = 'resources/js/Pages/Jana/Index.tsx';
const PAINEL_CONTRATO = 'prototipo-ui/contrato/jana-painel.contract.json';

function painelBootstrap(): User
{
    try {
        $business = test()->seededTenant();
    } catch (\Throwable $e) {
        test()->markTestSkipped('Tabela business indisponível: '.$e->getMessage());
    }

    $user = User::where('business_id', $business->id)->first();

    if (! $user) {
        test()->markTestSkipped("Sem user em business_id={$business->id}.");
    }

    // O grupo `/ia` é protegido por `can:jana.access` — e isso é CORRETO: o
    // `JanaAccessGateTest` tem um caso dedicado provando que sem a permissão a rota
    // DEVE dar 403 ("MORDE: não-admin SEM jana.access leva 403 em /ia"). Sem esta
    // concessão os 4 casos de runtime abaixo tomavam 403 e reprovavam por SETUP, não
    // por defeito de produto.
    //
    // O defeito ficou 1 dia invisível porque este arquivo estava FORA da allowlist da
    // lane `PHP / Pest (Jana · MySQL)` — nunca rodou. Ao entrar na lista (PR desta
    // data), ele acusou na primeira execução. Padrão copiado do `JanaAccessGateTest`,
    // que é o dono do tema; o gate NÃO é afrouxado aqui — damos ao usuário de teste a
    // permissão que o usuário real tem.
    try {
        Permission::findOrCreate('jana.access', 'web');
        $user->givePermissionTo('jana.access');
    } catch (\Throwable $e) {
        test()->markTestSkipped('Não foi possível garantir a permission jana.access: '.$e->getMessage());
    }

    test()->actingAs($user);
    session([
        'user.business_id' => $business->id,
        'user.id'          => $user->id,
        'business'         => ['id' => $business->id, 'name' => $business->name],
    ]);

    return $user;
}

/** Lê a copy declarada no contrato — a FONTE da asserção, nunca o .tsx. */
function painelCopyDoContrato(string $secaoId): array
{
    $j = json_decode(file_get_contents(base_path(PAINEL_CONTRATO)), true);

    foreach (($j['secoes'] ?? $j['sections'] ?? []) as $s) {
        if (($s['id'] ?? null) === $secaoId) {
            return $s['copy'] ?? [];
        }
    }

    throw new RuntimeException("Seção '{$secaoId}' não existe no contrato — o contrato mudou sem o teste.");
}

function painelTsx(): string
{
    return file_get_contents(base_path(PAINEL_TSX));
}

// ── RUNTIME ──────────────────────────────────────────────────────────────────

/** UC-COPI-PAINEL-01 — rota abre o Painel (SPEC US-COPI-148: `/ia` é a rota viva). */
it('UC-COPI-PAINEL-01: GET /ia retorna 200 com Inertia component Jana/Index', function () {
    painelBootstrap();

    $this->get('/ia')
        ->assertStatus(200)
        ->assertInertia(fn ($page) => $page->component('Jana/Index'));
});

/**
 * UC-COPI-PAINEL-02 — contrato de props.
 * As 4 eager chegam no first render; `coworkAggregates` NÃO — ela é deferida
 * (charter §Goals + HOTFIX [W] 2026-05-25: `metas` não pode ser deferida porque
 * a Page lê `metas.length` direto).
 */
it('UC-COPI-PAINEL-02: as 4 props eager chegam e coworkAggregates NÃO vem no first render', function () {
    painelBootstrap();

    $this->get('/ia')->assertInertia(fn ($page) => $page
        ->component('Jana/Index')
        ->has('metas')
        ->has('sellKpis')
        ->has('insightsAggregates')
        ->has('janaContext.businessId')
        ->missing('coworkAggregates')
    );
});

/** UC-COPI-PAINEL-03 — Tier 0: escopo da sessão, nunca de input (ADR 0093). */
it('UC-COPI-PAINEL-03: janaContext.businessId vem da sessão e ignora ?business_id (Tier 0)', function () {
    $user = painelBootstrap();

    $this->get('/ia?business_id=999')->assertInertia(fn ($page) => $page
        ->where('janaContext.businessId', $user->business_id)
    );
});

/**
 * UC-COPI-PAINEL-04 — farol é do servidor.
 * Duas metades: o payload ENTREGA farol, e a regra NÃO voltou pro frontend.
 * (`FarolServerSideTest` cobre as fronteiras −5%/−15%; aqui é o contrato da tela.)
 */
it('UC-COPI-PAINEL-04: cada meta traz farol do servidor e o Index.tsx não recalcula', function () {
    painelBootstrap();

    $this->get('/ia')->assertInertia(function ($page) {
        $metas = $page->toArray()['props']['metas'] ?? [];

        foreach ($metas as $meta) {
            expect($meta)->toHaveKey('farol');
        }

        return $page;
    });

    expect(painelTsx())
        ->not->toMatch('/function\s+calcularFarol/')
        ->not->toMatch('/const\s+calcularFarol\s*=/');
});

// ── ARQUIVO (copy e vocabulário) ─────────────────────────────────────────────

/** UC-COPI-PAINEL-05 — o empty state declara ausência (estado real de 100% dos tenants). */
it('UC-COPI-PAINEL-05: a copy do empty state do contrato está na tela', function () {
    $tsx = painelTsx();

    foreach (painelCopyDoContrato('painel-metas-vazio') as $copy) {
        expect($tsx)->toContain($copy);
    }

    expect($tsx)->toContain('data-contract="painel-metas-vazio"');
});

/** UC-COPI-PAINEL-06 — meta sem apuração não vira zero. */
it('UC-COPI-PAINEL-06: meta sem apuração declara "Aguardando apuração…" em vez de zero', function () {
    $tsx = painelTsx();

    foreach (painelCopyDoContrato('painel-meta-apurando') as $copy) {
        expect($tsx)->toContain($copy);
    }

    expect($tsx)->toContain('data-contract="painel-meta-apurando"');
});

/** UC-COPI-PAINEL-07 — sparkline sem série declara ausência. */
it('UC-COPI-PAINEL-07: sparkline sem série declara "Sem histórico" em vez de desenhar zero', function () {
    $tsx = painelTsx();

    foreach (painelCopyDoContrato('painel-meta-sem-historico') as $copy) {
        expect($tsx)->toContain($copy);
    }

    expect($tsx)->toContain('data-contract="painel-meta-sem-historico"');
});

/**
 * UC-COPI-PAINEL-08 — enquanto o cockpit não chega, a tela NÃO mostra zero.
 *
 * O `?? 0` do `JanaCockpit` FICA (é ele que impede o TypeError e mantém válida a
 * entrada na `DEFER_GUARD_ONLY_ALLOWLIST`); o que este caso trava é o RENDER:
 * existe um sinal de carregamento derivado de `coworkAggregates === undefined`, e
 * os dois KPIs que dependem dele não são pintados enquanto isso.
 *
 * A asserção é de ARQUIVO porque o defeito é de render, e Pest não monta React.
 * Ela morde no que importa: apagar o `carregandoCockpit`, ou voltar a passar o
 * `<KpiCard>` direto, derruba o caso. O par visual (screenshot 1280/1440) é o
 * portão F1.5 e vive fora daqui.
 */
it('UC-COPI-PAINEL-08: o cockpit declara carregando em vez de pintar zero', function () {
    $cockpit = file_get_contents(base_path('resources/js/Pages/Jana/_components/JanaCockpit.tsx'));

    // 1. o sinal existe e é `undefined` (ausência), não falsy — `?? 0` já virou 0
    expect($cockpit)->toContain('coworkAggregates === undefined');

    // 2. os DOIS KPIs que dependem da prop deferida trocam de card enquanto carrega.
    //    `\(` no meio não é decoração: o JSX real é `carregandoCockpit ? (\n <KpiCardSkeleton`.
    expect($cockpit)
        ->toMatch('/carregandoCockpit\s*\?\s*\(\s*<KpiCardSkeleton label="Faturamento mês"/u')
        ->toMatch('/carregandoCockpit\s*\?\s*\(\s*<KpiCardSkeleton label="PIX hoje"/u');

    // 3. a série tem TRÊS arms — carregando · vazio-de-verdade · série. Asserção de
    //    ESTRUTURA, não de texto solto: `not->toContain("Carregando sparkline…")` seria
    //    falso-positivo, porque a frase antiga vive (legitimamente) no comentário que
    //    documenta a troca. Proibir a prosa proibiria registrar a decisão (§5 2026-07-26).
    expect($cockpit)
        ->toMatch('/carregandoCockpit\s*\?\s*\(\s*<SparklineSkeleton\s*\/>\s*\)\s*:\s*sparkline\.length === 0\s*\?/u')
        ->toContain('Sem histórico');

    // 4. controle negativo: os KPIs EAGER não podem ter virado skeleton junto —
    //    `insightsAggregates` chega no first render e esconder é regressão.
    expect($cockpit)
        ->not->toMatch('/carregandoCockpit\s*\?\s*\(\s*<KpiCardSkeleton label="Inadimplência total"/u')
        ->not->toMatch('/carregandoCockpit\s*\?\s*\(\s*<KpiCardSkeleton label="Ticket médio"/u');
});

/** UC-COPI-PAINEL-09 — as 5 âncoras existem e a ordem declarada é subsequência da ordem de arquivo. */
it('UC-COPI-PAINEL-09: as 5 âncoras data-contract existem e a ordem do contrato é respeitada', function () {
    $j   = json_decode(file_get_contents(base_path(PAINEL_CONTRATO)), true);
    $tsx = painelTsx();

    $posicoes = [];

    foreach (($j['secoes'] ?? $j['sections'] ?? []) as $s) {
        $ancora = 'data-contract="'.$s['id'].'"';
        expect($tsx)->toContain($ancora);
        $posicoes[$s['id']] = strpos($tsx, $ancora);
    }

    $declarada = array_map(fn ($id) => $posicoes[$id], $j['ordem']);
    $ordenada  = $declarada;
    sort($ordenada);

    expect($declarada)->toBe($ordenada);
});

/**
 * UC-COPI-PAINEL-10 — "Configurar" abre drawer e não promete o que o servidor não cumpre.
 *
 * Asserção de ARQUIVO pelo mesmo motivo do UC-08: o defeito é de render/promessa,
 * e Pest não monta React. O par visual é o portão F1.5 e vive fora daqui.
 *
 * O caso tem DUAS metades, e a segunda é a que importa. Ligar o botão é trivial;
 * o risco real é o drawer virar vitrine de promessa — foi por isso que o
 * `_pendente_w` do contrato manteve os dois botões fora dele ("pinar uma promessa
 * é congelá-la"). Então o teste trava a ausência das 4 promessas medidas em
 * 2026-08-17 como não-cumpríveis hoje: brief diário (gerado server-side, nenhum
 * cron lê o localStorage deste browser), TTS, retenção automática (o
 * `jana:retention-purge` foi DESCARTADO por [W]) e as 3 análises sem fonte de dado.
 */
it('UC-COPI-PAINEL-10: Configurar abre o drawer e não promete o que o servidor não cumpre', function () {
    $tsx    = painelTsx();
    $drawer = file_get_contents(base_path('resources/js/Pages/Jana/_components/JanaConfigDrawer.tsx'));

    // 1. o botão deixou de ser promessa: sem "(em breve)", com handler de abertura.
    expect($tsx)
        ->not->toContain('Configurar Brain B Jana (em breve)')
        ->toContain('setConfigAberto(true)')
        ->toContain('<JanaConfigDrawer');

    // 2+3. Asserção de ESTRUTURA, não de prosa — e é deliberado. `not->toContain('Frota')`
    //      passaria hoje só por acidente de capitalização (o cabeçalho do drawer cita
    //      "churn, frota e cheques" ao REGISTRAR por que eles não entraram), e quebraria
    //      no dia em que alguém reescrevesse o comentário. É o mesmo falso-positivo que o
    //      UC-08 acima documenta: proibir a prosa proibiria registrar a decisão (§5 2026-07-26).
    //
    //      O que morde de verdade é a contagem de controles: existem DOIS `<Switch` no
    //      drawer — os das análises (um `.map`) e o HITL travado. Qualquer toggle novo
    //      (brief, áudio, retenção, análise sem fonte) vira um terceiro e derruba o caso,
    //      independente da copy escolhida.
    expect(substr_count($drawer, '<Switch'))->toBe(2);

    //      E o conjunto de análises é fechado nos 4 ids que a tela renderiza.
    $cfg = file_get_contents(base_path('resources/js/Pages/Jana/_components/useJanaConfig.ts'));
    expect($cfg)
        ->toContain("export type JanaAnaliseId = 'inad' | 'fat' | 'conc' | 'metodos';")
        //  `{ id: '` com a aspa: sem ela a assinatura do tipo
        //  (`ReadonlyArray<{ id: JanaAnaliseId; …`) entra na conta e vira 5.
        ->and(substr_count($cfg, "{ id: '"))->toBe(4);

    // 4. o que vale pra empresa toda aponta pro dono server-side que já existe,
    //    em vez de ganhar um segundo dono no localStorage deste navegador.
    expect($drawer)->toContain('/ia/alertas/config');

    // 5. controle negativo — o drawer PRECISA seguir entregando o que é verdade.
    //    Sem isto, apagar o corpo inteiro passaria nos itens 2 e 3 acima.
    expect($drawer)
        ->toContain('Configurar a Jana')
        ->toContain('JANA_ANALISES.map');

    // 6. e o filtro tem efeito real no cockpit (senão o toggle é decorativo).
    $cockpit = file_get_contents(base_path('resources/js/Pages/Jana/_components/JanaCockpit.tsx'));
    expect($cockpit)->toContain("analisesVisiveis?.[id] !== false");
});

