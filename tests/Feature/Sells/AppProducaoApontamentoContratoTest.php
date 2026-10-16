<?php

declare(strict_types=1);

use App\Services\AppLojas\EtiquetaProducao;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Passport\Passport;

/**
 * Apontamento de produção pelo app (P8, tela 02) — /api/app/producao/apontamentos/* e
 * GET /api/app/producao/etiqueta/{codigo}, sobre o ApontamentoOperadorService do PCP (ADR 0431).
 *
 * Contrato: memory/requisitos/AppMobile/api/tela-02-apontamento.md + handoff propostas-app
 * (PLAYBOOK-PRS.md: outra empresa → 404 sem vazar, erros {erro, mensagem}, Idempotency-Key;
 * AJUSTES-DA-EMPRESA.md: app_apontamento_exige_qr, padrão false) + tradução combinada com a
 * thread 04 de Produção (ApontamentoRecusado->codigo → HTTP). A regra do apontamento é testada no
 * ProducaoApontamentoContratoTest do módulo; aqui é a API do app. NÃO derivado do controller.
 *
 * Tenants: 98 (canônico, ADR 0358) × 99 (adversário). Nunca biz=4. Todo "não aparece / não grava"
 * de outra empresa tem controle positivo em par. Hora fixa com Carbon::setTestNow (o Service
 * valida o `momento` contra o relógio do servidor).
 */
uses(DatabaseTransactions::class);

const APP_APT_DIA = '2026-10-15';

function appAptOs(int $bizId, ?int $orcamentoId = null): int
{
    return (int) DB::table('comvis_os')->insertGetId([
        'business_id' => $bizId, 'orcamento_id' => $orcamentoId,
        'numero' => 'OS-2099-' . random_int(10000, 99999), 'status_etapa' => 'producao',
        'valor_total' => 0, 'created_at' => now(), 'updated_at' => now(),
    ]);
}

