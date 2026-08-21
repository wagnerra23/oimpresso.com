<?php

declare(strict_types=1);

use App\User;
use Modules\Jana\Entities\AcaoAprovacao;
use Modules\Jana\Services\AcaoHitlService;
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
    //    Rótulo "Receita 30 dias" desde 2026-08-21 (era "Receita mês", e antes
    //    "Faturamento mês"). Mesmo dado, mesma prop deferida.
    //
    //    ⚠️ A versão anterior desta nota dizia que "Receita mês" era "alinhamento de
    //    COPY com a âncora `jana-merge.jsx`". Isso é FALSO, e foi medido em
    //    2026-08-21: a âncora oficial NÃO tem este KPI. O rótulo veio de
    //    `chat-jana.jsx` :87 — o protótipo que o §5 de 2026-08-10 declarou
    //    NÃO-âncora. Lá ele é coerente, porque o delta ao lado é "vs mai/25" (mês
    //    contra mês); aqui o dado é de 30 dias deslizantes e o delta é diário.
    //    Corrigido junto com o rótulo em UC-COPI-PAINEL-14 — comentário que afirma
    //    proveniência errada é instrução ativa pra próxima sessão (§5 2026-08-10).
    expect($cockpit)
        ->toMatch('/carregandoCockpit\s*\?\s*\(\s*<KpiCardSkeleton label="Receita 30 dias"/u')
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
 * `jana:retention-purge` foi DESCARTADO por [W]) e as análises sem fonte de dado
 * (frota e cheques — o churn ouro ganhou fonte no UC-13 e virou o 5º card).
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
    //      "frota e cheques" ao REGISTRAR por que eles não entraram), e quebraria
    //      no dia em que alguém reescrevesse o comentário. É o mesmo falso-positivo que o
    //      UC-08 acima documenta: proibir a prosa proibiria registrar a decisão (§5 2026-07-26).
    //
    //      O que morde de verdade é a contagem de controles: existem DOIS `<Switch` no
    //      drawer — os das análises (um `.map`) e o HITL travado. Qualquer toggle novo
    //      (brief, áudio, retenção, análise sem fonte) vira um terceiro e derruba o caso,
    //      independente da copy escolhida.
    expect(substr_count($drawer, '<Switch'))->toBe(2);

    //      E o conjunto de análises é fechado nos ids que a tela renderiza. A contagem
    //      subiu de 4 pra 5 no PR do UC-13 (churn ouro) — o guard MORDEU como o charter
    //      previu ("toggle novo derruba o caso"), e subir o número aqui é o ato consciente
    //      que ele existe pra exigir, não um contorno.
    $cfg = file_get_contents(base_path('resources/js/Pages/Jana/_components/useJanaConfig.ts'));
    expect($cfg)
        ->toContain("export type JanaAnaliseId = 'inad' | 'fat' | 'conc' | 'metodos' | 'churn';")
        //  `{ id: '` com a aspa: sem ela a assinatura do tipo
        //  (`ReadonlyArray<{ id: JanaAnaliseId; …`) entra na conta e vira 5.
        ->and(substr_count($cfg, "{ id: '"))->toBe(5);

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

/**
 * UC-COPI-PAINEL-12 — a ação sugerida vira decisão registrada, e a prévia é do SERVIDOR.
 *
 * Quatro `it()` porque são quatro perguntas independentes; um só bloco esconderia
 * qual metade quebrou.
 *
 * A trava que a ordem 1 do `Index-visual-comparison.md` declarava era **backend** —
 * e é ele que estes casos defendem. O risco que reincide não é abrir um modal
 * (trivial): é o modal virar autoridade sobre número que ninguém apurou. É o mesmo
 * §Anti-hooks do farol (UC-04) e da fonte do drill (charter), agora no eixo da
 * PRÉVIA.
 */
function acaoCockpitTsx(): string
{
    return file_get_contents(base_path('resources/js/Pages/Jana/_components/JanaCockpit.tsx'));
}

