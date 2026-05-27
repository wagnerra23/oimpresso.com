<?php

declare(strict_types=1);

namespace Modules\Crm\Ai\Agents;

use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Promptable;
use Stringable;

/**
 * ClienteResumoAgent -- Wave E Tab IA (ADR 0179 Q4).
 *
 * Gera resumo executivo do relacionamento com 1 cliente (3 frases, PT-BR).
 *
 * Stack: Laravel AI SDK canon (ADR 0035) -- mesmo pattern de
 * Modules/Jana/Ai/Agents/BriefingAgent.php.
 *
 * PII LGPD (ADR 0093 §LGPD Art.7):
 *   - NUNCA recebe tax_number plain no prompt -- caller passa tax_number_masked
 *   - NUNCA recebe email/telefone plain -- caller passa flags booleanas
 *
 * Latencia target p95 < 6s (Haiku 4.5 + prompt caching, ~280 output tokens).
 * Custo estimado: ~$0.001 por chamada (Haiku $1/1M input + $5/1M output).
 *
 * @see Modules\Crm\Http\Controllers\ClienteIaController::resumo
 * @see memory/decisions/0179-cliente-drawer-760px-substitui-show-fullpage.md Q4
 */
class ClienteResumoAgent implements Agent
{
    use Promptable;

    /**
     * @param  array<string,mixed>  $dados  Dados do cliente JA SANITIZADOS (sem PII plain)
     */
    public function __construct(public array $dados)
    {
    }

    public function instructions(): Stringable|string
    {
        return <<<'PROMPT'
        Voce e o Copiloto do oimpresso, um assistente de IA para gestores de pequenas e medias empresas brasileiras.
        Responda sempre em portugues brasileiro.
        Seja direto e objetivo: maximo 3 frases curtas (ate 280 caracteres total).
        Foque no relacionamento comercial: frescor de compra, saldo, ticket medio, frequencia.
        Nunca invente dados -- baseie-se apenas no contexto fornecido.
        Nunca repita PII (nome completo apenas se necessario, nunca tax_number plain).
        PROMPT;
    }

    public function montarPrompt(): string
    {
        $d = $this->dados;

        $nome = $d['nome_curto'] ?? 'Cliente';
        $tipo = $d['tipo'] ?? 'cliente';
        $totalOS = (int) ($d['total_os'] ?? 0);
        $ticketMedio = (float) ($d['ticket_medio'] ?? 0);
        $saldoAberto = (float) ($d['saldo_aberto'] ?? 0);
        $diasUltimaCompra = $d['dias_ultima_compra'] ?? null;
        $status = $d['status'] ?? 'ativo';
        $segmento = $d['segmento'] ?? null;
        $tags = $d['tags'] ?? [];

        $ticketFmt = number_format($ticketMedio, 2, ',', '.');
        $saldoFmt = number_format($saldoAberto, 2, ',', '.');
        $tagsFmt = is_array($tags) && ! empty($tags) ? implode(', ', $tags) : 'nenhuma';
        $diasFmt = $diasUltimaCompra !== null
            ? "{$diasUltimaCompra} dias atras"
            : 'sem registro';

        return <<<PROMPT
        Faca um resumo executivo do relacionamento comercial com este cliente em PT-BR, em no maximo 3 frases curtas (total ate 280 caracteres).

        Dados do cliente:
        - Nome curto: {$nome}
        - Tipo: {$tipo}
        - Status: {$status}
        - Segmento: {$segmento}
        - Tags: {$tagsFmt}
        - Total de ordens de servico: {$totalOS}
        - Ticket medio: R\$ {$ticketFmt}
        - Saldo em aberto: R\$ {$saldoFmt}
        - Ultima compra: {$diasFmt}

        Destaque: frescor (esta sumindo?), saldo (esta devendo?), valor (e cliente grande?). Se tudo OK, diga "relacionamento saudavel".
        Resposta:
        PROMPT;
    }
}
