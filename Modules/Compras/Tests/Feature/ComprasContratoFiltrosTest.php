<?php

declare(strict_types=1);

use App\Business;
use App\Transaction;
use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Compras\Services\ComprasService;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Contrato do cockpit /compras — filtros, ordenação e escopo de localização.
 *
 * FAILING-FIRST por desenho (padrão #4300): os 3 UCs abaixo derivam do SDD
 * §6 (CU-COM-04 e CU-COM-05) e do que a Blade legada faz — NÃO do Index.tsx.
 * Se ficarem vermelhos, o vermelho É o achado; a correção é decisão do [W],
 * nunca conserto silencioso (proibicoes.md §Precedência).
 *
 * ⚠️ NOTA SOBRE A CATRACA DA LANE: o cabeçalho do compras-pest.yml declara
 * "ALLOWLIST VERDE — roda só os arquivos comprovadamente VERDES". Este arquivo
 * entra na allowlist SEM ser verde, de propósito: teste que não está listado
 * NÃO RODA (é o "verde impossível" que o anchor-lint denuncia). A lane é
 * ADVISORY (não está em governance/required-checks-baseline.json), então o
 * vermelho é VISÍVEL e NÃO bloqueia merge. Se [W] preferir manter a catraca
 * "só-verde", a decisão é dele — está reportada no session log do chip S1.
 *
 * ANTI-VÁCUO (lição proibicoes.md §5 2026-07-24): cada UC roda uma
 * pré-condição que prova que a requisição BASE funciona (200 sem filtro / a
 * compra de controle aparece). Sem isso, um verde poderia significar
 * "nada foi exercitado" em vez de "o contrato vale".
 *
 * @see memory/requisitos/Compras/SDD-tela-cockpit-compras-v1.0.md §5.4.1 · §5.4.4 · §6.1
 * @see resources/js/Pages/Compras/Index.casos.md — UC-CMP-06 · UC-CMP-07 · UC-CMP-08
 * @see memory/requisitos/Compras/SPEC.md — US-COM-008 (whitelist) · R-COM-004 (filtros)
 * @covers-uc UC-CMP-06
 * @covers-uc UC-CMP-07
 * @covers-uc UC-CMP-08
 */

// `Tests\TestCase::class` é OBRIGATÓRIO: Modules/Compras/Tests NÃO está registrado
// no `uses(TestCase::class)->in(...)` do tests/Pest.php. Sem ele o Laravel não boota.
// Espelha MultiTenantTest / ComprasListagemNPlusUmTest (verdes na lane).
uses(Tests\TestCase::class, DatabaseTransactions::class);

beforeEach(function () {
    try {
        // biz=1 (Wagner WR2 SC). ADR 0101 — NUNCA biz=4 (Larissa, cliente real).
        $this->biz = Business::find(1);
        if (! $this->biz) {
            $this->biz = Business::forceCreate([
                'id' => 1,
                'name' => 'Test Biz Primary (auto)',
                'currency_id' => 1,
                'start_date' => Carbon::now()->toDateString(),
                'default_profit_percent' => 0,
                'owner_id' => 1,
                // NOT NULL sem default no schema real (business) — espelha o seed
                // .github/actions/pest-mysql-setup.
                'stop_selling_before' => 0,
                'weighing_scale_setting' => '',
                'certificado' => '',
                'officeimpresso_numerodemaquinas' => 0,
            ]);
        }
    } catch (\Throwable $e) {
        $this->markTestSkipped(
            'Schema UltimatePOS ausente (business/transactions/etc): '.$e->getMessage()
            .' — a lane compras-pest roda em MySQL real semeado.'
        );
    }

    $permView = Permission::firstOrCreate(['name' => 'compras.view', 'guard_name' => 'web']);
    $permCreate = Permission::firstOrCreate(['name' => 'purchase.create', 'guard_name' => 'web']);

    // `roles.business_id` é NOT NULL + FK pra business (proibicoes.md §FSM).
    $role = Role::firstOrCreate(
        ['name' => 'compras-contrato-test#1', 'guard_name' => 'web'],
        ['business_id' => $this->biz->id]
    );
    $role->givePermissionTo([$permView, $permCreate]);

    // user_type='user' + allow_login=1: sem eles o middleware CheckUserLogin aborta 403.
    $this->user = User::factory()->create([
        'business_id' => $this->biz->id,
        'username' => 'compras_contrato_'.uniqid(),
        'user_type' => 'user',
        'allow_login' => 1,
    ]);
    $this->user->assignRole($role);

    // business_locations tem FK NOT NULL invoice_scheme_id + invoice_layout_id.
    // Reusa os existentes; cria mínimo se a tabela estiver vazia.
    $schemeId = DB::table('invoice_schemes')->value('id');
    if (! $schemeId) {
        $schemeId = DB::table('invoice_schemes')->insertGetId([
            'business_id' => $this->biz->id,
            'name' => 'CI Scheme Contrato',
            'scheme_type' => 'blank',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
    $layoutId = DB::table('invoice_layouts')->value('id');
    if (! $layoutId) {
        $layoutId = DB::table('invoice_layouts')->insertGetId([
            'business_id' => $this->biz->id,
            'name' => 'CI Layout Contrato',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    $locationCols = [
        'country' => 'BR',
        'state' => 'SC',
        'city' => 'Test City',
        'zip_code' => '0000000',
        'invoice_scheme_id' => $schemeId,
        'invoice_layout_id' => $layoutId,
        'is_active' => 1,
    ];

    // DUAS localizações do MESMO business — é o eixo do UC-CMP-08 (escopo
    // intra-tenant por local, não cross-tenant).
    $this->locA = DB::table('business_locations')
        ->where('business_id', $this->biz->id)
        ->first();

    if (! $this->locA) {
        $idA = DB::table('business_locations')->insertGetId(array_merge($locationCols, [
            'business_id' => $this->biz->id,
            'name' => 'Loja A (contrato)',
            'location_id' => 'CTRA'.substr((string) time(), -4),
            'created_at' => now(),
            'updated_at' => now(),
        ]));
        $this->locA = DB::table('business_locations')->find($idA);
    }

    $idB = DB::table('business_locations')->insertGetId(array_merge($locationCols, [
        'business_id' => $this->biz->id,
        'name' => 'Loja B (contrato)',
        'location_id' => 'CTRB'.substr((string) time(), -4),
        'created_at' => now(),
        'updated_at' => now(),
    ]));
    $this->locB = DB::table('business_locations')->find($idB);
});

/**
 * Versão Inertia = md5 do manifest (HandleInertiaRequests::version()). Header fixo
 * ('1') causa 409 quando o servidor tem manifest real.
 *
 * Nome próprio (sufixo `Contrato`) pra NÃO colidir com `comprasInertiaVersion()`
 * do MultiTenantTest — Pest carrega todos os arquivos do run no mesmo processo e
 * uma redeclaração de função global é fatal.
 */
function comprasContratoInertiaVersion(): string
{
    $manifest = public_path('build-inertia/manifest.json');

    return file_exists($manifest) ? md5_file($manifest) : '1';
}

function comprasContratoCriarCompra(int $businessId, int $locationId, int $userId, string $refNo): Transaction
{
    return Transaction::forceCreate([
        'business_id' => $businessId,
        'location_id' => $locationId,
        'type' => 'purchase',
        'status' => 'received',
        'payment_status' => 'due',
        'transaction_date' => Carbon::now()->toDateTimeString(),
        'ref_no' => $refNo,
        'final_total' => 500.00,
        'total_before_tax' => 500.00,
        'tax_amount' => 0,
        'discount_amount' => 0,
        'created_by' => $userId,
        // NOT NULL sem default no schema real (transactions).
        'essentials_duration' => 0,
    ]);
}

// ─────────────────────────────────────────────────────────────────────────────
/**
 * Vocabulario CORE de `stage`, lido da PROPRIA whitelist do ListarComprasRequest
 * (fonte unica) — nunca de lista repetida a mao aqui. Se a whitelist crescer, este
 * teste passa a exercitar o valor novo sozinho.
 */
function comprasStagesCore(): array
{
    $regras = (new ModulesComprasHttpRequestsListarComprasRequest())->rules();
    $regra = null;
    foreach ((array) ($regras['stage'] ?? []) as $r) {
        if (is_string($r) && str_starts_with($r, 'in:')) { $regra = $r; break; }
    }
    $vals = $regra ? explode(',', substr($regra, 3)) : [];

    return array_values(array_filter($vals, fn ($v) => $v !== 'all'));
}

// UC-CMP-06 — a aba de estágio que a TELA emite não pode derrubar a listagem
//             (SDD CU-COM-04 item 1 · §5.4.4)
// ─────────────────────────────────────────────────────────────────────────────

it('UC-CMP-06 · aba de estágio emitida pela tela não pode derrubar a listagem', function () {
    $sessao = ['user' => ['business_id' => $this->biz->id, 'id' => $this->user->id]];

    // PRÉ-CONDIÇÃO ANTI-VÁCUO: sem filtro a tela responde 200. Se isto falhar, o
    // defeito é da fixture/permissão — não do contrato de filtro.
    $this->actingAs($this->user)->withSession($sessao)->get('/compras')
        ->assertStatus(200);

    // Os ids que as abas do cockpit emitem quando o operador busca com uma aba
    // ativa. Contrato: "a UI não emite valor que o próprio contrato rejeita".
    // NÃO é lista lida do .tsx pra assertar chave — é o conjunto de estágios que
    // a tela OFERECE ao operador, declarado no §Goals do charter
    // ("Filtros locais: all / abertas / rascunhos / em trânsito").
    // RECONCILIADO (2026-08-11): a fronteira carrega so o vocabulario CORE.
    // A tela NAO emite mais rotulo de exibicao como `stage` — o defeito era
    // Index.tsx:256 mandando `stage: localFilter` na busca. As abas locais seguem
    // existindo, mas filtram client-side sobre as linhas ja carregadas:
    //   'abertas'  = dueAmount > 0            (predicado de PAGAMENTO, nao status)
    //   'transito' = 'transito' OU 'pedido'  (DOIS status; o service faz `where` unico)
    // Por isso a correcao NAO foi alargar a whitelist: aceitar esses valores daria
    // 200 com resultado VAZIO em silencio — pior que o 302 que o UC denunciava.
    foreach (array_merge(['all'], comprasStagesCore()) as $stage) {
        $r = $this->actingAs($this->user)->withSession($sessao)->get('/compras?stage='.$stage);

        expect($r->getStatusCode())->toBe(
            200,
            "A aba '{$stage}' do cockpit derruba a listagem (HTTP {$r->getStatusCode()}). "
            .'A whitelist do ListarComprasRequest (all,received,ordered,pending,draft) e os ids '
            .'das abas do Index.tsx são vocabulários DIFERENTES no mesmo módulo. '
            .'Duas correções são válidas — alargar a whitelist OU a aba emitir o status core; '
            .'este assert não escolhe qual (SDD §5.4.4 · CU-COM-04).'
        );
    }
});

it('UC-CMP-06 controle-negativo · stage arbitrário CONTINUA rejeitado (anti-SQLi)', function () {
    // A superfície permitida não pode crescer pra "qualquer coisa" — o UC pede
    // reconciliação dos dois vocabulários, NÃO abertura da whitelist.
    $r = $this->actingAs($this->user)
        ->withSession(['user' => ['business_id' => $this->biz->id, 'id' => $this->user->id]])
        ->get('/compras?stage='.urlencode("'; DROP TABLE transactions; --"));

    expect($r->getStatusCode())->not->toBe(
        200,
        'stage arbitrário foi ACEITO — a whitelist do ListarComprasRequest (US-COM-008) caiu.'
    );
});

// ─────────────────────────────────────────────────────────────────────────────
// UC-CMP-07 — os DOIS contratos de ordenação do módulo não podem divergir
//             (SDD CU-COM-04 itens 2-3 · §5.4.4)
// ─────────────────────────────────────────────────────────────────────────────

it('UC-CMP-07 · todo sort do SORT_MAP é aceito pelo ListarComprasRequest', function () {
    $sessao = ['user' => ['business_id' => $this->biz->id, 'id' => $this->user->id]];

    // PRÉ-CONDIÇÃO ANTI-VÁCUO.
    $this->actingAs($this->user)->withSession($sessao)->get('/compras')->assertStatus(200);

    // O conjunto vem do PRÓPRIO SORT_MAP por reflexão — não de uma lista copiada
    // à mão (que drifaria em silêncio na próxima coluna nova). O SORT_MAP é o
    // contrato de segurança do módulo: é ele que decide qual coluna SQL real
    // entra no orderBy.
    $sortMap = (new ReflectionClass(ComprasService::class))->getConstant('SORT_MAP');

    expect($sortMap)->toBeArray()->not->toBeEmpty();

    foreach (array_keys($sortMap) as $sort) {
        $r = $this->actingAs($this->user)->withSession($sessao)->get('/compras?sort='.$sort);

        expect($r->getStatusCode())->toBe(
            200,
            "O ComprasService sabe ordenar por '{$sort}' (está no SORT_MAP) mas o "
            ."ListarComprasRequest REJEITA (HTTP {$r->getStatusCode()}). "
            .'Dois contratos do mesmo módulo discordam: ou a whitelist do Request passa a '
            .'derivar do SORT_MAP, ou a coluna sai do SORT_MAP (SDD §5.4.4 · CU-COM-04).'
        );
    }
});

it('UC-CMP-07 controle-negativo · sort FORA do SORT_MAP continua rejeitado', function () {
    $r = $this->actingAs($this->user)
        ->withSession(['user' => ['business_id' => $this->biz->id, 'id' => $this->user->id]])
        ->get('/compras?sort=users.password');

    expect($r->getStatusCode())->not->toBe(
        200,
        'sort fora do SORT_MAP foi ACEITO — a defesa anti-SQLi da US-COM-008 caiu. '
        .'O UC-CMP-07 pede UM dono pra whitelist, não whitelist aberta.'
    );
});

// ─────────────────────────────────────────────────────────────────────────────
// UC-CMP-08 — escopo por localização permitida (paridade com a Blade e com o
//             Purchase/Index Inertia) — SDD CU-COM-05 · §5.4.1
// ─────────────────────────────────────────────────────────────────────────────

it('UC-CMP-08 · compra de local não permitido não aparece no cockpit', function () {
    // Usuário SEM `access_all_locations`, com permissão DIRETA só pra Loja A.
    // `User::permitted_locations()` lê `$user->permissions` (permissões DIRETAS),
    // por isso o givePermissionTo vai no user, não no role.
    Permission::firstOrCreate(['name' => 'location.'.$this->locA->id, 'guard_name' => 'web']);
    $this->user->givePermissionTo('location.'.$this->locA->id);

    $refPermitida = 'CTR-LOCA-'.uniqid();
    $refProibida = 'CTR-LOCB-'.uniqid();

    comprasContratoCriarCompra($this->biz->id, (int) $this->locA->id, $this->user->id, $refPermitida);
    comprasContratoCriarCompra($this->biz->id, (int) $this->locB->id, $this->user->id, $refProibida);

    $response = $this->actingAs($this->user)
        ->withSession(['user' => ['business_id' => $this->biz->id, 'id' => $this->user->id]])
        ->withHeaders([
            'X-Inertia' => 'true',
            'X-Inertia-Version' => comprasContratoInertiaVersion(),
            // partial reload força resolver o defer e devolver o payload JSON.
            'X-Inertia-Partial-Data' => 'rows',
            'X-Inertia-Partial-Component' => 'Compras/Index',
        ])
        ->get('/compras?per_page=100');

    $response->assertStatus(200);

    $rows = $response->json('props.rows.data') ?? [];
    $refs = array_filter(array_map(fn ($r) => $r['ref_no'] ?? null, $rows));

    // PRÉ-CONDIÇÃO ANTI-VÁCUO: a compra do local PERMITIDO tem que aparecer.
    // Sem isto, um "não vazou" poderia significar "a listagem veio vazia" —
    // ausência de execução travestida de contrato satisfeito.
    // FALHA AQUI SIGNIFICA: PRÉ-CONDIÇÃO FALHOU: a compra do local PERMITIDO não apareceu. O teste não exercitou o contrato — conferir fixture/permissão antes de ler o resultado.
    expect($refs)->toContain($refPermitida);

    expect($refs)->not->toContain(
        $refProibida,
        'PERDA DE ESCOPO NA MIGRAÇÃO: compra da Loja B (usuário sem permissão nela) '
        .'apareceu no cockpit. /purchases aplica whereIn(location_id, permitted_locations) '
        .'nos DOIS caminhos (Blade AJAX e indexInertia); ComprasService::listarCompras não '
        .'aplica escopo algum além do business_id. Não é vazamento cross-tenant — é '
        .'intra-tenant entre lojas (SDD §5.4.1 · CU-COM-05). '
        .'Se Compras NÃO deve ter escopo por localização, isto vira Non-Goal no charter ([W]).'
    );
});
