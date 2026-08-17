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
    //    Rótulo "Receita mês" desde 2026-08-17 (era "Faturamento mês") — alinhamento
    //    de COPY com a âncora `jana-merge.jsx`. Mesmo dado, mesma prop deferida.
    expect($cockpit)
        ->toMatch('/carregandoCockpit\s*\?\s*\(\s*<KpiCardSkeleton label="Receita mês"/u')
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
    //
    //    ⚠️ O rótulo aqui foi atualizado junto com o rename (2026-08-17:
    //    "Inadimplência total" → "A receber vencido"). Um `not->toMatch` cujo
    //    LABEL não existe mais passa VAZIO — verde por o alvo ter sumido, não por
    //    o comportamento estar certo. É a classe LC-11 (gate que mede presença em
    //    vez de comportamento) na sua forma mais silenciosa, porque um controle
    //    negativo já é verde por construção e ninguém nota que ele parou de medir.
    //    Regra ao renomear label: atualize o negativo no MESMO diff, ou ele vira
    //    decoração.
    expect($cockpit)
        ->not->toMatch('/carregandoCockpit\s*\?\s*\(\s*<KpiCardSkeleton label="A receber vencido"/u')
        ->not->toMatch('/carregandoCockpit\s*\?\s*\(\s*<KpiCardSkeleton label="Ticket médio"/u');

    //    Prova de que o par acima ainda tem alvo: os dois labels EXISTEM no arquivo
    //    (só não podem estar sob `carregandoCockpit ? <KpiCardSkeleton`). Sem isto,
    //    o próximo rename volta a esvaziar o negativo sem que nada acuse.
    expect($cockpit)
        ->toContain('label="A receber vencido"')
        ->toContain('label="Ticket médio"');
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


/**
 * UC-COPI-PAINEL-11 — a meta abre NA PRÓPRIA TELA, e o drawer não projeta o futuro.
 *
 * Asserção de ARQUIVO pelo mesmo motivo dos UC-08 e UC-10: o defeito é de
 * navegação/promessa, e o Pest não monta React. O par visual é o portão F1.5.
 *
 * Duas metades, e a segunda é a que reincide. Abrir um drawer é trivial; o risco
 * é ele virar autoridade sobre número que ninguém apurou. A âncora
 * (`jana-merge.jsx` §JmMetaDrawer) projeta o fechamento NO CLIENTE — `jmMeta()`
 * faz `atual * 1.3` quando a meta acumula e extrapola a tendência quando é
 * média/taxa. Portar isso seria o §Anti-hooks do farol de novo, no eixo da
 * projeção.
 */
it('UC-COPI-PAINEL-11: a meta abre em drawer na própria tela, sem projetar o fechamento', function () {
    $tsx    = painelTsx();
    $drawer = file_get_contents(base_path('resources/js/Pages/Jana/_components/JanaMetaDrawer.tsx'));

    // 1. o card deixou de ser um link que TIRA o usuário da tela: virou botão
    //    que abre o drawer. A asserção é pelo LINK, não pela copy "Ver detalhe":
    //    o comentário do `MetaCard` cita essa copy ao registrar o que saiu, e
    //    proibir a prosa proibiria registrar a decisão (§5 2026-07-26 — a mesma
    //    armadilha do item 3 abaixo, e ela mordeu esta suíte na escrita).
    expect($tsx)
        ->not->toContain('/ia/metas/${meta.id}')
        ->toContain('setMetaAberta')
        ->toContain('<JanaMetaDrawer');

    // 2. nenhuma capacidade se perdeu — o caminho pra tela própria migrou pro
    //    rodapé do drawer. Sem isto, "fechar a divergência" viraria remover
    //    acesso.
    expect($drawer)->toContain('/ia/metas/${meta.id}');

    // 3. ANTI-PROJEÇÃO — asserção ESTRUTURAL, não de prosa, e é deliberado.
    //    `not->toContain('Projeção')` FALHARIA hoje: o cabeçalho do arquivo cita
    //    a palavra ao REGISTRAR por que a projeção não entrou. Proibir a prosa
    //    proibiria registrar a decisão — o falso-positivo que o §5 2026-07-26
    //    cataloga (mesmo raciocínio do item 2+3 do UC-10 acima).
    //
    //    O que morde é a contagem de números da seção "Situação": são TRÊS, e os
    //    três saem de campos do payload. Uma projeção vira o quarto e derruba o
    //    caso, seja qual for o rótulo escolhido.
    expect(substr_count($drawer, '<Numero rotulo='))->toBe(3);
    expect($drawer)
        ->toContain('rotulo="Realizado"')
        ->toContain('rotulo="Alvo"')
        ->toContain('rotulo="% do alvo"');

    // 4. a fonte citada existe de verdade. A âncora cita `MetricasApurador::farol`
    //    — classe real, método inexistente (charter v4). O drawer se chama "de
    //    onde vem esse número"; nome errado ali é mentira com selo de autoridade.
    expect($drawer)
        ->toContain('ApuracaoService::farol')
        ->not->toContain('MetricasApurador');

    // 5. controle negativo — o drawer PRECISA seguir entregando o que é verdade.
    //    Sem isto, esvaziar o corpo passaria nos itens 3 e 4.
    expect($drawer)
        ->toContain('business_id')
        ->toContain('<Serie dados={serie}');
});
