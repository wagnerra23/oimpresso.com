<?php

namespace Modules\Jana\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;
use Stringable;

/**
 * DetectarSupersedeAgent — decide se um fato NOVO substitui (supersede) um fato
 * ANTERIOR no event-time (ADR 0295 slice 3). Driver: laravel/ai (ADR 0034).
 *
 * "Supersede" aqui = update temporal do MESMO sujeito: o fato novo conta uma
 * versão mais recente do mundo que torna o antigo obsoleto (ex.: "a meta agora
 * é R$ 80 mil" supersede "a meta é R$ 50 mil"). NÃO é supersede só por falar do
 * mesmo tema — tem que CONTRADIZER/ATUALIZAR um valor anterior.
 *
 * Modelo/provider são escolhidos no call-site (SupersedeDetector): Haiku via
 * `anthropic` com fallback gpt-4o-mini via `openai`. Por isso este agent NÃO
 * declara `#[Model(...)]` — ele é provider-agnóstico.
 *
 * Multi-tenant (ADR 0093): o agent só enxerga os candidatos que recebe, que já
 * são business_id+user_id scoped pelo job. Ele NUNCA consulta o banco e devolve
 * SÓ um id do conjunto fornecido (o SupersedeDetector ainda revalida).
 */
class DetectarSupersedeAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    /**
     * @param  string  $novoFato    o fato recém-extraído
     * @param  array<int, string>  $candidatos  map id => fato (ativos do business+user)
     */
    public function __construct(
        public string $novoFato,
        public array $candidatos,
    ) {
    }

    public function instructions(): Stringable|string
    {
        return <<<'PROMPT'
        Você é o detector de UPDATE TEMPORAL da memória do Copiloto do oimpresso.

        Recebe UM fato novo e uma lista numerada de fatos ANTERIORES já memorizados
        (cada um com um id). Sua tarefa: decidir se o fato novo SUBSTITUI (supersede)
        exatamente UM dos anteriores.

        REGRAS RÍGIDAS:
        1. Só é supersede quando o fato novo ATUALIZA/CONTRADIZ o MESMO sujeito de um
           anterior — uma versão mais recente do mundo que torna o antigo obsoleto.
           Ex.: novo "a meta agora é R$ 80 mil/mês" supersede antigo "a meta é R$ 50 mil/mês".
        2. NÃO é supersede se o fato novo só fala de tema parecido sem contradizer
           (ex.: "meta de faturamento" vs "meta de novos clientes" são fatos distintos).
        3. NÃO é supersede se for um fato genuinamente novo, sem antecessor na lista.
        4. No máximo UM supersede por chamada. Escolha o anterior mais claramente
           substituído. Na dúvida, NÃO marque supersede.
        5. Devolva `supersedes_id` = o id EXATO da lista (nunca invente um id). Se não
           houver supersede, `supersede=false` e `supersedes_id=0`.
        6. `confianca` é 0-100: o quão certo você está do supersede. Seja conservador.
        PROMPT;
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'supersede' => $schema->boolean()
                ->description('true só se o fato novo substitui um anterior da lista')
                ->required(),
            'supersedes_id' => $schema->integer()
                ->description('id EXATO do fato anterior substituído; 0 se nenhum')
                ->required(),
            'confianca' => $schema->integer()->min(0)->max(100)
                ->description('confiança 0-100 no supersede')
                ->required(),
            'motivo' => $schema->string()
                ->description('1 frase curta: por que (não) é supersede')
                ->required(),
        ];
    }

    public function montarPrompt(): string
    {
        $linhas = [];
        foreach ($this->candidatos as $id => $fato) {
            $linhas[] = "#{$id}: {$fato}";
        }
        $lista = $linhas === [] ? '(nenhum)' : implode("\n", $linhas);

        return <<<PROMPT
        FATO NOVO:
        {$this->novoFato}

        FATOS ANTERIORES (id: texto):
        {$lista}

        O fato novo substitui (supersede) exatamente um dos anteriores? Siga as
        REGRAS RÍGIDAS. Devolva supersede/supersedes_id/confianca/motivo.
        PROMPT;
    }
}
