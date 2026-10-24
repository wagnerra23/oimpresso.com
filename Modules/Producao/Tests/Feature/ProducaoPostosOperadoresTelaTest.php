<?php
// @covers-us US-PCP-030

declare(strict_types=1);
// Cobre UC-PCOPE-06 (Postos/Index.casos.md · US-PCP-030 · UC-FLX-08): a tela de Postos mostra e edita os operadores.

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia;
use Modules\Producao\Entities\Posto;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * Fonte: UC-FLX-08 do catálogo de fluxos ("cada etapa tem setor e responsável") e a US-PCP-030 —
 * a regra (OperadoresDoPosto) está provada em ProducaoOperadoresDoPostoContratoTest; aqui se prova o
 * que só a tela faz: a lista de cada posto e os candidatos chegam pelas props deferidas que o browser
 * pede, só da empresa da sessão, e só para quem pode definir.
 * Tenant 98 × 99 (ADR 0358), NUNCA biz=4. ⚠️ SKIP em SQLite: leia assertions, não "0 failed" (LC-13).
 */
beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: schema MySQL do UltimatePOS.');
    }
    if (! Schema::hasTable('pcp_posto_operadores')) {
        $this->markTestSkipped('pcp_posto_operadores ausente — migrations do Modules/Producao não rodaram.');
    }
    $this->B98 = $this->seededTenant();
    $this->b98 = (int) $this->B98->id;
    $this->B99 = $this->seededSupportClientTenant();
    $this->gestor = $this->usuarioComPermissoes(['producao.access', 'producao.etapa.mover'], $this->B98);
    $this->semMover = $this->usuarioComPermissoes(['producao.access'], $this->B98);
    $this->ana = $this->usuarioComPermissoes(['producao.access'], $this->B98);
    $this->bia = $this->usuarioComPermissoes(['producao.access'], $this->B98);
    $this->semProducao = $this->usuarioComPermissoes([], $this->B98);
    $this->op99 = $this->usuarioComPermissoes(['producao.access'], $this->B99);
    // SUPERADMIN: fixture grava em duas empresas; o escopo é provado nos asserts, não aqui.
    $posto = fn (int $biz, string $nome) => Posto::withoutGlobalScopes()->create(['business_id' => $biz, 'codigo' => 'OPT-'.uniqid(),
        'nome' => $nome, 'tipo_custo' => 'pessoa', 'custo_hora' => '30.00']);
    $this->plotter = $posto($this->b98, 'Plotter');
    $this->acabamento = $posto($this->b98, 'Acabamento');
});

function optComo($teste, $user)
{
    $teste->flushSession();

    return $teste->actingAs($user);
}

/** Prop deferida pedida como o browser pede (X-Inertia + X-Requested-With). */
function optProps($teste, $user, string $prop): array
{
    optComo($teste, $user);
    $versao = app(\App\Http\Middleware\HandleInertiaRequests::class)->version(request());

    return (array) $teste->get('/producao/postos', ['X-Inertia' => 'true', 'X-Requested-With' => 'XMLHttpRequest',
        'X-Inertia-Version' => (string) $versao, 'X-Inertia-Partial-Data' => $prop,
        'X-Inertia-Partial-Component' => 'Producao/Postos/Index'])->assertOk()->json('props.'.$prop);
}

function optOperadores(array $postos, int $postoId): array
{
    return collect(collect($postos)->firstWhere('id', $postoId)['operadores'] ?? null)->pluck('nome')->sort()->values()->all();
}

it('UC-PCOPE-06 · UC-FLX-08 · a tela lista os operadores de cada posto e o gestor os troca pelo campo da gaveta', function () {
    $nome = fn ($u) => trim($u->first_name.' '.$u->last_name);
    DB::table('pcp_posto_operadores')->insert([
        ['business_id' => $this->b98, 'posto_id' => $this->plotter->id, 'user_id' => $this->ana->id, 'created_at' => now(), 'updated_at' => now()],
        // Lixo cross-tenant: usuário da 99 pendurado num posto da 98 não pode aparecer na tela.
        ['business_id' => $this->b98, 'posto_id' => $this->plotter->id, 'user_id' => $this->op99->id, 'created_at' => now(), 'updated_at' => now()],
    ]);

    $postos = optProps($this, $this->gestor, 'postos');
    expect(optOperadores($postos, (int) $this->plotter->id))->toBe([$nome($this->ana)]);
    expect(optOperadores($postos, (int) $this->acabamento->id))->toBe([]);

    // Candidatos do campo: gente da 98 com acesso à Produção — nem a 99, nem quem não tem acesso.
    $candidatos = collect(optProps($this, $this->gestor, 'usuarios'))->pluck('id')->map(fn ($id) => (int) $id);
    expect($candidatos->all())->toContain((int) $this->ana->id, (int) $this->bia->id, (int) $this->gestor->id)
        ->and($candidatos->contains((int) $this->op99->id))->toBeFalse()
        ->and($candidatos->contains((int) $this->semProducao->id))->toBeFalse();

    // O campo grava pelo endpoint JSON (como a gaveta faz) e a lista recarregada mostra a troca.
    optComo($this, $this->gestor)->putJson("/producao/postos/{$this->plotter->id}/operadores", ['user_ids' => [$this->bia->id]])->assertOk();
    expect(optOperadores(optProps($this, $this->gestor, 'postos'), (int) $this->plotter->id))->toBe([$nome($this->bia)]);
});

it('UC-PCOPE-06 · UC-FLX-08 · sem producao.etapa.mover a tela mostra os operadores mas não oferece o campo', function () {
    DB::table('pcp_posto_operadores')->insert(['business_id' => $this->b98, 'posto_id' => $this->plotter->id, 'user_id' => $this->ana->id,
        'created_at' => now(), 'updated_at' => now()]);

    optComo($this, $this->semMover)->get('/producao/postos')->assertOk()
        ->assertInertia(fn (AssertableInertia $p) => $p->component('Producao/Postos/Index', false)->where('pode_definir_operadores', false));
    expect(optProps($this, $this->semMover, 'usuarios'))->toBe([]);
    expect(optOperadores(optProps($this, $this->semMover, 'postos'), (int) $this->plotter->id))
        ->toBe([trim($this->ana->first_name.' '.$this->ana->last_name)]);

    optComo($this, $this->gestor)->get('/producao/postos')->assertOk()
        ->assertInertia(fn (AssertableInertia $p) => $p->where('pode_definir_operadores', true));

    // A empresa 99 não vê os postos da 98, nem os operadores deles.
    $da99 = collect(optProps($this, $this->op99, 'postos'))->pluck('id')->map(fn ($id) => (int) $id);
    expect($da99->contains((int) $this->plotter->id))->toBeFalse();
});
