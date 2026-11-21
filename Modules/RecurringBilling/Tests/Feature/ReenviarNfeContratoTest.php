<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\NfeBrasil\Http\Controllers\NfeEmissaoController;
use Modules\RecurringBilling\Http\Controllers\RecurringBillingController;
use Modules\RecurringBilling\Repositories\SubscriptionRepository;

uses(Tests\TestCase::class);

/**
 * POST /recurring-billing/{id}/reenviar-nfe — auditoria RecurringBilling d3 (2026-11-19).
 *
 * Três defeitos medidos no código antes deste teste:
 *  1. sem `nfe_emissao_id` na fatura, o controller pegava a última NF-e autorizada da EMPRESA
 *     numa janela de ±7 dias, de qualquer cliente — reenviava o DANFE de outro cliente;
 *  2. chamava NfeEmissaoController::reenviarEmail in-process, pulando o middleware
 *     PermissaoNfe::REENVIAR da rota do NfeBrasil;
 *  3. devolvia `$e->getMessage()` ao front.
 *
 * O NfeEmissaoController é trocado por um dublê que registra o id pedido: o que se mede é a
 * decisão do RecurringBilling (qual nota e se reenvia), não o e-mail. O usuário é um dublê
 * com `can()` por lista, para medir a regra do controller sem o Spatie.
 *
 * SQLite in-memory (lane modules-pest, matrix RecurringBilling). Tenant 98 (ADR 0358); nunca o 4.
 */
beforeEach(function () {
    if (config('database.default') !== 'sqlite' || ! str_contains((string) config('database.connections.sqlite.database'), ':memory:')) {
        $this->markTestSkipped('Rodado em SQLite in-memory (schema montado aqui).');
    }

    foreach (['rb_subscription_events', 'nfe_emissoes', 'rb_invoices', 'rb_subscriptions'] as $t) {
        Schema::dropIfExists($t);
    }
    Schema::create('rb_subscriptions', function ($t) {
        $t->id();
        $t->unsignedInteger('business_id')->index();
        $t->unsignedBigInteger('plan_id')->nullable();
        $t->unsignedInteger('contact_id')->nullable();
        $t->string('status', 20)->default('active');
        $t->date('start_date')->nullable();
        $t->date('next_due_date')->nullable();
        $t->date('billing_anchor_date')->nullable();
        $t->json('metadata')->nullable();
        $t->timestamps();
        $t->softDeletes();
    });
    Schema::create('rb_invoices', function ($t) {
        $t->id();
        $t->unsignedInteger('business_id')->index();
        $t->unsignedBigInteger('subscription_id')->nullable();
        $t->unsignedInteger('contact_id')->nullable();
        $t->string('numero_documento', 50)->nullable();
        $t->decimal('valor', 15, 2)->default(0);
        $t->string('status', 20)->default('open');
        $t->date('vencimento')->nullable();
        $t->dateTime('pago_em')->nullable();
        $t->json('metadata')->nullable();
        $t->timestamps();
        $t->softDeletes();
    });
    Schema::create('nfe_emissoes', function ($t) {
        $t->id();
        $t->unsignedInteger('business_id')->index();
        $t->string('status', 20);
        $t->dateTime('emitido_em')->nullable();
        $t->json('metadata')->nullable();
        $t->timestamps();
        $t->softDeletes();
    });
    Schema::create('rb_subscription_events', function ($t) {
        $t->id();
        $t->unsignedInteger('business_id')->index();
        $t->unsignedBigInteger('subscription_id');
        $t->string('kind', 20);
        $t->string('by_actor', 64);
        $t->text('body');
        $t->dateTime('occurred_at');
        $t->timestamps();
    });

    $this->biz = 98;
    session(['user.business_id' => $this->biz, 'business.id' => $this->biz]);

    $this->sub = (int) DB::table('rb_subscriptions')->insertGetId(['business_id' => $this->biz, 'contact_id' => 501,
        'status' => 'active', 'start_date' => '2031-01-01', 'next_due_date' => '2031-04-05',
        'billing_anchor_date' => '2031-04-05', 'metadata' => '[]', 'created_at' => now(), 'updated_at' => now()]);
    $this->faturaPaga = fn (array $meta) => DB::table('rb_invoices')->insertGetId(['business_id' => $this->biz,
        'subscription_id' => $this->sub, 'contact_id' => 501, 'numero_documento' => 'RNF-'.uniqid(), 'valor' => '90.00',
        'status' => 'paid', 'vencimento' => '2031-03-05', 'pago_em' => now()->subDay(),
        'metadata' => json_encode($meta), 'created_at' => now(), 'updated_at' => now()]);

    // Dublê do NfeBrasil: registra o id pedido; falha sob demanda.
    $this->pedidos = new ArrayObject();
    $this->falha = new ArrayObject();
    $pedidos = $this->pedidos;
    $falha = $this->falha;
    app()->instance(NfeEmissaoController::class, new class($pedidos, $falha) {
        public function __construct(private ArrayObject $pedidos, private ArrayObject $falha) {}

        public function reenviarEmail($request, int $id)
        {
            $this->pedidos->append($id);
            if (count($this->falha) > 0) {
                throw new RuntimeException('detalhe-interno-'.$id.' /var/www/segredo.pem');
            }

            return response()->json(['ok' => true]);
        }
    });

    $this->reenviar = function (array $permissoes) {
        $user = new class($permissoes) {
            public string $first_name = 'Teste';

            public function __construct(private array $permissoes) {}

            public function can($ability, $arguments = []): bool
            {
                return in_array($ability, $this->permissoes, true);
            }
        };
        $request = Request::create("/recurring-billing/{$this->sub}/reenviar-nfe", 'POST');
        $request->setUserResolver(fn () => $user);

        return (new RecurringBillingController(new SubscriptionRepository()))->reenviarNfe($request, $this->sub);
    };
});

