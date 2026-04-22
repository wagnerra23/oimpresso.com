<?php

namespace Modules\Essentials\Http\Controllers;

use App\BusinessLocation;
use App\User;
use App\Utils\ModuleUtil;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Essentials\Entities\EssentialsMessage;
use Modules\Essentials\Notifications\NewMessageNotification;

/**
 * EssentialsMessageController — versão Inertia (chat mural).
 *
 * Paridade com o Blade:
 *   - Index: mural de mensagens do business, cronológico ASC, filtrado por
 *     localidades permitidas do usuário
 *   - Store: adiciona mensagem, dispara NewMessageNotification (sem spam:
 *     notificação DB só se passou >10min da última mesma localidade)
 *   - Destroy: remove mensagem própria
 *   - getNewMessages: polling JSON — usado pelo React para atualizar sem
 *     recarregar a página (intervalo `chat_refresh_interval` da config)
 *
 * Permissões Spatie: essentials.view_message, essentials.create_message.
 */
class EssentialsMessageController extends Controller
{
    protected ModuleUtil $moduleUtil;

    public function __construct(ModuleUtil $moduleUtil)
    {
        $this->moduleUtil = $moduleUtil;
    }

    public function index(): Response
    {
        $businessId = $this->currentBusinessId();
        $this->authorizeAccess($businessId);

        if (! auth()->user()->can('essentials.view_message') && ! auth()->user()->can('essentials.create_message')) {
            abort(403, 'Unauthorized action.');
        }

        $messages = $this->scopedMessagesQuery($businessId)->get()
            ->map(fn (EssentialsMessage $m) => $this->toMessageShape($m))
            ->values();

        $locations = BusinessLocation::forDropdown($businessId);
        $locationsArr = collect($locations)->map(fn ($label, $id) => [
            'id'    => (int) $id,
            'label' => (string) $label,
        ])->values()->all();

        return Inertia::render('Essentials/Messages/Index', [
            'messages'        => $messages,
            'locations'       => $locationsArr,
            'can'             => [
                'view'   => (bool) auth()->user()->can('essentials.view_message'),
                'create' => (bool) auth()->user()->can('essentials.create_message'),
            ],
            'refreshInterval' => (int) config('essentials::config.chat_refresh_interval', 20),
            'me'              => auth()->user()->id,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $businessId = $this->currentBusinessId();
        $this->authorizeAccess($businessId);

        if (! auth()->user()->can('essentials.create_message')) {
            abort(403, 'Unauthorized action.');
        }

        $request->validate([
            'message'     => 'required|string|max:5000',
            'location_id' => 'nullable|integer|exists:business_locations,id',
        ]);

        $userId = $request->user()->id;
        $text   = nl2br($request->string('message'));
        $locId  = $request->input('location_id') ?: null;

        $lastMessage = EssentialsMessage::where('business_id', $businessId)
            ->where(function ($q) use ($locId) {
                $q->where('location_id', $locId)->orWhereNull('location_id');
            })
            ->orderByDesc('created_at')
            ->first();

        $message = EssentialsMessage::create([
            'business_id' => $businessId,
            'user_id'     => $userId,
            'message'     => $text,
            'location_id' => $locId,
        ]);

        $dbNotification = empty($lastMessage) || $lastMessage->created_at->diffInMinutes(now()) > 10;
        $this->notify($message, $dbNotification, $businessId);

        return back()->with('success', __('lang_v1.success'));
    }

    public function destroy($id): RedirectResponse
    {
        $businessId = $this->currentBusinessId();
        $this->authorizeAccess($businessId);

        if (! auth()->user()->can('essentials.create_message')) {
            abort(403, 'Unauthorized action.');
        }

        EssentialsMessage::where('business_id', $businessId)
            ->where('user_id', auth()->user()->id)
            ->where('id', $id)
            ->delete();

        return back()->with('success', __('lang_v1.deleted_success'));
    }

    /**
     * Endpoint de polling — retorna somente mensagens mais recentes que o
     * último timestamp visto pelo cliente. React chama em loop (fetch).
     */
    public function getNewMessages(Request $request): JsonResponse
    {
        $businessId = $this->currentBusinessId();
        $this->authorizeAccess($businessId);

        if (! auth()->user()->can('essentials.view_message') && ! auth()->user()->can('essentials.create_message')) {
            abort(403, 'Unauthorized action.');
        }

        $lastChatTime = $request->input('last_chat_time');

        $query = EssentialsMessage::where('business_id', $businessId)
            ->where('user_id', '!=', auth()->user()->id)
            ->with(['sender:id,first_name,last_name,username'])
            ->orderBy('created_at', 'ASC');

        if (! empty($lastChatTime)) {
            $query->where('created_at', '>', $lastChatTime);
        }

        $permittedLocations = auth()->user()->permitted_locations();
        if ($permittedLocations !== 'all') {
            $query->where(function ($q) use ($permittedLocations) {
                $q->whereIn('location_id', $permittedLocations)
                    ->orWhereRaw('location_id IS NULL');
            });
        }

        $messages = $query->get()->map(fn ($m) => $this->toMessageShape($m))->values();

        return response()->json(['messages' => $messages]);
    }

    // ------------------------------------------------------------------------

    protected function scopedMessagesQuery(int $businessId)
    {
        $query = EssentialsMessage::where('business_id', $businessId)
            ->with(['sender:id,first_name,last_name,username'])
            ->orderBy('created_at', 'ASC');

        $permitted = auth()->user()->permitted_locations();
        if ($permitted !== 'all') {
            $query->where(function ($q) use ($permitted) {
                $q->whereIn('location_id', $permitted)
                    ->orWhereRaw('location_id IS NULL');
            });
        }

        return $query;
    }

    protected function notify(EssentialsMessage $message, bool $databaseNotification, int $businessId): void
    {
        $query = User::where('id', '!=', $message->user_id)
            ->where('business_id', $businessId);

        $users = empty($message->location_id)
            ? $query->get()
            : $query->permission('location.' . $message->location_id)->get();

        if ($users->count() === 0) {
            return;
        }

        $message->database_notification = $databaseNotification;
        \Notification::send($users, new NewMessageNotification($message));
    }

    protected function toMessageShape(EssentialsMessage $m): array
    {
        return [
            'id'          => $m->id,
            'user_id'     => $m->user_id,
            'message'     => $m->message,
            'location_id' => $m->location_id,
            'sender_name' => optional($m->sender)->user_full_name ?? '—',
            'created_at'  => optional($m->created_at)->toIso8601String(),
            'created_at_human' => optional($m->created_at)->diffForHumans(),
        ];
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
