<?php

declare(strict_types=1);

namespace Modules\Whatsapp\Http\Controllers\Admin;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Modules\Whatsapp\Entities\WhatsappQueue;

/**
 * QueuesController — CRUD do painel "Filas" da Caixa Unificada V4
 * (US-WA-301 · ADR 0267).
 *
 * Sem página própria: o painel é um Sheet in-place na Caixa Unificada
 * (`QueuesSheet.tsx`); aqui só mutações. Leitura vai nos props do
 * CaixaUnificadaController (payload `queuesAdmin` deferred).
 *
 * Permission: `whatsapp.settings.manage` (mesma do painel Canais).
 * Tier 0 ADR 0093: where business_id explícito em TODA query.
 */
class QueuesController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $businessId = (int) session('user.business_id');
        $data = $this->validatePayload($request, $businessId, null);

        WhatsappQueue::query()->create(array_merge($data, [
            'business_id' => $businessId,
            'trigger_tags' => $data['trigger_tags'] ?? [],
            'members' => [],
        ]));

        return back()->with('success', 'Fila criada.');
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $businessId = (int) session('user.business_id');
        $queue = WhatsappQueue::query()
            ->where('business_id', $businessId)
            ->findOrFail($id);

        $data = $this->validatePayload($request, $businessId, $queue);
        $queue->fill(array_merge($data, [
            'trigger_tags' => $data['trigger_tags'] ?? [],
        ]))->save();

        return back()->with('success', 'Fila atualizada.');
    }

    public function destroy(Request $request, int $id): RedirectResponse
    {
        $businessId = (int) session('user.business_id');
        $queue = WhatsappQueue::query()
            ->where('business_id', $businessId)
            ->findOrFail($id);

        // Fila default é o fallback da heurística tag→fila — não deleta (ADR 0267)
        $defaultSlug = (string) config('whatsapp.default_queue', 'comercial');
        if ($queue->slug === $defaultSlug) {
            return back()->withErrors([
                'queue' => "Fila \"{$queue->label}\" é a default ({$defaultSlug}) — fallback da heurística. Não pode ser removida.",
            ]);
        }

        $queue->delete();

        return back()->with('success', 'Fila removida.');
    }

    /**
     * @return array{slug: string, label: string, hue: int, sla_minutes: ?int, dist: string, trigger_tags?: array<int, string>, sort_order: int}
     */
    protected function validatePayload(Request $request, int $businessId, ?WhatsappQueue $current): array
    {
        $data = $request->validate([
            'label' => ['required', 'string', 'max:80'],
            'slug' => ['nullable', 'string', 'max:40', 'regex:/^[a-z0-9-]+$/'],
            'hue' => ['required', 'integer', 'min:0', 'max:360'],
            'sla_minutes' => ['nullable', 'integer', 'min:1', 'max:10080'],
            'dist' => ['required', Rule::in(WhatsappQueue::DIST_MODES)],
            'trigger_tags' => ['nullable', 'array'],
            'trigger_tags.*' => ['string', 'max:40'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        // Slug: imutável no update (referência estável pra default_queue e
        // queue_override futura); auto-gerado do label no create quando ausente.
        if ($current !== null) {
            $data['slug'] = $current->slug;
        } else {
            $slug = $data['slug'] ?? Str::slug(Str::limit($data['label'], 36, ''));
            $exists = WhatsappQueue::query()
                ->where('business_id', $businessId)
                ->where('slug', $slug)
                ->exists();
            if ($slug === '' || $exists) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'slug' => $exists ? "Já existe fila com slug \"{$slug}\" neste business." : 'Slug inválido.',
                ]);
            }
            $data['slug'] = $slug;
        }

        $data['sort_order'] = $data['sort_order'] ?? 0;

        return $data;
    }
}