/** UC-COPI-PAINEL-12 — o CTA deixou de ser decorativo e os rótulos casam com o backend. */
it('UC-COPI-PAINEL-12: o CTA abre o modal HITL e cada rótulo tem chave no AcaoHitlService', function () {
    $cockpit = acaoCockpitTsx();
    $modal   = file_get_contents(base_path('resources/js/Pages/Jana/_components/JanaAcaoModal.tsx'));

    // 1. o botão ganhou comportamento. A asserção é pela FORMA do botão morto
    //    (`title={`${a.cta.label} …`}`), não pela frase "HITL — em breve V2": essa
    //    frase está viva no comentário que REGISTRA o que saiu, e proibi-la
    //    proibiria registrar a decisão — o falso-positivo do §5 2026-07-26, o mesmo
    //    que mordeu os UC-10 e UC-11 na escrita.
    expect(substr_count($cockpit, 'onClick={() => setAcaoHitl({'))->toBe(1);
    expect($cockpit)
        ->toContain('<JanaAcaoModal')
        ->not->toContain('title={`${a.cta.label}');

    // 2. PARIDADE de vocabulário — o backend valida a chave e devolve 404 pro que
    //    não conhece (caso abaixo). Sem esta amarração, uma 6ª regra nascendo só no
    //    `.tsx` viraria um botão que abre modal e morre em 404 — botão morto com
    //    passo a mais. A contagem é o que morde: acrescentar regra sem chave derruba.
    expect(substr_count($cockpit, "cta: { label: '"))->toBe(count(AcaoHitlService::ACOES));

    foreach (AcaoHitlService::ACOES as $chave => $rotulo) {
        expect($cockpit)
            ->toContain("id: '{$chave}',")
            ->toContain("label: '{$rotulo}'");
    }

    // 3. a prévia NÃO nasce no cliente — vem da rota. A âncora (`JmAcaoModal`) traz
    //    as 4 prévias em texto fixo do Martinho; portar isso seria a mentira com
    //    selo de autoridade que o `JanaDrillDrawer` existe pra evitar.
    expect($modal)->toContain('/ia/acoes/${acao.id}/previa');

    // 4. o modal DIZ o alcance deste PR. Sem esta linha o botão "Aprovar" herdaria
    //    a promessa que o CTA acabou de largar ao virar "Revisar".
    expect($modal)->toContain('O envio entra quando o disparo for ligado.');
});

/** UC-COPI-PAINEL-12 — a prévia é gerada no servidor, e só pras chaves conhecidas. */
it('UC-COPI-PAINEL-12: a prévia vem do servidor com alcance, e ação desconhecida é 404', function () {
    painelBootstrap();

    $this->get('/ia/acoes/regua-whatsapp/previa')
        ->assertStatus(200)
        ->assertJsonStructure(['previa', 'contexto', 'alcance']);

    // Fail-secure: chave fora do dicionário não vira prévia nem registro.
    $this->get('/ia/acoes/inventada/previa')->assertNotFound();
    $this->post('/ia/acoes/inventada/aprovar')->assertNotFound();

    // E o Service fecha o próprio domínio — quem o chama DIRETO (job, tinker,
    // outro teste) não passa pelo `abort_unless` da rota. Sem esta asserção o
    // `default` do `match` seria código morto e a defesa valeria só pela rota.
    expect(fn () => app(AcaoHitlService::class)->previa('inventada', 1))
        ->toThrow(InvalidArgumentException::class);
});

/**
 * UC-COPI-PAINEL-12 — o que fica gravado é o recibo do SERVIDOR.
 *
 * O vetor que este caso fecha: se `previa` viesse do request, o cliente
 * reescreveria o que "foi aprovado" — e o ledger deixaria de ser recibo.
 */
it('UC-COPI-PAINEL-12: aprovar grava a prévia do servidor e ignora o texto do cliente', function () {
    painelBootstrap();

    $this->post('/ia/acoes/regua-whatsapp/aprovar', [
        'previa'   => 'TEXTO FORJADO PELO CLIENTE',
        'acao_key' => 'negociar-top',
        'status'   => 'executada',
    ])->assertRedirect();

    $registro = AcaoAprovacao::latest('id')->first();

    expect($registro)->not->toBeNull();
    expect($registro->previa)->not->toContain('TEXTO FORJADO PELO CLIENTE');
    // A chave e o status também vêm da rota/serviço, nunca do corpo do request.
    expect($registro->acao_key)->toBe('regua-whatsapp');
    expect($registro->status)->toBe('aprovada');
    // Controle negativo: sem isto, gravar string vazia passaria no `not->toContain`.
    expect($registro->previa)->toContain('cobrança');
});

