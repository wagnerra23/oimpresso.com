<?php

declare(strict_types=1);
// Cobre UC-FPN-01 (Fisio/Painel.casos.md) e UC-FPI-04 (Fisio/Pacientes/Index.casos.md).

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Modules\Fisio\Http\Controllers\DataController;
use Modules\Fisio\Tests\Support\FisioFixtures as F;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * Módulo invisível para quem não o contratou (T-02): sem o pacote `fisio_module`, nem o
 * menu nem as rotas existem para o business. Habilitação é SÓ por pacote + permissão.
 *
 * Tenant 98 (com pacote) contra o 99 (sem). Usuários novos com EXATAMENTE as permissões
 * do caso (usuarioComPermissoes). ⚠️ SKIP em SQLite: leia assertions, não "0 failed" (LC-13).
 */
beforeEach(function () {
    if ($motivo = F::motivoDeSkip()) {
        $this->markTestSkipped($motivo);
    }
    F::configurar();
    $this->b98 = $this->seededTenant();
    $this->b99 = $this->seededSupportClientTenant();
    $this->u98 = $this->usuarioComPermissoes([F::PERM_ACESSO, F::PERM_EDITAR], $this->b98);
    $this->u99 = $this->usuarioComPermissoes([F::PERM_ACESSO, F::PERM_EDITAR], $this->b99);
    F::pacote([(int) $this->b98->id]);
});

/** Labels publicados na sidebar depois do modifyAdminMenu do Fisio (caminho real, não o fonte). */
function fisioMenuLabels(): array
{
    \Menu::create('admin-sidebar-menu', fn ($menu) => $menu);
    (new DataController())->modifyAdminMenu();

    return array_column((new \App\Services\LegacyMenuAdapter())->build(), 'label');
}

it('UC-FPN-01 · menu Fisio aparece para quem tem pacote e permissão, e some sem pacote', function () {
    F::como($this, $this->u98);
    expect(fisioMenuLabels())->toContain('Fisio');

    // Controle negativo 1: mesmo usuário com permissão, business SEM pacote → sem menu.
    F::como($this, $this->u99);
    expect(fisioMenuLabels())->not->toContain('Fisio');

    // Controle negativo 2: business COM pacote, usuário sem fisio.access → sem menu.
    $semPermissao = $this->usuarioComPermissoes([], $this->b98);
    F::como($this, $semPermissao);
    expect(fisioMenuLabels())->not->toContain('Fisio');
});

it('UC-FPN-01 · rotas do Fisio respondem 403 para business sem o pacote, e 200 com', function () {
    F::como($this, $this->u98);
    $this->get('/fisio')->assertOk();
    $this->get('/fisio/pacientes')->assertOk();
    $this->get('/fisio/biblioteca')->assertOk();

    F::como($this, $this->u99);
    foreach (['/fisio', '/fisio/pacientes', '/fisio/biblioteca'] as $rota) {
        $this->get($rota)->assertForbidden();
    }
    // Escrita também barrada — nada é gravado no business sem módulo.
    $contato = F::contato((int) $this->b99->id, $this->u99->id);
    $this->post('/fisio/pacientes', ['contact_id' => $contato, 'sessions_per_week' => 3, 'main_complaint' => 'x'])->assertForbidden();
    expect(\Modules\Fisio\Entities\Patient::withoutGlobalScopes()->where('contact_id', $contato)->exists())->toBeFalse(); // SUPERADMIN: controle negativo
});

it('UC-FPI-04 · desligar o pacote corta o acesso no request seguinte (não depende de cache de menu)', function () {
    F::como($this, $this->u98);
    $this->get('/fisio/pacientes')->assertOk();

    F::trocarPacote((int) $this->b98->id, false);
    $this->get('/fisio/pacientes')->assertForbidden();

    F::trocarPacote((int) $this->b98->id, true);
    $this->get('/fisio/pacientes')->assertOk();
});

it('UC-FPI-04 · sem fisio.access, 403 mesmo com o pacote ativo', function () {
    F::como($this, $this->usuarioComPermissoes([], $this->b98));
    $this->get('/fisio/pacientes')->assertForbidden();
});
