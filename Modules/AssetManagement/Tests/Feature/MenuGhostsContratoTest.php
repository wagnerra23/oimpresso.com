<?php

declare(strict_types=1);

use App\Facades\Menu;
use App\Services\LegacyMenuAdapter;
use App\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\AssetManagement\Http\Controllers\DataController;

uses(Tests\TestCase::class);

/**
 * Contrato do menu do modulo (`DataController::modifyAdminMenu`, chave `ghosts[]`).
 *
 * POR QUE ESTE TESTE EXISTE. O `ghosts[]` e o dono UNICO dos rotulos das abas: o
 * `resources/js/Pages/Patrimonio/_shared/PatrimonioSubNav.tsx` DERIVA dele via
 * `shell.menu` e nao declara lista propria (o docblock dele explica). Dono unico sem
 * teste e dono unico ate alguem editar sem saber: uma troca aqui muda a barra das
 * CINCO telas do modulo de uma vez, e nada mais no repo defendia esses seis rotulos.
 *
 * O QUE ELE TRAVA, e a data. Em 2026-09-09 [W] decidiu "Bens" e "Manutencoes" para as
 * abas, encerrando a divergencia que o `RUNBOOK-patrimonio-index.md` §6 e a
 * `_saida-07.md` §0 registraram: a aba dizia "Ativos"/"Manutencao" enquanto o
 * `PageHeader` da MESMA tela dizia "Bens"/"Manutencoes" (`Bens.tsx:528`,
 * `Manutencoes.tsx:438`). Se alguem reverter no `DataController`, este teste cai.
 *
 * O QUE ELE NAO FAZ. Nao afirma que as SETE abas do prototipo deveriam existir:
 * Garantias e Auditoria nao tem rota em `Routes/web.php` e sao decisao [W] em aberto
 * (bloqueios D-GARANTIAS / D-AUDITORIA no `Bens.charter.md:81`). O 4o cenario abaixo
 * defende justamente a AUSENCIA delas -- "renderizar aba que nao navega e afordancia
 * falsa". Quando a decisao sair, ela entra pelo `DataController` e este teste muda junto.
 *
 * ADR 0358: tenant canonico de teste e o FICTICIO 98 (`seededTenant()`). Nunca biz=1/biz=4.
 *
 * O gate de `modifyAdminMenu` NAO se neutraliza por mock de container: ele faz
 * `new ModuleUtil()` direto (`DataController:106`), entao `app()->instance()` nao alcanca
 * -- o padrao de `BensContratoTest:110` nao serve aqui. O caminho usado e o real:
 * `superadmin` faz `hasThePermissionInSubscription` devolver `true` na entrada
 * (`ModuleUtil:145-147`), antes de consultar assinatura.
 */
beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompativel: ModuleUtil + Subscription requerem schema MySQL UltimatePOS');
    }
    foreach (['business', 'users', 'roles', 'permissions'] as $tabela) {
        if (! Schema::hasTable($tabela)) {
            $this->markTestSkipped("Tabela {$tabela} ausente -- rode migrate primeiro");
        }
    }

    // Lavary Menu e singleton: sem recriar, um cenario herda os itens do anterior e a
    // contagem mede o acumulado em vez desta chamada.
    Menu::make('admin-sidebar-menu', function ($m) {});
});

/** Usuario que satisfaz o gate por `superadmin` (ver docblock). Some no `finally`. */
function menuGhostsUsuario(int $businessId): User
{
    $user = User::factory()->create([
        'business_id' => $businessId,
        'username' => 'menu_ghosts_'.uniqid(),
        'user_type' => 'user',
        'allow_login' => 1,
    ]);
    $user->givePermissionTo('superadmin');

    return $user;
}

/**
 * Invoca o dono real e devolve os ghosts JA no shape que o React recebe.
 *
 * Pelo `LegacyMenuAdapter`, nao pelo `Menu` direto: o adapter e o consumidor REAL -- e o
 * que o `ShellMenuBuilder` chama pra produzir o `shell.menu` que o React le. Medir a API
 * interna do `Menu` responderia "o item foi registrado"; medir o adapter responde "o
 * rotulo chega ao browser", que e a pergunta deste teste.
 *
 * A entry e procurada pelo MESMO predicado do componente (label do modulo em minusculas,
 * `PatrimonioSubNav.tsx::LABEL_MODULO`): se alguem renomear o modulo sem atualizar o
 * componente, a barra some das cinco telas -- e este teste cai antes.
 */
