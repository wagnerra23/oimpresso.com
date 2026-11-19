<?php

declare(strict_types=1);

use App\User;
use App\Utils\ModuleUtil;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Modules\NfeBrasil\Http\Controllers\NfeEmissaoController;

uses(Tests\TestCase::class, DatabaseTransactions::class);

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
 * O NfeEmissaoController é substituído por um dublê que registra o id pedido: o que se mede é
 * a decisão do RecurringBilling (qual nota e se reenvia), não o envio do e-mail.
 * Tenant 98 (ADR 0358), nunca o 4. ⚠️ SKIP fora do MySQL: leia assertions (LC-13).
 */
beforeEach(function () {
    if (DB::connection()->getDriverName() !== 'mysql') {
        $this->markTestSkipped('Schema real do RecurringBilling + NfeBrasil (MySQL).');
    }
    $this->biz = (int) $this->seededTenant()->id;

    $moduleUtil = Mockery::mock(ModuleUtil::class)->makePartial();
    $moduleUtil->shouldReceive('hasThePermissionInSubscription')->andReturn(true);
    app()->instance(ModuleUtil::class, $moduleUtil);

    $this->pedidos = new ArrayObject();
    $this->falhar = false;
    $teste = $this;
    app()->instance(NfeEmissaoController::class, new class($teste) {
        public function __construct(private $teste) {}

        public function reenviarEmail($request, int $id)
        {
            $this->teste->pedidos->append($id);
            if ($this->teste->falhar) {
                throw new RuntimeException('detalhe-interno-'.$id.' /var/www/segredo.pem');
            }

            return response()->json(['ok' => true]);
        }
    });

    $owner = (int) DB::table('business')->where('id', $this->biz)->value('owner_id');
    $contato = fn () => DB::table('contacts')->insertGetId(['business_id' => $this->biz, 'type' => 'customer',
        'name' => 'Cliente RNF '.uniqid(), 'mobile' => '', 'contact_id' => 'RNF-'.uniqid(),
        'created_by' => $owner, 'created_at' => now(), 'updated_at' => now()]);
    $this->contatoAssinatura = $contato();
    $this->contatoOutro = $contato();

    $plano = DB::table('rb_plans')->insertGetId(['business_id' => $this->biz, 'name' => 'P RNF '.uniqid(),
        'slug' => 'p-rnf-'.uniqid(), 'valor' => '90.00', 'ciclo' => 'monthly', 'created_at' => now(), 'updated_at' => now()]);
    $this->sub = (int) DB::table('rb_subscriptions')->insertGetId(['business_id' => $this->biz, 'plan_id' => $plano,
        'contact_id' => $this->contatoAssinatura, 'status' => 'active', 'start_date' => '2031-01-01',
        'next_due_date' => '2031-04-05', 'billing_anchor_date' => '2031-04-05', 'metadata' => json_encode([]),
        'created_at' => now(), 'updated_at' => now()]);

    $this->faturaPaga = fn (array $meta) => DB::table('rb_invoices')->insertGetId(['business_id' => $this->biz,
        'subscription_id' => $this->sub, 'contact_id' => $this->contatoAssinatura, 'numero_documento' => 'RNF-'.uniqid(),
        'valor' => '90.00', 'status' => 'paid', 'vencimento' => '2031-03-05', 'pago_em' => now()->subDay(),
        'metadata' => json_encode($meta), 'created_at' => now(), 'updated_at' => now()]);

    $this->como = function (array $permissoes) {
        $user = $this->usuarioComPermissoes($permissoes, \App\Business::find($this->biz));

        return $this->actingAs($user)->withSession([
            'user.business_id' => $this->biz, 'user.id' => $user->id,
            'user' => ['business_id' => $this->biz, 'id' => $user->id],
            'business.id' => $this->biz,
            'business.date_format' => 'd/m/Y', 'business.time_format' => 24,
        ]);
    };
});

afterEach(function () {
    app()->forgetInstance(ModuleUtil::class);
    app()->forgetInstance(NfeEmissaoController::class);
});

it('d3 · sem NF-e vinculada à fatura responde 422 e NÃO reenvia a nota de outro cliente da empresa', function () {
    // A nota "da vizinhança" que a heurística antiga escolhia: autorizada, mesma empresa,
    // emitida no dia do pagamento, mas de OUTRO cliente.
    $notaAlheia = DB::table('nfe_emissoes')->insertGetId(['business_id' => $this->biz, 'transaction_id' => null,
        'modelo' => '55', 'serie' => '9', 'numero' => random_int(800000, 899999), 'status' => 'autorizada',
        'valor_total' => '90.00', 'emitido_em' => now()->subDay(), 'metadata' => json_encode(['contact_id' => $this->contatoOutro]),
        'created_at' => now(), 'updated_at' => now()]);
    ($this->faturaPaga)([]);

    $r = ($this->como)(['fiscal.nfe.acoes'])->postJson("/recurring-billing/{$this->sub}/reenviar-nfe");

    $r->assertStatus(422)->assertJson(['error' => 'sem_nfe_vinculada']);
    expect($this->pedidos->getArrayCopy())->toBe([])
        ->and($this->pedidos->getArrayCopy())->not->toContain($notaAlheia);
});

it('d3 · usuário sem a permissão de reenvio do NfeBrasil recebe 403; com a permissão, reenvia a nota vinculada', function () {
    ($this->faturaPaga)(['nfe_emissao_id' => 424242]);

    // Negativo: só acesso ao RecurringBilling.
    ($this->como)(['recurringbilling.access'])->postJson("/recurring-billing/{$this->sub}/reenviar-nfe")
        ->assertStatus(403)->assertJson(['error' => 'sem_permissao_nfe']);
    expect($this->pedidos->getArrayCopy())->toBe([]);

    // Positivo: a permissão de reenvio do NfeBrasil — reenvia exatamente a nota vinculada.
    ($this->como)(['fiscal.nfe.acoes'])->postJson("/recurring-billing/{$this->sub}/reenviar-nfe")
        ->assertOk()->assertJson(['ok' => true, 'emissao_id' => 424242]);
    expect($this->pedidos->getArrayCopy())->toBe([424242]);
});

it('d3 · falha no reenvio devolve mensagem genérica, sem o texto da exceção', function () {
    ($this->faturaPaga)(['nfe_emissao_id' => 515151]);
    $this->falhar = true;

    $r = ($this->como)(['nfebrasil.emit.manage'])
        ->postJson("/recurring-billing/{$this->sub}/reenviar-nfe");

    $r->assertStatus(503)->assertJson(['error' => 'nfe_reenvio_falhou']);
    expect($this->pedidos->getArrayCopy())->toBe([515151]) // chegou ao reenvio: a falha é dele
        ->and($r->getContent())->not->toContain('detalhe-interno')
        ->and($r->getContent())->not->toContain('segredo.pem');
});
