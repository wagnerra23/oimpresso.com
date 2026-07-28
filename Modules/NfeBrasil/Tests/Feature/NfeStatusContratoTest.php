<?php

declare(strict_types=1);

// @covers-us US-NFE-002 — status NFC-e pós-venda: o payload que a operadora lê enquanto espera a SEFAZ.
// Contrato da tela: resources/js/Pages/NfeBrasil/Transactions/NfceStatus.casos.md — UC-NFST-01..05.
// Os casos derivam do CONTRATO (SDD §5.3 F3 + §6.1 CU-NFE-02 + charter + US-NFE-002), não da
// implementação — teste derivado do código é tautológico (proibicoes.md §5 2026-06-05).

use App\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\NfeBrasil\Models\NfeEmissao;

uses(Tests\TestCase::class);

/**
 * US-NFE-002 fase 2C · GET /nfe-brasil/api/transactions/{tx}/nfe-status.
 *
 * POR QUE ESTE ARQUIVO EXISTE AO LADO DO `NfeStatusControllerTest`
 * ---------------------------------------------------------------
 * O irmão cobre a **forma** do payload com `withSession` e SEM `actingAs` — e nesse estado o
 * global scope `ScopeByBusiness` faz early-return em `! auth()->check()`, então o "guard de
 * isolamento" dele prova só o `where` manual do Controller. Aqui usamos `actingAs` E semeamos as
 * DUAS chaves de sessão que o módulo usa para o mesmo tenant (`business.id`, lida pelos
 * Controllers, e `user.business_id`, lida pelo scope — SDD §5.4.2), então as duas camadas valem.
 * É a mesma causa que derrubou NfeBrasilMultiTenantIsolationTest e Wave25NfeSaturationTest em
 * 2026-06-24, documentada na allowlist de nfebrasil-pest.yml.
 *
 * Todo caso de isolamento carrega CONTROLE POSITIVO — sem ele, "não vazou" pode ser só "não havia
 * nada", que é verde por não-execução (proibicoes.md §5 2026-07-24).
 *
 * POR QUE MYSQL-ONLY
 * ------------------
 * Isolamento só vale contra o schema real; no lane sqlite (:memory:) o schema é recriado à mão e o
 * verde MENTE — razão declarada da lane nfebrasil-pest.yml.
 *
 * biz=1 (Wagner) e biz=2 (contraparte) são os semeados por `pest-mysql-setup`.
 * NUNCA biz=4 — é ROTA LIVRE / Larissa, cliente real em produção (ADR 0101).
 *
 * @see memory/requisitos/NfeBrasil/SDD-emissao-fiscal-v1.0.md §5.3 F3 · §6.1
 * @see resources/js/Pages/NfeBrasil/Transactions/NfceStatus.casos.md
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 */

function nfstBiz(): int
{
    return 1;
}

function nfstBizOutro(): int
{
    return 2;
}

/** Faixa de transaction_id sintético — alta o bastante pra não colidir com dado semeado. */
function nfstTx(int $offset): int
{
    return 7300000 + $offset;
}

function nfstUrl(int $tx): string
{
    return "/nfe-brasil/api/transactions/{$tx}/nfe-status";
}

function nfstEmissao(int $businessId, int $tx, array $overrides = []): NfeEmissao
{
    return NfeEmissao::create(array_merge([
        'business_id'    => $businessId,
        'transaction_id' => $tx,
        'modelo'         => 65,
        'serie'          => '1',
        'numero'         => 1,
        'status'         => 'autorizada',
        'cstat'          => '100',
        'valor_total'    => 100.00,
    ], $overrides));
}

function nfstLimpar(): void
{
    DB::table('nfe_emissoes')
        ->whereIn('business_id', [nfstBiz(), nfstBizOutro()])
        ->where('transaction_id', '>=', nfstTx(0))
        ->delete();
}

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('MySQL-only: isolamento multi-tenant exige schema real (ADR 0101; ver nfebrasil-pest.yml).');
    }
    if (! Schema::hasTable('nfe_emissoes')) {
        $this->markTestSkipped('Tabela nfe_emissoes ausente — rode as migrations do módulo.');
    }

    nfstLimpar();

    $user = User::where('business_id', nfstBiz())->firstOrFail();
    $this->actingAs($user);

    // As DUAS chaves: `business.id` (Controller) e `user.business_id` (ScopeByBusiness).
    // Semear só uma faria metade do guard no-opar e o verde não provaria nada.
    // `withSession` (e não o helper `session()`) porque é ele que persiste entre requests do
    // mesmo teste — e com o bloco `user` já preenchido o middleware `SetSessionData` fica no-op,
    // então o que semeamos aqui é o que o Controller lê.
    $this->withSession(['business.id' => nfstBiz(), 'user.business_id' => nfstBiz()]);
});

afterEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite' || ! Schema::hasTable('nfe_emissoes')) {
        return;
    }
    nfstLimpar();
});

// ---------------------------------------------------------------------------------------
// UC-NFST-01 · Venda sem nota mostra "ainda não emitida" — não erro
// ---------------------------------------------------------------------------------------
it('UC-NFST-01 · venda sem emissão responde 200 com estado não-emitida e mensagem', function () {
    $tx = nfstTx(1);

    // Pré-condição explícita: a venda realmente não tem emissão (senão o caso mediria outra coisa).
    expect(DB::table('nfe_emissoes')->where('transaction_id', $tx)->count())->toBe(0);

    $res = $this->getJson(nfstUrl($tx));

    // O contrato é COMPORTAMENTO, não o nome da chave: resposta bem-sucedida, estado vazio
    // identificável e um texto legível pro operador.
    $res->assertOk();

    $corpo = $res->json();
    expect($corpo)->toBeArray();

    $temEstadoVazio = collect($corpo)->contains(fn ($v) => $v === null);
    expect($temEstadoVazio)->toBeTrue('nenhum campo do payload sinaliza "ainda não emitida"');

    $temTextoLegivel = collect($corpo)
        ->contains(fn ($v) => is_string($v) && mb_strlen($v) >= 10);
    expect($temTextoLegivel)->toBeTrue('nenhuma mensagem legível acompanha o estado vazio');
});

// ---------------------------------------------------------------------------------------
// UC-NFST-02 · O desfecho da nota de outro business nunca aparece  [T0]
// ---------------------------------------------------------------------------------------
it('UC-NFST-02 · consulta não devolve emissão de outro business, e devolve a do próprio', function () {
    $txAlheio = nfstTx(2);
    $txProprio = nfstTx(3);

    $chaveAlheia = '35210199999999000199650010000009991000000099';
    nfstEmissao(nfstBizOutro(), $txAlheio, [
        'numero'      => 999,
        'chave_44'    => $chaveAlheia,
        'valor_total' => 4321.00,
    ]);

    $chavePropria = '35210111111111000199650010000001111000000011';
    nfstEmissao(nfstBiz(), $txProprio, [
        'numero'      => 111,
        'chave_44'    => $chavePropria,
        'valor_total' => 12.34,
    ]);

    // (a) A nota do vizinho não pode aparecer de forma nenhuma — nem chave, nem número, nem valor.
    $alheio = $this->getJson(nfstUrl($txAlheio));
    $alheio->assertOk();
    $bruto = json_encode($alheio->json(), JSON_UNESCAPED_UNICODE);

    expect($bruto)->not->toContain($chaveAlheia);
    expect($bruto)->not->toContain('999');
    expect($bruto)->not->toContain('4321');

    // (b) CONTROLE POSITIVO — a minha aparece. Sem isto, (a) passaria com o endpoint quebrado.
    $proprio = $this->getJson(nfstUrl($txProprio));
    $proprio->assertOk();
    expect(json_encode($proprio->json(), JSON_UNESCAPED_UNICODE))->toContain($chavePropria);
});

// ---------------------------------------------------------------------------------------
// UC-NFST-03 · A consulta só se declara terminal nos 3 estados terminais
// ---------------------------------------------------------------------------------------
it('UC-NFST-03 · o indicador terminal é verdadeiro só em autorizada, rejeitada e denegada', function () {
    $esperado = [
        'pendente'   => false,
        'autorizada' => true,
        'rejeitada'  => true,
        'denegada'   => true,
    ];

    $offset = 10;
    foreach ($esperado as $status => $deveSerTerminal) {
        $tx = nfstTx($offset++);
        nfstEmissao(nfstBiz(), $tx, ['status' => $status, 'numero' => $offset]);

        $corpo = $this->getJson(nfstUrl($tx))->assertOk()->json();

        // Pré-condição anti-vácuo: o payload precisa ser o DESTA emissão — senão estaríamos
        // medindo a resposta "não emitida", que também tem terminal falso.
        expect($corpo)->toHaveKey('status');
        expect($corpo['status'])->toBe($status);

        $terminal = collect($corpo)->first(fn ($v) => is_bool($v));
        expect($terminal)->toBe(
            $deveSerTerminal,
            "estado '{$status}' deveria ter terminal=" . ($deveSerTerminal ? 'true' : 'false'),
        );
    }
});

