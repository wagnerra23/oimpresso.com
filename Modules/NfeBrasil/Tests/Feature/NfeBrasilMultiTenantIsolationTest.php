<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\NfeBrasil\Models\NfeEmissao;
use Modules\NfeBrasil\Models\NfeInutilizacao;

uses(Tests\TestCase::class);

/**
 * Wave 13 D2 — Isolamento multi-tenant Tier 0 dos Models NfeBrasil.
 *
 * Modelos NfeEmissao + NfeInutilizacao usam trait `App\Concerns\HasBusinessScope`
 * (global scope automático). Esse teste verifica:
 *
 *   1. Global scope BusinessScope filtra biz=99 transparentemente quando biz=1 é ativo
 *   2. Coluna `business_id` é NOT NULL nas tabelas críticas (nfe_emissoes, nfe_inutilizacoes)
 *   3. Numeração SEFAZ é isolada por (business_id, modelo, serie) — biz=1 não vê n.º biz=99
 *   4. Range de inutilização também é per-tenant — fechamento ano fiscal isolado
 *
 * ADR 0093: business_id Tier 0 IRREVOGÁVEL — toda Model que toca dados negócio.
 * ADR 0101: NUNCA usar biz=4 (ROTA LIVRE — Larissa, cliente real prod) em tests.
 *
 * Edge fiscal: CNPJ 11.222.333/0001-44 (pii-allowlist: CNPJ fictício oficial da Receita,
 * NUNCA real) é o CNPJ de teste documentado por MARTINS no Receita Federal "Manual da NFe"
 * — fictício, NUNCA emite contra SEFAZ com ele em produção (só homolog/test).
 *
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 * @see memory/decisions/0101-tests-business-id-1-nunca-cliente.md
 * @see Modules/NfeBrasil/Models/NfeEmissao.php (HasBusinessScope)
 * @see Modules/NfeBrasil/Models/NfeInutilizacao.php (HasBusinessScope)
 */

const NFE_BIZ_WAGNER = 1;
const NFE_BIZ_FICTICIO = 99;
const NFE_TAG_TEST = 'WAVE13-ISO-TEST';

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: NfeBrasil requer schema MySQL UltimatePOS com FKs business/transactions (ADR 0101)');
    }
    if (! Schema::hasTable('nfe_emissoes')) {
        $this->markTestSkipped('nfe_emissoes table missing — rode Modules/NfeBrasil migrate primeiro');
    }
    if (! Schema::hasTable('nfe_inutilizacoes')) {
        $this->markTestSkipped('nfe_inutilizacoes table missing — rode Modules/NfeBrasil migrate primeiro');
    }

    // O global scope ScopeByBusiness só filtra com usuário AUTENTICADO — faz
    // early-return em `! auth()->check()` (ScopeByBusiness.php:26) e lê a business
    // ativa de session('user.business_id') (NÃO 'business.id'). Sem actingAs o scope
    // no-opa e estes "guards de isolamento" passariam vácuo. Autenticamos um usuário
    // do biz=1 (semeado pelo pest-mysql-setup; sem role → não é superadmin). Cada
    // teste seta session('user.business_id') pra escolher o tenant ativo. ADR 0093.
    $this->actingAs(\App\User::where('business_id', NFE_BIZ_WAGNER)->firstOrFail());
});

afterEach(function () {
    // Cleanup defensivo — só registros marcados pelo teste (sem afetar dados reais).
    // Guard: se rodou em SQLite ou tabelas ausentes (beforeEach skip), pula cleanup.
    if (DB::connection()->getDriverName() === 'sqlite') {
        return;
    }
    if (! Schema::hasTable('nfe_emissoes') || ! Schema::hasTable('nfe_inutilizacoes')) {
        return;
    }
    try {
        NfeEmissao::withoutGlobalScopes()->withTrashed()
            ->whereIn('business_id', [NFE_BIZ_WAGNER, NFE_BIZ_FICTICIO])
            ->whereJsonContains('metadata->tag', NFE_TAG_TEST)
            ->forceDelete();
        NfeInutilizacao::withoutGlobalScopes()
            ->whereIn('business_id', [NFE_BIZ_WAGNER, NFE_BIZ_FICTICIO])
            ->where('justificativa', 'like', '%' . NFE_TAG_TEST . '%')
            ->delete();
    } catch (\Throwable) {
        // Cleanup best-effort; falha aqui não invalida assertions
    }
});

