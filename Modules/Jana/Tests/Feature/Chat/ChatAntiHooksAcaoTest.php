<?php

declare(strict_types=1);

use App\Support\Privacy\PiiRedactor;
use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Modules\Jana\Ai\Agents\ChatCopilotoAgent;
use Modules\Jana\Contracts\AiAdapter;
use Modules\Jana\Entities\Conversa;
use Modules\Jana\Entities\Mensagem;
use Modules\Jana\Services\Telemetry\LangfuseClient;
use Modules\Jana\Support\ContextoNegocio;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * Os DOIS anti-hooks que faltavam do `Chat.charter.md` — os da AÇÃO, não do render.
 *
 * ── POR QUE ESTE ARQUIVO EXISTE (2026-08-17, leva 2) ────────────────────────
 * O §Automation Anti-hooks do charter lista OITO regras e diz "Vira Pest GUARD".
 * A leva 1 (`ChatAntiHooksTier0Test`) virou seis — todos exercitando o **GET**
 * (render da thread). Os dois restantes não cabiam lá porque só se provam
 * quando a Jana **age**:
 *
 *   7. "Não roda tool sem auth check do tool registry (cada tool declara
 *      permission required)"                                     → UC-09
 *   8. "Não loga PII em plain text (sanitizer obrigatório antes de
 *      `jana_audit_log`)"                                        → UC-10
 *
 * ── DOIS ORÁCULOS QUE PRECISARAM SER MEDIDOS, NÃO LIDOS ─────────────────────
 * O charter nomeia duas coisas que **não existem com esse nome**. Escrever o
 * teste contra o nome do charter produziria guarda muda — verde por não medir
 * nada:
 *
 *   (a) **`jana_audit_log` NÃO é tabela.** É o `log_name` do Spatie ActivityLog
 *       (`JanaAuditService::register` chama `activity('jana_audit')`), cujo
 *       destino é a tabela `activity_log`. Pior: o `ChatController` **não chama
 *       o JanaAuditService nenhuma vez** — medido, zero ocorrência de
 *       `JanaAuditService`, `activity(` ou `Log::channel` no arquivo inteiro.
 *       Um assert "activity_log não contém CPF" passaria **vacuamente**, porque
 *       o chat não escreve linha nenhuma lá.
 *
 *       O sink de log que o chat REALMENTE alimenta é o **Langfuse**
 *       (`ChatController:395` `startTrace` e `:550` `endTrace`), e é lá que o
 *       UC-10 mede — o anti-hook fala de PII em plain text num sink de
 *       observabilidade, e este é o sink que existe.
 *
 *   (b) **Não há "tool registry" no Jana.** As 5 tools do chat são lista
 *       hardcoded em `ChatCopilotoAgent::toolsAtivas()`. O registry com
 *       permissão que existe no projeto é o da **Forja**
 *       (`Modules/Forja/Services/ToolRegistry`), que o Jana não consome
 *       (medido: zero referência a `ToolRegistry` em `Modules/Jana`) — e cujo
 *       contrato `Modules/Forja/Contracts/Tool` declara `isReadOnly()`, não
 *       permissão.
 *
 * ── POR QUE NÃO É TAUTOLÓGICO (§5 2026-06-05) ───────────────────────────────
 * Nenhuma asserção foi lida da implementação. As duas vêm do charter, e as duas
 * **discordam do código medido hoje** — é o mesmo desenho que a leva 1 usou no
 * UC-05. Os dois desfechos são informação: se passar, o contrato está cumprido
 * por um caminho que eu não vi; se falhar, o teste achou o buraco que o
 * anti-hook descreve.
 *
 * ── O QUE ESTE ARQUIVO NÃO FAZ (LC-19 — não duplicar dono) ──────────────────
 * `ChatCopilotoAgentToolsTest` (R-COPI-141) já é dono de: flag OFF → zero
 * tools · flag ON → as 5 · o `business_id` repassado da conversa · conversa sem
 * business → zero tools. **Nada disso é re-assertado aqui.** O UC-09 cobre só o
 * eixo que ninguém cobre: cada tool exposta ao LLM declara a permissão que
 * exige.
 *
 * ⚠️ NÃO RODADO LOCAL: Pest é CT 100/CI only (proibicoes.md §Ambiente). Quem
 * prova é o CI — por isso os UCs em `Chat.casos.md` nascem 🧪, nunca ✅.
 *
 * @see resources/js/Pages/Jana/Chat.charter.md §Automation Anti-hooks
 * @see Modules/Jana/Tests/Feature/Chat/ChatAntiHooksTier0Test.php (leva 1 — o GET)
 * @see Modules/Jana/Tests/Feature/Ai/ChatCopilotoAgentToolsTest.php (dono de flag/business)
 */

