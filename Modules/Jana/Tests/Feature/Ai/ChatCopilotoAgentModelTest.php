<?php

declare(strict_types=1);

// @covers-us US-COPI-143 — modelo do chat env-driven: ChatCopilotoAgent::model()
// lê copiloto.chat_model (env JANA_CHAT_MODEL). Sem config → null (default do
// provider = legado gpt-4o-mini); setado → liga o modelo forte SÓ neste agent,
// sem arrastar os agents batch internos que herdam o default global.

use Modules\Jana\Ai\Agents\ChatCopilotoAgent;
use Modules\Jana\Entities\Conversa;

uses(Tests\TestCase::class);

/**
 * R-COPI-143 — GUARD tests pro modelo env-driven do chat (US-COPI-143).
 *
 * Não faz chamada de LLM — model() é função pura de config. O ganho de qualidade
 * do modelo forte vs mini é medido por eval (US-COPI-137), não por unit test.
 *
 * @see memory/decisions/0245-jana-advisor-modo-consultor-clarify.md (padrão env-driven)
 */
function chatAgentModel(): ChatCopilotoAgent
{
    return new ChatCopilotoAgent(new Conversa(['business_id' => 1]));
}

it('R-COPI-143-001 — sem config → model() é null (cai no default do provider, legado)', function () {
    config()->set('copiloto.chat_model', null);

    expect(chatAgentModel()->model())->toBeNull();
});

it('R-COPI-143-002 — string vazia → null (não força "", que quebraria o request)', function () {
    config()->set('copiloto.chat_model', '');

    expect(chatAgentModel()->model())->toBeNull();
});

it('R-COPI-143-003 — JANA_CHAT_MODEL=gpt-4o → model() devolve gpt-4o (liga só o chat)', function () {
    config()->set('copiloto.chat_model', 'gpt-4o');

    expect(chatAgentModel()->model())->toBe('gpt-4o');
});
