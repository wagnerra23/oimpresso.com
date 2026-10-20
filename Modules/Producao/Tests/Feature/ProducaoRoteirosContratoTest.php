<?php

declare(strict_types=1);
// Cobre UC-PCV-13 (Producao.casos.md) e UC-PCV-33 (Producao-celular.casos.md · emenda E03).

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Producao\Entities\Posto;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * Cadastros do PCP — thread 03 + E03 do playbook de Produção (ADR 0431). Os casos vêm do
 * `Producao.casos.md` / `Producao-celular.casos.md`, não do código.
 *
 * Tenant 98 (seededTenant, ADR 0358) contra o 99 (seededSupportClientTenant). NUNCA biz=4.
 * Usuários novos com EXATAMENTE as permissões do caso (usuarioComPermissoes).
 * ⚠️ SKIP em SQLite (schema MySQL do UltimatePOS): leia assertions, não "0 failed" (LC-13).
 */
beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: schema MySQL do UltimatePOS.');
    }
    if (! Schema::hasTable('pcp_roteiro_etapas')) {
        $this->markTestSkipped('Tabelas de cadastro pcp_* ausentes — migrations do Modules/Producao não rodaram.');
    }
    $this->b98 = $this->seededTenant();
    $this->b99 = $this->seededSupportClientTenant();
    $this->g98 = $this->usuarioComPermissoes(['producao.access'], $this->b98);
    $this->g99 = $this->usuarioComPermissoes(['producao.access'], $this->b99);
});

function rotPosto(int $biz, string $codigo): Posto
{
    // SUPERADMIN: fixture grava em duas empresas; o escopo é provado nos asserts, não aqui.
    return Posto::withoutGlobalScopes()->create(['business_id' => $biz, 'codigo' => $codigo.'-'.uniqid(), 'nome' => $codigo,
        'tipo_custo' => 'maquina', 'custo_hora' => '50.00']);
}

/**
 * Troca de usuário como um login novo: limpa a sessão antes. O SetSessionData só preenche
 * `user.business_id` quando a sessão está vazia, então `actingAs` sozinho deixaria a empresa
 * do usuário anterior na sessão e o teste mediria a empresa errada.
 */
function rotComo($teste, $user)
{
    $teste->flushSession();

    return $teste->actingAs($user);
}

/** Prop deferida pedida como o browser pede (X-Inertia + X-Requested-With). */
function rotProps($teste, $user, string $rota, string $componente, string $prop): array
{
    rotComo($teste, $user);
    $versao = app(\App\Http\Middleware\HandleInertiaRequests::class)->version(request());
    $r = $teste->get($rota, ['X-Inertia' => 'true', 'X-Requested-With' => 'XMLHttpRequest', 'X-Inertia-Version' => (string) $versao,
        'X-Inertia-Partial-Data' => $prop, 'X-Inertia-Partial-Component' => $componente]);
    $r->assertOk();

    return (array) $r->json('props.'.$prop);
}

/** O roteiro da fachada do protótipo: 2 trilhas que se juntam em "Montagem na fábrica". */
function rotFachada(string $nome, array $mudar = []): array
{
    $bloco = array_merge(['paralelo' => [
        [['nome' => 'Estrutura metálica', 'horas' => 6], ['nome' => 'Corte CNC do ACM', 'horas' => 1]],
        [['nome' => 'Adesivo de aplicação', 'horas' => 1], ['nome' => 'Laminação', 'horas' => 0.5, 'opcional_por' => 'com laminação']],
    ], 'junta' => 'Montagem na fábrica'], $mudar);

    return ['familia' => 'Projeto com instalação', 'nome' => $nome, 'etapas' => [
        ['nome' => 'Projeto e medição', 'horas' => 2], $bloco,
        ['nome' => 'Montagem na fábrica', 'horas' => 2], ['nome' => 'Instalação em campo', 'horas' => 6],
    ]];
}

