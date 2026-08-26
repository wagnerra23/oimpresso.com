<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Modules\Jana\Entities\Conversa;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * Os DOIS anti-hooks Tier 0 do `Chat.charter.md`, virando Pest GUARD.
 *
 * ── POR QUE ESTE ARQUIVO EXISTE (2026-08-17) ────────────────────────────────
 * O §Automation Anti-hooks do charter lista OITO regras e diz, literalmente,
 * "Vira Pest GUARD". Medido nesta data: **nenhum dos oito** tinha virado. O módulo
 * tinha só `BriefDiarioChatTriggerTest` e `Chat/ChatTokensTurnoTest`, e nenhum
 * guardava estes. O charter ainda carrega uma seção "Métricas vivas (Pest GUARD —
 * a escrever em F1.5)", e o parêntese continuava verdadeiro.
 *
 * Dos oito, dois não são estética — são Tier 0, e são os que este arquivo trava:
 *   1. "Não acessa thread de outro `business_id`"  → multi-tenant, ADR 0093
 *   2. "Não dispara emails/SMS ao abrir"           → read da thread é PURO
 *
 * ── POR QUE NÃO É TAUTOLÓGICO ───────────────────────────────────────────────
 * As asserções não foram lidas do `ChatController` — vieram do CHARTER, e uma delas
 * já discorda do código: o `show()` guarda por `user_id`
 * (`abort_unless($conversa->user_id === auth()->id(), 403)`), **não** por
 * `business_id`. O charter promete isolamento por BUSINESS. Se o teste passar, o
 * `user_id` está cobrindo o caso na prática; se falhar, ele achou o buraco que o
 * anti-hook descreve. Qualquer um dos dois desfechos é informação — que é o ponto
 * de derivar do contrato e não da implementação (§5 2026-06-05).
 *
 * ── TENANT ──────────────────────────────────────────────────────────────────
 * Tenant fictício 98 e um vizinho 99 (ADR 0358). NUNCA biz=4 — é cliente real.
 *
 * ⚠️ NÃO RODADO LOCAL: Pest é CT 100/CI only (proibicoes.md §Ambiente). Quem prova
 * é o CI. Por isso os UCs em `Chat.casos.md` nascem 🧪, não ✅ — status de teste vem
 * do veredito, nunca da leitura.
 */
function chatTier0Skip(string $motivo): void
{
    test()->markTestSkipped($motivo);
}

/**
 * Cria uma conversa num business, com um dono. Devolve [conversa, dono].
 *
 * Sem factory: o módulo não expõe uma pra `Conversa`, e inventar factory aqui
 * acoplaria o teste a um contrato que não existe.
 */
function chatTier0Conversa(int $businessId, int $userId): Conversa
{
    return Conversa::create([
        'business_id' => $businessId,
        'user_id' => $userId,
        'titulo' => "Conversa de prova biz={$businessId}",
        'status' => 'ativa',
    ]);
}

/**
 * Garante que o usuário ATRAVESSA o gate da rota — e isto é ANTI-VÁCUO, não conveniência.
 *
 * O grupo `/ia` exige `can:jana.access` (`Modules/Jana/Http/routes.php`). Medido em
 * 2026-08-26: **nenhum seeder do repo cria essa permission** (`git grep "jana.access"`
 * em `*Seeder*`: 0 hits, com controle positivo de 14 hits no `JanaAccessGateTest`) e o
 * `pest-mysql-setup` — o seed compartilhado por 16 lanes — **não concede papel nem
 * permissão a usuário nenhum** (0 hits pra `assignRole`/`givePermissionTo`). Num banco
 * FRESCO todo GET em `/ia` volta 403 (é o que o `JanaAccessGateTest` crava no 1º caso),
 * e sem esta concessão os quatro UCs viram isto:
 *
 *   · UC-05 — "não é 200" passa pelo 403 do GATE DE ACESSO, não pelo isolamento de tenant
 *   · UC-06 — não vê e-mail porque não houve render
 *   · UC-07 — não vê escrita porque não houve render
 *   · UC-08 — único que denuncia, reprovando no `assertOk()`
 *
 * Ou seja: três verdes que não provam nada e um vermelho (LC-13 na forma mais cara —
 * verde por NÃO-EXECUÇÃO do que se diz medir).
 *
 * No CT 100 o defeito é INVISÍVEL: a base é PERSISTENTE e os dois usuários já tinham
 * `can('jana.access') === true` (medido na mesma data). É a mesma armadilha que tirou o
 * `ProContractTest` desta lane — ver o comentário do #6312 no `jana-pest.yml`.
 *
 * Receita idêntica à do `JanaAccessGateTest`, que já roda nesta lane. O `DatabaseTransactions`
 * devolve a concessão no teardown. O tenant 98 é FICTÍCIO por decisão ([ADR 0358]) — nunca
 * biz=4.
 */
