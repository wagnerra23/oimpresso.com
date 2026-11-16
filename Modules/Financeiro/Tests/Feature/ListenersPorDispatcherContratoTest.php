<?php

declare(strict_types=1);

use Illuminate\Events\Dispatcher;
use Modules\Financeiro\Listeners\OnCashRegisterClosedCreateFinanceiroTitulo;
use Modules\Financeiro\Listeners\OnCobrancaPagaCreateFinanceiroTitulo;
use Modules\Financeiro\Events\CashRegisterClosed;
use Modules\Financeiro\Providers\FinanceiroServiceProvider;
use Modules\PaymentGateway\Events\CobrancaPaga;
use Modules\PaymentGateway\Events\CobrancaVencida;
use Modules\Superadmin\Listeners\OnCobrancaPagaUpdateSubscription;
use Modules\Superadmin\Listeners\OnCobrancaVencidaBloqueaSubscription;
use Modules\Superadmin\Providers\SuperadminServiceProvider;

/*
 * Contrato do registro dos listeners de pagamento nos providers do Financeiro e do
 * Superadmin (registerPaymentGatewayListeners).
 *
 * Até 2026-11-16 a guarda era uma flag ESTÁTICA: na suíte ela sobrevivia entre testes
 * enquanto o Laravel recriava a app e o dispatcher, e só o 1º teste do processo tinha o
 * listener que cria título. Com executionOrder="random" isso derrubou a lane required
 * "PHP / Pest (Unit)" (runs 43759180443 e 43758393324).
 *
 * O que este arquivo trava:
 *  - toda app tem EXATAMENTE 1 registro de cada listener (nem 0, nem 2) — o caso
 *    repetido no dataset roda em apps diferentes do mesmo processo;
 *  - boot() chamado de novo na MESMA app não duplica (o boot duplo do nWidart);
 *  - um dispatcher novo recebe o listener. Este é o caso que distingue as duas guardas
 *    em qualquer ordem: com a flag estática ele dá 0, porque o boot desta app já a ligou.
 *
 * Sem banco: roda na lane sqlite.
 */

uses(Tests\TestCase::class);

function lpdContar(object $events, string $evento, string $listener): int
{
    return count(array_filter(
        $events->getRawListeners()[$evento] ?? [],
        fn ($l) => is_array($l) && ($l[0] ?? null) === $listener,
    ));
}

function lpdRegistrar(object $provider): void
{
    (fn () => $this->registerPaymentGatewayListeners())->call($provider);
}

function lpdContagens(object $events): array
{
    return [
        'financeiro.cobranca_paga' => lpdContar($events, CobrancaPaga::class, OnCobrancaPagaCreateFinanceiroTitulo::class),
        'financeiro.caixa_fechado' => lpdContar($events, CashRegisterClosed::class, OnCashRegisterClosedCreateFinanceiroTitulo::class),
        'superadmin.cobranca_paga' => lpdContar($events, CobrancaPaga::class, OnCobrancaPagaUpdateSubscription::class),
        'superadmin.cobranca_vencida' => lpdContar($events, CobrancaVencida::class, OnCobrancaVencidaBloqueaSubscription::class),
    ];
}

function lpdUmDeCada(): array
{
    return [
        'financeiro.cobranca_paga' => 1,
        'financeiro.caixa_fechado' => 1,
        'superadmin.cobranca_paga' => 1,
        'superadmin.cobranca_vencida' => 1,
    ];
}

it('a app de cada teste tem exatamente 1 registro de cada listener de pagamento', function () {
    expect(lpdContagens(app('events')))->toBe(lpdUmDeCada());
})->with(['1ª app do processo' => [1], '2ª app' => [2], '3ª app' => [3]]);

it('boot repetido na mesma app não duplica o listener (boot duplo do nWidart)', function () {
    lpdRegistrar(new FinanceiroServiceProvider(app()));
    lpdRegistrar(new FinanceiroServiceProvider(app()));
    lpdRegistrar(new SuperadminServiceProvider(app()));
    lpdRegistrar(new SuperadminServiceProvider(app()));

    expect(lpdContagens(app('events')))->toBe(lpdUmDeCada());
});

it('um dispatcher novo recebe o listener mesmo depois de outro já ter recebido', function () {
    $original = app('events');
    $novo = new Dispatcher(app());
    app()->instance('events', $novo);

    try {
        // boot duplo, como o nWidart faz numa request de produção
        foreach ([1, 2] as $_) {
            lpdRegistrar(new FinanceiroServiceProvider(app()));
            lpdRegistrar(new SuperadminServiceProvider(app()));
        }
        $contagens = lpdContagens($novo);
    } finally {
        app()->instance('events', $original);
    }

    expect($contagens)->toBe(lpdUmDeCada())
        // o dispatcher original segue com 1 de cada: a guarda não vazou de um pro outro
        ->and(lpdContagens($original))->toBe(lpdUmDeCada());
});

it('disparado o evento, cada listener EXECUTA uma vez (caminho de execução, não só o registro)', function () {
    // 2º caminho de prova, independente do getRawListeners: conta execução real.
    // O dispatcher novo só tem o que os dois providers registraram, então o evento
    // não chega a listener de outro módulo. Os listeners viram espiões no container
    // (o dispatcher resolve [Classe, 'handle'] por make()), sem tocar banco.
    $execucoes = new ArrayObject();
    $espiao = fn (string $nome) => new class($nome, $execucoes) {
        public function __construct(private string $nome, private ArrayObject $execucoes) {}

        public function handle(mixed $evento): void
        {
            $this->execucoes[$this->nome] = ($this->execucoes[$this->nome] ?? 0) + 1;
        }
    };
    app()->instance(OnCobrancaPagaCreateFinanceiroTitulo::class, $espiao('financeiro.cobranca_paga'));
    app()->instance(OnCashRegisterClosedCreateFinanceiroTitulo::class, $espiao('financeiro.caixa_fechado'));
    app()->instance(OnCobrancaPagaUpdateSubscription::class, $espiao('superadmin.cobranca_paga'));
    app()->instance(OnCobrancaVencidaBloqueaSubscription::class, $espiao('superadmin.cobranca_vencida'));

    $original = app('events');
    $novo = new Dispatcher(app());
    app()->instance('events', $novo);

    try {
        foreach ([1, 2] as $_) {
            lpdRegistrar(new FinanceiroServiceProvider(app()));
            lpdRegistrar(new SuperadminServiceProvider(app()));
        }
        foreach ([CobrancaPaga::class, CashRegisterClosed::class, CobrancaVencida::class] as $evento) {
            $novo->dispatch($evento, [new stdClass()]);
        }
    } finally {
        app()->instance('events', $original);
    }

    $execucoes = $execucoes->getArrayCopy();
    ksort($execucoes);
    $esperado = lpdUmDeCada();
    ksort($esperado);
    expect($execucoes)->toBe($esperado);
});
