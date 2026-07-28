<?php

declare(strict_types=1);

// @covers-us US-NFE-052 — UI de manifestação do destinatário: lote, sync NSU, painéis lazy e isolamento.
// (US-NFE-050 — os 4 eventos NT 2014.002 — NÃO é reivindicada aqui: este arquivo dubla a ida à SEFAZ.
//  Quem a cobre de verdade é ManifestacaoServiceTest, que a declara.)
// Contrato da tela: resources/js/Pages/NfeBrasil/Manifestacao/Index.casos.md — UC-NFMA-01..06.
// Os casos derivam do CONTRATO (SDD §5.3 F9/F10 + §6.3 + ADR 0116 + charter), não da implementação —
// teste derivado do código é tautológico (proibicoes.md §5 2026-06-05).

use App\User;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\NfeBrasil\Jobs\BuscarDfesRecebidosJob;
use Modules\NfeBrasil\Models\NfeDfeEvento;
use Modules\NfeBrasil\Models\NfeDfeRecebido;
use Modules\NfeBrasil\Services\CertificadoService;
use Modules\NfeBrasil\Services\Manifestacao\ManifestacaoService;
use Spatie\Permission\Models\Permission;

uses(Tests\TestCase::class);

/**
 * US-NFE-052 · /nfe-brasil/manifestacao.
 *
 * O QUE ESTE ARQUIVO **NÃO** COBRE (de propósito)
 * -----------------------------------------------
 * A whitelist dos 4 eventos SEFAZ e a regra de justificativa ≥15 chars são contrato de
 * `CU-FISC-07` (memory/requisitos/Fiscal/SDD-cockpit-fiscal-v1.0.md), porque `/fiscal/dfe` faz a
 * mesma manifestação chamando o MESMO ManifestacaoService. Dois vereditos pro mesmo comportamento é
 * dívida, não cobertura. Aqui fica só o que existe apenas nesta tela: LOTE, SYNC, JSON lazy e
 * isolamento da listagem.
 *
 * POR QUE UM DUBLÊ DE SERVICE (e não um mock que não escreve)
 * ------------------------------------------------------------
 * A SEFAZ não pode ser chamada em teste. O dublê abaixo substitui **apenas a ida à SEFAZ**,
 * reproduzindo fielmente o ramo "evento autorizado" do Service real: cria o `NfeDfeEvento`
 * `autorizado` (cstat 135) e atualiza `status_manifestacao`. Um mock que só conta chamadas deixaria
 * as asserções de banco vazias — e "nada mudou" seria verdade por nada ter sido escrito, que é
 * verde por não-execução (proibicoes.md §5 2026-07-24).
 *
 * POR QUE MYSQL-ONLY · biz=1 e biz=2 (NUNCA biz=4 — ROTA LIVRE em produção, ADR 0101).
 *
 * @see memory/requisitos/NfeBrasil/SDD-emissao-fiscal-v1.0.md §5.3 F9/F10 · §6.3
 * @see memory/decisions/0116-pivot-gold-manifestacao-destinatario-emenda-0115.md
 */

/** Dublê: substitui só a chamada SEFAZ, preservando a persistência local do ramo autorizado. */
final class ManifestacaoServiceSemSefaz extends ManifestacaoService
{
    public function confirmar(NfeDfeRecebido $dfe): NfeDfeEvento
    {
        $jaAutorizado = NfeDfeEvento::where('business_id', (int) $dfe->business_id)
            ->where('dfe_recebido_id', $dfe->id)
            ->where('tipo', NfeDfeEvento::TIPO_CONFIRMACAO)
            ->where('status', 'autorizado')
            ->first();

        if ($jaAutorizado) {
            return $jaAutorizado; // idempotência, igual ao Service real
        }

        $evento = NfeDfeEvento::create([
            'business_id'     => (int) $dfe->business_id,
            'dfe_recebido_id' => $dfe->id,
            'tipo'            => NfeDfeEvento::TIPO_CONFIRMACAO,
            'status'          => 'autorizado',
            'cstat_evento'    => '135',
            'nseq_evento'     => 1,
        ]);

        $dfe->update([
            'status_manifestacao' => NfeDfeRecebido::STATUS_CONFIRMADA,
            'manifestado_em'      => now(),
        ]);

        return $evento;
    }
}

