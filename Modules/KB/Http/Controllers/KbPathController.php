<?php

declare(strict_types=1);

namespace Modules\KB\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\KB\Entities\KbPath;
use Modules\KB\Entities\KbPathStep;

/**
 * KbPathController — trilhas de aprendizado (kb_paths + kb_path_steps).
 *
 * Contrato: SCHEMA-DB-V1.md §11
 *
 * - GET  /kb/paths              lista
 * - GET  /kb/paths/{slug}       detalhe + nodes ordenados
 * - POST /kb/paths              cria (kb.publish.path)
 * - PUT  /kb/paths/{slug}       edita (atomic transação pra steps)
 */
class KbPathController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('can:copiloto.mcp.memory.manage');
    }

    public function index(Request $request): JsonResponse
    {
        $q = KbPath::query()->where('status', '!=', 'archived');

        if ($audience = $request->string('audience')->toString()) {
            $q->where('audience', 'like', "%{$audience}%");
        }

        $paths = $q->orderBy('title')->get();

        return response()->json(['paths' => $paths]);
    }

    public function show(Request $request, string $slug): JsonResponse
    {
        $path = KbPath::query()
            ->where('slug', $slug)
            ->with(['steps.node:id,slug,title,type,excerpt,read_time_min'])
            ->firstOrFail();

        return response()->json(['path' => $path]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title'       => 'required|string|max:180',
            'slug'        => 'nullable|string|max:120',
            'audience'    => 'nullable|string|max:180',
            'description' => 'nullable|string|max:500',
            'hue'         => 'nullable|integer|min:0|max:360',
            'status'      => 'sometimes|string|in:draft,published',
            'steps'       => 'nullable|array',
            'steps.*.node_id'   => 'required_with:steps|integer|exists:kb_nodes,id',
            'steps.*.step_type' => 'sometimes|string|in:leitura,pratica,decisao',
            'steps.*.note'      => 'nullable|string|max:500',
        ]);

        $path = DB::transaction(function () use ($data) {
            $path = KbPath::create([
                'title'          => $data['title'],
                'slug'           => $data['slug'] ?? Str::slug($data['title']),
                'audience'       => $data['audience'] ?? null,
                'description'    => $data['description'] ?? null,
                'hue'            => $data['hue'] ?? 240,
                'status'         => $data['status'] ?? 'published',
                'author_user_id' => Auth::id(),
            ]);

            foreach ((array) ($data['steps'] ?? []) as $i => $step) {
                KbPathStep::create([
                    'business_id' => $path->business_id,
                    'path_id'     => $path->id,
                    'node_id'     => $step['node_id'],
                    'position'    => $i + 1,
                    'step_type'   => $step['step_type'] ?? 'leitura',
                    'note'        => $step['note'] ?? null,
                ]);
            }

            return $path;
        });

        return response()->json(['path' => $path->fresh('steps.node')], 201);
    }

    public function update(Request $request, string $slug): JsonResponse
    {
        $data = $request->validate([
            'title'       => 'sometimes|string|max:180',
            'audience'    => 'nullable|string|max:180',
            'description' => 'nullable|string|max:500',
            'hue'         => 'nullable|integer|min:0|max:360',
            'status'      => 'sometimes|string|in:draft,published,archived',
            'steps'       => 'nullable|array',
            'steps.*.node_id'   => 'required_with:steps|integer|exists:kb_nodes,id',
            'steps.*.step_type' => 'sometimes|string|in:leitura,pratica,decisao',
            'steps.*.note'      => 'nullable|string|max:500',
        ]);

        $path = KbPath::query()->where('slug', $slug)->firstOrFail();

        DB::transaction(function () use ($path, $data) {
            $path->fill(array_filter($data, fn ($k) => $k !== 'steps', ARRAY_FILTER_USE_KEY))->save();

            if (isset($data['steps'])) {
                // Replace strategy — V1 simples. Edit individual = V2.
                KbPathStep::where('path_id', $path->id)->delete();

                foreach ($data['steps'] as $i => $step) {
                    KbPathStep::create([
                        'business_id' => $path->business_id,
                        'path_id'     => $path->id,
                        'node_id'     => $step['node_id'],
                        'position'    => $i + 1,
                        'step_type'   => $step['step_type'] ?? 'leitura',
                        'note'        => $step['note'] ?? null,
                    ]);
                }
            }
        });

        return response()->json(['path' => $path->fresh('steps.node')]);
    }
}
