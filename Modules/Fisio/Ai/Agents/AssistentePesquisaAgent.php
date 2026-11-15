<?php

declare(strict_types=1);

namespace Modules\Fisio\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;
use Stringable;

/**
 * Assistente da pesquisadora do estudo (etapa 07 P-08; ADR 0035 camada A/B).
 *
 * Recebe SÓ o que a exportação para a universidade já leva: código do participante, dia do
 * estudo e valores, sem nome, CPF, telefone nem data (ExportacaoEstudo). Devolve sugestões
 * de três tipos para a pesquisadora aceitar ou descartar:
 * - `qualidade`: faltante, fora de faixa, momento vencido;
 * - `resumo`: descrição do que há no dado, sem interpretação clínica;
 * - `padrao`: o que costuma vir antes de uma crise, como HIPÓTESE para conferir no arquivo.
 *
 * Nunca recomenda conduta nem fala com o paciente: isso tornaria o software dispositivo médico
 * (RDC 657/2022; ADR 0437 §5, ADR 0447). A saída só aparece na área do estudo.
 */
class AssistentePesquisaAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    /** @param array{momentos:list<list<string>>,diario:list<list<string>>} $dados  sem identificação */
    public function __construct(public readonly array $dados, public readonly string $tituloEstudo) {}

    public function provider(): ?string
    {
        $p = config('fisio.pesquisa.assistente.provider');

        return is_string($p) && $p !== '' ? $p : null;
    }

    public function model(): ?string
    {
        $m = config('fisio.pesquisa.assistente.model');

        return is_string($m) && $m !== '' ? $m : null;
    }

    public function instructions(): Stringable|string
    {
        return <<<'PROMPT'
        Você auxilia a pesquisadora de um estudo observacional sobre fibromialgia. Responda em português do Brasil.

        Você recebe CSVs sem identificação: "participante" é um código; "dia_estudo" e "dia_alvo" contam dias desde a entrada; "NA" é ausente (nunca é zero).

        Gere sugestões curtas (até 2 frases cada), de três tipos:
        - qualidade: dado faltante, valor fora da faixa do instrumento, momento sem resposta. Cite o código e o momento ou dia.
        - resumo: descrição do que existe no dado (quantos participantes, quantos dias com registro, medianas). Sem interpretar.
        - padrao: o que aparece nos dias anteriores às crises (crise = 1). Escreva como hipótese para a pesquisadora conferir no arquivo.

        Regras que não se negociam:
        - Nunca recomende tratamento, exercício, conduta nem contato com o paciente.
        - Nunca diga que um participante "está pior", "precisa de" ou "deve".
        - Nunca invente número; use só o que está nos CSVs.
        - Se não houver dado suficiente para um tipo, não gere sugestão daquele tipo.
        PROMPT;
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'sugestoes' => $schema->array()->items(
                $schema->object([
                    'tipo' => $schema->string()->enum(['qualidade', 'resumo', 'padrao'])->required(),
                    'texto' => $schema->string()->required(),
                ])
            )->required(),
        ];
    }

    public function montarPrompt(): string
    {
        $csv = fn (array $linhas) => implode("\n", array_map(fn ($l) => implode(',', $l), $linhas));

        return "Estudo: {$this->tituloEstudo}\n\nMOMENTOS (CSV):\n".$csv($this->dados['momentos'])
            ."\n\nSÉRIE DIÁRIA (CSV):\n".$csv($this->dados['diario']);
    }
}
