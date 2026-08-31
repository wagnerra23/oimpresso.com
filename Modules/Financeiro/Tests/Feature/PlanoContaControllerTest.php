<?php

declare(strict_types=1);

use App\Business;
use App\User;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;

uses(Tests\TestCase::class);

/**
 * Plano de Contas — guard da tela /financeiro/plano-contas (somente leitura).
 *
 * Cobre os UC do contrato ao lado do .tsx
 * (`resources/js/Pages/Financeiro/PlanoContas/Index.casos.md`):
 *
 *   UC-FPC-01  shape + ordenação por `codigo` (a ordenação É a hierarquia)
 *   UC-FPC-02  Tier 0 — conta de outro business nunca aparece [ADR 0093]
 *   UC-FPC-03  conta `ativo = false` fica fora
 *   UC-FPC-04  `stats` conta exatamente as linhas listadas
 *
 * Âncora: `memory/requisitos/Financeiro/SPEC.md` R-FIN-009 ("47 contas do plano
 * padrão Receita Federal são seedadas com business_id correto"), que declarava
 * `_lacuna_` em "Testado em:" e pedia cobertura. Este arquivo fecha o eixo de
 * LEITURA. O eixo de SEED (o `SeedPlanoContasPadrao` disparar no `BusinessCreated`
 * e proteger 1.1.01.001 / 3.1.01.001) segue SEM cobertura — está no backlog do
 * casos.md, declarado, não vendido como coberto aqui.
 *
 * Sem `@covers-us`: não existe US nem `CU-FIN-*` de plano de contas no SPEC/SDD.
 * Ancorar num alheio seria âncora falsa (mesma postura do `CaixaControllerTest`).
 *
 * Tenant: `$this->seededTenant()` — biz=98 fictício ([ADR 0358]); biz=4 (ROTA
 * LIVRE, cliente real) é proibido sem exceção. Os inserts usam `DB::table` cru
 * de propósito: o Model tem global scope + auto-fill de `business_id`, e semear
 * o tenant B pelo Eloquent silenciosamente reescreveria o business errado —
 * o teste passaria sem nunca ter exercitado o cruzamento.
 */

/**
 * Marca única por execução.
 *
 * `fin_planos_conta` tem `unique(business_id, codigo)` e a base do CT 100 **não é
 * limpa entre runs** (é clone de prod persistente — proibicoes §Ambiente). Código
 * fixo passaria no primeiro run e bateria na constraint no segundo, e a falha
 * pareceria regressão do Controller. O afterEach abaixo ainda apaga o que semeamos.
 */
function planoContaMarca(): string
{
    static $marca = null;

    return $marca ??= strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));
}

/** Cria uma conta do plano direto no banco, sem passar pelo Model (ver docblock). */
function planoContaSeed(int $businessId, string $codigo, string $nome, array $over = []): int
{
    return (int) DB::table('fin_planos_conta')->insertGetId(array_merge([
        'business_id' => $businessId,
        'codigo' => $codigo,
        'nome' => $nome,
        'tipo' => 'ativo',
        'nivel' => substr_count($codigo, '.') + 1,
        'parent_id' => null,
        'natureza' => 'debito',
        'aceita_lancamento' => true,
        'protegido' => false,
        'ativo' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ], $over));
}

/** Usuário do tenant canônico, com a sessão que o Controller lê. */
function planoContaAtor(): array
{
    $business = test()->seededTenant();

    $user = User::where('business_id', $business->id)->first();
    if (! $user) {
        test()->markTestSkipped("Sem user no business {$business->id} — seed mínimo não rodou.");
    }

    if (! DB::getSchemaBuilder()->hasTable('fin_planos_conta')) {
        test()->markTestSkipped('Tabela fin_planos_conta ausente (migration do Financeiro não rodou).');
    }

    return [$user, $business];
}

// Apaga só o que ESTE run semeou (a marca é única por execução). Sem isso a base
// persistente do CT 100 acumula lixo de teste dentro do espelho de dado real.
afterEach(function () {
    if (DB::getSchemaBuilder()->hasTable('fin_planos_conta')) {
        DB::table('fin_planos_conta')->where('codigo', 'like', '9.9.%'.planoContaMarca())->delete();
    }
});

it('UC-FPC-01 · lista o plano com o shape que a tela consome, ordenado por codigo', function () {
    [$user, $business] = planoContaAtor();

    // Inseridos FORA de ordem de propósito: se o Controller perder o orderBy,
    // a asserção de ordenação cai — e não por acaso do id auto-incremento.
    $marca = planoContaMarca();
    planoContaSeed($business->id, "9.9.02.{$marca}", 'Conta Z');
    planoContaSeed($business->id, "9.9.01.{$marca}", 'Conta A');

    $r = $this->actingAs($user)
        ->withSession(['user.business_id' => $business->id])
        ->get('/financeiro/plano-contas');

    $r->assertStatus(200);
    $r->assertInertia(fn (AssertableInertia $page) => $page
        ->component('Financeiro/PlanoContas/Index')
        ->has('planos')
        ->has('stats')
        ->where('planos', function ($planos) use ($marca) {
            $lista = collect($planos);

            // shape: os campos que a tabela renderiza
            $meus = $lista->filter(fn ($p) => str_contains((string) data_get($p, 'codigo'), $marca));
            expect($meus)->toHaveCount(2);
            foreach (['id', 'codigo', 'nome', 'tipo', 'nivel', 'natureza', 'aceita_lancamento', 'protegido'] as $campo) {
                expect(data_get($meus->first(), $campo))->not->toBeNull("campo `{$campo}` ausente no payload");
            }

            // ordenação por codigo — é o que produz a hierarquia visual
            $codigos = $lista->pluck('codigo')->map(fn ($c) => (string) $c)->values()->all();
            $ordenados = $codigos;
            sort($ordenados, SORT_STRING);
            expect($codigos)->toBe($ordenados);

            return true;
        })
    );
});

