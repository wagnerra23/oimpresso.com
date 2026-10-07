<?php

declare(strict_types=1);

namespace Modules\Jana\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;
use Stringable;

/**
 * SugestaoFiscalAgent — a Jana sugere NCM, natureza, regra incompleta ou aponta inconsistência
 * (playbook Fiscal thread 10 · D-IA). Mesmo desenho do `SugestoesMetasAgent` (ADR 0034 · 0035).
 *
 * Só SUGERE: quem chama (`SugestaoFiscalService`, no NfeBrasil) grava como `pendente`, e só uma
 * pessoa com `nfe.tributacao.manage` aplica. A Jana não toca rejeição de nota — aquilo é receita
 * determinística por cStat (Fiscal/SPEC).
 */
class SugestaoFiscalAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    /**
     * @param list<array<string, mixed>> $produtos saída de `ProdutoFiscalTool::dados()`
     */
    public function __construct(
        public array $produtos,
    ) {}

    public function instructions(): Stringable|string
    {
        return <<<'PROMPT'
        Você é a Jana, assistente fiscal do oimpresso. Responda em português brasileiro.
        Analise SÓ os produtos e regras fornecidos. NUNCA invente NCM, CFOP ou alíquota: se não tiver
        base nos dados, não sugira. Quando sugerir regra, cite a lei ou a norma no motivo.
        Não trate rejeição de nota da SEFAZ. Marque risco "alto" quando a sugestão muda imposto.
        PROMPT;
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'sugestoes' => $schema->array()->items(
                $schema->object([
                    'tipo' => $schema->string()->enum(['ncm', 'natureza', 'regra', 'inconsistencia'])->required(),
                    'alvo_id' => $schema->integer()->required(),
                    'valor_sugerido' => $schema->string()->required(),
                    'confianca' => $schema->number()->required(),
                    'risco' => $schema->string()->enum(['baixo', 'medio', 'alto'])->required(),
                    'motivo' => $schema->string()->required(),
                ])
            )->required(),
        ];
    }

    public function montarPrompt(): string
    {
        $dados = json_encode($this->produtos, JSON_UNESCAPED_UNICODE);

        return <<<PROMPT
        Produtos da empresa (com o NCM atual e as regras tributárias do NCM):
        {$dados}

        Devolva até 10 sugestões. Para tipo "ncm", "natureza" e "inconsistencia", `alvo_id` é o id do
        produto. Para tipo "regra", `alvo_id` é o id da regra e `valor_sugerido` é um JSON com os
        campos da regra a mudar (ex.: {"c_class_trib":"000001","cst_ibs":"000","cst_cbs":"000"}).
        `confianca` vai de 0 a 1.
        PROMPT;
    }
}
