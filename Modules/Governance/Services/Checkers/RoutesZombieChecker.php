<?php

declare(strict_types=1);

namespace Modules\Governance\Services\Checkers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Modules\Governance\Contracts\DriftChecker;
use Modules\Governance\Services\DriftCheckResult;
use Modules\Governance\Services\DriftFinding;

/**
 * RoutesZombieChecker — detecta routes declaradas sem hit no log de acesso.
 *
 * ADR 0221 (filha de 0216).
 *
 * Lógica:
 *   1. Snapshot todas Route::getRoutes() (GET-only por padrão; configurável)
 *   2. Cross-check com `system_access_log` ou `mcp_audit_log.route_name` últimos 30d
 *   3. Routes em "zombie window" (registered >14d ago + 0 hits 30d) viram finding
 *
 * Severity: low (route morta != bug, mas é tech debt + blast radius extra)
 * Enforcement: advisory (NÃO bloqueia merge; só Brief Jana mensal)
 * Cadence: weekly (route activity stats são caros — não roda toda noite)
 *
 * Exceções legítimas:
 *   - Health checks (`/healthz`, `/up`)
 *   - Webhooks externos low-frequency (Asaas, Sicoob, Mailgun, Stripe)
 *   - Routes de feature flag desabilitada
 * Allowlist via `config/governance.php > routes_zombie_allowlist[]` (regex patterns).
 *
 * Implementação MVP: usa tabela `system_access_log` se existir; senão skip + log warn.
 * Sprint 2: integrar com `mcp_observability_spans` (ADR 0162) pra ter timeline.
 */
final class RoutesZombieChecker implements DriftChecker
{
    private const DEFAULT_HIT_WINDOW_DAYS = 30;

    public function name(): string
    {
        return 'routes_zombie';
    }

    public function description(): string
    {
        return 'Routes declaradas sem hits em N dias (tech debt + blast radius)';
    }

    public function tags(): array
    {
        return ['tier_2', 'tech_debt', 'observability'];
    }

    public function severity(): string
    {
        return 'low';
    }

    public function enforcement(): string
    {
        return 'advisory';
    }

    public function cadence(): string
    {
        return 'weekly';
    }

    public function check(array $opts = []): DriftCheckResult
    {
        $start = microtime(true);

        $routes = $this->snapshotRoutes();
        $accessLog = $this->fetchAccessLogStats(self::DEFAULT_HIT_WINDOW_DAYS);

        if ($accessLog === null) {
            return DriftCheckResult::clean($this->name(), 0, [
                'skipped' => 'access log table not available — Sprint 2 ADR 0162',
                'routes_total' => count($routes),
            ]);
        }

        $allowlist = $this->loadAllowlist();
        $findings = [];

        foreach ($routes as $routeData) {
            $name = $routeData['name'];
            $uri = $routeData['uri'];

            // Skip allowlist
            if ($this->matchesAllowlist($uri, $allowlist) || $this->matchesAllowlist($name ?? '', $allowlist)) {
                continue;
            }

            $hits = $accessLog[$uri] ?? 0;
            if ($hits > 0) {
                continue;
            }

            $findings[] = new DriftFinding(
                target: $uri,
                target_type: 'route',
                severity: 'low',
                message: sprintf(
                    'Route %s (%s) sem hits nos últimos %dd. Ação: verificar se feature em uso, ' .
                    'remover route OR adicionar pattern em config/governance.php > routes_zombie_allowlist.',
                    $uri,
                    $name ?? '(unnamed)',
                    self::DEFAULT_HIT_WINDOW_DAYS,
                ),
                evidence: [
                    'uri' => $uri,
                    'name' => $name,
                    'method' => $routeData['method'],
                    'action' => $routeData['action'],
                    'window_days' => self::DEFAULT_HIT_WINDOW_DAYS,
                    'hits_observed' => $hits,
                ],
            );
        }

        $durationMs = (int) ((microtime(true) - $start) * 1000);

        return count($findings) === 0
            ? DriftCheckResult::clean($this->name(), $durationMs, [
                'routes_scanned' => count($routes),
                'window_days' => self::DEFAULT_HIT_WINDOW_DAYS,
            ])
            : DriftCheckResult::drifted(
                name: $this->name(),
                findings: $findings,
                duration_ms: $durationMs,
                metadata: [
                    'routes_scanned' => count($routes),
                    'zombie_count' => count($findings),
                    'window_days' => self::DEFAULT_HIT_WINDOW_DAYS,
                ],
            );
    }

    /**
     * @return array<int, array{uri: string, method: string, name: ?string, action: string}>
     */
    private function snapshotRoutes(): array
    {
        $out = [];
        foreach (Route::getRoutes()->getRoutes() as $route) {
            $methods = $route->methods();
            // Apenas GET/POST publicly-callable (ignora HEAD, OPTIONS, internals)
            $relevantMethods = array_intersect($methods, ['GET', 'POST', 'PUT', 'PATCH', 'DELETE']);
            if (count($relevantMethods) === 0) {
                continue;
            }
            $uri = '/' . ltrim($route->uri(), '/');
            // getActionName() já retorna 'Closure' pra rotas closure (Laravel core),
            // sempre string — ternário is_string() era redundante.
            $action = $route->getActionName();
            $out[] = [
                'uri' => $uri,
                'method' => implode('|', $relevantMethods),
                'name' => $route->getName(),
                'action' => $action,
            ];
        }

        return $out;
    }

    /**
     * Retorna [uri => hit_count] últimos N dias, ou null se tabela ausente.
     *
     * @return array<string, int>|null
     */
    private function fetchAccessLogStats(int $windowDays): ?array
    {
        try {
            $tables = DB::select("SHOW TABLES LIKE 'system_access_log'");
            if (count($tables) === 0) {
                return null;
            }
            $rows = DB::table('system_access_log')
                ->select('uri', DB::raw('COUNT(*) as cnt'))
                ->where('created_at', '>=', now()->subDays($windowDays))
                ->groupBy('uri')
                ->get();

            $stats = [];
            foreach ($rows as $row) {
                $stats[(string) $row->uri] = (int) $row->cnt;
            }

            return $stats;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return array<int, string>
     */
    private function loadAllowlist(): array
    {
        return array_values(array_filter(
            (array) config('governance.routes_zombie_allowlist', [
                '#^/healthz$#',
                '#^/up$#',
                '#^/api/webhooks/#',
            ]),
            'is_string',
        ));
    }

    /**
     * @param array<int, string> $allowlist
     */
    private function matchesAllowlist(string $target, array $allowlist): bool
    {
        foreach ($allowlist as $pattern) {
            if ($pattern === '') {
                continue;
            }
            // Padrão regex se começa com '#'; senão match literal exato
            if (str_starts_with($pattern, '#')) {
                if (@preg_match($pattern, $target) === 1) {
                    return true;
                }
            } elseif ($target === $pattern) {
                return true;
            }
        }

        return false;
    }
}
