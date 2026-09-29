<?php

declare(strict_types=1);

use App\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Schema;
use Modules\Ponto\Entities\Colaborador;
use Modules\Ponto\Entities\Intercorrencia;
use Modules\Ponto\Tests\Feature\PontoTestCase;

uses(PontoTestCase::class);

/**
 * Contrato das telas de intercorrência — fila (`/ponto/intercorrencias`) e registro
 * (`/ponto/intercorrencias/create`).
 *
 * Cada teste cita o UC no TÍTULO do `it()` (G-2 do casos-gate, ADR 0264):
 *   Intercorrencias/Index.casos.md  → UC-INTIDX-01..04 (o 04 é o GUARD de D-INTERC-ACOES)
 *   Intercorrencias/Create.casos.md → UC-INTCRE-01..04
 *
 * Os UC derivam do SDD §6.2 (CU-PONTO-05) e §6.5 (CU-PONTO-12) + US-PONTO-003 +
 * fluxo F4 (§5.3). NÃO do `.tsx`.
 *
 * ⚠️ UM UC nasce FAILING-FIRST por desenho:
 *   UC-INTCRE-01 → `business_id` NUNCA é atribuído no caminho de criação. Medido:
 *                  o FormRequest não declara a chave, o Service seta só
 *                  codigo/solicitante_id/estado, o `creating` só gera UUID, o trait
 *                  HasBusinessScope só adiciona scope de LEITURA, e há 0 `observe()`
 *                  no módulo. A coluna é NOT NULL + FK, sem default. O próprio
 *                  Service denuncia que sabia: usa `($dados['business_id'] ?? 0)`.
 *                  Registrar intercorrência pela tela NÃO grava.
 *
 * Não duplica as telas irmãs: a DECISÃO (aprovar/rejeitar/lote cross-tenant) é
 * UC-PAPR-* em Aprovacoes/Index; o DETALHE é UC-INTSHOW-* em Intercorrencias/Show.
 *
 * PII: os asserts comparam ids, nunca o texto da justificativa; a fixture usa texto
 * neutro (LGPD Art. 7º II · SDD §3.1).
 *
 * Tier 0: biz=1 (WR2 interno) — NUNCA biz=4 (ROTA LIVRE, ADR 0101). Sem
 * RefreshDatabase: a lane ponto-pest proíbe.
 *
 * @see \Modules\Ponto\Http\Controllers\IntercorrenciaController
 */

const INTC_MARCADOR = 'SDD-INTC-CONTRATO';
const INTC_BIZ_ALHEIO = 99;
const INTC_BIZ_NOME = 'INTC Test Biz Adversario#99';

function intcPrecisaDe(array $tabelas): void
{
    foreach ($tabelas as $t) {
        if (! Schema::hasTable($t)) {
            test()->markTestSkipped("Tabela {$t} ausente — schema do Ponto não migrado nesta lane.");
        }
    }
}

/** Stub do biz fictício — sem ele o INSERT morre na FK (medido na run 30778424885). */
function intcGarantirBizAlheio(): void
{
    if (\App\Business::find(INTC_BIZ_ALHEIO)) {
        return;
    }

    \App\Business::forceCreate([
        'id'                              => INTC_BIZ_ALHEIO,
        'name'                            => INTC_BIZ_NOME,
        'currency_id'                     => 1,
        'start_date'                      => now()->toDateString(),
        'default_profit_percent'          => 0,
        'owner_id'                        => 1,
        'stop_selling_before'             => 0,
        'weighing_scale_setting'          => '',
        'certificado'                     => '',
        'officeimpresso_numerodemaquinas' => 0,
    ]);
}

/**
 * GET com cabeçalho Inertia.
 *
 * MEDIDO na run 30779959209: `$this->get(...)` cru devolve o HTML da página (o
 * Inertia só responde JSON quando o request se declara Inertia), então
 * `->json('props...')` estoura "Invalid JSON was returned from the route" — o caso
 * morre sem exercer nada. Mesmo helper que o EspelhoContratoTest usa.
 */
