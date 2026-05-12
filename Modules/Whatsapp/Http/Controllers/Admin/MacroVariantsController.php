<?php

declare(strict_types=1);

namespace Modules\Whatsapp\Http\Controllers\Admin;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Whatsapp\Entities\Macro;
use Modules\Whatsapp\Entities\MacroVariant;

/**
 * MacroVariantsController — CRUD de variants A/B de uma Macro (US-WA-049,
 * gap P2 #18).
 *
 * Pattern Take Blip A/B testing: atendente cria N variantes (label + body
 * + weight). `MacroVariantPicker` sorteia uma no apply, métricas mostram
 * `response_rate` por variante.
 *
 * Rotas (nested em macro_id):
 *   GET    /atendimento/macros/{macro}/variants            → index
 *   POST   /atendimento/macros/{macro}/variants            → store
 *   PUT    /atendimento/macros/{macro}/variants/{variant}  → update
 *   DELETE /atendimento/macros/{macro}/variants/{variant}  → destroy
 *   POST   /atendimento/macros/{macro}/variants/{variant}/mark-winner → mark_winner
 *
 * Multi-tenant Tier 0 IRREVOGÁVEL (ADR 0093) — todas queries scopadas via
 * trait HasBusinessScope. Defesa em profundidade: confere FK macro_id em
 * cada operação.
 *
 * Permission `whatsapp.settings.manage` (mesmo grupo de Macros CRUD).
 *
 * @see memory/requisitos/Whatsapp/SPEC.md US-WA-049
 */
class MacroVariantsController extends Controller
{
    public function index(Request $request, int $macroId): Response
    {
        $businessId = (int) session('user.business_id');

        $macro = Macro::query()
            ->where('business_id', $businessId)
            ->findOrFail($macroId);

        $variants = MacroVariant::query()
            ->where('business_id', $businessId)
            ->where('macro_id', $macro->id)
            ->orderBy('active', 'desc')
            ->orderByDesc('weight')
            ->orderBy('label')
            ->get()
            ->map(fn (MacroVariant $v) => $this->variantToUiArray($v));

        return Inertia::render('Atendimento/Macros/Variants', [
            'macro' => [
                'id' => $macro->id,
                'label' => $macro->label,
                'shortcut' => $macro->shortcut,
                'body' => $macro->body,
            ],
            'variants' => $variants,
        ]);
    }

    public function store(Request $request, int $macroId): RedirectResponse
    {
        $businessId = (int) session('user.business_id');

        $macro = Macro::query()
            ->where('business_id', $businessId)
            ->findOrFail($macroId);

        $validated = $this->validateVariantPayload($request);

        MacroVariant::query()->create([
            'business_id' => $businessId,
            'macro_id' => $macro->id,
            'label' => $validated['label'],
            'body' => $validated['body'],
            'weight' => $validated['weight'] ?? 50,
            'active' => (bool) ($validated['active'] ?? true),
        ]);

        return back()->with('success', 'Variante criada.');
    }

    public function update(Request $request, int $macroId, int $variantId): RedirectResponse
    {
        $businessId = (int) session('user.business_id');

        $variant = $this->findVariantOrFail($businessId, $macroId, $variantId);
        $validated = $this->validateVariantPayload($request);

        $variant->forceFill([
            'label' => $validated['label'],
            'body' => $validated['body'],
            'weight' => $validated['weight'] ?? $variant->weight,
            'active' => array_key_exists('active', $validated)
                ? (bool) $validated['active']
                : $variant->active,
        ])->save();

        return back()->with('success', 'Variante atualizada.');
    }

    public function destroy(Request $request, int $macroId, int $variantId): RedirectResponse
    {
        $businessId = (int) session('user.business_id');

        $variant = $this->findVariantOrFail($businessId, $macroId, $variantId);
        $variant->delete();

        return back()->with('success', 'Variante removida.');
    }

    /**
     * Marca variante como "vencedora": desativa as outras + bump weight=100.
     * Atalho operacional pra encerrar experimento A/B sem deletar histórico.
     */
    public function markWinner(Request $request, int $macroId, int $variantId): RedirectResponse
    {
        $businessId = (int) session('user.business_id');

        $winner = $this->findVariantOrFail($businessId, $macroId, $variantId);

        // Desativa as outras variantes da MESMA macro
        MacroVariant::query()
            ->where('business_id', $businessId)
            ->where('macro_id', $winner->macro_id)
            ->where('id', '!=', $winner->id)
            ->update(['active' => false]);

        $winner->forceFill([
            'active' => true,
            'weight' => 100,
        ])->save();

        return back()->with('success', "Variante \"{$winner->label}\" marcada como vencedora.");
    }

    /**
     * Encontra variante por (business, macro, id) — defesa em profundidade.
     */
    protected function findVariantOrFail(int $businessId, int $macroId, int $variantId): MacroVariant
    {
        return MacroVariant::query()
            ->where('business_id', $businessId)
            ->where('macro_id', $macroId)
            ->findOrFail($variantId);
    }

    /**
     * Validação compartilhada store/update. Weight clamped 0-100.
     *
     * @return array{label: string, body: string, weight?: int, active?: bool}
     */
    protected function validateVariantPayload(Request $request): array
    {
        return $request->validate([
            'label' => ['required', 'string', 'max:80'],
            'body' => ['required', 'string', 'max:4096'],
            'weight' => [
                'nullable', 'integer',
                'between:' . MacroVariant::WEIGHT_MIN . ',' . MacroVariant::WEIGHT_MAX,
            ],
            'active' => ['nullable', 'boolean'],
        ]);
    }

    /**
     * Serializa variant pra Inertia (com derived `response_rate`).
     *
     * @return array<string, mixed>
     */
    protected function variantToUiArray(MacroVariant $v): array
    {
        return [
            'id' => $v->id,
            'macro_id' => $v->macro_id,
            'label' => $v->label,
            'body' => $v->body,
            'weight' => (int) $v->weight,
            'active' => (bool) $v->active,
            'sent_count' => (int) $v->sent_count,
            'response_count' => (int) $v->response_count,
            'response_rate' => $v->responseRate(),
            'created_at' => optional($v->created_at)->toIso8601String(),
            'updated_at' => optional($v->updated_at)->toIso8601String(),
        ];
    }
}