function chatTier0ComAcesso(\App\User $u): \App\User
{
    \Spatie\Permission\Models\Permission::findOrCreate('jana.access', 'web');
    $u->givePermissionTo('jana.access');
    $u->forgetCachedPermissions();

    return $u;
}

it('UC-JCHAT-05 — não devolve thread de OUTRO business (anti-hook Tier 0 · ADR 0093)', function () {
    $vizinho = \App\User::query()->where('business_id', '!=', 98)->first();
    $dono = \App\User::query()->where('business_id', 98)->first();

    if (! $vizinho || ! $dono) {
        chatTier0Skip('Sem usuário em biz=98 e num business vizinho — o guard só morde com os dois.');
    }

    chatTier0ComAcesso($vizinho);
    chatTier0ComAcesso($dono);

    $alheia = chatTier0Conversa(98, $dono->id);
    $propria = chatTier0Conversa((int) $vizinho->business_id, $vizinho->id);

    // CONTROLE POSITIVO — sem ele este UC é vácuo. Um 403 UNIVERSAL (permissão
    // ausente no banco fresco, módulo desligado, rota caída) faria os três asserts
    // abaixo passarem sem que isolamento nenhum tivesse sido exercitado. Provar
    // primeiro que o vizinho ALCANÇA a própria conversa é o que garante que o 403
    // que sobra só pode vir da guarda de propriedade.
    expect($this->actingAs($vizinho)->get(route('jana.conversas.show', $propria->id))->status())
        ->toBe(200, 'O vizinho não alcança nem a PRÓPRIA conversa — logo o assert abaixo mediria o gate de acesso, não o isolamento de tenant.');

    // O vizinho pede a conversa do OUTRO business pelo id.
    $status = $this->actingAs($vizinho)->get(route("jana.conversas.show", $alheia->id))->status();

    // 403 (negado) ou 404 (nem existe pra ele) servem — o que NÃO pode é 200.
    // Anti-vácuo: se virasse 302 (login), o assert passaria por engano sem provar
    // isolamento nenhum, então o 302 também é reprovado explicitamente.
    expect($status)->not->toBe(200);
    expect($status)->not->toBe(302);
    expect([403, 404])->toContain($status);
});

it('UC-JCHAT-06 — abrir a thread é leitura PURA: zero e-mail, zero notificação', function () {
    $dono = \App\User::query()->where('business_id', 98)->first();

    if (! $dono) {
        chatTier0Skip('Sem usuário em biz=98 — nada a abrir.');
    }

    chatTier0ComAcesso($dono);

    Mail::fake();
    Notification::fake();

    $conversa = chatTier0Conversa(98, $dono->id);

    // O `assertOk()` é ANTI-VÁCUO, não decoração: se o GET voltasse 403 (banco sem a
    // permission — ver `chatTier0ComAcesso`) não haveria render, e "nenhum e-mail
    // enviado" seria verdade por nada ter acontecido.
    $this->actingAs($dono)->get(route("jana.conversas.show", $conversa->id))->assertOk();

    // O charter: "Não dispara emails ao abrir (read da thread é puro)" +
    // "Não dispara SMS". Abrir uma conversa é consulta — quem manda mensagem é o
    // POST, e é lá que efeito colateral pode existir.
    Mail::assertNothingSent();
    Notification::assertNothingSent();
});

it('UC-JCHAT-07 — o render inicial NÃO escreve no banco de mensagens', function () {
    $dono = \App\User::query()->where('business_id', 98)->first();

    if (! $dono) {
        chatTier0Skip('Sem usuário em biz=98 — nada a abrir.');
    }

    chatTier0ComAcesso($dono);

    $conversa = chatTier0Conversa(98, $dono->id);
    $antes = \Illuminate\Support\Facades\DB::table('jana_mensagens')
        ->where('conversa_id', $conversa->id)->count();

    // ANTI-VÁCUO, mesma razão do UC-06: sem render (403) a contagem ficaria igual
    // por nada ter rodado, e o UC declararia "não escreve" sobre uma página negada.
    $this->actingAs($dono)->get(route('jana.conversas.show', $conversa->id))->assertOk();

    $depois = \Illuminate\Support\Facades\DB::table('jana_mensagens')
        ->where('conversa_id', $conversa->id)->count();

    // Charter: "⛔ Não escreve no banco no render inicial (só no POST de mensagem)".
    // Conta ANTES e DEPOIS em vez de assertar zero: a conversa pode nascer com
    // mensagem de sistema, e o contrato é sobre o GET não ACRESCENTAR — não sobre
    // a thread estar vazia.
    expect($depois)->toBe($antes);
});

