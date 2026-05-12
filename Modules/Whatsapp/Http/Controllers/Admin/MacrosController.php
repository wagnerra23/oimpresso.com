<?php

declare(strict_types=1);

namespace Modules\Whatsapp\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Support\Facades\Schema;
use Modules\Whatsapp\Entities\Macro;
use Modules\Whatsapp\Entities\Tag;
use Modules\Whatsapp\Services\Macros\MacroExecutor;

/**
 * MacrosController — CRUD + apply (US-WA-048).
 *
 * Funde "quick reply puro" + "automation actions" estilo Chatwoot (gap
 * P1 #6+#12 em memory/requisitos/Whatsapp/COMPARATIVO-MERCADO-2026-05-12.md).
 *
 *  - `index/store/update/destroy` → tela settings `/atendimento/macros`
 *  - `list` → JSON endpoint pro dropdown do composer (curto, sem paginate)
 *  - `apply` → POST `/atendimento/inbox/{conv}/apply-macro/{macro}`
 *
 * Permission `whatsapp.send` em apply (mesma escala operacional de send);
 * `whatsapp.settings.manage` em CRUD (config).
 *
 * Multi-tenant Tier 0 IRREVOGÁVEL (ADR 0093) — Macro tem global scope
 * `business_id`; route binding manual via where business_id pra defesa
 * em profundidade.
 */
class MacrosController extends Controller
{
    public function index(Request $request): Response
    {
        $businessId = (int) session('user.business_id');

        // US-WA-049: pré-computa variants_count por macro pra coluna na tela.
        // Schema guard pra back-compat (migration pode não ter rodado ainda).
        $variantCounts = [];
        if (Schema::hasTable('macro_variants')) {
            $variantCounts = \Illuminate\Support\Facades\DB::table('macro_variants')
                ->where('business_id', $businessId)
                ->selectRaw('macro_id, COUNT(*) as c')
                ->groupBy('macro_id')
                ->pluck('c', 'macro_id')
                ->toArray();
        }

        $macros = Macro::query()
            ->where('business_id', $businessId)
            ->orderByDesc('used_count')
            ->orderBy('label')
            ->get()
            ->map(fn (Macro $m) => $this->macroToUiArray($m, (int) ($variantCounts[$m->id] ?? 0)));

        // Catálogo de tags pra form (multi-select actions)
        $tags = Tag::query()
            ->where('business_id', $businessId)
            ->orderBy('sort_order')
            ->orderBy('label')
            ->get(['id', 'slug', 'label', 'color']);

        return Inertia::render('Atendimento/Macros/Index', [
            'macros' => $macros,
            'availableTags' => $tags,
            'availableStatuses' => [
                ['value' => 'open',            'label' => 'Aberta'],
                ['value' => 'awaiting_human',  'label' => 'Aguardando humano'],
                ['value' => 'resolved',        'label' => 'Resolvida'],
                ['value' => 'archived',        'label' => 'Arquivada'],
            ],
        ]);
    }

