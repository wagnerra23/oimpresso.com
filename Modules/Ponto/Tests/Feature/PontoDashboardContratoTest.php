<?php

declare(strict_types=1);

use App\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Modules\Ponto\Entities\ApuracaoDia;
use Modules\Ponto\Entities\Colaborador;
use Modules\Ponto\Entities\Intercorrencia;
use Modules\Ponto\Tests\Feature\PontoTestCase;

uses(PontoTestCase::class);

/**
 * Contrato do PAINEL do Ponto (`/ponto` — `Ponto/Dashboard/Index`).
 *
 * Cada teste cita o UC no TÍTULO do `it()` (G-2 do casos-gate, ADR 0264):
 *   resources/js/Pages/Ponto/Dashboard/Index.casos.md -> UC-PAINEL-01..06
 *
 * ── De onde os UC derivam (ordem de fonte, how-trabalhar.md) ────────────────
 * Não há SDD do Painel (o SDD do módulo cobre espelho e jornada). A âncora de
 * contrato aqui é, nesta ordem:
 *   1. `prototipo-ui/contrato/ponto-painel.contract.json` — copy + ordem literais.
 *      É a fonte de VERDADE dos casos 01, 03 e 06: o teste LÊ o JSON e afirma
 *      contra ele, em vez de repetir as strings aqui. Assim o assert deriva do
 *      contrato, nunca do `.tsx` (teste tautológico — proibicoes.md §5 2026-06-05).
 *   2. `Index.charter.md` §Non-Goals / §Anti-hooks / §Automation hooks — 04 e 05.
 *   3. ADR 0093 (multi-tenant Tier 0) — caso 02.
 *
 * ── Por que Pest `it()` e não classe PHPUnit ────────────────────────────────
 * O `casos-results-collect.mjs` (manifesto G-7) lê o UC-id do atributo `name` do
 * `<testcase>`. Método PHP não aceita hífen, então UC em método nunca casa o
 * regex canônico (`scripts/lib/uc-regex.mjs`) e o teste nasce verde valendo 0.
 * É por isso que o UC-PAINEL-05 mora AQUI e não como método novo no
 * `DashboardDeferredContractTest` (classe): lá ele seria invisível ao manifesto.
 * Aquele arquivo segue dono do `Inertia::defer` e do wrap `<Deferred>`; este
 * cobre a LISTA do polling, que nenhum teste afirmava.
 *
 * ── Tier 0 ─────────────────────────────────────────────────────────────────
 * O "outro" empregador é o fictício 99 (`PontoTestCase::garantirBizAlheio`) —
 * NUNCA biz=4 (ROTA LIVRE, cliente real · ADR 0101). Sem `RefreshDatabase`: a
 * lane `ponto-pest` proíbe (dropa o schema e limpa o seed biz=1).
 *
 * ── [V0] ───────────────────────────────────────────────────────────────────
 * `he_mes_minutos` é minuto de jornada, logo valor (REGRA MESTRE de
 * proibicoes.md). Os casos aqui provam ISOLAMENTO do agregado (o número do meu
 * empregador não se mexe quando nasce dado alheio), NUNCA o valor apurado —
 * assert sobre o número exigiria dupla confirmação + antes->depois + [W].
 *
 * ── Limite MEDIDO desta suíte (não é omissão; é fixture impossível) ─────────
 * `presentes_agora` e `atividade_recente` saem de `ponto_marcacoes`, que tem
 * `trg_ponto_marcacoes_no_delete` e `trg_ponto_marcacoes_no_update` SEM escape
 * (Portaria 671/2021 · database/schema/mysql-schema.sql linhas 7420 e 7441).
 * Fixture de marcação é IRREVERSÍVEL, e o CT 100 roda contra base persistente —
 * o lixo se acumularia a cada run. Por isso o UC-PAINEL-02 exerce isolamento
 * sobre os agregados limpáveis (colaborador · apuração · intercorrência) e a
 * perna presença/feed está registrada como `[BACKLOG]` no casos.md, com a razão.
 *
 * Contrato: resources/js/Pages/Ponto/Dashboard/Index.casos.md
 *
 * @see \Modules\Ponto\Http\Controllers\DashboardController::index
 */

