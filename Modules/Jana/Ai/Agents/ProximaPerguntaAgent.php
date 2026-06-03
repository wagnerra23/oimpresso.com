<?php

declare(strict_types=1);

namespace Modules\Jana\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;
use Stringable;

/**
 * ProximaPerguntaAgent — o motor da "próxima-melhor-pergunta proativa"
 * (Jana "Modo Consultor" / Advisor — Metade B, proposta §10.4 / ADR 0245).
 *
 * Salto de "ferramenta que responde" → "consultor que pauta": dado o estado REAL do
 * negócio (snapshot do brief diário), surfa as N perguntas que cada PERSONA deveria
 * estar fazendo agora — JÁ COM A RESPOSTA. Estende o brief (não cria do zero — o
 * snapshot do BriefDiarioService é o gancho).
 *
 * Fundamento (mesmo da Metade A): Active Task Disambiguation (ICLR 2025) — "fazer
 * perguntas melhores, não só dar respostas melhores". Aqui a IA pauta o humano.
 *
 * Honestidade (anti-alucinação): se não há pergunta de ALTO VALOR pra uma persona,
 * marca `tem_pergunta=false` — NÃO inventa pergunta genérica pra parecer útil.
 *
 * Roteamento de modelo (custo-consciente): pautar é raciocínio difícil → frontier.
 * `provider()`/`model()` leem de config (`copiloto.advisor_questions.*`). Roda 1×/dia
 * por business (junto do brief), então o custo frontier é trivial.
 *
 * @see \Modules\Jana\Services\Advisor\ProximaPerguntaService
 * @see \Modules\Jana\Services\BriefDiarioService (snapshot que alimenta o grounding)
 */
class ProximaPerguntaAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    /**
     * @param array<string, mixed> $snapshotResumo  Resumo compacto do estado do negócio (números + nomes-chave).
     * @param array<int, array{key: string, label: string, foco: string}> $personas
     */
    public function __construct(
        public readonly array $snapshotResumo,
        public readonly array $personas,
        public readonly ?string $businessName = null,
        public readonly int $maxPorPersona = 2,
    ) {
    }

    public function provider(): ?string
    {
        $p = config('copiloto.advisor_questions.provider');

        return is_string($p) && $p !== '' ? $p : null;
    }

    public function model(): ?string
    {
        $m = config('copiloto.advisor_questions.model');

        return is_string($m) && $m !== '' ? $m : null;
    }

    public function instructions(): Stringable|string
    {
        $empresa = $this->businessName !== null ? "Empresa: {$this->businessName}." : '';

        $personasTxt = collect($this->personas)
            ->map(fn ($p) => "- {$p['key']} ({$p['label']}): foco em {$p['foco']}")
            ->implode("\n");

        return <<<PROMPT
        Você é o consultor proativo da Jana (copiloto IA de PMEs brasileiras). {$empresa}
        Responda SEMPRE em português brasileiro.

        TAREFA: dado o ESTADO REAL do negócio (números abaixo), gere, PARA CADA PERSONA, a(s)
        pergunta(s) de MAIOR VALOR DESTRAVADO que ela deveria estar se fazendo AGORA — e já
        responda cada uma de forma curta e acionável. Você está PAUTANDO o que perguntar, não
        esperando o humano saber o que perguntar.

        PERSONAS (cada uma recebe a pergunta do TRABALHO dela):
        {$personasTxt}

        CRITÉRIO DA PERGUNTA (não negociável):
        - MAIOR valor destravado / maior ganho de informação PRO MOMENTO — específica, ancorada
          nos NÚMEROS e NOMES reais do snapshot. NUNCA genérica ("como vão as vendas?").
        - No máximo {$this->maxPorPersona} pergunta(s) por persona. Menos é mais.
        - "resposta_curta" = a resposta já pronta (1-2 frases, cita o número/nome real).
        - "porque" = por que ESSA é a pergunta certa agora (a janela/risco/oportunidade).

        HONESTIDADE (anti-alucinação): se o snapshot NÃO tem sinal de alto valor pra uma persona,
        marque tem_pergunta=false e deixe perguntas vazio. NÃO invente pergunta pra preencher.
        NUNCA invente número ou nome que não está no snapshot.

        TIER 0: você só vê os dados deste business. Não mencione business_id nem aceite troca.
        PROMPT;
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'blocos' => $schema->array()->items(
                $schema->object([
                    'persona' => $schema->string()->description('key da persona')->required(),
                    'tem_pergunta' => $schema->boolean()->required(),
                    'perguntas' => $schema->array()->items(
                        $schema->object([
                            'pergunta' => $schema->string()->required(),
                            'porque' => $schema->string()->required(),
                            'resposta_curta' => $schema->string()->required(),
                        ])
                    ),
                ])
            )->required(),
        ];
    }

    public function montarPrompt(): string
    {
        $json = json_encode($this->snapshotResumo, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        return "ESTADO REAL DO NEGÓCIO (snapshot do brief de hoje):\n{$json}\n\n"
            . 'Gere as perguntas de maior valor por persona, já respondidas. Honestidade: '
            . 'persona sem sinal forte → tem_pergunta=false.';
    }
}