it('UC-FPC-02 · Tier 0 — conta de outro negocio nunca aparece', function () {
    [$user, $business] = planoContaAtor();

    $outro = Business::where('id', '!=', $business->id)->first();
    if (! $outro) {
        $this->markTestSkipped('Precisa 2+ businesses no banco pro cruzamento Tier 0.');
    }

    $codigoVazado = '9.9.99.'.planoContaMarca();
    planoContaSeed($outro->id, $codigoVazado, 'Conta do outro negocio');

    $r = $this->actingAs($user)
        ->withSession(['user.business_id' => $business->id])
        ->get('/financeiro/plano-contas');

    $r->assertStatus(200);
    $r->assertInertia(fn (AssertableInertia $page) => $page
        ->where('planos', function ($planos) use ($codigoVazado, $business) {
            $lista = collect($planos);

            // nenhuma conta do outro business, nem pelo codigo nem pelo escopo
            expect($lista->pluck('codigo')->contains($codigoVazado))->toBeFalse(
                'conta do outro business apareceu na lista — vazamento cross-tenant (ADR 0093)'
            );

            // e a lista não está vazia por acidente: se estivesse, o assert acima
            // passaria sem provar nada (o "0 failed" que não roda nada)
            expect($lista->count())->toBeGreaterThan(0);

            return true;
        })
    );

    // o vazamento também não pode entrar pelo KPI
    $r->assertInertia(function (AssertableInertia $page) use ($business) {
        $stats = (array) ($page->toArray()['props']['stats'] ?? []);

        $doTenant = DB::table('fin_planos_conta')
            ->where('business_id', $business->id)
            ->where('ativo', true)
            ->whereNull('deleted_at')
            ->count();

        expect((int) ($stats['total'] ?? -1))->toBe(
            $doTenant,
            'o KPI total nao corresponde ao que existe no tenant — ou vazou, ou perdeu linha'
        );

        return true;
    });
});

it('UC-FPC-03 · conta inativa fica fora da lista', function () {
    [$user, $business] = planoContaAtor();

    $codigoInativo = '9.9.03.I'.planoContaMarca();
    $codigoAtivo = '9.9.03.A'.planoContaMarca();
    planoContaSeed($business->id, $codigoInativo, 'Conta desativada', ['ativo' => false]);
    planoContaSeed($business->id, $codigoAtivo, 'Conta em uso');

    $r = $this->actingAs($user)
        ->withSession(['user.business_id' => $business->id])
        ->get('/financeiro/plano-contas');

    $r->assertStatus(200);
    $r->assertInertia(fn (AssertableInertia $page) => $page
        ->where('planos', function ($planos) use ($codigoInativo, $codigoAtivo) {
            $codigos = collect($planos)->pluck('codigo');

            expect($codigos->contains($codigoInativo))->toBeFalse('conta inativa vazou pra lista');
            // controle positivo: a ativa irmã ENTROU — sem isso o assert acima
            // ficaria verde numa lista vazia
            expect($codigos->contains($codigoAtivo))->toBeTrue('a conta ativa de controle não apareceu');

            return true;
        })
    );
});

it('UC-FPC-04 · o KPI conta exatamente as linhas listadas', function () {
    [$user, $business] = planoContaAtor();

    planoContaSeed($business->id, '9.9.04.R'.planoContaMarca(), 'Receita de teste', ['tipo' => 'receita', 'natureza' => 'credito']);
    planoContaSeed($business->id, '9.9.04.D'.planoContaMarca(), 'Despesa de teste', ['tipo' => 'despesa']);

    $r = $this->actingAs($user)
        ->withSession(['user.business_id' => $business->id])
        ->get('/financeiro/plano-contas');

    $r->assertStatus(200);
    $r->assertInertia(function (AssertableInertia $page) {
        $planos = collect($page->toArray()['props']['planos'] ?? []);
        $stats = (array) ($page->toArray()['props']['stats'] ?? []);

        expect($stats['total'] ?? null)->toBe($planos->count(), 'o KPI total nao bate com as linhas listadas');

        $porTipo = array_sum(array_map(
            fn ($k) => (int) ($stats[$k] ?? 0),
            ['receita', 'despesa', 'ativo', 'passivo', 'patrimonio', 'custo']
        ));
        expect($porTipo)->toBeLessThanOrEqual((int) $stats['total'], 'a soma por tipo excede o total');

        // controle positivo: os dois tipos que semeamos aparecem no strip
        expect((int) ($stats['receita'] ?? 0))->toBeGreaterThan(0);
        expect((int) ($stats['despesa'] ?? 0))->toBeGreaterThan(0);

        return true;
    });
});
