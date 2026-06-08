<?php

declare(strict_types=1);

namespace Modules\Crm\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;
use Stringable;

/**
 * ClienteSegmentoAgent -- Wave E Tab IA (ADR 0179 Q4).
 *
 * Reavalia segmento + tags do cliente com base no historico real
 * (OSs, ticket medio, cidade, segmento atual). Output JSON estruturado.
 *
 * Enums permitidos (espelha ClienteAutosaveController::SEGMENTOS + TAGS_WHITELIST):
 *   - segmento: varejo, atacado, agencia, corporativo, evento, governo
 *   - tags: vip, atencao, churn_risk, promotor, novo, fiel,
 *           problematico, potencial, perdido
 *
 * Latencia p95 < 6s. Custo ~$0.001 por chamada.
 *
 * @see memory/decisions/0179-cliente-drawer-760px-substitui-show-fullpage.md Q4
 */
class ClienteSegmentoAgent implements Agent, HasStructuredOutput
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
        Reavalie segmento + tags do cliente com base APENAS nos dados fornecidos -- nunca invente.
        Se nao houver sinal forte, mantenha o atual e explique o motivo em "justificativa".
        PROMPT;
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'segmento_sugerido' => $schema->string()
                ->enum(['varejo', 'atacado', 'agencia', 'corporativo', 'evento', 'governo'])
                ->required(),
            'tags_sugeridas' => $schema->array()
                ->items($schema->string()->enum([
                    'vip', 'atencao', 'churn_risk', 'promotor', 'novo', 'fiel',
                    'problematico', 'potencial', 'perdido',
                ]))
                ->required(),
            'justificativa' => $schema->string()->required(),
        ];
    }

    public function montarPrompt(): string
    {
        $d = $this->dados;

        $nome = $d['nome_curto'] ?? 'Cliente';
        $tipo = $d['tipo'] ?? '?';
        $cidade = $d['cidade'] ?? '?';
        $uf = $d['uf'] ?? '?';
        $segmentoAtual = $d['segmento'] ?? 'vazio';
        $tagsAtuais = $d['tags'] ?? [];
        $tagsFmt = is_array($tagsAtuais) && ! empty($tagsAtuais)
            ? implode(', ', $tagsAtuais)
            : 'vazio';
        $totalOS = (int) ($d['total_os'] ?? 0);
        $ticketMedio = (float) ($d['ticket_medio'] ?? 0);
        $ticketFmt = number_format($ticketMedio, 2, ',', '.');

        return <<<PROMPT
        Reavalie segmento + tags deste cliente com base no historico real.

        Cliente:
        - Nome curto: {$nome}
        - Tipo: {$tipo}
        - Localizacao: {$cidade}/{$uf}
        - Segmento atual: {$segmentoAtual}
        - Tags atuais: {$tagsFmt}
        - Total de OSs: {$totalOS}
        - Ticket medio: R\$ {$ticketFmt}

        Responda no schema JSON: segmento_sugerido (enum) + tags_sugeridas (array enum) + justificativa (1 frase curta em PT-BR explicando o porque).
        PROMPT;
    }
}
