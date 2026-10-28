<?php
// @covers-us US-SELL-069 (UC-FLXT-19: a margem mínima é configurada nesta tela)
// @covers-us US-PCP-025 (UC-FLXT-23: o sinal mínimo da produção é configurado nesta tela)
// @covers-us US-PCP-021 (UC-FLXT-20/21: a regra de baixa de material sem OS não executa e não baixa duas vezes)
// @covers-us US-PCP-031 (UC-FLXT-22: o interruptor da reserva de insumo na venda mora nesta tela)
// @covers-us US-COM-027 (UC-FLXT-24: a requisição pelo estoque mínimo liga e desliga nesta tela)

declare(strict_types=1);

// Tests\TestCase já é aplicado globalmente em tests/Pest.php. NÃO redeclarar.

/**
 * Tela Sistema › Fluxos e regras (ADR 0435) — acesso, menu e a aba Modelos.
 *
 * Cobre os UCs de resources/js/Pages/Fluxos/Index.casos.md (G-2: cada UC citado aqui).
 * Os números do encaixe vêm do protótipo (fluxos-data.jsx `encaixe`: porte igual 2, vizinho 1,
 * atividade igual 2), não do controller.
 *
 * Headers Inertia de verdade, incluindo X-Requested-With (§5 2026-09-08). Tenant fictício 98
 * (ADR 0358), transação revertida por caso. NUNCA biz=4.
 *
 * @see app/Http/Controllers/FluxosController.php
 * @see resources/js/Pages/Fluxos/Index.casos.md
 */

use App\User;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

const FLXT_BIZ = 98;

function flxtUsuario(array $permissoes): User
{
    $user = User::factory()->create(['business_id' => FLXT_BIZ, 'username' => 'flxt_' . uniqid()]);
    if ($permissoes !== []) {
        $papel = Role::create(['name' => 'FlxtTeste' . uniqid() . '#' . FLXT_BIZ, 'business_id' => FLXT_BIZ, 'guard_name' => 'web']);
        foreach ($permissoes as $p) {
            Permission::findOrCreate($p, 'web');
        }
        $papel->syncPermissions($permissoes);
        $user->assignRole($papel);
    }
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    return User::findOrFail($user->id);
}

function flxtGet($teste, string $url, ?string $parcial = null)
{
    $manifest = public_path('build-inertia/manifest.json');
    $h = [
        'X-Inertia' => 'true',
        'X-Inertia-Version' => file_exists($manifest) ? md5_file($manifest) : '1',
        'X-Requested-With' => 'XMLHttpRequest',
        'Accept' => 'text/html, application/xhtml+xml',
    ];
    if ($parcial !== null) {
        $h['X-Inertia-Partial-Component'] = 'Fluxos/Index';
        $h['X-Inertia-Partial-Data'] = $parcial;
    }

    return $teste->withHeaders($h)->get($url);
}

/** Procura no shell.menu um item com href /fluxos; devolve o item ou null. */
function flxtItemMenu(array $itens): ?array
{
    foreach ($itens as $item) {
        if (parse_url((string) ($item['href'] ?? ''), PHP_URL_PATH) === '/fluxos') {
            return $item;
        }
        if ($achado = flxtItemMenu($item['children'] ?? [])) {
            return $achado;
        }
    }

    return null;
}

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('Schema UltimatePOS + Spatie exigem MySQL (ADR 0358).');
    }
    if (! DB::table('business')->where('id', FLXT_BIZ)->exists()) {
        $this->markTestSkipped('Tenant fictício 98 ausente nesta lane.');
    }
    DB::beginTransaction();
});

afterEach(function () {
    if (DB::transactionLevel() > 0) {
        DB::rollBack();
    }
});

function flxtEntrar($teste, User $u): void
{
    $teste->actingAs($u);
    session(['user.business_id' => FLXT_BIZ, 'business.id' => FLXT_BIZ]);
}

it('UC-FLXT-01 · com fluxos.ver a tela abre e o menu leva a /fluxos', function () {
    flxtEntrar($this, flxtUsuario(['fluxos.ver']));

    $r = flxtGet($this, '/fluxos');
    $r->assertOk();
    expect($r->json('component'))->toBe('Fluxos/Index');
    expect($r->json('props.aba'))->toBe('modelos');

    $menu = flxtGet($this, '/fluxos', 'shell');
    $menu->assertOk();
    $item = flxtItemMenu($menu->json('props.shell.menu') ?? []);
    expect($item)->not->toBeNull();
    expect((string) ($item['label'] ?? ''))->toBe('Fluxos e regras');

    // O item declara o grupo SISTEMA (Sidebar.tsx findGroupKey dá precedência ao declarado).
    expect((string) ($item['group'] ?? ''))->toBe('sistema');
    expect((bool) ($item['inertia'] ?? false))->toBeTrue();
    $src = file_get_contents(base_path('app/Http/Middleware/AdminSidebarMenu.php'));
    expect($src)->toContain("auth()->user()->can('fluxos.ver')");
    expect($src)->toContain("route('fluxos.index')");
});

it('UC-FLXT-02 · sem fluxos.ver a tela devolve 403', function () {
    flxtEntrar($this, flxtUsuario(['customer.view']));

    expect(flxtGet($this, '/fluxos')->getStatusCode())->toBe(403);
});

