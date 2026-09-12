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

it('UC-RDSH-02 · o KPI de pendentes conta ORDENS DE SERVIÇO, e se mexe quando entra folha', function () {
    $biz = $this->seededTenant();
    $user = rdshUser((int) $biz->id);

    // ⚠️ Este UC AFIRMAVA o contrário até 2026-09-09: cravava que o KPI era o TAMANHO da
    // lista de status (`count($job_sheets_by_status)`) e que +3 OS não o moviam. Aquilo era
    // contrato honesto do defeito, não do produto: com 6 status configurados o número ficava
    // preso em ~6 com 3 ou 3.000 OS. O protótipo (`repair-page.jsx` região `Painel`) manda
    // "Folhas pendentes" no topo, e no eixo FORMA ele é soberano (ADR UI-0029) — então o
    // perdedor a corrigir no MESMO PR era ESTE teste, reescrito, nunca desabilitado
    // (proibicoes.md §Precedência). Ver RUNBOOK-repair-dashboard.md.
    $antes = rdshAbrirPainel($this, $user);

    $statusPendente = DB::table('repair_statuses')
        ->where('business_id', $biz->id)
        ->where(function ($q) {
            $q->where('is_completed_status', 0)->orWhereNull('is_completed_status');
        })
        ->value('id');

    if (! $statusPendente || rdshCriarOs((int) $biz->id, 'KPI-1', (int) $statusPendente) === null) {
        test()->markTestSkipped('Tenant 98 sem contact/status pendente/location/user — sem como criar OS de fixture.');
    }
    rdshCriarOs((int) $biz->id, 'KPI-2', (int) $statusPendente);
    rdshCriarOs((int) $biz->id, 'KPI-3', (int) $statusPendente);

    $depois = rdshAbrirPainel($this, $user);

    // A prova: 3 folhas pendentes a mais movem o KPI em 3. `>=` porque outra sessão pode
    // escrever no mesmo tenant entre as duas leituras (o banco do CT 100 persiste).
    expect((int) $depois['kpis']['pending'] - (int) $antes['kpis']['pending'])
        ->toBeGreaterThanOrEqual(3);

    // CONTROLE NEGATIVO do defeito antigo: o número de status distintos NÃO mudou (criei
    // as 3 no MESMO status). Se o KPI ainda fosse `count($job_sheets_by_status)`, o delta
    // acima seria 0 e este teste reprovaria — é o que o torna capaz de ficar vermelho.
    expect(count($depois['job_sheets_by_status'] ?? []))
        ->toBe(count($antes['job_sheets_by_status'] ?? []));

    // As 4 chaves do contrato novo existem e são inteiros — sem isso a tela renderiza
    // `undefined` e ninguém vê erro.
    foreach (['pending', 'pending_unassigned', 'completed', 'overdue'] as $chave) {
        expect($depois['kpis'])->toHaveKey($chave);
        expect($depois['kpis'][$chave])->toBeInt();
    }

    // Coerência interna: quem está sem técnico é subconjunto de quem está pendente, e quem
    // venceu também. Um agregado que violasse isso estaria contando universo errado.
    expect((int) $depois['kpis']['pending_unassigned'])->toBeLessThanOrEqual((int) $depois['kpis']['pending']);
    expect((int) $depois['kpis']['overdue'])->toBeLessThanOrEqual((int) $depois['kpis']['pending']);
});

