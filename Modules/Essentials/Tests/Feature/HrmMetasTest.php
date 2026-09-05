<?php

declare(strict_types=1);

use App\User;
use App\Utils\ModuleUtil;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia;
use Modules\Essentials\Entities\EssentialsUserSalesTarget;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * HRM-O7 / PR-9 (Onda 9) — a tela `Essentials/Metas` (`/hrm/sales-target`).
 *
 * Contrato em resources/js/Pages/Essentials/Metas.casos.md. Cada `it` cita o UC que defende.
 *
 * ── POR QUE ESTE ARQUIVO EXISTE, sendo que SalesTargetFaixaValidacaoTest já cobre as faixas
 *
 * Aquele prova a REGRA (o servidor recusa faixa ruim). Este prova a TRAVESSIA: que a tela
 * lista o que o banco tem, e que o TEXTO que o front envia chega ao banco como o MESMO número
 * que o Blade legado gravava. São perguntas diferentes e nenhuma cobre a outra.
 *
 * ── A PROVA DE VALOR (regra mestre, memory/proibicoes.md §CÁLCULO DE VALOR ou ESTOQUE)
 *
 * A tela não calcula nada — mas REENVIA percentual e faixa. Os dois caminhos independentes:
 *
 *   (1) JS: `formatDecimalPtBR(1234.56, 2) === '1.234,56'` — fixado em tests/numberPtBR.test.ts,
 *       que ainda tem o round-trip `parseDecimalPtBR(formatDecimalPtBR(v)) === v`.
 *   (2) PHP: `num_uf('1.234,56') === 1234.56` E o POST com esse texto grava o MESMO valor que
 *       o POST com o número cru — provado aqui, com números concretos, sem confiar no (1).
 *
 * O elo que fecha: o `.tsx` envia exatamente a saída de `formatDecimalPtBR(n, 2)`.
 *
 * Tenant: 98 (ADR 0358 — canônico fictício). NUNCA biz=4. NUNCA biz=1.
 *
 * @covers-us US-ESS-011
 */
const HM_ROTA_LISTA = '/hrm/sales-target';

const HM_ROTA_SALVAR = '/hrm/save-sales-target';

// ---------------------------------------------------------------------------
// (A) Conversão de número — sem banco, sem HTTP. Aritmética conferível a olho.
//     É o lado PHP da dupla prova: a string que o front produz é lida sem ambiguidade.
// ---------------------------------------------------------------------------

it('UC-METAS-06: o texto pt-BR que o front envia e lido pelo num_uf sem ambiguidade', function () {
    $num_uf = fn (string $s) => app(ModuleUtil::class)->num_uf($s);

    // Exatamente as strings que formatDecimalPtBR(n, 2) produz.
    expect($num_uf('1.234,56'))->toBe(1234.56)
        ->and($num_uf('0,00'))->toBe(0.0)
        ->and($num_uf('5,00'))->toBe(5.0)
        ->and($num_uf('20.000,00'))->toBe(20000.0)
        ->and($num_uf('1.000,01'))->toBe(1000.01);
});

it('UC-METAS-06: float com EXATAMENTE 3 decimais e lido como MILHAR — por isso o front manda texto', function () {
    // CONTROLE NEGATIVO — e ele e o motivo de o .tsx nunca mandar `String(n)`.
    //
    // ⚠️ Errata da 1a versao deste teste (2026-09-05, pego pelo CI): eu afirmei aqui que
    // `204.99605` viraria 20.499.605. FALSO HOJE — o proprio incidente de 2026-06-05 fez
    // Util::num_uf ganhar a regra "1 ponto + >=4 digitos = decimal" (Util.php ~L80-90),
    // justamente pra tratar esse numero. Afirmei sem ler o parser ate o fim; o vermelho do
    // CI corrigiu. O que continua REAL e a faixa de 3 digitos, que a heuristica trata como
    // separador de milhar por construcao — e e o unico caso onde `String(n)` do JS explode:
    //
    //     JS String(1.234)  === '1.234'   → num_uf le 1234.0  (mil vezes maior)
    //     JS String(2.5)    === '2.5'     → num_uf le 2.5     (ok, <=2 digitos)
    //     JS String(204.99605) === '204.99605' → num_uf le 204.99605 (ok, >=4 digitos)
    //
    // Nao ha como o parser distinguir "1.234" (milhar pt-BR) de "1.234" (decimal en-US):
    // a string e a mesma. Por isso a defesa e a FORMA de envio, nao o parser.
    $num_uf = fn (string $s) => app(ModuleUtil::class)->num_uf($s);

    expect($num_uf('1.234'))->toBe(1234.0)
        ->and($num_uf('1.234'))->not->toBe(1.234);

    // E a prova de que o caminho da tela nao cai nisso: formatDecimalPtBR SEMPRE emite
    // 2 casas com virgula decimal, entao o mesmo numero chega inequivoco.
    expect($num_uf('1,234'))->toBe(1.234)
        ->and($num_uf('1.234,00'))->toBe(1234.0);
});

