<?php

namespace Modules\MemCofre\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;
use Modules\MemCofre\Entities\DocEvidence;

class InboxController extends Controller
{
    public function index(Request $request): Response
    {
        $businessId = (int) (session('business.id') ?: $request->session()->get('user.business_id'));

        $status = $request->string('status')->toString() ?: 'pending';
        $module = $request->string('module')->toString() ?: null;
        $search = trim((string) $request->query('q', ''));

        // Busca via Scout (ADR arq/0006) quando há termo, senão Eloquent direto.
        if ($search !== '') {
            $evidences = DocEvidence::search($search)
                ->where('business_id', $businessId)
                ->when($status, fn ($q) => $q->where('status', $status))
                ->when($module, fn ($q) => $q->where('module_target', $module))
                ->paginate(25)
                ->withQueryString();
            // Scout::paginate não carrega relations — hidratar.
            $evidences->getCollection()->load(['source:id,type,title,storage_path,source_url']);
        } else {
            $evidences = DocEvidence::where('business_id', $businessId)
                ->where('status', $status)
                ->when($module, fn ($q) => $q->where('module_target', $module))
                ->with(['source:id,type,title,storage_path,source_url'])
                ->orderByDesc('created_at')
                ->paginate(25)
                ->withQueryString();
        }

        $evidences->getCollection()->transform(fn (DocEvidence $e) => [
            'id'             => $e->id,
            'kind'           => $e->kind,
            'status'         => $e->status,
            'module_target'  => $e->module_target,
            'content'        => $e->content,
            'ai_confidence'  => $e->ai_confidence,
            'extracted_by_ai'=> (bool) $e->extracted_by_ai,
            'suggested_story_id' => $e->suggested_story_id,
            'suggested_rule_id'  => $e->suggested_rule_id,
            'notes'          => $e->notes,
            'created_at_human' => optional($e->created_at)->diffForHumans(),
            'source'         => [
                'id'    => optional($e->source)->id,
                'type'  => optional($e->source)->type,
                'title' => optional($e->source)->title,
                'storage_url' => optional($e->source)->storage_path
                    ? \Storage::disk(config('memcofre.upload.disk', 'public'))->url($e->source->storage_path)
                    : null,
                'source_url'  => optional($e->source)->source_url,
            ],
        ]);

        return Inertia::render('MemCofre/Inbox', [
            'evidences' => $evidences,
            'filtros'   => [
                'status' => $status,
                'module' => $module,
                'q'      => $search,
            ],
            'counts'    => DocEvidence::where('business_id', $businessId)
                ->selectRaw('status, count(*) as total')
                ->groupBy('status')
                ->pluck('total', 'status')
                ->toArray(),
        ]);
    }

    public function triage(Request $request, $evidenceId): RedirectResponse
    {
        $businessId = (int) (session('business.id') ?: $request->session()->get('user.business_id'));

        $validated = $request->validate([
            'status'        => 'required|in:pending,triaged,applied,rejected,duplicate',
            'kind'          => 'nullable|in:bug,rule,flow,quote,screenshot,decision',
            'module_target' => 'nullable|string|max:64',
            'suggested_story_id' => 'nullable|string|max:32',
            'suggested_rule_id'  => 'nullable|string|max:32',
            'notes'         => 'nullable|string|max:2000',
        ]);

        $evidence = DocEvidence::where('business_id', $businessId)->findOrFail($evidenceId);

        $update = array_filter([
            'status'        => $validated['status'] ?? null,
            'kind'          => $validated['kind'] ?? null,
            'module_target' => $validated['module_target'] ?? null,
            'suggested_story_id' => $validated['suggested_story_id'] ?? null,
            'suggested_rule_id'  => $validated['suggested_rule_id'] ?? null,
            'notes'         => $validated['notes'] ?? null,
        ], fn ($v) => $v !== null);

        if (in_array($update['status'] ?? null, ['triaged', 'applied', 'rejected'], true)) {
            $update['triaged_by'] = auth()->user()->id;
            $update['triaged_at'] = now();
        }

        $evidence->update($update);

        return back()->with('success', 'Evidência atualizada.');
    }

    public function apply(Request $request, $evidenceId): RedirectResponse
    {
        // Placeholder pra Fase 3: aplica a evidência gerando/atualizando
        // entrada em docs_requirements + patch no arquivo .md correspondente.
        // Por ora, só marca como applied.
        $businessId = (int) (session('business.id') ?: $request->session()->get('user.business_id'));
        $evidence = DocEvidence::where('business_id', $businessId)->findOrFail($evidenceId);

        $evidence->update([
            'status'     => 'applied',
            'triaged_by' => auth()->user()->id,
            'triaged_at' => now(),
        ]);

        return back()->with('success', 'Marcada como aplicada. (Aplicação automática do .md vem na Fase 3.)');
    }

    public function destroy($evidenceId): RedirectResponse
    {
        $businessId = (int) (session('business.id') ?: request()->session()->get('user.business_id'));
        DocEvidence::where('business_id', $businessId)->where('id', $evidenceId)->delete();
        return back()->with('success', 'Evidência removida.');
    }
}
