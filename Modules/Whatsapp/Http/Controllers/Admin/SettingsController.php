<?php

declare(strict_types=1);

namespace Modules\Whatsapp\Http\Controllers\Admin;

use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Whatsapp\Entities\WhatsappBusinessConfig;
use Modules\Whatsapp\Http\Requests\BusinessSettingsRequest;

/**
 * SettingsController — Templates HSM + toggle Bot Jana.
 *
 * Drivers (Z-API / Meta Cloud / Baileys) migraram para Modules\Whatsapp\Channels
 * (US-WA-067 + ADR 0135). Tela canônica agora é `/atendimento/canais/jana-templates`
 * (US-WA-070); rota legacy `/whatsapp/settings` é redirect 301.
 *
 * @see memory/requisitos/Whatsapp/SPEC.md US-WA-067, US-WA-070
 */
class SettingsController extends Controller
{
    public function show(): Response
    {
        $businessId = (int) session('user.business_id');
        $config = WhatsappBusinessConfig::where('business_id', $businessId)->first();

        $configForUi = $config === null ? null : [
            'bot_enabled' => (bool) $config->bot_enabled,
            'template_repair_ready_name' => $config->template_repair_ready_name,
            'template_repair_waiting_parts_name' => $config->template_repair_waiting_parts_name,
            'template_billing_due_name' => $config->template_billing_due_name,
            'template_billing_paid_name' => $config->template_billing_paid_name,
        ];

        return Inertia::render('Atendimento/JanaTemplates', [
            'config' => $configForUi,
        ]);
    }

    public function update(BusinessSettingsRequest $request): RedirectResponse
    {
        $businessId = (int) $request->session()->get('user.business_id');
        $validated = $request->validated();

        $config = WhatsappBusinessConfig::firstOrNew(['business_id' => $businessId]);

        if (! $config->exists) {
            $config->business_id = $businessId;
            $config->business_uuid = Str::uuid()->toString();
        }

        $fields = [
            'bot_enabled',
            'template_repair_ready_name',
            'template_repair_waiting_parts_name',
            'template_billing_due_name',
            'template_billing_paid_name',
        ];

        foreach ($fields as $field) {
            if (array_key_exists($field, $validated)) {
                $config->{$field} = $validated[$field];
            }
        }

        $config->save();

        return back()->with('status', 'Templates Jana salvos.');
    }
}