const PAINEL_MARCADOR = 'SDD-PAINEL-CONTRATO';
const PAINEL_PAGE = 'resources/js/Pages/Ponto/Dashboard/Index.tsx';
const PAINEL_CONTRATO = 'prototipo-ui/contrato/ponto-painel.contract.json';

/** Guard de ambiente — schema do Ponto presente. Skip gracioso e VISÍVEL. */
function painelPrecisaDe(array $tabelas): void
{
    foreach ($tabelas as $t) {
        if (! Schema::hasTable($t)) {
            test()->markTestSkipped("Tabela {$t} ausente — schema do Ponto não migrado nesta lane.");
        }
    }
}

/** O contrato de tela, decodificado. É a ÂNCORA dos casos de copy/ordem. */
function painelContrato(): array
{
    $raw = file_get_contents(base_path(PAINEL_CONTRATO));
    test()->assertNotFalse($raw, 'Contrato de tela ausente — sem ele não há o que afirmar.');

    $json = json_decode($raw, true);
    test()->assertIsArray($json, 'Contrato de tela não é JSON válido.');

    return $json;
}

/** Copy declarada de uma seção do contrato, na ordem em que o contrato a declara. */
function painelCopyDaSecao(string $id): array
{
    foreach (painelContrato()['secoes'] as $secao) {
        if (($secao['id'] ?? null) === $id) {
            return $secao['copy'] ?? [];
        }
    }

    test()->fail("Seção '{$id}' não existe no contrato — o caso perdeu a âncora.");
}

/** Fonte do alvo (a Page). */
function painelSource(): string
{
    $src = file_get_contents(base_path(PAINEL_PAGE));
    test()->assertNotFalse($src, 'Page alvo ausente.');

    return $src;
}

/** Colaborador ativo marcado pro cleanup. `forceFill` pra poder nascer alheio. */
function painelCriarColaborador(int $businessId, int $userBusinessId): Colaborador
{
    $user = User::factory()->create([
        'business_id' => $userBusinessId,
        'user_type'   => 'user',
    ]);

    $colab = new Colaborador();
    $colab->forceFill([
        'business_id'    => $businessId,
        'user_id'        => $user->id,
        'matricula'      => PAINEL_MARCADOR . '-' . uniqid(),
        'controla_ponto' => true,
        'desligamento'   => null,
    ])->save();

    return $colab;
}

/** Dia apurado (alimenta atrasos / faltas / HE / divergências). */
function painelCriarApuracao(int $businessId, int $colaboradorId, array $attrs = []): void
{
    $ap = new ApuracaoDia();
    $ap->forceFill(array_merge([
        'business_id'           => $businessId,
        'colaborador_config_id' => $colaboradorId,
        'data'                  => now()->toDateString(),
        'estado'                => ApuracaoDia::ESTADO_DIVERGENCIA,
        'atraso_minutos'        => 90,
        'falta_minutos'         => 60,
        'he_diurna_minutos'     => 120,
    ], $attrs))->save();
}

/**
 * Intercorrência PENDENTE (alimenta o KPI e a fila de aprovações).
 *
 * Devolve o **id** (UUID), não o `codigo`: MEDIDO no CT 100 (2026-08-23) que
 * `DashboardController::buildAprovacoes` NÃO expõe `codigo` no payload — as
 * chaves são id · tipo · prioridade · data_inicio · data_fim · justificativa ·
 * estado · created_at · colaborador. Afirmar sobre uma chave que a tela não
 * entrega reprovaria o teste sem dizer nada sobre o produto.
 *
 * Nasce `URGENTE` porque a fila tem `limit(5)` com `orderByDesc('prioridade')`:
 * numa base persistente com pendências pré-existentes, uma fixture NORMAL pode
 * legitimamente não caber nas 5 primeiras, e o caso falharia por corte de lista,
 * não por isolamento.
 */
