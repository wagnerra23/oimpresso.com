<?php

declare(strict_types=1);

/**
 * Pest 4 Browser — as 3 telas principais da clínica do Fisio, com axe:
 *   - Fisio/Painel          (/fisio)            Painel.casos.md
 *   - Fisio/Pacientes/Index (/fisio/pacientes)  Pacientes/Index.casos.md
 *   - Fisio/Agenda/Index    (/fisio/agenda)     Agenda/Index.casos.md
 *
 * ── O BURACO QUE ISTO FECHA ───────────────────────────────────────────────────
 * Auditoria do Fisio (2026-11-05), item d18 / lote 9: nenhuma tela do Fisio abria num browser
 * nem passava por axe. Os Feature do módulo (FisioPainelTest, FisioMultiTenantTest,
 * FisioAgendaTelaTest) provam o payload; o que só o browser prova é que a tela MONTA com as
 * props reais depois dos `<Deferred>` e o que o axe mede no DOM. Par do PortalBrowserTest.
 *
 * ── O QUE PROVA (viewport 1280) ───────────────────────────────────────────────
 *   - UC-FPN-06: o Painel lista o paciente da 98 e o HTML não traz o paciente da 99.
 *   - UC-FPI-02: a lista de Pacientes traz o paciente da 98 e não o da 99.
 *   - UC-FAG-02: a sessão marcada aparece na semana com o nome curto, sem o recado do paciente.
 *   - Em cada tela: 0 violações axe-core CRITICAL (`level: 0`, piso do A11yAxeBrowserTest).
 *
 * ── DADO ──────────────────────────────────────────────────────────────────────
 * Tenant 98 contra 99 (ADR 0358; nunca biz=4 nem biz=1). Admin 98 com role `Admin#98` (o
 * Gate::before libera `fisio.access`). As rotas do Fisio exigem o pacote `fisio_module`: a
 * assinatura nasce aqui e sai no fim. Pacientes e contatos marcados por `FCB_QUEIXA` /
 * `FCB_NOME`, apagados pelo id. Os tipos de atendimento da 98 são os que a primeira abertura da
 * agenda cria (AgendaDaSemana::tipos, UC-FAG-01) e ficam, como ficariam na clínica.
 *
 * ── ONDE RODA ─────────────────────────────────────────────────────────────────
 * CI apenas (memory/proibicoes.md §Ambiente): step "E2E de comportamento + axe · Fisio/Clínica
 * (ADVISORY)" do `visual-regression.yml`. Não foi executado antes do PR.
 */

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Fisio\Entities\Appointment;
use Modules\Fisio\Entities\Patient;
use Modules\Fisio\Services\Agenda\AgendaDaSemana;
use Modules\Fisio\Tests\Support\FisioFixtures as F;

const FCB_BIZ = 98;
const FCB_ADMIN = 98;
const FCB_ADVERSARIO = 99;
const FCB_USER_ADVERSARIO = 99;
const FCB_ASSINATURA = 900093;
const FCB_QUEIXA = 'Queixa E2E clinica FCB';
const FCB_NOME = 'Fisioclin Propria Silva';
const FCB_NOME_ALHEIO = 'Fisioclin Alheia Souza';
const FCB_RECADO = 'Recado E2E FCB nao aparece';

beforeEach(function () {
    config(['database.default' => 'mysql', 'database.connections.mysql.database' => 'oimpresso_test']);
    DB::purge('mysql');

    // Falha alto: tenant ausente não pode virar verde por pulo.
    if (! DB::table('users')->where('id', FCB_ADMIN)->where('business_id', FCB_BIZ)->exists()) {
        throw new RuntimeException('Admin do tenant 98 ausente: o VisregEmptyTenantSeeder não rodou.');
    }
    if (! DB::table('users')->where('id', FCB_USER_ADVERSARIO)->where('business_id', FCB_ADVERSARIO)->exists()) {
        throw new RuntimeException('Usuário do business 99 ausente: o VisregTenantBLeakSeeder não rodou.');
    }

    fcbLimpar();
    DB::table('subscriptions')->insert([
        'id' => FCB_ASSINATURA,
        'business_id' => FCB_BIZ,
        'package_id' => 900001, // pacote do VisregTenantSeeder; o que vale aqui é o package_details
        'start_date' => '2026-01-01',
        'end_date' => '2099-12-31',
        'package_price' => 0,
        'package_details' => json_encode(['name' => 'Fixture Fisio clínica', F::PACOTE => '1']),
        'created_id' => FCB_ADMIN,
        'status' => 'approved',
    ]);

    $this->fcbPropria = fcbPaciente(FCB_BIZ, FCB_ADMIN, FCB_NOME);
    $this->fcbAlheia = fcbPaciente(FCB_ADVERSARIO, FCB_USER_ADVERSARIO, FCB_NOME_ALHEIO);
});

afterEach(function () {
    fcbLimpar();
});

/** Paciente com contato de nome fixo (o nome curto da agenda depende dele). */
function fcbPaciente(int $biz, int $criador, string $nome): Patient
{
    $contato = (int) DB::table('contacts')->insertGetId([
        'business_id' => $biz, 'type' => 'customer', 'name' => $nome, 'mobile' => '48999990000',
        'contact_status' => 'active', 'created_by' => $criador, 'created_at' => now(), 'updated_at' => now(),
    ]);

    return F::paciente($biz, $criador, ['contact_id' => $contato, 'main_complaint' => FCB_QUEIXA]);
}

