<?php

declare(strict_types=1);

use App\User;
use Modules\Jana\Contracts\AiAdapter;
use Modules\Jana\Entities\Conversa;
use Modules\Jana\Entities\Mensagem;
use Modules\Jana\Support\ContextoNegocio;

uses(Tests\TestCase::class);

/**
 * R-COPI-TOK — tokens do turno N devem gravar na mensagem assistant do turno N.
 *
 * CONTRATO (nao derivado do codigo — derivado do docblock do proprio contrato):
 * `Modules/Jana/Contracts/AiAdapter.php:30-33` diz que o CALLER e responsavel por
 * "Acumular chunks numa string e persistir Mensagem assistant ao fim" e
 * "Persistir tokens_in/out". Os dois drivers violam isso: escrevem no banco eles
 * mesmos, via `latest('created_at')` — e o generator retoma ANTES do controller
 * criar a mensagem do turno corrente (provado em PHP 8.4: o corpo apos o ultimo
 * yield roda durante o `next()` do foreach do caller).
 *
 *   LaravelAiSdkDriver.php:204  (stream, driver CANONICO/live)
 *   LaravelAiSdkDriver.php:333  (blocking)
 *   OpenAiDirectDriver.php:261  (stream, legado)
 *   OpenAiDirectDriver.php:188  (blocking, legado)
 *
 * Assinatura medida em PROD (2026-07-28, read-only): em conversas com >=2 turnos
 * assistant, a ULTIMA mensagem esta 6/6 = 100% sem tokens, enquanto 84,8% das
 * nao-ultimas tem — exatamente o deslocamento de um turno.
 *
 * O QUE ESTE TESTE EXERCITA DE VERDADE: a rota real + o ChatController real. O
 * unico dublê e o AiAdapter (nao ha LLM em CI) — e ele reproduz FIELMENTE a forma
 * dos drivers reais: efeito colateral depois do ultimo yield. Ele tambem DEVOLVE
 * o usage pelo `return` do generator, que e o canal que o contrato do AiAdapter
 * pede — assim o mesmo teste fica verde quando a correcao for aplicada, sem
 * reescrita.
 *
 * ANTI-VACUO (LC-13): as pre-condicoes provam que o stream RODOU e que as duas
 * mensagens assistant existem com conteudo. Sem isso o teste poderia "passar"
 * medindo ausencia de operacao.
 *
 * Multi-tenant: biz=1 (ADR 0101) — nunca biz=4 (ROTA LIVRE, cliente real).
 *
 * @see memory/proibicoes.md §"Claim sem evidencia"
 */

/**
 * Dublê do AiAdapter que espelha a forma dos drivers reais.
 */
final class FakeStreamAdapterTokens implements AiAdapter
{
    /** @var list<array{in:int,out:int,texto:string}> */
    private array $turnos;

    private int $chamada = 0;

    /** Registro do que cada turno realmente produziu (prova anti-vacuo). */
    public array $produzidos = [];

    /** @var array{tokens_in: int|null, tokens_out: int|null} */
    private array $ultimoUso = ['tokens_in' => null, 'tokens_out' => null];

    /**
     * Fila de turnos numa UNICA instancia — re-bind de container no meio do
     * teste nao pega (medido: o 2o turno reusava o adaptador do 1o).
     *
     * @param list<array{in:int,out:int,texto:string}> $turnos
     */
    public function __construct(array $turnos)
    {
        $this->turnos = $turnos;
    }

    private function turnoAtual(): array
    {
        if (! isset($this->turnos[$this->chamada])) {
            throw new \RuntimeException('Fake sem turno para a chamada #' . $this->chamada);
        }

        return $this->turnos[$this->chamada];
    }

    public function gerarBriefing(ContextoNegocio $ctx): string
    {
        return '';
    }

    public function sugerirMetas(ContextoNegocio $ctx, string $prompt): array
    {
        return [];
    }

    public function responderChat(Conversa $conv, string $mensagem): string
    {
        $this->ultimoUso = ['tokens_in' => null, 'tokens_out' => null];

        $turno = $this->turnoAtual();
        $this->chamada++;
        $this->produzidos[] = $turno['texto'];

        // Mesma posicao do driver real: o usage so existe quando o metodo esta
        // pra retornar — ou seja ANTES do controller criar a mensagem assistant.
        $this->ultimoUso = ['tokens_in' => $turno['in'], 'tokens_out' => $turno['out']];

        return $turno['texto'];
    }

    public function responderChatStream(Conversa $conv, string $mensagem): \Generator
    {
        $this->ultimoUso = ['tokens_in' => null, 'tokens_out' => null];

        $turno = $this->turnoAtual();
        $this->chamada++;
        $this->produzidos[] = $turno['texto'];

        foreach (str_split($turno['texto'], 4) as $chunk) {
            yield $chunk;
        }

        // PROPRIEDADE ESTRUTURAL QUE IMPORTA: igual aos drivers reais, o usage so
        // fica conhecido DEPOIS do ultimo yield — ou seja, este trecho roda
        // durante o next() do foreach do caller, ANTES do controller criar a
        // mensagem assistant do turno. E exatamente por isso que o driver nao
        // pode gravar: ele so EXPOE, e quem grava e o caller.
        $this->ultimoUso = ['tokens_in' => $turno['in'], 'tokens_out' => $turno['out']];
    }

    /** {@inheritDoc} */
    public function ultimoResultadoStream(): array
    {
        return [
            'path' => 'llm',
            'status' => 'ok',
            'cache_hit' => false,
            'recall_count' => 0,
            'jobs_dispatched' => 0,
            'error_class' => null,
        ];
    }

    /** {@inheritDoc} */
    public function ultimoUsoTokens(): array
    {
        return $this->ultimoUso;
    }
}

