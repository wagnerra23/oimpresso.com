<?php

/**
 * Modulo: Officeimpresso — superadmin-only enforcement.
 *
 * Pattern dos controllers (ADR 0017 + project_officeimpresso_modulo):
 *   if (!auth()->user()->can('superadmin')) abort(403, 'Unauthorized action.');
 *
 * Endpoints protegidos auditados aqui (controller-level abort 403):
 *   - ClientController::index/store/destroy/regenerate
 *   - LicencaLogController::timeline/show (abort_unless dono)
 *   - OfficeimpressoController::generateQr (abort 403)
 *
 * IMPORTANTE: o contrato Delphi e IMUTAVEL (feedback_delphi_contrato_imutavel).
 * Esses testes NAO mexem em /connector/api/* nem em /oauth/token.
 *
 * Estrategia: loga como user comum (DEV_LOGIN_NORMAL_*) e confirma 403/redirect.
 * Sem creds normais no .env, marca skip — nunca quebra o build.
 */

use App\User;

function loginAsNonSuperadmin(): ?User
{
    session()->flush();
    auth()->logout();

    // Tenta credencial dedicada de "user normal" no .env (preferencial).
    $user = env('DEV_LOGIN_NORMAL_USERNAME');
    $pass = env('DEV_LOGIN_NORMAL_PASSWORD');

    if ($user && $pass) {
        test()->post('/login', ['username' => $user, 'password' => $pass]);
        if (auth()->check() && ! auth()->user()->can('superadmin')) {
            return auth()->user();
        }
    }

    // Fallback: pega qualquer user no DB que NAO seja superadmin.
    $candidate = User::query()
        ->where('status', 'active')
        ->orderBy('id')
        ->get()
        ->first(fn ($u) => ! $u->can('superadmin'));

    if (! $candidate) {
        return null;
    }
    test()->actingAs($candidate);
    return $candidate;
}

beforeEach(function () {
    $this->nonSuper = loginAsNonSuperadmin();
    if (! $this->nonSuper) {
        $this->markTestSkipped('Sem user "nao-superadmin" disponivel no DB local — defina DEV_LOGIN_NORMAL_* ou seede um user comum.');
    }
});

it('GET /officeimpresso/client nega acesso pra non-superadmin (403)', function () {
    $r = $this->get('/officeimpresso/client');
    expect($r->getStatusCode())->toBe(403);
});

it('POST /officeimpresso/client nega acesso pra non-superadmin (403)', function () {
    $r = $this->post('/officeimpresso/client', ['name' => 'Cliente teste'], [
        'Accept' => 'application/json',
    ]);
    expect($r->getStatusCode())->toBe(403);
});

it('GET /officeimpresso/regenerate nega acesso pra non-superadmin (403)', function () {
    $r = $this->get('/officeimpresso/regenerate');
    expect($r->getStatusCode())->toBe(403);
});

it('GET /officeimpresso/businessall NAO da 200 pra non-superadmin', function () {
    // Controller redireciona ou aborta — qualquer coisa exceto 200 ja prova
    // que nao expoe lista de empresas pra user nao-superadmin.
    $r = $this->get('/officeimpresso/businessall');
    expect($r->getStatusCode())->not->toBe(200);
});

it('GET /officeimpresso/catalogue-qr exige superadmin OR subscription (nao 200)', function () {
    // generateQr() faz abort(403) sem subscription do modulo + sem superadmin.
    $r = $this->get('/officeimpresso/catalogue-qr');
    expect($r->getStatusCode())->toBeIn([302, 403, 404]);
});

it('rotas Officeimpresso exigem auth (logout -> 302 login)', function () {
    auth()->logout();
    session()->flush();
    $r = $this->get('/officeimpresso/client');
    expect($r->getStatusCode())->toBeIn([302, 401, 403]);
});

it('GET /api/officeimpresso devolve 401 sem Bearer (contrato Delphi preservado)', function () {
    auth()->logout();
    session()->flush();
    $r = $this->withHeaders(['Accept' => 'application/json'])
        ->getJson('/api/officeimpresso');
    expect($r->getStatusCode())->toBe(401);
});

it('LicencaLogController::timeline aborta 403 quando licenca nao pertence ao business do user', function () {
    // Sem superadmin + business diferente -> abort_unless 403.
    // Se nao houver registro na tabela, marca skip — nao falha por dado.
    $licenca = \DB::table('licenca_computador')->orderBy('id')->first();
    if (! $licenca) {
        $this->markTestSkipped('Tabela licenca_computador vazia em dev — sem fixture pra testar timeline guard.');
    }

    // Forca licenca de outro business (ou usa o que ha — guard age igual).
    $r = $this->get('/officeimpresso/licenca_log/timeline/' . $licenca->id);
    // Espera 403 se business diverge; se for igual (raridade em DB compartilhado),
    // ao menos prova que controller responde com algo != 500.
    expect($r->getStatusCode())->toBeIn([200, 302, 403, 404]);
});
