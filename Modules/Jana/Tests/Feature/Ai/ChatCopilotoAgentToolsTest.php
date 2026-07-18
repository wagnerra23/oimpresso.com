<?php

declare(strict_types=1);

// @covers-us US-COPI-141 — tool use READ-ONLY no chat: contrato de que o
// ChatCopilotoAgent declara as 5 tools do BriefDiarioAgent com o business_id
// vindo da conversa (Tier 0 mecânico), que a flag OFF mantém o pipeline
// byte-idêntico ao legado, e que o prompt nunca promete ferramenta que o SDK
// não mandou.
// @covers-us US-COPI-142 — o flip da flag: o caso "flag ON → 5 tools" é
// exatamente o comportamento que o flip ativa em prod (a flag OFF↔ON é o único
// grau de liberdade do flip). O smoke real prod/homolog fecha o resto do DoD.

use Laravel\Ai\Contracts\HasTools;
use Modules\Jana\Ai\Agents\ChatCopilotoAgent;
use Modules\Jana\Ai\Tools\BriefDiario\VendasPeriodoTool;
use Modules\Jana\Entities\Conversa;

uses(Tests\TestCase::class);

/**
 * R-COPI-141 — GUARD tests pro tool use READ-ONLY no chat (US-COPI-141, ADR 0141).
 *
 * O que estes testes defendem (contrato, não implementação):
 *  001. Flag OFF (default) → zero tools. O SDK omite a chave `tools` do request
 *       (BuildsTextRequests: `if (filled($tools))`) → pipeline byte-idêntico ao
 *       legado. É a promessa da ADR 0245 ("prod espera") virada teste.
 *  002. Flag ON + conversa com business → as 5 tools READ-ONLY do BriefDiarioAgent.
 *  003. Tier 0 FIAÇÃO — a tool recebe o business_id DA CONVERSA, não outro.
 *       (Que a tool respeita o próprio businessId já é provado por
 *       R-COPI-202-003; aqui o contrato é o repasse.)
 *  004. Tier 0 FAIL-SAFE — conversa sem business_id → zero tools. Sem tenant
 *       provado não existe consulta: tool com tenant chutado é vazamento.
 *  005. O prompt não promete ferramenta que o SDK não mandou (anti-mentira).
 *
 * Sem schema/DB de propósito: `tools()` é função pura de (flag, conversa). Os
 * testes que precisam de dado real vivem em BriefDiarioAgentTest.
 *
 * @see memory/decisions/0141-agents-tool-use-pattern-claude-code.md
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 * @see memory/decisions/0245-jana-advisor-modo-consultor-clarify.md (flag env-driven)
 */

/** ADR 0101 — biz=1 (dogfooding), NUNCA biz=4 (ROTA LIVRE, cliente real). */
const R_COPI_141_BIZ = 1;

const R_COPI_141_BIZ_ALHEIO = 99;

function chatAgentComBusiness(?int $businessId): ChatCopilotoAgent
{
    return new ChatCopilotoAgent(new Conversa(['business_id' => $businessId]));
}

it('R-COPI-141-001 — flag OFF (default) → zero tools, pipeline idêntico ao legado', function () {
    // Não seta a flag: exercita o DEFAULT de verdade, que é o que roda em prod.
    $tools = iterator_to_array(chatAgentComBusiness(R_COPI_141_BIZ)->tools(), false);

    expect($tools)->toBe([]);
});

it('R-COPI-141-002 — flag ON → declara as 5 tools READ-ONLY', function () {
    config()->set('copiloto.chat_tools.enabled', true);

    $tools = iterator_to_array(chatAgentComBusiness(R_COPI_141_BIZ)->tools(), false);

    expect($tools)->toHaveCount(5)
        ->and(array_map(fn ($t) => class_basename($t), $tools))
        ->toEqualCanonicalizing([
            'VendasPeriodoTool',
            'InadimplenciaTool',
            'TicketsTopTool',
            'NfeStatusTool',
            'OportunidadesTool',
        ]);
});

it('R-COPI-141-003 — Tier 0: a tool recebe o business_id DA CONVERSA', function () {
    config()->set('copiloto.chat_tools.enabled', true);

    $tools = iterator_to_array(chatAgentComBusiness(R_COPI_141_BIZ)->tools(), false);

    // Reflection porque o businessId é readonly private de propósito (ADR 0141 —
    // o LLM não tem como injetar business). O contrato aqui é o REPASSE: se
    // alguém trocar a fonte por auth()/session()/param do LLM, isto quebra.
    foreach ($tools as $tool) {
        $prop = new ReflectionProperty($tool, 'businessId');
        $prop->setAccessible(true);

        expect($prop->getValue($tool))
            ->toBe(R_COPI_141_BIZ, class_basename($tool) . ' recebeu business errado');
    }
});

it('R-COPI-141-003b — Tier 0: conversa de outro business gera tool daquele business, nunca vazamento cruzado', function () {
    config()->set('copiloto.chat_tools.enabled', true);

    $tools = iterator_to_array(chatAgentComBusiness(R_COPI_141_BIZ_ALHEIO)->tools(), false);

    $prop = new ReflectionProperty($tools[0], 'businessId');
    $prop->setAccessible(true);

    expect($tools[0])->toBeInstanceOf(VendasPeriodoTool::class)
        ->and($prop->getValue($tools[0]))->toBe(R_COPI_141_BIZ_ALHEIO)
        ->and($prop->getValue($tools[0]))->not->toBe(R_COPI_141_BIZ);
});

it('R-COPI-141-004 — Tier 0 fail-safe: conversa sem business_id → zero tools', function () {
    config()->set('copiloto.chat_tools.enabled', true);

    $tools = iterator_to_array(chatAgentComBusiness(null)->tools(), false);

    // Sem tenant provado é melhor a Jana não consultar nada do que consultar
    // o business errado.
    expect($tools)->toBe([]);
});

it('R-COPI-141-005 — o prompt só promete ferramenta quando o SDK realmente vai mandar', function () {
    $semTools = chatAgentComBusiness(R_COPI_141_BIZ);
    expect((string) $semTools->instructions())->not->toContain('FERRAMENTAS DE CONSULTA');

    config()->set('copiloto.chat_tools.enabled', true);
    $comTools = chatAgentComBusiness(R_COPI_141_BIZ);
    expect((string) $comTools->instructions())->toContain('FERRAMENTAS DE CONSULTA');

    // Conversa sem business: flag ON mas tools() vazio → o prompt NÃO pode
    // prometer consulta que não existe.
    $semBusiness = chatAgentComBusiness(null);
    expect((string) $semBusiness->instructions())->not->toContain('FERRAMENTAS DE CONSULTA');
});

it('R-COPI-141-006 — ChatCopilotoAgent declara o contrato HasTools', function () {
    expect(chatAgentComBusiness(R_COPI_141_BIZ))->toBeInstanceOf(HasTools::class);
});