function painelCriarIntercorrenciaPendente(int $businessId, int $colaboradorId, int $solicitanteId): string
{
    $id = (string) Str::uuid();

    $i = new Intercorrencia();
    $i->forceFill([
        'id'                    => $id,
        'business_id'           => $businessId,
        'colaborador_config_id' => $colaboradorId,
        'codigo'                => PAINEL_MARCADOR . '-' . uniqid(),
        'tipo'                  => 'ESQUECIMENTO_MARCACAO',
        'data'                  => now()->toDateString(),
        'dia_todo'              => 0,
        'justificativa'         => 'Fixture de contrato do Painel.',
        'estado'                => Intercorrencia::ESTADO_PENDENTE,
        'prioridade'            => 'URGENTE',
        'solicitante_id'        => $solicitanteId,
    ])->save();

    return $id;
}

/** Pede as props diferidas (Inertia::defer) numa segunda passada. */
function painelInertiaPartial(array $props)
{
    $manifestPath = public_path('build-inertia/manifest.json');
    $version = file_exists($manifestPath) ? md5_file($manifestPath) : '1';

    return test()->withHeaders([
        'X-Inertia'                   => 'true',
        'X-Inertia-Version'           => $version,
        'X-Inertia-Partial-Data'      => implode(',', $props),
        'X-Inertia-Partial-Component' => 'Ponto/Dashboard/Index',
        'Accept'                      => 'text/html',
    ])->get('/ponto');
}

afterEach(function () {
    try {
        $ids = Colaborador::withoutGlobalScopes()
            ->where('matricula', 'like', PAINEL_MARCADOR . '%')
            ->pluck('id');

        // Cleanup usa DB::table de propósito (jamais em código de produção).
        // Nenhuma destas 3 tabelas tem trigger de imutabilidade — MEDIDO em
        // mysql-schema.sql; só `ponto_marcacoes` tem, e é por isso que ela não
        // é fixture aqui.
        DB::table('ponto_intercorrencias')
            ->where('codigo', 'like', PAINEL_MARCADOR . '%')
            ->delete();

        if ($ids->isNotEmpty()) {
            DB::table('ponto_apuracao_dia')->whereIn('colaborador_config_id', $ids)->delete();
            Colaborador::withoutGlobalScopes()->whereIn('id', $ids)->delete();
        }

        test()->removerBizAlheio();
    } catch (\Throwable $e) {
        // schema ausente ou dependente sobrando — cleanup best-effort.
    }
});

// =====================================================================
// Ponto/Dashboard/Index — o Painel
// =====================================================================

it('UC-PAINEL-01 · os seis KPIs aparecem com a copy e na ordem que o contrato manda', function () {
    $copy = painelCopyDaSecao('painel-kpis');

    expect($copy)->toHaveCount(6,
        'O contrato declara 6 KPIs. Se este número mudou, a mudança é decisão [W] no contrato — '
        . 'não se ajusta o teste pra caber.'
    );

    $src = painelSource();

    // A janela é a SEÇÃO, não o arquivo: procurar a copy no arquivo inteiro
    // acharia o rótulo em qualquer outro bloco e a ordem seria medida sobre o
    // texto errado.
    $inicio = strpos($src, 'data-contract="painel-kpis"');
    $fim    = strpos($src, 'data-contract="painel-fila-aprovacoes"');

    expect($inicio)->not->toBeFalse('Âncora painel-kpis ausente na Page.');
    expect($fim)->not->toBeFalse('Âncora painel-fila-aprovacoes ausente — sem ela não há como delimitar a seção dos KPIs.');
    expect($fim)->toBeGreaterThan($inicio, 'A fila não pode preceder os KPIs — o contrato fixa a ordem.');

    $secao = substr($src, $inicio, $fim - $inicio);

    $anterior = -1;
    foreach ($copy as $i => $label) {
        $pos = strpos($secao, $label);

        expect($pos)->not->toBeFalse(
            "O KPI \"{$label}\" (posição {$i} do contrato) não aparece literalmente na seção painel-kpis."
        );
        expect($pos)->toBeGreaterThan($anterior,
            "O KPI \"{$label}\" aparece fora da ordem contratada. A ordem é lei [W] "
            . '(prototipo-ui/contrato/ponto-painel.contract.json).'
        );

        $anterior = $pos;
    }
});

