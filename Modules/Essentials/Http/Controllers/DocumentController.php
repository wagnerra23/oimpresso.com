<?php

namespace Modules\Essentials\Http\Controllers;

use App\User;
use App\Utils\ModuleUtil;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Essentials\Entities\Document;
use Modules\Essentials\Entities\DocumentShare;

/**
 * DocumentController — versão Inertia.
 *
 * Paridade com Blade preservada:
 *   - Listagem com 2 tipos: "document" (arquivo físico) e "memos" (texto)
 *   - Upload de arquivo via moduleUtil->uploadFile (pasta public/uploads/documents)
 *   - Share por usuário ou role (DocumentShare) — carregado no Dialog do React
 *   - Download restrito a criador OU usuário/role compartilhado
 *   - Scope por business_id
 *
 * A Page React `Essentials/Documents/Index` apresenta as duas listas
 * (arquivos + memos) em tabs internas. Cada linha faz JOIN com
 * `essentials_document_shares` para expor com quem já foi compartilhado.
 */
class DocumentController extends Controller
{
    protected ModuleUtil $moduleUtil;

    public function __construct(ModuleUtil $moduleUtil)
    {
        $this->moduleUtil = $moduleUtil;
    }

    public function index(Request $request): Response
    {
        $businessId = $this->currentBusinessId();
        $this->authorizeAccess($businessId);

        $userId = auth()->user()->id;
        $roleId = optional(User::find($userId)->roles()->first())->id;

        $documents = $this->fetchByType($businessId, $userId, $roleId, 'document');
        $memos     = $this->fetchByType($businessId, $userId, $roleId, 'memos');

        return Inertia::render('Essentials/Documents/Index', [
            'documents' => $documents,
            'memos'     => $memos,
            'initialTab' => $request->string('type')->toString() === 'memos' ? 'memos' : 'documents',
            'me' => $userId,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $businessId = $this->currentBusinessId();
        $this->authorizeAccess($businessId);

        $type = $request->has('body') ? 'memos' : 'document';

        if ($type === 'document') {
            $request->validate([
                'name'        => 'required|file|max:20480', // 20MB
                'description' => 'nullable|string|max:2000',
            ]);
            $name = $this->moduleUtil->uploadFile($request, 'name', 'documents');
        } else {
            $request->validate([
                'name'        => 'required|string|max:255',
                'body'        => 'required|string',
                'description' => 'nullable|string|max:2000',
            ]);
            $name = $request->string('name');
        }

        Document::create([
            'business_id' => $businessId,
            'user_id'     => $request->user()->id,
            'type'        => $type,
            'name'        => $name,
            'description' => $type === 'memos'
                ? $request->string('body')
                : $request->input('description'),
        ]);

        return redirect()
            ->route('document.index', $type === 'memos' ? ['type' => 'memos'] : [])
            ->with('success', __('lang_v1.success'));
    }

    public function show(Request $request, $id)
    {
        $businessId = $this->currentBusinessId();
        $this->authorizeAccess($businessId);

        $memo = Document::where('business_id', $businessId)->findOrFail($id);

        return response()->json([
            'id'          => $memo->id,
            'name'        => $memo->name,
            'description' => $memo->description,
            'type'        => $memo->type,
            'created_at'  => optional($memo->created_at)->format('Y-m-d H:i'),
        ]);
    }

    public function edit()
    {
        return Inertia::location('/essentials/document');
    }

    public function update(Request $request)
    {
        return back();
    }

    public function destroy(Request $request, $id): RedirectResponse
    {
        $businessId = $this->currentBusinessId();
        $this->authorizeAccess($businessId);

        $userId = auth()->user()->id;
        $document = Document::where('business_id', $businessId)->find($id);

        if ($document && $document->user_id === $userId) {
            if ($document->type === 'document') {
                Storage::delete('documents/' . $document->name);
            }
            $document->delete();
        }

        return back()->with('success', __('lang_v1.deleted_success'));
    }

    public function download(Request $request, $id)
    {
        $businessId = $this->currentBusinessId();
        $this->authorizeAccess($businessId);

        $userId = auth()->user()->id;
        $roleId = optional(User::find($userId)->roles()->first())->id;

        $document = Document::where('business_id', $businessId)->findOrFail($id);
        $creator  = $document->user_id;

        $hasShareAccess = DocumentShare::where('document_id', $id)
            ->where(function ($query) use ($userId, $roleId) {
                $query->where(function ($q) use ($userId) {
                    $q->where('value_type', 'user')->where('value', $userId);
                })->orWhere(function ($q) use ($roleId) {
                    $q->where('value_type', 'role')->where('value', $roleId);
                });
            })
            ->exists();

        if ($userId !== $creator && ! $hasShareAccess) {
            abort(403);
        }

        $file = explode('_', $document->name, 2);
        $fileName = $file[1] ?? $document->name;

        return Storage::download('documents/' . $document->name, $fileName);
    }

    // ------------------------------------------------------------------------

    protected function fetchByType(int $businessId, int $userId, ?int $roleId, string $type): array
    {
        // Documentos criados por mim + compartilhados comigo (via user ou role)
        $documents = Document::leftJoin('essentials_document_shares', 'essentials_documents.id', '=', 'essentials_document_shares.document_id')
            ->join('users', 'essentials_documents.user_id', '=', 'users.id')
            ->where('essentials_documents.business_id', $businessId)
            ->where('essentials_documents.type', $type)
            ->where(function ($q) use ($userId, $roleId) {
                $q->where('essentials_documents.user_id', $userId);
                $q->orWhere(function ($inner) use ($userId) {
                    $inner->where('essentials_document_shares.value', $userId)
                        ->where('essentials_document_shares.value_type', 'user');
                });
                if ($roleId !== null) {
                    $q->orWhere(function ($inner) use ($roleId) {
                        $inner->where('essentials_document_shares.value', $roleId)
                            ->where('essentials_document_shares.value_type', 'role');
                    });
                }
            })
            ->select(
                'essentials_documents.id',
                'essentials_documents.name',
                'essentials_documents.description',
                'essentials_documents.type',
                'essentials_documents.user_id',
                'essentials_documents.created_at',
                'users.first_name',
                'users.last_name'
            )
            ->groupBy('essentials_documents.id')
            ->orderByDesc('essentials_documents.created_at')
            ->get();

        return $documents->map(function ($row) use ($userId, $type) {
            $file = explode('_', (string) $row->name, 2);
            $displayName = $type === 'document' ? ($file[1] ?? $row->name) : $row->name;

            return [
                'id'            => $row->id,
                'name'          => $row->name,                 // raw (pra download URL)
                'display_name'  => $displayName,               // sem prefix de timestamp
                'description'   => $row->description,
                'type'          => $row->type,
                'user_id'       => $row->user_id,
                'is_mine'       => $row->user_id === $userId,
                'shared_by'     => trim(($row->first_name ?? '') . ' ' . ($row->last_name ?? '')),
                'created_at'    => optional($row->created_at)->format('Y-m-d H:i'),
            ];
        })->values()->all();
    }

    protected function currentBusinessId(): int
    {
        return (int) (session('business.id') ?: request()->session()->get('user.business_id'));
    }

    protected function authorizeAccess(int $businessId): void
    {
        if (! (auth()->user()->can('superadmin') || $this->moduleUtil->hasThePermissionInSubscription($businessId, 'essentials_module'))) {
            abort(403, 'Unauthorized action.');
        }
    }
}
