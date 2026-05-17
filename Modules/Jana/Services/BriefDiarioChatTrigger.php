<?php

declare(strict_types=1);

namespace Modules\Jana\Services;

use App\Util\OtelHelper;
use Modules\Jana\Ai\Agents\BriefDiarioAgent;
use Modules\Jana\Entities\Conversa;
use Throwable;

/**
 * BriefDiarioChatTrigger (US-COPI-203) — intent detection no chat Jana.
 *
 * Quando user digita algo como "brief", "/brief", "como tá meu negócio hoje",
 * "manda o brief diário" etc, intercepta antes do ChatCopilotoAgent normal e
 * dispara BriefDiarioAgent que retorna markdown formatado Versão A.
 *
 * Pattern simples regex-first (zero custo) — se quiser intent classification
 * via LLM no futuro, troca aqui SEM mexer no controller.
 *
 * @see memory/requisitos/Copiloto/JANA-PRO-PRODUCT-PLAN.md (US-COPI-203)
 * @see memory/decisions/0140-jana-pro-produto-comercial-saas.md
 */
class BriefDiarioChatTrigger
{
    /**
     * Patterns que ativam o brief shortcut. Case-insensitive, regex.
     *
     * Coberturas (não exaustivo):
     *  - "/brief", "brief", "brief diário", "brief de hoje"
     *  - "manda o brief", "gera brief", "quero o brief"
     *  - "como tá meu negócio hoje", "como vai o negócio"
     *  - "resumo do dia", "panorama hoje", "como foi a semana"
     */
    // PCRE flag `u` (unicode) é obrigatória — word boundary `\b` quebra com
    // caracteres multi-byte (á, ê, ó) sem ela.
    private const TRIGGER_PATTERNS = [
        '#^\s*/brief\b#iu',
        '#\bbrief\s+(di[aá]rio|de\s+hoje|do\s+dia|executivo|jana\s+pro)#iu',
        '#\b(manda|gera|gere|quero|me\s+d[aá])\s+(o\s+)?brief\b#iu',
        '#\bcomo\s+(t[aá]|vai|est[aá])\s+(meu\s+|o\s+)?neg[oó]cio\b#iu',
        '#\bresumo\s+(do\s+)?dia\b#iu',
        '#\bpanorama\s+(de\s+)?hoje\b#iu',
        '#\bcomo\s+foi\s+(a\s+|o\s+)?(semana|m[eê]s|ontem|dia)\b#iu',
        '#\bjana\s+pro\b#iu',
        '#^\s*brief\s*$#iu',
    ];

    /**
     * Retorna true se a mensagem do user é intent de brief diário.
     */
    public function matches(string $userInput): bool
    {
        foreach (self::TRIGGER_PATTERNS as $pattern) {
            if (preg_match($pattern, $userInput)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Gera o brief diário pra business da conversa.
     *
     * Retorna markdown ~300-500 palavras (formato Versão A Dashboard) OU
     * mensagem de erro graceful se LLM falhar (não vaza stack trace).
     *
     * Tier 0 mecânico: business_id vem da Conversa, NUNCA da mensagem do user.
     */
    public function gerar(Conversa $conversa): string
    {
        // D9.a (Wave 18 SATURATION) — span brief chat-trigger; business_id explícito Tier 0.
        return OtelHelper::span('jana.brief.chat_trigger.gerar', [
            'business_id' => $conversa->business_id,
            'conversa_id' => $conversa->id,
        ], fn () => $this->gerarInternal($conversa));
    }

    private function gerarInternal(Conversa $conversa): string
    {
        try {
            $businessName = $this->resolveBusinessName($conversa->business_id);

            $agent = new BriefDiarioAgent(
                businessId: $conversa->business_id,
                businessName: $businessName,
            );

            $response = $agent->prompt(
                'Gere o brief diário executivo de hoje seguindo a estrutura canônica '
                .'(Versão A Dashboard). Use as tools pra puxar dados reais.'
            );

            $markdown = (string) $response;

            // Anti-vazamento: se response veio vazia/curtíssima, fallback amigável
            if (mb_strlen(trim($markdown)) < 50) {
                return "Não consegui gerar o brief agora. Tenta de novo daqui a 1 min, "
                    ."ou pede pra ver alguma fonte específica (vendas, oportunidades, etc).";
            }

            return $markdown;
        } catch (Throwable $e) {
            // Log estruturado pra observability (não vaza PII no chat)
            \Illuminate\Support\Facades\Log::error('BriefDiarioChatTrigger falhou', [
                'business_id' => $conversa->business_id,
                'conversa_id' => $conversa->id,
                'error_class' => get_class($e),
                'error_msg' => mb_substr($e->getMessage(), 0, 200),
            ]);

            return "Brief temporariamente indisponível. Tenta de novo daqui a 1 min "
                ."ou me pergunta algo específico (vendas, clientes inadimplentes, etc).";
        }
    }

    /**
     * Resolve nome do business via tabela `business` (UltimatePOS) sem
     * dependência hard — se tabela ausente em test, devolve null.
     */
    private function resolveBusinessName(int $businessId): ?string
    {
        try {
            if (! \Illuminate\Support\Facades\Schema::hasTable('business')) {
                return null;
            }
            $row = \Illuminate\Support\Facades\DB::table('business')
                ->where('id', $businessId)
                ->first(['name']);
            return $row?->name;
        } catch (Throwable) {
            return null;
        }
    }
}