it('UC-PAINEL-02 · dado de outro empregador não entra em KPI nenhum nem na fila do painel', function () {
    $this->actAsAdmin();
    painelPrecisaDe(['ponto_colaborador_config', 'ponto_apuracao_dia', 'ponto_intercorrencias']);
    $alheio = $this->garantirBizAlheio();

    // Antes: fotografa os agregados do MEU empregador.
    $antes = painelInertiaPartial(['kpis']);
    $antes->assertStatus(200);
    $kpisAntes = $antes->json('props.kpis');

    expect($kpisAntes)->toBeArray(
        'Os KPIs precisam chegar na passada partial — sem eles o caso não mede nada.'
    );

    // Controle positivo: o painel REAGE a dado do meu empregador. Sem isto, um
    // painel quebrado devolvendo zeros passaria por imobilidade, não por isolamento.
    $meu = painelCriarColaborador($this->business->id, $this->business->id);
    painelCriarApuracao($this->business->id, $meu->id);
    $meuId = painelCriarIntercorrenciaPendente($this->business->id, $meu->id, $this->admin->id);

    $comMeu = painelInertiaPartial(['kpis', 'aprovacoes']);
    $comMeu->assertStatus(200);
    $kpisComMeu = $comMeu->json('props.kpis');

    expect($kpisComMeu)->not->toBe($kpisAntes,
        'Os KPIs não se mexeram com dado do MEU empregador — o caso não exerceu nada.'
    );

    $idsComMeu = collect($comMeu->json('props.aprovacoes') ?? [])->pluck('id')->all();

    // `assertContains` e não `expect()->toContain()`: o `toContain` do Pest é
    // VARIÁDICO (aceita N needles), então a mensagem viraria um segundo needle e
    // o caso reprovaria com "array não contém <a explicação>" — §5 2026-07-28.
    $this->assertContains($meuId, $idsComMeu,
        'A minha intercorrência pendente não apareceu na fila — sem ela, "a alheia não está" '
        . 'seria verdade por lista vazia, não por isolamento.'
    );

    // Agora nasce o mesmo tipo de dado em OUTRO empregador.
    $outro = painelCriarColaborador($alheio, $this->business->id);
    painelCriarApuracao($alheio, $outro->id, ['he_diurna_minutos' => 9999, 'atraso_minutos' => 9999]);
    $idAlheio = painelCriarIntercorrenciaPendente($alheio, $outro->id, $this->admin->id);

    $depois = painelInertiaPartial(['kpis', 'aprovacoes']);
    $depois->assertStatus(200);

    expect($depois->json('props.kpis'))->toBe($kpisComMeu,
        'Nenhum KPI do meu empregador pode mudar porque nasceu dado em OUTRO empregador. '
        . 'Agregado que vaza não deixa linha para ninguém notar (ADR 0093 · Tier 0).'
    );

    $idsDepois = collect($depois->json('props.aprovacoes') ?? [])->pluck('id')->all();

    $this->assertContains($meuId, $idsDepois,
        'A minha intercorrência sumiu da fila — o caso perdeu a pré-condição.'
    );
    $this->assertNotContains($idAlheio, $idsDepois,
        'Intercorrência de OUTRO empregador apareceu na fila do painel (ADR 0093 · Tier 0).'
    );
});

