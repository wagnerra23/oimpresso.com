<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Modules\Jana\Entities\Conversa;
use Modules\Jana\Entities\Mensagem;

// `Tests\TestCase::class` NAO e opcional aqui: o `tests/Pest.php` so vincula o
// TestCase em `->in('Feature')`, que e a pasta RAIZ tests/Feature — nao alcanca
// `Modules/*/Tests/Feature`. Sem ele o container do Laravel nao sobe e o primeiro
// toque no banco estoura "Cannot use object of type ...Config as array", que foi
// exatamente como este arquivo reprovou na primeira run (PR #5901).
uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * UC-COPI-CHAT-12 — o card da conversa recebe `preview` e `ultima_em`.
 *
 * ── DE ONDE VEM O CONTRATO ──────────────────────────────────────────────────
 * Do protótipo `prototipo-ui/cowork/jana-merge.jsx` §`JmConversa`, que desenha o
 * card com resumo de uma linha (`t.preview`) e um tempo (`t.quando` → "última em
 * X" / "criada agora"), e do `Chat.casos.md`. NÃO foi lido do Controller — o
 * Controller é o que este teste mede, e derivar dele seria tautológico
 * (§5 2026-06-05).
 *
 * ── POR QUE ISTO É BACKEND, E NÃO PIXEL ─────────────────────────────────────
 * Medido em 2026-08-17: o card mostrava só o título porque o payload não tinha o
 * dado — `buildConversasListPayload` mandava `id · titulo · unread · origem ·
 * status · ativa`. E não é coluna esquecida: `jana_conversas` não guarda preview
 * nem última atividade. `iniciada_em` NÃO serve — é quando a conversa nasceu, não
 * quando falaram nela pela última vez. Por isso o dado sai de `jana_mensagens`.
 *
 * ── O QUE ESTE TESTE NÃO PROVA ──────────────────────────────────────────────
 * Que a tela DESENHA os campos. Ele para no payload de propósito: renderizar muda
 * pixel e a baseline do visreg de `Jana/Chat`, que é outro PR e aprovação [W].
 * Prop que chega e ninguém lê é degrau consciente, não sobra.
 *
 * ── TENANT ──────────────────────────────────────────────────────────────────
 * Tenant fictício 98 (ADR 0358). NUNCA biz=4 — é cliente real.
 *
 * ⚠️ NÃO RODADO LOCAL: Pest é CT 100/CI only (proibicoes.md §Ambiente). Quem prova
 * é o CI — status de teste vem do veredito, nunca da leitura.
 */
function chatPreviewSkip(string $motivo): void
{
    test()->markTestSkipped($motivo);
}

/**
 * Versao do asset que o Inertia espera — MESMA conta do app.
 *
 * `HandleInertiaRequests::version()` devolve `md5_file()` do manifest quando ele
 * existe. Sem mandar isto no header, o middleware responde **409** (version
 * mismatch, "recarrega a pagina inteira") em vez de 200, e o teste reprova por
 * um motivo que nao tem nada a ver com o payload — foi assim que este arquivo
 * reprovou na segunda run do PR #5901. A lane stuba o manifest num step
 * dedicado, entao aqui o ramo do `file_exists` e deterministico.
 */
function chatPreviewVersaoInertia(): string
{
    $manifest = public_path('build-inertia/manifest.json');

    return file_exists($manifest) ? md5_file($manifest) : '1';
}

/** Pede a lista deferida do Chat como o `<Deferred>` do front pede. */
function chatPreviewConversas($teste, $user): array
{
    $resposta = $teste->actingAs($user)->get(route('jana.chat.index'), [
        'X-Inertia'                   => 'true',
        'X-Inertia-Version'           => chatPreviewVersaoInertia(),
        'X-Inertia-Partial-Component' => 'Jana/Chat',
        'X-Inertia-Partial-Data'      => 'conversas',
    ]);

    // 409 aqui NAO seria "o payload esta errado" — seria o Inertia mandando
    // recarregar. Asserir 200 explicitamente impede que a diferenca passe como
    // se fosse ausencia de dado.
    $resposta->assertStatus(200);

    return json_decode($resposta->getContent(), true)['props']['conversas'] ?? [];
}

it('UC-COPI-CHAT-12 — `preview` traz a ÚLTIMA mensagem, não a primeira', function () {
    $dono = \App\User::query()->where('business_id', 98)->first();

    if (! $dono) {
        chatPreviewSkip('Sem usuário em biz=98 — o tenant fictício da ADR 0358 não está semeado.');
    }

    $conversa = Conversa::create([
        'business_id' => 98,
        'user_id'     => $dono->id,
        'titulo'      => 'Conversa com histórico',
        'status'      => 'ativa',
    ]);

    // Append-only: a ordem de criação É a ordem cronológica.
    Mensagem::create(['conversa_id' => $conversa->id, 'role' => 'user', 'content' => 'PRIMEIRA pergunta']);
    Mensagem::create(['conversa_id' => $conversa->id, 'role' => 'assistant', 'content' => 'ULTIMA resposta da Jana']);

    $conversas = chatPreviewConversas($this, $dono);
    $card = collect($conversas['recentes'] ?? [])->firstWhere('id', (string) $conversa->id);

    expect($card)->not->toBeNull();
    // O contrato é o VALOR, não a presença da chave: preview da PRIMEIRA passaria
    // num assert de `toHaveKey` e estaria errado.
    expect($card['preview'])->toBe('ULTIMA resposta da Jana');
    expect($card['ultima_em'])->not->toBeNull();
});

it('UC-COPI-CHAT-12 — conversa SEM mensagem devolve preview nulo, não string vazia nem erro', function () {
    $dono = \App\User::query()->where('business_id', 98)->first();

    if (! $dono) {
        chatPreviewSkip('Sem usuário em biz=98 — o tenant fictício da ADR 0358 não está semeado.');
    }

    $vazia = Conversa::create([
        'business_id' => 98,
        'user_id'     => $dono->id,
        'titulo'      => 'Conversa recém-criada',
        'status'      => 'ativa',
    ]);

    $conversas = chatPreviewConversas($this, $dono);
    $card = collect($conversas['recentes'] ?? [])->firstWhere('id', (string) $vazia->id);

    expect($card)->not->toBeNull();
    // `null` é o sinal que deixa o front escolher a frase ("Sem mensagens ainda" /
    // "criada agora"). String vazia obrigaria o front a adivinhar entre "não tem" e
    // "tem e é vazia".
    expect($card['preview'])->toBeNull();
    expect($card['ultima_em'])->toBeNull();
});

it('UC-COPI-CHAT-12 — o preview colapsa quebra de linha e corta em uma linha', function () {
    $dono = \App\User::query()->where('business_id', 98)->first();

    if (! $dono) {
        chatPreviewSkip('Sem usuário em biz=98 — o tenant fictício da ADR 0358 não está semeado.');
    }

    $conversa = Conversa::create([
        'business_id' => 98,
        'user_id'     => $dono->id,
        'titulo'      => 'Conversa com resposta longa',
        'status'      => 'ativa',
    ]);

    Mensagem::create([
        'conversa_id' => $conversa->id,
        'role'        => 'assistant',
        'content'     => "Linha um\n\nLinha dois   com   espaço   demais " . str_repeat('x', 200),
    ]);

    $conversas = chatPreviewConversas($this, $dono);
    $card = collect($conversas['recentes'] ?? [])->firstWhere('id', (string) $conversa->id);

    expect($card)->not->toBeNull();
    expect($card['preview'])->not->toContain("\n");
    expect($card['preview'])->not->toContain('   ');
    // 90 é o teto; o corte acrescenta a reticência, então o comprimento não passa dele.
    expect(mb_strlen($card['preview']))->toBeLessThanOrEqual(90);
    expect($card['preview'])->toStartWith('Linha um Linha dois com espaço demais');
});
