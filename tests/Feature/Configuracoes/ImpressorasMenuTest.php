<?php

declare(strict_types=1);
// Cobre UC-IMPR-05 (Configuracoes/Impressoras/Index.casos.md) — o "limite medido" dele: com só access_printers.

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Schema;

/**
 * Thread sistema/playbook/11 — o dropdown de Configurações do AdminSidebarMenu tem o filho
 * "Impressoras" sob `access_printers`, mas a condição EXTERNA do grupo não listava a permissão:
 * quem tinha só ela não via o grupo (Blade) nem as abas do ConfiguracoesSubNav, que derivam do
 * mesmo dropdown entregue como `shell.menu`.
 *
 * Lê o `shell.menu` real por partial reload (a mesma sonda do ImpressorasContratoTest UC-IMPR-05).
 * Antes do conserto a 1ª asserção recebe [] (o grupo não existe). Tenant de teste 98 (ADR 0358).
 */

uses(DatabaseTransactions::class);

beforeEach(function () {
    if (! Schema::hasTable('printers') || ! Schema::hasColumn('users', 'business_id')) {
        $this->markTestSkipped('Schema UltimatePOS ausente (sqlite memory) — rode com DB_CONNECTION=mysql.');
    }

    $this->business = $this->seededTenant();
    $this->versaoInertia = app(\App\Http\Middleware\HandleInertiaRequests::class)->version(request());
    // As flags põem /printers e /business-location em Inertia: é o caminho que expõe `shell.menu`
    // por partial reload. Override de ambiente, inerte em produção.
    config(['feature-flags.forced_on' => 'useV2ConfiguracoesImpressoras,useV2ConfiguracoesLocais']);
});

if (! function_exists('imprMenuGrupoDeConfiguracoes')) {
    /** Hrefs (path) dos filhos do grupo cujo filho aponta para $alvo; [] se o grupo não veio. */
    function imprMenuGrupoDeConfiguracoes(array $itens, string $alvo): array
    {
        foreach ($itens as $item) {
            $filhos = $item['children'] ?? [];
            $hrefs = array_map(fn ($c) => (string) parse_url((string) ($c['href'] ?? ''), PHP_URL_PATH), $filhos);
            if (in_array($alvo, $hrefs, true)) {
                return $hrefs;
            }
            $dentro = imprMenuGrupoDeConfiguracoes($filhos, $alvo);
            if ($dentro !== []) {
                return $dentro;
            }
        }

        return [];
    }
}

function imprMenuComo(object $teste, array $permissoes, object $business, string $rota, string $componente, string $alvo): array
{
    $user = $teste->usuarioComPermissoes($permissoes, $business);
    $teste->actingAs($user);
    session(['user.business_id' => $business->id, 'user.id' => $user->id, 'business.id' => $business->id]);

    $r = $teste->withHeaders([
        'X-Inertia' => 'true', 'X-Inertia-Version' => $teste->versaoInertia,
        'X-Inertia-Partial-Component' => $componente, 'X-Inertia-Partial-Data' => 'shell',
    ])->get($rota);
    $r->assertOk();

    return imprMenuGrupoDeConfiguracoes($r->json('props.shell.menu') ?? [], $alvo);
}

test('UC-IMPR-05 com SÓ access_printers o grupo de Configurações aparece com a aba Impressoras e nenhuma outra', function () {
    $grupo = imprMenuComo($this, ['access_printers'], $this->business, '/printers', 'Configuracoes/Impressoras/Index', '/printers');

    // Vermelho antes do conserto: o grupo inteiro sumia e isto era [].
    expect($grupo)->toBe(['/printers']);
});

test('UC-IMPR-05 controle — sem access_printers o grupo vem sem a aba Impressoras', function () {
    // business_settings.access abre o grupo por outra porta; Impressoras não pode vir junto.
    $grupo = imprMenuComo($this, ['business_settings.access'], $this->business, '/business-location', 'Configuracoes/Locais/Index', '/business-location');

    expect($grupo)->toContain('/business-location');
    expect($grupo)->not->toContain('/printers');
});