function intcInertiaGet(string $url)
{
    $manifestPath = public_path('build-inertia/manifest.json');
    $version = file_exists($manifestPath) ? md5_file($manifestPath) : '1';

    return test()->withHeaders([
        'X-Inertia'         => 'true',
        'X-Inertia-Version' => $version,
        'Accept'            => 'text/html',
    ])->get($url);
}

function intcCriarColaborador(int $businessId, int $userBusinessId): Colaborador
{
    $user = User::factory()->create([
        'business_id' => $userBusinessId,
        'user_type'   => 'user',
        'username'    => strtolower(INTC_MARCADOR) . '-' . uniqid(),
    ]);

    $colab = new Colaborador();
    $colab->forceFill([
        'business_id'    => $businessId,
        'user_id'        => $user->id,
        'matricula'      => INTC_MARCADOR . '-' . uniqid(),
        'controla_ponto' => true,
        'admissao'       => '2019-01-01',
    ])->save();

    return $colab;
}

/** Justificativa NEUTRA de propósito — nada de conteúdo sensível em fixture (LGPD). */
function intcCriarIntercorrencia(int $businessId, int $colaboradorId, int $solicitanteId, string $estado): Intercorrencia
{
    $i = new Intercorrencia();
    $i->forceFill([
        'business_id'           => $businessId,
        'colaborador_config_id' => $colaboradorId,
        'codigo'                => INTC_MARCADOR . '-' . strtoupper(substr(uniqid(), -8)),
        'tipo'                  => 'OUTRO',
        'data'                  => '2019-03-11',
        'dia_todo'              => true,
        'justificativa'         => 'Fixture de contrato SDD — texto neutro, sem PII.',
        'estado'                => $estado,
        'prioridade'            => 'NORMAL',
        'solicitante_id'        => $solicitanteId,
    ])->save();

    return $i;
}

afterEach(function () {
    try {
        DB::table('ponto_intercorrencias')
            ->where('codigo', 'like', INTC_MARCADOR . '%')
            ->delete();

        $ids = Colaborador::withoutGlobalScopes()
            ->where('matricula', 'like', INTC_MARCADOR . '%')
            ->pluck('id');

        if ($ids->isNotEmpty()) {
            DB::table('ponto_intercorrencias')->whereIn('colaborador_config_id', $ids)->delete();
            Colaborador::withoutGlobalScopes()->whereIn('id', $ids)->delete();
        }

        // Só o stub deste arquivo (filtro por nome próprio).
        \App\Business::where('id', INTC_BIZ_ALHEIO)
            ->where('name', INTC_BIZ_NOME)
            ->delete();
    } catch (\Throwable $e) {
        // schema ausente — cleanup best-effort
    }
});

// =====================================================================
// Intercorrencias/Index — a fila
// =====================================================================

it('UC-INTIDX-01 · a fila traz as intercorrências do meu empregador', function () {
    $this->actAsAdmin();
    intcPrecisaDe(['ponto_colaborador_config', 'ponto_intercorrencias']);

    $colab = intcCriarColaborador($this->business->id, $this->business->id);
    $inc = intcCriarIntercorrencia(
        $this->business->id, $colab->id, $this->admin->id, Intercorrencia::ESTADO_PENDENTE
    );

    $resp = intcInertiaGet('/ponto/intercorrencias');
    $resp->assertStatus(200);

    $linhas = collect($resp->json('props.intercorrencias.data') ?? []);

    // Pré-condição anti-vácuo (proibicoes.md §5 2026-07-24 LC-13).
    expect($linhas)->not->toBeEmpty('A fila veio vazia — o caso não exerceu nada.');

    $minha = $linhas->firstWhere('id', $inc->id);
    expect($minha)->not->toBeNull(
        'A intercorrência do meu business tem de aparecer na fila (CU-PONTO-05).'
    );
    expect($minha['estado'])->toBe(Intercorrencia::ESTADO_PENDENTE,
        'A fila tem de mostrar o estado — é por ele que o RH sabe o que falta decidir.'
    );
    // Identidade: `optional()` encadeado devolve '—' em silêncio se o eager-load sumir.
    expect($minha['colaborador']['matricula'])->toStartWith(INTC_MARCADOR,
        'A linha tem de trazer a matrícula do colaborador, não um placeholder.'
    );
});

