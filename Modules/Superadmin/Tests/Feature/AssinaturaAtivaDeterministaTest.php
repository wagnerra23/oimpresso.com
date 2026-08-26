<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Superadmin\Entities\Subscription;

uses(Tests\TestCase::class);

/**
 * `Subscription::active_subscription()` é DETERMINÍSTICA quando há mais de uma ativa.
 *
 * ## Por que este arquivo existe
 *
 * O método fazia `->first()` sem `orderBy`. Um business pode ter duas assinaturas ativas ao
 * mesmo tempo — o schema não impede — e aí quem escolhia era o plano de execução do MySQL,
 * que devolve a de menor `id`, isto é a MAIS ANTIGA.
 *
 * Não é detalhe de listagem: `ModuleUtil::hasThePermissionInSubscription` sai daí, e é o
 * portão de visibilidade de módulo do sistema inteiro (300 pontos em 122 arquivos). Uma
 * assinatura velha esquecida apagava, em silêncio, todo módulo habilitado depois dela.
 *
 * Medido em produção em 2026-08-26 (leitura via SSH), antes do conserto:
 *
 *   biz=1   id=1    start=2021-01-12 end=2099-12-31   1 chave `*_module`
 *           id=118  start=2025-04-04 end=2030-05-13  13 chaves `*_module`
 *   -> devolvia a de 2021; o gate enxergava `officeimpresso` e mais nada.
 *
 * ## O que os casos cobrem
 *
 * O teste NÃO afirma "a mais nova ganha" olhando o `orderBy` no fonte — isso seria medir a
 * implementação. Ele monta as duas linhas e pergunta ao método qual voltou, que é o
 * comportamento que os 122 arquivos consomem.
 *
 * Tenant: **99** (fictício de adversário). Nunca `biz=4` — ROTA LIVRE é cliente real
 * ([ADR 0358](memory/decisions/0358-doutrina-de-teste-tenant-98-supersede-0101.md)).
 */

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('Requer schema MySQL UltimatePOS + Superadmin.');
    }

    if (! Schema::hasTable('subscriptions') || ! Schema::hasTable('business')) {
        $this->markTestSkipped('Schema Superadmin ausente nesta lane.');
    }
});

/** Tenant fictício de adversário. */
const ASSIN_BIZ = 99;

/**
 * Marcador de limpeza.
 *
 * Fica no `payment_transaction_id` porque esse campo é do HELPER — nenhum caso abaixo o
 * sobrescreve. Ancorar a limpeza num campo que o caso escolhe (o `package_details`, por
 * exemplo) deixaria linha para trás no primeiro caso que precisasse de outro conteúdo,
 * e a linha órfã contaminaria o caso seguinte.
 */
const ASSIN_MARCA = 'FIXTURE-ASSIN-DETERMINISTA';

if (! function_exists('assinCria')) {
    function assinCria(string $inicio, string $fim, array $detalhes): Subscription
    {
        return Subscription::create([
            'business_id'            => ASSIN_BIZ,
            'package_id'             => 1,
            'paid_via'               => 'offline',
            'payment_transaction_id' => ASSIN_MARCA,
            'start_date'             => $inicio,
            'end_date'               => $fim,
            'trial_end_date'         => null,
            'status'                 => 'approved',
            'package_price'          => 0,
            'package_details'        => $detalhes,
            'created_id'             => 1,
        ]);
    }
}

it('a assinatura ATIVA mais recente vence a antiga — a de 2021 nao pode apagar a de hoje', function () {
    // A velha nasce PRIMEIRO, logo tem `id` menor: é exatamente ela que o `first()` sem
    // ordenação devolvia. Se o teste criasse na ordem inversa, passaria por acidente.
    $velha = assinCria('2021-01-12', '2099-12-31', ['fixture_module' => 0, 'name' => 'velha']);
    $nova  = assinCria(now()->subMonth()->toDateString(), now()->addYear()->toDateString(), [
        'fixture_module' => 1,
        'name'           => 'nova',
    ]);

    expect($velha->id)->toBeLessThan($nova->id);

    $ativa = Subscription::active_subscription(ASSIN_BIZ);

    expect($ativa)->not->toBeNull();
    expect($ativa->id)->toBe($nova->id);

    // O que de fato importa aos 122 arquivos: a chave de módulo que o gate vai ler.
    expect((array) $ativa->package_details)->toHaveKey('fixture_module');
    expect((int) ((array) $ativa->package_details)['fixture_module'])->toBe(1);
})->group('superadmin', 'multi-tenant');

it('empate de start_date desempata pelo id mais alto — a decisao mais recente de quem vendeu', function () {
    $mesmoDia = now()->subDays(3)->toDateString();

    $primeira = assinCria($mesmoDia, now()->addYear()->toDateString(), ['name' => 'primeira']);
    $segunda  = assinCria($mesmoDia, now()->addYear()->toDateString(), ['name' => 'segunda']);

    $ativa = Subscription::active_subscription(ASSIN_BIZ);

    expect($ativa->id)->toBe($segunda->id);
    expect($ativa->id)->toBeGreaterThan($primeira->id);
})->group('superadmin');

it('com UMA assinatura ativa o retorno nao muda — o conserto nao inventa comportamento novo', function () {
    // Controle negativo. Sem ele, um `orderBy` que quebrasse o caso simples passaria
    // despercebido: os dois casos acima só exercitam o cenário de empate.
    $unica = assinCria(now()->subMonth()->toDateString(), now()->addYear()->toDateString(), ['name' => 'unica']);

    $ativa = Subscription::active_subscription(ASSIN_BIZ);

    expect($ativa)->not->toBeNull();
    expect($ativa->id)->toBe($unica->id);
})->group('superadmin');

it('assinatura VENCIDA nao volta, mesmo sendo a de start_date mais recente', function () {
    // A ordenação não pode passar por cima do recorte de vigência: uma assinatura que
    // começou ontem e terminou ontem é a mais "recente" por `start_date` e mesmo assim
    // não vale hoje. Sem este caso, um `orderByDesc` mal colocado devolveria ela.
    $valida   = assinCria(now()->subMonths(2)->toDateString(), now()->addYear()->toDateString(), ['name' => 'valida']);
    $vencida  = assinCria(now()->subDay()->toDateString(), now()->subDay()->toDateString(), ['name' => 'vencida']);

    expect($vencida->id)->toBeGreaterThan($valida->id);

    $ativa = Subscription::active_subscription(ASSIN_BIZ);

    expect($ativa->id)->toBe($valida->id);
})->group('superadmin');

afterEach(function () {
    if (Schema::hasTable('subscriptions')) {
        // `delete()` no query builder: direto na tabela, sem depender de model/scope.
        DB::table('subscriptions')->where('payment_transaction_id', ASSIN_MARCA)->delete();
    }
});
