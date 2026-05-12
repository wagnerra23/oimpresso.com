<?php

declare(strict_types=1);

namespace Modules\Whatsapp\Services\Audio;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\Whatsapp\Services\Audio\Contracts\AudioTranscriber;
use RuntimeException;

/**
 * US-WA-072 — Whisper transcriber via OpenAI API.
 *
 * Provider único nesta fase (`openai`). Fallback Ollama whisper-local CT 100
 * fica em US separada (decisão pendente SPEC §1 — adiar até OpenAI custo
 * mensal estável > R$50 ou Ollama whisper-large local validado).
 *
 * Endpoint:   POST https://api.openai.com/v1/audio/transcriptions
 * Model:      whisper-1 (padrão) ou gpt-4o-mini-transcribe (config-driven)
 * Auth:       Bearer OPENAI_API_KEY (env, NÃO commitado)
 * Response:   {"text": "transcrição português..."}
 *
 * Custo: $0.006/min audio (whisper-1) ou $0.003/min (gpt-4o-mini-transcribe).
 * Rate limit anti-abuse: 100min/business/dia enforçado em
 * `TranscribeAudioJob::handle()` (via Cache::increment), não aqui — esse
 * service é dumb wrapper HTTP.
 *
 * @see memory/requisitos/Whatsapp/SPEC.md US-WA-072 §Whisper integração
 * @see memory/decisions/0035-stack-ai-canonica-wagner-2026-04-26.md
 */
class WhisperTranscriber implements AudioTranscriber
{
    public function __construct(
        protected ?string $apiKey = null,
        protected string $model = 'whisper-1',
        protected string $endpoint = 'https://api.openai.com/v1/audio/transcriptions',
        protected int $timeoutSeconds = 60,
    ) {
        $this->apiKey ??= (string) config('whatsapp.audio.transcription.api_key', env('OPENAI_API_KEY'));
        $this->model = (string) config('whatsapp.audio.transcription.model', $model);
        $this->endpoint = (string) config('whatsapp.audio.transcription.endpoint', $endpoint);
        $this->timeoutSeconds = (int) config('whatsapp.audio.transcription.timeout', $timeoutSeconds);
    }

    /**
     * Transcreve áudio a partir do caminho absoluto local.
     *
     * @param string $absolutePath Caminho absoluto pro arquivo (ogg/mp3/m4a/wav)
     * @param string $language     ISO 639-1 (pt = português; OpenAI auto-detecta se vazio)
     *
     * @return string Texto transcrito (pode ser vazio se áudio mudo)
     * @throws RuntimeException Em falha de auth/rede/parse
     */
    public function transcribe(string $absolutePath, string $language = 'pt'): string
    {
        if (! $this->apiKey) {
            throw new RuntimeException('OPENAI_API_KEY ausente — transcrição desabilitada.');
        }

        if (! is_file($absolutePath)) {
            throw new RuntimeException("Arquivo de áudio não existe: {$absolutePath}");
        }

        $response = $this->httpClient()
            ->attach('file', file_get_contents($absolutePath), basename($absolutePath))
            ->post($this->endpoint, [
                'model' => $this->model,
                'language' => $language,
                'response_format' => 'json',
            ]);

        if (! $response->successful()) {
            Log::warning('[whisper] API error', [
                'status' => $response->status(),
                'body' => mb_substr($response->body(), 0, 240),
            ]);
            throw new RuntimeException(
                "Whisper API retornou {$response->status()}: " . mb_substr($response->body(), 0, 200)
            );
        }

        $json = $response->json();
        $text = (string) ($json['text'] ?? '');

        return trim($text);
    }

    protected function httpClient(): PendingRequest
    {
        return Http::withToken($this->apiKey)
            ->timeout($this->timeoutSeconds)
            ->acceptJson();
    }
}