/** Apaga a assinatura e os pacientes marcados por FCB_QUEIXA nos dois tenants. */
function fcbLimpar(): void
{
    DB::table('subscriptions')->where('id', FCB_ASSINATURA)->delete();

    // SUPERADMIN: limpeza das fixtures dos tenants 98 e 99, fora de request.
    $pacientes = Patient::withoutGlobalScopes()->whereIn('business_id', [FCB_BIZ, FCB_ADVERSARIO])
        ->where('main_complaint', FCB_QUEIXA)->get(['id', 'business_id', 'contact_id']);
    foreach ($pacientes as $p) {
        DB::table('fisio_appointments')->where('business_id', $p->business_id)->where('patient_id', $p->id)->delete();
        DB::table('fisio_patients')->where('business_id', $p->business_id)->where('id', $p->id)->delete();
        DB::table('contacts')->where('business_id', $p->business_id)->where('id', $p->contact_id)->delete();
    }
}

/**
 * Loga como o admin 98, abre a rota e espera o h1 ter o título, o seletor aparecer e os
 * esqueletos dos `<Deferred>` sumirem. Falha dizendo o último estado lido.
 */
function fcbAbrir(string $rota, string $titulo, string $seletor)
{
    $page = visit('/_visreg-login/'.FCB_ADMIN.'?to='.urlencode($rota))->resize(1280, 800);

    $t = json_encode($titulo);
    $s = json_encode($seletor);
    $js = "(() => { const t = (document.body ? document.body.innerText : '');"
        ." if (/unauthorized action|sem acesso/i.test(t.slice(0, 300))) return 'ERRO-403 ' + t.slice(0, 120);"
        ." const h1 = (document.querySelector('h1')?.textContent ?? '').trim();"
        ." if (!h1.includes({$t})) return 'H1 ' + h1;"
        ." if (!document.querySelector({$s})) return 'SEM ' + {$s};"
        ." const sk = document.querySelectorAll('[data-slot=\"skeleton\"]').length;"
        ." return sk ? 'SKELETON ' + sk : 'PRONTO'; })()";

    $ultimo = null;
    for ($i = 0; $i < 40; $i++) {
        $ultimo = (string) $page->script($js);
        if ($ultimo === 'PRONTO') {
            return $page;
        }
        $page->wait(0.25);
    }

    throw new RuntimeException("A tela {$rota} não estabilizou — último estado: '{$ultimo}'.");
}

function fcbHtml($page): string
{
    return (string) $page->script('document.documentElement.outerHTML');
}

it('UC-FPN-06 · Fisio/Painel lista o paciente da 98, não o da 99, sem violação axe CRITICAL', function () {
    $page = fcbAbrir('/fisio', 'Painel da fisioterapia', '[data-contract="fisio-painel-tabela"]');

    $html = fcbHtml($page);
    expect(str_contains($html, FCB_NOME))->toBeTrue();
    expect(str_contains($html, FCB_NOME_ALHEIO))->toBeFalse();
    $page->assertNoAccessibilityIssues(level: 0);
});

it('UC-FPI-02 · Fisio/Pacientes/Index traz o paciente da 98, não o da 99, sem violação axe CRITICAL', function () {
    $page = fcbAbrir('/fisio/pacientes', 'Pacientes', '[data-contract="fisio-pacientes-lista"]');

    $html = fcbHtml($page);
    expect(str_contains($html, FCB_NOME))->toBeTrue();
    expect(str_contains($html, FCB_NOME_ALHEIO))->toBeFalse();
    $page->assertNoAccessibilityIssues(level: 0);
});

it('UC-FAG-02 · Fisio/Agenda/Index mostra a sessão na semana com o nome curto e sem o recado do paciente', function () {
    $sessao = collect(app(AgendaDaSemana::class)->tipos(FCB_BIZ))->firstWhere('nome', 'Sessão');
    expect($sessao)->not->toBeNull();

    $segunda = Carbon::now()->startOfWeek();
    $inicio = $segunda->copy()->addDays(2)->setTime(10, 0);
    // SUPERADMIN: fixture do tenant 98 criada fora de request (sem sessão para o escopo).
    Appointment::withoutGlobalScopes()->create([
        'business_id' => FCB_BIZ, 'patient_id' => $this->fcbPropria->id, 'professional_id' => FCB_ADMIN,
        'type_id' => $sessao['id'], 'starts_at' => $inicio, 'ends_at' => $inicio->copy()->addMinutes($sessao['duracao']),
        'status' => 'agendado', 'patient_note' => FCB_RECADO, 'created_by' => FCB_ADMIN,
    ]);

    $rota = '/fisio/agenda?semana='.$segunda->toDateString().'&profissional='.FCB_ADMIN;
    $page = fcbAbrir($rota, 'Agenda', 'aside[aria-label="Lista de espera"]');

    $curto = AgendaDaSemana::curto(FCB_NOME);
    $html = fcbHtml($page);
    expect(str_contains($html, $curto))->toBeTrue("A semana não mostrou '{$curto}'.");
    expect(str_contains($html, FCB_RECADO))->toBeFalse();
    $page->assertNoAccessibilityIssues(level: 0);
});
