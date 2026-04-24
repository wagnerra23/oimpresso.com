<?php

namespace Modules\MemCofre\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Modules\MemCofre\Entities\DocEvidence;
use Modules\MemCofre\Entities\DocSource;

class IngestController extends Controller
{
    public function show(Request $request): Response
    {
        return Inertia::render('MemCofre/Ingest', [
            'source_types' => $this->optionsFromConfig('memcofre.source_types'),
            'modules'      => $this->listModuleNames(),
            'evidence_kinds' => [
                ['value' => 'bug',        'label' => 'Bug'],
                ['value' => 'rule',       'label' => 'Regra de negócio'],
                ['value' => 'flow',       'label' => 'Fluxo / jornada'],
                ['value' => 'quote',      'label' => 'Citação / decisão'],
                ['value' => 'screenshot', 'label' => 'Print de tela'],
                ['value' => 'decision',   'label' => 'Decisão arquitetural'],
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'type'            => 'required|in:screenshot,chat,error,file,text,url',
            'module_target'   => 'nullable|string|max:64',
            'title'           => 'nullable|string|max:255',
            'description'     => 'nullable|string|max:2000',
            'body_text'       => 'nullable|string',
            'source_url'      => 'nullable|url|max:500',
            'upload'          => 'nullable|file|max:20480',
            // Evidência inicial (opcional — criar direto do ingest)
            'create_evidence' => 'boolean',
            'evidence_kind'   => 'nullable|in:bug,rule,flow,quote,screenshot,decision',
            'evidence_content'=> 'nullable|string|max:5000',
        ]);

        $businessId = (int) (session('business.id') ?: $request->session()->get('user.business_id'));

        // Upload do arquivo, se houver
        $storagePath = null;
        if ($request->hasFile('upload')) {
            $file = $request->file('upload');
            $disk = config('memcofre.upload.disk', 'public');
            $dir = config('memcofre.upload.directory', 'memcofre');
            $filename = $dir . '/' . now()->format('Y/m/d') . '/' . Str::random(8) . '_' . $file->getClientOriginalName();
            Storage::disk($disk)->put($filename, file_get_contents($file->getRealPath()));
            $storagePath = $filename;
        }

        $source = DocSource::create([
            'business_id'   => $businessId,
            'created_by'    => auth()->user()->id,
            'module_target' => $validated['module_target'] ?? null,
            'type'          => $validated['type'],
            'title'         => $validated['title'] ?? null,
            'description'   => $validated['description'] ?? null,
            'storage_path'  => $storagePath,
            'source_url'    => $validated['source_url'] ?? null,
            'body_text'     => $validated['body_text'] ?? null,
            'meta'          => [
                'uploaded_name' => $request->hasFile('upload') ? $request->file('upload')->getClientOriginalName() : null,
                'uploaded_size' => $request->hasFile('upload') ? $request->file('upload')->getSize() : null,
                'uploaded_mime' => $request->hasFile('upload') ? $request->file('upload')->getClientMimeType() : null,
            ],
        ]);

        // Opcionalmente cria 1 evidência inicial
        if (! empty($validated['create_evidence']) && ! empty($validated['evidence_content'])) {
            DocEvidence::create([
                'business_id'    => $businessId,
                'source_id'      => $source->id,
                'module_target'  => $validated['module_target'] ?? null,
                'kind'           => $validated['evidence_kind'] ?? 'quote',
                'status'         => 'pending',
                'content'        => $validated['evidence_content'],
                'extracted_by_ai'=> false,
            ]);
        }

        return redirect()->route('memcofre.dashboard')
            ->with('success', 'Evidência registrada. Abra o Inbox para classificar.');
    }

    protected function optionsFromConfig(string $key): array
    {
        $map = config($key, []);
        $out = [];
        foreach ($map as $v => $l) {
            $out[] = ['value' => $v, 'label' => $l];
        }
        return $out;
    }

    protected function listModuleNames(): array
    {
        $dir = base_path('memory/requisitos');
        if (! is_dir($dir)) return [];
        $out = [];
        foreach (glob($dir . '/*.md') as $f) {
            $base = basename($f, '.md');
            if (in_array($base, ['INDEX', 'RECOMENDACOES'], true)) continue;
            $out[] = ['value' => $base, 'label' => $base];
        }
        return $out;
    }
}