it('UC-PAINEL-03 · sem intercorrência pendente a fila aparece com a frase de vazio do contrato', function () {
    $frase = 'Nenhuma intercorrência aguardando decisão.';

    $this->assertContains($frase, painelCopyDaSecao('painel-fila-aprovacoes'),
        'A frase de vazio saiu do contrato. Mudança de copy é decisão [W] — o teste não a persegue.'
    );

    $src = painelSource();
    $inicio = strpos($src, 'data-contract="painel-fila-aprovacoes"');
    $fim    = strpos($src, 'data-contract="painel-atividade"');

    expect($inicio)->not->toBeFalse('Âncora painel-fila-aprovacoes ausente na Page.');
    expect($fim)->not->toBeFalse('Âncora painel-atividade ausente na Page.');
    expect($fim)->toBeGreaterThan($inicio, 'A atividade tem de vir depois da fila (ordem do contrato).');

    // A frase tem de morar DENTRO da seção da fila — não basta existir no
    // arquivo: âncora numa seção e copy noutra é a forma de passar parecendo
    // que passou (RUNBOOK-dashboard §8).
    $this->assertStringContainsString($frase, substr($src, $inicio, $fim - $inicio),
        "A frase \"{$frase}\" não está dentro da seção painel-fila-aprovacoes."
    );

    // E o servidor precisa mesmo entregar a fila quando pedida.
    $this->actAsAdmin();
    painelPrecisaDe(['ponto_intercorrencias']);

    $resp = painelInertiaPartial(['aprovacoes']);
    $resp->assertStatus(200);

    // `assertArrayHasKey` e não `expect()->toHaveKey()`: o 2º argumento do
    // `toHaveKey` é o VALOR esperado, não a mensagem — passar a explicação ali
    // faria o caso exigir que `aprovacoes` fosse igual ao texto (mesma família do
    // `toContain` variádico, §5 2026-07-28).
    $this->assertArrayHasKey('aprovacoes', $resp->json('props'),
        'A prop da fila tem de chegar quando pedida — sem ela a tela não tem como decidir o estado vazio.'
    );
});

it('UC-PAINEL-04 · o painel é somente leitura: a rota não aceita escrita e o controller não grava', function () {
    $this->actAsAdmin();

    // 1) A rota /ponto só existe em GET (Modules/Ponto/Http/routes.php).
    foreach (['post', 'put', 'patch', 'delete'] as $verbo) {
        $resp = $this->{$verbo}('/ponto');

        expect($resp->status())->not->toBe(200,
            "A rota /ponto respondeu 200 a {$verbo} — o painel é read-only (charter §Anti-hooks)."
        );
    }

    // 2) O controller não chama primitiva de escrita nenhuma. Âncora: charter
    //    §Anti-hooks ("Não muta nada — dashboard é read-only").
    $escritas = [
        '->save(', '->update(', '->delete(', '->insert(', '->create(',
        '->forceCreate(', '->firstOrCreate(', '->updateOrCreate(',
        'DB::insert(', 'DB::update(', 'DB::delete(',
    ];

    $src = file_get_contents(base_path('Modules/Ponto/Http/Controllers/DashboardController.php'));
    expect($src)->not->toBeFalse();

    foreach ($escritas as $chamada) {
        $this->assertStringNotContainsString($chamada, $src,
            "DashboardController chama {$chamada} — o painel não pode gravar (charter §Anti-hooks)."
        );
    }

    // Controle positivo do DETECTOR: o mesmo predicado encontra escrita num
    // controller que de fato grava. Sem isto, uma lista de agulhas errada daria
    // verde em qualquer arquivo — o gate mediria a si mesmo, não o controller.
    $escritor = file_get_contents(base_path('Modules/Ponto/Http/Controllers/EscalaController.php'));
    expect($escritor)->not->toBeFalse();

    $achou = false;
    foreach ($escritas as $chamada) {
        if (str_contains($escritor, $chamada)) {
            $achou = true;
            break;
        }
    }

    expect($achou)->toBeTrue(
        'O detector de escrita não achou nada no EscalaController, que grava — logo o verde '
        . 'do DashboardController mediria a lista de agulhas, não o controller.'
    );
});

