<?php

namespace Modules\Copiloto\Services\Ai;

use Illuminate\Support\Facades\Log;
use Modules\Copiloto\Ai\Agents\BriefingAgent;
use Modules\Copiloto\Ai\Agents\ChatCopilotoAgent;
use Modules\Copiloto\Ai\Agents\SugestoesMetasAgent;
use Modules\Copiloto\Contracts\AiAdapter;
use Modules\Copiloto\Contracts\MemoriaContrato;
use Modules\Copiloto\Entities\Conversa;
use Modules\Copiloto\Entities\Mensagem;
use Modules\Copiloto\Jobs\ExtrairFatosDaConversaJob;
use Modules\Copiloto\Support\ContextoNegocio;

/**
 * LaravelAiSdkDriver — driver canônico de IA do Copiloto.
 *
 * Stack-alvo (verdade canônica — ADR 0035):
 *  - Camada A: laravel/ai (Laravel AI SDK oficial fev/2026) — este driver
 *  - Camada B: vizra/vizra-adk (sprints 2-3, ADR 0032)
 *  - Camada C: MemoriaContrato + Mem0RestDriver/MeilisearchDriver (sprints 4-5/8-10, ADRs 0031+0033)
 *
 * Substitui OpenAiDirectDriver (que dependia de openai-php/laravel abandonado).
 * Mantém sanitização de CPF/CNPJ herdada do driver legado.
 */
class LaravelAiSdkDriver implements AiAdapter
{
    public function gerarBriefing(ContextoNegocio $ctx): string
    {
        if (config('copiloto.dry_run')) {
            return $this->fixtureBriefing($ctx);
        }

        $ctxSanitizado = $this->sanitizarContexto($ctx);
        $agent = new BriefingAgent($ctxSanitizado);

        try {
            $response = $agent->prompt($agent->montarPromptBriefing());

            Log::channel('copiloto-ai')->info('gerarBriefing', [
                'business_id' => $ctx->businessId,
                'driver' => 'laravel_ai_sdk',
            ]);

            return (string) $response;
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

        $ctxSanitizado = $this->sanitizarContexto($ctx);
        $agent = new SugestoesMetasAgent($ctxSanitizado, $prompt);

        try {
            $response = $agent->prompt($agent->montarPromptSugestoes());

            Log::channel('copiloto-ai')->info('sugerirMetas', [
                'business_id' => $ctx->businessId,
                'driver' => 'laravel_ai_sdk',
            ]);

            $propostas = $response['propostas'] ?? null;

            if (! is_array($propostas) || empty($propostas)) {
                Log::channel('copiloto-ai')->warning('sugerirMetas: shape inválido, usando fixture');

                return $this->fixtureSugestoes($ctx);
            }

            foreach ($propostas as $p) {
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

            return $propostas;
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

        // Sprint 5 (ADR 0036) — recall de memória semântica antes de chamar LLM.
        $memoriaContexto = $this->recallMemoria($conv, $mensagem);

        $agent = new ChatCopilotoAgent($conv, $memoriaContexto);

        try {
            $response = $agent->prompt($mensagem);
            $texto = (string) $response;

            Mensagem::where('conversa_id', $conv->id)
                ->where('role', 'assistant')
                ->latest('created_at')
                ->first()
                ?->update([
                    'tokens_in' => $response->usage->promptTokens ?? null,
                    'tokens_out' => $response->usage->completionTokens ?? null,
                ]);

            Log::channel('copiloto-ai')->info('responderChat', [
                'conversa_id' => $conv->id,
                'driver' => 'laravel_ai_sdk',
                'memoria_recall_chars' => strlen($memoriaContexto),
            ]);

            // Sprint 5 — após resposta, extrair fatos novos em background (Horizon).
            if (config('copiloto.memoria.write_enabled', true)) {
                ExtrairFatosDaConversaJob::dispatch(
                    conversaId: $conv->id,
                    businessId: (int) $conv->business_id,
                    userId: (int) $conv->user_id,
                );
            }

            return $texto;
        } catch (\Throwable $e) {
            Log::channel('copiloto-ai')->error('responderChat error: ' . $e->getMessage());

            return 'Estou sem conexão com IA no momento. Você quer criar a meta manualmente?';
        }
    }

    /**
     * Busca top-K memórias relevantes via MemoriaContrato e retorna texto pronto pra
     * injetar como system additional message. Falha silente — recall não pode quebrar chat.
     */
    protected function recallMemoria(Conversa $conv, string $query): string
    {
        if (! config('copiloto.memoria.recall_enabled', true)) {
            return '';
        }

        try {
            /** @var MemoriaContrato $memoria */
            $memoria = app(MemoriaContrato::class);
            $topK = (int) config('copiloto.memoria.meilisearch.top_k_default', 5);

            $resultados = $memoria->buscar(
                businessId: (int) $conv->business_id,
                userId: (int) $conv->user_id,
                query: $query,
                topK: $topK,
            );

            if (empty($resultados)) {
                return '';
            }

            $linhas = collect($resultados)
                ->map(fn ($m) => '- ' . $m->fato)
                ->implode("\n");

            return "Você lembra dos seguintes fatos sobre este usuário/business:\n{$linhas}\n";
        } catch (\Throwable $e) {
            Log::channel('copiloto-ai')->warning('recallMemoria falhou (degradação silenciosa)', [
                'conversa_id' => $conv->id,
                'error' => $e->getMessage(),
            ]);
            return '';
        }
    }

    public function mascararDocumentos(string $texto): string
    {
        $texto = preg_replace('/\b\d{3}\.?\d{3}\.?\d{3}-?\d{2}\b/', 'XXX.XXX.XXX-NN', $texto);
        $texto = preg_replace('/\b\d{2}\.?\d{3}\.?\d{3}\/?0001-?\d{2}\b/', 'XX.XXX.XXX/0001-NN', $texto);

        return $texto;
    }

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

    protected function fixtureBriefing(ContextoNegocio $ctx): string
    {
        $nomeBiz = $ctx->businessName;
        $clientes = $ctx->clientesAtivos;

        return "Olá! Sou seu Copiloto. Estou olhando {$nomeBiz} — vejo {$clientes} clientes ativos "
            . 'e ' . count($ctx->faturamento90d) . ' meses de faturamento nos últimos 90 dias. '
            . 'Quer que eu sugira metas pro próximo período? É só pedir.';
    }

    protected function fixtureSugestoes(ContextoNegocio $ctx): array
    {
        return [
            [
                'nome' => 'Faturamento — conservador',
                'metrica' => 'faturamento',
                'valor_alvo' => 120000,
                'periodo' => 'mensal',
                'dificuldade' => 'facil',
                'racional' => 'Manter base atual com +10% sobre média 90d.',
                'dependencias' => [],
            ],
            [
                'nome' => 'Faturamento — realista',
                'metrica' => 'faturamento',
                'valor_alvo' => 180000,
                'periodo' => 'mensal',
                'dificuldade' => 'realista',
                'racional' => '+50% requer campanha em clientes B + upsell de módulos.',
                'dependencias' => ['Grow', 'PontoWr2 em 2 clientes'],
            ],
            [
                'nome' => 'Faturamento — ambicioso',
                'metrica' => 'faturamento',
                'valor_alvo' => 300000,
                'periodo' => 'mensal',
                'dificuldade' => 'ambicioso',
                'racional' => 'Alavancagem total: ativar 49 businesses + captar 10 novos.',
                'dependencias' => ['Grow', 'Campanha reativação', 'Comercial dedicado'],
            ],
        ];
    }
}
