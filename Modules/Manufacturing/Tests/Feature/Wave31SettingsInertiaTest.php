<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

uses(Tests\TestCase::class);

/**
 * Wave 31 — Configurações do módulo (Manufacturing/Settings) em
 * `/manufacturing/v2/settings`.
 *
 * Contrato = os UC de `resources/js/Pages/Manufacturing/Settings.casos.md`, que derivam do
 * §4.7 e §16 do handoff "PROTÓTIPO OFICIAL - FABRICAÇÃO V1" — NÃO do `.tsx`
 * (§5 tautológico das proibições).
 *
 * Esta é a PRIMEIRA tela da família que escreve, e a defesa central é justamente que ela
 * NÃO inventou escrita nova: o `store()` legado não mudou. Por isso os asserts de UC-CFG-02
 * e UC-CFG-04 são sobre a FORMA do controller (o que ele lê, como ele grava) — o
 * comportamento HTTP ponta-a-ponta é smoke (RUNBOOK-settings.md §3), não Pest, porque a
 * suíte deste módulo ainda não tem fixture autenticada.
 *
 * @covers-us US-MANU-003
 *
 * @see resources/js/Pages/Manufacturing/Settings.casos.md
 * @see memory/requisitos/Manufacturing/RUNBOOK-settings.md
 */

describe('UC-CFG-02/03/04 — o contrato do controller (DB-less)', function () {

    // UC-CFG-02 — a escrita segue no endpoint legado, com as MESMAS 3 chaves.
    it('UC-CFG-02 o store() legado segue lendo as 3 chaves e nao foi trocado por endpoint novo', function () {
        $fonte = file_get_contents(base_path('Modules/Manufacturing/Http/Controllers/SettingsController.php'));

        // As 3 chaves reais do §16 — nem mais, nem menos.
        expect($fonte)->toContain("\$request->only(['ref_no_prefix'])");
        expect($fonte)->toContain("\$settings['disable_editing_ingredient_qty']");
        expect($fonte)->toContain("\$settings['enable_updating_product_price']");

        // E o retorno que o Inertia depende (o client segue o redirect e re-busca as props).
        expect($fonte)->toContain('return redirect()->back()->with(');

        // Não pode existir um segundo caminho de escrita: só UM `->update([` no arquivo.
        expect(substr_count($fonte, '->update(['))->toBe(
            1,
            'Apareceu um segundo caminho de escrita no SettingsController — o charter proíbe endpoint de escrita novo.'
        );
    });

    // UC-CFG-04 — Tier 0: o update é scoped por business, nunca em massa.
    it('UC-CFG-04 a escrita e scoped por business_id, nunca update em massa', function () {
        $fonte = file_get_contents(base_path('Modules/Manufacturing/Http/Controllers/SettingsController.php'));

        expect($fonte)->toContain("Business::where('id', \$business_id)");

        // O update tem que vir DEPOIS da cláusula de tenant — um `Business::update(` solto
        // (sem where) reconfiguraria todos os tenants de uma vez.
        expect($fonte)->not->toContain('Business::update(');
    });

    // UC-CFG-03 — business sem config salva não pode mandar `undefined` pro React.
    it('UC-CFG-03 o payload normaliza as 3 chaves quando getSettings devolve vazio', function () {
        $fonte = file_get_contents(base_path('Modules/Manufacturing/Http/Controllers/SettingsController.php'));

        // `getSettings()` devolve [] quando nunca salvou (medido em ManufacturingUtil:103).
        // O indexV2 tem que normalizar — string vazia e false, nunca null/undefined.
        expect($fonte)->toContain("\$manufacturing_settings['ref_no_prefix'] ?? ''");
        expect($fonte)->toContain("! empty(\$manufacturing_settings['disable_editing_ingredient_qty'])");
        expect($fonte)->toContain("! empty(\$manufacturing_settings['enable_updating_product_price'])");
    });
});

describe('UC-CFG-01 — a rota (schema MySQL real)', function () {
    beforeEach(function () {
        if (DB::connection()->getDriverName() === 'sqlite') {
            $this->markTestSkipped('SQLite-incompatível: a rota depende do schema MySQL UltimatePOS (business/users).');
        }
        if (! Schema::hasTable('business')) {
            $this->markTestSkipped('Schema UltimatePOS ausente neste ambiente.');
        }
    });

    // UC-CFG-01 — o endereço CANÔNICO existe e é do SettingsController.
    //
    // ⚠️ Reapontado no cutover de 2026-09-04: `/v2/settings` segue registrado, mas como
    // REDIRECT — asserir o controller nele mediria o `RedirectController`, não o dono da tela.
    it('UC-CFG-01 a rota /manufacturing/settings esta registrada no runtime', function () {
        // Oráculo é o registry vivo, não a leitura do arquivo — §5 2026-07-28.
        $rota = collect(Route::getRoutes()->getRoutes())
            ->first(fn ($r) => $r->uri() === 'manufacturing/settings' && in_array('GET', $r->methods(), true));

        expect($rota)->not->toBeNull('A rota GET /manufacturing/settings sumiu do registry.');
        expect($rota->getActionName())->toContain('SettingsController');
    });

    // E a rota legada de ESCRITA continua lá — é ela que o form novo usa.
    it('UC-CFG-02 o POST /manufacturing/settings legado continua registrado', function () {
        $rota = collect(Route::getRoutes()->getRoutes())
            ->first(fn ($r) => $r->uri() === 'manufacturing/settings' && in_array('POST', $r->methods(), true));

        expect($rota)->not->toBeNull(
            'O POST /manufacturing/settings sumiu — a tela nova posta nele, e a Blade legada também.'
        );
    });
});
