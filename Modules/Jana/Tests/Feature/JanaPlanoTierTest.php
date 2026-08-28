<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Modules\Jana\Http\Controllers\DataController;

// DatabaseTransactions porque o ultimo caso ESCREVE em `subscriptions` pra medir o
// comportamento nos dois estados (com e sem a chave). Sem isso o teste deixaria o
// pacote alterado no banco da lane.
uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * O TIER da Jana (`jana_pro_module`) é um eixo SEPARADO do módulo (`jana_module`).
 *
 * ## Por que existe
 *
 * Em 2026-08-27 [W] marcou `jana_module` no Superadmin, viu a Jana acender e concluiu
 * *"jana pro esta ativa"*. Era razoável e era um eixo errado: aquela chave é BINÁRIA
 * (*o business tem a Jana*), não um plano. A medição em produção fechou o diagnóstico —
 * nenhuma chave de tier na assinatura, nenhuma coluna, nenhuma tabela — e está no
 * `PARIDADE-area-jana-diagnostico-e-ondas.md` §8.1 (reforço de evidência).
 *
 * A confusão foi de PESSOA lendo o painel, então o que este teste defende não é a
 * existência de uma string: é que os dois eixos permaneçam **distintos e ambos
 * alcançáveis**. Fundi-los — um só checkbox servindo de módulo e de plano — reproduziria
 * o engano dentro do código, e aí nenhum humano teria como notar.
 *
 * ## Honestidade sobre a forma
 *
 * O 1º caso é comportamental (chama o método e lê o retorno). Os outros dois são de
 * FONTE, e digo por quê em vez de disfarçar: a chave que o middleware passa é um literal
 * dentro de um `try`, e exercitá-la ao vivo exigiria montar business + subscription +
 * package_details + sessão — fixture que provaria o Laravel, não o acordo. Mesma escolha
 * (e mesma justificativa) do `JanaModuleChaveCanonicaTest`, que é o irmão deste no eixo
 * do módulo.
 *
 * Cobre: UC-JPAIN-17 · UC-JCHAT-13 · UC-MEM-06 (o selo de plano lê o pacote, não o cliente).
 *
 * @see memory/requisitos/Jana/PARIDADE-area-jana-diagnostico-e-ondas.md §8.1
 * @see memory/decisions/0140-jana-pro-produto-comercial-saas.md (billing = Sprint JANA-B)
 */
it('o painel de pacotes declara os DOIS eixos, e o tier nao substitui o modulo', function () {
    $declarado = (new DataController())->superadmin_package();

    $chaves = array_column($declarado, 'name');

    // Controle: se o método voltar vazio, os `toContain` abaixo passariam por vacuidade.
    expect($chaves)->not->toBeEmpty();

    expect($chaves)->toContain('jana_module');      // binário — o business tem a Jana
    expect($chaves)->toContain('jana_pro_module');  // tier — o plano dentro dela

    // O que de fato importa: são DUAS caixas, não uma renomeada. Se alguém trocar o
    // `jana_module` por `jana_pro_module` "pra simplificar", quem tem a Jana perde a área.
    expect($chaves)->toHaveCount(count(array_unique($chaves)));
    expect(count($chaves))->toBeGreaterThanOrEqual(2);
})->group('jana');

it('o selo NAO usa hasThePermissionInSubscription — aquele metodo tem bypass de superadmin', function () {
    $middleware = file_get_contents(base_path('app/Http/Middleware/HandleInertiaRequests.php'));

    $ok = preg_match(
        '/private function janaPlanoPro\(int \$businessId\): bool\s*\{(.+?)\n    \}/s',
        $middleware,
        $corpo
    );
    expect($ok)->toBe(1, 'metodo janaPlanoPro nao encontrado — o assert abaixo mediria o vazio');

    // O bypass: `ModuleUtil::hasThePermissionInSubscription()` abre com
    // `if (auth()->user()->can('superadmin')) return true`. Correto pro que ELE responde
    // (*este usuario pode ver o modulo?*); errado pro que o SELO afirma (*qual o plano
    // deste business?*). Medido em prod 2026-08-28: devolveu true pra chave AUSENTE e
    // ate pra chave inventada, quando o logado era superadmin.
    expect($corpo[1])->not->toContain('hasThePermissionInSubscription');
    expect($corpo[1])->toContain('active_subscription');

    // O eixo do MODULO continua usando o metodo — la o bypass e desejado.
    expect($middleware)->toContain("'jana_module'");
})->group('jana');

it('o fail-safe do tier e false — na duvida o header diz Gratis, nunca Pro', function () {
    $middleware = file_get_contents(base_path('app/Http/Middleware/HandleInertiaRequests.php'));

    // Isola o corpo de janaPlanoPro() pra não medir o catch dos vizinhos, cujo
    // back-compat é o oposto (`shortcuts` degrada pra `true`, e está certo lá).
    $ok = preg_match(
        '/private function janaPlanoPro\(int \$businessId\): bool\s*\{(.+?)\n    \}/s',
        $middleware,
        $corpo
    );

    expect($ok)->toBe(1, 'metodo janaPlanoPro nao encontrado — o assert abaixo mediria o vazio');

    // Afirmar Pro para quem não é custa caro (promete recurso pago); dizer Grátis para
    // quem é, não. O degrade tem de cair pro lado barato.
    expect($corpo[1])->toContain('return false;');
    expect($corpo[1])->not->toContain('return true;');
})->group('jana');

it('superadmin SEM a chave no pacote NAO ve Pro — o plano e do business, nao de quem olha', function () {
    $business = \App\Business::query()->first();
    if (! $business) {
        test()->markTestSkipped('Sem business no banco — rode o seeder antes.');
    }
    if (! class_exists(\Modules\Superadmin\Entities\Subscription::class)) {
        test()->markTestSkipped('Modulo Superadmin ausente.');
    }

    // Chama o metodo REAL (privado) — e o comportamento, nao a fonte. Foi esta lacuna
    // que deixou o bypass passar: a 1a versao deste arquivo so assertava STRINGS.
    $chamar = function (int $bizId): bool {
        $mw = new \App\Http\Middleware\HandleInertiaRequests();
        $m  = new \ReflectionMethod($mw, 'janaPlanoPro');
        $m->setAccessible(true);

        return (bool) $m->invoke($mw, $bizId);
    };

    $sub = \Modules\Superadmin\Entities\Subscription::active_subscription($business->id);
    if (empty($sub)) {
        test()->markTestSkipped('Business sem assinatura ativa — nada a medir.');
    }

    $detalhes = (array) ($sub->package_details ?? []);
    $original = $detalhes;

    // (a) SEM a chave -> false, mesmo com um superadmin logado.
    unset($detalhes['jana_pro_module']);
    $sub->package_details = $detalhes;
    $sub->save();

    $superadmin = \App\User::query()->where('business_id', $business->id)->get()
        ->first(fn ($u) => $u->can('superadmin'));

    if ($superadmin) {
        auth()->login($superadmin);
    }

    expect($chamar((int) $business->id))->toBeFalse();

    // (b) COM a chave -> true. O controle positivo: sem ele, um metodo que devolvesse
    // `false` sempre passaria no assert acima por acidente.
    $detalhes['jana_pro_module'] = '1';
    $sub->package_details = $detalhes;
    $sub->save();

    expect($chamar((int) $business->id))->toBeTrue();

    // Restaura o estado exato — o teste roda em DatabaseTransactions, mas o rollback
    // nao e desculpa pra deixar escrita pendurada.
    $sub->package_details = $original;
    $sub->save();

    if ($superadmin) {
        auth()->logout();
    }
})->group('jana');
