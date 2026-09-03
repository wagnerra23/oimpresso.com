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
 * UC-JPAIN-08 (skeleton) ganhou caso quando o conserto nasceu — nunca antes.
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

/**
 * O texto de TODOS os arquivos que o contrato declara em `alvo`, concatenados.
 *
 * Existe porque `painelTsx()` lê só o `Index.tsx`, e o contrato passou a apontar
 * DOIS arquivos em 2026-08-28: a âncora `painel-plano` mora no
 * `_components/JanaPlanoBadge.tsx`, já que as 3 telas da área injetam o selo pelo
 * slot `actions` do header compartilhado. Com o alvo hard-coded aqui, o teste
 * procurava a âncora no arquivo errado e reprovava um contrato correto.
 *
 * Derivar do `alvo` (em vez de listar arquivos aqui) é o que impede a próxima
 * divergência: quem editar o contrato não precisa lembrar deste teste.
 */
function painelAlvoTexto(): string
{
    $j = json_decode(file_get_contents(base_path(PAINEL_CONTRATO)), true);

    return implode("\n", array_map(
        fn ($rel) => file_get_contents(base_path($rel)),
        (array) ($j['alvo'] ?? [PAINEL_TSX])
    ));
}

// ── RUNTIME ──────────────────────────────────────────────────────────────────

/** UC-JPAIN-01 — rota abre o Painel (SPEC US-COPI-148: `/ia` é a rota viva). */
it('UC-JPAIN-01: GET /ia retorna 200 com Inertia component Jana/Index', function () {
    painelBootstrap();

    $this->get('/ia')
        ->assertStatus(200)
        ->assertInertia(fn ($page) => $page->component('Jana/Index'));
});

/**
 * UC-JPAIN-02 — contrato de props.
 * As 4 eager chegam no first render; `coworkAggregates` NÃO — ela é deferida
 * (charter §Goals + HOTFIX [W] 2026-05-25: `metas` não pode ser deferida porque
 * a Page lê `metas.length` direto).
 */