// ---------------------------------------------------------------------------
// (B) Travessia — MySQL real (a lane essentials-pest semeia o tenant).
// ---------------------------------------------------------------------------

beforeEach(function () {
    $this->hmSkip = true;

    if (DB::connection()->getDriverName() === 'sqlite') {
        return; // o bloco (A) segue rodando; o resto depende do schema UltimatePOS
    }
    if (! Schema::hasTable('essentials_user_sales_targets')) {
        return;
    }

    $tenant = static::resolveSeededTenant();
    $user = $tenant ? User::where('business_id', $tenant->id)->first() : null;
    if (! $user) {
        return;
    }
    $this->hmTenant = $tenant;
    $this->hmUser = $user;

    // Admin#<biz> → Gate::before autoriza as cláusulas de permissão do controller,
    // isolando o teste na TRAVESSIA (não no gate de permissão nem no de tenant).
    $role = Role::firstOrCreate(
        ['name' => 'Admin#'.$tenant->id, 'guard_name' => 'web'],
        ['business_id' => $tenant->id]
    );
    if (! $user->hasRole($role->name)) {
        $user->assignRole($role);
    }
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    // SetSessionData roda DEPOIS do auth e reconstrói user.business_id do usuário
    // autenticado — setar a sessão à mão deixaria o bloco não-stale e o business_id
    // chegaria nulo no controller (padrão do SalesTargetShiftCrossTenantTest).
    session()->flush();
    $this->actingAs($user);

    $this->hmSkip = false;
});

function hmPular($ctx): void
{
    if ($ctx->hmSkip ?? true) {
        $ctx->markTestSkipped('Requer MySQL com schema UltimatePOS + tenant semeado (lane essentials-pest).');
    }
}

function hmMetas(int $userId)
{
    return EssentialsUserSalesTarget::withoutGlobalScopes()->where('user_id', $userId)->get();
}

/**
 * Versão do asset que o Inertia compara. SEM ela, o partial reload devolve 409
 * (o Inertia entende "cliente com bundle velho" e manda recarregar) — foi o que
 * derrubou 3 casos deste arquivo na 1ª rodada do CI. Mesmo helper do Compras.
 */
function hmInertiaVersion(): string
{
    $manifest = public_path('build-inertia/manifest.json');

    return file_exists($manifest) ? md5_file($manifest) : '1';
}

/** Headers do partial reload que força o `Inertia::defer` do `paginator` a resolver. */
function hmHeadersPaginator(): array
{
    return [
        'X-Inertia' => 'true',
        'X-Inertia-Version' => hmInertiaVersion(),
        'X-Inertia-Partial-Data' => 'paginator',
        'X-Inertia-Partial-Component' => 'Essentials/Metas',
    ];
}

it('UC-METAS-01: a rota renderiza a Page Inertia Essentials/Metas com as props da tela', function () {
    hmPular($this);

    $this->get(HM_ROTA_LISTA)
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Essentials/Metas')
            ->has('filtros.q')
            ->has('sem_imposto')
        );
});

it('UC-METAS-02: a lista traz o colaborador com as faixas JA GRAVADAS, como estao no banco', function () {
    hmPular($this);

    EssentialsUserSalesTarget::create([
        'user_id' => $this->hmUser->id,
        'target_start' => 1000,
        'target_end' => 2000,
        'commission_percent' => 5,
    ]);

    // `paginator` é Inertia::defer — só chega no partial reload, e a resposta do partial
    // é JSON puro (não a view), então NÃO cabe assertInertia aqui: ele exige o payload
    // completo da página e falha com "Not a valid Inertia response". Padrão do Compras.
    $resp = $this->withHeaders(hmHeadersPaginator())->get(HM_ROTA_LISTA);
    $resp->assertOk();

    $linhas = collect($resp->json('props.paginator.data') ?? []);
    $minha = $linhas->firstWhere('id', $this->hmUser->id);

    expect($minha)->not->toBeNull();
    $faixa = collect($minha['faixas'])->firstWhere('inicio', 1000.0);
    expect($faixa)->not->toBeNull()
        ->and($faixa['fim'])->toBe(2000.0)
        ->and($faixa['percentual'])->toBe(5.0);
});

