<?php

declare(strict_types=1);

use App\Business;
use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Modules\Jana\Entities\AcaoAprovacao;
use Modules\Jana\Services\AcaoHitlService;
use Spatie\Permission\Models\Permission;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * Aba Ações da Jana (`/ia/acoes` — a fila HITL) — CONTRATO. Fecha os UC do `Acoes.casos.md`.
 *
 * As asserções vêm de fora do `.tsx`: charter, `jana-acoes.contract.json` (copy + ordem),
 * `AcaoHitlService::ACOES`/`TITULOS` (paridade rótulo↔chave, como o UC-JPAIN-12 do Painel)
 * e a âncora `jana-telas-novas.jsx` §JmAcoesFila.
 *
 * Tenant: `seededTenant()`. ⚠️ skip sai exit 0: leia ASSERTIONS, não "0 failed" (LC-13).
 */
const ACOES_CONTRATO = 'prototipo-ui/contrato/jana-acoes.contract.json';

function acoesBootstrap(): array
{
    try {
        $business = test()->seededTenant();
    } catch (\Throwable $e) {
        test()->markTestSkipped('Tabela business indisponível: '.$e->getMessage());
    }

    $user = User::where('business_id', $business->id)->first();

    if (! $user) {
        test()->markTestSkipped("Sem user em business_id={$business->id}.");
    }

    try {
        Permission::findOrCreate('jana.access', 'web');
        $user->givePermissionTo('jana.access');
    } catch (\Throwable $e) {
        test()->markTestSkipped('Não foi possível garantir a permission jana.access: '.$e->getMessage());
    }

    test()->actingAs($user);
    session([
        'user.business_id' => $business->id,
        'user.id'          => $user->id,
        'business'         => ['id' => $business->id, 'name' => $business->name],
    ]);

    return [$business, $user];
}

/**
 * A entry da Jana no `shell.menu` é gated pela ASSINATURA (Camada 1 — `jana_module` no
 * `package_details`, `ModuleUtil::hasThePermissionInSubscription`), não só pela permission.
 * O tenant do seed pode não ter assinatura: garantimos uma (transação, rollback no fim) —
 * é o mesmo eixo que o `JanaPlanoTierTest` escreve. Sem isto o UC-00 media o pacote, não a aba.
 */
function acoesGaranteAssinaturaJana(Business $business, User $user): void
{
    // `AdminSidebarMenu` só invoca o `modifyAdminMenu` de módulo INSTALADO — `ModuleUtil::isModuleInstalled`
    // lê `system.jana_version`, que só o InstallController grava. O CI migra do zero e nunca o roda: sem a
    // property, nenhuma entry da Jana nasce, com ou sem assinatura. Gravada na transação (rollback no fim).
    if (! \App\System::getProperty('jana_version')) {
        \App\System::addProperty('jana_version', (string) config('copiloto.module_version', '0.1'));
    }

    if (! class_exists(\Modules\Superadmin\Entities\Subscription::class)) {
        return; // sem Superadmin, `hasThePermissionInSubscription` devolve true sozinho
    }

    $sub = \Modules\Superadmin\Entities\Subscription::active_subscription($business->id);

    if (! $sub) {
        $pkg = \Modules\Superadmin\Entities\Package::query()->first()
            ?: \Modules\Superadmin\Entities\Package::query()->forceCreate([
                'name' => 'Pacote de teste (Jana)', 'description' => 'fixture', 'location_count' => 0,
                'user_count' => 0, 'product_count' => 0, 'invoice_count' => 0, 'interval' => 'months',
                'interval_count' => 1, 'trial_days' => 0, 'price' => 0, 'custom_permissions' => [],
                'created_by' => $user->id, 'is_active' => 1,
            ]);
        $sub = \Modules\Superadmin\Entities\Subscription::query()->forceCreate([
            'business_id' => $business->id, 'package_id' => $pkg->id,
            'start_date' => now()->subDay()->toDateString(), 'end_date' => now()->addMonth()->toDateString(),
            'package_price' => 0, 'package_details' => [], 'created_id' => $user->id, 'status' => 'approved',
        ]);
    }

    $detalhes = (array) ($sub->package_details ?? []);
    $detalhes['jana_module'] = 1;
    $sub->package_details = $detalhes;
    $sub->save();
}

// ── RUNTIME ──────────────────────────────────────────────────────────────────

