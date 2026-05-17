<?php

declare(strict_types=1);

namespace Modules\Jana\Ai\Agents;

use Laravel\Ai\Attributes\Model;
use Laravel\Ai\Attributes\Provider;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Promptable;
use Stringable;

/**
 * SaleInsightAgent — Cowork KB-9.75 Sells Onda 2.5 R2 IA real (substitui stub).
 *
 * Agent leve da Camada A (laravel/ai, ADR 0035) que gera insight one-shot
 * sobre uma venda em 3 modos: summary / history / suggest.
 *
 * Recebe contexto pré-buscado (cliente, itens, total, payment, history quando
 * aplicável) como string formatada no constructor — NÃO usa tools, NÃO faz
 * query DB. Backend (SellController::aiAsk) monta contexto + invoca o agent.
 * Single-shot = custo previsível + zero risco loop.
 *
 * Modelo: gpt-4o-mini (Brain A barato, ADR 0035). Custo estimado:
 *   - Input: ~400-800 tokens (system 300 + contexto venda 100-500)
 *   - Output: ~80-200 tokens (texto curto, 2-3 frases ou template parseado)
 *   - gpt-4o-mini: $0.15/M input + $0.60/M output ≈ $0.0003/call ≈ R$ [redacted Tier 0]
 *   - 5k vendas/mês com IA = ~R$ [redacted Tier 0] — desprezível.
 *
 * Pattern copiado de KbAnswerAgent.php (sibling). Failover laravel/ai built-in.
 *
 * @see Modules/Jana/Ai/Agents/KbAnswerAgent.php (template)
 * @see app/Http/Controllers/SellController.php::aiAsk (callsite)
 * @see memory/decisions/0035-stack-ai-canonica-wagner-2026-04-26.md
 */
#[Provider('openai')]
#[Model('gpt-4o-mini')]
class SaleInsightAgent implements Agent
{
    use Promptable;

    public function __construct(
        public readonly string $mode,
        public readonly string $contextoVenda,
    ) {}

    public function instructions(): Stringable|string
    {
        return match ($this->mode) {
            'summary' => <<<PROMPT
            Você é Jana, copiloto IA do oimpresso (ERP modular Laravel + Inertia).

            TAREFA: Resumir a venda dada em 2-3 frases curtas pra um colega
            entender no meio do dia sem ler tudo. PT-BR direto, sem corporativês.
            Inclua: cliente, valor total formatado, item/itens principal(is),
            e status do pagamento.

            FORMATO: prosa fluida 2-3 frases. NÃO use bullets, NÃO use cabeçalhos.

            CONTEXTO DA VENDA:
            {$this->contextoVenda}
            PROMPT,

            'history' => <<<PROMPT
            Você é Jana, copiloto IA do oimpresso (ERP modular Laravel + Inertia).

            TAREFA: Resumir o histórico do cliente desta venda em 2-3 frases.
            Inclua: quantas vendas anteriores, soma total, última data se
            disponível, e classificação do nível de relacionamento (primeira
            venda / em desenvolvimento / recorrente VIP / inativo retornando).

            FORMATO: prosa fluida 2-3 frases PT-BR. Sem bullets.
            Se for primeira venda: focar em "boa oportunidade pra atenção
            extra" e sugerir captura de email/whatsapp se faltarem.

            CONTEXTO:
            {$this->contextoVenda}
            PROMPT,

            'suggest' => <<<PROMPT
            Você é Jana, copiloto IA do oimpresso (ERP modular Laravel + Inertia).

            TAREFA: Sugerir UM produto complementar pra oferta cruzada baseado
            no(s) item(ns) da venda dada.

            FORMATO OBRIGATÓRIO (markdown EXATO, NÃO desvie da estrutura):

            PRODUTO: <nome do produto sugerido, curto e específico>
            PREÇO: <faixa estimada em R\$ formato pt-BR (ex: R\$ 50-150)>
            PORQUE: <1-2 frases explicando o motivo da sugestão considerando
            o que o cliente já comprou e o ticket médio da venda atual>

            CONTEXTO:
            {$this->contextoVenda}
            PROMPT,

            default => <<<PROMPT
            Mode inválido: '{$this->mode}'. Esperado: summary | history | suggest.
            PROMPT,
        };
    }
}
