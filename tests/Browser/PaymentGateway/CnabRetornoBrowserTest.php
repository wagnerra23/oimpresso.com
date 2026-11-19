<?php

declare(strict_types=1);

/**
 * Pest 4 Browser — a tela de retorno CNAB no navegador, com axe.
 * Tela: Modules/PaymentGateway/Resources/js/Pages/Settings/PaymentGateways/CnabRetorno.tsx
 * (Settings/PaymentGateways/CnabRetorno · /settings/payment-gateways/{id}/cnab-retorno).
 * Casos em `CnabRetorno.casos.md` ao lado.
 *
 * ── O BURACO QUE ISTO FECHA (medido, não suposto) ─────────────────────────────
 * `node scripts/qa/screen-coverage-map.mjs --screen Settings/PaymentGateways/CnabRetorno`
 * (2026-11-19): `e2e (Browser) ✗`, axe 0 e "sem UC declarado". O motor (`CnabRetornoProcessor`)
 * tem 7 casos Feature (UC-PGCNAB-01..07, lane sqlite do `ci.yml`); a tela nunca foi aberta num
 * navegador por teste. Auditoria do PaymentGateway (2026-11-19), lote 4, PR 4.2.
 *
 * ── O QUE PROVA (viewport 1280) ───────────────────────────────────────────────
 *   - UC-PGCNAB-08: o histórico da credencial mostra o arquivo processado com os contadores
 *     (pagas, canceladas, vencidas, registradas), o status "N erro(s)" que abre a lista de erros,
 *     e o arquivo ainda não processado como "Pendente".
 *   - UC-PGCNAB-09 [T0]: a tela de uma credencial do business 99, pedida pela sessão do 98,
 *     devolve 404 (controle: a do 98 devolve 200).
 *   - Em cada caso com tela: 0 violações axe-core CRITICAL (`level: 0`).
 *
 * ── O QUE NÃO PROVA ──────────────────────────────────────────────────────────
 *   - Processar um arquivo válido (baixa de cobrança): é o Job, coberto por UC-PGCNAB-01..07.
 *   - Prévia antes→depois: não existe ainda (backlog P0, PR 4.3).
 *   - Envio de arquivo (UC-PGCNAB-10): o servidor HTTP do Pest Browser não repassa arquivos ao
 *     Laravel (`LaravelHttpServer.php`, `[], // @TODO files...`); medido no run 44542116020, o
 *     servidor respondeu "O campo arquivo é obrigatório". O caso mora em
 *     `tests/Feature/PaymentGateway/CnabRetornoUploadContratoTest.php`.
 *   - Pixel: nenhum `screenshot()` (ADR 0445).
 *
 * ── COMO ENTRA ────────────────────────────────────────────────────────────────
 * Usuário `VisregEmptyTenantSeeder::USER_EMPTY` (id 98, business 98, Admin#98 — tenant fictício,
 * ADR 0358). O grupo `settings.*` não tem `SetSessionData` e o `/_visreg-login` limpa o bloco
 * `user` da sessão: o login vai para `/home` e a mesma aba navega para a tela.
 *
 * ── DADO ──────────────────────────────────────────────────────────────────────
 * Cada caso grava credenciais e linhas de `cnab_retorno_uploads` com marcador único e apaga pelos
 * ids no `->after()`. Nenhum arquivo CNAB real, nenhum valor monetário, nenhuma cobrança.
 *
 * ── ONDE RODA ─────────────────────────────────────────────────────────────────
 * CI apenas (memory/proibicoes.md §Ambiente): step "E2E de comportamento + axe ·
 * PaymentGateway/CnabRetorno (ADVISORY)" do `visual-regression.yml`. Não foi executado antes do PR.
 */

use Database\Seeders\VisregEmptyTenantSeeder;
use Illuminate\Support\Facades\DB;
use Modules\PaymentGateway\Models\CnabRetornoUpload;
use Modules\PaymentGateway\Models\PaymentGatewayCredential;

const PGCNAB_ADVERSARIO = 99;

beforeEach(function () {
    config(['database.default' => 'mysql', 'database.connections.mysql.database' => 'oimpresso_test']);
    DB::purge('mysql');

    if (! DB::table('users')->where('id', VisregEmptyTenantSeeder::USER_EMPTY)->where('business_id', VisregEmptyTenantSeeder::BIZ_EMPTY)->exists()) {
        throw new RuntimeException('Sem o usuario 98 do business 98: o VisregEmptyTenantSeeder nao rodou.');
    }
    if (! DB::table('business')->where('id', PGCNAB_ADVERSARIO)->exists()) {
        throw new RuntimeException('Sem o business 99: o VisregTenantBLeakSeeder nao rodou.');
    }

    $this->cnMarca = 'PGCNAB' . strtoupper(substr(uniqid(), -6));
    $this->cnCredIds = [];
    $this->cnUploadIds = [];
});

/** Credencial num par (gateway_key, ambiente) livre do business. */
function pgCnabCredencial($test, int $businessId): PaymentGatewayCredential
{
    foreach (['inter', 'c6', 'asaas', 'bcb_pix', 'pesapal'] as $key) {
        foreach (['sandbox', 'production'] as $amb) {
            $livre = ! DB::table('payment_gateway_credentials')
                ->where('business_id', $businessId)->where('gateway_key', $key)->where('ambiente', $amb)->exists();
            if ($livre) {
                // Sem auth no processo do teste: o business vai explícito.
                $cred = PaymentGatewayCredential::withoutGlobalScopes()->create([
                    'business_id' => $businessId,
                    'gateway_key' => $key,
                    'ambiente' => $amb,
                    'ativo' => true,
                    'nome_display' => $test->cnMarca . '-CRED',
                    'config_json' => [],
                    'health_status' => 'ok',
                ]);
                $test->cnCredIds[] = (int) $cred->id;

                return $cred;
            }
        }
    }

    throw new RuntimeException("Sem par (gateway_key, ambiente) livre para o business {$businessId}.");
}