it('UC-JCHAT-08 — o render NÃO chama o Brain B, e NÃO vaza credencial pro client', function () {
    $dono = \App\User::query()->where('business_id', 98)->first();

    if (! $dono) {
        chatTier0Skip('Sem usuário em biz=98 — nada a abrir.');
    }

    chatTier0ComAcesso($dono);

    // Qualquer chamada HTTP de saída no render vira falha: o charter diz que o
    // Brain B só é acionado APÓS submit do usuário.
    \Illuminate\Support\Facades\Http::preventStrayRequests();

    // ── POR QUE O SSR DO INERTIA É STUBADO (2026-08-26) ────────────────────────
    // A sonda acima é mais LARGA que o contrato que ela guarda: `preventStrayRequests()`
    // barra QUALQUER saída HTTP, e o render do Inertia faz um POST legítimo de
    // INFRAESTRUTURA pro servidor de SSR — `Inertia\Ssr\HttpGateway::dispatch()` chama
    // `Http::post(config('inertia.ssr.url').'/render')` e RE-LANÇA o StrayRequestException
    // em vez de engolir. Medido nesta data: era esse POST que reprovava o UC, NUNCA o
    // Brain B. E o estouro acontecia no `assertOk()` logo abaixo, ANTES das asserts de
    // credencial — que por isso nunca chegaram a rodar.
    //
    // Não é peculiaridade de ambiente: `inertia.ssr.enabled` é `true` por default
    // (config/inertia.php) e o `Inertia\Ssr\BundleDetector` aceita `public/js/app.js`
    // — asset legado do UltimatePOS, VERSIONADO no repo — como bundle de SSR, logo
    // `bundleExists()` é true em qualquer checkout, CI incluído.
    //
    // O stub ESTREITA a sonda até o contrato, não a afrouxa: só a URL do SSR passa, e
    // segue sendo falha qualquer outra saída — Brain B incluído. A URL vem da config
    // (não hardcoded) pra não apodrecer se `INERTIA_SSR_URL` mudar.
    // ⛔ NÃO trocar por remover o `preventStrayRequests()`, por desligar
    //    `inertia.ssr.enabled`, nem por skip: seria afrouxar um anti-hook Tier 0.
    //
    // O corpo VAZIO da resposta stubada é deliberado: `HttpGateway` faz `$response->json()`,
    // recebe null e devolve null — o Inertia então cai no render client-side e o corpo
    // servido carrega o `data-page` com os props REAIS, que é o que as asserts de
    // credencial precisam examinar. Um stub com JSON de SSR válido faria o corpo virar o
    // markup (vazio) do SSR, e as asserts `not->toContain` passariam VACUAMENTE.
    $ssrUrl = rtrim((string) config('inertia.ssr.url', 'http://127.0.0.1:13714'), '/');
    \Illuminate\Support\Facades\Http::fake([
        $ssrUrl.'/*' => \Illuminate\Support\Facades\Http::response('', 200),
    ]);

    $conversa = chatTier0Conversa(98, $dono->id);
    $resposta = $this->actingAs($dono)->get(route('jana.conversas.show', $conversa->id));

    $resposta->assertOk();

    // Charter: "⛔ Não persiste credencial Brain B no client (token vive no backend)".
    // O corpo servido não pode conter a chave — nem o valor dela, se estiver
    // configurada. Testa os dois: o NOME (que denunciaria a prop trafegando) e o
    // VALOR (que é o vazamento de fato).
    $corpo = $resposta->getContent() ?: '';

    // Anti-vácuo: as duas asserts abaixo são `not->toContain`, logo passariam num corpo
    // em branco. Esta prova que o payload do Inertia de fato chegou ao corpo — senão o
    // UC estaria declarando "não vaza" sobre uma página vazia.
    expect($corpo)->toContain('data-page');

    expect($corpo)->not->toContain('ANTHROPIC_API_KEY');

    $token = (string) config('services.anthropic.key', '');
    if ($token !== '') {
        expect($corpo)->not->toContain($token);
    }
});
