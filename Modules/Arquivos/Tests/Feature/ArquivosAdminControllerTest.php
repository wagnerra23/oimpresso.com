<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;
use Modules\Arquivos\Http\Controllers\ArquivosAdminController;

uses(Tests\TestCase::class);

/**
 * Contrato da tela do acervo — US-ARQ-013 (onda 1 · PR-1).
 *
 * Defende o que o `Index.casos.md` declara em UC-INDEX-01 e na seção anti-regressão,
 * e o que o `Index.charter.md` põe em Non-Goals.
 *
 * O isolamento cross-tenant em si já é coberto por `MultiTenantTest` (global scope no
 * `Arquivo`); aqui o alvo é o CONTROLLER — que ele não quebre o scope e não vaze o que
 * o charter proíbe.
 *
 * Tenant: fictício 98 ([ADR 0358](memory/decisions/0358-doutrina-de-teste-tenant-98-supersede-0101.md)).
 * `biz=4` (ROTA LIVRE) é PROIBIDO em teste, sem exceção.
 *
 * @see resources/js/Pages/Arquivos/Index.casos.md
 * @see memory/requisitos/Arquivos/RUNBOOK-index.md
 */

beforeEach(function () {
    if (! Schema::hasTable('arquivos')) {
        $this->markTestSkipped('arquivos table missing — rode migrate primeiro');
    }
});

it('UC-INDEX-01 · o controller NAO quebra o global scope multi-tenant', function () {
    // Tier 0 (ADR 0093): o business_id vem da SESSÃO. Se alguém puser um
    // `withoutGlobalScopes` aqui, este teste é quem avisa.
    $fonte = file_get_contents(
        base_path('Modules/Arquivos/Http/Controllers/ArquivosAdminController.php')
    );

    expect($fonte)->not->toContain('withoutGlobalScopes');
    expect($fonte)->not->toContain("where('business_id'");
})->group('arquivos', 'multi-tenant');

it('UC-INDEX-01 · a linha do acervo NAO carrega storage_path nem md5 (LGPD Art. 37)', function () {
    // Non-Goal do charter: PII e caminho vivem só em `arquivos_audit_log`. Uma vista de
    // governança que os renderize é vazamento — e o `casos.md` declara isso.
    $r = new ReflectionMethod(ArquivosAdminController::class, 'linha');
    $r->setAccessible(true);

    $fonte = file_get_contents(
        base_path('Modules/Arquivos/Http/Controllers/ArquivosAdminController.php')
    );
    $corpo = substr($fonte, (int) strpos($fonte, 'private function linha('));

    expect($corpo)->not->toContain('storage_path');
    expect($corpo)->not->toContain('md5');
})->group('arquivos', 'lgpd');

it('UC-INDEX-01 · a tela e LEITURA PURA — nenhum caminho escreve, apaga ou enfileira', function () {
    // Anti-regressão do casos.md: "Nenhum caminho de upload nesta tela" +
    // "Excluir nunca chama hard-delete direto". Na onda 1 nem existe mutação.
    $fonte = file_get_contents(
        base_path('Modules/Arquivos/Http/Controllers/ArquivosAdminController.php')
    );

    foreach (['->delete(', '->save(', '->update(', 'dispatch(', 'forceDelete('] as $proibido) {
        expect($fonte)->not->toContain($proibido);
    }
})->group('arquivos');

it('a rota do acervo exige a permission arquivos.access', function () {
    // A permission existia declarada desde a Sprint 1 e NÃO tinha consumidor no repo.
    // Esta rota é o primeiro — se alguém tirar o can(), o gate de acesso some calado.
    $rotas = file_get_contents(base_path('Modules/Arquivos/Routes/web.php'));

    expect($rotas)->toContain('can:arquivos.access');
    expect($rotas)->toContain("->name('arquivos.index')");
})->group('arquivos');

it('a rota assinada de download e as 3 do Install seguem intactas', function () {
    // Regra 4 do pedido zero-toque: não tocar nelas. Teste de não-regressão.
    $rotas = file_get_contents(base_path('Modules/Arquivos/Routes/web.php'));

    expect($rotas)->toContain("->name('arquivos.download')");
    expect($rotas)->toContain("'signed'");
    expect($rotas)->toContain('throttle:60,1');
    expect(substr_count($rotas, 'InstallController::class'))->toBe(3);
})->group('arquivos');
