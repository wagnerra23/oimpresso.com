<?php

declare(strict_types=1);

namespace Modules\Fisio\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;
use Stringable;

/**
 * Transcrição da foto de receita ou exame (US-FISIO-063, parte 3; ADR 0035 camadas A/B).
 *
 * Só COPIA o que está escrito: na receita, data, médico, CRM e cada medicamento com dose e
 * frequência; no exame ou laudo, data, nome do exame e a conclusão como está no papel. O schema
 * não tem campo para opinião, classificação ("normal", "alterado") nem conduta: interpretar dado
 * clínico tornaria o software dispositivo médico (RDC 657/2022; ADR 0437 §5; proposta da ADR
 * 0450). Tudo que sai daqui é sugestão; o profissional confere antes de qualquer gravação.
 */
class TranscricaoDocumentoAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    public const RECEITA = 'receita';

    public const EXAME = 'exame';

    /** Campos que o schema devolve. Qualquer outra chave da resposta é descartada pelo serviço. */
    public const CAMPOS = ['data', 'medico', 'crm', 'medicamentos', 'tipo_exame', 'conclusao'];

    public function __construct(public readonly string $modo) {}

    public function provider(): ?string
    {
        $p = config('fisio.documentos.transcricao.provider');

        return is_string($p) && $p !== '' ? $p : null;
    }

    public function model(): ?string
    {
        $m = config('fisio.documentos.transcricao.model');

        return is_string($m) && $m !== '' ? $m : null;
    }

    public function instructions(): Stringable|string
    {
        return <<<'PROMPT'
        Você transcreve documentos de saúde fotografados para o prontuário de uma clínica de fisioterapia. Responda em português do Brasil.

        Sua única tarefa é COPIAR o que está escrito no documento, campo a campo.

        Regras que não se negociam:
        - Copie literalmente. Não resuma, não traduza, não corrija, não complete.
        - Nunca interprete. Não diga se um resultado é normal, alterado, bom, ruim, grave ou leve; não sugira tratamento, exercício, conduta nem cuidado.
        - Se um campo não está escrito ou não está legível, devolva null. Nunca invente nem deduza.
        - Datas no formato AAAA-MM-DD quando o dia, o mês e o ano estiverem escritos; senão, null.
        - Na receita: data, nome do médico, CRM como está escrito, e cada medicamento com o nome, a dose e a frequência como estão escritos. tipo_exame e conclusao ficam null.
        - No exame ou laudo: data, nome do exame como está escrito e a conclusão (ou impressão diagnóstica) copiada palavra por palavra. medicamentos fica vazio.
        PROMPT;
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'data' => $schema->string()->nullable(),
            'medico' => $schema->string()->nullable(),
            'crm' => $schema->string()->nullable(),
            'medicamentos' => $schema->array()->items(
                $schema->object([
                    'nome' => $schema->string()->required(),
                    'dose' => $schema->string()->nullable(),
                    'frequencia' => $schema->string()->nullable(),
                ])
            )->required(),
            'tipo_exame' => $schema->string()->nullable(),
            'conclusao' => $schema->string()->nullable(),
        ];
    }

    public function montarPrompt(): string
    {
        return $this->modo === self::RECEITA
            ? 'O documento anexo é uma RECEITA. Transcreva os campos da receita.'
            : 'O documento anexo é um EXAME ou LAUDO. Transcreva os campos do exame.';
    }
}