function pgCnabUpload($test, PaymentGatewayCredential $cred, string $nome, array $attrs = []): int
{
    $up = CnabRetornoUpload::withoutGlobalScopes()->create(array_merge([
        'business_id' => $cred->business_id,
        'payment_gateway_credential_id' => $cred->id,
        'arquivo_path' => 'cnab-retornos/e2e/' . $nome,
        'arquivo_nome_original' => $nome,
        'arquivo_tamanho_bytes' => 2048,
        'processado_por_user_id' => VisregEmptyTenantSeeder::USER_EMPTY,
    ], $attrs));
    $test->cnUploadIds[] = (int) $up->id;

    return (int) $up->id;
}

function pgCnabLimpar($test): void
{
    $creds = $test->cnCredIds ?? [];
    if ($creds !== []) {
        // Inclui o que o próprio caso de upload possa ter gravado.
        DB::table('cnab_retorno_uploads')->whereIn('payment_gateway_credential_id', $creds)->delete();
        DB::table('payment_gateway_credentials')->whereIn('id', $creds)->delete();
    }
}

/** Login em /home (popula a sessão), navega para a tela da credencial e espera o histórico resolver. */
function pgCnabAbrir(int $credId, array $marcadores = [])
{
    $rota = "/settings/payment-gateways/{$credId}/cnab-retorno";
    $page = visit('/_visreg-login/' . VisregEmptyTenantSeeder::USER_EMPTY . '?to=' . urlencode('/home'))->resize(1280, 800);
    $page->navigate($rota);

    $marcas = json_encode(array_merge(['retorno cnab', 'uploads recentes', 'enviar arquivo'], array_map('mb_strtolower', $marcadores)));
    $js = "(() => { const t = (document.body ? document.body.innerText : '').replace(/\\s+/g, ' ').toLowerCase();"
        . " if (/unauthorized action/.test(t)) return 'ERRO-403';"
        . " const falta = {$marcas}.filter((m) => !t.includes(m));"
        . " if (falta.length) return 'FALTA ' + falta.join(',');"
        . " const sk = document.querySelectorAll('.animate-pulse').length;"
        . " return sk ? 'SKELETON ' + sk : 'PRONTO'; })()";

    $ultimo = null;
    for ($i = 0; $i < 40; $i++) {
        $ultimo = (string) $page->script($js);
        if ($ultimo === 'PRONTO') {
            return $page;
        }
        $page->wait(0.25);
    }

    throw new RuntimeException("A tela {$rota} nao estabilizou — ultimo estado: '{$ultimo}'.");
}

function pgCnabTexto($page): string
{
    return (string) $page->script("document.body.innerText.replace(/\\s+/g, ' ')");
}

it('UC-PGCNAB-08 · o histórico mostra contadores, erros expansíveis e o arquivo pendente, sem violação axe CRITICAL', function () {
    $cred = pgCnabCredencial($this, VisregEmptyTenantSeeder::BIZ_EMPTY);
    $processado = $this->cnMarca . '-PROC.ret';
    $pendente = $this->cnMarca . '-PEND.ret';
    $erro = 'Linha 7: nosso numero ' . $this->cnMarca . ' sem cobranca';

    pgCnabUpload($this, $cred, $processado, [
        'processado_em' => now(),
        'qtd_paga' => 3,
        'qtd_cancelada' => 1,
        'qtd_vencida' => 2,
        'qtd_registrada' => 4,
        'erros_json' => json_encode([$erro]),
    ]);
    pgCnabUpload($this, $cred, $pendente);

    $page = pgCnabAbrir($cred->id, [$processado, $pendente]);

    $linha = json_encode($processado);
    $celulas = (string) $page->script("(() => { const tr = [...document.querySelectorAll('tbody tr')].find((r) => r.innerText.includes({$linha}));"
        . " return tr ? [...tr.querySelectorAll('td')].slice(4, 8).map((td) => td.innerText.trim()).join('|') : 'SEM-LINHA'; })()");
    expect($celulas)->toBe('3|1|2|4');

    $texto = pgCnabTexto($page);
    expect($texto)->toContain('1 erro(s)');
    expect($texto)->toContain('Pendente');
    expect($texto)->not->toContain($erro);

    $page->script("[...document.querySelectorAll('button[aria-expanded]')].find((b) => b.innerText.trim() === 'Ver').click()");
    $aberto = false;
    for ($i = 0; $i < 20 && ! $aberto; $i++) {
        $aberto = str_contains(pgCnabTexto($page), $erro);
        if (! $aberto) {
            $page->wait(0.1);
        }
    }
    expect($aberto)->toBeTrue();
    $page->assertNoAccessibilityIssues(level: 0);
})->after(fn () => pgCnabLimpar($this));

it('[T0] UC-PGCNAB-09 · a tela de retorno de uma credencial do 99 devolve 404 para a sessão do 98; a do 98 abre', function () {
    $propria = pgCnabCredencial($this, VisregEmptyTenantSeeder::BIZ_EMPTY);
    $alheia = pgCnabCredencial($this, PGCNAB_ADVERSARIO);

    $page = pgCnabAbrir($propria->id);

    $status = fn (int $id) => (int) $page->script("fetch('/settings/payment-gateways/{$id}/cnab-retorno', { headers: { 'Accept': 'text/html' } }).then((r) => r.status)");

    expect($status($propria->id))->toBe(200);
    expect($status($alheia->id))->toBe(404);
    $page->assertNoAccessibilityIssues(level: 0);
})->after(fn () => pgCnabLimpar($this));
