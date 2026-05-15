<?php

declare(strict_types=1);

namespace Modules\KB\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\KB\Entities\KbDecisionTree;
use Modules\KB\Entities\KbDecisionTreeStep;

/**
 * KbDecisionTreeController — troubleshooters (Q→Sim/Não→Q'/Fix).
 *
 * Contrato: SCHEMA-DB-V1.md §11
 *
 * Steps são criados em ordem (position 1-based) e linkados via *_next_step_id
 * em segundo passe (FK circular).
 *
 * **root_step_id** é o entry-point — primeiro step criado.
 */
class KbDecisionTreeController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('can:copiloto.mcp.memory.manage');
    }

    public function index(Request $request): JsonResponse
    {
        $q = KbDecisionTree::query()->where('status', '!=', 'archived');

        if ($equip = $request->string('equip')->toString()) {
            $q->where('equip', $equip);
        }

        $trees = $q->orderBy('title')->get(['id', 'slug', 'title', 'equip', 'when_to_use', 'hue', 'status']);

        return response()->json(['trees' => $trees]);
    }

    public function show(Request $request, string $slug): JsonResponse
    {
        $tree = KbDecisionTree::query()
            ->where('slug', $slug)
            ->with(['steps'])
            ->firstOrFail();

        return response()->json(['tree' => $tree]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title'       => 'required|string|max:180',
            'slug'        => 'nullable|string|max:120',
            'equip'       => 'nullable|string|max:80',
            'when_to_use' => 'nullable|string|max:500',
            'hue'         => 'nullable|integer|min:0|max:360',
            'status'      => 'sometimes|string|in:draft,published',
            'steps'       => 'required|array|min:1',
            'steps.*.question'         => 'required|string|max:500',
            'steps.*.yes_next_position' => 'nullable|integer|min:1',
            'steps.*.yes_fix'          => 'nullable|string',
            'steps.*.yes_fix_node_id'  => 'nullable|integer|exists:kb_nodes,id',
            'steps.*.no_next_position' => 'nullable|integer|min:1',
            'steps.*.no_fix'           => 'nullable|string',
            'steps.*.no_fix_node_id'   => 'nullable|integer|exists:kb_nodes,id',
        ]);

        $tree = DB::transaction(function () use ($data) {
            $tree = KbDecisionTree::create([
                'title'          => $data['title'],
                'slug'           => $data['slug'] ?? Str::slug($data['title']),
                'equip'          => $data['equip'] ?? null,
                'when_to_use'    => $data['when_to_use'] ?? null,
                'hue'            => $data['hue'] ?? 240,
                'status'         => $data['status'] ?? 'published',
                'author_user_id' => Auth::id(),
            ]);

            // 1ª passe: cria steps com fix imediato (sem _next_step_id ainda).
            $stepsByPosition = [];
            foreach ($data['steps'] as $i => $step) {
                $position = $i + 1;
                $created = KbDecisionTreeStep::create([
                    'business_id' => $tree->business_id,
                    'tree_id'     => $tree->id,
                    'position'    => $position,
                    'question'    => $step['question'],
                    'yes_fix'         => $step['yes_fix']         ?? null,
                    'yes_fix_node_id' => $step['yes_fix_node_id'] ?? null,
                    'no_fix'          => $step['no_fix']          ?? null,
                    'no_fix_node_id' => $step['no_fix_node_id']   ?? null,
                ]);
                $stepsByPosition[$position] = $created;
            }

            // 2ª passe: linka *_next_step_id por position.
            foreach ($data['steps'] as $i => $step) {
                $position = $i + 1;
                $current = $stepsByPosition[$position];

                $updates = [];
                if (! empty($step['yes_next_position'])) {
                    $target = $stepsByPosition[$step['yes_next_position']] ?? null;
                    if ($target) {
                        $updates['yes_next_step_id'] = $target->id;
                        $updates['yes_fix'] = null;
                        $updates['yes_fix_node_id'] = null;
                    }
                }
                if (! empty($step['no_next_position'])) {
                    $target = $stepsByPosition[$step['no_next_position']] ?? null;
                    if ($target) {
                        $updates['no_next_step_id'] = $target->id;
                        $updates['no_fix'] = null;
                        $updates['no_fix_node_id'] = null;
                    }
                }
                if (! empty($updates)) {
                    $current->fill($updates)->save();
                }
            }

            // 3ª passe: define root_step_id (primeiro step da array).
            $tree->root_step_id = $stepsByPosition[1]->id;
            $tree->save();

            return $tree;
        });

        return response()->json(['tree' => $tree->fresh('steps')], 201);
    }

    public function update(Request $request, string $slug): JsonResponse
    {
        // V1: replace strategy. Edit-in-place fica V2.
        $tree = KbDecisionTree::query()->where('slug', $slug)->firstOrFail();

        $data = $request->validate([
            'title'       => 'sometimes|string|max:180',
            'equip'       => 'nullable|string|max:80',
            'when_to_use' => 'nullable|string|max:500',
            'hue'         => 'nullable|integer|min:0|max:360',
            'status'      => 'sometimes|string|in:draft,published,archived',
            'steps'       => 'nullable|array',
        ]);

        DB::transaction(function () use ($tree, $data) {
            $tree->fill(array_filter($data, fn ($k) => $k !== 'steps', ARRAY_FILTER_USE_KEY))->save();

            if (isset($data['steps']) && is_array($data['steps'])) {
                // Re-create steps via store() — esvazia primeiro.
                $tree->root_step_id = null;
                $tree->save();
                KbDecisionTreeStep::where('tree_id', $tree->id)->delete();
                // TODO[CL]: refatorar pra repos compartilhado entre store() e update() — V2.
                $request->merge(['title' => $tree->title, 'slug' => $tree->slug, 'steps' => $data['steps']]);
                $this->store($request);
            }
        });

        return response()->json(['tree' => $tree->fresh('steps')]);
    }
}
