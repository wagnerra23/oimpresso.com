<?php

use Modules\Copiloto\Ai\Agents\ChatCopilotoAgent;
use Modules\Copiloto\Entities\Conversa;
use Modules\Copiloto\Support\ContextoNegocio;

/**
 * Regressão MEM-HOT-2 (ADR 0047, fix Caminho A do ADR 0046).
 *
 * Antes: ChatCopilotoAgent::instructions() era só prompt genérico — agent
 * não sabia faturamento/clientes/metas do negócio. Larissa pergunta
 * "qual meu faturamento" → "preciso que você me informe o período exato".
 *
 * Agora: instructions() recebe ContextoNegocio opcional e formata bloco compacto
 * com EMPRESA / FATURAMENTO 90d / CLIENTES / METAS ATIVAS.
 *
 * BC-compat: ChatCopilotoAgent($conv) sem ctx mantém comportamento anterior.
 */

function fakeConversa(): Conversa
{
    // Conversa não-persistida só pra preencher o construtor; Pest com TestCase
    // genérico não bate em DB (não usamos ->mensagens()).
    $c = new Conversa();
    $c->id = 1;
    $c->business_id = 4;
    $c->user_id = 9;
    return $c;
}

function ctxLarissa(): ContextoNegocio
{
    return new ContextoNegocio(
        businessId: 4,
        businessName: 'ROTA LIVRE',
        // MEM-FAT-1 — 3 ângulos por mês (bruto / líquido / caixa)
        faturamento90d: [
            ['mes' => '2026-02', 'valor' => 78450.00, 'bruto' => 78450.00, 'liquido' => 77000.00, 'caixa' => 76500.00],
            ['mes' => '2026-03', 'valor' => 38215.07, 'bruto' => 38215.07, 'liquido' => 37518.47, 'caixa' => 35440.25],
            ['mes' => '2026-04', 'valor' => 31513.29, 'bruto' => 31513.29, 'liquido' => 31513.29, 'caixa' => 28100.00],
        ],
        clientesAtivos: 3,
        modulosAtivos: ['Copiloto', 'Financeiro'],
        metasAtivas: [
            ['nome' => 'Faturamento mensal', 'valor_alvo' => 80000.0, 'realizado' => 31513.29],
        ],
        observacoes: null,
    );
}

it('BC-compat: ChatCopilotoAgent sem ctx mantém prompt genérico', function () {
    $agent = new ChatCopilotoAgent(fakeConversa());

    $prompt = (string) $agent->instructions();

    expect($prompt)->toContain('Você é o Copiloto do oimpresso');
    expect($prompt)->not->toContain('CONTEXTO DO NEGÓCIO');
    expect($prompt)->not->toContain('EMPRESA');
});

it('com ctx Larissa, instructions inclui empresa + faturamento (3 ângulos) + cliente + metas', function () {
    $agent = new ChatCopilotoAgent(fakeConversa(), '', ctxLarissa());

    $prompt = (string) $agent->instructions();

    expect($prompt)->toContain('CONTEXTO DO NEGÓCIO');
    expect($prompt)->toContain('EMPRESA: ROTA LIVRE (id 4)');
    expect($prompt)->toContain('CLIENTES ATIVOS: 3');
    expect($prompt)->toContain('FATURAMENTO ÚLTIMOS 90 DIAS (3 ângulos por mês)');
    // MEM-FAT-1 — 3 ângulos distintos pra LLM responder corretamente
    expect($prompt)->toContain('BRUTO');
    expect($prompt)->toContain('LÍQUIDO');
    expect($prompt)->toContain('CAIXA');
    // Marcador de mês (2026-03 com 3 valores distintos: bruto/líquido/caixa)
    expect($prompt)->toContain('2026-03: bruto R$ 38.215,07 · líquido R$ 37.518,47 · caixa R$ 35.440,25');
    expect($prompt)->toContain('METAS ATIVAS');
    expect($prompt)->toContain('Faturamento mensal: alvo R$ 80.000,00 / realizado R$ 31.513,29');
    expect($prompt)->toContain('MÓDULOS ATIVOS: Copiloto, Financeiro');
});