it('UC-FLXT-03 · aba Modelos traz os 7 modelos com o encaixe do catálogo', function () {
    flxtEntrar($this, flxtUsuario(['fluxos.ver']));

    $r = flxtGet($this, '/fluxos?aba=modelos');
    $r->assertOk();
    $modelos = $r->json('props.modelos');
    expect($modelos)->toHaveCount(7);
    expect(array_column($modelos, 'id'))->toContain('cv-pequena', 'oficina', 'balcao');

    $cv = collect($modelos)->firstWhere('id', 'cv-pequena');
    expect(array_column($cv['processos'], 'dominio'))->toBe(['venda', 'producao', 'compra']);

    // Pequena · Comunicação visual: cv-pequena tem porte e atividade iguais (2 + 2).
    expect($r->json('props.encaixe.cv-pequena.pequena.cv'))->toBe(['n' => 4, 'rotulo' => 'Recomendado', 'motivo' => 'Porte e atividade batem']);
    // Oficina: porte igual (pequena está na lista), outra atividade → 2 = Parcial.
    expect($r->json('props.encaixe.oficina.pequena.cv'))->toBe(['n' => 2, 'rotulo' => 'Parcial', 'motivo' => 'Porte bate · outra atividade']);
    // Balcão (só micro): porte vizinho 1 + atividade cv 2 = 3 = Serve.
    expect($r->json('props.encaixe.balcao.pequena.cv'))->toBe(['n' => 3, 'rotulo' => 'Serve', 'motivo' => 'Atividade bate · feito para porte vizinho']);

    // Aba desconhecida cai em Modelos.
    expect(flxtGet($this, '/fluxos?aba=xyz')->json('props.aba'))->toBe('modelos');
});

it('UC-FLXT-04 · a migration dá fluxos.ver e fluxos.configurar a quem tem business_settings.access', function () {
    Permission::findOrCreate('business_settings.access', 'web');
    $gerente = Role::create(['name' => 'FlxtGerente' . uniqid() . '#' . FLXT_BIZ, 'business_id' => FLXT_BIZ, 'guard_name' => 'web']);
    $gerente->syncPermissions(['business_settings.access']);
    $vendedor = Role::create(['name' => 'FlxtVendedor' . uniqid() . '#' . FLXT_BIZ, 'business_id' => FLXT_BIZ, 'guard_name' => 'web']);
    $vendedor->syncPermissions([Permission::findOrCreate('customer.view', 'web')]);

    $migration = require base_path('database/migrations/2026_10_16_150000_add_fluxos_permissions.php');
    $migration->up();
    $migration->up(); // idempotente
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $gerente = Role::findById($gerente->id, 'web');
    expect($gerente->hasPermissionTo('fluxos.ver'))->toBeTrue();
    expect($gerente->hasPermissionTo('fluxos.configurar'))->toBeTrue();
    expect(Role::findById($vendedor->id, 'web')->hasPermissionTo('fluxos.ver'))->toBeFalse();

    $u = User::factory()->create(['business_id' => FLXT_BIZ, 'username' => 'flxt_g_' . uniqid()]);
    $u->assignRole($gerente);
    flxtEntrar($this, User::findOrFail($u->id));
    expect(flxtGet($this, '/fluxos')->json('props.pode_configurar'))->toBeTrue();
});

function flxtPostAplicar($teste, array $dados)
{
    return $teste->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'X-Inertia' => 'true'])
        ->post('/fluxos/modelo', $dados);
}

it('UC-FLXT-05 · quem configura usa um modelo e só a empresa da sessão passa a tê-lo', function () {
    if (! \Illuminate\Support\Facades\Schema::hasTable('fsm_modelos_empresa')) {
        $this->markTestSkipped('Tabela fsm_modelos_empresa ausente (migration do #9492).');
    }
    $outra = (int) DB::table('business')->where('id', '!=', FLXT_BIZ)->value('id');
    $antesOutra = DB::table('sale_processes')->where('business_id', $outra)->where('key', 'like', 'flx_cv_pequena_%')->count();

    flxtEntrar($this, flxtUsuario(['fluxos.ver', 'fluxos.configurar']));
    $r = flxtPostAplicar($this, ['modelo' => 'cv-pequena', 'porte' => 'pequena', 'atividade' => 'cv', 'business_id' => $outra]);

    $r->assertRedirect('/fluxos?aba=empresa');
    expect(session('status.msg'))->toContain('Documentos em andamento continuam na etapa em que estavam');
    $linha = DB::table('fsm_modelos_empresa')->where('business_id', FLXT_BIZ)->first();
    expect($linha->modelo)->toBe('cv-pequena');
    expect([$linha->porte, $linha->atividade])->toBe(['pequena', 'cv']);
    expect(DB::table('sale_processes')->where('business_id', FLXT_BIZ)->where('key', 'like', 'flx_cv_pequena_%')->pluck('dominio')->sort()->values()->all())
        ->toBe(['compra', 'producao', 'venda']);
    // O business_id do formulário é ignorado: a outra empresa não ganhou nada.
    expect(DB::table('sale_processes')->where('business_id', $outra)->where('key', 'like', 'flx_cv_pequena_%')->count())->toBe($antesOutra);
});

it('UC-FLXT-06 · sem fluxos.configurar o POST devolve 403 e nada é gravado', function () {
    if (! \Illuminate\Support\Facades\Schema::hasTable('fsm_modelos_empresa')) {
        $this->markTestSkipped('Tabela fsm_modelos_empresa ausente (migration do #9492).');
    }
    $antes = DB::table('fsm_modelos_empresa')->where('business_id', FLXT_BIZ)->value('modelo');

    flxtEntrar($this, flxtUsuario(['fluxos.ver']));
    expect(flxtGet($this, '/fluxos')->json('props.pode_configurar'))->toBeFalse();
    expect(flxtPostAplicar($this, ['modelo' => 'oficina'])->getStatusCode())->toBe(403);

    expect(DB::table('fsm_modelos_empresa')->where('business_id', FLXT_BIZ)->value('modelo'))->toBe($antes);
    expect(DB::table('sale_processes')->where('business_id', FLXT_BIZ)->where('key', 'like', 'flx_oficina_%')->exists())->toBeFalse();
});