it('UC-PCV-13 · até 2 trilhas que se juntam numa etapa nomeada; 3ª trilha ou trilha sem junção é recusada', function () {
    $this->actingAs($this->g98);
    $nome = 'Fachada '.uniqid();

    // Caso discriminante: 2 trilhas + junção nomeada salva, com a etapa opcional ligada pelo orçamento.
    $this->post('/producao/roteiros', rotFachada($nome))->assertSessionHasNoErrors();
    $id = (int) DB::table('pcp_roteiros')->where('business_id', $this->b98->id)->where('nome', $nome)->value('id');
    $etapas = DB::table('pcp_roteiro_etapas')->where('roteiro_id', $id)->get();
    expect($id)->toBeGreaterThan(0)
        ->and($etapas)->toHaveCount(7)
        ->and($etapas->where('trilha', 1)->count())->toBe(2)
        ->and($etapas->where('trilha', 2)->count())->toBe(2)
        ->and($etapas->where('trilha', '>', 0)->pluck('junta')->unique()->values()->all())->toBe(['Montagem na fábrica'])
        ->and($etapas->firstWhere('nome', 'Laminação')->opcional_por)->toBe('com laminação');

    // Controle negativo — mesmo payload, só muda a regra: nada a mais é gravado.
    $antes = DB::table('pcp_roteiros')->count();
    $terceira = rotFachada('Tres '.uniqid());
    $terceira['etapas'][1]['paralelo'][] = [['nome' => 'Pintura', 'horas' => 1]];
    $this->post('/producao/roteiros', $terceira)->assertSessionHasErrors('etapas.1.paralelo');
    $this->post('/producao/roteiros', rotFachada('SemJunta '.uniqid(), ['junta' => '']))->assertSessionHasErrors('etapas.1.junta');
    $this->post('/producao/roteiros', rotFachada('JuntaErrada '.uniqid(), ['junta' => 'Outra etapa']))->assertSessionHasErrors('etapas.1.junta');
    expect(DB::table('pcp_roteiros')->count())->toBe($antes);

    // Tenant: o roteiro da 98 não aparece para a 99, e a 99 não o altera.
    $daOutra = collect(rotProps($this, $this->g99, '/producao/roteiros', 'Producao/Roteiros/Index', 'roteiros'))->pluck('id');
    expect($daOutra)->not->toContain($id);
    rotComo($this, $this->g99)->put("/producao/roteiros/$id", rotFachada('Invadido'))->assertNotFound();
    expect(DB::table('pcp_roteiros')->where('id', $id)->value('nome'))->toBe($nome);

    // A 98 lê o roteiro no mesmo formato que salvou (o bloco volta com as 2 trilhas e a junção).
    $meu = collect(rotProps($this, $this->g98, '/producao/roteiros', 'Producao/Roteiros/Index', 'roteiros'))->firstWhere('id', $id);
    expect($meu['etapas'][1]['junta'])->toBe('Montagem na fábrica')
        ->and($meu['etapas'][1]['paralelo'])->toHaveCount(2);
});

it('UC-PCV-33 · motivos de pausa vêm do cadastro do posto; outro posto mostra os dele', function () {
    $this->actingAs($this->g98);

    // Posto novo nasce com o padrão sugerido (E03).
    $codigo = 'ROLAND-'.uniqid();
    $this->post('/producao/postos', ['codigo' => $codigo, 'nome' => 'Roland', 'tipo_custo' => 'maquina', 'custo_hora' => 80])
        ->assertSessionHasNoErrors();
    $roland = (int) DB::table('pcp_postos')->where('business_id', $this->b98->id)->where('codigo', $codigo)->value('id');
    expect(DB::table('pcp_motivos')->where('posto_id', $roland)->where('tipo', 'pausa')->count())->toBe(6);

    // O gestor fecha a lista da Roland em 6 motivos próprios; a Laminadora fica com 2.
    $daRoland = ['Troca de bobina', 'Setup', 'Falta de material', 'Manutenção', 'Dúvida na arte', 'Limpeza da cabeça'];
    $lamin = rotPosto($this->b98->id, 'LAMIN');
    $this->put("/producao/postos/$roland/motivos", ['tipo' => 'pausa', 'nomes' => $daRoland])->assertSessionHasNoErrors();
    $this->put("/producao/postos/{$lamin->id}/motivos", ['tipo' => 'pausa', 'nomes' => ['Bolha', 'Troca de rolo']])->assertSessionHasNoErrors();

    $postos = collect(rotProps($this, $this->g98, '/producao/postos', 'Producao/Postos/Index', 'postos'))->keyBy('id');
    expect($postos[$roland]['motivos']['pausa'])->toBe($daRoland)
        ->and($postos[$lamin->id]['motivos']['pausa'])->toBe(['Bolha', 'Troca de rolo'])
        ->and($postos[$roland]['motivos']['refugo'])->toHaveCount(5);

    // Controle negativo — a 99 não vê nem altera os motivos dos postos da 98.
    $daOutra = collect(rotProps($this, $this->g99, '/producao/postos', 'Producao/Postos/Index', 'postos'))->pluck('id');
    expect($daOutra)->not->toContain($roland)->not->toContain($lamin->id);
    rotComo($this, $this->g99)->put("/producao/postos/$roland/motivos", ['tipo' => 'pausa', 'nomes' => ['Invasão']])->assertNotFound();
    expect(DB::table('pcp_motivos')->where('posto_id', $roland)->where('tipo', 'pausa')->orderBy('ordem')->pluck('nome')->all())->toBe($daRoland);
});

