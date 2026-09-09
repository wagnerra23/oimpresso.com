<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;

/**
 * Todo comando que o SuperadminServiceProvider AGENDA precisa EXISTIR no Artisan.
 *
 * Origem (2026-09-09, medido em prod SHA a0db7b0177): `pos:sendSubscriptionExpiryAlert`
 * estava agendado por `registerScheduleCommands()` desde sempre e NUNCA esteve no
 * `commands([...])` do mesmo provider. Resultado: o cron das 00:00 morria em
 * CommandNotFoundException — 410 linhas no laravel.log — e o alerta de expiração de
 * assinatura jamais saiu. O defeito é irmão do que já tinha sido consertado no mesmo
 * arquivo para o `superadmin:health` (Wave 23 D9.c), sem que ninguém medisse o vizinho.
 *
 * O ORÁCULO É O REGISTRY, não o disco: `Artisan::all()`, nunca `class_exists()` nem
 * `app(X::class)` — os dois respondem "a classe existe", que é outra pergunta, e é
 * exatamente como a classe LC-11 se disfarça de cobertura (§5 2026-07-28).
 *
 * ⚠️ Escopo honesto: este teste cobre os comandos DESTE provider, nomeados. A régua
 * geral ("nenhum comando agendado é fantasma") não cabe no CI, porque o bloco de
 * schedule do Superadmin é `if ($env === 'live')` — em `testing` ele nem existe, e um
 * teste que enumerasse o Schedule aqui nasceria mudo, que é pior que ausente. A régua
 * geral vive onde o schedule é real: em produção, no `jana:health-check`.
 */
test('os comandos agendados pelo SuperadminServiceProvider existem no Artisan', function (string $comando) {
    // assertArrayHasKey, NÃO expect()->toHaveKey($c, $msg): o 2º argumento do toHaveKey
    // é o VALOR esperado, não mensagem — o assert passaria a comparar o Command com uma
    // string e reprovaria pelo motivo errado (§5 2026-09-05).
    $this->assertArrayHasKey(
        $comando,
        Artisan::all(),
        "O comando `{$comando}` é agendado pelo SuperadminServiceProvider mas NÃO está "
        . 'registrado no Artisan. Em produção o cron dele morre em CommandNotFoundException '
        . 'sem falhar nada visível. Registre a classe no `commands([...])` do provider.'
    );
})->with([
    // Os dois que `registerScheduleCommands()` agenda (`if ($env === 'live')`).
    'pos:sendSubscriptionExpiryAlert',
    'paymentgateway:emit-trial-expired',
]);

/**
 * CONTROLE NEGATIVO — prova que o assert acima sabe reprovar.
 *
 * Sem isto, um `toHaveKey` sobre um array grande passa por qualquer motivo e o teste
 * vira carimbo: o nome promete "existe no Artisan" e o assert só provaria que o array
 * não está vazio.
 */
test('CONTROLE NEGATIVO — comando inexistente NÃO aparece no registry', function () {
    expect(Artisan::all())->not->toHaveKey('pos:comandoQueNaoExisteDeProposito');
});