it('UC-INTIDX-02 · intercorrência de outro empregador não aparece na fila', function () {
    $this->actAsAdmin();
    intcPrecisaDe(['ponto_colaborador_config', 'ponto_intercorrencias']);
    intcGarantirBizAlheio();

    $meuColab    = intcCriarColaborador($this->business->id, $this->business->id);
    $alheioColab = intcCriarColaborador(INTC_BIZ_ALHEIO, $this->business->id);

    $minha  = intcCriarIntercorrencia(
        $this->business->id, $meuColab->id, $this->admin->id, Intercorrencia::ESTADO_PENDENTE
    );
    $alheia = intcCriarIntercorrencia(
        INTC_BIZ_ALHEIO, $alheioColab->id, $this->admin->id, Intercorrencia::ESTADO_PENDENTE
    );

    $resp = intcInertiaGet('/ponto/intercorrencias');
    $resp->assertStatus(200);

    // Compara IDs — nunca o texto da justificativa (PII, LGPD Art. 7º II).
    $ids = collect($resp->json('props.intercorrencias.data') ?? [])->pluck('id')->all();

    // Pré-condição anti-vácuo: sem a minha, "a alheia não está" seria verdade por
    // lista vazia, não por isolamento.
    $this->assertContains(
        $minha->id,
        $ids,
        'A minha intercorrência tem de estar na fila — senão o caso não exerce isolamento.'
    );
    $this->assertNotContains(
        $alheia->id,
        $ids,
        'Intercorrência de OUTRO empregador não pode aparecer — a justificativa é dado '
        . 'sensível (ADR 0093 · CU-PONTO-12 · LGPD Art. 7º II).'
    );
});

it('UC-INTIDX-03 · filtrar por estado devolve só aquele estado', function () {
    $this->actAsAdmin();
    intcPrecisaDe(['ponto_colaborador_config', 'ponto_intercorrencias']);

    $colab = intcCriarColaborador($this->business->id, $this->business->id);

    $pendente = intcCriarIntercorrencia(
        $this->business->id, $colab->id, $this->admin->id, Intercorrencia::ESTADO_PENDENTE
    );
    $rascunho = intcCriarIntercorrencia(
        $this->business->id, $colab->id, $this->admin->id, Intercorrencia::ESTADO_RASCUNHO
    );

    $resp = intcInertiaGet('/ponto/intercorrencias?estado=' . Intercorrencia::ESTADO_PENDENTE);
    $resp->assertStatus(200);

    $ids = collect($resp->json('props.intercorrencias.data') ?? [])->pluck('id')->all();

    // Os DOIS lados: só o positivo passaria com o filtro desligado (o `when()` vira
    // no-op silencioso se a chave do request mudar de nome).
    $this->assertContains(
        $pendente->id,
        $ids,
        'Filtrar por PENDENTE tem de trazer a intercorrência pendente.'
    );
    $this->assertNotContains(
        $rascunho->id,
        $ids,
        'Filtrar por PENDENTE NÃO pode trazer o rascunho. Filtro que vira no-op em '
        . 'silêncio faz o RH decidir sobre a lista errada (CU-PONTO-05).'
    );
});

// =====================================================================
// Intercorrencias/Create — o registro
// =====================================================================

it('UC-INTCRE-01 · registrar uma intercorrência cria o rascunho', function () {
    $this->actAsAdmin();
    intcPrecisaDe(['ponto_colaborador_config', 'ponto_intercorrencias']);

    $colab = intcCriarColaborador($this->business->id, $this->business->id);

    $antes = DB::table('ponto_intercorrencias')
        ->where('colaborador_config_id', $colab->id)
        ->count();

    $this->post('/ponto/intercorrencias', [
        'colaborador_config_id' => $colab->id,
        'tipo'                  => 'ATESTADO_MEDICO',
        'data'                  => '2019-03-11',
        'dia_todo'              => true,
        'justificativa'         => 'Fixture de contrato SDD — texto neutro, sem PII.',
        'prioridade'            => 'NORMAL',
    ]);

    // Lê o ESTADO PERSISTIDO, não o status HTTP: o assert vale para qualquer correção
    // (injetar business_id no Service, no creating, ou no FormRequest).
    $criada = DB::table('ponto_intercorrencias')
        ->where('colaborador_config_id', $colab->id)
        ->first();

    expect($criada)->not->toBeNull(
        'Registrar a intercorrência tem de GRAVAR (CU-PONTO-05). Hoje o `business_id` nunca é '
        . 'atribuído no caminho de criação — o FormRequest não declara a chave, o Service seta '
        . 'só codigo/solicitante_id/estado, o `creating` só gera UUID, o trait HasBusinessScope '
        . 'só adiciona scope de LEITURA e há 0 observe() no módulo. A coluna é NOT NULL + FK, '
        . 'sem default.'
    );
    expect($criada->estado)->toBe(Intercorrencia::ESTADO_RASCUNHO,
        'A intercorrência nasce RASCUNHO — só vai a PENDENTE por ação explícita (US-PONTO-003).'
    );
    expect((int) DB::table('ponto_intercorrencias')->where('colaborador_config_id', $colab->id)->count())
        ->toBe($antes + 1, 'Exatamente uma intercorrência tem de ter sido criada.');
});

