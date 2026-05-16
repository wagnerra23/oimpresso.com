<?php

declare(strict_types=1);

namespace Modules\Whatsapp\Services\Notes;

/**
 * Observabilidade D9.a (ADR 0155): DTO puro — Tracer via
 * `OtelHelper::span(` reside no handler que produz este DTO.
 *
 * SlashCommandResult — DTO imutável do resultado de execução de um
 * {@see SlashCommandHandler}.
 *
 * Convenção: o resultado é puramente view-state (vai pra flash session
 * pra UI renderizar badge). NÃO altera o estado da Message original —
 * a nota interna sempre persiste como `is_internal_note=true`, independente
 * do sucesso/erro do handler.
 *
 * Convenção do `badge`: texto curto pra UI exibir como chip clicável ao
 * lado da bubble da nota (ex.: "✓ memorizado", "✓ lembrete agendado").
 * `link_url` é opcional — quando preenchido, badge vira link clicável.
 *
 *   success      → flash 'success' (verde) + badge + link
 *   error        → flash 'warning' (amarelo) — comando opcional, nota é válida
 *   unrecognized → ignora silenciosamente (não polui UI com warnings)
 *
 * @see SlashCommandHandler
 * @see memory/decisions/0142-notas-internas-sinal-treino-jana.md
 */
final class SlashCommandResult
{
    public const KIND_SUCCESS = 'success';
    public const KIND_ERROR = 'error';
    public const KIND_UNRECOGNIZED = 'unrecognized';

    public function __construct(
        public readonly string $kind,
        public readonly ?string $badge = null,
        public readonly ?string $linkUrl = null,
        public readonly ?string $errorMessage = null,
    ) {
    }

    public static function success(string $badge, ?string $linkUrl = null): self
    {
        return new self(self::KIND_SUCCESS, $badge, $linkUrl, null);
    }

    public static function error(string $errorMessage): self
    {
        return new self(self::KIND_ERROR, null, null, $errorMessage);
    }

    /**
     * Comando não registrado OU graceful no-op (arguments vazio, sintaxe ok mas
     * sem semântica). Convenção: UI ignora completamente — nota interna
     * continua válida sem warning.
     */
    public static function unrecognized(): self
    {
        return new self(self::KIND_UNRECOGNIZED, null, null, null);
    }

    public function isSuccess(): bool
    {
        return $this->kind === self::KIND_SUCCESS;
    }

    public function isError(): bool
    {
        return $this->kind === self::KIND_ERROR;
    }

    public function isUnrecognized(): bool
    {
        return $this->kind === self::KIND_UNRECOGNIZED;
    }

    public function toFlashPayload(): array
    {
        return [
            'kind' => $this->kind,
            'badge' => $this->badge,
            'link_url' => $this->linkUrl,
            'error_message' => $this->errorMessage,
        ];
    }
}
