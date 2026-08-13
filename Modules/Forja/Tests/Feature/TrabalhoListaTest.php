<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Forja\Entities\McpActor;
use Modules\Forja\Services\TrabalhoService;
use Modules\Jana\Entities\Mcp\McpTask;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * Trabalho — a lista única que funde os três backlogs (US-FORJA-006).
 *
 * O que estes casos defendem, e por que cada um existe:
 *
 *   1. A lista abre com TODAS as tasks, não só as do projeto FORJA. É a decisão
 *      [W] de 2026-08-08 e o inverso do `ForjaBacklogService`, que devolvia `[]`
 *      sem `project_id` — se alguém "consertar" isso de volta, a fusão perde o
 *      sentido (viraria o quarto backlog com o mesmo recorte dos outros dois).
 *   2. `sort` inválido cai no default. Sem a allowlist, o valor livre vira um
 *      `FIELD(...)` sem correspondência e a ordem sai ALEATÓRIA sem erro nenhum —
 *      falha silenciosa é pior que 500.
 *   3. `tasks` e `kpis` saem da MESMA query (memoização). Duas chamadas seguidas
 *      têm que devolver o mesmo objeto, senão a render dobra a consulta.
 *
 * ⚠️ Estes casos rodam SEM HTTP de propósito: exercitam o service direto. A
 * cobertura de rota/permissão da tela é do `ForjaRoutesSmokeTest` (UC-FORJA-01/07),
 * que já cobre o padrão do hub; duplicar aqui seria régua paralela.
 *
 * NUNCA biz=4 (ROTA LIVRE prod) — ADR 0101. `mcp_tasks` é repo-wide (ADR 0093),
 * então estes casos não precisam de tenant: precisam de schema.
 *
 * @see Modules\Forja\Services\TrabalhoService
 * @see Modules/Forja/Resources/js/Pages/Forja/Trabalho/Index.charter.md
 */

function trabalhoExigeSchema(): void
{
    if (! Schema::hasTable('mcp_tasks')) {
        test()->markTestSkipped('Schema ausente (mcp_tasks) — rode com DB_CONNECTION=mysql.');
    }
    // `FIELD(...)` da ordenação é MySQL-only; em sqlite a query nem monta.
    if (DB::connection()->getDriverName() === 'sqlite') {
        test()->markTestSkipped('A ordenação usa FIELD() — MySQL-only. Lane sqlite pula.');
    }
}

/** Fixture com projeto opcional — o ponto dos casos é justamente o escopo. */
function trabalhoTask(string $sufixo, ?int $projectId, string $status = 'todo', string $prio = 'p2'): McpTask
{
    trabalhoExigeSchema();

    $t = new McpTask();
    $t->task_id    = 'TRAB-TEST-'.$sufixo;
    $t->identifier = 'TRAB-TEST-'.$sufixo;
    $t->title      = 'Fixture trabalho '.$sufixo;
    $t->module     = 'Forja';
    $t->status     = $status;
    $t->type       = 'story';
    $t->priority   = $prio;
    $t->project_id = $projectId;
    $t->save();

    return $t;
}

it('UC-TRAB-01 — a lista abre com TODAS as tasks, não só as de um projeto', function () {
    trabalhoExigeSchema();

    $semProjeto = trabalhoTask('SEMPROJ', null);
    $comProjeto = trabalhoTask('COMPROJ', 999999);   // id que não existe em mcp_projects

    $ids = collect(app(TrabalhoService::class)->build(TrabalhoService::filtrosPadrao())['tasks'])
        ->pluck('task_id')->all();

    // O coração do caso: as DUAS aparecem. O `ForjaBacklogService` devolvia []
    // sem project_id; se essa regra voltar, este caso cai.
    expect($ids)->toContain($semProjeto->task_id);
    expect($ids)->toContain($comProjeto->task_id);
});

