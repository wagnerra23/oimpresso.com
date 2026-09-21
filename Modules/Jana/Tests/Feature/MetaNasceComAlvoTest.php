<?php

declare(strict_types=1);

// @covers-us US-COPI-150

use App\Business;
use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Jana\Entities\Meta;
use Modules\Jana\Entities\MetaPeriodo;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * UC-JPAIN-33 (backend) — a meta criada pelo caminho MANUAL nasce com ALVO.
 *
 * POR QUE EXISTE. Medido em produção em 2026-09-21: as 5 metas do tenant tinham ZERO
 * período, ZERO apuração e ZERO fonte. Os 5 cards do Painel saíam idênticos —
 * "<nome> | <unidade> | Aguardando apuração…", sem valor, sem barra e sem projeção.
 *
 * A causa não era cadastro interrompido pelo usuário; era assimetria entre os dois
 * caminhos de criação, lida nos dois controllers:
 *
 *   ChatController@escolher (IA)     -> Meta + MetaPeriodo + MetaFonte + ApurarMetaJob
 *   MetasController@store   (manual) -> Meta
 *
 * E o `StoreMetaRequest` NEM ACEITAVA campo de alvo, então criar uma meta completa por
 * ali era impossível. Este teste trava a simetria no eixo do ALVO.
 *
 * ⚠️ RESIDUAL DECLARADO — a `MetaFonte` continua fora, e de propósito. Sem fonte a meta
 * não apura (o próprio `buildMetasPayload` diz: "`null` = meta sem fonte gravada, que é
 * estado REAL"), mas NÃO EXISTE UI pra ela em lugar nenhum: `copiloto::fontes.show` é
 * somente-leitura e declara no corpo que o editor com prévia é a **US-COPI-040**.
 * Criar fonte aqui exigiria inventar a query — pior que o buraco.
 *
 * ÂNCORA DE CONTRATO (nunca o próprio `.php` — lápide §5 2026-06-05):
 *   · `StorePeriodoRequest` — as MESMAS regras de janela/alvo, mesma tabela destino
 *   · US-COPI-012 (criar meta) · ADR 0093 (multi-tenant) · ADR 0358 (tenant 98)
 *
 * TENANT: 98 (canônico, FICTÍCIO). NUNCA biz=4 (ROTA LIVRE, cliente real) e NUNCA biz=1.
 *
 * @see Modules/Jana/Http/Controllers/MetasController.php
 * @see Modules/Jana/Tests/Feature/MetasControllerBaselineTest.php (harness irmão)
 */

const ALVO_BIZ_CANONICO = 98;

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: schema UltimatePOS requer MySQL (ADR 0062).');
    }
    foreach (['jana_metas', 'jana_meta_periodos'] as $tbl) {
        if (! Schema::hasTable($tbl)) {
            $this->markTestSkipped("Tabela {$tbl} ausente — rode migrate Modules/Jana.");
        }
    }

    $business = Business::find(ALVO_BIZ_CANONICO);
    if (! $business) {
        $this->markTestSkipped('business_id=98 (tenant canônico ADR 0358) ausente — rode o seed do pest-mysql-setup.');
    }
    $user = User::where('business_id', ALVO_BIZ_CANONICO)->first();
    if (! $user) {
        $this->markTestSkipped('Sem user em business_id=98.');
    }
    $this->user = $user;

    // Pré-condição do gate `can:jana.access` do grupo /ia: sem ela o middleware corta
    // com 403 ANTES do controller, e o teste mediria o gate em vez do contrato.
    \Spatie\Permission\Models\Permission::findOrCreate('jana.access', 'web');
    $this->user->givePermissionTo('jana.access');
    $this->user->forgetCachedPermissions();

    $this->actingAs($this->user);
    session([
        'user.business_id' => ALVO_BIZ_CANONICO,
        'business' => ['id' => ALVO_BIZ_CANONICO, 'name' => $business->name],
    ]);
});

function payloadComAlvo(array $over = []): array
{
    return array_merge([
        'nome' => 'Faturamento do mes',
        'slug' => 'alvo_'.uniqid(),
        'unidade' => 'R$',
        'tipo_agregacao' => 'soma',
        'valor_alvo' => 1500,
        'tipo_periodo' => 'mes',
        'data_ini' => '2026-09-01',
        'data_fim' => '2026-09-30',
    ], $over);
}

// ─────────────────────────────────────────────────────────────────────────────
// O ALVO nasce junto
// ─────────────────────────────────────────────────────────────────────────────