it('UC-JPAIN-02: as 4 props eager chegam e coworkAggregates NÃO vem no first render', function () {
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

/** UC-JPAIN-03 — Tier 0: escopo da sessão, nunca de input (ADR 0093). */
it('UC-JPAIN-03: janaContext.businessId vem da sessão e ignora ?business_id (Tier 0)', function () {
    $user = painelBootstrap();

    $this->get('/ia?business_id=999')->assertInertia(fn ($page) => $page
        ->where('janaContext.businessId', $user->business_id)
    );
});

/**
 * UC-JPAIN-04 — farol é do servidor.
 * Duas metades: o payload ENTREGA farol, e a regra NÃO voltou pro frontend.
 * (`FarolServerSideTest` cobre as fronteiras −5%/−15%; aqui é o contrato da tela.)
 */
it('UC-JPAIN-04: cada meta traz farol do servidor e o Index.tsx não recalcula', function () {
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

/** UC-JPAIN-05 — o empty state declara ausência (estado real de 100% dos tenants). */
it('UC-JPAIN-05: a copy do empty state do contrato está na tela', function () {
    $tsx = painelTsx();

    foreach (painelCopyDoContrato('painel-metas-vazio') as $copy) {
        expect($tsx)->toContain($copy);
    }

    expect($tsx)->toContain('data-contract="painel-metas-vazio"');
});

/** UC-JPAIN-06 — meta sem apuração não vira zero. */
it('UC-JPAIN-06: meta sem apuração declara "Aguardando apuração…" em vez de zero', function () {
    $tsx = painelTsx();

    foreach (painelCopyDoContrato('painel-meta-apurando') as $copy) {
        expect($tsx)->toContain($copy);
    }

    expect($tsx)->toContain('data-contract="painel-meta-apurando"');
});

/** UC-JPAIN-07 — sparkline sem série declara ausência. */
it('UC-JPAIN-07: sparkline sem série declara "Sem histórico" em vez de desenhar zero', function () {
    $tsx = painelTsx();

    foreach (painelCopyDoContrato('painel-meta-sem-historico') as $copy) {
        expect($tsx)->toContain($copy);
    }

    expect($tsx)->toContain('data-contract="painel-meta-sem-historico"');
});

/**
 * UC-JPAIN-08 — enquanto o cockpit não chega, a tela NÃO mostra zero.
 *
 * O `?? 0` do `JanaCockpit` FICA (é ele que impede o TypeError e mantém válida a
 * entrada na `DEFER_GUARD_ONLY_ALLOWLIST`); o que este caso trava é o RENDER:
 * existe um sinal de carregamento derivado de `coworkAggregates === undefined`, e
 * o KPI que depende dele não é pintado enquanto isso.
 *
 * ⚠️ Eram DOIS até 2026-08-31 (`Receita 30 dias` · `PIX hoje`). O `PIX hoje` saiu
 * do painel na paridade com o protótipo (UC-JPAIN-18) e a asserção dele saiu no
 * MESMO diff — `toMatch` cujo alvo não existe mais FALHA (é positivo, acusa), mas
 * a prosa que dizia "os dois" viraria mentira silenciosa. Sobrou 1 dos 3 KPIs
 * dependendo da prop deferida; os outros 2 vêm de `insightsAggregates` (eager).
 *
 * A asserção é de ARQUIVO porque o defeito é de render, e Pest não monta React.
 * Ela morde no que importa: apagar o `carregandoCockpit`, ou voltar a passar o
 * `<JanaKpiCard>` direto, derruba o caso. O par visual (screenshot 1280/1440) é o
 * portão F1.5 e vive fora daqui.
 */
it('UC-JPAIN-08: o cockpit declara carregando em vez de pintar zero', function () {
    $cockpit = file_get_contents(base_path('resources/js/Pages/Jana/_components/JanaCockpit.tsx'));

    // 1. o sinal existe e é `undefined` (ausência), não falsy — `?? 0` já virou 0
    expect($cockpit)->toContain('coworkAggregates === undefined');

    // 2. o KPI que depende da prop deferida troca de card enquanto carrega.
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
    //    Corrigido junto com o rótulo em UC-JPAIN-14 — comentário que afirma
    //    proveniência errada é instrução ativa pra próxima sessão (§5 2026-08-10).
    expect($cockpit)
        ->toMatch('/carregandoCockpit\s*\?\s*\(\s*<KpiCardSkeleton label="Receita 30 dias"/u');

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

/**
 * UC-JPAIN-09 — toda âncora declarada existe, e a ordem declarada é subsequência da
 * ordem de arquivo.
 *
 * SEM número no nome: ele dizia "as 5 âncoras" e virou mentira quando o contrato ganhou
 * a 6ª (`painel-plano`, 2026-08-28). O assert sempre foi dinâmico — itera `secoes` —, era
 * só o RÓTULO que estava congelado, e rótulo é promessa. O universo é o contrato.
 */
it('UC-JPAIN-09: toda âncora declarada existe e a ordem do contrato é respeitada', function () {
    $j   = json_decode(file_get_contents(base_path(PAINEL_CONTRATO)), true);
    $tsx = painelAlvoTexto();

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
 * UC-JPAIN-10 — "Configurar" abre drawer e não promete o que o servidor não cumpre.
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
it('UC-JPAIN-10: Configurar abre o drawer e não promete o que o servidor não cumpre', function () {
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
 * UC-JPAIN-11 — a meta abre NA PRÓPRIA TELA, e o drawer não projeta o futuro.
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
it('UC-JPAIN-11: a meta abre em drawer na própria tela, sem projetar o fechamento', function () {
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
 * UC-JPAIN-12 — a ação sugerida vira decisão registrada, e a prévia é do SERVIDOR.
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

/** UC-JPAIN-12 — o CTA deixou de ser decorativo e os rótulos casam com o backend. */
it('UC-JPAIN-12: o CTA abre o modal HITL e cada rótulo tem chave no AcaoHitlService', function () {
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

/** UC-JPAIN-12 — a prévia é gerada no servidor, e só pras chaves conhecidas. */
it('UC-JPAIN-12: a prévia vem do servidor com alcance, e ação desconhecida é 404', function () {
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
 * UC-JPAIN-12 — o que fica gravado é o recibo do SERVIDOR.
 *
 * O vetor que este caso fecha: se `previa` viesse do request, o cliente
 * reescreveria o que "foi aprovado" — e o ledger deixaria de ser recibo.
 */
it('UC-JPAIN-12: aprovar grava a prévia do servidor e ignora o texto do cliente', function () {
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

/** UC-JPAIN-12 — Tier 0: o registro nasce escopado pela SESSÃO (ADR 0093). */
it('UC-JPAIN-12: a aprovação nasce com o business_id da sessão, nunca do request', function () {
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

// ── UC-JPAIN-13 — Churn ouro ───────────────────────────────────────────

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
 * UC-JPAIN-13 — o recorte é RELATIVO (maior LTV entre os parados), e o
 * controle negativo é o que prova: quem comprou ontem NÃO entra, mesmo valendo
 * muito mais. Sem esse caso, um bug que ignorasse a data passaria verde — o card
 * viraria "top clientes" com outro título.
 *
 * ⚠️ O CT 100 PERSISTE entre runs (§5 2026-07-28), então cada caso limpa o que
 * criou e todas as asserções são marcadas por `uniqid()` — nunca por contagem
 * absoluta, que quebraria com resíduo de outra execução.
 */
it('UC-JPAIN-13: churn ouro pega o parado >90d e IGNORA quem comprou ontem', function () {
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
 * UC-JPAIN-13 — o drill drawer promete um método por análise; todos têm de
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
it('UC-JPAIN-13: todo `metodo` prometido pelo JanaDrillDrawer existe no aggregator', function () {
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

// ── UC-JPAIN-14 — a janela do KPI ──────────────────────────────────────

/**
 * UC-JPAIN-14 — o rótulo declara a janela REAL do dado.
 *
 * Asserção de ARQUIVO e ESTRUTURAL, pelos mesmos dois motivos dos UC-08/10/12: o
 * Pest não monta React, e a copy É o artefato. A forma escolhida é `label="…"`
 * (com o atributo), nunca a prosa solta — `not->toContain('Receita mês')`
 * FALHARIA, porque a frase está viva no comentário que registra o que saiu. É o
 * falso-positivo do §5 2026-07-26, que já mordeu esta suíte duas vezes.
 */
it('UC-JPAIN-14: o KPI da sparkline se chama pela janela que tem, não "mês"', function () {
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
 * UC-JPAIN-14 — e a prova de que o `|| faturadoHoje` era INALCANÇÁVEL.
 *
 * Este caso existe porque a remoção de um fallback é exatamente o tipo de
 * mudança que parece perda de robustez. Ele demonstra a contenção: as duas
 * consultas têm filtros idênticos e a janela da série vai até o fim de hoje,
 * então toda venda de hoje JÁ está dentro da série. Se alguém encurtar a janela
 * (ex.: `endOfDay` → `startOfDay`) esta asserção quebra, e é o que se quer.
 */
it('UC-JPAIN-14: a série tem 30 dias e CONTÉM o faturamento de hoje', function () {
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
 * UC-JPAIN-15 — o card diz o PESO do vencido, não só a contagem.
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
it('UC-JPAIN-15: o vencido declara seu peso, e nunca arredonda pra zero', function () {
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

// ─────────────────────────────────────────────────────────────────────────────
// UC-JPAIN-16 — botão clicável que não faz nada
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Extrai os `<Button>` SEM comportamento de um fonte `.tsx`, por RÓTULO.
 *
 * Recebe CONTEÚDO, não path, de propósito: o bite-test abaixo exercita ESTA
 * função, não uma cópia paralela — selftest que roda a cópia fica verde enquanto
 * o caminho real regride (§5 2026-08-14).
 *
 * ## Por que RÓTULO e não `arquivo:linha`
 *
 * Ref de linha apodrece no primeiro refactor (§5 2026-07-26), e este parse
 * REMOVE comentários antes de contar — então a linha daqui nem casa com a do
 * arquivo. Medido em 2026-08-27: o mesmo botão "Ouvir áudio" é a linha 455 no
 * arquivo e 373 depois do strip. O rótulo é o que o usuário vê e é o que
 * identifica o botão de forma estável.
 *
 * ## Por que ESTRUTURAL e não prosa ("em breve")
 *
 * `not->toContain('em breve')` FALHARIA: a frase vive nos comentários que
 * REGISTRAM as decisões (4 ocorrências medidas em `Index.tsx`/`JanaCockpit.tsx`),
 * e proibir a prosa proibiria registrar a decisão — o falso-positivo que já
 * mordeu os UC-10, UC-11 e UC-12 na escrita. O que morde aqui é a FORMA: botão
 * sem `onClick`, sem `disabled`, sem `type=submit`, sem `asChild`, sem `href`,
 * sem spread — e que também não tem wrapper-pai (`<Link>` e afins) dando o
 * comportamento por ele.
 *
 * Medição que precedeu o caso (2026-08-27, ANTES de ligar — regra "LIGUE A
 * MÁQUINA" item 4): o predicado CRU ("sem onClick") dava 14 de 35 botões na
 * Jana — 85% falso-positivo, quase tudo `<Link href><Button>`. Com o filtro de
 * wrapper-pai: 5 de 35, e os 5 são reais. No repo inteiro (687 botões), o mesmo
 * predicado dá 15 — e por isso este caso NÃO é ampliado pra fora do Painel sem
 * medir o FP daquele corpus.
 */
function painelBotoesMudos(string $src): array
{
    // Wrappers que DÃO comportamento ao filho — o pai é quem navega/abre.
    $wrapper = '~<(Link|SheetClose|DialogTrigger|DialogClose|PopoverTrigger|TooltipTrigger|DropdownMenuTrigger|AlertDialogTrigger|AlertDialogAction|AlertDialogCancel|a)\b[^>]*>\s*$~';
    $vivo    = '~onClick|disabled|type\s*=\s*.?submit|asChild|href|\{\s*\.\.\.~';

    // Comentário não chega ao cliente. Removê-lo ANTES é o que separa este caso
    // do falso-positivo de prosa descrito no docblock.
    $src = preg_replace('~\{/\*.*?\*/\}~s', '', $src);
    $src = preg_replace('~/\*.*?\*/~s', '', $src);
    $src = preg_replace('~^\s*//.*$~m', '', $src);

    $mudos = [];
    $pos   = 0;
    $n     = strlen($src);

    while (($i = strpos($src, '<Button', $pos)) !== false) {
        $pos   = $i + 7;
        $prof  = 0;
        $attrs = '';
        $j     = $pos;

        for (; $j < $n; $j++) {
            $c = $src[$j];
            if ($c === '{') {
                $prof++;
            } elseif ($c === '}') {
                $prof--;
            } elseif ($c === '>' && $prof === 0) {
                break;
            }
            $attrs .= $c;
        }

        if (preg_match($vivo, $attrs) === 1) {
            continue;
        }
        if (preg_match($wrapper, substr($src, max(0, $i - 300), min($i, 300))) === 1) {
            continue;
        }

        $fim    = strpos($src, '</Button>', $j);
        $corpo  = $fim === false ? '' : substr($src, $j + 1, $fim - $j - 1);
        $corpo  = preg_replace('~<[^>]*>~', ' ', $corpo);   // <Icon />
        $corpo  = preg_replace('~\{[^}]*\}~', ' ', $corpo); // {expressao}
        $rotulo = trim(preg_replace('~\s+~', ' ', $corpo));

        $mudos[] = $rotulo === '' ? '(sem rotulo)' : $rotulo;
    }

    sort($mudos);

    return $mudos;
}

/** Os DOIS arquivos que desenham o Painel `/ia` — os mesmos que o `paths:` da lane acorda. */
function painelBotoesMudosDaTela(): array
{
    $mudos = [];

    foreach ([PAINEL_TSX, 'resources/js/Pages/Jana/_components/JanaCockpit.tsx'] as $rel) {
        $mudos = array_merge($mudos, painelBotoesMudos(file_get_contents(base_path($rel))));
    }

    sort($mudos);

    return $mudos;
}

/**
 * UC-JPAIN-16 — nenhum botão NOVO do Painel nasce clicável sem fazer nada.
 *
 * ## O que este caso é, e o que ele NÃO é
 *
 * Ele **não decide** o destino dos 5 botões abaixo — isso é [W], e o próprio
 * `jana-painel.contract.json` já diz por quê: *"Some, vira `disabled` com o
 * motivo, ou entrega? Enquanto não decidido, NÃO entra no contrato: pinar uma
 * promessa é congelá-la"*. Consertar um sem decisão seria escolher no lugar dele.
 *
 * O que ele faz é **forward-only** (ADR 0275): trava o conjunto CONHECIDO e
 * derruba o **sexto**. É a diferença entre uma dívida declarada e uma dívida
 * invisível — e a invisibilidade é o que deixou os 3 chips crescerem depois que
 * o UC-JPAIN-12 consertou os CTAs vizinhos, no mesmo arquivo, um a um.
 *
 * ## Por que a lista, e não uma contagem
 *
 * `toBe(5)` trancaria nos dois sentidos: consertar um derrubaria o caso
 * (predicado absoluto onde cabia delta — §5 2026-08-24), e trocar um pelo outro
 * passaria em silêncio. Com a lista, consertar um é REMOVER a linha dele no
 * mesmo PR — o teste conta a dívida encolhendo, que é o comportamento desejado.
 */
it('UC-JPAIN-16: nenhum botão novo do Painel nasce clicável sem fazer nada', function () {
    // ── BITE-TEST — sem isto, um regex quebrado ficaria verde pra sempre ──────
    $ruim = '<Button variant="ghost">Exportar tudo</Button>';
    $bom  = '<Button variant="ghost" onClick={() => f()}>Exportar tudo</Button>';
    $pai  = '<Link href="/x">' . "\n" . '  <Button variant="ghost">Abrir</Button>' . "\n" . '</Link>';
    $cmt  = '{/* um botao "em breve" citado em COMENTARIO nao e um botao */}';

    expect(painelBotoesMudos($ruim))->toBe(['Exportar tudo']);  // MORDE
    expect(painelBotoesMudos($bom))->toBe([]);                  // libera onClick
    expect(painelBotoesMudos($pai))->toBe([]);                  // libera wrapper-pai
    expect(painelBotoesMudos($cmt))->toBe([]);                  // ignora comentario

    // ── CORPUS REAL ──────────────────────────────────────────────────────────
    // Cada linha é uma dívida DECLARADA, com dono. Consertar = apagar a linha
    // no MESMO PR. Acrescentar linha aqui exige a razão escrita ao lado.
    $conhecidos = [
        // Catalogados no contrato de tela e no inventário — decisão [W] ABERTA
        // (`prototipo-ui/contrato/jana-painel.contract.json` · `Index-visual-comparison.md`).
        'Exportar',                               // Index.tsx — mudo: sem rota e sem handler.
        //                                          O "(em breve)" saiu do title em 2026-08-31
        //                                          (a âncora não promete); o botão NÃO mudou.
        'Ouvir áudio',                            // JanaCockpit — title="(em breve — TTS V2)"

        // Os 3 chips do rodapé do brief. O inventário os registra como
        // "🟡 botão morto" e a ref de linha dele (`479-500`) já apodreceu — os
        // chips estão em 553/557/565. Estes NÃO prometem nada: clicam e nada
        // acontece, sem explicação — o mesmo estado em que o "Exportar" caiu
        // quando perdeu o "(em breve)" sem ganhar handler. O único que ainda
        // promete data é o "Ouvir áudio" (title="(em breve — TTS V2)").
        'Disparar régua WhatsApp pros atrasados', // JanaCockpit
        'Investigar queda ticket médio',          // JanaCockpit
        'Ver top devedores',                      // JanaCockpit
    ];

    sort($conhecidos);

    expect(painelBotoesMudosDaTela())->toBe($conhecidos);
});

/**
 * Extrai, na ORDEM de arquivo, os rótulos dos `<JanaKpiCard>` que vivem dentro do
 * `<KpiGrid>` do cockpit.
 *
 * Por que o recorte é o bloco, e não o arquivo: `label="Receita 30 dias"` aparece
 * DUAS vezes (o card e o `<KpiCardSkeleton>` do ramo de carregamento), e o
 * `Sells/Index.tsx` tem KPIs próprios. Contar no arquivo inteiro mediria outra
 * coisa e daria um número plausível — a armadilha do §5 2026-08-01.
 *
 * ⚠️ O NOME mudou em 2026-09-03 (Onda 2 da paridade · UC-JPAIN-20): o card deixou de
 * ser o `KpiCard` shared (anatomia PT-04) e passou a ser a RÉPLICA do `.jc-kpi` da
 * âncora, `_components/JanaKpiCard.tsx` (ADR 0388 §D-1 · precedência de FORMA da ADR
 * UI-0029). Se este regex não tivesse acompanhado, ele voltaria `[]` e o caso ficaria
 * VERDE por não achar nada — LC-11 na forma silenciosa.
 *
 * `<JanaKpiCard\s` não casa `<KpiCardSkeleton` (outro nome) porque exige o espaço
 * logo após o nome.
 */
function painelKpisDoGrid(string $src): array
{
    if (! preg_match('/<KpiGrid\b.*?<\/KpiGrid>/us', $src, $bloco)) {
        return [];
    }

    preg_match_all('/<JanaKpiCard\s+label="([^"]+)"/u', $bloco[0], $m);

    return $m[1];
}

/**
 * UC-JPAIN-18 — o Painel mostra os 3 KPIs do protótipo, e o 4º saiu sem levar o dado.
 *
 * Medido em 2026-08-31 na âncora (`node prototipo-ui/ancora.mjs Jana/Index` →
 * `prototipo-ui/cowork/jana-merge.jsx`): a `jc-kpis` dela renderiza `data.kpis.map`,
 * e esse array publica 3 entradas — `Receita mês` · `A receber vencido` ·
 * `Ticket médio`. A tela viva tinha 4 (o extra era `PIX hoje`). Decisão [W]: o 4º sai.
 *
 * ⚠️ O que este caso NÃO trava, de propósito: o RÓTULO do 1º card. Ele é
 * `Receita 30 dias` aqui e `Receita mês` na âncora, e a divergência é DELIBERADA —
 * o dado são 30 dias deslizantes, e o UC-JPAIN-14 corrigiu a palavra com esse
 * fundamento. Copiar a copy do protótipo reintroduziria bug conhecido; aqui é o
 * protótipo que está atrás.
 *
 * A 2ª asserção é negativa, e vem acompanhada da prova de que ainda tem sentido:
 * `pixHoje` CONTINUA no arquivo com consumidores vivos (a ação sugerida e a linha do
 * brief). Sem essa prova, o dia em que alguém apagasse o `pixHoje` inteiro deixaria o
 * negativo verde por ausência de alvo — LC-11 na forma mais silenciosa, que é
 * exatamente o que o UC-JPAIN-08 deste arquivo já ensina no controle negativo dele.
 */
it('UC-JPAIN-18: o grid tem os 3 KPIs da âncora e o PIX saiu como CARD, não como dado', function () {
    $cockpit = acaoCockpitTsx();

    // ── BITE-TEST do extrator: ele mede o que diz medir? ─────────────────────
    // Controle positivo E negativo antes de confiar no número real (§5 2026-08-01).
    $fixtureBoa  = '<KpiGrid cols={4}><JanaKpiCard label="A" /><JanaKpiCard label="B" /></KpiGrid>';
    $fixtureSkel = '<KpiGrid cols={4}><KpiCardSkeleton label="X" /><JanaKpiCard label="A" /></KpiGrid>';
    $fixtureFora = '<JanaKpiCard label="Z" /><KpiGrid cols={4}><JanaKpiCard label="A" /></KpiGrid>';

    expect(painelKpisDoGrid($fixtureBoa))->toBe(['A', 'B']);   // conta os cards
    expect(painelKpisDoGrid($fixtureSkel))->toBe(['A']);       // ignora o skeleton
    expect(painelKpisDoGrid($fixtureFora))->toBe(['A']);       // ignora fora do grid
    expect(painelKpisDoGrid('<div/>'))->toBe([]);              // sem grid, sem número

    // ── 1. o conjunto e a ORDEM ──────────────────────────────────────────────
    // Ordem importa: é ela que casa 1:1 com a da âncora depois da saída do 4º.
    expect(painelKpisDoGrid($cockpit))->toBe([
        'Receita 30 dias',
        'A receber vencido',
        'Ticket médio',
    ]);

    // ── 2. o grid declara as 4 colunas da ÂNCORA, com o gap dela ─────────────
    // A `jc-kpis` é `grid-template-columns: repeat(4, 1fr); gap: 10px`, e os 3 cards
    // ocupam 3/4 — o vão à direita é do DESENHO, não sobra de card removido. Era
    // `cols={3}` até 2026-09-03, quando a Onda 2 mediu a âncora (`gap-2.5` = 10px).
    expect($cockpit)->toContain('<KpiGrid cols={4} className="gap-2.5">');

    // ── 2b. e o card é a RÉPLICA, não o shared PT-04 ─────────────────────────
    // Sem este par, trocar `JanaKpiCard` de volta por `KpiCard` deixaria o extrator
    // devolvendo `[]` — um caso verde por ausência de alvo, que é o que o docblock
    // do extrator acabou de avisar.
    expect($cockpit)
        ->toContain("import JanaKpiCard from './JanaKpiCard';")
        ->not->toContain("from '@/Components/shared/KpiCard'");

    // ── 3. o PIX saiu como CARD ──────────────────────────────────────────────
    expect($cockpit)
        ->not->toContain('label="PIX hoje"')
        ->not->toMatch('/<KpiCardSkeleton label="PIX hoje"/u');

    // ── 4. …e NÃO como dado. Prova de que o negativo acima ainda tem alvo. ───
    // `pixHojeTotal` segue chegando na prop deferida e `pixHoje` segue sendo lido:
    // a ação "PIX adoção em N% — manter" e a linha do brief dependem dele. Se um dia
    // isto cair, o item 3 vira decoração — e aí a remoção deixou de ser "tirar o card"
    // e virou "tirar a capacidade", que é outra decisão e precisa de [W].
    expect($cockpit)
        ->toContain('const pixHoje = coworkAggregates?.pixHojeTotal ?? 0;')
        ->toContain('custo zero vs maquininha');
});