/**
 * Espião do Langfuse — captura o que o chat MANDA pro sink de observabilidade.
 *
 * Não é mock de conveniência: `LangfuseClient::startTrace` só emite de verdade
 * quando `shouldEmit()` passa (env/flag), então em CI o cliente real seria mudo
 * e o teste não veria payload nenhum. O espião torna o payload observável
 * **sem** depender de o Langfuse estar configurado na lane.
 */
final class EspiaoLangfuseAcaoAntiHook extends LangfuseClient
{
    /** @var list<array<string,mixed>> */
    public array $inicios = [];

    /** @var list<array<string,mixed>> */
    public array $fins = [];

    public function startTrace(array $attrs): string
    {
        $this->inicios[] = $attrs;

        return 'trace-espiao-anti-hook';
    }

    public function endTrace(string $traceId, array $attrs = []): void
    {
        $this->fins[] = $attrs;
    }
}

/**
 * Dublê do AiAdapter — não há LLM em CI.
 *
 * Devolve texto fixo e registra que foi chamado (prova anti-vácuo de que o
 * turno rodou de verdade, e não que o teste mediu ausência de operação).
 */
final class DubleAdapterAcaoAntiHook implements AiAdapter
{
    /** @var list<string> */
    public array $recebidos = [];

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
        $this->recebidos[] = $mensagem;

        return 'resposta do duble';
    }

    public function responderChatStream(Conversa $conv, string $mensagem): \Generator
    {
        $this->recebidos[] = $mensagem;

        yield 'resposta ';
        yield 'do duble';
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
        return ['tokens_in' => 10, 'tokens_out' => 20];
    }
}

/**
 * Dispara um turno de streaming completo.
 *
 * NÃO usa `TestResponse::streamedContent()`: o controller derruba TODOS os
 * buffers de saída de propósito pra SSE real-time, o que faz o `ob_get_clean()`
 * do helper do Laravel estourar "No buffer to delete". Mesma guarda de nível de
 * buffer que o `ChatTokensTurnoTest` já usa — sem ela o PHPUnit marca o teste
 * como "risky" por buffer que ele abriu e não existe mais no teardown.
 */
function acaoAntiHookEnviarStream(object $test, User $user, Conversa $conv, string $texto): void
{
    $resp = $test->actingAs($user)
        ->withoutMiddleware()
        ->post(route('jana.conversas.mensagens.stream', $conv->id), ['content' => $texto]);

    $resp->assertOk();

    $nivel = ob_get_level();
    ob_start();
    try {
        $resp->baseResponse->sendContent();
    } finally {
        while (ob_get_level() > $nivel) {
            @ob_end_clean();
        }
        while (ob_get_level() < $nivel) {
            ob_start();
        }
    }
}

