<?php

declare(strict_types=1);

use App\Transaction;
use App\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Tests\Support\WithSeededTenant;

/**
 * Contrato executável da fila de Ordens de Serviço — `/repair/repair`.
 *
 * Cada `it()` cita no TÍTULO o UC que defende, porque é o título que o manifesto
 * G-7 lê; UC citado apenas em docblock nunca vira `✅` (o `--report` do
 * casos-coverage-guard contabiliza 141 UCs presos exatamente assim).
 *
 * O que este arquivo NÃO refaz: flag MWART on/off, whitelist de `business_ids`,
 * isolamento cross-tenant e whitelist de `sort` já são defendidos por
 * `RepairIndexMwartTest.php`. Duplicar aqui só para criar a citação seria pagar
 * runtime por nada — esses quatro estão listados como `[BACKLOG]` no casos.md,
 * apontando para o teste que de fato os defende.
 *
 * Tenant: `seededTenant()` = 98 (fictício, canônico — ADR 0358). biz=4 é o cliente
 * real ROTA LIVRE e é PROIBIDO sem exceção; biz=1 é a WR2, empresa em operação, e
 * no CT 100 a base é clone de prod que não se limpa entre runs.
 *
 * @see resources/js/Pages/Repair/Index.casos.md
 * @see resources/js/Pages/Repair/Index.charter.md
 * @see memory/requisitos/Repair/RUNBOOK-repair-index.md
 * @see memory/decisions/0358-doutrina-de-teste-tenant-98-supersede-0101.md
 */
uses(Tests\TestCase::class, WithSeededTenant::class);

const RIDX_MARCA = 'TEST-CONTRATO-RIDX-';

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: o schema UltimatePOS exige MySQL (ADR 0358)');
    }
    foreach (['business', 'users', 'transactions', 'repair_statuses'] as $t) {
        if (! Schema::hasTable($t)) {
            $this->markTestSkipped("Schema incompleto — tabela {$t} ausente; rode migrate + seed mínimo");
        }
    }
});

afterEach(function () {
    try {
        Transaction::where('invoice_no', 'like', RIDX_MARCA.'%')->forceDelete();
    } catch (\Throwable $e) {
        // ambiente sem a coluna/tabela — nada a limpar
    }
    config(['mwart.repair_index.enabled' => false, 'mwart.repair_index.business_ids' => []]);
});

/**
 * Versão do Inertia perguntada AO PRÓPRIO middleware, não reimplementada nem literal.
 *
 * Medido no CT 100 em 2026-09-05: o vizinho `RepairIndexMwartTest` manda `'test'` e
 * falha 6/6 com **409** — mede o handshake de versão, não a tela. Passou despercebido
 * porque a única lane que o roda (`modules-pest`) é SQLite `:memory:` sem migrations,
 * onde ele pula inteiro. Copiar aquele literal para cá teria herdado o defeito.
 */
function ridxInertiaVersion(): string
{
    return (string) app(\App\Http\Middleware\HandleInertiaRequests::class)->version(request());
}

/**
 * Usuário do tenant 98 com as permissões pedidas.
 *
 * `access_all_locations` NÃO é decoração: sem ele `permitted_locations()` devolve
 * lista vazia e o Controller aplica `whereIn('location_id', [])` — a fila volta
 * vazia e TODOS os casos abaixo passariam no vácuo, provando nada.
 */
function ridxUser(int $businessId, array $perms): User
{
    $user = User::factory()->create([
        'business_id' => $businessId,
        'username' => 'ridx_'.uniqid(),
    ]);

    foreach (array_merge(['access_all_locations'], $perms) as $name) {
        $user->givePermissionTo(Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']));
    }

    return $user;
}

function ridxSessao(int $businessId, int $userId): void
{
    session([
        'user.business_id' => $businessId,
        'user.id' => $userId,
        'business.id' => $businessId,
        'business.currency_symbol' => 'R$',
        'business' => ['id' => $businessId, 'name' => 'Tenant 98', 'currency_symbol' => 'R$'],
    ]);
}

/** Cria uma transaction no tenant, marcada para limpeza. Devolve o id. */
function ridxTransacao(int $businessId, string $sufixo, array $over = []): int
{
    $locId = DB::table('business_locations')->where('business_id', $businessId)->value('id');
    if (! $locId) {
        test()->markTestSkipped('Tenant 98 sem business_location — seed mínimo incompleto.');
    }

    return (int) Transaction::create(array_merge([
        'business_id' => $businessId,
        'location_id' => $locId,
        'type' => 'sell',
        'sub_type' => 'repair',
        'status' => 'final',
        'payment_status' => 'due',
        'invoice_no' => RIDX_MARCA.$sufixo,
        'transaction_date' => now(),
        'final_total' => 10.00,
        'created_by' => 1,
    ], $over))->id;
}

/** GET da fila em modo Inertia com a flag ligada. Skip honesto quando o gate de assinatura barra. */
function ridxAbrirFila($test, User $user, string $query = '')
{
    config(['mwart.repair_index.enabled' => true, 'mwart.repair_index.business_ids' => []]);
    ridxSessao((int) $user->business_id, (int) $user->id);

    $resp = $test->actingAs($user)
        ->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => ridxInertiaVersion()])
        ->get('/repair/repair'.$query);

    if ($resp->status() === 403) {
        test()->markTestSkipped('Gate de assinatura/módulo barra repair_module neste ambiente.');
    }

    return $resp;
}

