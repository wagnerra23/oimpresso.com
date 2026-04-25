<?php

namespace Modules\Copiloto\Services\Ai;

use Modules\Copiloto\Contracts\AiAdapter;
use Modules\Copiloto\Entities\Conversa;
use Modules\Copiloto\Support\ContextoNegocio;

/**
 * OpenAiDirectDriver — fallback padrão em v1 (usa openai-php/laravel direto).
 *
 * STUB spec-ready: integração com o SDK openai-php não plugada ainda.
 * Ver adr/tech/0002-adapter-ia-laravelai-ou-openai.md pro fluxo completo.
 */
class OpenAiDirectDriver implements AiAdapter
{
    public function gerarBriefing(ContextoNegocio $ctx): string
    {
        if (config('copiloto.dry_run')) {
            return $this->fixtureBriefing($ctx);
        }

        // TODO: montar prompt + chamar OpenAI::chat()->create([...])
        return $this->fixtureBriefing($ctx);
    }

    public function sugerirMetas(ContextoNegocio $ctx, string $prompt): array
    {
        if (config('copiloto.dry_run')) {
            return $this->fixtureSugestoes($ctx);
        }

        // TODO: JSON mode + validação zod-like do shape de resposta.
        return $this->fixtureSugestoes($ctx);
    }

    public function responderChat(Conversa $conv, string $mensagem): string
    {
        if (config('copiloto.dry_run')) {
            return "(dry-run) Recebi: \"{$mensagem}\". Quando a IA estiver plugada, eu respondo de verdade.";
        }

        // TODO: histórico + call chat completion.
        return "(stub) Integração OpenAI pendente — ver adr/tech/0002.";
    }

    /** Fixture pra rodar UI offline. */
    protected function fixtureBriefing(ContextoNegocio $ctx): string
    {
        $nomeBiz = $ctx->businessName;
        $clientes = $ctx->clientesAtivos;
        return "Olá! Sou seu Copiloto. Estou olhando {$nomeBiz} — vejo {$clientes} clientes ativos "
            . "e " . count($ctx->faturamento90d) . " meses de faturamento nos últimos 90 dias. "
            . "Quer que eu sugira metas pro próximo período? É só pedir.";
    }

    /** Fixture pra rodar UI offline. */
    protected function fixtureSugestoes(ContextoNegocio $ctx): array
    {
        return [
            [
                'nome'           => 'Faturamento — conservador',
                'metrica'        => 'faturamento',
                'valor_alvo'     => 120000,
                'periodo'        => 'mensal',
                'dificuldade'    => 'facil',
                'racional'       => 'Manter base atual com +10% sobre média 90d.',
                'dependencias'   => [],
            ],
            [
                'nome'           => 'Faturamento — realista',
                'metrica'        => 'faturamento',
                'valor_alvo'     => 180000,
                'periodo'        => 'mensal',
                'dificuldade'    => 'realista',
                'racional'       => '+50% requer campanha em clientes B + upsell de módulos.',
                'dependencias'   => ['Grow', 'PontoWr2 em 2 clientes'],
            ],
            [
                'nome'           => 'Faturamento — ambicioso',
                'metrica'        => 'faturamento',
                'valor_alvo'     => 300000,
                'periodo'        => 'mensal',
                'dificuldade'    => 'ambicioso',
                'racional'       => 'Alavancagem total: ativar 49 businesses + captar 10 novos.',
                'dependencias'   => ['Grow', 'Campanha reativação', 'Comercial dedicado'],
            ],
        ];
    }
}
