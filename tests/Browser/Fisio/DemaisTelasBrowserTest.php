<?php

declare(strict_types=1);

/**
 * Pest 4 Browser — render + axe das demais telas da clínica do Fisio (lote 9, fase 3):
 *   Fisio/Onboarding · Fisio/Biblioteca/Index · Fisio/Protocolos/Index ·
 *   Fisio/Configuracoes/Agendamento · Fisio/Configuracoes/Whatsapp · Fisio/Fiscal/Index ·
 *   Fisio/Pesquisa/Index · Fisio/Relatorio/Associacao · Fisio/Pacientes/Show · Fisio/Pacientes/Pacote
 *
 * ── O BURACO QUE ISTO FECHA ───────────────────────────────────────────────────
 * Auditoria do Fisio (2026-11-05), item d18 / lote 9: nenhuma tela do Fisio abria num browser
 * nem passava por axe. Portal, Painel, Pacientes e Agenda estão no PortalBrowserTest e no
 * ClinicaTelasBrowserTest; este arquivo cobre as outras dez. Os Feature do módulo provam o
 * payload; aqui a prova é a tela montar com as props reais e o que o axe mede no DOM.
 *
 * ── O QUE PROVA, POR TELA ─────────────────────────────────────────────────────
 *   - a tela abre para o admin do tenant 98 com o pacote (o h1 traz o título, sem 403 nem
 *     /login) e os esqueletos dos `<Deferred>` somem; cada caso cita o UC do `.casos.md` dela;
 *   - 0 violações axe-core CRITICAL (level 0, o piso do A11yAxeBrowserTest).
 * Além do render:
 *   - UC-FRA-04: o relatório da associação não traz o nome do paciente.
 *   - UC-FPS-01: a ficha de um paciente da 99, aberta pelo admin da 98, não traz o nome dele.
 *
 * ── DADO ──────────────────────────────────────────────────────────────────────
 * Tenant 98 contra 99 (ADR 0358; nunca biz=4 nem biz=1). Admin 98 com role `Admin#98` (o
 * Gate::before libera fisio.access, fisio.editar, fisio.pesquisa e fisio.relatorio). Assinatura
 * `fisio_module` criada e apagada aqui (id próprio, não colide com os irmãos). Um paciente em
 * cada tenant, marcados por `FDT_QUEIXA` e apagados pelo id. Sem pixel (ADR 0445).
 *
 * ── ONDE RODA ─────────────────────────────────────────────────────────────────
 * CI apenas (memory/proibicoes.md §Ambiente): step "E2E de comportamento + axe · Fisio/Demais
 * telas (ADVISORY)" do `visual-regression.yml`. Não foi executado antes do PR.
 */

use Illuminate\Support\Facades\DB;
use Modules\Fisio\Entities\Patient;
use Modules\Fisio\Tests\Support\FisioFixtures as F;

const FDT_BIZ = 98;
const FDT_ADMIN = 98;
const FDT_ADVERSARIO = 99;
const FDT_USER_ADVERSARIO = 99;
const FDT_ASSINATURA = 900092;
const FDT_QUEIXA = 'Queixa E2E demais FDT';
const FDT_NOME = 'Fisiodemais Propria Lima';
const FDT_NOME_ALHEIO = 'Fisiodemais Alheia Costa';

beforeEach(function () {
    config(['database.default' => 'mysql', 'database.connections.mysql.database' => 'oimpresso_test']);
    DB::purge('mysql');

    // Falha alto: tenant ausente não pode virar verde por pulo.
    if (! DB::table('users')->where('id', FDT_ADMIN)->where('business_id', FDT_BIZ)->exists()) {
        throw new RuntimeException('Admin do tenant 98 ausente: o VisregEmptyTenantSeeder não rodou.');
    }
    if (! DB::table('users')->where('id', FDT_USER_ADVERSARIO)->where('business_id', FDT_ADVERSARIO)->exists()) {
        throw new RuntimeException('Usuário do business 99 ausente: o VisregTenantBLeakSeeder não rodou.');
    }

    fdtLimpar();
    DB::table('subscriptions')->insert([
        'id' => FDT_ASSINATURA,
        'business_id' => FDT_BIZ,
        'package_id' => 900001, // pacote do VisregTenantSeeder; o que vale aqui é o package_details
        'start_date' => '2026-01-01',
        'end_date' => '2099-12-31',
        'package_price' => 0,
        'package_details' => json_encode(['name' => 'Fixture Fisio demais telas', F::PACOTE => '1']),
        'created_id' => FDT_ADMIN,
        'status' => 'approved',
    ]);

    $this->fdtPropria = fdtPaciente(FDT_BIZ, FDT_ADMIN, FDT_NOME);
    $this->fdtAlheia = fdtPaciente(FDT_ADVERSARIO, FDT_USER_ADVERSARIO, FDT_NOME_ALHEIO);
});

afterEach(function () {
    fdtLimpar();
});

function fdtPaciente(int $biz, int $criador, string $nome): Patient
{
    $contato = (int) DB::table('contacts')->insertGetId([
        'business_id' => $biz, 'type' => 'customer', 'name' => $nome, 'mobile' => '48999990000',
        'contact_status' => 'active', 'created_by' => $criador, 'created_at' => now(), 'updated_at' => now(),
    ]);

    return F::paciente($biz, $criador, ['contact_id' => $contato, 'main_complaint' => FDT_QUEIXA]);
}