/**
 * Dispara um turno de streaming completo.
 *
 * NAO usa TestResponse::streamedContent(): o controller mata TODOS os buffers de
 * saida de proposito pra SSE real-time (ChatController.php:385-387), o que faz o
 * ob_get_clean() do helper do Laravel estourar "No buffer to delete". Entao a
 * execucao e disparada na mao, com guarda de nivel de buffer.
 */
function enviarTurnoStream(object $test, User $user, Conversa $conv, string $texto): void
{
    $resp = $test->actingAs($user)
        ->withoutMiddleware()
        ->post(route('jana.conversas.mensagens.stream', $conv->id), ['content' => "pergunta {$texto}"]);

    $resp->assertOk();

    $nivel = ob_get_level();
    ob_start();
    try {
        $resp->baseResponse->sendContent();
    } finally {
        while (ob_get_level() > $nivel) {
            @ob_end_clean();
        }
        // O controller derruba TODOS os buffers (inclusive o do PHPUnit). Sem
        // restaurar o nivel, o PHPUnit marca o teste como "risky" por buffer
        // que ele abriu e nao existe mais no teardown.
        while (ob_get_level() < $nivel) {
            ob_start();
        }
    }
}

/**
 * Caminho BLOQUEANTE (ChatController::send -> AiAdapter::responderChat).
 * Nao ha generator aqui: o metodo simplesmente RETORNA antes do controller
 * criar a mensagem assistant (send() chama em :335 e cria em :341) — mesmo
 * deslocamento de um turno, por outro mecanismo.
 */
function enviarTurnoBloqueante(object $test, User $user, Conversa $conv): void
{
    $test->actingAs($user)
        ->withoutMiddleware()
        ->post(route('jana.conversas.mensagens.store', $conv->id), ['content' => 'pergunta'])
        ->assertRedirect();
}

it('R-COPI-TOK-001 — tokens do turno N gravam na mensagem assistant do turno N', function () {
    $user = User::factory()->create(['business_id' => 1]);

    $conv = Conversa::create([
        'business_id' => 1,
        'user_id'     => $user->id,
        'titulo'      => 'teste-tokens-turno',
        'status'      => 'ativa',
    ]);

    $fake = new FakeStreamAdapterTokens([
        ['in' => 111, 'out' => 222, 'texto' => 'alfa'],   // turno 1
        ['in' => 333, 'out' => 444, 'texto' => 'beta'],   // turno 2
    ]);
    app()->instance(AiAdapter::class, $fake);

    // Turno 1 — nao existe assistant anterior nesta conversa.
    enviarTurnoStream($this, $user, $conv, 'alfa');

    // Turno 2 — ja existe a assistant do turno 1.
    enviarTurnoStream($this, $user, $conv, 'beta');

    // ---------- PRE-CONDICOES ANTI-VACUO (LC-13) ----------
    // Sem isto, "0 failed" poderia significar "nada rodou". O `content` so fica
    // preenchido se o generator REALMENTE rendeu os chunks e o controller os
    // acumulou — logo ele prova execucao, nao so existencia de linha.
    expect($fake->produzidos)->toBe(['alfa', 'beta']);
    expect(Mensagem::where('conversa_id', $conv->id)->where('role', 'user')->count())->toBe(2);

    $assistants = Mensagem::where('conversa_id', $conv->id)
        ->where('role', 'assistant')
        ->orderBy('id')
        ->get();

    expect($assistants)->toHaveCount(2);
    expect($assistants[0]->content)->toBe('alfa');
    expect($assistants[1]->content)->toBe('beta');

    // ---------- CONTRATO ----------
    // Turno 1 gravou no turno 1.
    expect((int) $assistants[0]->tokens_in)->toBe(111);
    expect((int) $assistants[0]->tokens_out)->toBe(222);

    // Turno 2 gravou no turno 2 (e NAO retroagiu pro turno 1).
    expect((int) $assistants[1]->tokens_in)->toBe(333);
    expect((int) $assistants[1]->tokens_out)->toBe(444);
});

it('R-COPI-TOK-002 — idem no caminho BLOQUEANTE (send -> responderChat)', function () {
    $user = User::factory()->create(['business_id' => 1]);

    $conv = Conversa::create([
        'business_id' => 1,
        'user_id'     => $user->id,
        'titulo'      => 'teste-tokens-turno-blocking',
        'status'      => 'ativa',
    ]);

    $fake = new FakeStreamAdapterTokens([
        ['in' => 555, 'out' => 666, 'texto' => 'gama'],   // turno 1
        ['in' => 777, 'out' => 888, 'texto' => 'delta'],  // turno 2
    ]);
    app()->instance(AiAdapter::class, $fake);

    enviarTurnoBloqueante($this, $user, $conv);
    enviarTurnoBloqueante($this, $user, $conv);

    // ---------- PRE-CONDICOES ANTI-VACUO (LC-13) ----------
    expect($fake->produzidos)->toBe(['gama', 'delta']);
    expect(Mensagem::where('conversa_id', $conv->id)->where('role', 'user')->count())->toBe(2);

    $assistants = Mensagem::where('conversa_id', $conv->id)
        ->where('role', 'assistant')
        ->orderBy('id')
        ->get();

    expect($assistants)->toHaveCount(2);
    expect($assistants[0]->content)->toBe('gama');
    expect($assistants[1]->content)->toBe('delta');

    // ---------- CONTRATO ----------
    expect((int) $assistants[0]->tokens_in)->toBe(555);
    expect((int) $assistants[0]->tokens_out)->toBe(666);
    expect((int) $assistants[1]->tokens_in)->toBe(777);
    expect((int) $assistants[1]->tokens_out)->toBe(888);
});
