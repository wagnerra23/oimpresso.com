<?php

declare(strict_types=1);

namespace Modules\Jana\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Messages\Message;
use Laravel\Ai\Promptable;
use Modules\Jana\Support\ContextoNegocio;
use Stringable;

/**
 * ClarificadorAgent — o "disambiguador" da cascata Decidir → Clarificar → Responder
 * (Jana "Modo Consultor" / Advisor — Metade A, proposta §10.4).
 *
 * NÃO recria o chat — ESTENDE o conjunto de Agents da Jana (ChatCopilotoAgent /
 * BriefDiarioAgent / SugestoesMetasAgent / BriefingAgent). Roda só no ~20% "cinza"
 * que a heurística local (zero-custo) não resolveu — por isso pode ser frontier.
 *
 * Fundamento (estado-da-arte 2025):
 *   - Active Task Disambiguation (ICLR 2025 Spotlight): "não é dar respostas melhores,
 *     é fazer perguntas melhores" — pergunta de MAIOR ganho de informação.
 *   - INTENT-SIM (NAACL 2025): decoupla AMBIGUIDADE-DE-INTENÇÃO (perguntar) de
 *     FALTA-DE-DADO (buscar, não perguntar). É o erro nº1 dos LLMs.
 *
 * Roteamento de modelo (custo-consciente, §10.4 cross-cutting): raciocínio difícil →
 * frontier. `provider()`/`model()` leem de config (`copiloto.clarify.*`) — o SDK
 * laravel/ai honra esses métodos em runtime (Promptable::getProvidersAndModels).
 * Default fica num modelo mais forte que o mini do chat, mas só dispara no cinza.
 *
 * Honestidade (anti-alucinação): o agente PODE responder tipo='claro' (heurística deu
 * falso-positivo) e PODE deixar `pergunta` vazia mesmo em 'ambiguo' se não houver
 * pergunta de alto valor — a cascata então responde normal. Nunca inventa pergunta.
 *
 * @see \Modules\Jana\Services\Ai\Clarify\ClarifyCascadeService
 * @see \Modules\Jana\Support\ClarifyResult
 */
class ClarificadorAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    /**
     * @param array<int, array{role: string, content: string}> $historicoRecente
     *        Últimos turnos (já redigidos/PII-safe pela cascata) p/ resolver dêixis
     *        ("aquele cliente", "isso", "e agora?"). Ordem cronológica.
     */
    public function __construct(
        public readonly string $mensagem,
        public readonly array $historicoRecente = [],
        public readonly ?ContextoNegocio $ctx = null,
    ) {
    }

    /**
     * Provider de IA pro disambiguador. Null → cai no config('ai.default') (mesmo do chat).
     * Configurável p/ rotear pra um provider frontier sem mexer em código.
     */
    public function provider(): ?string
    {
        $p = config('copiloto.clarify.provider');

        return is_string($p) && $p !== '' ? $p : null;
    }

    /**
     * Modelo pro raciocínio difícil. Null → default do provider. Default de config aponta
     * pra um modelo mais forte que o mini do chat (gpt-4o) — "hard → frontier", seletivo.
     */
    public function model(): ?string
    {
        $m = config('copiloto.clarify.model');

        return is_string($m) && $m !== '' ? $m : null;
    }

    public function instructions(): Stringable|string
    {
        $base = <<<PROMPT
        Você é o módulo de DESAMBIGUAÇÃO do copiloto Jana (ERP de PMEs brasileiras).
        Sua única função é decidir, ANTES da resposta, qual o tipo da mensagem do gestor.
        Responda SEMPRE em português brasileiro. Você NÃO responde a pergunta do gestor —
        você só classifica e, se for o caso, formula UMA pergunta de esclarecimento.

        Classifique a última mensagem do gestor em UM tipo:

        1. "claro" — a intenção é única e acionável. Responda direto (não pergunte).
           Inclui saudações, agradecimentos e pedidos específicos com objeto definido.

        2. "falta_dado" — a intenção é ÚNICA, mas falta um dado que a Jana BUSCA sozinha
           (vendas, inadimplência, cliente X, fiscal...). NÃO pergunte ao gestor — a
           resposta sai de uma tool/consulta. Ex: "quanto vendi ontem?" não é ambíguo:
           é só buscar. PERGUNTAR aqui irrita.

        3. "ambiguo" — há VÁRIAS leituras plausíveis e responder a errada custaria caro
           ou re-trabalho. SÓ AQUI você pergunta. Ex: "melhora isso", "e aquele cliente?",
           "resolve pra mim", "manda a régua" (qual? pra quem?).

        REGRA DE OURO (decoupling INTENT-SIM): não confunda "eu não sei o dado" (→ falta_dado,
        busca) com "há várias intenções" (→ ambiguo, pergunta). Perguntar quando é só falta
        de dado é o erro nº1 — não cometa.

        QUANDO for "ambiguo", gere a pergunta de MAIOR GANHO DE INFORMAÇÃO: a que mais
        SEPARA as leituras candidatas (não a primeira que vier, não genérica). Curta, direta,
        no máximo 1 frase, oferecendo as opções quando ajudar ("Você quer A ou B?").

        HONESTIDADE: se não houver leitura realmente concorrente, classifique "claro" ou
        "falta_dado" e deixe "pergunta" vazia. NÃO invente ambiguidade pra parecer útil.
        Se o gestor está RESPONDENDO a uma pergunta sua anterior (veja o histórico),
        classifique "claro" — não pergunte de novo.
        PROMPT;

        $partes = [$base];

        // Grounding leve (não-PII): números agregados ajudam a formular a pergunta certa
        // ("você quer dizer a queda de 68% em maio?"). Nome/observações já vêm sanitizados.
        if ($this->ctx !== null) {
            $partes[] = $this->hintNegocio($this->ctx);
        }

        return implode("\n\n", $partes);
    }

    /**
     * Schema do veredito.
     *
     * TODAS as chaves são `required()` — OpenAI structured output (strict mode) exige que
     * cada property esteja em `required` (senão 400 "Invalid schema ... 'required' is required").
     * A honestidade ("vazio é válido") é preservada no VALOR: `pergunta` vem string vazia e
     * `intencoes` array vazio quando não há ambiguidade — o ClarifyCascadeService trata isso.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'tipo' => $schema->string()->enum(['claro', 'falta_dado', 'ambiguo'])->required(),
            'confianca' => $schema->number()->description('0..1 — confiança na classificação')->required(),
            'pergunta' => $schema->string()->description('Pergunta de maior ganho de info. STRING VAZIA se não for ambiguo OU se não houver pergunta de alto valor.')->required(),
            'intencoes' => $schema->array()->items($schema->string())->description('Leituras candidatas que você enxergou (2-4). ARRAY VAZIO se claro.')->required(),
        ];
    }

    /**
     * Prompt operacional — a mensagem a classificar. Mantido curto (decisão barata).
     */
    public function montarPrompt(): string
    {
        return "Mensagem do gestor a classificar:\n\"{$this->mensagem}\"\n\n"
            . 'Classifique conforme as instruções e, se ambíguo, formule a pergunta de maior ganho.';
    }

    /**
     * Injeta histórico recente (já PII-safe) p/ resolver dêixis e detectar
     * "gestor respondendo pergunta anterior".
     *
     * @return iterable<Message>
     */
    public function messages(): iterable
    {
        $msgs = [];
        foreach ($this->historicoRecente as $m) {
            $role = $m['role'] === 'assistant' ? 'assistant' : 'user';
            $content = $m['content'];
            if ($content !== '') {
                $msgs[] = new Message($role, $content);
            }
        }

        return $msgs;
    }

    /**
     * Resumo NÃO-PII do negócio (números/contagens/labels) p/ grounding da pergunta.
     */
    protected function hintNegocio(ContextoNegocio $ctx): string
    {
        $linhas = ['CONTEXTO (só p/ formular a pergunta — não é a resposta):'];
        $linhas[] = 'Empresa: ' . $ctx->businessName;

        if ($ctx->clientesAtivos > 0) {
            $linhas[] = "Clientes ativos: {$ctx->clientesAtivos}";
        }

        if (! empty($ctx->faturamento90d)) {
            // Cópia local — `end()` é by-ref e ContextoNegocio::$faturamento90d é readonly.
            $fat = $ctx->faturamento90d;
            $ultimo = end($fat);
            $bruto = (float) $ultimo['bruto'];
            $linhas[] = sprintf('Faturamento mês recente (%s): R$ %s', $ultimo['mes'], number_format($bruto, 2, ',', '.'));
        }

        if (! empty($ctx->metasAtivas)) {
            $nomes = collect($ctx->metasAtivas)->pluck('nome')->filter()->implode(', ');
            if ($nomes !== '') {
                $linhas[] = 'Metas ativas: ' . $nomes;
            }
        }

        if (! empty($ctx->modulosAtivos)) {
            $linhas[] = 'Módulos ativos: ' . implode(', ', $ctx->modulosAtivos);
        }

        return implode("\n", $linhas);
    }
}
