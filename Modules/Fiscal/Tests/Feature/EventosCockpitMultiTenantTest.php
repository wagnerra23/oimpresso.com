<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\NfeBrasil\Models\NfeEvento;

uses(Tests\TestCase::class);

/**
 * PR #2 Wave Eventos Fiscal — isolation Tier 0 + mapeamento de tipos canônicos SEFAZ.
 *
 * NfeEvento = append-only log (UPDATED_AT = null). HasBusinessScope ADR 0093.
 *
 * ⚠️ 2026-07-17 — o guard de isolamento passava VÁCUO (falso-verde). Setava
 * session sem actingAs, então ScopeByBusiness no-opava (early-return
 * `! auth()->check()`, ScopeByBusiness.php:26) e a contagem cross-tenant dava 0
 * por AUSÊNCIA de dado (nfe_eventos vazia), NÃO por filtro. Fix (espelha
 * Modules/NfeBrasil/Tests/Feature/NfeBrasilMultiTenantIsolationTest): actingAs(user
 * biz=1) + CRIA uma linha biz=99 real (bypass do scope) pra o scope ter o que
 * excluir, MAIS um controle positivo biz=1 (garante que o filtro é por-tenant, não
 * "esconde tudo"). ADR 0093 (multi-tenant Tier 0) + ADR 0101 (biz=1).
 *
 * @covers-us US-FISCAL-007
 * (ADR 0273/0303 — este arquivo esta na allowlist da lane que emite JUnit,
 *  entao o "verde" declarado aqui e alcancavel; ver SDD Fiscal §8.1.)
 */

const EVENTOS_BIZ_WAGNER   = 1;
const EVENTOS_BIZ_FICTICIO = 99; // nfe_eventos→nfe_emissoes (SEM FK a business) ⇒ biz fictício OK
const EVENTOS_TAG_TEST     = 'FISCAL-EVENTOS-ISO-TEST';

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: NfeEvento requer schema MySQL (ADR 0101)');
    }
    if (! Schema::hasTable('nfe_eventos') || ! Schema::hasTable('nfe_emissoes')) {
        $this->markTestSkipped('nfe_eventos/nfe_emissoes table missing');
    }

    // O global scope ScopeByBusiness só filtra com usuário AUTENTICADO — faz early-return
    // em `! auth()->check()` (ScopeByBusiness.php:26) e lê a business ativa de
    // session('user.business_id') (NÃO 'business.id'). Sem actingAs o scope no-opa e o
    // guard de isolamento passa vácuo. Autenticamos um usuário do biz=1 (semeado pelo
    // pest-mysql-setup; sem role → não é superadmin). ADR 0093.
    $this->actingAs(\App\User::where('business_id', EVENTOS_BIZ_WAGNER)->firstOrFail());
});

afterEach(function () {
    // Cleanup defensivo — só os registros marcados pelo teste (tag específica),
    // em qualquer business (biz=1 do controle positivo + biz=99 cross-tenant).
    if (DB::connection()->getDriverName() === 'sqlite') {
        return;
    }
    if (! Schema::hasTable('nfe_eventos') || ! Schema::hasTable('nfe_emissoes')) {
        return;
    }
    try {
        // Evento primeiro (FK ON DELETE CASCADE cobriria, mas explícito é seguro).
        DB::table('nfe_eventos')
            ->where('justificativa', 'like', '%' . EVENTOS_TAG_TEST . '%')
            ->delete();
        DB::table('nfe_emissoes')
            ->whereJsonContains('metadata->tag', EVENTOS_TAG_TEST)
            ->delete();
    } catch (\Throwable) {
        // Cleanup best-effort; falha aqui não invalida assertions.
    }
});

it('UC-FEVT-03 · mapa de TIPOS cobre os 7 códigos SEFAZ canônicos esperados pelo cockpit', function () {
    $tipos = \Modules\Fiscal\Http\Controllers\EventosController::TIPOS;

    expect($tipos)
        ->toHaveKeys(['110110', '110111', '110140', '210200', '210210', '210220', '210240'])
        ->and($tipos['110110']['kind'])->toBe('cce')
        ->and($tipos['110111']['kind'])->toBe('cancel')
        ->and($tipos['110140']['kind'])->toBe('epec')
        ->and($tipos['210200']['kind'])->toBe('manifest');
});

