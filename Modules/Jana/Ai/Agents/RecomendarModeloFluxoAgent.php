<?php

declare(strict_types=1);

namespace Modules\Jana\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;
use Stringable;

/**
 * Assistente de configuração da tela Sistema › Fluxos e regras (ADR 0435): lê a descrição livre
 * do dono e sugere um modelo de fluxo do catálogo, o porquê e os casos de uso a ligar/desligar.
 *
 * Só sugere — quem aplica é o dono, pela tela. Os dados (modelos, casos, atividades) vêm do
 * App\Domain\Fsm\Modelos\CatalogoModelos; o prompt espelha o `recomendar()` do protótipo
 * prototipo-ui/cowork/Wagner/fluxos-page.jsx. Quem chama valida a saída (RecomendarModelo).
 */
class RecomendarModeloFluxoAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    /**
     * @param  array{texto:string, porte:string, atividade:string, modelos:array, casos:array, atividades:array, portes:list<string>}  $dados
     */
    public function __construct(public array $dados) {}

    public function instructions(): Stringable|string
    {
        return <<<'PROMPT'
        Você configura um ERP brasileiro para gráficas, comunicação visual e oficinas. Escolha o
        modelo de fluxo (máquina de estados) mais adequado à empresa. Responda em português
        brasileiro, tratando o dono por "você". Use SÓ os modelos, casos de uso e atividades
        fornecidos — nunca invente um id.
        PROMPT;
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'modelo' => $schema->string()->enum(array_keys($this->dados['modelos']))->required(),
            'porque' => $schema->string()->required(),
            'ligar' => $schema->array()->items($schema->string())->required(),
            'desligar' => $schema->array()->items($schema->string())->required(),
            'porte' => $schema->string()->enum($this->dados['portes'])->required(),
            'atividade' => $schema->string()->enum(array_keys($this->dados['atividades']))->required(),
        ];
    }

    public function montarPrompt(): string
    {
        $d = $this->dados;
        $ativs = implode('; ', array_map(fn ($k, $v) => "{$k}={$v}", array_keys($d['atividades']), $d['atividades']));
        $modelos = implode("\n", array_map(
            fn ($id, $m) => "{$id}: {$m['nome']} | portes ".implode('/', $m['portes']).' | atividades '.implode('/', $m['atividades'])." | {$m['para']} | casos ".implode(',', $m['casos']),
            array_keys($d['modelos']),
            $d['modelos'],
        ));
        $casos = implode("\n", array_map(fn ($id, $c) => "{$id}: {$c[0]}", array_keys($d['casos']), $d['casos']));
        $texto = $d['texto'] !== '' ? $d['texto'] : '(sem descrição)';

        return <<<PROMPT
        Perfil informado: porte {$d['porte']}, atividade {$d['atividade']}.
        Descrição do dono: "{$texto}"

        Atividades possíveis: {$ativs}

        Modelos:
        {$modelos}

        Casos de uso:
        {$casos}

        Devolva: `modelo` (id), `porque` (até 2 frases), `ligar` (casos fora do modelo que a
        descrição pede), `desligar` (casos do modelo que a descrição dispensa), `porte` e
        `atividade` que a descrição indica.
        PROMPT;
    }
}