// ------------------------------------------------------------------
// Contract: business_id NOT NULL nas tabelas fiscais críticas
// ------------------------------------------------------------------

it('nfe_emissoes tem coluna business_id NOT NULL', function () {
    expect(Schema::hasColumn('nfe_emissoes', 'business_id'))->toBeTrue();
    // Portável (binding em WHERE) — `SHOW COLUMNS ... LIKE ?` estoura "syntax error
    // near '?'" no MySQL (placeholder proibido em SHOW). information_schema aceita.
    $isNullable = DB::table('information_schema.columns')
        ->where('table_schema', DB::connection()->getDatabaseName())
        ->where('table_name', 'nfe_emissoes')
        ->where('column_name', 'business_id')
        ->value('IS_NULLABLE');
    expect($isNullable)->toBe('NO'); // NOT NULL
});

it('nfe_inutilizacoes tem coluna business_id NOT NULL', function () {
    expect(Schema::hasColumn('nfe_inutilizacoes', 'business_id'))->toBeTrue();
    $isNullable = DB::table('information_schema.columns')
        ->where('table_schema', DB::connection()->getDatabaseName())
        ->where('table_name', 'nfe_inutilizacoes')
        ->where('column_name', 'business_id')
        ->value('IS_NULLABLE');
    expect($isNullable)->toBe('NO'); // NOT NULL
});

// ------------------------------------------------------------------
// NfeEmissao — global scope HasBusinessScope isola cross-tenant
// ------------------------------------------------------------------

it('NfeEmissao biz=99 NÃO aparece quando session ativa é biz=1', function () {
    // Cria emissão pertencente ao biz=99 (ficticio) via insert direto (bypass scope on write).
    $emissaoId = DB::table('nfe_emissoes')->insertGetId([
        'business_id' => NFE_BIZ_FICTICIO,
        'modelo'      => '55',
        'serie'       => '1',
        'numero'      => 999991,
        'status'      => 'pendente',
        'valor_total' => 100.00,
        'metadata'    => json_encode(['tag' => NFE_TAG_TEST]),
        'created_at'  => now(),
        'updated_at'  => now(),
    ]);

    // Session simula usuário Wagner biz=1 ativo
    session(['user.business_id' => NFE_BIZ_WAGNER]);

    // Global scope BusinessScope deve filtrar — biz=1 NÃO enxerga biz=99
    $vaza = NfeEmissao::where('id', $emissaoId)->first();
    expect($vaza)->toBeNull();

    // Mas com withoutGlobalScopes (SUPERADMIN) consegue ver — prova que existe
    $emissaoReal = NfeEmissao::withoutGlobalScopes()->where('id', $emissaoId)->first();
    expect($emissaoReal)->not->toBeNull();
    expect((int) $emissaoReal->business_id)->toBe(NFE_BIZ_FICTICIO);
});

it('NfeEmissao biz=1 aparece quando session ativa é biz=1', function () {
    $emissaoId = DB::table('nfe_emissoes')->insertGetId([
        'business_id' => NFE_BIZ_WAGNER,
        'modelo'      => '55',
        'serie'       => '1',
        'numero'      => 999992,
        'status'      => 'pendente',
        'valor_total' => 200.00,
        'metadata'    => json_encode(['tag' => NFE_TAG_TEST]),
        'created_at'  => now(),
        'updated_at'  => now(),
    ]);

    session(['user.business_id' => NFE_BIZ_WAGNER]);

    $emissao = NfeEmissao::where('id', $emissaoId)->first();
    expect($emissao)->not->toBeNull();
    expect((int) $emissao->business_id)->toBe(NFE_BIZ_WAGNER);
    expect((int) $emissao->numero)->toBe(999992);
});

// ------------------------------------------------------------------
// NfeInutilizacao — global scope HasBusinessScope isola cross-tenant
// ------------------------------------------------------------------

