<?php

declare(strict_types=1);

// @covers-us US-FISCAL-030 — tela Fiscal/Das (prévia do DAS pelo Anexo, atrás de flag).
// Contrato: resources/js/Pages/Fiscal/Das.casos.md — UC-FDAS-01..04.

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\NfeBrasil\Services\Tributacao\ApuracaoDasService;

uses(Tests\TestCase::class);

/**
 * MySQL-only (usuário + papel Spatie do tenant semeado). Tenant 98 (ADR 0358), 99 como vizinho.
 * A conta em si é do ApuracaoDasTest (UC-DAS-01..05); aqui só o que a tela garante.
 */
function dasVersao(): string
{
    $manifest = public_path('build-inertia/manifest.json');

    return file_exists($manifest) ? md5_file($manifest) : '1';
}

function dasComo(\App\User $u, int $biz): void
{
    test()->actingAs($u)->withSession(['business.id' => $biz, 'user.business_id' => $biz]);
}

/** GET /fiscal/das como Inertia; com $parcial, pede a prop adiada `apuracao`. */
function dasPagina(string $query = '', bool $parcial = false): array
{
    $headers = ['X-Inertia' => 'true', 'X-Inertia-Version' => dasVersao()];
    if ($parcial) {
        $headers += ['X-Inertia-Partial-Component' => 'Fiscal/Das', 'X-Inertia-Partial-Data' => 'apuracao'];
    }
    $r = test()->withHeaders($headers)->get('/fiscal/das'.$query);
    $r->assertOk();

    return json_decode($r->getContent(), true);
}

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('MySQL-only: usuário com papel Spatie do tenant semeado.');
    }
});

it('UC-FDAS-01 · flag desligada responde 404', function () {
    config(['fiscal.das_apuracao_enabled' => false]);
    dasComo(test()->usuarioComPermissoes(['fiscal.access']), test()->seededTenant()->id);

    $this->get('/fiscal/das?anexo=I')->assertNotFound();
});

it('UC-FDAS-02 · sem fiscal.access responde 403', function () {
    config(['fiscal.das_apuracao_enabled' => true]);
    dasComo(test()->usuarioComPermissoes(['fiscal.nfe.view']), test()->seededTenant()->id);

    $this->get('/fiscal/das?anexo=I')->assertForbidden();
});

it('UC-FDAS-03 · sem anexo (ou anexo inválido) a tela não escolhe o anexo', function () {
    config(['fiscal.das_apuracao_enabled' => true]);
    dasComo(test()->usuarioComPermissoes(['fiscal.access']), test()->seededTenant()->id);

    foreach (['', '?anexo=VI'] as $q) {
        $p = dasPagina($q);
        expect($p['component'])->toBe('Fiscal/Das');
        expect($p['props']['anexo'])->toBeNull();
        expect($p['props']['faixas'])->toBe([]);

        $adiada = dasPagina($q, true);
        expect($adiada['props']['apuracao'])->toBeNull();
    }
});

it('UC-FDAS-04 · com anexo, a apuração vem do business da sessão e da competência pedida', function () {
    config(['fiscal.das_apuracao_enabled' => true]);
    $biz = test()->seededTenant()->id;
    dasComo(test()->usuarioComPermissoes(['fiscal.access']), $biz);

    $p = dasPagina('?anexo=I&competencia=2026-10&business_id=99');
    expect($p['props']['anexo'])->toBe('I');
    expect($p['props']['competencia'])->toBe('2026-10');
    expect($p['props']['faixas'])->toHaveCount(6);
    expect($p['props']['faixas'][2])->toMatchArray(['faixa' => 3, 'ate' => 720000, 'parcela' => 13860]);

    // A prop adiada é a conta do service para o business da SESSÃO — o `business_id=99` da query não manda.
    $apuracao = dasPagina('?anexo=I&competencia=2026-10&business_id=99', true)['props']['apuracao'];
    $esperado = (new ApuracaoDasService())->apurar($biz, CarbonImmutable::parse('2026-10-01'), 'I');
    expect($apuracao['rbt12'])->toEqual($esperado['rbt12']);
    expect($apuracao['receita_mes'])->toEqual($esperado['receita_mes']);
    expect($apuracao['rbt12_de'])->toBe('2025-10-01');
    expect($apuracao['competencia'])->toBe('2026-10');

    // Competência inválida cai no mês anterior ao corrente.
    $q = dasPagina('?anexo=I&competencia=2026-13');
    expect($q['props']['competencia'])->toBe(CarbonImmutable::now()->startOfMonth()->subMonthNoOverflow()->format('Y-m'));
});