it('UC-FLXT-07 · a aba Fluxos da empresa traz o modelo da própria empresa', function () {
    if (! \Illuminate\Support\Facades\Schema::hasTable('fsm_modelos_empresa')) {
        $this->markTestSkipped('Tabela fsm_modelos_empresa ausente (migration do #9492).');
    }
    $outra = (int) DB::table('business')->where('id', '!=', FLXT_BIZ)->value('id');
    DB::table('fsm_modelos_empresa')->updateOrInsert(['business_id' => FLXT_BIZ], ['modelo' => 'grafica', 'porte' => 'media', 'atividade' => 'offset', 'aplicado_em' => now()]);
    DB::table('fsm_modelos_empresa')->updateOrInsert(['business_id' => $outra], ['modelo' => 'oficina', 'porte' => 'micro', 'atividade' => 'oficina', 'aplicado_em' => now()]);

    flxtEntrar($this, flxtUsuario(['fluxos.ver']));
    $empresa = flxtGet($this, '/fluxos?aba=empresa')->json('props.empresa');

    expect($empresa['modelo'])->toBe('grafica');
    expect([$empresa['porte'], $empresa['atividade']])->toBe(['media', 'offset']);
});

function flxtAssistente($teste, array $dados)
{
    return $teste->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
        ->postJson('/fluxos/assistente', $dados);
}

it('UC-FLXT-08 · com IA, o assistente devolve o modelo sugerido só com casos do catálogo', function () {
    \App\Domain\Fsm\Ai\RecomendarModeloFluxoAgent::fake([[
        'modelo' => 'instalacao', 'porque' => 'Você instala fachadas com equipe própria.',
        'ligar' => ['UC-FLX-28', 'UC-FLX-99'], 'desligar' => ['UC-FLX-15'],
        'porte' => 'pequena', 'atividade' => 'instal',
    ]]);
    flxtEntrar($this, flxtUsuario(['fluxos.ver']));

    $r = flxtAssistente($this, ['texto' => 'Duas equipes de instalação de fachada.', 'porte' => 'pequena', 'atividade' => 'cv']);

    $r->assertOk();
    expect($r->json())->toBe([
        'modelo' => 'instalacao', 'porque' => 'Você instala fachadas com equipe própria.',
        'ligar' => ['UC-FLX-28'], 'desligar' => ['UC-FLX-15'],
        'porte' => 'pequena', 'atividade' => 'instal', 'origem' => 'ia',
    ]);
});

it('UC-FLXT-09 · sem IA, cai na regra de encaixe e diz que foi pelo perfil', function () {
    \App\Domain\Fsm\Ai\RecomendarModeloFluxoAgent::fake(fn () => throw new RuntimeException('provedor fora do ar'));
    flxtEntrar($this, flxtUsuario(['fluxos.ver']));

    $r = flxtAssistente($this, ['texto' => '', 'porte' => 'micro', 'atividade' => 'oficina']);

    $r->assertOk();
    // Micro · oficina: o modelo Oficina mecânica tem porte e atividade iguais (encaixe 4).
    expect($r->json('modelo'))->toBe('oficina');
    expect($r->json('origem'))->toBe('regra');
    expect($r->json('porque'))->toBe('Escolhido só pelo porte e pela atividade — a IA não respondeu.');
});

it('UC-FLXT-10 · modelo fora do catálogo vindo da IA também cai na regra', function () {
    \App\Domain\Fsm\Ai\RecomendarModeloFluxoAgent::fake([[
        'modelo' => 'nao-existe', 'porque' => 'x', 'ligar' => [], 'desligar' => [], 'porte' => 'pequena', 'atividade' => 'cv',
    ]]);
    flxtEntrar($this, flxtUsuario(['fluxos.ver']));

    $r = flxtAssistente($this, ['texto' => 'qualquer', 'porte' => 'pequena', 'atividade' => 'cv']);

    expect($r->json('origem'))->toBe('regra');
    expect($r->json('modelo'))->toBe('cv-pequena');
});

it('UC-FLXT-11 · sem fluxos.ver o assistente devolve 403', function () {
    flxtEntrar($this, flxtUsuario(['customer.view']));

    expect(flxtAssistente($this, ['porte' => 'pequena', 'atividade' => 'cv'])->getStatusCode())->toBe(403);
});

it('UC-FLXT-12 · aba Processos traz os processos do catálogo com estados, transições e roteiro', function () {
    flxtEntrar($this, flxtUsuario(['fluxos.ver']));

    $r = flxtGet($this, '/fluxos?aba=processos&processo=nfe');
    $r->assertOk();
    $procs = collect($r->json('props.processos'));

    // Os processos do protótipo aprovado (#9498), incluindo devoluções e as 3 OS.
    expect($procs->count())->toBe(count(\App\Domain\Fsm\Modelos\CatalogoProcessos::processos()));
    expect($procs->pluck('id')->all())->toContain('venda', 'producao', 'nfe', 'devolucao', 'devolucao-compra', 'os-reparo', 'oficina-os', 'os-cv');
    expect($r->json('props.processo'))->toBe('nfe');
    expect($r->json('props.processos_total'))->toBe($procs->count());

    // Produção: roteiro em paralelo; transição sem mudar de estado vem com para=null.
    $producao = $procs->firstWhere('id', 'producao');
    expect($producao['roteiro']['junta'])->toBe(['Montagem', 'Instalação']);
    $concluirEtapa = collect($producao['transicoes'])->firstWhere('rotulo', 'Concluir etapa');
    expect($concluirEtapa['para'])->toBeNull();
    $chaves = array_column($producao['estados'], 'chave');
    expect(collect($producao['transicoes'])->pluck('de')->diff($chaves)->all())->toBe([]);

    // Fora da aba Processos o payload não leva os processos; processo desconhecido cai em producao.
    expect(flxtGet($this, '/fluxos?aba=modelos')->json('props.processos'))->toBe([]);
    expect(flxtGet($this, '/fluxos?aba=processos&processo=xyz')->json('props.processo'))->toBe('producao');
});

