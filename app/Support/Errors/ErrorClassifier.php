<?php

declare(strict_types=1);

namespace App\Support\Errors;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Validation\ValidationException;
use PDOException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * ErrorClassifier — o coração da Fase 1: carimba todo erro na origem.
 *
 * `classify()` devolve `{severity, audience, owner, dedupKey, operatorMessage}`.
 * Regras na ordem (primeira que casar vence), espelhando o Mapa de Severidade [W]:
 *
 *   1. CrossTenantViolation        → S0 (Tier 0, o "S0 silencioso")
 *   2. ClassifiedError             → severidade auto-declarada pelo domínio
 *   3. Banco indisponível (conexão)→ S0
 *   4. Exceções esperadas/tratadas → S3 (ruído conhecido, sem alerta)
 *   5. default                     → S3
 *
 * @see prototipo-ui/handoffs/erros-fase1-classificacao.md
 */
class ErrorClassifier
{
    public function classify(Throwable $e, ?Request $request = null): Classification
    {
        $dedupKey = $this->dedupKey($e);

        // 1. Cross-tenant — Tier 0, sempre S0 (ADR 0093). Operador só vê negação genérica.
        if ($e instanceof CrossTenantViolation) {
            return new Classification(
                Severity::S0,
                Audience::CONSTRUTOR,
                'plataforma',
                $dedupKey,
                'Você não tem acesso a esse recurso.',
            );
        }

        // 2. Domínio se auto-classifica (caminho confiável — quem lança sabe).
        if ($e instanceof ClassifiedError) {
            return new Classification(
                $e->severity(),
                $e->audience(),
                $e->owner(),
                $dedupKey,
                $e->operatorMessage(),
            );
        }

        // 3. Banco indisponível (conexão caiu) → S0. NÃO todo QueryException — só conexão.
        if ($this->isDatabaseUnavailable($e)) {
            return new Classification(
                Severity::S0,
                Audience::CONSTRUTOR,
                'plataforma',
                $dedupKey,
                'Sistema temporariamente indisponível. Já fomos avisados — tente em instantes.',
            );
        }

        // 4. Exceções esperadas/tratadas → S3 (ruído conhecido, sem alerta).
        if ($this->isExpected($e)) {
            return new Classification(
                Severity::S3,
                Audience::OPERADOR,
                'app',
                $dedupKey,
                $this->operatorMessageFor($e),
            );
        }

        // 5. default → S3 (bug ainda não graduado; vai pro dashboard, não alerta).
        return new Classification(
            Severity::S3,
            Audience::OPERADOR,
            'app',
            $dedupKey,
            'Algo deu errado. Tente novamente — se persistir, chame o suporte.',
        );
    }

    /**
     * dedupKey = hash(classe + local + business_id) — Fase 2 agrupa; aqui já carimba.
     * `local` = arquivo:linha de origem (não vaza PII — é caminho de código).
     */
    public function dedupKey(Throwable $e): string
    {
        $local = $e->getFile().':'.$e->getLine();

        return sha1(get_class($e).'|'.$local.'|'.$this->businessId());
    }

    /**
     * Detecta banco indisponível (conexão), não erro de query qualquer.
     * Olha a cadeia de `getPrevious()` por PDOException com SQLSTATE classe 08
     * (connection exception) ou mensagem de conexão recusada/servidor fora.
     */
    private function isDatabaseUnavailable(Throwable $e): bool
    {
        $connectionStates = ['08001', '08003', '08004', '08006', '08S01'];
        $cursor = $e;

        while ($cursor !== null) {
            if ($cursor instanceof PDOException) {
                $sqlState = is_array($cursor->errorInfo) ? ($cursor->errorInfo[0] ?? null) : (string) $cursor->getCode();
                if ($sqlState !== null && in_array($sqlState, $connectionStates, true)) {
                    return true;
                }
                $msg = $cursor->getMessage();
                if (str_contains($msg, 'Connection refused')
                    || str_contains($msg, 'server has gone away')
                    || str_contains($msg, 'could not find driver')
                    || str_contains($msg, "Can't connect")) {
                    return true;
                }
            }
            $cursor = $cursor->getPrevious();
        }

        return false;
    }

    private function isExpected(Throwable $e): bool
    {
        return $e instanceof ValidationException
            || $e instanceof AuthenticationException
            || $e instanceof AuthorizationException
            || $e instanceof ModelNotFoundException
            || $e instanceof TokenMismatchException
            || $e instanceof HttpExceptionInterface;
    }

    private function operatorMessageFor(Throwable $e): string
    {
        return match (true) {
            $e instanceof ValidationException => 'Confira os campos destacados e tente de novo.',
            $e instanceof AuthenticationException => 'Sua sessão expirou. Entre novamente.',
            $e instanceof AuthorizationException => 'Você não tem permissão para isso.',
            $e instanceof ModelNotFoundException => 'Não encontramos o que você procurou.',
            default => 'Não foi possível concluir agora. Tente novamente.',
        };
    }

    /**
     * business_id do contexto — mesmo padrão de {@see \App\Util\OtelHelper::spanBiz}.
     * session() não existe em CLI/queue/Unit sem TestCase → try/catch + fallback 0.
     */
    private function businessId(): int
    {
        try {
            $bizId = session()->get('user.business_id');
        } catch (Throwable) {
            $bizId = null;
        }
        $bizId ??= optional(auth()->user())->business_id ?? 0;

        return (int) $bizId;
    }
}
