<?php

declare(strict_types=1);

use App\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Tests\Support\WithSeededTenant;

/**
 * Contrato executável do Painel do Repair — `/repair/dashboard`.
 *
 * Cada `it()` cita no TÍTULO o UC que defende — é o título que o manifesto G-7 lê.
 *
 * ⚠️ O charter desta tela promete OITO Pest GUARD num arquivo que não existe
 * (`Modules/Repair/Tests/Charters/RepairDashboardCharterTest.php` — medido em
 * 2026-09-05: nem o arquivo nem o diretório; `git grep` do nome devolve só o próprio
 * charter). Estes quatro UCs pagam quatro deles; os outros quatro estão nomeados como
 * `[BACKLOG]` no casos.md, sem fingir cobertura.
 *
 * ⚠️ ESTRATÉGIA DE ASSERÇÃO — por que nada aqui compara contagem absoluta nem delta
 * global. O banco do CT 100 é COMPARTILHADO e escrito por outras sessões enquanto a
 * suíte roda: medido em 2026-09-05, a contagem de `repair_job_sheets` do tenant 98 foi
 * de 58 para 62 entre dois runs meus, sem nenhuma linha minha sobreviver ao `afterEach`.
 * Um teste que afirmasse "o total não mudou" mediria o tráfego dos vizinhos, não a tela
 * — verde ou vermelho por sorte. Então:
 *   · "não escreve" vira: NENHUM `insert/update/delete` nas tabelas do domínio durante
 *     a requisição (via `DB::listen`) — determinístico e imune a concorrência;
 *   · "não vaza tenant" vira: o número do painel é IGUAL à consulta escopada por
 *     `business_id`, comparação exata contra a verdade do tenant;
 *   · o delta de fixture é `>= 3`, não `== 3`.
 *
 * Tenant: `seededTenant()` = 98; o vizinho de isolamento é o 99
 * (`seededSupportClientTenant`). biz=4 é o cliente real e é PROIBIDO (ADR 0358).
 *
 * @see resources/js/Pages/Repair/Dashboard/Index.casos.md
 * @see resources/js/Pages/Repair/Dashboard/Index.charter.md
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 */
uses(Tests\TestCase::class, WithSeededTenant::class);

const RDSH_MARCA = 'TEST-CONTRATO-RDSH-';

/** Tabelas de domínio que o painel NUNCA pode escrever (o resto é encanamento do framework). */
const RDSH_TABELAS_DOMINIO = ['repair_job_sheets', 'transactions', 'repair_statuses', 'contacts'];

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: o schema UltimatePOS exige MySQL (ADR 0358)');
    }
    foreach (['business', 'users', 'repair_job_sheets', 'repair_statuses'] as $t) {
        if (! Schema::hasTable($t)) {
            $this->markTestSkipped("Schema incompleto — tabela {$t} ausente; rode migrate + seed mínimo");
        }
    }
});

afterEach(function () {
    try {
        DB::table('repair_job_sheets')->where('job_sheet_no', 'like', RDSH_MARCA.'%')->delete();
    } catch (\Throwable $e) {
        // ambiente sem a tabela — nada a limpar
    }
    config(['mwart.repair_dashboard_index.enabled' => false, 'mwart.repair_dashboard_index.business_ids' => []]);
});

/** Versão perguntada AO middleware — literal ou regra reimplementada dá 409 (ver RepairShowContratoTest). */
function rdshInertiaVersion(): string
{
    return (string) app(\App\Http\Middleware\HandleInertiaRequests::class)->version(request());
}