it('UC-FLXT-13 · aba Casos de uso traz os casos com fontes, estado, US e os modelos que usam', function () {
    flxtEntrar($this, flxtUsuario(['fluxos.ver']));

    $r = flxtGet($this, '/fluxos?aba=casos');
    $r->assertOk();
    $casos = collect($r->json('props.casos_lista'));

    expect($casos->count())->toBe(count(\App\Domain\Fsm\Modelos\CatalogoModelos::casos()));
    $uc01 = $casos->firstWhere('id', 'UC-FLX-01');
    expect($uc01['titulo'])->toBe('Orçamento aprovado vira OS sem redigitar');
    expect($uc01['fontes'])->toContain('mubisys');
    expect($uc01['estado'])->toBe('producao');
    // Modelos que listam UC-FLX-01 no catálogo.
    $esperados = collect(\App\Domain\Fsm\Modelos\CatalogoModelos::modelos())
        ->filter(fn ($m) => in_array('UC-FLX-01', $m['casos'], true))->keys()->values()->all();
    expect($uc01['modelos'])->toBe($esperados);
    expect($esperados)->toContain('cv-pequena', 'balcao');

    // Fontes, estados, descrição do porte e "Inspirado em" para o cabeçalho e os cards.
    expect($r->json('props.fontes.mubisys.nome'))->toBe('Mubisys');
    expect($r->json('props.estados_caso.planejado.rotulo'))->toBe('planejado');
    expect(collect($r->json('props.portes'))->firstWhere('value', 'pequena')['descricao'])->toBe('6 a 20 pessoas');
    expect(collect($r->json('props.modelos'))->firstWhere('id', 'cv-pequena')['insp'])->toBe(['mubisys', 'holdprint', 'shopvox']);

    // Fora da aba Casos de uso a lista não vai.
    expect(flxtGet($this, '/fluxos?aba=modelos')->json('props.casos_lista'))->toBe([]);
});

function flxtPatch($teste, string $url, array $dados)
{
    return $teste->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'X-Inertia' => 'true'])
        ->patch($url, $dados);
}

function flxtComRegrasConfiguraveis($teste): void
{
    if (! \Illuminate\Support\Facades\Schema::hasColumn('fsm_modelos_empresa', 'regras_compra')) {
        $teste->markTestSkipped('Coluna fsm_modelos_empresa.regras_compra ausente (migration 2026_10_16_170000).');
    }
}

/** `ligada` gravada na regra da etapa do processo do modelo, na empresa dada. */
function flxtLigada(int $biz, string $processo, string $etapa, string $uc): ?bool
{
    $pid = DB::table('sale_processes')->where('business_id', $biz)->where('key', $processo)->value('id');
    $regras = json_decode((string) DB::table('sale_process_stages')->where('process_id', $pid)->where('key', $etapa)->value('regras_ao_entrar'), true) ?? [];
    $r = collect($regras)->firstWhere('uc', $uc);

    return $r === null ? null : ($r['ligada'] ?? true);
}

it('UC-FLXT-14 · quem configura desliga e religa uma regra do modelo só na própria empresa', function () {
    flxtComRegrasConfiguraveis($this);
    $outra = (int) DB::table('business')->where('id', '!=', FLXT_BIZ)->value('id');
    app(\App\Domain\Fsm\Modelos\AplicarModelo::class)->aplicar($outra, 'cv-pequena');

    flxtEntrar($this, flxtUsuario(['fluxos.ver', 'fluxos.configurar']));
    flxtPostAplicar($this, ['modelo' => 'cv-pequena', 'porte' => 'pequena', 'atividade' => 'cv']);
    expect(flxtGet($this, '/fluxos?aba=empresa')->json('props.regras_ligadas.producao'))->toBe([true, true, true]);

    // Produção, regra 1 = "Impressão → Apontamento pelo celular (UC-FLX-09)".
    flxtPatch($this, '/fluxos/regra', ['dominio' => 'producao', 'indice' => 1, 'ligada' => false, 'business_id' => $outra])
        ->assertRedirect('/fluxos?aba=empresa');

    expect(flxtLigada(FLXT_BIZ, 'flx_cv_pequena_producao', 'impressao', 'UC-FLX-09'))->toBeFalse();
    expect(flxtGet($this, '/fluxos?aba=empresa')->json('props.regras_ligadas.producao'))->toBe([true, false, true]);
    // O business_id do formulário é ignorado: a outra empresa segue com a regra ligada.
    expect(flxtLigada($outra, 'flx_cv_pequena_producao', 'impressao', 'UC-FLX-09'))->toBeTrue();

    // Aplicar o mesmo modelo de novo não religa o que a empresa desligou.
    flxtPostAplicar($this, ['modelo' => 'cv-pequena', 'porte' => 'pequena', 'atividade' => 'cv']);
    expect(flxtLigada(FLXT_BIZ, 'flx_cv_pequena_producao', 'impressao', 'UC-FLX-09'))->toBeFalse();

    flxtPatch($this, '/fluxos/regra', ['dominio' => 'producao', 'indice' => 1, 'ligada' => true]);
    expect(flxtLigada(FLXT_BIZ, 'flx_cv_pequena_producao', 'impressao', 'UC-FLX-09'))->toBeTrue();

    // Regra que não existe no modelo em uso: erro de formulário, nada gravado.
    flxtPatch($this, '/fluxos/regra', ['dominio' => 'entrega', 'indice' => 0, 'ligada' => false])
        ->assertSessionHasErrors('regra');
});