// ---------------------------------------------------------------------------------------
// UC-NFST-04 · Depois de uma retentativa, vale a emissão mais recente
// ---------------------------------------------------------------------------------------
it('UC-NFST-04 · com retentativa, a consulta devolve a emissão mais recente', function () {
    $tx = nfstTx(20);

    nfstEmissao(nfstBiz(), $tx, ['status' => 'rejeitada', 'cstat' => '215', 'numero' => 500]);
    $segunda = nfstEmissao(nfstBiz(), $tx, ['status' => 'autorizada', 'cstat' => '100', 'numero' => 501]);

    // Pré-condição: há de fato DUAS. Com uma só, "devolveu a mais recente" é trivialmente verdade.
    expect(DB::table('nfe_emissoes')->where('transaction_id', $tx)->count())->toBe(2);

    $corpo = $this->getJson(nfstUrl($tx))->assertOk()->json();

    // Distinguimos pelo NÚMERO da nota, não pelo status: com dois `autorizada` o status não
    // separaria nada, e é o número que o operador usa pra decidir se reemite.
    expect($corpo['numero'])->toBe(501);
    expect($corpo['emissao_id'] ?? null)->toBe($segunda->id);
});

// ---------------------------------------------------------------------------------------
// UC-NFST-05 · O payload não carrega segredo nem XML  [T0]
// ---------------------------------------------------------------------------------------
it('UC-NFST-05 · nenhum segredo de certificado nem conteúdo de XML viaja no payload de status', function () {
    $tx = nfstTx(30);

    $xmlSecreto = '<nfeProc><NFe><infNFe><dest><CPF>00000000000</CPF></dest></infNFe></NFe></nfeProc>';

    nfstEmissao(nfstBiz(), $tx, [
        'numero'   => 777,
        'chave_44' => '35210122222222000199650010000007771000000077',
    ]);

    // Se a coluna de XML existir no schema, semeia conteúdo pra o assert ter o que encontrar.
    // Sem isto o caso mediria a ausência de um dado que nunca esteve lá (verde por não-execução).
    $colunaXml = collect(['xml_conteudo', 'xml', 'xml_autorizado'])
        ->first(fn ($c) => Schema::hasColumn('nfe_emissoes', $c));

    if ($colunaXml !== null) {
        DB::table('nfe_emissoes')->where('transaction_id', $tx)->update([$colunaXml => $xmlSecreto]);
    }

    $corpo = $this->getJson(nfstUrl($tx))->assertOk()->json();
    $bruto = json_encode($corpo, JSON_UNESCAPED_UNICODE);

    // Pré-condição anti-vácuo: o payload é o desta emissão (senão "não vazou" é trivial).
    expect($bruto)->toContain('777');

    // O contrato é sobre o VALOR, não sobre o nome da chave — renomear a chave não pode fazer o
    // vazamento passar.
    if ($colunaXml !== null) {
        expect($bruto)->not->toContain('<infNFe');
        expect($bruto)->not->toContain('<CPF>');
    }

    if (Schema::hasTable('nfe_certificados') && Schema::hasColumn('nfe_certificados', 'encrypted_password')) {
        $senhaCert = DB::table('nfe_certificados')->where('business_id', nfstBiz())->value('encrypted_password');
        if (! empty($senhaCert)) {
            expect($bruto)->not->toContain((string) $senhaCert);
        }
    }

    // E nenhuma chave do payload pode se chamar algo que denuncie transporte de segredo.
    foreach (array_keys($corpo) as $chave) {
        expect(str_contains(strtolower((string) $chave), 'password'))->toBeFalse();
        expect(str_contains(strtolower((string) $chave), 'senha'))->toBeFalse();
    }
});
