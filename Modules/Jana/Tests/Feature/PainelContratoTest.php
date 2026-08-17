<?php

declare(strict_types=1);

use App\User;

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
 * UC-PAINEL-08 (skeleton) ganhou caso quando o conserto nasceu — nunca antes.
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

/** UC-PAINEL-01 — rota abre o Painel (SPEC US-COPI-148: `/ia` é a rota viva). */
it('UC-PAINEL-01: GET /ia retorna 200 com Inertia component Jana/Index', function () {
    painelBootstrap();

    $this->get('/ia')
        ->assertStatus(200)
        ->assertInertia(fn ($page) => $page->component('Jana/Index'));
});

/**
 * UC-PAINEL-02 — contrato de props.
 * As 4 eager chegam no first render; `coworkAggregates` NÃO — ela é deferida
 * (charter §Goals + HOTFIX [W] 2026-05-25: `metas` não pode ser deferida porque
 * a Page lê `metas.length` direto).
 */
it('UC-PAINEL-02: as 4 props eager chegam e coworkAggregates NÃO vem no first render', function () {
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

/** UC-PAINEL-03 — Tier 0: escopo da sessão, nunca de input (ADR 0093). */
it('UC-PAINEL-03: janaContext.businessId vem da sessão e ignora ?business_id (Tier 0)', function () {
    $user = painelBootstrap();

    $this->get('/ia?business_id=999')->assertInertia(fn ($page) => $page
        ->where('janaContext.businessId', $user->business_id)
    );
});

/**
 * UC-PAINEL-07 — farol é do servidor.
 * Duas metades: o payload ENTREGA farol, e a regra NÃO voltou pro frontend.
 * (`FarolServerSideTest` cobre as fronteiras −5%/−15%; aqui é o contrato da tela.)
 */
it('UC-PAINEL-07: cada meta traz farol do servidor e o Index.tsx não recalcula', function () {
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

/** UC-PAINEL-04 — o empty state declara ausência (estado real de 100% dos tenants). */
it('UC-PAINEL-04: a copy do empty state do contrato está na tela', function () {
    $tsx = painelTsx();

    foreach (painelCopyDoContrato('painel-metas-vazio') as $copy) {
        expect($tsx)->toContain($copy);
    }

    expect($tsx)->toContain('data-contract="painel-metas-vazio"');
});

/** UC-PAINEL-05 — meta sem apuração não vira zero. */
it('UC-PAINEL-05: meta sem apuração declara "Aguardando apuração…" em vez de zero', function () {
    $tsx = painelTsx();

    foreach (painelCopyDoContrato('painel-meta-apurando') as $copy) {
        expect($tsx)->toContain($copy);
    }

    expect($tsx)->toContain('data-contract="painel-meta-apurando"');
});

/** UC-PAINEL-06 — sparkline sem série declara ausência. */
it('UC-PAINEL-06: sparkline sem série declara "Sem histórico" em vez de desenhar zero', function () {
    $tsx = painelTsx();

    foreach (painelCopyDoContrato('painel-meta-sem-historico') as $copy) {
        expect($tsx)->toContain($copy);
    }

    expect($tsx)->toContain('data-contract="painel-meta-sem-historico"');
});

/**
 * UC-PAINEL-08 — enquanto o cockpit não chega, a tela NÃO mostra zero.
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
it('UC-PAINEL-08: o cockpit declara carregando em vez de pintar zero', function () {
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

/** UC-PAINEL-09 — as 5 âncoras existem e a ordem declarada é subsequência da ordem de arquivo. */
it('UC-PAINEL-09: as 5 âncoras data-contract existem e a ordem do contrato é respeitada', function () {
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
 * UC-PAINEL-10 — a análise "Frota" não existe NESTA tela.
 * O `dominio-gate` não varre paths da Jana (seus `forbidden_ui_paths` são só
 * `Pages/OficinaAuto` + as 2 pastas de DB dele) — sem este caso, nada defende.
 * Escopo: código de UI. Prosa de charter e comentário que DOCUMENTAM a decisão
 * ficam de fora de propósito — proibir falar da regra proíbe registrar a regra.
 */
it('UC-PAINEL-10: nenhum termo de locação/frota em código de UI da Jana', function () {
    $arquivos = array_merge(
        glob(base_path('resources/js/Pages/Jana/*.tsx')) ?: [],
        glob(base_path('resources/js/Pages/Jana/**/*.tsx')) ?: [],
    );

    expect($arquivos)->not->toBeEmpty('glob não achou .tsx — a varredura não mediu nada');

    foreach ($arquivos as $arq) {
        $linhas = file($arq, FILE_IGNORE_NEW_LINES);

        foreach ($linhas as $i => $linha) {
            if (preg_match('#^\s*(//|\*|/\*)#', $linha)) {
                continue; // comentário que documenta a decisão não é UI
            }

            expect(mb_strtolower($linha))
                ->not->toMatch('/\b(locada|locadas|locacao|locação|caçamba|cacamba)\b/u',
                    basename($arq).':'.($i + 1).' reintroduz domínio erradicado (ADR 0265)');
        }
    }
});
