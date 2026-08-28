<?php

declare(strict_types=1);

use Modules\Jana\Http\Controllers\DataController;

uses(Tests\TestCase::class);

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

it('o selo de plano le o TIER, e o gate da area continua lendo o MODULO', function () {
    $middleware = file_get_contents(base_path('app/Http/Middleware/HandleInertiaRequests.php'));

    // Extrai a chave de cada `hasThePermissionInSubscription(...)` do arquivo.
    preg_match_all(
        '/hasThePermissionInSubscription\s*\(\s*[^,]+,\s*[\'"]([a-z0-9_]+_module)[\'"]/i',
        $middleware,
        $m
    );
    $chaves = array_values(array_unique($m[1]));

    // Controle-positivo: regex que para de casar viraria "0 chaves" e passaria calado.
    expect($chaves)->not->toBeEmpty();

    expect($chaves)->toContain('jana_module');      // sidebarShortcuts()['ia']
    expect($chaves)->toContain('jana_pro_module');  // janaPlanoPro()
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
