<?php

declare(strict_types=1);
// Cobre UC-IMPR-01, UC-IMPR-02, UC-IMPR-03, UC-IMPR-04, UC-IMPR-05 (Configuracoes/Impressoras/Index.casos.md).

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Thread sistema/playbook/04, F3 — /printers em Inertia atrás da flag useV2ConfiguracoesImpressoras.
 *
 * A flag é ligada pelo override de ambiente (`feature-flags.forced_on`, inerte em produção), não
 * pelo GrowthBook. store/update/destroy não mudaram: o comportamento deles é do ImpressorasBaselineTest.
 * Tenant de teste 98 x cliente fictício 99 (ADR 0358).
 */

uses(DatabaseTransactions::class);

beforeEach(function () {
    if (! Schema::hasTable('printers') || ! Schema::hasColumn('users', 'business_id')) {
        $this->markTestSkipped('Schema UltimatePOS ausente (sqlite memory) — rode com DB_CONNECTION=mysql.');
    }

    $this->business = $this->seededTenant();
    $this->user = $this->usuarioComPermissoes(['access_printers'], $this->business);
    $this->versaoInertia = app(\App\Http\Middleware\HandleInertiaRequests::class)->version(request());
    $this->actingAs($this->user);
    session(['user.business_id' => $this->business->id, 'user.id' => $this->user->id, 'business.id' => $this->business->id]);
});

function imprLigarFlag(): void
{
    config(['feature-flags.forced_on' => 'useV2ConfiguracoesImpressoras']);
}

test('UC-IMPR-01 com a flag ligada, GET /printers renderiza Inertia (não a DataTable)', function () {
    imprLigarFlag();

    // X-Requested-With junto do X-Inertia, como o browser manda: o ramo ajax() antigo engolia isso.
    $r = $this->withHeaders([
        'X-Inertia' => 'true', 'X-Inertia-Version' => $this->versaoInertia, 'X-Requested-With' => 'XMLHttpRequest',
    ])->get('/printers');

    $r->assertOk();
    expect($r->json('component'))->toBe('Configuracoes/Impressoras/Index');
    expect($r->json('props.opcoes.conexao'))->toHaveKey('network');
});

test('UC-IMPR-02 com a flag desligada, GET /printers segue na Blade', function () {
    config(['feature-flags.forced_on' => '']);

    $this->get('/printers')->assertOk()->assertViewIs('printer.index');
});

test('UC-IMPR-03 Tier 0 — a lista adiada traz só as impressoras do negócio da sessão', function () {
    imprLigarFlag();
    $outro = $this->seededSupportClientTenant();
    $base = ['connection_type' => 'network', 'capability_profile' => 'default', 'char_per_line' => '48',
        'ip_address' => '192.168.0.31', 'port' => '9100', 'path' => '', 'created_by' => $this->user->id];
    $minha = DB::table('printers')->insertGetId($base + ['business_id' => $this->business->id, 'name' => 'Caixa '.uniqid()]);
    $alheia = DB::table('printers')->insertGetId($base + ['business_id' => $outro->id, 'name' => 'Alheia '.uniqid()]);

    $r = $this->withHeaders([
        'X-Inertia' => 'true',
        'X-Inertia-Version' => $this->versaoInertia,
        'X-Inertia-Partial-Component' => 'Configuracoes/Impressoras/Index',
        'X-Inertia-Partial-Data' => 'impressoras',
    ])->get('/printers');
    $r->assertOk();
    $porId = collect($r->json('props.impressoras') ?? [])->keyBy('id');

    expect($porId->has($minha))->toBeTrue();
    expect($porId->has($alheia))->toBeFalse();
    expect($porId[$minha]['conexao'])->toBe('network');
    expect($porId[$minha]['ip'])->toBe('192.168.0.31');
});

test('UC-IMPR-04 cadastrar e editar pelo drawer gravam no negócio da sessão', function () {
    imprLigarFlag();
    $inertia = ['X-Inertia' => 'true', 'X-Inertia-Version' => $this->versaoInertia, 'X-Requested-With' => 'XMLHttpRequest'];
    $nome = 'Drawer '.uniqid();
    // Mesmo corpo que o Index.tsx monta em salvar().
    $corpo = ['name' => $nome, 'connection_type' => 'network', 'capability_profile' => 'SP2000',
        'char_per_line' => '48', 'ip_address' => '10.0.0.7', 'port' => '9100', 'path' => ''];

    $this->withHeaders($inertia)->post('/printers', $corpo)->assertRedirect('printers');
    $linha = DB::table('printers')->where('name', $nome)->first();
    expect($linha)->not->toBeNull();
    expect((int) $linha->business_id)->toBe((int) $this->business->id);
    expect($linha->capability_profile)->toBe('SP2000');

    $this->withHeaders($inertia)->put("/printers/{$linha->id}", ['name' => 'Editada pelo drawer'] + $corpo)->assertRedirect('printers');
    expect(DB::table('printers')->where('id', $linha->id)->value('name'))->toBe('Editada pelo drawer');
});

function imprHrefsDoGrupo(array $itens): array
{
    foreach ($itens as $item) {
        $filhos = $item['children'] ?? [];
        $hrefs = array_map(fn ($c) => (string) parse_url((string) ($c['href'] ?? ''), PHP_URL_PATH), $filhos);
        if (in_array('/printers', $hrefs, true)) {
            return $hrefs;
        }
        $dentro = imprHrefsDoGrupo($filhos);
        if ($dentro !== []) {
            return $dentro;
        }
    }

    return [];
}

test('UC-IMPR-05 as abas vêm do grupo do menu que contém /printers, sob a permissão de cada uma', function () {
    imprLigarFlag();
    $menu = function () {
        $r = $this->withHeaders([
            'X-Inertia' => 'true', 'X-Inertia-Version' => $this->versaoInertia,
            'X-Inertia-Partial-Component' => 'Configuracoes/Impressoras/Index', 'X-Inertia-Partial-Data' => 'shell',
        ])->get('/printers');
        $r->assertOk();

        return imprHrefsDoGrupo($r->json('props.shell.menu') ?? []);
    };

    // Com SÓ access_printers o dropdown inteiro some: a condição externa do grupo no AdminSidebarMenu
    // não lista essa permissão (defeito legado, fora do prefixo da thread — registrado no _saida-04).
    // A tela segue utilizável; o ConfiguracoesSubNav só não tem de onde tirar as abas.
    expect($menu())->toBe([]);

    $this->actingAs($this->usuarioComPermissoes(['access_printers', 'business_settings.access'], $this->business));
    $semEtiqueta = $menu();
    expect($semEtiqueta)->toContain('/printers');
    expect($semEtiqueta)->toContain('/business-location');
    expect($semEtiqueta)->not->toContain('/barcodes');

    $this->actingAs($this->usuarioComPermissoes(['access_printers', 'business_settings.access', 'barcode_settings.access'], $this->business));
    expect($menu())->toContain('/barcodes');
});