function appAptOrcamento(int $bizId, int $criadoPor, string $cliente, array $itens): int
{
    $contato = (int) DB::table('contacts')->insertGetId([
        'business_id' => $bizId, 'type' => 'customer', 'name' => $cliente, 'contact_status' => 'active',
        'created_by' => $criadoPor, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $orc = (int) DB::table('comvis_orcamentos')->insertGetId([
        'business_id' => $bizId, 'numero' => 'ORC-2099-' . random_int(10000, 99999), 'contato_id' => $contato,
        'data_emissao' => APP_APT_DIA, 'status' => 'aprovado', 'created_at' => now(), 'updated_at' => now(),
    ]);
    foreach ($itens as $i => $desc) {
        DB::table('comvis_orcamento_itens')->insert([
            'orcamento_id' => $orc, 'business_id' => $bizId, 'descricao' => $desc, 'ordem' => $i,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    return $orc;
}

function appAptPosto(int $bizId, string $tipo = 'pessoa'): int
{
    return (int) DB::table('pcp_postos')->insertGetId([
        'business_id' => $bizId, 'codigo' => 'APP-' . uniqid(), 'nome' => 'Impressão ' . $tipo,
        'tipo_custo' => $tipo, 'custo_hora' => '30.00', 'created_at' => now(), 'updated_at' => now(),
    ]);
}

function appAptEtapa(int $bizId, int $posto, int $osId, ?int $resp, string $status = 'pendente', string $hora = '08:00:00'): int
{
    return (int) DB::table('pcp_programacoes')->insertGetId([
        'business_id' => $bizId, 'os_tipo' => 'comvis_os', 'os_id' => $osId, 'posto_id' => $posto,
        'inicio_previsto' => APP_APT_DIA . ' ' . $hora, 'fim_previsto' => Carbon::parse(APP_APT_DIA . ' ' . $hora)->addHour(),
        'status' => $status, 'user_id' => $resp, 'created_by' => $resp ?? 1, 'created_at' => now(), 'updated_at' => now(),
    ]);
}

/** Motivo de pausa do posto (`pcp_motivos`, thread 03); antes dela basta um id > 0. */
function appAptMotivo(int $bizId, int $posto): int
{
    if (! Schema::hasTable('pcp_motivos')) {
        return 7;
    }

    return (int) DB::table('pcp_motivos')->insertGetId(['business_id' => $bizId, 'posto_id' => $posto, 'tipo' => 'pausa',
        'nome' => 'Troca de bobina', 'created_at' => now(), 'updated_at' => now()]);
}

function appAptExigeQr(int $bizId, bool $liga): void
{
    $pos = json_decode((string) DB::table('business')->where('id', $bizId)->value('pos_settings'), true) ?: [];
    $pos['app_apontamento_exige_qr'] = $liga;
    DB::table('business')->where('id', $bizId)->update(['pos_settings' => json_encode($pos)]);
}

/** POST com Idempotency-Key. */
function appAptPost($t, string $url, array $corpo, string $chave)
{
    return $t->withHeaders(['Idempotency-Key' => $chave])->postJson($url, $corpo);
}

beforeEach(function () {
    if (! file_exists(storage_path('oauth-public.key'))) {
        \Illuminate\Support\Facades\Artisan::call('passport:keys', ['--force' => true]);
    }
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('Requer schema MySQL UltimatePOS + trigger append-only do PCP (ADR 0358).');
    }
    foreach (['pcp_apontamentos', 'pcp_programacoes', 'pcp_postos', 'pcp_eventos', 'ponto_marcacoes', 'comvis_os', 'comvis_orcamentos', 'comvis_orcamento_itens', 'contacts'] as $t) {
        if (! Schema::hasTable($t)) {
            $this->markTestSkipped("Schema ausente ({$t}).");
        }
    }
    Carbon::setTestNow(APP_APT_DIA . ' 10:00:00');

    $this->bizId = (int) $this->seededTenant()->id;
    $this->outroBizId = (int) $this->seededSupportClientTenant()->id;
    // PRÉ-CONDIÇÃO: empresas distintas, senão o cross-tenant é tautológico.
    expect($this->outroBizId)->not->toBe($this->bizId);

    $this->user = $this->usuarioComPermissoes(['producao.access']);
    if (app(\App\Utils\ModuleUtil::class)->isSuperadminInstalled() && Schema::hasTable('subscriptions')) {
        DB::table('subscriptions')->insert([
            'business_id' => $this->bizId, 'package_id' => 0, 'package_price' => 0,
            'start_date' => now()->subDay()->toDateString(), 'end_date' => now()->addDay()->toDateString(),
            'package_details' => json_encode(['producao_module' => 1]), 'created_id' => $this->user->id,
            'status' => 'approved', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }
    appAptExigeQr($this->bizId, false);
    $this->posto = appAptPosto($this->bizId);

    Passport::actingAs($this->user, [], 'api');
});

afterEach(fn () => Carbon::setTestNow());

it('UC-APPAPT-01 sem token 401; sem producao.access 403 em leitura e escrita, nada gravado', function () {
    $os = appAptOs($this->bizId);
    $etapa = appAptEtapa($this->bizId, $this->posto, $os, (int) $this->user->id);

    $nada = $this->usuarioComPermissoes([]);
    Passport::actingAs($nada, [], 'api');
    $this->getJson('/api/app/producao/apontamentos/em-andamento')->assertStatus(403)->assertJsonPath('erro', 'sem_permissao');
    appAptPost($this, '/api/app/producao/apontamentos/iniciar', ['programacao_id' => $etapa], 'k01')
        ->assertStatus(403)->assertJsonPath('erro', 'sem_permissao');
    expect(DB::table('pcp_apontamentos')->where('programacao_id', $etapa)->count())->toBe(0);

    $this->app['auth']->forgetGuards();
    $this->withHeaders(['Accept' => 'application/json'])->get('/api/app/producao/apontamentos/em-andamento')->assertStatus(401);
});

it('UC-APPAPT-02 etiqueta: QR da empresa e número digitado abrem a etapa do operador; hash de outra empresa e OS do 99 → 404', function () {
    $orc = appAptOrcamento($this->bizId, (int) $this->user->id, 'Ótica Visão Clara', ['Fachada ACM', 'Letra caixa']);
    $os = appAptOs($this->bizId, $orc);
    $etapa = appAptEtapa($this->bizId, $this->posto, $os, (int) $this->user->id);

    $r = $this->getJson('/api/app/producao/etiqueta/' . rawurlencode(EtiquetaProducao::codigo($this->bizId, $os)))->assertOk();
    expect($r->json('pedido.id'))->toBe($os);
    expect($r->json('pedido.resumo'))->toBe('Fachada ACM + Letra caixa');
    expect($r->json('pedido.cliente'))->toBe('Ótica Visão Clara');
    expect($r->json('etapa.programacao_id'))->toBe($etapa);
    expect($r->json('etapa.posto.id'))->toBe($this->posto);
    expect($r->json('etapa.responsavel.id'))->toBe((int) $this->user->id);
    expect($r->json('pode_apontar'))->toBeTrue();
    $numero = (string) DB::table('comvis_os')->where('id', $os)->value('numero');
    expect($this->getJson('/api/app/producao/etiqueta/' . $os)->assertOk()->json('etapa.programacao_id'))->toBe($etapa);
    expect($this->getJson('/api/app/producao/etiqueta/' . rawurlencode(strtolower($numero)))->assertOk()->json('pedido.numero'))->toBe($numero);

    // Mesmo id com o hash de OUTRA empresa: 404 sem dizer que o pedido existe.
    $this->getJson('/api/app/producao/etiqueta/' . rawurlencode(EtiquetaProducao::codigo($this->outroBizId, $os)))
        ->assertStatus(404)->assertExactJson(['erro' => 'nao_encontrado', 'mensagem' => 'Etiqueta não encontrada.']);
    // OS do 99 com etapa no 99, pelo número e pelo QR do 99: 404 para quem é do 98.
    $alheia = appAptOs($this->outroBizId);
    appAptEtapa($this->outroBizId, appAptPosto($this->outroBizId), $alheia, null);
    $this->getJson('/api/app/producao/etiqueta/' . $alheia)->assertStatus(404)->assertJsonPath('erro', 'nao_encontrado');
    $this->getJson('/api/app/producao/etiqueta/' . rawurlencode(EtiquetaProducao::codigo($this->outroBizId, $alheia)))
        ->assertStatus(404)->assertJsonPath('erro', 'nao_encontrado');
});

it('UC-APPAPT-03 etapa de outro operador: etiqueta diz com quem está (pode_apontar=false); iniciar → 409 etapa_alheia sem gravar', function () {
    $colega = $this->usuarioComPermissoes(['producao.access']);
    $os = appAptOs($this->bizId);
    $etapa = appAptEtapa($this->bizId, $this->posto, $os, (int) $colega->id);

    $r = $this->getJson('/api/app/producao/etiqueta/' . $os)->assertOk();
    expect($r->json('pode_apontar'))->toBeFalse();
    expect($r->json('etapa.responsavel.id'))->toBe((int) $colega->id);

    appAptPost($this, '/api/app/producao/apontamentos/iniciar', ['programacao_id' => $etapa], 'k03')
        ->assertStatus(409)->assertJsonPath('erro', 'etapa_alheia')->assertJsonPath('etapa.responsavel.id', (int) $colega->id);
    expect(DB::table('pcp_apontamentos')->where('programacao_id', $etapa)->count())->toBe(0);
});

it('UC-APPAPT-04 app_apontamento_exige_qr: número → 422 campos.codigo; iniciar exige o QR da OS da etapa', function () {
    $os = appAptOs($this->bizId);
    $etapa = appAptEtapa($this->bizId, $this->posto, $os, (int) $this->user->id);
    appAptExigeQr($this->bizId, true);

    $this->getJson('/api/app/producao/etiqueta/' . $os)
        ->assertStatus(422)->assertJsonPath('erro', 'validacao')->assertJsonStructure(['campos' => ['codigo']]);
    $this->getJson('/api/app/producao/etiqueta/' . rawurlencode(EtiquetaProducao::codigo($this->bizId, $os)))->assertOk();

    appAptPost($this, '/api/app/producao/apontamentos/iniciar', ['programacao_id' => $etapa], 'k04a')
        ->assertStatus(422)->assertJsonStructure(['campos' => ['codigo']]);
    // QR de OUTRA OS da mesma empresa: a etapa não é dela → 404.
    appAptPost($this, '/api/app/producao/apontamentos/iniciar', [
        'programacao_id' => $etapa, 'codigo' => EtiquetaProducao::codigo($this->bizId, appAptOs($this->bizId)),
    ], 'k04b')->assertStatus(404);
    expect(DB::table('pcp_apontamentos')->where('programacao_id', $etapa)->count())->toBe(0);

    appAptPost($this, '/api/app/producao/apontamentos/iniciar', [
        'programacao_id' => $etapa, 'codigo' => EtiquetaProducao::codigo($this->bizId, $os),
    ], 'k04c')->assertStatus(201);
    expect(DB::table('pcp_apontamentos')->where('programacao_id', $etapa)->count())->toBe(1);
});

it('UC-APPAPT-05 iniciar grava no PCP da empresa do token com a hora do toque; reenvio 200 igual; 2º de pessoa → 409 em_andamento', function () {
    $os = appAptOs($this->bizId);
    $etapa = appAptEtapa($this->bizId, $this->posto, $os, (int) $this->user->id);
    $toque = ['programacao_id' => $etapa, 'momento' => APP_APT_DIA . 'T09:40:00-03:00'];

    $r = appAptPost($this, '/api/app/producao/apontamentos/iniciar', $toque, 'k05-1')->assertStatus(201);
    $id = (int) $r->json('id');
    $linha = DB::table('pcp_apontamentos')->where('id', $id)->first();
    expect((int) $linha->business_id)->toBe($this->bizId);
    expect($linha->tipo)->toBe('inicio');
    expect((int) $linha->user_id)->toBe((int) $this->user->id);
    expect(Carbon::parse((string) $linha->momento)->equalTo(Carbon::parse($toque['momento'])))->toBeTrue();

    // Reenvio da fila: mesma chave e mesmo corpo → 200 com a mesma linha, sem duplicar.
    expect(appAptPost($this, '/api/app/producao/apontamentos/iniciar', $toque, 'k05-1')->assertStatus(200)->json('id'))->toBe($id);
    expect(DB::table('pcp_apontamentos')->where('programacao_id', $etapa)->count())->toBe(1);
    // Mesma chave, corpo diferente → conflito.
    appAptPost($this, '/api/app/producao/apontamentos/iniciar', ['programacao_id' => $etapa, 'momento' => APP_APT_DIA . 'T09:45:00-03:00'], 'k05-1')
        ->assertStatus(422)->assertJsonPath('erro', 'idempotencia_conflito');

    $outra = appAptEtapa($this->bizId, $this->posto, appAptOs($this->bizId), (int) $this->user->id, 'pendente', '09:00:00');
    appAptPost($this, '/api/app/producao/apontamentos/iniciar', ['programacao_id' => $outra], 'k05-2')
        ->assertStatus(409)->assertJsonPath('erro', 'em_andamento')->assertJsonPath('apontamento_id', $id);

    $a = $this->getJson('/api/app/producao/apontamentos/em-andamento')->assertOk();
    expect($a->json('pessoa.id'))->toBe($id);
    expect($a->json('pessoa.pedido.id'))->toBe($os);
    expect($a->json('pessoa.etapa.programacao_id'))->toBe($etapa);
    expect($a->json('maquinas'))->toBe([]);
});

it('UC-APPAPT-06 hora do aparelho fora dos limites → 422 campos.momento; etapa encerrada → 422 bloqueado; nada gravado', function () {
    $etapa = appAptEtapa($this->bizId, $this->posto, appAptOs($this->bizId), (int) $this->user->id);
    appAptPost($this, '/api/app/producao/apontamentos/iniciar', ['programacao_id' => $etapa, 'momento' => APP_APT_DIA . 'T13:10:00Z'], 'k06a')
        ->assertStatus(422)->assertJsonStructure(['campos' => ['momento']]); // 10:10 em Brasília = 10 min à frente (> 5 min)
    appAptPost($this, '/api/app/producao/apontamentos/iniciar', ['programacao_id' => $etapa, 'momento' => '2026-10-11T10:00:00-03:00'], 'k06b')
        ->assertStatus(422)->assertJsonStructure(['campos' => ['momento']]); // > 72 h atrás
    appAptPost($this, '/api/app/producao/apontamentos/iniciar', ['programacao_id' => $etapa, 'momento' => 'ontem'], 'k06c')
        ->assertStatus(422)->assertJsonStructure(['campos' => ['momento']]);
    expect(DB::table('pcp_apontamentos')->where('programacao_id', $etapa)->count())->toBe(0);

    $fim = appAptEtapa($this->bizId, $this->posto, appAptOs($this->bizId), (int) $this->user->id, 'concluida');
    appAptPost($this, '/api/app/producao/apontamentos/iniciar', ['programacao_id' => $fim], 'k06d')
        ->assertStatus(422)->assertJsonPath('erro', 'bloqueado');
    // Controle positivo: dentro do limite grava.
    appAptPost($this, '/api/app/producao/apontamentos/iniciar', ['programacao_id' => $etapa, 'momento' => APP_APT_DIA . 'T09:58:00-03:00'], 'k06e')
        ->assertStatus(201);
});

it('UC-APPAPT-07 pausar exige motivo; concluir devolve minutos pelo momento; encerrado não muda; corrigir é append-only e não duplica', function () {
    $etapa = appAptEtapa($this->bizId, $this->posto, appAptOs($this->bizId), (int) $this->user->id);
    $id = (int) appAptPost($this, '/api/app/producao/apontamentos/iniciar', ['programacao_id' => $etapa, 'momento' => APP_APT_DIA . 'T08:00:00-03:00'], 'k07-i')->json('id');

    appAptPost($this, "/api/app/producao/apontamentos/{$id}/pausar", [], 'k07-p0')->assertStatus(422)->assertJsonStructure(['campos' => ['motivo_id']]);
    if (Schema::hasTable('pcp_motivos')) {
        // Motivo de OUTRO posto: o Service recusa (só vale o cadastro do posto do apontamento).
        appAptPost($this, "/api/app/producao/apontamentos/{$id}/pausar", ['motivo_id' => appAptMotivo($this->bizId, appAptPosto($this->bizId))], 'k07-pX')
            ->assertStatus(422)->assertJsonStructure(['campos' => ['motivo_id']]);
        expect(DB::table('pcp_apontamentos')->where('inicio_id', $id)->count())->toBe(0);
    }
    $p = appAptPost($this, "/api/app/producao/apontamentos/{$id}/pausar", ['motivo_id' => appAptMotivo($this->bizId, $this->posto), 'momento' => APP_APT_DIA . 'T08:30:00-03:00'], 'k07-p')->assertStatus(201);
    expect($p->json('minutos'))->toBe(30);
    expect($p->json('encerrado_por'))->toBe('pausa');

    $id2 = (int) appAptPost($this, '/api/app/producao/apontamentos/iniciar', ['programacao_id' => $etapa, 'momento' => APP_APT_DIA . 'T08:40:00-03:00'], 'k07-i2')->json('id');
    $f = appAptPost($this, "/api/app/producao/apontamentos/{$id2}/concluir", ['momento' => APP_APT_DIA . 'T09:25:00-03:00'], 'k07-f')->assertStatus(201);
    expect($f->json())->toMatchArray(['id' => $id2, 'encerrado_por' => 'fim', 'minutos' => 45]);
    expect(DB::table('pcp_programacoes')->where('id', $etapa)->value('status'))->toBe('concluida');
    // Reenvio igual → 200 igual; outra chave sobre o encerrado → 422 bloqueado.
    expect(appAptPost($this, "/api/app/producao/apontamentos/{$id2}/concluir", ['momento' => APP_APT_DIA . 'T09:25:00-03:00'], 'k07-f')->assertStatus(200)->json('minutos'))->toBe(45);
    appAptPost($this, "/api/app/producao/apontamentos/{$id2}/concluir", [], 'k07-f2')->assertStatus(422)->assertJsonPath('erro', 'bloqueado');

    appAptPost($this, "/api/app/producao/apontamentos/{$id2}/corrigir", ['momento' => APP_APT_DIA . 'T09:10:00-03:00'], 'k07-c0')
        ->assertStatus(422)->assertJsonStructure(['campos' => ['justificativa']]);
    $c = appAptPost($this, "/api/app/producao/apontamentos/{$id2}/corrigir", ['momento' => APP_APT_DIA . 'T09:10:00-03:00', 'justificativa' => 'Parei antes'], 'k07-c')
        ->assertStatus(201)->assertJsonPath('situacao', 'aguardando_gestor');
    expect(appAptPost($this, "/api/app/producao/apontamentos/{$id2}/corrigir", ['momento' => APP_APT_DIA . 'T09:10:00-03:00', 'justificativa' => 'Parei antes'], 'k07-c')
        ->assertStatus(200)->json('id'))->toBe($c->json('id'));
    appAptPost($this, "/api/app/producao/apontamentos/{$id2}/corrigir", ['momento' => APP_APT_DIA . 'T09:11:00-03:00', 'justificativa' => 'Parei antes'], 'k07-c')
        ->assertStatus(422)->assertJsonPath('erro', 'idempotencia_conflito');
    expect(DB::table('pcp_eventos')->where('business_id', $this->bizId)->where('tipo', 'correcao_solicitada')->count())->toBe(1);
    // O par original fica (append-only): ainda 1 linha de fim, no horário da conclusão.
    $fins = DB::table('pcp_apontamentos')->where('inicio_id', $id2)->pluck('momento')->all();
    expect($fins)->toHaveCount(1);
    expect(Carbon::parse((string) $fins[0])->equalTo(Carbon::parse(APP_APT_DIA . 'T09:25:00-03:00')))->toBeTrue();
});

it('UC-APPAPT-08 pausar/concluir/corrigir apontamento do 99 ou de outro operador → 404, nada gravado; o dono consegue', function () {
    $postoAlheio = appAptPosto($this->outroBizId);
    $alheio = (int) DB::table('pcp_apontamentos')->insertGetId([
        'business_id' => $this->outroBizId, 'chave_idempotencia' => 'k08-99-' . uniqid(), 'tipo' => 'inicio',
        'posto_id' => $postoAlheio, 'tipo_custo' => 'pessoa', 'custo_hora' => '30.00', 'user_id' => (int) $this->user->id,
        'iniciado_por' => (int) $this->user->id, 'momento' => APP_APT_DIA . ' 09:00:00', 'origem' => 'qr',
    ]);
    $colega = $this->usuarioComPermissoes(['producao.access']);
    $etapaColega = appAptEtapa($this->bizId, $this->posto, appAptOs($this->bizId), (int) $colega->id);
    Passport::actingAs($colega, [], 'api');
    $doColega = (int) appAptPost($this, '/api/app/producao/apontamentos/iniciar', ['programacao_id' => $etapaColega], 'k08-col')
        ->assertStatus(201)->json('id');
    Passport::actingAs($this->user, [], 'api');

    foreach ([$alheio, $doColega] as $id) {
        appAptPost($this, "/api/app/producao/apontamentos/{$id}/pausar", ['motivo_id' => 7], "k08-p{$id}")->assertStatus(404)->assertJsonPath('erro', 'nao_encontrado');
        appAptPost($this, "/api/app/producao/apontamentos/{$id}/concluir", [], "k08-f{$id}")->assertStatus(404)->assertJsonPath('erro', 'nao_encontrado');
        appAptPost($this, "/api/app/producao/apontamentos/{$id}/corrigir", ['momento' => APP_APT_DIA . 'T09:30:00-03:00', 'justificativa' => 'Tentativa alheia'], "k08-c{$id}")
            ->assertStatus(404)->assertJsonPath('erro', 'nao_encontrado');
        expect(DB::table('pcp_apontamentos')->where('inicio_id', $id)->count())->toBe(0);
    }
    expect($this->getJson('/api/app/producao/apontamentos/em-andamento')->assertOk()->json('pessoa'))->toBeNull();

    // Controle positivo: o colega conclui o dele.
    Passport::actingAs($colega, [], 'api');
    appAptPost($this, "/api/app/producao/apontamentos/{$doColega}/concluir", [], 'k08-ok')->assertStatus(201);
});

it('UC-APPAPT-09 escrita sem Idempotency-Key ou com chave > 64 → 422, nada gravado', function () {
    $etapa = appAptEtapa($this->bizId, $this->posto, appAptOs($this->bizId), (int) $this->user->id);
    $this->postJson('/api/app/producao/apontamentos/iniciar', ['programacao_id' => $etapa])
        ->assertStatus(422)->assertJsonPath('erro', 'validacao')->assertJsonStructure(['campos' => ['idempotency_key']]);
    appAptPost($this, '/api/app/producao/apontamentos/iniciar', ['programacao_id' => $etapa], str_repeat('x', 65))
        ->assertStatus(422)->assertJsonStructure(['campos' => ['idempotency_key']]);
    expect(DB::table('pcp_apontamentos')->where('programacao_id', $etapa)->count())->toBe(0);
});

it('UC-APPAPT-10 motivos-pausa: só pausa ATIVA do posto, na ordem do gestor; posto do 99 → lista vazia; em-andamento leva os do posto; o pausar aceita o id listado', function () {
    if (! Schema::hasTable('pcp_motivos')) {
        $this->markTestSkipped('Schema ausente (pcp_motivos, thread 03).');
    }
    $motivo = fn (int $biz, int $posto, string $nome, int $ordem = 0, string $tipo = 'pausa', bool $ativo = true) => (int) DB::table('pcp_motivos')
        ->insertGetId(['business_id' => $biz, 'posto_id' => $posto, 'tipo' => $tipo, 'nome' => $nome, 'ordem' => $ordem,
            'ativo' => $ativo, 'created_at' => now(), 'updated_at' => now()]);
    $outroPosto = appAptPosto($this->bizId, 'maquina');
    $postoDo99 = appAptPosto($this->outroBizId);

    $setup = $motivo($this->bizId, $this->posto, 'Setup', 2);
    $bobina = $motivo($this->bizId, $this->posto, 'Troca de bobina', 1);
    $motivo($this->bizId, $this->posto, 'Antigo', 0, 'pausa', false);
    $motivo($this->bizId, $this->posto, 'Cor', 0, 'refugo');
    $material = $motivo($this->bizId, $outroPosto, 'Falta de material', 1);
    $do99 = $motivo($this->outroBizId, $postoDo99, 'Motivo do 99', 1);

    // Filtro por posto: forma exata, ordem do cadastro, sem inativo nem refugo.
    $this->getJson('/api/app/producao/motivos-pausa?posto_id=' . $this->posto)->assertOk()->assertExactJson(['motivos' => [
        ['id' => $bobina, 'posto_id' => $this->posto, 'nome' => 'Troca de bobina'],
        ['id' => $setup, 'posto_id' => $this->posto, 'nome' => 'Setup'],
    ]]);
    // Sem filtro: os dois postos da empresa, nada do 99.
    $todos = collect($this->getJson('/api/app/producao/motivos-pausa')->assertOk()->json('motivos'));
    expect($todos->pluck('id')->all())->toContain($bobina, $setup, $material);
    expect($todos->pluck('id')->all())->not->toContain($do99);
    expect($todos->pluck('nome')->all())->not->toContain('Antigo', 'Cor');
    // Posto do 99: lista vazia, sem dizer que o posto existe. Controle positivo: o 99 vê o dele.
    $this->getJson('/api/app/producao/motivos-pausa?posto_id=' . $postoDo99)->assertOk()->assertExactJson(['motivos' => []]);
    expect(DB::table('pcp_motivos')->where('posto_id', $postoDo99)->where('ativo', true)->count())->toBe(1);
    $this->getJson('/api/app/producao/motivos-pausa?posto_id=abc')->assertStatus(422)->assertJsonStructure(['campos' => ['posto_id']]);

    // em-andamento traz os motivos do posto do apontamento aberto, e o pausar aceita o id listado.
    $etapa = appAptEtapa($this->bizId, $this->posto, appAptOs($this->bizId), (int) $this->user->id);
    $id = (int) appAptPost($this, '/api/app/producao/apontamentos/iniciar', ['programacao_id' => $etapa, 'momento' => APP_APT_DIA . 'T09:00:00-03:00'], 'k10-i')
        ->assertStatus(201)->json('id');
    $a = $this->getJson('/api/app/producao/apontamentos/em-andamento')->assertOk();
    expect($a->json('pessoa.motivos_pausa'))->toBe([['id' => $bobina, 'nome' => 'Troca de bobina'], ['id' => $setup, 'nome' => 'Setup']]);
    appAptPost($this, "/api/app/producao/apontamentos/{$id}/pausar", ['motivo_id' => $a->json('pessoa.motivos_pausa.0.id'), 'momento' => APP_APT_DIA . 'T09:30:00-03:00'], 'k10-p')
        ->assertStatus(201)->assertJsonPath('encerrado_por', 'pausa');

    // Sem producao.access: 403, nada listado.
    Passport::actingAs($this->usuarioComPermissoes([]), [], 'api');
    $this->getJson('/api/app/producao/motivos-pausa')->assertStatus(403)->assertJsonPath('erro', 'sem_permissao');
});