it('UC-ACAO-00: a aba Ações existe na barra da área, na 4ª posição, e leva a /ia/acoes', function () {
    [$business, $user] = acoesBootstrap();
    acoesGaranteAssinaturaJana($business, $user);

    $resposta = $this->get('/ia/acoes')->assertStatus(200);
    $resposta->assertInertia(fn ($page) => $page->component('Jana/Acoes')->has('shell.menu'));

    $menu = $resposta->inertiaPage()['props']['shell']['menu'];
    $jana = collect($menu)->first(fn ($m) => ($m['group'] ?? null) === 'ia' || strtolower($m['label'] ?? '') === 'jana');
    expect($jana)->not->toBeNull();

    $keys = array_column($jana['ghosts'] ?? [], 'key');
    expect(array_slice($keys, 0, 5))->toBe(['dashboard', 'copiloto', 'alertas', 'acoes', 'memorias']);

    $ghost = collect($jana['ghosts'])->firstWhere('key', 'acoes');
    expect($ghost['label'])->toBe('Ações')->and($ghost['href'])->toBe('/ia/acoes');
});

it('UC-ACAO-01: a fila traz as 5 ações do serviço, CTA byte-idêntico e prévia do SERVIDOR', function () {
    [$business] = acoesBootstrap();

    $acoes = $this->get('/ia/acoes')->assertStatus(200)->inertiaPage()['props']['acoes'];

    expect(array_column($acoes, 'key'))->toBe(array_keys(AcaoHitlService::ACOES));

    $svc = app(AcaoHitlService::class);
    foreach ($acoes as $a) {
        $p = $svc->previa($a['key'], $business->id);
        expect($a['cta'])->toBe(AcaoHitlService::ACOES[$a['key']])
            ->and($a['titulo'])->toBe(AcaoHitlService::TITULOS[$a['key']])
            ->and($a['previa'])->toBe($p['previa'])
            ->and($a['alcance'])->toBe($p['alcance'])
            ->and($a)->toHaveKey('recibo');
    }
    // Ninguém aprovou nada neste tenant de teste (transação): a fila inteira é "sugeridas".
    expect(collect($acoes)->pluck('recibo')->filter()->all())->toBe([]);
});

it('UC-ACAO-02: aprovar grava o recibo (prévia do servidor), e recibo de OUTRO business não vaza', function () {
    [$business, $user] = acoesBootstrap();

    $outro = Business::where('id', '!=', $business->id)->first();
    if ($outro) {
        // Aprovação alheia da MESMA chave — se a fila a mostrasse, seria vazamento Tier 0.
        AcaoAprovacao::withoutGlobalScopes()->create([
            'business_id' => $outro->id, 'user_id' => $user->id, 'acao_key' => 'investigar-ticket',
            'status' => 'aprovada', 'previa' => 'PREVIA DE OUTRO BUSINESS', 'contexto' => [], 'aprovada_em' => now(),
        ]);
    }

    $this->post('/ia/acoes/investigar-ticket/aprovar')->assertRedirect();

    $gravada = AcaoAprovacao::where('business_id', $business->id)->where('acao_key', 'investigar-ticket')->latest('id')->first();
    expect($gravada)->not->toBeNull();

    $acoes = collect($this->get('/ia/acoes')->inertiaPage()['props']['acoes']);
    $linha = $acoes->firstWhere('key', 'investigar-ticket');

    expect($linha['recibo'])->not->toBeNull()
        ->and($linha['recibo']['previa'])->toBe($gravada->previa)
        ->and($linha['recibo']['previa'])->not->toBe('PREVIA DE OUTRO BUSINESS')
        ->and($linha['recibo']['quando'])->not->toBeNull()
        ->and($linha['recibo']['quem'])->toBe($user->first_name);

    // As outras 4 seguem sem recibo — aprovar uma não "aprova" a fila.
    expect($acoes->where('key', '!=', 'investigar-ticket')->pluck('recibo')->filter()->all())->toBe([]);
});

// ── ARQUIVO ──────────────────────────────────────────────────────────────────

it('UC-ACAO-03: toda âncora e toda copy do contrato estão no alvo, na ordem declarada', function () {
    $c    = json_decode(file_get_contents(base_path(ACOES_CONTRATO)), true);
    $blob = implode("\n", array_map(fn ($f) => file_get_contents(base_path($f)), $c['alvo']));

    preg_match_all('/data-contract\s*=\s*["\']([^"\']+)["\']/u', $blob, $m);
    $seq = $m[1];

    foreach ($c['secoes'] as $s) {
        expect($seq)->toContain($s['id']);
        foreach ($s['copy'] as $copy) {
            expect($blob)->toContain($copy);
        }
    }

    $pos = array_map(fn ($id) => array_search($id, $seq, true), $c['ordem']);
    expect($pos)->not->toContain(false);
    $sorted = $pos; sort($sorted);
    expect($pos)->toBe($sorted);
});