it('NfeInutilizacao biz=99 NÃO aparece quando session ativa é biz=1', function () {
    $inutId = DB::table('nfe_inutilizacoes')->insertGetId([
        'business_id'    => NFE_BIZ_FICTICIO,
        'modelo'         => '55',
        'serie'          => '1',
        'numero_de'      => 990001,
        'numero_ate'     => 990010,
        'justificativa'  => 'Range de teste isolamento ' . NFE_TAG_TEST,
        'status'         => 'pendente',
        'created_at'     => now(),
        'updated_at'     => now(),
    ]);

    session(['user.business_id' => NFE_BIZ_WAGNER]);

    $vaza = NfeInutilizacao::where('id', $inutId)->first();
    expect($vaza)->toBeNull();

    $real = NfeInutilizacao::withoutGlobalScopes()->where('id', $inutId)->first();
    expect($real)->not->toBeNull();
    expect((int) $real->business_id)->toBe(NFE_BIZ_FICTICIO);
});

// ------------------------------------------------------------------
// Numeração SEFAZ isolada por (business_id, modelo, serie)
// ------------------------------------------------------------------
// Regra fiscal: cada CNPJ emitente tem sua própria sequência. biz=1 numero=10
// NÃO pode conflitar com biz=99 numero=10. Index UNIQUE (business_id, modelo,
// serie, numero) garante isso no DB. Pest valida o contrato.

it('numeração NFe é isolada por business_id — biz=1 e biz=99 podem ter mesmo numero/modelo/serie', function () {
    // Mesmo numero/modelo/serie em dois businesses diferentes — DEVE permitir.
    DB::table('nfe_emissoes')->insert([
        'business_id' => NFE_BIZ_WAGNER,
        'modelo'      => '55',
        'serie'       => '7',
        'numero'      => 777001,
        'status'      => 'autorizada',
        'valor_total' => 50.00,
        'metadata'    => json_encode(['tag' => NFE_TAG_TEST]),
        'created_at'  => now(),
        'updated_at'  => now(),
    ]);

    DB::table('nfe_emissoes')->insert([
        'business_id' => NFE_BIZ_FICTICIO,
        'modelo'      => '55',
        'serie'       => '7',
        'numero'      => 777001, // mesmo numero/modelo/serie — permitido
        'status'      => 'autorizada',
        'valor_total' => 75.00,
        'metadata'    => json_encode(['tag' => NFE_TAG_TEST]),
        'created_at'  => now(),
        'updated_at'  => now(),
    ]);

    $contagem = DB::table('nfe_emissoes')
        ->where('modelo', '55')
        ->where('serie', '7')
        ->where('numero', 777001)
        ->count();

    expect($contagem)->toBe(2);

    // Cada business vê só o seu (via global scope)
    session(['user.business_id' => NFE_BIZ_WAGNER]);
    $emissaoBiz1 = NfeEmissao::where('numero', 777001)->where('serie', '7')->first();
    expect($emissaoBiz1)->not->toBeNull();
    expect((int) $emissaoBiz1->business_id)->toBe(NFE_BIZ_WAGNER);
    expect((float) $emissaoBiz1->valor_total)->toBe(50.00);
});

// ------------------------------------------------------------------
// Cross-tenant query explícita: where business_id=X NUNCA vaza Y
// ------------------------------------------------------------------

it('NfeEmissao with explicit where business_id NÃO vaza cross-tenant', function () {
    DB::table('nfe_emissoes')->insert([
        'business_id' => NFE_BIZ_FICTICIO,
        'modelo'      => '65',
        'serie'       => '1',
        'numero'      => 888001,
        'status'      => 'autorizada',
        'valor_total' => 999.99,
        'metadata'    => json_encode(['tag' => NFE_TAG_TEST]),
        'created_at'  => now(),
        'updated_at'  => now(),
    ]);

    // Query com filtro explícito biz=1 NÃO retorna o registro biz=99
    // Mesmo sem scope (bypass via withoutGlobalScopes pra simular query SQL raw)
    $resultado = NfeEmissao::withoutGlobalScopes()
        ->where('business_id', NFE_BIZ_WAGNER)
        ->where('numero', 888001)
        ->where('modelo', '65')
        ->get();

    expect($resultado)->toHaveCount(0);
});
