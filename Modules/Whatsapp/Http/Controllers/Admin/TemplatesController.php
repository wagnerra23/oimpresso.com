<?php

declare(strict_types=1);

namespace Modules\Whatsapp\Http\Controllers\Admin;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Jana\Scopes\ScopeByBusiness;
use Modules\Whatsapp\Entities\WhatsappBusinessConfig;
use Modules\Whatsapp\Entities\WhatsappTemplate;
use Modules\Whatsapp\Services\Drivers\DriverFactory;
use Modules\Whatsapp\Services\Drivers\MetaCloudDriver;

/**
 * Gerenciador de templates HSM Meta + locais Z-API/Baileys.
 *
 * Comportamento por driver:
 * - Meta Cloud: status reflete aprovação Meta (PENDING/APPROVED/REJECTED/PAUSED/DISABLED)
 * - Z-API/Baileys: templates locais (status=LOCAL); driver expande placeholders
 *
 * **Validação contraparte (alerta UI):** template Z-API LOCAL deve ter
 * HSM Meta correspondente (mesmo nome) pra fallback funcionar quando
 * Z-API for banido.
 *
 * @see memory/requisitos/Whatsapp/SPEC.md US-WA-013
 */
class TemplatesController extends Controller
{
    public function index(Request $request): Response
    {
        $providerFilter = (string) $request->query('provider', 'all');
        $statusFilter = (string) $request->query('status', 'all');

        // D-14 perf 2026-05-15 (skill `inertia-defer-default` Tier 0):
        // `templates` (query + map + N+1 hasMetaCounterpart checks) vira
        // Inertia::defer — pula execução quando partial reload não pede.
        return Inertia::render('Whatsapp/Templates/Index', [
            // ─── Eager (custo zero — filtros UI) ───
            'filters' => [
                'provider' => $providerFilter,
                'status' => $statusFilter,
            ],

            // ─── Defer (query + N+1 EXISTS subquery per row) ───
            'templates' => Inertia::defer(fn () => $this->buildTemplatesPayload($providerFilter, $statusFilter)),
        ]);
    }

    /**
     * D-14 perf — query WhatsappTemplate + map com check `hasMetaCounterpart`
     * por row (EXISTS subquery). Em business com 20+ templates Z-API → 20 EXISTS
     * extras. Defer skipa quando partial reload não solicita.
     */
    protected function buildTemplatesPayload(string $providerFilter, string $statusFilter): array
    {
        $query = WhatsappTemplate::query()->orderBy('name');

        if ($providerFilter !== 'all') {
            $query->where('provider', $providerFilter);
        }
        if ($statusFilter !== 'all') {
            $query->where('status', $statusFilter);
        }

        return $query->get()->map(function (WhatsappTemplate $t) {
            // Detecta se template Z-API/Baileys local tem contraparte Meta aprovada
            $hasMetaCounterpart = false;
            if ($t->provider !== 'meta_cloud') {
                $hasMetaCounterpart = WhatsappTemplate::where('provider', 'meta_cloud')
                    ->where('name', $t->name)
                    ->where('language', $t->language)
                    ->where('status', 'APPROVED')
                    ->exists();
            }

            return [
                'id' => $t->id,
                'provider' => $t->provider,
                'name' => $t->name,
                'language' => $t->language,
                'category' => $t->category,
                'status' => $t->status,
                'rejection_reason' => $t->rejection_reason,
                'last_synced_at' => optional($t->last_synced_at)->toIso8601String(),
                'body_preview' => mb_substr($t->expandBody([]), 0, 200),
                'has_meta_counterpart' => $hasMetaCounterpart,
                'is_ready' => $t->isReadyToSend(),
            ];
        })->all();
    }

    /**
     * Sync HSM Meta — chama MetaCloudDriver::fetchTemplates() e upserta em DB.
     *
     * Idempotente: UNIQUE (business_id, provider, name, language) faz upsert.
     * Apenas templates Meta sincronizam aqui (locais Z-API/Baileys são criados
     * via store() ou seeder).
     */
    public function syncMeta(Request $request): RedirectResponse
    {
        $businessId = (int) $request->session()->get('user.business_id');

        $config = WhatsappBusinessConfig::query()
            ->withoutGlobalScope(ScopeByBusiness::class)
            ->where('business_id', $businessId)
            ->first();

        if ($config === null) {
            return back()->with('error', 'Configuração Whatsapp não cadastrada. Cadastre Meta Cloud em /whatsapp/settings primeiro.');
        }

        if (! $config->hasMetaCloudConfigured()) {
            return back()->with('error', 'Meta Cloud não cadastrado pra este business — sync impossível.');
        }

        /** @var MetaCloudDriver $driver */
        $driver = app(MetaCloudDriver::class);

        try {
            $items = $driver->fetchTemplates($config);
        } catch (\Throwable $e) {
            \Log::error('[whatsapp.sync_meta_templates] falha', [
                'business_id' => $businessId,
                'error' => $e->getMessage(),
            ]);
            return back()->with('error', "Sync falhou: {$e->getMessage()}");
        }

        $upsertedCount = 0;
        foreach ($items as $item) {
            if (empty($item['name']) || empty($item['language'])) {
                continue;
            }

            WhatsappTemplate::updateOrCreate(
                [
                    'business_id' => $businessId,
                    'provider' => 'meta_cloud',
                    'name' => $item['name'],
                    'language' => $item['language'],
                ],
                [
                    'meta_template_id' => $item['meta_template_id'] ?? null,
                    'category' => $item['category'] ?? 'UTILITY',
                    'status' => $item['status'] ?? 'PENDING',
                    'components' => $item['components'] ?? [],
                    'rejection_reason' => $item['rejection_reason'] ?? null,
                    'last_synced_at' => now(),
                ]
            );
            $upsertedCount++;
        }

        return back()->with('status', "Sync Meta HSM completo — {$upsertedCount} template(s) atualizado(s).");
    }

    /**
     * Cria template LOCAL Z-API/Baileys (sem aprovação Meta).
     */
    public function store(Request $request): RedirectResponse
    {
        $businessId = (int) $request->session()->get('user.business_id');

        $validated = $request->validate([
            'provider' => ['required', Rule::in(['zapi', 'baileys'])],
            'name' => ['required', 'string', 'max:64', 'regex:/^[a-z0-9_]+$/'],
            'language' => ['required', 'string', 'max:10'],
            'category' => ['required', Rule::in(['UTILITY', 'MARKETING', 'AUTHENTICATION'])],
            'body' => ['required', 'string', 'max:4096'],
        ], [
            'name.regex' => 'Nome deve ser snake_case (ex: repair_status_ready) — sem espaços ou maiúsculas.',
        ]);

        WhatsappTemplate::updateOrCreate(
            [
                'business_id' => $businessId,
                'provider' => $validated['provider'],
                'name' => $validated['name'],
                'language' => $validated['language'],
            ],
            [
                'category' => $validated['category'],
                'status' => 'LOCAL',
                'components' => [
                    ['type' => 'BODY', 'text' => $validated['body']],
                ],
                'last_synced_at' => now(),
            ]
        );

        return back()->with('status', 'Template LOCAL criado/atualizado.');
    }
}