/**
 * invoice_no de todas as linhas devolvidas pela fila.
 *
 * Lê o JSON, e NÃO `assertInertia`: com o header `X-Inertia: true` o servidor responde
 * **JSON**, enquanto `AssertableInertia::fromTestResponse` começa por
 * `assertViewHas('page')` — ou seja, espera a casca HTML. Header + `assertInertia` é
 * combinação impossível, e falha com a mensagem enganosa "Not a valid Inertia response"
 * mesmo quando a resposta é Inertia perfeitamente válida (medido no CT 100 em
 * 2026-09-05: `component=Repair/Index`, `X-Inertia: true`, status 200).
 * Ou se manda o header e lê `->json(...)`, ou se omite o header e usa `assertInertia`.
 */
function ridxFaturas($response): array
{
    return collect($response->json('props.repairs.data') ?? [])
        ->pluck('invoice_no')->filter()->values()->all();
}

it('UC-RIDX-01 · a fila mostra venda-de-reparo, e só ela', function () {
    $biz = $this->seededTenant();
    $user = ridxUser((int) $biz->id, ['repair.view']);

    ridxTransacao((int) $biz->id, 'REPARO');                            // deve aparecer
    ridxTransacao((int) $biz->id, 'VENDA-COMUM', ['sub_type' => null]); // sub_type errado
    ridxTransacao((int) $biz->id, 'RASCUNHO', ['status' => 'draft']);   // status errado

    $faturas = ridxFaturas(ridxAbrirFila($this, $user));

    expect($faturas)->toContain(RIDX_MARCA.'REPARO');
    expect($faturas)->not->toContain(RIDX_MARCA.'VENDA-COMUM');
    expect($faturas)->not->toContain(RIDX_MARCA.'RASCUNHO');
});

it('UC-RIDX-02 · o Controller ecoa o filtro aplicado, para a tela distinguir filtro-vazio de base-vazia', function () {
    $biz = $this->seededTenant();
    $user = ridxUser((int) $biz->id, ['repair.view']);

    // um status REAL do tenant: a validação do Controller exige `exists:repair_statuses,id`
    $statusId = DB::table('repair_statuses')->where('business_id', $biz->id)->value('id');
    if (! $statusId) {
        test()->markTestSkipped('Tenant 98 sem repair_statuses — sem como exercitar o filtro.');
    }

    ridxTransacao((int) $biz->id, 'SEM-STATUS', ['repair_status_id' => null]);

    $comFiltro = ridxAbrirFila($this, $user, '?repair_status_id[]='.$statusId);
    expect($comFiltro->json('props.filters.repair_status_id'))->toBe([(string) $statusId]);
    expect(ridxFaturas($comFiltro))->not->toContain(RIDX_MARCA.'SEM-STATUS');

    // sem filtro: `filters` volta sem filtro ativo — a tela lê "base vazia", não "filtro não achou"
    $semFiltro = ridxAbrirFila($this, $user);
    expect($semFiltro->json('props.filters.repair_status_id'))->toBeNull();
});

it('UC-RIDX-03 · quem só tem repair.view_own não enxerga a fila dos outros', function () {
    $biz = $this->seededTenant();

    $dono = ridxUser((int) $biz->id, ['repair.view_own']);
    $outro = ridxUser((int) $biz->id, ['repair.view']);

    ridxTransacao((int) $biz->id, 'CRIADA-POR-MIM', ['created_by' => $dono->id]);
    ridxTransacao((int) $biz->id, 'SOU-O-TECNICO', ['created_by' => $outro->id, 'res_waiter_id' => $dono->id]);
    ridxTransacao((int) $biz->id, 'NADA-COMIGO', ['created_by' => $outro->id]);

    $meus = ridxFaturas(ridxAbrirFila($this, $dono));

    // as DUAS pernas do critério dual: criei OU sou o service staff
    expect($meus)->toContain(RIDX_MARCA.'CRIADA-POR-MIM');
    expect($meus)->toContain(RIDX_MARCA.'SOU-O-TECNICO');
    expect($meus)->not->toContain(RIDX_MARCA.'NADA-COMIGO');

    // controle positivo: com repair.view a MESMA fila mostra a que não é dele
    expect(ridxFaturas(ridxAbrirFila($this, $outro)))->toContain(RIDX_MARCA.'NADA-COMIGO');
});

it('UC-RIDX-04 · abrir a fila não escreve nada e não enfileira nada', function () {
    $biz = $this->seededTenant();
    $user = ridxUser((int) $biz->id, ['repair.view']);
    ridxTransacao((int) $biz->id, 'INTOCADA');

    // Captura o SQL de ESCRITA emitido durante a requisição, em vez de comparar contagem
    // antes→depois: o banco do CT 100 é compartilhado e outras sessões escrevem enquanto a
    // suíte roda (medido 2026-09-05 — a contagem do tenant 98 andou sozinha entre dois runs).
    // Contagem mediria o tráfego dos vizinhos; o SQL da própria requisição mede a tela.
    $escritas = [];
    DB::listen(function ($q) use (&$escritas) {
        if (! preg_match('/^\s*(insert|update|delete)\b/i', $q->sql)) {
            return;
        }
        foreach (['transactions', 'repair_statuses', 'contacts'] as $tabela) {
            if (stripos($q->sql, $tabela) !== false) {
                $escritas[] = $q->sql;

                return;
            }
        }
    });

    Queue::fake();
    ridxAbrirFila($this, $user)->assertOk();

    expect($escritas)->toBe([]);
    Queue::assertNothingPushed();
});