it('UC-FLXT-15 · a Compra é configurável: Usar modelo traz o custo do modelo e a empresa muda os parâmetros', function () {
    flxtComRegrasConfiguraveis($this);
    flxtEntrar($this, flxtUsuario(['fluxos.ver', 'fluxos.configurar']));
    flxtPostAplicar($this, ['modelo' => 'balcao', 'porte' => 'micro', 'atividade' => 'rapida']);

    $compra = fn () => collect(flxtGet($this, '/fluxos?aba=empresa')->json('props.regras_compra'));
    // balcao: último preço, só recalcula o custo (CasosDoPrototipo::MODELOS).
    expect($compra()->firstWhere('id', 'insumo.custo')['params'])->toMatchArray(['metodo' => 'ultimo', 'preco' => 'so_custo', 'etapa' => 'recebido']);

    flxtPatch($this, '/fluxos/compra', ['regra' => 'insumo.custo', 'params' => ['metodo' => 'medio', 'etapa' => 'conferido']])
        ->assertRedirect('/fluxos?aba=empresa');
    $custo = $compra()->firstWhere('id', 'insumo.custo');
    expect($custo['params'])->toMatchArray(['metodo' => 'medio', 'preco' => 'so_custo', 'etapa' => 'conferido']);
    expect($custo['etapa_efetiva'])->toBe('conferido');

    flxtPatch($this, '/fluxos/compra', ['regra' => 'pedido.email', 'ativo' => false]);
    expect($compra()->firstWhere('id', 'pedido.email')['ativo'])->toBeFalse();

    // Trocar de modelo volta o custo para o do modelo e mantém o resto.
    flxtPostAplicar($this, ['modelo' => 'brindes', 'porte' => 'micro', 'atividade' => 'brindes']);
    expect($compra()->firstWhere('id', 'insumo.custo')['params'])->toMatchArray(['metodo' => 'ultimo', 'preco' => 'aplicar', 'regraEm' => 'categoria', 'ops' => 'vivo']);
    expect($compra()->firstWhere('id', 'pedido.email')['ativo'])->toBeFalse();
});

it('UC-FLXT-16 · a entrada no estoque é obrigatória e valor fora das opções é recusado', function () {
    flxtComRegrasConfiguraveis($this);
    flxtEntrar($this, flxtUsuario(['fluxos.ver', 'fluxos.configurar']));
    flxtPostAplicar($this, ['modelo' => 'cv-pequena']);
    $antes = DB::table('fsm_modelos_empresa')->where('business_id', FLXT_BIZ)->value('regras_compra');

    flxtPatch($this, '/fluxos/compra', ['regra' => 'recebido.estoque', 'ativo' => false])->assertSessionHasErrors('regra');
    flxtPatch($this, '/fluxos/compra', ['regra' => 'insumo.custo', 'params' => ['metodo' => 'chute']])->assertSessionHasErrors('regra');
    flxtPatch($this, '/fluxos/compra', ['regra' => 'insumo.custo', 'params' => ['tolerancia' => 5]])->assertSessionHasErrors('regra');

    expect(DB::table('fsm_modelos_empresa')->where('business_id', FLXT_BIZ)->value('regras_compra'))->toBe($antes);
});

it('UC-FLXT-17 · sem fluxos.configurar mudar regra devolve 403; sem modelo, recusa', function () {
    flxtComRegrasConfiguraveis($this);
    DB::table('fsm_modelos_empresa')->updateOrInsert(['business_id' => FLXT_BIZ], ['modelo' => 'legado', 'regras_compra' => null]);

    flxtEntrar($this, flxtUsuario(['fluxos.ver']));
    expect(flxtPatch($this, '/fluxos/regra', ['dominio' => 'venda', 'indice' => 0, 'ligada' => false])->getStatusCode())->toBe(403);
    expect(flxtPatch($this, '/fluxos/compra', ['regra' => 'pedido.email', 'ativo' => false])->getStatusCode())->toBe(403);

    flxtEntrar($this, flxtUsuario(['fluxos.ver', 'fluxos.configurar']));
    flxtPatch($this, '/fluxos/compra', ['regra' => 'pedido.email', 'ativo' => false])->assertSessionHasErrors('regra');
    expect(DB::table('fsm_modelos_empresa')->where('business_id', FLXT_BIZ)->value('regras_compra'))->toBeNull();
    expect(flxtGet($this, '/fluxos?aba=empresa')->json('props.regras_compra'))->toBe([]);
});

it('UC-FLXT-18 · aba Processos mostra quanto o ERP executa de cada processo e de cada transição', function () {
    flxtEntrar($this, flxtUsuario(['fluxos.ver']));
    $procs = collect(flxtGet($this, '/fluxos?aba=processos')->json('props.processos'))->keyBy('id');

    // Números do protótipo aprovado (#9506, fluxos-processos.jsx COB): [sim, parcial, nao, nao_verificado].
    // `producao` mudou com a OS por item (#9522, ADR 0436): Retomar virou sim e Liberar, Reprovar,
    // Refazer e Cancelar item viraram parcial em CatalogoProcessos — era [0, 5, 4, 0].
    // Aguardando material (US-PCP-032, 2026-10-28) somou Aguardar material, Conferir material e o Cancelar
    // item a partir dele, todos parcial — era [1, 8, 0, 0].
    $prototipo = [
        'venda' => [0, 6, 0, 0], 'producao' => [1, 11, 0, 0], 'nfe' => [2, 7, 0, 0], 'nfse' => [1, 4, 2, 0],
        'cobranca' => [0, 5, 3, 0], 'pagar' => [0, 2, 2, 0], 'compra' => [0, 6, 1, 0], 'requisicao' => [0, 0, 3, 0],
        'inventario' => [0, 1, 2, 0], 'entrega' => [0, 5, 1, 0], 'devolucao' => [1, 2, 6, 0], 'devolucao-compra' => [2, 0, 0, 1],
        'os-reparo' => [21, 0, 2, 0], 'oficina-os' => [14, 0, 0, 0], 'os-cv' => [0, 32, 2, 0], 'atendimento' => [0, 2, 2, 0],
        'contrato' => [1, 3, 2, 0], 'ponto' => [2, 3, 0, 0],
    ];
    expect($procs->keys()->sort()->values()->all())->toBe(collect(array_keys($prototipo))->sort()->values()->all());
    foreach ($prototipo as $id => [$sim, $parcial, $nao, $nv]) {
        expect($procs[$id]['cobertura'])->toBe(['sim' => $sim, 'parcial' => $parcial, 'nao' => $nao, 'nao_verificado' => $nv, 'total' => $sim + $parcial + $nao + $nv]);
        expect(count($procs[$id]['transicoes']))->toBe($sim + $parcial + $nao + $nv);
    }

    // Cada transição diz o estado e onde está no código.
    $iniciar = collect($procs['os-reparo']['transicoes'])->firstWhere('rotulo', 'Iniciar diagnóstico');
    expect($iniciar['cobertura']['estado'])->toBe('sim');
    expect($iniciar['cobertura']['onde'])->toContain('RepairFsmActionController@execute');
    expect(collect($procs['devolucao-compra']['transicoes'])->pluck('cobertura.estado')->countBy()->all())
        ->toBe(['sim' => 2, 'nao_verificado' => 1]);
});

