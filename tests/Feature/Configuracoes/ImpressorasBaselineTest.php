<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Baseline F2 (MWART, ADR 0104) de /printers — o comportamento da Blade ANTES da tela React.
 *
 * Thread sistema/playbook/04. Trava o que store/update/destroy fazem hoje, para a F3 (ramo
 * Inertia atrás da flag useV2ConfiguracoesImpressoras) não mudar nada disso sem um teste cair.
 * Mapa campo-a-campo: memory/requisitos/Configuracoes/impressoras-parity.md (itens `alta`).
 *
 * Tenant de teste 98 x cliente fictício 99 (ADR 0358). Toda prova de "não alcançou o alheio"
 * vem com a contraprova positiva no próprio negócio, senão um 403/erro genérico passaria por isolamento.
 */

uses(DatabaseTransactions::class);

beforeEach(function () {
    if (! Schema::hasTable('printers') || ! Schema::hasColumn('users', 'business_id')) {
        $this->markTestSkipped('Schema UltimatePOS ausente (sqlite memory) — rode com DB_CONNECTION=mysql.');
    }

    $this->business = $this->seededTenant();
    $this->user = $this->usuarioComPermissoes(['access_printers'], $this->business);
    $this->actingAs($this->user);
    session(['user.business_id' => $this->business->id, 'user.id' => $this->user->id, 'business.id' => $this->business->id]);
});

function impBaseline(int $businessId, int $criadoPor, array $campos = []): int
{
    return DB::table('printers')->insertGetId(array_merge([
        'business_id' => $businessId, 'name' => 'Imp '.uniqid(), 'connection_type' => 'network',
        'capability_profile' => 'default', 'char_per_line' => '42', 'ip_address' => '192.168.0.31',
        'port' => '9100', 'path' => '', 'created_by' => $criadoPor, 'created_at' => now(), 'updated_at' => now(),
    ], $campos));
}

test('baseline: a lista (DataTable) traz só as impressoras do negócio da sessão', function () {
    $outro = $this->seededSupportClientTenant();
    $minha = impBaseline($this->business->id, $this->user->id, ['name' => 'Caixa Meu '.uniqid()]);
    $alheia = impBaseline($outro->id, $this->user->id, ['name' => 'Caixa Alheio '.uniqid()]);

    $r = $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])->get('/printers');
    $r->assertOk();
    $nomes = collect($r->json('data'))->pluck(0);

    expect($nomes)->toContain(DB::table('printers')->where('id', $minha)->value('name'));
    expect($nomes)->not->toContain(DB::table('printers')->where('id', $alheia)->value('name'));
});

test('baseline: cadastrar grava no negócio da sessão e a conexão de rede zera o caminho', function () {
    $nome = 'Balcão '.uniqid();
    $this->post('/printers', [
        'name' => $nome, 'connection_type' => 'network', 'capability_profile' => 'SP2000',
        'char_per_line' => '48', 'ip_address' => '10.0.0.5', 'port' => '9100', 'path' => 'LPT1',
    ])->assertRedirect();

    $linha = DB::table('printers')->where('name', $nome)->first();
    expect($linha)->not->toBeNull();
    expect((int) $linha->business_id)->toBe((int) $this->business->id);
    expect((int) $linha->created_by)->toBe((int) $this->user->id);
    expect($linha->ip_address)->toBe('10.0.0.5');
    expect((string) $linha->path)->toBe('');
});

test('baseline: conexão Windows/Linux zera IP e porta e guarda o caminho', function () {
    foreach (['windows' => 'COM1', 'linux' => '/dev/usb/lp1'] as $tipo => $caminho) {
        $nome = "Imp {$tipo} ".uniqid();
        $this->post('/printers', [
            'name' => $nome, 'connection_type' => $tipo, 'capability_profile' => 'default',
            'char_per_line' => '42', 'ip_address' => '10.0.0.9', 'port' => '9100', 'path' => $caminho,
        ])->assertRedirect();

        $linha = DB::table('printers')->where('name', $nome)->first();
        expect($linha)->not->toBeNull();
        expect($linha->path)->toBe($caminho);
        expect((string) $linha->ip_address)->toBe('');
        expect((string) $linha->port)->toBe('');
    }
});

test('baseline: editar altera a impressora do negócio e não alcança a de outro', function () {
    $outro = $this->seededSupportClientTenant();
    $minha = impBaseline($this->business->id, $this->user->id);
    $alheia = impBaseline($outro->id, $this->user->id, ['name' => 'Intocada']);
    // put() direto: POST + _method depende do override que o kernel só liga na 1ª requisição (405 se o teste roda primeiro).
    $corpo = ['name' => 'Renomeada', 'connection_type' => 'network',
        'capability_profile' => 'default', 'char_per_line' => '32', 'ip_address' => '10.1.1.1', 'port' => '9100'];

    $this->put("/printers/{$minha}", $corpo)->assertRedirect();
    expect(DB::table('printers')->where('id', $minha)->value('name'))->toBe('Renomeada');

    $this->put("/printers/{$alheia}", $corpo)->assertRedirect();
    expect(DB::table('printers')->where('id', $alheia)->value('name'))->toBe('Intocada');
});

test('baseline: excluir remove a impressora do negócio e não alcança a de outro', function () {
    $outro = $this->seededSupportClientTenant();
    $minha = impBaseline($this->business->id, $this->user->id);
    $alheia = impBaseline($outro->id, $this->user->id);
    $h = ['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'];

    $r = $this->withHeaders($h)->delete("/printers/{$alheia}");
    $r->assertOk();
    expect($r->json('success'))->toBeFalse();
    expect(DB::table('printers')->where('id', $alheia)->exists())->toBeTrue();

    $r = $this->withHeaders($h)->delete("/printers/{$minha}");
    $r->assertOk();
    expect($r->json('success'))->toBeTrue();
    expect(DB::table('printers')->where('id', $minha)->exists())->toBeFalse();
});

test('baseline: sem access_printers a lista e o cadastro devolvem 403', function () {
    $semPermissao = $this->usuarioComPermissoes([], $this->business);
    $this->actingAs($semPermissao);
    $antes = DB::table('printers')->where('business_id', $this->business->id)->count();

    $this->get('/printers')->assertForbidden();
    $this->post('/printers', ['name' => 'Não grava', 'connection_type' => 'network'])->assertForbidden();
    expect(DB::table('printers')->where('business_id', $this->business->id)->count())->toBe($antes);
});