/**
 * GUARD — D-INTERC-ACOES ([W] 2026-09-14, ata bloco 2): a lista NÃO submete nem edita; o Show é
 * o dono da ação. R1 da mesma ata: Non-Goal ratificado vira Pest GUARD.
 *
 * Lido no fonte da página porque o comportamento é da TELA: o `index()` não manda ação nenhuma no
 * payload, então um teste HTTP ficaria verde com os botões recolocados. É o mesmo idioma do
 * UC-INTCRE-03 acima. Limite: URL montada por concatenação escaparia — as rotas de escrita têm as
 * próprias defesas no servidor.
 */
it('UC-INTIDX-04 · a linha da fila só oferece Ver — não submete nem edita', function () {
    $src = file_get_contents(base_path('resources/js/Pages/Ponto/Intercorrencias/Index.tsx'));

    expect($src)->not->toBeFalse();
    $texto = preg_replace('/\s+/u', ' ', (string) $src);

    // Âncora positiva: sem ela, um arquivo esvaziado passaria nas negativas abaixo.
    expect(preg_match('#href=\{`/ponto/intercorrencias/\$\{i\.id\}`\}#u', $texto))->toBe(1,
        'A linha tem de levar ao detalhe (Show) — é por lá que se submete e se edita.'
    );

    // Negativas: nenhuma rota de ação de intercorrência a partir da lista.
    expect(preg_match('#/ponto/intercorrencias/[^\s"\'`]*/(submeter|cancelar|edit)\b#u', $texto))->toBe(0,
        'A lista não pode chamar submeter/cancelar/editar (D-INTERC-ACOES, [W] 2026-09-14).'
    );
    // Nenhuma escrita a partir da lista: só navegação (router.get / router.visit).
    expect(preg_match('/router\s*\.\s*(post|put|patch|delete)\s*\(/u', $texto))->toBe(0,
        'A lista é read-only: nenhuma chamada de escrita.'
    );
    // Nenhum rótulo de ação de escrita na linha.
    expect(preg_match('/>\s*(Editar|Submeter)\s*</u', $texto))->toBe(0,
        'Os botões Editar/Submeter saíram da linha em 2026-09-14 e não voltam.'
    );
});

it('UC-INTCRE-03 · a tela não promete enviar ao RH ao salvar', function () {
    // Âncora: Create.charter.md, Anti-hooks — "Salvar não dispara aprovação nem notifica o RH
    // (submeter é ação separada no `Show`)". A copy vive no .tsx (não é prop Inertia), então o
    // contrato é lido no fonte da página.
    $src = file_get_contents(base_path('resources/js/Pages/Ponto/Intercorrencias/Create.tsx'));

    expect($src)->not->toBeFalse();
    // Normaliza quebras de linha do JSX para a frase não escapar por estar partida.
    $texto = preg_replace('/\s+/u', ' ', (string) $src);

    expect(preg_match('/submetid[oa]s?\s+ao\s+RH/iu', $texto))->toBe(0);
    expect(preg_match('/Salvar cria um rascunho/u', $texto))->toBe(1);
});

