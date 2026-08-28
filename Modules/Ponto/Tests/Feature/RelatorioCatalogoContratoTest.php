<?php

declare(strict_types=1);

use App\User;
use Illuminate\Support\Facades\Schema;
use Modules\Ponto\Entities\Colaborador;
use Modules\Ponto\Tests\Feature\PontoTestCase;

uses(PontoTestCase::class);

/** Marcador próprio deste arquivo — o cleanup só apaga o que ELE criou. */
const RELIDX_MARCADOR = 'RELIDX-TEST';

/**
 * Contrato do catálogo de relatórios (`/ponto/relatorios`).
 *
 * Cada teste cita o UC no TÍTULO do `it()` (G-2 do casos-gate, ADR 0264):
 *   Relatorios/Index.casos.md → UC-RELIDX-01..02
 *
 * Os UC derivam do SDD §6.5 `CU-PONTO-14` ("o catálogo de relatórios não promete o
 * que não entrega") + o fluxo F8 (§5.3). NÃO do `.tsx`.
 *
 * ── O que estes casos NÃO fazem ────────────────────────────────────────────
 * Não enumeram as 7 chaves indisponíveis de hoje, e não cravam o status 501.
 * Enumerar transformaria toda implementação legítima (uma `false` que vira `true`)
 * numa reprova; cravar o 501 reprovaria a melhoria de UX de trocá-lo por um 404 com
 * mensagem ou um redirect com toast. O assert é sobre a FORMA do contrato:
 * o indicador existe, o conjunto dos indisponíveis não é vazio enquanto `gerar()`
 * recusar, e a recusa não devolve sucesso.
 *
 * Não toca DB: o catálogo é um array estático no controller.
 *
 * Tier 0: biz=1 (WR2 interno) — NUNCA biz=4 (ROTA LIVRE, ADR 0101).
 *
 * Contrato: resources/js/Pages/Ponto/Relatorios/Index.casos.md
 *
 * @see \Modules\Ponto\Http\Controllers\RelatorioController
 */

/**
 * GET com cabeçalho Inertia.
 *
 * MEDIDO na run 30779959209: `$this->get(...)` cru devolve o HTML da página (o
 * Inertia só responde JSON quando o request se declara Inertia), então
 * `->json('props...')` estoura "Invalid JSON was returned from the route" — o caso
 * morre sem exercer nada. Mesmo helper que o EspelhoContratoTest usa.
 */
function relInertiaGet(string $url)
{
    $manifestPath = public_path('build-inertia/manifest.json');
    $version = file_exists($manifestPath) ? md5_file($manifestPath) : '1';

    return test()->withHeaders([
        'X-Inertia'         => 'true',
        'X-Inertia-Version' => $version,
        'Accept'            => 'text/html',
    ])->get($url);
}

it('UC-RELIDX-01 · relatório não implementado aparece marcado como indisponível', function () {
    $this->actAsAdmin();

    $resp = relInertiaGet('/ponto/relatorios');
    $resp->assertStatus(200);

    $relatorios = collect($resp->json('props.relatorios') ?? []);

    // Pré-condição anti-vácuo: catálogo vazio faria "todos declaram disponibilidade"
    // ser verdade por vacuidade (proibicoes.md §5 2026-07-24 LC-13).
    expect($relatorios)->not->toBeEmpty('O catálogo precisa listar relatórios.');

    // (a) Todo item declara disponibilidade — sem isso o operador não tem como saber
    //     antes de clicar.
    $semFlag = $relatorios->filter(fn ($r) => ! array_key_exists('disponivel', $r));
    expect($semFlag)->toBeEmpty(
        'Todo relatório do catálogo tem de declarar se está disponível — CU-PONTO-14.'
    );

    // (b) Enquanto houver relatório não implementado, ele tem de estar declarado como
    //     indisponível. Não enumeramos QUAIS: implementar um é trocar false→true, e
    //     um assert por chave transformaria a entrega legítima em reprova.
    $indisponiveis = $relatorios->filter(fn ($r) => $r['disponivel'] === false);
    expect($indisponiveis)->not->toBeEmpty(
        'US-PONTO-006 (AFD legacy) e US-PONTO-009 (AEJ) seguem `_pendente_` no SPEC e '
        . '`RelatorioController@gerar()` é abort(501) para qualquer chave — logo o catálogo '
        . 'tem de declarar pelo menos um indisponível. Se este assert falhar, alguém marcou '
        . 'os relatórios como prontos sem o gerador existir (CU-PONTO-14).'
    );
});