/** UC-COPI-PAINEL-12 — Tier 0: o registro nasce escopado pela SESSÃO (ADR 0093). */
it('UC-COPI-PAINEL-12: a aprovação nasce com o business_id da sessão, nunca do request', function () {
    $user = painelBootstrap();

    $this->post('/ia/acoes/preventivo-pendentes/aprovar', ['business_id' => 999999])
        ->assertRedirect();

    $registro = AcaoAprovacao::latest('id')->first();

    expect($registro->business_id)->toBe((int) $user->business_id);
    expect($registro->user_id)->toBe((int) $user->id);

    // E o global scope não deixa a linha ser lida de fora do tenant. Contar sob o
    // scope é o que prova o isolamento — `where` cru só provaria a coluna.
    session(['user.business_id' => 999999]);
    expect(AcaoAprovacao::where('id', $registro->id)->count())->toBe(0);
});

// ── UC-COPI-PAINEL-13 — Churn ouro ───────────────────────────────────────────

/**
 * Semeia cliente + 1 venda final na data pedida, marcada por um sufixo único.
 *
 * `payment_status = 'paid'` de propósito: venda paga NÃO gera `fin_titulo`
 * (mesma razão do `TituloAutoServiceTest`), e `fin_titulos` não permite delete —
 * o cleanup do fim do caso ficaria impossível. O churn não olha pagamento, só
 * data e valor, então pagar não muda o que está sob teste.
 */
function churnSemear(int $businessId, int $userId, string $nome, float $total, string $data): array
{
    $contact = \App\Contact::create([
        'business_id' => $businessId,
        'type'        => 'customer',
        'name'        => $nome,
        'created_by'  => $userId,
    ]);

    $tx = \App\Transaction::create([
        'business_id'      => $businessId,
        'location_id'      => null,
        'type'             => 'sell',
        'status'           => 'final',
        'payment_status'   => 'paid',
        'contact_id'       => $contact->id,
        'invoice_no'       => 'CHURN-'.uniqid(),
        'transaction_date' => $data,
        'total_before_tax' => $total,
        'final_total'      => $total,
        'created_by'       => $userId,
    ]);

    return [$contact, $tx];
}

/**
 * UC-COPI-PAINEL-13 — o recorte é RELATIVO (maior LTV entre os parados), e o
 * controle negativo é o que prova: quem comprou ontem NÃO entra, mesmo valendo
 * muito mais. Sem esse caso, um bug que ignorasse a data passaria verde — o card
 * viraria "top clientes" com outro título.
 *
 * ⚠️ O CT 100 PERSISTE entre runs (§5 2026-07-28), então cada caso limpa o que
 * criou e todas as asserções são marcadas por `uniqid()` — nunca por contagem
 * absoluta, que quebraria com resíduo de outra execução.
 */
it('UC-COPI-PAINEL-13: churn ouro pega o parado >90d e IGNORA quem comprou ontem', function () {
    $user   = painelBootstrap();
    $bizId  = (int) session('user.business_id');
    $marca  = uniqid('chn');

    // (a) alto valor, parado há 200 dias → DEVE entrar.
    [$c1, $t1] = churnSemear($bizId, (int) $user->id, "Parado {$marca}", 90000.0, now()->subDays(200)->toDateString());
    // (b) valor 5× MAIOR, comprou ontem → NÃO pode entrar. Este é o controle negativo.
    [$c2, $t2] = churnSemear($bizId, (int) $user->id, "Ativo {$marca}", 450000.0, now()->subDay()->toDateString());

    try {
        $agg   = app(\App\Services\Sells\SellsCockpitAggregator::class)->buildInsightsAggregates($bizId);
        $nomes = collect($agg['churnOuro'])->pluck('name')->all();

        expect($nomes)->toContain("Parado {$marca}");
        expect($nomes)->not->toContain("Ativo {$marca}");

        $linha = collect($agg['churnOuro'])->firstWhere('name', "Parado {$marca}");

        // O número que a tela mostra é MEDIDO, não estimado.
        expect($linha['ltv'])->toBe(90000.0);
        expect($linha['diasInativo'])->toBeGreaterThan(90);
        expect($linha['ultimaCompra'])->toBe(now()->subDays(200)->toDateString());

        // Tier 0 (ADR 0093): o recorte é do tenant pedido, e de mais nenhum.
        $outro = app(\App\Services\Sells\SellsCockpitAggregator::class)->buildInsightsAggregates(999999);
        expect(collect($outro['churnOuro'])->pluck('name')->all())->not->toContain("Parado {$marca}");
    } finally {
        $t1->forceDelete();
        $t2->forceDelete();
        $c1->forceDelete();
        $c2->forceDelete();
    }
});

