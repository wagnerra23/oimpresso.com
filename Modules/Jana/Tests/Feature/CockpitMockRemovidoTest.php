<?php

declare(strict_types=1);

// @covers-us US-COPI-123

use Illuminate\Support\Facades\Route;

/**
 * US-COPI-123 (p0) — o mock em rota LIVE não pode voltar.
 *
 * ── O CONTRATO (derivado da US, não do código) ───────────────────────────────
 * A US pedia: "remover startMockStream da rota live". Ela foi resolvida por
 * REMOÇÃO na onda 4 da US-COPI-148 (2026-08-07) — a tela inteira saiu, porque a
 * capacidade tem receptor vivo em `/ia` com dado real do SellsCockpitAggregator.
 *
 * As DUAS metades do mock eram um par acoplado, e é isso que este teste trava:
 *   · `startMockStream`   simulava streaming client-side no `Cockpit.tsx`
 *   · `mockJanaPayload()` servia payload fixo do Martinho (biz=164) no controller
 *
 * ── POR QUE ELE NÃO É TAUTOLÓGICO ───────────────────────────────────────────
 * Ele não afirma "o código faz o que o código faz". Afirma uma proibição vinda da
 * US: *nenhuma rota live da Jana responde mock*. Se alguém reintroduzir qualquer
 * uma das metades — ou ressuscitar a rota — ele fica vermelho, que é exatamente
 * o evento que a US quer impedir.
 *
 * ── O QUE ELE DELIBERADAMENTE NÃO PROMETE ───────────────────────────────────
 * NÃO prova que a Jana não tem mock em lugar nenhum: `config('copiloto.dry_run')`
 * segue existindo e é legítimo (fixture em teste, nunca em rota live). O escopo
 * aqui é o par que a US-COPI-123 nomeia.
 */
it('a Page Jana/Cockpit não existe mais (foi apagada, não desativada)', function () {
    expect(file_exists(base_path('resources/js/Pages/Jana/Cockpit.tsx')))->toBeFalse();
});

it('startMockStream não existe em NENHUMA Page da Jana', function () {
    $achados = [];

    foreach (glob(base_path('resources/js/Pages/Jana/**/*.tsx'), GLOB_BRACE) ?: [] as $f) {
        if (str_contains((string) file_get_contents($f), 'startMockStream')) {
            $achados[] = str_replace(base_path().DIRECTORY_SEPARATOR, '', $f);
        }
    }
    foreach (glob(base_path('resources/js/Pages/Jana/*.tsx')) ?: [] as $f) {
        if (str_contains((string) file_get_contents($f), 'startMockStream')) {
            $achados[] = str_replace(base_path().DIRECTORY_SEPARATOR, '', $f);
        }
    }

    // Mensagem separada do needle: `toContain` é variádico e engoliria a
    // explicação como 2º needle (§5 2026-07-28).
    expect($achados)->toBe([], 'mock de streaming voltou em: '.implode(', ', $achados));
});

it('o ChatController não tem cockpit() nem mockJanaPayload()', function () {
    $src = (string) file_get_contents(
        base_path('Modules/Jana/Http/Controllers/ChatController.php')
    );

    // `function X(` e não só `X` — o comentário-lápide cita os nomes de propósito,
    // e um grep cru daria falso-positivo nele.
    expect($src)->not->toContain('function cockpit(');
    expect($src)->not->toContain('function mockJanaPayload(');

    // Controle negativo: iniciais() FICOU (index/show usam). Se este assert cair,
    // o corte levou junto o que não devia — e o teste acima passaria feliz.
    expect($src)->toContain('function iniciais(');
});

it('/ia/cockpit não serve tela — é 301 para o Painel', function () {
    $rota = collect(Route::getRoutes())->first(
        fn ($r) => $r->uri() === 'ia/cockpit' && in_array('GET', $r->methods(), true)
    );

    expect($rota)->not->toBeNull('a rota /ia/cockpit sumiu — o 301 precisa existir pra bookmark antigo');
    expect($rota->getName())->not->toBe('jana.cockpit');

    $this->get('/ia/cockpit')->assertRedirect('/ia');
});
