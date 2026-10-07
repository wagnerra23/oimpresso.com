<?php

declare(strict_types=1);

// @covers-us US-NFE-009 — link read-only de 14 dias para o contador (D-CONTADOR caminho 1, thread 15b).
// Contrato da tela: resources/js/Pages/NfeBrasil/Tributacao/Index.casos.md — UC-NFTR-22 · 23

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Modules\NfeBrasil\Events\FiscalRuleCreated;
use Modules\NfeBrasil\Events\FiscalRuleDeleted;
use Modules\NfeBrasil\Events\FiscalRuleUpdated;
use Modules\NfeBrasil\Mail\RevisaoContadorMail;
use Modules\NfeBrasil\Models\NfeContadorLink;
use Modules\NfeBrasil\Models\NfeFiscalRule;
use Modules\NfeBrasil\Models\NfeRevisaoContador;
use Modules\NfeBrasil\Services\Tributacao\ImportRegrasCsvService;
use Modules\NfeBrasil\Services\Tributacao\RevisaoContadorService;

uses(Tests\TestCase::class);

/**
 * Os casos derivam do caminho 1 da D-CONTADOR (link assinado de 14 dias preso a um business +
 * código de 6 dígitos no mesmo e-mail, 15 min, 5 erros bloqueiam) e da US-NFE-009 — não do
 * controller. MySQL-only · tenant 98, vizinho 99 (ADR 0358). NCMs 7716xxxx reservados a este arquivo.
 */

function lnkA(): int
{
    return test()->seededTenant()->id;
}

function lnkB(): int
{
    return test()->seededSupportClientTenant()->id;
}

function lnkLimpar(): void
{
    DB::statement('SET FOREIGN_KEY_CHECKS=0');
    $regras = DB::table('nfe_fiscal_rules')->whereIn('business_id', [lnkA(), lnkB()])->where('ncm', 'like', '7716%')->pluck('id');
    $rev = DB::table('nfe_revisoes_contador')->whereIn('regra_id', $regras)->pluck('id');
    DB::table('activity_log')->where('subject_type', NfeRevisaoContador::class)->whereIn('subject_id', $rev)->delete();
    DB::table('nfe_revisoes_contador')->whereIn('id', $rev)->delete();
    DB::table('nfe_fiscal_rules')->whereIn('id', $regras)->delete();
    DB::table('nfe_contador_links')->where('email', 'like', '%@contador.test')->delete();
    DB::statement('SET FOREIGN_KEY_CHECKS=1');
}

function lnkRegra(int $biz, string $ncm, float $icms): void
{
    $r = app(ImportRegrasCsvService::class)->aplicar($biz, [[
        'ncm' => $ncm, 'uf_origem' => 'SP', 'uf_destino' => null, 'cfop' => '5102', 'csosn' => '102', 'cst' => null,
        'aliquota_icms' => $icms, 'aliquota_pis' => 0, 'aliquota_cofins' => 0, 'aliquota_ipi' => 0,
    ]]);
    expect($r['criadas'])->toBe(1);
}

/** A empresa envia o link pela rota real; devolve a URL assinada que foi para o e-mail. */
function lnkEnviar(string $email): string
{
    $gestor = test()->usuarioComPermissoes(['nfe.tributacao.manage']);
    test()->actingAs($gestor)->withSession(['business.id' => lnkA(), 'user.business_id' => lnkA()]);
    test()->postJson('/nfe-brasil/tributacao/revisoes/link', ['nome' => 'Contadora Teste', 'email' => $email, 'crc' => 'SC-000000/O'])
        ->assertOk();

    // Daqui em diante quem age é o contador: sem login, sessão limpa.
    auth()->guard('web')->forgetUser();
    test()->flushSession();

    return Mail::sent(RevisaoContadorMail::class, fn ($m) => $m->url !== null && $m->hasTo($email))->last()->url;
}

/** Último código mandado ao e-mail. */
function lnkCodigo(string $email): string
{
    return Mail::sent(RevisaoContadorMail::class, fn ($m) => $m->codigo !== null && $m->hasTo($email))->last()->codigo;
}

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('MySQL-only: tabelas nfe_contador_links / nfe_revisoes_contador vêm das migrações 2026_10_07.');
    }
    if (! Schema::hasTable('nfe_contador_links') || ! Schema::hasTable('nfe_revisoes_contador')) {
        $this->markTestSkipped('Migrações da thread 15 não rodaram — rode as migrations do módulo.');
    }
    Mail::fake();
    // O throttle das rotas públicas não é o que este arquivo prova, e o cache dele sobrevive entre
    // testes (uma rodada inteira passa de 30 requisições/min). O limite de tentativas do código é outro
    // mecanismo, e esse é provado aqui.
    $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);
    Event::fake([FiscalRuleCreated::class, FiscalRuleUpdated::class, FiscalRuleDeleted::class]);
    NfeFiscalRule::esquecerVersionamento();
    RevisaoContadorService::esquecerTabela();
    lnkLimpar();
});

