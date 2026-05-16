<?php

declare(strict_types=1);

namespace Modules\Whatsapp\Services\Audio\Contracts;

/**
 * Contrato pra serviços de transcrição de áudio (US-WA-072).
 *
 * Observabilidade D9.a (ADR 0155): implementações concretas envolvem
 * `transcribe()` em `OtelHelper::span(` — Tracer registra latência + erros.
 *
 * Implementações:
 *  - `WhisperTranscriber` (OpenAI API, default Hostinger)
 *  - `NullTranscriber` (testes / dev sem OpenAI key)
 *  - (futuro) `OllamaWhisperTranscriber` (CT 100 self-host)
 */
interface AudioTranscriber
{
    /**
     * @param string $absolutePath Caminho absoluto pro arquivo de áudio local
     * @param string $language     ISO 639-1 ('pt', 'en', ...). Default 'pt'.
     *
     * @return string Texto transcrito. Vazio = áudio mudo / silêncio.
     * @throws \RuntimeException Em falha de auth/rede/parse
     */
    public function transcribe(string $absolutePath, string $language = 'pt'): string;
}