/**
 * UC-COPI-PAINEL-13 — o drill drawer promete um método por análise; todos têm de
 * EXISTIR. O componente nasceu justamente porque o protótipo cita classes
 * fictícias (`AnaliseChurnService` & cia., re-medido 2026-08-20: nenhuma existe),
 * e o cabeçalho dele pede "ao mexer no aggregator, mexa aqui no mesmo PR".
 *
 * O teste não olha o churn em particular: varre TODAS as fontes declaradas. Assim
 * ele defende a classe inteira do defeito, não a instância de hoje — e quebra na
 * hora em que alguém renomear um método do aggregator sem atualizar o drawer.
 *
 * `method_exists` sobre a classe REAL é comportamento, não presença de string:
 * um `metodo:` apontando pra nome inventado reprova mesmo estando bem escrito.
 */
it('UC-COPI-PAINEL-13: todo `metodo` prometido pelo JanaDrillDrawer existe no aggregator', function () {
    $drawer = file_get_contents(base_path('resources/js/Pages/Jana/_components/JanaDrillDrawer.tsx'));

    preg_match_all("/metodo: '([^']+)'/", $drawer, $m);

    // Controle positivo: se o regex parar de casar, o caso vira carimbo verde —
    // é a lápide §5 2026-08-01 (saída plausível de sonda que não rodou).
    expect($m[1])->not->toBeEmpty();

    $quebrados = [];

    foreach (array_unique($m[1]) as $ref) {
        [$classe, $metodo] = array_pad(explode('::', $ref), 2, null);
        $fqcn = 'App\\Services\\Sells\\'.$classe;

        if (! class_exists($fqcn) || ! method_exists($fqcn, $metodo)) {
            $quebrados[] = $ref;
        }
    }

    expect($quebrados)->toBe([]);
});

// ── UC-COPI-PAINEL-14 — a janela do KPI ──────────────────────────────────────

/**
 * UC-COPI-PAINEL-14 — o rótulo declara a janela REAL do dado.
 *
 * Asserção de ARQUIVO e ESTRUTURAL, pelos mesmos dois motivos dos UC-08/10/12: o
 * Pest não monta React, e a copy É o artefato. A forma escolhida é `label="…"`
 * (com o atributo), nunca a prosa solta — `not->toContain('Receita mês')`
 * FALHARIA, porque a frase está viva no comentário que registra o que saiu. É o
 * falso-positivo do §5 2026-07-26, que já mordeu esta suíte duas vezes.
 */
it('UC-COPI-PAINEL-14: o KPI da sparkline se chama pela janela que tem, não "mês"', function () {
    $cockpit = file_get_contents(base_path('resources/js/Pages/Jana/_components/JanaCockpit.tsx'));

    // O card e o skeleton dizem a MESMA coisa — senão o rótulo antigo pisca
    // enquanto a prop deferida não chega, e o usuário lê "mês" por um instante.
    expect(substr_count($cockpit, 'label="Receita 30 dias"'))->toBe(2);

    // E o valor continua vindo da série de 30 dias, sem o fallback morto.
    expect($cockpit)->toContain('value={fmtShort(sparkSum)}');

    // Controle negativo: a FORMA do rótulo antigo não volta.
    expect($cockpit)->not->toContain('label="Receita mês"');

    // O delta declara a própria janela — ele compara HOJE com ONTEM, e ao lado
    // de um valor de 30 dias o rótulo curto sugeria que o valor grande variou.
    expect($cockpit)->toContain("label: 'hoje vs ontem'");
});

