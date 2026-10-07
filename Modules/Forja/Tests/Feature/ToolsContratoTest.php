<?php

declare(strict_types=1);

use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Forja\Contracts\Tool;
use Modules\Forja\Services\ToolRegistry;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * Contrato da tela /ads/admin/tools — catálogo de tools + audit das execuções.
 *
 * UC-TOOLS-01 `[T0]` — toda execução grava no audit log, no business da sessão,
 *                      inclusive a que falha.
 * UC-TOOLS-02        — o catálogo distingue tool de leitura de tool de escrita.
 *
 * Os UC derivam do charter (`Tools.charter.md`) + ADR 0053 (§Arquitetura técnica item 9 e
 * Pilar 4: audit de TODA chamada; tabela de tools com a coluna "Destrutiva") + ADR 0093
 * — nunca do `.tsx`.
 * Trio: Modules/Forja/Resources/js/Pages/ads/Admin/{Tools.charter.md,Tools.casos.md}
 *
 * As tools do teste são FALSAS e registradas num ToolRegistry vinculado ao container:
 * executar uma tool real (Boost/escrita) num teste tocaria banco/arquivo/git de verdade,
 * e o contrato aqui é o do catálogo e do audit — não o de cada tool.
 *
 * Tier 0 (ADR 0093 + ADR 0358): usuário no tenant fictício 98. NUNCA biz=4.
 * `DatabaseTransactions`: a execução grava em `mcp_tool_executions`.
 *
 * @see Modules/Forja/Http/Controllers/Admin/ToolsController.php
 * @see Modules/Forja/Services/ToolRegistry.php
 */

const TOOLS_FAKE_LEITURA = 'uc_tools_fake_leitura';
const TOOLS_FAKE_FALHA = 'uc_tools_fake_falha';
const TOOLS_FAKE_ESCRITA = 'uc_tools_fake_escrita';

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: a stack UltimatePOS das rotas /ads exige schema MySQL.');
    }
    foreach (['users', 'business', 'mcp_tool_executions'] as $t) {
        if (! Schema::hasTable($t)) {
            $this->markTestSkipped("Tabela {$t} ausente — rode com DB_CONNECTION=mysql e o schema baseline.");
        }
    }
});

/** Tool falsa: leitura ou escrita, que dá certo ou lança. */
function toolsFake(string $nome, bool $leitura, bool $lanca = false): Tool
{
    return new class($nome, $leitura, $lanca) implements Tool
    {
        public function __construct(private string $nome, private bool $leitura, private bool $lanca) {}

        public function name(): string { return $this->nome; }

        public function description(): string { return 'Tool falsa do ToolsContratoTest.'; }

        public function inputSchema(): array { return ['type' => 'object', 'properties' => []]; }

        public function category(): string { return $this->leitura ? 'leitura' : 'escrita'; }

        public function isReadOnly(): bool { return $this->leitura; }

        public function execute(array $input): array
        {
            if ($this->lanca) {
                throw new \RuntimeException('falha proposital do teste');
            }

            return ['ok' => true, 'output' => ['eco' => $input], 'error' => null];
        }
    };
}

/** Registry real + as 3 tools falsas, vinculado ao container (o controller o recebe por injeção). */
function toolsRegistryComFalsas(): ToolRegistry
{
    $registry = new ToolRegistry();
    $registry->register(toolsFake(TOOLS_FAKE_LEITURA, true));
    $registry->register(toolsFake(TOOLS_FAKE_FALHA, true, lanca: true));
    $registry->register(toolsFake(TOOLS_FAKE_ESCRITA, false));
    app()->instance(ToolRegistry::class, $registry);

    return $registry;
}

function toolsUsuario(int $businessId): User
{
    return User::factory()->create([
        'business_id' => $businessId,
        'username' => 'tools_contrato_'.uniqid(),
        'user_type' => 'user',
        'allow_login' => 1,
    ]);
}

function toolsSessao(User $user, int $businessId)
{
    return test()
        ->actingAs($user)
        ->withSession([
            'user.business_id' => $businessId,
            'user' => ['business_id' => $businessId, 'id' => $user->id],
        ]);
}