it('UC-RELIDX-02 · nenhum relatório do catálogo entrega download sem aviso', function () {
    $this->actAsAdmin();

    $catalogo = collect(relInertiaGet('/ponto/relatorios')->json('props.relatorios') ?? []);
    $indisponivel = $catalogo->first(fn ($r) => $r['disponivel'] === false);

    // Pré-condição anti-vácuo: sem um indisponível no catálogo, não há o que exercer.
    expect($indisponivel)->not->toBeNull(
        'Precisa haver ao menos um relatório indisponível para exercer a recusa.'
    );

    $resp = $this->get("/ponto/relatorios/{$indisponivel['chave']}");

    // "Não é sucesso" — e não "é exatamente 501": trocar o 501 por 404-com-mensagem ou
    // por redirect-com-toast é correção legítima de UX, e não pode reprovar aqui.
    expect($resp->isSuccessful())->toBeFalse(
        "O relatório '{$indisponivel['chave']}' está marcado como indisponível, então pedir a "
        . 'geração dele NÃO pode responder com sucesso. Entregar arquivo vazio ou página em '
        . 'branco numa fiscalização é pior que recusar — CU-PONTO-14.'
    );
});

// =====================================================================
// O catálogo aponta pro gerador que EXISTE — UC-RELIDX-03..05
//
// Contexto (SDD §5.3 F8 + §5.4 item 4): até 2026-08-28 o `gerar()` era
// abort(501) pra QUALQUER chave, inclusive `espelho` — o único card
// `disponivel: true`. Ou seja: o único botão habilitado da tela levava a um
// 501, enquanto o PDF do espelho existia e funcionava por OUTRA rota (F3).
// O `casos.md` registrava isso como [BACKLOG] declarando honestamente que o
// clique não tinha sido medido. Foi medido; [W] decidiu que o espelho sai
// POR COLABORADOR (não em lote); estes casos travam a decisão.
//
// Nenhum caso hardcoda a chave 'espelho': ela é DERIVADA do catálogo pelo par
// (disponivel && requer_colaborador). Um segundo relatório por-colaborador no
// futuro passa a ser exercido de graça, em vez de nascer descoberto.
// =====================================================================

/** O relatório vivo que sai por colaborador, lido do próprio catálogo. */
function relPorColaborador(): ?array
{
    $catalogo = collect(relInertiaGet('/ponto/relatorios')->json('props.relatorios') ?? []);

    return $catalogo->first(
        fn ($r) => ($r['disponivel'] ?? false) === true
            && ($r['requer_colaborador'] ?? false) === true
    );
}

/** Colaborador ativo num business — `forceFill` porque o alheio nasce fora do scope. */
function relCriarColaborador(int $businessId, int $userBusinessId): Colaborador
{
    $user = User::factory()->create([
        'business_id' => $userBusinessId,
        'user_type'   => 'user',
    ]);

    $colab = new Colaborador();
    $colab->forceFill([
        'business_id'    => $businessId,
        'user_id'        => $user->id,
        'matricula'      => RELIDX_MARCADOR . '-' . uniqid(),
        'controla_ponto' => true,
    ])->save();

    return $colab;
}

afterEach(function () {
    try {
        Colaborador::withoutGlobalScopes()
            ->where('matricula', 'like', RELIDX_MARCADOR . '%')
            ->delete();

        $this->removerBizAlheio();
    } catch (\Throwable $e) {
        // schema ausente — cleanup best-effort
    }
});