/**
 * UC-COPI-PAINEL-14 — e a prova de que o `|| faturadoHoje` era INALCANÇÁVEL.
 *
 * Este caso existe porque a remoção de um fallback é exatamente o tipo de
 * mudança que parece perda de robustez. Ele demonstra a contenção: as duas
 * consultas têm filtros idênticos e a janela da série vai até o fim de hoje,
 * então toda venda de hoje JÁ está dentro da série. Se alguém encurtar a janela
 * (ex.: `endOfDay` → `startOfDay`) esta asserção quebra, e é o que se quer.
 */
it('UC-COPI-PAINEL-14: a série tem 30 dias e CONTÉM o faturamento de hoje', function () {
    $user  = painelBootstrap();
    $bizId = (int) session('user.business_id');
    $marca = uniqid('jan');

    [$c, $t] = churnSemear($bizId, (int) $user->id, "Hoje {$marca}", 1234.56, now()->toDateString());

    try {
        $agg   = app(\App\Services\Sells\SellsCockpitAggregator::class)->buildCoworkAggregates($bizId);
        $serie = $agg['sparkline'];

        expect($serie)->toHaveCount(30);

        $soma        = array_sum($serie);
        $faturadoHoje = (float) $agg['faturadoHojeTotal'];

        // A contenção: a soma da série cobre o de hoje. Com isso, `sparkSum` só
        // é 0 quando `faturadoHoje` também é — e aí o `||` devolvia o mesmo 0.
        expect($faturadoHoje)->toBeGreaterThan(0.0);
        expect($soma)->toBeGreaterThanOrEqual($faturadoHoje);

        // E o último ponto da série é HOJE (não ontem) — é isso que fecha.
        expect((float) $serie[29])->toBeGreaterThanOrEqual($faturadoHoje);
    } finally {
        $t->forceDelete();
        $c->forceDelete();
    }
});

/**
 * UC-COPI-PAINEL-15 — o card diz o PESO do vencido, não só a contagem.
 *
 * `overdueValue` e `totalAReceber` já chegavam no payload e ninguém os cruzava:
 * a tela dizia "1 venda vencida" sem dizer se isso é irrelevante ou metade do
 * caixa. A leitura é derivada, não pede backend novo.
 *
 * Asserção de ARQUIVO pelo mesmo motivo dos UC-08/10/12/14 — o Pest não monta
 * React e a fórmula vive no `.tsx`. O que morde aqui é a GUARDA do arredondamento:
 * sem ela, uma venda vencida de peso pequeno renderiza "0% do a receber" ao lado
 * de um card que afirma que ela venceu — número que contradiz o próprio card.
 * Medido em produção (biz=1) antes de escrever: a razão real dá < 1%.
 */
it('UC-COPI-PAINEL-15: o vencido declara seu peso, e nunca arredonda pra zero', function () {
    $cockpit = file_get_contents(base_path('resources/js/Pages/Jana/_components/JanaCockpit.tsx'));

    // 1. a razão é DERIVADA dos dois campos, não um número solto.
    expect($cockpit)->toContain('overdueValue / totalAReceber');

    // 2. a guarda do "<1%" existe — é ela que impede o zero enganoso.
    expect($cockpit)->toContain("'<1% do a receber'");

    // 3. e o card CONSOME a leitura (senão o derivado seria mais um campo órfão,
    //    exatamente o defeito que este PR corrige no eixo do payload).
    expect($cockpit)->toContain('pctVencidoTexto');

    // 4. controle negativo: a linha só aparece quando HÁ vencido e HÁ base —
    //    `totalAReceber > 0 && overdueValue > 0` é o que evita divisão por zero
    //    e "0% do a receber" num tenant sem nada a receber.
    expect($cockpit)->toContain('totalAReceber > 0 && overdueValue > 0');
});