it('UC-TOOLS-01 — toda execução grava no audit log no business da sessão, inclusive a que falha', function () {
    $biz = (int) $this->seededTenant()->id;
    $user = toolsUsuario($biz);
    toolsRegistryComFalsas();

    $antes = DB::table('mcp_tool_executions')->max('id') ?? 0;

    // (a) execução que dá certo.
    toolsSessao($user, $biz)
        ->postJson('/ads/admin/tools/'.TOOLS_FAKE_LEITURA.'/execute', ['input' => ['x' => 1]])
        ->assertOk()
        ->assertJsonPath('ok', true);

    // (b) execução que FALHA também é auditada — audit só do sucesso esconderia justamente
    // o que se quer investigar depois (ADR 0053: "TODA chamada").
    toolsSessao($user, $biz)
        ->postJson('/ads/admin/tools/'.TOOLS_FAKE_FALHA.'/execute', ['input' => []])
        ->assertOk()
        ->assertJsonPath('ok', false);

    $linhas = DB::table('mcp_tool_executions')->where('id', '>', $antes)->orderBy('id')->get();

    expect($linhas)->toHaveCount(2);

    $ok = $linhas->firstWhere('tool_name', TOOLS_FAKE_LEITURA);
    expect($ok)->not->toBeNull();
    expect((bool) $ok->ok)->toBeTrue();
    expect((bool) $ok->is_read_only)->toBeTrue();
    // [T0] o audit carrega o business da sessão, não o default da coluna.
    expect((int) $ok->business_id)->toBe($biz);

    $falha = $linhas->firstWhere('tool_name', TOOLS_FAKE_FALHA);
    expect($falha)->not->toBeNull();
    expect((bool) $falha->ok)->toBeFalse();
    expect((string) $falha->error)->not->toBe('');
    expect((int) $falha->business_id)->toBe($biz);
});

it('UC-TOOLS-02 — o catálogo distingue tool de leitura de tool de escrita', function () {
    $biz = (int) $this->seededTenant()->id;
    $user = toolsUsuario($biz);
    toolsRegistryComFalsas();

    $inicial = toolsSessao($user, $biz)->get('/ads/admin/tools');
    expect($inicial->status())->toBe(200);
    $versao = data_get($inicial->viewData('page'), 'version');

    // As props são deferidas: o browser as pede num partial reload. `X-Requested-With`
    // vai junto porque o cliente Inertia o manda SEMPRE (lápide §5 2026-09-08).
    $parcial = toolsSessao($user, $biz)
        ->withHeaders([
            'X-Requested-With' => 'XMLHttpRequest',
            'X-Inertia' => 'true',
            'X-Inertia-Version' => (string) $versao,
            'X-Inertia-Partial-Component' => 'ads/Admin/Tools',
            'X-Inertia-Partial-Data' => 'tools_by_category,kpis',
        ])
        ->get('/ads/admin/tools');
    expect($parcial->status())->toBe(200);

    $tools = collect(data_get($parcial->json(), 'props.tools_by_category', []))
        ->flatMap(fn ($grupo) => $grupo['tools'] ?? [])
        ->keyBy('name');

    // As duas naturezas aparecem, cada uma com a marca certa.
    expect($tools->has(TOOLS_FAKE_LEITURA))->toBeTrue();
    expect($tools->has(TOOLS_FAKE_ESCRITA))->toBeTrue();
    expect($tools[TOOLS_FAKE_LEITURA]['is_read_only'])->toBeTrue();
    expect($tools[TOOLS_FAKE_ESCRITA]['is_read_only'])->toBeFalse();

    // Toda tool do catálogo carrega a marca como booleano — nenhuma fica sem natureza declarada.
    expect($tools->every(fn ($t) => is_bool($t['is_read_only'] ?? null)))->toBeTrue();

    // Os KPIs batem com o catálogo: leitura + escrita = total, e o total é o que a tela lista.
    $kpis = (array) data_get($parcial->json(), 'props.kpis', []);
    expect($kpis['read_only'] + $kpis['write'])->toBe($kpis['total']);
    expect($kpis['total'])->toBe($tools->count());
    expect($kpis['write'])->toBe($tools->filter(fn ($t) => $t['is_read_only'] === false)->count());
});