afterEach(function () {
    app()->forgetInstance(NfeEmissaoController::class);
    session()->flush();
});

it('d3 · sem NF-e vinculada à fatura responde 422 e NÃO reenvia a nota de outro cliente da empresa', function () {
    // A nota que a heurística antiga escolhia: autorizada, mesma empresa, emitida no dia do
    // pagamento — mas de OUTRO cliente (contato 777).
    $notaAlheia = DB::table('nfe_emissoes')->insertGetId(['business_id' => $this->biz, 'status' => 'autorizada',
        'emitido_em' => now()->subDay(), 'metadata' => json_encode(['contact_id' => 777]),
        'created_at' => now(), 'updated_at' => now()]);
    ($this->faturaPaga)([]);

    $r = ($this->reenviar)(['recurringbilling.access', 'fiscal.nfe.acoes']);

    expect($r->getStatusCode())->toBe(422)
        ->and($r->getData(true)['error'])->toBe('sem_nfe_vinculada')
        ->and($this->pedidos->getArrayCopy())->toBe([])
        ->and($notaAlheia)->toBeGreaterThan(0);
});

it('d3 · sem a permissão de reenvio do NfeBrasil responde 403; com ela, reenvia a nota vinculada', function () {
    ($this->faturaPaga)(['nfe_emissao_id' => 424242]);

    // Negativo: só o acesso ao RecurringBilling.
    $negado = ($this->reenviar)(['recurringbilling.access']);
    expect($negado->getStatusCode())->toBe(403)
        ->and($negado->getData(true)['error'])->toBe('sem_permissao_nfe')
        ->and($this->pedidos->getArrayCopy())->toBe([]);

    // Positivo: uma das permissões de PermissaoNfe::REENVIAR — reenvia exatamente a vinculada.
    $ok = ($this->reenviar)(['recurringbilling.access', 'fiscal.nfe.acoes']);
    expect($ok->getStatusCode())->toBe(200)
        ->and($ok->getData(true)['emissao_id'])->toBe(424242)
        ->and($this->pedidos->getArrayCopy())->toBe([424242])
        ->and(DB::table('rb_subscription_events')->where('subscription_id', $this->sub)->where('kind', 'event-nf')->count())->toBe(1);
});

it('d3 · falha no reenvio devolve mensagem genérica, sem o texto da exceção', function () {
    ($this->faturaPaga)(['nfe_emissao_id' => 515151]);
    $this->falha->append(true);

    $r = ($this->reenviar)(['recurringbilling.access', 'nfebrasil.emit.manage']);

    expect($r->getStatusCode())->toBe(503)
        ->and($r->getData(true)['error'])->toBe('nfe_reenvio_falhou')
        ->and($this->pedidos->getArrayCopy())->toBe([515151]) // chegou ao reenvio: a falha é dele
        ->and((string) $r->getContent())->not->toContain('detalhe-interno')
        ->and((string) $r->getContent())->not->toContain('segredo.pem');
});
