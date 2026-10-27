<?php

declare(strict_types=1);

// @covers-us US-NFE-004 — cancelar a NF-e pergunta: só a nota ou a venda junto (R-NFE-044).

use App\Domain\Fsm\Models\SaleStageHistory;
use App\Domain\Fsm\Services\ExecuteStageActionService;
use App\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Modules\Fiscal\Http\Controllers\AcoesController;
use Modules\NfeBrasil\Models\NfeEmissao;
use Modules\NfeBrasil\Services\NfeService;

uses(Tests\TestCase::class);

/**
 * R-NFE-044 · decisão [W] 2026-10-26: ao cancelar a NF-e, a pessoa escolhe cancelar só a nota (para
 * reemitir) ou a venda junto (ação FSM `cancelar_venda`, que roda o CancelarVendaCascade).
 * Tenant 98 e vizinho 99 (ADR 0358). A FSM é falsa: o que se prova aqui é QUEM é chamado, com que motivo.
 */

function escopoUsuario(): void
{
    Auth::setUser(new class extends User
    {
        public function can($abilities, $arguments = []): bool
        {
            return true;
        }
    });
}

function escopoFsmFalsa(ArrayObject $log): void
{
    app()->instance(ExecuteStageActionService::class, new class($log) extends ExecuteStageActionService
    {
        public function __construct(private ArrayObject $log) {}

        public function execute(Model $subject, string $actionKey, ?User $user = null, array $payload = []): SaleStageHistory
        {
            $this->log[] = [(int) $subject->getKey(), $actionKey, $payload['motivo'] ?? null];

            return new SaleStageHistory();
        }
    });
}

it('UC-FNFE-51 · escolha diferente de nota/venda não passa da validação', function () {
    escopoUsuario();

    try {
        (new AcoesController())->cancelarNfe(
            Request::create('/x', 'POST', ['motivo' => 'Cliente desistiu da compra inteira', 'escopo' => 'tudo']),
            app(NfeService::class),
            1,
        );
        $erro = null;
    } catch (Throwable $e) {
        $erro = $e;
    }

    expect($erro)->toBeInstanceOf(ValidationException::class);
    expect(array_keys($erro->errors()))->toContain('escopo');
});

