<?php

namespace Modules\Copiloto\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;
use Stringable;

/**
 * ExtrairFatosAgent — extrai até 5 fatos persistentes de uma conversa.
 *
 * Sprint 5 do roadmap canônico (ADR 0036). Driver: laravel/ai (ADR 0034).
 * Output estruturado via HasStructuredOutput pra MemoriaContrato.lembrar() consumir.
 *
 * Critérios de extração:
 *  - APENAS fatos persistentes (preferências, metas, contexto, restrições)
 *  - Ignora conversa trivial / pergunta-resposta efêmera
 *  - Máx 5 por chamada (limite de tokens + qualidade > quantidade)
 */
class ExtrairFatosAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    public function __construct(
        public string $businessName,
        public string $transcript,
    ) {
    }

    public function instructions(): Stringable|string
    {
        return <<<PROMPT
        Você é o extrator de memória do Copiloto do oimpresso.
        Sua tarefa é IDENTIFICAR fatos persistentes sobre o usuário ou business da empresa "{$this->businessName}"
        a partir de uma transcrição de conversa.

        REGRAS RÍGIDAS:
        1. Extraia APENAS fatos que devem ser lembrados em conversas futuras (preferências,
           metas declaradas, contexto de negócio, restrições operacionais).
        2. NÃO extraia pergunta-resposta efêmera (ex: "qual o faturamento de hoje?" não vira fato).
        3. NÃO invente fatos — só o que estiver textualmente claro na conversa.
        4. NÃO extraia dados sensíveis (CPF, CNPJ, senhas) — eles são mascarados antes.
        5. Máximo 5 fatos por chamada. Qualidade > quantidade.
        6. Cada fato deve ser uma frase completa em português brasileiro.

        Categorias válidas:
        - meta: meta de negócio declarada (faturamento, clientes, etc)
        - preferencia: preferência de uso (formato de relatório, canal, horário)
        - restricao: restrição operacional (não fazer X, evitar Y)
        - contexto: contexto duradouro do business (perfil cliente, equipe, sistema usado)
        - acao_pendente: ação que o user disse que vai fazer

        Se NADA na conversa qualificar como fato persistente, retorne array vazio.
        PROMPT;
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'fatos' => $schema->array()->items(
                $schema->object([
                    'fato' => $schema->string()->required(),
                    'categoria' => $schema->string()
                        ->enum(['meta', 'preferencia', 'restricao', 'contexto', 'acao_pendente'])
                        ->required(),
                    'relevancia' => $schema->integer()->min(1)->max(10)->required(),
                ])
            )->required(),
        ];
    }

    public function montarPrompt(): string
    {
        return <<<PROMPT
        Transcrição da conversa pra extração:
        ---
        {$this->transcript}
        ---

        Extraia até 5 fatos persistentes seguindo as REGRAS RÍGIDAS do system prompt.
        PROMPT;
    }
}