it('UC-METAS-03: colaborador sem faixa vem com a lista de faixas VAZIA — ausencia, nao zero fabricado', function () {
    hmPular($this);

    hmMetas($this->hmUser->id)->each(fn ($m) => $m->delete());

    $resp = $this->withHeaders(hmHeadersPaginator())->get(HM_ROTA_LISTA);
    $resp->assertOk();

    $linhas = collect($resp->json('props.paginator.data') ?? []);
    $minha = $linhas->firstWhere('id', $this->hmUser->id);

    expect($minha)->not->toBeNull()
        ->and($minha['faixas'])->toBe([]);
});

it('UC-METAS-04: a busca ?q= filtra server-side e nao vaza colaborador de outro tenant', function () {
    hmPular($this);

    $vazio = $this->withHeaders(hmHeadersPaginator())
        ->get(HM_ROTA_LISTA.'?q=zzz-nome-que-nao-existe-'.uniqid());
    $vazio->assertOk();
    expect($vazio->json('props.paginator.data'))->toBe([]);

    // Toda linha listada (sem filtro) pertence ao business da sessão — Tier 0 ADR 0093.
    $todas = $this->withHeaders(hmHeadersPaginator())->get(HM_ROTA_LISTA);
    $todas->assertOk();

    $ids = collect($todas->json('props.paginator.data') ?? [])->pluck('id');
    expect($ids)->not->toBeEmpty(); // canário: lista vazia tornaria o assert abaixo vácuo

    $forasteiros = User::withoutGlobalScopes()
        ->whereIn('id', $ids)
        ->where('business_id', '!=', $this->hmTenant->id)
        ->count();

    expect($forasteiros)->toBe(0);
});

it('UC-METAS-05: o texto pt-BR do front grava o MESMO valor que o numero cru — pipeline intocado', function () {
    hmPular($this);

    hmMetas($this->hmUser->id)->each(fn ($m) => $m->delete());

    // Caminho da tela nova: TEXTO pt-BR, como formatDecimalPtBR(n, 2) produz.
    $this->post(HM_ROTA_SALVAR, [
        'user_id' => $this->hmUser->id,
        'sales_amount_start' => ['0' => '1.000,00'],
        'sales_amount_end' => ['0' => '20.000,00'],
        'commission' => ['0' => '2,50'],
    ])->assertRedirect();

    $viaTexto = hmMetas($this->hmUser->id);
    expect($viaTexto)->toHaveCount(1);
    expect((float) $viaTexto[0]->target_start)->toBe(1000.0)
        ->and((float) $viaTexto[0]->target_end)->toBe(20000.0)
        ->and((float) $viaTexto[0]->commission_percent)->toBe(2.5);

    // Caminho legado (número cru, como o Blade manda quando o campo não tem separador):
    // tem de gravar EXATAMENTE o mesmo. É a segunda perna da dupla prova.
    hmMetas($this->hmUser->id)->each(fn ($m) => $m->delete());

    $this->post(HM_ROTA_SALVAR, [
        'user_id' => $this->hmUser->id,
        'sales_amount_start' => ['0' => '1000'],
        'sales_amount_end' => ['0' => '20000'],
        'commission' => ['0' => '2.5'],
    ])->assertRedirect();

    $viaNumero = hmMetas($this->hmUser->id);
    expect($viaNumero)->toHaveCount(1);
    expect((float) $viaNumero[0]->target_start)->toBe((float) $viaTexto[0]->target_start)
        ->and((float) $viaNumero[0]->target_end)->toBe((float) $viaTexto[0]->target_end)
        ->and((float) $viaNumero[0]->commission_percent)->toBe((float) $viaTexto[0]->commission_percent);
});

it('UC-METAS-07: a rota Inertia NAO quebrou o ramo DataTables que a Blade legada consome', function () {
    hmPular($this);

    // Enquanto sales_targets/index.blade.php existir, o jQuery dele chama ESTA rota via ajax.
    $this->get(HM_ROTA_LISTA, ['X-Requested-With' => 'XMLHttpRequest'])
        ->assertOk()
        ->assertJsonStructure(['data', 'recordsTotal', 'recordsFiltered']);
});
