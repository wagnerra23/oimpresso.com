<?php

declare(strict_types=1);

namespace Modules\Admin\Http\Controllers;

use App\Models\FeatureFlagAudit;
use App\Services\GrowthBookAdminService;
use App\Util\OtelHelper;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

/**
 * FeatureFlagsController — painel admin Wagner-only de feature flags GrowthBook.
 *
 * Sprint 2 (US-INFRA-008 — 2026-05-13). Lê via GrowthBookAdminService (REST API),
 * escreve via tools/CLI/painel (este controller). Audit em feature_flag_audits.
 *
 * Middleware: tailscale-only -> auth -> is-wagner (herdado de Routes/web.php).
 *
 * @see memory/decisions/0122-admin-center-ct100.md
 */
class FeatureFlagsController extends Controller
{
    public function __construct(
        protected GrowthBookAdminService $service,
    ) {}

    /** Lista todas feature flags + estado por env. */
    public function index(): Response
    {
        // D9.a (Wave 18): span agrega 2 sources (REST GrowthBook + DB audits) numa
        // métrica única — pra spotting de regressão de latência por env.
        return OtelHelper::spanBiz('admin.feature_flags.index', function () {
            $features = $this->safeCall(fn () => $this->service->listFeatures());
            $recentAudits = FeatureFlagAudit::query()
                ->orderByDesc('id')
                ->limit(20)
                ->get(['id', 'created_at', 'actor_label', 'flag_key', 'action', 'environment', 'diff_summary']);

            return Inertia::render('Admin/FeatureFlags/Index', [
                'configured'   => $this->service->isConfigured(),
                'features'     => $features['data'] ?? [],
                'fetch_error'  => $features['error'] ?? null,
                'recent_audits' => $recentAudits,
            ]);
        }, ['component' => 'admin.feature_flags']);
    }

    /** Detalhe de 1 feature + audit history dela. */
    public function show(string $key): Response
    {
        // Wave 25 D6 SATURATION (+4): Inertia::defer canônico
        // (RUNBOOK-inertia-defer-pattern.md). `feature` chama HTTP externo
        // GrowthBook (~100-300ms); `audits` é DB query com limit(50).
        // Page Admin/FeatureFlags/Show deve wrappar ambos em <Deferred fallback={skeleton}>.
        return Inertia::render('Admin/FeatureFlags/Show', [
            'configured'   => $this->service->isConfigured(),
            'key'          => $key,
            'feature'      => Inertia::defer(fn () => $this->safeCall(fn () => $this->service->getFeature($key))['data'] ?? null),
            'fetch_error'  => Inertia::defer(fn () => $this->safeCall(fn () => $this->service->getFeature($key))['error'] ?? null),
            'audits'       => Inertia::defer(fn () => FeatureFlagAudit::query()
                ->where('flag_key', $key)
                ->orderByDesc('id')
                ->limit(50)
                ->get()),
        ]);
    }

    /** Set/remove rule biz-{N}. Espera body { biz_id, value?, remove?, env? }. */
    public function setBizRule(Request $request, string $key): RedirectResponse
    {
        $data = $request->validate([
            'biz_id'      => 'required|integer|min:1',
            'value'       => 'nullable|boolean',
            'remove'      => 'nullable|boolean',
            'env'         => 'nullable|string|max:50',
            'clear_cache' => 'nullable|boolean',
        ]);

        $env = $data['env'] ?? 'production';
        $clearCache = $data['clear_cache'] ?? true;

        try {
            if (! empty($data['remove'])) {
                $this->service->removeBizRule($key, $data['biz_id'], $env);
                $msg = "Rule biz-{$data['biz_id']} removida.";
            } else {
                if (! array_key_exists('value', $data) || $data['value'] === null) {
                    return back()->withErrors(['value' => 'Campo value é obrigatório quando remove=false.']);
                }
                $this->service->setBizRule($key, $data['biz_id'], (bool) $data['value'], $env);
                $valueStr = $data['value'] ? 'true' : 'false';
                $msg = "Rule biz-{$data['biz_id']} setada (value={$valueStr}).";
            }
        } catch (Throwable $e) {
            return back()->withErrors(['flag' => 'Falha: ' . $e->getMessage()]);
        }

        if ($clearCache) {
            $this->service->clearLocalCache();
            $msg .= ' Cache local limpo.';
        }

        return redirect()->route('admin.feature-flags.show', ['key' => $key])
            ->with('success', $msg);
    }

    /** Mata-switch do environment (liga/desliga feature inteira). */
    public function setEnvEnabled(Request $request, string $key): RedirectResponse
    {
        $data = $request->validate([
            'enabled'     => 'required|boolean',
            'env'         => 'nullable|string|max:50',
            'clear_cache' => 'nullable|boolean',
        ]);

        $env = $data['env'] ?? 'production';
        $clearCache = $data['clear_cache'] ?? true;

        try {
            $this->service->setEnvEnabled($key, (bool) $data['enabled'], $env);
        } catch (Throwable $e) {
            return back()->withErrors(['flag' => 'Falha: ' . $e->getMessage()]);
        }

        if ($clearCache) {
            $this->service->clearLocalCache();
        }

        $action = $data['enabled'] ? 'LIGADA' : 'DESLIGADA';
        return redirect()->route('admin.feature-flags.show', ['key' => $key])
            ->with('success', "Feature {$action} no environment {$env}.");
    }

    /** Limpa cache local Laravel. */
    public function clearCache(): RedirectResponse
    {
        $this->service->clearLocalCache();
        return back()->with('success', 'Cache local Laravel limpo.');
    }

    /**
     * Wrapper que captura exceção e devolve { data, error } pra não derrubar a page.
     */
    private function safeCall(callable $fn): array
    {
        try {
            return ['data' => $fn(), 'error' => null];
        } catch (Throwable $e) {
            Log::warning('FeatureFlagsController: GrowthBook fetch falhou', ['error' => $e->getMessage()]);
            return ['data' => null, 'error' => $e->getMessage()];
        }
    }
}