function menuGhostsDoModulo(User $user, int $businessId): array
{
    test()->actingAs($user);
    session(['user.business_id' => $businessId, 'business.id' => $businessId]);

    (new DataController())->modifyAdminMenu();

    $rotuloModulo = mb_strtolower(__('assetmanagement::lang.asset_management'));

    $entry = collect((new LegacyMenuAdapter())->build())
        ->first(fn ($item) => mb_strtolower((string) ($item['label'] ?? '')) === $rotuloModulo);

    expect($entry)->not->toBeNull(
        'a entry do modulo nao chegou ao shell.menu -- o gate barrou antes, ou o label do '
        .'modulo mudou sem atualizar LABEL_MODULO no PatrimonioSubNav.tsx'
    );

    return $entry['ghosts'] ?? [];
}

it('os rotulos das abas sao os que [W] decidiu em 2026-09-09 -- "Bens" e "Manutencoes"', function () {
    $biz = $this->seededTenant();
    $user = menuGhostsUsuario($biz->id);

    try {
        $ghosts = menuGhostsDoModulo($user, $biz->id);
        $porChave = collect($ghosts)->keyBy('key');

        // Os dois que a decisao mudou. Assertados por CHAVE, nao por posicao: reordenar
        // a barra e mudanca legitima e nao deve quebrar o contrato do rotulo.
        expect($porChave['assets']['label'])->toBe('Bens')
            ->and($porChave['asset-maintenance']['label'])->toBe('Manutenções');
    } finally {
        $user->forceDelete();
    }
});

it('os outros quatro rotulos ficaram intactos na mesma troca', function () {
    $biz = $this->seededTenant();
    $user = menuGhostsUsuario($biz->id);

    try {
        $porChave = collect(menuGhostsDoModulo($user, $biz->id))->keyBy('key');

        expect($porChave['dashboard']['label'])->toBe('Painel')
            ->and($porChave['allocation']['label'])->toBe('Alocações')
            ->and($porChave['revocation']['label'])->toBe('Devoluções')
            ->and($porChave['settings']['label'])->toBe('Configurações');
    } finally {
        $user->forceDelete();
    }
});

it('todo ghost aponta para uma rota que existe -- aba que nao navega e afordancia falsa', function () {
    $biz = $this->seededTenant();
    $user = menuGhostsUsuario($biz->id);

    try {
        $ghosts = menuGhostsDoModulo($user, $biz->id);
        expect($ghosts)->toHaveCount(6);

        // Resolve o href contra o roteador REAL em vez de comparar com uma lista escrita
        // aqui: uma lista aqui seria um segundo dono do mesmo fato, que e o defeito que o
        // `PatrimonioSubNav` existe para nao cometer.
        foreach ($ghosts as $ghost) {
            $rota = app('router')->getRoutes()->match(
                Illuminate\Http\Request::create($ghost['href'], 'GET')
            );
            expect($rota)->not->toBeNull("ghost '{$ghost['key']}' aponta para {$ghost['href']}, que nao resolve");
        }
    } finally {
        $user->forceDelete();
    }
});

it('Garantias e Auditoria NAO aparecem enquanto nao tiverem rota (D-GARANTIAS / D-AUDITORIA)', function () {
    $biz = $this->seededTenant();
    $user = menuGhostsUsuario($biz->id);

    try {
        $chaves = collect(menuGhostsDoModulo($user, $biz->id))->pluck('key');

        // O prototipo desenha as duas (`patrimonio-page.jsx:839-840`) e o contrato de tela
        // as lista. Nenhuma tem rota; renderiza-las seria afordancia falsa. Este assert cai
        // no dia em que a rota nascer -- e ai a aba deve mesmo entrar.
        expect($chaves)->not->toContain('warranty')
            ->and($chaves)->not->toContain('garantias')
            ->and($chaves)->not->toContain('audit')
            ->and($chaves)->not->toContain('auditoria');
    } finally {
        $user->forceDelete();
    }
});