function nfmaBiz(): int
{
    return 1;
}

function nfmaBizOutro(): int
{
    return 2;
}

/** Chave de 44 dígitos, única e determinística por `$seq` (sem aritmética de inteiro gigante). */
function nfmaChave(int $seq): string
{
    return '3521011122233300018955001000000' . str_pad((string) $seq, 13, '0', STR_PAD_LEFT);
}

function nfmaDfe(int $businessId, string $status = 'pendente', int $seq = 1): int
{
    return (int) DB::table('nfe_dfe_recebidos')->insertGetId([
        'business_id'          => $businessId,
        'chave_44'             => nfmaChave($seq),
        'nsu'                  => 900000 + $seq,
        'cnpj_emitente'        => '11222333000181',
        'nome_emitente'        => 'FORNECEDOR TESTE ' . $seq,
        'valor_total'          => 100.00 + $seq,
        'data_emissao'         => now()->subDays(3),
        'status_manifestacao'  => $status,
        'prazo_confirmacao_em' => now()->addDays(30)->toDateString(),
        'created_at'           => now(),
        'updated_at'           => now(),
    ]);
}

function nfmaLimpar(): void
{
    $bizs = [nfmaBiz(), nfmaBizOutro()];
    DB::statement('SET FOREIGN_KEY_CHECKS=0');
    if (Schema::hasTable('nfe_dfe_eventos')) {
        DB::table('nfe_dfe_eventos')->whereIn('business_id', $bizs)->delete();
    }
    if (Schema::hasTable('nfe_dfe_itens')) {
        DB::table('nfe_dfe_itens')->delete();
    }
    DB::table('nfe_dfe_recebidos')->whereIn('business_id', $bizs)->delete();
    DB::statement('SET FOREIGN_KEY_CHECKS=1');
}

/** Spatie cacheia o mapa de permissões — sem invalidar, o grant/revoke do teste não vale. */
function nfmaEsquecerCache(): void
{
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
}

function nfmaConceder(string $permissao): User
{
    $perm = Permission::firstOrCreate(['name' => $permissao, 'guard_name' => 'web']);
    $user = User::where('business_id', nfmaBiz())->firstOrFail();
    $user->givePermissionTo($perm);
    nfmaEsquecerCache();

    return $user;
}

function nfmaLogar(User $user): void
{
    test()->actingAs($user);
    // As DUAS chaves do módulo: `user.business_id` (ScopeByBusiness + este Controller) e
    // `business.id` (os demais Controllers) — SDD §5.4.2. `withSession` (não o helper `session()`)
    // porque é ele que persiste entre requests do mesmo teste; e com o bloco `user` preenchido o
    // middleware `SetSessionData` fica no-op, então o semeado é o que o Controller lê.
    test()->withSession(['user.business_id' => nfmaBiz(), 'business.id' => nfmaBiz()]);
}

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('MySQL-only: isolamento multi-tenant exige schema real (ADR 0101; ver nfebrasil-pest.yml).');
    }
    if (! Schema::hasTable('nfe_dfe_recebidos') || ! Schema::hasTable('nfe_dfe_eventos')) {
        $this->markTestSkipped('Tabelas de DF-e ausentes — rode as migrations do módulo.');
    }

    nfmaLimpar();

    $this->app->bind(ManifestacaoService::class, fn () => new ManifestacaoServiceSemSefaz(
        app(CertificadoService::class),
    ));
});

afterEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite' || ! Schema::hasTable('nfe_dfe_recebidos')) {
        return;
    }
    nfmaLimpar();

    foreach (['nfe.manifestacao.view', 'nfe.manifestacao.manage'] as $nome) {
        $perm = Permission::where('name', $nome)->where('guard_name', 'web')->first();
        $user = User::where('business_id', nfmaBiz())->first();
        if ($perm && $user) {
            $user->revokePermissionTo($perm);
        }
    }
    nfmaEsquecerCache();
});