it('UC-INTCRE-02 · a lista de colaboradores traz só os do meu empregador', function () {
    $this->actAsAdmin();
    intcPrecisaDe(['ponto_colaborador_config']);
    intcGarantirBizAlheio();

    $meu    = intcCriarColaborador($this->business->id, $this->business->id);
    $alheio = intcCriarColaborador(INTC_BIZ_ALHEIO, $this->business->id);

    $resp = intcInertiaGet('/ponto/intercorrencias/create');
    $resp->assertStatus(200);

    $ids = collect($resp->json('props.colaboradores') ?? [])->pluck('id')->all();

    // Pré-condição anti-vácuo: sem o meu, "o alheio não está" seria verdade por lista
    // vazia, não por isolamento.
    $this->assertContains(
        $meu->id,
        $ids,
        'O colaborador do meu business tem de ser selecionável — senão o caso não exerce nada.'
    );
    $this->assertNotContains(
        $alheio->id,
        $ids,
        'Colaborador de OUTRO empregador não pode ser selecionável — o seletor expõe nome e '
        . 'matrícula (ADR 0093 · CU-PONTO-12 · LGPD Art. 7º).'
    );
});

// =====================================================================
// Intercorrencias/Create — o comprovante (D-INTERC-ANEXO)
// =====================================================================

/** Disco configurado do comprovante, e a prova de que ele NÃO é servido pelo webserver. */
function intcDiscoAnexoPrivado(): string
{
    $disco = (string) config('pontowr2.intercorrencias.anexo_disk');
    $raiz  = (string) config("filesystems.disks.{$disco}.root");

    // Medido ANTES do Storage::fake (que troca a raiz). Neste app o disco `local` aponta para
    // public_path('uploads'): um atestado lá seria baixável por URL, sem login.
    test()->assertNotSame('', $raiz, "O disco `{$disco}` do comprovante tem de existir na config.");
    test()->assertStringStartsNotWith(
        public_path(),
        $raiz,
        "O comprovante é dado de saúde (LGPD Art. 11) e não pode ir para disco dentro do webroot — `{$disco}` aponta para {$raiz}."
    );

    return $disco;
}