it('UC-JPAIN-33 · store com alvo cria a Meta E o MetaPeriodo', function () {
    $payload = payloadComAlvo();

    $this->post('/ia/metas', $payload)->assertRedirect();

    $meta = Meta::where('slug', $payload['slug'])->first();
    expect($meta)->not->toBeNull('a meta não foi criada');

    $periodo = MetaPeriodo::where('meta_id', $meta->id)->first();
    expect($periodo)->not->toBeNull('a meta nasceu SEM período — é a regressão que este UC trava');
    expect((float) $periodo->valor_alvo)->toBe(1500.0);
    expect((string) $periodo->data_ini)->toStartWith('2026-09-01');
    expect((string) $periodo->data_fim)->toStartWith('2026-09-30');
    expect($periodo->tipo_periodo)->toBe('mes');
});

it('UC-JPAIN-33 · com alvo, `periodoAtual` resolve — é o que dá barra e % do alvo ao card', function () {
    // A janela precisa CONTER hoje: `Meta::periodoAtual` filtra por data_ini <= now <= data_fim.
    // Sem isso o card fica sem alvo mesmo tendo período gravado.
    $payload = payloadComAlvo([
        'data_ini' => now()->startOfMonth()->toDateString(),
        'data_fim' => now()->endOfMonth()->toDateString(),
    ]);

    $this->post('/ia/metas', $payload)->assertRedirect();

    $meta = Meta::where('slug', $payload['slug'])->first();
    expect($meta->periodoAtual)->not->toBeNull('periodoAtual nulo: o card cairia em "Aguardando apuração"');
    expect((float) $meta->periodoAtual->valor_alvo)->toBe(1500.0);
});

it('UC-JPAIN-33 · o período fica no MESMO business da meta (Tier 0, via parent)', function () {
    $payload = payloadComAlvo();
    $this->post('/ia/metas', $payload)->assertRedirect();

    $meta = Meta::where('slug', $payload['slug'])->first();
    expect((int) $meta->business_id)->toBe(ALVO_BIZ_CANONICO);
    expect(MetaPeriodo::where('meta_id', $meta->id)->count())->toBe(1);
});

// ─────────────────────────────────────────────────────────────────────────────
// Meia-declaração é recusada — o estado quebrado não volta por outra porta
// ─────────────────────────────────────────────────────────────────────────────

it('UC-JPAIN-33 · alvo SEM janela é recusado (422), e nada é criado', function () {
    $payload = payloadComAlvo();
    unset($payload['data_ini'], $payload['data_fim']);

    $this->from('/ia')->post('/ia/metas', $payload)->assertSessionHasErrors(['data_ini', 'data_fim']);

    expect(Meta::where('slug', $payload['slug'])->exists())->toBeFalse('criou meta com alvo pela metade');
});

it('UC-JPAIN-33 · janela SEM alvo é recusada, e nada é criado', function () {
    $payload = payloadComAlvo();
    unset($payload['valor_alvo']);

    $this->from('/ia')->post('/ia/metas', $payload)->assertSessionHasErrors(['valor_alvo']);

    expect(Meta::where('slug', $payload['slug'])->exists())->toBeFalse('criou meta com janela sem alvo');
});

it('UC-JPAIN-33 · data_fim antes de data_ini é recusada', function () {
    $payload = payloadComAlvo(['data_ini' => '2026-09-30', 'data_fim' => '2026-09-01']);

    $this->from('/ia')->post('/ia/metas', $payload)->assertSessionHasErrors(['data_fim']);
});

// ─────────────────────────────────────────────────────────────────────────────
// RETROCOMPATIBILIDADE — o Blade legado manda só identidade e NÃO pode quebrar
// ─────────────────────────────────────────────────────────────────────────────

it('UC-JPAIN-33 · payload SEM alvo continua criando a meta (Blade legado não quebra)', function () {
    // `metas/create.blade.php` manda só os 4 campos de identidade. Tornar o alvo
    // obrigatório no request devolveria 422 pra ele — por isso é `nullable` com
    // `required_with`. O cutover do Blade é o PR-4 do RUNBOOK-metas §9.4.
    $payload = [
        'nome' => 'Legado sem alvo',
        'slug' => 'legado_'.uniqid(),
        'unidade' => 'qtd',
        'tipo_agregacao' => 'contagem',
    ];

    $this->post('/ia/metas', $payload)->assertRedirect();

    $meta = Meta::where('slug', $payload['slug'])->first();
    expect($meta)->not->toBeNull('o caminho legado parou de criar meta');
    // e SEM período, que é o estado honesto: não veio alvo, não se inventa um
    expect(MetaPeriodo::where('meta_id', $meta->id)->count())->toBe(0);
});