// ---------------------------------------------------------------------------------------
// UC-NFMA-01 · Lote confirma só o que é meu e está pendente  [T0] [fiscal]
// ---------------------------------------------------------------------------------------
it('UC-NFMA-01 · bulk confirma só a pendente do próprio business e não toca as demais', function () {
    nfmaLogar(nfmaConceder('nfe.manifestacao.manage'));

    $minhaPendente   = nfmaDfe(nfmaBiz(), 'pendente', 1);
    $minhaConfirmada = nfmaDfe(nfmaBiz(), 'confirmada', 2);
    $doVizinho       = nfmaDfe(nfmaBizOutro(), 'pendente', 3);

    $this->post('/nfe-brasil/manifestacao/bulk/confirmar', [
        'ids' => [$minhaPendente, $minhaConfirmada, $doVizinho],
    ])->assertRedirect();

    // (a) CONTROLE POSITIVO — a minha pendente foi manifestada de verdade.
    expect(DB::table('nfe_dfe_recebidos')->where('id', $minhaPendente)->value('status_manifestacao'))
        ->toBe('confirmada');

    // (b) A do vizinho não foi tocada — nem status, nem evento.
    expect(DB::table('nfe_dfe_recebidos')->where('id', $doVizinho)->value('status_manifestacao'))
        ->toBe('pendente');
    expect(DB::table('nfe_dfe_eventos')->where('dfe_recebido_id', $doVizinho)->count())->toBe(0);

    // (c) A já confirmada não ganhou segundo evento (duplicidade = cstat 573 na SEFAZ).
    expect(DB::table('nfe_dfe_eventos')->where('dfe_recebido_id', $minhaConfirmada)->count())->toBe(0);
});

// ---------------------------------------------------------------------------------------
// UC-NFMA-02 · Lote vazio não dispara SEFAZ  [fiscal]
// ---------------------------------------------------------------------------------------
it('UC-NFMA-02 · bulk sem ids devolve erro e não manifesta nada', function () {
    nfmaLogar(nfmaConceder('nfe.manifestacao.manage'));

    // Pré-condição anti-vácuo: existe algo manifestável. Sem isto, "nada mudou" seria trivial.
    $pendente = nfmaDfe(nfmaBiz(), 'pendente', 4);

    $this->post('/nfe-brasil/manifestacao/bulk/confirmar', ['ids' => []])
        ->assertRedirect()
        ->assertSessionHas('error');

    expect(DB::table('nfe_dfe_recebidos')->where('id', $pendente)->value('status_manifestacao'))
        ->toBe('pendente');
    expect(DB::table('nfe_dfe_eventos')->where('dfe_recebido_id', $pendente)->count())->toBe(0);
});

// ---------------------------------------------------------------------------------------
// UC-NFMA-03 · Sync sob demanda enfileira com o meu tenant  [T0]
// ---------------------------------------------------------------------------------------
it('UC-NFMA-03 · sync-now despacha o job de busca carregando o business do tenant', function () {
    Bus::fake();
    nfmaLogar(nfmaConceder('nfe.manifestacao.manage'));

    $this->post('/nfe-brasil/manifestacao/sync-now')
        ->assertRedirect()
        ->assertSessionHas('success');

    // O que importa não é "foi despachado" (presença), e sim COM QUAL TENANT — em fila não existe
    // session(), então o business precisa viajar dentro do job (ADR 0093).
    // Procuramos pelo NOME da propriedade (não por "algum int que seja 1"): `tries = 3` e
    // `backoff = [30,60,120]` também são inteiros do job, e um match cego passaria por acidente.
    Bus::assertDispatched(BuscarDfesRecebidosJob::class, function ($job) {
        foreach ((new ReflectionClass($job))->getProperties() as $prop) {
            if (! str_contains(strtolower($prop->getName()), 'business')) {
                continue;
            }
            $prop->setAccessible(true);

            return (int) $prop->getValue($job) === nfmaBiz();
        }

        return false; // nenhuma propriedade carrega o tenant — o job roda cego na fila
    });
});

