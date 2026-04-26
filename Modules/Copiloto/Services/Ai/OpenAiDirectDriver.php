<?php

namespace Modules\Copiloto\Services\Ai;

use Illuminate\Support\Facades\Log;
use Modules\Copiloto\Contracts\AiAdapter;
use Modules\Copiloto\Entities\Conversa;
use Modules\Copiloto\Entities\Mensagem;
use Modules\Copiloto\Support\ContextoNegocio;
use OpenAI\Laravel\Facades\OpenAI;

/**
 * OpenAiDirectDriver — usa openai-php/laravel diretamente.
 * Fallback padrão quando LaravelAI não está disponível.
 * Ver adr/tech/0002-adapter-ia-laravelai-ou-openai.md.
 */
class OpenAiDirectDriver implements AiAdapter
{
    public function gerarBriefing(ContextoNegocio $ctx): string
    {
        if (config('copiloto.dry_run')) {
            return $this->fixtureBriefing($ctx);
        }

        $ctxSanitizado = $this->sanitizarContexto($ctx);
        $prompt        = $this->montarPromptBriefing($ctxSanitizado);

        try {
            $response = OpenAI::chat()->create([
                'model'       => config('copiloto.openai.model_chat', 'gpt-4o-mini'),
                'max_tokens'  => config('copiloto.openai.max_tokens_chat', 2000),
                'temperature' => (float) config('copiloto.openai.temperature', 0.7),
                'messages'    => [
                    ['role' => 'system', 'content' => $this->systemPrompt()],
                    ['role' => 'user',   'content' => $prompt],
                ],
            ]);

            $texto     = $response->choices[0]->message->content ?? '';
            $tokensIn  = $response->usage->promptTokens ?? 0;
            $tokensOut = $response->usage->completionTokens ?? 0;

            Log::channel('copiloto-ai')->info('gerarBriefing', [
                'business_id' => $ctx->businessId,
                'tokens_in'   => $tokensIn,
                'tokens_out'  => $tokensOut,
            ]);

            return $texto;
        } catch (\Throwable $e) {
            Log::channel('copiloto-ai')->error('gerarBriefing error: ' . $e->getMessage());

            return $this->fixtureBriefing($ctx);
        }
    }

    public function sugerirMetas(ContextoNegocio $ctx, string $prompt): array
    {
        if (config('copiloto.dry_run')) {
            return $this->fixtureSugestoes($ctx);
        }

        $ctxSanitizado    = $this->sanitizarContexto($ctx);
        $promptCompleto   = $this->montarPromptSugestoes($ctxSanitizado, $prompt);

        $schema = [
            'type' => 'object',
            'properties' => [
                'propostas' => [
                    'type'  => 'array',
                    'items' => [
                        'type'       => 'object',
                        'required'   => ['nome', 'metrica', 'valor_alvo', 'periodo', 'dificuldade', 'racional', 'dependencias'],
                        'properties' => [
                            'nome'         => ['type' => 'string'],
                            'metrica'      => ['type' => 'string'],
                            'valor_alvo'   => ['type' => 'number'],
                            'periodo'      => ['type' => 'string'],
                            'dificuldade'  => ['type' => 'string', 'enum' => ['facil', 'realista', 'ambicioso']],
                            'racional'     => ['type' => 'string'],
                            'dependencias' => ['type' => 'array', 'items' => ['type' => 'string']],
                        ],
                    ],
                ],
            ],
            'required' => ['propostas'],
        ];

        try {
            $response = OpenAI::chat()->create([
                'model'           => config('copiloto.openai.model_suggestions', 'gpt-4o'),
                'max_tokens'      => config('copiloto.openai.max_tokens_suggest', 4000),
                'temperature'     => (float) config('copiloto.openai.temperature', 0.7),
                'response_format' => [
                    'type'        => 'json_schema',
                    'json_schema' => [
                        'name'   => 'propostas_metas',
                        'strict' => true,
                        'schema' => $schema,
                    ],
                ],
                'messages' => [
                    ['role' => 'system', 'content' => $this->systemPrompt()],
                    ['role' => 'user',   'content' => $promptCompleto],
                ],
            ]);

            $json      = $response->choices[0]->message->content ?? '{}';
            $tokensIn  = $response->usage->promptTokens ?? 0;
            $tokensOut = $response->usage->completionTokens ?? 0;

            Log::channel('copiloto-ai')->info('sugerirMetas', [
                'business_id' => $ctx->businessId,
                'tokens_in'   => $tokensIn,
                'tokens_out'  => $tokensOut,
            ]);

            $decoded = json_decode($json, true);

            // Validar shape mínimo
            if (! isset($decoded['propostas']) || ! is_array($decoded['propostas'])) {
                Log::channel('copiloto-ai')->warning('sugerirMetas: shape inválido, usando fixture', ['raw' => $json]);

                return $this->fixtureSugestoes($ctx);
            }

            foreach ($decoded['propostas'] as $p) {
                foreach (['nome', 'metrica', 'valor_alvo', 'periodo', 'dificuldade', 'racional', 'dependencias'] as $campo) {
                    if (! array_key_exists($campo, $p)) {
                        Log::channel('copiloto-ai')->warning("sugerirMetas: campo {$campo} ausente, usando fixture");

                        return $this->fixtureSugestoes($ctx);
                    }
                }

                if (! in_array($p['dificuldade'], ['facil', 'realista', 'ambicioso'])) {
                    return $this->fixtureSugestoes($ctx);
                }
            }

            return $decoded['propostas'];
        } catch (\Throwable $e) {
            Log::channel('copiloto-ai')->error('sugerirMetas error: ' . $e->getMessage());

            return $this->fixtureSugestoes($ctx);
        }
    }