/** Apaga a assinatura e os pacientes marcados por FDT_QUEIXA nos dois tenants. */
function fdtLimpar(): void
{
    DB::table('subscriptions')->where('id', FDT_ASSINATURA)->delete();

    // SUPERADMIN: limpeza das fixtures dos tenants 98 e 99, fora de request.
    $pacientes = Patient::withoutGlobalScopes()->whereIn('business_id', [FDT_BIZ, FDT_ADVERSARIO])
        ->where('main_complaint', FDT_QUEIXA)->get(['id', 'business_id', 'contact_id']);
    foreach ($pacientes as $p) {
        DB::table('fisio_patients')->where('business_id', $p->business_id)->where('id', $p->id)->delete();
        DB::table('contacts')->where('business_id', $p->business_id)->where('id', $p->contact_id)->delete();
    }
}

/**
 * Loga como o admin 98, abre a rota e espera: o seletor existir, o h1 conter o título (quando
 * dado) e os esqueletos dos `<Deferred>` sumirem. Falha dizendo o último estado lido.
 */
function fdtAbrir(string $rota, ?string $titulo, string $seletor = 'h1')
{
    $page = visit('/_visreg-login/'.FDT_ADMIN.'?to='.urlencode($rota))->resize(1280, 800);

    $t = json_encode($titulo ?? '');
    $s = json_encode($seletor);
    $js = "(() => { const txt = (document.body ? document.body.innerText : '');"
        ." if (/unauthorized action|sem acesso/i.test(txt.slice(0, 300))) return 'ERRO-403 ' + txt.slice(0, 120);"
        ." if (!document.querySelector({$s})) return 'SEM ' + {$s} + ' em ' + location.pathname;"
        ." const h1 = (document.querySelector('h1')?.textContent ?? '').trim();"
        ." if ({$t} && !h1.includes({$t})) return 'H1 ' + h1;"
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

function fdtHtml($page): string
{
    return (string) $page->script('document.documentElement.outerHTML');
}

// [rota ({p} = id do paciente da 98), trecho do h1 (null = h1 varia por passo), seletor]
dataset('telas_fisio_demais', [
    'UC-FOB-01 · Fisio/Onboarding' => ['/fisio/onboarding', null, '[data-contract="fisio-onboarding-passo"]'],
    'UC-FBI-01 · Fisio/Biblioteca/Index' => ['/fisio/biblioteca', 'Biblioteca de exercícios', 'h1'],
    'UC-FPR-01 · Fisio/Protocolos/Index' => ['/fisio/protocolos', 'Protocolos-modelo', 'h1'],
    'UC-FCA-01 · Fisio/Configuracoes/Agendamento' => ['/fisio/configuracoes/agendamento', 'Agendamento pelo app', 'h1'],
    'UC-FCW-01 · Fisio/Configuracoes/Whatsapp' => ['/fisio/configuracoes/whatsapp', 'WhatsApp', 'h1'],
    'UC-FFI-01 · Fisio/Fiscal/Index' => ['/fisio/fiscal', 'Documentos fiscais do dia', 'h1'],
    'UC-FES-07 · Fisio/Pesquisa/Index' => ['/fisio/pesquisa', 'Pesquisa', 'h1'],
    'UC-FPS-02 · Fisio/Pacientes/Show' => ['/fisio/pacientes/{p}', FDT_NOME, 'h1'],
    'UC-FPK-01 · Fisio/Pacientes/Pacote' => ['/fisio/pacientes/{p}/pacote', 'Pacote e cobrança', 'h1'],
]);

it('a tela abre para o admin com o pacote e tem 0 violações axe CRITICAL', function (string $rota, ?string $titulo, string $seletor) {
    $rota = str_replace('{p}', (string) $this->fdtPropria->id, $rota);
    $page = fdtAbrir($rota, $titulo, $seletor);

    expect((string) $page->script('window.location.pathname'))->toBe($rota);
    $page->assertNoAccessibilityIssues(level: 0);
})->with('telas_fisio_demais');

it('UC-FRA-04 · Fisio/Relatorio/Associacao abre sem nenhum nome de paciente, sem violação axe CRITICAL', function () {
    $page = fdtAbrir('/fisio/relatorio-associacao', 'Relatório da associação');

    $html = fdtHtml($page);
    expect(str_contains($html, FDT_NOME))->toBeFalse();
    expect(str_contains($html, FDT_NOME_ALHEIO))->toBeFalse();
    $page->assertNoAccessibilityIssues(level: 0);
});

it('UC-FPS-01 · a ficha de um paciente da 99, aberta pela 98, não traz o nome dele', function () {
    // Controle: a ficha da 98 traz o nome (o teste mede o vazamento, não uma tela que nunca monta).
    $propria = fdtAbrir('/fisio/pacientes/'.$this->fdtPropria->id, FDT_NOME);
    expect(str_contains(fdtHtml($propria), FDT_NOME))->toBeTrue();

    // Mesma aba, mesma sessão: se caísse no /login, o "não traz o nome" passaria vazio.
    $rota = '/fisio/pacientes/'.$this->fdtAlheia->id;
    $propria->navigate($rota);
    $propria->wait(1);
    expect((string) $propria->script('window.location.pathname'))->toBe($rota);
    expect(str_contains(fdtHtml($propria), FDT_NOME_ALHEIO))->toBeFalse();
});
