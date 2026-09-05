<?php

declare(strict_types=1);

use Modules\Ponto\Tests\Feature\PontoTestCase;

uses(PontoTestCase::class);

/**
 * Contrato do hub de boas-vindas — `/ponto/react` → Welcome.casos.md (UC-PWEL-01).
 *
 * O UC cita o id no TÍTULO do `it()` — é o que o manifesto G-7 alcança.
 *
 * Deriva do charter §Non-Goals/§Anti-hooks + `CU-PONTO-12` + LGPD Art. 7º. NÃO do `.tsx`.
 *
 * ── Por que esta tela merece um caso, sendo "só links" ─────────────────────────────────
 * É a ÚNICA do módulo servida por closure de rota (`Modules/Ponto/Http/routes.php`), sem
 * controller. Isso a torna o lugar mais barato de alguém "enriquecer" com um KPI: basta um
 * array no `Inertia::render` dentro do arquivo de rotas — sem tocar controller, sem revisão
 * de query, sem `business_id` escopado à vista. O caso trava a tela como navegação.
 *
 * Sem `RefreshDatabase` — a lane ponto-pest proíbe. Não toca banco.
 *
 * @see \Modules\Ponto\Http\routes.php (rota `ponto.react.welcome`)
 */

it('UC-PWEL-01 · a porta de entrada do ponto não carrega dado de ponto', function () {
    $this->actAsAdmin();

    $resp = $this->inertiaGet('/ponto/react');
    $resp->assertStatus(200);

    // Pré-condição: é de fato o hub que está sendo medido, e não um redirect que devolveu 200
    // de outra tela (sem isto, a ausência de props seria verdade sobre a página errada).
    expect($resp->json('component'))->toBe('Ponto/Welcome',
        'A rota tem de renderizar o hub — se ela passar a redirecionar, o caso está medindo outra tela.'
    );

    $props = array_keys((array) ($resp->json('props') ?? []));

    // Ausência POR NOME, e não comparação com uma lista esperada de props: as compartilhadas do
    // HandleInertiaRequests (auth, business, shell, locale, …) mudam por motivos alheios a esta
    // tela, e fixá-las aqui faria o caso quebrar em PR de terceiro — gate frágil se aprende a ignorar.
    $dominioDoPonto = [
        'marcacoes', 'colaboradores', 'apuracoes', 'apuracao', 'espelho', 'escalas',
        'reps', 'intercorrencias', 'importacoes', 'aprovacoes', 'banco_horas', 'kpis', 'config',
    ];

    $vazando = array_values(array_intersect($props, $dominioDoPonto));

    expect($vazando)->toBe([],
        'O hub de boas-vindas é navegação, não painel: ele não pode receber prop de domínio do '
        . 'ponto (charter §Non-Goals — "não mostra KPIs, dados de ponto nem marcações; sem props '
        . 'do backend"). Jornada é dado sensível (LGPD Art. 7º + sigilo trabalhista), e esta tela '
        . 'é servida por closure de rota, sem controller — se ela precisar de dado um dia, a '
        . 'mudança passa por aqui e alguém decide conscientemente, inclusive sobre o isolamento '
        . 'por business_id. Props de domínio encontradas: ' . implode(', ', $vazando)
    );
});