function rdshUser(int $businessId): User
{
    $user = User::factory()->create([
        'business_id' => $businessId,
        'username' => 'rdsh_'.uniqid(),
    ]);

    foreach (['access_all_locations', 'repair.view'] as $name) {
        $user->givePermissionTo(Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']));
    }

    return $user;
}

/**
 * Insere uma OS no tenant. Devolve `null` (em vez de fabricar) quando falta pré-condição
 * de seed — `contact_id`, `status_id` e `created_by` são FK, e plantar dado global só
 * para o teste passar é semear o ambiente pelas costas do seed (§5 2026-08-24).
 *
 * `created_by` NÃO pode ser omitido: a coluna tem FK para `users.id` e default 0, então
 * o insert sem ela morre com 1452 (medido no CT 100, 2026-09-05).
 */
function rdshCriarOs(int $businessId, string $sufixo, ?int $statusId = null, ?int $deviceId = null): ?int
{
    $locId = DB::table('business_locations')->where('business_id', $businessId)->value('id');
    $contactId = DB::table('contacts')->where('business_id', $businessId)->value('id');
    $statusId = $statusId ?? DB::table('repair_statuses')->where('business_id', $businessId)->value('id');
    $autorId = DB::table('users')->where('business_id', $businessId)->value('id')
        ?? DB::table('users')->orderBy('id')->value('id');

    if (! $locId || ! $contactId || ! $statusId || ! $autorId) {
        return null;
    }

    return (int) DB::table('repair_job_sheets')->insertGetId([
        'business_id' => $businessId,
        'location_id' => $locId,
        'contact_id' => $contactId,
        'job_sheet_no' => RDSH_MARCA.$sufixo,
        'service_type' => 'carry_in',
        'serial_no' => 'SN-'.$sufixo,
        'status_id' => $statusId,
        'device_id' => $deviceId,
        'created_by' => $autorId,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

/**
 * GET do painel em modo Inertia, flag ligada. Devolve as props já como array.
 *
 * Quando `$escritas` é passado por referência, coleta todo SQL de escrita nas tabelas de
 * domínio emitido DURANTE a requisição — é assim que UC-RDSH-01 prova "read-only" sem
 * depender de contagem num banco que os vizinhos também escrevem.
 */
function rdshAbrirPainel($test, User $user, ?array &$escritas = null): array
{
    config(['mwart.repair_dashboard_index.enabled' => true, 'mwart.repair_dashboard_index.business_ids' => []]);
    session(['user.business_id' => $user->business_id, 'user.id' => $user->id, 'business.id' => $user->business_id]);

    if ($escritas !== null) {
        $capturadas = [];
        DB::listen(function ($q) use (&$capturadas) {
            if (! preg_match('/^\s*(insert|update|delete)\b/i', $q->sql)) {
                return;
            }
            foreach (RDSH_TABELAS_DOMINIO as $tabela) {
                if (stripos($q->sql, $tabela) !== false) {
                    $capturadas[] = $q->sql;

                    return;
                }
            }
        });
    }

    $resp = $test->actingAs($user)
        ->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => rdshInertiaVersion()])
        ->get('/repair/dashboard');

    if ($escritas !== null) {
        $escritas = $capturadas;
    }

    if ($resp->status() === 403) {
        test()->markTestSkipped('Gate de assinatura/módulo barra repair_module neste ambiente.');
    }

    // JSON, e NÃO `assertInertia`: com `X-Inertia: true` a resposta é JSON, e
    // `AssertableInertia::fromTestResponse` começa por `assertViewHas('page')` — espera a
    // casca HTML. Header + assertInertia falha com "Not a valid Inertia response" mesmo
    // quando a resposta É Inertia válida (medido no CT 100, 2026-09-05).
    $resp->assertOk();
    expect($resp->json('component'))->toBe('Repair/Dashboard/Index');

    return $resp->json('props') ?? [];
}

it('UC-RDSH-01 · abrir o painel não escreve nada e não enfileira nada', function () {
    $biz = $this->seededTenant();
    $user = rdshUser((int) $biz->id);

    Queue::fake();

    $escritas = [];
    rdshAbrirPainel($this, $user, $escritas);

    // nenhum insert/update/delete nas tabelas do domínio durante a requisição
    expect($escritas)->toBe([]);
    Queue::assertNothingPushed();
});

it('UC-RDSH-02 · o primeiro KPI conta STATUS distintos, não ordens de serviço', function () {
    $biz = $this->seededTenant();
    $user = rdshUser((int) $biz->id);

    // invariante que vale em qualquer estado de base: o KPI É o tamanho da lista de status
    $props = rdshAbrirPainel($this, $user);
    expect((int) $props['kpis']['total_repairs'])
        ->toBe(count($props['job_sheets_by_status'] ?? []));

    // prova direta: +3 OS no MESMO status não pode mexer no KPI
    $statusId = DB::table('repair_statuses')->where('business_id', $biz->id)->value('id');
    if (! $statusId || rdshCriarOs((int) $biz->id, 'KPI-1', (int) $statusId) === null) {
        test()->markTestSkipped('Tenant 98 sem contact/status/location/user — sem como criar OS de fixture.');
    }
    rdshCriarOs((int) $biz->id, 'KPI-2', (int) $statusId);
    rdshCriarOs((int) $biz->id, 'KPI-3', (int) $statusId);

    $depois = rdshAbrirPainel($this, $user);

    expect((int) $depois['kpis']['total_repairs'])
        ->toBe((int) $props['kpis']['total_repairs']);

    // controle positivo: as 3 OS entraram — senão o "não mudou" seria vácuo. `>=` porque
    // outra sessão pode ter escrito no mesmo tenant entre as duas leituras.
    $somaAntes = collect($props['job_sheets_by_status'] ?? [])->sum('count');
    $somaDepois = collect($depois['job_sheets_by_status'] ?? [])->sum('count');
    expect($somaDepois - $somaAntes)->toBeGreaterThanOrEqual(3);
});

it('UC-RDSH-03 · o painel Top aparelhos nunca enche — o Controller descarta a consulta que já rodou', function () {
    $biz = $this->seededTenant();
    $user = rdshUser((int) $biz->id);

    // O aparelho da OS é uma linha de `categories`. Medido no CT 100 em 2026-09-05: a
    // tabela está VAZIA no staging (0 linhas no banco inteiro, não só no tenant 98) — a
    // taxonomia de device não é semeada. Quem semeia ambiente é o seed, não o teste
    // (§5 2026-08-24), então a perna forte fica condicional em vez de fabricada.
    $deviceId = DB::table('categories')->where('business_id', $biz->id)->value('id');

    if (rdshCriarOs((int) $biz->id, 'DEVICE', null, $deviceId === null ? null : (int) $deviceId) === null) {
        test()->markTestSkipped('Tenant 98 sem contact/status/location/user — sem como criar OS de fixture.');
    }

    $props = rdshAbrirPainel($this, $user);

    // O contrato vigente do servidor, e ele vale com ou sem taxonomia: o Controller manda
    // um `[]` LITERAL, não o resultado da consulta que ele mesmo roda uma linha acima.
    expect($props['trending_devices_chart'] ?? null)->toBe([]);

    // ANTI-VÁCUO parcial: a OS existe e os OUTROS agregados a enxergam — o painel está
    // servindo dado do tenant, e ainda assim "Top aparelhos" volta vazio.
    expect(collect($props['job_sheets_by_status'] ?? [])->sum('count'))->toBeGreaterThan(0);

    // Perna FORTE — só onde a taxonomia existir: com `device_id` preenchido, o ramo Blade
    // encheria o gráfico via `RepairUtil::getTrendingDevices`, e o Inertia continua vazio.
    // Sem ela, o `[]` acima é o contrato pinado, não a prova do descarte.
    if ($deviceId !== null) {
        expect(collect($props['trending_devices_chart'] ?? [])->count())->toBe(0);
    }
});

it('UC-RDSH-04 · nenhum dos agregados enxerga OS de outro tenant', function () {
    $biz = $this->seededTenant();
    $vizinho = $this->seededSupportClientTenant();
    $user = rdshUser((int) $biz->id);

    if (rdshCriarOs((int) $vizinho->id, 'VIZINHO-1') === null) {
        test()->markTestSkipped('Tenant 99 sem contact/status/location/user — sem como criar OS vizinha.');
    }
    rdshCriarOs((int) $vizinho->id, 'VIZINHO-2');

    // ANTI-VÁCUO: as OS vizinhas existem mesmo — o isolamento tem que custar algo
    expect(DB::table('repair_job_sheets')->where('business_id', $vizinho->id)
        ->where('job_sheet_no', 'like', RDSH_MARCA.'VIZINHO-%')->count())->toBe(2);

    $props = rdshAbrirPainel($this, $user);

    // comparação EXATA contra a verdade escopada por business_id — não é antes→depois,
    // então uma escrita concorrente de outra sessão não fabrica falso vermelho.
    $verdadeDoTenant = (int) DB::table('repair_job_sheets')
        ->join('repair_statuses as rs', 'repair_job_sheets.status_id', '=', 'rs.id')
        ->where('repair_job_sheets.business_id', $biz->id)
        ->count('repair_job_sheets.id');

    expect(collect($props['job_sheets_by_status'] ?? [])->sum('count'))->toBe($verdadeDoTenant);

    // e o KPI de status também: nenhum status que só existe no vizinho entra na conta
    $statusDoTenant = (int) DB::table('repair_job_sheets')
        ->join('repair_statuses as rs', 'repair_job_sheets.status_id', '=', 'rs.id')
        ->where('repair_job_sheets.business_id', $biz->id)
        ->distinct()->count('rs.id');

    expect((int) $props['kpis']['total_repairs'])->toBe($statusDoTenant);
});
