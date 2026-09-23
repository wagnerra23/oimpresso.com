<?php

declare(strict_types=1);

use App\Business;
use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * UC-JPERM-04 — custo de IA não vaza no Painel do cliente.
 *
 * ── O CONTRATO: emenda de casos do Cowork (thread 04 do playbook jana). A asserção é
 * sobre o PAYLOAD, não o HTML: o que não vaza é o dado, não o `display:none`.
 *
 * ── A METADE QUE NÃO FOI ESCRITA, e por quê
 * A emenda pede também "contém quando o usuário tem `jana.admin.custos.view`". Isso
 * caducou: a tela de custos saiu da Jana pra `/governance/custos` (ADR 0366 §D-B,
 * comentário em `Modules/Jana/Http/routes.php`). No Painel, custo não entra pra NINGUÉM —
 * asserir presença seria pedir a regressão. Divergência registrada no `_saida-04.md`.
 *
 * TENANT: 98 (ADR 0358). NUNCA biz=4, NUNCA biz=1.
 */
const CUSTOS_BIZ = 98;
const CUSTOS_CHAVE_PROIBIDA = '/(custo|cost|consumo|gasto|usage|tokens?_?(in|out|usad|total))/i';

/** Todas as chaves do payload, com caminho — o vazamento pode estar aninhado. */
function custosChaves(array $dado, string $prefixo = ''): array
{
    $out = [];
    foreach ($dado as $k => $v) {
        $caminho = $prefixo === '' ? (string) $k : "{$prefixo}.{$k}";
        if (is_string($k)) {
            $out[] = $caminho;
        }
        if (is_array($v)) {
            $out = array_merge($out, custosChaves($v, $caminho));
        }
    }

    return $out;
}

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: schema UltimatePOS requer MySQL (ADR 0062).');
    }
    if (! Business::find(CUSTOS_BIZ)) {
        $this->markTestSkipped('business_id='.CUSTOS_BIZ.' ausente — rode o seed do pest-mysql-setup.');
    }
    $user = User::where('business_id', CUSTOS_BIZ)->first();
    if (! $user) {
        $this->markTestSkipped('Sem user em business_id='.CUSTOS_BIZ.'.');
    }

    // Cache do Spatie sobrevive ao rollback da transação: sem limpar, uma permissão
    // criada (e revertida) por um caso anterior volta com id morto — FK/PermissionDoesNotExist
    // conforme a ordem aleatória. Medido no 1º run no CT 100.
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    Permission::findOrCreate('jana.access', 'web');
    Permission::findOrCreate('jana.admin.custos.view', 'web');
    $user->syncRoles([]);
    $user->syncPermissions(['jana.access']);
    $user->forgetCachedPermissions();

    $this->actingAs($user);
    session([
        'user.business_id' => CUSTOS_BIZ,
        'business' => ['id' => CUSTOS_BIZ, 'name' => Business::find(CUSTOS_BIZ)->name],
    ]);
});

it('CONTROLE: a sonda de chave pega um custo aninhado (valor conhecido antes do veredito)', function () {
    $chaves = custosChaves(['a' => ['b' => ['custo_ia_brl' => 1]], 'metas' => []]);

    expect(preg_grep(CUSTOS_CHAVE_PROIBIDA, $chaves))->toBe([2 => 'a.b.custo_ia_brl']);
});

it('UC-JPERM-04 · o render inicial de /ia não tem nenhuma chave de custo/consumo de IA', function () {
    $props = [];
    $this->get(route('jana.index'))
        ->assertStatus(200)
        ->assertInertia(function ($page) use (&$props) {
            $props = $page->toArray()['props'];
        });

    $chaves = custosChaves($props);
    // Anti-vácuo: o payload tem o que o Painel sempre entrega.
    expect($chaves)->toContain('metas');
    expect(array_values(preg_grep(CUSTOS_CHAVE_PROIBIDA, $chaves)))->toBe([]);
})->group('tier0');

it('UC-JPERM-04 · a prop DEFERIDA (coworkAggregates) também não carrega custo', function () {
    // O render inicial não traz props deferidas — o vazamento podia morar só nelas.
    // Partial reload com os headers que o cliente Inertia manda de fato (§5 2026-09-08).
    // A versão sai do próprio render — `Inertia::getVersion()` fora do middleware dá
    // outra e o servidor responde 409 (medido no 1º run).
    $versao = '';
    $this->get(route('jana.index'))->assertInertia(function ($page) use (&$versao) {
        $versao = (string) ($page->toArray()['version'] ?? '');
    });
    $resp = $this->get(route('jana.index'), [
        'X-Inertia' => 'true',
        'X-Requested-With' => 'XMLHttpRequest',
        'X-Inertia-Version' => $versao,
        'X-Inertia-Partial-Component' => 'Jana/Index',
        'X-Inertia-Partial-Data' => 'coworkAggregates',
    ]);

    $resp->assertStatus(200);
    $props = $resp->json('props') ?? [];
    expect($props)->toHaveKey('coworkAggregates');
    expect(array_values(preg_grep(CUSTOS_CHAVE_PROIBIDA, custosChaves($props))))->toBe([]);
})->group('tier0');
