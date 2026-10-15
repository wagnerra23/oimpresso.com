<?php

declare(strict_types=1);

use App\Services\AppLojas\EtiquetaProducao;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Passport\Passport;

/**
 * Apontamento de produção pelo app (P8, tela 02) — /api/app/producao/apontamentos/* e
 * GET /api/app/producao/etiqueta/{codigo}.
 *
 * Contrato: memory/requisitos/AppMobile/api/tela-02-apontamento.md + handoff propostas-app
 * (PLAYBOOK-PRS.md: outra empresa → 404 sem vazar, erros {erro, mensagem}, Idempotency-Key em
 * app_idempotencia; AJUSTES-DA-EMPRESA.md: app_apontamento_exige_qr, padrão false).
 * NÃO derivado do controller.
 *
 * Tenants: 98 (canônico, ADR 0358) × 99 (adversário). Nunca biz=4. Todo "não aparece / não grava"
 * de outra empresa tem controle positivo em par — senão seria verde por vácuo.
 */
uses(DatabaseTransactions::class);

function apAptOs(int $bizId, string $etapa = 'producao', ?int $orcamentoId = null): int
{
    return (int) DB::table('comvis_os')->insertGetId([
        'business_id' => $bizId, 'orcamento_id' => $orcamentoId,
        'numero' => 'OS-2099-' . random_int(10000, 99999), 'status_etapa' => $etapa,
        'valor_total' => 0, 'created_at' => now(), 'updated_at' => now(),
    ]);
}

