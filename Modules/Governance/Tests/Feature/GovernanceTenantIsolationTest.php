<?php

declare(strict_types=1);

use App\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Jana\Services\CustosService;
use Tests\Support\WithSeededTenant;

/**
 * Isolamento multi-tenant Tier 0 do módulo Governance (ADR 0093, Garantia 4) — 98 × 99.
 *
 * Nasceu como esqueleto de 3 `->todo()` na auditoria RBAC de 2026-11-14. Ativado no lote
 * L1.3 da auditoria de 2026-11-19, depois de medir ONDE o módulo lê dado de empresa:
 *
 *   - `CustosController` (/governance/custos): empresa da SESSÃO, `business_id` do request
 *     ignorado. É o único leitor do módulo com escopo por empresa → casos 1 e 2.
 *     A prova HTTP desse controller já existe (UC-GCUS-01, GovernanceCustosContratoTest, na
 *     lane jana-pest); aqui a prova é na camada do serviço, que é onde o filtro mora.
 *   - `mcp_observability_spans` TEM `business_id`, mas nenhum leitor do módulo filtra por
 *     ele: o `ObservabilitySnapshotService` agrega por módulo, entre empresas, por desenho
 *     (docblock da classe). Não há isolamento a provar ali — afirmar o contrário seria teste
 *     de mentira. Fica registrado, não testado.
 *   - Rotas com id na URL: só `policies/{id}/toggle`, sobre `mcp_governance_rules`, que é
 *     tabela da plataforma (sem `business_id`). O caso 3 trava isso: rota nova com id de
 *     dado de empresa tem de nascer com teste de 404 cruzado.
 *   - `/governance/audit` entre empresas: depende de decisão aberta de [W] (a trilha é da
 *     plataforma ou de cada empresa?). O caso entra depois da decisão — `->todo()` abaixo.
 *
 * Sempre o par: controle negativo (99 não aparece) + controle positivo (98 aparece).
 */

uses(Tests\TestCase::class, DatabaseTransactions::class, WithSeededTenant::class);

beforeEach(function () {
    if (DB::connection()->getDriverName() !== 'mysql'
        || ! Schema::hasTable('jana_conversas')
        || ! Schema::hasTable('jana_mensagens')) {
        $this->markTestSkipped('Requer o schema MySQL da lane governance-pest (jana_conversas/jana_mensagens).');
    }

    $this->bizA = $this->seededTenant();                 // 98
    $this->bizB = $this->seededSupportClientTenant();    // 99
    expect((int) $this->bizA->id)->not->toBe((int) $this->bizB->id);
});

/** Grava uma conversa com 1 mensagem de `$tokensIn + $tokensOut` tokens na empresa dada. */
function govIsoConsumo(int $businessId, int $tokensIn, int $tokensOut): void
{
    $quando = '2001-02-10 10:00:00';
    $userId = User::factory()->create([
        'business_id' => $businessId,
        'username' => 'gov_iso_'.uniqid(),
        'user_type' => 'user',
    ])->id;

    $conversaId = DB::table('jana_conversas')->insertGetId([
        'business_id' => $businessId,
        'user_id' => $userId,
        'titulo' => '__test_gov_iso__'.uniqid(),
        'status' => 'ativa',
        'iniciada_em' => $quando,
        'created_at' => $quando,
        'updated_at' => $quando,
    ]);

    DB::table('jana_mensagens')->insert([
        'conversa_id' => $conversaId,
        'role' => 'user',
        'content' => 'fixture isolamento governance',
        'tokens_in' => $tokensIn,
        'tokens_out' => $tokensOut,
        'created_at' => $quando,
    ]);
}

function govIsoPainel(int $businessId): array
{
    return app(CustosService::class)->painel(
        $businessId,
        Carbon::parse('2001-02-01 00:00:00'),
        Carbon::parse('2001-02-28 23:59:59'),
    );
}

it('Governance · Custos da empresa 98 não somam o consumo da 99 (com controle positivo)', function () {
    govIsoConsumo((int) $this->bizA->id, 1200, 300);
    govIsoConsumo((int) $this->bizB->id, 50000, 50000);

    $kpisA = govIsoPainel((int) $this->bizA->id)['kpis'];
    $kpisB = govIsoPainel((int) $this->bizB->id)['kpis'];

    // positivo: o consumo da 98 aparece na 98
    expect($kpisA['tokens'])->toBe(1500)->and($kpisA['mensagens'])->toBe(1);
    // negativo: o da 99 não entra na 98, e vice-versa
    expect($kpisB['tokens'])->toBe(100000)->and($kpisB['mensagens'])->toBe(1);
});

it('Governance · empresa sem consumo no período não herda o consumo de outra (com controle positivo)', function () {
    govIsoConsumo((int) $this->bizB->id, 700, 300);

    // positivo: a 99 vê o próprio consumo
    expect(govIsoPainel((int) $this->bizB->id)['kpis']['tokens'])->toBe(1000);
    // negativo: a 98, sem nada no período, fica zerada
    expect(govIsoPainel((int) $this->bizA->id)['kpis']['tokens'])->toBe(0);
});

it('Governance · nenhuma rota com id na URL lê dado de empresa (a única é o toggle de policy, tabela da plataforma)', function () {
    $comParametro = collect(\Route::getRoutes()->getRoutes())
        ->filter(fn ($r) => str_starts_with((string) $r->getName(), 'governance.'))
        ->filter(fn ($r) => $r->parameterNames() !== [])
        ->map(fn ($r) => $r->getName())
        ->values()
        ->all();

    // positivo: o toggle existe e é a rota com {id}
    expect($comParametro)->toBe(['governance.policies.toggle']);
    // a tabela que ele escreve não tem business_id (dado da plataforma, ADR 0363)
    if (Schema::hasTable('mcp_governance_rules')) {
        expect(Schema::hasColumn('mcp_governance_rules', 'business_id'))->toBeFalse();
    }
});

it('Governance · /governance/audit entre empresas — entra depois da decisão [W] plataforma × por empresa')->todo();
