<?php

declare(strict_types=1);
// @covers-us US-FIN-080
// Cobre UC-FCC-19..22 (resources/js/Pages/Financeiro/Conciliacao/Index.casos.md · US-FIN-080).

use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Financeiro\Http\Controllers\ConciliacaoController;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * Conciliação por regras. O motor só VINCULA a linha ao título — não baixa, não mexe em valor.
 * Isso é provado por dois caminhos em UC-FCC-19: a linha do título igual antes e depois, e a
 * contagem de baixas do título igual antes e depois.
 *
 * Tenant 98 (seededTenant, ADR 0358) contra o 99. NUNCA biz=4.
 * ⚠️ SKIP em SQLite: leia assertions, não "0 failed" (LC-13).
 */
beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: fin_* depende de schema MySQL.');
    }
    if (! Schema::hasTable('fin_conciliacao_regras') || ! Schema::hasColumn('fin_extrato_lancamentos', 'regra_id')) {
        $this->markTestSkipped('Migration de regras (2026_10_27_120000) não aplicada — rode migrate.');
    }
    $this->b98 = (int) $this->seededTenant()->id;
    $this->b99 = (int) $this->seededSupportClientTenant()->id;
    $this->u98 = (int) (User::where('business_id', $this->b98)->value('id') ?? 0);
    $this->u99 = (int) (User::where('business_id', $this->b99)->value('id') ?? 0);
    if ($this->u98 === 0 || $this->u99 === 0) {
        $this->markTestSkipped('Sem usuário nos tenants 98/99.');
    }
    config(['financeiro.conciliacao.regras_enabled' => true, 'financeiro.conciliacao.janela_dias' => 5]);
});

function regraConta(int $biz): int
{
    $accountId = DB::table('accounts')->insertGetId([
        'business_id' => $biz, 'name' => 'PEST-REGRA-'.uniqid(), 'account_number' => '999',
        'created_at'  => now(), 'updated_at' => now(),
    ]);

    return (int) DB::table('fin_contas_bancarias')->insertGetId([
        'business_id'               => $biz,
        'account_id'                => $accountId,
        'banco_codigo'              => '077',
        'agencia'                   => '0001',
        'carteira'                  => '112',
        'beneficiario_documento'    => '00.000.000/0000-00', // pii-allowlist — CNPJ fixture de teste (nao-PII real)
        'beneficiario_razao_social' => 'Teste Regras',
        'created_at'                => now(),
        'updated_at'                => now(),
    ]);
}

function regraLinhaApi(int $biz, int $conta, float $valor, string $tipo, string $descricao): int
{
    return (int) DB::table('fin_extrato_lancamentos')->insertGetId([
        'business_id' => $biz, 'conta_bancaria_id' => $conta, 'data' => '2031-04-10',
        'valor' => $valor, 'tipo' => $tipo, 'descricao' => $descricao,
        'idempotency_key' => 'PEST-REGRA-'.uniqid(), 'raw_payload' => json_encode(['source' => 'test']),
        'created_at' => now(), 'updated_at' => now(),
    ]);
}

function regraTitulo(int $biz, int $user, string $tipo, float $valor, ?int $clienteId, string $venc = '2031-04-11'): int
{
    return (int) DB::table('fin_titulos')->insertGetId([
        'business_id' => $biz, 'numero' => 'PR-'.substr(uniqid(), -8), 'tipo' => $tipo, 'status' => 'aberto',
        'cliente_id' => $clienteId, 'cliente_descricao' => 'PEST regra', 'valor_total' => $valor,
        'valor_aberto' => $valor, 'emissao' => '2031-04-01', 'vencimento' => $venc, 'competencia_mes' => '2031-04',
        'origem' => 'manual', 'created_by' => $user, 'created_at' => now(), 'updated_at' => now(),
    ]);
}

function regraCriar(int $biz, array $over = []): int
{
    return (int) DB::table('fin_conciliacao_regras')->insertGetId(array_merge([
        'business_id' => $biz, 'nome' => 'PEST regra', 'ativo' => 1, 'prioridade' => 10,
        'descricao_contem' => 'mensalidade', 'sentido' => 'C', 'acao' => 'conciliar',
        'created_at' => now(), 'updated_at' => now(),
    ], $over));
}

/** Mesmo fluxo do upload e do botão "Aplicar regras": regras primeiro, depois valor + data. */
function regraRodarMotor(int $biz): void
{
    $controller = new ConciliacaoController();
    $ref = new ReflectionMethod($controller, 'sugerirMatches');
    $ref->setAccessible(true);
    $ref->invoke($controller, $biz);
}