    public function responderChat(Conversa $conv, string $mensagem): string
    {
        if (config('copiloto.dry_run')) {
            return "(dry-run) Recebi: \"{$mensagem}\". Quando a IA estiver plugada, eu respondo de verdade.";
        }

        // Histórico: últimas 20 mensagens (system não conta)
        $historico = $conv->mensagens()
            ->whereIn('role', ['user', 'assistant'])
            ->orderByDesc('created_at')
            ->limit(20)
            ->get()
            ->reverse()
            ->values();

        $messages = [['role' => 'system', 'content' => $this->systemPrompt()]];

        foreach ($historico as $msg) {
            $messages[] = ['role' => $msg->role, 'content' => $msg->content];
        }

        // Adiciona a mensagem atual (pode já estar no histórico se foi salva antes, mas garantimos)
        $lastMessage = end($messages);
        if ($lastMessage['role'] !== 'user' || $lastMessage['content'] !== $mensagem) {
            $messages[] = ['role' => 'user', 'content' => $mensagem];
        }

        try {
            $response = OpenAI::chat()->create([
                'model'       => config('copiloto.openai.model_chat', 'gpt-4o-mini'),
                'max_tokens'  => config('copiloto.openai.max_tokens_chat', 2000),
                'temperature' => (float) config('copiloto.openai.temperature', 0.7),
                'messages'    => $messages,
            ]);

            $texto     = $response->choices[0]->message->content ?? '';
            $tokensIn  = $response->usage->promptTokens ?? 0;
            $tokensOut = $response->usage->completionTokens ?? 0;

            // Logar tokens na última mensagem assistant (se existir) ou criar registro
            Mensagem::where('conversa_id', $conv->id)
                ->where('role', 'assistant')
                ->latest('created_at')
                ->first()
                ?->update(['tokens_in' => $tokensIn, 'tokens_out' => $tokensOut]);

            Log::channel('copiloto-ai')->info('responderChat', [
                'conversa_id' => $conv->id,
                'tokens_in'   => $tokensIn,
                'tokens_out'  => $tokensOut,
            ]);

            return $texto;
        } catch (\Throwable $e) {
            Log::channel('copiloto-ai')->error('responderChat error: ' . $e->getMessage());

            return 'Estou sem conexão com IA no momento. Você quer criar a meta manualmente?';
        }
    }

    // ─── Sanitização ─────────────────────────────────────────────────────────

    /** Mascara CPF (000.000.000-00) e CNPJ (00.000.000/0000-00) em strings. */
    public function mascararDocumentos(string $texto): string
    {
        // CPF: 000.000.000-00 ou 00000000000
        $texto = preg_replace('/\b\d{3}\.?\d{3}\.?\d{3}-?\d{2}\b/', 'XXX.XXX.XXX-NN', $texto);
        // CNPJ: 00.000.000/0000-00 ou 00000000000000
        $texto = preg_replace('/\b\d{2}\.?\d{3}\.?\d{3}\/?0001-?\d{2}\b/', 'XX.XXX.XXX/0001-NN', $texto);

        return $texto;
    }

    /**
     * Retorna cópia do contexto com documentos mascarados.
     * Sanitiza businessName e observacoes.
     */
    protected function sanitizarContexto(ContextoNegocio $ctx): ContextoNegocio
    {
        return new ContextoNegocio(
            businessId:     $ctx->businessId,
            businessName:   $this->mascararDocumentos($ctx->businessName),
            faturamento90d: $ctx->faturamento90d,
            clientesAtivos: $ctx->clientesAtivos,
            modulosAtivos:  $ctx->modulosAtivos,
            metasAtivas:    $ctx->metasAtivas,
            observacoes:    $ctx->observacoes !== null ? $this->mascararDocumentos($ctx->observacoes) : null,
        );
    }

