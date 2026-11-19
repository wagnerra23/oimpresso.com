<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Validator;
use Modules\Governance\Http\Controllers\AuditController;
use Modules\Governance\Http\Controllers\DataController;
use Modules\Governance\Http\Requests\FilterAuditRequest;

/**
 * Governança — cada aba do menu só aparece para quem consegue abrir a tela.
 *
 * Origem: auditoria do módulo em 2026-11-19 (lote L3, item 3.1). As 7 abas saíam sob
 * `governance.dashboard.view`, mas Auditoria exige `governance.audit.view`, Custos
 * `jana.admin.custos.view` e Qualidade IA `jana.mcp.usage.all`: quem tinha só a
 * primeira via as três e tomava 403.
 *
 * O gate de cada tela é perguntado ao REGISTRY vivo (`gatherMiddleware()` junta o
 * `can:` da rota e o do construtor do controller), não ao texto do routes.php —
 * assim, se uma tela mudar de permission e o menu não, este teste cai.
 *
 * Roda sem banco (lane sqlite sem migrate): a função testada é a mesma que o
 * `modifyAdminMenu()` chama, alimentada por um Gate de mentira.
 */

/** Chave da aba → a única permission que a faz aparecer. */
function govPermissaoPorAba(): array
{
    $todas = [
        'governance.dashboard.view',
        'governance.audit.view',
        'jana.admin.custos.view',
        'jana.mcp.usage.all',
    ];

    $mapa = [];
    foreach ($todas as $permissao) {
        $abas = DataController::ghostsPermitidos(fn (string $p): bool => $p === $permissao);
        foreach ($abas as $aba) {
            $mapa[$aba['key']][] = $permissao;
        }
    }

    return $mapa;
}

describe('Governança — menu × permissão da tela', function () {

    it('com todas as permissions, as 7 abas aparecem na ordem do menu', function () {
        $abas = DataController::ghostsPermitidos(fn (): bool => true);

        expect(array_column($abas, 'key'))->toBe([
            'dashboard', 'policies', 'audit', 'drift', 'ds-rollout', 'custos', 'qualidade-ia',
        ]);
        // A permission é interna: não vaza para o shell.menu do front.
        foreach ($abas as $aba) {
            $this->assertSame(['key', 'label', 'href'], array_keys($aba));
        }
    });

    it('só com governance.dashboard.view, Auditoria, Custos e Qualidade IA somem', function () {
        $abas = DataController::ghostsPermitidos(fn (string $p): bool => $p === 'governance.dashboard.view');

        expect(array_column($abas, 'key'))->toBe(['dashboard', 'policies', 'drift', 'ds-rollout']);
    });

    it('sem nenhuma permission, nenhuma aba aparece', function () {
        expect(DataController::ghostsPermitidos(fn (): bool => false))->toBe([]);
    });

    it('a permission de cada aba é a mesma que a tela exige', function () {
        $mapa = govPermissaoPorAba();
        $abas = DataController::ghostsPermitidos(fn (): bool => true);

        // Controle positivo: sem isto o laço abaixo passaria por vácuo.
        $this->assertCount(7, $abas);

        foreach ($abas as $aba) {
            $this->assertCount(1, $mapa[$aba['key']] ?? [], "aba `{$aba['key']}` não depende de exatamente uma permission");
            $permissaoDoMenu = $mapa[$aba['key']][0];

            $rota = collect(Route::getRoutes()->getRoutes())
                ->first(fn ($r) => '/' . $r->uri() === $aba['href'] && in_array('GET', $r->methods(), true));
            $this->assertNotNull($rota, "aba `{$aba['key']}` aponta para {$aba['href']}, que não tem rota GET");

            $gates = array_values(array_filter(
                $rota->gatherMiddleware(),
                fn ($m) => is_string($m) && str_starts_with($m, 'can:'),
            ));

            if ($gates === []) {
                // Só o Painel não tem `can:` hoje (rota só com `auth`); a aba usa o gate
                // da própria entrada do menu.
                $this->assertSame('dashboard', $aba['key'], "tela {$aba['href']} sem `can:` — o menu não tem como espelhar o gate");
                $this->assertSame('governance.dashboard.view', $permissaoDoMenu);

                continue;
            }

            $this->assertContains('can:' . $permissaoDoMenu, $gates, "aba `{$aba['key']}` aparece com `{$permissaoDoMenu}`, mas a tela exige " . implode(', ', $gates));
        }
    });
});

describe('Governança — filtros do Audit validados pelo FilterAuditRequest', function () {

    it('UC-GAUD-09 · o AuditController::index recebe o FilterAuditRequest (é o que dispara a validação)', function () {
        $tipo = (new ReflectionMethod(AuditController::class, 'index'))->getParameters()[0]->getType();

        $this->assertSame(FilterAuditRequest::class, $tipo?->getName());
    });

    it('UC-GAUD-09 · período, status e actor fora da lista são recusados', function (array $entrada, string $campo) {
        $v = Validator::make($entrada, (new FilterAuditRequest())->rules());

        expect($v->fails())->toBeTrue();
        expect($v->errors()->keys())->toBe([$campo]);
    })->with([
        'período desconhecido' => [['period' => '99d'], 'period'],
        'status fora do enum'  => [['status' => 'quebrado'], 'status'],
        'actor com espaço'     => [['actor' => 'wagner ra'], 'actor'],
    ]);

    it('UC-GAUD-09 · os valores que a tela oferece passam', function (array $entrada) {
        expect(Validator::make($entrada, (new FilterAuditRequest())->rules())->fails())->toBeFalse();
    })->with([
        'padrão'        => [[]],
        'período 1h'    => [['period' => '1h']],
        'período 30d'   => [['period' => '30d']],
        'status ok'     => [['status' => 'ok']],
        'status error'  => [['status' => 'error']],
        'actor do time' => [['actor' => 'wagner', 'endpoint' => 'tools/call']],
    ]);
});