it('UC-RDSH-03 · Top aparelhos entrega a consulta que o Controller já roda', function () {
    $biz = $this->seededTenant();
    $user = rdshUser((int) $biz->id);

    // ⚠️ Este UC AFIRMAVA `toBe([])` até 2026-09-09 — o Controller chamava
    // `RepairUtil::getTrendingDevices()` na linha 42 e mandava um `[]` LITERAL pra tela.
    // O card, o Deferred, o skeleton e o emptyMsg existiam: tudo funcionava, e nunca
    // mostrava nada. Corrigido no mesmo PR que reescreve este teste.
    $deviceId = DB::table('categories')->where('business_id', $biz->id)->value('id');

    if (rdshCriarOs((int) $biz->id, 'DEVICE', null, $deviceId === null ? null : (int) $deviceId) === null) {
        test()->markTestSkipped('Tenant 98 sem contact/status/location/user — sem como criar OS de fixture.');
    }

    $props = rdshAbrirPainel($this, $user);

    // O contrato agora é de FORMA, e vale com ou sem taxonomia: um array de linhas
    // {device, count}, nunca mais um literal vazio plantado no Controller.
    expect($props)->toHaveKey('trending_devices_chart');
    expect($props['trending_devices_chart'])->toBeArray();

    // ANTI-VÁCUO: a OS existe e os outros agregados a enxergam.
    expect(collect($props['job_sheets_by_status'] ?? [])->sum('count'))->toBeGreaterThan(0);

    // Perna FORTE — só onde a taxonomia existir. Medido no CT 100 em 2026-09-05:
    // `categories` está VAZIA no staging (0 linhas no banco inteiro), e quem semeia
    // ambiente é o seed, não o teste (§5 2026-08-24). Com device_id preenchido, a OS
    // criada acima TEM de aparecer — é exatamente o que o `[]` literal impedia.
    if ($deviceId !== null) {
        expect(collect($props['trending_devices_chart'])->sum('count'))->toBeGreaterThan(0);
        expect(collect($props['trending_devices_chart'])->first())->toHaveKeys(['label', 'count']);
    }
});

it('UC-RDSH-05 · toda série de gráfico chega com a chave que a tela lê', function () {
    $biz = $this->seededTenant();
    $user = rdshUser((int) $biz->id);

    if (rdshCriarOs((int) $biz->id, 'LABEL') === null) {
        test()->markTestSkipped('Tenant 98 sem contact/status/location/user — sem como criar OS de fixture.');
    }

    $props = rdshAbrirPainel($this, $user);

    // O consumidor é o `BarChartCard` do Index.tsx, e ele lê `r.label`. Até 2026-09-09 o
    // Controller mandava `status`/`staff`/`brand`/`model`: os 4 gráficos renderizavam o
    // rótulo VAZIO em produção, e o `.tsx:17` afirmava que "toda série é normalizada pelo
    // Controller pra {label,count}" — comentario certo sobre a intenção, errado sobre o
    // fato. Nenhum teste pegava porque todos somavam `count`, que era a metade que batia.
    // Este UC trava a OUTRA metade.
    foreach ([
        'job_sheets_by_status',
        'job_sheets_by_service_staff',
        'trending_brand_chart',
        'trending_dm_chart',
        'trending_devices_chart',
    ] as $serie) {
        expect($props)->toHaveKey($serie);
        foreach ($props[$serie] ?? [] as $linha) {
            expect($linha)->toHaveKeys(['label', 'count']);
            // `label` é o que aparece na barra: vazio ali é o defeito, não o dado.
            expect($linha['label'])->not->toBeNull();
            expect((string) $linha['label'])->not->toBe('');
        }
    }

    // ANTI-VÁCUO: pelo menos uma série tem linha — senão o foreach acima passa por
    // vacuidade e o UC vira carimbo.
    expect(collect($props['job_sheets_by_status'] ?? [])->count())->toBeGreaterThan(0);
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

    // E os KPIs tambem. Ate 2026-09-09 esta perna comparava o KPI contra os STATUS
    // distintos do tenant, porque era isso que o Controller mandava; com o KPI passando a
    // contar ORDENS (ver UC-RDSH-02), a mesma pergunta se faz melhor: pendentes +
    // concluidas TEM de fechar com a contagem escopada por business_id. Se a agregada
    // perdesse o escopo, as 2 OS do vizinho apareceriam aqui.
    //
    // ⚠️ `leftJoin`, nao `join`: o Controller conta a folha mesmo com status apagado no
    // legado (ela cai em pendente, o lado seguro). Um `join` interno aqui mediria um
    // universo menor que o da tela e fabricaria vermelho onde nao ha defeito.
    $osDoTenant = (int) DB::table('repair_job_sheets')
        ->where('business_id', $biz->id)
        ->count();

    expect((int) $props['kpis']['pending'] + (int) $props['kpis']['completed'])
        ->toBe($osDoTenant);
});