it('MEM-FAT-1 — quando bruto≠líquido, o LLM vê 3 números distintos (não confunde)', function () {
    $ctx = new ContextoNegocio(
        businessId: 4,
        businessName: 'TESTE',
        faturamento90d: [
            ['mes' => '2026-03', 'valor' => 38215.07, 'bruto' => 38215.07, 'liquido' => 37518.47, 'caixa' => 35440.25],
        ],
        clientesAtivos: 0,
        modulosAtivos: [],
        metasAtivas: [],
    );

    $agent = new ChatCopilotoAgent(fakeConversa(), '', $ctx);
    $prompt = (string) $agent->instructions();

    // Os 3 valores devem aparecer literalmente — LLM NÃO pode reportar bruto pra "caixa"
    expect($prompt)->toContain('R$ 38.215,07'); // bruto
    expect($prompt)->toContain('R$ 37.518,47'); // líquido
    expect($prompt)->toContain('R$ 35.440,25'); // caixa
    // Glossário no prompt deixa claro o que é cada um
    expect($prompt)->toContain('BRUTO    = total vendido');
    expect($prompt)->toContain('LÍQUIDO  = bruto menos devoluções');
    expect($prompt)->toContain('CAIXA    = pagamentos efetivamente recebidos');
});

it('MEM-FAT-1 BC-compat — registro antigo só com `valor` ainda funciona (fallback bruto)', function () {
    $ctx = new ContextoNegocio(
        businessId: 4,
        businessName: 'LEGADO',
        // Shape antigo (sem bruto/liquido/caixa) — não deveria existir mais em prod,
        // mas garante que cache stale ou fixture velho não quebra
        faturamento90d: [
            ['mes' => '2026-01', 'valor' => 4140.82],
        ],
        clientesAtivos: 0,
        modulosAtivos: [],
        metasAtivas: [],
    );

    $agent = new ChatCopilotoAgent(fakeConversa(), '', $ctx);
    $prompt = (string) $agent->instructions();

    // Fallback: bruto=liquido=caixa=valor (todos iguais ao único número disponível)
    expect($prompt)->toContain('2026-01: bruto R$ 4.140,82 · líquido R$ 4.140,82 · caixa R$ 4.140,82');
});

it('com ctx + memoria recall, ambos aparecem (ordem: base → ctx → memoria)', function () {
    $memoria = "Você lembra dos seguintes fatos sobre este usuário/business:\n- Larissa quer R$80mil/mês\n";
    $agent = new ChatCopilotoAgent(fakeConversa(), $memoria, ctxLarissa());

    $prompt = (string) $agent->instructions();

    $posBase    = strpos($prompt, 'Você é o Copiloto');
    $posCtx     = strpos($prompt, 'CONTEXTO DO NEGÓCIO');
    $posMemoria = strpos($prompt, 'Você lembra dos seguintes fatos');

    expect($posBase)->toBeLessThan($posCtx);
    expect($posCtx)->toBeLessThan($posMemoria);
    expect($prompt)->toContain('Larissa quer R$80mil/mês');
});

it('ctx com businessId null (plataforma) NÃO mostra "(id ...)"', function () {
    $ctx = new ContextoNegocio(
        businessId: null,
        businessName: 'oimpresso (plataforma)',
        faturamento90d: [],
        clientesAtivos: 0,
        modulosAtivos: [],
        metasAtivas: [],
    );

    $agent = new ChatCopilotoAgent(fakeConversa(), '', $ctx);
    $prompt = (string) $agent->instructions();

    expect($prompt)->toContain('EMPRESA: oimpresso (plataforma)');
    expect($prompt)->not->toContain('id ');
});

it('ctx com seções vazias pula linhas (token economy)', function () {
    $ctx = new ContextoNegocio(
        businessId: 4,
        businessName: 'TESTE',
        faturamento90d: [],
        clientesAtivos: 0, // pula
        modulosAtivos: [], // pula
        metasAtivas: [],   // pula
    );

    $agent = new ChatCopilotoAgent(fakeConversa(), '', $ctx);
    $prompt = (string) $agent->instructions();

    expect($prompt)->toContain('EMPRESA: TESTE');
    expect($prompt)->not->toContain('CLIENTES ATIVOS');
    expect($prompt)->not->toContain('FATURAMENTO');
    expect($prompt)->not->toContain('METAS ATIVAS');
    expect($prompt)->not->toContain('MÓDULOS ATIVOS');
});

it('ctx com observacoes inclui campo no prompt', function () {
    $ctx = new ContextoNegocio(
        businessId: 4,
        businessName: 'ROTA LIVRE',
        faturamento90d: [],
        clientesAtivos: 0,
        modulosAtivos: [],
        metasAtivas: [],
        observacoes: 'Cliente prefere reportes mensais às sextas',
    );

    $agent = new ChatCopilotoAgent(fakeConversa(), '', $ctx);
    $prompt = (string) $agent->instructions();

    expect($prompt)->toContain('OBSERVAÇÕES: Cliente prefere reportes mensais às sextas');
});