function apAptOrcamento(int $bizId, int $criadoPor, string $cliente, array $itens): int
{
    $contato = (int) DB::table('contacts')->insertGetId([
        'business_id' => $bizId, 'type' => 'customer', 'name' => $cliente, 'contact_status' => 'active',
        'created_by' => $criadoPor, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $orc = (int) DB::table('comvis_orcamentos')->insertGetId([
        'business_id' => $bizId, 'numero' => 'ORC-2099-' . random_int(10000, 99999), 'contato_id' => $contato,
        'data_emissao' => now()->toDateString(), 'status' => 'aprovado', 'created_at' => now(), 'updated_at' => now(),
    ]);
    foreach ($itens as $i => $desc) {
        DB::table('comvis_orcamento_itens')->insert([
            'orcamento_id' => $orc, 'business_id' => $bizId, 'descricao' => $desc, 'ordem' => $i,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    return $orc;
}

function apAptAberto(int $bizId, int $osId, int $operador): int
{
    return (int) DB::table('comvis_apontamentos')->insertGetId([
        'business_id' => $bizId, 'os_id' => $osId, 'operador_id' => $operador,
        'iniciado_em' => now()->subMinutes(30), 'created_at' => now(), 'updated_at' => now(),
    ]);
}

function apAptExigeQr(int $bizId, bool $liga): void
{
    $pos = json_decode((string) DB::table('business')->where('id', $bizId)->value('pos_settings'), true) ?: [];
    $pos['app_apontamento_exige_qr'] = $liga;
    DB::table('business')->where('id', $bizId)->update(['pos_settings' => json_encode($pos)]);
}

beforeEach(function () {
    if (! file_exists(storage_path('oauth-public.key'))) {
        \Illuminate\Support\Facades\Artisan::call('passport:keys', ['--force' => true]);
    }
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('Requer schema MySQL UltimatePOS (ADR 0358).');
    }
    foreach (['comvis_os', 'comvis_apontamentos', 'comvis_orcamentos', 'comvis_orcamento_itens', 'app_idempotencia', 'contacts'] as $t) {
        if (! Schema::hasTable($t)) {
            $this->markTestSkipped("Schema ausente ({$t}).");
        }
    }

    $this->bizId = (int) $this->seededTenant()->id;
    $this->outroBizId = (int) $this->seededSupportClientTenant()->id;
    // PRÉ-CONDIÇÃO: empresas distintas, senão o cross-tenant é tautológico.
    expect($this->outroBizId)->not->toBe($this->bizId);

    $this->user = $this->usuarioComPermissoes(['comvis.os.view', 'comvis.apontamento.create']);

    if (app(\App\Utils\ModuleUtil::class)->isSuperadminInstalled() && Schema::hasTable('subscriptions')) {
        DB::table('subscriptions')->insert([
            'business_id' => $this->bizId, 'package_id' => 0, 'package_price' => 0,
            'start_date' => now()->subDay()->toDateString(), 'end_date' => now()->addDay()->toDateString(),
            'package_details' => json_encode(['comunicacao_visual_module' => 1]), 'created_id' => $this->user->id,
            'status' => 'approved', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }
    apAptExigeQr($this->bizId, false);

    Passport::actingAs($this->user, [], 'api');
});

it('UC-APPAPT-01 sem token 401; sem ver OS 403; ver sem apontar lê a etiqueta com pode_apontar=false e não inicia', function () {
    $os = apAptOs($this->bizId);

    $soVer = $this->usuarioComPermissoes(['comvis.os.view']);
    Passport::actingAs($soVer, [], 'api');
    expect($this->getJson('/api/app/producao/etiqueta/' . $os)->assertOk()->json('pode_apontar'))->toBeFalse();
    $this->withHeaders(['Idempotency-Key' => 'k-soVer'])
        ->postJson('/api/app/producao/apontamentos/iniciar', ['pedido_id' => $os, 'etapa' => 'producao'])
        ->assertStatus(403)->assertJsonPath('erro', 'sem_permissao');
    expect(DB::table('comvis_apontamentos')->where('os_id', $os)->count())->toBe(0);

    $nada = $this->usuarioComPermissoes([]);
    Passport::actingAs($nada, [], 'api');
    $this->getJson('/api/app/producao/apontamentos/em-andamento')->assertStatus(403)->assertJsonPath('erro', 'sem_permissao');

    $this->app['auth']->forgetGuards();
    $this->withHeaders(['Accept' => 'application/json'])->get('/api/app/producao/apontamentos/em-andamento')->assertStatus(401);
});

it('UC-APPAPT-02 etiqueta: QR da empresa e número digitado resolvem o pedido; QR de outra empresa e OS do 99 → 404', function () {
    $orc = apAptOrcamento($this->bizId, (int) $this->user->id, 'Ótica Visão Clara', ['Fachada ACM', 'Letra caixa']);
    $os = apAptOs($this->bizId, 'producao', $orc);

    $r = $this->getJson('/api/app/producao/etiqueta/' . rawurlencode(EtiquetaProducao::codigo($this->bizId, $os)))->assertOk();
    expect($r->json('pedido.id'))->toBe($os);
    expect($r->json('pedido.resumo'))->toBe('Fachada ACM + Letra caixa');
    expect($r->json('pedido.cliente'))->toBe('Ótica Visão Clara');
    expect($r->json('etapa'))->toBe(['chave' => 'producao', 'rotulo' => 'Produção']);
    expect($r->json('pode_apontar'))->toBeTrue();
    // Número digitado: o id e o número da OS.
    $numero = (string) DB::table('comvis_os')->where('id', $os)->value('numero');
    expect($this->getJson('/api/app/producao/etiqueta/' . $os)->assertOk()->json('pedido.id'))->toBe($os);
    expect($this->getJson('/api/app/producao/etiqueta/' . rawurlencode(strtolower($numero)))->assertOk()->json('pedido.numero'))->toBe($numero);

    // Mesmo id, mas com o hash de OUTRA empresa: 404 sem dizer que o pedido existe.
    $this->getJson('/api/app/producao/etiqueta/' . rawurlencode(EtiquetaProducao::codigo($this->outroBizId, $os)))
        ->assertStatus(404)->assertExactJson(['erro' => 'nao_encontrado', 'mensagem' => 'Etiqueta não encontrada.']);
    // OS do 99, pelo id digitado e pelo QR do próprio 99: 404 para quem é do 98.
    $alheia = apAptOs($this->outroBizId);
    $this->getJson('/api/app/producao/etiqueta/' . $alheia)->assertStatus(404)->assertJsonPath('erro', 'nao_encontrado');
    $this->getJson('/api/app/producao/etiqueta/' . rawurlencode(EtiquetaProducao::codigo($this->outroBizId, $alheia)))
        ->assertStatus(404)->assertJsonPath('erro', 'nao_encontrado');
});

it('UC-APPAPT-03 app_apontamento_exige_qr ligado: número digitado → 422 campos.codigo; iniciar exige o QR do pedido', function () {
    $os = apAptOs($this->bizId);
    apAptExigeQr($this->bizId, true);

    $this->getJson('/api/app/producao/etiqueta/' . $os)
        ->assertStatus(422)->assertJsonPath('erro', 'validacao')->assertJsonStructure(['campos' => ['codigo']]);
    // Controle positivo: o QR continua valendo.
    $this->getJson('/api/app/producao/etiqueta/' . rawurlencode(EtiquetaProducao::codigo($this->bizId, $os)))->assertOk();

    $this->withHeaders(['Idempotency-Key' => 'k-qr-sem'])
        ->postJson('/api/app/producao/apontamentos/iniciar', ['pedido_id' => $os, 'etapa' => 'producao'])
        ->assertStatus(422)->assertJsonStructure(['campos' => ['codigo']]);
    $this->withHeaders(['Idempotency-Key' => 'k-qr-outro'])
        ->postJson('/api/app/producao/apontamentos/iniciar', [
            'pedido_id' => $os, 'etapa' => 'producao', 'codigo' => EtiquetaProducao::codigo($this->bizId, apAptOs($this->bizId)),
        ])->assertStatus(404);
    expect(DB::table('comvis_apontamentos')->where('os_id', $os)->count())->toBe(0);

    $this->withHeaders(['Idempotency-Key' => 'k-qr-ok'])
        ->postJson('/api/app/producao/apontamentos/iniciar', [
            'pedido_id' => $os, 'etapa' => 'producao', 'codigo' => EtiquetaProducao::codigo($this->bizId, $os),
        ])->assertStatus(201);
    expect(DB::table('comvis_apontamentos')->where('os_id', $os)->count())->toBe(1);
});

it('UC-APPAPT-04 iniciar grava no business do token; mesma chave devolve a mesma resposta; outra chave com um aberto → 409 em_andamento', function () {
    $os = apAptOs($this->bizId);

    $r = $this->withHeaders(['Idempotency-Key' => 'k-ini-1'])
        ->postJson('/api/app/producao/apontamentos/iniciar', ['pedido_id' => $os, 'etapa' => 'producao'])->assertStatus(201);
    $id = (int) $r->json('id');
    expect($r->json('iniciado_em'))->toBeString();
    $linha = DB::table('comvis_apontamentos')->where('id', $id)->first();
    expect((int) $linha->business_id)->toBe($this->bizId);
    expect((int) $linha->operador_id)->toBe((int) $this->user->id);
    expect($linha->finalizado_em)->toBeNull();

    $repetida = $this->withHeaders(['Idempotency-Key' => 'k-ini-1'])
        ->postJson('/api/app/producao/apontamentos/iniciar', ['pedido_id' => $os, 'etapa' => 'producao'])->assertStatus(200);
    expect($repetida->json('id'))->toBe($id);
    expect(DB::table('comvis_apontamentos')->where('os_id', $os)->count())->toBe(1);

    // Mesma chave, corpo diferente: conflito, nada novo gravado.
    $this->withHeaders(['Idempotency-Key' => 'k-ini-1'])
        ->postJson('/api/app/producao/apontamentos/iniciar', ['pedido_id' => apAptOs($this->bizId), 'etapa' => 'producao'])
        ->assertStatus(422)->assertJsonPath('erro', 'idempotencia_conflito');

    $outro = $this->withHeaders(['Idempotency-Key' => 'k-ini-2'])
        ->postJson('/api/app/producao/apontamentos/iniciar', ['pedido_id' => apAptOs($this->bizId), 'etapa' => 'producao'])
        ->assertStatus(409)->assertJsonPath('erro', 'em_andamento');
    expect($outro->json('item.id'))->toBe($id);

    $andamento = $this->getJson('/api/app/producao/apontamentos/em-andamento')->assertOk();
    expect($andamento->json('item.id'))->toBe($id);
    expect($andamento->json('item.pedido.id'))->toBe($os);
    expect($andamento->json('item.etapa.chave'))->toBe('producao');
});

it('UC-APPAPT-05 iniciar recusa etapa que mudou (409 etapa_mudou), pedido encerrado (422 bloqueado) e pedido do 99 (404) sem gravar', function () {
    $os = apAptOs($this->bizId, 'finalizando');
    $this->withHeaders(['Idempotency-Key' => 'k-et'])
        ->postJson('/api/app/producao/apontamentos/iniciar', ['pedido_id' => $os, 'etapa' => 'producao'])
        ->assertStatus(409)->assertJsonPath('erro', 'etapa_mudou')->assertJsonPath('etapa.chave', 'finalizando');

    $fim = apAptOs($this->bizId, 'concluida');
    $this->withHeaders(['Idempotency-Key' => 'k-fim'])
        ->postJson('/api/app/producao/apontamentos/iniciar', ['pedido_id' => $fim, 'etapa' => 'concluida'])
        ->assertStatus(422)->assertJsonPath('erro', 'bloqueado');

    $alheia = apAptOs($this->outroBizId);
    $this->withHeaders(['Idempotency-Key' => 'k-99'])
        ->postJson('/api/app/producao/apontamentos/iniciar', ['pedido_id' => $alheia, 'etapa' => 'producao'])
        ->assertStatus(404)->assertExactJson(['erro' => 'nao_encontrado', 'mensagem' => 'Pedido não encontrado.']);

    expect(DB::table('comvis_apontamentos')->whereIn('os_id', [$os, $fim, $alheia])->count())->toBe(0);
    // A recusa desfaz a reserva: a mesma chave inicia quando a etapa bate (controle positivo).
    $this->withHeaders(['Idempotency-Key' => 'k-et'])
        ->postJson('/api/app/producao/apontamentos/iniciar', ['pedido_id' => $os, 'etapa' => 'finalizando'])
        ->assertStatus(201);
});

it('UC-APPAPT-06 finalizar devolve minutos e fecha; cancelar fecha com motivo e m² 0; encerrado não muda mais', function () {
    $os = apAptOs($this->bizId);
    $ap = apAptAberto($this->bizId, $os, (int) $this->user->id);

    $r = $this->withHeaders(['Idempotency-Key' => 'k-fin'])
        ->postJson("/api/app/producao/apontamentos/{$ap}/finalizar", ['m2_produzido' => '4.500'])->assertOk();
    expect($r->json())->toBe(['id' => $ap, 'minutos' => 30]);
    $linha = DB::table('comvis_apontamentos')->where('id', $ap)->first();
    expect($linha->finalizado_em)->not->toBeNull();
    expect((string) $linha->m2_produzido)->toBe('4.500');
    // Repetida com a mesma chave: mesma resposta. Com outra chave: já encerrado.
    expect($this->withHeaders(['Idempotency-Key' => 'k-fin'])
        ->postJson("/api/app/producao/apontamentos/{$ap}/finalizar", ['m2_produzido' => '4.500'])->assertOk()->json('minutos'))->toBe(30);
    $this->withHeaders(['Idempotency-Key' => 'k-fin-2'])
        ->postJson("/api/app/producao/apontamentos/{$ap}/finalizar", [])->assertStatus(422)->assertJsonPath('erro', 'bloqueado');

    $ap2 = apAptAberto($this->bizId, $os, (int) $this->user->id);
    $this->withHeaders(['Idempotency-Key' => 'k-can-sem'])
        ->postJson("/api/app/producao/apontamentos/{$ap2}/cancelar", [])->assertStatus(422)->assertJsonStructure(['campos' => ['motivo']]);
    $this->withHeaders(['Idempotency-Key' => 'k-can'])
        ->postJson("/api/app/producao/apontamentos/{$ap2}/cancelar", ['motivo' => 'Peça errada'])
        ->assertOk()->assertExactJson(['id' => $ap2, 'cancelado' => true]);
    $c = DB::table('comvis_apontamentos')->where('id', $ap2)->first();
    expect($c->observacoes)->toBe('[CANCELADO] Peça errada');
    expect((float) $c->m2_produzido)->toBe(0.0);
    expect($this->getJson('/api/app/producao/apontamentos/em-andamento')->assertOk()->json('item'))->toBeNull();
});

it('UC-APPAPT-07 finalizar/cancelar apontamento do 99 ou de outro operador → 404 e a linha alheia não muda', function () {
    $alheio = apAptAberto($this->outroBizId, apAptOs($this->outroBizId), (int) $this->user->id);
    $colega = $this->usuarioComPermissoes(['comvis.os.view', 'comvis.apontamento.create']);
    $doColega = apAptAberto($this->bizId, apAptOs($this->bizId), (int) $colega->id);

    foreach ([$alheio, $doColega] as $id) {
        $this->withHeaders(['Idempotency-Key' => "k-f-{$id}"])
            ->postJson("/api/app/producao/apontamentos/{$id}/finalizar", [])
            ->assertStatus(404)->assertJsonPath('erro', 'nao_encontrado');
        $this->withHeaders(['Idempotency-Key' => "k-c-{$id}"])
            ->postJson("/api/app/producao/apontamentos/{$id}/cancelar", ['motivo' => 'Tentativa alheia'])
            ->assertStatus(404)->assertJsonPath('erro', 'nao_encontrado');
        expect(DB::table('comvis_apontamentos')->where('id', $id)->value('finalizado_em'))->toBeNull();
    }
    // O apontamento do 99 não aparece no em-andamento de quem é do 98.
    expect($this->getJson('/api/app/producao/apontamentos/em-andamento')->assertOk()->json('item'))->toBeNull();

    // Controle positivo: o colega fecha o próprio.
    Passport::actingAs($colega, [], 'api');
    $this->withHeaders(['Idempotency-Key' => 'k-colega'])
        ->postJson("/api/app/producao/apontamentos/{$doColega}/finalizar", [])->assertOk();
    expect(DB::table('comvis_apontamentos')->where('id', $doColega)->value('finalizado_em'))->not->toBeNull();
});

it('UC-APPAPT-08 escrita sem Idempotency-Key → 422 campos.idempotency_key, nada gravado', function () {
    $os = apAptOs($this->bizId);
    $this->postJson('/api/app/producao/apontamentos/iniciar', ['pedido_id' => $os, 'etapa' => 'producao'])
        ->assertStatus(422)->assertJsonPath('erro', 'validacao')->assertJsonStructure(['campos' => ['idempotency_key']]);
    expect(DB::table('comvis_apontamentos')->where('os_id', $os)->count())->toBe(0);
});