it('UC-FCC-19 · regra casa a linha com o único título do cliente e não mexe no título', function () {
    $conta = regraConta($this->b98);
    $cliente = 900000 + random_int(1, 99999);
    $titulo = regraTitulo($this->b98, $this->u98, 'receber', 249.90, $cliente);
    $outroCliente = regraTitulo($this->b98, $this->u98, 'receber', 249.90, $cliente + 1);
    $linha = regraLinhaApi($this->b98, $conta, 249.90, 'C', 'PIX RECEBIDO Mensalidade abril');
    $regra = regraCriar($this->b98, ['cliente_id' => $cliente]);

    $tituloAntes = (array) DB::table('fin_titulos')->where('id', $titulo)->first();
    $baixasAntes = DB::table('fin_titulo_baixas')->where('titulo_id', $titulo)->count();

    regraRodarMotor($this->b98);

    $l = DB::table('fin_extrato_lancamentos')->where('id', $linha)->first();
    expect($l->status)->toBe('conciliado');
    expect((int) $l->titulo_id)->toBe($titulo);
    expect((int) $l->regra_id)->toBe($regra);
    expect($l->conciliado_by)->toBeNull();

    // Caminho 1: a linha do título é a mesma (valor, aberto, status). Caminho 2: nenhuma baixa nasceu.
    $tituloDepois = (array) DB::table('fin_titulos')->where('id', $titulo)->first();
    expect($tituloDepois['valor_total'])->toBe($tituloAntes['valor_total']);
    expect($tituloDepois['valor_aberto'])->toBe($tituloAntes['valor_aberto']);
    expect($tituloDepois['status'])->toBe('aberto');
    expect(DB::table('fin_titulo_baixas')->where('titulo_id', $titulo)->count())->toBe($baixasAntes);

    // O título do outro cliente, de mesmo valor, não foi tocado.
    expect(DB::table('fin_extrato_lancamentos')->where('titulo_id', $outroCliente)->count())->toBe(0);
});

it('UC-FCC-20 · com dois títulos possíveis a regra não decide; sugerir deixa para revisão; reabrir limpa a regra', function () {
    $conta = regraConta($this->b98);
    regraTitulo($this->b98, $this->u98, 'receber', 80.00, null);
    regraTitulo($this->b98, $this->u98, 'receber', 80.00, null, '2031-04-12');
    $ambigua = regraLinhaApi($this->b98, $conta, 80.00, 'C', 'Mensalidade sem cliente');
    regraCriar($this->b98, ['valor_min' => 50, 'valor_max' => 100]);

    $unico = regraTitulo($this->b98, $this->u98, 'pagar', 33.33, null);
    $saida = regraLinhaApi($this->b98, $conta, 33.33, 'D', 'TARIFA PACOTE');
    $regraSugerir = regraCriar($this->b98, ['descricao_contem' => 'tarifa', 'sentido' => 'D', 'acao' => 'sugerir']);

    regraRodarMotor($this->b98);

    // Ambígua: a regra não escolhe entre dois — a linha cai no casamento por valor e data (sem regra).
    $a = DB::table('fin_extrato_lancamentos')->where('id', $ambigua)->first();
    expect($a->regra_id)->toBeNull();
    expect($a->status)->not->toBe('conciliado');

    // Sugerir: casa, mas espera a confirmação de uma pessoa.
    $s = DB::table('fin_extrato_lancamentos')->where('id', $saida)->first();
    expect($s->status)->toBe('sugerido');
    expect((int) $s->titulo_id)->toBe($unico);
    expect((int) $s->regra_id)->toBe($regraSugerir);

    // Reabrir pelo método da tela (o gate de permissão da rota é coberto pelo PermissaoRotasContratoTest).
    session(['user.business_id' => $this->b98, 'business.id' => $this->b98]);
    (new ConciliacaoController())->reabrir(Request::create('/x', 'POST', ['origem' => 'api']), $saida);
    $r = DB::table('fin_extrato_lancamentos')->where('id', $saida)->first();
    expect($r->status)->toBeNull();
    expect($r->regra_id)->toBeNull();
    expect($r->titulo_id)->toBeNull();
});

it('UC-FCC-21 · com a flag desligada nenhuma regra roda', function () {
    config(['financeiro.conciliacao.regras_enabled' => false]);
    $conta = regraConta($this->b98);
    $cliente = 900000 + random_int(1, 99999);
    regraTitulo($this->b98, $this->u98, 'receber', 61.00, $cliente);
    $linha = regraLinhaApi($this->b98, $conta, 61.00, 'C', 'Mensalidade');
    regraCriar($this->b98, ['cliente_id' => $cliente]);

    regraRodarMotor($this->b98);

    $l = DB::table('fin_extrato_lancamentos')->where('id', $linha)->first();
    expect($l->regra_id)->toBeNull();
    expect($l->status)->not->toBe('conciliado'); // no máximo sugerido pelo valor + data de sempre
});

it('UC-FCC-22 · regra e título de outro business nunca casam a linha [T0]', function () {
    $conta98 = regraConta($this->b98);
    $cliente = 900000 + random_int(1, 99999);
    // Título com o cliente certo, mas no 99; regra também no 99.
    regraTitulo($this->b99, $this->u99, 'receber', 412.00, $cliente);
    regraCriar($this->b99, ['cliente_id' => $cliente]);
    $linha98 = regraLinhaApi($this->b98, $conta98, 412.00, 'C', 'Mensalidade');

    regraRodarMotor($this->b98);
    regraRodarMotor($this->b99);

    $l = DB::table('fin_extrato_lancamentos')->where('id', $linha98)->first();
    expect($l->regra_id)->toBeNull();
    expect($l->titulo_id)->toBeNull();
    expect($l->status)->toBeNull();
});