it('UC-PCCH-02 · E03 · custo-hora da pessoa só aparece e só grava com producao.desempenho.ver', function () {
    $operador = $this->usuarioComPermissoes([], $this->b98);
    $gestor = $this->usuarioComPermissoes(['producao.access', 'producao.desempenho.ver'], $this->b98);

    // Sem a permissão: a prop vem vazia e a gravação é 403 — nada é gravado.
    $this->actingAs($this->g98)->put("/producao/colaboradores/{$operador->id}/custo-hora", ['custo_hora' => 42])->assertForbidden();
    expect(DB::table('pcp_colaborador_custos')->where('user_id', $operador->id)->exists())->toBeFalse()
        ->and(rotProps($this, $this->g98, '/producao/postos', 'Producao/Postos/Index', 'colaboradores'))->toBe([]);

    // Com a permissão: grava e lê o valor do colaborador da própria empresa.
    rotComo($this, $gestor)->put("/producao/colaboradores/{$operador->id}/custo-hora", ['custo_hora' => '42.50'])->assertSessionHasNoErrors();
    $lista = collect(rotProps($this, $gestor, '/producao/postos', 'Producao/Postos/Index', 'colaboradores'))->keyBy('id');
    expect((string) $lista[$operador->id]['custo_hora'])->toBe('42.50');

    // Colaborador de outra empresa é recusado, mesmo com a permissão.
    rotComo($this, $gestor)->put("/producao/colaboradores/{$this->g99->id}/custo-hora", ['custo_hora' => 10])->assertSessionHasErrors('user_id');
    expect(DB::table('pcp_colaborador_custos')->where('user_id', $this->g99->id)->exists())->toBeFalse();
});

it('E03 · veículo é posto de máquina com placa e custo por km; ferramenta tem QR único por empresa', function () {
    $this->actingAs($this->g98);
    $codigo = 'VAN-'.uniqid();
    $this->post('/producao/postos', ['codigo' => $codigo, 'nome' => 'Van Fiorino', 'tipo_custo' => 'pessoa', 'veiculo' => true,
        'placa' => 'ABC1D23', 'custo_hora' => 35, 'custo_km' => '1.20'])->assertSessionHasNoErrors();
    $van = DB::table('pcp_postos')->where('business_id', $this->b98->id)->where('codigo', $codigo)->first();
    expect($van->tipo_custo)->toBe('maquina')->and($van->placa)->toBe('ABC1D23')->and((string) $van->custo_km)->toBe('1.20');
    $this->post('/producao/postos', ['codigo' => 'VAN2-'.uniqid(), 'nome' => 'Sem placa', 'tipo_custo' => 'maquina', 'veiculo' => true,
        'custo_hora' => 35])->assertSessionHasErrors('placa');

    $qr = 'FER-'.uniqid();
    $this->post('/producao/ferramentas', ['codigo_qr' => $qr, 'nome' => 'Furadeira'])->assertSessionHasNoErrors();
    $this->post('/producao/ferramentas', ['codigo_qr' => $qr, 'nome' => 'Outra'])->assertSessionHasErrors('codigo_qr');
    // O mesmo código vale em outra empresa: o QR é único por empresa, não no sistema.
    rotComo($this, $this->g99)->post('/producao/ferramentas', ['codigo_qr' => $qr, 'nome' => 'Furadeira da 99'])->assertSessionHasNoErrors();
    expect(DB::table('pcp_ferramentas')->where('codigo_qr', $qr)->pluck('business_id')->sort()->values()->all())
        ->toBe([(int) $this->b98->id, (int) $this->b99->id]);
});