afterEach(function () {
    if (DB::connection()->getDriverName() !== 'sqlite' && Schema::hasTable('nfe_contador_links')) {
        lnkLimpar();
    }
});

// ---------------------------------------------------------------------------------------
// UC-NFTR-22 · Link de revisão só abre com o código do e-mail, expira e serve a um business  [T0]
// ---------------------------------------------------------------------------------------
it('UC-NFTR-22 · link assinado + código, 14 dias, um business', function () {
    lnkRegra(lnkA(), '77160001', 0.12);
    lnkRegra(lnkB(), '77169901', 0.12);

    // --- 5 erros bloqueiam o link — nem o código certo abre depois.
    $url = lnkEnviar('bloqueio@contador.test');
    $this->get($url)->assertOk()->assertSee('código de 6 dígitos')->assertDontSee('77160001');
    $certo = lnkCodigo('bloqueio@contador.test');
    $errado = $certo === '000000' ? '111111' : '000000';
    for ($i = 1; $i <= 4; $i++) {
        $this->post($url, ['codigo' => $errado])->assertStatus(422);
    }
    $this->post($url, ['codigo' => $errado])->assertForbidden();
    $this->post($url, ['codigo' => $certo])->assertForbidden();
    $this->get($url)->assertForbidden();
    expect(NfeContadorLink::query()->where('email', 'bloqueio@contador.test')->value('bloqueado_em'))->not->toBeNull();

    // --- Link novo. Sem o código, nada além da tela do código.
    $url = lnkEnviar('contadora@contador.test');
    $link = NfeContadorLink::query()->where('email', 'contadora@contador.test')->firstOrFail();
    expect($link->business_id)->toBe(lnkA())->and($url)->toContain('/revisao/' . lnkA() . '/' . $link->id);
    $this->get($url)->assertOk()->assertDontSee('77160001');
    $this->get("/nfe-brasil/contador/link/{$link->id}/regras.csv")->assertForbidden();
    $revA = NfeRevisaoContador::query()->withoutGlobalScopes()->where('business_id', lnkA())
        ->whereIn('regra_id', NfeFiscalRule::query()->withoutGlobalScopes()->where('ncm', '77160001')->pluck('id'))->firstOrFail();
    $this->postJson("/nfe-brasil/contador/link/{$link->id}/revisoes/{$revA->id}/aceitar")->assertForbidden();

    // Assinatura alterada → 403. Trocar o business na URL também quebra a assinatura → 403.
    $this->get(preg_replace('/signature=([0-9a-f])/', 'signature=x', $url))->assertForbidden();
    $this->get(str_replace('/revisao/' . lnkA() . '/', '/revisao/' . lnkB() . '/', $url))->assertForbidden();

    // Código certo → acesso à lista da empresa A, e só dela.
    $codigo = lnkCodigo('contadora@contador.test');
    $this->post($url, ['codigo' => $codigo])->assertStatus(303);
    $this->get($url)->assertOk()->assertSee('77160001')->assertDontSee('77169901');

    // A empresa A não abre dado da B: revisão do business B pelo link de A → 404, e nada muda.
    $revB = NfeRevisaoContador::query()->withoutGlobalScopes()->where('business_id', lnkB())
        ->whereIn('regra_id', NfeFiscalRule::query()->withoutGlobalScopes()->where('ncm', '77169901')->pluck('id'))->firstOrFail();
    $this->postJson("/nfe-brasil/contador/link/{$link->id}/revisoes/{$revB->id}/aceitar")->assertNotFound();
    expect($revB->fresh()->status)->toBe('pendente');
    // A sessão liberada para ESTE link não serve para outro link.
    $outro = NfeContadorLink::query()->where('email', 'bloqueio@contador.test')->value('id');
    $this->get("/nfe-brasil/contador/link/{$outro}/regras.csv")->assertForbidden();

    // CONTROLE POSITIVO — aceite pelo link grava o contador do link (nome, e-mail, CRC), sem usuário.
    $this->postJson("/nfe-brasil/contador/link/{$link->id}/revisoes/{$revA->id}/aceitar")->assertOk()->assertJsonPath('status', 'aceita');
    $revA->refresh();
    expect($revA->aceito_por_email)->toBe('contadora@contador.test')
        ->and($revA->aceito_por_crc)->toBe('SC-000000/O')
        ->and($revA->aceito_por_user_id)->toBeNull();

    // Código é de uso único: outra sessão, mesmo código → recusado (e sem acesso).
    $this->flushSession();
    $this->post($url, ['codigo' => $codigo])->assertStatus(422);
    $this->get($url)->assertOk()->assertDontSee('77160001');

    // Código expira em 15 min.
    $novo = lnkCodigo('contadora@contador.test');
    $this->travel(16)->minutes();
    $this->post($url, ['codigo' => $novo])->assertStatus(422);
    $this->get($url)->assertDontSee('77160001');

    // Mais de 14 dias → 403, pela assinatura e pelo próprio link.
    $this->travel(15)->days();
    $this->get($url)->assertForbidden();
});