it('UC-INTCRE-04 · o comprovante anexado vai para disco privado, só quem aprova baixa, e nada dele vai a log', function () {
    $this->actAsAdmin();
    intcPrecisaDe(['ponto_colaborador_config', 'ponto_intercorrencias']);

    $disco = intcDiscoAnexoPrivado();
    Storage::fake($disco);

    // Coleta TODO log emitido durante o registro — o caminho e o nome do arquivo não podem
    // aparecer em nenhum (atestado é dado de saúde; nome de arquivo costuma trazer nome/CID).
    $logs = [];
    Event::listen(MessageLogged::class, function (MessageLogged $m) use (&$logs) {
        $logs[] = $m->message . ' ' . json_encode($m->context);
    });

    $colab = intcCriarColaborador($this->business->id, $this->business->id);
    $nomeOriginal = 'atestado-fulano-cid-sdd.pdf';
    $conteudo = "%PDF-1.4" . PHP_EOL . "SDD fixture neutra";

    $this->post('/ponto/intercorrencias', [
        'colaborador_config_id' => $colab->id,
        'tipo'                  => 'ATESTADO_MEDICO',
        'data'                  => '2019-03-11',
        'dia_todo'              => true,
        'justificativa'         => 'Fixture de contrato SDD — texto neutro, sem PII.',
        'prioridade'            => 'NORMAL',
        'anexo'                 => UploadedFile::fake()->createWithContent($nomeOriginal, $conteudo),
    ]);

    $criada = Intercorrencia::where('colaborador_config_id', $colab->id)->first();
    $this->assertNotNull($criada, 'A intercorrência com comprovante tem de ser gravada (UC-INTCRE-01 é pré-condição).');

    // 1) Vinculado e no disco privado, sob o empregador, sem o nome original.
    $path = (string) $criada->anexo_path;
    $this->assertNotSame('', $path, 'O comprovante tem de ficar vinculado à intercorrência (anexo_path).');
    Storage::disk($disco)->assertExists($path);
    $this->assertStringStartsWith("ponto/intercorrencias/biz-{$this->business->id}/", $path);
    $this->assertStringNotContainsString('fulano', $path, 'O nome original do arquivo não pode ir para o caminho gravado.');

    // 2) Nada do comprovante em log. Anti-vácuo: o registro EMITE log (ponto.intercorrencia.criada)
    //    — sem isso, "não achei o caminho no log" seria verdade por não haver log nenhum.
    $this->assertNotEmpty($logs, 'O registro tem de emitir log — senão o caso não exerce a ausência.');
    $tudo = implode(PHP_EOL, $logs);
    $this->assertStringNotContainsString($path, $tudo, 'O caminho do comprovante não pode aparecer em log (LGPD Art. 11).');
    $this->assertStringNotContainsString('fulano', $tudo, 'O nome do arquivo não pode aparecer em log (LGPD Art. 11).');

    // 3) Quem aprova (o admin tem ponto.aprovacoes.manage) baixa o arquivo, íntegro.
    $download = $this->get("/ponto/intercorrencias/{$criada->id}/anexo");
    $download->assertStatus(200);
    $this->assertSame($conteudo, $download->streamedContent(), 'O comprovante baixado tem de ser o arquivo enviado.');

    // 4) Quem NÃO aprova recebe 403. Âncora positiva (§5 2026-09-27): o mesmo usuário ABRE o
    //    detalhe (200) — prova que ele passou do middleware do módulo e que o 403 vem da regra
    //    do comprovante, não de "não entra no Ponto".
    $semAprovacao = User::factory()->create([
        'business_id' => $this->business->id,
        'user_type'   => 'user',
        'username'    => strtolower(INTC_MARCADOR) . '-leitor-' . uniqid(),
    ]);
    $semAprovacao->givePermissionTo('ponto.access');
    $this->assertFalse($semAprovacao->can('ponto.aprovacoes.manage'), 'O leitor não pode ter a permissão de aprovação.');

    $this->actingAs($semAprovacao);
    $detalhe = intcInertiaGet("/ponto/intercorrencias/{$criada->id}");
    $detalhe->assertStatus(200);
    $this->assertTrue((bool) $detalhe->json('props.intercorrencia.tem_anexo'), 'O detalhe informa que há comprovante.');
    $this->assertFalse((bool) $detalhe->json('props.intercorrencia.pode_baixar_anexo'), 'O detalhe não oferece o download a quem não aprova.');
    $this->assertStringNotContainsString($path, $detalhe->getContent(), 'O caminho do comprovante não vai ao front.');

    $this->get("/ponto/intercorrencias/{$criada->id}/anexo")->assertStatus(403);

    $semAprovacao->delete();
});

it('UC-INTCRE-04 · o comprovante de intercorrência de outro empregador responde 404', function () {
    $this->actAsAdmin();
    intcPrecisaDe(['ponto_colaborador_config', 'ponto_intercorrencias']);

    $disco = intcDiscoAnexoPrivado();
    Storage::fake($disco);

    intcGarantirBizAlheio();
    $colabAlheio = intcCriarColaborador(INTC_BIZ_ALHEIO, INTC_BIZ_ALHEIO);
    $alheia = intcCriarIntercorrencia(INTC_BIZ_ALHEIO, $colabAlheio->id, $this->admin->id, Intercorrencia::ESTADO_PENDENTE);

    $path = 'ponto/intercorrencias/biz-' . INTC_BIZ_ALHEIO . '/sdd-alheio.pdf';
    Storage::disk($disco)->put($path, "%PDF-1.4 SDD");
    DB::table('ponto_intercorrencias')->where('id', $alheia->id)->update(['anexo_path' => $path]);

    // Pré-condição anti-vácuo: o arquivo EXISTE e está vinculado — o 404 abaixo não pode vir
    // de "não há arquivo", só do isolamento.
    Storage::disk($disco)->assertExists($path);
    $this->assertSame($path, DB::table('ponto_intercorrencias')->where('id', $alheia->id)->value('anexo_path'));

    // O admin TEM a permissão de aprovação: o 404 não pode ser atribuído a falta dela.
    $this->assertTrue($this->admin->can('ponto.aprovacoes.manage'));

    // 404 e não 403: 403 confirmaria que o id existe (CU-PONTO-12).
    $this->get("/ponto/intercorrencias/{$alheia->id}/anexo")->assertStatus(404);
});
