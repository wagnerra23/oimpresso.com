<?php

declare(strict_types=1);

namespace Modules\Whatsapp\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Whatsapp\Entities\WhatsappTemplate;

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

        $query = WhatsappTemplate::query()->orderBy('name');

        if ($providerFilter !== 'all') {
            $query->where('provider', $providerFilter);
        }
        if ($statusFilter !== 'all') {
            $query->where('status', $statusFilter);
        }

        $templates = $query->get()->map(function (WhatsappTemplate $t) {
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
        });

        return Inertia::render('Whatsapp/Templates/Index', [
            'templates' => $templates,
            'filters' => [
                'provider' => $providerFilter,
                'status' => $statusFilter,
            ],
        ]);
    }

    /**
     * Sync HSM Meta (Sprint 2 — pra Lote 2f). Stub aqui.
     */
    public function syncMeta(Request $request)
    {
        return back()->with('status', 'Sync Meta HSM ainda não implementado (Lote 2f Sprint 2).');
    }
}