it('UC-TRAB-02 — filtrar por frente RESTRINGE (o filtro existe, só não é oferecido na UI)', function () {
    trabalhoExigeSchema();

    $fora  = trabalhoTask('FORA', null);
    $dentro = trabalhoTask('DENTRO', 999999);

    $filtros = array_merge(TrabalhoService::filtrosPadrao(), ['frente' => 999999]);
    $ids = collect(app(TrabalhoService::class)->build($filtros)['tasks'])->pluck('task_id')->all();

    expect($ids)->toContain($dentro->task_id);
    expect($ids)->not->toContain($fora->task_id);
});

it('UC-TRAB-03 — sort fora da allowlist não é aceito pelo contrato', function () {
    // A allowlist é o contrato: `sort` livre viraria FIELD(...) sem correspondência
    // e a ordem sairia aleatória, sem erro. Quem barra é o controller (in_array);
    // aqui se prova que a lista de válidos é fechada e conhecida.
    expect(TrabalhoService::SORTS)->toBe(['rank', 'recent', 'due', 'title', 'id', 'execucao']);
    expect(TrabalhoService::SORTS)->not->toContain('; DROP TABLE');
    expect(TrabalhoService::filtrosPadrao()['sort'])->toBe('rank');
});

it('UC-TRAB-04 — tasks e kpis vêm da MESMA query (memoizado, não dobra consulta)', function () {
    trabalhoExigeSchema();

    trabalhoTask('MEMO1', null, 'doing', 'p0');
    trabalhoTask('MEMO2', null, 'blocked');

    $svc = app(TrabalhoService::class);
    $f = TrabalhoService::filtrosPadrao();

    $a = $svc->build($f);
    $b = $svc->build($f);

    // Mesma instância de Collection = veio do cache; se alguém quebrar a
    // memoização, a render passa a fazer 2 queries idênticas em silêncio.
    expect($a['tasks'])->toBe($b['tasks']);

    // E os KPIs contam o que a lista tem — não um segundo SELECT.
    expect($a['kpis']['fazendo'])->toBeGreaterThanOrEqual(1);
    expect($a['kpis']['bloqueadas'])->toBeGreaterThanOrEqual(1);
    expect($a['kpis']['p0'])->toBeGreaterThanOrEqual(1);
});

it('UC-TRAB-05 — cancelled some por default, mas volta com status=all', function () {
    trabalhoExigeSchema();

    $cancelada = trabalhoTask('CANC', null, 'cancelled');
    $svc = app(TrabalhoService::class);

    $padrao = collect($svc->build(TrabalhoService::filtrosPadrao())['tasks'])->pluck('task_id')->all();
    expect($padrao)->not->toContain($cancelada->task_id);

    $todos = collect($svc->build(array_merge(TrabalhoService::filtrosPadrao(), ['status' => 'all']))['tasks'])
        ->pluck('task_id')->all();
    expect($todos)->toContain($cancelada->task_id);
});

it('UC-TRAB-06 — a task carrega os campos das TRÊS origens que foram fundidas', function () {
    trabalhoExigeSchema();

    $t = trabalhoTask('CAMPOS', null, 'todo', 'p1');
    $t->custom_fields = ['forja_fase' => 'F3', 'forja_papel' => 'CC'];
    $t->save();

    $item = collect(app(TrabalhoService::class)->build(TrabalhoService::filtrosPadrao())['tasks'])
        ->firstWhere('task_id', $t->task_id);

    expect($item)->not->toBeNull();

    // da NATIVA
    expect($item)->toHaveKeys(['display_id', 'priority', 'status', 'is_blocked', 'is_overdue']);
    // do COCKPIT (projeção sobre custom_fields)
    expect($item['forja_fase'])->toBe('F3');
    expect($item['forja_papel'])->toBe('CC');
    // do TEAM-MCP (a lista mistura projetos, então a frente importa)
    expect($item)->toHaveKey('frente_id');
});