it('UC-FEVT-01 · NfeEvento HasBusinessScope esconde cross-tenant — listagem timeline scoped', function () {
    // Emissão pai + evento CROSS-TENANT (biz=99). nfe_emissoes NÃO tem FK a business ⇒
    // biz fictício OK. numero randômico evita colisão do UNIQUE (business_id,modelo,serie,numero).
    $emissaoCross = DB::table('nfe_emissoes')->insertGetId([
        'business_id' => EVENTOS_BIZ_FICTICIO,
        'modelo'      => '55',
        'serie'       => '9',
        'numero'      => random_int(900000, 999999),
        'status'      => 'autorizada',
        'valor_total' => 10.00,
        'metadata'    => json_encode(['tag' => EVENTOS_TAG_TEST]),
        'created_at'  => now(),
        'updated_at'  => now(),
    ]);
    $eventoCross = DB::table('nfe_eventos')->insertGetId([
        'business_id'   => EVENTOS_BIZ_FICTICIO,
        'emissao_id'    => $emissaoCross,
        'tipo'          => '110111', // cancelamento
        'justificativa' => 'cross-tenant ' . EVENTOS_TAG_TEST,
        'status'        => 'autorizado',
        'created_at'    => now(),
    ]);

    // Emissão pai + evento do PRÓPRIO tenant (biz=1) — controle positivo.
    $emissaoOwn = DB::table('nfe_emissoes')->insertGetId([
        'business_id' => EVENTOS_BIZ_WAGNER,
        'modelo'      => '55',
        'serie'       => '9',
        'numero'      => random_int(800000, 899999),
        'status'      => 'autorizada',
        'valor_total' => 20.00,
        'metadata'    => json_encode(['tag' => EVENTOS_TAG_TEST]),
        'created_at'  => now(),
        'updated_at'  => now(),
    ]);
    $eventoOwn = DB::table('nfe_eventos')->insertGetId([
        'business_id'   => EVENTOS_BIZ_WAGNER,
        'emissao_id'    => $emissaoOwn,
        'tipo'          => '110111',
        'justificativa' => 'own-tenant ' . EVENTOS_TAG_TEST,
        'status'        => 'autorizado',
        'created_at'    => now(),
    ]);

    session(['business.id' => EVENTOS_BIZ_WAGNER, 'user.business_id' => EVENTOS_BIZ_WAGNER]);

    // (1) A timeline scoped de biz=1 NÃO enxerga o evento biz=99.
    expect(NfeEvento::where('id', $eventoCross)->first())->toBeNull();

    // (2) Mas o registro EXISTE (bypass do scope) — sem isso o passo (1) seria vácuo
    //     (0 por ausência de dado, não por filtro).
    $real = NfeEvento::withoutGlobalScopes()->where('id', $eventoCross)->first(); // SUPERADMIN: provar existência cross-tenant
    expect($real)->not->toBeNull();
    expect((int) $real->business_id)->toBe(EVENTOS_BIZ_FICTICIO);

    // (3) Controle positivo: o evento do PRÓPRIO tenant (biz=1) É visível sob o scope —
    //     prova que o filtro é por-tenant e não "esconde tudo" (over-scoping).
    expect(NfeEvento::where('id', $eventoOwn)->first())->not->toBeNull();
});

it('UC-FEVT-02 · NfeEvento é append-only (UPDATED_AT = null) — eventos não devem ser editados', function () {
    expect(NfeEvento::UPDATED_AT)->toBeNull();
});

/**
 * Onda 7 — export CSV da timeline.
 *
 * Os dois UC abaixo moram NESTE arquivo, e não num `EventosExportCsvTest.php`
 * novo, por uma razão medida: a allowlist da lane required
 * (`.github/workflows/nfebrasil-pest.yml:245-284`) lista os testes UM A UM. Um
 * arquivo novo não seria executado, e o UC nasceria mudo — verde por
 * NÃO-EXECUÇÃO. Este arquivo já está na allowlist e já é o dono do tema
 * "Eventos + isolamento".
 */

/**
 * biz=1 com `fiscal.access` — o `beforeEach` autentica um user SEM permissão.
 *
 * `findOrCreate` em vez de `givePermissionTo` direto: os testes fiscais vizinhos
 * assumem que a permission já foi semeada, e aqui isso seria uma dependência
 * silenciosa de ambiente — se `fiscal.access` não existisse na base da lane, o
 * Spatie lançaria `PermissionDoesNotExist` e o UC morreria por erro de setup, não
 * por regressão. É idempotente: reusa a permission quando ela já existe.
 */
function eventosUserComAcessoFiscal(): \App\User
{
    $u = \App\User::factory()->create(['business_id' => EVENTOS_BIZ_WAGNER]);
    $u->givePermissionTo(
        \Spatie\Permission\Models\Permission::findOrCreate('fiscal.access', 'web')
    );

    return $u;
}