    // ─── Prompts ─────────────────────────────────────────────────────────────

    protected function systemPrompt(): string
    {
        return <<<PROMPT
        Você é o Copiloto do oimpresso, um assistente de IA para gestores de pequenas e médias empresas brasileiras.
        Responda sempre em português brasileiro.
        Seja direto, prático e orientado a resultados.
        Nunca sugira ações ilegais ou antiéticas.
        Nunca invente dados — baseie-se apenas no contexto fornecido.
        Quando não tiver informação suficiente, peça esclarecimentos.
        PROMPT;
    }

    protected function montarPromptBriefing(ContextoNegocio $ctx): string
    {
        $fat = collect($ctx->faturamento90d)
            ->map(fn($m) => "  {$m['mes']}: R$ " . number_format($m['valor'], 2, ',', '.'))
            ->implode("\n");

        $metas = collect($ctx->metasAtivas)
            ->map(fn($m) => "  - {$m['nome']}: alvo R$ " . number_format($m['valor_alvo'], 2, ',', '.') . ' / realizado R$ ' . number_format($m['realizado'], 2, ',', '.'))
            ->implode("\n");

        return <<<PROMPT
        Faça um briefing rápido e amigável para o gestor da empresa "{$ctx->businessName}".

        Dados disponíveis:
        - Clientes ativos: {$ctx->clientesAtivos}
        - Faturamento últimos 90 dias (por mês):
        {$fat}
        - Módulos ativos: {$ctx->modulosAtivos}
        - Metas ativas:
        {$metas}

        Seja conciso (3-5 frases). Destaque tendência e uma oportunidade óbvia. Termine convidando o gestor a pedir sugestões de metas.
        PROMPT;
    }

    protected function montarPromptSugestoes(ContextoNegocio $ctx, string $promptUsuario): string
    {
        $fat = collect($ctx->faturamento90d)
            ->map(fn($m) => "  {$m['mes']}: R$ " . number_format($m['valor'], 2, ',', '.'))
            ->implode("\n");

        return <<<PROMPT
        Contexto da empresa "{$ctx->businessName}":
        - Clientes ativos: {$ctx->clientesAtivos}
        - Faturamento últimos 90 dias:
        {$fat}
        - Módulos ativos: {$ctx->modulosAtivos}

        Pedido do gestor: {$promptUsuario}

        Gere de 3 a 5 propostas de metas em 3 cenários de dificuldade (fácil, realista, ambicioso).
        Pelo menos uma proposta deve ser do tipo "realista".
        Baseie os valores numéricos no histórico fornecido — não invente dados.
        PROMPT;
    }

    // ─── Fixtures ────────────────────────────────────────────────────────────

    protected function fixtureBriefing(ContextoNegocio $ctx): string
    {
        $nomeBiz  = $ctx->businessName;
        $clientes = $ctx->clientesAtivos;

        return "Olá! Sou seu Copiloto. Estou olhando {$nomeBiz} — vejo {$clientes} clientes ativos "
            . 'e ' . count($ctx->faturamento90d) . ' meses de faturamento nos últimos 90 dias. '
            . 'Quer que eu sugira metas pro próximo período? É só pedir.';
    }

    protected function fixtureSugestoes(ContextoNegocio $ctx): array
    {
        return [
            [
                'nome'        => 'Faturamento — conservador',
                'metrica'     => 'faturamento',
                'valor_alvo'  => 120000,
                'periodo'     => 'mensal',
                'dificuldade' => 'facil',
                'racional'    => 'Manter base atual com +10% sobre média 90d.',
                'dependencias' => [],
            ],
            [
                'nome'        => 'Faturamento — realista',
                'metrica'     => 'faturamento',
                'valor_alvo'  => 180000,
                'periodo'     => 'mensal',
                'dificuldade' => 'realista',
                'racional'    => '+50% requer campanha em clientes B + upsell de módulos.',
                'dependencias' => ['Grow', 'PontoWr2 em 2 clientes'],
            ],
            [
                'nome'        => 'Faturamento — ambicioso',
                'metrica'     => 'faturamento',
                'valor_alvo'  => 300000,
                'periodo'     => 'mensal',
                'dificuldade' => 'ambicioso',
                'racional'    => 'Alavancagem total: ativar 49 businesses + captar 10 novos.',
                'dependencias' => ['Grow', 'Campanha reativação', 'Comercial dedicado'],
            ],
        ];
    }
}