it('UC-FLXT-19 · quem configura grava a margem mínima do orçamento; vazio desliga; fora de 0–100 é recusado', function () {
    if (! \Illuminate\Support\Facades\Schema::hasColumn('fsm_modelos_empresa', 'margem_minima_pct')) {
        $this->markTestSkipped('Coluna fsm_modelos_empresa.margem_minima_pct ausente (migration 2026_10_19_220000).');
    }
    flxtEntrar($this, flxtUsuario(['fluxos.ver', 'fluxos.configurar']));
    flxtPostAplicar($this, ['modelo' => 'cv-pequena']);
    $gravada = fn () => DB::table('fsm_modelos_empresa')->where('business_id', FLXT_BIZ)->value('margem_minima_pct');

    flxtPatch($this, '/fluxos/margem', ['margem_minima' => 27.5])->assertRedirect('/fluxos?aba=empresa');
    expect((float) $gravada())->toBe(27.5);
    expect(flxtGet($this, '/fluxos?aba=empresa')->json('props.margem_minima'))->toEqual(27.5);

    flxtPatch($this, '/fluxos/margem', ['margem_minima' => 120])->assertSessionHasErrors('margem_minima');
    flxtPatch($this, '/fluxos/margem', ['margem_minima' => -1])->assertSessionHasErrors('margem_minima');
    expect((float) $gravada())->toBe(27.5);

    flxtPatch($this, '/fluxos/margem', ['margem_minima' => null])->assertRedirect('/fluxos?aba=empresa');
    expect($gravada())->toBeNull();

    flxtEntrar($this, flxtUsuario(['fluxos.ver']));
    expect(flxtPatch($this, '/fluxos/margem', ['margem_minima' => 10])->getStatusCode())->toBe(403);
    expect($gravada())->toBeNull();
});

it('UC-FLXT-23 · quem configura grava o sinal mínimo da produção; vazio desliga; fora de 0,01–100 é recusado; outra empresa não muda', function () {
    if (! \Illuminate\Support\Facades\Schema::hasColumn('fsm_modelos_empresa', 'sinal_minimo_pct')) {
        $this->markTestSkipped('Coluna fsm_modelos_empresa.sinal_minimo_pct ausente (migration 2026_10_20_120000).');
    }
    // Empresa vizinha com a regra já gravada: o PATCH da 98 não pode tocá-la (Tier 0).
    $vizinha = (int) $this->seededSupportClientTenant()->id;
    DB::table('fsm_modelos_empresa')->updateOrInsert(['business_id' => $vizinha], ['modelo' => 'cv-pequena', 'sinal_minimo_pct' => '40.00']);
    $daVizinha = fn () => DB::table('fsm_modelos_empresa')->where('business_id', $vizinha)->value('sinal_minimo_pct');

    flxtEntrar($this, flxtUsuario(['fluxos.ver', 'fluxos.configurar']));
    flxtPostAplicar($this, ['modelo' => 'cv-pequena']);
    $gravado = fn () => DB::table('fsm_modelos_empresa')->where('business_id', FLXT_BIZ)->value('sinal_minimo_pct');

    // Texto com 2 casas, como a tela manda: chega ao DECIMAL sem passar por float.
    flxtPatch($this, '/fluxos/sinal', ['sinal_minimo' => '33.33'])->assertRedirect('/fluxos?aba=empresa');
    expect((string) $gravado())->toBe('33.33');
    expect(flxtGet($this, '/fluxos?aba=empresa')->json('props.sinal_minimo'))->toBe('33.33');

    foreach (['0', '0.00', '100.01', '-5', 'abc'] as $ruim) {
        flxtPatch($this, '/fluxos/sinal', ['sinal_minimo' => $ruim])->assertSessionHasErrors('sinal_minimo');
    }
    expect((string) $gravado())->toBe('33.33');

    flxtPatch($this, '/fluxos/sinal', ['sinal_minimo' => '100.00'])->assertRedirect('/fluxos?aba=empresa');
    expect((string) $gravado())->toBe('100.00');

    flxtPatch($this, '/fluxos/sinal', ['sinal_minimo' => null])->assertRedirect('/fluxos?aba=empresa');
    expect($gravado())->toBeNull();
    expect(flxtGet($this, '/fluxos?aba=empresa')->json('props.sinal_minimo'))->toBeNull();
    expect((string) $daVizinha())->toBe('40.00');

    flxtEntrar($this, flxtUsuario(['fluxos.ver']));
    expect(flxtPatch($this, '/fluxos/sinal', ['sinal_minimo' => '10.00'])->getStatusCode())->toBe(403);
    expect($gravado())->toBeNull();
});

/** Regra do UC-FLX-14 gravada na etapa do processo de venda do modelo, na empresa dada. */
function flxtRegraBaixa(int $biz, string $processo, string $etapa): ?array
{
    $pid = DB::table('sale_processes')->where('business_id', $biz)->where('key', $processo)->value('id');
    $regras = json_decode((string) DB::table('sale_process_stages')->where('process_id', $pid)->where('key', $etapa)->value('regras_ao_entrar'), true) ?? [];

    return collect($regras)->firstWhere('uc', 'UC-FLX-14');
}

