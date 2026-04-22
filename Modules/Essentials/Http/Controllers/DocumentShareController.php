<?php

namespace Modules\Essentials\Http\Controllers;

use App\User;
use App\Utils\ModuleUtil;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Essentials\Entities\Document;
use Modules\Essentials\Entities\DocumentShare;
use Modules\Essentials\Notifications\DocumentShareNotification;

/**
 * DocumentShareController — endpoint JSON (consumido pelo Dialog React).
 *
 * `edit` retorna a lista de usuários, roles e quem já está compartilhado.
 * `update` sincroniza os shares (cria/remove/notifica).
 */
class DocumentShareController extends Controller
{
    protected ModuleUtil $moduleUtil;

    public function __construct(ModuleUtil $moduleUtil)
    {
        $this->moduleUtil = $moduleUtil;
    }

    public function edit($id): JsonResponse
    {
        $businessId = $this->currentBusinessId();
        $this->authorizeAccess($businessId);

        $users = collect(User::forDropdown($businessId, false))
            ->map(fn ($label, $uid) => ['id' => (int) $uid, 'label' => (string) $label])
            ->values()->all();

        $roles = collect($this->moduleUtil->getDropdownForRoles($businessId))
            ->map(fn ($label, $rid) => ['id' => (int) $rid, 'label' => (string) $label])
            ->values()->all();

        $shared = DocumentShare::where('document_id', $id)->get()->groupBy('value_type');
        $sharedUsers = isset($shared['user']) ? $shared['user']->pluck('value')->map(fn ($v) => (int) $v)->all() : [];
        $sharedRoles = isset($shared['role']) ? $shared['role']->pluck('value')->map(fn ($v) => (int) $v)->all() : [];

        return response()->json([
            'users' => $users,
            'roles' => $roles,
            'shared_user_ids' => $sharedUsers,
            'shared_role_ids' => $sharedRoles,
            'document_id' => (int) $id,
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $businessId = $this->currentBusinessId();
        $this->authorizeAccess($businessId);

        $validated = $request->validate([
            'document_id' => 'required|integer|exists:essentials_documents,id',
            'user'        => 'nullable|array',
            'user.*'      => 'integer|exists:users,id',
            'role'        => 'nullable|array',
            'role.*'      => 'integer|exists:roles,id',
        ]);

        $documentId = (int) $validated['document_id'];
        $document   = Document::findOrFail($documentId);
        $userIds    = $validated['user'] ?? [];
        $roleIds    = $validated['role'] ?? [];

        // Users: cria novos + notifica + remove os que saíram
        $existingUser = [0];
        foreach ($userIds as $uid) {
            $existingUser[] = $uid;
            $share = DocumentShare::updateOrCreate([
                'document_id' => $documentId,
                'value_type'  => 'user',
                'value'       => $uid,
            ]);
            if ($share->wasRecentlyCreated) {
                $this->notify($document, $uid);
            }
        }
        DocumentShare::where('document_id', $documentId)
            ->where('value_type', 'user')
            ->whereNotIn('value', $existingUser)
            ->delete();

        // Roles (sem notificação — é compartilhamento amplo)
        $existingRole = [0];
        foreach ($roleIds as $rid) {
            $existingRole[] = $rid;
            DocumentShare::updateOrCreate([
                'document_id' => $documentId,
                'value_type'  => 'role',
                'value'       => $rid,
            ]);
        }
        DocumentShare::where('document_id', $documentId)
            ->where('value_type', 'role')
            ->whereNotIn('value', $existingRole)
            ->delete();

        return response()->json([
            'success' => true,
            'msg'     => __('lang_v1.success'),
        ]);
    }

    // ------------------------------------------------------------------------

    protected function notify(Document $document, int $userId): void
    {
        $user = User::find($userId);
        if ($user) {
            $user->notify(new DocumentShareNotification($document, auth()->user()));
        }
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