/** Achata um payload aninhado em texto único, pra procurar PII em qualquer nível. */
function acaoAntiHookAchatar(array $payload): string
{
    return (string) json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

it('UC-COPI-CHAT-09 — toda tool exposta ao LLM declara a permissão que exige (anti-hook do tool registry)', function () {
    // A flag é o único jeito de as tools existirem (ADR 0245 — default OFF em
    // prod). Sem ligar, `toolsAtivas()` devolve [] e o laço abaixo passaria sem
    // examinar NADA: o vácuo clássico de "todas as zero tools estão corretas".
    config()->set('copiloto.chat_tools.enabled', true);

    $conversa = new Conversa(['business_id' => Tests\TestCase::SEEDED_TENANT_ID]);
    $tools = iterator_to_array((new ChatCopilotoAgent($conversa))->tools(), false);

    // ---------- PRÉ-CONDIÇÃO ANTI-VÁCUO (LC-13) ----------
    // Sem isto, "0 failed" poderia significar "não havia tool pra examinar".
    // O número 5 NÃO é o contrato deste teste (esse é do R-COPI-141-002) — é a
    // prova de que existe superfície pra medir.
    expect($tools)->not->toBeEmpty('Sem tool ativa não há o que auditar — o guard seria mudo.');
    expect($tools)->toHaveCount(5);

    // ---------- CONTRATO (charter §Anti-hooks) ----------
    // "cada tool declara permission required". Aceita QUALQUER forma plausível
    // de declaração: o achado que interessa é a ausência total, não o nome que
    // alguém escolheria. Método OU constante, com valor não-vazio.
    $formasMetodo = ['permission', 'permissao', 'requiredPermission', 'permissionRequired'];
    $formasConstante = ['PERMISSION', 'PERMISSAO', 'REQUIRED_PERMISSION'];

    $semDeclaracao = [];

    foreach ($tools as $tool) {
        $declara = false;

        foreach ($formasMetodo as $metodo) {
            if (method_exists($tool, $metodo) && filled($tool->{$metodo}())) {
                $declara = true;
                break;
            }
        }

        if (! $declara) {
            foreach ($formasConstante as $constante) {
                if (defined($tool::class . '::' . $constante) && filled(constant($tool::class . '::' . $constante))) {
                    $declara = true;
                    break;
                }
            }
        }

        if (! $declara) {
            $semDeclaracao[] = class_basename($tool);
        }
    }

    expect($semDeclaracao)->toBe([], sprintf(
        'O charter promete que cada tool declara a permissão exigida. Sem declaração: %s. '
        . 'Medido em 2026-08-17: as tools do chat implementam Laravel\\Ai\\Contracts\\Tool '
        . '(description/handle/schema) e nenhuma expõe permissão — o gate hoje é só a flag + '
        . 'o business_id da conversa, ambos já cobertos por R-COPI-141. Se este teste está '
        . 'vermelho, o anti-hook descreve um controle que não existe: fechar exige decidir [W] '
        . 'entre implementar a permissão por tool ou revogar a linha do charter.',
        implode(', ', $semDeclaracao) ?: '(nenhuma)'
    ));
});

it('UC-COPI-CHAT-10 — o turno NÃO manda PII em plain text pro sink de log (anti-hook do sanitizer)', function () {
    $tenant = $this->seededTenant();
    $user = User::factory()->create(['business_id' => $tenant->id]);

    $conversa = Conversa::create([
        'business_id' => $tenant->id,
        'user_id' => $user->id,
        'titulo' => 'anti-hook PII no sink de log',
        'status' => 'ativa',
    ]);

    $espiao = new EspiaoLangfuseAcaoAntiHook();
    $duble = new DubleAdapterAcaoAntiHook();
    app()->instance(LangfuseClient::class, $espiao);
    app()->instance(AiAdapter::class, $duble);

    // CPF sintético — nunca dado real. Mesmo valor que o corpus de teste do
    // projeto já usa (LgpdComplianceTest, PiiLeakActivityLogEnforceTest).
    $cpf = '123.456.789-09'; # pii-allowlist
    $mensagem = "o cliente do CPF {$cpf} pediu a segunda via";

    acaoAntiHookEnviarStream($this, $user, $conversa, $mensagem);

    // ---------- PRÉ-CONDIÇÕES ANTI-VÁCUO (LC-13) ----------
    // (1) O turno rodou: o adapter recebeu a mensagem e a user foi persistida.
    expect($duble->recebidos)->toBe([$mensagem], 'O turno não chegou ao adapter — nada foi exercitado.');
    expect(Mensagem::where('conversa_id', $conversa->id)->where('role', 'user')->count())->toBe(1);

    // (2) O sink foi acionado. Sem isto, "nenhum payload contém CPF" seria
    //     verdade por não existir payload — exatamente o vácuo que derrubaria
    //     a versão deste teste escrita contra `jana_audit_log` (ver docblock).
    expect($espiao->inicios)->not->toBeEmpty('O chat não abriu trace — o guard não teria o que medir.');

    // (3) O CPF da fixture é RECONHECÍVEL como PII pelo sanitizer canônico.
    //     Vácuo sutil: se o PiiRedactor não casasse este padrão, o assert final
    //     passaria por o dado nunca ter sido PII aos olhos do projeto.
    expect(str_contains(app(PiiRedactor::class)->redact($mensagem), $cpf))
        ->toBeFalse('A fixture precisa ser PII reconhecida pelo sanitizer, senão o contrato não é exercitado.');

    // ---------- CONTRATO (charter §Anti-hooks) ----------
    // "Não loga PII em plain text (sanitizer obrigatório antes do audit)".
    // Procura o CPF cru em TODO o payload, em qualquer nível de aninhamento.
    // `str_contains(...)->toBeFalse(msg)` e NÃO `not->toContain($cpf, msg)`: o
    // `toContain` é variádico em needles, então a mensagem viraria um SEGUNDO
    // needle e o assert mudaria de sentido calado (§5 2026-07-28, PR #4918).
    foreach ($espiao->inicios as $i => $attrs) {
        expect(str_contains(acaoAntiHookAchatar($attrs), $cpf))->toBeFalse(sprintf(
            'startTrace #%d levou o CPF em plain text. Medido em 2026-08-17: '
            . 'ChatController:401 passa `input => $userInput` cru, sem PiiRedactor no caminho. '
            . 'Se este teste está vermelho, ele achou vazamento real de PII pra observabilidade — '
            . 'o conserto é redigir antes de montar o payload, não afrouxar o assert.',
            $i
        ));
    }

    foreach ($espiao->fins as $i => $attrs) {
        expect(str_contains(acaoAntiHookAchatar($attrs), $cpf))->toBeFalse(sprintf(
            'endTrace #%d levou o CPF em plain text (o `output` do turno ecoa o que o modelo '
            . 'devolveu, e o modelo repete o dado do usuário).',
            $i
        ));
    }
});
