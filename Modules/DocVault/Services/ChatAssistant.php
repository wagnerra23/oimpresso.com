<?php

namespace Modules\DocVault\Services;

/**
 * Responde perguntas sobre o conhecimento consolidado no DocVault.
 *
 * Modo offline (default): busca por keyword nos arquivos do(s) módulo(s),
 * retorna trechos relevantes citando a fonte. Útil sem API key.
 *
 * Modo AI: monta contexto RAG e delega ao provedor LLM. Só roda quando
 * config('docvault.ai.enabled') = true E OPENAI_API_KEY setada.
 */
class ChatAssistant
{
    public function __construct(
        protected RequirementsFileReader $reader
    ) {}

    /**
     * @return array{reply: string, sources: array, mode: string, tokens_used: ?int}
     */
    public function ask(string $question, ?string $moduleContext = null): array
    {
        $snippets = $this->retrieve($question, $moduleContext);

        if (empty($snippets)) {
            return [
                'reply'       => "Não encontrei nada relevante nos documentos do DocVault sobre isso. Tente termos diferentes ou especifique um módulo.",
                'sources'     => [],
                'mode'        => 'offline',
                'tokens_used' => null,
            ];
        }

        // Modo AI (opcional, desligado por padrão)
        if ($this->aiEnabled()) {
            return $this->askWithAi($question, $snippets);
        }

        // Modo offline: monta resposta narrando os snippets
        return $this->buildOfflineReply($question, $snippets);
    }

    /**
     * Busca snippets relevantes nos arquivos dos módulos. Keyword-based, simples.
     */
    protected function retrieve(string $question, ?string $moduleContext): array
    {
        $modules = $moduleContext
            ? [$this->reader->readModule($moduleContext)]
            : $this->reader->listModules();

        $modules = array_filter($modules);
        $terms = $this->extractTerms($question);
        if (empty($terms)) return [];

        $hits = [];
        foreach ($modules as $m) {
            // Quando vem de listModules, não tem conteúdo bruto — carregamos on-demand
            $data = $m['name'] ? $this->reader->readModule($m['name']) : null;
            if (! $data) continue;

            // Procura em cada um dos blobs disponíveis
            $sources = [
                'README'       => $data['readme'] ?? null,
                'ARCHITECTURE' => $data['architecture'] ?? null,
                'SPEC'         => $data['raw'] ?? null,
                'CHANGELOG'    => $data['changelog'] ?? null,
            ];

            foreach ($sources as $sourceName => $blob) {
                if (! $blob) continue;
                $score = $this->scoreText($blob, $terms);
                if ($score > 0) {
                    $snippet = $this->extractSnippet($blob, $terms);
                    $hits[] = [
                        'module'  => $data['name'],
                        'source'  => $sourceName,
                        'score'   => $score,
                        'snippet' => $snippet,
                    ];
                }
            }

            // ADRs: cada ADR é item separado (pra citação precisa)
            foreach ($data['adrs'] ?? [] as $adr) {
                $score = $this->scoreText($adr['raw'], $terms);
                if ($score > 0) {
                    $hits[] = [
                        'module'  => $data['name'],
                        'source'  => "ADR {$adr['number']}",
                        'title'   => $adr['title'],
                        'score'   => $score * 1.2, // pesa ADR um pouco mais (decisão > texto)
                        'snippet' => $this->extractSnippet($adr['raw'], $terms),
                    ];
                }
            }
        }

        usort($hits, fn ($a, $b) => $b['score'] <=> $a['score']);
        return array_slice($hits, 0, 6);
    }

    protected function extractTerms(string $question): array
    {
        $q = mb_strtolower($question);
        $q = preg_replace('/[^\p{L}\p{N}\s\-]+/u', ' ', $q);
        $words = preg_split('/\s+/', trim($q));
        // stopwords pt/en minimalistas
        $stop = ['o','a','os','as','de','do','da','dos','das','um','uma','que','qual','como','onde','quando','por','para','pra','em','no','na','se','e','ou','the','a','an','of','to','in','on','is','are','what','how','where','when','why','it','that'];
        $terms = array_filter($words, fn ($w) => mb_strlen($w) >= 3 && ! in_array($w, $stop, true));
        return array_values(array_unique($terms));
    }

    protected function scoreText(string $text, array $terms): float
    {
        $lower = mb_strtolower($text);
        $score = 0;
        foreach ($terms as $t) {
            $count = mb_substr_count($lower, $t);
            $score += $count * (mb_strlen($t) >= 5 ? 2 : 1);
        }
        return $score;
    }

    protected function extractSnippet(string $text, array $terms, int $ctx = 160): string
    {
        $lower = mb_strtolower($text);
        foreach ($terms as $t) {
            $pos = mb_strpos($lower, $t);
            if ($pos !== false) {
                $start = max(0, $pos - $ctx);
                $len = $ctx * 2 + mb_strlen($t);
                $snippet = mb_substr($text, $start, $len);
                return ($start > 0 ? '…' : '') . trim($snippet) . (mb_strlen($text) > $start + $len ? '…' : '');
            }
        }
        return mb_substr($text, 0, $ctx * 2);
    }

    protected function buildOfflineReply(string $question, array $snippets): array
    {
        $lines = ["Encontrei " . count($snippets) . " trecho(s) relevante(s) no DocVault:\n"];
        foreach ($snippets as $i => $s) {
            $title = $s['title'] ?? $s['source'];
            $lines[] = sprintf("%d. **%s · %s** (score %.1f)", $i + 1, $s['module'], $title, $s['score']);
            $lines[] = "   > " . $s['snippet'];
            $lines[] = '';
        }
        $lines[] = "*(Modo offline — busca por keyword. Pra respostas sintéticas, ative `DOCVAULT_AI_ENABLED=true` no .env e configure `OPENAI_API_KEY`.)*";

        return [
            'reply'       => implode("\n", $lines),
            'sources'     => array_map(fn ($s) => ['module' => $s['module'], 'source' => $s['source']], $snippets),
            'mode'        => 'offline',
            'tokens_used' => null,
        ];
    }

    protected function aiEnabled(): bool
    {
        return (bool) config('docvault.ai.enabled', false) && env('OPENAI_API_KEY');
    }

    protected function askWithAi(string $question, array $snippets): array
    {
        // Stub: integração real com OpenAI vem quando o AI for ligado de fato.
        // Por ora, comporta-se como offline + marca mode=ai pra rastrear intenção.
        $base = $this->buildOfflineReply($question, $snippets);
        $base['mode'] = 'ai';
        $base['reply'] = "[MODO AI STUB] " . $base['reply'];
        return $base;
    }
}