// ---------------------------------------------------------------------------------------
// UC-NFTR-23 · A revisão pelo link mostra só regras fiscais, com de → para, e baixa o CSV  [T0]
// ---------------------------------------------------------------------------------------
it('UC-NFTR-23 · só regras fiscais, com de → para e CSV do Import', function () {
    lnkRegra(lnkA(), '77160010', 0.12);
    lnkRegra(lnkB(), '77169910', 0.17);

    $url = lnkEnviar('diff@contador.test');
    $link = NfeContadorLink::query()->where('email', 'diff@contador.test')->firstOrFail();
    $this->get($url)->assertOk();
    $this->post($url, ['codigo' => lnkCodigo('diff@contador.test')])->assertStatus(303);

    // Toda consulta feita pelas rotas do link: só regra fiscal, revisão, o link, o nome do autor e da empresa.
    $tabelas = [];
    DB::listen(function ($q) use (&$tabelas) {
        preg_match_all('/\b(?:from|join|into|update)\s+`?([a-z0-9_]+)`?/i', $q->sql, $m);
        foreach ($m[1] as $t) {
            $tabelas[$t] = true;
        }
    });

    $this->get($url)->assertOk()
        ->assertSee('77160010')->assertSee('aliquota_icms')->assertSee('0.12')->assertSee('origem: csv')
        ->assertSee('Baixar regras (CSV)')->assertDontSee('77169910');

    $csv = $this->get("/nfe-brasil/contador/link/{$link->id}/regras.csv")->assertOk()->streamedContent();
    $linhas = array_values(array_filter(explode("\n", str_replace("\r", '', $csv))));
    expect($linhas[0])->toBe(implode(',', ImportRegrasCsvService::COLUNAS_OBRIGATORIAS))
        ->and($csv)->toContain('77160010')->and($csv)->not->toContain('77169910');
    // CONTROLE POSITIVO — o arquivo volta pelo Import CSV sem erro (é o caminho 3 da D-CONTADOR).
    $parse = app(ImportRegrasCsvService::class)->parse($csv);
    expect($parse['erros'])->toBe([])->and(collect($parse['linhas'])->pluck('ncm')->all())->toContain('77160010');

    // Pedir ajuste pelo link: sem comentário → 422; com comentário → ajuste_pedido.
    $rev = NfeRevisaoContador::query()->withoutGlobalScopes()->where('business_id', lnkA())
        ->whereIn('regra_id', NfeFiscalRule::query()->withoutGlobalScopes()->where('ncm', '77160010')->pluck('id'))->firstOrFail();
    $this->postJson("/nfe-brasil/contador/link/{$link->id}/revisoes/{$rev->id}/ajuste", ['comentario' => '  '])->assertStatus(422);
    $this->postJson("/nfe-brasil/contador/link/{$link->id}/revisoes/{$rev->id}/ajuste", ['comentario' => 'ICMS 7% em SP.'])
        ->assertOk()->assertJsonPath('status', 'ajuste_pedido');

    // information_schema = Schema::hasTable/hasColumn (metadado do banco, não dado de negócio).
    $permitidas = ['nfe_contador_links', 'nfe_revisoes_contador', 'nfe_fiscal_rules', 'users', 'business', 'activity_log',
        'information_schema'];
    expect(array_values(array_diff(array_keys($tabelas), $permitidas)))->toBe([]);
});
