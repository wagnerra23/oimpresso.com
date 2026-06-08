<?php

declare(strict_types=1);

namespace Modules\Crm\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;
use Stringable;

/**
 * ClienteProximaAcaoAgent -- Wave E Tab IA (ADR 0179 Q4).
 *
 * Propoe a proxima acao concreta que o gestor deve tomar com este cliente.
 * Output JSON: acao (titulo) + urgencia (enum) + justificativa.
 *
 * Latencia p95 < 6s. Custo ~$0.001 por chamada.
 *
 * @see memory/decisions/0179-cliente-drawer-760px-substitui-show-fullpage.md Q4
 */
class ClienteProximaAcaoAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    public function __construct(public array $dados)
    {
    }

    public function instructions(): Stringable|string
    {
        return <<<'PROMPT'
        Voce e o Copiloto do oimpresso, um assistente de IA para gestores de PMEs brasileiras.
        Responda sempre em portugues brasileiro.
        Proponha 1 acao concreta e acionavel (nao generica). Maximo 60 caracteres no titulo.
        Urgencia: "alta" se ha saldo aberto vencido OU cliente esfriou >180d.
                  "media" se cliente saudavel mas tem oportunidade clara.
                  "baixa" se manutencao de rotina.
        Nunca invente dados -- baseie-se APENAS no contexto fornecido.
        PROMPT;
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'acao' => $schema->string()->required(),
            'urgencia' => $schema->string()->enum(['alta', 'media', 'baixa'])->required(),
            'justificativa' => $schema->string()->required(),
        ];
    }

    public function montarPrompt(): string
    {
        $d = $this->dados;

        $nome = $d['nome_curto'] ?? 'Cliente';
        $status = $d['status'] ?? 'ativo';
        $saldoAberto = (float) ($d['saldo_aberto'] ?? 0);
        $saldoFmt = number_format($saldoAberto, 2, ',', '.');
        $diasUltimaCompra = $d['dias_ultima_compra'] ?? null;
        $diasFmt = $diasUltimaCompra !== null ? "{$diasUltimaCompra} dias" : 'sem registro';
        $totalOS = (int) ($d['total_os'] ?? 0);
        $ticketMedio = (float) ($d['ticket_medio'] ?? 0);
        $ticketFmt = number_format($ticketMedio, 2, ',', '.');
        $tags = $d['tags'] ?? [];
        $tagsFmt = is_array($tags) && ! empty($tags) ? implode(', ', $tags) : 'nenhuma';

        return <<<PROMPT
        Proponha a proxima acao concreta para este cliente.

        Cliente:
        - Nome curto: {$nome}
        - Status: {$status}
        - Saldo em aberto: R\$ {$saldoFmt}
        - Ultima compra: {$diasFmt} atras
        - Total OSs: {$totalOS}
        - Ticket medio: R\$ {$ticketFmt}
        - Tags: {$tagsFmt}

        Responda no schema JSON: acao (string ate 60 chars) + urgencia (alta|media|baixa) + justificativa (1 frase curta explicando).
        PROMPT;
    }
}