it('UC-FNFE-52 · "venda junto" chama cancelar_venda só para a venda da empresa e no fluxo', function () {
    if (DB::getDriverName() !== 'mysql' || ! Schema::hasTable('nfe_emissoes') || ! Schema::hasTable('sale_process_stages')) {
        $this->markTestSkipped('precisa do schema MySQL de transactions, nfe_emissoes e sale_process_stages');
    }
    $a = $this->seededTenant()->id;
    $b = $this->seededSupportClientTenant()->id;
    $stage = DB::table('sale_process_stages')->value('id');
    if ($stage === null) {
        $this->markTestSkipped('sem estágio de fluxo de vendas neste banco');
    }
    $user = DB::table('users')->where('business_id', $a)->value('id') ?? 1;
    $venda = fn (int $biz, ?int $etapa) => DB::table('transactions')->insertGetId([
        'business_id' => $biz, 'type' => 'sell', 'status' => 'final', 'payment_status' => 'paid',
        'transaction_date' => now(), 'ref_no' => 'CANCESC-'.uniqid(), 'final_total' => 100, 'total_before_tax' => 100,
        'tax_amount' => 0, 'discount_amount' => 0, 'created_by' => $user, 'essentials_duration' => 0,
        'current_stage_id' => $etapa, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $ids = [$venda($a, $stage), $venda($a, null), $venda($b, $stage)];

    try {
        $log = new ArrayObject();
        escopoFsmFalsa($log);
        $c = new AcoesController();
        $m = new ReflectionMethod(AcoesController::class, 'cancelarVendaDaNota');
        $m->setAccessible(true);
        $nota = fn (int $tx) => tap(new NfeEmissao(), fn ($n) => $n->forceFill(['transaction_id' => $tx, 'numero' => 7]));

        [$ok, $msg] = $m->invoke($c, $a, $nota($ids[0]), 'Cliente desistiu da compra inteira');
        expect($ok)->toBeTrue();
        expect($log[0])->toBe([$ids[0], 'cancelar_venda', 'Cliente desistiu da compra inteira']);
        expect($msg)->toContain('cancelada');

        // Fora do fluxo: não cancela, manda cancelar pela tela de Vendas.
        [$ok, $msg] = $m->invoke($c, $a, $nota($ids[1]), 'Cliente desistiu da compra inteira');
        expect($ok)->toBeFalse();
        expect($msg)->toContain('não está no fluxo de vendas');

        // Venda de outra empresa: não é achada.
        [$ok, $msg] = $m->invoke($c, $a, $nota($ids[2]), 'Cliente desistiu da compra inteira');
        expect($ok)->toBeFalse();
        expect($msg)->toContain('não tem venda nesta empresa');

        expect(count($log))->toBe(1);
    } finally {
        DB::table('transactions')->whereIn('id', $ids)->delete();
    }
});

it('UC-FNFE-54 · venda com dinheiro recebido: nada é cancelado, nem a nota', function () {
    if (! class_exists('App\Services\Sells\CancelamentoDaVenda')) {
        $this->markTestSkipped('depende do CancelamentoDaVenda da US-SELL-077 (#9911)');
    }
    if (DB::getDriverName() !== 'mysql' || ! Schema::hasTable('nfe_emissoes') || ! Schema::hasTable('sale_process_stages')) {
        $this->markTestSkipped('precisa do schema MySQL de transactions, nfe_emissoes e sale_process_stages');
    }
    $a = $this->seededTenant()->id;
    $stage = DB::table('sale_process_stages')->value('id');
    if ($stage === null) {
        $this->markTestSkipped('sem estágio de fluxo de vendas neste banco');
    }
    $user = DB::table('users')->where('business_id', $a)->value('id') ?? 1;
    $tx = DB::table('transactions')->insertGetId([
        'business_id' => $a, 'type' => 'sell', 'status' => 'final', 'payment_status' => 'paid',
        'transaction_date' => now(), 'ref_no' => 'CANCESC-'.uniqid(), 'final_total' => 60, 'total_before_tax' => 60,
        'tax_amount' => 0, 'discount_amount' => 0, 'created_by' => $user, 'essentials_duration' => 0,
        'current_stage_id' => $stage, 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('transaction_payments')->insert([
        'transaction_id' => $tx, 'business_id' => $a, 'amount' => 60, 'method' => 'cash', 'is_return' => 0,
        'paid_on' => now(), 'created_by' => $user, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $nota = DB::table('nfe_emissoes')->insertGetId([
        'business_id' => $a, 'transaction_id' => $tx, 'modelo' => '55', 'serie' => '954', 'numero' => 9_540_001,
        'status' => 'autorizada', 'valor_total' => 60, 'emitido_em' => now(), 'created_at' => now(), 'updated_at' => now(),
    ]);

    try {
        escopoUsuario();
        session(['user.business_id' => $a]);
        $nfe = Mockery::mock(NfeService::class);
        $nfe->shouldNotReceive('cancelar');

        (new AcoesController())->cancelarNfe(
            Request::create('/x', 'POST', ['motivo' => 'Cliente desistiu da compra inteira', 'escopo' => 'venda']),
            $nfe,
            $nota,
        );

        expect((string) session('error'))->toContain('Nada foi cancelado.')
            ->and((string) session('error'))->toContain('recebidos');
        expect(DB::table('nfe_emissoes')->where('id', $nota)->value('status'))->toBe('autorizada');
    } finally {
        DB::table('nfe_emissoes')->where('id', $nota)->delete();
        DB::table('transaction_payments')->where('transaction_id', $tx)->delete();
        DB::table('transactions')->where('id', $tx)->delete();
    }
});