it('UC-TRAB-07 — as fases do quadro batem com o dono do pipeline (backend)', function () {
    // O `TrabalhoQuadro.tsx` ESPELHA as fases do `ForjaQuadroService` — é a única
    // forma de o front desenhar as colunas sem um roundtrip só pra isso. Espelho
    // sem trava vira duas declarações do pipeline que divergem na 1ª mudança.
    // Este caso cruza as DUAS fontes (PHP × TSX), como o UC-FORJA-14 faz com a nav.
    $php = file_get_contents(base_path('Modules/Forja/Services/ForjaQuadroService.php'));
    preg_match_all("/'key'\s*=>\s*'([^']+)'/", $php, $mPhp);

    $tsx = file_get_contents(base_path('Modules/Forja/Resources/js/Pages/Forja/Trabalho/_components/TrabalhoQuadro.tsx'));
    $ini = strpos($tsx, 'const FASES');
    expect($ini)->not->toBeFalse('FASES sumiu do TrabalhoQuadro — o espelho mudou de forma.');
    $bloco = substr($tsx, $ini, strpos($tsx, '];', $ini) - $ini);
    preg_match_all("/key:\s*'([^']+)'/", $bloco, $mTsx);

    // Guarda anti-falso-verde: dois vazios seriam "iguais".
    expect(count($mPhp[1] ?? []))->toBeGreaterThan(0, 'Nenhuma fase extraída do ForjaQuadroService.');
    expect(count($mTsx[1] ?? []))->toBeGreaterThan(0, 'Nenhuma fase extraída do TrabalhoQuadro.');

    expect($mTsx[1])->toBe($mPhp[1],
        "As fases do pipeline divergiram.
".
        '  backend (ForjaQuadroService): '.implode(' · ', $mPhp[1])."
".
        '  front   (TrabalhoQuadro)    : '.implode(' · ', $mTsx[1])."
".
        'Mudou a fase num lado? Mude no outro — senão o board desenha coluna que o dado não preenche.'
    );
});

it('UC-TRAB-08 — visao e eixo têm default e allowlist', function () {
    // Mesma razão do `sort`: valor livre viraria estado desconhecido no front,
    // que renderiza vazio SEM erro. O default é o que a tela abre.
    $f = TrabalhoService::filtrosPadrao();
    expect($f['visao'])->toBe('lista');
    expect($f['eixo'])->toBe('execucao');
});

it('UC-TRAB-09 — trocar de visão NÃO refaz a query (mesma chave de cache)', function () {
    trabalhoExigeSchema();
    trabalhoTask('VISAO', null);

    $svc = app(TrabalhoService::class);
    $lista  = $svc->build(array_merge(TrabalhoService::filtrosPadrao(), ['visao' => 'lista']));
    $quadro = $svc->build(array_merge(TrabalhoService::filtrosPadrao(), ['visao' => 'quadro', 'eixo' => 'pipeline']));

    // Mesma instância de Collection = veio do cache. Se `visao`/`eixo` entrarem na
    // chave, alternar a vista refaz a consulta inteira por nada.
    expect($lista['tasks'])->toBe($quadro['tasks']);
});

it('UC-TRAB-10 — os filtros do atalho Gantt são de fato LIDOS pelo destino', function () {
    // O botão "Gantt" leva os filtros de `/forja/trabalho` para
    // `/forja/roadmap-gantt`. Se o destino parar de ler um deles, o link segue
    // funcionando e o parâmetro é IGNORADO EM SILÊNCIO — a pessoa vê a lista
    // "não filtrar" e não tem como saber por quê. É o pior tipo de defeito:
    // não dá erro, não dá 500, só mente.
    //
    // Este caso cruza a constante com o controller de lá. Note o que ele NÃO
    // aceita: `status` aparece no PAYLOAD DE SAÍDA do Gantt (ele serializa o
    // campo), mas não é filtro de ENTRADA — confundir os dois foi o erro que
    // esta trava existe pra impedir.
    $ganttSrc = file_get_contents(base_path('Modules/Forja/Http/Controllers/RoadmapGanttController.php'));

    expect(TrabalhoService::FILTROS_ATALHO_GANTT)->not->toBeEmpty(
        'Lista vazia passaria neste caso sem provar nada.'
    );

    foreach (TrabalhoService::FILTROS_ATALHO_GANTT as $filtro) {
        // ⚠️ `toContain` é VARIÁDICO no Pest — passar a mensagem como 2º
        // argumento faz ele procurar a FRASE no haystack, e o assert falha
        // sempre (§5 proibicoes, 2026-07-28). Por isso a mensagem vai no
        // `toBeTrue`, que a aceita de verdade.
        expect(str_contains($ganttSrc, "\$request->get('{$filtro}')"))->toBeTrue(
            "O atalho manda `{$filtro}` mas o RoadmapGanttController não lê esse parâmetro — ".
            'o link levaria filtro que o destino ignora em silêncio. Tire da constante ou leia no destino.'
        );
    }

    // Controle negativo: `status` NÃO pode entrar na lista enquanto o Gantt não
    // o ler. Sem este assert, alguém adicionaria "porque o Gantt tem status".
    expect(TrabalhoService::FILTROS_ATALHO_GANTT)->not->toContain('status');
});