it('UC-FLXT-20 · a regra de baixa de material dos modelos sem OS diz por que não executa e não vira side effect', function () {
    if (! \Illuminate\Support\Facades\Schema::hasTable('fsm_modelos_empresa')) {
        $this->markTestSkipped('Tabela fsm_modelos_empresa ausente (migration do #9492).');
    }
    $aplicar = app(\App\Domain\Fsm\Modelos\AplicarModelo::class);

    foreach ([['balcao', 'flx_balcao_venda', 'aprovado', 'orcamento'], ['brindes', 'flx_brindes_venda', 'producao', 'aprovado']] as [$modelo, $proc, $etapa, $anterior]) {
        $ids = $aplicar->aplicar(FLXT_BIZ, $modelo);
        // Modelo sem processo de produção: não há OS onde o apontamento baixaria o consumido.
        expect(array_keys($ids))->not->toContain('producao');

        $regra = flxtRegraBaixa(FLXT_BIZ, $proc, $etapa);
        expect($regra['estado'])->toBe('planejado')
            ->and(array_key_exists('side_effect', $regra))->toBeFalse()
            ->and($regra['candidato'])->toBe(\App\Domain\Fsm\SideEffects\ConsumirEstoque::class)
            ->and($regra['pendencia'])->toBe(\App\Domain\Fsm\Modelos\CatalogoModelos::PENDENCIAS['UC-FLX-14']);

        // A ação que entra na etapa da regra não carrega a baixa.
        $de = DB::table('sale_process_stages')->where('process_id', $ids['venda'])->where('key', $anterior)->value('id');
        expect(DB::table('sale_stage_actions')->where('stage_id', $de)->where('key', 'avancar')->value('side_effect_class'))->toBeNull();
    }

    // Regra sem motivo conhecido não ganha pendência (UC-FLX-26 do balcão).
    $pid = DB::table('sale_processes')->where('business_id', FLXT_BIZ)->where('key', 'flx_balcao_venda')->value('id');
    $pago = json_decode((string) DB::table('sale_process_stages')->where('process_id', $pid)->where('key', 'pago')->value('regras_ao_entrar'), true);
    expect($pago[0]['uc'])->toBe('UC-FLX-26')
        ->and(array_key_exists('pendencia', $pago[0]))->toBeFalse();
});