/** Cria emissão + evento de um business, sem passar pelo global scope. */
function eventosSemearEvento(int $businessId, string $tipo, string $marcador): int
{
    $emissaoId = DB::table('nfe_emissoes')->insertGetId([
        'business_id' => $businessId,
        'modelo'      => '55',
        'serie'       => '9',
        'numero'      => random_int(700000, 799999),
        'status'      => 'autorizada',
        'valor_total' => 30.00,
        'metadata'    => json_encode(['tag' => EVENTOS_TAG_TEST]),
        'created_at'  => now(),
        'updated_at'  => now(),
    ]);

    return DB::table('nfe_eventos')->insertGetId([
        'business_id'   => $businessId,
        'emissao_id'    => $emissaoId,
        'tipo'          => $tipo,
        'justificativa' => $marcador . ' ' . EVENTOS_TAG_TEST,
        'status'        => 'autorizado',
        'cstat_evento'  => '135',
        'created_at'    => now(),
    ]);
}

it('UC-FEVT-05 · o CSV exportado nunca traz evento de outro business', function () {
    eventosSemearEvento(EVENTOS_BIZ_FICTICIO, '110111', 'VAZAMENTO-CROSS-TENANT');
    eventosSemearEvento(EVENTOS_BIZ_WAGNER, '110111', 'LINHA-DO-PROPRIO-TENANT');

    $this->actingAs(eventosUserComAcessoFiscal());
    session(['business.id' => EVENTOS_BIZ_WAGNER, 'user.business_id' => EVENTOS_BIZ_WAGNER]);

    $response = $this->get('/fiscal/eventos/export?kind=todos&dias=30');
    $response->assertOk();

    $csv = $response->streamedContent();

    // (1) O arquivo abre no Excel pt-BR: BOM UTF-8 + separador `;`.
    expect(substr($csv, 0, 3))->toBe("\xEF\xBB\xBF");
    expect((string) $response->headers->get('Content-Type'))->toContain('text/csv');
    expect($csv)->toContain('Quando;Tipo;Sequência;Documento;Justificativa;Autor;cstat');

    // (2) Controle positivo — a linha do PRÓPRIO tenant ESTÁ no arquivo. Sem ele,
    //     o passo (3) passaria vácuo (ausência por CSV vazio, não por filtro).
    expect($csv)->toContain('LINHA-DO-PROPRIO-TENANT');

    // (3) Tier 0 — a linha do biz=99 NÃO está no arquivo.
    expect($csv)->not->toContain('VAZAMENTO-CROSS-TENANT');
});

it('UC-FEVT-06 · o CSV respeita o filtro de tipo ativo — exporta o recorte, não a timeline inteira', function () {
    eventosSemearEvento(EVENTOS_BIZ_WAGNER, '110111', 'EVENTO-DE-CANCELAMENTO');
    eventosSemearEvento(EVENTOS_BIZ_WAGNER, '110110', 'EVENTO-DE-CARTA-CORRECAO');

    $this->actingAs(eventosUserComAcessoFiscal());
    session(['business.id' => EVENTOS_BIZ_WAGNER, 'user.business_id' => EVENTOS_BIZ_WAGNER]);

    $csv = $this->get('/fiscal/eventos/export?kind=cancel&dias=30')->assertOk()->streamedContent();

    // Filtrou cancelamento: leva cancelamento, não leva carta de correção.
    expect($csv)->toContain('EVENTO-DE-CANCELAMENTO');
    expect($csv)->not->toContain('EVENTO-DE-CARTA-CORRECAO');

    // Controle do filtro oposto — prova que o recorte é POR TIPO e não
    // "esconde tudo menos o primeiro".
    $csvCce = $this->get('/fiscal/eventos/export?kind=cce&dias=30')->assertOk()->streamedContent();
    expect($csvCce)->toContain('EVENTO-DE-CARTA-CORRECAO');
    expect($csvCce)->not->toContain('EVENTO-DE-CANCELAMENTO');
});

it('UC-FEVT-07 · a janela do CSV é clampada nas opções da tela — `?dias` arbitrário não vira varredura', function () {
    $controller = new \Modules\Fiscal\Http\Controllers\EventosController();
    $parseDias = (new ReflectionClass($controller))->getMethod('parseDias');
    $parseDias->setAccessible(true);

    // As três janelas que a UI oferece passam intactas.
    expect($parseDias->invoke($controller, '7'))->toBe(7)
        ->and($parseDias->invoke($controller, '30'))->toBe(30)
        ->and($parseDias->invoke($controller, '90'))->toBe(90);

    // Qualquer outra coisa cai no default — `?dias=99999` não varre a tabela.
    expect($parseDias->invoke($controller, '99999'))->toBe(30)
        ->and($parseDias->invoke($controller, '-1'))->toBe(30)
        ->and($parseDias->invoke($controller, 'abc'))->toBe(30)
        ->and($parseDias->invoke($controller, null))->toBe(30);
});