it('UC-TRAB-11 — agentes() lista SÓ ator ai_agent ativo, em lowercase', function () {
    trabalhoExigeSchema();
    if (! Schema::hasTable('mcp_actors')) {
        test()->markTestSkipped('Schema ausente (mcp_actors).');
    }

    // Esta lista alimenta o <ActorSeal>, que decide AGENTE vs HUMANO no card.
    // É allowlist, não heurística de nome: quem não está aqui é humano. Logo os
    // três erros possíveis são (a) deixar humano entrar, (b) deixar revogado
    // entrar, (c) errar o case e o front nunca casar o owner.
    // `mcp_actors` exige os 5 JSON de capability + display_name (NOT NULL sem
    // default) — coluna JSON no MySQL 8 carrega CHECK implícito `json_valid`,
    // então omitir não dá "null", dá violação de constraint.
    $ator = fn (string $slug, string $type, ?string $revogadoEm = null) => McpActor::create([
        'slug'            => $slug,
        'type'            => $type,
        'trust_level'     => 'L0',
        'display_name'    => $slug,
        'modules_write'   => [],
        'modules_read'    => [],
        'modules_blocked' => [],
        'skills_required' => [],
        'actions_blocked' => [],
        'revoked_at'      => $revogadoEm,
    ]);

    $ativo    = $ator('AgenteFixtura', 'ai_agent');
    $revogado = $ator('agente-revogado-fixtura', 'ai_agent', now()->toDateTimeString());
    $humano   = $ator('humano-fixtura', 'human');

    $lista = app(TrabalhoService::class)->agentes();

    // (c) lowercase — o front compara `agents.includes(owner.toLowerCase())`;
    //     sem normalizar aqui, "AgenteFixtura" nunca casaria e o selo mentiria
    //     dizendo "humano" pra um agente.
    expect($lista)->toContain('agentefixtura');
    expect($lista)->not->toContain('AgenteFixtura');

    // (b) revogado fica fora — ator desligado não deve seguir carimbando cards.
    expect($lista)->not->toContain($revogado->slug);

    // (a) humano nunca entra — se entrasse, o selo chamaria pessoa de robô.
    expect($lista)->not->toContain($humano->slug);
});

it('UC-TRAB-12 — o slug `claude` está no Mesh como AGENTE (o selo lê dado, não palpite)', function () {
    trabalhoExigeSchema();
    if (! Schema::hasTable('mcp_actors')) {
        test()->markTestSkipped('Schema ausente (mcp_actors).');
    }

    // Medido em produção 2026-08-10: `mcp_tasks.owner` usa `claude`, mas o Mesh
    // só tinha `claude-code-wagner-laptop` (o ator-com-token). Resultado: as 8
    // tasks do claude apareciam como HUMANO, em /forja/trabalho e em
    // /team-mcp/tasks (383 selos, 100% human). O selo nunca distinguiu nada.
    //
    // A migration 2026_08_10_120000 registra o fato. Este caso trava a volta:
    // se alguém remover o ator, o selo passa a mentir de novo — em silêncio,
    // porque `ActorSeal` cai em "humano" por default e não dá erro nenhum.
    //
    // ⚠️ O que NÃO se testa aqui: `startsWith('claude')`. A regra é allowlist de
    // dado; heurística de nome erra e mente com confiança (Non-Goal do charter).
    expect(app(TrabalhoService::class)->agentes())->toContain('claude');
});