    /**
     * GET `/atendimento/macros/list` — JSON pro dropdown do composer.
     *
     * Top 50 macros por `used_count` desc — suficiente pra busca live
     * sem precisar paginate. Frontend filtra client-side por shortcut/label.
     */
    public function list(Request $request): JsonResponse
    {
        $businessId = (int) session('user.business_id');

        $macros = Macro::query()
            ->where('business_id', $businessId)
            ->orderByDesc('used_count')
            ->orderBy('label')
            ->limit(50)
            ->get(['id', 'label', 'shortcut', 'body', 'used_count']);

        return response()->json([
            'macros' => $macros->map(fn (Macro $m) => [
                'id' => $m->id,
                'label' => $m->label,
                'shortcut' => $m->shortcut,
                'body' => $m->body,
                'used_count' => $m->used_count,
                // Preview curto pra dropdown (não vaza CPF/CNPJ — body
                // é template digitado por atendente, não PII de cliente).
                'body_preview' => mb_substr($m->body, 0, 120),
            ])->all(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $businessId = (int) session('user.business_id');
        $userId = (int) (session('user.id') ?? auth()->id() ?? 0);

        $validated = $this->validateMacroPayload($request, $businessId, macroId: null);

        Macro::query()->create([
            'business_id' => $businessId,
            'label' => $validated['label'],
            'shortcut' => Macro::normalizeShortcut($validated['shortcut'] ?? null),
            'body' => $validated['body'],
            'actions_json' => $validated['actions_json'] ?? [],
            'created_by_user_id' => $userId ?: null,
        ]);

        return back()->with('success', 'Macro criada.');
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $businessId = (int) session('user.business_id');

        $macro = Macro::query()
            ->where('business_id', $businessId)
            ->findOrFail($id);

        $validated = $this->validateMacroPayload($request, $businessId, macroId: $macro->id);

        $macro->forceFill([
            'label' => $validated['label'],
            'shortcut' => Macro::normalizeShortcut($validated['shortcut'] ?? null),
            'body' => $validated['body'],
            'actions_json' => $validated['actions_json'] ?? [],
        ])->save();

        return back()->with('success', 'Macro atualizada.');
    }

    public function destroy(Request $request, int $id): RedirectResponse
    {
        $businessId = (int) session('user.business_id');

        $macro = Macro::query()
            ->where('business_id', $businessId)
            ->findOrFail($id);

        $macro->delete();

        return back()->with('success', 'Macro removida.');
    }

    /**
     * POST `/atendimento/inbox/conversations/{id}/apply-macro/{macroId}`.
     *
     * Dispara MacroExecutor (envia msg + aplica actions + incrementa used_count).
     * UI fica responsável por reload da conv via Inertia partial.
     */
    public function apply(Request $request, int $id, int $macroId, MacroExecutor $executor): RedirectResponse
    {
        $businessId = (int) session('user.business_id');
        $userId = (int) (session('user.id') ?? auth()->id() ?? 0);

        $result = $executor->execute($businessId, $macroId, $id, $userId);

        if ($result['send_failed']) {
            // Mantém sucesso parcial visível — actions podem ter aplicado mesmo
            // se daemon falhou. UI mostra status=failed no bubble normalmente.
            return back()->with('warning', 'Macro aplicada com falha no envio. Veja status da mensagem.');
        }

        $applied = count($result['actions_applied']);
        $msg = $applied > 0
            ? "Macro aplicada (msg + {$applied} ação(ões))."
            : 'Macro aplicada.';

        return back()->with('success', $msg);
    }

    /**
     * Valida payload comum a store/update. Inclui regra de UNIQUE shortcut
     * por business (com ignore quando edita a própria macro).
     *
     * @return array{label: string, shortcut: ?string, body: string, actions_json: array}
     */
    protected function validateMacroPayload(Request $request, int $businessId, ?int $macroId): array
    {
        $rules = [
            'label' => ['required', 'string', 'max:80'],
            'shortcut' => ['nullable', 'string', 'max:30'],
            'body' => ['required', 'string', 'max:4096'],
            'actions_json' => ['nullable', 'array'],
            'actions_json.*.type' => ['required_with:actions_json.*', Rule::in(Macro::ACTION_TYPES)],
            'actions_json.*.tag_id' => ['nullable', 'integer'],
            'actions_json.*.status' => ['nullable', 'string', Rule::in(['open', 'awaiting_human', 'resolved', 'archived'])],
            'actions_json.*.user_id' => ['nullable'],
        ];

        $validated = $request->validate($rules);

        // UNIQUE shortcut per-business (normalizado). DB constraint catches
        // dups, mas valida amigável aqui pra dar mensagem clara no form.
        $normalized = Macro::normalizeShortcut($validated['shortcut'] ?? null);
        if ($normalized !== null) {
            $dup = Macro::query()
                ->where('business_id', $businessId)
                ->where('shortcut', $normalized)
                ->when($macroId !== null, fn ($q) => $q->where('id', '!=', $macroId))
                ->exists();
            if ($dup) {
                abort(422, "Atalho '/{$normalized}' já está em uso por outra macro deste business.");
            }
        }

        return $validated;
    }

    protected function macroToUiArray(Macro $m, int $variantsCount = 0): array
    {
        return [
            'id' => $m->id,
            'label' => $m->label,
            'shortcut' => $m->shortcut,
            'body' => $m->body,
            'actions_json' => $m->actions_json ?? [],
            'used_count' => (int) $m->used_count,
            'variants_count' => $variantsCount,
            'created_at' => optional($m->created_at)->toIso8601String(),
            'updated_at' => optional($m->updated_at)->toIso8601String(),
        ];
    }
}