// ---------------------------------------------------------------------------------------
// UC-NFMA-04 · Sem a permissão de gerenciar, nenhuma mutação passa  [T0]
// ---------------------------------------------------------------------------------------
it('UC-NFMA-04 · usuário com view mas sem manage recebe 403 nas mutações', function () {
    Bus::fake();
    nfmaLogar(nfmaConceder('nfe.manifestacao.view'));

    $pendente = nfmaDfe(nfmaBiz(), 'pendente', 5);

    $this->post('/nfe-brasil/manifestacao/bulk/confirmar', ['ids' => [$pendente]])->assertForbidden();
    $this->post('/nfe-brasil/manifestacao/sync-now')->assertForbidden();

    expect(DB::table('nfe_dfe_recebidos')->where('id', $pendente)->value('status_manifestacao'))
        ->toBe('pendente');
    Bus::assertNotDispatched(BuscarDfesRecebidosJob::class);

    // CONTROLE POSITIVO — com `manage`, os mesmos dois passam. Sem isto o 403 poderia vir de
    // qualquer outra coisa (rota, CSRF, sessão).
    nfmaLogar(nfmaConceder('nfe.manifestacao.manage'));
    $this->post('/nfe-brasil/manifestacao/bulk/confirmar', ['ids' => [$pendente]])->assertRedirect();
    expect(DB::table('nfe_dfe_recebidos')->where('id', $pendente)->value('status_manifestacao'))
        ->toBe('confirmada');
});

// ---------------------------------------------------------------------------------------
// UC-NFMA-05 · Os painéis laterais não abrem DF-e de outro tenant  [T0]
// ---------------------------------------------------------------------------------------
it('UC-NFMA-05 · itens e eventos de DFe de outro business dão 404, e os do próprio devolvem 200', function () {
    nfmaLogar(nfmaConceder('nfe.manifestacao.view'));

    $meu      = nfmaDfe(nfmaBiz(), 'pendente', 6);
    $doVizinho = nfmaDfe(nfmaBizOutro(), 'pendente', 7);

    $this->getJson("/nfe-brasil/manifestacao/{$doVizinho}/itens")->assertNotFound();
    $this->getJson("/nfe-brasil/manifestacao/{$doVizinho}/eventos")->assertNotFound();

    // CONTROLE POSITIVO — nos meus, responde.
    $this->getJson("/nfe-brasil/manifestacao/{$meu}/itens")->assertOk();
    $this->getJson("/nfe-brasil/manifestacao/{$meu}/eventos")->assertOk();
});

// ---------------------------------------------------------------------------------------
// UC-NFMA-06 · A lista e os KPIs contam só o meu business  [T0]
// ---------------------------------------------------------------------------------------
it('UC-NFMA-06 · listagem e KPI de pendentes contam só o próprio business', function () {
    nfmaLogar(nfmaConceder('nfe.manifestacao.view'));

    nfmaDfe(nfmaBiz(), 'pendente', 8);
    $doVizinho = nfmaDfe(nfmaBizOutro(), 'pendente', 9);

    // Pré-condição anti-vácuo: as duas existem no banco. Sem isto, "não vazou" seria trivial.
    expect(DB::table('nfe_dfe_recebidos')->whereIn('business_id', [nfmaBiz(), nfmaBizOutro()])->count())
        ->toBe(2);

    $this->get('/nfe-brasil/manifestacao')
        ->assertOk()
        ->assertInertia(function ($page) {
            $chaves = collect($page->toArray()['props']['itens']['data'] ?? [])
                ->pluck('chave_44')
                ->all();

            // A listagem NÃO tem `where('business_id')` explícito (só o global scope) enquanto os
            // KPIs têm — SDD §5.4.2. Por isso as duas metades são medidas no MESMO caso.
            expect($chaves)->toContain(nfmaChave(8));      // controle positivo
            expect($chaves)->not->toContain(nfmaChave(9)); // a do vizinho, não

            $pendentes = $page->toArray()['props']['kpis']['pendentes'] ?? null;
            expect($pendentes)->toBe(1); // conta 1, não 2
        });

    // E a do vizinho continua lá, intacta — o isolamento é de leitura, não deleção.
    expect(DB::table('nfe_dfe_recebidos')->where('id', $doVizinho)->count())->toBe(1);
});