it('UC-PAINEL-05 · o polling recarrega apenas as props de leitura declaradas no charter', function () {
    // Âncora: Index.charter.md §Automation hooks declara a lista literal.
    $esperadas = ['kpis', 'presenca_agora', 'atividade_recente', 'alertas', 'server_time'];

    $src = painelSource();
    $pos = strpos($src, 'router.reload(');

    expect($pos)->not->toBeFalse(
        'O painel não tem polling — o charter §Automation hooks declara refresh a cada 30s.'
    );

    $bloco = substr($src, $pos, 400);

    $this->assertStringContainsString('only:', $bloco,
        'O reload precisa ser PARCIAL (only) — reload cheio refaz todas as closures '
        . 'deferidas a cada 30s (charter §Automation hooks).'
    );

    // A metade que importa: ler a lista DECLARADA e compará-la inteira. Só
    // conferir presença deixaria passar uma prop a mais (recarrega de graça a
    // cada 30s) ou uma prop de escrita entrando no polling.
    $ok = preg_match('/only:\s*\[([^\]]*)\]/', $bloco, $m);

    expect($ok)->toBe(1, 'Não consegui ler a lista do polling — o caso não mediu nada.');

    $declaradas = array_values(array_filter(array_map(
        static fn ($p) => trim($p, " \t\n\r'\"" ),
        explode(',', $m[1])
    )));

    sort($declaradas);
    $ordenadas = $esperadas;
    sort($ordenadas);

    expect($declaradas)->toBe($ordenadas,
        'A lista do polling divergiu do charter §Automation hooks. Props a mais recarregam '
        . 'de graça a cada 30s; props a menos deixam o painel mostrar dado velho.'
    );
});

it('UC-PAINEL-06 · a nota do que trava o fechamento vem acima dos KPIs e reflete o estado real', function () {
    // Ordem: o contrato põe a nota como PRIMEIRA seção.
    expect(painelContrato()['ordem'][0])->toBe('painel-nota-fechamento',
        'O contrato declara a nota como 1ª seção — quem abre o painel precisa ver o que trava '
        . 'o mês antes de tentar fechá-lo.'
    );

    $src = painelSource();
    $posNota = strpos($src, 'data-contract="painel-nota-fechamento"');
    $posKpis = strpos($src, 'data-contract="painel-kpis"');

    expect($posNota)->not->toBeFalse('Âncora painel-nota-fechamento ausente na Page.');
    expect($posKpis)->not->toBeFalse('Âncora painel-kpis ausente na Page.');
    expect($posNota)->toBeLessThan($posKpis, 'A nota tem de preceder os KPIs (ordem do contrato).');

    // A copy que nomeia o estado travado é do contrato, não escolhida aqui.
    $this->assertContains('DIVERGENCIA', painelCopyDaSecao('painel-nota-fechamento'),
        'A copy DIVERGENCIA saiu do contrato — mudança de copy é decisão [W].'
    );
    $this->assertStringContainsString('DIVERGENCIA', substr($src, $posNota, $posKpis - $posNota),
        'A palavra DIVERGENCIA não aparece dentro da seção da nota.'
    );

    // E o servidor precisa entregar o número que decide o estado da nota.
    $this->actAsAdmin();
    painelPrecisaDe(['ponto_colaborador_config', 'ponto_apuracao_dia']);

    $antes = painelInertiaPartial(['kpis']);
    $antes->assertStatus(200);
    $divAntes = $antes->json('props.kpis.divergencias_mes');

    expect($divAntes)->toBeInt(
        'O painel não entrega `divergencias_mes` — sem ele a nota não tem como distinguir '
        . '"a competência pode consolidar" de "o AFD sai com a jornada errada".'
    );

    $colab = painelCriarColaborador($this->business->id, $this->business->id);
    painelCriarApuracao($this->business->id, $colab->id, ['estado' => ApuracaoDia::ESTADO_DIVERGENCIA]);

    $depois = painelInertiaPartial(['kpis']);
    $depois->assertStatus(200);

    expect($depois->json('props.kpis.divergencias_mes'))->toBe($divAntes + 1,
        'Nasceu um dia em DIVERGENCIA na competência e a nota do painel não ficaria sabendo.'
    );
});
