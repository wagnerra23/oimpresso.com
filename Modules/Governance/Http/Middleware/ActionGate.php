<?php

declare(strict_types=1);

namespace Modules\Governance\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Modules\Jana\Services\Privacy\PiiRedactor;
use Modules\TeamMcp\Services\ActorResolver;
use Symfony\Component\HttpFoundation\Response;

/**
 * ActionGate middleware — Constituição Art. 8 (Policy Gating).
 *
 * MVP modo `warn`: registra warning no log se actor sem capability tenta ação,
 * mas NÃO bloqueia. Coleta sinal pra calibrar policies antes de virar `strict`.
 *
 * Modo `strict` (futuro Fase 5+1): bloqueia com 403 + audit obrigatório.
 *
 * Configuração: `config('governance.actiongate_mode')` = off|warn|strict
 *
 * Uso (futuro): adicionar no kernel ou em rotas L2+:
 *   Route::middleware(['actiongate'])->...
 */
class ActionGate
{
    public function __construct(private ActorResolver $resolver)
    {
    }

    public function handle(Request $request, Closure $next, ?string $requiredTier = null): Response
    {
        $mode = config('governance.actiongate_mode', 'warn');

        if ($mode === 'off') {
            return $next($request);
        }

        $actor = $this->resolver->fromRequest($request);

        // Sem actor = sem rastreabilidade. Em strict, bloqueia. Em warn, log.
        if (!$actor) {
            $msg = 'ActionGate: request sem actor identificado';
            $route = $request->route()?->getName() ?? $request->path();
            $this->logViolation('no_actor', $route, null, $request);

            if ($mode === 'strict') {
                abort(403, 'No actor manifest declared');
            }
            return $next($request);
        }

        // Trust tier check (se requiredTier especificado na rota)
        if ($requiredTier !== null) {
            $actorLevel = $this->trustLevelToInt($actor->trust_level);
            $required = $this->trustLevelToInt($requiredTier);

            if ($actorLevel < $required) {
                $route = $request->route()?->getName() ?? $request->path();
                $this->logViolation('trust_insufficient', $route, $actor->slug, $request, [
                    'actor_tier' => $actor->trust_level,
                    'required_tier' => $requiredTier,
                ]);

                if ($mode === 'strict') {
                    abort(403, "Trust tier insuficiente: {$actor->trust_level} < {$requiredTier}");
                }
            }
        }

        // Actor revogado
        if ($actor->isRevoked()) {
            $route = $request->route()?->getName() ?? $request->path();
            $this->logViolation('actor_revoked', $route, $actor->slug, $request);
            if ($mode === 'strict') abort(403, 'Actor revogado');
        }

        // OK pra prosseguir. Em modo warn, request passa mesmo se violação leve.
        return $next($request);
    }

    private function trustLevelToInt(string $tier): int
    {
        // L0 é mais privilegiado (root); L4 é menos
        // Pra comparação "tem ao menos L1", precisa que actor.tier seja L0 ou L1
        // Trust level "L1" requerido = actor pode ser L0 ou L1
        // Convenção: número maior = mais permissivo (kernel)
        return match ($tier) {
            'L0' => 4,  // KERNEL — mais permissivo
            'L1' => 3,
            'L2' => 2,
            'L3' => 1,
            'L4' => 0,
            default => -1,
        };
    }

    private function logViolation(string $type, string $route, ?string $actorSlug, Request $request, array $extra = []): void
    {
        // D7 LGPD — redact PII (CPF/CNPJ/email/phone) antes de log/audit.
        // ActionGate violations vão pra log filesystem (storage/logs/laravel.log)
        // que é persistido + rotacionado — PII em claro = vazamento LGPD.
        //
        // Reusa Modules\Jana\Services\Privacy\PiiRedactor (canônico do projeto).
        // Sem-op se config('governance.pii_redaction_enabled')=false (debug local).
        $piiRedactionEnabled = (bool) config('governance.pii_redaction_enabled', true);

        $payload = array_merge([
            'type'        => $type,
            'route'       => $route,
            'actor'       => $actorSlug,
            'method'      => $request->method(),
            'ip'          => $request->ip(),
            'mode'        => config('governance.actiongate_mode', 'warn'),
        ], $extra);

        if ($piiRedactionEnabled && class_exists(PiiRedactor::class)) {
            // PiiRedactor canon trabalha sobre string; iterar campos sensíveis.
            // Mantém shape estável pro log parser (campos sempre presentes,
            // apenas valores redacted in-place).
            try {
                $redactor = app(PiiRedactor::class);
                foreach ($payload as $k => $v) {
                    if (is_string($v) && $v !== '') {
                        $payload[$k] = $redactor->redact($v);
                    }
                }
            } catch (\Throwable $e) {
                // Fail-open: log original sem PII redaction se Service indisponível
                // (Jana opcional em alguns ambientes). NÃO bloqueia ActionGate.
                Log::channel('single')->debug('ActionGate PiiRedactor falhou (fail-open)', [
                    'exception' => $e->getMessage(),
                ]);
            }
        }

        Log::channel('single')->warning('ActionGate violation', $payload);
    }
}