it('UC-FLXT-21 · venda finalizada do balcão entra em Aprovado sem segunda baixa de estoque, com a regra ligada ou desligada', function () {
    if (! \Illuminate\Support\Facades\Schema::hasTable('stock_reservations') || ! \Illuminate\Support\Facades\Schema::hasColumn('transactions', 'process_id')) {
        $this->markTestSkipped('Tabelas do motor FSM ausentes.');
    }
    flxtComRegrasConfiguraveis($this);
    $outra = (int) DB::table('business')->where('id', '!=', FLXT_BIZ)->value('id');
    $ids = app(\App\Domain\Fsm\Modelos\AplicarModelo::class)->aplicar(FLXT_BIZ, 'balcao');
    $etapa = fn (string $k) => (int) DB::table('sale_process_stages')->where('process_id', $ids['venda'])->where('key', $k)->value('id');

    $user = flxtUsuario([]);
    $user->assignRole(Role::findByName('vendas.enviar#' . FLXT_BIZ, 'web'));
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user = User::findOrFail($user->id);

    $regras = app(\App\Domain\Fsm\Modelos\RegrasDaEmpresa::class);
    foreach ([true, false] as $ligada) {
        // Balcão, venda: regra 0 = "Aprovado → Baixa o material do estoque (UC-FLX-14)".
        $regras->ligar(FLXT_BIZ, 'venda', 0, $ligada);
        expect(flxtRegraBaixa(FLXT_BIZ, 'flx_balcao_venda', 'aprovado')['ligada'])->toBe($ligada);

        // Venda final: o núcleo já baixou a peça. Uma reserva ativa denuncia ConsumirEstoque se ele rodar.
        $venda = DB::table('transactions')->insertGetId([
            'business_id' => FLXT_BIZ, 'created_by' => $user->id, 'type' => 'sell', 'status' => 'final',
            'payment_status' => 'due', 'invoice_no' => 'FLXT21-' . uniqid(), 'transaction_date' => now(),
            'total_before_tax' => 10, 'final_total' => 10, 'process_id' => $ids['venda'],
            'current_stage_id' => $etapa('orcamento'), 'created_at' => now(), 'updated_at' => now(),
        ]);
        $reserva = fn (int $biz) => DB::table('stock_reservations')->insertGetId([
            'business_id' => $biz, 'transaction_id' => $venda, 'product_id' => 1, 'variation_id' => 1,
            'location_id' => 1, 'qty_reserved' => 3, 'status' => 'active', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $minha = $reserva(FLXT_BIZ);
        $alheia = $reserva($outra);
        $saldo = fn () => (string) DB::table('variation_location_details')->sum('qty_available');
        $saldoAntes = $saldo();

        app(\App\Domain\Fsm\Services\ExecuteStageActionService::class)
            ->execute(\App\Transaction::findOrFail($venda), 'avancar', $user);

        // Controle positivo: a venda entrou em Aprovado.
        expect((int) DB::table('transactions')->where('id', $venda)->value('current_stage_id'))->toBe($etapa('aprovado'));
        // Caminho 1: nenhuma reserva consumida (nem a da outra empresa).
        expect(DB::table('stock_reservations')->where('id', $minha)->value('status'))->toBe('active')
            ->and(DB::table('stock_reservations')->where('id', $alheia)->value('status'))->toBe('active');
        // Caminho 2: o saldo de estoque não mexeu.
        expect($saldo())->toBe($saldoAntes);
    }
});

it('UC-FLXT-22 · quem configura liga e desliga a reserva de material na venda, só na empresa da sessão', function () {
    if (! class_exists(\Modules\Producao\Services\ReservaDeInsumoDaOs::class)) {
        $this->markTestSkipped('Modules/Producao ausente.');
    }
    // Objeto, não variável: o `fn` externo capturaria uma cópia e desligar o pacote não chegaria ao mock.
    $pacote = (object) ['producao' => true];
    $this->partialMock(\App\Utils\ModuleUtil::class, fn ($m) => $m->shouldReceive('hasThePermissionInSubscription')
        ->andReturnUsing(fn ($b, $perm) => $perm === 'producao_module' && $pacote->producao));
    $chave = \Modules\Producao\Services\ReservaDeInsumoDaOs::CHAVE;
    $ajustes = fn (int $biz) => json_decode((string) DB::table('business')->where('id', $biz)->value('common_settings'), true) ?? [];
    $outra = (int) DB::table('business')->where('id', '!=', FLXT_BIZ)->value('id');
    $antesOutra = DB::table('business')->where('id', $outra)->value('common_settings');
    // Uma chave vizinha prova que o interruptor mexe só na dele.
    DB::table('business')->where('id', FLXT_BIZ)->update(['common_settings' => json_encode(['flxt_vizinha' => 'fica'])]);

    flxtEntrar($this, flxtUsuario(['fluxos.ver', 'fluxos.configurar']));
    flxtPostAplicar($this, ['modelo' => 'cv-pequena']);
    expect(flxtGet($this, '/fluxos?aba=empresa')->json('props.reserva_insumo'))->toBeFalse();

    flxtPatch($this, '/fluxos/reserva', ['ligada' => true, 'business_id' => $outra])->assertRedirect('/fluxos?aba=empresa');
    expect($ajustes(FLXT_BIZ))->toBe(['flxt_vizinha' => 'fica', $chave => 1]);
    expect(app(\Modules\Producao\Services\ReservaDeInsumoDaOs::class)->ligada(FLXT_BIZ))->toBeTrue();
    expect(flxtGet($this, '/fluxos?aba=empresa')->json('props.reserva_insumo'))->toBeTrue();
    // O business_id do formulário é ignorado: a outra empresa não mudou.
    expect(DB::table('business')->where('id', $outra)->value('common_settings'))->toBe($antesOutra);

    flxtPatch($this, '/fluxos/reserva', ['ligada' => 'talvez'])->assertSessionHasErrors('ligada');
    expect($ajustes(FLXT_BIZ)[$chave] ?? null)->toBe(1);

    flxtPatch($this, '/fluxos/reserva', ['ligada' => false])->assertRedirect('/fluxos?aba=empresa');
    expect($ajustes(FLXT_BIZ))->toBe(['flxt_vizinha' => 'fica']);

    // Sem fluxos.configurar: 403 e nada muda.
    flxtEntrar($this, flxtUsuario(['fluxos.ver']));
    expect(flxtPatch($this, '/fluxos/reserva', ['ligada' => true])->getStatusCode())->toBe(403);
    expect($ajustes(FLXT_BIZ))->toBe(['flxt_vizinha' => 'fica']);

    // Sem o módulo Produção no pacote: o interruptor some e o PATCH não existe.
    $pacote->producao = false;
    flxtEntrar($this, flxtUsuario(['fluxos.ver', 'fluxos.configurar']));
    expect(flxtGet($this, '/fluxos?aba=empresa')->json('props.reserva_insumo'))->toBeNull();
    expect(flxtPatch($this, '/fluxos/reserva', ['ligada' => true])->getStatusCode())->toBe(404);
    expect($ajustes(FLXT_BIZ))->toBe(['flxt_vizinha' => 'fica']);
});

it('UC-FLXT-24 · quem configura liga e desliga a requisição pelo estoque mínimo; só na empresa da sessão', function () {
    if (! app()->bound(\App\Contracts\Compras\RequisicaoEstoqueMinimo::class)) {
        $this->markTestSkipped('Módulo Compras não carregado: a interface RequisicaoEstoqueMinimo não está ligada.');
    }
    $chave = 'compras_requisicao_estoque_minimo';
    $settings = fn (int $biz) => json_decode((string) DB::table('business')->where('id', $biz)->value('common_settings'), true) ?? [];
    // outra chave de common_settings da empresa sobrevive ao interruptor
    DB::table('business')->where('id', FLXT_BIZ)->update(['common_settings' => json_encode(['flxt_outra' => 'fica'])]);
    $outroAntes = DB::table('business')->where('id', 99)->value('common_settings');

    flxtEntrar($this, flxtUsuario(['fluxos.ver', 'fluxos.configurar']));
    flxtPostAplicar($this, ['modelo' => 'cv-pequena']);
    expect(flxtGet($this, '/fluxos?aba=empresa')->json('props.requisicao_estoque_minimo'))->toBeFalse();

    flxtPatch($this, '/fluxos/requisicao-estoque-minimo', ['ativo' => true])->assertRedirect('/fluxos?aba=empresa');
    expect($settings(FLXT_BIZ)[$chave])->toBe(['ativo' => true]);
    expect($settings(FLXT_BIZ)['flxt_outra'])->toBe('fica');
    expect(flxtGet($this, '/fluxos?aba=empresa')->json('props.requisicao_estoque_minimo'))->toBeTrue();
    expect(DB::table('business')->where('id', 99)->value('common_settings'))->toBe($outroAntes);

    flxtPatch($this, '/fluxos/requisicao-estoque-minimo', ['ativo' => 'talvez'])->assertSessionHasErrors('ativo');
    expect($settings(FLXT_BIZ)[$chave])->toBe(['ativo' => true]);

    flxtPatch($this, '/fluxos/requisicao-estoque-minimo', ['ativo' => false])->assertRedirect('/fluxos?aba=empresa');
    expect($settings(FLXT_BIZ)[$chave])->toBe(['ativo' => false]);

    flxtEntrar($this, flxtUsuario(['fluxos.ver']));
    expect(flxtPatch($this, '/fluxos/requisicao-estoque-minimo', ['ativo' => true])->getStatusCode())->toBe(403);
    expect($settings(FLXT_BIZ)[$chave])->toBe(['ativo' => false]);
});