it('UC-RELIDX-03 · o relatório que sai por colaborador não gera sem um escolhido', function () {
    $this->actAsAdmin();

    $rel = relPorColaborador();

    // Anti-vácuo (LC-13): sem um relatório por-colaborador no catálogo, o caso não
    // exerce nada e passaria por vacuidade.
    expect($rel)->not->toBeNull(
        'O catálogo precisa declarar ao menos um relatório disponível que sai por '
        . 'colaborador (`requer_colaborador: true`) — é o contrato do espelho.'
    );

    // Sem `colaborador` na query: tem de ser RECUSADO, nunca "escolho um por você".
    $resp = $this->get("/ponto/relatorios/{$rel['chave']}");

    expect($resp->isSuccessful())->toBeFalse(
        "'{$rel['chave']}' sai por colaborador e o pedido não trouxe nenhum. Assumir um "
        . 'default entrega o documento de OUTRA pessoa — e espelho de ponto é peça de '
        . 'fiscalização (Portaria MTP 671/2021 Art. 85). Recusar é o comportamento correto.'
    );

    expect($resp->isRedirect())->toBeFalse(
        'Recusa por falta de colaborador não pode virar redirect pro gerador: o operador '
        . 'receberia um PDF que não foi o que ele pediu.'
    );
});

it('UC-RELIDX-04 · relatório marcado disponível leva ao gerador, não a "não implementado"', function () {
    $this->actAsAdmin();

    if (! Schema::hasTable('ponto_colaborador_config')) {
        $this->markTestSkipped('Tabela ponto_colaborador_config ausente.');
    }

    $rel = relPorColaborador();
    expect($rel)->not->toBeNull('Catálogo precisa ter o relatório por-colaborador.');

    $meu = relCriarColaborador($this->business->id, $this->business->id);

    $resp = $this->get("/ponto/relatorios/{$rel['chave']}?colaborador={$meu->id}&periodo=" . now()->format('Y-m'));

    // O ponto do caso: `disponivel: true` + insumo completo NÃO pode responder
    // "não implementado". Era exatamente o que acontecia antes (501 pra toda chave).
    expect($resp->status())->not->toBe(501,
        "'{$rel['chave']}' está marcado como DISPONÍVEL no catálogo. Responder 501 com o "
        . 'insumo completo é o catálogo prometendo o que não entrega — CU-PONTO-14.'
    );

    // E leva ao gerador que existe (F3), em vez de a um segundo gerador duplicado.
    expect($resp->isRedirect())->toBeTrue(
        'O catálogo é atalho pro gerador do espelho (F3), então o pedido redireciona pra lá.'
    );
    expect((string) $resp->headers->get('Location'))->toContain((string) $meu->id,
        'O redirect tem de carregar o colaborador que o operador escolheu.'
    );
});

it('UC-RELIDX-05 · pedir o relatório de colaborador de outro empregador é recusado', function () {
    $this->actAsAdmin();

    if (! Schema::hasTable('ponto_colaborador_config')) {
        $this->markTestSkipped('Tabela ponto_colaborador_config ausente.');
    }

    $rel = relPorColaborador();
    expect($rel)->not->toBeNull('Catálogo precisa ter o relatório por-colaborador.');

    $bizAlheio = $this->garantirBizAlheio();
    $alheio = relCriarColaborador($bizAlheio, $bizAlheio);

    $resp = $this->get("/ponto/relatorios/{$rel['chave']}?colaborador={$alheio->id}&periodo=" . now()->format('Y-m'));

    // Tier 0 (ADR 0093 · CU-PONTO-12): recurso de outro business responde 404 —
    // nunca 200 com dado, nunca redirect pro PDF alheio.
    expect($resp->status())->toBe(404,
        'Espelho de colaborador de OUTRO empregador tem de dar 404. Redirecionar pro '
        . 'gerador e deixar a defesa por conta do outro controller faria o isolamento '
        . 'depender de quem está do outro lado do redirect — ADR 0093.'
    );
});
